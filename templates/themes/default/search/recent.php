<?php
/**
 * @var App\Support\View $this
 * @var App\Support\Paginator $paginator
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Recent topics</h1>
        <p class="page-subtitle">Everything posted across the forums you can read, newest first.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('search')) ?>">Advanced search</a>
    </div>
</header>

<section class="panel">
<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'Nothing has been posted yet.']) ?>
<?php else: ?>
    <div class="result-list">
<?php foreach ($paginator->items() as $row): ?>
        <article class="result">
            <header class="result-head">
                <a class="result-title" href="<?= $this->e($this->route('topic.show', ['slug' => (string) $row['topic_slug']])) ?>">
                    <?= $this->e((string) $row['topic_title']) ?>
                </a>
                <span class="result-meta mono">
                    <a href="<?= $this->e($this->route('forum.show', ['slug' => (string) $row['forum_slug']])) ?>"><?= $this->e((string) $row['forum_name']) ?></a>
<?php if (($row['author_username'] ?? null) !== null): ?>
                    · <?= $this->username($row) ?>
<?php endif; ?>
                    · <?= $this->e($this->relative((string) $row['created_at'])) ?>
                    · <?= $this->number($row['post_count'] ?? 0) ?> post(s)
                    · <?= $this->number($row['view_count'] ?? 0) ?> view(s)
                </span>
            </header>
            <div class="result-body"><?= $this->e($this->excerpt((string) ($row['excerpt_source'] ?? ''), 240)) ?></div>
        </article>
<?php endforeach; ?>
    </div>
<?php endif; ?>
</section>

<?= $this->partial('partials/pagination', ['paginator' => $paginator]) ?>
