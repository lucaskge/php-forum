<?php /** @var App\Support\View $this */ ?>
<section class="error-page">
    <p class="error-code mono">500</p>
    <h1 class="error-title">Internal error</h1>
    <p class="error-message">Something broke while handling this request. The incident has been written to the log.</p>
<?php if (App\Support\Config::get('app.debug', false)): ?>
    <pre class="error-trace"><?= $this->e($message) ?></pre>
<?php endif; ?>
    <div class="error-actions">
        <a class="btn btn-accent" href="<?= $this->e($this->url('/')) ?>">Board index</a>
    </div>
</section>
