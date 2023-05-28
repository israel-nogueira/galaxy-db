<?php
    declare(strict_types = 1);
    namespace IsraelNogueira\galaxyDB;
	use PDO;
	use RuntimeException;
	use PDOException;

    trait generalBase{

        /*
        |--------------------------------------------------------------------------
        |		GENERAL BASE
        |--------------------------------------------------------------------------
        |   Retorna dados gerais da nossa base
        |--------------------------------------------------------------------------
        | 
        |  getDB_Tables:	RETORNA APENAS AS TABELAS
        |  getDB_Data:  	RETORNA APENAS CONTEUDO
        |  getDB_Columns:  RETORNA COLUNAS DE UMA TABELA
        |  Verify:  		VERIFICA SE UMA TABELA OU COLUNA EXISTE
        |  
        |--------------------------------------------------------------------------
        */
		public function getDB_Tables($tables='*')
		{
			$return = '';			
			$this->connection->exec("SET NAMES 'utf8'");
			if($tables == '*'){
				$tables = array();
				$this->stmt = $this->connection->query('SHOW TABLES');
				while($row = $this->stmt->fetch(PDO::FETCH_NUM)){
					$tables[] = $row[0];
				}
			} else {
				$tables = is_array($tables) ? $tables : explode(',', $tables);
			}
			foreach($tables as $table){
				$this->stmt = $this->connection->query('SELECT * FROM ' . $table);
				$num_fields = $this->stmt->columnCount();
				$num_rows = $this->stmt->rowCount();
				$this->stmt2 = $this->connection->query('SHOW CREATE TABLE ' . $table);
				$row2 = $this->stmt2->fetch(PDO::FETCH_NUM);
				$return .= "\n\n" . $row2[1] . ";\n\n";
				$counter = 1;
				$return .= "\n\n\n";
			}
			return $return;
		}

		public function getDB_Data($tables='*')
		{
			$return = '';
			// Configurações do dump
			$tables = '*'; // Dump de todas as tabelas
			$ignore = array(); // Tabelas a serem ignoradas no dump

			// Inicia o dump
			$dump = "";
			try {
				// Desabilita verificações de chave estrangeira
				$this->connection->exec("SET FOREIGN_KEY_CHECKS=0");

				// Seleciona as tabelas para o dump
				if ($tables == '*') {
					$tables = array();
					$this->stmt = $this->connection->query("SHOW TABLES");
					while ($row = $this->stmt->fetch(PDO::FETCH_NUM)) {
						$tables[] = $row[0];
					}
				} else {
					$tables = is_array($tables) ? $tables : explode(',', $tables);
				}

				// Realiza o dump de cada tabela selecionada
				foreach ($tables as $table) {
					if (in_array($table, $ignore)) {
						continue;
					}

					// Cria o comando INSERT INTO
					$dump .= "INSERT INTO `{$table}` VALUES ";

					// Seleciona os registros da tabela
					$this->stmt = $this->connection->query("SELECT * FROM `{$table}`");
					$rows = $this->stmt->fetchAll(PDO::FETCH_ASSOC);

					// Adiciona os valores na variável $dump
					$values = array();
					foreach ($rows as $row) {
						foreach ($row as $key => $value) {
							if (!is_null($value)) {
								$row[$key] = $this->connection->quote($value);
							}else{
								$row[$key] = 'NULL';
							}

						}
						$values[] = '(' . implode(',', $row) . ')';
					}
					$dump .= implode(',', $values) . ";\n\n";
				}

				// Habilita verificações de chave estrangeira
				$this->connection->exec("SET FOREIGN_KEY_CHECKS=1");

				return $dump;
			} catch (PDOException $e) {
				die("Erro ao gerar o dump: " . $e->getMessage());
			}
		}

		public function showDBColumns($table){
			$query =  'SHOW COLUMNS FROM ' . $table;
			$result = $this->connection->query($query);
			$tables = $result->fetchAll(PDO::FETCH_COLUMN);
			return $tables;
		}

		public function showProcedures(){
			$query =  'SHOW PROCEDURE STATUS WHERE Db = "'.getEnv('DB_DATABASE').'" ';
			$result = $this->connection->query($query);
			$tables = $result->fetchAll(PDO::PARAM_STR);
			return $tables;
		}


		public function getFileLog(){
			$query =  'SHOW VARIABLES LIKE "%general_log%";';
			$result = $this->connection->query($query);
			$tables = $result->fetchAll(PDO::PARAM_STR);
			$_result=[];
			foreach ($tables as  $value) $_result[$value['Variable_name']]=$value['Value'];
			return $_result;
		}

		public function historyDB(){
			/*
			|--------------------------------------------------------------------
			|	PRIMEIRO TIRAMOS TODO LIXO DO LOG
			|--------------------------------------------------------------------
			*/
			$this->cleanLogFilePhpMyAdmin();

			/*
			|--------------------------------------------------------------------
			|	AGORA SIM PUXAMOS
			|--------------------------------------------------------------------
			*/

			$_RESULT	= $this->getFileLog();
			if($_RESULT['general_log']=='OFF')	return json_encode([]);
			$logLines	= file_get_contents($_RESULT['general_log_file']);
			$logLines	= explode("\n",$logLines);
			$_LOG		= [];
			$_LOGMAP	= [];
			$RAW 		= [];
			foreach ($logLines as $line) {
				$parts		=	explode('Query', $line);
				$_QUERY		=	trim($parts[1]??'');
				$_TYPE		=	trim($parts[0]??'');
				$_CONFIG	=	explode(' ',$_TYPE);
				if(count($_CONFIG)==3 && $_CONFIG[1]=='Init'){
					$_CONFIG[2]				=	str_replace('DB	','',$_CONFIG[2]);
					$id						=	intVal($_CONFIG[0]);
					$_LOG[$_CONFIG[2]]		=	[];
					$RAW[$_CONFIG[2]]		=	[];
					$_LOGMAP[$_CONFIG[2]][] =	$id;
				}
			}

			
			foreach ($logLines as $line) {
				$parts		=	explode('Query', $line);
				$_QUERY		=	trim($parts[1]??'');
				$_TYPE		=	trim($parts[0]??'');
				$_CONFIG	=	explode(' ',$_TYPE);
				
				$phpMyAdmin1 = strpos($_QUERY, '`phpmyadmin`') ==FALSE;
				if(count($_CONFIG)==1 && $phpMyAdmin1){
							
					foreach (array_keys($_LOG) as $BASENAME) {
						if(in_array($_CONFIG[0],$_LOGMAP[$BASENAME]) ){
							if (

									(strpos($_QUERY, 'CREATE TABLE') !==FALSE && strpos($_QUERY, 'CREATE TABLE')==0)
								||	(strpos($_QUERY, 'ALTER TABLE') !==FALSE && strpos($_QUERY, 'ALTER TABLE')==0)
								||	(strpos($_QUERY, 'DROP TABLE') !==FALSE && strpos($_QUERY, 'DROP TABLE')==0)

								||	(strpos($_QUERY, 'CREATE FUNCTION') !==FALSE && strpos($_QUERY, 'CREATE FUNCTION')==0)
								||	(strpos($_QUERY, 'ALTER FUNCTION') !==FALSE && strpos($_QUERY, 'ALTER FUNCTION')==0)
								||	(strpos($_QUERY, 'DROP FUNCTION') !==FALSE && strpos($_QUERY, 'DROP FUNCTION')==0)

								||	(strpos($_QUERY, 'CREATE PROCEDURE') !==FALSE && strpos($_QUERY, 'CREATE PROCEDURE')==0)
								||	(strpos($_QUERY, 'ALTER PROCEDURE') !==FALSE && strpos($_QUERY, 'ALTER PROCEDURE')==0)
								||	(strpos($_QUERY, 'DROP PROCEDURE') !==FALSE && strpos($_QUERY, 'DROP PROCEDURE')==0)

								||	(strpos($_QUERY, 'CREATE TRIGGER') !==FALSE && strpos($_QUERY, 'CREATE TRIGGER')==0)
								||	(strpos($_QUERY, 'ALTER TRIGGER') !==FALSE && strpos($_QUERY, 'ALTER TRIGGER')==0)
								||	(strpos($_QUERY, 'DROP TRIGGER') !==FALSE && strpos($_QUERY, 'DROP TRIGGER')==0)

								||	(strpos($_QUERY, 'CREATE EVENT') !==FALSE && strpos($_QUERY, 'CREATE EVENT')==0)
								||	(strpos($_QUERY, 'ALTER EVENT') !==FALSE && strpos($_QUERY, 'ALTER EVENT')==0)
								||	(strpos($_QUERY, 'DROP EVENT') !==FALSE && strpos($_QUERY, 'DROP EVENT')==0)

								||	(strpos($_QUERY, 'CREATE DEFINER') !==FALSE && strpos($_QUERY, 'CREATE DEFINER')==0)
								||	(strpos($_QUERY, 'ALTER DEFINER') !==FALSE && strpos($_QUERY, 'ALTER DEFINER')==0)
								||	(strpos($_QUERY, 'DROP DEFINER') !==FALSE && strpos($_QUERY, 'DROP DEFINER')==0)

								/*
								|--------------------------------------------------------------------
								|	REGISTROS DE UPDATE, INSERT E DELETE
								|--------------------------------------------------------------------
								|
								|	Comentei pois isso pesaria demais ter o historico
								|	Mas quem quiser, pode descomentar
								|
								*/

								//--------------------------------------------------------------------
								// ||	(strpos($_QUERY, 'UPDATE') !==FALSE && strpos($_QUERY, 'UPDATE')==0)
								// ||	(strpos($_QUERY, 'INSERT') !==FALSE && strpos($_QUERY, 'INSERT')==0)
								// ||	(strpos($_QUERY, 'DELETE') !==FALSE && strpos($_QUERY, 'DELETE')==0)
								//--------------------------------------------------------------------
							) {
								$_LOG[$BASENAME][]			= $_QUERY;
								$RAW[$BASENAME][]		= $line;
							}
						}
					}
				}
			}
			// 
			if(isset($RAW[getEnv('DB_DATABASE')])){
				return json_encode([$RAW[getEnv('DB_DATABASE')], $_LOG[getEnv('DB_DATABASE')]]);
			}else{
				return json_encode([[],[]]);
			}
			
		}

		public function cleanLogFilePhpMyAdmin()
		{
			$_RESULT = $this->getFileLog();
			$logContent = file_get_contents($_RESULT['general_log_file']);
			$keywords = array(
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
			);
			$lines = explode("\n", $logContent);
			$novalinha = [];
			$ok = 0;
			foreach ($lines as $line) {
				$ok = 0;
				foreach ($keywords as $proibido) {
					if (stripos($line, $proibido) !== false) $ok = 1;
				}
				if($ok==0) $novalinha[]=$line;
			}

			file_put_contents($_RESULT['general_log_file'], implode("\n", $novalinha));

		}


		public function setHistorySQLfile(){

			$timestamp	= time();
			$date		= date('d-m-Y-H-i-s', $timestamp);
			$_FILENAME	= getEnv('DB_DATABASE') . '_' . $date . '.sql';
			$_RESULT	= $this->getFileLog();
			$_ORIGINAL	= file_get_contents($_RESULT['general_log_file']);

			
			if($_RESULT['general_log']=='OFF')	return json_encode([]);
			$_HISTORY	= json_decode($this->historyDB(),true);

			if(count($_HISTORY[0])>0){
				$GALAXY_FOLDER = realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..').DIRECTORY_SEPARATOR.'galaxyDB'.DIRECTORY_SEPARATOR;
				if(!file_exists($GALAXY_FOLDER)) mkdir($GALAXY_FOLDER, 0755, true);
				$_ORIGINAL	= file_get_contents($_RESULT['general_log_file']);
				$_TRATADO	= str_replace($_HISTORY[0],'			Registro em: '.$_FILENAME,$_ORIGINAL);
				file_put_contents($_RESULT['general_log_file'],$_TRATADO);
				file_put_contents($GALAXY_FOLDER.$_FILENAME,implode(';'.PHP_EOL,$_HISTORY[1]).';');
			}

		}
		public function showDBTables(){
			$tables = array();
			$query = 'SHOW TABLES';
			$result = $this->connection->query($query);
			$tables = $result->fetchAll(PDO::FETCH_COLUMN);
			return $tables;
		}

		public function verify(){		
			if (!is_null($this->tableClass) && is_null($this->setcolum)){
				$query = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :databaseName AND table_name = :tableName";
				$this->stmt = $this->connection->prepare($query);
				$this->stmt->execute([
					'databaseName'	=>	$this->connection->query('SELECT DATABASE()')->fetchColumn(),
					'tableName'		=>	$this->tableClass
				]);
				return $this->stmt->fetchColumn() > 0;

			}elseif (!is_null($this->tableClass) && !is_null($this->setcolum)){
				$this->stmt = $this->connection->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = :tableName AND COLUMN_NAME = :columnName");
				$this->stmt->bindParam(":tableName", $this->tableClass, PDO::PARAM_STR);
				$this->stmt->bindParam(":columnName", $this->setcolum[0], PDO::PARAM_STR);
				$this->stmt->execute();
				return $this->stmt->rowCount() > 0;
			}
		}

        

    }
