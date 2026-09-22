<?php /** @var App\Support\View $this */ ?>
<section class="auth-panel">
    <header class="auth-head">
        <h1 class="auth-title">Choose a new password</h1>
        <p class="auth-subtitle mono">account: <?= $this->e((string) $username) ?></p>
    </header>

    <form class="stacked-form" action="<?= $this->e($this->route('auth.reset')) ?>" method="post">
        <?= $this->csrf() ?>
        <input type="hidden" name="token" value="<?= $this->e((string) $token) ?>">

        <div class="field">
            <label class="field-label" for="password">New password</label>
            <input class="field-input" type="password" id="password" name="password" autocomplete="new-password"
                   minlength="<?= (int) $min_password_length ?>" required autofocus>
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
            <button type="submit" class="btn btn-accent btn-block">Change password</button>
        </div>
    </form>
</section>
