<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use App\Support\Dates;

final class RoleRepository extends Repository
{
    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->select(
            'SELECT r.*, (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS member_count
             FROM roles r ORDER BY r.priority DESC, r.name ASC',
        );
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->selectOne('SELECT * FROM roles WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne('SELECT * FROM roles WHERE slug = :slug', ['slug' => $slug]);
    }

    /** @return array<string,mixed>|null */
    public function defaultRole(): ?array
    {
        return $this->db->selectOne('SELECT * FROM roles WHERE is_default = 1 ORDER BY priority ASC LIMIT 1');
    }

    /** @return array<string,mixed>|null */
    public function guestRole(): ?array
    {
        return $this->db->selectOne('SELECT * FROM roles WHERE is_guest = 1 LIMIT 1');
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $data['created_at'] = Dates::nowString();
        $data['updated_at'] = Dates::nowString();

        return $this->db->insert('roles', $data);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): int
    {
        $data['updated_at'] = Dates::nowString();

        return $this->db->update('roles', $data, 'id = :id', ['id' => $id]);
    }

    public function delete(int $id): int
    {
        return $this->db->delete('roles', 'id = :id AND is_system = 0', ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function rolesForUser(int $userId): array
    {
        return $this->db->select(
            'SELECT r.* FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :user ORDER BY r.priority DESC',
            ['user' => $userId],
        );
    }

    /** @return array<int,int> */
    public function roleIdsForUser(int $userId): array
    {
        $rows = $this->db->select('SELECT role_id FROM user_roles WHERE user_id = :user', ['user' => $userId]);

        return array_map(static fn (array $row): int => (int) $row['role_id'], $rows);
    }

    /** @param array<int,int> $roleIds */
    public function syncUserRoles(int $userId, array $roleIds): void
    {
        $this->db->transaction(function () use ($userId, $roleIds): void {
            $this->db->delete('user_roles', 'user_id = :user', ['user' => $userId]);

            foreach (array_unique($roleIds) as $roleId) {
                $this->db->execute(
                    'INSERT IGNORE INTO user_roles (user_id, role_id, assigned_at) VALUES (:user, :role, :at)',
                    ['user' => $userId, 'role' => (int) $roleId, 'at' => Dates::nowString()],
                );
            }
        });
    }

    public function assign(int $userId, int $roleId): void
    {
        $this->db->execute(
            'INSERT IGNORE INTO user_roles (user_id, role_id, assigned_at) VALUES (:user, :role, :at)',
            ['user' => $userId, 'role' => $roleId, 'at' => Dates::nowString()],
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function allPermissions(): array
    {
        return $this->db->select('SELECT * FROM permissions ORDER BY group_name ASC, position ASC, name ASC');
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    public function permissionsGrouped(): array
    {
        $grouped = [];

        foreach ($this->allPermissions() as $permission) {
            $grouped[(string) $permission['group_name']][] = $permission;
        }

        return $grouped;
    }

    /** @return array<int,string> Permission slugs held by the role. */
    public function permissionSlugsForRole(int $roleId): array
    {
        $rows = $this->db->select(
            'SELECT p.slug FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = :role',
            ['role' => $roleId],
        );

        return array_map(static fn (array $row): string => (string) $row['slug'], $rows);
    }

    /**
     * Union of every permission granted by any of the given roles.
     *
     * @param array<int,int> $roleIds
     * @return array<int,string>
     */
    public function permissionSlugsForRoles(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        [$placeholders, $bindings] = Database::inClause($roleIds, 'role');

        $rows = $this->db->select(
            'SELECT DISTINCT p.slug FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id IN (' . $placeholders . ')',
            $bindings,
        );

        return array_map(static fn (array $row): string => (string) $row['slug'], $rows);
    }

    /** @param array<int,int> $permissionIds */
    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->transaction(function () use ($roleId, $permissionIds): void {
            $this->db->delete('role_permissions', 'role_id = :role', ['role' => $roleId]);

            foreach (array_unique($permissionIds) as $permissionId) {
                $this->db->execute(
                    'INSERT IGNORE INTO role_permissions (role_id, permission_id, granted_at) VALUES (:role, :permission, :at)',
                    ['role' => $roleId, 'permission' => (int) $permissionId, 'at' => Dates::nowString()],
                );
            }
        });
    }

    public function memberCount(int $roleId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM user_roles WHERE role_id = :role', ['role' => $roleId]);
    }
}
