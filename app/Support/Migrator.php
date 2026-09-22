<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Applies the numbered .sql files in database/migrations, recording what has
 * run so a second pass is a no-op. Used by the console and by the web
 * installer, so both apply exactly the same schema in exactly the same order.
 */
final class Migrator
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * Opens a connection to the server without selecting a database, so the
     * database itself can be created before anything else happens.
     *
     * @param array{host:string,port:int,username:string,password:string,charset?:string} $config
     */
    public static function connectToServer(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $config['host'],
            $config['port'],
            $config['charset'] ?? 'utf8mb4',
        );

        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }

    /**
     * Creates the database if it is absent and selects it.
     *
     * The name is validated rather than escaped because it cannot be a bound
     * parameter: MySQL does not accept placeholders for identifiers.
     */
    public static function selectDatabase(PDO $pdo, string $database, string $charset = 'utf8mb4', string $collation = 'utf8mb4_unicode_ci'): void
    {
        if (!self::isValidDatabaseName($database)) {
            throw new RuntimeException('That database name is not valid. Use letters, digits and underscores.');
        }

        $pdo->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s', $database, $charset, $collation));
        $pdo->exec(sprintf('USE `%s`', $database));
    }

    public static function isValidDatabaseName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9_]{1,64}$/', $name) === 1;
    }

    /** @return array<int,string> The migrations applied by this call. */
    public function run(): array
    {
        $this->ensureTable();

        $applied = [];

        foreach ($this->db->select('SELECT filename FROM migrations') as $row) {
            $applied[(string) $row['filename']] = true;
        }

        $batch = (int) ($this->db->scalar('SELECT COALESCE(MAX(batch), 0) FROM migrations') ?? 0) + 1;
        $ran = [];

        foreach (self::files() as $file) {
            $name = basename($file);

            if (isset($applied[$name])) {
                continue;
            }

            foreach (self::statements((string) file_get_contents($file)) as $statement) {
                try {
                    $this->db->pdo()->exec($statement);
                } catch (PDOException $exception) {
                    throw new RuntimeException(
                        sprintf('Migration %s failed: %s', $name, $exception->getMessage()),
                        0,
                        $exception,
                    );
                }
            }

            $this->db->insert('migrations', ['filename' => $name, 'batch' => $batch]);
            $ran[] = $name;
        }

        return $ran;
    }

    public function pending(): int
    {
        try {
            $this->ensureTable();

            $applied = [];

            foreach ($this->db->select('SELECT filename FROM migrations') as $row) {
                $applied[(string) $row['filename']] = true;
            }
        } catch (\Throwable) {
            return count(self::files());
        }

        $pending = 0;

        foreach (self::files() as $file) {
            if (!isset($applied[basename($file)])) {
                $pending++;
            }
        }

        return $pending;
    }

    public function dropEverything(string $database): int
    {
        $tables = $this->db->select(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema',
            ['schema' => $database],
        );

        if ($tables === []) {
            return 0;
        }

        $this->db->execute('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            $this->db->execute('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $table['name']) . '`');
        }

        $this->db->execute('SET FOREIGN_KEY_CHECKS = 1');

        return count($tables);
    }

    private function ensureTable(): void
    {
        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                filename VARCHAR(190) NOT NULL,
                batch INT UNSIGNED NOT NULL DEFAULT 1,
                executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_migrations_filename (filename)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    /** @return array<int,string> */
    public static function files(): array
    {
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        sort($files);

        return $files;
    }

    /**
     * Splits a migration file into statements. Comment lines are dropped first;
     * the migrations deliberately contain no semicolons inside string literals.
     *
     * @return array<int,string>
     */
    public static function statements(string $sql): array
    {
        $clean = [];

        foreach (preg_split('/\R/', $sql) ?: [] as $line) {
            if (!str_starts_with(ltrim($line), '--')) {
                $clean[] = $line;
            }
        }

        $statements = [];

        foreach (explode(';', implode("\n", $clean)) as $statement) {
            $statement = trim($statement);

            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        return $statements;
    }
}
