<?php
/**
 * MENU "HASIL PKG" — halaman akun PENGAWAS SEKOLAH/MADRASAH.
 *
 * Permintaan pemilik: *"Selain itu pengawas juga bisa melihat hasil PKG setiap guru binaannya."*
 * Akun pengawas hanya melihat & mencetak; penilaian dilakukan kepala sekolah/madrasah.
 *
 * Seluruh perilaku ada di modul `inc/pkg.php` — jangan menyalin logikanya ke sini.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

pkg_pengawas_halaman();
