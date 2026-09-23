# Messages and alerts

Two separate things, deliberately kept separate.

## Private messages

`/messages`. Inbox, Sent, Compose.

- **Compose** takes an exact username, a subject and a body, and uses the same
  [formatting](formatting.md) as posts.
- **Reply** arrives with the original already quoted.
- Messages form a thread; the conversation is listed when you read one.
- **Delete** removes it from *your* side. The row disappears only when both
  sides have deleted it.
- Only the two parties can open a message. Nobody else — moderators included —
  can read it. A reported message is shown to moderators as a report, not as a
  mailbox they can browse.

The header carries the unread count.

## Alerts

`/notifications`. What happened that involves you:

| Type | Raised when |
|---|---|
| `topic.reply` | Somebody replied in a topic you follow |
| `post.quote` | Somebody quoted your post |
| `post.mention` | Somebody wrote `@yourname` |
| `message.received` | A private message arrived — **off by default** |
| `moderation.action` | A moderator did something that concerns you |
| `moderation.warning` | You were warned |

### One post, one alert

A single post can involve you three ways at once. You get **one** alert, naming
the most specific reason that applies:

```
quoted  >  mentioned  >  follows the topic
```

Specific first because it is more useful: *you were quoted* tells you why to
look; *somebody replied* does not.

### Messages do not raise an alert

Your inbox already has its own unread counter in the header. An alert about the
same message is the same number twice, which is how people learn to ignore
both. If you would rather see everything in one list, turn it on under
[preferences](account.md).

### Reading clears the badge

Opening `/notifications` marks the alerts **on that page** as read — that is
what clears the counter. They stay highlighted for that visit so you can see
what was new. With more than one page, only what you looked at is cleared, so
the counter keeps telling the truth.

Clicking an alert marks it read and takes you to what it is about: the post,
the message, or [your record](account.md) for a warning.

Alerts update when you load a page. Nothing polls in the background, because
nothing can.
