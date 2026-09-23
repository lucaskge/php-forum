# Database

MySQL 5.7+ or MariaDB 10.3+, InnoDB, `utf8mb4_unicode_ci` throughout, so
emoji and every script store and compare correctly.

**Times are UTC.** Every timestamp column is `DATETIME` defaulting to
`CURRENT_TIMESTAMP`, and every connection opens with `SET time_zone = '+00:00'`
so that default is UTC regardless of the server's own zone. Display conversion
to each member's timezone happens in PHP (`Dates`), never in SQL.

!!! note "Why not `DEFAULT UTC_TIMESTAMP()`"
    MariaDB accepts it; MySQL rejects it outright and the whole migration
    fails. `CURRENT_TIMESTAMP` plus the session timezone gives the same result
    on both.

## Groups

The 31 tables fall into six groups, matching the six migrations.

### Identity — `001_identity.sql`

| Table | Holds |
|---|---|
| `roles` | Role definitions: slug, name, `colour`, `priority`, `is_default`, `is_guest`, `is_staff`, `is_system` |
| `permissions` | The permission catalogue: slug, label, `group_name`, position |
| `role_permissions` | Which roles hold which permissions (composite PK) |
| `users` | Accounts, profile, preferences, counters, `status` |
| `user_roles` | Additional roles beyond `users.primary_role_id` |
| `password_resets` | Hashed single-use reset tokens with expiry |
| `login_attempts` | Every attempt with IP and outcome, for throttling and audit |

Notes that matter:

- `users.username_canonical` is the lowercased name and carries the unique
  index — two members cannot differ only by case.
- `users.primary_role_id` is `ON DELETE SET NULL`; a member never disappears
  because a role was deleted.
- `roles.is_system` marks the roles the board itself depends on. They cannot
  be deleted from the admin panel.
- `roles.is_staff` is the only thing that produces the "Staff" badge.
- `roles.priority` decides which role's colour wins when a member holds
  several; lower number wins.

### Forum — `002_forum.sql`

| Table | Holds |
|---|---|
| `categories` | Top-level headings on the index |
| `forums` | Forums and subforums (`parent_id` self-reference) |
| `forum_permissions` | The role × forum matrix: view / read / create topic / reply / moderate |
| `topics` | Threads, their flags and denormalised counters |
| `posts` | Every post, including each topic's first post |
| `post_edits` | Before and after for every edit, with editor and reason |
| `topic_subscriptions` | Who is watching what |
| `bookmarks` | Saved topics |

- `topics.first_post_id` / `last_post_id` and the matching columns on `forums`
  are denormalised for listing speed. They are maintained inside the same
  transaction as the write, in `TopicService` — nothing else may set them.
- The listing index `(forum_id, deleted_at, is_pinned, last_post_at)` is what
  makes a forum page a single fast range scan.
- Deletion is soft: `deleted_at` + `deleted_by`. Nothing a moderator removes is
  actually gone until an administrator purges it.
- The two foreign keys from `topics` to `posts` are added at the end of the
  migration, after both tables exist — a chicken-and-egg constraint.

### Messaging — `003_messaging.sql`

| Table | Holds |
|---|---|
| `private_messages` | Sender, recipient, subject, body, read state, per-side deletion |
| `notifications` | Alerts: `type`, `title`, `body`, `url`, read state |

- `sender_deleted` / `recipient_deleted` are separate flags: each side removes
  its own copy, and the row only becomes eligible for purging when both are set.
- `notifications.url` is stored as an internal path (`/topic/x#post-9`), not a
  full URL, so the alert still resolves after a domain change or a URL-mode
  change.

### Moderation — `004_moderation.sql`

| Table | Holds |
|---|---|
| `reports` | What was reported, by whom, reason, status, outcome |
| `moderation_actions` | The append-only moderation log |
| `bans` | Bans and suspensions, with expiry and lift record |
| `warnings` | Warnings with points, expiry and acknowledgement |
| `user_notes` | Staff-only notes on an account |

