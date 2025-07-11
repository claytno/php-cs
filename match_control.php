<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$matchId = $_GET['match_id'] ?? '';

if (empty($matchId)) {
    header('Location: index.php');
    exit;
}

// Obter dados da partida
$stmt = $pdo->prepare("SELECT * FROM matches WHERE match_id = ?");
$stmt->execute([$matchId]);
$match = $stmt->fetch();

if (!$match) {
    header('Location: index.php');
    exit;
}

// Obter eventos recentes da partida
$events = getMatchEvents($match['match_id'], 20);

// Decodificar dados JSON
$team1Players = json_decode($match['team1_players'], true) ?: [];
$team2Players = json_decode($match['team2_players'], true) ?: [];
$maps = json_decode($match['maps'], true) ?: [];
$config = json_decode($match['config'], true) ?: [];

// Processar comandos RCON
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'start_match':
            $result = executeRconCommand($match['server_ip'], 'matchzy_loadmatch_url "' . $_SERVER['HTTP_HOST'] . '/api/match_config.php?id=' . $match['match_id'] . '"');
            updateMatchStatus($match['match_id'], 'loading');
            $message = 'Comando de iniciar partida enviado';
            break;
            
        case 'pause_match':
            executeRconCommand($match['server_ip'], 'mp_pause_match');
            $message = 'Partida pausada';
            break;
            
        case 'unpause_match':
            executeRconCommand($match['server_ip'], 'mp_unpause_match');
            $message = 'Partida despausada';
            break;
            
        case 'end_match':
            executeRconCommand($match['server_ip'], 'matchzy_endmatch');
            updateMatchStatus($match['match_id'], 'finished');
            $message = 'Partida finalizada';
            break;
            
        case 'restart_round':
            executeRconCommand($match['server_ip'], 'mp_restartgame 1');
            $message = 'Round reiniciado';
            break;
            
        case 'change_map':
            $newMap = $_POST['map'] ?? '';
            if (!empty($newMap)) {
                executeRconCommand($match['server_ip'], 'changelevel ' . $newMap);
                updateMatchCurrentMap($match['match_id'], $newMap);
                $message = 'Mapa alterado para ' . $newMap;
            }
            break;
    }
    
    // Registrar evento
    if (!empty($message)) {
        logMatchEvent($match['match_id'], 'admin_action', [
            'action' => $action,
            'message' => $message,
            'admin' => 'system'
        ]);
    }
}

