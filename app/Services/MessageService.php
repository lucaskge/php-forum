<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UserStatus;
use App\Repositories\MessageRepository;
use App\Repositories\UserRepository;

final class MessageService
{
    private MessageRepository $messages;

    private UserRepository $users;

    private NotificationService $notifications;

    public function __construct(
        ?MessageRepository $messages = null,
        ?UserRepository $users = null,
        ?NotificationService $notifications = null,
    ) {
        $this->messages = $messages ?? new MessageRepository();
        $this->users = $users ?? new UserRepository();
        $this->notifications = $notifications ?? new NotificationService();
    }

    /**
     * @param array<string,mixed> $sender
     * @return array{ok:bool,message?:string,id?:int}
     */
    public function send(array $sender, string $recipientUsername, string $subject, string $body, ?int $parentId = null): array
    {
        $recipient = $this->users->findByUsername($recipientUsername);

        if ($recipient === null) {
            return ['ok' => false, 'message' => 'No member is registered under that name.'];
        }

        if ((int) $recipient['id'] === (int) $sender['id']) {
            return ['ok' => false, 'message' => 'You cannot send a message to yourself.'];
        }

        if (UserStatus::tryFrom((string) $recipient['status']) === UserStatus::Banned) {
            return ['ok' => false, 'message' => 'That account is banned and cannot receive messages.'];
        }

        $id = $this->messages->create([
            'sender_id' => (int) $sender['id'],
            'recipient_id' => (int) $recipient['id'],
            'parent_id' => $parentId,
            'subject' => $subject,
            'body' => $body,
            'is_read' => 0,
        ]);

        $this->notifications->notifyMessage(
            (int) $recipient['id'],
            (int) $sender['id'],
            (string) $sender['username'],
            $subject,
            $id,
        );

        return ['ok' => true, 'id' => $id];
    }

    /** @param array<string,mixed> $message */
    public function canAccess(array $message, int $userId): bool
    {
        return (int) $message['sender_id'] === $userId || (int) $message['recipient_id'] === $userId;
    }

    /** @param array<string,mixed> $message */
    public function isRecipient(array $message, int $userId): bool
    {
        return (int) $message['recipient_id'] === $userId;
    }

    public function unreadCount(int $userId): int
    {
        return $this->messages->unreadCount($userId);
    }
}
