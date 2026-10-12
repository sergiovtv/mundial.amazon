<?php

declare(strict_types=1);

/**
 * Recebe leads enviados por webhook pelos portais (Zap/Viva Real e Chaves na Mão) e grava
 * nas tabelas lidas pelo ERP, com as mesmas regras dos scripts do site antigo.
 */
final class PortalLeadReceiver
{
    private const COMPANY = 2;
    private const CHANNEL = 8;

    public const PORTALS = [
        'zap' => ['table' => 'atendimento_zap', 'source' => 29, 'requires_listing' => true],
        'chavesnamao' => ['table' => 'atendimento_chavesnamao', 'source' => 37, 'requires_listing' => false],
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{0: int, 1: string} código HTTP e mensagem */
    public function receive(string $portal, string $body): array
    {
        $config = self::PORTALS[$portal] ?? throw new InvalidArgumentException('Portal desconhecido.');
        $lead = json_decode($body, true);
        if (!is_array($lead)) return [400, 'JSON inválido'];

        $listing = trim((string) ($lead['clientListingId'] ?? ''));
        if ($listing === '' || strtolower($listing) === 'null' || $listing === 'IndicacaoNaMao') {
            if ($config['requires_listing']) return [400, 'Código do imóvel vazio'];
            $listing = '0';
        }

        $message = str_replace('<br>', "\n", $this->text($lead['message'] ?? '', 2000));
        $receivedAt = $this->receivedAt((string) ($lead['timestamp'] ?? ''));
        $row = [
            'date' => $receivedAt->format('Y-m-d'),
            'time' => $receivedAt->format('H:i:s'),
            'name' => $this->text($lead['name'] ?? '', 255),
            'phone' => $this->phone((string) ($lead['phoneNumber'] ?? '')),
            'email' => $this->text($lead['email'] ?? '', 255),
            'message' => $message,
            'operation' => $this->operation($listing, $message),
            'listing' => $this->text($listing, 255),
        ];

        $table = $config['table'];
        $duplicate = $this->pdo->prepare(
            "SELECT 1 FROM {$table}
              WHERE dataatendimento = :date AND horaatendimento = :time AND atendidonome = :name
                AND atendidofone1 = :phone AND atendidoemail = :email AND observacao = :message
                AND codoperacao = :operation AND codimovel = :listing
              LIMIT 1"
        );
        $duplicate->execute($row);
        if ($duplicate->fetchColumn()) return [200, 'Lead já recebido'];

        $insert = $this->pdo->prepare(
            "INSERT INTO {$table} (dataatendimento, horaatendimento, atendidonome, atendidofone1, atendidoemail,
                                   observacao, codoperacao, codempresa, atendimentogenerico, canal, fonte, codimovel)
             VALUES (:date, :time, :name, :phone, :email, :message, :operation, :company, 'S', :channel, :source, :listing)"
        );
        $insert->execute($row + ['company' => self::COMPANY, 'channel' => self::CHANNEL, 'source' => $config['source']]);
        return [200, 'Lead recebido'];
    }

    private function receivedAt(string $timestamp): DateTimeImmutable
    {
        $zone = new DateTimeZone('America/Sao_Paulo');
        try {
            return ($timestamp !== '' ? new DateTimeImmutable($timestamp) : new DateTimeImmutable())->setTimezone($zone);
        } catch (Exception) {
            return new DateTimeImmutable('now', $zone);
        }
    }

    /** 4 = locação, 5 = venda; pelo sufixo do código do imóvel ou pelo texto da mensagem. */
    private function operation(string $listing, string $message): int
    {
        $listing = strtoupper($listing);
        if (str_contains($listing, 'MA') || str_contains($listing, 'AL')) return 4;
        if (str_contains($listing, 'MV') || str_contains($listing, 'VE')) return 5;

        $text = mb_strtoupper($message);
        foreach (['ALUGUEL', 'LOCAR', 'ALUGA', 'LOCACAO', 'LOCAÇÃO', 'ALUGO'] as $word) {
            if (str_contains($text, $word)) return 4;
        }
        foreach (['VENDE', 'VENDI', 'VENDA', 'COMPRA', 'COMPRO', 'FINANCIA'] as $word) {
            if (str_contains($text, $word)) return 5;
        }
        return 4;
    }

    private function phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) > 11 && $digits[0] === '0') $digits = substr($digits, 1);
        $digits = substr($digits, 0, 11);
        if (strlen($digits) < 2) return '';

        $area = substr($digits, 0, 2);
        $number = substr($digits, 2);
        $split = strlen($number) === 9 ? 5 : 4;
        return '(' . $area . ')' . ($number === '' ? '' : ' ' . substr($number, 0, $split)
            . (strlen($number) > $split ? '-' . substr($number, $split) : ''));
    }

    private function text(mixed $value, int $limit): string
    {
        $text = mb_scrub(trim((string) $value), 'UTF-8');
        return mb_substr($text, 0, $limit);
    }
}
