<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;
use App\Support\Paginator;

/**
 * Search is intentionally kept behind its own repository: swapping the LIKE
 * scan for a FULLTEXT index or an external engine only touches this class.
 */
final class SearchRepository extends Repository
{
    /**
     * @param array{keywords?:string,author?:string,forum?:int,from?:string,to?:string,mode?:string} $criteria
     * @param array<int,int> $visibleForumIds
     * @return Paginator<array<string,mixed>>
     */
    public function search(array $criteria, array $visibleForumIds, int $page, int $perPage, string $baseUrl): Paginator
    {
        $query = array_filter([
            'q' => $criteria['keywords'] ?? '',
            'author' => $criteria['author'] ?? '',
            'forum' => $criteria['forum'] ?? '',
            'from' => $criteria['from'] ?? '',
            'to' => $criteria['to'] ?? '',
            'mode' => $criteria['mode'] ?? '',
        ], static fn ($v): bool => $v !== '' && $v !== 0 && $v !== null);

        if ($visibleForumIds === []) {
            return new Paginator([], 0, $perPage, $page, $baseUrl, $query);
        }

        $mode = ($criteria['mode'] ?? 'posts') === 'topics' ? 'topics' : 'posts';

        [$forumPlaceholders, $bindings] = Database::inClause($visibleForumIds, 'forum');

        $conditions = [
            ($mode === 'topics' ? 't.forum_id' : 'p.forum_id') . ' IN (' . $forumPlaceholders . ')',
            't.deleted_at IS NULL',
            't.is_hidden = 0',
        ];

        if ($mode === 'posts') {
            $conditions[] = 'p.deleted_at IS NULL';
            $conditions[] = 'p.is_hidden = 0';
        }

        $keywords = trim((string) ($criteria['keywords'] ?? ''));

        if ($keywords !== '') {
            $terms = array_slice(array_filter(preg_split('/\s+/', $keywords) ?: []), 0, 6);

            foreach ($terms as $index => $term) {
                $name = 'kw' . $index;

                if ($mode === 'topics') {
                    $conditions[] = 't.title LIKE :' . $name;
                } else {
                    $conditions[] = '(p.content LIKE :' . $name . ' OR t.title LIKE :' . $name . '_title)';
                    $bindings[$name . '_title'] = '%' . $term . '%';
                }

                $bindings[$name] = '%' . $term . '%';
            }
        }

        if (($criteria['author'] ?? '') !== '') {
            $conditions[] = ($mode === 'topics' ? 'au.username_canonical' : 'pu.username_canonical') . ' = :author';
            $bindings['author'] = mb_strtolower((string) $criteria['author'], 'UTF-8');
        }

        if (($criteria['forum'] ?? 0) > 0) {
            $conditions[] = ($mode === 'topics' ? 't.forum_id' : 'p.forum_id') . ' = :single_forum';
            $bindings['single_forum'] = (int) $criteria['forum'];
        }

        $dateColumn = $mode === 'topics' ? 't.created_at' : 'p.created_at';

        if (($criteria['from'] ?? '') !== '') {
            $conditions[] = $dateColumn . ' >= :from';
            $bindings['from'] = $criteria['from'] . ' 00:00:00';
        }

        if (($criteria['to'] ?? '') !== '') {
            $conditions[] = $dateColumn . ' <= :to';
            $bindings['to'] = $criteria['to'] . ' 23:59:59';
        }

        $where = implode(' AND ', $conditions);

        if ($mode === 'topics') {
            $countSql = 'SELECT COUNT(*) FROM topics t
                         LEFT JOIN users au ON au.id = t.user_id
                         WHERE ' . $where;

            $rowsSql = 'SELECT t.id AS topic_id, t.title AS topic_title, t.slug AS topic_slug, t.created_at,
                               t.post_count, t.view_count, t.first_post_id AS post_id,
                               au.username AS author_username, au.id AS author_id,
                               f.name AS forum_name, f.slug AS forum_slug,
                               fp.content AS excerpt_source
                        FROM topics t
                        INNER JOIN forums f ON f.id = t.forum_id
                        LEFT JOIN users au ON au.id = t.user_id
                        LEFT JOIN posts fp ON fp.id = t.first_post_id
                        WHERE ' . $where . '
                        ORDER BY t.created_at DESC LIMIT :limit OFFSET :offset';
        } else {
            $countSql = 'SELECT COUNT(*) FROM posts p
                         INNER JOIN topics t ON t.id = p.topic_id
                         LEFT JOIN users pu ON pu.id = p.user_id
                         WHERE ' . $where;

            $rowsSql = 'SELECT p.id AS post_id, p.content AS excerpt_source, p.created_at, p.topic_id,
                               t.title AS topic_title, t.slug AS topic_slug, t.post_count, t.view_count,
                               pu.username AS author_username, pu.id AS author_id,
                               f.name AS forum_name, f.slug AS forum_slug
                        FROM posts p
                        INNER JOIN topics t ON t.id = p.topic_id
                        INNER JOIN forums f ON f.id = p.forum_id
                        LEFT JOIN users pu ON pu.id = p.user_id
                        WHERE ' . $where . '
                        ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset';
        }

        $total = (int) $this->db->scalar($countSql, $bindings);

        $rows = $this->db->select(
            $rowsSql,
            array_merge($bindings, ['limit' => $perPage, 'offset' => Paginator::offset($page, $perPage)]),
        );

        return new Paginator($rows, $total, $perPage, $page, $baseUrl, $query);
    }
}
