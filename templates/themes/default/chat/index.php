<?php
/**
 * Chat room.
 *
 * The transcript is rendered by the server on every request. The page is built
 * so a future real-time transport can replace the refresh without changing the
 * markup: the message list, the composer and the presence column are already
 * the shape such a transport would update.
 *
 * @var App\Support\View $this
 * @var array<string,mixed> $room
 * @var array<int,array<string,mixed>> $messages
 * @var App\Services\Chat\ChatTransport $transport
 */
$user = $this->shared('current_user');
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Chat · <?= $this->e((string) $room['name']) ?></h1>
        <p class="page-subtitle">
<?php if (($room['topic_line'] ?? '') !== ''): ?>
            <?= $this->e((string) $room['topic_line']) ?>
<?php endif; ?>
<?php if (!$transport->isRealtime()): ?>
            <span class="mono">reload the page to see new messages</span>
<?php endif; ?>
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('chat.room', ['room' => (string) $room['slug']])) ?>">Refresh</a>
<?php if ($can_moderate): ?>
        <a class="btn btn-mod" href="<?= $this->e($this->route('chat.moderate', ['room' => (string) $room['slug']])) ?>">Moderate</a>
<?php endif; ?>
    </div>
</header>

<?php if (count($rooms) > 1): ?>
<nav class="tabbar" aria-label="Chat rooms">
<?php foreach ($rooms as $entry): ?>
    <a class="tab<?= (int) $entry['id'] === (int) $room['id'] ? ' is-current' : '' ?>"
       href="<?= $this->e($this->route('chat.room', ['room' => (string) $entry['slug']])) ?>"><?= $this->e((string) $entry['name']) ?></a>
<?php endforeach; ?>
</nav>
<?php endif; ?>

<div class="chat-layout">
    <section class="panel chat-panel">
        <header class="panel-head">
            <h2 class="panel-title">Transcript</h2>
            <span class="panel-meta mono"><?= $this->number(count($messages)) ?> most recent message(s)</span>
        </header>

        <ol class="chat-log" aria-label="Chat messages">
<?php if ($messages === []): ?>
            <li class="chat-empty">No messages in this room yet. Say something.</li>
<?php endif; ?>
<?php foreach ($messages as $message): ?>
<?php if ((string) $message['type'] === 'system'): ?>
            <li class="chat-line chat-line-system">
                <time class="chat-time mono" datetime="<?= $this->e((string) $message['created_at']) ?>"><?= $this->e($this->date((string) $message['created_at'], 'H:i')) ?></time>
                <span class="chat-system"><?= $this->e((string) $message['content']) ?></span>
            </li>
<?php continue; endif; ?>
            <li class="chat-line<?= (int) $message['is_deleted'] === 1 ? ' is-removed' : '' ?>" id="chat-<?= (int) $message['id'] ?>">
                <time class="chat-time mono" datetime="<?= $this->e((string) $message['created_at']) ?>" title="<?= $this->e($this->date((string) $message['created_at'], 'Y-m-d H:i')) ?>">
                    <?= $this->e($this->date((string) $message['created_at'], 'H:i')) ?>
                </time>
                <?= $this->avatar($message, 22) ?>
                <span class="chat-author"><?= $this->username($message) ?></span>
                <span class="chat-text">
<?php if ((int) $message['is_deleted'] === 1): ?>
                    <em class="muted">message removed by <?= $this->e((string) ($message['deleted_by_username'] ?? 'a moderator')) ?></em>
<?php else: ?>
                    <?= $this->e((string) $message['content']) ?>
<?php endif; ?>
                </span>
<?php if ($can_moderate && (int) $message['is_deleted'] === 0): ?>
                <form class="inline-form chat-mod" action="<?= $this->e($this->route('chat.message.delete', ['room' => (string) $room['slug'], 'id' => (int) $message['id']])) ?>" method="post">
                    <?= $this->csrf() ?>
                    <button type="submit" class="linklike linklike-danger" title="Delete this message">del</button>
                </form>
<?php endif; ?>
            </li>
<?php endforeach; ?>
        </ol>
        <span id="chat-end" class="chat-anchor" aria-hidden="true"></span>

