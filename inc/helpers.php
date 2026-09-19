<?php
/**
 * Fungsi bantu aplikasi e-GURU (MULTI-SEKOLAH / lintas sekolah-madrasah).
 *
 * Peran pengguna:
 *   super    = Super Admin  — lintas sekolah (sekolah_id NULL), mengelola sekolah & akun
 *   kepala   = Kepala Sekolah/Madrasah — terikat satu sekolah, melihat semua & pengaturan
 *   operator = Admin Operator — terikat satu sekolah, data master & administrasi
 *   guru     = Guru — terikat satu sekolah, hanya kelas & mata pelajaran yang diampu
 *
 * ATURAN PENTING: setiap query data WAJIB dibatasi `sekolah_id = sekolah_id()`
 * (termasuk saat mengambil satu baris berdasarkan id) supaya data antar sekolah tidak bocor.
 *
 * Berkas ini TIDAK boleh memanggil db() saat di-include (dipanggil secara lazy).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// ------------------------------------------------------------------ dasar

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Tanggal valid (YYYY-MM-DD) atau null. */
function tgl_ok($v): ?string
{
    $v = trim((string) $v);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : null;
}

function bulan_id(int $n): string
{
    $b = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return $b[$n] ?? '-';
}

function hari_id(?string $ymd): string
{
    if (!$ymd) {
        return '-';
    }
    $ts = strtotime((string) $ymd);
    if ($ts === false) {
        return '-';
    }
    $h = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
          'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
    return $h[date('l', $ts)] ?? '-';
}

function tanggal_id(?string $ymd, bool $denganHari = false): string
{
    if (!$ymd) {
        return '-';
    }
    $ts = strtotime((string) $ymd);
    if ($ts === false) {
        return (string) $ymd;
    }
    $t = date('j', $ts) . ' ' . bulan_id((int) date('n', $ts)) . ' ' . date('Y', $ts);
    return $denganHari ? hari_id($ymd) . ', ' . $t : $t;
}

/** Angka dengan 2 desimal (koma sebagai pemisah desimal). */
function r1($v): string
{
    return number_format((float) $v, 2, ',', '.');
}

/** Angka bulat dengan pemisah ribuan. */
function r0($v): string
{
    return number_format((float) $v, 0, ',', '.');
}

/**
 * Nilai untuk atribut value pada <input type="number"> — WAJIB memakai titik desimal
 * (input angka peramban menolak format "85,50" dan akan mengosongkan isiannya).
 */
