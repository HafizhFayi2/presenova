<?php

namespace App\Support\Core;

use Illuminate\Support\Facades\DB;

class Database
{
    private \PDO $conn;

    private string $error = '';

    public function __construct()
    {
        /** @var \PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $this->conn = $pdo;
        $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    public function getConnection(): \PDO
    {
        return $this->conn;
    }

    /**
     * @param array<int, mixed> $params
     */
    public function query(string $sql, array $params = []): \PDOStatement|false
    {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);

            return $stmt;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return false;
        }
    }

    public function lastInsertId(): string|false
    {
        try {
            return $this->conn->lastInsertId();
        } catch (\Throwable) {
            return false;
        }
    }

    public function beginTransaction(): bool
    {
        try {
            return $this->conn->beginTransaction();
        } catch (\Throwable) {
            return false;
        }
    }

    public function commit(): bool
    {
        try {
            return $this->conn->commit();
        } catch (\Throwable) {
            return false;
        }
    }

    public function rollBack(): bool
    {
        try {
            if ($this->conn->inTransaction()) {
                return $this->conn->rollBack();
            }
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    public function inTransaction(): bool
    {
        try {
            return $this->conn->inTransaction();
        } catch (\Throwable) {
            return false;
        }
    }
}

