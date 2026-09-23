<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ModerationRepository;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;

/**
 * What the board has on file about the member reading it.
 *
 * A board that warns and suspends people owes them somewhere to read what they
 * were warned for and when it ends. Without this page a warning notification
 * has nowhere to lead, and a suspension is something the member discovers by
 * finding buttons that no longer work.
 */
final class AccountController extends Controller
{
    public function record(Request $request): Response
    {
        $user = $this->user();
        $userId = (int) $user['id'];
        $moderation = new ModerationRepository();

        $this->view->title('Your record');
        $this->view->meta('robots', 'noindex');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => 'Account settings', 'url' => Url::route('settings.profile')],
            ['label' => 'Your record'],
        ]);

        return $this->render('user/record', [
            'profile' => $user,
            'warnings' => $moderation->warningsForUser($userId),
            'restriction' => $moderation->activeBan($userId),
            'history' => $moderation->bansForUser($userId),
            'points' => $moderation->activeWarningPoints($userId),
        ]);
    }
}
