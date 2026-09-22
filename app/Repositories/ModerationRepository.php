<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Dates;
use App\Support\Paginator;

final class ModerationRepository extends Repository
{
    // ------------------------------------------------------------------
    // Moderation log
    // ------------------------------------------------------------------

    /** @param array<string,mixed> $data */
    public function log(array $data): int
    {
        $data['created_at'] = Dates::nowString();

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $this->db->insert('moderation_actions', $data);
    }

    /**
     * @param array{action?:string,moderator?:int,target_user?:int,search?:string} $filters
     * @return Paginator<array<string,mixed>>
     */
    public function paginateLog(array $filters, int $page, int $perPage, string $baseUrl): Paginator
    {
        $conditions = ['1 = 1'];
        $bindings = [];

        if (($filters['action'] ?? '') !== '') {
            $conditions[] = 'ma.action = :action';
            $bindings['action'] = $filters['action'];
        }

        if (($filters['moderator'] ?? 0) > 0) {
            $conditions[] = 'ma.moderator_id = :moderator';
            $bindings['moderator'] = (int) $filters['moderator'];
        }

        if (($filters['target_user'] ?? 0) > 0) {
            $conditions[] = 'ma.target_user_id = :target';
            $bindings['target'] = (int) $filters['target_user'];
        }

        if (($filters['search'] ?? '') !== '') {
            $conditions[] = '(ma.summary LIKE :search OR ma.reason LIKE :search2)';
            $bindings['search'] = '%' . $filters['search'] . '%';
            $bindings['search2'] = '%' . $filters['search'] . '%';
        }

        $where = implode(' AND ', $conditions);

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM moderation_actions ma WHERE ' . $where, $bindings);

        $rows = $this->db->select(
            'SELECT ma.*, m.username AS moderator_username, m.primary_role_id AS moderator_role_id,
                    t.username AS target_username, t.primary_role_id AS target_role_id
             FROM moderation_actions ma
             LEFT JOIN users m ON m.id = ma.moderator_id
             LEFT JOIN users t ON t.id = ma.target_user_id
             WHERE ' . $where . ' ORDER BY ma.created_at DESC, ma.id DESC LIMIT :limit OFFSET :offset',
            array_merge($bindings, ['limit' => $perPage, 'offset' => Paginator::offset($page, $perPage)]),
        );

        $query = array_filter([
            'action' => $filters['action'] ?? '',
            'moderator' => $filters['moderator'] ?? '',
            'target_user' => $filters['target_user'] ?? '',
            'search' => $filters['search'] ?? '',
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        return new Paginator($rows, $total, $perPage, $page, $baseUrl, $query);
    }

    /** @return array<int,array<string,mixed>> */
    public function recentLog(int $limit = 8): array
    {
        return $this->db->select(
            'SELECT ma.*, m.username AS moderator_username, m.primary_role_id AS moderator_role_id,
                    t.username AS target_username, t.primary_role_id AS target_role_id
             FROM moderation_actions ma
             LEFT JOIN users m ON m.id = ma.moderator_id
             LEFT JOIN users t ON t.id = ma.target_user_id
             ORDER BY ma.id DESC LIMIT :limit',
            ['limit' => $limit],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function logForUser(int $userId, int $limit = 25): array
    {
        return $this->db->select(
            'SELECT ma.*, m.username AS moderator_username FROM moderation_actions ma
             LEFT JOIN users m ON m.id = ma.moderator_id
             WHERE ma.target_user_id = :user ORDER BY ma.id DESC LIMIT :limit',
            ['user' => $userId, 'limit' => $limit],
        );
    }

    /** @return array<int,string> Distinct action names, for the log filter. */
    public function actionTypes(): array
    {
        $rows = $this->db->select('SELECT DISTINCT action FROM moderation_actions ORDER BY action ASC');

        return array_map(static fn (array $row): string => (string) $row['action'], $rows);
    }

    // ------------------------------------------------------------------
    // Bans and suspensions
    // ------------------------------------------------------------------

    /** @param array<string,mixed> $data */
    public function createBan(array $data): int
    {
        $data['created_at'] = Dates::nowString();

        return $this->db->insert('bans', $data);
    }

    /** @return array<string,mixed>|null The active ban for a user, if any. */
    public function activeBan(int $userId): ?array
    {
        return $this->db->selectOne(
            'SELECT b.*, c.username AS created_by_username FROM bans b
             LEFT JOIN users c ON c.id = b.created_by
             WHERE b.user_id = :user AND b.is_active = 1
               AND (b.expires_at IS NULL OR b.expires_at > UTC_TIMESTAMP())
             ORDER BY b.id DESC LIMIT 1',
            ['user' => $userId],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function bansForUser(int $userId): array
    {
        return $this->db->select(
            'SELECT b.*, c.username AS created_by_username, l.username AS lifted_by_username
             FROM bans b
             LEFT JOIN users c ON c.id = b.created_by
             LEFT JOIN users l ON l.id = b.lifted_by
             WHERE b.user_id = :user ORDER BY b.id DESC',
            ['user' => $userId],
        );
    }

    public function lift(int $banId, int $moderatorId): int
    {
        return $this->db->update('bans', [
            'is_active' => 0,
            'lifted_by' => $moderatorId,
            'lifted_at' => Dates::nowString(),
        ], 'id = :id', ['id' => $banId]);
    }

    public function liftAllForUser(int $userId, int $moderatorId): int
    {
        return $this->db->update('bans', [
            'is_active' => 0,
            'lifted_by' => $moderatorId,
            'lifted_at' => Dates::nowString(),
        ], 'user_id = :user AND is_active = 1', ['user' => $userId]);
    }

    /**
     * Clears expired suspensions and returns the affected user ids so their
     * account status can be restored.
     *
     * @return array<int,int>
     */
    public function expireBans(): array
    {
        $rows = $this->db->select(
            'SELECT id, user_id FROM bans WHERE is_active = 1 AND expires_at IS NOT NULL AND expires_at <= UTC_TIMESTAMP()',
        );

        foreach ($rows as $row) {
            $this->db->update('bans', ['is_active' => 0, 'lifted_at' => Dates::nowString()], 'id = :id', ['id' => (int) $row['id']]);
        }

        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    /** @return Paginator<array<string,mixed>> */
    public function paginateBans(string $filter, int $page, int $perPage, string $baseUrl): Paginator
    {
        $where = match ($filter) {
            'active' => 'b.is_active = 1 AND (b.expires_at IS NULL OR b.expires_at > UTC_TIMESTAMP())',
            'expired' => 'b.is_active = 0 OR (b.expires_at IS NOT NULL AND b.expires_at <= UTC_TIMESTAMP())',
            default => '1 = 1',
        };

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM bans b WHERE ' . $where);

        $rows = $this->db->select(
            'SELECT b.*, u.username, c.username AS created_by_username
             FROM bans b
             INNER JOIN users u ON u.id = b.user_id
             LEFT JOIN users c ON c.id = b.created_by
             WHERE ' . $where . ' ORDER BY b.id DESC LIMIT :limit OFFSET :offset',
            ['limit' => $perPage, 'offset' => Paginator::offset($page, $perPage)],
        );

        return new Paginator($rows, $total, $perPage, $page, $baseUrl, $filter !== '' ? ['filter' => $filter] : []);
    }

    public function activeBanCount(): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM bans WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())',
        );
    }

    // ------------------------------------------------------------------
    // Warnings and staff notes
    // ------------------------------------------------------------------

    /** @param array<string,mixed> $data */
    public function createWarning(array $data): int
    {
        $data['created_at'] = Dates::nowString();

        return $this->db->insert('warnings', $data);
    }

    /** @return array<int,array<string,mixed>> */
    public function warningsForUser(int $userId): array
    {
        return $this->db->select(
            'SELECT w.*, m.username AS moderator_username FROM warnings w
             LEFT JOIN users m ON m.id = w.moderator_id
             WHERE w.user_id = :user ORDER BY w.id DESC',
            ['user' => $userId],
        );
    }

    public function activeWarningPoints(int $userId): int
    {
        return (int) $this->db->scalar(
            'SELECT COALESCE(SUM(points), 0) FROM warnings
             WHERE user_id = :user AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())',
            ['user' => $userId],
        );
    }

    /** @param array<string,mixed> $data */
    public function addNote(array $data): int
    {
        $data['created_at'] = Dates::nowString();

        return $this->db->insert('user_notes', $data);
    }

    /** @return array<int,array<string,mixed>> */
    public function notesForUser(int $userId): array
    {
        return $this->db->select(
            'SELECT n.*, a.username AS author_username FROM user_notes n
             LEFT JOIN users a ON a.id = n.author_id
             WHERE n.user_id = :user ORDER BY n.id DESC',
            ['user' => $userId],
        );
    }

    public function deleteNote(int $id): int
    {
        return $this->db->delete('user_notes', 'id = :id', ['id' => $id]);
    }
}
