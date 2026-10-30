<?php
/**
 * Unggah / hapus / ubah keterangan FOTO BUKTI KEGIATAN pada sebuah notulen rapat.
 *
 * Maksimal NOTULEN_FOTO_MAKS (2) foto per notulen — batas ini ditegakkan di SERVER, bukan hanya
 * oleh JavaScript (satu notulen tidak boleh punya lebih dari 2 foto walau permintaan dikirim
 * berulang atau langsung ke endpoint ini).
 *
 * Berkas diteruskan ke media proxy platform dari sisi server (media_upload() di inc/helpers.php);
 * token unggah TIDAK pernah dikirim ke browser. Yang disimpan ke basis data hanya URL CDN hasil
 * respons (tabel notulen_foto).
 *
 * Menerima dua cara pengiriman, sama seperti upload_foto.php / upload_logo.php:
 *   - form multipart ($_FILES['foto'])
 *   - raw body (fetch/JS) dengan header X-CSRF-Token + field notulen_id & nama_berkas
 *
 * Hak akses: Admin Operator & guru yang ditugaskan sebagai Ka. Tata Usaha (+ Super Admin dalam
 * konteks sekolah) — sama dengan pembuat notulen (require_notulen_ubah()). Kepala Sekolah/Madrasah
 * hanya melihat & mencetak.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_notulen_ubah();

// Terima CSRF dari field form (multipart) atau header (unggahan fetch/raw body).
if (empty($_POST['csrf']) && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $_POST['csrf'] = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
}
csrf_guard();

/**
 * Ambil parameter dari POST MAUPUN GET. Penting untuk jalur raw body (fetch/JS): pada pengiriman
 * itu $_POST benar-benar kosong, sehingga notulen_id/aksi harus boleh dikirim lewat query string
 * (?notulen_id=…&aksi=unggah) — kalau hanya dibaca dari $_POST, notulennya tidak ditemukan.
 */
$param = static function (string $nama, string $bawaan = ''): string {
    if (isset($_POST[$nama]) && is_scalar($_POST[$nama])) {
        return (string) $_POST[$nama];
    }
    if (isset($_GET[$nama]) && is_scalar($_GET[$nama])) {
        return (string) $_GET[$nama];
    }
    return $bawaan;
};

$notulenId = (int) $param('notulen_id');
$n = notulen_by_id($notulenId); // sekaligus penjaga kepemilikan (harus milik sekolah dalam konteks)
$kembali = 'notulen_form.php?id=' . $notulenId . '#cardFotoNotulen';
if (!$n) {
    flash('Notulen tidak ditemukan.', 'err');
    redirect('notulen.php');
}

$aksi = $param('aksi');

// ---------------------------------------------------------------- hapus satu foto
if ($aksi === 'hapus') {
    $fotoId = (int) $param('id');
    $f = $fotoId > 0
        ? one('SELECT * FROM notulen_foto WHERE id = ? AND notulen_id = ?', [$fotoId, $notulenId])
        : null;
    if (!$f) {
        flash('Foto tidak ditemukan pada notulen ini.', 'err');
    } else {
        q('DELETE FROM notulen_foto WHERE id = ? AND notulen_id = ?', [$fotoId, $notulenId]);
        // Urutkan ulang supaya nomor urut tetap rapat (1, 2).
        notulen_foto_urutkan_ulang($notulenId);
        flash('Foto bukti kegiatan dihapus.', 'warn');
    }
    redirect($kembali);
}

// ---------------------------------------------------------------- ubah keterangan foto
if ($aksi === 'keterangan') {
    $fotoId = (int) $param('id');
    $ket = trim($param('keterangan'));
    if ($ket !== '' && function_exists('mb_substr')) {
        $ket = (string) mb_substr($ket, 0, 160);
    } elseif ($ket !== '') {
        $ket = substr($ket, 0, 160);
    }
    $f = $fotoId > 0
        ? one('SELECT * FROM notulen_foto WHERE id = ? AND notulen_id = ?', [$fotoId, $notulenId])
        : null;
    if (!$f) {
        flash('Foto tidak ditemukan pada notulen ini.', 'err');
    } else {
        q('UPDATE notulen_foto SET keterangan = ? WHERE id = ? AND notulen_id = ?', [$ket, $fotoId, $notulenId]);
        flash('Keterangan foto diperbarui.');
    }
    redirect($kembali);
}

// ---------------------------------------------------------------- unggah foto baru
$sisa = notulen_sisa_slot_foto($notulenId);
if ($sisa <= 0) {
    flash('Foto bukti kegiatan sudah ' . NOTULEN_FOTO_MAKS . ' buah (maksimal). Hapus salah satu dulu untuk menggantinya.', 'err');
    redirect($kembali);
}

$keterangan = trim($param('keterangan'));
if ($keterangan !== '' && function_exists('mb_substr')) {
    $keterangan = (string) mb_substr($keterangan, 0, 160);
} elseif ($keterangan !== '') {
    $keterangan = substr($keterangan, 0, 160);
}

// Ambil berkas dari server, apa pun cara pengirimannya.
$namaAsli = '';
$ukuran = 0;
$tmpPath = '';
$sementara = false; // true bila berkas sementara buatan kita sendiri (wajib dibersihkan)

if (!empty($_FILES['foto']['name'])) {
    $f = $_FILES['foto'];
    $kode = (int) $f['error'];
    if ($kode !== UPLOAD_ERR_OK) {
        $pesan = ($kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE)
            ? 'Foto terlalu besar untuk diunggah (batas server ' . ini_get('upload_max_filesize') . '). Perkecil dulu fotonya.'
            : 'Gagal mengunggah foto (kode ' . $kode . '). Silakan coba lagi.';
        flash($pesan, 'err');
        redirect($kembali);
    }
    $namaAsli = (string) $f['name'];
    $ukuran = (int) $f['size'];
    $tmpPath = (string) $f['tmp_name'];
} else {
    $namaAsli = trim($param('nama_berkas'));
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        flash('Tidak ada berkas foto yang dikirim. Pilih foto terlebih dahulu.', 'err');
        redirect($kembali);
    }
    $ukuran = strlen($raw);
    if ($namaAsli === '') {
        $namaAsli = 'bukti-kegiatan.jpg';
    }
    $tmp = tempnam(sys_get_temp_dir(), 'egnotfoto');
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
    flash('Gagal membaca berkas foto di server.', 'err');
    redirect($kembali);
}

// Cek ulang slot SEBELUM menyimpan (menutup celah dua permintaan hampir bersamaan).
if (notulen_sisa_slot_foto($notulenId) <= 0) {
    flash('Foto bukti kegiatan sudah ' . NOTULEN_FOTO_MAKS . ' buah (maksimal). Hapus salah satu dulu untuk menggantinya.', 'err');
    redirect($kembali);
}

$hasil = media_upload($bytes, $namaAsli);
if (empty($hasil['ok'])) {
    flash((string) ($hasil['pesan'] ?? 'Foto gagal diunggah.'), 'err');
    redirect($kembali);
}

q('INSERT INTO notulen_foto (notulen_id, url, keterangan, urutan, dibuat, dibuat_oleh) VALUES (?,?,?,?,?,?)',
    [$notulenId, (string) $hasil['url'], $keterangan,
     notulen_jumlah_foto($notulenId), date('Y-m-d H:i:s'), (int) $u['id']]);

flash('Foto bukti kegiatan berhasil diunggah (' . notulen_jumlah_foto($notulenId) . ' dari ' . NOTULEN_FOTO_MAKS . ' foto).');
redirect($kembali);
