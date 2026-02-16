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

    public function __construct(GalaxyDB $query, array $request)
    {
        $this->query = $query;
        $this->request = $this->sanitizeRequest($request);
    }

    public function process(): array
    {
        // SQL da query original (com WHERE/HAVING do usuário intactos)
        $innerSql = $this->query->toSql();

        // Total sem filtros do DataTable
        $recordsTotal = $this->getTotalRecords($innerSql);

        // Monta SQL externo com filtros, order e paginação aplicados sobre a subquery
        $search        = $this->request['search']['value'] ?? '';
        $whereSearch   = $this->buildSearchWhere($search);
        $columnSearch  = $this->buildColumnSearchWhere();
        $orderBy       = $this->buildOrderBy();
        $pagination    = $this->buildPagination();

        // Combina condições de busca
        $whereParts = array_filter([$whereSearch, $columnSearch]);
        $whereClause = !empty($whereParts) ? ' WHERE ' . implode(' AND ', $whereParts) : '';

        $filteredSql = "SELECT COUNT(*) as total FROM ({$innerSql}) AS _dt{$whereClause}";
        $recordsFiltered = $this->rawCount($filteredSql);

        $dataSql = "SELECT * FROM ({$innerSql}) AS _dt{$whereClause}{$orderBy}{$pagination}";
        $data = $this->rawSelect($dataSql);

        $allData = null;
        if ($this->returnAllData) {
            $allDataSql = "SELECT * FROM ({$innerSql}) AS _dt{$whereClause}{$orderBy}";
            $allData = $this->rawSelect($allDataSql);
        }

        $response = [
            'draw'            => $this->request['draw'],
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
        // Filtra apenas colunas válidas (sem TABELA.*, subqueries, funções complexas)
        $safe = $this->buildSafeColumnsList();
        $this->searchableColumns = array_values(array_filter(
            $columns,
            fn($col) => !empty($col) && in_array(strtoupper($col), $safe)
        ));
        return $this;
    }

    public function setOrderableColumns(array $columns): self
    {
        $safe = $this->buildSafeColumnsList();
        $this->orderableColumns = array_values(array_filter(
            $columns,
            fn($col) => !empty($col) && in_array(strtoupper($col), $safe)
        ));
        return $this;
    }

    public function withAllData(bool $enabled = true): self
    {
        $this->returnAllData = $enabled;
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
    private function getTotalRecords(string $innerSql): int
    {
        $sql = "SELECT COUNT(*) as total FROM ({$innerSql}) AS _dt";
        return $this->rawCount($sql);
    }

    /**
     * Monta condição WHERE para busca global
     */
    private function buildSearchWhere(string $search): string
    {
        if (empty($search) || empty($this->searchableColumns)) {
            return '';
        }

        $pattern = $this->quote('%' . $this->normalizeSearch($search) . '%');
        $conditions = array_map(
            fn($col) => "`_dt`.`{$col}` LIKE {$pattern}",
            $this->searchableColumns
        );

        return '(' . implode(' OR ', $conditions) . ')';
    }

    /**
     * Monta condição WHERE para busca por coluna individual
     */
    private function buildColumnSearchWhere(): string
    {
        $conditions = [];
        foreach ($this->request['columns'] ?? [] as $column) {
            $search     = $column['search']['value'] ?? '';
            $columnName = $column['data'] ?? null;

            if (!empty($search) && $columnName && in_array($columnName, $this->searchableColumns)) {
                $pattern      = $this->quote('%' . $this->normalizeSearch($search) . '%');
                $conditions[] = "`_dt`.`{$columnName}` LIKE {$pattern}";
            }
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Monta ORDER BY para a query externa
     */
    private function buildOrderBy(): string
    {
        $orders  = $this->request['order'] ?? [];
        $columns = $this->request['columns'] ?? [];
        $parts   = [];

        foreach ($orders as $order) {
            $idx       = $order['column'] ?? null;
            $direction = strtoupper($order['dir'] ?? 'ASC');

            if (!in_array($direction, ['ASC', 'DESC'])) {
                $direction = 'ASC';
            }

            if ($idx !== null && isset($columns[$idx])) {
                $col = $columns[$idx]['data'] ?? null;
                if ($col && in_array($col, $this->orderableColumns)) {
                    $parts[] = "`_dt`.`{$col}` {$direction}";
                }
            }
        }

        return !empty($parts) ? ' ORDER BY ' . implode(', ', $parts) : '';
    }

    /**
     * Monta LIMIT para paginação
     */
    private function buildPagination(): string
    {
        $start  = $this->request['start'] ?? 0;
        $length = $this->request['length'] ?? 10;

        return $length > 0 ? " LIMIT {$start}, {$length}" : '';
    }

    /**
     * Executa SELECT e retorna array de resultados
     */
    private function rawSelect(string $sql): array
    {
        try {
            $stmt = $this->query->getConnection()->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Executa COUNT e retorna inteiro
     */
    private function rawCount(string $sql): int
    {
        try {
            $stmt = $this->query->getConnection()->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch();
            return (int) ($row['total'] ?? 0);
        } catch (\PDOException $e) {
            throw $e;
        }
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

        $orders  = $this->request['order'] ?? [];
        $columns = $this->request['columns'] ?? [];

        foreach ($orders as $order) {
            $idx       = $order['column'] ?? null;
            $direction = $order['dir'] ?? 'ASC';
            if ($idx !== null && isset($columns[$idx])) {
                $col = $columns[$idx]['data'] ?? null;
                if ($col && in_array($col, $this->orderableColumns)) {
                    $filters['order'][] = ['column' => $col, 'direction' => strtoupper($direction)];
                }
            }
        }

        foreach ($columns as $column) {
            $search = $column['search']['value'] ?? '';
            $col    = $column['data'] ?? null;
            if (!empty($search) && $col && in_array($col, $this->searchableColumns)) {
                $filters['columns'][$col] = $search;
            }
        }

        return $filters;
    }
}
