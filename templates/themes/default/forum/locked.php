<?php
/**
 * Shown when a forum is listed but the viewer may not read it.
 *
 * @var App\Support\View $this
 * @var array<string,mixed> $forum
 */
?>
<section class="panel panel-notice">
    <header class="panel-head"><h1 class="panel-title"><?= $this->e((string) $forum['name']) ?></h1></header>
    <div class="panel-inset">
        <p>This forum is visible to your account but its contents are restricted.</p>
<?php if (($forum['description'] ?? '') !== ''): ?>
        <p class="muted"><?= $this->e((string) $forum['description']) ?></p>
<?php endif; ?>
<?php if ($this->shared('current_user') === null): ?>
        <p><a class="btn btn-accent" href="<?= $this->e($this->route('auth.login.show')) ?>">Sign in</a>
           <a class="btn btn-quiet" href="<?= $this->e($this->route('auth.register.show')) ?>">Register</a></p>
<?php else: ?>
        <p class="muted">If you believe this is a mistake, contact a moderator.</p>
<?php endif; ?>
    </div>
</section>
