# PHP MySQL

Classe para controlar o MySQL no PHP com facilidade e segurança.
 
## Instalação

A instalação é muito simples. Basta clonar o repositório em sua e criar suas próprias *extensões* como os arquivos em `./exemplos/*.mysql.php`

## Extensão

A extensão é o *preset* do uso da classe abstrata PHP MySQL. Nela serão cadastrados os parâmetros de uso da classe.

```php
/**
 *  Início da extensão da classe mysql.
 */
class livrosBD extends mysql{
    
    /**
     *  Aqui você pode adicionar variáveis globais e traits
     *  Como no exemplo abaixo:
     * 
     *  Treats
     *  use bdLivros1, bdLivros2;
     *  
     *  Variáveis globais
     *  public  $var_publica1, $var_publica2;
     *  private $var_privada1, $var_privada2;
     */
    
    /**
     *  Pré setamos as condições de uso da classe 
     *  no construtor.
     */

    public function __construct(){
        
        /**
         *  Puxamos os presets básicos do construtor da 
         *  classe pai mysql.
         */

        parent::__construct();

        /**
         *  Setamos a qual tabela estamos nos referindo.
         *  
         *  Não é obrigatório, porém sem ela não teremos
         *  acesso a códigos com presets.
         *  
         *  Os parâmetros utilizados são:
         *  -            String NOME_DA_TABELA
         *  - (Opcional) String ALIAS da tabela
         */

        $this->setDefaultTable( "LIVROS", "l" );

        /**
         *  Podemos cadastrar colunas privadas da tabela
         *  padrão de forma que essas nunca sejam mostradas
         *  quando dados forem solicitados.
         *  
         *  Mesmo se forçarmos o uso como:
         *  - SELECT TOKEN FROM LIVROS;
         *  - SELECT * FROM LIVROS;
         *  ainda assim não serão retornados os dados.
         *  
         *  Os parâmetros utilizados são:
         *  caso 1) Array[string] NOME_DAS_COLUNAS
         *  caso 2) ...String     NOME_DAS_COLUNAS
         */

        $this->setProtectedColumns( "TOKEN" );

        /**
         *  O retorno do PHP para a classe nativa mysqli,
         *  utilizada no core da classe mysql é sempre
         *  uma string.
         *  
         *  Para contornar isso, setamos manualmente os
         *  tipos de dados que serão retornados do banco
         *  de dados e assim a classe poderá tratar isso
         *  e retornar no formato correto.
         *  
         *  Os tipos disponíveis são os mesmos utilizados
         *  tanto no PHP quanto no SQL, sempre em lowercase.
         */

        $this->setColumnTypes([
            "ID"            => "integer",
            "DATA_CADASTRO" => "date",
            "ATIVO"         => "boolean"
        ]);
    }

    /**
     *  Você pode criar aqui tanto seus métodos customizados
     *  para essa extensão, quanto reformular métodos do core.
     *  
     *                      !!!!!!!!!!!!!
     *                      !!! AVISO !!!
     *                      !!!!!!!!!!!!!
     * 
     *  Caso algum método do core seja alterado poderão existir
     *  erros durante o uso dessa extensão.
     *  É altamente recomendável que isso não seja feito!
     */
}
```

## Exemplos de uso

### Select

O exemplo apresenta um `SELECT` básico com um filtro apenas para livros com `ID > 10`.

Ao executar o método `exec()` será retornada uma `array` com os resultados da consulta.

Uma `array` vazia será retornada caso a consulta não encontre resultados.

```php
/**
 *  SELECT ID, TITULO, VALOR FROM LIVROS WHERE ID > 10
 */

livrosBD::init()
            ->select( 'ID', 'TITULO', 'VALOR' )
            ->where( 'ID', '>', 10 )
            ->exec()
```

Uma seleção simples pode ser feita apenas inserindo o dado a ser buscado. Nesse caso estamos buscando o livro de `ID = 10` e retornando uma `array` com os valores encontrados.

Uma `array` vazia será retornada caso a consulta não encontre resultados.

```php
/**
 * SELECT * FROM LIVROS WHERE ID = 10
 */

livrosBD::init()->ID( 10 )
```
A busca pode ser feita com mais de um parâmetro:

```php
/**
 * SELECT * FROM LIVROS WHERE TITULO IN ( 'João', 'Maria', 'José' );
 */

livrosBD::init()->TITULO( 'João', 'Maria', 'José' )
```
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
