<?
    namespace IsraelNogueira\galaxyDB;
	use PDO;
	use Exception;
	use RuntimeException;
	use InvalidArgumentException;
	use PDOException;
    
    trait actions{
	/*
	|--------------------------------------------------------------------------
	|	NOVPOS VALORES PARA ARRAYS E OBJETOS
	|--------------------------------------------------------------------------
	|
	|	Ele verifica antes dejá existe um valor, e vai acrescentando um contador até 
	|	não existir um valor que ele possa acrescentar
	|
	|	"newValueArray"  ["item","item_1","item_2","item_3"]
	|	"newKeyArray"  	["item"=>"value","item_1"=>"value","item_2"=>"value","item_3"=>"value"]
	|
	|--------------------------------------------------------------------------
	*/
		public function newValueArray($ARRAY, $PARAM){
				$NEW_PARAM = $PARAM;
				$i = 1;
				verifica_i:
				foreach ($ARRAY as  $SP_OUTS) {
					if (in_array($NEW_PARAM, $SP_OUTS)) {
						$i++;								
						$NEW_PARAM = $PARAM.'_'.$i;
						goto verifica_i;
					} 
				}
				return $NEW_PARAM;
		}

		public function newKeyArray($ARRAY, $SP_NAME){
			$a = 1;
			$NEW_SP_NAME = $SP_NAME;
			verifica_a:
			if(array_key_exists($NEW_SP_NAME,$ARRAY)){
				$a++;	
				$NEW_SP_NAME = $SP_NAME.'_'.$a;
				goto verifica_a;
			}
			return $NEW_SP_NAME;
		}

	/*
	|--------------------------------------------------------------------------
	|	LIMPANDO CONFIGS
	|--------------------------------------------------------------------------
	|
	|	Toda vez que damos um "insert", "prepare_select", "prepare_update"
	|	Ou qualquer execução de query, nós zeramos os parametros para que outras
	|	querys possam ser criadas futuramente.
	|
	|--------------------------------------------------------------------------
	*/

		public function clear(){
			
			$this->_num_rows			=[];
			$this->SP_OUTS				=[];
			$this->SP_OUTPUTS			=[];
			$this->SP_NEW_PARAMS		=[];
			$this->like					= (object) array();
			$this->InsertVars			= array();
			$this->Insert_like			= array();
			$this->Insert_Update		= array();
			$this->stmt					= null;
			$this->setcolum				= null;
			$this->debug				= true;
			$this->decode				= false;
			$this->isEscape				= false;
			$this->rell					= null;
			$this->ignore				= array();
			$this->on_duplicate			= array();
			$this->DISTINCT				= null;
			$this->replace_isso			= array();
			$this->replace_porisso		= array();
			$this->where				= null;
			$this->having				= null;
			$this->_QUERY				= '';
			$this->set_where_not_exist	= null;
			$this->colum				= null;
			$this->setorder				= [];
			$this->order				= null;
			$this->setwhere				= null;
			$this->setHaving			= null;
			$this->limit				= null;
			$this->SP_OUTPUTS			= [];
			$this->SP_NEW_PARAMS		= [];

		}

	/*
	|--------------------------------------------------------------------------
	|	TRANSACTIONS
	|--------------------------------------------------------------------------
	*/
        public function transaction($return='none'){
            $this->transactionFn = true;
            $this->rollbackFn = $return;
        }

        public function rollbackExec($return){
            if($this->rollbackFn!='none'){
                call_user_func_array($this->rollbackFn,array($return));
            }
        }



/*
	|--------------------------------------------------------------------------
	|	STORE PROCEDURES
	|--------------------------------------------------------------------------
	*/
		public function createSP($name,$params,$sql){
			return 'DELIMITER'.PHP_EOL.
					'//'.PHP_EOL.
					'	DROP PROCEDURE IF EXISTS '.$name.PHP_EOL.
					'//'.PHP_EOL.
					'	CREATE PROCEDURE '.$name.'('.implode(',',$params).')'.PHP_EOL.
					'	BEGIN '.PHP_EOL.$sql.PHP_EOL.
					'	END //'.PHP_EOL.
					'	DELIMITER ;';
		}

		public function responseSP($variable = null){
			if ($variable != null) {
				return $this->sp_response[$variable];
			} else {
				return $this->sp_response;
			}
		}

		public function execSP($_ALIAS='RESPONSE', $_RETORNO=null){				
				$this->prepare_sp($_ALIAS,$_RETORNO);
				$this->execQuery();
				$this->clear();
				return $this;	
		}

		public function sp($SP='STOREP', $SP_PARAMS=[]){
			$this->SP[$SP]			= $SP;
			$this->SP_PARAMS[$SP][]	= [...$SP_PARAMS];
		}

		public function prepare_sp(){
			//--------------------------------------------------------------
			// como vamos montar a query, confirma q é uma array
			//--------------------------------------------------------------
			if(is_string($this->query)){$this->query=[];}
			foreach ($this->SP_PARAMS as $SP_NAME => $SP_ARRAY) {			
				foreach ($SP_ARRAY as $key=>$_PARAMS) {
					$outputs	= [];
					$params		= [];
					$NEW_SP_NAME = $this->newKeyArray($this->SP_NEW_PARAMS, $SP_NAME);
					foreach ($_PARAMS as $key2=>$PARAM) {
						if(is_string($PARAM) && substr($PARAM,0,1)=='@'){
							$NEW_PARAM 									= 	$this->newValueArray($this->SP_OUTS, $PARAM);
							$outputs[] 									= 	$NEW_PARAM;
							$this->SP_NEW_PARAMS[$NEW_SP_NAME][$key2]	=	$NEW_PARAM;
							$this->SP_OUTS[$NEW_SP_NAME][]				=	$NEW_PARAM;
							
						}elseif(is_array($PARAM) || is_object($PARAM)){

					 		$this->SP_NEW_PARAMS[$NEW_SP_NAME][$key2] = "'".trim(json_encode($PARAM,JSON_BIGINT_AS_STRING), '[]')."'";

						}elseif(is_numeric($PARAM) || is_int($PARAM) || is_float($PARAM)){

							$this->SP_NEW_PARAMS[$NEW_SP_NAME][$key2] = trim($PARAM);

						}elseif(is_string($PARAM)){

							$this->SP_NEW_PARAMS[$NEW_SP_NAME][$key2] = "'".trim($PARAM)."'";

						}elseif($PARAM==null){

							$this->SP_NEW_PARAMS[$NEW_SP_NAME][$key2] = 'NULL';

						}
					} 
				}
			}
			foreach ($this->SP_PARAMS as $SP_NAME => $SP_ARRAY) {
				foreach ($SP_ARRAY as $key=>$_PARAMS) {
					$NEW_SP_NAME = $this->newKeyArray($this->query, $SP_NAME);				
					$this->query[$NEW_SP_NAME] =  ('CALL ' . $SP_NAME . '(' . implode(',',$this->SP_NEW_PARAMS[$NEW_SP_NAME]).');');
				}
			}
		}

		public function output_sp($SP=null){
			return $this->SP_OUTS[$SP]['result'];
		}

	/*
	|--------------------------------------------------------------------------
	|	VIEWS
	|--------------------------------------------------------------------------
	*/

		public function create_view($name = null){
			if ($name == null) {
				throw new InvalidArgumentException('create_view(NULL), Error:' . __LINE__);
			}
			$this->view_query = 'CREATE OR REPLACE ALGORITHM=TEMPTABLE SQL SECURITY DEFINER VIEW ' . $name . ' AS (' . $this->get_query() . ')';
			try {
				if ($this->debug == true) {
					$this->stmt = $this->connection->prepare($this->view_query);
					$this->stmt->execute();
					if ($this->stmt->errorCode() != "00000") {
						$errorInfo = $this->stmt->errorInfo();
						throw new RuntimeException($errorInfo[2]);
					}
				} else {
					$this->stmt = $this->connection->prepare($this->view_query);
					$this->stmt->execute();
				}
			} catch (PDOException $exception) {
				if ($this->rollbackFn != false) {
					$this->rollbackExec($exception->getMessage());
				} else {
					throw new RuntimeException($exception);
				}
			}
			return $this;
		}

	/*
	|--------------------------------------------------------------------------
	|	PREPARE FUNCTIONS
	|--------------------------------------------------------------------------
	*/

		public function prepare_insert($ALIAS='response'){
			$this->prepareCrypt = true;

			$queryPrepare = 'INSERT ';

			if (!empty($this->ignore)) {
				$queryPrepare .= $this->ignore;
			}
			$queryPrepare .= ' INTO ';
			
			if ($this->tableClass != null) {$queryPrepare .= $this->tableClass;} 

			if (count($this->InsertVars) > 0) {
				$keyvalue = array();
				foreach ($this->InsertVars as $key => $value) {
					if ($value=='NULL') {
						$keyvalue[] = 'NULL';
					}elseif (is_string($value)) {
						if (substr($value, 0, 8) == "command:") {$value = substr($value,8);} 
						$_verify = $this->functionVerifyArray($value);
						if($_verify!==false){
							$keyvalue[] = $_verify['function'].(($_verify['function']!="")?'('.$_verify['params'].')':"NULL");
						}else{
							$keyvalue[] = '"'.$this->preventMySQLInject($value).'"';
						}
					} else {
						$keyvalue[] 		= $this->preventMySQLInject($value);
					}
				}
				$queryPrepare .= ' ( ' . implode(',', array_keys($this->InsertVars)) . ' ) ';
				$queryPrepare .= ' VALUES ';
				$queryPrepare .= ' (' . implode(',', $keyvalue) . ') ';
			}else{
				$queryPrepare = '-- NÃO EXISTE COLUNAS';
			}

			if ($this->set_where_not_exist == true) {$not = " NOT EXISTS ";
			} else { $not = "";}

			if (!empty($this->where)) { $queryPrepare .= ' WHERE' . $not . '(' . $this->where . ')'; }
 			if (!empty($this->having)) { $queryPrepare .= ' HAVING' . $not . '(' . $this->having . ')'; }

			if (!empty($this->on_duplicate)) {
				$queryPrepare .= $this->on_duplicate;
			}

			if(!is_array($this->query)){$this->query = [];}
			$this->query[$ALIAS]	= $queryPrepare;
			$this->InsertVars		= [];
			$this->clear();
		}

		public function prepare_replace($ALIAS='response'){
			$this->prepareCrypt = true;
			$queryPrepare = 'REPLACE ';
			if (!empty($this->ignore)) {
				$queryPrepare .= $this->ignore;
			}
			$queryPrepare .= ' INTO ';
			if ($this->tableClass != null) {$queryPrepare .= $this->tableClass;} 
			if (count($this->InsertVars) > 0) {
				$keyvalue = array();
				foreach ($this->InsertVars as $key => $value) {
					if ($value=='NULL') {
						$keyvalue[] = 'NULL';
					}elseif (is_string($value)) {
						if (substr($value, 0, 8) == "command:") {$value = substr($value,8);} 

						$_verify = $this->functionVerifyArray($value);
						if($_verify!==false){
							$keyvalue[] = $_verify['function'].(($_verify['function']!="")?'('.$_verify['params'].')':"NULL");
						}else{
							$keyvalue[] = '"'.$this->preventMySQLInject($value).'"';
						}
					} else {
						$keyvalue[] 		= $this->preventMySQLInject($value);
					}
				}
				$queryPrepare .= ' ( ' . implode(',', array_keys($this->InsertVars)) . ' ) ';
			} 
			$queryPrepare .= ' VALUES ';
			if (count($this->InsertVars) > 0) {
				$queryPrepare .= ' (' . implode(',', $keyvalue) . ') ';
			} else {
				exit;
			};
			if ($this->set_where_not_exist == true) {$not = " NOT EXISTS ";
			} else { $not = "";}
			if (!empty($this->where)) { $queryPrepare .= ' WHERE' . $not . '(' . $this->where . ')'; }
			if (!empty($this->having)) { $queryPrepare .= ' HAVING' . $not . '(' . $this->having . ')'; }


			if (!empty($this->on_duplicate)) {
				$queryPrepare .= $this->on_duplicate;
			}
			if (is_null($this->query)) {
				$this->query = $queryPrepare;
			} else {
				if(!is_array($this->query)){$this->query = [];}
				$this->query[$ALIAS]= $queryPrepare;
				$this->InsertVars	= [];
			}
		}

		public function prepare_update($ALIAS='response'){

			$queryPrepare = 'UPDATE ';
			if ($this->tableClass != null) {
				$queryPrepare .= $this->tableClass;
			} else {
				throw new Exception('$this->tableClass UNDEFINED, linha:'.__LINE__,1);
			}
			$queryPrepare .= ' SET ';
			if (count($this->Insert_Update) > 0) {
				$queryPrepare .= implode(',', $this->Insert_Update);
			}else{
				return true;
			};

			if ($this->set_where_not_exist == true) {$not = " NOT EXISTS ";
			} else { $not = "";}

			if (!empty($this->where)) { $queryPrepare .= ' WHERE' . $not . '(' . $this->where . ')'; }
			if (!empty($this->having)) { $queryPrepare .= ' HAVING' . $not . '(' . $this->having . ')'; }
			
			$queryPrepare = str_replace("command:",'',$queryPrepare);



			if(!is_array($this->query)){$this->query = [];}
			$this->query[$ALIAS] = $queryPrepare;
			$this->clear();
		}
		
		public function prepare_delete(){
			$this->prepareCrypt = true;
			$queryPrepare = 'DELETE FROM ';
			if (!empty($this->tableClass)) {
				$queryPrepare .= $this->tableClass;
			} 
			if ($this->set_where_not_exist == true) {
				$not = " NOT EXISTS ";
			} else { $not = "";}

			if (!empty($this->where)) { $queryPrepare .= ' WHERE' . $not . '(' . $this->where . ')'; }
			if (!empty($this->having)) { $queryPrepare .= ' HAVING' . $not . '(' . $this->having . ')'; }

			if (is_null($this->query)) {
				$this->query = $queryPrepare;
			} else {
				if(!is_array($this->query)){$this->query = [];}
				$this->query[] = $queryPrepare;
				$this->clear();
			}
		}

		public function prepare_select($alias = null, $script = null){
			$this->prepareCrypt = true;
			if (!is_array($this->query)) {$this->query = [];}
			$this->query[$alias] = (is_null($script)) ? $this->get_query():$script;
			$this->clear();
			return $this;
		}

	/*
	|--------------------------------------------------------------------------
	|	PREPARE FUNCTIONS
	|--------------------------------------------------------------------------
	*/


		public function insert(){
			$this->query= null;
			$this->prepare_insert();
			$this->execQuery();
			$this->clear();
			return $this;
		}

		public function replace(){
			$this->query= null;
			$this->prepare_replace();
			$this->execQuery();
			$this->clear();
			return $this;
		}

		public function update(){
			$this->query = null;
			$this->prepare_update();
			$this->execQuery();
			$this->clear();
			return $this;
		}
		
		public function delete(){
			$this->query = null;
			$this->prepare_delete();
			$this->execQuery();
			$this->clear();
			return $this;
		}

		public function select($alias = null, $script = null){	
			$this->prepare_select($alias, $script);
			$this->execQuery();
			$this->clear();
			return $this;
		}


	/*
	|--------------------------------------------------------------------------
	|	EXEC FUNCTION
	|--------------------------------------------------------------------------
	*/

	
        public function execQuery($successCallback=null){
			if($this->transactionFn == true){
                if($this->query==""){ return [];}

                $_QUERY = (!is_array($this->query))? [$this->query] : $this->query;
				
				// try {
				// 	$this->connection->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
				// 	$this->connection->beginTransaction();
				// 	foreach ($_QUERY as $_ALIAS => $query) {
				// 		$this->stmt = $this->connection->query($query);
				// 		if (!$this->stmt) {
				// 			$this->connection->rollBack();
				// 			throw new Exception($this->connection->errorInfo()[2]);
				// 			break;
				// 		}
				// 		$this->startProcessResult($this->stmt,$query,$_ALIAS);					
				// 		$this->stmt->closeCursor();					
				// 	}

				// 	/*
				// 	|--------------------------------------------------------------------
				// 	|	
				// 	|--------------------------------------------------------------------
				// 	*/
				// 	$this->SP_OUTPUTS = [];
				// 	foreach ($this->SP_OUTS as $SP_NAME =>$SP_OUTS) {
				// 		$_RESULT = [];
				// 		foreach ($SP_OUTS as $key2 => $value) {
				// 			$query = 'SELECT '.$value;
				// 			$this->stmt = $this->connection->query($query);	
				// 			$_RESULT[$value] = $this->stmt->fetchColumn();	
				// 		}
				// 		$this->SP_OUTPUTS[$SP_NAME] = $_RESULT;
				// 	}

				// 	$this->connection->commit();
				// 	/*
				// 	|--------------------------------------------------------------------
				// 	|	RETORNA NO SUCESSO UM CALLBACK
				// 	|--------------------------------------------------------------------
				// 	*/
				// 	if (is_callable($successCallback)) {
				// 		$successCallback($this);
				// 	}
				// } catch (PDOException $exception) {					
				// 	/*
				// 	|--------------------------------------------------------------------
				// 	|	RETORNA NO ERRO UM CALLBACK
				// 	|--------------------------------------------------------------------
				// 	*/
				// 	$this->connection->rollback();
				// 	if ($this->rollbackFn != false) {
				// 		$this->rollbackExec($exception->getMessage());
				// 	} else {
				// 		throw new RuntimeException($exception);
				// 	}
				// }
				try {
					$this->connection->setAttribute(PDO::ATTR_AUTOCOMMIT, true); // Ativa autocommit
					foreach ($_QUERY as $_ALIAS => $query) {
						$this->stmt = $this->connection->query($query);
						if (!$this->stmt) {
							throw new Exception($this->connection->errorInfo()[2]);
							break;
						}
						$this->startProcessResult($this->stmt, $query, $_ALIAS);
						$this->stmt->closeCursor();
					}

					$this->SP_OUTPUTS = [];
					foreach ($this->SP_OUTS as $SP_NAME => $SP_OUTS) {
						$_RESULT = [];
						foreach ($SP_OUTS as $key2 => $value) {
							$query = 'SELECT ' . $value;
							$this->stmt = $this->connection->query($query);
							$_RESULT[$value] = $this->stmt->fetchColumn();
						}
						$this->SP_OUTPUTS[$SP_NAME] = $_RESULT;
					}

					if (is_callable($successCallback)) {
						$successCallback($this);
					}

				} catch (PDOException $exception) {
					if ($this->rollbackFn != false) {
						$this->rollbackExec($exception->getMessage());
					} else {
						throw new RuntimeException($exception);
					}
				}



            }
        }
    }







	
        
