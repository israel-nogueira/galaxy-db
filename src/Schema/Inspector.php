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
     * Construtor
     * 
     * @param PDO $connection Conexão PDO ativa
     */
    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Retorna lista de todas as tabelas do banco
     * 
     * @return array Lista de nomes de tabelas
     */
    public function getTables(): array
    {
        $query = 'SHOW TABLES';
        $stmt = $this->connection->query($query);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
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
        // Remove possíveis caracteres especiais mantendo apenas o nome da tabela
        $pattern = '/\b(\w+)\b/i';
        preg_match($pattern, $table, $matches);
        $tableName = $matches[1] ?? $table;

        $query = "SHOW COLUMNS FROM `{$tableName}`";
        $stmt = $this->connection->query($query);

        if (!$detailed) {
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna índices de uma tabela
     * 
     * @param string $table Nome da tabela
     * @return array Lista de índices
     */
    public function getIndexes(string $table): array
    {
        $database = getEnv('DB_DATABASE');
        
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
            'table' => $table
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna stored procedures do banco
     * 
     * @return array Lista de procedures
     */
    public function getProcedures(): array
    {
        $database = getEnv('DB_DATABASE');
        
        $query = "SHOW PROCEDURE STATUS WHERE Db = :database";
        $stmt = $this->connection->prepare($query);
        $stmt->execute(['database' => $database]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna functions do banco
     * 
     * @return array Lista de functions
     */
    public function getFunctions(): array
    {
        $database = getEnv('DB_DATABASE');
        
        $query = "SHOW FUNCTION STATUS WHERE Db = :database";
        $stmt = $this->connection->prepare($query);
        $stmt->execute(['database' => $database]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna triggers do banco
     * 
     * @return array Lista de triggers
     */
    public function getTriggers(): array
    {
        $database = getEnv('DB_DATABASE');
        
        $query = "SHOW TRIGGERS FROM `{$database}`";
        $stmt = $this->connection->query($query);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna a chave primária de uma tabela
     * 
     * @param string $table Nome da tabela
     * @return string|null Nome da coluna chave primária
     */
    public function getPrimaryKey(string $table): ?string
    {
        $columns = $this->getColumns($table, true);

        foreach ($columns as $column) {
            if ($column['Key'] === 'PRI') {
                return $column['Field'];
            }
        }

        return null;
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

        foreach ($columns as $col) {
            if ($col['Field'] === $column) {
                return $col;
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
            'table' => $table
        ]);

        return $stmt->fetchColumn() > 0;
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
        $query = "
            SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_NAME = :table 
              AND COLUMN_NAME = :column
        ";

        $stmt = $this->connection->prepare($query);
        $stmt->execute([
            'table' => $table,
            'column' => $column
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Retorna estatísticas da tabela
     * 
     * @param string $table Nome da tabela
     * @return array Estatísticas (tamanho, número de linhas, etc)
     */
    public function getTableStats(string $table): array
    {
        $database = getEnv('DB_DATABASE');
        
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
            'table' => $table
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Retorna o charset de uma tabela
     * 
     * @param string $table Nome da tabela
     * @return string|null Charset da tabela
     */
    public function getTableCharset(string $table): ?string
    {
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
            'table' => $table
        ]);

        return $stmt->fetchColumn() ?: null;
    }
}
