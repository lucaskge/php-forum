# Users, roles and permissions

## The model

Two layers, and they work together.

### 1. Global permissions

Granular slugs granted to roles: `topic.create`, `post.delete.any`,
`report.handle`, `admin.settings`, and 42 others. A role holds any subset.

`*` is the administrator wildcard. It satisfies every check, and only the
administrator role holds it.

### 2. Per-forum access

A role × forum matrix with five flags: *see the forum*, *read topics*, *start
topics*, *reply*, *moderate*. The same role can behave completely differently
on two boards.

### How they combine

A member holds **several roles at once**, and their effective permissions are
the **union**. A permission is granted if any of their roles grants it; forum
access likewise.

Nothing in the application checks a role by name. Every decision goes through
`AccessControl`, which is what makes the system granular rather than three
hardcoded tiers.

## Default roles

| Role | Holds | Notes |
|---|---|---|
| **Guest** | Read and search | Applies to everyone not signed in |
| **Member** | Post, reply, message, chat, report, edit own | Given to new registrations |
| **Trusted member** | Member plus `post.history.view` | An example of stacking one grant on top |
| **Moderator** | Content and member tools | Staff |
| **Administrator** | `*` | Staff |

Guest, Member, Moderator and Administrator are **system roles**: they cannot be
deleted, because the board relies on them.

## User list

*Admin → Users*. Search by name or e-mail, filter by role and status, sort.
Each row opens the account.

### Editing an account

Username, e-mail, status, custom rank title, reputation, timezone, location,
website, about text and signature.

**Roles** are a separate permission (`user.role.manage`) from editing a
profile. Tick the roles; the **displayed rank** decides which one's name and
colour appear beside the member, and must be one of the assigned roles.

The board will not let you remove your own administrator role, and will not let
the last role holding `*` lose it.

### Operations

| | |
|---|---|
| **Reset password** | Generates one and shows it **once**. It is not stored in clear |
| **Remove avatar** | Back to the generated monogram |
| **Delete account** | Posts stay, attributed to "a removed member"; messages, alerts, bookmarks and subscriptions go |

## Roles

*Admin → Roles*. Each role has a name, an identifier, a **colour**, a priority
(higher is listed first), a description and a staff flag.

### Colour

Set with a native colour picker, previewed beside it in the role's own chip.
The colour follows the member's name **everywhere** it appears: posts, topic
lists, last-post columns, the index, search results, chat, messages,
moderation tables and the admin panels.

It is delivered as a generated stylesheet (`/board-styles.css`) rather than an
inline style, because the board's Content-Security-Policy refuses inline
styles. Change a colour and the file changes with it.

### The staff flag

`is_staff` belongs to the **role**, not the member. Anyone holding a role
marked staff is listed in the staff panel on the index and carries a *staff*
marker under their name on every post. System roles show the flag but lock it.

### Permissions

The role's edit screen lists all 46 permissions, grouped, each with its slug
and what it does. Tick and save.

*Admin → Permissions* shows the whole matrix — every permission against every
role — as a read-only overview.

### Building a role

A "content administrator" who cannot change settings:

1. New role, priority 60, staff.
2. Grant `admin.access`, `admin.content`, `moderation.access`,
   `report.view`, `report.handle`, plus the topic and post tools.
3. Leave `admin.settings`, `admin.roles`, `admin.system` off.

They now reach `/admin`, see only Content and Reports in the sidebar, and are
refused everything else — by the routes themselves, not by hidden links.

## Bans

*Admin → Bans* lists every restriction issued, filtered by active or expired,
with reason, issuer, expiry and a lift button. Issuing one is done from the
[member's record](../moderation/members.md).
