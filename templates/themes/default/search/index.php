<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $criteria
 * @var App\Support\Paginator|null $paginator
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Search</h1>
        <p class="page-subtitle">Only forums your account can read are searched.</p>
    </div>
</header>

<section class="panel">
    <form class="search-form" action="<?= $this->e($this->route('search')) ?>" method="get">
        <div class="field">
            <label class="field-label" for="q">Keywords</label>
            <input class="field-input" type="search" id="q" name="q" maxlength="120" autofocus
                   value="<?= $this->e((string) $criteria['keywords']) ?>">
            <p class="field-hint">Words are matched independently; all of them must appear.</p>
        </div>

        <div class="form-grid form-grid-3">
            <div class="field">
                <label class="field-label" for="author">Author</label>
                <input class="field-input" type="text" id="author" name="author" maxlength="32"
                       value="<?= $this->e((string) $criteria['author']) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="forum">Forum</label>
                <select class="field-input" id="forum" name="forum">
                    <option value="">Every forum</option>
<?php foreach ($forums as $forum): ?>
                    <option value="<?= (int) $forum['id'] ?>" <?= (int) $criteria['forum'] === (int) $forum['id'] ? 'selected' : '' ?>>
                        <?= $this->e(($forum['parent_name'] !== null ? '— ' : '') . (string) $forum['name']) ?>
                    </option>
<?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="mode">Search in</label>
                <select class="field-input" id="mode" name="mode">
                    <option value="posts" <?= $criteria['mode'] === 'posts' ? 'selected' : '' ?>>Post contents</option>
                    <option value="topics" <?= $criteria['mode'] === 'topics' ? 'selected' : '' ?>>Topic subjects</option>
                </select>
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label class="field-label" for="from">Posted after</label>
                <input class="field-input" type="date" id="from" name="from" value="<?= $this->e((string) $criteria['from']) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="to">Posted before</label>
                <input class="field-input" type="date" id="to" name="to" value="<?= $this->e((string) $criteria['to']) ?>">
            </div>
        </div>

        <div class="form-buttons">
            <a class="btn btn-quiet" href="<?= $this->e($this->route('search')) ?>">Reset</a>
            <button type="submit" class="btn btn-accent">Search</button>
        </div>
    </form>
</section>

<?php if ($notice !== null): ?>
<div class="alert alert-warning"><span class="alert-tag">warn</span><span class="alert-body"><?= $this->e($notice) ?></span></div>
<?php endif; ?>

<?php if ($paginator !== null): ?>
<section class="panel">
    <header class="panel-head">
        <h2 class="panel-title">Results</h2>
        <span class="panel-meta mono"><?= $this->number($paginator->total()) ?> match(es)</span>
    </header>

<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'Nothing matched those criteria.']) ?>
<?php else: ?>
    <div class="result-list">
<?php foreach ($paginator->items() as $row): ?>
        <article class="result">
            <header class="result-head">
                <a class="result-title" href="<?= $this->e($this->route('post.permalink', ['id' => (int) $row['post_id']])) ?>">
                    <?= $this->e((string) $row['topic_title']) ?>
                </a>
                <span class="result-meta mono">
                    <a href="<?= $this->e($this->route('forum.show', ['slug' => (string) $row['forum_slug']])) ?>"><?= $this->e((string) $row['forum_name']) ?></a>
<?php if (($row['author_username'] ?? null) !== null): ?>
                    · <?= $this->username($row) ?>
<?php endif; ?>
                    · <?= $this->e($this->date((string) $row['created_at'], 'Y-m-d H:i')) ?>
                    · <?= $this->number($row['post_count'] ?? 0) ?> post(s)
                </span>
            </header>
            <div class="result-body"><?= $this->e($this->excerpt((string) ($row['excerpt_source'] ?? ''), 300)) ?></div>
        </article>
<?php endforeach; ?>
    </div>
<?php endif; ?>
</section>

<?= $this->partial('partials/pagination', ['paginator' => $paginator, 'label' => 'Result pages']) ?>
<?php endif; ?>
