<?php /** @var App\Support\View $this */ ?>
<section class="error-page">
    <p class="error-code mono">503</p>
    <h1 class="error-title">Switched off</h1>
    <p class="error-message"><?= $this->e($message) ?></p>
    <p class="error-message muted">
        An administrator has disabled this part of the board. It is off for
        everybody, staff included, until it is switched back on.
    </p>
    <div class="error-actions">
        <a class="btn btn-accent" href="<?= $this->e($this->url('/')) ?>">Board index</a>
    </div>
</section>
