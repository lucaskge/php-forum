<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Dates;

final class SettingRepository extends Repository
{
    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->select('SELECT * FROM settings ORDER BY group_name ASC, position ASC, label ASC');
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->all() as $setting) {
            $grouped[(string) $setting['group_name']][] = $setting;
        }

        return $grouped;
    }

    /** @return array<string,string|null> Flat key => value map. */
    public function map(): array
    {
        $map = [];

        foreach ($this->db->select('SELECT key_name, value FROM settings') as $row) {
            $map[(string) $row['key_name']] = $row['value'] === null ? null : (string) $row['value'];
        }

        return $map;
    }

    /** @return array<string,mixed>|null */
    public function find(string $key): ?array
    {
        return $this->db->selectOne('SELECT * FROM settings WHERE key_name = :key', ['key' => $key]);
    }

    public function put(string $key, ?string $value): void
    {
        $this->db->update('settings', [
            'value' => $value,
            'updated_at' => Dates::nowString(),
        ], 'key_name = :key', ['key' => $key]);
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $data['updated_at'] = Dates::nowString();

        return $this->db->insert('settings', $data);
    }
}
