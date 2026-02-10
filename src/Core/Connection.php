<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Core;

use PDO;
use PDOException;
use Exception;

trait Connection
{
    protected ?PDO $connection = null;
    private static array $connectionPool = [];

    public static function errorConnection(PDOException $error): never
    {
        throw new Exception(
            "Erro de conexão com o banco de dados: " . $error->getMessage(),
            (int) $error->getCode()
        );
    }

    public static function connect(array $db = []): PDO
    {
        $connectionKey = md5(serialize($db));
        
        if (isset(self::$connectionPool[$connectionKey])) {
            return self::$connectionPool[$connectionKey];
        }

        $user = $db['DB_USERNAME'] ?? getEnv('DB_USERNAME');
        $type = $db['DB_TYPE']     ?? getEnv('DB_TYPE');
        $pass = $db['DB_PASSWORD'] ?? getEnv('DB_PASSWORD');
        $name = $db['DB_DATABASE'] ?? getEnv('DB_DATABASE');
        $host = $db['DB_HOST']     ?? getEnv('DB_HOST');
        $port = $db['DB_PORT']     ?? getEnv('DB_PORT');
        
        // Garante que char seja string ou null
        $char = $db['DB_CHAR'] ?? getEnv('DB_CHAR');
        $char = ($char === false || $char === '' || $char === null) ? null : (string) $char;
        
        $flow = $db['DB_FLOW']     ?? getEnv('DB_FLOW');
        $fkey = $db['DB_FKEY']     ?? getEnv('DB_FKEY');

        $conn = match ($type) {
            'pgsql'  => self::connectPostgreSQL($host, $port, $name, $user, $pass, $char),
            'mysql', 'mysqli' => self::connectMySQL($host, $port, $name, $user, $pass, $char),
            'sqlite' => self::connectSQLite($name, $fkey),
            'ibase', 'fbird' => self::connectFirebird($host, $port, $name, $user, $pass, $char),
            'oracle' => self::connectOracle($host, $port, $name, $user, $pass, $char, $db),
            'mssql'  => self::connectMSSQL($host, $port, $name, $user, $pass, $char),
            'dblib'  => self::connectDBLib($host, $port, $name, $user, $pass, $char),
            'sqlsrv' => self::connectSQLServer($host, $port, $name, $user, $pass),
            default  => throw new Exception("Driver não suportado: {$type}")
        };

        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if ($flow === '1') {
            $conn->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        }

        self::$connectionPool[$connectionKey] = $conn;
        self::$dbaseType = $type;

        return $conn;
    }

    private static function connectPostgreSQL(
        string $host,
        ?string $port,
        string $name,
        string $user,
        string $pass,
        ?string $char
    ): PDO {
        $port = $port ?: '5432';
        
        try {
            $conn = new PDO(
                "pgsql:dbname={$name};user={$user};password={$pass};host={$host};port={$port}"
            );

            if (!empty($char)) {
                $conn->exec("SET CLIENT_ENCODING TO '{$char}';");
            }

            return $conn;
        } catch (PDOException $e) {
            self::errorConnection($e);
        }
    }

