<?php
/**
 * Webhook endpoint para receber eventos do MatchZy
 * 
 * Configure este endpoint no servidor CS2 usando:
 * matchzy_remote_log_url "http://seusite.com/webhook.php"
 */

header('Content-Type: application/json');

require_once 'config/database.php';
require_once 'includes/functions.php';

// Função para responder
function respond($status, $message) {
    http_response_code($status);
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// Verificar método da requisição
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'Método não permitido');
}

// Obter dados do corpo da requisição
$input = file_get_contents('php://input');
if (empty($input)) {
    respond(400, 'Dados não fornecidos');
}

// Decodificar JSON
$eventData = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    respond(400, 'JSON inválido');
}

// Log do evento recebido
error_log('MatchZy Event: ' . $input);

try {
    // Processar o evento
    $processed = processMatchZyEvent($eventData);
    
    if ($processed) {
        respond(200, 'Evento processado com sucesso');
    } else {
        respond(400, 'Erro ao processar evento');
    }
    
} catch (Exception $e) {
    error_log('Erro no webhook: ' . $e->getMessage());
    respond(500, 'Erro interno do servidor');
}
?>
