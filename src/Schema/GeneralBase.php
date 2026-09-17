<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Schema;

use PDO;
use PDOException;
use IsraelNogueira\galaxyDB\Schema\Inspector;
use IsraelNogueira\galaxyDB\Backup\Dump;

/**
 * -------------------------------------------------------------------------
 * Trait GeneralBase
 * -------------------------------------------------------------------------
 * 
 * Retorna dados gerais da base de dados.
 * Delega operações para Inspector e Dump.
 * 
 * @package IsraelNogueira\galaxyDB\Schema
 * @author Israel Nogueira <israel@feats.com>
 * @license GPL-3.0-or-later
 * @copyright 2023 Israel Nogueira
 * -------------------------------------------------------------------------
 */
trait GeneralBase
{
    /**
     * Instância do Inspector (criada sob demanda)
     */
    private ?Inspector $inspectorInstance = null;

    /**
     * Instância do Dump (criada sob demanda)
     */
    private ?Dump $dumpInstance = null;

    /**
     * Cache para resultados de schema
     */
    private array $schemaCache = [];

    /**
     * Obtém instância do Inspector
     * 
     * @return Inspector
     */
    private function getInspector(): Inspector
    {
        if ($this->inspectorInstance === null) {
            $this->inspectorInstance = new Inspector($this->connection);
        }
        return $this->inspectorInstance;
    }

    /**
     * Obtém instância do Dump
     * 
     * @return Dump
     */
    private function getDump(): Dump
    {
        if ($this->dumpInstance === null) {
            $this->dumpInstance = new Dump($this->connection);
        }
        return $this->dumpInstance;
    }

    /**
     * Retorna apenas a estrutura das tabelas (CREATE TABLE)
     * 
     * @param string|array $tables Tabela(s) específica(s) ou '*' para todas
     * @param bool $addDropTable Adiciona DROP TABLE IF EXISTS
     * @return string SQL com CREATE TABLE das tabelas
     */
    public function getDB_Tables(string|array $tables = '*', bool $addDropTable = true): string
    {
        $dump = $this->getDump();
        return $dump->getStructure($tables, $addDropTable);
    }

    /**
     * Retorna apenas os dados das tabelas (INSERT INTO)
     * 
     * @param string|array $tables Tabela(s) específica(s) ou '*' para todas
     * @param array $ignore Tabelas a ignorar no dump
     * @param int $batchSize Tamanho do batch para INSERT
     * @return string SQL com INSERT INTO dos dados
     */
    public function getDB_Data(string|array $tables = '*', array $ignore = [], int $batchSize = 100): string
    {
        $dump = $this->getDump();
        return $dump->getData($tables, $ignore, $batchSize);
    }

    /**
     * Retorna dump completo (estrutura + dados)
     * 
     * @param string|array $tables Tabela(s) ou '*' para todas
     * @param array $ignore Tabelas a ignorar
     * @param bool $addDropTable Adiciona DROP TABLE
     * @param int $batchSize Tamanho do batch
     * @return string SQL completo
     */
    public function getDB_Full(
        string|array $tables = '*', 
        array $ignore = [],
        bool $addDropTable = true,
        int $batchSize = 100
    ): string {
        $dump = $this->getDump();
        return $dump->getFull($tables, $ignore, $addDropTable, $batchSize);
    }

    /**
     * Exporta dump completo para arquivo
     * 
     * @param string $filepath Caminho do arquivo
     * @param string|array $tables Tabela(s) ou '*'
     * @param array $ignore Tabelas a ignorar
     * @param bool $compress Se deve comprimir
     * @param int $batchSize Tamanho do batch
     * @return bool True se exportado com sucesso
     */
    public function exportDB_Full(
        string $filepath,
        string|array $tables = '*',
        array $ignore = [],
        bool $compress = false,
        int $batchSize = 100
    ): bool {
        $dump = $this->getDump();
        return $dump->export($filepath, $tables, $ignore, $compress, $batchSize);
    }

