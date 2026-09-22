<?php
/**
 * Forum listing.
 *
 * @var App\Support\View $this
 * @var array<string,mixed> $forum
 * @var App\Support\Paginator $paginator
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title"><?= $this->e((string) $forum['name']) ?></h1>
<?php if (($forum['description'] ?? '') !== ''): ?>
        <p class="page-subtitle"><?= $this->e((string) $forum['description']) ?></p>
<?php endif; ?>
    </div>
    <div class="page-actions">
<?php if ($can_create_topic): ?>
        <a class="btn btn-accent" href="<?= $this->e($this->route('topic.create', ['forum' => (string) $forum['slug']])) ?>">New topic</a>
<?php elseif ($this->shared('current_user') === null): ?>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('auth.login.show')) ?>">Sign in to post</a>
<?php elseif ((int) $forum['is_locked'] === 1): ?>
        <span class="btn btn-disabled" aria-disabled="true">Forum locked</span>
<?php endif; ?>
    </div>
</header>

<?php if ($children !== []): ?>
<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Subforums</h2></header>
    <table class="board-table forum-table">
        <caption class="visually-hidden">Subforums of <?= $this->e((string) $forum['name']) ?></caption>
        <thead>
            <tr>
                <th scope="col" class="col-icon"><span class="visually-hidden">Status</span></th>
                <th scope="col" class="col-forum">Forum</th>
                <th scope="col" class="col-num">Topics</th>
                <th scope="col" class="col-num">Posts</th>
                <th scope="col" class="col-last">Last post</th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($children as $child): ?>
            <?= $this->partial('partials/forum-row', ['forum' => $child, 'is_child' => true]) ?>
<?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<section class="panel">
    <header class="panel-head">
        <h2 class="panel-title">Topics</h2>
        <span class="panel-meta mono"><?= $this->number($paginator->total()) ?> total</span>
    </header>
<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', [
        'message' => 'No topics here yet.',
        'action_url' => $can_create_topic ? $this->route('topic.create', ['forum' => (string) $forum['slug']]) : null,
        'action_label' => 'Start the first topic',
    ]) ?>
<?php else: ?>
    <table class="board-table topic-table">
        <caption class="visually-hidden">Topics in <?= $this->e((string) $forum['name']) ?></caption>
        <thead>
            <tr>
                <th scope="col" class="col-flag"><span class="visually-hidden">Flags</span></th>
                <th scope="col" class="col-topic">Topic</th>
                <th scope="col" class="col-num">Replies</th>
                <th scope="col" class="col-num">Views</th>
                <th scope="col" class="col-last">Last post</th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($paginator->items() as $topic): ?>
            <?= $this->partial('partials/topic-row', ['topic' => $topic, 'show_forum' => (int) $topic['forum_id'] !== (int) $forum['id']]) ?>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</section>

<?= $this->partial('partials/pagination', ['paginator' => $paginator, 'label' => 'Topic pages']) ?>

<footer class="page-foot">
    <p class="legend mono">
        <span class="flag flag-pin">PIN</span> pinned ·
        <span class="flag flag-lock">LCK</span> locked
<?php if ($can_moderate): ?>
        · you moderate this forum: open a topic to reach its moderation panel
<?php endif; ?>
    </p>
</footer>
