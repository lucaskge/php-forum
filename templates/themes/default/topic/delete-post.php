<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $post
 */
?>
<section class="panel panel-danger">
    <header class="panel-head"><h1 class="panel-title">Delete post #<?= (int) $post['id'] ?></h1></header>
    <div class="panel-inset">
        <p>This post will be removed from <strong><?= $this->e((string) $post['topic_title']) ?></strong>.</p>
        <div class="quoted-preview"><?= $this->content((string) $post['content']) ?></div>

        <form class="stacked-form" action="<?= $this->e($this->route('post.destroy', ['id' => (int) $post['id']])) ?>" method="post">
            <?= $this->csrf() ?>
<?php if ($is_moderator_action): ?>
            <div class="field">
                <label class="field-label" for="reason">Reason <span class="muted">(recorded in the moderation log)</span></label>
                <input class="field-input" type="text" id="reason" name="reason" maxlength="255">
            </div>
<?php endif; ?>
            <div class="form-buttons">
                <a class="btn btn-quiet" href="<?= $this->e($this->route('post.permalink', ['id' => (int) $post['id']])) ?>">Cancel</a>
                <button type="submit" class="btn btn-danger">Delete post</button>
            </div>
        </form>
    </div>
</section>
