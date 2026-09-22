<?php
/**
 * Appearance editor.
 *
 * Every control here is declared by the theme's own manifest, so a custom theme
 * exposes whatever it wants to expose without a line of PHP changing. The
 * inputs are native HTML — `<input type="color">` gives a real picker with no
 * scripting involved.
 *
 * @var App\Support\View $this
 * @var array<string,mixed> $theme
 * @var array<string,array<int,array<string,mixed>>> $groups
 * @var array<string,string> $values
 */
?>
<header class="page-head">
    <div>
        <h1 class="page-title">Appearance</h1>
        <p class="page-subtitle">
            <?= $this->e((string) $theme['name']) ?>
            <span class="mono muted">/ <?= $this->e((string) $theme['slug']) ?></span>
<?php if (!$is_active): ?>
            · <span class="tag tag-warn">not the active theme</span>
<?php endif; ?>
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.theme', ['slug' => (string) $theme['slug']])) ?>">Theme details</a>
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.themes')) ?>">All themes</a>
    </div>
</header>

<form class="stacked-form" action="<?= $this->e($this->route('admin.theme.appearance.save', ['slug' => (string) $theme['slug']])) ?>" method="post">
    <?= $this->csrf() ?>

<?php foreach ($groups as $group => $definitions): ?>
    <section class="panel">
        <header class="panel-head">
            <h2 class="panel-title"><?= $this->e((string) $group) ?></h2>
            <span class="panel-meta mono"><?= $this->number(count($definitions)) ?> setting(s)</span>
        </header>
        <div class="panel-form">
            <div class="appearance-grid">
<?php foreach ($definitions as $definition): ?>
<?php
    $key = (string) $definition['key'];
    $id = 'theme-' . $key;
    $value = (string) ($values[$key] ?? $definition['default']);
?>
                <div class="field appearance-field">
                    <label class="field-label" for="<?= $this->e($id) ?>"><?= $this->e((string) $definition['label']) ?></label>

<?php if ((string) $definition['type'] === 'colour'): ?>
                    <div class="colour-field">
                        <input class="colour-swatch" type="color" id="<?= $this->e($id) ?>"
                               name="theme[<?= $this->e($key) ?>]" value="<?= $this->e($value) ?>">
                        <output class="colour-preview" for="<?= $this->e($id) ?>">
                            <code class="mono"><?= $this->e($value) ?></code>
                        </output>
                    </div>
<?php elseif ((string) $definition['type'] === 'integer'): ?>
                    <input class="field-input field-input-narrow" type="number" id="<?= $this->e($id) ?>"
                           name="theme[<?= $this->e($key) ?>]" value="<?= (int) $value ?>"
                           min="<?= (int) ($definition['min'] ?? 0) ?>" max="<?= (int) ($definition['max'] ?? 9999) ?>">
                    <span class="field-unit mono"><?= $this->e((string) $definition['unit']) ?></span>
<?php elseif ((string) $definition['type'] === 'select'): ?>
                    <select class="field-input" id="<?= $this->e($id) ?>" name="theme[<?= $this->e($key) ?>]">
<?php foreach ((array) $definition['options'] as $optionValue => $optionLabel): ?>
                        <option value="<?= $this->e((string) $optionValue) ?>" <?= $value === (string) $optionValue ? 'selected' : '' ?>>
                            <?= $this->e((string) $optionLabel) ?>
                        </option>
<?php endforeach; ?>
                    </select>
<?php else: ?>
                    <input class="field-input" type="text" id="<?= $this->e($id) ?>"
                           name="theme[<?= $this->e($key) ?>]" value="<?= $this->e($value) ?>" maxlength="120">
<?php endif; ?>

<?php if ((string) $definition['hint'] !== ''): ?>
                    <p class="field-hint"><?= $this->e((string) $definition['hint']) ?></p>
<?php endif; ?>
                    <p class="field-hint mono muted"><?= $this->e((string) $definition['css']) ?></p>
                </div>
<?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endforeach; ?>

    <div class="form-buttons">
        <a class="btn btn-quiet" href="<?= $this->e($this->route('admin.themes')) ?>">Cancel</a>
        <button type="submit" class="btn btn-accent">Save appearance</button>
    </div>
</form>

<section class="panel">
    <header class="panel-head">
        <h2 class="panel-title">Code preview</h2>
        <span class="panel-meta mono">rendered with the saved values</span>
    </header>
    <div class="panel-inset post-content">
        <?= $this->content($sample) ?>
        <p class="muted">
            Highlighting runs on the server and emits nothing but
            <code>&lt;span&gt;</code> elements; the colours above are the only thing
            that decides how it looks. Save the form and reload to see changes here.
        </p>
    </div>
</section>

<section class="panel panel-danger">
    <header class="panel-head"><h2 class="panel-title">Reset</h2></header>
    <div class="panel-form">
        <p class="muted">Discards every override and returns to the values declared in the theme's manifest.</p>
        <form action="<?= $this->e($this->route('admin.theme.appearance.reset', ['slug' => (string) $theme['slug']])) ?>" method="post">
            <?= $this->csrf() ?>
            <button type="submit" class="btn btn-danger">Reset to theme defaults</button>
        </form>
    </div>
</section>
