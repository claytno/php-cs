# MatchZy Manager - Sistema de Criação de Partidas CS2

Um sistema web completo para gerenciar partidas de CS2 usando o plugin MatchZy. Desenvolvido em PHP com interface moderna em Tailwind CSS.

## 🚀 Funcionalidades

### ✨ Principais
- **Criação de Partidas**: Interface intuitiva para configurar partidas completas
- **Gerenciamento de Servidores**: Cadastro e monitoramento de servidores CS2
- **Controle em Tempo Real**: Pausa, retoma, reinicia rounds e controla partidas ativas
- **Webhook Integration**: Recebe eventos do MatchZy em tempo real
- **Sistema de Logs**: Histórico completo de eventos das partidas
- **API REST**: Geração automática de configurações JSON para MatchZy

### 🎯 Recursos Avançados
- **Sistema de Veto**: Configuração completa do sistema de escolha de mapas
- **Múltiplos Mapas**: Suporte para Bo1, Bo3, Bo5 e séries personalizadas
- **Estatísticas**: Relatórios detalhados de partidas e jogadores
- **Configuração Flexível**: Rounds customizáveis, overtime, knife round
- **Interface Responsiva**: Funciona perfeitamente em desktop e mobile

## 📋 Requisitos

### Servidor Web
- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Apache/Nginx com mod_rewrite

### Servidor CS2
- Counter-Strike 2 Dedicated Server
- Plugin MatchZy instalado e configurado
- CounterStrikeSharp framework

## 🛠️ Instalação

### 1. Clone o Repositório
```bash
git clone https://github.com/seu-usuario/matchzy-manager.git
cd matchzy-manager
```

### 2. Configure o Banco de Dados
```sql
CREATE DATABASE matchzy_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Edite `config/database.php` com suas credenciais:
```php
$host = 'localhost';
$dbname = 'matchzy_manager';
$username = 'seu_usuario';
$password = 'sua_senha';
```

### 3. Configure o Servidor Web
Configure seu servidor web para apontar para a pasta do projeto.

Exemplo de configuração Apache (.htaccess):
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/$1 [L]
```

### 4. Configure o MatchZy
No arquivo `server.cfg` do seu servidor CS2:
```
// Configuração do MatchZy para integração
matchzy_remote_log_url "http://seu-site.com/webhook.php"
matchzy_remote_log_header_key "Authorization"
matchzy_remote_log_header_value "Bearer SeuTokenSecreto"

// Configurações recomendadas
matchzy_minimum_ready_required 2
matchzy_autostart_mode 1
matchzy_kick_when_no_match_loaded true
```

## 📖 Como Usar

### 1. Adicionar Servidores
1. Acesse `Configurações > Servidores`
2. Clique em "Adicionar Servidor"
3. Preencha IP, porta e senha RCON
4. Teste a conexão

### 2. Criar Partida
1. Na página inicial, clique em "Nova Partida"
2. Configure os times e jogadores (Steam64 IDs)
3. Selecione mapas e configurações
4. Escolha o servidor
5. Clique em "Criar Partida"

### 3. Controlar Partida
1. Acesse `Partidas > Controlar`
2. Use os botões para:
   - Iniciar partida
   - Pausar/Retomar
   - Reiniciar round
   - Trocar mapa
   - Finalizar partida

### 4. Monitorar Eventos
- Os eventos do MatchZy são recebidos automaticamente via webhook
- Visualize logs em tempo real na página de controle
- Histórico completo disponível em `Logs`

## 🔧 API Endpoints

### GET /api/match_config.php?id={MATCH_ID}
Retorna a configuração JSON da partida no formato MatchZy.

Exemplo de resposta:
```json
{
    "matchid": "match_20250711_143052_a1b2c3d4",
    "num_maps": 3,
    "players_per_team": 5,
    "min_players_to_ready": 2,
    "skip_veto": false,
    "maplist": ["de_dust2", "de_mirage", "de_inferno"],
    "team1": {
        "name": "Team Alpha",
        "players": {
            "76561198123456789": "Player1",
            "76561198123456790": "Player2"
        }
    },
    "team2": {
        "name": "Team Beta",
        "players": {
            "76561198123456791": "Player3",
            "76561198123456792": "Player4"
        }
    }
}
```

### POST /webhook.php
Recebe eventos do MatchZy em tempo real.

