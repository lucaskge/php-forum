<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed> $theme
 * @var array<string,mixed> $manifest
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title"><?= $this->e((string) $theme['name']) ?></h1>
        <p class="page-subtitle mono"><?= $this->e((string) $theme['slug']) ?> · v<?= $this->e((string) $theme['version']) ?> · by <?= $this->e((string) $theme['author']) ?></p>
    </div>
    <div class="page-actions">
<?php if (!$is_active && ($theme['missing'] ?? false) !== true): ?>
        <form class="inline-form" action="<?= $this->e($this->route('admin.theme.activate', ['slug' => (string) $theme['slug']])) ?>" method="post">
            <?= $this->csrf() ?>
            <button type="submit" class="btn btn-accent">Activate this theme</button>
        </form>
<?php endif; ?>
        <a class="btn btn-accent" href="<?= $this->e($this->route('admin.theme.appearance', ['slug' => (string) $theme['slug']])) ?>">Edit appearance</a>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.themes')) ?>">All themes</a>
    </div>
</header>

<div class="split-panels split-panels-wide">
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Metadata</h2></header>
        <dl class="stat-list stat-list-wide">
            <div><dt>Description</dt><dd><?= $this->e((string) $theme['description']) ?></dd></div>
            <div><dt>Templates on disk</dt><dd><?= $this->number($theme['templates']) ?></dd></div>
            <div><dt>Path</dt><dd class="mono"><?= $this->e((string) ($theme['path'] ?? 'missing')) ?></dd></div>
            <div><dt>Parent theme</dt><dd class="mono"><?= $this->e((string) ($theme['parent'] ?? 'none')) ?></dd></div>
            <div><dt>Registered</dt><dd><?= $theme['registered'] ? 'yes' : 'no' ?></dd></div>
            <div><dt>Enabled</dt><dd><?= $theme['is_enabled'] ? 'yes' : 'no' ?></dd></div>
            <div><dt>Active</dt><dd><?= $is_active ? 'yes' : 'no' ?></dd></div>
        </dl>
    </section>

    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Theme settings</h2></header>
        <div class="panel-inset">
<?php $settings = (array) ($manifest['settings'] ?? []); ?>
<?php if ($settings === []): ?>
            <p class="muted">This theme declares no settings in its manifest.</p>
<?php else: ?>
            <dl class="stat-list stat-list-wide">
<?php foreach ($settings as $key => $value): ?>
                <div><dt class="mono"><?= $this->e((string) $key) ?></dt><dd class="mono"><?= $this->e(is_scalar($value) ? (string) $value : json_encode($value)) ?></dd></div>
<?php endforeach; ?>
            </dl>
<?php endif; ?>
            <p class="muted">
                Declared values are stored with the theme record and are available to its templates; the shipped default
                theme reads them from its own CSS custom properties.
            </p>
        </div>
    </section>
</div>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Manifest</h2></header>
    <pre class="code-block"><code><?= $this->e(json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') ?></code></pre>
</section>
