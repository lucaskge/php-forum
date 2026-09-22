<?php
/**
 * Read-only matrix of every permission against every role.
 *
 * @var App\Support\View $this
 * @var array<int,array<string,mixed>> $roles
 * @var array<string,array<int,array<string,mixed>>> $permissions
 * @var array<int,array<string,int>> $matrix
 */
?>
<header class="page-head">
    <div><h1 class="page-title">Permission matrix</h1>
    <p class="page-subtitle">Read-only overview. Edit the grants on each role's own page.</p></div>
    <div class="page-actions"><a class="btn btn-quiet" href="<?= $this->e($this->route('admin.roles')) ?>">Roles</a></div>
</header>

<section class="panel">
    <div class="table-scroll">
        <table class="board-table data-table matrix-table">
            <caption class="visually-hidden">Permissions by role</caption>
            <thead>
                <tr>
                    <th scope="col">Permission</th>
<?php foreach ($roles as $role): ?>
                    <th scope="col" class="matrix-role">
                        <a href="<?= $this->e($this->route('admin.role.edit', ['id' => (int) $role['id']])) ?>"><?= $this->e((string) $role['name']) ?></a>
                    </th>
<?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
<?php foreach ($permissions as $group => $items): ?>
                <tr class="matrix-group">
                    <th scope="rowgroup" colspan="<?= count($roles) + 1 ?>"><?= $this->e(ucfirst(str_replace('_', ' ', $group))) ?></th>
                </tr>
<?php foreach ($items as $permission): ?>
                <tr>
                    <th scope="row">
                        <span class="permission-name"><?= $this->e((string) $permission['name']) ?></span>
                        <code class="permission-slug"><?= $this->e((string) $permission['slug']) ?></code>
                    </th>
<?php foreach ($roles as $role): ?>
<?php
    $granted = isset($matrix[(int) $role['id']][(string) $permission['slug']])
        || isset($matrix[(int) $role['id']]['*']);
?>
                    <td class="matrix-cell <?= $granted ? 'is-granted' : 'is-denied' ?>">
                        <span aria-label="<?= $granted ? 'granted' : 'not granted' ?>"><?= $granted ? '✓' : '·' ?></span>
                    </td>
<?php endforeach; ?>
                </tr>
<?php endforeach; ?>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
