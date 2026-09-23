<?php /** @var App\Support\View $this */ ?>
<header class="page-head">
    <div><h1 class="page-title">Formatting and help</h1>
    <p class="page-subtitle">Everything the editor understands. No JavaScript is involved anywhere on this board.</p></div>
</header>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Text formatting</h2></header>
    <table class="board-table data-table">
        <caption class="visually-hidden">Formatting reference</caption>
        <thead><tr><th scope="col">You type</th><th scope="col">You get</th></tr></thead>
        <tbody>
<?php foreach (App\Support\ContentFormatter::reference() as $tag): ?>
            <tr>
                <td class="mono"><?= $this->e($tag['example']) ?></td>
                <td><?= $this->e($tag['describes']) ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
    <p class="panel-note muted">
        Bare <code>https://</code> links are turned into links automatically. Only <code>http</code> and <code>https</code>
        addresses are accepted; everything else is left as plain text.
    </p>
</section>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Quoting</h2></header>
    <div class="panel-inset">
        <p>Use the <strong>quote</strong> link under any post. It opens the reply form with that post already quoted —
           a plain page load, no scripting.</p>
        <p class="muted">Quotes inside the quoted text are dropped so long chains do not snowball.</p>
    </div>
</section>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Getting around</h2></header>
    <div class="panel-inset">
        <ul class="plain-list">
            <li><strong>Subscribe</strong> to a topic to be notified of replies; <strong>bookmark</strong> one to find it later.</li>
            <li>Both lists live under <a href="<?= $this->e($this->route('settings.subscriptions')) ?>">account settings</a>.</li>
            <li>Notifications appear when you load a page — there is nothing polling in the background.</li>
            <li>The <a href="<?= $this->e($this->route('search')) ?>">search page</a> can filter by author, forum and date range.</li>
            <li>Set your timezone under <a href="<?= $this->e($this->route('settings.preferences')) ?>">preferences</a> and every timestamp follows it.</li>
        </ul>
    </div>
</section>
