<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $post
 * @var array<int,array<string,mixed>> $revisions
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Edit history</h1>
        <p class="page-subtitle">
            Post #<?= (int) $post['id'] ?> in
            <a href="<?= $this->e($this->route('topic.show', ['slug' => (string) $post['topic_slug']])) ?>"><?= $this->e((string) $post['topic_title']) ?></a>
            · <?= (int) $post['edit_count'] ?> revision(s)
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('post.permalink', ['id' => (int) $post['id']])) ?>">Back to post</a>
    </div>
</header>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Current version</h2></header>
    <div class="panel-inset revision-body"><?= $this->content((string) $post['content']) ?></div>
</section>

<?php if ($revisions === []): ?>
<?= $this->partial('partials/empty', ['message' => 'This post has never been edited.']) ?>
<?php endif; ?>

<?php foreach ($revisions as $revision): ?>
<section class="panel revision">
    <header class="panel-head">
        <h2 class="panel-title">
            Revision from <?= $this->e($this->date((string) $revision['created_at'], 'Y-m-d H:i')) ?>
        </h2>
        <span class="panel-meta mono">
            by <?= $this->e((string) ($revision['editor_username'] ?? 'removed member')) ?>
<?php if (($revision['reason'] ?? null) !== null): ?>
            · reason: <?= $this->e((string) $revision['reason']) ?>
<?php endif; ?>
        </span>
    </header>
    <div class="revision-columns">
        <div class="revision-column">
            <h3 class="revision-heading">Before</h3>
            <pre class="revision-raw"><?= $this->e((string) $revision['content_before']) ?></pre>
        </div>
        <div class="revision-column">
            <h3 class="revision-heading">After</h3>
            <pre class="revision-raw"><?= $this->e((string) $revision['content_after']) ?></pre>
        </div>
    </div>
</section>
<?php endforeach; ?>
