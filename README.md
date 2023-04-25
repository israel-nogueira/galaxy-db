
# PHP MySQL

Classe para controlar o MySQL no PHP com facilidade e segurança.
 
## Instalação

A instalação é muito simples. 
Execute em seu CLI:
```
composer require israel-nogueira/mysql-orm
```

Basta importar o autoload e criar seus próprios *Models* como os arquivos  `./app/models/*.model.php`
```php
<?php
	include vendor\autoload.php
	use IsraelNogueira\MysqlOrm
```


## Models

O *Model* é o uso da classe abstrata da classe principal. 
Nela serão cadastrados os parâmetros de uso da classe.
Nesse caso criamos o arquivo `` /app/Models/user.model.php``

```php
/**
 *  Início da extensão da classe mysql.
 */
<?php
	namespace App\Models
	use IsraelNogueira\MysqlOrm\mariaDB;

	class  userModel  extends  mariaDB {
		//  TABELA PADRÃO 
		protected $table =  'usuarios';
		//  COLUNAS BLOQUEADAS 
		protected $columnsBlocked = ['CPF','CARTAO_CREDITO_TOKEN'];
		//  COLUNAS PERMITIDAS 
		protected $columnsEnabled = ['NOME','EMAIL','TELEFONE','ENDERECO'];
		//  FUNÇÕES MYSQL PROIBIDAS 
		protected $functionsBlocked = ['CONCAT','SHA2'];
		//  FUNÇÕES MYSQL PERMITIDAS 
		protected $functionsEnabled = ['IF','SH1','COALESCE'];
	}
?>



```

## Exemplos de uso

### Select simples

O exemplo apresenta um `SELECT` básico com um filtro apenas para usuário com `ID=7`.
Uma `array` vazia será retornada caso a consulta não encontre resultados.
```sql 
SELECT nome,email as mail,endereco,telefone FROM usuarios WHERE id=7
```
```php
<?php
	use  App\Models\userModel
	
	// SELECT ID, TITULO, VALOR FROM LIVROS WHERE ID > 10
	$users =  new  userModel();
	$users->colum('nome');//unitario
	$users->colum('email as mail');// com alias
	$users->colum(['endereco','telefone']); // ou ainda varias de uma vez
	$users->set_where('id=7');
	$users->select();
	$_RESULT = $users->fetch_array(); // retorna um ARRAY

?>
```
### Select mais completo
```php
<?php
	use  App\Models\userModel
	$users =  new  userModel();
	$users->colum('nome');
	$users->colum('bairro_id');
	$users->join('INNER','bairros',' bairros.id=usuarios.bairro_id'); // TIPO | TABELA | ON
	$users->join('LEFT','cidades',' cidades.id=usuarios.cidade_id'); // TIPO | TABELA | ON
	$users->group_by('bairros'); // GROUP BY
	$users->like('nome','%edro%');
	$users->like('nome','%ão%');
	$users->order('nome','asc'); // ORDER BY nome ASC
	$users->limit(1,10); // SET LIMIT 1, 10
	$users->set_where('id=7');
	$users->debug(true); // false não retornará erros e falhas. Default:true
	$users->distinct(); // ignora os resultados repetidos
	$users->select();
	
	// $_ARRAY[0]["nome"] | $_ARRAY[1]["nome"] 
	$_ARRAY = $users->fetch_array(); 
	
	// $_OBJECT[0]->nome | $_OBJECT[1]->nome
	$_OBJECT = $users->fetch_obj(); 
	
?>
```
## SUB SELECTS

```php
<?php
	use  App\Models\userModel
	$users =  new  userModel();
	// Puxamos todos usuarios que morem na cidade 11 ( 11=Curitiba )
	// Criamos um sub select e instanciamos como "cidade_11"
	$users->set_where('cidade_id=11');
	$users->setSubQuery('cidade_11');
	
	// Agora selecionamos com o tableSubQuery() nossa subQuery e damos o alias de "curitiba"
	$getInfoBanner->tableSubQuery('(cidade_11) curitiba');
	$users->set_where('curitiba.solteiros=1');
	
	// Poderiamos parar poraqui mas se quiser aprofundarmos
	$users->setSubQuery('solteiros'); 
	$getInfoBanner->tableSubQuery('(solteiros) sexo');
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
Também podemos aplicar isso a uma coluna:

```php
<?php
	use  App\Models\userModel
	$users =  new  userModel();
	
	// Aqui apenas trazemos o total de usuarios que moram na cidade 11
	$users->colum('COUNT(1) as total_registro ');
	$users->set_where('cidade_id=11');
	$users->setSubQuery('total_11'); // <----- Guarda  Subquery
	
	$users->colum('user.*');
	$users->columSubQuery('(total_11) AS total_curitibanos');
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


### Insert
Podemos inserir dados de algumas formas diferentes:

### Update


### Delete
