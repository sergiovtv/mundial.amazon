<?php

declare(strict_types=1);

/**
 * PDFs da Área do Cliente enviados pelo ERP via FTP:
 *   extratos/extrato<cod_extrato>.pdf
 *   informe_rendimentos/<grupo>/<cpf somente dígitos>/<ano>/contrato_<codcontrato>.pdf
 */
final class CustomerDocuments
{
    private readonly string $root;

    public function __construct(?string $root = null, private readonly string $incomeGroup = 'Mundial')
    {
        $this->root = rtrim($root ?? env('CLIENT_DOCUMENT_ROOT', '/var/lib/mundial/documentos'), '/');
    }

    public function statementPath(int $statement): ?string
    {
        return $this->existing($this->root . '/extratos/extrato' . $statement . '.pdf');
    }

    /** @return array<int, list<int>> ano => contratos, do ano mais recente para o mais antigo */
    public function incomeReports(string $cpf): array
    {
        $directory = $this->incomeDirectory($cpf);
        if ($directory === null) return [];

        $reports = [];
        foreach (glob($directory . '/[0-9][0-9][0-9][0-9]/contrato_*.pdf') ?: [] as $file) {
            if (!preg_match('#/(\d{4})/contrato_(\d+)\.pdf$#', $file, $match)) continue;
            $reports[(int) $match[1]][] = (int) $match[2];
        }

        krsort($reports);
        foreach ($reports as &$contracts) sort($contracts);
        return $reports;
    }

    public function incomeReportPath(string $cpf, int $year, int $contract): ?string
    {
        $directory = $this->incomeDirectory($cpf);
        if ($directory === null) return null;
        return $this->existing($directory . '/' . $year . '/contrato_' . $contract . '.pdf');
    }

    public function stream(string $file, string $downloadName): never
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $downloadName . '"');
        header('Content-Length: ' . (int) filesize($file));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    private function incomeDirectory(string $cpf): ?string
    {
        $digits = preg_replace('/\D+/', '', $cpf) ?? '';
        if (!in_array(strlen($digits), [11, 14], true)) return null;

        $group = str_replace(' ', '_', $this->incomeGroup);
        if (!preg_match('/^[A-Za-z0-9_]+$/', $group)) return null;

        $directory = $this->root . '/informe_rendimentos/' . $group . '/' . $digits;
        return is_dir($directory) ? $directory : null;
    }

    private function existing(string $file): ?string
    {
        return is_file($file) && is_readable($file) ? $file : null;
    }
}