function num_input($v): string
{
    if ($v === null || $v === '') {
        return '';
    }
    $s = number_format((float) $v, 2, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '' || $s === '-' ? '0' : $s;
}

/** Nilai masukan angka: null bila kosong / bukan angka. */
function num($v): ?float
{
    $s = trim(str_replace(',', '.', (string) $v));
    if ($s === '' || !is_numeric($s)) {
        return null;
    }
    return (float) $s;
}

/** Nilai 0-100 (dibulatkan 2 desimal) atau null. */
function nilai_sah($v): ?float
{
    $n = num($v);
    if ($n === null) {
        return null;
    }
    if ($n < 0) {
        $n = 0.0;
    }
    if ($n > 100) {
        $n = 100.0;
    }
    return round($n, 2);
}

// ------------------------------------------------------------------ flash & CSRF

function flash(string $pesan, string $tipe = 'ok'): void
{
    $_SESSION['flash'][] = ['pesan' => $pesan, 'tipe' => $tipe];
}

function flash_take(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    $t = $_POST['csrf'] ?? $_GET['csrf'] ?? '';
    return is_string($t) && $t !== '' && hash_equals((string) ($_SESSION['csrf'] ?? ''), $t);
}

function csrf_guard(): void
{
    if (!csrf_ok()) {
        http_response_code(419);
        exit('Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

// ------------------------------------------------------------------ pengguna & peran

function current_user(): ?array
{
    static $u = null;
    static $sudah = false;
    if ($sudah) {
        return $u;
    }
    $sudah = true;
    $id = $_SESSION['uid'] ?? null;
    if (!$id) {
        $u = null;
        return null;
    }
    $u = one('SELECT * FROM users WHERE id = ? AND aktif = 1', [(int) $id]);
    if (!$u) {
        $u = null;
        unset($_SESSION['uid']);
        return null;
    }
    return $u;
}

function peran(): string
{
    $u = current_user();
    return $u ? (string) $u['role'] : '';
}

function is_super(): bool
{
    return peran() === 'super';
}

function is_kepala(): bool
{
    return peran() === 'kepala';
}

function is_operator(): bool
{
    return peran() === 'operator';
}

function is_guru(): bool
{
    return peran() === 'guru';
}

function role_label(?string $role = null): string
{
    $role = $role ?? peran();
    switch ($role) {
        case 'super':    return 'Super Admin';
        case 'kepala':   return 'Kepala Sekolah/Madrasah';
        case 'operator': return 'Admin Operator';
        case 'guru':     return 'Guru';
    }
    return '-';
}

// ------------------------------------------------------------------ konteks sekolah

/**
 * Sekolah/madrasah yang sedang aktif.
 * - kepala, operator, guru : sekolah tempat akunnya terdaftar
 * - super                  : sekolah yang sedang dipilih (mode "Kelola Sekolah"), atau null
 *                            (tampilan lintas sekolah) bila belum memilih.
 */
function current_sekolah(bool $fresh = false): ?array
{
    static $cache = null;
    static $sudah = false;
    if (!$fresh && $sudah) {
        return $cache;
    }
    $sudah = true;
    $cache = null;

    $u = current_user();
    if (!$u) {
        return null;
    }
    if ($u['role'] === 'super') {
        $id = (int) ($_SESSION['ctx_sekolah_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        // Konteks hanya berlaku untuk sekolah yang disetujui DAN masih aktif.
        $cache = one('SELECT * FROM sekolah WHERE id = ? AND aktif = 1 AND verifikasi = "disetujui"', [$id]);
        if (!$cache) {
            unset($_SESSION['ctx_sekolah_id']);
        }
        return $cache;
    }
    $sid = (int) ($u['sekolah_id'] ?? 0);
    if ($sid > 0) {
        $cache = one('SELECT * FROM sekolah WHERE id = ?', [$sid]);
    }
    return $cache;
}

function sekolah_id(): int
{
    $s = current_sekolah();
    return $s ? (int) $s['id'] : 0;
}

function set_sekolah_context(?int $id): void
{
    if ($id === null || $id <= 0) {
        unset($_SESSION['ctx_sekolah_id']);
    } else {
        $_SESSION['ctx_sekolah_id'] = (int) $id;
    }
    current_sekolah(true);
    settings_all(true);
}

/** Nama sekolah untuk ditampilkan ("" -> penanda belum diisi). */
function nama_sekolah_tampil(?array $s = null): string
{
    $s = $s ?? current_sekolah();
    $n = $s ? trim((string) $s['nama']) : '';
    return $n !== '' ? $n : '(nama belum diisi)';
}

function sekolah_by_id(int $id): ?array
{
    return $id > 0 ? one('SELECT * FROM sekolah WHERE id = ?', [$id]) : null;
}

/** Daftar sekolah: $semua = true termasuk yang belum disetujui/nonaktif. */
function sekolah_list(bool $semua = false, string $cari = ''): array
{
    $w = [];
    $p = [];
    if (!$semua) {
        $w[] = 'aktif = 1 AND verifikasi = "disetujui"';
    }
    if ($cari !== '') {
        $w[] = '(nama LIKE ? OR npsn LIKE ? OR alamat LIKE ?)';
        $like = '%' . $cari . '%';
        array_push($p, $like, $like, $like);
    }
    $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
    return all('SELECT * FROM sekolah' . $where . ' ORDER BY nama', $p);
}

function jumlah_sekolah(): int
{
    return (int) val('SELECT COUNT(*) FROM sekolah WHERE verifikasi = "disetujui"', [], 0);
}

function jumlah_pendaftaran_menunggu(): int
{
    return (int) val('SELECT COUNT(*) FROM sekolah WHERE verifikasi = "menunggu"', [], 0);
}

function verifikasi_label(string $v): string
{
    switch ($v) {
        case 'disetujui': return 'Disetujui';
        case 'menunggu':  return 'Menunggu Persetujuan';
        case 'ditolak':   return 'Ditolak';
    }
    return '-';
}

/** Ringkasan isi sebuah sekolah — dipakai sebelum menonaktifkan/menghapus. */
function sekolah_dampak(int $sid): array
{
    if ($sid <= 0) {
        return [];
    }
    return [
        'guru'      => (int) val('SELECT COUNT(*) FROM guru WHERE sekolah_id = ?', [$sid], 0),
        'akun'      => (int) val('SELECT COUNT(*) FROM users WHERE sekolah_id = ?', [$sid], 0),
        'kelas'     => (int) val('SELECT COUNT(*) FROM kelas WHERE sekolah_id = ?', [$sid], 0),
        'mapel'     => (int) val('SELECT COUNT(*) FROM mapel WHERE sekolah_id = ?', [$sid], 0),
        'siswa'     => (int) val('SELECT COUNT(*) FROM siswa WHERE sekolah_id = ?', [$sid], 0),
        'nilai'     => (int) val('SELECT COUNT(*) FROM nilai WHERE sekolah_id = ?', [$sid], 0),
        'absensi'   => (int) val('SELECT COUNT(*) FROM absensi WHERE sekolah_id = ?', [$sid], 0),
        'jurnal'    => (int) val('SELECT COUNT(*) FROM jurnal WHERE sekolah_id = ?', [$sid], 0),
        'kasus'     => (int) val('SELECT COUNT(*) FROM kasus WHERE sekolah_id = ?', [$sid], 0),
        'periode'   => (int) val('SELECT COUNT(*) FROM tahun_ajaran WHERE sekolah_id = ?', [$sid], 0),
    ];
}

/** Statistik ringkas sebuah sekolah (daftar lintas sekolah). */
function sekolah_stat(int $sid): array
{
    return [
        'guru'    => (int) val('SELECT COUNT(*) FROM guru WHERE sekolah_id = ? AND aktif = 1', [$sid], 0),
        'siswa'   => (int) val('SELECT COUNT(*) FROM siswa WHERE sekolah_id = ? AND aktif = 1', [$sid], 0),
        'kelas'   => (int) val('SELECT COUNT(*) FROM kelas WHERE sekolah_id = ? AND aktif = 1', [$sid], 0),
        'mapel'   => (int) val('SELECT COUNT(*) FROM mapel WHERE sekolah_id = ? AND aktif = 1', [$sid], 0),
        'akun'    => (int) val('SELECT COUNT(*) FROM users WHERE sekolah_id = ?', [$sid], 0),
        'jurnal'  => (int) val('SELECT COUNT(*) FROM jurnal WHERE sekolah_id = ?', [$sid], 0),
        'nilai'   => (int) val('SELECT COUNT(*) FROM nilai WHERE sekolah_id = ?', [$sid], 0),
        'absensi' => (int) val('SELECT COUNT(*) FROM absensi WHERE sekolah_id = ?', [$sid], 0),
    ];
}

// ------------------------------------------------------------------ pengaturan (per sekolah)

/**
 * Peta kunci pengaturan -> kolom tabel `sekolah`.
 * Kunci lama (dari tabel `pengaturan` skema ≤ v2) dipertahankan agar halaman tidak perlu berubah.
 */
function setting_kolom(): array
{
    return [
        'nama_sekolah'       => 'nama',
        'npsn'               => 'npsn',
        'alamat_sekolah'     => 'alamat',
        'telepon_sekolah'    => 'telepon',
        'email_sekolah'      => 'email',
        'website'            => 'website',
        'kepala_sekolah'     => 'kepala_sekolah',
        'nip_kepala_sekolah' => 'nip_kepala_sekolah',
        'jenis_sekolah'      => 'jenis',
        'logo_sekolah'       => 'logo',
        'ta_aktif_id'        => 'ta_aktif_id',
        'semester_aktif_id'  => 'semester_aktif_id',
        'jumlah_formatif'    => 'jumlah_formatif',
        'jumlah_sumatif'     => 'jumlah_sumatif',
        'bobot_asas'         => 'bobot_asas',
        'batas_a'            => 'batas_a',
        'batas_b'            => 'batas_b',
        'batas_c'            => 'batas_c',
    ];
}

function setting_default(): array
{
    return [
        'nama_sekolah'       => '',
        'npsn'               => '',
        'alamat_sekolah'     => '',
        'telepon_sekolah'    => '',
        'email_sekolah'      => '',
        'website'            => '',
        'kepala_sekolah'     => '',
        'nip_kepala_sekolah' => '',
        'jenis_sekolah'      => 'umum',
        'logo_sekolah'       => '',
        'ta_aktif_id'        => '0',
        'semester_aktif_id'  => '0',
        'jumlah_formatif'    => '4',
        'jumlah_sumatif'     => '4',
        'bobot_asas'         => '50',
        'batas_a'            => '90',
        'batas_b'            => '80',
        'batas_c'            => '70',
    ];
}

/** Semua pengaturan sekolah yang sedang aktif (default bila belum ada konteks). */
function settings_all(bool $fresh = false): array
{
    static $c = null;
    if ($fresh) {
        $c = null;
    }
    if ($c !== null) {
        return $c;
    }
    $c = setting_default();
    $s = current_sekolah();
    if (!$s) {
        return $c;
    }
    foreach (setting_kolom() as $kunci => $kolom) {
        if (array_key_exists($kolom, $s) && $s[$kolom] !== null) {
            $c[$kunci] = (string) $s[$kolom];
        }
    }
    return $c;
}

function setting(string $kunci, string $default = ''): string
{
    $s = settings_all();
    if (!array_key_exists($kunci, $s)) {
        return $default;
    }
    $v = (string) $s[$kunci];
    return $v === '' ? $default : $v;
}

function set_setting(string $kunci, string $nilai): void
{
    $kolom = setting_kolom()[$kunci] ?? null;
    $sid = sekolah_id();
    if ($kolom === null || $sid <= 0) {
        return;
    }
    q('UPDATE sekolah SET ' . $kolom . ' = ? WHERE id = ?', [$nilai, $sid]);
    settings_all(true);
    current_sekolah(true);
}

function setting_int(string $kunci, int $default = 0): int
{
    $v = setting($kunci, (string) $default);
    return is_numeric($v) ? (int) $v : $default;
}

function setting_float(string $kunci, float $default = 0.0): float
{
    $v = str_replace(',', '.', setting($kunci, (string) $default));
    return is_numeric($v) ? (float) $v : $default;
}

function nama_sekolah(): string
{
    return setting('nama_sekolah', '');
}

/** Jumlah kolom penilaian Formatif (proses). */
function jumlah_formatif(): int
{
    return max(1, min(12, setting_int('jumlah_formatif', 4)));
}

/** Jumlah kolom Sumatif Lingkup Materi. */
function jumlah_sumatif(): int
{
    return max(1, min(12, setting_int('jumlah_sumatif', 4)));
}

/** Bobot Sumatif Akhir Semester (ASAS) dalam persen. */
function bobot_asas(): float
{
    return max(0.0, min(100.0, setting_float('bobot_asas', 50)));
}

/** Bobot rata-rata Sumatif Lingkup Materi (sisa dari bobot ASAS). */
function bobot_sumatif(): float
{
    return round(100.0 - bobot_asas(), 2);
}

function thresholds(): array
{
    return [
        'batas_a' => setting_float('batas_a', 90),
        'batas_b' => setting_float('batas_b', 80),
        'batas_c' => setting_float('batas_c', 70),
    ];
}

function predikat_dari(?float $nilai): string
{
    if ($nilai === null) {
        return '-';
    }
    $t = thresholds();
    if ($nilai >= $t['batas_a']) {
        return 'A';
    }
    if ($nilai >= $t['batas_b']) {
        return 'B';
    }
    if ($nilai >= $t['batas_c']) {
        return 'C';
    }
    return 'D';
}

function predikat_label(string $p): string
{
    switch ($p) {
        case 'A': return 'Sangat Baik';
        case 'B': return 'Baik';
        case 'C': return 'Cukup';
        case 'D': return 'Perlu Bimbingan';
    }
    return '-';
}

// ------------------------------------------------------------------ istilah Sekolah / Madrasah

function jenis_sekolah(): string
{
    return setting('jenis_sekolah', 'umum') === 'madrasah' ? 'madrasah' : 'umum';
}

function is_madrasah(): bool
{
    return jenis_sekolah() === 'madrasah';
}

/** Ganti kata utuh "Sekolah" -> "Madrasah" bila jenisnya madrasah. */
function istilah_sekolah(?string $teks): string
{
    $teks = (string) $teks;
    if ($teks === '') {
        return $teks;
    }
    $teks = (string) preg_replace_callback('/\x01(.*?)\x02/s', static fn(array $m): string => (string) $m[1], $teks);
    if (!is_madrasah()) {
        return $teks;
    }
    return (string) preg_replace_callback(
        '/(?<![A-Za-z])(SEKOLAH|Sekolah|sekolah)(?![A-Za-z])/u',
        static function (array $m): string {
            if ($m[1] === 'SEKOLAH') {
                return 'MADRASAH';
            }
            return $m[1] === 'Sekolah' ? 'Madrasah' : 'madrasah';
        },
        $teks
    );
}

/** Tandai potongan teks agar TIDAK ikut penyesuaian istilah (nama resmi sekolah). */
function tetap_kata(?string $teks): string
{
    $teks = (string) $teks;
    // Halaman lintas sekolah/madrasah (Kelola Sekolah, Pendaftaran, Ringkasan Super Admin)
    // memakai istilah generik sehingga tidak ada penyesuaian istilah sama sekali.
    if ($teks === '' || !is_madrasah() || !empty($GLOBALS['__vs_istilah_nonaktif'])) {
        return $teks;
    }
    return "\x01" . $teks . "\x02";
}

/** Jalankan istilah_sekolah() pada seluruh TEKS halaman HTML (isi script/style tidak disentuh). */
function terjemahkan_html(string $html): string
{
    // Halaman lintas sekolah (mengelola sekolah umum DAN madrasah sekaligus) memakai
    // istilah generik "Sekolah" — jangan diterjemahkan agar tidak muncul "Madrasah/Madrasah".
    if ($html === '' || !is_madrasah() || !empty($GLOBALS['__vs_istilah_nonaktif'])) {
        return $html;
    }
    if (stripos($html, 'sekolah') === false) {
        return $html;
    }
    $lindungi = [];
    $html = (string) preg_replace_callback('/\x01(.*?)\x02/s', static function (array $m) use (&$lindungi): string {
        $kunci = "\x03" . count($lindungi) . "\x03";
        $lindungi[$kunci] = (string) $m[1];
        return $kunci;
    }, $html);

    $bagian = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($bagian === false) {
        return $html;
    }
    $keluar = '';
    $dalamScript = false;
    $dalamStyle = false;
    foreach ($bagian as $i => $potong) {
        if ($i % 2 === 1) {
            $tag = strtolower($potong);
            if (strncmp($tag, '<script', 7) === 0) {
                $dalamScript = true;
            } elseif (strncmp($tag, '</script', 8) === 0) {
                $dalamScript = false;
            } elseif (strncmp($tag, '<style', 6) === 0) {
                $dalamStyle = true;
            } elseif (strncmp($tag, '</style', 7) === 0) {
                $dalamStyle = false;
            } else {
                $potong = (string) preg_replace_callback(
                    '/\b(placeholder|title|alt)="([^"]*)"/i',
                    static fn(array $m): string => $m[1] . '="' . e(istilah_sekolah($m[2])) . '"',
                    $potong
                );
            }
            $keluar .= $potong;
            continue;
        }
        $keluar .= ($dalamScript || $dalamStyle) ? $potong : istilah_sekolah($potong);
    }
    if ($lindungi) {
        $keluar = strtr($keluar, $lindungi);
    }
    return $keluar;
}

function logo_sekolah(): string
{
    return trim(setting('logo_sekolah', ''));
}

function ada_logo(): bool
{
    return logo_sekolah() !== '';
}

// ------------------------------------------------------------------ tahun ajaran & semester (per sekolah)

function ta_list(bool $aktifSaja = false): array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return [];
    }
    return all('SELECT * FROM tahun_ajaran WHERE sekolah_id = ?' . ($aktifSaja ? ' AND aktif = 1' : '') . ' ORDER BY nama DESC', [$sid]);
}

/** Tahun ajaran milik sekolah aktif (anti-IDOR: id dari sekolah lain diabaikan). */
function ta_by_id(int $id): ?array
{
    $sid = sekolah_id();
    if ($id <= 0 || $sid <= 0) {
        return null;
    }
    return one('SELECT * FROM tahun_ajaran WHERE id = ? AND sekolah_id = ?', [$id, $sid]);
}

function ta_aktif(): ?array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return null;
    }
    $ta = ta_by_id(setting_int('ta_aktif_id', 0));
    if ($ta) {
        return $ta;
    }
    return one('SELECT * FROM tahun_ajaran WHERE sekolah_id = ? ORDER BY nama DESC LIMIT 1', [$sid]) ?: null;
}

/** Semester milik sekolah aktif. */
function semester_list(?int $taId = null): array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return [];
    }
    if ($taId !== null && $taId > 0) {
        return all('SELECT * FROM semester WHERE ta_id = ? ORDER BY urutan, id', [$taId]);
    }
    return all('SELECT s.* FROM semester s JOIN tahun_ajaran t ON t.id = s.ta_id
                WHERE t.sekolah_id = ? ORDER BY s.ta_id, s.urutan, s.id', [$sid]);
}

function semester_by_id(int $id): ?array
{
    $sid = sekolah_id();
    if ($id <= 0 || $sid <= 0) {
        return null;
    }
    return one('SELECT s.* FROM semester s JOIN tahun_ajaran t ON t.id = s.ta_id
                WHERE s.id = ? AND t.sekolah_id = ?', [$id, $sid]);
}

function semester_aktif(): ?array
{
    $sem = semester_by_id(setting_int('semester_aktif_id', 0));
    if ($sem) {
        return $sem;
    }
    $ta = ta_aktif();
    if ($ta) {
        return one('SELECT * FROM semester WHERE ta_id = ? ORDER BY urutan, id LIMIT 1', [(int) $ta['id']]) ?: null;
    }
    return null;
}

/**
 * Tahun ajaran & semester yang dipakai halaman (bisa dipilih lewat ?ta=&sem=,
 * bila kosong memakai periode aktif). Semester selalu dipastikan milik tahun ajaran itu.
 */
function konteks_ajaran(): array
{
    $ta = ta_by_id((int) ($_GET['ta'] ?? 0)) ?? ta_aktif();
    $sem = semester_by_id((int) ($_GET['sem'] ?? 0));
    if (!$ta) {
        return ['ta' => null, 'sem' => null];
    }
    if (!$sem || (int) $sem['ta_id'] !== (int) $ta['id']) {
        $sem = one('SELECT * FROM semester WHERE ta_id = ? ORDER BY urutan, id LIMIT 1', [(int) $ta['id']]);
    }
    return ['ta' => $ta, 'sem' => $sem ?: null];
}

/** Potongan query string ta/sem untuk menjaga pilihan saat berpindah halaman. */
function ajaran_qs(?array $ta, ?array $sem, string $prefix = '?'): string
{
    $p = [];
    if ($ta) {
        $p['ta'] = (int) $ta['id'];
    }
    if ($sem) {
        $p['sem'] = (int) $sem['id'];
    }
    return $p ? $prefix . http_build_query($p) : '';
}

function ta_nama(?array $ta): string
{
    return $ta ? (string) $ta['nama'] : '-';
}

function sem_nama(?array $sem): string
{
    return $sem ? (string) $sem['nama'] : '-';
}

// ------------------------------------------------------------------ master data (per sekolah aktif)

function kelas_list(bool $aktifSaja = true): array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return [];
    }
    return all('SELECT k.*, (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.aktif = 1) AS jml_siswa,
                       (SELECT COUNT(*) FROM guru_kelas gk WHERE gk.kelas_id = k.id) AS jml_guru,
                       (SELECT nama FROM guru g WHERE g.id = k.wali_id) AS wali_nama
                FROM kelas k WHERE k.sekolah_id = ?' . ($aktifSaja ? ' AND k.aktif = 1' : '') . '
                ORDER BY k.urutan, k.nama', [$sid]);
}

function kelas_by_id(int $id): ?array
{
    $sid = sekolah_id();
    if ($id <= 0 || $sid <= 0) {
        return null;
    }
    return one('SELECT * FROM kelas WHERE id = ? AND sekolah_id = ?', [$id, $sid]);
}

function kelas_nama(int $id): string
{
    $k = kelas_by_id($id);
    return $k ? (string) $k['nama'] : '-';
}

function mapel_list(bool $aktifSaja = true): array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return [];
    }
    return all('SELECT m.*, (SELECT COUNT(*) FROM guru_mapel gm WHERE gm.mapel_id = m.id) AS jml_guru
                FROM mapel m WHERE m.sekolah_id = ?' . ($aktifSaja ? ' AND m.aktif = 1' : '') . '
                ORDER BY m.urutan, m.nama', [$sid]);
}

