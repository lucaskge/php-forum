<?php

declare(strict_types=1);

use App\Install\Installer;
use App\Support\Migrator;
use Tests\Assert;

/**
 * The installer's decisions, without writing anything. The full run is not
 * exercised here because it creates a database and an administrator; that is
 * covered by installing into a scratch database by hand.
 */
return [
    'requirements report the runtime honestly' => static function (): void {
        $checks = (new Installer())->requirements();

        Assert::true(count($checks) >= 8, 'every prerequisite is listed');

        $labels = array_column($checks, 'label');

        foreach (['PHP 8.3 or newer', 'Extension: pdo_mysql', 'Extension: gd'] as $expected) {
            Assert::true(in_array($expected, $labels, true), $expected . ' must be checked');
        }

        foreach ($checks as $check) {
            Assert::true(is_bool($check['ok']));
            Assert::true(is_bool($check['required']));
            Assert::true($check['detail'] !== '', $check['label'] . ' should explain itself');
        }
    },

    'a failed optional check does not block installation' => static function (): void {
        $installer = new Installer();

        Assert::true($installer->requirementsMet([
            ['ok' => true, 'required' => true],
            ['ok' => false, 'required' => false],
        ]), 'gd is a warning, not a blocker');

        Assert::false($installer->requirementsMet([
            ['ok' => true, 'required' => false],
            ['ok' => false, 'required' => true],
        ]));
    },

    'database names are restricted to safe identifiers' => static function (): void {
        foreach (['coldwire', 'my_board', 'board2024', 'A_b_9'] as $valid) {
            Assert::true(Migrator::isValidDatabaseName($valid), $valid . ' should be accepted');
        }

        // A database name cannot be a bound parameter, so it is validated
        // rather than escaped; these must never reach a CREATE DATABASE.
        foreach (['my board', 'board;DROP DATABASE x', 'board`', "board'", 'board-1', '../etc', '', str_repeat('a', 65)] as $invalid) {
            Assert::false(Migrator::isValidDatabaseName($invalid), var_export($invalid, true) . ' must be rejected');
        }
    },

    'the generated env carries everything the board needs' => static function (): void {
        $env = (new Installer())->buildEnv(
            ['host' => 'db.example.org', 'port' => '3307', 'database' => 'board', 'username' => 'boarduser', 'password' => 'p@ss"word'],
            ['site_name' => 'My Board', 'site_url' => 'https://board.example.org/', 'url_mode' => 'path'],
        );

        Assert::contains('DB_HOST=db.example.org', $env);
        Assert::contains('DB_PORT=3307', $env);
        Assert::contains('DB_DATABASE=board', $env);
        Assert::contains('APP_URL=https://board.example.org', $env);
        Assert::notContains('APP_URL=https://board.example.org/', $env, 'the trailing slash is trimmed');
        Assert::contains('APP_URL_MODE=path', $env);
        Assert::contains('APP_NAME="My Board"', $env);

        // A quote in the password must not end the value early.
        Assert::contains('DB_PASSWORD="p@ss\\"word"', $env);

        // Every deployment gets its own key, and it is never blank.
        Assert::same(1, preg_match('/^APP_KEY=[0-9a-f]{64}$/m', $env), 'a key is generated');
        Assert::true(
            (new Installer())->buildEnv(
                ['host' => 'h', 'port' => '1', 'database' => 'd', 'username' => 'u', 'password' => ''],
                ['site_name' => 'x', 'site_url' => 'http://x', 'url_mode' => 'query'],
            ) !== $env,
            'two installations never share a key',
        );
    },

    'https addresses turn on the secure session cookie' => static function (): void {
        $installer = new Installer();

        $secure = $installer->buildEnv(
            ['host' => 'h', 'port' => '3306', 'database' => 'd', 'username' => 'u', 'password' => ''],
            ['site_name' => 'x', 'site_url' => 'https://board.example.org', 'url_mode' => 'query'],
        );
        Assert::contains('SESSION_SECURE=true', $secure);

        $plain = $installer->buildEnv(
            ['host' => 'h', 'port' => '3306', 'database' => 'd', 'username' => 'u', 'password' => ''],
            ['site_name' => 'x', 'site_url' => 'http://board.example.org', 'url_mode' => 'query'],
        );
        Assert::contains('SESSION_SECURE=false', $plain);
    },

    'the administrator details are validated before anything is written' => static function (): void {
        $installer = new Installer();

        $good = $installer->validateDetails(
            ['site_name' => 'My Board', 'site_url' => 'https://board.example.org'],
            ['username' => 'lucas', 'email' => 'lucas@example.org', 'password' => 'a-good-password-1', 'password_confirmation' => 'a-good-password-1'],
        );
        Assert::same([], $good, 'valid details pass');

        $weak = $installer->validateDetails(
            ['site_name' => 'My Board', 'site_url' => 'https://board.example.org'],
            ['username' => 'lucas', 'email' => 'lucas@example.org', 'password' => 'short', 'password_confirmation' => 'short'],
        );
        Assert::true(isset($weak['password']), 'a weak password is refused');

        $mismatch = $installer->validateDetails(
            ['site_name' => 'My Board', 'site_url' => 'https://board.example.org'],
            ['username' => 'lucas', 'email' => 'lucas@example.org', 'password' => 'a-good-password-1', 'password_confirmation' => 'something-else-2'],
        );
        Assert::true(isset($mismatch['password_confirmation']));

        $badUrl = $installer->validateDetails(
            ['site_name' => 'My Board', 'site_url' => 'board.example.org'],
            ['username' => 'lucas', 'email' => 'lucas@example.org', 'password' => 'a-good-password-1', 'password_confirmation' => 'a-good-password-1'],
        );
        Assert::true(isset($badUrl['site_url']), 'the address must be a full URL');

        $badName = $installer->validateDetails(
            ['site_name' => 'My Board', 'site_url' => 'https://board.example.org'],
            ['username' => 'a b', 'email' => 'not-an-email', 'password' => 'a-good-password-1', 'password_confirmation' => 'a-good-password-1'],
        );
        Assert::true(isset($badName['username']) && isset($badName['email']));
    },

    'an installed board reports itself as installed' => static function (): void {
        // This checkout is installed, so the guard must say so — that is what
        // stops the installer running a second time on a live board.
        Assert::true((new Installer())->isInstalled());
    },
];
