<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Backup;

use PDO;
use DateTime;
use Exception;

/**
 * -------------------------------------------------------------------------
 * Class MysqlBackup
 * -------------------------------------------------------------------------
 * 
 * Backup MySQL usando mysqldump nativo.
 * Gera backups separados: estrutura, triggers/procedures/functions, dados.
 * 
 * @package IsraelNogueira\galaxyDB\Backup
 * @author Israel Nogueira <israel@feats.com>
 * @license GPL-3.0-or-later
 * @copyright 2023 Israel Nogueira
 * -------------------------------------------------------------------------
 */
class MysqlBackup
{
    /**
     * Caminho do mysqldump
     */
    private string $mysqldumpPath;

    /**
     * Diretório de saída dos backups
     */
    private string $outputDir;

    /**
     * DSN de conexão
     */
    private string $dsn;

    /**
     * Usuário do banco
     */
    private string $username;

    /**
     * Senha do banco
     */
    private string $password;

    /**
     * Host do banco
     */
    private string $host;

    /**
     * Porta do banco
     */
    private int $port;

    /**
     * Opções adicionais do mysqldump
     */
    private array $extraOptions = [];

    /**
     * Timeout do backup em segundos
     */
    private int $timeout = 300;

    /**
     * Máximo de tentativas em caso de falha
     */
    private int $maxRetries = 3;

    /**
     * Construtor
     * 
     * @param string $mysqldumpPath Caminho do executável mysqldump
     * @param string $outputDir Diretório de saída
     * @param string $username Usuário
     * @param string $password Senha
     * @param string $host Host (padrão: localhost)
     * @param int $port Porta (padrão: 3306)
     */

