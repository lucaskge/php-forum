<?php
/**
 * Primary board layout.
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
<?php if ($this->canonical() !== ''): ?>
<link rel="canonical" href="<?= $this->e($this->canonical()) ?>">
<?php endif; ?>
<link rel="stylesheet" href="<?= $this->e($this->asset('css/board.css')) ?>">
<link rel="stylesheet" href="<?= $this->e($this->themeSettingsUrl()) ?>">
<link rel="stylesheet" href="<?= $this->e($this->dynamicStylesUrl()) ?>">
<link rel="icon" href="<?= $this->e($this->asset('img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<?= $this->partial('partials/header') ?>
<div class="shell">
    <?= $this->partial('partials/restriction-banner') ?>
    <?= $this->partial('partials/breadcrumbs') ?>
    <?= $this->partial('partials/flash') ?>
    <main id="main" class="board-main">
        <?= $content ?>
    </main>
</div>
<?= $this->partial('partials/footer') ?>
</body>
</html>
