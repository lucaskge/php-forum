# Routes

Every registered address, in the order the router tries them. Link to one
with its name — `$this->route('topic.show', ['slug' => $slug])` — never by
writing the path out.

!!! info "Generated"
    This page is written by `php bin/console docs:reference`. Edit the code,
    not this file — the next run overwrites it.

161 routes in 17 groups.

## Account

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/account/restricted` | `account.restricted` | `csrf`, `maintenance` |

## Admin

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/admin/users` | `admin.users` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.users` |
| GET | `/admin/users/{id:\d+}` | `admin.user.edit` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.users` |
| POST | `/admin/users/{id:\d+}` | `admin.user.update` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.users` |
| POST | `/admin/users/{id:\d+}/password` | `admin.user.password` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.users` |
| POST | `/admin/users/{id:\d+}/avatar` | `admin.user.avatar` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.users` |
| GET | `/admin/users/{id:\d+}/delete` | `admin.user.delete` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.users` |
| POST | `/admin/users/{id:\d+}/delete` | `admin.user.destroy` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.users` |
| GET | `/admin/bans` | `admin.bans` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.users` |
| POST | `/admin/bans/{id:\d+}/lift` | `admin.ban.lift` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.users` |
| GET | `/admin/roles` | `admin.roles` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.roles` |
| GET | `/admin/roles/new` | `admin.role.create` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.roles` |
| POST | `/admin/roles/new` | `admin.role.store` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.roles` |
| GET | `/admin/roles/{id:\d+}` | `admin.role.edit` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.roles` |
| POST | `/admin/roles/{id:\d+}` | `admin.role.update` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.roles` |
| POST | `/admin/roles/{id:\d+}/delete` | `admin.role.destroy` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.roles` |
| GET | `/admin/permissions` | `admin.permissions` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.roles` |
| GET | `/admin/forums` | `admin.forums` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.forums` |
| POST | `/admin/forums/order` | `admin.forums.order` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.forums` |
| POST | `/admin/forums/recount` | `admin.forums.recount` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.forums` |
| GET | `/admin/forums/category` | `admin.category.form` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.forums` |
| POST | `/admin/forums/category` | `admin.category.save` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.forums` |
| POST | `/admin/forums/category/{id:\d+}/delete` | `admin.category.delete` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.forums` |
| GET | `/admin/forums/forum` | `admin.forum.form` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.forums` |
| POST | `/admin/forums/forum` | `admin.forum.save` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.forums` |
| POST | `/admin/forums/forum/{id:\d+}/delete` | `admin.forum.delete` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.forums` |
| GET | `/admin/forums/forum/{id:\d+}/permissions` | `admin.forum.permissions` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.forums` |
| POST | `/admin/forums/forum/{id:\d+}/permissions` | `admin.forum.permissions.save` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.forums` |
| GET | `/admin/topics` | `admin.topics` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.content` |
| POST | `/admin/topics/{id:\d+}/purge` | `admin.topic.purge` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.content` |
| POST | `/admin/topics/{id:\d+}/restore` | `admin.topic.restore` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.content` |
| GET | `/admin/posts` | `admin.posts` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.content` |
| POST | `/admin/posts/{id:\d+}/purge` | `admin.post.purge` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.content` |
| POST | `/admin/posts/{id:\d+}/restore` | `admin.post.restore` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.content` |
| GET | `/admin/settings` | `admin.settings` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.settings` |
| GET | `/admin/settings/{group}` | `admin.settings.group` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.settings` |
| POST | `/admin/settings/{group}` | `admin.settings.save` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.settings` |
| GET | `/admin/themes` | `admin.themes` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.themes` |
| POST | `/admin/themes/sync` | `admin.themes.sync` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.themes` |
| GET | `/admin/themes/{slug}` | `admin.theme` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.themes` |
| GET | `/admin/themes/{slug}/appearance` | `admin.theme.appearance` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.themes` |
| POST | `/admin/themes/{slug}/appearance` | `admin.theme.appearance.save` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.themes` |
| POST | `/admin/themes/{slug}/appearance/reset` | `admin.theme.appearance.reset` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.themes` |
| POST | `/admin/themes/{slug}/activate` | `admin.theme.activate` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.themes` |
| POST | `/admin/themes/{slug}/toggle` | `admin.theme.toggle` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.themes` |
| GET | `/admin/chat` | `admin.chat` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.chat` |
| GET | `/admin/chat/room` | `admin.chat.room` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.chat` |
| POST | `/admin/chat/room` | `admin.chat.room.save` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.chat` |
| POST | `/admin/chat/room/{id:\d+}/delete` | `admin.chat.room.delete` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.chat` |
| GET | `/admin/chat/restrictions` | `admin.chat.bans` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.chat` |
| POST | `/admin/chat/restrictions/{id:\d+}/lift` | `admin.chat.ban.lift` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.chat` |
| GET | `/admin/system` | `admin.system` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.system` |
| GET | `/admin/logs` | `admin.logs` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.system` |
| POST | `/admin/logs/clear` | `admin.logs.clear` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.system` |
| GET | `/admin/maintenance` | `admin.maintenance` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.system` |
| POST | `/admin/maintenance/task` | `admin.maintenance.task` | `csrf`, `maintenance`, `auth`, `can:admin.access`, `can:admin.system` |

