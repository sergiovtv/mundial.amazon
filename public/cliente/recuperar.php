<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/_layout.php';
customerSession();

$message = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf()) {
        $error = 'A sessão expirou. Atualize a página e tente novamente.';
    } else {
        $cpf = (string) ($_POST['cpf'] ?? '');
        $generic = 'Se o CPF estiver cadastrado, enviaremos uma nova senha para o e-mail registrado.';
        try {
            $repository = new CustomerRepository(Database::connect(env('CLIENT_DB_NAME', 'cliente')));
            $customer = $repository->findActiveByCpf($cpf);
            if ($customer) {
                $lastRequest = !empty($customer['data_nova_senha']) ? strtotime((string) $customer['data_nova_senha']) : false;
                if ($lastRequest === false || time() - $lastRequest >= 300) {
                    $email = $repository->email($customer);
                    $temporaryPassword = bin2hex(random_bytes(4)) . strtoupper(substr(bin2hex(random_bytes(2)), 0, 2));
                    $pdo = $repository->pdo();
                    $pdo->beginTransaction();
                    try {
                        $repository->updatePassword((int) $customer['id'], $temporaryPassword);
                        (new BrevoMailer(Database::connect()))->sendPassword($email, $temporaryPassword);
                        $pdo->commit();
                        $repository->log(formatCpfCnpj($cpf) ?? '', 'Recuperação de senha enviada');
                    } catch (Throwable $exception) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        throw $exception;
                    }
                }
            }
            $message = $generic;
        } catch (Throwable $exception) {
            error_log('Recuperação cliente: ' . $exception->getMessage());
            $error = 'Não foi possível concluir a solicitação agora. Tente novamente mais tarde.';
        }
    }
}

customerHeader('Recuperar senha');
?>
<main class="customer-auth-page customer-auth-single">
    <section class="customer-card">
        <p class="eyebrow">Acesso</p>
        <h2>Recuperar senha</h2>
        <p>Informe o CPF ou CNPJ cadastrado. A nova senha será enviada pelo Brevo ao e-mail registrado.</p>
        <?php if ($message): ?><div class="form-success" role="status"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if (!$message): ?>
        <form method="post" class="customer-form">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <label>CPF ou CNPJ
                <input name="cpf" inputmode="numeric" autocomplete="username" required maxlength="18" value="<?= e($_POST['cpf'] ?? '') ?>">
            </label>
            <button type="submit">Enviar nova senha</button>
        </form>
        <?php endif; ?>
        <a class="customer-forgot" href="/cliente/">Voltar para o login</a>
    </section>
</main>
<?php customerFooter(); ?>
