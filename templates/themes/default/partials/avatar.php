<?php
/**
 * Avatar. With no uploaded file a deterministic monogram is generated from the
 * username — no external service, no placeholder request. Size and hue come
 * from classes in the generated stylesheet rather than inline styles, which
 * the Content-Security-Policy refuses.
 *
 * @var App\Support\View $this
 * @var array<string,mixed>|null $user
 * @var int $size
 */
$username = (string) ($user['username']
    ?? $user['author_username']
    ?? $user['sender_username']
    ?? $user['recipient_username']
    ?? $user['actor_username']
    ?? 'Guest');

$path = $user['avatar_path']
    ?? $user['author_avatar']
    ?? $user['sender_avatar']
    ?? $user['recipient_avatar']
    ?? $user['actor_avatar']
    ?? null;

$sizeClass = App\Services\DynamicStyles::avatarClass((int) $size);
?>
<?php if (is_string($path) && $path !== ''): ?>
<img class="avatar <?= $this->e($sizeClass) ?>" src="<?= $this->e($path) ?>"
     width="<?= (int) $size ?>" height="<?= (int) $size ?>"
     alt="<?= $this->e($username) ?>'s avatar" loading="lazy">
<?php else: ?>
<span class="avatar avatar-generated <?= $this->e($sizeClass) ?> <?= $this->e(App\Services\DynamicStyles::hueClass($username)) ?>" aria-hidden="true"><?= $this->e(App\Support\Str::initials($username)) ?></span>
<?php endif; ?>
