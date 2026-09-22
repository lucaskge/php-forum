<?php /** @var App\Support\View $this */ ?>
<header class="page-head">
    <div><h1 class="page-title">Board rules</h1>
    <p class="page-subtitle">Read them before posting. Moderators enforce them.</p></div>
</header>

<section class="panel">
    <div class="panel-inset post-content">
<?php if ($rules !== ''): ?>
        <?= $this->content((string) $rules) ?>
<?php else: ?>
        <p class="muted">No rules have been published yet.</p>
<?php endif; ?>
    </div>
</section>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">How moderation works here</h2></header>
    <div class="panel-inset">
        <ul class="plain-list">
            <li>Any member can report a post. Reports go to a queue that moderators work through.</li>
            <li>Moderators can hide or delete content, lock or move topics, and warn, suspend or ban accounts.</li>
            <li>Every moderator action is written to a log with the moderator, the target, the reason and the time.</li>
            <li>Deleted posts and topics are kept in the database so a mistake can be undone.</li>
            <li>Warnings expire; suspensions end on their own; bans do not.</li>
        </ul>
    </div>
</section>
