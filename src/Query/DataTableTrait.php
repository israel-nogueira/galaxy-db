<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Query;

use IsraelNogueira\galaxyDB\Query\DataTableHandler;

trait DataTableTrait
{
    private ?DataTableHandler $dataTableHandler = null;
    private bool $isDataTableMode = false;
    private array $dataTableCache = [];
    private bool $dataTableCacheEnabled = false;
    private int $dataTableCacheTTL = 60; // 1 minuto

    /**
     * Prepara para processamento DataTables (não executa ainda).
     * A query original (com WHERE/HAVING do usuário) é transformada em subquery
     * internamente — filtros, order e paginação do DataTable são aplicados na camada externa.
     *
     * @param array|null $searchableColumns Colunas pesquisáveis (null = auto-detect)
     * @param array|null $orderableColumns  Colunas ordenáveis  (null = usa searchable)
     * @param bool $useCache Se deve usar cache
     * @param int $cacheTTL Tempo de vida do cache em segundos
     * @return self
     */
    public function prepare_dataTable(
        ?array $searchableColumns = null,
        ?array $orderableColumns = null,
        bool $useCache = false,
        int $cacheTTL = 60
    ): self {
        $request = $this->detectDataTableRequest();

        if (!$this->isValidDataTableRequest($request)) {
            $this->isDataTableMode = false;
            return $this;
        }

        $this->isDataTableMode = true;
        $this->dataTableCacheEnabled = $useCache;
        $this->dataTableCacheTTL = max(1, $cacheTTL);

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
     * 
     * @param bool $useCache Se deve usar cache (sobrescreve configuração)
     * @return array
     */
    public function dataTable(bool $useCache = null): array
    {
        if (!$this->isDataTableMode || $this->dataTableHandler === null) {
            $data = $this->select();
            return [
                'draw'            => 1,
                'recordsTotal'    => count($data),
                'recordsFiltered' => count($data),
                'data'            => $data,
                'filter'          => null
            ];
        }

        // Determina se usa cache
        $useCache = $useCache ?? $this->dataTableCacheEnabled;
        
        // Gera chave de cache
        if ($useCache) {
            $cacheKey = $this->generateDataTableCacheKey();
            if (isset($this->dataTableCache[$cacheKey])) {
                $cache = $this->dataTableCache[$cacheKey];
                if ((time() - $cache['time']) < $this->dataTableCacheTTL) {
                    return $cache['data'];
                }
                unset($this->dataTableCache[$cacheKey]);
            }
        }

        $result = $this->dataTableHandler->process();

        // Armazena em cache
        if ($useCache) {
            $cacheKey = $this->generateDataTableCacheKey();
            $this->dataTableCache[$cacheKey] = [
                'data' => $result,
                'time' => time()
            ];
        }

        return $result;
    }

    /**
     * Gera chave de cache para DataTable
     * 
     * @return string
     */
    private function generateDataTableCacheKey(): string
    {
        $request = $this->detectDataTableRequest();
        $sql = $this->toSql();
        $bindings = serialize($this->getWhereBindings());
        return md5($sql . $bindings . serialize($request));
    }

    /**
     * Limpa cache do DataTable
     * 
     * @return self
     */
    public function clearDataTableCache(): self
    {
        $this->dataTableCache = [];
        return $this;
    }

    /**
     * Verifica se está em modo DataTable
     * 
     * @return bool
     */
    public function isDataTableMode(): bool
    {
        return $this->isDataTableMode;
    }

    /**
     * Detecta requisição DataTables de várias fontes
     * 
     * @return array
     */
    private function detectDataTableRequest(): array
    {
        $source = null;

        // Verifica constantes definidas pelo sistema
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
            // Tenta $_POST, depois $_GET, depois $_REQUEST
            $source = !empty($_POST['draw']) ? $_POST : 
                     (!empty($_GET['draw']) ? $_GET : 
                     (!empty($_REQUEST['draw']) ? $_REQUEST : []));
        }

        // Normaliza request
        return [
            'draw'   => (int) ($source['draw'] ?? 1),
            'start'  => max(0, (int) ($source['start'] ?? 0)),
            'length' => max(-1, (int) ($source['length'] ?? 10)),
            'search' => [
                'value' => (string) ($source['search']['value'] ?? ''),
                'regex' => (bool) ($source['search']['regex'] ?? false)
            ],
            'order'   => $source['order'] ?? [],
            'columns' => $source['columns'] ?? []
        ];
    }

    /**
     * Valida se é uma requisição DataTables válida
     * 
     * @param array $request
     * @return bool
     */
    private function isValidDataTableRequest(array $request): bool
    {
        return !empty($request['draw']) && isset($request['columns']);
    }

    /**
     * Extrai colunas pesquisáveis do request do DataTables.
     * Como usamos subquery, todos os aliases são acessíveis — exceto TABELA.*
     * 
     * @param array $request
     * @return array
     */
    private function extractColumnsFromRequest(array $request): array
    {
        $safe = $this->buildSafeColumnsListFromSelect();
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
     * 
     * @return array
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

    /**
     * Processa DataTable com tratamento de erros
     * 
     * @param bool $useCache
     * @return array
     * @throws \Exception
     */
    public function safeDataTable(bool $useCache = null): array
    {
        try {
            return $this->dataTable($useCache);
        } catch (\Exception $e) {
            // Log do erro
            error_log("DataTable Error: " . $e->getMessage());
            
            // Retorna resposta de erro amigável
            return [
                'draw' => (int) ($_POST['draw'] ?? 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Erro ao processar DataTable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Gera colunas para DataTable a partir do schema da tabela
     * 
     * @param array $exclude Colunas a excluir
     * @return array Lista de colunas
     */
    public function getDataTableColumns(array $exclude = []): array
    {
        if (empty($this->tableClass)) {
            return [];
        }

        $columns = $this->showDBColumns($this->tableClass);
        
        // Filtra colunas excluídas
        return array_filter($columns, function($col) use ($exclude) {
            return !in_array($col, $exclude);
        });
    }

    /**
     * Define colunas para o DataTable baseado no schema
     * 
     * @param array $exclude Colunas a excluir
     * @param bool $autoConfigure Se deve configurar automaticamente
     * @return self
     */
    public function autoConfigureDataTable(array $exclude = ['id', 'created_at', 'updated_at', 'deleted_at'], bool $autoConfigure = true): self
    {
        $columns = $this->getDataTableColumns($exclude);
        
        if ($autoConfigure && !empty($columns)) {
            $this->colum(implode(',', $columns));
        }
        
        return $this;
    }
}