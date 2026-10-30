<?php
/**
 * Unggah / kembalikan LOGO APLIKASI (khusus Super Admin) — tingkat APLIKASI, bukan per sekolah.
 *
 * Berkas diteruskan ke media proxy platform dari SISI SERVER (media_upload()); token unggah
 * TIDAK pernah dikirim ke browser. Yang disimpan ke basis data hanya URL CDN hasil respons
 * (`app_pengaturan` kunci `logo_app`).
 *
 * Menerima dua cara: form multipart ($_FILES['logo']) atau raw body + header X-CSRF-Token.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

require_super();

if (empty($_POST['csrf']) && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $_POST['csrf'] = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
}
csrf_guard();

$kembali = 'logo.php';
$aksi = trim((string) ($_POST['aksi'] ?? 'unggah'));

if ($aksi === 'hapus') {
    app_setting_set('logo_app', '');
    flash('Logo aplikasi dikembalikan ke lambang bawaan.', 'warn');
    redirect($kembali);
}

$namaAsli = '';
$ukuran = 0;
$tmpPath = '';
$sementara = false;

if (!empty($_FILES['logo']['name'])) {
    $f = $_FILES['logo'];
    $kode = (int) $f['error'];
    if ($kode !== UPLOAD_ERR_OK) {
        $pesan = ($kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE)
            ? 'Berkas logo terlalu besar untuk diunggah (batas server ' . ini_get('upload_max_filesize') . '). Perkecil dulu berkasnya.'
            : 'Gagal mengunggah logo (kode ' . $kode . '). Silakan coba lagi.';
        flash($pesan, 'err');
        redirect($kembali);
    }
    $namaAsli = (string) $f['name'];
    $ukuran = (int) $f['size'];
    $tmpPath = (string) $f['tmp_name'];
} else {
    $namaAsli = trim((string) ($_POST['nama_berkas'] ?? ''));
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        flash('Tidak ada berkas logo yang dikirim. Pilih berkas terlebih dahulu.', 'err');
        redirect($kembali);
    }
    $ukuran = strlen($raw);
    if ($namaAsli === '') {
        $namaAsli = 'logo-aplikasi.png';
    }
    $tmp = tempnam(sys_get_temp_dir(), 'egapplogo');
    if ($tmp === false || file_put_contents($tmp, $raw) === false) {
        flash('Gagal menyiapkan berkas sementara di server.', 'err');
        redirect($kembali);
    }
    $tmpPath = $tmp;
    $sementara = true;
}

$cek = validasi_foto($namaAsli, $ukuran, $tmpPath);
if (empty($cek['ok'])) {
    if ($sementara) {
        @unlink($tmpPath);
    }
    flash((string) $cek['pesan'], 'err');
    redirect($kembali);
}

$bytes = file_get_contents($tmpPath);
if ($sementara) {
    @unlink($tmpPath);
}
if ($bytes === false) {
    flash('Gagal membaca berkas logo di server.', 'err');
    redirect($kembali);
}

$hasil = media_upload($bytes, $namaAsli);
if (empty($hasil['ok'])) {
    flash((string) ($hasil['pesan'] ?? 'Logo gagal diunggah.'), 'err');
    redirect($kembali);
}

app_setting_set('logo_app', (string) $hasil['url']);
flash('Logo aplikasi berhasil diunggah dan langsung dipakai pada halaman masuk, halaman pendaftaran, dan sidebar.');
redirect($kembali);
