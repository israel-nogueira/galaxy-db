<?php
    declare(strict_types = 1);

    namespace IsraelNogueira\galaxyDB;
    use PDOException;
    use Exception;

    trait connection{

        /**
         * método open()
         * recebe o nome do banco de dados e instancia o objecto PDO correspondente
         */
        public static function errorConnection($error){
            // die(\app\system\lib\system::ajaxReturn($error->getMessage(),0));
            // throw new Exception($error->getMessage(), 1);
            
        }
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
            
            $type = (!getEnv('DB_TYPE')     || is_null(getEnv('DB_TYPE')))     ? '' : getEnv('DB_TYPE');
            $user = (!getEnv('DB_USERNAME') || is_null(getEnv('DB_USERNAME'))) ? '' : getEnv('DB_USERNAME');
            $pass = (!getEnv('DB_PASSWORD') || is_null(getEnv('DB_PASSWORD'))) ? '' : getEnv('DB_PASSWORD');
            $name = (!getEnv('DB_DATABASE') || is_null(getEnv('DB_DATABASE'))) ? '' : getEnv('DB_DATABASE');
            $host = (!getEnv('DB_HOST')     || is_null(getEnv('DB_HOST')))     ? '' : getEnv('DB_HOST');
            $port = (!getEnv('DB_PORT')     || is_null(getEnv('DB_PORT')))     ? '' : getEnv('DB_PORT');
            $char = (!getEnv('DB_CHAR')     || is_null(getEnv('DB_CHAR')))     ? '' : getEnv('DB_CHAR');
            $flow = (!getEnv('DB_FLOW')     || is_null(getEnv('DB_FLOW')))     ? '' : getEnv('DB_FLOW');
            $fkey = (!getEnv('DB_FKEY')     || is_null(getEnv('DB_FKEY')))     ? '' : getEnv('DB_FKEY');


            // descobre qual o tipo (driver) de banco de dados a ser utilizado
            switch ($type){
                /*
                |----------------------------------------------------------------------------------------------------
                | pgsql
                |----------------------------------------------------------------------------------------------------
                |
                | abre conexão com banco de dados do tipo Postgresql
                |
                */
                case 'pgsql':
                    $port = $port ? $port : '3306';
                    try{
                        $conn = new \PDO("pgsql:dbname={$name};user={$user}; password={$pass};host=$host;port={$port}");
                    }catch(PDOException $e){
                        self::errorConnection($e);
                    }
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
                        try{
                            $conn = new \PDO("mysql:host={$host};port={$port};dbname={$name}", $user, $pass);
                        }catch(PDOException $e){
                            self::errorConnection($e);
                        }
                    }
                    else
                    {
                        try{
                            $conn = new \PDO("mysql:host={$host};port={$port};dbname={$name}", $user, $pass, array(\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"));
                        }catch(PDOException $e){
                            self::errorConnection($e);
                        }
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
                    try{
                        $conn = new \PDO("sqlite:{$name}");
                    }catch(PDOException $e){
                        self::errorConnection($e);
                    }
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
                    try{
                        $conn = new \PDO("firebird:dbname={$db_string}{$charset}", $user, $pass);
                    }catch(PDOException $e){
                        self::errorConnection($e);
                    }
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
                        try{
                            $conn = new \PDO("oci:dbname={$tns}{$charset}", $user, $pass);
                        }catch(PDOException $e){
                            self::errorConnection($e);
                        }
                    }
                    else
                    {
                        try{
                            $conn = new \PDO("oci:dbname={$host}:{$port}/{$name}{$charset}", $user, $pass);
                        }catch(PDOException $e){
                            self::errorConnection($e);
                        }
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
                            try{
                                $conn = new \PDO("sqlsrv:Server={$host},{$port};Database={$name}", $user, $pass);
                            }catch(PDOException $e){
                                self::errorConnection($e);
                            }
                        }
                        else
                        {
                            try{
                                $conn = new \PDO("sqlsrv:Server={$host};Database={$name}", $user, $pass);
                            }catch(PDOException $e){
                                self::errorConnection($e);
                            }
                        }
                    }
                    else
                    {
                        $charset = $char ? ";charset={$char}" : '';
                        
                        if ($port)
                        {
                            try{
                                $conn = new \PDO("dblib:host={$host}:{$port};dbname={$name}{$charset}", $user, $pass);
                            }catch(PDOException $e){
                                self::errorConnection($e);
                            }
                        }
                        else
                        {
                            try{
                                $conn = new \PDO("dblib:host={$host};dbname={$name}{$charset}", $user, $pass);
                            }catch(PDOException $e){
                                self::errorConnection($e);
                            }
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
                        try{
                            $conn = new \PDO("dblib:host={$host}:{$port};dbname={$name}{$charset}", $user, $pass);
                        }catch(PDOException $e){
                            self::errorConnection($e);
                        }
                    }
                    else
                    {
                        try{
                            $conn = new \PDO("dblib:host={$host};dbname={$name}{$charset}", $user, $pass);
                        }catch(PDOException $e){
                            self::errorConnection($e);
                        }
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
                        try{
                            $conn = new \PDO("sqlsrv:Server={$host},{$port};Database={$name}", $user, $pass);
                        }catch(PDOException $e){
                            self::errorConnection($e);
                        }
                    }
                    else
                    {
                        try{
                            $conn = new \PDO("sqlsrv:Server={$host};Database={$name}", $user, $pass);
                        }catch(PDOException $e){
                            self::errorConnection($e);
                        }
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