    /**
     * Exporta dump em modo streaming (para arquivos muito grandes)
     * 
     * @param string $filepath Caminho do arquivo
     * @param string|array $tables Tabela(s) ou '*'
     * @param array $ignore Tabelas a ignorar
     * @param int $batchSize Tamanho do batch
     * @return bool True se exportado com sucesso
     */
    public function exportDB_Stream(
        string $filepath,
        string|array $tables = '*',
        array $ignore = [],
        int $batchSize = 1000
    ): bool {
        $dump = $this->getDump();
        return $dump->exportStream($filepath, $tables, $ignore, $batchSize);
    }

    /**
     * Retorna índices de uma tabela
     * 
     * @param string $tableName Nome da tabela
     * @return array Lista de índices
     */
    public function getIndexes(string $tableName): array
    {
        $inspector = $this->getInspector();
        return $inspector->getIndexes($tableName);
    }

    /**
     * Retorna colunas de uma tabela
     * 
     * @param string $table Nome da tabela
     * @param bool $type Se true, retorna detalhes completos
     * @return array Lista de colunas
     */
    public function showDBColumns(string $table, bool $type = false): array
    {
        $inspector = $this->getInspector();
        return $inspector->getColumns($table, $type);
    }

    /**
     * Retorna stored procedures do banco
     * 
     * @param string $schema Schema específico (opcional)
     * @return array Lista de procedures
     */
    public function showProcedures(string $schema = null): array
    {
        $inspector = $this->getInspector();
        return $inspector->getProcedures($schema);
    }

    /**
     * Retorna functions do banco
     * 
     * @param string $schema Schema específico (opcional)
     * @return array Lista de functions
     */
    public function showFunctions(string $schema = null): array
    {
        $inspector = $this->getInspector();
        return $inspector->getFunctions($schema);
    }

    /**
     * Retorna triggers do banco
     * 
     * @param string $schema Schema específico (opcional)
     * @return array Lista de triggers
     */
    public function showTriggers(string $schema = null): array
    {
        $inspector = $this->getInspector();
        return $inspector->getTriggers($schema);
    }

    /**
     * Retorna lista de tabelas do banco
     * 
     * @param string $schema Schema específico (opcional)
     * @return array Lista de nomes de tabelas
     */
    public function showDBTables(string $schema = null): array
    {
        $inspector = $this->getInspector();
        return $inspector->getTables($schema);
    }

    /**
     * Retorna a chave primária de uma tabela
     * 
     * @param string $table Nome da tabela
     * @return string|null Nome da coluna chave primária
     */
    public function getPrimaryKey(string $table): ?string
    {
        $inspector = $this->getInspector();
        return $inspector->getPrimaryKey($table);
    }

    /**
     * Retorna estatísticas da tabela
     * 
     * @param string $table Nome da tabela
     * @return array Estatísticas
     */
    public function getTableStats(string $table): array
    {
        $inspector = $this->getInspector();
        return $inspector->getTableStats($table);
    }

    /**
     * Retorna foreign keys de uma tabela
     * 
     * @param string $table Nome da tabela
     * @return array Lista de foreign keys
     */
    public function getForeignKeys(string $table): array
    {
        $inspector = $this->getInspector();
        return $inspector->getForeignKeys($table);
    }

    /**
     * Verifica se uma tabela ou coluna existe
     * 
     * @param string|null $table Nome da tabela (usa $this->tableClass se null)
     * @param string|null $column Nome da coluna (opcional)
     * @return bool True se existir
     */
    public function verify(?string $table = null, ?string $column = null): bool
    {
        $inspector = $this->getInspector();
        
        $tableName = $table ?? $this->tableClass ?? null;
        
        if ($tableName === null) {
            return false;
        }

        // Verifica apenas tabela
        if ($column === null) {
            return $inspector->tableExists($tableName);
        }

        // Verifica tabela e coluna
        return $inspector->columnExists($tableName, $column);
    }

