<?php

declare(strict_types=1);

// Webhook de leads do Chaves na Mão (mesmo endereço do site antigo). O portal não envia
// credencial válida; o site antigo também não a conferia.
require_once __DIR__ . '/../../src/bootstrap.php';

try {
    [$status, $message] = (new PortalLeadReceiver(Database::connect()))->receive('chavesnamao', (string) file_get_contents('php://input'));
} catch (Throwable $exception) {
    error_log('Lead Chaves na Mão: ' . $exception->getMessage());
    [$status, $message] = [500, 'Falha ao gravar lead'];
}
http_response_code($status);
echo $message;
