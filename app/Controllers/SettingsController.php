<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\TopicRepository;
use App\Repositories\UserRepository;
use App\Services\AvatarService;
use App\Support\Config;
use App\Support\Dates;
use App\Support\Flash;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use App\Support\Validator;

/**
 * "Account settings" for the signed-in member: profile, avatar, password,
 * preferences, subscriptions and bookmarks.
 */
final class SettingsController extends Controller
{
    private UserRepository $users;

    public function __construct()
    {
        parent::__construct();

        $this->users = new UserRepository();
    }

    public function profileForm(Request $request): Response
    {
        $this->view->title('Edit profile');
        $this->view->breadcrumbs($this->crumbs('Profile'));

        return $this->render('user/settings-profile', [
            'profile' => $this->user(),
            'signature_max' => $this->settings->int('signature_max_length', 400),
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        $user = $this->user();
        $formUrl = Url::route('settings.profile');

        $bio = $request->text('bio');
        $signature = $request->text('signature');
        $location = (string) $request->input('location', '');
        $website = (string) $request->input('website', '');
        $signatureMax = $this->settings->int('signature_max_length', 400);

        $validator = Validator::make([
            'bio' => $bio,
            'signature' => $signature,
            'location' => $location,
            'website' => $website,
        ])
            ->maxLength('bio', 2000)
            ->maxLength('signature', $signatureMax)
            ->maxLength('location', 64)
            ->maxLength('website', 190);

        if ($website !== '' && filter_var($website, FILTER_VALIDATE_URL) === false) {
            $validator->fail('website', 'Enter a full address including https://');
        }

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), [
                'bio' => $bio,
                'signature' => $signature,
                'location' => $location,
                'website' => $website,
            ], $formUrl);
        }

        $this->users->update((int) $user['id'], [
            'bio' => $bio === '' ? null : $bio,
            'signature' => $signature === '' ? null : $signature,
            'location' => $location === '' ? null : $location,
            'website' => $website === '' ? null : $website,
        ]);

        Flash::success('Profile updated.');

