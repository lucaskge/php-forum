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

        $labels = array_column($checks, 'label');

        foreach (['PHP ' . Installer::MINIMUM_PHP . ' or newer', 'Extension: pdo_mysql'] as $expected) {
            Assert::true(in_array($expected, $labels, true), $expected . ' must be checked');
        }

        foreach ($checks as $check) {
            Assert::true(is_bool($check['ok']));
            Assert::true(is_bool($check['required']));
            Assert::true($check['detail'] !== '', $check['label'] . ' should explain itself');
        }
    },

    'only what the board truly cannot run without is required' => static function (): void {
        // The point of this list is that it is short. Somebody installing on
        // hosting they do not administer cannot add an extension, so anything
        // the board can work around must not stop them.
        $required = array_values(array_filter(
            (new Installer())->requirements(),
            static fn (array $check): bool => $check['required'],
        ));

        $labels = array_column($required, 'label');
        sort($labels);

        Assert::same(
            ['Extension: mbstring', 'Extension: pdo_mysql', 'PHP ' . Installer::MINIMUM_PHP . ' or newer'],
            $labels,
            'these three, and nothing else, may block an installation',
        );
    },

    'the image library accepts either gd or imagick' => static function (): void {
        $checks = (new Installer())->requirements();
        $image = null;

        foreach ($checks as $check) {
            if (str_contains($check['label'], 'Image library')) {
                $image = $check;
            }
        }

        Assert::notNull($image, 'the image library is reported');
        Assert::false($image['required'], 'and it never blocks installation');
        Assert::same(
            extension_loaded('gd') || extension_loaded('imagick'),
            $image['ok'],
            'either extension satisfies it',
        );
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

    'a refused connection is not reported as a bad password' => static function (): void {
        // Port 1 has nothing listening, so this fails immediately.
        $result = (new Installer())->testDatabase([
            'host' => '127.0.0.1',
            'port' => '1',
            'database' => 'anything',
            'username' => 'someone',
            'password' => 'secret',
        ]);

        Assert::false($result['ok']);
        Assert::false(isset($result['errors']['username']), 'the credentials were never even tried');
        Assert::true(isset($result['errors']['host']), 'the host is what needs looking at');
    },

    'a host that does not resolve says so' => static function (): void {
        $result = (new Installer())->testDatabase([
            'host' => 'no-such-host.invalid',
            'port' => '3306',
            'database' => 'anything',
            'username' => 'someone',
            'password' => 'secret',
        ]);

        Assert::false($result['ok']);
        Assert::contains('resolve', $result['message']);
        Assert::true(isset($result['errors']['host']));
    },

    'the suggested host suits where the installer is running' => static function (): void {
        $host = (new Installer())->suggestedDatabaseHost();

        Assert::true(
            in_array($host, ['127.0.0.1', 'host.docker.internal'], true),
            'unexpected suggestion: ' . $host,
        );

        // These tests run in a container, where a loopback address would point
        // at the container itself and fail on the very first screen.
        if (is_file('/.dockerenv')) {
            Assert::same('host.docker.internal', $host);
        }
    },

    'an invalid database name never reaches the server' => static function (): void {
        $result = (new Installer())->testDatabase([
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'forum; DROP DATABASE coldwire',
            'username' => 'someone',
            'password' => 'secret',
        ]);

        Assert::false($result['ok']);
        Assert::true(isset($result['errors']['database']));
        Assert::contains('letters, digits and underscores', $result['message']);
    },

    'the guard names why it refuses, rather than just refusing' => static function (): void {
        $reason = (new Installer())->blockedReason();

        // Either it is not blocked, or it can say which signal blocked it.
        Assert::true(
            $reason === null || in_array($reason, ['lock', 'accounts'], true),
            'unexpected reason: ' . var_export($reason, true),
        );

        Assert::same($reason !== null, (new Installer())->isInstalled());
    },
];
