<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'db_kmb_tvri';

try {
    $conn = new mysqli($host, $user, $pass);
    $conn->set_charset('utf8mb4');

    $conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db($dbname);

    $queries = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            nama_lengkap VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS kalender_event (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama_event VARCHAR(150) NOT NULL,
            tanggal DATE NOT NULL,
            jenis_event ENUM('Libur Nasional', 'Perayaan Spesial') DEFAULT 'Perayaan Spesial',
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_kalender_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS rencana_konten (
            id INT AUTO_INCREMENT PRIMARY KEY,
            judul_konten VARCHAR(200) NOT NULL,
            tanggal_rencana DATE NOT NULL,
            status ENUM('Ide', 'Proses', 'Siap Tayang', 'Selesai') DEFAULT 'Ide',
            catatan TEXT,
            pic VARCHAR(100),
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_rencana_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS app_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        "INSERT INTO users (username, password, nama_lengkap)
         SELECT 'admin', 'admin123', 'Admin KMB TVRI'
         WHERE NOT EXISTS (
             SELECT 1 FROM users WHERE username = 'admin'
         )"
    ];

    foreach ($queries as $sql) {
        $conn->query($sql);
    }
} catch (mysqli_sql_exception $e) {
    die('Koneksi database gagal: ' . $e->getMessage());
}
