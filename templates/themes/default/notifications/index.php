<?php
/**
 * @var App\Support\View $this
 * @var App\Support\Paginator $paginator
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Notifications</h1>
        <p class="page-subtitle">
<?php if ($just_read !== []): ?>
            <?= $this->number(count($just_read)) ?> new, marked as read just now ·
<?php endif; ?>
            <?= $this->number($unread_count) ?> still unread of <?= $this->number($paginator->total()) ?>
        </p>
    </div>
    <div class="page-actions">
        <form class="inline-form" action="<?= $this->e($this->route('notifications.read-all')) ?>" method="post">
            <?= $this->csrf() ?>
            <button type="submit" class="btn btn-quiet">Mark all read</button>
        </form>
        <form class="inline-form" action="<?= $this->e($this->route('notifications.clear')) ?>" method="post">
            <?= $this->csrf() ?>
            <button type="submit" class="btn btn-danger-quiet">Clear all</button>
        </form>
    </div>
</header>

<nav class="tabbar" aria-label="Notification filter">
    <a class="tab<?= $unread_only ? '' : ' is-current' ?>" href="<?= $this->e($this->route('notifications')) ?>">All</a>
    <a class="tab<?= $unread_only ? ' is-current' : '' ?>" href="<?= $this->e($this->route('notifications', [], ['filter' => 'unread'])) ?>">Unread</a>
</nav>

<section class="panel">
<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', ['message' => $unread_only ? 'Nothing unread.' : 'No notifications yet.']) ?>
<?php else: ?>
    <ul class="notification-list">
<?php foreach ($paginator->items() as $notification): ?>
<?php
    // Unread when the page loaded — it has just been marked read, but showing
    // it as new is the point: you can still see what arrived since last time.
    $wasUnread = (int) $notification['is_read'] === 0
        || in_array((int) $notification['id'], $just_read ?? [], true);
?>
        <li class="notification<?= $wasUnread ? ' is-unread' : '' ?>">
            <span class="notification-kind mono"><?= $this->e(App\Models\NotificationType::markerFor((string) $notification['type'])) ?></span>
            <div class="notification-body">
                <a class="notification-title" href="<?= $this->e($this->route('notifications.open', ['id' => (int) $notification['id']])) ?>">
                    <?= $this->e((string) $notification['title']) ?>
                </a>
<?php if (($notification['body'] ?? '') !== ''): ?>
                <p class="notification-text"><?= $this->e(App\Support\Str::limit((string) $notification['body'], 220)) ?></p>
<?php endif; ?>
                <p class="notification-meta mono"><?= $this->e($this->date((string) $notification['created_at'], 'Y-m-d H:i')) ?></p>
            </div>
            <form class="inline-form" action="<?= $this->e($this->route('notifications.delete', ['id' => (int) $notification['id']])) ?>" method="post">
                <?= $this->csrf() ?>
                <button type="submit" class="linklike linklike-danger">remove</button>
            </form>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
</section>

<?= $this->partial('partials/pagination', ['paginator' => $paginator]) ?>
