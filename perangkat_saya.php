<?php
/**
 * HALAMAN GURU "KIRIM PERANGKAT" (`perangkat_saya.php`) — v1.67.0 / skema v48.
 *
 * Permintaan pemilik: *"Sekarang buatkan fitur Kirim Perangkat di akun guru (Seperti Kalender
 * Pendidikan, Analisis Pekan Efektif, Capaian Pembelajaran, Alur Tujuan Pembelajaran, RPP/Modul Ajar.
 * Nama-nama dokumen ini di isi oleh Super Admin, bisa ditambah atau dikurangi. File yang terkirim
 * masuk di akun Kepala sekolah (…), dan Pengawas (…). Hasil tinjauan kepala sekolah terkirim ke akun
 * guru"*.
 *
 * Halaman ini hanya berkas tipis: seluruh logika (data, unggah berkas, kelengkapan, dan tampilan
 * hasil tinjauan) ada di modul `inc/perangkat.php`.
 * **Jangan menyalin logikanya ke halaman lain.**
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

perangkat_saya_halaman();
