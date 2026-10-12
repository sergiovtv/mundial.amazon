<?php

declare(strict_types=1);

// Webhook de leads do Zap/Viva Real (mesmo endereço do site antigo). Autenticação por Basic Auth.
require_once __DIR__ . '/../../src/bootstrap.php';

$expected = (string) getenv('ZAP_LEAD_KEY');
$received = (string) ($_SERVER['PHP_AUTH_PW'] ?? '');
if ($expected === '' || !hash_equals($expected, $received)) {
    http_response_code(400);
    exit('chave inválida');
}

try {
    [$status, $message] = (new PortalLeadReceiver(Database::connect()))->receive('zap', (string) file_get_contents('php://input'));
} catch (Throwable $exception) {
    error_log('Lead Zap: ' . $exception->getMessage());
    [$status, $message] = [500, 'Falha ao gravar lead'];
}
http_response_code($status);
echo $message;
