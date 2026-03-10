<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Security;

use Exception;

/**
 * -------------------------------------------------------------------------
 * Class Validator
 * -------------------------------------------------------------------------
 * 
 * Responsável por validar entradas e prevenir SQL Injection.
 * Valida colunas, funções e expressões SQL.
 * 
 * @package IsraelNogueira\galaxyDB\Security
 * @author Israel Nogueira <israel@feats.com>
 * @license GPL-3.0-or-later
 * @copyright 2023 Israel Nogueira
 * -------------------------------------------------------------------------
 */
class Validator
{
    /**
     * Palavras-chave SQL perigosas que devem ser bloqueadas
     */
    private const DANGEROUS_KEYWORDS = [
        'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 
        'EXEC', 'EXECUTE', 'UNION', 'SCRIPT'
    ];

    /**
     * Sanitiza string para prevenir SQL Injection
     * 
     * @param string $input String a ser sanitizada
     * @return string String sanitizada
     */
    public static function sanitize(string $input): string
    {
        // Remove caracteres potencialmente perigosos
        $dangerous = ['@', ';', '*', '?', '|', '+', '%'];
        return str_replace($dangerous, '', $input);
    }

    /**
     * Verifica se a string contém palavras-chave perigosas
     * 
     * @param string $input String a ser verificada
     * @return bool True se contém palavras perigosas
     */
    public static function hasDangerousKeywords(string $input): bool
    {
        $upperInput = strtoupper($input);
        
        foreach (self::DANGEROUS_KEYWORDS as $keyword) {
            if (str_contains($upperInput, $keyword)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Valida nome de coluna
     * 
     * @param string $column Nome da coluna
     * @return bool True se válido
     */
    public static function isValidColumnName(string $column): bool
    {
        // Aceita apenas letras, números e underscores
        return preg_match('/^[a-zA-Z0-9_]+$/', $column) === 1;
    }

    /**
     * Valida nome de tabela
     * 
     * @param string $table Nome da tabela
     * @return bool True se válido
     */
    public static function isValidTableName(string $table): bool
    {
        // Aceita apenas letras, números, underscores e pontos (para aliases)
        return preg_match('/^[a-zA-Z0-9_.]+$/', $table) === 1;
    }

    /**
     * Extrai funções SQL de uma expressão
     * 
     * @param string $expression Expressão SQL
     * @return array Lista de funções encontradas
     */
    public static function extractSQLFunctions(string $expression): array
    {
        preg_match_all('/\b(\w+)\s*\(/i', $expression, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Valida se a expressão contém apenas funções permitidas
     * 
     * @param string $expression Expressão SQL
     * @param array $allowedFunctions Funções permitidas
     * @param array $blockedFunctions Funções bloqueadas
     * @return bool True se válido
     */
    public static function validateFunctions(
        string $expression,
        array $allowedFunctions = [],
        array $blockedFunctions = []
    ): bool {
        $functions = self::extractSQLFunctions($expression);
        
        if (empty($functions)) {
            return true;
        }

        // Se há funções bloqueadas, verifica se alguma está presente
        if (!empty($blockedFunctions)) {
            $blocked = array_intersect($functions, $blockedFunctions);
            if (!empty($blocked)) {
                return false;
            }
        }

        // Se há lista de permitidas, verifica se todas estão permitidas
        if (!empty($allowedFunctions)) {
            $notAllowed = array_diff($functions, $allowedFunctions);
            if (!empty($notAllowed)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Codifica string em base64
     * 
     * @param string $data Dados a serem codificados
     * @return string Dados codificados
     */
    public static function base64Encode(string $data): string
    {
        return base64_encode($data);
    }

    /**
     * Decodifica string de base64
     * 
     * @param string $data Dados codificados
     * @return string Dados decodificados
     */
    public static function base64Decode(string $data): string
    {
        $decoded = base64_decode($data, true);
        return $decoded !== false ? $decoded : $data;
    }
}
