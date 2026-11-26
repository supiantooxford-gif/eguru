<?php
/**
 * MEDIA INTERAKTIF — kumpulan media pembelajaran & game edukasi untuk AKUN GURU.
 *
 * Permintaan pemilik (v1.105.0 / skema v67): *"pada akun guru tambahkan Menu 'Media Interaktif' tepat
 * dibawah Smart Teacher dan beri penanda Bonus berkedip. Isi dari menu ini adalah Kumpulan Media
 * Interaktif (SD/MI - SMA/MA yang dibuatkan menu sendiri-sendiri untuk memisahkan antar jenjang) dan
 * Game Edukasi."*
 *
 * Pilihan pemilik (jangan diubah tanpa bertanya):
 *   1. isinya SATU halaman dengan **tab per jenjang** (SD/MI · SMP/MTs · SMA/MA) + tab **Game
 *      Edukasi** — bukan sub menu di sidebar;
 *   2. menunya **HANYA untuk akun guru** (kepala sekolah/madrasah & Admin Operator tidak melihatnya);
 *   3. lencana **"Bonus" berkedip SELALU tampil** pada menu sidebar di bawah Smart Teacher.
 *
 * Bentuk daftarnya SAMA dengan menu daftar aplikasi lain (nama aplikasi, link, keterangan — diisi
 * Super Admin), jadi seluruh perilakunya memakai MODUL BERSAMA `inc/daftar_apps.php` dengan kunci
 * menu `media_interaktif`. JANGAN menyalin logikanya ke sini: pemisahan tab ada pada
 * `daftar_apps_kategori()` dkk., dan pembatasan "hanya guru" pada `daftar_apps_peran_boleh()`.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/daftar_apps.php';

daftar_apps_halaman('media_interaktif');
