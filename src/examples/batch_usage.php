<?php

/**
 * EXEMPLO DE USO - QueryBatch com prepare_insert + execQuery + transaction
 */

require 'vendor/autoload.php';

use IsraelNogueira\galaxyDB\Core\GalaxyDB;

// ============================================================================
// EXEMPLO 1: Múltiplos INSERTS com Transaction
// ============================================================================

class UsuariosModel extends GalaxyDB
{
    protected string $table = 'usuarios';
}

$users = new UsuariosModel();

// Primeiro insert
$users->nome = 'João Silva';
$users->email = 'joao@teste.com';
$users->idade = 25;
$users->prepare_insert();

// Segundo insert
$users->nome = 'Maria Santos';
$users->email = 'maria@teste.com';
$users->idade = 30;
$users->prepare_insert();

// Terceiro insert com WHERE (convertido para INSERT SELECT)
$users->nome = 'Pedro Costa';
$users->email = 'pedro@teste.com';
$users->idade = 28;
$users->where('NOW() > "2024-01-01 00:00:00"');
$users->prepare_insert();

// Define callback de erro para transaction
$users->transaction(function ($ERROR) {
    error_log("ROLLBACK: " . $ERROR);
    throw new ErrorException($ERROR, 1);
});

// Executa todas as queries preparadas
try {
    $users->execQuery(function($db, $results) {
        echo "Sucesso! IDs inseridos:\n";
        foreach ($results as $result) {
            if ($result['type'] === 'insert') {
                echo "- ID: {$result['id']}\n";
            }
        }
        return $results;
    });
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

// ============================================================================
// EXEMPLO 2: Mix de INSERT + UPDATE + DELETE
// ============================================================================

$users = new UsuariosModel();

// Insere novo usuário
$users->nome = 'Ana Lima';
$users->email = 'ana@teste.com';
$users->prepare_insert();

// Atualiza usuário existente
$users->nome = 'Ana Paula Lima';
$users->where('id', 10)
      ->prepare_update();

// Deleta usuário inativo
$users->where('ativo', 0)
      ->where('created_at', '2020-01-01', '<')
      ->prepare_delete();

// Executa tudo em uma transação
$users->transaction(function($error) {
    echo "ERRO: $error\n";
});

$results = $users->execQuery();

// ============================================================================
// EXEMPLO 3: Batch com SELECT
// ============================================================================

$users = new UsuariosModel();

$users->nome = 'Teste 1';
$users->email = 'teste1@test.com';
$users->prepare_insert();

$users->nome = 'Teste 2';
$users->email = 'teste2@test.com';
$users->prepare_insert();

// Adiciona um SELECT no final
$users->where('email', '%@test.com', 'LIKE')
      ->prepare_select('id, nome, email');

$results = $users->execQuery(function($db, $results) {
    foreach ($results as $result) {
        if ($result['type'] === 'select') {
            print_r($result['data']);
        }
    }
});

// ============================================================================
// EXEMPLO 4: Verificar queries preparadas antes de executar
// ============================================================================

$users = new UsuariosModel();

$users->nome = 'Debug Test';
$users->prepare_insert();

echo "Queries preparadas: " . $users->getPreparedCount() . "\n";
print_r($users->getPreparedQueries());

// Limpar sem executar
$users->clearPrepared();

// ============================================================================
// EXEMPLO 5: Uso avançado - Callback customizado
// ============================================================================

$users = new UsuariosModel();

for ($i = 1; $i <= 5; $i++) {
    $users->nome = "User $i";
    $users->email = "user{$i}@batch.com";
    $users->prepare_insert();
}

$users->transaction(function($error) {
    // Log personalizado
    file_put_contents('errors.log', date('Y-m-d H:i:s') . " - $error\n", FILE_APPEND);
});

$users->execQuery(function($db, $results) use ($users) {
    $insertCount = array_filter($results, fn($r) => $r['type'] === 'insert');
    
    echo "Total inserido: " . count($insertCount) . "\n";
    echo "Último ID: " . $db->lastInsertId() . "\n";
    
    // Busca os registros inseridos
    $inserted = $db->table('usuarios')
                   ->whereIn('id', array_column($insertCount, 'id'))
                   ->select();
    
    return $inserted;
});

// ============================================================================
// EXEMPLO 6: Sem transaction (auto-commit)
// ============================================================================

$users = new UsuariosModel();

$users->nome = 'No Transaction';
$users->email = 'notx@test.com';
$users->prepare_insert();

// Executa SEM transaction - cada query commitada individualmente
$users->execQuery();

// ============================================================================
// EXEMPLO 7: Debug mode
// ============================================================================

$users = new UsuariosModel();
$users->setDebug(true);

$users->nome = 'Debug User';
$users->email = 'debug@test.com';
$users->prepare_insert();

$users->execQuery(); // Vai logar a query executada
