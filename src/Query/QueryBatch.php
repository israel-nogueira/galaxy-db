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
    protected array $executedResults = [];
    protected int $maxBatchSize = 1000;
    protected bool $autoClearAfterExec = true;
    protected array $batchErrors = [];
    protected ?string $lastBatchQuery = null;
    protected array $lastBatchBindings = [];

    public function prepare_insert(?string $name = null): self
    {
        if (empty($this->InsertVars)) {
            throw new Exception("Nenhum dado para inserir");
        }

        // Valida tamanho do batch
        if (count($this->preparedQueries) >= $this->maxBatchSize) {
            throw new Exception("Batch excede o limite de {$this->maxBatchSize} queries");
        }

        // Valida colunas
        $this->validateBatchInsertColumns();

        $columns = array_keys($this->InsertVars);
        $values = array_values($this->InsertVars);
        $columnsStr = '`' . implode('`, `', $columns) . '`';
        
        $placeholders = [];
        $bindings = [];
        $queryIndex = count($this->preparedQueries);
        
        foreach ($values as $i => $value) {
            $placeholder = ":ins_{$queryIndex}_{$i}";
            $placeholders[] = $placeholder;
            $bindings[$placeholder] = $value;
        }
        $valuesStr = implode(', ', $placeholders);

        $sql = "INSERT INTO `{$this->tableClass}` ({$columnsStr}) VALUES ({$valuesStr})";
        
        $whereClause = $this->buildWhere();
        if ($whereClause) {
            $sql = "INSERT INTO `{$this->tableClass}` ({$columnsStr}) SELECT {$valuesStr} FROM DUAL {$whereClause}";
            $bindings = array_merge($bindings, $this->whereBindings);
        }

        $key = $name ?? $queryIndex;
        $this->preparedQueries[$key] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'type' => 'insert',
            'columns' => $columns,
            'values' => $values
        ];

        $this->InsertVars = [];
        $this->where = null;
        $this->whereBindings = [];

        return $this;
    }

    public function prepare_update(?string $name = null): self
    {
        if (empty($this->Insert_Update)) {
            throw new Exception("Nenhum dado para atualizar");
        }

        // Valida colunas
        $this->validateBatchUpdateColumns();

        // Valida se tem WHERE
        if (empty($this->where)) {
            throw new Exception("UPDATE sem WHERE não é permitido por segurança");
        }

        $sets = [];
        $bindings = [];
        $queryIndex = count($this->preparedQueries);
        $index = 0;

        foreach ($this->Insert_Update as $column => $value) {
            $placeholder = ":upd_{$queryIndex}_{$index}";
            $sets[] = "`{$column}` = {$placeholder}";
            $bindings[$placeholder] = $value;
            $index++;
        }

        $sql = "UPDATE `{$this->tableClass}` SET " . implode(', ', $sets);
        $sql .= $this->buildWhere();

        // Merge bindings do update com os do where
        $allBindings = array_merge($bindings, $this->whereBindings);

        $key = $name ?? $queryIndex;
        $this->preparedQueries[$key] = [
            'sql' => $sql,
            'bindings' => $allBindings,
            'type' => 'update',
            'columns' => array_keys($this->Insert_Update)
        ];

        $this->Insert_Update = [];
        $this->where = null;
        $this->whereBindings = [];

        return $this;
    }

    public function prepare_delete(?string $name = null): self
    {
        // Valida se tem WHERE
        if (empty($this->where)) {
            throw new Exception("DELETE sem WHERE não é permitido por segurança");
        }

        $queryIndex = count($this->preparedQueries);
        $sql = "DELETE FROM `{$this->tableClass}`";
        $sql .= $this->buildWhere();

        $key = $name ?? $queryIndex;
        $this->preparedQueries[$key] = [
            'sql' => $sql,
            'bindings' => $this->whereBindings,
            'type' => 'delete'
        ];

        $this->where = null;
        $this->whereBindings = [];

        return $this;
    }

    public function prepare_select(?string $name = null, ?string $columns = null): self
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
        $sql .= $this->buildOrderBy();
        $sql .= $this->buildLimit();

        $queryIndex = count($this->preparedQueries);
        $key = $name ?? $queryIndex;
        $this->preparedQueries[$key] = [
            'sql' => $sql,
            'bindings' => $this->whereBindings,
            'type' => 'select',
            'columns' => $columns
        ];

        $this->resetBuilder();

        return $this;
    }

    public function transaction(callable $errorCallback): self
    {
        $this->transactionCallbacks[] = $errorCallback;
        return $this;
    }

    public function execQuery(string|callable|null $nameOrCallback = null, ?callable $callback = null): mixed
    {
        if (empty($this->preparedQueries)) {
            throw new Exception("Nenhuma query preparada para executar");
        }

        // Detecta os parâmetros
        $name = null;
        $finalCallback = null;

        if (is_callable($nameOrCallback)) {
            // execQuery(callback)
            $finalCallback = $nameOrCallback;
        } elseif (is_string($nameOrCallback)) {
            // execQuery("nome", callback) ou execQuery("nome")
            $name = $nameOrCallback;
            $finalCallback = $callback;
        }
        // Se ambos null: execQuery() - executa todas sem callback

        // Se especificou nome, executa apenas essa
        $queriesToExecute = [];
        if ($name !== null) {
            if (!isset($this->preparedQueries[$name])) {
                throw new Exception("Query '{$name}' não encontrada");
            }
            $queriesToExecute = [$name => $this->preparedQueries[$name]];
        } else {
            // Executa todas em fila
            $queriesToExecute = $this->preparedQueries;
        }

        $results = [];
        $this->batchErrors = [];
        $inTransaction = !empty($this->transactionCallbacks);

        try {
            if ($inTransaction) {
                $this->connection->beginTransaction();
            }

            foreach ($queriesToExecute as $queryName => $prepared) {
                try {
                    $stmt = $this->connection->prepare($prepared['sql']);
                    
                    foreach ($prepared['bindings'] as $placeholder => $value) {
                        $stmt->bindValue($placeholder, $value, $this->getPDOType($value));
                    }

                    $stmt->execute();

                    // Armazena última query para debug (usando propriedades próprias)
                    $this->lastBatchQuery = $prepared['sql'];
                    $this->lastBatchBindings = $prepared['bindings'];
                    
                    // Também atualiza as propriedades do Actions se existirem
                    if (property_exists($this, 'query')) {
                        $this->query = $prepared['sql'];
                    }
                    if (property_exists($this, 'lastBindings')) {
                        $this->lastBindings = $prepared['bindings'];
                    }

                    if ($this->debug) {
                        $this->logQuery($prepared['sql'], $prepared['bindings']);
                    }

                    // Coleta resultados conforme tipo
                    switch ($prepared['type']) {
                        case 'insert':
                            $lastId = (int) $this->connection->lastInsertId();
                            if (property_exists($this, '_last_id')) {
                                $this->_last_id[] = $lastId;
                            }
                            $results[$queryName] = [
                                'type' => 'insert', 
                                'id' => $lastId,
                                'success' => true
                            ];
                            break;
                        
                        case 'update':
                        case 'delete':
                            $affected = $stmt->rowCount();
                            if (property_exists($this, '_num_rows')) {
                                $this->_num_rows[] = $affected;
                            }
                            $results[$queryName] = [
                                'type' => $prepared['type'], 
                                'affected' => $affected,
                                'success' => true
                            ];
                            break;
                        
                        case 'select':
                            $data = $stmt->fetchAll();
                            $results[$queryName] = [
                                'type' => 'select', 
                                'data' => $data,
                                'count' => count($data),
                                'success' => true
                            ];
                            $this->executedResults[$queryName] = $data;
                            break;
                    }
                } catch (PDOException $e) {
                    // Registra erro
                    $this->batchErrors[$queryName] = $e->getMessage();
                    $results[$queryName] = [
                        'type' => $prepared['type'] ?? 'unknown',
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    
                    // Se está em transação, lança exceção para rollback
                    if ($inTransaction) {
                        throw $e;
                    }
                    // Se não está em transação, continua com as próximas queries
                }
            }

            // Se está em transação e não houve erros, commita
            if ($inTransaction && empty($this->batchErrors)) {
                $this->connection->commit();
            }

            // Se executou query específica, remove apenas ela
            if ($name !== null) {
                unset($this->preparedQueries[$name]);
            } elseif ($this->autoClearAfterExec) {
                // Se houve erro em modo não-transacional, mantém as queries com erro
                if (!empty($this->batchErrors)) {
                    // Remove apenas as que executaram com sucesso
                    foreach ($results as $key => $result) {
                        if (isset($result['success']) && $result['success'] === true) {
                            unset($this->preparedQueries[$key]);
                        }
                    }
                } else {
                    $this->preparedQueries = [];
                }
            }
            
            // Se houve erros em modo não-transacional, chama callbacks de erro
            // ANTES de limpar a lista (limpar antes matava os callbacks).
            if (!empty($this->batchErrors) && !$inTransaction) {
                foreach ($this->transactionCallbacks as $errorCallback) {
                    $errorCallback(implode('; ', $this->batchErrors));
                }
            }

            $this->transactionCallbacks = [];

            // Callback de sucesso
            if ($finalCallback) {
                return $finalCallback($this, $results);
            }

            return $results;

        } catch (PDOException $e) {
            if ($inTransaction) {
                $this->connection->rollBack();
            }

            $this->logError($e->getMessage());

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

    public function clearPrepared(?string $name = null): self
    {
        if ($name !== null) {
            unset($this->preparedQueries[$name]);
        } else {
            $this->preparedQueries = [];
        }
        $this->transactionCallbacks = [];
        $this->batchErrors = [];
        return $this;
    }

    public function getPreparedQueries(): array
    {
        return $this->preparedQueries;
    }

    public function getBatchErrors(): array
    {
        return $this->batchErrors;
    }

    public function hasBatchErrors(): bool
    {
        return !empty($this->batchErrors);
    }

    public function setMaxBatchSize(int $size): self
    {
        $this->maxBatchSize = max(1, $size);
        return $this;
    }

    public function setAutoClearAfterExec(bool $autoClear): self
    {
        $this->autoClearAfterExec = $autoClear;
        return $this;
    }

    /**
     * Obtém a última query executada no batch
     */
    public function getLastBatchQuery(): string
    {
        return $this->lastBatchQuery ?? '';
    }

    /**
     * Obtém os bindings da última query executada no batch
     */
    public function getLastBatchBindings(): array
    {
        return $this->lastBatchBindings ?? [];
    }

    /**
     * Valida colunas para insert (versão do batch)
     */
    private function validateBatchInsertColumns(): void
    {
        if (empty($this->InsertVars)) {
            return;
        }

        $columns = array_keys($this->InsertVars);

        // Se há lista de colunas permitidas, verifica
        if (!empty($this->columnsEnab)) {
            $invalidColumns = array_diff($columns, $this->columnsEnab);
            if (!empty($invalidColumns)) {
                throw new Exception(
                    "Colunas não permitidas para insert: " . implode(', ', $invalidColumns)
                );
            }
        }

        // Verifica colunas bloqueadas
        if (!empty($this->columnsBlock)) {
            $blockedColumns = array_intersect($columns, $this->columnsBlock);
            if (!empty($blockedColumns)) {
                throw new Exception(
                    "Colunas bloqueadas para insert: " . implode(', ', $blockedColumns)
                );
            }
        }
    }

    /**
     * Valida colunas para update (versão do batch)
     */
    private function validateBatchUpdateColumns(): void
    {
        if (empty($this->Insert_Update)) {
            return;
        }

        $columns = array_keys($this->Insert_Update);

        // Se há lista de colunas permitidas
        if (!empty($this->columnsEnab)) {
            $invalidColumns = array_diff($columns, $this->columnsEnab);
            if (!empty($invalidColumns)) {
                throw new Exception(
                    "Colunas não permitidas para update: " . implode(', ', $invalidColumns)
                );
            }
        }

        // Verifica colunas bloqueadas
        if (!empty($this->columnsBlock)) {
            $blockedColumns = array_intersect($columns, $this->columnsBlock);
            if (!empty($blockedColumns)) {
                throw new Exception(
                    "Colunas bloqueadas para update: " . implode(', ', $blockedColumns)
                );
            }
        }
    }

    /**
     * Adiciona query raw ao batch
     */
    public function prepare_raw(string $sql, array $bindings = [], ?string $name = null): self
    {
        // Valida SQL básica
        if (stripos($sql, ';') !== false && substr_count($sql, ';') > 1) {
            throw new Exception("Múltiplas queries não são permitidas");
        }

        // Detecta tipo da query
        $type = 'raw';
        $sqlUpper = strtoupper(trim($sql));
        if (str_starts_with($sqlUpper, 'INSERT')) {
            $type = 'insert';
        } elseif (str_starts_with($sqlUpper, 'UPDATE')) {
            $type = 'update';
        } elseif (str_starts_with($sqlUpper, 'DELETE')) {
            $type = 'delete';
        } elseif (str_starts_with($sqlUpper, 'SELECT')) {
            $type = 'select';
        }

        $queryIndex = count($this->preparedQueries);
        $key = $name ?? $queryIndex;
        $this->preparedQueries[$key] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'type' => $type
        ];

        return $this;
    }

    /**
     * Obtém resumo do batch
     */
    public function getBatchSummary(): array
    {
        $summary = [
            'total' => count($this->preparedQueries),
            'types' => [],
            'has_errors' => $this->hasBatchErrors(),
            'errors' => $this->batchErrors
        ];

        foreach ($this->preparedQueries as $query) {
            $type = $query['type'] ?? 'unknown';
            $summary['types'][$type] = ($summary['types'][$type] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * Verifica se há queries preparadas de um tipo específico
     */
    public function hasPreparedType(string $type): bool
    {
        foreach ($this->preparedQueries as $query) {
            if (($query['type'] ?? '') === $type) {
                return true;
            }
        }
        return false;
    }

    /**
     * Remove queries de um tipo específico
     */
    public function removePreparedType(string $type): self
    {
        foreach ($this->preparedQueries as $key => $query) {
            if (($query['type'] ?? '') === $type) {
                unset($this->preparedQueries[$key]);
            }
        }
        return $this;
    }
}