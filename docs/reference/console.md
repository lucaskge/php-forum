# Console commands

Everything `php bin/console` accepts. In Docker, prefix each with
`docker compose run --rm app`.

!!! info "Generated"
    This page is written by `php bin/console docs:reference`. Edit the code,
    not this file — the next run overwrites it.

| Command | Does | |
|---|---|---|
| `migrate` | Apply pending migrations |  |
| `migrate:fresh` | Drop every table and migrate from zero | **destroys data** |
| `db:seed` | Load the demo data | **destroys data** |
| `install` | migrate:fresh followed by db:seed | **destroys data** |
| `key:generate` | Write a new APP_KEY into .env |  |
| `theme:sync` | Register themes found on disk |  |
| `maintenance:run` | Purge stale sessions and throttles, expire suspensions |  |
| `user:promote <username>` | Give an account the administrator role |  |
| `routes` | List every registered route |  |
| `docs:reference` | Regenerate the reference pages of the documentation |  |
| `serve [host:port]` | Run PHP's built-in server on /public |  |

## `migrate`

Runs every migration file that is not yet recorded in the `migrations` table, in filename order, and records each one. Safe to run repeatedly: applying nothing is a normal outcome. Creates the database first if it does not exist and the credentials allow it.

## `migrate:fresh`

!!! danger "Destroys data"
    Take a backup first, and never run it against a live board.

Drops every table in the configured database, then applies all migrations. The schema comes back empty — no accounts, no posts, no settings. Intended for development.

## `db:seed`

!!! danger "Destroys data"
    Take a backup first, and never run it against a live board.

Clears and repopulates the tables it owns with the demo board: roles, permissions, settings, themes, the sample categories and forums, the demo accounts and a set of topics and posts. It does not touch the schema.

## `install`

!!! danger "Destroys data"
    Take a backup first, and never run it against a live board.

The one command for a new checkout: drops everything, rebuilds the schema, loads the demo data and prints the demo credentials. Never run it against a board with real content.

## `key:generate`

Generates a 32-character random key and writes or replaces the APP_KEY line in .env. Run it once per installation. Changing it later invalidates anything derived from it.

## `theme:sync`

Scans templates/themes/ for directories holding a theme.json and inserts any that are not yet in the themes table. Run it after copying a theme in by hand; the admin panel does the same thing from its own button.

## `maintenance:run`

Removes session-tracking rows older than a day, deletes expired rate-limit windows and lifts suspensions whose end date has passed. Harmless to run at any time; a daily cron entry is the intended use.

## `user:promote <username>`

Assigns the administrator role, makes it the account's primary role and sets its status to active. The recovery path when nobody can reach the admin panel any more.

## `routes`

Prints the verb, URI, route name and middleware of every route, in declaration order — which is also the order the router tries them.

## `docs:reference`

Rewrites docs/reference/routes.md, permissions.md, settings.md and console.md from the router, the database and the command table. Run it after adding a route, a permission, a setting or a command.

## `serve [host:port]`

Starts PHP's development server on 127.0.0.1:8080 unless another address is given, with public/router.php handling static files. For development only — it serves one request at a time.

