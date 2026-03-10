<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Query;

use IsraelNogueira\galaxyDB\Query\DataTableHandler;

trait DataTableTrait
{
    private ?DataTableHandler $dataTableHandler = null;
    private bool $isDataTableMode = false;

    /**
     * Prepara para processamento DataTables (não executa ainda).
     * A query original (com WHERE/HAVING do usuário) é transformada em subquery
     * internamente — filtros, order e paginação do DataTable são aplicados na camada externa.
     *
     * @param array|null $searchableColumns Colunas pesquisáveis (null = auto-detect)
     * @param array|null $orderableColumns  Colunas ordenáveis  (null = usa searchable)
     */
    public function prepare_dataTable(
        ?array $searchableColumns = null,
        ?array $orderableColumns = null
    ): self {
        $request = $this->detectDataTableRequest();

        if (!$this->isValidDataTableRequest($request)) {
            $this->isDataTableMode = false;
            return $this;
        }

        $this->isDataTableMode = true;

        if ($searchableColumns === null) {
            $searchableColumns = $this->extractColumnsFromRequest($request);
        }

        $this->dataTableHandler = DataTableHandler::create($this, $request)
            ->setSearchableColumns($searchableColumns)
            ->setOrderableColumns($orderableColumns ?? $searchableColumns);

        return $this;
    }

    /**
     * Executa o DataTable (após prepare_dataTable).
     * Se não foi preparado, retorna select normal no formato DataTables.
     */
    public function dataTable(): array
    {
        if (!$this->isDataTableMode || $this->dataTableHandler === null) {
            $data = $this->select();
            return [
                'draw'            => 1,
                'recordsTotal'    => count($data),
                'recordsFiltered' => count($data),
                'data'            => $data
            ];
        }

        return $this->dataTableHandler->process();
    }

    public function isDataTableMode(): bool
    {
        return $this->isDataTableMode;
    }

    // -------------------------------------------------------------------------
    // INTERNOS
    // -------------------------------------------------------------------------

    private function detectDataTableRequest(): array
    {
        $source = null;

        if (defined('PUBLIC_DATA') &&
            is_array(PUBLIC_DATA) &&
            array_key_exists('oAjaxData', PUBLIC_DATA) &&
            is_array(PUBLIC_DATA['oAjaxData'])) {

            $source = PUBLIC_DATA['oAjaxData'];

        } elseif (defined('PRIVATE_DATA') &&
			is_array(PRIVATE_DATA) &&
			array_key_exists('oAjaxData', PRIVATE_DATA) &&
			is_array(PRIVATE_DATA['oAjaxData'])) {
				
            $source = PRIVATE_DATA['oAjaxData'];
        } else {
            $source = !empty($_POST['draw']) ? $_POST : (!empty($_GET['draw']) ? $_GET : []);
        }

        return [
            'draw'   => $source['draw'] ?? null,
            'start'  => $source['start'] ?? 0,
            'length' => $source['length'] ?? 10,
            'search' => [
                'value' => $source['search']['value'] ?? '',
                'regex' => $source['search']['regex'] ?? false
            ],
            'order'   => $source['order'] ?? [],
            'columns' => $source['columns'] ?? []
        ];
    }

    private function isValidDataTableRequest(array $request): bool
    {
        return !empty($request['draw']) && isset($request['columns']);
    }

    /**
     * Extrai colunas pesquisáveis do request do DataTables.
     * Como usamos subquery, todos os aliases são acessíveis — exceto TABELA.*
     */
    private function extractColumnsFromRequest(array $request): array
    {
        $safe    = $this->buildSafeColumnsListFromSelect();
        $columns = [];

        foreach ($request['columns'] ?? [] as $column) {
            $data = $column['data'] ?? null;
            if (!empty($data) &&
                $data !== 'null' &&
                ($column['searchable'] ?? true) &&
                in_array(strtoupper($data), $safe)
            ) {
                $columns[] = $data;
            }
        }

        return $columns;
    }

    /**
     * Monta lista de colunas seguras a partir dos selectColumns.
     * Com subquery, aceita todos os aliases — só rejeita TABELA.* (ambíguo no inner SQL).
     */
    private function buildSafeColumnsListFromSelect(): array
    {
        $safe = [];

        foreach ($this->getSelectColumns() as $col) {
            $trimmed = trim($col);

            // TABELA.* → ignora
            if (preg_match('/^[A-Za-z0-9_]+\.\*$/', $trimmed)) {
                continue;
            }

            // Alias → pega o nome do alias (qualquer tipo — subquery resolve)
            if (preg_match('/\s+AS\s+(\w+)\s*$/i', $trimmed, $matches)) {
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
}
