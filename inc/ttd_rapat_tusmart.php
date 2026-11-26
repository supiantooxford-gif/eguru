<?php
/**
 * JEMBATAN TANDA TANGAN RAPAT dari aplikasi **TUSmart** ke akun guru e-GURU —
 * e-GURU v1.102.0 (tanpa perubahan skema, baik di e-GURU maupun di TUSmart).
 *
 * Permintaan pemilik: *"Khusus MA. Sirojuth Tholibiin Mootilango. Ketika Kepala TU/Operator membuat
 * notulen rapat di aplikasi TU Smart otomatis guru-guru MA. Sirojuth Tholibiin Mootilango bisa
 * menandatangani lewat akun e-Guru mereka masing-masing."*
 *
 * Cara kerjanya (sengaja TANPA menyalin data, supaya tidak ada dua kebenaran):
 *   1. TUSmart menyimpan notulen + daftar hadir + tanda tangan pada basis datanya sendiri
 *      (`tusmart/data/tusmart.sqlite`, tabel `notulen`, `notulen_hadir`, `notulen_ttd`).
 *   2. Modul ini membuka basis data TUSmart itu langsung (satu lembaga, berkas di sebelah folder
 *      e-GURU) — HANYA BILA lembaga TUSmart memang sekolah yang sedang dibuka di e-GURU
 *      (NPSN sama, cadangan: nama lembaga sama). Jadi rapat TUSmart TIDAK muncul di sekolah lain.
 *   3. Guru dipetakan ke data guru TUSmart: **NIP sama** lebih dulu, lalu **nama yang sama**
 *      setelah gelar/pungtuasi diabaikan (`ttd_rapat_nama_normal()`). TUSmart tidak menyimpan NIP
 *      pada data gurunya, jadi pencocokan nama inilah yang dipakai sehari-hari — bila ada LEBIH DARI
 *      SATU kandidat, guru dianggap tidak cocok (lebih baik tidak tampil daripada salah orang).
 *   4. Tanda tangan tetap memakai mekanisme e-GURU (kanvas → PNG → media proxy platform), lalu
 *      URL CDN-nya ditulis ke tabel `notulen_ttd` TUSmart dengan kunci pihak **`hadir:g<id guru
 *      TUSmart>`** — kunci yang SAMA dengan yang dipakai TUSmart, sehingga tanda tangan guru
 *      langsung tampil pada lembar cetak Daftar Hadir & penghitung "X ttd" di TUSmart.
 *
 * Hak tanda tangan tetap dibatasi pada baris guru itu sendiri dan ditegakkan di server
 * (`upload_notulen_ttd.php`, cabang `sumber=tusmart`).
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/notulen_ttd.php';

/** Lokasi basis data TUSmart dibanding folder workspace (e-GURU & TUSmart bersaudara). */
const TUSMART_DB_RELATIF = '/tusmart/data/tusmart.sqlite';

/** Nama basis data TUSmart yang dipakai (bisa ditimpa lewat env saat pengujian). */
function tusmart_db_path(): string
{
    $env = getenv('TUSMART_DB_FILE');
    if (is_string($env) && $env !== '') {
        return $env;
    }
    return dirname(dirname(__DIR__)) . TUSMART_DB_RELATIF;
}

/** Koneksi ke basis data TUSmart (null bila berkasnya tidak ada / tidak dapat dibuka). */
function tusmart_db(): ?PDO
{
    static $pdo = null;
    static $sudah = false;
    if ($sudah) {
        return $pdo;
    }
    $sudah = true;
    $path = tusmart_db_path();
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    try {
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
    } catch (Throwable $e) {
        $pdo = null;
    }
    return $pdo;
}

/** Satu nilai `pengaturan` TUSmart ('' bila tidak ada). */
function tusmart_pengaturan(string $kunci): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $db = tusmart_db();
        if ($db !== null) {
            try {
                foreach ($db->query('SELECT kunci, nilai FROM pengaturan') as $r) {
                    $cache[(string) $r['kunci']] = (string) ($r['nilai'] ?? '');
                }
            } catch (Throwable $e) {
                $cache = [];
            }
        }
    }
    return (string) ($cache[$kunci] ?? '');
}

