<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Query;

use PDO;
use PDOException;
use Exception;

trait QueryBatch
{
    protected array $preparedQueries = [];
    protected array $transactionCallbacks = [];
    protected bool $inBatchMode = false;

    public function prepare_insert(): self
    {
        if (empty($this->InsertVars)) {
            throw new Exception("Nenhum dado para inserir");
        }

        $columns = array_keys($this->InsertVars);
        $values = array_values($this->InsertVars);
        $columnsStr = '`' . implode('`, `', $columns) . '`';
        
        $placeholders = [];
        $bindings = [];
        foreach ($values as $i => $value) {
            $placeholder = ":ins_" . count($this->preparedQueries) . "_{$i}";
            $placeholders[] = $placeholder;
            $bindings[$placeholder] = $value;
        }
        $valuesStr = implode(', ', $placeholders);

        $sql = "INSERT INTO `{$this->tableClass}` ({$columnsStr}) VALUES ({$valuesStr})";
        
        // Adiciona WHERE se existir (INSERT ... WHERE não é padrão, mas para compatibilidade)
        $whereClause = $this->buildWhere();
        if ($whereClause) {
            // Converte para INSERT ... SELECT com WHERE
            $sql = "INSERT INTO `{$this->tableClass}` ({$columnsStr}) SELECT {$valuesStr} FROM DUAL {$whereClause}";
            $bindings = array_merge($bindings, $this->whereBindings);
        }

        $this->preparedQueries[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'type' => 'insert'
        ];

        // Limpa apenas os dados de insert, mantém prepared queries
        $this->InsertVars = [];
        $this->where = null;
        $this->whereBindings = [];

        return $this;
    }

    public function prepare_update(): self
    {
        if (empty($this->Insert_Update)) {
            throw new Exception("Nenhum dado para atualizar");
        }

        $sets = [];
        $bindings = [];
        $index = 0;

        foreach ($this->Insert_Update as $column => $value) {
            $placeholder = ":upd_" . count($this->preparedQueries) . "_{$index}";
            $sets[] = "`{$column}` = {$placeholder}";
            $bindings[$placeholder] = $value;
            $index++;
        }

        $sql = "UPDATE `{$this->tableClass}` SET " . implode(', ', $sets);
        $sql .= $this->buildWhere();

        $this->preparedQueries[] = [
            'sql' => $sql,
            'bindings' => array_merge($bindings, $this->whereBindings),
            'type' => 'update'
        ];

        $this->Insert_Update = [];
        $this->where = null;
        $this->whereBindings = [];

        return $this;
    }

    public function prepare_delete(): self
    {
        $sql = "DELETE FROM `{$this->tableClass}`";
        $sql .= $this->buildWhere();

        $this->preparedQueries[] = [
            'sql' => $sql,
            'bindings' => $this->whereBindings,
            'type' => 'delete'
        ];

        $this->where = null;
        $this->whereBindings = [];

        return $this;
    }

    public function prepare_select(?string $columns = null): self
    {
        $columns = $columns ?? '*';
        
        if ($columns !== '*') {
            $columnsParts = array_map('trim', explode(',', $columns));
            $validatedColumns = [];
            foreach ($columnsParts as $col) {
                $validatedColumns[] = '`' . $this->validateIdentifier($col) . '`';
            }
            $columns = implode(', ', $validatedColumns);
        }

        $distinct = $this->DISTINCT ? 'DISTINCT ' : '';
        
        $sql = "SELECT {$distinct}{$columns} FROM `{$this->tableClass}`";
        $sql .= $this->buildWhere();
        $sql .= $this->buildGroupBy();
        $sql .= $this->buildOrderBy();
        $sql .= $this->buildLimit();

        $this->preparedQueries[] = [
            'sql' => $sql,
            'bindings' => $this->whereBindings,
            'type' => 'select'
        ];

        $this->resetBuilder();

        return $this;
    }

    public function transaction(callable $errorCallback): self
    {
        $this->transactionCallbacks[] = $errorCallback;
        return $this;
    }

    public function execQuery(?callable $callback = null): mixed
    {
        if (empty($this->preparedQueries)) {
            throw new Exception("Nenhuma query preparada para executar");
        }

        $results = [];
        $inTransaction = !empty($this->transactionCallbacks);

        try {
            if ($inTransaction) {
                $this->connection->beginTransaction();
            }

            foreach ($this->preparedQueries as $index => $prepared) {
                $stmt = $this->connection->prepare($prepared['sql']);
                
                foreach ($prepared['bindings'] as $placeholder => $value) {
                    $stmt->bindValue($placeholder, $value, $this->getPDOType($value));
                }

                $stmt->execute();

                if ($this->debug) {
                    $this->logQuery($prepared['sql'], $prepared['bindings']);
                }

                // Coleta resultados conforme tipo
                switch ($prepared['type']) {
                    case 'insert':
                        $lastId = (int) $this->connection->lastInsertId();
                        $this->_last_id[] = $lastId;
                        $results[] = ['type' => 'insert', 'id' => $lastId];
                        break;
                    
                    case 'update':
                    case 'delete':
                        $affected = $stmt->rowCount();
                        $this->_num_rows[] = $affected;
                        $results[] = ['type' => $prepared['type'], 'affected' => $affected];
                        break;
                    
                    case 'select':
                        $data = $stmt->fetchAll();
                        $results[] = ['type' => 'select', 'data' => $data];
                        break;
                }
            }

            if ($inTransaction) {
                $this->connection->commit();
            }

            // Limpa queries preparadas
            $this->preparedQueries = [];
            $this->transactionCallbacks = [];

            // Callback de sucesso
            if ($callback) {
                return $callback($this, $results);
            }

            return $results;

        } catch (PDOException $e) {
            if ($inTransaction) {
                $this->connection->rollBack();
            }

            $this->logError($e->getMessage());

            // Executa callbacks de erro
            foreach ($this->transactionCallbacks as $errorCallback) {
                $errorCallback($e->getMessage());
            }

            $this->preparedQueries = [];
            $this->transactionCallbacks = [];

            throw $e;
        }
    }

    public function getPreparedCount(): int
    {
        return count($this->preparedQueries);
    }

    public function clearPrepared(): self
    {
        $this->preparedQueries = [];
        $this->transactionCallbacks = [];
        return $this;
    }

    public function getPreparedQueries(): array
    {
        return $this->preparedQueries;
    }
}
