<?php
/** @var App\Support\View $this */
$old = $this->shared('old_input', []);
?>
<header class="page-head"><div><h1 class="page-title">Account settings</h1></div></header>
<?= $this->partial('partials/settings-nav') ?>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Account</h2></header>
    <dl class="stat-list stat-list-wide">
        <div><dt>Username</dt><dd class="mono"><?= $this->e((string) $profile['username']) ?></dd></div>
        <div><dt>Member since</dt><dd class="mono"><?= $this->e($this->date((string) $profile['created_at'], 'Y-m-d H:i')) ?></dd></div>
        <div><dt>Last sign-in</dt><dd class="mono"><?= $this->e($this->date((string) $profile['last_login_at'], 'Y-m-d H:i')) ?></dd></div>
        <div><dt>Status</dt><dd class="mono"><?= $this->e((string) $profile['status']) ?></dd></div>
    </dl>
    <p class="panel-note muted">Usernames are permanent. Ask an administrator if you need yours changed.</p>

    <form class="stacked-form" action="<?= $this->e($this->route('settings.account.save')) ?>" method="post">
        <?= $this->csrf() ?>
        <div class="field">
            <label class="field-label" for="email">E-mail address</label>
            <input class="field-input" type="email" id="email" name="email" maxlength="190" required
                   value="<?= $this->e((string) ($old['email'] ?? $profile['email'])) ?>">
            <?= $this->partial('partials/field-error', ['field' => 'email']) ?>
        </div>
        <div class="field">
            <label class="field-label" for="current_password">Confirm with your password</label>
            <input class="field-input" type="password" id="current_password" name="current_password"
                   autocomplete="current-password" required>
            <?= $this->partial('partials/field-error', ['field' => 'current_password']) ?>
        </div>
        <div class="form-buttons">
            <button type="submit" class="btn btn-accent">Update e-mail</button>
        </div>
    </form>
</section>
