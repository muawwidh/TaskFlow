-- ============================================================
-- TaskFlow - Database Migration Script
-- Run this file once to set up the database schema
-- Usage: mysql -u root -p < migration.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS taskflow_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE taskflow_db;

-- Table: users
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)        NOT NULL,
    email       VARCHAR(191)        NOT NULL UNIQUE,
    password    VARCHAR(255)        NOT NULL,
    avatar      VARCHAR(2)          NOT NULL DEFAULT '🧑',
    bio         VARCHAR(300)        NULL,
    role        ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB;

-- Table: teams
CREATE TABLE IF NOT EXISTS teams (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)        NOT NULL,
    description TEXT                NULL,
    invite_code CHAR(8)             NOT NULL UNIQUE,
    owner_id    INT UNSIGNED        NOT NULL,
    color       VARCHAR(7)          NOT NULL DEFAULT '#6366f1',
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_invite_code (invite_code),
    INDEX idx_owner (owner_id)
) ENGINE=InnoDB;

-- Table: team_members

CREATE TABLE IF NOT EXISTS team_members (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    team_id     INT UNSIGNED        NOT NULL,
    user_id     INT UNSIGNED        NOT NULL,
    role        ENUM('member','moderator','owner') NOT NULL DEFAULT 'member',
    joined_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_team_user (team_id, user_id),
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)  ON DELETE CASCADE,
    INDEX idx_team (team_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- Table: tasks

CREATE TABLE IF NOT EXISTS tasks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(200)        NOT NULL,
    description TEXT                NULL,
    status      ENUM('todo','in_progress','review','done') NOT NULL DEFAULT 'todo',
    priority    ENUM('low','medium','high','urgent')       NOT NULL DEFAULT 'medium',
    due_date    DATE                NULL,
    team_id     INT UNSIGNED        NOT NULL,
    creator_id  INT UNSIGNED        NOT NULL,
    assignee_id INT UNSIGNED        NULL,
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (team_id)     REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_team   (team_id),
    INDEX idx_creator(creator_id),
    INDEX idx_status (status),
    INDEX idx_due    (due_date)
) ENGINE=InnoDB;

-- Table: task_comments  (bonus feature)

CREATE TABLE IF NOT EXISTS task_comments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id     INT UNSIGNED        NOT NULL,
    user_id     INT UNSIGNED        NOT NULL,
    body        TEXT                NOT NULL,
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_task (task_id)
) ENGINE=InnoDB;

-- Table: notifications

CREATE TABLE IF NOT EXISTS notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED        NOT NULL,
    actor_id    INT UNSIGNED        NULL,
    team_id     INT UNSIGNED        NULL,
    task_id     INT UNSIGNED        NULL,
    type        VARCHAR(50)         NOT NULL,
    title       VARCHAR(160)        NOT NULL,
    body        VARCHAR(255)        NOT NULL,
    link_url    VARCHAR(255)        NULL,
    is_read     TINYINT(1)          NOT NULL DEFAULT 0,
    read_at     DATETIME            NULL,
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (team_id)  REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id)  REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_user_read_created (user_id, is_read, created_at),
    INDEX idx_team (team_id),
    INDEX idx_task (task_id)
) ENGINE=InnoDB;

ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS actor_id INT UNSIGNED NULL AFTER user_id,
    ADD COLUMN IF NOT EXISTS team_id INT UNSIGNED NULL AFTER actor_id,
    ADD COLUMN IF NOT EXISTS task_id INT UNSIGNED NULL AFTER team_id,
    ADD COLUMN IF NOT EXISTS link_url VARCHAR(255) NULL AFTER body,
    ADD COLUMN IF NOT EXISTS read_at DATETIME NULL AFTER is_read;

UPDATE notifications
SET link_url = link
WHERE link_url IS NULL AND link IS NOT NULL;

-- --------------------------------------------------------
-- Table: external_resources
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS external_resources (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED        NOT NULL,
    topic        VARCHAR(120)        NOT NULL,
    title        VARCHAR(200)        NOT NULL,
    summary_text TEXT                NULL,
    source_url   VARCHAR(500)        NOT NULL,
    api_source   VARCHAR(80)         NOT NULL DEFAULT 'Wikipedia',
    created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB;

-- Seed: demo data
-- Password for all demo users is: Password1!
INSERT IGNORE INTO users (name, email, password, avatar, bio) VALUES
('Alice Chen',   'alice@demo.com',   '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewWBZuLHOyBJLO7y', '👩', 'Full-stack dev & team lead'),
('Bob Smith',    'bob@demo.com',     '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewWBZuLHOyBJLO7y', '👨', 'Backend specialist'),
('Carol White',  'carol@demo.com',   '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewWBZuLHOyBJLO7y', '🧑', 'UI/UX designer');
