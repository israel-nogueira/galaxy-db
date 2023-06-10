<?php
    declare(strict_types = 1);
    namespace IsraelNogueira\galaxyDB;
	use PDO;
	use RuntimeException;
	use PDOException;

        /*
        |--------------------------------------------------------------------------
        |		GENERAL BASE
        |--------------------------------------------------------------------------
        |   Retorna dados gerais da nossa base
        |--------------------------------------------------------------------------
        | 
        |  enableRAC:		CRIA TRIGGERS DE LOG
        |  getDB_Tables:	RETORNA APENAS AS TABELAS
        |  getDB_Data:  	RETORNA APENAS CONTEUDO
        |  getDB_Columns:   RETORNA COLUNAS DE UMA TABELA
        |  Verify:  		VERIFICA SE UMA TABELA OU COLUNA EXISTE
        |  
        |--------------------------------------------------------------------------
        */


    trait generalBase{
		
        /*
        |--------------------------------------------------------------------------
        |	TRIGGERS DE LOG DE CONTEUDOS 
        |--------------------------------------------------------------------------
        |   Cria pequenos backups das alterações da base
        |--------------------------------------------------------------------------
        */
		public function enableRAC(){ 
			$this->stmt = $this->connection->query('SHOW TABLES');
			$tables = $this->stmt->fetchAll(PDO::FETCH_COLUMN);
			$this->query = [];

			/*
			|----------------------------------------------------
			| TABELA QUE RECEBERÁ TODOS OS UPDATES
			|----------------------------------------------------
			*/
			$this->connection->exec('CREATE TABLE IF NOT EXISTS `GALAXY__RAC` (
										`ID` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
										`TABELA` varchar(200) DEFAULT NULL,
										`ACTION` varchar(100) DEFAULT NULL,
										`QUERY` text DEFAULT NULL,
										`ROLLBACK` text DEFAULT NULL,
										`DATA_HORA` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
									) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');


			// primeiro excluimos todas antes de começar
			$this->disableRAC();

			/*
			|----------------------------------------------------
			| VARREMOS TODAS AS TABELAS E INSERIMOS AS TRIGGERS
			|----------------------------------------------------
			*/
			foreach ($tables as $table) {
				if($table=='GALAXY__RAC'){ continue;}

				/*
				|----------------------------------------------------
				| LISTNER DE UPDATE
				|----------------------------------------------------
				*/
				$triggerUPDATE = 'CREATE TRIGGER `GALAXY___UPDATE_'.$table.'` BEFORE UPDATE ON `'.$table.'` FOR EACH ROW BEGIN';
				$triggerUPDATE .= '    DECLARE old_query VARCHAR(255);';
				$triggerUPDATE .= '    DECLARE new_query VARCHAR(255);';
				$triggerUPDATE .= '    DECLARE array_old TEXT DEFAULT "";';
				$triggerUPDATE .= '    DECLARE array_new TEXT DEFAULT "";';
				$query = "SHOW COLUMNS FROM $table";
				$stmt = $this->connection->query($query);
				$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
				$priKey = '';
				foreach ($columns as $column) {
					if($column['Key']=='PRI'){
						$priKey = $column['Field'];
					}else{
						$triggerUPDATE .= '    IF (IFNULL(OLD.'.$column['Field'].', "") <> IFNULL(NEW.'.$column['Field'].', "")) THEN';
						$triggerUPDATE .= '        SET array_old = TRIM(BOTH "," FROM CONCAT_WS(",", array_old, CONCAT("'.$column['Field'].'=", \'"\', OLD.'.$column['Field'].', \'"\')));';
						$triggerUPDATE .= '        SET array_new = TRIM(BOTH "," FROM CONCAT_WS(",", array_new, CONCAT("'.$column['Field'].'=", \'"\', NEW.'.$column['Field'].', \'"\')));';
						$triggerUPDATE .= '    END IF;';
					 }
				}
				if($priKey!=''){
					$triggerUPDATE .= '    SET old_query = CONCAT(\'UPDATE `'.$table.'` SET \',array_old,\' WHERE `'.$table.'`.`'.$priKey.'`=\', OLD.'.$priKey.');';
					$triggerUPDATE .= '    SET new_query = CONCAT(\'UPDATE `'.$table.'` SET \',array_new,\' WHERE `'.$table.'`.`'.$priKey.'`=\', NEW.'.$priKey.');';
					$triggerUPDATE .= '    INSERT INTO `GALAXY__RAC` (`TABELA`, `ACTION`, `ROLLBACK`, `QUERY`) VALUES (\''.$table.'\', \'UPDATE\', old_query, new_query);';
				}
				$triggerUPDATE .= 'END';

				/*
				|----------------------------------------------------
				| LISTNER DE DELETE
				|----------------------------------------------------
				*/
				$triggerDELETE = 'CREATE TRIGGER `GALAXY___DELETE_'.$table.'` AFTER DELETE ON `'.$table.'` FOR EACH ROW BEGIN';
				$triggerDELETE .= '    DECLARE old_query VARCHAR(255);';
				$triggerDELETE .= '    DECLARE new_query VARCHAR(255);';
				$triggerDELETE .= '    DECLARE array_colum_old TEXT DEFAULT "";';
				$triggerDELETE .= '    DECLARE array_value_old TEXT DEFAULT "";';
				$triggerDELETE .= '    DECLARE array_new TEXT DEFAULT "";';
				$query = "SHOW COLUMNS FROM $table";
				$stmt = $this->connection->query($query);
				$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
				$priKey = '';
				foreach ($columns as $column) {
					if ($column['Key'] == 'PRI') {
						$priKey = $column['Field'];
					} else {
						$triggerDELETE .= '	SET array_colum_old = TRIM(BOTH "," FROM CONCAT_WS(",", array_colum_old, "'.$column['Field'].'"));';
						$triggerDELETE .= "	SET array_value_old = TRIM(BOTH ',' FROM CONCAT_WS(',', array_value_old, CONCAT('\"', OLD.".$column['Field'].", '\"')));";
					}
				}
				if ($priKey != '') {
					$triggerDELETE .= "    SET old_query = CONCAT('INSERT INTO `".$table."` (',array_colum_old,') VALUES (',array_value_old,')');";
					$triggerDELETE .= "    SET new_query = CONCAT('DELETE FROM `".$table."` WHERE `".$priKey."`=', OLD.".$priKey.");";
					$triggerDELETE .= "    INSERT INTO `GALAXY__RAC` (`TABELA`, `ACTION`, `ROLLBACK`, `QUERY`) VALUES ('".$table."', 'DELETE', old_query, new_query);";
				}
				$triggerDELETE .= 'END';	

				/*
				|----------------------------------------------------
				| LISTNER DE INSERT
				|----------------------------------------------------
				*/
				$triggerINSERT = 'CREATE TRIGGER `GALAXY___INSERT_'.$table.'` AFTER INSERT ON `'.$table.'` FOR EACH ROW BEGIN';
				$triggerINSERT .= '    DECLARE old_query VARCHAR(255);';
				$triggerINSERT .= '    DECLARE new_query VARCHAR(255);';
				$triggerINSERT .= '    DECLARE array_colum_new TEXT DEFAULT "";';
				$triggerINSERT .= '    DECLARE array_value_new TEXT DEFAULT "";';
				$triggerINSERT .= '    DECLARE array_old TEXT DEFAULT "";';
				$query = "SHOW COLUMNS FROM $table";
				$stmt = $this->connection->query($query);
				$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
				$priKey = '';
				foreach ($columns as $column) {
					if ($column['Key'] == 'PRI') {
						$priKey = $column['Field'];
					} else {
						$triggerINSERT .= '    SET array_colum_new = TRIM(BOTH "," FROM CONCAT_WS(",", array_colum_new, "'.$column['Field'].'"));';
						$triggerINSERT .= "    SET array_value_new = TRIM(BOTH ',' FROM CONCAT_WS(',', array_value_new, CONCAT('\"', NEW.".$column['Field'].", '\"')));";
					}
				}
				if ($priKey != '') {
					$triggerINSERT .= "    SET old_query = CONCAT('DELETE FROM `".$table."` WHERE `".$priKey."`=', NEW.".$priKey.");";
					$triggerINSERT .= "    SET new_query = CONCAT('INSERT INTO `".$table."` (',array_colum_new,') VALUES (',array_value_new,')');";
					$triggerINSERT .= "    INSERT INTO `GALAXY__RAC` (`TABELA`, `ACTION`, `ROLLBACK`, `QUERY`) VALUES ('".$table."', 'INSERT', old_query, new_query);";
				}
				$triggerINSERT .= 'END';
				$this->connection->exec($triggerDELETE);
				$this->connection->exec($triggerUPDATE);
				$this->connection->exec($triggerINSERT);
			}
		}

		/*
		|----------------------------------------------------
		| EXCLUIMOS AS TRIGGERS DE LOG 
		|----------------------------------------------------
		*/
		public function disableRAC(){ 
			$this->stmt = $this->connection->query('SHOW TABLES');
			foreach ($this->stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
				$this->connection->exec('DROP TRIGGER IF EXISTS `GALAXY___UPDATE_'.$table.'`');
				$this->connection->exec('DROP TRIGGER IF EXISTS `GALAXY___DELETE_'.$table.'`');
				$this->connection->exec('DROP TRIGGER IF EXISTS `GALAXY___INSERT_'.$table.'`');
			}
		}




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
