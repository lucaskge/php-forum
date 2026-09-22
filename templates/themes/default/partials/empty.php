<?php
/**
 * @var App\Support\View $this
 * @var string $message
 * @var string|null $action_url
 * @var string|null $action_label
 */
?>
<div class="empty-state">
    <p class="empty-message"><?= $this->e($message) ?></p>
<?php if (($action_url ?? null) !== null): ?>
    <p><a class="btn btn-accent" href="<?= $this->e((string) $action_url) ?>"><?= $this->e((string) ($action_label ?? 'Continue')) ?></a></p>
<?php endif; ?>
</div>
