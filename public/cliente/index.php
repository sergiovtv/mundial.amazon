<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/_layout.php';
customerSession();

if (!empty($_SESSION['customer'])) {
    header('Location: /cliente/painel.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf()) {
        $error = 'A sessão expirou. Atualize a página e tente novamente.';
    } else {
        $cpf = (string) ($_POST['cpf'] ?? '');
        $password = (string) ($_POST['senha'] ?? '');
        try {
            $repository = new CustomerRepository(Database::connect(env('CLIENT_DB_NAME', 'cliente')));
            $customer = $repository->findActiveByCpf($cpf);
            if (!$customer || !$repository->verifyPassword($customer, $password)) {
                $repository->log(formatCpfCnpj($cpf) ?? 'CPF inválido', 'Falha no login');
                usleep(350000);
                $error = 'CPF ou senha inválidos.';
            } else {
                session_regenerate_id(true);
                $_SESSION['customer'] = [
                    'id' => (int) $customer['id'],
                    'code' => (int) $customer['cod_cliente'],
                    'name' => trim((string) $customer['nome']),
                    'cpf' => formatCpfCnpj($cpf),
                ];
                $_SESSION['csrf'] = bin2hex(random_bytes(24));
                $repository->log((string) $_SESSION['customer']['cpf'], 'Login realizado');
                header('Location: /cliente/painel.php');
                exit;
            }
        } catch (Throwable $exception) {
            error_log('Login cliente: ' . $exception->getMessage());
            $error = 'Não foi possível acessar a área do cliente agora.';
        }
    }
}

customerHeader('Área do Cliente');
?>
<main class="customer-auth-page">
    <section class="customer-auth-copy">
        <p class="eyebrow">Área do Cliente</p>
        <h1>Seus contratos e documentos em um só lugar.</h1>
        <p>Acesse boletos e informações vinculadas ao seu CPF com segurança.</p>
    </section>
    <section class="customer-card">
        <h2>Entrar</h2>
        <p>Use o CPF ou CNPJ cadastrado e sua senha.</p>
        <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="customer-form">
            <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
            <label>CPF ou CNPJ
                <input name="cpf" data-mask="cpf-cnpj" inputmode="numeric" autocomplete="username" required maxlength="18" value="<?= e($_POST['cpf'] ?? '') ?>" placeholder="CPF ou CNPJ">
            </label>
            <label>Senha
                <input type="password" name="senha" autocomplete="current-password" required maxlength="72">
            </label>
            <button type="submit">Entrar na área do cliente</button>
        </form>
        <a class="customer-forgot" href="/cliente/recuperar.php">Esqueci minha senha</a>
    </section>
</main>
<?php customerFooter(); ?>
