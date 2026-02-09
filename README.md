# 🌌 GalaxyDB v2.0 - Database Layer Seguro e Poderoso

[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL--3.0-green)](LICENSE)
[![Security](https://img.shields.io/badge/SQL%20Injection-ZERO-brightgreen)](README.md)

> **ORM/Query Builder leve, seguro e multi-database para PHP 8.1+**

---

## 📑 Índice

- [Características](#-características)
- [Instalação](#-instalação)
- [Conexão](#-conexão)
- [Uso Básico](#-uso-básico)
- [Sistema de Batch](#-sistema-de-batch-prepare--execquery)
- [Query Builder Avançado](#-query-builder-avançado)
- [Transações](#-transações)
- [Métodos Auxiliares](#-métodos-auxiliares)
- [Segurança](#-segurança)
- [Performance](#-performance)
- [Breaking Changes](#-breaking-changes)
- [Exemplos Completos](#-exemplos-completos)

---

## 🚀 Características

### ✅ Segurança Total
- ✅ **100% Prepared Statements** - Zero SQL Injection
- ✅ **Validação de Identificadores** - Proteção contra nomes maliciosos
- ✅ **Tipagem Automática** - PDO types corretos (INT, BOOL, NULL, STR)
- ✅ **Connection Pooling** - Reutilização segura de conexões

### ⚡ Performance
- ✅ **Cache de Conexões** - Até 30% mais rápido
- ✅ **Batch Operations** - Múltiplas queries em uma transação
- ✅ **Lazy Loading** - Conexão sob demanda
- ✅ **Prepared Statement Reuse** - Otimização automática

### 🎯 Produtividade
- ✅ **Fluent Interface** - Código limpo e legível
- ✅ **Multi-Database** - MySQL, PostgreSQL, SQLite, Oracle, MSSQL, Firebird
- ✅ **Magic Methods** - `$db->coluna = 'valor'`
- ✅ **Stored Procedures** - Suporte nativo via `SP_*`

### 🔧 Flexibilidade
- ✅ **Dual Mode** - Execute direto ou em batch
- ✅ **Custom Queries** - SQL raw quando necessário
- ✅ **Transaction Control** - Manual ou automático
- ✅ **Debug Mode** - Log completo de queries

---

## 📦 Instalação

```bash
composer require israelnogueira/galaxydb
```

Ou clone o repositório:

```bash
git clone https://github.com/israelnogueira/galaxydb.git
```

---

## 🔌 Conexão

### Variáveis de Ambiente (.env)

```env
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=meu_banco
DB_USERNAME=root
DB_PASSWORD=senha123
DB_CHAR=utf8mb4
```

### Conexão Básica

```php
use IsraelNogueira\galaxyDB\Core\GalaxyDB;

// Usando .env
$db = new GalaxyDB();

// Ou passando configuração
$db = new GalaxyDB([
    'DB_TYPE' => 'mysql',
    'DB_HOST' => 'localhost',
    'DB_DATABASE' => 'meu_banco',
    'DB_USERNAME' => 'root',
    'DB_PASSWORD' => 'senha123'
]);
```

### Model Customizado

```php
class UsuariosModel extends GalaxyDB
{
    protected string $table = 'usuarios';
    
    // Conexão customizada (opcional)
    protected array $customConnectData = [
        'DB_TYPE' => 'mysql',
        'DB_HOST' => 'db.example.com',
        'DB_DATABASE' => 'usuarios_db',
        'DB_USERNAME' => 'api_user',
        'DB_PASSWORD' => 'secret'
    ];
}

$users = new UsuariosModel();
```

### Bancos Suportados

| Database | Driver | Porta Padrão |
|----------|--------|--------------|
| MySQL / MariaDB | `mysql` | 3306 |
| PostgreSQL | `pgsql` | 5432 |
| SQLite | `sqlite` | - |
| Oracle | `oracle` | 1521 |
| SQL Server | `mssql` / `sqlsrv` | 1433 |
| Firebird | `ibase` / `fbird` | 3050 |

---

## 📖 Uso Básico

### SELECT

```php
$db = new GalaxyDB();

// SELECT * FROM usuarios
$users = $db->table('usuarios')->select();

// SELECT nome, email FROM usuarios
$users = $db->table('usuarios')->select('nome, email');

// Com WHERE
$users = $db->table('usuarios')
    ->where('ativo', 1)
    ->select();

// Primeiro resultado
$user = $db->table('usuarios')
    ->where('id', 123)
    ->first();

// Contar
$total = $db->table('usuarios')
    ->where('ativo', 1)
    ->count();
```

### INSERT

```php
// Método 1: Magic properties
$db->table('usuarios');
$db->nome = 'João Silva';
$db->email = 'joao@example.com';
$db->idade = 30;
$lastId = $db->insert();

echo "ID inserido: $lastId";

// Método 2: Batch insert
$db->insertBatch([
    ['nome' => 'Maria', 'email' => 'maria@test.com'],
    ['nome' => 'Pedro', 'email' => 'pedro@test.com'],
    ['nome' => 'Ana', 'email' => 'ana@test.com']
]);
```

### UPDATE

```php
$db->table('usuarios');
$db->nome = 'João Pedro Silva';
$db->email = 'joao.pedro@example.com';

$affected = $db->where('id', 123)->update();

echo "Linhas atualizadas: $affected";
```

### DELETE

```php
$affected = $db->table('usuarios')
    ->where('ativo', 0)
    ->where('created_at', '2020-01-01', '<')
    ->delete();

echo "Linhas deletadas: $affected";
```

---

## 🔥 Sistema de Batch (prepare + execQuery)

### Conceito

Prepare múltiplas operações e execute todas em **UMA transação atômica**.

### Exemplo Básico

```php
$users = new UsuariosModel();

// Prepara 3 inserts
$users->nome = 'João';
$users->email = 'joao@test.com';
$users->prepare_insert();

$users->nome = 'Maria';
$users->email = 'maria@test.com';
$users->prepare_insert();

$users->nome = 'Pedro';
$users->email = 'pedro@test.com';
$users->prepare_insert();

// Executa tudo de uma vez
$users->execQuery();
```

### Com Transaction e Rollback

```php
$users = new UsuariosModel();

$users->nome = 'Teste 1';
$users->prepare_insert();

$users->nome = 'Teste 2';
$users->prepare_insert();

// Define handler de erro
$users->transaction(function ($ERROR) {
    error_log("ROLLBACK: $ERROR");
    throw new ErrorException($ERROR, 1);
});

// Executa - se QUALQUER query falhar, faz ROLLBACK de TUDO
try {
    $users->execQuery();
    echo "✅ Tudo inserido com sucesso!";
} catch (Exception $e) {
    echo "❌ Erro: Nenhum dado foi salvo (rollback automático)";
}
```

### Mix de Operações (INSERT + UPDATE + DELETE)

```php
$users = new UsuariosModel();

// INSERT
$users->nome = 'Novo Usuario';
$users->email = 'novo@test.com';
$users->prepare_insert();

// UPDATE
$users->status = 'ativo';
$users->where('email', 'antigo@test.com')
      ->prepare_update();

// DELETE
$users->where('ativo', 0)
      ->where('created_at', '2020-01-01', '<')
      ->prepare_delete();

// INSERT em outra tabela
$users->table('logs');
$users->acao = 'limpeza_usuarios';
$users->data = date('Y-m-d H:i:s');
$users->prepare_insert();

// Executa os 4 em UMA transação
$users->transaction(fn($e) => throw new Error($e));
$users->execQuery(function($db, $results) {
    echo "Total de operações: " . count($results) . "\n";
    print_r($results);
});
```

### Callback de Sucesso

```php
$users->execQuery(function($db, $results) {
    // $results é um array com info de cada operação
    foreach ($results as $result) {
        switch ($result['type']) {
            case 'insert':
                echo "Inserido ID: {$result['id']}\n";
                break;
            case 'update':
                echo "Atualizado: {$result['affected']} linhas\n";
                break;
            case 'delete':
                echo "Deletado: {$result['affected']} linhas\n";
                break;
        }
    }
    
    return $results;
});
```

### Métodos de Controle

```php
// Quantas queries preparadas?
$count = $users->getPreparedCount();

// Ver queries antes de executar
$queries = $users->getPreparedQueries();
print_r($queries);

// Cancelar sem executar (útil para debug ou validação)
$users->clearPrepared();
```

---

## 🎯 Query Builder Avançado

### WHERE Variações

```php
// WHERE simples
$db->where('idade', 18, '>=');

// WHERE OR
$db->where('status', 'ativo')
   ->orWhere('status', 'pendente');

// WHERE IN
$db->whereIn('role', ['admin', 'moderator', 'editor']);

// WHERE BETWEEN
$db->whereBetween('idade', 18, 65);

// WHERE LIKE
$db->whereLike('nome', '%Silva%');

// WHERE NULL
$db->whereNull('deleted_at');

// WHERE NOT NULL
$db->whereNotNull('email_verificado');
```

### ORDER BY

```php
// Simples
$db->orderBy('created_at', 'DESC');

// Múltiplos
$db->orderBy('status', 'ASC')
   ->orderBy('created_at', 'DESC');
```

### LIMIT e OFFSET

```php
// LIMIT 10
$db->limit(10);

// LIMIT 10 OFFSET 20
$db->limit(10, 20);
```

### GROUP BY

```php
$db->groupBy('categoria')
   ->select('categoria, COUNT(*) as total');
```

### DISTINCT

```php
$db->distinct()
   ->select('email');
```

### Exemplo Completo

```php
$users = $db->table('usuarios')
    ->distinct()
    ->where('ativo', 1)
    ->whereIn('role', ['admin', 'editor'])
    ->whereBetween('idade', 18, 60)
    ->whereLike('email', '%@empresa.com')
    ->whereNotNull('email_verificado')
    ->orderBy('created_at', 'DESC')
    ->limit(50)
    ->select('id, nome, email, role');
```

---

## 💾 Transações

### Automática (com prepare)

```php
$users->prepare_insert();
$users->prepare_update();
$users->transaction(fn($e) => throw new Error($e));
$users->execQuery(); // Auto commit ou rollback
```

### Manual

```php
try {
    $db->beginTransaction();
    
    $db->table('usuarios');
    $db->nome = 'Teste';
    $db->insert();
    
    $db->table('logs');
    $db->acao = 'usuario_criado';
    $db->insert();
    
    $db->commit();
    
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}
```

---

## 🛠️ Métodos Auxiliares

### find() e findOrFail()

```php
// Busca por ID (padrão)
$user = $db->table('usuarios')->find(123);

// Busca por outra chave
$user = $db->table('usuarios')->find('joao@test.com', 'email');

// Lança exception se não encontrar
$user = $db->table('usuarios')->findOrFail(123);
```

### exists()

```php
if ($db->table('usuarios')->where('email', 'teste@test.com')->exists()) {
    echo "Email já cadastrado!";
}
```

### pluck()

```php
// Retorna array de valores
$emails = $db->table('usuarios')
    ->where('ativo', 1)
    ->pluck('email');

// ['joao@test.com', 'maria@test.com', ...]
```

### chunk()

```php
// Processa em lotes (evita memory overflow)
$db->table('usuarios')
   ->chunk(100, function($users) {
       foreach ($users as $user) {
           // Processa cada usuário
           enviarEmail($user['email']);
       }
       
       // Retornar false para parar
       if ($alguma_condicao) {
           return false;
       }
   });
```

### raw() / query()

```php
// Query customizada
$results = $db->raw("
    SELECT u.*, COUNT(p.id) as total_posts
    FROM usuarios u
    LEFT JOIN posts p ON p.user_id = u.id
    WHERE u.ativo = :ativo
    GROUP BY u.id
", [':ativo' => 1]);
```

### lastInsertId() e affectedRows()

```php
$db->insert();
echo "Último ID: " . $db->lastInsertId();

$db->update();
echo "Linhas afetadas: " . $db->affectedRows();
```

### getLastQuery()

```php
$db->table('usuarios')->where('id', 123)->select();
echo $db->getLastQuery();
// SELECT * FROM `usuarios` WHERE `id` = :w_0
```

---

## 🔒 Segurança

### Antes (v1.x) - VULNERÁVEL ❌

```php
// SQL INJECTION POSSÍVEL!
$id = "1 OR 1=1";
$sql = "SELECT * FROM users WHERE id = " . $db->connection->quote($id);
```

### Agora (v2.0) - SEGURO ✅

```php
// PREPARED STATEMENTS
$id = "1 OR 1=1";
$user = $db->table('users')->where('id', $id)->first();
// Executado como: WHERE `id` = :w_0
// Binding: [':w_0' => '1 OR 1=1']
// Resultado: null (seguro!)
```

### Validação de Identificadores

```php
// Válido ✅
$db->table('usuarios_ativos');
$db->where('email_verificado', 1);

// INVÁLIDO ❌ - Lança Exception
try {
    $db->table('usuarios; DROP TABLE usuarios;--');
} catch (Exception $e) {
    echo "Identificador inválido detectado!";
}
```

### Tipagem Automática

```php
$db->idade = 30;        // PDO::PARAM_INT
$db->ativo = true;      // PDO::PARAM_BOOL
$db->observacoes = null; // PDO::PARAM_NULL
$db->nome = "João";     // PDO::PARAM_STR
```

---

## ⚡ Performance

### Connection Pooling

```php
// Primeira conexão
$db1 = new GalaxyDB(['DB_DATABASE' => 'app']);

// Reutiliza a mesma conexão (cache)
$db2 = new GalaxyDB(['DB_DATABASE' => 'app']);

// Nova conexão (config diferente)
$db3 = new GalaxyDB(['DB_DATABASE' => 'logs']);
```

### Batch vs Individual

```php
// ❌ LENTO: 100 queries individuais
for ($i = 0; $i < 100; $i++) {
    $db->nome = "User $i";
    $db->insert(); // 100x round-trip ao DB
}

// ✅ RÁPIDO: 1 transação com 100 inserts
for ($i = 0; $i < 100; $i++) {
    $db->nome = "User $i";
    $db->prepare_insert();
}
$db->execQuery(); // 1x round-trip
```

### Chunk para Grandes Volumes

```php
// ❌ MEMORY OVERFLOW
$users = $db->table('usuarios')->select(); // 1 milhão de linhas

// ✅ SEGURO
$db->table('usuarios')->chunk(1000, function($users) {
    // Processa 1000 por vez
});
```

---

## ⚠️ Breaking Changes

### v1.x → v2.0

| Antes (v1.x) | Agora (v2.0) | Motivo |
|--------------|--------------|--------|
| `$db->debug = true` | `$db->setDebug(true)` | Encapsulamento |
| `$db->query($sql)` | `$db->query($sql, $bindings)` | Prepared statements |
| Retorna `false` em erro | Lança `PDOException` | Error handling moderno |
| `quote()` para escape | Prepared statements | Segurança |

### Migração de Código

```php
// ANTES
$db->debug = true;
$db->query("SELECT * FROM users WHERE id = " . $id);

// DEPOIS
$db->setDebug(true);
$db->query("SELECT * FROM users WHERE id = :id", [':id' => $id]);
// OU melhor ainda:
$db->table('users')->where('id', $id)->select();
```

---

## 📚 Exemplos Completos

### CRUD Completo

```php
class ProdutosModel extends GalaxyDB
{
    protected string $table = 'produtos';
}

$produtos = new ProdutosModel();

// CREATE
$produtos->nome = 'Notebook Dell';
$produtos->preco = 3500.00;
$produtos->estoque = 10;
$id = $produtos->insert();

// READ
$produto = $produtos->find($id);
$todos = $produtos->where('estoque', 0, '>')->select();

// UPDATE
$produtos->preco = 3200.00;
$produtos->where('id', $id)->update();

// DELETE
$produtos->where('id', $id)->delete();
```

### Sistema de Blog

```php
class PostsModel extends GalaxyDB
{
    protected string $table = 'posts';
}

$posts = new PostsModel();

// Criar post e atualizar contador do autor em UMA transação
$posts->titulo = 'Meu Primeiro Post';
$posts->conteudo = 'Lorem ipsum...';
$posts->autor_id = 123;
$posts->prepare_insert();

$posts->table('usuarios');
$posts->total_posts = 'total_posts + 1'; // SQL raw
$posts->where('id', 123)
      ->prepare_update();

$posts->transaction(fn($e) => throw new Error($e));

$posts->execQuery(function($db, $results) {
    $postId = $results[0]['id'];
    echo "Post $postId criado com sucesso!";
});
```

### Relatório com Aggregação

```php
$relatorio = $db->table('vendas')
    ->select('
        categoria,
        COUNT(*) as total_vendas,
        SUM(valor) as faturamento,
        AVG(valor) as ticket_medio
    ')
    ->where('created_at', date('Y-m-01'), '>=')
    ->groupBy('categoria')
    ->orderBy('faturamento', 'DESC')
    ->select();
```

### Import de CSV em Batch

```php
$csv = fopen('usuarios.csv', 'r');
$header = fgetcsv($csv);

$users = new UsuariosModel();

while (($row = fgetcsv($csv)) !== false) {
    $users->nome = $row[0];
    $users->email = $row[1];
    $users->telefone = $row[2];
    $users->prepare_insert();
}

fclose($csv);

$users->transaction(fn($e) => throw new Error("Import falhou: $e"));

$users->execQuery(function($db, $results) {
    echo "✅ " . count($results) . " usuários importados!";
});
```

### Soft Delete

```php
class SoftDeleteModel extends GalaxyDB
{
    protected string $table = 'usuarios';
    
    public function softDelete(): int
    {
        $this->deleted_at = date('Y-m-d H:i:s');
        return $this->update();
    }
    
    public function restore(): int
    {
        $this->deleted_at = null;
        return $this->update();
    }
    
    public function withTrashed(): self
    {
        // Não filtra deleted_at
        return $this;
    }
    
    public function onlyTrashed(): self
    {
        return $this->whereNotNull('deleted_at');
    }
}

$users = new SoftDeleteModel();

// Soft delete
$users->where('id', 123)->softDelete();

// Buscar incluindo deletados
$all = $users->withTrashed()->select();

// Buscar APENAS deletados
$deleted = $users->onlyTrashed()->select();

// Restaurar
$users->where('id', 123)->restore();
```

---

## 🐛 Debug e Logging

### Debug Mode

```php
$db->setDebug(true);

$db->table('usuarios')->where('id', 123)->select();
// Loga: SELECT * FROM `usuarios` WHERE `id` = :w_0
// Bindings: [':w_0' => 123]
```

### Custom Logger

```php
class MyDB extends GalaxyDB
{
    protected function logQuery(string $sql, array $bindings = []): void
    {
        $log = date('Y-m-d H:i:s') . " | $sql | " . json_encode($bindings) . "\n";
        file_put_contents('queries.log', $log, FILE_APPEND);
    }
    
    protected function logError(string $error): void
    {
        $log = date('Y-m-d H:i:s') . " | ERROR: $error\n";
        file_put_contents('errors.log', $log, FILE_APPEND);
    }
}
```

---

## 📞 Suporte

- **Issues**: [GitHub Issues](https://github.com/israelnogueira/galaxydb/issues)
- **Email**: israel@feats.com
- **Docs**: [Documentação Completa](https://galaxydb.docs.com)

---

## 📄 Licença

GPL-3.0-or-later

---

## 🙏 Contribuindo

Pull requests são bem-vindos! Para mudanças grandes, abra uma issue primeiro.

```bash
git clone https://github.com/israelnogueira/galaxydb.git
cd galaxydb
composer install
phpunit
```

---

## 🏆 Roadmap

- [ ] Query caching
- [ ] Relationships (hasMany, belongsTo)
- [ ] Events/Observers
- [ ] Schema Builder
- [ ] Migrations
- [ ] Seeders
- [ ] Eager loading
- [ ] Read replicas support

---

**Feito com ❤️ por Israel Nogueira**
