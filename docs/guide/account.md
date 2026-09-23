# Your account

`/settings`, with a tab per screen.

## Profile

Your **about** text, **signature**, location and website. Both the about text
and the signature use board [formatting](formatting.md), and both appear on
your profile; the signature also appears under each of your posts.

## Avatar

Upload an image, or keep the generated monogram — a coloured square derived
from your name, produced by the board, with no external service involved.

Uploads are checked by their contents, never by their name, and then re-encoded
from the decoded pixels. That strips metadata, so an avatar cannot leak your
camera model or GPS coordinates, and nothing hidden inside the original file
survives. Large photographs are accepted and scaled down for you.

The screen states the real limits, including PHP's own upload ceilings when
those are lower than the board's.

## Account

Your username, join date, last sign-in, and account status. The e-mail address
can be changed here, confirmed with your password. Usernames are permanent —
ask an administrator.

## Password

Current password, then the new one twice. Changing it invalidates any
outstanding reset links.

## Preferences

| | |
|---|---|
| Timezone | Every timestamp on the board follows it |
| Posts per page | Your own, overriding the board default |
| Alerts | Replies in topics you follow, mentions, quotes, and whether private messages should also appear in alerts (off by default) |
| Privacy | Whether you are listed as online. Staff can always see |

## Subscriptions and bookmarks

Two lists: topics you follow, and topics you saved.

## Your record

`/settings/record` — what the moderators have on file about you:

- any active restriction, with the reason and when it lifts;
- every warning, with its reason, details, points and expiry;
- past restrictions.

Only you and the staff can see it. Warning alerts lead here — a notice with
nothing to open is not a notice.

## If your account is restricted

A **suspension** lets you sign in and read, but not post, reply, send messages
or use chat. You are told plainly: signing in lands on your record, and a
banner naming the reason and the end date sits on every page. It lifts by
itself on its date; nothing is required of you.

A **ban** refuses the sign-in, with the reason shown, and does not expire.

## Forgotten password

`/forgot-password`. A single-use link valid for one hour. Whether or not the
address is registered, the answer is the same — the board does not confirm who
has an account.

In development, with `MAIL_TRANSPORT=log`, the link is written to
`storage/logs/mail.log`.
