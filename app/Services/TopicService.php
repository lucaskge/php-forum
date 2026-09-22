<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TopicFlag;
use App\Repositories\ForumRepository;
use App\Repositories\PostRepository;
use App\Repositories\TopicRepository;
use App\Repositories\UserRepository;
use App\Support\Database;
use App\Support\Dates;
use App\Support\Str;
use App\Support\Url;

/**
 * Write-side operations on topics and posts. Everything that mutates the board
 * goes through here so counter maintenance and notifications stay in one place.
 */
final class TopicService
{
    private TopicRepository $topics;

    private PostRepository $posts;

    private ForumRepository $forums;

    private UserRepository $users;

    private NotificationService $notifications;

    private SettingsService $settings;

    public function __construct(
        ?TopicRepository $topics = null,
        ?PostRepository $posts = null,
        ?ForumRepository $forums = null,
        ?UserRepository $users = null,
        ?NotificationService $notifications = null,
        ?SettingsService $settings = null,
    ) {
        $this->topics = $topics ?? new TopicRepository();
        $this->posts = $posts ?? new PostRepository();
        $this->forums = $forums ?? new ForumRepository();
        $this->users = $users ?? new UserRepository();
        $this->notifications = $notifications ?? new NotificationService();
        $this->settings = $settings ?? SettingsService::instance();
    }

    /**
     * @return array{topic_id:int,post_id:int,slug:string}
     */
    public function createTopic(int $forumId, int $userId, string $title, string $content, string $ip, bool $subscribe = true): array
    {
        return Database::instance()->transaction(function () use ($forumId, $userId, $title, $content, $ip, $subscribe): array {
            $slug = $this->topics->uniqueSlug(Str::slug(Str::limit($title, 80, '')));

            $topicId = $this->topics->create([
                'forum_id' => $forumId,
                'user_id' => $userId,
                'title' => $title,
                'slug' => $slug,
                'post_count' => 0,
            ]);

            $postId = $this->posts->create([
                'topic_id' => $topicId,
                'forum_id' => $forumId,
                'user_id' => $userId,
                'content' => $content,
                'is_first_post' => 1,
                'ip_address' => $ip,
            ]);

            $this->topics->refreshCounters($topicId);
            $this->forums->refreshCounters($forumId);
            $this->users->incrementPostCount($userId);
            $this->users->incrementTopicCount($userId);

            if ($subscribe) {
                $this->topics->subscribe($userId, $topicId);
            }

            return ['topic_id' => $topicId, 'post_id' => $postId, 'slug' => $slug];
        });
    }

    /**
     * @param array<string,mixed> $topic
     * @return array{post_id:int,url:string}
     */
    public function reply(array $topic, int $userId, string $content, string $ip, ?int $quotedUserId = null, bool $subscribe = true): array
    {
        $topicId = (int) $topic['id'];
        $forumId = (int) $topic['forum_id'];

        $postId = Database::instance()->transaction(function () use ($topicId, $forumId, $userId, $content, $ip, $subscribe): int {
            $postId = $this->posts->create([
                'topic_id' => $topicId,
                'forum_id' => $forumId,
                'user_id' => $userId,
                'content' => $content,
                'is_first_post' => 0,
                'ip_address' => $ip,
            ]);

            $this->topics->refreshCounters($topicId);
            $this->forums->refreshCounters($forumId);
            $this->users->incrementPostCount($userId);

            if ($subscribe) {
                $this->topics->subscribe($userId, $topicId);
            }

            return $postId;
        });

        $url = $this->postUrl($topicId, (string) $topic['slug'], $postId);
        $author = $this->users->find($userId);
        $authorName = (string) ($author['username'] ?? 'Someone');

        $this->notifications->dispatchForReply(
            $topic,
            $postId,
            $userId,
            $authorName,
            $content,
            $this->topics->subscriberIds($topicId, $userId),
            $url,
        );

        if ($quotedUserId !== null && $quotedUserId !== $userId) {
            $this->notifications->notifyQuoted($quotedUserId, $userId, $authorName, (string) $topic['title'], $url);
        }

        return ['post_id' => $postId, 'url' => $url];
    }

