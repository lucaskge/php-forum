# Coldwire

A forum platform written in plain PHP 8.1+ with MySQL/MariaDB. Server-rendered,
traditional board layout, granular permissions, a real moderation trail — and
**no JavaScript anywhere**. Not one `<script>` tag, not one inline handler.

Everything that would normally need scripting is done with HTML and CSS: the
mobile menu is a `<details>` disclosure, confirmations are real pages, quoting
is a GET followed by a POST, and the chat transcript is whatever the server
rendered on the last request.

---

## Full documentation

This README is the tour. The complete documentation lives in [`docs/`](docs/) —
installation, every screen, moderation, administration, the architecture, how to
add a page, and a generated reference of every route, permission, setting and
command:

The files read fine as plain Markdown, starting at
[`docs/index.md`](docs/index.md). To browse them with search and navigation,
with nothing to install on the host:

```bash
docker compose --profile docs up -d docs
# http://127.0.0.1:8100
```

With Python available, `pip install mkdocs mkdocs-material && mkdocs serve`
does the same thing on port 8000.

---

## The two servers

The board and the documentation are two containers. `make` wraps the compose
commands so you do not have to remember them:

```bash
make            # the list of targets
make up         # board          → http://127.0.0.1:8080
make docs       # documentation  → http://127.0.0.1:8100
make stop       # stop both, keep them ready
make start      # start both again, instantly
make status     # what is running, and on which ports
make down       # stop and remove the containers
```

| Want to | Run | Long form |
|---|---|---|
| Start the board | `make up` | `docker compose up -d app` |
| Start the docs | `make docs` | `docker compose --profile docs up -d docs` |
| Stop just the docs | `make docs-stop` | `docker compose stop docs` |
| Stop both, keep them | `make stop` | `docker compose --profile docs stop` |
| Start both again | `make start` | `docker compose --profile docs start` |
| Restart both | `make restart` | `docker compose --profile docs restart` |
| Remove everything | `make down` | `docker compose --profile docs down` |
| Watch the logs | `make logs` | `docker compose --profile docs logs -f` |

**`stop` versus `down`.** `stop` halts the containers and keeps them, so
`make start` is instant. `down` removes them and the network; the next `make up`
recreates them, which takes a few seconds. Neither touches the database — that
is your own MySQL, outside this project — and neither touches your files.

> **Why the targets pass `--profile docs`.** The documentation service sits
> behind a compose profile so it never starts with a plain `docker compose up`.
> The catch: **`docker compose down` on its own leaves the docs container
> running**, then fails to remove the network with `Resource is still in use`.
> The profile has to be named for a clean sweep — `make down` does it for you.

`make` also wraps the commands you run most often, each against the app
container so no PHP on the host is needed:

```bash
make migrate                  # apply pending migrations
make reference                # regenerate docs/reference/ from the live code
make test                     # the suite, against the scratch database
make console CMD="routes"     # any console command
make shell                    # a shell inside the app container
```

Nothing is hidden: every target is one line in the [`Makefile`](Makefile) with
the equivalent compose command beside it. With PHP on the host,
`php bin/console serve` and `mkdocs serve` do the same two jobs without Docker.

---

## Contents

