<?php /** @var App\Support\View $this */ ?>
<section class="auth-panel">
    <header class="auth-head">
        <h1 class="auth-title">This link is no longer valid</h1>
    </header>
    <div class="panel-inset">
        <p>Password reset links can be used once and expire after an hour.</p>
        <p class="muted">Request a new one and follow it straight away.</p>
    </div>
    <footer class="auth-foot">
        <a href="<?= $this->e($this->route('auth.forgot.show')) ?>">Request a new link</a>
        <a href="<?= $this->e($this->route('auth.login.show')) ?>">Sign in</a>
    </footer>
</section>
