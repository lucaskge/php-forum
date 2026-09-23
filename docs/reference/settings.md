# Settings

Every board setting, as stored. Change them on **Admin → Settings**; the
screen builds itself from these rows, so adding one is an insert and not a
template change.

!!! info "Generated"
    This page is written by `php bin/console docs:reference`. Edit the code,
    not this file — the next run overwrites it.

28 settings in 5 groups.

## Chat

| Key | Type | Default here | Does |
|---|---|---|---|
| `chat_enabled` | boolean | on | **Chat enabled** — Turn the chat area on or off for everyone. |
| `chat_rules` | text | `Keep it civil and keep it readable. No floo…` | **Chat rules** — Shown in the sidebar of the chat page. |
| `chat_max_length` | integer | `500` | **Message length limit** —  |
| `chat_slow_mode` | integer | `0` | **Global slow mode (seconds)** — Minimum delay between messages from the same member. Rooms can set a higher value. |
| `chat_history_limit` | integer | `60` | **Messages shown** — How many recent messages the transcript renders. |
| `chat_presence_window` | integer | `300` | **Presence window (seconds)** — How long a member stays listed in the room after loading it. |
| `chat_transport` | select | `http` | **Chat transport** — Implementation used to deliver messages. Additional transports register themselves in TransportFactory. One of: `http`. |

## Forums

| Key | Type | Default here | Does |
|---|---|---|---|
| `topics_per_page` | integer | `25` | **Topics per page** — Rows in a forum listing. |
| `posts_per_page` | integer | `15` | **Posts per page** — Posts shown per page in a topic; members may override this. |
| `items_per_page` | integer | `20` | **Rows per page elsewhere** — Search results, member lists, messages and admin tables. |
| `online_window_minutes` | integer | `15` | **Online window (minutes)** — How long after their last request a member counts as online. |

## General

| Key | Type | Default here | Does |
|---|---|---|---|
| `site_name` | string | `Forum` | **Board name** — Shown in the masthead, page titles and e-mail. |
| `site_tagline` | string | `technical discussion, quietly` | **Tagline** — Short line under the board name. |
| `site_description` | string | `A technical discussion board for systems, n…` | **Meta description** — Used by search engines on the index page. |
| `announcement` | text | *(empty)* | **Announcement** — Shown at the top of the board index. Board formatting works. Leave empty to hide it. |
| `board_rules` | text | `[b]1. Stay on topic.[/b] Threads drift; pos…` | **Board rules** — Shown on /rules and during registration. |
| `contact_email` | string | `staff@localhost` | **Contact address** — Where members should write about account problems. |
| `maintenance_mode` | boolean | off | **Maintenance mode** — When on, only administrators can reach the board. |
| `maintenance_message` | text | `The board is offline for scheduled maintena…` | **Maintenance message** — Shown while maintenance mode is on. |

## Posting

| Key | Type | Default here | Does |
|---|---|---|---|
| `min_post_length` | integer | `5` | **Minimum post length** — Characters required in a post or reply. |
| `edit_window_minutes` | integer | `0` | **Self-edit window (minutes)** — How long members may edit their own posts. 0 means no limit. |
| `search_min_length` | integer | `3` | **Minimum search term length** —  |
| `censored_words` | text | *(empty)* | **Word filter** — One word or phrase per line (or separated by commas). Replaced when a post is displayed, never in what is stored, so the original stays available to moderators and the list can be changed at any time. Whole words only, case and accents ignored. |
| `censor_replacement` | string | `***` | **Replace filtered words with** — What readers see in place of a filtered word. |

## Registration

| Key | Type | Default here | Does |
|---|---|---|---|
| `registration_enabled` | boolean | on | **Registration open** — Turn off to close new sign-ups. |
| `registration_closed_message` | text | `Registration is closed at the moment. Watch…` | **Closed message** — Shown when registration is off. |
| `avatars_enabled` | boolean | on | **Allow avatar uploads** — When off, everyone uses the generated monogram. |
| `signature_max_length` | integer | `400` | **Signature length limit** — Characters allowed in a member signature. |

