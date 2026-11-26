<?php
/**
 * Unggah / hapus TANDA TANGAN KEPALA + CAP/STEMPEL lembaga, dan SAKELAR aktif/nonaktifnya
 * (v1.104.0 / skema v66).
 *
 * Permintaan pemilik: *"Pada bagian yang saya tandai warna merah tambahkan fitur untuk upload
 * Tanda tangan kepala sekolah/madrasah dan Cap/Stempel sekolah. Fitur ini bisa diaktifkan dan
 * non aktifkan oleh Operator sekolah/madrasah"*. Keputusan pemilik: keduanya dicetak pada blok
 * "Mengetahui, Kepala …" di SELURUH dokumen cetak, cap MENIMPA tanda tangan, dan sakelar Operator
 * menentukan MUNCUL/TIDAKNYA keduanya pada hasil cetak.
 *
 * Berkas diteruskan ke media proxy platform dari sisi server (media_upload()); token unggah TIDAK
 * pernah dikirim ke browser. Yang disimpan ke basis data hanya URL CDN hasil respons
 * (kolom `sekolah.ttd_kepala` / `sekolah.cap_sekolah`), sakelarnya `sekolah.ttd_cap_aktif`.
 *
 * Aksi (POST `aksi`): `ttd` · `cap` (unggah/ganti) · `hapus_ttd` · `hapus_cap` · `aktif` · `nonaktif`.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_pengaturan();
$kembali = 'pengaturan.php#cardTtdCap';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($kembali);
}
csrf_guard();
$aksi = (string) ($_POST['aksi'] ?? '');

// ---------------------------------------------------------------- SAKELAR aktif / nonaktif
// Sakelar hanya mengubah MUNCUL/TIDAKNYA tanda tangan & cap pada hasil cetak; berkas yang sudah
// diunggah TIDAK dihapus sehingga Operator dapat menyalakannya kembali tanpa mengunggah ulang.
if ($aksi === 'aktif' || $aksi === 'nonaktif') {
    set_setting('ttd_cap_aktif', $aksi === 'aktif' ? '1' : '0');
    if ($aksi === 'aktif') {
        if (ttd_kepala_gambar() === '' && cap_sekolah_gambar() === '') {
            // Menyala tetapi belum ada berkas: fitur belum berpengaruh pada hasil cetak.
            flash('Fitur tanda tangan & cap diaktifkan. Belum ada berkas yang diunggah — hasil cetak '
                . 'baru berubah setelah tanda tangan kepala dan/atau cap/stempel diunggah.', 'warn');
        } else {
            flash('Fitur tanda tangan kepala & cap/stempel ' . istilah_sekolah('sekolah')
                . ' DIAKTIFKAN — keduanya kini tercetak pada blok "Mengetahui, '
                . jabatan_kepala() . '" di seluruh dokumen cetak.');
        }
    } else {
        flash('Fitur tanda tangan kepala & cap/stempel DINONAKTIFKAN — hasil cetak kembali tanpa '
            . 'tanda tangan dan cap. Berkas yang sudah diunggah tetap tersimpan.', 'warn');
    }
    redirect($kembali);
}

// ---------------------------------------------------------------- HAPUS berkas
if ($aksi === 'hapus_ttd') {
    set_setting('ttd_kepala_sekolah', '');
    flash('Gambar tanda tangan kepala dihapus.', 'warn');
    redirect($kembali);
}
if ($aksi === 'hapus_cap') {
    set_setting('cap_sekolah', '');
    flash('Gambar cap/stempel dihapus.', 'warn');
    redirect($kembali);
}

// ---------------------------------------------------------------- UNGGAH berkas
$medan = '';
$kunci = '';
$label = '';
if ($aksi === 'ttd') {
    $medan = 'ttd_kepala';
    $kunci = 'ttd_kepala_sekolah';
    $label = 'Tanda tangan kepala ' . istilah_sekolah('sekolah');
} elseif ($aksi === 'cap') {
    $medan = 'cap_sekolah';
    $kunci = 'cap_sekolah';
    $label = 'Cap/stempel ' . istilah_sekolah('sekolah');
} else {
    flash('Permintaan tidak dikenali.', 'err');
    redirect($kembali);
}

$namaAsli = '';
$ukuran = 0;
$tmpPath = '';
$sementara = false;

if (!empty($_FILES[$medan]['name'])) {
    $f = $_FILES[$medan];
    $kode = (int) $f['error'];
    if ($kode !== UPLOAD_ERR_OK) {
        $pesan = ($kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE)
            ? 'Berkas terlalu besar untuk diunggah (batas server ' . ini_get('upload_max_filesize') . '). Perkecil dulu berkasnya.'
            : 'Gagal mengunggah berkas (kode ' . $kode . '). Silakan coba lagi.';
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
        flash('Belum ada berkas ' . $label . ' yang dikirim. Pilih berkas terlebih dahulu.', 'err');
        redirect($kembali);
    }
    $ukuran = strlen($raw);
    if ($namaAsli === '') {
        $namaAsli = ($aksi === 'ttd' ? 'ttd-kepala' : 'cap-sekolah') . '.png';
    }
    $tmp = tempnam(sys_get_temp_dir(), 'egttdcap');
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
    flash('Gagal membaca berkas di server.', 'err');
    redirect($kembali);
}

$hasil = media_upload($bytes, $namaAsli);
if (empty($hasil['ok'])) {
    flash((string) ($hasil['pesan'] ?? ($label . ' gagal diunggah.')), 'err');
    redirect($kembali);
}

set_setting($kunci, (string) $hasil['url']);
// Berkas pertama yang berhasil diunggah TIDAK menyalakan fitur sendiri — sakelar tetap milik
// Operator (permintaan pemilik). Yang ditampilkan hanya pengingat bila sakelarnya masih mati.
if (!ttd_cap_aktif()) {
    flash(ucfirst($label) . ' berhasil diunggah, tetapi fitur masih NONAKTIF — tekan "Aktifkan" '
        . 'agar tanda tangan & cap ikut tercetak.', 'warn');
} else {
    flash(ucfirst($label) . ' berhasil diunggah dan akan tampil pada blok "Mengetahui, '
        . jabatan_kepala() . '" di seluruh dokumen cetak.');
}
redirect($kembali);
