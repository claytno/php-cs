<?php
/**
 * API para gerar configuração JSON da partida para MatchZy
 * URL: /api/match_config.php?id=MATCH_ID
 */

// Garantir que sempre retorne JSON, mesmo em caso de erro fatal
ob_start();

// Headers para JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Função para responder JSON
function jsonResponse($data, $status = 200) {
    // Limpar qualquer output anterior
    ob_clean();
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Tratamento de erros fatais
function fatalErrorHandler() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        ob_clean();
        http_response_code(500);
        echo json_encode([
            'error' => 'Erro fatal no servidor',
            'message' => $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ], JSON_PRETTY_PRINT);
    }
}
register_shutdown_function('fatalErrorHandler');

try {
    require_once '../config/database.php';
    require_once '../includes/functions.php';
} catch (Exception $e) {
    jsonResponse([
        'error' => 'Erro ao carregar dependências',
        'message' => $e->getMessage()
    ], 500);
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
    
    // Gerar configuração MatchZy no formato correto
    $matchConfig = [
        "matchid" => $match['match_id'],
        "team1" => [
            "name" => $match['team1_name'],
            "players" => formatPlayersForMatchZy($team1Players)
        ],
        "team2" => [
            "name" => $match['team2_name'],
            "players" => formatPlayersForMatchZy($team2Players)
        ],
        "num_maps" => count($maps),
        "maplist" => $maps
    ];
    
    // Campos opcionais
    if (!empty($config)) {
        if (isset($config['players_per_team'])) {
            $matchConfig["players_per_team"] = (int)$config['players_per_team'];
        }
        
        if (isset($config['veto_enabled']) && !$config['veto_enabled']) {
            $matchConfig["skip_veto"] = true;
        }
        
        if (isset($config['veto_first'])) {
            $matchConfig["veto_first"] = $config['veto_first'];
        }
        
        if (isset($config['clinch_series'])) {
            $matchConfig["clinch_series"] = (bool)$config['clinch_series'];
        }
    }
    
    // CVars personalizadas
    $cvars = [
        "hostname" => "MatchZy: " . $match['team1_name'] . " vs " . $match['team2_name'],
        "mp_teamname_1" => $match['team1_name'],
        "mp_teamname_2" => $match['team2_name']
    ];
    
    if (isset($match['max_rounds']) && $match['max_rounds'] > 0) {
        $cvars["mp_maxrounds"] = (int)$match['max_rounds'];
    }
    
    if (isset($config['overtime_enabled'])) {
        $cvars["mp_overtime_enable"] = $config['overtime_enabled'] ? 1 : 0;
    }
    
    $matchConfig["cvars"] = $cvars;
    
    // Registrar que a configuração foi solicitada
    logApiAccess($match['match_id'], true);
    logMatchEvent($match['match_id'], 'config_requested', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'teams' => $match['team1_name'] . ' vs ' . $match['team2_name'],
        'maps_count' => count($maps)
    ]);
    
    // Atualizar status da partida para loading se ainda estiver created
    if ($match['status'] === 'created') {
        updateMatchStatus($match['match_id'], 'loading');
    }
    
    // Validar configuração antes de enviar
    if (empty($matchConfig['maplist'])) {
        jsonResponse([
            'error' => 'Configuração inválida: lista de mapas vazia',
            'match_id' => $match['match_id']
        ], 400);
    }
    
    if (empty($matchConfig['team1']['players']) && empty($matchConfig['team2']['players'])) {
        jsonResponse([
            'error' => 'Configuração inválida: nenhum jogador encontrado',
            'match_id' => $match['match_id']
        ], 400);
    }
    
    jsonResponse($matchConfig);
    
} catch (Exception $e) {
    $errorMsg = 'Erro na API match_config: ' . $e->getMessage();
    error_log($errorMsg);
    logApiAccess($matchId ?? 'unknown', false, $e->getMessage());
    
    jsonResponse([
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}

/**
 * Formatar jogadores para o formato MatchZy
 */
function formatPlayersForMatchZy($players) {
    $formatted = [];
    
    if (empty($players) || !is_array($players)) {
        return $formatted;
    }
    
    foreach ($players as $player) {
        // Suportar diferentes formatos de entrada
        if (is_array($player)) {
            $steamId = $player['steamid'] ?? $player['steam_id'] ?? '';
            $name = $player['name'] ?? $player['nickname'] ?? 'Player';
        } else if (is_string($player)) {
            // Se for só uma string, assumir que é SteamID
            $steamId = $player;
            $name = 'Player';
        } else {
            continue; // Pular entradas inválidas
        }
        
        // Validar SteamID (deve ser numérico e ter pelo menos 10 dígitos)
        if (!empty($steamId) && is_numeric($steamId) && strlen($steamId) >= 10) {
            $formatted[$steamId] = $name;
        }
    }
    
    return $formatted;
}

// Log adicional para debug
function logApiAccess($matchId, $success = true, $error = null) {
    try {
        $logData = [
            'match_id' => $matchId,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'success' => $success
        ];
        
        if ($error) {
            $logData['error'] = $error;
        }
        
        error_log('MatchZy Config API: ' . json_encode($logData));
    } catch (Exception $e) {
        // Ignorar erros de log para não afetar a API
    }
}
?>
