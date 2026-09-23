<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ModerationRepository;
use App\Repositories\UserRepository;
use App\Support\Dates;

/**
 * Applies moderator decisions and writes the audit trail. Every method here
 * records a moderation_actions row — the log is not optional bookkeeping, it is
 * how the action is performed.
 */
final class ModerationService
{
    private ModerationRepository $moderation;

    private UserRepository $users;

    private NotificationService $notifications;

    public function __construct(
        ?ModerationRepository $moderation = null,
        ?UserRepository $users = null,
        ?NotificationService $notifications = null,
    ) {
        $this->moderation = $moderation ?? new ModerationRepository();
        $this->users = $users ?? new UserRepository();
        $this->notifications = $notifications ?? new NotificationService();
    }

    /**
     * @param array<string,mixed> $metadata
     */
    public function record(
        int $moderatorId,
        string $action,
        string $targetType,
        ?int $targetId,
        string $summary,
        ?string $reason = null,
        ?int $targetUserId = null,
        array $metadata = [],
        string $ip = '0.0.0.0',
    ): void {
        $this->moderation->log([
            'moderator_id' => $moderatorId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_user_id' => $targetUserId,
            'summary' => mb_substr($summary, 0, 255),
            'reason' => $reason === null ? null : mb_substr($reason, 0, 255),
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $ip,
        ]);
    }

    public function warn(int $userId, int $moderatorId, string $reason, ?string $details, int $points, ?int $expiresInDays, string $ip): int
    {
        $warningId = $this->moderation->createWarning([
            'user_id' => $userId,
            'moderator_id' => $moderatorId,
            'reason' => $reason,
            'details' => $details,
            'points' => max(0, min(20, $points)),
            'expires_at' => $expiresInDays !== null && $expiresInDays > 0
                ? Dates::addSeconds($expiresInDays * 86400)
                : null,
        ]);

        $this->users->update($userId, [
            'warning_points' => $this->moderation->activeWarningPoints($userId),
        ]);

        $this->notifications->notifyModeration(
            $userId,
            'You received a warning',
            $reason,
            '/settings/record',
            $moderatorId,
            true,
        );

        $target = $this->users->find($userId);

        $this->record(
            $moderatorId,
            'user.warn',
            'user',
            $userId,
            sprintf('Warned %s (%d point(s))', (string) ($target['username'] ?? '#' . $userId), $points),
            $reason,
            $userId,
            ['warning_id' => $warningId],
            $ip,
        );

        return $warningId;
    }

    public function suspend(int $userId, int $moderatorId, string $reason, ?string $note, int $days, string $ip): int
    {
        $banId = $this->moderation->createBan([
            'user_id' => $userId,
            'created_by' => $moderatorId,
            'type' => 'suspension',
            'reason' => $reason,
            'internal_note' => $note,
            'expires_at' => Dates::addSeconds(max(1, $days) * 86400),
            'is_active' => 1,
        ]);

        $this->users->update($userId, ['status' => 'suspended']);

        $this->notifications->notifyModeration(
            $userId,
            sprintf('Your account is suspended for %d day(s)', $days),
            $reason,
            '/settings/record',
            $moderatorId,
            true,
        );

        $target = $this->users->find($userId);

        $this->record(
            $moderatorId,
            'user.suspend',
            'user',
            $userId,
            sprintf('Suspended %s for %d day(s)', (string) ($target['username'] ?? '#' . $userId), $days),
            $reason,
            $userId,
            ['ban_id' => $banId, 'days' => $days],
            $ip,
        );

        return $banId;
    }

    public function ban(int $userId, int $moderatorId, string $reason, ?string $note, string $ip): int
    {
        $banId = $this->moderation->createBan([
            'user_id' => $userId,
            'created_by' => $moderatorId,
            'type' => 'ban',
            'reason' => $reason,
            'internal_note' => $note,
            'expires_at' => null,
            'is_active' => 1,
        ]);

        $this->users->update($userId, ['status' => 'banned']);

        $target = $this->users->find($userId);

        $this->record(
            $moderatorId,
            'user.ban',
            'user',
            $userId,
            sprintf('Banned %s permanently', (string) ($target['username'] ?? '#' . $userId)),
            $reason,
            $userId,
            ['ban_id' => $banId],
            $ip,
        );

        return $banId;
    }

    public function lift(int $userId, int $moderatorId, string $ip): void
    {
        $this->moderation->liftAllForUser($userId, $moderatorId);
        $this->users->update($userId, ['status' => 'active']);

        $target = $this->users->find($userId);

        $this->notifications->notifyModeration(
            $userId,
            'Your account restriction was lifted',
            'You can post on the board again.',
            '/settings/record',
            $moderatorId,
        );

        $this->record(
            $moderatorId,
            'user.unban',
            'user',
            $userId,
            sprintf('Lifted restrictions on %s', (string) ($target['username'] ?? '#' . $userId)),
            null,
            $userId,
            [],
            $ip,
        );
    }

    /** Called on each request cycle to clear suspensions that have run out. */
    public function expireLapsedBans(): void
    {
        foreach ($this->moderation->expireBans() as $userId) {
            $user = $this->users->find($userId);

            if ($user !== null && (string) $user['status'] === 'suspended') {
                $this->users->update($userId, ['status' => 'active']);
            }
        }
    }
}
