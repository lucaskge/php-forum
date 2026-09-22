<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $message
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title"><?= $this->e((string) $message['subject']) ?></h1>
        <p class="page-subtitle">
            From <?= $this->username($message, ['fallback' => 'a removed member']) ?>
            to <?= $this->username(['recipient_username' => $message['recipient_username'] ?? null, 'recipient_role_id' => $message['recipient_role_id'] ?? null], ['fallback' => 'you']) ?>
            · <?= $this->e($this->date((string) $message['created_at'], 'Y-m-d H:i')) ?>
        </p>
    </div>
    <div class="page-actions">
<?php if (($message['sender_username'] ?? null) !== null && $is_recipient): ?>
        <a class="btn btn-accent" href="<?= $this->e($this->route('messages.compose', [], ['reply_to' => (int) $message['id']])) ?>">Reply</a>
<?php endif; ?>
        <form class="inline-form" action="<?= $this->e($this->route('messages.delete', ['id' => (int) $message['id']])) ?>" method="post">
            <?= $this->csrf() ?>
            <button type="submit" class="btn btn-danger-quiet">Delete</button>
        </form>
    </div>
</header>
<?= $this->partial('partials/messages-nav', ['folder' => $folder, 'unread' => $unread]) ?>

<article class="panel message-view">
    <header class="message-view-head">
        <?= $this->avatar($message, 40) ?>
        <div>
            <p class="message-view-from"><?= $this->username($message) ?></p>
            <p class="message-view-meta mono"><?= $this->e($this->date((string) $message['created_at'], 'Y-m-d H:i')) ?></p>
        </div>
    </header>
    <div class="post-content message-view-body"><?= $this->content((string) $message['body']) ?></div>
</article>

<?php if (count($thread) > 1): ?>
<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Conversation</h2></header>
    <ul class="feed-list">
<?php foreach ($thread as $entry): ?>
        <li class="feed-item<?= (int) $entry['id'] === (int) $message['id'] ? ' is-current' : '' ?>">
            <a class="feed-title" href="<?= $this->e($this->route('messages.show', ['id' => (int) $entry['id']])) ?>"><?= $this->e((string) $entry['subject']) ?></a>
            <span class="feed-meta"><?= $this->username($entry) ?> · <?= $this->e($this->date((string) $entry['created_at'], 'Y-m-d H:i')) ?></span>
        </li>
<?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
