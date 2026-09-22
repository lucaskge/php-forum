<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $restriction
 */
?>
<section class="error-page">
    <p class="error-code mono"><?= $this->e(strtoupper((string) $restriction['type'])) ?></p>
    <h1 class="error-title">Your account is restricted</h1>
    <p class="error-message"><?= $this->e((string) $restriction['reason']) ?></p>
    <p class="error-message muted">
<?php if ($restriction['expires_at'] !== null): ?>
        The restriction lifts on <?= $this->e($this->date((string) $restriction['expires_at'], 'Y-m-d H:i')) ?>.
<?php else: ?>
        No end date was set.
<?php endif; ?>
        You can still read the board, but not post, send messages or use chat.
    </p>
    <div class="error-actions">
        <a class="btn btn-accent" href="<?= $this->e($this->url('/')) ?>">Board index</a>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('rules')) ?>">Board rules</a>
    </div>
</section>
