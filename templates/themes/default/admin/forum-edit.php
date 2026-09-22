<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed>|null $forum
 */
$old = $this->shared('old_input', []);
$isNew = $forum === null;
$selectedCategory = (int) ($old['category_id'] ?? $forum['category_id'] ?? $preset_category ?? 0);
$selectedParent = (int) ($old['parent_id'] ?? $forum['parent_id'] ?? 0);
?>
<header class="page-head">
    <div><h1 class="page-title"><?= $isNew ? 'New forum' : 'Edit forum' ?></h1>
<?php if (!$isNew): ?>
    <p class="page-subtitle mono"><?= $this->e((string) $forum['slug']) ?></p>
<?php endif; ?>
    </div>
    <div class="page-actions">
<?php if (!$isNew): ?>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.forum.permissions', ['id' => (int) $forum['id']])) ?>">Permissions</a>
<?php endif; ?>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.forums')) ?>">Back</a>
    </div>
</header>

<section class="panel">
    <form class="stacked-form panel-form" action="<?= $this->e($this->route('admin.forum.save')) ?>" method="post">
        <?= $this->csrf() ?>
<?php if (!$isNew): ?>
        <input type="hidden" name="id" value="<?= (int) $forum['id'] ?>">
<?php endif; ?>
        <div class="form-grid">
            <div class="field">
                <label class="field-label" for="name">Name</label>
                <input class="field-input" type="text" id="name" name="name" maxlength="96" required
                       value="<?= $this->e((string) ($old['name'] ?? $forum['name'] ?? '')) ?>">
                <?= $this->partial('partials/field-error', ['field' => 'name']) ?>
            </div>
            <div class="field">
                <label class="field-label" for="slug">Identifier</label>
                <input class="field-input" type="text" id="slug" name="slug" maxlength="120"
                       value="<?= $this->e((string) ($old['slug'] ?? $forum['slug'] ?? '')) ?>">
                <p class="field-hint">Used in the address: /forum/&lt;identifier&gt;</p>
                <?= $this->partial('partials/field-error', ['field' => 'slug']) ?>
            </div>
        </div>

        <div class="field">
            <label class="field-label" for="description">Description</label>
            <textarea class="field-input" id="description" name="description" rows="2" maxlength="500"><?= $this->e((string) ($old['description'] ?? $forum['description'] ?? '')) ?></textarea>
        </div>

        <div class="form-grid form-grid-3">
            <div class="field">
                <label class="field-label" for="category_id">Category</label>
                <select class="field-input" id="category_id" name="category_id" required>
                    <option value="">Choose…</option>
<?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= $selectedCategory === (int) $category['id'] ? 'selected' : '' ?>><?= $this->e((string) $category['name']) ?></option>
<?php endforeach; ?>
                </select>
                <?= $this->partial('partials/field-error', ['field' => 'category_id']) ?>
            </div>
            <div class="field">
                <label class="field-label" for="parent_id">Parent forum</label>
                <select class="field-input" id="parent_id" name="parent_id">
                    <option value="">None — top level</option>
<?php foreach ($parents as $candidate): ?>
                    <option value="<?= (int) $candidate['id'] ?>" <?= $selectedParent === (int) $candidate['id'] ? 'selected' : '' ?>><?= $this->e((string) $candidate['name']) ?></option>
<?php endforeach; ?>
                </select>
                <p class="field-hint">Pick a parent to make this a subforum.</p>
                <?= $this->partial('partials/field-error', ['field' => 'parent_id']) ?>
            </div>
            <div class="field">
                <label class="field-label" for="icon">Icon glyph</label>
                <input class="field-input" type="text" id="icon" name="icon" maxlength="16"
                       value="<?= $this->e((string) ($old['icon'] ?? $forum['icon'] ?? '#')) ?>">
                <p class="field-hint">A short text marker shown in the listing, e.g. <code>#</code>, <code>&gt;_</code>, <code>::</code>.</p>
            </div>
        </div>

        <div class="form-grid form-grid-3">
            <div class="field">
                <label class="field-label" for="position">Position</label>
                <input class="field-input" type="number" id="position" name="position" min="0" max="999"
                       value="<?= (int) ($old['position'] ?? $forum['position'] ?? 0) ?>">
            </div>
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="is_visible" value="1" <?= $isNew || (int) $forum['is_visible'] === 1 ? 'checked' : '' ?>>
                    <span>Visible</span>
                </label>
            </div>
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="is_locked" value="1" <?= !$isNew && (int) $forum['is_locked'] === 1 ? 'checked' : '' ?>>
                    <span>Locked — no new topics or replies</span>
                </label>
            </div>
        </div>

        <div class="form-buttons">
            <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.forums')) ?>">Cancel</a>
            <button type="submit" class="btn btn-accent"><?= $isNew ? 'Create forum' : 'Save forum' ?></button>
        </div>
    </form>
</section>

<?php if (!$isNew): ?>
<section class="panel panel-danger">
    <header class="panel-head"><h2 class="panel-title">Delete forum</h2></header>
    <div class="panel-form">
        <p class="muted">
            Deleting removes its subforums, topics and posts —
            <?= $this->number($forum['topic_count']) ?> topic(s) and <?= $this->number($forum['post_count']) ?> post(s) here.
        </p>
        <form action="<?= $this->e($this->route('admin.forum.delete', ['id' => (int) $forum['id']])) ?>" method="post">
            <?= $this->csrf() ?>
            <button type="submit" class="btn btn-danger">Delete forum and contents</button>
        </form>
    </div>
</section>
<?php endif; ?>
