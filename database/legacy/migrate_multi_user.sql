USE muscle_recovery_v2;

-- 1. Criar a tabela de utilizadores
CREATE TABLE IF NOT EXISTS users (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    name                   VARCHAR(100) NOT NULL,
    email                  VARCHAR(191) NOT NULL UNIQUE,
    password_hash          VARCHAR(255) NOT NULL,
    email_verification_token VARCHAR(100) DEFAULT NULL,
    email_verified_at      DATETIME DEFAULT NULL,
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Criar um utilizador inicial para associar os teus dados de teste atuais
-- Palavra-passe temporária de teste: "password123" (hash bcrypt nativo)
INSERT IGNORE INTO users (id, name, email, password_hash, email_verified_at)
VALUES (
    1, 
    'Demo User', 
    'demo@recovery.local', 
    '$2y$10$wEkgzP1YJpXN7Qc0q66U3OQnK9J3H.0eB2V3U2U5M1A1J8P9W2uS2', 
    NOW()
);

-- 3. Adicionar user_id à tabela user_profile e criar chave estrangeira
ALTER TABLE user_profile 
    ADD COLUMN user_id INT NOT NULL DEFAULT 1 FIRST,
    ADD CONSTRAINT fk_user_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD UNIQUE KEY unique_user_profile (user_id);

-- 4. Adicionar user_id à tabela workouts e criar chave estrangeira + índice
ALTER TABLE workouts 
    ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER id,
    ADD CONSTRAINT fk_workouts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD INDEX idx_workouts_user (user_id);