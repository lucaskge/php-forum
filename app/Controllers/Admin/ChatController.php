<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Repositories\ChatRepository;
use App\Services\Chat\TransportFactory;
use App\Services\ChatService;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Str;
use App\Support\Url;
use App\Support\Validator;

final class ChatController extends Controller
{
    private ChatRepository $chat;

    public function __construct()
    {
        parent::__construct();

        $this->chat = new ChatRepository();
    }

    public function index(Request $request): Response
    {
        $service = new ChatService();

        $this->view->setLayout('layouts/admin');
        $this->view->title('Chat');
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/chat', [
            'rooms' => $this->chat->rooms(false),
            'transport' => $service->transport(),
            'transports' => TransportFactory::available(),
            'message_count' => $this->chat->messageCount(),
            'restriction_count' => $this->chat->activeRestrictionCount(),
            'enabled' => $this->settings->bool('chat_enabled', true),
        ]);
    }

    public function roomForm(Request $request): Response
    {
        $id = $request->int('id', 0);
        $room = $id > 0 ? $this->chat->findRoom($id) : null;

        if ($id > 0 && $room === null) {
            throw HttpException::notFound('No such room.');
        }

        $this->view->setLayout('layouts/admin');
        $this->view->title($room === null ? 'New chat room' : 'Edit chat room');

        return $this->render('admin/chat-room', ['room' => $room]);
    }

    public function saveRoom(Request $request): Response
    {
        $id = $request->int('id', 0);
        $existing = $id > 0 ? $this->chat->findRoom($id) : null;

        $name = (string) $request->input('name', '');
        $slug = (string) $request->input('slug', '') !== '' ? (string) $request->input('slug') : Str::slug($name);

        $validator = Validator::make(['name' => $name, 'slug' => $slug])
            ->required('name')->between('name', 2, 96)
            ->required('slug')->slug('slug')->maxLength('slug', 48);

        if ($validator->passes()) {
            $clash = $this->chat->findRoomBySlug($slug);

            if ($clash !== null && (int) $clash['id'] !== $id) {
                $validator->fail('slug', 'That identifier is already in use.');
            }
        }

        if ($validator->fails()) {
            return $this->withErrors(
                $validator->errors(),
                $request->all(),
                Url::route('admin.chat.room', [], $id > 0 ? ['id' => $id] : []),
            );
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'description' => $request->input('description'),
            'topic_line' => $request->input('topic_line'),
            'is_active' => $request->bool('is_active') ? 1 : 0,
            'is_readonly' => $request->bool('is_readonly') ? 1 : 0,
            'slow_mode' => max(0, min(3600, $request->int('slow_mode', 0))),
            'position' => $request->int('position', 0),
        ];

        if ($existing === null) {
            $this->chat->createRoom($data);
            Flash::success('Chat room created.');
        } else {
            $this->chat->updateRoom($id, $data);
            Flash::success('Chat room updated.');
        }

        return $this->redirect(Url::route('admin.chat'));
    }

    public function deleteRoom(Request $request): Response
    {
        $id = (int) $request->route('id');

        if ($this->chat->findRoom($id) === null) {
            throw HttpException::notFound('No such room.');
        }

        $this->chat->deleteRoom($id);

        Flash::success('Chat room deleted along with its messages.');

        return $this->redirect(Url::route('admin.chat'));
    }

    public function restrictions(Request $request): Response
    {
        $paginator = $this->chat->paginateRestrictions(
            $this->page($request),
            $this->settings->itemsPerPage(),
            Url::route('admin.chat.bans'),
        );

        $this->view->setLayout('layouts/admin');
        $this->view->title('Chat restrictions');
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/chat-bans', ['paginator' => $paginator]);
    }

    public function liftRestriction(Request $request): Response
    {
        (new ChatService())->liftRestriction((int) $request->route('id'), $this->userId(), $request->ip());

        Flash::success('Chat restriction lifted.');

        return $this->redirect(Url::route('admin.chat.bans'));
    }
}
