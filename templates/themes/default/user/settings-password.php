<?php /** @var App\Support\View $this */ ?>
<header class="page-head"><div><h1 class="page-title">Account settings</h1></div></header>
<?= $this->partial('partials/settings-nav') ?>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Change password</h2></header>
    <form class="stacked-form" action="<?= $this->e($this->route('settings.password.save')) ?>" method="post">
        <?= $this->csrf() ?>
        <div class="field">
            <label class="field-label" for="current_password">Current password</label>
            <input class="field-input" type="password" id="current_password" name="current_password"
                   autocomplete="current-password" required>
            <?= $this->partial('partials/field-error', ['field' => 'current_password']) ?>
        </div>
        <div class="field">
            <label class="field-label" for="password">New password</label>
            <input class="field-input" type="password" id="password" name="password" autocomplete="new-password"
                   minlength="<?= (int) $min_password_length ?>" required>
            <p class="field-hint">At least <?= (int) $min_password_length ?> characters, including a letter and a digit.</p>
            <?= $this->partial('partials/field-error', ['field' => 'password']) ?>
        </div>
        <div class="field">
            <label class="field-label" for="password_confirmation">Repeat new password</label>
            <input class="field-input" type="password" id="password_confirmation" name="password_confirmation"
                   autocomplete="new-password" required>
            <?= $this->partial('partials/field-error', ['field' => 'password_confirmation']) ?>
        </div>
        <div class="form-buttons">
            <button type="submit" class="btn btn-accent">Change password</button>
        </div>
    </form>
</section>
