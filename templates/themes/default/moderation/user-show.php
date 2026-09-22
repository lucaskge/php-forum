<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $profile
 */
$username = (string) $profile['username'];
?>
<header class="page-head">
    <div>
        <h1 class="page-title">
            <?= $this->e($username) ?>
            <span class="status-badge status-<?= $this->e((string) $profile['status']) ?>"><?= $this->e((string) $profile['status']) ?></span>
        </h1>
        <p class="page-subtitle mono">
            joined <?= $this->e($this->date((string) $profile['created_at'], 'Y-m-d')) ?> ·
            <?= $this->number($profile['post_count']) ?> posts ·
            <?= $this->number($warning_points) ?> warning point(s) ·
            <?= $is_online ? 'online now' : 'last seen ' . $this->relative((string) $profile['last_active_at']) ?>
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('user.profile', ['username' => $username])) ?>">Public profile</a>
<?php if ($this->can('admin.users')): ?>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.user.edit', ['id' => (int) $profile['id']])) ?>">Edit account</a>
<?php endif; ?>
    </div>
</header>

<?php if ($active_ban !== null): ?>
<div class="alert alert-warning">
    <span class="alert-tag">warn</span>
    <span class="alert-body">
        Active <?= $this->e((string) $active_ban['type']) ?>: <?= $this->e((string) $active_ban['reason']) ?>
<?php if ($active_ban['expires_at'] !== null): ?>
        — expires <?= $this->e($this->date((string) $active_ban['expires_at'], 'Y-m-d H:i')) ?>
<?php else: ?>
        — permanent
<?php endif; ?>
        (by <?= $this->e((string) ($active_ban['created_by_username'] ?? 'unknown')) ?>)
    </span>
</div>
<?php endif; ?>

<div class="split-panels split-panels-wide">
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Actions</h2></header>

<?php if ($can_warn): ?>
        <form class="stacked-form panel-form" action="<?= $this->e($this->route('moderation.user.warn', ['username' => $username])) ?>" method="post">
            <?= $this->csrf() ?>
            <h3 class="form-heading">Warn</h3>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="warn-reason">Reason</label>
                    <input class="field-input" type="text" id="warn-reason" name="reason" maxlength="255" required>
                </div>
                <div class="field">
                    <label class="field-label" for="warn-points">Points</label>
                    <input class="field-input" type="number" id="warn-points" name="points" min="0" max="20" value="1">
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="warn-expires">Expires in (days, 0 = never)</label>
                    <input class="field-input" type="number" id="warn-expires" name="expires_days" min="0" max="3650" value="90">
                </div>
                <div class="field">
                    <label class="field-label" for="warn-details">Details</label>
                    <input class="field-input" type="text" id="warn-details" name="details" maxlength="255">
                </div>
            </div>
            <div class="form-buttons"><button type="submit" class="btn btn-accent">Issue warning</button></div>
        </form>
<?php endif; ?>

<?php if ($can_suspend): ?>
        <form class="stacked-form panel-form" action="<?= $this->e($this->route('moderation.user.suspend', ['username' => $username])) ?>" method="post">
            <?= $this->csrf() ?>
            <h3 class="form-heading">Suspend</h3>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="suspend-reason">Reason</label>
                    <input class="field-input" type="text" id="suspend-reason" name="reason" maxlength="255" required>
                </div>
                <div class="field">
                    <label class="field-label" for="suspend-days">Days</label>
                    <input class="field-input" type="number" id="suspend-days" name="days" min="1" max="3650" value="7" required>
                </div>
            </div>
            <div class="field">
                <label class="field-label" for="suspend-note">Internal note <span class="muted">(staff only)</span></label>
                <input class="field-input" type="text" id="suspend-note" name="note" maxlength="255">
            </div>
            <div class="form-buttons"><button type="submit" class="btn btn-danger">Suspend account</button></div>
        </form>
<?php endif; ?>

<?php if ($can_ban): ?>
        <form class="stacked-form panel-form" action="<?= $this->e($this->route('moderation.user.ban', ['username' => $username])) ?>" method="post">
            <?= $this->csrf() ?>
            <h3 class="form-heading">Ban permanently</h3>
            <div class="field">
                <label class="field-label" for="ban-reason">Reason</label>
                <input class="field-input" type="text" id="ban-reason" name="reason" maxlength="255" required>
            </div>
            <div class="field">
                <label class="field-label" for="ban-note">Internal note</label>
                <input class="field-input" type="text" id="ban-note" name="note" maxlength="255">
            </div>
            <div class="form-buttons"><button type="submit" class="btn btn-danger">Ban account</button></div>
        </form>
<?php endif; ?>

<?php if ($active_ban !== null && ($can_ban || $can_suspend)): ?>
        <form class="panel-form" action="<?= $this->e($this->route('moderation.user.lift', ['username' => $username])) ?>" method="post">
            <?= $this->csrf() ?>
            <h3 class="form-heading">Lift restrictions</h3>
            <p class="muted">Clears every active ban or suspension and restores the account.</p>
            <div class="form-buttons"><button type="submit" class="btn btn-accent">Lift restrictions</button></div>
        </form>
<?php endif; ?>

<?php if (!$can_warn && !$can_suspend && !$can_ban): ?>
        <div class="panel-inset muted">
            <p>You cannot apply moderation actions to this account. Staff accounts can only be actioned by an administrator.</p>
        </div>
