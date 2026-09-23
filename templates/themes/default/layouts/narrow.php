<?php
/**
 * Layout for focused single-column forms (sign in, register, reset).
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
<title><?= $this->e($this->documentTitle()) ?></title>
<?php foreach ($this->metaTags() as $name => $value): ?>
<meta name="<?= $this->e($name) ?>" content="<?= $this->e($value) ?>">
<?php endforeach; ?>
<link rel="stylesheet" href="<?= $this->e($this->asset('css/board.css')) ?>">
<link rel="stylesheet" href="<?= $this->e($this->themeSettingsUrl()) ?>">
<link rel="stylesheet" href="<?= $this->e($this->dynamicStylesUrl()) ?>">
<link rel="icon" href="<?= $this->e($this->asset('img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="body-narrow">
<a class="skip-link" href="#main">Skip to content</a>
<?= $this->partial('partials/header') ?>
<div class="shell shell-narrow">
    <?= $this->partial('partials/restriction-banner') ?>
    <?= $this->partial('partials/flash') ?>
    <main id="main">
        <?= $content ?>
    </main>
</div>
<?= $this->partial('partials/footer') ?>
</body>
</html>
