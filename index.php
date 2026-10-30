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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');

    respond(405, [
        'status' => 'error',
        'message' => 'Only GET requests are allowed.'
    ]);
}

try {
    $configPath = __DIR__ . '/config.php';

    if (!is_file($configPath)) {
        respond(500, [
            'status' => 'error',
            'message' => 'API configuration is unavailable.'
        ]);
    }

    $config = require $configPath;

    if (
        !is_array($config) ||
        !isset($config['api_token'], $config['pdo']) ||
        !is_string($config['api_token']) ||
        !($config['pdo'] instanceof PDO)
    ) {
        respond(500, [
            'status' => 'error',
            'message' => 'API configuration is invalid.'
        ]);
    }

    // Authenticate API request.
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

    $pdo = $config['pdo'];

    // Identify the requested endpoint.
    $requestPath = parse_url(
        $_SERVER['REQUEST_URI'] ?? '/',
        PHP_URL_PATH
    );

    $basePath = '/api/v1/students';

    if ($requestPath === $basePath) {
        $studentNumber = null;
    } elseif (preg_match(
        '#^/api/v1/students/([^/]+)/?$#',
        $requestPath,
        $matches
    )) {
        $studentNumber = $matches[1];
    } else {
        // Also support ?student_number=...
        $studentNumber = $_GET['student_number'] ?? null;

        if ($studentNumber === null) {
            respond(404, [
                'status' => 'error',
                'message' => 'API endpoint not found.'
            ]);
        }
    }

    // Retrieve one student.
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
                  AND is_active IS TRUE
                LIMIT 1';

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'student_number' => $studentNumber
        ]);

        $student = $stmt->fetch();

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

    // Retrieve all active students.
    $sql = 'SELECT
                student_number,
                full_name,
                email,
                college,
                program,
                year_level,
                section
            FROM api_students
            WHERE is_active IS TRUE
            ORDER BY student_number ASC
            LIMIT 100';

    $stmt = $pdo->query($sql);

    respond(200, [
        'status' => 'success',
        'data' => $stmt->fetchAll(),
        'pagination' => [
            'per_page' => 100
        ]
    ]);

} catch (Throwable $e) {
    error_log(
        'CampusConnect API student retrieval failed: ' .
        get_class($e) . ': ' . $e->getMessage()
    );

    respond(500, [
        'status' => 'error',
        'message' => 'Unable to retrieve student records.'
    ]);
}
