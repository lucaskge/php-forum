<?php /** @var App\Support\View $this */ ?>
<header class="page-head">
    <div><h1 class="page-title">Maintenance</h1>
    <p class="page-subtitle">Housekeeping tasks and the offline switch.</p></div>
</header>

<section class="panel<?= $maintenance_mode ? ' panel-danger' : '' ?>">
    <header class="panel-head"><h2 class="panel-title">Maintenance mode</h2></header>
    <div class="panel-form">
        <p>
            Currently <strong><?= $maintenance_mode ? 'ON' : 'OFF' ?></strong>.
            While on, every visitor except administrators sees the offline notice — including on form submissions,
            so nothing is written to the board meanwhile.
        </p>
<?php if ($maintenance_message !== ''): ?>
        <p class="muted">Message shown: <?= $this->e($maintenance_message) ?></p>
<?php endif; ?>
        <form action="<?= $this->e($this->route('admin.maintenance.task')) ?>" method="post">
            <?= $this->csrf() ?>
            <input type="hidden" name="task" value="toggle-maintenance">
            <button type="submit" class="btn <?= $maintenance_mode ? 'btn-accent' : 'btn-danger' ?>">
                Turn maintenance mode <?= $maintenance_mode ? 'off' : 'on' ?>
            </button>
        </form>
        <p class="muted">
            The message itself is edited under
            <a href="<?= $this->e($this->route('admin.settings.group', ['group' => 'general'])) ?>">site settings</a>.
        </p>
    </div>
</section>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Housekeeping</h2></header>
    <div class="panel-form operations-grid">
        <form action="<?= $this->e($this->route('admin.maintenance.task')) ?>" method="post">
            <?= $this->csrf() ?>
            <input type="hidden" name="task" value="purge-sessions">
            <p class="muted">Remove session-tracking rows older than a day. Currently tracking <?= $this->number($stale_sessions) ?>.</p>
            <button type="submit" class="btn btn-small">Purge session records</button>
        </form>

        <form action="<?= $this->e($this->route('admin.maintenance.task')) ?>" method="post">
            <?= $this->csrf() ?>
            <input type="hidden" name="task" value="purge-throttles">
            <p class="muted">Drop expired rate-limit buckets.</p>
            <button type="submit" class="btn btn-small">Purge throttles</button>
        </form>

        <form action="<?= $this->e($this->route('admin.maintenance.task')) ?>" method="post">
            <?= $this->csrf() ?>
            <input type="hidden" name="task" value="expire-bans">
            <p class="muted">Clear suspensions whose end date has passed and restore those accounts.</p>
            <button type="submit" class="btn btn-small">Expire lapsed suspensions</button>
        </form>

    </div>
</section>
