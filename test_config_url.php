<?php
/**
 * Teste direto da URL de configuração MatchZy
 * Este arquivo pode ser usado para testar se a API está funcionando
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// Obter a partida mais recente para teste
$stmt = $pdo->prepare("SELECT match_id FROM matches ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$match = $stmt->fetch();

if (!$match) {
    die("Erro: Nenhuma partida encontrada no banco de dados");
}

$matchId = $match['match_id'];
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$configUrl = $protocol . '://' . $host . '/api/match_config.php?id=' . $matchId;

echo "<h2>Teste da URL de Configuração MatchZy</h2>";
echo "<p><strong>Match ID:</strong> {$matchId}</p>";
echo "<p><strong>URL:</strong> <a href='{$configUrl}' target='_blank'>{$configUrl}</a></p>";

echo "<h3>Testando acesso à URL...</h3>";

// Testar acesso à URL
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'method' => 'GET',
        'header' => 'User-Agent: MatchZy-Tester/1.0'
    ]
]);

$response = @file_get_contents($configUrl, false, $context);

if ($response === false) {
    $error = error_get_last();
    echo "<div style='color: red;'>";
    echo "<strong>❌ ERRO:</strong> Não foi possível acessar a URL<br>";
    echo "<strong>Erro:</strong> " . ($error['message'] ?? 'Erro desconhecido') . "<br>";
    echo "</div>";
} else {
    echo "<div style='color: green;'>";
    echo "<strong>✅ SUCESSO:</strong> URL acessível!<br>";
    echo "</div>";
    
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo "<h3>Conteúdo JSON válido:</h3>";
        echo "<pre style='background: #f0f0f0; padding: 10px; border-radius: 5px; overflow: auto; max-height: 400px;'>";
        echo htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT));
        echo "</pre>";
    } else {
        echo "<div style='color: orange;'>";
        echo "<strong>⚠️ AVISO:</strong> URL acessível mas JSON inválido<br>";
        echo "<strong>Resposta raw:</strong><br>";
        echo "<pre style='background: #f0f0f0; padding: 10px; border-radius: 5px;'>";
        echo htmlspecialchars($response);
        echo "</pre>";
        echo "</div>";
    }
}

echo "<h3>Comandos MatchZy sugeridos:</h3>";
echo "<div style='background: #f0f0f0; padding: 10px; border-radius: 5px;'>";
echo "<p><strong>Comando 1 (sem aspas):</strong></p>";
echo "<code>matchzy_loadmatch_url {$configUrl}</code><br><br>";
echo "<p><strong>Comando 2 (com aspas duplas):</strong></p>";
echo "<code>matchzy_loadmatch_url \"{$configUrl}\"</code><br><br>";
echo "<p><strong>Comando 3 (com aspas simples):</strong></p>";
echo "<code>matchzy_loadmatch_url '{$configUrl}'</code><br><br>";
echo "<p><strong>Comando alternativo Get5:</strong></p>";
echo "<code>get5_loadmatch_url {$configUrl}</code>";
echo "</div>";

echo "<hr>";
echo "<p><strong>Data/Hora do teste:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><a href='match_control.php?match_id={$matchId}'>← Voltar ao controle da partida</a></p>";
?>
