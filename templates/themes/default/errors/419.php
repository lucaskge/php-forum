<?php /** @var App\Support\View $this */ ?>
<section class="error-page">
    <p class="error-code mono">419</p>
    <h1 class="error-title">The form expired</h1>
    <p class="error-message">
        Its security token no longer matches your session. This happens when a page sits open for a long time,
        when you sign in from another tab, or when a request comes from somewhere other than this board.
    </p>
    <p class="error-message muted">Nothing was saved. Go back, reload the page and submit it again.</p>
    <div class="error-actions">
        <a class="btn btn-accent" href="<?= $this->e($this->url('/')) ?>">Board index</a>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('auth.login.show')) ?>">Sign in again</a>
    </div>
</section>
