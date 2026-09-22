<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use App\Support\Dates;

final class ForumRepository extends Repository
{
    private const SELECT = 'f.id, f.category_id, f.parent_id, f.name, f.slug, f.description, f.icon, f.position,
            f.is_visible, f.is_locked, f.topic_count, f.post_count, f.last_topic_id, f.last_post_id,
            f.last_post_user_id, f.last_post_at, f.created_at';

    /** @return array<int,array<string,mixed>> */
    public function allCategories(bool $visibleOnly = true): array
    {
        return $this->db->select(
            'SELECT * FROM categories' . ($visibleOnly ? ' WHERE is_visible = 1' : '') . ' ORDER BY position ASC, name ASC',
        );
    }

    /** @return array<string,mixed>|null */
    public function findCategory(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM categories WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string,mixed>|null */
    public function findCategoryBySlug(string $slug): ?array
    {
        return $this->db->selectOne('SELECT * FROM categories WHERE slug = :slug', ['slug' => $slug]);
    }

    /** @param array<string,mixed> $data */
    public function createCategory(array $data): int
    {
        $data['created_at'] = Dates::nowString();
        $data['updated_at'] = Dates::nowString();

        return $this->db->insert('categories', $data);
    }

    /** @param array<string,mixed> $data */
    public function updateCategory(int $id, array $data): int
    {
        $data['updated_at'] = Dates::nowString();

        return $this->db->update('categories', $data, 'id = :id', ['id' => $id]);
    }

    public function deleteCategory(int $id): int
    {
        return $this->db->delete('categories', 'id = :id', ['id' => $id]);
    }

    public function categorySlugTaken(string $slug, ?int $exceptId = null): bool
    {
        return $this->db->selectOne(
            'SELECT id FROM categories WHERE slug = :slug AND (:except IS NULL OR id <> :except2)',
            ['slug' => $slug, 'except' => $exceptId, 'except2' => $exceptId ?? 0],
        ) !== null;
    }

    /**
     * Every forum with its last-post summary, ordered for display.
     *
     * @return array<int,array<string,mixed>>
     */
    public function allForums(bool $visibleOnly = true): array
    {
        return $this->db->select(
            'SELECT ' . self::SELECT . ',
                    c.name AS category_name, c.slug AS category_slug, c.position AS category_position,
                    p.name AS parent_name, p.slug AS parent_slug,
                    t.title AS last_topic_title, t.slug AS last_topic_slug, t.post_count AS last_topic_post_count,
                    lu.username AS last_post_username, lu.id AS last_post_user_id
             FROM forums f
             INNER JOIN categories c ON c.id = f.category_id
             LEFT JOIN forums p ON p.id = f.parent_id
             LEFT JOIN topics t ON t.id = f.last_topic_id
             LEFT JOIN users lu ON lu.id = f.last_post_user_id
             ' . ($visibleOnly ? 'WHERE f.is_visible = 1 AND c.is_visible = 1' : '') . '
             ORDER BY c.position ASC, f.position ASC, f.name ASC',
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT ' . self::SELECT . ', c.name AS category_name, c.slug AS category_slug,
                    p.name AS parent_name, p.slug AS parent_slug
             FROM forums f
             INNER JOIN categories c ON c.id = f.category_id
             LEFT JOIN forums p ON p.id = f.parent_id
             WHERE f.id = :id',
            ['id' => $id],
        );
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne(
            'SELECT ' . self::SELECT . ', c.name AS category_name, c.slug AS category_slug,
                    p.name AS parent_name, p.slug AS parent_slug
             FROM forums f
             INNER JOIN categories c ON c.id = f.category_id
             LEFT JOIN forums p ON p.id = f.parent_id
             WHERE f.slug = :slug',
            ['slug' => $slug],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function children(int $forumId): array
    {
        return $this->db->select(
            'SELECT ' . self::SELECT . ',
                    t.title AS last_topic_title, t.slug AS last_topic_slug,
                    lu.username AS last_post_username
             FROM forums f
             LEFT JOIN topics t ON t.id = f.last_topic_id
             LEFT JOIN users lu ON lu.id = f.last_post_user_id
             WHERE f.parent_id = :parent ORDER BY f.position ASC, f.name ASC',
            ['parent' => $forumId],
        );
    }

    /** @return array<int,int> The forum plus every descendant id. */
    public function descendantIds(int $forumId): array
    {
        $ids = [$forumId];
        $frontier = [$forumId];

        // Depth is small (category > forum > subforum), so an iterative walk is
        // cheaper and clearer than a recursive CTE here.
        for ($depth = 0; $depth < 6 && $frontier !== []; $depth++) {
            [$placeholders, $bindings] = Database::inClause($frontier, 'f');

            $rows = $this->db->select(
                'SELECT id FROM forums WHERE parent_id IN (' . $placeholders . ')',
                $bindings,
            );

            $frontier = array_map(static fn (array $row): int => (int) $row['id'], $rows);
            $ids = array_merge($ids, $frontier);
        }

        return array_values(array_unique($ids));
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $data['created_at'] = Dates::nowString();
        $data['updated_at'] = Dates::nowString();

        return $this->db->insert('forums', $data);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): int
    {
        $data['updated_at'] = Dates::nowString();

        return $this->db->update('forums', $data, 'id = :id', ['id' => $id]);
    }

    public function delete(int $id): int
    {
        return $this->db->delete('forums', 'id = :id', ['id' => $id]);
    }

    public function slugTaken(string $slug, ?int $exceptId = null): bool
    {
        return $this->db->selectOne(
            'SELECT id FROM forums WHERE slug = :slug AND (:except IS NULL OR id <> :except2)',
            ['slug' => $slug, 'except' => $exceptId, 'except2' => $exceptId ?? 0],
        ) !== null;
    }

    public function move(int $id, int $direction): void
    {
        $forum = $this->find($id);

        if ($forum === null) {
            return;
        }

        $this->db->execute(
            'UPDATE forums SET position = GREATEST(0, CAST(position AS SIGNED) + :delta) WHERE id = :id',
            ['delta' => $direction, 'id' => $id],
        );
    }

    /** @return array<int,array<string,mixed>> Permission rows keyed for the admin grid. */
    public function permissions(int $forumId): array
    {
        return $this->db->select(
            'SELECT fp.*, r.name AS role_name, r.slug AS role_slug, r.priority
             FROM forum_permissions fp INNER JOIN roles r ON r.id = fp.role_id
             WHERE fp.forum_id = :forum ORDER BY r.priority DESC',
            ['forum' => $forumId],
        );
    }

    /**
     * @param array<int,array{role_id:int,can_view:int,can_read:int,can_create_topic:int,can_reply:int,can_moderate:int}> $rows
     */
    public function syncPermissions(int $forumId, array $rows): void
    {
        $this->db->transaction(function () use ($forumId, $rows): void {
            $this->db->delete('forum_permissions', 'forum_id = :forum', ['forum' => $forumId]);

            foreach ($rows as $row) {
                $this->db->insert('forum_permissions', [
                    'forum_id' => $forumId,
                    'role_id' => $row['role_id'],
                    'can_view' => $row['can_view'],
                    'can_read' => $row['can_read'],
                    'can_create_topic' => $row['can_create_topic'],
                    'can_reply' => $row['can_reply'],
                    'can_moderate' => $row['can_moderate'],
                ]);
            }
        });
    }

    /**
     * Effective forum access for a set of roles. A permission is granted when
     * at least one of the viewer's roles grants it; forums without an explicit
     * row fall back to the defaults passed in.
     *
     * @param array<int,int> $roleIds
     * @return array<int,array{can_view:bool,can_read:bool,can_create_topic:bool,can_reply:bool,can_moderate:bool}>
     */
    public function accessMapForRoles(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        [$placeholders, $bindings] = Database::inClause($roleIds, 'role');

        $rows = $this->db->select(
            'SELECT forum_id,
                    MAX(can_view) AS can_view,
                    MAX(can_read) AS can_read,
                    MAX(can_create_topic) AS can_create_topic,
                    MAX(can_reply) AS can_reply,
                    MAX(can_moderate) AS can_moderate
             FROM forum_permissions WHERE role_id IN (' . $placeholders . ') GROUP BY forum_id',
            $bindings,
        );

        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row['forum_id']] = [
                'can_view' => (bool) $row['can_view'],
                'can_read' => (bool) $row['can_read'],
                'can_create_topic' => (bool) $row['can_create_topic'],
                'can_reply' => (bool) $row['can_reply'],
                'can_moderate' => (bool) $row['can_moderate'],
            ];
        }

        return $map;
    }

    /**
     * Recomputes counters and the last-post pointer for a forum and, because
     * subforum activity bubbles up, for each of its ancestors.
     */
    public function refreshCounters(int $forumId): void
    {
        $forum = $this->db->selectOne('SELECT id, parent_id FROM forums WHERE id = :id', ['id' => $forumId]);

        if ($forum === null) {
            return;
        }

        $stats = $this->db->selectOne(
            'SELECT COUNT(*) AS topics, COALESCE(SUM(post_count), 0) AS posts
             FROM topics WHERE forum_id = :forum AND deleted_at IS NULL AND is_hidden = 0',
            ['forum' => $forumId],
        ) ?? ['topics' => 0, 'posts' => 0];

        $last = $this->db->selectOne(
            'SELECT t.id AS topic_id, t.last_post_id, t.last_post_user_id, t.last_post_at
             FROM topics t
             WHERE t.forum_id = :forum AND t.deleted_at IS NULL AND t.is_hidden = 0 AND t.last_post_at IS NOT NULL
             ORDER BY t.last_post_at DESC LIMIT 1',
            ['forum' => $forumId],
        );

        $this->db->update('forums', [
            'topic_count' => (int) $stats['topics'],
            'post_count' => (int) $stats['posts'],
            'last_topic_id' => $last['topic_id'] ?? null,
            'last_post_id' => $last['last_post_id'] ?? null,
            'last_post_user_id' => $last['last_post_user_id'] ?? null,
            'last_post_at' => $last['last_post_at'] ?? null,
            'updated_at' => Dates::nowString(),
        ], 'id = :id', ['id' => $forumId]);

        if ($forum['parent_id'] !== null) {
            $this->refreshCounters((int) $forum['parent_id']);
        }
    }

    /** @return array<int,array<string,mixed>> Flat list for <select> menus. */
    public function selectList(): array
    {
        $forums = $this->allForums(false);
        $list = [];

        foreach ($forums as $forum) {
            $list[] = [
                'id' => (int) $forum['id'],
                'label' => ($forum['parent_name'] !== null ? '— ' : '') . $forum['name'],
                'category' => $forum['category_name'],
            ];
        }

        return $list;
    }

    public function countForums(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM forums');
    }
}
