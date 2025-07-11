-- MatchZy Manager Database Schema
-- Execute este script para criar o banco de dados e tabelas

-- Criar banco de dados
CREATE DATABASE IF NOT EXISTS matchzy_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE matchzy_manager;

-- Tabela de partidas
CREATE TABLE IF NOT EXISTS matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id VARCHAR(255) UNIQUE NOT NULL,
    team1_name VARCHAR(255) NOT NULL,
    team2_name VARCHAR(255) NOT NULL,
    team1_players JSON,
    team2_players JSON,
    maps JSON,
    current_map VARCHAR(255),
    status ENUM('created', 'loading', 'active', 'paused', 'finished', 'cancelled') DEFAULT 'created',
    server_ip VARCHAR(255),
    server_port INT,
    rcon_password VARCHAR(255),
    knife_round BOOLEAN DEFAULT true,
    overtime_enabled BOOLEAN DEFAULT true,
    max_rounds INT DEFAULT 30,
    config JSON,
    team1_score INT DEFAULT 0,
    team2_score INT DEFAULT 0,
    current_round INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_match_id (match_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Tabela de eventos das partidas
CREATE TABLE IF NOT EXISTS match_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id VARCHAR(255),
    event_type VARCHAR(100) NOT NULL,
    event_data JSON,
    round_number INT DEFAULT 0,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE CASCADE,
    INDEX idx_match_id (match_id),
    INDEX idx_event_type (event_type),
    INDEX idx_timestamp (timestamp)
);

-- Tabela de servidores
CREATE TABLE IF NOT EXISTS servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    ip VARCHAR(255) NOT NULL,
    port INT NOT NULL,
    rcon_password VARCHAR(255) NOT NULL,
    status ENUM('online', 'offline', 'busy') DEFAULT 'offline',
    current_match_id VARCHAR(255) NULL,
    max_players INT DEFAULT 10,
    location VARCHAR(100) DEFAULT 'BR',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (current_match_id) REFERENCES matches(match_id) ON DELETE SET NULL,
    UNIQUE KEY unique_server (ip, port),
    INDEX idx_status (status)
);

-- Tabela de jogadores
CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    steam_id VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500),
    country VARCHAR(3) DEFAULT 'BR',
    total_matches INT DEFAULT 0,
    wins INT DEFAULT 0,
    losses INT DEFAULT 0,
    total_kills INT DEFAULT 0,
    total_deaths INT DEFAULT 0,
    total_assists INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_steam_id (steam_id),
    INDEX idx_name (name)
);

-- Tabela de relação partida-jogadores
CREATE TABLE IF NOT EXISTS match_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id VARCHAR(255),
    steam_id VARCHAR(255),
    team ENUM('team1', 'team2', 'spec') NOT NULL,
    is_captain BOOLEAN DEFAULT false,
    kills INT DEFAULT 0,
    deaths INT DEFAULT 0,
    assists INT DEFAULT 0,
    score INT DEFAULT 0,
    mvps INT DEFAULT 0,
    
    FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE CASCADE,
    FOREIGN KEY (steam_id) REFERENCES players(steam_id) ON DELETE CASCADE,
    UNIQUE KEY unique_match_player (match_id, steam_id),
    INDEX idx_match_id (match_id),
    INDEX idx_steam_id (steam_id),
    INDEX idx_team (team)
);

-- Tabela de rounds detalhados
CREATE TABLE IF NOT EXISTS match_rounds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id VARCHAR(255),
    round_number INT NOT NULL,
    winner_team ENUM('team1', 'team2') NOT NULL,
    win_type VARCHAR(50), -- 'elimination', 'bomb_defused', 'bomb_exploded', 'time_expired'
    round_data JSON, -- Dados detalhados do round (kills, economia, etc.)
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE CASCADE,
    UNIQUE KEY unique_match_round (match_id, round_number),
    INDEX idx_match_id (match_id),
    INDEX idx_round_number (round_number)
);

-- Tabela de webhooks configurados
CREATE TABLE IF NOT EXISTS webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    secret VARCHAR(255),
    events JSON, -- Array de eventos que este webhook deve receber
    active BOOLEAN DEFAULT true,
    last_triggered TIMESTAMP NULL,
    success_count INT DEFAULT 0,
    error_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_active (active)
);

-- Tabela de configurações do sistema
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key)
);

-- Tabela de sessões de administração
CREATE TABLE IF NOT EXISTS admin_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    user_ip VARCHAR(45),
    user_agent TEXT,
    data JSON,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_session_id (session_id),
    INDEX idx_expires_at (expires_at)
);

