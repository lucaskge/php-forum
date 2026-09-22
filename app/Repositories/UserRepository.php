<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Dates;
use App\Support\Paginator;

final class UserRepository extends Repository
{
    private const SELECT = 'u.id, u.username, u.username_canonical, u.email, u.password_hash, u.primary_role_id,
            u.title, u.avatar_path, u.bio, u.signature, u.location, u.website, u.timezone, u.post_count,
            u.topic_count, u.reputation, u.warning_points, u.status, u.show_online, u.notify_replies,
            u.notify_mentions, u.notify_quotes, u.notify_messages, u.posts_per_page, u.email_verified_at,
            u.last_active_at, u.last_login_at, u.last_ip, u.created_at, u.updated_at,
            r.name AS role_name, r.slug AS role_slug, r.colour AS role_colour, r.is_staff AS role_is_staff';

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT ' . self::SELECT . ' FROM users u LEFT JOIN roles r ON r.id = u.primary_role_id WHERE u.id = :id',
            ['id' => $id],
        );
    }

    /** @return array<string,mixed>|null */
    public function findByUsername(string $username): ?array
    {
        return $this->db->selectOne(
            'SELECT ' . self::SELECT . ' FROM users u LEFT JOIN roles r ON r.id = u.primary_role_id
             WHERE u.username_canonical = :username',
            ['username' => mb_strtolower($username, 'UTF-8')],
        );
    }

    /** @return array<string,mixed>|null */
    public function findByEmail(string $email): ?array
    {
        return $this->db->selectOne(
            'SELECT ' . self::SELECT . ' FROM users u LEFT JOIN roles r ON r.id = u.primary_role_id
             WHERE u.email = :email',
            ['email' => mb_strtolower($email, 'UTF-8')],
        );
    }

    /** @return array<string,mixed>|null Accepts either a username or an e-mail address. */
    public function findByIdentifier(string $identifier): ?array
    {
        return str_contains($identifier, '@')
            ? $this->findByEmail($identifier)
            : $this->findByUsername($identifier);
    }

    public function usernameTaken(string $username, ?int $exceptId = null): bool
    {
        $row = $this->db->selectOne(
            'SELECT id FROM users WHERE username_canonical = :username AND (:except IS NULL OR id <> :except2)',
            [
                'username' => mb_strtolower($username, 'UTF-8'),
                'except' => $exceptId,
                'except2' => $exceptId ?? 0,
            ],
        );

        return $row !== null;
    }

    public function emailTaken(string $email, ?int $exceptId = null): bool
    {
        $row = $this->db->selectOne(
            'SELECT id FROM users WHERE email = :email AND (:except IS NULL OR id <> :except2)',
            [
                'email' => mb_strtolower($email, 'UTF-8'),
                'except' => $exceptId,
                'except2' => $exceptId ?? 0,
            ],
        );

        return $row !== null;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $data['username_canonical'] = mb_strtolower((string) $data['username'], 'UTF-8');
        $data['email'] = mb_strtolower((string) $data['email'], 'UTF-8');
        $data['created_at'] = Dates::nowString();
        $data['updated_at'] = Dates::nowString();

        return $this->db->insert('users', $data);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): int
    {
        if (isset($data['username'])) {
            $data['username_canonical'] = mb_strtolower((string) $data['username'], 'UTF-8');
        }

        if (isset($data['email'])) {
            $data['email'] = mb_strtolower((string) $data['email'], 'UTF-8');
        }

        $data['updated_at'] = Dates::nowString();

        return $this->db->update('users', $data, 'id = :id', ['id' => $id]);
    }

    public function delete(int $id): int
    {
        return $this->db->delete('users', 'id = :id', ['id' => $id]);
    }

    public function touchActivity(int $id, string $ip): void
    {
        $this->db->execute(
            'UPDATE users SET last_active_at = :now, last_ip = :ip WHERE id = :id',
            ['now' => Dates::nowString(), 'ip' => $ip, 'id' => $id],
        );
    }

    public function recordLogin(int $id, string $ip): void
    {
        $this->db->execute(
            'UPDATE users SET last_login_at = :now, last_active_at = :now2, last_ip = :ip WHERE id = :id',
            ['now' => Dates::nowString(), 'now2' => Dates::nowString(), 'ip' => $ip, 'id' => $id],
        );
    }

    public function incrementPostCount(int $id, int $by = 1): void
    {
        $this->db->execute(
            'UPDATE users SET post_count = GREATEST(0, CAST(post_count AS SIGNED) + :by) WHERE id = :id',
            ['by' => $by, 'id' => $id],
        );
    }

    public function incrementTopicCount(int $id, int $by = 1): void
    {
        $this->db->execute(
            'UPDATE users SET topic_count = GREATEST(0, CAST(topic_count AS SIGNED) + :by) WHERE id = :id',
            ['by' => $by, 'id' => $id],
        );
    }

    public function addReputation(int $id, int $by): void
    {
        $this->db->execute('UPDATE users SET reputation = reputation + :by WHERE id = :id', ['by' => $by, 'id' => $id]);
    }

    /**
     * @param array{search?:string,role?:int,status?:string,sort?:string} $filters
     * @return Paginator<array<string,mixed>>
     */
    public function paginate(array $filters, int $page, int $perPage, string $baseUrl): Paginator
    {
        $where = ['1 = 1'];
        $bindings = [];

        if (($filters['search'] ?? '') !== '') {
            $where[] = '(u.username LIKE :search OR u.email LIKE :search2)';
            $bindings['search'] = '%' . $filters['search'] . '%';
            $bindings['search2'] = '%' . $filters['search'] . '%';
        }

        if (($filters['role'] ?? 0) > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role_id = :role)';
            $bindings['role'] = (int) $filters['role'];
        }

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'u.status = :status';
            $bindings['status'] = $filters['status'];
        }

        $order = match ($filters['sort'] ?? 'recent') {
            'oldest' => 'u.created_at ASC',
            'posts' => 'u.post_count DESC',
            'username' => 'u.username_canonical ASC',
            'active' => 'u.last_active_at DESC',
            default => 'u.created_at DESC',
        };

        $clause = implode(' AND ', $where);

        $total = (int) $this->db->scalar('SELECT COUNT(*) FROM users u WHERE ' . $clause, $bindings);

        $rows = $this->db->select(
            'SELECT ' . self::SELECT . ' FROM users u LEFT JOIN roles r ON r.id = u.primary_role_id
             WHERE ' . $clause . ' ORDER BY ' . $order . ' LIMIT :limit OFFSET :offset',
            array_merge($bindings, ['limit' => $perPage, 'offset' => Paginator::offset($page, $perPage)]),
        );

        $query = array_filter([
            'search' => $filters['search'] ?? '',
            'role' => $filters['role'] ?? '',
            'status' => $filters['status'] ?? '',
            'sort' => $filters['sort'] ?? '',
        ], static fn ($v): bool => $v !== '' && $v !== 0);

        return new Paginator($rows, $total, $perPage, $page, $baseUrl, $query);
    }

    /** @return array<int,array<string,mixed>> */
    public function newest(int $limit = 5): array
    {
        return $this->db->select(
            'SELECT u.id, u.username, u.avatar_path, u.created_at, u.primary_role_id, r.colour AS role_colour
             FROM users u LEFT JOIN roles r ON r.id = u.primary_role_id
             WHERE u.status <> :banned ORDER BY u.created_at DESC LIMIT :limit',
            ['banned' => 'banned', 'limit' => $limit],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function staff(): array
    {
        return $this->db->select(
            'SELECT DISTINCT u.id, u.username, u.avatar_path, u.last_active_at, r.id AS primary_role_id,
                    r.name AS role_name, r.colour AS role_colour, r.priority
             FROM users u
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE r.is_staff = 1 AND u.status = :active
             ORDER BY r.priority DESC, u.username ASC',
            ['active' => 'active'],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function topPosters(int $limit = 10): array
    {
        return $this->db->select(
            'SELECT u.id, u.username, u.avatar_path, u.post_count, u.primary_role_id, r.colour AS role_colour
             FROM users u LEFT JOIN roles r ON r.id = u.primary_role_id
             WHERE u.post_count > 0 ORDER BY u.post_count DESC LIMIT :limit',
            ['limit' => $limit],
        );
    }

    public function countAll(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM users');
    }

    public function countSince(string $since): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM users WHERE created_at >= :since', ['since' => $since]);
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM users WHERE status = :status', ['status' => $status]);
    }

    /** @return array<string,mixed>|null */
    public function latest(): ?array
    {
        return $this->db->selectOne('SELECT id, username FROM users WHERE status <> :banned ORDER BY id DESC LIMIT 1', ['banned' => 'banned']);
    }

    /**
     * Username suggestions for the compose form — server rendered, no lookahead.
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $term, int $limit = 10): array
    {
        return $this->db->select(
            'SELECT id, username, avatar_path FROM users
             WHERE username_canonical LIKE :term AND status = :active ORDER BY username_canonical LIMIT :limit',
            ['term' => mb_strtolower($term, 'UTF-8') . '%', 'active' => 'active', 'limit' => $limit],
        );
    }

    /**
     * @param array<int,string> $usernames
     * @return array<int,array<string,mixed>>
     */
    public function findManyByUsernames(array $usernames): array
    {
        if ($usernames === []) {
            return [];
        }

        $lower = array_map(static fn (string $u): string => mb_strtolower($u, 'UTF-8'), $usernames);
        [$placeholders, $bindings] = \App\Support\Database::inClause($lower, 'u');

        return $this->db->select(
            'SELECT id, username, notify_mentions FROM users WHERE username_canonical IN (' . $placeholders . ')',
            $bindings,
        );
    }
}
