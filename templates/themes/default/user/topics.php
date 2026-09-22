<?php
/**
 * @var App\Support\View $this
 * @var App\Support\Paginator $paginator
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Topics by <?= $this->e((string) $profile['username']) ?></h1>
        <p class="page-subtitle"><?= $this->number($paginator->total()) ?> topic(s) visible to you</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('user.profile', ['username' => (string) $profile['username']])) ?>">Profile</a>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('user.posts', ['username' => (string) $profile['username']])) ?>">Posts</a>
    </div>
</header>

<section class="panel">
<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'No topics to show.']) ?>
<?php else: ?>
    <table class="board-table topic-table">
        <caption class="visually-hidden">Topics started by <?= $this->e((string) $profile['username']) ?></caption>
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
            <?= $this->partial('partials/topic-row', ['topic' => $topic, 'show_forum' => true]) ?>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</section>

<?= $this->partial('partials/pagination', ['paginator' => $paginator]) ?>
