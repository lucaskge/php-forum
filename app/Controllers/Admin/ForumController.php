<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Repositories\ForumRepository;
use App\Repositories\RoleRepository;
use App\Services\ModerationService;
use App\Services\TopicService;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Str;
use App\Support\Url;
use App\Support\Validator;

final class ForumController extends Controller
{
    private ForumRepository $forums;

    public function __construct()
    {
        parent::__construct();

        $this->forums = new ForumRepository();
    }

    public function index(Request $request): Response
    {
        $this->view->setLayout('layouts/admin');
        $this->view->title('Forums');
        $this->view->meta('robots', 'noindex');

        $forums = $this->forums->allForums(false);
        $byCategory = [];

        foreach ($forums as $forum) {
            $byCategory[(int) $forum['category_id']][] = $forum;
        }

        return $this->render('admin/forums', [
            'categories' => $this->forums->allCategories(false),
            'forums_by_category' => $byCategory,
        ]);
    }

    // ------------------------------------------------------------------
    // Categories
    // ------------------------------------------------------------------

    public function categoryForm(Request $request): Response
    {
        $id = $request->int('id', 0);
        $category = $id > 0 ? $this->forums->findCategory($id) : null;

        if ($id > 0 && $category === null) {
            throw HttpException::notFound('No such category.');
        }

        $this->view->setLayout('layouts/admin');
        $this->view->title($category === null ? 'New category' : 'Edit category');

        return $this->render('admin/category-edit', ['category' => $category]);
    }

    public function saveCategory(Request $request): Response
    {
        $id = $request->int('id', 0);
        $existing = $id > 0 ? $this->forums->findCategory($id) : null;

        $name = (string) $request->input('name', '');
        $slug = (string) $request->input('slug', '') !== '' ? (string) $request->input('slug') : Str::slug($name);

        $validator = Validator::make(['name' => $name, 'slug' => $slug])
            ->required('name')->between('name', 2, 96)
            ->required('slug')->slug('slug')->maxLength('slug', 120);

        if ($validator->passes() && $this->forums->categorySlugTaken($slug, $id > 0 ? $id : null)) {
            $validator->fail('slug', 'That identifier is already in use.');
        }

        if ($validator->fails()) {
            return $this->withErrors(
                $validator->errors(),
                $request->all(),
                Url::route('admin.category.form', [], $id > 0 ? ['id' => $id] : []),
            );
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'description' => $request->input('description'),
            'position' => $request->int('position', 0),
            'is_visible' => $request->bool('is_visible') ? 1 : 0,
        ];

        if ($existing === null) {
            $this->forums->createCategory($data);
            Flash::success('Category created.');
        } else {
            $this->forums->updateCategory($id, $data);
            Flash::success('Category updated.');
        }

        return $this->redirect(Url::route('admin.forums'));
    }

    public function deleteCategory(Request $request): Response
    {
        $id = (int) $request->route('id');
        $category = $this->forums->findCategory($id);

        if ($category === null) {
            throw HttpException::notFound('No such category.');
        }

        $this->forums->deleteCategory($id);

        (new ModerationService())->record(
            $this->userId(),
            'admin.category.delete',
            'category',
            $id,
            sprintf('Deleted the category “%s” and everything inside it', (string) $category['name']),
            null,
            null,
            [],
            $request->ip(),
        );

        Flash::success('Category deleted.');

        return $this->redirect(Url::route('admin.forums'));
    }

    // ------------------------------------------------------------------
    // Forums
    // ------------------------------------------------------------------

    public function forumForm(Request $request): Response
    {
        $id = $request->int('id', 0);
        $forum = $id > 0 ? $this->forums->find($id) : null;

        if ($id > 0 && $forum === null) {
            throw HttpException::notFound('No such forum.');
        }

        $this->view->setLayout('layouts/admin');
        $this->view->title($forum === null ? 'New forum' : 'Edit forum');

        return $this->render('admin/forum-edit', [
            'forum' => $forum,
            'categories' => $this->forums->allCategories(false),
            'parents' => array_values(array_filter(
                $this->forums->allForums(false),
                static fn (array $candidate): bool => $forum === null || (int) $candidate['id'] !== (int) $forum['id'],
            )),
            'preset_category' => $request->int('category', 0),
        ]);
    }

    public function saveForum(Request $request): Response
    {
        $id = $request->int('id', 0);
        $existing = $id > 0 ? $this->forums->find($id) : null;

        $name = (string) $request->input('name', '');
        $slug = (string) $request->input('slug', '') !== '' ? (string) $request->input('slug') : Str::slug($name);
        $categoryId = $request->int('category_id', 0);
        $parentId = $request->int('parent_id', 0);

        $validator = Validator::make(['name' => $name, 'slug' => $slug, 'category_id' => $categoryId])
            ->required('name')->between('name', 2, 96)
            ->required('slug')->slug('slug')->maxLength('slug', 120)
            ->required('category_id');

        if ($validator->passes()) {
            if ($this->forums->slugTaken($slug, $id > 0 ? $id : null)) {
                $validator->fail('slug', 'That identifier is already in use.');
            }

            if ($this->forums->findCategory($categoryId) === null) {
                $validator->fail('category_id', 'Choose a category.');
            }

            if ($parentId > 0 && $id > 0 && in_array($parentId, $this->forums->descendantIds($id), true)) {
                $validator->fail('parent_id', 'A forum cannot be nested inside itself.');
            }
        }

        if ($validator->fails()) {
            return $this->withErrors(
                $validator->errors(),
                $request->all(),
                Url::route('admin.forum.form', [], $id > 0 ? ['id' => $id] : []),
            );
        }

        $data = [
            'category_id' => $categoryId,
            'parent_id' => $parentId > 0 ? $parentId : null,
            'name' => $name,
            'slug' => $slug,
            'description' => $request->input('description'),
            'icon' => mb_substr((string) $request->input('icon', '#'), 0, 16),
            'position' => $request->int('position', 0),
            'is_visible' => $request->bool('is_visible') ? 1 : 0,
            'is_locked' => $request->bool('is_locked') ? 1 : 0,
        ];

        if ($existing === null) {
            $forumId = $this->forums->create($data);
            $this->seedDefaultPermissions($forumId);
            Flash::success('Forum created. Review its permissions next.');

            return $this->redirect(Url::route('admin.forum.permissions', ['id' => $forumId]));
        }

        $this->forums->update($id, $data);
        $this->forums->refreshCounters($id);

        Flash::success('Forum updated.');

        return $this->redirect(Url::route('admin.forums'));
    }

