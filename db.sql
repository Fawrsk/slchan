CREATE TABLE IF NOT EXISTS files(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    size BIGINT UNSIGNED NOT NULL,
    extension VARCHAR(10) NOT NULL,
    width BIGINT UNSIGNED NOT NULL,
    height BIGINT UNSIGNED NOT NULL,
    hash VARCHAR(64) NOT NULL,
    created INT(11) UNSIGNED NOT NULL,
    post_id BIGINT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    CONSTRAINT uc_files_hash UNIQUE (hash)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS posts(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50),
    subject VARCHAR(100),
    created INT(11) UNSIGNED NOT NULL,
    last_updated INT(11) UNSIGNED NOT NULL,
    message TEXT(3000) NOT NULL,
    file_id BIGINT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    password VARCHAR(255) NOT NULL,
    parent_id BIGINT UNSIGNED DEFAULT NULL,
    hidden BOOLEAN NOT NULL DEFAULT FALSE,
    INDEX idx_posts_ip_address (ip_address),
    CONSTRAINT fk_posts_files FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE SET NULL,
    CONSTRAINT fk_posts_parents FOREIGN KEY (parent_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bans(
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    reason VARCHAR(150) NOT NULL,
    created INT(11) UNSIGNED NOT NULL,
    expires INT(11) UNSIGNED DEFAULT NULL,
    post_id BIGINT UNSIGNED DEFAULT NULL,
    INDEX idx_bans_ip_address_expires (ip_address, expires),
    CONSTRAINT fk_bans_posts FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE SET NULL
) ENGINE=InnoDB;
