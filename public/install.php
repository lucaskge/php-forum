<?php

/**
 * Coldwire — web installer.
 *
 * Upload the project, open this file in a browser, answer two forms. It writes
 * .env, creates the database if your account is allowed to, applies the
 * migrations, seeds the configuration and creates the first administrator.
 *
 * It refuses to run once the board is installed, and the last screen offers to
 * delete it. Delete it. An installer left reachable on a live site is a way in.
 *
 * This file is deliberately self-contained: it renders its own pages and serves
 * its own stylesheet, so it works before there is any configuration to read.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Support/Autoloader.php';

App\Support\Autoloader::register(BASE_PATH . '/app');
App\Support\Autoloader::register(BASE_PATH . '/database/seeders', 'Database\\Seeders\\');

use App\Install\Installer;
use App\Support\Config;
use App\Support\Env;
use App\Support\Str;
use App\Support\Url;

mb_internal_encoding('UTF-8');
date_default_timezone_set('UTC');

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$installer = new Installer();

// ---------------------------------------------------------------------------
// Stylesheet: served from here so the installer needs no routing and no
// database, and so the Content-Security-Policy below can stay strict.
// ---------------------------------------------------------------------------

if (($_GET['asset'] ?? '') === 'css') {
    $theme = BASE_PATH . '/templates/themes/default/assets/css/board.css';

    header('Content-Type: text/css; charset=UTF-8');
    header('Cache-Control: no-store');

    echo is_readable($theme) ? file_get_contents($theme) : '';
    echo <<<'CSS'

    /* Installer-only additions. */
    .install-shell { max-width: 780px; margin: 0 auto; padding: 36px 18px 64px; }
    .install-head { margin-bottom: 20px; }
    .install-title { font-size: 22px; letter-spacing: 0.02em; }
    .install-subtitle { margin-top: 6px; color: var(--ink-dim); font-size: 13px; }
    .install-steps { display: flex; gap: 6px; margin: 18px 0 22px; padding: 0; list-style: none; }
    .install-steps li {
        flex: 1 1 0;
        padding: 7px 10px;
        background: var(--surface);
        border: 1px solid var(--line);
        border-radius: var(--radius);
        color: var(--ink-faint);
        font-family: var(--font-mono);
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .install-steps li.is-current { border-color: var(--accent-soft); color: var(--ink-strong); }
    .install-steps li.is-done { color: var(--ok); }
    .check-list { margin: 0; padding: 0; list-style: none; }
    .check-list li {
        display: flex;
        align-items: baseline;
        gap: 10px;
        padding: 8px 12px;
        border-bottom: 1px solid var(--line-soft);
    }
    .check-list li:last-child { border-bottom: 0; }
    .check-mark { flex: 0 0 34px; font-family: var(--font-mono); font-size: 10.5px; text-transform: uppercase; }
    .check-ok { color: var(--ok); }
    .check-warn { color: var(--warn); }
    .check-bad { color: var(--danger); }
    .check-body { flex: 1 1 auto; }
    .check-detail { display: block; margin-top: 2px; color: var(--ink-faint); font-size: 11.5px; }
    .env-box { white-space: pre-wrap; word-break: break-all; }
    .install-done { text-align: center; padding: 26px 16px; }
    .danger-zone { margin-top: 18px; padding-top: 14px; border-top: 1px solid var(--line-soft); }
CSS;

    exit;
}

// ---------------------------------------------------------------------------
// Session, token and state
// ---------------------------------------------------------------------------

session_name('coldwire_install');
session_start();

if (!isset($_SESSION['token']) || !is_string($_SESSION['token'])) {
    $_SESSION['token'] = Str::random(32);
}

$token = (string) $_SESSION['token'];
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$action = (string) ($_POST['action'] ?? '');

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @var array<string,string> $errors */
$errors = [];
$notice = null;
$noticeType = 'info';
$step = 'requirements';
$result = null;

