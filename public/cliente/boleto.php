<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';
$customer = requireCustomer();
$number = filter_input(INPUT_GET, 'numero', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

try {
    if (!$number) throw new RuntimeException('Boleto inválido.');
    $repository = new CustomerRepository(Database::connect(env('CLIENT_DB_NAME', 'cliente')));
    $boleto = $repository->authorizedBoleto((int) $customer['code'], (int) $number);
    if (!$boleto) {
        $repository->log((string) $customer['cpf'], 'Tentativa de boleto não autorizado', null, (int) $number);
        http_response_code(404);
        throw new RuntimeException('Boleto não encontrado.');
    }
    $repository->log((string) $customer['cpf'], 'Boleto aberto', (int) $boleto['cod_contrato'], (int) $number);
    (new BoletoService())->output($boleto);
} catch (Throwable $exception) {
    error_log('Boleto cliente: ' . $exception->getMessage());
    if (http_response_code() < 400) http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Boleto indisponível</title><body style="font-family:Arial;padding:3rem"><h1>Boleto indisponível</h1><p>'
        . e($exception->getMessage()) . '</p><p><a href="/cliente/painel.php">Voltar</a></p></body></html>';
}
