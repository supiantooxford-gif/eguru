<?php
/**
 * HALAMAN SUPER ADMIN "JENIS PERANGKAT" (`perangkat_jenis.php`) — v1.67.0 / skema v48.
 *
 * Permintaan pemilik: *"Seperti Kalender Pendidikan, Analisis Pekan Efektif, Capaian Pembelajaran,
 * Alur Tujuan Pembelajaran, RPP/Modul Ajar. Nama-nama dokumen ini di isi oleh Super Admin, bisa
 * ditambah atau dikurangi."*
 *
 * Daftar ini GLOBAL (berlaku semua sekolah/madrasah, seperti daftar aplikasi). Seluruh logikanya ada
 * di modul `inc/perangkat.php` — halaman ini hanya berkas tipis.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

perangkat_jenis_halaman();
