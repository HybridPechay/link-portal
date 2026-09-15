-- Link Portal database schema
-- Import this into an empty MySQL/MariaDB database before running install.php

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_admin      TINYINT(1)   NOT NULL DEFAULT 0,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A "folder" is any node in the hierarchy: a program (BSBA), an area inside it,
-- an area inside that, and so on. Nesting depth is unlimited.
CREATE TABLE IF NOT EXISTS folders (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    parent_id  INT NULL,
    name       VARCHAR(150) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_folders_parent FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE,
    INDEX idx_folders_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A link always lives inside a folder (a program or an area).
CREATE TABLE IF NOT EXISTS links (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    folder_id  INT NOT NULL,
    title      VARCHAR(200) NOT NULL,
    url        VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_links_folder FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE,
    INDEX idx_links_folder (folder_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Grants: a user can be granted a whole folder (which implies everything
-- nested inside it) or a single link on its own.
CREATE TABLE IF NOT EXISTS permissions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    target_type ENUM('folder','link') NOT NULL,
    target_id   INT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_perm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_perm (user_id, target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login throttling so brute-force password guessing gets locked out.
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50) NOT NULL,
    ip_address   VARCHAR(45) NOT NULL,
    success      TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempts_lookup (username, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
