<?php
/** @var App\Support\View $this */
$crumbs = $this->breadcrumbs();

if ($crumbs === []) {
    return;
}
?>
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <ol>
<?php foreach ($crumbs as $index => $crumb): ?>
        <li>
<?php if ($crumb['url'] !== null && $index < count($crumbs) - 1): ?>
            <a href="<?= $this->e($crumb['url']) ?>"><?= $this->e($crumb['label']) ?></a>
<?php else: ?>
            <span aria-current="page"><?= $this->e($crumb['label']) ?></span>
<?php endif; ?>
        </li>
<?php endforeach; ?>
    </ol>
</nav>