- [The two servers](#the-two-servers)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Console commands](#console-commands)
- [Demo accounts](#demo-accounts)
- [Directory structure](#directory-structure)
- [Architecture](#architecture)
- [Authentication](#authentication)
- [Permissions](#permissions)
- [Extending the board](#extending-the-board)
- [The theme system](#the-theme-system)
- [Creating a theme](#creating-a-theme)
- [Running the board](#running-the-board)
- [Chat architecture](#chat-architecture)
- [Security](#security)
- [Tests](#tests)
- [Deployment](#deployment)

---

## Requirements

The list is short on purpose. If you do not administer the server you cannot add
an extension, so anything the board can work around must not stop you.

**Required — nothing works without these:**

| Requirement | Why |
|-------------|-----|
| PHP 8.1 or newer | The code uses enums, readonly properties and `never`. The suite runs on 8.1 and 8.3 |
| `pdo_mysql` | How the board reaches MySQL or MariaDB. No way around it |
| `mbstring` | Counts and cuts non-ASCII text. Part of the standard PHP build |
| MySQL 8.0+ / MariaDB 10.6+ | The schema is tested against both |

**Optional — each costs you one thing and nothing else:**

| Requirement | Without it |
|-------------|------------|
| `gd` **or** `imagick` | No avatar uploads; everyone keeps the generated monogram. Either library satisfies it |
| `fileinfo` | One of several cross-checks on uploads is skipped; the rest, including re-encoding, still apply |
| Writable `storage/logs` | The board runs but keeps no record of errors or security events |
| Writable `public/uploads/avatars` | No avatar uploads. Turn them off in the settings and it stops mattering |
| Writable project root | The installer shows you the `.env` to save by hand instead of writing it |

`json` is not listed because it cannot be disabled in PHP 8. Any web server will
do: Apache, nginx, or PHP's built-in server for development — and with the
default URL mode, no rewrite rules either.

The installer checks all of this in the browser and tells you what each failure
costs, so you can find out whether a host will work by uploading the files and
opening one page.

Timestamps are stored in UTC throughout: the connection pins its session zone to
`+00:00`, so column defaults and the `UTC_TIMESTAMP()` comparisons in queries
agree no matter how the server itself is configured.

There are **no Composer dependencies**. The project ships its own PSR-4
autoloader, router, template engine, validator and test runner, so deployment is
a file copy.

---

## Installation

### Through the browser, with no terminal at all

Upload the project to your server, point the document root at `/public`, and
open **`/install.php`**. Three screens:

1. **Requirements** — PHP version, extensions and writable directories, each
   with what it is needed for. Optional failures (like `gd`, needed only for
   avatar uploads) are warnings rather than blockers.
2. **Database** — host, port, **database name**, user and password. The
   connection is tested before anything is written. The database is created for
   you if the account may create databases; on shared hosting it usually may
   not, so create it in your panel and put the name here — the installer says
   exactly that rather than failing obscurely.
3. **Board and administrator** — board name, address, which of the three
   address styles to use, and the first administrator account.

It then writes `.env` (generating a fresh `APP_KEY`), applies the migrations,
seeds the configuration — the permission grid, the roles, the board settings,
the theme record, a starter category with two forums and a chat room — and
creates the administrator. No demo content: just a board ready to use.

If the project directory is not writable, the installer shows you the exact
`.env` to save by hand instead of failing.

**The last screen has a button that deletes the installer.** Use it. An
installer left reachable on a live site is the one page that can rewrite your
configuration. It also refuses to run once `storage/installed.lock` exists or
the database already holds accounts, and it says which files it could not remove
rather than claiming success.

> Upload and install in one sitting. Between the upload and the installation,
> `install.php` is reachable by anyone who finds it — whoever runs it first
> becomes the administrator. This is true of every installer of this kind.

### From the command line

```bash
# 1. Get the code and configure it
cp .env.example .env
php bin/console key:generate

# 2. Point .env at your database (see Configuration below), then:
php bin/console install        # creates the database, migrates and seeds

# 3. Run it
php bin/console serve          # http://127.0.0.1:8080
```

### With Docker, if you have no PHP locally

The compose file runs **only the application**; the database is whatever
`DB_HOST` in `.env` points at. That way it never fights with a MySQL you already
have.

**Using a MySQL/MariaDB you already run** (on the host, or in another container
that publishes a port) — create a database and a user for the board, then point
`.env` at it through `host.docker.internal`:

```sql
CREATE DATABASE coldwire CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'coldwire'@'%' IDENTIFIED BY 'change-me';
GRANT ALL PRIVILEGES ON coldwire.* TO 'coldwire'@'%';
```

```dotenv
DB_HOST=host.docker.internal
DB_PORT=3306
DB_DATABASE=coldwire
DB_USERNAME=coldwire
DB_PASSWORD=change-me
```

```bash
docker compose up -d
docker compose run --rm app php bin/console key:generate
docker compose run --rm app php bin/console install
# http://127.0.0.1:8080
```

**No database of your own?** Start the bundled one alongside it. It publishes
host port **3307**, so it cannot collide with a server already listening on
3306:

```bash
docker compose -f docker-compose.yml -f docker-compose.db.yml up -d
docker compose -f docker-compose.yml -f docker-compose.db.yml run --rm app php bin/console install
```

with `DB_HOST=db`, `DB_PORT=3306`, `DB_USERNAME=coldwire`, `DB_PASSWORD=secret`
in `.env` (3306 is the port *inside* the compose network).

The compose files are a development convenience only; production deployment is a
normal PHP application (see [Deployment](#deployment)).

`install` is `migrate:fresh` followed by `db:seed`. On an existing board use
`php bin/console migrate` instead — it applies only what has not run yet and
never touches your data.

The `install` command creates the database if it does not exist, so the account
in `.env` needs `CREATE` rights the first time (or create the schema yourself and
grant the usual `SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP,
REFERENCES`).

### Directory permissions

Two directories should be writable by the web server user. Neither is fatal —
see the table above for what you lose:

```bash
chmod -R 775 storage/logs public/uploads
```

---

## Configuration

Everything environment-specific lives in `.env`; everything board-specific lives
in the database and is edited under **Admin → Site settings**.

### Environment variables (`.env`)

| Variable | Default | Meaning |
|----------|---------|---------|
| `APP_NAME` | `Coldwire` | Fallback name before the database is reachable |
| `APP_ENV` | `production` | `production` or `development` |
| `APP_DEBUG` | `false` | Shows stack traces on the 500 page. Never enable in production |
| `APP_URL` | `http://localhost:8080` | Used for canonical URLs and password-reset links |
| `APP_KEY` | — | 64 hex chars; `php bin/console key:generate` writes it |
| `APP_TIMEZONE` | `UTC` | Storage timezone. Members pick their own for display |
| `APP_URL_MODE` | `query` | `path`, `pathinfo` or `query` — see [Routing](#routing) |
| `APP_ENTRYPOINT` | `/index.php` | The script links point at in `query` mode |
| `DB_HOST` `DB_PORT` `DB_DATABASE` `DB_USERNAME` `DB_PASSWORD` | — | Connection details |
| `SESSION_NAME` | `coldwire_session` | Cookie name |
| `SESSION_LIFETIME` | `7200` | Idle timeout in seconds |
| `SESSION_SECURE` | `false` | **Set to `true` when serving over HTTPS** |
| `SESSION_SAMESITE` | `Lax` | Cookie SameSite policy |
| `MAIL_TRANSPORT` | `log` | `log` writes to `storage/logs/mail.log`; `mail` uses PHP's `mail()` |
| `MAIL_FROM`, `MAIL_FROM_NAME` | — | Envelope sender for transactional mail |
| `UPLOAD_MAX_AVATAR_BYTES` | `524288` | Avatar size ceiling (512 KB) |

> In development `MAIL_TRANSPORT=log` is what you want: password-reset links
> appear in `storage/logs/mail.log` instead of needing an MTA.

### Board settings (database)

Edited in the admin panel, applied immediately, grouped as:

- **General** — board name, tagline, meta description, announcement banner,
  board rules, contact address, maintenance mode and its message.
- **Registration** — open/closed plus the closed message, avatar uploads,
  signature length.
- **Forums** — topics per page, posts per page, rows per page elsewhere, the
  window that counts somebody as "online".
- **Posting** — minimum post length, self-edit window, minimum search term.
- **Chat** — enabled, rules, message length, slow mode, history depth, presence
  window, transport.

Static configuration (throttles, password policy, upload rules, response
headers) lives in `/config` and is read-only at runtime.

---

## Console commands

```
php bin/console migrate            apply pending migrations
php bin/console migrate:fresh      drop every table and migrate from zero
php bin/console db:seed            load the demo data
php bin/console install            migrate:fresh + db:seed
php bin/console key:generate       write a new APP_KEY into .env
php bin/console theme:sync         register themes found on disk
php bin/console user:promote NAME  give an account the administrator role
php bin/console maintenance:run    purge stale sessions and throttles, expire suspensions
php bin/console routes             list every route with its middleware
php bin/console docs:reference     regenerate docs/reference/ from the live code
php bin/console serve [host:port]  run the built-in server on /public
```

Run it with no argument for the same list — it is printed from
`App\Support\DocsReference::COMMANDS`, the table
[`docs/reference/console.md`](docs/reference/console.md) is generated from, so
the tool and its documentation cannot disagree.

`php bin/console routes` is the fastest way to see what the board exposes and
which guard protects each address.

---

## Demo accounts

Created by `db:seed`. **Development credentials — change or remove them before
exposing the board.**

| Username | Password | Role | Use for |
|----------|----------|------|---------|
| `admin` | `coldwire-admin-1` | Administrator | Everything, including `/admin` |
| `nullroute` | `coldwire-mod-1` | Moderator | Reports, topic tools, member actions |
| `hexdump` | `coldwire-mod-2` | Moderator | A second moderator |
| `grepwire` | `coldwire-user-1` | Trusted member | A member with one extra permission |
| `pale_socket` | `coldwire-user-2` | Member | The ordinary member experience |
| `stratum` | `coldwire-user-3` | Member | |
| `quiet_fan` | `coldwire-user-4` | Member | |
| `dust_index` | `coldwire-user-5` | Member | |
| `loudpacket` | `coldwire-user-6` | Member (suspended) | The restriction screens |

The seed also creates 4 categories, 13 forums, 19 topics (one with 58 posts so
pagination has something real to page through), two chat rooms with a
transcript, private messages, notifications, a pending report, a resolved
report, a warning, a suspension and a populated moderation log.

---

## Directory structure

```
app/
  Controllers/        HTTP entry points — thin; they validate, delegate, render
    Admin/            administration panel
    Moderation/       moderation panel
  Models/             domain enums: user status, report status, topic flags, …
  Repositories/       every SQL statement in the project lives here
  Services/           business rules: auth, access control, topics, chat, …
    Chat/             the chat transport abstraction
  Middleware/         csrf, auth, guest, permissions, throttling, maintenance
  Policies/           "may this viewer do this to this object?"
  Support/            router, request/response, view, database, validation, …
config/               static configuration (app, database, session, mail, …)
app/
  Install/            the web installer's logic (deleted after installation)
database/
  migrations/         numbered .sql files, applied in order and recorded
  seeders/
    CoreSeeder.php    the configuration a working board needs — used by the installer
    DatabaseSeeder.php  that, plus demo accounts and discussion
public/               the only web-reachable directory
  index.php           front controller
  install.php         the web installer; delete it once installed
  router.php          router for PHP's built-in server
  uploads/avatars/    user uploads (execution disabled)
routes/web.php        every route, with its middleware
storage/
  logs/               app, security and mail logs
templates/themes/
  default/            the shipped theme (templates + assets)
tests/                unit and feature tests plus the runner
```

---

## Routing

The board does **not** require URL rewriting. `APP_URL_MODE` decides how every
link is built:

| Mode | Links look like | Needs |
|------|-----------------|-------|
| `path` | `/forum/general` | `mod_rewrite`, nginx `try_files`, or the bundled dev router |
| `pathinfo` | `/index.php/forum/general` | nothing — the path arrives as `PATH_INFO` |
| `query` (default) | `/index.php?r=/forum/general` | nothing at all |

`query` is the default because it works on shared hosting, in a subdirectory,
behind a control panel, and anywhere the deployment has no privileges to
configure the web server. `pathinfo` is the one to prefer when it works: it
reads almost like a clean URL and still asks nothing of the server.

The slashes in `query` mode are left as slashes. They are legal in a query
string, and percent-encoding them to `%2F` buys nothing but an unreadable
address. Individual segments are still escaped, so a stray character in a slug
cannot break out of its segment.

Drop the project in, point the document root at `/public`, and it runs. Nothing
else in the codebase knows which mode is active: routes are declared with
logical paths and every URL is produced by `App\Support\Url`, so switching
modes is one environment variable.

The front controller accepts the logical path three ways — the `r` parameter,
`PATH_INFO`, or the request URI when rewriting is in place — so the same
deployment answers all of them.

### Writing a form that survives every mode

A form submitted with **GET** throws away the query string in its `action` and
replaces it with its own fields. In `query` mode that would drop the route and
send the submission to the board index, so such a form asks the view for both
halves:

```php
<form action="<?= $this->e($this->formAction('search')) ?>" method="get">
    <?= $this->routeField('search') ?>
```

`formAction()` returns the entry script alone in query mode and the normal URL
otherwise; `routeField()` emits the hidden field that carries the route, and
nothing at all in the modes where the address already holds it. Route
parameters go to both: `$this->formAction('chat.moderate', ['room' => $slug])`.

**POST** forms need neither — a POST keeps its action exactly as written — so
they use `$this->route(…)` as usual. A feature test renders every page with a
GET form and fails if one carries the route in its action instead of a field.

## Architecture

Request flow:

```
public/index.php
  └─ Kernel::boot()          load env + config, start the session, register routes
  └─ Kernel::handle()
       ├─ prepare()          share the viewer, settings and counters with the view
       ├─ Router::dispatch() match the path, run middleware, call the controller
       │    └─ Controller    validate input, call a service, render a template
       │         ├─ Service  business rules, transactions, notifications
       │         └─ Repo     parameterised SQL
       └─ Response::send()   status, security headers, body
```

The rules the codebase keeps to:

- **Controllers hold no SQL and no HTML.** They validate, call a service or a
  repository, and render a template.
- **Repositories hold all the SQL.** Every statement is parameterised; there is
  no string concatenation of user input anywhere. Where a query needs a dynamic
  fragment (sort column, join table) it comes from a fixed internal list.
- **Services own the write paths** so counter maintenance, notifications and the
  moderation log cannot be forgotten by a caller.
- **Policies answer authorisation questions** about a specific object; they ask
  `AccessControl`, never a role name.
- **Templates receive data and nothing else.** No template opens a database
  connection. This is what makes the forum logic independent of the theme.

---

## Authentication

Session-based, with the usual defences wired in:

- Passwords hashed with `password_hash()` (bcrypt by default) and verified with
  `password_verify()`. Hashes are upgraded transparently on sign-in when the
  algorithm or cost changes.
- The session id is regenerated on sign-in and on sign-out, and the CSRF token is
  rotated with it, so a fixated session or token is useless.
- Sessions are bound to a fingerprint of the user agent and `APP_KEY`; a
  mismatch logs the session out and writes a line to `storage/logs/security.log`.
- An idle session past `SESSION_LIFETIME` is discarded rather than resurrected.
- Failed sign-ins are throttled per identifier *and* address (6 per 15 minutes by
  default) and recorded in `login_attempts`.
- Sign-in timing does not reveal whether an account exists: a hash comparison
  runs either way.
- Password reset uses a single-use token; only its SHA-256 hash is stored, it
  expires after an hour, and changing a password invalidates outstanding links.
  The "did we send it" answer is identical whether or not the address exists.

### Suspensions and bans

The two are deliberately different:

| | Sign in | Read | Post, message, chat | Ends |
|---|---|---|---|---|
| **Suspension** | yes | yes | no | on its own date |
| **Ban** | **refused**, with the reason | — | — | never |

A suspended member can still sign in on purpose: it is the only way they get to
read *why* and *until when*. Locking them out instead produces a member who sees
"wrong credentials" and concludes the board is broken.

That only works if it is visible, so a restricted account is told plainly:
signing in lands on **Your record** rather than the index, a banner naming the
reason and the end date sits on every page, and `RestrictionMiddleware` turns
away anything that writes.

**Your record** (`/settings/record`) is the member's own copy: active
restriction, every warning with its reason and expiry, and past restrictions.
Warning notifications lead there — a notice with nothing to open is not a
notice.

---

## Permissions

Two layers work together.

**1. Global permissions** — granular slugs granted to roles. The seed ships 46 of
them, grouped as `general`, `topics`, `posts`, `messaging`, `chat`, `profile`,
`moderation`, `administration`, plus the `*` wildcard held only by the
administrator role.

```
topic.create        post.edit.own       report.handle       admin.users
topic.reply         post.delete.any     user.warn           admin.roles
topic.pin           post.hide           user.suspend        admin.forums
topic.move          post.history.view   user.ban            admin.settings
topic.split         message.send        moderation.access   admin.themes
…
```

**2. Per-forum access** — a role × forum matrix with five flags: *see the forum*,
*read topics*, *start topics*, *reply*, *moderate*. A role can therefore behave
completely differently on two boards: visible-but-unreadable teaser forums,
read-only archives, staff-only areas.

A member holds several roles at once and their effective permission set is the
**union** of all of them. Nothing in the application checks a role name — every
decision goes through `AccessControl`, which is what makes the system genuinely
granular rather than three hardcoded tiers.

Default roles: **Guest** (read and search), **Member** (post, message, chat),
**Trusted member** (member plus edit-history access — an example of stacking),
**Moderator** (content and member tools), **Administrator** (`*`).

### Creating a moderator

Either way works:

1. **Admin panel** — *Users → pick the account → Roles → tick “Moderator” → Save*.
   Set “Displayed rank” to Moderator so the title shows next to their posts.
2. **Per forum** — if you want somebody moderating one board only, give them a
   role without `moderation.forums.all` and tick **Moderate** for that role on
   just that forum under *Forums → (forum) → permissions*.

The board refuses to let the last administrator role lose the `*` permission, and
you cannot strip your own administrator role.

### Creating a forum

*Admin → Categories & forums → New forum*: name, identifier (the URL segment),
category, optional parent (that is what makes it a subforum), icon glyph,
position, visibility and lock state. New forums start with a sensible permission
matrix which you then adjust on the permissions screen. Display order is edited
inline on the forum list.

---

## Extending the board

Three things people want to change first, and where each one lives.

### Restricting something to members, or to a role

There are four levels, from coarsest to finest. Use the highest one that fits.

**A whole forum** — *Admin → Forums → (forum) → permissions*. A role × forum
grid with five flags: see the forum, read topics, start topics, reply,
moderate. This is the one to reach for: it needs no code, and "see but not
read" gives you a forum that is listed but closed, which is how you advertise a
members-only area without hiding it.

**A route** — in [routes/web.php](routes/web.php), on the route or a group:

```php
$router->get('/secret', [X::class, 'y'])->middleware('auth');            // signed in
$router->get('/secret', [X::class, 'y'])->middleware('can:admin.access'); // a permission
$router->get('/secret', [X::class, 'y'])->middleware('can:report.view|user.ban'); // any of
```

Add a new permission by inserting it in `CoreSeeder::seedPermissions()` and
granting it to roles in the admin panel. `can:` accepts any slug; nothing else
needs to know about it.

**One object** — a policy, in [app/Policies/](app/Policies/). `TopicPolicy`,
`PostPolicy` and `UserPolicy` answer questions like "may this viewer reply to
*this* topic". They ask `AccessControl`, never a role name, which is why a
permission you invent works everywhere at once.

**A component on a page** — in any template:

```php
<?php if ($this->can('chat.moderate')): ?>        <!-- a permission -->
<?php if ($this->shared('current_user') !== null): ?>  <!-- signed in at all -->
<?php if ($this->shared('is_staff')): ?>          <!-- any moderation right -->
```

Hiding a control is presentation, not protection: guard the route or the policy
as well, or the address still works when somebody types it.

> A topic cannot be restricted individually — that is deliberate. Per-topic
> permissions are how a board becomes impossible to reason about. Move the
> topic into a forum with the access you want, or hide it (*Moderate → Hide*),
> which leaves it readable by moderators only.

### Processing what members write

Two hooks, in [app/Services/ContentFilter.php](app/Services/ContentFilter.php),
and they are deliberately different:

| Hook | Runs | Changes | Use it for |
|------|------|---------|-----------|
| `clean()` | once, on save | **what is stored** | normalising: trimming, collapsing blank lines, stripping invisible characters |
| `display()` | every render | **only what this reader sees** | word filters, anything you may want to undo or apply retroactively |

`clean()` is called by `TopicService` on create, reply and edit —
the three places content enters the database. `display()` is called by
`ContentFormatter::render()`, on the raw source *before* any markup exists, so
a replacement can never introduce HTML.

Prefer `display()` for anything judgemental. A word filter that rewrites the
stored text destroys the evidence a moderator needs when the member disputes it,
and cannot be changed afterwards. The shipped filter is display-time: configure
it under *Admin → Settings → Posting* with a word per line. Matching is
whole-word, case- and accent-insensitive, so a short entry cannot mangle a
longer legitimate word.

To add a rule of your own, edit that one class:

```php
public function display(string $raw): string
{
    $raw = $this->censor($raw);
    $raw = $this->yourOwnRule($raw);   // here

    return $raw;
}
```

### Adding a formatting tag

One place: `ContentFormatter::INLINE_TAGS` in
[app/Support/ContentFormatter.php](app/Support/ContentFormatter.php).

```php
'mark' => ['<mark class="content-mark">', '</mark>', 'highlighted'],
//  tag      opening HTML                  closing     help text
```

That is the whole change. The editor's help panel and the
[formatting reference](templates/themes/default/static/help.php) are both
generated from this array, so a new tag documents itself and the two cannot
drift apart.

Two rules for anything you add:

1. **The opening HTML is fixed text.** It never interpolates what the member
   wrote. Their content is already escaped by the time these tags are applied,
   and that is what keeps the whole scheme safe.
2. **Style it with a class, not a `style` attribute.** The board's
   Content-Security-Policy refuses inline styles, so a tag that needs a colour
   gets a class here and a rule in the theme's CSS. A `style="…"` would be
   silently dropped by the browser.

Tags that take an argument or need their own parsing — `[quote=name]`,
`[code=php]`, `[url=…]`, `[list]` — are separate methods in the same class, and
each is listed in `reference()` so it shows up in the help.

## The theme system

The active theme is stored in the `themes` table and resolved per request by
`App\Services\ThemeManager`. Templates are looked up in order:

```
active theme → its parent (if declared) → default
```

so a theme only ships the files it actually wants to change. Everything else
falls through. **No forum logic lives in a theme, and no theme file is required
to add a feature** — a template receives the data the controller passed and
renders it.

Assets are served from inside the theme package through `/theme/{slug}/{path}`,
so a theme stays self-contained instead of scattering files into `/public`.

Admin → Themes lists what is on disk and what is registered, shows each
manifest, and activates, enables or disables them.

### Customising a theme without editing files

A theme declares which of its values an administrator may change, in the
`settings` array of its manifest. Each entry names a CSS custom property:

```json
{ "key": "accent", "group": "Palette", "label": "Accent",
  "type": "colour", "default": "#6d93b8", "css": "--accent" }
```

**Admin → Themes → Appearance** then renders a form for them — native
`<input type="color">` pickers, number fields and selects, no scripting — and
the chosen values are written to the theme's database record. The default theme
exposes 29 settings: page background, panels, borders, accent, body text,
headings, secondary text, link and link-hover colours, the full code-highlighting
palette, base text size, corner radius, panel spacing, maximum page width and the
interface typeface.

Values are validated against their declared type before they can reach a
stylesheet — a colour must match `#rrggbb`, an integer is clamped to its declared
range, a select must be one of its own options — so a stored setting can never
inject arbitrary CSS.

The result is served as a real stylesheet at `/theme/{slug}/settings.css`,
generated from the database and linked after the theme's own CSS. Doing it that
way rather than with an inline `<style>` block is what lets the
Content-Security-Policy keep refusing inline styles outright.

### Syntax highlighting

`[code]` blocks are highlighted **on the server** by
`App\Support\CodeHighlighter`, which emits nothing but `<span>` elements. It is
language-agnostic: instead of a grammar per language it recognises what nearly
all of them share — comments, strings, numbers, keywords, literals, variables,
calls, types, markup tags, attributes, operators and punctuation — so anything a
member pastes gets useful colour. `[code=php]` adds a language label to the
block.

Each token class maps to a CSS custom property (`--tok-keyword`, `--tok-string`,
…), so the palette is customised in the same Appearance screen as everything
else, with a live preview rendered from the saved values.

---

## Creating a theme

```bash
mkdir -p templates/themes/my-theme/assets/css
```

`templates/themes/my-theme/theme.json`:

```json
{
    "name": "My Theme",
    "slug": "my-theme",
    "version": "1.0.0",
    "author": "You",
    "description": "What it looks like and who it is for.",
    "parent": "default",
    "settings": { "accent": "#7d6db8" }
}
```

With `"parent": "default"` you can start by overriding a single file — say
`layouts/main.php` or `partials/post.php` — and inherit the other 100 templates.
Then:

```bash
php bin/console theme:sync     # or use "Scan directory" in Admin → Themes
```

and activate it in the admin panel. Nothing in `/app` changes.

Template conventions:

- `$this` inside a template is the `View`. Escape with `$this->e()`, build links
  with `$this->route()`, render user content with `$this->content()`, include a
  partial with `$this->partial()`.
- **Everything printed must be escaped.** `$this->content()` is the only method
  that emits markup, and it escapes first and re-introduces a fixed tag set
  afterwards.
- Layouts live in `layouts/`: `main`, `narrow`, `minimal`, `admin`, `moderation`.

---

## Running the board

These are the flows the project is built around; all of them work end to end.

**Member** — register → sign in → browse a forum → open a topic → reply (or
quote) → edit your post → subscribe/bookmark → edit your profile → upload an
avatar → sign out.

**Moderator** — sign in → `/moderation` → reports → **click the reported item
to open it in context** → hide or delete the content and resolve it → open a
member's record → warn/suspend → check the log, which now contains every one of
those actions.

Two kinds of report arrive. A **post** report carries the post, and the review
screen can hide or delete it in the same action that resolves the report. A
**member** report is for conduct that is not in one post — a pattern across
several, a name, an avatar, private messages — and requires a description,
because without a post to look at, that description is all the moderator has.
Members report a post from the link under it, and a member from the Report
button on their profile. Neither can be used on yourself.

**Administrator** — `/admin` → dashboard → users, roles and the permission
matrix → categories and forums with their per-forum permissions → topics and
posts → settings → themes → chat rooms → system information, logs and
maintenance tasks.

**Messaging** — `/messages` → compose → send → recipient sees it in their inbox,
with the unread count in the header → read → reply (the body arrives
pre-quoted). No separate alert is raised: the inbox counter *is* the
notification, and showing the same number twice is how people learn to ignore
both. A member who would rather see everything in one list can turn that on
under preferences.

Alerts follow the same rule within a topic. One post can involve you three ways
— you follow the topic, you were quoted, you were mentioned — and produces
exactly one alert, naming the most specific reason: quoted, then mentioned,
then replied. "You were quoted" tells you why to look; "somebody replied" does
not.

**Chat** — `/chat` → send a message → the page redirects and re-renders the
transcript → moderators delete a message, mute, ban or purge a member.

---

## Chat architecture

The chat page is entirely server-rendered today, but it is built so real-time
delivery can be dropped in without redesigning anything.

```
ChatController ──▶ ChatService ──▶ ChatTransport (interface)
                       │                 ├── HttpTransport   (shipped)
                       │                 └── your transport   (WebSocket/SSE)
                       └── rules: slow mode, mutes, length limits
```

`App\Services\Chat\ChatTransport` is the seam:

```php
interface ChatTransport
{
    public function name(): string;
    public function describe(): string;
    public function isRealtime(): bool;
    public function publish(ChatMessage $message): ChatMessage;
    public function history(int $roomId, int $limit, bool $includeDeleted = false): array;
    public function join(int $roomId, int $userId): void;
    public function presence(int $roomId, int $windowSeconds): array;
    public function retract(int $messageId, int $moderatorId): void;
    public function endpoint(): ?string;
}
```

To add real-time delivery later:

1. Implement the interface (persist through `ChatRepository`, then push).
2. Register it: `TransportFactory::register('websocket', WebSocketTransport::class);`
3. Select it in **Admin → Chat settings → Chat transport**.

The controller, routes, moderation tools and templates stay exactly as they are.
The page already renders the message list, composer and presence column as the
separate regions such a transport would update, and `endpoint()` is where the
connection URL would come from. The board itself still ships no browser-side
code; adding some would be that transport's own decision.

---

## Security

What is implemented, and where to look:

| Concern | Measure | Location |
|---------|---------|----------|
| SQL injection | Every statement parameterised; dynamic fragments from fixed internal lists only | `app/Support/Database.php`, `app/Repositories/` |
| XSS | Output escaped at render; user markup escaped first, then a fixed tag set re-introduced; `javascript:`/`data:` URLs dropped | `View::e()`, `ContentFormatter` |
| CSRF | Token on every POST, checked by middleware, rotated on sign-in/out | `Csrf`, `CsrfMiddleware` |
| Session fixation | Id regenerated on sign-in and sign-out | `AuthService::login()` |
| Session hijacking | Fingerprint binding, idle timeout, `HttpOnly`/`SameSite` cookies | `Session`, `AuthService` |
| Brute force | Per-identifier and per-address throttles, attempt log | `RateLimiter`, `config/security.php` |
| Flooding | Named throttles on posting, chat, reports, messages, search | `RateLimitMiddleware` |
| Authorisation | Route middleware plus object policies; no role-name checks | `PermissionMiddleware`, `app/Policies/` |
| Upload abuse | Layered inspection then mandatory re-encoding — see [Uploads](#uploads) | `UploadGuard`, `AvatarService` |
| Clickjacking / sniffing | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` | `config/security.php` |
| Script injection | A CSP of `script-src 'none'` — the board has no JavaScript, so the policy enforces it | `config/security.php` |
| Information disclosure | Identical answers for existing and non-existing accounts on sign-in and password reset | `AuthService` |
| Audit | Every moderator action written to `moderation_actions` with actor, target, reason, time and address | `ModerationService` |

### Uploads

Nothing the browser says about a file is believed — not the name, not the
extension, not the `Content-Type` header. `App\Services\UploadGuard` layers the
checks so defeating one is not enough:

1. the file really arrived through PHP's upload machinery (`is_uploaded_file`);
2. its size is within the policy, and it is not empty;
3. the declared name carries no dangerous extension **in any position**, so
   `avatar.php.png` is refused on the `.php`;
4. its leading bytes match a signature for an allowed format (and WebP is
   verified at byte 8, since `RIFF` also fronts WAV and AVI);
5. the signature, the `finfo` type and `getimagesize` all agree;
6. dimensions **and total pixel count** are within the policy, checked before
   anything decodes, so a small file declaring an enormous canvas cannot
   exhaust memory;
7. the whole file is scanned for executable markers (`<?php`, `<script`,
   shebangs), with an overlap so a marker split across two reads is still seen;
8. nothing follows the point at which the format declares the image ends —
   the IEND chunk of a PNG, the EOI marker of a JPEG, the trailer of a GIF, the
   length in a WebP's RIFF header. This is what catches an appended payload,
   precisely and without guessing.

Every marker in step 7 is at least five bytes long, and that is deliberate.
Compressed image data is effectively random, so a short sequence occurs by
chance: `<%` is two bytes, which turns up roughly once per 64 KB and therefore
about twice in an ordinary 145 KB photograph. Scanning for it rejects real
images — which is exactly what an earlier version of this code did. At five
bytes the odds of a chance match are around one in 10^12, so a hit is a finding
rather than noise, and the structural check in step 8 covers what the short
markers were reaching for.

Then the decisive step: `AvatarService` **decodes the image and re-encodes it
from the pixel buffer**. What lands on disk is bytes GD produced, always as PNG,
so anything that survived every check above — appended archives, payloads in
EXIF or `tEXt` chunks, polyglot headers — is simply not part of the output.
Metadata is dropped in the same move, which also stops avatars leaking a
member's camera model or GPS coordinates.

Source limits and stored limits are separate: a photograph straight off a phone
(several megabytes, thousands of pixels wide) is accepted and **scaled down** to
the stored size, because it is being re-encoded anyway. The limit quoted to
members is the *effective* one — PHP refuses an upload above
`upload_max_filesize`, and a request above `post_max_size`, before the
application ever sees it, so the board reports the smallest of the three rather
than naming a limit the file was under. Raise those two directives to at least
`UPLOAD_MAX_AVATAR_BYTES` or they, not the board, decide the real ceiling.

The stored name is generated (`<id>-<24 hex chars>.png`); nothing from the
client reaches the filesystem. Files are written `0644`, the upload directory
denies execution, and deletion only accepts names of exactly that shape.
Uploads require the GD extension: without it the feature refuses rather than
storing bytes it cannot verify.

The test suite exercises this with the attacks themselves — a PHP script renamed
to `.png`, a valid PNG with code appended after `IEND`, a JPEG with data after
its EOI marker, a payload hidden in a `tEXt` chunk, compound extensions, an SVG,
and an HTML file fronted by a `GIF89a` signature — **and with ordinary
photographs**, including ten noisy images in a row, so that a check tuned to
catch payloads cannot start rejecting real uploads unnoticed.

### Configuration is enforced, not decorative

A switch an administrator throws applies to everybody. `SettingMiddleware`
guards the routes themselves, so turning the chat off closes `/chat` for guests,
members, moderators **and administrators** — the wildcard permission does not
reopen a disabled feature. Configuring it stays reachable in the administration
area, which is guarded by permission rather than by the feature's own setting.
The same holds for registration and avatar uploads, and maintenance mode blocks
writes rather than only hiding links.

Because there is no JavaScript, **no validation happens on the client** and none
is trusted: every rule is enforced server-side in `Validator`, the policies and
the services.

Before going live:

0. **Delete `public/install.php`** if it is still there — the installer's last
   screen offers to do it for you.
2. `SESSION_SECURE=true` behind HTTPS.
3. Change or delete the demo accounts (`admin` especially).
4. Give the database user only the rights it needs.
5. Point the web server's document root at `/public` and nothing above it.

---

## Tests

```bash
php tests/run.php            # everything the environment allows
php tests/run.php unit       # no database needed
php tests/run.php feature    # boots the kernel, needs a seeded database
```

The runner has no dependencies. Unit tests cover the router, the content
formatter (including the XSS and URL-scheme cases), the validator, the paginator
and the support helpers. Feature tests boot the real kernel and dispatch real
requests through the router, so routing, middleware, policies, repositories and
templates are exercised together — including that **no rendered page contains a
script tag**, that a member gets 403 on staff areas, that a bad CSRF token is
rejected, and that private messages stay between their two parties.

---

## Deployment

With `APP_URL_MODE=query` (the default) no rewriting is needed at all: point the
document root at `/public` and you are done. The configuration below is only for
running in `path` mode.

### Apache

`public/.htaccess` ships with the rewrite rules. Point the vhost at `/public`
and enable `AllowOverride All`, or inline the rules.

### nginx

```nginx
server {
    listen 80;
    server_name board.example.org;
    root /var/www/coldwire/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Uploads are data, never executables.
    location ^~ /uploads/ {
        location ~ \.php$ { return 403; }
    }
}
```

### Housekeeping

Session-tracking rows, expired throttles and lapsed suspensions are cleared from
**Admin → Maintenance**, or on a schedule if you prefer:

```cron
17 * * * * cd /var/www/coldwire && php bin/console maintenance:run > /dev/null
```

Nothing breaks if you never run it — every query filters on expiry — the tables
simply grow.

---

## Licence

Written as a self-contained application; use it as you see fit.
