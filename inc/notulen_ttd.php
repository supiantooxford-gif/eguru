<?php
/**
 * TANDA TANGAN DIGITAL pada Notulen Rapat — e-GURU v1.70.0 / skema v50.
 *
 * Permintaan pemilik: *"Pada notulen rapat buat tanda tangan langsung lewat HP seperti aplikasi
 * TUSmart"*. Sama seperti TUSmart: nama-nama pada **Daftar Hadir Rapat** (juga Kepala
 * Sekolah/Madrasah, Pimpinan Rapat, dan Notulis pada blok tanda tangan lembar cetak) dapat
 * membubuhkan tanda tangan **langsung di layar sentuh** — coretan jari/stylus pada <canvas>
 * dikirim sebagai PNG ke `upload_notulen_ttd.php`, lalu yang disimpan di tabel `notulen_ttd`
 * HANYA URL CDN hasil media proxy platform (bukan berkas di server aplikasi).
 *
 * Tombol "Tanda Tangan" disembunyikan sejak HTML (`hidden`) dan hanya dimunculkan oleh skrip bila
 * perangkat benar-benar memiliki layar sentuh, sehingga komputer biasa tetap mencetak lembar dengan
 * kolom tanda tangan kosong seperti sebelumnya.
 *
 * Modul ini dipakai bersama oleh `cetak_notulen.php` (lembar cetak) dan handler unggahannya.
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/** Batas ukuran PNG tanda tangan (byte). */
const NOTULEN_TTD_MAKS_BYTE = 2 * 1024 * 1024;

/** Ukuran minimum / maksimum sisi gambar tanda tangan (piksel) — menolak gambar ngawur. */
const NOTULEN_TTD_MIN_PX = 24;
const NOTULEN_TTD_MAKS_PX = 3000;

/** Keterangan ukuran berkas yang ramah dibaca ("2 MB"). */
function notulen_ttd_ukuran_teks(int $byte): string
{
    if ($byte >= 1048576) {
        return number_format($byte / 1048576, 0, ',', '.') . ' MB';
    }
    if ($byte >= 1024) {
        return number_format($byte / 1024, 0, ',', '.') . ' KB';
    }
    return $byte . ' byte';
}

/**
 * Daftar pihak yang boleh menandatangani notulen tertentu.
 *
 * @return array<string, array{label: string, nama: string, jenis: string}>
 *   kunci = 'kepala' | 'pimpinan' | 'notulis' | 'hadir:g<guru_id>' (cadangan 'hadir:h<id>')
 */
function notulen_ttd_pihak(array $notulen, array $peserta): array
{
    $pihak = [];
    $pimpinan = trim((string) ($notulen['pimpinan'] ?? ''));
    $notulis = trim((string) ($notulen['notulis'] ?? ''));
    $pihak['kepala'] = [
        'label' => 'Kepala ' . istilah_sekolah('Sekolah'),
        'nama' => kepala_nama(),
        'jenis' => 'kepala',
    ];
    // Pimpinan & Notulis hanya ditawarkan bila namanya diisi pada notulen.
    if ($pimpinan !== '') {
        $pihak['pimpinan'] = ['label' => 'Pimpinan Rapat', 'nama' => $pimpinan, 'jenis' => 'pimpinan'];
    }
    if ($notulis !== '') {
        $pihak['notulis'] = ['label' => 'Notulis', 'nama' => $notulis, 'jenis' => 'notulis'];
    }
    foreach ($peserta as $p) {
        // Kunci peserta memakai ID GURU (bukan ID baris daftar hadir): saat notulen diubah, baris
        // `notulen_hadir` dihapus & dibuat ulang sehingga ID-nya berganti — dengan ID guru, tanda
        // tangan yang sudah ada TETAP menempel pada orang yang sama.
        $gid = (int) ($p['guru_id'] ?? 0);
        $kunci = $gid > 0 ? 'hadir:g' . $gid : 'hadir:h' . (int) ($p['id'] ?? 0);
        $pihak[$kunci] = ['label' => (string) ($p['nama'] ?? ''), 'nama' => (string) ($p['nama'] ?? ''),
            'jenis' => 'hadir'];
    }
    return $pihak;
}

