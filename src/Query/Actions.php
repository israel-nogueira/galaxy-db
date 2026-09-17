<?php

declare (strict_types = 1);

namespace IsraelNogueira\galaxyDB\Query;

use PDO;
use PDOException;

trait Actions
{
    /**
     * Cache para resultados de select
     */
    private array $selectCache = [];
    private bool $useCache = false;
    private int $cacheTTL = 300; // 5 segundos

    public function select(?string $columns = null): array
    {
        // Prioriza colunas do colum()
        if (! empty($this->selectColumns)) {
            $columns = implode(', ', $this->selectColumns);
        } else {
            $columns = $columns ?? '*';
        }

        if ($columns !== '*') {
            $columnsParts     = array_map('trim', explode(',', $columns));
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

        // Verifica cache
        $cacheKey = md5($sql . serialize($this->whereBindings));
        if ($this->useCache && isset($this->selectCache[$cacheKey])) {
            $cache = $this->selectCache[$cacheKey];
            if ((time() - $cache['time']) < $this->cacheTTL) {
                return $cache['data'];
            }
            unset($this->selectCache[$cacheKey]);
        }

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

            // Armazena em cache
            if ($this->useCache) {
                $this->selectCache[$cacheKey] = [
                    'data' => $result,
                    'time' => time()
                ];
            }

            $this->resetBuilder();

            return $result;
        } catch (PDOException $e) {
            $this->logError($e->getMessage());
            throw $e;
        }
    }

    /**
     * Habilita cache para próxima consulta
     */
    public function withCache(int $ttl = 300): self
    {
        $this->useCache = true;
        $this->cacheTTL = max(1, $ttl);
        return $this;
    }