    public function __construct(
        string $mysqldumpPath,
        string $outputDir,
        string $username = 'root',
        string $password = '',
        string $host = 'localhost',
        int $port = 3306
    ) {
        $this->mysqldumpPath = $mysqldumpPath;
        $this->outputDir = $outputDir;
        $this->dsn = "mysql:host={$host};port={$port}";
        $this->username = $username;
        $this->password = $password;
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * Retorna timestamp atual formatado
     * 
     * @return string Timestamp no formato Y-m-d__H-i
     */
    private function getCurrentTimestamp(): string
    {
        return (new DateTime())->format('Y-m-d__H-i');
    }

    /**
     * Executa comando do sistema com timeout e retry
     * 
     * @param string $command Comando a executar
     * @param int $attempt Tentativa atual
     * @return array Output e código de retorno
     * @throws Exception Se falhar após todas as tentativas
     */
    private function executeCommand(string $command, int $attempt = 1): array
    {
        $output = [];
        $returnVar = null;

        // Adiciona timeout
        if (PHP_OS !== 'WIN') {
            $command = "timeout {$this->timeout} " . $command;
        }

        exec($command, $output, $returnVar);

        if ($returnVar !== 0 && $attempt < $this->maxRetries) {
            // Aguarda antes de tentar novamente
            sleep(2 * $attempt);
            return $this->executeCommand($command, $attempt + 1);
        }

        return ['output' => $output, 'returnVar' => $returnVar];
    }

    /**
     * Retorna lista de bancos de dados
     * 
     * @param array $exclude Bancos a excluir
     * @return array Lista de nomes de bancos
     * @throws Exception Se erro na conexão
     */
    private function getDatabases(array $exclude = []): array
    {
        $defaultExclude = [
            'information_schema',
            'mysql',
            'performance_schema',
            'phpmyadmin',
            'sys'
        ];

        $exclude = array_merge($defaultExclude, $exclude);

        try {
            $pdo = new PDO($this->dsn, $this->username, $this->password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->query("SHOW DATABASES");
            $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Remove bancos do sistema
            return array_filter($databases, function ($db) use ($exclude) {
                return !in_array(strtolower($db), array_map('strtolower', $exclude));
            });
        } catch (\PDOException $e) {
            throw new Exception("Erro ao listar bancos: " . $e->getMessage());
        }
    }

    /**
     * Cria diretório de backup
     * 
     * @param string $database Nome do banco
     * @param string $timestamp Timestamp do backup
     * @return string Caminho do diretório criado
     * @throws Exception Se não for possível criar
     */
    private function createBackupDirectory(string $database, string $timestamp): string
    {
        $path = $this->outputDir . DIRECTORY_SEPARATOR . 
                $database . DIRECTORY_SEPARATOR . 
                $timestamp;

        if (!is_dir($path)) {
            if (!mkdir($path, 0777, true)) {
                throw new Exception("Não foi possível criar o diretório: {$path}");
            }
        }

        return $path;
    }

    /**
     * Valida comando básico para segurança
     * 
     * @param string $command
     * @return bool
     */
    private function validateCommand(string $command): bool
    {
        // Verifica caracteres perigosos
        $dangerous = [';', '&&', '||', '|', '>', '<', '`', '$', '(', ')'];
        foreach ($dangerous as $char) {
            if (strpos($command, $char) !== false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Monta comando mysqldump com opções
     * 
     * @param string $database Nome do banco
     * @param array $options Opções do mysqldump
     * @param string $outputFile Arquivo de saída
     * @return string Comando completo
     */
    private function buildMysqldumpCommand(string $database, array $options, string $outputFile): string
    {
        // Opções básicas
        $baseOptions = [
            '-u' . escapeshellarg($this->username),
            '-h' . escapeshellarg($this->host),
            '-P' . $this->port,
            '--default-character-set=utf8mb4'
        ];

        // Adiciona senha se não estiver vazia
        if (!empty($this->password)) {
            $baseOptions[] = '-p' . escapeshellarg($this->password);
        }

        // Combina todas as opções
        $allOptions = array_merge($baseOptions, $options, $this->extraOptions);

        // Monta comando
        $command = escapeshellcmd($this->mysqldumpPath) . ' ' . 
                   implode(' ', $allOptions) . ' ' .
                   escapeshellarg($database) . ' > ' . 
                   escapeshellarg($outputFile);

        return $command;
    }

    /**
     * Faz backup de um banco específico
     * 
     * @param string $database Nome do banco
     * @param array $options Opções adicionais
     * @return array Resultado do backup
     * @throws Exception Se falhar
     */
    public function backupDatabase(string $database, array $options = []): array
    {
        $timestamp = $this->getCurrentTimestamp();
        $outputFolder = $this->createBackupDirectory($database, $timestamp);

        // Valida nome do banco
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $database)) {
            throw new Exception("Nome de banco inválido: {$database}");
        }

        $results = [];

        // 1. Estrutura (sem triggers/procedures)
        $structureFile = $outputFolder . DIRECTORY_SEPARATOR . 'estrutura.sql';
        $structureOptions = array_merge([
            '--no-data',
            '--skip-triggers',
            '--skip-routines',
            '--skip-comments'
        ], $options);
        
        $cmd = $this->buildMysqldumpCommand($database, $structureOptions, $structureFile);
        
        if (!$this->validateCommand($cmd)) {
            throw new Exception("Comando inválido detectado");
        }

        $result = $this->executeCommand($cmd);
        $results['estrutura'] = [
            'file' => $structureFile,
            'size' => file_exists($structureFile) ? filesize($structureFile) : 0,
            'success' => $result['returnVar'] === 0
        ];

        // 2. Triggers, Functions, Procedures
        $routinesFile = $outputFolder . DIRECTORY_SEPARATOR . 'triggers_functions_procedures.sql';
        $routinesOptions = array_merge([
            '--no-data',
            '--no-create-info',
            '--routines',
            '--triggers',
            '--skip-comments'
        ], $options);
        
        $cmd = $this->buildMysqldumpCommand($database, $routinesOptions, $routinesFile);
        
        if (!$this->validateCommand($cmd)) {
            throw new Exception("Comando inválido detectado");
        }

        $result = $this->executeCommand($cmd);
        $results['routines'] = [
            'file' => $routinesFile,
            'size' => file_exists($routinesFile) ? filesize($routinesFile) : 0,
            'success' => $result['returnVar'] === 0
        ];

        // 3. Dados (sem estrutura)
        $dataFile = $outputFolder . DIRECTORY_SEPARATOR . 'conteudo.sql';
        $dataOptions = array_merge([
            '--no-create-info',
            '--skip-triggers',
            '--skip-routines',
            '--skip-comments',
            '--complete-insert'
        ], $options);
        
        $cmd = $this->buildMysqldumpCommand($database, $dataOptions, $dataFile);
        
        if (!$this->validateCommand($cmd)) {
            throw new Exception("Comando inválido detectado");
        }

        $result = $this->executeCommand($cmd);
        $results['dados'] = [
            'file' => $dataFile,
            'size' => file_exists($dataFile) ? filesize($dataFile) : 0,
            'success' => $result['returnVar'] === 0
        ];

        // Verifica se todos os backups foram bem-sucedidos
        $allSuccess = true;
        foreach ($results as $key => $value) {
            if (!$value['success']) {
                $allSuccess = false;
                $results['error'] = "Falha no backup de {$key}";
                break;
            }
        }

        // Se algum falhou, remove os arquivos
        if (!$allSuccess) {
            foreach ($results as $key => $value) {
                if (isset($value['file']) && file_exists($value['file'])) {
                    unlink($value['file']);
                }
            }
            // Remove diretório se vazio
            if (is_dir($outputFolder) && count(scandir($outputFolder)) === 2) {
                rmdir($outputFolder);
            }
            throw new Exception($results['error'] ?? "Falha no backup do banco {$database}");
        }

        // Cria arquivo de metadados
        $this->createMetadataFile($outputFolder, $database, $timestamp);

        return [
            'success' => true,
            'database' => $database,
            'timestamp' => $timestamp,
            'folder' => $outputFolder,
            'files' => $results,
            'total_size' => array_sum(array_column($results, 'size'))
        ];
    }

    /**
     * Cria arquivo de metadados do backup
     * 
     * @param string $folder
     * @param string $database
     * @param string $timestamp
     */
    private function createMetadataFile(string $folder, string $database, string $timestamp): void
    {
        $metadata = [
            'database' => $database,
            'timestamp' => $timestamp,
            'date' => date('Y-m-d H:i:s'),
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'mysqldump_version' => $this->getMysqldumpVersion(),
            'files' => []
        ];

        $files = scandir($folder);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $path = $folder . DIRECTORY_SEPARATOR . $file;
                $metadata['files'][$file] = [
                    'size' => filesize($path),
                    'modified' => date('Y-m-d H:i:s', filemtime($path))
                ];
            }
        }

        file_put_contents(
            $folder . DIRECTORY_SEPARATOR . 'metadata.json',
            json_encode($metadata, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Obtém versão do mysqldump
     * 
     * @return string
     */
    private function getMysqldumpVersion(): string
    {
        try {
            $cmd = escapeshellcmd($this->mysqldumpPath) . ' --version';
            exec($cmd, $output);
            return $output[0] ?? 'unknown';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    /**
     * Faz backup de todos os bancos de dados
     * 
     * @param array $exclude Bancos a excluir
     * @param array $options Opções adicionais
     * @return array Lista de bancos com sucesso/falha
     */
    public function backupAllDatabases(array $exclude = [], array $options = []): array
    {
        $databases = $this->getDatabases($exclude);
        $results = [];

        foreach ($databases as $database) {
            try {
                $result = $this->backupDatabase($database, $options);
                $results[$database] = $result;
            } catch (Exception $e) {
                $results[$database] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Faz backup apenas da estrutura
     * 
     * @param string $database
     * @return array
     */
    public function backupStructure(string $database): array
    {
        $timestamp = $this->getCurrentTimestamp();
        $outputFolder = $this->createBackupDirectory($database, $timestamp);

        $file = $outputFolder . DIRECTORY_SEPARATOR . 'estrutura_only.sql';
        $options = [
            '--no-data',
            '--skip-triggers',
            '--skip-routines',
            '--skip-comments'
        ];

        $cmd = $this->buildMysqldumpCommand($database, $options, $file);
        
        if (!$this->validateCommand($cmd)) {
            throw new Exception("Comando inválido detectado");
        }

        $result = $this->executeCommand($cmd);

        if ($result['returnVar'] !== 0) {
            throw new Exception("Falha ao fazer backup da estrutura");
        }

        return [
            'success' => true,
            'database' => $database,
            'file' => $file,
            'size' => filesize($file)
        ];
    }

    /**
     * Faz backup apenas dos dados
     * 
     * @param string $database
     * @return array
     */
    public function backupDataOnly(string $database): array
    {
        $timestamp = $this->getCurrentTimestamp();
        $outputFolder = $this->createBackupDirectory($database, $timestamp);

        $file = $outputFolder . DIRECTORY_SEPARATOR . 'dados_only.sql';
        $options = [
            '--no-create-info',
            '--skip-triggers',
            '--skip-routines',
            '--skip-comments',
            '--complete-insert'
        ];

        $cmd = $this->buildMysqldumpCommand($database, $options, $file);
        
        if (!$this->validateCommand($cmd)) {
            throw new Exception("Comando inválido detectado");
        }

        $result = $this->executeCommand($cmd);

        if ($result['returnVar'] !== 0) {
            throw new Exception("Falha ao fazer backup dos dados");
        }

        return [
            'success' => true,
            'database' => $database,
            'file' => $file,
            'size' => filesize($file)
        ];
    }

    /**
     * Restaura um backup
     * 
     * @param string $database Nome do banco
     * @param string $filePath Caminho do arquivo SQL
     * @return bool
     */
    public function restore(string $database, string $filePath): bool
    {
        if (!file_exists($filePath)) {
            throw new Exception("Arquivo de backup não encontrado: {$filePath}");
        }

        // Monta comando mysql para restaurar
        $mysqlCmd = 'mysql';
        if (PHP_OS !== 'WIN') {
            $mysqlCmd = 'mysql';
        }

        $command = escapeshellcmd($mysqlCmd) . ' ' .
                   '-u' . escapeshellarg($this->username) . ' ' .
                   '-h' . escapeshellarg($this->host) . ' ' .
                   '-P' . $this->port . ' ' .
                   (empty($this->password) ? '' : '-p' . escapeshellarg($this->password)) . ' ' .
                   escapeshellarg($database) . ' < ' .
                   escapeshellarg($filePath);

        if (!$this->validateCommand($command)) {
            throw new Exception("Comando inválido detectado");
        }

        $result = $this->executeCommand($command);

        return $result['returnVar'] === 0;
    }

    /**
     * Define caminho do mysqldump
     * 
     * @param string $path Caminho do executável
     * @return self
     */
    public function setMysqldumpPath(string $path): self
    {
        $this->mysqldumpPath = $path;
        return $this;
    }

    /**
     * Define diretório de saída
     * 
     * @param string $dir Diretório
     * @return self
     */
    public function setOutputDir(string $dir): self
    {
        $this->outputDir = $dir;
        return $this;
    }

    /**
     * Define timeout do backup
     * 
     * @param int $seconds
     * @return self
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = max(10, $seconds);
        return $this;
    }

    /**
     * Define número máximo de tentativas
     * 
     * @param int $retries
     * @return self
     */
    public function setMaxRetries(int $retries): self
    {
        $this->maxRetries = max(1, $retries);
        return $this;
    }

    /**
     * Adiciona opções extras ao mysqldump
     * 
     * @param array $options
     * @return self
     */
    public function addExtraOptions(array $options): self
    {
        $this->extraOptions = array_merge($this->extraOptions, $options);
        return $this;
    }

    /**
     * Limpa backups antigos
     * 
     * @param int $days Dias a manter
     * @param string $database Banco específico (opcional)
     * @return int Número de backups removidos
     */
    public function cleanOldBackups(int $days = 30, ?string $database = null): int
    {
        $deleted = 0;
        $cutoff = time() - ($days * 86400);

        $searchPath = $this->outputDir;
        if ($database !== null) {
            $searchPath .= DIRECTORY_SEPARATOR . $database;
        }

        if (!is_dir($searchPath)) {
            return 0;
        }

        $items = scandir($searchPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $searchPath . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                // Verifica se é um diretório de backup (timestamp)
                if (preg_match('/^\d{4}-\d{2}-\d{2}__\d{2}-\d{2}$/', $item)) {
                    $dirTime = filemtime($path);
                    if ($dirTime < $cutoff) {
                        $this->removeDirectory($path);
                        $deleted++;
                    }
                } else {
                    // Subdiretório (banco)
                    $deleted += $this->cleanOldBackups($days, ($database ? $database . DIRECTORY_SEPARATOR : '') . $item);
                }
            }
        }

        return $deleted;
    }

    /**
     * Remove diretório recursivamente
     * 
     * @param string $path
     */
    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $this->removeDirectory($fullPath);
            } else {
                unlink($fullPath);
            }
        }

        rmdir($path);
    }

    /**
     * Obtém informações de backups existentes
     * 
     * @param string $database
     * @return array
     */
    public function getBackupInfo(string $database): array
    {
        $path = $this->outputDir . DIRECTORY_SEPARATOR . $database;
        
        if (!is_dir($path)) {
            return [];
        }

        $backups = [];
        $items = scandir($path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            
            if (is_dir($fullPath) && preg_match('/^\d{4}-\d{2}-\d{2}__\d{2}-\d{2}$/', $item)) {
                $metadata = $fullPath . DIRECTORY_SEPARATOR . 'metadata.json';
                
                $backups[] = [
                    'timestamp' => $item,
                    'date' => date('Y-m-d H:i:s', filemtime($fullPath)),
                    'path' => $fullPath,
                    'size' => $this->getDirectorySize($fullPath),
                    'has_metadata' => file_exists($metadata),
                    'files' => array_diff(scandir($fullPath), ['.', '..'])
                ];
            }
        }

        // Ordena por data (mais recente primeiro)
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return $backups;
    }

    /**
     * Calcula tamanho do diretório
     * 
     * @param string $path
     * @return int Tamanho em bytes
     */
    private function getDirectorySize(string $path): int
    {
        $size = 0;
        $items = scandir($path);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $size += $this->getDirectorySize($fullPath);
            } else {
                $size += filesize($fullPath);
            }
        }
        
        return $size;
    }

    /**
     * Verifica se o mysqldump está disponível
     * 
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            $cmd = escapeshellcmd($this->mysqldumpPath) . ' --version';
            exec($cmd, $output, $returnVar);
            return $returnVar === 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtém informações de conexão
     * 
     * @return array
     */
    public function getConnectionInfo(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'database' => 'multiple',
            'driver' => 'mysql',
            'mysqldump_path' => $this->mysqldumpPath,
            'output_dir' => $this->outputDir
        ];
    }

    /**
     * Obtém estatísticas de backups
     * 
     * @return array
     */
    public function getStats(): array
    {
        $totalBackups = 0;
        $totalSize = 0;
        $oldest = null;
        $newest = null;

        $databases = $this->getDatabases();
        foreach ($databases as $database) {
            $backups = $this->getBackupInfo($database);
            $totalBackups += count($backups);
            
            foreach ($backups as $backup) {
                $totalSize += $backup['size'];
                $timestamp = strtotime($backup['date']);
                
                if ($oldest === null || $timestamp < $oldest) {
                    $oldest = $timestamp;
                }
                if ($newest === null || $timestamp > $newest) {
                    $newest = $timestamp;
                }
            }
        }

        return [
            'total_backups' => $totalBackups,
            'total_size' => $totalSize,
            'total_size_human' => $this->formatSize($totalSize),
            'oldest_backup' => $oldest ? date('Y-m-d H:i:s', $oldest) : null,
            'newest_backup' => $newest ? date('Y-m-d H:i:s', $newest) : null,
            'databases_count' => count($databases)
        ];
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
}