    /**
     * @param array<string,mixed> $post
     */
    public function editPost(array $post, int $editorId, string $content, ?string $reason, string $ip): void
    {
        $postId = (int) $post['id'];

        Database::instance()->transaction(function () use ($post, $postId, $editorId, $content, $reason, $ip): void {
            $this->posts->recordEdit([
                'post_id' => $postId,
                'editor_id' => $editorId,
                'content_before' => (string) $post['content'],
                'content_after' => $content,
                'reason' => $reason,
                'ip_address' => $ip,
            ]);

            $this->posts->update($postId, [
                'content' => $content,
                'edit_count' => (int) $post['edit_count'] + 1,
                'edited_at' => Dates::nowString(),
                'edited_by' => $editorId,
            ]);
        });
    }

    /**
     * @param array<string,mixed> $post
     */
    public function deletePost(array $post, int $actorId): void
    {
        $postId = (int) $post['id'];
        $topicId = (int) $post['topic_id'];
        $forumId = (int) $post['forum_id'];

        Database::instance()->transaction(function () use ($post, $postId, $topicId, $forumId, $actorId): void {
            $this->posts->softDelete($postId, $actorId);

            if ($post['user_id'] !== null) {
                $this->users->incrementPostCount((int) $post['user_id'], -1);
            }

            $this->topics->refreshCounters($topicId);
            $this->forums->refreshCounters($forumId);
        });
    }

    /** @param array<string,mixed> $post */
    public function restorePost(array $post): void
    {
        $postId = (int) $post['id'];

        Database::instance()->transaction(function () use ($post, $postId): void {
            $this->posts->restore($postId);

            if ($post['user_id'] !== null) {
                $this->users->incrementPostCount((int) $post['user_id'], 1);
            }

            $this->topics->refreshCounters((int) $post['topic_id']);
            $this->forums->refreshCounters((int) $post['forum_id']);
        });
    }

    /** @param array<string,mixed> $post */
    public function setPostHidden(array $post, bool $hidden): void
    {
        $this->posts->update((int) $post['id'], ['is_hidden' => $hidden ? 1 : 0]);
        $this->topics->refreshCounters((int) $post['topic_id']);
        $this->forums->refreshCounters((int) $post['forum_id']);
    }

    /** @param array<string,mixed> $topic */
    public function deleteTopic(array $topic, int $actorId): void
    {
        Database::instance()->transaction(function () use ($topic, $actorId): void {
            $this->topics->softDelete((int) $topic['id'], $actorId);

            if ($topic['user_id'] !== null) {
                $this->users->incrementTopicCount((int) $topic['user_id'], -1);
            }

            $this->forums->refreshCounters((int) $topic['forum_id']);
        });
    }

    /** @param array<string,mixed> $topic */
    public function restoreTopic(array $topic): void
    {
        Database::instance()->transaction(function () use ($topic): void {
            $this->topics->restore((int) $topic['id']);

            if ($topic['user_id'] !== null) {
                $this->users->incrementTopicCount((int) $topic['user_id'], 1);
            }

            $this->forums->refreshCounters((int) $topic['forum_id']);
        });
    }

    /** @param array<string,mixed> $topic */
    public function move(array $topic, int $targetForumId): void
    {
        $sourceForumId = (int) $topic['forum_id'];

        if ($sourceForumId === $targetForumId) {
            return;
        }

        Database::instance()->transaction(function () use ($topic, $sourceForumId, $targetForumId): void {
            $this->topics->update((int) $topic['id'], ['forum_id' => $targetForumId]);
            $this->posts->moveTopicPosts((int) $topic['id'], $targetForumId);
            $this->forums->refreshCounters($sourceForumId);
            $this->forums->refreshCounters($targetForumId);
        });
    }

