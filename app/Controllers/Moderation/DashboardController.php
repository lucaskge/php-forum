<?php

declare(strict_types=1);

namespace App\Controllers\Moderation;

use App\Controllers\Controller;
use App\Repositories\ModerationRepository;
use App\Repositories\PostRepository;
use App\Repositories\ReportRepository;
use App\Repositories\TopicRepository;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $reports = new ReportRepository();
        $moderation = new ModerationRepository();

        $this->view->setLayout('layouts/moderation');
        $this->view->title('Moderation');
        $this->view->meta('robots', 'noindex');

        return $this->render('moderation/dashboard', [
            'pending_reports' => $reports->countByStatus('pending'),
            'resolved_reports' => $reports->countByStatus('resolved'),
            'dismissed_reports' => $reports->countByStatus('dismissed'),
            'recent_reports' => $reports->recent(6),
            'recent_actions' => $moderation->recentLog(10),
            'active_bans' => $moderation->activeBanCount(),
            'hidden_posts' => (new PostRepository())->paginateForAdmin(['status' => 'hidden'], 1, 5, '')->items(),
            'deleted_topics' => (new TopicRepository())->paginateForAdmin(['status' => 'deleted'], 1, 5, '')->items(),
            'moderated_forums' => count($this->access->moderatableForumIds()),
        ]);
    }

    public function queue(Request $request): Response
    {
        $posts = new PostRepository();
        $topics = new TopicRepository();

        $this->view->setLayout('layouts/moderation');
        $this->view->title('Content queue');
        $this->view->meta('robots', 'noindex');

        return $this->render('moderation/queue', [
            'hidden_posts' => $posts->paginateForAdmin(['status' => 'hidden'], $this->page($request), 20, Url::route('moderation.queue'))->items(),
            'deleted_posts' => $posts->paginateForAdmin(['status' => 'deleted'], 1, 20, '')->items(),
            'hidden_topics' => $topics->paginateForAdmin(['status' => 'hidden'], 1, 20, '')->items(),
            'deleted_topics' => $topics->paginateForAdmin(['status' => 'deleted'], 1, 20, '')->items(),
        ]);
    }
}
