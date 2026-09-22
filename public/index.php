<?php

/**
 * Coldwire — front controller.
 *
 * Every request enters here; the web server only needs to rewrite unknown
 * paths onto this file. Nothing else in the project is web-reachable.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
define('APP_START', microtime(true));

require BASE_PATH . '/app/Support/Autoloader.php';

App\Support\Autoloader::register(BASE_PATH . '/app');

$kernel = new App\Support\Kernel();
$kernel->boot();

$request = App\Support\Request::capture();
$response = $kernel->handle($request);
$response->send();
