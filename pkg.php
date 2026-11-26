<?php
/**
 * MENU "PKG" (Penilaian Kinerja Guru) — halaman akun KEPALA SEKOLAH/MADRASAH.
 *
 * Permintaan pemilik: *"Pada akun kepala sekolah/madrasah tambahkan menu PKG … digunakan untuk
 * menilai setiap guru pada lembaganya sendiri (Instrumen penilaian seperti yang saya upload) dan
 * hasil penilaiannya masuk ke menu Hasil PKG pada akun guru."*
 *
 * Seluruh perilaku (instrumen 13 butir, perhitungan, penyimpanan, tampilan) ada di modul
 * `inc/pkg.php` — **jangan menyalin logikanya ke halaman lain.**
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

pkg_halaman();
