# Installation

Three ways in. The first needs nothing but a browser.

## With the web installer

This is the path for shared hosting, a control panel, or any server you do not
administer.

1. Upload the project. Point the document root at **`public/`**.
   If you cannot change the document root, the board still works — see
   [Subdirectories](#subdirectories) below.
2. Open **`/install.php`** in a browser.

The installer has three screens.

### 1. Requirements

Every item from [Requirements](requirements.md), checked on this server, split
into what is required and what is optional. Optional failures say what you
lose; they do not block you.

### 2. Database

Host, port, **database name**, user and password. The connection is tested
before anything is written.

The database is created for you **if your account is allowed to create
databases**. On shared hosting it usually is not — the control panel creates
it — so create it there first and put its name here.

If the connection fails, the message says which part is wrong rather than
listing all four:

| What you see | What it means |
|---|---|
| *Inside a container, 127.0.0.1 means the container itself…* | The board runs in a container; use the database container's name or `host.docker.internal` |
| *The server answered, but rejected these credentials* | Host and port are right; the user or password is not |
| *That host name does not resolve* | The host name is wrong or unreachable |
| *Connected to the server, but that database is not there* | Create the database, or fix its spelling |

### 3. Board and administrator

Board name, address, the [address style](../architecture/routing.md) to use,
and the first administrator account.

The installer then writes `.env` with a freshly generated `APP_KEY`, applies
the migrations, seeds the configuration — permission grid, roles, settings,
theme record, a starter category with two forums, and a chat room — and creates
the administrator. **No demo content:** a board ready to use, and nothing to
clean up.

If the project directory is not writable, it shows you the exact `.env` to save
by hand instead of failing.

### 4. Delete the installer

The last screen has a button that removes `public/install.php` and
`app/Install/`. **Use it.** While it exists it is the one page on the site that
can rewrite your configuration.

It also refuses to run once `storage/installed.lock` exists or the database
already holds accounts, and if it cannot delete a file it says which one rather
than claiming success.

!!! warning "Install in one sitting"
    Between the upload and the installation, `install.php` is reachable by
    anyone who finds it — whoever runs it first becomes the administrator. This
    is true of every installer of this kind.

## From the command line

```bash
cp .env.example .env
php bin/console key:generate
# edit .env: DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
php bin/console install        # migrate:fresh + demo seed
php bin/console serve          # http://127.0.0.1:8080
```

`install` **drops every table** and loads the demo board: 9 accounts, 4
categories, 13 forums, 19 topics including one with 58 posts, chat transcript,
messages, reports and a populated moderation log. It is for development.

On an existing board use `php bin/console migrate`, which applies only what has
not run yet and never touches your data.

Demo credentials are listed in [First steps](first-steps.md).

## With Docker

For a machine with no PHP installed. The compose file runs **only the
application**; the database is whatever `DB_HOST` points at, so it never fights
with a MySQL you already have.

```bash
docker compose up -d
docker compose run --rm app php bin/console key:generate
docker compose run --rm app php bin/console install
```

No database of your own? Start the bundled one alongside it — it publishes host
port **3307**, so it cannot collide with a server already on 3306:

```bash
docker compose -f docker-compose.yml -f docker-compose.db.yml up -d
```

## Directory permissions

```bash
chmod -R 775 storage/logs public/uploads
```

Neither is fatal; see [Requirements](requirements.md) for what you lose.

## Subdirectories

The board runs from a subdirectory with no extra configuration, as long as
`APP_URL` matches and the default URL mode is in use. Links are built from
`APP_ENTRYPOINT`, which you can set to `/forum/index.php` if the board lives at
`/forum`.

## Upgrading

1. Back up the database and the `public/uploads` directory.
2. Replace the files, keeping `.env`, `public/uploads/` and
   `storage/installed.lock`.
3. Run `php bin/console migrate`, or just load any page — migrations are not
   applied automatically, so run the command. If you have no shell, the
   [System screen](../admin/system.md) lists which migrations have run.
