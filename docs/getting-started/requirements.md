# Requirements

The list is short on purpose. If you do not administer the server you cannot
add an extension, so anything the board can work around must not stop you.

## Required

Three things, and nothing else, can prevent an installation.

| Requirement | Why it cannot be worked around |
|---|---|
| **PHP 8.1 or newer** | The code uses enums, readonly properties and `never`. The test suite is run on 8.1 and 8.3 |
| **`pdo_mysql`** | How the board reaches the database. There is no second driver |
| **`mbstring`** | Counts and cuts text that is not plain ASCII. Part of the standard PHP build, so hosts effectively always have it |

Plus a database: **MySQL 8.0+** or **MariaDB 10.6+**. The schema is tested
against both.

`json` is not on the list because it cannot be disabled in PHP 8.

## Optional

Each of these costs you exactly one thing and nothing else. The installer shows
them as warnings and continues.

| Requirement | Without it |
|---|---|
| **`gd`** or **`imagick`** | No avatar uploads; everyone keeps the generated monogram. Either library satisfies it |
| **`fileinfo`** | One of several cross-checks on uploads is skipped. The rest, including re-encoding, still apply |
| Writable `storage/logs` | The board runs but keeps no record of errors or security events. Worth fixing |
| Writable `public/uploads/avatars` | No avatar uploads. Turn them off in the settings and it stops mattering |
| Writable project root | The installer shows you the `.env` to save by hand instead of writing it |

## Web server

Any. Apache, nginx, Caddy, or PHP's own server for development.

With the default [URL mode](../architecture/routing.md) the board needs **no
rewrite rules and no server configuration at all** — it works anywhere a PHP
file can run, including shared hosting and subdirectories.

## Finding out whether a host will work

Upload the files and open `/install.php`. The first screen checks every item
above on that server and says what each failure would cost. You do not need
shell access to find out.

## What the board does not need

- No Composer, and no vendor directory. The project ships its own autoloader,
  router, template engine, validator and test runner
- No Node, no build step, no asset pipeline
- No cron job. Housekeeping tasks exist but nothing breaks if they never run
- No mail server, unless you want password resets to arrive by e-mail; the
  default transport writes them to `storage/logs/mail.log`
- No cache server, no queue, no search daemon
