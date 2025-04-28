<?php

trait MysqlBackupTrait
{
    private $mysqlDumpPath = "D:\\NOGUEIRA\\xampp\\mysql\\bin\\mysqldump.exe";
    private $outputDir = "D:\\NOGUEIRA\\LOCALHOS-BASE-FILES\\bkp_mysql";
    private $dsn = "mysql:host=localhost";
    private $username = "root";
    private $password = "";

    private function getCurrentTimestamp()
    {
        $datetime = new \DateTime();
        return $datetime->format('Y-m-d__H-i');
    }

    private function executeCommand($command)
    {
        $output = [];
        $returnVar = null;
        exec($command, $output, $returnVar);
        return ['output' => $output, 'returnVar' => $returnVar];
    }

    private function getDatabases()
    {
        try {
            $pdo = new \PDO($this->dsn, $this->username, $this->password);
            $stmt = $pdo->query("SHOW DATABASES;");
            $databases = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            return array_filter($databases, function ($db) {
                return !in_array($db, ['information_schema', 'mysql', 'performance_schema', 'phpmyadmin']);
            });
        } catch (\PDOException $e) {
            echo "Error: " . $e->getMessage();
            return [];
        }
    }

    private function createBackupDirectory($database, $timestamp)
    {
        $outputFolder = sprintf('%s\\%s\\%s', $this->outputDir, $database, $timestamp);
        if (!file_exists($outputFolder)) {
            mkdir($outputFolder, 0777, true);
        }
        return $outputFolder;
    }

    private function backupDatabase($database)
    {
        $timestamp = $this->getCurrentTimestamp();
        $outputFolder = $this->createBackupDirectory($database, $timestamp);

        $commands = [
            sprintf(
                '%s -u%s --no-data --skip-triggers --skip-routines --databases %s > %s\\estrutura.sql',
                escapeshellarg($this->mysqlDumpPath),
                escapeshellarg($this->username),
                escapeshellarg($database),
                escapeshellarg($outputFolder)
            ),
            sprintf(
                '%s -u%s --no-data --no-create-info --routines --triggers --databases %s > %s\\triggers_functions_procedures_eventos.sql',
                escapeshellarg($this->mysqlDumpPath),
                escapeshellarg($this->username),
                escapeshellarg($database),
                escapeshellarg($outputFolder)
            ),
            sprintf(
                '%s -u%s --no-create-info --skip-triggers --skip-routines --databases %s > %s\\conteudo.sql',
                escapeshellarg($this->mysqlDumpPath),
                escapeshellarg($this->username),
                escapeshellarg($database),
                escapeshellarg($outputFolder)
            ),
        ];

        foreach ($commands as $command) {
            $this->executeCommand($command);
        }
    }

    public function backupAllDatabases()
    {
        $databases = $this->getDatabases();
        foreach ($databases as $database) {
            $this->backupDatabase($database);
        }
        echo "PROCESSO CONCLUIDO!\n";
    }
}

class BackupManager
{
    use MysqlBackupTrait;

    public function runBackup()
    {
        $this->backupAllDatabases();
    }
}

$backupManager = new BackupManager();
$backupManager->runBackup();
