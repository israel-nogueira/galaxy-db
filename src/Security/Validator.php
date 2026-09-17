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
        'EXEC', 'EXECUTE', 'UNION', 'SCRIPT',
        'BENCHMARK', 'SLEEP', 'LOAD_FILE',
        'INTO OUTFILE', 'INTO DUMPFILE',
        ' INFORMATION_SCHEMA ', ' PERFORMANCE_SCHEMA ',
        'INFORMATION_SCHEMA', 'PERFORMANCE_SCHEMA'
    ];

    /**
     * Padrões de SQL Injection comuns
     */
    private const INJECTION_PATTERNS = [
        '/\'[^"]*\'/i',
        '/"[^"]*"/i',
        '/;.*--/i',
        '/\bOR\s+1\s*=\s*1\b/i',
        '/\bOR\s+1\s*=\s*\'1\'/i',
        '/\bOR\s+\'1\'\s*=\s*\'1\'/i',
        '/\bAND\s+1\s*=\s*1\b/i',
        '/\bAND\s+\'1\'\s*=\s*\'1\'/i',
        '/\bUNION\s+SELECT\b/i',
        '/\bSELECT\s+.*\s+FROM\s+.*\s+WHERE\s+.*\s*=\s*.*--/i',
        '/\bINSERT\s+INTO\b/i',
        '/\bUPDATE\s+.*\s+SET\b/i',
        '/\bDELETE\s+FROM\b/i',
        '/\bDROP\s+TABLE\b/i',
        '/\bALTER\s+TABLE\b/i',
        '/\bCREATE\s+TABLE\b/i',
        '/\bTRUNCATE\s+TABLE\b/i',
        '/\bEXEC\s+.*\s+XP_/i',
        '/\bEXEC\s+SP_/i',
        '/\bWAITFOR\s+DELAY\b/i',
        '/\bBENCHMARK\s*\(/i',
        '/\bSLEEP\s*\(/i',
        '/\bLOAD_FILE\s*\(/i',
        '/\bINTO\s+OUTFILE\b/i',
        '/\bINTO\s+DUMPFILE\b/i',
        '/\bGROUP_CONCAT\s*\(/i',
        '/\bCONCAT\s*\(/i',
        '/\bSUBSTR\s*\(/i',
        '/\bSUBSTRING\s*\(/i',
        '/\bMID\s*\(/i',
        '/\bIF\s*\(/i',
        '/\bCASE\s+WHEN\b/i',
        '/\bELSE\s+.*\s+END\b/i',
        '/\bLIKE\s+.*\'%\'\s+AND\b/i',
        '/\bLIKE\s+.*\'_\'\s+AND\b/i'
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
        $dangerous = ['@', ';', '*', '?', '|', '+', '%', '`', '"', "'"];
        return str_replace($dangerous, '', $input);
    }

    /**
     * Sanitiza com escape de caracteres especiais
     * 
     * @param string $input String a ser sanitizada
     * @return string String com escape
     */
    public static function escape(string $input): string
    {
        return addslashes(trim($input));
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
     * Verifica se a string contém padrões de SQL Injection
     * 
     * @param string $input String a ser verificada
     * @return bool True se contém padrões de injection
     */
    public static function hasInjectionPatterns(string $input): bool
    {
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Valida completamente uma string contra SQL Injection
     * 
     * @param string $input String a ser validada
     * @return bool True se segura
     */
    public static function isSafe(string $input): bool
    {
        return !self::hasDangerousKeywords($input) && !self::hasInjectionPatterns($input);
    }

    /**
     * Valida e sanitiza completamente
     * 
     * @param string $input String a ser processada
     * @param bool $strict Modo estrito (lança exceção se perigoso)
     * @return string String sanitizada
     * @throws Exception Se $strict=true e detectar perigo
     */
    public static function validate(string $input, bool $strict = false): string
    {
        if ($strict && !self::isSafe($input)) {
            throw new Exception("SQL Injection detectada na entrada: " . substr($input, 0, 100));
        }
        
        return self::sanitize($input);
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
     * Valida nome de coluna com alias (ex: tabela.coluna)
     * 
     * @param string $column Nome da coluna com alias
     * @return bool True se válido
     */
    public static function isValidQualifiedColumn(string $column): bool
    {
        return preg_match('/^[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+$/', $column) === 1;
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
        return array_unique($matches[1] ?? []);
    }

    /**
     * Extrai colunas de uma expressão SQL
     * 
     * @param string $expression Expressão SQL
     * @return array Lista de colunas encontradas
     */
    public static function extractColumns(string $expression): array
    {
        // Extrai colunas no formato tabela.coluna ou apenas coluna
        preg_match_all('/(?:([a-zA-Z0-9_]+)\.)?([a-zA-Z0-9_]+)/', $expression, $matches);
        
        $columns = [];
        if (isset($matches[2])) {
            foreach ($matches[2] as $index => $col) {
                $table = $matches[1][$index] ?? '';
                $columns[] = $table ? "{$table}.{$col}" : $col;
            }
        }
        
        return array_unique($columns);
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

    /**
     * Verifica se é uma string base64 válida
     * 
     * @param string $data String a ser verificada
     * @return bool True se é base64 válido
     */
    public static function isValidBase64(string $data): bool
    {
        return base64_decode($data, true) !== false;
    }

    /**
     * Sanitiza array recursivamente
     * 
     * @param array $data Array a ser sanitizado
     * @param bool $recursive Se deve sanitizar recursivamente
     * @return array Array sanitizado
     */
    public static function sanitizeArray(array $data, bool $recursive = true): array
    {
        $result = [];
        
        foreach ($data as $key => $value) {
            $safeKey = self::sanitize((string) $key);
            
            if (is_array($value) && $recursive) {
                $result[$safeKey] = self::sanitizeArray($value, true);
            } elseif (is_string($value)) {
                $result[$safeKey] = self::sanitize($value);
            } else {
                $result[$safeKey] = $value;
            }
        }
        
        return $result;
    }

    /**
     * Valida array recursivamente contra SQL Injection
     * 
     * @param array $data Array a ser validado
     * @param bool $strict Modo estrito
     * @return bool True se seguro
     * @throws Exception Se $strict=true e detectar perigo
     */
    public static function validateArray(array $data, bool $strict = false): bool
    {
        foreach ($data as $key => $value) {
            if (!self::isSafe((string) $key)) {
                if ($strict) {
                    throw new Exception("SQL Injection detectada na chave: " . substr($key, 0, 100));
                }
                return false;
            }
            
            if (is_array($value)) {
                if (!self::validateArray($value, $strict)) {
                    return false;
                }
            } elseif (is_string($value) && !self::isSafe($value)) {
                if ($strict) {
                    throw new Exception("SQL Injection detectada no valor: " . substr($value, 0, 100));
                }
                return false;
            }
        }
        
        return true;
    }

    /**
     * Limpa comentários SQL
     * 
     * @param string $sql SQL a ser limpo
     * @return string SQL sem comentários
     */
    public static function removeComments(string $sql): string
    {
        // Remove comentários de bloco /* */
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Remove comentários de linha -- e #
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/#.*$/m', '', $sql);
        
        return $sql;
    }

    /**
     * Normaliza espaços em branco no SQL
     * 
     * @param string $sql SQL a ser normalizado
     * @return string SQL normalizado
     */
    public static function normalizeSQL(string $sql): string
    {
        // Remove espaços extras
        $sql = preg_replace('/\s+/', ' ', $sql);
        
        // Remove espaços antes de pontuação
        $sql = preg_replace('/\s+([,;])/', '$1', $sql);
        
        return trim($sql);
    }
}