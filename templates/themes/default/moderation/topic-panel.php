<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $topic
 * @var App\Policies\TopicPolicy $policy
 */
$slug = (string) $topic['slug'];
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Moderate topic</h1>
        <p class="page-subtitle">
            <a href="<?= $this->e($this->route('topic.show', ['slug' => $slug])) ?>"><?= $this->e((string) $topic['title']) ?></a>
            · <?= $this->number($topic['post_count']) ?> post(s)
            · in <?= $this->e((string) $topic['forum_name']) ?>
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('topic.show', ['slug' => $slug])) ?>">Back to topic</a>
    </div>
</header>

<div class="split-panels split-panels-wide">
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">State</h2></header>
        <div class="panel-inset">
            <dl class="stat-list stat-list-wide">
                <div><dt>Pinned</dt><dd><?= (int) $topic['is_pinned'] === 1 ? 'yes' : 'no' ?></dd></div>
                <div><dt>Locked</dt><dd><?= (int) $topic['is_locked'] === 1 ? 'yes' : 'no' ?></dd></div>
                <div><dt>Hidden</dt><dd><?= (int) $topic['is_hidden'] === 1 ? 'yes' : 'no' ?></dd></div>
                <div><dt>Archived</dt><dd><?= (int) $topic['is_archived'] === 1 ? 'yes' : 'no' ?></dd></div>
                <div><dt>Deleted</dt><dd><?= $topic['deleted_at'] === null ? 'no' : $this->e($this->date((string) $topic['deleted_at'], 'Y-m-d H:i')) ?></dd></div>
            </dl>
        </div>

        <form class="stacked-form panel-form" action="<?= $this->e($this->route('moderation.topic.flag', ['slug' => $slug])) ?>" method="post">
            <?= $this->csrf() ?>
            <div class="field">
                <label class="field-label" for="reason">Reason <span class="muted">(recorded in the log)</span></label>
                <input class="field-input" type="text" id="reason" name="reason" maxlength="255">
            </div>
            <div class="button-grid">
<?php if ($policy->pin($topic)): ?>
                <button class="btn btn-small" type="submit" name="action" value="<?= (int) $topic['is_pinned'] === 1 ? 'unpin' : 'pin' ?>">
                    <?= (int) $topic['is_pinned'] === 1 ? 'Unpin' : 'Pin' ?>
                </button>
<?php endif; ?>
<?php if ($policy->lock($topic)): ?>
                <button class="btn btn-small" type="submit" name="action" value="<?= (int) $topic['is_locked'] === 1 ? 'unlock' : 'lock' ?>">
                    <?= (int) $topic['is_locked'] === 1 ? 'Unlock' : 'Lock' ?>
                </button>
                <button class="btn btn-small" type="submit" name="action" value="<?= (int) $topic['is_archived'] === 1 ? 'unarchive' : 'archive' ?>">
                    <?= (int) $topic['is_archived'] === 1 ? 'Unarchive' : 'Archive' ?>
                </button>
<?php endif; ?>
<?php if ($policy->hide($topic)): ?>
                <button class="btn btn-small" type="submit" name="action" value="<?= (int) $topic['is_hidden'] === 1 ? 'unhide' : 'hide' ?>">
                    <?= (int) $topic['is_hidden'] === 1 ? 'Unhide' : 'Hide' ?>
                </button>
<?php endif; ?>
            </div>
        </form>

        <div class="panel-form">
            <h3 class="form-heading">Removal</h3>
<?php if ($topic['deleted_at'] === null): ?>
            <p><a class="btn btn-danger-quiet" href="<?= $this->e($this->route('topic.delete', ['slug' => $slug])) ?>">Delete this topic</a></p>
<?php else: ?>
            <form action="<?= $this->e($this->route('moderation.topic.restore', ['slug' => $slug])) ?>" method="post">
                <?= $this->csrf() ?>
                <button type="submit" class="btn btn-accent">Restore this topic</button>
            </form>
<?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Move</h2></header>
<?php if ($policy->move($topic)): ?>
        <form class="stacked-form panel-form" action="<?= $this->e($this->route('moderation.topic.move', ['slug' => $slug])) ?>" method="post">
            <?= $this->csrf() ?>
            <div class="field">
                <label class="field-label" for="forum_id">Destination forum</label>
                <select class="field-input" id="forum_id" name="forum_id" required>
<?php foreach ($move_targets as $target): ?>
                    <option value="<?= (int) $target['id'] ?>" <?= (int) $target['id'] === (int) $topic['forum_id'] ? 'selected' : '' ?>>
                        <?= $this->e((string) $target['category_name']) ?> / <?= $this->e(($target['parent_name'] !== null ? '— ' : '') . (string) $target['name']) ?>
                    </option>
<?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="move-reason">Reason</label>
                <input class="field-input" type="text" id="move-reason" name="reason" maxlength="255">
            </div>
            <div class="form-buttons"><button type="submit" class="btn btn-accent">Move topic</button></div>
        </form>
<?php else: ?>
        <div class="panel-inset muted">You do not have the permission to move topics.</div>
<?php endif; ?>

        <header class="panel-head"><h2 class="panel-title">Merge and split</h2></header>
        <div class="panel-inset">
            <ul class="link-list">
<?php if ($policy->merge($topic)): ?>
                <li><a href="<?= $this->e($this->route('moderation.topic.merge', ['slug' => $slug])) ?>">Merge this topic into another &rarr;</a></li>
<?php endif; ?>
<?php if ($policy->split($topic)): ?>
                <li><a href="<?= $this->e($this->route('moderation.topic.split', ['slug' => $slug])) ?>">Split posts into a new topic &rarr;</a></li>
<?php endif; ?>
            </ul>
            <p class="muted">Both operations are recorded in the moderation log with the posts affected.</p>
        </div>
    </section>
</div>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Posts in this topic</h2></header>
    <table class="board-table data-table">
        <caption class="visually-hidden">Posts</caption>
        <thead><tr><th scope="col">#</th><th scope="col">Author</th><th scope="col">When</th><th scope="col">Excerpt</th><th scope="col" class="col-actions">Actions</th></tr></thead>
        <tbody>
<?php foreach ($posts as $post): ?>
            <tr>
                <td class="mono"><?= (int) $post['id'] ?><?= (int) $post['is_first_post'] === 1 ? ' <span class="tag tag-muted">first</span>' : '' ?></td>
                <td><?= $this->e((string) ($post['author_username'] ?? 'removed member')) ?></td>
                <td class="mono"><?= $this->e($this->date((string) $post['created_at'], 'Y-m-d H:i')) ?></td>
                <td><?= $this->e($this->excerpt((string) $post['content'], 90)) ?></td>
                <td class="col-actions">
                    <a class="linklike" href="<?= $this->e($this->route('post.permalink', ['id' => (int) $post['id']])) ?>">open</a>
                    <a class="linklike" href="<?= $this->e($this->route('post.edit', ['id' => (int) $post['id']])) ?>">edit</a>
                    <form class="inline-form" action="<?= $this->e($this->route('post.visibility', ['id' => (int) $post['id']])) ?>" method="post">
                        <?= $this->csrf() ?>
                        <button type="submit" class="linklike">hide</button>
                    </form>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</section>
