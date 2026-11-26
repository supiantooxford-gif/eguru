<?php
/**
 * Handler TANDA TANGAN DIGITAL notulen (layar sentuh) — e-GURU v1.70.0 / skema v50.
 *
 * Menerima POST dari dialog tanda tangan pada lembar Notulen Rapat:
 *   aksi=simpan (bawaan) + id (notulen) + pihak + gambar (data URL PNG hasil <canvas>)
 *   aksi=hapus  + id + pihak
 *
 * Alur: periksa login & hak lihat notulen → periksa CSRF → periksa pihak SAH untuk notulen itu
 * (anti-IDOR) → dekode PNG → `notulen_ttd_simpan()` (unggah ke media proxy, simpan URL-nya saja).
 * Balasan JSON bila permintaan AJAX/JSON; selain itu flash + pengalihan kembali ke lembar
 * Daftar Hadir (pola yang sama dengan `upload_notulen_foto.php`).
 *
 * Sejak v1.102.0 handler ini juga melayani `sumber=tusmart`: tanda tangan rapat yang dibuat
 * Kepala Tata Usaha di aplikasi TUSmart (hanya untuk lembaga yang sama — lihat
 * `inc/ttd_rapat_tusmart.php`). PNG tetap diunggah lewat media proxy, lalu URL-nya ditulis ke
 * tabel `notulen_ttd` TUSmart dengan kunci `hadir:g<id guru TUSmart>`.
 *
 * Sejak v1.103.0 cabang `sumber=tusmart` juga melayani pihak **`kepala`**: kepala sekolah/madrasah
 * membubuhkan tanda tangan pada notulen TUSmart yang tampil di Daftar Notulen e-GURU
 * (`inc/notulen_tusmart.php`). Hanya akun kepala, hanya pihak `kepala`, hanya lembaga yang cocok
 * (NPSN) — seluruhnya diperiksa di sini, bukan hanya dengan menyembunyikan tombol.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/notulen_ttd.php';
require_once __DIR__ . '/inc/ttd_rapat_tusmart.php';
require_once __DIR__ . '/inc/notulen_tusmart.php';   // notulen TUSmart (v1.103.0)

$u = require_login();
require_sekolah();
// Hak MEMBUKA lembar Notulen (kepala/operator/guru Ka. TU/Super Admin dalam konteks) menentukan
// cakupan hak tanda tangan di bawah. Akun GURU lain tidak boleh membuka lembar cetak, tetapi sejak
// v1.101.0 boleh menandatangani daftar hadir rapat untuk NAMANYA SENDIRI dari menu "Tanda Tangan
// Rapat" (`ttd_rapat.php`) — permintaan pemilik: *"Buat guru bisa menandatangani lewat akun guru
// masing-masing."*

$inginJson = (isset($_GET['format']) && $_GET['format'] === 'json')
    || (isset($_POST['format']) && $_POST['format'] === 'json')
    || (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest')
    || (str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json'));

/** Parameter dari POST MAUPUN GET (jalur fetch bisa mengirim sebagian lewat query string). */
$param = static function (string $nama, string $bawaan = ''): string {
    if (isset($_POST[$nama]) && is_scalar($_POST[$nama])) {
        return (string) $_POST[$nama];
    }
    if (isset($_GET[$nama]) && is_scalar($_GET[$nama])) {
        return (string) $_GET[$nama];
    }
    return $bawaan;
};

