<?php
/**
 * Topic view.
 *
 * @var App\Support\View $this
 * @var array<string,mixed> $topic
 * @var App\Support\Paginator $paginator
 * @var App\Policies\PostPolicy $post_policy
 * @var App\Policies\TopicPolicy $topic_policy
 */
$user = $this->shared('current_user');
$number = (int) $first_number;
?>
<header class="page-head topic-head">
    <div>
        <h1 class="page-title">
<?php if ((int) $topic['is_pinned'] === 1): ?><span class="flag flag-pin">PIN</span> <?php endif; ?>
<?php if ((int) $topic['is_locked'] === 1): ?><span class="flag flag-lock">LCK</span> <?php endif; ?>
            <?= $this->e((string) $topic['title']) ?>
        </h1>
        <p class="page-subtitle">
            Started by <?= $this->username($topic, ['fallback' => 'a removed member']) ?>
            · <?= $this->e($this->date((string) $topic['created_at'], 'Y-m-d H:i')) ?>
            · <?= $this->number($topic['post_count']) ?> post(s)
            · <?= $this->number($topic['view_count']) ?> view(s)
        </p>
    </div>
    <div class="page-actions">
<?php if ($can_reply && $replying_over_lock): ?>
        <a class="btn btn-mod" href="<?= $this->e($this->route('topic.reply', ['slug' => (string) $topic['slug']])) ?>">Reply (locked)</a>
<?php elseif ($can_reply): ?>
        <a class="btn btn-accent" href="<?= $this->e($this->route('topic.reply', ['slug' => (string) $topic['slug']])) ?>">Reply</a>
<?php elseif ((int) $topic['is_locked'] === 1): ?>
        <span class="btn btn-disabled" aria-disabled="true">Topic locked</span>
<?php elseif ($user === null): ?>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('auth.login.show')) ?>">Sign in to reply</a>
<?php endif; ?>
<?php if ($can_moderate): ?>
        <a class="btn btn-mod" href="<?= $this->e($this->route('moderation.topic', ['slug' => (string) $topic['slug']])) ?>">Moderate</a>
<?php endif; ?>
    </div>
</header>

<?php if ($user !== null): ?>
<div class="topic-toolbar">
    <form class="inline-form" action="<?= $this->e($this->route('topic.subscribe', ['slug' => (string) $topic['slug']])) ?>" method="post">
        <?= $this->csrf() ?>
        <button type="submit" class="btn btn-small"><?= $is_subscribed ? 'Unsubscribe' : 'Subscribe' ?></button>
    </form>
    <form class="inline-form" action="<?= $this->e($this->route('topic.bookmark', ['slug' => (string) $topic['slug']])) ?>" method="post">
        <?= $this->csrf() ?>
        <button type="submit" class="btn btn-small"><?= $is_bookmarked ? 'Remove bookmark' : 'Bookmark' ?></button>
    </form>
<?php if ($topic_policy->edit($topic)): ?>
    <a class="btn btn-small" href="<?= $this->e($this->route('topic.edit', ['slug' => (string) $topic['slug']])) ?>">Edit subject</a>
<?php endif; ?>
<?php if ($topic_policy->delete($topic) && $topic['deleted_at'] === null): ?>
    <a class="btn btn-small btn-danger-quiet" href="<?= $this->e($this->route('topic.delete', ['slug' => (string) $topic['slug']])) ?>">Delete topic</a>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if ($topic['deleted_at'] !== null): ?>
<div class="alert alert-warning">
    <span class="alert-tag">warn</span>
    <span class="alert-body">This topic is deleted. Only moderators can see it.</span>
</div>
<?php endif; ?>

<?php if ($replying_over_lock): ?>
<div class="alert alert-warning">
    <span class="alert-tag">warn</span>
    <span class="alert-body">
<?php if ((int) $topic['is_locked'] === 1): ?>
        This topic is locked. Members cannot reply — you can, because you moderate this forum.
<?php elseif ((int) $topic['is_archived'] === 1): ?>
        This topic is archived. Members cannot reply — you can, because you moderate this forum.
<?php else: ?>
        This forum is locked. Members cannot reply — you can, because you moderate it.
<?php endif; ?>
    </span>
</div>
<?php endif; ?>

<?= $this->partial('partials/pagination', ['paginator' => $paginator, 'label' => 'Post pages']) ?>

<div class="post-stream">
<?php foreach ($paginator->items() as $post): ?>
    <?= $this->partial('partials/post', [
        'post' => $post,
        'topic' => $topic,
        'number' => $number++,
        'post_policy' => $post_policy,
        'can_reply' => $can_reply,
    ]) ?>
<?php endforeach; ?>
</div>

<?= $this->partial('partials/pagination', ['paginator' => $paginator, 'label' => 'Post pages, bottom']) ?>

<?php if ($quick_reply): ?>
<section class="panel quick-reply<?= $replying_over_lock ? ' panel-mod' : '' ?>" id="quick-reply">
    <header class="panel-head">
        <h2 class="panel-title"><?= $replying_over_lock ? 'Quick reply — over the lock' : 'Quick reply' ?></h2>
<?php if ($replying_over_lock): ?>
        <span class="panel-meta mono">moderator</span>
<?php endif; ?>
    </header>
    <form class="stacked-form" action="<?= $this->e($this->route('topic.reply.store', ['slug' => (string) $topic['slug']])) ?>" method="post">
        <?= $this->csrf() ?>
        <?= $this->partial('partials/editor', ['name' => 'content', 'value' => '', 'label' => 'Your reply', 'rows' => 8]) ?>
        <div class="form-row form-row-split">
            <label class="check">
                <input type="checkbox" name="subscribe" value="1" checked>
                <span>Notify me about new replies</span>
            </label>
            <div class="form-buttons">
                <a class="btn btn-quiet" href="<?= $this->e($this->route('topic.reply', ['slug' => (string) $topic['slug']])) ?>">Full editor</a>
                <button type="submit" class="btn btn-accent">Post reply</button>
            </div>
        </div>
    </form>
</section>
<?php elseif ((int) $topic['is_locked'] === 1): ?>
<section class="panel panel-notice">
    <div class="panel-inset"><p>This topic is locked. New replies are not accepted.</p></div>
</section>
<?php endif; ?>

<footer class="page-foot">
    <p class="mono muted">
        <a href="<?= $this->e($this->route('forum.show', ['slug' => (string) $topic['forum_slug']])) ?>">&larr; back to <?= $this->e((string) $topic['forum_name']) ?></a>
    </p>
</footer>
