<?php

declare(strict_types=1);

namespace App\Controllers\Moderation;

use App\Controllers\Controller;
use App\Policies\TopicPolicy;
use App\Repositories\ForumRepository;
use App\Repositories\PostRepository;
use App\Repositories\TopicRepository;
use App\Services\ForumService;
use App\Services\ModerationService;
use App\Services\TopicService;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use App\Support\Validator;

/**
 * Topic-level moderation. Each action has a confirmation screen (a real page,
 * not a dialog) and a POST that performs the change and writes the log entry.
 */
final class TopicController extends Controller
{
    private TopicRepository $topics;

    private TopicService $service;

    private ModerationService $moderation;

    private TopicPolicy $policy;

    public function __construct()
    {
        parent::__construct();

        $this->topics = new TopicRepository();
        $this->service = new TopicService();
        $this->moderation = new ModerationService();
        $this->policy = new TopicPolicy();
    }

    public function panel(Request $request): Response
    {
        $topic = $this->findOrFail((string) $request->route('slug'));

        $this->view->title('Moderate · ' . (string) $topic['title']);
        $this->view->meta('robots', 'noindex');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => (string) $topic['forum_name'], 'url' => Url::route('forum.show', ['slug' => (string) $topic['forum_slug']])],
            ['label' => (string) $topic['title'], 'url' => Url::route('topic.show', ['slug' => (string) $topic['slug']])],
            ['label' => 'Moderate'],
        ]);

        return $this->render('moderation/topic-panel', [
            'topic' => $topic,
            'policy' => $this->policy,
            'move_targets' => (new ForumService())->moveTargets(),
            'posts' => (new PostRepository())->allForTopic((int) $topic['id']),
        ]);
    }

    public function flag(Request $request): Response
    {
        $topic = $this->findOrFail((string) $request->route('slug'));
        $action = (string) $request->input('action', '');

        [$flag, $value, $permission, $label] = match ($action) {
            'pin' => ['is_pinned', true, 'topic.pin', 'Pinned'],
            'unpin' => ['is_pinned', false, 'topic.pin', 'Unpinned'],
            'lock' => ['is_locked', true, 'topic.lock', 'Locked'],
            'unlock' => ['is_locked', false, 'topic.lock', 'Unlocked'],
            'hide' => ['is_hidden', true, 'topic.hide', 'Hid'],
            'unhide' => ['is_hidden', false, 'topic.hide', 'Unhid'],
            'archive' => ['is_archived', true, 'topic.lock', 'Archived'],
            'unarchive' => ['is_archived', false, 'topic.lock', 'Unarchived'],
            default => [null, false, '', ''],
        };

        if ($flag === null) {
            throw HttpException::badRequest('Unknown moderation action.');
        }

        $this->requirePermission($permission);

        $this->service->setFlag($topic, $flag, $value);

        $this->moderation->record(
            $this->userId(),
            'topic.' . $action,
            'topic',
            (int) $topic['id'],
            sprintf('%s topic “%s”', $label, (string) $topic['title']),
            $request->input('reason'),
            $topic['user_id'] === null ? null : (int) $topic['user_id'],
            [],
            $request->ip(),
        );

        Flash::success(sprintf('%s “%s”.', $label, (string) $topic['title']));

        return $this->back($request, Url::route('topic.show', ['slug' => (string) $topic['slug']]));
    }

    public function move(Request $request): Response
    {
        $topic = $this->findOrFail((string) $request->route('slug'));

        if (!$this->policy->move($topic)) {
            throw HttpException::forbidden('You cannot move this topic.');
        }

        $targetId = $request->int('forum_id', 0);
        $target = (new ForumRepository())->find($targetId);

        if ($target === null || !$this->access->canModerateForum($targetId)) {
            Flash::error('Choose a forum you moderate.');

            return $this->redirect(Url::route('moderation.topic', ['slug' => (string) $topic['slug']]));
        }

        $this->service->move($topic, $targetId);

        $this->moderation->record(
            $this->userId(),
            'topic.move',
            'topic',
            (int) $topic['id'],
            sprintf('Moved “%s” from %s to %s', (string) $topic['title'], (string) $topic['forum_name'], (string) $target['name']),
            $request->input('reason'),
            $topic['user_id'] === null ? null : (int) $topic['user_id'],
            ['from' => (int) $topic['forum_id'], 'to' => $targetId],
            $request->ip(),
        );

        Flash::success(sprintf('Topic moved to %s.', (string) $target['name']));

        return $this->redirect(Url::route('topic.show', ['slug' => (string) $topic['slug']]));
    }

    public function mergeForm(Request $request): Response
    {
        $topic = $this->findOrFail((string) $request->route('slug'));

        if (!$this->policy->merge($topic)) {
            throw HttpException::forbidden('You cannot merge this topic.');
        }

        $search = (string) $request->input('q', '');
        $candidates = $search === ''
            ? []
            : $this->topics->paginateForAdmin(['search' => $search], 1, 20, '')->items();

        $this->view->title('Merge topic');
        $this->view->meta('robots', 'noindex');

        return $this->render('moderation/topic-merge', [
            'topic' => $topic,
            'search' => $search,
            'candidates' => array_values(array_filter(
                $candidates,
                fn (array $candidate): bool => (int) $candidate['id'] !== (int) $topic['id']
                    && $this->access->canModerateForum((int) $candidate['forum_id']),
            )),
        ]);
    }

    public function merge(Request $request): Response
    {
        $topic = $this->findOrFail((string) $request->route('slug'));

        if (!$this->policy->merge($topic)) {
            throw HttpException::forbidden('You cannot merge this topic.');
        }

        $targetId = $request->int('target_id', 0);
        $target = $this->topics->find($targetId);

        if ($target === null || $targetId === (int) $topic['id'] || !$this->access->canModerateForum((int) $target['forum_id'])) {
            Flash::error('Choose a destination topic you moderate.');

            return $this->redirect(Url::route('moderation.topic.merge', ['slug' => (string) $topic['slug']]));
        }

        $moved = $this->service->merge($topic, $target, $this->userId());

        $this->moderation->record(
            $this->userId(),
            'topic.merge',
            'topic',
            (int) $topic['id'],
            sprintf('Merged “%s” into “%s” (%d post(s))', (string) $topic['title'], (string) $target['title'], $moved),
            $request->input('reason'),
            $topic['user_id'] === null ? null : (int) $topic['user_id'],
            ['target_topic_id' => $targetId, 'posts_moved' => $moved],
            $request->ip(),
        );

        Flash::success(sprintf('%d post(s) merged into “%s”.', $moved, (string) $target['title']));

        return $this->redirect(Url::route('topic.show', ['slug' => (string) $target['slug']]));
    }

    public function splitForm(Request $request): Response
    {
        $topic = $this->findOrFail((string) $request->route('slug'));

        if (!$this->policy->split($topic)) {
            throw HttpException::forbidden('You cannot split this topic.');
        }

        $this->view->title('Split topic');
        $this->view->meta('robots', 'noindex');

        return $this->render('moderation/topic-split', [
            'topic' => $topic,
            'posts' => (new PostRepository())->allForTopic((int) $topic['id']),
            'move_targets' => (new ForumService())->moveTargets(),
        ]);
    }

    public function split(Request $request): Response
    {
        $topic = $this->findOrFail((string) $request->route('slug'));

        if (!$this->policy->split($topic)) {
            throw HttpException::forbidden('You cannot split this topic.');
        }

        $title = (string) $request->input('title', '');
        $forumId = $request->int('forum_id', (int) $topic['forum_id']);
        $selected = array_values(array_filter(array_map('intval', $request->array('posts'))));

        $validator = Validator::make(['title' => $title])
            ->label('title', 'New subject')
            ->required('title')
            ->between('title', 5, 190);

        if ($validator->fails()) {
            Flash::error((string) $validator->firstError());

            return $this->redirect(Url::route('moderation.topic.split', ['slug' => (string) $topic['slug']]));
        }

        if ($selected === []) {
            Flash::error('Select at least one post to split out.');

            return $this->redirect(Url::route('moderation.topic.split', ['slug' => (string) $topic['slug']]));
        }

        if (!$this->access->canModerateForum($forumId)) {
            Flash::error('Choose a destination forum you moderate.');

            return $this->redirect(Url::route('moderation.topic.split', ['slug' => (string) $topic['slug']]));
        }

        // Only posts that actually belong to this topic may be split out.
        $ownPostIds = array_map(
            static fn (array $post): int => (int) $post['id'],
            (new PostRepository())->allForTopic((int) $topic['id']),
        );

        $selected = array_values(array_intersect($selected, $ownPostIds));

        if ($selected === [] || count($selected) >= count($ownPostIds)) {
            Flash::error('Leave at least one post in the original topic.');

            return $this->redirect(Url::route('moderation.topic.split', ['slug' => (string) $topic['slug']]));
        }

        $result = $this->service->split($topic, $selected, $title, $forumId, $this->userId());

        $this->moderation->record(
            $this->userId(),
            'topic.split',
            'topic',
            (int) $topic['id'],
            sprintf('Split %d post(s) out of “%s” into “%s”', count($selected), (string) $topic['title'], $title),
            $request->input('reason'),
            null,
            ['new_topic_id' => $result['topic_id'], 'posts' => $selected],
            $request->ip(),
        );

        Flash::success(sprintf('%d post(s) split into a new topic.', count($selected)));

        return $this->redirect(Url::route('topic.show', ['slug' => $result['slug']]));
    }

    public function restore(Request $request): Response
    {
        $topic = $this->findOrFail((string) $request->route('slug'));

        if (!$this->policy->moderate($topic)) {
            throw HttpException::forbidden('You cannot restore this topic.');
        }

        $this->service->restoreTopic($topic);

        $this->moderation->record(
            $this->userId(),
            'topic.restore',
            'topic',
            (int) $topic['id'],
            sprintf('Restored topic “%s”', (string) $topic['title']),
            null,
            $topic['user_id'] === null ? null : (int) $topic['user_id'],
            [],
            $request->ip(),
        );

        Flash::success('Topic restored.');

        return $this->redirect(Url::route('topic.show', ['slug' => (string) $topic['slug']]));
    }

    /** @return array<string,mixed> */
    private function findOrFail(string $slug): array
    {
        $topic = $this->topics->findBySlug($slug);

        if ($topic === null) {
            throw HttpException::notFound('There is no topic at this address.');
        }

        if (!$this->policy->moderate($topic)) {
            throw HttpException::forbidden('You do not moderate this forum.');
        }

        return $topic;
    }
}