/** Nama (lembaga/orang) yang dinormalkan: huruf kecil, tanpa gelar setelah koma, tanpa tanda baca. */
function ttd_rapat_nama_normal(string $nama): string
{
    $nama = trim($nama);
    if ($nama === '') {
        return '';
    }
    // Gelar akademik ditulis setelah koma ("Supianto, S.Pd.I") → hanya bagian depan yang dipakai.
    $koma = strpos($nama, ',');
    if ($koma !== false) {
        $nama = substr($nama, 0, $koma);
    }
    $nama = strtolower($nama);
    $nama = preg_replace('/[^a-z0-9 ]+/', ' ', $nama) ?? '';
    return trim(preg_replace('/\s+/', ' ', $nama) ?? '');
}

/**
 * Apakah jembatan TUSmart berlaku untuk sekolah yang sedang dibuka di e-GURU.
 * Dicocokkan lewat **NPSN** (cadangan: nama lembaga) — jadi hanya lembaga yang memang dikelola
 * di TUSmart (MA. Sirojuth Tholibiin Mootilango) yang melihat rapat TUSmart.
 */
function tusmart_aktif(): bool
{
    static $hasil = null;
    if ($hasil !== null) {
        return $hasil;
    }
    $hasil = false;
    $s = current_sekolah();
    if (!$s) {
        return false;
    }
    if (tusmart_db() === null) {
        return false;
    }
    $npsnTusmart = trim(tusmart_pengaturan('npsn'));
    $npsnSekolah = trim((string) ($s['npsn'] ?? ''));
    // Bila KEDUA sisi punya NPSN, hanya NPSN yang menentukan — dua lembaga berbeda yang kebetulan
    // senama tetap TIDAK dicocokkan. Nama hanya dipakai sebagai cadangan saat NPSN belum diisi.
    if ($npsnTusmart !== '' && $npsnSekolah !== '') {
        $hasil = $npsnTusmart === $npsnSekolah;
        return $hasil;
    }
    $namaTusmart = ttd_rapat_nama_normal(tusmart_pengaturan('nama'));
    $namaSekolah = ttd_rapat_nama_normal((string) ($s['nama'] ?? ''));
    $hasil = $namaTusmart !== '' && $namaTusmart === $namaSekolah;
    return $hasil;
}

/**
 * ID guru pada data TUSmart untuk seorang guru e-GURU: NIP sama lebih dulu, lalu nama sama
 * (gelar diabaikan). 0 = tidak ada / lebih dari satu kandidat (tidak dicocokkan).
 */
function tusmart_guru_id(array $guru): int
{
    static $cache = [];
    $gid = (int) ($guru['id'] ?? 0);
    if (array_key_exists($gid, $cache)) {
        return $cache[$gid];
    }
    $cache[$gid] = 0;
    $db = tusmart_db();
    if ($db === null || $gid <= 0) {
        return 0;
    }
    $nip = trim((string) ($guru['nip'] ?? ''));
    try {
        if ($nip !== '') {
            $st = $db->prepare('SELECT id FROM guru WHERE TRIM(IFNULL(nip, "")) = ? LIMIT 2');
            $st->execute([$nip]);
            $baris = $st->fetchAll();
            if (count($baris) === 1) {
                return $cache[$gid] = (int) $baris[0]['id'];
            }
        }
        $normal = ttd_rapat_nama_normal((string) ($guru['nama'] ?? ''));
        if ($normal === '') {
            return 0;
        }
        $cocok = [];
        foreach ($db->query('SELECT id, nama FROM guru ORDER BY id') as $r) {
            if (ttd_rapat_nama_normal((string) $r['nama']) === $normal) {
                $cocok[] = (int) $r['id'];
            }
        }
        if (count($cocok) === 1) {
            $cache[$gid] = $cocok[0];
        }
    } catch (Throwable $e) {
        $cache[$gid] = 0;
    }
    return $cache[$gid];
}

/**
 * Rapat TUSmart yang daftar hadirnya memuat guru ini, terbaru lebih dulu.
 *
 * @return array<int, array<string, mixed>>  baris dengan kunci `sumber`, `kunci`, `guru_id_sumber`
 */
