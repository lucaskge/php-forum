# Adding a page

A complete worked example: a **Board statistics** page at `/stats`, visible to
members who hold a new `board.stats` permission, linked from the navigation.
Every step is a real file in this project.

## 1. The controller

`app/Controllers/StatsController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\StatisticsRepository;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;

final class StatsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->view->title('Board statistics');
        $this->view->breadcrumbs([
            ['label' => 'Board index', 'url' => Url::route('home')],
            ['label' => 'Statistics'],
        ]);

        return $this->render('static/stats', [
            'totals' => (new StatisticsRepository())->totals(),
        ]);
    }
}
```

Extending `Controller` gives you `$this->view`, `$this->auth`,
`$this->access`, `$this->settings`, and the helpers `render()`, `redirect()`,
`back()`, `withErrors()` and `user()`.

`title()` sets the `<title>` and the page heading; `breadcrumbs()` fills the
trail. Both are per-request state that `View::reset()` clears.

## 2. The route

In `routes/web.php`, inside the group that already carries the global
middleware:

```php
$router->get('/stats', [StatsController::class, 'index'])
    ->middleware(['auth', 'can:board.stats'])
    ->name('stats');
```

The name is what everything else links with — never write `/stats` into a
template.

## 3. The template

`templates/themes/default/static/stats.php`. Templates are plain PHP inside the
layout; the `@var` line at the top is what gives an editor autocompletion on
`$this`:

```php
<?php /** @var App\Support\View $this */ ?>
<header class="page-head">
    <div><h1 class="page-title">Board statistics</h1>
    <p class="page-subtitle">Everything the board has accumulated so far.</p></div>
</header>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Totals</h2></header>
    <div class="panel-inset">
        <dl class="stat-list">
            <div><dt>Members</dt><dd><?= number_format((int) $totals['users']) ?></dd></div>
            <div><dt>Topics</dt><dd><?= number_format((int) $totals['topics']) ?></dd></div>
            <div><dt>Posts</dt><dd><?= number_format((int) $totals['posts']) ?></dd></div>
        </dl>
    </div>
</section>
```

Layouts available: `layouts/main` (the default), `narrow` for forms,
`minimal` for sign-in and errors, `admin` and `moderation` for the panels.
Choose another with `$this->view->layout('layouts/narrow')` in the controller.

**Escape everything** with `<?= $this->e($value) ?>`. Casting to `int` and
formatting, as above, is equally safe. The only unescaped output is content
that has already been through `$this->content()`.

## 4. The repository

`app/Repositories/StatisticsRepository.php` — all SQL lives here:

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Database;

final class StatisticsRepository
{
    /** @return array{users:int,topics:int,posts:int} */
    public function totals(): array
    {
        $db = Database::instance();

        return [
            'users'  => (int) $db->scalar("SELECT COUNT(*) FROM users WHERE status = 'active'"),
            'topics' => (int) $db->scalar('SELECT COUNT(*) FROM topics WHERE deleted_at IS NULL'),
            'posts'  => (int) $db->scalar('SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL'),
        ];
    }
}
```

If the page needed a business rule rather than a read — creating something,
updating counters, sending notifications — that would go in a service in
`app/Services/`, and the controller would call the service instead.

## 5. The permission

A migration, `database/migrations/008_stats_permission.sql`:

```sql
INSERT INTO permissions (slug, name, description, group_name, position)
VALUES ('board.stats', 'View board statistics', 'See the statistics page.', 'board', 90);

-- Grant it to every staff role that already reaches moderation.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE p.slug = 'board.stats' AND r.is_staff = 1;
```

Then `php bin/console migrate`. The permission appears on
**Admin → Roles → edit** by itself, because that screen renders from the
`permissions` table.

## 6. The navigation link

In `templates/themes/default/partials/nav-links.php`, beside the other entries.
That partial is rendered twice — inline on wide screens and inside the
`<details>` disclosure on narrow ones — so one entry here covers both menus:

```php
<?php if ($this->can('board.stats')): ?>
    <li><a href="<?= $this->e($this->route('stats')) ?>" class="<?= $this->active('/stats') ? 'is-current' : '' ?>">Statistics</a></li>
<?php endif; ?>
```

`$this->active('/stats')` marks the entry as the current page.

The other navigation partials, for when the page belongs to a panel rather than
the board: `admin-sidebar.php`, `moderation-sidebar.php`, `settings-nav.php`
and `messages-nav.php`.

`$this->can()` is the same check the middleware makes, so the link never shows
a page the member would be refused. **Both are required** — hiding a link is
presentation, and the route must refuse on its own.

## 7. The test

In `tests/Feature/BoardTest.php`, add a case to the returned array:

```php
'the statistics page is staff-only' => static function (): void {
    Harness::logout();
    Assert::same(302, Harness::get('/stats')->status(), 'a guest is sent to sign in');

    Harness::loginAs('pale_socket');
    Assert::same(403, Harness::get('/stats')->status(), 'a plain member is refused');

    Harness::loginAs('nullroute');
    $response = Harness::get('/stats');
    Assert::same(200, $response->status(), 'a moderator sees it');
    Assert::contains('Board statistics', $response->body(), 'the heading renders');
},
```

Run it: `php tests/run.php`.

## Variations

### A page with a form

Add the POST route with `csrf` (applied globally) plus `restricted` and a
throttle:

```php
$router->post('/stats/export', [StatsController::class, 'export'])
    ->middleware(['auth', 'can:board.stats', 'throttle:search'])
    ->name('stats.export');
```

In the template:

```php
<form method="post" action="<?= $this->route('stats.export') ?>">
    <?= $this->csrfField() ?>
    <button class="button" type="submit">Export</button>
</form>
```

### A page with a GET form

Use the two helpers, or it breaks in query URL mode:

```php
<form method="get" action="<?= $this->formAction('stats') ?>">
    <?= $this->routeField('stats') ?>
    <input type="text" name="from" value="<?= $this->e($from) ?>">
    <button type="submit">Filter</button>
</form>
```

### A page behind a board setting

Add the setting row, then `->middleware(['setting:stats_enabled'])`. Switching
it off then closes the route, not just the menu link.

### An admin page

Put the controller in `app/Controllers/Admin/`, the template in
`templates/themes/default/admin/`, and the route inside the existing admin
group — it inherits the prefix and `can:admin.access`. Set the layout with
`$this->view->layout('layouts/admin')`.

## Checklist

- [ ] Controller extends `Controller`, holds no SQL
- [ ] Route named, with its middleware
- [ ] Template escapes everything, uses no inline `style`, ships no JavaScript
- [ ] All SQL in a repository, every value bound
- [ ] Permission added by migration, not by hand in the database
- [ ] Link guarded by `$this->can()` *and* the route guarded by middleware
- [ ] GET forms use `formAction()` + `routeField()`
- [ ] A feature test covers who may reach it
