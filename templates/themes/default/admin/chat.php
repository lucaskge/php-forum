<?php
/**
 * @var App\Support\View $this
 * @var App\Services\Chat\ChatTransport $transport
 */
?>
<header class="page-head">
    <div><h1 class="page-title">Chat</h1>
    <p class="page-subtitle mono">
        <?= $enabled ? 'enabled' : 'disabled' ?> ·
        <?= $this->number($message_count) ?> message(s) ·
        <?= $this->number($restriction_count) ?> active restriction(s)
    </p></div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.chat.bans')) ?>">Restrictions</a>
        <a class="btn btn-accent" href="<?= $this->e($this->route('admin.chat.room')) ?>">New room</a>
    </div>
</header>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Transport</h2></header>
    <div class="panel-inset">
        <dl class="stat-list stat-list-wide">
            <div><dt>Active transport</dt><dd class="mono"><?= $this->e($transport->name()) ?></dd></div>
            <div><dt>Real-time</dt><dd><?= $transport->isRealtime() ? 'yes' : 'no — the page updates on reload' ?></dd></div>
            <div><dt>Endpoint</dt><dd class="mono"><?= $this->e($transport->endpoint() ?? 'none (same-origin HTTP)') ?></dd></div>
            <div><dt>Registered</dt><dd class="mono"><?= $this->e(implode(', ', $transports)) ?></dd></div>
        </dl>
        <p class="muted"><?= $this->e($transport->describe()) ?></p>
        <p class="muted">
            The chat feature talks to <code>App\Services\Chat\ChatTransport</code> only. To add WebSocket or SSE delivery,
            implement that interface, register it in <code>TransportFactory</code> and point the <code>chat_transport</code>
            setting at it — the page, routes and moderation tools stay as they are.
        </p>
        <p><a class="btn btn-small btn-quiet" href="<?= $this->e($this->route('admin.settings.group', ['group' => 'chat'])) ?>">Chat settings</a></p>
    </div>
</section>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Rooms</h2></header>
<?php if ($rooms === []): ?>
    <?= $this->partial('partials/empty', [
        'message' => 'No chat rooms configured.',
        'action_url' => $this->route('admin.chat.room'),
        'action_label' => 'Create a room',
    ]) ?>
<?php else: ?>
    <table class="board-table data-table">
        <caption class="visually-hidden">Chat rooms</caption>
        <thead><tr><th scope="col">Room</th><th scope="col">Identifier</th><th scope="col">State</th><th scope="col">Slow mode</th><th scope="col" class="col-actions">Actions</th></tr></thead>
        <tbody>
<?php foreach ($rooms as $room): ?>
            <tr>
                <td><?= $this->e((string) $room['name']) ?></td>
                <td class="mono muted"><?= $this->e((string) $room['slug']) ?></td>
                <td class="mono muted">
<?= (int) $room['is_active'] === 1 ? 'active ' : 'closed ' ?><?= (int) $room['is_readonly'] === 1 ? 'read-only' : '' ?>
                </td>
                <td class="mono"><?= (int) $room['slow_mode'] > 0 ? (int) $room['slow_mode'] . 's' : '—' ?></td>
                <td class="col-actions">
                    <a class="linklike" href="<?= $this->e($this->route('admin.chat.room', [], ['id' => (int) $room['id']])) ?>">edit</a>
                    <a class="linklike" href="<?= $this->e($this->route('chat.room', ['room' => (string) $room['slug']])) ?>">open</a>
                    <form class="inline-form" action="<?= $this->e($this->route('admin.chat.room.delete', ['id' => (int) $room['id']])) ?>" method="post">
                        <?= $this->csrf() ?>
                        <button type="submit" class="linklike linklike-danger">delete</button>
                    </form>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</section>