$dbInput = $_SESSION['db'] ?? ['host' => '127.0.0.1', 'port' => '3306', 'database' => 'coldwire', 'username' => '', 'password' => ''];
$siteInput = $_SESSION['site'] ?? ['site_name' => 'Coldwire', 'site_url' => '', 'url_mode' => 'query'];
$adminInput = $_SESSION['admin'] ?? ['username' => '', 'email' => ''];

if ($siteInput['site_url'] === '') {
    $scheme = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $siteInput['site_url'] = $scheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

$blocked = $installer->blockedReason();

if ($isPost && (!is_string($_POST['_token'] ?? null) || !hash_equals($token, (string) $_POST['_token']))) {
    $notice = 'This form expired. It has been reloaded — try again.';
    $noticeType = 'error';
    $action = '';
}

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------

if ($action === 'remove') {
    // Deleting the installer is irreversible, so it is never the accidental
    // outcome of clicking the only button on a page.
    if (($_POST['confirm'] ?? '') !== 'yes') {
        $notice = 'Tick the confirmation box first — deleting the installer cannot be undone.';
        $noticeType = 'error';
        $step = $blocked !== null ? 'locked' : 'requirements';
    } else {
        $removal = $installer->removeInstaller();

        if ($removal['failed'] === []) {
            header('Location: ' . ($siteInput['site_url'] ?: '/'));

            exit;
        }

        $step = 'removal-failed';
        $result = $removal;
    }
} elseif ($blocked !== null) {
    $step = 'locked';
} elseif ($action === 'database') {
    $dbInput = [
        'host' => trim((string) ($_POST['host'] ?? '')),
        'port' => trim((string) ($_POST['port'] ?? '3306')),
        'database' => trim((string) ($_POST['database'] ?? '')),
        'username' => trim((string) ($_POST['username'] ?? '')),
        'password' => (string) ($_POST['password'] ?? ''),
    ];

    $test = $installer->testDatabase($dbInput);

    if ($test['ok']) {
        $_SESSION['db'] = $dbInput;
        $step = 'details';
        $notice = $test['message'];
        $noticeType = 'success';
    } else {
        $step = 'database';
        $errors = $test['errors'];
        $notice = $test['message'];
        $noticeType = 'error';
    }
} elseif ($action === 'install') {
    $siteInput = [
        'site_name' => trim((string) ($_POST['site_name'] ?? '')),
        'site_url' => trim((string) ($_POST['site_url'] ?? '')),
        'url_mode' => Url::normaliseMode((string) ($_POST['url_mode'] ?? 'query')),
    ];

    $adminInput = [
        'username' => trim((string) ($_POST['admin_username'] ?? '')),
        'email' => trim((string) ($_POST['admin_email'] ?? '')),
    ];

    $password = (string) ($_POST['admin_password'] ?? '');

    $errors = $installer->validateDetails($siteInput, [
        'username' => $adminInput['username'],
        'email' => $adminInput['email'],
        'password' => $password,
        'password_confirmation' => (string) ($_POST['admin_password_confirmation'] ?? ''),
    ]);

    $_SESSION['site'] = $siteInput;
    $_SESSION['admin'] = $adminInput;

    if ($errors !== []) {
        $step = 'details';
        $notice = 'Check the fields marked below.';
        $noticeType = 'error';
    } elseif (!isset($_SESSION['db'])) {
        $step = 'database';
        $notice = 'The database details were lost. Enter them again.';
        $noticeType = 'error';
    } else {
        $result = $installer->install($_SESSION['db'], $siteInput, [
            'username' => $adminInput['username'],
            'email' => $adminInput['email'],
            'password' => $password,
        ]);

        if ($result['ok']) {
            $step = 'done';
            unset($_SESSION['db'], $_SESSION['admin']);
        } else {
            $step = 'details';
            $notice = $result['message'];
            $noticeType = 'error';
        }
    }
} elseif ($action === 'continue') {
    $step = 'database';
} else {
    $step = 'requirements';
}

$checks = $installer->requirements();
$ready = $installer->requirementsMet($checks);

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header_remove('X-Powered-By');

$stepOrder = ['requirements' => 'Requirements', 'database' => 'Database', 'details' => 'Board & admin', 'done' => 'Finished'];
?>
<!DOCTYPE html>
<html lang="en" class="theme-default">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Install Coldwire</title>
<link rel="stylesheet" href="install.php?asset=css">
</head>
<body>
<div class="install-shell">
    <header class="install-head">
        <h1 class="install-title">Coldwire &mdash; installation</h1>
        <p class="install-subtitle">No terminal needed. Two forms, and the board is running.</p>
    </header>

<?php if (!in_array($step, ['locked', 'removal-failed'], true)): ?>
    <ol class="install-steps">
<?php $passed = true; foreach ($stepOrder as $key => $label): ?>
        <li class="<?= $key === $step ? 'is-current' : ($passed && $key !== $step ? 'is-done' : '') ?>"><?= e($label) ?></li>
<?php if ($key === $step) { $passed = false; } endforeach; ?>
    </ol>
<?php endif; ?>

<?php if ($notice !== null): ?>
    <div class="alert alert-<?= e($noticeType) ?>">
        <span class="alert-tag"><?= e($noticeType === 'error' ? 'err' : ($noticeType === 'success' ? 'ok' : 'info')) ?></span>
        <span class="alert-body"><?= e($notice) ?></span>
    </div>
<?php endif; ?>

<?php if ($step === 'locked'): ?>
    <section class="panel panel-notice">
        <header class="panel-head"><h2 class="panel-title">This board is already installed</h2></header>
        <div class="panel-inset">
<?php if ($blocked === 'lock'): ?>
            <p>
                The installer is locked because <code>storage/installed.lock</code> exists. That file is
                written at the end of a successful installation.
            </p>
<?php else: ?>
            <p>
                The installer is locked because the database named in <code>.env</code> already contains
                member accounts. Running it again would write a second configuration over a live board.
            </p>
<?php endif; ?>

            <p class="muted">
                Nothing here has been changed. You have two ways forward:
            </p>

            <ul class="plain-list">
                <li>
                    <strong>You are done installing.</strong> Delete this file — while it exists it is
                    the one page on the site that can rewrite your configuration. Use the button below.
                </li>
                <li>
                    <strong>You want to install again, on purpose.</strong> Delete
                    <code>storage/installed.lock</code> and point <code>.env</code> at an empty database,
                    then reload this page. Installing over a database that already has accounts is
                    refused whatever the lock file says.
                </li>
            </ul>

            <div class="danger-zone">
                <form method="post">
                    <input type="hidden" name="_token" value="<?= e($token) ?>">
                    <input type="hidden" name="action" value="remove">
                    <label class="check">
                        <input type="checkbox" name="confirm" value="yes">
                        <span>Yes, delete the installer. I understand this cannot be undone.</span>
                    </label>
                    <div class="form-buttons">
                        <button type="submit" class="btn btn-danger">Delete the installer</button>
                    </div>
                </form>
            </div>
        </div>
    </section>

<?php elseif ($step === 'removal-failed'): ?>
    <section class="panel panel-danger">
        <header class="panel-head"><h2 class="panel-title">Could not delete every file</h2></header>
        <div class="panel-inset">
            <p>The server would not let PHP remove these. Delete them by hand, over FTP or in your file manager:</p>
            <pre class="code-block"><?php foreach ($result['failed'] as $path): ?><?= e($path) ?>
<?php endforeach; ?></pre>
<?php if ($result['removed'] !== []): ?>
            <p class="muted">Already removed: <?= e(implode(', ', $result['removed'])) ?></p>
<?php endif; ?>
        </div>
    </section>

<?php elseif ($step === 'requirements'): ?>
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Server requirements</h2></header>
        <ul class="check-list">
<?php foreach ($checks as $check): ?>
            <li>
                <span class="check-mark <?= $check['ok'] ? 'check-ok' : ($check['required'] ? 'check-bad' : 'check-warn') ?>"><?= $check['ok'] ? 'ok' : ($check['required'] ? 'fail' : 'warn') ?></span>
                <span class="check-body">
                    <?= e($check['label']) ?>
                    <span class="check-detail"><?= e($check['detail']) ?></span>
                </span>
            </li>
<?php endforeach; ?>
        </ul>
    </section>

    <form method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="continue">
        <div class="form-buttons">
            <button type="submit" class="btn btn-accent" <?= $ready ? '' : 'disabled' ?>>
                <?= $ready ? 'Continue' : 'Fix the failures above' ?>
            </button>
        </div>
    </form>

<?php elseif ($step === 'database'): ?>
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Database</h2></header>
        <form class="stacked-form" method="post">
            <input type="hidden" name="_token" value="<?= e($token) ?>">
            <input type="hidden" name="action" value="database">

            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="host">Host</label>
                    <input class="field-input" type="text" id="host" name="host" required value="<?= e($dbInput['host']) ?>">
<?php if (isset($errors['host'])): ?><p class="field-error"><?= e($errors['host']) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field-label" for="port">Port</label>
                    <input class="field-input" type="number" id="port" name="port" min="1" max="65535" value="<?= e($dbInput['port']) ?>">
<?php if (isset($errors['port'])): ?><p class="field-error"><?= e($errors['port']) ?></p><?php endif; ?>
                </div>
            </div>

            <div class="field">
                <label class="field-label" for="database">Database name</label>
                <input class="field-input" type="text" id="database" name="database" required value="<?= e($dbInput['database']) ?>">
                <p class="field-hint">
                    Created for you if your account may create databases. On shared hosting it usually
                    may not — create it in your panel and put its name here.
                </p>
<?php if (isset($errors['database'])): ?><p class="field-error"><?= e($errors['database']) ?></p><?php endif; ?>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label class="field-label" for="username">Database user</label>
                    <input class="field-input" type="text" id="username" name="username" required value="<?= e($dbInput['username']) ?>">
<?php if (isset($errors['username'])): ?><p class="field-error"><?= e($errors['username']) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field-label" for="password">Database password</label>
                    <input class="field-input" type="password" id="password" name="password" autocomplete="off" value="<?= e($dbInput['password']) ?>">
                </div>
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn btn-accent">Test and continue</button>
            </div>
        </form>
    </section>

<?php elseif ($step === 'details'): ?>
    <form class="stacked-form" method="post">
        <input type="hidden" name="_token" value="<?= e($token) ?>">
        <input type="hidden" name="action" value="install">

        <section class="panel">
            <header class="panel-head"><h2 class="panel-title">Board</h2></header>
            <div class="panel-form">
                <div class="field">
                    <label class="field-label" for="site_name">Board name</label>
                    <input class="field-input" type="text" id="site_name" name="site_name" required value="<?= e($siteInput['site_name']) ?>">
<?php if (isset($errors['site_name'])): ?><p class="field-error"><?= e($errors['site_name']) ?></p><?php endif; ?>
                </div>

                <div class="field">
                    <label class="field-label" for="site_url">Board address</label>
                    <input class="field-input" type="url" id="site_url" name="site_url" required value="<?= e($siteInput['site_url']) ?>">
                    <p class="field-hint">Where members will reach the board. Used for links in e-mail.</p>
<?php if (isset($errors['site_url'])): ?><p class="field-error"><?= e($errors['site_url']) ?></p><?php endif; ?>
                </div>

                <fieldset class="field">
                    <legend class="field-label">Addresses</legend>
                    <label class="check">
                        <input type="radio" name="url_mode" value="path" <?= $siteInput['url_mode'] === 'path' ? 'checked' : '' ?>>
                        <span>
                            <strong>Clean</strong> &mdash; <code>/forum/general</code>.
                            The tidiest, but the server has to send unknown addresses to the board:
                            Apache with mod_rewrite (the bundled .htaccess does it) or an nginx rule.
                        </span>
                    </label>
                    <label class="check">
                        <input type="radio" name="url_mode" value="pathinfo" <?= $siteInput['url_mode'] === 'pathinfo' ? 'checked' : '' ?>>
                        <span>
                            <strong>Readable, no configuration</strong> &mdash; <code>/index.php/forum/general</code>.
                            Needs nothing from the server and stays easy to read. Works on most hosts;
                            if links come back as &ldquo;not found&rdquo;, use the last option.
                        </span>
                    </label>
                    <label class="check">
                        <input type="radio" name="url_mode" value="query" <?= $siteInput['url_mode'] !== 'path' && $siteInput['url_mode'] !== 'pathinfo' ? 'checked' : '' ?>>
                        <span>
                            <strong>Works on anything</strong> &mdash; <code>/index.php?r=/forum/general</code>.
                            The safe choice: if a server can run a PHP file at all, this works.
                        </span>
                    </label>
                </fieldset>
            </div>
        </section>

        <section class="panel">
            <header class="panel-head"><h2 class="panel-title">Administrator account</h2></header>
            <div class="panel-form">
                <div class="form-grid">
                    <div class="field">
                        <label class="field-label" for="admin_username">Username</label>
                        <input class="field-input" type="text" id="admin_username" name="admin_username" required value="<?= e($adminInput['username']) ?>">
<?php if (isset($errors['username'])): ?><p class="field-error"><?= e($errors['username']) ?></p><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field-label" for="admin_email">E-mail</label>
                        <input class="field-input" type="email" id="admin_email" name="admin_email" required value="<?= e($adminInput['email']) ?>">
<?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label class="field-label" for="admin_password">Password</label>
                        <input class="field-input" type="password" id="admin_password" name="admin_password" required>
                        <p class="field-hint">At least 10 characters, with a letter and a digit.</p>
<?php if (isset($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field-label" for="admin_password_confirmation">Repeat password</label>
                        <input class="field-input" type="password" id="admin_password_confirmation" name="admin_password_confirmation" required>
<?php if (isset($errors['password_confirmation'])): ?><p class="field-error"><?= e($errors['password_confirmation']) ?></p><?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <div class="form-buttons">
            <button type="submit" class="btn btn-accent">Install the board</button>
        </div>
    </form>

<?php elseif ($step === 'done'): ?>
    <section class="panel">
        <header class="panel-head"><h2 class="panel-title">Installed</h2></header>
        <div class="panel-inset">
            <ul class="check-list">
<?php foreach ($result['log'] as $line): ?>
                <li><span class="check-mark check-ok">ok</span><span class="check-body"><?= e($line) ?></span></li>
<?php endforeach; ?>
            </ul>
        </div>
    </section>

<?php if (!$result['env_written']): ?>
    <section class="panel panel-danger">
        <header class="panel-head"><h2 class="panel-title">Save this as .env</h2></header>
        <div class="panel-inset">
            <p>
                The project directory is not writable, so the configuration could not be saved.
                Create a file named <code>.env</code> in the project root with exactly this content,
                then reload the board:
            </p>
            <pre class="code-block env-box"><?= e($result['env']) ?></pre>
        </div>
    </section>
<?php endif; ?>

    <section class="panel panel-danger">
        <header class="panel-head"><h2 class="panel-title">Remove the installer</h2></header>
        <div class="panel-inset install-done">
            <p>
                Installation is finished, so this file has no further use &mdash; and while it exists it is
                the one page on the site that can rewrite your configuration.
            </p>
            <form method="post">
                <input type="hidden" name="_token" value="<?= e($token) ?>">
                <input type="hidden" name="action" value="remove">
                <label class="check">
                    <input type="checkbox" name="confirm" value="yes">
                    <span>Yes, delete the installer. I understand this cannot be undone.</span>
                </label>
                <div class="form-buttons">
                    <button type="submit" class="btn btn-danger">Delete the installer and open the board</button>
                </div>
            </form>
            <p class="panel-note muted">
                Deletes <code>public/install.php</code> and <code>app/Install/</code>, then takes you to the board.
            </p>
        </div>
    </section>
<?php endif; ?>
</div>
</body>
</html>