<?php if ($can_post): ?>
        <form class="chat-composer" action="<?= $this->e($this->route('chat.send', ['room' => (string) $room['slug']])) ?>" method="post">
            <?= $this->csrf() ?>
            <label class="visually-hidden" for="chat-message">Your message</label>
            <input class="field-input" type="text" id="chat-message" name="content" maxlength="<?= (int) $max_length ?>"
                   placeholder="type a message and press enter…" autocomplete="off" required>
            <button type="submit" class="btn btn-accent">Send</button>
        </form>
        <p class="chat-hint mono">
            up to <?= (int) $max_length ?> characters
<?php if ($slow_mode > 0): ?>
            · slow mode: one message every <?= (int) $slow_mode ?>s
<?php endif; ?>
            · messages are plain text · the page updates when you reload
        </p>
<?php elseif ($restriction !== null): ?>
        <div class="chat-blocked">
            <p><strong>You cannot post here.</strong> <?= $this->e((string) $restriction['reason']) ?></p>
            <p class="muted mono">
                <?= $this->e((string) $restriction['type']) ?>
<?php if ($restriction['expires_at'] !== null): ?>
                until <?= $this->e($this->date((string) $restriction['expires_at'], 'Y-m-d H:i')) ?>
<?php else: ?>
                — no expiry set
<?php endif; ?>
            </p>
        </div>
<?php elseif ($user === null): ?>
        <div class="chat-blocked">
            <p>Chat is readable by everyone. <a href="<?= $this->e($this->route('auth.login.show')) ?>">Sign in</a> or
               <a href="<?= $this->e($this->route('auth.register.show')) ?>">register</a> to take part.</p>
        </div>
<?php elseif ((int) $room['is_readonly'] === 1): ?>
        <div class="chat-blocked"><p>This room is read-only.</p></div>
<?php else: ?>
        <div class="chat-blocked"><p>Your account does not have permission to post in chat.</p></div>
<?php endif; ?>
    </section>

    <aside class="chat-side">
        <section class="panel">
            <header class="panel-head"><h2 class="panel-title">In the room</h2></header>
            <ul class="user-list">
<?php foreach ($present as $member): ?>
                <?= $this->partial('partials/user-card', ['member' => $member, 'meta' => $this->relative((string) $member['last_seen_at'])]) ?>
<?php endforeach; ?>
<?php if ($present === []): ?>
                <li class="muted">Nobody here in the last few minutes.</li>
<?php endif; ?>
            </ul>
            <p class="panel-note muted">Presence is recorded when a member loads the room.</p>
        </section>

        <section class="panel">
            <header class="panel-head"><h2 class="panel-title">Room rules</h2></header>
            <div class="panel-inset chat-rules">
<?php if ($rules !== ''): ?>
                <?= $this->content((string) $rules) ?>
<?php else: ?>
                <p class="muted">No specific chat rules are configured. The board rules apply.</p>
<?php endif; ?>
                <p class="mono muted"><a href="<?= $this->e($this->route('rules')) ?>">Board rules &rarr;</a></p>
            </div>
        </section>

<?php if ($can_moderate): ?>
        <section class="panel panel-mod">
            <header class="panel-head"><h2 class="panel-title">Moderation</h2></header>
            <p class="panel-note mono">transport: <?= $this->e($transport->name()) ?><?= $transport->isRealtime() ? ' · live' : ' · polled' ?></p>
            <ul class="link-list">
                <li><a href="<?= $this->e($this->route('chat.moderate', ['room' => (string) $room['slug']])) ?>">Mute, ban or purge a member</a></li>
<?php if ($this->can('admin.chat')): ?>
                <li><a href="<?= $this->e($this->route('admin.chat')) ?>">Room settings and transport</a></li>
                <li><a href="<?= $this->e($this->route('admin.chat.bans')) ?>">Active chat restrictions</a></li>
<?php endif; ?>
            </ul>
            <p class="panel-note muted">Deleted messages stay visible to staff with their author.</p>
        </section>
<?php endif; ?>
    </aside>
</div>
