#!/usr/bin/env php
<?php

use Google\Client;
use Google\Service\Drive;

require dirname(__DIR__) . '/load.php';

$client = new Client();
$client->setApplicationName('Google Drive API PHP Quickstart');
$client->setScopes(Drive::DRIVE);
$client->setAuthConfig(ROOT_PATH . '/google_credentials.json');
$client->setAccessType('offline');
$client->setPrompt('select_account consent');
$authUrl = $client->createAuthUrl();
printf("Open the following link in your browser:\n%s\n", $authUrl);
print('Enter verification code: ');
$authCode = trim(fgets(STDIN));
$accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
if ($accessToken) {
    $token_path = ROOT_PATH . '/token.json';
    file_put_contents($token_path, json_encode($accessToken));
    printf("Login successful.\n");
} else {
    printf("Login failed.\n");
}
