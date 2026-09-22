<?php /** @var App\Support\View $this */ ?>
<section class="panel panel-danger">
    <header class="panel-head"><h1 class="panel-title">Delete account</h1></header>
    <div class="panel-inset">
        <p>You are about to permanently delete <strong><?= $this->e((string) $profile['username']) ?></strong> (#<?= (int) $profile['id'] ?>).</p>
        <ul class="plain-list">
            <li>Their posts and topics stay on the board, attributed to “a removed member”.</li>
            <li>Their private messages, notifications, bookmarks and subscriptions are removed.</li>
            <li>This cannot be undone.</li>
        </ul>
        <form class="stacked-form" action="<?= $this->e($this->route('admin.user.destroy', ['id' => (int) $profile['id']])) ?>" method="post">
            <?= $this->csrf() ?>
            <div class="field">
                <label class="field-label" for="reason">Reason <span class="muted">(recorded in the log)</span></label>
                <input class="field-input" type="text" id="reason" name="reason" maxlength="255">
            </div>
            <div class="form-buttons">
                <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.user.edit', ['id' => (int) $profile['id']])) ?>">Cancel</a>
                <button type="submit" class="btn btn-danger">Delete permanently</button>
            </div>
        </form>
    </div>
</section>
