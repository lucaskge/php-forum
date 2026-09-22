<?php /** @var App\Support\View $this */ ?>
<section class="error-page">
    <p class="error-code mono">403</p>
    <h1 class="error-title">Forbidden</h1>
    <p class="error-message"><?= $this->e($message) ?></p>
    <p class="error-path mono"><?= $this->e($path) ?></p>
    <div class="error-actions">
        <a class="btn btn-accent" href="<?= $this->e($this->url('/')) ?>">Board index</a>
<?php if ($this->shared('current_user') === null): ?>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('auth.login.show')) ?>">Sign in</a>
<?php endif; ?>
    </div>
</section>
