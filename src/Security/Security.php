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
     * Nível de segurança (strict, normal, lenient)
     */
    protected string $securityLevel = 'normal';

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
     * Define nível de segurança
     * 
     * @param string $level strict, normal, lenient
     * @return self
     */
    public function setSecurityLevel(string $level): self
    {
        $validLevels = ['strict', 'normal', 'lenient'];
        if (in_array($level, $validLevels)) {
            $this->securityLevel = $level;
        }
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
            
            // Configura com restrições da model
            if (!empty($this->columnsEnab) || !empty($this->columnsBlock)) {
                $this->columnGuard
                    ->setAllowedColumns($this->columnsEnab ?? [])
                    ->setBlockedColumns($this->columnsBlock ?? []);
            }
            
            // Configura funções
            if (!empty($this->mysqlFnEnabClass) || !empty($this->mysqlFnBlockClass)) {
                $this->columnGuard
                    ->setAllowedFunctions($this->mysqlFnEnabClass ?? [])
                    ->setBlockedFunctions($this->mysqlFnBlockClass ?? []);
            }
            
            // Habilita bloqueio de colunas sensíveis se nível strict
            if ($this->securityLevel === 'strict') {
                $this->columnGuard->enableSensitiveBlock(true);
            }
        }
        
        return $this->columnGuard;
    }

    /**
     * Criptografa dados
     * 
     * @param mixed $data Dados a serem criptografados
     * @param bool $strict Se deve lançar exceção em falha
     * @return string Dados criptografados
     * @throws \RuntimeException Se $strict true e falhar
     */
    protected function crypta(mixed $data, bool $strict = true): string
    {
        $this->isCrypt = false;
        
        try {
            return $this->getEncryptor()->encrypt($data);
        } catch (\RuntimeException $e) {
            if ($strict) {
                throw $e;
            }
            return (string) $data;
        }
    }

    /**
     * Descriptografa dados
     * 
     * @param string $data Dados criptografados
     * @param bool $strict Se deve lançar exceção em falha
     * @return string Dados descriptografados
     * @throws \RuntimeException Se $strict true e falhar
     */
    protected function decrypta(string $data, bool $strict = true): string
    {
        $this->isCrypt = false;
        
        try {
            return $this->getEncryptor()->decrypt($data);
        } catch (\RuntimeException $e) {
            if ($strict) {
                throw $e;
            }
            return $data;
        }
    }

    /**
     * Verifica e valida colunas em expressões JOIN
     * 
     * @param string $expression Expressão SQL
     * @param bool $strict Se deve lançar exceção
     * @return string Expressão validada
     * @throws Exception Se $strict true e inválida
     */
    public function verifyJoinColums(string $expression, bool $strict = true): string
    {
        $guard = $this->getColumnGuard();
        return $guard->validateJoinExpression($expression, $strict);
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
        return $guard->isColumnAllowed($column) ? $column : false;
    }

    /**
     * Verifica e retorna colunas permitidas
     * 
     * @param bool $strict Se deve lançar exceção para colunas inválidas
     * @return string Lista de colunas separadas por vírgula
     * @throws Exception Se $strict true e houver colunas inválidas
     */
    public function verifyColunms(bool $strict = true): string
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
        $validColumns = [];
        $invalidColumns = [];

        foreach ($columns as $column) {
            $trimmed = trim($column);
            
            // Verifica se a coluna é permitida
            if ($guard->isColumnAllowed($trimmed)) {
                // Verifica funções na coluna
                $verified = $this->functionVerifyString($trimmed, false);
                if ($verified !== false) {
                    $validColumns[] = $verified;
                } else {
                    $invalidColumns[] = $trimmed;
                }
            } else {
                $invalidColumns[] = $trimmed;
            }
        }

        if ($strict && !empty($invalidColumns)) {
            throw new \Exception(
                "Colunas inválidas ou bloqueadas: " . implode(', ', $invalidColumns)
            );
        }

        return str_replace('¸', ',', implode(',', $validColumns));
    }

    /**
     * Verifica se uma função MySQL em array é permitida
     * 
     * @param string $str String contendo função
     * @param bool $strict Se deve lançar exceção
     * @return array|false Array com função e parâmetros ou false
     * @throws Exception Se $strict true e função inválida
     */
    public function functionVerifyArray(string $str, bool $strict = true): array|false
    {
        if (preg_match('/(\w+)\s*\((.*)\)/', $str, $matches)) {
            $function = $matches[1] ?? '';
            $params = $matches[2] ?? '';

            $guard = $this->getColumnGuard();

            if (!$guard->isFunctionAllowed($function)) {
                if ($strict) {
                    throw new \Exception("Função MySQL bloqueada: {$function}");
                }
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
     * @param bool $strict Se deve lançar exceção
     * @return string|false String se válida, false caso contrário
     * @throws Exception Se $strict true e função inválida
     */
    public function functionVerifyString(string $stringColumn, bool $strict = true): string|false
    {
        $guard = $this->getColumnGuard();

        if ($guard->validateFunctionExpression($stringColumn, $strict)) {
            return $stringColumn;
        }

        return false;
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

    /**
     * Valida SQL completo antes de executar
     * 
     * @param string $sql SQL a ser validado
     * @param bool $strict Se deve lançar exceção
     * @return bool True se seguro
     * @throws Exception Se $strict true e detectar perigo
     */
    public function validateSQL(string $sql, bool $strict = true): bool
    {
        $guard = $this->getColumnGuard();
        return $guard->validateSQL($sql, $strict);
    }

    /**
     * Verifica se uma coluna é sensível
     * 
     * @param string $column Nome da coluna
     * @return bool True se é sensível
     */
    public function isSensitiveColumn(string $column): bool
    {
        $guard = $this->getColumnGuard();
        return $guard->isSensitiveColumn($column);
    }

    /**
     * Valida e sanitiza array de dados para insert/update
     * 
     * @param array $data Dados a serem validados
     * @param bool $strict Se deve lançar exceção
     * @return array Dados validados
     * @throws Exception Se $strict true e detectar perigo
     */
    public function validateData(array $data, bool $strict = true): array
    {
        $result = [];
        
        foreach ($data as $key => $value) {
            // Valida chave (nome da coluna)
            if (!Validator::isValidColumnName($key)) {
                if ($strict) {
                    throw new \Exception("Nome de coluna inválido: {$key}");
                }
                continue;
            }

            // Valida coluna
            if (!$this->verifyIndividualColum($key)) {
                if ($strict) {
                    throw new \Exception("Coluna bloqueada: {$key}");
                }
                continue;
            }

            // Se for string, sanitiza
            if (is_string($value)) {
                // Verifica se contém caracteres perigosos
                if ($this->securityLevel === 'strict' && !Validator::isSafe($value)) {
                    if ($strict) {
                        throw new \Exception("Valor contém caracteres perigosos para a coluna: {$key}");
                    }
                    $value = Validator::sanitize($value);
                }
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Verifica se há restrições de segurança configuradas
     * 
     * @return bool True se há restrições
     */
    public function hasSecurityRestrictions(): bool
    {
        return !empty($this->columnsEnab) || 
               !empty($this->columnsBlock) ||
               !empty($this->mysqlFnEnabClass) ||
               !empty($this->mysqlFnBlockClass) ||
               $this->securityLevel === 'strict';
    }

    /**
     * Obtém colunas bloqueadas atuais
     * 
     * @return array
     */
    public function getBlockedColumns(): array
    {
        return $this->getColumnGuard()->getBlockedColumns();
    }

    /**
     * Obtém colunas permitidas atuais
     * 
     * @return array
     */
    public function getAllowedColumns(): array
    {
        return $this->getColumnGuard()->getAllowedColumns();
    }

    /**
     * Adiciona colunas à lista de bloqueadas
     * 
     * @param array $columns Colunas a bloquear
     * @return self
     */
    public function blockColumns(array $columns): self
    {
        $guard = $this->getColumnGuard();
        $current = $guard->getBlockedColumns();
        $guard->setBlockedColumns(array_merge($current, $columns));
        return $this;
    }

    /**
     * Adiciona colunas à lista de permitidas
     * 
     * @param array $columns Colunas a permitir
     * @return self
     */
    public function allowColumns(array $columns): self
    {
        $guard = $this->getColumnGuard();
        $current = $guard->getAllowedColumns();
        $guard->setAllowedColumns(array_merge($current, $columns));
        return $this;
    }
}