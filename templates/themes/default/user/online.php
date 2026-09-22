<?php
/**
 * @var App\Support\View $this
 * @var array<int,array<string,mixed>> $online_users
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Who is online</h1>
        <p class="page-subtitle mono">
            <?= $this->number(count($online_users)) ?> member(s) ·
            <?= $this->number($guest_count) ?> guest(s) ·
            <?= $this->number($bot_count) ?> crawler(s) ·
            window: last <?= (int) $window_minutes ?> minutes
        </p>
    </div>
</header>

<section class="panel">
<?php if ($online_users === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'No members are online right now.']) ?>
<?php else: ?>
    <table class="board-table data-table">
        <caption class="visually-hidden">Members currently online</caption>
        <thead>
            <tr>
                <th scope="col">Member</th>
                <th scope="col">Rank</th>
                <th scope="col">Last seen</th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($online_users as $member): ?>
<?php $hidden = (int) ($member['show_online'] ?? 1) === 0; ?>
<?php if ($hidden && !$this->shared('is_staff')) { continue; } ?>
            <tr>
                <td>
                    <?= $this->username($member, ['avatar' => 24]) ?>
<?php if ($hidden): ?>
                    <span class="tag tag-muted">hidden</span>
<?php endif; ?>
                </td>
                <td><?= $this->e((string) ($member['role_name'] ?? 'Member')) ?></td>
                <td class="mono"><?= $this->e($this->relative((string) $member['last_activity'])) ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</section>
