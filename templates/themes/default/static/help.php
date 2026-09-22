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
            <tr><td class="mono">[b]bold[/b]</td><td><strong>bold</strong></td></tr>
            <tr><td class="mono">[i]italic[/i]</td><td><em>italic</em></td></tr>
            <tr><td class="mono">[u]underline[/u]</td><td><span class="u">underline</span></td></tr>
            <tr><td class="mono">[s]struck[/s]</td><td><del>struck</del></td></tr>
            <tr><td class="mono">[code]monospace[/code]</td><td><code>monospace</code></td></tr>
            <tr><td class="mono">[quote]quoted text[/quote]</td><td><em>an indented quote block</em></td></tr>
            <tr><td class="mono">[quote=name]text[/quote]</td><td><em>a quote attributed to a member</em></td></tr>
            <tr><td class="mono">[url=https://example.org]label[/url]</td><td>a link</td></tr>
            <tr><td class="mono">[img]https://example.org/a.png[/img]</td><td>an image</td></tr>
            <tr><td class="mono">[list][*]one[*]two[/list]</td><td>a bulleted list</td></tr>
            <tr><td class="mono">[spoiler]hidden[/spoiler]</td><td>text revealed on hover or focus</td></tr>
            <tr><td class="mono">[hr]</td><td>a horizontal rule</td></tr>
            <tr><td class="mono">@username</td><td>a mention that notifies that member</td></tr>
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
