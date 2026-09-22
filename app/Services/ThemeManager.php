<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ThemeRepository;
use App\Support\Config;
use App\Support\Url;

/**
 * Resolves which theme renders the site and where its templates and assets
 * live. A theme is a directory under /templates/themes containing a theme.json
 * manifest, template files and an assets folder. Themes may declare a parent,
 * in which case missing templates fall through to it and then to `default`.
 */
final class ThemeManager
{
    public const FALLBACK = 'default';

    private ThemeRepository $repository;

    private ?string $activeSlug = null;

    /** @var array<int,string>|null */
    private ?array $lookupPaths = null;

    /** @var array<string,mixed>|null */
    private ?array $activeTheme = null;

    public function __construct(?ThemeRepository $repository = null)
    {
        $this->repository = $repository ?? new ThemeRepository();
    }

    public function themesPath(): string
    {
        return (string) Config::get('app.paths.themes');
    }

    public function activeSlug(): string
    {
        if ($this->activeSlug !== null) {
            return $this->activeSlug;
        }

        try {
            $theme = $this->repository->active();
        } catch (\Throwable) {
            // The database may not be reachable yet (installation, error page).
            $theme = null;
        }

        $slug = $theme['slug'] ?? self::FALLBACK;

        if (!$this->isInstalledOnDisk((string) $slug)) {
            $slug = self::FALLBACK;
        }

        $this->activeTheme = $theme;
        $this->activeSlug = (string) $slug;

        return $this->activeSlug;
    }

    /** @return array<string,mixed>|null */
    public function activeTheme(): ?array
    {
        $this->activeSlug();

        return $this->activeTheme;
    }

    /** Forces a theme for the current request (used by the theme preview). */
    public function useTheme(string $slug): void
    {
        if ($this->isInstalledOnDisk($slug)) {
            $this->activeSlug = $slug;
            $this->lookupPaths = null;
        }
    }

    /**
     * Template search order: active theme, its parent chain, then `default`.
     *
     * @return array<int,string>
     */
    public function lookupPaths(): array
    {
        if ($this->lookupPaths !== null) {
            return $this->lookupPaths;
        }

        $paths = [];
        $slug = $this->activeSlug();
        $seen = [];

        while ($slug !== '' && !isset($seen[$slug])) {
            $seen[$slug] = true;
            $directory = $this->themesPath() . '/' . $slug;

            if (is_dir($directory)) {
                $paths[] = $directory;
            }

            $manifest = $this->manifest($slug);
            $slug = (string) ($manifest['parent'] ?? '');
        }

        $fallback = $this->themesPath() . '/' . self::FALLBACK;

        if (!in_array($fallback, $paths, true) && is_dir($fallback)) {
            $paths[] = $fallback;
        }

        $this->lookupPaths = $paths;

        return $paths;
    }

    public function assetUrl(string $path): string
    {
        $path = ltrim($path, '/');
        $slug = $this->activeSlug();

        // Assets are served by the front controller from the theme directory,
        // which keeps theme packages self-contained.
        $file = $this->themesPath() . '/' . $slug . '/assets/' . $path;
        $version = is_file($file) ? (string) filemtime($file) : '1';

        if (!is_file($file)) {
            $fallbackFile = $this->themesPath() . '/' . self::FALLBACK . '/assets/' . $path;

            if (is_file($fallbackFile)) {
                return Url::to('/theme/' . self::FALLBACK . '/' . $path, ['v' => (string) filemtime($fallbackFile)]);
            }
        }

        return Url::to('/theme/' . $slug . '/' . $path, ['v' => $version]);
    }

    // ------------------------------------------------------------------
    // Customisation
    // ------------------------------------------------------------------

    /**
     * The settings a theme declares as customisable, keyed by setting key.
     *
     * A theme opts into customisation by listing definitions in its manifest;
     * each one names the CSS custom property it drives. Nothing here is
     * hardcoded to the default theme.
     *
     * @return array<string,array<string,mixed>>
     */
    public function schema(string $slug): array
    {
        $manifest = $this->manifest($slug);
        $definitions = $manifest['settings'] ?? [];
        $schema = [];

        // A theme may also inherit its parent's customisable settings.
        $parent = (string) ($manifest['parent'] ?? '');

        if ($parent !== '' && $parent !== $slug) {
            $schema = $this->schema($parent);
        }

        if (!is_array($definitions)) {
            return $schema;
        }

        foreach ($definitions as $definition) {
            if (!is_array($definition) || !isset($definition['key'], $definition['css'])) {
                continue;
            }

            $schema[(string) $definition['key']] = [
                'key' => (string) $definition['key'],
                'group' => (string) ($definition['group'] ?? 'General'),
                'label' => (string) ($definition['label'] ?? $definition['key']),
                'type' => (string) ($definition['type'] ?? 'string'),
                'default' => (string) ($definition['default'] ?? ''),
                'css' => (string) $definition['css'],
                'unit' => (string) ($definition['unit'] ?? ''),
                'hint' => (string) ($definition['hint'] ?? ''),
                'min' => isset($definition['min']) ? (int) $definition['min'] : null,
                'max' => isset($definition['max']) ? (int) $definition['max'] : null,
                'options' => is_array($definition['options'] ?? null) ? $definition['options'] : [],
                'values' => is_array($definition['values'] ?? null) ? $definition['values'] : [],
            ];
        }

        return $schema;
    }

