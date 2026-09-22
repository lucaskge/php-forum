<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Repositories\ChatRepository;
use App\Repositories\ForumRepository;
use App\Repositories\MessageRepository;
use App\Repositories\ModerationRepository;
use App\Repositories\PostRepository;
use App\Repositories\ReportRepository;
use App\Repositories\SessionRepository;
use App\Repositories\StatisticsRepository;
use App\Repositories\TopicRepository;
use App\Repositories\UserRepository;
use App\Support\Dates;
use App\Support\Request;
use App\Support\Response;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $users = new UserRepository();
        $topics = new TopicRepository();
        $posts = new PostRepository();
        $reports = new ReportRepository();
        $moderation = new ModerationRepository();
        $statistics = new StatisticsRepository();

        $dayAgo = Dates::now()->modify('-1 day')->format('Y-m-d H:i:s');
        $weekAgo = Dates::now()->modify('-7 days')->format('Y-m-d H:i:s');

        $this->view->setLayout('layouts/admin');
        $this->view->title('Dashboard');
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/dashboard', [
            'totals' => [
                'users' => $users->countAll(),
                'topics' => $topics->countAll(),
                'posts' => $posts->countAll(),
                'forums' => (new ForumRepository())->countForums(),
                'messages' => (new MessageRepository())->countAll(),
                'chat_messages' => (new ChatRepository())->messageCount(),
            ],
            'recent' => [
                'users_day' => $users->countSince($dayAgo),
                'users_week' => $users->countSince($weekAgo),
                'topics_day' => $topics->countSince($dayAgo),
                'topics_week' => $topics->countSince($weekAgo),
                'posts_day' => $posts->countSince($dayAgo),
                'posts_week' => $posts->countSince($weekAgo),
            ],
            'active_users' => (new SessionRepository())->onlineUsers($this->settings->onlineWindowSeconds()),
            'guest_count' => (new SessionRepository())->guestCount($this->settings->onlineWindowSeconds()),
            'pending_reports' => $reports->pendingCount(),
            'recent_reports' => $reports->recent(5),
            'recent_actions' => $moderation->recentLog(8),
            'newest_members' => $users->newest(6),
            'suspended' => $users->countByStatus('suspended'),
            'banned' => $users->countByStatus('banned'),
            'active_bans' => $moderation->activeBanCount(),
            'posts_per_day' => $statistics->postsPerDay(14),
            'busiest_forums' => $statistics->busiestForums(5),
            'system' => $this->systemSnapshot(),
        ]);
    }

    /** @return array<string,string> */
    private function systemSnapshot(): array
    {
        return [
            'php' => PHP_VERSION,
            'server' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'),
            'app_env' => (string) \App\Support\Config::get('app.env', 'production'),
            'debug' => \App\Support\Config::get('app.debug', false) ? 'on' : 'off',
            'theme' => $this->view->themes()->activeSlug(),
            'chat_transport' => $this->settings->string('chat_transport', 'http'),
        ];
    }
}
