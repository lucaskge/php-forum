<?php
/**
 * The post editor. No toolbar buttons that need scripting — the reference is
 * printed next to the field instead.
 *
 * @var App\Support\View $this
 * @var string $name
 * @var string $value
 * @var string $label
 * @var int $rows
 */
$name = $name ?? 'content';
$value = $value ?? '';
$label = $label ?? 'Message';
$rows = $rows ?? 14;
$errors = $this->shared('form_errors', []);
$error = $errors[$name] ?? null;
?>
<div class="field<?= $error !== null ? ' field-invalid' : '' ?>">
    <label class="field-label" for="editor-<?= $this->e($name) ?>"><?= $this->e($label) ?></label>
    <textarea class="field-input editor" id="editor-<?= $this->e($name) ?>" name="<?= $this->e($name) ?>"
              rows="<?= (int) $rows ?>" required
              <?= $error !== null ? 'aria-invalid="true" aria-describedby="editor-' . $this->e($name) . '-error"' : '' ?>><?= $this->e($value) ?></textarea>
<?php if ($error !== null): ?>
    <p class="field-error" id="editor-<?= $this->e($name) ?>-error"><?= $this->e($error) ?></p>
<?php endif; ?>
    <details class="format-help">
        <summary>Formatting tags</summary>
        <ul class="format-list">
<?php foreach (App\Support\ContentFormatter::reference() as $tag): ?>
            <li><code><?= $this->e($tag['example']) ?></code></li>
<?php endforeach; ?>
        </ul>
        <p class="field-hint">
            Every tag except <code>[*]</code> and <code>[hr]</code> must be closed with its
            <code>[/tag]</code> counterpart, or it is shown as plain text.
            <a href="<?= $this->e($this->route('help')) ?>">Full reference</a>.
        </p>
    </details>
</div>
