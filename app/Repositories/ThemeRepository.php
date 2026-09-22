<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Dates;

final class ThemeRepository extends Repository
{
    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->select('SELECT * FROM themes ORDER BY is_active DESC, name ASC');
    }

    /** @return array<string,mixed>|null */
    public function active(): ?array
    {
        return $this->db->selectOne('SELECT * FROM themes WHERE is_active = 1 AND is_enabled = 1 LIMIT 1');
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->selectOne('SELECT * FROM themes WHERE slug = :slug', ['slug' => $slug]);
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $data['installed_at'] = Dates::nowString();
        $data['updated_at'] = Dates::nowString();

        return $this->db->insert('themes', $data);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): int
    {
        $data['updated_at'] = Dates::nowString();

        return $this->db->update('themes', $data, 'id = :id', ['id' => $id]);
    }

    public function delete(int $id): int
    {
        return $this->db->delete('themes', 'id = :id AND is_active = 0', ['id' => $id]);
    }

    public function activate(string $slug): void
    {
        $this->db->transaction(function () use ($slug): void {
            $this->db->execute('UPDATE themes SET is_active = 0');
            $this->db->update('themes', [
                'is_active' => 1,
                'is_enabled' => 1,
                'updated_at' => Dates::nowString(),
            ], 'slug = :slug', ['slug' => $slug]);
        });
    }

    public function setEnabled(string $slug, bool $enabled): void
    {
        $this->db->update('themes', [
            'is_enabled' => $enabled ? 1 : 0,
            'updated_at' => Dates::nowString(),
        ], 'slug = :slug AND is_active = 0', ['slug' => $slug]);
    }
}
