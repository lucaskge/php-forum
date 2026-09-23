# Moderation

`/moderation`, for anyone holding `moderation.access`. The sidebar has
Overview, Reports, Content queue, Members and the Log.

## Overview

Counts of pending, resolved and dismissed reports; active restrictions; the
most recent reports and the most recent actions; hidden posts and deleted
topics waiting for a decision; and how many forums you moderate.

## What a moderator can do

Permissions are granular, so a moderator role can hold any subset of these. See
[Permissions reference](../reference/permissions.md).

| Area | |
|---|---|
| Reports | View, resolve, dismiss |
| Topics | Pin, lock, archive, hide, move, merge, split, delete, restore |
| Posts | Edit, hide, delete, restore, read edit history |
| Members | Warn, suspend, ban, write internal notes |
| Chat | Delete messages, mute, ban, purge |
| Log | Read it |

Two things shape what you see:

- **Which forums you moderate.** Per-forum in the permission matrix, or every
  readable forum if your role has `moderation.forums.all`.
- **Who you can act on.** Nobody can action themselves, and only an
  administrator can action another staff member.

## Reports

`/moderation/reports`, filtered by pending, resolved, dismissed or all.

Each row links **straight to the reported item** — the post, the topic, the
member's record, or the chat room. When there is nothing to open (a private
message, or content already gone) the row says why instead of offering a dead
link.

### Two kinds

| | Carries | Review screen offers |
|---|---|---|
| **Post** | The post itself | Hide or delete it as part of resolving |
| **Member** | The reporter's description | A link to the member's record; conduct is acted on there |

A member report requires a description, because without a post to look at that
description is all you have.

### Reviewing one

The detail screen shows the reported content, the reporter, the reported
member, the reason, when it arrived, the reporter's address, and any earlier
reports about the same member.

You then choose:

1. **Resolve** (the report was valid) or **Dismiss** (no action needed).
2. A **content action**: leave it, hide it, or delete it. Only for posts.
3. **Notes**, kept on the report and written to the log.

Both the decision and the content action are recorded as one entry in the
moderation log.

Warning, suspending or banning is done from the member's record, not from here
— an action against a person outlives the report that prompted it.

## Content queue

`/moderation/queue` gathers everything currently hidden or deleted — posts and
topics — with restore in one click. This is where something removed by mistake
is found.

## The log

`/moderation/log` records every moderator action: who, what, against whom, the
reason, the time and the address. Filter by action, by moderator, or by free
text.

The log is not optional bookkeeping — it is how the action is performed. Every
method in `ModerationService` writes its entry in the same transaction as the
change, so there is no path that alters the board without leaving a trace.

Actions recorded include: `report.resolve`, `report.dismiss`, `topic.pin`,
`topic.lock`, `topic.move`, `topic.merge`, `topic.split`, `topic.delete`,
`topic.restore`, `post.edit`, `post.delete`, `post.restore`, `post.hide`,
`user.warn`, `user.suspend`, `user.ban`, `user.unban`, `chat.mute`,
`chat.ban`, `chat.purge`, and the `admin.*` entries for configuration changes.
