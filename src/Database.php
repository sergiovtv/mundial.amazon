<?php

declare(strict_types=1);

final class Database
{
    public static function connect(?string $database = null): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;connect_timeout=5',
            env('DB_HOST'),
            env('DB_PORT', '5432'),
            $database ?? env('DB_NAME', 'imobi')
        );

        return new PDO($dsn, env('DB_USER'), env('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
