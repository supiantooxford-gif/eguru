<?php
/**
 * HASIL SUPERVISI — daftar aplikasi Hasil Supervisi (nama aplikasi, link, keterangan).
 *
 * Permintaan pemilik: "Dibawah Menu Smart Teacher tambahkan Menu Hasil Supervisi.
 * Isi menu adalah nama aplikasi, link aplikasi dan keterangan aplikasi yang di isi oleh super admin."
 * Perlakuan sama dengan menu Smart Teacher (pilihan pemilik): terlihat SEMUA pengguna,
 * daftarnya SATU untuk semua sekolah/madrasah (global), tampilannya KARTU dengan tombol
 * "Buka Aplikasi", dan isinya diisi Super Admin.
 *
 * Seluruh perilaku ada pada MODUL BERSAMA `inc/daftar_apps.php` — jangan menyalin logikanya ke sini.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/daftar_apps.php';

daftar_apps_halaman('hasil_supervisi');
