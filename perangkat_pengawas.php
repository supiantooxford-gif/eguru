<?php
/**
 * HALAMAN PENGAWAS "PERANGKAT GURU" (`perangkat_pengawas.php`) — v1.72.0 / skema v52.
 *
 * Permintaan pemilik: *"Pada akun pengawas tambahkan menu Perangkat Guru (Perangkat guru ditampilkan
 * per lembaga yang di dalamnya ada daftar guru + mata pelajaran dan daftar perangkatnya). Pengawas
 * bisa melihat, memberikan catatan setiap perangkat dan mendownload"*.
 *
 * Pilih lembaga dulu (halaman PEMILIH BINAAN), lalu tampil daftar guru → mata pelajaran → perangkat.
 * Catatan pengawas TERPISAH dari status tinjauan kepala sekolah dan dapat dibaca guru & kepala
 * sekolah/madrasah.
 *
 * Halaman ini hanya berkas tipis: seluruh logika ada di modul `inc/perangkat.php`
 * (`perangkat_pengawas_halaman()`). **Jangan menyalin logikanya ke halaman lain.**
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

perangkat_pengawas_halaman();
