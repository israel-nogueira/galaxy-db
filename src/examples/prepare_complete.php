<?php

require 'vendor/autoload.php';

use IsraelNogueira\galaxyDB\Core\GalaxyDB;

class UsuariosModel extends GalaxyDB
{
    protected string $table = 'usuarios';
}

// ============================================================================
// EXEMPLO COMPLETO: prepare_insert + prepare_update + prepare_delete
// ============================================================================

$users = new UsuariosModel();

// -------------------------
// 1. PREPARE INSERT
// -------------------------
echo "=== PREPARANDO INSERTS ===\n";

$users->nome = 'João Silva';
$users->email = 'joao@teste.com';
$users->idade = 25;
$users->ativo = 1;
$users->prepare_insert();

$users->nome = 'Maria Santos';
$users->email = 'maria@teste.com';
$users->idade = 30;
$users->ativo = 1;
$users->prepare_insert();

$users->nome = 'Pedro Costa';
$users->email = 'pedro@teste.com';
$users->idade = 28;
$users->ativo = 0; // Inativo - será deletado depois
$users->prepare_insert();

echo "Inserts preparados: " . $users->getPreparedCount() . "\n\n";

// -------------------------
// 2. PREPARE UPDATE
// -------------------------
echo "=== PREPARANDO UPDATES ===\n";

// Atualiza o João
$users->email = 'joao.silva@novodominio.com';
$users->idade = 26;
$users->where('nome', 'João Silva');
$users->prepare_update();

// Atualiza a Maria
$users->ativo = 0;
$users->where('email', 'maria@teste.com');
$users->prepare_update();

echo "Total preparados (inserts + updates): " . $users->getPreparedCount() . "\n\n";

// -------------------------
// 3. PREPARE DELETE
// -------------------------
echo "=== PREPARANDO DELETES ===\n";

// Deleta usuários inativos
$users->where('ativo', 0);
$users->prepare_delete();

// Deleta usuários antigos (exemplo)
$users->where('created_at', '2020-01-01', '<');
$users->prepare_delete();

echo "Total preparados (inserts + updates + deletes): " . $users->getPreparedCount() . "\n\n";

// -------------------------
// 4. VISUALIZAR QUERIES PREPARADAS
// -------------------------
echo "=== QUERIES PREPARADAS ===\n";
print_r($users->getPreparedQueries());
echo "\n";

// -------------------------
// 5. EXECUTAR COM TRANSACTION
// -------------------------
echo "=== EXECUTANDO COM TRANSACTION ===\n";

$users->transaction(function ($ERROR) {
    echo "ERRO CAPTURADO: $ERROR\n";
    throw new ErrorException($ERROR, 1);
});

try {
    $results = $users->execQuery(function($db, $results) {
        echo "\n✅ SUCESSO! Resultados:\n\n";
        
        foreach ($results as $index => $result) {
            echo "Query " . ($index + 1) . ":\n";
            echo "  Tipo: {$result['type']}\n";
            
            switch ($result['type']) {
                case 'insert':
                    echo "  ID inserido: {$result['id']}\n";
                    break;
                    
                case 'update':
                    echo "  Linhas atualizadas: {$result['affected']}\n";
                    break;
                    
                case 'delete':
                    echo "  Linhas deletadas: {$result['affected']}\n";
                    break;
            }
            echo "\n";
        }
        
        return $results;
    });
    
} catch (Exception $e) {
    echo "\n❌ FALHA: " . $e->getMessage() . "\n";
    echo "Transaction foi revertida (ROLLBACK)\n";
}

// ============================================================================
// EXEMPLO 2: MIX COMPLEXO COM WHERE CONDICIONAL
// ============================================================================

echo "\n\n=== EXEMPLO 2: MIX COMPLEXO ===\n";

$users = new UsuariosModel();

// Insert novo usuário
$users->nome = 'Carlos Admin';
$users->email = 'carlos@admin.com';
$users->role = 'admin';
$users->prepare_insert();

