<?php
/**
 * Handler TANDA TANGAN DIGITAL daftar hadir MEETING/PERTEMUAN — e-GURU v1.73.0 / skema v55.
 *
 * Menerima POST dari dialog tanda tangan (`initTtdSentuh()` di assets/app.js):
 *   aksi=simpan (bawaan) + id (meeting) + pihak + gambar (data URL PNG hasil <canvas>)
 *   aksi=hapus  + id + pihak
 *
 * `pihak` = `pengawas` (Pemimpin Meeting — hanya oleh pengawas PEMBUAT meeting) atau
 * `u<user_id>` (akun lembaga yang menandatangani DIRINYA SENDIRI).
 *
 * Alur: periksa login & peran → periksa CSRF → tentukan sekolah/madrasah + hak (anti-IDOR) →
 * dekode PNG → simpan (unggah ke media proxy lewat `media_upload()`, yang disimpan hanya URL-nya).
 * Balasan JSON untuk permintaan AJAX (polanya sama dengan `upload_notulen_ttd.php`).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/meeting.php';

// ---------------------------------------------------------------- JARING PENGAMAN JSON
// Endpoint ini SELALU dijawab JSON. Bila terjadi galat PHP (fatal/exception), tanpa penjaga ini
// PHP mencetak halaman HTML sehingga dialog tanda tangan hanya menampilkan pesan umum
// "Gagal menghubungi server" — persis kejadian nyata yang dilaporkan pemilik (kolom
// `meeting_hadir` sempat ber-FK ke tabel yang tidak ada). Sekarang galatnya tetap dikirim
// sebagai JSON berisi keterangan, dan dicatat ke log server.
$GLOBALS['__meeting_ttd_json_selesai'] = false;
$meetingTtdGagal = static function (string $pesan, int $kode = 500): void {
    if (!empty($GLOBALS['__meeting_ttd_json_selesai'])) {
        return;
    }
    $GLOBALS['__meeting_ttd_json_selesai'] = true;
    if (!headers_sent()) {
        http_response_code($kode);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode(['ok' => false, 'pesan' => $pesan], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};
set_exception_handler(static function (Throwable $e) use ($meetingTtdGagal): void {
    error_log('e-GURU upload_meeting_ttd: ' . $e->getMessage());
    $meetingTtdGagal('Tanda tangan gagal disimpan karena galat pada server. '
        . 'Muat ulang halaman lalu coba lagi; bila tetap gagal, hubungi Admin aplikasi.');
});
register_shutdown_function(static function () use ($meetingTtdGagal): void {
    $e = error_get_last();
    if ($e === null || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    error_log('e-GURU upload_meeting_ttd (fatal): ' . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line']);
    $meetingTtdGagal('Tanda tangan gagal disimpan karena galat pada server. '
        . 'Muat ulang halaman lalu coba lagi; bila tetap gagal, hubungi Admin aplikasi.');
});

$u = require_login();

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
function meeting_ttd_jawab(bool $ok, string $pesan, array $tambahan = [], int $kode = 0, bool $json = false): void
{
    // Tandai bahwa balasan JSON sudah dikirim (jaring pengaman shutdown tidak perlu menambah apa pun).
    $GLOBALS['__meeting_ttd_json_selesai'] = true;
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
    redirect($id > 0 ? 'meeting_hadir.php?id=' . $id : 'meeting_hadir.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($inginJson) {
        meeting_ttd_jawab(false, 'Permintaan harus dikirim dengan metode POST.', [], 405, true);
    }
    flash('Permintaan tanda tangan tidak dikenal.', 'err');
    redirect('meeting_hadir.php');
}

// Token juga diterima lewat header (jalur fetch non-form).
if (empty($_POST['csrf']) && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $_POST['csrf'] = (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
}
if (!csrf_ok()) {
    if ($inginJson) {
        meeting_ttd_jawab(false, 'Sesi formulir sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.',
            ['kedaluwarsa' => true], 419, true);
    }
    csrf_guard();
}

$meetingId = (int) $param('id');
if ($meetingId <= 0) {
    $meetingId = (int) $param('meeting_id');
}
$m = meeting_by_id($meetingId);
if (!$m) {
    meeting_ttd_jawab(false, 'Meeting tidak ditemukan.', [], 404, $inginJson);
}

$pihak = trim($param('pihak'));
if ($pihak === '') {
    meeting_ttd_jawab(false, 'Pihak penanda tangan tidak dikenal.', [], 400, $inginJson);
}
$aksi = $param('aksi') !== '' ? $param('aksi') : 'simpan';

// ---------------------------------------------------------------- PEMIMPIN MEETING (pengawas)
if ($pihak === MEETING_PIHAK_PEMIMPIN) {
    if (!is_pengawas() || (int) ($m['pengawas_id'] ?? 0) !== (int) $u['id']) {
        meeting_ttd_jawab(false, 'Hanya pengawas pembuat meeting ini yang dapat menandatangani '
            . 'sebagai Pemimpin Meeting.', [], 403, $inginJson);
    }
    if ($aksi === 'hapus') {
        if (!meeting_ttd_pemimpin_hapus($meetingId)) {
            meeting_ttd_jawab(false, 'Belum ada tanda tangan Pemimpin Meeting yang tersimpan.', [], 404, $inginJson);
        }
        meeting_ttd_jawab(true, 'Tanda tangan Pemimpin Meeting dihapus.', [], 0, $inginJson);
    }
    if ($aksi !== 'simpan') {
        meeting_ttd_jawab(false, 'Aksi tanda tangan tidak dikenal.', [], 400, $inginJson);
    }
    $bytes = meeting_bytes_dari_data_url($param('gambar'));
    if (empty($bytes['ok'])) {
        meeting_ttd_jawab(false, (string) $bytes['pesan'], [], 400, $inginJson);
    }
    $hasil = meeting_ttd_pemimpin_simpan($meetingId, (string) $bytes['bytes']);
    if (empty($hasil['ok'])) {
        meeting_ttd_jawab(false, (string) $hasil['pesan'], [], 400, $inginJson);
    }
    meeting_ttd_jawab(true, (string) $hasil['pesan'], ['pihak' => $pihak, 'url' => (string) $hasil['url']],
        0, $inginJson);
}

// ---------------------------------------------------------------- PESERTA (akun lembaga)
if (preg_match('/^u(\d+)$/', $pihak, $mm) !== 1) {
    meeting_ttd_jawab(false, 'Pihak penanda tangan tidak dikenal.', [], 400, $inginJson);
}
$pihakUserId = (int) $mm[1];
if ($pihakUserId !== (int) $u['id']) {
    meeting_ttd_jawab(false, 'Anda hanya dapat menandatangani daftar hadir untuk akun Anda sendiri.',
        [], 403, $inginJson);
}
if (!in_array(peran(), meeting_peran_peserta(), true)) {
    meeting_ttd_jawab(false, 'Daftar hadir meeting hanya ditandatangani akun kepala sekolah/madrasah, '
        . 'admin operator, dan guru.', [], 403, $inginJson);
}
$sekolah = current_sekolah();
$sekolahId = $sekolah ? (int) $sekolah['id'] : 0;
if ($sekolahId <= 0 || (int) ($u['sekolah_id'] ?? 0) !== $sekolahId) {
    meeting_ttd_jawab(false, 'Sekolah/madrasah Anda belum dipilih.', [], 400, $inginJson);
}
if (!meeting_aktif_untuk($meetingId, $sekolahId)) {
    meeting_ttd_jawab(false, 'Daftar hadir meeting ini tidak diaktifkan untuk sekolah/madrasah Anda.',
        [], 403, $inginJson);
}
$peserta = null;
foreach (meeting_peserta($meetingId, $sekolahId) as $p) {
    if ((int) $p['id'] === $pihakUserId) {
        $peserta = $p;
        break;
    }
}
if ($peserta === null) {
    meeting_ttd_jawab(false, 'Akun Anda tidak termasuk peserta daftar hadir meeting ini.', [], 403, $inginJson);
}

if ($aksi === 'hapus') {
    if (!meeting_hadir_hapus($meetingId, $sekolahId, $pihakUserId)) {
        meeting_ttd_jawab(false, 'Belum ada tanda tangan tersimpan untuk Anda.', [], 404, $inginJson);
    }
    meeting_ttd_jawab(true, 'Tanda tangan Anda dihapus.', ['pihak' => $pihak], 0, $inginJson);
}
if ($aksi !== 'simpan') {
    meeting_ttd_jawab(false, 'Aksi tanda tangan tidak dikenal.', [], 400, $inginJson);
}
$bytes = meeting_bytes_dari_data_url($param('gambar'));
if (empty($bytes['ok'])) {
    meeting_ttd_jawab(false, (string) $bytes['pesan'], [], 400, $inginJson);
}
$hasil = meeting_hadir_simpan($meetingId, $sekolahId, $peserta, (string) $bytes['bytes']);
if (empty($hasil['ok'])) {
    meeting_ttd_jawab(false, (string) $hasil['pesan'], [], 400, $inginJson);
}
meeting_ttd_jawab(true, (string) $hasil['pesan'], ['pihak' => $pihak, 'url' => (string) $hasil['url']],
    0, $inginJson);

/**
 * Mengubah data URL PNG (hasil <canvas>) menjadi byte PNG.
 *
 * @return array{ok: bool, pesan: string, bytes: string}
 */
