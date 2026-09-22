<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $post
 * @var array<string,string> $reasons
 */
$old = $this->shared('old_input', []);
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Report post #<?= (int) $post['id'] ?></h1>
        <p class="page-subtitle">Reports go to the moderation queue. Abuse of the report system is itself a rules violation.</p>
    </div>
</header>

<section class="panel">
    <div class="panel-inset">
        <p class="muted mono">Reported content by <?= $this->e((string) ($post['author_username'] ?? 'removed member')) ?>:</p>
        <div class="quoted-preview"><?= $this->content((string) $post['content']) ?></div>
    </div>

    <form class="stacked-form" action="<?= $this->e($this->route('post.report.store', ['id' => (int) $post['id']])) ?>" method="post">
        <?= $this->csrf() ?>
        <fieldset class="field">
            <legend class="field-label">Reason</legend>
<?php foreach ($reasons as $value => $label): ?>
            <label class="check">
                <input type="radio" name="reason" value="<?= $this->e($value) ?>"
                    <?= ($old['reason'] ?? '') === $value ? 'checked' : '' ?> required>
                <span><?= $this->e($label) ?></span>
            </label>
<?php endforeach; ?>
            <?= $this->partial('partials/field-error', ['field' => 'reason']) ?>
        </fieldset>

        <div class="field">
            <label class="field-label" for="details">Details <span class="muted">(optional but helpful)</span></label>
            <textarea class="field-input" id="details" name="details" rows="5" maxlength="2000"><?= $this->e((string) ($old['details'] ?? '')) ?></textarea>
        </div>

        <div class="form-buttons">
            <a class="btn btn-quiet" href="<?= $this->e($this->route('post.permalink', ['id' => (int) $post['id']])) ?>">Cancel</a>
            <button type="submit" class="btn btn-accent">Submit report</button>
        </div>
    </form>
</section>
