<?php
/**
 * Konfigurasi dasar aplikasi e-GURU (Administrasi Guru Digital).
 *
 * Aplikasi SINGLE-SEKOLAH: satu instalasi dipakai satu sekolah/madrasah.
 * Identitas sekolah, tahun ajaran, semester, dan kelas diatur lewat Pengaturan.
 */
declare(strict_types=1);

define('APP_NAME', 'e-GURU');
define('APP_SUB', 'Administrasi Guru Digital');
define('APP_VERSION', '1.0.1');

// Unggahan berkas (logo sekolah) lewat media proxy platform. Token dibaca server-side
// dari berkas .vibecoder-media-token dan TIDAK pernah dikirim ke browser.
define('MEDIA_ENDPOINT', 'http://127.0.0.1:4310/api/app-media/upload');
define('FOTO_MAKS_BYTE', 15 * 1024 * 1024); // 15 MB (batas platform untuk gambar)
define('FOTO_EKSTENSI', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);

// Pembuat aplikasi (tampil pada halaman login & footer).
define('APP_AUTHOR', 'Supianto');
define('APP_AUTHOR_JABATAN', 'Kepala MA. Sirojuth Tholibiin Mootilango');

// Berkas database SQLite (di dalam folder aplikasi).
define('DB_FILE', __DIR__ . '/../data/eguru.sqlite');

// Aplikasi selalu diakses di bawah subfolder-nya sendiri (https://.../e-guru/),
// jadi SEMUA tautan memakai path relatif (tanpa leading slash).
define('BASE', '');

date_default_timezone_set('Asia/Jakarta');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Simpan berkas sesi di dalam folder aplikasi (folder sesi sistem bisa tidak writable).
    $sessDir = __DIR__ . '/../data/sessions';
    if (!is_dir($sessDir)) {
        @mkdir($sessDir, 0775, true);
    }
    if (is_dir($sessDir) && is_writable($sessDir)) {
        session_save_path($sessDir);
    }
    session_start();
}
