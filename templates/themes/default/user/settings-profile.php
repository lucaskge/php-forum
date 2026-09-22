<?php
/** @var App\Support\View $this */
$old = $this->shared('old_input', []);
?>
<header class="page-head">
    <div><h1 class="page-title">Account settings</h1>
    <p class="page-subtitle">Signed in as <?= $this->e((string) $profile['username']) ?></p></div>
</header>
<?= $this->partial('partials/settings-nav') ?>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Profile</h2></header>
    <form class="stacked-form" action="<?= $this->e($this->route('settings.profile.save')) ?>" method="post">
        <?= $this->csrf() ?>
        <div class="field">
            <label class="field-label" for="bio">About you</label>
            <textarea class="field-input" id="bio" name="bio" rows="6" maxlength="2000"><?= $this->e((string) ($old['bio'] ?? $profile['bio'] ?? '')) ?></textarea>
            <p class="field-hint">Shown on your profile. Board formatting works here.</p>
            <?= $this->partial('partials/field-error', ['field' => 'bio']) ?>
        </div>

        <div class="field">
            <label class="field-label" for="signature">Signature</label>
            <textarea class="field-input" id="signature" name="signature" rows="4" maxlength="<?= (int) $signature_max ?>"><?= $this->e((string) ($old['signature'] ?? $profile['signature'] ?? '')) ?></textarea>
            <p class="field-hint">Appended below each of your posts. Up to <?= (int) $signature_max ?> characters.</p>
            <?= $this->partial('partials/field-error', ['field' => 'signature']) ?>
        </div>

        <div class="form-grid">
            <div class="field">
                <label class="field-label" for="location">Location</label>
                <input class="field-input" type="text" id="location" name="location" maxlength="64"
                       value="<?= $this->e((string) ($old['location'] ?? $profile['location'] ?? '')) ?>">
            </div>
            <div class="field">
                <label class="field-label" for="website">Website</label>
                <input class="field-input" type="url" id="website" name="website" maxlength="190" placeholder="https://"
                       value="<?= $this->e((string) ($old['website'] ?? $profile['website'] ?? '')) ?>">
                <?= $this->partial('partials/field-error', ['field' => 'website']) ?>
            </div>
        </div>

        <div class="form-buttons">
            <button type="submit" class="btn btn-accent">Save profile</button>
        </div>
    </form>
</section>
