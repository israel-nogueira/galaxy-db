<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Security;

use Exception;
use RuntimeException;

/**
 * -------------------------------------------------------------------------
 * Class ColumnGuard
 * -------------------------------------------------------------------------
 * 
 * Gerencia permissões de acesso a colunas e funções MySQL.
 * Permite definir listas de colunas permitidas ou bloqueadas.
 * 
 * @package IsraelNogueira\galaxyDB\Security
 * @author Israel Nogueira <israel@feats.com>
 * @license GPL-3.0-or-later
 * @copyright 2023 Israel Nogueira
 * -------------------------------------------------------------------------
 */
class ColumnGuard
{
    /**
     * Colunas permitidas para acesso
     * 
     * @var array
     */
    private array $allowedColumns = [];

    /**
     * Colunas bloqueadas para acesso
     * 
     * @var array
     */
    private array $blockedColumns = [];

    /**
     * Funções MySQL permitidas
     * 
     * @var array
     */
    private array $allowedFunctions = [];

    /**
     * Funções MySQL bloqueadas
     * 
     * @var array
     */
    private array $blockedFunctions = [];

    /**
     * Colunas sensíveis que devem sempre ser bloqueadas
     */
    private const SENSITIVE_COLUMNS = [
        'password',
        'senha',
        'hash',
        'token',
        'api_key',
        'secret',
        'credit_card',
        'cartao',
        'cpf',
        'cnpj',
        'rg',
        'passport'
    ];

    /**
     * Funções sensíveis que devem sempre ser bloqueadas
     */
    private const SENSITIVE_FUNCTIONS = [
        'SLEEP',
        'BENCHMARK',
        'LOAD_FILE',
        'INTO OUTFILE',
        'INTO DUMPFILE',
        'GET_LOCK',
        'RELEASE_LOCK',
        'IS_FREE_LOCK',
        'MASTER_POS_WAIT'
    ];

    /**
     * Define colunas permitidas
     * 
     * @param array $columns Lista de colunas permitidas
     * @return self
     */
    public function setAllowedColumns(array $columns): self
    {
        $this->allowedColumns = array_map('strtolower', $columns);
        return $this;
    }

    /**
     * Define colunas bloqueadas
     * 
     * @param array $columns Lista de colunas bloqueadas
     * @return self
     */
    public function setBlockedColumns(array $columns): self
    {
        $this->blockedColumns = array_map('strtolower', $columns);
        return $this;
    }

    /**
     * Define funções permitidas
     * 
     * @param array $functions Lista de funções permitidas
     * @return self
     */
    public function setAllowedFunctions(array $functions): self
    {
        $this->allowedFunctions = array_map('strtoupper', $functions);
        return $this;
    }

    /**
     * Define funções bloqueadas
     * 
     * @param array $functions Lista de funções bloqueadas
     * @return self
     */
    public function setBlockedFunctions(array $functions): self
    {
        $this->blockedFunctions = array_map('strtoupper', $functions);
        return $this;
    }

    /**
     * Adiciona colunas sensíveis automaticamente bloqueadas
     * 
     * @param bool $enable Se deve ativar bloqueio de colunas sensíveis
     * @return self
     */
    public function enableSensitiveBlock(bool $enable = true): self
    {
        if ($enable) {
            $this->blockedColumns = array_merge(
                $this->blockedColumns,
                self::SENSITIVE_COLUMNS
            );
            $this->blockedFunctions = array_merge(
                $this->blockedFunctions,
                self::SENSITIVE_FUNCTIONS
            );
        }
        return $this;
    }

    /**
     * Verifica se uma coluna individual é permitida
     * 
     * @param string $column Nome da coluna
     * @param bool $caseSensitive Se deve considerar maiúsculas/minúsculas
     * @return bool True se permitida
     */
    public function isColumnAllowed(string $column, bool $caseSensitive = false): bool
    {
        $checkColumn = $caseSensitive ? $column : strtolower($column);
        $allowedColumns = $caseSensitive ? $this->allowedColumns : array_map('strtolower', $this->allowedColumns);
        $blockedColumns = $caseSensitive ? $this->blockedColumns : array_map('strtolower', $this->blockedColumns);

        // Se está na lista de bloqueadas, retorna false
        if (in_array($checkColumn, $blockedColumns)) {
            return false;
        }

        // Se há lista de permitidas e a coluna não está nela, retorna false
        if (!empty($allowedColumns) && !in_array($checkColumn, $allowedColumns)) {
            return false;
        }

        return true;
    }

