<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Query;

use IsraelNogueira\galaxyDB\Core\GalaxyDB;

class DataTableHandler
{
    private GalaxyDB $query;
    private array $request;
    private array $searchableColumns = [];
    private array $orderableColumns = [];
    private bool $returnAllData = false;
    private bool $strictMode = false;
    private array $columnAliases = [];

    public function __construct(GalaxyDB $query, array $request)
    {
        $this->query = $query;
        $this->request = $this->sanitizeRequest($request);
    }

    public function process(): array
    {
        // SQL da query original (com WHERE/HAVING do usuário intactos)
        $innerSql = $this->query->toSql();

        // Bindings do WHERE original — necessários para executar a subquery
        $innerBindings = $this->query->getWhereBindings();

        // Total sem filtros do DataTable
        $recordsTotal = $this->getTotalRecords($innerSql, $innerBindings);

        // Monta SQL externo com filtros, order e paginação aplicados sobre a subquery
        $search       = $this->request['search']['value'] ?? '';
        $whereSearch  = $this->buildSearchWhere($search);
        $columnSearch = $this->buildColumnSearchWhere();
        $orderBy      = $this->buildOrderBy();
        $pagination   = $this->buildPagination();

        // Combina condições de busca
        $whereParts  = array_filter([$whereSearch, $columnSearch]);
        $whereClause = !empty($whereParts) ? ' WHERE ' . implode(' AND ', $whereParts) : '';

        // Conta registros filtrados
        $filteredSql     = "SELECT COUNT(*) as total FROM ({$innerSql}) AS _dt{$whereClause}";
        $recordsFiltered = $this->rawCount($filteredSql, $innerBindings);

        // Busca dados paginados
        $dataSql = "SELECT * FROM ({$innerSql}) AS _dt{$whereClause}{$orderBy}{$pagination}";
        $data    = $this->rawSelect($dataSql, $innerBindings);
        $data    = $this->query->applyJsonDecode($data);

        // Se solicitou todos os dados (sem paginação)
        $allData = null;
        if ($this->returnAllData) {
            $allDataSql = "SELECT * FROM ({$innerSql}) AS _dt{$whereClause}{$orderBy}";
            $allData    = $this->rawSelect($allDataSql, $innerBindings);
        }

        $response = [
            'draw'            => (int) $this->request['draw'],
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
            'filter'          => $this->getAppliedFilters()
        ];

        if ($allData !== null) {
            $response['allData'] = $allData;
        }

        return $response;
    }

    public function setSearchableColumns(array $columns): self
    {
        $this->searchableColumns = array_values(array_filter(
            $columns,
            fn($col) => !empty($col) && $col !== 'null'
        ));
        return $this;
    }

    public function setOrderableColumns(array $columns): self
    {
        $this->orderableColumns = array_values(array_filter(
            $columns,
            fn($col) => !empty($col) && $col !== 'null'
        ));
        return $this;
    }

    public function withAllData(bool $enabled = true): self
    {
        $this->returnAllData = $enabled;
        return $this;
    }

    public function setStrictMode(bool $strict = true): self
    {
        $this->strictMode = $strict;
        return $this;
    }

    public function setColumnAliases(array $aliases): self
    {
        $this->columnAliases = $aliases;
        return $this;
    }

    // -------------------------------------------------------------------------
    // INTERNOS
    // -------------------------------------------------------------------------

    /**
     * Lista de colunas pesquisáveis/ordenáveis seguras.
     * Aceita: TABELA.COLUNA (extrai COLUNA) e aliases simples sem funções complexas.
     * Rejeita: TABELA.*, subqueries, DATE_FORMAT, CONCAT, IF, etc.
     */
    private function buildSafeColumnsList(): array
    {
        $safe = [];

        foreach ($this->query->getSelectColumns() as $col) {
            $trimmed = trim($col);

            // TABELA.* → ignora
            if (preg_match('/^[A-Za-z0-9_]+\.\*$/', $trimmed)) {
                continue;
            }

            // Expressão com alias
            if (preg_match('/\s+AS\s+(\w+)\s*$/i', $trimmed, $matches)) {
                // Subquery → ignora
                if (stripos($trimmed, '(SELECT ') !== false) {
                    continue;
                }
                // Funções não-pesquisáveis → ignora
                if (preg_match('/^\s*(DATE_FORMAT|CONCAT|IF|IFNULL|COALESCE|NULLIF|CAST|CONVERT|DATE|TIME|YEAR|MONTH|DAY|NOW|CURDATE)\s*\(/i', $trimmed)) {
                    continue;
                }
                $safe[] = strtoupper($matches[1]);
                continue;
            }

            // TABELA.COLUNA → extrai COLUNA
            if (preg_match('/^[A-Za-z0-9_]+\.([A-Za-z0-9_]+)$/', $trimmed, $matches)) {
                $safe[] = strtoupper($matches[1]);
                continue;
            }

            // Coluna simples
            if (preg_match('/^[A-Za-z0-9_]+$/', $trimmed)) {
                $safe[] = strtoupper($trimmed);
            }
        }

        return $safe;
    }

