<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $topic
 */
$old = $this->shared('old_input', []);
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Edit topic</h1>
        <p class="page-subtitle"><a href="<?= $this->e($this->route('topic.show', ['slug' => (string) $topic['slug']])) ?>"><?= $this->e((string) $topic['title']) ?></a></p>
    </div>
</header>

<section class="panel">
    <form class="stacked-form" action="<?= $this->e($this->route('topic.update', ['slug' => (string) $topic['slug']])) ?>" method="post">
        <?= $this->csrf() ?>
        <div class="field">
            <label class="field-label" for="title">Subject</label>
            <input class="field-input" type="text" id="title" name="title" maxlength="190" required
                   value="<?= $this->e((string) ($old['title'] ?? $topic['title'])) ?>">
            <?= $this->partial('partials/field-error', ['field' => 'title']) ?>
            <p class="field-hint">The address of the topic does not change when the subject is edited.</p>
        </div>
        <div class="form-buttons">
            <a class="btn btn-quiet" href="<?= $this->e($this->route('topic.show', ['slug' => (string) $topic['slug']])) ?>">Cancel</a>
            <button type="submit" class="btn btn-accent">Save subject</button>
        </div>
    </form>
</section>
