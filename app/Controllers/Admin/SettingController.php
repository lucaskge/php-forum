<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Repositories\SettingRepository;
use App\Services\ModerationService;
use App\Support\Flash;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;

final class SettingController extends Controller
{
    public function index(Request $request): Response
    {
        $group = (string) $request->route('group', 'general');
        $grouped = $this->settings->grouped();

        if (!isset($grouped[$group])) {
            $group = array_key_first($grouped) ?? 'general';
        }

        $this->view->setLayout('layouts/admin');
        $this->view->title('Settings · ' . ucfirst($group));
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/settings', [
            'groups' => array_keys($grouped),
            'group' => $group,
            'settings' => $grouped[$group] ?? [],
        ]);
    }

    public function update(Request $request): Response
    {
        $group = (string) $request->route('group', 'general');
        $grouped = $this->settings->grouped();
        $definitions = $grouped[$group] ?? [];

        $values = [];

        foreach ($definitions as $definition) {
            $key = (string) $definition['key_name'];
            $type = (string) $definition['type'];

            $values[$key] = match ($type) {
                'boolean' => $request->bool('setting_' . $key) ? '1' : '0',
                'integer' => (string) $request->int('setting_' . $key, 0),
                'select' => $this->validateSelect($definition, (string) $request->input('setting_' . $key, '')),
                'text' => $request->text('setting_' . $key),
                default => (string) $request->input('setting_' . $key, ''),
            };
        }

        $this->settings->putMany($values);

        (new ModerationService())->record(
            $this->userId(),
            'admin.settings.update',
            'settings',
            null,
            sprintf('Updated the “%s” settings group', $group),
            null,
            null,
            ['keys' => array_keys($values)],
            $request->ip(),
        );

        Flash::success('Settings saved.');

        return $this->redirect(Url::route('admin.settings.group', ['group' => $group]));
    }

    /** @param array<string,mixed> $definition */
    private function validateSelect(array $definition, string $value): string
    {
        $options = json_decode((string) ($definition['options'] ?? '[]'), true);

        if (!is_array($options) || $options === []) {
            return $value;
        }

        $allowed = array_map('strval', array_keys($options));

        return in_array($value, $allowed, true) ? $value : (string) ($definition['value'] ?? '');
    }
}
