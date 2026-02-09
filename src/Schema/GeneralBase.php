<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Schema;

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
     * Retorna apenas a estrutura das tabelas (CREATE TABLE)
     * 
     * @param string|array $tables Tabela(s) específica(s) ou '*' para todas
     * @return string SQL com CREATE TABLE das tabelas
     */
    public function getDB_Tables(string|array $tables = '*'): string
    {
        $dump = new Dump($this->connection);
        return $dump->getStructure($tables);
    }

    /**
     * Retorna apenas os dados das tabelas (INSERT INTO)
     * 
     * @param string|array $tables Tabela(s) específica(s) ou '*' para todas
     * @param array $ignore Tabelas a ignorar no dump
     * @return string SQL com INSERT INTO dos dados
     */
    public function getDB_Data(string|array $tables = '*', array $ignore = []): string
    {
        $dump = new Dump($this->connection);
        return $dump->getData($tables, $ignore);
    }

    /**
     * Retorna índices de uma tabela
     * 
     * @param string $tableName Nome da tabela
     * @return array Lista de índices
     */
    public function getIndexes(string $tableName): array
    {
        $inspector = new Inspector($this->connection);
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
        $inspector = new Inspector($this->connection);
        return $inspector->getColumns($table, $type);
    }

    /**
     * Retorna stored procedures do banco
     * 
     * @return array Lista de procedures
     */
    public function showProcedures(): array
    {
        $inspector = new Inspector($this->connection);
        return $inspector->getProcedures();
    }

    /**
     * Retorna lista de tabelas do banco
     * 
     * @return array Lista de nomes de tabelas
     */
    public function showDBTables(): array
    {
        $inspector = new Inspector($this->connection);
        return $inspector->getTables();
    }

    /**
     * Verifica se uma tabela ou coluna existe
     * 
     * @return bool True se existir
     */
    public function verify(): bool
    {
        $inspector = new Inspector($this->connection);

        // Verifica apenas tabela
        if (!is_null($this->tableClass ?? null) && is_null($this->setcolum ?? null)) {
            return $inspector->tableExists($this->tableClass);
        }

        // Verifica tabela e coluna
        if (!is_null($this->tableClass ?? null) && !is_null($this->setcolum ?? null)) {
            $columnName = is_array($this->setcolum) ? $this->setcolum[0] : $this->setcolum;
            return $inspector->columnExists($this->tableClass, $columnName);
        }

        return false;
    }
}
