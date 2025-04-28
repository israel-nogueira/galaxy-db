<?
    namespace IsraelNogueira\galaxyDB;
	use PDO;
	use Exception;
	trait addOns{
        /*
        |--------------------------------------------------------------------------
        |	
        |--------------------------------------------------------------------------
        |
        |	
        |
        |--------------------------------------------------------------------------
        */

         	public function dataTable(array $oAjaxData=[],$fail=null){

				// PREPARAMOS OS FILTROS DO DATATABLE
				$draw		= $oAjaxData['draw'] ?? 1;
				$colunas	= $oAjaxData['columns']??[];
				$start		= $oAjaxData['start']??0;
				$length		= $oAjaxData['length']??10;

				$search		= $oAjaxData['search']['value']??"";
				$order		= $oAjaxData['order'][0]['column'];
				$order		= $colunas[$order]['data'];
				$order		= [$order=>$oAjaxData['order'][0]['dir']]??"ID ASC";

				

				// RESGATAMOS O TOTAL DA BASE SEM FILTRO SEM NADA
				$numrow_sem_filtros			= clone $this;
				$numrow_sem_filtros->colum('COUNT(*) as TOTAL');
				$numrow_sem_filtros->prepare_select('param');
				$numrow_sem_filtros->transaction(function($e) {die($e);});
				$numrow_sem_filtros->execQuery();
				$_RESULT = $numrow_sem_filtros->fetch_array('param')[0]['TOTAL'] ?? [];
				$recordsTotal = ($_RESULT!=[]) ? $_RESULT : 0;

				
				//INSERE AS PALAVRAS DE SEARCH 
				$busca_por_coluna = false;
				foreach ($colunas as $value) {
					if($value['search']['value']!=""){
						if(!is_null($value['data']) && $value['data']!="NULL"){
							$busca_por_coluna = true;
							$this->like($value['data'],'%'.trim(preg_replace('/[\s]+/mu', '%',trim($value['search']['value']))).'%');
						}
					}
				}
				if(strlen($search)>0){
					foreach($colunas as $value) {
						if(!is_null($value['data']) && $value['data']!="NULL"){
							$this->like($value['data'],'%'.trim(preg_replace('/[\s]+/mu', '%',trim($search))).'%');
						}
					}
				}
				
				//CASO NÃO TENHA WHERES AINDA, ADICIONAMOS 
				if($this->where==null){$this->where(' TRUE ');}

				//POSSIVEIS WHERE DE FORMULARIOS
				// $this->where('AND 2=2');
				// $this->where('AND 3=3');

				//ORDENAMOS PELAS COLUNAS 
				foreach($order as  $key=>$value) {
					if($key){$this->order($key,$value);}
				}

				// TOTAL DE PESQUISA COM AS COLUNAS E PESQUISAS  
				if(strlen($search)>0 || $busca_por_coluna==true){
					$noPage = clone $this;
					$noPage->setcolum = [];
					$noPage->colum('COUNT(*) as TOTAL');
					$noPage->select();
					$fetch_array = $noPage->fetch_array()??[];
					$_FILTRADO 	= ($fetch_array!=[])?intVal($noPage->fetch_array()['response'][0]['TOTAL']):0;
				}else{
					$_FILTRADO 	= intVal($recordsTotal);
				}
				// AGORA TOTAL COM A PAGINAÇÃO 
				if($oAjaxData!=[]){
					$this->set_limit($start,$length);
				}



				$select_result = clone $this;
				$select_result->prepare_select('param');

				$select_result->transaction(function($e)use($fail){
					if(is_callable($fail)){ $fail($e); }
				});
				$select_result->execQuery();
				$_RESULT = $select_result->fetch_array('param');
	
				
				$_TOTAL		= intVal($recordsTotal);
				return [
					"query"				=>	$select_result->query['param'],
					"paginate"			=>	$start.' - '.$length,
					"draw"				=>	$draw,
					"recordsFiltered"	=>	$_FILTRADO,
					"recordsTotal"		=>	$_TOTAL,
					"data"				=>	$_RESULT ?? []
				];
			}


		/*
		|--------------------------------------------------------------------------
		|
		|--------------------------------------------------------------------------
		|
		|
		|
		|--------------------------------------------------------------------------
		*/

			public static function multi_language($_TABLE,$IDIOMAS=['pt','en','es']){
					$COLUNA_PRIMARY		=	null;
					$_MYSQL				=	new galaxyDB();
					$_MYSQL->connect();
					$_INDEX				=	$_MYSQL->getIndexes($_TABLE);
					$_COLUNAS			=	$_MYSQL->showDBColumns($_TABLE,true);
					$COLUNAS_FIELDS		=	$_MYSQL->showDBColumns($_TABLE,false);
					$_TABELA_TRANSLATE	=	$_TABLE.'__TRANSLATE';

				/*
				|--------------------------------------------------------------------
				|	LIMPA TUDO PRIMNEIRO
				|--------------------------------------------------------------------
				*/
					
						$QUERY	='ALTER TABLE '.$_TABLE.' DROP COLUMN IF EXISTS FW_LANG;';
						$QUERY .='ALTER TABLE '.$_TABLE.' DROP COLUMN IF EXISTS FW_UID_LANG;';
						$QUERY .='DROP TRIGGER IF EXISTS `INSERT_PRIMARY__'.$_TABLE.'`;';
						$QUERY .='DROP TRIGGER IF EXISTS `UPDATE__'.$_TABLE.'`;';
						$QUERY .="DROP TRIGGER IF EXISTS `INSERT__{$_TABLE}`;";				
						$QUERY .="DROP TRIGGER IF EXISTS `DELETE__{$_TABLE}`;";
						$QUERY .="DROP TRIGGER IF EXISTS `{$_TABLE}__SECURITY_INSERT`;";
						$QUERY .="DROP TRIGGER IF EXISTS `{$_TABLE}__SECURITY_UPDATE`;";
						$QUERY .="DROP TRIGGER IF EXISTS `{$_TABLE}__SECURITY_DELETE`;";
						$_MYSQL->connection->query($QUERY);
						
				/*
				|--------------------------------------------------------------------
				|	ISOLAMOS A COLUNA PRIMÁRIA
				|--------------------------------------------------------------------
				|
				| 	Existem bases que nem sempre o ID é a chave primaria
				|
				*/
						foreach ($_INDEX as $index) {
							if ($index['INDEX_NAME'] == 'PRIMARY') {
								$COLUNA_PRIMARY = $index['COLUMN_LIST'];
								break;
							}
						}				
						if(is_null($COLUNA_PRIMARY)){
							throw new Exception("Não foi encontrato INDEX PRIMARY KEY da tabela ".$_TABLE);							
						}

				/*
				|--------------------------------------------------------------------
				|	LIMPAMOS AS COLUNAS
				|--------------------------------------------------------------------
				|
				|	Aqui retiramos da lista a CHAVE PRIMÁRIA 
				|	Também retiramos a coluna da trigger "FW_LANG" "FW_UID_LANG"
				|
				|
				*/

					$COLUNAS_TRATADAS = $_COLUNAS;
					foreach ($COLUNAS_TRATADAS as $index=>$coluna) {
						if (
							$coluna['Field'] === $COLUNA_PRIMARY || 
							$coluna['Field'] === 'FW_UID_LANG' || 
							$coluna['Field'] === 'ID_FW_PAI' || 
							$coluna['Field'] === 'FW_LANG'
						) {
							unset($COLUNAS_TRATADAS[$index]);
						}
					}

				/*
				|--------------------------------------------------------------------
				|	PRIMEIRO TRANSFORMAMOS AS COLUNAS DA TABELA ORIGINAL PARA NULL
				|--------------------------------------------------------------------
				|
				|	todas as colunas precisarão aceitar NULL
				|
				*/
					
				foreach ($COLUNAS_TRATADAS as $COLUNA) {			
					if($COLUNA['Field']!='ID'){
						if($COLUNA['Default']=='current_timestamp()'){
							$default = 'CURRENT_TIMESTAMP';
						}elseif(is_numeric($COLUNA['Default'])){
							$default = $COLUNA['Default'];
						}elseif( $COLUNA['Null'] == 'YES'){
							$default = 'NULL';
						}else{
							$default = '"'.$COLUNA['Default'].'"';
						}
						$QUERY1 = 'ALTER TABLE `'.$_TABLE.'` CHANGE `'.$COLUNA['Field'].'` `'.$COLUNA['Field'].'` '.$COLUNA['Type'].' NULL DEFAULT '.$default.';';
						try {$_MYSQL->connection->query($QUERY1);} catch (\Throwable $th) {}
					}
				}

				/*
				|--------------------------------------------------------------------
				|	DA MESMA FORMA A COLUNA FW_LANG NA TABELA ORIGINAL
				|--------------------------------------------------------------------
				*/
			
					$_INSERE=	new galaxyDB();
					$_INSERE->connect();

					$QUERY 	= 'ALTER TABLE `'.$_TABLE.'` ADD COLUMN FW_LANG ENUM("'.implode('","',$IDIOMAS).'") DEFAULT "pt";'.PHP_EOL;
					$QUERY .= 'ALTER TABLE `'.$_TABLE.'` ADD COLUMN FW_UID_LANG BIGINT(25) DEFAULT NULL;';
					try {$_INSERE->connection->prepare($QUERY)->execute();} catch (\Throwable $th) {}


					$QUERY	= 'CREATE INDEX FW_UID_LANG ON '.$_TABLE.' (FW_UID_LANG);'.PHP_EOL;
					$QUERY .= 'CREATE INDEX FW_UID_LANG ON '.$_TABELA_TRANSLATE.' (FW_UID_LANG);'.PHP_EOL;
					try {$_INSERE->connection->prepare($QUERY)->execute();} catch (\Throwable $th) {
						throw new Exception($th);
					}



				/*
				|--------------------------------------------------------------------
				|	CRIAMOS A TABELA DE TRANSLATE CASO NÃO EXISTA
				|--------------------------------------------------------------------
				|
				|	varremos as colunas tratadas anteriormente
				|	e verificamos se ela ainda não existe na tabela de translate
				|	se nao existir, insere
				|
				*/

				
					$QUERY = 'CREATE TABLE IF NOT EXISTS ' . $_TABELA_TRANSLATE . ' (
						ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
						FW_LANG ENUM("' . implode('","', $IDIOMAS) . '") DEFAULT "pt",
						ID_FW_PAI INT,
						FW_UID_LANG BIGINT(25),
						UNIQUE KEY UNIQUE_FW_LANG (FW_LANG, FW_UID_LANG)
					);' . PHP_EOL;

					try {$_INSERE->connection->prepare($QUERY)->execute();} catch (\Throwable $th) {}
					
					
					foreach ($COLUNAS_TRATADAS as $COLUNA) {			
						if(
							$COLUNA['Field']!='ID' &&
							$COLUNA['Field']!='FW_LANG' &&
							$COLUNA['Field']!='FW_UID_LANG'
						){
							try {$_MYSQL->connection->query('ALTER TABLE `'.$_TABELA_TRANSLATE.'` ADD `'.$COLUNA['Field'].'` '.$COLUNA['Type'].';');} catch (\Throwable $th) {}
						}
					}

					

				/*
				|--------------------------------------------------------------------
				|	INICIAMOS A TRIGGER DE UPDATE
				|--------------------------------------------------------------------
				*/
				
						$TRIGGER_UPDATE	='	CREATE TRIGGER UPDATE__'.$_TABLE.' BEFORE UPDATE ON '.$_TABLE.' FOR EACH ROW';
						$TRIGGER_UPDATE	.='		BEGIN'.PHP_EOL;
						$TRIGGER_UPDATE	.='			DECLARE record_count INT;'.PHP_EOL;
						$TRIGGER_UPDATE .= "		IF @atualiza_id = 1 THEN".PHP_EOL;
						$TRIGGER_UPDATE .= "			SET @atualiza_id = 0;".PHP_EOL;
						$TRIGGER_UPDATE .= "		ELSE" . PHP_EOL. PHP_EOL;
						$TRIGGER_UPDATE .='				SET @permitir_atualizacao = 1;'.PHP_EOL;
						$TRIGGER_UPDATE .='				SELECT COUNT(*) INTO @record_count FROM '.$_TABELA_TRANSLATE.' WHERE FW_UID_LANG=NEW.FW_UID_LANG AND FW_LANG=NEW.FW_LANG;'.PHP_EOL;
						$TRIGGER_UPDATE .='				IF @record_count > 0 THEN'.PHP_EOL;
						$TRIGGER_UPDATE .='					UPDATE '.$_TABELA_TRANSLATE.' SET '.PHP_EOL;
						$COLUNAS_DECLARADAS =[]; 
						foreach ($COLUNAS_TRATADAS as $COLUNA) {
							if($COLUNA['Field']!=$COLUNA_PRIMARY && $COLUNA['Field']!='FW_UID_LANG'){
									$COLUNAS_DECLARADAS[]=$COLUNA['Field'].'=IF(NEW.'.$COLUNA['Field'].' IS NULL , '.$COLUNA['Field'].', NEW.'.$COLUNA['Field'].')'.PHP_EOL;
								}
							}
							$TRIGGER_UPDATE .= implode(',',$COLUNAS_DECLARADAS);
							$TRIGGER_UPDATE .=' WHERE FW_UID_LANG=NEW.FW_UID_LANG AND FW_LANG=NEW.FW_LANG; '.PHP_EOL;					
							$TRIGGER_UPDATE .='			ELSE'.PHP_EOL;
							$TRIGGER_UPDATE .='				INSERT INTO '.$_TABELA_TRANSLATE.' ( '.implode(',',$COLUNAS_FIELDS).', FW_UID_LANG, FW_LANG) VALUES ( NEW.'.IMPLODE(',NEW.',$COLUNAS_FIELDS).',NEW.FW_UID_LANG, NEW.FW_LANG);'.PHP_EOL;
							$TRIGGER_UPDATE .='			END IF;'.PHP_EOL;
							foreach ($COLUNAS_TRATADAS as $COLUNA) {
								if($COLUNA['Field']!=$COLUNA_PRIMARY){
									$TRIGGER_UPDATE.='			SET NEW.'.$COLUNA['Field'].'=NULL;'.PHP_EOL;
								}
							}
						$TRIGGER_UPDATE .= "			END IF;" . PHP_EOL. PHP_EOL;
						$TRIGGER_UPDATE .='		END;'.PHP_EOL.PHP_EOL;
						
						try {$_MYSQL->connection->prepare($TRIGGER_UPDATE)->execute();} catch (\Throwable $th) {throw new Exception($th);}

						$SECURITY_UPDATE ="	CREATE TRIGGER {$_TABLE}__SECURITY_UPDATE BEFORE UPDATE ON {$_TABELA_TRANSLATE}";
							$SECURITY_UPDATE .="	FOR EACH ROW";
							$SECURITY_UPDATE .="	BEGIN";
							$SECURITY_UPDATE .= "		DECLARE mensagem_erro VARCHAR(255);" . PHP_EOL;
							$SECURITY_UPDATE .= "		SET mensagem_erro = '0';" . PHP_EOL;
							$SECURITY_UPDATE .= "		IF @permitir_atualizacao=1 THEN " . PHP_EOL;
							$SECURITY_UPDATE .= "			SET mensagem_erro = '';" . PHP_EOL;
							$SECURITY_UPDATE .= "		ELSE" . PHP_EOL;
							$SECURITY_UPDATE .= "			SET mensagem_erro = 'ATENÇÃO! Faça alterações APENAS pela tabela principal.';" . PHP_EOL;
							$SECURITY_UPDATE .= "			SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = mensagem_erro;" . PHP_EOL;
							$SECURITY_UPDATE .= "		END IF;" . PHP_EOL;
							$SECURITY_UPDATE .="	END;".PHP_EOL;

						try {$_MYSQL->connection->prepare($SECURITY_UPDATE)->execute();} catch (\Throwable $th) {throw new Exception($th);}


				/*
				|--------------------------------------------------------------------
				|	INICIAMOS A TRIGGER DE INSERT
				|--------------------------------------------------------------------
				*/


					$INSERT_PRIMARY	 ='	CREATE TRIGGER INSERT_PRIMARY__'.$_TABLE.' AFTER INSERT ON '.$_TABLE.' FOR EACH ROW';
					$INSERT_PRIMARY	.='	BEGIN'.PHP_EOL;
					$INSERT_PRIMARY .='		SET @permitir_atualizacao = 1;'.PHP_EOL;
					$INSERT_PRIMARY .='		SET @atualiza_id = 1;'.PHP_EOL;
					$INSERT_PRIMARY .='		UPDATE '.$_TABELA_TRANSLATE.' SET ID_FW_PAI=NEW.'.$COLUNA_PRIMARY.' WHERE(FW_UID_LANG=NEW.FW_UID_LANG);'.PHP_EOL;
					$INSERT_PRIMARY .='	END;'.PHP_EOL.PHP_EOL;

					try {$_MYSQL->connection->prepare($INSERT_PRIMARY)->execute();} catch (\Throwable $th) {throw new Exception($th);}


					$TRIGGER_INSERT  ="	CREATE TRIGGER INSERT__{$_TABLE} BEFORE INSERT ON {$_TABLE}".PHP_EOL;
					$TRIGGER_INSERT .="		FOR EACH ROW BEGIN".PHP_EOL;
					$TRIGGER_INSERT .= '		SET @permitir_atualizacao= 1;' . PHP_EOL;
					$TRIGGER_INSERT .= '		SET new.FW_UID_LANG=UUID_SHORT();' . PHP_EOL;
					
					// CRIAMOS AS LINGUAGENS VAZIAS 
					foreach ($IDIOMAS as $value) {$TRIGGER_INSERT .="			INSERT INTO {$_TABELA_TRANSLATE} (FW_UID_LANG, FW_LANG) VALUES (NEW.FW_UID_LANG, '$value');".PHP_EOL;}
					// E DA UPDATE NO REGISTRO INSERIDO 
					$TRIGGER_INSERT .=PHP_EOL.'			UPDATE '.$_TABELA_TRANSLATE.' SET ';
					$COLUNAS_DECLARADAS =[]; 
					foreach ($COLUNAS_TRATADAS as $COLUNA) {
						if($COLUNA['Field']!=$COLUNA_PRIMARY){	
							$COLUNAS_DECLARADAS[]=$COLUNA['Field'].'=NEW.'.$COLUNA['Field'];
						}
					}
					$TRIGGER_INSERT .= implode(',',$COLUNAS_DECLARADAS);
					$TRIGGER_INSERT .=' WHERE FW_UID_LANG = new.FW_UID_LANG AND FW_LANG=NEW.FW_LANG; '.PHP_EOL;					
					
					foreach ($COLUNAS_TRATADAS as $COLUNA) {
						if($COLUNA['Field']!=$COLUNA_PRIMARY){
							$TRIGGER_INSERT.='			SET NEW.'.$COLUNA['Field'].'=DEFAULT;'.PHP_EOL;
						}
					}

					$TRIGGER_INSERT .="	END;".PHP_EOL.PHP_EOL;
					try {$_MYSQL->connection->prepare($TRIGGER_INSERT)->execute();} catch (\Throwable $th) {throw new Exception($th);}


					$SECURITY_INSERT ="	CREATE TRIGGER {$_TABLE}__SECURITY_INSERT BEFORE INSERT ON {$_TABELA_TRANSLATE}";
					$SECURITY_INSERT .="	FOR EACH ROW";
					$SECURITY_INSERT .="	BEGIN";
					$SECURITY_INSERT .="		DECLARE mensagem_erro VARCHAR(255);".PHP_EOL;
					$SECURITY_INSERT .="		SET mensagem_erro = '0';".PHP_EOL;
					$SECURITY_INSERT .="		IF @permitir_atualizacao=1 THEN ".PHP_EOL;
					$SECURITY_INSERT .="			SET mensagem_erro = '';".PHP_EOL;
					$SECURITY_INSERT .="		ELSE".PHP_EOL;
					$SECURITY_INSERT .="			SET mensagem_erro = 'ATENÇÃO! Faça alterações APENAS pela tabela principal.';".PHP_EOL;
					$SECURITY_INSERT .="			SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = mensagem_erro;".PHP_EOL;
					$SECURITY_INSERT .="		END IF;".PHP_EOL;
					$SECURITY_INSERT .="	END;".PHP_EOL;
					try {$_MYSQL->connection->prepare($SECURITY_INSERT)->execute();} catch (\Throwable $th) {throw new Exception($th);}

				/*
				|--------------------------------------------------------------------
				|    INICIAMOS A TRIGGER DE DELETE
				|--------------------------------------------------------------------
				*/

					$TRIGGER_DELETE ="	CREATE TRIGGER DELETE__{$_TABLE} AFTER DELETE ON {$_TABLE} FOR EACH ROW BEGIN ".PHP_EOL;
					$TRIGGER_DELETE .='	SET @permitir_atualizacao=1;'.PHP_EOL;
					$TRIGGER_DELETE .="	DELETE FROM {$_TABELA_TRANSLATE} WHERE FW_UID_LANG=OLD.FW_UID_LANG; ".PHP_EOL;
					$TRIGGER_DELETE .="	END;".PHP_EOL.PHP_EOL;
					try {$_MYSQL->connection->prepare($TRIGGER_DELETE)->execute();} catch (\Throwable $th) {throw new Exception($th);}

					$SECURITY_DELETE ="	CREATE TRIGGER {$_TABLE}__SECURITY_DELETE BEFORE DELETE ON {$_TABELA_TRANSLATE}".PHP_EOL;
					$SECURITY_DELETE .="	FOR EACH ROW".PHP_EOL;
					$SECURITY_DELETE .="	BEGIN".PHP_EOL;
					$SECURITY_DELETE .="		DECLARE mensagem_erro VARCHAR(255);".PHP_EOL;
					$SECURITY_DELETE .="		SET mensagem_erro = '0';".PHP_EOL;
					$SECURITY_DELETE .="		IF @permitir_atualizacao=1 THEN ".PHP_EOL;
					$SECURITY_DELETE .="			SET mensagem_erro = '';".PHP_EOL;
					$SECURITY_DELETE .="		ELSE".PHP_EOL;
					$SECURITY_DELETE .="			SET mensagem_erro = 'ATENÇÃO! Faça alterações APENAS pela tabela principal.';".PHP_EOL;
					$SECURITY_DELETE .="			SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = mensagem_erro;".PHP_EOL;
					$SECURITY_DELETE .="		END IF;".PHP_EOL;
					$SECURITY_DELETE .="	END;".PHP_EOL;
					try {$_MYSQL->connection->prepare($SECURITY_DELETE)->execute();} catch (\Throwable $th) {throw new Exception($th);}

			}




    }