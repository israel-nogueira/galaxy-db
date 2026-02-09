<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Security;

use IsraelNogueira\galaxyDB\Security\Encryption;
use IsraelNogueira\galaxyDB\Security\Validator;
use IsraelNogueira\galaxyDB\Security\ColumnGuard;

/**
 * -------------------------------------------------------------------------
 * Trait Security
 * -------------------------------------------------------------------------
 * 
 * Orquestra as funcionalidades de segurança do GalaxyDB.
 * Gerencia criptografia, validação e proteção de colunas.
 * 
 * @package IsraelNogueira\galaxyDB\Security
 * @author Israel Nogueira <israel@feats.com>
 * @license GPL-3.0-or-later
 * @copyright 2023 Israel Nogueira
 * -------------------------------------------------------------------------
 */
trait Security
{
    /**
     * Indica se deve criptografar dados
     * 
     * @var bool
     */
    protected bool $isCrypt = false;

    /**
     * Indica se deve fazer escape em base64
     * 
     * @var bool
     */
    protected bool $isEscape = false;

    /**
     * Indica se é comando direto
     * 
     * @var bool
     */
    protected bool $isCommand = false;

    /**
     * Instância do encriptador
     * 
     * @var Encryption|null
     */
    protected ?Encryption $encryptor = null;

    /**
     * Instância do guardian de colunas
     * 
     * @var ColumnGuard|null
     */
    protected ?ColumnGuard $columnGuard = null;

    /**
     * Colunas preparadas para descriptografia
     * 
     * @var array
     */
    protected array $prepareDeCrypt = [];

    /**
     * Indica se deve preparar criptografia
     * 
     * @var bool
     */
    protected bool $prepareCrypt = false;

    /**
     * Habilita criptografia para próxima operação
     * 
     * @return self
     */
    public function isCrypt(): self
    {
        $this->isCrypt = true;
        return $this;
    }

    /**
     * Habilita escape em base64 para próxima operação
     * 
     * @return self
     */
    public function escape(): self
    {
        $this->isEscape = true;
        return $this;
    }

    /**
     * Alias para escape()
     * 
     * @return self
     */
    public function base64(): self
    {
        return $this->escape();
    }

    /**
     * Marca como comando direto
     * 
     * @return self
     */
    public function command(): self
    {
        $this->isCommand = true;
        return $this;
    }

    /**
     * Obtém instância do encriptador
     * 
     * @return Encryption
     */
    protected function getEncryptor(): Encryption
    {
        if ($this->encryptor === null) {
            $this->encryptor = new Encryption();
        }
        
        return $this->encryptor;
    }

    /**
     * Obtém instância do guardian de colunas
     * 
     * @return ColumnGuard
     */
    protected function getColumnGuard(): ColumnGuard
    {
        if ($this->columnGuard === null) {
            $this->columnGuard = new ColumnGuard();
        }
        
        return $this->columnGuard;
    }

    /**
     * Criptografa dados
     * 
     * @param mixed $data Dados a serem criptografados
     * @return string Dados criptografados
     */
    protected function crypta(mixed $data): string
    {
        $this->isCrypt = false;
        return $this->getEncryptor()->encrypt($data);
    }

    /**
     * Descriptografa dados
     * 
     * @param string $data Dados criptografados
     * @return string Dados descriptografados
     */
    protected function decrypta(string $data): string
    {
        $this->isCrypt = false;
        return $this->getEncryptor()->decrypt($data);
    }

    /**
     * Verifica e valida colunas em expressões JOIN
     * 
     * @param string $expression Expressão SQL
     * @return string Expressão validada
     */
    public function verifyJoinColums(string $expression): string
    {
        $guard = $this->getColumnGuard();
        
        // Configura guardian com as restrições da model
        $guard->setAllowedColumns($this->columnsEnab ?? [])
              ->setBlockedColumns($this->columnsBlock ?? []);

        return $guard->validateJoinExpression($expression);
    }

    /**
     * Verifica se uma coluna individual é permitida
     * 
     * @param string $column Nome da coluna
     * @return string|false Coluna se permitida, false caso contrário
     */
    public function verifyIndividualColum(string $column): string|false
    {
        if (is_null($this->tableClass ?? null)) {
            throw new \RuntimeException("É necessário pelo menos uma tabela ou query cadastrada");
        }

        $guard = $this->getColumnGuard();
        $guard->setAllowedColumns($this->columnsEnab ?? [])
              ->setBlockedColumns($this->columnsBlock ?? []);

        return $guard->isColumnAllowed($column) ? $column : false;
    }

    /**
     * Verifica e retorna colunas permitidas
     * 
     * @return string Lista de colunas separadas por vírgula
     */
    public function verifyColunms(): string
    {
        if (is_null($this->tableClass ?? null)) {
            throw new \RuntimeException("É necessário pelo menos uma tabela ou query cadastrada");
        }

        // Obtém colunas da tabela ou usa as especificadas
        if (is_null($this->colum ?? null)) {
            $columns = $this->showDBColumns($this->tableClass);
        } else {
            $columns = explode(',', $this->colum);
        }

        $guard = $this->getColumnGuard();
        $guard->setAllowedColumns($this->columnsEnab ?? [])
              ->setBlockedColumns($this->columnsBlock ?? []);

        $validColumns = [];
        foreach ($columns as $column) {
            if ($guard->isColumnAllowed($column)) {
                $verified = $this->functionVerifyString($column);
                if ($verified !== false) {
                    $validColumns[] = $verified;
                }
            }
        }

        return str_replace('¸', ',', implode(',', $validColumns));
    }

    /**
     * Verifica se uma função MySQL em array é permitida
     * 
     * @param string $str String contendo função
     * @return array|false Array com função e parâmetros ou false
     */
    public function functionVerifyArray(string $str): array|false
    {
        if (preg_match('/(\w+)\s*\((.*)\)/', $str, $matches)) {
            $function = $matches[1] ?? '';
            $params = $matches[2] ?? '';

            $guard = $this->getColumnGuard();
            $guard->setAllowedFunctions($this->mysqlFnEnabClass ?? [])
                  ->setBlockedFunctions($this->mysqlFnBlockClass ?? []);

            if (!$guard->isFunctionAllowed($function)) {
                return ['function' => '', 'params' => "(NULL)"];
            }

            return ['function' => $function, 'params' => $params];
        }

        return false;
    }

    /**
     * Verifica se funções em uma string são permitidas
     * 
     * @param string $stringColumn String contendo possíveis funções
     * @return string|false String se válida, false caso contrário
     */
    public function functionVerifyString(string $stringColumn): string|false
    {
        $guard = $this->getColumnGuard();
        $guard->setAllowedFunctions($this->mysqlFnEnabClass ?? [])
              ->setBlockedFunctions($this->mysqlFnBlockClass ?? []);

        return $guard->validateFunctionExpression($stringColumn) 
            ? $stringColumn 
            : false;
    }

    /**
     * Previne SQL Injection (método legado - mantido por compatibilidade)
     * 
     * @param string $string String a ser sanitizada
     * @return string String sanitizada
     */
    public function preventMySQLInject(string $string): string
    {
        return Validator::sanitize($string);
    }
}