    /**
     * Valida múltiplas colunas
     * 
     * @param array $columns Lista de colunas a validar
     * @param bool $strict Se deve lançar exceção para colunas inválidas
     * @return array Colunas válidas
     * @throws Exception Se houver colunas inválidas e $strict for true
     */
    public function validateColumns(array $columns, bool $strict = true): array
    {
        $validColumns = [];
        $invalidColumns = [];

        foreach ($columns as $column) {
            // Extrai nome puro da coluna (remove alias)
            $pureColumn = $this->extractPureColumnName($column);
            
            if ($this->isColumnAllowed($pureColumn)) {
                $validColumns[] = $column;
            } else {
                $invalidColumns[] = $column;
            }
        }

        if ($strict && !empty($invalidColumns)) {
            throw new Exception(
                "Colunas inválidas selecionadas: " . implode(', ', $invalidColumns)
            );
        }

        return $validColumns;
    }

    /**
     * Extrai nome puro da coluna (remove tabela.alias)
     * 
     * @param string $column Nome da coluna
     * @return string Nome puro
     */
    private function extractPureColumnName(string $column): string
    {
        // Remove alias AS
        if (preg_match('/\s+AS\s+(\w+)\s*$/i', $column, $matches)) {
            return strtolower($matches[1]);
        }

        // Remove tabela.coluna -> coluna
        if (strpos($column, '.') !== false) {
            $parts = explode('.', $column);
            return strtolower(end($parts));
        }

        return strtolower(trim($column));
    }

    /**
     * Valida colunas em expressões JOIN
     * 
     * @param string $expression Expressão SQL com JOINs
     * @param bool $strict Se deve lançar exceção
     * @return string Expressão validada
     * @throws Exception Se houver colunas inválidas e $strict for true
     */
    public function validateJoinExpression(string $expression, bool $strict = true): string
    {
        // Extrai colunas no formato tabela.coluna
        $pattern = '/([a-zA-Z0-9_]+\.[a-zA-Z0-9_]+)/';
        preg_match_all($pattern, $expression, $matches);
        $columnsInQuery = $matches[1] ?? [];

        if (empty($columnsInQuery)) {
            return $expression;
        }

        // Valida apenas se há restrições configuradas
        if (!empty($this->allowedColumns) || !empty($this->blockedColumns)) {
            $this->validateColumns($columnsInQuery, $strict);
        }

        return $expression;
    }

    /**
     * Verifica se uma função é permitida
     * 
     * @param string $function Nome da função
     * @param bool $caseSensitive Se deve considerar maiúsculas/minúsculas
     * @return bool True se permitida
     */
    public function isFunctionAllowed(string $function, bool $caseSensitive = false): bool
    {
        $checkFunction = $caseSensitive ? $function : strtoupper($function);
        $allowedFunctions = $caseSensitive ? $this->allowedFunctions : array_map('strtoupper', $this->allowedFunctions);
        $blockedFunctions = $caseSensitive ? $this->blockedFunctions : array_map('strtoupper', $this->blockedFunctions);

        // Se está na lista de bloqueadas, retorna false
        if (in_array($checkFunction, $blockedFunctions)) {
            return false;
        }

        // Se há lista de permitidas e a função não está nela, retorna false
        if (!empty($allowedFunctions) && !in_array($checkFunction, $allowedFunctions)) {
            return false;
        }

        return true;
    }

