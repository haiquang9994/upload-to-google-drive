#!/usr/bin/env php
<?php

use RedisQueue\Client as RedisQueueClient;

require dirname(__DIR__) . '/load.php';

$lock_file = ROOT_PATH . '/upload.lock';
if (is_file($lock_file)) {
    return;
}

$client = new RedisQueueClient();

$client->push('backup_video_queue', [
    'cmd' => 'upload',
]);
