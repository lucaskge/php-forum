<?php
/**
 * @var App\Support\View $this
 * @var string $field
 */
$errors = $this->shared('form_errors', []);

if (!isset($errors[$field])) {
    return;
}
?>
<p class="field-error" id="<?= $this->e($field) ?>-error"><?= $this->e((string) $errors[$field]) ?></p>
