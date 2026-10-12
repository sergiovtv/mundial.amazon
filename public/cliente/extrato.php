<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';
$customer = requireCustomer();
$code = filter_input(INPUT_GET, 'codigo', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

try {
    if (!$code) throw new RuntimeException('Extrato inválido.');
    $repository = new CustomerRepository(Database::connect(env('CLIENT_DB_NAME', 'cliente')));
    $statement = $repository->authorizedStatement((int) $customer['code'], (int) $code);
    if (!$statement) {
        $repository->log((string) $customer['cpf'], 'Tentativa de extrato não autorizado', null, (int) $code);
        http_response_code(404);
        throw new RuntimeException('Extrato não encontrado.');
    }

    $documents = new CustomerDocuments();
    $file = $documents->statementPath((int) $code);
    if ($file === null) {
        http_response_code(404);
        throw new RuntimeException('O PDF deste extrato ainda não está disponível.');
    }

    $repository->log((string) $customer['cpf'], 'Extrato aberto', (int) $statement['cod_contrato'], (int) $code);
    $documents->stream($file, 'extrato-' . (int) $code . '.pdf');
} catch (Throwable $exception) {
    error_log('Extrato cliente: ' . $exception->getMessage());
    if (http_response_code() < 400) http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Extrato indisponível</title><body style="font-family:Arial;padding:3rem"><h1>Extrato indisponível</h1><p>'
        . e($exception->getMessage()) . '</p><p><a href="/cliente/painel.php">Voltar</a></p></body></html>';
}
