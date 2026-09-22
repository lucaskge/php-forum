<?php

declare(strict_types=1);

/**
 * Route table.
 *
 * Every address the board answers is declared here, with the middleware that
 * guards it. `$router` is provided by App\Support\Kernel.
 *
 * Middleware aliases:
 *   csrf         verifies the form token on POST
 *   maintenance  short-circuits to the offline notice
 *   auth         requires a signed-in account
 *   guest        requires *no* signed-in account
 *   restricted   blocks suspended/banned accounts from writing
 *   can:<slug>   requires a granular permission (| separated = any of)
 *   throttle:<n> applies the named rate limit window
 *
 * @var App\Support\Router $router
 */

use App\Controllers\Admin;
use App\Controllers\AssetController;
use App\Controllers\AuthController;
use App\Controllers\ChatController;
use App\Controllers\ForumController;
use App\Controllers\HomeController;
use App\Controllers\MessageController;
use App\Controllers\Moderation;
use App\Controllers\NotificationController;
use App\Controllers\PostController;
use App\Controllers\SearchController;
use App\Controllers\SettingsController;
use App\Controllers\TopicController;
use App\Controllers\UserController;

// ---------------------------------------------------------------------------
// Theme assets (served from the active theme package)
// ---------------------------------------------------------------------------

$router->get('/board-styles.css', [AssetController::class, 'dynamic'])->name('styles.dynamic');
$router->get('/theme/{theme}/settings.css', [AssetController::class, 'settings'])->name('theme.settings.css');
$router->get('/theme/{theme}/{path:.+}', [AssetController::class, 'show'])->name('theme.asset');

