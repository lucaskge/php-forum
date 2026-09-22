<?php
/**
 * @var App\Support\View $this
 * @var array<int,string> $groups
 * @var array<int,array<string,mixed>> $settings
 */
?>
<header class="page-head">
    <div><h1 class="page-title">Site settings</h1>
    <p class="page-subtitle">Stored in the database and applied immediately.</p></div>
</header>

<nav class="tabbar" aria-label="Setting groups">
<?php foreach ($groups as $name): ?>
    <a class="tab<?= $group === $name ? ' is-current' : '' ?>" href="<?= $this->e($this->route('admin.settings.group', ['group' => $name])) ?>">
        <?= $this->e(ucfirst(str_replace('_', ' ', $name))) ?>
    </a>
<?php endforeach; ?>
</nav>

<section class="panel">
    <form class="stacked-form panel-form" action="<?= $this->e($this->route('admin.settings.save', ['group' => $group])) ?>" method="post">
        <?= $this->csrf() ?>
<?php foreach ($settings as $setting): ?>
<?php
    $key = (string) $setting['key_name'];
    $id = 'setting_' . $key;
    $value = (string) ($setting['value'] ?? '');
    $options = json_decode((string) ($setting['options'] ?? 'null'), true);
?>
        <div class="field setting-field">
<?php if ((string) $setting['type'] === 'boolean'): ?>
            <label class="check">
                <input type="checkbox" id="<?= $this->e($id) ?>" name="<?= $this->e($id) ?>" value="1"
                    <?= in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true) ? 'checked' : '' ?>>
                <span><?= $this->e((string) $setting['label']) ?></span>
            </label>
<?php elseif ((string) $setting['type'] === 'text'): ?>
            <label class="field-label" for="<?= $this->e($id) ?>"><?= $this->e((string) $setting['label']) ?></label>
            <textarea class="field-input" id="<?= $this->e($id) ?>" name="<?= $this->e($id) ?>" rows="6"><?= $this->e($value) ?></textarea>
<?php elseif ((string) $setting['type'] === 'integer'): ?>
            <label class="field-label" for="<?= $this->e($id) ?>"><?= $this->e((string) $setting['label']) ?></label>
            <input class="field-input field-input-narrow" type="number" id="<?= $this->e($id) ?>" name="<?= $this->e($id) ?>" value="<?= (int) $value ?>">
<?php elseif ((string) $setting['type'] === 'select' && is_array($options)): ?>
            <label class="field-label" for="<?= $this->e($id) ?>"><?= $this->e((string) $setting['label']) ?></label>
            <select class="field-input" id="<?= $this->e($id) ?>" name="<?= $this->e($id) ?>">
<?php foreach ($options as $optionValue => $optionLabel): ?>
                <option value="<?= $this->e((string) $optionValue) ?>" <?= $value === (string) $optionValue ? 'selected' : '' ?>><?= $this->e((string) $optionLabel) ?></option>
<?php endforeach; ?>
            </select>
<?php else: ?>
            <label class="field-label" for="<?= $this->e($id) ?>"><?= $this->e((string) $setting['label']) ?></label>
            <input class="field-input" type="text" id="<?= $this->e($id) ?>" name="<?= $this->e($id) ?>" value="<?= $this->e($value) ?>" maxlength="500">
<?php endif; ?>
<?php if (($setting['description'] ?? '') !== ''): ?>
            <p class="field-hint"><?= $this->e((string) $setting['description']) ?> <code class="muted"><?= $this->e($key) ?></code></p>
<?php endif; ?>
        </div>
<?php endforeach; ?>

<?php if ($settings === []): ?>
        <p class="muted">No settings in this group.</p>
<?php endif; ?>

        <div class="form-buttons">
            <button type="submit" class="btn btn-accent">Save settings</button>
        </div>
    </form>
</section>
