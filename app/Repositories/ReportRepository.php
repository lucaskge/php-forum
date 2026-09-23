<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Dates;
use App\Support\Paginator;

final class ReportRepository extends Repository
{
    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $data['created_at'] = Dates::nowString();

        return $this->db->insert('reports', $data);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT rp.*, r.username AS reporter_username, r.primary_role_id AS reporter_role_id,
                    ru.username AS reported_username, ru.primary_role_id AS reported_role_id,
                    h.username AS handler_username, rt.slug AS content_topic_slug
             FROM reports rp
             LEFT JOIN users r ON r.id = rp.reporter_id
             LEFT JOIN users ru ON ru.id = rp.reported_user_id
             LEFT JOIN users h ON h.id = rp.handled_by
             LEFT JOIN topics rt ON rt.id = rp.content_id AND rp.content_type = :topic_type
             WHERE rp.id = :id',
            ['id' => $id, 'topic_type' => 'topic'],
        );
    }

    /** @return Paginator<array<string,mixed>> */
    public function paginate(string $status, int $page, int $perPage, string $baseUrl): Paginator
    {
        $where = $status === 'all' ? '1 = 1' : 'rp.status = :status';
        $bindings = $status === 'all' ? [] : ['status' => $status];

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM reports rp WHERE ' . $where, $bindings);

        $rows = $this->db->select(
            'SELECT rp.*, r.username AS reporter_username, r.primary_role_id AS reporter_role_id,
                    ru.username AS reported_username, ru.primary_role_id AS reported_role_id,
                    h.username AS handler_username, rt.slug AS content_topic_slug
             FROM reports rp
             LEFT JOIN users r ON r.id = rp.reporter_id
             LEFT JOIN users ru ON ru.id = rp.reported_user_id
             LEFT JOIN users h ON h.id = rp.handled_by
             LEFT JOIN topics rt ON rt.id = rp.content_id AND rp.content_type = :topic_type
             WHERE ' . $where . ' ORDER BY rp.created_at DESC LIMIT :limit OFFSET :offset',
            array_merge($bindings, [
                'topic_type' => 'topic',
                'limit' => $perPage,
                'offset' => Paginator::offset($page, $perPage),
            ]),
        );

        return new Paginator($rows, $total, $perPage, $page, $baseUrl, $status === 'all' ? [] : ['status' => $status]);
    }

    public function resolve(int $id, int $moderatorId, string $status, string $action, ?string $notes): int
    {
        return $this->db->update('reports', [
            'status' => $status,
            'handled_by' => $moderatorId,
            'handled_at' => Dates::nowString(),
            'action_taken' => $action,
            'moderator_notes' => $notes,
        ], 'id = :id', ['id' => $id]);
    }

    public function pendingCount(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM reports WHERE status = :status', ['status' => 'pending']);
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM reports WHERE status = :status', ['status' => $status]);
    }

    public function alreadyReported(int $reporterId, string $type, int $contentId): bool
    {
        return $this->db->selectOne(
            'SELECT id FROM reports WHERE reporter_id = :user AND content_type = :type AND content_id = :content AND status = :pending',
            ['user' => $reporterId, 'type' => $type, 'content' => $contentId, 'pending' => 'pending'],
        ) !== null;
    }

    /** @return array<int,array<string,mixed>> */
    public function forUser(int $userId, int $limit = 20): array
    {
        return $this->db->select(
            'SELECT rp.*, r.username AS reporter_username FROM reports rp
             LEFT JOIN users r ON r.id = rp.reporter_id
             WHERE rp.reported_user_id = :user ORDER BY rp.created_at DESC LIMIT :limit',
            ['user' => $userId, 'limit' => $limit],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 5): array
    {
        return $this->db->select(
            'SELECT rp.id, rp.content_type, rp.reason, rp.status, rp.created_at,
                    r.username AS reporter_username, ru.username AS reported_username
             FROM reports rp
             LEFT JOIN users r ON r.id = rp.reporter_id
             LEFT JOIN users ru ON ru.id = rp.reported_user_id
             ORDER BY rp.created_at DESC LIMIT :limit',
            ['limit' => $limit],
        );
    }
}
