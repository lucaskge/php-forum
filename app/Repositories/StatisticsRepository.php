<?php

declare(strict_types=1);

namespace App\Repositories;

final class StatisticsRepository extends Repository
{
    /** @return array<string,int|string|null> */
    public function boardSummary(): array
    {
        $row = $this->db->selectOne(
            'SELECT
                (SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL AND is_hidden = 0) AS topics,
                (SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL AND is_hidden = 0) AS posts,
                (SELECT COUNT(*) FROM users WHERE status <> :banned) AS members,
                (SELECT COUNT(*) FROM forums WHERE is_visible = 1) AS forums',
            ['banned' => 'banned'],
        ) ?? [];

        $newest = $this->db->selectOne(
            'SELECT id, username, primary_role_id FROM users WHERE status <> :banned ORDER BY id DESC LIMIT 1',
            ['banned' => 'banned'],
        );

        return [
            'topics' => (int) ($row['topics'] ?? 0),
            'posts' => (int) ($row['posts'] ?? 0),
            'members' => (int) ($row['members'] ?? 0),
            'forums' => (int) ($row['forums'] ?? 0),
            'newest_member' => $newest['username'] ?? null,
            'newest_member_id' => isset($newest['id']) ? (int) $newest['id'] : null,
            'newest_member_role_id' => $newest['primary_role_id'] ?? null,
        ];
    }

    /**
     * @return array<int,array{day:string,count:int}> Daily post volume.
     */
    public function postsPerDay(int $days = 14): array
    {
        $rows = $this->db->select(
            'SELECT DATE(created_at) AS day, COUNT(*) AS total
             FROM posts
             WHERE created_at >= (UTC_TIMESTAMP() - INTERVAL :days DAY) AND deleted_at IS NULL
             GROUP BY DATE(created_at) ORDER BY day ASC',
            ['days' => $days],
        );

        return array_map(
            static fn (array $row): array => ['day' => (string) $row['day'], 'count' => (int) $row['total']],
            $rows,
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function busiestForums(int $limit = 5): array
    {
        return $this->db->select(
            'SELECT f.id, f.name, f.slug, f.post_count, f.topic_count
             FROM forums f WHERE f.is_visible = 1 ORDER BY f.post_count DESC LIMIT :limit',
            ['limit' => $limit],
        );
    }
}
