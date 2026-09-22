<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Repositories\ChatRepository;

/**
 * Default transport: plain HTTP. A message is posted with a normal form, the
 * server stores it and renders the updated transcript on the redirect that
 * follows. Viewers see new messages when the page is reloaded.
 */
final class HttpTransport implements ChatTransport
{
    private ChatRepository $repository;

    public function __construct(?ChatRepository $repository = null)
    {
        $this->repository = $repository ?? new ChatRepository();
    }

    public function name(): string
    {
        return 'http';
    }

    public function describe(): string
    {
        return 'Server-rendered HTTP transport. Messages are persisted on POST and the transcript is re-rendered on the following GET. No client-side code is involved.';
    }

    public function isRealtime(): bool
    {
        return false;
    }

    public function publish(ChatMessage $message): ChatMessage
    {
        $id = $this->repository->createMessage([
            'room_id' => $message->roomId,
            'user_id' => $message->userId,
            'type' => $message->type,
            'content' => $message->content,
            'ip_address' => $message->ipAddress,
        ]);

        return new ChatMessage(
            $message->roomId,
            $message->userId,
            $message->content,
            $message->type,
            $message->ipAddress,
            $id,
            null,
            $message->username,
        );
    }

    public function history(int $roomId, int $limit, bool $includeDeleted = false): array
    {
        return $this->repository->recentMessages($roomId, $limit, $includeDeleted);
    }

    public function join(int $roomId, int $userId): void
    {
        $this->repository->touchPresence($roomId, $userId);
    }

    public function presence(int $roomId, int $windowSeconds): array
    {
        return $this->repository->presentUsers($roomId, $windowSeconds);
    }

    public function retract(int $messageId, int $moderatorId): void
    {
        $this->repository->deleteMessage($messageId, $moderatorId);
    }

    public function endpoint(): ?string
    {
        return null;
    }
}
