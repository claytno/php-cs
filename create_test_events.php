<?php
/**
 * Script para simular eventos de uma partida em andamento
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

try {
    // Obter a última partida criada
    $stmt = $pdo->prepare("SELECT match_id FROM matches ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    $matchId = $stmt->fetchColumn();
    
    if (!$matchId) {
        echo "Nenhuma partida encontrada. Execute create_test_match.php primeiro.\n";
        exit;
    }
    
    echo "Simulando eventos para partida: {$matchId}\n\n";
    
    // Evento 1: Partida carregada
    logMatchEvent($matchId, 'match_loaded', [
        'server' => '127.0.0.1:27015',
        'config_loaded' => true
    ]);
    echo "✓ Evento: match_loaded\n";
    
    // Evento 2: Série iniciada
    logMatchEvent($matchId, 'series_start', [
        'team1' => 'Team Alpha',
        'team2' => 'Team Beta',
        'maps' => ['de_dust2', 'de_mirage', 'de_inferno']
    ]);
    updateMatchStatus($matchId, 'active');
    echo "✓ Evento: series_start (partida ativa)\n";
    
    // Evento 3: Mapa iniciado
    logMatchEvent($matchId, 'map_start', [
        'map' => 'de_dust2',
        'map_number' => 1
    ]);
    updateMatchCurrentMap($matchId, 'de_dust2');
    echo "✓ Evento: map_start (de_dust2)\n";
    
    // Evento 4: Round iniciado
    logMatchEvent($matchId, 'round_start', [
        'round' => 1,
        'ct_team' => 'Team Alpha',
        't_team' => 'Team Beta'
    ]);
    echo "✓ Evento: round_start (Round 1)\n";
    
    // Evento 5: Kill
    logMatchEvent($matchId, 'player_death', [
        'attacker' => 'Player1',
        'attacker_team' => 'Team Alpha',
        'victim' => 'Player6',
        'victim_team' => 'Team Beta',
        'weapon' => 'ak47',
        'headshot' => true,
        'round' => 1
    ]);
    echo "✓ Evento: player_death (Player1 matou Player6)\n";
    
    // Evento 6: Fim do round
    logMatchEvent($matchId, 'round_end', [
        'round' => 1,
        'winner' => 'Team Alpha',
        'win_reason' => 'elimination',
        'score' => [
            'team1' => 1,
            'team2' => 0
        ]
    ]);
    echo "✓ Evento: round_end (Team Alpha venceu)\n";
    
    // Evento 7: Partida pausada
    sleep(1);
    logMatchEvent($matchId, 'match_paused', [
        'paused_by' => 'admin',
        'reason' => 'technical_issue'
    ]);
    updateMatchStatus($matchId, 'paused');
    echo "✓ Evento: match_paused\n";
    
    // Evento 8: Partida despausada
    sleep(2);
    logMatchEvent($matchId, 'match_unpaused', [
        'unpaused_by' => 'admin'
    ]);
    updateMatchStatus($matchId, 'active');
    echo "✓ Evento: match_unpaused\n";
    
    echo "\n=== EVENTOS DE TESTE CRIADOS COM SUCESSO! ===\n";
    echo "Agora você pode:\n";
    echo "1. Ver os logs em: http://localhost:8000/logs.php\n";
    echo "2. Controlar a partida em: http://localhost:8000/match_control.php?id=1\n";
    echo "3. Ver detalhes em: http://localhost:8000/matches.php\n";
    
} catch (Exception $e) {
    echo "❌ Erro ao criar eventos de teste: " . $e->getMessage() . "\n";
}
?>
