<?php
/**
 * DAFTAR HADIR MEETING/PERTEMUAN PENGAWAS — e-GURU v1.73.0 / skema v55.
 *
 * Permintaan pemilik: *"Pada akun pengawas tambahkan menu aktifkan daftar hadir meeting
 * (pertemuan/zoom). Jadwal di atur dengan memasukkan tanggal pertemuan, judul pertemuan/meeting.
 * Pengawas bisa mengaktifkan daftar hadir binaan yang dia pilih atau seluruh binaannya. Ketika
 * diaktifkan maka akan muncul di seluruh akun binaannya untuk di tanda tangani secara online (tanda
 * tangan digital seperti di notulen rapat). Dokumen daftar hadir meeting/pertemuan ini akan menjadi
 * rekapan setiap pertemuan yang dilakukan oleh pengawas."*
 *
 * Pilihan pemilik (JANGAN diubah tanpa bertanya):
 *   1. yang menandatangani = **SEMUA akun lembaga** binaan (kepala, operator, guru);
 *   2. **pengawas juga menandatangani** sebagai **Pemimpin Meeting** (satu tanda tangan, berlaku
 *      untuk seluruh lembar binaan pada meeting itu);
 *   3. hasilnya **rekapan di layar** + **lembar cetak A4** (bisa Cetak/Simpan PDF).
 *
 * Model data (skema v55):
 *   - `meeting`        : satu baris = satu pertemuan/meeting (judul, tanggal, pembuat = pengawas,
 *                        + tanda tangan Pemimpin Meeting). GLOBAL oleh pengguna: TIDAK punya kolom
 *                        `sekolah_id` karena satu meeting dapat diaktifkan untuk banyak binaan.
 *   - `meeting_sekolah`: binaan mana saja yang daftar hadirnya DIAKTIFKAN (UNIQUE meeting+sekolah).
 *   - `meeting_hadir`  : tanda tangan tiap akun lembaga per (meeting × sekolah × pengguna).
 *
 * Tanda tangan memakai mekanisme yang SAMA dengan Notulen Rapat (v1.70.0): coretan pada <canvas>
 * dikirim sebagai PNG ke `upload_meeting_ttd.php`, diunggah lewat media proxy platform, dan yang
 * disimpan HANYA URL CDN-nya. Markup dialog & JSON `#ttdData` sengaja dibuat sama supaya
 * `initTtdSentuh()` (`assets/app.js`) dipakai apa adanya — jangan menulis skrip tanda tangan baru.
 *
 * HAK AKSES:
 *   - Membuat/mengaktifkan/menghapus meeting + menandatangani sebagai Pemimpin Meeting →
 *     **hanya akun pengawas pembuatnya** (`meeting.php`).
 *   - Melihat & menandatangani daftar hadir → **kepala, admin operator, dan guru** sekolah yang
 *     diaktifkan (`meeting_hadir.php`). Super Admin (dalam konteks sekolah) boleh MELIHAT & MENCETAK
 *     tetapi tidak menandatangani (bukan akun lembaga).
 *   - Lembar cetak (`cetak_meeting.php`) boleh dibuka pengawas pembuat + akun sekolah tersebut.
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
// Batas ukuran/format PNG tanda tangan & pembaca header PNG dipakai BERSAMA dengan Notulen Rapat
// (satu sumber kebenaran, jangan membuat batas kedua).
require_once __DIR__ . '/notulen_ttd.php';

/** Batas panjang judul pertemuan. */
const MEETING_JUDUL_MAKS = 160;

/** Peran akun lembaga yang menandatangani daftar hadir (permintaan pemilik). */
function meeting_peran_peserta(): array
{
    return ['kepala', 'operator', 'guru'];
}

/** Kunci "pihak" tanda tangan Pemimpin Meeting (pengawas) pada JSON tanda tangan. */
const MEETING_PIHAK_PEMIMPIN = 'pengawas';

/** Judul pertemuan yang sudah dirapikan (dipotong ke MEETING_JUDUL_MAKS). */
function meeting_judul_sah(string $judul): string
{
    $judul = trim(preg_replace('/\s+/u', ' ', $judul) ?? '');
    return strlen($judul) > MEETING_JUDUL_MAKS ? rtrim(substr($judul, 0, MEETING_JUDUL_MAKS)) : $judul;
}

/** Tanggal meeting yang sah (Y-m-d) atau '' bila kosong/tidak sah. */
function meeting_tanggal_sah(string $tanggal): string
{
    $tanggal = trim($tanggal);
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tanggal, $m) !== 1) {
        return '';
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $tanggal : '';
}

/** Daftar meeting milik satu pengawas (terbaru lebih dulu) + jumlah binaan & tanda tangan. */
function meeting_list_pengawas(int $pengawasId): array
{
    if ($pengawasId <= 0) {
        return [];
    }
    $baris = all('SELECT * FROM meeting WHERE pengawas_id = ? ORDER BY tanggal DESC, id DESC', [$pengawasId]);
    foreach ($baris as $i => $m) {
        $id = (int) $m['id'];
        $baris[$i]['jml_sekolah'] = (int) val('SELECT COUNT(*) FROM meeting_sekolah WHERE meeting_id = ?', [$id], 0);
        $baris[$i]['jml_hadir'] = (int) val('SELECT COUNT(*) FROM meeting_hadir WHERE meeting_id = ?', [$id], 0);
        $baris[$i]['pernah_ttd'] = trim((string) ($m['ttd_url'] ?? '')) !== '';
    }
    return $baris;
}

/** Satu baris meeting (== null bila tidak ada). */
function meeting_by_id(int $id): ?array
{
    return $id > 0 ? one('SELECT * FROM meeting WHERE id = ?', [$id]) : null;
}

/** Meeting milik pengawas tertentu (null bila bukan miliknya — anti-IDOR). */
function meeting_milik(int $id, int $pengawasId): ?array
{
    $m = meeting_by_id($id);
    if (!$m || (int) ($m['pengawas_id'] ?? 0) !== $pengawasId) {
        return null;
    }
    return $m;
}

/** ID sekolah/madrasah yang daftar hadirnya diaktifkan untuk satu meeting. */
function meeting_sekolah_ids(int $meetingId): array
{
    if ($meetingId <= 0) {
        return [];
    }
    $ids = [];
    foreach (all('SELECT sekolah_id FROM meeting_sekolah WHERE meeting_id = ? ORDER BY sekolah_id',
        [$meetingId]) as $r) {
        $ids[] = (int) $r['sekolah_id'];
    }
    return $ids;
}

