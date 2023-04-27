
# PHP ORM

Classe para controlar o MySQL no PHP com facilidade e segurança.

## Instalação

Instale via composer. 


```

	composer require israel-nogueira/mysql-orm

```

Acrescente em seu *composer.json*:

```
    "scripts": {
        "orm": "php vendor/israel-nogueira/mysql-orm/src/orm"
    }

```

Agora poderá executar comandos para criar seus Models.
Cada palavra é um parametro, por exemplo *"usuarios e produtos"* são duas Models que serão criadas *usuariosModel.php* e *produtosModel.php*;


```

	composer run-script orm usuario produtos

```

Isso criará automaticamente os seguinte arquivos: 
**/app/models/usuariosModel.php**
**/app/models/produtosModel.php**


## PADRÃO DAS MODELS

A *Model* é o uso da classe abstrata da classe principal. 
Nela serão cadastrados os parâmetros de uso da classe.

```php
<?php
	namespace IsraelNogueira\Models;
	use IsraelNogueira\MysqlOrm\mysqlORM;

	class usuariosModel	extends	mysqlORM	{
		//  TABELA PADRÃO 
		protected $table =  'usuarios';
		//  COLUNAS BLOQUEADAS 
		protected $columnsBlocked = [];
		//  COLUNAS PERMITIDAS 
		protected $columnsEnabled = [];
		//  FUNÇÕES MYSQL PROIBIDAS 
		protected $functionsBlocked = [];
		//  FUNÇÕES MYSQL PERMITIDAS 
		protected $functionsEnabled = [];
		//  FUNÇÕES MYSQL PERMITIDAS 
		protected $charactersEnabled = [];
		//  FUNÇÕES MYSQL PROIBIDOS 
		protected $charactersBlocked = [];

	}
?>
```

Basta importar o autoload e o namespace da sua Model e utilizar

```php

<?php
	include "vendor\autoload.php";
	use IsraelNogueira\Models\usuariosModel;
?>

```

# EXEMPLOS DE USO

### Select simples

O exemplo apresenta um `SELECT` básico com um filtro apenas para usuário com `ID=7`.
Uma `array` vazia será retornada caso a consulta não encontre resultados.

```php

<?php
	include "vendor\autoload.php";
	use  App\Models\usuariosModel;

	$users =  new usuariosModel();
	$users->colum('nome');//unitario
	$users->colum('email as mail');// com alias
	$users->colum(['endereco','telefone']); // ou ainda varias de uma vez
	$users->set_where('id=7');
	$users->select();
	$_RESULT = $users->fetch_array(); // retorna um ARRAY

?>

```
Resultará no seguinte select: 
```sql 
SELECT nome,email as mail,endereco,telefone FROM usuarios WHERE id=7
```


### Select mais completo
```php
<?php
	include "vendor\autoload.php";
	use  App\Models\usuariosModel;

	$users =  new  usuariosModel();
	$users->colum('nome');
	$users->colum('bairro_id');

	$users->join('INNER','bairros',' bairros.id=usuarios.bairro_id')
			->join('LEFT','cidades',' cidades.id=usuarios.cidade_id'); // TIPO | TABELA | ON
			
	$users->group_by('bairros'); // GROUP BY

	$users->like('nome','%edro%')->like('nome','%ão%');

	$users->order('nome','asc')->order('idade','desc'); // ORDER BY nome ASC, idade DESC

	$users->limit(1,10); // SET LIMIT 1, 10
	$users->where('cidades.id=11');
	$users->distinct(); // ignora os resultados repetidos
	$users->debug(true); // false não retornará erros e falhas. Default:true
	$users->select();

	// $_ARRAY[0]["nome"] | $_ARRAY[1]["nome"] 
	$_ARRAY = $users->fetch_array(); 

	// $_OBJECT[0]->nome | $_OBJECT[1]->nome
	$_OBJECT = $users->fetch_obj();
	
?>

```
Resultará em uma query assim:
```sql
SELECT  DISTINCT  nome,  bairro_id  FROM  usuarios  
INNER  JOIN  bairros  ON  bairros.id  =  usuarios.bairro_id  
LEFT  JOIN  cidades  ON  cidades.id  =  usuarios.cidade_id  
WHERE  (  
		cidades.id  =  11  
		AND  (
			Lower(nome)  LIKE  Lower("%edro%")  OR  Lower(nome)  LIKE  Lower("%ão%") 
		)
	) GROUP  BY  bairros ORDER BY nome ASC, idade DESC
```



## SUB SELECTS

