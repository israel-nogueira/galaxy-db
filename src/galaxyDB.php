<?php

	namespace IsraelNogueira\galaxyDB;
	use IsraelNogueira\galaxyDB\connection;
	use IsraelNogueira\galaxyDB\queryBuilder;
	use IsraelNogueira\galaxyDB\actions;
	use IsraelNogueira\galaxyDB\log;
	use RuntimeException;
	use ReflectionClass;

/**
 * -------------------------------------------------------------------------
 *		@author Israel Nogueira <israel@feats.com>
 *		@package library
 *		@license GPL-3.0-or-later
 *		@copyright 2023 Israel Nogueira
 * -------------------------------------------------------------------------
 * 
 * 		Classe galaxyDB para base de dados: 
 * 		mysql | pgsql | sqlite | ibase | fbird | oracle | mssql | dblib | sqlsrv
 * 
 * -------------------------------------------------------------------------
 */


	class galaxyDB{
		use connection;
		use generalBase;
		use security;
		use queryBuilder;
		use actions;
		use addOns;
		use log;
		private $initialized = false;
		protected $customConnectData;
		public static $dbaseType;


		public function __construct($conn=null){
			$this->_last_id				=[];
			$this->_num_rows			=[];
			$this->SP_OUTS				=[];
			$this->SP_OUTPUTS			=[];
			$this->SP_NEW_PARAMS		=[];
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
			$this->setwhere				= null;
			$this->setHaving			= null;
			$this->where				= null;
			$this->having				= null;
			$this->set_where_not_exist	= null;
			$this->settable				= [];
			$this->tableClass			= null;
			$this->SP_RETURN			= null;
			$this->sp_response			= [];
			$this->SP					= null;
			$this->SP_PARAMS			= null;
			$this->view_query			= null;
			$this->CONECT_PARAMS		= [];
			$this->transactionFn		= false;
			$this->rollbackFn			= false;
			$this->isCrypt				= false;
			$this->isEscape				= false;
			$this->isCommand			= false;
			$this->prepareDeCrypt		= [];
			$this->prepareCrypt			= false;
			$this->colunmToJson			= [];
			$this->subQueryAlias		= null;
			$this->subQuery				= [];
			$this->columnsBlock			= [];
			$this->columnsEnabl			= [];
			$this->colum				= null;
			$this->_QUERY				= '';
			$this->setorder				= [];
			$this->order				= null;
			$this->mysqlFnBlockClass	= [];
			$this->mysqlFnEnabClass		= [];
			$this->limit				= null;
			$this->stmt					= null;
			$this->logFile 				= realpath(__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..').DIRECTORY_SEPARATOR.'galaxyDB'.DIRECTORY_SEPARATOR.'galaxy.log';
			$customConnectData = null;
			if (basename(get_class($this)) != "galaxyDB") {
				$this->extended();

				if (property_exists($this, 'customConnectData') && is_array($this->customConnectData) && !empty($this->customConnectData)) {
					$customConnectData =  $this->customConnectData;
				}
			}
			if (is_array($conn) && ! empty($conn)) {
				$customConnectData = $conn;
			}
			$this->connection = $this->connect($customConnectData);
			$this->initialized	= true;
			return $this;
		}

	/*
	|--------------------------------------------------------------------------
	|		__SET
	|--------------------------------------------------------------------------
	| 
	|	Utilizo basicamente para dar um valor as colunas;
	|	Pode ser usado para Inserts ou Updates
	|	Ex: $param->nome_da_coluna = "string";
	|
	|
	*/

		public function __set($name, $value) {
			$VAR_CARREGADAS = array_keys(get_mangled_object_vars($this));
			if($this->initialized && !in_array($name,$VAR_CARREGADAS)){

				// coloquei antes, pq a função "set_insert" transforma em false ao final
				// então copio e seto novamente depois o valor
				$crypt		= $this->isCrypt;
				$isEscape	= $this->isEscape;
				$isCommand	= $this->isCommand;

				$this->set_insert($name, $value); 

				// setamos novamente com o valor antigo
				$this->isCrypt	=$crypt;
				$this->isEscape	=$isEscape;
				$this->isCommand=$isCommand;
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
	*/
		public function __call($_name, $arguments){

			if (in_array($_name, (get_class_methods(get_called_class())??[]))) {
				return $this->$_name(...$arguments);
			} else {
				if (substr(strtolower($_name), 0, 3) == 'sp_') {
					$this->sp(substr($_name,3), $arguments);
					return $this;
				} else {
					// die(\app\system\lib\system::ajaxReturn('MariaDB error: Função '.$_name.' desconhecida',0));;
					throw new RuntimeException('MariaDB error: Função '.$_name.' desconhecida');
				}
			}
		}

	/*
	|--------------------------------------------------------------------------
	|	STATIC
	|--------------------------------------------------------------------------
	|
	|	Aqui apenas caso a pessoa queira utilizar a classe estaticamente
	|	Ex: return meuModel::static()->table('minha_tabela')->select();
	|
	|
	*/

		static public function static() {
			return new static;
		}

	/*
	|--------------------------------------------------------------------------
	|		PARÂMETROS DE CONFIGURAÇÃO
	|--------------------------------------------------------------------------
	|
	|	Na model, temos alguns parametros pré-preparados
	|	Aqui nós acessamos eles e setamos na classe mãe
	|
	|
	*/
	
		public function extended() 
		{
			if (get_parent_class($this) !== false) {
				$this->tableClass				= $this->gExtnd('table',null);
				$this->columnsBlock				= $this->gExtnd('columnsBlocked',[]);
				$this->columnsEnab				= $this->gExtnd('columnsEnabled',[]);
				$this->mysqlFnBlockClass		= $this->gExtnd('functionsBlocked',[]);
				$this->mysqlFnEnabClass			= $this->gExtnd('functionsEnabled',[]);
				$this->customConnectData		= $this->gExtnd('customConnectData',[]);
			} else {
				return false;
			}
		}

		public function gExtnd($_param, $default = null) {
			$reflection = new ReflectionClass($this);
			
			while ($reflection) {
				$properties = $reflection->getDefaultProperties();
				
				if (array_key_exists($_param, $properties)) {
					return $this->$_param ?? $default;
				}
				
				$reflection = $reflection->getParentClass();
			}
			
			return $default;
		}


		// public function gExtnd($_param, $default = null)		{
		// 	$reflection = new ReflectionClass($this);		
		// 	while ($reflection !== false) {
		// 		if ($reflection->hasProperty($_param)) {
		// 			$property = $reflection->getProperty($_param);
		// 			$property->setAccessible(true);
		// 			return $property->getValue($this) ?? $default;
		// 		}
		// 		$reflection = $reflection->getParentClass();
		// 	}
		// 	return $default;
		// }

	}