/** Peta tanda tangan tersimpan: pihak => baris `notulen_ttd`. */
function notulen_ttd_peta(int $notulenId): array
{
    if ($notulenId <= 0) {
        return [];
    }
    $peta = [];
    foreach (all('SELECT * FROM notulen_ttd WHERE notulen_id = ?', [$notulenId]) as $r) {
        $peta[(string) $r['pihak']] = $r;
    }
    return $peta;
}

/** Satu baris tanda tangan (null bila belum ada). */
function notulen_ttd_baris(int $notulenId, string $pihak): ?array
{
    if ($notulenId <= 0 || $pihak === '') {
        return null;
    }
    return one('SELECT * FROM notulen_ttd WHERE notulen_id = ? AND pihak = ?', [$notulenId, $pihak]);
}

/** Berapa banyak pihak yang sudah menandatangani. */
function notulen_ttd_jumlah(int $notulenId): int
{
    if ($notulenId <= 0) {
        return 0;
    }
    return (int) val('SELECT COUNT(*) FROM notulen_ttd WHERE notulen_id = ?', [$notulenId], 0);
}

/** Membaca ukuran (lebar × tinggi) dari header PNG; null bila bukan PNG yang sah. */
function notulen_ttd_png_ukuran(string $bytes): ?array
{
    if (strlen($bytes) < 24 || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        return null;
    }
    // Chunk pertama wajib IHDR; lebar & tinggi = 4 byte big-endian mulai offset 16.
    if (substr($bytes, 12, 4) !== 'IHDR') {
        return null;
    }
    $lebar = unpack('N', substr($bytes, 16, 4))[1] ?? 0;
    $tinggi = unpack('N', substr($bytes, 20, 4))[1] ?? 0;
    if ($lebar <= 0 || $tinggi <= 0) {
        return null;
    }
    return ['lebar' => (int) $lebar, 'tinggi' => (int) $tinggi];
}

/**
 * Menyimpan tanda tangan sebuah pihak. Mengembalikan ['ok'=>bool,'pesan'=>string].
 * Yang disimpan hanya URL CDN hasil media proxy.
 */
function notulen_ttd_simpan(int $notulenId, string $pihak, string $bytes): array
{
    if ($notulenId <= 0) {
        return ['ok' => false, 'pesan' => 'Notulen tidak ditemukan.'];
    }
    if ($pihak === '') {
        return ['ok' => false, 'pesan' => 'Pihak penanda tangan tidak dikenal.'];
    }
    $ukuran = strlen($bytes);
    if ($ukuran < 100) {
        return ['ok' => false,
            'pesan' => 'Tanda tangan masih kosong — coretkan tanda tangan terlebih dahulu.'];
    }
    if ($ukuran > NOTULEN_TTD_MAKS_BYTE) {
        return ['ok' => false, 'pesan' => 'Gambar tanda tangan terlalu besar (maksimal '
            . notulen_ttd_ukuran_teks(NOTULEN_TTD_MAKS_BYTE) . ').'];
    }
    $dim = notulen_ttd_png_ukuran($bytes);
    if ($dim === null) {
        return ['ok' => false, 'pesan' => 'Gambar tanda tangan harus berupa PNG yang sah.'];
    }
    if ($dim['lebar'] < NOTULEN_TTD_MIN_PX || $dim['tinggi'] < NOTULEN_TTD_MIN_PX) {
        return ['ok' => false, 'pesan' => 'Gambar tanda tangan terlalu kecil.'];
    }
    if ($dim['lebar'] > NOTULEN_TTD_MAKS_PX || $dim['tinggi'] > NOTULEN_TTD_MAKS_PX) {
        return ['ok' => false, 'pesan' => 'Ukuran gambar tanda tangan tidak wajar.'];
    }
    $namaBerkas = 'ttd-notulen-' . $notulenId . '-'
        . preg_replace('/[^A-Za-z0-9]+/', '-', $pihak) . '.png';
    $up = media_upload($bytes, $namaBerkas);
    if (empty($up['ok'])) {
        return ['ok' => false, 'pesan' => (string) ($up['pesan'] ?? 'Tanda tangan gagal diunggah.')];
    }
    $url = (string) ($up['url'] ?? '');
    if ($url === '') {
        return ['ok' => false, 'pesan' => 'Tanda tangan gagal diunggah (URL kosong).'];
    }
    $sekarang = date('Y-m-d H:i:s');
    q('INSERT INTO notulen_ttd (notulen_id, pihak, url, lebar, tinggi, oleh, ip, dibuat, diubah)
       VALUES (?,?,?,?,?,?,?,?,?)
       ON CONFLICT(notulen_id, pihak) DO UPDATE SET url = excluded.url, lebar = excluded.lebar,
         tinggi = excluded.tinggi, oleh = excluded.oleh, ip = excluded.ip, diubah = excluded.diubah',
        [$notulenId, $pihak, $url, $dim['lebar'], $dim['tinggi'],
         (string) (current_user()['nama'] ?? ''), log_ip(), $sekarang, $sekarang]);
    return ['ok' => true, 'pesan' => 'Tanda tangan tersimpan.', 'url' => $url];
}

