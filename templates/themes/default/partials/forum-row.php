<?php
/**
 * A single forum line in the index table.
 *
 * @var App\Support\View $this
 * @var array<string,mixed> $forum
 * @var bool $is_child
 */
$isChild = $is_child ?? false;
$canRead = (bool) ($forum['can_read'] ?? true);
?>
<tr class="forum-row<?= $isChild ? ' forum-row-child' : '' ?>">
    <td class="col-icon">
        <span class="forum-icon" aria-hidden="true"><?= $this->e((string) ($forum['icon'] ?? '#')) ?></span>
    </td>
    <td class="col-forum">
        <a class="forum-name" href="<?= $this->e($this->route('forum.show', ['slug' => (string) $forum['slug']])) ?>">
            <?= $this->e((string) $forum['name']) ?>
        </a>
<?php if ((int) ($forum['is_locked'] ?? 0) === 1): ?>
        <span class="tag tag-muted">locked</span>
<?php endif; ?>
<?php if ((int) ($forum['is_visible'] ?? 1) === 0): ?>
        <span class="tag tag-warn">hidden</span>
<?php endif; ?>
<?php if (!$canRead): ?>
        <span class="tag tag-muted">restricted</span>
<?php endif; ?>
<?php if (($forum['description'] ?? '') !== ''): ?>
        <p class="forum-desc"><?= $this->e((string) $forum['description']) ?></p>
<?php endif; ?>
<?php if (!empty($forum['children'])): ?>
        <p class="subforum-list">
            <span class="subforum-label">Subforums:</span>
<?php foreach ($forum['children'] as $child): ?>
            <a href="<?= $this->e($this->route('forum.show', ['slug' => (string) $child['slug']])) ?>"><?= $this->e((string) $child['name']) ?></a>
<?php endforeach; ?>
        </p>
<?php endif; ?>
    </td>
    <td class="col-num"><span class="num"><?= $this->number($forum['topic_count'] ?? 0) ?></span><span class="num-label">topics</span></td>
    <td class="col-num"><span class="num"><?= $this->number($forum['post_count'] ?? 0) ?></span><span class="num-label">posts</span></td>
    <td class="col-last">
<?php if (($forum['last_post_at'] ?? null) !== null && ($forum['last_topic_slug'] ?? null) !== null): ?>
        <a class="last-title" href="<?= $this->e($this->route('topic.show', ['slug' => (string) $forum['last_topic_slug']])) ?>">
            <?= $this->e(App\Support\Str::limit((string) $forum['last_topic_title'], 46)) ?>
        </a>
        <span class="last-meta">
            by <?= $this->username($forum, ['fallback' => 'a removed member']) ?>
            <time datetime="<?= $this->e((string) $forum['last_post_at']) ?>" title="<?= $this->e($this->date((string) $forum['last_post_at'], 'Y-m-d H:i')) ?>"><?= $this->e($this->relative((string) $forum['last_post_at'])) ?></time>
        </span>
<?php else: ?>
        <span class="muted">No posts yet</span>
<?php endif; ?>
    </td>
</tr>