/**
 * Baris sekolah/madrasah yang diaktifkan untuk satu meeting, lengkap dengan jumlah akun & jumlah
 * tanda tangan. Hanya sekolah yang masih ada (JOIN) — sekolah yang dihapus otomatis hilang (CASCADE).
 */
function meeting_sekolah_list(int $meetingId): array
{
    if ($meetingId <= 0) {
        return [];
    }
    $baris = all('SELECT s.*, ms.diaktifkan FROM meeting_sekolah ms
                  JOIN sekolah s ON s.id = ms.sekolah_id
                  WHERE ms.meeting_id = ?
                  ORDER BY s.nama COLLATE NOCASE', [$meetingId]);
    foreach ($baris as $i => $s) {
        $sid = (int) $s['id'];
        $baris[$i]['jumlah_akun'] = count(meeting_peserta($meetingId, $sid));
        $baris[$i]['jumlah_ttd'] = meeting_jumlah_hadir($meetingId, $sid);
    }
    return $baris;
}

/**
 * Membuat meeting baru beserta sekolah/madrasah yang diaktifkan.
 *
 * @param int[] $sekolahIds binaan yang dipilih (satu, beberapa, atau seluruh binaan)
 * @return array{ok: bool, pesan: string, id: int}
 */
function meeting_simpan(int $pengawasId, string $judul, string $tanggal, array $sekolahIds): array
{
    $judul = meeting_judul_sah($judul);
    $tanggal = meeting_tanggal_sah($tanggal);
    if ($pengawasId <= 0) {
        return ['ok' => false, 'pesan' => 'Akun pengawas tidak dikenal.', 'id' => 0];
    }
    if ($judul === '') {
        return ['ok' => false, 'pesan' => 'Judul pertemuan/meeting wajib diisi.', 'id' => 0];
    }
    if ($tanggal === '') {
        return ['ok' => false, 'pesan' => 'Tanggal pertemuan wajib diisi dengan tanggal yang sah.', 'id' => 0];
    }
    $sah = meeting_sekolah_binaan_sah($pengawasId, $sekolahIds);
    if (!$sah) {
        return ['ok' => false,
            'pesan' => 'Pilih minimal satu sekolah/madrasah binaan yang daftar hadirnya diaktifkan.',
            'id' => 0];
    }
    $u = current_user();
    $now = date('Y-m-d H:i:s');
    q('INSERT INTO meeting (pengawas_id, pengawas_nama, judul, tanggal, dibuat, diubah) VALUES (?,?,?,?,?,?)',
        [$pengawasId, (string) ($u['nama'] ?? ''), $judul, $tanggal, $now, $now]);
    $id = (int) db()->lastInsertId();
    meeting_sekolah_simpan($id, $sah);
    return ['ok' => true,
        'pesan' => 'Meeting "' . $judul . '" (' . tanggal_id($tanggal) . ') dibuat dan daftar hadirnya '
            . 'diaktifkan pada ' . count($sah) . ' sekolah/madrasah binaan.',
        'id' => $id];
}

/**
 * Menyaring & memvalidasi sekolah yang boleh diaktifkan: HANYA binaan aktif milik pengawas itu.
 *
 * @param int[] $ids id sekolah yang dikirim formulir
 * @return int[] id sekolah yang sah (urut & tanpa duplikat)
 */
function meeting_sekolah_binaan_sah(int $pengawasId, array $ids): array
{
    $boleh = [];
    foreach (sekolah_binaan($pengawasId, true) as $s) {
        $boleh[(int) $s['id']] = true;
    }
    $keluar = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0 && isset($boleh[$id]) && !in_array($id, $keluar, true)) {
            $keluar[] = $id;
        }
    }
    sort($keluar);
    return $keluar;
}

/**
 * Menyetel ULANG daftar binaan yang daftar hadirnya diaktifkan (tambah & lepas sekaligus).
 * Tanda tangan yang sudah ada TIDAK dihapus — bila binaannya diaktifkan kembali, tanda tangan
 * lamanya muncul lagi (riwayat tetap utuh).
 *
 * @param int[] $sekolahIds
 */
function meeting_sekolah_simpan(int $meetingId, array $sekolahIds): int
{
    if ($meetingId <= 0) {
        return 0;
    }
    $now = date('Y-m-d H:i:s');
    $lama = meeting_sekolah_ids($meetingId);
    foreach ($sekolahIds as $sid) {
        $sid = (int) $sid;
        if (!in_array($sid, $lama, true)) {
            q('INSERT OR IGNORE INTO meeting_sekolah (meeting_id, sekolah_id, diaktifkan) VALUES (?,?,?)',
                [$meetingId, $sid, $now]);
        }
    }
    foreach ($lama as $sid) {
        if (!in_array($sid, $sekolahIds, true)) {
            q('DELETE FROM meeting_sekolah WHERE meeting_id = ? AND sekolah_id = ?', [$meetingId, $sid]);
        }
    }
    q('UPDATE meeting SET diubah = ? WHERE id = ?', [$now, $meetingId]);
    return count($sekolahIds);
}

/** Menghapus meeting (hanya oleh pembuatnya). Tanda tangan & daftar binaan ikut terhapus (CASCADE). */
function meeting_hapus(int $meetingId, int $pengawasId): bool
{
    if (!meeting_milik($meetingId, $pengawasId)) {
        return false;
    }
    q('DELETE FROM meeting WHERE id = ?', [$meetingId]);
    return true;
}

/**
 * Menyimpan TANDA TANGAN PEMIMPIN MEETING (pengawas pembuat meeting).
 * Satu tanda tangan berlaku untuk SELURUH lembar binaan pada meeting itu.
 *
 * @return array{ok: bool, pesan: string, url: string}
 */
function meeting_ttd_pemimpin_simpan(int $meetingId, string $bytes): array
{
    $m = meeting_by_id($meetingId);
    if (!$m) {
        return ['ok' => false, 'pesan' => 'Meeting tidak ditemukan.', 'url' => ''];
    }
    $cek = meeting_png_sah($bytes);
    if (empty($cek['ok'])) {
        return ['ok' => false, 'pesan' => (string) $cek['pesan'], 'url' => ''];
    }
    $up = media_upload($bytes, 'ttd-meeting-pemimpin-' . $meetingId . '.png');
    if (empty($up['ok']) || (string) ($up['url'] ?? '') === '') {
        return ['ok' => false, 'pesan' => (string) ($up['pesan'] ?? 'Tanda tangan gagal diunggah.'), 'url' => ''];
    }
    $url = (string) $up['url'];
    q('UPDATE meeting SET ttd_url = ?, ttd_lebar = ?, ttd_tinggi = ?, ttd_pada = ?, diubah = ? WHERE id = ?',
        [$url, (int) $cek['lebar'], (int) $cek['tinggi'], date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $meetingId]);
    return ['ok' => true, 'pesan' => 'Tanda tangan Pemimpin Meeting tersimpan.', 'url' => $url];
}