// Recarregar dados após ação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE match_id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    $events = getMatchEvents($match['match_id'], 20);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle da Partida - <?= htmlspecialchars($match['match_id']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta http-equiv="refresh" content="30"> <!-- Auto refresh a cada 30 segundos -->
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <div class="container mx-auto px-4 py-8" x-data="matchControl()">
        <!-- Header -->
        <header class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-orange-500">
                        <i class="fas fa-gamepad mr-2"></i>Controle da Partida
                    </h1>
                    <p class="text-gray-400">ID: <?= htmlspecialchars($match['match_id']) ?></p>
                </div>
                <div class="flex gap-4">
                    <button onclick="location.reload()" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-sync mr-2"></i>Atualizar
                    </button>
                    <a href="matches.php" class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar
                    </a>
                </div>
            </div>
        </header>

        <?php if ($message): ?>
        <div class="bg-green-800 border border-green-600 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-400 mr-2"></i>
                <span class="text-green-100"><?= htmlspecialchars($message) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Status da Partida -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- Informações Gerais -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-bold mb-4 text-orange-400">
                    <i class="fas fa-info-circle mr-2"></i>Informações
                </h2>
                
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Status:</span>
                        <span class="px-2 py-1 bg-<?= getStatusColor($match['status']) ?>-600 rounded text-xs">
                            <?= ucfirst($match['status']) ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Servidor:</span>
                        <span><?= htmlspecialchars($match['server_ip'] . ':' . $match['server_port']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Mapa Atual:</span>
                        <span><?= htmlspecialchars($match['current_map'] ?: 'N/A') ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Criada:</span>
                        <span><?= date('d/m/Y H:i', strtotime($match['created_at'])) ?></span>
                    </div>
                </div>
            </div>

            <!-- Times -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-bold mb-4 text-blue-400">
                    <i class="fas fa-users mr-2"></i>Times
                </h2>
                
                <div class="space-y-4">
                    <div>
                        <h3 class="font-bold text-blue-300"><?= htmlspecialchars($match['team1_name']) ?></h3>
                        <div class="text-sm text-gray-400">
                            <?= count($team1Players) ?> jogadores
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-700 pt-4">
                        <h3 class="font-bold text-red-300"><?= htmlspecialchars($match['team2_name']) ?></h3>
                        <div class="text-sm text-gray-400">
                            <?= count($team2Players) ?> jogadores
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mapas -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-bold mb-4 text-purple-400">
                    <i class="fas fa-map mr-2"></i>Mapas
                </h2>
                
                <div class="space-y-2">
                    <?php foreach ($maps as $index => $map): ?>
                    <div class="flex items-center justify-between">
                        <span class="<?= $map === $match['current_map'] ? 'text-purple-300 font-bold' : 'text-gray-400' ?>">
                            <?= $index + 1 ?>. <?= htmlspecialchars($map) ?>
                        </span>
                        <?php if ($map === $match['current_map']): ?>
                        <i class="fas fa-play text-green-400"></i>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Controles -->
        <div class="bg-gray-800 rounded-lg p-6 mb-8">
            <h2 class="text-xl font-bold mb-6 text-orange-400">
                <i class="fas fa-gamepad mr-2"></i>Controles da Partida
            </h2>
            
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <?php if ($match['status'] === 'created'): ?>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="start_match">
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-play mb-1"></i><br>
                        <span class="text-xs">Iniciar</span>
                    </button>
                </form>
                <?php endif; ?>

                <?php if (in_array($match['status'], ['active', 'loading'])): ?>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="pause_match">
                    <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-pause mb-1"></i><br>
                        <span class="text-xs">Pausar</span>
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($match['status'] === 'paused'): ?>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="unpause_match">
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-play mb-1"></i><br>
                        <span class="text-xs">Retomar</span>
                    </button>
                </form>
                <?php endif; ?>

                <?php if (in_array($match['status'], ['active', 'paused', 'loading'])): ?>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="restart_round">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-redo mb-1"></i><br>
                        <span class="text-xs">Restart</span>
                    </button>
                </form>

                <button @click="showMapChanger = true" class="w-full bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-map mb-1"></i><br>
                    <span class="text-xs">Trocar Mapa</span>
                </button>

                <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja finalizar a partida?')">
                    <input type="hidden" name="action" value="end_match">
                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-stop mb-1"></i><br>
                        <span class="text-xs">Finalizar</span>
                    </button>
                </form>
                <?php endif; ?>

                <a href="index.php" class="w-full bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors text-center">
                    <i class="fas fa-home mb-1"></i><br>
                    <span class="text-xs">Início</span>
                </a>
            </div>
        </div>

        <!-- Eventos Recentes -->
        <div class="bg-gray-800 rounded-lg p-6">
            <h2 class="text-xl font-bold mb-6 text-yellow-400">
                <i class="fas fa-history mr-2"></i>Eventos Recentes
            </h2>
            
            <div class="space-y-3 max-h-96 overflow-y-auto">
                <?php if (count($events) > 0): ?>
                    <?php foreach ($events as $event): ?>
                    <div class="flex items-start space-x-3 p-3 bg-gray-700 rounded-lg">
                        <div class="flex-shrink-0">
                            <i class="fas fa-circle text-xs text-blue-400 mt-2"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <div class="text-sm font-medium">
                                    <?= htmlspecialchars($event['event_type']) ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    <?= date('H:i:s', strtotime($event['timestamp'])) ?>
                                </div>
                            </div>
                            <?php 
                            $eventData = json_decode($event['event_data'], true);
                            if ($eventData && !empty($eventData)):
                            ?>
                            <div class="text-xs text-gray-400 mt-1">
                                <?= htmlspecialchars(json_encode($eventData, JSON_PRETTY_PRINT)) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="text-center text-gray-400 py-8">
                    <i class="fas fa-inbox text-3xl mb-2"></i>
                    <p>Nenhum evento registrado ainda</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal para trocar mapa -->
        <div x-show="showMapChanger" x-cloak 
             class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-bold mb-4">Trocar Mapa</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="change_map">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2">Selecionar Mapa:</label>
                        <select name="map" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-purple-500">
                            <?php foreach ($maps as $map): ?>
                            <option value="<?= htmlspecialchars($map) ?>" <?= $map === $match['current_map'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($map) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex gap-4">
                        <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition-colors">
                            Trocar Mapa
                        </button>
                        <button type="button" @click="showMapChanger = false" 
                                class="flex-1 bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        [x-cloak] { display: none !important; }
    </style>

    <script>
        function matchControl() {
            return {
                showMapChanger: false
            }
        }
    </script>
</body>
</html>