function tusmart_rapat_daftar(int $guruEguruId): array
{
    if ($guruEguruId <= 0 || !tusmart_aktif()) {
        return [];
    }
    $guru = one('SELECT * FROM guru WHERE id = ?', [$guruEguruId]);
    if (!$guru) {
        return [];
    }
    $gidTs = tusmart_guru_id($guru);
    if ($gidTs <= 0) {
        return [];
    }
    $db = tusmart_db();
    if ($db === null) {
        return [];
    }
    $kunci = ttd_rapat_kunci($gidTs);
    try {
        $st = $db->prepare('SELECT n.id, n.tanggal, n.waktu, n.tempat, n.judul, n.pembahasan,
                                   n.pimpinan, n.pimpinan_jabatan, n.notulis,
                                   h.nama AS nama_hadir, h.jabatan AS jabatan_hadir,
                                   t.url AS ttd_url, t.dibuat AS ttd_pada, t.oleh AS ttd_oleh,
                                   (SELECT COUNT(*) FROM notulen_hadir x WHERE x.notulen_id = n.id)
                                     AS jumlah_hadir
                            FROM notulen_hadir h
                            JOIN notulen n ON n.id = h.notulen_id
                            LEFT JOIN notulen_ttd t ON t.notulen_id = n.id AND t.pihak = ?
                            WHERE h.guru_id = ?
                            ORDER BY n.tanggal DESC, n.id DESC');
        $st->execute([$kunci, $gidTs]);
        $baris = $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
    $keluar = [];
    $sudah = [];
    foreach ($baris as $r) {
        $id = (int) $r['id'];
        if (isset($sudah[$id])) {
            continue;   // guru tercatat dua kali pada daftar hadir → tampil sekali saja
        }
        $sudah[$id] = true;
        $r['id'] = $id;
        $r['sumber'] = 'tusmart';
        $r['kunci'] = $kunci;
        $r['guru_id_sumber'] = $gidTs;
        $keluar[] = $r;
    }
    return $keluar;
}

/** Satu rapat TUSmart milik guru ini (null bila bukan pesertanya). */
function tusmart_rapat_satu(int $notulenId, int $guruEguruId): ?array
{
    if ($notulenId <= 0) {
        return null;
    }
    foreach (tusmart_rapat_daftar($guruEguruId) as $r) {
        if ((int) $r['id'] === $notulenId) {
            return $r;
        }
    }
    return null;
}

/** Jumlah pihak yang sudah menandatangani pada sebuah rapat TUSmart. */
function tusmart_ttd_jumlah(int $notulenId): int
{
    $db = tusmart_db();
    if ($db === null || $notulenId <= 0) {
        return 0;
    }
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM notulen_ttd WHERE notulen_id = ?');
        $st->execute([$notulenId]);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Menyimpan tanda tangan guru pada rapat TUSmart.
 * Yang disimpan ke TUSmart HANYA URL CDN hasil media proxy platform (sama seperti e-GURU).
 *
 * @return array{ok: bool, pesan: string, url: string}
 */
function tusmart_ttd_simpan(int $notulenId, int $gidTs, string $bytes, string $oleh): array
{
    if ($gidTs <= 0) {
        return ['ok' => false, 'pesan' => 'Rapat TUSmart tidak ditemukan.', 'url' => ''];
    }
    return tusmart_ttd_simpan_pihak($notulenId, ttd_rapat_kunci($gidTs), $bytes, $oleh);
}

/**
 * Menyimpan tanda tangan SATU PIHAK pada rapat TUSmart (kunci pihak apa adanya).
 *
 * Dipakai dua jalur:
 *   - guru peserta rapat (`hadir:g<id guru TUSmart>`), dari menu e-GURU "Tanda Tangan Rapat";
 *   - **kepala sekolah/madrasah** (`kepala`), dari Daftar Notulen e-GURU (v1.103.0).
 * Yang disimpan ke TUSmart HANYA URL CDN hasil media proxy platform.
 *
 * @return array{ok: bool, pesan: string, url: string}
 */
function tusmart_ttd_simpan_pihak(int $notulenId, string $pihak, string $bytes, string $oleh): array
{
    $db = tusmart_db();
    if ($db === null || $notulenId <= 0 || $pihak === '') {
        return ['ok' => false, 'pesan' => 'Rapat TUSmart tidak ditemukan.', 'url' => ''];
    }
    $ukuran = strlen($bytes);
    if ($ukuran < 100) {
        return ['ok' => false,
            'pesan' => 'Tanda tangan masih kosong — coretkan tanda tangan terlebih dahulu.', 'url' => ''];
    }
    if ($ukuran > NOTULEN_TTD_MAKS_BYTE) {
        return ['ok' => false, 'pesan' => 'Gambar tanda tangan terlalu besar (maksimal '
            . notulen_ttd_ukuran_teks(NOTULEN_TTD_MAKS_BYTE) . ').', 'url' => ''];
    }
    $dim = notulen_ttd_png_ukuran($bytes);
    if ($dim === null) {
        return ['ok' => false, 'pesan' => 'Gambar tanda tangan harus berupa PNG yang sah.', 'url' => ''];
    }
    if ($dim['lebar'] < NOTULEN_TTD_MIN_PX || $dim['tinggi'] < NOTULEN_TTD_MIN_PX) {
        return ['ok' => false, 'pesan' => 'Gambar tanda tangan terlalu kecil.', 'url' => ''];
    }
    if ($dim['lebar'] > NOTULEN_TTD_MAKS_PX || $dim['tinggi'] > NOTULEN_TTD_MAKS_PX) {
        return ['ok' => false, 'pesan' => 'Ukuran gambar tanda tangan tidak wajar.', 'url' => ''];
    }
    $up = media_upload($bytes, 'ttd-tusmart-' . $notulenId . '-'
        . preg_replace('/[^A-Za-z0-9]+/', '-', $pihak) . '.png');
    if (empty($up['ok']) || (string) ($up['url'] ?? '') === '') {
        return ['ok' => false, 'pesan' => (string) ($up['pesan'] ?? 'Tanda tangan gagal diunggah.'),
            'url' => ''];
    }
    $url = (string) $up['url'];
    $sekarang = date('Y-m-d H:i:s');
    try {
        $db->exec('BEGIN IMMEDIATE');
        $st = $db->prepare('INSERT INTO notulen_ttd (notulen_id, pihak, url, lebar, tinggi, oleh, ip, dibuat, diubah)
                            VALUES (?,?,?,?,?,?,?,?,?)
                            ON CONFLICT(notulen_id, pihak) DO UPDATE SET url = excluded.url,
                              lebar = excluded.lebar, tinggi = excluded.tinggi, oleh = excluded.oleh,
                              ip = excluded.ip, diubah = excluded.diubah');
        $st->execute([$notulenId, $pihak, $url, $dim['lebar'], $dim['tinggi'],
            $oleh, log_ip(), $sekarang, $sekarang]);
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        try { $db->exec('ROLLBACK'); } catch (Throwable $e2) { /* diabaikan */ }
        return ['ok' => false, 'pesan' => 'Tanda tangan tidak dapat disimpan ke TUSmart.', 'url' => ''];
    }
    return ['ok' => true, 'pesan' => 'Tanda tangan tersimpan.', 'url' => $url];
}

/** Menghapus tanda tangan guru pada rapat TUSmart. */
function tusmart_ttd_hapus(int $notulenId, int $gidTs): bool
{
    return tusmart_ttd_hapus_pihak($notulenId, ttd_rapat_kunci($gidTs));
}

/** Menghapus tanda tangan SATU PIHAK pada rapat TUSmart (kunci pihak apa adanya). */
function tusmart_ttd_hapus_pihak(int $notulenId, string $pihak): bool
{
    $db = tusmart_db();
    $kunci = $pihak;
    if ($db === null || $notulenId <= 0 || $kunci === '') {
        return false;
    }
    try {
        $st = $db->prepare('SELECT 1 FROM notulen_ttd WHERE notulen_id = ? AND pihak = ?');
        $st->execute([$notulenId, $kunci]);
        if ($st->fetchColumn() === false) {
            return false;
        }
        $db->exec('BEGIN IMMEDIATE');
        $del = $db->prepare('DELETE FROM notulen_ttd WHERE notulen_id = ? AND pihak = ?');
        $del->execute([$notulenId, $kunci]);
        $db->exec('COMMIT');
        return true;
    } catch (Throwable $e) {
        try { $db->exec('ROLLBACK'); } catch (Throwable $e2) { /* diabaikan */ }
        return false;
    }
}

/**
 * Peta tanda tangan untuk tampilan rincian rapat TUSmart: hanya baris milik guru ini.
 *
 * @return array<string, array<string, mixed>>
 */
function tusmart_ttd_peta(array $rapat): array
{
    $kunci = (string) ($rapat['kunci'] ?? '');
    $url = trim((string) ($rapat['ttd_url'] ?? ''));
    if ($kunci === '' || $url === '') {
        return [];
    }
    return [$kunci => ['pihak' => $kunci, 'url' => $url,
        'oleh' => (string) ($rapat['ttd_oleh'] ?? ''), 'dibuat' => (string) ($rapat['ttd_pada'] ?? '')]];
}
