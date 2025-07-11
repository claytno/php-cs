<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Verificar se existe uma partida ativa
$activeMatch = getActiveMatch();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MatchZy - Sistema de Partidas CS2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <header class="text-center mb-12">
            <h1 class="text-5xl font-bold text-orange-500 mb-4">
                <i class="fas fa-gamepad mr-3"></i>MatchZy Manager
            </h1>
            <p class="text-gray-400 text-lg">Sistema de Criação e Gerenciamento de Partidas CS2</p>
        </header>

        <!-- Status da Partida Ativa -->
        <?php if ($activeMatch): ?>
        <div class="bg-green-800 border border-green-600 rounded-lg p-6 mb-8">
            <h2 class="text-2xl font-bold text-green-400 mb-4">
                <i class="fas fa-play-circle mr-2"></i>Partida Ativa
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <strong>ID:</strong> <?= htmlspecialchars($activeMatch['match_id']) ?>
                </div>
                <div>
                    <strong>Time 1:</strong> <?= htmlspecialchars($activeMatch['team1_name']) ?>
                </div>
                <div>
                    <strong>Time 2:</strong> <?= htmlspecialchars($activeMatch['team2_name']) ?>
                </div>
                <div>
                    <strong>Mapa:</strong> <?= htmlspecialchars($activeMatch['current_map']) ?>
                </div>
                <div>
                    <strong>Status:</strong> <span class="text-green-400"><?= htmlspecialchars($activeMatch['status']) ?></span>
                </div>
                <div>
                    <strong>Criada:</strong> <?= date('d/m/Y H:i', strtotime($activeMatch['created_at'])) ?>
                </div>
            </div>
            <div class="mt-4 flex gap-4">
                <a href="match_control.php?id=<?= $activeMatch['id'] ?>" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-cog mr-2"></i>Controlar Partida
                </a>
                <a href="match_stats.php?id=<?= $activeMatch['id'] ?>" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i>Estatísticas
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cards de Ações Principais -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <!-- Criar Partida Rápida -->
            <div class="bg-gray-800 rounded-lg p-6 hover:bg-gray-700 transition-colors">
                <div class="text-center">
                    <div class="text-4xl text-green-500 mb-4">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Partida Rápida</h3>
                    <p class="text-gray-400 mb-4">Times pré-definidos - Formato CS2</p>
                    <a href="create_match_simple.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition-colors inline-block">
                        Criar Rápida
                    </a>
                </div>
            </div>

            <!-- Criar Nova Partida -->
            <div class="bg-gray-800 rounded-lg p-6 hover:bg-gray-700 transition-colors">
                <div class="text-center">
                    <div class="text-4xl text-orange-500 mb-4">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Partida Customizada</h3>
                    <p class="text-gray-400 mb-4">Configuração completa de partida</p>
                    <a href="create_match.php" class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-2 rounded-lg transition-colors inline-block">
                        Criar Partida
                    </a>
                </div>
            </div>

            <!-- Gerenciar Partidas -->
            <div class="bg-gray-800 rounded-lg p-6 hover:bg-gray-700 transition-colors">
                <div class="text-center">
                    <div class="text-4xl text-blue-500 mb-4">
                        <i class="fas fa-list"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Partidas</h3>
                    <p class="text-gray-400 mb-4">Visualizar e gerenciar partidas</p>
                    <a href="matches.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors inline-block">
                        Ver Partidas
                    </a>
                </div>
            </div>

            <!-- Estatísticas -->
            <div class="bg-gray-800 rounded-lg p-6 hover:bg-gray-700 transition-colors">
                <div class="text-center">
                    <div class="text-4xl text-purple-500 mb-4">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Estatísticas</h3>
                    <p class="text-gray-400 mb-4">Relatórios e análises</p>
                    <a href="stats.php" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg transition-colors inline-block">
                        Ver Stats
                    </a>
                </div>
            </div>

            <!-- Configurações -->
            <div class="bg-gray-800 rounded-lg p-6 hover:bg-gray-700 transition-colors">
                <div class="text-center">
                    <div class="text-4xl text-green-500 mb-4">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Configurações</h3>
                    <p class="text-gray-400 mb-4">Configurar servidores e mapas</p>
                    <a href="config.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg transition-colors inline-block">
                        Configurar
                    </a>
                </div>
            </div>

            <!-- Logs de Eventos -->
            <div class="bg-gray-800 rounded-lg p-6 hover:bg-gray-700 transition-colors">
                <div class="text-center">
                    <div class="text-4xl text-yellow-500 mb-4">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Logs</h3>
                    <p class="text-gray-400 mb-4">Eventos e logs do servidor</p>
                    <a href="logs.php" class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-2 rounded-lg transition-colors inline-block">
                        Ver Logs
                    </a>
                </div>
            </div>

            <!-- Webhooks -->
            <div class="bg-gray-800 rounded-lg p-6 hover:bg-gray-700 transition-colors">
                <div class="text-center">
                    <div class="text-4xl text-red-500 mb-4">
                        <i class="fas fa-webhook"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Webhooks</h3>
                    <p class="text-gray-400 mb-4">Receber eventos do MatchZy</p>
                    <a href="webhooks.php" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg transition-colors inline-block">
                        Configurar
                    </a>
                </div>
            </div>
        </div>

        <!-- Últimas Partidas -->
        <div class="bg-gray-800 rounded-lg p-6">
            <h2 class="text-2xl font-bold mb-6">
                <i class="fas fa-history mr-2"></i>Últimas Partidas
            </h2>
            <div class="overflow-x-auto">
                <?php 
                $recentMatches = getRecentMatches(5);
                if (count($recentMatches) > 0): 
                ?>
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-gray-700">
                            <th class="pb-3">ID</th>
                            <th class="pb-3">Times</th>
                            <th class="pb-3">Mapa</th>
                            <th class="pb-3">Status</th>
                            <th class="pb-3">Data</th>
                            <th class="pb-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentMatches as $match): ?>
                        <tr class="border-b border-gray-700">
                            <td class="py-3 text-orange-400"><?= htmlspecialchars($match['match_id']) ?></td>
                            <td class="py-3">
                                <?= htmlspecialchars($match['team1_name']) ?> vs <?= htmlspecialchars($match['team2_name']) ?>
                            </td>
                            <td class="py-3"><?= htmlspecialchars($match['current_map']) ?></td>
                            <td class="py-3">
                                <span class="px-2 py-1 bg-<?= getStatusColor($match['status']) ?>-600 rounded text-xs">
                                    <?= htmlspecialchars($match['status']) ?>
                                </span>
                            </td>
                            <td class="py-3"><?= date('d/m/Y H:i', strtotime($match['created_at'])) ?></td>
                            <td class="py-3">
                                <a href="match_details.php?id=<?= $match['id'] ?>" class="text-blue-400 hover:text-blue-300 mr-3">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($match['status'] === 'active'): ?>
                                <a href="match_control.php?id=<?= $match['id'] ?>" class="text-green-400 hover:text-green-300">
                                    <i class="fas fa-play"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="text-center text-gray-400 py-8">
                    <i class="fas fa-inbox text-4xl mb-4"></i>
                    <p>Nenhuma partida encontrada. <a href="create_match.php" class="text-orange-400 hover:text-orange-300">Criar primeira partida</a></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="text-center text-gray-500 py-8">
        <p>&copy; 2025 MatchZy Manager - Sistema de Partidas CS2</p>
    </footer>
</body>
</html>
