<?php
/**
 * @var App\Support\View $this
 * @var App\Support\Paginator $paginator
 */
?>
<header class="page-head">
    <div><h1 class="page-title">Chat restrictions</h1>
    <p class="page-subtitle"><?= $this->number($paginator->total()) ?> record(s)</p></div>
    <div class="page-actions"><a class="btn btn-quiet" href="<?= $this->e($this->route('admin.chat')) ?>">Chat</a></div>
</header>

<section class="panel">
<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'Nobody is muted or banned from chat.']) ?>
<?php else: ?>
    <table class="board-table data-table">
        <caption class="visually-hidden">Chat restrictions</caption>
        <thead><tr><th scope="col">Member</th><th scope="col">Type</th><th scope="col">Room</th><th scope="col">Reason</th><th scope="col">By</th><th scope="col">Expires</th><th scope="col">State</th><th scope="col" class="col-actions">Actions</th></tr></thead>
        <tbody>
<?php foreach ($paginator->items() as $ban): ?>
            <tr>
                <td><a href="<?= $this->e($this->route('moderation.user', ['username' => (string) $ban['username']])) ?>"><?= $this->e((string) $ban['username']) ?></a></td>
                <td class="mono"><?= $this->e((string) $ban['type']) ?></td>
                <td><?= $this->e((string) ($ban['room_name'] ?? 'all rooms')) ?></td>
                <td><?= $this->e((string) $ban['reason']) ?></td>
                <td><?= $this->e((string) ($ban['moderator_username'] ?? 'system')) ?></td>
                <td class="mono"><?= $ban['expires_at'] === null ? 'never' : $this->e($this->date((string) $ban['expires_at'], 'Y-m-d H:i')) ?></td>
                <td><?= (int) $ban['is_active'] === 1 ? '<span class="status-badge status-pending">active</span>' : '<span class="status-badge status-resolved">lifted</span>' ?></td>
                <td class="col-actions">
<?php if ((int) $ban['is_active'] === 1): ?>
                    <form class="inline-form" action="<?= $this->e($this->route('admin.chat.ban.lift', ['id' => (int) $ban['id']])) ?>" method="post">
                        <?= $this->csrf() ?>
                        <button type="submit" class="linklike">lift</button>
                    </form>
<?php else: ?>
                    <span class="muted">—</span>
<?php endif; ?>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</section>

<?= $this->partial('partials/pagination', ['paginator' => $paginator]) ?>
