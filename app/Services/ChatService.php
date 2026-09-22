<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ChatRepository;
use App\Services\Chat\ChatMessage;
use App\Services\Chat\ChatTransport;
use App\Services\Chat\TransportFactory;
use App\Support\Dates;

/**
 * Feature-level chat API used by controllers. It owns the rules (slow mode,
 * mutes, length limits) and delegates delivery to the configured transport.
 */
final class ChatService
{
    private ChatRepository $repository;

    private ChatTransport $transport;

    private SettingsService $settings;

    private ModerationService $moderation;

    public function __construct(
        ?ChatRepository $repository = null,
        ?ChatTransport $transport = null,
        ?SettingsService $settings = null,
        ?ModerationService $moderation = null,
    ) {
        $this->repository = $repository ?? new ChatRepository();
        $this->transport = $transport ?? TransportFactory::make();
        $this->settings = $settings ?? SettingsService::instance();
        $this->moderation = $moderation ?? new ModerationService();
    }

    public function transport(): ChatTransport
    {
        return $this->transport;
    }

    public function enabled(): bool
    {
        return $this->settings->bool('chat_enabled', true);
    }

    /** @return array<int,array<string,mixed>> */
    public function rooms(): array
    {
        return $this->repository->rooms();
    }

    /** @return array<string,mixed>|null */
    public function room(?string $slug): ?array
    {
        if ($slug === null || $slug === '') {
            return $this->repository->defaultRoom();
        }

        return $this->repository->findRoomBySlug($slug);
    }

    /** @return array<int,array<string,mixed>> */
    public function history(int $roomId, bool $includeDeleted = false): array
    {
        return $this->transport->history($roomId, $this->settings->int('chat_history_limit', 60), $includeDeleted);
    }

    /** @return array<int,array<string,mixed>> */
    public function presence(int $roomId): array
    {
        return $this->transport->presence($roomId, $this->settings->int('chat_presence_window', 300));
    }

    public function join(int $roomId, int $userId): void
    {
        $this->transport->join($roomId, $userId);
    }

    /**
     * @param array<string,mixed> $room
     * @return array{ok:bool,message?:string,id?:int}
     */
    public function post(array $room, int $userId, string $content, string $ip): array
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'message' => 'Chat is currently disabled.'];
        }

        if ((int) $room['is_readonly'] === 1) {
            return ['ok' => false, 'message' => 'This room is read-only.'];
        }

        $restriction = $this->restrictionFor($userId, (int) $room['id']);

        if ($restriction !== null) {
            return [
                'ok' => false,
                'message' => sprintf(
                    'You are %s from chat%s. Reason: %s',
                    (string) $restriction['type'] === 'ban' ? 'banned' : 'muted',
                    $restriction['expires_at'] === null ? '' : ' until ' . Dates::format((string) $restriction['expires_at']),
                    (string) $restriction['reason'],
                ),
            ];
        }

        $content = trim($content);
        $maxLength = $this->settings->int('chat_max_length', 500);

        if ($content === '') {
            return ['ok' => false, 'message' => 'Write something before sending.'];
        }

        if (mb_strlen($content, 'UTF-8') > $maxLength) {
            return ['ok' => false, 'message' => sprintf('Messages are limited to %d characters.', $maxLength)];
        }

        $slowMode = max((int) $room['slow_mode'], $this->settings->int('chat_slow_mode', 0));

        if ($slowMode > 0) {
            $last = $this->repository->lastMessageAt((int) $room['id'], $userId);
            $lastDate = Dates::parse($last);

            if ($lastDate !== null) {
                $elapsed = Dates::now()->getTimestamp() - $lastDate->getTimestamp();

                if ($elapsed < $slowMode) {
                    return [
                        'ok' => false,
                        'message' => sprintf('Slow mode is on — wait %d more second(s).', $slowMode - $elapsed),
                    ];
                }
            }
        }

        $published = $this->transport->publish(new ChatMessage(
            (int) $room['id'],
            $userId,
            $content,
            'message',
            $ip,
        ));

        $this->transport->join((int) $room['id'], $userId);

        return ['ok' => true, 'id' => (int) $published->id];
    }

    public function systemMessage(int $roomId, string $content): void
    {
        $this->transport->publish(new ChatMessage($roomId, null, $content, 'system'));
    }

    /** @return array<string,mixed>|null */
    public function restrictionFor(int $userId, ?int $roomId = null): ?array
    {
        return $this->repository->activeRestriction($userId, $roomId);
    }

    public function deleteMessage(int $messageId, int $moderatorId, string $ip): bool
    {
        $message = $this->repository->findMessage($messageId);

        if ($message === null || (int) $message['is_deleted'] === 1) {
            return false;
        }

        $this->transport->retract($messageId, $moderatorId);

        $this->moderation->record(
            $moderatorId,
            'chat.message.delete',
            'chat_message',
            $messageId,
            sprintf('Deleted a chat message by %s', (string) ($message['username'] ?? 'a guest')),
            null,
            $message['user_id'] === null ? null : (int) $message['user_id'],
            [],
            $ip,
        );

        return true;
    }

    public function mute(int $userId, int $moderatorId, ?int $roomId, string $reason, int $minutes, string $ip): int
    {
        $id = $this->repository->createBan([
            'user_id' => $userId,
            'moderator_id' => $moderatorId,
            'room_id' => $roomId,
            'type' => 'mute',
            'reason' => $reason,
            'expires_at' => $minutes > 0 ? Dates::addSeconds($minutes * 60) : null,
            'is_active' => 1,
        ]);

        $this->moderation->record(
            $moderatorId,
            'chat.mute',
            'user',
            $userId,
            sprintf('Muted user #%d in chat for %s', $userId, $minutes > 0 ? $minutes . ' minute(s)' : 'an indefinite period'),
            $reason,
            $userId,
            ['chat_ban_id' => $id],
            $ip,
        );

        return $id;
    }

    public function banFromChat(int $userId, int $moderatorId, string $reason, string $ip): int
    {
        $id = $this->repository->createBan([
            'user_id' => $userId,
            'moderator_id' => $moderatorId,
            'room_id' => null,
            'type' => 'ban',
            'reason' => $reason,
            'expires_at' => null,
            'is_active' => 1,
        ]);

        $this->moderation->record(
            $moderatorId,
            'chat.ban',
            'user',
            $userId,
            sprintf('Banned user #%d from chat', $userId),
            $reason,
            $userId,
            ['chat_ban_id' => $id],
            $ip,
        );

        return $id;
    }

    public function liftRestriction(int $restrictionId, int $moderatorId, string $ip): void
    {
        $this->repository->liftRestriction($restrictionId);

        $this->moderation->record(
            $moderatorId,
            'chat.restriction.lift',
            'chat_ban',
            $restrictionId,
            sprintf('Lifted chat restriction #%d', $restrictionId),
            null,
            null,
            [],
            $ip,
        );
    }

    public function purgeUser(int $userId, int $roomId, int $moderatorId, string $ip): int
    {
        $count = $this->repository->purgeUserMessages($userId, $roomId, $moderatorId);

        $this->moderation->record(
            $moderatorId,
            'chat.purge',
            'user',
            $userId,
            sprintf('Purged %d chat message(s) from user #%d', $count, $userId),
            null,
            $userId,
            ['room_id' => $roomId],
            $ip,
        );

        return $count;
    }
}
