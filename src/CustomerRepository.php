<?php

declare(strict_types=1);

final class CustomerRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findActiveByCpf(string $cpf): ?array
    {
        $formatted = formatCpfCnpj($cpf);
        if ($formatted === null) return null;

        $statement = $this->pdo->prepare(
            'SELECT id, cod_cliente, nome, email, senha, nivel, ativo, data, data_nova_senha
               FROM cliente
              WHERE ativo = 1 AND (cpf_real = :cpf OR usuario = :legacy_user)
              ORDER BY id LIMIT 1'
        );
        $statement->execute([
            'cpf' => $formatted,
            'legacy_user' => LegacyCustomerCipher::encode($formatted),
        ]);
        $customer = $statement->fetch();
        return $customer ?: null;
    }

    public function verifyPassword(array $customer, string $password): bool
    {
        $stored = strtolower(trim((string) ($customer['senha'] ?? '')));
        return strlen($stored) === 40 && hash_equals($stored, sha1($password));
    }

    public function email(array $customer): string
    {
        $stored = (string) ($customer['email'] ?? '');
        if (filter_var($stored, FILTER_VALIDATE_EMAIL)) return $stored;
        return LegacyCustomerCipher::decode($stored);
    }

    public function contracts(int $customerCode): array
    {
        $statement = $this->pdo->prepare(
            'SELECT cc.cod_contrato, cc.como_participa
               FROM cliente_contrato cc
              WHERE cc.cod_cliente = :customer
              ORDER BY cc.cod_contrato DESC'
        );
        $statement->execute(['customer' => $customerCode]);
        return $statement->fetchAll();
    }

    public function openBoletos(int $customerCode): array
    {
        $statement = $this->pdo->prepare(
            'SELECT b.cod_boleto, b.num_boleto, b.cod_contrato, b.data_vencimento,
                    b.data_recebimento, b.linha_digitavel
               FROM boleto b
               JOIN cliente_contrato cc ON cc.cod_contrato = b.cod_contrato
                AND cc.cod_cliente = :customer AND cc.como_participa = 2
              WHERE b.num_boleto IS NOT NULL
                AND b.data_recebimento IS NULL
              ORDER BY b.data_vencimento DESC, b.num_boleto DESC'
        );
        $statement->execute(['customer' => $customerCode]);
        return $statement->fetchAll();
    }

    public function authorizedBoleto(int $customerCode, int $number): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT b.cod_boleto, b.num_boleto, b.cod_contrato, b.data_vencimento
               FROM boleto b
               JOIN cliente_contrato cc ON cc.cod_contrato = b.cod_contrato
                AND cc.cod_cliente = :customer AND cc.como_participa = 2
              WHERE b.num_boleto = :number
              ORDER BY b.data_vencimento DESC LIMIT 1'
        );
        $statement->execute(['customer' => $customerCode, 'number' => $number]);
        $boleto = $statement->fetch();
        return $boleto ?: null;
    }

    public function updatePassword(int $id, string $password): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE cliente
                SET senha = :password, senha_real = NULL, data_nova_senha = LOCALTIMESTAMP
              WHERE id = :id AND ativo = 1'
        );
        $statement->execute(['password' => sha1($password), 'id' => $id]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('Cliente não atualizado.');
    }

    public function log(string $user, string $action, ?int $contract = null, ?int $boleto = null): void
    {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO logs (hora, ip, navegador, sistema_operacional, usuario, acao, contrato, boleto)
                 VALUES (LOCALTIMESTAMP, :ip, :browser, :os, :user, :action, :contract, :boleto)'
            );
            $statement->execute([
                'ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                'browser' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
                'os' => '',
                'user' => $user,
                'action' => $action,
                'contract' => $contract,
                'boleto' => $boleto,
            ]);
        } catch (Throwable) {
            // An audit failure must not make the customer portal unavailable.
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
