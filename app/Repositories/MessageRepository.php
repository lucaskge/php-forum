<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Dates;
use App\Support\Paginator;

final class MessageRepository extends Repository
{
    /** @return Paginator<array<string,mixed>> */
    public function inbox(int $userId, int $page, int $perPage, string $baseUrl): Paginator
    {
        $where = 'm.recipient_id = :user AND m.recipient_deleted = 0';
        $bindings = ['user' => $userId];

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM private_messages m WHERE ' . $where, $bindings);

        $rows = $this->db->select(
            'SELECT m.id, m.subject, m.body, m.is_read, m.created_at, m.sender_id,
                    u.username AS sender_username, u.avatar_path AS sender_avatar,
                    u.primary_role_id AS sender_role_id, r.colour AS sender_colour
             FROM private_messages m
             LEFT JOIN users u ON u.id = m.sender_id
             LEFT JOIN roles r ON r.id = u.primary_role_id
             WHERE ' . $where . ' ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset',
            array_merge($bindings, ['limit' => $perPage, 'offset' => Paginator::offset($page, $perPage)]),
        );

        return new Paginator($rows, $total, $perPage, $page, $baseUrl);
    }

    /** @return Paginator<array<string,mixed>> */
    public function sent(int $userId, int $page, int $perPage, string $baseUrl): Paginator
    {
        $where = 'm.sender_id = :user AND m.sender_deleted = 0';
        $bindings = ['user' => $userId];

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM private_messages m WHERE ' . $where, $bindings);

        $rows = $this->db->select(
            'SELECT m.id, m.subject, m.body, m.is_read, m.created_at, m.recipient_id,
                    u.username AS recipient_username, u.avatar_path AS recipient_avatar,
                    u.primary_role_id AS recipient_role_id, r.colour AS recipient_colour
             FROM private_messages m
             LEFT JOIN users u ON u.id = m.recipient_id
             LEFT JOIN roles r ON r.id = u.primary_role_id
             WHERE ' . $where . ' ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset',
            array_merge($bindings, ['limit' => $perPage, 'offset' => Paginator::offset($page, $perPage)]),
        );

        return new Paginator($rows, $total, $perPage, $page, $baseUrl);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT m.*, s.username AS sender_username, s.avatar_path AS sender_avatar,
                    s.primary_role_id AS sender_role_id, sr.colour AS sender_colour, sr.name AS sender_role,
                    rcp.username AS recipient_username, rcp.avatar_path AS recipient_avatar,
                    rcp.primary_role_id AS recipient_role_id
             FROM private_messages m
             LEFT JOIN users s ON s.id = m.sender_id
             LEFT JOIN roles sr ON sr.id = s.primary_role_id
             LEFT JOIN users rcp ON rcp.id = m.recipient_id
             WHERE m.id = :id',
            ['id' => $id],
        );
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $data['created_at'] = Dates::nowString();

        return $this->db->insert('private_messages', $data);
    }

    public function markRead(int $id, bool $read = true): void
    {
        $this->db->update('private_messages', [
            'is_read' => $read ? 1 : 0,
            'read_at' => $read ? Dates::nowString() : null,
        ], 'id = :id', ['id' => $id]);
    }

    /** Deletes only from the acting side; the row disappears once both sides drop it. */
    public function deleteFor(int $id, int $userId): void
    {
        $message = $this->db->selectOne('SELECT sender_id, recipient_id, sender_deleted, recipient_deleted FROM private_messages WHERE id = :id', ['id' => $id]);

        if ($message === null) {
            return;
        }

        $senderDeleted = (int) $message['sender_deleted'];
        $recipientDeleted = (int) $message['recipient_deleted'];

        if ((int) $message['sender_id'] === $userId) {
            $senderDeleted = 1;
        }

        if ((int) $message['recipient_id'] === $userId) {
            $recipientDeleted = 1;
        }

        if ($senderDeleted === 1 && $recipientDeleted === 1) {
            $this->db->delete('private_messages', 'id = :id', ['id' => $id]);

            return;
        }

        $this->db->update('private_messages', [
            'sender_deleted' => $senderDeleted,
            'recipient_deleted' => $recipientDeleted,
        ], 'id = :id', ['id' => $id]);
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM private_messages WHERE recipient_id = :user AND is_read = 0 AND recipient_deleted = 0',
            ['user' => $userId],
        );
    }

    /** @return array<int,array<string,mixed>> Other messages in the same thread. */
    public function thread(int $messageId, int $userId): array
    {
        $root = (int) ($this->db->scalar(
            'SELECT COALESCE(parent_id, id) FROM private_messages WHERE id = :id',
            ['id' => $messageId],
        ) ?? $messageId);

        return $this->db->select(
            'SELECT m.id, m.subject, m.created_at, m.sender_id, u.username AS sender_username,
                    u.primary_role_id AS sender_role_id
             FROM private_messages m
             LEFT JOIN users u ON u.id = m.sender_id
             WHERE (m.id = :root OR m.parent_id = :root2)
               AND (m.sender_id = :user OR m.recipient_id = :user2)
             ORDER BY m.created_at ASC',
            ['root' => $root, 'root2' => $root, 'user' => $userId, 'user2' => $userId],
        );
    }

    public function countAll(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM private_messages');
    }
}
