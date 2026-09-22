<?php
/**
 * @var App\Support\View $this
 * @var string $folder
 * @var int $unread
 */
?>
<nav class="tabbar" aria-label="Messages">
    <a class="tab<?= $folder === 'inbox' ? ' is-current' : '' ?>" href="<?= $this->e($this->route('messages.inbox')) ?>">
        Inbox<?php if ($unread > 0): ?> <span class="pill pill-accent"><?= (int) $unread ?></span><?php endif; ?>
    </a>
    <a class="tab<?= $folder === 'sent' ? ' is-current' : '' ?>" href="<?= $this->e($this->route('messages.sent')) ?>">Sent</a>
    <a class="tab<?= $folder === 'compose' ? ' is-current' : '' ?>" href="<?= $this->e($this->route('messages.compose')) ?>">Compose</a>
</nav>
