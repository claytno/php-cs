<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar dados básicos
        $team1Name = sanitizeInput($_POST['team1_name'] ?? '');
        $team2Name = sanitizeInput($_POST['team2_name'] ?? '');
        $selectedMaps = $_POST['maps'] ?? [];
        $maxRounds = (int)($_POST['max_rounds'] ?? 30);
        $knifeRound = isset($_POST['knife_round']);
        $overtimeEnabled = isset($_POST['overtime_enabled']);
        $vetoEnabled = isset($_POST['veto_enabled']);
        $serverId = (int)($_POST['server_id'] ?? 0);
        
        // Validações
        if (empty($team1Name) || empty($team2Name)) {
            throw new Exception('Nomes dos times são obrigatórios');
        }
        
        if (empty($selectedMaps)) {
            throw new Exception('Pelo menos um mapa deve ser selecionado');
        }
        
        if ($serverId <= 0) {
            throw new Exception('Servidor deve ser selecionado');
        }
        
        // Processar jogadores
        $team1Players = [];
        $team2Players = [];
        
        // Team 1 players
        for ($i = 1; $i <= 5; $i++) {
            $steamId = sanitizeInput($_POST["team1_player_{$i}_steam"] ?? '');
            $name = sanitizeInput($_POST["team1_player_{$i}_name"] ?? '');
            
            if (!empty($steamId)) {
                if (!validateSteamId($steamId)) {
                    throw new Exception("Steam ID inválido para jogador {$i} do Time 1");
                }
                
                if (isSteamIdInUse($steamId)) {
                    throw new Exception("Steam ID {$steamId} já está em uso em outra partida");
                }
                
                $team1Players[] = [
                    'steamid' => $steamId,
                    'name' => $name ?: "Player {$i}"
                ];
            }
        }
        
        // Team 2 players
        for ($i = 1; $i <= 5; $i++) {
            $steamId = sanitizeInput($_POST["team2_player_{$i}_steam"] ?? '');
            $name = sanitizeInput($_POST["team2_player_{$i}_name"] ?? '');
            
            if (!empty($steamId)) {
                if (!validateSteamId($steamId)) {
                    throw new Exception("Steam ID inválido para jogador {$i} do Time 2");
                }
                
                if (isSteamIdInUse($steamId)) {
                    throw new Exception("Steam ID {$steamId} já está em uso em outra partida");
                }
                
                $team2Players[] = [
                    'steamid' => $steamId,
                    'name' => $name ?: "Player {$i}"
                ];
            }
        }
        
        // Gerar ID da partida
        $matchId = generateMatchId();
        
        // Dados da partida
        $matchData = [
            'match_id' => $matchId,
            'team1_name' => $team1Name,
            'team2_name' => $team2Name,
            'team1_players' => $team1Players,
            'team2_players' => $team2Players,
            'maps' => $selectedMaps,
            'max_rounds' => $maxRounds,
            'knife_round' => $knifeRound,
            'overtime_enabled' => $overtimeEnabled,
            'veto_enabled' => $vetoEnabled,
            'veto_first' => $_POST['veto_first'] ?? 'team1'
        ];
        
        // Inserir partida no banco
        $stmt = $pdo->prepare("
            INSERT INTO matches (
                match_id, team1_name, team2_name, team1_players, team2_players, 
                maps, current_map, max_rounds, config, server_ip, server_port
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 
                (SELECT ip FROM servers WHERE id = ?),
                (SELECT port FROM servers WHERE id = ?)
            )
        ");
        
        $config = json_encode([
            'knife_round' => $knifeRound,
            'overtime_enabled' => $overtimeEnabled,
            'veto_enabled' => $vetoEnabled,
            'veto_first' => $matchData['veto_first']
        ]);
        
        // Inserir/atualizar jogadores na tabela players PRIMEIRO (antes da partida)
        foreach ($team1Players as $player) {
            $stmt = $pdo->prepare("INSERT INTO players (steam_id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = ?, updated_at = NOW()");
            $stmt->execute([$player['steamid'], $player['name'], $player['name']]);
        }
        
        foreach ($team2Players as $player) {
            $stmt = $pdo->prepare("INSERT INTO players (steam_id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = ?, updated_at = NOW()");
            $stmt->execute([$player['steamid'], $player['name'], $player['name']]);
        }
        
        // Agora inserir a partida
        $stmt->execute([
            $matchId,
            $team1Name,
            $team2Name,
            json_encode($team1Players),
            json_encode($team2Players),
            json_encode($selectedMaps),
            $selectedMaps[0] ?? null,
            $maxRounds,
            $config,
            $serverId,
            $serverId
        ]);
        
        // Por último, inserir jogadores na tabela match_players
        foreach ($team1Players as $player) {
            $stmt = $pdo->prepare("INSERT INTO match_players (match_id, steam_id, team) VALUES (?, ?, 'team1')");
            $stmt->execute([$matchId, $player['steamid']]);
        }
        
        foreach ($team2Players as $player) {
            $stmt = $pdo->prepare("INSERT INTO match_players (match_id, steam_id, team) VALUES (?, ?, 'team2')");
            $stmt->execute([$matchId, $player['steamid']]);
        }
        
        // Gerar configuração MatchZy
        $configJson = createMatchConfig($matchData);
        
        // Registrar evento
        logMatchEvent($matchId, 'match_created', $matchData);
        
        $success = "Partida criada com sucesso! ID: {$matchId}";
        
        // Opcional: Enviar configuração para servidor
        // $result = sendMatchConfigToServer($serverId, $configJson);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$availableMaps = getAvailableMaps();
$availableServers = getAvailableServers();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Partida - MatchZy Manager</title>
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
                        <i class="fas fa-plus-circle mr-2"></i>Criar Nova Partida
                    </h1>
                    <p class="text-gray-400">Configure uma nova partida CS2 com MatchZy</p>
                </div>
                <a href="index.php" class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </a>
            </div>
        </header>

        <?php if ($error): ?>
        <div class="bg-red-800 border border-red-600 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                <span class="text-red-100"><?= htmlspecialchars($error) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-800 border border-green-600 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-400 mr-2"></i>
                <span class="text-green-100"><?= htmlspecialchars($success) ?></span>
            </div>
            <div class="mt-4">
                <a href="matches.php" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded-lg transition-colors mr-3">
                    Ver Partidas
                </a>
                <a href="index.php" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
                    Início
                </a>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-8" x-data="matchForm()">
            <!-- Informações Básicas -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-bold mb-6 text-orange-400">
                    <i class="fas fa-info-circle mr-2"></i>Informações Básicas
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">Nome do Time 1</label>
                        <input type="text" name="team1_name" required
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500"
                               placeholder="Ex: Team Alpha">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Nome do Time 2</label>
                        <input type="text" name="team2_name" required
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500"
                               placeholder="Ex: Team Beta">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Servidor</label>
                        <select name="server_id" required
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500">
                            <option value="">Selecione um servidor</option>
                            <?php foreach ($availableServers as $server): ?>
                            <option value="<?= $server['id'] ?>">
                                <?= htmlspecialchars($server['name']) ?> (<?= $server['ip'] ?>:<?= $server['port'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Máximo de Rounds</label>
                        <select name="max_rounds"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500">
                            <option value="16">MR16 (16 rounds)</option>
                            <option value="30" selected>MR30 (30 rounds)</option>
                            <option value="24">MR24 (24 rounds)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Configurações da Partida -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-bold mb-6 text-orange-400">
                    <i class="fas fa-cogs mr-2"></i>Configurações
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="flex items-center">
                        <input type="checkbox" name="knife_round" id="knife_round" checked
                               class="bg-gray-700 border border-gray-600 rounded focus:outline-none focus:border-orange-500">
                        <label for="knife_round" class="ml-2">Round de Faca</label>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="overtime_enabled" id="overtime_enabled" checked
                               class="bg-gray-700 border border-gray-600 rounded focus:outline-none focus:border-orange-500">
                        <label for="overtime_enabled" class="ml-2">Prorrogação</label>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="veto_enabled" id="veto_enabled"
                               class="bg-gray-700 border border-gray-600 rounded focus:outline-none focus:border-orange-500">
                        <label for="veto_enabled" class="ml-2">Sistema de Veto</label>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Primeiro no Veto</label>
                        <select name="veto_first"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500">
                            <option value="team1">Time 1</option>
                            <option value="team2">Time 2</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Seleção de Mapas -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-bold mb-6 text-orange-400">
                    <i class="fas fa-map mr-2"></i>Mapas
                </h2>
                
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                    <?php foreach ($availableMaps as $mapCode => $mapName): ?>
                    <div class="relative">
                        <input type="checkbox" name="maps[]" value="<?= $mapCode ?>" id="map_<?= $mapCode ?>"
                               class="absolute opacity-0 peer">
                        <label for="map_<?= $mapCode ?>" 
                               class="block bg-gray-700 border-2 border-gray-600 rounded-lg p-4 cursor-pointer peer-checked:border-orange-500 peer-checked:bg-orange-900 hover:bg-gray-600 transition-colors">
                            <div class="text-center">
                                <i class="fas fa-map text-2xl mb-2"></i>
                                <div class="font-medium"><?= htmlspecialchars($mapName) ?></div>
                                <div class="text-xs text-gray-400"><?= $mapCode ?></div>
                            </div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Jogadores Time 1 -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-bold mb-6 text-blue-400">
                    <i class="fas fa-users mr-2"></i>Jogadores - Time 1
                </h2>
                
                <div class="space-y-4">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Steam ID do Jogador <?= $i ?></label>
                            <input type="text" name="team1_player_<?= $i ?>_steam"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500"
                                   placeholder="76561198XXXXXXXXX">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Nome do Jogador <?= $i ?></label>
                            <input type="text" name="team1_player_<?= $i ?>_name"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-blue-500"
                                   placeholder="Nome do jogador">
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Jogadores Time 2 -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-bold mb-6 text-red-400">
                    <i class="fas fa-users mr-2"></i>Jogadores - Time 2
                </h2>
                
                <div class="space-y-4">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">Steam ID do Jogador <?= $i ?></label>
                            <input type="text" name="team2_player_<?= $i ?>_steam"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-red-500"
                                   placeholder="76561198XXXXXXXXX">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">Nome do Jogador <?= $i ?></label>
                            <input type="text" name="team2_player_<?= $i ?>_name"
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-red-500"
                                   placeholder="Nome do jogador">
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Botões de Ação -->
            <div class="flex gap-4">
                <button type="submit" 
                        class="bg-orange-600 hover:bg-orange-700 text-white px-8 py-3 rounded-lg font-medium transition-colors">
                    <i class="fas fa-plus mr-2"></i>Criar Partida
                </button>
                
                <a href="index.php" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-8 py-3 rounded-lg font-medium transition-colors">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </a>
            </div>
        </form>
    </div>

    <script>
        function matchForm() {
            return {
                // Funções auxiliares do formulário podem ser adicionadas aqui
            }
        }
    </script>
</body>
</html>
