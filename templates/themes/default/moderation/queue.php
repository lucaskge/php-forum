<?php /** @var App\Support\View $this */ ?>
<header class="page-head">
    <div><h1 class="page-title">Content queue</h1>
    <p class="page-subtitle">Everything currently hidden or deleted, with one-click restore.</p></div>
</header>

<?php
$sections = [
    ['title' => 'Hidden posts', 'rows' => $hidden_posts, 'kind' => 'post'],
    ['title' => 'Deleted posts', 'rows' => $deleted_posts, 'kind' => 'post'],
    ['title' => 'Hidden topics', 'rows' => $hidden_topics, 'kind' => 'topic'],
    ['title' => 'Deleted topics', 'rows' => $deleted_topics, 'kind' => 'topic'],
];
?>
<?php foreach ($sections as $section): ?>
<section class="panel">
    <header class="panel-head">
        <h2 class="panel-title"><?= $this->e($section['title']) ?></h2>
        <span class="panel-meta mono"><?= $this->number(count($section['rows'])) ?></span>
    </header>
<?php if ($section['rows'] === []): ?>
    <div class="panel-inset muted">Nothing here.</div>
<?php else: ?>
    <table class="board-table data-table">
        <caption class="visually-hidden"><?= $this->e($section['title']) ?></caption>
        <thead><tr><th scope="col">Item</th><th scope="col">Forum</th><th scope="col">Author</th><th scope="col">When</th><th scope="col" class="col-actions">Actions</th></tr></thead>
        <tbody>
<?php foreach ($section['rows'] as $row): ?>
            <tr>
                <td>
<?php if ($section['kind'] === 'post'): ?>
                    <a href="<?= $this->e($this->route('post.permalink', ['id' => (int) $row['id']])) ?>">#<?= (int) $row['id'] ?> in <?= $this->e((string) $row['topic_title']) ?></a>
<?php else: ?>
                    <a href="<?= $this->e($this->route('topic.show', ['slug' => (string) $row['slug']])) ?>"><?= $this->e((string) $row['title']) ?></a>
<?php endif; ?>
                </td>
                <td><?= $this->e((string) ($row['forum_name'] ?? '—')) ?></td>
                <td><?= $this->e((string) ($row['author_username'] ?? 'removed member')) ?></td>
                <td class="mono"><?= $this->e($this->relative((string) $row['created_at'])) ?></td>
                <td class="col-actions">
<?php if ($section['kind'] === 'post'): ?>
                    <form class="inline-form" action="<?= $this->e($this->route('post.restore', ['id' => (int) $row['id']])) ?>" method="post">
                        <?= $this->csrf() ?>
                        <button type="submit" class="linklike">restore</button>
                    </form>
                    <form class="inline-form" action="<?= $this->e($this->route('post.visibility', ['id' => (int) $row['id']])) ?>" method="post">
                        <?= $this->csrf() ?>
                        <button type="submit" class="linklike">toggle hidden</button>
                    </form>
<?php else: ?>
                    <form class="inline-form" action="<?= $this->e($this->route('moderation.topic.restore', ['slug' => (string) $row['slug']])) ?>" method="post">
                        <?= $this->csrf() ?>
                        <button type="submit" class="linklike">restore</button>
                    </form>
                    <a class="linklike" href="<?= $this->e($this->route('moderation.topic', ['slug' => (string) $row['slug']])) ?>">panel</a>
<?php endif; ?>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</section>
<?php endforeach; ?>
