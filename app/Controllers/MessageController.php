<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\MessageRepository;
use App\Repositories\UserRepository;
use App\Services\MessageService;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Str;
use App\Support\Url;
use App\Support\Validator;

final class MessageController extends Controller
{
    private MessageRepository $messages;

    private MessageService $service;

    public function __construct()
    {
        parent::__construct();

        $this->messages = new MessageRepository();
        $this->service = new MessageService();
    }

    public function inbox(Request $request): Response
    {
        $paginator = $this->messages->inbox(
            $this->userId(),
            $this->page($request),
            $this->settings->itemsPerPage(),
            Url::route('messages.inbox'),
        );

        $this->view->title('Inbox');
        $this->view->meta('robots', 'noindex');
        $this->view->breadcrumbs($this->crumbs('Inbox'));

        return $this->render('messages/inbox', [
            'paginator' => $paginator,
            'folder' => 'inbox',
            'unread' => $this->service->unreadCount($this->userId()),
        ]);
    }

    public function sent(Request $request): Response
    {
        $paginator = $this->messages->sent(
            $this->userId(),
            $this->page($request),
            $this->settings->itemsPerPage(),
            Url::route('messages.sent'),
        );

        $this->view->title('Sent messages');
        $this->view->meta('robots', 'noindex');
        $this->view->breadcrumbs($this->crumbs('Sent'));

        return $this->render('messages/sent', [
            'paginator' => $paginator,
            'folder' => 'sent',
            'unread' => $this->service->unreadCount($this->userId()),
        ]);
    }

    public function composeForm(Request $request): Response
    {
        $to = (string) $request->input('to', '');
        $replyTo = $request->int('reply_to', 0);
        $parent = null;

        if ($replyTo > 0) {
            $parent = $this->messages->find($replyTo);

            if ($parent === null || !$this->service->canAccess($parent, $this->userId())) {
                $parent = null;
            }
        }

        $this->view->title('Compose message');
        $this->view->meta('robots', 'noindex');
        $this->view->breadcrumbs($this->crumbs('Compose'));

        return $this->render('messages/compose', [
            'to' => $to !== '' ? $to : (string) ($parent['sender_username'] ?? ''),
            'subject' => $parent === null ? '' : $this->replySubject((string) $parent['subject']),
            'body' => $parent === null ? '' : $this->quoteBody($parent),
            'parent' => $parent,
            'folder' => 'compose',
            'unread' => $this->service->unreadCount($this->userId()),
        ]);
    }

    public function send(Request $request): Response
    {
        $sender = $this->user();
        $to = (string) $request->input('to', '');
        $subject = (string) $request->input('subject', '');
        $body = $request->text('body');
        $parentId = $request->int('parent_id', 0);
        $formUrl = Url::route('messages.compose');

        $validator = Validator::make(['to' => $to, 'subject' => $subject, 'body' => $body])
            ->label('to', 'Recipient')
            ->required('to')->maxLength('to', 32)
            ->required('subject')->between('subject', 2, 190)
            ->required('body')->between('body', 2, 20000);

        if ($validator->fails()) {
            return $this->withErrors($validator->errors(), ['to' => $to, 'subject' => $subject, 'body' => $body], $formUrl);
        }

        $parent = null;

        if ($parentId > 0) {
            $parent = $this->messages->find($parentId);

            if ($parent === null || !$this->service->canAccess($parent, (int) $sender['id'])) {
                $parentId = 0;
            }
        }

        $result = $this->service->send($sender, $to, $subject, $body, $parentId > 0 ? $parentId : null);

        if (!$result['ok']) {
            return $this->withErrors(
                ['to' => (string) $result['message']],
                ['to' => $to, 'subject' => $subject, 'body' => $body],
                $formUrl,
            );
        }

        Flash::success('Message sent.');

        return $this->redirect(Url::route('messages.sent'));
    }

    public function show(Request $request): Response
    {
        $message = $this->findOrFail((int) $request->route('id'));
        $userId = $this->userId();

        if ($this->service->isRecipient($message, $userId) && (int) $message['is_read'] === 0) {
            $this->messages->markRead((int) $message['id']);
            $message['is_read'] = 1;
        }

        $this->view->title((string) $message['subject']);
        $this->view->meta('robots', 'noindex');
        $this->view->breadcrumbs($this->crumbs(Str::limit((string) $message['subject'], 40)));

        return $this->render('messages/show', [
            'message' => $message,
            'thread' => $this->messages->thread((int) $message['id'], $userId),
            'is_recipient' => $this->service->isRecipient($message, $userId),
            'folder' => $this->service->isRecipient($message, $userId) ? 'inbox' : 'sent',
            'unread' => $this->service->unreadCount($userId),
        ]);
    }

    public function toggleRead(Request $request): Response
    {
        $message = $this->findOrFail((int) $request->route('id'));

        if (!$this->service->isRecipient($message, $this->userId())) {
            throw HttpException::forbidden('Only the recipient can change the read state.');
        }

        $this->messages->markRead((int) $message['id'], (int) $message['is_read'] === 0);

        Flash::info((int) $message['is_read'] === 0 ? 'Marked as read.' : 'Marked as unread.');

        return $this->back($request, Url::route('messages.inbox'));
    }

    public function destroy(Request $request): Response
    {
        $message = $this->findOrFail((int) $request->route('id'));
        $userId = $this->userId();
        $wasRecipient = $this->service->isRecipient($message, $userId);

        $this->messages->deleteFor((int) $message['id'], $userId);

        Flash::success('Message deleted.');

        return $this->redirect($wasRecipient ? Url::route('messages.inbox') : Url::route('messages.sent'));
    }

    /** @return array<string,mixed> */
    private function findOrFail(int $id): array
    {
        $message = $this->messages->find($id);

        if ($message === null || !$this->service->canAccess($message, $this->userId())) {
            throw HttpException::notFound('That message does not exist or is not yours.');
        }

        return $message;
    }

    private function replySubject(string $subject): string
    {
        return str_starts_with(mb_strtolower($subject), 're:') ? $subject : 'Re: ' . $subject;
    }

    /** @param array<string,mixed> $parent */
    private function quoteBody(array $parent): string
    {
        return sprintf(
            "[quote=%s]%s[/quote]\n\n",
            (string) ($parent['sender_username'] ?? 'Member'),
            (string) $parent['body'],
        );
    }

    /** @return array<int,array{label:string,url?:string}> */
    private function crumbs(string $current): array
    {
        return [
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => 'Messages', 'url' => Url::route('messages.inbox')],
            ['label' => $current],
        ];
    }
}
