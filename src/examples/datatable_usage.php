<?php

/**
 * ===================================================================
 * EXEMPLOS DE USO - DataTables com GalaxyDB
 * ===================================================================
 */

require_once 'vendor/autoload.php';

use IsraelNogueira\galaxyDB\Core\GalaxyDB;

// ===================================================================
// EXEMPLO 1: Uso Básico
// ===================================================================

$db = new GalaxyDB();
$db->table('users');

// Colunas pesquisáveis
$searchableColumns = ['name', 'email', 'city'];

// Processa requisição DataTables
$response = $db->dataTable($_POST, $searchableColumns);

// Retorna JSON para o DataTables
header('Content-Type: application/json');
echo json_encode($response);


// ===================================================================
// EXEMPLO 2: Com Filtros Adicionais
// ===================================================================

$db = new GalaxyDB();
$db->table('users')
   ->where('status', 'active')
   ->where('deleted_at', null, 'IS NULL');

$response = $db->dataTable($_POST, ['name', 'email', 'phone']);

echo json_encode($response);


// ===================================================================
// EXEMPLO 3: Com Classe Estendida
// ===================================================================

class User extends GalaxyDB
{
    protected string $table = 'users';
    
    public function getActiveUsers(): array
    {
        return $this->where('status', 'active')
                    ->dataTable(
                        $_POST,
                        ['name', 'email', 'department'],
                        ['name', 'created_at'] // Colunas ordenáveis
                    );
    }
}

$user = new User();
$response = $user->getActiveUsers();
echo json_encode($response);


// ===================================================================
// EXEMPLO 4: Auto-detecta Request
// ===================================================================

$db = new GalaxyDB();
$db->table('products')
   ->where('available', 1);

// Detecta automaticamente $_POST ou $_GET
$response = $db->dataTableAuto(['name', 'sku', 'category']);

echo json_encode($response);


// ===================================================================
// EXEMPLO 5: Endpoint Completo com Validação
// ===================================================================

function getUsersDataTable(): void
{
    try {
        // Validação básica
        if (!isset($_POST['draw'])) {
            throw new Exception('Invalid DataTables request');
        }

        $db = new GalaxyDB();
        $db->table('users');

        // Filtros condicionais do formulário
        if (!empty($_POST['department'])) {
            $db->where('department', $_POST['department']);
        }

        if (!empty($_POST['status'])) {
            $db->where('status', $_POST['status']);
        }

        // Processa DataTables
        $response = $db->dataTable(
            $_POST,
            ['name', 'email', 'phone', 'city', 'department'],
            ['name', 'email', 'created_at', 'department']
        );

        // Retorna JSON
        header('Content-Type: application/json');
        echo json_encode($response);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage()
        ]);
    }
}

getUsersDataTable();


// ===================================================================
// EXEMPLO 6: Com Joins
// ===================================================================

$db = new GalaxyDB();

// Query com join (usando raw para exemplo)
$db->raw("
    SELECT 
        u.id,
        u.name,
        u.email,
        d.name as department_name
    FROM users u
    LEFT JOIN departments d ON d.id = u.department_id
    WHERE u.deleted_at IS NULL
");

// Note: Para joins complexos, é melhor usar subquery ou view
// e depois aplicar o DataTable


// ===================================================================
// EXEMPLO 7: Response Customizado
// ===================================================================

$db = new GalaxyDB();
$db->table('orders')
   ->where('status', 'pending');

$response = $db->dataTable($_POST, ['order_number', 'customer', 'total']);

// Adiciona informações extras
$response['summary'] = [
    'total_amount' => 15000.50,
    'pending_count' => $response['recordsFiltered']
];

echo json_encode($response);


// ===================================================================
// HTML - Tabela DataTables
// ===================================================================
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
</head>
<body>
    <table id="usersTable" class="display">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Email</th>
                <th>Telefone</th>
                <th>Cidade</th>
                <th>Departamento</th>
                <th>Ações</th>
            </tr>
        </thead>
    </table>

    <script>
    $(document).ready(function() {
        $('#usersTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: 'api/users-datatable.php',
                type: 'POST'
            },
            columns: [
                { data: 'id' },
                { data: 'name' },
                { data: 'email' },
                { data: 'phone' },
                { data: 'city' },
                { data: 'department' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return `
                            <button onclick="editUser(${row.id})">Editar</button>
                            <button onclick="deleteUser(${row.id})">Excluir</button>
                        `;
                    }
                }
            ],
            order: [[1, 'asc']],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/pt-BR.json'
            }
        });
    });

    function editUser(id) {
        window.location.href = `edit.php?id=${id}`;
    }

    function deleteUser(id) {
        if (confirm('Deseja realmente excluir?')) {
            // Implementar delete
        }
    }
    </script>
</body>
</html>
