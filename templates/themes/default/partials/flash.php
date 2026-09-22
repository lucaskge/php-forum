<?php
/**
 * @var App\Support\View $this
 * @var array<int,array{type:string,message:string}> $flash_messages
 */
$messages = $this->shared('flash_messages', []);

if ($messages === []) {
    return;
}
?>
<div class="flash-stack" role="status" aria-live="polite">
<?php foreach ($messages as $message): ?>
    <div class="alert alert-<?= $this->e($message['type']) ?>">
        <span class="alert-tag" aria-hidden="true"><?= $this->e(match ($message['type']) {
            'success' => 'ok',
            'error' => 'err',
            'warning' => 'warn',
            default => 'info',
        }) ?></span>
        <span class="alert-body"><?= $this->e($message['message']) ?></span>
    </div>
<?php endforeach; ?>
</div>
