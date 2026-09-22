<?php /** @var App\Support\View $this */ ?>
<section class="error-page">
    <p class="error-code mono">401</p>
    <h1 class="error-title">Authentication required</h1>
    <p class="error-message">This part of the board is only available to signed-in members.</p>
    <div class="error-actions">
        <a class="btn btn-accent" href="<?= $this->e($this->route('auth.login.show')) ?>">Sign in</a>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('auth.register.show')) ?>">Register</a>
    </div>
</section>
