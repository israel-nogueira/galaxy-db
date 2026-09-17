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
     * @return string SQL com CREATE TABLE
     */
    public function getStructure(string|array $tables = '*'): string
    {
        $result = '';
        $this->connection->exec("SET NAMES 'utf8'");

        // Resolve tabelas
        if ($tables === '*') {
            $tables = $this->inspector->getTables();
        } else {
            $tables = is_array($tables) ? $tables : explode(',', $tables);
        }

        // Para cada tabela, pega CREATE TABLE
        foreach ($tables as $table) {
            $stmt = $this->connection->query("SHOW CREATE TABLE `{$table}`");
            $row = $stmt->fetch(PDO::FETCH_NUM);

            if ($row && isset($row[1])) {
                $result .= "\n\n" . $row[1] . ";\n\n";
            }
        }

        return $result;
    }

    /**
     * Retorna apenas dados (INSERT INTO)
     * 
     * @param string|array $tables Tabela(s) ou '*' para todas
     * @param array $ignore Tabelas a ignorar
     * @return string SQL com INSERT INTO
     */
    public function getData(string|array $tables = '*', array $ignore = []): string
    {
        $result = '';

        try {
            $this->connection->exec("SET FOREIGN_KEY_CHECKS=0");

            // Resolve tabelas
            if ($tables === '*') {
                $tables = $this->inspector->getTables();
            } else {
                $tables = is_array($tables) ? $tables : explode(',', $tables);
            }

            // Para cada tabela
            foreach ($tables as $table) {
                if (in_array($table, $ignore)) {
                    continue;
                }

                $stmt = $this->connection->query("SELECT * FROM `{$table}`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($rows)) {
                    continue;
                }

                $result .= "INSERT INTO `{$table}` VALUES ";

                $values = [];
                foreach ($rows as $row) {
                    foreach ($row as $key => $value) {
                        $row[$key] = is_null($value) 
                            ? 'NULL' 
                            : $this->connection->quote($value);
                    }
                    $values[] = '(' . implode(',', $row) . ')';
                }

                $result .= implode(',', $values) . ";\n\n";
            }

            $this->connection->exec("SET FOREIGN_KEY_CHECKS=1");

            return $result;

        } catch (\PDOException $e) {
            throw new \Exception("Erro ao gerar dump: " . $e->getMessage());
        }
    }

    /**
     * Retorna dump completo (estrutura + dados)
     * 
     * @param string|array $tables Tabela(s) ou '*' para todas
     * @param array $ignore Tabelas a ignorar
     * @return string SQL completo
     */
    public function getFull(string|array $tables = '*', array $ignore = []): string
    {
        $dump = "-- GalaxyDB Full Dump\n";
        $dump .= "-- Gerado em: " . date('Y-m-d H:i:s') . "\n\n";
        $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        $dump .= $this->getStructure($tables);
        $dump .= $this->getData($tables, $ignore);
        
        $dump .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

        return $dump;
    }

    /**
     * Salva dump em arquivo
     * 
     * @param string $filepath Caminho do arquivo
     * @param string $content Conteúdo do dump
     * @return bool True se salvo com sucesso
     */
    public function saveToFile(string $filepath, string $content): bool
    {
        $dir = dirname($filepath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($filepath, $content) !== false;
    }

    /**
     * Exporta dump completo para arquivo
     * 
     * @param string $filepath Caminho do arquivo
     * @param string|array $tables Tabela(s) ou '*'
     * @param array $ignore Tabelas a ignorar
     * @return bool True se exportado com sucesso
     */
    public function export(
        string $filepath,
        string|array $tables = '*',
        array $ignore = []
    ): bool {
        $content = $this->getFull($tables, $ignore);
        return $this->saveToFile($filepath, $content);
    }
}
