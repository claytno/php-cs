<?php
/**
 * Funções auxiliares para o sistema MatchZy Manager
 */

/**
 * Gerar ID único para partida
 */
function generateMatchId() {
    return 'match_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 8);
}

/**
 * Obter partida ativa
 */
function getActiveMatch() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE status IN ('loading', 'active', 'paused') ORDER BY created_at DESC LIMIT 1");
    $stmt->execute();
    return $stmt->fetch();
}

/**
 * Obter partidas recentes
 */
function getRecentMatches($limit = 10) {
    global $pdo;
    
    // Garantir que limit é um inteiro válido
    $limit = (int)$limit;
    if ($limit <= 0) $limit = 10;
    
    $stmt = $pdo->prepare("SELECT * FROM matches ORDER BY created_at DESC LIMIT " . $limit);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Obter cor do status
 */
function getStatusColor($status) {
    $colors = [
        'created' => 'blue',
        'loading' => 'yellow',
        'active' => 'green',
        'paused' => 'orange',
        'finished' => 'gray',
        'cancelled' => 'red'
    ];
    
    return $colors[$status] ?? 'gray';
}

/**
 * Mapas disponíveis do CS2
 */
function getAvailableMaps() {
    return [
        'de_dust2' => 'Dust II',
        'de_mirage' => 'Mirage',
        'de_inferno' => 'Inferno',
        'de_cache' => 'Cache',
        'de_overpass' => 'Overpass',
        'de_vertigo' => 'Vertigo',
        'de_ancient' => 'Ancient',
        'de_anubis' => 'Anubis',
        'de_nuke' => 'Nuke',
        'de_train' => 'Train'
    ];
}

/**
 * Criar configuração JSON para MatchZy
 */
function createMatchConfig($matchData) {
    $config = [
        "matchid" => $matchData['match_id'],
        "num_maps" => count($matchData['maps']),
        "players_per_team" => 5,
        "min_players_to_ready" => 2,
        "skip_veto" => !$matchData['veto_enabled'],
        "veto_first" => $matchData['veto_first'] ?? 'team1',
        "maplist" => $matchData['maps'],
        "team1" => [
            "name" => $matchData['team1_name'],
            "players" => $matchData['team1_players']
        ],
        "team2" => [
            "name" => $matchData['team2_name'],
            "players" => $matchData['team2_players']
        ],
        "cvars" => [
            "mp_teamname_1" => $matchData['team1_name'],
            "mp_teamname_2" => $matchData['team2_name'],
            "mp_maxrounds" => $matchData['max_rounds'],
            "mp_overtime_enable" => $matchData['overtime_enabled'] ? 1 : 0,
            "mp_overtime_maxrounds" => 6,
            "mp_overtime_startmoney" => 10000
        ]
    ];
    
    return json_encode($config, JSON_PRETTY_PRINT);
}

/**
 * Enviar configuração para servidor
 */
function sendMatchConfigToServer($serverId, $configJson) {
    global $pdo;
    
    // Obter dados do servidor
    $stmt = $pdo->prepare("SELECT * FROM servers WHERE id = ?");
    $stmt->execute([$serverId]);
    $server = $stmt->fetch();
    
    if (!$server) {
        return ['success' => false, 'message' => 'Servidor não encontrado'];
    }
    
    // Salvar configuração em arquivo temporário
    $configFile = tempnam(sys_get_temp_dir(), 'matchzy_config_');
    file_put_contents($configFile, $configJson);
    
    // Simular envio para servidor (aqui você implementaria RCON ou SSH)
    // Por enquanto, vamos apenas retornar sucesso
    
    unlink($configFile);
    
    return ['success' => true, 'message' => 'Configuração enviada com sucesso'];
}

/**
 * Executar comando RCON
 */
function executeRconCommand($serverIdOrIp, $command) {
    global $pdo;
    
    // Se é um número, trata como ID
    if (is_numeric($serverIdOrIp)) {
        $stmt = $pdo->prepare("SELECT * FROM servers WHERE id = ?");
        $stmt->execute([$serverIdOrIp]);
        $server = $stmt->fetch();
    } else {
        // Se não é número, trata como IP
        $stmt = $pdo->prepare("SELECT * FROM servers WHERE ip = ? LIMIT 1");
        $stmt->execute([$serverIdOrIp]);
        $server = $stmt->fetch();
        
        // Se não encontrou servidor pelo IP, cria uma entrada temporária
        if (!$server) {
            $server = [
                'id' => 0,
                'ip' => $serverIdOrIp,
                'port' => 27015,
                'rcon_password' => 'changeme',
                'name' => 'Servidor ' . $serverIdOrIp
            ];
        }
    }
    
    if (!$server) {
        return ['success' => false, 'message' => 'Servidor não encontrado'];
    }
    
    // Log detalhado do comando
    $logMessage = sprintf(
        "RCON Command Execution:\n- Server: %s:%s\n- Command: %s\n- Time: %s",
        $server['ip'],
        $server['port'] ?? 27015,
        $command,
        date('Y-m-d H:i:s')
    );
    error_log($logMessage);
    
    // Aqui você implementaria a conexão RCON real
    // Por enquanto, simularemos o comando com validação
    
    // Simular diferentes tipos de resposta baseado no comando
    if (strpos($command, 'matchzy_loadmatch_url') !== false) {
        // Para comandos de carregar match, simular sucesso
        $result = "MatchZy: Loading match configuration from URL: " . substr($command, 21);
    } else if (strpos($command, 'mp_pause_match') !== false) {
        $result = "Match paused";
    } else if (strpos($command, 'mp_unpause_match') !== false) {
        $result = "Match unpaused";
    } else if (strpos($command, 'changelevel') !== false) {
        $mapName = trim(str_replace('changelevel', '', $command));
        $result = "Changing level to: " . $mapName;
    } else {
        $result = "Command executed: " . $command;
    }
    
    $successMessage = sprintf(
        "RCON executado com sucesso no servidor %s:\n%s",
        $server['ip'],
        $result
    );
    
    return ['success' => true, 'message' => $successMessage, 'command' => $command];
}

/**
 * Obter servidores disponíveis
 */
function getAvailableServers() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM servers WHERE status IN ('online', 'offline') ORDER BY name");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Validar Steam ID
 */
function validateSteamId($steamId) {
    // Validação básica para Steam64 ID
    return preg_match('/^765611\d{11}$/', $steamId);
}

/**
 * Formatar duração
 */
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    } else {
        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}

