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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert data divisi
INSERT INTO divisions (id, nama_divisi) VALUES
(1, 'Teknik IT'),
(2, 'Teknik Kelistrikan'),
(3, 'Studio (Audio Video)');

-- ================================================
-- TABEL 2: users (Semua Pengguna Sistem)
-- ================================================
-- Tabel ini menyimpan SEMUA user: Admin, User, Teknisi
-- division_id: NULL untuk Admin & User, WAJIB untuk Teknisi
-- email & foto: opsional (bisa NULL)
-- ================================================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user', 'teknisi') NOT NULL,
    division_id INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    foto VARCHAR(255) DEFAULT NULL,
    KEY division_id (division_id),
    CONSTRAINT users_ibfk_1 FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert data user aktual
-- CATATAN: Password di-hash menggunakan bcrypt (password_hash PHP).
-- Hash di bawah adalah untuk password "123456".
-- Setelah import, ubah password masing-masing user via fitur Change Password.
-- Hash "123456": $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi

INSERT INTO users (id, nama, username, email, password, role, division_id) VALUES
-- Admin (division_id NULL)
(1, 'Administrator',    'admin',      NULL,                   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',   NULL),

-- Teknisi IT (division_id = 1)
(2, 'Loudres Batjo',    'loudres_it', NULL,                   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teknisi', 1),

-- User/Pelapor (division_id NULL)
(3, 'Jeremia Lelemboto','lelemboto',  'noeljr073@gmail.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user',    NULL),

-- Teknisi Kelistrikan (division_id = 2)
(4, 'Varel',            'varel',      NULL,                   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teknisi', 2),

-- Teknisi Studio (division_id = 3)
(5, 'Nova Rayana',      'raya',       'hahahihi@gmail.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teknisi', 3),

-- Teknisi Kelistrikan (division_id = 2)
(7, 'Jonathan Rahasia', 'joxra',      NULL,                   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teknisi', 2);

-- ================================================
-- TABEL 3: tickets (Tiket Laporan)
-- ================================================
-- Tabel INTI sistem ticketing
-- Status: Open, Assigned, In Progress, Resolved
-- Priority: Low, Medium, High, Urgent
-- attachment     : file lampiran saat pelaporan
-- foto_perbaikan : foto hasil perbaikan oleh teknisi
-- handled_by     : user_id teknisi yang menangani
-- ================================================

CREATE TABLE tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(200) NOT NULL,
    lokasi VARCHAR(150) NOT NULL,
    deskripsi TEXT NOT NULL,
    attachment VARCHAR(255) DEFAULT NULL,
    foto_perbaikan VARCHAR(255) DEFAULT NULL,
    user_id INT NOT NULL,
    assigned_division_id INT DEFAULT NULL,
    handled_by INT DEFAULT NULL,
    status ENUM('Open', 'Assigned', 'In Progress', 'Resolved') DEFAULT 'Open',
    priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
    catatan_teknisi TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY user_id (user_id),
    KEY assigned_division_id (assigned_division_id),
    KEY handled_by (handled_by),
    CONSTRAINT tickets_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT tickets_ibfk_2 FOREIGN KEY (assigned_division_id) REFERENCES divisions(id) ON DELETE SET NULL,
    CONSTRAINT tickets_ibfk_3 FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=79 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Contoh tiket awal (opsional, hapus jika tidak diperlukan)
INSERT INTO tickets (judul, lokasi, deskripsi, user_id, assigned_division_id, status, priority) VALUES
('Komputer Tidak Bisa Booting', 'Ruang Admin Lantai 2', 'Komputer di ruang admin tidak bisa menyala sama sekali, sudah dicoba restart tetap tidak bisa', 3, 1, 'Assigned', 'High'),
('Lampu Studio Mati',          'Studio 1',              'Lampu di studio 1 tiba-tiba mati saat siaran',                                                  3, 2, 'In Progress', 'Urgent'),
('Mic Tidak Bunyi',            'Studio 2',              'Microphone di studio 2 tidak mengeluarkan suara',                                               3, 3, 'Open',        'Medium');

-- ================================================
-- TABEL 4: ticket_logs (Log Aktivitas Tiket)
-- ================================================
-- Mencatat setiap perubahan/aksi pada tiket
-- ticket_id      : id tiket (NULL jika tiket sudah dihapus)
-- ticket_judul   : judul tiket saat log dibuat (backup)
-- ticket_id_orig : id tiket asli sebelum dihapus
-- action         : jenis aksi (create, assign, update_status, resolve, dll)
-- old_value      : nilai sebelum perubahan
-- new_value      : nilai setelah perubahan
-- ================================================

CREATE TABLE ticket_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT DEFAULT NULL,
    ticket_judul VARCHAR(255) DEFAULT NULL,
    ticket_id_orig INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    action VARCHAR(50) NOT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY user_id (user_id),
    KEY idx_ticket_logs_ticket_id (ticket_id),
    KEY idx_ticket_logs_created_at (created_at),
    CONSTRAINT ticket_logs_ibfk_1 FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    CONSTRAINT ticket_logs_ibfk_2 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

