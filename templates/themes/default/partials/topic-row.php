<?php
/**
 * A single topic line in a forum listing.
 *
 * @var App\Support\View $this
 * @var array<string,mixed> $topic
 * @var bool $show_forum
 */
$showForum = $show_forum ?? false;
$isDeleted = ($topic['deleted_at'] ?? null) !== null;
?>
<tr class="topic-row<?= $isDeleted ? ' is-removed' : '' ?>">
    <td class="col-flag">
<?php if ((int) ($topic['is_pinned'] ?? 0) === 1): ?>
        <span class="flag flag-pin" title="Pinned">PIN</span>
<?php elseif ((int) ($topic['is_locked'] ?? 0) === 1): ?>
        <span class="flag flag-lock" title="Locked">LCK</span>
<?php else: ?>
        <span class="flag" aria-hidden="true">&mdash;</span>
<?php endif; ?>
    </td>
    <td class="col-topic">
        <a class="topic-title" href="<?= $this->e($this->route('topic.show', ['slug' => (string) $topic['slug']])) ?>">
            <?= $this->e((string) $topic['title']) ?>
        </a>
<?php if ((int) ($topic['is_hidden'] ?? 0) === 1): ?>
        <span class="tag tag-warn">hidden</span>
<?php endif; ?>
<?php if ($isDeleted): ?>
        <span class="tag tag-danger">deleted</span>
<?php endif; ?>
<?php if ((int) ($topic['is_archived'] ?? 0) === 1): ?>
        <span class="tag tag-muted">archived</span>
<?php endif; ?>
        <span class="topic-meta">
            by <?= $this->username($topic, ['fallback' => 'a removed member']) ?>
            · <time datetime="<?= $this->e((string) $topic['created_at']) ?>"><?= $this->e($this->date((string) $topic['created_at'], 'Y-m-d')) ?></time>
<?php if ($showForum && ($topic['forum_name'] ?? null) !== null): ?>
            · in <a href="<?= $this->e($this->route('forum.show', ['slug' => (string) $topic['forum_slug']])) ?>"><?= $this->e((string) $topic['forum_name']) ?></a>
<?php endif; ?>
        </span>
    </td>
    <td class="col-num"><span class="num"><?= $this->number(max(0, (int) $topic['post_count'] - 1)) ?></span><span class="num-label">replies</span></td>
    <td class="col-num"><span class="num"><?= $this->number($topic['view_count'] ?? 0) ?></span><span class="num-label">views</span></td>
    <td class="col-last">
<?php if (($topic['last_post_at'] ?? null) !== null): ?>
        <a class="last-title" href="<?= $this->e($this->route('topic.show', ['slug' => (string) $topic['slug']])) ?>">
            <?= $this->e($this->relative((string) $topic['last_post_at'])) ?>
        </a>
        <span class="last-meta">
            by <?= $this->username(['last_post_username' => $topic['last_post_username'] ?? null, 'last_post_role_id' => $topic['last_post_role_id'] ?? null], ['fallback' => 'unknown']) ?>
        </span>
<?php else: ?>
        <span class="muted">—</span>
<?php endif; ?>
    </td>
</tr>
