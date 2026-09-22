<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DynamicStyles;
use App\Support\Config;
use App\Support\HttpException;
use App\Support\Request;
use App\Support\Response;

/**
 * Serves files from a theme's assets directory. Themes are self-contained
 * packages, so their CSS and images live next to their templates rather than
 * in /public — this route is what makes that work.
 */
final class AssetController extends Controller
{
    private const TYPES = [
        'css' => 'text/css; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'txt' => 'text/plain; charset=UTF-8',
    ];

    /**
     * The generated stylesheet carrying the active theme's settings. Serving it
     * as a real file keeps the Content-Security-Policy free to forbid inline
     * styles, which an embedded <style> block would have required.
     */
    public function settings(Request $request): Response
    {
        $theme = (string) $request->route('theme');

        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $theme) !== 1) {
            throw HttpException::notFound();
        }

        $themes = $this->view->themes();

        if (!$themes->isInstalledOnDisk($theme)) {
            throw HttpException::notFound();
        }

        $css = $themes->customCss($theme);
        $etag = '"' . md5($css) . '"';

        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            return new Response('', 304, ['ETag' => $etag]);
        }

        return new Response($css, 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'ETag' => $etag,
        ]);
    }

    /**
     * Values that come from the database — role colours, avatar hues, bar
     * widths — served as a stylesheet rather than written into `style`
     * attributes, which the Content-Security-Policy refuses.
     */
    public function dynamic(Request $request): Response
    {
        $css = (new DynamicStyles())->css();
        $etag = '"' . md5($css) . '"';

        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            return new Response('', 304, ['ETag' => $etag]);
        }

        return new Response($css, 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
            'ETag' => $etag,
        ]);
    }

    public function show(Request $request): Response
    {
        $theme = (string) $request->route('theme');
        $path = (string) $request->route('path');

        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $theme) !== 1) {
            throw HttpException::notFound();
        }

        // Reject anything that could escape the assets directory.
        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            throw HttpException::notFound();
        }

        $base = (string) Config::get('app.paths.themes') . '/' . $theme . '/assets';
        $candidate = $base . '/' . $path;

        $realBase = realpath($base);
        $realFile = realpath($candidate);

        if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR)) {
            throw HttpException::notFound();
        }

        $extension = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));

        if (!array_key_exists($extension, self::TYPES)) {
            throw HttpException::notFound();
        }

        $contents = (string) file_get_contents($realFile);
        $etag = '"' . md5($contents) . '"';

        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            return new Response('', 304, ['ETag' => $etag]);
        }

        return new Response($contents, 200, [
            'Content-Type' => self::TYPES[$extension],
            'Cache-Control' => 'public, max-age=604800',
            'ETag' => $etag,
        ]);
    }
}
