<?php
/**
 * Menu **SUPERVISI GURU BK** pada akun PENGAWAS SEKOLAH/MADRASAH.
 *
 * Permintaan pemilik: *"pada halaman pengawas tambahkan menu Supervisi Guru BK ( didalamnya akan
 * menampilkan daftar madrasah yang ada guru BKnya untuk di supervisi) … Hasil supervisi otomatis
 * akan masuk ke Guru yang ditugaskan Bimbingan Konseling dan ada rekapannya di akun pengawas."*
 *
 * Halaman ini hanya berkas tipis — SELURUH logikanya ada di `inc/bk.php`
 * (`bk_pengawas_halaman()`), supaya daftar binaan, instrumen, penyimpanan, dan rekap memakai satu
 * sumber aturan yang sama dengan halaman menu Bimbingan Konseling pada akun guru.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/bk.php';

bk_pengawas_halaman();
