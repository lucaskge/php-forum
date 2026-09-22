<?php
/** @var App\Support\View $this */
$old = $this->shared('old_input', []);
?>
<header class="page-head"><div><h1 class="page-title">Compose message</h1></div></header>
<?= $this->partial('partials/messages-nav', ['folder' => $folder, 'unread' => $unread]) ?>

<section class="panel">
    <form class="stacked-form" action="<?= $this->e($this->route('messages.send')) ?>" method="post">
        <?= $this->csrf() ?>
<?php if ($parent !== null): ?>
        <input type="hidden" name="parent_id" value="<?= (int) $parent['id'] ?>">
<?php endif; ?>
        <div class="form-grid">
            <div class="field">
                <label class="field-label" for="to">Recipient</label>
                <input class="field-input" type="text" id="to" name="to" maxlength="32" required
                       value="<?= $this->e((string) ($old['to'] ?? $to)) ?>">
                <p class="field-hint">Exact username. Open a profile and use “Send message” to fill this in for you.</p>
                <?= $this->partial('partials/field-error', ['field' => 'to']) ?>
            </div>
            <div class="field">
                <label class="field-label" for="subject">Subject</label>
                <input class="field-input" type="text" id="subject" name="subject" maxlength="190" required
                       value="<?= $this->e((string) ($old['subject'] ?? $subject)) ?>">
                <?= $this->partial('partials/field-error', ['field' => 'subject']) ?>
            </div>
        </div>

        <?= $this->partial('partials/editor', [
            'name' => 'body',
            'value' => (string) ($old['body'] ?? $body),
            'label' => 'Message',
            'rows' => 14,
        ]) ?>

        <div class="form-buttons">
            <a class="btn btn-quiet" href="<?= $this->e($this->route('messages.inbox')) ?>">Cancel</a>
            <button type="submit" class="btn btn-accent">Send message</button>
        </div>
    </form>
</section>
