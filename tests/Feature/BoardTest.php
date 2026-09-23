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

    'a member can be reported, not only a post' => static function (): void {
        $reports = new App\Repositories\ReportRepository();
        $reporter = (new App\Repositories\UserRepository())->findByUsername('quiet_fan');
        Assert::notNull($reporter);

        Harness::loginAs('quiet_fan');
        Harness::clearThrottles();

        Assert::same(200, Harness::get('/user/loudpacket/report')->status(), 'the form is reachable');

        $before = $reports->countByStatus('pending');

        $submit = static fn (): int => Harness::post('/user/loudpacket/report', [
            '_token' => App\Support\Csrf::token(),
            'reason' => 'abuse',
            'details' => 'Reported by the feature test, describing conduct across several topics.',
        ])->status();

        try {
            Assert::same(302, $submit());
            Assert::same($before + 1, $reports->countByStatus('pending'), 'the report reached the queue');

            // Reporting the same member twice does not pile up duplicates.
            Assert::same(302, $submit());
            Assert::same($before + 1, $reports->countByStatus('pending'), 'a second report is absorbed');
            Assert::contains('already reported', Harness::flashText());
        } finally {
            // The suite must be repeatable, and the duplicate guard would stop
            // the next run from ever creating this report again.
            App\Support\Database::instance()->execute(
                'DELETE FROM reports WHERE reporter_id = :reporter AND content_type = :type AND details LIKE :marker',
                [
                    'reporter' => (int) $reporter['id'],
                    'type' => 'user',
                    'marker' => 'Reported by the feature test%',
                ],
            );
        }
    },

    'nobody can report themselves' => static function (): void {
        Harness::loginAs('quiet_fan');

        Assert::same(403, Harness::get('/user/quiet_fan/report')->status());
    },

    'a guest cannot report anyone' => static function (): void {
        Harness::logout();

        Assert::same(302, Harness::get('/user/loudpacket/report')->status(), 'sent to sign in');
    },

    'the report queue links to what was reported' => static function (): void {
        Harness::loginAs('nullroute');

        $body = Harness::get('/moderation/reports', ['status' => 'all'])->body();

        Assert::same(200, Harness::get('/moderation/reports', ['status' => 'all'])->status());
        Assert::contains('Open the reported content', $body, 'the listing links, rather than printing an id');

        // A reported member points at their moderation record.
        Assert::contains('moderation/users/loudpacket', urldecode($body));
    },

    'a reported member resolves on the review screen' => static function (): void {
        $reports = new App\Repositories\ReportRepository();
        $paginator = $reports->paginate('all', 1, 50, '/x');
        $userReport = null;

        foreach ($paginator->items() as $report) {
            if ((string) $report['content_type'] === 'user') {
                $userReport = $report;

                break;
            }
        }

        Assert::notNull($userReport, 'the fixture has a member report');

        $content = (new App\Services\ReportService())->resolveContent($userReport);

        Assert::true($content['exists'], 'a reported member is not "content that no longer exists"');
        Assert::contains('The member', $content['label']);
        Assert::notNull($content['url']);
    },

    'one post never produces two alerts for the same person' => static function (): void {
        $db = App\Support\Database::instance();
        $users = new App\Repositories\UserRepository();
        $topics = new App\Repositories\TopicRepository();

        $topic = $topics->findBySlug('the-long-thread');
        $target = $users->findByUsername('stratum');
        $author = $users->findByUsername('grepwire');
        Assert::notNull($topic);
        Assert::notNull($target);
        Assert::notNull($author);

        $targetId = (int) $target['id'];

        // Subscribed, quoted and mentioned — three reasons, one post.
        $topics->subscribe($targetId, (int) $topic['id']);
        $db->delete('notifications', 'user_id = :user', ['user' => $targetId]);

        $first = (int) $db->scalar(
            'SELECT id FROM posts WHERE topic_id = :topic ORDER BY id ASC LIMIT 1',
            ['topic' => (int) $topic['id']],
        );

        $result = (new App\Services\TopicService())->reply(
            $topic,
            (int) $author['id'],
            '[quote=stratum;' . $first . ']quoted[/quote] and @stratum mentioned',
            '127.0.0.1',
            $targetId,
            false,
        );

        try {
            $alerts = $db->select(
                'SELECT type FROM notifications WHERE user_id = :user',
                ['user' => $targetId],
            );

            Assert::same(1, count($alerts), 'one post, one alert');
            Assert::same('post.quote', (string) $alerts[0]['type'], 'and it names the most specific reason');
        } finally {
            $db->delete('posts', 'id = :id', ['id' => $result['post_id']]);
            $db->delete('notifications', 'user_id = :user', ['user' => $targetId]);
            $topics->unsubscribe($targetId, (int) $topic['id']);
            $topics->refreshCounters((int) $topic['id']);
        }
    },

    'a private message does not also raise an alert' => static function (): void {
        $users = new App\Repositories\UserRepository();
        $notifications = new App\Repositories\NotificationRepository();
        $db = App\Support\Database::instance();

        $sender = $users->findByUsername('grepwire');
        $recipient = $users->findByUsername('quiet_fan');
        Assert::notNull($sender);
        Assert::notNull($recipient);

        $recipientId = (int) $recipient['id'];
        $db->delete('notifications', 'user_id = :user', ['user' => $recipientId]);

        $service = new App\Services\MessageService();

        try {
            // The inbox counter is the notification for a message.
            $users->update($recipientId, ['notify_messages' => 0]);
            $sent = $service->send($sender, 'quiet_fan', 'Feature test message', 'Body written by the test suite.');

            Assert::true($sent['ok'], (string) ($sent['message'] ?? ''));
            Assert::same(0, $notifications->unreadCount($recipientId), 'no second counter for the same thing');
            Assert::true(
                (new App\Repositories\MessageRepository())->unreadCount($recipientId) > 0,
                'the inbox still counts it',
            );

            // Unless the member asked for everything in one list.
            $users->update($recipientId, ['notify_messages' => 1]);
            $service->send($sender, 'quiet_fan', 'Feature test message two', 'Body written by the test suite.');

            Assert::same(1, $notifications->unreadCount($recipientId), 'opted in, so it is listed');
        } finally {
            $users->update($recipientId, ['notify_messages' => 0]);
            $db->delete('notifications', 'user_id = :user', ['user' => $recipientId]);
            $db->execute(
                'DELETE FROM private_messages WHERE recipient_id = :user AND subject LIKE :marker',
                ['user' => $recipientId, 'marker' => 'Feature test message%'],
            );
        }
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

    'a suspended member is told so on every page' => static function (): void {
        Harness::loginAs('loudpacket');

        foreach (['/', '/forum/networking', '/topic/the-long-thread', '/members'] as $path) {
            $body = Harness::get($path)->body();

            Assert::contains('account-restriction', $body, $path . ' must carry the notice');
            Assert::contains('Repeated advertising', $body, $path . ' must say why');
        }

        // A member in good standing never sees it.
        Harness::loginAs('grepwire');
        Assert::notContains('account-restriction', Harness::get('/')->body());
    },

    'a suspended member can read their own record' => static function (): void {
        Harness::loginAs('loudpacket');

        $response = Harness::get('/settings/record');

        Assert::same(200, $response->status());
        Assert::contains('Repeated advertising', $response->body(), 'the reason is there');
        Assert::contains('Warnings', $response->body(), 'and so are the warnings');
    },

    'a warning notification leads somewhere the member can read' => static function (): void {
        $users = new App\Repositories\UserRepository();
        $db = App\Support\Database::instance();
        $target = $users->findByUsername('quiet_fan');
        $moderator = $users->findByUsername('nullroute');
        Assert::notNull($target);
        Assert::notNull($moderator);

        $targetId = (int) $target['id'];
        $db->delete('notifications', 'user_id = :user', ['user' => $targetId]);

        try {
            (new App\Services\ModerationService())->warn(
                $targetId,
                (int) $moderator['id'],
                'Feature test warning',
                'Issued by the test suite.',
                1,
                30,
                '127.0.0.1',
            );

            $alert = $db->selectOne(
                'SELECT id, url FROM notifications WHERE user_id = :user ORDER BY id DESC LIMIT 1',
                ['user' => $targetId],
            );

            Assert::notNull($alert);
            Assert::notContains('/notifications', (string) $alert['url'], 'a warning must not point back at the list');

            Harness::loginAs('quiet_fan');
            $opened = Harness::get('/notifications/' . (int) $alert['id']);

            Assert::same(302, $opened->status());

            $target = urldecode((string) ($opened->headers()['Location'] ?? ''));
            Assert::contains('/settings/record', $target, 'it leads to the record');

            $record = Harness::get('/settings/record');
            Assert::contains('Feature test warning', $record->body(), 'where the warning is readable');
        } finally {
            $db->execute('DELETE FROM warnings WHERE user_id = :user AND reason = :reason', [
                'user' => $targetId,
                'reason' => 'Feature test warning',
            ]);
            $db->execute('DELETE FROM moderation_actions WHERE target_user_id = :user AND reason = :reason', [
                'user' => $targetId,
                'reason' => 'Feature test warning',
            ]);
            $db->delete('notifications', 'user_id = :user', ['user' => $targetId]);
            $users->update($targetId, ['warning_points' => 0]);
        }
    },

    'a profile shows the signature its owner set' => static function (): void {
        Harness::logout();

        $body = Harness::get('/user/stratum')->body();

        Assert::contains('Signature', $body, 'the profile has a signature section');
        Assert::contains('RAID is not a backup', $body, 'showing what the member actually set');

        // It is rendered as board markup, not printed raw.
        Assert::notContains('[i]RAID', $body);

        // Somebody without one gets no empty section.
        Assert::notContains('Signature', Harness::get('/user/dust_index')->body());
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

    'every GET form survives the rewrite-free url mode' => static function (): void {
        // A GET form discards the query string in its action and replaces it
        // with its own fields. In query mode that drops the route and the
        // submission lands on the board index — which is exactly how the
        // search box stopped working once. Every such form must carry the
        // route as a field instead.
        Harness::loginAs('admin');
        Harness::urlMode(App\Support\Url::MODE_QUERY);

        try {
            foreach ([
                '/', '/search', '/members', '/moderation/log', '/moderation/users',
                '/admin/users', '/admin/topics', '/admin/posts', '/chat/room/lobby/moderate',
            ] as $path) {
                $body = Harness::get($path)->body();

                preg_match_all('/<form[^>]*method="get"[^>]*>/i', $body, $matches);

                foreach ($matches[0] as $form) {
                    Assert::notContains(
                        '?r=',
                        $form,
                        $path . ': a GET form cannot carry the route in its action',
                    );
                }

                if ($matches[0] !== []) {
                    Assert::contains(
                        'name="r"',
                        $body,
                        $path . ': its GET form must carry the route as a hidden field',
                    );
                }
            }
        } finally {
            Harness::urlMode(null);
        }
    },

    'searching finds a post in every url mode' => static function (): void {
        foreach ([
            App\Support\Url::MODE_QUERY,
            App\Support\Url::MODE_PATH,
            App\Support\Url::MODE_PATH_INFO,
        ] as $mode) {
            Harness::logout();
            Harness::urlMode($mode);

            try {
                $response = Harness::get('/search', ['q' => 'conntrack', 'mode' => 'posts']);

                Assert::same(200, $response->status(), $mode . ': the search page answers');
                Assert::contains('Debugging asymmetric routing', $response->body(), $mode . ': and finds the post');
            } finally {
                Harness::urlMode(null);
            }
        }
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
