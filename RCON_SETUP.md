# Implementação RCON Real - Guia

## ⚠️ IMPORTANTE: DESENVOLVIMENTO vs PRODUÇÃO

Atualmente o sistema está usando **simulação RCON** para desenvolvimento. Para usar em produção real, você precisa implementar conexão RCON verdadeira.

## 🔧 Como Implementar RCON Real

### 1. Instalar biblioteca RCON para PHP

```bash
composer require xpaw/php-source-query-rcon
```

### 2. Substituir função simulateRconResponse

No arquivo `includes/functions.php`, substitua a função `executeRconCommand` por:

```php
function executeRconCommand($serverIdOrIp, $command) {
    global $pdo;
    
    // Buscar dados do servidor...
    // (código existente para buscar servidor)
    
    try {
        // Usar biblioteca RCON real
        require_once 'vendor/autoload.php';
        
        $rcon = new \xPaw\SourceQuery\SourceRcon();
        $rcon->Connect($server['ip'], $server['port'], $server['rcon_password']);
        
        $response = $rcon->Command($command);
        $rcon->Disconnect();
        
        return [
            'success' => true,
            'message' => "RCON executado com sucesso",
            'console_output' => $response,
            'command' => $command
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Erro RCON: " . $e->getMessage(),
            'console_output' => "Falha na conexão RCON",
            'command' => $command
        ];
    }
}
```

### 3. Configurar dados do servidor

Certifique-se que a tabela `servers` tem:
- `ip`: IP do servidor CS2
- `port`: Porta RCON (padrão 27015)
- `rcon_password`: Senha RCON configurada no servidor

### 4. Configurar servidor CS2

No arquivo `server.cfg` do seu servidor CS2:

```
// RCON Configuration
rcon_password "sua_senha_rcon_segura"
sv_rcon_banpenalty 0
sv_rcon_log 1
sv_rcon_minfailures 5
sv_rcon_minfailuretime 30
sv_rcon_maxfailures 10

// MatchZy Configuration
matchzy_kick_when_no_match_loaded false
```

## 🎮 Verificar se MatchZy está funcionando

### Comandos de teste no console do servidor:

```
plugin_print
meta list
matchzy_version
matchzy_status
```

### Se MatchZy não aparecer:

1. **Verificar instalação:**
   - MatchZy deve estar em: `csgo/addons/counterstrikesharp/plugins/MatchZy/`
   - Metamod deve estar instalado
   - CounterStrikeSharp deve estar instalado

2. **Verificar logs:**
   - `csgo/logs/` - logs do servidor
   - `csgo/addons/counterstrikesharp/logs/` - logs do CS#

3. **Comandos de reload:**
   ```
   css_plugins reload
   css_plugins load MatchZy
   ```

## 🔍 Debug de problemas comuns

### Erro: "Unknown command: matchzy_loadmatch_url"
- MatchZy não está instalado ou carregado
- Verificar se CounterStrikeSharp está funcionando

### Erro: "Invalid URL" 
- Servidor não consegue acessar a URL
- JSON malformado
- Campos obrigatórios faltando

### Erro: "Failed to load match configuration"
- Verificar se JSON tem todos os campos obrigatórios:
  - `matchid`
  - `team1` (com `name` e `players`)
  - `team2` (com `name` e `players`) 
  - `num_maps`
  - `maplist`

## 📋 Checklist de implementação

- [ ] Instalar biblioteca RCON
- [ ] Substituir função simulateRconResponse
- [ ] Configurar dados do servidor na tabela `servers`
- [ ] Configurar RCON no servidor CS2
- [ ] Instalar MatchZy no servidor
- [ ] Testar comandos básicos
- [ ] Testar carregamento de match

## 🆘 Se ainda não funcionar

1. Use o botão "Diagnóstico" para verificar plugins
2. Use o botão "Validar JSON" para verificar configuração
3. Use o botão "Testar URL" para verificar conectividade
4. Verifique logs do servidor CS2
5. Teste comando manual no console: `matchzy_loadmatch_url "https://sua-url/api/match_config.php?id=MATCH_ID"`
