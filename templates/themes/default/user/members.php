<?php
/**
 * @var App\Support\View $this
 * @var App\Support\Paginator $paginator
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Members</h1>
        <p class="page-subtitle"><?= $this->number($paginator->total()) ?> registered account(s)</p>
    </div>
</header>

<section class="panel">
    <form class="filter-bar" action="<?= $this->e($this->formAction('members')) ?>" method="get">
        <?= $this->routeField('members') ?>
        <div class="field field-inline">
            <label class="field-label" for="q">Search</label>
            <input class="field-input" type="search" id="q" name="q" value="<?= $this->e($search) ?>" maxlength="64" placeholder="username">
        </div>
        <div class="field field-inline">
            <label class="field-label" for="sort">Sort by</label>
            <select class="field-input" id="sort" name="sort">
<?php foreach (['recent' => 'Newest members', 'oldest' => 'Oldest members', 'posts' => 'Most posts', 'username' => 'Username', 'active' => 'Recently active'] as $value => $label): ?>
                <option value="<?= $this->e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-accent">Apply</button>
            <a class="btn btn-quiet" href="<?= $this->e($this->route('members')) ?>">Reset</a>
        </div>
    </form>

<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'No members matched that search.']) ?>
<?php else: ?>
    <table class="board-table data-table">
        <caption class="visually-hidden">Registered members</caption>
        <thead>
            <tr>
                <th scope="col">Member</th>
                <th scope="col">Rank</th>
                <th scope="col" class="col-num">Posts</th>
                <th scope="col" class="col-num">Topics</th>
                <th scope="col">Joined</th>
                <th scope="col">Last active</th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($paginator->items() as $member): ?>
            <tr>
                <td>
                    <?= $this->username($member, ['avatar' => 24]) ?>
                </td>
                <td><?= $this->e((string) ($member['title'] ?? $member['role_name'] ?? 'Member')) ?></td>
                <td class="col-num"><?= $this->number($member['post_count']) ?></td>
                <td class="col-num"><?= $this->number($member['topic_count']) ?></td>
                <td class="mono"><?= $this->e($this->date((string) $member['created_at'], 'Y-m-d')) ?></td>
                <td class="mono"><?= $this->e($this->relative((string) $member['last_active_at'])) ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</section>

<?= $this->partial('partials/pagination', ['paginator' => $paginator]) ?>

<div class="split-panels">
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Staff</h2></header>
        <ul class="user-list">
<?php foreach ($staff as $member): ?>
            <?= $this->partial('partials/user-card', ['member' => $member, 'meta' => (string) $member['role_name']]) ?>
<?php endforeach; ?>
        </ul>
    </section>
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Top posters</h2></header>
        <ul class="user-list">
<?php foreach ($top_posters as $member): ?>
            <?= $this->partial('partials/user-card', ['member' => $member, 'meta' => $this->number($member['post_count']) . ' posts']) ?>
<?php endforeach; ?>
        </ul>
    </section>
</div>
