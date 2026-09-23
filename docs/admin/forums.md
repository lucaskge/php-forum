# Categories and forums

*Admin → Categories & forums*.

## The structure

```
Category
└── Forum
    └── Subforum
        └── Topic
            └── Post
```

A category is a heading on the index; it holds forums but no topics. A forum
holds topics, and optionally subforums. Nesting deeper than one level works but
gets hard to read.

## Categories

Name, identifier, description, position and visibility. Deleting a category
**deletes every forum, topic and post inside it** — the one genuinely
destructive operation in the admin panel.

## Forums

| Field | |
|---|---|
| Name | |
| Identifier | The URL segment: `/forum/<identifier>` |
| Description | Shown under the name on the index |
| Category | Required |
| Parent forum | Set it to make this a subforum |
| Icon glyph | A short text marker: `#`, `>_`, `::` |
| Position | Lower sorts first |
| Visible | Off hides it from everyone but moderators |
| Locked | No new topics or replies. Moderators excepted |

A new forum starts with a sensible permission matrix so it is never silently
invisible, and takes you straight to its permissions screen.

## Per-forum permissions

*(forum) → permissions*. Every role down the side, five flags across:

| Flag | |
|---|---|
| **See the forum** | It appears in the index |
| **Read topics** | Its contents can be opened |
| **Start topics** | |
| **Reply** | |
| **Moderate** | The moderation tools for this forum |

A member gets a flag when **any** of their roles grants it.

### The combinations worth knowing

| See | Read | Result |
|---|---|---|
| ✓ | ✓ | Normal |
| ✓ | ✗ | Listed, contents closed. How a members-only area is advertised |
| ✗ | — | Absent. The index never mentions it |

A subforum is only reachable when its parent is visible too, so closing a
parent closes everything under it.

## Ordering

Positions are edited inline on the forum list — one number per row, one save
for the lot.

## Recount

Recalculates every topic count, post count and last-post pointer from the
underlying rows. Safe at any time. Useful after importing data or if a counter
ever looks wrong.