<?php endif; ?>
    </section>

    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Internal notes</h2></header>
        <form class="stacked-form panel-form" action="<?= $this->e($this->route('moderation.user.note', ['username' => $username])) ?>" method="post">
            <?= $this->csrf() ?>
            <div class="field">
                <label class="field-label" for="note">Add a note</label>
                <textarea class="field-input" id="note" name="note" rows="3" maxlength="5000"></textarea>
                <p class="field-hint">Visible to staff only. Never shown to the member.</p>
            </div>
            <div class="form-buttons"><button type="submit" class="btn btn-accent">Save note</button></div>
        </form>

        <ul class="note-list">
<?php foreach ($notes as $note): ?>
            <li class="note">
                <p class="note-body"><?= nl2br($this->e((string) $note['note']), false) ?></p>
                <p class="note-meta mono">
                    <?= $this->e((string) ($note['author_username'] ?? 'system')) ?> · <?= $this->e($this->date((string) $note['created_at'], 'Y-m-d H:i')) ?>
                </p>
                <form class="inline-form" action="<?= $this->e($this->route('moderation.user.note.delete', ['username' => $username, 'note' => (int) $note['id']])) ?>" method="post">
                    <?= $this->csrf() ?>
                    <button type="submit" class="linklike linklike-danger">remove</button>
                </form>
            </li>
<?php endforeach; ?>
<?php if ($notes === []): ?>
            <li class="muted">No notes recorded.</li>
<?php endif; ?>
        </ul>
    </section>
</div>

<div class="split-panels split-panels-wide">
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Warnings</h2></header>
<?php if ($warnings === []): ?>
        <div class="panel-inset muted">No warnings issued.</div>
<?php else: ?>
        <table class="board-table data-table">
            <caption class="visually-hidden">Warnings</caption>
            <thead><tr><th scope="col">Reason</th><th scope="col">Points</th><th scope="col">By</th><th scope="col">When</th><th scope="col">Expires</th></tr></thead>
            <tbody>
<?php foreach ($warnings as $warning): ?>
                <tr>
                    <td><?= $this->e((string) $warning['reason']) ?></td>
                    <td class="col-num"><?= (int) $warning['points'] ?></td>
                    <td><?= $this->e((string) ($warning['moderator_username'] ?? 'system')) ?></td>
                    <td class="mono"><?= $this->e($this->date((string) $warning['created_at'], 'Y-m-d')) ?></td>
                    <td class="mono"><?= $warning['expires_at'] === null ? 'never' : $this->e($this->date((string) $warning['expires_at'], 'Y-m-d')) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
<?php endif; ?>
    </section>

    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Restriction history</h2></header>
<?php if ($bans === []): ?>
        <div class="panel-inset muted">Never restricted.</div>
<?php else: ?>
        <table class="board-table data-table">
            <caption class="visually-hidden">Bans and suspensions</caption>
            <thead><tr><th scope="col">Type</th><th scope="col">Reason</th><th scope="col">By</th><th scope="col">From</th><th scope="col">Until</th><th scope="col">State</th></tr></thead>
            <tbody>
<?php foreach ($bans as $ban): ?>
                <tr>
                    <td class="mono"><?= $this->e((string) $ban['type']) ?></td>
                    <td><?= $this->e((string) $ban['reason']) ?></td>
                    <td><?= $this->e((string) ($ban['created_by_username'] ?? 'system')) ?></td>
                    <td class="mono"><?= $this->e($this->date((string) $ban['created_at'], 'Y-m-d')) ?></td>
                    <td class="mono"><?= $ban['expires_at'] === null ? 'permanent' : $this->e($this->date((string) $ban['expires_at'], 'Y-m-d')) ?></td>
                    <td><?= (int) $ban['is_active'] === 1 ? '<span class="status-badge status-pending">active</span>' : '<span class="status-badge status-resolved">lifted</span>' ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
<?php endif; ?>
    </section>
</div>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Moderation history</h2></header>
    <ul class="log-list">
<?php foreach ($log as $entry): ?>
        <li class="log-item">
            <span class="log-action mono"><?= $this->e((string) $entry['action']) ?></span>
            <span class="log-summary"><?= $this->e((string) $entry['summary']) ?></span>
            <span class="log-meta mono"><?= $this->username($entry, ['fallback' => 'system']) ?> · <?= $this->e($this->date((string) $entry['created_at'], 'Y-m-d H:i')) ?></span>
        </li>
<?php endforeach; ?>
<?php if ($log === []): ?>
        <li class="muted">Nothing recorded against this account.</li>
<?php endif; ?>
    </ul>
</section>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Recent posts</h2></header>
    <ul class="feed-list">
<?php foreach ($recent_posts as $post): ?>
        <li class="feed-item">
            <a class="feed-title" href="<?= $this->e($this->route('post.permalink', ['id' => (int) $post['id']])) ?>"><?= $this->e((string) $post['topic_title']) ?></a>
            <span class="feed-meta"><?= $this->e((string) $post['forum_name']) ?> · <?= $this->e($this->relative((string) $post['created_at'])) ?></span>
            <p class="feed-excerpt"><?= $this->e($this->excerpt((string) $post['content'], 160)) ?></p>
        </li>
<?php endforeach; ?>
<?php if ($recent_posts === []): ?>
        <li class="muted">No posts.</li>
<?php endif; ?>
    </ul>
</section>
