<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $data): void
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

// Allow GET requests only.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');

    respond(405, [
        'status' => 'error',
        'message' => 'Only GET requests are allowed.'
    ]);
}

// Load API configuration and database connection.
$configPath = __DIR__ . '/config.php';

if (!is_file($configPath)) {
    error_log('CampusConnect API: config.php is missing.');

    respond(500, [
        'status' => 'error',
        'message' => 'API configuration is unavailable.'
    ]);
}

try {
    $config = require $configPath;
} catch (Throwable $e) {
    error_log('CampusConnect API: configuration or database connection failed.');

    respond(500, [
        'status' => 'error',
        'message' => 'API configuration could not be loaded.'
    ]);
}

if (
    !is_array($config) ||
    !isset($config['api_token'], $config['pdo']) ||
    !is_string($config['api_token']) ||
    $config['api_token'] === '' ||
    !($config['pdo'] instanceof PDO)
) {
    respond(500, [
        'status' => 'error',
        'message' => 'API configuration is invalid.'
    ]);
}

// Authenticate the client using a Bearer Token.
$authorization = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

if (
    !preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches) ||
    !hash_equals($config['api_token'], $matches[1])
) {
    header('WWW-Authenticate: Bearer');

    respond(401, [
        'status' => 'error',
        'message' => 'Unauthorized.'
    ]);
}

try {
    $pdo = $config['pdo'];

    // Optional student lookup by 10-digit student number.
    $studentNumber = $_GET['student_number'] ?? null;

    if ($studentNumber !== null) {
        if (
            !is_string($studentNumber) ||
            !preg_match('/^[0-9]{10}$/', $studentNumber)
        ) {
            respond(400, [
                'status' => 'error',
                'message' => 'Student number must contain exactly 10 digits.'
            ]);
        }

        $sql = 'SELECT
                    student_number,
                    full_name,
                    email,
                    college,
                    program,
                    year_level,
                    section
                FROM api_students
                WHERE student_number = :student_number
                  AND is_active = 1
                LIMIT 1';

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'student_number' => $studentNumber
        ]);

        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            respond(404, [
                'status' => 'error',
                'message' => 'Student record not found.'
            ]);
        }

        respond(200, [
            'status' => 'success',
            'data' => $student
        ]);
    }

    // Return active student records only.
    $sql = 'SELECT
                student_number,
                full_name,
                email,
                college,
                program,
                year_level,
                section
            FROM api_students
            WHERE is_active = 1
            ORDER BY student_number ASC
            LIMIT 100';

    $stmt = $pdo->query($sql);

    respond(200, [
        'status' => 'success',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'pagination' => [
            'per_page' => 100
        ]
    ]);

} catch (Throwable $e) {
    error_log('CampusConnect API: student retrieval failed.');

    respond(500, [
        'status' => 'error',
        'message' => 'Unable to retrieve student records.'
    ]);
}
