<?php /** @var App\Support\View $this */ ?>
<section class="error-page">
    <p class="error-code mono">405</p>
    <h1 class="error-title">Method not allowed</h1>
    <p class="error-message"><?= $this->e($message) ?></p>
    <p class="error-path mono"><?= $this->e($path) ?></p>
    <div class="error-actions">
        <a class="btn btn-accent" href="<?= $this->e($this->url('/')) ?>">Board index</a>
    </div>
</section>
