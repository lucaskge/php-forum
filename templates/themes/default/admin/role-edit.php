<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed>|null $role
 * @var array<string,array<int,array<string,mixed>>> $permissions
 * @var array<int,string> $granted
 */
$old = $this->shared('old_input', []);
$isNew = $role === null;
$action = $isNew ? $this->route('admin.role.store') : $this->route('admin.role.update', ['id' => (int) $role['id']]);
?>
<header class="page-head">
    <div>
        <h1 class="page-title"><?= $isNew ? 'New role' : 'Edit role' ?></h1>
<?php if (!$isNew): ?>
        <p class="page-subtitle mono"><?= $this->e((string) $role['slug']) ?> · <?= $this->number($member_count ?? 0) ?> member(s)</p>
<?php endif; ?>
    </div>
    <div class="page-actions"><a class="btn btn-quiet" href="<?= $this->e($this->route('admin.roles')) ?>">Back to roles</a></div>
</header>

<form class="stacked-form" action="<?= $this->e($action) ?>" method="post">
    <?= $this->csrf() ?>

    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Definition</h2></header>
        <div class="panel-form">
            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="name">Name</label>
                    <input class="field-input" type="text" id="name" name="name" maxlength="64" required
                           value="<?= $this->e((string) ($old['name'] ?? $role['name'] ?? '')) ?>">
                    <?= $this->partial('partials/field-error', ['field' => 'name']) ?>
                </div>
<?php if ($isNew): ?>
                <div class="field">
                    <label class="field-label" for="slug">Identifier</label>
                    <input class="field-input" type="text" id="slug" name="slug" maxlength="48"
                           value="<?= $this->e((string) ($old['slug'] ?? '')) ?>">
                    <p class="field-hint">Lowercase, dashes. Left empty it is derived from the name.</p>
                    <?= $this->partial('partials/field-error', ['field' => 'slug']) ?>
                </div>
<?php endif; ?>
            </div>

            <div class="form-grid form-grid-3">
<?php $colour = (string) ($old['colour'] ?? $role['colour'] ?? '#8fa3b8'); ?>
                <div class="field">
                    <label class="field-label" for="colour">Colour</label>
                    <div class="colour-field">
                        <input class="colour-swatch" type="color" id="colour" name="colour" value="<?= $this->e($colour) ?>">
                        <output class="colour-preview" for="colour">
<?php if ($role !== null): ?>
                            <span class="role-chip <?= $this->e($this->roleClass($role['id'])) ?>">
                                <?= $this->e((string) ($old['name'] ?? $role['name'] ?? 'Role name')) ?>
                            </span>
<?php endif; ?>
                            <code class="mono"><?= $this->e($colour) ?></code>
                        </output>
                    </div>
                    <p class="field-hint">How the member's name is coloured next to their posts and across the board.</p>
                </div>
                <div class="field">
                    <label class="field-label" for="priority">Priority</label>
                    <input class="field-input" type="number" id="priority" name="priority" min="0" max="65535"
                           value="<?= (int) ($old['priority'] ?? $role['priority'] ?? 10) ?>">
                    <p class="field-hint">Higher priority roles are listed first.</p>
                </div>
                <div class="field">
                    <label class="field-label" for="description">Description</label>
                    <input class="field-input" type="text" id="description" name="description" maxlength="255"
                           value="<?= $this->e((string) ($old['description'] ?? $role['description'] ?? '')) ?>">
                </div>
            </div>

            <fieldset class="field">
                <legend class="field-label">Flags</legend>

                <label class="check">
                    <input type="checkbox" name="is_staff" value="1"
                        <?= (int) ($role['is_staff'] ?? 0) === 1 ? 'checked' : '' ?>
                        <?= $isNew || (int) $role['is_system'] === 0 ? '' : 'disabled' ?>>
                    <span>
                        <strong>Staff role</strong> — members holding it are listed in the staff panel
                        and carry a <em>staff</em> marker under their name on every post.
                    </span>
                </label>

<?php if (!$isNew): ?>
                <p class="field-hint">
                    Other flags on this role, set when the board was installed:
                    <?= (int) $role['is_default'] === 1 ? '<code>default</code> (given to new registrations)' : '' ?>
                    <?= (int) $role['is_guest'] === 1 ? '<code>guest</code> (applies to visitors who are not signed in)' : '' ?>
                    <?= (int) $role['is_system'] === 1 ? '<code>system</code> (cannot be deleted, structural flags fixed)' : '' ?>
                    <?= (int) $role['is_default'] === 0 && (int) $role['is_guest'] === 0 && (int) $role['is_system'] === 0 ? 'none' : '' ?>
                </p>
<?php endif; ?>
<?php if (!$isNew && (int) $role['is_system'] === 1): ?>
                <p class="field-hint">
                    This is a system role, so the staff flag is shown but locked — the board relies on it.
                </p>
<?php endif; ?>
            </fieldset>
        </div>
    </section>

    <section class="panel">
        <header class="panel-head">
            <h2 class="panel-title">Permissions</h2>
            <span class="panel-meta mono"><?= $this->number(count($granted)) ?> granted</span>
        </header>
<?php foreach ($permissions as $group => $items): ?>
        <div class="permission-group">
            <h3 class="permission-heading"><?= $this->e(ucfirst(str_replace('_', ' ', $group))) ?></h3>
            <div class="permission-grid">
<?php foreach ($items as $permission): ?>
                <label class="check permission">
                    <input type="checkbox" name="permissions[]" value="<?= (int) $permission['id'] ?>"
                        <?= in_array((string) $permission['slug'], $granted, true) ? 'checked' : '' ?>>
                    <span>
                        <span class="permission-name"><?= $this->e((string) $permission['name']) ?></span>
                        <code class="permission-slug"><?= $this->e((string) $permission['slug']) ?></code>
<?php if (($permission['description'] ?? '') !== ''): ?>
                        <span class="permission-desc"><?= $this->e((string) $permission['description']) ?></span>
<?php endif; ?>
                    </span>
                </label>
<?php endforeach; ?>
            </div>
        </div>
<?php endforeach; ?>
    </section>

    <div class="form-buttons">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.roles')) ?>">Cancel</a>
        <button type="submit" class="btn btn-accent"><?= $isNew ? 'Create role' : 'Save role' ?></button>
    </div>
</form>

<?php if (!$isNew && (int) $role['is_system'] === 0): ?>
<section class="panel panel-danger">
    <header class="panel-head"><h2 class="panel-title">Delete role</h2></header>
    <div class="panel-form">
        <p class="muted">Only possible when no member holds the role.</p>
        <form action="<?= $this->e($this->route('admin.role.destroy', ['id' => (int) $role['id']])) ?>" method="post">
            <?= $this->csrf() ?>
            <button type="submit" class="btn btn-danger">Delete this role</button>
        </form>
    </div>
</section>
<?php endif; ?>
