<?php
/** @var App\Support\View $this */
$items = [
    ['label' => 'Overview', 'url' => $this->route('moderation'), 'permission' => 'moderation.access'],
    ['label' => 'Reports', 'url' => $this->route('moderation.reports'), 'permission' => 'report.view', 'badge' => (int) $this->shared('pending_reports', 0)],
    ['label' => 'Content queue', 'url' => $this->route('moderation.queue'), 'permission' => 'moderation.access'],
    ['label' => 'Members', 'url' => $this->route('moderation.users'), 'permission' => 'moderation.access'],
    ['label' => 'Moderation log', 'url' => $this->route('moderation.log'), 'permission' => 'moderation.log.view'],
];
$path = (string) $this->shared('current_path');
?>
<aside class="panel-sidebar" aria-label="Moderation">
    <p class="panel-sidebar-title">Moderation</p>
    <nav class="panel-nav-group">
        <ul>
<?php foreach ($items as $item): ?>
<?php if (!$this->can($item['permission'])) { continue; } ?>
            <li>
                <a href="<?= $this->e($item['url']) ?>" class="<?= $path === $item['url'] ? 'is-current' : '' ?>">
                    <?= $this->e($item['label']) ?>
<?php if (($item['badge'] ?? 0) > 0): ?>
                    <span class="pill pill-warn"><?= (int) $item['badge'] ?></span>
<?php endif; ?>
                </a>
            </li>
<?php endforeach; ?>
        </ul>
    </nav>
<?php if ($this->shared('is_admin')): ?>
    <nav class="panel-nav-group">
        <p class="panel-nav-heading">Elsewhere</p>
        <ul>
            <li><a href="<?= $this->e($this->route('admin')) ?>">Administration</a></li>
        </ul>
    </nav>
<?php endif; ?>
    <p class="panel-sidebar-foot"><a href="<?= $this->e($this->url('/')) ?>">&larr; Back to the board</a></p>
</aside>
