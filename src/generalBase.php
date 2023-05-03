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

		public function showDBTables()
		{
			$tables = array();
			$query = 'SHOW TABLES';
			$result = $this->connection->query($query);
			$tables = $result->fetchAll(PDO::FETCH_COLUMN);
			return $tables;
		}

		public function verify()
		{		
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