        return $this->redirect($formUrl);
    }

    public function avatarForm(Request $request): Response
    {
        $service = new AvatarService();

        $this->view->title('Avatar');
        $this->view->breadcrumbs($this->crumbs('Avatar'));

        return $this->render('user/settings-avatar', [
            'profile' => $this->user(),
            'formats' => $service->allowedFormats(),
            'max_kb' => $service->maxKilobytes(),
            'max_width' => (int) Config::get('uploads.avatars.max_width', 512),
            'max_height' => (int) Config::get('uploads.avatars.max_height', 512),
            'max_source_width' => (int) Config::get('uploads.avatars.max_source_width', 8000),
            'max_source_height' => (int) Config::get('uploads.avatars.max_source_height', 8000),
            'avatars_enabled' => $this->settings->bool('avatars_enabled', true),
            'uploads_available' => $service->available(),
        ]);
    }

    public function uploadAvatar(Request $request): Response
    {
        $user = $this->user();
        $formUrl = Url::route('settings.avatar');

        if (!$this->settings->bool('avatars_enabled', true)) {
            Flash::error('Avatar uploads are disabled on this board.');

            return $this->redirect($formUrl);
        }

        $this->requirePermission('avatar.upload', 'Your account may not upload an avatar.');

        $file = $request->file('avatar');

        if ($file === null) {
            Flash::error('Choose an image file first.');

            return $this->redirect($formUrl);
        }

        $service = new AvatarService();
        $result = $service->store($file, (int) $user['id']);

        if (!$result['ok']) {
            Flash::error((string) $result['message']);

            return $this->redirect($formUrl);
        }

        $service->remove($user['avatar_path'] === null ? null : (string) $user['avatar_path']);
        $this->users->update((int) $user['id'], ['avatar_path' => $result['path']]);

        Flash::success('Avatar updated.');

        return $this->redirect($formUrl);
    }

    public function deleteAvatar(Request $request): Response
    {
        $user = $this->user();

        (new AvatarService())->remove($user['avatar_path'] === null ? null : (string) $user['avatar_path']);
        $this->users->update((int) $user['id'], ['avatar_path' => null]);

        Flash::info('Avatar removed. The generated placeholder is back.');

        return $this->redirect(Url::route('settings.avatar'));
    }

    public function passwordForm(Request $request): Response
    {
        $this->view->title('Change password');
        $this->view->breadcrumbs($this->crumbs('Password'));

        return $this->render('user/settings-password', [
            'min_password_length' => (int) Config::get('security.password.min_length', 10),
        ]);
    }

    public function updatePassword(Request $request): Response
    {
        $user = $this->user();
        $input = $request->all();
        $formUrl = Url::route('settings.password');

        $current = (string) ($input['current_password'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirmation'] ?? '');

        $validator = Validator::make([
            'current_password' => $current,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ])
            ->label('current_password', 'Current password')
            ->required('current_password')
            ->required('password')->password('password')
            ->label('password_confirmation', 'Password confirmation')
            ->matches('password_confirmation', 'password');

        if ($validator->passes() && !$this->auth->verifyPassword((int) $user['id'], $current)) {
            $validator->fail('current_password', 'That is not your current password.');
        }

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), [], $formUrl);
        }

        $this->auth->changePassword((int) $user['id'], $password);

        Flash::success('Password changed.');

        return $this->redirect($formUrl);
    }

    public function accountForm(Request $request): Response
    {
        $this->view->title('Account');
        $this->view->breadcrumbs($this->crumbs('Account'));

        return $this->render('user/settings-account', ['profile' => $this->user()]);
    }

    public function updateAccount(Request $request): Response
    {
        $user = $this->user();
        $formUrl = Url::route('settings.account');
        $input = $request->all();

        $email = (string) $request->input('email', '');
        $password = (string) ($input['current_password'] ?? '');

        $validator = Validator::make(['email' => $email, 'current_password' => $password])
            ->required('email')->email('email')->maxLength('email', 190)
            ->label('current_password', 'Current password')
            ->required('current_password');

        if ($validator->passes()) {
            if (!$this->auth->verifyPassword((int) $user['id'], $password)) {
                $validator->fail('current_password', 'That is not your current password.');
            } elseif ($this->users->emailTaken($email, (int) $user['id'])) {
                $validator->fail('email', 'That address is already registered to another account.');
            }
        }

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), ['email' => $email], $formUrl);
        }

        $this->users->update((int) $user['id'], ['email' => $email]);

        Flash::success('E-mail address updated.');

        return $this->redirect($formUrl);
    }

    public function preferencesForm(Request $request): Response
    {
        $this->view->title('Preferences');
        $this->view->breadcrumbs($this->crumbs('Preferences'));

        return $this->render('user/settings-preferences', [
            'profile' => $this->user(),
            'timezones' => Dates::timezones(),
            'site_posts_per_page' => $this->settings->postsPerPage(),
        ]);
    }

    public function updatePreferences(Request $request): Response
    {
        $user = $this->user();
        $formUrl = Url::route('settings.preferences');

        $timezone = (string) $request->input('timezone', 'UTC');

        if (!array_key_exists($timezone, Dates::timezones())) {
            $timezone = 'UTC';
        }

        $postsPerPage = $request->int('posts_per_page', 0);
        $postsPerPage = $postsPerPage <= 0 ? 0 : max(5, min(100, $postsPerPage));

        $this->users->update((int) $user['id'], [
            'timezone' => $timezone,
            'posts_per_page' => $postsPerPage,
            'show_online' => $request->bool('show_online') ? 1 : 0,
            'notify_replies' => $request->bool('notify_replies') ? 1 : 0,
            'notify_mentions' => $request->bool('notify_mentions') ? 1 : 0,
            'notify_quotes' => $request->bool('notify_quotes') ? 1 : 0,
            'notify_messages' => $request->bool('notify_messages') ? 1 : 0,
        ]);

        Flash::success('Preferences saved.');

        return $this->redirect($formUrl);
    }

    public function subscriptions(Request $request): Response
    {
        $page = $this->page($request);
        $baseUrl = Url::route('settings.subscriptions');

        $paginator = (new TopicRepository())->paginateSubscriptions(
            $this->userId(),
            $this->access->readableForumIds(),
            $page,
            $this->settings->itemsPerPage(),
            $baseUrl,
        );

        $this->view->title('Subscriptions');
        $this->view->breadcrumbs($this->crumbs('Subscriptions'));

        return $this->render('user/settings-subscriptions', ['paginator' => $paginator]);
    }

    public function bookmarks(Request $request): Response
    {
        $page = $this->page($request);
        $baseUrl = Url::route('settings.bookmarks');

        $paginator = (new TopicRepository())->paginateBookmarks(
            $this->userId(),
            $this->access->readableForumIds(),
            $page,
            $this->settings->itemsPerPage(),
            $baseUrl,
        );

        $this->view->title('Bookmarks');
        $this->view->breadcrumbs($this->crumbs('Bookmarks'));

        return $this->render('user/settings-bookmarks', ['paginator' => $paginator]);
    }

    /** @return array<int,array{label:string,url?:string}> */
    private function crumbs(string $current): array
    {
        return [
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => 'Account settings', 'url' => Url::route('settings.profile')],
            ['label' => $current],
        ];
    }
}
