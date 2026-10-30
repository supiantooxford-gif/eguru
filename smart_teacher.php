<?php
/**
 * SMART TEACHER — daftar aplikasi Smart Teacher (nama aplikasi, link, keterangan).
 *
 * Permintaan pemilik: "Tambahkan Menu Smart Teacher dibawah Menu Catatan Pelanggaran Siswa.
 * Isi menu adalah nama aplikasi, link aplikasi dan keterangan aplikasi yang di isi oleh super admin."
 * Pilihan pemilik: menu terlihat oleh SEMUA pengguna (guru, kepala, operator, Super Admin),
 * daftarnya SATU untuk semua sekolah/madrasah (global), dan tampilannya berupa KARTU berisi nama,
 * keterangan, serta tombol "Buka Aplikasi".
 *
 * Seluruh perilaku (validasi tautan, pengurutan, sembunyikan, hapus, render kartu) ada pada
 * MODUL BERSAMA `inc/daftar_apps.php` karena bentuk menu ini juga dipakai "Hasil Supervisi" —
 * jangan menyalin logikanya ke sini.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/daftar_apps.php';

daftar_apps_halaman('smart_teacher');
