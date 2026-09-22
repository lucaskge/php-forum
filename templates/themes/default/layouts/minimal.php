<?php
/**
 * Bare layout used by error pages and the offline notice: no navigation, no
 * database reads beyond what was already shared.
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
<body class="body-minimal">
<div class="shell shell-narrow">
    <main id="main">
        <?= $content ?>
    </main>
    <p class="minimal-home"><a href="<?= $this->e($this->url('/')) ?>">&larr; Return to the board index</a></p>
</div>
</body>
</html>
