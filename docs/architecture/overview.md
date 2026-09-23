# Architecture overview

## Request lifecycle

```
public/index.php
  │  defines BASE_PATH, registers the autoloader
  │
  ├─ Kernel::boot()
  │    Env::load(.env)            values; a real environment variable wins over the file
  │    Config::load(/config)      the PHP files in /config, by dot notation
  │    error handler              warnings become exceptions
  │    Session::start()           strict mode, idle timeout, SameSite cookie
  │    Router                     middleware aliases, then routes/web.php
  │    View                       bound to the active ThemeManager
  │
  └─ Kernel::handle(Request)
       ├─ view->reset()           clears per-page state
       ├─ prepare()               shares viewer, settings, counters with every template;
       │                          touches the session-tracking row
       ├─ Router::dispatch()
       │    match path + method   405 when the path matches but the verb does not
       │    middleware pipeline   outermost first, innermost is the controller
       │    Controller            validate → service/repository → render
       └─ Response::send()        status, security headers, body
```

An uncaught `HttpException` becomes its error page; anything else is logged and
becomes a 500 — with the trace only when `APP_DEBUG` is on.

## Layers

| Layer | Directory | Holds | Never holds |
|---|---|---|---|
| Controllers | `app/Controllers/` | Input validation, orchestration, rendering | SQL, HTML |
| Services | `app/Services/` | Business rules, transactions, notifications | HTML, `$_GET` |
| Repositories | `app/Repositories/` | Every SQL statement | Business rules |
| Policies | `app/Policies/` | "May this viewer do this to this object?" | Role names |
| Middleware | `app/Middleware/` | Cross-cutting route guards | Business rules |
| Models | `app/Models/` | Domain enums | Behaviour |
| Support | `app/Support/` | Router, request, response, view, database, validation | Anything board-specific |
| Templates | `templates/themes/` | Presentation | Database access |

## Why it is arranged this way

**All SQL in one layer.** Every statement is parameterised; the few places
needing a dynamic fragment (a sort column, a join table) take it from a fixed
internal list. Auditing the board for injection means reading one directory.

**Services own the write paths.** Creating a post touches the posts table, the
topic's counters, the forum's counters, the author's post count, subscriptions
and notifications. Doing that in a controller means the next controller forgets
one. `TopicService::reply()` does all of it in one transaction.

**Policies, not role checks.** `if ($user['role'] === 'moderator')` scattered
around is how a permission system stops being one. Policies ask
`AccessControl`, which combines global permissions with per-forum access, so a
permission invented tomorrow works everywhere at once.

**Templates receive data.** No template opens a database connection. That is
what makes the forum logic independent of the theme, and what lets a theme
override one file and inherit the rest.

## Directory map

```
app/
  Controllers/        HTTP entry points
    Admin/            administration panel
    Moderation/       moderation panel
  Install/            the web installer (deleted after installation)
  Middleware/         csrf, auth, guest, permissions, throttle, setting, maintenance
  Models/             domain enums: UserStatus, ReportStatus, TopicFlag, …
  Policies/           TopicPolicy, PostPolicy, UserPolicy
  Repositories/       one per aggregate; all SQL
  Services/           auth, access control, topics, moderation, chat, uploads, …
    Chat/             the chat transport abstraction
  Support/            router, request/response, view, database, validation, …
config/               app, database, session, mail, uploads, security
database/
  migrations/         numbered .sql, applied in order and recorded
  seeders/
    CoreSeeder.php    the configuration a working board needs
    DatabaseSeeder.php  that, plus demo accounts and discussion
public/               the only web-reachable directory
  index.php           front controller
  install.php         the web installer; delete it once installed
  router.php          for PHP's built-in server
  uploads/avatars/    user uploads, execution disabled
routes/web.php        every route with its middleware
storage/logs/         app, security and mail logs
templates/themes/
  default/            the shipped theme: templates + assets + theme.json
tests/                unit and feature tests, and the runner
```

## The support layer

| Class | |
|---|---|
| `Kernel` | Boots and handles |
| `Router`, `Route` | Matching, groups, middleware |
| `Url` | Every link the board emits; owns the URL modes |
| `Request`, `Response` | Immutable request view; status, headers, body |
| `View` | Theme-aware template renderer, plus the helpers templates use |
| `Database` | PDO wrapper; parameterised statements, nested transactions |
| `Migrator` | Applies and records migrations. Shared by console and installer |
| `Validator` | Rule-based server-side validation |
| `Paginator` | Counts, windows, page URLs |
| `ContentFormatter` | Board markup → HTML |
| `CodeHighlighter` | Language-agnostic syntax highlighting |
| `Csrf`, `Session`, `Flash` | Token, session, one-shot messages |
| `RateLimiter` | Database-backed fixed-window throttles |
| `Str`, `Dates` | Slugs, excerpts, timezone-aware formatting |
| `Logger`, `Mailer` | Channelled logs; plain-text transactional mail |
| `HttpException` | Status + title + message, rendered as an error page |

## No dependencies

There is no Composer file and no vendor directory. The autoloader, router,
template engine, validator and test runner are part of the project.

The trade is deliberate: a few hundred lines to read and own, against a
dependency tree to audit and upgrade. For an application one person maintains
for a decade, that is the right side of the trade. For a team of twenty
shipping weekly, it would not be.
