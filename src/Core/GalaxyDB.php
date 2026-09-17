<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Core;

use PDO;
use PDOException;
use IsraelNogueira\galaxyDB\Core\Connection;
use IsraelNogueira\galaxyDB\Core\DatabaseInterface;
use IsraelNogueira\galaxyDB\Schema\GeneralBase;
use IsraelNogueira\galaxyDB\Security\Security;
use IsraelNogueira\galaxyDB\Query\QueryBuilder;
use IsraelNogueira\galaxyDB\Query\Actions;
use IsraelNogueira\galaxyDB\Query\QueryBatch;
use IsraelNogueira\galaxyDB\Query\DataTableTrait;
use IsraelNogueira\galaxyDB\Audit\Log;
use IsraelNogueira\galaxyDB\StoredProcedures\SPExecutor;
use RuntimeException;
use ReflectionClass;
use Exception;

class GalaxyDB implements DatabaseInterface
{
    use Connection;
    use GeneralBase;
    use Security;
    use QueryBuilder;
    use Actions;
    use QueryBatch;
    use Log;
    use DataTableTrait;

    private bool $initialized = false;
    protected ?array $customConnectData = null;
    public static ?string $dbaseType = null;
    protected ?string $tableClass = null;
    protected array $columnsBlock = [];
    protected array $columnsEnab = [];
    protected array $mysqlFnBlockClass = [];
    protected array $mysqlFnEnabClass = [];
    protected array $_last_id = [];
    protected array $_num_rows = [];
    protected string $query = '';
    protected array $lastBindings = [];
    protected ?string $colum = null;
    protected mixed $setcolum = null;
    public bool $debug = false;

    /**
     * Query log para debug
     */
    private array $queryLog = [];

    /**
     * Tempo de execução da última query
     */
    private ?float $lastQueryTime = null;

    /**
     * Cache de resultados
     */
    private array $resultCache = [];

    public function __construct(?array $conn = null)
    {
        $this->_last_id = [];
        $this->_num_rows = [];

        if (basename(get_class($this)) !== "GalaxyDB") {
            $this->extended();

            if (property_exists($this, 'customConnectData') && 
                is_array($this->customConnectData) && 
                !empty($this->customConnectData)) {
                $conn = $this->customConnectData;
            }
        }

        $this->connection = $this->connect($conn ?? []);
        $this->initialized = true;
    }

    public function __set(string $name, mixed $value): void
    {
        $declaredVars = array_keys(get_mangled_object_vars($this));

        if ($this->initialized && !in_array($name, $declaredVars)) {
            $this->setInsertValue($name, $value);
            $this->setUpdateValue($name, $value);
        } else {
            $this->{$name} = $value;
        }
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (in_array($name, get_class_methods(get_called_class()) ?? [])) {
            return $this->$name(...$arguments);
        }

        if (str_starts_with(strtolower($name), 'sp_')) {
            return $this->executeSP(substr($name, 3), $arguments);
        }

        throw new RuntimeException("Método desconhecido: {$name}");
    }

    public static function static(): static
    {
        return new static();
    }

    public function extended(): void
    {
        if (get_parent_class($this) !== false) {
            $this->tableClass = $this->getExtendedProperty('table', null);
            $this->columnsBlock = $this->getExtendedProperty('columnsBlocked', []);
            $this->columnsEnab = $this->getExtendedProperty('columnsEnabled', []);
            $this->mysqlFnBlockClass = $this->getExtendedProperty('functionsBlocked', []);
            $this->mysqlFnEnabClass = $this->getExtendedProperty('functionsEnabled', []);
            $this->customConnectData = $this->getExtendedProperty('customConnectData', []);
        }
    }

    public function getExtendedProperty(string $property, mixed $default = null): mixed
    {
        $reflection = new ReflectionClass($this);

        while ($reflection) {
            $properties = $reflection->getDefaultProperties();

            if (array_key_exists($property, $properties)) {
                return $this->$property ?? $default;
            }

            $reflection = $reflection->getParentClass();
        }

        return $default;
    }

