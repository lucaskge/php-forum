<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Policies\PostPolicy;
use App\Policies\TopicPolicy;
use App\Repositories\ForumRepository;
use App\Repositories\PostRepository;
use App\Repositories\TopicRepository;
use App\Services\ForumService;
use App\Services\TopicService;
use App\Support\ContentFormatter;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\Str;
use App\Support\Url;
use App\Support\Validator;

final class TopicController extends Controller
{
    private TopicRepository $topics;

    private PostRepository $posts;

    private ForumRepository $forums;

    private TopicService $service;

    private TopicPolicy $policy;

    public function __construct()
    {
        parent::__construct();

        $this->topics = new TopicRepository();
        $this->posts = new PostRepository();
        $this->forums = new ForumRepository();
        $this->service = new TopicService();
        $this->policy = new TopicPolicy();
    }

    public function show(Request $request): Response
    {
        $topic = $this->findTopicOrFail((string) $request->route('slug'));

        if (!$this->policy->view($topic)) {
            throw HttpException::forbidden('This topic is not available to your account.');
        }

        $canModerate = $this->policy->moderate($topic);
        $page = $this->page($request);
        $baseUrl = Url::route('topic.show', ['slug' => (string) $topic['slug']]);
        $perPage = $this->postsPerPage();

        $paginator = $this->posts->paginateForTopic((int) $topic['id'], $page, $perPage, $baseUrl, $canModerate);

        if ($paginator->items() === [] && $page > 1) {
            return $this->redirect($baseUrl);
        }

        // One view per session per topic keeps the counter honest enough.
        $viewed = Session::get('__viewed_topics', []);

        if (!in_array((int) $topic['id'], is_array($viewed) ? $viewed : [], true)) {
            $this->topics->incrementViews((int) $topic['id']);
            $viewed[] = (int) $topic['id'];
            Session::put('__viewed_topics', array_slice($viewed, -100));
        }

        $forum = $this->forums->find((int) $topic['forum_id']);
        $ancestors = $forum === null ? [] : (new ForumService())->ancestors($forum);

        $crumbs = [['label' => 'Board index', 'url' => Url::route('home')]];

        foreach ($ancestors as $ancestor) {
            $crumbs[] = ['label' => (string) $ancestor['name'], 'url' => Url::route('forum.show', ['slug' => (string) $ancestor['slug']])];
        }

        if ($forum !== null) {
            $crumbs[] = ['label' => (string) $forum['name'], 'url' => Url::route('forum.show', ['slug' => (string) $forum['slug']])];
        }

        $crumbs[] = ['label' => Str::limit((string) $topic['title'], 60)];

        $userId = $this->auth->id();
        $firstPost = $paginator->items()[0] ?? null;

        $this->view->title((string) $topic['title']);
        $this->view->meta('description', Str::limit(
            ContentFormatter::plain((string) ($firstPost['content'] ?? $topic['title'])),
            160,
        ));
        $this->view->canonical(Url::withQuery($baseUrl, $page > 1 ? ['page' => $page] : []));
        $this->view->breadcrumbs($crumbs);

        return $this->render('topic/show', [
            'topic' => $topic,
            'forum' => $forum,
            'paginator' => $paginator,
            'post_policy' => new PostPolicy(),
            'topic_policy' => $this->policy,
            'can_reply' => $this->policy->reply($topic),
            'can_moderate' => $canModerate,
            'first_number' => $paginator->firstItemNumber(),
            'is_subscribed' => $userId !== null && $this->topics->isSubscribed($userId, (int) $topic['id']),
            'is_bookmarked' => $userId !== null && $this->topics->isBookmarked($userId, (int) $topic['id']),
            'quick_reply' => $this->policy->reply($topic),
        ]);
    }