```php
<?php
	include "vendor\autoload.php";
	use  App\Models\usuariosModel;

	$users =  new  usuariosModel();
	// Puxamos todos usuarios que morem na cidade 11 ( 11=Curitiba )
	// Criamos um sub select e instanciamos como "cidade_11"
	$users->set_where('cidade_id=11');
	$users->setSubQuery('cidade_11');

	// Agora selecionamos com o tableSubQuery() nossa subQuery e damos o alias de "curitiba"
	$users->tableSubQuery('(cidade_11) curitiba');
	$users->set_where('curitiba.solteiros=1');

	// Poderiamos parar poraqui mas se quiser aprofundarmos
	$users->setSubQuery('solteiros'); 
	$users->tableSubQuery('(solteiros) sexo');
	$users->set_where('solteiros.sexo="male"');

	//	Executamos o select puxando os moradores da cidade 11 
	//	e depois filtramos os solteiros
	$users->select('homens_solteiros_curitiba');

	$_ARRAY = $users->fetch_array('homens_solteiros_curitiba'); 

?>

```
Isso resultará na seguinte query:
```sql
SELECT  *  
	FROM  (SELECT  *  
		FROM  (SELECT  *  
				FROM  usuarios
				WHERE  (  cidade_id  =  11  ))  curitiba  
		WHERE  (  curitiba.solteiros  =  1  ))  sexo  
WHERE  (  solteiros.sexo  =  "male"  )
```
Também podemos aplicar uma subquery a uma coluna:

```php
<?php
	include "vendor\autoload.php";
	use  App\Models\meusUsuario;
	$users =  new  usuariosModel();

	// Aqui apenas trazemos o total de usuarios que moram na cidade 11
	$users->colum('COUNT(1) as total_registro ');
	$users->set_where('cidade_id=11');
	$users->setSubQuery('total_11'); // <----- Cria subquery "total_11"

	$users->colum('user.*');
	$users->columSubQuery('(total_11) AS total_curitibanos'); // Monta coluna com a Subquery
	$users->set_where('total_curitibanos>100');
	$users->prepare_select('homens_solteiros_curitiba');	
	$_ARRAY = $users->fetch_array('homens_solteiros_curitiba'); 

?>
```

```sql
SELECT user.*, (
	SELECT  COUNT(1) AS total_registro FROM users WHERE(cidade_id=11)
)  AS  total_curitibanos  
FROM  users  WHERE  (  total_curitibanos  >  100  )
```

### MULTIPLOS SELECTS

Podemos também executar múltiplos selects em uma só instancia:


```php
<?php
	include "vendor\autoload.php";
	use  App\Models\usuariosModel;

	$users =  new  usuariosModel();
	$users->colum('username');
	$users->colum('email');
	$users->limit(1);
	$users->prepare_select('users_1'); //Guardamos a query
	
	$users->table('financeiro__historico'); // pode setar uma nova tabela
	$users->colum('VALOR');
	$users->limit(1);
	$users->where('PAGADOR="'.$uid.'"');
	$users->prepare_select('valores');//Guardamos a query

	// executamos todas as querys 
	$getInfoBanner->execQuery();

	$_ARRAY = $users->fetch_array(); 

?>
```
Nos resultará no seguinte array:

```
Array
(
	[users_1] => Array
		(
			[0] => Array
				(
					[username] => username_01
					[email] => exemplo@email.com
				)
		)

	[valores] => Array
		(
			[0] => Array
				(
					[VALOR] => 100.00
				)
		)
)
``` 



# Insert
Podemos inserir dados de algumas formas diferentes:

```php
<?php
	include "vendor\autoload.php";
	use  App\Models\usuariosModel;

	//FORMA SIMPLIFICADA
	$users =  new  usuariosModel();
	$users->coluna1 = 'valor';
	$users->coluna2 = 'valor';
	$users->coluna3 = 'valor';
	$users->insert();

	//Todas as condicionais podem ser aplicadas aqui também
	$users =  new  usuariosModel();
	$users->coluna1 = 'valor';
	$users->coluna2 = 'valor';
	$users->coluna3 = 'valor';
	$users->where('NOW() > "00-00-00 00:00:00"');
	$users->insert();
?>
```
# MULTIPLOS INSERTS + TRANSACTION + ROLLBACK

```php
<?
	// MULTIPLOS INSERTS
	$users =  new  usuariosModel();
	$users->coluna1 = 'valor';
	$users->coluna2 = 'valor';
	$users->coluna3 = 'valor';
	$users->prepare_insert();

	$users->coluna1 = 'valor';
	$users->coluna2 = 'valor';
	$users->coluna3 = 'valor';
	$users->where('NOW() > "00-00-00 00:00:00"');
	$users->prepare_insert();

	// TRANSACTION + ROLLBACK
	$users->transaction(function ($ERROR) {
		throw  new  ErrorException($ERROR, 1); // erro
	});

	//EXECUTA OS INSERTS
	$users->execQuery();
?>
```
# INSERT ARRAY + TRANSACTION + ROLLBACK

