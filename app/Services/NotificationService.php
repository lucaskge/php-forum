<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationType;
use App\Repositories\NotificationRepository;
use App\Repositories\UserRepository;
use App\Support\ContentFormatter;
use App\Support\Str;

/**
 * Notifications are written synchronously on the request that causes them and
 * read when the member next loads a page — no polling, no JavaScript.
 */
final class NotificationService
{
    private NotificationRepository $notifications;

    private UserRepository $users;

    public function __construct(?NotificationRepository $notifications = null, ?UserRepository $users = null)
    {
        $this->notifications = $notifications ?? new NotificationRepository();
        $this->users = $users ?? new UserRepository();
    }

    public function push(int $userId, NotificationType $type, string $title, string $body, string $url, ?int $actorId = null): void
    {
        if ($actorId !== null && $actorId === $userId) {
            return;
        }

        $this->notifications->create([
            'user_id' => $userId,
            'actor_id' => $actorId,
            'type' => $type->value,
            'title' => Str::limit($title, 180, ''),
            'body' => Str::limit($body, 480),
            'url' => mb_substr($url, 0, 255),
            'is_read' => 0,
        ]);
    }

    /**
     * Fan-out for a new reply: subscribers, the quoted author and anyone
     * mentioned, each notified at most once.
     *
     * @param array<string,mixed> $topic
     * @param array<int,int> $subscriberIds
     */
    public function dispatchForReply(array $topic, int $postId, int $authorId, string $authorName, string $content, array $subscriberIds, string $postUrl): void
    {
        $notified = [$authorId => true];

        foreach ($subscriberIds as $subscriberId) {
            if (isset($notified[$subscriberId])) {
                continue;
            }

            $notified[$subscriberId] = true;

            $this->push(
                $subscriberId,
                NotificationType::Reply,
                sprintf('%s replied in “%s”', $authorName, (string) $topic['title']),
                ContentFormatter::plain($content),
                $postUrl,
                $authorId,
            );
        }

        foreach ($this->mentionedUsers($content) as $user) {
            $userId = (int) $user['id'];

            if (isset($notified[$userId]) || (int) $user['notify_mentions'] === 0) {
                continue;
            }

            $notified[$userId] = true;

            $this->push(
                $userId,
                NotificationType::Mention,
                sprintf('%s mentioned you in “%s”', $authorName, (string) $topic['title']),
                ContentFormatter::plain($content),
                $postUrl,
                $authorId,
            );
        }
    }

    public function notifyQuoted(int $quotedUserId, int $actorId, string $actorName, string $topicTitle, string $postUrl): void
    {
        $user = $this->users->find($quotedUserId);

        if ($user === null || (int) $user['notify_quotes'] === 0) {
            return;
        }

        $this->push(
            $quotedUserId,
            NotificationType::Quote,
            sprintf('%s quoted you in “%s”', $actorName, $topicTitle),
            'Your post was quoted in a reply.',
            $postUrl,
            $actorId,
        );
    }

    public function notifyMessage(int $recipientId, int $senderId, string $senderName, string $subject, int $messageId): void
    {
        $user = $this->users->find($recipientId);

        if ($user === null || (int) $user['notify_messages'] === 0) {
            return;
        }

        $this->push(
            $recipientId,
            NotificationType::Message,
            sprintf('New message from %s', $senderName),
            $subject,
            '/messages/' . $messageId,
            $senderId,
        );
    }

    public function notifyModeration(int $userId, string $title, string $body, string $url = '/notifications', ?int $actorId = null, bool $isWarning = false): void
    {
        $this->push(
            $userId,
            $isWarning ? NotificationType::Warning : NotificationType::Moderation,
            $title,
            $body,
            $url,
            $actorId,
        );
    }

    /** @return array<int,array<string,mixed>> */
    private function mentionedUsers(string $content): array
    {
        $names = ContentFormatter::extractMentions($content);

        return $names === [] ? [] : $this->users->findManyByUsernames($names);
    }

    public function unreadCount(int $userId): int
    {
        return $this->notifications->unreadCount($userId);
    }
}
