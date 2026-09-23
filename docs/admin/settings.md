# Site settings

*Admin → Site settings*. Stored in the database, applied immediately, grouped
into five tabs. Static configuration — throttles, password policy, upload
rules, response headers — lives in `/config` and is not editable at runtime.

For the full table with defaults and types, see
[Settings reference](../reference/settings.md).

## General

| Setting | What it does |
|---|---|
| Board name | Masthead, page titles, e-mail |
| Tagline | The line under the board name |
| Meta description | What search engines show for the index |
| Announcement | A banner at the top of the index. Board formatting works. Empty hides it |
| Board rules | Shown on `/rules` and during registration |
| Contact address | Where members should write about account problems |
| Maintenance mode | See below |
| Maintenance message | Shown while it is on |

### Maintenance mode

Everybody except administrators is redirected to the offline notice —
**including on form submissions**, so nothing is written to the board while it
is on. Administrators keep working normally.

Toggle it here or from *System → Maintenance*.

## Registration

| Setting | |
|---|---|
| Registration open | Turn off to close sign-ups. Both the form and a direct POST are refused |
| Closed message | Shown in place of the form |
| Allow avatar uploads | When off, everybody uses the generated monogram |
| Signature length limit | Characters allowed in a signature |

## Forums

| Setting | Default | |
|---|---|---|
| Topics per page | 25 | Rows in a forum listing |
| Posts per page | 15 | Members can override this for themselves |
| Rows per page elsewhere | 20 | Search, member lists, messages, admin tables |
| Online window | 15 min | How long after their last request somebody counts as online |

## Posting

| Setting | Default | |
|---|---|---|
| Minimum post length | 5 | Characters required in a post |
| Self-edit window | 0 | Minutes a member may edit their own post. 0 means no limit |
| Minimum search term | 3 | Characters |
| **Word filter** | empty | One word or phrase per line, or comma-separated |
| Replace filtered words with | `***` | |

The word filter is applied **when a post is displayed**, never to what is
stored. The original stays available to moderators, the list can change at any
time, and the change applies retroactively. Matching is whole-word and ignores
case and accents, so a short entry cannot mangle a longer legitimate word.

## Chat

| Setting | Default | |
|---|---|---|
| Chat enabled | on | Off closes `/chat` for everybody, staff included |
| Chat rules | | Shown in the sidebar of the chat page |
| Message length limit | 500 | |
| Global slow mode | 0 | Seconds between messages from one member. Rooms can set a higher value |
| Messages shown | 60 | How much transcript is rendered |
| Presence window | 300 | Seconds somebody stays listed after loading the room |
| Chat transport | `http` | See [Chat transports](../architecture/content.md#chat-transports) |

## Adding a setting

Settings are rows in the `settings` table, seeded by
`database/seeders/CoreSeeder.php`. Each has a key, a value, a type
(`string`, `text`, `integer`, `boolean`, `select`), a group, a label, a
description and optional select options.

Add one there and it appears on the matching tab with the right control, with
no template change. Read it with `SettingsService::instance()->bool('my_key')`
or its `string`, `int` siblings.