$router->group(['middleware' => ['csrf', 'maintenance']], function (App\Support\Router $router): void {
    // -----------------------------------------------------------------------
    // Public board
    // -----------------------------------------------------------------------

    $router->get('/', [HomeController::class, 'index'])->name('home');
    $router->get('/members', [HomeController::class, 'members'])->name('members');
    $router->get('/online', [HomeController::class, 'online'])->name('online');
    $router->get('/rules', [HomeController::class, 'rules'])->name('rules');
    $router->get('/help', [HomeController::class, 'help'])->name('help');
    $router->get('/maintenance', [HomeController::class, 'maintenance'])->name('maintenance');
    $router->get('/account/restricted', [HomeController::class, 'restricted'])->name('account.restricted');

    $router->get('/forum/{slug}', [ForumController::class, 'show'])->name('forum.show');
    $router->get('/forum/{forum}/topic/{topic}', [ForumController::class, 'topicRedirect'])->name('forum.topic');
    $router->post('/forums/mark-read', [ForumController::class, 'markRead'])->middleware('auth')->name('forums.mark-read');

    $router->get('/search', [SearchController::class, 'index'])->middleware('can:search.use')->name('search');
    $router->get('/recent', [SearchController::class, 'recent'])->middleware('can:search.use')->name('search.recent');

    // -----------------------------------------------------------------------
    // Topics and posts
    // -----------------------------------------------------------------------

    $router->get('/topic/{slug}', [TopicController::class, 'show'])->name('topic.show');

    $router->group(['middleware' => ['auth', 'restricted']], function (App\Support\Router $router): void {
        $router->get('/forum/{forum}/new-topic', [TopicController::class, 'createForm'])->name('topic.create');
        $router->post('/forum/{forum}/new-topic', [TopicController::class, 'store'])
            ->middleware('throttle:post')->name('topic.store');

        $router->get('/topic/{slug}/reply', [TopicController::class, 'replyForm'])->name('topic.reply');
        $router->post('/topic/{slug}/reply', [TopicController::class, 'storeReply'])
            ->middleware('throttle:post')->name('topic.reply.store');

        $router->get('/topic/{slug}/edit', [TopicController::class, 'editForm'])->name('topic.edit');
        $router->post('/topic/{slug}/edit', [TopicController::class, 'update'])->name('topic.update');

        $router->get('/topic/{slug}/delete', [TopicController::class, 'deleteForm'])->name('topic.delete');
        $router->post('/topic/{slug}/delete', [TopicController::class, 'destroy'])->name('topic.destroy');

        $router->post('/topic/{slug}/subscribe', [TopicController::class, 'subscribe'])->name('topic.subscribe');
        $router->post('/topic/{slug}/bookmark', [TopicController::class, 'bookmark'])->name('topic.bookmark');
    });

    $router->get('/post/{id:\d+}', [PostController::class, 'permalink'])->name('post.permalink');
    $router->get('/post/{id:\d+}/history', [PostController::class, 'history'])
        ->middleware('auth')->name('post.history');

    $router->group(['middleware' => ['auth', 'restricted']], function (App\Support\Router $router): void {
        $router->get('/post/{id:\d+}/edit', [PostController::class, 'editForm'])->name('post.edit');
        $router->post('/post/{id:\d+}/edit', [PostController::class, 'update'])->name('post.update');
        $router->get('/post/{id:\d+}/delete', [PostController::class, 'deleteForm'])->name('post.delete');
        $router->post('/post/{id:\d+}/delete', [PostController::class, 'destroy'])->name('post.destroy');
        $router->post('/post/{id:\d+}/restore', [PostController::class, 'restore'])->name('post.restore');
        $router->post('/post/{id:\d+}/visibility', [PostController::class, 'toggleHidden'])->name('post.visibility');

        $router->get('/post/{id:\d+}/report', [PostController::class, 'reportForm'])
            ->middleware('can:report.create')->name('post.report');
        $router->post('/post/{id:\d+}/report', [PostController::class, 'report'])
            ->middleware(['can:report.create', 'throttle:report'])->name('post.report.store');
    });

    // -----------------------------------------------------------------------
    // Accounts
    // -----------------------------------------------------------------------

    $router->group(['middleware' => 'guest'], function (App\Support\Router $router): void {
        $router->get('/login', [AuthController::class, 'loginForm'])->name('auth.login.show');
        $router->post('/login', [AuthController::class, 'login'])->name('auth.login');
        $router->get('/register', [AuthController::class, 'registerForm'])->name('auth.register.show');
        $router->post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:register')->name('auth.register');
        $router->get('/forgot-password', [AuthController::class, 'forgotForm'])->name('auth.forgot.show');
        $router->post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('auth.forgot');
        $router->get('/reset-password', [AuthController::class, 'resetForm'])->name('auth.reset.show');
        $router->post('/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset');
    });

    $router->post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('auth.logout');

    $router->get('/user/{username}', [UserController::class, 'profile'])->name('user.profile');
    $router->get('/user/{username}/topics', [UserController::class, 'topics'])->name('user.topics');
    $router->get('/user/{username}/posts', [UserController::class, 'posts'])->name('user.posts');
    $router->get('/user/{username}/activity', [UserController::class, 'activity'])->name('user.activity');

    $router->group(['prefix' => 'settings', 'middleware' => 'auth'], function (App\Support\Router $router): void {
        $router->get('/profile', [SettingsController::class, 'profileForm'])->name('settings.profile');
        $router->post('/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile.save');
        $router->get('/avatar', [SettingsController::class, 'avatarForm'])->name('settings.avatar');
        $router->post('/avatar', [SettingsController::class, 'uploadAvatar'])->name('settings.avatar.save');
        $router->post('/avatar/delete', [SettingsController::class, 'deleteAvatar'])->name('settings.avatar.delete');
        $router->get('/password', [SettingsController::class, 'passwordForm'])->name('settings.password');
        $router->post('/password', [SettingsController::class, 'updatePassword'])->name('settings.password.save');
        $router->get('/account', [SettingsController::class, 'accountForm'])->name('settings.account');
        $router->post('/account', [SettingsController::class, 'updateAccount'])->name('settings.account.save');
        $router->get('/preferences', [SettingsController::class, 'preferencesForm'])->name('settings.preferences');
        $router->post('/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences.save');
        $router->get('/subscriptions', [SettingsController::class, 'subscriptions'])->name('settings.subscriptions');
        $router->get('/bookmarks', [SettingsController::class, 'bookmarks'])->name('settings.bookmarks');
    });

    // -----------------------------------------------------------------------
    // Private messages and notifications
    // -----------------------------------------------------------------------

    $router->group(['prefix' => 'messages', 'middleware' => ['auth', 'can:message.send']], function (App\Support\Router $router): void {
        $router->get('/', [MessageController::class, 'inbox'])->name('messages.inbox');
        $router->get('/sent', [MessageController::class, 'sent'])->name('messages.sent');
        $router->get('/compose', [MessageController::class, 'composeForm'])->name('messages.compose');
        $router->post('/compose', [MessageController::class, 'send'])
            ->middleware(['restricted', 'throttle:message'])->name('messages.send');
        $router->get('/{id:\d+}', [MessageController::class, 'show'])->name('messages.show');
        $router->post('/{id:\d+}/read', [MessageController::class, 'toggleRead'])->name('messages.read');
        $router->post('/{id:\d+}/delete', [MessageController::class, 'destroy'])->name('messages.delete');
    });

    $router->group(['prefix' => 'notifications', 'middleware' => 'auth'], function (App\Support\Router $router): void {
        $router->get('/', [NotificationController::class, 'index'])->name('notifications');
        $router->get('/{id:\d+}', [NotificationController::class, 'open'])->name('notifications.open');
        $router->post('/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        $router->post('/clear', [NotificationController::class, 'clear'])->name('notifications.clear');
        $router->post('/{id:\d+}/delete', [NotificationController::class, 'destroy'])->name('notifications.delete');
    });

    // -----------------------------------------------------------------------
    // Chat
    // -----------------------------------------------------------------------

    // `setting:chat_enabled` closes every one of these when an administrator
    // switches the chat off — staff and administrators included.
    $router->group(['middleware' => 'setting:chat_enabled'], function (App\Support\Router $router): void {
        $router->get('/chat', [ChatController::class, 'index'])->name('chat');
        $router->get('/chat/room/{room}', [ChatController::class, 'index'])->name('chat.room');
        $router->post('/chat/room/{room}/send', [ChatController::class, 'send'])
            ->middleware(['auth', 'restricted', 'can:chat.post', 'throttle:chat'])->name('chat.send');
        $router->post('/chat/room/{room}/message/{id:\d+}/delete', [ChatController::class, 'deleteMessage'])
            ->middleware(['auth', 'can:chat.moderate'])->name('chat.message.delete');
        $router->get('/chat/room/{room}/moderate', [ChatController::class, 'moderateForm'])
            ->middleware(['auth', 'can:chat.moderate'])->name('chat.moderate');
        $router->post('/chat/room/{room}/moderate', [ChatController::class, 'moderate'])
            ->middleware(['auth', 'can:chat.moderate'])->name('chat.moderate.apply');
    });

    // -----------------------------------------------------------------------
    // Moderation
    // -----------------------------------------------------------------------

    $router->group([
        'prefix' => 'moderation',
        'middleware' => ['auth', 'can:moderation.access'],
    ], function (App\Support\Router $router): void {
        $router->get('/', [Moderation\DashboardController::class, 'index'])->name('moderation');
        $router->get('/queue', [Moderation\DashboardController::class, 'queue'])->name('moderation.queue');

        $router->get('/reports', [Moderation\ReportController::class, 'index'])
            ->middleware('can:report.view')->name('moderation.reports');
        $router->get('/reports/{id:\d+}', [Moderation\ReportController::class, 'show'])
            ->middleware('can:report.view')->name('moderation.report');
        $router->post('/reports/{id:\d+}', [Moderation\ReportController::class, 'handle'])
            ->middleware('can:report.handle')->name('moderation.report.handle');

        $router->get('/users', [Moderation\UserController::class, 'index'])->name('moderation.users');
        $router->get('/users/{username}', [Moderation\UserController::class, 'show'])->name('moderation.user');
        $router->post('/users/{username}/warn', [Moderation\UserController::class, 'warn'])
            ->middleware('can:user.warn')->name('moderation.user.warn');
        $router->post('/users/{username}/suspend', [Moderation\UserController::class, 'suspend'])
            ->middleware('can:user.suspend')->name('moderation.user.suspend');
        $router->post('/users/{username}/ban', [Moderation\UserController::class, 'ban'])
            ->middleware('can:user.ban')->name('moderation.user.ban');
        $router->post('/users/{username}/lift', [Moderation\UserController::class, 'lift'])
            ->middleware('can:user.suspend|user.ban')->name('moderation.user.lift');
        $router->post('/users/{username}/notes', [Moderation\UserController::class, 'addNote'])->name('moderation.user.note');
        $router->post('/users/{username}/notes/{note:\d+}/delete', [Moderation\UserController::class, 'deleteNote'])
            ->name('moderation.user.note.delete');

        $router->get('/log', [Moderation\LogController::class, 'index'])
            ->middleware('can:moderation.log.view')->name('moderation.log');

        $router->get('/topic/{slug}', [Moderation\TopicController::class, 'panel'])->name('moderation.topic');
        $router->post('/topic/{slug}/flag', [Moderation\TopicController::class, 'flag'])->name('moderation.topic.flag');
        $router->post('/topic/{slug}/move', [Moderation\TopicController::class, 'move'])->name('moderation.topic.move');
        $router->get('/topic/{slug}/merge', [Moderation\TopicController::class, 'mergeForm'])->name('moderation.topic.merge');
        $router->post('/topic/{slug}/merge', [Moderation\TopicController::class, 'merge'])->name('moderation.topic.merge.apply');
        $router->get('/topic/{slug}/split', [Moderation\TopicController::class, 'splitForm'])->name('moderation.topic.split');
        $router->post('/topic/{slug}/split', [Moderation\TopicController::class, 'split'])->name('moderation.topic.split.apply');
        $router->post('/topic/{slug}/restore', [Moderation\TopicController::class, 'restore'])->name('moderation.topic.restore');
    });

    // -----------------------------------------------------------------------
    // Administration
    // -----------------------------------------------------------------------

    $router->group([
        'prefix' => 'admin',
        'middleware' => ['auth', 'can:admin.access'],
    ], function (App\Support\Router $router): void {
        $router->get('/', [Admin\DashboardController::class, 'index'])->name('admin');

        // Users, roles, bans
        $router->get('/users', [Admin\UserController::class, 'index'])
            ->middleware('can:admin.users')->name('admin.users');
        $router->get('/users/{id:\d+}', [Admin\UserController::class, 'edit'])
            ->middleware('can:admin.users')->name('admin.user.edit');
        $router->post('/users/{id:\d+}', [Admin\UserController::class, 'update'])
            ->middleware('can:admin.users')->name('admin.user.update');
        $router->post('/users/{id:\d+}/password', [Admin\UserController::class, 'resetPassword'])
            ->middleware('can:admin.users')->name('admin.user.password');
        $router->post('/users/{id:\d+}/avatar', [Admin\UserController::class, 'removeAvatar'])
            ->middleware('can:admin.users')->name('admin.user.avatar');
        $router->get('/users/{id:\d+}/delete', [Admin\UserController::class, 'deleteForm'])
            ->middleware('can:admin.users')->name('admin.user.delete');
        $router->post('/users/{id:\d+}/delete', [Admin\UserController::class, 'destroy'])
            ->middleware('can:admin.users')->name('admin.user.destroy');

        $router->get('/bans', [Admin\UserController::class, 'bans'])
            ->middleware('can:admin.users')->name('admin.bans');
        $router->post('/bans/{id:\d+}/lift', [Admin\UserController::class, 'liftBan'])
            ->middleware('can:admin.users')->name('admin.ban.lift');

        $router->get('/roles', [Admin\RoleController::class, 'index'])
            ->middleware('can:admin.roles')->name('admin.roles');
        $router->get('/roles/new', [Admin\RoleController::class, 'createForm'])
            ->middleware('can:admin.roles')->name('admin.role.create');
        $router->post('/roles/new', [Admin\RoleController::class, 'store'])
            ->middleware('can:admin.roles')->name('admin.role.store');
        $router->get('/roles/{id:\d+}', [Admin\RoleController::class, 'edit'])
            ->middleware('can:admin.roles')->name('admin.role.edit');
        $router->post('/roles/{id:\d+}', [Admin\RoleController::class, 'update'])
            ->middleware('can:admin.roles')->name('admin.role.update');
        $router->post('/roles/{id:\d+}/delete', [Admin\RoleController::class, 'destroy'])
            ->middleware('can:admin.roles')->name('admin.role.destroy');
        $router->get('/permissions', [Admin\RoleController::class, 'permissions'])
            ->middleware('can:admin.roles')->name('admin.permissions');

        // Board structure
        $router->get('/forums', [Admin\ForumController::class, 'index'])
            ->middleware('can:admin.forums')->name('admin.forums');
        $router->post('/forums/order', [Admin\ForumController::class, 'reorder'])
            ->middleware('can:admin.forums')->name('admin.forums.order');
        $router->post('/forums/recount', [Admin\ForumController::class, 'recount'])
            ->middleware('can:admin.forums')->name('admin.forums.recount');
        $router->get('/forums/category', [Admin\ForumController::class, 'categoryForm'])
            ->middleware('can:admin.forums')->name('admin.category.form');
        $router->post('/forums/category', [Admin\ForumController::class, 'saveCategory'])
            ->middleware('can:admin.forums')->name('admin.category.save');
        $router->post('/forums/category/{id:\d+}/delete', [Admin\ForumController::class, 'deleteCategory'])
            ->middleware('can:admin.forums')->name('admin.category.delete');
        $router->get('/forums/forum', [Admin\ForumController::class, 'forumForm'])
            ->middleware('can:admin.forums')->name('admin.forum.form');
        $router->post('/forums/forum', [Admin\ForumController::class, 'saveForum'])
            ->middleware('can:admin.forums')->name('admin.forum.save');
        $router->post('/forums/forum/{id:\d+}/delete', [Admin\ForumController::class, 'deleteForum'])
            ->middleware('can:admin.forums')->name('admin.forum.delete');
        $router->get('/forums/forum/{id:\d+}/permissions', [Admin\ForumController::class, 'permissionsForm'])
            ->middleware('can:admin.forums')->name('admin.forum.permissions');
        $router->post('/forums/forum/{id:\d+}/permissions', [Admin\ForumController::class, 'savePermissions'])
            ->middleware('can:admin.forums')->name('admin.forum.permissions.save');

        // Content
        $router->get('/topics', [Admin\ContentController::class, 'topics'])
            ->middleware('can:admin.content')->name('admin.topics');
        $router->post('/topics/{id:\d+}/purge', [Admin\ContentController::class, 'purgeTopic'])
            ->middleware('can:admin.content')->name('admin.topic.purge');
        $router->post('/topics/{id:\d+}/restore', [Admin\ContentController::class, 'restoreTopic'])
            ->middleware('can:admin.content')->name('admin.topic.restore');
        $router->get('/posts', [Admin\ContentController::class, 'posts'])
            ->middleware('can:admin.content')->name('admin.posts');
        $router->post('/posts/{id:\d+}/purge', [Admin\ContentController::class, 'purgePost'])
            ->middleware('can:admin.content')->name('admin.post.purge');
        $router->post('/posts/{id:\d+}/restore', [Admin\ContentController::class, 'restorePost'])
            ->middleware('can:admin.content')->name('admin.post.restore');

        // Settings, themes, chat, system
        $router->get('/settings', [Admin\SettingController::class, 'index'])
            ->middleware('can:admin.settings')->name('admin.settings');
        $router->get('/settings/{group}', [Admin\SettingController::class, 'index'])
            ->middleware('can:admin.settings')->name('admin.settings.group');
        $router->post('/settings/{group}', [Admin\SettingController::class, 'update'])
            ->middleware('can:admin.settings')->name('admin.settings.save');

        $router->get('/themes', [Admin\ThemeController::class, 'index'])
            ->middleware('can:admin.themes')->name('admin.themes');
        $router->post('/themes/sync', [Admin\ThemeController::class, 'synchronise'])
            ->middleware('can:admin.themes')->name('admin.themes.sync');
        $router->get('/themes/{slug}', [Admin\ThemeController::class, 'show'])
            ->middleware('can:admin.themes')->name('admin.theme');
        $router->get('/themes/{slug}/appearance', [Admin\ThemeController::class, 'appearance'])
            ->middleware('can:admin.themes')->name('admin.theme.appearance');
        $router->post('/themes/{slug}/appearance', [Admin\ThemeController::class, 'saveAppearance'])
            ->middleware('can:admin.themes')->name('admin.theme.appearance.save');
        $router->post('/themes/{slug}/appearance/reset', [Admin\ThemeController::class, 'resetAppearance'])
            ->middleware('can:admin.themes')->name('admin.theme.appearance.reset');
        $router->post('/themes/{slug}/activate', [Admin\ThemeController::class, 'activate'])
            ->middleware('can:admin.themes')->name('admin.theme.activate');
        $router->post('/themes/{slug}/toggle', [Admin\ThemeController::class, 'toggle'])
            ->middleware('can:admin.themes')->name('admin.theme.toggle');

        $router->get('/chat', [Admin\ChatController::class, 'index'])
            ->middleware('can:admin.chat')->name('admin.chat');
        $router->get('/chat/room', [Admin\ChatController::class, 'roomForm'])
            ->middleware('can:admin.chat')->name('admin.chat.room');
        $router->post('/chat/room', [Admin\ChatController::class, 'saveRoom'])
            ->middleware('can:admin.chat')->name('admin.chat.room.save');
        $router->post('/chat/room/{id:\d+}/delete', [Admin\ChatController::class, 'deleteRoom'])
            ->middleware('can:admin.chat')->name('admin.chat.room.delete');
        $router->get('/chat/restrictions', [Admin\ChatController::class, 'restrictions'])
            ->middleware('can:admin.chat')->name('admin.chat.bans');
        $router->post('/chat/restrictions/{id:\d+}/lift', [Admin\ChatController::class, 'liftRestriction'])
            ->middleware('can:admin.chat')->name('admin.chat.ban.lift');

        $router->get('/system', [Admin\SystemController::class, 'info'])
            ->middleware('can:admin.system')->name('admin.system');
        $router->get('/logs', [Admin\SystemController::class, 'logs'])
            ->middleware('can:admin.system')->name('admin.logs');
        $router->post('/logs/clear', [Admin\SystemController::class, 'clearLog'])
            ->middleware('can:admin.system')->name('admin.logs.clear');
        $router->get('/maintenance', [Admin\SystemController::class, 'maintenance'])
            ->middleware('can:admin.system')->name('admin.maintenance');
        $router->post('/maintenance/task', [Admin\SystemController::class, 'runTask'])
            ->middleware('can:admin.system')->name('admin.maintenance.task');
    });
});
