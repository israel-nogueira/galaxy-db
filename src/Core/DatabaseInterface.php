<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Core;

interface DatabaseInterface
{
    /**
     * Seleciona registros da tabela
     * 
     * @param string|null $columns Colunas a selecionar (ex: 'id, nome, email')
     * @return array Lista de registros
     */
    public function select(?string $columns = null): array;
    
    /**
     * Retorna o primeiro registro da consulta
     * 
     * @param string|null $columns Colunas a selecionar
     * @return array|null Primeiro registro ou null
     */
    public function first(?string $columns = null): ?array;
    
    /**
     * Retorna o total de registros da consulta
     * 
     * @return int Número de registros
     */
    public function count(): int;
    
    /**
     * Insere um registro na tabela
     * 
     * @return int|bool ID do registro inserido ou false em caso de falha
     */
    public function insert(): int|bool;
    
    /**
     * Atualiza registros na tabela
     * 
     * @return int Número de linhas afetadas
     */
    public function update(): int;
    
    /**
     * Deleta registros da tabela
     * 
     * @return int Número de linhas afetadas
     */
    public function delete(): int;
    
    /**
     * Adiciona condição WHERE à consulta
     * 
     * @param string $column Nome da coluna
     * @param mixed $value Valor para comparação
     * @param string $operator Operador de comparação (padrão: '=')
     * @return self
     */
    public function where(string $column, mixed $value, string $operator = '='): self;
    
    /**
     * Adiciona condição OR WHERE à consulta
     * 
     * @param string $column Nome da coluna
     * @param mixed $value Valor para comparação
     * @param string $operator Operador de comparação (padrão: '=')
     * @return self
     */
    public function orWhere(string $column, mixed $value, string $operator = '='): self;
    
    /**
     * Adiciona ORDER BY à consulta
     * 
     * @param string $column Nome da coluna
     * @param string $direction Direção (ASC ou DESC)
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self;
    
    /**
     * Adiciona LIMIT à consulta
     * 
     * @param int $limit Número de registros
     * @param int|null $offset Offset (opcional)
     * @return self
     */
    public function limit(int $limit, ?int $offset = null): self;
    
    /**
     * Adiciona GROUP BY à consulta
     * 
     * @param string $column Nome da coluna
     * @return self
     */
    public function groupBy(string $column): self;
    
    /**
     * Define a tabela para a consulta
     * 
     * @param string $table Nome da tabela
     * @return self
     */
    public function table(string $table): self;
    
    /**
     * Inicia uma transação
     * 
     * @return bool True se iniciou com sucesso
     */
    public function beginTransaction(): bool;
    
    /**
     * Commita a transação atual
     * 
     * @return bool True se commitou com sucesso
     */
    public function commit(): bool;
    
    /**
     * Reverte a transação atual
     * 
     * @return bool True se reverteu com sucesso
     */
    public function rollback(): bool;

    /**
     * Obtém a conexão PDO
     * 
     * @return \PDO
     */
    public function getConnection(): \PDO;

    /**
     * Obtém a última query executada
     * 
     * @return string
     */
    public function getLastQuery(): string;

    /**
     * Obtém o último ID inserido
     * 
     * @return int
     */
    public function lastInsertId(): int;

    /**
     * Obtém o número de linhas afetadas pela última operação
     * 
     * @return int
     */
    public function affectedRows(): int;

    /**
     * Executa uma query SQL raw
     * 
     * @param string $sql Query SQL
     * @param array $bindings Bindings para a query
     * @return array|bool Resultados ou false em caso de falha
     */
    public function raw(string $sql, array $bindings = []): array|bool;

    /**
     * Verifica se a consulta retornou resultados
     * 
     * @return bool True se existir pelo menos um resultado
     */
    public function exists(): bool;

    /**
     * Encontra um registro pelo ID
     * 
     * @param mixed $id ID do registro
     * @param string $primaryKey Nome da chave primária (padrão: 'id')
     * @return array|null Registro encontrado ou null
     */
    public function find(mixed $id, string $primaryKey = 'id'): ?array;

    /**
     * Encontra um registro pelo ID ou lança exceção
     * 
     * @param mixed $id ID do registro
     * @param string $primaryKey Nome da chave primária (padrão: 'id')
     * @return array Registro encontrado
     * @throws \Exception Se não encontrar o registro
     */
    public function findOrFail(mixed $id, string $primaryKey = 'id'): array;

    /**
     * Obtém os valores de uma coluna em array
     * 
     * @param string $column Nome da coluna
     * @return array Lista de valores
     */
    public function pluck(string $column): array;

    /**
     * Processa resultados em partes (chunk)
     * 
     * @param int $size Tamanho de cada chunk
     * @param callable $callback Função de callback
     * @return void
     */
    public function chunk(int $size, callable $callback): void;

    /**
     * Habilita/desabilita o modo debug
     * 
     * @param bool $debug
     * @return self
     */
    public function setDebug(bool $debug): self;

    /**
     * Obtém a query SQL montada (sem executar)
     * 
     * @return string
     */
    public function toSql(): string;
}