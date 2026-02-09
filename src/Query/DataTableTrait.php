<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Query;

use IsraelNogueira\galaxyDB\Query\DataTableHandler;

trait DataTableTrait
{
    private ?DataTableHandler $dataTableHandler = null;
    private bool $isDataTableMode = false;

    /**
     * Prepara para processamento DataTables (não executa ainda)
     * Detecta automaticamente a requisição e configura os filtros
     * 
     * @param array|null $searchableColumns Colunas pesquisáveis (null = auto-detect)
     * @param array|null $orderableColumns Colunas ordenáveis (null = usa searchable)
     * @return self Para encadeamento
     */
    public function prepare_dataTable(
        ?array $searchableColumns = null,
        ?array $orderableColumns = null
    ): self {
        // Detecta requisição DataTables
        $request = $this->detectDataTableRequest();
        
        // Verifica se é requisição DataTables válida
        if (!$this->isValidDataTableRequest($request)) {
            $this->isDataTableMode = false;
            return $this;
        }

        $this->isDataTableMode = true;

        // Auto-detecta colunas se não fornecidas
        if ($searchableColumns === null) {
            $searchableColumns = $this->extractColumnsFromRequest($request);
        }

        // Cria o handler
        $this->dataTableHandler = DataTableHandler::create($this, $request)
            ->setSearchableColumns($searchableColumns)
            ->setOrderableColumns($orderableColumns ?? $searchableColumns);

        return $this;
    }

    /**
     * Executa o DataTable (após prepare_dataTable)
     * Se não foi preparado, retorna select normal
     * 
     * @return array Dados formatados
     */
    public function dataTable(): array
    {
        if (!$this->isDataTableMode || $this->dataTableHandler === null) {
            return $this->select();
        }

        return $this->dataTableHandler->process();
    }

    /**
     * Verifica se está em modo DataTable
     */
    public function isDataTableMode(): bool
    {
        return $this->isDataTableMode;
    }

    /**
     * Detecta requisição DataTables de $_POST ou $_GET
     */
    private function detectDataTableRequest(): array
    {
        // Prioridade: PUBLIC_DATA > PRIVATE_DATA > $_POST > $_GET
        $source = null;
        
        // Verifica PUBLIC_DATA
        if (defined('PUBLIC_DATA') && 
            is_array(PUBLIC_DATA) && 
            array_key_exists('oAjaxData', PUBLIC_DATA) &&
            is_array(PUBLIC_DATA['oAjaxData'])) {
            $source = PUBLIC_DATA['oAjaxData'];
        }
        // Verifica PRIVATE_DATA
        elseif (defined('PRIVATE_DATA') && 
                is_array(PRIVATE_DATA) && 
                array_key_exists('oAjaxData', PRIVATE_DATA) &&
                is_array(PRIVATE_DATA['oAjaxData'])) {
            $source = PRIVATE_DATA['oAjaxData'];
        }
        // Fallback para $_POST ou $_GET
        else {
            $source = !empty($_POST['draw']) ? $_POST : (!empty($_GET['draw']) ? $_GET : []);
        }
        
        return [
            'draw' => $source['draw'] ?? null,
            'start' => $source['start'] ?? 0,
            'length' => $source['length'] ?? 10,
            'search' => [
                'value' => $source['search']['value'] ?? '',
                'regex' => $source['search']['regex'] ?? false
            ],
            'order' => $source['order'] ?? [],
            'columns' => $source['columns'] ?? []
        ];
    }

    /**
     * Verifica se é uma requisição DataTables válida
     */
    private function isValidDataTableRequest(array $request): bool
    {
        return !empty($request['draw']) && isset($request['columns']);
    }

    /**
     * Extrai nomes de colunas da requisição DataTables
     */
    private function extractColumnsFromRequest(array $request): array
    {
        $columns = [];
        
        foreach ($request['columns'] ?? [] as $column) {
            if (!empty($column['data']) && 
                $column['data'] !== 'null' && 
                ($column['searchable'] ?? true)) {
                $columns[] = $column['data'];
            }
        }
        
        return $columns;
    }
}