function mapel_by_id(int $id): ?array
{
    $sid = sekolah_id();
    if ($id <= 0 || $sid <= 0) {
        return null;
    }
    return one('SELECT * FROM mapel WHERE id = ? AND sekolah_id = ?', [$id, $sid]);
}

function mapel_nama(int $id): string
{
    $m = mapel_by_id($id);
    return $m ? (string) $m['nama'] : '-';
}

function guru_list(bool $aktifSaja = true): array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return [];
    }
    return all('SELECT * FROM guru WHERE sekolah_id = ?' . ($aktifSaja ? ' AND aktif = 1' : '') . ' ORDER BY nama', [$sid]);
}

function guru_by_id(int $id): ?array
{
    $sid = sekolah_id();
    if ($id <= 0 || $sid <= 0) {
        return null;
    }
    return one('SELECT * FROM guru WHERE id = ? AND sekolah_id = ?', [$id, $sid]);
}

function guru_nama(int $id): string
{
    $g = guru_by_id($id);
    return $g ? (string) $g['nama'] : '-';
}

/** Kelas yang diajar seorang guru (daftar id) — di sekolah aktif. */
function guru_kelas_ids(int $guruId): array
{
    $sid = sekolah_id();
    if ($guruId <= 0 || $sid <= 0) {
        return [];
    }
    return array_map('intval', array_column(all('SELECT gk.kelas_id FROM guru_kelas gk
        JOIN kelas k ON k.id = gk.kelas_id WHERE gk.guru_id = ? AND k.sekolah_id = ?', [$guruId, $sid]), 'kelas_id'));
}

/** Mata pelajaran yang dipegang seorang guru (daftar id) — di sekolah aktif. */
function guru_mapel_ids(int $guruId): array
{
    $sid = sekolah_id();
    if ($guruId <= 0 || $sid <= 0) {
        return [];
    }
    return array_map('intval', array_column(all('SELECT gm.mapel_id FROM guru_mapel gm
        JOIN mapel m ON m.id = gm.mapel_id WHERE gm.guru_id = ? AND m.sekolah_id = ?', [$guruId, $sid]), 'mapel_id'));
}

function kelas_diampu(int $guruId = 0): array
{
    $guruId = $guruId > 0 ? $guruId : guru_id_saya();
    if ($guruId <= 0) {
        return [];
    }
    $sid = sekolah_id();
    return all('SELECT k.*, (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.aktif = 1) AS jml_siswa
                FROM kelas k JOIN guru_kelas gk ON gk.kelas_id = k.id
                WHERE gk.guru_id = ? AND k.aktif = 1 AND k.sekolah_id = ? ORDER BY k.urutan, k.nama', [$guruId, $sid]);
}

function mapel_diampu(int $guruId = 0): array
{
    $guruId = $guruId > 0 ? $guruId : guru_id_saya();
    if ($guruId <= 0) {
        return [];
    }
    $sid = sekolah_id();
    return all('SELECT m.* FROM mapel m JOIN guru_mapel gm ON gm.mapel_id = m.id
                WHERE gm.guru_id = ? AND m.aktif = 1 AND m.sekolah_id = ? ORDER BY m.urutan, m.nama', [$guruId, $sid]);
}

/** Daftar kelas untuk dropdown — guru hanya kelas yang diampu. */
function kelas_pilih(): array
{
    return is_guru() ? kelas_diampu() : kelas_list(true);
}

function mapel_pilih(): array
{
    return is_guru() ? mapel_diampu() : mapel_list(true);
}

function boleh_kelas(int $kelasId): bool
{
    if (!is_guru()) {
        return true;
    }
    return in_array($kelasId, guru_kelas_ids(guru_id_saya()), true);
}

function boleh_mapel(int $mapelId): bool
{
    if (!is_guru()) {
        return true;
    }
    return in_array($mapelId, guru_mapel_ids(guru_id_saya()), true);
}

function siswa_list(int $kelasId = 0, string $cari = '', bool $aktifSaja = true): array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return [];
    }
    $w = ['s.sekolah_id = ?'];
    $p = [$sid];
    if ($aktifSaja) {
        $w[] = 's.aktif = 1';
    }
    if ($kelasId > 0) {
        $w[] = 's.kelas_id = ?';
        $p[] = $kelasId;
    }
    if ($cari !== '') {
        $w[] = '(s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR s.nama_wali LIKE ?)';
        $like = '%' . $cari . '%';
        array_push($p, $like, $like, $like, $like);
    }
    return all('SELECT s.*, k.nama AS kelas_nama FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
                WHERE ' . implode(' AND ', $w) . ' ORDER BY k.urutan, k.nama, s.nama', $p);
}

function siswa_by_id(int $id): ?array
{
    $sid = sekolah_id();
    if ($id <= 0 || $sid <= 0) {
        return null;
    }
    return one('SELECT * FROM siswa WHERE id = ? AND sekolah_id = ?', [$id, $sid]);
}

function siswa_kelas(int $kelasId, bool $aktifSaja = true): array
{
    $sid = sekolah_id();
    if ($kelasId <= 0 || $sid <= 0) {
        return [];
    }
    return all('SELECT * FROM siswa WHERE kelas_id = ? AND sekolah_id = ?' . ($aktifSaja ? ' AND aktif = 1' : '') . ' ORDER BY nama',
        [$kelasId, $sid]);
}

/** Guru yang mengampu pasangan kelas + mata pelajaran (mis. untuk tanda tangan lembar cetak). */
function guru_pengampu(int $kelasId, int $mapelId): ?array
{
    $sid = sekolah_id();
    if ($kelasId <= 0 || $mapelId <= 0 || $sid <= 0) {
        return null;
    }
    return one('SELECT g.* FROM guru g
                JOIN guru_kelas gk ON gk.guru_id = g.id AND gk.kelas_id = ?
                JOIN guru_mapel gm ON gm.guru_id = g.id AND gm.mapel_id = ?
                WHERE g.aktif = 1 AND g.sekolah_id = ? ORDER BY g.nama LIMIT 1', [$kelasId, $mapelId, $sid]);
}

/** Benar bila akun guru ini BELUM ditugaskan ke kelas mana pun (peran guru saja). */
function guru_tanpa_kelas(): bool
{
    if (!is_guru()) {
        return false;
    }
    return guru_id_saya() <= 0 || !kelas_diampu();
}

/** Benar bila akun guru ini BELUM ditugaskan ke mata pelajaran mana pun. */
function guru_tanpa_mapel(): bool
{
    if (!is_guru()) {
        return false;
    }
    return guru_id_saya() <= 0 || !mapel_diampu();
}

/**
 * Kartu bantuan (bukan galat 403) ketika akun guru belum punya penugasan kelas/mapel.
 * Dipakai halaman pengisian (daftar hadir, nilai, jurnal) supaya guru tidak menemui
 * pesan "Kelas itu tidak Anda ampu" hanya karena penugasannya belum diatur admin.
 */
function kartu_tanpa_penugasan(string $judul, string $pesan): void
{
    ?>
    <div class="card">
      <div class="empty">
        <?= ikon('users', 34) ?>
        <h3><?= e($judul) ?></h3>
        <p><?= e($pesan) ?></p>
        <div class="flex" style="justify-content:center">
          <a class="btn btn-ghost" href="index.php"><?= ikon('dashboard', 16) ?> Kembali ke Dashboard</a>
          <?php if (can_buka_akun()) { ?>
            <a class="btn btn-ghost" href="guru.php"><?= ikon('users', 16) ?> Lihat Data Guru</a>
          <?php } ?>
        </div>
        <p class="hint" style="margin-top:14px">
          Admin Operator atau Super Admin dapat menetapkan <b>kelas yang diajar</b> dan
          <b>mata pelajaran yang dipegang</b> pada menu <b>Data Guru → Ubah</b>.
        </p>
      </div>
    </div>
    <?php
}

/** Halaman master hanya untuk super (dalam konteks) & operator — dipakai untuk menyembunyikan tautan. */
function can_buka_akun(): bool
{
    return boleh_master();
}

// ------------------------------------------------------------------ data guru yang sedang masuk

function guru_saya(): ?array
{
    static $c = false;
    static $g = null;
    if ($c) {
        return $g;
    }
    $c = true;
    $u = current_user();
    $gid = (int) ($u['guru_id'] ?? 0);
    $g = $gid > 0 ? one('SELECT * FROM guru WHERE id = ?', [$gid]) : null;
    return $g;
}

function guru_id_saya(): int
{
    $g = guru_saya();
    return $g ? (int) $g['id'] : 0;
}

// ------------------------------------------------------------------ nilai (Kurikulum Merdeka)

/** Daftar nilai satu kelas+mapel+periode di sekolah aktif: [siswa_id => [...kolom]]. */
function nilai_map(int $kelasId, int $mapelId, int $taId, int $semId): array
{
    if ($kelasId <= 0 || $mapelId <= 0 || $taId <= 0 || $semId <= 0 || sekolah_id() <= 0) {
        return [];
    }
    $rows = all('SELECT siswa_id, jenis, kolom, nilai FROM nilai
                 WHERE sekolah_id = ? AND kelas_id = ? AND mapel_id = ? AND ta_id = ? AND semester_id = ?',
        [sekolah_id(), $kelasId, $mapelId, $taId, $semId]);
    $map = [];
    foreach ($rows as $r) {
        $sid = (int) $r['siswa_id'];
        if (!isset($map[$sid])) {
            $map[$sid] = ['formatif' => [], 'sumatif' => [], 'asas' => null];
        }
        $v = $r['nilai'] === null ? null : (float) $r['nilai'];
        if ($r['jenis'] === 'formatif') {
            $map[$sid]['formatif'][(int) $r['kolom']] = $v;
        } elseif ($r['jenis'] === 'sumatif') {
            $map[$sid]['sumatif'][(int) $r['kolom']] = $v;
        } else {
            $map[$sid]['asas'] = $v;
        }
    }
    return $map;
}

/** Rata-rata nilai yang terisi (null bila belum ada). */
function rata(array $angka): ?float
{
    $isi = [];
    foreach ($angka as $v) {
        if ($v !== null && $v !== '') {
            $isi[] = (float) $v;
        }
    }
    return $isi ? round(array_sum($isi) / count($isi), 2) : null;
}

/**
 * NILAI AKHIR (Kurikulum Merdeka):
 *   rata-rata Sumatif Lingkup Materi (bobot 100 - bobot ASAS) + Sumatif Akhir Semester (bobot ASAS).
 * Nilai FORMATIF tidak dijumlahkan (hanya penilaian proses).
 * Bila salah satu komponen belum ada, komponen yang ada dipakai apa adanya.
 */
function nilai_akhir(array $sumatif, ?float $asas, ?float $bobotAsas = null): ?float
{
    $rataS = rata($sumatif);
    $b = $bobotAsas === null ? bobot_asas() : max(0.0, min(100.0, $bobotAsas));
    if ($rataS === null && $asas === null) {
        return null;
    }
    if ($rataS !== null && $asas !== null) {
        return round($rataS * (100 - $b) / 100 + $asas * $b / 100, 2);
    }
    return $rataS ?? $asas;
}

// ------------------------------------------------------------------ daftar hadir

function status_hadir(): array
{
    return ['H' => 'Hadir', 'S' => 'Sakit', 'I' => 'Izin', 'A' => 'Alpa'];
}

/** Catatan pertemuan daftar hadir (hanya dari sekolah aktif). */
function absensi_sesi(int $id): ?array
{
    $sid = sekolah_id();
    if ($id <= 0 || $sid <= 0) {
        return null;
    }
    return one('SELECT a.*, k.nama AS kelas_nama, m.nama AS mapel_nama, g.nama AS guru_nama,
                       ta.nama AS ta_nama, sm.nama AS semester_nama
                FROM absensi a
                LEFT JOIN kelas k ON k.id = a.kelas_id
                LEFT JOIN mapel m ON m.id = a.mapel_id
                LEFT JOIN guru g ON g.id = a.guru_id
                LEFT JOIN tahun_ajaran ta ON ta.id = a.ta_id
                LEFT JOIN semester sm ON sm.id = a.semester_id
                WHERE a.id = ? AND a.sekolah_id = ?', [$id, $sid]);
}

function absensi_jumlah_pertemuan(int $kelasId, int $mapelId, int $taId, int $semId): int
{
    if (sekolah_id() <= 0) {
        return 0;
    }
    return (int) val('SELECT COUNT(*) FROM absensi WHERE sekolah_id = ? AND kelas_id = ? AND mapel_id = ? AND ta_id = ? AND semester_id = ?',
        [sekolah_id(), $kelasId, $mapelId, $taId, $semId], 0);
}

/** Rekap kehadiran per siswa pada satu kelas (opsional per mapel & rentang tanggal). */
function absensi_rekap(int $kelasId, int $mapelId, int $taId, int $semId, ?string $dari = null, ?string $sampai = null, int $guruId = 0): array
{
    $sid = sekolah_id();
    if ($kelasId <= 0 || $sid <= 0) {
        return [];
    }
    $w = ['a.sekolah_id = ?', 'a.kelas_id = ?', 'a.ta_id = ?', 'a.semester_id = ?'];
    $p = [$sid, $kelasId, $taId, $semId];
    if ($mapelId > 0) {
        $w[] = 'a.mapel_id = ?';
        $p[] = $mapelId;
    }
    if ($guruId > 0) {
        $w[] = 'a.guru_id = ?';
        $p[] = $guruId;
    }
    if ($dari !== null) {
        $w[] = 'a.tanggal >= ?';
        $p[] = $dari;
    }
    if ($sampai !== null) {
        $w[] = 'a.tanggal <= ?';
        $p[] = $sampai;
    }
    $rows = all('SELECT d.siswa_id, d.status, COUNT(*) AS jml
                 FROM absensi a JOIN absensi_detail d ON d.absensi_id = a.id
                 WHERE ' . implode(' AND ', $w) . '
                 GROUP BY d.siswa_id, d.status', $p);
    $out = [];
    foreach ($rows as $r) {
        $sid2 = (int) $r['siswa_id'];
        if (!isset($out[$sid2])) {
            $out[$sid2] = ['h' => 0, 's' => 0, 'i' => 0, 'a' => 0, 'total' => 0, 'persen' => null];
        }
        $st = strtolower((string) $r['status']);
        if (isset($out[$sid2][$st])) {
            $out[$sid2][$st] += (int) $r['jml'];
        }
    }
    foreach ($out as $k => $v) {
        $total = $v['h'] + $v['s'] + $v['i'] + $v['a'];
        $out[$k]['total'] = $total;
        $out[$k]['persen'] = $total > 0 ? round($v['h'] / $total * 100, 1) : null;
    }
    return $out;
}

// ------------------------------------------------------------------ kenaikan kelas & kelulusan

/** Status siswa: kunci => label. */
function status_siswa_list(): array
{
    return ['aktif' => 'Aktif', 'lulus' => 'Lulus'];
}

function status_siswa(?string $status): string
{
    $st = (string) $status;
    return status_siswa_list()[$st] ?? 'Aktif';
}

/** Benar bila kolom `status` sudah ada (basis data lama belum tentu punya). */
function ada_kolom_status_siswa(): bool
{
    static $ada = null;
    if ($ada === null) {
        $ada = false;
        foreach (all("PRAGMA table_info(siswa)") as $r) {
            if (strcasecmp((string) $r['name'], 'status') === 0) {
                $ada = true;
                break;
            }
        }
    }
    return $ada;
}

/** Daftar siswa aktif pada satu kelas (status apa pun: aktif maupun lulus). */
function siswa_kelas_status(int $kelasId): array
{
    $sid = sekolah_id();
    if ($kelasId <= 0 || $sid <= 0) {
        return [];
    }
    return all('SELECT s.*, k.nama AS kelas_nama FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
                WHERE s.kelas_id = ? AND s.sekolah_id = ? AND s.aktif = 1 ORDER BY s.nama', [$kelasId, $sid]);
}

/** Riwayat kenaikan/kelulusan sebuah sekolah (terbaru lebih dahulu). */
function kenaikan_riwayat(int $limit = 100): array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return [];
    }
    return all('SELECT r.*, s.nama AS siswa_nama, s.nis,
                       kd.nama AS dari_kelas, kk.nama AS ke_kelas
                FROM siswa_kenaikan r
                LEFT JOIN siswa s ON s.id = r.siswa_id
                LEFT JOIN kelas kd ON kd.id = r.dari_kelas_id
                LEFT JOIN kelas kk ON kk.id = r.ke_kelas_id
                WHERE r.sekolah_id = ?
                ORDER BY r.dibuat DESC, r.id DESC LIMIT ' . max(1, min(2000, $limit)), [$sid]);
}

/** Jumlah siswa berstatus tertentu (mis. lulus) pada sekolah aktif. */
function jumlah_siswa_status(string $status): int
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return 0;
    }
    if ($status === 'lulus') {
        return (int) val("SELECT COUNT(*) FROM siswa WHERE sekolah_id = ? AND aktif = 0 AND status = 'lulus'", [$sid], 0);
    }
    return (int) val("SELECT COUNT(*) FROM siswa WHERE sekolah_id = ? AND aktif = 1 AND (status = 'aktif' OR status IS NULL)", [$sid], 0);
}

// ------------------------------------------------------------------ jurnal & kasus

/**
 * Kolom kehadiran pada Jurnal Harian: kunci kolom => [kode, label].
 * Dipakai form jurnal, daftar jurnal, dan lembar cetak (urutan ini juga yang dicetak).
 */
function jurnal_kehadiran(): array
{
    return [
        'sakit'     => ['S', 'Sakit'],
        'izin'      => ['I', 'Izin'],
        'alpa'      => ['A', 'Alpa'],
        'bolos'     => ['B', 'Bolos'],
        'terlambat' => ['T', 'Terlambat'],
    ];
}

/** Ringkasan kehadiran sebuah catatan jurnal: "S 1, A 2" (tanda "-" bila tidak ada). */
function kehadiran_ringkas(array $j): string
{
    $bagian = [];
    foreach (jurnal_kehadiran() as $kolom => [$kode]) {
        $n = (int) ($j[$kolom] ?? 0);
        if ($n > 0) {
            $bagian[] = $kode . ' ' . $n;
        }
    }
    return $bagian ? implode(', ', $bagian) : '-';
}

/** Jumlah seluruh siswa yang tercatat tidak hadir/bolos/terlambat pada sebuah jurnal. */
function kehadiran_jumlah(array $j): int
{
    $n = 0;
    foreach (array_keys(jurnal_kehadiran()) as $kolom) {
        $n += (int) ($j[$kolom] ?? 0);
    }
    return $n;
}

function kasus_jenis(): array
{
    return [
        'kedisiplinan' => 'Kedisiplinan',
        'akademik'     => 'Akademik / Belajar',
        'sosial'       => 'Sosial / Pertemanan',
        'kesehatan'    => 'Kesehatan',
        'kehadiran'    => 'Kehadiran',
        'lainnya'      => 'Lainnya',
    ];
}

function kasus_status(): array
{
    return ['baru' => 'Baru', 'proses' => 'Dalam Penanganan', 'selesai' => 'Selesai'];
}

// ------------------------------------------------------------------ otorisasi & penjaga halaman

function tolak403(string $pesan): void
{
    http_response_code(403);
    exit($pesan);
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['redirect_after'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
    log_sesi_ping();   // perbarui "terakhir aktif" agar daftar online akurat (maks 1x/60 detik)
    return $u;
}

function require_super(): array
{
    $u = require_login();
    if ($u['role'] !== 'super') {
        tolak403('Halaman ini hanya untuk Super Admin.');
    }
    return $u;
}

/**
 * Pastikan ada sekolah aktif dalam konteks.
 * Super Admin yang belum memilih sekolah diarahkan ke halaman Kelola Sekolah.
 */
function require_sekolah(): array
{
    $u = require_login();
    $s = current_sekolah();
    if ($s) {
        return $s;
    }
    if ($u['role'] === 'super') {
        flash('Pilih sekolah/madrasah yang ingin dikelola terlebih dahulu.', 'warn');
        redirect('sekolah.php');
    }
    http_response_code(403);
    exit('Akun ini belum ditautkan ke sekolah/madrasah mana pun. Hubungi Super Admin.');
}

/** Admin di dalam konteks sekolah: kepala sekolah/madrasah, atau super admin yang sedang mengelola. */
function is_admin(): bool
{
    $u = current_user();
    if (!$u) {
        return false;
    }
    if ($u['role'] === 'kepala') {
        return true;
    }
    return $u['role'] === 'super' && current_sekolah() !== null;
}

function require_admin(): array
{
    $u = require_login();
    if (!is_admin()) {
        tolak403('Halaman ini hanya untuk Kepala Sekolah/Madrasah.');
    }
    require_sekolah();
    return $u;
}

/** Boleh mengubah identitas & aturan penilaian (super dalam konteks, kepala, atau Admin Operator). */
function boleh_pengaturan(): bool
{
    return is_kepala() || is_operator() || (is_super() && current_sekolah() !== null);
}

function require_pengaturan(): array
{
    $u = require_login();
    if (!boleh_pengaturan()) {
        tolak403('Pengaturan aplikasi hanya untuk Super Admin (dalam konteks sekolah), Kepala Sekolah/Madrasah, dan Admin Operator.');
    }
    require_sekolah();
    return $u;
}

/** Boleh mengubah data master: guru, siswa, kelas, mapel, tahun ajaran. */
function boleh_master(): bool
{
    return is_operator() || (is_super() && current_sekolah() !== null);
}

function require_master(): array
{
    $u = require_login();
    if (!boleh_master()) {
        tolak403('Halaman data master hanya untuk Super Admin (dalam konteks sekolah) dan Admin Operator.');
    }
    require_sekolah();
    return $u;
}

/** Boleh mengisi administrasi: daftar hadir, nilai, jurnal, kasus. */
function boleh_transaksi(): bool
{
    return is_operator() || is_guru() || (is_super() && current_sekolah() !== null);
}

function require_transaksi(): array
{
    $u = require_login();
    if (!boleh_transaksi()) {
        tolak403('Halaman ini tidak dapat diakses oleh peran Anda.');
    }
    require_sekolah();
    return $u;
}

/**
 * Melihat/mencetak data satu sekolah: semua peran kecuali guru (yang hanya kelas/mapelnya).
 * Pesan 403 dapat disesuaikan agar jelas bagi pengguna halaman tertentu.
 */
function require_staf(string $pesan = ''): array
{
    $u = require_login();
    if ($u['role'] === 'guru') {
        tolak403($pesan !== '' ? $pesan : 'Halaman ini tidak tersedia untuk akun guru.');
    }
    require_sekolah();
    return $u;
}

/**
 * Daftar Data Guru — memuat nama pengguna (akun login) guru lain, jadi akun GURU DILARANG
 * membukanya (permintaan pemilik: "hapus menu data guru dari akun guru agar tidak bisa melihat
 * akun guru lainnya"). Kepala Sekolah/Madrasah, Super Admin, dan Admin Operator tetap boleh.
 */
function require_data_guru(): array
{
    return require_staf('Daftar Data Guru (termasuk akun login guru lain) hanya untuk Super Admin, Kepala Sekolah/Madrasah, dan Admin Operator.');
}

// ------------------------------------------------------------------ unggah berkas (media proxy)

/**
 * Kirim bytes berkas ke media proxy platform. Token dibaca server-side dari
 * .vibebcoder-media-token dan tidak pernah dikembalikan/ditampilkan ke browser.
 */
function media_upload(string $bytes, string $filename): array
{
    if ($bytes === '') {
        return ['ok' => false, 'pesan' => 'Berkas kosong.'];
    }
    $tokenFile = dirname(__DIR__) . '/.vibecoder-media-token';
    if (!is_file($tokenFile) || !is_readable($tokenFile)) {
        return ['ok' => false, 'pesan' => 'Layanan penyimpanan berkas belum siap (token unggah tidak ditemukan).'];
    }
    $token = trim((string) @file_get_contents($tokenFile));
    if ($token === '') {
        return ['ok' => false, 'pesan' => 'Layanan penyimpanan berkas belum siap (token unggah kosong).'];
    }

    $endpoint = MEDIA_ENDPOINT . '?filename=' . rawurlencode($filename);
    $body = false;
    $code = 0;
    $err = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $bytes,
            CURLOPT_HTTPHEADER     => ['X-App-Media-Token: ' . $token, 'Content-Type: application/octet-stream'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 180,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = (string) curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "X-App-Media-Token: " . $token . "\r\nContent-Type: application/octet-stream\r\n",
            'content'       => $bytes,
            'timeout'       => 180,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($endpoint, false, $ctx);
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $h, $m)) {
                    $code = (int) $m[1];
                }
            }
        }
        if ($body === false) {
            $err = 'tidak dapat menghubungi layanan unggah';
        }
    }

    if ($body === false || $body === null || $body === '') {
        return ['ok' => false, 'pesan' => 'Gagal mengunggah berkas. ' . trim($err)];
    }
    $json = json_decode((string) $body, true);
    if (is_array($json) && !empty($json['ok']) && !empty($json['url'])) {
        return ['ok' => true, 'url' => (string) $json['url']];
    }
    $pesan = (is_array($json) && !empty($json['error'])) ? (string) $json['error'] : '';
    if ($code === 403) {
        return ['ok' => false, 'pesan' => 'Kuota penyimpanan berkas aplikasi sudah penuh' . ($pesan !== '' ? ' (' . $pesan . ')' : '') . '. Hubungi pengelola aplikasi.'];
    }
    if ($code === 401) {
        return ['ok' => false, 'pesan' => 'Izin unggah aplikasi tidak valid. Hubungi pengelola platform.'];
    }
    return ['ok' => false, 'pesan' => 'Unggahan ditolak: ' . ($pesan !== '' ? $pesan : 'HTTP ' . $code)];
}

/** Validasi berkas gambar (logo). */
function validasi_foto(string $namaAsli, int $ukuran, string $tmpPath): array
{
    $ext = strtolower((string) pathinfo($namaAsli, PATHINFO_EXTENSION));
    if (!in_array($ext, FOTO_EKSTENSI, true)) {
        return ['ok' => false, 'pesan' => 'Format gambar harus ' . implode(', ', FOTO_EKSTENSI) . ' (berkas Anda: ' . ($ext !== '' ? $ext : 'tanpa ekstensi') . ').'];
    }
    if ($ukuran <= 0) {
        return ['ok' => false, 'pesan' => 'Berkas gambar kosong atau gagal diunggah.'];
    }
    if ($ukuran > FOTO_MAKS_BYTE) {
        return ['ok' => false, 'pesan' => 'Ukuran gambar maksimal 15 MB (berkas Anda ' . number_format($ukuran / 1048576, 1, ',', '.') . ' MB).'];
    }
    if ($ext !== 'svg') {
        if (@getimagesize($tmpPath) === false) {
            return ['ok' => false, 'pesan' => 'Berkas ini sepertinya bukan gambar yang sah.'];
        }
    }
    return ['ok' => true];
}

/** URL foto profil pengguna (null bila belum diunggah). */
function foto_user(?array $u): ?string
{
    $f = $u['foto'] ?? null;
    return ($f !== null && trim((string) $f) !== '') ? trim((string) $f) : null;
}

/**
 * Avatar pengguna: foto profil bila ada, kalau tidak inisial nama dengan gradien.
 * $foto boleh dikosongkan — pemanggil lama (yang hanya punya nama) tetap bekerja.
 */
function avatar_html(string $nama, int $size = 34, string $kelas = 'avatar', ?string $foto = null): string
{
    $nama = trim($nama);
    $style = 'width:' . $size . 'px;height:' . $size . 'px;font-size:' . max(11, (int) round($size / 2.6)) . 'px';
    if ($foto !== null && trim($foto) !== '') {
        return '<span class="' . e($kelas) . ' avatar-img" style="' . $style . '">'
            . '<img src="' . e(trim($foto)) . '" alt="Foto ' . e($nama !== '' ? $nama : 'pengguna') . '" loading="lazy">'
            . '</span>';
    }
    $bagian = preg_split('/\s+/', $nama !== '' ? $nama : 'U') ?: ['U'];
    $inisial = '';
    foreach ($bagian as $b) {
        if ($b !== '' && strlen($inisial) < 2) {
            $inisial .= strtoupper(substr($b, 0, 1));
        }
    }
    return '<span class="' . e($kelas) . '" style="' . $style . '">' . e($inisial !== '' ? $inisial : 'U') . '</span>';
}

/** Avatar dari baris pengguna (nama + foto sekaligus). */
function avatar_user(?array $u, int $size = 34, string $kelas = 'avatar'): string
{
    return avatar_html((string) ($u['nama'] ?? ''), $size, $kelas, foto_user($u));
}

// ------------------------------------------------------------------ NIP & nama pengguna

/** NIP/NUPTK "-" atau kosong dianggap belum ada (beberapa guru boleh sama-sama kosong). */
function nip_bersih($v): string
{
    $s = trim((string) $v);
    if ($s === '') {
        return '';
    }
    if (preg_match('/^[-–—_.\s]+$/u', $s) === 1) {
        return '';
    }
    if (in_array(strtolower($s), ['n/a', 'na', 'none', 'null', 'tidak ada', 'belum ada', '-'], true)) {
        return '';
    }
    return $s;
}

function username_dari_nama(string $nama): string
{
    $u = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '.', $nama));
    $u = trim((string) preg_replace('/\.+/', '.', $u), '.');
    if ($u === '' || strlen($u) < 3) {
        $u = 'guru';
    }
    return substr($u, 0, 60);
}

/** Nama pengguna unik se-aplikasi dari data guru (pakai NIP bila ada). */
function guru_username_unik(array $guru): string
{
    $nip = nip_bersih($guru['nip'] ?? '');
    $base = $nip !== '' ? $nip : username_dari_nama((string) ($guru['nama'] ?? ''));
    $username = $base;
    $n = 1;
    while (one('SELECT id FROM users WHERE username = ?', [$username])) {
        $n++;
        $username = $base . $n;
    }
    return $username;
}

/** Kata sandi awal guru: NIP bila ada (min. 6 karakter), kalau tidak 'guru123'. */
function guru_sandi_awal(array $guru): string
{
    $nip = nip_bersih($guru['nip'] ?? '');
    return strlen($nip) >= 6 ? $nip : 'guru123';
}

// ------------------------------------------------------------------ log aktivitas & pengguna online
//
// Halaman Log Aktivitas (Super Admin) menampilkan DUA hal:
//   1. pengguna yang SEDANG ONLINE  -> tabel `sesi_aktif` (online bila aktif <= 15 menit lalu)
//   2. RIWAYAT masuk/keluar/gagal masuk -> tabel `log_aktivitas`
// Semua penulisan log dibungkus try/catch: pencatatan TIDAK BOLEH menggagalkan permintaan pengguna.

/** Jumlah menit tanpa aktivitas sebelum sebuah sesi dianggap tidak online lagi. */
const LOG_MENIT_ONLINE = 15;

/** Alamat IP pemakai. Aplikasi di belakang proxy, jadi X-Forwarded-For diutamakan. */
function log_ip(): string
{
    $fwd = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($fwd !== '') {
        foreach (explode(',', $fwd) as $bagian) {
            $ip = trim($bagian);
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }
    }
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    return $ip === '' ? '-' : substr($ip, 0, 45);
}

/** Nama peramban + sistem operasi dari User-Agent (ringkas, ditampilkan apa adanya). */
function log_peramban(?string $ua = null): string
{
    $ua = trim($ua === null ? (string) ($_SERVER['HTTP_USER_AGENT'] ?? '') : $ua);
    if ($ua === '') {
        return 'Tidak dikenal';
    }

    // Urutan penting: Edge & Opera memuat kata "Chrome", jadi diperiksa lebih dulu.
    $nama = 'Peramban lain';
    $pola = [
        ['/Edg(?:e|A|iOS)?\/([\d.]+)/i', 'Edge'],
        ['/OPR\/([\d.]+)/i', 'Opera'],
        ['/SamsungBrowser\/([\d.]+)/i', 'Samsung Internet'],
        ['/Firefox\/([\d.]+)/i', 'Firefox'],
        ['/CriOS\/([\d.]+)/i', 'Chrome'],
        ['/Chrome\/([\d.]+)/i', 'Chrome'],
        ['/Version\/([\d.]+).*Safari/i', 'Safari'],
        ['/Safari\/([\d.]+)/i', 'Safari'],
    ];
    foreach ($pola as $p) {
        if (preg_match($p[0], $ua, $m) === 1) {
            $versi = (string) ($m[1] ?? '');
            $utama = $versi !== '' ? explode('.', $versi)[0] : '';
            $nama = $p[1] . ($utama !== '' ? ' ' . $utama : '');
            break;
        }
    }

    $sistem = '';
    if (stripos($ua, 'Android') !== false) {
        $sistem = 'Android';
    } elseif (preg_match('/iPhone|iPad|iPod/i', $ua) === 1) {
        $sistem = 'iPhone/iPad';
    } elseif (stripos($ua, 'Windows') !== false) {
        $sistem = 'Windows';
    } elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) {
        $sistem = 'macOS';
    } elseif (stripos($ua, 'CrOS') !== false) {
        $sistem = 'ChromeOS';
    } elseif (stripos($ua, 'Linux') !== false) {
        $sistem = 'Linux';
    }
    $hp = preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $ua) === 1 ? 'HP' : 'Komputer';

    return $nama . ($sistem !== '' ? ' · ' . $sistem : '') . ' · ' . $hp;
}

/** Penanda sesi (hash) — id sesi mentah tidak pernah disimpan di basis data. */
function log_sesi_id(): string
{
    return substr(hash('sha256', session_id()), 0, 32);
}

/** Tanggal & jam yang mudah dibaca, mis. "15 Juni 2026 · 14:32:07". */
function waktu_id(?string $dt): string
{
    $dt = trim((string) $dt);
    if ($dt === '') {
        return '-';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return $dt;
    }
    return tanggal_id(date('Y-m-d', $ts)) . ' &middot; ' . date('H:i:s', $ts);
}

/**
 * Catat satu aktivitas ke log_aktivitas.
 *
 * @param string     $aktivitas  'masuk' | 'keluar' | 'gagal'
 * @param array|null $user       baris users (boleh dari current_user()) — null bila nama tidak dikenal
 * @param string     $namaManual nama yang diketik saat masuk (untuk percobaan gagal)
 * @param string     $keterangan alasan singkat (mis. "kata sandi salah")
 */
function log_aktivitas_catat(string $aktivitas, ?array $user = null, string $namaManual = '', string $keterangan = ''): void
{
    try {
        $uid = $user ? (int) ($user['id'] ?? 0) : 0;
        $nama = $user ? trim((string) ($user['nama'] ?? '')) : '';
        if ($nama === '') {
            $nama = $namaManual !== '' ? $namaManual : '(nama tidak dikenal)';
        }
        $username = $user ? trim((string) ($user['username'] ?? '')) : $namaManual;
        $role = $user ? (string) ($user['role'] ?? '') : '';
        $sid = $user ? (int) ($user['sekolah_id'] ?? 0) : 0;
        $sekolah = '';
        if ($sid > 0) {
            $sekolah = trim((string) ($user['sekolah_nama'] ?? ''));
            if ($sekolah === '') {
                $sekolah = (string) val('SELECT nama FROM sekolah WHERE id = ?', [$sid], '');
            }
        }
        q('INSERT INTO log_aktivitas (user_id, nama, username, role, sekolah_id, sekolah, aktivitas, keterangan, ip, peramban, waktu)
           VALUES (?,?,?,?,?,?,?,?,?,?,?)', [
            $uid > 0 ? $uid : null,
            $nama,
            $username !== '' ? $username : null,
            $role !== '' ? $role : null,
            $sid > 0 ? $sid : null,
            $sekolah !== '' ? $sekolah : null,
            $aktivitas,
            $keterangan !== '' ? $keterangan : null,
            log_ip(),
            log_peramban(),
            date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        // Diabaikan dengan sengaja: kegagalan mencatat log tidak boleh menghalangi pengguna.
    }
}

/** Mulai sesi: daftarkan sebagai pengguna online + catat aktivitas "masuk". */
function log_sesi_mulai(array $u): void
{
    $_SESSION['__log_ping'] = time();
    try {
        $sid = log_sesi_id();
        $now = date('Y-m-d H:i:s');
        // Sesi dengan penanda sama (mis. masuk ulang di peramban yang sama) dimulai dari awal.
        q('DELETE FROM sesi_aktif WHERE id = ?', [$sid]);
        q('INSERT INTO sesi_aktif (id, user_id, ip, peramban, mulai, terakhir) VALUES (?,?,?,?,?,?)',
            [$sid, (int) $u['id'], log_ip(), log_peramban(), $now, $now]);
    } catch (Throwable $e) {
    }
    log_aktivitas_catat('masuk', $u);
}

/**
 * Perbarui waktu aktif sesi (dipanggil dari require_login()).
 * Ditulis paling sering sekali per 60 detik agar tidak membebani basis data.
 */
function log_sesi_ping(): void
{
    $u = current_user();
    if (!$u) {
        return;
    }
    $kini = time();
    if ($kini - (int) ($_SESSION['__log_ping'] ?? 0) < 60) {
        return;
    }
    $_SESSION['__log_ping'] = $kini;
    try {
        $sid = log_sesi_id();
        $now = date('Y-m-d H:i:s');
        $ip = log_ip();
        $pb = log_peramban();
        // Sesi lama (dibuat sebelum fitur ini ada) atau sesi baru didaftarkan sekalian.
        q('INSERT OR IGNORE INTO sesi_aktif (id, user_id, ip, peramban, mulai, terakhir) VALUES (?,?,?,?,?,?)',
            [$sid, (int) $u['id'], $ip, $pb, $now, $now]);
        q('UPDATE sesi_aktif SET user_id = ?, terakhir = ?, ip = ?, peramban = ? WHERE id = ?',
            [(int) $u['id'], $now, $ip, $pb, $sid]);
    } catch (Throwable $e) {
    }
}

/** Akhiri sesi: catat aktivitas "keluar" + hilangkan dari daftar online. */
function log_sesi_selesai(?array $u): void
{
    try {
        if ($u) {
            log_aktivitas_catat('keluar', $u);
        }
        q('DELETE FROM sesi_aktif WHERE id = ?', [log_sesi_id()]);
    } catch (Throwable $e) {
    }
}

/**
 * Batasi riwayat percobaan masuk yang GAGAL (halaman login publik, bisa dipicu siapa saja).
 * Hanya `$maks` baris terbaru yang disimpan; riwayat masuk/keluar tidak tersentuh.
 */
function log_batasi_gagal(int $maks = 2000): void
{
    try {
        $jml = (int) val('SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas = "gagal"', [], 0);
        if ($jml > $maks) {
            q('DELETE FROM log_aktivitas WHERE aktivitas = "gagal" AND id NOT IN (
                   SELECT id FROM log_aktivitas WHERE aktivitas = "gagal" ORDER BY id DESC LIMIT ?)', [$maks]);
        }
    } catch (Throwable $e) {
    }
}

/** Catat percobaan masuk yang gagal + jaga jumlah barisnya. */
function log_gagal(?array $user, string $namaManual, string $keterangan): void
{
    log_aktivitas_catat('gagal', $user, $namaManual, $keterangan);
    log_batasi_gagal();
}

/** Buang catatan sesi yang sudah lama tidak aktif (bukan penghapusan riwayat). */
function log_sesi_bersihkan(int $hari = 1): int
{
    try {
        $st = q('DELETE FROM sesi_aktif WHERE terakhir < ?', [date('Y-m-d H:i:s', time() - max(1, $hari) * 86400)]);
        return $st->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Syarat SQL sebuah sesi dianggap "sedang online".
 * Hanya sesi dari akun yang MASIH aktif dan sekolahnya masih aktif & disetujui: akun/sekolah yang
 * sudah dinonaktifkan tidak lagi dihitung online walau sesinya belum sempat ditutup (konsisten
 * dengan aturan "sekolah nonaktif hilang dari ringkasan"). Akun super (sekolah_id NULL) selalu boleh.
 */
function log_online_syarat(string $alias = 'sx', string $aliasUser = 'u'): string
{
    return $alias . '.terakhir >= ? AND ' . $aliasUser . '.aktif = 1
        AND (' . $aliasUser . '.sekolah_id IS NULL OR EXISTS (
                SELECT 1 FROM sekolah sk WHERE sk.id = ' . $aliasUser . '.sekolah_id
                  AND sk.aktif = 1 AND sk.verifikasi = "disetujui"))';
}

/** Pengguna yang dianggap MASIH online (aktif dalam LOG_MENIT_ONLINE menit terakhir). */
function log_online(int $menit = LOG_MENIT_ONLINE): array
{
    $batas = date('Y-m-d H:i:s', time() - max(1, $menit) * 60);
    return all('SELECT sx.id AS sesi, sx.ip, sx.peramban, sx.mulai, sx.terakhir,
                       u.id AS user_id, u.nama, u.username, u.role, u.sekolah_id, u.foto,
                       s.nama AS sekolah_nama
                FROM sesi_aktif sx
                JOIN users u ON u.id = sx.user_id
                LEFT JOIN sekolah s ON s.id = u.sekolah_id
                WHERE ' . log_online_syarat() . '
                ORDER BY sx.terakhir DESC, u.nama', [$batas]);
}

/** Berapa pengguna yang sedang online (dipakai lencana pada menu sidebar). */
function log_jumlah_online(int $menit = LOG_MENIT_ONLINE): int
{
    try {
        $batas = date('Y-m-d H:i:s', time() - max(1, $menit) * 60);
        return (int) val('SELECT COUNT(*) FROM sesi_aktif sx JOIN users u ON u.id = sx.user_id
                          WHERE ' . log_online_syarat(), [$batas], 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/** Ringkasan asal sekolah/madrasah seorang pengguna pada daftar log. */
function log_sekolah_label(?string $role, ?string $sekolah): string
{
    $sekolah = trim((string) $sekolah);
    if ($sekolah !== '') {
        return $sekolah;
    }
    return (string) $role === 'super' ? 'Lintas sekolah/madrasah' : '-';
}

// ------------------------------------------------------------------ ekspor CSV

/**
 * Kirim CSV (pemisah ";" + BOM supaya Excel membaca UTF-8 dengan benar).
 *
 * @param array<int,string>            $header
 * @param array<int,array<int,scalar>> $rows
 */
function csv_kirim(string $namaBerkas, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $namaBerkas . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header, ';');
    foreach ($rows as $r) {
        fputcsv($out, $r, ';');
    }
    fclose($out);
    exit;
}
