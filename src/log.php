<?php
    declare(strict_types = 1);
    namespace IsraelNogueira\galaxyDB;
	use PDO;
	use RuntimeException;
	use PDOException;

    trait log{

		/*
		|--------------------------------------------------------------------
		|	VERIFICAMOS SE ESSE USUÁRIO TEM PERMISSÕES
		|--------------------------------------------------------------------
		*/
		public function checkGeneralLogPermissions(){
			$stmt = $this->connection->query("SHOW GRANTS");
			$grants = $stmt->fetchAll(PDO::FETCH_COLUMN);
			$hasGlobalPrivilege = false;
			foreach ($grants as $grant) {
				if (strpos($grant, 'ALL PRIVILEGES') !== false) {
					$hasGlobalPrivilege = true;
					break;
				}
			}
			if ($hasGlobalPrivilege)  return true;
			$stmt = $this->connection->query("SHOW DATABASES");
			$databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
			$currentDatabase = $pdo->query("SELECT DATABASE()")->fetchColumn();
			$hasDatabasePrivilege = false;
			foreach ($databases as $database) {
				if ($database === $currentDatabase) {
					$stmt = $pdo->query("SHOW GRANTS FOR CURRENT_USER()");
					$grants = $stmt->fetchAll(PDO::FETCH_COLUMN);
					
					foreach ($grants as $grant) {
						if (strpos($grant, 'ALL PRIVILEGES') !== false) {
							$hasDatabasePrivilege = true;
							break;
						}
					}
					break;
				}
			}
			return $hasDatabasePrivilege;
		}

		/*
		|--------------------------------------------------------------------
		|	HABILITAMOS E CRIAMOS O ARQUIVO DE LOG DA BASE DE DADOS
		|--------------------------------------------------------------------
		*/
		public function enableGeneralLog(){
			if (!$this->checkGeneralLogPermissions()) {
				throw new Exception('Usuário não tem permissões suficientes para configurar o general_log e o general_log_file.');
			}
			$path = str_replace('\\', '/', $this->logFile);
			$this->connection->exec("SET GLOBAL general_log = 'ON'");
			$this->connection->exec('SET GLOBAL general_log_file="'.$path.'"');
			file_put_contents($this->logFile,'',FILE_APPEND);
			return true;
		}

		/*
		|--------------------------------------------------------------------
		|	RETORNA O PATH DO ARQUIVO DE LOG
		|--------------------------------------------------------------------
		*/
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
			|	VERIFICAMOS SE ESTA ATIVO OS LOGS DE ERRO
			|--------------------------------------------------------------------
			|
			|	Caso esteja OFF e tiver permissões, tentaremos habilitar o log
			|	Caso esteja Off, não tem registros. Então não tem o que retornar
			|	Nesse caso retorna vazio
			|
			*/

			$_RESULT	= $this->getFileLog();
			if($_RESULT['general_log']=='OFF')	{
				$this->enableGeneralLog();
				return json_encode([]);
			}
	
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

							) {
								$_LOG[$BASENAME][]		= $_QUERY;
								$RAW[$BASENAME][]		= $line;
							}

								/*
								|--------------------------------------------------------------------
								|	REGISTROS DE UPDATE, INSERT E DELETE
								|--------------------------------------------------------------------
								|
								|	Comentei pois isso pesaria demais ter o historico
								|	Mas quem quiser, pode descomentar
								|
								|--------------------------------------------------------------------
                                
								if(
									(strpos($_QUERY, 'UPDATE') !==FALSE && strpos($_QUERY, 'UPDATE')==0) ||
									(strpos($_QUERY, 'INSERT') !==FALSE && strpos($_QUERY, 'INSERT')==0) ||
									(strpos($_QUERY, 'DELETE') !==FALSE && strpos($_QUERY, 'DELETE')==0)
                                    ){
										$_LOG[$BASENAME][]		= $_QUERY;
										$RAW[$BASENAME][]		= $line;
								}
                                
                                */
						}
					}
				}
			}
			if(isset($RAW[getEnv('DB_DATABASE')])){
				return json_encode([$RAW[getEnv('DB_DATABASE')], $_LOG[getEnv('DB_DATABASE')]]);
			}else{
				return json_encode([[],[]]);
			}
			
		}
		/*
		|--------------------------------------------------------------------
		|	LIMPEZA DO ARQUIVO DE LOG
		|--------------------------------------------------------------------
		|
		|	NORMALMENTE os servidores utilizam o phpMyAdmin;
		|	Isso gera muita poluição, então nessa função
		|	resolvemos isso, a fim de liberar espaço em disco
		|	e facilitar a leitura
		|
		*/
		public function cleanLogFilePhpMyAdmin(){

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

		/*
		|--------------------------------------------------------------------
		|	GRAVA O ARQUIVO COM O LOG ATUALIZADO DA BASE
		|--------------------------------------------------------------------
		*/
		public function setHistorySQLfile(){
            try {
                $timestamp	= time();
                $date		= date('d-m-Y-H-i-s', $timestamp);
                $_FILENAME	= getEnv('DB_DATABASE') . '_' . $date . '.sql';
                $_RESULT	= $this->getFileLog();
                $_ORIGINAL	= file_get_contents($_RESULT['general_log_file']);
                $_HISTORY	= json_decode($this->historyDB(),true);
                if(count($_HISTORY[0])>0){
					$GALAXY_FOLDER = realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..').DIRECTORY_SEPARATOR.'galaxyDB'.DIRECTORY_SEPARATOR;
					@mkdir($GALAXY_FOLDER,0755,true);
                    if(!file_exists($GALAXY_FOLDER)) mkdir($GALAXY_FOLDER, 0755, true);
                    $_ORIGINAL	= file_get_contents($_RESULT['general_log_file']);
                    $_TRATADO	= str_replace($_HISTORY[0],'			Registro em: '.$_FILENAME,$_ORIGINAL);
                    file_put_contents($_RESULT['general_log_file'],$_TRATADO);
                    file_put_contents($GALAXY_FOLDER.$_FILENAME,implode(';'.PHP_EOL,$_HISTORY[1]).';');
					return ['status'=>true,'message'=>$GALAXY_FOLDER.$_FILENAME];
                }

            } catch (\Throwable $th) {
                return ['status'=>false,'message'=>$th];
            }
		}


        

    }
