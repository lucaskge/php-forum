<?php

declare(strict_types=1);

namespace App\Controllers\Moderation;

use App\Controllers\Controller;
use App\Repositories\ModerationRepository;
use App\Repositories\UserRepository;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;

final class LogController extends Controller
{
    public function index(Request $request): Response
    {
        $moderation = new ModerationRepository();

        $filters = [
            'action' => (string) $request->input('action', ''),
            'moderator' => $request->int('moderator', 0),
            'search' => (string) $request->input('q', ''),
        ];

        $paginator = $moderation->paginateLog(
            $filters,
            $this->page($request),
            $this->settings->itemsPerPage(),
            Url::route('moderation.log'),
        );

        $this->view->setLayout('layouts/moderation');
        $this->view->title('Moderation log');
        $this->view->meta('robots', 'noindex');

        return $this->render('moderation/log', [
            'paginator' => $paginator,
            'filters' => $filters,
            'actions' => $moderation->actionTypes(),
            'staff' => (new UserRepository())->staff(),
        ]);
    }
}
