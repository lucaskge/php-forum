<?php /** @var App\Support\View $this */ ?>
<header class="page-head"><div><h1 class="page-title">Account settings</h1></div></header>
<?= $this->partial('partials/settings-nav') ?>

<section class="panel">
    <header class="panel-head">
        <h2 class="panel-title">Bookmarked topics</h2>
        <span class="panel-meta mono"><?= $this->number($paginator->total()) ?> topic(s)</span>
    </header>
<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'You have not bookmarked any topic yet. Use “Bookmark” inside a topic.']) ?>
<?php else: ?>
    <table class="board-table topic-table">
        <caption class="visually-hidden">Topics you bookmarked</caption>
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
