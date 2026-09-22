<?php /** @var App\Support\View $this */ ?>
<section class="error-page">
    <p class="error-code mono">400</p>
    <h1 class="error-title">Bad request</h1>
    <p class="error-message">The server could not make sense of that request. A form field was missing or malformed.</p>
    <p class="error-path mono"><?= $this->e($path) ?></p>
    <div class="error-actions">
        <a class="btn btn-accent" href="<?= $this->e($this->url('/')) ?>">Board index</a>
    </div>
</section>