    /**
     * Valida expressão contendo funções SQL
     * 
     * @param string $expression Expressão SQL
     * @param bool $strict Se deve lançar exceção
     * @return bool True se válida
     * @throws Exception Se $strict for true e função inválida
     */
    public function validateFunctionExpression(string $expression, bool $strict = true): bool
    {
        $functions = Validator::extractSQLFunctions($expression);

        if (empty($functions)) {
            return true;
        }

        foreach ($functions as $function) {
            if (!$this->isFunctionAllowed($function)) {
                if ($strict) {
                    throw new Exception(
                        "Função MySQL bloqueada: {$function}"
                    );
                }
                return false;
            }
        }

        return true;
    }

    /**
     * Valida coluna com segurança (lança exceção se inválida)
     * 
     * @param string $column Nome da coluna
     * @return string Coluna validada
     * @throws Exception Se coluna inválida
     */
    public function validateColumn(string $column): string
    {
        $pureColumn = $this->extractPureColumnName($column);
        
        if (!$this->isColumnAllowed($pureColumn)) {
            throw new Exception(
                "Acesso negado à coluna: {$column}"
            );
        }

        return $column;
    }

    /**
     * Verifica se uma coluna é sensível
     * 
     * @param string $column Nome da coluna
     * @return bool True se é sensível
     */
    public function isSensitiveColumn(string $column): bool
    {
        $pureColumn = strtolower($this->extractPureColumnName($column));
        return in_array($pureColumn, self::SENSITIVE_COLUMNS);
    }

    /**
     * Obtém colunas permitidas
     * 
     * @return array
     */
    public function getAllowedColumns(): array
    {
        return $this->allowedColumns;
    }

    /**
     * Obtém colunas bloqueadas
     * 
     * @return array
     */
    public function getBlockedColumns(): array
    {
        return $this->blockedColumns;
    }

    /**
     * Obtém funções permitidas
     * 
     * @return array
     */
    public function getAllowedFunctions(): array
    {
        return $this->allowedFunctions;
    }

    /**
     * Obtém funções bloqueadas
     * 
     * @return array
     */
    public function getBlockedFunctions(): array
    {
        return $this->blockedFunctions;
    }

    /**
     * Reseta todas as configurações
     * 
     * @return self
     */
    public function reset(): self
    {
        $this->allowedColumns = [];
        $this->blockedColumns = [];
        $this->allowedFunctions = [];
        $this->blockedFunctions = [];
        
        return $this;
    }

    /**
     * Verifica se há alguma restrição configurada
     * 
     * @return bool True se há restrições
     */
    public function hasRestrictions(): bool
    {
        return !empty($this->allowedColumns) || 
               !empty($this->blockedColumns) || 
               !empty($this->allowedFunctions) || 
               !empty($this->blockedFunctions);
    }

    /**
     * Valida se uma expressão SQL completa é segura
     * 
     * @param string $sql Expressão SQL
     * @param bool $strict Se deve lançar exceção
     * @return bool True se segura
     * @throws Exception Se $strict for true e detectar perigo
     */
    public function validateSQL(string $sql, bool $strict = true): bool
    {
        // Verifica palavras perigosas
        if (Validator::hasDangerousKeywords($sql)) {
            if ($strict) {
                throw new Exception("SQL contém palavras-chave perigosas");
            }
            return false;
        }

        // Verifica padrões de injection
        if (Validator::hasInjectionPatterns($sql)) {
            if ($strict) {
                throw new Exception("SQL contém padrões de injection");
            }
            return false;
        }

        // Valida funções
        if (!$this->validateFunctionExpression($sql, $strict)) {
            return false;
        }

        return true;
    }

    /**
     * Valida array de parâmetros para binding
     * 
     * @param array $params Parâmetros a validar
     * @param bool $strict Se deve lançar exceção
     * @return bool True se válidos
     * @throws Exception Se $strict for true e detectar perigo
     */
    public function validateBindings(array $params, bool $strict = true): bool
    {
        foreach ($params as $key => $value) {
            // Valida chave do binding
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                if ($strict) {
                    throw new Exception("Nome de binding inválido: {$key}");
                }
                return false;
            }

            // Se for string, verifica segurança
            if (is_string($value) && !Validator::isSafe($value)) {
                if ($strict) {
                    throw new Exception("Valor de binding contém caracteres perigosos");
                }
                return false;
            }
        }

        return true;
    }
}