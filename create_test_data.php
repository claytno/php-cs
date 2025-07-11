<?php
/**
 * Script para inserir dados de teste no MatchZy Manager
 * Execute este script uma vez para criar dados de exemplo
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "=== Inserindo Dados de Teste ===\n\n";

try {
    // 1. Inserir servidor de teste
    echo "1. Inserindo servidor de teste...\n";
    $stmt = $pdo->prepare("INSERT IGNORE INTO servers (name, ip, port, rcon_password, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Servidor de Teste', '127.0.0.1', 27015, 'rcon_password_123', 'online']);
    $serverId = $pdo->lastInsertId() ?: 1;
    echo "   ✓ Servidor inserido (ID: $serverId)\n";

    // 2. Inserir jogadores de teste
    echo "\n2. Inserindo jogadores de teste...\n";
    $testPlayers = [
        // Time 1
        ['76561198123456789', 'Player1_Alpha'],
        ['76561198123456790', 'Player2_Alpha'],
        ['76561198123456791', 'Player3_Alpha'],
        ['76561198123456792', 'Player4_Alpha'],
        ['76561198123456793', 'Player5_Alpha'],
        // Time 2
        ['76561198123456794', 'Player1_Beta'],
        ['76561198123456795', 'Player2_Beta'],
        ['76561198123456796', 'Player3_Beta'],
        ['76561198123456797', 'Player4_Beta'],
        ['76561198123456798', 'Player5_Beta'],
    ];

    foreach ($testPlayers as $player) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO players (steam_id, name) VALUES (?, ?)");
        $stmt->execute([$player[0], $player[1]]);
    }
    echo "   ✓ " . count($testPlayers) . " jogadores inseridos\n";

    // 3. Criar partida de teste
    echo "\n3. Criando partida de teste...\n";
    $matchId = generateMatchId();
    
    $team1Players = [
        ['steamid' => '76561198123456789', 'name' => 'Player1_Alpha'],
        ['steamid' => '76561198123456790', 'name' => 'Player2_Alpha'],
        ['steamid' => '76561198123456791', 'name' => 'Player3_Alpha'],
        ['steamid' => '76561198123456792', 'name' => 'Player4_Alpha'],
        ['steamid' => '76561198123456793', 'name' => 'Player5_Alpha']
    ];
    
    $team2Players = [
        ['steamid' => '76561198123456794', 'name' => 'Player1_Beta'],
        ['steamid' => '76561198123456795', 'name' => 'Player2_Beta'],
        ['steamid' => '76561198123456796', 'name' => 'Player3_Beta'],
        ['steamid' => '76561198123456797', 'name' => 'Player4_Beta'],
        ['steamid' => '76561198123456798', 'name' => 'Player5_Beta']
    ];
    
    $maps = ['de_dust2', 'de_mirage', 'de_inferno'];
    
    $config = [
        'knife_round' => true,
        'overtime_enabled' => true,
        'veto_enabled' => false,
        'veto_first' => 'team1'
    ];

    // Inserir partida
    $stmt = $pdo->prepare("
        INSERT INTO matches (
            match_id, team1_name, team2_name, team1_players, team2_players, 
            maps, current_map, max_rounds, config, server_ip, server_port, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $matchId,
        'Team Alpha',
        'Team Beta',
        json_encode($team1Players),
        json_encode($team2Players),
        json_encode($maps),
        $maps[0],
        30,
        json_encode($config),
        '127.0.0.1',
        27015,
        'created'
    ]);
    
    echo "   ✓ Partida criada (ID: $matchId)\n";

    // 4. Inserir match_players
    echo "\n4. Inserindo relações match_players...\n";
    foreach ($team1Players as $player) {
        $stmt = $pdo->prepare("INSERT INTO match_players (match_id, steam_id, team) VALUES (?, ?, 'team1')");
        $stmt->execute([$matchId, $player['steamid']]);
    }
    
    foreach ($team2Players as $player) {
        $stmt = $pdo->prepare("INSERT INTO match_players (match_id, steam_id, team) VALUES (?, ?, 'team2')");
        $stmt->execute([$matchId, $player['steamid']]);
    }
    echo "   ✓ " . (count($team1Players) + count($team2Players)) . " relações inseridas\n";

    // 5. Inserir alguns eventos de teste
    echo "\n5. Inserindo eventos de teste...\n";
    $testEvents = [
        ['match_created', ['message' => 'Partida criada via script de teste']],
        ['config_requested', ['ip' => '127.0.0.1', 'user_agent' => 'test-script']],
        ['series_start', ['map' => 'de_dust2', 'team1' => 'Team Alpha', 'team2' => 'Team Beta']],
    ];

    foreach ($testEvents as $event) {
        logMatchEvent($matchId, $event[0], $event[1]);
    }
    echo "   ✓ " . count($testEvents) . " eventos inseridos\n";

    // 6. Inserir configurações do sistema
    echo "\n6. Inserindo configurações do sistema...\n";
    $settings = [
        ['webhook_secret', 'test_secret_token_123', 'Token secreto para webhooks de teste'],
        ['steam_api_key', '', 'Chave da API do Steam (configure se necessário)'],
        ['site_url', 'http://localhost:8000', 'URL base do site'],
    ];

    foreach ($settings as $setting) {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute($setting);
    }
    echo "   ✓ " . count($settings) . " configurações inseridas\n";

    echo "\n=== ✅ DADOS DE TESTE INSERIDOS COM SUCESSO! ===\n\n";
    echo "🎮 Partida de Teste Criada:\n";
    echo "   • ID: $matchId\n";
    echo "   • Teams: Team Alpha vs Team Beta\n";
    echo "   • Mapas: " . implode(', ', $maps) . "\n";
    echo "   • Status: Criada (pronta para iniciar)\n";
    echo "   • Servidor: 127.0.0.1:27015\n\n";
    
    echo "🌐 Acesse o sistema em: http://localhost:8000\n";
    echo "📊 Você pode agora:\n";
    echo "   • Ver a partida no dashboard\n";
    echo "   • Controlar a partida em tempo real\n";
    echo "   • Testar o servidor configurado\n";
    echo "   • Visualizar logs de eventos\n\n";

} catch (Exception $e) {
    echo "❌ Erro ao inserir dados de teste: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
