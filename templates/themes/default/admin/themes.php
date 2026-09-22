<?php
/**
 * @var App\Support\View $this
 * @var array<int,array<string,mixed>> $themes
 */
?>
<header class="page-head">
    <div><h1 class="page-title">Themes</h1>
    <p class="page-subtitle mono">active: <?= $this->e($active) ?> · directory: <?= $this->e($themes_path) ?></p></div>
    <div class="page-actions">
        <form class="inline-form" action="<?= $this->e($this->route('admin.themes.sync')) ?>" method="post">
            <?= $this->csrf() ?>
            <button type="submit" class="btn btn-quiet">Scan directory</button>
        </form>
    </div>
</header>

<div class="theme-grid">
<?php foreach ($themes as $theme): ?>
    <article class="panel theme-card<?= $theme['is_active'] ? ' is-active' : '' ?>">
        <header class="panel-head">
            <h2 class="panel-title">
                <a href="<?= $this->e($this->route('admin.theme', ['slug' => (string) $theme['slug']])) ?>"><?= $this->e((string) $theme['name']) ?></a>
            </h2>
            <span class="panel-meta mono">v<?= $this->e((string) $theme['version']) ?></span>
        </header>
        <div class="panel-inset">
            <p class="theme-description"><?= $this->e((string) $theme['description']) ?></p>
            <dl class="stat-list stat-list-wide">
                <div><dt>Identifier</dt><dd class="mono"><?= $this->e((string) $theme['slug']) ?></dd></div>
                <div><dt>Author</dt><dd><?= $this->e((string) $theme['author']) ?></dd></div>
                <div><dt>Templates</dt><dd><?= $this->number($theme['templates']) ?></dd></div>
<?php if (($theme['parent'] ?? null) !== null): ?>
                <div><dt>Inherits from</dt><dd class="mono"><?= $this->e((string) $theme['parent']) ?></dd></div>
<?php endif; ?>
                <div><dt>State</dt><dd>
<?php if (($theme['missing'] ?? false) === true): ?>
                    <span class="status-badge status-dismissed">files missing</span>
<?php elseif ($theme['is_active']): ?>
                    <span class="status-badge status-resolved">active</span>
<?php elseif (!$theme['is_enabled']): ?>
                    <span class="status-badge status-dismissed">disabled</span>
<?php elseif (!$theme['registered']): ?>
                    <span class="status-badge status-pending">not registered</span>
<?php else: ?>
                    <span class="status-badge status-pending">available</span>
<?php endif; ?>
                </dd></div>
            </dl>
        </div>
        <footer class="theme-actions">
<?php if (!$theme['is_active'] && ($theme['missing'] ?? false) !== true): ?>
            <form class="inline-form" action="<?= $this->e($this->route('admin.theme.activate', ['slug' => (string) $theme['slug']])) ?>" method="post">
                <?= $this->csrf() ?>
                <button type="submit" class="btn btn-small btn-accent">Activate</button>
            </form>
<?php endif; ?>
<?php if ($theme['registered'] && !$theme['is_active']): ?>
            <form class="inline-form" action="<?= $this->e($this->route('admin.theme.toggle', ['slug' => (string) $theme['slug']])) ?>" method="post">
                <?= $this->csrf() ?>
                <button type="submit" class="btn btn-small"><?= $theme['is_enabled'] ? 'Disable' : 'Enable' ?></button>
            </form>
<?php endif; ?>
            <a class="btn btn-small btn-quiet" href="<?= $this->e($this->route('admin.theme', ['slug' => (string) $theme['slug']])) ?>">Details</a>
<?php if (($theme['customisable'] ?? false) === true): ?>
            <a class="btn btn-small" href="<?= $this->e($this->route('admin.theme.appearance', ['slug' => (string) $theme['slug']])) ?>">Appearance</a>
<?php endif; ?>
        </footer>
    </article>
<?php endforeach; ?>
</div>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Adding a theme</h2></header>
    <div class="panel-inset">
        <p>Drop a directory into <code><?= $this->e($themes_path) ?></code> containing a <code>theme.json</code> manifest and
           the templates you want to override, then use <strong>Scan directory</strong> above.</p>
        <p class="muted">
            A theme only has to ship the files it changes: anything missing falls back to its parent theme and then to
            <code>default</code>. Board logic lives in <code>/app</code> and is never touched by a theme.
        </p>
    </div>
</section>
