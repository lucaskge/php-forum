<?php

declare(strict_types=1);

namespace App\Controllers\Moderation;

use App\Controllers\Controller;
use App\Policies\UserPolicy;
use App\Repositories\ModerationRepository;
use App\Repositories\PostRepository;
use App\Repositories\ReportRepository;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Services\ModerationService;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use App\Support\Validator;

final class UserController extends Controller
{
    private UserRepository $users;

    private ModerationRepository $moderation;

    private ModerationService $service;

    private UserPolicy $policy;

    public function __construct()
    {
        parent::__construct();

        $this->users = new UserRepository();
        $this->moderation = new ModerationRepository();
        $this->service = new ModerationService();
        $this->policy = new UserPolicy();
    }

    public function index(Request $request): Response
    {
        $paginator = $this->users->paginate(
            [
                'search' => (string) $request->input('q', ''),
                'status' => (string) $request->input('status', ''),
                'sort' => (string) $request->input('sort', 'recent'),
            ],
            $this->page($request),
            $this->settings->itemsPerPage(),
            Url::route('moderation.users'),
        );

        $this->view->setLayout('layouts/moderation');
        $this->view->title('Members');
        $this->view->meta('robots', 'noindex');

        return $this->render('moderation/users', [
            'paginator' => $paginator,
            'search' => (string) $request->input('q', ''),
            'status' => (string) $request->input('status', ''),
            'sort' => (string) $request->input('sort', 'recent'),
        ]);
    }

    public function show(Request $request): Response
    {
        $user = $this->findOrFail((string) $request->route('username'));
        $userId = (int) $user['id'];

        $this->view->setLayout('layouts/moderation');
        $this->view->title('Member · ' . (string) $user['username']);
        $this->view->meta('robots', 'noindex');

        return $this->render('moderation/user-show', [
            'profile' => $user,
            'active_ban' => $this->moderation->activeBan($userId),
            'bans' => $this->moderation->bansForUser($userId),
            'warnings' => $this->moderation->warningsForUser($userId),
            'notes' => $this->moderation->notesForUser($userId),
            'log' => $this->moderation->logForUser($userId, 25),
            'reports' => (new ReportRepository())->forUser($userId, 10),
            'recent_posts' => (new PostRepository())->paginateForAdmin(['author' => (string) $user['username']], 1, 10, '')->items(),
            'is_online' => (new SessionRepository())->isOnline($userId, $this->settings->onlineWindowSeconds()),
            'can_warn' => $this->policy->warn($user),
            'can_suspend' => $this->policy->suspend($user),
            'can_ban' => $this->policy->ban($user),
            'warning_points' => $this->moderation->activeWarningPoints($userId),
        ]);
    }

    public function warn(Request $request): Response
    {
        $user = $this->findOrFail((string) $request->route('username'));

        if (!$this->policy->warn($user)) {
            throw HttpException::forbidden('You cannot warn this member.');
        }

        $reason = (string) $request->input('reason', '');
        $details = $request->text('details');
        $points = $request->int('points', 1);
        $expires = $request->int('expires_days', 90);

        $validator = Validator::make(['reason' => $reason])
            ->required('reason')
            ->between('reason', 3, 255);

        if ($validator->fails()) {
            Flash::error((string) $validator->firstError());

            return $this->redirectToUser($user);
        }

        $this->service->warn(
            (int) $user['id'],
            $this->userId(),
            $reason,
            $details === '' ? null : $details,
            $points,
            $expires > 0 ? $expires : null,
            $request->ip(),
        );

        Flash::success(sprintf('%s has been warned.', (string) $user['username']));

        return $this->redirectToUser($user);
    }

    public function suspend(Request $request): Response
    {
        $user = $this->findOrFail((string) $request->route('username'));

        if (!$this->policy->suspend($user)) {
            throw HttpException::forbidden('You cannot suspend this member.');
        }

        $reason = (string) $request->input('reason', '');
        $days = $request->int('days', 7);
        $note = $request->text('note');

        $validator = Validator::make(['reason' => $reason])->required('reason')->between('reason', 3, 255);

        if ($validator->fails() || $days < 1) {
            Flash::error($validator->fails() ? (string) $validator->firstError() : 'A suspension lasts at least one day.');

            return $this->redirectToUser($user);
        }

        $this->service->suspend(
            (int) $user['id'],
            $this->userId(),
            $reason,
            $note === '' ? null : $note,
            min(3650, $days),
            $request->ip(),
        );

        Flash::success(sprintf('%s is suspended for %d day(s).', (string) $user['username'], $days));

        return $this->redirectToUser($user);
    }

    public function ban(Request $request): Response
    {
        $user = $this->findOrFail((string) $request->route('username'));

        if (!$this->policy->ban($user)) {
            throw HttpException::forbidden('You cannot ban this member.');
        }

        $reason = (string) $request->input('reason', '');
        $note = $request->text('note');

        $validator = Validator::make(['reason' => $reason])->required('reason')->between('reason', 3, 255);

        if ($validator->fails()) {
            Flash::error((string) $validator->firstError());

            return $this->redirectToUser($user);
        }

        $this->service->ban((int) $user['id'], $this->userId(), $reason, $note === '' ? null : $note, $request->ip());

        Flash::success(sprintf('%s is banned.', (string) $user['username']));

        return $this->redirectToUser($user);
    }

    public function lift(Request $request): Response
    {
        $user = $this->findOrFail((string) $request->route('username'));

        if (!$this->policy->ban($user) && !$this->policy->suspend($user)) {
            throw HttpException::forbidden('You cannot change restrictions on this member.');
        }

        $this->service->lift((int) $user['id'], $this->userId(), $request->ip());

        Flash::success(sprintf('Restrictions on %s were lifted.', (string) $user['username']));

        return $this->redirectToUser($user);
    }

    public function addNote(Request $request): Response
    {
        $user = $this->findOrFail((string) $request->route('username'));
        $note = $request->text('note');

        if ($note === '') {
            Flash::error('Write something before saving the note.');

            return $this->redirectToUser($user);
        }

        $this->moderation->addNote([
            'user_id' => (int) $user['id'],
            'author_id' => $this->userId(),
            'note' => mb_substr($note, 0, 5000),
        ]);

        Flash::success('Internal note saved.');

        return $this->redirectToUser($user);
    }

    public function deleteNote(Request $request): Response
    {
        $user = $this->findOrFail((string) $request->route('username'));
        $this->moderation->deleteNote((int) $request->route('note'));

        Flash::info('Note removed.');

        return $this->redirectToUser($user);
    }

    /** @return array<string,mixed> */
    private function findOrFail(string $username): array
    {
        $user = $this->users->findByUsername($username);

        if ($user === null) {
            throw HttpException::notFound('No member is registered under that name.');
        }

        return $user;
    }

    /** @param array<string,mixed> $user */
    private function redirectToUser(array $user): Response
    {
        return $this->redirect(Url::route('moderation.user', ['username' => (string) $user['username']]));
    }
}
