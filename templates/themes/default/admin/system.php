<?php /** @var App\Support\View $this */ ?>
<header class="page-head">
    <div><h1 class="page-title">System information</h1>
    <p class="page-subtitle mono">php <?= $this->e($php_version) ?> · <?= $this->e($database_version) ?></p></div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.logs')) ?>">Logs</a>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.maintenance')) ?>">Maintenance</a>
    </div>
</header>

<div class="split-panels split-panels-wide">
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Application</h2></header>
        <dl class="stat-list stat-list-wide">
<?php foreach ($app as $label => $value): ?>
            <div><dt><?= $this->e(ucfirst(str_replace('_', ' ', (string) $label))) ?></dt><dd class="mono"><?= $this->e((string) $value) ?></dd></div>
<?php endforeach; ?>
            <div><dt>Server</dt><dd class="mono"><?= $this->e($server) ?></dd></div>
        </dl>
    </section>

    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Runtime limits</h2></header>
        <dl class="stat-list stat-list-wide">
<?php foreach ($limits as $label => $value): ?>
            <div><dt class="mono"><?= $this->e((string) $label) ?></dt><dd class="mono"><?= $this->e((string) $value) ?></dd></div>
<?php endforeach; ?>
        </dl>
    </section>
</div>

<div class="split-panels split-panels-wide">
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Extensions</h2></header>
        <dl class="stat-list stat-list-wide">
<?php foreach ($extensions as $name => $loaded): ?>
            <div>
                <dt class="mono"><?= $this->e((string) $name) ?></dt>
                <dd><?= $loaded ? '<span class="status-badge status-resolved">loaded</span>' : '<span class="status-badge status-dismissed">missing</span>' ?></dd>
            </div>
<?php endforeach; ?>
        </dl>
    </section>

    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Writable paths</h2></header>
        <dl class="stat-list stat-list-wide">
<?php foreach ($writable as $path => $ok): ?>
            <div>
                <dt class="mono"><?= $this->e((string) $path) ?></dt>
                <dd><?= $ok ? '<span class="status-badge status-resolved">writable</span>' : '<span class="status-badge status-dismissed">not writable</span>' ?></dd>
            </div>
<?php endforeach; ?>
        </dl>
    </section>
</div>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Database · <?= $this->e($database_name) ?></h2></header>
    <table class="board-table data-table">
        <caption class="visually-hidden">Tables</caption>
        <thead><tr><th scope="col">Table</th><th scope="col" class="col-num">Approx. rows</th><th scope="col" class="col-num">Size (KB)</th></tr></thead>
        <tbody>
<?php foreach ($tables as $table): ?>
            <tr>
                <td class="mono"><?= $this->e((string) $table['name']) ?></td>
                <td class="col-num"><?= $this->number($table['approx_rows'] ?? 0) ?></td>
                <td class="col-num"><?= $this->e((string) ($table['size_kb'] ?? '0')) ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Applied migrations</h2></header>
    <table class="board-table data-table">
        <caption class="visually-hidden">Migrations</caption>
        <thead><tr><th scope="col">File</th><th scope="col">Batch</th><th scope="col">Executed</th></tr></thead>
        <tbody>
<?php foreach ($migrations as $migration): ?>
            <tr>
                <td class="mono"><?= $this->e((string) $migration['filename']) ?></td>
                <td class="mono"><?= (int) $migration['batch'] ?></td>
                <td class="mono"><?= $this->e($this->date((string) $migration['executed_at'], 'Y-m-d H:i')) ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</section>
