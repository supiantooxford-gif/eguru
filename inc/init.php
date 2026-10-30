<?php
/**
 * Titik masuk tunggal untuk semua halaman aplikasi e-GURU.
 */
declare(strict_types=1);
// ===================== JALUR AWALAN GANDA (DOMAIN SENDIRI) =====================
// v1.71.3 — laporan pemilik: "Aplikasi ketika dibuka di domain saya sendiri muncul seperti gambar"
// (tampilan HTML polos tanpa gaya). Penyebabnya: alamat yang dibuka masih memakai jalur folder
// aplikasi pada DOMAIN SENDIRI, mis. https://asisten.guru/e-guru/ . Di domain sendiri aplikasi
// memang dilayani pada AKAR domain (https://asisten.guru/), jadi seluruh tautan RELATIF
// (assets/style.css, assets/app.js, halaman .php) menunjuk satu folder terlalu dalam → CSS/JS
// tidak pernah termuat. Bila jalur permintaan memuat awalan slug DUA KALI, pengunjung dialihkan
// satu tingkat ke atas memakai alamat RELATIF ('../…') sehingga benar di KEDUA alamat:
//   asisten.guru/e-guru/                   -> asisten.guru/
//   supanto.vibecoder.co.id/e-guru/e-guru/ -> supanto.vibecoder.co.id/e-guru/
// Hanya GET/HEAD yang dialihkan (POST tidak boleh kehilangan isian). Slug = nama folder aplikasi
// (nama folder = slug publik), jadi tidak ada angka/nama yang ditulis mati di sini.
if (PHP_SAPI !== 'cli' && isset($_SERVER['REQUEST_URI'])) {
    $__slug   = basename(dirname(__DIR__));
    $__jalur  = (string) (parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
    $__metode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $__pola   = '#^/' . preg_quote($__slug, '#') . '/' . preg_quote($__slug, '#') . '(?=/|$)#i';
    if (($__metode === 'GET' || $__metode === 'HEAD') && preg_match($__pola, $__jalur)) {
        $__sisa   = (string) preg_replace($__pola, '', $__jalur);
        $__tujuan = '../' . ltrim($__sisa, '/');
        $__q      = (string) ($_SERVER['QUERY_STRING'] ?? '');
        if ($__q !== '') {
            $__tujuan .= '?' . $__q;
        }
        header('Location: ' . $__tujuan, true, 302);
        exit;
    }
    unset($__slug, $__jalur, $__metode, $__pola, $__sisa, $__tujuan, $__q);
}

require_once __DIR__ . '/config.php';

// ================= KEPALA KEAMANAN (JANGAN DIHAPUS) =================
// Lapisan TAMBAHAN di atas kepala keamanan yang sudah dipasang server (Caddy sudah mengirim
// X-Frame-Options: SAMEORIGIN, X-Content-Type-Options: nosniff, Referrer-Policy). Header di bawah
// TIDAK diduplikasi dengan milik server, dan sengaja TIDAK membatasi script-src/style-src karena
// aplikasi memakai skrip & gaya sebaris (membatasinya akan mematikan halamannya). Yang dikunci:
//   • frame-ancestors 'self' : halaman tidak dapat dibingkai situs lain (anti CLICKJACKING —
//     menyamarkan halaman aplikasi di dalam situs penyerang agar pengguna menekan tombol diam-diam).
//   • object-src 'none'      : plugin/objek (Flash dsb.) dilarang.
//   • base-uri 'self'        : tag <base> tidak dapat disisipkan penyerang untuk membajak
//     seluruh tautan relatif halaman.
//   • form-action 'self'     : formulir hanya boleh dikirim ke aplikasi ini sendiri.
//   • Permissions-Policy     : kamera/mikrofon/lokasi dsb. tidak diperlukan aplikasi → dimatikan.
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header("Content-Security-Policy: frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self'");
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/daftar_apps.php';   // aturan tampil menu daftar aplikasi (dipakai sidebar & pintasan)
// AKUN SISWA (v1.61.0/skema v45): pembacaan data siswa (daftar hadir, nilai, pelanggaran) +
// penjaga halaman siswa. Dimuat di sini supaya halaman layar & lembar cetak memakai fungsi yang sama.
require_once __DIR__ . '/siswa_akun.php';
// CHAT/OBROLAN (v1.51.0) — modulnya dipakai layout (kotak mengambang) DAN endpoint `chat.php`,
// jadi di-require di sini supaya tidak ada halaman yang lupa memuatnya.
require_once __DIR__ . '/chat.php';
// PERANGKAT GURU (v1.67.0/skema v48) — modul "Kirim Perangkat": dipakai sidebar (lencana menu) dan
// ketiga halamannya (`perangkat_saya.php`, `perangkat_masuk.php`, `perangkat_jenis.php`). Modul ini
// memuat `inc/dokumen.php` sendiri (aturan validasi & batas ukuran berkas) — jangan dihapus.
require_once __DIR__ . '/perangkat.php';
// DAFTAR HADIR MEETING/PERTEMUAN (v1.73.0/skema v55) — modul ini dipakai halaman pengawas, halaman
// akun lembaga, endpoint tanda tangan, lembar cetak, DAN sidebar (lencana jumlah pertemuan yang belum
// ditandatangani), jadi di-require di sini. Modul ini juga memuat `inc/notulen_ttd.php` (batas &
// pembaca PNG tanda tangan dipakai bersama).
require_once __DIR__ . '/meeting.php';
require_once __DIR__ . '/layout.php';
