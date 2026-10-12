<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/_layout.php';
$customer = requireCustomer();
$error = null;
$contracts = $boletos = $statements = $incomeReports = [];
$documents = new CustomerDocuments();

try {
    $repository = new CustomerRepository(Database::connect(env('CLIENT_DB_NAME', 'cliente')));
    $contracts = $repository->contracts((int) $customer['code']);
    $boletos = $repository->openBoletos((int) $customer['code']);
    $statements = $repository->ownerStatements((int) $customer['code']);
    $incomeReports = $documents->incomeReports((string) $customer['cpf']);
} catch (Throwable $exception) {
    error_log('Painel cliente: ' . $exception->getMessage());
    $error = 'Não foi possível carregar seus documentos agora.';
}

customerHeader('Meus documentos', $customer);
?>
<main class="customer-dashboard">
    <section class="customer-welcome">
        <p class="eyebrow">Área do Cliente</p>
        <h1>Olá, <?= e($customer['name'] ?: 'cliente') ?>.</h1>
        <p>Aqui estão os contratos, boletos, extratos e informes associados ao seu cadastro.</p>
    </section>
    <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>

    <section class="customer-section">
        <div class="customer-section-heading"><div><p class="eyebrow">Financeiro</p><h2>Boletos disponíveis</h2></div><span><?= count($boletos) ?> encontrado(s)</span></div>
        <?php if ($boletos): ?>
        <div class="customer-table-wrap">
            <table class="customer-table">
                <thead><tr><th>Boleto</th><th>Contrato</th><th>Vencimento</th><th>Linha digitável</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($boletos as $boleto): ?>
                    <tr>
                        <td><?= e($boleto['num_boleto']) ?></td>
                        <td><?= e($boleto['cod_contrato']) ?></td>
                        <td><?= e(date('d/m/Y', strtotime((string) $boleto['data_vencimento']))) ?></td>
                        <td class="digit-line"><?= e($boleto['linha_digitavel'] ?: '—') ?></td>
                        <td><a class="table-action" href="/cliente/boleto.php?numero=<?= e($boleto['num_boleto']) ?>" target="_blank" rel="noopener">Abrir PDF</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?><div class="customer-empty">Nenhum boleto em aberto foi encontrado.</div><?php endif; ?>
    </section>

    <section class="customer-section">
        <div class="customer-section-heading"><div><p class="eyebrow">Proprietário</p><h2>Extratos</h2></div><span><?= count($statements) ?> encontrado(s)</span></div>
        <?php if ($statements): ?>
        <div class="customer-table-wrap">
            <table class="customer-table">
                <thead><tr><th>Extrato</th><th>Contrato</th><th>Processamento</th><th>Geração</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($statements as $statement): ?>
                    <tr>
                        <td><?= e($statement['cod_extrato']) ?></td>
                        <td><?= e($statement['cod_contrato']) ?></td>
                        <td><?= $statement['data_processo'] ? e(date('d/m/Y', strtotime((string) $statement['data_processo']))) : '—' ?></td>
                        <td><?= $statement['data_geracao'] ? e(date('d/m/Y', strtotime((string) $statement['data_geracao']))) : '—' ?></td>
                        <td><?php if ($documents->statementPath((int) $statement['cod_extrato'])): ?><a class="table-action" href="/cliente/extrato.php?codigo=<?= e($statement['cod_extrato']) ?>" target="_blank" rel="noopener">Abrir PDF</a><?php else: ?><span class="customer-unavailable">Indisponível</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?><div class="customer-empty">Nenhum extrato de proprietário foi encontrado.</div><?php endif; ?>
    </section>

    <section class="customer-section">
        <div class="customer-section-heading"><div><p class="eyebrow">Imposto de renda</p><h2>Informes de rendimentos</h2></div><span><?= count($incomeReports) ?> ano(s)</span></div>
        <?php if ($incomeReports): ?>
        <div class="customer-table-wrap">
            <table class="customer-table">
                <thead><tr><th>Ano-calendário</th><th>Contrato</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($incomeReports as $year => $reportContracts): ?>
                    <?php foreach ($reportContracts as $reportContract): ?>
                    <tr>
                        <td><?= e($year) ?></td>
                        <td><?= e($reportContract) ?></td>
                        <td><a class="table-action" href="/cliente/informe.php?ano=<?= e($year) ?>&amp;contrato=<?= e($reportContract) ?>" target="_blank" rel="noopener">Abrir PDF</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?><div class="customer-empty">Nenhum informe de rendimentos foi encontrado.</div><?php endif; ?>
    </section>

    <section class="customer-section">
        <div class="customer-section-heading"><div><p class="eyebrow">Cadastro</p><h2>Meus contratos</h2></div></div>
        <?php if ($contracts): ?><div class="contract-grid">
            <?php foreach ($contracts as $contract): ?><article><span>Contrato</span><strong><?= e($contract['cod_contrato']) ?></strong><small><?= (int) $contract['como_participa'] === 2 ? 'Locatário' : 'Participante' ?></small></article><?php endforeach; ?>
        </div><?php else: ?><div class="customer-empty">Nenhum contrato foi encontrado.</div><?php endif; ?>
    </section>
</main>
<?php customerFooter(); ?>
