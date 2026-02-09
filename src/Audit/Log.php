<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Audit;

use PDO;
use Exception;

/**
 * -------------------------------------------------------------------------
 * Trait Log
 * -------------------------------------------------------------------------
 * 
 * Gerencia o general_log do MySQL para auditoria de queries.
 * Permite habilitar, limpar e exportar histórico de comandos SQL.
 * 
 * @package IsraelNogueira\galaxyDB\Audit
 * @author Israel Nogueira <israel@feats.com>
 * @license GPL-3.0-or-later
 * @copyright 2023 Israel Nogueira
 * -------------------------------------------------------------------------
 */
trait Log
{
    /**
     * Caminho do arquivo de log
     */
    protected string $logFile;

    /**
     * Verifica se usuário tem permissões para general_log
     * 
     * @return bool True se tem permissões
     */
    public function checkGeneralLogPermissions(): bool
    {
        try {
            $stmt = $this->connection->query("SHOW GRANTS");
            $grants = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Verifica privilégios globais
            foreach ($grants as $grant) {
                if (str_contains($grant, 'ALL PRIVILEGES')) {
                    return true;
                }
            }

            // Verifica privilégios no banco atual
            $stmt = $this->connection->query("SHOW DATABASES");
            $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $currentDb = $this->connection->query("SELECT DATABASE()")->fetchColumn();

            foreach ($databases as $database) {
                if ($database === $currentDb) {
                    $stmt = $this->connection->query("SHOW GRANTS FOR CURRENT_USER()");
                    $grants = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($grants as $grant) {
                        if (str_contains($grant, 'ALL PRIVILEGES')) {
                            return true;
                        }
                    }
                }
            }

            return false;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Habilita general_log do MySQL
     * 
     * @return bool True se habilitado com sucesso
     * @throws Exception Se não tem permissões
     */
    public function enableGeneralLog(): bool
    {
        if (!$this->checkGeneralLogPermissions()) {
            throw new Exception(
                'Usuário não tem permissões para configurar general_log'
            );
        }

        $this->logFile = $this->logFile ?? $this->getDefaultLogPath();
        $path = str_replace('\\', '/', $this->logFile);

        $this->connection->exec("SET GLOBAL general_log = 'ON'");
        $this->connection->exec("SET GLOBAL general_log_file='{$path}'");

        // Cria arquivo se não existir
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
        }

        return true;
    }

    /**
     * Desabilita general_log
     * 
     * @return bool True se desabilitado
     */
    public function disableGeneralLog(): bool
    {
        try {
            $this->connection->exec("SET GLOBAL general_log = 'OFF'");
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Retorna configurações do general_log
     * 
     * @return array Configurações [general_log, general_log_file]
     */
    public function getLogConfig(): array
    {
        $query = "SHOW VARIABLES LIKE '%general_log%'";
        $result = $this->connection->query($query);
        $rows = $result->fetchAll(PDO::FETCH_ASSOC);

        $config = [];
        foreach ($rows as $row) {
            $config[$row['Variable_name']] = $row['Value'];
        }

        return $config;
    }

    /**
     * Limpa entradas do phpMyAdmin do log
     * 
     * @return bool True se limpo com sucesso
     */
    public function cleanLogPhpMyAdmin(): bool
    {
        $config = $this->getLogConfig();
        
        if (!isset($config['general_log_file'])) {
            return false;
        }

        $logFile = $config['general_log_file'];
        
        if (!file_exists($logFile)) {
            return false;
        }

        $content = file_get_contents($logFile);
        $lines = explode("\n", $content);

        // Palavras-chave para remover
        $keywords = [
            '`phpmyadmin`',
            '`pma__',
            'mysql.sock',
            'FROM `mysql`',
            'SELECT @@version',
            'Connect	pma@',
            'Connect	root@',
            'Query	SELECT DATABASE()',
            'Query	SELECT CURRENT_USER()',
            'Query	SHOW SESSION',
            'Query	SHOW',
            "Quit	\n",
            'Query	SELECT `SCHEMA_NAME',
            'INFORMATION_SCHEMA',
            'Query	SET NAMES'
        ];

        // Filtra linhas
        $cleanLines = [];
        foreach ($lines as $line) {
            $skip = false;
            
            foreach ($keywords as $keyword) {
                if (stripos($line, $keyword) !== false) {
                    $skip = true;
                    break;
                }
            }
            
            if (!$skip) {
                $cleanLines[] = $line;
            }
        }

        file_put_contents($logFile, implode("\n", $cleanLines));
        
        return true;
    }

    /**
     * Retorna histórico do banco
     * 
     * @return array [raw, queries] - Linhas brutas e queries filtradas
     */
    public function getHistory(): array
    {
        $config = $this->getLogConfig();

        // Se log está OFF, tenta habilitar
        if ($config['general_log'] === 'OFF') {
            try {
                $this->enableGeneralLog();
                return [[], []];
            } catch (Exception $e) {
                return [[], []];
            }
        }

        // Limpa lixo do phpMyAdmin
        $this->cleanLogPhpMyAdmin();

        $logFile = $config['general_log_file'];
        
        if (!file_exists($logFile)) {
            return [[], []];
        }

        $lines = file($logFile);
        $databases = [];
        $databaseMap = [];
        $raw = [];
        $queries = [];

        $currentDb = getEnv('DB_DATABASE');

        // Processa linhas
        foreach ($lines as $line) {
            $parts = explode('Query', $line);
            
            if (count($parts) < 2) {
                continue;
            }

            $query = trim($parts[1] ?? '');
            $info = trim($parts[0] ?? '');
            $config = explode(' ', $info);

            // Identifica conexão ao banco
            if (count($config) === 3 && $config[1] === 'Init') {
                $dbName = str_replace('DB	', '', $config[2]);
                $id = (int) $config[0];
                
                $databases[$dbName] = [];
                $raw[$dbName] = [];
                $databaseMap[$dbName][] = $id;
            }
        }

        // Filtra queries relevantes
        foreach ($lines as $line) {
            $parts = explode('Query', $line);
            
            if (count($parts) < 2) {
                continue;
            }

            $query = trim($parts[1] ?? '');
            $info = trim($parts[0] ?? '');
            $config = explode(' ', $info);

            if (count($config) !== 1) {
                continue;
            }

            $isPhpMyAdmin = str_contains($query, '`phpmyadmin`');
            
            if ($isPhpMyAdmin) {
                continue;
            }

            foreach (array_keys($databases) as $dbName) {
                if (!in_array($config[0], $databaseMap[$dbName] ?? [])) {
                    continue;
                }

                // Filtra apenas DDL importantes
                $isDDL = $this->isDDLStatement($query);

                if ($isDDL) {
                    $databases[$dbName][] = $query;
                    $raw[$dbName][] = $line;
                }
            }
        }

        if (isset($raw[$currentDb]) && isset($databases[$currentDb])) {
            return [$raw[$currentDb], $databases[$currentDb]];
        }

        return [[], []];
    }

    /**
     * Verifica se é DDL (CREATE, ALTER, DROP)
     * 
     * @param string $query Query SQL
     * @return bool True se for DDL
     */
    private function isDDLStatement(string $query): bool
    {
        $patterns = [
            '/^CREATE (TABLE|FUNCTION|PROCEDURE|TRIGGER|EVENT|DEFINER)/i',
            '/^ALTER (TABLE|FUNCTION|PROCEDURE|TRIGGER|EVENT|DEFINER)/i',
            '/^DROP (TABLE|FUNCTION|PROCEDURE|TRIGGER|EVENT|DEFINER)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Salva histórico em arquivo SQL
     * 
     * @return array [status, message] - Resultado da operação
     */
    public function saveHistoryToFile(): array
    {
        try {
            $timestamp = time();
            $date = date('d-m-Y-H-i-s', $timestamp);
            $dbName = getEnv('DB_DATABASE');
            $filename = "{$dbName}_{$date}.sql";

            $config = $this->getLogConfig();
            $logFile = $config['general_log_file'];
            
            [$raw, $queries] = $this->getHistory();

            if (empty($queries)) {
                return [
                    'status' => false,
                    'message' => 'Nenhuma query para salvar'
                ];
            }

            // Diretório galaxyDB
            $galaxyDir = $this->getGalaxyDir();
            
            if (!is_dir($galaxyDir)) {
                mkdir($galaxyDir, 0755, true);
            }

            $filepath = $galaxyDir . DIRECTORY_SEPARATOR . $filename;

            // Salva arquivo
            file_put_contents($filepath, implode(";\n", $queries) . ';');

            // Limpa log original
            $originalContent = file_get_contents($logFile);
            $cleanedContent = str_replace(
                $raw,
                array_fill(0, count($raw), "			Registro em: {$filename}"),
                $originalContent
            );
            file_put_contents($logFile, $cleanedContent);

            return [
                'status' => true,
                'message' => $filepath
            ];

        } catch (\Throwable $th) {
            return [
                'status' => false,
                'message' => $th->getMessage()
            ];
        }
    }

    /**
     * Retorna diretório padrão do log
     * 
     * @return string
     */
    private function getDefaultLogPath(): string
    {
        return $this->getGalaxyDir() . DIRECTORY_SEPARATOR . 'galaxy.log';
    }

    /**
     * Retorna diretório galaxyDB
     * 
     * @return string
     */
    private function getGalaxyDir(): string
    {
        $baseDir = realpath(
            __DIR__ . DIRECTORY_SEPARATOR . 
            '..' . DIRECTORY_SEPARATOR . 
            '..' . DIRECTORY_SEPARATOR . 
            '..' . DIRECTORY_SEPARATOR . 
            '..' . DIRECTORY_SEPARATOR
        );

        return $baseDir . DIRECTORY_SEPARATOR . 'galaxyDB';
    }
}
