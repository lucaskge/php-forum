<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserStatus;
use App\Repositories\ModerationRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Support\Config;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\Dates;
use App\Support\Logger;
use App\Support\RateLimiter;
use App\Support\Session;
use App\Support\Str;

final class AuthService
{
    private const SESSION_KEY = 'auth_user_id';

    private const FINGERPRINT_KEY = 'auth_fingerprint';

    private static ?AuthService $instance = null;

    private UserRepository $users;

    private RoleRepository $roles;

    private ModerationRepository $moderation;

    /** @var array<string,mixed>|null */
    private ?array $cachedUser = null;

    private bool $resolved = false;

    public function __construct(
        ?UserRepository $users = null,
        ?RoleRepository $roles = null,
        ?ModerationRepository $moderation = null,
    ) {
        $this->users = $users ?? new UserRepository();
        $this->roles = $roles ?? new RoleRepository();
        $this->moderation = $moderation ?? new ModerationRepository();
    }

    public static function instance(): AuthService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->cachedUser;
        }

        $this->resolved = true;
        $id = Session::get(self::SESSION_KEY);

        if (!is_int($id) && !is_numeric($id)) {
            return $this->cachedUser = null;
        }

        $user = $this->users->find((int) $id);

        if ($user === null) {
            $this->logout();

            return $this->cachedUser = null;
        }

        // A session bound to a different browser fingerprint is discarded.
        $fingerprint = Session::get(self::FINGERPRINT_KEY);

        if (is_string($fingerprint) && $fingerprint !== $this->fingerprint()) {
            Logger::security('Session fingerprint mismatch', ['user_id' => $user['id']]);
            $this->logout();

            return $this->cachedUser = null;
        }

        return $this->cachedUser = $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    /**
     * @return array{ok:bool,user?:array<string,mixed>,message?:string}
     */
    public function attempt(string $identifier, string $password, string $ip, string $userAgent): array
    {
        $throttleKey = 'login:' . mb_strtolower($identifier) . '|' . $ip;

        if (RateLimiter::tooManyAttempts($throttleKey, 'login')) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return [
                'ok' => false,
                'message' => sprintf('Too many failed attempts. Try again in %d minute(s).', max(1, (int) ceil($seconds / 60))),
            ];
        }

        $user = $this->users->findByIdentifier($identifier);

        // Always run a hash comparison so a missing account and a wrong
        // password take a comparable amount of time.
        $hash = $user['password_hash'] ?? '$2y$12$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidinv';
        $valid = password_verify($password, (string) $hash);

        if ($user === null || !$valid) {
            RateLimiter::hit($throttleKey, 'login');
            $this->recordAttempt($identifier, $ip, false, $userAgent);

            return ['ok' => false, 'message' => 'Those credentials do not match any account.'];
        }

        if (UserStatus::tryFrom((string) $user['status']) === UserStatus::Banned) {
            $ban = $this->moderation->activeBan((int) $user['id']);

            return [
                'ok' => false,
                'message' => 'This account is banned. Reason: ' . ($ban['reason'] ?? 'not specified') . '.',
            ];
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $this->users->update((int) $user['id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $this->recordAttempt($identifier, $ip, true, $userAgent);
        $this->login($user, $ip);

        return ['ok' => true, 'user' => $user];
    }

    /** @param array<string,mixed> $user */
    public function login(array $user, string $ip = '0.0.0.0'): void
    {
        // Session fixation defence: a brand new session id for the new identity.
        Session::regenerate();
        Session::put(self::SESSION_KEY, (int) $user['id']);
        Session::put(self::FINGERPRINT_KEY, $this->fingerprint());
        Csrf::rotate();

        $this->users->recordLogin((int) $user['id'], $ip);
        $this->cachedUser = $user;
        $this->resolved = true;
    }

    public function logout(): void
    {
        $id = Session::get(self::SESSION_KEY);

        if ($id !== null) {
            try {
                Database::instance()->delete('sessions', 'id = :id', ['id' => Session::id()]);
            } catch (\Throwable) {
                // Session tracking is best-effort.
            }
        }

        Session::invalidate();
        Csrf::rotate();
        $this->cachedUser = null;
        $this->resolved = true;
    }

    /**
     * @param array{username:string,email:string,password:string} $data
     * @return array{ok:bool,user_id?:int,message?:string}
     */
    public function register(array $data, string $ip): array
    {
        $defaultRole = $this->roles->defaultRole();

        if ($defaultRole === null) {
            return ['ok' => false, 'message' => 'No default role is configured. Contact an administrator.'];
        }

        $userId = Database::instance()->transaction(function () use ($data, $ip, $defaultRole): int {
            $userId = $this->users->create([
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'primary_role_id' => (int) $defaultRole['id'],
                'timezone' => 'UTC',
                'status' => 'active',
                'last_ip' => $ip,
                'last_active_at' => Dates::nowString(),
            ]);

            $this->roles->assign($userId, (int) $defaultRole['id']);

            return $userId;
        });

        Logger::security('Account registered', ['user_id' => $userId, 'ip' => $ip]);

        return ['ok' => true, 'user_id' => $userId];
    }

    public function changePassword(int $userId, string $newPassword): void
    {
        $this->users->update($userId, ['password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)]);

        // Any outstanding reset links become useless once the password changes.
        Database::instance()->execute(
            'UPDATE password_resets SET used_at = :now WHERE user_id = :user AND used_at IS NULL',
            ['now' => Dates::nowString(), 'user' => $userId],
        );
    }

    public function verifyPassword(int $userId, string $password): bool
    {
        $user = $this->users->find($userId);

        return $user !== null && password_verify($password, (string) $user['password_hash']);
    }

    // ------------------------------------------------------------------
    // Password reset
    // ------------------------------------------------------------------

    public function createPasswordReset(int $userId, string $ip): string
    {
        $token = Str::random(32);

        Database::instance()->insert('password_resets', [
            'user_id' => $userId,
            'token_hash' => hash('sha256', $token),
            'ip_address' => $ip,
            'expires_at' => Dates::addSeconds((int) Config::get('security.reset_token_lifetime', 3600)),
            'created_at' => Dates::nowString(),
        ]);

        return $token;
    }

    /** @return array<string,mixed>|null */
    public function findValidReset(string $token): ?array
    {
        return Database::instance()->selectOne(
            'SELECT pr.*, u.username, u.email FROM password_resets pr
             INNER JOIN users u ON u.id = pr.user_id
             WHERE pr.token_hash = :hash AND pr.used_at IS NULL AND pr.expires_at > UTC_TIMESTAMP()',
            ['hash' => hash('sha256', $token)],
        );
    }

    public function consumeReset(int $resetId): void
    {
        Database::instance()->update('password_resets', ['used_at' => Dates::nowString()], 'id = :id', ['id' => $resetId]);
    }

    // ------------------------------------------------------------------
    // Ban state
    // ------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public function activeRestriction(): ?array
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        if (UserStatus::tryFrom((string) $user['status'])?->isRestricted() !== true) {
            return null;
        }

        $ban = $this->moderation->activeBan((int) $user['id']);

        if ($ban === null) {
            // The suspension has lapsed; restore the account automatically.
            $this->users->update((int) $user['id'], ['status' => 'active']);
            $this->cachedUser = null;
            $this->resolved = false;

            return null;
        }

        return $ban;
    }

    private function recordAttempt(string $identifier, string $ip, bool $successful, string $userAgent): void
    {
        try {
            Database::instance()->insert('login_attempts', [
                'identifier' => mb_substr($identifier, 0, 190),
                'ip_address' => $ip,
                'successful' => $successful ? 1 : 0,
                'user_agent' => $userAgent,
                'created_at' => Dates::nowString(),
            ]);
        } catch (\Throwable $exception) {
            Logger::exception($exception);
        }
    }

    private function fingerprint(): string
    {
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $key = (string) Config::get('app.key', 'coldwire');

        return hash('sha256', $key . '|' . $agent);
    }

    /** Forces the next user() call to reload from the database. */
    public function forget(): void
    {
        $this->cachedUser = null;
        $this->resolved = false;
    }
}
