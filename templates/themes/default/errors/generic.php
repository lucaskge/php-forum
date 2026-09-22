<?php
/**
 * Fallback error page. Specific status codes get their own template.
 *
 * @var App\Support\View $this
 * @var int $status
 */
?>
<section class="error-page">
    <p class="error-code mono"><?= (int) $status ?></p>
    <h1 class="error-title"><?= $this->e($title) ?></h1>
    <p class="error-message"><?= $this->e($message) ?></p>
    <p class="error-path mono"><?= $this->e($path) ?></p>
    <div class="error-actions">
        <a class="btn btn-accent" href="<?= $this->e($this->url('/')) ?>">Board index</a>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('search')) ?>">Search</a>
    </div>
</section>
