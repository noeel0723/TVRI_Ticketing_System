-- ================================================
-- DATABASE TICKETING SYSTEM TVRI
-- ================================================
-- Dibuat untuk: Sistem Ticketing dengan 3 Role
-- Role: Admin, User (Pelapor), Teknisi
-- Divisi Teknisi: Teknik Kelistrikan, Teknik IT, Studio (Audio Video)
-- ================================================

-- Buat Database
CREATE DATABASE IF NOT EXISTS db_ticketing;
USE db_ticketing;

-- ================================================
-- TABEL 1: divisions (Divisi Teknisi)
-- ================================================
-- Tabel ini menyimpan divisi untuk teknisi
-- User & Admin tidak punya divisi (NULL)
-- ================================================

CREATE TABLE divisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_divisi VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert data divisi
INSERT INTO divisions (nama_divisi) VALUES
('Teknik IT'),
('Teknik Kelistrikan'),
('Studio (Audio Video)');

-- ================================================
-- TABEL 2: users (Semua Pengguna Sistem)
-- ================================================
-- Tabel ini menyimpan SEMUA user: Admin, User, Teknisi
-- division_id: NULL untuk Admin & User, WAJIB untuk Teknisi
-- ================================================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user', 'teknisi') NOT NULL,
    division_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert data user default
-- Password semua: 123456 (sudah di-hash dengan password_hash)
-- Hash untuk "123456": $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

INSERT INTO users (nama, username, password, role, division_id) VALUES
-- Admin (division_id NULL)
('Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL),

-- User/Pelapor (division_id NULL)
('Budi Santoso', 'budi', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', NULL),
('Siti Aminah', 'siti', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', NULL),

-- Teknisi IT (division_id = 1)
('Ahmad Hidayat', 'ahmad_it', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teknisi', 1),

-- Teknisi Kelistrikan (division_id = 2)
('Joko Widodo', 'joko_listrik', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teknisi', 2),

-- Teknisi Studio (division_id = 3)
('Rina Wati', 'rina_studio', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teknisi', 3);

-- ================================================
-- TABEL 3: tickets (Tiket Laporan)
-- ================================================
-- Tabel INTI sistem ticketing
-- Status: Open, Assigned, In Progress, Resolved
-- Priority: Low, Medium, High, Urgent
-- ================================================

CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(200) NOT NULL,
    lokasi VARCHAR(150) NOT NULL,
    deskripsi TEXT NOT NULL,
    user_id INT NOT NULL,
    assigned_division_id INT DEFAULT NULL,
    status ENUM('Open', 'Assigned', 'In Progress', 'Resolved') DEFAULT 'Open',
    priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
    catatan_teknisi TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_division_id) REFERENCES divisions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert data tiket contoh (opsional, untuk testing)
INSERT INTO tickets (judul, lokasi, deskripsi, user_id, assigned_division_id, status, priority) VALUES
('Komputer Tidak Bisa Booting', 'Ruang Admin Lantai 2', 'Komputer di ruang admin tidak bisa menyala sama sekali, sudah dicoba restart tetap tidak bisa', 2, 1, 'Assigned', 'High'),
('Lampu Studio Mati', 'Studio 1', 'Lampu di studio 1 tiba-tiba mati saat siaran', 3, 2, 'In Progress', 'Urgent'),
('Mic Tidak Bunyi', 'Studio 2', 'Microphone di studio 2 tidak mengeluarkan suara', 2, 3, 'Open', 'Medium');

-- ================================================
-- INFO AKUN DEFAULT
-- ================================================
-- Username: admin       | Password: 123456 | Role: admin
-- Username: budi        | Password: 123456 | Role: user
-- Username: siti        | Password: 123456 | Role: user
-- Username: ahmad_it    | Password: 123456 | Role: teknisi (IT)
-- Username: joko_listrik| Password: 123456 | Role: teknisi (Kelistrikan)
-- Username: rina_studio | Password: 123456 | Role: teknisi (Studio)
-- ================================================

-- Selesai!
-- Silakan import file ini ke phpMyAdmin atau jalankan via terminal:
-- mysql -u root -p < db_ticketing.sql
