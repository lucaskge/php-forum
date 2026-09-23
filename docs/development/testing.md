# Testing

The board has its own runner. No PHPUnit, no Composer, no configuration file —
a test file returns an array of `description => callable`, and a test passes
when it returns without throwing.

```bash
php tests/run.php            # everything the environment allows
php tests/run.php unit       # unit only — no database needed
php tests/run.php feature    # feature only
```

Output is one line per test, then a summary. A failure prints the description,
the message and the file and line that threw.

## The two suites

**Unit** — `tests/Unit/`. Pure functions and classes with no database:
`ContentFormatterTest`, `CodeHighlighter` cases, `PaginatorTest`,
`RouterTest`, `ValidatorTest`, `UploadGuardTest`, `InstallerTest`,
`SupportTest`. These always run.

**Feature** — `tests/Feature/BoardTest.php`. These boot the real `Kernel` and
dispatch real requests through the router, so one test exercises routing,
middleware, policies, repositories and templates together. They are skipped
with a message when no seeded database is reachable, so `php tests/run.php` is
always safe to run.

Feature tests are the ones that catch what matters here. A unit test on the
router would not have caught the GET-form bug; a feature test that fetches
`/search?q=…` in all three URL modes did.

## Writing a unit test

`tests/Unit/ExampleTest.php`:

```php
<?php

declare(strict_types=1);

use App\Support\Str;
use Tests\Assert;

return [
    'slugs collapse punctuation' => static function (): void {
        Assert::same('hello-world', Str::slug('Hello, World!'));
    },

    'slugs never end in a separator' => static function (): void {
        Assert::same('trailing', Str::slug('trailing---'));
    },
];
```

The file is picked up automatically. No registration anywhere.

## Writing a feature test

Add a case to the array returned by `tests/Feature/BoardTest.php`:

```php
'a locked topic refuses replies' => static function (): void {
    Harness::loginAs('pale_socket');

    $response = Harness::post('/topic/the-locked-one/reply', ['content' => 'hello']);

    Assert::same(403, $response->status(), 'a locked topic must refuse a reply');
},
```

`Harness::post()` supplies a valid CSRF token for you; to test the CSRF guard
itself, build the request by hand as the *"posting requires a valid csrf
token"* case does.

## The harness

| Call | |
|---|---|
| `Harness::boot()` | The kernel, booted once and reused |
| `Harness::get($path, $query)` | Dispatch a GET, get back a `Response` |
| `Harness::post($path, $body)` | Dispatch a POST with a valid token |
| `Harness::raw($method, $uri, $query, $pathInfo)` | Full control, for URL-mode tests |
| `Harness::loginAs($username)` | Sign in as a seeded account |
| `Harness::logout()` | Become a guest |
| `Harness::setting($key, $value)` | Change a board setting for this test |
| `Harness::urlMode($mode)` | Switch URL mode; pass `null` to restore |
| `Harness::clearThrottles()` | Empty `rate_limits` |
| `Harness::flashText()` | The flash message the last request produced |
| `Harness::databaseReachable()`, `seeded()` | What the runner uses to decide |

## The assertions

`Assert::true`, `false`, `same`, `contains`, `notContains`, `null`, `notNull`.
Each takes a trailing message; write it as the sentence you would want to read
at 2am when it fails — *"members get a hard 403, not a redirect"* beats
*"status check"*.

## Isolation

Shared state is what makes a suite flaky, and each of these has bitten this
project once:

- **Rate limits.** Enough `post` requests in one run and later tests get 429.
  The runner calls `clearThrottles()` before the feature suite; a test that
  posts a lot should call it too.
- **Changed settings.** A test that switches a setting off must switch it back,
  in a `finally` if anything between can throw.
- **URL mode.** `Url` caches the mode statically. A test that changes it
  restores it with `Harness::urlMode(null)`, or every later test runs in the
  wrong mode.
- **View state.** Title, breadcrumbs and layout persist on the shared `View`.
  `Kernel::handle()` calls `View::reset()` per request, which is what keeps
  one test's breadcrumbs out of the next test's page.
- **Rows created by a test.** Delete them at the end. The duplicate-report
  guard means a report left behind makes the same test fail on the next run.

## Against your own database

Feature tests read, write and delete. Point them at a throwaway database,
never at a board you care about:

```bash
DB_DATABASE=coldwire_test php tests/run.php
```

or, in Docker:

```bash
docker compose run --rm -e DB_DATABASE=coldwire_test app php tests/run.php
```

## What to test

The rules a reviewer cannot see by reading a diff:

- **Who may reach what.** Guest, member, moderator, administrator, for every
  new route.
- **Settings that close a feature.** That the *route* refuses, not just that
  the menu link is gone.
- **The invariants.** No JavaScript in any page, no inline `style` attribute,
  every GET form carrying its route field — these are already tested across
  every route, and they are why the rules hold.
- **Anything that has broken once.** Every bug fixed in this project has a test
  named after the symptom, so the fix cannot silently regress.
