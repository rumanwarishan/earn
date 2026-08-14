<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Request;
use App\Core\Session;

Session::start();

$request = Request::capture();

$router = require dirname(__DIR__) . '/routes/web.php';
$router->dispatch($request);