    /**
     * Limpa cache de consultas
     */
    public function clearCache(): self
    {
        $this->selectCache = [];
        return $this;
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

        $this->query  = $sql;

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

    public function insert(): int | bool
    {
        if (empty($this->InsertVars)) {
            return false;
        }

        // Valida colunas antes de inserir
        $this->validateInsertColumns();

        $columns = array_keys($this->InsertVars);
        $values  = array_values($this->InsertVars);

        $columnsStr = '`' . implode('`, `', $columns) . '`';

        $placeholders = [];
        $bindings     = [];
        foreach ($values as $i => $value) {
            $placeholder            = ":ins_{$i}";
            $placeholders[]         = $placeholder;
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

            // Limpa cache após insert
            $this->clearCache();

            return $lastId ?: true;
        } catch (PDOException $e) {
            $this->logError($e->getMessage());
            throw $e;
        }
    }

    /**
     * Valida colunas para insert
     */
    private function validateInsertColumns(): void
    {
        if (empty($this->InsertVars)) {
            return;
        }

        // Se há lista de colunas permitidas, verifica
        if (!empty($this->columnsEnab)) {
            $invalidColumns = array_diff(array_keys($this->InsertVars), $this->columnsEnab);
            if (!empty($invalidColumns)) {
                throw new \Exception(
                    "Colunas não permitidas para insert: " . implode(', ', $invalidColumns)
                );
            }
        }

        // Verifica colunas bloqueadas
        if (!empty($this->columnsBlock)) {
            $blockedColumns = array_intersect(array_keys($this->InsertVars), $this->columnsBlock);
            if (!empty($blockedColumns)) {
                throw new \Exception(
                    "Colunas bloqueadas para insert: " . implode(', ', $blockedColumns)
                );
            }
        }
    }

    public function insertBatch(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        // Valida primeira linha
        $firstRow   = reset($data);
        $columns    = array_keys($firstRow);
        
        // Valida colunas
        foreach ($columns as $column) {
            $this->validateIdentifier($column);
        }

        $columnsStr = '`' . implode('`, `', $columns) . '`';

        $valueSets = [];
        $bindings  = [];
        $rowIndex  = 0;
        $maxBatchSize = 1000; // Limite para evitar timeout

        foreach ($data as $row) {
            if ($rowIndex >= $maxBatchSize) {
                throw new \Exception("Batch excede o limite de {$maxBatchSize} registros");
            }

            $placeholders = [];
            foreach ($columns as $colIndex => $column) {
                $placeholder            = ":batch_{$rowIndex}_{$colIndex}";
                $placeholders[]         = $placeholder;
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

            // Limpa cache após batch insert
            $this->clearCache();

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

        // Valida colunas para update
        $this->validateUpdateColumns();

        $sets     = [];
        $bindings = [];
        $index    = 0;

        foreach ($this->Insert_Update as $column => $value) {
            $placeholder            = ":upd_{$index}";
            $sets[]                 = "`{$column}` = {$placeholder}";
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

            // Limpa cache após update
            $this->clearCache();

            return $affected;
        } catch (PDOException $e) {
            $this->logError($e->getMessage());
            throw $e;
        }
    }

    /**
     * Valida colunas para update
     */
    private function validateUpdateColumns(): void
    {
        if (empty($this->Insert_Update)) {
            return;
        }

        $columns = array_keys($this->Insert_Update);

        // Se há lista de colunas permitidas
        if (!empty($this->columnsEnab)) {
            $invalidColumns = array_diff($columns, $this->columnsEnab);
            if (!empty($invalidColumns)) {
                throw new \Exception(
                    "Colunas não permitidas para update: " . implode(', ', $invalidColumns)
                );
            }
        }

        // Verifica colunas bloqueadas
        if (!empty($this->columnsBlock)) {
            $blockedColumns = array_intersect($columns, $this->columnsBlock);
            if (!empty($blockedColumns)) {
                throw new \Exception(
                    "Colunas bloqueadas para update: " . implode(', ', $blockedColumns)
                );
            }
        }
    }

    public function delete(): int
    {
        $sql = "DELETE FROM {$this->formatTableName($this->tableClass)}";
        $sql .= $this->buildWhere();

        $this->query  = $sql;

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

            // Limpa cache após delete
            $this->clearCache();

            return $affected;
        } catch (PDOException $e) {
            $this->logError($e->getMessage());
            throw $e;
        }
    }

    public function query(string $sql, array $bindings = []): array | bool
    {
        // Valida SQL básica
        if (stripos($sql, ';') !== false && substr_count($sql, ';') > 1) {
            throw new \Exception("Múltiplas queries não são permitidas");
        }

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
        // Valida SQL básica
        if (stripos($sql, ';') !== false && substr_count($sql, ';') > 1) {
            throw new \Exception("Múltiplas queries não são permitidas");
        }

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

            // Limpa cache após exec
            $this->clearCache();

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
        if (empty($this->lastBindings)) {
            return $this->query;
        }

        $sql = $this->query;
        foreach ($this->lastBindings as $placeholder => $value) {
            if (is_null($value)) {
                $quoted = 'NULL';
            } elseif (is_bool($value)) {
                $quoted = $value ? '1' : '0';
            } elseif (is_int($value) || is_float($value)) {
                $quoted = (string) $value;
            } else {
                $quoted = "'" . addslashes((string) $value) . "'";
            }
            $sql = str_replace((string) $placeholder, $quoted, $sql);
        }
        return $sql;
    }

    public function toSql(): string
    {
        // Monta SQL sem executar
        $columns = ! empty($this->selectColumns)
            ? implode(', ', $this->selectColumns)
            : '*';

        $distinct = $this->DISTINCT ? 'DISTINCT ' : '';
        $sql      = "SELECT {$distinct}{$columns} FROM {$this->formatTableName($this->tableClass)}";
        $sql      .= $this->buildJoins();
        $sql      .= $this->buildWhere();
        $sql      .= $this->buildGroupBy();
        $sql      .= $this->buildHaving();
        $sql      .= $this->buildOrderBy();
        $sql      .= $this->buildLimit();

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
            is_int($value)  => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default         => PDO::PARAM_STR,
        };
    }

    protected function logQuery(string $sql, array $bindings = []): void
    {
        // Implementação básica - sobrescreva conforme necessário
        if (defined('GALAXY_DEBUG') && GALAXY_DEBUG) {
            $log = sprintf(
                "[%s] Query: %s | Bindings: %s\n",
                date('Y-m-d H:i:s'),
                $sql,
                json_encode($bindings)
            );
            error_log($log);
        }
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

    /**
     * Paginação básica
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $total = $this->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        
        $offset = ($page - 1) * $perPage;
        $data = $this->limit($perPage, $offset)->select();

        return [
            'data' => $data,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }

    /*
    |--------------------------------------------------------------------
    |   RETROCOMPATIBILIDADE
    |--------------------------------------------------------------------
    |
    |   Aliases para manter compatibilidade com código legado
    |   que usa a API antiga da lib (set_insert, set_update, etc.)
    |
    */

    public function set_insert(string $colum, mixed $var): static
    {
        $this->setInsertValue($colum, $var ?? '');
        return $this;
    }

    public function set_update(string $colum, mixed $var): static
    {
        $this->setUpdateValue($colum, $var ?? '');
        return $this;
    }

    public function set_insert_obj(array $object): static
    {
        foreach ($object as $key => $var) {
            $this->setInsertValue($key, $var ?? '');
        }
        return $this;
    }

    public function set_update_obj(array $object): static
    {
        foreach ($object as $key => $var) {
            $this->setUpdateValue($key, $var ?? '');
        }
        return $this;
    }
}