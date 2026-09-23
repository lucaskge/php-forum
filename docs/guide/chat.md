# Chat

`/chat` is a public room rendered entirely by the server.

## How it behaves

Sending a message is a normal form POST followed by a redirect. The transcript
is whatever the server rendered on the last request, so **new messages appear
when you reload the page**. The page says so rather than pretending otherwise.

This is a deliberate design, not a limitation waiting to be fixed: the board
ships no JavaScript, and polling with a meta refresh would be worse than an
honest reload. The architecture is ready for real-time delivery to be added —
see [Content and transports](../architecture/content.md#chat-transports) — and
that would not change a single template.

## The room

| | |
|---|---|
| Transcript | The most recent messages, oldest first. 60 by default |
| Composer | Plain text, 500 characters by default |
| In the room | Who has loaded it in the last five minutes |
| Room rules | Set by an administrator |

Several rooms can exist; they appear as tabs. A room can be **read-only**, or
have **slow mode**, which is a minimum delay between two messages from the same
person.

Chat is plain text. Board formatting is not applied, which keeps a fast-moving
transcript readable.

## Who can post

Signed in, with the `chat.post` permission, not muted or banned from chat, and
the room not read-only. If you cannot post, the page says which of those it is.

## If chat is switched off

An administrator can disable chat entirely. When they do it is off for
**everybody** — members, moderators and administrators alike. A feature that
stays open for the person who turned it off is a feature whose switch appears
not to work.

Configuring it remains reachable at *Admin → Chat*, which is guarded by
permission rather than by the feature's own setting.

## Moderation

With `chat.moderate`:

| Action | Effect |
|---|---|
| **del** beside a message | Removes it. Staff still see it, with who removed it |
| **Mute** | Cannot post in this room for a set number of minutes |
| **Ban from chat** | Cannot post in any room, no expiry |
| **Purge** | Removes every message that member has in the room |

All four are recorded in the [moderation log](../moderation/index.md). Active
restrictions are listed at *Admin → Chat → Restrictions*, where they can be
lifted.
