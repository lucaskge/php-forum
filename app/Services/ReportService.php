<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ContentType;
use App\Repositories\ChatRepository;
use App\Repositories\MessageRepository;
use App\Repositories\PostRepository;
use App\Repositories\ReportRepository;
use App\Repositories\TopicRepository;
use App\Repositories\UserRepository;
use App\Support\Url;

final class ReportService
{
    public const REASONS = [
        'spam' => 'Spam or advertising',
        'abuse' => 'Harassment or abuse',
        'illegal' => 'Illegal content',
        'offtopic' => 'Off topic or wrong forum',
        'duplicate' => 'Duplicate content',
        'doxxing' => 'Personal information',
        'other' => 'Something else',
    ];

    private ReportRepository $reports;

    private PostRepository $posts;

    private TopicRepository $topics;

    private MessageRepository $messages;

    private ChatRepository $chat;

    public function __construct(
        ?ReportRepository $reports = null,
        ?PostRepository $posts = null,
        ?TopicRepository $topics = null,
        ?MessageRepository $messages = null,
        ?ChatRepository $chat = null,
    ) {
        $this->reports = $reports ?? new ReportRepository();
        $this->posts = $posts ?? new PostRepository();
        $this->topics = $topics ?? new TopicRepository();
        $this->messages = $messages ?? new MessageRepository();
        $this->chat = $chat ?? new ChatRepository();
    }

    public function submit(int $reporterId, string $type, int $contentId, ?int $reportedUserId, string $reason, ?string $details, string $ip): int
    {
        return $this->reports->create([
            'reporter_id' => $reporterId,
            'reported_user_id' => $reportedUserId,
            'content_type' => $type,
            'content_id' => $contentId,
            'reason' => $reason,
            'details' => $details,
            'status' => 'pending',
            'ip_address' => $ip,
        ]);
    }

    /**
     * Resolves the reported object into something the review screen can render
     * — reports outlive the content they point at, so a missing target is a
     * normal case rather than an error.
     *
     * @param array<string,mixed> $report
     * @return array{label:string,excerpt:string,url:string|null,exists:bool,author:string|null}
     */
    public function resolveContent(array $report): array
    {
        $type = (string) $report['content_type'];
        $id = (int) $report['content_id'];

        $missing = ['label' => (ContentType::tryFrom($type)?->label() ?? ucfirst($type)) . ' #' . $id, 'excerpt' => 'The reported content no longer exists.', 'url' => null, 'exists' => false, 'author' => null];

        return match ($type) {
            ContentType::Post->value => $this->resolvePost($id) ?? $missing,
            ContentType::Topic->value => $this->resolveTopic($id) ?? $missing,
            ContentType::Message->value => $this->resolveMessage($id) ?? $missing,
            ContentType::ChatMessage->value => $this->resolveChatMessage($id) ?? $missing,
            ContentType::User->value => $this->resolveUser($report) ?? $missing,
            default => $missing,
        };
    }

    /**
     * Where a report points, derived from the row the listing already loaded.
     *
     * Kept separate from resolveContent() so a list of reports does not run a
     * query per row just to render a link.
     *
     * @param array<string,mixed> $report
     */
    public function contentUrl(array $report): ?string
    {
        $id = (int) $report['content_id'];

        return match ((string) $report['content_type']) {
            // The permalink controller works out which page the post is on.
            ContentType::Post->value => Url::route('post.permalink', ['id' => $id]),
            ContentType::Topic->value => ($report['content_topic_slug'] ?? null) === null
                ? null
                : Url::route('topic.show', ['slug' => (string) $report['content_topic_slug']]),
            ContentType::ChatMessage->value => Url::route('chat'),
            ContentType::User->value => ($report['reported_username'] ?? null) === null
                ? null
                : Url::route('moderation.user', ['username' => (string) $report['reported_username']]),
            // A private message is not readable by a moderator, by design.
            default => null,
        };
    }

    /**
     * A reported member. There is no "content" to quote, so the report itself
     * carries the case and the link goes to their moderation record.
     *
     * @param array<string,mixed> $report
     * @return array{label:string,excerpt:string,url:string|null,exists:bool,author:string|null}|null
     */
    private function resolveUser(array $report): ?array
    {
        $user = $report['reported_user_id'] === null
            ? null
            : (new UserRepository())->find((int) $report['reported_user_id']);

        if ($user === null) {
            return null;
        }

        return [
            'label' => sprintf('The member %s', (string) $user['username']),
            'excerpt' => (string) ($report['details'] ?? '') !== ''
                ? (string) $report['details']
                : 'Reported as conduct rather than a single post. The reporter’s notes are below.',
            'url' => Url::route('moderation.user', ['username' => (string) $user['username']]),
            'exists' => true,
            'author' => (string) $user['username'],
        ];
    }

    /** @return array{label:string,excerpt:string,url:string|null,exists:bool,author:string|null}|null */
    private function resolvePost(int $id): ?array
    {
        $post = $this->posts->find($id);

        if ($post === null) {
            return null;
        }

        return [
            'label' => sprintf('Post #%d in “%s”', $id, (string) $post['topic_title']),
            'excerpt' => (string) $post['content'],
            'url' => '/post/' . $id,
            'exists' => true,
            'author' => $post['author_username'] === null ? null : (string) $post['author_username'],
        ];
    }

    /** @return array{label:string,excerpt:string,url:string|null,exists:bool,author:string|null}|null */
    private function resolveTopic(int $id): ?array
    {
        $topic = $this->topics->find($id);

        if ($topic === null) {
            return null;
        }

        return [
            'label' => sprintf('Topic “%s”', (string) $topic['title']),
            'excerpt' => (string) $topic['title'],
            'url' => '/topic/' . (string) $topic['slug'],
            'exists' => true,
            'author' => $topic['author_username'] === null ? null : (string) $topic['author_username'],
        ];
    }

    /** @return array{label:string,excerpt:string,url:string|null,exists:bool,author:string|null}|null */
    private function resolveMessage(int $id): ?array
    {
        $message = $this->messages->find($id);

        if ($message === null) {
            return null;
        }

        return [
            'label' => sprintf('Private message “%s”', (string) $message['subject']),
            'excerpt' => (string) $message['body'],
            'url' => null,
            'exists' => true,
            'author' => $message['sender_username'] === null ? null : (string) $message['sender_username'],
        ];
    }

    /** @return array{label:string,excerpt:string,url:string|null,exists:bool,author:string|null}|null */
    private function resolveChatMessage(int $id): ?array
    {
        $message = $this->chat->findMessage($id);

        if ($message === null) {
            return null;
        }

        return [
            'label' => sprintf('Chat message #%d', $id),
            'excerpt' => (string) $message['content'],
            'url' => '/chat',
            'exists' => true,
            'author' => $message['username'] === null ? null : (string) $message['username'],
        ];
    }
}
