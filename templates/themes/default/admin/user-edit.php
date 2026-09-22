<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $profile
 */
$old = $this->shared('old_input', []);
$userId = (int) $profile['id'];
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Edit user</h1>
        <p class="page-subtitle mono">#<?= $userId ?> · <?= $this->e((string) $profile['username']) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('user.profile', ['username' => (string) $profile['username']])) ?>">Public profile</a>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('moderation.user', ['username' => (string) $profile['username']])) ?>">Moderation record</a>
    </div>
</header>

<form class="stacked-form" action="<?= $this->e($this->route('admin.user.update', ['id' => $userId])) ?>" method="post">
    <?= $this->csrf() ?>

    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Account</h2></header>
        <div class="panel-form">
            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="username">Username</label>
                    <input class="field-input" type="text" id="username" name="username" maxlength="32" required
                           value="<?= $this->e((string) ($old['username'] ?? $profile['username'])) ?>">
                    <?= $this->partial('partials/field-error', ['field' => 'username']) ?>
                </div>
                <div class="field">
                    <label class="field-label" for="email">E-mail</label>
                    <input class="field-input" type="email" id="email" name="email" maxlength="190" required
                           value="<?= $this->e((string) ($old['email'] ?? $profile['email'])) ?>">
                    <?= $this->partial('partials/field-error', ['field' => 'email']) ?>
                </div>
            </div>
            <div class="form-grid form-grid-3">
                <div class="field">
                    <label class="field-label" for="status">Status</label>
                    <select class="field-input" id="status" name="status">
<?php foreach (App\Models\UserStatus::values() as $value): ?>
                        <option value="<?= $this->e($value) ?>" <?= (string) $profile['status'] === $value ? 'selected' : '' ?>><?= $this->e(ucfirst($value)) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="title">Custom rank title</label>
                    <input class="field-input" type="text" id="title" name="title" maxlength="64"
                           value="<?= $this->e((string) ($old['title'] ?? $profile['title'] ?? '')) ?>">
                </div>
                <div class="field">
                    <label class="field-label" for="reputation">Reputation</label>
                    <input class="field-input" type="number" id="reputation" name="reputation" value="<?= (int) $profile['reputation'] ?>">
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="timezone">Timezone</label>
                    <select class="field-input" id="timezone" name="timezone">
<?php foreach ($timezones as $value => $label): ?>
                        <option value="<?= $this->e($value) ?>" <?= (string) $profile['timezone'] === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
<?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label" for="location">Location</label>
                    <input class="field-input" type="text" id="location" name="location" maxlength="64"
                           value="<?= $this->e((string) ($profile['location'] ?? '')) ?>">
                </div>
            </div>
            <div class="field">
                <label class="field-label" for="website">Website</label>
                <input class="field-input" type="url" id="website" name="website" maxlength="190"
                       value="<?= $this->e((string) ($profile['website'] ?? '')) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="bio">About</label>
                <textarea class="field-input" id="bio" name="bio" rows="4" maxlength="2000"><?= $this->e((string) ($profile['bio'] ?? '')) ?></textarea>
            </div>
            <div class="field">
                <label class="field-label" for="signature">Signature</label>
                <textarea class="field-input" id="signature" name="signature" rows="3" maxlength="500"><?= $this->e((string) ($profile['signature'] ?? '')) ?></textarea>
            </div>
        </div>
    </section>

<?php if ($this->can('user.role.manage')): ?>
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Roles</h2>
        <span class="panel-meta mono">permissions are the union of every assigned role</span></header>
        <div class="panel-form">
            <div class="role-grid">
<?php foreach ($roles as $role): ?>
                <label class="check">
                    <input type="checkbox" name="roles[]" value="<?= (int) $role['id'] ?>"
                        <?= in_array((int) $role['id'], $assigned_roles, true) ? 'checked' : '' ?>>
                    <span>
                        <span class="role-chip <?= $this->e($this->roleClass($role['id'])) ?>"><?= $this->e((string) $role['name']) ?></span>
                        <span class="muted mono"><?= $this->e((string) $role['slug']) ?></span>
                    </span>
                </label>
<?php endforeach; ?>
            </div>
            <div class="field">
                <label class="field-label" for="primary_role_id">Displayed rank</label>
                <select class="field-input" id="primary_role_id" name="primary_role_id">
<?php foreach ($roles as $role): ?>
                    <option value="<?= (int) $role['id'] ?>" <?= (int) ($profile['primary_role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>><?= $this->e((string) $role['name']) ?></option>
<?php endforeach; ?>
                </select>
                <p class="field-hint">Must be one of the assigned roles; it only affects what is shown next to the name.</p>
            </div>
        </div>
    </section>
<?php endif; ?>

    <div class="form-buttons">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.users')) ?>">Back to list</a>
        <button type="submit" class="btn btn-accent">Save user</button>
    </div>
</form>

<section class="panel panel-danger">
    <header class="panel-head"><h2 class="panel-title">Account operations</h2></header>
    <div class="panel-form operations-grid">
        <form action="<?= $this->e($this->route('admin.user.password', ['id' => $userId])) ?>" method="post">
            <?= $this->csrf() ?>
            <p class="muted">Generate a new random password and show it once.</p>
            <button type="submit" class="btn btn-small">Reset password</button>
        </form>

        <form action="<?= $this->e($this->route('admin.user.avatar', ['id' => $userId])) ?>" method="post">
            <?= $this->csrf() ?>
            <p class="muted">Delete the uploaded avatar and fall back to the monogram.</p>
            <button type="submit" class="btn btn-small" <?= $profile['avatar_path'] === null ? 'disabled' : '' ?>>Remove avatar</button>
        </form>

        <div>
            <p class="muted">Permanently delete the account. Posts are kept and attributed to a removed member.</p>
            <a class="btn btn-small btn-danger-quiet" href="<?= $this->e($this->route('admin.user.delete', ['id' => $userId])) ?>">Delete account…</a>
        </div>
    </div>
<?php if ($active_ban !== null): ?>
    <p class="panel-note">
        Active <?= $this->e((string) $active_ban['type']) ?>: <?= $this->e((string) $active_ban['reason']) ?>.
        Lift it from the <a href="<?= $this->e($this->route('moderation.user', ['username' => (string) $profile['username']])) ?>">moderation record</a>.
    </p>
<?php endif; ?>
</section>
