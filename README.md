# GalaxyDB

ORM PHP moderno, type-safe e com suporte nativo a jQuery DataTables.

## Características

- ✅ **Fluent Interface** - Encadeamento de métodos
- ✅ **Prepared Statements** - Segurança automática
- ✅ **Multi-Database** - MySQL, PostgreSQL, SQLite, Oracle, MSSQL, Firebird
- ✅ **Query Builder** - Construtor de queries intuitivo
- ✅ **DataTables Integration** - Suporte nativo para jQuery DataTables
- ✅ **Type Safe** - Strict types e validações
- ✅ **Transaction Support** - Controle transacional
- ✅ **Connection Pool** - Reutilização de conexões

## Instalação

```bash
composer require israelnogueira/galaxydb
```

## Uso Básico

```php
use IsraelNogueira\galaxyDB\Core\GalaxyDB;

// Criar instância
$db = new GalaxyDB();

// Select
$users = $db->table('users')
            ->where('status', 'active')
            ->orderBy('name', 'ASC')
            ->limit(10)
            ->select();

// Insert
$db->table('users');
$db->name = 'João';
$db->email = 'joao@email.com';
$userId = $db->insert();

// Update
$db->table('users')
   ->where('id', 1)
   ->update(['name' => 'João Silva']);

// Delete
$db->table('users')
   ->where('id', 1)
   ->delete();
```

## Query Builder

### Where Clauses

```php
$db->where('status', 'active');
$db->where('age', 18, '>=');
$db->whereIn('department', ['TI', 'RH', 'Vendas']);
$db->whereBetween('salary', 1000, 5000);
$db->whereLike('name', '%João%');
$db->whereNull('deleted_at');
$db->whereNotNull('email');
$db->orWhere('status', 'pending');
```

### Ordering & Grouping

```php
$db->orderBy('name', 'ASC')
   ->orderBy('created_at', 'DESC')
   ->groupBy('department')
   ->distinct();
```

### Limits & Pagination

```php
$db->limit(10);           // LIMIT 10
$db->limit(10, 20);       // LIMIT 20, 10 (offset, limit)
```

## Métodos Úteis

```php
// Encontrar por ID
$user = $db->table('users')->find(1);

// Encontrar ou falhar
$user = $db->table('users')->findOrFail(1);

// Primeiro registro
$user = $db->table('users')
           ->where('email', 'joao@email.com')
           ->first();

// Contar registros
$count = $db->table('users')
            ->where('status', 'active')
            ->count();

// Verificar existência
$exists = $db->table('users')
             ->where('email', 'joao@email.com')
             ->exists();

// Pegar apenas uma coluna
$names = $db->table('users')->pluck('name');

// Processar em chunks
$db->table('users')->chunk(100, function($users) {
    foreach ($users as $user) {
        // Processar usuário
    }
});
```

## Transações

```php
$db->beginTransaction();

try {
    $db->table('users');
    $db->name = 'João';
    $db->insert();
    
    $db->table('logs');
    $db->action = 'user_created';
    $db->insert();
    
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}
```

## Batch Insert

```php
$users = [
    ['name' => 'João', 'email' => 'joao@email.com'],
    ['name' => 'Maria', 'email' => 'maria@email.com'],
    ['name' => 'Pedro', 'email' => 'pedro@email.com']
];

$db->table('users')->insertBatch($users);
```

## jQuery DataTables Integration

### Uso Básico

```php
// Auto-detecta requisição DataTables e processa
$_EXEMPLO = new GalaxyDB();
$_EXEMPLO->table("users");
$_EXEMPLO->where('status', 'active');
$_EXEMPLO->prepare_dataTable();
$result = $_EXEMPLO->dataTable();

header('Content-Type: application/json');
die(json_encode($result));
```

### Com Colunas Específicas