## Auth

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/login` | `auth.login.show` | `csrf`, `maintenance`, `guest` |
| POST | `/login` | `auth.login` | `csrf`, `maintenance`, `guest` |
| GET | `/register` | `auth.register.show` | `csrf`, `maintenance`, `guest` |
| POST | `/register` | `auth.register` | `csrf`, `maintenance`, `guest`, `throttle:register` |
| GET | `/forgot-password` | `auth.forgot.show` | `csrf`, `maintenance`, `guest` |
| POST | `/forgot-password` | `auth.forgot` | `csrf`, `maintenance`, `guest` |
| GET | `/reset-password` | `auth.reset.show` | `csrf`, `maintenance`, `guest` |
| POST | `/reset-password` | `auth.reset` | `csrf`, `maintenance`, `guest` |
| POST | `/logout` | `auth.logout` | `csrf`, `maintenance`, `auth` |

## Board

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/` | `home` | `csrf`, `maintenance` |
| GET | `/members` | `members` | `csrf`, `maintenance` |
| GET | `/online` | `online` | `csrf`, `maintenance` |
| GET | `/rules` | `rules` | `csrf`, `maintenance` |
| GET | `/help` | `help` | `csrf`, `maintenance` |
| GET | `/maintenance` | `maintenance` | `csrf`, `maintenance` |
| GET | `/search` | `search` | `csrf`, `maintenance`, `can:search.use` |
| GET | `/notifications` | `notifications` | `csrf`, `maintenance`, `auth` |
| GET | `/chat` | `chat` | `csrf`, `maintenance`, `setting:chat_enabled` |
| GET | `/moderation` | `moderation` | `csrf`, `maintenance`, `auth`, `can:moderation.access` |
| GET | `/admin` | `admin` | `csrf`, `maintenance`, `auth`, `can:admin.access` |

## Chat

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/chat/room/{room}` | `chat.room` | `csrf`, `maintenance`, `setting:chat_enabled` |
| POST | `/chat/room/{room}/send` | `chat.send` | `csrf`, `maintenance`, `setting:chat_enabled`, `auth`, `restricted`, `can:chat.post`, `throttle:chat` |
| POST | `/chat/room/{room}/message/{id:\d+}/delete` | `chat.message.delete` | `csrf`, `maintenance`, `setting:chat_enabled`, `auth`, `can:chat.moderate` |
| GET | `/chat/room/{room}/moderate` | `chat.moderate` | `csrf`, `maintenance`, `setting:chat_enabled`, `auth`, `can:chat.moderate` |
| POST | `/chat/room/{room}/moderate` | `chat.moderate.apply` | `csrf`, `maintenance`, `setting:chat_enabled`, `auth`, `can:chat.moderate` |

## Forum

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/forum/{slug}` | `forum.show` | `csrf`, `maintenance` |
| GET | `/forum/{forum}/topic/{topic}` | `forum.topic` | `csrf`, `maintenance` |

## Forums

| Method | Address | Name | Middleware |
|---|---|---|---|
| POST | `/forums/mark-read` | `forums.mark-read` | `csrf`, `maintenance`, `auth` |

