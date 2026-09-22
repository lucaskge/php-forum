<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Dates;
use App\Support\Paginator;

final class ChatRepository extends Repository
{
    /** @return array<int,array<string,mixed>> */
    public function rooms(bool $activeOnly = true): array
    {
        return $this->db->select(
            'SELECT * FROM chat_rooms' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY position ASC, name ASC',
        );
    }

    /** @return array<string,mixed>|null */
    public function findRoom(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM chat_rooms WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string,mixed>|null */
    public function findRoomBySlug(string $slug): ?array
    {
        return $this->db->selectOne('SELECT * FROM chat_rooms WHERE slug = :slug', ['slug' => $slug]);
    }

    /** @return array<string,mixed>|null */
    public function defaultRoom(): ?array
    {
        return $this->db->selectOne('SELECT * FROM chat_rooms WHERE is_active = 1 ORDER BY position ASC, id ASC LIMIT 1');
    }

    /** @param array<string,mixed> $data */
    public function createRoom(array $data): int
    {
        $data['created_at'] = Dates::nowString();
        $data['updated_at'] = Dates::nowString();

        return $this->db->insert('chat_rooms', $data);
    }

    /** @param array<string,mixed> $data */
    public function updateRoom(int $id, array $data): int
    {
        $data['updated_at'] = Dates::nowString();

        return $this->db->update('chat_rooms', $data, 'id = :id', ['id' => $id]);
    }

    public function deleteRoom(int $id): int
    {
        return $this->db->delete('chat_rooms', 'id = :id', ['id' => $id]);
    }

    /**
     * Most recent messages, oldest first so the transcript reads top-to-bottom.
     *
     * @return array<int,array<string,mixed>>
     */
    public function recentMessages(int $roomId, int $limit = 60, bool $includeDeleted = false): array
    {
        $rows = $this->db->select(
            'SELECT m.id, m.room_id, m.user_id, m.type, m.content, m.is_deleted, m.deleted_at, m.created_at,
                    u.username, u.avatar_path, u.title AS user_title, u.primary_role_id,
                    r.name AS role_name, r.colour AS role_colour, r.is_staff,
                    d.username AS deleted_by_username
             FROM chat_messages m
             LEFT JOIN users u ON u.id = m.user_id
             LEFT JOIN roles r ON r.id = u.primary_role_id
             LEFT JOIN users d ON d.id = m.deleted_by
             WHERE m.room_id = :room' . ($includeDeleted ? '' : ' AND m.is_deleted = 0') . '
             ORDER BY m.id DESC LIMIT :limit',
            ['room' => $roomId, 'limit' => $limit],
        );

        return array_reverse($rows);
    }

    /** @return array<string,mixed>|null */
    public function findMessage(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT m.*, u.username FROM chat_messages m LEFT JOIN users u ON u.id = m.user_id WHERE m.id = :id',
            ['id' => $id],
        );
    }

    /** @param array<string,mixed> $data */
    public function createMessage(array $data): int
    {
        $data['created_at'] = Dates::nowString();

        return $this->db->insert('chat_messages', $data);
    }

    public function deleteMessage(int $id, int $moderatorId): int
    {
        return $this->db->update('chat_messages', [
            'is_deleted' => 1,
            'deleted_by' => $moderatorId,
            'deleted_at' => Dates::nowString(),
        ], 'id = :id', ['id' => $id]);
    }

    public function purgeUserMessages(int $userId, int $roomId, int $moderatorId): int
    {
        return $this->db->update('chat_messages', [
            'is_deleted' => 1,
            'deleted_by' => $moderatorId,
            'deleted_at' => Dates::nowString(),
        ], 'user_id = :user AND room_id = :room AND is_deleted = 0', ['user' => $userId, 'room' => $roomId]);
    }

    public function lastMessageAt(int $roomId, int $userId): ?string
    {
        $value = $this->db->scalar(
            'SELECT created_at FROM chat_messages WHERE room_id = :room AND user_id = :user ORDER BY id DESC LIMIT 1',
            ['room' => $roomId, 'user' => $userId],
        );

        return $value === null ? null : (string) $value;
    }

    public function messageCount(?int $roomId = null): int
    {
        if ($roomId === null) {
            return (int) $this->db->scalar('SELECT COUNT(*) FROM chat_messages WHERE is_deleted = 0');
        }

        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM chat_messages WHERE room_id = :room AND is_deleted = 0',
            ['room' => $roomId],
        );
    }

    // ------------------------------------------------------------------
    // Presence
    // ------------------------------------------------------------------

    public function touchPresence(int $roomId, int $userId): void
    {
        $this->db->execute(
            'INSERT INTO chat_presence (room_id, user_id, last_seen_at) VALUES (:room, :user, :now)
             ON DUPLICATE KEY UPDATE last_seen_at = :now2',
            ['room' => $roomId, 'user' => $userId, 'now' => Dates::nowString(), 'now2' => Dates::nowString()],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function presentUsers(int $roomId, int $windowSeconds = 300): array
    {
        return $this->db->select(
            'SELECT u.id, u.username, u.avatar_path, u.primary_role_id, cp.last_seen_at,
                    r.colour AS role_colour, r.name AS role_name, r.is_staff
             FROM chat_presence cp
             INNER JOIN users u ON u.id = cp.user_id
             LEFT JOIN roles r ON r.id = u.primary_role_id
             WHERE cp.room_id = :room AND cp.last_seen_at >= (UTC_TIMESTAMP() - INTERVAL :window SECOND)
             ORDER BY r.priority DESC, u.username ASC',
            ['room' => $roomId, 'window' => $windowSeconds],
        );
    }

    // ------------------------------------------------------------------
    // Chat bans and mutes
    // ------------------------------------------------------------------

    /** @param array<string,mixed> $data */
    public function createBan(array $data): int
    {
        $data['created_at'] = Dates::nowString();

        return $this->db->insert('chat_bans', $data);
    }

    /** @return array<string,mixed>|null */
    public function activeRestriction(int $userId, ?int $roomId = null): ?array
    {
        return $this->db->selectOne(
            'SELECT * FROM chat_bans
             WHERE user_id = :user AND is_active = 1
               AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
               AND (room_id IS NULL OR room_id = :room)
             ORDER BY FIELD(type, :ban, :mute), id DESC LIMIT 1',
            ['user' => $userId, 'room' => $roomId ?? 0, 'ban' => 'ban', 'mute' => 'mute'],
        );
    }

    public function liftRestriction(int $id): int
    {
        return $this->db->update('chat_bans', ['is_active' => 0], 'id = :id', ['id' => $id]);
    }

    /** @return Paginator<array<string,mixed>> */
    public function paginateRestrictions(int $page, int $perPage, string $baseUrl): Paginator
    {
        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM chat_bans');

        $rows = $this->db->select(
            'SELECT cb.*, u.username, m.username AS moderator_username, cr.name AS room_name
             FROM chat_bans cb
             INNER JOIN users u ON u.id = cb.user_id
             LEFT JOIN users m ON m.id = cb.moderator_id
             LEFT JOIN chat_rooms cr ON cr.id = cb.room_id
             ORDER BY cb.id DESC LIMIT :limit OFFSET :offset',
            ['limit' => $perPage, 'offset' => Paginator::offset($page, $perPage)],
        );

        return new Paginator($rows, $total, $perPage, $page, $baseUrl);
    }

    public function activeRestrictionCount(): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM chat_bans WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())',
        );
    }
}
