<?php
    declare(strict_types = 1);
    namespace IsraelNogueira\galaxyDB;
	use RuntimeException;
	/*
	|--------------------------------------------------------------------------
	|		GENERAL BASE
	|--------------------------------------------------------------------------
	|   Retorna dados gerais da nossa base
	|--------------------------------------------------------------------------
	*/
    trait security{
		/*
		|--------------------------------------------------------------------
		|	VERIFICA COLUNAS PERMITIDAS
		|--------------------------------------------------------------------
		|
		|	Aqui verificamos as colunas que tem permissão 
		|	para serem acessadas
		|
		*/
		public function verifyindividualColum($_COLUNA){


			if(is_null($this->tableClass)){
				throw new RuntimeException("É necessário pelo menos uma tabela ou query cadastradas");
			}

			if(
				in_array($_COLUNA , $this->columnsBlock)||
				count($this->columnsEnab)>0 && !in_array($matches[1] , $this->columnsEnab)
			){
				return false;
			}

			
			return $_COLUNA;//str_replace(',','¸',$colum);			
		}
		public function verifyColunms(){
			if(is_null($this->tableClass)){
				throw new RuntimeException("É necessário pelo menos uma tabela ou query cadastradas");
			}
			if(is_null($this->colum)){				
				$_COLUNAS_QUERY = $this->showDBColumns($this->tableClass);
			}else{
				$_COLUNAS_QUERY = explode(',',$this->colum);				
			}
			$result = $_COLUNAS_QUERY;
			if(count($this->columnsBlock)>0){
				$result = array_diff($_COLUNAS_QUERY, $this->columnsBlock);
			}
			if(count($this->columnsEnab)>0){
				$result = array_intersect($result,$this->columnsEnab);
			}
			$_colunas = [];
			foreach ($result as $value) {
				$_verify = $this->functionVerifyString($value);
				if($_verify!=false){
					$_colunas[] = $_verify;
				}
			}
			return str_replace('¸',',',implode(',',$_colunas));			
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
		public function functionVerifyArray($str)
		{
			if (preg_match('/(\w+)\s*\((.*)\)/', $str, $matches)) {
				$funcao = $matches[1]??'';
				if (
						(
							count($this->mysqlFnBlockClass)>0 && in_array($funcao,$this->mysqlFnBlockClass)

						) || (

							count($this->mysqlFnEnabClass)>0 && !in_array($funcao,$this->mysqlFnEnabClass)
							
						) 
					) {
					return ['function' => '', 'params' =>"(NULL)"];
				}else{
					return ['function' => $funcao, 'params' => isset($matches[2]) ? $matches[2] :""];
				}
			}
			return false;
		}


		public function functionVerifyString($STRING_COLUNA){
			preg_match_all('/\b(\w+)\s*\(/i', $STRING_COLUNA, $matches);
			$LISTA_FUNCTIONS	= $matches[1];	
			$arrayBloqueio		= count($this->mysqlFnBlockClass)	> 0;
			$arrayLiberados		= count($this->mysqlFnEnabClass)	> 0;
			$bloqueados			= count(array_intersect($LISTA_FUNCTIONS, $this->mysqlFnBlockClass)) >0;
			$liberados			= count(array_intersect($LISTA_FUNCTIONS, $this->mysqlFnEnabClass))  >0;			
			if ($bloqueados  || ($arrayLiberados && !$liberados)){
				return false;
			}else{
				return 	$STRING_COLUNA;	
			}
			return false;
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

		public function preventMySQLInject($string)
			{	
				// echo '---------->'. $string;
				// $search = array('@',';','*','?','|','+','%','(',')','[',']','{','}' ); 
				// // $search = array_merge($search,['<','-','\\','>','=', "'",'"','/']);
				// $input = @str_replace($search, '', $string);
				return $string;
			}

				

    }
