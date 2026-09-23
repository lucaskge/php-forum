<?php
/**
 * Reporting a member rather than a single post.
 *
 * @var App\Support\View $this
 * @var array<string,mixed> $profile
 * @var array<string,string> $reasons
 */
$old = $this->shared('old_input', []);
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Report <?= $this->e((string) $profile['username']) ?></h1>
        <p class="page-subtitle">
            For conduct that is not contained in one post — a pattern across several, a name, an
            avatar, or private messages. To report a single post, use the “report” link under it.
        </p>
    </div>
</header>

<section class="panel">
    <div class="panel-inset">
        <p class="muted">
            Reports go to the queue every moderator sees, with your name attached. Abuse of the
            report system is itself a rules violation.
        </p>
    </div>

    <form class="stacked-form" action="<?= $this->e($this->route('user.report', ['username' => (string) $profile['username']])) ?>" method="post">
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
            <label class="field-label" for="details">What happened</label>
            <textarea class="field-input" id="details" name="details" rows="6" maxlength="2000" required><?= $this->e((string) ($old['details'] ?? '')) ?></textarea>
            <p class="field-hint">
                Required here, unlike a post report: without the post to look at, your description is
                all the moderator has. Link to the posts involved if you can.
            </p>
            <?= $this->partial('partials/field-error', ['field' => 'details']) ?>
        </div>

        <div class="form-buttons">
            <a class="btn btn-quiet" href="<?= $this->e($this->route('user.profile', ['username' => (string) $profile['username']])) ?>">Cancel</a>
            <button type="submit" class="btn btn-accent">Submit report</button>
        </div>
    </form>
</section>
