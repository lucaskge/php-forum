<?php
/**
 * A member's name, wherever it appears.
 *
 * One partial for the whole board, so a role's colour shows up consistently:
 * in posts, topic listings, last-post columns, search results, chat, messages,
 * moderation tables and the admin panels. The colour arrives as a class from
 * the generated stylesheet — never an inline style, which the CSP refuses.
 *
 * $source may carry the name under any of the usual column aliases, with its
 * matching `*_role_id`; a missing role simply means no colour.
 *
 * @var App\Support\View $this
 * @var array<string,mixed> $source
 * @var array<string,mixed> $options  link (bool), avatar (int|null), fallback (string), staff (bool)
 */
$options = $options ?? [];

$name = null;
$roleId = null;

foreach ([
    ['username', 'primary_role_id'],
    ['author_username', 'author_role_id'],
    ['sender_username', 'sender_role_id'],
    ['recipient_username', 'recipient_role_id'],
    ['last_post_username', 'last_post_role_id'],
    ['actor_username', 'actor_role_id'],
    ['moderator_username', 'moderator_role_id'],
    ['target_username', 'target_role_id'],
    ['reporter_username', 'reporter_role_id'],
    ['reported_username', 'reported_role_id'],
] as [$nameKey, $roleKey]) {
    if (($source[$nameKey] ?? null) !== null && (string) $source[$nameKey] !== '') {
        $name = (string) $source[$nameKey];
        $roleId = $source[$roleKey] ?? ($source['role_id'] ?? null);

        break;
    }
}

$fallback = (string) ($options['fallback'] ?? 'removed member');
$link = ($options['link'] ?? true) !== false;
$avatarSize = $options['avatar'] ?? null;
$roleClass = $name === null ? '' : $this->roleClass($roleId);
$classes = trim('username ' . $roleClass . ($roleClass === '' ? ' is-plain' : ''));
?>
<?php if ($name === null): ?>
<span class="username is-missing"><?= $this->e($fallback) ?></span>
<?php elseif ($link): ?>
<a class="<?= $this->e($classes) ?>" href="<?= $this->e($this->route('user.profile', ['username' => $name])) ?>"><?php if ($avatarSize !== null): ?><?= $this->avatar($source, (int) $avatarSize) ?><?php endif; ?><span class="username-text"><?= $this->e($name) ?></span></a>
<?php else: ?>
<span class="<?= $this->e($classes) ?>"><?php if ($avatarSize !== null): ?><?= $this->avatar($source, (int) $avatarSize) ?><?php endif; ?><span class="username-text"><?= $this->e($name) ?></span></span>
<?php endif; ?>
