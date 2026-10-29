<?php

declare(strict_types=1);

$dbHost = getenv('DB_HOST') ?: '';
$dbName = getenv('DB_NAME') ?: '';
$dbUser = getenv('DB_USER') ?: '';
$dbPass = getenv('DB_PASSWORD') ?: '';
$apiToken = getenv('API_TOKEN') ?: '';

if (
    $dbHost === '' ||
    $dbName === '' ||
    $dbUser === '' ||
    $apiToken === ''
) {
    error_log('CampusConnect API: required environment variables are missing.');
    throw new RuntimeException('API configuration is incomplete.');
}

$pdo = new PDO(
    "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
    $dbUser,
    $dbPass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

return [
    'pdo' => $pdo,
    'api_token' => $apiToken,
];
