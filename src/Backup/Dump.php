<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Backup;

use PDO;
use IsraelNogueira\galaxyDB\Schema\Inspector;

/**
 * -------------------------------------------------------------------------
 * Class Dump
 * -------------------------------------------------------------------------
 * 
 * Responsável por gerar dumps SQL de estrutura e dados.
 * Separado de GeneralBase para melhor organização.
 * 
 * @package IsraelNogueira\galaxyDB\Backup
 * @author Israel Nogueira <israel@feats.com>
 * @license GPL-3.0-or-later
 * @copyright 2023 Israel Nogueira
 * -------------------------------------------------------------------------
 */
class Dump
{
    /**
     * Conexão PDO
     */
    private PDO $connection;

    /**
     * Inspector para obter informações do schema
     */
    private Inspector $inspector;

    /**
     * Tamanho máximo de memória para dump
     */
    private int $maxMemoryLimit = 256;

    /**
     * Modo streaming (para grandes tabelas)
     */
    private bool $streamMode = false;

    /**
     * Charset do dump
     */
    private string $charset = 'utf8mb4';

    /**
     * Construtor
     * 
     * @param PDO $connection Conexão PDO ativa
     */
    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->inspector = new Inspector($connection);
    }

    /**
     * Retorna apenas estrutura das tabelas (CREATE TABLE)
     * 
     * @param string|array $tables Tabela(s) ou '*' para todas
     * @param bool $addDropTable Adiciona DROP TABLE IF EXISTS
     * @return string SQL com CREATE TABLE
     */
    public function getStructure(string|array $tables = '*', bool $addDropTable = true): string
    {
        $result = '';
        $this->connection->exec("SET NAMES '{$this->charset}'");

        // Resolve tabelas
        $tables = $this->resolveTables($tables);

        // Para cada tabela, pega CREATE TABLE
        foreach ($tables as $table) {
            $safeTable = $this->sanitizeTableName($table);
            
            if ($addDropTable) {
                $result .= "\n\nDROP TABLE IF EXISTS `{$safeTable}`;\n";
            }

            $stmt = $this->connection->query("SHOW CREATE TABLE `{$safeTable}`");
            $row = $stmt->fetch(PDO::FETCH_NUM);

            if ($row && isset($row[1])) {
                $result .= "\n\n" . $row[1] . ";\n\n";
            }
        }

        return $result;
    }

    /**
     * Retorna apenas dados (INSERT INTO) em modo streaming
     * 
     * @param string|array $tables Tabela(s) ou '*' para todas
     * @param array $ignore Tabelas a ignorar
     * @param int $batchSize Tamanho do batch para INSERT
     * @return string SQL com INSERT INTO
     */
    public function getData(string|array $tables = '*', array $ignore = [], int $batchSize = 100): string
    {
        $result = '';

        try {
            $this->connection->exec("SET FOREIGN_KEY_CHECKS=0");

            // Resolve tabelas
            $tables = $this->resolveTables($tables);

            // Para cada tabela
            foreach ($tables as $table) {
                if (in_array($table, $ignore)) {
                    continue;
                }

                $safeTable = $this->sanitizeTableName($table);
                
                // Verifica se tabela existe
                if (!$this->inspector->tableExists($safeTable)) {
                    continue;
                }

                // Obtém colunas da tabela
                $columns = $this->inspector->getColumns($safeTable);
                $columnsStr = '`' . implode('`, `', $columns) . '`';

                $stmt = $this->connection->query("SELECT * FROM `{$safeTable}`");
                
                // Se modo streaming, usa fetch progressivo
                if ($this->streamMode) {
                    $result .= $this->getDataStream($stmt, $safeTable, $columnsStr, $batchSize);
                } else {
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (empty($rows)) {
                        continue;
                    }

                    $result .= $this->formatInsertData($rows, $safeTable, $columnsStr, $batchSize);
                }
            }

            $this->connection->exec("SET FOREIGN_KEY_CHECKS=1");

            return $result;

        } catch (\PDOException $e) {
            throw new \Exception("Erro ao gerar dump: " . $e->getMessage());
        }
    }

    /**
     * Processa dados em streaming (para tabelas grandes)
     * 
     * @param \PDOStatement $stmt
     * @param string $table
     * @param string $columnsStr
     * @param int $batchSize
     * @return string
     */
    private function getDataStream(\PDOStatement $stmt, string $table, string $columnsStr, int $batchSize): string
    {
        $result = '';
        $batch = [];
        $rowCount = 0;
        $totalRows = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $batch[] = $row;
            $rowCount++;
            $totalRows++;

            if ($rowCount >= $batchSize) {
                $result .= $this->formatInsertData($batch, $table, $columnsStr, $batchSize);
                $batch = [];
                $rowCount = 0;
                
                // Libera memória
                if ($totalRows % 1000 === 0) {
                    $this->memoryCleanup();
                }
            }
        }

        // Último batch
        if (!empty($batch)) {
            $result .= $this->formatInsertData($batch, $table, $columnsStr, $batchSize);
        }

        return $result;
    }

    /**
     * Formata dados para INSERT
     * 
     * @param array $rows
     * @param string $table
     * @param string $columnsStr
     * @param int $batchSize
     * @return string
     */
    private function formatInsertData(array $rows, string $table, string $columnsStr, int $batchSize): string
    {
        if (empty($rows)) {
            return '';
        }

        $result = "INSERT INTO `{$table}` ({$columnsStr}) VALUES ";

        $valueSets = [];
        foreach ($rows as $row) {
            foreach ($row as $key => $value) {
                if (is_null($value)) {
                    $row[$key] = 'NULL';
                } elseif (is_bool($value)) {
                    $row[$key] = $value ? '1' : '0';
                } elseif (is_int($value) || is_float($value)) {
                    $row[$key] = (string) $value;
                } else {
                    $row[$key] = $this->connection->quote((string) $value);
                }
            }
            $valueSets[] = '(' . implode(',', $row) . ')';
        }

        $result .= implode(',', $valueSets) . ";\n\n";

        return $result;
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
    public function getFull(
        string|array $tables = '*', 
        array $ignore = [],
        bool $addDropTable = true,
        int $batchSize = 100
    ): string {
        $dump = "-- GalaxyDB Full Dump\n";
        $dump .= "-- Gerado em: " . date('Y-m-d H:i:s') . "\n";
        $dump .= "-- Charset: {$this->charset}\n";
        $dump .= "-- Server: " . $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n\n";
        $dump .= "SET NAMES '{$this->charset}';\n";
        $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        $dump .= $this->getStructure($tables, $addDropTable);
        $dump .= $this->getData($tables, $ignore, $batchSize);
        
        $dump .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

        return $dump;
    }

    /**
     * Salva dump em arquivo
     * 
     * @param string $filepath Caminho do arquivo
     * @param string $content Conteúdo do dump
     * @param bool $compress Se deve comprimir (gzip)
     * @return bool True se salvo com sucesso
     */
    public function saveToFile(string $filepath, string $content, bool $compress = false): bool
    {
        $dir = dirname($filepath);
        
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new \Exception("Não foi possível criar o diretório: {$dir}");
            }
        }

        // Verifica permissão de escrita
        if (!is_writable($dir)) {
            throw new \Exception("Diretório não é gravável: {$dir}");
        }

        if ($compress) {
            $filepath .= '.gz';
            $content = gzencode($content, 9);
        }

        return file_put_contents($filepath, $content) !== false;
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
    public function export(
        string $filepath,
        string|array $tables = '*',
        array $ignore = [],
        bool $compress = false,
        int $batchSize = 100
    ): bool {
        // Para arquivos grandes, ativa streaming
        $this->streamMode = true;
        
        $content = $this->getFull($tables, $ignore, true, $batchSize);
        return $this->saveToFile($filepath, $content, $compress);
    }

    /**
     * Exporta em modo streaming (para arquivos muito grandes)
     * 
     * @param string $filepath Caminho do arquivo
     * @param string|array $tables Tabela(s) ou '*'
     * @param array $ignore Tabelas a ignorar
     * @param int $batchSize Tamanho do batch
     * @return bool True se exportado com sucesso
     */
    public function exportStream(
        string $filepath,
        string|array $tables = '*',
        array $ignore = [],
        int $batchSize = 1000
    ): bool {
        $dir = dirname($filepath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($filepath, 'w');
        if ($handle === false) {
            throw new \Exception("Não foi possível abrir o arquivo para escrita: {$filepath}");
        }

        try {
            // Cabeçalho
            fwrite($handle, "-- GalaxyDB Full Dump (Streaming)\n");
            fwrite($handle, "-- Gerado em: " . date('Y-m-d H:i:s') . "\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

            $this->connection->exec("SET NAMES '{$this->charset}'");

            // Resolve tabelas
            $tables = $this->resolveTables($tables);

            // Estrutura
            foreach ($tables as $table) {
                if (in_array($table, $ignore)) {
                    continue;
                }

                $safeTable = $this->sanitizeTableName($table);
                
                fwrite($handle, "\n\nDROP TABLE IF EXISTS `{$safeTable}`;\n");
                
                $stmt = $this->connection->query("SHOW CREATE TABLE `{$safeTable}`");
                $row = $stmt->fetch(PDO::FETCH_NUM);
                if ($row && isset($row[1])) {
                    fwrite($handle, "\n\n" . $row[1] . ";\n\n");
                }

                // Dados em streaming
                $columns = $this->inspector->getColumns($safeTable);
                $columnsStr = '`' . implode('`, `', $columns) . '`';
                
                $dataStmt = $this->connection->query("SELECT * FROM `{$safeTable}`");
                $batch = [];
                $rowCount = 0;

                while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                    $batch[] = $row;
                    $rowCount++;

                    if ($rowCount >= $batchSize) {
                        fwrite($handle, $this->formatInsertData($batch, $safeTable, $columnsStr, $batchSize));
                        $batch = [];
                        $rowCount = 0;
                        
                        // Libera buffer
                        flush();
                    }
                }

                // Último batch
                if (!empty($batch)) {
                    fwrite($handle, $this->formatInsertData($batch, $safeTable, $columnsStr, $batchSize));
                }
            }

            fwrite($handle, "\nSET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);

            return true;

        } catch (\Exception $e) {
            fclose($handle);
            throw $e;
        }
    }

    /**
     * Resolve lista de tabelas
     * 
     * @param string|array $tables
     * @return array
     */
    private function resolveTables(string|array $tables): array
    {
        if ($tables === '*') {
            return $this->inspector->getTables();
        }
        
        return is_array($tables) ? $tables : array_map('trim', explode(',', $tables));
    }

    /**
     * Sanitiza nome de tabela
     * 
     * @param string $table
     * @return string
     */
    private function sanitizeTableName(string $table): string
    {
        // Remove caracteres inválidos
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        return $clean ?: $table;
    }

    /**
     * Limpa memória
     */
    private function memoryCleanup(): void
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Define limite de memória
     * 
     * @param int $mb
     * @return self
     */
    public function setMemoryLimit(int $mb): self
    {
        $this->maxMemoryLimit = max(64, $mb);
        ini_set('memory_limit', $this->maxMemoryLimit . 'M');
        return $this;
    }

    /**
     * Define charset do dump
     * 
     * @param string $charset
     * @return self
     */
    public function setCharset(string $charset): self
    {
        $this->charset = $charset;
        return $this;
    }

    /**
     * Habilita modo streaming
     * 
     * @param bool $enabled
     * @return self
     */
    public function setStreamMode(bool $enabled = true): self
    {
        $this->streamMode = $enabled;
        return $this;
    }

    /**
     * Obtém tamanho aproximado do dump
     * 
     * @param string|array $tables
     * @return int Tamanho em bytes
     */
    public function estimateSize(string|array $tables = '*'): int
    {
        $totalSize = 0;
        $tables = $this->resolveTables($tables);

        foreach ($tables as $table) {
            $safeTable = $this->sanitizeTableName($table);
            $stats = $this->inspector->getTableStats($safeTable);
            if (!empty($stats)) {
                $totalSize += (int) ($stats['data_size'] ?? 0);
                $totalSize += (int) ($stats['index_size'] ?? 0);
            }
        }

        return $totalSize;
    }

    /**
     * Gera dump com filtro de colunas
     * 
     * @param string $table
     * @param array $columns Colunas a incluir
     * @param array $where Condições WHERE
     * @return string
     */
    public function getFilteredData(string $table, array $columns, array $where = []): string
    {
        $safeTable = $this->sanitizeTableName($table);
        $columnsStr = '`' . implode('`, `', $columns) . '`';
        
        $whereClause = '';
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $key => $value) {
                $conditions[] = "`{$key}` = " . $this->connection->quote((string) $value);
            }
            $whereClause = ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql = "SELECT {$columnsStr} FROM `{$safeTable}`{$whereClause}";
        $stmt = $this->connection->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return '';
        }

        return $this->formatInsertData($rows, $safeTable, $columnsStr, 100);
    }
}