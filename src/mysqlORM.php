<?php
	namespace IsraelNogueira\MysqlOrm;
	use IsraelNogueira\MysqlOrm\connection;
	use IsraelNogueira\MysqlOrm\queryBuilder;
	use IsraelNogueira\MysqlOrm\actions;
	use PDO;
	use Exception;
	use RuntimeException;
	use ReflectionClass;
	use InvalidArgumentException;
	use PDOException;


/**
 * -------------------------------------------------------------------------
 * @author Israel Nogueira <israel@feats.com>
 * @package library
 * @license GPL-3.0-or-later
 * @copyright 2022 Israel Nogueira
 * -------------------------------------------------------------------------
 * 
 * 	Classe de ORM para base de dados: 
 * 	Ainda não funciona todas as bases, mas implementarei aos poucos
 * 
 *  Plano é suportar as seguintes conexões:
 * 	mysql | pgsql | sqlite | ibase | fbird | oracle | mssql | dblib | sqlsrv
 * 
 * -------------------------------------------------------------------------
 */


	class mysqlORM{
		use connection;
		use queryBuilder;
		use actions;
		private $initialized = false;
		public static $dbaseType;
/* ------------------------------------------------------------------------- */

		public function __construct(){
			$this->like					= (object) array();
			$this->InsertVars			= array();
			$this->Insert_like			= array();
			$this->Insert_Update		= array();
			$this->fetch_array			= array();
			$this->obj					= array();
			$this->variaveis			= array();
			$this->setcolum				= null;
			$this->debug				= true;
			$this->decode				= false;
			$this->rell					= null;
			$this->ignore				= array();
			$this->on_duplicate			= array();
			$this->DISTINCT				= null;
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
			$this->colum				= null;
			$this->setorder				= [];
			$this->mysqlFnBlockedClass	= [];
			$this->mysqlFnEnabledClass	= [];
			$this->limit				= null;
			$this->stmt					= null;
			$this->connection			= $this->connect([
				"user"=>DB_USERNAME,
				"pass"=>DB_PASSWORD,
				"name"=>DB_DATABASE,
				"host"=>DB_HOST,
				"type"=>DB_TYPE,
				"port"=>DB_PORT,
				"char"=>DB_CHAR,
				"flow"=>DB_FLOW,
				"fkey"=>DB_FKEY,
			]);
			$this->extended();
			$this->initialized			= true;
			return $this;
		}

/*
 |--------------------------------------------------------------------------
 |		__SET
 |--------------------------------------------------------------------------
 | 
 |  Utilizo basicamente para dar um valor as colunas;
 |	Pode ser usado para Inserts ou Updates
 |	
 |	$param->nome_da_coluna = "string";
 |
 |--------------------------------------------------------------------------
*/

		public function __set($name, $value) {
			$VAR_CARREGADAS = array_keys(get_mangled_object_vars($this));
			if($this->initialized && !in_array($name,$VAR_CARREGADAS)){
				$this->set_insert($name, $value);
				$this->set_update($name, $value);		
			}else{
				$this->{$name}=$value;
			}
		}
/*
 |--------------------------------------------------------------------------
 |		__CALL
 |--------------------------------------------------------------------------
 | 
 |  Utilizo basicamente executar Stored Procedures ou Functions;
 |	Basta acrescentar SP_  ou FN_ no inicio da função
 |	e caso a função não exista na classe, ele entenderá que é da Base;
 |	Ex:
 |	$param->SP_processaAlgo($param1,$param2);
 |	$param->FN_processaAlgo($param1,$param2);
 |
 |--------------------------------------------------------------------------
*/
		public function __call($_name, $arguments){
			if (in_array($_name, (get_class_methods('mysqlORM')??[]))) {
				return $this->$_name(...$arguments);
			} else {
				if (substr(strtolower($_name), 0, 3) == 'sp_') {
					$this->SP			= $_name;
					$this->SP_PARAMS	= $arguments;
					return $this;
				} else {
					throw new RuntimeException('MariaDB error: Função '.$_name.' desconhecida');
				}
			}
		}

/*
 |--------------------------------------------------------------------------
 | 
 |	Aqui apenas caso a pessoa queira utilizar a classe estaticamente
 |
 |	Ex:
 |  return meuModel::static()->table('minha_tabela')->select();
 |
 |--------------------------------------------------------------------------
*/
		static public function static() {
			return new static;
		}

/*
 |--------------------------------------------------------------------------
 |		DADOS DA BASE
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

		public function getDB_Columns()
		{
			if(is_null($this->tableClass)){
				throw new RuntimeException('getDB_Columns() sem tabela de referencia ->colum(NULL)');
			}
			$this->query = 'SHOW COLUMNS FROM ' . $this->tableClass;
			$this->stmt = $this->connection->query($this->query);
			if ($this->debug == true) {
				if ($this->stmt === false) {
					throw new RuntimeException($this->connection->errorInfo()[2]);
				}
			}
			$_array = [];
			while ($row = $this->stmt->fetch(PDO::FETCH_ASSOC)) {
				$_array[] = $row;
			}
			$this->fetch_array['show_columns'][$this->tableClass] = $_array;
		}

		public function showTables()
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

/*
 |--------------------------------------------------------------------------
 |		VERIFICA SE UMA FUNCTION É PERMITIDA OU VÁLIDA
 |--------------------------------------------------------------------------
 | 
 |  Funções como CONCAT, SHA2, UPPERCASE  etc podem ser bloqueadas na model
 |	Aqui fazemos as verificações
 |  
 |--------------------------------------------------------------------------
*/
		public function functionVerify($str)
		{
			if (preg_match('/(\w+)\s*\((.*)\)/', $str, $matches)) {
				$funcao = $matches[1];
				if (
						(
							count($this->mysqlFnBlockedClass)>0 && in_array($funcao,$this->mysqlFnBlockedClass)

						) || (

							count($this->mysqlFnEnabledClass)>0 && !in_array($funcao,$this->mysqlFnEnabledClass)
							
						) 
					) {
					return ['function' => '', 'params' =>"(NULL)"];
				}else{
					return ['function' => $funcao, 'params' => isset($matches[2]) ? $matches[2] :""];
				}
			}
			return false;
		}

/*
|--------------------------------------------------------------------------
|		PARÂMETROS DE CONFIGURAÇÃO
|--------------------------------------------------------------------------
|
|	Na model, temos alguns parametros pré-preparados
|	Aqui nós acessamos eles e setamos na classe mãe
|
|--------------------------------------------------------------------------
*/
	public function extended() 
	{
		if (get_parent_class($this) !== false) {
			$this->tableClass				= $this->gExtnd('table',null);
			$this->columnsBlocked			= $this->gExtnd('columnsBlocked',[]);
			$this->columnsEnabled			= $this->gExtnd('columnsEnabled',[]);
			$this->mysqlFnBlockedClass		= $this->gExtnd('functionsBlocked',[]);
			$this->mysqlFnEnabledClass		= $this->gExtnd('functionsEnabled',[]);
		} else {
			return false;
		}
	}

	public function gExtnd($_param,$default=null) 
	{
		$reflection	= new ReflectionClass(get_called_class());
		if ($reflection->hasProperty($_param)) {
			$paramRoot	= $reflection->getProperty($_param);
			$paramRoot->setAccessible(true);
			return $paramRoot->getValue($this);
		}else{
			return $default;
		}
	}

/*
|--------------------------------------------------------------------------
|	PREVENÇÃO DE INJECT
|--------------------------------------------------------------------------
|
|	Aqui se alguém quiser dar alguma opinião,
|	Pois quando preciso salvar HTML preciso desabilitar essa função
|	Mas tinha que ter alguma forma de melhorar isso
|
|--------------------------------------------------------------------------
*/

	public function preventMySQLInject($string,$type=null)
	{
		$search = array('@',';','*','?','|','+','%','(',')','[',']','{','}' ); 
		// $search = array_merge($search,['<','-','\\','>','=', "'",'"','/']);
		$input = str_replace($search, '', $string);
		return $input;
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
			$this->like					= (object) array();
			$this->InsertVars			= array();
			$this->Insert_like			= array();
			$this->Insert_Update		= array();
			$this->stmt					= null;
			$this->setcolum				= null;
			$this->debug				= true;
			$this->decode				= false;
			$this->rell					= null;
			$this->ignore				= array();
			$this->on_duplicate			= array();
			$this->DISTINCT				= null;
			$this->replace_isso			= array();
			$this->replace_porisso		= array();
			$this->where				= null;
			$this->set_where_not_exist	= null;
			$this->colum				= null;
			$this->setorder				= [];
			$this->setwhere				= null;
			$this->limit				= null;
		}

/*
|--------------------------------------------------------------------------
|	PROCESSANDO O RESULTADO
|--------------------------------------------------------------------------
|
|	Transformamos o resultado em array ou object para retorno
|
|--------------------------------------------------------------------------
*/

		public function process_result($result, $alias = null){
			$array = null;
			$obj = null;

			while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
				$array[] = $row;
				$obj[] = $row;
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
				$this->_num_rows['response']	=  $this->stmt->rowCount();
				$this->fetch_array['response']	= $array??[];
				$this->obj['response']			= (object) $obj??[];
			} else {
				$this->_num_rows[$alias]		=  $this->stmt->rowCount();
				$this->fetch_array[$alias]		= $array??[];
				$this->obj[$alias]				= (object) $obj??[];
			}
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
        
        public function startProcessResult($result,$query,$_ALIAS){
			if(gettype($result)=='object'){
				if($_ALIAS==''){
					$this->process_result($result);
				}else{
					$this->process_result($result,$_ALIAS);
				}
			}
		}

/*
|--------------------------------------------------------------------------
|	SUBQUERY
|--------------------------------------------------------------------------
|
|	As funções separam o select atual, separam em SubQuerys e armazenam 
|	para utilização posterior
|
|--------------------------------------------------------------------------
*/
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


/*
|--------------------------------------------------------------------------
|	FUNÇÕES DE SET
|--------------------------------------------------------------------------
|
|	São funções que não retornam nada, apenas setam parametros 
|	para processamento da query posteriormente
|
|--------------------------------------------------------------------------
*/

		public function colum($P_COLUMNS = array(),$JSON=false){
			$this->set_colum($P_COLUMNS,$JSON);
		}

		public function set_colum($P_COLUMNS = array(),$JSON=false){
			if (is_null($this->setcolum)){$this->setcolum = array();}
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
			if (is_null($this->colum)) {
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

		public function group($colum){
			$this->group_by($colum);
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
			if (is_null($var)) {$var = '';}
			$this->InsertVars[$colum] = $this->preventMySQLInject($var);
			return $this;
		}

		public function set_insert_form($object){
			if(is_array($object)){
				foreach($object as $key => $var){
					if (is_null($var)) {$var = '';}
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
			return $this->set_ignore($dados);;
		}

		public function set_ignore($dados){
			$this->ignore = 'IGNORE';
			return $this;
		}

		public function on_duplicate(){
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

		public function distinct(){
			$this->DISTINCT = ' DISTINCT ';
			return $this;
		}

		public function join($join = "LEFT", $tabela=null, $on=null){
			if(is_null($this->rell)){
				$this->rell = ' '.$join . ' JOIN ' . $tabela . ' ON ' . $on;
			}else{
				$this->rell .= ' '.$join . ' JOIN ' . $tabela . ' ON ' . $on;
			}
			return $this;
		}

		public function like($coluna, $palavra_chave){
			array_push($this->Insert_like, ' LOWER('.$coluna.') LIKE LOWER("'.$palavra_chave.'")');
			return $this;
		}

		public function REGEXP($coluna, $palavra_chave){
			array_push($this->Insert_like, $coluna . ' REGEXP "' . ($palavra_chave) . '"');
			return $this;
		}

//--------------------------------------------------------------------------

		public function last_id(){
			return $this->connection->lastInsertId();
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

		public function jsonToArray($json){
			$array = json_decode($json, true);
			array_walk_recursive($array, function (&$value) {
				if (is_string($value)) {
					$value = htmlspecialchars_decode($value);
				}
			});
			return $array;
		}

		public function set_var($key,$value){
			$this->stmt =$this->connection->prepare('SET @'.$key.' := :value');
			$this->stmt->bindParam(':value', $value);
		}

		public function get_query($type = 'SELECT'){
			$_QUERY = '';
			if(!in_array(trim($type),['SELECT', 'INSERT', 'DELETE','UPDATE'])){
				throw new RuntimeException("->get_query() com parâmetro incorreto. Utilize 'SELECT', 'INSERT', 'DELETE' ou 'UPDATE'");
			}
			$_QUERY 	.= $type.' ';
			$_QUERY 	.= $this->DISTINCT	??	'';
			$_QUERY 	.= $this->colum		??	' * ';	
			$_QUERY 	.= ' FROM ';
			$_QUERY		.= $this->tableClass??'';
			$_QUERY		.= (!is_null($this->rell))? ' '.$this->rell . ' ' :'';
			$array_like	= (count($this->Insert_like) > 0) ? implode(' OR ', $this->Insert_like):"";

			if (!is_null($this->where) || (count($this->Insert_like) > 0)) {
				if (!is_null($this->where) && $array_like != "") {
					$this->where = $this->where . " AND ";
				}
				$not	= ($this->set_where_not_exist == true)		?	" NOT EXISTS "	:	"";
				$_QUERY .= ' WHERE' . $not . '(' . $this->where . '(' . $array_like . '))';
				$_QUERY		= str_replace('())', ')', $_QUERY);
			}
			$_QUERY .= (count($this->group)>0) 	?	' GROUP BY '.implode(',',$this->group).' ' :'' ;
			$_QUERY .= (!empty($this->order)) 	?	$this->order . ' ' : '';
			$_QUERY .= (!is_null($this->limit)) ?	$this->limit : '';

			return $_QUERY;
		}

	}
