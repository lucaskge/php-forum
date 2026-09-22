<?php

declare(strict_types=1);

namespace App\Support;

use App\Middleware\AuthenticateMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\MaintenanceMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RestrictionMiddleware;
use App\Middleware\SettingMiddleware;
use App\Repositories\MessageRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\ReportRepository;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Services\AccessControl;
use App\Services\AuthService;
use App\Services\SettingsService;
use App\Services\ThemeManager;
use Throwable;

/**
 * Wires the application together and turns a Request into a Response.
 */
final class Kernel
{
    private Router $router;

    private View $view;

    private bool $booted = false;

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        Env::load(BASE_PATH . '/.env');
        Config::load(BASE_PATH . '/config');

        date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));
        mb_internal_encoding('UTF-8');

        $debug = (bool) Config::get('app.debug', false);
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting(E_ALL);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        Session::start();

        $this->router = new Router();
        $this->router->registerMiddleware([
            'auth' => AuthenticateMiddleware::class,
            'guest' => GuestMiddleware::class,
            'can' => PermissionMiddleware::class,
            'csrf' => CsrfMiddleware::class,
            'throttle' => RateLimitMiddleware::class,
            'restricted' => RestrictionMiddleware::class,
            'maintenance' => MaintenanceMiddleware::class,
            'setting' => SettingMiddleware::class,
        ]);

        $this->view = new View(new ThemeManager());

        $router = $this->router;
        require BASE_PATH . '/routes/web.php';
    }

    public function handle(Request $request): Response
    {
        try {
            $this->view->reset();
            $this->prepare($request);

            return $this->router->dispatch($request);
        } catch (HttpException $exception) {
            return $this->renderError($exception->statusCode(), $exception->title(), $exception->getMessage(), $request);
        } catch (Throwable $exception) {
            Logger::exception($exception);

            if ((bool) Config::get('app.debug', false)) {
                return $this->renderError(
                    500,
                    'Unhandled exception',
                    $exception::class . ': ' . $exception->getMessage()
                        . "\n" . $exception->getFile() . ':' . $exception->getLine()
                        . "\n\n" . $exception->getTraceAsString(),
                    $request,
                );
            }

            return $this->renderError(500, 'Internal error', 'Something went wrong while handling this request. The incident has been logged.', $request);
        }
    }

    /**
     * Per-request state every template can rely on. Controllers never have to
     * pass the current user, the settings or the permission checker around.
     */
    private function prepare(Request $request): void
    {
        $settings = SettingsService::instance();
        $auth = AuthService::instance();
        $access = AccessControl::instance();

        $user = null;
        $unreadMessages = 0;
        $unreadNotifications = 0;
        $pendingReports = 0;

        try {
            $user = $auth->user();

            if ($user !== null) {
                $unreadMessages = (new MessageRepository())->unreadCount((int) $user['id']);
                $unreadNotifications = (new NotificationRepository())->unreadCount((int) $user['id']);
                (new UserRepository())->touchActivity((int) $user['id'], $request->ip());
            }

            if ($access->canModerateAnything()) {
                $pendingReports = (new ReportRepository())->pendingCount();
            }

            (new SessionRepository())->touch(
                Session::id(),
                $user === null ? null : (int) $user['id'],
                $request->ip(),
                $request->userAgent(),
                $request->path(),
            );
        } catch (Throwable $exception) {
            // A missing schema must not stop the error pages from rendering.
            Logger::exception($exception);
        }

        $this->view->share([
            'app' => [
                'name' => $settings->string('site_name', (string) Config::get('app.name', 'Coldwire')),
                'version' => '1.0.0',
            ],
            'site_name' => $settings->string('site_name', (string) Config::get('app.name', 'Coldwire')),
            'site_tagline' => $settings->string('site_tagline', ''),
            'settings' => $settings->all(),
            'current_user' => $user,
            'viewer_timezone' => $user['timezone'] ?? 'UTC',
            'current_path' => $request->path(),
            'current_query' => $request->queryString(),
            'unread_messages' => $unreadMessages,
            'unread_notifications' => $unreadNotifications,
            'pending_reports' => $pendingReports,
            'is_staff' => $access->canModerateAnything(),
            'is_admin' => $access->isAdministrator(),
            'permission_checker' => static fn (string $permission): bool => AccessControl::instance()->can($permission),
            'flash_messages' => Flash::drain(),
            'old_input' => Flash::oldInput(),
            'form_errors' => Flash::errors(),
            'csrf_token' => Csrf::token(),
            'chat_enabled' => $settings->bool('chat_enabled', true),
        ]);
    }

    private function renderError(int $status, string $title, string $message, Request $request): Response
    {
        $template = 'errors/' . $status;

        try {
            if (!$this->view->exists($template)) {
                $template = 'errors/generic';
            }

            $this->view->setLayout('layouts/minimal');
            $this->view->title($status . ' · ' . $title);

            $body = $this->view->render($template, [
                'status' => $status,
                'title' => $title,
                'message' => $message,
                'path' => $request->path(),
            ]);

            return Response::html($body, $status);
        } catch (Throwable $exception) {
            Logger::exception($exception);

            return Response::text(sprintf("%d %s\n\n%s\n", $status, $title, $message), $status);
        }
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function view(): View
    {
        return $this->view;
    }
}
