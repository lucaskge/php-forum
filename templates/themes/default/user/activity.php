<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $profile
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Activity · <?= $this->e((string) $profile['username']) ?></h1>
        <p class="page-subtitle">The most recent contributions visible to your account.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('user.profile', ['username' => (string) $profile['username']])) ?>">Profile</a>
    </div>
</header>

<div class="split-panels">
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Topics started</h2></header>
        <ul class="feed-list">
<?php foreach ($topics as $topic): ?>
            <li class="feed-item">
                <a class="feed-title" href="<?= $this->e($this->route('topic.show', ['slug' => (string) $topic['slug']])) ?>"><?= $this->e((string) $topic['title']) ?></a>
                <span class="feed-meta"><?= $this->e((string) $topic['forum_name']) ?> · <?= $this->e($this->relative((string) $topic['created_at'])) ?></span>
            </li>
<?php endforeach; ?>
<?php if ($topics === []): ?>
            <li class="feed-item muted">Nothing yet.</li>
<?php endif; ?>
        </ul>
    </section>

    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Replies</h2></header>
        <ul class="feed-list">
<?php foreach ($posts as $post): ?>
            <li class="feed-item">
                <a class="feed-title" href="<?= $this->e($this->route('post.permalink', ['id' => (int) $post['id']])) ?>"><?= $this->e((string) $post['topic_title']) ?></a>
                <span class="feed-meta"><?= $this->e((string) $post['forum_name']) ?> · <?= $this->e($this->relative((string) $post['created_at'])) ?></span>
            </li>
<?php endforeach; ?>
<?php if ($posts === []): ?>
            <li class="feed-item muted">Nothing yet.</li>
<?php endif; ?>
        </ul>
    </section>
</div>