## Messages

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/messages` | `messages.inbox` | `csrf`, `maintenance`, `auth`, `can:message.send` |
| GET | `/messages/sent` | `messages.sent` | `csrf`, `maintenance`, `auth`, `can:message.send` |
| GET | `/messages/compose` | `messages.compose` | `csrf`, `maintenance`, `auth`, `can:message.send`, `restricted` |
| POST | `/messages/compose` | `messages.send` | `csrf`, `maintenance`, `auth`, `can:message.send`, `restricted`, `throttle:message` |
| GET | `/messages/{id:\d+}` | `messages.show` | `csrf`, `maintenance`, `auth`, `can:message.send` |
| POST | `/messages/{id:\d+}/read` | `messages.read` | `csrf`, `maintenance`, `auth`, `can:message.send` |
| POST | `/messages/{id:\d+}/delete` | `messages.delete` | `csrf`, `maintenance`, `auth`, `can:message.send` |

## Moderation

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/moderation/queue` | `moderation.queue` | `csrf`, `maintenance`, `auth`, `can:moderation.access` |
| GET | `/moderation/reports` | `moderation.reports` | `csrf`, `maintenance`, `auth`, `can:moderation.access`, `can:report.view` |
| GET | `/moderation/reports/{id:\d+}` | `moderation.report` | `csrf`, `maintenance`, `auth`, `can:moderation.access`, `can:report.view` |
| POST | `/moderation/reports/{id:\d+}` | `moderation.report.handle` | `csrf`, `maintenance`, `auth`, `can:moderation.access`, `can:report.handle` |
| GET | `/moderation/users` | `moderation.users` | `csrf`, `maintenance`, `auth`, `can:moderation.access` |
| GET | `/moderation/users/{username}` | `moderation.user` | `csrf`, `maintenance`, `auth`, `can:moderation.access` |
| POST | `/moderation/users/{username}/warn` | `moderation.user.warn` | `csrf`, `maintenance`, `auth`, `can:moderation.access`, `can:user.warn` |
| POST | `/moderation/users/{username}/suspend` | `moderation.user.suspend` | `csrf`, `maintenance`, `auth`, `can:moderation.access`, `can:user.suspend` |
| POST | `/moderation/users/{username}/ban` | `moderation.user.ban` | `csrf`, `maintenance`, `auth`, `can:moderation.access`, `can:user.ban` |
| POST | `/moderation/users/{username}/lift` | `moderation.user.lift` | `csrf`, `maintenance`, `auth`, `can:moderation.access`, `can:user.suspend|user.ban` |
| POST | `/moderation/users/{username}/notes` | `moderation.user.note` | `csrf`, `maintenance`, `auth`, `can:moderation.access` |
| POST | `/moderation/users/{username}/notes/{note:\d+}/delete` | `moderation.user.note.delete` | `csrf`, `maintenance`, `auth`, `can:moderation.access` |
| GET | `/moderation/log` | `moderation.log` | `csrf`, `maintenance`, `auth`, `can:moderation.access`, `can:moderation.log.view` |
| GET | `/moderation/topic/{slug}` | `moderation.topic` | `csrf`, `maintenance`, `auth`, `can:moderation.access` |
| POST | `/moderation/topic/{slug}/flag` | `moderation.topic.flag` | `csrf`, `maintenance`, `auth`, `can:moderation.access` |
| POST | `/moderation/topic/{slug}/move` | `moderation.topic.move` | `csrf`, `maintenance`, `auth`, `can:moderation.access` |
| GET | `/moderation/topic/{slug}/merge` | `moderation.topic.merge` | `csrf`, `maintenance`, `auth`, `can:moderation.access` |
| POST | `/moderation/topic/{slug}/merge` | `moderation.topic.merge.apply` | `csrf`, `maintenance`, `auth`, `can:moderation.access` |
| GET | `/moderation/topic/{slug}/split` | `moderation.topic.split` | `csrf`, `maintenance`, `auth`, `can:moderation.access` |
| POST | `/moderation/topic/{slug}/split` | `moderation.topic.split.apply` | `csrf`, `maintenance`, `auth`, `can:moderation.access` |
| POST | `/moderation/topic/{slug}/restore` | `moderation.topic.restore` | `csrf`, `maintenance`, `auth`, `can:moderation.access` |

## Notifications

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/notifications/{id:\d+}` | `notifications.open` | `csrf`, `maintenance`, `auth` |
| POST | `/notifications/read-all` | `notifications.read-all` | `csrf`, `maintenance`, `auth` |
| POST | `/notifications/clear` | `notifications.clear` | `csrf`, `maintenance`, `auth` |
| POST | `/notifications/{id:\d+}/delete` | `notifications.delete` | `csrf`, `maintenance`, `auth` |

## Post

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/post/{id:\d+}` | `post.permalink` | `csrf`, `maintenance` |
| GET | `/post/{id:\d+}/history` | `post.history` | `csrf`, `maintenance`, `auth` |
| GET | `/post/{id:\d+}/edit` | `post.edit` | `csrf`, `maintenance`, `auth`, `restricted` |
| POST | `/post/{id:\d+}/edit` | `post.update` | `csrf`, `maintenance`, `auth`, `restricted` |
| GET | `/post/{id:\d+}/delete` | `post.delete` | `csrf`, `maintenance`, `auth`, `restricted` |
| POST | `/post/{id:\d+}/delete` | `post.destroy` | `csrf`, `maintenance`, `auth`, `restricted` |
| POST | `/post/{id:\d+}/restore` | `post.restore` | `csrf`, `maintenance`, `auth`, `restricted` |
| POST | `/post/{id:\d+}/visibility` | `post.visibility` | `csrf`, `maintenance`, `auth`, `restricted` |
| GET | `/post/{id:\d+}/report` | `post.report` | `csrf`, `maintenance`, `auth`, `restricted`, `can:report.create` |
| POST | `/post/{id:\d+}/report` | `post.report.store` | `csrf`, `maintenance`, `auth`, `restricted`, `can:report.create`, `throttle:report` |

