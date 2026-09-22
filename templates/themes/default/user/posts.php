<?php
/**
 * @var App\Support\View $this
 * @var App\Support\Paginator $paginator
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Posts by <?= $this->e((string) $profile['username']) ?></h1>
        <p class="page-subtitle"><?= $this->number($paginator->total()) ?> post(s) visible to you</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('user.profile', ['username' => (string) $profile['username']])) ?>">Profile</a>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('user.topics', ['username' => (string) $profile['username']])) ?>">Topics</a>
    </div>
</header>

<?php if ($paginator->items() === []): ?>
<?= $this->partial('partials/empty', ['message' => 'No posts to show.']) ?>
<?php endif; ?>

<div class="result-list">
<?php foreach ($paginator->items() as $post): ?>
    <article class="result">
        <header class="result-head">
            <a class="result-title" href="<?= $this->e($this->route('post.permalink', ['id' => (int) $post['id']])) ?>">
                <?= $this->e((string) $post['topic_title']) ?>
            </a>
            <span class="result-meta mono">
                <a href="<?= $this->e($this->route('forum.show', ['slug' => (string) $post['forum_slug']])) ?>"><?= $this->e((string) $post['forum_name']) ?></a>
                · <?= $this->e($this->date((string) $post['created_at'], 'Y-m-d H:i')) ?>
            </span>
        </header>
        <div class="result-body"><?= $this->e($this->excerpt((string) $post['content'], 320)) ?></div>
    </article>
<?php endforeach; ?>
</div>

<?= $this->partial('partials/pagination', ['paginator' => $paginator]) ?>
