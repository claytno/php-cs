-- MatchZy Manager - Schema Básico para Teste
USE matchzy_manager;

-- Tabela de servidores
CREATE TABLE IF NOT EXISTS servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    ip VARCHAR(255) NOT NULL,
    port INT NOT NULL,
    rcon_password VARCHAR(255) NOT NULL,
    status ENUM('online', 'offline', 'busy') DEFAULT 'offline',
    current_match_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_server (ip, port)
);

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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de jogadores
CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    steam_id VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de relação partida-jogadores
CREATE TABLE IF NOT EXISTS match_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id VARCHAR(255),
    steam_id VARCHAR(255),
    team ENUM('team1', 'team2', 'spec') NOT NULL,
    is_captain BOOLEAN DEFAULT false,
    
    FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE CASCADE,
    FOREIGN KEY (steam_id) REFERENCES players(steam_id) ON DELETE CASCADE
);

-- Tabela de eventos das partidas
CREATE TABLE IF NOT EXISTS match_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id VARCHAR(255),
    event_type VARCHAR(100) NOT NULL,
    event_data JSON,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE CASCADE
);

-- Inserir servidor de teste
INSERT IGNORE INTO servers (name, ip, port, rcon_password, status) VALUES 
('Servidor de Teste', '127.0.0.1', 27015, 'senha123', 'online');

-- Inserir jogadores de teste
INSERT IGNORE INTO players (steam_id, name) VALUES 
('76561198123456789', 'Player1'),
('76561198123456790', 'Player2'),
('76561198123456791', 'Player3'),
('76561198123456792', 'Player4'),
('76561198123456793', 'Player5'),
('76561198123456794', 'Player6'),
('76561198123456795', 'Player7'),
('76561198123456796', 'Player8'),
('76561198123456797', 'Player9'),
('76561198123456798', 'Player10');

SELECT 'Database setup completed successfully!' as status;