function meeting_bytes_dari_data_url(string $data): array
{
    $data = trim($data);
    if ($data === '') {
        return ['ok' => false, 'pesan' => 'Tanda tangan masih kosong — coretkan tanda tangan terlebih dahulu.',
            'bytes' => ''];
    }
    if (strlen($data) > 8 * 1024 * 1024) {
        return ['ok' => false, 'pesan' => 'Gambar tanda tangan terlalu besar.', 'bytes' => ''];
    }
    $b64 = $data;
    if (str_starts_with($data, 'data:')) {
        $koma = strpos($data, ',');
        if ($koma === false) {
            return ['ok' => false, 'pesan' => 'Format gambar tanda tangan tidak dikenal.', 'bytes' => ''];
        }
        if (stripos(substr($data, 0, $koma), 'image/png') === false) {
            return ['ok' => false, 'pesan' => 'Gambar tanda tangan harus berformat PNG.', 'bytes' => ''];
        }
        $b64 = substr($data, $koma + 1);
    }
    $b64 = preg_replace('/\s+/', '', $b64) ?? '';
    $bytes = base64_decode($b64, true);
    if ($bytes === false || $bytes === '') {
        return ['ok' => false, 'pesan' => 'Gambar tanda tangan tidak dapat dibaca.', 'bytes' => ''];
    }
    return ['ok' => true, 'pesan' => '', 'bytes' => $bytes];
}
