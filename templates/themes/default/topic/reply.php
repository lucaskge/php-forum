<?php
/**
 * Reply form. When reached with ?quote=<id> the editor arrives pre-filled with
 * the quoted post — quoting is a server-side round trip, nothing more.
 *
 * @var App\Support\View $this
 * @var array<string,mixed> $topic
 * @var string $prefill
 */
$old = $this->shared('old_input', []);
$value = (string) ($old['content'] ?? $prefill);
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Reply</h1>
        <p class="page-subtitle">
            to <a href="<?= $this->e($this->route('topic.show', ['slug' => (string) $topic['slug']])) ?>"><?= $this->e((string) $topic['title']) ?></a>
            in <a href="<?= $this->e($this->route('forum.show', ['slug' => (string) $topic['forum_slug']])) ?>"><?= $this->e((string) $topic['forum_name']) ?></a>
        </p>
    </div>
</header>

<section class="panel">
    <form class="stacked-form" action="<?= $this->e($this->route('topic.reply.store', ['slug' => (string) $topic['slug']])) ?>" method="post">
        <?= $this->csrf() ?>
<?php if ($quoted_user_id !== null): ?>
        <input type="hidden" name="quoted_user_id" value="<?= (int) $quoted_user_id ?>">
<?php endif; ?>
        <?= $this->partial('partials/editor', ['name' => 'content', 'value' => $value, 'label' => 'Your reply', 'rows' => 16]) ?>

        <div class="form-row form-row-split">
            <label class="check">
                <input type="checkbox" name="subscribe" value="1" checked>
                <span>Notify me about new replies</span>
            </label>
            <div class="form-buttons">
                <a class="btn btn-quiet" href="<?= $this->e($this->route('topic.show', ['slug' => (string) $topic['slug']])) ?>">Cancel</a>
                <button type="submit" class="btn btn-accent">Post reply</button>
            </div>
        </div>
    </form>
</section>

<?php if ($recent_posts !== []): ?>
<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Topic review — first posts</h2></header>
    <div class="review-list">
<?php foreach ($recent_posts as $post): ?>
        <article class="review-item">
            <header class="review-head">
                <strong><?= $this->e((string) ($post['author_username'] ?? 'removed member')) ?></strong>
                <time datetime="<?= $this->e((string) $post['created_at']) ?>"><?= $this->e($this->date((string) $post['created_at'], 'Y-m-d H:i')) ?></time>
            </header>
            <div class="review-body"><?= $this->content((string) $post['content']) ?></div>
        </article>
<?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
