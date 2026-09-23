<?php
/**
 * The member's own moderation record.
 *
 * @var App\Support\View $this
 * @var array<int,array<string,mixed>> $warnings
 * @var array<string,mixed>|null $restriction
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Your record</h1>
        <p class="page-subtitle">
            Everything the moderators have recorded about your account, and what it means.
            Only you and the staff can see this page.
        </p>
    </div>
</header>
<?= $this->partial('partials/settings-nav') ?>

<?php if ($restriction !== null): ?>
<section class="panel panel-danger">
    <header class="panel-head">
        <h2 class="panel-title">
            Your account is <?= $this->e((string) $restriction['type'] === 'ban' ? 'banned' : 'suspended') ?>
        </h2>
    </header>
    <div class="panel-inset">
        <p><strong>Reason given:</strong> <?= $this->e((string) $restriction['reason']) ?></p>
        <p class="muted">
<?php if ($restriction['expires_at'] !== null): ?>
            It lifts by itself on <?= $this->e($this->date((string) $restriction['expires_at'], 'Y-m-d H:i')) ?>
            (<?= $this->e($this->relative((string) $restriction['expires_at'])) ?>). Nothing is required of you.
<?php else: ?>
            No end date was set.
<?php endif; ?>
        </p>
        <p class="muted">
            While it lasts you can read the board as usual, but not post, reply, send messages or use
            chat. If you think this is a mistake, reply to the moderator who issued it by private
            message rather than starting a topic about it.
        </p>
    </div>
</section>
<?php else: ?>
<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Standing</h2></header>
    <div class="panel-inset">
        <p>Your account is in good standing. Nothing restricts what you can do on the board.</p>
    </div>
</section>
<?php endif; ?>

<section class="panel">
    <header class="panel-head">
        <h2 class="panel-title">Warnings</h2>
        <span class="panel-meta mono"><?= $this->number($points) ?> active point(s)</span>
    </header>
<?php if ($warnings === []): ?>
    <div class="panel-inset muted">You have never been warned.</div>
<?php else: ?>
    <table class="board-table data-table">
        <caption class="visually-hidden">Warnings on your account</caption>
        <thead>
            <tr>
                <th scope="col">Reason</th>
                <th scope="col">Details</th>
                <th scope="col" class="col-num">Points</th>
                <th scope="col">Issued</th>
                <th scope="col">Expires</th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($warnings as $warning): ?>
            <tr>
                <td><?= $this->e((string) $warning['reason']) ?></td>
                <td><?= $this->e((string) ($warning['details'] ?? '—')) ?></td>
                <td class="col-num"><?= (int) $warning['points'] ?></td>
                <td class="mono"><?= $this->e($this->date((string) $warning['created_at'], 'Y-m-d')) ?></td>
                <td class="mono">
<?php if ($warning['expires_at'] === null): ?>
                    never
<?php elseif (App\Support\Dates::isPast((string) $warning['expires_at'])): ?>
                    expired
<?php else: ?>
                    <?= $this->e($this->date((string) $warning['expires_at'], 'Y-m-d')) ?>
<?php endif; ?>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
    <p class="panel-note muted">
        Points from an expired warning no longer count against you; the entry stays for the record.
    </p>
<?php endif; ?>
</section>

<?php if ($history !== []): ?>
<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Past restrictions</h2></header>
    <table class="board-table data-table">
        <caption class="visually-hidden">Restriction history</caption>
        <thead><tr><th scope="col">Type</th><th scope="col">Reason</th><th scope="col">From</th><th scope="col">Until</th><th scope="col">State</th></tr></thead>
        <tbody>
<?php foreach ($history as $entry): ?>
            <tr>
                <td class="mono"><?= $this->e((string) $entry['type']) ?></td>
                <td><?= $this->e((string) $entry['reason']) ?></td>
                <td class="mono"><?= $this->e($this->date((string) $entry['created_at'], 'Y-m-d')) ?></td>
                <td class="mono"><?= $entry['expires_at'] === null ? 'permanent' : $this->e($this->date((string) $entry['expires_at'], 'Y-m-d')) ?></td>
                <td><?= (int) $entry['is_active'] === 1 ? '<span class="status-badge status-pending">active</span>' : '<span class="status-badge status-resolved">lifted</span>' ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>
