<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\PostRepository;
use App\Repositories\SessionRepository;
use App\Repositories\StatisticsRepository;
use App\Repositories\TopicRepository;
use App\Repositories\UserRepository;
use App\Services\ForumService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $forumService = new ForumService();
        $topics = new TopicRepository();
        $posts = new PostRepository();
        $sessions = new SessionRepository();
        $statistics = new StatisticsRepository();
        $users = new UserRepository();

        $readableIds = $forumService->readableIds();
        $window = $this->settings->onlineWindowSeconds();

        $this->view->title('');
        $this->view->meta('description', $this->settings->string(
            'site_description',
            'A technical discussion board.',
        ));
        $this->view->canonical(Url::route('home'));

        return $this->render('forum/index', [
            'tree' => $forumService->tree(),
            'latest_topics' => $topics->latest($readableIds, 8),
            'latest_posts' => $posts->latest($readableIds, 8),
            'statistics' => $statistics->boardSummary(),
            'online_users' => $sessions->onlineUsers($window),
            'guest_count' => $sessions->guestCount($window),
            'bot_count' => $sessions->botCount($window),
            'online_window_minutes' => intdiv($window, 60),
            'staff' => $users->staff(),
            'newest_members' => $users->newest(5),
            'announcement' => $this->settings->string('announcement', ''),
        ]);
    }

    public function members(Request $request): Response
    {
        $users = new UserRepository();
        $page = $this->page($request);

        $paginator = $users->paginate(
            [
                'search' => (string) $request->input('q', ''),
                'sort' => (string) $request->input('sort', 'recent'),
            ],
            $page,
            $this->settings->itemsPerPage(),
            Url::route('members'),
        );

        $this->view->title('Members');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => 'Members'],
        ]);

        return $this->render('user/members', [
            'paginator' => $paginator,
            'search' => (string) $request->input('q', ''),
            'sort' => (string) $request->input('sort', 'recent'),
            'staff' => $users->staff(),
            'top_posters' => $users->topPosters(10),
        ]);
    }

    public function online(Request $request): Response
    {
        $sessions = new SessionRepository();
        $window = $this->settings->onlineWindowSeconds();

        $this->view->title('Who is online');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => 'Who is online'],
        ]);

        return $this->render('user/online', [
            'online_users' => $sessions->onlineUsers($window),
            'guest_count' => $sessions->guestCount($window),
            'bot_count' => $sessions->botCount($window),
            'window_minutes' => intdiv($window, 60),
        ]);
    }

    public function rules(Request $request): Response
    {
        $this->view->title('Board rules');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => 'Rules'],
        ]);

        return $this->render('static/rules', [
            'rules' => $this->settings->string('board_rules', ''),
        ]);
    }

    public function help(Request $request): Response
    {
        $this->view->title('Formatting and help');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => 'Help'],
        ]);

        return $this->render('static/help');
    }

    public function maintenance(Request $request): Response
    {
        $this->view->setLayout('layouts/minimal');
        $this->view->title('Maintenance');

        return $this->render('static/maintenance', [
            'message' => $this->settings->string('maintenance_message', 'The board is temporarily offline for maintenance.'),
        ], 503);
    }

    public function restricted(Request $request): Response
    {
        $restriction = $this->auth->activeRestriction();

        if ($restriction === null) {
            return $this->redirect(Url::route('home'));
        }

        $this->view->setLayout('layouts/minimal');
        $this->view->title('Account restricted');

        return $this->render('static/restricted', ['restriction' => $restriction], 403);
    }
}