    public function deleteForum(Request $request): Response
    {
        $id = (int) $request->route('id');
        $forum = $this->forums->find($id);

        if ($forum === null) {
            throw HttpException::notFound('No such forum.');
        }

        $this->forums->delete($id);

        (new ModerationService())->record(
            $this->userId(),
            'admin.forum.delete',
            'forum',
            $id,
            sprintf('Deleted the forum “%s” with its topics', (string) $forum['name']),
            null,
            null,
            [],
            $request->ip(),
        );

        Flash::success('Forum deleted.');

        return $this->redirect(Url::route('admin.forums'));
    }

    public function reorder(Request $request): Response
    {
        foreach ($request->all()['position'] ?? [] as $forumId => $position) {
            $this->forums->update((int) $forumId, ['position' => max(0, (int) $position)]);
        }

        foreach ($request->all()['category_position'] ?? [] as $categoryId => $position) {
            $this->forums->updateCategory((int) $categoryId, ['position' => max(0, (int) $position)]);
        }

        Flash::success('Display order saved.');

        return $this->redirect(Url::route('admin.forums'));
    }

    // ------------------------------------------------------------------
    // Per-forum permissions
    // ------------------------------------------------------------------

    public function permissionsForm(Request $request): Response
    {
        $forum = $this->findForumOrFail((int) $request->route('id'));
        $roles = (new RoleRepository())->all();

        $current = [];

        foreach ($this->forums->permissions((int) $forum['id']) as $row) {
            $current[(int) $row['role_id']] = $row;
        }

        $this->view->setLayout('layouts/admin');
        $this->view->title('Permissions · ' . (string) $forum['name']);
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/forum-permissions', [
            'forum' => $forum,
            'roles' => $roles,
            'current' => $current,
        ]);
    }

    public function savePermissions(Request $request): Response
    {
        $forum = $this->findForumOrFail((int) $request->route('id'));
        $roles = (new RoleRepository())->all();
        $input = $request->all();

        $rows = [];

        foreach ($roles as $role) {
            $roleId = (int) $role['id'];
            $flags = $input['perm'][$roleId] ?? [];

            if (!is_array($flags)) {
                $flags = [];
            }

            $rows[] = [
                'role_id' => $roleId,
                'can_view' => isset($flags['can_view']) ? 1 : 0,
                'can_read' => isset($flags['can_read']) ? 1 : 0,
                'can_create_topic' => isset($flags['can_create_topic']) ? 1 : 0,
                'can_reply' => isset($flags['can_reply']) ? 1 : 0,
                'can_moderate' => isset($flags['can_moderate']) ? 1 : 0,
            ];
        }

        $this->forums->syncPermissions((int) $forum['id'], $rows);
        $this->access->flush();

        (new ModerationService())->record(
            $this->userId(),
            'admin.forum.permissions',
            'forum',
            (int) $forum['id'],
            sprintf('Updated permissions for “%s”', (string) $forum['name']),
            null,
            null,
            [],
            $request->ip(),
        );

        Flash::success('Forum permissions saved.');

        return $this->redirect(Url::route('admin.forum.permissions', ['id' => (int) $forum['id']]));
    }

    public function recount(Request $request): Response
    {
        (new TopicService())->recountEverything();

        Flash::success('Counters recalculated across every forum.');

        return $this->redirect(Url::route('admin.forums'));
    }

    /**
     * A new forum starts with the same access its category peers grant, so it
     * is never silently invisible to everyone.
     */
    private function seedDefaultPermissions(int $forumId): void
    {
        $roles = (new RoleRepository())->all();
        $rows = [];

        foreach ($roles as $role) {
            $isGuest = (int) $role['is_guest'] === 1;
            $isStaff = (int) $role['is_staff'] === 1;

            $rows[] = [
                'role_id' => (int) $role['id'],
                'can_view' => 1,
                'can_read' => 1,
                'can_create_topic' => $isGuest ? 0 : 1,
                'can_reply' => $isGuest ? 0 : 1,
                'can_moderate' => $isStaff ? 1 : 0,
            ];
        }

        $this->forums->syncPermissions($forumId, $rows);
    }

    /** @return array<string,mixed> */
    private function findForumOrFail(int $id): array
    {
        $forum = $this->forums->find($id);

        if ($forum === null) {
            throw HttpException::notFound('No such forum.');
        }

        return $forum;
    }
}
