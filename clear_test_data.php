<?php
require_once 'config/database.php';

echo "Limpando dados antigos...\n";

// Limpar dados de teste antigos
$pdo->exec("DELETE FROM match_players");
$pdo->exec("DELETE FROM match_events");
$pdo->exec("DELETE FROM matches");
$pdo->exec("DELETE FROM players");

echo "Dados antigos removidos.\n";

echo "Inserindo dados básicos...\n";

// Inserir servidor de teste
$stmt = $pdo->prepare("INSERT IGNORE INTO servers (id, name, ip, port, rcon_password, status) VALUES (1, 'Servidor Teste', '127.0.0.1', 27015, 'test123', 'online')");
$stmt->execute();

echo "Servidor de teste criado.\n";
echo "Pronto para criar nova partida!\n";
?>
