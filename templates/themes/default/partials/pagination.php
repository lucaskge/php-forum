<?php
/**
 * @var App\Support\View $this
 * @var App\Support\Paginator $paginator
 * @var string $label
 */
$label = $label ?? 'Pagination';

if (!$paginator->hasPages()) {
    return;
}
?>
<nav class="pagination" aria-label="<?= $this->e($label) ?>">
    <span class="pagination-summary">
        <?= $this->number($paginator->firstItemNumber()) ?>–<?= $this->number($paginator->lastItemNumber()) ?>
        of <?= $this->number($paginator->total()) ?>
    </span>
    <ul class="pagination-list">
<?php if ($paginator->previousUrl() !== null): ?>
        <li><a class="page-step" href="<?= $this->e($paginator->previousUrl()) ?>" rel="prev">&larr; Prev</a></li>
<?php else: ?>
        <li><span class="page-step is-disabled">&larr; Prev</span></li>
<?php endif; ?>
<?php foreach ($paginator->window() as $page): ?>
<?php if ($page === null): ?>
        <li><span class="page-gap">…</span></li>
<?php elseif ($page === $paginator->currentPage()): ?>
        <li><span class="page-number is-current" aria-current="page"><?= (int) $page ?></span></li>
<?php else: ?>
        <li><a class="page-number" href="<?= $this->e($paginator->url($page)) ?>"><?= (int) $page ?></a></li>
<?php endif; ?>
<?php endforeach; ?>
<?php if ($paginator->nextUrl() !== null): ?>
        <li><a class="page-step" href="<?= $this->e($paginator->nextUrl()) ?>" rel="next">Next &rarr;</a></li>
<?php else: ?>
        <li><span class="page-step is-disabled">Next &rarr;</span></li>
<?php endif; ?>
    </ul>
</nav>
