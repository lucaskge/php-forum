<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\UserStatus;
use App\Controllers\Controller;
use App\Repositories\ModerationRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\AvatarService;
use App\Services\ModerationService;
use App\Support\Dates;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\Str;
use App\Support\Url;
use App\Support\Validator;

final class UserController extends Controller
{
    private UserRepository $users;

    private RoleRepository $roles;

    public function __construct()
    {
        parent::__construct();

        $this->users = new UserRepository();
        $this->roles = new RoleRepository();
    }

    public function index(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->input('q', ''),
            'role' => $request->int('role', 0),
            'status' => (string) $request->input('status', ''),
            'sort' => (string) $request->input('sort', 'recent'),
        ];

        $paginator = $this->users->paginate(
            $filters,
            $this->page($request),
            $this->settings->itemsPerPage(),
            Url::route('admin.users'),
        );

        $this->view->setLayout('layouts/admin');
        $this->view->title('Users');
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/users', [
            'paginator' => $paginator,
            'filters' => $filters,
            'roles' => $this->roles->all(),
        ]);
    }

    public function edit(Request $request): Response
    {
        $user = $this->findOrFail((int) $request->route('id'));

        $this->view->setLayout('layouts/admin');
        $this->view->title('Edit user · ' . (string) $user['username']);
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/user-edit', [
            'profile' => $user,
            'roles' => $this->roles->all(),
            'assigned_roles' => $this->roles->roleIdsForUser((int) $user['id']),
            'active_ban' => (new ModerationRepository())->activeBan((int) $user['id']),
            'timezones' => Dates::timezones(),
        ]);
    }

    public function update(Request $request): Response
    {
        $user = $this->findOrFail((int) $request->route('id'));
        $userId = (int) $user['id'];
        $redirect = Url::route('admin.user.edit', ['id' => $userId]);

        $username = (string) $request->input('username', (string) $user['username']);
        $email = (string) $request->input('email', (string) $user['email']);
        $title = (string) $request->input('title', '');
        $status = (string) $request->input('status', (string) $user['status']);
        $timezone = (string) $request->input('timezone', (string) $user['timezone']);

        $validator = Validator::make([
            'username' => $username,
            'email' => $email,
            'title' => $title,
            'status' => $status,
        ])
            ->required('username')->username('username')
            ->required('email')->email('email')
            ->maxLength('title', 64)
            ->in('status', UserStatus::values());

        if ($validator->passes()) {
            if ($this->users->usernameTaken($username, $userId)) {
                $validator->fail('username', 'That username is already taken.');
            }

            if ($this->users->emailTaken($email, $userId)) {
                $validator->fail('email', 'That e-mail address is already registered.');
            }
        }

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), $request->all(), $redirect);
        }

        if (!array_key_exists($timezone, Dates::timezones())) {
            $timezone = 'UTC';
        }

        $this->users->update($userId, [
            'username' => $username,
            'email' => $email,
            'title' => $title === '' ? null : $title,
            'status' => $status,
            'timezone' => $timezone,
            'bio' => $request->text('bio') === '' ? null : $request->text('bio'),
            'signature' => $request->text('signature') === '' ? null : $request->text('signature'),
            'location' => $request->input('location'),
            'website' => $request->input('website'),
            'reputation' => $request->int('reputation', (int) $user['reputation']),
        ]);

        // Role assignment is a separate permission from editing a profile.
        if ($this->access->can('user.role.manage') && $request->has('roles')) {
            $roleIds = array_values(array_filter(array_map('intval', $request->array('roles'))));

            if ($userId === $this->auth->id() && !$this->stillAdministrator($roleIds)) {
                Flash::warning('Your own administrator role was kept — you cannot lock yourself out.');
                $roleIds[] = (int) ($user['primary_role_id'] ?? 0);
            }

            $this->roles->syncUserRoles($userId, $roleIds);

            $primary = $request->int('primary_role_id', (int) ($user['primary_role_id'] ?? 0));

            if ($primary > 0 && in_array($primary, $roleIds, true)) {
                $this->users->update($userId, ['primary_role_id' => $primary]);
            }

            $this->access->flush();
        }

        (new ModerationService())->record(
            $this->userId(),
            'admin.user.update',
            'user',
            $userId,
            sprintf('Updated the account of %s', $username),
            null,
            $userId,
            [],
            $request->ip(),
        );

        Flash::success('User updated.');

        return $this->redirect($redirect);
    }

    public function resetPassword(Request $request): Response
    {
        $user = $this->findOrFail((int) $request->route('id'));
        $password = Str::random(6);

        $this->auth->changePassword((int) $user['id'], $password);

        (new ModerationService())->record(
            $this->userId(),
            'admin.user.password',
            'user',
            (int) $user['id'],
            sprintf('Reset the password of %s', (string) $user['username']),
            null,
            (int) $user['id'],
            [],
            $request->ip(),
        );

        Logger::security('Administrator reset a password', ['target' => $user['id'], 'by' => $this->userId()]);

        Flash::success(sprintf(
            'New password for %s: %s — copy it now, it is not stored in clear text.',
            (string) $user['username'],
            $password,
        ));

        return $this->redirect(Url::route('admin.user.edit', ['id' => (int) $user['id']]));
    }

    public function removeAvatar(Request $request): Response
    {
        $user = $this->findOrFail((int) $request->route('id'));

        (new AvatarService())->remove($user['avatar_path'] === null ? null : (string) $user['avatar_path']);
        $this->users->update((int) $user['id'], ['avatar_path' => null]);

        Flash::info('Avatar removed.');

        return $this->redirect(Url::route('admin.user.edit', ['id' => (int) $user['id']]));
    }

    public function deleteForm(Request $request): Response
    {
        $user = $this->findOrFail((int) $request->route('id'));

        $this->view->setLayout('layouts/admin');
        $this->view->title('Delete user');

        return $this->render('admin/user-delete', ['profile' => $user]);
    }

    public function destroy(Request $request): Response
    {
        $user = $this->findOrFail((int) $request->route('id'));

        if ((int) $user['id'] === $this->auth->id()) {
            Flash::error('You cannot delete your own account from here.');

            return $this->redirect(Url::route('admin.user.edit', ['id' => (int) $user['id']]));
        }

        (new AvatarService())->remove($user['avatar_path'] === null ? null : (string) $user['avatar_path']);

        (new ModerationService())->record(
            $this->userId(),
            'admin.user.delete',
            'user',
            (int) $user['id'],
            sprintf('Deleted the account %s', (string) $user['username']),
            $request->input('reason'),
            null,
            ['username' => $user['username'], 'email' => $user['email']],
            $request->ip(),
        );

        $this->users->delete((int) $user['id']);

        Flash::success('Account deleted. Their posts remain, attributed to a removed member.');

        return $this->redirect(Url::route('admin.users'));
    }

    public function bans(Request $request): Response
    {
        $filter = (string) $request->input('filter', 'active');

        if (!in_array($filter, ['active', 'expired', 'all'], true)) {
            $filter = 'active';
        }

        $paginator = (new ModerationRepository())->paginateBans(
            $filter,
            $this->page($request),
            $this->settings->itemsPerPage(),
            Url::route('admin.bans'),
        );

        $this->view->setLayout('layouts/admin');
        $this->view->title('Bans');
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/bans', ['paginator' => $paginator, 'filter' => $filter]);
    }

    public function liftBan(Request $request): Response
    {
        $banId = (int) $request->route('id');
        $moderation = new ModerationRepository();

        $moderation->lift($banId, $this->userId());

        Flash::success('Ban lifted.');

        return $this->back($request, Url::route('admin.bans'));
    }

    /** @param array<int,int> $roleIds */
    private function stillAdministrator(array $roleIds): bool
    {
        foreach ($roleIds as $roleId) {
            if (in_array('*', $this->roles->permissionSlugsForRole($roleId), true)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string,mixed> */
    private function findOrFail(int $id): array
    {
        $user = $this->users->find($id);

        if ($user === null) {
            throw HttpException::notFound('No such user.');
        }

        return $user;
    }
}