/** Menghapus tanda tangan Pemimpin Meeting. */
function meeting_ttd_pemimpin_hapus(int $meetingId): bool
{
    $m = meeting_by_id($meetingId);
    if (!$m || trim((string) ($m['ttd_url'] ?? '')) === '') {
        return false;
    }
    q('UPDATE meeting SET ttd_url = NULL, ttd_lebar = 0, ttd_tinggi = 0, ttd_pada = NULL, diubah = ?
       WHERE id = ?', [date('Y-m-d H:i:s'), $meetingId]);
    return true;
}

/**
 * Validasi berkas PNG tanda tangan (ukuran + header PNG). Memakai batas yang SAMA dengan tanda
 * tangan Notulen Rapat supaya tidak ada dua aturan berbeda.
 *
 * @return array{ok: bool, pesan: string, lebar: int, tinggi: int}
 */
function meeting_png_sah(string $bytes): array
{
    $ukuran = strlen($bytes);
    if ($ukuran < 100) {
        return ['ok' => false, 'pesan' => 'Tanda tangan masih kosong — coretkan tanda tangan terlebih dahulu.',
            'lebar' => 0, 'tinggi' => 0];
    }
    if ($ukuran > NOTULEN_TTD_MAKS_BYTE) {
        return ['ok' => false, 'pesan' => 'Gambar tanda tangan terlalu besar (maksimal '
            . notulen_ttd_ukuran_teks(NOTULEN_TTD_MAKS_BYTE) . ').', 'lebar' => 0, 'tinggi' => 0];
    }
    $dim = notulen_ttd_png_ukuran($bytes);
    if ($dim === null) {
        return ['ok' => false, 'pesan' => 'Gambar tanda tangan harus berupa PNG yang sah.',
            'lebar' => 0, 'tinggi' => 0];
    }
    if ($dim['lebar'] < NOTULEN_TTD_MIN_PX || $dim['tinggi'] < NOTULEN_TTD_MIN_PX) {
        return ['ok' => false, 'pesan' => 'Gambar tanda tangan terlalu kecil.', 'lebar' => 0, 'tinggi' => 0];
    }
    if ($dim['lebar'] > NOTULEN_TTD_MAKS_PX || $dim['tinggi'] > NOTULEN_TTD_MAKS_PX) {
        return ['ok' => false, 'pesan' => 'Ukuran gambar tanda tangan tidak wajar.', 'lebar' => 0, 'tinggi' => 0];
    }
    return ['ok' => true, 'pesan' => '', 'lebar' => $dim['lebar'], 'tinggi' => $dim['tinggi']];
}

/**
 * Akun lembaga yang boleh menandatangani daftar hadir satu sekolah pada satu meeting
 * (kepala, admin operator, guru — akun aktif).
 */
function meeting_peserta(int $meetingId, int $sekolahId): array
{
    if ($meetingId <= 0 || $sekolahId <= 0) {
        return [];
    }
    $peran = meeting_peran_peserta();
    $tanda = implode(',', array_fill(0, count($peran), '?'));
    return all('SELECT * FROM users
                WHERE sekolah_id = ? AND aktif = 1 AND role IN (' . $tanda . ')
                ORDER BY CASE role WHEN "kepala" THEN 1 WHEN "operator" THEN 2 ELSE 3 END,
                         nama COLLATE NOCASE',
        array_merge([$sekolahId], $peran));
}

/** Peta tanda tangan peserta: user_id => baris `meeting_hadir`. */
function meeting_hadir_peta(int $meetingId, int $sekolahId): array
{
    if ($meetingId <= 0 || $sekolahId <= 0) {
        return [];
    }
    $peta = [];
    foreach (all('SELECT * FROM meeting_hadir WHERE meeting_id = ? AND sekolah_id = ?',
        [$meetingId, $sekolahId]) as $r) {
        $peta[(int) $r['user_id']] = $r;
    }
    return $peta;
}

/** Berapa akun yang sudah menandatangani (satu sekolah, atau seluruh binaan bila $sekolahId = 0). */
function meeting_jumlah_hadir(int $meetingId, int $sekolahId = 0): int
{
    if ($meetingId <= 0) {
        return 0;
    }
    if ($sekolahId > 0) {
        return (int) val('SELECT COUNT(*) FROM meeting_hadir WHERE meeting_id = ? AND sekolah_id = ?',
            [$meetingId, $sekolahId], 0);
    }
    return (int) val('SELECT COUNT(*) FROM meeting_hadir WHERE meeting_id = ?', [$meetingId], 0);
}

/** Baris tanda tangan satu akun (null bila belum). */
function meeting_hadir_baris(int $meetingId, int $sekolahId, int $userId): ?array
{
    if ($meetingId <= 0 || $sekolahId <= 0 || $userId <= 0) {
        return null;
    }
    return one('SELECT * FROM meeting_hadir WHERE meeting_id = ? AND sekolah_id = ? AND user_id = ?',
        [$meetingId, $sekolahId, $userId]);
}

/**
 * Menyimpan tanda tangan seorang peserta (akun lembaga) — menimpa tanda tangan lamanya bila
 * menandatangani ulang.
 *
 * @return array{ok: bool, pesan: string, url: string}
 */
function meeting_hadir_simpan(int $meetingId, int $sekolahId, array $user, string $bytes): array
{
    $uid = (int) ($user['id'] ?? 0);
    if ($uid <= 0) {
        return ['ok' => false, 'pesan' => 'Akun penanda tangan tidak dikenal.', 'url' => ''];
    }
    $cek = meeting_png_sah($bytes);
    if (empty($cek['ok'])) {
        return ['ok' => false, 'pesan' => (string) $cek['pesan'], 'url' => ''];
    }
    $up = media_upload($bytes, 'ttd-meeting-' . $meetingId . '-s' . $sekolahId . '-u' . $uid . '.png');
    if (empty($up['ok']) || (string) ($up['url'] ?? '') === '') {
        return ['ok' => false, 'pesan' => (string) ($up['pesan'] ?? 'Tanda tangan gagal diunggah.'), 'url' => ''];
    }
    $url = (string) $up['url'];
    $now = date('Y-m-d H:i:s');
    q('INSERT INTO meeting_hadir (meeting_id, sekolah_id, user_id, nama, username, peran,
                                  url, lebar, tinggi, ip, dibuat, diubah)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
       ON CONFLICT(meeting_id, sekolah_id, user_id) DO UPDATE SET url = excluded.url,
         lebar = excluded.lebar, tinggi = excluded.tinggi, nama = excluded.nama,
         username = excluded.username, peran = excluded.peran, ip = excluded.ip, diubah = excluded.diubah',
        [$meetingId, $sekolahId, $uid, (string) ($user['nama'] ?? ''), (string) ($user['username'] ?? ''),
         (string) ($user['role'] ?? ''), $url, (int) $cek['lebar'], (int) $cek['tinggi'], log_ip(), $now, $now]);
    return ['ok' => true, 'pesan' => 'Tanda tangan tersimpan.', 'url' => $url];
}

