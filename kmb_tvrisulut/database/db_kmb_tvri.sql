CREATE DATABASE IF NOT EXISTS db_kmb_tvri;
USE db_kmb_tvri;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS kalender_event (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_event VARCHAR(150) NOT NULL,
    tanggal DATE NOT NULL,
    jenis_event ENUM('Libur Nasional', 'Perayaan Spesial') DEFAULT 'Perayaan Spesial',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_kalender_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS rencana_konten (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul_konten VARCHAR(200) NOT NULL,
    tanggal_rencana DATE NOT NULL,
    status ENUM('Ide', 'Proses', 'Siap Tayang', 'Selesai') DEFAULT 'Ide',
    catatan TEXT,
    pic VARCHAR(100),
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rencana_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS ig_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id VARCHAR(100) NOT NULL UNIQUE,
    media_url TEXT NULL,
    thumbnail_url TEXT NULL,
    media_type VARCHAR(30) NOT NULL DEFAULT 'IMAGE',
    caption TEXT NULL,
    likes INT NOT NULL DEFAULT 0,
    comments INT NOT NULL DEFAULT 0,
    posted_at DATETIME NULL,
    permalink TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS app_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO users (username, password, nama_lengkap)
SELECT 'admin', 'admin123', 'Admin KMB TVRI'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE username = 'admin'
);
