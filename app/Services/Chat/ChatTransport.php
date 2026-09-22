<?php

declare(strict_types=1);

namespace App\Services\Chat;

/**
 * The seam between the chat feature and how messages actually travel.
 *
 * The shipped implementation (HttpTransport) stores messages and lets the
 * server re-render the transcript on the next request, which keeps the board
 * working with zero JavaScript. Adding real-time delivery later means writing
 * another class against this interface and pointing the `chat_transport`
 * setting at it — no controller, template or route has to change.
 */
interface ChatTransport
{
    public function name(): string;

    public function describe(): string;

    /** True once messages are pushed to clients rather than polled for. */
    public function isRealtime(): bool;

    /** Persists and dispatches a message, returning it with its new id. */
    public function publish(ChatMessage $message): ChatMessage;

    /**
     * Backlog for a room, oldest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function history(int $roomId, int $limit, bool $includeDeleted = false): array;

    /**
     * Marks a viewer as present. Real-time transports would translate this into
     * a subscription; the HTTP transport records a heartbeat row.
     */
    public function join(int $roomId, int $userId): void;

    /**
     * @return array<int,array<string,mixed>>
     */
    public function presence(int $roomId, int $windowSeconds): array;

    public function retract(int $messageId, int $moderatorId): void;

    /**
     * Endpoint a future client would connect to, or null when the transport
     * has no separate channel (as with the HTTP transport).
     */
    public function endpoint(): ?string;
}
