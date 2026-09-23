<?php
/**
 * @var App\Support\View $this
 * @var App\Support\Paginator $paginator
 */
?>
<header class="page-head">
    <div><h1 class="page-title">Topics</h1><p class="page-subtitle"><?= $this->number($paginator->total()) ?> topic(s)</p></div>
    <div class="page-actions"><a class="btn btn-quiet" href="<?= $this->e($this->route('admin.posts')) ?>">Posts</a></div>
</header>

<section class="panel">
    <form class="filter-bar" action="<?= $this->e($this->formAction('admin.topics')) ?>" method="get">
        <?= $this->routeField('admin.topics') ?>
        <div class="field field-inline">
            <label class="field-label" for="q">Subject contains</label>
            <input class="field-input" type="search" id="q" name="q" value="<?= $this->e((string) $filters['search']) ?>" maxlength="120">
        </div>
        <div class="field field-inline">
            <label class="field-label" for="forum">Forum</label>
            <select class="field-input" id="forum" name="forum">
                <option value="">Any</option>
<?php foreach ($forums as $forum): ?>
                <option value="<?= (int) $forum['id'] ?>" <?= (int) $filters['forum'] === (int) $forum['id'] ? 'selected' : '' ?>><?= $this->e((string) $forum['name']) ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="field field-inline">
            <label class="field-label" for="status">State</label>
            <select class="field-input" id="status" name="status">
<?php foreach (['' => 'Any', 'pinned' => 'Pinned', 'locked' => 'Locked', 'hidden' => 'Hidden', 'archived' => 'Archived', 'deleted' => 'Deleted'] as $value => $label): ?>
                <option value="<?= $this->e((string) $value) ?>" <?= (string) $filters['status'] === (string) $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-accent">Apply</button>
            <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.topics')) ?>">Reset</a>
        </div>
    </form>

<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'No topics matched.']) ?>
<?php else: ?>
    <table class="board-table data-table">
        <caption class="visually-hidden">Topics</caption>
        <thead><tr><th scope="col">ID</th><th scope="col">Subject</th><th scope="col">Forum</th><th scope="col">Author</th><th scope="col" class="col-num">Posts</th><th scope="col">State</th><th scope="col" class="col-actions">Actions</th></tr></thead>
        <tbody>
<?php foreach ($paginator->items() as $topic): ?>
            <tr>
                <td class="mono"><?= (int) $topic['id'] ?></td>
                <td><a href="<?= $this->e($this->route('topic.show', ['slug' => (string) $topic['slug']])) ?>"><?= $this->e(App\Support\Str::limit((string) $topic['title'], 60)) ?></a></td>
                <td><?= $this->e((string) $topic['forum_name']) ?></td>
                <td><?= $this->e((string) ($topic['author_username'] ?? 'removed member')) ?></td>
                <td class="col-num"><?= $this->number($topic['post_count']) ?></td>
                <td class="mono muted">
<?= (int) $topic['is_pinned'] === 1 ? 'pin ' : '' ?><?= (int) $topic['is_locked'] === 1 ? 'lock ' : '' ?><?= (int) $topic['is_hidden'] === 1 ? 'hidden ' : '' ?><?= $topic['deleted_at'] !== null ? 'deleted' : '' ?>
                </td>
                <td class="col-actions">
                    <a class="linklike" href="<?= $this->e($this->route('moderation.topic', ['slug' => (string) $topic['slug']])) ?>">panel</a>
<?php if ($topic['deleted_at'] !== null): ?>
                    <form class="inline-form" action="<?= $this->e($this->route('admin.topic.restore', ['id' => (int) $topic['id']])) ?>" method="post">
                        <?= $this->csrf() ?>
                        <button type="submit" class="linklike">restore</button>
                    </form>
                    <form class="inline-form" action="<?= $this->e($this->route('admin.topic.purge', ['id' => (int) $topic['id']])) ?>" method="post">
                        <?= $this->csrf() ?>
                        <button type="submit" class="linklike linklike-danger">purge</button>
                    </form>
<?php endif; ?>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</section>

<?= $this->partial('partials/pagination', ['paginator' => $paginator]) ?>
