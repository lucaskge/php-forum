<?php

declare(strict_types=1);

namespace App\Install;

use App\Support\Config;
use App\Support\Database;
use App\Support\Migrator;
use App\Support\Str;
use App\Support\Url;
use App\Support\Validator;
use Database\Seeders\CoreSeeder;
use PDO;
use PDOException;
use Throwable;

/**
 * The web installer.
 *
 * Exists so the board can be put on a server with nothing but FTP or a file
 * manager: upload the files, open install.php, fill in two forms. It writes the
 * .env file, creates the database if it is allowed to, applies the migrations,
 * seeds the configuration and creates the first administrator.
 *
 * It refuses to run once the board is installed, and offers to delete itself
 * afterwards — an installer left reachable on a live site is a way in.
 */
final class Installer
{
    public const LOCK_FILE = '/storage/installed.lock';

    /** Files and directories removed when the installer is dismissed. */
    private const REMOVABLE = [
        '/public/install.php',
        '/app/Install/Installer.php',
        '/app/Install',
    ];

    /**
     * Why the installer will not run, or null when it will.
     *
     * Naming the reason matters: "already installed" with no explanation and a
     * single delete button is how somebody ends up removing the installer while
     * trying to find out what it does.
     */
    public function blockedReason(): ?string
    {
        if (is_file(BASE_PATH . self::LOCK_FILE)) {
            return 'lock';
        }

        if (!is_file(BASE_PATH . '/.env')) {
            return null;
        }

        // A .env pointing at a database that already holds accounts means this
        // is a live board, whatever the lock file says.
        try {
            if ((int) Database::instance()->scalar('SELECT COUNT(*) FROM users') > 0) {
                return 'accounts';
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    public function isInstalled(): bool
    {
        return $this->blockedReason() !== null;
    }

    /**
     * Environment checks, shown before anything is written.
     *
     * @return array<int,array{label:string,ok:bool,required:bool,detail:string}>
     */
    public function requirements(): array
    {
        $checks = [];

        $checks[] = [
            'label' => 'PHP 8.3 or newer',
            'ok' => PHP_VERSION_ID >= 80300,
            'required' => true,
            'detail' => 'Running ' . PHP_VERSION,
        ];

        foreach ([
            'pdo_mysql' => 'Talks to MySQL or MariaDB.',
            'mbstring' => 'Handles text that is not plain ASCII.',
            'json' => 'Used by settings and the moderation log.',
            'fileinfo' => 'Identifies uploaded files by their contents.',
        ] as $extension => $why) {
            $checks[] = [
                'label' => 'Extension: ' . $extension,
                'ok' => extension_loaded($extension),
                'required' => true,
                'detail' => $why,
            ];
        }

        $checks[] = [
            'label' => 'Extension: gd',
            'ok' => extension_loaded('gd'),
            'required' => false,
            'detail' => 'Needed for avatar uploads. Without it the board works, but members cannot upload a picture.',
        ];

        foreach ([
            '/storage/logs' => 'Application and security logs.',
            '/storage/cache' => 'Cache directory.',
            '/public/uploads/avatars' => 'Where avatars are stored.',
        ] as $path => $why) {
            $checks[] = [
                'label' => 'Writable: ' . ltrim($path, '/'),
                'ok' => is_dir(BASE_PATH . $path) && is_writable(BASE_PATH . $path),
                'required' => true,
                'detail' => $why,
            ];
        }

        $checks[] = [
            'label' => 'Writable: project root',
            'ok' => is_writable(BASE_PATH),
            'required' => false,
            'detail' => 'Lets the installer write .env for you. If it is not writable you will be shown the file to create by hand.',
        ];

        return $checks;
    }

    /** @param array<int,array{ok:bool,required:bool}> $checks */
    public function requirementsMet(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['required'] && !$check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Connects with the given credentials, creating the database when the
     * account is allowed to. On shared hosting it usually is not — the panel
     * creates it — so a clear message matters more than a clever fallback.
     *
     * @param array<string,string> $input
     * @return array{ok:bool,message:string,created:bool,errors:array<string,string>}
     */
    public function testDatabase(array $input): array
    {
        $validator = Validator::make($input)
            ->label('host', 'Database host')->required('host')->maxLength('host', 190)
            ->label('database', 'Database name')->required('database')->maxLength('database', 64)
            ->label('username', 'Database user')->required('username')->maxLength('username', 190);

        if ($validator->passes() && !Migrator::isValidDatabaseName($input['database'])) {
            $validator->fail('database', 'Database names may contain letters, digits and underscores only.');
        }

        $port = (int) ($input['port'] ?? 3306);

        if ($port < 1 || $port > 65535) {
            $validator->fail('port', 'Enter a port between 1 and 65535.');
        }

        if ($validator->fails()) {
            return [
                'ok' => false,
                'message' => (string) $validator->firstError(),
                'created' => false,
                'errors' => $validator->errors(),
            ];
        }

        try {
            $pdo = Migrator::connectToServer([
                'host' => $input['host'],
                'port' => $port,
                'username' => $input['username'],
                'password' => $input['password'] ?? '',
            ]);
        } catch (PDOException $exception) {
            return [
                'ok' => false,
                'message' => 'Could not reach the database server: ' . $this->cleanDriverMessage($exception),
                'created' => false,
                'errors' => ['host' => 'Check the host, port, user and password.'],
            ];
        }

        $database = (string) $input['database'];

        if (!$this->databaseExists($pdo, $database)) {
            try {
                Migrator::selectDatabase($pdo, $database);
            } catch (Throwable $exception) {
                return [
                    'ok' => false,
                    'message' => sprintf(
                        'The database "%s" does not exist and this account may not create it (%s). Create it in your hosting panel and come back.',
                        $database,
                        $this->cleanDriverMessage($exception),
                    ),
                    'created' => false,
                    'errors' => ['database' => 'Create this database first, then try again.'],
                ];
            }

            return ['ok' => true, 'message' => sprintf('Connected, and created the database "%s".', $database), 'created' => true, 'errors' => []];
        }

        try {
            $pdo->exec(sprintf('USE `%s`', $database));
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => sprintf('The database "%s" exists but this account cannot use it (%s).', $database, $this->cleanDriverMessage($exception)),
                'created' => false,
                'errors' => ['username' => 'Grant this user access to the database.'],
            ];
        }

        $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn();

        if ($tables > 0) {
            return [
                'ok' => true,
                'message' => sprintf('Connected. Note that "%s" already contains %d table(s); installing will add to it.', $database, $tables),
                'created' => false,
                'errors' => [],
            ];
        }

        return ['ok' => true, 'message' => sprintf('Connected to "%s".', $database), 'created' => false, 'errors' => []];
    }

    /**
     * Validates the site and administrator details before anything is written.
     *
     * @param array<string,string> $site
     * @param array<string,string> $admin
     * @return array<string,string> Field errors, empty when everything passes.
     */
    public function validateDetails(array $site, array $admin): array
    {
        $validator = Validator::make([
            'site_name' => $site['site_name'] ?? '',
            'site_url' => $site['site_url'] ?? '',
            'username' => $admin['username'] ?? '',
            'email' => $admin['email'] ?? '',
            'password' => $admin['password'] ?? '',
            'password_confirmation' => $admin['password_confirmation'] ?? '',
        ])
            ->label('site_name', 'Board name')->required('site_name')->between('site_name', 2, 64)
            ->label('site_url', 'Board address')->required('site_url')->maxLength('site_url', 190)
            ->label('username', 'Administrator username')->required('username')->username('username')
            ->label('email', 'Administrator e-mail')->required('email')->email('email')
            ->required('password')->password('password')
            ->label('password_confirmation', 'Password confirmation')->matches('password_confirmation', 'password');

        if (($site['site_url'] ?? '') !== '' && filter_var($site['site_url'], FILTER_VALIDATE_URL) === false) {
            $validator->fail('site_url', 'Enter the full address, including https://');
        }

        return $validator->errors();
    }

    /**
     * Writes .env, migrates, seeds the configuration and creates the first
     * administrator.
     *
     * @param array<string,string> $db
     * @param array<string,string> $site
     * @param array<string,string> $admin
     * @return array{ok:bool,message:string,log:array<int,string>,env:string,env_written:bool}
     */
    public function install(array $db, array $site, array $admin): array
    {
        $log = [];
        $env = $this->buildEnv($db, $site);
        $envWritten = $this->writeEnv($env);

        $log[] = $envWritten
            ? 'Wrote the configuration file (.env).'
            : 'Could not write .env — you will be shown its contents to save by hand.';

        // Apply the settings for this request whether or not .env landed, so
        // the rest of the installation can proceed either way.
        Config::set('app.url', rtrim((string) $site['site_url'], '/'));
        Config::set('app.url_mode', Url::normaliseMode((string) ($site['url_mode'] ?? 'query')));
        Config::set('database.host', $db['host']);
        Config::set('database.port', (int) $db['port']);
        Config::set('database.database', $db['database']);
        Config::set('database.username', $db['username']);
        Config::set('database.password', $db['password'] ?? '');

        try {
            $pdo = Migrator::connectToServer([
                'host' => $db['host'],
                'port' => (int) $db['port'],
                'username' => $db['username'],
                'password' => $db['password'] ?? '',
            ]);

            Migrator::selectDatabase($pdo, (string) $db['database']);
            Database::swap($pdo);

            $applied = (new Migrator())->run();
            $log[] = sprintf('Applied %d migration(s).', count($applied));

            $core = new CoreSeeder();

            foreach ($core->run() as $line) {
                $log[] = $line;
            }

            $seeded = count($core->log());

            $core->createAdministrator(
                (string) $admin['username'],
                (string) $admin['email'],
                (string) $admin['password'],
            );

            // Whatever the seeder recorded while creating the account.
            foreach (array_slice($core->log(), $seeded) as $line) {
                $log[] = $line;
            }

            // The board name the operator chose wins over the seeded default.
            Database::instance()->update(
                'settings',
                ['value' => (string) $site['site_name']],
                'key_name = :key',
                ['key' => 'site_name'],
            );

            $this->lock();
            $log[] = 'Locked the installer.';
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'message' => 'Installation failed: ' . $exception->getMessage(),
                'log' => $log,
                'env' => $env,
                'env_written' => $envWritten,
            ];
        }

        return [
            'ok' => true,
            'message' => 'The board is installed.',
            'log' => $log,
            'env' => $env,
            'env_written' => $envWritten,
        ];
    }

    /**
     * @param array<string,string> $db
     * @param array<string,string> $site
     */
    public function buildEnv(array $db, array $site): string
    {
        $lines = [
            'APP_NAME=' . $this->quote((string) $site['site_name']),
            'APP_ENV=production',
            'APP_DEBUG=false',
            'APP_URL=' . rtrim((string) $site['site_url'], '/'),
            'APP_KEY=' . Str::random(32),
            'APP_TIMEZONE=UTC',
            '',
            'APP_URL_MODE=' . Url::normaliseMode((string) ($site['url_mode'] ?? 'query')),
            'APP_ENTRYPOINT=/index.php',
            '',
            'DB_HOST=' . $db['host'],
            'DB_PORT=' . (int) $db['port'],
            'DB_DATABASE=' . $db['database'],
            'DB_USERNAME=' . $db['username'],
            'DB_PASSWORD=' . $this->quote((string) ($db['password'] ?? '')),
            'DB_CHARSET=utf8mb4',
            '',
            'SESSION_NAME=coldwire_session',
            'SESSION_LIFETIME=7200',
            'SESSION_SECURE=' . (str_starts_with((string) $site['site_url'], 'https://') ? 'true' : 'false'),
            'SESSION_SAMESITE=Lax',
            '',
            'MAIL_TRANSPORT=log',
            'MAIL_FROM=no-reply@' . (parse_url((string) $site['site_url'], PHP_URL_HOST) ?: 'localhost'),
            'MAIL_FROM_NAME=' . $this->quote((string) $site['site_name']),
            '',
            'UPLOAD_MAX_AVATAR_BYTES=4194304',
        ];

        return implode("\n", $lines) . "\n";
    }

    public function writeEnv(string $contents): bool
    {
        $file = BASE_PATH . '/.env';

        if (is_file($file) && !is_writable($file)) {
            return false;
        }

        if (!is_file($file) && !is_writable(BASE_PATH)) {
            return false;
        }

        if (@file_put_contents($file, $contents) === false) {
            return false;
        }

        // The file holds the database password and the application key.
        @chmod($file, 0640);

        return true;
    }

    public function lock(): void
    {
        @file_put_contents(
            BASE_PATH . self::LOCK_FILE,
            "Installed on " . gmdate('Y-m-d H:i:s') . " UTC.\nDelete this file only if you intend to run the installer again.\n",
        );
    }

    /**
     * Deletes the installer. Reports what it could not remove rather than
     * claiming success, because a leftover installer is the whole risk.
     *
     * @return array{removed:array<int,string>,failed:array<int,string>}
     */
    public function removeInstaller(): array
    {
        $removed = [];
        $failed = [];

        foreach (self::REMOVABLE as $relative) {
            $path = BASE_PATH . $relative;

            if (!file_exists($path)) {
                continue;
            }

            $ok = is_dir($path) ? @rmdir($path) : @unlink($path);

            if ($ok) {
                $removed[] = ltrim($relative, '/');
            } else {
                $failed[] = ltrim($relative, '/');
            }
        }

        return ['removed' => $removed, 'failed' => $failed];
    }

    private function databaseExists(PDO $pdo, string $database): bool
    {
        $statement = $pdo->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :name');
        $statement->execute(['name' => $database]);

        return $statement->fetchColumn() !== false;
    }

    /** Driver messages can be long and carry credentials; keep them short. */
    private function cleanDriverMessage(Throwable $exception): string
    {
        $message = preg_replace('/SQLSTATE\[[^\]]+\]\s*(\[\d+\])?\s*/', '', $exception->getMessage()) ?? $exception->getMessage();
        $message = ltrim($message, ": \t\n");

        return Str::limit(trim($message), 160);
    }

    private function quote(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
}