    /**
     * Total de registros sem filtros DataTable (usa a inner query)
     */
    private function getTotalRecords(string $innerSql, array $bindings = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM ({$innerSql}) AS _dt";
        return $this->rawCount($sql, $bindings);
    }

    /**
     * Monta condição WHERE para busca global
     */
    private function buildSearchWhere(string $search): string
    {
        if (empty($search) || empty($this->searchableColumns)) {
            return '';
        }

        $pattern    = $this->quote('%' . $this->normalizeSearch($search) . '%');
        $conditions = array_map(
            fn($col) => $this->buildSearchCondition($col, $pattern),
            $this->searchableColumns
        );

        return '(' . implode(' OR ', $conditions) . ')';
    }

    /**
     * Constrói condição de busca para uma coluna
     */
    private function buildSearchCondition(string $column, string $pattern): string
    {
        // Se tem alias definido, usa o alias
        $colName = $this->columnAliases[$column] ?? $column;
        
        // Se é uma coluna com alias no formato "tabela.coluna"
        if (strpos($colName, '.') !== false) {
            return "`_dt`.`{$colName}` LIKE {$pattern}";
        }
        
        return "`_dt`.`{$colName}` LIKE {$pattern}";
    }

    /**
     * Monta condição WHERE para busca por coluna individual
     */
    private function buildColumnSearchWhere(): string
    {
        $conditions      = [];
        $searchableUpper = array_map('strtoupper', $this->searchableColumns);

        foreach ($this->request['columns'] ?? [] as $column) {
            $search     = $column['search']['value'] ?? '';
            $columnName = $column['data'] ?? null;

            if (!empty($search) && $columnName && in_array(strtoupper($columnName), $searchableUpper)) {
                $pattern      = $this->quote('%' . $this->normalizeSearch($search) . '%');
                $colName = $this->columnAliases[$columnName] ?? $columnName;
                $conditions[] = "`_dt`.`{$colName}` LIKE {$pattern}";
            }
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Monta ORDER BY para a query externa.
     * Se o request não trouxer nenhuma ordenação válida, usa o ORDER BY
     * definido manualmente na query original (via orderBy()) como fallback.
     */
    private function buildOrderBy(): string
    {
        $orders         = $this->request['order'] ?? [];
        $columns        = $this->request['columns'] ?? [];
        $parts          = [];
        $orderableUpper = array_map('strtoupper', $this->orderableColumns);

        foreach ($orders as $order) {
            $idx       = $order['column'] ?? null;
            $direction = strtoupper($order['dir'] ?? 'ASC');

            if (!in_array($direction, ['ASC', 'DESC'])) {
                $direction = 'ASC';
            }

            if ($idx !== null && isset($columns[$idx])) {
                $col = $columns[$idx]['data'] ?? null;
                if (!empty($col) && $col !== 'null' && in_array(strtoupper($col), $orderableUpper)) {
                    $colName = $this->columnAliases[$col] ?? $col;
                    $parts[] = "`_dt`.`{$colName}` {$direction}";
                }
            }
        }

        if (!empty($parts)) {
            return ' ORDER BY ' . implode(', ', $parts);
        }

        // Fallback: extrai o ORDER BY principal da query original.
        $innerSql      = $this->query->toSql();
        $fallbackOrder = $this->extractMainOrderBy($innerSql);

        if (!empty($fallbackOrder)) {
            // Substitui TABELA.COLUNA por `_dt`.`COLUNA` para funcionar na subquery externa
            $fallbackOrder = preg_replace(
                '/\\b([A-Za-z0-9_`]+)\\.([A-Za-z0-9_`]+)\\b/i',
                '`_dt`.`$2`',
                $fallbackOrder
            );
            return ' ORDER BY ' . $fallbackOrder;
        }

        return '';
    }

    /**
     * Extrai a cláusula ORDER BY principal do SQL ignorando
     * qualquer ORDER BY dentro de parênteses (GROUP_CONCAT, subqueries etc.).
     * Retorna apenas o conteúdo após "ORDER BY", sem o keyword.
     */
    private function extractMainOrderBy(string $sql): string
    {
        $len        = strlen($sql);
        $depth      = 0;
        $i          = 0;
        $orderStart = -1;

        while ($i < $len) {
            $ch = $sql[$i];

            if ($ch === '(') {
                $depth++;
                $i++;
                continue;
            }

            if ($ch === ')') {
                $depth--;
                $i++;
                continue;
            }

            // Pula strings com aspas simples
            if ($ch === "'") {
                $i++;
                while ($i < $len) {
                    if ($sql[$i] === "'" && ($i < 1 || $sql[$i - 1] !== '\\')) {
                        break;
                    }
                    $i++;
                }
                $i++;
                continue;
            }

            // Pula strings com aspas duplas
            if ($ch === '"') {
                $i++;
                while ($i < $len) {
                    if ($sql[$i] === '"' && ($i < 1 || $sql[$i - 1] !== '\\')) {
                        break;
                    }
                    $i++;
                }
                $i++;
                continue;
            }

            // Só interessa ORDER BY no nível 0 (fora de parênteses)
            if ($depth === 0 && ($ch === 'O' || $ch === 'o')) {
                if (strncasecmp(substr($sql, $i, 8), 'ORDER BY', 8) === 0) {
                    $prev = $i > 0 ? $sql[$i - 1] : ' ';
                    if ($prev === ' ' || $prev === "\n" || $prev === "\t" || $i === 0) {
                        $orderStart = $i + 9; // pula "ORDER BY " (8 chars + 1 espaço)
                        // Não dá break: pega o ÚLTIMO ORDER BY no nível 0
                    }
                }
            }

            $i++;
        }

        if ($orderStart === -1) {
            return '';
        }

        $clause = substr($sql, $orderStart);
        $clause = $this->cutAtTopLevel($clause, ['LIMIT', 'HAVING']);

        return trim($clause);
    }

    /**
     * Corta a string na primeira ocorrência de qualquer keyword no nível 0 (fora de parênteses).
     */
    private function cutAtTopLevel(string $str, array $keywords): string
    {
        $len   = strlen($str);
        $depth = 0;

        for ($i = 0; $i < $len; $i++) {
            $ch = $str[$i];

            if ($ch === '(') { $depth++; continue; }
            if ($ch === ')') { $depth--; continue; }

            if ($ch === "'") {
                $i++;
                while ($i < $len) {
                    if ($str[$i] === "'" && ($i < 1 || $str[$i - 1] !== '\\')) break;
                    $i++;
                }
                continue;
            }

            if ($ch === '"') {
                $i++;
                while ($i < $len) {
                    if ($str[$i] === '"' && ($i < 1 || $str[$i - 1] !== '\\')) break;
                    $i++;
                }
                continue;
            }

            if ($depth === 0) {
                foreach ($keywords as $kw) {
                    $kwLen = strlen($kw);
                    if (strncasecmp(substr($str, $i, $kwLen), $kw, $kwLen) === 0) {
                        $prev = $i > 0 ? $str[$i - 1] : ' ';
                        if ($prev === ' ' || $prev === "\n" || $prev === "\t") {
                            return substr($str, 0, $i);
                        }
                    }
                }
            }
        }

        return $str;
    }

    /**
     * Monta LIMIT para paginação
     */
    private function buildPagination(): string
    {
        $start  = (int) ($this->request['start'] ?? 0);
        $length = (int) ($this->request['length'] ?? 10);

        // Se length = -1, retorna todos (sem paginação)
        if ($length === -1) {
            return '';
        }

        return $length > 0 ? " LIMIT {$start}, {$length}" : '';
    }

    /**
     * Executa SELECT e retorna array de resultados.
     * Recebe os bindings da inner query para resolver os placeholders do WHERE original.
     */
    private function rawSelect(string $sql, array $bindings = []): array
    {
        try {
            $stmt = $this->query->getConnection()->prepare($sql);

            foreach ($bindings as $placeholder => $value) {
                $type = $this->getPDOType($value);
                $stmt->bindValue($placeholder, $value, $type);
            }

            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            if ($this->strictMode) {
                throw $e;
            }
            // Em modo não-strict, loga e retorna vazio
            error_log("DataTableHandler rawSelect error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Executa COUNT e retorna inteiro.
     * Recebe os bindings da inner query para resolver os placeholders do WHERE original.
     */
    private function rawCount(string $sql, array $bindings = []): int
    {
        try {
            $stmt = $this->query->getConnection()->prepare($sql);

            foreach ($bindings as $placeholder => $value) {
                $type = $this->getPDOType($value);
                $stmt->bindValue($placeholder, $value, $type);
            }

            $stmt->execute();
            $row = $stmt->fetch();
            return (int) ($row['total'] ?? 0);
        } catch (\PDOException $e) {
            if ($this->strictMode) {
                throw $e;
            }
            error_log("DataTableHandler rawCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtém tipo PDO para binding
     */
    private function getPDOType(mixed $value): int
    {
        return match (true) {
            is_int($value)  => \PDO::PARAM_INT,
            is_bool($value) => \PDO::PARAM_BOOL,
            is_null($value) => \PDO::PARAM_NULL,
            default         => \PDO::PARAM_STR,
        };
    }

    /**
     * Escapa valor para uso direto no SQL
     */
    private function quote(string $value): string
    {
        return $this->query->getConnection()->quote($value);
    }

    private function normalizeSearch(string $search): string
    {
        return preg_replace('/\s+/', ' ', trim($search));
    }

    private function sanitizeRequest(array $request): array
    {
        return [
            'draw'   => (int) ($request['draw'] ?? 1),
            'start'  => max(0, (int) ($request['start'] ?? 0)),
            'length' => max(-1, (int) ($request['length'] ?? 10)),
            'search' => [
                'value' => (string) ($request['search']['value'] ?? ''),
                'regex' => (bool) ($request['search']['regex'] ?? false)
            ],
            'order'   => $request['order'] ?? [],
            'columns' => $request['columns'] ?? []
        ];
    }

    public static function create(GalaxyDB $query, array $request): self
    {
        return new self($query, $request);
    }

    private function getAppliedFilters(): array
    {
        $filters = ['search' => null, 'order' => [], 'columns' => []];

        $globalSearch = $this->request['search']['value'] ?? '';
        if (!empty($globalSearch)) {
            $filters['search'] = $globalSearch;
        }

        $orders         = $this->request['order'] ?? [];
        $columns        = $this->request['columns'] ?? [];
        $orderableUpper = array_map('strtoupper', $this->orderableColumns);
        $searchableUpper = array_map('strtoupper', $this->searchableColumns);

        foreach ($orders as $order) {
            $idx       = $order['column'] ?? null;
            $direction = $order['dir'] ?? 'ASC';
            if ($idx !== null && isset($columns[$idx])) {
                $col = $columns[$idx]['data'] ?? null;
                if ($col && in_array(strtoupper($col), $orderableUpper)) {
                    $filters['order'][] = ['column' => $col, 'direction' => strtoupper($direction)];
                }
            }
        }

        foreach ($columns as $column) {
            $search = $column['search']['value'] ?? '';
            $col    = $column['data'] ?? null;
            if (!empty($search) && $col && in_array(strtoupper($col), $searchableUpper)) {
                $filters['columns'][$col] = $search;
            }
        }

        return $filters;
    }

    /**
     * Obtém estatísticas da query executada
     * 
     * @return array Estatísticas
     */
    public function getStats(): array
    {
        return [
            'total_records' => $this->request['draw'] ?? 0,
            'searchable_columns' => count($this->searchableColumns),
            'orderable_columns' => count($this->orderableColumns),
            'has_search' => !empty($this->request['search']['value']),
            'has_order' => !empty($this->request['order']),
            'has_column_search' => !empty($this->request['columns'])
        ];
    }

    /**
     * Valida se a requisição está completa
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isValidDataTableRequest($this->request);
    }

    /**
     * Valida se é uma requisição DataTables válida
     */
    private function isValidDataTableRequest(array $request): bool
    {
        return isset($request['draw']) && 
               isset($request['columns']) && 
               is_array($request['columns']);
    }
}