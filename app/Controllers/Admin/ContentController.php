<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Repositories\ForumRepository;
use App\Repositories\PostRepository;
use App\Repositories\TopicRepository;
use App\Services\TopicService;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;

final class ContentController extends Controller
{
    public function topics(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->input('q', ''),
            'forum' => $request->int('forum', 0),
            'status' => (string) $request->input('status', ''),
        ];

        $paginator = (new TopicRepository())->paginateForAdmin(
            $filters,
            $this->page($request),
            $this->settings->itemsPerPage(),
            Url::route('admin.topics'),
        );

        $this->view->setLayout('layouts/admin');
        $this->view->title('Topics');
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/topics', [
            'paginator' => $paginator,
            'filters' => $filters,
            'forums' => (new ForumRepository())->allForums(false),
        ]);
    }

    public function posts(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->input('q', ''),
            'forum' => $request->int('forum', 0),
            'author' => (string) $request->input('author', ''),
            'status' => (string) $request->input('status', ''),
        ];

        $paginator = (new PostRepository())->paginateForAdmin(
            $filters,
            $this->page($request),
            $this->settings->itemsPerPage(),
            Url::route('admin.posts'),
        );

        $this->view->setLayout('layouts/admin');
        $this->view->title('Posts');
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/posts', [
            'paginator' => $paginator,
            'filters' => $filters,
            'forums' => (new ForumRepository())->allForums(false),
        ]);
    }

    /** Hard delete — the administrative counterpart to a moderator's soft delete. */
    public function purgeTopic(Request $request): Response
    {
        $topics = new TopicRepository();
        $topic = $topics->find((int) $request->route('id'));

        if ($topic === null) {
            throw HttpException::notFound('No such topic.');
        }

        $forumId = (int) $topic['forum_id'];
        $topics->delete((int) $topic['id']);
        (new ForumRepository())->refreshCounters($forumId);

        Flash::success('Topic permanently removed.');

        return $this->back($request, Url::route('admin.topics'));
    }

    public function restoreTopic(Request $request): Response
    {
        $topics = new TopicRepository();
        $topic = $topics->find((int) $request->route('id'));

        if ($topic === null) {
            throw HttpException::notFound('No such topic.');
        }

        (new TopicService())->restoreTopic($topic);

        Flash::success('Topic restored.');

        return $this->back($request, Url::route('admin.topics'));
    }

    public function purgePost(Request $request): Response
    {
        $posts = new PostRepository();
        $post = $posts->find((int) $request->route('id'));

        if ($post === null) {
            throw HttpException::notFound('No such post.');
        }

        $posts->delete((int) $post['id']);
        (new TopicRepository())->refreshCounters((int) $post['topic_id']);
        (new ForumRepository())->refreshCounters((int) $post['forum_id']);

        Flash::success('Post permanently removed.');

        return $this->back($request, Url::route('admin.posts'));
    }

    public function restorePost(Request $request): Response
    {
        $posts = new PostRepository();
        $post = $posts->find((int) $request->route('id'));

        if ($post === null) {
            throw HttpException::notFound('No such post.');
        }

        (new TopicService())->restorePost($post);

        Flash::success('Post restored.');

        return $this->back($request, Url::route('admin.posts'));
    }
}
