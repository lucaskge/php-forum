<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Policies\UserPolicy;
use App\Repositories\ModerationRepository;
use App\Repositories\PostRepository;
use App\Repositories\SessionRepository;
use App\Repositories\TopicRepository;
use App\Repositories\ReportRepository;
use App\Repositories\UserRepository;
use App\Services\ReportService;
use App\Support\Flash;
use App\Support\Validator;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Str;
use App\Support\Url;

final class UserController extends Controller
{
    private UserRepository $users;

    public function __construct()
    {
        parent::__construct();

        $this->users = new UserRepository();
    }

    public function profile(Request $request): Response
    {
        $user = $this->findUserOrFail((string) $request->route('username'));
        $policy = new UserPolicy();

        if (!$policy->viewProfile($user)) {
            throw HttpException::notFound('No such member.');
        }

        $readable = $this->access->readableForumIds();
        $topics = new TopicRepository();
        $posts = new PostRepository();
        $moderation = new ModerationRepository();

        $recentTopics = $topics->paginateForUser((int) $user['id'], $readable, 1, 5, '')->items();
        $recentPosts = $posts->paginateForUser((int) $user['id'], $readable, 1, 5, '')->items();

        $this->view->title((string) $user['username']);
        $this->view->meta('description', Str::limit(
            (string) ($user['bio'] ?? '') !== ''
                ? (string) $user['bio']
                : sprintf('%s — member profile.', (string) $user['username']),
            160,
        ));
        $this->view->canonical(Url::route('user.profile', ['username' => (string) $user['username']]));
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => 'Members', 'url' => Url::route('members')],
            ['label' => (string) $user['username']],
        ]);

        return $this->render('user/profile', [
            'profile' => $user,
            'roles' => (new \App\Repositories\RoleRepository())->rolesForUser((int) $user['id']),
            'recent_topics' => $recentTopics,
            'recent_posts' => $recentPosts,
            'is_online' => (new SessionRepository())->isOnline((int) $user['id'], $this->settings->onlineWindowSeconds()),
            'can_message' => $policy->message($user),
            'can_report' => $policy->report($user),
            'can_moderate' => $this->access->canModerateAnything(),
            'active_ban' => $this->access->canModerateAnything() ? $moderation->activeBan((int) $user['id']) : null,
            'warning_count' => $this->access->canModerateAnything() ? count($moderation->warningsForUser((int) $user['id'])) : 0,
        ]);
    }

    public function topics(Request $request): Response
    {
        $user = $this->findUserOrFail((string) $request->route('username'));
        $page = $this->page($request);
        $baseUrl = Url::route('user.topics', ['username' => (string) $user['username']]);

        $paginator = (new TopicRepository())->paginateForUser(
            (int) $user['id'],
            $this->access->readableForumIds(),
            $page,
            $this->settings->itemsPerPage(),
            $baseUrl,
        );

        $this->view->title((string) $user['username'] . ' · topics');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => (string) $user['username'], 'url' => Url::route('user.profile', ['username' => (string) $user['username']])],
            ['label' => 'Topics'],
        ]);

        return $this->render('user/topics', ['profile' => $user, 'paginator' => $paginator]);
    }

    public function posts(Request $request): Response
    {
        $user = $this->findUserOrFail((string) $request->route('username'));
        $page = $this->page($request);
        $baseUrl = Url::route('user.posts', ['username' => (string) $user['username']]);

        $paginator = (new PostRepository())->paginateForUser(
            (int) $user['id'],
            $this->access->readableForumIds(),
            $page,
            $this->settings->itemsPerPage(),
            $baseUrl,
        );

        $this->view->title((string) $user['username'] . ' · posts');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => (string) $user['username'], 'url' => Url::route('user.profile', ['username' => (string) $user['username']])],
            ['label' => 'Posts'],
        ]);

        return $this->render('user/posts', ['profile' => $user, 'paginator' => $paginator]);
    }

    public function activity(Request $request): Response
    {
        $user = $this->findUserOrFail((string) $request->route('username'));
        $readable = $this->access->readableForumIds();

        $this->view->title((string) $user['username'] . ' · activity');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => (string) $user['username'], 'url' => Url::route('user.profile', ['username' => (string) $user['username']])],
            ['label' => 'Activity'],
        ]);

        return $this->render('user/activity', [
            'profile' => $user,
            'topics' => (new TopicRepository())->paginateForUser((int) $user['id'], $readable, 1, 15, '')->items(),
            'posts' => (new PostRepository())->paginateForUser((int) $user['id'], $readable, 1, 15, '')->items(),
        ]);
    }

    public function reportForm(Request $request): Response
    {
        $user = $this->findUserOrFail((string) $request->route('username'));

        if (!(new UserPolicy())->report($user)) {
            throw HttpException::forbidden('You cannot report this member.');
        }

        $this->view->title('Report ' . (string) $user['username']);
        $this->view->meta('robots', 'noindex');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => (string) $user['username'], 'url' => Url::route('user.profile', ['username' => (string) $user['username']])],
            ['label' => 'Report'],
        ]);

        return $this->render('user/report', [
            'profile' => $user,
            'reasons' => ReportService::REASONS,
        ]);
    }

    public function report(Request $request): Response
    {
        $user = $this->findUserOrFail((string) $request->route('username'));
        $policy = new UserPolicy();

        if (!$policy->report($user)) {
            throw HttpException::forbidden('You cannot report this member.');
        }

        $reports = new ReportRepository();
        $reporterId = $this->userId();
        $targetId = (int) $user['id'];

        if ($reports->alreadyReported($reporterId, 'user', $targetId)) {
            Flash::info('You already reported this member — the moderators are on it.');

            return $this->redirect(Url::route('user.profile', ['username' => (string) $user['username']]));
        }

        $reason = (string) $request->input('reason', '');
        $details = $request->text('details');
        $formUrl = Url::route('user.report', ['username' => (string) $user['username']]);

        $validator = Validator::make(['reason' => $reason, 'details' => $details])
            ->required('reason')
            ->in('reason', array_keys(ReportService::REASONS))
            ->label('details', 'What happened')
            ->required('details')
            ->between('details', 10, 2000);

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), ['reason' => $reason, 'details' => $details], $formUrl);
        }

        (new ReportService())->submit(
            $reporterId,
            'user',
            $targetId,
            $targetId,
            $reason,
            $details,
            $request->ip(),
        );

        Flash::success('Report submitted. Moderators will review it.');

        return $this->redirect(Url::route('user.profile', ['username' => (string) $user['username']]));
    }

    /** @return array<string,mixed> */
    private function findUserOrFail(string $username): array
    {
        $user = $this->users->findByUsername($username);

        if ($user === null) {
            throw HttpException::notFound('No member is registered under that name.');
        }

        return $user;
    }
}
