<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Backup;

use PDO;
use DateTime;

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
     * Construtor
     * 
     * @param string $mysqldumpPath Caminho do executável mysqldump
     * @param string $outputDir Diretório de saída
     * @param string $username Usuário
     * @param string $password Senha
     * @param string $host Host (padrão: localhost)
     */
    public function __construct(
        string $mysqldumpPath,
        string $outputDir,
        string $username = 'root',
        string $password = '',
        string $host = 'localhost'
    ) {
        $this->mysqldumpPath = $mysqldumpPath;
        $this->outputDir = $outputDir;
        $this->dsn = "mysql:host={$host}";
        $this->username = $username;
        $this->password = $password;
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
     * Executa comando do sistema
     * 
     * @param string $command Comando a executar
     * @return array Output e código de retorno
     */
    private function executeCommand(string $command): array
    {
        $output = [];
        $returnVar = null;
        exec($command, $output, $returnVar);
        
        return ['output' => $output, 'returnVar' => $returnVar];
    }

    /**
     * Retorna lista de bancos de dados
     * 
     * @return array Lista de nomes de bancos
     */
    private function getDatabases(): array
    {
        try {
            $pdo = new PDO($this->dsn, $this->username, $this->password);
            $stmt = $pdo->query("SHOW DATABASES");
            $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Remove bancos do sistema
            return array_filter($databases, function ($db) {
                return !in_array($db, [
                    'information_schema',
                    'mysql',
                    'performance_schema',
                    'phpmyadmin'
                ]);
            });
        } catch (\PDOException $e) {
            throw new \Exception("Erro ao listar bancos: " . $e->getMessage());
        }
    }

    /**
     * Cria diretório de backup
     * 
     * @param string $database Nome do banco
     * @param string $timestamp Timestamp do backup
     * @return string Caminho do diretório criado
     */
    private function createBackupDirectory(string $database, string $timestamp): string
    {
        $path = $this->outputDir . DIRECTORY_SEPARATOR . 
                $database . DIRECTORY_SEPARATOR . 
                $timestamp;

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        return $path;
    }

    /**
     * Faz backup de um banco específico
     * 
     * @param string $database Nome do banco
     * @return bool True se sucesso
     */
    public function backupDatabase(string $database): bool
    {
        $timestamp = $this->getCurrentTimestamp();
        $outputFolder = $this->createBackupDirectory($database, $timestamp);

        // 1. Estrutura (sem triggers/procedures)
        $structureCmd = sprintf(
            '%s -u%s --no-data --skip-triggers --skip-routines --databases %s > %s',
            escapeshellarg($this->mysqldumpPath),
            escapeshellarg($this->username),
            escapeshellarg($database),
            escapeshellarg($outputFolder . DIRECTORY_SEPARATOR . 'estrutura.sql')
        );

        // 2. Triggers, Functions, Procedures
        $routinesCmd = sprintf(
            '%s -u%s --no-data --no-create-info --routines --triggers --databases %s > %s',
            escapeshellarg($this->mysqldumpPath),
            escapeshellarg($this->username),
            escapeshellarg($database),
            escapeshellarg($outputFolder . DIRECTORY_SEPARATOR . 'triggers_functions_procedures.sql')
        );

        // 3. Dados (sem estrutura)
        $dataCmd = sprintf(
            '%s -u%s --no-create-info --skip-triggers --skip-routines --databases %s > %s',
            escapeshellarg($this->mysqldumpPath),
            escapeshellarg($this->username),
            escapeshellarg($database),
            escapeshellarg($outputFolder . DIRECTORY_SEPARATOR . 'conteudo.sql')
        );

        // Executa comandos
        $commands = [$structureCmd, $routinesCmd, $dataCmd];
        
        foreach ($commands as $cmd) {
            $result = $this->executeCommand($cmd);
            
            if ($result['returnVar'] !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Faz backup de todos os bancos de dados
     * 
     * @return array Lista de bancos com sucesso/falha
     */
    public function backupAllDatabases(): array
    {
        $databases = $this->getDatabases();
        $results = [];

        foreach ($databases as $database) {
            $results[$database] = $this->backupDatabase($database);
        }

        return $results;
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
}
