<?php
/** @var App\Support\View $this */
$old = $this->shared('old_input', []);
?>
<section class="auth-panel auth-panel-wide">
    <header class="auth-head">
        <h1 class="auth-title">Create an account</h1>
        <p class="auth-subtitle mono">username · e-mail · password — nothing else is collected</p>
    </header>

    <form class="stacked-form" action="<?= $this->e($this->route('auth.register')) ?>" method="post">
        <?= $this->csrf() ?>
        <div class="field">
            <label class="field-label" for="username">Username</label>
            <input class="field-input" type="text" id="username" name="username" autocomplete="username"
                   minlength="3" maxlength="32" required value="<?= $this->e((string) ($old['username'] ?? '')) ?>">
            <p class="field-hint">3–32 characters: letters, digits, dot, dash or underscore.</p>
            <?= $this->partial('partials/field-error', ['field' => 'username']) ?>
        </div>

        <div class="field">
            <label class="field-label" for="email">E-mail address</label>
            <input class="field-input" type="email" id="email" name="email" autocomplete="email"
                   maxlength="190" required value="<?= $this->e((string) ($old['email'] ?? '')) ?>">
            <p class="field-hint">Used for password resets only.</p>
            <?= $this->partial('partials/field-error', ['field' => 'email']) ?>
        </div>

        <div class="field">
            <label class="field-label" for="password">Password</label>
            <input class="field-input" type="password" id="password" name="password" autocomplete="new-password"
                   minlength="<?= (int) $min_password_length ?>" required>
            <p class="field-hint">At least <?= (int) $min_password_length ?> characters, including a letter and a digit.</p>
            <?= $this->partial('partials/field-error', ['field' => 'password']) ?>
        </div>

        <div class="field">
            <label class="field-label" for="password_confirmation">Repeat password</label>
            <input class="field-input" type="password" id="password_confirmation" name="password_confirmation"
                   autocomplete="new-password" required>
            <?= $this->partial('partials/field-error', ['field' => 'password_confirmation']) ?>
        </div>

<?php if ($rules !== ''): ?>
        <details class="rules-box">
            <summary>Board rules</summary>
            <div class="rules-body"><?= $this->content((string) $rules) ?></div>
        </details>
<?php endif; ?>

        <div class="field">
            <label class="check">
                <input type="checkbox" name="accept_rules" value="1" required>
                <span>I have read and accept the <a href="<?= $this->e($this->route('rules')) ?>">board rules</a>.</span>
            </label>
            <?= $this->partial('partials/field-error', ['field' => 'accept_rules']) ?>
        </div>

        <div class="form-buttons">
            <button type="submit" class="btn btn-accent btn-block">Create account</button>
        </div>
    </form>

    <footer class="auth-foot">
        <a href="<?= $this->e($this->route('auth.login.show')) ?>">Already registered? Sign in</a>
    </footer>
</section>
