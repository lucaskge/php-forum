<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $forum
 */
$old = $this->shared('old_input', []);
?>
<header class="page-head">
    <div>
        <h1 class="page-title">New topic</h1>
        <p class="page-subtitle">Posting in <a href="<?= $this->e($this->route('forum.show', ['slug' => (string) $forum['slug']])) ?>"><?= $this->e((string) $forum['name']) ?></a></p>
    </div>
</header>

<section class="panel">
    <form class="stacked-form" action="<?= $this->e($this->route('topic.store', ['forum' => (string) $forum['slug']])) ?>" method="post">
        <?= $this->csrf() ?>
        <div class="field">
            <label class="field-label" for="title">Subject</label>
            <input class="field-input" type="text" id="title" name="title" maxlength="190" required
                   value="<?= $this->e((string) ($old['title'] ?? '')) ?>">
            <?= $this->partial('partials/field-error', ['field' => 'title']) ?>
        </div>

        <?= $this->partial('partials/editor', [
            'name' => 'content',
            'value' => (string) ($old['content'] ?? ''),
            'label' => 'Message',
            'rows' => 16,
        ]) ?>

        <div class="form-row form-row-split">
            <label class="check">
                <input type="checkbox" name="subscribe" value="1" checked>
                <span>Subscribe to this topic</span>
            </label>
            <div class="form-buttons">
                <a class="btn btn-quiet" href="<?= $this->e($this->route('forum.show', ['slug' => (string) $forum['slug']])) ?>">Cancel</a>
                <button type="submit" class="btn btn-accent">Post topic</button>
            </div>
        </div>
    </form>
</section>
