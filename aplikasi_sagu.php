<?php
/**
 * APLIKASI SAGU — daftar aplikasi (nama aplikasi, link, keterangan).
 *
 * Permintaan pemilik: "Tambahkan Menu Aplikasi Sagu dibawah menu Smart Teacher.
 * Cara pengisian link aplikasi seperti Smart Teacher" → jadi menu ini berperilaku SAMA PERSIS dengan
 * menu Smart Teacher: daftar GLOBAL (satu untuk semua sekolah/madrasah), isinya diisi Super Admin,
 * tampilannya kartu dengan tombol "Buka Aplikasi", dan tautannya hanya menerima http/https.
 *
 * Seluruh perilaku ada pada MODUL BERSAMA `inc/daftar_apps.php` — jangan menyalin logikanya ke sini.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/daftar_apps.php';

daftar_apps_halaman('aplikasi_sagu');
