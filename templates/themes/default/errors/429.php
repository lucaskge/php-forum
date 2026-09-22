<?php /** @var App\Support\View $this */ ?>
<section class="error-page">
    <p class="error-code mono">429</p>
    <h1 class="error-title">Too many requests</h1>
    <p class="error-message"><?= $this->e($message) ?></p>
    <p class="error-message muted">Rate limits protect the board from flooding. Wait a moment and try again.</p>
    <div class="error-actions">
        <a class="btn btn-accent" href="<?= $this->e($this->url('/')) ?>">Board index</a>
    </div>
</section>
