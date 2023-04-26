<?php
	namespace IsraelNogueira\MysqlOrm;
	use mysqli;
	use Exception;
	use RuntimeException;
	use ReflectionClass;

	class mysqlORM{
		private $initialized = false;
		public function __construct(){
			$this->connection			= null;
			$this->type_connection		= 'mysqli'; // mysqli | pgsql | 
			$this->like					= (object) array();
			$this->InsertVars			= array();
			$this->Insert_like			= array();
			$this->Insert_Update		= array();
			$this->fetch_array			= array();
			$this->obj					= array();
			$this->variaveis			= array();
			$this->setcolum				= '';
			$this->debug				= true;
			$this->decode				= false;
			$this->rell					= '';
			$this->ignore				= array();
			$this->on_duplicate			= array();
			$this->DISTINCT				= '';
			$this->query				= '';
			$this->replace_isso			= array();
			$this->group				= array();
			$this->replace_porisso		= array();
			$this->where				= null;
			$this->set_where_not_exist	= null;
			$this->settable				= [];
			$this->tableClass			= null;
			$this->setwhere				= null;
			$this->SP_RETURN			= null;
			$this->sp_response			= [];
			$this->SP					= null;
			$this->SP_PARAMS			= null;
			$this->view_query			= null;
			$this->CONECT_PARAMS		= [];
			$this->transactionFn		= false;
			$this->rollbackFn			= false;
			$this->colunmToJson			= [];
			$this->subQueryAlias		= null;
			$this->subQuery				= [];
			$this->columnsBlocked		= [];
			$this->columnsEnabled		= [];
			$this->colum				= '*';
			$this->setorder				= [];
			$this->mysqlFnBlockedClass	= [];
			$this->mysqlFnEnabledClass	= [];
			$this->limit				= null;
			
			$this->connect();
			$this->extended();
			$this->initialized			= true;
			return $this;
		}

		public function __set($name, $value) {
			$VAR_CARREGADAS = array_keys(get_mangled_object_vars($this));
			//---------------------------------------------------------------
			// Se já foi tudo carregado e se não existe ainda declarado
			//--------------------------------------------------------------- 
			if($this->initialized && !in_array($name,$VAR_CARREGADAS)){
				$this->set_insert($name, $value);
				$this->set_update($name, $value);		
			}else{
				$this->{$name}=$value;
			}
		}

		public function __call($_name, $arguments){
			if (in_array($_name, (get_class_methods('mysqlORM')??[]))) {
				return $this->$_name(...$arguments);
			} else {
				if (substr(strtolower($_name), 0, 3) == 'sp_' && strtolower($_name) != 'sp_response') {
					$this->SP			= $_name;
					$this->SP_PARAMS	= $arguments;
					return $this;
				} else {
					throw new RuntimeException('MariaDB error: Função '.$_name.' desconhecida');
				}
			}
		}

		static public function static() {
			return new static;
		}

		public function connect($server=null,$user=null,$pass=null,$name=null,$port=null){   
			$this->CONECT_PARAMS[0] = $server	??	DB_HOST;
			$this->CONECT_PARAMS[1] = $user		??	DB_USERNAME;
			$this->CONECT_PARAMS[2] = $pass		??	DB_PASSWORD;
			$this->CONECT_PARAMS[3] = $name		??	DB_DATABASE;
			$this->CONECT_PARAMS[4] = $port		??	DB_PORT;
			$this->connection_close();

			if($this->type_connection=='mysqli'){ // MYSQLI

				$this->connection = new mysqli($this->CONECT_PARAMS[0], $this->CONECT_PARAMS[1], $this->CONECT_PARAMS[2], $this->CONECT_PARAMS[3], $this->CONECT_PARAMS[4]);		
				if ($this->connection->connect_error) {
					throw new RuntimeException("Connection failed: " . mysqli_error($this->connection->connect_error));
				}
				$this->connection->query("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;");	
				

			}elseif($this->type_connection=='pgsql'){ // POSTGRID

				try {
					$this->connection = pg_connect("host=".$this->CONECT_PARAMS[0]." dbname=".$this->CONECT_PARAMS[3]." user=".$this->CONECT_PARAMS[1]." password=".$this->CONECT_PARAMS[2]);
					if (!$this->connection) {throw new Exception(pg_last_error());}
					pg_query($this->connection,"SET NAMES utf8mb4 COLLATE utf8mb4_general_ci;");	
				} catch (Exception $e) {
					echo "Connection failed: " . $e->getMessage();
				}

			}
			return $this;
		}

		public function connection_close(){
			if (!is_null($this->connection)) {
					$this->connection->close();
			}
		}

		public function functionVerify($str) {
			if (preg_match('/(\w+)\s*\((.*)\)/', $str, $matches)) {
				$funcao = $matches[1];
				if ((count($this->mysqlFnBlockedClass)>0 && in_array($funcao,$this->mysqlFnBlockedClass)) || (count($this->mysqlFnEnabledClass)>0 && !in_array($funcao,$this->mysqlFnEnabledClass)) ) {
					return ['function' => '', 'params' =>"(NULL)"];
				}else{
					return ['function' => $funcao, 'params' => isset($matches[2]) ? $matches[2] :""];
				}
			}
			return false;
		}

		public function gExtnd($_param,$default=null) {
			$reflection	= new ReflectionClass(get_called_class());
			if ($reflection->hasProperty($_param)) {
				$paramRoot	= $reflection->getProperty($_param);
				$paramRoot->setAccessible(true);
				return $paramRoot->getValue($this);
			}else{
				return $default;
			}
		}

		public function extended() {
			if (get_parent_class($this) !== false) {
				$this->type_connection			= $this->gExtnd('type_connection','mysqli');
				$this->tableClass				= $this->gExtnd('table',null);
				$this->columnsBlocked			= $this->gExtnd('columnsBlocked',[]);
				$this->columnsEnabled			= $this->gExtnd('columnsEnabled',[]);
				$this->mysqlFnBlockedClass		= $this->gExtnd('functionsBlocked',[]);
				$this->mysqlFnEnabledClass		= $this->gExtnd('functionsEnabled',[]);
			} else {
				return false;
			}
		}

		public function preventMySQLInject($string,$type=null){
			$search = array('@',';','*','?','|','+','%','(',')','[',']','{','}' ); 
			// $search = array_merge($search,['<','-','\\','>','=', "'",'"','/']);
			$input = str_replace($search, '', $string);
			return $input;
		}

		public function clear(){
			$this->like					= (object) array();
			$this->InsertVars			= array();
			$this->Insert_like			= array();
			$this->Insert_Update		= array();
			$this->setcolum				= '';
			$this->debug				= true;
			$this->decode				= false;
			$this->rell					= '';
			$this->ignore				= array();
			$this->on_duplicate			= array();
			$this->DISTINCT				= '';
			$this->replace_isso			= array();
			$this->replace_porisso		= array();
			$this->where				= null;
			$this->set_where_not_exist	= null;
			$this->settable				= [];
			$this->colum				= '';
			$this->setorder				= [];
			$this->setwhere				= null;
			$this->limit				= null;
			// $this->tableClass			= null;
		}
		

		public function transaction($return='none'){
			if($this->type_connection=='mysqli'){
				mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
				mysqli_begin_transaction($this->connection,MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
				mysqli_autocommit($this->connection, false);
			}elseif($this->type_connection=='pgsql'){
				pg_report_error($this->connection, PGSQL_REPORT_ERROR | PGSQL_REPORT_STRICT);
				pg_query($this->connection, "BEGIN ISOLATION LEVEL REPEATABLE READ");
				pg_query($this->connection, "SET autocommit = off");
			}	
			$this->transactionFn = true;
			$this->rollbackFn = $return;
		}

		public function rollbackExec($return){
			if($this->rollbackFn!='none'){
				call_user_func_array($this->rollbackFn,array($return));
			}
		}

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

		public function last_id(){
			if($this->type_connection=='mysqli'){ // MYSQLI
				return $this->connection->insert_id;
			}elseif($this->type_connection=='pgsql'){ // POSTGRID
				return pg_last_oid($this->connection);
			}	
		}

		public function sp_response($variable = null){
			if ($variable != null) {
				return $this->sp_response[$variable];
			} else {
				return $this->sp_response;
			}
		}

		public function getCollumns($tabela='result'){
			$_QUERY = 'SHOW COLUMNS FROM `'.$this->tableClass.'`;';
			$this->prepare_select('COLUMNS', $_QUERY);
			$this->execQuery();
			$this->clear();
			return $this;
		}

		public function fetch_array($variable = null){
			if ($variable != null) {
				return $this->fetch_array[$variable] ??[];
			} else {
				return $this->fetch_array ??[];
			}
		}
		
		public function fetch_obj($variable = null){
			if ($variable != null) {
				return json_encode($this->fetch_array[$variable],JSON_BIGINT_AS_STRING)??[];
			} else {
				return json_encode($this->fetch_array,JSON_BIGINT_AS_STRING)??[];
			}
		}

		public function showTables(){
			$tables = array();
			$this->select('TABLES','SHOW TABLES');
			foreach($this->fetch_array('TABLES') as  $row ){$tables[] = array_values($row)[0];}
			return $tables;
		}
		
		public function getDB_Tables($tables='*'){

			$return = '';
			if($this->type_connection=='mysqli'){

				mysqli_query($this->connection, "SET NAMES 'utf8'");
				if($tables == '*'){
					$tables = array();
					$result = mysqli_query($this->connection, 'SHOW TABLES');
					while($row = mysqli_fetch_row($result)){$tables[] = $row[0];}
				}else{
					$tables = is_array($tables) ? $tables : explode(',',$tables);
				}
				foreach($tables as $table){
					$result = mysqli_query($this->connection, 'SELECT * FROM '.$table);
					$num_fields = mysqli_num_fields($result);
					$num_rows = mysqli_num_rows($result);
					$row2 = mysqli_fetch_row(mysqli_query($this->connection, 'SHOW CREATE TABLE '.$table));
					$return.= "\n\n".$row2[1].";\n\n";
					$counter = 1;
					$return.="\n\n\n";
				}

			}elseif($this->type_connection=='pgsql'){

				pg_query($this->connection, "SET NAMES 'utf8'");
				if($tables == '*'){
					$tables		= array();
					$result		= pg_query($this->connection, 'SHOW TABLES');
					while($row	= pg_fetch_row($result)){$tables[] = $row[0];}
				}else{
					$tables = is_array($tables) ? $tables : explode(',',$tables);
				}
				foreach($tables as $table){
					$result		= pg_query($this->connection, 'SELECT * FROM '.$table);
					$num_fields	= pg_num_fields($result);
					$num_rows	= pg_num_rows($result);
					$row2		= pg_fetch_row(pg_query($this->connection, 'SHOW CREATE TABLE '.$table));
					$return		.= "\n\n".$row2[1].";\n\n";
					$counter 	= 1;
					$return		.="\n\n\n";
				}
			}		
			return $return;
		}

		public function getDB_Data($tables='*'){
			$return = '';
			if($this->type_connection=='mysqli'){
				mysqli_query($this->connection, "SET NAMES 'utf8'");
				if ($tables == '*') {
					$tables = array();
					$result = mysqli_query($this->connection, 'SHOW TABLES');
					while ($row = mysqli_fetch_row($result)) {$tables[] = $row[0];}
				} else {
					$tables = is_array($tables) ? $tables : explode(',', $tables);
				}
				foreach ($tables as $table) {
					$result		= mysqli_query($this->connection, 'SELECT * FROM ' . $table);
					$num_fields = mysqli_num_fields($result);
					$num_rows	= mysqli_num_rows($result);
					$counter = 1;
					for ($i = 0; $i < $num_fields; $i++) { //Over rows
						while ($row = mysqli_fetch_row($result)) {
							if ($counter == 1) {
								$return .= 'INSERT INTO ' . $table . ' VALUES(';
							} else {
								$return .= '(';
							}
							for ($j = 0; $j < $num_fields; $j++) {
								$row[$j] = addslashes($row[$j]);
								$row[$j] = str_replace("\n", "\\n", $row[$j]);
								if (isset($row[$j])) {$return .= '"' . $row[$j] . '"';} else { $return .= '""';}
								if ($j < ($num_fields - 1)) {$return .= ',';}
							}
							if ($num_rows == $counter) {
								$return .= ");\n";
							} else {
								$return .= "),\n";
							}
							++$counter;
						}
					}
					$return .= "\n\n\n";
				}

			}elseif($this->type_connection=='pgsql'){
				pg_query($this->connection, "SET NAMES 'utf8'");
				if ($tables == '*') {
					$tables = array();
					$result = pg_query($this->connection, 'SHOW TABLES');
					while ($row = pg_fetch_row($result)) {$tables[] = $row[0];}
				} else {
					$tables = is_array($tables) ? $tables : explode(',', $tables);
				}
				foreach ($tables as $table) {
					$result		= pg_query($this->connection, 'SELECT * FROM ' . $table);
					$num_fields = pg_num_fields($result);
					$num_rows	= pg_num_rows($result);
					$counter = 1;
					for ($i = 0; $i < $num_fields; $i++) { //Over rows
						while ($row = pg_fetch_row($result)) {
							if ($counter == 1) {
								$return .= 'INSERT INTO ' . $table . ' VALUES(';
							} else {
								$return .= '(';
							}
							for ($j = 0; $j < $num_fields; $j++) {
								$row[$j] = addslashes($row[$j]);
								$row[$j] = str_replace("\n", "\\n", $row[$j]);
								if (isset($row[$j])) {$return .= '"' . $row[$j] . '"';} else { $return .= '""';}
								if ($j < ($num_fields - 1)) {$return .= ',';}
							}
							if ($num_rows == $counter) {
								$return .= ");\n";
							} else {
								$return .= "),\n";
							}
							++$counter;
						}
					}
					$return .= "\n\n\n";
				}
			}
			return $return;
		}

		public function getDB_Columns($alias=null){
			$this->query = 'SHOW COLUMNS FROM ';
			if ($this->tableClass != null) {
				$this->query .= $this->tableClass;
			}
			if ($this->debug == true) {
				$consulta = mysqli_query($this->connection, $this->query);
				if (mysqli_error($this->connection)) {
					throw new RuntimeException(mysqli_error($this->connection));
				}
			} else { 
				$consulta = @mysqli_query($this->connection, $this->query);
			}
			$_array = [];
			while ($row = mysqli_fetch_assoc($consulta)) {
				$_array[] = $row;
			}
			if($alias!=null){
				$this->fetch_array['show_columns'][$alias] = $_array;
			}else{
				$this->fetch_array['show_columns'][] = $_array;
			}
		}

		public function dataTable(array $oAjaxData=[]){

				// PREPARAMOS OS FILTROS DO DATATABLE

				$draw		= $oAjaxData['draw'];
				$colunas	= $oAjaxData['columns'];
				$start		= $oAjaxData['start'];
				$length		= $oAjaxData['length'];
				$search		= $oAjaxData['search']['value'];
				$order		= $oAjaxData['order'][0]['column'];
				$order		= $colunas[$order]['data'];
				$order		= [$order=>$oAjaxData['order'][0]['dir']];

				// RESGATAMOS O TOTAL DA BASE SEM FILTRO SEM NADA
				$numrow_sem_filtros			= clone $this;
				$numrow_sem_filtros->set_colum('COUNT(*) as TOTAL');
				
				// COLOCAR ISSO LÁ NA CHAMADA PRINCIPAL
				$allQuery = $numrow_sem_filtros->get_query();
				
				$numrow_sem_filtros->select();
				$_fetch_array = $numrow_sem_filtros->fetch_array() ?? [];
				$recordsTotal = ($_fetch_array!=[]) ? $numrow_sem_filtros->fetch_array()['response'][0]['TOTAL'] : 0;


				//INSERE AS PALAVRAS DE SEARCH 
				$busca_por_coluna = false;
				foreach ($colunas as $value) {
					if($value['search']['value']!=""){
						$busca_por_coluna = true;
						$this->like($value['data'],'%'.trim(preg_replace('/[\s]+/mu', '%',trim($value['search']['value']))).'%');
					}
				}
				if(strlen($search)>0){
					foreach($colunas as $value) {
						$this->like($value['data'],'%'.trim(preg_replace('/[\s]+/mu', '%',trim($search))).'%');
					}
				}
				
				//CASO NÃO TENHA WHERES AINDA, ADICIONAMOS 
				if($this->where==null){$this->set_where(' TRUE ');}

				//POSSIVEIS WHERE DE FORMULARIOS
				// $this->set_where('AND 2=2');
				// $this->set_where('AND 3=3');

				//ORDENAMOS PELAS COLUNAS 
				foreach($order as  $key=>$value) {
					$this->set_order($key,$value);
				}

				// TOTAL DE PESQUISA COM AS COLUNAS E PESQUISAS  
				if(strlen($search)>0 || $busca_por_coluna==true){
					$noPage = clone $this;
					$noPage->setcolum = [];
					$noPage->set_colum('COUNT(*) as TOTAL');
					$noPage->select();
					$fetch_array = $noPage->fetch_array()??[];
					$_FILTRADO 	= ($fetch_array!=[])?intVal($noPage->fetch_array()['response'][0]['TOTAL']):0;
				}else{
					$_FILTRADO 	= intVal($recordsTotal);
				}


				// AGORA TOTAL COM A PAGINAÇÃO 
				$this->set_limit($start,$length);
				$query = $this->get_query();
				$fire = new mysqlORM();
				$fire->connect();
				$fire->select('DataTable',$query);

				$_TOTAL		= intVal($recordsTotal);
				return [
					"query"				=>	$query,
					"paginate"			=>	$start.' - '.$length,
					"draw"				=>	$draw,
					"recordsFiltered"	=>	$_FILTRADO,
					"recordsTotal"		=>	$_TOTAL,
					"data"				=>	$fire->fetch_array()['DataTable'] ?? []
				];
		}

		public function colum($P_COLUMNS = array(),$JSON=false){
			$this->set_colum($P_COLUMNS,$JSON);
		}

		public function set_colum($P_COLUMNS = array(),$JSON=false){
			if (empty($this->setcolum)){$this->setcolum = array();}
			if (is_array($P_COLUMNS)) {
				foreach ($P_COLUMNS as $COLUMNS) {
					if (is_string($COLUMNS) && $COLUMNS != "") {
						$COLUMNS =  (substr($COLUMNS, 0, 8) == "command:")? substr($COLUMNS, 8):$COLUMNS;
						$this->setcolum[] = $COLUMNS;
						if ($JSON == true) {
							if (stripos($COLUMNS, ' as ') > -1) {
								$this->colunmToJson[] = preg_split("/ as /i", $COLUMNS);
							} else {
								$this->colunmToJson[] = $COLUMNS;
							}
						}
					}
				}
			} elseif (is_string($P_COLUMNS) && $P_COLUMNS != "") {
				$COLUMNS =  (substr($P_COLUMNS, 0, 8) == "command:")? substr($P_COLUMNS, 8):$P_COLUMNS;
				$this->setcolum[] = $COLUMNS;
				if($JSON==true){
					if(stripos($COLUMNS,' as ')>-1){
						$this->colunmToJson[]=preg_split("/ as /i", $COLUMNS);					
					}else{
						$this->colunmToJson[]=$COLUMNS;
					}
				}
			}

			if (is_array($this->setcolum)) {
				// coloquei @ não porque da erro, mas aparece um NOTICE chato...
				$this->colum = @implode(',',$this->setcolum); 
			} else {
				$this->colum = $this->setcolum;
			}
			if ($this->colum == "") {
				$this->colum = "*";
			}
		}

		public function table($TABLES, $ALIAS=null){
			$this->set_table($TABLES, $ALIAS);
		}

		public function set_table($TABLES, $ALIAS=null){
			if($ALIAS==null){
				$this->tableClass =$TABLES;
			}else{
				$this->tableClass =trim($TABLES).' as '.$ALIAS;
			}
			return $this;
		}

		public function where($WHERES){
			$this->set_where($WHERES);
		}

		public function set_where($WHERES){
			if (empty($this->setwhere) || $this->setwhere == null) {
				$this->setwhere = array();
			}
			$this->setwhere[] = $WHERES;
			$this->where = implode(' ',$this->setwhere);
			return $this;
		}

		public function set_where_not_exist(){
			$this->set_where_not_exist = true;
			return $this;
		}

		public function order($colum = null, $order = null){
			$this->set_order($colum, $order);
		}

		public function set_order($colum = null, $order = null){
			if ($colum == null && $order == null) {
				throw new RuntimeException('Valor set_order indefinido');
			}
			if ($colum != null && $order == null) {
				$this->setorder[] = $colum;
			} else {
				$this->setorder[] = $colum . ' ' . $order;
			}
			$this->order = ' ORDER BY ' . implode(',',$this->setorder);
			return $this;
		}

		public function group_by($colum){
			$this->group[] = $colum;
			return $this;
		}

		public function limit($init=null, $finit=null){
			$this->set_limit($init, $finit);
		}
		public function set_limit($init=null, $finit=null){
			if (is_null($init) && is_null($finit)) {
				throw new RuntimeException('Valor set_limit(?,?) indefinido');
				exit;
			}
			$this->limit = (is_null($finit)) ? ' LIMIT ' . $init : ' LIMIT '.$init.", ".$finit;
			return $this;
		}

		public function set_insert($colum, $var){
			if (is_null($var)) {$var = 'NULL';}
			$this->InsertVars[$colum] = $this->preventMySQLInject($var);
			return $this;
		}

		public function set_insert_form($object){
			if(is_array($object)){
				foreach($object as $key => $var){
					$this->set_insert($key,$var);
				}
			}
			return $this;
		}

		public function set_update_form($object){
			if(is_array($object)){
				foreach($object as $key => $var){
					$this->set_update($key,$var);
				}
			}
			return $this;
		}

		public function set_update($colum, $var,$type=null){
			if (is_string($var)) {
				if (substr($var, 0, 8) == "command:") {$var = substr($var,8);} 
				$_verify = $this->functionVerify($var);
				if($_verify!==false){
					$var = $_verify['function'].'('.$_verify['params'].')';
				}else{
					$var = '"' . $this->preventMySQLInject($var,$type) . '"';
				}		
			} else {
				$var = $this->preventMySQLInject($var,$type);
			}
			$this->Insert_Update[] =$colum . '=' . $var;
			return $this;
		}

		public function debug($bolean){
			$this->debug = $bolean;
			return $this;
		}

		public function ignore($dados){
			$this->ignore = 'IGNORE';
			return $this;
		}

		public function on_duplicate($dados){
			$this->on_duplicate = ' ON DUPLICATE KEY UPDATE ' . $dados . ' ';
			return $this;
		}

		public function distinct(){
			$this->DISTINCT = ' DISTINCT ';
			return $this;
		}

		public function join($join = "LEFT", $tabela, $on){
			$this->rell .= ' '.$join . ' JOIN ' . $tabela . ' ON ' . $on;
			return $this;
		}

		public function like($coluna, $palavra_chave){
			array_push($this->Insert_like, ' LOWER('.$coluna.') LIKE LOWER("'.$palavra_chave.'")');
			return $this;
		}

		public function REGEXP($coluna, $palavra_chave){
			array_push($this->Insert_like, $coluna . ' REGEXP "' . _likeString($palavra_chave) . '"');
			return $this;
		}

		public function create_view($name=null){
			if($name==null){
				throw new InvalidArgumentException('create_view(NULL), Error:'.__LINE__);
			}	
			$this->view_query = 'CREATE OR REPLACE ALGORITHM=TEMPTABLE SQL SECURITY DEFINER VIEW ' . $name . ' AS  ('.$this->get_query().')';
			if ($this->debug == true) {
				$consulta = mysqli_query($this->connection, $this->view_query);
				if (mysqli_error($this->connection)) {
					throw new RuntimeException(mysqli_error($this->connection));
				}
			} else {
				$consulta = @mysqli_query($this->connection,$this->view_query);
			}
			return $this;
		}

		public function verify(){
			if (($this->tableClass != null && $this->tableClass != "") && (!empty($this->setcolum) && $this->setcolum != "")) {
				if (is_array($this->tableClass)) {
					$this->tableClass = implode('',$this->tableClass);
				}
				if (is_array($this->setcolum)) {
					$this->setcolum = implode('',$this->setcolum);
				}
				$result = mysqli_query($this->connection, "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='" . $this->tableClass . "' AND column_name='" . $this->setcolum . "'");
				$tableExists = mysqli_num_rows($result);
				if ($tableExists > 0) {$tableExists = true;
				} else { $tableExists = false;}return $tableExists;
			} elseif ($this->tableClass != null && $this->tableClass != "") {
				$result = mysqli_query($this->connection, "SHOW TABLES LIKE '" . $this->tableClass . "' ");
				$tableExists = mysqli_num_rows($result) > 0;return $tableExists;
			} else {
				throw new RuntimeException("Favor setar uma tabela ou tabela + coluna");
				return false;
			}
			return true;
		}

		public function string_replace($value, $key){
			$value = str_replace($this->replace_isso, $this->replace_porisso, $value);
		}

		public function tableSubQuery($subQuery = null){
			if ($subQuery == null) {
				throw new RuntimeException("Parâmetro incorreto ou inexistente: ->subQuery()");
			} else {
				preg_match("/^\s*\(*(\w+)\)*\s+(\w+)\s*$/", $subQuery, $matches);
				$_suQuery	= $matches[1];
				$_alias		= $matches[2]??'';
				$this->tableClass ='('.$this->subQuery[$_suQuery].') '.$_alias;
			}
			return $this;
		}

		public function columSubQuery($subQuery = null){
			if ($subQuery == null) {
				throw new RuntimeException("Parâmetro incorreto ou inexistente: ->columSubQuery()");
			} else {
				$subQuery = preg_replace(['/[\s\t]+/', '/[()]/'], [' ', ''], $subQuery);
				$regex = "/\(([^)]+)\)\s*|\b(\w+)\b\s*(?:as\s*(\w+))?/";
				preg_match_all($regex, $subQuery, $matches, PREG_SET_ORDER);
				if(count($matches)==1 && count($matches[0])==4){
					$_suQuery	= $matches[0][2];
					$_alias		= $matches[0][3];
				}elseif(count($matches)==2){
					$_suQuery	= $matches[0][2];
					$_alias		= $matches[1][2];
				}elseif(count($matches)==1){
					$_suQuery	= $matches[0][2];
					$_alias		= $matches[0][2];
				}
				$this->set_colum('('.$this->subQuery[$_suQuery].') as '.$_alias);
			}
			return $this;
		}

		public function setSubQuery($subQuery = null){
			if ($subQuery == null) {
				throw new RuntimeException("Parâmetro incorreto ou inexistente: ->setSubQuery()");
			} else {
				$this->subQuery[$subQuery]	= $this->get_query();
				$this->clear();
			}
			return $this;
		}

		public function query($script = null){
			if ($script == null) {
				throw new RuntimeException("Parâmetro incorreto ou inexistente");
			} else {
				return $this->select('query', $script);
			}
			return $this;
		}

		public function jsonToArray($json){
			$array = json_decode($json, true);
			array_walk_recursive($array, function (&$value) {
				if (is_string($value)) {
					$value = htmlspecialchars_decode($value);
				}
			});
			return $array;
		}

		public function process_result($result, $alias = null){
			$array = null;
			$obj = null;
			while ($row = mysqli_fetch_assoc($result)) {
				$array[] 	= $row;
				$obj[] 		= $row;
			}
			if(count($this->colunmToJson)>0){	
				foreach (($array??[]) as $array_key => $array_value) {
					$_LINHA = $array[$array_key];
					foreach ($this->colunmToJson as $key=>$value) {	
						if(is_array($value)){
							$_CHAVE = $value[1];
						}else{
							$_CHAVE = explode('.',$value)[1];
						}
						if(array_key_exists($_CHAVE, $array[$array_key])){
							$array[$array_key][$_CHAVE] = (is_null($array[$array_key][$_CHAVE])) ? [] : json_decode(stripslashes(str_replace(["\r","\n"],'',trim($array[$array_key][$_CHAVE]))), true);
						}
					}
				}
			}

			if ($alias == null) {
				$this->_num_rows['response']	=  @mysqli_num_rows($result);
				$this->fetch_array['response']	= $array??[];
				$this->obj['response']			= (object) $obj??[];
			} else {
				$this->_num_rows[$alias]		=  @mysqli_num_rows($result);
				$this->fetch_array[$alias]		= $array??[];
				$this->obj[$alias]				= (object) $obj??[];
			}
			return $this;
		}	

		public function set_var($key,$value){
			mysqli_query($this->connection,'SET @'.$key.' := '.$value.';');
		}
		
		public function insert_or_replace($type=null){
			$keyvalue = array();
			foreach ($this->InsertVars as $key => $value) {
				if ($value=='NULL') {
						$keyvalue[] = $key . 'NULL';
					}elseif(is_string($value)) {

						if (substr($value, 0, 8) == "command:") {$value = substr($value,8);} 
						$_verify = $this->functionVerify($value);
						if($_verify!==false){
							$keyvalue[] = $key.'='.$_verify['function'].'('.$_verify['params'].')';
						}else{
							$keyvalue[] = $key.'="'.$this->preventMySQLInject($value,$type).'"';
						}

				} else {
					$keyvalue[] = $key . "=" . $this->preventMySQLInject($value,$type);
				}
			}
			$this->on_duplicate = ' ON DUPLICATE KEY UPDATE ' . implode(',', $keyvalue);
			return $this;
		}

		public function prepare_insert($type=null){
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
						$_verify = $this->functionVerify($value);
						if($_verify!==false){
							$keyvalue[] = $_verify['function'].'('.$_verify['params'].')';
						}else{
							$keyvalue[] = '"'.$this->preventMySQLInject($value,$type).'"';
						}
					} else {
						$keyvalue[] 		= $this->preventMySQLInject($value,$type);
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
			if (!empty($this->where)) {
				$queryPrepare .= ' WHERE' . $not . '(' . $this->where . ')';
			}
			if (!empty($this->on_duplicate)) {
				$queryPrepare .= $this->on_duplicate;
			}
			if (is_null($this->query)) {
				$this->query = $queryPrepare;
			} else {
				if(!is_array($this->query)){$this->query = [];}
				$this->query[]		= $queryPrepare;
				$this->InsertVars	= [];
			}
		}

		public function insert(){
			$this->query= null;
			$this->prepare_insert();
			$this->execQuery();
			$this->clear();
			return $this;
		}

		public function prepare_replace($type=null){
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
						$_verify = $this->functionVerify($value);
						if($_verify!==false){
							$keyvalue[] = $_verify['function'].'('.$_verify['params'].')';
						}else{
							$keyvalue[] = '"'.$this->preventMySQLInject($value,$type).'"';
						}
					} else {
						$keyvalue[] 		= $this->preventMySQLInject($value,$type);
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
			if (!empty($this->where)) {
				$queryPrepare .= ' WHERE' . $not . '(' . $this->where . ')';
			}
			if (!empty($this->on_duplicate)) {
				$queryPrepare .= $this->on_duplicate;
			}
			if (is_null($this->query)) {
				$this->query = $queryPrepare;
			} else {
				if(!is_array($this->query)){$this->query = [];}
				$this->query[]		= $queryPrepare;
				$this->InsertVars	= [];
			}
		}

		public function replace(){
			$this->query= null;
			$this->prepare_replace();
			$this->execQuery();
			$this->clear();
			return $this;
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

			if (!empty($this->where)) {
				$queryPrepare .= ' WHERE' . $not . '(' . $this->where . ')';
			}
			if(!is_array($this->query)){$this->query = [];}
			$this->query[$ALIAS] = $queryPrepare;
			$this->clear();
		}

		public function update(){
			$this->query = null;
			$this->prepare_update();
			$this->execQuery();
			$this->clear();
			return $this;
		}

		public function prepare_delete(){
			$queryPrepare = 'DELETE FROM ';
			if (!empty($this->tableClass)) {
				$queryPrepare .= $this->tableClass;
			} 

			if ($this->set_where_not_exist == true) {$not = " NOT EXISTS ";
			} else { $not = "";}

			if (!empty($this->where)) {
				$queryPrepare .= ' WHERE' . $not . '(' . $this->where . ')';
			}

			if (is_null($this->query)) {
				$this->query = $queryPrepare;
			} else {
				if(!is_array($this->query)){$this->query = [];}
				$this->query[] = $queryPrepare;
				$this->clear();
			}

		}

		public function delete(){
			$this->query = null;
			$this->prepare_delete();
			$this->execQuery();
			$this->clear();
			return $this;
		}

		public function get_query($type = 'SELECT'){
			$_QUERY = '';
			if(!in_array(trim($type),['SELECT', 'INSERT', 'DELETE','UPDATE'])){
				throw new RuntimeException("->get_query() com parâmetro incorreto. Utilize 'SELECT', 'INSERT', 'DELETE' ou 'UPDATE'");
			}
			$_QUERY .= $type.' ';
			if (!empty($this->DISTINCT)) {
				$_QUERY .= $this->DISTINCT;
			}
			if (!empty($this->colum)) {
				$_QUERY .= $this->colum;
			} else { $_QUERY .= ' * ';}$_QUERY .= ' FROM ';
			if ($this->tableClass != null) {
				$_QUERY .= $this->tableClass;
			}
			if (!empty($this->rell) && !empty($this->rell)) {
				$_QUERY .= ' '.$this->rell . ' ';
			}
			if (count($this->Insert_like) > 0) {$array_like = implode(' OR ', $this->Insert_like);
			} else { $array_like = "";}
			if ($this->where != null || (count($this->Insert_like) > 0)) {
				if ($this->where != null && $array_like != "") {
					$this->where = $this->where . " AND ";
				}
				if ($this->set_where_not_exist == true) {$not = " NOT EXISTS ";
				} else { $not = "";}
				$_QUERY .= ' WHERE' . $not . '(' . $this->where . '(' . $array_like . '))';
				$_QUERY = str_replace('())', ')', $_QUERY);
			}
			if (count($this->group)>0) {
				$_QUERY .= ' GROUP BY '.implode(',',$this->group).' ' ;
			}
			if (!empty($this->order)) {
				$_QUERY .= $this->order . ' ';
			}
			if (!is_null($this->limit)) {
				$_QUERY .= $this->limit;
			}
			return $_QUERY;
		}

		public function execSP($_ALIAS='RESPONSE', $_RETORNO=null){				
				$this->prepare_execSP($_ALIAS,$_RETORNO);
				$this->execQuery();
				$this->clear();
				return $this;	
		}

		public function prepare_execSP($_ALIAS='RESPONSE', $_RETORNO=null){
			$this->connect();
			#---------------------------------------------------------------
			# TRATAMOS AS ENTRADAS
			#---------------------------------------------------------------
			foreach ($this->SP_PARAMS as $key=>$value) {

				if(is_array($value) || is_object($value)){

					$this->SP_PARAMS[$key] = "'".trim(json_encode($value,JSON_BIGINT_AS_STRING), '[]')."'";

				}elseif(is_numeric($value) || is_int($value) || is_float($value)){

					$this->SP_PARAMS[$key] = trim($value);

				}elseif(is_string($value)){

					$this->SP_PARAMS[$key] = "'".trim($value)."'";

				}elseif($value==null){

					$this->SP_PARAMS[$key] = 'NULL';
				}
			}
			if (!is_array($this->query)) {$this->query = [];}

			if($_RETORNO==null){
				$this->query[$_ALIAS] = 'CALL ' . $this->SP . '(' . implode(',',$this->SP_PARAMS). ');';
			}else{
				$this->query[$_ALIAS] = 'CALL ' . $this->SP . '(' . implode(',',$this->SP_PARAMS). ', @' . $_RETORNO . ');';
			}
		}

		public function prepare_select($alias = null, $script = null){
			if (!is_array($this->query)) {$this->query = [];}
			$this->query[$alias] = (is_null($script)) ? $this->get_query():$script;
			$this->clear();
			return $this;
		}

		public function select($alias = null, $script = null){	
			$this->prepare_select($alias, $script);
			$this->execQuery();
			$this->clear();
			return $this;
		}

		public function startProcessResult($result,$query,$_ALIAS){
			if(gettype($result)=='object'){
				if($_ALIAS==''){
					$this->process_result($result);
				}else{
					$this->process_result($result,$_ALIAS);
				}
			}
		}

		public function execQuery($P_ALIAS='response'){
			if($this->transactionFn == true){
				try {
					if(is_array($this->query)){					
						foreach ($this->query as $_ALIAS => $query) {					
							$result = mysqli_query($this->connection, $query);	
							$this->startProcessResult($result,$query,$_ALIAS);
							
						}
					}elseif($this->query!=''){
						$result = mysqli_query($this->connection, $this->query);
						$this->startProcessResult($result,$this->query,$P_ALIAS);
						
					}
					mysqli_commit($this->connection);
				} catch (mysqli_sql_exception $exception) {
					mysqli_rollback($this->connection);
					if($this->rollbackFn!=false){
						$this->rollbackExec($exception->getMessage());
					}else{
						throw new RuntimeException($exception);
					}
				}
			}else{
				if(is_array($this->query)){
					foreach ($this->query as $_ALIAS => $query) {
						try {
							$result = mysqli_query($this->connection, $query);
							$this->startProcessResult($result,$query,$_ALIAS);
							if (mysqli_error($this->connection)) {
								throw new RuntimeException(mysqli_error($this->connection));
							}
						} catch (\Throwable $error) {
							throw new Exception($error,1);
						}
					}
				}else{
					try {
						$result = mysqli_query($this->connection, $this->query);
						$this->startProcessResult($result, $this->query,$P_ALIAS);
						if (mysqli_error($this->connection)) {
							throw new RuntimeException(mysqli_error($this->connection));
						}
					} catch (\Throwable $error) {
							throw new Exception($error,1);
					}


				}				
			}
		}
	}
