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
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $configUrl = $protocol . '://' . $host . '/api/match_config.php?id=' . $match['match_id'];
            
            // Comando MatchZy - diferentes variações para compatibilidade
            $commands = [
                'matchzy_loadmatch_url ' . $configUrl,                    // Sem aspas
                'matchzy_loadmatch_url "' . $configUrl . '"',             // Com aspas duplas
                "matchzy_loadmatch_url '" . $configUrl . "'",             // Com aspas simples
                'get5_loadmatch_url ' . $configUrl                        // Comando alternativo Get5
            ];
            
            $results = [];
            $success = false;
            
            // Tentar cada comando até um funcionar
            foreach ($commands as $index => $command) {
                $result = executeRconCommand($match['server_ip'], $command);
                $results[] = [
                    'command' => $command,
                    'result' => $result
                ];
                
                if ($result['success']) {
                    $success = true;
                    break;
                }
            }
            
            // Atualizar status se algum comando funcionou
            if ($success) {
                updateMatchStatus($match['match_id'], 'loading');
            }
            
            // Mensagem de resultado
            if ($success) {
                $message = 'Comando de iniciar partida enviado com sucesso!<br>';
                $message .= 'Comando usado: <code class="bg-gray-700 px-2 py-1 rounded text-sm">' . htmlspecialchars($results[count($results)-1]['command']) . '</code><br>';
                $message .= 'Config URL: <a href="' . $configUrl . '" target="_blank" class="text-blue-300 hover:text-blue-200">' . $configUrl . '</a>';
            } else {
                $message = 'Erro: Todos os comandos falharam!<br>';
                foreach ($results as $i => $res) {
                    $message .= 'Comando ' . ($i + 1) . ': <code class="bg-gray-700 px-1 py-0.5 rounded text-xs">' . htmlspecialchars($res['command']) . '</code> - ' . htmlspecialchars($res['result']['message']) . '<br>';
                }
                $message .= '<br>Verifique se o MatchZy está instalado no servidor e se a URL está acessível.';
            }
            break;
            
        case 'pause_match':
            $result = executeRconCommand($match['server_ip'], 'mp_pause_match');
            $message = $result['success'] ? 'Partida pausada' : 'Erro ao pausar partida: ' . $result['message'];
            break;
            
        case 'unpause_match':
            $result = executeRconCommand($match['server_ip'], 'mp_unpause_match');
            $message = $result['success'] ? 'Partida despausada' : 'Erro ao despausar partida: ' . $result['message'];
            break;
            
        case 'end_match':
            $result = executeRconCommand($match['server_ip'], 'matchzy_endmatch');
            if ($result['success']) {
                updateMatchStatus($match['match_id'], 'finished');
                $message = 'Partida finalizada';
            } else {
                $message = 'Erro ao finalizar partida: ' . $result['message'];
            }
            break;
            
        case 'restart_round':
            $result = executeRconCommand($match['server_ip'], 'mp_restartgame 1');
            $message = $result['success'] ? 'Round reiniciado' : 'Erro ao reiniciar round: ' . $result['message'];
            break;
            
        case 'change_map':
            $newMap = $_POST['map'] ?? '';
            if (empty($newMap)) {
                $message = 'Erro: Nenhum mapa selecionado';
            } else if ($newMap === $match['current_map']) {
                $message = 'Aviso: O mapa "' . $newMap . '" já é o mapa atual';
            } else {
                $result = executeRconCommand($match['server_ip'], 'changelevel ' . $newMap);
                if ($result['success']) {
                    updateMatchCurrentMap($match['match_id'], $newMap);
                    $message = 'Mapa alterado de "' . ($match['current_map'] ?: 'N/A') . '" para "' . $newMap . '"';
                } else {
                    $message = 'Erro ao trocar mapa: ' . $result['message'];
                }
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
        <?php 
        $isError = strpos($message, 'Erro:') === 0;
        $isWarning = strpos($message, 'Aviso:') === 0;
        $bgColor = $isError ? 'bg-red-800 border-red-600' : ($isWarning ? 'bg-yellow-800 border-yellow-600' : 'bg-green-800 border-green-600');
        $iconColor = $isError ? 'text-red-400' : ($isWarning ? 'text-yellow-400' : 'text-green-400');
        $textColor = $isError ? 'text-red-100' : ($isWarning ? 'text-yellow-100' : 'text-green-100');
        $icon = $isError ? 'fa-exclamation-circle' : ($isWarning ? 'fa-exclamation-triangle' : 'fa-check-circle');
        ?>
        <div class="<?= $bgColor ?> border rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <i class="fas <?= $icon ?> <?= $iconColor ?> mr-2 mt-0.5"></i>
                <div class="<?= $textColor ?>"><?= $message ?></div>
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

                <button @click="openMapChanger()" class="w-full bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition-colors">
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
                
                <button onclick="testConfigUrl()" class="w-full bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-link mb-1"></i><br>
                    <span class="text-xs">Testar URL</span>
                </button>
            </div>
        </div>

        <!-- Modal para resultado do teste de URL -->
        <div x-show="showUrlTest" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             style="display: none;"
             class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-gray-800 rounded-lg p-6 w-full max-w-2xl mx-4 max-h-96 overflow-auto"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
                <h3 class="text-lg font-bold mb-4 text-blue-400">
                    <i class="fas fa-link mr-2"></i>Teste da URL de Configuração
                </h3>
                
                <div id="urlTestResult" class="mb-4">
                    <div class="flex items-center justify-center py-4">
                        <i class="fas fa-spinner fa-spin text-blue-400 mr-2"></i>
                        <span class="text-gray-300">Testando URL...</span>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" 
                            @click="closeUrlTest()" 
                            class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500">
                        <i class="fas fa-times mr-2"></i>Fechar
                    </button>
                </div>
            </div>
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
        <div x-show="showMapChanger" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             style="display: none;"
             class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-gray-800 rounded-lg p-6 w-full max-w-md mx-4"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
                <h3 class="text-lg font-bold mb-4 text-purple-400">
                    <i class="fas fa-map mr-2"></i>Trocar Mapa
                </h3>
                
                <form method="POST" @submit="closeMapChanger()">
                    <input type="hidden" name="action" value="change_map">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-2 text-gray-300">Selecionar Mapa:</label>
                        <select name="map" required 
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="">-- Selecione um mapa --</option>
                            <?php foreach ($maps as $map): ?>
                            <option value="<?= htmlspecialchars($map) ?>" 
                                    <?= $map === $match['current_map'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($map) ?>
                                <?= $map === $match['current_map'] ? ' (Atual)' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="flex gap-4">
                        <button type="submit" 
                                class="flex-1 bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <i class="fas fa-exchange-alt mr-2"></i>Trocar Mapa
                        </button>
                        <button type="button" 
                                @click="closeMapChanger()" 
                                class="flex-1 bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-gray-500">
                            <i class="fas fa-times mr-2"></i>Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function matchControl() {
            return {
                showMapChanger: false,
                showUrlTest: false,
                
                // Método para abrir o modal
                openMapChanger() {
                    this.showMapChanger = true;
                },
                
                // Método para fechar o modal
                closeMapChanger() {
                    this.showMapChanger = false;
                },
                
                // Método para abrir teste de URL
                openUrlTest() {
                    this.showUrlTest = true;
                },
                
                // Método para fechar teste de URL
                closeUrlTest() {
                    this.showUrlTest = false;
                }
            }
        }
        
        // Função para testar URL de configuração
        async function testConfigUrl() {
            const alpineInstance = document.querySelector('[x-data]').__x.$data;
            alpineInstance.openUrlTest();
            
            const matchId = '<?= htmlspecialchars($match['match_id']) ?>';
            const protocol = window.location.protocol;
            const host = window.location.host;
            const configUrl = protocol + '//' + host + '/api/match_config.php?id=' + matchId;
            
            try {
                const response = await fetch(configUrl);
                const data = await response.json();
                
                let resultHtml = '';
                if (response.ok) {
                    resultHtml = `
                        <div class="bg-green-800 border border-green-600 rounded-lg p-4">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-check-circle text-green-400 mr-2"></i>
                                <span class="text-green-100 font-bold">URL funcionando corretamente!</span>
                            </div>
                            <div class="text-sm text-gray-300 mb-2">
                                <strong>URL:</strong> <a href="${configUrl}" target="_blank" class="text-blue-300 hover:text-blue-200">${configUrl}</a>
                            </div>
                            <div class="text-sm text-gray-300">
                                <strong>Resposta (primeiras linhas):</strong>
                                <pre class="bg-gray-700 p-2 rounded text-xs mt-2 overflow-auto max-h-32">${JSON.stringify(data, null, 2)}</pre>
                            </div>
                        </div>
                    `;
                } else {
                    resultHtml = `
                        <div class="bg-red-800 border border-red-600 rounded-lg p-4">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
                                <span class="text-red-100 font-bold">Erro na URL!</span>
                            </div>
                            <div class="text-sm text-gray-300 mb-2">
                                <strong>Status:</strong> ${response.status} ${response.statusText}
                            </div>
                            <div class="text-sm text-gray-300">
                                <strong>URL:</strong> <a href="${configUrl}" target="_blank" class="text-blue-300 hover:text-blue-200">${configUrl}</a>
                            </div>
                            <div class="text-sm text-gray-300">
                                <strong>Resposta:</strong>
                                <pre class="bg-gray-700 p-2 rounded text-xs mt-2">${JSON.stringify(data, null, 2)}</pre>
                            </div>
                        </div>
                    `;
                }
                
                document.getElementById('urlTestResult').innerHTML = resultHtml;
                
            } catch (error) {
                document.getElementById('urlTestResult').innerHTML = `
                    <div class="bg-red-800 border border-red-600 rounded-lg p-4">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                            <span class="text-red-100 font-bold">Erro de conexão!</span>
                        </div>
                        <div class="text-sm text-gray-300 mb-2">
                            <strong>Erro:</strong> ${error.message}
                        </div>
                        <div class="text-sm text-gray-300">
                            <strong>URL:</strong> <a href="${configUrl}" target="_blank" class="text-blue-300 hover:text-blue-200">${configUrl}</a>
                        </div>
                        <div class="text-xs text-gray-400 mt-2">
                            Verifique se o servidor web está rodando e se a URL está acessível.
                        </div>
                    </div>
                `;
            }
        }
        
        // Auto refresh da página a cada 30 segundos
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
