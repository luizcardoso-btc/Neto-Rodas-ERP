<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

App\Env::load(dirname(__DIR__) . '/.env');
date_default_timezone_set('America/Bahia');
