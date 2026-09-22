<?php
/**
 * Per-forum access matrix.
 *
 * @var App\Support\View $this
 * @var array<string,mixed> $forum
 * @var array<int,array<string,mixed>> $roles
 * @var array<int,array<string,mixed>> $current
 */
$flags = [
    'can_view' => 'See the forum',
    'can_read' => 'Read topics',
    'can_create_topic' => 'Start topics',
    'can_reply' => 'Reply',
    'can_moderate' => 'Moderate',
];
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Forum permissions</h1>
        <p class="page-subtitle"><?= $this->e((string) $forum['name']) ?> <span class="mono muted">/ <?= $this->e((string) $forum['slug']) ?></span></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.forum.form', [], ['id' => (int) $forum['id']])) ?>">Forum settings</a>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.forums')) ?>">All forums</a>
    </div>
</header>

<section class="panel">
    <form action="<?= $this->e($this->route('admin.forum.permissions.save', ['id' => (int) $forum['id']])) ?>" method="post">
        <?= $this->csrf() ?>
        <div class="table-scroll">
            <table class="board-table data-table matrix-table">
                <caption class="visually-hidden">Role access to <?= $this->e((string) $forum['name']) ?></caption>
                <thead>
                    <tr>
                        <th scope="col">Role</th>
<?php foreach ($flags as $label): ?>
                        <th scope="col" class="matrix-role"><?= $this->e($label) ?></th>
<?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
<?php foreach ($roles as $role): ?>
<?php $roleId = (int) $role['id']; $row = $current[$roleId] ?? null; ?>
                    <tr>
                        <th scope="row">
                            <span class="role-chip <?= $this->e($this->roleClass($role['id'])) ?>"><?= $this->e((string) $role['name']) ?></span>
                            <code class="permission-slug"><?= $this->e((string) $role['slug']) ?></code>
                        </th>
<?php foreach ($flags as $flag => $label): ?>
                        <td class="matrix-cell">
                            <label class="check check-bare">
                                <input type="checkbox" name="perm[<?= $roleId ?>][<?= $this->e($flag) ?>]" value="1"
                                    <?= $row !== null && (int) $row[$flag] === 1 ? 'checked' : '' ?>>
                                <span class="visually-hidden"><?= $this->e($label) ?> for <?= $this->e((string) $role['name']) ?></span>
                            </label>
                        </td>
<?php endforeach; ?>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="panel-form">
            <p class="muted">
                A member gets a permission when <em>any</em> of their roles grants it. “See the forum” without “Read topics”
                lists the forum but keeps its contents closed — useful for teaser boards. A subforum is only reachable when
                its parent is visible too.
            </p>
            <div class="form-buttons">
                <button type="submit" class="btn btn-accent">Save permissions</button>
            </div>
        </div>
    </form>
</section>
