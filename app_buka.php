<?php
/**
 * ENDPOINT "BUKA APLIKASI" — mencatat penggunaan aplikasi lalu mengalihkan ke tautannya.
 *
 * Permintaan pemilik (v1.79.0): *"Pada halaman smart teacher tambahkan Grafik Aplikasi yang Sering
 * Digunakan. Data grafik diambil dari data seluruh pengguna yang menggunakan … Tujuannya agar
 * pengguna tau aplikasi mana yang sering digunakan."* Pilihan pemilik: yang dihitung = **klik
 * tombol "Buka Aplikasi"**, dan rentang datanya = **seluruh data yang tercatat** (tanpa batas waktu).
 *
 * Karena itu tombol "Buka Aplikasi" pada SEMUA menu daftar aplikasi (Smart Teacher, Aplikasi Sagu,
 * Ujian Online, Hasil Supervisi, Bendahara Bos/Madrasah) diarahkan ke berkas ini:
 *
 *      app_buka.php?m=<kunci menu>&id=<id aplikasi>[&b=<halaman asal>]
 *
 * Alurnya: periksa hak akses → catat satu baris `app_klik` (nama aplikasi + asal pengguna sebagai
 * snapshot) → alihkan (302) ke tautan aplikasi yang sebenarnya.
 *
 * PENTING (jangan diubah tanpa membaca ini):
 *  - pencatatan dilakukan di SERVER, bukan lewat JavaScript → tetap bekerja tanpa skrip dan tidak
 *    bisa dilewati dari tampilan;
 *  - tautan tujuan WAJIB lewat `tautan_aman()` (hanya http/https) → tidak bisa dipakai sebagai
 *    pengalihan terbuka (open redirect);
 *  - parameter `b` (halaman asal) hanya dipakai untuk KEMBALI saat aplikasi tidak ditemukan dan
 *    hanya diterima bila bentuknya benar-benar berkas aplikasi ini (`namaberkas.php` atau
 *    `tugas.php?kode=…`), selain itu jatuh ke `index.php`;
 *  - kegagalan mencatat TIDAK BOLEH menghalangi pengguna membuka aplikasinya (lihat
 *    `app_klik_catat()`).
 *  - **MENU YANG DINONAKTIFKAN SUPER ADMIN (v1.81.0)** — bila `daftar_apps_aktif($menu)` bernilai
 *    false (sekarang hanya menu **Smart Teacher**), tombol "Buka Aplikasi" TIDAK membuka tautannya:
 *    pengguna dikembalikan ke halaman menunya yang menampilkan peringatan gangguan. Super Admin
 *    tetap boleh (dialah yang mengatur keadaan menu itu).
 *
 * GET saja (tidak ada POST, tidak ada CSRF): berkas ini juga terdaftar di `pengawas_berkas_boleh()`
 * supaya akun pengawas (hanya-lihat) tetap dapat membuka aplikasi dari daftar aplikasi.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/daftar_apps.php';

/** Halaman asal yang aman — hanya berkas aplikasi ini sendiri (mencegah pengalihan terbuka). */
function app_buka_kembali(string $b): string
{
    $b = trim($b);
    // Sejak v1.105.0 query boleh memuat parameter halaman sendiri (mis. `?jenjang=sd-mi` pada menu
    // bertab "Media Interaktif", atau `?kode=…` pada menu Tugas Tambahan). Batasannya tetap ketat:
    // hanya nama berkas .php di folder aplikasi ini + query huruf/angka/`=`/`-`/`&`, sehingga TIDAK
    // bisa dipakai sebagai pengalihan terbuka (tidak ada ":" atau "//").
    if (preg_match('~^[a-z0-9_]+\.php(\?[a-z0-9_%=\-&]+)?$~', $b) === 1) {
        return $b;
    }
    return 'index.php';
}

$u = require_login();          // sekaligus gerbang aktivasi/pengawas/sandi
$menu = strtolower(trim((string) ($_GET['m'] ?? '')));
$appId = (int) ($_GET['id'] ?? 0);
$kembali = app_buka_kembali((string) ($_GET['b'] ?? ''));

$k = daftar_apps_konfig($menu);
if ($k === null) {
    flash('Menu daftar aplikasi tidak dikenal.', 'err');
    redirect($kembali);
}

$namaMenu = (string) ($k['menu'] ?? 'Daftar Aplikasi');

// MENU KHUSUS AKUN GURU (v1.105.0 — "Media Interaktif"): peran lain tidak boleh membuka tautannya
// lewat alamat langsung. Aturan satu tempat: daftar_apps_peran_boleh() (Super Admin selalu boleh).
if (!daftar_apps_peran_boleh($menu)) {
    tolak403(daftar_apps_peran_pesan());
}

// Menu yang dibatasi PER SEKOLAH: sekolah di luar daftar benar-benar ditolak (aturan yang sama
// dengan halaman menunya — lihat daftar_apps_halaman()).
if (!daftar_apps_tampil($menu) && !daftar_apps_per_aplikasi($menu)) {
    tolak403('Menu ' . $namaMenu . ' tidak tersedia untuk sekolah/madrasah ini.');
}

// Menu yang hanya terbuka bagi lembaga yang sudah melakukan aktivasi (v1.78.0).
if (!daftar_apps_aktivasi_boleh($menu)) {
    tolak403(daftar_apps_aktivasi_pesan());
}

// Menu yang sedang DINONAKTIFKAN Super Admin (v1.81.0): aplikasi di dalamnya tidak boleh dibuka
// lewat alamat langsung. Pengguna dikembalikan ke halaman menunya yang menampilkan peringatan
// "Server AI … lagi mengalami gangguan" — bukan 403, supaya pesannya terbaca. Super Admin tetap
// boleh (dialah yang menonaktifkan & harus bisa menguji tautannya).
if (!daftar_apps_aktif($menu) && !is_super()) {
    $halamanMenu = (string) ($k['berkas'] ?? '');
    redirect($halamanMenu !== '' ? $halamanMenu : 'index.php');
}

$app = daftar_apps_by_id($menu, $appId);
if (!$app || (int) ($app['aktif'] ?? 0) !== 1) {
    flash('Aplikasi tidak ditemukan atau sudah disembunyikan dari daftar.', 'err');
    redirect($kembali);
}

// AKSES PER APLIKASI (Bendahara Bos/Madrasah): sekolah di luar daftar tidak boleh membuka tautannya.
if (daftar_apps_per_aplikasi($menu) && !is_super()) {
    $s = current_sekolah();
    $sid = $s ? (int) $s['id'] : 0;
    if (!daftar_apps_app_boleh($menu, $appId, $sid)) {
        tolak403('Madrasah Anda belum punya akses ke aplikasi keuangan.');
    }
}

$link = tautan_aman((string) ($app['link'] ?? ''));
if ($link === '') {
    flash('Tautan aplikasi ' . (string) $app['nama'] . ' belum diisi Super Admin.', 'err');
    redirect($kembali);
}

// CATAT PENGGUNAAN — inti GRAFIK "Aplikasi yang Sering Digunakan".
app_klik_catat($menu, $app);

// Alihkan ke aplikasinya (tautan sudah dipastikan http/https lewat tautan_aman()).
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Referrer-Policy: no-referrer');
header('Location: ' . $link, true, 302);
exit;