/** Menghapus tanda tangan satu peserta. */
function meeting_hadir_hapus(int $meetingId, int $sekolahId, int $userId): bool
{
    if (!meeting_hadir_baris($meetingId, $sekolahId, $userId)) {
        return false;
    }
    q('DELETE FROM meeting_hadir WHERE meeting_id = ? AND sekolah_id = ? AND user_id = ?',
        [$meetingId, $sekolahId, $userId]);
    return true;
}

/**
 * Meeting yang daftar hadirnya SUDAH DIAKTIFKAN untuk sebuah sekolah (terbaru lebih dulu).
 * Inilah yang tampil pada akun kepala/operator/guru sekolah itu.
 */
function meeting_untuk_sekolah(int $sekolahId): array
{
    if ($sekolahId <= 0) {
        return [];
    }
    $baris = all('SELECT m.*, ms.diaktifkan FROM meeting_sekolah ms
                  JOIN meeting m ON m.id = ms.meeting_id
                  WHERE ms.sekolah_id = ?
                  ORDER BY m.tanggal DESC, m.id DESC', [$sekolahId]);
    foreach ($baris as $i => $m) {
        $baris[$i]['jumlah_akun'] = count(meeting_peserta((int) $m['id'], $sekolahId));
        $baris[$i]['jumlah_ttd'] = meeting_jumlah_hadir((int) $m['id'], $sekolahId);
    }
    return $baris;
}

/** Berapa meeting aktif untuk sekolah ini yang BELUM ditandatangani pengguna tertentu. */
function meeting_belum_ttd(int $sekolahId, int $userId): int
{
    if ($sekolahId <= 0 || $userId <= 0) {
        return 0;
    }
    return (int) val('SELECT COUNT(*) FROM meeting_sekolah ms
                      JOIN meeting m ON m.id = ms.meeting_id
                      WHERE ms.sekolah_id = ?
                        AND NOT EXISTS (SELECT 1 FROM meeting_hadir h
                                        WHERE h.meeting_id = m.id AND h.sekolah_id = ms.sekolah_id
                                          AND h.user_id = ?)', [$sekolahId, $userId], 0);
}

/** Apakah daftar hadir satu meeting diaktifkan untuk sekolah tertentu. */
function meeting_aktif_untuk(int $meetingId, int $sekolahId): bool
{
    if ($meetingId <= 0 || $sekolahId <= 0) {
        return false;
    }
    return (bool) one('SELECT 1 FROM meeting_sekolah WHERE meeting_id = ? AND sekolah_id = ?',
        [$meetingId, $sekolahId]);
}

/**
 * Daftar "pihak" penanda tangan untuk JSON `#ttdData` + label pada dialog.
 *
 * @return array<string, array{label: string, nama: string, jenis: string}>
 */
function meeting_pihak_peta(int $meetingId, int $sekolahId): array
{
    $m = meeting_by_id($meetingId);
    if (!$m) {
        return [];
    }
    $peta = [MEETING_PIHAK_PEMIMPIN => [
        'label' => 'Pemimpin Meeting',
        'nama' => (string) ($m['pengawas_nama'] ?? ''),
        'jenis' => 'pemimpin',
    ]];
    foreach (meeting_peserta($meetingId, $sekolahId) as $p) {
        $peta['u' . (int) $p['id']] = [
            'label' => (string) $p['nama'],
            'nama' => (string) $p['nama'],
            'jenis' => 'hadir',
        ];
    }
    return $peta;
}

/** JSON untuk `#ttdData` (dibaca `initTtdSentuh()` di assets/app.js). */
function meeting_ttd_json(int $meetingId, int $sekolahId, array $pihak, array $petaHadir): string
{
    $data = ['id' => $meetingId, 'pihak' => [], 'endpoint' => 'upload_meeting_ttd.php'];
    $m = meeting_by_id($meetingId);
    foreach ($pihak as $kunci => $info) {
        $ada = false;
        if ($kunci === MEETING_PIHAK_PEMIMPIN) {
            $ada = $m !== null && trim((string) ($m['ttd_url'] ?? '')) !== '';
        } else {
            $ada = isset($petaHadir[(int) substr($kunci, 1)]);
        }
        $data['pihak'][$kunci] = ['label' => (string) $info['label'],
            'nama' => (string) $info['nama'], 'ada' => $ada];
    }
    return (string) json_encode($data,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * Dialog tanda tangan (kanvas layar sentuh) — markup SENGAJA sama dengan dialog Notulen Rapat
 * (`notulen_ttd_dialog()`) supaya `initTtdSentuh()` bekerja tanpa perubahan. Yang berbeda hanya
 * nilai `<input name="id">` (id meeting) dan judulnya.
 */
function meeting_ttd_dialog(int $meetingId): string
{
    ob_start();
    ?>
<dialog class="ttd-dialog" id="ttdDialog" aria-labelledby="ttdJudul">
  <form method="dialog" class="ttd-bingkai" id="ttdForm">
    <div class="ttd-kepala">
      <div>
        <h3 id="ttdJudul">Tanda Tangan</h3>
        <p class="ttd-ket" id="ttdSub">Tanda tangan langsung pada layar menggunakan jari atau stylus.</p>
      </div>
      <button type="button" class="btn btn-ghost btn-sm" id="ttdTutup" aria-label="Tutup">Tutup</button>
    </div>
    <div class="ttd-kanvas-bungkus">
      <canvas id="ttdKanvas" width="800" height="300" aria-label="Area tanda tangan"></canvas>
      <span class="ttd-garis-bantu" aria-hidden="true"></span>
    </div>
    <p class="ttd-petunjuk">Coretkan tanda tangan di dalam kotak. Tekan <b>Hapus</b> untuk mengulang,
      lalu <b>Simpan</b> bila sudah sesuai.</p>
    <div class="ttd-tombol-baris">
      <button type="button" class="btn btn-ghost" id="ttdHapus">Hapus</button>
      <button type="button" class="btn btn-ghost" id="ttdHapusTersimpan">Hapus Tersimpan</button>
      <button type="button" class="btn btn-primary" id="ttdSimpan">Simpan Tanda Tangan</button>
    </div>
    <p class="ttd-pesan" id="ttdPesan" role="status" aria-live="polite"></p>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $meetingId ?>">
    <input type="hidden" name="pihak" id="ttdPihak" value="">
  </form>
</dialog>
    <?php
    return (string) ob_get_clean();
}

/** HTML gambar tanda tangan ('' bila belum ada). */
function meeting_ttd_gambar(?string $url, string $oleh = '', string $kelas = 'ttd-img'): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    return '<img class="' . e($kelas) . '" src="' . e($url) . '" alt="Tanda tangan'
        . ($oleh !== '' ? ' ' . e($oleh) : '') . '">';
}

/** Tombol pembuka dialog tanda tangan (disembunyikan skrip pada perangkat tanpa layar sentuh). */
function meeting_ttd_tombol(string $pihak, bool $sudahAda = false): string
{
    return '<button type="button" class="ttd-tombol" data-ttd-tombol="' . e($pihak) . '" hidden>'
        . ($sudahAda ? 'Ulangi' : 'Tanda Tangan') . '</button>';
}

/** Ringkasan jumlah tanda tangan seluruh meeting milik pengawas (untuk lencana menu). */
function meeting_jumlah_pengawas(int $pengawasId): int
{
    if ($pengawasId <= 0) {
        return 0;
    }
    return (int) val('SELECT COUNT(*) FROM meeting WHERE pengawas_id = ?', [$pengawasId], 0);
}

// ================================================================================================
// HALAMAN PENGAWAS — membuat meeting, mengaktifkan daftar hadir binaan, tanda tangan pemimpin,
// dan rekapan tiap pertemuan.
// ================================================================================================
function meeting_halaman_pengawas(): void
{
    $u = require_login();
    if (!is_pengawas()) {
        tolak403('Menu Daftar Hadir Meeting ini khusus akun Pengawas Sekolah/Madrasah.');
    }
    $uid = (int) $u['id'];

    $binaan = sekolah_binaan($uid, true);
    $namaBinaan = [];
    foreach ($binaan as $s) {
        $namaBinaan[(int) $s['id']] = (string) $s['nama'];
    }

    // ---------------------------------------------------------------- POST
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);

        if ($aksi === 'simpan') {
            $pilih = (array) ($_POST['sekolah'] ?? []);
            if (isset($_POST['seluruh'])) {
                $pilih = array_keys($namaBinaan);   // "Seluruh binaan" (tanpa perlu JavaScript)
            }
            $hasil = meeting_simpan($uid, (string) ($_POST['judul'] ?? ''),
                (string) ($_POST['tanggal'] ?? ''), $pilih);
            flash($hasil['pesan'], !empty($hasil['ok']) ? 'ok' : 'err');
            redirect($hasil['id'] > 0 ? 'meeting.php?id=' . (int) $hasil['id'] : 'meeting.php');
        }

        $m = $id > 0 ? meeting_milik($id, $uid) : null;
        if (!$m) {
            flash('Meeting tidak ditemukan pada akun Anda.', 'err');
            redirect('meeting.php');
        }

        if ($aksi === 'aktifkan') {
            $pilih = (array) ($_POST['sekolah'] ?? []);
            if (isset($_POST['seluruh'])) {
                $pilih = array_keys($namaBinaan);
            }
            $sah = meeting_sekolah_binaan_sah($uid, $pilih);
            if (!$sah) {
                flash('Pilih minimal satu sekolah/madrasah binaan yang daftar hadirnya diaktifkan.', 'err');
                redirect('meeting.php?id=' . $id);
            }
            meeting_sekolah_simpan($id, $sah);
            flash('Daftar hadir meeting "' . (string) $m['judul'] . '" diaktifkan pada '
                . count($sah) . ' sekolah/madrasah binaan.', 'ok');
            redirect('meeting.php?id=' . $id);
        }

        if ($aksi === 'hapus') {
            meeting_hapus($id, $uid);
            flash('Meeting "' . (string) $m['judul'] . '" dihapus beserta seluruh tanda tangannya.', 'warn');
            redirect('meeting.php');
        }

        redirect('meeting.php');
    }

    // ---------------------------------------------------------------- tampilan
    $idLihat = (int) ($_GET['id'] ?? 0);
    $lihat = $idLihat > 0 ? meeting_milik($idLihat, $uid) : null;
    if ($idLihat > 0 && !$lihat) {
        flash('Meeting tidak ditemukan pada akun Anda.', 'err');
        redirect('meeting.php');
    }

    $daftar = meeting_list_pengawas($uid);
    $binaanSekolah = meeting_sekolah_list($idLihat);
    $diaktifkan = [];
    foreach ($binaanSekolah as $s) {
        $diaktifkan[(int) $s['id']] = true;
    }
    $gambarPemimpin = $lihat !== null ? trim((string) ($lihat['ttd_url'] ?? '')) : '';

    page_top($lihat !== null ? 'Daftar Hadir Meeting' : 'Daftar Hadir Meeting',
        $lihat !== null ? 'Rincian Pertemuan' : 'Aktifkan & pantau daftar hadir pertemuan binaan');
    ?>
<?php if ($lihat === null): ?>
  <div class="card">
    <div class="card-head">
      <div>
        <h2>Buat Daftar Hadir Meeting</h2>
        <p>Isi <b>tanggal pertemuan</b> dan <b>judul pertemuan/meeting</b>, lalu tentukan binaan yang
          daftar hadirnya diaktifkan. Setelah diaktifkan, daftar hadir itu muncul pada akun
          kepala sekolah/madrasah, admin operator, dan guru binaan untuk ditandatangani secara online.</p>
      </div>
      <span class="chip"><?= count($daftar) ?> meeting</span>
    </div>

    <?php if (!$binaan): ?>
      <div class="alert alert-warn" style="margin-bottom:16px">
        <?= ikon('info', 16) ?>
        <span>Akun Anda belum memiliki sekolah/madrasah binaan yang aktif, sehingga daftar hadir belum
          dapat diaktifkan. Hubungi Admin aplikasi untuk menetapkan binaan Anda.</span>
      </div>
    <?php endif; ?>

    <form method="post" id="formMeeting">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <div class="form-grid">
        <div>
          <label class="f" for="judul">Judul Pertemuan/Meeting *</label>
          <input type="text" id="judul" name="judul" maxlength="<?= MEETING_JUDUL_MAKS ?>" required
                 placeholder="mis. Rapat Koordinasi Supervisi Akademik (Zoom Meeting)">
        </div>
        <div>
          <label class="f" for="tanggal">Tanggal Pertemuan *</label>
          <input type="date" id="tanggal" name="tanggal" required value="<?= e(date('Y-m-d')) ?>">
        </div>
        <div>
          <label class="f">Binaan yang Daftar Hadirnya Diaktifkan</label>
          <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600;margin-bottom:8px">
            <input type="checkbox" name="seluruh" value="1" style="width:16px;height:16px;accent-color:var(--accent)">
            Seluruh binaan (<?= count($binaan) ?> sekolah/madrasah)
          </label>
          <div class="meeting-binaan">
            <?php foreach ($binaan as $s): ?>
              <label class="meeting-binaan-item">
                <input type="checkbox" name="sekolah[]" value="<?= (int) $s['id'] ?>"
                       style="width:16px;height:16px;accent-color:var(--accent)">
                <span><?= e(tetap_kata(nama_sekolah_tampil($s))) ?></span>
              </label>
            <?php endforeach; ?>
            <?php if (!$binaan): ?><p class="hint" style="margin:0">Belum ada binaan aktif.</p><?php endif; ?>
          </div>
          <div class="hint">Centang satu atau beberapa binaan, atau centang <b>Seluruh binaan</b>.
            Bila <b>Seluruh binaan</b> dicentang, pilihan di bawahnya diabaikan.</div>
        </div>
        <div class="flex">
          <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Buat &amp; Aktifkan Daftar Hadir</button>
        </div>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h2>Rekapan Pertemuan</h2>
        <p>Setiap baris = satu pertemuan. Klik <b>Rincian</b> untuk melihat &amp; mencetak daftar hadir
          tiap binaan, atau membuka/menutup binaan yang diaktifkan.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table class="tbl" id="tblMeeting">
        <thead><tr>
          <th class="ctr" style="width:34px">No</th>
          <th>Tanggal</th>
          <th>Judul Pertemuan/Meeting</th>
          <th class="ctr">Binaan</th>
          <th class="ctr">Sudah Tanda Tangan</th>
          <th class="ctr">Pemimpin</th>
          <th class="ctr">Aksi</th>
        </tr></thead>
        <tbody>
        <?php $n = 0; foreach ($daftar as $m): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td class="nowrap"><?= e(tanggal_id((string) $m['tanggal'], true)) ?></td>
            <td><b><?= tetap_kata((string) $m['judul']) ?></b></td>
            <td class="ctr"><?= (int) $m['jml_sekolah'] ?></td>
            <td class="ctr"><b><?= (int) $m['jml_hadir'] ?></b>
              <span class="tbl-sub">akun lembaga</span></td>
            <td class="ctr">
              <?php if (!empty($m['pernah_ttd'])): ?>
                <span class="pill pill-ok">Sudah</span>
              <?php else: ?>
                <span class="pill pill-warn">Belum</span>
              <?php endif; ?>
            </td>
            <td class="ctr">
              <a class="btn btn-ghost btn-sm" href="meeting.php?id=<?= (int) $m['id'] ?>"><?= ikon('eye', 14) ?> Rincian</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$daftar): ?>
          <tr><td colspan="7" class="ctr mut">Belum ada meeting. Buat meeting pertama pada formulir di atas.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php else: ?>
  <?php
  // ---------------------------------------------------------------- RINCIAN satu meeting
  $jmlAkun = 0;
  $jmlTtd = 0;
  foreach ($binaanSekolah as $s) {
      $jmlAkun += (int) $s['jumlah_akun'];
      $jmlTtd += (int) $s['jumlah_ttd'];
  }
  ?>
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= tetap_kata((string) $lihat['judul']) ?></h2>
        <p>Pertemuan <b><?= e(tanggal_id((string) $lihat['tanggal'], true)) ?></b> ·
          daftar hadir aktif pada <b><?= count($binaanSekolah) ?></b> sekolah/madrasah binaan ·
          <b><?= (int) $jmlTtd ?></b> dari <?= (int) $jmlAkun ?> akun lembaga sudah menandatangani.</p>
      </div>
      <div class="flex">
        <a class="btn btn-ghost btn-sm" href="meeting.php"><?= ikon('prev', 15) ?> Daftar Meeting</a>
        <?php if ($binaanSekolah): ?>
          <a class="btn btn-primary btn-sm" href="cetak_meeting.php?id=<?= (int) $lihat['id'] ?>">
            <?= ikon('print', 15) ?> Cetak Semua Binaan</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid g21">
      <div>
        <div class="section-title" style="margin-top:0">Tanda Tangan Pemimpin Meeting</div>
        <p class="hint" style="margin-top:-4px">Satu tanda tangan berlaku untuk lembar daftar hadir
          <b>seluruh binaan</b> pada pertemuan ini. Tanda tangan langsung pada layar (jari atau stylus).</p>
        <div class="meeting-pemimpin" data-ttd-sel="<?= e(MEETING_PIHAK_PEMIMPIN) ?>">
          <?= meeting_ttd_gambar($gambarPemimpin, (string) ($lihat['pengawas_nama'] ?? '')) ?>
          <?= meeting_ttd_tombol(MEETING_PIHAK_PEMIMPIN, $gambarPemimpin !== '') ?>
        </div>
        <p class="hint xs" id="ttdCatatan" hidden>Tanda tangan hanya muncul pada perangkat berlayar sentuh
          (HP/tablet). Bila memakai komputer, minta tanda tangan lewat HP.</p>
        <p class="xs mut" style="margin-top:10px">
          <?= $gambarPemimpin !== ''
              ? 'Ditandatangani ' . e((string) ($lihat['pengawas_nama'] ?? '')) . ' pada '
                . e(waktu_id_polos((string) ($lihat['ttd_pada'] ?? '')))
              : 'Belum ditandatangani.' ?>
        </p>
      </div>
      <div>
        <div class="section-title" style="margin-top:0">Aktifkan / Tutup Binaan</div>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="aktifkan">
          <input type="hidden" name="id" value="<?= (int) $lihat['id'] ?>">
          <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600;margin-bottom:8px">
            <input type="checkbox" name="seluruh" value="1" style="width:16px;height:16px;accent-color:var(--accent)">
            Seluruh binaan
          </label>
          <div class="meeting-binaan">
            <?php foreach ($binaan as $s): ?>
              <label class="meeting-binaan-item">
                <input type="checkbox" name="sekolah[]" value="<?= (int) $s['id'] ?>"
                       style="width:16px;height:16px;accent-color:var(--accent)"
                       <?= isset($diaktifkan[(int) $s['id']]) ? 'checked' : '' ?>>
                <span><?= e(tetap_kata(nama_sekolah_tampil($s))) ?></span>
              </label>
            <?php endforeach; ?>
            <?php if (!$binaan): ?><p class="hint" style="margin:0">Belum ada binaan aktif.</p><?php endif; ?>
          </div>
          <div class="hint">Menutup centang sebuah binaan menyembunyikan daftar hadir dari akun binaan itu;
            tanda tangan yang sudah ada tetap tersimpan.</div>
          <div class="flex mt12">
            <button class="btn btn-primary btn-sm" type="submit"><?= ikon('save', 15) ?> Simpan Pilihan Binaan</button>
          </div>
        </form>
        <form method="post" class="mt12"
              data-confirm="Hapus meeting ini beserta seluruh tanda tangannya? Tindakan ini tidak dapat dibatalkan.">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="hapus">
          <input type="hidden" name="id" value="<?= (int) $lihat['id'] ?>">
          <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 15) ?> Hapus Meeting</button>
        </form>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h2>Daftar Hadir per Binaan</h2>
        <p>Klik <b>Cetak</b> untuk mencetak lembar daftar hadir (A4) binaan tersebut — sudah memuat
          tanda tangan yang masuk beserta tanda tangan Pemimpin Meeting.</p>
      </div>
    </div>
    <div class="table-wrap">
      <table class="tbl" id="tblMeetingBinaan">
        <thead><tr>
          <th class="ctr" style="width:34px">No</th>
          <th>Sekolah/Madrasah</th>
          <th class="ctr">Akun Lembaga</th>
          <th class="ctr">Sudah Tanda Tangan</th>
          <th class="ctr">Aksi</th>
        </tr></thead>
        <tbody>
        <?php $n = 0; foreach ($binaanSekolah as $s): $n++; $sid = (int) $s['id']; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td>
              <b><?= e(tetap_kata(nama_sekolah_tampil($s))) ?></b>
              <span class="tbl-sub">diaktifkan <?= e(waktu_id_polos((string) ($s['diaktifkan'] ?? ''))) ?></span>
            </td>
            <td class="ctr"><?= (int) $s['jumlah_akun'] ?></td>
            <td class="ctr"><b><?= (int) $s['jumlah_ttd'] ?></b> / <?= (int) $s['jumlah_akun'] ?></td>
            <td class="ctr">
              <a class="btn btn-ghost btn-sm" href="cetak_meeting.php?id=<?= (int) $lihat['id'] ?>&sekolah=<?= $sid ?>">
                <?= ikon('print', 14) ?> Cetak</a>
            </td>
          </tr>
          <tr>
            <td></td>
            <td colspan="4">
              <details class="meeting-tanda">
                <summary class="xs">Lihat daftar akun &amp; status tanda tangan</summary>
                <?php $peserta = meeting_peserta((int) $lihat['id'], $sid); $peta = meeting_hadir_peta((int) $lihat['id'], $sid); ?>
                <?php if (!$peserta): ?>
                  <p class="hint" style="margin:8px 0 0">Belum ada akun kepala/operator/guru aktif pada
                    sekolah/madrasah ini.</p>
                <?php else: ?>
                  <ul class="meeting-hadir-list">
                    <?php foreach ($peserta as $p): $baris = $peta[(int) $p['id']] ?? null; ?>
                      <li>
                        <span><?= e((string) $p['nama']) ?>
                          <span class="xs mut"><?= e(role_label((string) $p['role'])) ?></span></span>
                        <?php if ($baris): ?>
                          <span class="pill pill-ok">Sudah</span>
                          <span class="xs mut"><?= e(waktu_id_polos((string) $baris['dibuat'])) ?></span>
                        <?php else: ?>
                          <span class="pill pill-gray">Belum</span>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$binaanSekolah): ?>
          <tr><td colspan="5" class="ctr mut">Belum ada binaan yang diaktifkan. Centang binaan pada
            kartu "Aktifkan / Tutup Binaan" di atas.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php
  // Skrip tanda tangan: dipakai apa adanya oleh initTtdSentuh(). Pihak yang tersedia = Pemimpin
  // Meeting + peserta binaan pertama yang diaktifkan (label dialog saja; tombol hanya dicetak pada
  // sel Pemimpin Meeting di atas).
  $sekolahJson = $binaanSekolah ? (int) $binaanSekolah[0]['id'] : 0;
  $pihakJson = meeting_pihak_peta((int) $lihat['id'], $sekolahJson);
  $petaJson = $sekolahJson > 0 ? meeting_hadir_peta((int) $lihat['id'], $sekolahJson) : [];
  ?>
  <script type="application/json" id="ttdData"><?= meeting_ttd_json((int) $lihat['id'], $sekolahJson, $pihakJson, $petaJson) ?></script>
  <?= meeting_ttd_dialog((int) $lihat['id']) ?>
