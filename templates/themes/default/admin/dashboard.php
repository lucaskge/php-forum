<?php
/** @var App\Support\View $this */
$max = max(1, max(array_map(static fn (array $row): int => $row['count'], $posts_per_day ?: [['count' => 1]])));
?>
<header class="page-head">
    <div><h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle mono">
        <?= $this->e((string) $system['app_env']) ?> · php <?= $this->e((string) $system['php']) ?> ·
        theme <?= $this->e((string) $system['theme']) ?> · chat transport <?= $this->e((string) $system['chat_transport']) ?>
    </p></div>
</header>

<div class="metric-row">
    <a class="metric" href="<?= $this->e($this->route('admin.users')) ?>">
        <span class="metric-value"><?= $this->number($totals['users']) ?></span>
        <span class="metric-label">members</span>
        <span class="metric-delta">+<?= $this->number($recent['users_week']) ?> this week</span>
    </a>
    <a class="metric" href="<?= $this->e($this->route('admin.topics')) ?>">
        <span class="metric-value"><?= $this->number($totals['topics']) ?></span>
        <span class="metric-label">topics</span>
        <span class="metric-delta">+<?= $this->number($recent['topics_week']) ?> this week</span>
    </a>
    <a class="metric" href="<?= $this->e($this->route('admin.posts')) ?>">
        <span class="metric-value"><?= $this->number($totals['posts']) ?></span>
        <span class="metric-label">posts</span>
        <span class="metric-delta">+<?= $this->number($recent['posts_week']) ?> this week</span>
    </a>
    <a class="metric<?= $pending_reports > 0 ? ' metric-alert' : '' ?>" href="<?= $this->e($this->route('moderation.reports')) ?>">
        <span class="metric-value"><?= $this->number($pending_reports) ?></span>
        <span class="metric-label">pending reports</span>
    </a>
    <span class="metric">
        <span class="metric-value"><?= $this->number(count($active_users)) ?></span>
        <span class="metric-label">members online</span>
        <span class="metric-delta"><?= $this->number($guest_count) ?> guests</span>
    </span>
    <a class="metric" href="<?= $this->e($this->route('admin.bans')) ?>">
        <span class="metric-value"><?= $this->number($active_bans) ?></span>
        <span class="metric-label">active bans</span>
        <span class="metric-delta"><?= $this->number($suspended) ?> suspended</span>
    </a>
</div>

<div class="split-panels split-panels-wide">
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Posts per day</h2><span class="panel-meta mono">last 14 days</span></header>
        <div class="panel-inset">
<?php if ($posts_per_day === []): ?>
            <p class="muted">No posts in this period.</p>
<?php else: ?>
            <table class="bar-table">
                <caption class="visually-hidden">Daily post volume</caption>
                <tbody>
<?php foreach ($posts_per_day as $row): ?>
                    <tr>
                        <th scope="row" class="mono"><?= $this->e($row['day']) ?></th>
                        <td>
                            <span class="bar <?= $this->e(App\Services\DynamicStyles::barClass($row['count'] / $max)) ?>"></span>
                        </td>
                        <td class="mono bar-value"><?= $this->number($row['count']) ?></td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
<?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Busiest forums</h2></header>
        <table class="board-table data-table">
            <caption class="visually-hidden">Busiest forums</caption>
            <thead><tr><th scope="col">Forum</th><th scope="col" class="col-num">Topics</th><th scope="col" class="col-num">Posts</th></tr></thead>
            <tbody>
<?php foreach ($busiest_forums as $forum): ?>
                <tr>
                    <td><a href="<?= $this->e($this->route('forum.show', ['slug' => (string) $forum['slug']])) ?>"><?= $this->e((string) $forum['name']) ?></a></td>
                    <td class="col-num"><?= $this->number($forum['topic_count']) ?></td>
                    <td class="col-num"><?= $this->number($forum['post_count']) ?></td>
                </tr>
<?php endforeach; ?>
<?php if ($busiest_forums === []): ?>
                <tr><td colspan="3" class="muted">No forums yet.</td></tr>
<?php endif; ?>
            </tbody>
        </table>
    </section>
</div>

<div class="split-panels split-panels-wide">
    <section class="panel">
        <header class="panel-head">
            <h2 class="panel-title">Recent moderation</h2>
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

    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Newest members</h2></header>
        <ul class="user-list">
<?php foreach ($newest_members as $member): ?>
            <?= $this->partial('partials/user-card', ['member' => $member, 'meta' => $this->date((string) $member['created_at'], 'Y-m-d')]) ?>
<?php endforeach; ?>
        </ul>
        <p class="panel-note mono">
            today: +<?= $this->number($recent['users_day']) ?> members,
            +<?= $this->number($recent['topics_day']) ?> topics,
            +<?= $this->number($recent['posts_day']) ?> posts
        </p>
    </section>
</div>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">System</h2>
    <a class="panel-action" href="<?= $this->e($this->route('admin.system')) ?>">details</a></header>
    <dl class="stat-list stat-list-wide">
        <div><dt>PHP</dt><dd class="mono"><?= $this->e((string) $system['php']) ?></dd></div>
        <div><dt>Server</dt><dd class="mono"><?= $this->e((string) $system['server']) ?></dd></div>
        <div><dt>Environment</dt><dd class="mono"><?= $this->e((string) $system['app_env']) ?></dd></div>
        <div><dt>Debug</dt><dd class="mono"><?= $this->e((string) $system['debug']) ?></dd></div>
        <div><dt>Private messages</dt><dd><?= $this->number($totals['messages']) ?></dd></div>
        <div><dt>Chat messages</dt><dd><?= $this->number($totals['chat_messages']) ?></dd></div>
    </dl>
</section>
