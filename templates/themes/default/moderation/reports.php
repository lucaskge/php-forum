<?php
/**
 * @var App\Support\View $this
 * @var App\Support\Paginator $paginator
 */
?>
<header class="page-head">
    <div><h1 class="page-title">Reports</h1>
    <p class="page-subtitle mono">
        <?= $this->number($counts['pending']) ?> pending ·
        <?= $this->number($counts['resolved']) ?> resolved ·
        <?= $this->number($counts['dismissed']) ?> dismissed
    </p></div>
</header>

<nav class="tabbar" aria-label="Report status">
<?php foreach (App\Models\ReportStatus::filters() as $value): ?>
<?php $label = App\Models\ReportStatus::tryFrom($value)?->label() ?? 'All'; ?>
    <a class="tab<?= $status === $value ? ' is-current' : '' ?>" href="<?= $this->e($this->route('moderation.reports', [], ['status' => $value])) ?>"><?= $this->e($label) ?></a>
<?php endforeach; ?>
</nav>

<section class="panel">
<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'No reports with this status.']) ?>
<?php else: ?>
    <table class="board-table data-table">
        <caption class="visually-hidden">Reports</caption>
        <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Content</th>
                <th scope="col">Reason</th>
                <th scope="col">Reporter</th>
                <th scope="col">Reported member</th>
                <th scope="col">Status</th>
                <th scope="col">When</th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($paginator->items() as $report): ?>
            <tr>
                <td><a href="<?= $this->e($this->route('moderation.report', ['id' => (int) $report['id']])) ?>">#<?= (int) $report['id'] ?></a></td>
                <td class="mono"><?= $this->e((string) $report['content_type']) ?> #<?= (int) $report['content_id'] ?></td>
                <td><?= $this->e($reasons[(string) $report['reason']] ?? (string) $report['reason']) ?></td>
                <td><?= $this->username($report) ?></td>
                <td><?= $this->username(['username' => $report['reported_username'] ?? null, 'primary_role_id' => $report['reported_role_id'] ?? null], ['fallback' => '—']) ?></td>
                <td><span class="status-badge status-<?= $this->e((string) $report['status']) ?>"><?= $this->e((string) $report['status']) ?></span></td>
                <td class="mono"><?= $this->e($this->relative((string) $report['created_at'])) ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</section>

<?= $this->partial('partials/pagination', ['paginator' => $paginator]) ?>