    public function createForm(Request $request): Response
    {
        $forum = $this->forums->findBySlug((string) $request->route('forum'));

        if ($forum === null) {
            throw HttpException::notFound('There is no forum at this address.');
        }

        if (!$this->access->canCreateTopicIn((int) $forum['id'])) {
            throw HttpException::forbidden('You cannot start a topic in this forum.');
        }

        $this->view->title('New topic · ' . (string) $forum['name']);
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => (string) $forum['name'], 'url' => Url::route('forum.show', ['slug' => (string) $forum['slug']])],
            ['label' => 'New topic'],
        ]);

        return $this->render('topic/create', [
            'forum' => $forum,
            'postable_forums' => (new ForumService())->postableForums(),
        ]);
    }

    public function store(Request $request): Response
    {
        $forum = $this->forums->findBySlug((string) $request->route('forum'));

        if ($forum === null) {
            throw HttpException::notFound('There is no forum at this address.');
        }

        if (!$this->access->canCreateTopicIn((int) $forum['id'])) {
            throw HttpException::forbidden('You cannot start a topic in this forum.');
        }

        $title = (string) $request->input('title', '');
        $content = $request->text('content');
        $formUrl = Url::route('topic.create', ['forum' => (string) $forum['slug']]);

        $validator = Validator::make(['title' => $title, 'content' => $content])
            ->label('title', 'Subject')
            ->label('content', 'Message')
            ->required('title')->between('title', 5, 190)
            ->required('content')->between('content', $this->settings->int('min_post_length', 5), 60000);

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), ['title' => $title, 'content' => $content], $formUrl);
        }

        $result = $this->service->createTopic(
            (int) $forum['id'],
            $this->userId(),
            $title,
            $content,
            $request->ip(),
            $request->bool('subscribe'),
        );

        Flash::success('Your topic was posted.');

        return $this->redirect(Url::route('topic.show', ['slug' => $result['slug']], [], 'post-' . $result['post_id']));
    }

    /**
     * Reply form. `?quote=<post id>` seeds the editor with the quoted post —
     * the whole quoting feature is a plain GET followed by a plain POST.
     */
    public function replyForm(Request $request): Response
    {
        $topic = $this->findTopicOrFail((string) $request->route('slug'));

        if (!$this->policy->reply($topic)) {
            throw HttpException::forbidden('This topic is closed to new replies.');
        }

        $prefill = '';
        $quotedUserId = null;
        $quoteId = $request->int('quote', 0);

        if ($quoteId > 0) {
            $quoted = $this->posts->find($quoteId);

            if ($quoted !== null && (int) $quoted['topic_id'] === (int) $topic['id'] && $quoted['deleted_at'] === null) {
                $prefill = ContentFormatter::quoteOf(
                    (string) ($quoted['author_username'] ?? 'Guest'),
                    (int) $quoted['id'],
                    (string) $quoted['content'],
                );
                $quotedUserId = $quoted['user_id'] === null ? null : (int) $quoted['user_id'];
            }
        }

        $this->view->title('Reply · ' . (string) $topic['title']);
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => (string) $topic['forum_name'], 'url' => Url::route('forum.show', ['slug' => (string) $topic['forum_slug']])],
            ['label' => Str::limit((string) $topic['title'], 50), 'url' => Url::route('topic.show', ['slug' => (string) $topic['slug']])],
            ['label' => 'Reply'],
        ]);

        return $this->render('topic/reply', [
            'topic' => $topic,
            'prefill' => $prefill,
            'quoted_user_id' => $quotedUserId,
            'recent_posts' => $this->posts->paginateForTopic((int) $topic['id'], 1, 5, '')->items(),
        ]);
    }

    public function storeReply(Request $request): Response
    {
        $topic = $this->findTopicOrFail((string) $request->route('slug'));

        if (!$this->policy->reply($topic)) {
            throw HttpException::forbidden('This topic is closed to new replies.');
        }

        $content = $request->text('content');
        $formUrl = Url::route('topic.reply', ['slug' => (string) $topic['slug']]);

        $validator = Validator::make(['content' => $content])
            ->label('content', 'Message')
            ->required('content')
            ->between('content', $this->settings->int('min_post_length', 5), 60000);

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), ['content' => $content], $formUrl);
        }

        $quotedUserId = $request->int('quoted_user_id', 0);

        $result = $this->service->reply(
            $topic,
            $this->userId(),
            $content,
            $request->ip(),
            $quotedUserId > 0 ? $quotedUserId : null,
            $request->bool('subscribe'),
        );

        Flash::success('Your reply was posted.');

        return $this->redirect($result['url']);
    }

    public function editForm(Request $request): Response
    {
        $topic = $this->findTopicOrFail((string) $request->route('slug'));

        if (!$this->policy->edit($topic)) {
            throw HttpException::forbidden('You cannot edit this topic.');
        }

        $this->view->title('Edit topic · ' . (string) $topic['title']);

        return $this->render('topic/edit', ['topic' => $topic]);
    }

    public function update(Request $request): Response
    {
        $topic = $this->findTopicOrFail((string) $request->route('slug'));

        if (!$this->policy->edit($topic)) {
            throw HttpException::forbidden('You cannot edit this topic.');
        }

        $title = (string) $request->input('title', '');
        $formUrl = Url::route('topic.edit', ['slug' => (string) $topic['slug']]);

        $validator = Validator::make(['title' => $title])
            ->label('title', 'Subject')
            ->required('title')
            ->between('title', 5, 190);

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), ['title' => $title], $formUrl);
        }

        $this->service->rename($topic, $title);

        Flash::success('Topic updated.');

        return $this->redirect(Url::route('topic.show', ['slug' => (string) $topic['slug']]));
    }

    public function deleteForm(Request $request): Response
    {
        $topic = $this->findTopicOrFail((string) $request->route('slug'));

        if (!$this->policy->delete($topic)) {
            throw HttpException::forbidden('You cannot delete this topic.');
        }

        $this->view->title('Delete topic');

        return $this->render('topic/delete', ['topic' => $topic]);
    }

    public function destroy(Request $request): Response
    {
        $topic = $this->findTopicOrFail((string) $request->route('slug'));

        if (!$this->policy->delete($topic)) {
            throw HttpException::forbidden('You cannot delete this topic.');
        }

        $this->service->deleteTopic($topic, $this->userId());

        if ($this->policy->moderate($topic)) {
            (new \App\Services\ModerationService())->record(
                $this->userId(),
                'topic.delete',
                'topic',
                (int) $topic['id'],
                sprintf('Deleted topic “%s”', (string) $topic['title']),
                $request->input('reason'),
                $topic['user_id'] === null ? null : (int) $topic['user_id'],
                [],
                $request->ip(),
            );
        }

        Flash::success('Topic deleted.');

        return $this->redirect(Url::route('forum.show', ['slug' => (string) $topic['forum_slug']]));
    }

    public function subscribe(Request $request): Response
    {
        $topic = $this->findTopicOrFail((string) $request->route('slug'));
        $this->requirePermission('topic.subscribe');

        $userId = $this->userId();

        if ($this->topics->isSubscribed($userId, (int) $topic['id'])) {
            $this->topics->unsubscribe($userId, (int) $topic['id']);
            Flash::info('You will no longer be notified about this topic.');
        } else {
            $this->topics->subscribe($userId, (int) $topic['id']);
            Flash::success('You will be notified about new replies.');
        }

        return $this->back($request, Url::route('topic.show', ['slug' => (string) $topic['slug']]));
    }

    public function bookmark(Request $request): Response
    {
        $topic = $this->findTopicOrFail((string) $request->route('slug'));
        $this->requirePermission('topic.bookmark');

        $userId = $this->userId();

        if ($this->topics->isBookmarked($userId, (int) $topic['id'])) {
            $this->topics->removeBookmark($userId, (int) $topic['id']);
            Flash::info('Bookmark removed.');
        } else {
            $this->topics->bookmark($userId, (int) $topic['id']);
            Flash::success('Topic bookmarked.');
        }

        return $this->back($request, Url::route('topic.show', ['slug' => (string) $topic['slug']]));
    }

    /** @return array<string,mixed> */
    private function findTopicOrFail(string $slug): array
    {
        $topic = $this->topics->findBySlug($slug);

        if ($topic === null) {
            throw HttpException::notFound('There is no topic at this address.');
        }

        return $topic;
    }

    private function postsPerPage(): int
    {
        $user = $this->auth->user();
        $preference = (int) ($user['posts_per_page'] ?? 0);

        return $preference > 0 ? min(100, $preference) : $this->settings->postsPerPage();
    }
}
