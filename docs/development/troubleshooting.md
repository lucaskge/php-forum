# Troubleshooting

Symptoms, causes and fixes. Most of these are bugs this project actually hit.

## Installation

### "Check the host, port, user and password"

The installer could not open a connection. It classifies the failure, so read
the sentence under the error — but the usual cause is that the board runs in a
container and you entered `127.0.0.1`, which inside a container means the
container itself.

| Where the board runs | Host to enter |
|---|---|
| Same machine as MySQL, no container | `127.0.0.1` |
| Docker, MySQL on the host | `host.docker.internal` |
| Docker, MySQL in another container | that container's name, e.g. `mysql8-app` |
| Managed hosting | whatever the panel says — rarely `localhost` |

The installer pre-fills the likely answer for the environment it detects.

### "Access denied for user"

The credentials reached MySQL and were refused. The user also needs privileges
on the database you named:

```sql
GRANT ALL PRIVILEGES ON coldwire.* TO 'coldwire'@'%';
FLUSH PRIVILEGES;
```

Never grant `ON *.*`. That hands the board every other database on the server.

### "Unknown database"

Either create it, or let the installer create it — which needs `CREATE` on that
name.

### The installer says the board is already installed

`.env` exists and the schema is present. The screen explains which guard fired
and offers both paths: delete the installer, or remove the lock deliberately.
Deleting requires ticking the confirmation box.

### I deleted the installer and need it back

Restore `public/install.php` and `app/Install/` from your copy of the source.
Nothing else is needed — the installer is self-contained.

## Pages

### Every link 404s

`APP_URL_MODE=path` without working rewrite rules. Set `APP_URL_MODE=query` in
`.env`, which needs no server configuration, or fix the rewrite rules. See
[Routing](../architecture/routing.md).

### A GET form goes to the board index instead of searching

The form is missing its route field. In query mode the action's query string is
discarded by the browser. Every GET form needs both helpers:

```php
<form method="get" action="<?= $this->formAction('search') ?>">
    <?= $this->routeField('search') ?>
```

A feature test enforces this across every template.

### URLs contain `%2F`

Something is building the `r` parameter with `http_build_query()` instead of
`Url::to()`/`Url::route()`, which encode each segment and rejoin with real
slashes.

### A page 405s

The path matched a route but not the verb — usually a form declaring `method="get"`
against a POST route, or a missing `method="post"`.

### Styling is missing or a colour does not apply

You used an inline `style` attribute. `style-src 'self'` blocks them silently.
Use a class; for dynamic values, generate one in `DynamicStyles`.

### A role colour does not show

Every username must render through `partials/username.php`. A name printed any
other way gets no role class. Check also that the role has a colour set and
that `/board-styles.css` is being served.

### A theme change does nothing

- The theme must be **active**, not merely installed.
- Your CSS rule must use the custom property the setting writes
  (`var(--accent)`), not a literal colour.
- A setting only exists if it is declared in `theme.json`.

## Posting and content

### Replies are accepted in a locked topic

Only moderators should get that, and they get a notice saying why. If an
ordinary member can, the route is missing `TopicPolicy::reply()` — the check
belongs in the controller as well as the template.

### A code block is not highlighted

`[code=lang]` and `[code]` both highlight; the language label is cosmetic. If
nothing is coloured, the theme's `--tok-*` properties are all the same colour,
or the block reached the page through `plain()` rather than `render()`.

### The highlighter throws a `preg` warning

`pattern()` uses `/` as its delimiter and the patterns contain `//`, `/*` and
`*/`. Every literal slash in there must stay escaped.

### A censored word is not replaced

Matching is whole-word. `censored_words` takes one entry per line or
comma-separated, and the replacement comes from `censor_replacement`.

## Uploads

### A real photograph is rejected

Read the message; each check says a different thing.

- *"contains code, which an image never does"* — a marker scan matched. If you
  added a marker shorter than five bytes, that is why: two-byte sequences occur
  by chance roughly once per 64 KB of compressed data.
- *"extra data appended after the image ends"* — genuine trailing data. Some
  cameras and editors append proprietary blocks; re-saving in any editor fixes
  it.
- *"more pixels than the board will decode"* — raise `max_pixels` in
  `config/uploads.php` if you mean to accept it.

### "That file could not be processed"

Neither GD nor Imagick is available, so the mandatory re-encode cannot run and
the upload is refused rather than stored unverified. Install one, or switch
avatar uploads off in **Admin → Settings → Uploads**.

### Uploads 403 or 500 with no message

`public/uploads/avatars/` is not writable by the web server user.

## Accounts

### A suspended member can still sign in

That is deliberate. A suspension lets the member in, lands them on **Your
record**, shows a persistent banner and blocks every write route. A **ban**
refuses at sign-in. If a suspended member can post, `RestrictionMiddleware` is
missing from that route.

### A member gets two alerts for one post

They should not: `dispatchForReply()` applies precedence — quote beats mention
beats reply — and emits one alert per person. If you added a notification call
elsewhere in the posting path, remove it and extend that method instead.

### Members get an alert for every private message

`notify_messages` defaults to off, because the inbox badge already says there
is mail. A member can switch it on in their notification preferences.

## Database

### "Invalid default value for …" on migrate

`DEFAULT UTC_TIMESTAMP()` in a migration. MariaDB accepts it; MySQL does not.
Use `DEFAULT CURRENT_TIMESTAMP` — every connection sets `time_zone = '+00:00'`,
so it is UTC on both.

### Timestamps are hours out

A connection is not setting the session timezone, or a member's profile
timezone is wrong. The database stores UTC and PHP converts for display; no
conversion belongs in SQL.

### Counters disagree with reality

`topics.post_count`, `forums.last_post_at` and friends are denormalised and
maintained inside the writing transaction. If something wrote to `posts`
directly — a manual `INSERT`, a half-finished import — they drift. There is no
recount command; rebuild them with SQL:

```sql
UPDATE topics t SET post_count =
    (SELECT COUNT(*) FROM posts p WHERE p.topic_id = t.id AND p.deleted_at IS NULL);

UPDATE forums f SET
    topic_count = (SELECT COUNT(*) FROM topics t WHERE t.forum_id = f.id AND t.deleted_at IS NULL),
    post_count  = (SELECT COUNT(*) FROM posts p WHERE p.forum_id = f.id AND p.deleted_at IS NULL);

UPDATE users u SET
    post_count  = (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id AND p.deleted_at IS NULL),
    topic_count = (SELECT COUNT(*) FROM topics t WHERE t.user_id = u.id AND t.deleted_at IS NULL);
```

Take a backup first, and prefer writing to the tables only through
`TopicService` so this does not recur.

## Tests

### Feature tests are skipped

No reachable, seeded database. `Harness::databaseReachable()` and `seeded()`
decide, and the runner says so.

### Tests fail on the second run but pass on the first

Left-behind state. Rows created by a test and not deleted (the duplicate-report
guard is the usual one), rate-limit counters, a setting changed and not
restored, or a URL mode left switched.

### One test fails only when the whole suite runs

Order-dependent shared state — see [Testing → Isolation](testing.md#isolation).

## Production

### A stack trace is visible to members

`APP_DEBUG=true`. Set it to `false`; detail goes to `storage/logs/app.log`.

### Sessions drop on every request

`storage/` is not writable, or `SESSION_SECURE=true` without HTTPS — the
browser then refuses to send the cookie back.

### Nothing at all loads after a change

Check `storage/logs/app.log`. If that is empty, the failure is before boot:
a PHP parse error, or a PHP version below 8.1. `php -l` on the file you edited.
