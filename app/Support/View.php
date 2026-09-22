<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\ThemeManager;
use RuntimeException;

/**
 * Template renderer.
 *
 * Templates are plain PHP files living inside a theme directory. A template
 * never talks to the database: controllers pass everything it needs. Lookups
 * fall back to the `default` theme, so a custom theme only has to override the
 * files it actually wants to change.
 */
final class View
{
    private ThemeManager $themes;

    /** @var array<string,mixed> */
    private array $shared = [];

    private string $layout = 'layouts/main';

    private string $title = '';

    /** @var array<string,string> */
    private array $meta = [];

    private string $canonical = '';

    /** @var array<int,array{label:string,url:string|null}> */
    private array $breadcrumbs = [];

    private static ?View $instance = null;

    public function __construct(ThemeManager $themes)
    {
        $this->themes = $themes;
        self::$instance = $this;
    }

    public static function instance(): View
    {
        if (self::$instance === null) {
            self::$instance = new self(new ThemeManager());
        }

        return self::$instance;
    }

    /**
     * Clears the per-page state (layout, title, meta, breadcrumbs). One request
     * per process makes this a formality in production; it matters when several
     * requests are handled in the same process, as the tests do.
     */
    public function reset(): void
    {
        $this->layout = 'layouts/main';
        $this->title = '';
        $this->meta = [];
        $this->canonical = '';
        $this->breadcrumbs = [];
    }

    /** @param array<string,mixed> $data */
    public function share(array $data): void
    {
        $this->shared = array_merge($this->shared, $data);
    }

    public function shared(string $key, mixed $default = null): mixed
    {
        return $this->shared[$key] ?? $default;
    }

    public function themes(): ThemeManager
    {
        return $this->themes;
    }

    /**
     * Renders a template inside the current layout.
     *
     * @param array<string,mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $this->layout = $data['__layout'] ?? $this->layout;
        unset($data['__layout']);

        $content = $this->renderTemplate($template, $data);

        if ($this->layout === '') {
            return $content;
        }

        return $this->renderTemplate($this->layout, array_merge($data, ['content' => $content]));
    }

    /**
     * Renders a template without a layout — used for partials and components.
     *
     * @param array<string,mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        return $this->renderTemplate($template, $data);
    }

    /** @param array<string,mixed> $data */
    private function renderTemplate(string $template, array $data): string
    {
        $file = $this->resolve($template);

        $scope = array_merge($this->shared, $data);

        $render = function (string $__file, array $__scope): string {
            extract($__scope, EXTR_SKIP);
            ob_start();

            try {
                include $__file;
            } catch (\Throwable $exception) {
                ob_end_clean();

                throw $exception;
            }

            return (string) ob_get_clean();
        };

        return $render->call($this, $file, $scope);
    }

    public function resolve(string $template): string
    {
        $template = trim($template, '/');
        $candidates = [];

        foreach ($this->themes->lookupPaths() as $path) {
            $candidates[] = $path . '/' . $template . '.php';
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(sprintf('Template "%s" was not found in the active theme or the default theme.', $template));
    }

    public function exists(string $template): bool
    {
        try {
            $this->resolve($template);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function layout(string $layout): void
    {
        $this->layout = $layout;
    }

    public function setLayout(string $layout): self
    {
        $this->layout = $layout;

        return $this;
    }

    public function title(?string $title = null): string
    {
        if ($title !== null) {
            $this->title = $title;
        }

        return $this->title;
    }

    public function documentTitle(): string
    {
        $site = (string) $this->shared('site_name', 'Coldwire');

        return $this->title === '' ? $site : $this->title . ' · ' . $site;
    }

    public function meta(string $name, ?string $content = null): string
    {
        if ($content !== null) {
            $this->meta[$name] = $content;
        }

        return $this->meta[$name] ?? '';
    }

    /** @return array<string,string> */
    public function metaTags(): array
    {
        return $this->meta;
    }

    public function canonical(?string $url = null): string
    {
        if ($url !== null) {
            $this->canonical = str_starts_with($url, 'http') ? $url : Url::absolute($url);
        }

        return $this->canonical;
    }

    /** @param array<int,array{label:string,url?:string|null}> $crumbs */
    public function breadcrumbs(?array $crumbs = null): array
    {
        if ($crumbs !== null) {
            $this->breadcrumbs = array_map(
                static fn (array $crumb): array => ['label' => $crumb['label'], 'url' => $crumb['url'] ?? null],
                $crumbs,
            );
        }

        return $this->breadcrumbs;
    }

    // ---------------------------------------------------------------------
    // Helpers available inside every template via $this->
    // ---------------------------------------------------------------------

    public function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function attr(?string $value): string
    {
        return $this->e($value);
    }

    /**
      * @param array<string,string|int> $parameters Route placeholders.
      * @param array<string,string|int|null> $query Query string values.
      */
    public function route(string $name, array $parameters = [], array $query = [], string $fragment = ''): string
    {
        return Url::route($name, $parameters, $query, $fragment);
    }

    /** @param array<string,string|int|null> $query */
    public function url(string $path, array $query = [], string $fragment = ''): string
    {
        return Url::to($path, $query, $fragment);
    }

    public function asset(string $path): string
    {
        return $this->themes->assetUrl($path);
    }

    /** The stylesheet carrying the active theme's customised settings. */
    public function themeSettingsUrl(): string
    {
        return $this->themes->customCssUrl($this->themes->activeSlug());
    }

    /** The stylesheet carrying database-driven values (role colours, …). */
    public function dynamicStylesUrl(): string
    {
        return Url::route('styles.dynamic', [], ['v' => (new \App\Services\DynamicStyles())->version()]);
    }

    /** The class that paints a name in its role's colour. */
    public function roleClass(mixed $roleId): string
    {
        return \App\Services\DynamicStyles::roleClass($roleId);
    }

    /**
     * Renders a member's name, coloured by their role, linked to their profile.
     * Every screen that shows a username goes through this.
     *
     * @param array<string,mixed> $source A row holding the name and role id.
     */
    public function username(array $source, array $options = []): string
    {
        return $this->partial('partials/username', ['source' => $source, 'options' => $options]);
    }

    public function csrf(): string
    {
        return Csrf::field();
    }

    public function token(): string
    {
        return Csrf::token();
    }

    public function date(?string $value, string $format = 'Y-m-d H:i'): string
    {
        return Dates::format($value, $format, (string) $this->shared('viewer_timezone', 'UTC'));
    }

    public function relative(?string $value): string
    {
        return Dates::relative($value);
    }

    public function number(int|float|string|null $value): string
    {
        return number_format((float) ($value ?? 0), 0, '.', ' ');
    }

    /** @param array<string,mixed> $post */
    public function content(string $raw): string
    {
        return ContentFormatter::render($raw);
    }

    public function excerpt(string $raw, int $length = 180): string
    {
        return Str::limit(ContentFormatter::plain($raw), $length);
    }

    public function can(string $permission, mixed $context = null): bool
    {
        $checker = $this->shared('permission_checker');

        return is_callable($checker) ? (bool) $checker($permission, $context) : false;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $settings = $this->shared('settings', []);

        return is_array($settings) && array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /** @param array<string,mixed>|null $user */
    public function avatar(?array $user, int $size = 40): string
    {
        return $this->partial('partials/avatar', ['user' => $user, 'size' => $size]);
    }

    public function active(string $prefix): bool
    {
        $path = (string) $this->shared('current_path', '/');

        return $path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/');
    }
}
