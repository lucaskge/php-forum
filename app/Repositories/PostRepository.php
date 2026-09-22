<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use App\Support\Dates;
use App\Support\Paginator;

final class PostRepository extends Repository
{
    private const SELECT = 'p.id, p.topic_id, p.forum_id, p.user_id, p.content, p.is_hidden, p.is_first_post,
            p.edit_count, p.edited_at, p.edited_by, p.ip_address, p.deleted_at, p.created_at, p.updated_at';

    private const AUTHOR = 'u.username AS author_username, u.avatar_path AS author_avatar, u.title AS author_title,
            u.post_count AS author_post_count, u.reputation AS author_reputation, u.created_at AS author_joined,
            u.last_active_at AS author_last_active, u.signature AS author_signature, u.status AS author_status,
            r.id AS author_role_id, r.name AS author_role, r.colour AS author_colour,
            r.is_staff AS author_is_staff, e.username AS editor_username';

    private const FROM = 'FROM posts p
             LEFT JOIN users u ON u.id = p.user_id
             LEFT JOIN roles r ON r.id = u.primary_role_id
             LEFT JOIN users e ON e.id = p.edited_by';

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT ' . self::SELECT . ', ' . self::AUTHOR . ', t.title AS topic_title, t.slug AS topic_slug,
                    t.is_locked AS topic_locked, t.deleted_at AS topic_deleted_at, f.name AS forum_name, f.slug AS forum_slug
             ' . self::FROM . '
             INNER JOIN topics t ON t.id = p.topic_id
             INNER JOIN forums f ON f.id = p.forum_id
             WHERE p.id = :id',
            ['id' => $id],
        );
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $data['created_at'] = Dates::nowString();
        $data['updated_at'] = Dates::nowString();

        return $this->db->insert('posts', $data);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): int
    {
        $data['updated_at'] = Dates::nowString();

        return $this->db->update('posts', $data, 'id = :id', ['id' => $id]);
    }

    public function softDelete(int $id, int $moderatorId): int
    {
        return $this->db->update('posts', [
            'deleted_at' => Dates::nowString(),
            'deleted_by' => $moderatorId,
        ], 'id = :id', ['id' => $id]);
    }

    public function restore(int $id): int
    {
        return $this->db->update('posts', ['deleted_at' => null, 'deleted_by' => null], 'id = :id', ['id' => $id]);
    }

    public function delete(int $id): int
    {
        return $this->db->delete('posts', 'id = :id', ['id' => $id]);
    }

    /**
     * @return Paginator<array<string,mixed>>
     */
    public function paginateForTopic(int $topicId, int $page, int $perPage, string $baseUrl, bool $includeHidden = false): Paginator
    {
        $conditions = ['p.topic_id = :topic', 'p.deleted_at IS NULL'];
        $bindings = ['topic' => $topicId];

        if (!$includeHidden) {
            $conditions[] = 'p.is_hidden = 0';
        }

        $where = implode(' AND ', $conditions);

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM posts p WHERE ' . $where, $bindings);

        $rows = $this->db->select(
            'SELECT ' . self::SELECT . ', ' . self::AUTHOR . ' ' . self::FROM . '
             WHERE ' . $where . ' ORDER BY p.id ASC LIMIT :limit OFFSET :offset',
            array_merge($bindings, ['limit' => $perPage, 'offset' => Paginator::offset($page, $perPage)]),
        );

        return new Paginator($rows, $total, $perPage, $page, $baseUrl);
    }

    /**
     * Ordinal of a post inside its topic, so a permalink can resolve to a page.
     */
    public function positionInTopic(int $postId, int $topicId, bool $includeHidden = false): int
    {
        $sql = 'SELECT COUNT(*) FROM posts WHERE topic_id = :topic AND deleted_at IS NULL AND id <= :post';

        if (!$includeHidden) {
            $sql .= ' AND is_hidden = 0';
        }

        return (int) $this->db->scalar($sql, ['topic' => $topicId, 'post' => $postId]);
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

        $where = 'p.user_id = :user AND p.forum_id IN (' . $placeholders . ') AND p.deleted_at IS NULL
                  AND p.is_hidden = 0 AND t.deleted_at IS NULL AND t.is_hidden = 0';

        $total = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM posts p INNER JOIN topics t ON t.id = p.topic_id WHERE ' . $where,
            $bindings,
        );

        $rows = $this->db->select(
            'SELECT ' . self::SELECT . ', t.title AS topic_title, t.slug AS topic_slug,
                    f.name AS forum_name, f.slug AS forum_slug
             FROM posts p
             INNER JOIN topics t ON t.id = p.topic_id
             INNER JOIN forums f ON f.id = p.forum_id
             WHERE ' . $where . ' ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset',
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
            'SELECT p.id, p.content, p.created_at, p.user_id, u.username AS author_username, u.avatar_path AS author_avatar,
                    u.primary_role_id AS author_role_id,
                    t.title AS topic_title, t.slug AS topic_slug, f.name AS forum_name, f.slug AS forum_slug
             FROM posts p
             INNER JOIN topics t ON t.id = p.topic_id
             INNER JOIN forums f ON f.id = p.forum_id
             LEFT JOIN users u ON u.id = p.user_id
             WHERE p.forum_id IN (' . $placeholders . ') AND p.deleted_at IS NULL AND p.is_hidden = 0
               AND t.deleted_at IS NULL AND t.is_hidden = 0
             ORDER BY p.id DESC LIMIT :limit',
            array_merge($bindings, ['limit' => $limit]),
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function editHistory(int $postId): array
    {
        return $this->db->select(
            'SELECT pe.*, u.username AS editor_username FROM post_edits pe
             LEFT JOIN users u ON u.id = pe.editor_id
             WHERE pe.post_id = :post ORDER BY pe.created_at DESC',
            ['post' => $postId],
        );
    }

    /** @param array<string,mixed> $data */
    public function recordEdit(array $data): int
    {
        $data['created_at'] = Dates::nowString();

        return $this->db->insert('post_edits', $data);
    }

    public function countAll(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL');
    }

    public function countSince(string $since): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL AND created_at >= :since',
            ['since' => $since],
        );
    }

    public function countForUser(int $userId): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM posts WHERE user_id = :user AND deleted_at IS NULL',
            ['user' => $userId],
        );
    }

    /**
     * Moves every post of a topic to another forum — used when a topic moves.
     */
    public function moveTopicPosts(int $topicId, int $forumId): int
    {
        return $this->db->execute(
            'UPDATE posts SET forum_id = :forum WHERE topic_id = :topic',
            ['forum' => $forumId, 'topic' => $topicId],
        );
    }

    /**
     * @param array<int,int> $postIds
     */
    public function movePostsToTopic(array $postIds, int $topicId, int $forumId): int
    {
        if ($postIds === []) {
            return 0;
        }

        [$placeholders, $bindings] = Database::inClause($postIds, 'post');
        $bindings['topic'] = $topicId;
        $bindings['forum'] = $forumId;

        return $this->db->execute(
            'UPDATE posts SET topic_id = :topic, forum_id = :forum WHERE id IN (' . $placeholders . ')',
            $bindings,
        );
    }

    /** @return array<int,array<string,mixed>> Every post in a topic (used by split/merge screens). */
    public function allForTopic(int $topicId): array
    {
        return $this->db->select(
            'SELECT p.id, p.content, p.created_at, p.is_first_post, u.username AS author_username,
                    u.primary_role_id AS author_role_id
             FROM posts p LEFT JOIN users u ON u.id = p.user_id
             WHERE p.topic_id = :topic AND p.deleted_at IS NULL ORDER BY p.id ASC',
            ['topic' => $topicId],
        );
    }

    /**
     * @param array{search?:string,forum?:int,author?:string,status?:string} $filters
     * @return Paginator<array<string,mixed>>
     */
    public function paginateForAdmin(array $filters, int $page, int $perPage, string $baseUrl): Paginator
    {
        $conditions = ['1 = 1'];
        $bindings = [];

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = 'p.content LIKE :search';
            $bindings['search'] = '%' . $filters['search'] . '%';
        }

        if (($filters['forum'] ?? 0) > 0) {
            $conditions[] = 'p.forum_id = :forum';
            $bindings['forum'] = (int) $filters['forum'];
        }

        if (($filters['author'] ?? '') !== '') {
            $conditions[] = 'u.username_canonical = :author';
            $bindings['author'] = mb_strtolower((string) $filters['author'], 'UTF-8');
        }

        $conditions[] = match ($filters['status'] ?? '') {
            'deleted' => 'p.deleted_at IS NOT NULL',
            'hidden' => 'p.is_hidden = 1',
            'edited' => 'p.edit_count > 0',
            default => '1 = 1',
        };

        $where = implode(' AND ', $conditions);

        $total = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM posts p LEFT JOIN users u ON u.id = p.user_id WHERE ' . $where,
            $bindings,
        );

        $rows = $this->db->select(
            'SELECT ' . self::SELECT . ', u.username AS author_username, u.primary_role_id AS author_role_id,
                    t.title AS topic_title, t.slug AS topic_slug, f.name AS forum_name, f.slug AS forum_slug
             FROM posts p
             LEFT JOIN users u ON u.id = p.user_id
             INNER JOIN topics t ON t.id = p.topic_id
             INNER JOIN forums f ON f.id = p.forum_id
             WHERE ' . $where . ' ORDER BY p.id DESC LIMIT :limit OFFSET :offset',
            array_merge($bindings, ['limit' => $perPage, 'offset' => Paginator::offset($page, $perPage)]),
        );

        $query = array_filter([
            'search' => $filters['search'] ?? '',
            'forum' => $filters['forum'] ?? '',
            'author' => $filters['author'] ?? '',
            'status' => $filters['status'] ?? '',
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        return new Paginator($rows, $total, $perPage, $page, $baseUrl, $query);
    }
}
