<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Repositories\RoleRepository;
use App\Services\ModerationService;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Str;
use App\Support\Url;
use App\Support\Validator;

final class RoleController extends Controller
{
    private RoleRepository $roles;

    public function __construct()
    {
        parent::__construct();

        $this->roles = new RoleRepository();
    }

    public function index(Request $request): Response
    {
        $this->view->setLayout('layouts/admin');
        $this->view->title('Roles');
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/roles', [
            'roles' => $this->roles->all(),
            'permission_count' => count($this->roles->allPermissions()),
        ]);
    }

    public function createForm(Request $request): Response
    {
        $this->view->setLayout('layouts/admin');
        $this->view->title('New role');

        return $this->render('admin/role-edit', [
            'role' => null,
            'permissions' => $this->roles->permissionsGrouped(),
            'granted' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $name = (string) $request->input('name', '');
        $slug = (string) $request->input('slug', '') !== '' ? (string) $request->input('slug') : Str::slug($name);

        $validator = Validator::make(['name' => $name, 'slug' => $slug])
            ->required('name')->between('name', 2, 64)
            ->required('slug')->slug('slug')->maxLength('slug', 48);

        if ($validator->passes() && $this->roles->findBySlug($slug) !== null) {
            $validator->fail('slug', 'A role with that identifier already exists.');
        }

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), $request->all(), Url::route('admin.role.create'));
        }

        $roleId = $this->roles->create([
            'slug' => $slug,
            'name' => $name,
            'description' => $request->input('description'),
            'colour' => $this->colour((string) $request->input('colour', '#8fa3b8')),
            'priority' => $request->int('priority', 10),
            'is_default' => 0,
            'is_guest' => 0,
            'is_staff' => $request->bool('is_staff') ? 1 : 0,
            'is_system' => 0,
        ]);

        $this->roles->syncPermissions($roleId, array_map('intval', $request->array('permissions')));

        Flash::success('Role created.');

        return $this->redirect(Url::route('admin.role.edit', ['id' => $roleId]));
    }

    public function edit(Request $request): Response
    {
        $role = $this->findOrFail((int) $request->route('id'));

        $this->view->setLayout('layouts/admin');
        $this->view->title('Role · ' . (string) $role['name']);
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/role-edit', [
            'role' => $role,
            'permissions' => $this->roles->permissionsGrouped(),
            'granted' => $this->roles->permissionSlugsForRole((int) $role['id']),
            'member_count' => $this->roles->memberCount((int) $role['id']),
        ]);
    }

    public function update(Request $request): Response
    {
        $role = $this->findOrFail((int) $request->route('id'));
        $redirect = Url::route('admin.role.edit', ['id' => (int) $role['id']]);

        $name = (string) $request->input('name', (string) $role['name']);

        $validator = Validator::make(['name' => $name])->required('name')->between('name', 2, 64);

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), $request->all(), $redirect);
        }

        $data = [
            'name' => $name,
            'description' => $request->input('description'),
            'colour' => $this->colour((string) $request->input('colour', (string) $role['colour'])),
            'priority' => $request->int('priority', (int) $role['priority']),
        ];

        // System roles keep their structural flags so the board stays coherent.
        if ((int) $role['is_system'] === 0) {
            $data['is_staff'] = $request->bool('is_staff') ? 1 : 0;
        }

        $this->roles->update((int) $role['id'], $data);

        $permissionIds = array_map('intval', $request->array('permissions'));

        // Refuse to strip the wildcard from the role that holds it, unless
        // another role still grants it.
        if ($this->wouldOrphanAdministrators($role, $permissionIds)) {
            Flash::warning('The administrator permission was kept: at least one role must retain full access.');
            $permissionIds[] = $this->wildcardPermissionId();
        }

        $this->roles->syncPermissions((int) $role['id'], $permissionIds);
        $this->access->flush();

        (new ModerationService())->record(
            $this->userId(),
            'admin.role.update',
            'role',
            (int) $role['id'],
            sprintf('Updated the role “%s”', $name),
            null,
            null,
            ['permissions' => count($permissionIds)],
            $request->ip(),
        );

        Flash::success('Role saved.');

        return $this->redirect($redirect);
    }

    public function destroy(Request $request): Response
    {
        $role = $this->findOrFail((int) $request->route('id'));

        if ((int) $role['is_system'] === 1) {
            Flash::error('System roles cannot be deleted.');

            return $this->redirect(Url::route('admin.roles'));
        }

        if ($this->roles->memberCount((int) $role['id']) > 0) {
            Flash::error('Move the members out of this role before deleting it.');

            return $this->redirect(Url::route('admin.role.edit', ['id' => (int) $role['id']]));
        }

        $this->roles->delete((int) $role['id']);
        $this->access->flush();

        Flash::success('Role deleted.');

        return $this->redirect(Url::route('admin.roles'));
    }

    public function permissions(Request $request): Response
    {
        $this->view->setLayout('layouts/admin');
        $this->view->title('Permissions');
        $this->view->meta('robots', 'noindex');

        $roles = $this->roles->all();
        $matrix = [];

        foreach ($roles as $role) {
            $matrix[(int) $role['id']] = array_flip($this->roles->permissionSlugsForRole((int) $role['id']));
        }

        return $this->render('admin/permissions', [
            'roles' => $roles,
            'permissions' => $this->roles->permissionsGrouped(),
            'matrix' => $matrix,
        ]);
    }

    private function colour(string $value): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : '#8fa3b8';
    }

    /**
     * @param array<string,mixed> $role
     * @param array<int,int> $permissionIds
     */
    private function wouldOrphanAdministrators(array $role, array $permissionIds): bool
    {
        $wildcardId = $this->wildcardPermissionId();

        if ($wildcardId === 0) {
            return false;
        }

        $hasWildcard = in_array('*', $this->roles->permissionSlugsForRole((int) $role['id']), true);

        if (!$hasWildcard || in_array($wildcardId, $permissionIds, true)) {
            return false;
        }

        foreach ($this->roles->all() as $other) {
            if ((int) $other['id'] === (int) $role['id']) {
                continue;
            }

            if (in_array('*', $this->roles->permissionSlugsForRole((int) $other['id']), true)) {
                return false;
            }
        }

        return true;
    }

    private function wildcardPermissionId(): int
    {
        foreach ($this->roles->allPermissions() as $permission) {
            if ((string) $permission['slug'] === '*') {
                return (int) $permission['id'];
            }
        }

        return 0;
    }

    /** @return array<string,mixed> */
    private function findOrFail(int $id): array
    {
        $role = $this->roles->find($id);

        if ($role === null) {
            throw HttpException::notFound('No such role.');
        }

        return $role;
    }
}
