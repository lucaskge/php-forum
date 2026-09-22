<?php /** @var App\Support\View $this */ ?>
<header class="page-head"><div><h1 class="page-title">Account settings</h1></div></header>
<?= $this->partial('partials/settings-nav') ?>

<section class="panel">
    <header class="panel-head"><h2 class="panel-title">Preferences</h2></header>
    <form class="stacked-form" action="<?= $this->e($this->route('settings.preferences.save')) ?>" method="post">
        <?= $this->csrf() ?>

        <div class="form-grid">
            <div class="field">
                <label class="field-label" for="timezone">Timezone</label>
                <select class="field-input" id="timezone" name="timezone">
<?php foreach ($timezones as $value => $label): ?>
                    <option value="<?= $this->e($value) ?>" <?= (string) $profile['timezone'] === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
<?php endforeach; ?>
                </select>
                <p class="field-hint">All timestamps on the board are rendered in this zone.</p>
            </div>

            <div class="field">
                <label class="field-label" for="posts_per_page">Posts per page</label>
                <input class="field-input" type="number" id="posts_per_page" name="posts_per_page" min="0" max="100"
                       value="<?= (int) $profile['posts_per_page'] ?>">
                <p class="field-hint">0 uses the board default (<?= (int) $site_posts_per_page ?>).</p>
            </div>
        </div>

        <fieldset class="field">
            <legend class="field-label">Notifications</legend>
            <label class="check"><input type="checkbox" name="notify_replies" value="1" <?= (int) $profile['notify_replies'] === 1 ? 'checked' : '' ?>><span>Replies in topics I follow</span></label>
            <label class="check"><input type="checkbox" name="notify_mentions" value="1" <?= (int) $profile['notify_mentions'] === 1 ? 'checked' : '' ?>><span>When somebody mentions me with @name</span></label>
            <label class="check"><input type="checkbox" name="notify_quotes" value="1" <?= (int) $profile['notify_quotes'] === 1 ? 'checked' : '' ?>><span>When somebody quotes my post</span></label>
            <label class="check"><input type="checkbox" name="notify_messages" value="1" <?= (int) $profile['notify_messages'] === 1 ? 'checked' : '' ?>><span>New private messages</span></label>
        </fieldset>

        <fieldset class="field">
            <legend class="field-label">Privacy</legend>
            <label class="check"><input type="checkbox" name="show_online" value="1" <?= (int) $profile['show_online'] === 1 ? 'checked' : '' ?>><span>Show me in the online list</span></label>
            <p class="field-hint">Staff can always see who is online.</p>
        </fieldset>

        <div class="form-buttons">
            <button type="submit" class="btn btn-accent">Save preferences</button>
        </div>
    </form>
</section>
