<?php
/** @var App\Support\View $this */
$items = [
    ['label' => 'Profile', 'url' => $this->route('settings.profile')],
    ['label' => 'Avatar', 'url' => $this->route('settings.avatar')],
    ['label' => 'Account', 'url' => $this->route('settings.account')],
    ['label' => 'Password', 'url' => $this->route('settings.password')],
    ['label' => 'Preferences', 'url' => $this->route('settings.preferences')],
    ['label' => 'Subscriptions', 'url' => $this->route('settings.subscriptions')],
    ['label' => 'Bookmarks', 'url' => $this->route('settings.bookmarks')],
];
$path = (string) $this->shared('current_path');
?>
<nav class="tabbar" aria-label="Account settings">
<?php foreach ($items as $item): ?>
    <a class="tab<?= $path === $item['url'] ? ' is-current' : '' ?>" href="<?= $this->e($item['url']) ?>"><?= $this->e($item['label']) ?></a>
<?php endforeach; ?>
</nav>