// Update todos os admins antigos
$users->ultimo_acesso = date('Y-m-d H:i:s');
$users->where('role', 'admin')
      ->where('id', 1, '!=') // Não atualiza o ID 1
      ->prepare_update();

// Delete usuários sem e-mail verificado há mais de 30 dias
$users->where('email_verificado', 0)
      ->where('created_at', date('Y-m-d', strtotime('-30 days')), '<')
      ->prepare_delete();

// Insert de log da operação
$users->table('logs');
$users->acao = 'limpeza_usuarios';
$users->detalhes = 'Removidos usuários não verificados';
$users->data = date('Y-m-d H:i:s');
$users->prepare_insert();

// Volta para tabela usuarios
$users->table('usuarios');

echo "Operações preparadas: " . $users->getPreparedCount() . "\n";

$users->transaction(function($error) {
    file_put_contents('errors.log', date('Y-m-d H:i:s') . " - $error\n", FILE_APPEND);
});

$users->execQuery(function($db, $results) {
    $inserts = array_filter($results, fn($r) => $r['type'] === 'insert');
    $updates = array_filter($results, fn($r) => $r['type'] === 'update');
    $deletes = array_filter($results, fn($r) => $r['type'] === 'delete');
    
    echo "\n📊 Resumo:\n";
    echo "  Inserções: " . count($inserts) . "\n";
    echo "  Atualizações: " . count($updates) . "\n";
    echo "  Deleções: " . count($deletes) . "\n";
});

// ============================================================================
// EXEMPLO 3: PREPARE COM WHERE COMPLEXO
// ============================================================================

echo "\n\n=== EXEMPLO 3: WHERE COMPLEXO ===\n";

$users = new UsuariosModel();

// Insert condicional (usando INSERT SELECT com WHERE)
$users->nome = 'Usuario Especial';
$users->email = 'especial@test.com';
$users->where('NOW() > "2024-01-01 00:00:00"'); // Só insere se condição for verdadeira
$users->prepare_insert();

// Update com múltiplos WHERE
$users->status = 'premium';
$users->where('idade', 18, '>=')
      ->where('ativo', 1)
      ->where('created_at', date('Y-m-d', strtotime('-1 year')), '>')
      ->prepare_update();

// Delete com WHERE IN (simulado)
$users->whereIn('status', ['banido', 'suspenso', 'inativo'])
      ->prepare_delete();

$users->execQuery();

// ============================================================================
// EXEMPLO 4: SEM TRANSACTION (cada query commita individual)
// ============================================================================

echo "\n\n=== EXEMPLO 4: SEM TRANSACTION ===\n";

$users = new UsuariosModel();

$users->nome = 'Teste 1';
$users->prepare_insert();

$users->nome = 'Teste 2';
$users->prepare_insert();

// SEM chamar transaction() - cada query commita separadamente
$users->execQuery(function($db, $results) {
    echo "Executado sem transaction - commits automáticos\n";
    echo "Total de operações: " . count($results) . "\n";
});

// ============================================================================
// EXEMPLO 5: LIMPAR FILA SEM EXECUTAR
// ============================================================================

echo "\n\n=== EXEMPLO 5: LIMPAR FILA ===\n";

$users = new UsuariosModel();

$users->nome = 'Será cancelado';
$users->prepare_insert();

$users->nome = 'Também cancelado';
$users->prepare_insert();

echo "Preparados: " . $users->getPreparedCount() . "\n";

$users->clearPrepared(); // Limpa tudo

echo "Após clear: " . $users->getPreparedCount() . "\n";

// ============================================================================
// EXEMPLO 6: DEBUG MODE
// ============================================================================

echo "\n\n=== EXEMPLO 6: DEBUG MODE ===\n";

$users = new UsuariosModel();
$users->setDebug(true); // Ativa logging

$users->nome = 'Debug Test';
$users->email = 'debug@test.com';
$users->prepare_insert();

$users->status = 'active';
$users->where('email', 'debug@test.com');
$users->prepare_update();

$users->execQuery(); // Vai logar todas as queries executadas
