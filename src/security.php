<?php
    declare(strict_types = 1);
    namespace IsraelNogueira\MysqlOrm;

	/*
	|--------------------------------------------------------------------------
	|		GENERAL BASE
	|--------------------------------------------------------------------------
	|   Retorna dados gerais da nossa base
	|--------------------------------------------------------------------------
	*/
    trait security{

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
				$search = array('@',';','*','?','|','+','%','(',')','[',']','{','}' ); 
				// $search = array_merge($search,['<','-','\\','>','=', "'",'"','/']);
				$input = str_replace($search, '', $string);
				return $input;
			}

				

    }
