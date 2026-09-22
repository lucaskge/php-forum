<?php
/** @var App\Support\View $this */
$old = $this->shared('old_input', []);
?>
<section class="auth-panel">
    <header class="auth-head">
        <h1 class="auth-title">Forgotten password</h1>
        <p class="auth-subtitle mono">a single-use link, valid for one hour</p>
    </header>

    <form class="stacked-form" action="<?= $this->e($this->route('auth.forgot')) ?>" method="post">
        <?= $this->csrf() ?>
        <div class="field">
            <label class="field-label" for="email">E-mail address</label>
            <input class="field-input" type="email" id="email" name="email" autocomplete="email" required autofocus
                   value="<?= $this->e((string) ($old['email'] ?? '')) ?>">
            <?= $this->partial('partials/field-error', ['field' => 'email']) ?>
            <p class="field-hint">Whether or not the address is registered, the answer you get here is the same.</p>
        </div>
        <div class="form-buttons">
            <button type="submit" class="btn btn-accent btn-block">Send reset link</button>
        </div>
    </form>

    <footer class="auth-foot">
        <a href="<?= $this->e($this->route('auth.login.show')) ?>">Back to sign in</a>
    </footer>
</section>
