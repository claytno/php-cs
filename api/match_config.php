<?php
/**
 * API para gerar configuração JSON da partida para MatchZy
 * URL: /api/match_config.php?id=MATCH_ID
 */

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/functions.php';

// Função para responder JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Verificar se o ID da partida foi fornecido
$matchId = $_GET['id'] ?? '';

if (empty($matchId)) {
    jsonResponse(['error' => 'Match ID não fornecido'], 400);
}

try {
    // Buscar partida no banco
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE match_id = ?");
    $stmt->execute([$matchId]);
    $match = $stmt->fetch();
    
    if (!$match) {
        jsonResponse(['error' => 'Partida não encontrada'], 404);
    }
    
    // Decodificar dados JSON
    $team1Players = json_decode($match['team1_players'], true) ?: [];
    $team2Players = json_decode($match['team2_players'], true) ?: [];
    $maps = json_decode($match['maps'], true) ?: [];
    $config = json_decode($match['config'], true) ?: [];
    
    // Gerar configuração MatchZy
    $matchConfig = [
        "matchid" => $match['match_id'],
        "num_maps" => count($maps),
        "players_per_team" => 5,
        "min_players_to_ready" => $config['min_players_to_ready'] ?? 2,
        "skip_veto" => !($config['veto_enabled'] ?? false),
        "veto_first" => $config['veto_first'] ?? "team1",
        "side_type" => "standard",
        "maplist" => $maps,
        "team1" => [
            "name" => $match['team1_name'],
            "tag" => substr($match['team1_name'], 0, 5),
            "flag" => "BR",
            "logo" => "",
            "players" => formatPlayersForMatchZy($team1Players)
        ],
        "team2" => [
            "name" => $match['team2_name'],
            "tag" => substr($match['team2_name'], 0, 5),
            "flag" => "BR",
            "logo" => "",
            "players" => formatPlayersForMatchZy($team2Players)
        ],
        "cvars" => [
            "mp_teamname_1" => $match['team1_name'],
            "mp_teamname_2" => $match['team2_name'],
            "mp_maxrounds" => (int)$match['max_rounds'],
            "mp_overtime_enable" => $config['overtime_enabled'] ?? true ? 1 : 0,
            "mp_overtime_maxrounds" => 6,
            "mp_overtime_startmoney" => 10000,
            "mp_halftime" => 1,
            "mp_match_can_clinch" => 1,
            "mp_match_end_changelevel" => 0,
            "mp_endmatch_votenextmap" => 0,
            "cash_team_bonus_shorthanded" => 0,
            "mp_autokick" => 0,
            "mp_tkpunish" => 0,
            "sv_allow_wait_command" => 0,
            "sv_cheats" => 0,
            "sv_lan" => 0,
            "sv_pausable" => 1,
            "mp_pause_match" => 0
        ]
    ];
    
    // Se knife round estiver habilitado
    if ($config['knife_round'] ?? true) {
        $matchConfig["cvars"]["mp_do_warmup_period"] = 1;
        $matchConfig["cvars"]["mp_warmuptime"] = 1;
    }
    
    // Registrar que a configuração foi solicitada
    logMatchEvent($match['match_id'], 'config_requested', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    // Atualizar status da partida para loading se ainda estiver created
    if ($match['status'] === 'created') {
        updateMatchStatus($match['match_id'], 'loading');
    }
    
    jsonResponse($matchConfig);
    
} catch (Exception $e) {
    error_log('Erro na API match_config: ' . $e->getMessage());
    jsonResponse(['error' => 'Erro interno do servidor'], 500);
}

/**
 * Formatar jogadores para o formato MatchZy
 */
function formatPlayersForMatchZy($players) {
    $formatted = [];
    
    foreach ($players as $player) {
        $formatted[$player['steamid']] = $player['name'] ?? '';
    }
    
    return $formatted;
}
?>