    protected function executeSP(string $name, array $params): mixed
    {
        $executor = new SPExecutor($this->connection);
        return $executor->execute($name, $params);
    }

    // ============================================================
    // MÉTODOS DA INTERFACE DatabaseInterface
    // ============================================================

    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    public function raw(string $sql, array $bindings = []): array|bool
    {
        return $this->query($sql, $bindings);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function find(mixed $id, string $primaryKey = 'id'): ?array
    {
        return $this->where($primaryKey, $id)->first();
    }

    public function findOrFail(mixed $id, string $primaryKey = 'id'): array
    {
        $result = $this->find($id, $primaryKey);
        
        if ($result === null) {
            throw new Exception("Registro não encontrado com {$primaryKey} = {$id}");
        }

        return $result;
    }

    public function pluck(string $column): array
    {
        $column = $this->validateIdentifier($column);
        $results = $this->select($column);
        return array_column($results, $column);
    }

    public function chunk(int $size, callable $callback): void
    {
        $offset = 0;
        
        do {
            $results = $this->limit($size, $offset)->select();
            
            if (empty($results)) {
                break;
            }

            if ($callback($results) === false) {
                break;
            }

            $offset += $size;
        } while (count($results) === $size);
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function getLastQuery(): string
    {
        return $this->query;
    }

    public function lastInsertId(): int
    {
        return $this->getLastInsertId();
    }

    public function affectedRows(): int
    {
        return $this->getAffectedRows();
    }

    public function toSql(): string
    {
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

    // ============================================================
    // GETTERS PARA ID E AFFECTED ROWS
    // ============================================================

    public function getLastInsertId(): int
    {
        return end($this->_last_id) ?: 0;
    }

    public function getAffectedRows(): int
    {
        return end($this->_num_rows) ?: 0;
    }

    // ============================================================
    // MÉTODOS DE LOG E DEBUG
    // ============================================================

    public function getLastQueryTime(): ?float
    {
        return $this->lastQueryTime;
    }

    public function getQueryLog(): array
    {
        return $this->queryLog;
    }

    public function clearQueryLog(): self
    {
        $this->queryLog = [];
        return $this;
    }

    public function getQueryCount(): int
    {
        return count($this->queryLog);
    }

    public function getLastExecutedQuery(): ?array
    {
        if (empty($this->queryLog)) {
            return null;
        }
        return end($this->queryLog);
    }

    /**
     * Executa uma query com monitoramento de tempo
     */
    protected function executeWithTiming(string $sql, array $bindings = []): array|bool
    {
        $start = microtime(true);
        
        try {
            $result = $this->query($sql, $bindings);
            $this->lastQueryTime = microtime(true) - $start;
            
            if ($this->debug) {
                $this->queryLog[] = [
                    'sql' => $sql,
                    'bindings' => $bindings,
                    'time' => $this->lastQueryTime,
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            $this->lastQueryTime = microtime(true) - $start;
            throw $e;
        }
    }

    protected function logQuery(string $sql, array $bindings = []): void
    {
        if ($this->debug) {
            $this->queryLog[] = [
                'sql' => $sql,
                'bindings' => $bindings,
                'time' => microtime(true),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    protected function logError(string $error): void
    {
        error_log("GalaxyDB Error: {$error}");
        
        if ($this->debug) {
            $this->queryLog[] = [
                'error' => $error,
                'timestamp' => date('Y-m-d H:i:s'),
                'type' => 'error'
            ];
        }
    }

    // ============================================================
    // MÉTODOS DE CACHE
    // ============================================================

    public function cachedQuery(string $sql, array $bindings = [], int $ttl = 300): array|bool
    {
        $cacheKey = md5($sql . serialize($bindings));
        
        if (isset($this->resultCache[$cacheKey])) {
            $cache = $this->resultCache[$cacheKey];
            if ((time() - $cache['time']) < $ttl) {
                return $cache['data'];
            }
            unset($this->resultCache[$cacheKey]);
        }

        $result = $this->query($sql, $bindings);
        
        if ($result !== false) {
            $this->resultCache[$cacheKey] = [
                'data' => $result,
                'time' => time()
            ];
        }

        return $result;
    }

    public function clearResultCache(): self
    {
        $this->resultCache = [];
        return $this;
    }

    // ============================================================
    // MÉTODOS DE TABELA
    // ============================================================

    public function getTable(): ?string
    {
        return $this->tableClass;
    }

    public function setTable(string $table): self
    {
        $this->tableClass = $table;
        return $this;
    }

    public function tableExists(string $table = null): bool
    {
        $tableName = $table ?? $this->tableClass;
        if ($tableName === null) {
            return false;
        }
        return $this->verify($tableName);
    }

    public function totalCount(string $table = null): int
    {
        $tableName = $table ?? $this->tableClass;
        if ($tableName === null) {
            return 0;
        }
        
        $stats = $this->getTableStats($tableName);
        return (int) ($stats['row_count'] ?? 0);
    }

    public function forTable(string $table): self
    {
        $clone = clone $this;
        $clone->tableClass = $table;
        $clone->resetBuilder();
        return $clone;
    }

    // ============================================================
    // MÉTODOS DE TRANSAÇÃO
    // ============================================================

    public function begin(): self
    {
        $this->beginTransaction();
        return $this;
    }

    public function commitTransaction(): self
    {
        $this->commit();
        return $this;
    }

    public function rollbackTransaction(): self
    {
        $this->rollback();
        return $this;
    }

    public function transaction(callable $callback, ?callable $errorCallback = null): mixed
    {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            if ($errorCallback) {
                $errorCallback($e);
            }
            throw $e;
        }
    }

    // ============================================================
    // MÉTODOS DE DRIVER
    // ============================================================

    public function getDriver(): ?string
    {
        return self::$dbaseType;
    }

    public function isMySQL(): bool
    {
        return in_array($this->getDriver(), ['mysql', 'mysqli']);
    }

    public function isPostgreSQL(): bool
    {
        return $this->getDriver() === 'pgsql';
    }

    public function isSQLite(): bool
    {
        return $this->getDriver() === 'sqlite';
    }

    public function isSQLServer(): bool
    {
        return in_array($this->getDriver(), ['mssql', 'sqlsrv', 'dblib']);
    }

    public function getPDO(): PDO
    {
        return $this->connection;
    }

    // ============================================================
    // MÉTODOS DE INFORMAÇÃO
    // ============================================================

    public function getConnectionInfo(): array
    {
        return [
            'driver' => $this->getDatabaseDriver(),
            'version' => $this->getDatabaseVersion(),
            'database' => getEnv('DB_DATABASE'),
            'host' => getEnv('DB_HOST'),
            'port' => getEnv('DB_PORT'),
            'connected' => $this->isConnected()
        ];
    }

    public function hasError(): bool
    {
        return !empty($this->batchErrors);
    }

    public function getErrors(): array
    {
        return $this->batchErrors;
    }

    public function getStats(): array
    {
        return [
            'query_count' => $this->getQueryCount(),
            'last_query_time' => $this->getLastQueryTime(),
            'last_insert_id' => $this->getLastInsertId(),
            'affected_rows' => $this->getAffectedRows(),
            'has_errors' => $this->hasError(),
            'error_count' => count($this->getErrors()),
            'table' => $this->getTable(),
            'driver' => $this->getDriver(),
            'debug_enabled' => $this->debug
        ];
    }

    // ============================================================
    // MÉTODOS DE RESET
    // ============================================================

    public function reset(): self
    {
        $this->resetBuilder();
        $this->clearPrepared();
        $this->clearQueryLog();
        $this->clearResultCache();
        $this->_last_id = [];
        $this->_num_rows = [];
        $this->batchErrors = [];
        return $this;
    }

    public function clearCache(): self
    {
        if (method_exists($this, 'clearResultCache')) {
            $this->clearResultCache();
        }
        return $this;
    }
}