- `reports.content_type` is an enum covering `post`, `topic`, `message`,
  `chat_message` and `user`, which is what lets a member report an account and
  not only a piece of text.
- `moderation_actions` is never updated or deleted by the application. Its
  `metadata` JSON column carries whatever context the action had.
- `bans.type` separates a `suspension` (expires) from a `ban` (does not).

### Chat — `005_chat.sql`

| Table | Holds |
|---|---|
| `chat_rooms` | Rooms, `min_role_id`, slow mode, read-only flag |
| `chat_messages` | Messages, soft deletion, IP |
| `chat_bans` | Room-scoped or global mutes and bans |
| `chat_presence` | Last-seen per room per member (composite PK) |

### System — `006_system.sql`

| Table | Holds |
|---|---|
| `settings` | Every board setting, with its type, group, label and options |
| `themes` | Installed themes, active flag and per-theme `settings` JSON |
| `sessions` | Session tracking for "who is online" |
| `rate_limits` | Fixed-window counters |
| `migrations` | Which migration files have run, and in which batch |

`settings` is self-describing: `type` (`string`, `text`, `integer`, `boolean`,
`select`), `group_name`, `label`, `description` and an `options` JSON list.
The admin settings screen renders itself from those rows, so adding a setting
is an insert — no template change.

## Migrations

Migrations are plain numbered `.sql` files in `database/migrations/`. They run
in filename order; each applied file is recorded in `migrations` with a batch
number, so re-running is a no-op.

```bash
php bin/console migrate          # apply everything pending
php bin/console migrate:fresh    # drop every table and rebuild (destructive)
php bin/console install          # migrate:fresh plus the demo data (destructive)
```

`Migrator` is shared by the console and the web installer, so both paths
produce byte-identical schemas.

### Adding one

Create the next number:

```sql
-- database/migrations/008_topic_tags.sql
CREATE TABLE topic_tags (
    topic_id  INT UNSIGNED NOT NULL,
    tag       VARCHAR(32) NOT NULL,
    PRIMARY KEY (topic_id, tag),
    CONSTRAINT fk_topic_tags_topic FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Rules:

- Never edit a migration that has run anywhere. Write the next one.
- `DEFAULT CURRENT_TIMESTAMP`, never `UTC_TIMESTAMP()`.
- Always `ENGINE=InnoDB` and the utf8mb4 collation, or foreign keys and
  comparisons behave differently from the rest of the schema.
- Statements are split on `;` at end of line, so keep one statement per
  statement and avoid stored routines in a migration file.

## Access from PHP

`Database` wraps PDO with `ERRMODE_EXCEPTION`, emulated prepares off and
`FETCH_ASSOC`:

```php
$db = Database::instance();

$rows  = $db->select('SELECT * FROM topics WHERE forum_id = ?', [$forumId]);
$row   = $db->selectOne('SELECT * FROM users WHERE id = ?', [$id]);
$total = $db->scalar('SELECT COUNT(*) FROM posts WHERE topic_id = ?', [$id]);

// Writes by table and column map — the SQL is built for you, always bound.
$postId = $db->insert('posts', ['topic_id' => $topicId, 'content' => $content]);
$db->update('topics', ['is_locked' => 1], 'id = ?', [$topicId]);
$db->delete('bookmarks', 'user_id = ? AND topic_id = ?', [$userId, $topicId]);

// Anything else: raw SQL with bindings, returning the affected row count.
$db->execute('UPDATE topics SET view_count = view_count + 1 WHERE id = ?', [$id]);

$db->transaction(function () use ($db) { … });   // nestable; uses savepoints
```

Every value is bound. Where a query needs a dynamic fragment — a sort column,
a direction — the repository picks it from a fixed internal allow-list and
never from the request. That is the whole injection story, and it is confined
to `app/Repositories/`.
