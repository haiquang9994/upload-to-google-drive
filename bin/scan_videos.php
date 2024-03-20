#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/load.php';

$path = $_SERVER['argv'][1] ?? null;

if (!$path) {
    console_log("Missing path!");
    exit;
}

if (!is_dir($path)) {
    console_log("Path is invalid!");
    exit;
}

function scan($path)
{
    $items = glob(rtrim($path, '/') . '/*');

    $files = [];

    foreach ($items as $item) {
        if (is_file($item)) {
            $files[] = $item;
        }
        if (is_dir($item)) {
            $files = array_merge($files, scan($item));
        }
    }

    return $files;
}

$videos = get_video();

$new_paths = glob(rtrim($path, '/') . '/*');

foreach ($new_paths as $new_path) {
    if (!array_key_exists($new_path, $videos)) {
        $files = scan($new_path);
        if (count($files) > 0) {
            $videos[$new_path] = false;
        }
    }
}

file_put_contents("video.json", json_encode($videos));
