<?php
/**
 * KIRIM DOKUMEN (akun PENGAWAS Sekolah/Madrasah) — `kirim_dokumen.php`.
 *
 * Permintaan pemilik: *"Pada halaman Pengawas tambahkan Menu Kirim Dokumen (Dokumen yang diunggah
 * oleh pengawas akan terkirim ke semua akun guru madrasah binaannya). Fiturnya seperti pada menu
 * kurikulum."*
 * Pilihan pemilik: (1) tujuan bisa **binaan ini saja** atau **semua binaan**; (2) menunya di blok
 * **Aplikasi** sidebar pengawas (selalu terlihat, tanpa memilih binaan lebih dulu); (3) pengawas
 * hanya dapat menghapus **dokumen yang ia kirim sendiri**.
 *
 * Seluruh logika ada pada MODUL BERSAMA `inc/dokumen.php` (`dokumen_halaman_pengawas()`) —
 * **jangan menyalinnya ke sini**. Berkas ini juga terdaftar pada `pengawas_berkas_boleh()` dan
 * `pengawas_berkas_post()` (`inc/helpers.php`) supaya gerbang pengawas mengizinkannya.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/dokumen.php';

dokumen_halaman_pengawas();
