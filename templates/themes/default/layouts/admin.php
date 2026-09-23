<?php
/**
 * Administration layout: same universe as the board, more utilitarian.
 *
 * @var App\Support\View $this
 * @var string $content
 */
?>
<!DOCTYPE html>
<html lang="en" class="theme-default">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $this->e($this->documentTitle()) ?></title>
<link rel="stylesheet" href="<?= $this->e($this->asset('css/board.css')) ?>">
<link rel="stylesheet" href="<?= $this->e($this->themeSettingsUrl()) ?>">
<link rel="stylesheet" href="<?= $this->e($this->dynamicStylesUrl()) ?>">
<link rel="icon" href="<?= $this->e($this->asset('img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="body-panel">
<a class="skip-link" href="#main">Skip to content</a>
<?= $this->partial('partials/header') ?>
<div class="shell">
    <div class="panel-shell">
        <?= $this->partial('partials/admin-sidebar') ?>
        <div class="panel-content">
            <?= $this->partial('partials/restriction-banner') ?>
    <?= $this->partial('partials/flash') ?>
            <main id="main">
                <?= $content ?>
            </main>
        </div>
    </div>
</div>
<?= $this->partial('partials/footer') ?>
</body>
</html>