```php
$db = new GalaxyDB();
$db->table("products");
$db->where('available', 1);
$db->prepare_dataTable(
    ['name', 'sku', 'description'],  // Colunas pesquisáveis
    ['name', 'price', 'created_at']  // Colunas ordenáveis (opcional)
);
$result = $db->dataTable();

echo json_encode($result);
```

### Encadeamento Completo

```php
$response = (new GalaxyDB())
    ->table("orders")
    ->where('status', 'pending')
    ->where('created_at', '2024-01-01', '>=')
    ->prepare_dataTable(['order_number', 'customer', 'total'])
    ->dataTable();

die(json_encode($response));
```

### Resposta DataTables

Quando há requisição DataTables:
```json
{
    "draw": 1,
    "recordsTotal": 1500,
    "recordsFiltered": 45,
    "data": [
        {"id": 1, "name": "João", "email": "joao@example.com"},
        {"id": 2, "name": "Maria", "email": "maria@example.com"}
    ]
}
```

Quando NÃO há requisição DataTables (fallback):
```json
[
    {"id": 1, "name": "João", "email": "joao@example.com"},
    {"id": 2, "name": "Maria", "email": "maria@example.com"}
]
```

### HTML Frontend

```html
<table id="usersTable" class="display">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Email</th>
            <th>Telefone</th>
        </tr>
    </thead>
</table>

<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/users.php',
            type: 'POST'
        },
        columns: [
            { data: 'id' },
            { data: 'name' },
            { data: 'email' },
            { data: 'phone' }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
        }
    });
});
</script>
```

## Classes Estendidas

```php
class User extends GalaxyDB
{
    protected string $table = 'users';
    protected array $columnsBlocked = ['password'];
    
    public function getActive(): array
    {
        return $this->where('status', 'active')
                    ->where('deleted_at', null, 'IS NULL')
                    ->select();
    }
    
    public function getActiveDataTable(): array
    {
        return $this->where('status', 'active')
                    ->prepare_dataTable(['name', 'email', 'phone'])
                    ->dataTable();
    }
}

// Uso
$user = new User();
$activeUsers = $user->getActive();
$dataTableResponse = $user->getActiveDataTable();
```

## Configuração de Conexão

### Via Array

```php
$db = new GalaxyDB([
    'DB_TYPE' => 'mysql',
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'meu_banco',
    'DB_USERNAME' => 'usuario',
    'DB_PASSWORD' => 'senha',
    'DB_CHAR' => 'utf8mb4'
]);
```

### Via Variáveis de Ambiente

```php
// .env
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=meu_banco
DB_USERNAME=usuario
DB_PASSWORD=senha
DB_CHAR=utf8mb4

// PHP
$db = new GalaxyDB();
```

### Conexão Customizada em Classe Estendida

```php
class User extends GalaxyDB
{
    protected string $table = 'users';
    protected ?array $customConnectData = [
        'DB_TYPE' => 'mysql',
        'DB_HOST' => 'localhost',
        'DB_DATABASE' => 'usuarios_db'
    ];
}
```

## Bancos de Dados Suportados

- MySQL / MariaDB
- PostgreSQL
- SQLite
- Oracle
- Microsoft SQL Server
- Firebird

## Raw Queries

```php
// Query com retorno
$result = $db->raw('SELECT * FROM users WHERE id = :id', ['id' => 1]);

// Query sem retorno (INSERT, UPDATE, DELETE)
$affected = $db->exec('UPDATE users SET status = :status', ['status' => 'active']);
```

## Debug

```php
$db->setDebug(true);
$users = $db->table('users')->select();

// Ver última query executada
echo $db->getLastQuery();

// Ver último ID inserido
echo $db->lastInsertId();

// Ver linhas afetadas
echo $db->affectedRows();
```

## Stored Procedures

```php
// Executa stored procedure chamando sp_nome_da_procedure
$result = $db->sp_listar_usuarios(1, 'active');
```

## Licença

MIT

## Autor

Israel Nogueira
