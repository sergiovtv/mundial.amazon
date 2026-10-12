<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$city = (string) ($_GET['cidade'] ?? '');
$cityId = ctype_digit($city) && (int) $city > 0 ? (int) $city : null;

try {
    $repository = new PropertyRepository(Database::connect());
    echo json_encode($repository->neighborhoods($cityId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível carregar os bairros.']);
}
