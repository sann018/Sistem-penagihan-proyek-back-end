-- ============================================
-- SCRIPT SQL UNTUK ROLLBACK RESTRUCTURE DATABASE
-- Database: monitoring_penagihan
-- Tanggal: 2026-01-01
-- ============================================

-- PENTING: Gunakan script ini jika ingin mengembalikan perubahan!

USE monitoring_penagihan;

-- Nonaktifkan foreign key checks sementara
SET FOREIGN_KEY_CHECKS=0;

-- ============================================
-- 1. TABEL DATA_PROYEK - Kembalikan ke PENAGIHAN
-- ============================================

-- Step 1: Drop primary key pid
ALTER TABLE `data_proyek` DROP PRIMARY KEY;

-- Step 2: Tambahkan kolom id kembali sebagai primary key
ALTER TABLE `data_proyek` 
ADD `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST;

-- Step 3: Rename tabel kembali ke penagihan
RENAME TABLE `data_proyek` TO `penagihan`;

-- Step 4: Set pid kembali ke unique (bukan primary key)
ALTER TABLE `penagihan` 
MODIFY `pid` VARCHAR(255) NOT NULL UNIQUE;

-- ============================================
-- 2. TABEL PENGGUNA - Kembalikan id_pengguna ke id
-- ============================================
ALTER TABLE `pengguna` 
CHANGE `id_pengguna` `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- ============================================
-- 3. TABEL AKTIVITAS_SISTEM - Kembalikan id_aktivitas ke id
-- ============================================
ALTER TABLE `aktivitas_sistem` 
CHANGE `id_aktivitas` `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- ============================================
-- 4. TABEL TOKEN_AKSES_PRIBADI - Kembalikan id_token ke id
-- ============================================
ALTER TABLE `token_akses_pribadi` 
CHANGE `id_token` `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- Aktifkan kembali foreign key checks
SET FOREIGN_KEY_CHECKS=1;

-- ============================================
-- SELESAI - ROLLBACK BERHASIL
-- ============================================

-- Verifikasi struktur tabel yang telah dikembalikan:
SHOW COLUMNS FROM `pengguna`;
SHOW COLUMNS FROM `penagihan`;
SHOW COLUMNS FROM `aktivitas_sistem`;
SHOW COLUMNS FROM `token_akses_pribadi`;

SELECT 'Rollback selesai! Database kembali ke struktur awal.' AS Status;
