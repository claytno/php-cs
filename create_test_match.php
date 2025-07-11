<?php
/**
 * Script para criar uma partida de teste
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

try {
    echo "Criando partida de teste...\n";
    
    // Dados da partida de teste
    $matchId = generateMatchId();
    $team1Name = 'Team Alpha';
    $team2Name = 'Team Beta';
    $maps = ['de_dust2', 'de_mirage', 'de_inferno'];
    $maxRounds = 30;
    
    // Jogadores do Time 1
    $team1Players = [
        ['steamid' => '76561198123456789', 'name' => 'Player1'],
        ['steamid' => '76561198123456790', 'name' => 'Player2'],
        ['steamid' => '76561198123456791', 'name' => 'Player3'],
        ['steamid' => '76561198123456792', 'name' => 'Player4'],
        ['steamid' => '76561198123456793', 'name' => 'Player5']
    ];
    
    // Jogadores do Time 2
    $team2Players = [
        ['steamid' => '76561198123456794', 'name' => 'Player6'],
        ['steamid' => '76561198123456795', 'name' => 'Player7'],
        ['steamid' => '76561198123456796', 'name' => 'Player8'],
        ['steamid' => '76561198123456797', 'name' => 'Player9'],
        ['steamid' => '76561198123456798', 'name' => 'Player10']
    ];
    
    $config = json_encode([
        'knife_round' => true,
        'overtime_enabled' => true,
        'veto_enabled' => false,
        'veto_first' => 'team1'
    ]);
    
    // Inserir partida
    $stmt = $pdo->prepare("
        INSERT INTO matches (
            match_id, team1_name, team2_name, team1_players, team2_players, 
            maps, current_map, max_rounds, config, server_ip, server_port
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '127.0.0.1', 27015)
    ");
    
    $stmt->execute([
        $matchId,
        $team1Name,
        $team2Name,
        json_encode($team1Players),
        json_encode($team2Players),
        json_encode($maps),
        $maps[0],
        $maxRounds,
        $config
    ]);
    
    echo "✓ Partida criada: {$matchId}\n";
    
    // Inserir jogadores na tabela match_players
    foreach ($team1Players as $player) {
        $stmt = $pdo->prepare("INSERT INTO match_players (match_id, steam_id, team) VALUES (?, ?, 'team1')");
        $stmt->execute([$matchId, $player['steamid']]);
    }
    
    foreach ($team2Players as $player) {
        $stmt = $pdo->prepare("INSERT INTO match_players (match_id, steam_id, team) VALUES (?, ?, 'team2')");
        $stmt->execute([$matchId, $player['steamid']]);
    }
    
    echo "✓ Jogadores adicionados à partida\n";
    
    // Registrar evento
    logMatchEvent($matchId, 'match_created', [
        'team1' => $team1Name,
        'team2' => $team2Name,
        'maps' => $maps,
        'created_by' => 'test_script'
    ]);
    
    echo "✓ Evento de criação registrado\n";
    
    echo "\n=== PARTIDA DE TESTE CRIADA COM SUCESSO! ===\n";
    echo "ID da Partida: {$matchId}\n";
    echo "Time 1: {$team1Name} ({$team1Players[0]['name']}, {$team1Players[1]['name']}, ...)\n";
    echo "Time 2: {$team2Name} ({$team2Players[0]['name']}, {$team2Players[1]['name']}, ...)\n";
    echo "Mapas: " . implode(', ', $maps) . "\n";
    echo "Servidor: 127.0.0.1:27015\n";
    echo "\nAcesse http://localhost:8000 para ver a partida no sistema!\n";
    
} catch (Exception $e) {
    echo "❌ Erro ao criar partida de teste: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
