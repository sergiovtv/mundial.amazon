<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';
$customer = requireCustomer();
$year = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2100]]);
$contract = filter_input(INPUT_GET, 'contrato', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

try {
    if (!$year || !$contract) throw new RuntimeException('Informe inválido.');

    // A pasta é escolhida pelo CPF da sessão, então o cliente só acessa os próprios informes.
    $documents = new CustomerDocuments();
    $file = $documents->incomeReportPath((string) $customer['cpf'], (int) $year, (int) $contract);
    $repository = new CustomerRepository(Database::connect(env('CLIENT_DB_NAME', 'cliente')));
    if ($file === null) {
        $repository->log((string) $customer['cpf'], 'Informe de rendimentos não encontrado ' . (int) $year, (int) $contract);
        http_response_code(404);
        throw new RuntimeException('Informe de rendimentos não encontrado.');
    }

    $repository->log((string) $customer['cpf'], 'Informe de rendimentos aberto ' . (int) $year, (int) $contract);
    $documents->stream($file, 'informe-rendimentos-' . (int) $year . '-contrato-' . (int) $contract . '.pdf');
} catch (Throwable $exception) {
    error_log('Informe cliente: ' . $exception->getMessage());
    if (http_response_code() < 400) http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Informe indisponível</title><body style="font-family:Arial;padding:3rem"><h1>Informe indisponível</h1><p>'
        . e($exception->getMessage()) . '</p><p><a href="/cliente/painel.php">Voltar</a></p></body></html>';
}
