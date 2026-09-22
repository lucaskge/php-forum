<?php /** @var App\Support\View $this */ ?>
<section class="auth-panel">
    <header class="auth-head">
        <h1 class="auth-title">Registration is closed</h1>
    </header>
    <div class="panel-inset">
        <p><?= $this->e((string) $message) ?></p>
        <p class="muted">Existing members can still sign in.</p>
    </div>
    <footer class="auth-foot">
        <a href="<?= $this->e($this->route('auth.login.show')) ?>">Sign in</a>
        <a href="<?= $this->e($this->url('/')) ?>">Board index</a>
    </footer>
</section>
