<?
    namespace IsraelNogueira\galaxyDB;
	use PDO;
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
				$numrow_sem_filtros->set_colum('COUNT(*) as TOTAL');
				$numrow_sem_filtros->prepare_select('param');
				$numrow_sem_filtros->transaction(function($e) {die($e);});
				$numrow_sem_filtros->execQuery();
				$_RESULT = $numrow_sem_filtros->fetch_array('param')[0]['TOTAL'] ?? [];
				$recordsTotal = ($_RESULT!=[]) ? $_RESULT : 0;

				
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



				$select_result = clone $this;
				$select_result->prepare_select('param');

				$select_result->transaction(function($e)use($fail){
					if(is_callable($fail)){
						$fail($e);
					}
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



			public function multi_language($_TABLE){
					$COLUNA_PRIMARY = null;
					$_MYSQL = new galaxyDB();
					$_MYSQL->connect();
					$_INDEX				=	$_MYSQL->getIndexes($_TABLE);
					$_COLUNAS			=	$_MYSQL->showDBColumns($_TABLE,true);
					$_TABELA_TRANSLATE	=	$_TABLE.'__TRANSLATE';

				/*
				|--------------------------------------------------------------------
				|	LIMPA TUDO PRIMNEIRO
				|--------------------------------------------------------------------
				*/
					
						$QUERY	='ALTER TABLE '.$_TABLE.' DROP COLUMN IF EXISTS FW_LANG;';
						$QUERY .='ALTER TABLE '.$_TABLE.' DROP COLUMN IF EXISTS ID_FW_PAI;';
						// $QUERY .='DROP TABLE IF EXISTS '.$_TABELA_TRANSLATE.';';
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
						if(is_null($COLUNA_PRIMARY)){die(\app\system\lib\system::ajaxReturn("Não foi encontrato INDEX PRIMARY KEY da tabela ".$_TABLE,0));}

				/*
				|--------------------------------------------------------------------
				|	LIMPAMOS AS COLUNAS
				|--------------------------------------------------------------------
				|
				|	Aqui retiramos da lista a CHAVE PRIMÁRIA 
				|	Também retiramos a coluna da trigger "FW_LANG"
				|
				|
				*/

					$COLUNAS_TRATADAS = $_COLUNAS;
					foreach ($COLUNAS_TRATADAS as $index=>$coluna) {
						if ($coluna['Field'] === $COLUNA_PRIMARY || $coluna['Field'] === 'FW_LANG') {
							unset($COLUNAS_TRATADAS[$index]);
						}
					}
					$COLUNAS_TRATADAS = array_values($COLUNAS_TRATADAS);
					$COLUNAS_FIELDS = [];
					foreach ($COLUNAS_TRATADAS as $COLUNA) {
						$COLUNAS_FIELDS[]= $COLUNA['Field'];

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
					$CREATE_TRANSLATE			= 'CREATE TABLE IF NOT EXISTS '.$_TABELA_TRANSLATE.' (ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY, FW_LANG VARCHAR(5) DEFAULT "pt", ID_FW_PAI int(11));'.PHP_EOL;
					foreach ($COLUNAS_TRATADAS as $COLUNA) {				
						$CREATE_TRANSLATE		.=	'SET @columnCount = ( SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA="'.getEnv('DB_DATABASE').'" AND TABLE_NAME ="'.$_TABELA_TRANSLATE.'" AND COLUMN_NAME ="'.$COLUNA['Field'].'");'.PHP_EOL;
						$CREATE_TRANSLATE		.=	'IF  @columnCount = 0 THEN ' . PHP_EOL;
						$CREATE_TRANSLATE		.=	'	ALTER TABLE `'.$_TABELA_TRANSLATE.'` ADD `'.$COLUNA['Field'].'` '.$COLUNA['Type'].';'.PHP_EOL;
						$CREATE_TRANSLATE		.=	'END IF;'.PHP_EOL.PHP_EOL;
					}
					$_MYSQL->connection->query($CREATE_TRANSLATE);

				/*
				|--------------------------------------------------------------------
				|	DA MESMA FORMA A COLUNA FW_LANG NA TABELA ORIGINAL
				|--------------------------------------------------------------------
				*/
					$ORIGINAL_FW_LANG		=	'SET @columnCount = ( SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA="'.getEnv('DB_DATABASE').'" AND TABLE_NAME ="'.$_TABLE.'" AND COLUMN_NAME="FW_LANG");'.PHP_EOL;
					$ORIGINAL_FW_LANG		.=	'IF  @columnCount = 0 THEN ' . PHP_EOL;
					$ORIGINAL_FW_LANG		=	'	ALTER TABLE `'.$_TABLE.'` ADD COLUMN FW_LANG VARCHAR(5) DEFAULT "pt";'.PHP_EOL;
					$ORIGINAL_FW_LANG		.=	'END IF;'.PHP_EOL.PHP_EOL;					
					$_MYSQL->connection->query($ORIGINAL_FW_LANG);

				/*
				|--------------------------------------------------------------------
				|	INICIAMOS A TRIGGER DE UPDATE
				|--------------------------------------------------------------------
				*/
				
						$TRIGGER_UPDATE	='	CREATE TRIGGER UPDATE__'.$_TABLE.' AFTER UPDATE ON '.$_TABLE.' FOR EACH ROW';
						$TRIGGER_UPDATE	.='		BEGIN'.PHP_EOL;
						$TRIGGER_UPDATE	.='		    DECLARE record_count INT;'.PHP_EOL;
						$TRIGGER_UPDATE .='			SET @permitir_atualizacao = 1;'.PHP_EOL;
						$TRIGGER_UPDATE	.='			SET @record_count = ( SELECT COUNT(*) FROM '.$_TABELA_TRANSLATE.' WHERE ID_FW_PAI = NEW.ID AND FW_LANG = NEW.FW_LANG );'.PHP_EOL;
						$TRIGGER_UPDATE .='			IF  @record_count > 0 THEN '.PHP_EOL;
						$TRIGGER_UPDATE .='				UPDATE '.$_TABELA_TRANSLATE.' SET '.PHP_EOL;
						$COLUNAS_DECLARADAS =[]; 
						foreach ($COLUNAS_TRATADAS as $COLUNA) {if($COLUNA['Field']!=$COLUNA_PRIMARY){	$COLUNAS_DECLARADAS[]=$COLUNA['Field'].'=IF(OLD.'.$COLUNA['Field'].'!=NEW.'.$COLUNA['Field'].', NEW.'.$COLUNA['Field'].', '.$COLUNA['Field'].')';}}
						$TRIGGER_UPDATE .= '						'.implode(','.PHP_EOL.'					 	',$COLUNAS_DECLARADAS).PHP_EOL;
						$TRIGGER_UPDATE .='				WHERE ID_FW_PAI = NEW.'.$COLUNA_PRIMARY.' AND FW_LANG = NEW.FW_LANG; '.PHP_EOL;					
						$TRIGGER_UPDATE .='			ELSE'.PHP_EOL;
						$TRIGGER_UPDATE .='				INSERT INTO '.$_TABELA_TRANSLATE.' ( '.implode(',',$COLUNAS_FIELDS).', ID_FW_PAI, FW_LANG) VALUES ( NEW.'.IMPLODE(',NEW.',$COLUNAS_FIELDS).',NEW.'.$COLUNA_PRIMARY.', NEW.FW_LANG);'.PHP_EOL;
						$TRIGGER_UPDATE .='			END IF; '.PHP_EOL.PHP_EOL;
						$TRIGGER_UPDATE .='		END;'.PHP_EOL;
						$_MYSQL->connection->query($TRIGGER_UPDATE);

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
						$_MYSQL->connection->query($SECURITY_UPDATE);


				/*
				|--------------------------------------------------------------------
				|	INICIAMOS A TRIGGER DE INSERT
				|--------------------------------------------------------------------
				*/
					$TRIGGER_INSERT  ="	CREATE TRIGGER INSERT__{$_TABLE} AFTER INSERT ON {$_TABLE}".PHP_EOL;
					$TRIGGER_INSERT .="		FOR EACH ROW BEGIN".PHP_EOL;
					$TRIGGER_INSERT .= '			SET @permitir_atualizacao= 1;' . PHP_EOL;
					$TRIGGER_INSERT .="			INSERT INTO {$_TABELA_TRANSLATE} (".implode(',',$COLUNAS_FIELDS).", ID_FW_PAI, FW_LANG) VALUES (NEW.".implode(',NEW.',$COLUNAS_FIELDS).", NEW.{$COLUNA_PRIMARY}, 'pt');".PHP_EOL;
					$TRIGGER_INSERT .="			INSERT INTO {$_TABELA_TRANSLATE} (ID_FW_PAI, FW_LANG) VALUES (NEW.{$COLUNA_PRIMARY}, 'en');".PHP_EOL;
					$TRIGGER_INSERT .="			INSERT INTO {$_TABELA_TRANSLATE} (ID_FW_PAI, FW_LANG) VALUES (NEW.{$COLUNA_PRIMARY}, 'es');".PHP_EOL;
					$TRIGGER_INSERT .="	END;".PHP_EOL.PHP_EOL;
					$_MYSQL->connection->query($TRIGGER_INSERT);

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
					$_MYSQL->connection->query($SECURITY_INSERT);
					
				/*
				|--------------------------------------------------------------------
				|    INICIAMOS A TRIGGER DE DELETE
				|--------------------------------------------------------------------
				*/

					$TRIGGER_DELETE ="	CREATE TRIGGER DELETE__{$_TABLE} AFTER DELETE ON {$_TABLE} FOR EACH ROW BEGIN ".PHP_EOL;
					$TRIGGER_DELETE .='	SET @permitir_atualizacao=1;'.PHP_EOL;
					$TRIGGER_DELETE .="	DELETE FROM {$_TABELA_TRANSLATE} WHERE ID_FW_PAI=OLD.ID; ".PHP_EOL;
					$TRIGGER_DELETE .="	END;".PHP_EOL.PHP_EOL;
					$_MYSQL->connection->query($TRIGGER_DELETE);

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
					$_MYSQL->connection->query($SECURITY_DELETE);

			}

    }