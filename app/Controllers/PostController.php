<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Policies\PostPolicy;
use App\Repositories\PostRepository;
use App\Repositories\ReportRepository;
use App\Repositories\TopicRepository;
use App\Services\ModerationService;
use App\Services\ReportService;
use App\Services\TopicService;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use App\Support\Validator;

final class PostController extends Controller
{
    private PostRepository $posts;

    private TopicService $service;

    private PostPolicy $policy;

    public function __construct()
    {
        parent::__construct();

        $this->posts = new PostRepository();
        $this->service = new TopicService();
        $this->policy = new PostPolicy();
    }

    /** Permalink: resolves a post id to the page it sits on. */
    public function permalink(Request $request): Response
    {
        $post = $this->findPostOrFail((int) $request->route('id'));

        if (!$this->policy->view($post)) {
            throw HttpException::forbidden('That post is not available to your account.');
        }

        return $this->redirect($this->service->postUrl(
            (int) $post['topic_id'],
            (string) $post['topic_slug'],
            (int) $post['id'],
            $this->policy->moderate($post),
        ));
    }

    public function editForm(Request $request): Response
    {
        $post = $this->findPostOrFail((int) $request->route('id'));

        if (!$this->policy->edit($post)) {
            throw HttpException::forbidden('This post can no longer be edited.');
        }

        $topic = (new TopicRepository())->find((int) $post['topic_id']);

        $this->view->title('Edit post #' . (int) $post['id']);
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => (string) $post['forum_name'], 'url' => Url::route('forum.show', ['slug' => (string) $post['forum_slug']])],
            ['label' => (string) $post['topic_title'], 'url' => Url::route('topic.show', ['slug' => (string) $post['topic_slug']])],
            ['label' => 'Edit post'],
        ]);

        return $this->render('topic/edit-post', [
            'post' => $post,
            'topic' => $topic,
            'is_moderator_edit' => (int) ($post['user_id'] ?? 0) !== $this->auth->id(),
        ]);
    }

    public function update(Request $request): Response
    {
        $post = $this->findPostOrFail((int) $request->route('id'));

        if (!$this->policy->edit($post)) {
            throw HttpException::forbidden('This post can no longer be edited.');
        }

        $content = $request->text('content');
        $reason = $request->input('reason');
        $formUrl = Url::route('post.edit', ['id' => (int) $post['id']]);

        $validator = Validator::make(['content' => $content])
            ->label('content', 'Message')
            ->required('content')
            ->between('content', $this->settings->int('min_post_length', 5), 60000);

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), ['content' => $content, 'reason' => $reason], $formUrl);
        }

        if ($content === (string) $post['content']) {
            Flash::info('No changes were made.');

            return $this->redirect($this->postUrl($post));
        }

        $editorId = $this->userId();
        $this->service->editPost($post, $editorId, $content, $reason, $request->ip());

        if ((int) ($post['user_id'] ?? 0) !== $editorId) {
            (new ModerationService())->record(
                $editorId,
                'post.edit',
                'post',
                (int) $post['id'],
                sprintf('Edited post #%d by %s', (int) $post['id'], (string) ($post['author_username'] ?? 'a guest')),
                $reason,
                $post['user_id'] === null ? null : (int) $post['user_id'],
                [],
                $request->ip(),
            );
        }

        Flash::success('Post updated.');

        return $this->redirect($this->postUrl($post));
    }

    public function deleteForm(Request $request): Response
    {
        $post = $this->findPostOrFail((int) $request->route('id'));

        if (!$this->policy->delete($post)) {
            throw HttpException::forbidden('This post cannot be deleted.');
        }

        $this->view->title('Delete post #' . (int) $post['id']);

        return $this->render('topic/delete-post', [
            'post' => $post,
            'is_moderator_action' => (int) ($post['user_id'] ?? 0) !== $this->auth->id(),
        ]);
    }

    public function destroy(Request $request): Response
    {
        $post = $this->findPostOrFail((int) $request->route('id'));

        if (!$this->policy->delete($post)) {
            throw HttpException::forbidden('This post cannot be deleted.');
        }

        $actorId = $this->userId();
        $this->service->deletePost($post, $actorId);

        if ((int) ($post['user_id'] ?? 0) !== $actorId) {
            (new ModerationService())->record(
                $actorId,
                'post.delete',
                'post',
                (int) $post['id'],
                sprintf('Deleted post #%d by %s', (int) $post['id'], (string) ($post['author_username'] ?? 'a guest')),
                $request->input('reason'),
                $post['user_id'] === null ? null : (int) $post['user_id'],
                [],
                $request->ip(),
            );
        }

        Flash::success('Post deleted.');

        return $this->redirect(Url::route('topic.show', ['slug' => (string) $post['topic_slug']]));
    }

    public function restore(Request $request): Response
    {
        $post = $this->findPostOrFail((int) $request->route('id'));

        if (!$this->policy->restore($post)) {
            throw HttpException::forbidden('You cannot restore this post.');
        }

        $this->service->restorePost($post);

        (new ModerationService())->record(
            $this->userId(),
            'post.restore',
            'post',
            (int) $post['id'],
            sprintf('Restored post #%d', (int) $post['id']),
            null,
            $post['user_id'] === null ? null : (int) $post['user_id'],
            [],
            $request->ip(),
        );

        Flash::success('Post restored.');

        return $this->back($request, $this->postUrl($post));
    }

    public function toggleHidden(Request $request): Response
    {
        $post = $this->findPostOrFail((int) $request->route('id'));

        if (!$this->policy->hide($post)) {
            throw HttpException::forbidden('You cannot hide this post.');
        }

        $hide = (int) $post['is_hidden'] === 0;
        $this->service->setPostHidden($post, $hide);

        (new ModerationService())->record(
            $this->userId(),
            $hide ? 'post.hide' : 'post.unhide',
            'post',
            (int) $post['id'],
            sprintf('%s post #%d', $hide ? 'Hid' : 'Unhid', (int) $post['id']),
            $request->input('reason'),
            $post['user_id'] === null ? null : (int) $post['user_id'],
            [],
            $request->ip(),
        );

        Flash::success($hide ? 'Post hidden from members.' : 'Post is visible again.');

        return $this->back($request, $this->postUrl($post));
    }

    public function history(Request $request): Response
    {
        $post = $this->findPostOrFail((int) $request->route('id'));

        if (!$this->policy->viewHistory($post)) {
            throw HttpException::forbidden('The edit history of this post is not visible to your account.');
        }

        $this->view->title('Edit history · post #' . (int) $post['id']);
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => (string) $post['topic_title'], 'url' => Url::route('topic.show', ['slug' => (string) $post['topic_slug']])],
            ['label' => 'Edit history'],
        ]);

        return $this->render('topic/history', [
            'post' => $post,
            'revisions' => $this->posts->editHistory((int) $post['id']),
        ]);
    }

    public function reportForm(Request $request): Response
    {
        $post = $this->findPostOrFail((int) $request->route('id'));

        if (!$this->policy->report($post)) {
            throw HttpException::forbidden('You cannot report this post.');
        }

        $this->view->title('Report post #' . (int) $post['id']);

        return $this->render('topic/report', [
            'post' => $post,
            'reasons' => ReportService::REASONS,
        ]);
    }

    public function report(Request $request): Response
    {
        $post = $this->findPostOrFail((int) $request->route('id'));

        if (!$this->policy->report($post)) {
            throw HttpException::forbidden('You cannot report this post.');
        }

        $reports = new ReportRepository();
        $userId = $this->userId();

        if ($reports->alreadyReported($userId, 'post', (int) $post['id'])) {
            Flash::info('You already reported this post — the moderators are on it.');

            return $this->redirect($this->postUrl($post));
        }

        $reason = (string) $request->input('reason', '');
        $details = $request->text('details');

        $validator = Validator::make(['reason' => $reason, 'details' => $details])
            ->required('reason')
            ->in('reason', array_keys(ReportService::REASONS))
            ->maxLength('details', 2000);

        if ($validator->fails()) {
            return $this->withErrors(
                $validator->errors(),
                ['reason' => $reason, 'details' => $details],
                Url::route('post.report', ['id' => (int) $post['id']]),
            );
        }

        (new ReportService())->submit(
            $userId,
            'post',
            (int) $post['id'],
            $post['user_id'] === null ? null : (int) $post['user_id'],
            $reason,
            $details === '' ? null : $details,
            $request->ip(),
        );

        Flash::success('Report submitted. Moderators will review it.');

        return $this->redirect($this->postUrl($post));
    }

    /** @return array<string,mixed> */
    private function findPostOrFail(int $id): array
    {
        $post = $this->posts->find($id);

        if ($post === null) {
            throw HttpException::notFound('There is no post with that number.');
        }

        return $post;
    }

    /** @param array<string,mixed> $post */
    private function postUrl(array $post): string
    {
        return $this->service->postUrl(
            (int) $post['topic_id'],
            (string) $post['topic_slug'],
            (int) $post['id'],
            $this->policy->moderate($post),
        );
    }
}
