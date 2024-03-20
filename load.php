<?php

require __DIR__ . '/helpers.php';
require __DIR__ . '/vendor/autoload.php';

define('ROOT_PATH', __DIR__);

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