## Search

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/recent` | `search.recent` | `csrf`, `maintenance`, `can:search.use` |

## Settings

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/settings/profile` | `settings.profile` | `csrf`, `maintenance`, `auth` |
| POST | `/settings/profile` | `settings.profile.save` | `csrf`, `maintenance`, `auth` |
| GET | `/settings/avatar` | `settings.avatar` | `csrf`, `maintenance`, `auth` |
| POST | `/settings/avatar` | `settings.avatar.save` | `csrf`, `maintenance`, `auth` |
| POST | `/settings/avatar/delete` | `settings.avatar.delete` | `csrf`, `maintenance`, `auth` |
| GET | `/settings/password` | `settings.password` | `csrf`, `maintenance`, `auth` |
| POST | `/settings/password` | `settings.password.save` | `csrf`, `maintenance`, `auth` |
| GET | `/settings/account` | `settings.account` | `csrf`, `maintenance`, `auth` |
| POST | `/settings/account` | `settings.account.save` | `csrf`, `maintenance`, `auth` |
| GET | `/settings/preferences` | `settings.preferences` | `csrf`, `maintenance`, `auth` |
| POST | `/settings/preferences` | `settings.preferences.save` | `csrf`, `maintenance`, `auth` |
| GET | `/settings/subscriptions` | `settings.subscriptions` | `csrf`, `maintenance`, `auth` |
| GET | `/settings/bookmarks` | `settings.bookmarks` | `csrf`, `maintenance`, `auth` |
| GET | `/settings/record` | `settings.record` | `csrf`, `maintenance`, `auth` |

## Styles

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/board-styles.css` | `styles.dynamic` | — |

## Theme

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/theme/{theme}/settings.css` | `theme.settings.css` | — |
| GET | `/theme/{theme}/{path:.+}` | `theme.asset` | — |

## Topic

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/topic/{slug}` | `topic.show` | `csrf`, `maintenance` |
| GET | `/forum/{forum}/new-topic` | `topic.create` | `csrf`, `maintenance`, `auth`, `restricted` |
| POST | `/forum/{forum}/new-topic` | `topic.store` | `csrf`, `maintenance`, `auth`, `restricted`, `throttle:post` |
| GET | `/topic/{slug}/reply` | `topic.reply` | `csrf`, `maintenance`, `auth`, `restricted` |
| POST | `/topic/{slug}/reply` | `topic.reply.store` | `csrf`, `maintenance`, `auth`, `restricted`, `throttle:post` |
| GET | `/topic/{slug}/edit` | `topic.edit` | `csrf`, `maintenance`, `auth`, `restricted` |
| POST | `/topic/{slug}/edit` | `topic.update` | `csrf`, `maintenance`, `auth`, `restricted` |
| GET | `/topic/{slug}/delete` | `topic.delete` | `csrf`, `maintenance`, `auth`, `restricted` |
| POST | `/topic/{slug}/delete` | `topic.destroy` | `csrf`, `maintenance`, `auth`, `restricted` |
| POST | `/topic/{slug}/subscribe` | `topic.subscribe` | `csrf`, `maintenance`, `auth`, `restricted` |
| POST | `/topic/{slug}/bookmark` | `topic.bookmark` | `csrf`, `maintenance`, `auth`, `restricted` |

## User

| Method | Address | Name | Middleware |
|---|---|---|---|
| GET | `/user/{username}` | `user.profile` | `csrf`, `maintenance` |
| GET | `/user/{username}/topics` | `user.topics` | `csrf`, `maintenance` |
| GET | `/user/{username}/posts` | `user.posts` | `csrf`, `maintenance` |
| GET | `/user/{username}/activity` | `user.activity` | `csrf`, `maintenance` |
| GET | `/user/{username}/report` | `user.report` | `csrf`, `maintenance`, `auth`, `can:report.create` |
| POST | `/user/{username}/report` | `user.report.store` | `csrf`, `maintenance`, `auth`, `restricted`, `can:report.create`, `throttle:report` |

