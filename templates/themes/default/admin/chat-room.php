<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed>|null $room
 */
$old = $this->shared('old_input', []);
$isNew = $room === null;
?>
<header class="page-head">
    <div><h1 class="page-title"><?= $isNew ? 'New chat room' : 'Edit chat room' ?></h1></div>
    <div class="page-actions"><a class="btn btn-quiet" href="<?= $this->e($this->route('admin.chat')) ?>">Back</a></div>
</header>

<section class="panel">
    <form class="stacked-form panel-form" action="<?= $this->e($this->route('admin.chat.room.save')) ?>" method="post">
        <?= $this->csrf() ?>
<?php if (!$isNew): ?>
        <input type="hidden" name="id" value="<?= (int) $room['id'] ?>">
<?php endif; ?>
        <div class="form-grid">
            <div class="field">
                <label class="field-label" for="name">Name</label>
                <input class="field-input" type="text" id="name" name="name" maxlength="96" required
                       value="<?= $this->e((string) ($old['name'] ?? $room['name'] ?? '')) ?>">
                <?= $this->partial('partials/field-error', ['field' => 'name']) ?>
            </div>
            <div class="field">
                <label class="field-label" for="slug">Identifier</label>
                <input class="field-input" type="text" id="slug" name="slug" maxlength="48"
                       value="<?= $this->e((string) ($old['slug'] ?? $room['slug'] ?? '')) ?>">
                <?= $this->partial('partials/field-error', ['field' => 'slug']) ?>
            </div>
        </div>

        <div class="field">
            <label class="field-label" for="description">Description</label>
            <input class="field-input" type="text" id="description" name="description" maxlength="255"
                   value="<?= $this->e((string) ($old['description'] ?? $room['description'] ?? '')) ?>">
        </div>

        <div class="field">
            <label class="field-label" for="topic_line">Topic line</label>
            <input class="field-input" type="text" id="topic_line" name="topic_line" maxlength="255"
                   value="<?= $this->e((string) ($old['topic_line'] ?? $room['topic_line'] ?? '')) ?>">
            <p class="field-hint">Shown under the room title.</p>
        </div>

        <div class="form-grid form-grid-3">
            <div class="field">
                <label class="field-label" for="slow_mode">Slow mode (seconds)</label>
                <input class="field-input" type="number" id="slow_mode" name="slow_mode" min="0" max="3600"
                       value="<?= (int) ($old['slow_mode'] ?? $room['slow_mode'] ?? 0) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="position">Position</label>
                <input class="field-input" type="number" id="position" name="position" min="0" max="999"
                       value="<?= (int) ($old['position'] ?? $room['position'] ?? 0) ?>">
            </div>
            <div class="field">
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" <?= $isNew || (int) $room['is_active'] === 1 ? 'checked' : '' ?>>
                    <span>Active</span>
                </label>
                <label class="check">
                    <input type="checkbox" name="is_readonly" value="1" <?= !$isNew && (int) $room['is_readonly'] === 1 ? 'checked' : '' ?>>
                    <span>Read-only</span>
                </label>
            </div>
        </div>

        <div class="form-buttons">
            <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.chat')) ?>">Cancel</a>
            <button type="submit" class="btn btn-accent"><?= $isNew ? 'Create room' : 'Save room' ?></button>
        </div>
    </form>
</section>
