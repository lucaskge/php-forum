<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ForumRepository;

/**
 * Read-side helper that turns the flat forum table into the category → forum →
 * subforum tree the index renders, filtered by what the viewer may see.
 */
final class ForumService
{
    private ForumRepository $forums;

    private AccessControl $access;

    public function __construct(?ForumRepository $forums = null, ?AccessControl $access = null)
    {
        $this->forums = $forums ?? new ForumRepository();
        $this->access = $access ?? AccessControl::instance();
    }

    /**
     * @return array<int,array{category:array<string,mixed>,forums:array<int,array<string,mixed>>}>
     */
    public function tree(): array
    {
        $categories = $this->forums->allCategories(!$this->access->isAdministrator());
        $forums = $this->forums->allForums(false);

        $byParent = [];

        foreach ($forums as $forum) {
            if (!$this->access->canViewForum((int) $forum['id'])) {
                continue;
            }

            $parentId = $forum['parent_id'] === null ? 0 : (int) $forum['parent_id'];
            $byParent[$parentId][] = $forum;
        }

        $tree = [];

        foreach ($categories as $category) {
            $categoryId = (int) $category['id'];
            $rows = [];

            foreach ($byParent[0] ?? [] as $forum) {
                if ((int) $forum['category_id'] !== $categoryId) {
                    continue;
                }

                $forum['children'] = $byParent[(int) $forum['id']] ?? [];
                $forum['can_read'] = $this->access->canReadForum((int) $forum['id']);
                $rows[] = $forum;
            }

            if ($rows === []) {
                continue;
            }

            $tree[] = ['category' => $category, 'forums' => $rows];
        }

        return $tree;
    }

    /**
     * Ancestor chain for breadcrumbs, outermost first.
     *
     * @param array<string,mixed> $forum
     * @return array<int,array<string,mixed>>
     */
    public function ancestors(array $forum): array
    {
        $chain = [];
        $current = $forum;

        for ($depth = 0; $depth < 6; $depth++) {
            if ($current['parent_id'] === null) {
                break;
            }

            $parent = $this->forums->find((int) $current['parent_id']);

            if ($parent === null) {
                break;
            }

            array_unshift($chain, $parent);
            $current = $parent;
        }

        return $chain;
    }

    /**
     * Forums the viewer may start a topic in, for the "new topic" forum picker.
     *
     * @return array<int,array<string,mixed>>
     */
    public function postableForums(): array
    {
        $list = [];

        foreach ($this->forums->allForums(false) as $forum) {
            if ($this->access->canCreateTopicIn((int) $forum['id'])) {
                $list[] = $forum;
            }
        }

        return $list;
    }

    /**
     * Forums the viewer may move a topic into.
     *
     * @return array<int,array<string,mixed>>
     */
    public function moveTargets(): array
    {
        $list = [];

        foreach ($this->forums->allForums(false) as $forum) {
            if ($this->access->canModerateForum((int) $forum['id'])) {
                $list[] = $forum;
            }
        }

        return $list;
    }

    /** @return array<int,int> */
    public function readableIds(): array
    {
        return $this->access->readableForumIds();
    }
}
