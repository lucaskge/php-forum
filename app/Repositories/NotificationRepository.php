<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Dates;
use App\Support\Paginator;

final class NotificationRepository extends Repository
{
    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $data['created_at'] = Dates::nowString();

        return $this->db->insert('notifications', $data);
    }

    /** @return Paginator<array<string,mixed>> */
    public function paginate(int $userId, int $page, int $perPage, string $baseUrl, bool $unreadOnly = false): Paginator
    {
        $where = 'n.user_id = :user' . ($unreadOnly ? ' AND n.is_read = 0' : '');
        $bindings = ['user' => $userId];

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM notifications n WHERE ' . $where, $bindings);

        $rows = $this->db->select(
            'SELECT n.*, a.username AS actor_username, a.avatar_path AS actor_avatar,
                    a.primary_role_id AS actor_role_id
             FROM notifications n LEFT JOIN users a ON a.id = n.actor_id
             WHERE ' . $where . ' ORDER BY n.created_at DESC LIMIT :limit OFFSET :offset',
            array_merge($bindings, ['limit' => $perPage, 'offset' => Paginator::offset($page, $perPage)]),
        );

        return new Paginator($rows, $total, $perPage, $page, $baseUrl, $unreadOnly ? ['filter' => 'unread'] : []);
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $userId, int $limit = 6): array
    {
        return $this->db->select(
            'SELECT n.*, a.username AS actor_username FROM notifications n
             LEFT JOIN users a ON a.id = n.actor_id
             WHERE n.user_id = :user ORDER BY n.created_at DESC LIMIT :limit',
            ['user' => $userId, 'limit' => $limit],
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM notifications WHERE id = :id', ['id' => $id]);
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :user AND is_read = 0',
            ['user' => $userId],
        );
    }

    public function markRead(int $id, int $userId): void
    {
        $this->db->update('notifications', [
            'is_read' => 1,
            'read_at' => Dates::nowString(),
        ], 'id = :id AND user_id = :user', ['id' => $id, 'user' => $userId]);
    }

    /**
     * @param array<int,int> $ids
     */
    public function markManyRead(array $ids, int $userId): int
    {
        if ($ids === []) {
            return 0;
        }

        [$placeholders, $bindings] = \App\Support\Database::inClause($ids, 'n');
        $bindings['user'] = $userId;
        $bindings['now'] = Dates::nowString();

        return $this->db->execute(
            'UPDATE notifications SET is_read = 1, read_at = :now
             WHERE user_id = :user AND is_read = 0 AND id IN (' . $placeholders . ')',
            $bindings,
        );
    }

    public function markAllRead(int $userId): int
    {
        return $this->db->update('notifications', [
            'is_read' => 1,
            'read_at' => Dates::nowString(),
        ], 'user_id = :user AND is_read = 0', ['user' => $userId]);
    }

    public function deleteFor(int $id, int $userId): void
    {
        $this->db->delete('notifications', 'id = :id AND user_id = :user', ['id' => $id, 'user' => $userId]);
    }

    public function clear(int $userId): int
    {
        return $this->db->delete('notifications', 'user_id = :user', ['user' => $userId]);
    }
}
