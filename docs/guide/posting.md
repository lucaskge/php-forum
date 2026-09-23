# Posting

## Starting a topic

From a forum you may post in: **New topic**. A subject (5–190 characters) and a
message. You are subscribed to your own topic by default, so replies reach your
alerts.

The address of a topic comes from its subject and **does not change** if the
subject is later edited — old links keep working.

## Replying

**Reply** opens the full editor with a review of the first posts below it.
**Quick reply** at the foot of a topic is the same thing without leaving the
page.

## Quoting

The **quote** link under a post opens the reply form with that post already
quoted, attributed, and linked back. It is a plain page load — a GET followed
by a POST, no scripting.

Quotes inside the quoted text are dropped, so a long chain does not snowball.

Being quoted raises an alert for the person quoted, unless they turned that off.

## Editing

**edit** under your own post. Whether you can depends on:

- the `post.edit.own` permission;
- the **self-edit window** (`edit_window_minutes`, 0 = no limit);
- whether the topic is locked.

Every edit is recorded: the text before, the text after, who made it, when, and
an optional reason. **view history** shows the revisions to the author,
moderators, and anyone with `post.history.view`.

A moderator editing somebody else's post is recorded in the
[moderation log](../moderation/index.md) as well as the post's history.

## Deleting

You can delete your own post while the topic is open and the edit window has
not passed — except the **opening post**, because removing that means removing
the topic. Deleting your own topic is allowed only while nobody has replied.

Deletes are recoverable: the row stays and moderators can restore it.

## Subscribing and bookmarking

| | |
|---|---|
| **Subscribe** | Alerts you when somebody replies |
| **Bookmark** | Saves the topic for later, silently |

Both lists live under [your account](account.md).

## Reporting

**report** under a post opens a form: a reason and optional details. Reports go
to the queue every moderator sees.

You can also report a **member** rather than a post, from the Report button on
their profile — for conduct that is not in one post. A description is required
there: without a post to look at, your description is all the moderator has.

Neither can be used on yourself, and reporting the same thing twice is absorbed
rather than piling up.

## Locked topics

A locked topic refuses replies. Moderators can still post — somebody has to be
able to leave the note explaining why it was closed — and when they do, the page
says so plainly: the reply box is labelled **over the lock**, so a normal reply
box never appears on a topic that is closed.

## Limits

| | Default | Setting |
|---|---|---|
| Minimum post length | 5 characters | `min_post_length` |
| Self-edit window | unlimited | `edit_window_minutes` |
| Posts per rate-limit window | 25 per 10 minutes | `config/security.php` |
| Signature length | 400 characters | `signature_max_length` |
