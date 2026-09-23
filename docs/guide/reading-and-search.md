# Reading and searching

## The board index

Categories, each holding forums, each showing its topic count, post count and
last post. Subforums are listed under their parent.

Beside it: board statistics, who is online, the staff list, newest members, and
the latest topics and posts. A forum you cannot see is simply absent — the
index never advertises what it will not let you open.

## Forums

A forum lists its topics, newest activity first, with pinned topics above the
rest. Its subforums' topics are listed alongside its own, as traditional boards
do.

| Marker | Meaning |
|---|---|
| `PIN` | Pinned — stays at the top |
| `LCK` | Locked — no new replies |
| `hidden` | Visible to moderators only |
| `deleted` | Removed, recoverable, moderators only |
| `archived` | Closed to replies, kept for reference |

A forum can be **visible but not readable**: it appears in the index and
explains that its contents are restricted. That is how a members-only area is
advertised rather than hidden.

## Topics

Posts are paginated — 15 per page by default, and you can set your own under
[preferences](account.md). A long topic is never loaded in one go.

Each post shows its author, their rank and statistics, the time, the content,
and its number in the topic. The permalink under a post links to it directly,
and resolves to whichever page it is on even if that changes later.

Under a post you may see: **permalink**, **quote**, **edit**, **delete**,
**report**, **view history**. Which appear depends on who you are and on the
board's settings — nothing is shown that will not work when clicked.

## Recent

`/recent` lists everything posted across the forums you can read, newest first.
This is the "what happened while I was away" view.

## Search

`/search` searches the forums you can read, and only those.

| Field | Notes |
|---|---|
| Keywords | Words are matched independently; all must appear |
| Author | Exact username |
| Forum | One forum, or all of them |
| Search in | Post contents, or topic subjects |
| Posted after / before | Date range |

Terms must be at least three characters by default
(`search_min_length`). Results show the topic, forum, author, date and an
excerpt, and link straight to the matching post.

Search runs as a `LIKE` scan. That is honest at board scale and keeps the
install dependency-free; if you outgrow it,
`app/Repositories/SearchRepository.php` is the single class to replace.

## Members

`/members` lists registered accounts with their rank, post count and join date,
searchable and sortable. `/online` shows who has been active recently — members
who asked not to be listed are hidden from everyone but staff.
