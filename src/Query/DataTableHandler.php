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

    /**
     * Processa requisição DataTables e retorna resposta formatada
     */
    public function process(): array
    {
        // Total sem filtros
        $recordsTotal = $this->getTotalRecords();

        // Aplica filtros globais e por coluna
        $this->applySearch();
        
        // Total com filtros
        $recordsFiltered = $this->getFilteredCount();

        // Se precisa retornar todos os dados
        $allData = null;
        if ($this->returnAllData) {
            $allDataQuery = clone $this->query;
            $this->applySearchToQuery($allDataQuery);
            $this->applyOrderingToQuery($allDataQuery);
            $allData = $allDataQuery->select();
        }

        // Aplica ordenação
        $this->applyOrdering();

        // Aplica paginação
        $this->applyPagination();

        // Executa query paginada
        $data = $this->query->select();

        $response = [
            'draw' => $this->request['draw'],
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data
        ];

        if ($allData !== null) {
            $response['allData'] = $allData;
        }

        return $response;
    }

    /**
     * Define colunas pesquisáveis
     */
    public function setSearchableColumns(array $columns): self
    {
        $this->searchableColumns = $columns;
        return $this;
    }

    /**
     * Define colunas ordenáveis
     */
    public function setOrderableColumns(array $columns): self
    {
        $this->orderableColumns = $columns;
        return $this;
    }

    /**
     * Habilita retorno de todos os dados (além dos paginados)
     */
    public function withAllData(bool $enabled = true): self
    {
        $this->returnAllData = $enabled;
        return $this;
    }

    /**
     * Obtém total de registros sem filtros
     */
    private function getTotalRecords(): int
    {
        $countQuery = clone $this->query;
        return $countQuery->count();
    }

    /**
     * Obtém total de registros com filtros aplicados
     */
    private function getFilteredCount(): int
    {
        $countQuery = clone $this->query;
        
        // Reaplicar filtros no clone
        $this->applySearchToQuery($countQuery);
        
        return $countQuery->count();
    }

    /**
     * Aplica busca global e por coluna
     */
    private function applySearch(): void
    {
        $this->applySearchToQuery($this->query);
    }

    /**
     * Aplica busca em uma query específica
     */
    private function applySearchToQuery(GalaxyDB $query): void
    {
        $globalSearch = $this->request['search']['value'] ?? '';
        $columns = $this->request['columns'] ?? [];

        // Busca global
        if (!empty($globalSearch) && !empty($this->searchableColumns)) {
            $firstColumn = true;
            
            foreach ($this->searchableColumns as $column) {
                $pattern = '%' . $this->normalizeSearch($globalSearch) . '%';
                
                if ($firstColumn) {
                    $query->whereLike($column, $pattern);
                    $firstColumn = false;
                } else {
                    $query->orWhere($column, $pattern, 'LIKE');
                }
            }
        }

        // Busca por coluna individual
        foreach ($columns as $column) {
            $columnSearch = $column['search']['value'] ?? '';
            $columnName = $column['data'] ?? null;

            if (!empty($columnSearch) && $columnName && in_array($columnName, $this->searchableColumns)) {
                $pattern = '%' . $this->normalizeSearch($columnSearch) . '%';
                $query->whereLike($columnName, $pattern);
            }
        }
    }

    /**
     * Aplica ordenação
     */
    private function applyOrdering(): void
    {
        $orders = $this->request['order'] ?? [];
        $columns = $this->request['columns'] ?? [];

        foreach ($orders as $order) {
            $columnIndex = $order['column'] ?? null;
            $direction = strtoupper($order['dir'] ?? 'ASC');

            if ($columnIndex !== null && isset($columns[$columnIndex])) {
                $columnName = $columns[$columnIndex]['data'] ?? null;

                if ($columnName && in_array($columnName, $this->orderableColumns)) {
                    $this->query->orderBy($columnName, $direction);
                }
            }
        }
    }

    /**
     * Aplica paginação
     */
    private function applyPagination(): void
    {
        $start = $this->request['start'] ?? 0;
        $length = $this->request['length'] ?? 10;

        if ($length > 0) {
            $this->query->limit($length, $start);
        }
    }

    /**
     * Normaliza string de busca (remove múltiplos espaços)
     */
    private function normalizeSearch(string $search): string
    {
        return preg_replace('/\s+/', ' ', trim($search));
    }

    /**
     * Sanitiza requisição DataTables
     */
    private function sanitizeRequest(array $request): array
    {
        return [
            'draw' => (int) ($request['draw'] ?? 1),
            'start' => max(0, (int) ($request['start'] ?? 0)),
            'length' => max(-1, (int) ($request['length'] ?? 10)),
            'search' => [
                'value' => (string) ($request['search']['value'] ?? ''),
                'regex' => (bool) ($request['search']['regex'] ?? false)
            ],
            'order' => $request['order'] ?? [],
            'columns' => $request['columns'] ?? []
        ];
    }

    /**
     * Factory method estático
     */
    public static function create(GalaxyDB $query, array $request): self
    {
        return new self($query, $request);
    }
}
