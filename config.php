<?php

declare(strict_types=1);

$databaseUrl = getenv('DATABASE_URL') ?: '';
$apiToken = getenv('API_TOKEN') ?: '';

if ($databaseUrl === '' || $apiToken === '') {
    error_log('CampusConnect API: required environment variables are missing.');
    throw new RuntimeException('API configuration is incomplete.');
}

$db = parse_url($databaseUrl);

if (
    !is_array($db) ||
    empty($db['host']) ||
    empty($db['user']) ||
    !isset($db['pass']) ||
    empty($db['path']) ||
    strlen($db['path']) < 2
) {
    error_log('CampusConnect API: DATABASE_URL is invalid.');
    throw new RuntimeException('Database configuration is invalid.');
}

$dbHost = $db['host'];
$dbPort = $db['port'] ?? 5432;
$dbName = substr($db['path'], 1);
$dbUser = urldecode($db['user']);
$dbPass = urldecode($db['pass']);

$pdo = new PDO(
    "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName};sslmode=require",
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
