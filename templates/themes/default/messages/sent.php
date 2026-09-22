<?php
/**
 * @var App\Support\View $this
 * @var App\Support\Paginator $paginator
 */
?>
<header class="page-head">
    <div><h1 class="page-title">Messages</h1>
    <p class="page-subtitle"><?= $this->number($paginator->total()) ?> message(s) sent</p></div>
    <div class="page-actions"><a class="btn btn-accent" href="<?= $this->e($this->route('messages.compose')) ?>">Compose</a></div>
</header>
<?= $this->partial('partials/messages-nav', ['folder' => $folder, 'unread' => $unread]) ?>

<section class="panel">
<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'You have not sent any message yet.']) ?>
<?php else: ?>
    <table class="board-table data-table message-table">
        <caption class="visually-hidden">Sent messages</caption>
        <thead>
            <tr>
                <th scope="col">Subject</th>
                <th scope="col">To</th>
                <th scope="col">Sent</th>
                <th scope="col">Read</th>
                <th scope="col" class="col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($paginator->items() as $message): ?>
            <tr>
                <td>
                    <a class="message-subject" href="<?= $this->e($this->route('messages.show', ['id' => (int) $message['id']])) ?>">
                        <?= $this->e((string) $message['subject']) ?>
                    </a>
                    <p class="message-preview"><?= $this->e($this->excerpt((string) $message['body'], 110)) ?></p>
                </td>
                <td><?= $this->username(['recipient_username' => $message['recipient_username'] ?? null, 'recipient_role_id' => $message['recipient_role_id'] ?? null, 'recipient_avatar' => $message['recipient_avatar'] ?? null]) ?></td>
                <td class="mono"><?= $this->e($this->date((string) $message['created_at'], 'Y-m-d H:i')) ?></td>
                <td class="mono"><?= (int) $message['is_read'] === 1 ? 'yes' : 'no' ?></td>
                <td class="col-actions">
                    <form class="inline-form" action="<?= $this->e($this->route('messages.delete', ['id' => (int) $message['id']])) ?>" method="post">
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

<?= $this->partial('partials/pagination', ['paginator' => $paginator]) ?>
