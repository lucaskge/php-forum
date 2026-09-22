<?php
/**
 * @var App\Support\View $this
 * @var array<int,array<string,mixed>> $categories
 * @var array<int,array<int,array<string,mixed>>> $forums_by_category
 */
?>
<header class="page-head">
    <div><h1 class="page-title">Categories and forums</h1>
    <p class="page-subtitle">Structure, ordering and per-forum permissions.</p></div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.category.form')) ?>">New category</a>
        <a class="btn btn-accent" href="<?= $this->e($this->route('admin.forum.form')) ?>">New forum</a>
    </div>
</header>

<form action="<?= $this->e($this->route('admin.forums.order')) ?>" method="post">
    <?= $this->csrf() ?>
<?php foreach ($categories as $category): ?>
<?php $categoryId = (int) $category['id']; ?>
    <section class="panel">
        <header class="panel-head">
            <h2 class="panel-title">
                <?= $this->e((string) $category['name']) ?>
<?php if ((int) $category['is_visible'] === 0): ?>
                <span class="tag tag-warn">hidden</span>
<?php endif; ?>
            </h2>
            <span class="panel-actions">
                <label class="inline-number">
                    <span class="visually-hidden">Position of <?= $this->e((string) $category['name']) ?></span>
                    <input type="number" name="category_position[<?= $categoryId ?>]" value="<?= (int) $category['position'] ?>" min="0" max="999">
                </label>
                <a class="linklike" href="<?= $this->e($this->route('admin.category.form', [], ['id' => $categoryId])) ?>">edit</a>
                <a class="linklike" href="<?= $this->e($this->route('admin.forum.form', [], ['category' => $categoryId])) ?>">add forum</a>
            </span>
        </header>

<?php $rows = $forums_by_category[$categoryId] ?? []; ?>
<?php if ($rows === []): ?>
        <div class="panel-inset muted">No forums in this category yet.</div>
<?php else: ?>
        <table class="board-table data-table">
            <caption class="visually-hidden">Forums in <?= $this->e((string) $category['name']) ?></caption>
            <thead><tr><th scope="col">Forum</th><th scope="col">Identifier</th><th scope="col" class="col-num">Topics</th><th scope="col" class="col-num">Posts</th><th scope="col">Flags</th><th scope="col">Order</th><th scope="col" class="col-actions">Actions</th></tr></thead>
            <tbody>
<?php foreach ($rows as $forum): ?>
                <tr>
                    <td>
                        <span class="forum-tree<?= $forum['parent_id'] !== null ? ' is-child' : '' ?>">
                            <span class="forum-icon" aria-hidden="true"><?= $this->e((string) $forum['icon']) ?></span>
                            <a href="<?= $this->e($this->route('admin.forum.form', [], ['id' => (int) $forum['id']])) ?>"><?= $this->e((string) $forum['name']) ?></a>
                        </span>
                    </td>
                    <td class="mono muted"><?= $this->e((string) $forum['slug']) ?></td>
                    <td class="col-num"><?= $this->number($forum['topic_count']) ?></td>
                    <td class="col-num"><?= $this->number($forum['post_count']) ?></td>
                    <td class="mono muted">
<?= (int) $forum['is_visible'] === 0 ? 'hidden ' : '' ?><?= (int) $forum['is_locked'] === 1 ? 'locked' : '' ?>
                    </td>
                    <td>
                        <label class="inline-number">
                            <span class="visually-hidden">Position of <?= $this->e((string) $forum['name']) ?></span>
                            <input type="number" name="position[<?= (int) $forum['id'] ?>]" value="<?= (int) $forum['position'] ?>" min="0" max="999">
                        </label>
                    </td>
                    <td class="col-actions">
                        <a class="linklike" href="<?= $this->e($this->route('admin.forum.form', [], ['id' => (int) $forum['id']])) ?>">edit</a>
                        <a class="linklike" href="<?= $this->e($this->route('admin.forum.permissions', ['id' => (int) $forum['id']])) ?>">permissions</a>
                        <a class="linklike" href="<?= $this->e($this->route('forum.show', ['slug' => (string) $forum['slug']])) ?>">view</a>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
<?php endif; ?>
    </section>
<?php endforeach; ?>

<?php if ($categories === []): ?>
    <?= $this->partial('partials/empty', [
        'message' => 'No categories yet. A forum always lives inside a category.',
        'action_url' => $this->route('admin.category.form'),
        'action_label' => 'Create the first category',
    ]) ?>
<?php endif; ?>

    <div class="form-buttons">
        <button type="submit" class="btn btn-accent">Save display order</button>
    </div>
</form>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Maintenance</h2></header>
    <div class="panel-form">
        <p class="muted">Recalculate every topic, post and last-post counter from the underlying rows. Safe to run at any time.</p>
        <form action="<?= $this->e($this->route('admin.forums.recount')) ?>" method="post">
            <?= $this->csrf() ?>
            <button type="submit" class="btn btn-small">Recount forums</button>
        </form>
    </div>
</section>
