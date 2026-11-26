<?php
/**
 * JEMBATAN NOTULEN RAPAT dari aplikasi **TUSmart** ke e-GURU — e-GURU v1.103.0
 * (tanpa perubahan skema, baik di e-GURU maupun di TUSmart).
 *
 * Permintaan pemilik: *"Notulen rapat yang dibuat di aplikasi TU Smart masuk di Daftar Notulen
 * (di akun kepala madrasah khusus MA. Sirojuth Tholibiin Mootilango) agar kepala madrasah
 * MA. Sirojuth Tholibiin Mootilango bisa mengetahui."* Keputusan pemilik (dipilih lewat pertanyaan
 * pilihan):
 *   1. notulen TUSmart **bisa dibuka & dicetak** (Berita Acara, Daftar Hadir, Isi Notulen, Foto
 *      Dokumentasi) **dan kepala madrasah juga bisa membubuhkan tanda tangan**;
 *   2. yang melihat notulen TUSmart di Daftar Notulen **HANYA akun kepala sekolah/madrasah**
 *      (operator, Ka. Tata Usaha, dan akun lain TIDAK melihatnya).
 *
 * Cara kerjanya (sama seperti jembatan Tanda Tangan Rapat v1.102.0 — sengaja TANPA menyalin data,
 * supaya tidak ada dua kebenaran):
 *   1. Sumber kebenaran = basis data TUSmart (`tusmart/data/tusmart.sqlite`), dibuka langsung oleh
 *      `inc/ttd_rapat_tusmart.php` (koneksi, gerbang lembaga, dan penyimpanan tanda tangan ada di
 *      sana — **jangan** membuka PDO kedua di modul ini).
 *   2. Gerbang lembaga = **NPSN** lembaga TUSmart harus sama dengan NPSN sekolah yang sedang dibuka
 *      di e-GURU (`tusmart_aktif()`). Jadi rapat TUSmart tidak bocor ke sekolah lain.
 *   3. Hak lihat = `notulen_tusmart_boleh()` → **hanya akun kepala** pada lembaga yang cocok.
 *   4. Yang boleh ditandatangani dari e-GURU hanyalah blok **`kepala`** ("Mengetahui, Kepala
 *      Madrasah") — pihak lain (peserta/notulis) ditandatangani orangnya masing-masing di aplikasi
 *      TUSmart atau lewat menu e-GURU "Tanda Tangan Rapat" (v1.102.0). Ini ditegakkan di server
 *      (`upload_notulen_ttd.php`, cabang `sumber=tusmart`).
 *
 * Tanda tangan kepala tetap memakai mekanisme e-GURU (kanvas → PNG → media proxy platform), lalu
 * URL CDN-nya ditulis ke tabel `notulen_ttd` TUSmart dengan kunci pihak **`kepala`** — kunci yang
 * SAMA dengan TUSmart, sehingga tanda tangan langsung tercetak pada lembar Berita Acara / Daftar
 * Hadir / Isi Notulen TUSmart.
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/notulen_ttd.php';
require_once __DIR__ . '/ttd_rapat_tusmart.php';   // koneksi + gerbang lembaga + simpan tanda tangan

/** Kunci pihak yang boleh ditandatangani kepala madrasah dari e-GURU pada notulen TUSmart. */
const NOTULEN_TUSMART_PIHAK_KEPALA = 'kepala';

/**
 * Apakah notulen TUSmart boleh tampil untuk pengguna yang sedang masuk.
 *
 * Permintaan pemilik: **hanya akun kepala sekolah/madrasah** lembaga yang memang dikelola di
 * TUSmart (dicocokkan lewat NPSN) — operator, Ka. Tata Usaha, guru, dan sekolah lain tidak.
 */
function notulen_tusmart_boleh(): bool
{
    if (!is_kepala()) {
        return false;
    }
    if (current_sekolah() === null) {
        return false;
    }
    return tusmart_aktif();
}

/**
 * Daftar notulen TUSmart (terbaru lebih dulu), lengkap dengan jumlah hadir/foto/tanda tangan.
 *
 * Saringan dibuat dengan aturan yang SAMA dengan daftar notulen e-GURU (`notulen.php`) supaya
 * hasil pencarian/tanggal terasa konsisten: cari pada judul/tempat/agenda/hasil/keputusan.
 *
 * @return array<int, array<string, mixed>> baris dengan kunci mirip tabel `notulen` e-GURU
 *         (tanggal, waktu, tempat, judul, agenda, pembahasan, keputusan, pimpinan,
 *         pimpinan_jabatan, notulis, ta_id, semester_id) + `sumber`, `jumlah_hadir`,
 *         `jumlah_foto`, `jumlah_ttd`
 */
