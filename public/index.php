<?php

declare(strict_types=1);

use VendingMachine\Infrastructure\Http\HttpApplication;

require __DIR__ . '/../vendor/autoload.php';

$pdo = new PDO('sqlite:' . (getenv('DATABASE_PATH') ?: __DIR__ . '/../var/machines.sqlite'));

HttpApplication::create($pdo)->run();
