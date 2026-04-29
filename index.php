<?php

declare(strict_types=1);

require_once __DIR__ . '/core/Bootstrap.php';

try {
    $app = Nano\Bootstrap::boot(__DIR__);
    $app->run();
} catch (Nano\SetupException $e) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    echo Nano\Setup::renderRequired($e);
}
