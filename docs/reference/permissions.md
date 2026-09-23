# Permissions

The global permission catalogue as this board has it. Grant them on
**Admin → Roles → edit**. A role holding `*` holds everything, present and
future.

!!! info "Generated"
    This page is written by `php bin/console docs:reference`. Edit the code,
    not this file — the next run overwrites it.

46 permissions in 9 groups.

## Who holds what

| Permission | Guest | Member | Trusted member | Moderator | Administrator |
|---|---|---|---|---|---|
| `admin.access` | · | · | · | · | ✳ |
| `admin.users` | · | · | · | · | ✳ |
| `admin.roles` | · | · | · | · | ✳ |
| `user.role.manage` | · | · | · | · | ✳ |
| `admin.forums` | · | · | · | · | ✳ |
| `admin.content` | · | · | · | · | ✳ |
| `admin.settings` | · | · | · | · | ✳ |
| `admin.themes` | · | · | · | · | ✳ |
| `admin.chat` | · | · | · | · | ✳ |
| `admin.system` | · | · | · | · | ✳ |
| `chat.post` | · | ✓ | ✓ | ✓ | ✳ |
| `chat.moderate` | · | · | · | ✓ | ✳ |
| `search.use` | · | ✓ | ✓ | ✓ | ✳ |
| `message.send` | · | ✓ | ✓ | ✓ | ✳ |
| `report.create` | · | ✓ | ✓ | ✓ | ✳ |
| `moderation.access` | · | · | · | ✓ | ✳ |
| `moderation.forums.all` | · | · | · | ✓ | ✳ |
| `moderation.log.view` | · | · | · | ✓ | ✳ |
| `report.view` | · | · | · | ✓ | ✳ |
| `report.handle` | · | · | · | ✓ | ✳ |
| `topic.pin` | · | · | · | ✓ | ✳ |
| `topic.lock` | · | · | · | ✓ | ✳ |
| `topic.move` | · | · | · | ✓ | ✳ |
| `topic.merge` | · | · | · | ✓ | ✳ |
| `topic.split` | · | · | · | ✓ | ✳ |
| `topic.hide` | · | · | · | ✓ | ✳ |
| `user.warn` | · | · | · | ✓ | ✳ |
| `user.suspend` | · | · | · | ✓ | ✳ |
| `user.ban` | · | · | · | · | ✳ |
| `post.edit.own` | · | ✓ | ✓ | ✓ | ✳ |
| `post.delete.own` | · | ✓ | ✓ | ✓ | ✳ |
| `post.edit.any` | · | · | · | ✓ | ✳ |
| `post.delete.any` | · | · | · | ✓ | ✳ |
| `post.hide` | · | · | · | ✓ | ✳ |
| `post.history.view` | · | · | ✓ | ✓ | ✳ |
| `profile.edit` | · | ✓ | ✓ | ✓ | ✳ |
| `avatar.upload` | · | ✓ | ✓ | ✓ | ✳ |
| `*` | · | · | · | · | ✓ |
| `topic.create` | · | ✓ | ✓ | ✓ | ✳ |
| `topic.reply` | · | ✓ | ✓ | ✓ | ✳ |
| `topic.edit.own` | · | ✓ | ✓ | ✓ | ✳ |
| `topic.delete.own` | · | ✓ | ✓ | ✓ | ✳ |
| `topic.edit.any` | · | · | · | ✓ | ✳ |
| `topic.delete.any` | · | · | · | ✓ | ✳ |
| `topic.subscribe` | · | ✓ | ✓ | ✓ | ✳ |
| `topic.bookmark` | · | ✓ | ✓ | ✓ | ✳ |

✓ granted directly &nbsp;·&nbsp; ✳ held through the `*` wildcard &nbsp;·&nbsp; · not held

## Administration

| Slug | Allows |
|---|---|
| `admin.access` | **Open the administration area** —  |
| `admin.users` | **Manage user accounts** —  |
| `admin.roles` | **Manage roles and permissions** —  |
| `user.role.manage` | **Assign roles to members** —  |
| `admin.forums` | **Manage categories and forums** —  |
| `admin.content` | **Manage topics and posts** —  |
| `admin.settings` | **Change board settings** —  |
| `admin.themes` | **Manage themes** —  |
| `admin.chat` | **Manage chat rooms and transport** —  |
| `admin.system` | **System information, logs and maintenance** —  |

## Chat

| Slug | Allows |
|---|---|
| `chat.post` | **Post in chat** —  |
| `chat.moderate` | **Moderate chat** — Delete messages, mute and ban from chat. |

## General

| Slug | Allows |
|---|---|
| `search.use` | **Use search** — Run searches over readable forums. |

## Messaging

| Slug | Allows |
|---|---|
| `message.send` | **Use private messages** —  |
| `report.create` | **Report content** —  |

## Moderation

| Slug | Allows |
|---|---|
| `moderation.access` | **Open the moderation area** —  |
| `moderation.forums.all` | **Moderate every readable forum** — Grants moderation on any forum the role can read. |
| `moderation.log.view` | **Read the moderation log** —  |
| `report.view` | **View reports** —  |
| `report.handle` | **Resolve reports** —  |
| `topic.pin` | **Pin topics** —  |
| `topic.lock` | **Lock and archive topics** —  |
| `topic.move` | **Move topics** —  |
| `topic.merge` | **Merge topics** —  |
| `topic.split` | **Split topics** —  |
| `topic.hide` | **Hide topics** —  |
| `user.warn` | **Warn members** —  |
| `user.suspend` | **Suspend members** —  |
| `user.ban` | **Ban members** —  |

## Posts

| Slug | Allows |
|---|---|
| `post.edit.own` | **Edit own posts** — Subject to the edit window setting. |
| `post.delete.own` | **Delete own posts** —  |
| `post.edit.any` | **Edit any post** — Requires moderation rights on the forum. |
| `post.delete.any` | **Delete any post** — Requires moderation rights on the forum. |
| `post.hide` | **Hide posts** — Keep a post in place but out of sight of members. |
| `post.history.view` | **View edit history** — See every revision of any post. |

## Profile

| Slug | Allows |
|---|---|
| `profile.edit` | **Edit own profile** —  |
| `avatar.upload` | **Upload an avatar** —  |

## System

| Slug | Allows |
|---|---|
| `*` | **Full access** — Bypasses every other check. Reserve it for administrators. |

## Topics

| Slug | Allows |
|---|---|
| `topic.create` | **Start topics** —  |
| `topic.reply` | **Reply to topics** —  |
| `topic.edit.own` | **Edit own topics** — Change the subject of a topic they started. |
| `topic.delete.own` | **Delete own topics** — Only while nobody has replied. |
| `topic.edit.any` | **Edit any topic** —  |
| `topic.delete.any` | **Delete any topic** —  |
| `topic.subscribe` | **Subscribe to topics** —  |
| `topic.bookmark` | **Bookmark topics** —  |

