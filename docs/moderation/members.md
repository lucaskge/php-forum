# Moderating members

`/moderation/users`, or the **Staff view** button on any profile.

## The member record

Everything about one account on one screen: status, join date, post count,
warning points, whether they are online, active restriction, warning history,
restriction history, internal notes, the moderation log entries against them,
reports about them, and their recent posts.

## Actions

### Warn

A reason, optional details, **points** (0–20) and an expiry in days. The member
is alerted and can read the warning on [their record](../guide/account.md).
Points from an expired warning stop counting; the entry stays.

### Suspend

A reason, a length in days, and an optional internal note.

A suspended member **can still sign in and read** — deliberately, so they can
see why and until when. They cannot post, reply, send messages or use chat, and
they are told: signing in lands on their record, and a banner naming the reason
and the end date sits on every page.

It lifts by itself on its date. Nothing has to be done.

### Ban

A reason and an optional internal note. A ban **refuses the sign-in itself**,
with the reason shown, and does not expire.

!!! note "Choosing between them"
    A suspension is a timed loss of privileges with the reason visible to the
    person serving it. A ban is the door closed. If you want somebody out
    rather than quiet, ban.

### Lift restrictions

Clears every active ban and suspension and restores the account. The member is
alerted.

### Internal notes

Free text, visible to staff only, never shown to the member. This is where the
context behind a decision lives — the thing the next moderator needs when the
same name comes up again.

## Who can be actioned

- Never yourself.
- A staff member only by an administrator.
- The board refuses the last administrator losing their own administrator role,
  so nobody can lock everyone out.

## Administrative operations

*Admin → Users* additionally offers: editing any profile field, assigning
roles, resetting a password (shown once, not stored in clear), removing an
avatar, and deleting an account.

Deleting an account keeps its posts and topics, attributed to "a removed
member", and removes its messages, alerts, bookmarks and subscriptions.

## Bans list

*Admin → Bans* lists every restriction ever issued, filtered by active or
expired, with the reason, who issued it, when it ends, and a lift button.
