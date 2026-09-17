# GalaxyDB — Guia Prático para IA
`use IsraelNogueira\galaxyDB\Core\GalaxyDB;`

---

## PADRÃO DE USO — o fluxo real desta lib

O padrão principal é **prepare → transaction → execQuery → fetch**. Não é encadeamento direto.

```php
$db = new GalaxyDB();
$db->table('TABELA');
$db->colum('*');
$db->where('coluna', valor);
$db->prepare_select('param');
$db->transaction(function($error) { die($error); });
$db->execQuery(function($success) {});
$result = $db->fetch_array('param');
```

---

## SELECT

```php
// Padrão completo
$db = new GalaxyDB();
$db->table('users');
$db->colum('id');
$db->colum('name');
$db->colum('email');
$db->where('status', 'active');
$db->prepare_select('resultado');
$db->transaction(function($error) { die($error); });
$db->execQuery(function($success) {});
$result = $db->fetch_array('resultado');

// Simples (sem prepare — para queries rápidas)
$db = new GalaxyDB();
$db->table('users');
$db->where('status', 'active');
$result = $db->select(); // retorna array[]

// Primeiro registro
$db->table('users');
$db->where('email', 'a@a.com');
$row = $db->first(); // retorna ?array

// Com JOIN
$db = new GalaxyDB();
$db->table('TABELA AS T1');
$db->colum('T1.*');
$db->join('LEFT', 'TABELA2 AS T2', 'T2.ID = T1.FK');
$db->where('coluna', valor);
$db->prepare_select('resultado');
$db->transaction(function($error) { die($error); });
$db->execQuery(function($success) {});
$result = $db->fetch_array('resultado');
```

---

## INSERT

```php
// Padrão completo
$db = new GalaxyDB();
$db->table('users');
$db->name  = 'João';
$db->email = 'joao@email.com';
$db->prepare_insert('ins');
$db->transaction(function($error) { die($error); });
$db->execQuery(function($success) {});
$id = $db->lastInsertId(); // retorna lastInsertId

// Simples (sem prepare)
$db = new GalaxyDB();
$db->table('users');
$db->name  = 'João';
$db->email = 'joao@email.com';
$id = $db->insert(); 



// Batch (múltiplas linhas)
$db = new GalaxyDB();
$db->table('users');
$db->insertBatch([
    ['name' => 'João', 'email' => 'j@j.com'],
    ['name' => 'Maria', 'email' => 'm@m.com'],
]);

// ON DUPLICATE KEY UPDATE (upsert)
$db = new GalaxyDB();
$db->table('users');
$db->name  = 'João';
$db->email = 'joao@email.com';
$db->on_duplicate();
$db->prepare_insert('ins');
$db->clear();
$db->transaction(function($error) { die($error); });
$db->execQuery(function($success) {});


// DA UPDATE EM UM OBJETO INTEIRO AO INVES DE COLUNA POR COLUNA 
$db = new GalaxyDB();
$db->table('users');
$db->set_update_obj(['name' => 'João', 'email' => 'j@j.com']);
$db->prepare_update('ins');
$db->transaction(function($error) { die($error); });
$db->execQuery(function($success) {});

// INSERE UM OBJETO INTEIRO AO INVES DE COLUNA POR COLUNA 
$db = new GalaxyDB();
$db->table('users');
$db->set_insert_obj(['name' => 'João', 'email' => 'j@j.com']);
$db->prepare_insert('ins');
$db->transaction(function($error) { die($error); });
$db->execQuery(function($success) {});








```






---

## UPDATE

```php
// Padrão completo
$db = new GalaxyDB();
$db->table('users');
$db->name = 'João Silva';   // valor a atualizar via propriedade dinâmica
$db->where('id', 1);
$db->prepare_update('upd');
$db->transaction(function($error) { die($error); });
$db->execQuery(function($success) {});
$affected = $db->_num_rows['upd'];

// Simples (sem prepare)
$db = new GalaxyDB();
$db->table('users');
$db->name = 'João Silva';
$db->where('id', 1);
$affected = $db->update(); // retorna rowCount
```

---

## DELETE

```php
// Padrão completo
$db = new GalaxyDB();
$db->table('users');
$db->where('id', 1);
$db->prepare_delete('del');
$db->transaction(function($error) { die($error); });
$db->execQuery(function($success) {});
$affected = $db->_num_rows['del'];

// Simples (sem prepare)
$db = new GalaxyDB();
$db->table('users');
$db->where('id', 1);
$affected = $db->delete(); // retorna rowCount
```

---

## BATCH — múltiplas operações juntas

