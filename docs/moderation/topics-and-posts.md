# Moderating topics and posts

## The topic panel

**Moderate** at the top of any topic you moderate, or
`/moderation/topic/{slug}`. It shows the topic's current state and every action
in one place.

### State

| Flag | Effect |
|---|---|
| **Pinned** | Stays at the top of its forum |
| **Locked** | No new replies. Moderators can still post, and the page says so |
| **Archived** | Like locked, for topics kept as reference |
| **Hidden** | Invisible to members; moderators still see it, marked |
| **Deleted** | Removed from the board, kept in the database, restorable |

Each toggle takes an optional reason, which goes to the log.

Hidden and deleted are different on purpose: **hidden** keeps the topic in
place for staff to discuss, **deleted** takes it off the board. Neither
destroys anything.

### Move

Pick any forum you moderate. The topic and all its posts move, and both forums'
counters are recalculated.

### Merge

Search for a destination topic, pick it, and every post from this one moves
across. The opening post becomes an ordinary reply and the now-empty source is
removed. The log records how many posts moved and where.

### Split

Select posts and give the new topic a subject and a forum. The first selected
post becomes its opening post. At least one post must stay behind — the board
refuses to empty a topic this way.

## Posts

Under any post you moderate:

| Action | |
|---|---|
| **edit** | Recorded in the post's history *and* in the moderation log, with your reason |
| **hide** | Out of sight of members, still there for staff |
| **delete** | Off the board, restorable |
| **restore** | Brings back a deleted post |
| **view history** | Every revision, before and after |

Moderators also see the recorded IP address beside a post.

## Restoring

Anything hidden or deleted is listed in the [content queue](index.md#content-queue)
with a restore button. Counters are recalculated on restore, so nothing is left
inconsistent.

## Permanent deletion

Only administrators, from *Admin → Topics* or *Admin → Posts*, with **purge**.
That is the only path that removes a row for good; everything a moderator does
is reversible.
