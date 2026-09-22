<?php /** @var App\Support\View $this */ ?>
<header class="page-head">
    <div><h1 class="page-title">Roles</h1>
    <p class="page-subtitle"><?= $this->number(count($roles)) ?> role(s) · <?= $this->number($permission_count) ?> permission(s) available</p></div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.permissions')) ?>">Permission matrix</a>
        <a class="btn btn-accent" href="<?= $this->e($this->route('admin.role.create')) ?>">New role</a>
    </div>
</header>

<section class="panel">
    <table class="board-table data-table">
        <caption class="visually-hidden">Roles</caption>
        <thead><tr><th scope="col">Role</th><th scope="col">Identifier</th><th scope="col">Priority</th><th scope="col" class="col-num">Members</th><th scope="col">Flags</th><th scope="col" class="col-actions">Actions</th></tr></thead>
        <tbody>
<?php foreach ($roles as $role): ?>
            <tr>
                <td><span class="role-chip <?= $this->e($this->roleClass($role['id'])) ?>"><?= $this->e((string) $role['name']) ?></span></td>
                <td class="mono"><?= $this->e((string) $role['slug']) ?></td>
                <td class="mono"><?= (int) $role['priority'] ?></td>
                <td class="col-num"><?= $this->number($role['member_count']) ?></td>
                <td class="mono muted">
<?= (int) $role['is_default'] === 1 ? 'default ' : '' ?><?= (int) $role['is_guest'] === 1 ? 'guest ' : '' ?><?= (int) $role['is_staff'] === 1 ? 'staff ' : '' ?><?= (int) $role['is_system'] === 1 ? 'system' : '' ?>
                </td>
                <td class="col-actions">
                    <a class="linklike" href="<?= $this->e($this->route('admin.role.edit', ['id' => (int) $role['id']])) ?>">edit</a>
                </td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</section>

<p class="page-note muted">
    Permissions are granted per role and combined when a member holds several. System roles cannot be deleted because the
    board relies on them: the guest role defines what visitors can do, and the administrator role holds the <code>*</code>
    wildcard.
</p>
