<?php

declare(strict_types=1);

use App\Support\Route;
use App\Support\Url;
use Tests\Assert;

return [
    'route matches a literal path' => static function (): void {
        $route = new Route(['GET'], '/forum', ['X', 'y']);

        Assert::same([], $route->match('/forum'), 'literal path matches with no parameters');
        Assert::null($route->match('/forum/general'), 'a deeper path must not match');
    },

    'route captures named parameters' => static function (): void {
        $route = new Route(['GET'], '/forum/{slug}', ['X', 'y']);

        Assert::same(['slug' => 'general'], $route->match('/forum/general'));
        Assert::null($route->match('/forum/general/extra'), 'parameters never span a slash');
    },

    'route honours inline constraints' => static function (): void {
        $route = new Route(['GET'], '/post/{id:\d+}/edit', ['X', 'y']);

        Assert::same(['id' => '42'], $route->match('/post/42/edit'));
        Assert::null($route->match('/post/abc/edit'), 'a non-numeric id must be rejected');
    },

    'route captures several parameters' => static function (): void {
        $route = new Route(['GET'], '/chat/room/{room}/message/{id:\d+}/delete', ['X', 'y']);

        Assert::same(
            ['room' => 'lobby', 'id' => '7'],
            $route->match('/chat/room/lobby/message/7/delete'),
        );
    },

    'route method matching is case-insensitive' => static function (): void {
        $route = new Route(['GET', 'HEAD'], '/', ['X', 'y']);

        Assert::true($route->acceptsMethod('get'));
        Assert::false($route->acceptsMethod('POST'));
    },

    'named routes generate urls in path mode' => static function (): void {
        (new Route(['GET'], '/topic/{slug}', ['X', 'y']))->name('test.topic.show');
        Url::useMode(Url::MODE_PATH);

        Assert::same('/topic/hello-world', Url::route('test.topic.show', ['slug' => 'hello-world']));
    },

    'named routes generate rewrite-free urls in query mode' => static function (): void {
        (new Route(['GET'], '/topic/{slug}', ['X', 'y']))->name('test.topic.show');
        Url::useMode(Url::MODE_QUERY);

        $url = Url::route('test.topic.show', ['slug' => 'hello-world']);

        Assert::same('/index.php?r=/topic/hello-world', $url, 'query mode needs no server rewrite rules');

        // Slashes are legal in a query string. Escaping them to %2F would turn
        // a readable address into noise for no gain.
        Assert::notContains('%2F', $url);
        Assert::notContains('%2f', $url);
    },

    'pathinfo mode is readable and still needs no rewriting' => static function (): void {
        (new Route(['GET'], '/topic/{slug}', ['X', 'y']))->name('test.topic.show');
        Url::useMode(Url::MODE_PATH_INFO);

        Assert::same(
            '/index.php/topic/hello-world',
            Url::route('test.topic.show', ['slug' => 'hello-world']),
        );

        Assert::same('/index.php', Url::to('/'));
    },

    'an unknown mode falls back to the one that always works' => static function (): void {
        Url::useMode('something-else');

        Assert::same(Url::MODE_QUERY, Url::mode());
    },

    'the index needs no route parameter in query mode' => static function (): void {
        Url::useMode(Url::MODE_QUERY);

        Assert::same('/index.php', Url::to('/'));
    },

    'parameters are escaped exactly once' => static function (): void {
        (new Route(['GET'], '/user/{username}', ['X', 'y']))->name('test.user');

        Url::useMode(Url::MODE_PATH);
        Assert::same('/user/a%20b', Url::route('test.user', ['username' => 'a b']));

        Url::useMode(Url::MODE_QUERY);
        Assert::same('/index.php?r=/user/a%20b', Url::route('test.user', ['username' => 'a b']));
        Assert::notContains('%25', Url::route('test.user', ['username' => 'a b']), 'never double-encoded');
    },

    'a parameter cannot inject extra path segments' => static function (): void {
        (new Route(['GET'], '/user/{username}', ['X', 'y']))->name('test.user');
        Url::useMode(Url::MODE_PATH);

        Assert::same('/user/..admin', Url::route('test.user', ['username' => '../admin']));
    },

    'query and fragment are carried in both modes' => static function (): void {
        (new Route(['GET'], '/topic/{slug}', ['X', 'y']))->name('test.topic.show');

        Url::useMode(Url::MODE_PATH);
        Assert::same(
            '/topic/x?page=2#post-9',
            Url::route('test.topic.show', ['slug' => 'x'], ['page' => 2], 'post-9'),
        );

        Url::useMode(Url::MODE_QUERY);
        $url = Url::route('test.topic.show', ['slug' => 'x'], ['page' => 2], 'post-9');
        Assert::contains('page=2', $url);
        Assert::contains('#post-9', $url);
        Assert::contains('index.php', $url);
    },

    'unfilled placeholders never leak into a url' => static function (): void {
        (new Route(['GET'], '/forum/{slug}', ['X', 'y']))->name('test.forum');

        Assert::notContains('{', Url::route('test.forum'));
    },

    'query strings drop empty values' => static function (): void {
        Url::useMode(Url::MODE_PATH);

        Assert::same('/search?q=dns', Url::withQuery('/search', ['q' => 'dns', 'author' => '', 'page' => null]));
        Assert::same('/search', Url::withQuery('/search', ['q' => '']));
    },

    'query strings append to a url that already has one' => static function (): void {
        Assert::same(
            '/index.php?r=/search&page=2',
            Url::withQuery('/index.php?r=/search', ['page' => 2]),
            'pagination must not break the routing parameter',
        );

        Assert::same(
            '/index.php?r=/t&page=2#post-3',
            Url::withQuery('/index.php?r=/t#post-3', ['page' => 2]),
            'a fragment stays at the end',
        );
    },
];
