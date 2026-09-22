<?php
/**
 * A single post in a topic: author column on the left, content on the right —
 * the traditional board arrangement.
 *
 * @var App\Support\View $this
 * @var array<string,mixed> $post
 * @var array<string,mixed> $topic
 * @var int $number
 * @var App\Policies\PostPolicy $post_policy
 * @var bool $can_reply
 */
$author = $post['author_username'] ?? null;
$isRemoved = ($post['deleted_at'] ?? null) !== null;
$isHidden = (int) ($post['is_hidden'] ?? 0) === 1;
$canModerate = $post_policy->moderate($post);
$currentUser = $this->shared('current_user');
?>
<article class="post<?= $isRemoved ? ' post-removed' : '' ?><?= $isHidden ? ' post-hidden' : '' ?>" id="post-<?= (int) $post['id'] ?>">
    <div class="post-author">
<?php if ($author !== null): ?>
        <a class="post-avatar" href="<?= $this->e($this->route('user.profile', ['username' => (string) $author])) ?>">
            <?= $this->avatar($post, 56) ?>
        </a>
        <span class="post-username"><?= $this->username($post) ?></span>
        <p class="post-rank"><?= $this->e((string) ($post['author_title'] ?? $post['author_role'] ?? 'Member')) ?></p>
<?php if ((int) ($post['author_is_staff'] ?? 0) === 1): ?>
        <p class="post-staff" title="Holds a role marked as staff">staff</p>
<?php endif; ?>
        <dl class="post-stats">
            <div><dt>Posts</dt><dd><?= $this->number($post['author_post_count'] ?? 0) ?></dd></div>
            <div><dt>Rep</dt><dd><?= $this->number($post['author_reputation'] ?? 0) ?></dd></div>
            <div><dt>Joined</dt><dd><?= $this->e($this->date((string) ($post['author_joined'] ?? ''), 'Y-m')) ?></dd></div>
        </dl>
<?php else: ?>
        <span class="post-avatar"><?= $this->avatar(null, 56) ?></span>
        <p class="post-username is-removed">removed member</p>
<?php endif; ?>
    </div>

    <div class="post-body">
        <header class="post-head">
            <span class="post-number">
                <a href="#post-<?= (int) $post['id'] ?>" title="Permalink to this post">#<?= (int) $number ?></a>
            </span>
            <time class="post-date" datetime="<?= $this->e((string) $post['created_at']) ?>">
                <?= $this->e($this->date((string) $post['created_at'], 'Y-m-d H:i')) ?>
            </time>
<?php if ($isHidden): ?>
            <span class="tag tag-warn">hidden from members</span>
<?php endif; ?>
<?php if ($isRemoved): ?>
            <span class="tag tag-danger">deleted</span>
<?php endif; ?>
        </header>

        <div class="post-content">
            <?= $this->content((string) $post['content']) ?>
        </div>

<?php if ((int) ($post['edit_count'] ?? 0) > 0): ?>
        <p class="post-edited">
            Last edited <?= $this->e($this->relative((string) $post['edited_at'])) ?>
<?php if (($post['editor_username'] ?? null) !== null): ?>
            by <?= $this->e((string) $post['editor_username']) ?>
<?php endif; ?>
            · <?= (int) $post['edit_count'] ?> revision(s)
<?php if ($post_policy->viewHistory($post)): ?>
            · <a href="<?= $this->e($this->route('post.history', ['id' => (int) $post['id']])) ?>">view history</a>
<?php endif; ?>
        </p>
<?php endif; ?>

<?php if (($post['author_signature'] ?? null) !== null && trim((string) $post['author_signature']) !== ''): ?>
        <div class="post-signature"><?= $this->content((string) $post['author_signature']) ?></div>
<?php endif; ?>

        <footer class="post-actions">
            <a class="post-action" href="<?= $this->e($this->route('post.permalink', ['id' => (int) $post['id']])) ?>">permalink</a>
<?php if ($can_reply): ?>
            <a class="post-action" href="<?= $this->e($this->route('topic.reply', ['slug' => (string) $topic['slug']], ['quote' => (int) $post['id']])) ?>">quote</a>
<?php endif; ?>
<?php if ($post_policy->edit($post)): ?>
            <a class="post-action" href="<?= $this->e($this->route('post.edit', ['id' => (int) $post['id']])) ?>">edit</a>
<?php endif; ?>
<?php if ($post_policy->delete($post) && !$isRemoved): ?>
            <a class="post-action post-action-danger" href="<?= $this->e($this->route('post.delete', ['id' => (int) $post['id']])) ?>">delete</a>
<?php endif; ?>
<?php if ($post_policy->report($post)): ?>
            <a class="post-action" href="<?= $this->e($this->route('post.report', ['id' => (int) $post['id']])) ?>">report</a>
<?php endif; ?>
<?php if ($canModerate): ?>
<?php if ($post_policy->hide($post)): ?>
            <form class="inline-form" action="<?= $this->e($this->route('post.visibility', ['id' => (int) $post['id']])) ?>" method="post">
                <?= $this->csrf() ?>
                <button type="submit" class="post-action post-action-mod"><?= $isHidden ? 'unhide' : 'hide' ?></button>
            </form>
<?php endif; ?>
<?php if ($isRemoved && $post_policy->restore($post)): ?>
            <form class="inline-form" action="<?= $this->e($this->route('post.restore', ['id' => (int) $post['id']])) ?>" method="post">
                <?= $this->csrf() ?>
                <button type="submit" class="post-action post-action-mod">restore</button>
            </form>
<?php endif; ?>
<?php if (($post['ip_address'] ?? null) !== null): ?>
            <span class="post-action post-action-meta mono" title="Recorded address">ip <?= $this->e((string) $post['ip_address']) ?></span>
<?php endif; ?>
<?php endif; ?>
        </footer>
    </div>
</article>
