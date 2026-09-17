<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\StoredProcedures;

use PDO;
use Exception;

/**
 * -------------------------------------------------------------------------
 * Class SPExecutor
 * -------------------------------------------------------------------------
 * 
 * Executa Stored Procedures e Functions do banco de dados.
 * Gerencia parâmetros IN, OUT e INOUT.
 * 
 * @package IsraelNogueira\galaxyDB\StoredProcedures
 * @author Israel Nogueira <israel@feats.com>
 * @license GPL-3.0-or-later
 * @copyright 2023 Israel Nogueira
 * -------------------------------------------------------------------------
 */
class SPExecutor
{
    /**
     * Conexão PDO
     */
    private PDO $connection;

    /**
     * Parâmetros OUT da SP
     */
    private array $outputs = [];

    /**
     * Cache de procedures
     */
    private array $procedureCache = [];

    /**
     * Modo debug
     */
    private bool $debug = false;

    /**
     * Timeout da execução em segundos
     */
    private ?int $timeout = null;

    /**
     * Driver do banco de dados
     */
    private string $driver;

    /**
     * Construtor
     * 
     * @param PDO $connection Conexão PDO ativa
     */
    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Executa Stored Procedure
     * 
     * @param string $name Nome da procedure
     * @param array $params Parâmetros
     * @param bool $fetchAll Se deve retornar todos os resultados
     * @return mixed Resultado da execução
     * @throws Exception Se procedure inválida ou erro na execução
     */
    public function execute(string $name, array $params = [], bool $fetchAll = true): mixed
    {
        // Valida nome da procedure
        $name = $this->validateProcedureName($name);
        
        // Verifica se a procedure existe
        if (!$this->procedureExists($name)) {
            throw new Exception("Stored Procedure '{$name}' não encontrada");
        }

        // Monta placeholders
        $placeholders = [];
        foreach ($params as $value) {
            $placeholders[] = '?';
        }

        $sql = "CALL {$name}(" . implode(', ', $placeholders) . ")";

        if ($this->debug) {
            $this->logQuery($sql, $params);
        }

        try {
            // Configura timeout se definido
            if ($this->timeout !== null) {
                $this->applyTimeout($this->timeout);
            }

            $stmt = $this->connection->prepare($sql);

            // Bind dos parâmetros
            foreach ($params as $index => $value) {
                $stmt->bindValue($index + 1, $value, $this->getPDOType($value));
            }

            $stmt->execute();

            // Coleta resultados
            $results = [];
            
            // Para MySQL/MariaDB, pode ter múltiplos resultsets
            if ($this->driver === 'mysql' || $this->driver === 'mysqli') {
                do {
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($result)) {
                        $results[] = $result;
                    }
                } while ($stmt->nextRowset());
            } else {
                // Para outros drivers (PostgreSQL, SQLServer, etc.)
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($result)) {
                    $results[] = $result;
                }
                
                // Tenta pegar próximos resultados se disponível
                if (method_exists($stmt, 'nextRowset')) {
                    while ($stmt->nextRowset()) {
                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($result)) {
                            $results[] = $result;
                        }
                    }
                }
            }

            // Fecha cursor
            $stmt->closeCursor();

            // Retorna resultados
            if (empty($results)) {
                return [];
            }

            return $fetchAll ? $results : ($results[0] ?? []);

        } catch (\PDOException $e) {
            throw new Exception("Erro ao executar SP {$name}: " . $e->getMessage());
        }
    }

    /**
     * Executa Stored Procedure com parâmetros OUT
     * 
     * @param string $name Nome da procedure
     * @param array $inParams Parâmetros IN
     * @param array $outParams Parâmetros OUT (nome => tipo)
     * @param bool $fetchAll Se deve retornar todos os resultados
     * @return array Valores dos parâmetros OUT e resultados
     * @throws Exception Se erro na execução
     */
    public function executeWithOutput(
        string $name,
        array $inParams = [],
        array $outParams = [],
        bool $fetchAll = true
    ): array {
        // Valida nome da procedure
        $name = $this->validateProcedureName($name);

        // Verifica se a procedure existe
        if (!$this->procedureExists($name)) {
            throw new Exception("Stored Procedure '{$name}' não encontrada");
        }

        // Para MySQL, usa variáveis de sessão
        if ($this->driver === 'mysql' || $this->driver === 'mysqli') {
            return $this->executeMySQLWithOutput($name, $inParams, $outParams, $fetchAll);
        }

        // Para PostgreSQL, usa parâmetros nomeados
        if ($this->driver === 'pgsql') {
            return $this->executePostgresWithOutput($name, $inParams, $outParams, $fetchAll);
        }

        // Para outros drivers, tenta método genérico
        return $this->executeGenericWithOutput($name, $inParams, $outParams, $fetchAll);
    }

    /**
     * Executa MySQL com parâmetros OUT usando variáveis de sessão
     */
    private function executeMySQLWithOutput(
        string $name,
        array $inParams,
        array $outParams,
        bool $fetchAll
    ): array {
        // Prepara variáveis de sessão para OUT params
        $sessionVars = [];
        $outTypes = [];
        $index = 0;

        foreach ($outParams as $key => $paramData) {
            if (is_string($paramData)) {
                $paramName = $key;
                $paramType = $paramData;
            } else {
                $paramName = $paramData['name'] ?? $key;
                $paramType = $paramData['type'] ?? 'string';
            }

            $sessionVar = "@out_{$index}";
            $sessionVars[] = $sessionVar;
            $outTypes[$paramName] = [
                'var' => $sessionVar,
                'type' => $paramType,
                'value' => $paramData['value'] ?? null
            ];
            $index++;
        }

        // Monta placeholders
        $placeholders = [];
        foreach ($inParams as $value) {
            $placeholders[] = '?';
        }

        // Adiciona variáveis OUT
        $allPlaceholders = array_merge($placeholders, $sessionVars);

        $sql = "CALL {$name}(" . implode(', ', $allPlaceholders) . ")";

        if ($this->debug) {
            $this->logQuery($sql, array_merge($inParams, $outParams));
        }

        try {
            // Configura timeout se definido
            if ($this->timeout !== null) {
                $this->applyTimeout($this->timeout);
            }

            $stmt = $this->connection->prepare($sql);
            
            // Bind dos parâmetros IN
            $paramIndex = 1;
            foreach ($inParams as $value) {
                $stmt->bindValue($paramIndex, $value, $this->getPDOType($value));
                $paramIndex++;
            }

            // Bind dos parâmetros OUT (como strings)
            foreach ($sessionVars as $var) {
                $stmt->bindParam($paramIndex, $dummy, PDO::PARAM_STR, 255);
                $paramIndex++;
            }

            $stmt->execute();

            // Recupera valores OUT
            $outputResults = [];
            foreach ($outTypes as $paramName => $outData) {
                $stmtOut = $this->connection->query("SELECT {$outData['var']} as value");
                $row = $stmtOut->fetch(PDO::FETCH_ASSOC);
                $value = $row['value'] ?? null;
                $outputResults[$paramName] = $this->castValue($value, $outData['type']);
                $this->outputs[$paramName] = $outputResults[$paramName];
            }

            // Coleta resultados adicionais
            $additionalResults = [];
            do {
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($result)) {
                    $additionalResults[] = $result;
                }
            } while ($stmt->nextRowset());

            $stmt->closeCursor();

            return [
                'outputs' => $outputResults,
                'results' => $fetchAll ? $additionalResults : ($additionalResults[0] ?? [])
            ];

        } catch (\PDOException $e) {
            throw new Exception("Erro ao executar SP {$name} com OUT params: " . $e->getMessage());
        }
    }

    /**
     * Executa PostgreSQL com parâmetros OUT
     */
    private function executePostgresWithOutput(
        string $name,
        array $inParams,
        array $outParams,
        bool $fetchAll
    ): array {
        // PostgreSQL usa sintaxe: SELECT * FROM function_name(params)
        $placeholders = [];
        $paramIndex = 1;
        $outTypes = [];

        foreach ($inParams as $value) {
            $placeholders[] = '$' . $paramIndex;
            $paramIndex++;
        }

        // Parâmetros OUT em PostgreSQL são parte do SELECT
        $outNames = [];
        foreach ($outParams as $key => $paramData) {
            if (is_string($paramData)) {
                $paramName = $key;
                $paramType = $paramData;
            } else {
                $paramName = $paramData['name'] ?? $key;
                $paramType = $paramData['type'] ?? 'string';
            }
            $outNames[] = $paramName;
            $outTypes[$paramName] = $paramType;
        }

        // Se há OUT params, usa SELECT * FROM
        if (!empty($outNames)) {
            $sql = "SELECT * FROM {$name}(" . implode(', ', $placeholders) . ")";
        } else {
            $sql = "SELECT {$name}(" . implode(', ', $placeholders) . ") as result";
        }

        if ($this->debug) {
            $this->logQuery($sql, $inParams);
        }

        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($inParams as $index => $value) {
                $stmt->bindValue($index + 1, $value, $this->getPDOType($value));
            }

            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Extrai outputs
            $outputResults = [];
            if (!empty($outTypes) && !empty($result)) {
                $row = $result[0];
                foreach ($outTypes as $paramName => $paramType) {
                    $value = $row[$paramName] ?? null;
                    $outputResults[$paramName] = $this->castValue($value, $paramType);
                    $this->outputs[$paramName] = $outputResults[$paramName];
                }
            }

            $stmt->closeCursor();

            return [
                'outputs' => $outputResults,
                'results' => $fetchAll ? $result : ($result[0] ?? [])
            ];

        } catch (\PDOException $e) {
            throw new Exception("Erro ao executar SP {$name} com OUT params: " . $e->getMessage());
        }
    }

    /**
     * Executa genérico com parâmetros OUT (fallback)
     */
    private function executeGenericWithOutput(
        string $name,
        array $inParams,
        array $outParams,
        bool $fetchAll
    ): array {
        // Implementação genérica - pode não funcionar para todos os drivers
        $placeholders = [];
        foreach ($inParams as $value) {
            $placeholders[] = '?';
        }

        $sql = "CALL {$name}(" . implode(', ', $placeholders) . ")";

        if ($this->debug) {
            $this->logQuery($sql, $inParams);
        }

        try {
            $stmt = $this->connection->prepare($sql);
            
            foreach ($inParams as $index => $value) {
                $stmt->bindValue($index + 1, $value, $this->getPDOType($value));
            }

            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            // Para OUT params, tenta recuperar de variáveis de sessão (se suportado)
            $outputResults = [];
            foreach ($outParams as $key => $paramData) {
                if (is_string($paramData)) {
                    $paramName = $key;
                    $paramType = $paramData;
                } else {
                    $paramName = $paramData['name'] ?? $key;
                    $paramType = $paramData['type'] ?? 'string';
                }
                $outputResults[$paramName] = null;
                $this->outputs[$paramName] = null;
            }

            return [
                'outputs' => $outputResults,
                'results' => $fetchAll ? $result : ($result[0] ?? [])
            ];

        } catch (\PDOException $e) {
            throw new Exception("Erro ao executar SP {$name}: " . $e->getMessage());
        }
    }

    /**
     * Executa Function do banco
     * 
     * @param string $name Nome da function
     * @param array $params Parâmetros
     * @return mixed Retorno da function
     * @throws Exception Se function inválida ou erro na execução
     */
    public function executeFunction(string $name, array $params = []): mixed
    {
        // Valida nome da function
        $name = $this->validateProcedureName($name);

        // Verifica se a function existe
        if (!$this->functionExists($name)) {
            throw new Exception("Function '{$name}' não encontrada");
        }

        $placeholders = [];
        $paramIndex = 1;

        // Para PostgreSQL, usa sintaxe com $1, $2, etc.
        if ($this->driver === 'pgsql') {
            foreach ($params as $value) {
                $placeholders[] = '$' . $paramIndex;
                $paramIndex++;
            }
            $sql = "SELECT {$name}(" . implode(', ', $placeholders) . ") as result";
            
            try {
                $stmt = $this->connection->prepare($sql);
                foreach ($params as $index => $value) {
                    $stmt->bindValue($index + 1, $value, $this->getPDOType($value));
                }
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row['result'] ?? null;
            } catch (\PDOException $e) {
                throw new Exception("Erro ao executar function {$name}: " . $e->getMessage());
            }
        }

        // Para MySQL e outros
        foreach ($params as $value) {
            if (is_null($value)) {
                $placeholders[] = 'NULL';
            } elseif (is_int($value) || is_float($value)) {
                $placeholders[] = (string) $value;
            } else {
                $placeholders[] = $this->connection->quote((string) $value);
            }
        }

        $sql = "SELECT {$name}(" . implode(', ', $placeholders) . ") as result";

        if ($this->debug) {
            $this->logQuery($sql, $params);
        }

        try {
            // Configura timeout se definido
            if ($this->timeout !== null) {
                $this->applyTimeout($this->timeout);
            }

            $stmt = $this->connection->query($sql);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row['result'] ?? null;

        } catch (\PDOException $e) {
            throw new Exception("Erro ao executar function {$name}: " . $e->getMessage());
        }
    }

    /**
     * Verifica se Stored Procedure existe
     * 
     * @param string $name Nome da procedure
     * @return bool True se existe
     */
    public function procedureExists(string $name): bool
    {
        $cacheKey = 'proc_' . $name;
        
        if (isset($this->procedureCache[$cacheKey])) {
            return $this->procedureCache[$cacheKey];
        }

        $database = getEnv('DB_DATABASE');

        // Query diferente para PostgreSQL
        if ($this->driver === 'pgsql') {
            $sql = "
                SELECT COUNT(*) as total
                FROM information_schema.routines
                WHERE routine_schema = 'public'
                  AND routine_name = :name
                  AND routine_type = 'PROCEDURE'
            ";
        } else {
            $sql = "
                SELECT COUNT(*) as total
                FROM information_schema.routines
                WHERE routine_schema = :database
                  AND routine_name = :name
                  AND routine_type = 'PROCEDURE'
            ";
        }

        $stmt = $this->connection->prepare($sql);
        $params = ['name' => $name];
        if ($this->driver !== 'pgsql') {
            $params['database'] = $database;
        }
        
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $exists = ($row['total'] ?? 0) > 0;
        
        $this->procedureCache[$cacheKey] = $exists;
        
        return $exists;
    }

    /**
     * Verifica se Function existe
     * 
     * @param string $name Nome da function
     * @return bool True se existe
     */
    public function functionExists(string $name): bool
    {
        $cacheKey = 'func_' . $name;
        
        if (isset($this->procedureCache[$cacheKey])) {
            return $this->procedureCache[$cacheKey];
        }

        $database = getEnv('DB_DATABASE');

        // Query diferente para PostgreSQL
        if ($this->driver === 'pgsql') {
            $sql = "
                SELECT COUNT(*) as total
                FROM information_schema.routines
                WHERE routine_schema = 'public'
                  AND routine_name = :name
                  AND routine_type = 'FUNCTION'
            ";
        } else {
            $sql = "
                SELECT COUNT(*) as total
                FROM information_schema.routines
                WHERE routine_schema = :database
                  AND routine_name = :name
                  AND routine_type = 'FUNCTION'
            ";
        }

        $stmt = $this->connection->prepare($sql);
        $params = ['name' => $name];
        if ($this->driver !== 'pgsql') {
            $params['database'] = $database;
        }
        
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $exists = ($row['total'] ?? 0) > 0;
        
        $this->procedureCache[$cacheKey] = $exists;
        
        return $exists;
    }

    /**
     * Retorna informações sobre uma Stored Procedure
     * 
     * @param string $name Nome da procedure
     * @param bool $useCache Se deve usar cache
     * @return array|null Informações ou null
     */
    public function getProcedureInfo(string $name, bool $useCache = true): ?array
    {
        $cacheKey = 'info_' . $name;
        
        if ($useCache && isset($this->procedureCache[$cacheKey])) {
            return $this->procedureCache[$cacheKey];
        }

        $database = getEnv('DB_DATABASE');

        if ($this->driver === 'pgsql') {
            $sql = "
                SELECT 
                    routine_name,
                    routine_definition,
                    created as created,
                    last_altered as last_altered
                FROM information_schema.routines
                WHERE routine_schema = 'public'
                  AND routine_name = :name
                  AND routine_type = 'PROCEDURE'
            ";
        } else {
            $sql = "
                SELECT *
                FROM information_schema.routines
                WHERE routine_schema = :database
                  AND routine_name = :name
                  AND routine_type = 'PROCEDURE'
            ";
        }

        $stmt = $this->connection->prepare($sql);
        $params = ['name' => $name];
        if ($this->driver !== 'pgsql') {
            $params['database'] = $database;
        }
        
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($useCache) {
            $this->procedureCache[$cacheKey] = $result ?: null;
        }
        
        return $result ?: null;
    }

    /**
     * Retorna parâmetros de uma Stored Procedure
     * 
     * @param string $name Nome da procedure
     * @param bool $useCache Se deve usar cache
     * @return array Lista de parâmetros
     */
    public function getProcedureParameters(string $name, bool $useCache = true): array
    {
        $cacheKey = 'params_' . $name;
        
        if ($useCache && isset($this->procedureCache[$cacheKey])) {
            return $this->procedureCache[$cacheKey];
        }

        $database = getEnv('DB_DATABASE');

        if ($this->driver === 'pgsql') {
            // PostgreSQL - parâmetros estão em pg_proc
            $sql = "
                SELECT 
                    proname as parameter_name,
                    'IN' as parameter_mode,
                    typname as data_type
                FROM pg_proc p
                JOIN pg_type t ON p.prorettype = t.oid
                WHERE proname = :name
            ";
        } else {
            $sql = "
                SELECT 
                    parameter_name,
                    parameter_mode,
                    data_type,
                    character_maximum_length,
                    numeric_precision,
                    numeric_scale
                FROM information_schema.parameters
                WHERE specific_schema = :database
                  AND specific_name = :name
                ORDER BY ordinal_position
            ";
        }

        $stmt = $this->connection->prepare($sql);
        $params = ['name' => $name];
        if ($this->driver !== 'pgsql') {
            $params['database'] = $database;
        }
        
        $stmt->execute($params);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($useCache) {
            $this->procedureCache[$cacheKey] = $result;
        }
        
        return $result;
    }

    /**
     * Limpa outputs
     * 
     * @return self
     */
    public function clearOutputs(): self
    {
        $this->outputs = [];
        return $this;
    }

    /**
     * Retorna outputs
     * 
     * @return array
     */
    public function getOutputs(): array
    {
        return $this->outputs;
    }

    /**
     * Habilita debug
     * 
     * @param bool $debug
     * @return self
     */
    public function setDebug(bool $debug = true): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Define timeout da execução
     * 
     * @param int|null $seconds Timeout em segundos
     * @return self
     */
    public function setTimeout(?int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Limpa cache
     * 
     * @return self
     */
    public function clearCache(): self
    {
        $this->procedureCache = [];
        return $this;
    }

    /**
     * Valida nome da procedure
     * 
     * @param string $name
     * @return string
     * @throws Exception Se nome inválido
     */
    private function validateProcedureName(string $name): string
    {
        // Remove caracteres inválidos
        $cleanName = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
        
        if (empty($cleanName)) {
            throw new Exception("Nome de procedure inválido: {$name}");
        }
        
        return $cleanName;
    }

    /**
     * Obtém tipo PDO para binding
     * 
     * @param mixed $value
     * @return int
     */
    private function getPDOType(mixed $value): int
    {
        return match (true) {
            is_int($value)  => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default         => PDO::PARAM_STR,
        };
    }

    /**
     * Converte valor para o tipo especificado
     * 
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    private function castValue(mixed $value, string $type): mixed
    {
        return match (strtolower($type)) {
            'int', 'integer' => (int) $value,
            'float', 'double', 'decimal' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => json_decode((string) $value, true) ?? $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    /**
     * Log de query para debug
     * 
     * @param string $sql
     * @param array $params
     */
    private function logQuery(string $sql, array $params = []): void
    {
        $log = sprintf(
            "[%s] SP Executor: %s | Params: %s\n",
            date('Y-m-d H:i:s'),
            $sql,
            json_encode($params)
        );
        error_log($log);
    }

    /**
     * Aplica timeout no banco (método interno)
     * 
     * @param int $seconds
     */
    private function applyTimeout(int $seconds): void
    {
        switch ($this->driver) {
            case 'mysql':
            case 'mysqli':
                $this->connection->exec("SET SESSION wait_timeout = {$seconds}");
                break;
            case 'pgsql':
                $this->connection->exec("SET statement_timeout = " . ($seconds * 1000));
                break;
            default:
                // Outros drivers podem não suportar timeout
                break;
        }
    }

    /**
     * Lista todas as Stored Procedures do banco
     * 
     * @param string $pattern Filtro opcional (LIKE)
     * @return array Lista de procedures
     */
    public function listProcedures(string $pattern = null): array
    {
        $database = getEnv('DB_DATABASE');

        if ($this->driver === 'pgsql') {
            $sql = "
                SELECT 
                    proname as routine_name,
                    'PostgreSQL function' as routine_definition,
                    'created' as created,
                    'created' as last_altered
                FROM pg_proc
                WHERE proname NOT LIKE 'pg_%'
            ";
        } else {
            $sql = "
                SELECT 
                    routine_name,
                    routine_definition,
                    created,
                    last_altered
                FROM information_schema.routines
                WHERE routine_schema = :database
                  AND routine_type = 'PROCEDURE'
            ";
        }
        
        if ($pattern !== null) {
            if ($this->driver === 'pgsql') {
                $sql .= " AND proname LIKE :pattern";
            } else {
                $sql .= " AND routine_name LIKE :pattern";
            }
        }
        
        if ($this->driver !== 'pgsql') {
            $sql .= " ORDER BY routine_name";
        } else {
            $sql .= " ORDER BY proname";
        }
        
        $stmt = $this->connection->prepare($sql);
        $params = [];
        if ($this->driver !== 'pgsql') {
            $params['database'] = $database;
        }
        if ($pattern !== null) {
            $params['pattern'] = $pattern;
        }
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista todas as Functions do banco
     * 
     * @param string $pattern Filtro opcional (LIKE)
     * @return array Lista de functions
     */
    public function listFunctions(string $pattern = null): array
    {
        $database = getEnv('DB_DATABASE');

        if ($this->driver === 'pgsql') {
            $sql = "
                SELECT 
                    proname as routine_name,
                    'PostgreSQL function' as routine_definition,
                    'created' as created,
                    'created' as last_altered
                FROM pg_proc
                WHERE proname NOT LIKE 'pg_%'
            ";
        } else {
            $sql = "
                SELECT 
                    routine_name,
                    routine_definition,
                    created,
                    last_altered
                FROM information_schema.routines
                WHERE routine_schema = :database
                  AND routine_type = 'FUNCTION'
            ";
        }
        
        if ($pattern !== null) {
            if ($this->driver === 'pgsql') {
                $sql .= " AND proname LIKE :pattern";
            } else {
                $sql .= " AND routine_name LIKE :pattern";
            }
        }
        
        if ($this->driver !== 'pgsql') {
            $sql .= " ORDER BY routine_name";
        } else {
            $sql .= " ORDER BY proname";
        }
        
        $stmt = $this->connection->prepare($sql);
        $params = [];
        if ($this->driver !== 'pgsql') {
            $params['database'] = $database;
        }
        if ($pattern !== null) {
            $params['pattern'] = $pattern;
        }
        
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém o código fonte de uma procedure
     * 
     * @param string $name Nome da procedure
     * @return string|null Código fonte ou null
     */
    public function getProcedureSource(string $name): ?string
    {
        $info = $this->getProcedureInfo($name);
        return $info['ROUTINE_DEFINITION'] ?? $info['routine_definition'] ?? null;
    }
}