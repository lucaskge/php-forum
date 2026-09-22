<?php
/**
 * @var App\Support\View $this
 * @var array<int,string> $channels
 * @var array<int,string> $lines
 */
?>
<header class="page-head">
    <div><h1 class="page-title">Logs</h1>
    <p class="page-subtitle">Newest entries first, up to 300 lines.</p></div>
</header>

<?php if ($channels === []): ?>
<?= $this->partial('partials/empty', ['message' => 'No log files have been written yet.']) ?>
<?php else: ?>
<nav class="tabbar" aria-label="Log channels">
<?php foreach ($channels as $name): ?>
    <a class="tab<?= $channel === $name ? ' is-current' : '' ?>" href="<?= $this->e($this->route('admin.logs', [], ['channel' => $name])) ?>"><?= $this->e($name) ?></a>
<?php endforeach; ?>
</nav>

<section class="panel">
    <header class="panel-head">
        <h2 class="panel-title"><?= $this->e($channel) ?>.log</h2>
        <form class="inline-form" action="<?= $this->e($this->route('admin.logs.clear')) ?>" method="post">
            <?= $this->csrf() ?>
            <input type="hidden" name="channel" value="<?= $this->e($channel) ?>">
            <button type="submit" class="btn btn-small btn-danger-quiet">Clear this log</button>
        </form>
    </header>
<?php if ($lines === []): ?>
    <div class="panel-inset muted">This log is empty.</div>
<?php else: ?>
    <pre class="log-output"><?php foreach ($lines as $line): ?><?= $this->e($line) ?>
<?php endforeach; ?></pre>
<?php endif; ?>
</section>
<?php endif; ?>
