<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $topic
 */
$slug = (string) $topic['slug'];
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Merge topic</h1>
        <p class="page-subtitle">
            Every post of <strong><?= $this->e((string) $topic['title']) ?></strong> moves into the topic you pick;
            this one is then removed.
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('moderation.topic', ['slug' => $slug])) ?>">Back to panel</a>
    </div>
</header>

<section class="panel">
    <form class="filter-bar" action="<?= $this->e($this->formAction('moderation.topic.merge', ['slug' => $slug])) ?>" method="get">
        <?= $this->routeField('moderation.topic.merge', ['slug' => $slug]) ?>
        <div class="field field-inline">
            <label class="field-label" for="q">Find the destination topic</label>
            <input class="field-input" type="search" id="q" name="q" value="<?= $this->e($search) ?>" maxlength="120" placeholder="part of the subject" required>
        </div>
        <div class="filter-actions"><button type="submit" class="btn btn-accent">Search</button></div>
    </form>

<?php if ($search !== '' && $candidates === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'No topic you moderate matched that search.']) ?>
<?php elseif ($candidates !== []): ?>
    <form action="<?= $this->e($this->route('moderation.topic.merge.apply', ['slug' => $slug])) ?>" method="post">
        <?= $this->csrf() ?>
        <table class="board-table data-table">
            <caption class="visually-hidden">Merge candidates</caption>
            <thead><tr><th scope="col"><span class="visually-hidden">Pick</span></th><th scope="col">Topic</th><th scope="col">Forum</th><th scope="col" class="col-num">Posts</th><th scope="col">Last post</th></tr></thead>
            <tbody>
<?php foreach ($candidates as $candidate): ?>
                <tr>
                    <td><input type="radio" name="target_id" value="<?= (int) $candidate['id'] ?>" required aria-label="Merge into <?= $this->e((string) $candidate['title']) ?>"></td>
                    <td><a href="<?= $this->e($this->route('topic.show', ['slug' => (string) $candidate['slug']])) ?>"><?= $this->e((string) $candidate['title']) ?></a></td>
                    <td><?= $this->e((string) $candidate['forum_name']) ?></td>
                    <td class="col-num"><?= $this->number($candidate['post_count']) ?></td>
                    <td class="mono"><?= $this->e($this->relative((string) $candidate['last_post_at'])) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>

        <div class="panel-form">
            <div class="field">
                <label class="field-label" for="reason">Reason</label>
                <input class="field-input" type="text" id="reason" name="reason" maxlength="255">
            </div>
            <div class="form-buttons"><button type="submit" class="btn btn-danger">Merge topics</button></div>
        </div>
    </form>
<?php endif; ?>
</section>
