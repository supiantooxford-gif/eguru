<?php
/**
 * GAMBAR HALAMAN DEPAN (LANDING) — endpoint penerus gambar (v1.91.0).
 *
 * Halaman depan (`login.php`) mencetak alamat `gambar.php?j=…` (BUKAN alamat CDN fotonya), lalu
 * berkas ini mengambil fotonya dari CDN di sisi server dan meneruskannya ke peramban. Dengan begitu:
 *   • alamat sumber foto tidak pernah terlihat pengunjung (klik kanan / View page source);
 *   • alamat gambar tidak bisa dibuka langsung di tab baru atau dipasang di situs lain (403 bila
 *     permintaannya bukan dari halaman aplikasi ini);
 *   • gambar juga tidak bisa diklik kanan / diseret / disimpan lewat menu tekan-lama di HP
 *     (kelas `lp-lindung` + `initLindungGambar()`).
 *
 * Berkas ini TIDAK memakai sesi (lihat `EGURU_TANPA_SESI` di bawah) supaya belasan permintaan gambar
 * pada satu halaman tidak saling mengunci berkas sesi.
 */
declare(strict_types=1);

define('EGURU_TANPA_SESI', true);

require_once __DIR__ . '/inc/gambar.php';

gambar_halaman();
