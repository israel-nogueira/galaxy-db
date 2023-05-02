<?php
	namespace IsraelNogueira\galaxyDB;
	use IsraelNogueira\galaxyDB\connection;
	use IsraelNogueira\galaxyDB\queryBuilder;
	use IsraelNogueira\galaxyDB\actions;
	use PDO;
	use Exception;
	use RuntimeException;
	use ReflectionClass;
	use InvalidArgumentException;
	use PDOException;

    define('DB_HOST', 	'localhost');
    define('DB_PORT', 	'3306');
    define('DB_DATABASE', 	'FW_PADRAO');
    define('DB_TYPE', 	'mysql');
    define('DB_USERNAME', 	'root');
    define('DB_PASSWORD',   '');
    define('DB_CHAR',   	'');
    define('DB_FLOW',   	'');
    define('DB_FKEY',   	'');

/**
 * -------------------------------------------------------------------------
 *		@author Israel Nogueira <israel@feats.com>
 *		@package library
 *		@license GPL-3.0-or-later
 *		@copyright 2023 Israel Nogueira
 * -------------------------------------------------------------------------
 * 
 * 		Classe galaxyDB para base de dados: 
 * 		Ainda não funciona todas as bases, mas implementarei aos poucos
 * 	
 *  	Plano é suportar as seguintes conexões:
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
		private $initialized = false;
		public static $dbaseType;


		public function __construct(){
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
	|	Utilizo basicamente para dar um valor as colunas;
	|	Pode ser usado para Inserts ou Updates
	|	Ex: $param->nome_da_coluna = "string";
	|
	|
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
	*/
		public function __call($_name, $arguments){

			if (in_array($_name, (get_class_methods(get_called_class())??[]))) {
				return $this->$_name(...$arguments);
			} else {
				if (substr(strtolower($_name), 0, 3) == 'sp_') {
					$this->sp(substr($_name,3), $arguments);
					return $this;
				} else {
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




	}
