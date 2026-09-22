<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $room
 * @var array<string,mixed>|null $target
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Chat moderation</h1>
        <p class="page-subtitle">Room: <?= $this->e((string) $room['name']) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('chat.room', ['room' => (string) $room['slug']])) ?>">Back to the room</a>
    </div>
</header>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Find a member</h2></header>
    <form class="filter-bar" action="<?= $this->e($this->route('chat.moderate', ['room' => (string) $room['slug']])) ?>" method="get">
        <div class="field field-inline">
            <label class="field-label" for="user">Username</label>
            <input class="field-input" type="text" id="user" name="user" maxlength="32" value="<?= $this->e($username) ?>" required>
        </div>
        <div class="filter-actions"><button type="submit" class="btn btn-accent">Look up</button></div>
    </form>
</section>

<?php if ($username !== '' && $target === null): ?>
<div class="alert alert-error"><span class="alert-tag">err</span><span class="alert-body">No member is registered under that name.</span></div>
<?php endif; ?>

<?php if ($target !== null): ?>
<section class="panel">
    <header class="panel-head">
        <h2 class="panel-title"><?= $this->e((string) $target['username']) ?></h2>
        <a class="panel-action" href="<?= $this->e($this->route('moderation.user', ['username' => (string) $target['username']])) ?>">full record</a>
    </header>

<?php if ($restriction !== null): ?>
    <div class="panel-inset">
        <p><strong>Active restriction:</strong> <?= $this->e((string) $restriction['type']) ?> — <?= $this->e((string) $restriction['reason']) ?></p>
        <p class="muted mono">
<?php if ($restriction['expires_at'] !== null): ?>
            expires <?= $this->e($this->date((string) $restriction['expires_at'], 'Y-m-d H:i')) ?>
<?php else: ?>
            no expiry
<?php endif; ?>
        </p>
        <form class="inline-form" action="<?= $this->e($this->route('chat.moderate.apply', ['room' => (string) $room['slug']])) ?>" method="post">
            <?= $this->csrf() ?>
            <input type="hidden" name="user" value="<?= $this->e((string) $target['username']) ?>">
            <input type="hidden" name="action" value="lift">
            <button type="submit" class="btn btn-small">Lift restriction</button>
        </form>
    </div>
<?php endif; ?>

    <form class="stacked-form" action="<?= $this->e($this->route('chat.moderate.apply', ['room' => (string) $room['slug']])) ?>" method="post">
        <?= $this->csrf() ?>
        <input type="hidden" name="user" value="<?= $this->e((string) $target['username']) ?>">

        <fieldset class="field">
            <legend class="field-label">Action</legend>
            <label class="check"><input type="radio" name="action" value="mute" checked><span>Mute in this room</span></label>
            <label class="check"><input type="radio" name="action" value="ban"><span>Ban from chat entirely</span></label>
            <label class="check"><input type="radio" name="action" value="purge"><span>Purge their messages in this room</span></label>
        </fieldset>

        <div class="form-grid">
            <div class="field">
                <label class="field-label" for="minutes">Mute length (minutes)</label>
                <input class="field-input" type="number" id="minutes" name="minutes" min="1" max="10080" value="15">
            </div>
            <div class="field">
                <label class="field-label" for="reason">Reason</label>
                <input class="field-input" type="text" id="reason" name="reason" maxlength="255" value="Chat rules violation" required>
            </div>
        </div>

        <div class="form-buttons">
            <button type="submit" class="btn btn-danger">Apply</button>
        </div>
    </form>
</section>
<?php endif; ?>
