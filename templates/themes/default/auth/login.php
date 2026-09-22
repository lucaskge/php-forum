<?php
/** @var App\Support\View $this */
$old = $this->shared('old_input', []);
?>
<section class="auth-panel">
    <header class="auth-head">
        <h1 class="auth-title">Sign in</h1>
        <p class="auth-subtitle mono">session authentication · no third parties</p>
    </header>

    <form class="stacked-form" action="<?= $this->e($this->route('auth.login')) ?>" method="post">
        <?= $this->csrf() ?>
        <div class="field">
            <label class="field-label" for="identifier">Username or e-mail</label>
            <input class="field-input" type="text" id="identifier" name="identifier" autocomplete="username"
                   required autofocus value="<?= $this->e((string) ($old['identifier'] ?? '')) ?>">
            <?= $this->partial('partials/field-error', ['field' => 'identifier']) ?>
        </div>

        <div class="field">
            <label class="field-label" for="password">Password</label>
            <input class="field-input" type="password" id="password" name="password" autocomplete="current-password" required>
            <?= $this->partial('partials/field-error', ['field' => 'password']) ?>
        </div>

        <div class="form-buttons">
            <button type="submit" class="btn btn-accent btn-block">Sign in</button>
        </div>
    </form>

    <footer class="auth-foot">
        <a href="<?= $this->e($this->route('auth.forgot.show')) ?>">Forgotten your password?</a>
<?php if ($registration_open): ?>
        <a href="<?= $this->e($this->route('auth.register.show')) ?>">Create an account</a>
<?php endif; ?>
    </footer>
</section>
