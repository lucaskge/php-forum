<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $topic
 * @var array<int,array<string,mixed>> $posts
 */
$slug = (string) $topic['slug'];
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Split topic</h1>
        <p class="page-subtitle">Pick the posts that should leave <strong><?= $this->e((string) $topic['title']) ?></strong> for a new topic.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('moderation.topic', ['slug' => $slug])) ?>">Back to panel</a>
    </div>
</header>

<section class="panel">
    <form action="<?= $this->e($this->route('moderation.topic.split.apply', ['slug' => $slug])) ?>" method="post">
        <?= $this->csrf() ?>
        <div class="panel-form">
            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="title">New subject</label>
                    <input class="field-input" type="text" id="title" name="title" maxlength="190" required>
                </div>
                <div class="field">
                    <label class="field-label" for="forum_id">Destination forum</label>
                    <select class="field-input" id="forum_id" name="forum_id" required>
<?php foreach ($move_targets as $target): ?>
                        <option value="<?= (int) $target['id'] ?>" <?= (int) $target['id'] === (int) $topic['forum_id'] ? 'selected' : '' ?>>
                            <?= $this->e((string) $target['category_name']) ?> / <?= $this->e((string) $target['name']) ?>
                        </option>
<?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <table class="board-table data-table">
            <caption class="visually-hidden">Posts to split out</caption>
            <thead><tr><th scope="col"><span class="visually-hidden">Select</span></th><th scope="col">#</th><th scope="col">Author</th><th scope="col">When</th><th scope="col">Excerpt</th></tr></thead>
            <tbody>
<?php foreach ($posts as $post): ?>
                <tr>
                    <td><input type="checkbox" name="posts[]" value="<?= (int) $post['id'] ?>" aria-label="Split post #<?= (int) $post['id'] ?>"></td>
                    <td class="mono"><?= (int) $post['id'] ?><?= (int) $post['is_first_post'] === 1 ? ' <span class="tag tag-muted">first</span>' : '' ?></td>
                    <td><?= $this->e((string) ($post['author_username'] ?? 'removed member')) ?></td>
                    <td class="mono"><?= $this->e($this->date((string) $post['created_at'], 'Y-m-d H:i')) ?></td>
                    <td><?= $this->e($this->excerpt((string) $post['content'], 110)) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>

        <div class="panel-form">
            <div class="field">
                <label class="field-label" for="reason">Reason</label>
                <input class="field-input" type="text" id="reason" name="reason" maxlength="255">
            </div>
            <p class="muted">At least one post must stay behind in the original topic.</p>
            <div class="form-buttons"><button type="submit" class="btn btn-danger">Split selected posts</button></div>
        </div>
    </form>
</section>
