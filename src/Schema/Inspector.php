<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Schema;

use PDO;

/**
 * -------------------------------------------------------------------------
 * Class Inspector
 * -------------------------------------------------------------------------
 * 
 * Responsável por inspecionar a estrutura do banco de dados.
 * Retorna informações sobre tabelas, colunas, índices, procedures, etc.
 * 
 * @package IsraelNogueira\galaxyDB\Schema
 * @author Israel Nogueira <israel@feats.com>
 * @license GPL-3.0-or-later
 * @copyright 2023 Israel Nogueira
 * -------------------------------------------------------------------------
 */
class Inspector
{
    /**
     * Conexão PDO com o banco de dados
     * 
     * @var PDO
     */
    private PDO $connection;

    /**
     * Driver do banco de dados
     */
    private string $driver;

    /**
     * Cache de esquema
     */
    private array $schemaCache = [];

    /**
     * Tempo de vida do cache em segundos
     */
    private int $cacheTTL = 300;

    /**
     * Construtor
     * 
     * @param PDO $connection Conexão PDO ativa
     */
    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Retorna lista de todas as tabelas do banco
     * 
     * @param string $schema Schema específico (opcional)
     * @return array Lista de nomes de tabelas
     */
    public function getTables(string $schema = null): array
    {
        $cacheKey = 'tables_' . ($schema ?? 'default');
        
        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        $database = $schema ?? getEnv('DB_DATABASE');

        $query = match ($this->driver) {
            'pgsql' => "SELECT tablename FROM pg_tables WHERE schemaname = 'public'",
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'",
            default => "SHOW TABLES"
        };

        $stmt = $this->connection->query($query);
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $this->schemaCache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Retorna colunas de uma tabela
     * 
     * @param string $table Nome da tabela
     * @param bool $detailed Se true, retorna detalhes completos
     * @return array Lista de colunas
     */
    public function getColumns(string $table, bool $detailed = false): array
    {
        $cacheKey = 'columns_' . $table . '_' . ($detailed ? 'detailed' : 'simple');
        
        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        $safeTable = $this->sanitizeIdentifier($table);

        if ($this->driver === 'pgsql') {
            $query = "
                SELECT 
                    column_name,
                    data_type,
                    is_nullable,
                    column_default,
                    character_maximum_length,
                    numeric_precision,
                    numeric_scale
                FROM information_schema.columns
                WHERE table_name = :table
                ORDER BY ordinal_position
            ";
            
            $stmt = $this->connection->prepare($query);
            $stmt->execute(['table' => $safeTable]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!$detailed) {
                $result = array_column($result, 'column_name');
            }
        } elseif ($this->driver === 'sqlite') {
            $query = "PRAGMA table_info({$safeTable})";
            $stmt = $this->connection->query($query);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!$detailed) {
                $result = array_column($result, 'name');
            }
        } else {
            // MySQL e outros
            $query = "SHOW COLUMNS FROM `{$safeTable}`";
            $stmt = $this->connection->query($query);

            if (!$detailed) {
                $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $this->schemaCache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Retorna índices de uma tabela
     * 
     * @param string $table Nome da tabela
     * @return array Lista de índices
     */
    public function getIndexes(string $table): array
    {
        $cacheKey = 'indexes_' . $table;
        
        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        $safeTable = $this->sanitizeIdentifier($table);
        $database = getEnv('DB_DATABASE');

        if ($this->driver === 'pgsql') {
            $query = "
                SELECT 
                    indexname as INDEX_NAME,
                    indexdef as INDEX_DEF
                FROM pg_indexes
                WHERE tablename = :table
                ORDER BY indexname
            ";
            
            $stmt = $this->connection->prepare($query);
            $stmt->execute(['table' => $safeTable]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($this->driver === 'sqlite') {
            $query = "PRAGMA index_list({$safeTable})";
            $stmt = $this->connection->query($query);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // MySQL
            $query = "
                SELECT 
                    INDEX_NAME,
                    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS COLUMN_LIST,
                    NON_UNIQUE,
                    INDEX_TYPE
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = :database
                  AND TABLE_NAME = :table
                GROUP BY INDEX_NAME, NON_UNIQUE, INDEX_TYPE
            ";

            $stmt = $this->connection->prepare($query);
            $stmt->execute([
                'database' => $database,
                'table' => $safeTable
            ]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->schemaCache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Retorna stored procedures do banco
     * 
     * @param string $schema Schema específico (opcional)
     * @return array Lista de procedures
     */
    public function getProcedures(string $schema = null): array
    {
        $cacheKey = 'procedures_' . ($schema ?? 'default');
        
        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        $database = $schema ?? getEnv('DB_DATABASE');

        if ($this->driver === 'pgsql') {
            $query = "
                SELECT 
                    proname as Name,
                    'FUNCTION' as Type
                FROM pg_proc
                WHERE proname NOT LIKE 'pg_%'
                  AND proname NOT LIKE 'oid%'
                ORDER BY proname
            ";
            $stmt = $this->connection->query($query);
        } elseif ($this->driver === 'sqlite') {
            // SQLite não tem procedures
            return [];
        } else {
            // MySQL
            $query = "SHOW PROCEDURE STATUS WHERE Db = :database";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(['database' => $database]);
        }

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->schemaCache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Retorna functions do banco
     * 
     * @param string $schema Schema específico (opcional)
     * @return array Lista de functions
     */
    public function getFunctions(string $schema = null): array
    {
        $cacheKey = 'functions_' . ($schema ?? 'default');
        
        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        $database = $schema ?? getEnv('DB_DATABASE');

        if ($this->driver === 'pgsql') {
            $query = "
                SELECT 
                    proname as Name,
                    'FUNCTION' as Type
                FROM pg_proc
                WHERE proname NOT LIKE 'pg_%'
                  AND proname NOT LIKE 'oid%'
                  AND proname NOT LIKE 'plpgsql%'
                ORDER BY proname
            ";
            $stmt = $this->connection->query($query);
        } elseif ($this->driver === 'sqlite') {
            return [];
        } else {
            // MySQL
            $query = "SHOW FUNCTION STATUS WHERE Db = :database";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(['database' => $database]);
        }

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->schemaCache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Retorna triggers do banco
     * 
     * @param string $schema Schema específico (opcional)
     * @return array Lista de triggers
     */
    public function getTriggers(string $schema = null): array
    {
        $cacheKey = 'triggers_' . ($schema ?? 'default');
        
        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        $database = $schema ?? getEnv('DB_DATABASE');

        if ($this->driver === 'pgsql') {
            $query = "
                SELECT 
                    tgname as Trigger,
                    relname as Table
                FROM pg_trigger t
                JOIN pg_class c ON t.tgrelid = c.oid
                WHERE NOT tgisinternal
                ORDER BY tgname
            ";
            $stmt = $this->connection->query($query);
        } elseif ($this->driver === 'sqlite') {
            $query = "SELECT name FROM sqlite_master WHERE type='trigger'";
            $stmt = $this->connection->query($query);
        } else {
            // MySQL
            $query = "SHOW TRIGGERS FROM `{$database}`";
            $stmt = $this->connection->query($query);
        }

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->schemaCache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Retorna a chave primária de uma tabela
     * 
     * @param string $table Nome da tabela
     * @return string|null Nome da coluna chave primária
     */
    public function getPrimaryKey(string $table): ?string
    {
        $cacheKey = 'pk_' . $table;
        
        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        $safeTable = $this->sanitizeIdentifier($table);

        if ($this->driver === 'pgsql') {
            $query = "
                SELECT a.attname as column_name
                FROM pg_index i
                JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                WHERE i.indrelid = :table::regclass
                  AND i.indisprimary
                LIMIT 1
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(['table' => $safeTable]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $result = $row['column_name'] ?? null;
        } elseif ($this->driver === 'sqlite') {
            $query = "PRAGMA table_info({$safeTable})";
            $stmt = $this->connection->query($query);
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($columns as $col) {
                if ($col['pk'] == 1) {
                    $result = $col['name'];
                    break;
                }
            }
            $result = $result ?? null;
        } else {
            // MySQL
            $columns = $this->getColumns($safeTable, true);
            foreach ($columns as $column) {
                if ($column['Key'] === 'PRI') {
                    $result = $column['Field'];
                    break;
                }
            }
            $result = $result ?? null;
        }

        $this->schemaCache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Retorna informações detalhadas sobre uma coluna específica
     * 
     * @param string $table Nome da tabela
     * @param string $column Nome da coluna
     * @return array|null Informações da coluna
     */
    public function getColumnInfo(string $table, string $column): ?array
    {
        $columns = $this->getColumns($table, true);
        
        if ($this->driver === 'pgsql' || $this->driver === 'sqlite') {
            foreach ($columns as $col) {
                $name = $col['column_name'] ?? $col['name'] ?? null;
                if ($name === $column) {
                    return $col;
                }
            }
        } else {
            // MySQL
            foreach ($columns as $col) {
                if (($col['Field'] ?? '') === $column) {
                    return $col;
                }
            }
        }

        return null;
    }

    /**
     * Verifica se uma tabela existe
     * 
     * @param string $table Nome da tabela
     * @return bool True se existir
     */
    public function tableExists(string $table): bool
    {
        $cacheKey = 'exists_table_' . $table;
        
        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        $safeTable = $this->sanitizeIdentifier($table);

        if ($this->driver === 'pgsql') {
            $query = "
                SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = 'public'
                  AND table_name = :table
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(['table' => $safeTable]);
        } elseif ($this->driver === 'sqlite') {
            $query = "
                SELECT COUNT(*) 
                FROM sqlite_master 
                WHERE type='table' 
                  AND name = :table
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(['table' => $safeTable]);
        } else {
            // MySQL
            $database = $this->connection->query('SELECT DATABASE()')->fetchColumn();
            $query = "
                SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = :database 
                  AND table_name = :table
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute([
                'database' => $database,
                'table' => $safeTable
            ]);
        }

        $result = $stmt->fetchColumn() > 0;
        $this->schemaCache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Verifica se uma coluna existe em uma tabela
     * 
     * @param string $table Nome da tabela
     * @param string $column Nome da coluna
     * @return bool True se existir
     */
    public function columnExists(string $table, string $column): bool
    {
        $cacheKey = 'exists_column_' . $table . '_' . $column;
        
        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        $safeTable = $this->sanitizeIdentifier($table);
        $safeColumn = $this->sanitizeIdentifier($column);

        if ($this->driver === 'pgsql') {
            $query = "
                SELECT COUNT(*) 
                FROM information_schema.columns 
                WHERE table_name = :table 
                  AND column_name = :column
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(['table' => $safeTable, 'column' => $safeColumn]);
        } elseif ($this->driver === 'sqlite') {
            $query = "PRAGMA table_info({$safeTable})";
            $stmt = $this->connection->query($query);
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
            $result = in_array($safeColumn, $columns);
            $this->schemaCache[$cacheKey] = $result;
            return $result;
        } else {
            // MySQL
            $query = "
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = :table 
                  AND COLUMN_NAME = :column
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute([
                'table' => $safeTable,
                'column' => $safeColumn
            ]);
        }

        $result = $stmt->rowCount() > 0;
        $this->schemaCache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Retorna estatísticas da tabela
     * 
     * @param string $table Nome da tabela
     * @return array Estatísticas (tamanho, número de linhas, etc)
     */
    public function getTableStats(string $table): array
    {
        $cacheKey = 'stats_' . $table;
        
        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        $safeTable = $this->sanitizeIdentifier($table);
        $database = getEnv('DB_DATABASE');

        if ($this->driver === 'pgsql') {
            $query = "
                SELECT 
                    reltuples as row_count,
                    pg_relation_size(:table) as data_size,
                    pg_indexes_size(:table) as index_size,
                    pg_total_relation_size(:table) as total_size
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(['table' => $safeTable]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } elseif ($this->driver === 'sqlite') {
            $query = "SELECT COUNT(*) as row_count FROM {$safeTable}";
            $stmt = $this->connection->query($query);
            $rowCount = $stmt->fetchColumn();
            
            $result = [
                'row_count' => (int) $rowCount,
                'data_size' => filesize($database) ?? 0,
                'index_size' => 0,
                'total_size' => filesize($database) ?? 0
            ];
        } else {
            // MySQL
            $query = "
                SELECT 
                    table_rows as row_count,
                    data_length as data_size,
                    index_length as index_size,
                    (data_length + index_length) as total_size
                FROM information_schema.tables
                WHERE table_schema = :database
                  AND table_name = :table
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute([
                'database' => $database,
                'table' => $safeTable
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $this->schemaCache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Retorna o charset de uma tabela
     * 
     * @param string $table Nome da tabela
     * @return string|null Charset da tabela
     */
    public function getTableCharset(string $table): ?string
    {
        $cacheKey = 'charset_' . $table;
        
        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        $safeTable = $this->sanitizeIdentifier($table);

        if ($this->driver === 'pgsql') {
            $query = "SHOW server_encoding";
            $stmt = $this->connection->query($query);
            $result = $stmt->fetchColumn();
        } elseif ($this->driver === 'sqlite') {
            $result = 'UTF-8';
        } else {
            // MySQL
            $database = getEnv('DB_DATABASE');
            $query = "
                SELECT table_collation
                FROM information_schema.tables
                WHERE table_schema = :database
                  AND table_name = :table
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute([
                'database' => $database,
                'table' => $safeTable
            ]);
            $result = $stmt->fetchColumn() ?: null;
        }

        $this->schemaCache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Sanitiza identificador
     * 
     * @param string $identifier
     * @return string
     */
    private function sanitizeIdentifier(string $identifier): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $identifier);
    }

    /**
     * Limpa cache do schema
     * 
     * @param string|null $key Chave específica ou null para limpar tudo
     * @return self
     */
    public function clearCache(?string $key = null): self
    {
        if ($key !== null) {
            unset($this->schemaCache[$key]);
        } else {
            $this->schemaCache = [];
        }
        return $this;
    }

    /**
     * Define TTL do cache
     * 
     * @param int $seconds
     * @return self
     */
    public function setCacheTTL(int $seconds): self
    {
        $this->cacheTTL = max(0, $seconds);
        return $this;
    }

    /**
     * Obtém informações completas do banco
     * 
     * @return array
     */
    public function getDatabaseInfo(): array
    {
        return [
            'driver' => $this->driver,
            'version' => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION),
            'database' => getEnv('DB_DATABASE'),
            'tables' => $this->getTables(),
            'total_tables' => count($this->getTables()),
            'total_size' => $this->getDatabaseSize()
        ];
    }

    /**
     * Obtém tamanho total do banco
     * 
     * @return int Tamanho em bytes
     */
    public function getDatabaseSize(): int
    {
        $total = 0;
        $tables = $this->getTables();
        
        foreach ($tables as $table) {
            $stats = $this->getTableStats($table);
            $total += (int) ($stats['total_size'] ?? 0);
        }
        
        return $total;
    }

    /**
     * Obtém todas as foreign keys de uma tabela
     * 
     * @param string $table
     * @return array
     */
    public function getForeignKeys(string $table): array
    {
        $cacheKey = 'fk_' . $table;
        
        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        $safeTable = $this->sanitizeIdentifier($table);
        $database = getEnv('DB_DATABASE');

        if ($this->driver === 'pgsql') {
            $query = "
                SELECT
                    conname as constraint_name,
                    a.attname as column_name,
                    c.relname as referenced_table,
                    b.attname as referenced_column
                FROM pg_constraint pc
                JOIN pg_class c ON pc.confrelid = c.oid
                JOIN pg_attribute a ON a.attrelid = pc.conrelid AND a.attnum = ANY(pc.conkey)
                JOIN pg_attribute b ON b.attrelid = pc.confrelid AND b.attnum = ANY(pc.confkey)
                WHERE pc.conrelid = :table::regclass
                  AND pc.contype = 'f'
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(['table' => $safeTable]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($this->driver === 'sqlite') {
            $query = "PRAGMA foreign_key_list({$safeTable})";
            $stmt = $this->connection->query($query);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // MySQL
            $query = "
                SELECT 
                    CONSTRAINT_NAME,
                    COLUMN_NAME,
                    REFERENCED_TABLE_NAME,
                    REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = :database
                  AND TABLE_NAME = :table
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ";
            $stmt = $this->connection->prepare($query);
            $stmt->execute([
                'database' => $database,
                'table' => $safeTable
            ]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->schemaCache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Verifica se a conexão é válida
     * 
     * @return bool
     */
    public function isConnected(): bool
    {
        try {
            $this->connection->query('SELECT 1');
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }
}