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
     * Máximo de linhas para processar
     */
    protected int $maxLogLines = 10000;

    /**
     * Filtros de exclusão de queries
     */
    protected array $excludePatterns = [];

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
                if (str_contains($grant, 'SUPER')) {
                    return true;
                }
            }

            // Verifica privilégios específicos para general_log
            $requiredPrivileges = ['SUPER', 'RELOAD', 'PROCESS'];
            foreach ($grants as $grant) {
                foreach ($requiredPrivileges as $priv) {
                    if (str_contains($grant, $priv)) {
                        return true;
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
     * @param bool $force Se deve forçar mesmo sem permissões
     * @return bool True se habilitado com sucesso
     * @throws Exception Se não tem permissões
     */
    public function enableGeneralLog(bool $force = false): bool
    {
        if (!$force && !$this->checkGeneralLogPermissions()) {
            throw new Exception(
                'Usuário não tem permissões para configurar general_log'
            );
        }

        $this->logFile = $this->logFile ?? $this->getDefaultLogPath();
        $path = str_replace('\\', '/', $this->logFile);

        // Valida caminho
        $this->validateLogPath($path);

        try {
            $this->connection->exec("SET GLOBAL general_log = 'ON'");
            $this->connection->exec("SET GLOBAL general_log_file='{$path}'");

            // Cria arquivo se não existir
            if (!file_exists($this->logFile)) {
                touch($this->logFile);
                chmod($this->logFile, 0666);
            }

            return true;
        } catch (\PDOException $e) {
            throw new Exception("Erro ao habilitar general_log: " . $e->getMessage());
        }
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
     * Verifica se general_log está ativo
     * 
     * @return bool
     */
    public function isGeneralLogEnabled(): bool
    {
        try {
            $config = $this->getLogConfig();
            return ($config['general_log'] ?? 'OFF') === 'ON';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Retorna configurações do general_log
     * 
     * @return array Configurações [general_log, general_log_file, log_output]
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

        // Adiciona log_output se disponível
        try {
            $stmt = $this->connection->query("SHOW VARIABLES LIKE 'log_output'");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $config['log_output'] = $row['Value'];
            }
        } catch (\PDOException $e) {
            // Ignora se não existir
        }

        return $config;
    }

    /**
     * Limpa entradas indesejadas do log
     * 
     * @param array $customPatterns Padrões adicionais para remover
     * @return bool True se limpo com sucesso
     */
    public function cleanLog(array $customPatterns = []): bool
    {
        $config = $this->getLogConfig();
        
        if (!isset($config['general_log_file'])) {
            return false;
        }

        $logFile = $config['general_log_file'];
        
        if (!file_exists($logFile) || !is_readable($logFile)) {
            return false;
        }

        // Padrões padrão para remover
        $defaultPatterns = [
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
            'Query	SET NAMES',
            'Query	SET SESSION',
            'Query	SHOW VARIABLES',
            'Query	SHOW WARNINGS',
            'Query	SHOW ERRORS',
            'Query	SELECT @@session',
            'Query	SELECT @@global',
            'Query	SELECT CHARACTER',
            'Query	SELECT COLLATION'
        ];

        $patterns = array_merge($defaultPatterns, $customPatterns, $this->excludePatterns);

        // Lê o arquivo
        $content = file_get_contents($logFile);
        if ($content === false) {
            return false;
        }

        $lines = explode("\n", $content);

        // Filtra linhas
        $cleanLines = [];
        foreach ($lines as $line) {
            $skip = false;
            
            foreach ($patterns as $pattern) {
                if (stripos($line, $pattern) !== false) {
                    $skip = true;
                    break;
                }
            }
            
            if (!$skip) {
                $cleanLines[] = $line;
            }
        }

        // Limita número de linhas
        if (count($cleanLines) > $this->maxLogLines) {
            $cleanLines = array_slice($cleanLines, -$this->maxLogLines);
        }

        // Escreve de volta
        return file_put_contents($logFile, implode("\n", $cleanLines)) !== false;
    }

    /**
     * Alias para cleanLog() - mantido por compatibilidade
     */
    public function cleanLogPhpMyAdmin(): bool
    {
        return $this->cleanLog();
    }

    /**
     * Retorna histórico do banco
     * 
     * @param int $limit Limite de queries a retornar
     * @param array $filters Filtros adicionais
     * @return array [raw, queries] - Linhas brutas e queries filtradas
     */
    public function getHistory(int $limit = 1000, array $filters = []): array
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

        // Limpa lixo do log
        $this->cleanLog();

        $logFile = $config['general_log_file'];
        
        if (!file_exists($logFile) || !is_readable($logFile)) {
            return [[], []];
        }

        // Lê apenas as últimas N linhas
        $lines = $this->tailFile($logFile, $limit * 2);
        
        $databases = [];
        $databaseMap = [];
        $raw = [];
        $queries = [];

        $currentDb = getEnv('DB_DATABASE');

        // Primeira passagem: identifica conexões com bancos
        foreach ($lines as $line) {
            $parts = explode('Query', $line);
            
            if (count($parts) < 2) {
                continue;
            }

            $info = trim($parts[0] ?? '');
            $config = explode(' ', $info);

            // Identifica conexão ao banco
            if (count($config) === 3 && $config[1] === 'Init') {
                $dbName = str_replace('DB	', '', $config[2]);
                $id = (int) $config[0];
                
                if (!isset($databases[$dbName])) {
                    $databases[$dbName] = [];
                    $raw[$dbName] = [];
                }
                $databaseMap[$dbName][] = $id;
            }
        }

        // Segunda passagem: filtra queries relevantes
        foreach ($lines as $line) {
            $parts = explode('Query', $line);
            
            if (count($parts) < 2) {
                continue;
            }

            $query = trim($parts[1] ?? '');
            $info = trim($parts[0] ?? '');
            $config = explode(' ', $info);

            if (count($config) < 1) {
                continue;
            }

            // Aplica filtros personalizados
            if (!empty($filters)) {
                $skip = false;
                foreach ($filters as $filter) {
                    if (is_callable($filter)) {
                        if (!$filter($query, $line)) {
                            $skip = true;
                            break;
                        }
                    } elseif (is_string($filter) && stripos($query, $filter) !== false) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) {
                    continue;
                }
            }

            // Verifica se é uma query DDL ou DML importante
            $isRelevant = $this->isRelevantStatement($query);
            
            if (!$isRelevant) {
                continue;
            }

            foreach (array_keys($databases) as $dbName) {
                if (!in_array($config[0], $databaseMap[$dbName] ?? [])) {
                    continue;
                }

                $databases[$dbName][] = $query;
                $raw[$dbName][] = $line;
                
                // Limita resultados
                if (isset($queries[$dbName]) && count($queries[$dbName]) >= $limit) {
                    break 2;
                }
            }
        }

        if (isset($raw[$currentDb]) && isset($databases[$currentDb])) {
            return [$raw[$currentDb], $databases[$currentDb]];
        }

        // Se não encontrou o banco atual, retorna o primeiro que tem dados
        foreach ($databases as $dbName => $dbQueries) {
            if (!empty($dbQueries)) {
                return [$raw[$dbName] ?? [], $dbQueries];
            }
        }

        return [[], []];
    }

    /**
     * Verifica se é uma statement relevante (DDL, DML importante)
     * 
     * @param string $query Query SQL
     * @return bool True se for relevante
     */
    private function isRelevantStatement(string $query): bool
    {
        // Filtra apenas DDL e DML importantes
        $patterns = [
            '/^CREATE (TABLE|FUNCTION|PROCEDURE|TRIGGER|EVENT|DEFINER)/i',
            '/^ALTER (TABLE|FUNCTION|PROCEDURE|TRIGGER|EVENT|DEFINER)/i',
            '/^DROP (TABLE|FUNCTION|PROCEDURE|TRIGGER|EVENT|DEFINER)/i',
            '/^INSERT /i',
            '/^UPDATE /i',
            '/^DELETE /i',
            '/^REPLACE /i',
            '/^TRUNCATE /i',
            '/^RENAME /i',
            '/^SET /i'  // SET para variáveis de sessão
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, trim($query))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lê as últimas N linhas de um arquivo
     * 
     * @param string $filePath
     * @param int $lines
     * @return array
     */
    private function tailFile(string $filePath, int $lines): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return [];
        }

        $buffer = [];
        $pos = -1;
        $lineCount = 0;

        // Pula para o final do arquivo
        fseek($handle, 0, SEEK_END);
        $fileSize = ftell($handle);

        // Lê de trás para frente
        while ($lineCount < $lines && $pos > -$fileSize) {
            // Lê um bloco
            $blockSize = min(8192, -$pos);
            fseek($handle, $pos - $blockSize, SEEK_END);
            $block = fread($handle, $blockSize);
            
            // Conta linhas no bloco
            $linesInBlock = explode("\n", $block);
            
            // Adiciona linhas ao buffer (em ordem inversa)
            for ($i = count($linesInBlock) - 1; $i >= 0 && $lineCount < $lines; $i--) {
                if (!empty($linesInBlock[$i])) {
                    $buffer[] = $linesInBlock[$i];
                    $lineCount++;
                }
            }
            
            $pos -= $blockSize;
        }

        fclose($handle);
        
        // Inverte para ordem correta
        return array_reverse($buffer);
    }

    /**
     * Salva histórico em arquivo SQL
     * 
     * @param string|null $filename Nome do arquivo (opcional)
     * @param int $limit Limite de queries
     * @return array [status, message] - Resultado da operação
     */
    public function saveHistoryToFile(?string $filename = null, int $limit = 1000): array
    {
        try {
            $timestamp = time();
            $date = date('d-m-Y-H-i-s', $timestamp);
            $dbName = getEnv('DB_DATABASE');
            
            if ($filename === null) {
                $filename = "{$dbName}_{$date}.sql";
            }

            $config = $this->getLogConfig();
            $logFile = $config['general_log_file'];
            
            [$raw, $queries] = $this->getHistory($limit);

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

            // Salva arquivo com cabeçalho
            $content = "-- GalaxyDB Audit Log\n";
            $content .= "-- Gerado em: " . date('Y-m-d H:i:s') . "\n";
            $content .= "-- Banco: {$dbName}\n";
            $content .= "-- Total queries: " . count($queries) . "\n\n";
            $content .= implode(";\n", $queries) . ';';

            file_put_contents($filepath, $content);

            // Limpa log original
            if (file_exists($logFile) && is_writable($logFile)) {
                $originalContent = file_get_contents($logFile);
                $cleanedContent = str_replace(
                    $raw,
                    array_fill(0, count($raw), "			-- Registro salvo em: {$filename}"),
                    $originalContent
                );
                file_put_contents($logFile, $cleanedContent);
            }

            return [
                'status' => true,
                'message' => $filepath,
                'file' => $filepath,
                'queries' => count($queries)
            ];

        } catch (\Throwable $th) {
            return [
                'status' => false,
                'message' => $th->getMessage()
            ];
        }
    }

    /**
     * Valida caminho do log
     * 
     * @param string $path
     * @throws Exception
     */
    private function validateLogPath(string $path): void
    {
        $dir = dirname($path);
        
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Exception("Não foi possível criar o diretório de log: {$dir}");
            }
        }

        if (!is_writable($dir)) {
            throw new Exception("Diretório de log não é gravável: {$dir}");
        }

        // Previne path traversal
        $realPath = realpath($dir);
        $basePath = realpath($this->getGalaxyDir());
        
        if ($realPath === false || ($basePath && strpos($realPath, $basePath) !== 0)) {
            throw new Exception("Caminho de log inválido ou fora do diretório permitido");
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
        // Tenta encontrar o diretório raiz do projeto
        $baseDir = __DIR__;
        
        // Sobe até encontrar o diretório galaxyDB ou o root
        for ($i = 0; $i < 10; $i++) {
            $parent = dirname($baseDir);
            if ($parent === $baseDir) {
                break;
            }
            $baseDir = $parent;
            
            // Verifica se existe um diretório galaxyDB
            $galaxyPath = $baseDir . DIRECTORY_SEPARATOR . 'galaxyDB';
            if (is_dir($galaxyPath)) {
                return $galaxyPath;
            }
        }

        // Fallback: usa o diretório atual
        return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'galaxyDB';
    }

    /**
     * Define padrões para excluir do log
     * 
     * @param array $patterns
     * @return self
     */
    public function setExcludePatterns(array $patterns): self
    {
        $this->excludePatterns = $patterns;
        return $this;
    }

    /**
     * Define número máximo de linhas no log
     * 
     * @param int $max
     * @return self
     */
    public function setMaxLogLines(int $max): self
    {
        $this->maxLogLines = max(100, $max);
        return $this;
    }

    /**
     * Retorna estatísticas do log
     * 
     * @return array
     */
    public function getLogStats(): array
    {
        $config = $this->getLogConfig();
        $logFile = $config['general_log_file'] ?? null;

        $stats = [
            'enabled' => ($config['general_log'] ?? 'OFF') === 'ON',
            'file' => $logFile,
            'size' => 0,
            'size_human' => '0 B',
            'lines' => 0,
            'last_modified' => null
        ];

        if ($logFile && file_exists($logFile)) {
            $stats['size'] = filesize($logFile);
            $stats['size_human'] = $this->formatSize($stats['size']);
            $stats['lines'] = count(file($logFile));
            $stats['last_modified'] = date('Y-m-d H:i:s', filemtime($logFile));
        }

        return $stats;
    }

    /**
     * Formata tamanho em bytes para legível
     * 
     * @param int $bytes
     * @return string
     */
    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Rotaciona o arquivo de log
     * 
     * @param int $maxSize Tamanho máximo em bytes antes de rotacionar
     * @param int $keep Número de arquivos antigos a manter
     * @return bool
     */
    public function rotateLog(int $maxSize = 10485760, int $keep = 5): bool
    {
        $config = $this->getLogConfig();
        $logFile = $config['general_log_file'] ?? null;

        if (!$logFile || !file_exists($logFile)) {
            return false;
        }

        if (filesize($logFile) < $maxSize) {
            return true;
        }

        // Rotaciona arquivos
        for ($i = $keep - 1; $i >= 0; $i--) {
            $oldFile = $i === 0 ? $logFile : $logFile . '.' . $i;
            $newFile = $i === 0 ? $logFile . '.1' : $logFile . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                if ($i === 0) {
                    // Copia o arquivo atual para .1
                    copy($oldFile, $newFile);
                    // Trunca o arquivo atual
                    file_put_contents($oldFile, '');
                } elseif (file_exists($newFile)) {
                    // Remove o mais antigo
                    unlink($newFile);
                    // Renomeia
                    rename($oldFile, $newFile);
                }
            }
        }

        return true;
    }
}