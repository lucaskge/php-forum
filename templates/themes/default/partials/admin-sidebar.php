<?php
/** @var App\Support\View $this */
$groups = [
    'General' => [
        ['label' => 'Dashboard', 'url' => $this->route('admin'), 'permission' => 'admin.access'],
        ['label' => 'Site settings', 'url' => $this->route('admin.settings'), 'permission' => 'admin.settings'],
    ],
    'Users' => [
        ['label' => 'User list', 'url' => $this->route('admin.users'), 'permission' => 'admin.users'],
        ['label' => 'Roles', 'url' => $this->route('admin.roles'), 'permission' => 'admin.roles'],
        ['label' => 'Permissions', 'url' => $this->route('admin.permissions'), 'permission' => 'admin.roles'],
        ['label' => 'Bans', 'url' => $this->route('admin.bans'), 'permission' => 'admin.users'],
    ],
    'Forums' => [
        ['label' => 'Categories & forums', 'url' => $this->route('admin.forums'), 'permission' => 'admin.forums'],
    ],
    'Content' => [
        ['label' => 'Topics', 'url' => $this->route('admin.topics'), 'permission' => 'admin.content'],
        ['label' => 'Posts', 'url' => $this->route('admin.posts'), 'permission' => 'admin.content'],
        ['label' => 'Reports', 'url' => $this->route('moderation.reports'), 'permission' => 'report.view'],
        ['label' => 'Moderation log', 'url' => $this->route('moderation.log'), 'permission' => 'moderation.log.view'],
    ],
    'Appearance' => [
        ['label' => 'Themes', 'url' => $this->route('admin.themes'), 'permission' => 'admin.themes'],
        ['label' => 'Colours and type', 'url' => $this->route('admin.theme.appearance', ['slug' => $this->themes()->activeSlug()]), 'permission' => 'admin.themes'],
    ],
    'Chat' => [
        ['label' => 'Rooms & transport', 'url' => $this->route('admin.chat'), 'permission' => 'admin.chat'],
        ['label' => 'Chat restrictions', 'url' => $this->route('admin.chat.bans'), 'permission' => 'admin.chat'],
    ],
    'System' => [
        ['label' => 'System information', 'url' => $this->route('admin.system'), 'permission' => 'admin.system'],
        ['label' => 'Logs', 'url' => $this->route('admin.logs'), 'permission' => 'admin.system'],
        ['label' => 'Maintenance', 'url' => $this->route('admin.maintenance'), 'permission' => 'admin.system'],
    ],
];
$path = (string) $this->shared('current_path');
?>
<aside class="panel-sidebar" aria-label="Administration">
    <p class="panel-sidebar-title">Administration</p>
<?php foreach ($groups as $group => $items): ?>
<?php
    $visible = array_values(array_filter($items, fn (array $item): bool => $this->can($item['permission'])));

    if ($visible === []) {
        continue;
    }
?>
    <nav class="panel-nav-group" aria-label="<?= $this->e($group) ?>">
        <p class="panel-nav-heading"><?= $this->e($group) ?></p>
        <ul>
<?php foreach ($visible as $item): ?>
            <li>
                <a href="<?= $this->e($item['url']) ?>" class="<?= $path === $item['url'] || ($item['url'] !== '/admin' && str_starts_with($path, $item['url'])) ? 'is-current' : '' ?>">
                    <?= $this->e($item['label']) ?>
                </a>
            </li>
<?php endforeach; ?>
        </ul>
    </nav>
<?php endforeach; ?>
    <p class="panel-sidebar-foot"><a href="<?= $this->e($this->url('/')) ?>">&larr; Back to the board</a></p>
</aside>
