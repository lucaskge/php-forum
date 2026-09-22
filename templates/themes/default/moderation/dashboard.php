<?php /** @var App\Support\View $this */ ?>
<header class="page-head">
    <div>
        <h1 class="page-title">Moderation</h1>
        <p class="page-subtitle mono">you moderate <?= (int) $moderated_forums ?> forum(s)</p>
    </div>
</header>

<div class="metric-row">
    <a class="metric" href="<?= $this->e($this->route('moderation.reports', [], ['status' => 'pending'])) ?>">
        <span class="metric-value"><?= $this->number($pending_reports) ?></span>
        <span class="metric-label">pending reports</span>
    </a>
    <a class="metric" href="<?= $this->e($this->route('moderation.reports', [], ['status' => 'resolved'])) ?>">
        <span class="metric-value"><?= $this->number($resolved_reports) ?></span>
        <span class="metric-label">resolved</span>
    </a>
    <a class="metric" href="<?= $this->e($this->route('moderation.reports', [], ['status' => 'dismissed'])) ?>">
        <span class="metric-value"><?= $this->number($dismissed_reports) ?></span>
        <span class="metric-label">dismissed</span>
    </a>
    <span class="metric">
        <span class="metric-value"><?= $this->number($active_bans) ?></span>
        <span class="metric-label">active restrictions</span>
    </span>
</div>

<div class="split-panels">
    <section class="panel">
        <header class="panel-head">
            <h2 class="panel-title">Latest reports</h2>
            <a class="panel-action" href="<?= $this->e($this->route('moderation.reports')) ?>">all reports</a>
        </header>
<?php if ($recent_reports === []): ?>
        <div class="panel-inset muted">The queue is empty.</div>
<?php else: ?>
        <table class="board-table data-table">
            <caption class="visually-hidden">Latest reports</caption>
            <thead><tr><th scope="col">#</th><th scope="col">Type</th><th scope="col">Reason</th><th scope="col">Status</th><th scope="col">When</th></tr></thead>
            <tbody>
<?php foreach ($recent_reports as $report): ?>
                <tr>
                    <td><a href="<?= $this->e($this->route('moderation.report', ['id' => (int) $report['id']])) ?>">#<?= (int) $report['id'] ?></a></td>
                    <td class="mono"><?= $this->e((string) $report['content_type']) ?></td>
                    <td><?= $this->e((string) $report['reason']) ?></td>
                    <td><span class="status-badge status-<?= $this->e((string) $report['status']) ?>"><?= $this->e((string) $report['status']) ?></span></td>
                    <td class="mono"><?= $this->e($this->relative((string) $report['created_at'])) ?></td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
<?php endif; ?>
    </section>

    <section class="panel">
        <header class="panel-head">
            <h2 class="panel-title">Recent actions</h2>
            <a class="panel-action" href="<?= $this->e($this->route('moderation.log')) ?>">full log</a>
        </header>
        <ul class="log-list">
<?php foreach ($recent_actions as $action): ?>
            <li class="log-item">
                <span class="log-action mono"><?= $this->e((string) $action['action']) ?></span>
                <span class="log-summary"><?= $this->e((string) $action['summary']) ?></span>
                <span class="log-meta mono"><?= $this->username($action, ['fallback' => 'system']) ?> · <?= $this->e($this->relative((string) $action['created_at'])) ?></span>
            </li>
<?php endforeach; ?>
<?php if ($recent_actions === []): ?>
            <li class="muted">Nothing logged yet.</li>
<?php endif; ?>
        </ul>
    </section>
</div>

<div class="split-panels">
    <section class="panel">
        <header class="panel-head">
            <h2 class="panel-title">Hidden posts</h2>
            <a class="panel-action" href="<?= $this->e($this->route('moderation.queue')) ?>">content queue</a>
        </header>
        <ul class="feed-list">
<?php foreach ($hidden_posts as $post): ?>
            <li class="feed-item">
                <a class="feed-title" href="<?= $this->e($this->route('post.permalink', ['id' => (int) $post['id']])) ?>"><?= $this->e((string) $post['topic_title']) ?></a>
                <span class="feed-meta"><?= $this->e((string) ($post['author_username'] ?? 'removed member')) ?> · <?= $this->e($this->relative((string) $post['created_at'])) ?></span>
            </li>
<?php endforeach; ?>
<?php if ($hidden_posts === []): ?>
            <li class="muted">No hidden posts.</li>
<?php endif; ?>
        </ul>
    </section>

    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Deleted topics</h2></header>
        <ul class="feed-list">
<?php foreach ($deleted_topics as $topic): ?>
            <li class="feed-item">
                <a class="feed-title" href="<?= $this->e($this->route('topic.show', ['slug' => (string) $topic['slug']])) ?>"><?= $this->e((string) $topic['title']) ?></a>
                <span class="feed-meta"><?= $this->e((string) $topic['forum_name']) ?> · <?= $this->e($this->relative((string) $topic['created_at'])) ?></span>
            </li>
<?php endforeach; ?>
<?php if ($deleted_topics === []): ?>
            <li class="muted">No deleted topics.</li>
<?php endif; ?>
        </ul>
    </section>
</div>