/** Menghapus tanda tangan satu pihak (digambar ulang / tidak jadi). */
function notulen_ttd_hapus(int $notulenId, string $pihak): bool
{
    if ($notulenId <= 0 || $pihak === '') {
        return false;
    }
    if (!notulen_ttd_baris($notulenId, $pihak)) {
        return false;
    }
    q('DELETE FROM notulen_ttd WHERE notulen_id = ? AND pihak = ?', [$notulenId, $pihak]);
    return true;
}

/** Menghapus tanda tangan milik pihak yang sudah tidak ada pada notulen (mis. peserta diganti). */
function notulen_ttd_bersihkan(int $notulenId): int
{
    $n = notulen_by_id($notulenId);
    if (!$n) {
        return 0;
    }
    $sah = notulen_ttd_pihak($n, notulen_peserta($notulenId));
    $hapus = 0;
    foreach (all('SELECT pihak FROM notulen_ttd WHERE notulen_id = ?', [$notulenId]) as $r) {
        $kunci = (string) $r['pihak'];
        if (!array_key_exists($kunci, $sah)) {
            q('DELETE FROM notulen_ttd WHERE notulen_id = ? AND pihak = ?', [$notulenId, $kunci]);
            $hapus++;
        }
    }
    return $hapus;
}

/** HTML gambar tanda tangan ('' bila belum ada). */
function notulen_ttd_gambar(array $peta, string $pihak, string $kelas = 'ttd-img'): string
{
    if (!isset($peta[$pihak])) {
        return '';
    }
    $url = trim((string) ($peta[$pihak]['url'] ?? ''));
    if ($url === '') {
        return '';
    }
    return '<img class="' . e($kelas) . '" src="' . e($url) . '" alt="Tanda tangan '
        . e((string) ($peta[$pihak]['oleh'] ?? '')) . '">';
}

/** Tombol pembuka dialog tanda tangan (disembunyikan CSS/JS bila perangkat tanpa layar sentuh). */
function notulen_ttd_tombol(string $pihak, bool $sudahAda = false, string $kelas = 'ttd-tombol'): string
{
    return '<button type="button" class="' . e($kelas) . '" data-ttd-tombol="' . e($pihak) . '" hidden>'
        . ($sudahAda ? 'Ulangi' : 'Tanda Tangan') . '</button>';
}

/**
 * Data untuk skrip: daftar pihak, status tersimpan, dan alamat endpoint.
 * Dicetak `cetak_notulen.php` pada `<script type="application/json" id="ttdData">`.
 */
function notulen_ttd_skrip_json(int $notulenId, array $pihak, array $peta): string
{
    $data = ['id' => $notulenId, 'pihak' => [], 'endpoint' => 'upload_notulen_ttd.php'];
    foreach ($pihak as $kunci => $info) {
        $data['pihak'][$kunci] = ['label' => $info['label'], 'nama' => $info['nama'],
            'ada' => isset($peta[$kunci])];
    }
    return (string) json_encode($data,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * Dialog tanda tangan (kanvas layar sentuh). Dicetak SEKALI per halaman.
 */
function notulen_ttd_dialog(int $notulenId): string
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
    <input type="hidden" name="id" value="<?= (int) $notulenId ?>">
    <input type="hidden" name="pihak" id="ttdPihak" value="">
  </form>
</dialog>
    <?php
    return (string) ob_get_clean();
}
