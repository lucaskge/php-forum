<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Repositories\SessionRepository;
use App\Services\ModerationService;
use App\Support\Config;
use App\Support\Database;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\RateLimiter;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;

final class SystemController extends Controller
{
    public function info(Request $request): Response
    {
        $db = Database::instance();

        $tables = $db->select(
            'SELECT TABLE_NAME AS name, TABLE_ROWS AS approx_rows,
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 1) AS size_kb
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :schema ORDER BY TABLE_NAME',
            ['schema' => (string) Config::get('database.database')],
        );

        $this->view->setLayout('layouts/admin');
        $this->view->title('System information');
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/system', [
            'php_version' => PHP_VERSION,
            'extensions' => $this->requiredExtensions(),
            'server' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'),
            'database_version' => (string) $db->scalar('SELECT VERSION()'),
            'database_name' => (string) Config::get('database.database'),
            'tables' => $tables,
            'paths' => Config::get('app.paths'),
            'writable' => [
                'storage/logs' => is_writable((string) Config::get('app.paths.logs')),
                'public/uploads/avatars' => is_writable((string) Config::get('uploads.avatars.directory')),
            ],
            'limits' => [
                'memory_limit' => (string) ini_get('memory_limit'),
                'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
                'post_max_size' => (string) ini_get('post_max_size'),
                'max_execution_time' => (string) ini_get('max_execution_time'),
            ],
            'app' => [
                'environment' => (string) Config::get('app.env'),
                'debug' => Config::get('app.debug') ? 'enabled' : 'disabled',
                'url' => (string) Config::get('app.url'),
                'timezone' => (string) Config::get('app.timezone'),
                'key_set' => Config::get('app.key') !== '' ? 'yes' : 'no',
            ],
            'migrations' => $db->select('SELECT filename, batch, executed_at FROM migrations ORDER BY id ASC'),
        ]);
    }

    public function logs(Request $request): Response
    {
        $channels = Logger::channels();
        $channel = (string) $request->input('channel', $channels[0] ?? 'app');

        if (!in_array($channel, $channels, true)) {
            $channel = $channels[0] ?? 'app';
        }

        $this->view->setLayout('layouts/admin');
        $this->view->title('Logs');
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/logs', [
            'channels' => $channels,
            'channel' => $channel,
            'lines' => $channel === '' ? [] : Logger::tail($channel, 300),
        ]);
    }

    public function clearLog(Request $request): Response
    {
        $channel = basename((string) $request->input('channel', ''));
        $file = Config::get('app.paths.logs') . '/' . $channel . '.log';

        if ($channel !== '' && is_file($file)) {
            @file_put_contents($file, '');
            Flash::success(sprintf('Log “%s” cleared.', $channel));
        } else {
            Flash::error('No such log channel.');
        }

        return $this->redirect(Url::route('admin.logs', [], ['channel' => $channel]));
    }

    public function maintenance(Request $request): Response
    {
        $this->view->setLayout('layouts/admin');
        $this->view->title('Maintenance');
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/maintenance', [
            'maintenance_mode' => $this->settings->bool('maintenance_mode', false),
            'maintenance_message' => $this->settings->string('maintenance_message', ''),
            'stale_sessions' => (new SessionRepository())->activeCount(86400),
        ]);
    }

    public function runTask(Request $request): Response
    {
        $task = (string) $request->input('task', '');
        $moderation = new ModerationService();

        $message = match ($task) {
            'purge-sessions' => sprintf('%d stale session record(s) removed.', (new SessionRepository())->purge(86400)),
            'purge-throttles' => sprintf('%d expired throttle record(s) removed.', RateLimiter::purgeExpired()),
            'expire-bans' => $this->expireBans($moderation),
            'toggle-maintenance' => $this->toggleMaintenance(),
            default => null,
        };

        if ($message === null) {
            Flash::error('Unknown maintenance task.');
        } else {
            Flash::success($message);

            (new ModerationService())->record(
                $this->userId(),
                'admin.system.task',
                'system',
                null,
                sprintf('Ran the maintenance task “%s”', $task),
                null,
                null,
                [],
                $request->ip(),
            );
        }

        return $this->redirect(Url::route('admin.maintenance'));
    }

    private function expireBans(ModerationService $moderation): string
    {
        $moderation->expireLapsedBans();

        return 'Lapsed suspensions were cleared.';
    }

    private function toggleMaintenance(): string
    {
        $enabled = !$this->settings->bool('maintenance_mode', false);
        $this->settings->put('maintenance_mode', $enabled ? '1' : '0');

        return $enabled
            ? 'Maintenance mode is ON — only administrators can reach the board.'
            : 'Maintenance mode is OFF.';
    }

    /** @return array<string,bool> */
    private function requiredExtensions(): array
    {
        $extensions = ['pdo_mysql', 'mbstring', 'json', 'fileinfo', 'gd', 'openssl'];
        $status = [];

        foreach ($extensions as $extension) {
            $status[$extension] = extension_loaded($extension);
        }

        return $status;
    }
}
