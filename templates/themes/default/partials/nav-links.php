<?php
/**
 * Navigation list, rendered twice: inline on wide screens and inside a
 * <details> disclosure on narrow ones. Both are plain HTML — the mobile menu
 * opens with the browser's own disclosure behaviour, no script involved.
 *
 * @var App\Support\View $this
 * @var array<string,mixed>|null $user
 * @var string $variant
 */
$chatEnabled = (bool) $this->shared('chat_enabled', true);
?>
<ul class="navlist navlist-<?= $this->e($variant) ?>">
    <li><a href="<?= $this->e($this->url('/')) ?>" class="<?= $this->shared('current_path') === '/' ? 'is-current' : '' ?>">Index</a></li>
    <li><a href="<?= $this->e($this->route('search.recent')) ?>" class="<?= $this->active('/recent') ? 'is-current' : '' ?>">Recent</a></li>
    <li><a href="<?= $this->e($this->route('members')) ?>" class="<?= $this->active('/members') ? 'is-current' : '' ?>">Members</a></li>
<?php if ($chatEnabled): ?>
    <li><a href="<?= $this->e($this->route('chat')) ?>" class="<?= $this->active('/chat') ? 'is-current' : '' ?>">Chat</a></li>
<?php endif; ?>
    <li><a href="<?= $this->e($this->route('rules')) ?>" class="<?= $this->active('/rules') ? 'is-current' : '' ?>">Rules</a></li>
<?php if ($user !== null): ?>
    <li>
        <a href="<?= $this->e($this->route('messages.inbox')) ?>" class="<?= $this->active('/messages') ? 'is-current' : '' ?>">
            Messages<?php if ($unread_messages > 0): ?> <span class="pill pill-accent"><?= (int) $unread_messages ?></span><?php endif; ?>
        </a>
    </li>
    <li>
        <a href="<?= $this->e($this->route('notifications')) ?>" class="<?= $this->active('/notifications') ? 'is-current' : '' ?>">
            Alerts<?php if ($unread_notifications > 0): ?> <span class="pill pill-accent"><?= (int) $unread_notifications ?></span><?php endif; ?>
        </a>
    </li>
    <li><a href="<?= $this->e($this->route('settings.profile')) ?>" class="<?= $this->active('/settings') ? 'is-current' : '' ?>">Settings</a></li>
<?php endif; ?>
<?php if ($this->shared('is_staff')): ?>
    <li>
        <a class="nav-staff <?= $this->active('/moderation') ? 'is-current' : '' ?>" href="<?= $this->e($this->route('moderation')) ?>">
            Moderation<?php if ($pending_reports > 0): ?> <span class="pill pill-warn"><?= (int) $pending_reports ?></span><?php endif; ?>
        </a>
    </li>
<?php endif; ?>
<?php if ($this->shared('is_admin')): ?>
    <li><a class="nav-staff <?= $this->active('/admin') ? 'is-current' : '' ?>" href="<?= $this->e($this->route('admin')) ?>">Admin</a></li>
<?php endif; ?>
</ul>