```php
$db = new GalaxyDB();
$db->table('users');

// Prepara INSERT
$db->name = 'João'; $db->email = 'j@j.com';
$db->prepare_insert('ins_joao');

// Prepara UPDATE
$db->name = 'Maria Silva';
$db->where('id', 2)->prepare_update('upd_maria');

// Prepara DELETE
$db->where('ativo', 0)->prepare_delete('del_inativos');

// Prepara SELECT
$db->where('status', 'active')->prepare_select('sel_ativos');

// Executa tudo em transaction
$db->transaction(function($error) {
    throw new \ErrorException($error, 1);
});
$db->execQuery(function($db, $results) {
    $id      = $results['ins_joao']['id'];
    $ativos  = $results['sel_ativos']['data'];
    $linhas  = $results['upd_maria']['affected'];
});
```

---

## DATATABLES

```php
// Padrão com prepare_dataTable → dataTable()
$db = new GalaxyDB();
$db->table('users');
$db->where('status', 'active');
$db->prepare_dataTable(
    ['name', 'email', 'phone'],       // colunas pesquisáveis
    ['name', 'email', 'created_at']   // colunas ordenáveis (opcional)
);
header('Content-Type: application/json');
die(json_encode($db->dataTable()));

// Em classe estendida
class UserModel extends GalaxyDB {
    protected string $table = 'users';

    public function getActiveDataTable(): array {
        return $this->where('status', 'active')
                    ->prepare_dataTable(['name', 'email', 'phone'])
                    ->dataTable();
    }
}
```

---

## WHERE — todos os tipos

```php
$db->where('coluna', $valor);              // coluna = valor
$db->where('coluna', $valor, '>=');        // operador é SEMPRE o 3º parâmetro
$db->where('coluna', $valor, '!=');
$db->whereNull('coluna');                  // IS NULL
$db->whereNotNull('coluna');               // IS NOT NULL
$db->whereIn('coluna', [1, 2, 3]);
$db->whereBetween('coluna', $min, $max);
$db->whereLike('coluna', '%valor%');
$db->orWhere('coluna', $valor);
$db->having('expressao');
```

---

## MODEL (classe estendida)

```php
class UserModel extends GalaxyDB {
    protected string $table          = 'users';
    protected array $columnsBlocked  = ['password', 'token'];
    protected array $columnsEnabled  = []; // só permite essas (se preenchido)

    // conexão customizada (sobrescreve .env)
    protected ?array $customConnectData = [
        'DB_TYPE'     => 'mysql',
        'DB_HOST'     => 'localhost',
        'DB_DATABASE' => 'outro_banco',
    ];
}
```

---

## RAW

```php
$result  = $db->raw('SELECT * FROM users WHERE id = :id', [':id' => 1]);
$result  = $db->query('SELECT * FROM users WHERE id = :id', [':id' => 1]); // alias
$affected = $db->exec('UPDATE users SET active=1 WHERE id = :id', [':id' => 1]);
```

---

## OUTROS ÚTEIS

```php
$db->count();                    // int
$db->exists();                   // bool
$db->pluck('name');              // array de valores de uma coluna
$db->find(1);                    // busca por PK 'id'
$db->findOrFail(1);              // lança Exception se não encontrar
$db->orderBy('name', 'ASC');
$db->limit(10);                  // LIMIT 10
$db->limit(10, 20);              // LIMIT 20,10 — (quantidade, offset)
$db->groupBy('dep_id');
$db->distinct();
$db->setDebug(true);
$db->getLastQuery();
$db->sp_nome_procedure($p1, $p2); // Stored Procedure via mágica
```

---

## ❌ ERROS — NÃO FAZER ISSO

```php
// ❌ operador no lugar errado
$db->where('id', '=', 1);
// ✅
$db->where('id', 1);           // ou
$db->where('id', 1, '=');      // operador é o 3º, não o 2º

// ❌ update com array (não existe)
$db->where('id',1)->update(['name' => 'João']);
// ✅ valores vão via propriedade dinâmica
$db->name = 'João';
$db->where('id', 1);
$db->update();

// ❌ limit invertido
$db->limit(20, 10); // errado se queria pular 20 e pegar 10
// ✅ limit(quantidade, offset)
$db->limit(10, 20); // pula 20, pega 10

// ❌ IS NULL como valor
$db->where('deleted_at', 'IS NULL');
// ✅
$db->whereNull('deleted_at');

// ❌ encadeamento no padrão prepare (não funciona assim)
$db->table('users')->where('id',1)->prepare_select('p')->execQuery()->fetch_array('p');
// ✅ linha por linha como nos snippets acima
```