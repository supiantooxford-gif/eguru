<?php
/**
 * TANDA TANGAN RAPAT pada akun guru (`ttd_rapat.php`) — e-GURU v1.101.0.
 *
 * Permintaan pemilik: *"Pada saat operator memilih guru hadir pada kegiatan rapat. Buat guru bisa
 * menandatangani lewat akun guru masing-masing. Pada akun guru muncul tanda tangani rapat. Yang
 * muncul hanya yang dicentang oleh operator."*
 *
 * Halaman ini hanya berkas tipis: seluruh logika (daftar rapat hasil centang operator, rincian, dan
 * panel tanda tangan) ada di modul `inc/ttd_rapat.php`. **Jangan menyalin logikanya ke halaman lain.**
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

ttd_rapat_halaman();