```php
<?
	//PUXANDO UMA ARRAY
	$users =  new  usuariosModel();
	$users->set_insert_form(['UID'=>32,'NOME'=>'João', 'IDADE'=>27]);
	$users->prepare_insert();

	//DENTRO DE UM LAÇO
	foreach($_RESULTADO as $OBJ){
		$users->set_insert_form($OBJ);
		$users->prepare_insert();
	}

	// TRANSACTION + ROLLBACK
	$users->transaction(function ($ERROR) {
		throw  new  ErrorException($ERROR, 1); // erro
	});

	//EXECUTA OS INSERTS
	$users->execQuery();
?>
```

# UPDATE:
```php
<?php
	include "vendor\autoload.php";
	use  App\Models\usuariosModel;

	//FORMA SIMPLIFICADA
	$users =  new  usuariosModel();
	$users->coluna1 = 'valor';
	$users->coluna2 = 'valor';
	$users->coluna3 = 'valor';
	$users->update();
	
	//Todas as condicionais podem ser aplicadas aqui também
	$users =  new  usuariosModel();
	$users->coluna1 = 'valor';
	$users->coluna2 = 'valor';
	$users->coluna3 = 'valor';
	$users->where('UID="7365823765"');
	$users->update();
?>
```

# MULTIPLOS UPDATES + TRANSACTION + ROLLBACK:

```php
<?php
	// MULTIPLOS UPDATES
	$users =  new  usuariosModel();
	$users->coluna1 = 'valor';
	$users->coluna2 = 'valor';
	$users->coluna3 = 'valor';
	$users->where('UID="46746876"');
	$users->prepare_update();
	
	$users->coluna1 = 'valor';
	$users->coluna2 = 'valor';
	$users->coluna3 = 'valor';
	$users->where('UID="9653566573"');
	$users->prepare_update();
	
	// TRANSACTION + ROLLBACK
	$users->transaction(function ($ERROR) {
		throw  new  ErrorException($ERROR, 1); // erro
	});
	
	//EXECUTA OS UPDATES
	$users->execQuery();
?>
```

# MULTIPLOS UPDATES COM ARRAYS:

```php
<?php
	//PUXANDO UMA ARRAY
	$users =  new  usuariosModel();
	$users->set_update_form(['UID'=>32,'NOME'=>'João', 'IDADE'=>27]);
	$users->prepare_update();

	//DENTRO DE UM LAÇO
	foreach($_RESULTADO as $OBJ){
		$users->set_update_form($OBJ);
		$users->prepare_update();
	}

	// TRANSACTION + ROLLBACK
	$users->transaction(function ($ERROR) {
		throw  new  ErrorException($ERROR, 1); // erro
	});

	//EXECUTA OS INSERTS
	$users->execQuery();
?>
```



# DELETE

```php
<?php

	//DELETE DIRETO E SIMPLES
	$users =  new  usuariosModel();
	$users->where('UID=32');
	$users->delete();

	//PREPARANDO MULTIPLOS
	$users =  new  usuariosModel();
	$users->where('UID=32');
	$users->prepare_delete();//Armazena

	//DENTRO DE UM LAÇO
	foreach($_RESULTADO as $OBJ){
		$users->where('UID='.$OBJ['UID']);
		$users->prepare_delete();//Armazena
	}

	// TRANSACTION + ROLLBACK
	$users->transaction(function ($ERROR) {
		throw  new  ErrorException($ERROR, 1); // erro
	});

	//EXECUTA OS DELETES
	$users->execQuery();
?>
```

## FUNÇÕES NA MODEL

Você pode também estender padrões em sua model.
Podendo abstrair mais nossas consultas.

Seguindo o exemplo abaixo: 

```php
<?php
	namespace IsraelNogueira\Models;
	use IsraelNogueira\MysqlOrm\mysqlORM;

	class usuariosModel	extends	mysqlORM	{
		protected $table=  'usuarios';

		// AQUI MONTAMOS A NOSSA FUNÇÃO ESTENDIDA
		public function cidadeEstado(){
			$this->colum('city.nome as cidade');
			$this->colum('uf.nome as uf');
			$this->join('LEFT','table_cidade cidade','cidade.id=usuarios.cidade_id');
			$this->join('LEFT','table_uf uf','uf.id=cidade.uf_id');
		}

	}
?>
```

E quando for utilizar a classe:

```php

<?php
	include "vendor\autoload.php";
	use  App\Models\usuariosModel;

	$users =  new usuariosModel();
	$users->colum('nome');
	$users->colum('idade');
	$users->cidadeEstado(); //====> aqui executamos nossa função
	$users->select();
	$_RESULT = $users->fetch_array();

?>

```