Eventos suportados:
- `series_start` - Início da série
- `map_start` - Início do mapa
- `round_end` - Fim do round
- `match_paused` - Partida pausada
- `match_unpaused` - Partida retomada
- `series_end` - Fim da série

## 📁 Estrutura do Projeto

```
matchzy-manager/
├── config/
│   └── database.php          # Configuração do banco
├── includes/
│   └── functions.php         # Funções auxiliares
├── api/
│   └── match_config.php      # API de configuração
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── index.php                 # Página inicial
├── create_match.php          # Criar partida
├── matches.php               # Listar partidas
├── match_control.php         # Controlar partida
├── match_details.php         # Detalhes da partida
├── config.php                # Configuração de servidores
├── webhook.php               # Endpoint para eventos
├── logs.php                  # Visualizar logs
└── README.md                 # Esta documentação
```

## 🎮 Comandos MatchZy Integrados

O sistema integra automaticamente com os principais comandos do MatchZy:

### Comandos de Partida
- `matchzy_loadmatch_url` - Carrega configuração da partida
- `matchzy_endmatch` - Finaliza partida
- `mp_pause_match` - Pausa partida
- `mp_unpause_match` - Retoma partida
- `mp_restartgame` - Reinicia round

### Comandos de Administração
- `matchzy_addplayer` - Adiciona jogador à partida
- `matchzy_removeplayer` - Remove jogador da partida
- `changelevel` - Troca mapa

## 🔐 Segurança

### Tokens de Autenticação
Configure tokens seguros para webhook:
```php
// No webhook.php
$expectedToken = 'SeuTokenMuitoSeguro123';
$receivedToken = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if ($receivedToken !== "Bearer $expectedToken") {
    http_response_code(401);
    exit('Unauthorized');
}
```

### Validação de Dados
- Todos os inputs são sanitizados
- Steam IDs são validados
- IPs e portas são verificados
- Proteção contra SQL Injection

## 📊 Banco de Dados

### Tabelas Principais

#### `matches`
- Armazena configurações das partidas
- Status, times, jogadores, mapas
- Configurações específicas (rounds, overtime, etc.)

#### `match_events`
- Log de todos os eventos das partidas
- Dados JSON com detalhes dos eventos
- Timestamp para análise temporal

#### `servers`
- Cadastro de servidores CS2
- Status e informações de conexão
- Senhas RCON criptografadas

#### `players`
- Cache de informações dos jogadores
- Steam ID, nome, avatar
- Histórico de participações

## 🚀 Deploy em Produção

### 1. Configuração do Servidor
```bash
# Instalar dependências
sudo apt update
sudo apt install apache2 php php-mysql mysql-server

# Configurar virtual host
sudo nano /etc/apache2/sites-available/matchzy-manager.conf
```

### 2. SSL/HTTPS
Configure SSL para comunicação segura:
```bash
sudo certbot --apache -d seu-dominio.com
```

### 3. Backup Automático
Configure backup automático do banco:
```bash
# Crontab para backup diário
0 2 * * * mysqldump -u usuario -p senha matchzy_manager > /backup/matchzy_$(date +%Y%m%d).sql
```

## 🤝 Contribuindo

1. Faça um fork do projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📝 Licença

Este projeto está licenciado sob a MIT License - veja o arquivo [LICENSE](LICENSE) para detalhes.

## 🆘 Suporte

### Problemas Comuns

#### Webhook não recebe eventos
1. Verifique se o URL está acessível externamente
2. Confirme a configuração no `server.cfg`
3. Verifique logs do Apache/Nginx

#### Erro de conexão com servidor
1. Teste a conectividade de rede
2. Verifique se a porta RCON está aberta
3. Confirme a senha RCON

#### Partida não inicia
1. Verifique se o MatchZy está carregado
2. Confirme se os jogadores estão conectados
3. Verifique logs do servidor CS2

### Links Úteis
- [Documentação MatchZy](https://shobhit-pathak.github.io/MatchZy/)
- [CounterStrikeSharp](https://docs.cssharp.dev/)
- [Steam Web API](https://steamcommunity.com/dev)

## 🏆 Créditos

- **MatchZy Plugin**: [Shobhit Pathak](https://github.com/shobhit-pathak/MatchZy)
- **CounterStrikeSharp**: [CounterStrikeSharp Team](https://github.com/roflmuffin/CounterStrikeSharp)
- **Tailwind CSS**: [Tailwind Labs](https://tailwindcss.com/)

---

Desenvolvido com ❤️ para a comunidade CS2 brasileira.
