<?php

declare(strict_types=1);

namespace App\Services\Chat;

/**
 * Transport-neutral representation of a chat message. A future WebSocket or
 * SSE transport serialises exactly this object; the current HTTP transport
 * persists it and re-renders the page.
 */
final class ChatMessage
{
    public function __construct(
        public readonly int $roomId,
        public readonly ?int $userId,
        public readonly string $content,
        public readonly string $type = 'message',
        public readonly ?string $ipAddress = null,
        public readonly ?int $id = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $username = null,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['room_id'],
            $row['user_id'] === null ? null : (int) $row['user_id'],
            (string) $row['content'],
            (string) ($row['type'] ?? 'message'),
            $row['ip_address'] ?? null,
            isset($row['id']) ? (int) $row['id'] : null,
            $row['created_at'] ?? null,
            $row['username'] ?? null,
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'room_id' => $this->roomId,
            'user_id' => $this->userId,
            'username' => $this->username,
            'type' => $this->type,
            'content' => $this->content,
            'created_at' => $this->createdAt,
        ];
    }
}
