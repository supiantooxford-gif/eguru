<?php
/**
 * DOKUMEN SAYA — dokumen yang dikirim menu Kurikulum kepada guru sekolah ini.
 *
 * Permintaan pemilik: "Dokumen yang dikirim akan masuk di akun guru pada sekolah tersebut." →
 * halaman ini adalah "kotak masuk" dokumen bagi guru (disebut juga dari kartu di dashboard guru).
 *
 * Seluruh logika data & tampilannya ada pada MODUL BERSAMA `inc/dokumen.php` — jangan menyalinnya
 * ke sini.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/dokumen.php';

dokumen_halaman_guru();
