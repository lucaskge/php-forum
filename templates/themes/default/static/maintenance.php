<?php /** @var App\Support\View $this */ ?>
<section class="error-page">
    <p class="error-code mono">503</p>
    <h1 class="error-title">The board is offline</h1>
    <p class="error-message"><?= $this->e($message) ?></p>
    <p class="error-message muted">Administrators can still sign in and work.</p>
    <div class="error-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('auth.login.show')) ?>">Sign in</a>
    </div>
</section>
