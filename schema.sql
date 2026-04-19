-- Scorekeeper database schema
-- Run: sudo mysql scorekeeper < schema.sql

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    pin_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    max_score_per_round INT NOT NULL,
    game_type VARCHAR(16) NOT NULL DEFAULT 'canasta',
    meld_minimum INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at TIMESTAMP NULL DEFAULT NULL,
    last_activity_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS session_players (
    session_id INT NOT NULL,
    player_id INT NOT NULL,
    display_order INT NOT NULL,
    PRIMARY KEY (session_id, player_id),
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rounds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    round_number INT NOT NULL,
    target_score INT NOT NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL DEFAULT NULL,
    winner_player_id INT NULL,
    FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_player_id) REFERENCES players(id) ON DELETE SET NULL,
    INDEX idx_session_ended (session_id, ended_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    round_id INT NOT NULL,
    game_number INT NOT NULL,
    winner_player_id INT NULL,
    dealer_player_id INT NULL,
    played_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (round_id) REFERENCES rounds(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_player_id) REFERENCES players(id) ON DELETE SET NULL,
    FOREIGN KEY (dealer_player_id) REFERENCES players(id) ON DELETE SET NULL,
    INDEX idx_round_game (round_id, game_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS game_scores (
    game_id INT NOT NULL,
    player_id INT NOT NULL,
    score INT NOT NULL,
    is_winner TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (game_id, player_id),
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS meld_thresholds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    score_from INT NULL,
    score_to INT NULL,
    meld_minimum INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- View: aggregated round totals per player
CREATE OR REPLACE VIEW v_round_totals AS
SELECT
    r.id AS round_id,
    sp.player_id,
    COALESCE(SUM(gs.score), 0) AS round_total
FROM rounds r
JOIN session_players sp ON sp.session_id = r.session_id
LEFT JOIN games g ON g.round_id = r.id
LEFT JOIN game_scores gs ON gs.game_id = g.id AND gs.player_id = sp.player_id
GROUP BY r.id, sp.player_id;
