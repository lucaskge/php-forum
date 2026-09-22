<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\NotificationRepository;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;

final class NotificationController extends Controller
{
    private NotificationRepository $notifications;

    public function __construct()
    {
        parent::__construct();

        $this->notifications = new NotificationRepository();
    }

    public function index(Request $request): Response
    {
        $unreadOnly = $request->input('filter') === 'unread';

        $userId = $this->userId();

        $paginator = $this->notifications->paginate(
            $userId,
            $this->page($request),
            $this->settings->itemsPerPage(),
            Url::route('notifications'),
            $unreadOnly,
        );

        // Looking at the list counts as reading it — that is what clears the
        // badge. Only what is on this page is marked, so the counter keeps
        // telling the truth when there is more than one page, and the entries
        // are still highlighted here so you can see what was new.
        $justRead = [];

        foreach ($paginator->items() as $notification) {
            if ((int) $notification['is_read'] === 0) {
                $justRead[] = (int) $notification['id'];
            }
        }

        $this->notifications->markManyRead($justRead, $userId);

        // The header count was taken before this ran; refresh it so the badge
        // on the page you are looking at is not already stale.
        $this->view->share(['unread_notifications' => $this->notifications->unreadCount($userId)]);

        $this->view->title('Notifications');
        $this->view->meta('robots', 'noindex');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => 'Notifications'],
        ]);

        return $this->render('notifications/index', [
            'paginator' => $paginator,
            'unread_only' => $unreadOnly,
            'unread_count' => $this->notifications->unreadCount($userId),
            'just_read' => $justRead,
        ]);
    }

    /** Marks one notification read and forwards to its target. */
    public function open(Request $request): Response
    {
        $id = (int) $request->route('id');
        $notification = $this->notifications->find($id);

        if ($notification === null || (int) $notification['user_id'] !== $this->userId()) {
            throw HttpException::notFound('That notification does not exist.');
        }

        $this->notifications->markRead($id, $this->userId());

        // Notification targets are stored as logical paths.
        $url = (string) $notification['url'];
        [$path, $fragment] = array_pad(explode('#', $url, 2), 2, '');
        [$path, $query] = array_pad(explode('?', $path, 2), 2, '');

        if (!str_starts_with($path, '/')) {
            return $this->redirect(Url::route('notifications'));
        }

        parse_str((string) $query, $parameters);

        return $this->redirect(Url::to($path, $parameters, (string) $fragment));
    }

    public function markAllRead(Request $request): Response
    {
        $count = $this->notifications->markAllRead($this->userId());

        Flash::success($count === 0 ? 'Nothing left to read.' : sprintf('%d notification(s) marked as read.', $count));

        return $this->back($request, Url::route('notifications'));
    }

    public function destroy(Request $request): Response
    {
        $this->notifications->deleteFor((int) $request->route('id'), $this->userId());

        Flash::info('Notification removed.');

        return $this->back($request, Url::route('notifications'));
    }

    public function clear(Request $request): Response
    {
        $count = $this->notifications->clear($this->userId());

        Flash::success(sprintf('%d notification(s) removed.', $count));

        return $this->redirect(Url::route('notifications'));
    }
}
