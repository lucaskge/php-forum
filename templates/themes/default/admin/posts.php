<?php
/**
 * @var App\Support\View $this
 * @var App\Support\Paginator $paginator
 */
?>
<header class="page-head">
    <div><h1 class="page-title">Posts</h1><p class="page-subtitle"><?= $this->number($paginator->total()) ?> post(s)</p></div>
    <div class="page-actions"><a class="btn btn-quiet" href="<?= $this->e($this->route('admin.topics')) ?>">Topics</a></div>
</header>

<section class="panel">
    <form class="filter-bar" action="<?= $this->e($this->formAction('admin.posts')) ?>" method="get">
        <?= $this->routeField('admin.posts') ?>
        <div class="field field-inline">
            <label class="field-label" for="q">Content contains</label>
            <input class="field-input" type="search" id="q" name="q" value="<?= $this->e((string) $filters['search']) ?>" maxlength="120">
        </div>
        <div class="field field-inline">
            <label class="field-label" for="author">Author</label>
            <input class="field-input" type="text" id="author" name="author" value="<?= $this->e((string) $filters['author']) ?>" maxlength="32">
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
<?php foreach (['' => 'Any', 'hidden' => 'Hidden', 'deleted' => 'Deleted', 'edited' => 'Edited'] as $value => $label): ?>
                <option value="<?= $this->e((string) $value) ?>" <?= (string) $filters['status'] === (string) $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-accent">Apply</button>
            <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.posts')) ?>">Reset</a>
        </div>
    </form>

<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'No posts matched.']) ?>
<?php else: ?>
    <table class="board-table data-table">
        <caption class="visually-hidden">Posts</caption>
        <thead><tr><th scope="col">ID</th><th scope="col">Excerpt</th><th scope="col">Topic</th><th scope="col">Author</th><th scope="col">When</th><th scope="col">State</th><th scope="col" class="col-actions">Actions</th></tr></thead>
        <tbody>
<?php foreach ($paginator->items() as $post): ?>
            <tr>
                <td class="mono"><?= (int) $post['id'] ?></td>
                <td><?= $this->e($this->excerpt((string) $post['content'], 90)) ?></td>
                <td><a href="<?= $this->e($this->route('topic.show', ['slug' => (string) $post['topic_slug']])) ?>"><?= $this->e(App\Support\Str::limit((string) $post['topic_title'], 40)) ?></a></td>
                <td><?= $this->e((string) ($post['author_username'] ?? 'removed member')) ?></td>
                <td class="mono"><?= $this->e($this->date((string) $post['created_at'], 'Y-m-d H:i')) ?></td>
                <td class="mono muted">
<?= (int) $post['is_hidden'] === 1 ? 'hidden ' : '' ?><?= $post['deleted_at'] !== null ? 'deleted ' : '' ?><?= (int) $post['edit_count'] > 0 ? 'edited' : '' ?>
                </td>
                <td class="col-actions">
                    <a class="linklike" href="<?= $this->e($this->route('post.permalink', ['id' => (int) $post['id']])) ?>">open</a>
                    <a class="linklike" href="<?= $this->e($this->route('post.history', ['id' => (int) $post['id']])) ?>">history</a>
<?php if ($post['deleted_at'] !== null): ?>
                    <form class="inline-form" action="<?= $this->e($this->route('admin.post.restore', ['id' => (int) $post['id']])) ?>" method="post">
                        <?= $this->csrf() ?>
                        <button type="submit" class="linklike">restore</button>
                    </form>
                    <form class="inline-form" action="<?= $this->e($this->route('admin.post.purge', ['id' => (int) $post['id']])) ?>" method="post">
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