function notulen_tusmart_daftar(string $cari = '', ?string $dari = null, ?string $sampai = null): array
{
    if (!notulen_tusmart_boleh()) {
        return [];
    }
    $db = tusmart_db();
    if ($db === null) {
        return [];
    }
    $w = ['1 = 1'];
    $p = [];
    if (trim($cari) !== '') {
        $w[] = '(n.judul LIKE ? OR n.tempat LIKE ? OR n.agenda LIKE ? OR n.pembahasan LIKE ?'
            . ' OR n.keputusan LIKE ?)';
        $like = '%' . trim($cari) . '%';
        array_push($p, $like, $like, $like, $like, $like);
    }
    if ($dari !== null && $dari !== '') {
        $w[] = 'n.tanggal >= ?';
        $p[] = $dari;
    }
    if ($sampai !== null && $sampai !== '') {
        $w[] = 'n.tanggal <= ?';
        $p[] = $sampai;
    }
    try {
        $st = $db->prepare('SELECT n.id, n.tanggal, n.waktu, n.tempat, n.judul, n.agenda, n.pembahasan,
                n.keputusan, n.pimpinan, n.pimpinan_jabatan, n.notulis, n.dibuat, n.dibuat_oleh,
                (SELECT COUNT(*) FROM notulen_hadir x WHERE x.notulen_id = n.id) AS jumlah_hadir,
                (SELECT COUNT(*) FROM notulen_foto f WHERE f.notulen_id = n.id) AS jumlah_foto,
                (SELECT COUNT(*) FROM notulen_ttd t WHERE t.notulen_id = n.id) AS jumlah_ttd
            FROM notulen n
            WHERE ' . implode(' AND ', $w) . '
            ORDER BY n.tanggal DESC, n.id DESC LIMIT 300');
        $st->execute($p);
        $baris = $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    foreach ($baris as $i => $r) {
        $baris[$i] = notulen_tusmart_normal((array) $r);
    }
    return $baris;
}

/** Menyamakan nama kunci baris TUSmart dengan baris `notulen` e-GURU (+ penanda sumber). */
function notulen_tusmart_normal(array $r): array
{
    $r['id'] = (int) ($r['id'] ?? 0);
    $r['sumber'] = 'tusmart';
    // Notulen TUSmart tidak memakai tahun ajaran/semester e-GURU; kolomnya disediakan supaya
    // lembar cetak yang membaca kunci ini tidak perlu bercabang.
    $r['ta_id'] = 0;
    $r['semester_id'] = 0;
    foreach (['tanggal', 'waktu', 'tempat', 'judul', 'agenda', 'pembahasan', 'keputusan', 'pimpinan',
                 'pimpinan_jabatan', 'notulis'] as $k) {
        $r[$k] = (string) ($r[$k] ?? '');
    }
    $r['jumlah_hadir'] = (int) ($r['jumlah_hadir'] ?? 0);
    $r['jumlah_foto'] = (int) ($r['jumlah_foto'] ?? 0);
    $r['jumlah_ttd'] = (int) ($r['jumlah_ttd'] ?? 0);
    return $r;
}

/** Satu notulen TUSmart (null bila tidak ada / bukan hak pengguna ini). */
function notulen_tusmart_satu(int $id): ?array
{
    if ($id <= 0 || !notulen_tusmart_boleh()) {
        return null;
    }
    $db = tusmart_db();
    if ($db === null) {
        return null;
    }
    try {
        $st = $db->prepare('SELECT n.*,
                (SELECT COUNT(*) FROM notulen_hadir x WHERE x.notulen_id = n.id) AS jumlah_hadir,
                (SELECT COUNT(*) FROM notulen_foto f WHERE f.notulen_id = n.id) AS jumlah_foto,
                (SELECT COUNT(*) FROM notulen_ttd t WHERE t.notulen_id = n.id) AS jumlah_ttd
            FROM notulen n WHERE n.id = ?');
        $st->execute([$id]);
        $r = $st->fetch();
    } catch (Throwable $e) {
        return null;
    }
    return $r ? notulen_tusmart_normal((array) $r) : null;
}

/**
 * Peserta (daftar hadir) sebuah notulen TUSmart — urut seperti penyimpanan TUSmart.
 * Nama/NIP/jabatan adalah snapshot saat notulen dibuat, sama seperti TUSmart mencetaknya.
 */
function notulen_tusmart_peserta(int $id): array
{
    if ($id <= 0 || !notulen_tusmart_boleh()) {
        return [];
    }
    $db = tusmart_db();
    if ($db === null) {
        return [];
    }
    try {
        $st = $db->prepare('SELECT * FROM notulen_hadir WHERE notulen_id = ? ORDER BY urutan, id');
        $st->execute([$id]);
        return array_map('notulen_tusmart_normal_peserta', $st->fetchAll());
    } catch (Throwable $e) {
        return [];
    }
}

/** Menyamakan baris `notulen_hadir` TUSmart dengan baris `notulen_hadir` e-GURU. */
function notulen_tusmart_normal_peserta(array $r): array
{
    $r['id'] = (int) ($r['id'] ?? 0);
    $r['guru_id'] = (int) ($r['guru_id'] ?? 0);
    foreach (['nama', 'nip', 'jabatan'] as $k) {
        $r[$k] = (string) ($r[$k] ?? '');
    }
    return $r;
}

/** Foto bukti kegiatan sebuah notulen TUSmart (`url`, `keterangan`, `urutan`). */
function notulen_tusmart_foto(int $id): array
{
    if ($id <= 0 || !notulen_tusmart_boleh()) {
        return [];
    }
    $db = tusmart_db();
    if ($db === null) {
        return [];
    }
    try {
        $st = $db->prepare('SELECT * FROM notulen_foto WHERE notulen_id = ? ORDER BY urutan, id');
        $st->execute([$id]);
        $keluar = [];
        foreach ($st->fetchAll() as $f) {
            $f['id'] = (int) ($f['id'] ?? 0);
            $f['url'] = (string) ($f['url'] ?? '');
            $f['keterangan'] = (string) ($f['keterangan'] ?? '');
            $keluar[] = $f;
        }
        return $keluar;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Seluruh tanda tangan tersimpan pada sebuah notulen TUSmart — pihak => baris `notulen_ttd`.
 * Dipakai lembar cetak supaya tanda tangan yang sudah dibubuhkan (guru lewat e-GURU maupun kepala)
 * ikut tercetak.
 */
function notulen_tusmart_ttd_peta(int $id): array
{
    if ($id <= 0 || !notulen_tusmart_boleh()) {
        return [];
    }
    $db = tusmart_db();
    if ($db === null) {
        return [];
    }
    try {
        $st = $db->prepare('SELECT * FROM notulen_ttd WHERE notulen_id = ?');
        $st->execute([$id]);
        $peta = [];
        foreach ($st->fetchAll() as $r) {
            $peta[(string) $r['pihak']] = $r;
        }
        return $peta;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Pihak penanda tangan pada notulen TUSmart — kuncinya SAMA dengan TUSmart/e-GURU:
 * `kepala`, `pimpinan`, `notulis`, dan `hadir:g<id guru TUSmart>` (cadangan `hadir:h<id baris>`).
 *
 * @return array<string, array{label: string, nama: string, jenis: string}>
 */
function notulen_tusmart_pihak(array $notulen, array $peserta): array
{
    $pihak = [];
    $pimpinan = trim((string) ($notulen['pimpinan'] ?? ''));
    $notulis = trim((string) ($notulen['notulis'] ?? ''));
    $pihak[NOTULEN_TUSMART_PIHAK_KEPALA] = [
        'label' => 'Kepala ' . istilah_sekolah('Sekolah'),
        'nama'  => kepala_nama(),
        'jenis' => 'kepala',
    ];
    if ($pimpinan !== '') {
        $pihak['pimpinan'] = ['label' => 'Pimpinan Rapat', 'nama' => $pimpinan, 'jenis' => 'pimpinan'];
    }
    if ($notulis !== '') {
        $pihak['notulis'] = ['label' => 'Notulis', 'nama' => $notulis, 'jenis' => 'notulis'];
    }
    foreach ($peserta as $p) {
        $gid = (int) ($p['guru_id'] ?? 0);
        $kunci = $gid > 0 ? 'hadir:g' . $gid : 'hadir:h' . (int) ($p['id'] ?? 0);
        $pihak[$kunci] = ['label' => (string) ($p['nama'] ?? ''),
            'nama' => (string) ($p['nama'] ?? ''), 'jenis' => 'hadir'];
    }
    return $pihak;
}

/** Jumlah notulen TUSmart (untuk kartu ringkasan pada Daftar Notulen). */
function notulen_tusmart_jumlah(): int
{
    if (!notulen_tusmart_boleh()) {
        return 0;
    }
    $db = tusmart_db();
    if ($db === null) {
        return 0;
    }
    try {
        return (int) $db->query('SELECT COUNT(*) FROM notulen')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Jumlah notulen TUSmart pada bulan tertentu (`YYYY-MM`) — kartu "Bulan ini". */
function notulen_tusmart_jumlah_bulan(string $bulan): int
{
    if (!notulen_tusmart_boleh() || $bulan === '') {
        return 0;
    }
    $db = tusmart_db();
    if ($db === null) {
        return 0;
    }
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM notulen WHERE substr(tanggal, 1, 7) = ?');
        $st->execute([$bulan]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Alamat lembar cetak sebuah notulen TUSmart (`cetak_notulen.php?…&sumber=tusmart`).
 * Dipakai Daftar Notulen & tombol pada lembar cetak supaya penanda sumber tidak pernah lupa.
 */
function notulen_tusmart_cetak_url(int $id, string $bagian = 'berita', array $tambahan = []): string
{
    return 'cetak_notulen.php?' . http_build_query(array_merge(
        ['sumber' => 'tusmart', 'id' => $id, 'bagian' => $bagian],
        $tambahan
    ));
}
