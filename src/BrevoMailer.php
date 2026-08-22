<?php

declare(strict_types=1);

final class BrevoMailer
{
    public function __construct(private readonly PDO $parameters)
    {
    }

    public function sendPassword(string $email, string $password): void
    {
        $settings = $this->settings();
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('E-mail inválido.');

        $html = '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#172126">'
            . '<h1 style="color:#bd2c2f">Mundial Imóveis</h1>'
            . '<p>Recebemos uma solicitação de recuperação da sua Área do Cliente.</p>'
            . '<p>Sua nova senha temporária é: <strong>' . e($password) . '</strong></p>'
            . '<p>Entre na área do cliente e altere a senha após o acesso.</p>'
            . '<p><a href="' . e(siteUrl('/cliente/')) . '">Acessar a Área do Cliente</a></p>'
            . '<p>Se você não solicitou esta alteração, fale com a Mundial.</p></body></html>';

        $payload = json_encode([
            'sender' => ['email' => $settings['BREVO_SENDER_EMAIL'], 'name' => $settings['BREVO_SENDER_NAME']],
            'to' => [['email' => $email]],
            'subject' => 'Mundial Imóveis - nova senha da Área do Cliente',
            'htmlContent' => $html,
            'tags' => ['recuperacao-senha'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $curl = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['accept: application/json', 'content-type: application/json', 'api-key: ' . $settings['BREVO_API_KEY']],
            CURLOPT_POSTFIELDS => $payload,
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false || $status !== 201) {
            error_log('Falha Brevo: HTTP ' . $status . ($error !== '' ? ' - ' . $error : ''));
            throw new RuntimeException('Não foi possível enviar o e-mail agora.');
        }
    }

    private function settings(): array
    {
        $names = ['BREVO_API_KEY', 'BREVO_SENDER_EMAIL', 'BREVO_SENDER_NAME'];
        $placeholders = implode(',', array_fill(0, count($names), '?'));
        $statement = $this->parameters->prepare(
            "SELECT nomeparametro, valorparametro FROM parametro WHERE nomeparametro IN ({$placeholders})"
        );
        $statement->execute($names);
        $settings = array_column($statement->fetchAll(), 'valorparametro', 'nomeparametro');
        $settings['BREVO_SENDER_NAME'] = trim((string) ($settings['BREVO_SENDER_NAME'] ?? '')) ?: 'Mundial Imóveis';
        if (trim((string) ($settings['BREVO_API_KEY'] ?? '')) === '' || !filter_var($settings['BREVO_SENDER_EMAIL'] ?? '', FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Brevo não configurado.');
        }
        return $settings;
    }
}