    public function isCustomisable(string $slug): bool
    {
        return $this->schema($slug) !== [];
    }

    /**
     * Stored overrides for a theme, defaults filled in.
     *
     * @return array<string,string>
     */
    public function settings(string $slug): array
    {
        $schema = $this->schema($slug);
        $stored = $this->storedSettings($slug);
        $values = [];

        foreach ($schema as $key => $definition) {
            $values[$key] = $this->sanitise($definition, $stored[$key] ?? null);
        }

        return $values;
    }

    /** @return array<string,string> */
    private function storedSettings(string $slug): array
    {
        $record = $this->repository->findBySlug($slug);
        $decoded = json_decode((string) ($record['settings'] ?? '{}'), true);

        if (!is_array($decoded)) {
            return [];
        }

        $values = [];

        foreach ($decoded as $key => $value) {
            if (is_scalar($value)) {
                $values[(string) $key] = (string) $value;
            }
        }

        return $values;
    }

    /**
     * Writes overrides, dropping anything that is not declared or not valid.
     * A value equal to the default is stored anyway so the record is explicit.
     *
     * @param array<string,string> $values
     */
    public function saveSettings(string $slug, array $values): bool
    {
        $record = $this->repository->findBySlug($slug);

        if ($record === null) {
            return false;
        }

        $schema = $this->schema($slug);
        $clean = [];

        foreach ($schema as $key => $definition) {
            $clean[$key] = $this->sanitise($definition, $values[$key] ?? null);
        }

        $this->repository->update((int) $record['id'], [
            'settings' => json_encode($clean, JSON_UNESCAPED_SLASHES),
        ]);

        return true;
    }

    public function resetSettings(string $slug): void
    {
        $record = $this->repository->findBySlug($slug);

        if ($record !== null) {
            $this->repository->update((int) $record['id'], ['settings' => json_encode([], JSON_UNESCAPED_SLASHES)]);
        }
    }

    /**
     * Every value is validated against its declared type before it can reach a
     * stylesheet, so a stored setting can never inject arbitrary CSS.
     *
     * @param array<string,mixed> $definition
     */
    private function sanitise(array $definition, ?string $value): string
    {
        $default = (string) $definition['default'];

        if ($value === null || trim($value) === '') {
            return $default;
        }

        $value = trim($value);

        return match ((string) $definition['type']) {
            'colour' => preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtolower($value) : $default,
            'integer' => $this->clampInteger($definition, $value, $default),
            'select' => array_key_exists($value, (array) $definition['options']) ? $value : $default,
            default => preg_match('/^[A-Za-z0-9 ,._"\'()-]{0,120}$/', $value) === 1 ? $value : $default,
        };
    }

    /** @param array<string,mixed> $definition */
    private function clampInteger(array $definition, string $value, string $default): string
    {
        if (preg_match('/^-?\d{1,6}$/', $value) !== 1) {
            return $default;
        }

        $number = (int) $value;
        $min = $definition['min'];
        $max = $definition['max'];

        if ($min !== null) {
            $number = max((int) $min, $number);
        }

        if ($max !== null) {
            $number = min((int) $max, $number);
        }

        return (string) $number;
    }

