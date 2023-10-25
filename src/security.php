<?php
    declare(strict_types = 1);
    namespace IsraelNogueira\galaxyDB;
	use RuntimeException;
	use Exception;
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
		|	ISCRYPT
		|--------------------------------------------------------------------
		|
		|	Transformamos as entradas e saídas criptografas
		|	Na gravação / update criptografamos
		|	E na leitura, descryptografamos
		|
		|--------------------------------------------------------------------
		|
		|	É necessário cadastrar uma senha 
		|	no arquivo .ENV na raiz do projeto
		|
		|--------------------------------------------------------------------
		*/

		public function isCrypt() {
			$this->isCrypt = true;
			return $this;
		}
		/*
		|--------------------------------------------------------------------
		|	ESCAPE
		|--------------------------------------------------------------------
		|
		|	Transformamos as entradas em base64encode
		|	antes da gravação/update
		|	E na base, colamos limpo
		|--------------------------------------------------------------------
		*/

		public function escape() {
			$this->isEscape = true;
			return $this;
		}

		/*
		|--------------------------------------------------------------------------------------- 
		|	CRIPTOGRAFIA
		|--------------------------------------------------------------------------------------- 
		|	
		|	A função crypta é responsável por criptografar os dados de inserção usando a cifra AES-256-CBC, 
		|	que é considerada uma das mais seguras e amplamente utilizadas em criptografia simétrica. 
		|	Ela recebe um parâmetro $data contendo os dados a serem criptografados e retorna o resultado da criptografia.
		|
		*/
		private function crypta($data) 
		{	
			$this->isCrypt = false;
			$crypt = openssl_encrypt($data, 'aes-256-cbc', getEnv('GALAXY_CRYPT_KEY'), 0, getEnv('GALAXY_CRYPT_IV'));
			if ($crypt === false) {
				return $data;
			} else {
				return $crypt;
			}
		}

		/*
		|------------------------------------------------------------------------------------------------------- 
		|	DECRIPTOGRAFIA
		|------------------------------------------------------------------------------------------------------- 
		|	
		|	Já a função decrypta é responsável por descriptografar os dados da base que foram criptografados 
		|	pela função crypta. Ela também utiliza a cifra AES-256-CBC e recebe um parâmetro $data contendo 
		|	os dados criptografados. A função retorna os dados originais após a descriptografia.
		|
		*/
		private function decrypta($data) 
		{ 
			$this->isCrypt = false;
			$crypt = openssl_decrypt($data, 'aes-256-cbc', getEnv('GALAXY_CRYPT_KEY'), 0, getEnv('GALAXY_CRYPT_IV'));
			if ($crypt === false) {
				return $data;
			} else {
				return $crypt;
			}
		}



		/*
		|--------------------------------------------------------------------
		|	VERIFICA COLUNAS PERMITIDAS
		|--------------------------------------------------------------------
		|
		|	Aqui verificamos as colunas que tem permissão 
		|	para serem acessadas
		|
		*/

		public function verifyJoinColums($expression) {

			$pattern = '/([a-zA-Z0-9_]+\.[a-zA-Z0-9_]+)/';
			preg_match_all($pattern, $expression, $matches);
			$_COLUNAS_QUERY = $matches[1];
						
			$colunasPermitidas = $this->columnsEnab		??[];
			$colunasBloqueadas = $this->columnsBlock	??[];
			$colunasEscolhidas = $_COLUNAS_QUERY;
			$colunasInvalidas = array_diff($colunasEscolhidas, $colunasPermitidas);
			$colunasBloqueadasEncontradas = array_intersect($colunasEscolhidas, $colunasBloqueadas);
			
			if (!empty($colunasInvalidas) && count($colunasPermitidas)>0) {
				throw new Exception("Colunas inválidas selecionadas: " . implode(', ', $colunasInvalidas), 1);
				
			} elseif (!empty($colunasBloqueadasEncontradas) && count($colunasBloqueadas)>0) {
				throw new Exception("Colunas bloqueadas selecionadas: " . implode(', ', $colunasBloqueadasEncontradas), 1);
			} 
			
			return $expression;
		}

		public function verifyindividualColum($_COLUNA){

			if(is_null($this->tableClass)){
				throw new RuntimeException("É necessário pelo menos uma tabela ou query cadastradas");
			}

			if(
				in_array($_COLUNA , $this->columnsBlock)||
				count($this->columnsEnab??[])>0 && !in_array($matches[1] , $this->columnsEnab)
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
			if(count($this->columnsEnab??[])>0){
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
				$params = $matches[2]??'';

				if (
						(
							count($this->mysqlFnBlockClass)>0 && in_array($funcao,$this->mysqlFnBlockClass)

						) || (

							count($this->mysqlFnEnabClass)>0 && !in_array($funcao,$this->mysqlFnEnabClass)
							
						) 
					) {
					return ['function' => '', 'params' =>"(NULL)"];
				}else{
					return ['function' => $funcao, 'params' => $params];
				}
			}
			return false;
		}


		public function functionVerifyString($STRING_COLUNA){

			preg_match_all('/\b(\w+)\s*\(/i', strval($STRING_COLUNA), $matches);

			$LISTA_FUNCTIONS	= $matches[1]??[];	
			$arrayBloqueio		= count(($this->mysqlFnBlockClass??[]))	> 0;
			$arrayLiberados		= count(($this->mysqlFnEnabClass??[]))	> 0;
			$bloqueados			= count(array_intersect($LISTA_FUNCTIONS, ($this->mysqlFnBlockClass??[]))) >0;
			$liberados			= count(array_intersect($LISTA_FUNCTIONS, ($this->mysqlFnEnabClass??[])))  >0;	

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
