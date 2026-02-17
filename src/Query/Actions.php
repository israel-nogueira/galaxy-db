<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Query;

use PDO;
use PDOStatement;
use PDOException;

trait Actions
{
    public function select(?string $columns = null): array
    {
        // Prioriza colunas do colum()
        if (!empty($this->selectColumns)) {
            $columns = implode(', ', $this->selectColumns);
        } else {
            $columns = $columns ?? '*';
        }
        
        if ($columns !== '*') {
            $columnsParts = array_map('trim', explode(',', $columns));
            $validatedColumns = [];
            foreach ($columnsParts as $col) {
                // Se tem AS (alias), não adiciona backticks
                if (preg_match('/\s+AS\s+/i', $col)) {
                    $validatedColumns[] = $col;
                } elseif ($col === '*' || strpos($col, '.*') !== false) {
                    // Permite * e TABELA.*
                    $validatedColumns[] = $col;
                } else {
                    // Coluna simples ou com ponto (TABELA.COLUNA)
                    $validatedColumns[] = $col;
                }
            }
            $columns = implode(', ', $validatedColumns);
        }

        $distinct = $this->DISTINCT ? 'DISTINCT ' : '';
        
        $sql = "SELECT {$distinct}{$columns} FROM {$this->formatTableName($this->tableClass)}";
        $sql .= $this->buildJoins();
        $sql .= $this->buildWhere();
        $sql .= $this->buildGroupBy();
        $sql .= $this->buildHaving();
        $sql .= $this->buildOrderBy();
        $sql .= $this->buildLimit();

        $this->query = $sql;

        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($this->whereBindings as $placeholder => $value) {
                $stmt->bindValue($placeholder, $value, $this->getPDOType($value));
            }

            $stmt->execute();
            $result = $stmt->fetchAll();
            $result = $this->applyJsonDecode($result);

            if ($this->debug) {
                $this->logQuery($sql, $this->whereBindings);
            }

            $this->resetBuilder();

            return $result;
        } catch (PDOException $e) {
            $this->logError($e->getMessage());
            throw $e;
        }
    }

    public function first(?string $columns = null): ?array
    {
        $this->limit(1);
        $result = $this->select($columns);
        
        return $result[0] ?? null;
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->formatTableName($this->tableClass)}";
        $sql .= $this->buildJoins();
        $sql .= $this->buildWhere();
        $sql .= $this->buildGroupBy();
        $sql .= $this->buildHaving();

        $this->query = $sql;

        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($this->whereBindings as $placeholder => $value) {
                $stmt->bindValue($placeholder, $value, $this->getPDOType($value));
            }

            $stmt->execute();
            $result = $stmt->fetch();

            $this->resetBuilder();

            return (int) ($result['total'] ?? 0);
        } catch (PDOException $e) {
            $this->logError($e->getMessage());
            throw $e;
        }
    }

    public function insert(): int|bool
    {
        if (empty($this->InsertVars)) {
            return false;
        }

        $columns = array_keys($this->InsertVars);
        $values = array_values($this->InsertVars);

        $columnsStr = '`' . implode('`, `', $columns) . '`';
        
        $placeholders = [];
        $bindings = [];
        foreach ($values as $i => $value) {
            $placeholder = ":ins_{$i}";
            $placeholders[] = $placeholder;
            $bindings[$placeholder] = $value;
        }
        $valuesStr = implode(', ', $placeholders);

        $sql = "INSERT INTO {$this->formatTableName($this->tableClass)} ({$columnsStr}) VALUES ({$valuesStr})";

        $this->query = $sql;

        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($bindings as $placeholder => $value) {
                $stmt->bindValue($placeholder, $value, $this->getPDOType($value));
            }

            $stmt->execute();
            $lastId = (int) $this->connection->lastInsertId();
            
            $this->_last_id[] = $lastId;

            if ($this->debug) {
                $this->logQuery($sql, $bindings);
            }

            $this->resetBuilder();
            
            return $lastId ?: true;
        } catch (PDOException $e) {
            $this->logError($e->getMessage());
            throw $e;
        }
    }

    public function insertBatch(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $firstRow = reset($data);
        $columns = array_keys($firstRow);
        $columnsStr = '`' . implode('`, `', $columns) . '`';

        $valueSets = [];
        $bindings = [];
        $rowIndex = 0;

        foreach ($data as $row) {
            $placeholders = [];
            foreach ($columns as $colIndex => $column) {
                $placeholder = ":batch_{$rowIndex}_{$colIndex}";
                $placeholders[] = $placeholder;
                $bindings[$placeholder] = $row[$column] ?? null;
            }
            $valueSets[] = '(' . implode(', ', $placeholders) . ')';
            $rowIndex++;
        }

        $sql = "INSERT INTO {$this->formatTableName($this->tableClass)} ({$columnsStr}) VALUES " . implode(', ', $valueSets);

        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($bindings as $placeholder => $value) {
                $stmt->bindValue($placeholder, $value, $this->getPDOType($value));
            }

            $stmt->execute();

            if ($this->debug) {
                $this->logQuery($sql, $bindings);
            }

            return true;
        } catch (PDOException $e) {
            $this->logError($e->getMessage());
            throw $e;
        }
    }

    public function update(): int
    {
        if (empty($this->Insert_Update)) {
            return 0;
        }

        $sets = [];
        $bindings = [];
        $index = 0;

        foreach ($this->Insert_Update as $column => $value) {
            $placeholder = ":upd_{$index}";
            $sets[] = "`{$column}` = {$placeholder}";
            $bindings[$placeholder] = $value;
            $index++;
        }

        $sql = "UPDATE {$this->formatTableName($this->tableClass)} SET " . implode(', ', $sets);
        $sql .= $this->buildWhere();

        $this->query = $sql;

        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($bindings as $placeholder => $value) {
                $stmt->bindValue($placeholder, $value, $this->getPDOType($value));
            }

            foreach ($this->whereBindings as $placeholder => $value) {
                $stmt->bindValue($placeholder, $value, $this->getPDOType($value));
            }

            $stmt->execute();
            $affected = $stmt->rowCount();
            
            $this->_num_rows[] = $affected;

            if ($this->debug) {
                $this->logQuery($sql, array_merge($bindings, $this->whereBindings));
            }

            $this->resetBuilder();
            
            return $affected;
        } catch (PDOException $e) {
            $this->logError($e->getMessage());
            throw $e;
        }
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->formatTableName($this->tableClass)}";
        $sql .= $this->buildWhere();

        $this->query = $sql;

        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($this->whereBindings as $placeholder => $value) {
                $stmt->bindValue($placeholder, $value, $this->getPDOType($value));
            }

            $stmt->execute();
            $affected = $stmt->rowCount();
            
            $this->_num_rows[] = $affected;

            if ($this->debug) {
                $this->logQuery($sql, $this->whereBindings);
            }

            $this->resetBuilder();
            
            return $affected;
        } catch (PDOException $e) {
            $this->logError($e->getMessage());
            throw $e;
        }
    }

    public function query(string $sql, array $bindings = []): array|bool
    {
        $this->query = $sql;

        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($bindings as $placeholder => $value) {
                $stmt->bindValue($placeholder, $value, $this->getPDOType($value));
            }

            $stmt->execute();

            if ($this->debug) {
                $this->logQuery($sql, $bindings);
            }

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError($e->getMessage());
            throw $e;
        }
    }

    public function exec(string $sql, array $bindings = []): int
    {
        $this->query = $sql;

        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($bindings as $placeholder => $value) {
                $stmt->bindValue($placeholder, $value, $this->getPDOType($value));
            }

            $stmt->execute();

            if ($this->debug) {
                $this->logQuery($sql, $bindings);
            }

            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError($e->getMessage());
            throw $e;
        }
    }

    public function lastInsertId(): int
    {
        return end($this->_last_id) ?: 0;
    }

    public function affectedRows(): int
    {
        return end($this->_num_rows) ?: 0;
    }

    public function getLastQuery(): string
    {
        return $this->query;
    }

    public function toSql(): string
    {
        // Monta SQL sem executar
        $columns = !empty($this->selectColumns) 
            ? implode(', ', $this->selectColumns) 
            : '*';
        
        $distinct = $this->DISTINCT ? 'DISTINCT ' : '';
        $sql = "SELECT {$distinct}{$columns} FROM {$this->formatTableName($this->tableClass)}";
        $sql .= $this->buildJoins();
        $sql .= $this->buildWhere();
        $sql .= $this->buildGroupBy();
        $sql .= $this->buildHaving();
        $sql .= $this->buildOrderBy();
        $sql .= $this->buildLimit();
        
        return $sql;
    }

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    protected function getPDOType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    protected function logQuery(string $sql, array $bindings = []): void
    {
        // Implementação básica - sobrescreva conforme necessário
    }

    public function fetch_array(string $name = '0'): array
    {
        if (isset($this->executedResults[$name])) {
            return $this->executedResults[$name];
        }
        return $this->select();
    }

    public function fetch_row(): ?array
    {
        return $this->first();
    }

    protected function logError(string $error): void
    {
        // Implementação básica - sobrescreva conforme necessário
        error_log("GalaxyDB Error: {$error}");
    }
}
