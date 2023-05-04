![GalaxyDB](https://raw.githubusercontent.com/israel-nogueira/galaxy-db/main/src/topo_README.jpg)

---

[![Latest Stable Version](https://poser.pugx.org/israel-nogueira/galaxy-db/v/stable.svg)](https://packagist.org/packages/israel-nogueira/galaxy-db)
[![Total Downloads](https://poser.pugx.org/israel-nogueira/galaxy-db/downloads)](https://packagist.org/packages/israel-nogueira/galaxy-db)
[![License](https://poser.pugx.org/israel-nogueira/galaxy-db/license.svg)](https://packagist.org/packages/israel-nogueira/galaxy-db)


Classe para controlar a sua base de dados no PHP com facilidade e segurança.

Essa classe dá suporte as seguintes conexões:

`mysql` `pgsql`
`sqlite` `ibase`
`fbird ` `oracle`
`mssql` `dblib`
`sqlsrv`

## Instalação

Instale via composer.

```plaintext
    composer require israel-nogueira/galaxy-db
```

Acrescente em seu _composer.json_:

```plaintext
    "scripts": {
        "galaxy": "php vendor/israel-nogueira/galaxy-db/src/galaxy"
    }
```

## CONFIGURANDO A BASE

Você pode configuraros dados de conexão via CLI, sendo que<br/>
os parêmetros são opcionais, caso falte algum dado, o prompt lhe pedirá

- `type`: Sigla do tipo de base *(mysql, pgsql etc)* 
- `user`: Usuário da base
- `pass`: Senha 
- `name`: Nome da base
- `host ` Porta

```plaintext
  
  composer run-script galaxy config-connection type user pass name host port

```

No arquivo ```/vendor/israel-nogueira/galaxy-db/src/connection.php``` preencha os dados de conexão de sua base:

```php
    <?php
        declare(strict_types = 1);
        namespace IsraelNogueira\galaxyDB;

        define('DB_HOST', 	'localhost');
        define('DB_PORT', 	'3306');
        define('DB_DATABASE', 	'MY_DATABASE');
        define('DB_TYPE', 	'mysql');
        define('DB_USERNAME', 	'root');
        define('DB_PASSWORD',   '');
        define('DB_CHAR',   	'');
        define('DB_FLOW',   	'');
        define('DB_FKEY',   	'');

        trait connection{

        }

```
## Snippets para VSCode

Depois que você configurou os dados de conexão, poderá criar um snippets da classe.<br/>
Sim, essa classe também conta com um script que importa a estrutura da sua base de dados.<br/>
E monta um snippets com atalhos.

Para criar **ou atualizar** seu snippets, basta executar:
```plaintext

    composer run-script galaxy update-snippets

```

E Pronto, você e seu VSCode estão prontos para trabalhar de maneira rápida e eficaz.
![GalaxyDB](https://raw.githubusercontent.com/israel-nogueira/galaxy-db/main/src/snippets-exemplo.gif)

#### Alguns atalhos:

```select```, ```update```, ```insert``` ou ```delete``` retornam a classe completa de CRUD;

```table``` ou ```->table```:<br/>
Mostra a lista de tabelas disponíveis em sua base de dados;<br/>
Se tiver ```->``` retorna a função montada ```->table("sua-tabela")```;<br/>
Caso contrario, retorna apenas o nome da tabela


```colum``` ou ```->colum```:<br/>
Se tiver ```->``` retorna a função montada ```->colum("sua-tabela")```;<br/>
Caso contrario, retorna apenas o nome da coluna.

Inicialmente ela mostra a lista de tabelas disponíveis em sua base de dados;<br/>
E na sequencia a lista de colunas daquela tabela selecionada.

```columns``` ou ```tables``` :<br/>
Você pode retornar uma lista de tabelas ou colunas de sua base de dados

E com tempo vamos incrementando a lista de atalhos.


## CRIANDO MODELS

Este é o comando para criar suas Models.  
Cada palavra é um parametro, por exemplo *“usuarios e produtos”* no comando:

```plaintext
    composer run-script galaxy new-model usuarios produtos
```

Isso criará automaticamente os seguinte arquivos:

> **/app/models/usuariosModel.php**  
> **/app/models/produtosModel.php**

Você também notará, que foi adicionado em seu _composer.json_  
a rota onde suas Models serão criadas e executadas.

```plaintext
"psr-4": {
    "IsraelNogueira\\Models\\": "app/Models/"
}
```

## PADRÃO DAS MODELS

Basta importar o autoload e o namespace da sua Model e utilizar

```php
<?php
    include "vendor\autoload.php";
    use IsraelNogueira\Models\usuariosModel;
?>
```

A _Model_ é o uso da classe abstrata da classe principal.  
Nela serão cadastrados os parâmetros de uso da classe.

```php
<?php
    namespace IsraelNogueira\Models;
    use IsraelNogueira\galaxyDB\galaxyDB;

    class usuariosModel    extends    galaxyDB    {
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


## EXEMPLOS DE USO<br/>
### Select simples

O exemplo apresenta um `SELECT` básico com um filtro apenas para usuário com `ID=7`.<br/>  
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

    //    Executamos o select puxando os moradores da cidade 11 
    //    e depois filtramos os solteiros
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

```json
{
    "users_1":[
                {
                    "username": "username_01",
                    "email": "exemplo@email.com"
                }
            ],
    "valores":[
                {
                    "VALOR": "100.00"
                }
            ]
}
```

## Insert

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

## MULTIPLOS INSERTS + TRANSACTION + ROLLBACK

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

## INSERT ARRAY + TRANSACTION + ROLLBACK

```php
<?
    //PUXANDO UMA ARRAY
    $users =  new  usuariosModel();
    $users->set_insert_obj(['UID'=>32,'NOME'=>'João', 'IDADE'=>27]);
    $users->prepare_insert();

    //DENTRO DE UM LAÇO
    foreach($_RESULTADO as $OBJ){
        $users->set_insert_obj($OBJ);
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

## UPDATE:

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

## MULTIPLOS UPDATES + TRANSACTION + ROLLBACK:

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

## MULTIPLOS UPDATES COM ARRAYS:

```php
<?php
    //PUXANDO UMA ARRAY
    $users =  new  usuariosModel();
    $users->set_update_obj(['UID'=>32,'NOME'=>'João', 'IDADE'=>27]);
    $users->prepare_update();

    //DENTRO DE UM LAÇO
    foreach($_RESULTADO as $OBJ){
        $users->set_update_obj($OBJ);
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

## DELETE

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
    use IsraelNogueira\galaxyDB\galaxyDB;

    class usuariosModel    extends    galaxyDB    {
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


# STORED PROCEDURES

    Uma Store Procedure, pode ser chamada de duas maneiras.

### 1ª - Função ->SP()

```$usuarios->sp( NOME_DA_SP, ARRAY_PARAMS );```

```php
<?php
    include "vendor\autoload.php";
    use  App\Models\usuariosModel;

    $usuarios = new usuariosModel();
    $usuarios->sp("processaDados",['PARAM0','PARAM1','PARAM2']);
    $usuarios->prepare_sp();
    $usuarios->transaction(function($ERROR){die($ERROR);});
    $usuarios->execQuery();

    $_RESULT = $users->fetch_array();


?>
```

### 2ª - FUNÇÃO MÁGICA 

Você também pode chamar simplesmente adicionando ```sp_ ``` na frente da sua função, 
que a classe automaticamente entende que essa função é uma Stored Procedure;

Exemplo:

```php
<?php
    include "vendor\autoload.php";
    use  App\Models\usuariosModel;

    $usuarios = new usuariosModel();
	$teste->sp_processaDados('PARAM0','PARAM1','PARAM2');
	$teste->sp_sobePontos('PARAM0','PARAM1','PARAM2');
	$teste->prepare_sp();

	$teste->transaction(function($ERROR){die($ERROR);});
	$teste->execQuery();

?>
```

## PARÂMTROS IN OUT  

Todo parâmetro que você inserir com ```@``` no início, 
a classe identifica que é um parâmetro de saída.

```php
<?php
    include "vendor\autoload.php";
    use  App\Models\usuariosModel;

    $usuarios = new usuariosModel();
	$teste->sp_processaDados('PARAM0','@_NOME','@_EMAIL',25);
	$teste->sp_sobePontos(637,'@_NOME');
	$teste->prepare_sp();

	$teste->transaction(function($ERROR){die($ERROR);});
	$teste->execQuery();

	$_RESULT = $teste->params_sp();

?>
```
A variável ```$_RESULT``` representará a seguinte saída:

```json
    {
        "processaDados":{
            "@_NOME":"João da Silva",
            "@_EMAIL":"joao@gmail.com",
        },
        "sobePontos":{
            "@_NOME2":"João da Silva"
        }
    }
```

## PARÂMTROS IN OUT MAIS SELECTS

Caso a sua Store Procedure possúa algum select interno, 
será processado como uma query;

```php
<?php
    include "vendor\autoload.php";
    use  App\Models\usuariosModel;

    $usuarios = new usuariosModel();
	$teste->table("produtos");
	$teste->limit(1);
	$teste->prepare_select("LISTA_PRODUTOS");

	$teste->sp_processaDados('PARAM0','@_NOME','@_EMAIL',25);
	$teste->sp_sobePontos(637,'@_NOME');
	$teste->prepare_sp();

	$teste->transaction(function($ERROR){die($ERROR);});
	$teste->execQuery();

    $_RESULT = $users->fetch_array();
	$_OUTPUT = $teste->params_sp();

?>
```

Resultará em:

    $_RESULT:

```json

    {
        "LISTA_PRODUTOS" : [
                    {
                        "id": 654,
                        "nome": "cadeira de madeira",
                        "valor": 21.5,
                    },
                    {
                        "id": 655,
                        "nome": "Mesa de plástico",
                        "valor": 149.9,
                    }
                ]
    }
```
    $_OUTPUT:

```json
    {
        "processaDados":{
            "@_NOME":"João da Silva",
            "@_EMAIL":"joao@gmail.com",
        },
        "sobePontos":{
            "@_NOME2":"João da Silva"
        }
    }
```

