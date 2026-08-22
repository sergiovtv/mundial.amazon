<?php

declare(strict_types=1);

final class BoletoService
{
    public function __construct(private readonly PDO $parameters)
    {
    }

    public function output(array $boleto): never
    {
        $number = (int) $boleto['num_boleto'];
        $root = rtrim(env('CLIENT_DOCUMENT_ROOT', '/var/lib/mundial/documentos'), '/');
        $file = $root . '/boletos/boleto' . $number . '.pdf';

        if (is_file($file) && is_readable($file)) {
            $this->streamFile($file, $number);
        }

        $code = (int) ($boleto['cod_boleto'] ?? 0);
        if ($code <= 0) throw new RuntimeException('Boleto sem PDF ou identificador para emissão.');

        $baseUrl = rtrim(env('BOLETO_API_BASE_URL', 'http://177.200.69.64:9090/sistema/api/site_api.php'), '?');
        $query = http_build_query([
            'cod_boleto' => $code,
            'chave' => $this->apiKey(),
            'empresa' => env('BOLETO_API_COMPANY', 'sm'),
            'rotina' => env('BOLETO_API_ROUTINE', 'pdf_boleto_sicredi'),
        ]);
        $curl = curl_init($baseUrl . '?' . $query);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 30]);
        $pdf = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if (!is_string($pdf) || !str_starts_with($pdf, '%PDF') || $status >= 400) {
            throw new RuntimeException('Não foi possível emitir o boleto agora.');
        }

        $this->headers($number, strlen($pdf));
        echo $pdf;
        exit;
    }

    private function apiKey(): string
    {
        $statement = $this->parameters->prepare(
            'SELECT valorparametro FROM parametro WHERE nomeparametro = :name LIMIT 1'
        );
        $statement->execute(['name' => 'BOLETO_API_KEY']);
        $key = trim((string) $statement->fetchColumn());
        if ($key === '') {
            throw new RuntimeException('API de boleto não configurada.');
        }
        return $key;
    }

    private function streamFile(string $file, int $number): never
    {
        $this->headers($number, (int) filesize($file));
        readfile($file);
        exit;
    }

    private function headers(int $number, int $length): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="boleto-' . $number . '.pdf"');
        header('Content-Length: ' . $length);
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
    }
}
