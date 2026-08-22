<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
    http_response_code(413);
    echo json_encode(['error' => 'Requisição muito grande.']);
    exit;
}

try {
    $input = json_decode((string) file_get_contents('php://input'), true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new InvalidArgumentException('Avaliação inválida.');

    $source = (string) ($input['source'] ?? '');
    $photoId = filter_var($input['photoId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $reaction = filter_var($input['reaction'] ?? null, FILTER_VALIDATE_INT);
    if ($photoId === false || !in_array($reaction, [-1, 1], true)) {
        throw new InvalidArgumentException('Avaliação inválida.');
    }

    $repository = new PropertyRepository(Database::connect());
    $score = $repository->recordPhotoReaction($source, (int) $photoId, anonymousPhotoVisitorHash(), (int) $reaction);
    echo json_encode(['ok' => true, 'score' => $score], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException|JsonException $exception) {
    http_response_code(422);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(503);
    $message = env('APP_DEBUG', 'false') === 'true' ? $exception->getMessage() : 'Não foi possível registrar a avaliação agora.';
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
}
