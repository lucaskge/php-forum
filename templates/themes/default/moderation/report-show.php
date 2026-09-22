<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $report
 * @var array{label:string,excerpt:string,url:string|null,exists:bool,author:string|null} $content
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Report #<?= (int) $report['id'] ?></h1>
        <p class="page-subtitle mono">
            <?= $this->e((string) $report['content_type']) ?> #<?= (int) $report['content_id'] ?> ·
            <span class="status-badge status-<?= $this->e((string) $report['status']) ?>"><?= $this->e((string) $report['status']) ?></span>
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('moderation.reports')) ?>">Back to queue</a>
    </div>
</header>

<div class="split-panels split-panels-wide">
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Reported content</h2></header>
        <div class="panel-inset">
            <p class="mono muted"><?= $this->e($content['label']) ?></p>
            <div class="quoted-preview"><?= $this->content($content['excerpt']) ?></div>
<?php if ($content['url'] !== null): ?>
            <p><a class="btn btn-small" href="<?= $this->e($content['url']) ?>">Open in context</a></p>
<?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Report details</h2></header>
        <dl class="stat-list stat-list-wide">
            <div><dt>Reporter</dt><dd><?= $this->e((string) ($report['reporter_username'] ?? 'removed member')) ?></dd></div>
            <div><dt>Reported member</dt><dd>
<?php if (($report['reported_username'] ?? null) !== null): ?>
                <a href="<?= $this->e($this->route('moderation.user', ['username' => (string) $report['reported_username']])) ?>"><?= $this->e((string) $report['reported_username']) ?></a>
<?php else: ?>—<?php endif; ?>
            </dd></div>
            <div><dt>Reason</dt><dd><?= $this->e($reasons[(string) $report['reason']] ?? (string) $report['reason']) ?></dd></div>
            <div><dt>Submitted</dt><dd class="mono"><?= $this->e($this->date((string) $report['created_at'], 'Y-m-d H:i')) ?></dd></div>
<?php if (($report['ip_address'] ?? null) !== null): ?>
            <div><dt>Reporter address</dt><dd class="mono"><?= $this->e((string) $report['ip_address']) ?></dd></div>
<?php endif; ?>
<?php if (($report['handler_username'] ?? null) !== null): ?>
            <div><dt>Handled by</dt><dd><?= $this->e((string) $report['handler_username']) ?> · <?= $this->e($this->date((string) $report['handled_at'], 'Y-m-d H:i')) ?></dd></div>
            <div><dt>Action taken</dt><dd><?= $this->e((string) ($report['action_taken'] ?? '—')) ?></dd></div>
<?php endif; ?>
        </dl>
<?php if (($report['details'] ?? '') !== ''): ?>
        <div class="panel-inset">
            <p class="mono muted">Reporter's notes</p>
            <p><?= $this->e((string) $report['details']) ?></p>
        </div>
<?php endif; ?>
<?php if (($report['moderator_notes'] ?? '') !== ''): ?>
        <div class="panel-inset">
            <p class="mono muted">Moderator notes</p>
            <p><?= $this->e((string) $report['moderator_notes']) ?></p>
        </div>
<?php endif; ?>
    </section>
</div>

<?php if ((string) $report['status'] === 'pending' && $this->can('report.handle')): ?>
<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Review</h2></header>
    <form class="stacked-form" action="<?= $this->e($this->route('moderation.report.handle', ['id' => (int) $report['id']])) ?>" method="post">
        <?= $this->csrf() ?>
        <div class="form-grid">
            <fieldset class="field">
                <legend class="field-label">Decision</legend>
                <label class="check"><input type="radio" name="decision" value="resolve" checked><span>Resolve — the report was valid</span></label>
                <label class="check"><input type="radio" name="decision" value="dismiss"><span>Dismiss — no action needed</span></label>
            </fieldset>

            <fieldset class="field">
                <legend class="field-label">Content action</legend>
                <label class="check"><input type="radio" name="content_action" value="none" checked><span>Leave the content alone</span></label>
                <label class="check"><input type="radio" name="content_action" value="hide"><span>Hide it from members</span></label>
                <label class="check"><input type="radio" name="content_action" value="delete"><span>Delete it</span></label>
            </fieldset>
        </div>

        <div class="field">
            <label class="field-label" for="notes">Notes <span class="muted">(kept on the report and in the log)</span></label>
            <textarea class="field-input" id="notes" name="notes" rows="4" maxlength="2000"></textarea>
        </div>

        <div class="form-buttons">
            <button type="submit" class="btn btn-accent">Apply decision</button>
        </div>
    </form>
<?php if (($report['reported_username'] ?? null) !== null): ?>
    <p class="panel-note muted">
        To warn, suspend or ban the member, open their
        <a href="<?= $this->e($this->route('moderation.user', ['username' => (string) $report['reported_username']])) ?>">moderation record</a>.
    </p>
<?php endif; ?>
</section>
<?php endif; ?>

<?php if ($history !== []): ?>
<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Other reports about this member</h2></header>
    <table class="board-table data-table">
        <caption class="visually-hidden">Report history</caption>
        <thead><tr><th scope="col">#</th><th scope="col">Reason</th><th scope="col">Status</th><th scope="col">When</th></tr></thead>
        <tbody>
<?php foreach ($history as $entry): ?>
            <tr>
                <td><a href="<?= $this->e($this->route('moderation.report', ['id' => (int) $entry['id']])) ?>">#<?= (int) $entry['id'] ?></a></td>
                <td><?= $this->e((string) $entry['reason']) ?></td>
                <td><span class="status-badge status-<?= $this->e((string) $entry['status']) ?>"><?= $this->e((string) $entry['status']) ?></span></td>
                <td class="mono"><?= $this->e($this->date((string) $entry['created_at'], 'Y-m-d')) ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>
