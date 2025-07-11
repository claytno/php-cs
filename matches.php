<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Paginação
$page = (int)($_GET['page'] ?? 1);
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Filtros
$statusFilter = $_GET['status'] ?? '';
$searchFilter = $_GET['search'] ?? '';

// Query base
$whereClause = [];
$params = [];

if (!empty($statusFilter)) {
    $whereClause[] = "status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchFilter)) {
    $whereClause[] = "(team1_name LIKE ? OR team2_name LIKE ? OR match_id LIKE ?)";
    $params[] = "%{$searchFilter}%";
    $params[] = "%{$searchFilter}%";
    $params[] = "%{$searchFilter}%";
}

$whereSQL = !empty($whereClause) ? 'WHERE ' . implode(' AND ', $whereClause) : '';

// Obter total de partidas
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM matches {$whereSQL}");
$countStmt->execute($params);
$totalMatches = $countStmt->fetchColumn();

// Obter partidas
$stmt = $pdo->prepare("
    SELECT * FROM matches 
    {$whereSQL}
    ORDER BY created_at DESC 
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$matches = $stmt->fetchAll();

$totalPages = ceil($totalMatches / $perPage);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partidas - MatchZy Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <header class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-orange-500">
                        <i class="fas fa-list mr-2"></i>Gerenciar Partidas
                    </h1>
                    <p class="text-gray-400">Visualize e gerencie todas as partidas</p>
                </div>
                <div class="flex gap-4">
                    <a href="create_match.php" class="bg-orange-600 hover:bg-orange-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Nova Partida
                    </a>
                    <a href="index.php" class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar
                    </a>
                </div>
            </div>
        </header>

        <!-- Filtros -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Buscar</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchFilter) ?>"
                           placeholder="ID, nome dos times..."
                           class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Status</label>
                    <select name="status" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500">
                        <option value="">Todos os status</option>
                        <option value="created" <?= $statusFilter === 'created' ? 'selected' : '' ?>>Criada</option>
                        <option value="loading" <?= $statusFilter === 'loading' ? 'selected' : '' ?>>Carregando</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativa</option>
                        <option value="paused" <?= $statusFilter === 'paused' ? 'selected' : '' ?>>Pausada</option>
                        <option value="finished" <?= $statusFilter === 'finished' ? 'selected' : '' ?>>Finalizada</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
                
                <div class="flex items-end gap-2">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Filtrar
                    </button>
                    <a href="matches.php" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-times mr-2"></i>Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- Lista de Partidas -->
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            <?php if (count($matches) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Match ID
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Times
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Mapa
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Servidor
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Data
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php foreach ($matches as $match): ?>
                        <tr class="hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-mono text-orange-400">
                                    <?= htmlspecialchars($match['match_id']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium">
                                    <?= htmlspecialchars($match['team1_name']) ?>
                                </div>
                                <div class="text-xs text-gray-400">vs</div>
                                <div class="text-sm font-medium">
                                    <?= htmlspecialchars($match['team2_name']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm">
                                    <?= htmlspecialchars($match['current_map'] ?: 'N/A') ?>
                                </div>
                                <?php 
                                $maps = json_decode($match['maps'], true);
                                if ($maps && count($maps) > 1):
                                ?>
                                <div class="text-xs text-gray-400">
                                    +<?= count($maps) - 1 ?> mapas
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?= getStatusColor($match['status']) ?>-100 text-<?= getStatusColor($match['status']) ?>-800">
                                    <?= ucfirst($match['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm">
                                    <?= htmlspecialchars($match['server_ip'] . ':' . $match['server_port']) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm">
                                    <?= date('d/m/Y', strtotime($match['created_at'])) ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?= date('H:i', strtotime($match['created_at'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <a href="match_details.php?id=<?= $match['id'] ?>" 
                                       class="text-blue-400 hover:text-blue-300" title="Ver Detalhes">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if (in_array($match['status'], ['created', 'loading', 'active', 'paused'])): ?>
                                    <a href="match_control.php?id=<?= $match['id'] ?>" 
                                       class="text-green-400 hover:text-green-300" title="Controlar">
                                        <i class="fas fa-gamepad"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="match_config.php?id=<?= $match['id'] ?>" 
                                       class="text-purple-400 hover:text-purple-300" title="Configuração">
                                        <i class="fas fa-cog"></i>
                                    </a>
                                    
                                    <?php if ($match['status'] === 'finished'): ?>
                                    <a href="match_stats.php?id=<?= $match['id'] ?>" 
                                       class="text-yellow-400 hover:text-yellow-300" title="Estatísticas">
                                        <i class="fas fa-chart-bar"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($match['status'], ['created', 'paused'])): ?>
                                    <button onclick="deleteMatch(<?= $match['id'] ?>)" 
                                            class="text-red-400 hover:text-red-300" title="Excluir">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <?php if ($totalPages > 1): ?>
            <div class="bg-gray-700 px-6 py-3 flex items-center justify-between">
                <div class="text-sm text-gray-400">
                    Mostrando <?= count($matches) ?> de <?= $totalMatches ?> partidas
                </div>
                
                <div class="flex space-x-1">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($searchFilter) ?>" 
                       class="px-3 py-1 bg-gray-600 hover:bg-gray-500 rounded text-sm">
                        Anterior
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($searchFilter) ?>" 
                       class="px-3 py-1 <?= $i === $page ? 'bg-orange-600' : 'bg-gray-600 hover:bg-gray-500' ?> rounded text-sm">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($searchFilter) ?>" 
                       class="px-3 py-1 bg-gray-600 hover:bg-gray-500 rounded text-sm">
                        Próxima
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="text-center py-12">
                <i class="fas fa-search text-4xl text-gray-600 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-400 mb-2">Nenhuma partida encontrada</h3>
                <p class="text-gray-500 mb-6">
                    <?php if (!empty($statusFilter) || !empty($searchFilter)): ?>
                        Tente ajustar os filtros ou
                    <?php endif; ?>
                    <a href="create_match.php" class="text-orange-400 hover:text-orange-300">criar uma nova partida</a>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function deleteMatch(matchId) {
            if (confirm('Tem certeza que deseja excluir esta partida?')) {
                fetch('actions/delete_match.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ match_id: matchId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erro ao excluir partida: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao excluir partida');
                });
            }
        }
    </script>
</body>
</html>
