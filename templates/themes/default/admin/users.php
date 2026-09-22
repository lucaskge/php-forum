<?php
/**
 * @var App\Support\View $this
 * @var App\Support\Paginator $paginator
 */
?>
<header class="page-head">
    <div><h1 class="page-title">Users</h1><p class="page-subtitle"><?= $this->number($paginator->total()) ?> account(s)</p></div>
    <div class="page-actions"><a class="btn btn-quiet" href="<?= $this->e($this->route('admin.bans')) ?>">Bans</a></div>
</header>

<section class="panel">
    <form class="filter-bar" action="<?= $this->e($this->route('admin.users')) ?>" method="get">
        <div class="field field-inline">
            <label class="field-label" for="q">Search</label>
            <input class="field-input" type="search" id="q" name="q" value="<?= $this->e((string) $filters['search']) ?>" maxlength="64" placeholder="username or e-mail">
        </div>
        <div class="field field-inline">
            <label class="field-label" for="role">Role</label>
            <select class="field-input" id="role" name="role">
                <option value="">Any role</option>
<?php foreach ($roles as $role): ?>
                <option value="<?= (int) $role['id'] ?>" <?= (int) $filters['role'] === (int) $role['id'] ? 'selected' : '' ?>><?= $this->e((string) $role['name']) ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="field field-inline">
            <label class="field-label" for="status">Status</label>
            <select class="field-input" id="status" name="status">
                <option value="">Any</option>
<?php foreach (App\Models\UserStatus::values() as $value): ?>
                <option value="<?= $this->e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= $this->e(ucfirst($value)) ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-accent">Apply</button>
            <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.users')) ?>">Reset</a>
        </div>
    </form>

<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'No accounts matched.']) ?>
<?php else: ?>
    <table class="board-table data-table">
        <caption class="visually-hidden">User accounts</caption>
        <thead><tr><th scope="col">ID</th><th scope="col">Member</th><th scope="col">E-mail</th><th scope="col">Role</th><th scope="col">Status</th><th scope="col" class="col-num">Posts</th><th scope="col">Joined</th><th scope="col" class="col-actions">Actions</th></tr></thead>
        <tbody>
<?php foreach ($paginator->items() as $member): ?>
            <tr>
                <td class="mono"><?= (int) $member['id'] ?></td>
                <td>
                    <a class="member-link <?= $this->e($this->roleClass($member['primary_role_id'] ?? null)) ?>"
                       href="<?= $this->e($this->route('admin.user.edit', ['id' => (int) $member['id']])) ?>">
                        <?= $this->avatar($member, 22) ?>
                        <span><?= $this->e((string) $member['username']) ?></span>
                    </a>
                </td>
                <td class="mono muted"><?= $this->e(App\Support\Str::maskEmail((string) $member['email'])) ?></td>
                <td><?= $this->e((string) ($member['role_name'] ?? '—')) ?></td>
                <td><span class="status-badge status-<?= $this->e((string) $member['status']) ?>"><?= $this->e((string) $member['status']) ?></span></td>
                <td class="col-num"><?= $this->number($member['post_count']) ?></td>
                <td class="mono"><?= $this->e($this->date((string) $member['created_at'], 'Y-m-d')) ?></td>
                <td class="col-actions">
                    <a class="linklike" href="<?= $this->e($this->route('admin.user.edit', ['id' => (int) $member['id']])) ?>">edit</a>
                    <a class="linklike" href="<?= $this->e($this->route('moderation.user', ['username' => (string) $member['username']])) ?>">record</a>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</section>

<?= $this->partial('partials/pagination', ['paginator' => $paginator]) ?>
