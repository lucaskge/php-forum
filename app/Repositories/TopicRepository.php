<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use App\Support\Dates;
use App\Support\Paginator;

final class TopicRepository extends Repository
{
    private const SELECT = 't.id, t.forum_id, t.user_id, t.title, t.slug, t.is_pinned, t.is_locked, t.is_hidden,
            t.is_archived, t.view_count, t.post_count, t.first_post_id, t.last_post_id, t.last_post_user_id,
            t.last_post_at, t.deleted_at, t.created_at, t.updated_at';

    private const JOINED = 'f.name AS forum_name, f.slug AS forum_slug, f.is_locked AS forum_locked,
            au.username AS author_username, au.avatar_path AS author_avatar,
            ar.id AS author_role_id, ar.colour AS author_colour,
            lu.username AS last_post_username, lu.id AS last_post_user,
            lu.primary_role_id AS last_post_role_id';

    private const FROM = 'FROM topics t
             INNER JOIN forums f ON f.id = t.forum_id
             LEFT JOIN users au ON au.id = t.user_id
             LEFT JOIN roles ar ON ar.id = au.primary_role_id
             LEFT JOIN users lu ON lu.id = t.last_post_user_id';

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT ' . self::SELECT . ', ' . self::JOINED . ' ' . self::FROM . ' WHERE t.id = :id',
            ['id' => $id],
        );
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            'SELECT ' . self::SELECT . ', ' . self::JOINED . ' ' . self::FROM . ' WHERE t.slug = :slug',
            ['slug' => $slug],
        );
    }

    public function slugTaken(string $slug): bool
    {
        return $this->db->selectOne('SELECT id FROM topics WHERE slug = :slug', ['slug' => $slug]) !== null;
    }

    public function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while ($this->slugTaken($slug)) {
            $slug = $base . '-' . $suffix;
            $suffix++;

            if ($suffix > 500) {
                $slug = $base . '-' . bin2hex(random_bytes(3));

                break;
            }
        }

        return $slug;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $data['created_at'] = Dates::nowString();
        $data['updated_at'] = Dates::nowString();

        return $this->db->insert('topics', $data);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): int
    {
        $data['updated_at'] = Dates::nowString();

        return $this->db->update('topics', $data, 'id = :id', ['id' => $id]);
    }

    public function delete(int $id): int
    {
        return $this->db->delete('topics', 'id = :id', ['id' => $id]);
    }

    public function softDelete(int $id, int $moderatorId): int
    {
        return $this->db->update('topics', [
            'deleted_at' => Dates::nowString(),
            'deleted_by' => $moderatorId,
        ], 'id = :id', ['id' => $id]);
    }

    public function restore(int $id): int
    {
        return $this->db->update('topics', ['deleted_at' => null, 'deleted_by' => null], 'id = :id', ['id' => $id]);
    }

    public function incrementViews(int $id): void
    {
        $this->db->execute('UPDATE topics SET view_count = view_count + 1 WHERE id = :id', ['id' => $id]);
    }

    /**
     * @param array<int,int> $forumIds
     * @return Paginator<array<string,mixed>>
     */
    public function paginateForForums(array $forumIds, int $page, int $perPage, string $baseUrl, bool $includeHidden = false, bool $includeDeleted = false): Paginator
    {
        if ($forumIds === []) {
            return new Paginator([], 0, $perPage, $page, $baseUrl);
        }

        [$placeholders, $bindings] = Database::inClause($forumIds, 'forum');

        $conditions = ['t.forum_id IN (' . $placeholders . ')'];

        if (!$includeDeleted) {
            $conditions[] = 't.deleted_at IS NULL';
        }

        if (!$includeHidden) {
            $conditions[] = 't.is_hidden = 0';
        }

        $where = implode(' AND ', $conditions);

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM topics t WHERE ' . $where, $bindings);

        $rows = $this->db->select(
            'SELECT ' . self::SELECT . ', ' . self::JOINED . ' ' . self::FROM . '
             WHERE ' . $where . '
             ORDER BY t.is_pinned DESC, COALESCE(t.last_post_at, t.created_at) DESC
             LIMIT :limit OFFSET :offset',
            array_merge($bindings, ['limit' => $perPage, 'offset' => Paginator::offset($page, $perPage)]),
        );

        return new Paginator($rows, $total, $perPage, $page, $baseUrl);
    }

    /**
     * @param array<int,int> $visibleForumIds
     * @return array<int,array<string,mixed>>
     */
    public function latest(array $visibleForumIds, int $limit = 10): array
    {
        if ($visibleForumIds === []) {
            return [];
        }

        [$placeholders, $bindings] = Database::inClause($visibleForumIds, 'forum');

        return $this->db->select(
            'SELECT ' . self::SELECT . ', ' . self::JOINED . ' ' . self::FROM . '
             WHERE t.forum_id IN (' . $placeholders . ') AND t.deleted_at IS NULL AND t.is_hidden = 0
             ORDER BY COALESCE(t.last_post_at, t.created_at) DESC LIMIT :limit',
            array_merge($bindings, ['limit' => $limit]),
        );
    }

    /**
     * @param array<int,int> $visibleForumIds
     * @return Paginator<array<string,mixed>>
     */
    public function paginateForUser(int $userId, array $visibleForumIds, int $page, int $perPage, string $baseUrl): Paginator
    {
        if ($visibleForumIds === []) {
            return new Paginator([], 0, $perPage, $page, $baseUrl);
        }

        [$placeholders, $bindings] = Database::inClause($visibleForumIds, 'forum');
        $bindings['user'] = $userId;

        $where = 't.user_id = :user AND t.forum_id IN (' . $placeholders . ') AND t.deleted_at IS NULL AND t.is_hidden = 0';

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM topics t WHERE ' . $where, $bindings);

        $rows = $this->db->select(
            'SELECT ' . self::SELECT . ', ' . self::JOINED . ' ' . self::FROM . ' WHERE ' . $where . '
             ORDER BY t.created_at DESC LIMIT :limit OFFSET :offset',
            array_merge($bindings, ['limit' => $perPage, 'offset' => Paginator::offset($page, $perPage)]),
        );

        return new Paginator($rows, $total, $perPage, $page, $baseUrl);
    }

    /**
     * @param array<int,int> $visibleForumIds
     * @return Paginator<array<string,mixed>>
     */
    public function paginateSubscriptions(int $userId, array $visibleForumIds, int $page, int $perPage, string $baseUrl): Paginator
    {
        return $this->paginateByJoin('topic_subscriptions', $userId, $visibleForumIds, $page, $perPage, $baseUrl);
    }

    /**
     * @param array<int,int> $visibleForumIds
     * @return Paginator<array<string,mixed>>
     */
    public function paginateBookmarks(int $userId, array $visibleForumIds, int $page, int $perPage, string $baseUrl): Paginator
    {
        return $this->paginateByJoin('bookmarks', $userId, $visibleForumIds, $page, $perPage, $baseUrl);
    }

    /**
     * @param array<int,int> $visibleForumIds
     * @return Paginator<array<string,mixed>>
     */
    private function paginateByJoin(string $table, int $userId, array $visibleForumIds, int $page, int $perPage, string $baseUrl): Paginator
    {
        if ($visibleForumIds === []) {
            return new Paginator([], 0, $perPage, $page, $baseUrl);
        }

        // $table is chosen from a fixed internal set, never from user input.
        $table = $table === 'bookmarks' ? 'bookmarks' : 'topic_subscriptions';

        [$placeholders, $bindings] = Database::inClause($visibleForumIds, 'forum');
        $bindings['user'] = $userId;

        $join = 'INNER JOIN ' . $table . ' j ON j.topic_id = t.id AND j.user_id = :user';
        $where = 't.forum_id IN (' . $placeholders . ') AND t.deleted_at IS NULL';

        $total = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM topics t ' . $join . ' WHERE ' . $where,
            $bindings,
        );

        $rows = $this->db->select(
            'SELECT ' . self::SELECT . ', ' . self::JOINED . ', j.created_at AS saved_at ' . self::FROM . ' ' . $join . '
             WHERE ' . $where . ' ORDER BY j.created_at DESC LIMIT :limit OFFSET :offset',
            array_merge($bindings, ['limit' => $perPage, 'offset' => Paginator::offset($page, $perPage)]),
        );

        return new Paginator($rows, $total, $perPage, $page, $baseUrl);
    }

    /**
     * Recomputes post_count and the last-post pointer from the posts table.
     */
    public function refreshCounters(int $topicId): void
    {
        $stats = $this->db->selectOne(
            'SELECT COUNT(*) AS total, MIN(id) AS first_id, MAX(id) AS last_id
             FROM posts WHERE topic_id = :topic AND deleted_at IS NULL AND is_hidden = 0',
            ['topic' => $topicId],
        ) ?? ['total' => 0, 'first_id' => null, 'last_id' => null];

        $last = null;

        if ($stats['last_id'] !== null) {
            $last = $this->db->selectOne(
                'SELECT id, user_id, created_at FROM posts WHERE id = :id',
                ['id' => (int) $stats['last_id']],
            );
        }

        $this->db->update('topics', [
            'post_count' => (int) $stats['total'],
            'first_post_id' => $stats['first_id'] !== null ? (int) $stats['first_id'] : null,
            'last_post_id' => $last['id'] ?? null,
            'last_post_user_id' => $last['user_id'] ?? null,
            'last_post_at' => $last['created_at'] ?? null,
            'updated_at' => Dates::nowString(),
        ], 'id = :id', ['id' => $topicId]);
    }

    public function countAll(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL');
    }

    public function countSince(string $since): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL AND created_at >= :since',
            ['since' => $since],
        );
    }

    public function countForUser(int $userId): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM topics WHERE user_id = :user AND deleted_at IS NULL',
            ['user' => $userId],
        );
    }

    /**
     * Admin listing with free-text and forum filters.
     *
     * @param array{search?:string,forum?:int,status?:string} $filters
     * @return Paginator<array<string,mixed>>
     */
    public function paginateForAdmin(array $filters, int $page, int $perPage, string $baseUrl): Paginator
    {
        $conditions = ['1 = 1'];
        $bindings = [];

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = 't.title LIKE :search';
            $bindings['search'] = '%' . $filters['search'] . '%';
        }

        if (($filters['forum'] ?? 0) > 0) {
            $conditions[] = 't.forum_id = :forum';
            $bindings['forum'] = (int) $filters['forum'];
        }

        $conditions[] = match ($filters['status'] ?? '') {
            'deleted' => 't.deleted_at IS NOT NULL',
            'hidden' => 't.is_hidden = 1',
            'locked' => 't.is_locked = 1',
            'pinned' => 't.is_pinned = 1',
            'archived' => 't.is_archived = 1',
            default => '1 = 1',
        };

        $where = implode(' AND ', $conditions);

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM topics t WHERE ' . $where, $bindings);

        $rows = $this->db->select(
            'SELECT ' . self::SELECT . ', ' . self::JOINED . ' ' . self::FROM . ' WHERE ' . $where . '
             ORDER BY t.created_at DESC LIMIT :limit OFFSET :offset',
            array_merge($bindings, ['limit' => $perPage, 'offset' => Paginator::offset($page, $perPage)]),
        );

        $query = array_filter([
            'search' => $filters['search'] ?? '',
            'forum' => $filters['forum'] ?? '',
            'status' => $filters['status'] ?? '',
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        return new Paginator($rows, $total, $perPage, $page, $baseUrl, $query);
    }

    public function isSubscribed(int $userId, int $topicId): bool
    {
        return $this->db->selectOne(
            'SELECT id FROM topic_subscriptions WHERE user_id = :user AND topic_id = :topic',
            ['user' => $userId, 'topic' => $topicId],
        ) !== null;
    }

    public function isBookmarked(int $userId, int $topicId): bool
    {
        return $this->db->selectOne(
            'SELECT id FROM bookmarks WHERE user_id = :user AND topic_id = :topic',
            ['user' => $userId, 'topic' => $topicId],
        ) !== null;
    }

    public function subscribe(int $userId, int $topicId): void
    {
        $this->db->execute(
            'INSERT IGNORE INTO topic_subscriptions (user_id, topic_id, created_at) VALUES (:user, :topic, :at)',
            ['user' => $userId, 'topic' => $topicId, 'at' => Dates::nowString()],
        );
    }

    public function unsubscribe(int $userId, int $topicId): void
    {
        $this->db->delete('topic_subscriptions', 'user_id = :user AND topic_id = :topic', ['user' => $userId, 'topic' => $topicId]);
    }

    public function bookmark(int $userId, int $topicId): void
    {
        $this->db->execute(
            'INSERT IGNORE INTO bookmarks (user_id, topic_id, created_at) VALUES (:user, :topic, :at)',
            ['user' => $userId, 'topic' => $topicId, 'at' => Dates::nowString()],
        );
    }

    public function removeBookmark(int $userId, int $topicId): void
    {
        $this->db->delete('bookmarks', 'user_id = :user AND topic_id = :topic', ['user' => $userId, 'topic' => $topicId]);
    }

    /** @return array<int,int> User ids subscribed to the topic, excluding one. */
    public function subscriberIds(int $topicId, ?int $exceptUserId = null): array
    {
        $rows = $this->db->select(
            'SELECT s.user_id FROM topic_subscriptions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.topic_id = :topic AND u.status = :active AND u.notify_replies = 1
               AND (:except IS NULL OR s.user_id <> :except2)',
            [
                'topic' => $topicId,
                'active' => 'active',
                'except' => $exceptUserId,
                'except2' => $exceptUserId ?? 0,
            ],
        );

        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }
}
