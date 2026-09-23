---
hide:
  - navigation
---

# Coldwire

A forum platform written in plain PHP on MySQL or MariaDB. Server-rendered,
traditional board layout, granular permissions, a real moderation trail — and
**no JavaScript anywhere**: not one `<script>` tag, not one inline handler.

Everything that would normally need scripting is done with HTML and CSS. The
mobile menu is a `<details>` disclosure, confirmations are real pages, quoting
is a GET followed by a POST, and the chat transcript is whatever the server
rendered on the last request.

## Where to start

These pages are written to be enough on their own. Somebody who has never seen
this codebase should be able to install it, run a board with it, and then fix a
bug in it without asking anyone.

<div class="cw-index" markdown>

<div class="cw-card" markdown>
<span class="cw-card__kicker">Getting started</span>
[:material-download:{ .cw-card__icon } Get it running](getting-started/requirements.md){ .cw-card__title }
<span class="cw-card__body">What the server needs, the browser installer, Docker, and how to start and stop the two servers.</span>
<span class="cw-card__more">[Installation](getting-started/installation.md) [Running the servers](getting-started/running.md)</span>
</div>

<div class="cw-card" markdown>
<span class="cw-card__kicker">Using the board</span>
[:material-forum:{ .cw-card__icon } Run a board](guide/reading-and-search.md){ .cw-card__title }
<span class="cw-card__body">Reading, posting, formatting, messages, alerts and chat — then the moderation queue and the tools behind it.</span>
<span class="cw-card__more">[Posting](guide/posting.md) [Moderation](moderation/index.md)</span>
</div>

<div class="cw-card" markdown>
<span class="cw-card__kicker">Administration</span>
[:material-tune-variant:{ .cw-card__icon } Configure it](admin/index.md){ .cw-card__title }
<span class="cw-card__body">Every administration screen: settings, roles and permissions, forums, themes, chat and system maintenance.</span>
<span class="cw-card__more">[Settings](admin/settings.md) [Users and roles](admin/users-and-roles.md)</span>
</div>

<div class="cw-card" markdown>
<span class="cw-card__kicker">Architecture</span>
[:material-sitemap:{ .cw-card__icon } Understand it](architecture/overview.md){ .cw-card__title }
<span class="cw-card__body">The request lifecycle, the routing modes, the schema, the content pipeline and every security measure with the file it lives in.</span>
<span class="cw-card__more">[Routing](architecture/routing.md) [Database](architecture/database.md) [Security](architecture/security.md)</span>
</div>

<div class="cw-card" markdown>
<span class="cw-card__kicker">Development</span>
[:material-code-braces:{ .cw-card__icon } Change it](development/conventions.md){ .cw-card__title }
<span class="cw-card__body">The conventions, a full worked example of adding a page, extending the markup and permissions, and writing tests.</span>
<span class="cw-card__more">[Adding a page](development/adding-a-page.md) [Testing](development/testing.md)</span>
</div>

<div class="cw-card" markdown>
<span class="cw-card__kicker">Development</span>
[:material-palette-outline:{ .cw-card__icon } Restyle it](development/creating-a-theme.md){ .cw-card__title }
<span class="cw-card__body">Customise the colours from a form, inherit from the default theme, or build one from nothing.</span>
</div>

<div class="cw-card" markdown>
<span class="cw-card__kicker">Development</span>
[:material-bug-outline:{ .cw-card__icon } Fix it](development/troubleshooting.md){ .cw-card__title }
<span class="cw-card__body">Symptoms, causes and fixes — most of them bugs this project actually hit.</span>
</div>

<div class="cw-card" markdown>
<span class="cw-card__kicker">Reference</span>
[:material-book-open-variant:{ .cw-card__icon } Look something up](reference/routes.md){ .cw-card__title }
<span class="cw-card__body">Generated from the running code by a console command, so it cannot drift from the implementation.</span>
<span class="cw-card__more">[Permissions](reference/permissions.md) [Settings](reference/settings.md) [Console](reference/console.md)</span>
</div>

</div>

## The shape of it in one page

```
A request arrives at public/index.php
  │
  ├─ Kernel::boot()      loads .env and /config, starts the session, reads routes/web.php
  ├─ Kernel::handle()
  │    ├─ prepare()      shares the viewer, settings and counters with every template
  │    ├─ Router         matches the path, runs middleware, calls a controller
  │    │    └─ Controller  validates input, calls a service or repository, renders a template
  │    │         ├─ Service     business rules, transactions, notifications
  │    │         └─ Repository  parameterised SQL — the only place SQL lives
  │    └─ Response       status, security headers, body
```

Five rules hold the whole thing together, and everything else follows from
them:

1. **Controllers hold no SQL and no HTML.** They validate, delegate, render.
2. **Repositories hold all the SQL.** Every statement is parameterised.
3. **Services own the write paths**, so counters, notifications and the
   moderation log cannot be forgotten by a caller.
4. **Policies answer authorisation questions** about a specific object, and ask
   `AccessControl` rather than checking a role name.
5. **Templates receive data and nothing else.** No template opens a database
   connection, which is what makes the forum logic independent of the theme.

## By the numbers

| | |
|---|---|
| PHP files in `app/` | 106 |
| Templates in the default theme | 115 |
| Routes | 161 |
| Database tables | 31 |
| Permissions | 46 |
| Board settings | 28 across 5 groups |
| Composer dependencies | none |
| JavaScript | none |

## The reference pages are generated

`docs/reference/` is not written by hand. Every route, permission, setting and
console command is read out of the running code and the database by

```bash
php bin/console docs:reference
```

Run it after adding any of those four things, and the reference cannot drift
from the implementation. Everything outside `docs/reference/` is prose, written
and maintained by hand.

## Reading these pages in a browser

They are plain Markdown, so any editor or code host renders them. For search,
navigation and live reload, with nothing installed on the host:

```bash
make docs     # http://127.0.0.1:8100
```

`make docs-stop` stops it again. Full details, including the board's own
server, in [Running the servers](getting-started/running.md).

## Conventions in these pages

- Paths are relative to the project root: `app/Support/Router.php`.
- Addresses are written as logical paths — `/admin/users`. What that looks like
  in a browser depends on the [URL mode](architecture/routing.md).
- Anything you can do from a screen says which screen; anything you can do from
  a file says which file and which method.