    /**
     * Folds $source into $target: every post moves across and the now-empty
     * source topic is removed.
     *
     * @param array<string,mixed> $source
     * @param array<string,mixed> $target
     */
    public function merge(array $source, array $target, int $actorId): int
    {
        return Database::instance()->transaction(function () use ($source, $target, $actorId): int {
            $posts = $this->posts->allForTopic((int) $source['id']);
            $ids = array_map(static fn (array $post): int => (int) $post['id'], $posts);

            $this->posts->movePostsToTopic($ids, (int) $target['id'], (int) $target['forum_id']);

            // The merged opening post becomes an ordinary reply.
            foreach ($posts as $post) {
                if ((int) $post['is_first_post'] === 1) {
                    $this->posts->update((int) $post['id'], ['is_first_post' => 0]);
                }
            }

            $this->topics->softDelete((int) $source['id'], $actorId);
            $this->topics->update((int) $source['id'], ['post_count' => 0, 'first_post_id' => null, 'last_post_id' => null]);

            $this->topics->refreshCounters((int) $target['id']);
            $this->forums->refreshCounters((int) $source['forum_id']);
            $this->forums->refreshCounters((int) $target['forum_id']);

            return count($ids);
        });
    }

    /**
     * Splits selected posts out of a topic into a brand new one.
     *
     * @param array<string,mixed> $topic
     * @param array<int,int> $postIds
     * @return array{topic_id:int,slug:string}
     */
    public function split(array $topic, array $postIds, string $title, int $targetForumId, int $actorId): array
    {
        return Database::instance()->transaction(function () use ($topic, $postIds, $title, $targetForumId, $actorId): array {
            $slug = $this->topics->uniqueSlug(Str::slug(Str::limit($title, 80, '')));

            $firstPost = $this->posts->find($postIds[0]);

            $newTopicId = $this->topics->create([
                'forum_id' => $targetForumId,
                'user_id' => $firstPost['user_id'] ?? $actorId,
                'title' => $title,
                'slug' => $slug,
                'post_count' => 0,
            ]);

            $this->posts->movePostsToTopic($postIds, $newTopicId, $targetForumId);
            $this->posts->update($postIds[0], ['is_first_post' => 1]);

            $this->topics->refreshCounters($newTopicId);
            $this->topics->refreshCounters((int) $topic['id']);
            $this->forums->refreshCounters((int) $topic['forum_id']);
            $this->forums->refreshCounters($targetForumId);

            return ['topic_id' => $newTopicId, 'slug' => $slug];
        });
    }

    /** @param array<string,mixed> $topic */
    public function setFlag(array $topic, string $flag, bool $value): void
    {
        if (!in_array($flag, TopicFlag::columns(), true)) {
            return;
        }

        $this->topics->update((int) $topic['id'], [$flag => $value ? 1 : 0]);

        if ($flag === 'is_hidden') {
            $this->forums->refreshCounters((int) $topic['forum_id']);
        }
    }

    /** @param array<string,mixed> $topic */
    public function rename(array $topic, string $title): void
    {
        $this->topics->update((int) $topic['id'], ['title' => $title]);
    }

    /**
     * Page-aware permalink for a post.
     */
    public function postUrl(int $topicId, string $topicSlug, int $postId, bool $includeHidden = false): string
    {
        $perPage = $this->settings->postsPerPage();
        $position = $this->posts->positionInTopic($postId, $topicId, $includeHidden);
        $page = max(1, (int) ceil($position / $perPage));

        return Url::route(
            'topic.show',
            ['slug' => $topicSlug],
            $page > 1 ? ['page' => $page] : [],
            'post-' . $postId,
        );
    }

    /**
     * Post number as shown in the topic ("#12"), counted across pages.
     */
    public function postNumber(int $postId, int $topicId, bool $includeHidden = false): int
    {
        return $this->posts->positionInTopic($postId, $topicId, $includeHidden);
    }

    public function recountEverything(): void
    {
        foreach ($this->topics->paginateForAdmin([], 1, 100000, '')->items() as $topic) {
            $this->topics->refreshCounters((int) $topic['id']);
        }

        foreach ($this->forums->allForums(false) as $forum) {
            $this->forums->refreshCounters((int) $forum['id']);
        }
    }
}