-- Inserir configurações padrão
INSERT INTO settings (setting_key, setting_value, description) VALUES
('site_name', 'MatchZy Manager', 'Nome do site'),
('default_max_rounds', '30', 'Número padrão de rounds máximos'),
('default_overtime', '1', 'Overtime habilitado por padrão (1=sim, 0=não)'),
('default_knife_round', '1', 'Round de faca habilitado por padrão (1=sim, 0=não)'),
('webhook_secret', '', 'Token secreto para autenticação de webhooks'),
('steam_api_key', '', 'Chave da API do Steam para buscar dados dos jogadores'),
('timezone', 'America/Sao_Paulo', 'Fuso horário do sistema'),
('maintenance_mode', '0', 'Modo de manutenção (1=ativo, 0=inativo)')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Inserir mapas padrão do CS2
INSERT INTO settings (setting_key, setting_value, description) VALUES
('available_maps', '["de_dust2", "de_mirage", "de_inferno", "de_cache", "de_overpass", "de_vertigo", "de_ancient", "de_anubis", "de_nuke", "de_train"]', 'Lista de mapas disponíveis para as partidas')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Criar views úteis
CREATE OR REPLACE VIEW active_matches AS
SELECT 
    m.*,
    s.name as server_name,
    TIMESTAMPDIFF(MINUTE, m.created_at, NOW()) as duration_minutes
FROM matches m
LEFT JOIN servers s ON m.server_ip = s.ip AND m.server_port = s.port
WHERE m.status IN ('loading', 'active', 'paused');

CREATE OR REPLACE VIEW match_statistics AS
SELECT 
    m.match_id,
    m.team1_name,
    m.team2_name,
    m.status,
    COUNT(me.id) as total_events,
    COUNT(CASE WHEN me.event_type = 'round_end' THEN 1 END) as total_rounds,
    MAX(me.timestamp) as last_event_time,
    m.created_at,
    m.updated_at
FROM matches m
LEFT JOIN match_events me ON m.match_id = me.match_id
GROUP BY m.match_id;

CREATE OR REPLACE VIEW player_statistics AS
SELECT 
    p.steam_id,
    p.name,
    COUNT(DISTINCT mp.match_id) as matches_played,
    SUM(mp.kills) as total_kills,
    SUM(mp.deaths) as total_deaths,
    SUM(mp.assists) as total_assists,
    ROUND(SUM(mp.kills) / NULLIF(SUM(mp.deaths), 0), 2) as kd_ratio,
    SUM(mp.mvps) as total_mvps
FROM players p
LEFT JOIN match_players mp ON p.steam_id = mp.steam_id
GROUP BY p.steam_id, p.name;

-- Criar triggers para manter estatísticas atualizadas
DELIMITER //

CREATE TRIGGER update_player_stats_after_match
AFTER UPDATE ON matches
FOR EACH ROW
BEGIN
    IF NEW.status = 'finished' AND OLD.status != 'finished' THEN
        UPDATE players p
        SET total_matches = (
            SELECT COUNT(*) FROM match_players mp 
            JOIN matches m ON mp.match_id = m.match_id 
            WHERE mp.steam_id = p.steam_id AND m.status = 'finished'
        )
        WHERE p.steam_id IN (
            SELECT steam_id FROM match_players WHERE match_id = NEW.match_id
        );
    END IF;
END//

CREATE TRIGGER log_server_status_change
AFTER UPDATE ON servers
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO match_events (match_id, event_type, event_data)
        VALUES (
            'system',
            'server_status_change',
            JSON_OBJECT(
                'server_id', NEW.id,
                'server_name', NEW.name,
                'old_status', OLD.status,
                'new_status', NEW.status
            )
        );
    END IF;
END//

DELIMITER ;

-- Criar índices otimizados para consultas frequentes
CREATE INDEX idx_matches_status_created ON matches(status, created_at DESC);
CREATE INDEX idx_events_match_timestamp ON match_events(match_id, timestamp DESC);
CREATE INDEX idx_players_performance ON match_players(steam_id, kills DESC, deaths ASC);

-- Comentários nas tabelas
ALTER TABLE matches COMMENT = 'Tabela principal das partidas com configurações e status';
ALTER TABLE match_events COMMENT = 'Log de eventos das partidas recebidos via webhook';
ALTER TABLE servers COMMENT = 'Cadastro de servidores CS2 disponíveis';
ALTER TABLE players COMMENT = 'Cache de informações dos jogadores Steam';
ALTER TABLE match_players COMMENT = 'Relação entre partidas e jogadores com estatísticas';
ALTER TABLE webhooks COMMENT = 'Configuração de webhooks para notificações externas';
ALTER TABLE settings COMMENT = 'Configurações gerais do sistema';

-- Verificar integridade dos dados
-- SELECT 'Database schema created successfully!' as status;
