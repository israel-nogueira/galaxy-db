<?
    namespace IsraelNogueira\galaxyDB;

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


    }