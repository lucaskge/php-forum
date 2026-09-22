<?php
/**
 * @var App\Support\View $this
 * @var App\Support\Paginator $paginator
 */
?>
<header class="page-head">
    <div><h1 class="page-title">Moderation log</h1>
    <p class="page-subtitle"><?= $this->number($paginator->total()) ?> recorded action(s)</p></div>
</header>

<section class="panel">
    <form class="filter-bar" action="<?= $this->e($this->route('moderation.log')) ?>" method="get">
        <div class="field field-inline">
            <label class="field-label" for="action">Action</label>
            <select class="field-input" id="action" name="action">
                <option value="">Any action</option>
<?php foreach ($actions as $action): ?>
                <option value="<?= $this->e($action) ?>" <?= $filters['action'] === $action ? 'selected' : '' ?>><?= $this->e($action) ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="field field-inline">
            <label class="field-label" for="moderator">Moderator</label>
            <select class="field-input" id="moderator" name="moderator">
                <option value="">Anyone</option>
<?php foreach ($staff as $member): ?>
                <option value="<?= (int) $member['id'] ?>" <?= (int) $filters['moderator'] === (int) $member['id'] ? 'selected' : '' ?>><?= $this->e((string) $member['username']) ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="field field-inline">
            <label class="field-label" for="q">Contains</label>
            <input class="field-input" type="search" id="q" name="q" value="<?= $this->e((string) $filters['search']) ?>" maxlength="64">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-accent">Filter</button>
            <a class="btn btn-quiet" href="<?= $this->e($this->route('moderation.log')) ?>">Reset</a>
        </div>
    </form>

<?php if ($paginator->items() === []): ?>
    <?= $this->partial('partials/empty', ['message' => 'No log entries matched.']) ?>
<?php else: ?>
    <table class="board-table data-table log-table">
        <caption class="visually-hidden">Moderation log</caption>
        <thead>
            <tr>
                <th scope="col">When</th>
                <th scope="col">Moderator</th>
                <th scope="col">Action</th>
                <th scope="col">Summary</th>
                <th scope="col">Target</th>
                <th scope="col">Reason</th>
                <th scope="col">Address</th>
            </tr>
        </thead>
        <tbody>
<?php foreach ($paginator->items() as $entry): ?>
            <tr>
                <td class="mono"><?= $this->e($this->date((string) $entry['created_at'], 'Y-m-d H:i')) ?></td>
                <td><?= $this->username($entry, ['fallback' => 'system']) ?></td>
                <td class="mono"><?= $this->e((string) $entry['action']) ?></td>
                <td><?= $this->e((string) $entry['summary']) ?></td>
                <td>
<?php if (($entry['target_username'] ?? null) !== null): ?>
                    <?= $this->username(['username' => (string) $entry['target_username'], 'primary_role_id' => $entry['target_role_id'] ?? null]) ?>
<?php else: ?>
                    <span class="mono muted"><?= $this->e((string) $entry['target_type']) ?><?= $entry['target_id'] !== null ? ' #' . (int) $entry['target_id'] : '' ?></span>
<?php endif; ?>
                </td>
                <td><?= $this->e((string) ($entry['reason'] ?? '—')) ?></td>
                <td class="mono muted"><?= $this->e((string) ($entry['ip_address'] ?? '—')) ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</section>

<?= $this->partial('partials/pagination', ['paginator' => $paginator]) ?>
