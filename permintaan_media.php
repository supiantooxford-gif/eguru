<?php
/**
 * PERMINTAAN MEDIA — `permintaan_media.php` (v1.111.0 / skema v69).
 *
 * Permintaan pemilik: *"Jadi di halaman super admin tambahkan Menu Permintaan Media dengan isi daftar
 * Guru + Judul media yang diminta dan tambahkan fitur aksi (Sedang ditinjau, media telah dibuat).
 * Status sedang ditinjau atau media telah dibuat akan muncul juga di akun guru yang meminta"*.
 *
 * Berkas ini SENGAJA tipis: seluruh aturan (status, penyimpanan, daftar, penyaring, dan aksi ubah
 * status) ada pada modul `inc/media_permintaan.php` — jangan menyalin logikanya ke sini.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/media_permintaan.php';

media_permintaan_halaman();
