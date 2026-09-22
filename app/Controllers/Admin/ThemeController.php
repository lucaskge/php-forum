<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Repositories\ThemeRepository;
use App\Services\ModerationService;
use App\Services\ThemeManager;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;

final class ThemeController extends Controller
{
    private ThemeManager $themes;

    private ThemeRepository $repository;

    public function __construct()
    {
        parent::__construct();

        $this->themes = new ThemeManager();
        $this->repository = new ThemeRepository();
    }

    public function index(Request $request): Response
    {
        $this->view->setLayout('layouts/admin');
        $this->view->title('Themes');
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/themes', [
            'themes' => $this->themes->discover(),
            'active' => $this->themes->activeSlug(),
            'themes_path' => $this->themes->themesPath(),
        ]);
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->route('slug');
        $theme = $this->find($slug);

        $this->view->setLayout('layouts/admin');
        $this->view->title('Theme · ' . (string) $theme['name']);
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/theme-show', [
            'theme' => $theme,
            'manifest' => $this->themes->manifest($slug),
            'record' => $this->repository->findBySlug($slug),
            'is_active' => $this->themes->activeSlug() === $slug,
        ]);
    }

    /**
     * Appearance editor: colours, typography and the code-highlighting palette,
     * all declared by the theme itself rather than hardcoded here.
     */
    public function appearance(Request $request): Response
    {
        $slug = (string) $request->route('slug');
        $theme = $this->find($slug);

        if (!$this->themes->isCustomisable($slug)) {
            Flash::warning('This theme declares no customisable settings in its manifest.');

            return $this->redirect(Url::route('admin.theme', ['slug' => $slug]));
        }

        $this->view->setLayout('layouts/admin');
        $this->view->title('Appearance · ' . (string) $theme['name']);
        $this->view->meta('robots', 'noindex');

        return $this->render('admin/theme-appearance', [
            'theme' => $theme,
            'groups' => $this->groupSchema($slug),
            'values' => $this->themes->settings($slug),
            'is_active' => $this->themes->activeSlug() === $slug,
            'sample' => $this->sampleCode(),
        ]);
    }

    public function saveAppearance(Request $request): Response
    {
        $slug = (string) $request->route('slug');
        $this->find($slug);

        $values = [];

        foreach (array_keys($this->themes->schema($slug)) as $key) {
            $values[$key] = (string) ($request->all()['theme'][$key] ?? '');
        }

        if (!$this->themes->saveSettings($slug, $values)) {
            Flash::error('That theme is not registered yet. Scan the directory first.');

            return $this->redirect(Url::route('admin.themes'));
        }

        (new ModerationService())->record(
            $this->userId(),
            'admin.theme.appearance',
            'theme',
            null,
            sprintf('Changed the appearance of the theme “%s”', $slug),
            null,
            null,
            ['settings' => count($values)],
            $request->ip(),
        );

        Flash::success('Appearance saved.');

        return $this->redirect(Url::route('admin.theme.appearance', ['slug' => $slug]));
    }

    public function resetAppearance(Request $request): Response
    {
        $slug = (string) $request->route('slug');
        $this->find($slug);

        $this->themes->resetSettings($slug);

        Flash::info('Appearance reset to the values the theme ships with.');

        return $this->redirect(Url::route('admin.theme.appearance', ['slug' => $slug]));
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function groupSchema(string $slug): array
    {
        $groups = [];

        foreach ($this->themes->schema($slug) as $definition) {
            $groups[(string) $definition['group']][] = $definition;
        }

        return $groups;
    }

    /** A snippet that exercises every token class in the preview. */
    private function sampleCode(): string
    {
        return <<<'SAMPLE'
            [code=php]<?php
            // Every token class the highlighter can emit appears below.
            namespace App\Services;

            final class ForumService extends Repository
            {
                public const PER_PAGE = 25;

                public function refresh(int $forumId, ?string $reason = null): bool
                {
                    $rows = $this->db->select('SELECT id FROM forums WHERE id = :id', ['id' => $forumId]);

                    return count($rows) > 0 && $reason !== null;
                }
            }[/code]
            SAMPLE;
    }

    public function activate(Request $request): Response
    {
        $slug = (string) $request->route('slug');

        if (!$this->themes->activate($slug)) {
            Flash::error('That theme is not installed on disk.');

            return $this->redirect(Url::route('admin.themes'));
        }

        (new ModerationService())->record(
            $this->userId(),
            'admin.theme.activate',
            'theme',
            null,
            sprintf('Activated the theme “%s”', $slug),
            null,
            null,
            [],
            $request->ip(),
        );

        Flash::success(sprintf('“%s” is now the active theme.', $slug));

        return $this->redirect(Url::route('admin.themes'));
    }

    public function toggle(Request $request): Response
    {
        $slug = (string) $request->route('slug');
        $record = $this->repository->findBySlug($slug);

        if ($record === null) {
            Flash::error('Register the theme first by synchronising.');

            return $this->redirect(Url::route('admin.themes'));
        }

        if ((int) $record['is_active'] === 1) {
            Flash::error('The active theme cannot be disabled. Activate another one first.');

            return $this->redirect(Url::route('admin.themes'));
        }

        $enable = (int) $record['is_enabled'] === 0;
        $this->repository->setEnabled($slug, $enable);

        Flash::success(sprintf('Theme %s.', $enable ? 'enabled' : 'disabled'));

        return $this->redirect(Url::route('admin.themes'));
    }

    public function synchronise(Request $request): Response
    {
        $added = $this->themes->synchronise();

        Flash::success($added === 0
            ? 'No new themes were found on disk.'
            : sprintf('%d new theme(s) registered.', $added));

        return $this->redirect(Url::route('admin.themes'));
    }

    /** @return array<string,mixed> */
    private function find(string $slug): array
    {
        foreach ($this->themes->discover() as $theme) {
            if ((string) $theme['slug'] === $slug) {
                return $theme;
            }
        }

        throw HttpException::notFound('No such theme.');
    }
}
