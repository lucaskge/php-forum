#!/usr/bin/env php
<?php

/**
 * Test runner.
 *
 *   php tests/run.php            run everything the environment allows
 *   php tests/run.php unit       unit tests only (no database needed)
 *   php tests/run.php feature    feature tests only
 *
 * Each test file returns an array of `description => callable`. A test passes
 * when it returns without throwing.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Support/Autoloader.php';

App\Support\Autoloader::register(BASE_PATH . '/app');
App\Support\Autoloader::register(BASE_PATH . '/tests', 'Tests\\');

App\Support\Env::load(BASE_PATH . '/.env');
App\Support\Config::load(BASE_PATH . '/config');
date_default_timezone_set('UTC');

$only = $argv[1] ?? 'all';

$suites = [];

if ($only === 'all' || $only === 'unit') {
    $suites['unit'] = glob(BASE_PATH . '/tests/Unit/*.php') ?: [];
}

if ($only === 'all' || $only === 'feature') {
    if (Tests\Harness::databaseReachable() && Tests\Harness::seeded()) {
        Tests\Harness::clearThrottles();
        $suites['feature'] = glob(BASE_PATH . '/tests/Feature/*.php') ?: [];
    } else {
        fwrite(STDOUT, "feature tests skipped: no seeded database reachable (run `php bin/console install`)\n\n");
    }
}

$passed = 0;
$failed = [];
$start = microtime(true);

foreach ($suites as $suite => $files) {
    fwrite(STDOUT, strtoupper($suite) . "\n");

    foreach ($files as $file) {
        $name = basename($file, '.php');
        $tests = require $file;

        if (!is_array($tests)) {
            continue;
        }

        fwrite(STDOUT, '  ' . $name . "\n");

        foreach ($tests as $description => $test) {
            try {
                $test();
                $passed++;
                fwrite(STDOUT, "    \033[32mok\033[0m   " . $description . "\n");
            } catch (Throwable $exception) {
                $failed[] = [$name, (string) $description, $exception];
                fwrite(STDOUT, "    \033[31mFAIL\033[0m " . $description . "\n");
            }
        }
    }

    fwrite(STDOUT, "\n");
}

Tests\Fixtures::cleanup();

$elapsed = round((microtime(true) - $start) * 1000);

if ($failed !== []) {
    fwrite(STDOUT, "FAILURES\n\n");

    foreach ($failed as [$file, $description, $exception]) {
        fwrite(STDOUT, sprintf(
            "  %s › %s\n    %s\n    at %s:%d\n\n",
            $file,
            $description,
            $exception->getMessage(),
            str_replace(BASE_PATH . '/', '', $exception->getFile()),
            $exception->getLine(),
        ));
    }
}

fwrite(STDOUT, sprintf(
    "%d passed, %d failed, %d assertions, %dms\n",
    $passed,
    count($failed),
    Tests\Assert::$count,
    $elapsed,
));

exit($failed === [] ? 0 : 1);
