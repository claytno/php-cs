<?php
echo "Testando conexão com banco de dados...\n";

$host = 'app.cs2.click';
$dbname = 'cs2';
$username = 'click';
$password = 'pucon@chile';

try {
    echo "Conectando a: mysql:host=$host;dbname=$dbname\n";
    echo "Usuário: $username\n";
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Conexão bem-sucedida!\n";
    
    // Testar uma query simples
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "✅ Query teste executada: " . $result['test'] . "\n";
    
    // Verificar se tabelas existem
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✅ Tabelas encontradas: " . implode(", ", $tables) . "\n";
    
} catch (PDOException $e) {
    echo "❌ Erro de conexão: " . $e->getMessage() . "\n";
    echo "Código do erro: " . $e->getCode() . "\n";
}
?>
