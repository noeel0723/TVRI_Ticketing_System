-- ================================================
-- MIGRATION: Tambah kolom email ke tabel users
-- ================================================
USE db_ticketing;
ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(150) DEFAULT NULL AFTER username;