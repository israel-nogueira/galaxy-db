<?
    namespace IsraelNogueira\galaxyDB;
	use PDO;
	use RuntimeException;

    trait queryBuilder{


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

                foreach ($result->fetchAll(PDO::FETCH_ASSOC) as  $value) {
                    $array[]	= $value;
                    $obj[]		= (object)$value;
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

		/*
		|--------------------------------------------------------------------
		|	FETCH_ARRAY
		|--------------------------------------------------------------------
		|
		|	Retorna a array associativa do resultado de uma query
		|
		|
		*/
            public function fetch_array($variable = null){
                if ($variable != null) {
                    return $this->fetch_array[$variable] ??[];
                } else {
                    return $this->fetch_array ??[];
                }
            }

		/*
		|--------------------------------------------------------------------
		|	FETCH_OBJ
		|--------------------------------------------------------------------
		|
		|	Retorna um objeto do resultado de uma query
		|
		*/
            public function fetch_obj($variable = null){
                if ($variable != null) {
                    return json_encode($this->fetch_array[$variable],JSON_BIGINT_AS_STRING)??[];
                } else {
                    return json_encode($this->fetch_array,JSON_BIGINT_AS_STRING)??[];
                }
            }

		/*
		|--------------------------------------------------------------------
		|	STARTPROCESSRESULT
		|--------------------------------------------------------------------
		|
		|	Apenas dá start no processamento do resultado
		|	enviando ou não o Alias enviado pelo PDO
		|
		*/
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
	|	Documentação:
	|	https://github.com/israel-nogueira/galaxy-db#sub-selects
    |
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


		/*
		|--------------------------------------------------------------------
		|	COLUMSUBQUERY
		|--------------------------------------------------------------------
		|
		|	As funções separam o select atual, separam em SubQuerys e armazenam 
    	|	para utilização posterior
		|
		|	Documentação:
		|	https://github.com/israel-nogueira/galaxy-db#sub-selects
		|
		*/

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

		/*
		|--------------------------------------------------------------------
		|	SETSUBQUERY
		|--------------------------------------------------------------------
		|
		|	Responsável em armazenar a query para pós utilização
		|
		|	Documentação:
		|	https://github.com/israel-nogueira/galaxy-db#sub-selects
		|
		*/
		public function setSubQuery($subQuery = null){
			if ($subQuery == null) {
				throw new RuntimeException("Parâmetro incorreto ou inexistente: ->setSubQuery()");
			} else {
				$this->subQuery[$subQuery]	= $this->get_query();
				$this->clear();
			}
			return $this;
		}

		/*
		|--------------------------------------------------------------------
		|	QUERY
		|--------------------------------------------------------------------
		|
		|	Basicamente executa o comando MySQL
		|
		*/
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
    |	SET_COLUM / COLUM
    |--------------------------------------------------------------------------
    |
    |	Cadastra as colunas no local correto para serem utilizadas dentro 
	|	das queries de cada consulta ou comando
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
					$COLUMNS = str_replace(',','¸',$COLUMNS);

					if (is_string($COLUMNS) && $COLUMNS != "") {
						$COLUMNS =  (substr($COLUMNS, 0, 8) == "command:")? substr($COLUMNS, 8):$COLUMNS;
						if($this->verifyindividualColum($COLUMNS)!=false){
							$_verify = $this->functionVerifyString($COLUMNS);
							if($_verify!=false){
								$this->setcolum[] = $_verify;
								if ($JSON == true) {
									if (stripos($COLUMNS, ' as ') > -1) {
										$this->colunmToJson[] = preg_split("/ as /i", $COLUMNS);
									} else {
										$this->colunmToJson[] = $COLUMNS;
									}
								}
							}
						}
						

					}
				}
			} elseif (is_string($P_COLUMNS) && $P_COLUMNS != "") {

				$P_COLUMNS	=	str_replace(',','¸',$P_COLUMNS);
				$COLUMNS	=	(substr($P_COLUMNS, 0, 8) == "command:")? substr($P_COLUMNS, 8):$P_COLUMNS;			
				if($this->verifyindividualColum($COLUMNS)!=false){
					$_verify = $this->functionVerifyString($COLUMNS);
					if($_verify!=false){
						$this->setcolum[] = $_verify;
						if($JSON==true){
							if(stripos($COLUMNS,' as ')>-1){
								$this->colunmToJson[]=preg_split("/ as /i", $COLUMNS);					
							}else{
								$this->colunmToJson[]=$COLUMNS;
							}
						}
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

    /*
    |--------------------------------------------------------------------------
    |	SET_TABLE / TABLE
    |--------------------------------------------------------------------------
    |
    |	Cadastra as tabelas no local correto para serem utilizadas dentro 
	|	das queries de cada consulta ou comando
    |
    |--------------------------------------------------------------------------
    */

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

		/*
		|--------------------------------------------------------------------
		|	SET_WHERE / WHERE / WHERE_NOT_EXIST
		|--------------------------------------------------------------------
		|
		|	Documentação simples
		|	https://dev.mysql.com/doc/refman/5.7/en/where-optimization.html
		|	https://dev.mysql.com/doc/refman/8.0/en/exists-and-not-exists-subqueries.html
		|
		*/

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

		public function where_not_exist(){
			$this->set_where_not_exist = true;
			return $this;
		}
		
		public function set_where_not_exist(){
			$this->set_where_not_exist = true;
			return $this;
		}

		/*
		|--------------------------------------------------------------------
		|	ORDER / SET_ORDER
		|--------------------------------------------------------------------
		|
		|	Documentação:
		|	https://dev.mysql.com/doc/refman/8.0/en/sorting-rows.html
		|
		|
		*/
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

		/*
		|--------------------------------------------------------------------
		|	GROUP / GROUP_BY
		|--------------------------------------------------------------------
		|
		|	Documentação:
		|	https://dev.mysql.com/doc/refman/5.7/en/group-by-handling.html
		|
		|
		*/
		public function group($colum){
			$this->group_by($colum);
			return $this;
		}
		
		public function group_by($colum){
			$this->group[] = $colum;
			return $this;
		}
		/*
		|--------------------------------------------------------------------
		|	LIMIT / SET_LIMIT
		|--------------------------------------------------------------------
		|
		|	Documentação:
		|	https://dev.mysql.com/doc/refman/8.0/en/limit-optimization.html
		|
		|
		*/
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

		/*
		|--------------------------------------------------------------------
		|	SET_INSERT
		|--------------------------------------------------------------------
		|
		|	Armazena em cache os dados de inserção de um registro da base
		|
		|	Documentação:
		|	https://dev.mysql.com/doc/refman/8.0/en/insert.html
		|
		*/
		public function set_insert($colum, $var){
			if (is_null($var)) {$var = '';}
			if($this->verifyindividualColum($colum)!=false){
				$_verify = $this->functionVerifyString($var);
				 if($_verify!==false){
					$this->InsertVars[$colum] = @$_verify;//$this->preventMySQLInject($_verify);
				 }
			 }
			return $this;
		}


		/*
		|--------------------------------------------------------------------
		|	SET_INSERT_OBJ
		|--------------------------------------------------------------------
		|
		|	Insere um conjunto de dados de um array
		|
		*/
		public function set_insert_obj($object){
			if(is_array($object)){
				foreach($object as $key => $var){
					if (is_null($var)) {$var = '';}
					$this->set_insert($key,$var);
				}
			}
			return $this;
		}
		/*
		|--------------------------------------------------------------------
		|	SET_UPDATE_OBJ
		|--------------------------------------------------------------------
		|
		|	Armazena em cache os dados de UPDATE de um registro da base
		|	utilizando um array
		|
		*/
		public function set_update_obj($object){
			if(is_array($object)){
				foreach($object as $key => $var){
					$this->set_update($key,$var);
				}
			}
			return $this;
		}

		/*
		|--------------------------------------------------------------------
		|	SET_UPDATE
		|--------------------------------------------------------------------
		|
		|	Armazena em cache os dados de UPDATE de um registro da base
		|
		*/

		public function set_update($colum, $var,$type=null){
			if($this->verifyindividualColum($colum)!=false){
				if (is_string($var)) {
					if (substr($var, 0, 8) == "command:") {
						$var = substr($var,8);
					}
					$_verify = $this->functionVerifyArray($var);
					if($_verify!==false){
						$var = $_verify['function'].(($_verify['function']!="")?'('.$_verify['params'].')':"NULL");
					}else{
						$var = '"' . $this->preventMySQLInject($var,$type) . '"';
					}				
				} else {
					$var = $this->preventMySQLInject($var,$type);
				}
				$this->Insert_Update[] =$colum . '=' . $var;
			}
			return $this;
		}
		/*
		|--------------------------------------------------------------------
		|	DEBUG
		|--------------------------------------------------------------------
		|
		|	Habilita / desabilita a saída de erros
		|	Default true
		|
		*/
		public function debug($bolean){
			$this->debug = $bolean;
			return $this;
		}

		/*
		|--------------------------------------------------------------------
		|	IGNORE / SET_IGNORE
		|--------------------------------------------------------------------
		|
		|	Documentação:
		|	https://dev.mysql.com/blog-archive/improvements-to-the-mysql-ignore-implementation/
		|
		*/
		public function ignore($dados){
			return $this->set_ignore($dados);;
		}

		public function set_ignore($dados){
			$this->ignore = 'IGNORE';
			return $this;
		}

		/*
		|--------------------------------------------------------------------
		|	ON_DUPLICATE
		|--------------------------------------------------------------------
		|
		|	https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html
		|
		*/

		public function on_duplicate(){
			$keyvalue = array();
			foreach ($this->InsertVars as $key => $value) {
				if ($value=='NULL') {
						$keyvalue[] = $key . 'NULL';
					}elseif(is_string($value)) {
						if (substr($value, 0, 8) == "command:") {$value = substr($value,8);} 
						$_verify = $this->functionVerifyArray($value);
						$keyvalue[] = $key.'='.$_verify['function'].(($_verify['function']!="")?'('.$_verify['params'].')':"NULL");//$this->preventMySQLInject($value)
				} else {
					$keyvalue[] = $key . "=" . $this->preventMySQLInject($value);
				}
			}
			$this->on_duplicate = ' ON DUPLICATE KEY UPDATE ' . implode(',', $keyvalue);
			return $this;
		}

		/*
		|--------------------------------------------------------------------
		|	DISTINCT
		|--------------------------------------------------------------------
		|
		|	https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html
		|
		*/
		public function distinct(){
			$this->DISTINCT = ' DISTINCT ';
			return $this;
		}

		/*
		|--------------------------------------------------------------------
		|	JOINS
		|--------------------------------------------------------------------
		|	Documentação popular
		|	https://pt.stackoverflow.com/questions/6441/qual-%C3%A9-a-diferen%C3%A7a-entre-inner-join-e-outer-join
		|
		|	Documentação oficial
		|	https://dev.mysql.com/doc/refman/5.7/en/join.html
		|
		*/

		public function innerJoin($tabela=null, $colunas=null){
		 return $this->join("INNER", $tabela, $colunas);
		}
		public function leftJoin($tabela=null, $colunas=null){
			return $this->join("LEFT", $tabela, $colunas);
			
		}
		public function rightJoin($tabela=null, $colunas=null){
			return $this->join("RIGHT", $tabela, $colunas);
			
		}
		public function fullOuterJoin($tabela=null, $colunas=null){
			return $this->join("FULL OUTER", $tabela, $colunas);
			
		}
		public function crossJoin($tabela=null, $colunas=null){
			return $this->join("CROSS", $tabela, $colunas);
		}

		public function join($join = "LEFT", $tabela=null, $colunas=null){
			if(is_null($this->rell)){
				$this->rell = ' '.$join . ' JOIN ' . $tabela . ' ON ' . $this->verifyJoinColums($colunas);
			}else{
				$this->rell .= ' '.$join . ' JOIN ' . $tabela . ' ON ' . $this->verifyJoinColums($colunas);
			}
			return $this;
		}

		/*
		|--------------------------------------------------------------------
		|	LIKE
		|--------------------------------------------------------------------
		|
		|	Documentação oficial:
		|	https://dev.mysql.com/doc/refman/5.7/en/string-comparison-functions.html#operator_like
		|
		|	Documentação simplificada:
		|	https://www.w3schools.com/mysql/mysql_like.asp
		|
		*/
			public function like($coluna, $palavra_chave){
				array_push($this->Insert_like, ' LOWER('.$coluna.') LIKE LOWER("'.$palavra_chave.'")');
				return $this;
			}

		/*
		|--------------------------------------------------------------------
		|	EXPRESSÕES REGULARES 
		|--------------------------------------------------------------------
		|
		|	Documentação oficial:
		|	https://dev.mysql.com/doc/refman/5.7/en/regexp.html
		|
		*/
			public function rlike($coluna, $palavra_chave){
				return $this->regexp($coluna, $palavra_chave);
			}

			public function regexp($coluna, $palavra_chave){
				array_push($this->Insert_like, $coluna . ' REGEXP "' . ($palavra_chave) . '"');
				return $this;
			}


		/*
		|--------------------------------------------------------------------
		|	RETORNA O ID DO ULTIMO ITEM ADICIONADO DA CONEXÃO
		|--------------------------------------------------------------------
		|
		|	Dpocumentação oficial:
		|	https://dev.mysql.com/doc/refman/8.0/en/information-functions.html#function_last-insert-id
		|
		|	Documentação simplificada:
		|	https://stackoverflow.com/questions/3837990/last-insert-id-mysql
		|
		*/

		public function last_id(){
			return $this->connection->lastInsertId();
		}

		/*
		|--------------------------------------------------------------------
		|	JSON TO ARRAY
		|--------------------------------------------------------------------
		|
		|	Percorre recurssivamente o JSON trazendo um array com as strings tratadas
		|
		|
		*/

		public function jsonToArray($json){
			$array = json_decode($json, true);
			array_walk_recursive($array, function (&$value) {
				if (is_string($value)) {
					$value = htmlspecialchars_decode($value);
				}
			});
			return $array;
		}

		/*
		|--------------------------------------------------------------------
		|	FUNÇÃO SET
		|--------------------------------------------------------------------
		|
		|	Documentação oficial:
		|	https://dev.mysql.com/doc/refman/5.7/en/set-variable.html
		|
		|
		*/
		public function set_var($key,$value){
			$this->stmt =$this->connection->prepare('SET @'.$key.' := :value');
			$this->stmt->bindParam(':value', $value);
		}
		
		/*
		|--------------------------------------------------------------------
		|	get_query
		|--------------------------------------------------------------------
		|
		|	Retorna a query completa
		|
		|
		*/
		
		public function get_query($type = 'SELECT'){
			$_QUERY = '';
			if(!in_array(trim($type),['SELECT', 'INSERT', 'DELETE','UPDATE'])){
				throw new RuntimeException("->get_query() com parâmetro incorreto. Utilize 'SELECT', 'INSERT', 'DELETE' ou 'UPDATE'");
			}
			$_QUERY 	.= $type.' ';
			$_QUERY 	.= $this->DISTINCT	??	'';
			$_QUERY 	.= $this->verifyColunms();	
			$_QUERY 	.= ' FROM ';
			$_QUERY		.= $this->tableClass??'';
			$_QUERY		.= (!is_null($this->rell))? ' '.$this->rell . ' ' :'';
			$array_like	 = (count($this->Insert_like) > 0) ? implode(' OR ', $this->Insert_like):"";

			if (!is_null($this->where) || (count($this->Insert_like) > 0)) {
				if (!is_null($this->where) && $array_like != "") {
					$this->where = $this->where . " AND ";
				}
				$not		= ($this->set_where_not_exist == true)		?	" NOT EXISTS "	:	"";
				$_QUERY		.= ' WHERE' . $not . '(' . $this->where . '(' . $array_like . '))';
				$_QUERY		= str_replace('())', ')', $_QUERY);
			}

			$_QUERY .= (count($this->group)>0) 	?	' GROUP BY '.implode(',',$this->group).' ' :'' ;
			$_QUERY .= (!empty($this->order)) 	?	$this->order . ' ' : '';
			$_QUERY .= (!is_null($this->limit)) ?	$this->limit : '';

			return $_QUERY;
		}



    }