/** Mengirim balasan lalu berhenti. */
function ttd_jawab(bool $ok, string $pesan, array $tambahan = [], int $kode = 0, bool $json = false): void
{
    // Kode status HANYA untuk balasan JSON; pada jalur formulir biasa pengguna selalu dialihkan
    // kembali ke lembar daftar hadir dengan pesan.
    if ($json && $kode > 0) {
        http_response_code($kode);
    }
    if ($json) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode(array_merge(['ok' => $ok, 'pesan' => $pesan], $tambahan),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    flash($pesan, $ok ? 'ok' : 'err');
    // Pengalihan khusus (mis. rapat TUSmart → halaman rinciannya sendiri).
    $tujuan = (string) ($GLOBALS['ttd_redirect'] ?? '');
    if ($tujuan !== '') {
        redirect($tujuan);
    }
    $id = (int) $param('id');
    // Akun guru (bukan pembaca lembar cetak) dikembalikan ke halaman Tanda Tangan Rapat miliknya —
    // `cetak_notulen.php` menolaknya dengan 403 (v1.101.0).
    if (boleh_lihat_notulen()) {
        redirect($id > 0 ? 'cetak_notulen.php?id=' . $id . '&bagian=hadir' : 'notulen.php');
    }
    redirect($id > 0 ? 'ttd_rapat.php?id=' . $id : 'ttd_rapat.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($inginJson) {
        ttd_jawab(false, 'Permintaan harus dikirim dengan metode POST.', [], 405, true);
    }
    flash('Permintaan tanda tangan tidak dikenal.', 'err');
    redirect(boleh_lihat_notulen() ? 'notulen.php' : 'ttd_rapat.php');
}

// Token juga diterima lewat header (jalur fetch non-form).
if (empty($_POST['csrf']) && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $_POST['csrf'] = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
}
if (!csrf_ok()) {
    if ($inginJson) {
        ttd_jawab(false, 'Sesi formulir sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.',
            ['kedaluwarsa' => true], 419, true);
    }
    csrf_guard();
}

$notulenId = (int) $param('id');
if ($notulenId <= 0) {
    $notulenId = (int) $param('notulen_id');
}

// ============================================================================================
// CABANG TUSMART — notulen rapat yang dibuat Kepala Tata Usaha di aplikasi TUSmart.
//   CABANG A (v1.103.0): akun KEPALA sekolah/madrasah menandatangani blok "Mengetahui, Kepala …".
//   CABANG B (v1.102.0): akun GURU peserta menandatangani baris daftar hadirnya sendiri
//                       (`hadir:g<id guru TUSmart>`) dari menu "Tanda Tangan Rapat".
// Keduanya hanya bila lembaga TUSmart memang sekolah yang sedang dibuka (dicocokkan lewat NPSN);
// tanda tangan ditulis ke basis data TUSmart supaya langsung tercetak pada lembar di sana.
// ============================================================================================
if ($param('sumber') === 'tusmart') {
    $pihakTs = trim($param('pihak'));
    $aksiTs = $param('aksi') !== '' ? $param('aksi') : 'simpan';

    // ------------------------------------------------------------------------------------------
    // CABANG A (v1.103.0) — KEPALA sekolah/madrasah membubuhkan tanda tangan pada blok
    // "Mengetahui, Kepala …" dari Daftar Notulen e-GURU. Hanya pihak `kepala`, hanya akun kepala,
    // hanya lembaga yang memang dikelola di TUSmart (NPSN) — semuanya diperiksa di server.
    // ------------------------------------------------------------------------------------------
    if ($pihakTs === NOTULEN_TUSMART_PIHAK_KEPALA) {
        $GLOBALS['ttd_redirect'] = 'cetak_notulen.php?sumber=tusmart&bagian=hadir'
            . ($notulenId > 0 ? '&id=' . $notulenId : '');
        if (!notulen_tusmart_boleh()) {
            ttd_jawab(false, 'Notulen rapat TUSmart hanya dapat ditandatangani oleh Kepala '
                . istilah_sekolah('Sekolah') . ' pada lembaga yang dikelola di TUSmart.', [], 403,
                $inginJson);
        }
        $notulenTs = notulen_tusmart_satu($notulenId);
        if (!$notulenTs) {
            ttd_jawab(false, 'Notulen rapat TUSmart tidak ditemukan.', [], 404, $inginJson);
        }
        if ($aksiTs === 'hapus') {
            if (!tusmart_ttd_hapus_pihak($notulenId, NOTULEN_TUSMART_PIHAK_KEPALA)) {
                ttd_jawab(false, 'Belum ada tanda tangan Kepala ' . istilah_sekolah('Sekolah')
                    . ' yang tersimpan pada notulen ini.', [], 404, $inginJson);
            }
            ttd_jawab(true, 'Tanda tangan Kepala ' . istilah_sekolah('Sekolah') . ' dihapus.',
                ['pihak' => $pihakTs], 0, $inginJson);
        }
        if ($aksiTs !== 'simpan') {
            ttd_jawab(false, 'Aksi tanda tangan tidak dikenal.', [], 400, $inginJson);
        }
        $pnKepala = notulen_ttd_png_dari_data_url($param('gambar'));
        if (empty($pnKepala['ok'])) {
            ttd_jawab(false, (string) ($pnKepala['pesan'] ?? 'Gambar tanda tangan tidak dapat dibaca.'),
                [], 400, $inginJson);
        }
        $uKepala = current_user();
        $hasilKepala = tusmart_ttd_simpan_pihak($notulenId, NOTULEN_TUSMART_PIHAK_KEPALA,
            (string) $pnKepala['bytes'], (string) ($uKepala['nama'] ?? ''));
        if (empty($hasilKepala['ok'])) {
            ttd_jawab(false, (string) ($hasilKepala['pesan'] ?? 'Tanda tangan gagal disimpan.'),
                [], 400, $inginJson);
        }
        ttd_jawab(true, 'Tanda tangan Kepala ' . istilah_sekolah('Sekolah') . ' tersimpan.',
            ['pihak' => $pihakTs, 'url' => (string) ($hasilKepala['url'] ?? '')], 0, $inginJson);
    }

    $GLOBALS['ttd_redirect'] = 'ttd_rapat.php'
        . ($notulenId > 0 ? '?sumber=tusmart&id=' . $notulenId : '');
    $gidSaya = guru_id_saya();
    if ($gidSaya <= 0 || !tusmart_aktif()) {
        ttd_jawab(false, 'Rapat TUSmart ini bukan untuk lembaga Anda.', [], 403, $inginJson);
    }
    $rapat = tusmart_rapat_satu($notulenId, $gidSaya);
    if (!$rapat) {
        ttd_jawab(false, 'Nama Anda tidak tercatat pada daftar hadir rapat ini, sehingga tidak '
            . 'dapat ditandatangani.', [], 400, $inginJson);
    }
    $kunciTs = (string) ($rapat['kunci'] ?? '');
    if ($kunciTs === '' || $pihakTs !== $kunciTs) {
        ttd_jawab(false, 'Anda hanya dapat menandatangani daftar hadir rapat untuk nama Anda sendiri.',
            [], 403, $inginJson);
    }
    $gidTs = (int) ($rapat['guru_id_sumber'] ?? 0);

    if ($aksiTs === 'hapus') {
        if (!tusmart_ttd_hapus($notulenId, $gidTs)) {
            ttd_jawab(false, 'Belum ada tanda tangan tersimpan untuk '
                . (string) ($rapat['nama_hadir'] ?? '') . '.', [], 404, $inginJson);
        }
        ttd_jawab(true, 'Tanda tangan ' . (string) ($rapat['nama_hadir'] ?? '') . ' dihapus.',
            ['pihak' => $pihakTs], 0, $inginJson);
    }
    if ($aksiTs !== 'simpan') {
        ttd_jawab(false, 'Aksi tanda tangan tidak dikenal.', [], 400, $inginJson);
    }
    $pn = notulen_ttd_png_dari_data_url($param('gambar'));
    if (empty($pn['ok'])) {
        ttd_jawab(false, (string) ($pn['pesan'] ?? 'Gambar tanda tangan tidak dapat dibaca.'),
            [], 400, $inginJson);
    }
    $uSekarang = current_user();
    $hasilTs = tusmart_ttd_simpan($notulenId, $gidTs, (string) $pn['bytes'],
        (string) ($uSekarang['nama'] ?? ''));
    if (empty($hasilTs['ok'])) {
        ttd_jawab(false, (string) ($hasilTs['pesan'] ?? 'Tanda tangan gagal disimpan.'),
            [], 400, $inginJson);
    }
    ttd_jawab(true, 'Tanda tangan ' . (string) ($rapat['nama_hadir'] ?? '') . ' tersimpan.',
        ['pihak' => $pihakTs, 'url' => (string) ($hasilTs['url'] ?? '')], 0, $inginJson);
}

$n = notulen_by_id($notulenId); // sekaligus penjaga kepemilikan (sekolah dalam konteks)
if (!$n) {
    ttd_jawab(false, 'Notulen tidak ditemukan.', [], 404, $inginJson);
}

$peserta = notulen_peserta($notulenId);
$daftarPihak = notulen_ttd_pihak($n, $peserta);
$pihak = trim($param('pihak'));

// ---------------------------------------------------------------- hak tanda tangan (anti-IDOR)
// Pembaca lembar notulen (kepala/operator/guru Ka. TU/Super Admin dalam konteks) menandatangani
// SEMUA pihak seperti sebelumnya. Akun GURU lain HANYA boleh menandatangani barisnya sendiri
// (`hadir:g<id guru>`) — yaitu rapat yang namanya DICENTANG operator pada daftar hadir.
$kunciSaya = ttd_rapat_kunci(guru_id_saya());
if (!boleh_lihat_notulen() && ($kunciSaya === '' || $pihak !== $kunciSaya)) {
    ttd_jawab(false, 'Anda hanya dapat menandatangani daftar hadir rapat untuk nama Anda sendiri.',
        [], 403, $inginJson);
}

if ($pihak === '' || !array_key_exists($pihak, $daftarPihak)) {
    $pesan = (!boleh_lihat_notulen() && $kunciSaya !== '')
        ? 'Nama Anda tidak tercatat pada daftar hadir rapat ini, sehingga tidak dapat ditandatangani.'
        : 'Pihak penanda tangan tidak dikenal pada notulen ini.';
    ttd_jawab(false, $pesan, [], 400, $inginJson);
}

$aksi = $param('aksi') !== '' ? $param('aksi') : 'simpan';

// ---------------------------------------------------------------- hapus tanda tangan tersimpan
if ($aksi === 'hapus') {
    $ada = notulen_ttd_hapus($notulenId, $pihak);
    if (!$ada) {
        ttd_jawab(false, 'Belum ada tanda tangan tersimpan untuk '
            . $daftarPihak[$pihak]['label'] . '.', [], 404, $inginJson);
    }
    ttd_jawab(true, 'Tanda tangan ' . $daftarPihak[$pihak]['label'] . ' dihapus.',
        ['pihak' => $pihak], 0, $inginJson);
}

if ($aksi !== 'simpan') {
    ttd_jawab(false, 'Aksi tanda tangan tidak dikenal.', [], 400, $inginJson);
}

// ---------------------------------------------------------------- data URL PNG dari kanvas
// Dekode memakai penolong BERSAMA `notulen_ttd_png_dari_data_url()` (dipakai juga cabang TUSmart).
$png = notulen_ttd_png_dari_data_url($param('gambar'));
if (empty($png['ok'])) {
    ttd_jawab(false, (string) ($png['pesan'] ?? 'Gambar tanda tangan tidak dapat dibaca.'),
        [], 400, $inginJson);
}

$hasil = notulen_ttd_simpan($notulenId, $pihak, (string) $png['bytes']);
if (empty($hasil['ok'])) {
    ttd_jawab(false, (string) ($hasil['pesan'] ?? 'Tanda tangan gagal disimpan.'), [], 400, $inginJson);
}
ttd_jawab(true, 'Tanda tangan ' . $daftarPihak[$pihak]['label'] . ' tersimpan.',
    ['pihak' => $pihak, 'url' => (string) ($hasil['url'] ?? '')], 0, $inginJson);
