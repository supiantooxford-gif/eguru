<?php
/**
 * MENU "HASIL PKG" — halaman akun GURU.
 *
 * Permintaan pemilik: *"pada akun Guru tambahkan menu Hasil PKG … hasil penilaiannya masuk ke menu
 * Hasil PKG pada akun guru."* Guru hanya melihat hasil MILIKNYA sendiri (hanya lihat & cetak).
 *
 * Seluruh perilaku ada di modul `inc/pkg.php` — jangan menyalin logikanya ke sini.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

pkg_hasil_halaman();