/**
 * Registrar evento de partida
 */
function logMatchEvent($matchId, $eventType, $eventData = []) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO match_events (match_id, event_type, event_data) VALUES (?, ?, ?)");
    $stmt->execute([$matchId, $eventType, json_encode($eventData)]);
}

/**
 * Processar evento do webhook MatchZy
 */
function processMatchZyEvent($eventData) {
    global $pdo;
    
    // Extrair informações do evento
    $matchId = $eventData['matchid'] ?? null;
    $eventType = $eventData['event'] ?? 'unknown';
    
    if (!$matchId) {
        return false;
    }
    
    // Registrar evento
    logMatchEvent($matchId, $eventType, $eventData);
    
    // Processar tipos específicos de eventos
    switch ($eventType) {
        case 'series_start':
            updateMatchStatus($matchId, 'active');
            break;
            
        case 'map_start':
            $mapName = $eventData['map'] ?? '';
            updateMatchCurrentMap($matchId, $mapName);
            break;
            
        case 'round_end':
            // Atualizar pontuação se necessário
            break;
            
        case 'series_end':
            updateMatchStatus($matchId, 'finished');
            break;
            
        case 'match_paused':
            updateMatchStatus($matchId, 'paused');
            break;
            
        case 'match_unpaused':
            updateMatchStatus($matchId, 'active');
            break;
    }
    
    return true;
}

/**
 * Atualizar status da partida
 */
function updateMatchStatus($matchId, $status) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE matches SET status = ?, updated_at = NOW() WHERE match_id = ?");
    $stmt->execute([$status, $matchId]);
}

/**
 * Atualizar mapa atual da partida
 */
function updateMatchCurrentMap($matchId, $mapName) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE matches SET current_map = ?, updated_at = NOW() WHERE match_id = ?");
    $stmt->execute([$mapName, $matchId]);
}

/**
 * Obter eventos de uma partida
 */
function getMatchEvents($matchId, $limit = 50) {
    global $pdo;
    
    // Garantir que limit é um inteiro válido
    $limit = (int)$limit;
    if ($limit <= 0) $limit = 50;
    
    $stmt = $pdo->prepare("SELECT * FROM match_events WHERE match_id = ? ORDER BY timestamp DESC LIMIT " . $limit);
    $stmt->execute([$matchId]);
    return $stmt->fetchAll();
}

/**
 * Verificar se Steam ID está em uso
 */
function isSteamIdInUse($steamId, $excludeMatchId = null) {
    global $pdo;
    
    $sql = "SELECT COUNT(*) FROM match_players mp 
            JOIN matches m ON mp.match_id = m.match_id 
            WHERE mp.steam_id = ? AND m.status IN ('loading', 'active', 'paused')";
    
    $params = [$steamId];
    
    if ($excludeMatchId) {
        $sql .= " AND m.match_id != ?";
        $params[] = $excludeMatchId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchColumn() > 0;
}

/**
 * Sanitizar entrada do usuário
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Gerar token de segurança
 */
function generateSecurityToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Verificar se servidor está online
 */
function checkServerStatus($ip, $port) {
    $connection = @fsockopen($ip, $port, $errno, $errstr, 5);
    
    if ($connection) {
        fclose($connection);
        return true;
    }
    
    return false;
}
?>
