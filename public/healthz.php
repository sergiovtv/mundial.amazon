<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

try {
    Database::connect()->query('SELECT 1')->fetchColumn();
    echo json_encode(['status' => 'ok', 'database' => 'connected'], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'database' => 'unavailable'], JSON_THROW_ON_ERROR);
}
