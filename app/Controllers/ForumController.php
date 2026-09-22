<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ForumRepository;
use App\Repositories\TopicRepository;
use App\Services\ForumService;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Str;
use App\Support\Url;

final class ForumController extends Controller
{
    public function show(Request $request): Response
    {
        $slug = (string) $request->route('slug');
        $forums = new ForumRepository();
        $forum = $forums->findBySlug($slug);

        if ($forum === null) {
            throw HttpException::notFound('There is no forum at this address.');
        }

        $forumId = (int) $forum['id'];

        if (!$this->access->canViewForum($forumId)) {
            throw HttpException::forbidden('This forum is not visible to your account.');
        }

        if (!$this->access->canReadForum($forumId)) {
            // Visible but unreadable: show the row, explain the wall.
            $this->view->title((string) $forum['name']);

            return $this->render('forum/locked', ['forum' => $forum], 403);
        }

        $forumService = new ForumService();
        $ancestors = $forumService->ancestors($forum);
        $children = $forums->children($forumId);

        // Subforum topics are listed alongside the parent's own, as members
        // expect from a traditional board.
        $listedForumIds = [$forumId];

        foreach ($children as $child) {
            if ($this->access->canReadForum((int) $child['id'])) {
                $listedForumIds[] = (int) $child['id'];
            }
        }

        $canModerate = $this->access->canModerateForum($forumId);
        $page = $this->page($request);
        $baseUrl = Url::route('forum.show', ['slug' => $slug]);

        $paginator = (new TopicRepository())->paginateForForums(
            $listedForumIds,
            $page,
            $this->settings->topicsPerPage(),
            $baseUrl,
            $canModerate,
            $canModerate,
        );

        $crumbs = [['label' => 'Board index', 'url' => Url::route('home')]];

        foreach ($ancestors as $ancestor) {
            $crumbs[] = ['label' => (string) $ancestor['name'], 'url' => Url::route('forum.show', ['slug' => (string) $ancestor['slug']])];
        }

        $crumbs[] = ['label' => (string) $forum['name']];

        $this->view->title((string) $forum['name']);
        $this->view->meta('description', Str::limit((string) ($forum['description'] ?? $forum['name']), 160));
        $this->view->canonical(Url::withQuery($baseUrl, $page > 1 ? ['page' => $page] : []));
        $this->view->breadcrumbs($crumbs);

        return $this->render('forum/show', [
            'forum' => $forum,
            'children' => array_values(array_filter(
                $children,
                fn (array $child): bool => $this->access->canViewForum((int) $child['id']),
            )),
            'paginator' => $paginator,
            'can_create_topic' => $this->access->canCreateTopicIn($forumId),
            'can_moderate' => $canModerate,
            'ancestors' => $ancestors,
        ]);
    }

    /** Legacy-style deep link: /forum/{forum}/topic/{slug}. */
    public function topicRedirect(Request $request): Response
    {
        return $this->redirect(Url::route('topic.show', ['slug' => (string) $request->route('topic')]), 301);
    }

    public function markRead(Request $request): Response
    {
        // Read state is derived from last_active_at; this endpoint exists so the
        // "mark forums read" control is not a dead link.
        $userId = $this->auth->id();

        if ($userId !== null) {
            (new \App\Repositories\UserRepository())->touchActivity($userId, $request->ip());
        }

        \App\Support\Flash::success('All forums marked as read.');

        return $this->back($request, Url::route('home'));
    }
}
