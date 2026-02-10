<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Query;

trait QueryBuilder
{
    protected ?string $where = null;
    protected array $whereBindings = [];
    protected ?string $having = null;
    protected ?string $order = null;
    protected array $setorder = [];
    protected ?string $limit = null;
    protected array $group = [];
    protected ?string $DISTINCT = null;
    protected array $InsertVars = [];
    protected array $Insert_Update = [];
    protected array $on_duplicate = [];
    protected array $ignore = [];
    protected array $selectColumns = [];
    protected array $joins = [];

    public function table(string $table): self
    {
        $this->tableClass = $this->validateIdentifier($table);
        return $this;
    }

    public function colum(string|array $column): self
    {
        // Se for array, adiciona múltiplas colunas
        if (is_array($column)) {
            foreach ($column as $col) {
                $this->selectColumns[] = $col;
            }
        } else {
            // String: adiciona uma coluna
            $this->selectColumns[] = $column;
        }
        return $this;
    }

    public function join(string $type, string $table, string $condition): self
    {
        // Aceita: LEFT, RIGHT, INNER, CROSS
        $type = strtoupper(trim($type));
        $validTypes = ['LEFT', 'RIGHT', 'INNER', 'CROSS', 'LEFT OUTER', 'RIGHT OUTER'];
        
        if (!in_array($type, $validTypes)) {
            $type = 'INNER';
        }
        
        $this->joins[] = "{$type} JOIN {$table} ON {$condition}";
        return $this;
    }

    public function where(string $column, mixed $value = null, string $operator = '='): self
    {
        // Se só passou 1 parâmetro: where("US.ID = 5") - SQL direto
        if ($value === null && func_num_args() === 1) {
            if ($this->where === null) {
                $this->where = $column;
            } else {
                $this->where .= " AND ({$column})";
            }
            return $this;
        }
        
        // Modo normal: where("coluna", "valor", "=")
        $column = $this->validateIdentifier($column);
        $placeholder = ':w_' . count($this->whereBindings);
        
        $condition = "`{$column}` {$operator} {$placeholder}";
        $this->whereBindings[$placeholder] = $value;

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " AND {$condition}";
        }

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            return $this;
        }

        $column = $this->validateIdentifier($column);
        $placeholders = [];
        
        foreach ($values as $i => $value) {
            $placeholder = ':win_' . count($this->whereBindings);
            $placeholders[] = $placeholder;
            $this->whereBindings[$placeholder] = $value;
        }

        $condition = "`{$column}` IN (" . implode(', ', $placeholders) . ")";

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " AND {$condition}";
        }

        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $column = $this->validateIdentifier($column);
        $placeholderMin = ':wbt_min_' . count($this->whereBindings);
        $placeholderMax = ':wbt_max_' . count($this->whereBindings);
        
        $this->whereBindings[$placeholderMin] = $min;
        $this->whereBindings[$placeholderMax] = $max;

        $condition = "`{$column}` BETWEEN {$placeholderMin} AND {$placeholderMax}";

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " AND {$condition}";
        }

        return $this;
    }

    public function whereLike(string $column, string $pattern): self
    {
        $column = $this->validateIdentifier($column);
        $placeholder = ':wl_' . count($this->whereBindings);
        
        $this->whereBindings[$placeholder] = $pattern;
        $condition = "`{$column}` LIKE {$placeholder}";

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " AND {$condition}";
        }

        return $this;
    }

    public function whereNull(string $column): self
    {
        $column = $this->validateIdentifier($column);
        $condition = "`{$column}` IS NULL";

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " AND {$condition}";
        }

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $column = $this->validateIdentifier($column);
        $condition = "`{$column}` IS NOT NULL";

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " AND {$condition}";
        }

        return $this;
    }

    public function orWhere(string $column, mixed $value, string $operator = '='): self
    {
        $column = $this->validateIdentifier($column);
        $placeholder = ':ow_' . count($this->whereBindings);
        
        $condition = "`{$column}` {$operator} {$placeholder}";
        $this->whereBindings[$placeholder] = $value;

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " OR {$condition}";
        }

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        
        if (!in_array($direction, ['ASC', 'DESC'])) {
            throw new \InvalidArgumentException("Direção inválida: {$direction}");
        }

        // Se tem ponto (alias.coluna), não adiciona backticks
        if (strpos($column, '.') !== false) {
            $this->setorder[] = "{$column} {$direction}";
        } else {
            $column = $this->validateIdentifier($column);
            $this->setorder[] = "`{$column}` {$direction}";
        }
        
        return $this;
    }

    /**
     * Alias para orderBy (compatibilidade)
     */
    public function order(string $column, string $direction = 'ASC'): self
    {
        return $this->orderBy($column, $direction);
    }

    public function limit(int $limit, ?int $offset = null): self
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException("Limit não pode ser negativo");
        }

        $this->limit = $offset !== null 
            ? "{$offset}, {$limit}" 
            : (string) $limit;
            
        return $this;
    }

    public function groupBy(string $column): self
    {
        // Se tem ponto (alias.coluna), não adiciona backticks
        if (strpos($column, '.') !== false) {
            $this->group[] = $column;
        } else {
            $column = $this->validateIdentifier($column);
            $this->group[] = "`{$column}`";
        }
        return $this;
    }

    public function distinct(): self
    {
        $this->DISTINCT = 'DISTINCT';
        return $this;
    }

    protected function buildWhere(): string
    {
        return $this->where !== null ? " WHERE {$this->where}" : '';
    }

    protected function buildOrderBy(): string
    {
        if (empty($this->setorder)) {
            return '';
        }

        return ' ORDER BY ' . implode(', ', $this->setorder);
    }

    protected function buildLimit(): string
    {
        return $this->limit !== null ? " LIMIT {$this->limit}" : '';
    }

    protected function buildGroupBy(): string
    {
        if (empty($this->group)) {
            return '';
        }

        return ' GROUP BY ' . implode(', ', $this->group);
    }

    protected function buildJoins(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        return ' ' . implode(' ', $this->joins);
    }

    protected function resetBuilder(): self
    {
        $this->where = null;
        $this->whereBindings = [];
        $this->having = null;
        $this->order = null;
        $this->limit = null;
        $this->setorder = [];
        $this->group = [];
        $this->DISTINCT = null;
        $this->InsertVars = [];
        $this->Insert_Update = [];
        $this->on_duplicate = [];
        $this->ignore = [];
        $this->selectColumns = [];
        $this->joins = [];

        return $this;
    }

    protected function setInsertValue(string $column, mixed $value): void
    {
        $column = $this->validateIdentifier($column);
        $this->InsertVars[$column] = $value;
    }

    protected function setUpdateValue(string $column, mixed $value): void
    {
        $column = $this->validateIdentifier($column);
        $this->Insert_Update[$column] = $value;
    }
}
