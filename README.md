
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

### Select

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
	$users->set_colum('nome');//unitario
	$users->set_colum('email as mail');// com alias
	$users->set_colum(['endereco','telefone']); // ou ainda varias de uma vez
	$users->set_where('id=7');
	$users->select();
	$_RESULT = $users->fetch_array(); // retorna um ARRAY

?>
```
```sql 
SELECT nome,bairro_id FROM usuarios WHERE id=7
```
```php
<?php
	use  App\Models\userModel
	
	// SELECT ID, TITULO, VALOR FROM LIVROS WHERE ID > 10
	$users =  new  userModel();
	$users->set_colum('nome');
	$users->set_colum('bairro_id');
	$users->set_where('id=7');
	$users->join('INNER','bairros',' bairros.id=usuarios.bairro_id'); // TIPO | TABELA | ON
	$users->group_by('bairros'); // GROUP BY
	$users->set_limit(1,10); // SET LIMIT 1, 10
	$users->set_order('nome','asc'); // ORDER BY nome ASC
	$users->select();
	$_RESULT = $users->fetch_array();

?>```
Se passado através de `array` é possível inserir como primeiro parâmetro `OR|AND` para que a consulta seja para todos os parâmetros ou apenas um deles:

```php
/**
 * SELECT * 
 *   FROM LIVROS 
 *  WHERE (
 *             TITULO = 'João'
 *          OR TITULO = 'Maria'
 *          OR TITULO = 'José'
 *        )
 */

livrosBD::init()->TITULO( [ 'OR', 'João', 'Maria', 'José' ] )

/**
 * SELECT * 
 *   FROM LIVROS 
 *  WHERE (
 *              TITULO = 'João'
 *          AND TITULO = 'Maria'
 *          AND TITULO = 'José'
 *        )
 */

livrosBD::init()->TITULO( [ 'AND', 'João', 'Maria', 'José' ] )

/**
 * Caso nenhum parâmetro seja passado OR será usado como padrão:
 * 
 * SELECT * 
 *   FROM LIVROS 
 *  WHERE (
 *             TITULO = 'João'
 *          OR TITULO = 'Maria'
 *          OR TITULO = 'José'
 *        )
 */

livrosBD::init()->TITULO( [ 'João', 'Maria', 'José' ] )
```

É possível fazer busca também por proximidade.
Vide parâmetros em https://www.w3schools.com/sql/sql_like.asp

```php
livrosBD::init()->EMAIL( '%@email.com' )
```
Caso seja uma coluna `DATE|DATETIME|TIME` é possível fazer uma busca por partes do tempo determinado:

```php

/**
 *  Os parâmetros podem ser:
 *  - String|Array|Integer
 * 
 *  No caso de String ou Integer o filtro pode ser mais
 *  de um parâmetro.
 * 
 *  No caso de Array, 1 único parâmetro é aceito contendo
 *  1 ou mais dados de texto ou numéricos.
 * 
 *  Referência:
 *  https://cloud.google.com/bigquery/docs/reference/standard-sql/date_functions?hl=pt-br
 */

/**
 * SELECT * 
 *   FROM LIVROS 
 *  WHERE EXTRACT( DAY FROM DATA_CADASTRO ) = 2
 */

livrosBD::init()->day()  ->DATA_CADASTRO( 2 )

/**
 * SELECT * 
 *   FROM LIVROS 
 *  WHERE EXTRACT( MONTH FROM DATA_CADASTRO ) IN ( 1, 7, 12 )
 */

livrosBD::init()->month()->DATA_CADASTRO( 1, 7, 12 )
```

### Insert

Podemos inserir dados de algumas formas diferentes:

#### Através do método `values()` de forma simples

> ***ATENÇÃO***
> 
> ***É altamente recomendado que se houver mais de 5 colunas, não se utilize o cadastro de colunas e valores via parâmetro e sim dentro de uma `array`***

```php
// Valores como parâmetros
livrosBD::init()
            ->insert( 'TITULO', 'SKU' )
            ->values( 'Desenvolvendo MySQL em PHP', 'TECPROG0052' )
            ->exec()

// Valores em uma array
livrosBD::init()
            ->insert( 'TITULO', 'SKU' )
            ->values([ 'Desenvolvendo MySQL em PHP', 'TECPROG0052' ])
            ->exec()
```

#### Através do método `values()` de forma composta

> ***ATENÇÃO:***
>
> ***Esse método utiliza do modo de inserção composta e deve ser usado em blocos caso a inserção seja muito grande. Deve-se corresponder a capacidade do servidor.***
> 
> ***Recomendado limitar a 10.000 inserções no máximo por vez.***
>
> ***A velocidade de inserção nesse método é muito maior que inserir via laço do PHP, por isso, caso haja necessidade de inserção em blocos, preferencialmente use esse método.***

```php
livrosBD::init()
            ->insert( 'TITULO', 'SKU' )
            ->values([
                ['Desenvolvendo MySQL em PHP', 'TECPROG0052'],
                ['A Arte da Programação'     , 'TECPROG0053'],
                ['MySQL fácil'               , 'TECPROG0054'],
            ])
            ->exec()
```

### Diretamente no método `insert()` utilizando de `array` com chave nominal*

**Esse método também utiliza o modo de inserção composta e deve respeitar as mesmas orientações*

> ***ATENÇÃO***
>
> ***A inserção de valores deve corresponder igualmente em todas as inserções de dados, sejam únicos ou compostos***

```php
// Modo simples
livrosBD::init()
            ->insert([
                'TITULO' => 'Desenvolvendo MySQL em PHP',
                'SKU'    => 'TECPROG0052'
            ])
            ->exec()

// Modo composto 1
livrosBD::init()
            ->insert([
                'TITULO' => [
                                'Desenvolvendo MySQL em PHP',
                                'A Arte da Programação',
                                'MySQL fácil'
                            ],
                'SKU'    => [
                                'TECPROG0052',
                                'TECPROG0053',
                                'TECPROG0054',
                            ]
            ])
            ->exec()

// Modo composto 2
livrosBD::init()
            ->insert([
                [
                    'TITULO' => 'Desenvolvendo MySQL em PHP',
                    'SKU'    => 'TECPROG0052'
                ],[
                    'TITULO' => 'A Arte da Programação',
                    'SKU'    => 'TECPROG0053'
                ],[
                    'TITULO' => 'MySQL fácil',
                    'SKU'    => 'TECPROG0054'
                ],
            ])
            ->exec()
```

### Update

```php
livrosBD::init()
            ->update([ 'TITULO' => 'TÍTULO ATUALIZADO' ])
            ->where( 'ID', 10 )
            ->exec()
```

### Delete

```php
livrosBD::init()
            ->delete()
            ->where( 'ID', 10 )
            ->exec()
```
