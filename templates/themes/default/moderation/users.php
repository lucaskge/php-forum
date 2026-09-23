<?php
/**
 * @var App\Support\View $this
 * @var App\Support\Paginator $paginator
 */
?>
<header class="page-head">
    <div><h1 class="page-title">Members</h1>
    <p class="page-subtitle"><?= $this->number($paginator->total()) ?> account(s)</p></div>
</header>

<section class="panel">
    <form class="filter-bar" action="<?= $this->e($this->formAction('moderation.users')) ?>" method="get">
        <?= $this->routeField('moderation.users') ?>
        <div class="field field-inline">
            <label class="field-label" for="q">Search</label>
            <input class="field-input" type="search" id="q" name="q" value="<?= $this->e($search) ?>" maxlength="64">
        </div>
        <div class="field field-inline">
            <label class="field-label" for="status">Status</label>
            <select class="field-input" id="status" name="status">
                <option value="">Any</option>
<?php foreach (App\Models\UserStatus::values() as $value): ?>
                <option value="<?= $this->e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= $this->e(ucfirst($value)) ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="field field-inline">
            <label class="field-label" for="sort">Sort</label>
            <select class="field-input" id="sort" name="sort">
<?php foreach (['recent' => 'Newest', 'active' => 'Recently active', 'posts' => 'Most posts', 'username' => 'Username'] as $value => $label): ?>
                <option value="<?= $this->e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-accent">Apply</button>
            <a class="btn btn-quiet" href="<?= $this->e($this->route('moderation.users')) ?>">Reset</a>
        </div>
    </form>

<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'No accounts matched.']) ?>
<?php else: ?>
    <table class="board-table data-table">
        <caption class="visually-hidden">Members</caption>
        <thead><tr><th scope="col">Member</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col" class="col-num">Posts</th><th scope="col">Joined</th><th scope="col">Last active</th></tr></thead>
        <tbody>
<?php foreach ($paginator->items() as $member): ?>
            <tr>
                <td>
                    <a class="member-link <?= $this->e($this->roleClass($member['primary_role_id'] ?? null)) ?>"
                       href="<?= $this->e($this->route('moderation.user', ['username' => (string) $member['username']])) ?>">
                        <?= $this->avatar($member, 22) ?>
                        <span><?= $this->e((string) $member['username']) ?></span>
                    </a>
                </td>
                <td><?= $this->e((string) ($member['role_name'] ?? '—')) ?></td>
                <td><span class="status-badge status-<?= $this->e((string) $member['status']) ?>"><?= $this->e((string) $member['status']) ?></span></td>
                <td class="col-num"><?= $this->number($member['post_count']) ?></td>
                <td class="mono"><?= $this->e($this->date((string) $member['created_at'], 'Y-m-d')) ?></td>
                <td class="mono"><?= $this->e($this->relative((string) $member['last_active_at'])) ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</section>

<?= $this->partial('partials/pagination', ['paginator' => $paginator]) ?>
