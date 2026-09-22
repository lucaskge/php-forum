<?php /** @var App\Support\View $this */ ?>
<header class="page-head"><div><h1 class="page-title">Account settings</h1></div></header>
<?= $this->partial('partials/settings-nav') ?>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Avatar</h2></header>
    <div class="avatar-settings">
        <div class="avatar-preview">
            <?= $this->avatar($profile, 96) ?>
            <p class="muted mono"><?= $profile['avatar_path'] === null ? 'generated monogram' : 'uploaded image' ?></p>
        </div>
        <div class="avatar-forms">
<?php if (!$avatars_enabled): ?>
            <p class="muted">Avatar uploads are disabled on this board. The generated monogram is used for everyone.</p>
<?php elseif (!$uploads_available): ?>
            <p class="muted">This server is missing the image library the board needs to process uploads, so avatars cannot be accepted.</p>
<?php else: ?>
            <form class="stacked-form" action="<?= $this->e($this->route('settings.avatar.save')) ?>" method="post" enctype="multipart/form-data">
                <?= $this->csrf() ?>
                <div class="field">
                    <label class="field-label" for="avatar">Upload an image</label>
                    <input class="field-input" type="file" id="avatar" name="avatar"
                           accept="image/jpeg,image/png,image/gif,image/webp" required>
                    <p class="field-hint">
                        <?= $this->e(implode(', ', $formats)) ?> · up to <?= $this->number($max_kb) ?> KB
                        and <?= $this->number($max_source_width) ?>×<?= $this->number($max_source_height) ?> px.
                        Anything larger than <?= (int) $max_width ?>×<?= (int) $max_height ?> is scaled down for you.
                    </p>
                    <p class="field-hint">
                        Files are checked by their contents, never by their name, and are then
                        re-encoded from the decoded pixels — so metadata is stripped and nothing
                        hidden inside the original file survives.
                    </p>
                </div>
                <div class="form-buttons">
                    <button type="submit" class="btn btn-accent">Upload avatar</button>
                </div>
            </form>

<?php if ($profile['avatar_path'] !== null): ?>
            <form action="<?= $this->e($this->route('settings.avatar.delete')) ?>" method="post">
                <?= $this->csrf() ?>
                <button type="submit" class="btn btn-danger-quiet">Remove current avatar</button>
            </form>
<?php endif; ?>
<?php endif; ?>
        </div>
    </div>
</section>
