
# PHP MySQL

Classe para controlar o MySQL no PHP com facilidade e segurança.
 
## Instalação

A instalação é muito simples. 
Execute em seu CLI:
```
composer require israel-nogueira/mysql-orm
```

Basta importar o autoload e criar seus próprios *Models* como os arquivos  `./app/Models/*.model.php`
```php
<?php
	include vendor\autoload.php;
	use IsraelNogueira\MysqlOrm;
```


## Models

O *Model* é uma classe extendida da classe principal. 
Nela serão cadastrados os parâmetros de uso da classe.
Nesse caso criamos o arquivo `` /app/Models/user.model.php``

```php
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

### Insert
Podemos inserir dados de algumas formas diferentes:

### Update


### Delete