    /**
     * Verifica se uma tabela existe (alias)
     * 
     * @param string $table Nome da tabela
     * @return bool
     */
    public function tableExists(string $table): bool
    {
        $inspector = $this->getInspector();
        return $inspector->tableExists($table);
    }

    /**
     * Verifica se uma coluna existe (alias)
     * 
     * @param string $table Nome da tabela
     * @param string $column Nome da coluna
     * @return bool
     */
    public function columnExists(string $table, string $column): bool
    {
        $inspector = $this->getInspector();
        return $inspector->columnExists($table, $column);
    }

    /**
     * Retorna informações completas do banco
     * 
     * @return array
     */
    public function getDatabaseInfo(): array
    {
        $inspector = $this->getInspector();
        return $inspector->getDatabaseInfo();
    }

    /**
     * Retorna tamanho total do banco
     * 
     * @return int Tamanho em bytes
     */
    public function getDatabaseSize(): int
    {
        $inspector = $this->getInspector();
        return $inspector->getDatabaseSize();
    }

    /**
     * Retorna o charset de uma tabela
     * 
     * @param string $table Nome da tabela
     * @return string|null
     */
    public function getTableCharset(string $table): ?string
    {
        $inspector = $this->getInspector();
        return $inspector->getTableCharset($table);
    }

    /**
     * Lista todas as views do banco
     * 
     * @param string $schema Schema específico (opcional)
     * @return array
     */
    public function showViews(string $schema = null): array
    {
        $database = $schema ?? getEnv('DB_DATABASE');
        $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $query = "SELECT viewname FROM pg_views WHERE schemaname = 'public'";
            $stmt = $this->connection->query($query);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } elseif ($driver === 'sqlite') {
            $query = "SELECT name FROM sqlite_master WHERE type='view'";
            $stmt = $this->connection->query($query);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } else {
            // MySQL
            $query = "SHOW FULL TABLES IN `{$database}` WHERE Table_type = 'VIEW'";
            $stmt = $this->connection->query($query);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    }

    /**
     * Obtém a definição de uma view
     * 
     * @param string $view Nome da view
     * @return string|null
     */
    public function getViewDefinition(string $view): ?string
    {
        $safeView = preg_replace('/[^a-zA-Z0-9_]/', '', $view);
        $database = getEnv('DB_DATABASE');
        $driver = $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'pgsql') {
            $query = "SELECT view_definition FROM information_schema.views WHERE table_name = :view";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(['view' => $safeView]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['view_definition'] ?? null;
        } elseif ($driver === 'sqlite') {
            $query = "SELECT sql FROM sqlite_master WHERE type='view' AND name = :view";
            $stmt = $this->connection->prepare($query);
            $stmt->execute(['view' => $safeView]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['sql'] ?? null;
        } else {
            // MySQL
            $query = "SHOW CREATE VIEW `{$safeView}`";
            $stmt = $this->connection->query($query);
            $row = $stmt->fetch(PDO::FETCH_NUM);
            return $row[1] ?? null;
        }
    }

    /**
     * Limpa cache do schema
     * 
     * @return self
     */
    public function clearSchemaCache(): self
    {
        $this->schemaCache = [];
        if ($this->inspectorInstance !== null) {
            $this->inspectorInstance->clearCache();
        }
        return $this;
    }

    /**
     * Define TTL do cache do schema
     * 
     * @param int $seconds
     * @return self
     */
    public function setSchemaCacheTTL(int $seconds): self
    {
        $inspector = $this->getInspector();
        $inspector->setCacheTTL($seconds);
        return $this;
    }

    /**
     * Verifica se a conexão está ativa
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

    /**
     * Obtém versão do banco de dados
     * 
     * @return string
     */
    public function getDatabaseVersion(): string
    {
        return $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * Obtém nome do driver
     * 
     * @return string
     */
    public function getDatabaseDriver(): string
    {
        return $this->connection->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}