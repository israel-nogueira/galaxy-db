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
    protected array $jsonDecodeColumns = [];
    protected ?string $lockMode = null;
    protected array $unions = [];

    public function getSelectColumns(): array
    {
        return $this->selectColumns;
    }

    public function getHaving(): ?string
    {
        return $this->having;
    }

    public function having(string $clause): self
    {
        // Valida HAVING básico
        if (stripos($clause, ';') !== false) {
            throw new \InvalidArgumentException("Cláusula HAVING inválida");
        }
        
        $this->having = ($this->having ? $this->having . ' AND ' : '') . $clause;
        return $this;
    }

    public function appendHaving(string $clause): void
    {
        $this->having($clause);
    }

    public function table(string $table): self
    {
        $this->tableClass = $this->validateIdentifier($table);
        return $this;
    }

    public function colum(string|array $column, bool $decode = false): self
    {
        if (is_array($column)) {
            foreach ($column as $col) {
                $this->selectColumns[] = $col;
            }
        } else {
            $this->selectColumns[] = $column;
            if ($decode) {
                // Extrai nome real da coluna (ignora alias AS xxx e prefixo TABELA.)
                preg_match('/(?:[\w]+\.)?([\w]+)(?:\s+AS\s+[\w]+)?$/i', $column, $m);
                $this->jsonDecodeColumns[] = $m[1] ?? $column;
            }
        }
        return $this;
    }

    public function join(string $type, string $table, string $condition): self
    {
        $type = strtoupper(trim($type));
        $validTypes = ['LEFT', 'RIGHT', 'INNER', 'CROSS', 'LEFT OUTER', 'RIGHT OUTER'];

        if (!in_array($type, $validTypes)) {
            $type = 'INNER';
        }

        // Valida tabela e condição básica
        if (stripos($table, ';') !== false || stripos($condition, ';') !== false) {
            throw new \InvalidArgumentException("JOIN contém caracteres inválidos");
        }

        $this->joins[] = "{$type} JOIN {$table} ON {$condition}";
        return $this;
    }

    public function where(string $column, mixed $value = null, string $operator = '='): self
    {
        if ($value === null && func_num_args() === 1) {
            // Raw expression — passa direto sem backticks
            if (stripos($column, ';') !== false) {
                throw new \InvalidArgumentException("Expressão WHERE inválida");
            }
            
            if ($this->where === null) {
                $this->where = $column;
            } else {
                $this->where .= " AND ({$column})";
            }
            return $this;
        }

        // Valida operador
        $validOperators = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'RLIKE', 'REGEXP'];
        if (!in_array(strtoupper($operator), $validOperators)) {
            throw new \InvalidArgumentException("Operador inválido: {$operator}");
        }

        $column      = $this->validateIdentifier($column);
        $placeholder = ':w_' . count($this->whereBindings);
        $condition   = $this->wrapColumn($column) . " {$operator} {$placeholder}";

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
            // WHERE IN com array vazio retorna falso
            $this->where('1', 0, '=');
            return $this;
        }

        $column       = $this->validateIdentifier($column);
        $placeholders = [];

        foreach ($values as $value) {
            $placeholder    = ':win_' . count($this->whereBindings);
            $placeholders[] = $placeholder;
            $this->whereBindings[$placeholder] = $value;
        }

        $condition = $this->wrapColumn($column) . ' IN (' . implode(', ', $placeholders) . ')';

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " AND {$condition}";
        }

        return $this;
    }

    /**
     * WHERE NOT IN
     */
    public function whereNotIn(string $column, array $values): self
    {
        if (empty($values)) {
            return $this;
        }

        $column       = $this->validateIdentifier($column);
        $placeholders = [];

        foreach ($values as $value) {
            $placeholder    = ':wnin_' . count($this->whereBindings);
            $placeholders[] = $placeholder;
            $this->whereBindings[$placeholder] = $value;
        }

        $condition = $this->wrapColumn($column) . ' NOT IN (' . implode(', ', $placeholders) . ')';

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " AND {$condition}";
        }

        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $column         = $this->validateIdentifier($column);
        $placeholderMin = ':wbt_min_' . count($this->whereBindings);
        $placeholderMax = ':wbt_max_' . (count($this->whereBindings) + 1);

        $this->whereBindings[$placeholderMin] = $min;
        $this->whereBindings[$placeholderMax] = $max;

        $condition = $this->wrapColumn($column) . " BETWEEN {$placeholderMin} AND {$placeholderMax}";

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " AND {$condition}";
        }

        return $this;
    }

    /**
     * WHERE NOT BETWEEN
     */
    public function whereNotBetween(string $column, mixed $min, mixed $max): self
    {
        $column         = $this->validateIdentifier($column);
        $placeholderMin = ':wnbt_min_' . count($this->whereBindings);
        $placeholderMax = ':wnbt_max_' . (count($this->whereBindings) + 1);

        $this->whereBindings[$placeholderMin] = $min;
        $this->whereBindings[$placeholderMax] = $max;

        $condition = $this->wrapColumn($column) . " NOT BETWEEN {$placeholderMin} AND {$placeholderMax}";

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " AND {$condition}";
        }

        return $this;
    }

    public function whereLike(string $column, string $pattern): self
    {
        $column      = $this->validateIdentifier($column);
        $placeholder = ':wl_' . count($this->whereBindings);

        $this->whereBindings[$placeholder] = $pattern;
        $condition = $this->wrapColumn($column) . " LIKE {$placeholder}";

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " AND {$condition}";
        }

        return $this;
    }

    public function whereNull(string $column): self
    {
        $column    = $this->validateIdentifier($column);
        $condition = $this->wrapColumn($column) . ' IS NULL';

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " AND {$condition}";
        }

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $column    = $this->validateIdentifier($column);
        $condition = $this->wrapColumn($column) . ' IS NOT NULL';

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " AND {$condition}";
        }

        return $this;
    }

    public function orWhere(string $column, mixed $value, string $operator = '='): self
    {
        $validOperators = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];
        if (!in_array(strtoupper($operator), $validOperators)) {
            throw new \InvalidArgumentException("Operador inválido: {$operator}");
        }

        $column      = $this->validateIdentifier($column);
        $placeholder = ':ow_' . count($this->whereBindings);
        $condition   = $this->wrapColumn($column) . " {$operator} {$placeholder}";

        $this->whereBindings[$placeholder] = $value;

        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where .= " OR {$condition}";
        }

        return $this;
    }

    /**
     * Agrupa condições WHERE com parênteses
     */
    public function whereGroup(callable $callback, string $boolean = 'AND'): self
    {
        // Guarda estado atual
        $savedWhere = $this->where;
        $savedBindings = $this->whereBindings;
        
        // Reseta para construir o grupo
        $this->where = null;
        $this->whereBindings = [];
        
        $callback($this);
        
        $groupSql = $this->where;
        $groupBindings = $this->whereBindings;
        
        // Restaura estado
        $this->where = $savedWhere;
        $this->whereBindings = $savedBindings;
        
        // Merge bindings do grupo
        foreach ($groupBindings as $key => $value) {
            $this->whereBindings[$key] = $value;
        }
        
        if (!empty($groupSql)) {
            $condition = "({$groupSql})";
            
            if ($this->where === null) {
                $this->where = $condition;
            } else {
                $this->where .= " {$boolean} {$condition}";
            }
        }
        
        return $this;
    }

    /**
     * Obtém cláusula WHERE atual (sem "WHERE ")
     */
    public function getWhereClause(): ?string
    {
        return $this->where;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'])) {
            throw new \InvalidArgumentException("Direção inválida: {$direction}");
        }

        // Permite expressões como RAND(), NOW(), etc.
        if (preg_match('/^[A-Z_]+\s*\(.*\)$/i', trim($column))) {
            $this->setorder[] = "{$column} {$direction}";
            return $this;
        }

        if (strpos($column, '.') !== false) {
            // alias.coluna — não coloca backtick englobando o ponto
            $this->setorder[] = "{$column} {$direction}";
        } else {
            $column = $this->validateIdentifier($column);
            $this->setorder[] = "`{$column}` {$direction}";
        }

        return $this;
    }

    /**
     * Order By com expressão raw
     */
    public function orderByRaw(string $expression): self
    {
        if (stripos($expression, ';') !== false) {
            throw new \InvalidArgumentException("Expressão ORDER BY inválida");
        }
        
        $this->setorder[] = $expression;
        return $this;
    }

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
        if (stripos($column, ';') !== false) {
            throw new \InvalidArgumentException("GROUP BY inválido");
        }
        
        if (strpos($column, '.') !== false) {
            $this->group[] = $column;
        } else {
            $column = $this->validateIdentifier($column);
            $this->group[] = "`{$column}`";
        }
        return $this;
    }

    /**
     * GROUP BY com múltiplas colunas
     */
    public function groupByMultiple(array $columns): self
    {
        foreach ($columns as $column) {
            $this->groupBy($column);
        }
        return $this;
    }

    public function distinct(): self
    {
        $this->DISTINCT = 'DISTINCT';
        return $this;
    }

    public function getWhereBindings(): array
    {
        return $this->whereBindings;
    }

    /**
     * Lock for update (SELECT ... FOR UPDATE)
     */
    public function lockForUpdate(): self
    {
        $this->lockMode = 'FOR UPDATE';
        return $this;
    }

    /**
     * Lock in share mode (SELECT ... LOCK IN SHARE MODE)
     */
    public function sharedLock(): self
    {
        $this->lockMode = 'LOCK IN SHARE MODE';
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

    protected function buildHaving(): string
    {
        return $this->having !== null ? " HAVING {$this->having}" : '';
    }

    protected function buildJoins(): string
    {
        if (empty($this->joins)) {
            return '';
        }

        return ' ' . implode(' ', $this->joins);
    }

    /**
     * Build das unions
     */
    protected function buildUnions(): string
    {
        if (empty($this->unions)) {
            return '';
        }
        return ' ' . implode(' ', $this->unions);
    }

    /**
     * Build do lock mode
     */
    protected function buildLock(): string
    {
        return $this->lockMode ? ' ' . $this->lockMode : '';
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
        $this->jsonDecodeColumns = [];
        $this->lockMode = null;
        $this->unions = [];

        return $this;
    }

    protected function applyJsonDecode(array $rows): array
    {
        if (empty($this->jsonDecodeColumns)) {
            return $rows;
        }
        foreach ($rows as &$row) {
            foreach ($this->jsonDecodeColumns as $col) {
                if (isset($row[$col]) && is_string($row[$col])) {
                    $decoded = json_decode($row[$col], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $row[$col] = $decoded;
                    }
                }
            }
        }
        return $rows;
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

    /**
     * Envolve um identificador de coluna com backticks corretamente,
     * respeitando o formato alias.coluna (ex: s.EXCLUIDO → s.`EXCLUIDO`).
     */
    protected function wrapColumn(string $column): string
    {
        if (str_contains($column, '.')) {
            [$alias, $col] = explode('.', $column, 2);
            return "{$alias}.`{$col}`";
        }

        return "`{$column}`";
    }
}