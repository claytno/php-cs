<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

// Times pré-definidos para teste
$predefinedTeams = [
    'team1' => [
        'name' => 'Team Legends',
        'players' => [
            ['name' => 'Player1', 'steam' => '76561198000000001'],
            ['name' => 'Player2', 'steam' => '76561198000000002'],
            ['name' => 'Player3', 'steam' => '76561198000000003'],
            ['name' => 'Player4', 'steam' => '76561198000000004'],
            ['name' => 'Player5', 'steam' => '76561198000000005']
        ]
    ],
    'team2' => [
        'name' => 'Team Champions',
        'players' => [
            ['name' => 'PlayerA', 'steam' => '76561198000000006'],
            ['name' => 'PlayerB', 'steam' => '76561198000000007'],
            ['name' => 'PlayerC', 'steam' => '76561198000000008'],
            ['name' => 'PlayerD', 'steam' => '76561198000000009'],
            ['name' => 'PlayerE', 'steam' => '76561198000000010']
        ]
    ]
];

// Mapas disponíveis
$availableMaps = [
    'de_mirage', 'de_inferno', 'de_dust2', 'de_overpass', 
    'de_nuke', 'de_vertigo', 'de_ancient'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Dados básicos
        $selectedMaps = $_POST['maps'] ?? [];
        $serverId = 1; // Server fixo para teste
        
        // Validações básicas
        if (empty($selectedMaps)) {
            throw new Exception('Pelo menos um mapa deve ser selecionado');
        }
        
        // Obter dados do servidor
        $stmt = $pdo->prepare("SELECT * FROM servers WHERE id = ?");
        $stmt->execute([$serverId]);
        $server = $stmt->fetch();
        
        if (!$server) {
            throw new Exception('Servidor não encontrado');
        }
        
        // Gerar ID único da partida
        $matchId = generateMatchId();
        
        // Preparar dados dos jogadores para inserção no banco
        $team1PlayersData = [];
        $team2PlayersData = [];
        
        foreach ($predefinedTeams['team1']['players'] as $player) {
            $team1PlayersData[] = [
                'name' => $player['name'],
                'steam_id' => $player['steam']
            ];
        }
        
        foreach ($predefinedTeams['team2']['players'] as $player) {
            $team2PlayersData[] = [
                'name' => $player['name'],
                'steam_id' => $player['steam']
            ];
        }
        
        // Configuração da partida (formato padrão CS2 - MR12)
        $matchConfig = [
            'match_id' => $matchId,
            'team1' => [
                'name' => $predefinedTeams['team1']['name'],
                'players' => $team1PlayersData
            ],
            'team2' => [
                'name' => $predefinedTeams['team2']['name'],
                'players' => $team2PlayersData
            ],
            'maps' => $selectedMaps,
            'max_rounds' => 24, // MR12 = máximo 24 rounds (12 para cada lado)
            'max_overtime_rounds' => 6, // MR3 overtime
            'knife_round' => true,
            'overtime_enabled' => true,
            'veto_enabled' => count($selectedMaps) > 1
        ];
        
        // Inserir jogadores primeiro
        foreach (array_merge($team1PlayersData, $team2PlayersData) as $playerData) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO players (name, steam_id) VALUES (?, ?)");
            $stmt->execute([$playerData['name'], $playerData['steam_id']]);
        }
        
        // Inserir partida
        $stmt = $pdo->prepare("
            INSERT INTO matches (
                match_id, team1_name, team2_name, team1_players, team2_players,
                maps, current_map, max_rounds, server_ip, server_port,
                config, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'created', NOW())
        ");
        
        $stmt->execute([
            $matchId,
            $predefinedTeams['team1']['name'],
            $predefinedTeams['team2']['name'],
            json_encode($team1PlayersData),
            json_encode($team2PlayersData),
            json_encode($selectedMaps),
            $selectedMaps[0],
            24,
            $server['ip'],
            $server['port'],
            json_encode($matchConfig)
        ]);
        
        // Obter ID da partida inserida
        $insertedMatchId = $pdo->lastInsertId();
        
        // Inserir relacionamentos match_players
        foreach ($team1PlayersData as $playerData) {
            $stmt = $pdo->prepare("
                INSERT INTO match_players (match_id, steam_id, team)
                VALUES (?, ?, 'team1')
            ");
            $stmt->execute([$matchId, $playerData['steam_id']]);
        }
        
        foreach ($team2PlayersData as $playerData) {
            $stmt = $pdo->prepare("
                INSERT INTO match_players (match_id, steam_id, team)
                VALUES (?, ?, 'team2')
            ");
            $stmt->execute([$matchId, $playerData['steam_id']]);
        }
        
        // Log do evento de criação
        logMatchEvent($matchId, 'match_created', [
            'team1' => $predefinedTeams['team1']['name'],
            'team2' => $predefinedTeams['team2']['name'],
            'maps' => $selectedMaps,
            'format' => 'MR12 (CS2 Padrão)'
        ]);
        
        $success = "Partida criada com sucesso! ID: {$matchId}";
        
        // Redirect para controle da partida
        header("Location: match_control.php?match_id={$matchId}");
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obter servidores disponíveis
$stmt = $pdo->prepare("SELECT * FROM servers WHERE status = 'online'");
$stmt->execute();
$servers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Partida Rápida - CS2 Match System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <header class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-orange-500">
                        <i class="fas fa-plus-circle mr-2"></i>Criar Partida Rápida
                    </h1>
                    <p class="text-gray-400">Times pré-definidos - Formato CS2 Padrão (MR12)</p>
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
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Times Pré-definidos -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-bold mb-4 text-blue-400">
                    <i class="fas fa-users mr-2"></i>Times de Teste
                </h2>
                
                <!-- Team 1 -->
                <div class="mb-6">
                    <h3 class="text-lg font-bold text-blue-300 mb-2">
                        <?= htmlspecialchars($predefinedTeams['team1']['name']) ?>
                    </h3>
                    <div class="grid grid-cols-1 gap-2">
                        <?php foreach ($predefinedTeams['team1']['players'] as $index => $player): ?>
                        <div class="bg-gray-700 p-2 rounded flex justify-between">
                            <span><?= htmlspecialchars($player['name']) ?></span>
                            <span class="text-xs text-gray-400"><?= htmlspecialchars($player['steam']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Team 2 -->
                <div>
                    <h3 class="text-lg font-bold text-red-300 mb-2">
                        <?= htmlspecialchars($predefinedTeams['team2']['name']) ?>
                    </h3>
                    <div class="grid grid-cols-1 gap-2">
                        <?php foreach ($predefinedTeams['team2']['players'] as $index => $player): ?>
                        <div class="bg-gray-700 p-2 rounded flex justify-between">
                            <span><?= htmlspecialchars($player['name']) ?></span>
                            <span class="text-xs text-gray-400"><?= htmlspecialchars($player['steam']) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Configuração da Partida -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-bold mb-4 text-orange-400">
                    <i class="fas fa-cog mr-2"></i>Configuração da Partida
                </h2>
                
                <form method="POST" class="space-y-6">
                    <!-- Seleção de Mapas -->
                    <div>
                        <label class="block text-sm font-medium mb-2">
                            <i class="fas fa-map mr-1"></i>Mapas da Partida:
                        </label>
                        <div class="grid grid-cols-1 gap-2">
                            <?php foreach ($availableMaps as $map): ?>
                            <label class="flex items-center p-2 bg-gray-700 rounded hover:bg-gray-600 transition-colors cursor-pointer">
                                <input type="checkbox" name="maps[]" value="<?= htmlspecialchars($map) ?>" 
                                       class="mr-3 text-orange-500 focus:ring-orange-500">
                                <span class="flex-1"><?= htmlspecialchars($map) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Selecione os mapas que farão parte da partida
                        </p>
                    </div>

                    <!-- Informações do Formato -->
                    <div class="bg-gray-700 rounded-lg p-4">
                        <h3 class="font-bold text-green-400 mb-2">
                            <i class="fas fa-trophy mr-2"></i>Formato da Partida
                        </h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Formato:</span>
                                <span class="text-green-300">MR12 (CS2 Padrão)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Rounds para vencer:</span>
                                <span class="text-green-300">13 rounds</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Overtime:</span>
                                <span class="text-green-300">MR3 (habilitado)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Knife Round:</span>
                                <span class="text-green-300">Habilitado</span>
                            </div>
                        </div>
                    </div>

                    <!-- Botão de Criar -->
                    <button type="submit" 
                            class="w-full bg-orange-600 hover:bg-orange-700 px-6 py-3 rounded-lg font-bold transition-colors">
                        <i class="fas fa-rocket mr-2"></i>Criar Partida Rápida
                    </button>
                </form>
            </div>
        </div>

        <!-- Instruções -->
        <div class="mt-8 bg-gray-800 rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4 text-yellow-400">
                <i class="fas fa-info-circle mr-2"></i>Como Funciona
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                <div class="text-center">
                    <i class="fas fa-users text-3xl text-blue-400 mb-2"></i>
                    <h3 class="font-bold mb-2">1. Times Pré-definidos</h3>
                    <p class="text-gray-400">Os times já estão configurados com jogadores de teste. Apenas selecione os mapas.</p>
                </div>
                
                <div class="text-center">
                    <i class="fas fa-map text-3xl text-purple-400 mb-2"></i>
                    <h3 class="font-bold mb-2">2. Escolha os Mapas</h3>
                    <p class="text-gray-400">Selecione um ou mais mapas da pool competitiva do CS2.</p>
                </div>
                
                <div class="text-center">
                    <i class="fas fa-play text-3xl text-green-400 mb-2"></i>
                    <h3 class="font-bold mb-2">3. Inicie a Partida</h3>
                    <p class="text-gray-400">A partida será criada no formato padrão do CS2 (MR12) e você será direcionado para o controle.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
