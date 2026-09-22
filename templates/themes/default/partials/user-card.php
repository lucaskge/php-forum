<?php
/**
 * Compact member card used in listings and sidebars.
 *
 * @var App\Support\View $this
 * @var array<string,mixed> $member
 * @var string|null $meta
 */
?>
<li class="user-card">
    <?= $this->username($member, ['avatar' => 28]) ?>
<?php if (($meta ?? null) !== null): ?>
    <span class="user-card-meta"><?= $this->e((string) $meta) ?></span>
<?php endif; ?>
</li>
