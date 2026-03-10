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
     * Define colunas permitidas
     * 
     * @param array $columns Lista de colunas permitidas
     * @return self
     */
    public function setAllowedColumns(array $columns): self
    {
        $this->allowedColumns = $columns;
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
        $this->blockedColumns = $columns;
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
        $this->allowedFunctions = $functions;
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
        $this->blockedFunctions = $functions;
        return $this;
    }

    /**
     * Verifica se uma coluna individual é permitida
     * 
     * @param string $column Nome da coluna
     * @return bool True se permitida
     */
    public function isColumnAllowed(string $column): bool
    {
        // Se está na lista de bloqueadas, retorna false
        if (in_array($column, $this->blockedColumns)) {
            return false;
        }

        // Se há lista de permitidas e a coluna não está nela, retorna false
        if (!empty($this->allowedColumns) && !in_array($column, $this->allowedColumns)) {
            return false;
        }

        return true;
    }

    /**
     * Valida múltiplas colunas
     * 
     * @param array $columns Lista de colunas a validar
     * @return array Colunas válidas
     * @throws Exception Se houver colunas inválidas
     */
    public function validateColumns(array $columns): array
    {
        $validColumns = [];

        foreach ($columns as $column) {
            if ($this->isColumnAllowed($column)) {
                $validColumns[] = $column;
            }
        }

        // Se há lista de permitidas, verifica se todas as colunas são válidas
        if (!empty($this->allowedColumns)) {
            $invalidColumns = array_diff($columns, $this->allowedColumns);
            if (!empty($invalidColumns)) {
                throw new Exception(
                    "Colunas inválidas selecionadas: " . implode(', ', $invalidColumns)
                );
            }
        }

        // Verifica se há colunas bloqueadas
        if (!empty($this->blockedColumns)) {
            $blockedFound = array_intersect($columns, $this->blockedColumns);
            if (!empty($blockedFound)) {
                throw new Exception(
                    "Colunas bloqueadas selecionadas: " . implode(', ', $blockedFound)
                );
            }
        }

        return $validColumns;
    }

    /**
     * Valida colunas em expressões JOIN
     * 
     * @param string $expression Expressão SQL com JOINs
     * @return string Expressão validada
     * @throws Exception Se houver colunas inválidas
     */
    public function validateJoinExpression(string $expression): string
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
            $this->validateColumns($columnsInQuery);
        }

        return $expression;
    }

    /**
     * Verifica se uma função é permitida
     * 
     * @param string $function Nome da função
     * @return bool True se permitida
     */
    public function isFunctionAllowed(string $function): bool
    {
        // Se está na lista de bloqueadas, retorna false
        if (in_array($function, $this->blockedFunctions)) {
            return false;
        }

        // Se há lista de permitidas e a função não está nela, retorna false
        if (!empty($this->allowedFunctions) && !in_array($function, $this->allowedFunctions)) {
            return false;
        }

        return true;
    }

    /**
     * Valida expressão contendo funções SQL
     * 
     * @param string $expression Expressão SQL
     * @return bool True se válida
     */
    public function validateFunctionExpression(string $expression): bool
    {
        $functions = Validator::extractSQLFunctions($expression);

        if (empty($functions)) {
            return true;
        }

        foreach ($functions as $function) {
            if (!$this->isFunctionAllowed($function)) {
                return false;
            }
        }

        return true;
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
}