    private static function connectMySQL(
        string $host,
        ?string $port,
        string $name,
        string $user,
        string $pass,
        ?string $char
    ): PDO {
        $port = $port ?: '3306';
        
        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            if ($char !== 'ISO') {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
            }

            return new PDO(
                "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                $options
            );
        } catch (PDOException $e) {
            self::errorConnection($e);
        }
    }

    private static function connectSQLite(string $name, ?string $fkey): PDO
    {
        try {
            $conn = new PDO("sqlite:{$name}");

            if (is_null($fkey) || $fkey === '1') {
                $conn->query('PRAGMA foreign_keys = ON');
            }

            return $conn;
        } catch (PDOException $e) {
            self::errorConnection($e);
        }
    }

    private static function connectFirebird(
        string $host,
        ?string $port,
        string $name,
        string $user,
        string $pass,
        ?string $char
    ): PDO {
        $dbString = empty($port) ? "{$host}:{$name}" : "{$host}/{$port}:{$name}";
        $charset = $char ? ";charset={$char}" : '';

        try {
            return new PDO("firebird:dbname={$dbString}{$charset}", $user, $pass);
        } catch (PDOException $e) {
            self::errorConnection($e);
        }
    }

    private static function connectOracle(
        string $host,
        ?string $port,
        string $name,
        string $user,
        string $pass,
        ?string $char,
        array $db
    ): PDO {
        $port = $port ?: '1521';
        $charset = $char ? ";charset={$char}" : '';
        $tns = $db['tns'] ?? null;

        try {
            $dsn = $tns 
                ? "oci:dbname={$tns}{$charset}"
                : "oci:dbname={$host}:{$port}/{$name}{$charset}";

            $conn = new PDO($dsn, $user, $pass);

            if (isset($db['date'])) {
                $conn->query("ALTER SESSION SET NLS_DATE_FORMAT = '{$db['date']}'");
            }
            if (isset($db['time'])) {
                $conn->query("ALTER SESSION SET NLS_TIMESTAMP_FORMAT = '{$db['time']}'");
            }
            if (isset($db['nsep'])) {
                $conn->query("ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '{$db['nsep']}'");
            }

            return $conn;
        } catch (PDOException $e) {
            self::errorConnection($e);
        }
    }

    private static function connectMSSQL(
        string $host,
        ?string $port,
        string $name,
        string $user,
        string $pass,
        ?string $char
    ): PDO {
        try {
            if (PHP_OS === 'WIN') {
                $dsn = $port 
                    ? "sqlsrv:Server={$host},{$port};Database={$name}"
                    : "sqlsrv:Server={$host};Database={$name}";
            } else {
                $charset = $char ? ";charset={$char}" : '';
                $dsn = $port
                    ? "dblib:host={$host}:{$port};dbname={$name}{$charset}"
                    : "dblib:host={$host};dbname={$name}{$charset}";
            }

            return new PDO($dsn, $user, $pass);
        } catch (PDOException $e) {
            self::errorConnection($e);
        }
    }

    private static function connectDBLib(
        string $host,
        ?string $port,
        string $name,
        string $user,
        string $pass,
        ?string $char
    ): PDO {
        $charset = $char ? ";charset={$char}" : '';

        try {
            $dsn = $port
                ? "dblib:host={$host}:{$port};dbname={$name}{$charset}"
                : "dblib:host={$host};dbname={$name}{$charset}";

            return new PDO($dsn, $user, $pass);
        } catch (PDOException $e) {
            self::errorConnection($e);
        }
    }

    private static function connectSQLServer(
        string $host,
        ?string $port,
        string $name,
        string $user,
        string $pass
    ): PDO {
        try {
            $dsn = $port
                ? "sqlsrv:Server={$host},{$port};Database={$name}"
                : "sqlsrv:Server={$host};Database={$name}";

            return new PDO($dsn, $user, $pass);
        } catch (PDOException $e) {
            self::errorConnection($e);
        }
    }

    protected function validateIdentifier(string $identifier): string
    {
        // Permite aliases: "USUARIOS US" ou "USUARIOS AS US"
        if (preg_match('/^([a-zA-Z0-9_]+)\s+(AS\s+)?([a-zA-Z0-9_]+)$/i', $identifier)) {
            return $identifier;
        }
        
        // Permite coluna com tabela: "US.ID" ou apenas "ID"
        if (preg_match('/^([a-zA-Z0-9_]+\.)?[a-zA-Z0-9_*]+$/', $identifier)) {
            return $identifier;
        }
        
        throw new Exception("Identificador inválido: {$identifier}");
    }

    protected function formatTableName(string $table): string
    {
        // Se tem alias: "USUARIOS US" ou "USUARIOS AS US"
        if (preg_match('/^([a-zA-Z0-9_]+)\s+(AS\s+)?([a-zA-Z0-9_]+)$/i', $table, $matches)) {
            $tableName = $matches[1];
            $alias = $matches[3];
            // SEMPRE usar AS explícito
            return "`{$tableName}` AS {$alias}";
        }
        
        // Tabela simples
        return "`{$table}`";
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
