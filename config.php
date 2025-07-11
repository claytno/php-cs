<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add_server') {
            $name = sanitizeInput($_POST['name'] ?? '');
            $ip = sanitizeInput($_POST['ip'] ?? '');
            $port = (int)($_POST['port'] ?? 27015);
            $rconPassword = sanitizeInput($_POST['rcon_password'] ?? '');
            
            if (empty($name) || empty($ip) || empty($rconPassword)) {
                throw new Exception('Todos os campos são obrigatórios');
            }
            
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                throw new Exception('IP inválido');
            }
            
            if ($port < 1 || $port > 65535) {
                throw new Exception('Porta inválida');
            }
            
            // Verificar se o servidor já existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM servers WHERE ip = ? AND port = ?");
            $stmt->execute([$ip, $port]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Servidor já existe');
            }
            
            // Verificar status do servidor
            $status = checkServerStatus($ip, $port) ? 'online' : 'offline';
            
            // Inserir servidor
            $stmt = $pdo->prepare("INSERT INTO servers (name, ip, port, rcon_password, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $ip, $port, $rconPassword, $status]);
            
            $success = 'Servidor adicionado com sucesso';
            
        } elseif ($action === 'delete_server') {
            $serverId = (int)($_POST['server_id'] ?? 0);
            
            if ($serverId <= 0) {
                throw new Exception('ID do servidor inválido');
            }
            
            // Verificar se há partidas ativas no servidor
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE server_ip = (SELECT ip FROM servers WHERE id = ?) AND status IN ('loading', 'active', 'paused')");
            $stmt->execute([$serverId]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Não é possível excluir servidor com partidas ativas');
            }
            
            // Excluir servidor
            $stmt = $pdo->prepare("DELETE FROM servers WHERE id = ?");
            $stmt->execute([$serverId]);
            
            $success = 'Servidor excluído com sucesso';
            
        } elseif ($action === 'test_server') {
            $serverId = (int)($_POST['server_id'] ?? 0);
            
            if ($serverId <= 0) {
                throw new Exception('ID do servidor inválido');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM servers WHERE id = ?");
            $stmt->execute([$serverId]);
            $server = $stmt->fetch();
            
            if (!$server) {
                throw new Exception('Servidor não encontrado');
            }
            
            // Testar conexão
            $isOnline = checkServerStatus($server['ip'], $server['port']);
            $newStatus = $isOnline ? 'online' : 'offline';
            
            // Atualizar status
            $stmt = $pdo->prepare("UPDATE servers SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $serverId]);
            
            $success = 'Servidor ' . ($isOnline ? 'online' : 'offline');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obter lista de servidores
$stmt = $pdo->prepare("SELECT s.*, COUNT(m.id) as active_matches FROM servers s LEFT JOIN matches m ON s.ip = CONCAT(m.server_ip, ':', m.server_port) AND m.status IN ('loading', 'active', 'paused') GROUP BY s.id ORDER BY s.name");
$stmt->execute();
$servers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração de Servidores - MatchZy Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <header class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-orange-500">
                        <i class="fas fa-server mr-2"></i>Configuração de Servidores
                    </h1>
                    <p class="text-gray-400">Gerencie os servidores CS2 com MatchZy</p>
                </div>
                <a href="index.php" class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar
                </a>
            </div>
        </header>

        <?php if ($error): ?>
        <div class="bg-red-800 border border-red-600 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-red-400 mr-2"></i>
                <span class="text-red-100"><?= htmlspecialchars($error) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-800 border border-green-600 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-400 mr-2"></i>
                <span class="text-green-100"><?= htmlspecialchars($success) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Adicionar Servidor -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-bold mb-6 text-orange-400">
                    <i class="fas fa-plus mr-2"></i>Adicionar Servidor
                </h2>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_server">
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Nome do Servidor</label>
                        <input type="text" name="name" required
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500"
                               placeholder="Ex: Servidor Principal">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2">IP do Servidor</label>
                            <input type="text" name="ip" required
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500"
                                   placeholder="127.0.0.1">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2">Porta</label>
                            <input type="number" name="port" value="27015" min="1" max="65535" required
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Senha RCON</label>
                        <input type="password" name="rcon_password" required
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-orange-500"
                               placeholder="Senha do RCON">
                    </div>
                    
                    <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Adicionar Servidor
                    </button>
                </form>
                
                <!-- Instruções -->
                <div class="mt-8 p-4 bg-blue-900 border border-blue-700 rounded-lg">
                    <h3 class="font-bold text-blue-400 mb-2">
                        <i class="fas fa-info-circle mr-2"></i>Configuração do MatchZy
                    </h3>
                    <div class="text-sm text-blue-100 space-y-2">
                        <p>Para integrar com este sistema, adicione no <code>server.cfg</code>:</p>
                        <code class="block bg-blue-800 p-2 rounded text-xs">
                            matchzy_remote_log_url "<?= $_SERVER['HTTP_HOST'] ?>/webhook.php"<br>
                            matchzy_remote_log_header_key "Authorization"<br>
                            matchzy_remote_log_header_value "Bearer YOUR_SECRET_TOKEN"
                        </code>
                    </div>
                </div>
            </div>

            <!-- Lista de Servidores -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-bold mb-6 text-green-400">
                    <i class="fas fa-list mr-2"></i>Servidores Cadastrados
                </h2>
                
                <?php if (count($servers) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($servers as $server): ?>
                    <div class="bg-gray-700 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-bold"><?= htmlspecialchars($server['name']) ?></h3>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-1 text-xs rounded <?= $server['status'] === 'online' ? 'bg-green-600' : 'bg-red-600' ?>">
                                    <?= $server['status'] === 'online' ? 'Online' : 'Offline' ?>
                                </span>
                                <?php if ($server['active_matches'] > 0): ?>
                                <span class="px-2 py-1 text-xs bg-blue-600 rounded">
                                    <?= $server['active_matches'] ?> partida(s)
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="text-sm text-gray-300 mb-3">
                            <div><strong>IP:</strong> <?= htmlspecialchars($server['ip']) ?></div>
                            <div><strong>Porta:</strong> <?= $server['port'] ?></div>
                            <div><strong>Adicionado:</strong> <?= date('d/m/Y H:i', strtotime($server['created_at'])) ?></div>
                        </div>
                        
                        <div class="flex space-x-2">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="test_server">
                                <input type="hidden" name="server_id" value="<?= $server['id'] ?>">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-xs transition-colors">
                                    <i class="fas fa-wifi mr-1"></i>Testar
                                </button>
                            </form>
                            
                            <?php if ($server['active_matches'] == 0): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja excluir este servidor?')">
                                <input type="hidden" name="action" value="delete_server">
                                <input type="hidden" name="server_id" value="<?= $server['id'] ?>">
                                <button type="submit" class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-xs transition-colors">
                                    <i class="fas fa-trash mr-1"></i>Excluir
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <button onclick="showRconCommands(<?= $server['id'] ?>, '<?= htmlspecialchars($server['name']) ?>')" 
                                    class="bg-purple-600 hover:bg-purple-700 px-3 py-1 rounded text-xs transition-colors">
                                <i class="fas fa-terminal mr-1"></i>RCON
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center text-gray-400 py-8">
                    <i class="fas fa-server text-4xl mb-4"></i>
                    <p>Nenhum servidor cadastrado</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <div class="text-3xl font-bold text-green-400"><?= count(array_filter($servers, fn($s) => $s['status'] === 'online')) ?></div>
                <div class="text-gray-400">Servidores Online</div>
            </div>
            
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <div class="text-3xl font-bold text-red-400"><?= count(array_filter($servers, fn($s) => $s['status'] === 'offline')) ?></div>
                <div class="text-gray-400">Servidores Offline</div>
            </div>
            
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <div class="text-3xl font-bold text-blue-400"><?= array_sum(array_column($servers, 'active_matches')) ?></div>
                <div class="text-gray-400">Partidas Ativas</div>
            </div>
            
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <div class="text-3xl font-bold text-orange-400"><?= count($servers) ?></div>
                <div class="text-gray-400">Total de Servidores</div>
            </div>
        </div>
    </div>

    <!-- Modal RCON -->
    <div id="rconModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-2xl">
            <h3 class="text-lg font-bold mb-4">Comandos RCON - <span id="serverName"></span></h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Comando:</label>
                    <input type="text" id="rconCommand" 
                           class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:outline-none focus:border-purple-500"
                           placeholder="Ex: status, mp_maxrounds 30">
                </div>
                
                <div class="grid grid-cols-3 gap-2">
                    <button onclick="setRconCommand('status')" class="bg-gray-600 hover:bg-gray-500 px-3 py-1 rounded text-sm">
                        status
                    </button>
                    <button onclick="setRconCommand('mp_maxrounds 30')" class="bg-gray-600 hover:bg-gray-500 px-3 py-1 rounded text-sm">
                        mp_maxrounds 30
                    </button>
                    <button onclick="setRconCommand('mp_restartgame 1')" class="bg-gray-600 hover:bg-gray-500 px-3 py-1 rounded text-sm">
                        mp_restartgame 1
                    </button>
                </div>
                
                <div class="flex gap-4">
                    <button onclick="executeRcon()" class="flex-1 bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-terminal mr-2"></i>Executar
                    </button>
                    <button onclick="closeRconModal()" class="flex-1 bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded-lg transition-colors">
                        Fechar
                    </button>
                </div>
            </div>
            
            <div id="rconOutput" class="mt-4 p-4 bg-black rounded-lg font-mono text-sm text-green-400 hidden max-h-40 overflow-y-auto"></div>
        </div>
    </div>

    <script>
        let currentServerId = null;

        function showRconCommands(serverId, serverName) {
            currentServerId = serverId;
            document.getElementById('serverName').textContent = serverName;
            document.getElementById('rconModal').classList.remove('hidden');
            document.getElementById('rconCommand').focus();
        }

        function closeRconModal() {
            document.getElementById('rconModal').classList.add('hidden');
            document.getElementById('rconOutput').classList.add('hidden');
            document.getElementById('rconCommand').value = '';
        }

        function setRconCommand(command) {
            document.getElementById('rconCommand').value = command;
        }

        function executeRcon() {
            const command = document.getElementById('rconCommand').value.trim();
            if (!command || !currentServerId) return;

            const output = document.getElementById('rconOutput');
            output.textContent = 'Executando comando...';
            output.classList.remove('hidden');

            // Simular execução do comando RCON
            setTimeout(() => {
                output.textContent = `> ${command}\nComando executado com sucesso\n(Esta é uma simulação - implemente RCON real aqui)`;
            }, 1000);
        }

        // Fechar modal com ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeRconModal();
            }
        });
    </script>
</body>
</html>
