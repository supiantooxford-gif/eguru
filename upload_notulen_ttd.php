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
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/notulen_ttd.php';

$u = require_notulen(); // kepala (lihat & cetak), operator, guru Ka. TU, Super Admin dalam konteks

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
    $id = (int) $param('id');
    redirect($id > 0 ? 'cetak_notulen.php?id=' . $id . '&bagian=hadir' : 'notulen.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($inginJson) {
        ttd_jawab(false, 'Permintaan harus dikirim dengan metode POST.', [], 405, true);
    }
    flash('Permintaan tanda tangan tidak dikenal.', 'err');
    redirect('notulen.php');
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
$n = notulen_by_id($notulenId); // sekaligus penjaga kepemilikan (sekolah dalam konteks)
if (!$n) {
    ttd_jawab(false, 'Notulen tidak ditemukan.', [], 404, $inginJson);
}

$peserta = notulen_peserta($notulenId);
$daftarPihak = notulen_ttd_pihak($n, $peserta);
$pihak = trim($param('pihak'));
if ($pihak === '' || !array_key_exists($pihak, $daftarPihak)) {
    ttd_jawab(false, 'Pihak penanda tangan tidak dikenal pada notulen ini.', [], 400, $inginJson);
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
$data = trim($param('gambar'));
if ($data === '') {
    ttd_jawab(false, 'Tanda tangan masih kosong — coretkan tanda tangan terlebih dahulu.', [], 400, $inginJson);
}
if (strlen($data) > 8 * 1024 * 1024) {
    ttd_jawab(false, 'Gambar tanda tangan terlalu besar.', [], 400, $inginJson);
}
$b64 = $data;
if (str_starts_with($data, 'data:')) {
    $koma = strpos($data, ',');
    if ($koma === false) {
        ttd_jawab(false, 'Format gambar tanda tangan tidak dikenal.', [], 400, $inginJson);
    }
    $kepala = substr($data, 0, $koma);
    if (stripos($kepala, 'image/png') === false) {
        ttd_jawab(false, 'Gambar tanda tangan harus berformat PNG.', [], 400, $inginJson);
    }
    $b64 = substr($data, $koma + 1);
}
$b64 = preg_replace('/\s+/', '', $b64) ?? '';
$bytes = base64_decode($b64, true);
if ($bytes === false || $bytes === '') {
    ttd_jawab(false, 'Gambar tanda tangan tidak dapat dibaca.', [], 400, $inginJson);
}

$hasil = notulen_ttd_simpan($notulenId, $pihak, (string) $bytes);
if (empty($hasil['ok'])) {
    ttd_jawab(false, (string) ($hasil['pesan'] ?? 'Tanda tangan gagal disimpan.'), [], 400, $inginJson);
}
ttd_jawab(true, 'Tanda tangan ' . $daftarPihak[$pihak]['label'] . ' tersimpan.',
    ['pihak' => $pihak, 'url' => (string) ($hasil['url'] ?? '')], 0, $inginJson);
