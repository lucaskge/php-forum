# Chat and system

## Chat

*Admin → Chat*.

### Transport

Which implementation delivers messages, what it is capable of, and which
transports are registered. The shipped one is `http`: messages are stored on
POST and the transcript is re-rendered on the following GET.

Adding real-time delivery means implementing one interface and selecting it
here. See [Chat transports](../architecture/content.md#chat-transports).

### Rooms

Name, identifier, description, topic line, position, and:

| | |
|---|---|
| **Active** | An inactive room is not reachable |
| **Read-only** | Readable, no new messages |
| **Slow mode** | Minimum seconds between messages from one member. Adds to the global setting; the higher wins |

Deleting a room deletes its messages.

### Restrictions

Every chat mute and ban, who issued it, why, when it expires, and a lift
button.

## System information

*Admin → System*. Read-only, and administrator-only.

| Section | |
|---|---|
| Application | Environment, debug state, URL, timezone, whether `APP_KEY` is set |
| Runtime limits | `memory_limit`, `upload_max_filesize`, `post_max_size`, `max_execution_time` |
| Extensions | Which of the expected ones are loaded |
| Writable paths | Whether the board can write where it needs to |
| Database | Server version, and every table with its approximate row count and size |
| Migrations | Which have been applied, in which batch, and when |

The migrations table is the quickest way to tell whether an upgrade has been
completed.

## Logs

*Admin → Logs*. One tab per channel, newest lines first, up to 300.

| Channel | |
|---|---|
| `app` | Errors and uncaught exceptions |
| `security` | CSRF rejections, session fingerprint mismatches, refused uploads, password resets, administrative password changes |
| `mail` | Outgoing mail when `MAIL_TRANSPORT=log` — where password reset links land in development |

**Clear this log** empties the file.

Logs are written to `storage/logs/`, which is outside the document root and not
reachable from the web.

## Maintenance

*Admin → Maintenance*.

### Maintenance mode

Turns the board off for everybody except administrators, form submissions
included. The message shown is edited under
[Site settings](settings.md#maintenance-mode).

### Housekeeping

| Task | |
|---|---|
| **Purge session records** | Removes online-tracking rows older than a day |
| **Purge throttles** | Drops expired rate-limit buckets |
| **Expire lapsed suspensions** | Clears suspensions past their date and restores those accounts |

Nothing breaks if you never run them — every query filters on expiry — the
tables simply grow. To run them on a schedule:

```cron
17 * * * * cd /var/www/board && php bin/console maintenance:run > /dev/null
```