    /**
     * The stylesheet that carries a theme's settings, served separately so the
     * page needs no inline <style> and the Content-Security-Policy can keep
     * refusing inline styles outright.
     */
    public function customCss(string $slug): string
    {
        $schema = $this->schema($slug);

        if ($schema === []) {
            return "/* This theme declares no customisable settings. */
";
        }

        $values = $this->settings($slug);
        $lines = [];

        foreach ($schema as $key => $definition) {
            $value = $values[$key] ?? (string) $definition['default'];

            // A select maps its key onto the real CSS value.
            if ((string) $definition['type'] === 'select') {
                $value = (string) (($definition['values'][$value] ?? null) ?? $value);
            }

            if ($value === '') {
                continue;
            }

            $lines[] = sprintf('    %s: %s%s;', $definition['css'], $value, $definition['unit']);
        }

        return "/* Theme settings for \"" . $slug . "\". Generated from the database. */
"
            . ":root {
" . implode("
", $lines) . "
}
";
    }

    /** Changes whenever the settings do, so browsers pick the new file up. */
    public function settingsVersion(string $slug): string
    {
        $record = $this->repository->findBySlug($slug);

        return $record === null ? '0' : (string) strtotime((string) $record['updated_at']);
    }

    public function customCssUrl(string $slug): string
    {
        return Url::to('/theme/' . $slug . '/settings.css', ['v' => $this->settingsVersion($slug)]);
    }

    public function isInstalledOnDisk(string $slug): bool
    {
        return $slug !== '' && preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) === 1
            && is_dir($this->themesPath() . '/' . $slug);
    }

    /**
     * Reads a theme.json manifest from disk.
     *
     * @return array<string,mixed>
     */
    public function manifest(string $slug): array
    {
        if (!$this->isInstalledOnDisk($slug)) {
            return [];
        }

        $file = $this->themesPath() . '/' . $slug . '/theme.json';

        if (!is_readable($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Every theme present on disk, merged with its database record.
     *
     * @return array<int,array<string,mixed>>
     */
    public function discover(): array
    {
        $installed = [];

        foreach ($this->repository->all() as $row) {
            $installed[(string) $row['slug']] = $row;
        }

        $themes = [];

        foreach (glob($this->themesPath() . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $slug = basename($directory);
            $manifest = $this->manifest($slug);
            $record = $installed[$slug] ?? null;

            $themes[$slug] = [
                'slug' => $slug,
                'name' => $manifest['name'] ?? ($record['name'] ?? ucfirst($slug)),
                'version' => $manifest['version'] ?? ($record['version'] ?? '1.0.0'),
                'author' => $manifest['author'] ?? ($record['author'] ?? 'Unknown'),
                'description' => $manifest['description'] ?? ($record['description'] ?? ''),
                'parent' => $manifest['parent'] ?? ($record['parent_slug'] ?? null),
                'screenshot' => $manifest['screenshot'] ?? null,
                'settings' => $manifest['settings'] ?? [],
                'customisable' => $this->schema($slug) !== [],
                'registered' => $record !== null,
                'is_active' => (bool) ($record['is_active'] ?? false),
                'is_enabled' => (bool) ($record['is_enabled'] ?? true),
                'templates' => $this->countTemplates($slug),
                'path' => $directory,
            ];
        }

        // Records without a directory are reported so the admin can clean up.
        foreach ($installed as $slug => $record) {
            if (!isset($themes[$slug])) {
                $themes[$slug] = [
                    'slug' => $slug,
                    'name' => (string) $record['name'],
                    'version' => (string) $record['version'],
                    'author' => (string) ($record['author'] ?? ''),
                    'description' => (string) ($record['description'] ?? ''),
                    'parent' => $record['parent_slug'],
                    'screenshot' => null,
                    'settings' => [],
                    'customisable' => false,
                    'registered' => true,
                    'is_active' => (bool) $record['is_active'],
                    'is_enabled' => (bool) $record['is_enabled'],
                    'templates' => 0,
                    'path' => null,
                    'missing' => true,
                ];
            }
        }

        ksort($themes);

        return array_values($themes);
    }

    /** Registers any theme found on disk but absent from the database. */
    public function synchronise(): int
    {
        $added = 0;

        foreach ($this->discover() as $theme) {
            if ($theme['registered'] === true || ($theme['missing'] ?? false) === true) {
                continue;
            }

            $this->repository->create([
                'slug' => $theme['slug'],
                'name' => (string) $theme['name'],
                'version' => (string) $theme['version'],
                'author' => (string) $theme['author'],
                'description' => mb_substr((string) $theme['description'], 0, 500),
                'parent_slug' => $theme['parent'],
                'is_active' => 0,
                'is_enabled' => 1,
                // The column stores chosen values, never the schema itself.
                'settings' => json_encode([], JSON_UNESCAPED_SLASHES),
            ]);

            $added++;
        }

        return $added;
    }

    public function activate(string $slug): bool
    {
        if (!$this->isInstalledOnDisk($slug)) {
            return false;
        }

        $this->synchronise();
        $this->repository->activate($slug);
        $this->activeSlug = null;
        $this->lookupPaths = null;

        return true;
    }

    private function countTemplates(string $slug): int
    {
        $directory = $this->themesPath() . '/' . $slug;
        $count = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $count++;
            }
        }

        return $count;
    }
}
