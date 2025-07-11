<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Filtros
$matchFilter = $_GET['match'] ?? '';
$eventTypeFilter = $_GET['event_type'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Query base
$whereClause = [];
$params = [];

if (!empty($matchFilter)) {
    $whereClause[] = "me.match_id LIKE ?";
    $params[] = "%{$matchFilter}%";
}

if (!empty($eventTypeFilter)) {
    $whereClause[] = "me.event_type = ?";
    $params[] = $eventTypeFilter;
}

$whereSQL = !empty($whereClause) ? 'WHERE ' . implode(' AND ', $whereClause) : '';

// Obter eventos
$stmt = $pdo->prepare("
    SELECT me.*, m.team1_name, m.team2_name, m.status as match_status
    FROM match_events me
    LEFT JOIN matches m ON me.match_id = m.match_id
    {$whereSQL}
    ORDER BY me.timestamp DESC 
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$events = $stmt->fetchAll();

// Obter total de eventos
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM match_events me {$whereSQL}");
$countStmt->execute($params);
$totalEvents = $countStmt->fetchColumn();

$totalPages = ceil($totalEvents / $perPage);

// Obter tipos de eventos únicos
$eventTypesStmt = $pdo->prepare("SELECT DISTINCT event_type FROM match_events ORDER BY event_type");
$eventTypesStmt->execute();
$eventTypes = $eventTypesStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Eventos - MatchZy Manager</title>
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
                        <i class="fas fa-file-alt mr-2"></i>Logs de Eventos
                    </h1>
                    <p class="text-gray-400">Visualize todos os eventos das partidas em tempo real</p>
                </div>
                <div class="flex gap-4">
                    <button onclick="location.reload()" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-sync mr-2"></i>Atualizar
                    </button>
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
                    <label class="block text-sm font-medium mb-2">Filtrar por Partida</label>
                    <input type="text" name="match" value="<?= htmlspecialchars($matchFilter) ?>"
                           placeholder="ID da partida..."
                           class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Tipo de Evento</label>
                    <select name="event_type" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500">
                        <option value="">Todos os tipos</option>
                        <?php foreach ($eventTypes as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>" <?= $eventTypeFilter === $type ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end gap-2">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Filtrar
                    </button>
                    <a href="logs.php" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-times mr-2"></i>Limpar
                    </a>
                </div>
            </form>
        </div>

        <!-- Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <?php
            $statsStmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_events,
                    COUNT(DISTINCT match_id) as unique_matches,
                    COUNT(CASE WHEN timestamp > NOW() - INTERVAL 1 HOUR THEN 1 END) as events_last_hour,
                    COUNT(CASE WHEN DATE(timestamp) = CURDATE() THEN 1 END) as events_today
                FROM match_events
            ");
            $statsStmt->execute();
            $stats = $statsStmt->fetch();
            ?>
            
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <div class="text-3xl font-bold text-blue-400"><?= number_format($stats['total_events']) ?></div>
                <div class="text-gray-400">Total de Eventos</div>
            </div>
            
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <div class="text-3xl font-bold text-green-400"><?= number_format($stats['unique_matches']) ?></div>
                <div class="text-gray-400">Partidas com Logs</div>
            </div>
            
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <div class="text-3xl font-bold text-orange-400"><?= number_format($stats['events_last_hour']) ?></div>
                <div class="text-gray-400">Última Hora</div>
            </div>
            
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <div class="text-3xl font-bold text-purple-400"><?= number_format($stats['events_today']) ?></div>
                <div class="text-gray-400">Hoje</div>
            </div>
        </div>

        <!-- Lista de Eventos -->
        <div class="bg-gray-800 rounded-lg overflow-hidden">
            <?php if (count($events) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Timestamp
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Partida
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Tipo de Evento
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-300 uppercase tracking-wider">
                                Dados
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php foreach ($events as $event): ?>
                        <tr class="hover:bg-gray-700" x-data="{ expanded: false }">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm">
                                    <?= date('d/m/Y', strtotime($event['timestamp'])) ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?= date('H:i:s', strtotime($event['timestamp'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-mono text-orange-400">
                                    <?= htmlspecialchars($event['match_id']) ?>
                                </div>
                                <?php if ($event['team1_name'] && $event['team2_name']): ?>
                                <div class="text-xs text-gray-400">
                                    <?= htmlspecialchars($event['team1_name']) ?> vs <?= htmlspecialchars($event['team2_name']) ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($event['match_status']): ?>
                                <span class="px-2 py-1 text-xs bg-<?= getStatusColor($event['match_status']) ?>-600 rounded">
                                    <?= htmlspecialchars($event['match_status']) ?>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs bg-blue-600 rounded font-mono">
                                    <?= htmlspecialchars($event['event_type']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php 
                                $eventData = json_decode($event['event_data'], true);
                                if ($eventData && !empty($eventData)):
                                ?>
                                <div class="flex items-center space-x-2">
                                    <div class="text-sm text-gray-300 truncate max-w-xs" x-show="!expanded">
                                        <?= htmlspecialchars(json_encode($eventData)) ?>
                                    </div>
                                    <button @click="expanded = !expanded" 
                                            class="text-blue-400 hover:text-blue-300 text-xs">
                                        <i class="fas fa-eye" x-show="!expanded"></i>
                                        <i class="fas fa-eye-slash" x-show="expanded"></i>
                                    </button>
                                </div>
                                <div x-show="expanded" x-cloak class="mt-2">
                                    <pre class="text-xs bg-black p-3 rounded text-green-400 whitespace-pre-wrap overflow-x-auto"><?= htmlspecialchars(json_encode($eventData, JSON_PRETTY_PRINT)) ?></pre>
                                </div>
                                <?php else: ?>
                                <span class="text-gray-500 text-sm">Sem dados</span>
                                <?php endif; ?>
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
                    Mostrando <?= count($events) ?> de <?= number_format($totalEvents) ?> eventos
                </div>
                
                <div class="flex space-x-1">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&match=<?= urlencode($matchFilter) ?>&event_type=<?= urlencode($eventTypeFilter) ?>" 
                       class="px-3 py-1 bg-gray-600 hover:bg-gray-500 rounded text-sm">
                        Anterior
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?>&match=<?= urlencode($matchFilter) ?>&event_type=<?= urlencode($eventTypeFilter) ?>" 
                       class="px-3 py-1 <?= $i === $page ? 'bg-orange-600' : 'bg-gray-600 hover:bg-gray-500' ?> rounded text-sm">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&match=<?= urlencode($matchFilter) ?>&event_type=<?= urlencode($eventTypeFilter) ?>" 
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
                <h3 class="text-lg font-medium text-gray-400 mb-2">Nenhum evento encontrado</h3>
                <p class="text-gray-500">
                    <?php if (!empty($matchFilter) || !empty($eventTypeFilter)): ?>
                        Tente ajustar os filtros ou verificar se há partidas ativas
                    <?php else: ?>
                        Aguarde eventos das partidas ou verifique a configuração do webhook
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Informações sobre Webhook -->
        <div class="mt-8 p-4 bg-blue-900 border border-blue-700 rounded-lg">
            <h3 class="font-bold text-blue-400 mb-2">
                <i class="fas fa-info-circle mr-2"></i>Configuração do Webhook
            </h3>
            <div class="text-sm text-blue-100 space-y-2">
                <p>Para receber eventos automaticamente, configure o MatchZy com:</p>
                <code class="block bg-blue-800 p-2 rounded text-xs">
                    matchzy_remote_log_url "<?= $_SERVER['HTTP_HOST'] ?>/webhook.php"
                </code>
                <p class="text-xs text-blue-200">
                    Os eventos incluem: início/fim de partida, rounds, pausas, kills, e muito mais.
                </p>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</body>
</html>
