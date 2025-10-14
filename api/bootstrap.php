<?php

declare(strict_types=1);

use HouzzHunt\Container\AppContainer;
use HouzzHunt\Support\AccessControl;

require_once __DIR__ . '/../bootstrap.php';

$pdo = hh_db();
$datamap = hh_datamap();

$container = new AppContainer($pdo, $datamap);

$auth = AccessControl::requireAuth();

return [$container, $auth];
