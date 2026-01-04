-- ============================================
-- SCRIPT SQL UNTUK RESTRUCTURE DATABASE
-- Database: monitoring_penagihan
-- Tanggal: 2026-01-01
-- ============================================

-- PENTING: Backup database Anda sebelum menjalankan script ini!

USE monitoring_penagihan;

-- Nonaktifkan foreign key checks sementara
SET FOREIGN_KEY_CHECKS=0;

-- ============================================
-- 1. TABEL PENGGUNA - Rename id menjadi id_pengguna
-- ============================================
ALTER TABLE `pengguna` 
CHANGE `id` `id_pengguna` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- ============================================
-- 2. TABEL AKTIVITAS_SISTEM - Rename id menjadi id_aktivitas
-- ============================================
ALTER TABLE `aktivitas_sistem` 
CHANGE `id` `id_aktivitas` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- ============================================
-- 3. TABEL TOKEN_AKSES_PRIBADI - Rename id menjadi id_token
-- ============================================
ALTER TABLE `token_akses_pribadi` 
CHANGE `id` `id_token` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;

-- ============================================
-- 4. TABEL PENAGIHAN - Restructure ke DATA_PROYEK
-- ============================================

-- Step 1: Hapus auto_increment dari kolom id terlebih dahulu
ALTER TABLE `penagihan` 
MODIFY `id` BIGINT UNSIGNED NOT NULL;

-- Step 2: Drop primary key yang ada
ALTER TABLE `penagihan` DROP PRIMARY KEY;

-- Step 3: Hapus kolom id
ALTER TABLE `penagihan` DROP COLUMN `id`;

-- Step 4: Rename tabel
RENAME TABLE `penagihan` TO `data_proyek`;

-- Step 5: Set pid sebagai primary key (pid sudah unique)
ALTER TABLE `data_proyek` 
MODIFY `pid` VARCHAR(255) NOT NULL,
ADD PRIMARY KEY (`pid`);

-- ============================================
-- 5. UPDATE TABEL LAIN YANG MUNGKIN ADA
-- ============================================
-- Jika ada tabel sesi, tembolok, dll yang perlu diupdate
-- Tambahkan script di sini jika diperlukan

-- Contoh untuk tabel sesi (jika ada foreign key ke pengguna)
-- ALTER TABLE `sesi` 
-- DROP FOREIGN KEY IF EXISTS `sesi_pengguna_id_foreign`;
-- ALTER TABLE `sesi` 
-- ADD CONSTRAINT `sesi_pengguna_id_foreign` 
-- FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna`(`id_pengguna`) 
-- ON DELETE CASCADE ON UPDATE CASCADE;

-- Aktifkan kembali foreign key checks
SET FOREIGN_KEY_CHECKS=1;

-- ============================================
-- SELESAI
-- ============================================

-- Verifikasi struktur tabel yang telah diubah:
SHOW COLUMNS FROM `pengguna`;
SHOW COLUMNS FROM `data_proyek`;
SHOW COLUMNS FROM `aktivitas_sistem`;
SHOW COLUMNS FROM `token_akses_pribadi`;

SELECT 'Migration selesai! Silakan verifikasi struktur tabel di atas.' AS Status;
