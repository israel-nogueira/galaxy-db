<?php
declare(strict_types = 1);
namespace IsraelNogueira\MysqlOrm;

define('DB_HOST', 		'localhost');
define('DB_PORT', 		'3306');
define('DB_DATABASE', 	'MY_DATABASE');
define('DB_TYPE', 		'mysql');
define('DB_USERNAME', 	'root');
define('DB_PASSWORD',   '');
define('DB_CHAR',   	'');
define('DB_FLOW',   	'');
define('DB_FKEY',   	'');

trait connection{

    /**
     * método open()
     * recebe o nome do banco de dados e instancia o objecto PDO correspondente
     */
    public static function connect($db=[]){
        $conn = null;
        /*
        |----------------------------------------------------------------------------------------------------
        | Config
        |----------------------------------------------------------------------------------------------------
        |
        | busca o array de conexões
        |
        */
        $user = isset($db['user']) ? $db['user'] : NULL;
        $pass = isset($db['pass']) ? $db['pass'] : NULL;
        $name = isset($db['name']) ? $db['name'] : NULL;
        $host = isset($db['host']) ? $db['host'] : NULL;
        $type = isset($db['type']) ? $db['type'] : NULL;
        $port = isset($db['port']) ? $db['port'] : NULL;
        $char = isset($db['char']) ? $db['char'] : NULL;
        $flow = isset($db['flow']) ? $db['flow'] : NULL;
        $fkey = isset($db['fkey']) ? $db['fkey'] : NULL;
        $type = strtolower($type);
        
        // descobre qual o tipo (driver) de banco de dados a ser utilizado
        switch ($type)
        {
            /*
            |----------------------------------------------------------------------------------------------------
            | pgsql
            |----------------------------------------------------------------------------------------------------
            |
            | abre conexão com banco de dados do tipo Postgresql
            |
            */
            case 'pgsql':
                $port = $port ? $port : '5432';
                $conn = new \PDO("pgsql:dbname={$name};user={$user}; password={$pass};host=$host;port={$port}");
                if(!empty($char))
                {
                    $conn->exec("SET CLIENT_ENCODING TO '{$char}';");
                }
                break;
            /*
            |----------------------------------------------------------------------------------------------------
            | mysql
            |----------------------------------------------------------------------------------------------------
            |
            | Abre conexão com banco de dados do tipo MySQL e MariaDB
            |
            */
            case 'mysqli':
            case 'mysql':
                $port = $port ? $port : '3306';
                if ($char == 'ISO')
                {
                    $conn = new \PDO("mysql:host={$host};port={$port};dbname={$name}", $user, $pass);
                }
                else
                {
                    $conn = new \PDO("mysql:host={$host};port={$port};dbname={$name}", $user, $pass, array(\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
                }
                break;
            /*
            |----------------------------------------------------------------------------------------------------
            | sqlite
            |----------------------------------------------------------------------------------------------------
            |
            | Abre conexão com banco de dados do tipo SQLLite
            |
            */
            case 'sqlite':
                $conn = new \PDO("sqlite:{$name}");
                if (is_null($fkey) OR $fkey == '1')
                {
                    $conn->query('PRAGMA foreign_keys = ON'); // referential integrity must be enabled
                }
                break;
            /*
            |----------------------------------------------------------------------------------------------------
            | ibase & fbird
            |----------------------------------------------------------------------------------------------------
            |
            | Abre conexão com os bancos de dados do tipo IBASE e Firebird
            |
            */
            case 'ibase':
            case 'fbird':
                $db_string = empty($port) ? "{$host}:{$name}" : "{$host}/{$port}:{$name}";
                $charset = $char ? ";charset={$char}" : '';
                $conn = new \PDO("firebird:dbname={$db_string}{$charset}", $user, $pass);
                break;
            /*
            |----------------------------------------------------------------------------------------------------
            | oracle
            |----------------------------------------------------------------------------------------------------
            |
            | Abre conexão com o banco de dado oracle
            |
            */
            case 'oracle':
                $port    = $port ? $port : '1521';
                $charset = $char ? ";charset={$char}" : '';
                $tns     = isset($db['tns']) ? $db['tns'] : NULL;
                
                if ($tns)
                {
                    $conn = new \PDO("oci:dbname={$tns}{$charset}", $user, $pass);
                }
                else
                {
                    $conn = new \PDO("oci:dbname={$host}:{$port}/{$name}{$charset}", $user, $pass);
                }
                
                if (isset($db['date']))
                {
                    $date = $db['date'];
                    $conn->query("ALTER SESSION SET NLS_DATE_FORMAT = '{$date}'");
                }
                if (isset($db['time']))
                {
                    $time = $db['time'];
                    $conn->query("ALTER SESSION SET NLS_TIMESTAMP_FORMAT = '{$time}'");
                }
                if (isset($db['nsep']))
                {
                    $nsep = $db['nsep'];
                    $conn->query("ALTER SESSION SET NLS_NUMERIC_CHARACTERS = '{$nsep}'");
                }
                break;
            /*
            |----------------------------------------------------------------------------------------------------
            | mssql
            |----------------------------------------------------------------------------------------------------
            |
            | Abre conexão com o banco de dados Microsoft SQL para servidores Windows
            |
            */
            case 'mssql':
                if (PHP_OS == 'WIN')
                {
                    if ($port)
                    {
                        $conn = new \PDO("sqlsrv:Server={$host},{$port};Database={$name}", $user, $pass);
                    }
                    else
                    {
                        $conn = new \PDO("sqlsrv:Server={$host};Database={$name}", $user, $pass);
                    }
                }
                else
                {
                    $charset = $char ? ";charset={$char}" : '';
                    
                    if ($port)
                    {
                        $conn = new \PDO("dblib:host={$host}:{$port};dbname={$name}{$charset}", $user, $pass);
                    }
                    else
                    {
                        $conn = new \PDO("dblib:host={$host};dbname={$name}{$charset}", $user, $pass);
                    }
                }
                break;
            /*
            |----------------------------------------------------------------------------------------------------
            | dblib
            |----------------------------------------------------------------------------------------------------
            |
            | Abre conexão com banco de dados dblib
            |
            */
            case 'dblib':
                $charset = $char ? ";charset={$char}" : '';
                
                if ($port)
                {
                    $conn = new \PDO("dblib:host={$host}:{$port};dbname={$name}{$charset}", $user, $pass);
                }
                else
                {
                    $conn = new \PDO("dblib:host={$host};dbname={$name}{$charset}", $user, $pass);
                }
                break;
            /*
            |----------------------------------------------------------------------------------------------------
            | sqlsrv
            |----------------------------------------------------------------------------------------------------
            |
            | Abre conexão com o banco de dados SQL Server
            |
            */
            case 'sqlsrv':
                if ($port)
                {
                    $conn = new \PDO("sqlsrv:Server={$host},{$port};Database={$name}", $user, $pass);
                }
                else
                {
                    $conn = new \PDO("sqlsrv:Server={$host};Database={$name}", $user, $pass);
                }
                break;
            /*
            |----------------------------------------------------------------------------------------------------
            | Default
            |----------------------------------------------------------------------------------------------------
            |
            | Caso o driver não exista, então cai na mensagem de erro 
            |
            */
            default:
                throw new \Exception('Driver not Found: ' . $type);
                break;
        }

        // define para que o PDO lance exceções na ocorrência de erros
        $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        if ($flow == '1')
        {
            $conn->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        }

        // retorna o objeto instanciado
        return $conn;
    }

}
