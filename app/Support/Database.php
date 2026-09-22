<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Thin PDO wrapper. Every method takes bound parameters — there is no code path
 * in the application that concatenates user input into SQL.
 */
final class Database
{
    private static ?Database $instance = null;

    private PDO $pdo;

    private int $transactionDepth = 0;

    private function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function instance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self(self::connect());
        }

        return self::$instance;
    }

    /** Lets the console bind an already-open connection (used by migrations). */
    public static function swap(PDO $pdo): void
    {
        self::$instance = new self($pdo);
    }

    private static function connect(): PDO
    {
        $config = Config::get('database');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'],
        );

        try {
            $pdo = new PDO($dsn, (string) $config['username'], (string) $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);

            // The application stores UTC throughout. Pinning the session zone
            // makes CURRENT_TIMESTAMP column defaults agree with the
            // UTC_TIMESTAMP() comparisons used in queries, on MySQL and
            // MariaDB alike, whatever the server is configured to.
            $pdo->exec("SET time_zone = '+00:00'");

            return $pdo;
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param array<string|int,mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);

        foreach ($bindings as $key => $value) {
            $parameter = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');

            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };

            $statement->bindValue($parameter, is_bool($value) ? (int) $value : $value, $type);
        }

        $statement->execute();

        return $statement;
    }

    /**
     * @param array<string|int,mixed> $bindings
     * @return array<int,array<string,mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->statement($sql, $bindings)->fetchAll();
    }

    /**
     * @param array<string|int,mixed> $bindings
     * @return array<string,mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->statement($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int,mixed> $bindings
     */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        $value = $this->statement($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $columns)),
            implode(', ', $placeholders),
        );

        $this->statement($sql, $data);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $bindings
     */
    public function update(string $table, array $data, string $where, array $bindings = []): int
    {
        if ($data === []) {
            return 0;
        }

        $assignments = [];
        $values = [];

        foreach ($data as $column => $value) {
            $assignments[] = sprintf('`%s` = :set_%s', $column, $column);
            $values['set_' . $column] = $value;
        }

        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $assignments), $where);

        return $this->statement($sql, array_merge($values, $bindings))->rowCount();
    }

    /**
     * @param array<string,mixed> $bindings
     */
    public function delete(string $table, string $where, array $bindings = []): int
    {
        return $this->statement(sprintf('DELETE FROM `%s` WHERE %s', $table, $where), $bindings)->rowCount();
    }

    /**
     * @param array<string|int,mixed> $bindings
     */
    public function execute(string $sql, array $bindings = []): int
    {
        return $this->statement($sql, $bindings)->rowCount();
    }

    /**
     * Nested calls reuse the outermost transaction via savepoints so services
     * can compose without caring who opened it.
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();

            return $result;
        } catch (\Throwable $exception) {
            $this->rollBack();

            throw $exception;
        }
    }

    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT trans' . $this->transactionDepth);
        }

        $this->transactionDepth++;
    }

    public function commit(): void
    {
        $this->transactionDepth--;

        if ($this->transactionDepth === 0) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec('RELEASE SAVEPOINT trans' . $this->transactionDepth);
        }
    }

    public function rollBack(): void
    {
        $this->transactionDepth--;

        if ($this->transactionDepth === 0) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } else {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT trans' . $this->transactionDepth);
        }
    }

    /**
     * Builds a `IN (...)` placeholder list with unique named parameters.
     *
     * @param array<int,int|string> $values
     * @return array{0:string,1:array<string,mixed>}
     */
    public static function inClause(array $values, string $prefix = 'in'): array
    {
        $placeholders = [];
        $bindings = [];

        foreach (array_values($values) as $index => $value) {
            $name = $prefix . $index;
            $placeholders[] = ':' . $name;
            $bindings[$name] = $value;
        }

        return [implode(', ', $placeholders), $bindings];
    }
}
