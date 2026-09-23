<?php
/**
 * Shown on every page while the viewer's own account is restricted.
 *
 * A suspension is not a secret kept from the person serving it. Without this,
 * a suspended member browses normally and only discovers the restriction by
 * finding that replying no longer works — which reads as a broken board rather
 * than a moderator decision.
 *
 * @var App\Support\View $this
 */
$restriction = $this->shared('account_restriction');

if (!is_array($restriction)) {
    return;
}

$isBan = (string) $restriction['type'] === 'ban';
?>
<div class="alert alert-error account-restriction" role="status">
    <span class="alert-tag">
        <?= $this->e($isBan ? 'banned' : 'suspended') ?>
    </span>
    <span class="alert-body">
        Your account is <?= $isBan ? 'banned' : 'suspended' ?>, so you can read the board but not
        post, reply, send messages or use chat.
        <strong><?= $this->e((string) $restriction['reason']) ?></strong>
<?php if ($restriction['expires_at'] !== null): ?>
        It lifts on <?= $this->e($this->date((string) $restriction['expires_at'], 'Y-m-d H:i')) ?>
        (<?= $this->e($this->relative((string) $restriction['expires_at'])) ?>).
<?php endif; ?>
        <a href="<?= $this->e($this->route('settings.record')) ?>">See your record</a>.
    </span>
</div>
