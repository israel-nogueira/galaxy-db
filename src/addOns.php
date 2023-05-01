<?
    namespace IsraelNogueira\MysqlOrm;

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


    }