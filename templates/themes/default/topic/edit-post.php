<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $post
 */
$old = $this->shared('old_input', []);
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Edit post #<?= (int) $post['id'] ?></h1>
        <p class="page-subtitle">in <a href="<?= $this->e($this->route('topic.show', ['slug' => (string) $post['topic_slug']])) ?>"><?= $this->e((string) $post['topic_title']) ?></a></p>
    </div>
</header>

<?php if ($is_moderator_edit): ?>
<div class="alert alert-warning">
    <span class="alert-tag">warn</span>
    <span class="alert-body">You are editing someone else's post. The change is recorded in the moderation log and in the post's edit history.</span>
</div>
<?php endif; ?>

<section class="panel">
    <form class="stacked-form" action="<?= $this->e($this->route('post.update', ['id' => (int) $post['id']])) ?>" method="post">
        <?= $this->csrf() ?>
        <?= $this->partial('partials/editor', [
            'name' => 'content',
            'value' => (string) ($old['content'] ?? $post['content']),
            'label' => 'Message',
            'rows' => 16,
        ]) ?>

        <div class="field">
            <label class="field-label" for="reason">Edit reason <span class="muted">(optional, shown in the history)</span></label>
            <input class="field-input" type="text" id="reason" name="reason" maxlength="190"
                   value="<?= $this->e((string) ($old['reason'] ?? '')) ?>">
        </div>

        <div class="form-buttons">
            <a class="btn btn-quiet" href="<?= $this->e($this->route('post.permalink', ['id' => (int) $post['id']])) ?>">Cancel</a>
            <button type="submit" class="btn btn-accent">Save changes</button>
        </div>
    </form>
</section>
