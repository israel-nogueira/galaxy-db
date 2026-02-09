<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\StoredProcedures;

use PDO;

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
     * Construtor
     * 
     * @param PDO $connection Conexão PDO ativa
     */
    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Executa Stored Procedure
     * 
     * @param string $name Nome da procedure
     * @param array $params Parâmetros
     * @return mixed Resultado da execução
     */
    public function execute(string $name, array $params = []): mixed
    {
        // Monta placeholders
        $placeholders = [];
        foreach ($params as $key => $value) {
            $placeholders[] = '?';
        }

        $sql = "CALL {$name}(" . implode(', ', $placeholders) . ")";

        try {
            $stmt = $this->connection->prepare($sql);

            // Bind dos parâmetros
            foreach ($params as $index => $value) {
                $stmt->bindValue($index + 1, $value);
            }

            $stmt->execute();

            // Retorna resultado
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fecha cursor para permitir próximas queries
            $stmt->closeCursor();

            return $result;

        } catch (\PDOException $e) {
            throw new \Exception("Erro ao executar SP {$name}: " . $e->getMessage());
        }
    }

    /**
     * Executa Stored Procedure com parâmetros OUT
     * 
     * @param string $name Nome da procedure
     * @param array $inParams Parâmetros IN
     * @param array $outParams Parâmetros OUT (nome => tipo)
     * @return array Valores dos parâmetros OUT
     */
    public function executeWithOutput(
        string $name,
        array $inParams = [],
        array $outParams = []
    ): array {
        // Prepara variáveis de sessão para OUT params
        $sessionVars = [];
        foreach (array_keys($outParams) as $index => $paramName) {
            $sessionVars[] = "@out_{$index}";
        }

        // Monta placeholders
        $placeholders = [];
        foreach ($inParams as $value) {
            $placeholders[] = $this->connection->quote($value);
        }

        // Adiciona variáveis OUT
        $placeholders = array_merge($placeholders, $sessionVars);

        // Executa procedure
        $sql = "CALL {$name}(" . implode(', ', $placeholders) . ")";
        $this->connection->exec($sql);

        // Recupera valores OUT
        $results = [];
        foreach ($sessionVars as $index => $var) {
            $stmt = $this->connection->query("SELECT {$var} as value");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $paramName = array_keys($outParams)[$index];
            $results[$paramName] = $row['value'] ?? null;
        }

        return $results;
    }

    /**
     * Executa Function do banco
     * 
     * @param string $name Nome da function
     * @param array $params Parâmetros
     * @return mixed Retorno da function
     */
    public function executeFunction(string $name, array $params = []): mixed
    {
        $placeholders = [];
        foreach ($params as $value) {
            $placeholders[] = $this->connection->quote($value);
        }

        $sql = "SELECT {$name}(" . implode(', ', $placeholders) . ") as result";

        try {
            $stmt = $this->connection->query($sql);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row['result'] ?? null;

        } catch (\PDOException $e) {
            throw new \Exception("Erro ao executar function {$name}: " . $e->getMessage());
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
        $database = getEnv('DB_DATABASE');

        $sql = "
            SELECT COUNT(*) as total
            FROM information_schema.routines
            WHERE routine_schema = :database
              AND routine_name = :name
              AND routine_type = 'PROCEDURE'
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'database' => $database,
            'name' => $name
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($row['total'] ?? 0) > 0;
    }

    /**
     * Verifica se Function existe
     * 
     * @param string $name Nome da function
     * @return bool True se existe
     */
    public function functionExists(string $name): bool
    {
        $database = getEnv('DB_DATABASE');

        $sql = "
            SELECT COUNT(*) as total
            FROM information_schema.routines
            WHERE routine_schema = :database
              AND routine_name = :name
              AND routine_type = 'FUNCTION'
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'database' => $database,
            'name' => $name
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($row['total'] ?? 0) > 0;
    }

    /**
     * Retorna informações sobre uma Stored Procedure
     * 
     * @param string $name Nome da procedure
     * @return array|null Informações ou null
     */
    public function getProcedureInfo(string $name): ?array
    {
        $database = getEnv('DB_DATABASE');

        $sql = "
            SELECT *
            FROM information_schema.routines
            WHERE routine_schema = :database
              AND routine_name = :name
              AND routine_type = 'PROCEDURE'
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'database' => $database,
            'name' => $name
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Retorna parâmetros de uma Stored Procedure
     * 
     * @param string $name Nome da procedure
     * @return array Lista de parâmetros
     */
    public function getProcedureParameters(string $name): array
    {
        $database = getEnv('DB_DATABASE');

        $sql = "
            SELECT 
                parameter_name,
                parameter_mode,
                data_type
            FROM information_schema.parameters
            WHERE specific_schema = :database
              AND specific_name = :name
            ORDER BY ordinal_position
        ";

        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'database' => $database,
            'name' => $name
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
}
