<?php
/**
 * VIDEO TUTORIAL — `video_tutorial.php` (v1.96.0 / skema v64).
 *
 * Permintaan pemilik: *"Dibawah menu dashboard akun guru, operator, kepala sekolah tambahkan menu
 * Video Tutorial. Isi dari menu ini adalah kumpulan video yang diupload oleh super admin. Video
 * berupa thumbail youtube yang bisa dibuka oleh guru, operator dan kepala sekolah. Admin mengisi
 * menu tersebut dengan memberikan judul video dan link youtube"*
 *
 * Berkas ini SENGAJA tipis: seluruh aturan (pembacaan ID video YouTube, penyimpanan, urutan, hak
 * akses, dan render kartu thumbnail) ada pada modul `inc/video_tutorial.php` — jangan menyalin
 * logikanya ke sini.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/video_tutorial.php';

video_tutorial_halaman();
