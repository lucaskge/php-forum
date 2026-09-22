<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed>|null $category
 */
$old = $this->shared('old_input', []);
$isNew = $category === null;
?>
<header class="page-head">
    <div><h1 class="page-title"><?= $isNew ? 'New category' : 'Edit category' ?></h1></div>
    <div class="page-actions"><a class="btn btn-quiet" href="<?= $this->e($this->route('admin.forums')) ?>">Back</a></div>
</header>

<section class="panel">
    <form class="stacked-form panel-form" action="<?= $this->e($this->route('admin.category.save')) ?>" method="post">
        <?= $this->csrf() ?>
<?php if (!$isNew): ?>
        <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
<?php endif; ?>
        <div class="form-grid">
            <div class="field">
                <label class="field-label" for="name">Name</label>
                <input class="field-input" type="text" id="name" name="name" maxlength="96" required
                       value="<?= $this->e((string) ($old['name'] ?? $category['name'] ?? '')) ?>">
                <?= $this->partial('partials/field-error', ['field' => 'name']) ?>
            </div>
            <div class="field">
                <label class="field-label" for="slug">Identifier</label>
                <input class="field-input" type="text" id="slug" name="slug" maxlength="120"
                       value="<?= $this->e((string) ($old['slug'] ?? $category['slug'] ?? '')) ?>">
                <p class="field-hint">Left empty it is derived from the name.</p>
                <?= $this->partial('partials/field-error', ['field' => 'slug']) ?>
            </div>
        </div>

        <div class="field">
            <label class="field-label" for="description">Description</label>
            <input class="field-input" type="text" id="description" name="description" maxlength="255"
                   value="<?= $this->e((string) ($old['description'] ?? $category['description'] ?? '')) ?>">
        </div>

        <div class="form-grid">
            <div class="field">
                <label class="field-label" for="position">Position</label>
                <input class="field-input" type="number" id="position" name="position" min="0" max="999"
                       value="<?= (int) ($old['position'] ?? $category['position'] ?? 0) ?>">
            </div>
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="is_visible" value="1" <?= $isNew || (int) $category['is_visible'] === 1 ? 'checked' : '' ?>>
                    <span>Visible on the board index</span>
                </label>
            </div>
        </div>

        <div class="form-buttons">
            <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.forums')) ?>">Cancel</a>
            <button type="submit" class="btn btn-accent"><?= $isNew ? 'Create category' : 'Save category' ?></button>
        </div>
    </form>
</section>

<?php if (!$isNew): ?>
<section class="panel panel-danger">
    <header class="panel-head"><h2 class="panel-title">Delete category</h2></header>
    <div class="panel-form">
        <p class="muted">Deleting a category removes every forum, topic and post inside it. This cannot be undone.</p>
        <form action="<?= $this->e($this->route('admin.category.delete', ['id' => (int) $category['id']])) ?>" method="post">
            <?= $this->csrf() ?>
            <button type="submit" class="btn btn-danger">Delete category and contents</button>
        </form>
    </div>
</section>
<?php endif; ?>
