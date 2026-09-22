<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ChatRepository;
use App\Repositories\UserRepository;
use App\Services\ChatService;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use App\Support\Validator;

/**
 * The chat page is rendered entirely on the server. Sending a message is a
 * normal POST followed by a redirect; the transcript is whatever the transport
 * returns on the next GET. Swapping in a real-time transport later does not
 * change this controller's shape — see App\Services\Chat\ChatTransport.
 */
final class ChatController extends Controller
{
    private ChatService $chat;

    public function __construct()
    {
        parent::__construct();

        $this->chat = new ChatService();
    }

    public function index(Request $request): Response
    {
        $room = $this->resolveRoom($request->route('room'));

        $userId = $this->auth->id();
        $canModerate = $this->access->can('chat.moderate');

        if ($userId !== null) {
            $this->chat->join((int) $room['id'], $userId);
        }

        $restriction = $userId === null ? null : $this->chat->restrictionFor($userId, (int) $room['id']);

        $this->view->title('Chat · ' . (string) $room['name']);
        $this->view->meta('description', 'Live discussion room for board members.');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => 'Chat'],
        ]);

        return $this->render('chat/index', [
            'room' => $room,
            'rooms' => $this->chat->rooms(),
            'messages' => $this->chat->history((int) $room['id'], $canModerate),
            'present' => $this->chat->presence((int) $room['id']),
            'can_post' => $userId !== null && $this->access->can('chat.post') && $restriction === null && (int) $room['is_readonly'] === 0,
            'can_moderate' => $canModerate,
            'restriction' => $restriction,
            'transport' => $this->chat->transport(),
            'rules' => $this->settings->string('chat_rules', ''),
            'max_length' => $this->settings->int('chat_max_length', 500),
            'slow_mode' => max((int) $room['slow_mode'], $this->settings->int('chat_slow_mode', 0)),
        ]);
    }

    public function send(Request $request): Response
    {
        $room = $this->resolveRoom($request->route('room'));
        $this->requirePermission('chat.post', 'Your account may not post in chat.');

        $content = $request->text('content');
        $redirect = Url::route('chat.room', ['room' => (string) $room['slug']]);
        $anchored = Url::route('chat.room', ['room' => (string) $room['slug']], [], 'chat-end');

        $validator = Validator::make(['content' => $content])
            ->label('content', 'Message')
            ->required('content')
            ->maxLength('content', $this->settings->int('chat_max_length', 500));

        if ($validator->fails()) {
            Flash::error((string) $validator->firstError());

            return $this->redirect($redirect);
        }

        $result = $this->chat->post($room, $this->userId(), $content, $request->ip());

        if (!$result['ok']) {
            Flash::error((string) $result['message']);
        }

        return $this->redirect($anchored);
    }

    public function deleteMessage(Request $request): Response
    {
        $this->requirePermission('chat.moderate');

        $room = $this->resolveRoom($request->route('room'));
        $messageId = (int) $request->route('id');

        if (!$this->chat->deleteMessage($messageId, $this->userId(), $request->ip())) {
            Flash::error('That message is already gone.');
        } else {
            Flash::success('Message removed.');
        }

        return $this->redirect(Url::route('chat.room', ['room' => (string) $room['slug']]));
    }

    public function moderateForm(Request $request): Response
    {
        $this->requirePermission('chat.moderate');

        $room = $this->resolveRoom($request->route('room'));
        $username = (string) $request->input('user', '');
        $target = $username === '' ? null : (new UserRepository())->findByUsername($username);

        $this->view->title('Chat moderation');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => 'Chat', 'url' => Url::route('chat.room', ['room' => (string) $room['slug']])],
            ['label' => 'Moderation'],
        ]);

        return $this->render('chat/moderate', [
            'room' => $room,
            'target' => $target,
            'username' => $username,
            'restriction' => $target === null ? null : $this->chat->restrictionFor((int) $target['id'], (int) $room['id']),
        ]);
    }

    public function moderate(Request $request): Response
    {
        $this->requirePermission('chat.moderate');

        $room = $this->resolveRoom($request->route('room'));
        $action = (string) $request->input('action', '');
        $username = (string) $request->input('user', '');
        $reason = (string) $request->input('reason', 'Chat rules violation');
        $minutes = $request->int('minutes', 15);

        $target = (new UserRepository())->findByUsername($username);

        if ($target === null) {
            Flash::error('No member is registered under that name.');

            return $this->redirect(Url::route('chat.moderate', ['room' => (string) $room['slug']]));
        }

        $targetId = (int) $target['id'];
        $moderatorId = $this->userId();

        if ($targetId === $moderatorId) {
            Flash::error('You cannot apply chat restrictions to yourself.');

            return $this->redirect(Url::route('chat.moderate', ['room' => (string) $room['slug']]));
        }

        match ($action) {
            'mute' => $this->chat->mute($targetId, $moderatorId, (int) $room['id'], $reason, max(1, $minutes), $request->ip()),
            'ban' => $this->chat->banFromChat($targetId, $moderatorId, $reason, $request->ip()),
            'purge' => $this->chat->purgeUser($targetId, (int) $room['id'], $moderatorId, $request->ip()),
            'lift' => $this->liftFor($targetId, (int) $room['id'], $moderatorId, $request->ip()),
            default => null,
        };

        Flash::success(match ($action) {
            'mute' => sprintf('%s is muted for %d minute(s).', (string) $target['username'], max(1, $minutes)),
            'ban' => sprintf('%s is banned from chat.', (string) $target['username']),
            'purge' => sprintf('Messages from %s were purged.', (string) $target['username']),
            'lift' => sprintf('Chat restrictions on %s were lifted.', (string) $target['username']),
            default => 'Nothing to do.',
        });

        return $this->redirect(Url::route(
            'chat.moderate',
            ['room' => (string) $room['slug']],
            ['user' => (string) $target['username']],
        ));
    }

    private function liftFor(int $userId, int $roomId, int $moderatorId, string $ip): void
    {
        $restriction = $this->chat->restrictionFor($userId, $roomId);

        if ($restriction !== null) {
            $this->chat->liftRestriction((int) $restriction['id'], $moderatorId, $ip);
        }
    }

    /** @return array<string,mixed> */
    private function resolveRoom(?string $slug): array
    {
        $room = $this->chat->room($slug);

        if ($room === null) {
            throw HttpException::notFound('There is no chat room at this address.');
        }

        if ((int) $room['is_active'] === 0 && !$this->access->can('chat.moderate')) {
            throw HttpException::notFound('That chat room is closed.');
        }

        return $room;
    }
}
