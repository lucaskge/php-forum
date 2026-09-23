# Coldwire

A forum platform written in plain PHP on MySQL or MariaDB. Server-rendered,
traditional board layout, granular permissions, a real moderation trail — and
**no JavaScript anywhere**: not one `<script>` tag, not one inline handler.

Everything that would normally need scripting is done with HTML and CSS. The
mobile menu is a `<details>` disclosure, confirmations are real pages, quoting
is a GET followed by a POST, and the chat transcript is whatever the server
rendered on the last request.

## What these pages are for

They are written to be enough on their own. Somebody who has never seen this
codebase should be able to install it, run a board with it, and then fix a bug
in it without asking anyone.

| If you want to… | Start at |
|---|---|
| Put the board on a server | [Requirements](getting-started/requirements.md), then [Installation](getting-started/installation.md) |
| Start or stop the servers | [Running the servers](getting-started/running.md) |
| Run a board day to day | [Using the board](guide/reading-and-search.md) and [Moderation](moderation/index.md) |
| Configure it | [Administration](admin/index.md) |
| Understand how it works | [Architecture overview](architecture/overview.md) |
| Change or extend it | [Conventions](development/conventions.md), then [Adding a page](development/adding-a-page.md) |
| Work out why something is broken | [Troubleshooting](development/troubleshooting.md) |
| Look something up | [Routes](reference/routes.md), [Permissions](reference/permissions.md), [Settings](reference/settings.md), [Console](reference/console.md) |

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
