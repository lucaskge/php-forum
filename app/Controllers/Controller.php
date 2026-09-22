<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AccessControl;
use App\Services\AuthService;
use App\Services\SettingsService;
use App\Support\Flash;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;

abstract class Controller
{
    protected View $view;

    protected AuthService $auth;

    protected AccessControl $access;

    protected SettingsService $settings;

    public function __construct()
    {
        $this->view = View::instance();
        $this->auth = AuthService::instance();
        $this->access = AccessControl::instance();
        $this->settings = SettingsService::instance();
    }

    /** @param array<string,mixed> $data */
    protected function render(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html($this->view->render($template, $data), $status);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    protected function back(Request $request, string $fallback = '/'): Response
    {
        return Response::redirect($request->safeReferer($fallback));
    }

    /**
     * Flashes validation errors plus the submitted values and bounces back so
     * the form can be re-rendered without losing what was typed.
     *
     * @param array<string,string> $errors
     * @param array<string,mixed> $input
     */
    protected function withErrors(array $errors, array $input, string $url): Response
    {
        Flash::withInput($input, $errors);

        $first = reset($errors);

        if (is_string($first)) {
            Flash::error($first);
        }

        return Response::redirect($url);
    }

    /** @return array<string,mixed> */
    protected function user(): array
    {
        $user = $this->auth->user();

        if ($user === null) {
            throw HttpException::unauthorized();
        }

        return $user;
    }

    protected function userId(): int
    {
        return (int) $this->user()['id'];
    }

    protected function requirePermission(string $permission, string $message = 'You do not have permission to do that.'): void
    {
        if (!$this->access->can($permission)) {
            throw HttpException::forbidden($message);
        }
    }

    protected function abortUnless(bool $condition, int $status = 403, string $message = ''): void
    {
        if (!$condition) {
            throw new HttpException($status, $message === '' ? HttpException::defaultTitle($status) : $message);
        }
    }

    /** Where the visitor was heading before being asked to sign in. */
    protected function intendedUrl(string $fallback = '/'): string
    {
        $intended = Session::pull('__intended');

        // Stored as a logical path; it becomes a URL in whichever mode is active.
        return is_string($intended) && str_starts_with($intended, '/')
            ? \App\Support\Url::to($intended)
            : $fallback;
    }

    protected function page(Request $request): int
    {
        return max(1, min(100000, $request->int('page', 1)));
    }
}
