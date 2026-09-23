<?php
/**
 * @var App\Support\View $this
 * @var array<string,mixed>|null $current_user
 */
$user = $this->shared('current_user');
$unreadMessages = (int) $this->shared('unread_messages', 0);
$unreadNotifications = (int) $this->shared('unread_notifications', 0);
$pendingReports = (int) $this->shared('pending_reports', 0);
?>
<header class="masthead">
    <div class="shell masthead-inner">
        <div class="brand">
            <a class="brand-mark" href="<?= $this->e($this->url('/')) ?>">
                <span class="brand-glyph" aria-hidden="true">//</span>
                <span class="brand-name"><?= $this->e($this->shared('site_name', 'Coldwire')) ?></span>
            </a>
<?php if ($this->shared('site_tagline', '') !== ''): ?>
            <p class="brand-tagline"><?= $this->e($this->shared('site_tagline')) ?></p>
<?php endif; ?>
        </div>

        <form class="masthead-search" action="<?= $this->e($this->formAction('search')) ?>" method="get" role="search">
        <?= $this->routeField('search') ?>
            <label class="visually-hidden" for="masthead-q">Search the board</label>
            <input type="search" id="masthead-q" name="q" placeholder="search…" maxlength="120" value="<?= $this->e((string) ($this->shared('search_prefill') ?? '')) ?>">
            <button type="submit">Search</button>
        </form>

        <div class="masthead-account">
<?php if ($user === null): ?>
            <a class="btn btn-quiet" href="<?= $this->e($this->route('auth.login.show')) ?>">Sign in</a>
            <a class="btn btn-accent" href="<?= $this->e($this->route('auth.register.show')) ?>">Register</a>
<?php else: ?>
            <a class="account-chip <?= $this->e($this->roleClass($user['primary_role_id'] ?? null)) ?>"
               href="<?= $this->e($this->route('user.profile', ['username' => (string) $user['username']])) ?>">
                <?= $this->avatar($user, 26) ?>
                <span class="account-name"><?= $this->e((string) $user['username']) ?></span>
            </a>
            <form action="<?= $this->e($this->route('auth.logout')) ?>" method="post" class="inline-form">
                <?= $this->csrf() ?>
                <button type="submit" class="btn btn-quiet">Sign out</button>
            </form>
<?php endif; ?>
        </div>
    </div>

    <nav class="mainnav" aria-label="Main">
        <div class="shell mainnav-inner">
            <details class="nav-toggle">
                <summary aria-label="Open navigation">Menu</summary>
                <?= $this->partial('partials/nav-links', [
                    'user' => $user,
                    'unread_messages' => $unreadMessages,
                    'unread_notifications' => $unreadNotifications,
                    'pending_reports' => $pendingReports,
                    'variant' => 'stacked',
                ]) ?>
            </details>
            <?= $this->partial('partials/nav-links', [
                'user' => $user,
                'unread_messages' => $unreadMessages,
                'unread_notifications' => $unreadNotifications,
                'pending_reports' => $pendingReports,
                'variant' => 'inline',
            ]) ?>
        </div>
    </nav>
</header>
