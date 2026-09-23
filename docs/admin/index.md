# Administration

`/admin`, for anyone holding `admin.access`. The sidebar groups every screen;
each entry appears only if you hold the permission behind it.

| Group | Screens |
|---|---|
| General | Dashboard, Site settings |
| Users | User list, Roles, Permissions, Bans |
| Forums | Categories & forums |
| Content | Topics, Posts, Reports, Moderation log |
| Appearance | Themes, Colours and type |
| Chat | Rooms & transport, Chat restrictions |
| System | System information, Logs, Maintenance |

## Dashboard

Totals for members, topics, posts, forums, private messages and chat messages,
each with how many arrived today and this week. Pending reports, members
online, active bans and suspended accounts.

Below: posts per day for the last fortnight as a bar chart drawn in CSS, the
busiest forums, the most recent moderation actions, the newest members, and a
short system summary.

Every tile links to the screen that explains it.

## The permission behind each screen

A role reaching `/admin` needs `admin.access`, and each screen needs its own
permission on top. An administrator holds `*`, which satisfies all of them.

| Screen | Permission |
|---|---|
| Dashboard | `admin.access` |
| Site settings | `admin.settings` |
| Users, Bans | `admin.users` |
| Roles, Permissions | `admin.roles` |
| Categories & forums | `admin.forums` |
| Topics, Posts | `admin.content` |
| Themes | `admin.themes` |
| Chat | `admin.chat` |
| System, Logs, Maintenance | `admin.system` |

This is how a "content administrator" who cannot change settings is built: give
the role `admin.access` and `admin.content`, and nothing else.