<?php endif; ?>
    <?php
    page_bottom();
}

// ================================================================================================
// HALAMAN AKUN LEMBAGA (kepala, admin operator, guru) — daftar hadir meeting binaannya + tanda tangan
// ================================================================================================
function meeting_halaman_sekolah(): void
{
    $u = require_login();
    $r = peran();
    $super = is_super();
    if (!$super && !in_array($r, meeting_peran_peserta(), true)) {
        tolak403('Daftar hadir meeting hanya dapat dibuka akun kepala sekolah/madrasah, admin operator, '
            . 'dan guru.');
    }
    if (!current_sekolah()) {
        require_sekolah();   // Super Admin tanpa konteks → halaman "pilih sekolah"
        return;
    }
    $sekolah = current_sekolah();
    $sid = (int) $sekolah['id'];
    $uid = (int) $u['id'];

    $daftar = meeting_untuk_sekolah($sid);
    $idLihat = (int) ($_GET['id'] ?? 0);
    $lihat = null;
    foreach ($daftar as $m) {
        if ((int) $m['id'] === $idLihat) {
            $lihat = $m;
            break;
        }
    }
    if ($idLihat > 0 && !$lihat) {
        flash('Daftar hadir meeting itu tidak tersedia untuk sekolah/madrasah Anda.', 'err');
        redirect('meeting_hadir.php');
    }

    $peserta = $lihat !== null ? meeting_peserta((int) $lihat['id'], $sid) : [];
    $peta = $lihat !== null ? meeting_hadir_peta((int) $lihat['id'], $sid) : [];
    $milikSaya = $lihat !== null ? ($peta[$uid] ?? null) : null;
    $pihak = $lihat !== null ? meeting_pihak_peta((int) $lihat['id'], $sid) : [];

    page_top('Daftar Hadir Meeting',
        $lihat !== null ? 'Tanda Tangan Online' : 'Pertemuan Pengawas Bina');
    ?>
<?php if ($lihat === null): ?>
  <div class="card">
    <div class="card-head">
      <div>
        <h2>Daftar Hadir Meeting Pengawas Bina</h2>
        <p>Daftar hadir pertemuan/meeting yang <b>diaktifkan pengawas bina</b> untuk
          <?= e(tetap_kata(nama_sekolah_tampil($sekolah))) ?>. Buka salah satunya untuk menandatangani
          secara online (tanda tangan langsung pada layar sentuh).</p>
      </div>
      <span class="chip"><?= count($daftar) ?> meeting</span>
    </div>
    <?php if (!$daftar): ?>
      <div class="empty">
        <?= ikon('calendar', 34) ?>
        <h3>Belum ada daftar hadir meeting</h3>
        <p>Pengawas bina belum mengaktifkan daftar hadir pertemuan untuk sekolah/madrasah ini.
          Daftar hadir akan muncul di sini begitu diaktifkan.</p>
      </div>
    <?php else: ?>
      <div class="grid g2 meeting-kartu-grid">
        <?php foreach ($daftar as $m): $sudahSaya = meeting_hadir_baris((int) $m['id'], $sid, $uid) !== null; ?>
          <div class="card meeting-kartu">
            <div class="meeting-kartu-head">
              <div>
                <div class="xs mut"><?= e(tanggal_id((string) $m['tanggal'], true)) ?></div>
                <h3><?= tetap_kata((string) $m['judul']) ?></h3>
                <div class="xs mut">Pengawas bina: <?= e((string) ($m['pengawas_nama'] ?? '-')) ?></div>
              </div>
              <?php if ($sudahSaya): ?>
                <span class="pill pill-ok">Sudah tanda tangan</span>
              <?php else: ?>
                <span class="pill pill-warn">Belum tanda tangan</span>
              <?php endif; ?>
            </div>
            <div class="meeting-kartu-kaki">
              <span class="xs mut"><?= (int) $m['jumlah_ttd'] ?> dari <?= (int) $m['jumlah_akun'] ?> akun
                sudah menandatangani</span>
              <a class="btn btn-primary btn-sm" href="meeting_hadir.php?id=<?= (int) $m['id'] ?>">
                <?= ikon('edit', 14) ?> Buka &amp; Tanda Tangan</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= tetap_kata((string) $lihat['judul']) ?></h2>
        <p>Pertemuan <b><?= e(tanggal_id((string) $lihat['tanggal'], true)) ?></b> ·
          pemimpin meeting <b><?= e((string) ($lihat['pengawas_nama'] ?? '-')) ?></b> ·
          <?= count($peta) ?> dari <?= count($peserta) ?> akun lembaga sudah menandatangani.</p>
      </div>
      <div class="flex">
        <a class="btn btn-ghost btn-sm" href="meeting_hadir.php"><?= ikon('prev', 15) ?> Daftar Meeting</a>
        <a class="btn btn-primary btn-sm" href="cetak_meeting.php?id=<?= (int) $lihat['id'] ?>&sekolah=<?= $sid ?>">
          <?= ikon('print', 15) ?> Cetak Daftar Hadir</a>
      </div>
    </div>

    <?php if (!$super && !$milikSaya): ?>
      <div class="alert alert-warn" style="margin-bottom:16px">
        <?= ikon('edit', 16) ?>
        <span>Anda belum menandatangani daftar hadir ini. Tekan <b>Tanda Tangan</b> pada baris nama Anda
          di bawah — tanda tangan dibuat langsung pada layar sentuh (jari atau stylus).</span>
      </div>
    <?php endif; ?>

    <div class="table-wrap">
      <table class="tbl" id="tblHadirMeeting">
        <thead><tr>
          <th class="ctr" style="width:40px">No</th>
          <th>Nama</th>
          <th>Jabatan</th>
          <th class="ctr" style="width:34%">Tanda Tangan</th>
        </tr></thead>
        <tbody>
        <?php $n = 0; foreach ($peserta as $p): $n++; $pu = (int) $p['id']; $baris = $peta[$pu] ?? null; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td>
              <b><?= e((string) $p['nama']) ?></b>
              <?php if ($pu === $uid): ?><span class="pill pill-ok">Anda</span><?php endif; ?>
            </td>
            <td><?= e(role_label((string) $p['role'])) ?></td>
            <td class="ctr ttd-sel" data-ttd-sel="u<?= $pu ?>">
              <?= meeting_ttd_gambar((string) ($baris['url'] ?? ''), (string) $p['nama']) ?>
              <?php if ($pu === $uid && !$super): ?>
                <?= meeting_ttd_tombol('u' . $pu, $baris !== null) ?>
              <?php elseif ($baris === null): ?>
                <span class="xs mut">belum</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$peserta): ?>
          <tr><td colspan="4" class="ctr mut">Belum ada akun kepala sekolah/madrasah, admin operator,
            atau guru aktif pada sekolah/madrasah ini.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="meeting-pemimpin-bawah">
      <div class="meeting-pemimpin-teks">
        <div class="xs mut">Pemimpin Meeting</div>
        <?php $gambarP = trim((string) ($lihat['ttd_url'] ?? '')); ?>
        <?= meeting_ttd_gambar($gambarP, (string) ($lihat['pengawas_nama'] ?? ''), 'ttd-img ttd-pemimpin') ?>
        <div><b><?= e((string) ($lihat['pengawas_nama'] ?? '-')) ?></b></div>
        <div class="xs mut"><?= e(role_label('pengawas')) ?></div>
      </div>
    </div>

    <p class="hint" id="ttdCatatan" hidden>Tanda tangan hanya muncul pada perangkat berlayar sentuh
      (HP/tablet). Bila memakai komputer, buka halaman ini lewat HP.</p>

    <script type="application/json" id="ttdData"><?= meeting_ttd_json((int) $lihat['id'], $sid, $pihak, $peta) ?></script>
    <?= meeting_ttd_dialog((int) $lihat['id']) ?>
  </div>
<?php endif; ?>
    <?php
    page_bottom();
}
