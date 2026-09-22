<?php
/**
 * Confirmation page — the board uses real pages rather than script dialogs.
 *
 * @var App\Support\View $this
 * @var array<string,mixed> $topic
 */
?>
<section class="panel panel-danger">
    <header class="panel-head"><h1 class="panel-title">Delete topic</h1></header>
    <div class="panel-inset">
        <p>You are about to delete <strong><?= $this->e((string) $topic['title']) ?></strong> along with its <?= $this->number($topic['post_count']) ?> post(s).</p>
        <p class="muted">The topic is removed from the board but kept in the database, so a moderator can restore it.</p>

        <form class="stacked-form" action="<?= $this->e($this->route('topic.destroy', ['slug' => (string) $topic['slug']])) ?>" method="post">
            <?= $this->csrf() ?>
            <div class="field">
                <label class="field-label" for="reason">Reason <span class="muted">(optional)</span></label>
                <input class="field-input" type="text" id="reason" name="reason" maxlength="255">
            </div>
            <div class="form-buttons">
                <a class="btn btn-quiet" href="<?= $this->e($this->route('topic.show', ['slug' => (string) $topic['slug']])) ?>">Cancel</a>
                <button type="submit" class="btn btn-danger">Delete topic</button>
            </div>
        </form>
    </div>
</section>
