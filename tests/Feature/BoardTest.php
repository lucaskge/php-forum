<?php

declare(strict_types=1);

use App\Repositories\UserRepository;
use App\Services\AccessControl;
use App\Services\AuthService;
use App\Support\Csrf;
use App\Support\Request;
use App\Support\Response;
use Tests\Assert;
use Tests\Harness;

/**
 * Feature tests. These boot the real kernel and dispatch real requests through
 * the router, so they exercise routing, middleware, policies, repositories and
 * templates together. They are skipped when no database is reachable.
 */
return [
    'the board index renders for a guest' => static function (): void {
        Harness::logout();
        $response = Harness::get('/');

        Assert::same(200, $response->status());
        Assert::contains('Board statistics', $response->body());
        Assert::contains('General Discussion', $response->body());
    },

    'no page ships any javascript' => static function (): void {
        foreach (['/', '/forum/networking', '/chat', '/search', '/login', '/members'] as $path) {
            $body = Harness::get($path)->body();

            Assert::notContains('<script', $body, $path . ' must contain no script tag');
            Assert::notContains('onclick=', $body, $path . ' must contain no inline handler');
            Assert::notContains('javascript:', $body, $path . ' must contain no javascript: URL');
        }
    },

    'no page relies on an inline style attribute' => static function (): void {
        // The Content-Security-Policy is `style-src 'self'`, which blocks
        // style="…" attributes as well as <style> blocks. Anything that varies
        // per row must therefore arrive as a class, or it silently does nothing
        // in the browser — which is exactly how role colours were lost once.
        Harness::loginAs('admin');

        foreach ([
            '/', '/forum/networking', '/topic/the-long-thread', '/members', '/online',
            '/chat', '/admin/roles', '/admin/users', '/admin/dashboard', '/admin',
            '/admin/themes/default/appearance', '/moderation/log', '/moderation/users',
        ] as $path) {
            $body = Harness::get($path)->body();

            Assert::notContains(' style="', $body, $path . ' must not use an inline style attribute');
        }
    },

    'role colours reach the markup as classes' => static function (): void {
        Harness::logout();

        $roles = (new App\Repositories\RoleRepository())->findBySlug('administrator');
        Assert::notNull($roles);

        $expected = 'role-colour-' . (int) $roles['id'];

        // The colour must follow the name everywhere it is rendered, not just
        // on the profile page.
        foreach (['/topic/board-rules-and-how-moderation-works-here', '/forum/announcements', '/'] as $path) {
            Assert::contains($expected, Harness::get($path)->body(), $path . ' must colour the author by role');
        }
    },

    'the generated stylesheet carries every role colour' => static function (): void {
        $response = Harness::get('/board-styles.css');

        Assert::same(200, $response->status());
        Assert::contains('text/css', $response->headers()['Content-Type'] ?? '');

        foreach ((new App\Repositories\RoleRepository())->all() as $role) {
            Assert::contains(
                '.role-colour-' . (int) $role['id'],
                $response->body(),
                'the role "' . (string) $role['slug'] . '" needs a colour class',
            );
            Assert::contains(strtolower((string) $role['colour']), $response->body());
        }
    },

    'changing a role colour changes what the stylesheet serves' => static function (): void {
        $repository = new App\Repositories\RoleRepository();
        $role = $repository->findBySlug('trusted');
        Assert::notNull($role);

        $original = (string) $role['colour'];

        try {
            $repository->update((int) $role['id'], ['colour' => '#ff0099']);

            Assert::contains('#ff0099', Harness::get('/board-styles.css')->body());
        } finally {
            $repository->update((int) $role['id'], ['colour' => $original]);
        }
    },

    'a forum lists its topics' => static function (): void {
        $response = Harness::get('/forum/networking');

        Assert::same(200, $response->status());
        Assert::contains('Debugging asymmetric routing', $response->body());
    },

    'a topic paginates instead of loading everything' => static function (): void {
        $first = Harness::get('/topic/the-long-thread');

        Assert::same(200, $first->status());
        Assert::contains('pagination', $first->body());
        Assert::contains('page=2', $first->body());

        $second = Harness::get('/topic/the-long-thread', ['page' => '2']);
        Assert::same(200, $second->status());
        Assert::true($first->body() !== $second->body(), 'page two shows different posts');
    },

    'unknown addresses return the 404 page' => static function (): void {
        $response = Harness::get('/no/such/place');

        Assert::same(404, $response->status());
        Assert::contains('Nothing at this address', $response->body());
    },

    'guests are redirected away from member areas' => static function (): void {
        Harness::logout();

        foreach (['/messages', '/notifications', '/settings/profile'] as $path) {
            Assert::same(302, Harness::get($path)->status(), $path . ' must redirect a guest');
        }
    },

    'guests cannot reach staff areas' => static function (): void {
        Harness::logout();

        Assert::same(302, Harness::get('/admin')->status());
        Assert::same(302, Harness::get('/moderation')->status());
    },

    'a member is refused the staff areas' => static function (): void {
        Harness::loginAs('pale_socket');

        Assert::same(403, Harness::get('/admin')->status(), 'members get a hard 403, not a redirect');
        Assert::same(403, Harness::get('/moderation')->status());
        Assert::same(403, Harness::get('/moderation/topic/the-long-thread')->status());
    },

    'a moderator reaches moderation but not administration' => static function (): void {
        Harness::loginAs('nullroute');

        Assert::same(200, Harness::get('/moderation')->status());
        Assert::same(200, Harness::get('/moderation/reports')->status());
        Assert::same(200, Harness::get('/moderation/log')->status());
        Assert::same(403, Harness::get('/admin')->status(), 'moderators hold no admin permission');
    },

    'an administrator reaches every panel' => static function (): void {
        Harness::loginAs('admin');

        foreach ([
            '/admin', '/admin/users', '/admin/roles', '/admin/permissions', '/admin/forums',
            '/admin/topics', '/admin/posts', '/admin/settings', '/admin/themes', '/admin/chat',
            '/admin/system', '/admin/logs', '/admin/maintenance', '/moderation', '/moderation/queue',
        ] as $path) {
            Assert::same(200, Harness::get($path)->status(), $path . ' should render for an administrator');
        }
    },

    'posting requires a valid csrf token' => static function (): void {
        Harness::loginAs('grepwire');

        $response = Harness::post('/topic/the-long-thread/reply', [
            '_token' => 'not-the-real-token',
            'content' => 'This should never be stored.',
        ]);

        Assert::same(419, $response->status(), 'a bad token is rejected');
        Assert::contains('The form expired', $response->body());
    },

    'a member can post a reply and it appears in the topic' => static function (): void {
        Harness::loginAs('grepwire');

        $marker = 'feature-test-reply-' . bin2hex(random_bytes(4));

        $response = Harness::post('/topic/the-long-thread/reply', [
            '_token' => Csrf::token(),
            'content' => $marker . ' — written by the feature test.',
        ]);

        Assert::same(302, $response->status());

        // The redirect points at the exact page the new post landed on.
        $location = $response->headers()['Location'] ?? '';
        Assert::contains('#post-', $location, 'the redirect is a permalink to the new post');

        $page = [];
        parse_str((string) parse_url($location, PHP_URL_QUERY), $page);

        $topic = Harness::get('/topic/the-long-thread', isset($page['page']) ? ['page' => (string) $page['page']] : []);
        Assert::contains($marker, $topic->body(), 'the new reply is on the page it redirected to');
    },

    'quoting pre-fills the reply form server-side' => static function (): void {
        Harness::loginAs('grepwire');

        $postId = (int) App\Support\Database::instance()->scalar(
            'SELECT p.id FROM posts p INNER JOIN topics t ON t.id = p.topic_id WHERE t.slug = :slug ORDER BY p.id ASC LIMIT 1',
            ['slug' => 'the-long-thread'],
        );

        $response = Harness::get('/topic/the-long-thread/reply', ['quote' => (string) $postId]);

        Assert::same(200, $response->status());
        Assert::contains('[quote=', $response->body(), 'the editor arrives with the quote already in it');
    },

    'a locked topic refuses replies' => static function (): void {
        Harness::loginAs('grepwire');

        $response = Harness::get('/topic/board-rules-and-how-moderation-works-here/reply');

        Assert::same(403, $response->status());
    },

    'search only returns readable content' => static function (): void {
        Harness::logout();

        $response = Harness::get('/search', ['q' => 'conntrack', 'mode' => 'posts']);

        Assert::same(200, $response->status());
        Assert::contains('Debugging asymmetric routing', $response->body());
    },

    'profiles render with their statistics' => static function (): void {
        $response = Harness::get('/user/grepwire');

        Assert::same(200, $response->status());
        Assert::contains('Statistics', $response->body());
        Assert::contains('Recent topics', $response->body());
    },

    'the chat page renders its transcript when the feature is on' => static function (): void {
        Harness::setting('chat_enabled', '1');
        Harness::logout();

        $response = Harness::get('/chat');

        Assert::same(200, $response->status());
        Assert::contains('Transcript', $response->body());
    },

    'switching the chat off closes it for everybody, administrators included' => static function (): void {
        Harness::setting('chat_enabled', '0');

        try {
            Harness::logout();
            Assert::same(503, Harness::get('/chat')->status(), 'guests are shut out');

            Harness::loginAs('pale_socket');
            Assert::same(503, Harness::get('/chat')->status(), 'members are shut out');

            Harness::loginAs('nullroute');
            Assert::same(503, Harness::get('/chat')->status(), 'moderators are shut out');

            Harness::loginAs('admin');
            Assert::same(503, Harness::get('/chat')->status(), 'the wildcard does not reopen a disabled feature');
            Assert::same(503, Harness::get('/chat/room/lobby')->status());
            Assert::same(503, Harness::post('/chat/room/lobby/send', [
                '_token' => App\Support\Csrf::token(),
                'content' => 'x',
            ])->status(), 'posting is closed too, not just the page');

            // Configuring it stays reachable; that is guarded by permission.
            Assert::same(200, Harness::get('/admin/chat')->status());
        } finally {
            Harness::setting('chat_enabled', '1');
        }
    },

    'switching registration off closes the form and the submission' => static function (): void {
        Harness::setting('registration_enabled', '0');
        Harness::clearThrottles();

        try {
            Harness::logout();
            Assert::same(403, Harness::get('/register')->status(), 'the form refuses');
            Assert::same(403, Harness::post('/register', [
                '_token' => App\Support\Csrf::token(),
                'username' => 'sneaky',
                'email' => 'sneaky@example.org',
                'password' => 'sneaky-pass-1',
                'password_confirmation' => 'sneaky-pass-1',
                'accept_rules' => '1',
            ])->status(), 'and so does a direct POST');
        } finally {
            Harness::setting('registration_enabled', '1');
        }
    },

    'switching avatar uploads off refuses the upload route' => static function (): void {
        Harness::setting('avatars_enabled', '0');

        try {
            Harness::loginAs('grepwire');
            $response = Harness::post('/settings/avatar', ['_token' => App\Support\Csrf::token()]);

            Assert::same(302, $response->status());
            Assert::contains('Avatar uploads are disabled', Harness::flashText());
        } finally {
            Harness::setting('avatars_enabled', '1');
        }
    },

    'maintenance mode holds everybody but administrators' => static function (): void {
        Harness::setting('maintenance_mode', '1');

        try {
            Harness::logout();
            Assert::same(302, Harness::get('/')->status(), 'guests are redirected to the notice');

            Harness::loginAs('nullroute');
            Assert::same(302, Harness::get('/')->status(), 'moderators too');

            Harness::loginAs('admin');
            Assert::same(200, Harness::get('/')->status(), 'administrators keep working');
        } finally {
            Harness::setting('maintenance_mode', '0');
        }
    },

    'looking at the alerts list clears the badge' => static function (): void {
        $notifications = new App\Repositories\NotificationRepository();
        $user = (new App\Repositories\UserRepository())->findByUsername('pale_socket');
        Assert::notNull($user);

        $userId = (int) $user['id'];

        $notifications->create([
            'user_id' => $userId,
            'actor_id' => null,
            'type' => 'topic.reply',
            'title' => 'Feature test alert',
            'body' => 'Queued by the test suite.',
            'url' => '/',
            'is_read' => 0,
        ]);

        Harness::loginAs('pale_socket');
        Assert::true($notifications->unreadCount($userId) > 0, 'there is something to read');

        $response = Harness::get('/notifications');

        Assert::same(200, $response->status());
        Assert::contains('marked as read just now', $response->body(), 'the page says what it did');
        Assert::contains('is-unread', $response->body(), 'and still shows which ones were new');

        Assert::same(0, $notifications->unreadCount($userId), 'the badge is cleared by looking');

        // Coming back, nothing is highlighted any more.
        Assert::notContains('is-unread', Harness::get('/notifications')->body());
    },

    'opening one alert marks it read and goes where it points' => static function (): void {
        $notifications = new App\Repositories\NotificationRepository();
        $user = (new App\Repositories\UserRepository())->findByUsername('grepwire');
        Assert::notNull($user);

        $id = $notifications->create([
            'user_id' => (int) $user['id'],
            'actor_id' => null,
            'type' => 'post.mention',
            'title' => 'Feature test mention',
            'body' => 'Queued by the test suite.',
            'url' => '/forum/networking',
            'is_read' => 0,
        ]);

        Harness::loginAs('grepwire');
        $response = Harness::get('/notifications/' . $id);

        Assert::same(302, $response->status());
        Assert::contains('forum', (string) ($response->headers()['Location'] ?? ''), 'it forwards to the target');

        $stored = $notifications->find($id);
        Assert::same(1, (int) ($stored['is_read'] ?? 0), 'and the alert is read');
    },

    'one member cannot open another member\'s alert' => static function (): void {
        $notifications = new App\Repositories\NotificationRepository();
        $owner = (new App\Repositories\UserRepository())->findByUsername('stratum');
        Assert::notNull($owner);

        $id = $notifications->create([
            'user_id' => (int) $owner['id'],
            'actor_id' => null,
            'type' => 'topic.reply',
            'title' => 'Private to stratum',
            'body' => null,
            'url' => '/',
            'is_read' => 0,
        ]);

        Harness::loginAs('quiet_fan');

        Assert::same(404, Harness::get('/notifications/' . $id)->status());
        Assert::same(0, (int) ($notifications->find($id)['is_read'] ?? 1), 'and it stays unread');
    },

    'private messages stay between their two parties' => static function (): void {
        $messageId = (int) App\Support\Database::instance()->scalar(
            'SELECT id FROM private_messages ORDER BY id ASC LIMIT 1',
        );

        Harness::loginAs('admin');
        Assert::same(200, Harness::get('/messages/' . $messageId)->status(), 'the recipient can read it');

        Harness::loginAs('quiet_fan');
        Assert::same(404, Harness::get('/messages/' . $messageId)->status(), 'nobody else can');
    },

    'a suspended account is held at the restriction notice' => static function (): void {
        Harness::loginAs('loudpacket');

        $response = Harness::get('/topic/the-long-thread/reply');

        Assert::same(302, $response->status());
        Assert::contains(
            rawurlencode('/account/restricted'),
            rawurlencode(urldecode($response->headers()['Location'] ?? '')),
        );
    },

    'theme assets are served, traversal is not' => static function (): void {
        Assert::same(200, Harness::get('/theme/default/css/board.css')->status());
        Assert::same(404, Harness::get('/theme/default/../../../.env')->status());
        Assert::same(404, Harness::get('/theme/default/../../../../etc/passwd')->status());
    },

    'the board works with no rewrite rules at all' => static function (): void {
        Harness::logout();
        Harness::urlMode(App\Support\Url::MODE_QUERY);

        try {
            // Exactly what an unprivileged server delivers: /index.php with the
            // logical path as a query parameter, no PATH_INFO, no rewriting.
            $response = Harness::raw('GET', '/index.php', ['r' => '/forum/networking']);

            Assert::same(200, $response->status());
            Assert::contains('Debugging asymmetric routing', $response->body());
            Assert::contains('index.php?r=', $response->body(), 'and every link it renders is rewrite-free');
            Assert::notContains('href="/topic/', $response->body(), 'no link assumes rewriting');
        } finally {
            Harness::urlMode(null);
        }
    },

    'the board also serves clean urls when rewriting is available' => static function (): void {
        Harness::logout();
        Harness::urlMode(App\Support\Url::MODE_PATH);

        try {
            $response = Harness::raw('GET', '/forum/networking');

            Assert::same(200, $response->status());
            Assert::contains('Debugging asymmetric routing', $response->body());
            Assert::contains('href="/topic/', $response->body(), 'links are clean paths in this mode');
            Assert::notContains('index.php?r=', $response->body());
        } finally {
            Harness::urlMode(null);
        }
    },

    'the board serves readable urls with no rewriting, through PATH_INFO' => static function (): void {
        Harness::logout();
        Harness::urlMode(App\Support\Url::MODE_PATH_INFO);

        try {
            $response = Harness::raw('GET', '/index.php/forum/networking', [], '/forum/networking');

            Assert::same(200, $response->status());
            Assert::contains('Debugging asymmetric routing', $response->body());
            Assert::contains('href="/index.php/topic/', $response->body(), 'links keep the readable shape');
            Assert::notContains('?r=', $response->body());
        } finally {
            Harness::urlMode(null);
        }
    },

    'the front controller resolves PATH_INFO even when the server omits it' => static function (): void {
        Harness::logout();

        // Some servers do not set PATH_INFO; the path is still in the URI.
        $response = Harness::raw('GET', '/index.php/forum/networking');

        Assert::same(200, $response->status());
        Assert::contains('Debugging asymmetric routing', $response->body());
    },

    'rewrite-free links never escape their slashes' => static function (): void {
        Harness::logout();
        Harness::urlMode(App\Support\Url::MODE_QUERY);

        try {
            $body = Harness::get('/forum/networking')->body();

            Assert::contains('?r=/topic/', $body, 'the path reads as a path');
            Assert::notContains('%2F', $body, 'no escaped slashes anywhere');
            Assert::notContains('%2f', $body);
        } finally {
            Harness::urlMode(null);
        }
    },

    'permission checks come from the role grid, not hardcoded names' => static function (): void {
        Harness::loginAs('grepwire');
        $access = AccessControl::instance();

        Assert::true($access->can('topic.create'));
        Assert::true($access->can('post.history.view'), 'granted through the trusted role');
        Assert::false($access->can('user.ban'));
        Assert::false($access->isAdministrator());

        Harness::loginAs('admin');
        Assert::true(AccessControl::instance()->can('anything.at.all'), 'the wildcard covers unknown slugs');
    },
];
