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

/**
 * Tanggal dari BERKAS IMPOR (Excel/CSV) → `Y-m-d`, atau null bila tidak bisa dikenali.
 *
 * Kenapa perlu (jangan disederhanakan menjadi tgl_ok()): sel tanggal pada Excel/CSV bisa berbunyi
 * apa saja — "12/04/2011", "12-4-2011", "2011/04/12", "12 April 2011", atau angka serial Excel
 * ("40615"). `tgl_ok()` hanya menerima ISO sehingga tanggal seperti itu DULU hilang tanpa pesan.
 *
 * Aturan pembacaan: format ISO `Y-m-d`/`Y/m/d` dan yang memuat nama bulan dikenali lebih dulu;
 * untuk `a/b/c` dipakai **hari lebih dulu** (kebiasaan Indonesia — 12/04/2011 = 12 April 2011),
 * KECUALI angka pertama > 12 (jelas bulan lebih dulu) atau angka KEDUA > 12 (jelas hari lebih dulu).
 * Hasilnya diperiksa dengan checkdate() supaya tanggal mustahil (mis. 31/02) tidak lolos.
 */
function tgl_impor($v): ?string
{
    $v = trim((string) $v);
    if ($v === '') {
        return null;
    }
    if (preg_match('/^(\d{4})[-\/\.](\d{1,2})[-\/\.](\d{1,2})/', $v, $m) === 1) {
        $y = (int) $m[1];
        $b = (int) $m[2];
        $h = (int) $m[3];
        return checkdate($b, $h, $y) ? sprintf('%04d-%02d-%02d', $y, $b, $h) : null;
    }
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $v, $m) === 1) {   // 20110412
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            ? $m[1] . '-' . $m[2] . '-' . $m[3] : null;
    }
    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})$/', $v, $m) === 1) {
        $a = (int) $m[1];
        $b = (int) $m[2];
        $y = (int) $m[3];
        if ($y < 100) {
            $y += $y < 50 ? 2000 : 1900;   // "11" → 2011, "98" → 1998
        }
        if ($a > 12 && $b <= 12) {
            $h = $a;
            $bl = $b;
        } elseif ($b > 12 && $a <= 12) {
            $h = $b;
            $bl = $a;
        } else {
            $h = $a;                       // hari lebih dulu (kebiasaan Indonesia)
            $bl = $b;
        }
        return checkdate($bl, $h, $y) ? sprintf('%04d-%02d-%02d', $y, $bl, $h) : null;
    }
    if (preg_match('/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{2,4})$/', $v, $m) === 1) {
        $nama = ['januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6,
                 'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11,
                 'desember' => 12, 'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'mei.' => 5,
                 'jun' => 6, 'jul' => 7, 'agu' => 8, 'ags' => 8, 'sep' => 9, 'sept' => 9,
                 'okt' => 10, 'nov' => 11, 'des' => 12,
                 'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6,
                 'july' => 7, 'august' => 8, 'aug' => 8, 'october' => 10, 'oct' => 10,
                 'december' => 12, 'dec' => 12];
        $bl = $nama[strtolower((string) $m[2])] ?? 0;
        $h = (int) $m[1];
        $y = (int) $m[3];
        if ($y < 100) {
            $y += 2000;
        }
        return ($bl > 0 && checkdate($bl, $h, $y)) ? sprintf('%04d-%02d-%02d', $y, $bl, $h) : null;
    }
    // Angka serial Excel (1 Jan 1900 = 1). Dipakai bila sel tanggal kehilangan formatnya.
    if (preg_match('/^\d{5}(\.\d+)?$/', $v) === 1) {
        $serial = (int) $v;
        if ($serial >= 20000 && $serial <= 80000) {
            $ts = (int) round(($serial - 25569) * 86400);   // 25569 = 1 Jan 1970 dalam serial Excel
            return gmdate('Y-m-d', $ts);
        }
    }
    return null;
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

/**
 * Nilai tanggal untuk BERKAS EKSPOR (Excel/CSV): dikembalikan sebagai `Y-m-d` bila bisa
 * dikenali. Penting karena pengimpor aplikasi lain (mis. e-PERPUS, menu Impor Data Anggota)
 * hanya menerima format ISO `YYYY-MM-DD` — teks tanggal Indonesia ("12 April 2011") DITOLAK
 * dan baris itu akan memakai tanggal hari ini. Nilai yang tidak bisa dikenali dikembalikan apa adanya.
 */
function ekspor_tgl($v): string
{
    $v = trim((string) $v);
    if ($v === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v) === 1) {
        return substr($v, 0, 10);
    }
    $ts = strtotime($v);
    return $ts === false ? $v : date('Y-m-d', $ts);
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
 * Nominal rupiah untuk TAMPILAN, berawalan "Rp. " — mis. `rupiah(AKTIVASI_BIAYA)` → **"Rp. 1.000.000"**
 * (permintaan pemilik menulis nominal dengan awalan "Rp. "). Jangan menulis nominal ke dalam teks
 * halaman: selalu lewat konstanta `AKTIVASI_BIAYA` / `AKTIVASI_BIAYA_LANGGANAN` + fungsi ini.
 */
function rupiah($v): string
{
    return 'Rp. ' . number_format(round((float) $v), 0, ',', '.');
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

// ------------------------------------------------- NILAI KOLOM FORMATIF (angka / huruf / centang)
// Permintaan pemilik (v1.36.0): "Pada lembar penilaian formatif guru bisa menginput nilai berupa
// angka, huruf atau tanda centang." Kolom **Formatif** (penilaian proses) menerima:
//   • angka 0-100  → disimpan sebagai ANGKA (perilaku lama dipertahankan),
//   • tanda centang (✓ ✔ √ v cek check centang) → disimpan sebagai '✓',
//   • huruf/teks singkat (A, B, C, D, BS, …) → disimpan sebagai huruf besar, maksimal
//     NILAI_FORMATIF_HURUF_MAKS karakter.
// Kolom Sumatif Lingkup Materi & ASAS TETAP angka (dipakai perhitungan nilai akhir) — gunakan
// nilai_sah() untuk keduanya.

/** True bila sebuah nilai adalah ANGKA (bukan huruf/tanda centang). */
function nilai_angka_ya($v): bool
{
    return $v !== null && $v !== '' && !is_array($v) && is_numeric($v);
}

/** Nilai yang hanya boleh dihitung: angka → float, selain itu null (huruf/centang/✓ dilewati). */
function nilai_angka($v): ?float
{
    return nilai_angka_ya($v) ? (float) $v : null;
}

/**
 * Nilai masuk kolom FORMATIF: angka (float) | huruf/centang (string) | null bila kosong.
 * `null` berarti sel dikosongkan → barisnya dihapus dari basis data (perilaku lama).
 */
function nilai_formatif_sah($v)
{
    $s = trim((string) $v);
    if ($s === '') {
        return null;
    }
    // Angka (boleh berkoma) tetap disimpan sebagai angka 0-100.
    $n = nilai_sah($s);
    if ($n !== null) {
        return $n;
    }
    // Ejaan tanda centang apa pun → selalu '✓'.
    $kunci = strtolower((string) preg_replace('/[\s.\-]+/', '', $s));
    if ($kunci !== '' && in_array($kunci, NILAI_FORMATIF_CENTANG, true)) {
        return '✓';
    }
    // Huruf/teks singkat: dirapikan (huruf besar, spasi ganda dirapatkan) & dibatasi panjangnya.
    $teks = strtoupper((string) preg_replace('/\s+/', ' ', $s));
    $teks = substr($teks, 0, NILAI_FORMATIF_HURUF_MAKS);
    // Isian yang sama sekali tidak memuat huruf/angka (mis. "!!!") dianggap kosong.
    if ($teks === '' || !preg_match('/[A-Z0-9]/', $teks)) {
        return null;
    }
    return $teks;
}

/** Isi atribut value pada kotak isian Formatif (angka pakai titik desimal — lihat num_input()). */
function nilai_formatif_input($v): string
{
    if ($v === null || $v === '' || is_array($v)) {
        return '';
    }
    return is_numeric($v) ? num_input($v) : (string) $v;
}

/**
 * Nilai apa adanya untuk DITAMPILKAN (daftar, lembar cetak, CSV):
 * angka → "85,50" (r1), huruf/centang → apa adanya.
 */
function nilai_tampil($v): string
{
    if ($v === null || $v === '' || is_array($v)) {
        return '';
    }
    return is_numeric($v) ? r1($v) : (string) $v;
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

/**
 * Putar (buat ulang) token CSRF — dipanggil setiap kali tingkat kepercayaan sesi berubah,
 * terutama SETELAH berhasil masuk, supaya token sesi tamu tidak berlaku lagi.
 */
function csrf_putar(): string
{
    unset($_SESSION['csrf']);
    return csrf_token();
}

/**
 * Periksa token CSRF.
 *
 * KEAMANAN (v1.73.4) — token HANYA diterima dari BADAN permintaan (`$_POST['csrf']`) atau
 * kepala `X-CSRF-Token` (jalur unggahan/JS non-form). Versi lama juga menerima `$_GET['csrf']`
 * sehingga token dapat ikut terkirim di URL; URL mudah bocor lewat Referer ke situs lain,
 * riwayat peramban, log server/proxy, dan tautan yang dibagikan — dan token yang bocor membuat
 * seluruh perlindungan CSRF runtuh. Jalur URL itu DITUTUP.
 */
function csrf_ok(): bool
{
    $t = '';
    if (isset($_POST['csrf']) && is_scalar($_POST['csrf'])) {
        $t = trim((string) $_POST['csrf']);
    } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $t = trim($_SERVER['HTTP_X_CSRF_TOKEN']);
    }
    $sah = (string) ($_SESSION['csrf'] ?? '');
    if ($t === '' || $sah === '' || strlen($t) !== strlen($sah)) {
        return false;   // perbandingan waktu-tetap + panjang token ikut diperiksa
    }
    return hash_equals($sah, $t);
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

/** Pengawas Sekolah/Madrasah: lintas sekolah binaan, hanya MELIHAT & MENCETAK. */
function is_pengawas(): bool
{
    return peran() === 'pengawas';
}

/**
 * SISWA (v1.61.0) — akun siswa yang masuk HANYA dengan NISN dan hanya boleh MELIHAT & MENCETAK
 * datanya SENDIRI (rekapan daftar hadir, nilai, dan catatan pelanggaran).
 * Akun ini dibuat otomatis saat siswa berhasil masuk pertama kali (lihat inc/siswa_akun.php).
 */
function is_siswa(): bool
{
    return peran() === 'siswa';
}

/** Daftar sekolah/madrasah binaan seorang pengawas (urut nama). */
function sekolah_binaan(int $userId, bool $aktifSaja = true): array
{
    if ($userId <= 0) {
        return [];
    }
    return all('SELECT s.* FROM pengawas_sekolah ps JOIN sekolah s ON s.id = ps.sekolah_id
                WHERE ps.user_id = ?' . ($aktifSaja ? ' AND s.aktif = 1 AND s.verifikasi = "disetujui"' : '') . '
                ORDER BY s.nama', [$userId]);
}

/**
 * Pengawas yang MEMBINA sebuah sekolah/madrasah — dasar aturan **"1 sekolah/madrasah hanya untuk
 * 1 pengawas"** (permintaan pemilik: *"Pada saat memilih madrasah binaan untuk pengawas, pastikan
 * madrasah yang sudah di pilih tidak bisa di pilih atau dicentang lagi untuk pengawas lainnya.
 * Sehingga 1 madrasah hanya untuk 1 pangawas"*).
 *
 * `$kecualiUserId` dipakai saat MENGUBAH binaan pengawas tertentu: binaan miliknya sendiri tidak
 * dianggap bentrok (kalau tidak, binaannya tidak akan pernah bisa disimpan ulang).
 *
 * @return array{id:int,nama:string}|null pengawas pembina, atau null bila belum ada yang membina
 */
function pengawas_binaan_oleh(int $sekolahId, int $kecualiUserId = 0): ?array
{
    if ($sekolahId <= 0) {
        return null;
    }
    $r = one('SELECT u.id, u.nama FROM pengawas_sekolah ps JOIN users u ON u.id = ps.user_id
              WHERE ps.sekolah_id = ? AND ps.user_id <> ? ORDER BY u.nama LIMIT 1',
        [$sekolahId, $kecualiUserId]);
    return $r ? ['id' => (int) $r['id'], 'nama' => (string) $r['nama']] : null;
}

/**
 * Peta `sekolah_id => "nama pengawas"` untuk SELURUH penugasan binaan yang ada, sehingga halaman
 * (mis. `pengawas.php`) dapat menandai pilihan yang sudah dibina pengawas lain tanpa query per baris.
 * Bila satu sekolah (karena data lama) dibina beberapa pengawas, nama-namanya digabung dengan ", ".
 *
 * @param int $kecualiUserId pengawas yang binaannya sendiri diabaikan (mis. sedang diubah)
 * @return array<int,string>
 */
function pengawas_binaan_peta(int $kecualiUserId = 0): array
{
    $peta = [];
    foreach (all('SELECT ps.sekolah_id, u.nama FROM pengawas_sekolah ps JOIN users u ON u.id = ps.user_id
                  WHERE ps.user_id <> ? ORDER BY u.nama', [$kecualiUserId]) as $r) {
        $sid = (int) $r['sekolah_id'];
        $peta[$sid] = isset($peta[$sid]) ? $peta[$sid] . ', ' . (string) $r['nama'] : (string) $r['nama'];
    }
    return $peta;
}

/**
 * Sekolah/madrasah yang (karena DATA LAMA, sebelum aturan 1-pengawas berlaku) masih dibina oleh
 * LEBIH DARI SATU pengawas. Dipakai halaman `pengawas.php` untuk memberi peringatan agar Super Admin
 * dapat merapikannya — datanya TIDAK dihapus otomatis oleh sistem.
 *
 * @return array<int,array{sekolah_id:int,sekolah:string,pengawas:string}>
 */
function pengawas_binaan_ganda(): array
{
    return all('SELECT ps.sekolah_id, s.nama AS sekolah, COUNT(*) AS jml,
                       GROUP_CONCAT(u.nama, ", ") AS pengawas
                FROM pengawas_sekolah ps
                JOIN users u ON u.id = ps.user_id
                JOIN sekolah s ON s.id = ps.sekolah_id
                GROUP BY ps.sekolah_id, s.nama
                HAVING COUNT(*) > 1
                ORDER BY s.nama');
}

/**
 * Daftar PENGAWAS BINA sebuah sekolah/madrasah — dipakai dashboard akun Kepala Sekolah/Madrasah dan
 * akun Guru supaya mereka tahu siapa pengawas pembinanya (foto + nama).
 * Permintaan pemilik: *"Pada halaman akun kepala madrasah dan akun guru tampilkan Pengawas Binanya
 * (Foto dan Nama) agar mereka tahu siapa pengawas binanya. Ditampilkan pada bagian dashboard
 * (pada bagian atas atau sesuaikan agar terlihat rapi)"*.
 *
 * Hanya pengawas yang akunnya MASIH AKTIF yang dikembalikan (akun nonaktif tidak sedang membina).
 * Normalnya satu (aturan "1 sekolah/madrasah hanya untuk 1 pengawas"), tetapi fungsi ini tetap
 * mengembalikan daftar supaya data lama yang masih punya lebih dari satu pembina tetap tampil semua.
 *
 * @return array<int,array<string,mixed>> baris `users` (id, nama, username, foto, …)
 */
function pengawas_bina_sekolah(int $sekolahId): array
{
    if ($sekolahId <= 0) {
        return [];
    }
    return all('SELECT u.* FROM pengawas_sekolah ps
                JOIN users u ON u.id = ps.user_id
                WHERE ps.sekolah_id = ? AND u.role = "pengawas" AND u.aktif = 1
                ORDER BY u.nama', [$sekolahId]);
}

/** Jumlah pengawas bina aktif sebuah sekolah (lihat pengawas_bina_sekolah()). */
function pengawas_bina_jumlah(int $sekolahId): int
{
    return count(pengawas_bina_sekolah($sekolahId));
}

// ---------------------------------------- AKUN PENGAWAS OTOMATIS DARI PENDAFTARAN SEKOLAH (v1.50.0)
// Permintaan pemilik: *"Nama Pengawas yang diinput dari halaman pendaftaran, otomatis masuk di akun
// super admin di daftar akun pengawas tapi status masih non aktif untuk ditinjau oleh super admin"*.
// Jadi nama pengawas bina yang ditulis pendaftar TIDAK berhenti sebagai identitas sekolah saja:
// sistem membuatkan akun pengawas (role `pengawas`, lintas sekolah) berstatus NONAKTIF dan
// menandainya `ditinjau = 0` (menunggu tinjauan). Super Admin cukup mengaktifkan (dan menetapkan
// kata sandi baru) pada halaman Akun Pengawas.

/** Kata sandi acak aman (tanpa huruf/angka yang mudah tertukar: 0/O, 1/I/L). */
function sandi_acak(int $panjang = 8): string
{
    $abjad = '23456789ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
    $sandi = '';
    $maks = strlen($abjad) - 1;
    for ($i = 0; $i < max(6, $panjang); $i++) {
        $sandi .= $abjad[random_int(0, $maks)];
    }
    return $sandi;
}

/**
 * Nama pengguna unik untuk akun PENGAWAS dari NAMA pengawas — memakai aturan yang SAMA dengan akun
 * guru (KATA AWAL nama, gelar/sapaan di depan dilewati, ditambah angka bila sudah dipakai):
 * "Drs. H. Ahmad Syaifuddin" → `ahmad`, `ahmad2`, … Jangan menulis ulang logikanya di halaman lain.
 */
function pengawas_username_unik(string $nama): string
{
    return guru_username_unik(['nama' => $nama]);
}

/** Gelar/singkatan yang lazim ditulis di BELAKANG nama pengawas (dipakai `nama_pengawas_kunci()`). */
function pengawas_gelar_belakang(): array
{
    return ['mpd', 'mpdi', 'spd', 'spdi', 'sag', 'sia', 'ski', 'sos', 'ssos', 'ssi', 'si', 'st',
            'se', 'sh', 'skom', 'mkom', 'mm', 'msi', 'mphi', 'sphi', 'spt', 'mp', 'ma', 'mad',
            'mi', 'ba', 'pd', 'kom', 'th', 'sarjana', 'magister', 'doktor'];
}

/**
 * KUNCI NAMA pengawas untuk mencocokkan orang yang sama pada dua pendaftaran sekolah yang berbeda:
 * huruf kecil, tanda baca dibuang, lalu gelar/sapaan (depan maupun belakang) dan inisial satu huruf
 * diabaikan — sehingga "Drs. H. Ahmad Syaifuddin, M.Pd" dan "ahmad syaifuddin, m.pd" dianggap SAMA
 * dan tidak membuat dua akun pengawas terpisah untuk satu orang.
 *
 * Bila seluruh kata nama ternyata gelar semua (mis. hanya "Drs."), fungsi ini mengembalikan '' —
 * pemanggil WAJIB memperlakukan '' sebagai "tidak dapat dicocokkan" (jangan menggabungkan semua
 * akun yang kuncinya kebetulan kosong).
 */
function nama_pengawas_kunci(string $nama): string
{
    $t = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', ' ', $nama));
    $kata = preg_split('/\s+/', trim($t)) ?: [];
    $gelar = array_merge(guru_gelar_depan(), pengawas_gelar_belakang());
    $sisa = [];
    foreach ($kata as $k) {
        if ($k === '' || in_array($k, $gelar, true)) {
            continue;
        }
        if (strlen($k) < 2) {   // inisial satu huruf (mis. "A." pada "Ahmad A. Hanunu")
            continue;
        }
        $sisa[] = $k;
    }
    return implode(' ', $sisa);
}

/**
 * Membuat (atau menemukan) AKUN PENGAWAS dari nama pengawas bina yang diisi pada PENDAFTARAN sekolah,
 * lalu menetapkan sekolah itu sebagai binaannya.
 *
 * Aturan yang dipakai:
 *   • nama kosong  → tidak ada yang dibuat (null);
 *   • nama pengawas yang SAMA (dibandingkan lewat `nama_pengawas_kunci()`, jadi beda penulisan
 *     gelar/spasi/tanda baca tetap dianggap satu orang) sudah punya akun → akun itu dipakai kembali
 *     (tidak dibuat duplikat), statusnya TIDAK diubah, dan sekolah pendaftar hanya ditambahkan ke
 *     binaannya — sehingga satu pengawas yang membina beberapa sekolah tetap satu akun;
 *   • akun baru dibuat **NONAKTIF** (`aktif = 0`) dengan **kata sandi acak** dan penanda
 *     `ditinjau = 0` → muncul di halaman Akun Pengawas sebagai "menunggu ditinjau";
 *   • binaan TIDAK ditambahkan bila sekolah itu sudah dibina pengawas lain (aturan "1 sekolah/
 *     madrasah hanya untuk 1 pengawas") — nama pembinanya dilaporkan pada hasil.
 *
 * @return array{user_id:int,nama:string,username:string,baru:bool,binaan:bool,ditolak:string}|null
 */
function pengawas_dari_pendaftaran(int $sekolahId, string $nama): ?array
{
    $nama = trim((string) preg_replace('/\s+/u', ' ', trim($nama)));
    if ($sekolahId <= 0 || $nama === '') {
        return null;
    }
    // Jakarta: cari akun pengawas yang SUDAH ada dengan kunci nama yang sama.
    $kunci = nama_pengawas_kunci($nama);
    $ada = null;
    if ($kunci !== '') {
        foreach (all('SELECT * FROM users WHERE role = "pengawas" ORDER BY id') as $r) {
            if (nama_pengawas_kunci((string) $r['nama']) === $kunci) {
                $ada = $r;
                break;
            }
        }
    }
    $baru = false;
    if ($ada) {
        $uid = (int) $ada['id'];
        $username = (string) $ada['username'];
    } else {
        $username = pengawas_username_unik($nama);
        db()->exec('BEGIN IMMEDIATE');
        try {
            q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, aktif, ditinjau, dibuat)
               VALUES (NULL,?,?,?,"pengawas",0,0,?)',
                [$username, password_hash(sandi_acak(), PASSWORD_DEFAULT), $nama, date('Y-m-d H:i:s')]);
            $uid = (int) db()->lastInsertId();
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        $baru = true;
    }

    $binaan = false;
    $ditolak = '';
    $sudah = one('SELECT 1 FROM pengawas_sekolah WHERE user_id = ? AND sekolah_id = ?', [$uid, $sekolahId]);
    if (!$sudah) {
        $lain = pengawas_binaan_oleh($sekolahId, $uid);
        if ($lain === null) {
            q('INSERT OR IGNORE INTO pengawas_sekolah (user_id, sekolah_id, dibuat) VALUES (?,?,?)',
                [$uid, $sekolahId, date('Y-m-d H:i:s')]);
            $binaan = true;
        } else {
            $ditolak = (string) $lain['nama'];
        }
    }

    return ['user_id' => $uid, 'nama' => $nama, 'username' => $username,
            'baru' => $baru, 'binaan' => $binaan, 'ditolak' => $ditolak];
}

/**
 * Akun pengawas yang MASIH MENUNGGU TINJAUAN Super Admin (`users.ditinjau = 0`) — itulah akun yang
 * dibuat otomatis dari pendaftaran sekolah/madrasah. Dipakai halaman Akun Pengawas (kartu peringatan
 * + pil per baris) dan lencana menu sidebar Super Admin.
 *
 * @return array<int,array<string,mixed>> baris `users`
 */
function pengawas_menunggu_tinjau(): array
{
    return all('SELECT * FROM users WHERE role = "pengawas" AND ditinjau = 0 ORDER BY nama');
}

/** Jumlah akun pengawas yang menunggu ditinjau (lihat pengawas_menunggu_tinjau()). */
function pengawas_jumlah_menunggu_tinjau(): int
{
    return (int) val('SELECT COUNT(*) FROM users WHERE role = "pengawas" AND ditinjau = 0', [], 0);
}

/**
 * Kartu "Pengawas Bina" untuk DASHBOARD — **hanya FOTO + NAMA** (permintaan pemilik: *"Pengawas Bina
 * yang di tampilkan di dashboard akun guru dan kepala sekolah Hanya Nama dan Foto profil saja. Nama
 * Pengguna dan terakhir masuk tidak perlu"*), beserta keterangan perannya. Dipakai akun Kepala
 * Sekolah/Madrasah dan akun Guru. Bila sekolah belum punya pengawas bina, kartunya tetap dicetak
 * dengan keterangan ramah supaya pengguna tahu keadaannya (bukan halaman kosong).
 * **Jangan menambahkan kembali nama pengguna / terakhir masuk / data lain pada kartu ini.**
 */
function kartu_pengawas_bina(): void
{
    $bina = pengawas_bina_sekolah(sekolah_id());
    ?>
    <div class="card card-pengawas" id="cardPengawasBina">
      <div class="card-head">
        <div>
          <h2><?= ikon('eye', 16) ?> Pengawas Bina</h2>
          <p><?= $bina
              ? 'Pengawas sekolah/madrasah yang membina ' . tetap_kata(nama_sekolah_tampil()) . '.'
              : 'Belum ada pengawas bina untuk ' . tetap_kata(nama_sekolah_tampil()) . '.' ?></p>
        </div>
        <?php if ($bina): ?><span class="chip"><?= count($bina) ?> pengawas</span><?php endif; ?>
      </div>
      <?php if (!$bina): ?>
        <div class="bina-kosong">
          <?= ikon('users', 22) ?>
          <span>Pengawas bina ditetapkan oleh <b>Super Admin</b> pada menu <b>Akun Pengawas</b>.
            Setelah ditetapkan, nama &amp; fotonya tampil di sini.</span>
        </div>
      <?php else: ?>
        <?php // Permintaan pemilik: "Pengawas Bina yang di tampilkan di dashboard akun guru dan kepala
              // sekolah Hanya Nama dan Foto profil saja. Nama Pengguna dan terakhir masuk tidak perlu"
              // → kartu HANYA memuat FOTO + NAMA (tanpa nama pengguna & tanpa terakhir masuk). ?>
        <div class="bina-grid">
          <?php foreach ($bina as $pw): ?>
            <div class="bina-item">
              <?= avatar_user($pw, 56, 'avatar bina-avatar') ?>
              <div class="bina-teks">
                <b><?= e((string) ($pw['nama'] ?? '-')) ?></b>
                <span class="bina-ket">Pengawas Sekolah/Madrasah</span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
}

// ============================================================================================
// PENGATURAN KARTU "GABUNG GRUP e-GURU" (permintaan pemilik, v1.48.0)
// --------------------------------------------------------------------------------------------
// *"Tambahkan menu baru di akun super admin untuk mengedit fitur Gabung Grup e-Guru. Inputan berupa:
//  Judul Grup, Link Grup, Keterangan"*
//
// Isinya bersifat TINGKAT APLIKASI (kartu tampil di dashboard semua sekolah) sehingga disimpan pada
// tabel `app_pengaturan` — bukan kolom per sekolah. Nilai bawaan diambil dari konstanta
// `GRUP_EGURU_JUDUL` / `GRUP_EGURU_URL` / `GRUP_EGURU_KET` (inc/config.php), sehingga kartu langsung
// tampil benar walaupun Super Admin belum pernah membuka halaman pengaturannya.
// ============================================================================================

/** Judul kartu grup (dapat diedit Super Admin). */
function grup_eguru_judul(): string
{
    return trim(app_setting('grup_judul', GRUP_EGURU_JUDUL));
}

/** Tautan grup WhatsApp (dapat diedit Super Admin). */
function grup_eguru_url(): string
{
    return trim(app_setting('grup_url', GRUP_EGURU_URL));
}

/** Keterangan pada kartu grup (dapat diedit Super Admin). */
function grup_eguru_ket(): string
{
    return trim(app_setting('grup_ket', GRUP_EGURU_KET));
}

/** true bila tautan grup berupa alamat http/https yang sah (dipakai sebelum menyimpan/mencetak). */
function grup_eguru_url_sah(string $url): bool
{
    $url = trim($url);
    return $url !== '' && preg_match('~^https?://~i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Simpan pengaturan kartu grup. Bagian yang tidak dikirim ($nilai === null) TIDAK diubah, sehingga
 * formulir yang hanya mengubah satu isian tidak mengosongkan isian lainnya.
 */
function grup_eguru_simpan(?string $judul, ?string $url, ?string $ket): void
{
    if ($judul !== null) {
        app_setting_set('grup_judul', substr(trim($judul), 0, GRUP_EGURU_JUDUL_MAKS));
    }
    if ($url !== null) {
        app_setting_set('grup_url', substr(trim($url), 0, GRUP_EGURU_URL_MAKS));
    }
    if ($ket !== null) {
        app_setting_set('grup_ket', substr(trim($ket), 0, GRUP_EGURU_KET_MAKS));
    }
}

/**
 * Kartu "Gabung Grup e-GURU" untuk DASHBOARD akun Kepala Sekolah/Madrasah, Admin Operator, dan Guru.
 * Permintaan pemilik: *"Pada Akun Kepala Sekolah, Operator dan Guru pada halaman dasboard tambahkan
 * Fitur Gabung Grup e-Guru (link: https://chat.whatsapp.com/CSfXdQ8NE07250AJtZ4tJL) tambahkan
 * keterangan 'Dapatkan info menarik dan update aplikasi dengan bergabung bersama grup premium
 * e-Guru'. Posisi fitur letakkan sejajar dengan kartu pengawas bina (kartu pengawas bina di dashboard
 * guru dan kepala madrasah jangan dipanjangkan)"*.
 *
 * Tautan & keterangan diambil dari konstanta `GRUP_EGURU_URL` / `GRUP_EGURU_KET` (inc/config.php) —
 * ubah di sana bila grupnya berganti, JANGAN menyalin tautannya ke halaman lain.
 * Tautan dibuka di tab baru dengan `rel="noopener noreferrer"`.
 */
function kartu_grup_eguru(): void
{
    $judul = grup_eguru_judul();
    $url = grup_eguru_url();
    $ket = grup_eguru_ket();
    ?>
    <div class="card card-grup" id="cardGrupEGuru">
      <div class="card-head">
        <div>
          <h2><?= ikon('whatsapp', 16) ?> <?= e($judul !== '' ? $judul : GRUP_EGURU_JUDUL) ?></h2>
          <?php if ($ket !== ''): ?><p><?= e($ket) ?></p><?php endif; ?>
        </div>
      </div>
      <?php // Tombol hanya dicetak bila tautannya sah (http/https) — tautan kosong/ngawur tidak pernah
            // menghasilkan tautan mati, dan pesan ramah ditampilkan sebagai gantinya. ?>
      <?php if (grup_eguru_url_sah($url)): ?>
        <a class="btn btn-wa" id="btnGrupEGuru" href="<?= e($url) ?>"
           target="_blank" rel="noopener noreferrer"><?= ikon('whatsapp', 16) ?> Gabung Grup WhatsApp</a>
      <?php else: ?>
        <p class="hint" style="margin:0">Tautan grup belum diatur. Hubungi Admin aplikasi.</p>
      <?php endif; ?>
    </div>
    <?php
}

/** true bila sekolah itu termasuk binaan pengawas tersebut. */
function pengawas_punya(int $userId, int $sekolahId): bool
{
    if ($userId <= 0 || $sekolahId <= 0) {
        return false;
    }
    return (int) val('SELECT COUNT(*) FROM pengawas_sekolah WHERE user_id = ? AND sekolah_id = ?',
        [$userId, $sekolahId], 0) > 0;
}

function role_label(?string $role = null): string
{
    $role = $role ?? peran();
    switch ($role) {
        case 'super':    return 'Super Admin';
        case 'kepala':   return 'Kepala Sekolah/Madrasah';
        case 'operator': return 'Admin Operator';
        case 'guru':     return 'Guru';
        case 'pengawas': return 'Pengawas Sekolah/Madrasah';
        case 'siswa':    return 'Siswa';
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
    if ($u['role'] === 'super' || $u['role'] === 'pengawas') {
        $id = (int) ($_SESSION['ctx_sekolah_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        // Pengawas hanya boleh masuk ke sekolah BINAANNYA (anti-IDOR).
        if ($u['role'] === 'pengawas' && !pengawas_punya((int) $u['id'], $id)) {
            unset($_SESSION['ctx_sekolah_id']);
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
/**
 * Kunci pembanding NAMA sekolah/madrasah: huruf kecil dan hanya huruf/angka.
 *
 * Dipakai untuk mencocokkan penulisan nama yang berbeda (spasi, titik, tanda hubung, huruf besar)
 * sebagai SATU sekolah — mis. "MTsS. Sirojuth Tholibiin Sidomukti Mootilango" dan
 * "MTSS SIROJUTH THOLIBIIN SIDOMUKTI MOOTILANGO" menghasilkan kunci yang sama.
 * Dipakai penyetelan awal akses menu per sekolah (tabel `menu_sekolah`).
 */
function sekolah_nama_kunci(string $nama): string
{
    $kunci = strtolower(trim($nama));
    return (string) preg_replace('/[^a-z0-9]+/', '', $kunci);
}

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
        // Akun SISWA (peran 'siswa') tidak dihitung: mereka bukan akun pengelola sekolah.
        'akun'      => (int) val('SELECT COUNT(*) FROM users WHERE sekolah_id = ? AND role <> "siswa"', [$sid], 0),
        'kelas'     => (int) val('SELECT COUNT(*) FROM kelas WHERE sekolah_id = ?', [$sid], 0),
        'mapel'     => (int) val('SELECT COUNT(*) FROM mapel WHERE sekolah_id = ?', [$sid], 0),
        'siswa'     => (int) val('SELECT COUNT(*) FROM siswa WHERE sekolah_id = ?', [$sid], 0),
        'nilai'     => (int) val('SELECT COUNT(*) FROM nilai WHERE sekolah_id = ?', [$sid], 0),
        'absensi'   => (int) val('SELECT COUNT(*) FROM absensi WHERE sekolah_id = ?', [$sid], 0),
        'jurnal'    => (int) val('SELECT COUNT(*) FROM jurnal WHERE sekolah_id = ?', [$sid], 0),
        'kasus'     => (int) val('SELECT COUNT(*) FROM kasus WHERE sekolah_id = ?', [$sid], 0),
        'notulen'   => (int) val('SELECT COUNT(*) FROM notulen WHERE sekolah_id = ?', [$sid], 0),
        'periode'   => (int) val('SELECT COUNT(*) FROM tahun_ajaran WHERE sekolah_id = ?', [$sid], 0),
    ];
}

/**
 * Apakah sandi konfirmasi HAPUS SEKOLAH sah?
 *
 * Permintaan pemilik (v1.72.3): *"Sebelum menghapus super admin akan dimintakan sandi (sandinya:
 * 181288). Tujuan sandi ini agar ketika tidak sengaja di klik hapus lembaga tidak langsung
 * terhapus"*.
 *
 * KEAMANAN (v1.73.4) — versi lama membandingkan isian dengan konstanta `SEKOLAH_HAPUS_SANDI`
 * APA ADANYA: teks sandi tersimpan di kode sumber/repositori, berlaku sama pada semua
 * pemasangan, dan tanpa batas percobaan sehingga bisa ditebak berulang (hanya 6 angka).
 * Sekarang:
 *   1. sandi disimpan sebagai **hash bcrypt** di `app_pengaturan` (`sekolah_hapus_hash`) —
 *      dibuat sekali dari nilai bawaan; teks sandi tidak lagi dipakai membandingkan;
 *   2. pemeriksaan memakai `password_verify()` (hash dinaikkan otomatis bila usang);
 *   3. percobaan dibatasi `SEKOLAH_HAPUS_COBA` kali per `SEKOLAH_HAPUS_MENIT` menit per akun,
 *      disimpan di BASIS DATA (mengosongkan cookie sesi tidak mengosongkan hitungan).
 * Bila basis data belum siap (mis. berkas ini dijalankan sendiri tanpa tabel `app_pengaturan`),
 * pemeriksaan jatuh ke nilai bawaan agar aplikasi tidak gagal — jalur normal selalu memakai hash.
 */
function sekolah_hapus_sandi_sah(?string $sandi): bool
{
    $sandi = trim((string) $sandi);
    if ($sandi === '' || sekolah_hapus_coba_habis()) {
        return false;
    }
    $hash = sekolah_hapus_hash();
    if ($hash !== '' && (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$argon2'))) {
        $ok = password_verify($sandi, $hash);
        if ($ok && password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            try {
                app_setting_set('sekolah_hapus_hash', password_hash($sandi, PASSWORD_DEFAULT));
            } catch (Throwable $e) {
                // gagal menaikkan hash bukan alasan menolak sandi yang sudah terbukti benar
            }
        }
    } else {
        $ok = hash_equals((string) SEKOLAH_HAPUS_SANDI, $sandi);   // cadangan, waktu-tetap
    }
    $ok ? sekolah_hapus_coba_reset() : sekolah_hapus_coba_catat();
    return $ok;
}

/** Hash bcrypt sandi konfirmasi hapus ('' bila belum dapat dibaca/dibuat). */
function sekolah_hapus_hash(): string
{
    try {
        $ada = app_setting('sekolah_hapus_hash', '');
        if ($ada !== '') {
            return $ada;
        }
        $hash = password_hash((string) SEKOLAH_HAPUS_SANDI, PASSWORD_DEFAULT);
        app_setting_set('sekolah_hapus_hash', $hash);
        return $hash;
    } catch (Throwable $e) {
        return '';
    }
}

/** Kunci penghitung percobaan (per akun yang sedang masuk). */
function sekolah_hapus_coba_kunci(): string
{
    return 'sekolah_hapus_coba_' . (int) (current_user()['id'] ?? 0);
}

/** Apakah jatah percobaan sandi konfirmasi hapus sudah habis? */
function sekolah_hapus_coba_habis(): bool
{
    try {
        $raw = app_setting(sekolah_hapus_coba_kunci(), '');
        if ($raw === '') {
            return false;
        }
        $bagian = explode('|', $raw);
        $awal = (int) ($bagian[0] ?? 0);
        $jml = (int) ($bagian[1] ?? 0);
        if ($awal <= 0 || time() - $awal > SEKOLAH_HAPUS_MENIT * 60) {
            return false;   // jendela waktu lewat → hitungan dianggap kosong
        }
        return $jml >= SEKOLAH_HAPUS_COBA;
    } catch (Throwable $e) {
        return false;
    }
}

/** Tambah satu percobaan sandi konfirmasi yang GAGAL. */
function sekolah_hapus_coba_catat(): void
{
    try {
        $kunci = sekolah_hapus_coba_kunci();
        $raw = app_setting($kunci, '');
        $bagian = $raw === '' ? [] : explode('|', $raw);
        $awal = (int) ($bagian[0] ?? 0);
        $jml = (int) ($bagian[1] ?? 0);
        if ($awal <= 0 || time() - $awal > SEKOLAH_HAPUS_MENIT * 60) {
            $awal = time();
            $jml = 0;
        }
        app_setting_set($kunci, $awal . '|' . ($jml + 1));
    } catch (Throwable $e) {
        // pencatatan percobaan tidak boleh menggagalkan halaman
    }
}

/** Kosongkan hitungan percobaan setelah sandi terbukti benar. */
function sekolah_hapus_coba_reset(): void
{
    try {
        q('DELETE FROM app_pengaturan WHERE kunci = ?', [sekolah_hapus_coba_kunci()]);
    } catch (Throwable $e) {
        // abaikan
    }
}

/**
 * Nonaktifkan (-$aktif = false) atau aktifkan kembali sebuah sekolah/madrasah.
 *
 * Dipakai DUA halaman (Kelola Sekolah `sekolah.php` & daftar sekolah pada dasbor `index.php`) supaya
 * aturannya satu: **tidak boleh menonaktifkan sekolah aktif terakhir**, dan konteks sekolah yang
 * sedang dibuka otomatis dilepas bila sekolah itu dinonaktifkan.
 *
 * @return array{ok:bool,pesan:string}
 */
function sekolah_set_aktif(int $id, bool $aktif): array
{
    $s = $id > 0 ? sekolah_by_id($id) : null;
    if (!$s) {
        return ['ok' => false, 'pesan' => 'Sekolah/madrasah tidak ditemukan.'];
    }
    if ($aktif) {
        q('UPDATE sekolah SET aktif = 1 WHERE id = ?', [$id]);
        return ['ok' => true, 'pesan' => 'diaktifkan kembali'];
    }
    $jumlahAktif = (int) val('SELECT COUNT(*) FROM sekolah WHERE aktif = 1 AND verifikasi = "disetujui"',
        [], 0);
    if ($jumlahAktif <= 1) {
        return ['ok' => false, 'pesan' => 'Minimal harus ada satu sekolah/madrasah aktif.'];
    }
    q('UPDATE sekolah SET aktif = 0 WHERE id = ?', [$id]);
    if (sekolah_id() === $id) {
        set_sekolah_context(null);
    }
    return ['ok' => true, 'pesan' => 'dinonaktifkan'];
}

/**
 * HAPUS PERMANEN sebuah sekolah/madrasah beserta seluruh datanya.
 *
 * ⚠️ Fungsi ini TIDAK memeriksa sandi — pemanggil WAJIB memeriksa
 * `sekolah_hapus_sandi_sah()` lebih dulu (lihat `sekolah.php` & `index.php`).
 * Isi penghapusannya dipindahkan ke sini (dari `sekolah.php`) supaya tombol Hapus pada daftar
 * sekolah di dasbor memakai JALUR YANG SAMA — jangan menulis ulang daftar tabelnya di tempat lain.
 *
 * `dampak` dihitung SEBELUM penghapusan (agar laporannya benar), dan seluruh penghapusan berjalan
 * dalam satu `BEGIN IMMEDIATE` … `COMMIT`.
 *
 * @return array{ok:bool,pesan:string,dampak:array<string,int>,akun:int}
 */
function sekolah_hapus_permanen(int $id): array
{
    $s = $id > 0 ? sekolah_by_id($id) : null;
    if (!$s) {
        return ['ok' => false, 'pesan' => 'Sekolah/madrasah tidak ditemukan.',
                'dampak' => [], 'akun' => 0];
    }
    if ((int) val('SELECT COUNT(*) FROM sekolah', [], 0) <= 1) {
        return ['ok' => false, 'pesan' => 'Sekolah/madrasah terakhir tidak dapat dihapus.',
                'dampak' => [], 'akun' => 0];
    }
    $d = sekolah_dampak($id); // dihitung SEBELUM penghapusan agar laporannya benar
    $jmlAkun = (int) val('SELECT COUNT(*) FROM users WHERE sekolah_id = ?', [$id], 0);
    db()->exec('BEGIN IMMEDIATE');
    try {
        q('DELETE FROM absensi_detail WHERE absensi_id IN (SELECT id FROM absensi WHERE sekolah_id = ?)', [$id]);
        // Notulen rapat: tabel anak (peserta & foto bukti kegiatan) dihapus lebih dulu,
        // lalu notulennya.
        q('DELETE FROM notulen_foto WHERE notulen_id IN (SELECT id FROM notulen WHERE sekolah_id = ?)', [$id]);
        q('DELETE FROM notulen_hadir WHERE notulen_id IN (SELECT id FROM notulen WHERE sekolah_id = ?)', [$id]);
        // Relasi guru → tugas tambahan. Sejak skema v30 daftar tugasnya GLOBAL (`tugas_menu`),
        // jadi yang dibersihkan hanya RELASI guru sekolah ini (daftar tugasnya tidak pernah
        // ikut terhapus). Baris `guru_tugas` yatim dari sekolah yang dihapus sebelum perbaikan
        // dulu dibiarkan (tidak mengganggu).
        q('DELETE FROM guru_tugas WHERE guru_id IN (SELECT id FROM guru WHERE sekolah_id = ?)', [$id]);
        q('DELETE FROM user_tugas WHERE user_id IN (SELECT id FROM users WHERE sekolah_id = ?)', [$id]);
        foreach (['absensi', 'nilai', 'jurnal', 'kasus', 'siswa', 'guru_mapel', 'guru_kelas', 'notulen'] as $tabel) {
            if ($tabel === 'guru_mapel') {
                q('DELETE FROM guru_mapel WHERE guru_id IN (SELECT id FROM guru WHERE sekolah_id = ?)', [$id]);
            } elseif ($tabel === 'guru_kelas') {
                q('DELETE FROM guru_kelas WHERE guru_id IN (SELECT id FROM guru WHERE sekolah_id = ?)', [$id]);
            } else {
                q('DELETE FROM ' . $tabel . ' WHERE sekolah_id = ?', [$id]);
            }
        }
        foreach (all('SELECT id FROM tahun_ajaran WHERE sekolah_id = ?', [$id]) as $ta) {
            q('DELETE FROM semester WHERE ta_id = ?', [(int) $ta['id']]);
        }
        q('DELETE FROM tahun_ajaran WHERE sekolah_id = ?', [$id]);
        q('DELETE FROM users WHERE sekolah_id = ?', [$id]);
        q('DELETE FROM kelas WHERE sekolah_id = ?', [$id]);
        q('DELETE FROM mapel WHERE sekolah_id = ?', [$id]);
        q('DELETE FROM guru WHERE sekolah_id = ?', [$id]);
        q('DELETE FROM sekolah WHERE id = ?', [$id]);
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    if (sekolah_id() === $id) {
        set_sekolah_context(null);
    }
    return ['ok' => true, 'pesan' => 'Sekolah/madrasah ' . nama_sekolah_tampil($s) . ' DIHAPUS PERMANEN: '
        . $d['kelas'] . ' kelas, ' . $d['siswa'] . ' siswa, ' . $d['guru'] . ' data guru, '
        . $d['nilai'] . ' baris nilai, ' . $d['absensi'] . ' pertemuan, ' . $d['jurnal'] . ' jurnal, '
        . $d['kasus'] . ' catatan kasus, ' . $d['notulen'] . ' notulen rapat, dan ' . $jmlAkun . ' akun.',
        'dampak' => $d, 'akun' => $jmlAkun];
}

/** Statistik ringkas sebuah sekolah (daftar lintas sekolah). */
function sekolah_stat(int $sid): array
{
    return [
        'guru'    => (int) val('SELECT COUNT(*) FROM guru WHERE sekolah_id = ? AND aktif = 1', [$sid], 0),
        'siswa'   => (int) val('SELECT COUNT(*) FROM siswa WHERE sekolah_id = ? AND aktif = 1', [$sid], 0),
        'kelas'   => (int) val('SELECT COUNT(*) FROM kelas WHERE sekolah_id = ? AND aktif = 1', [$sid], 0),
        'mapel'   => (int) val('SELECT COUNT(*) FROM mapel WHERE sekolah_id = ? AND aktif = 1', [$sid], 0),
        'akun'    => (int) val('SELECT COUNT(*) FROM users WHERE sekolah_id = ? AND role <> "siswa"', [$sid], 0),
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
        // v37: identitas lembaga dilengkapi — NSM & alamat per wilayah. WAJIB terdaftar di peta ini
        // (set_setting() hanya menulis kolom yang ada di sini; kalau tidak, isian dibuang diam-diam).
        'nsm'                => 'nsm',
        'alamat_sekolah'     => 'alamat',
        'desa'               => 'desa',
        'kecamatan'          => 'kecamatan',
        'kabupaten'          => 'kabupaten',
        'provinsi'           => 'provinsi',
        'telepon_sekolah'    => 'telepon',
        'email_sekolah'      => 'email',
        'website'            => 'website',
        'kepala_sekolah'     => 'kepala_sekolah',
        'nip_kepala_sekolah' => 'nip_kepala_sekolah',
        // v39: identitas PENGAWAS BINA (nama & NIP) — diisi saat pendaftaran sekolah (permintaan
        // pemilik) dan dapat diubah di Identitas & Pengaturan. WAJIB terdaftar di peta ini juga,
        // karena set_setting() hanya menulis kolom yang ada di sini.
        'pengawas_nama'      => 'pengawas_nama',
        'pengawas_nip'       => 'pengawas_nip',
        'jenis_sekolah'      => 'jenis',
        // v49: PILIHAN JENJANG (SD/SMP/SMA/SMK atau MI/MTs/MA/MAK) — menentukan acuan tingkat kelas
        // dan apakah sekolah memakai konsep GURU KELAS (SD/MI). WAJIB terdaftar di peta ini:
        // set_setting() hanya menulis kolom yang ada di sini.
        'jenjang_sekolah'    => 'jenjang',
        'logo_sekolah'       => 'logo',
        'ta_aktif_id'        => 'ta_aktif_id',
        'semester_aktif_id'  => 'semester_aktif_id',
        'jumlah_formatif'    => 'jumlah_formatif',
        'jumlah_sumatif'     => 'jumlah_sumatif',
        'bobot_asas'         => 'bobot_asas',
        'batas_a'            => 'batas_a',
        'batas_b'            => 'batas_b',
        'batas_c'            => 'batas_c',
        // BAGAN susunan organisasi OSIM/OSIS (skema v22) — judul & periode pada kepala bagan.
        // WAJIB terdaftar di sini: set_setting() hanya menulis kolom yang ada pada peta ini,
        // kalau tidak nilai yang dikirim pengguna dibuang diam-diam.
        'osim_bagan_judul'   => 'osim_bagan_judul',
        'osim_bagan_periode' => 'osim_bagan_periode',
        // BAGAN susunan organisasi menu PEMBINA PRAMUKA (skema v25) — kolom TERPISAH supaya judul &
        // periode bagan Pramuka tidak menimpa bagan OSIM (keduanya menu yang berbeda).
        'pramuka_bagan_judul'   => 'pramuka_bagan_judul',
        'pramuka_bagan_periode' => 'pramuka_bagan_periode',
        // BAGAN susunan organisasi menu PEMBINA PMR (skema v38) — kolom TERPISAH juga, dengan alasan
        // yang sama: judul/periode bagan PMR tidak boleh menimpa bagan OSIM maupun Pramuka.
        'pmr_bagan_judul'   => 'pmr_bagan_judul',
        'pmr_bagan_periode' => 'pmr_bagan_periode',
    ];
}

function setting_default(): array
{
    return [
        'nama_sekolah'       => '',
        'npsn'               => '',
        'nsm'                => '',
        'alamat_sekolah'     => '',
        'desa'               => '',
        'kecamatan'          => '',
        'kabupaten'          => '',
        'provinsi'           => '',
        'telepon_sekolah'    => '',
        'email_sekolah'      => '',
        'website'            => '',
        'kepala_sekolah'     => '',
        'nip_kepala_sekolah' => '',
        'pengawas_nama'      => '',
        'pengawas_nip'       => '',
        'jenis_sekolah'      => 'umum',
        // v49: jenjang dikosongkan lebih dulu (tidak ada nilai yang ditebak). Sekolah lama yang belum
        // pernah memilih jenjang tetap berjalan seperti sebelumnya — hanya saja acuan tingkat kelas
        // & konsep "Guru Kelas" belum aktif sampai jenjangnya dipilih.
        'jenjang_sekolah'    => '',
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

/**
 * Identitas kepala sekolah/madrasah dari Pengaturan.
 * CATATAN: ketiga fungsi ini ada di sini (bukan di inc/kop.php) karena halaman NON-cetak
 * (mis. notulen_form.php) juga memakainya. inc/kop.php dipakai khusus halaman cetak,
 * jadi fungsi data tidak boleh hanya ada di sana — pernah menggigit (fatal error).
 */
function kepala_nama(): string
{
    $n = trim(setting('kepala_sekolah', ''));
    return $n !== '' ? $n : '-';
}

function kepala_nip(): string
{
    return trim(setting('nip_kepala_sekolah', ''));
}

/** "Kepala Sekolah" (otomatis menjadi "Kepala Madrasah" pada jenis madrasah). */
function jabatan_kepala(): string
{
    return istilah_sekolah('Kepala Sekolah');
}

// ---------------------------------------------- DATA LEMBAGA PUBLIK (kode QR kartu pelajar, v1.71.0)
// Permintaan pemilik: kartu pelajar diberi BARCODE yang ketika dipindai menampilkan DATA
// LEMBAGA/SEKOLAH. Kode yang dipakai adalah QR berisi alamat halaman publik `lembaga.php` yang
// memuat identitas lembaga. Alamat itu memakai TOKEN rahasia (HMAC id sekolah) supaya data tidak
// bisa dibuka sembarang id, dan hanya sekolah aktif + disetujui yang ditampilkan.

/**
 * Dasar alamat aplikasi (skema + host + folder aplikasi) untuk membuat TAUTAN ABSOLUT.
 * Dibangun dari permintaan yang sedang diproses supaya benar baik pada alamat publik bawaan
 * (`…/e-guru/`) maupun bila aplikasi dipasang di folder/domain lain.
 */
function app_url_dasar(): string
{
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || !preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
        $host = 'localhost';
    }
    $berkas = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $folder = rtrim(str_replace('\\', '/', dirname($berkas)), '/');
    return ($https ? 'https' : 'http') . '://' . $host . ($folder === '' || $folder === '.' ? '' : $folder) . '/';
}

/** Token rahasia halaman data lembaga (HMAC id sekolah + kunci rahasia aplikasi). */
function lembaga_token(int $sekolahId): string
{
    if ($sekolahId <= 0) {
        return '';
    }
    return substr(hash_hmac('sha256', 'lembaga|' . $sekolahId, aktivasi_secret()), 0, 16);
}

/** Alamat (absolut) halaman data lembaga untuk kode QR kartu pelajar. */
function lembaga_tautan(int $sekolahId): string
{
    if ($sekolahId <= 0) {
        return '';
    }
    return app_url_dasar() . 'lembaga.php?k=' . $sekolahId . '-' . lembaga_token($sekolahId);
}

/** Benar bila parameter `k` (= "<id>-<token>") sah untuk sebuah sekolah. */
function lembaga_param_sah(string $k): int
{
    if (!preg_match('/^(\d{1,9})-([0-9a-f]{16})$/', trim($k), $m)) {
        return 0;
    }
    $id = (int) $m[1];
    return hash_equals(lembaga_token($id), $m[2]) ? $id : 0;
}

// ---------------------------------------------- IDENTITAS PENGAWAS BINA (skema v39)
// Permintaan pemilik: "Pada halaman pendaftaran tambahkan inputan Nama Pengawas Bina dan NIP.
// Posisi dibawah nama kepala sekolah".
// ⚠️ BEDA dengan pengawas_bina_sekolah()/pengawas_bina_jumlah() di atas — keduanya membaca AKUN
// pengawas (tabel `users` + `pengawas_sekolah`), sedangkan kedua fungsi di bawah ini membaca
// IDENTITAS sekolah (kolom `sekolah.pengawas_nama`/`pengawas_nip`) yang diisi saat pendaftaran.

/** Nama pengawas bina dari identitas sekolah — '' bila belum diisi. */
function pengawas_identitas_nama(): string
{
    return identitas_nilai(setting('pengawas_nama', ''));
}

/** NIP pengawas bina dari identitas sekolah — '' bila belum diisi. */
function pengawas_identitas_nip(): string
{
    return identitas_nilai(setting('pengawas_nip', ''));
}

// ---------------------------------------------- IDENTITAS LEMBAGA per wilayah (skema v37)
// Permintaan pemilik: identitas memuat NSM serta alamat yang terpisah (Alamat/Nama Jalan+Dusun,
// Desa/Kelurahan, Kecamatan, Kabupaten, Provinsi). Nilai yang hanya berisi tanda "-" dianggap kosong
// (lihat kop_nilai() di inc/kop.php untuk sisi cetak).

/** Nilai identitas yang dianggap terisi (kosong/spasi/"-"/"–"/"—"/"."/"_" → ''). */
function identitas_nilai(?string $v): string
{
    $v = trim((string) $v);
    return in_array($v, ['', '-', '–', '—', '.', '_'], true) ? '' : $v;
}

/** NSM lembaga (Nomor Statistik Madrasah) — '' bila belum diisi. */
function nsm_sekolah(): string
{
    return identitas_nilai(setting('nsm', ''));
}

/**
 * Alamat WILAYAH dari SATU BARIS data (tabel `sekolah` atau baris pendaftaran):
 * "Desa/Kelurahan X, Kec. Y, Kab. Z, Provinsi W". Hanya bagian yang terisi yang dicetak;
 * '' bila semuanya kosong. Dipakai halaman lintas sekolah (pendaftaran) yang menampilkan
 * data sekolah LAIN — bukan sekolah yang sedang berkonteks.
 */
function alamat_wilayah_dari(array $r): string
{
    $bagian = [];
    foreach ([['desa', 'Desa/Kelurahan '], ['kecamatan', 'Kec. '],
              ['kabupaten', 'Kab. '], ['provinsi', 'Provinsi ']] as [$kolom, $label]) {
        $v = identitas_nilai((string) ($r[$kolom] ?? ''));
        if ($v !== '') {
            $bagian[] = $label . $v;
        }
    }
    return implode(', ', $bagian);
}

/**
 * Alamat WILAYAH sekolah yang sedang aktif (baris kop dokumen cetak) — susunan sama dengan
 * alamat_wilayah_dari(), sumbernya PENGATURAN sekolah dalam konteks.
 */
function alamat_wilayah(): string
{
    return alamat_wilayah_dari([
        'desa'      => setting('desa', ''),
        'kecamatan' => setting('kecamatan', ''),
        'kabupaten' => setting('kabupaten', ''),
        'provinsi'  => setting('provinsi', ''),
    ]);
}

/** Nama KECAMATAN saja (dipakai untuk baris "tempat, tanggal" pada tanda tangan lembar cetak). */
function kecamatan_sekolah(): string
{
    return identitas_nilai(setting('kecamatan', ''));
}

/**
 * Label blok tanda tangan KEPALA sekolah/madrasah — DUA BARIS (permintaan pemilik):
 *   Mengetahui,
 *   Kepala Madrasah
 * Dijadikan dua baris supaya **nama guru dan nama kepala sekolah/madrasah menjadi sejajar/lurus**
 * pada lembar cetak: sisi kanan juga memuat dua baris (baris "tempat, tanggal" + jabatan guru),
 * sehingga garis nama kedua sisi mulai pada ketinggian yang sama.
 * Dipakai SEMUA lembar cetak lewat ttd_blok() (lihat inc/kop.php).
 */
function ttd_mengetahui(): string
{
    return "Mengetahui,\n" . jabatan_kepala();
}

/**
 * Baris "tempat, tanggal" di atas tanda tangan pada SELURUH hasil cetak (permintaan pemilik:
 * "Semua hasil cetak pada bagian tanda tangan, tepatnya diatas tanda tangan guru tambahkan tempat
 * (nama kecamatan yang diambil dari inputan identitas lembaga) kemudian sambung dengan tanggal dan
 * tahun (tanggal aktif sistem) ketika mencetak").
 *
 * Contoh: "Mootilango, 30 September 2026". Bila kecamatan belum diisi, hanya tanggalnya yang
 * dicetak supaya barisnya tidak pernah berisi tanda hubung yang janggal.
 */
function ttd_tempat_tanggal(?string $tanggal = null): string
{
    $tanggal = $tanggal === null || $tanggal === '' ? date('Y-m-d') : $tanggal;
    $tempat = kecamatan_sekolah();
    return $tempat !== '' ? $tempat . ', ' . tanggal_id($tanggal) : tanggal_id($tanggal);
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

// ------------------------------------------------------------------ pengaturan nilai PER (GURU, MAPEL)

/**
 * Baris pengaturan penilaian MILIK SEORANG GURU untuk SATU mata pelajaran (skema v31), atau null
 * bila guru itu belum mengaturnya (→ yang dipakai adalah pengaturan sekolah).
 *
 * Permintaan pemilik: "Pengaturan kolom formatif, sumatif dan bobot asas tambahkan ke akun guru
 * untuk diseting sendiri. Begitu juga Pengaturan Bobot nilai dari A, B, C, D di seting sendiri oleh
 * guru." Pilihannya: **per mata pelajaran yang diampu** dan **pengaturan guru menimpa pengaturan
 * sekolah**; kalau guru belum mengatur, pengaturan sekolah yang dipakai.
 */
function nilai_pengaturan_guru(int $guruId, int $mapelId): ?array
{
    if ($guruId <= 0 || $mapelId <= 0) {
        return null;
    }
    return one('SELECT * FROM nilai_pengaturan WHERE guru_id = ? AND mapel_id = ? AND sekolah_id = ?',
        [$guruId, $mapelId, sekolah_id()]);
}

/** Guru (id) yang pengaturan nilainya berlaku untuk mapel ini bagi pengguna yang sedang masuk. */
function nilai_guru_konteks(): int
{
    // Hanya akun GURU yang punya pengaturan penilaian sendiri (per mata pelajaran yang diampu).
    return is_guru() ? guru_id_saya() : 0;
}

/**
 * PENGATURAN NILAI YANG BERLAKU untuk sebuah mata pelajaran (dan kelas) — pengaturan GURU bila ada,
 * kalau belum → pengaturan SEKOLAH.
 *
 * Siapa "guru"-nya:
 *   1. akun guru yang sedang masuk (mereka hanya membuka kelas/mapel yang diampu) → pengaturannya
 *      sendiri;
 *   2. pemirsa lain (kepala, operator, Super Admin, pengawas) → **guru pengampu kelas+mapel itu**
 *      (`guru_pengampu()`), supaya lembar cetak/ekspor yang mereka lihat SAMA dengan tampilan guru
 *      yang mengisinya (jumlah kolom & ambang predikat tidak berbeda antara layar guru dan cetakan).
 *
 * @return array{jf:int, js:int, bobot_asas:float, batas_a:float, batas_b:float, batas_c:float,
 *               sumber:string, guru_id:int, mapel_id:int, guru_nama:string}
 */
function nilai_konteks(int $mapelId = 0, int $kelasId = 0, int $guruId = 0): array
{
    $sekolah = [
        'jf'         => jumlah_formatif(),
        'js'         => jumlah_sumatif(),
        'bobot_asas' => bobot_asas(),
        'batas_a'    => thresholds()['batas_a'],
        'batas_b'    => thresholds()['batas_b'],
        'batas_c'    => thresholds()['batas_c'],
        'sumber'     => 'sekolah',
        'guru_id'    => 0,
        'mapel_id'   => $mapelId,
        'guru_nama'  => '',
    ];
    if ($mapelId <= 0) {
        return $sekolah;
    }
    // Tentukan guru yang pengaturannya dipakai.
    $guruId = $guruId > 0 ? $guruId : nilai_guru_konteks();
    $namaGuru = '';
    if ($guruId > 0 && is_guru()) {
        $namaGuru = (string) (guru_saya()['nama'] ?? '');
    }
    if ($guruId <= 0 && $kelasId > 0) {
        $pengampu = guru_pengampu($kelasId, $mapelId);
        if ($pengampu) {
            $guruId = (int) $pengampu['id'];
            $namaGuru = (string) $pengampu['nama'];
        }
    }
    // Pemirsa non-guru juga boleh jatuh ke pengaturan guru mana pun yang sudah mengatur mapel ini,
    // supaya pengaturannya tetap terpakai walau kelas tidak diketahui (mis. tanpa filter kelas).
    if ($guruId <= 0) {
        $adaSiapa = one('SELECT p.guru_id, g.nama FROM nilai_pengaturan p
                         JOIN guru g ON g.id = p.guru_id
                         WHERE p.mapel_id = ? AND p.sekolah_id = ? ORDER BY g.nama LIMIT 1',
            [$mapelId, sekolah_id()]);
        if ($adaSiapa) {
            $guruId = (int) $adaSiapa['guru_id'];
            $namaGuru = (string) $adaSiapa['nama'];
        }
    }
    // Penting: guru_id TETAP dilaporkan walau barisnya belum ada — halaman nilai memakainya
    // untuk menentukan apakah tombol/form "Pengaturan Penilaian" perlu ditampilkan (kalau tidak,
    // guru tidak pernah bisa membuat pengaturan pertamanya).
    $sekolah['guru_id'] = $guruId;
    $sekolah['guru_nama'] = $namaGuru;
    $p = $guruId > 0 ? nilai_pengaturan_guru($guruId, $mapelId) : null;
    if (!$p) {
        return $sekolah;
    }
    return [
        'jf'         => max(1, min(12, (int) $p['jumlah_formatif'])),
        'js'         => max(1, min(12, (int) $p['jumlah_sumatif'])),
        'bobot_asas' => max(0.0, min(100.0, (float) $p['bobot_asas'])),
        'batas_a'    => (float) $p['batas_a'],
        'batas_b'    => (float) $p['batas_b'],
        'batas_c'    => (float) $p['batas_c'],
        'sumber'     => 'guru',
        'guru_id'    => $guruId,
        'mapel_id'   => $mapelId,
        'guru_nama'  => $namaGuru,
    ];
}

/** Ambang predikat dari sebuah konteks nilai (dipakai predikat_dari() saat ada mapel terpilih). */
function thresholds_konteks(array $konteks): array
{
    return [
        'batas_a' => (float) $konteks['batas_a'],
        'batas_b' => (float) $konteks['batas_b'],
        'batas_c' => (float) $konteks['batas_c'],
    ];
}

/** Kalimat keterangan ambang predikat menurut sebuah KONTEKS nilai (guru/sekolah). */
function predikat_keterangan_k(array $konteks): string
{
    return 'A (Amat Baik) ' . r0((float) $konteks['batas_a']) . ' ke atas - B (Baik) '
        . r0((float) $konteks['batas_b']) . ' ke atas - C (Cukup) ' . r0((float) $konteks['batas_c'])
        . ' ke atas - D (Kurang) di bawah ' . r0((float) $konteks['batas_c']);
}

function predikat_dari_k(array $konteks, ?float $nilai): string
{
    if ($nilai === null) {
        return '-';
    }
    $t = thresholds_konteks($konteks);
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

/** Simpan (atau ganti) pengaturan penilaian seorang guru untuk satu mata pelajaran. */
function nilai_pengaturan_simpan(int $guruId, int $mapelId, array $nilai): void
{
    if ($guruId <= 0 || $mapelId <= 0) {
        return;
    }
    $now = date('Y-m-d H:i:s');
    q('INSERT INTO nilai_pengaturan (sekolah_id, guru_id, mapel_id, jumlah_formatif, jumlah_sumatif,
                                     bobot_asas, batas_a, batas_b, batas_c, diubah)
       VALUES (?,?,?,?,?,?,?,?,?,?)
       ON CONFLICT(guru_id, mapel_id) DO UPDATE SET
         jumlah_formatif = excluded.jumlah_formatif, jumlah_sumatif = excluded.jumlah_sumatif,
         bobot_asas = excluded.bobot_asas, batas_a = excluded.batas_a, batas_b = excluded.batas_b,
         batas_c = excluded.batas_c, diubah = excluded.diubah',
        [sekolah_id(), $guruId, $mapelId,
         (int) $nilai['jf'], (int) $nilai['js'], (float) $nilai['bobot_asas'],
         (float) $nilai['batas_a'], (float) $nilai['batas_b'], (float) $nilai['batas_c'], $now]);
}

/** Hapus pengaturan guru untuk satu mapel → kembali memakai pengaturan sekolah. */
function nilai_pengaturan_hapus(int $guruId, int $mapelId): void
{
    if ($guruId <= 0 || $mapelId <= 0) {
        return;
    }
    q('DELETE FROM nilai_pengaturan WHERE guru_id = ? AND mapel_id = ? AND sekolah_id = ?',
        [$guruId, $mapelId, sekolah_id()]);
}

/** Berapa pengaturan mapel yang sudah dibuat seorang guru (untuk keterangan di halaman). */
function nilai_pengaturan_jumlah(int $guruId): int
{
    if ($guruId <= 0) {
        return 0;
    }
    return (int) val('SELECT COUNT(*) FROM nilai_pengaturan WHERE guru_id = ? AND sekolah_id = ?',
        [$guruId, sekolah_id()], 0);
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

/**
 * Keterangan tiap predikat (permintaan pemilik): A (Amat Baik), B (Baik), C (Cukup), D (Kurang).
 * Dipakai pada daftar nilai, lembar cetak, dan dashboard.
 */
function predikat_label(string $p): string
{
    switch ($p) {
        case 'A': return 'Amat Baik';
        case 'B': return 'Baik';
        case 'C': return 'Cukup';
        case 'D': return 'Kurang';
    }
    return '-';
}

/**
 * Kalimat keterangan ambang predikat untuk ditampilkan/dicetak, memakai ambang sekolah
 * (Pengaturan): "A (Amat Baik) ≥ 90 · B (Baik) ≥ 80 · C (Cukup) ≥ 70 · D (Kurang) < 70".
 */
function predikat_keterangan(): string
{
    $t = thresholds();
    return 'A (Amat Baik) ' . r0($t['batas_a']) . ' ke atas - B (Baik) ' . r0($t['batas_b'])
        . ' ke atas - C (Cukup) ' . r0($t['batas_c']) . ' ke atas - D (Kurang) di bawah '
        . r0($t['batas_c']);
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

// ------------------------------------------------------------------ JENJANG LEMBAGA (skema v49)

/**
 * Daftar jenjang yang SAH untuk sebuah jenis lembaga ('umum' → SD/SMP/SMA/SMK,
 * 'madrasah' → MI/MTs/MA/MAK). Jenis tak dikenal diperlakukan sebagai 'umum'.
 */
function jenjang_pilihan(?string $jenis = null): array
{
    $jenis = $jenis === null ? jenis_sekolah() : ($jenis === 'madrasah' ? 'madrasah' : 'umum');
    return JENJANG_PILIHAN[$jenis] ?? JENJANG_PILIHAN['umum'];
}

/** Benar bila $jenjang termasuk pilihan yang sah untuk jenis lembaga itu. */
function jenjang_sah(string $jenjang, ?string $jenis = null): bool
{
    return in_array($jenjang, jenjang_pilihan($jenis), true);
}

/** Jenjang lembaga yang sedang aktif ('' bila belum dipilih). */
function jenjang_sekolah(): string
{
    $j = trim((string) setting('jenjang_sekolah', ''));
    // Nilai lama/tak dikenal untuk jenis yang sekarang dinormalkan menjadi kosong: jenjang yang tidak
    // cocok dengan jenisnya tidak boleh dipakai sebagai acuan apa pun.
    return jenjang_sah($j) ? $j : '';
}

/** Label panjang jenjang, mis. "MA — Madrasah Aliyah" ('' bila belum dipilih). */
function jenjang_label(?string $jenjang = null): string
{
    $j = $jenjang === null ? jenjang_sekolah() : trim($jenjang);
    return JENJANG_LABEL[$j] ?? '';
}

/**
 * Benar bila lembaga ini memakai konsep GURU KELAS (jenjang SD atau MI): satu guru memegang banyak
 * mata pelajaran pada satu kelas. Untuk jenjang lain seluruh pengaturan tetap seperti sebelumnya.
 */
function is_guru_kelas_jenjang(): bool
{
    return in_array(jenjang_sekolah(), JENJANG_GURU_KELAS, true);
}

/** Tingkat kelas yang menjadi acuan pada jenjang aktif ([] bila jenjang belum dipilih). */
function jenjang_tingkat_list(?string $jenjang = null): array
{
    $j = $jenjang === null ? jenjang_sekolah() : trim($jenjang);
    return JENJANG_TINGKAT[$j] ?? [];
}

/** Ringkas acuan tingkat, mis. "I, II, III, IV, V, VI" ('' bila belum ada). */
function jenjang_tingkat_teks(?string $jenjang = null): string
{
    return implode(', ', jenjang_tingkat_list($jenjang));
}

/** Benar bila guru ini ditandai sebagai GURU KELAS (kolom guru.guru_kelas). */
function guru_adalah_kelas(int $guruId): bool
{
    if ($guruId <= 0) {
        return false;
    }
    return (int) val('SELECT guru_kelas FROM guru WHERE id = ? AND sekolah_id = ?', [$guruId, sekolah_id()], 0) === 1;
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
        // Penjaga lookahead `(?!\s*\/\s*[Mm]adrasah)`: pada aplikasi ini banyak teks berbunyi
        // "Sekolah/Madrasah" (mis. role_label() → "Kepala Sekolah/Madrasah"). Tanpa penjaga itu,
        // pada sekolah berjenis MADRASAH teks tersebut menjadi "Kepala Madrasah/Madrasah"
        // (tulisan ganda yang menyesatkan) — pola yang sama dipakai aplikasi Supervisi Guru.
        '/(?<![A-Za-z])(SEKOLAH|Sekolah|sekolah)(?![A-Za-z])(?!\s*\/\s*[Mm]adrasah)/u',
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

/**
 * Buang penanda INTERNAL tetap_kata() (\x01, \x02, \x03) yang mungkin masih tertinggal.
 *
 * PENTING: penanda itu murni penanda internal — bila sampai ke peramban, peramban
 * menggambarnya sebagai KOTAK pada teks yang dibungkus tetap_kata() (biasanya nama resmi
 * sekolah/madrasah). Penanda bisa tertinggal karena terjemahkan_html() punya jalan keluar
 * cepat "stripos($html, 'sekolah') === false" — pada halaman madrasah yang seluruh teksnya
 * sudah berbunyi "Madrasah" (mis. lembar cetak & KARTU PELAJAR) kata "sekolah" sering tidak
 * ada, sehingga jalan keluar itu mengambil alih dan penanda tidak pernah dibuang.
 */
function buang_penanda(string $html): string
{
    return strtr($html, ["\x01" => '', "\x02" => '', "\x03" => '']);
}

/** Jalankan istilah_sekolah() pada seluruh TEKS halaman HTML (isi script/style tidak disentuh). */
function terjemahkan_html(string $html): string
{
    // Halaman lintas sekolah (mengelola sekolah umum DAN madrasah sekaligus) memakai
    // istilah generik "Sekolah" — jangan diterjemahkan agar tidak muncul "Madrasah/Madrasah".
    if ($html === '' || !is_madrasah() || !empty($GLOBALS['__vs_istilah_nonaktif'])) {
        return buang_penanda($html);
    }
    if (stripos($html, 'sekolah') === false) {
        return buang_penanda($html);
    }
    $lindungi = [];
    $html = (string) preg_replace_callback('/\x01(.*?)\x02/s', static function (array $m) use (&$lindungi): string {
        $kunci = "\x03" . count($lindungi) . "\x03";
        $lindungi[$kunci] = (string) $m[1];
        return $kunci;
    }, $html);

    $bagian = preg_split('/(<[^>]*>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($bagian === false) {
        return buang_penanda($html);
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
    // Jaring pengaman terakhir: penanda internal tidak boleh pernah sampai ke peramban.
    return buang_penanda($keluar);
}

function logo_sekolah(): string
{
    return trim(setting('logo_sekolah', ''));
}

function ada_logo(): bool
{
    return logo_sekolah() !== '';
}

// ============================================================================================
// LOGO APLIKASI (tingkat APLIKASI — diatur Super Admin lewat menu "Logo Aplikasi")
// --------------------------------------------------------------------------------------------
// Tampil pada halaman masuk (portal), halaman pendaftaran, sidebar seluruh pengguna, dan
// sebagai favicon. Disimpan pada tabel `app_pengaturan` (kunci `logo_app`) — yang dicatat hanya
// URL CDN hasil media proxy platform. Selama belum diunggah, tampilannya tetap memakai lambang
// bawaan (ikon SVG) sehingga tidak ada halaman yang kosong.
// ============================================================================================

/** URL logo aplikasi hasil unggahan Super Admin ('' bila belum diunggah). */
function logo_aplikasi(): string
{
    return trim(app_setting('logo_app', ''));
}

/** Benar bila logo aplikasi sudah diunggah Super Admin (bukan lambang bawaan). */
function ada_logo_aplikasi(): bool
{
    return logo_aplikasi() !== '';
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

// ------------------------------------------------------------------ tautan aman (umum)

/**
 * Tautan yang aman dipakai pada atribut href: hanya http/https.
 * Nilai lain (mis. `javascript:` atau isian kosong) dianggap tidak sah.
 * Dipakai modul daftar aplikasi (`inc/daftar_apps.php`: menu Smart Teacher & Hasil Supervisi).
 */
function tautan_aman(?string $url): string
{
    $u = trim((string) $url);
    if ($u === '') {
        return '';
    }
    return preg_match('~^https?://~i', $u) === 1 ? $u : '';
}

// CATATAN: helper menu daftar aplikasi (Smart Teacher, Aplikasi Sagu, Hasil Supervisi) ada di
// inc/daftar_apps.php — bukan di sini — karena hanya halaman menu itu yang memakainya.

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
/**
 * Pecah daftar teks berpemisah (koma, titik koma, atau tanda pipa) menjadi array bersih
 * tanpa duplikat. Dipakai halaman impor: satu sel pada kolom "Mata Pelajaran", "Kelas", atau
 * "Tugas Tambahan" boleh memuat beberapa nilai sekaligus (mis. "Matematika, Informatika").
 */
function pecah_daftar($teks): array
{
    $bagian = preg_split('/[,;|]+/', (string) $teks) ?: [];
    $keluar = [];
    foreach ($bagian as $b) {
        $b = trim($b);
        if ($b !== '' && !in_array($b, $keluar, true)) {
            $keluar[] = $b;
        }
    }
    return $keluar;
}

/**
 * Kunci pembanding NAMA Tugas Tambahan (dan nama menu): huruf kecil, tanda baca → spasi,
 * spasi berlebih dirapikan. Dipakai mencocokkan nama lama `tugas_tambahan` dengan nama menu saat
 * migrasi v30 dan saat impor guru mencari tugas yang sudah ada.
 */
function tugas_nama_kunci(string $nama): string
{
    $k = strtolower(trim($nama));
    $k = (string) preg_replace('/[^a-z0-9]+/', ' ', $k);
    return trim((string) preg_replace('/\s+/', ' ', $k));
}

/**
 * PADANAN nama Tugas Tambahan versi LAMA (daftar per sekolah yang sudah dihapus pada skema v30)
 * dengan nama menu pada daftar GLOBAL (`tugas_menu`) — dipakai HANYA oleh migrasi v30 supaya data
 * guru yang sudah ada tidak hilang dan tidak menjadi baris kembar.
 *
 * Kunci = nama lama yang sudah dinormalkan `tugas_nama_kunci()`, nilai = nama menu tujuan.
 * Padanan hanya dipakai bila menu tujuannya BENAR-BENAR ada (kalau tidak, nama lama dibuatkan baris
 * menu sendiri). Tambahkan di sini bila kelak ada nama lama lain yang perlu dipadankan.
 *
 * @return array<string,string>
 */
function tugas_legacy_alias(): array
{
    return [
        'wakasek kesiswaan'    => 'Kesiswaan',
        'wakasek kurikulum'    => 'Kurikulum',
        'wakasek sarpras'      => 'Sarpras',
        'wakasek humas'        => 'Humas',
        'sarana prasarana'     => 'Sarpras',
        'sarana dan prasarana' => 'Sarpras',
        'pembina osim'         => 'Pembina OSIM/OSIS',
        'pembina osim osis'    => 'Pembina OSIM/OSIS',
        'pembina pramuka'      => 'Pembina Pramuka',
        'pembina pmr'          => 'Pembina PMR',
        'tata usaha'           => 'Ka. Tata Usaha',
        'kepala perpus'        => 'Ka. Perpustakaan',
        'kepala lab'           => 'Ka. Lab IPA',
        'wali kelas'           => 'Wali Kelas',
    ];
}

/**
 * Daftar TUGAS TAMBAHAN yang dapat dipilih pada Data Guru.
 *
 * **Sejak skema v30 daftar ini SAMA dengan daftar menu Tugas Tambahan (`tugas_menu`)** yang diisi
 * Super Admin pada halaman "Kelola Tugas Tambahan" — permintaan pemilik: "Daftar Tugas Tambahan
 * yang ada di akun operator harus sama atau bersumber dari Daftar Tugas tambahan yang diinput oleh
 * Super Admin". Tidak ada lagi tabel/salinan per sekolah (dulu `tugas_tambahan`).
 *
 * `$aktifSaja = true` (bawaan) hanya mengembalikan menu yang **diaktifkan** Super Admin — itulah
 * yang ditawarkan sebagai pilihan. Menu nonaktif tetap muncul di halaman daftar (dan data guru yang
 * sudah memakainya tetap tersimpan & tercetak), tetapi tidak lagi dijadikan pilihan baru.
 *
 * @return array<int,array<string,mixed>> baris: id, kode, nama, urutan, aktif, jml_guru
 */
function tugas_tambahan_list(bool $aktifSaja = true): array
{
    return all('SELECT m.id, m.kode, m.nama, m.urutan, m.aktif,
                       (SELECT COUNT(*) FROM guru_tugas gt WHERE gt.tugas_id = m.id) AS jml_guru
                FROM tugas_menu m' . ($aktifSaja ? ' WHERE m.aktif = 1' : '') . '
                ORDER BY m.urutan, m.id');
}

/**
 * Satu Tugas Tambahan berdasarkan id (id menu `tugas_menu`).
 *
 * Tidak dibatasi sekolah lagi karena daftarnya GLOBAL — tetapi pemanggil WAJIB tetap memeriksa
 * hasilnya sebelum menyimpan (id asing/karangan diabaikan).
 */
function tugas_tambahan_by_id(int $id): ?array
{
    return tugas_menu_by_id($id);
}

/** Tugas tambahan yang dipegang seorang guru (daftar id menu). */
function guru_tugas_ids(int $guruId): array
{
    if ($guruId <= 0) {
        return [];
    }
    return array_map('intval', array_column(all('SELECT gt.tugas_id FROM guru_tugas gt
        JOIN tugas_menu m ON m.id = gt.tugas_id WHERE gt.guru_id = ?', [$guruId]), 'tugas_id'));
}

/** Nama-nama tugas tambahan seorang guru (urut urutan menu, lalu nama) — daftar & lembar cetak. */
function guru_tugas_nama(int $guruId): array
{
    if ($guruId <= 0) {
        return [];
    }
    return array_column(all('SELECT m.nama FROM guru_tugas gt
        JOIN tugas_menu m ON m.id = gt.tugas_id WHERE gt.guru_id = ?
        ORDER BY m.urutan, m.nama', [$guruId]), 'nama');
}

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

// ------------------------------------------------------------------ aktivasi sekolah/madrasah (kode unik)

/** Lama masa uji coba yang boleh dipilih Super Admin saat menyetujui pendaftaran. */
function aktivasi_pilihan_hari(): array
{
    return [30, 60, 90];
}

/** Lama MASA AKTIF setelah kode aktivasi dimasukkan (permintaan pemilik: 1 tahun = 365 hari). */
function aktivasi_hari(): int
{
    return max(1, (int) AKTIVASI_HARI_AKTIF);
}

/** Nomor telepon dalam format internasional untuk tautan wa.me (mis. 0852… → 62852…). */
function wa_internasional(string $nomor): string
{
    $d = (string) preg_replace('/\D/', '', $nomor);
    if ($d === '') {
        return '';
    }
    if (strpos($d, '0') === 0) {
        return '62' . substr($d, 1);
    }
    if (strpos($d, '62') !== 0) {
        return '62' . $d;
    }
    return $d;
}

/**
 * Pesan WhatsApp permohonan kode aktivasi (permintaan pemilik):
 * "Assalamualaikum HD Admin Aplikasi (nama aplikasi) Kami dari (Nama Sekolah) ingin melakukan
 *  aktifasi aplikasi (nama aplikasi)."
 */
function aktivasi_pesan_permohonan(?array $sekolah = null): string
{
    if ($sekolah === null) {
        $sekolah = current_sekolah();
    }
    $nama = trim((string) ($sekolah['nama'] ?? ''));
    if ($nama === '') {
        $nama = '-';
    }
    return 'Assalamualaikum HD Admin Aplikasi ' . APP_NAME . '. Kami dari ' . $nama
        . ' ingin melakukan aktifasi aplikasi ' . APP_NAME . '.';
}

/** Tautan WhatsApp (wa.me) untuk mengirim permohonan kode aktivasi ke HD Admin. */
function aktivasi_tautan_permohonan(?array $sekolah = null): string
{
    return 'https://wa.me/' . wa_internasional(aktivasi_wa())
        . '?text=' . rawurlencode(aktivasi_pesan_permohonan($sekolah));
}

// ------------------------------------------------------------------ PENGATURAN BIAYA & WA (v1.53.0)
// Permintaan pemilik: *"Tambahkan menu di super admin untuk mengisi nominal biaya aktifasi pertama,
// biaya langganan, nomer WA HD Admin (pada bagian kirim permintaan aktifasi dan bukti transfer."*
//
// Isinya bersifat TINGKAT APLIKASI (semua sekolah memakai tarif & nomor WA yang sama) sehingga
// disimpan pada tabel `app_pengaturan` dengan kunci `aktivasi_biaya`, `aktivasi_biaya_langganan`,
// dan `aktivasi_wa`. Konstanta `AKTIVASI_BIAYA`, `AKTIVASI_BIAYA_LANGGANAN`, dan
// `AKTIVASI_WA_PERMOHONAN` (inc/config.php) menjadi **NILAI BAWAAN** bila Super Admin belum
// pernah mengubahnya — dan dipakai kembali oleh tombol "Kembalikan Bawaan".
//
// ATURAN: seluruh halaman WAJIB membaca lewat ketiga fungsi di bawah ini, JANGAN memakai
// konstantanya langsung — kalau tidak, perubahan Super Admin tidak akan terpakai.

/**
 * Pembacaan pengaturan biaya/WA dengan CACHE per permintaan: sidebar, kartu, banner, dan pratinjau
 * memanggil fungsi-fungsi ini berkali-kali pada satu halaman, sedangkan `app_setting()` selalu
 * menembak basis data. `$paksa` dipakai fungsi penyimpan agar nilainya langsung segar.
 */
function aktivasi_setting(string $kunci, string $bawaan, bool $paksa = false): string
{
    static $cache = [];
    if ($paksa) {
        $cache = [];
    }
    if (!array_key_exists($kunci, $cache)) {
        $cache[$kunci] = app_setting($kunci, $bawaan);
    }
    return $cache[$kunci];
}

/** Nominal biaya aktivasi PERTAMA (masa aktif 1 tahun) — bawaan AKTIVASI_BIAYA. */
function aktivasi_biaya_pertama(): int
{
    return max(0, (int) aktivasi_setting('aktivasi_biaya', (string) AKTIVASI_BIAYA));
}

/** Nominal biaya LANGGANAN tahun berikutnya — bawaan AKTIVASI_BIAYA_LANGGANAN. */
function aktivasi_biaya_langganan(): int
{
    return max(0, (int) aktivasi_setting('aktivasi_biaya_langganan', (string) AKTIVASI_BIAYA_LANGGANAN));
}

/**
 * Nomor WA HD Admin tujuan **permohonan kode aktivasi** dan **bukti transfer** — bawaan
 * AKTIVASI_WA_PERMOHONAN. Ditampilkan apa adanya (bentuk lokal 0…); tautan `wa.me`-nya dibangun
 * `wa_internasional()`.
 */
function aktivasi_wa(): string
{
    return trim(aktivasi_setting('aktivasi_wa', AKTIVASI_WA_PERMOHONAN));
}

/**
 * true bila nomor WA-nya masuk akal: hanya angka (boleh didahului + atau 0), 9–15 digit.
 * Tanda baca yang lazim dipakai saat menulis nomor (spasi, -, titik, tanda kurung) DIMA'AFKAN dan
 * disimpan dalam bentuk angka saja.
 */
function aktivasi_wa_sah(string $nomor): bool
{
    $d = (string) preg_replace('/\D/', '', $nomor);
    return $d !== '' && strlen($d) >= 9 && strlen($d) <= 15
        && preg_match('/^\+?[0-9 ().\-]*$/', trim($nomor)) === 1;
}

/** Nomor WA yang sudah dibersihkan (hanya angka) — dipakai saat menyimpan. */
function aktivasi_wa_bersih(string $nomor): string
{
    return (string) preg_replace('/\D/', '', $nomor);
}

/**
 * Simpan pengaturan biaya & nomor WA. Nilai `null` = jangan diubah (pola yang sama dengan
 * `grup_eguru_simpan()`), sehingga formulir yang hanya mengirim sebagian isian tidak mengosongkan
 * isian lainnya.
 */
function aktivasi_biaya_simpan(?int $pertama, ?int $langganan, ?string $wa): void
{
    // Segarkan cache agar pemanggilan berikutnya PADA PERMINTAAN YANG SAMA membaca nilai baru
    // (mis. pesan flash yang menyebut nominal & nomor WA barunya).
    aktivasi_setting('', '', true);
    if ($pertama !== null) {
        app_setting_set('aktivasi_biaya', (string) max(0, min(AKTIVASI_BIAYA_MAKS, $pertama)));
    }
    if ($langganan !== null) {
        app_setting_set('aktivasi_biaya_langganan', (string) max(0, min(AKTIVASI_BIAYA_MAKS, $langganan)));
    }
    if ($wa !== null) {
        app_setting_set('aktivasi_wa', substr(aktivasi_wa_bersih($wa), 0, AKTIVASI_WA_MAKS));
    }
}

/** Kembalikan ketiga pengaturan ke nilai bawaan aplikasi (konstanta di inc/config.php). */
function aktivasi_biaya_bawaan(): void
{
    aktivasi_setting('', '', true);
    app_setting_set('aktivasi_biaya', (string) AKTIVASI_BIAYA);
    app_setting_set('aktivasi_biaya_langganan', (string) AKTIVASI_BIAYA_LANGGANAN);
    app_setting_set('aktivasi_wa', AKTIVASI_WA_PERMOHONAN);
}

/** true bila ketiga pengaturan masih sama dengan nilai bawaannya (dipakai chip "masih bawaan"). */
function aktivasi_biaya_masih_bawaan(): bool
{
    return aktivasi_biaya_pertama() === (int) AKTIVASI_BIAYA
        && aktivasi_biaya_langganan() === (int) AKTIVASI_BIAYA_LANGGANAN
        && aktivasi_wa() === AKTIVASI_WA_PERMOHONAN;
}

// ------------------------------------------------------------------ BIAYA AKTIVASI (v1.52.0)
// Permintaan pemilik: *"Pada menu status dan aktifasi tampilkan nominal aktifasi. Sehingga menjadi
// seperti ini: Untuk mengaktifasi Lembaga anda silahkan transfer Rp. 1.000.000 ke Bank Mandiri
// 1500010135547. An. Supianto. Jika sudah melakukan transfer silahkan kirim bukti transfer ke wa
// (menuju ke nomer permohonan kode aktifasi). Jika aplikasi sudah di aktifasi maka muncul info:
// biaya langganan Rp. 500.000 untuk tahun depan"*.
//
// Dua tarif (nominalnya di inc/config.php, JANGAN ditulis di halaman):
//   • AKTIVASI_BIAYA           — aktivasi pertama (masa aktif 1 tahun);
//   • AKTIVASI_BIAYA_LANGGANAN — langganan tahun berikutnya.

/**
 * Apakah lembaga ini SUDAH PERNAH diaktifasi (jadi tarifnya memakai biaya LANGGANAN)?
 *
 * Dipakai untuk memilih nominal yang tampil pada halaman Status & Aktivasi. Penandanya adalah jejak
 * AKTIVASI YANG SUNGGUH TERJADI: kolom `aktivasi_pada` sudah terisi ATAU nomor periode
 * (`aktivasi_ke`) sudah melewati 1 — keduanya diisi hanya setelah kode aktivasi berhasil dimasukkan
 * (atau masa aktif 1 tahun diberikan Super Admin lewat pintasan di halaman yang sama).
 *
 * ⚠️ Mode `permanen` SENDIRIAN **tidak** dianggap "sudah diaktifasi": sekolah yang dibuat Super Admin
 * memang berstatus `permanen` tanpa pernah membayar, jadi menampilkan "biaya langganan" di sana akan
 * menyesatkan. Sekolah seperti itu tetap melihat tarif aktivasi pertama.
 */
function aktivasi_pernah_aktif(?array $status = null, ?array $sekolah = null): bool
{
    $st = $status ?? aktivasi_status($sekolah);
    if ((int) ($st['periode'] ?? 1) > 1) {
        return true;
    }
    $s = $sekolah ?? current_sekolah();
    return trim((string) ($s['aktivasi_pada'] ?? '')) !== '';
}

/** Nominal yang BERLAKU bagi lembaga ini (aktivasi pertama atau langganan perpanjangan). */
function aktivasi_biaya_berlaku(?array $status = null, ?array $sekolah = null): int
{
    // Membaca PENGATURAN Super Admin (menu "Biaya & WA Aktivasi"), dengan konstanta sebagai bawaan.
    return aktivasi_pernah_aktif($status, $sekolah) ? aktivasi_biaya_langganan() : aktivasi_biaya_pertama();
}

/** Label singkat jenis pembayarannya: "Biaya aktivasi" / "Biaya langganan". */
function aktivasi_biaya_label(?array $status = null, ?array $sekolah = null): string
{
    return aktivasi_pernah_aktif($status, $sekolah)
        ? 'Biaya langganan (perpanjangan 1 tahun)'
        : 'Biaya aktivasi (masa aktif 1 tahun)';
}

/**
 * Kalimat permintaan pembayaran — STRUKTUR PERSIS permintaan pemilik, hanya nominalnya mengikuti
 * tarif yang berlaku bagi lembaga itu. Dipakai pada halaman Status & Aktivasi dan pada pesan WhatsApp
 * bukti transfer supaya bunyinya selalu sama.
 */
function aktivasi_kalimat_biaya(?array $status = null, ?array $sekolah = null): string
{
    $nominal = rupiah(aktivasi_biaya_berlaku($status, $sekolah));
    return 'Untuk mengaktifasi Lembaga anda silahkan transfer ' . $nominal . ' ke '
        . AKTIVASI_REKENING . '. Jika sudah melakukan transfer silahkan kirim bukti transfer ke wa.';
}

/**
 * Pesan WhatsApp saat mengirim BUKTI TRANSFER — ditujukan ke nomor PERMOHONAN kode aktivasi yang sama
 * (permintaan pemilik: *"kirim bukti transfer ke wa (menuju ke nomer permohonan kode aktifasi)"*).
 */
function aktivasi_pesan_bukti(?array $sekolah = null, ?array $status = null): string
{
    if ($sekolah === null) {
        $sekolah = current_sekolah();
    }
    $nama = trim((string) ($sekolah['nama'] ?? ''));
    if ($nama === '') {
        $nama = '-';
    }
    $nominal = rupiah(aktivasi_biaya_berlaku($status, $sekolah));
    $jenis = aktivasi_pernah_aktif($status, $sekolah) ? 'biaya langganan' : 'biaya aktifasi';
    return 'Assalamualaikum HD Admin Aplikasi ' . APP_NAME . '. Kami dari ' . $nama
        . ' sudah melakukan transfer ' . $jenis . ' ' . $nominal . ' ke ' . AKTIVASI_REKENING
        . '. Berikut kami kirimkan bukti transfernya untuk proses verifikasi aktifasi aplikasi '
        . APP_NAME . '.';
}

/** Tautan WhatsApp (wa.me) untuk mengirim bukti transfer ke nomor permohonan kode aktivasi. */
function aktivasi_tautan_bukti(?array $sekolah = null, ?array $status = null): string
{
    return 'https://wa.me/' . wa_internasional(aktivasi_wa())
        . '?text=' . rawurlencode(aktivasi_pesan_bukti($sekolah, $status));
}

/**
 * Pesan lengkap yang dipakai saat aplikasi TERKUNCI (banner + gerbang + halaman aktivasi).
 * Menegaskan bahwa SELURUH FITUR terkunci, DATA LAMA tetap aman, dan pengguna diminta menghubungi
 * HD Admin untuk meminta kode aktivasi yang baru.
 */
function aktivasi_pesan_kunci(?array $sekolah = null): string
{
    $st = aktivasi_status($sekolah);
    $kapan = $st['sampai_ts'] > 0 ? ' (masa aktif berakhir ' . waktu_id_polos($st['sampai']) . ')' : '';
    return 'Masa aktif aplikasi sudah habis' . $kapan . ' sehingga aplikasi TERKUNCI: seluruh fitur '
        . 'tidak dapat dipakai sampai kode aktivasi yang baru dimasukkan. Seluruh data sebelumnya tetap '
        . 'aman (tidak ada yang dihapus). Hubungi HD Admin untuk meminta kode aktivasi yang baru'
        . (aktivasi_wa() !== '' ? ' (WA ' . aktivasi_wa() . ')' : '') . '.';
}

/** Lama uji coba bawaan (dipakai bila Super Admin memilih "uji coba" tanpa menyebut jumlah hari). */
const UJI_COBA_HARI_DEFAULT = 30;

/**
 * Pesan untuk akun PENGAWAS ketika salah satu BINAANNYA tidak dapat dibuka karena masa aktif
 * penggunaan aplikasi lembaga itu sudah berakhir (v1.71.8 — permintaan pemilik).
 *
 * Permintaan pemilik: *"jika pengawas kelola madrasah yang masa aktif habis ada keterangan madrasah
 * ini tidak bisa diakses karena masa aktif penggunaan aplikasi telah berakhir"*.
 *
 * BEDA dengan `aktivasi_pesan_kunci()`: pengawas BUKAN pengguna lembaga itu — ia hanya melihat &
 * mencetak, tidak bisa memasukkan kode, dan tidak boleh ikut terkunci. Karena itu kalimatnya
 * menjelaskan SIAPA yang dapat mengaktifkan kembali.
 */
function aktivasi_pesan_binaan_terkunci(?array $sekolah = null): string
{
    $st = aktivasi_status($sekolah);
    $kapan = $st['sampai_ts'] > 0 ? ' (masa aktif berakhir ' . waktu_id_polos($st['sampai']) . ')' : '';
    return 'Sekolah/madrasah ini tidak bisa diakses karena masa aktif penggunaan aplikasi telah '
        . 'berakhir' . $kapan . '. Seluruh data sebelumnya tetap aman (tidak ada yang dihapus). '
        . 'Kode aktivasi hanya dapat dimasukkan oleh Kepala Sekolah/Madrasah atau Admin Operator '
        . 'lembaga tersebut'
        . (aktivasi_wa() !== '' ? ', dan kodenya diminta kepada HD Admin (WA ' . aktivasi_wa() . ')' : '')
        . '.';
}

/**
 * Lama masa aktif untuk PENDAFTARAN MANDIRI (halaman publik `daftar.php`).
 *
 * Permintaan pemilik: pendaftar baru langsung DISETUJUI otomatis tetapi berstatus MASA UJI COBA
 * ("Pendaftaran Gratis (Aktif N Hari)") — jadi mereka bisa langsung masuk dan mencoba
 * seluruh fitur, dan setelah masa itu berakhir aplikasi terkunci sampai kode aktivasi lembaga
 * dimasukkan (mekanisme aktivasi yang sudah ada, lihat gerbang_aktivasi()).
 *
 * Lama masa uji coba pendaftar mandiri ini TERPISAH dari pilihan Super Admin pada halaman
 * Pendaftaran/Kelola Sekolah (30/60/90 hari untuk sekolah yang disetujui manual).
 *
 * Riwayat: semula 30 hari, lalu diperpendek menjadi 15 hari (permintaan pemilik).
 */
const PENDAFTARAN_HARI_AKTIF = 15;

/**
 * Abjad kode aktivasi: Crockford base32 (32 karakter, TANPA huruf I, L, O, U supaya tidak
 * mudah tertukar). Jumlahnya WAJIB tepat 32 karena generator memetakan byte (0-255) ke indeks
 * dengan `% 32` — 256 habis dibagi 32 sehingga pemetaannya seragam/tanpa bias.
 */
function aktivasi_abjad(): string
{
    return '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
}

/**
 * Bentuk baku sebuah kode aktivasi: huruf besar, tanpa tanda hubung/spasi, dan huruf yang mudah
 * tertukar disamakan (O→0, I/L→1) supaya pengelola boleh menulis kode dengan cara apa pun.
 */
function aktivasi_normalisasi(string $teks): string
{
    $bersih = (string) preg_replace('/[^A-Z0-9]/', '', strtoupper($teks));
    return strtr($bersih, ['O' => '0', 'I' => '1', 'L' => '1']);
}

/** Nilai satu pengaturan tingkat aplikasi (tabel `app_pengaturan`). */
function app_setting(string $kunci, string $default = ''): string
{
    try {
        $st = db()->prepare('SELECT nilai FROM app_pengaturan WHERE kunci = ?');
        $st->execute([$kunci]);
        $v = $st->fetchColumn();
    } catch (Throwable $ex) {
        // Tabel belum ada (mis. skema belum menyusul) → pakai nilai bawaan, JANGAN gagalkan halaman.
        return $default;
    }
    return $v === false || $v === null || (string) $v === '' ? $default : (string) $v;
}

/** Simpan pengaturan tingkat aplikasi. */
function app_setting_set(string $kunci, string $nilai): void
{
    q('INSERT INTO app_pengaturan (kunci, nilai, diubah) VALUES (?,?,?)
       ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai, diubah = excluded.diubah',
        [$kunci, $nilai, date('Y-m-d H:i:s')]);
}

// ============================================================================================
// ISI "TENTANG APLIKASI" PADA HALAMAN MASUK (permintaan pemilik)
// --------------------------------------------------------------------------------------------
// *"Pada halaman depan di bagian tentang aplikasi tambahkan tempat foto, nama dan keterangan dengan
//  posisi atau letak dibuat sama dengan aplikasi supervisi"*
// Pilihan pemilik: (1) DUA foto (seperti aplikasi Supervisi), (2) tulisan Tentang Aplikasi yang lama
// DIGANTI oleh keterangan yang diisi pemilik, (3) pengisiannya lewat menu baru "Tentang Aplikasi"
// pada akun Super Admin (`tentang.php`).
//
// Disimpan pada tabel `app_pengaturan` (tingkat APLIKASI) karena halaman masuk dipakai bersama
// seluruh sekolah/madrasah. Untuk FOTO hanya URL CDN hasil media proxy yang disimpan.
// ============================================================================================

/** Nomor tempat (slot) foto yang sah: 1 … TENTANG_FOTO_SLOT. */
function tentang_slot_sah(int $slot): int
{
    if ($slot < 1 || $slot > TENTANG_FOTO_SLOT) {
        return 1;
    }
    return $slot;
}

/** Daftar nomor tempat foto yang dipakai halaman (1 … TENTANG_FOTO_SLOT). */
function tentang_slot_list(): array
{
    $keluar = [];
    for ($n = 1; $n <= TENTANG_FOTO_SLOT; $n++) {
        $keluar[] = $n;
    }
    return $keluar;
}

/**
 * Isi blok "Tentang Aplikasi" pada halaman masuk (dipakai `login.php` & `tentang.php`).
 *
 * @return array{slot:array<int,array{foto:string,nama:string}>, keterangan:string, ada:bool,
 *               jumlah_foto:int}
 */
function tentang_isi(): array
{
    $slot = [];
    $jumlah = 0;
    $ada = false;
    foreach (tentang_slot_list() as $n) {
        $foto = trim(app_setting('tentang_foto_' . $n));
        $nama = trim(app_setting('tentang_nama_' . $n));
        $slot[$n] = ['foto' => $foto, 'nama' => $nama];
        if ($foto !== '') {
            $jumlah++;
        }
        if ($foto !== '' || $nama !== '') {
            $ada = true;
        }
    }
    $ket = trim(app_setting('tentang_keterangan'));
    if ($ket !== '') {
        $ada = true;
    }
    return ['slot' => $slot, 'keterangan' => $ket, 'ada' => $ada, 'jumlah_foto' => $jumlah];
}

/**
 * Pecah keterangan menjadi paragraf.
 *
 * ATURAN (dipilih agar mudah dipakai pengelola):
 *   - **Enter SATU kali** → ganti baris DI DALAM paragraf yang sama (dipakai juga saat mencetak
 *     lewat `nl2br()`); ini yang biasanya dimaksud pengguna pada kotak teks biasa;
 *   - **baris KOSONG** (Enter dua kali) → paragraf BARU (diberi jarak antarkeluar).
 *
 * Catatan: aturan ini SENGAJA berbeda dari aplikasi Supervisi yang melebur semua baris baru. Di
 * aplikasi ini pemilik menulis keterangannya dengan Enter tunggal untuk memisah kalimat, sehingga
 * peleburan akan menghilangkan pemisah yang ia maksud (peristiwa nyata pada data pemilik).
 *
 * @return array<int,string> paragraf (masing-masing boleh berisi baris baru)
 */
function tentang_paragraf(string $teks): array
{
    // Seragamkan akhir baris lebih dulu: formulir HTML mengirim CRLF (\r\n) sedangkan teks
    // dari PHP/seed memakai LF. Tanpa ini "baris kosong" dari isian pengguna TIDAK pernah
    // terdeteksi sehingga dua paragraf yang ia tulis tampil menyatu (bug nyata).
    $teks = str_replace(["\r\n", "\r"], "\n", trim($teks));
    $keluar = [];
    foreach (preg_split('/\n[ \t]*\n+/u', $teks) ?: [] as $blok) {
        // Buang spasi di ujung tiap baris, dan rapikan baris kosong berlebih di dalam blok.
        $baris = [];
        foreach (explode("\n", (string) $blok) as $b) {
            $baris[] = rtrim($b);
        }
        $blok = trim(implode("\n", $baris));
        if ($blok !== '') {
            $keluar[] = $blok;
        }
    }
    return $keluar;
}

/** Potong teks panjang tanpa mb_* bila mbstring tidak tersedia (runtime CLI sandbox). */
function tentang_potong(string $teks, int $maks): string
{
    if (function_exists('mb_substr')) {
        return (string) mb_substr($teks, 0, $maks);
    }
    return strlen($teks) > $maks ? substr($teks, 0, $maks) : $teks;
}

/**
 * Simpan satu tempat foto. Parameter bernilai `null` berarti bagian itu TIDAK diubah
 * (mis. saat hanya mengunggah foto tanpa mengirim isian namanya).
 */
function tentang_simpan_slot(int $slot, ?string $nama, ?string $foto): void
{
    $slot = tentang_slot_sah($slot);
    if ($nama !== null) {
        app_setting_set('tentang_nama_' . $slot, tentang_potong(trim($nama), TENTANG_NAMA_MAKS));
    }
    if ($foto !== null) {
        app_setting_set('tentang_foto_' . $slot, trim($foto));
    }
}

/** Simpan keterangan/penjelasan "Tentang Aplikasi". */
function tentang_simpan_keterangan(string $teks): void
{
    app_setting_set('tentang_keterangan', tentang_potong(trim($teks), TENTANG_KET_MAKS));
}

/**
 * Unggah berkas foto "Tentang Aplikasi" (`$_FILES['foto_<slot>']`) ke media proxy platform.
 * Yang disimpan ke basis data HANYA URL hasil respons.
 *
 * @return array{ok:bool,url:string,pesan:string}
 */
function tentang_unggah_foto(int $slot): array
{
    $kunci = 'foto_' . tentang_slot_sah($slot);
    $f = $_FILES[$kunci] ?? null;
    if (!is_array($f) || trim((string) ($f['name'] ?? '')) === '') {
        return ['ok' => false, 'url' => '', 'pesan' => 'Tidak ada berkas foto yang dipilih.'];
    }
    $kode = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($kode !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'url' => '', 'pesan' => ($kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE)
            ? 'berkasnya melebihi batas server (' . ini_get('upload_max_filesize') . '). Perkecil dulu fotonya.'
            : 'gagal diunggah (kode ' . $kode . '). Silakan coba lagi.'];
    }
    $namaAsli = (string) $f['name'];
    $ukuran = (int) ($f['size'] ?? 0);
    $tmp = (string) ($f['tmp_name'] ?? '');
    $cek = validasi_foto($namaAsli, $ukuran, $tmp);
    if (empty($cek['ok'])) {
        return ['ok' => false, 'url' => '', 'pesan' => (string) $cek['pesan']];
    }
    $bytes = @file_get_contents($tmp);
    if ($bytes === false) {
        return ['ok' => false, 'url' => '', 'pesan' => 'Gagal membaca berkas foto di server.'];
    }
    $hasil = media_upload($bytes, $namaAsli);
    if (empty($hasil['ok'])) {
        return ['ok' => false, 'url' => '', 'pesan' => (string) ($hasil['pesan'] ?? 'Unggahan foto ditolak.')];
    }
    return ['ok' => true, 'url' => (string) $hasil['url'], 'pesan' => ''];
}

/**
 * Kunci rahasia generator kode aktivasi.
 *
 * SENGAJA disimpan di BASIS DATA (tabel `app_pengaturan`), bukan sebagai berkas di `data/`:
 * berkas di folder `data/` dapat diunduh lewat web (terbukti — `data/.schema_ok` balas 200),
 * sehingga rahasia di sana bisa dibaca siapa pun dan kode aktivasi lembaga mana pun dapat
 * dibuat sendiri. Berkas basis data tidak dapat diunduh.
 *
 * JANGAN mengganti/menghapus nilainya setelah kode dibagikan ke sekolah: seluruh kode aktivasi
 * akan berubah dan sekolah yang sudah memegang kode tidak bisa memakainya lagi. Nilai dibuat
 * otomatis sekali (acak 48 digit heksadesimal) saat pertama dibutuhkan.
 */
function aktivasi_secret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $ada = app_setting('aktivasi_secret');
    if (strlen($ada) >= 32) {
        $secret = $ada;
        return $secret;
    }
    $baru = bin2hex(random_bytes(24));
    try {
        app_setting_set('aktivasi_secret', $baru);
        $secret = $baru;
    } catch (Throwable $ex) {
        // Cadangan terakhir: konstanta di inc/config.php (tidak pernah terekspos ke web).
        $secret = (string) (defined('AKTIVASI_SECRET_CADANGAN') ? AKTIVASI_SECRET_CADANGAN : 'eguru-cadangan');
    }
    return $secret;
}

/**
 * KODE AKTIVASI sebuah sekolah/madrasah — UNIK berdasar NAMA sekolah (ditambah id sekolah supaya
 * dua lembaga bernama sama tetap berbeda) dan kunci rahasia aplikasi.
 *
 * Sifatnya DETERMINISTIK: nama/id yang sama selalu menghasilkan kode yang sama, sehingga Super Admin
 * dapat menampilkan/mengirim ulang kode kapan saja tanpa menyimpan di basis data.
 * Format: 3 kelompok × 4 karakter, tanpa huruf/angka yang mudah tertukar (tanpa 0/O/1/I/L).
 */
function kode_aktivasi_sekolah(?array $sekolah): string
{
    if (!$sekolah) {
        return '';
    }
    $nama = aktivasi_normalisasi((string) ($sekolah['nama'] ?? ''));
    if ($nama === '') {
        $nama = 'TANPA-NAMA';
    }
    // ⚠️ PERIODE (`aktivasi_ke`) IKUT DIHITUNG — inti dari aturan "satu kode hanya bisa dipakai
    // SEKALI" (permintaan pemilik): setiap aktivasi yang berhasil menaikkan `aktivasi_ke`, sehingga
    // kode periode sebelumnya TIDAK PERNAH cocok lagi dan kode yang tampil di akun Super Admin
    // otomatis BERGANTI. Jangan menghapus bagian ini — tanpa periode, satu kode berlaku selamanya.
    $bahan = $nama . '|' . (int) ($sekolah['id'] ?? 0) . '|' . aktivasi_periode($sekolah);
    $biner = hash_hmac('sha256', $bahan, aktivasi_secret(), true);
    $abjad = aktivasi_abjad();
    $jumlah = strlen($abjad);   // 32
    $kode = '';
    for ($i = 0; $i < 12; $i++) {
        // 256 habis dibagi 32 → tidak ada bias pada pemetaan byte ke indeks abjad.
        $kode .= $abjad[ord($biner[$i]) % $jumlah];
    }
    return substr($kode, 0, 4) . '-' . substr($kode, 4, 4) . '-' . substr($kode, 8, 4);
}

/**
 * NOMOR PERIODE aktivasi lembaga (kolom `sekolah.aktivasi_ke`, minimal 1).
 *
 * Dipakai dua hal: (1) `kode_aktivasi_sekolah()` menghitungnya sehingga **kode berubah setiap
 * periode** — satu kode hanya sah untuk SEKALI pemakaian; (2) tampilan Super Admin menyebut
 * "kode periode ke-N" supaya jelas kode mana yang sedang berlaku.
 */
function aktivasi_periode(?array $sekolah = null): int
{
    if ($sekolah === null) {
        $sekolah = current_sekolah();
    }
    if (!$sekolah) {
        return 1;
    }
    if (array_key_exists('aktivasi_ke', $sekolah)) {
        return max(1, (int) $sekolah['aktivasi_ke']);
    }
    // Baris yang tidak memuat kolomnya (mis. hasil SELECT sebagian) → baca dari basis data.
    $id = (int) ($sekolah['id'] ?? 0);
    return $id > 0 ? max(1, (int) val('SELECT COALESCE(aktivasi_ke, 1) FROM sekolah WHERE id = ?', [$id], 1)) : 1;
}

/** true bila kode aktivasi yang dimasukkan cocok dengan kode lembaga tersebut. */
function kode_aktivasi_cocok(?array $sekolah, string $masukan): bool
{
    $benar = kode_aktivasi_sekolah($sekolah);
    if ($benar === '') {
        return false;
    }
    return hash_equals(aktivasi_normalisasi($benar), aktivasi_normalisasi($masukan));
}

/**
 * Status aktivasi sebuah sekolah.
 *
 * `sampai_ts` = waktu UNIX saat masa uji coba benar-benar berakhir (akhir hari batas, 23:59:59)
 * dan `sisa_detik` = sisa detik saat ini. Keduanya dipakai HITUNG MUNDUR pada halaman Aktivasi
 * (sebelumnya hanya ada `sisa_hari` yang dibulatkan ke atas, sehingga jam tidak dapat ditampilkan).
 *
 * @return array{mode:string,habis:bool,sisa_hari:int,sisa_detik:int,sampai_ts:int,label:string,mulai:string,sampai:string,hari:int,periode:int}
 *   mode: `permanen` (tanpa batas) | `aktif` (masa aktif 365 hari, berhitung mundur) |
 *         `uji_coba` (masa uji coba 30/60/90) | `tanpa_sekolah`
 */
function aktivasi_status(?array $sekolah = null): array
{
    if ($sekolah === null) {
        $sekolah = current_sekolah();
    }
    if (!$sekolah) {
        return ['mode' => 'tanpa_sekolah', 'habis' => false, 'sisa_hari' => 0, 'sisa_detik' => 0,
                'sampai_ts' => 0, 'label' => '-', 'mulai' => '', 'sampai' => '', 'hari' => 0, 'periode' => 1];
    }
    $nilai = trim((string) ($sekolah['aktivasi'] ?? 'permanen'));
    // JARING PENGAMAN nilai lama/rusak: kolom `aktivasi` pernah terisi ANGKA 1 (akibat SQL bertulis
    // `aktivasi = "aktif"` yang oleh SQLite dibaca sebagai NAMA KOLOM `aktif` — lihat catatan di
    // inc/db.php). Nilai yang tidak dikenal diperlakukan sebagai BERJANGKA bila `aktif_sampai` terisi
    // (jadi hitung mundur & penguncian tetap berlaku), selain itu sebagai permanen (perilaku lama).
    if (!in_array($nilai, ['permanen', 'aktif', 'uji_coba'], true)) {
        $nilai = trim((string) ($sekolah['aktif_sampai'] ?? '')) !== '' ? 'aktif' : 'permanen';
    }
    $mode = in_array($nilai, ['aktif', 'uji_coba'], true) ? $nilai : 'permanen';
    $mulai = (string) ($sekolah['uji_coba_mulai'] ?? '');
    $sampai = (string) ($sekolah['uji_coba_selesai'] ?? '');
    $periode = max(1, (int) ($sekolah['aktivasi_ke'] ?? 1));
    if ($mode === 'permanen') {
        return ['mode' => 'permanen', 'habis' => false, 'sisa_hari' => 0, 'sisa_detik' => 0,
                'sampai_ts' => 0, 'label' => 'Aktif permanen', 'mulai' => $mulai, 'sampai' => $sampai,
                'hari' => 0, 'periode' => $periode];
    }
    if ($mode === 'aktif') {
        // MASA AKTIF 365 HARI (permintaan pemilik): berakhir pada AKHIR hari batas (23:59:59),
        // hitung mundur memakai `sampai_ts`/`sisa_detik`, dan setelah lewat aplikasi TERKUNCI.
        $mulai = (string) ($sekolah['aktivasi_pada'] ?? '');
        $sampai = (string) ($sekolah['aktif_sampai'] ?? '');
        $ts = $sampai !== '' ? strtotime($sampai) : false;
        if ($ts === false) {
            // Masa aktif tanpa tanggal batas dianggap belum berjalan → tidak pernah mengunci.
            return ['mode' => 'aktif', 'habis' => false, 'sisa_hari' => 0, 'sisa_detik' => 0,
                    'sampai_ts' => 0, 'label' => 'Aktif', 'mulai' => $mulai, 'sampai' => '', 'hari' => 0,
                    'periode' => $periode];
        }
        $akhir = (int) strtotime(date('Y-m-d', $ts) . ' 23:59:59');
        $sisaDetik = $akhir - time();
        // "Sisa N hari" dihitung dari selisih TANGGAL (kalender), bukan pembulatan detik: masa aktif
        // 365 hari harus terbaca "sisa 365 hari" pada hari pertama (bukan 366).
        $sisa = (int) round((strtotime(date('Y-m-d', $ts)) - strtotime(date('Y-m-d'))) / 86400);
        if ($sisaDetik <= 0) {
            return ['mode' => 'aktif', 'habis' => true, 'sisa_hari' => 0, 'sisa_detik' => 0,
                    'sampai_ts' => $akhir, 'label' => 'Masa aktif BERAKHIR', 'mulai' => $mulai,
                    'sampai' => $sampai, 'hari' => aktivasi_hari(), 'periode' => $periode];
        }
        return ['mode' => 'aktif', 'habis' => false, 'sisa_hari' => $sisa, 'sisa_detik' => max(0, $sisaDetik),
                'sampai_ts' => $akhir, 'label' => 'Aktif (sisa ' . $sisa . ' hari)', 'mulai' => $mulai,
                'sampai' => $sampai, 'hari' => aktivasi_hari(), 'periode' => $periode];
    }

    // Sisa hari dihitung sampai AKHIR hari batas (tanggal batas masih bisa dipakai).
    $ts = $sampai !== '' ? strtotime($sampai) : false;
    if ($ts === false) {
        // Masa uji coba tanpa tanggal batas dianggap belum berjalan → tidak pernah mengunci.
        return ['mode' => 'uji_coba', 'habis' => false, 'sisa_hari' => 0, 'sisa_detik' => 0,
                'sampai_ts' => 0, 'label' => 'Masa uji coba', 'mulai' => $mulai, 'sampai' => '', 'hari' => 0,
                'periode' => $periode];
    }
    $akhir = (int) strtotime(date('Y-m-d', $ts) . ' 23:59:59');
    $sisaDetik = $akhir - time();
    $sisa = (int) ceil($sisaDetik / 86400);
    $hari = ($mulai !== '' && strtotime($mulai) !== false)
        ? (int) max(1, round((strtotime(date('Y-m-d', $ts)) - strtotime(date('Y-m-d', strtotime($mulai)))) / 86400))
        : 0;
    if ($sisa <= 0) {
        return ['mode' => 'uji_coba', 'habis' => true, 'sisa_hari' => 0, 'sisa_detik' => 0,
                'sampai_ts' => $akhir, 'label' => 'Masa uji coba BERAKHIR', 'mulai' => $mulai,
                'sampai' => $sampai, 'hari' => $hari, 'periode' => $periode];
    }
    return ['mode' => 'uji_coba', 'habis' => false, 'sisa_hari' => $sisa, 'sisa_detik' => max(0, $sisaDetik),
            'sampai_ts' => $akhir, 'label' => 'Masa uji coba (sisa ' . $sisa . ' hari)', 'mulai' => $mulai,
            'sampai' => $sampai, 'hari' => $hari, 'periode' => $periode];
}

/**
 * Kunci URUTAN "masa aktif" untuk daftar LINTAS SEKOLAH — permintaan pemilik (v1.71.9):
 * *"Urutkan: Paling atas adalah yang masa aktifnya paling lama."*
 *
 * Nilainya dipakai `usort()` MENURUN (besar → kecil), jadi makin besar = masa aktif makin lama:
 *   • `permanen`, atau masa aktif/uji coba TANPA tanggal batas (tidak pernah terkunci) → PHP_INT_MAX;
 *   • `aktif` / `uji_coba` yang masih berjalan → sisa DETIK menuju akhir masa aktif;
 *   • sudah habis (aplikasi terkunci) → -1 sehingga selalu berada di PALING BAWAH.
 *
 * Memakai `aktivasi_status()` sebagai satu-satunya sumber kebenaran — jangan menghitung sisa hari
 * sendiri di sini (lihat catatan pada `aktivasi_status()`).
 */
function aktivasi_urut_kunci(?array $sekolah = null): int
{
    $st = aktivasi_status($sekolah);
    if ($st['mode'] === 'tanpa_sekolah') {
        return -1;
    }
    if ($st['mode'] === 'permanen') {
        return PHP_INT_MAX;
    }
    if ($st['habis']) {
        return -1;
    }
    if ((int) $st['sampai_ts'] === 0) {
        // Masa aktif tanpa tanggal batas dianggap belum berjalan → tidak pernah terkunci.
        return PHP_INT_MAX;
    }
    return max(0, (int) $st['sisa_detik']);
}

/**
 * true bila aplikasi sekolah TERKUNCI: masa aktif / masa uji cobanya sudah habis.
 *
 * Permintaan pemilik: "Saat masa aktif habis, buat aplikasi otomatis mengunci semua fitur tanpa
 * menghilangkan data sebelumnya dan otomatis kolom input kode aktifasi muncul. dan meminta pengguna
 * untuk mengaktifkan kembali dengan menghubungi HD aplikasi."
 * Yang terkunci: mode `aktif` (masa aktif 365 hari) DAN `uji_coba` (30/60/90 hari) yang sudah lewat.
 * Mode `permanen` (tanpa batas waktu) tidak pernah terkunci.
 */
function aktivasi_terkunci(?array $sekolah = null): bool
{
    $st = aktivasi_status($sekolah);
    return in_array($st['mode'], ['aktif', 'uji_coba'], true) && $st['habis'];
}

/**
 * Data POPUP PERINGATAN AKTIVASI (v1.71.4 — permintaan pemilik).
 *
 * Permintaan: *"Pada madrasah yang masa aktifnya sisa 7 hari tampilkan pop up peringatan agar segera
 * melakukan aktifasi melalui akun kepala sekolah atau operator. Popup ini muncul di akun semua
 * pengguna pada lembaga tersebut sampai masa aktif benar-benar habis."*
 *
 * Aturan yang dipakai (satu tempat, jangan disalin ke halaman):
 *  • berlaku untuk masa aktif 365 hari (`aktif`) DAN masa uji coba (`uji_coba`) yang masih berjalan —
 *    keduanya berakhir dengan aplikasi TERKUNCI, jadi keduanya perlu diingatkan;
 *  • hanya bila sisa ≤ `AKTIVASI_INGAT_HARI` (7 hari) dan BELUM habis (keadaan habis sudah punya
 *    kartu/spanduk sendiri di halaman Status & Aktivasi);
 *  • tampil untuk SELURUH pengguna lembaga itu (kepala, operator, guru, siswa). **Super Admin**
 *    (pemilik aplikasi — dialah yang mengirim kode) dan **pengawas** (akun lintas sekolah, bukan
 *    pengguna lembaga) TIDAK melihatnya; keduanya tetap melihat spanduk peringatan biasa;
 *  • `boleh` = true bila pengguna ini dapat MEMASUKKAN kode sendiri (kepala/operator) sehingga
 *    tombol "Lakukan Aktivasi" ditampilkan; guru & siswa hanya diberi arahan menghubungi mereka.
 *
 * @return array{mode:string,sebut:string,judul:string,sisa_hari:int,sampai:string,sampai_ts:int,boleh:bool}|null
 */
function aktivasi_peringatan(?array $sekolah = null, ?string $peran = null): ?array
{
    $peran = $peran ?? (string) (current_user()['role'] ?? '');
    if (in_array($peran, ['super', 'pengawas'], true)) {
        return null;
    }
    if ($sekolah === null) {
        $sekolah = current_sekolah();
    }
    if (!$sekolah) {
        return null;
    }
    $st = aktivasi_status($sekolah);
    if (!in_array($st['mode'], ['aktif', 'uji_coba'], true) || $st['habis']) {
        return null;
    }
    // `sisa_hari` bisa 0 pada HARI TERAKHIR (masa aktif berakhir pukul 23:59:59 malam itu) —
    // masa itu TETAP diingatkan ("berakhir hari ini"), karena popup memang tampil sampai habis.
    $sisa = max(0, (int) $st['sisa_hari']);
    if ($sisa > (int) AKTIVASI_INGAT_HARI) {
        return null;
    }
    $madrasah = $st['mode'] === 'aktif';
    return [
        'mode'      => $st['mode'],
        'sebut'     => $madrasah ? 'masa aktif' : 'masa uji coba',
        'judul'     => $madrasah ? 'Masa Aktif Hampir Berakhir' : 'Masa Uji Coba Hampir Berakhir',
        'sisa_hari' => $sisa,
        'sisa_teks' => $sisa > 0 ? $sisa . ' hari' : 'hari ini',
        'sampai'    => (string) $st['sampai'],
        'sampai_ts' => (int) $st['sampai_ts'],
        'boleh'     => in_array($peran, ['kepala', 'operator'], true),
    ];
}

/**
 * Aktifkan sekolah secara PERMANEN (dipakai Super Admin maupun saat kode aktivasi dimasukkan).
 * Masa uji coba lama tetap disimpan sebagai riwayat.
 */
function aktivasi_permanen(int $sekolahId): void
{
    q('UPDATE sekolah SET aktivasi = "permanen", aktivasi_pada = ?, aktif_sampai = NULL, aktif = 1 WHERE id = ?',
        [date('Y-m-d H:i:s'), $sekolahId]);
}

/**
 * Aktifkan sekolah untuk SATU PERIODE masa aktif (bawaan 365 hari = 1 tahun) — dipakai saat KODE
 * AKTIVASI dimasukkan pengelola.
 *
 * Permintaan pemilik: *"Pada saat pengguna aktifasi melakukan aktifasi (memasukkan kode aktifasi dari
 * admin) Pastikan aplikasi aktif dengan terbuka semua fitur dengan masa aktif 1 tahun (365 hari).
 * Buat hitungan mundur. Dan jika masa aktif habis bisa menghubungi kembali HD Admin untuk meminta
 * kode aktifas yang baru."*
 *
 * Menghitung `aktivasi_ke` (periode ke-) supaya perpanjangan berikutnya terlihat pada halaman
 * aktivasi, dan mengosongkan sisa masa uji coba (sekolah kini berstatus AKTIF, bukan uji coba).
 *
 * @return int jumlah hari masa aktif yang diberikan
 */
function aktivasi_aktifkan(int $sekolahId, int $hari = 0): int
{
    $hari = $hari > 0 ? max(1, min(3650, $hari)) : aktivasi_hari();
    $sekarang = date('Y-m-d H:i:s');
    // PERPANJANGAN (permintaan pemilik: "Tampilkan fitur untuk menginput kode aktifasi
    // perpanjangan") — bila masa aktif SEKARANG masih berjalan, hari barunya DITAMBAHKAN dari
    // tanggal berakhir yang masih berlaku sehingga sisa waktu yang sudah dibayar tidak hangus.
    // Masa aktif yang sudah habis (atau belum pernah ada) dihitung dari HARI INI.
    $dasar = time();
    $lama = one('SELECT * FROM sekolah WHERE id = ?', [$sekolahId]);
    // ⚠️ Hanya masa aktif BERJALAN (mode `aktif`) yang dijadikan dasar. `aktif_sampai` bisa masih
    // berisi tanggal lama yang TIDAK lagi dipakai (mis. sekolah dipindahkan ke mode `uji_coba` atau
    // dinonaktifkan) — memakai tanggal itu akan memberi perpanjangan yang keliru.
    $st = aktivasi_status($lama);
    $batasLama = trim((string) ($lama['aktif_sampai'] ?? ''));
    if ($st['mode'] === 'aktif' && !$st['habis'] && $batasLama !== '') {
        $ts = strtotime($batasLama);
        if ($ts !== false && $ts > time()) {
            $dasar = $ts;
        }
    }
    $sampai = date('Y-m-d', strtotime('+' . $hari . ' days', $dasar)) . ' 23:59:59';
    $periode = (int) val('SELECT COALESCE(aktivasi_ke, 0) FROM sekolah WHERE id = ?', [$sekolahId], 0);
    // ⚠️ `aktivasi` diisi lewat PARAMETER: nilai teks 'aktif' TIDAK boleh ditulis dengan kutip ganda
    // (`"aktif"`) karena SQLite akan membacanya sebagai nama kolom `aktif` dan menyimpan angka 1.
    q('UPDATE sekolah SET aktivasi = ?, aktif_sampai = ?, aktivasi_pada = ?, aktivasi_ke = ?, aktif = 1
       WHERE id = ?',
        ['aktif', $sampai, $sekarang, $periode + 1, $sekolahId]);
    return $hari;
}

/** Mulai/perpanjang MASA UJI COBA selama $hari (dibatasi 1..365). */
function aktivasi_uji_coba(int $sekolahId, int $hari): int
{
    $hari = max(1, min(365, $hari));
    $mulai = date('Y-m-d H:i:s');
    $sampai = date('Y-m-d', strtotime('+' . $hari . ' days')) . ' 23:59:59';
    q('UPDATE sekolah SET aktivasi = "uji_coba", uji_coba_mulai = ?, uji_coba_selesai = ?,
         aktivasi_pada = NULL, aktif = 1 WHERE id = ?', [$mulai, $sampai, $sekolahId]);
    return $hari;
}

/** Pengelola sekolah/madrasah (kepala & admin operator, atau Super Admin dalam konteks). */
function boleh_aktivasi(): bool
{
    if (is_kepala() || is_operator()) {
        return true;
    }
    return is_super() && current_sekolah() !== null;
}



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
        // Kolom Formatif boleh berisi ANGKA, HURUF, atau TANDA CENTANG (v1.36.0) sehingga nilainya
        // TIDAK boleh selalu di-cast ke float — huruf "A" akan berubah menjadi 0.
        $raw = $r['nilai'];
        if ($raw === null || $raw === '') {
            $v = null;
        } else {
            $v = is_numeric($raw) ? (float) $raw : (string) $raw;
        }
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
        // Hanya NILAI ANGKA yang dihitung: pada kolom Formatif, sel yang berisi huruf/✓ tidak
        // boleh dianggap 0 (kalau tidak, rata-rata kelas turun palsu) — lihat nilai_angka().
        $n = nilai_angka($v);
        if ($n !== null) {
            $isi[] = $n;
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

// ------------------------------------------------ URAIAN KEGIATAN & KELAS/TEMPAT (skema v36)
// Permintaan pemilik: *"Pada bagian Input Jurnal Harian Baru tambahkan kolom 'Uraian Kegiatan
// (Pilihan Mengajar bisa juga input manual jika kegiatan selain mengajar)' setelah kolom tanggal.
// Pada kolom kelas berubah menjadi 'Kelas/Tempat' selain pilih kelas guru bisa menginput manual
// tempatnya. Mata Pelajaran dan materi/topik pembelajaran bisa dikosongkan jika kegiatan guru selain
// mengajar"*. Pilihan pemilik (jangan diubah tanpa bertanya): daftar saran hanya **"Mengajar"**
// (kegiatan lain diketik manual); **Kelas ATAU Tempat** (salah satu wajib, tidak boleh keduanya);
// dan **hanya catatan "Mengajar" yang dihitung tatap muka** pada Laporan Rekap Jumlah Pertemuan.

/** Daftar saran Uraian Kegiatan (isi tetap boleh diketik bebas). */
function jurnal_uraian_list(): array
{
    return JURNAL_URAIAN_PILIHAN;
}

/**
 * Uraian kegiatan yang DISIMPAN: kosong → "Mengajar"; ejaan apa pun dari kata "Mengajar"
 * (huruf besar/kecil, spasi berlebih) → "Mengajar"; selain itu dirapikan & dibatasi panjangnya.
 */
function jurnal_uraian_sah($v): string
{
    $s = trim((string) $v);
    if ($s === '') {
        return JURNAL_URAIAN_BAWAAN;
    }
    $s = (string) preg_replace('/\s+/', ' ', $s);
    if (strcasecmp($s, JURNAL_URAIAN_BAWAAN) === 0) {
        return JURNAL_URAIAN_BAWAAN;
    }
    return substr($s, 0, JURNAL_URAIAN_MAKS);
}

/** Tempat kegiatan yang DISIMPAN (bila guru tidak memilih kelas) — kosong berarti tidak diisi. */
function jurnal_tempat_sah($v): string
{
    return substr((string) preg_replace('/\s+/', ' ', trim((string) $v)), 0, JURNAL_TEMPAT_MAKS);
}

/**
 * True bila catatan jurnal adalah kegiatan MENGAJAR (uraian kosong/NULL juga dianggap mengajar
 * supaya catatan LAMA yang belum punya kolom uraian tetap terhitung sebagai tatap muka).
 */
function jurnal_mengajar($j): bool
{
    $u = strtolower(trim(is_array($j) ? (string) ($j['uraian'] ?? '') : (string) $j));
    return $u === '' || $u === strtolower(JURNAL_URAIAN_BAWAAN);
}

/** Label uraian kegiatan sebuah catatan jurnal (data lama tanpa uraian → "Mengajar"). */
function jurnal_uraian(array $j): string
{
    $u = trim((string) ($j['uraian'] ?? ''));
    return $u === '' ? JURNAL_URAIAN_BAWAAN : $u;
}

/** Tempat kegiatan manual sebuah catatan jurnal ('' bila guru memilih kelas / tidak diisi). */
function jurnal_tempat(array $j): string
{
    return trim((string) ($j['tempat'] ?? ''));
}

/**
 * Label kolom "Kelas/Tempat": nama KELAS bila guru memilih kelas, kalau tidak nama TEMPAT yang
 * diketik guru, dan "-" bila keduanya kosong. Dipakai daftar jurnal, dashboard, dan lembar cetak.
 */
function jurnal_kelas_tempat(array $j): string
{
    $k = trim((string) ($j['kelas_nama'] ?? ''));
    if ($k !== '') {
        return $k;
    }
    $t = jurnal_tempat($j);
    return $t !== '' ? $t : '-';
}

/**
 * Klausa SQL: catatan jurnal yang merupakan kegiatan MENGAJAR (dipakai Laporan Rekap Jumlah
 * Pertemuan Guru supaya kegiatan lain — rapat, bimbingan, dst. — TIDAK dihitung sebagai tatap muka).
 * Nilai NULL/kosong ikut dihitung mengajar agar catatan lama tetap terbaca.
 */
function jurnal_sql_mengajar(string $alias = 'j'): string
{
    $a = $alias === '' ? '' : $alias . '.';
    return '((' . $a . "uraian IS NULL OR TRIM(" . $a . "uraian) = '' OR LOWER(TRIM(" . $a
        . "uraian)) = 'mengajar'))";
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

// ------------------------------------------------------------------ rekap pelanggaran siswa

/**
 * Boleh membuka **Laporan Rekap Pelanggaran Siswa** (totals per siswa & per guru penginput).
 *
 * Permintaan pemilik (v1.26.0): *"Pada halaman akun kepala Madrasah/sekolah tambahkan Menu Laporan
 * Rekap Pelanggaran Siswa (Total Pelanggaran yang diinput oleh setiap guru mata pelajaran)."*
 * Pilihannya: **kepala sekolah/madrasah + Super Admin dalam konteks sekolah** (guru & pengawas 403).
 */
function boleh_rekap_pelanggaran(): bool
{
    return is_kepala() || (is_super() && current_sekolah() !== null);
}

/** Penjaga halaman/lembar cetak Laporan Rekap Pelanggaran Siswa. */
function require_rekap_pelanggaran(): array
{
    $u = require_login();
    require_sekolah();
    if (!boleh_rekap_pelanggaran()) {
        tolak403('Laporan Rekap Pelanggaran Siswa hanya untuk Kepala Sekolah/Madrasah dan Super Admin '
            . '(dalam konteks sekolah).');
    }
    return $u;
}

/**
 * Rekap SISWA dengan pelanggaran terbanyak di sekolah yang sedang aktif.
 *
 * @param int $limit jumlah baris (bawaan 20 — sesuai permintaan pemilik)
 * @return array<int,array<string,mixed>> baris: siswa_id, nama, nisn, nis, kelas_nama, jml, guru_nama
 */
function pelanggaran_rekap_siswa(int $limit = 20): array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return [];
    }
    $limit = max(1, min(200, $limit));
    return all('SELECT s.id AS siswa_id, s.nama, s.nisn, s.nis, k.nama AS kelas_nama,
                       COUNT(ks.id) AS jml,
                       GROUP_CONCAT(DISTINCT g.nama) AS guru_nama
                FROM kasus ks
                JOIN siswa s ON s.id = ks.siswa_id
                LEFT JOIN kelas k ON k.id = s.kelas_id
                LEFT JOIN guru g ON g.id = ks.guru_id
                WHERE ks.sekolah_id = ?
                GROUP BY s.id
                ORDER BY jml DESC, s.nama
                LIMIT ' . $limit, [$sid]);
}

/**
 * Rekap per GURU MATA PELAJARAN yang menginput pelanggaran ("Total Pelanggaran yang diinput oleh
 * setiap guru mata pelajaran" — permintaan pemilik).
 *
 * @return array<int,array<string,mixed>> baris: guru_nama, jml, jml_siswa, mapel_nama
 */
function pelanggaran_rekap_guru(): array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return [];
    }
    return all('SELECT COALESCE(NULLIF(g.nama, ""), "(tidak tercatat)") AS guru_nama,
                       COUNT(ks.id) AS jml,
                       COUNT(DISTINCT ks.siswa_id) AS jml_siswa,
                       GROUP_CONCAT(DISTINCT m.nama) AS mapel_nama
                FROM kasus ks
                LEFT JOIN guru g ON g.id = ks.guru_id
                LEFT JOIN mapel m ON m.id = ks.mapel_id
                WHERE ks.sekolah_id = ?
                GROUP BY COALESCE(ks.guru_id, 0)
                ORDER BY jml DESC, guru_nama', [$sid]);
}

/**
 * Ringkasan rekap pelanggaran sekolah ini: total pelanggaran, jumlah siswa yang tercatat,
 * jumlah guru penginput, dan jumlah kelas yang terlibat.
 *
 * @return array<string,int>
 */
function pelanggaran_ringkas(): array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return ['total' => 0, 'siswa' => 0, 'guru' => 0, 'kelas' => 0];
    }
    return [
        'total' => (int) val('SELECT COUNT(*) FROM kasus WHERE sekolah_id = ?', [$sid], 0),
        'siswa' => (int) val('SELECT COUNT(DISTINCT siswa_id) FROM kasus WHERE sekolah_id = ?', [$sid], 0),
        'guru'  => (int) val('SELECT COUNT(DISTINCT guru_id) FROM kasus WHERE sekolah_id = ? AND guru_id IS NOT NULL', [$sid], 0),
        'kelas' => (int) val('SELECT COUNT(DISTINCT kelas_id) FROM kasus WHERE sekolah_id = ?', [$sid], 0),
    ];
}

// ------------------------------------------------------------------ rekap jumlah pertemuan guru

/**
 * Boleh membuka **Laporan Rekap Jumlah Pertemuan Guru** (jumlah tatap muka tiap guru per kelas
 * dan mata pelajaran, diambil dari jurnal harian).
 *
 * Permintaan pemilik (v1.28.0): *"Pada halaman akun kepala Madrasah/sekolah tambahkan Menu Laporan
 * Rekap Jumlah Pertemuan Guru. Data diambil dari jurnal yang diisi oleh masing-masing guru pada
 * setiap mata pelajaran di setiap kelasnya. Isinya dalam bentuk tabel: Nama Guru, Kelas, Mata
 * Pelajaran, Jumlah Tatap Muka, Lihat Jurnal Pembelajaran"*.
 * Hak akses sama dengan Laporan Rekap Pelanggaran Siswa: **kepala sekolah/madrasah + Super Admin
 * dalam konteks sekolah** (guru, operator, & pengawas 403).
 */
function boleh_rekap_pertemuan(): bool
{
    return is_kepala() || (is_super() && current_sekolah() !== null);
}

/** Penjaga halaman/lembar cetak Laporan Rekap Jumlah Pertemuan Guru. */
function require_rekap_pertemuan(): array
{
    $u = require_login();
    require_sekolah();
    if (!boleh_rekap_pertemuan()) {
        tolak403('Laporan Rekap Jumlah Pertemuan Guru hanya untuk Kepala Sekolah/Madrasah dan '
            . 'Super Admin (dalam konteks sekolah).');
    }
    return $u;
}

/**
 * Klausa WHERE rekap pertemuan — DIPUSATKAN di sini supaya halaman, ringkasan, dan lembar cetak
 * selalu menghitung hal yang sama.
 *
 * Aturan periode mengikuti `jurnal.php` (dan `cetak_jurnal.php`): baris jurnal yang belum punya
 * tahun ajaran/semester (data lama) tetap ikut dihitung pada periode mana pun. Bila
 * `$semuaPeriode` true, penyaring periode tidak dipasang sama sekali.
 *
 * @param array{ta?:?array,sem?:?array,semua?:bool,kelas?:int,guru?:int,mapel?:int} $f
 * @return array{sql:string,p:array<int,scalar>}
 */
function pertemuan_where(array $f = []): array
{
    $ta = $f['ta'] ?? null;
    $sem = $f['sem'] ?? null;
    $semua = (bool) ($f['semua'] ?? false);

    $w = ['j.sekolah_id = ?'];
    $p = [sekolah_id()];
    if (!$semua && $ta) {
        $w[] = '(j.ta_id = ? OR j.ta_id IS NULL)';
        $p[] = (int) $ta['id'];
        if ($sem) {
            $w[] = '(j.semester_id = ? OR j.semester_id IS NULL)';
            $p[] = (int) $sem['id'];
        }
    }
    if ((int) ($f['kelas'] ?? 0) > 0) {
        $w[] = 'j.kelas_id = ?';
        $p[] = (int) $f['kelas'];
    }
    if ((int) ($f['guru'] ?? 0) > 0) {
        $w[] = 'j.guru_id = ?';
        $p[] = (int) $f['guru'];
    }
    if ((int) ($f['mapel'] ?? 0) > 0) {
        $w[] = 'j.mapel_id = ?';
        $p[] = (int) $f['mapel'];
    }
    // PENTING (permintaan pemilik, v1.37.0): hanya catatan ber-uraian **"Mengajar"** yang dihitung
    // sebagai TATAP MUKA. Kegiatan lain (rapat, bimbingan, dinas luar, dst.) tidak dihitung —
    // catatan lama tanpa uraian tetap ikut karena NULL/kosong dianggap mengajar.
    $w[] = jurnal_sql_mengajar('j');
    return ['sql' => ' WHERE ' . implode(' AND ', $w), 'p' => $p];
}

/**
 * Rekap jumlah tatap muka: SATU BARIS per kombinasi Guru + Kelas + Mata Pelajaran.
 *
 * **"Jumlah Tatap Muka" = banyaknya catatan jurnal** (permintaan pemilik: "Setiap catatan jurnal
 * = 1 tatap muka (hitung baris jurnal)"). `jml_hari` (jumlah tanggal unik) ikut dikembalikan
 * sebagai keterangan tambahan, bukan angka utama.
 *
 * @param array{ta?:?array,sem?:?array,semua?:bool,kelas?:int,guru?:int,mapel?:int} $f
 * @return array<int,array<string,mixed>> baris: guru_id, guru_nama, kelas_id, kelas_nama,
 *         mapel_id, mapel_nama, jml, jml_hari, tgl_awal, tgl_akhir
 */
function pertemuan_rekap(array $f = []): array
{
    if (sekolah_id() <= 0) {
        return [];
    }
    $w = pertemuan_where($f);
    // Label kelas memakai NAMA KELAS; bila kegiatan mengajar tidak dipasangkan kelas melainkan
    // TEMPAT manual (Kelas/Tempat), tempat itulah yang ditampilkan — bukan "(tanpa kelas)".
    return all('SELECT COALESCE(j.guru_id, 0) AS guru_id,
                       COALESCE(NULLIF(g.nama, ""), "(tidak tercatat)") AS guru_nama,
                       COALESCE(j.kelas_id, 0) AS kelas_id,
                       COALESCE(NULLIF(TRIM(k.nama), ""), NULLIF(TRIM(j.tempat), ""), "(tanpa kelas)") AS kelas_nama,
                       COALESCE(j.mapel_id, 0) AS mapel_id,
                       COALESCE(m.nama, "(tanpa mata pelajaran)") AS mapel_nama,
                       COUNT(j.id) AS jml,
                       COUNT(DISTINCT j.tanggal) AS jml_hari,
                       MIN(j.tanggal) AS tgl_awal,
                       MAX(j.tanggal) AS tgl_akhir
                FROM jurnal j
                LEFT JOIN guru g ON g.id = j.guru_id
                LEFT JOIN kelas k ON k.id = j.kelas_id
                LEFT JOIN mapel m ON m.id = j.mapel_id'
        . $w['sql'] . '
                GROUP BY COALESCE(j.guru_id, 0), COALESCE(j.kelas_id, 0), COALESCE(j.mapel_id, 0),
                         COALESCE(NULLIF(TRIM(j.tempat), ""), "")
                ORDER BY guru_nama, kelas_nama, mapel_nama', $w['p']);
}

/**
 * Ringkasan rekap pertemuan: total tatap muka, jumlah guru pengisi jurnal, kelas, dan mata pelajaran.
 *
 * @param array{ta?:?array,sem?:?array,semua?:bool,kelas?:int,guru?:int,mapel?:int} $f
 * @return array<string,int>
 */
function pertemuan_ringkas(array $f = []): array
{
    if (sekolah_id() <= 0) {
        return ['total' => 0, 'guru' => 0, 'kelas' => 0, 'mapel' => 0, 'hari' => 0, 'baris' => 0];
    }
    $w = pertemuan_where($f);
    $r = one('SELECT COUNT(j.id) AS total,
                     COUNT(DISTINCT j.guru_id) AS guru,
                     COUNT(DISTINCT j.kelas_id) AS kelas,
                     COUNT(DISTINCT j.mapel_id) AS mapel,
                     COUNT(DISTINCT j.tanggal) AS hari
              FROM jurnal j' . $w['sql'], $w['p']);
    $baris = one('SELECT COUNT(*) AS n FROM (SELECT 1 FROM jurnal j' . $w['sql']
        . ' GROUP BY COALESCE(j.guru_id, 0), COALESCE(j.kelas_id, 0), COALESCE(j.mapel_id, 0))', $w['p']);
    return [
        'total' => (int) ($r['total'] ?? 0),
        'guru'  => (int) ($r['guru'] ?? 0),
        'kelas' => (int) ($r['kelas'] ?? 0),
        'mapel' => (int) ($r['mapel'] ?? 0),
        'hari'  => (int) ($r['hari'] ?? 0),
        'baris' => (int) ($baris['n'] ?? 0),
    ];
}

/**
 * Tautan "Lihat Jurnal Pembelajaran" — membuka `jurnal.php` yang SUDAH tersaring untuk satu baris
 * rekap (guru + kelas + mata pelajaran + periode), lalu melompat ke tabel jurnalnya.
 * Parameter yang tidak diketahui dibiarkan kosong supaya guru yang tidak tercatat tetap terbuka.
 */
function pertemuan_link_jurnal(array $r, ?array $ta = null, ?array $sem = null, bool $semuaPeriode = false): string
{
    $q = array_filter([
        'guru' => (int) ($r['guru_id'] ?? 0) ?: null,
        'kelas' => (int) ($r['kelas_id'] ?? 0) ?: null,
        'mapel' => (int) ($r['mapel_id'] ?? 0) ?: null,
        'ta' => $ta ? (int) $ta['id'] : null,
        'sem' => $sem ? (int) $sem['id'] : null,
        'periode' => $semuaPeriode ? 'semua' : null,
        // Rekap hanya menghitung kegiatan "Mengajar", jadi daftar jurnal yang dibuka pun disaring
        // begitu — kalau tidak, jumlah barisnya tidak akan sama dengan Jumlah Tatap Muka.
        'uraian' => 'mengajar',
    ]);
    return 'jurnal.php' . ($q ? '?' . http_build_query($q) : '') . '#tblJurnal';
}

// ------------------------------------------------------------------ notulen rapat

/** Satu notulen rapat milik sekolah yang sedang dikelola (anti-IDOR: wajib cocok sekolah_id). */
function notulen_by_id(int $id): ?array
{
    $sid = sekolah_id();
    if ($id <= 0 || $sid <= 0) {
        return null;
    }
    return one('SELECT * FROM notulen WHERE id = ? AND sekolah_id = ?', [$id, $sid]);
}

/**
 * Peserta rapat (daftar hadir guru) sebuah notulen, urut sesuai urutan penyimpanan.
 * Nama/NIP/jabatan adalah SNAPSHOT saat notulen dibuat.
 */
function notulen_peserta(int $notulenId): array
{
    if ($notulenId <= 0) {
        return [];
    }
    return all('SELECT * FROM notulen_hadir WHERE notulen_id = ? ORDER BY urutan, id', [$notulenId]);
}

/** Jumlah guru yang menghadiri rapat. */
function notulen_jumlah_hadir(int $notulenId): int
{
    if ($notulenId <= 0) {
        return 0;
    }
    return (int) val('SELECT COUNT(*) FROM notulen_hadir WHERE notulen_id = ?', [$notulenId], 0);
}

/**
 * Foto BUKTI KEGIATAN sebuah notulen (maksimal NOTULEN_FOTO_MAKS per notulen), urut penyimpanan.
 * Yang tersimpan hanyalah URL hasil media proxy platform.
 */
function notulen_foto(int $notulenId): array
{
    if ($notulenId <= 0) {
        return [];
    }
    return all('SELECT * FROM notulen_foto WHERE notulen_id = ? ORDER BY urutan, id', [$notulenId]);
}

/** Jumlah foto bukti kegiatan sebuah notulen. */
function notulen_jumlah_foto(int $notulenId): int
{
    if ($notulenId <= 0) {
        return 0;
    }
    return (int) val('SELECT COUNT(*) FROM notulen_foto WHERE notulen_id = ?', [$notulenId], 0);
}

/** Sisa slot foto yang masih dapat diunggah untuk sebuah notulen. */
function notulen_sisa_slot_foto(int $notulenId): int
{
    return max(0, NOTULEN_FOTO_MAKS - notulen_jumlah_foto($notulenId));
}

/**
 * Ukuran bingkai foto bukti kegiatan PADA LEMBAR CETAK.
 *
 * Permintaan pemilik: seluruh dokumentasi harus muat dalam **SATU lembar** A4 landscape; bila perlu
 * ukuran fotonya diperkecil. Karena itu ukuran di sini BUKAN ukuran cetak 3R/4R apa adanya
 * (3R/4R itu format POTRAIT: 4R = 101,6 × 152,4 mm — tingginya melebihi tinggi kertas A4 landscape
 * yang hanya ±190 mm setelah margin), melainkan ukuran yang **dibatasi agar muat satu halaman**:
 *   - rasio bingkai 3:2 (landscape) supaya foto tampil besar, seragam, dan rapi;
 *   - lebarnya dibatasi 128 mm (2 foto + jarak 8 mm harus ≤ lebar konten ±277 mm);
 *   - tingginya dibatasi 74 mm (sisa tinggi setelah kop, judul, identitas, keterangan, tanda tangan).
 * Konfigurasi NOTULEN_FOTO_CETAK (3R/4R) tetap berpengaruh sebagai BATAS ATAS ukuran foto —
 * memilih 3R menghasilkan foto yang lebih kecil daripada 4R, dan keduanya tetap muat satu halaman.
 */
function foto_cetak_ukuran(): array
{
    $daftar = [
        // nama  => [lebar mm, tinggi mm]  — ukuran cetak asli (potrait) sebagai batas atas
        '4R' => [101.6, 152.4],   // 4 x 6 inci
        '3R' => [88.9, 127.0],    // 3,5 x 5 inci
    ];
    $nama = strtoupper((string) (defined('NOTULEN_FOTO_CETAK') ? NOTULEN_FOTO_CETAK : '4R'));
    if (!isset($daftar[$nama])) {
        $nama = '4R';
    }
    [$lebarAsli, $tinggiAsli] = $daftar[$nama];

    // Anggaran satu halaman A4 landscape (konten ±277 × 190 mm).
    $lebarBatasSatuHalaman = 128.0;   // 2 foto + jarak
    $tinggiBatasSatuHalaman = 74.0;   // sisa tinggi setelah komponen lain

    $lebar = min($lebarBatasSatuHalaman, $lebarAsli);
    $tinggi = round($lebar * 2 / 3, 1);            // rasio 3:2 (landscape)
    if ($tinggi > $tinggiBatasSatuHalaman) {        // jaga-jaga bila batas lebar dinaikkan kelak
        $tinggi = $tinggiBatasSatuHalaman;
        $lebar = round($tinggi * 3 / 2, 1);
    }

    return [
        'nama'        => $nama,
        'lebar'       => $lebar,
        'tinggi'      => $tinggi,
        'kolom'       => 2,                 // maksimal 2 foto per notulen → selalu 2 sebaris
        'asli'        => [$lebarAsli, $tinggiAsli],
        'satu_lembar' => true,
    ];
}

/**
 * Rapikan nomor urut foto sebuah notulen menjadi 0,1,2,… supaya tidak berlubang setelah
 * sebuah foto dihapus (dipakai upload_notulen_foto.php).
 */
function notulen_foto_urutkan_ulang(int $notulenId): void
{
    $urut = 0;
    foreach (notulen_foto($notulenId) as $f) {
        q('UPDATE notulen_foto SET urutan = ? WHERE id = ? AND notulen_id = ?', [$urut, (int) $f['id'], $notulenId]);
        $urut++;
    }
}

// ------------------------------------------------------------------ otorisasi & penjaga halaman

function tolak403(string $pesan): void
{
    http_response_code(403);
    exit($pesan);
}

/**
 * Berkas yang boleh dibuka akun PENGAWAS. Selain ini, pengawas ditolak 403.
 * Semuanya HANYA-LIHAT + lembar cetak (permintaan pemilik), tanpa halaman pengubahan,
 * tanpa halaman pengaturan/akun pengguna, dan TANPA unduhan (ekspor CSV tidak diizinkan).
 */
function pengawas_berkas_boleh(): array
{
    return [
        'index.php',        // daftar binaan + dashboard binaan (contoh: rekapan)
        // DASHBOARD PENGAWAS (v1.71.6 — permintaan pemilik): grafik lintas binaan
        // (guru/siswa per binaan, sebaran jenis lembaga, baris nilai, tren jurnal 6 bulan).
        // Hanya-baca: tidak ada POST di halaman ini.
        'dashboard_pengawas.php',
        'guru.php', 'siswa.php', 'absensi.php', 'nilai.php', 'jurnal.php', 'kasus.php',
        'cetak_guru.php', 'cetak_siswa.php', 'cetak_kartu_siswa.php',
        'cetak_nilai.php', 'cetak_absensi.php', 'cetak_jurnal.php', 'cetak_kasus.php',
        'cetak_rekap_binaan.php',
        'smart_teacher.php', 'aplikasi_sagu.php', 'hasil_supervisi.php',   // daftar aplikasi GLOBAL (semua peran)
        'studio_foto.php',                            // daftar aplikasi Studio Foto Pintar (v1.72.4, semua peran)
        'ujian_online.php',                           // daftar link aplikasi UJIAN ONLINE (semua peran)
        'tugas.php',                                  // isi menu Tugas Tambahan (hanya lihat)
        'cetak_sarpras.php',                          // lembar cetak data barang (hanya cetak)
        'cetak_kesiswaan.php',                        // lembar cetak program kerja Kesiswaan (hanya cetak)
        // v1.71.1: lembar cetak REKAP PELANGGARAN SISWA dari seluruh guru mata pelajaran
        // (tab "Rekap Pelanggaran" pada menu Kesiswaan) — pengawas hanya melihat & mencetak.
        'cetak_kesiswaan_pelanggaran.php',
        'cetak_osim_bagan.php',                       // lembar cetak BAGAN susunan organisasi OSIM
        'cetak_osim_organisasi.php',                  // lembar cetak susunan organisasi OSIM (hanya cetak)
        'cetak_osim_program.php',                     // lembar cetak program kerja OSIM (hanya cetak)
        'cetak_perpus.php',                           // lembar cetak laporan perpustakaan hasil impor e-PERPUS
        'cetak_lab.php',                              // lembar cetak inventaris & program kerja lab
        'cetak_wali_struktur.php', 'cetak_wali_bagan.php',   // lembar cetak struktur & bagan kelas
        'cetak_wali_program.php', 'cetak_wali_absensi.php',  // lembar cetak program kerja & absensi kelas
        'cetak_wali_piket.php',                       // lembar cetak jadwal piket kelas
        // v1.69.0: LEGER NILAI KELAS (rekap semua mata pelajaran + predikat & peringkat) — pengawas
        // boleh MELIHAT & MENCETAK rekap kelas binaannya (tidak menulis apa pun).
        'cetak_wali_leger.php',
        'akun.php', 'logout.php',
        // HALAMAN STATUS & AKTIVASI (v1.71.8 — PERBAIKAN BUG). Pengawas BUKAN pengguna lembaga
        // binaannya, jadi saat masa aktif sebuah binaan habis ia TIDAK boleh dikunci seperti kepala/
        // operator; halaman ini hanya menampilkan KETERANGAN (binaan mana yang dapat dibuka) dan
        // tidak punya formulir kode untuk pengawas. Sebelumnya berkas ini tidak terdaftar sehingga
        // pengalihan gerbang aktivasi berakhir sebagai halaman 403 mentah tanpa tema — persis keluhan
        // pemilik ("aplikasi mengalami kendala saat dibuka"). POST tetap DITOLAK karena berkas ini
        // sengaja TIDAK ada pada `pengawas_berkas_post()`.
        'aktivasi.php',
        'petunjuk.php',                               // panduan pemakaian (hanya dibaca)
        'kirim_dokumen.php',                          // kirim dokumen ke guru binaan (pengawas pengirim)
        // DAFTAR HADIR MEETING/PERTEMUAN (v1.73.0/skema v55) — permintaan pemilik: *"Pada akun
        // pengawas tambahkan menu aktifkan daftar hadir meeting (pertemuan/zoom) …"*. Pengawas
        // MEMBUAT pertemuan, mengaktifkan daftar hadir binaannya, dan menandatangani sebagai
        // Pemimpin Meeting — karena itu berkas-berkas ini juga terdaftar di `pengawas_berkas_post()`.
        // Lembar cetaknya boleh dibuka pengawas (mencetak seluruh binaan sekaligus).
        'meeting.php', 'upload_meeting_ttd.php', 'cetak_meeting.php',
        // PERANGKAT GURU (v1.67.0/skema v48) — permintaan pemilik: *"File yang terkirim masuk di akun
        // Kepala sekolah …, dan Pengawas (Pengawas hanya lihat dan download)."* Halaman ini memakai
        // satu berkas untuk kepala (meninjau) & pengawas (hanya melihat); POST oleh pengawas DITOLAK
        // gerbang (berkas ini sengaja TIDAK ada pada `pengawas_berkas_post()`).
        'perangkat_masuk.php',
        // PERANGKAT GURU — HALAMAN PENGAWAS (v1.72.0/skema v52). Permintaan pemilik: *"Pada akun
        // pengawas tambahkan menu Perangkat Guru (Perangkat guru ditampilkan per lembaga yang di
        // dalamnya ada daftar guru + mata pelajaran dan daftar perangkatnya). Pengawas bisa melihat,
        // memberikan catatan setiap perangkat dan mendownload"*. Halaman ini MEMANG menerima POST
        // (menyimpan catatan pengawas), jadi berkasnya juga terdaftar di `pengawas_berkas_post()` —
        // tanpa itu seluruh formulir catatan dijawab 403 oleh gerbang pengawas.
        'perangkat_pengawas.php',
        // OBROLAN/CHAT (v1.51.0) — permintaan pemilik: fitur chat dipakai SEMUA PERAN, termasuk
        // pengawas. Berkas ini hanya menyentuh tabel obrolan (bukan data sekolah) sehingga tidak
        // melanggar aturan "pengawas hanya melihat & mencetak" data binaan.
        'chat.php',
        // FOTO PROFIL milik pengawas SENDIRI — permintaan pemilik: *"Pada akun pengawas pastikan
        // pengawas bisa mengunggah foto profil. Karena saat ini fitur itu terkunci sehingga pengawas
        // tidak bisa menambahkan foto profil"*. Berkas ini hanya menyentuh baris `users` milik
        // pengguna yang sedang masuk (bukan data sekolah/madrasah binaan), jadi TIDAK melanggar
        // aturan "pengawas hanya melihat & mencetak". ⚠️ Sebelumnya berkas ini hanya terdaftar pada
        // `pengawas_berkas_post()` — dan karena pemeriksaan BERKAS dijalankan lebih dulu, seluruh
        // unggahan (multipart maupun raw body) dijawab 403. Jangan dihapus dari daftar ini.
        'upload_foto.php',
    ];
}

/** Berkas yang boleh menerima POST dari akun pengawas (sisanya 403 = benar-benar hanya-lihat). */
function pengawas_berkas_post(): array
{
    return ['index.php', 'akun.php', 'upload_foto.php', 'logout.php',
            // Mengirim/menghapus dokumen untuk guru binaan — permintaan pemilik: "Pada halaman
            // Pengawas tambahkan Menu Kirim Dokumen …". Ini SATU-SATUNYA berkas pengawas yang boleh
            // menerima POST selain pengaturan akunnya sendiri; penghapusan tetap dibatasi
            // kepemilikan lewat dokumen_hapus_saya().
            'kirim_dokumen.php',
            // v1.73.0: membuat/mengubah meeting miliknya sendiri + menandatangani sebagai Pemimpin
            // Meeting. Tanda tangan peserta tetap ditolak dari akun pengawas (peran peserta = kepala/
            // operator/guru), dan kepemilikan meeting selalu diperiksa di server.
            'meeting.php', 'upload_meeting_ttd.php',
            // OBROLAN/CHAT (v1.51.0): mengirim pesan obrolan — bukan pengubahan data sekolah.
            'chat.php',
            // PERANGKAT GURU — halaman pengawas (v1.72.0/skema v52): menyimpan CATATAN PENGAWAS pada
            // sebuah perangkat. Catatan itu tersimpan di tabelnya sendiri (`perangkat_catatan`) dan
            // TIDAK mengubah status tinjauan kepala sekolah maupun data sekolah lainnya.
            'perangkat_pengawas.php'];
}

/**
 * GERBANG PENGAWAS (hanya-lihat) — dipanggil dari require_login().
 *
 * Pengawas hanya boleh MELIHAT & MENCETAK data binaannya:
 *   1. berkas di luar daftar putih → 403;
 *   2. POST di luar daftar putih → 403 (semua pengubahan data mustahil dilakukan).
 * Pemeriksaan dijalankan pada SATU titik (require_login) supaya tidak ada halaman yang terlewat.
 */
function gerbang_pengawas(): void
{
    if (!is_pengawas()) {
        return;
    }
    $berkas = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!in_array($berkas, pengawas_berkas_boleh(), true)) {
        tolak403('Akun pengawas hanya dapat melihat dan mencetak data sekolah/madrasah binaannya.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !in_array($berkas, pengawas_berkas_post(), true)) {
        tolak403('Akun pengawas tidak dapat mengubah data (hanya melihat dan mencetak).');
    }
}

/**
 * Berkas yang boleh dibuka akun SISWA.
 *
 * Permintaan pemilik: *"Pada halaman siswa hanya bisa melihat dan mencetak"* — karena itu daftarnya
 * HANYA berisi halaman siswa sendiri beserta lembar cetaknya, ditambah halaman aktivasi (supaya
 * siswa tetap melihat keterangan saat aplikasi sekolahnya terkunci) dan keluar.
 * JANGAN menambahkan menu pengelolaan ke daftar ini.
 */
function siswa_berkas_boleh(): array
{
    return [
        'index.php',            // dasbor siswa
        'siswa_saya.php',       // Data Saya (nama & NISN — hanya dibaca)
        'siswa_absensi.php',    // rekapan daftar hadir (per mata pelajaran & per kelas)
        'siswa_nilai.php',      // rekapan nilai seluruh mata pelajaran
        'siswa_pelanggaran.php',// catatan pelanggaran dari guru mata pelajaran
        'cetak_siswa_data.php', 'cetak_siswa_absensi.php',
        'cetak_siswa_nilai.php', 'cetak_siswa_pelanggaran.php',
        'aktivasi.php',         // keterangan bila aplikasi sekolah terkunci
        'logout.php',
    ];
}

/**
 * GERBANG SISWA (hanya-lihat data sendiri) — dipanggil dari require_login().
 *
 * 1. berkas di luar daftar putih → 403;
 * 2. POST apa pun → 403 (siswa tidak memiliki formulir pengubahan sama sekali);
 * 3. keadaan siswanya diperiksa ulang setiap permintaan (dinonaktifkan/diluluskan/dihapus saat
 *    masih masuk → sesi langsung diakhiri supaya data yang tampil tidak "nyangkut").
 */
function gerbang_siswa(): void
{
    if (!is_siswa()) {
        return;
    }
    $berkas = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!in_array($berkas, siswa_berkas_boleh(), true)) {
        tolak403('Akun siswa hanya dapat melihat dan mencetak data miliknya sendiri.');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        tolak403('Akun siswa tidak dapat mengubah data apa pun (hanya melihat dan mencetak).');
    }
    // Keadaan siswa diperiksa ulang: akun yang dinonaktifkan/diluluskan/dihapus saat masih masuk
    // tidak boleh terus memakai sesinya.
    if ($berkas === 'logout.php') {
        return;
    }
    if (siswa_saya() === null) {
        unset($_SESSION['uid']);
        flash('Akun siswa ini sudah tidak aktif (data siswanya dinonaktifkan atau diluluskan). '
            . 'Hubungi Admin Operator sekolah/madrasah Anda.', 'warn');
        redirect('login.php');
    }
}

/**
 * Berkas yang tetap boleh dibuka walau masa uji coba sekolah sudah berakhir:
 * halaman aktivasi itu sendiri dan keluar. Selain ini, seluruh menu dikunci.
 */
function aktivasi_berkas_bebas(): array
{
    return ['aktivasi.php', 'logout.php'];
}

/**
 * GERBANG AKTIVASI — dipanggil dari require_login().
 *
 * Bila masa uji coba sekolah berakhir (dan belum diaktivasi permanen), SEMUA halaman aplikasi
 * dialihkan ke `aktivasi.php` sampai KODE AKTIVASI dimasukkan (permintaan pemilik: pengelola tetap
 * bisa masuk, tetapi seluruh menu terkunci ke halaman Aktivasi).
 * Super Admin (pemilik aplikasi) TIDAK pernah terkunci supaya tetap bisa membantu sekolah.
 */
function gerbang_aktivasi(): void
{
    if (is_super()) {
        return;
    }
    $berkas = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($berkas, aktivasi_berkas_bebas(), true)) {
        return;
    }
    $s = current_sekolah();
    if (!$s || !aktivasi_terkunci($s)) {
        return;
    }
    // -------------------------------------------------------------------------------------------
    // PENGAWAS (v1.71.8 — PERBAIKAN BUG): pengawas BUKAN pengguna lembaga binaannya. Dulu ia ikut
    // dialihkan ke `aktivasi.php`, tetapi berkas itu tidak ada pada daftar putihnya sehingga yang
    // tampil hanyalah teks 403 mentah tanpa tema — terlihat seperti "aplikasi rusak" (keluhan
    // pemilik). Karena itu konteks binaan yang masa aktifnya habis cukup DILEPAS: pengawas kembali
    // ke Daftar Binaan disertai keterangan bahwa binaan itu tidak dapat diakses, lalu binaan lain
    // tetap dapat dibuka seperti biasa. Data binaan yang terkunci tidak pernah ditampilkan.
    // -------------------------------------------------------------------------------------------
    if (is_pengawas()) {
        // POST `aksi=masuk`/`aksi=keluar` pada index.php HARUS tetap boleh berjalan (memilih binaan
        // lain / melepas binaan yang terkunci) — tanpa pengecualian ini pengawas tidak akan pernah
        // bisa keluar dari binaan yang masa aktifnya baru saja habis.
        if ($berkas === 'index.php') {
            $aksiPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? (string) ($_POST['aksi'] ?? '') : '';
            if (isset($_GET['keluar']) || in_array($aksiPost, ['masuk', 'keluar'], true)) {
                return;
            }
        }
        set_sekolah_context(null);
        flash(aktivasi_pesan_binaan_terkunci($s), 'warn');
        redirect('index.php');
    }
    flash(aktivasi_pesan_kunci($s), 'warn');
    redirect('aktivasi.php');
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['redirect_after'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
    log_sesi_ping();   // perbarui "terakhir aktif" agar daftar online akurat (maks 1x/60 detik)
    gerbang_aktivasi();  // kunci seluruh menu bila masa uji coba sudah berakhir
    gerbang_pengawas();  // pengawas: hanya berkas lihat/cetak + POST ditolak
    gerbang_siswa();     // siswa: hanya halaman sendiri (lihat/cetak) + POST ditolak
    gerbang_sandi();     // sandi lemah/bawaan: WAJIB diganti lebih dulu (popup + alihkan ke akun.php)
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
    if ($u['role'] === 'pengawas') {
        // Pengawas memilih binaan dari daftar binaannya (bukan dari Kelola Sekolah).
        flash('Pilih sekolah/madrasah binaan yang ingin dilihat terlebih dahulu.', 'warn');
        redirect('index.php');
    }
    http_response_code(403);
    exit('Akun ini belum ditautkan ke sekolah/madrasah mana pun. Hubungi Super Admin.');
}

/**
 * Boleh MELIHAT & MENCETAK data sekolah/madrasah: pengelola (Super Admin dalam konteks sekolah,
 * Admin Operator), **Kepala Sekolah/Madrasah**, ATAU pengawas (hanya lihat).
 *
 * Permintaan pemilik (v1.19.0): *"Pada akun kepala madrasah tambahkan fitur cetak pada menu data
 * guru dan data siswa."* → kepala boleh membuka lembar cetak (`cetak_guru.php`, `cetak_siswa.php`,
 * `cetak_kartu_siswa.php`). **Ekspor CSV tetap TIDAK untuk kepala** (itu fitur terpisah dan
 * penjaganya `require_master()`), demikian pula unduhan untuk pengawas (gerbang pengawas).
 */
function boleh_lihat_cetak(): bool
{
    return boleh_master() || is_kepala() || is_pengawas();
}

function require_lihat_cetak(): array
{
    $u = require_login();
    if (!boleh_lihat_cetak()) {
        tolak403('Halaman ini hanya untuk Super Admin (dalam konteks sekolah), Admin Operator, dan Pengawas.');
    }
    require_sekolah();
    return $u;
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

/**
 * KEPALA SEKOLAH/MADRASAH saja (bukan Admin Operator, bukan guru, bukan Super Admin dalam konteks).
 *
 * Dipakai halaman yang wewenangnya memang milik kepala sekolah/madrasah — saat ini
 * `akun_operator.php` (permintaan pemilik: "Pada halaman kepala sekolah tambahkan fitur agar bisa
 * menambahkan akun operatornya sendiri"). Super Admin memakai halaman `pengguna.php` untuk hal yang
 * sama, jadi sengaja TIDAK ikut di sini supaya wewenangnya tidak tumpang tindih.
 */
function require_kepala(): array
{
    $u = require_login();
    if (!is_kepala()) {
        tolak403('Halaman ini hanya untuk Kepala Sekolah/Madrasah.');
    }
    require_sekolah();
    return $u;
}

/** Jumlah akun aktif dengan peran tertentu pada sekolah/madrasah yang sedang aktif. */
function akun_jumlah_peran(string $role, int $sekolahId = 0): int
{
    $sid = $sekolahId > 0 ? $sekolahId : sekolah_id();
    if ($sid <= 0) {
        return 0;
    }
    return (int) val('SELECT COUNT(*) FROM users WHERE role = ? AND sekolah_id = ?', [$role, $sid], 0);
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

/**
 * Boleh MENGISI administrasi pembelajaran: **daftar hadir siswa, penilaian siswa (nilai),
 * jurnal harian, dan pelanggaran siswa** (menu "Pelanggaran Siswa").
 *
 * Permintaan pemilik (v1.21.0): *"Pada akun operator pada Menu Daftar Hadir siswa, Daftar Nilai,
 * Jurnal Harian dan Catatan Pelaggaran siswa, Admin Operator hanya bisa melihat dan cetak saja,
 * tidak bisa menambahkan pertemuan, nilai, catatan jurnal baru dan catatan pelanggaran baru."*
 * → **Admin Operator TIDAK lagi mengisi** keempat menu itu (hanya melihat & mencetak).
 *
 * Yang boleh mengisi:
 *   - **Guru** (pengampu kelas/mapel; halaman sudah membatasi cakupannya) — pengisi utama;
 *   - **Super Admin dalam konteks sekolah** — pemilik aplikasi, supaya tetap ada jalan masuk
 *     bila data perlu dibetulkan (pola yang sama dengan `boleh_ubah_tugas_menu()`).
 * Kepala Sekolah/Madrasah tetap hanya melihat & mencetak (seperti sebelumnya).
 */
function boleh_isi_administrasi(): bool
{
    return is_guru() || (is_super() && current_sekolah() !== null);
}

/** Penjaga halaman pengisian administrasi (form daftar hadir, dsb.). */
function require_isi_administrasi(string $apa = 'administrasi pembelajaran'): array
{
    $u = require_login();
    if (!boleh_isi_administrasi()) {
        if (is_operator()) {
            tolak403('Akun Admin Operator hanya dapat melihat dan mencetak ' . $apa . '. Pengisiannya '
                . 'dilakukan guru pengampu (Super Admin dapat membantu bila diperlukan).');
        }
        tolak403('Halaman ' . $apa . ' hanya dapat diisi oleh guru pengampu.');
    }
    require_sekolah();
    return $u;
}

/**
 * Kalimat pemberitahuan "hanya lihat" pada halaman administrasi pembelajaran (daftar hadir, nilai,
 * jurnal, catatan pelanggaran) — pengguna yang tidak mengisi (kepala & Admin Operator) diberi tahu
 * dengan jelas, sesuai permintaan pemilik v1.21.0.
 */
function pemberitahuan_hanya_lihat_administrasi(string $apa): string
{
    if (is_operator()) {
        $sebab = 'Akun <b>Admin Operator</b> hanya dapat melihat dan mencetak';
    } elseif (is_kepala()) {
        $sebab = 'Akun <b>Kepala Sekolah/Madrasah</b> hanya dapat melihat dan mencetak';
    } else {
        $sebab = 'Akun Anda hanya dapat melihat dan mencetak';
    }
    return $sebab . ' — halaman ini menampilkan <b>' . $apa . '</b>. Pengisiannya dilakukan '
        . '<b>guru pengampu</b>, sedangkan tombol <b>Cetak</b> tetap dapat dipakai.';
}

/**
 * Boleh MENGHAPUS catatan administrasi orang lain (pertemuan daftar hadir & catatan pelanggaran).
 * Sejak v1.21.0 hanya **Super Admin dalam konteks sekolah** — Admin Operator (yang kini hanya
 * melihat & mencetak) tidak lagi menghapus. Guru tetap boleh menghapus catatannya sendiri
 * (diperiksa terpisah di halaman masing-masing).
 */
function boleh_hapus_administrasi(): bool
{
    return is_super() && current_sekolah() !== null;
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

/**
 * Periksa ISI berkas SVG sebelum diteruskan ke penyimpanan.
 *
 * KEAMANAN (v1.73.4) — sebelumnya SVG lolos TANPA pemeriksaan isi (`getimagesize()` dilewati),
 * padahal SVG adalah dokumen yang boleh memuat skrip. Berkas semacam itu dapat menjadi
 * *stored XSS* pada domain penyimpanan (skrip berjalan saat URL-nya dibuka langsung) dan bahan
 * phishing dengan tampilan resmi aplikasi. Yang ditolak: elemen/atribut yang menjalankan skrip,
 * rujukan `javascript:`, entitas eksternal (XXE), dan `<!DOCTYPE`/`<iframe>`/`<foreignObject>`.
 *
 * @return array{ok:bool,pesan:string}
 */
function svg_aman(string $isi): array
{
    if ($isi === '') {
        return ['ok' => false, 'pesan' => 'Berkas SVG kosong.'];
    }
    if (strlen($isi) > 2 * 1024 * 1024) {
        return ['ok' => false, 'pesan' => 'Berkas SVG terlalu besar (maksimal 2 MB).'];
    }
    $bawah = strtolower($isi);
    if (strpos($bawah, '<svg') === false) {
        return ['ok' => false, 'pesan' => 'Berkas SVG ini tidak memuat gambar SVG yang sah.'];
    }
    $terlarang = ['<script', '<foreignobject', '<iframe', '<embed', '<object',
                  '<!entity', '<!doctype', 'javascript:', 'data:text/html'];
    foreach ($terlarang as $tanda) {
        if (strpos($bawah, $tanda) !== false) {
            return ['ok' => false, 'pesan' => 'Berkas SVG memuat kode yang tidak diizinkan ('
                . $tanda . ') sehingga ditolak demi keamanan.'];
        }
    }
    // Atribut penanganan peristiwa (onload=, onclick=, onerror=, …) juga menjalankan skrip.
    if (preg_match('/\son[a-z]{3,}\s*=/i', $isi) === 1) {
        return ['ok' => false, 'pesan' => 'Berkas SVG memuat penanganan peristiwa (on…) yang tidak diizinkan.'];
    }
    return ['ok' => true, 'pesan' => ''];
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
        return ['ok' => false, 'pesan' => 'Ukuran gambar maksimal ' . ukuran_berkas_teks(FOTO_MAKS_BYTE)
            . ' (berkas Anda ' . ukuran_berkas_teks($ukuran) . ').'];
    }
    if ($ext !== 'svg') {
        if (@getimagesize($tmpPath) === false) {
            return ['ok' => false, 'pesan' => 'Berkas ini sepertinya bukan gambar yang sah.'];
        }
    } else {
        // SVG WAJIB diperiksa isinya — tanpa itu berkas bisa memuat skrip (stored XSS).
        $isi = (string) @file_get_contents($tmpPath, false, null, 0, 2 * 1024 * 1024 + 1);
        $cekSvg = svg_aman($isi);
        if (empty($cekSvg['ok'])) {
            return ['ok' => false, 'pesan' => (string) $cekSvg['pesan']];
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

// ------------------------------------------------------------------ pas foto siswa 3x4

/** URL pas foto siswa (null bila belum diunggah). Dipakai form siswa & Kartu Pelajar. */
function siswa_foto(?array $s): ?string
{
    $f = $s['foto'] ?? null;
    return ($f !== null && trim((string) $f) !== '') ? trim((string) $f) : null;
}

/** Rasio pas foto yang diharapkan (3 : 4) beserta toleransinya. */
const PASFOTO_RASIO = 0.75;      // lebar / tinggi
const PASFOTO_TOLERANSI = 0.08;  // selisih rasio yang masih dianggap "mendekati 3:4"
const PASFOTO_MIN_PX = 240;      // lebar minimum yang dianjurkan (240 x 320 px)

/**
 * Proses PAS FOTO 3x4 yang dikirim lewat form ($_FILES[$namaField]) menjadi URL CDN
 * melalui media proxy platform. Dipakai form Tambah/Ubah Data Siswa supaya pas foto
 * tersimpan bersamaan dengan datanya, dan langsung dipakai pada Kartu Pelajar.
 *
 * Mengembalikan:
 *   ['ada' => bool, 'ok' => bool, 'url' => ?string, 'pesan' => string, 'peringatan' => string[]]
 * `ada` = pengguna benar-benar memilih berkas; `ok` = berkas sah dan berhasil diunggah.
 * Ukuran/rasio yang kurang ideal TIDAK ditolak, hanya dikembalikan sebagai peringatan
 * (guru perlu bisa mengunggah foto seadanya; pemotongan diatur kartu lewat object-fit).
 */
function unggah_pas_foto(string $namaField = 'foto'): array
{
    $hasil = ['ada' => false, 'ok' => true, 'url' => null, 'pesan' => '', 'peringatan' => []];
    $f = $_FILES[$namaField] ?? null;
    if (!is_array($f)) {
        return $hasil;
    }
    $kode = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
    $namaAsli = trim((string) ($f['name'] ?? ''));
    if ($kode === UPLOAD_ERR_NO_FILE || $namaAsli === '') {
        return $hasil;
    }
    $hasil['ada'] = true;

    if ($kode !== UPLOAD_ERR_OK) {
        $hasil['ok'] = false;
        $hasil['pesan'] = ($kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE)
            ? 'Pas foto terlalu besar untuk diunggah (batas server ' . ini_get('upload_max_filesize') . '). Perkecil dulu fotonya.'
            : 'Pas foto gagal diunggah (kode ' . $kode . '). Silakan coba lagi.';
        return $hasil;
    }

    $ukuran = (int) ($f['size'] ?? 0);
    $tmpPath = (string) ($f['tmp_name'] ?? '');
    $cek = validasi_foto($namaAsli, $ukuran, $tmpPath);
    if (empty($cek['ok'])) {
        $hasil['ok'] = false;
        $hasil['pesan'] = (string) $cek['pesan'];
        return $hasil;
    }

    // Peringatan bentuk/ukuran (tidak menolak) — dihitung dari ukuran gambar sebenarnya.
    $ext = strtolower((string) pathinfo($namaAsli, PATHINFO_EXTENSION));
    if ($ext !== 'svg') {
        $info = @getimagesize($tmpPath);
        if (is_array($info) && (int) $info[0] > 0 && (int) $info[1] > 0) {
            $w = (int) $info[0];
            $h = (int) $info[1];
            if ($h <= $w) {
                $hasil['peringatan'][] = 'Foto yang diunggah tidak tegak (lebar ' . $w . ' x tinggi ' . $h . ' px). Pas foto 3 x 4 sebaiknya tegak.';
            } elseif (abs(($w / $h) - PASFOTO_RASIO) > PASFOTO_TOLERANSI) {
                $hasil['peringatan'][] = 'Rasio foto ' . $w . ':' . $h . ' tidak persis 3:4; pada kartu pelajar foto akan dipotong otomatis (object-fit) agar tetap rapi.';
            }
            if ($w < PASFOTO_MIN_PX) {
                $hasil['peringatan'][] = 'Resolusi foto cukup kecil (' . $w . ' x ' . $h . ' px). Untuk hasil cetak yang tajam dianjurkan minimal ' . PASFOTO_MIN_PX . ' x ' . (int) round(PASFOTO_MIN_PX / PASFOTO_RASIO) . ' px.';
            }
        }
    }

    $bytes = @file_get_contents($tmpPath);
    if ($bytes === false || $bytes === '') {
        $hasil['ok'] = false;
        $hasil['pesan'] = 'Pas foto gagal dibaca di server. Silakan coba lagi.';
        return $hasil;
    }
    $unggah = media_upload($bytes, $namaAsli);
    if (empty($unggah['ok'])) {
        $hasil['ok'] = false;
        $hasil['pesan'] = (string) ($unggah['pesan'] ?? 'Pas foto gagal diunggah ke penyimpanan platform.');
        return $hasil;
    }
    $hasil['url'] = (string) $unggah['url'];
    return $hasil;
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

/** Seluruh nama diubah menjadi huruf kecil tanpa tanda baca (mis. "Ahmad Fauzi" → "ahmadfauzi"). */
function username_dari_nama(string $nama): string
{
    $u = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '.', $nama));
    $u = trim((string) preg_replace('/\.+/', '.', $u), '.');
    if ($u === '' || strlen($u) < 3) {
        $u = 'guru';
    }
    return substr($u, 0, 60);
}

/**
 * KATA AWAL nama guru sebagai dasar nama pengguna (permintaan pemilik: "pastikan user/nama pengguna
 * yang digunakan adalah nama awal guru (kata awal)").
 *
 * Contoh: "Astia A. Hanunu , S.Pd" → `astia`; "Nining, S.Pd" → `nining`;
 * "Sulistiawan, S.Pd" → `sulistiawan`. Tanda baca pada kata (mis. koma setelah nama depan)
 * dibuang. Bila kata awalnya terlalu pendek (< 3 karakter, mis. "H." atau "Ust."), dipakai
 * `username_dari_nama()` (seluruh nama) supaya nama pengguna tidak pernah terlalu pendek.
 * NIP/NUPTK SENGAJA tidak dipakai lagi sebagai nama pengguna.
 */
function guru_username_dasar(array $guru): string
{
    $nama = trim((string) ($guru['nama'] ?? ''));
    $kata = preg_split('/\s+/u', $nama) ?: [];
    $awal = '';
    foreach ($kata as $k) {
        $bersih = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $k));
        // Gelar/sapaan di depan (mis. "H. Ahmad Fauzi", "Ust. Abdul Rahman") dilewati supaya yang
        // dipakai benar-benar NAMA AWAL guru — bukan gelarnya.
        if ($bersih !== '' && !in_array($bersih, guru_gelar_depan(), true)) {
            $awal = $bersih;
            break;
        }
    }
    if (strlen($awal) < 3) {
        return username_dari_nama($nama);
    }
    return substr($awal, 0, 40);
}

/**
 * Gelar/sapaan yang biasa ditulis di DEPAN nama guru. Dipakai `guru_username_dasar()` agar
 * nama pengguna diambil dari nama awalnya, bukan dari gelar (mis. "H. Ahmad Fauzi" → `ahmad`).
 * Tambahkan di sini bila pemilik memakai sapaan lain yang lazim di sekolahnya.
 */
function guru_gelar_depan(): array
{
    return ['h', 'hj', 'haji', 'hajjah', 'drs', 'dra', 'dr', 'prof', 'ir', 'kh',
            'ust', 'ustzh', 'ustadz', 'ustadzah', 'pak', 'bu', 'ibu', 'bapak', 'sdr', 'sdri'];
}

/**
 * Nama pengguna unik se-aplikasi dari data guru: KATA AWAL namanya, ditambah angka bila sudah dipakai
 * (mis. `astia`, `astia2`, `astia3`). Dipakai SEMUA jalur pembuatan akun guru — jangan menulis
 * ulang logika ini di halaman lain.
 */
function guru_username_unik(array $guru): string
{
    $base = guru_username_dasar($guru);
    $username = $base;
    $n = 1;
    while (one('SELECT id FROM users WHERE username = ?', [$username])) {
        $n++;
        $username = $base . $n;
    }
    return $username;
}

/**
 * Kata sandi awal akun guru — SELALU 'guru123' (permintaan pemilik: "password defaulnya: guru123").
 * Sebelumnya memakai NIP bila panjangnya >= 6 karakter; sekarang tidak lagi supaya seragam.
 */
function guru_sandi_awal(array $guru): string
{
    return 'guru123';
}

// ------------------------------------------------------------------ log aktivitas & pengguna online
//
// Halaman Log Aktivitas (Super Admin) menampilkan DUA hal:
//   1. pengguna yang SEDANG ONLINE  -> tabel `sesi_aktif` (online bila aktif <= 15 menit lalu)
//   2. RIWAYAT masuk/keluar/gagal masuk -> tabel `log_aktivitas`
// Semua penulisan log dibungkus try/catch: pencatatan TIDAK BOLEH menggagalkan permintaan pengguna.

/** Jumlah menit tanpa aktivitas sebelum sebuah sesi dianggap tidak online lagi. */
const LOG_MENIT_ONLINE = 15;

/** true bila alamat IP termasuk loopback/privat/terpesan (mis. 127.0.0.1, 10.x, 192.168.x). */
function ip_privat(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return true;
    }
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

/**
 * Alamat IP pemakai — dipakai Log Aktivitas DAN pembatas percobaan masuk (anti brute force).
 *
 * ⚠️ KEAMANAN (jangan dikembalikan ke versi lama): versi lama mengambil entri **PERTAMA** dari
 * `X-Forwarded-For`, dan header itu **dapat ditulis sendiri oleh klien**. Akibatnya penyerang bisa
 * memalsukan alamat IP-nya sehingga (a) riwayat Log Aktivitas menyesatkan dan (b) pembatas
 * percobaan masuk bisa dilewati hanya dengan mengganti nilai header tiap percobaan.
 *
 * Urutan kepercayaan sekarang:
 *   1. `REMOTE_ADDR` — diisi server dari koneksi TCP sehingga TIDAK dapat dipalsukan klien. Dipakai
 *      bila alamatnya publik.
 *   2. Bila `REMOTE_ADDR` kosong/privat (aplikasi di belakang proxy lokal seperti Caddy di
 *      127.0.0.1), dipakai entri **TERAKHIR** dari `X-Forwarded-For` yang berupa alamat publik —
 *      entri terakhir itu nilai yang DITAMBAHKAN proxy, sedangkan nilai kiriman klien berada di
 *      sebelah kiri daftar.
 *   3. Cadangan terakhir: alamat privat/terpesan apa pun dari daftar tadi, lalu `REMOTE_ADDR`.
 */
function log_ip(): string
{
    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remote !== '' && filter_var($remote, FILTER_VALIDATE_IP) !== false && !ip_privat($remote)) {
        return substr($remote, 0, 45);
    }

    $fwd = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($fwd !== '') {
        $daftar = [];
        foreach (explode(',', $fwd) as $bagian) {
            $ip = trim($bagian);
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                $daftar[] = $ip;
            }
        }
        for ($i = count($daftar) - 1; $i >= 0; $i--) {   // dari KANAN (yang ditambahkan proxy)
            if (!ip_privat($daftar[$i])) {
                return substr($daftar[$i], 0, 45);
            }
        }
        if ($daftar) {
            return substr($daftar[count($daftar) - 1], 0, 45);
        }
    }

    return $remote === '' ? '-' : substr($remote, 0, 45);
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

/**
 * Tanggal & jam sebagai TEKS BIASA (tanpa entitas HTML), mis. "15 Juni 2026, 14:32:07".
 *
 * WAJIB dipakai bila hasilnya masuk ke dalam `e()` atau ke NILAI ATRIBUT (mis. `data-confirm`):
 * `waktu_id()` mengembalikan HTML ("&middot;") sehingga di dalam `e()` entitas itu ter-escape
 * menjadi `&amp;middot;` dan yang TAMPIL justru tulisan "&middot;" — pernah terjadi di aplikasi
 * Supervisi Guru. Untuk dicetak LANGSUNG ke HTML, pakai `waktu_id()`.
 */
function waktu_id_polos(?string $dt): string
{
    $dt = trim((string) $dt);
    if ($dt === '') {
        return '-';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return $dt;
    }
    return tanggal_id(date('Y-m-d', $ts)) . ', ' . date('H:i:s', $ts);
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

// ------------------------------------------------------------------ PEMBATAS PERCOBAAN MASUK
// (anti brute force — lihat konstanta LOGIN_* di inc/config.php)
//
// Sumber hitungan: riwayat percobaan GAGAL pada tabel `log_aktivitas` (kolom ip/username/waktu).
// Tidak ada tabel/kolom baru sehingga tidak ada migrasi. Keberhasilan masuk dari satu IP
// MENGOSONGKAN hitungan IP itu (pengguna yang berhasil tidak pernah "terbawa" kegagalan lamanya).
//
// ATURAN PENTING: fungsi ini TIDAK PERNAH melempar galat. Bila tabel log belum ada / basis data
// bermasalah, hasilnya "tidak diblokir" (0) — pembatas keamanan tidak boleh mengunci semua orang
// hanya karena pencatatan log gagal.

/**
 * Sisa detik pemblokiran percobaan masuk untuk satu alamat IP (0 = boleh mencoba).
 *
 * @param string $ip       hasil log_ip()
 * @param string $username nama pengguna yang dicoba (kosong = hanya hitungan per-IP yang dipakai)
 */
function login_blokir_detik(string $ip, string $username = ''): int
{
    $ip = trim($ip);
    if ($ip === '' || $ip === '-') {
        return 0;
    }
    try {
        $jendela = LOGIN_MENIT_GAGAL * 60;
        $batas  = date('Y-m-d H:i:s', time() - $jendela);   // awal rentang hitungan
        // Keberhasilan terakhir dari IP ini → kegagalan SEBELUMNYA tidak dihitung lagi.
        // ⚠️ Pembandingnya harus ID (bukan waktu): kolom `waktu` beresolusi DETIK sehingga
        // `waktu >= waktu_sukses` masih ikut menghitung kegagalan yang tercatat pada detik yang
        // SAMA dengan keberhasilan — akibatnya pengguna yang baru saja berhasil masuk bisa
        // terhitung 1 kegagalan lebih banyak (pernah membuat uji ini gagal sesekali).
        $suksesId = (int) val(
            "SELECT COALESCE(MAX(id), 0) FROM log_aktivitas WHERE aktivitas = 'masuk' AND ip = ?",
            [$ip],
            0
        );

        $rows = all(
            "SELECT waktu, username FROM log_aktivitas
              WHERE aktivitas = 'gagal' AND ip = ? AND id > ? AND waktu >= ?
              ORDER BY id",
            [$ip, $suksesId, $batas]
        );
        if (!$rows) {
            return 0;
        }

        $jmlIp   = count($rows);
        $jmlUser = 0;
        $tertuaUser = '';
        $username = trim($username);
        foreach ($rows as $r) {
            if ($username !== '' && strcasecmp(trim((string) $r['username']), $username) === 0) {
                $jmlUser++;
                if ($tertuaUser === '') {
                    $tertuaUser = (string) $r['waktu'];
                }
            }
        }

        $pelanggar = '';
        // Aturan per-IP hanya dipakai bila alamatnya berupa IP PUBLIK. Bila IP-nya privat/loopback
        // (mis. 127.0.0.1 karena permintaan datang dari proxy lokal yang tidak meneruskan alamat
        // asli), SEMUA pengguna tampak berasal dari satu alamat — aturan per-IP justru akan
        // mengunci semua orang. Dalam keadaan itu hanya aturan per-NAMA PENGGUNA yang dipakai.
        $privat = ip_privat($ip);
        if (!$privat && $jmlIp >= LOGIN_MAKS_GAGAL_IP) {
            $pelanggar = (string) $rows[0]['waktu'];
        }
        if ($jmlUser >= LOGIN_MAKS_GAGAL_USER && ($pelanggar === '' || $tertuaUser > $pelanggar)) {
            $pelanggar = $tertuaUser;
        }
        if ($pelanggar === '') {
            return 0;
        }

        $sisa = (int) strtotime($pelanggar) + $jendela - time();
        return $sisa > 0 ? $sisa : 0;
    } catch (Throwable $e) {
        return 0;   // jangan pernah mengunci pengguna karena kegagalan teknis
    }
}

/**
 * Sisa detik pemblokiran untuk percobaan masuk SISWA (dibedakan agar batasnya lebih ketat).
 *
 * Sejak v1.65.0 masuk siswa memakai **NISN + kata sandi (tanggal lahir)**, jadi ada DUA hitungan
 * yang diperiksa dan yang paling lama dipakai:
 *   1. **per NISN** (`LOGIN_MAKS_GAGAL_USER` kegagalan untuk NISN yang sama dalam `LOGIN_MENIT_GAGAL`
 *      menit) — inilah yang mencegah kata sandi satu siswa ditebak berulang-ulang. Aturan ini
 *      berlaku TANPA memandang alamat IP, karena di balik satu alamat (NAT sekolah) bisa ada banyak
 *      siswa; hitungannya memakai kunci `siswa:<NISN>` pada riwayat percobaan gagal.
 *   2. **per alamat IP publik** (`LOGIN_MAKS_GAGAL_SISWA`) — aturan lama, hanya untuk IP PUBLIK
 *      (IP privat/loopback = alamat bersama proxy lokal → jangan mengunci semua siswa).
 *
 * Hitungannya **tidak** dikosongkan oleh keberhasilan masuk, tetapi akan hilang sendiri 10 menit
 * setelah percobaan gagal yang paling tua (dan baris lama juga dipangkas `log_batasi_gagal()`) —
 * jadi paling lama akun terkunci sementara 10 menit, tidak permanen.
 */
function login_blokir_detik_siswa(string $ip, string $nisn = ''): int
{
    $ip = trim($ip);
    // Normalisasi yang SAMA dengan `nisn_bersih()` (dipakai login.php saat mencatat kegagalan).
    $kunciNisn = (string) preg_replace('/[^0-9A-Za-z]+/', '', $nisn);
    try {
        $jendela = LOGIN_MENIT_GAGAL * 60;
        $batas = date('Y-m-d H:i:s', time() - $jendela);
        $sisa = 0;

        // (1) Batas per NISN.
        if ($kunciNisn !== '') {
            $namaSiswa = 'siswa:' . $kunciNisn;
            $jml = (int) val(
                "SELECT COUNT(*) FROM log_aktivitas
                  WHERE aktivitas = 'gagal' AND username = ? AND waktu >= ?",
                [$namaSiswa, $batas],
                0
            );
            if ($jml >= LOGIN_MAKS_GAGAL_USER) {
                $tertua = (string) val(
                    "SELECT waktu FROM log_aktivitas
                      WHERE aktivitas = 'gagal' AND username = ? AND waktu >= ?
                      ORDER BY id LIMIT 1",
                    [$namaSiswa, $batas],
                    ''
                );
                if ($tertua !== '') {
                    $sisa = (int) strtotime($tertua) + $jendela - time();
                }
            }
        }

        // (2) Batas per alamat IP (hanya IP publik).
        if ($ip !== '' && $ip !== '-' && !ip_privat($ip)) {
            // Pembanding ID (bukan waktu) — lihat catatan pada login_blokir_detik().
            $suksesId = (int) val(
                "SELECT COALESCE(MAX(id), 0) FROM log_aktivitas WHERE aktivitas = 'masuk' AND ip = ?",
                [$ip],
                0
            );
            $jmlIp = (int) val(
                "SELECT COUNT(*) FROM log_aktivitas
                  WHERE aktivitas = 'gagal' AND ip = ? AND username LIKE 'siswa:%'
                    AND id > ? AND waktu >= ?",
                [$ip, $suksesId, $batas],
                0
            );
            if ($jmlIp >= LOGIN_MAKS_GAGAL_SISWA) {
                $tertuaIp = (string) val(
                    "SELECT waktu FROM log_aktivitas
                      WHERE aktivitas = 'gagal' AND ip = ? AND username LIKE 'siswa:%'
                        AND id > ? AND waktu >= ?
                      ORDER BY id LIMIT 1",
                    [$ip, $suksesId, $batas],
                    ''
                );
                if ($tertuaIp !== '') {
                    $sisaIp = (int) strtotime($tertuaIp) + $jendela - time();
                    if ($sisaIp > $sisa) {
                        $sisa = $sisaIp;
                    }
                }
            }
        }

        return $sisa > 0 ? $sisa : 0;
    } catch (Throwable $e) {
        return 0;
    }
}

// ------------------------------------------------------------------ PEMBATAS ENDPOINT PUBLIK (IP)
// KEAMANAN (v1.73.4): pendaftaran sekolah & formulir pesan halaman depan dulu hanya dibatasi
// "N kali per SESI". Batas berbasis sesi dapat dilewati cukup dengan mengosongkan cookie, sehingga
// program dapat membuat sekolah/akun atau mengirim pesan tanpa henti (basis data membengkak dan
// kuota penyimpanan aplikasi terkuras). Sekarang ditambah pembatas per ALAMAT IP PUBLIK pada tabel
// `throttle`; IP privat/loopback dilewati supaya pengujian lokal & proxy internal tidak terkunci.

/** true bila alamat IP ini layak dijadikan kunci pembatas (bukan privat/loopback). */
function throttle_ip_layak(string $ip): bool
{
    $ip = trim($ip);
    return $ip !== '' && $ip !== '-' && filter_var($ip, FILTER_VALIDATE_IP) !== false && !ip_privat($ip);
}

/** Jumlah kejadian tercatat untuk kunci ini dalam N menit terakhir. */
function throttle_jumlah(string $kunci, int $menit): int
{
    try {
        return (int) val('SELECT COUNT(*) FROM throttle WHERE kunci = ? AND waktu >= ?',
            [$kunci, date('Y-m-d H:i:s', time() - max(1, $menit) * 60)], 0);
    } catch (Throwable $e) {
        return 0;   // tabel belum ada / galat → jangan pernah memblokir pengguna karena teknis
    }
}

/**
 * Catat satu kejadian dan kembalikan TRUE bila batasnya sudah terlampaui
 * (dipakai sebelum memproses permintaan: "sudah terlalu banyak" → tolak).
 */
function throttle_lewat(string $kunci, int $maks, int $menit): bool
{
    try {
        if (throttle_jumlah($kunci, $menit) >= max(1, $maks)) {
            return true;
        }
        // Buang catatan lama sekadarnya supaya tabel tidak tumbuh tanpa batas.
        if (random_int(1, 20) === 1) {
            q('DELETE FROM throttle WHERE waktu < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        }
        q('INSERT INTO throttle (kunci, waktu) VALUES (?, ?)', [$kunci, date('Y-m-d H:i:s')]);
        return false;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Teks ukuran berkas yang ramah dibaca dan SERAGAM di seluruh aplikasi.
 * Dipakai halaman & pesan validasi supaya angka batas tidak pernah ditulis mati
 * (lihat UNGGAH_MAKS_BYTE di inc/config.php).
 */
function ukuran_berkas_teks(int $byte): string
{
    if ($byte <= 0) {
        return '0 byte';
    }
    if ($byte >= 1024 * 1024) {
        $mb = $byte / 1048576;
        return rtrim(rtrim(number_format($mb, 2, ',', '.'), '0'), ',') . ' MB';
    }
    if ($byte >= 1024) {
        return r0($byte / 1024) . ' KB';
    }
    return $byte . ' byte';
}

/** Batas unggah per berkas dalam bentuk teks ("2 MB") — dipakai keterangan pada formulir. */
function maks_unggah_teks(): string
{
    return ukuran_berkas_teks(UNGGAH_MAKS_BYTE);
}

// ------------------------------------------------------------------ KEBIJAKAN KATA SANDI (v1.74.0)
// Permintaan pemilik: minimal 8 karakter + kombinasi huruf besar, huruf kecil, dan angka; akun yang
// sandinya masih lemah (termasuk sandi awal guru123/admin123) WAJIB menggantinya lebih dulu.

/** Kalimat kebijakan sandi untuk ditampilkan pada formulir/pesan. */
function sandi_kebijakan_teks(): string
{
    $syarat = [];
    if (SANDI_WAJIB_BESAR) {
        $syarat[] = 'huruf besar';
    }
    if (SANDI_WAJIB_KECIL) {
        $syarat[] = 'huruf kecil';
    }
    if (SANDI_WAJIB_ANGKA) {
        $syarat[] = 'angka';
    }
    return 'minimal ' . SANDI_MIN . ' karakter'
        . ($syarat ? ', kombinasi ' . implode(', ', $syarat) : '');
}

/** Daftar sandi lemah/bawaan yang sudah diketahui (huruf kecil semua). */
function sandi_lemah_daftar(): array
{
    return array_map('strtolower', SANDI_LEMAH);
}

/**
 * Periksa sebuah kata sandi terhadap kebijakan.
 *
 * @param bool $penuh  true = seluruh syarat (dipakai saat pengguna MENETAPKAN SANDINYA SENDIRI);
 *                     false = hanya panjang minimal (dipakai saat pengelola menetapkan sandi
 *                     sementara untuk akun orang lain — sandi lemah tetap ditandai wajib ganti).
 * @return array{ok:bool,pesan:string}
 */
function sandi_pemeriksa(string $sandi, bool $penuh = true): array
{
    if (strlen($sandi) < SANDI_MIN) {
        return ['ok' => false, 'pesan' => 'Kata sandi minimal ' . SANDI_MIN . ' karakter.'];
    }
    if (strlen($sandi) > 200) {
        return ['ok' => false, 'pesan' => 'Kata sandi terlalu panjang (maksimal 200 karakter).'];
    }
    if (!$penuh) {
        return ['ok' => true, 'pesan' => ''];
    }
    if (SANDI_WAJIB_BESAR && !preg_match('/[A-Z]/', $sandi)) {
        return ['ok' => false, 'pesan' => 'Kata sandi harus memuat HURUF BESAR.'];
    }
    if (SANDI_WAJIB_KECIL && !preg_match('/[a-z]/', $sandi)) {
        return ['ok' => false, 'pesan' => 'Kata sandi harus memuat huruf kecil.'];
    }
    if (SANDI_WAJIB_ANGKA && !preg_match('/[0-9]/', $sandi)) {
        return ['ok' => false, 'pesan' => 'Kata sandi harus memuat angka.'];
    }
    return ['ok' => true, 'pesan' => ''];
}

/** true bila sandi ini KURANG dari minimal atau termasuk sandi yang sudah diketahui umum. */
function sandi_lemah_dasar(string $sandi): bool
{
    $sandi = trim($sandi);
    if ($sandi === '' || strlen($sandi) < SANDI_MIN) {
        return true;
    }
    return in_array(strtolower($sandi), sandi_lemah_daftar(), true);
}

/**
 * Apakah akun ini WAJIB mengganti kata sandinya lebih dulu?
 * Akun SISWA dikecualikan: sandinya adalah tanggal lahir (tidak disimpan, tidak dapat diganti
 * sendiri oleh siswa) — memaksa mereka mengganti tidak mungkin dan tidak ada gunanya.
 */
function sandi_wajib_ganti(?array $u = null): bool
{
    $u = $u ?? current_user();
    if (!$u) {
        return false;
    }
    if ((string) ($u['role'] ?? '') === 'siswa') {
        return false;
    }
    return (int) ($u['sandi_wajib_ganti'] ?? 0) === 1;
}

/**
 * Simpan kata sandi baru untuk sebuah akun + tentukan penanda wajib-ganti.
 *
 * @param string $sandi        sandi baru (sudah divalidasi pemanggil)
 * @param bool   $wajibGanti   true = pengguna wajib menggantinya saat masuk berikutnya
 */
function sandi_simpan(int $userId, string $sandi, bool $wajibGanti = false): void
{
    if ($userId <= 0 || $sandi === '') {
        return;
    }
    q('UPDATE users SET password_hash = ?, sandi_wajib_ganti = ?, sandi_diubah = ? WHERE id = ?',
        [password_hash($sandi, PASSWORD_DEFAULT), $wajibGanti ? 1 : 0, date('Y-m-d H:i:s'), $userId]);
}

/** Berkas yang TETAP dapat dibuka saat akun wajib mengganti sandi. */
function sandi_berkas_bebas(): array
{
    return [
        'akun.php',        // formulir ganti sandi
        'upload_foto.php', // foto profil (bagian dari halaman Akun Saya)
        'logout.php',
        'login.php',
        'aktivasi.php',    // status & aktivasi lembaga (di luar urusan sandi akun)
        'chat.php',        // endpoint JSON — kalau dialihkan, klien menerima HTML (galat aneh)
        'daftar.php',      // pendaftaran publik (tanpa login)
        'lembaga.php',     // halaman data lembaga publik (token)
    ];
}

/**
 * GERBANG WAJIB GANTI SANDI — dipanggil dari require_login().
 *
 * Selama penanda `users.sandi_wajib_ganti` aktif, seluruh halaman pengelolaan dialihkan ke
 * `akun.php?wajib_sandi=1` (popup + formulir ganti sandi). Permintaan AJAX/JSON dijawab 403 JSON
 * supaya klien tidak menerima halaman HTML sebagai "jawaban JSON" (pola sama dengan gerbang lain).
 */
function gerbang_sandi(): void
{
    $u = current_user();
    if (!$u || !sandi_wajib_ganti($u)) {
        return;
    }
    $berkas = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($berkas, sandi_berkas_bebas(), true)) {
        return;
    }
    $inginJson = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')
        || (isset($_GET['format']) && $_GET['format'] === 'json');
    if ($inginJson) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'wajib_sandi' => true,
            'pesan_galat' => 'Kata sandi akun Anda wajib diganti lebih dulu. Buka halaman Akun Saya.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    flash('Kata sandi akun Anda belum memenuhi kebijakan keamanan. Wajib diganti terlebih dahulu.', 'warn');
    redirect('akun.php?wajib_sandi=1');
}

/** Kalimat sisa waktu tunggu yang ramah dibaca manusia ("2 menit", "45 detik"). */
function login_tunggu_teks(int $detik): string
{
    $detik = max(1, $detik);
    if ($detik < 60) {
        return $detik . ' detik';
    }
    $menit = (int) ceil($detik / 60);
    return $menit . ' menit';
}

/** Pesan yang ditampilkan saat percobaan masuk sedang dibatasi. */
function login_pesan_blokir(int $detik): string
{
    return 'Percobaan masuk gagal terlalu banyak dari jaringan ini. Demi keamanan akun, '
        . 'coba lagi dalam ' . login_tunggu_teks($detik) . '.';
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
    return in_array((string) $role, ['super', 'pengawas'], true) ? 'Lintas sekolah/madrasah' : '-';
}

// ------------------------------------------------------------------ ekspor CSV

/**
 * Kirim CSV (pemisah ";" + BOM supaya Excel membaca UTF-8 dengan benar).
 *
 * @param array<int,string>            $header
 * @param array<int,array<int,scalar>> $rows
 */
function csv_kirim(string $namaBerkas, array $header, array $rows): void
{    header('Content-Type: text/csv; charset=UTF-8');
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

// ------------------------------------------------------------------ pengumuman sambutan (popup login)

/**
 * Daftar pengumuman sambutan POPUP "Selamat datang" (skema v16) — GLOBAL, diisi Super Admin.
 *
 * Dipakai dua tempat: halaman pengelolaan `pengumuman.php` (semua baris) dan popup pada
 * `page_top()` (hanya yang AKTIF, urut sesuai `urutan`).
 *
 * @return array<int,array<string,mixed>>
 */
function pengumuman_list(bool $aktifSaja = false, ?string $sasaran = null): array
{
    $w = [];
    $p = [];
    if ($aktifSaja) {
        $w[] = 'aktif = 1';
    }
    $sas = pengumuman_sasaran_sah($sasaran);
    if ($sas !== '') {
        $w[] = 'sasaran = ?';
        $p[] = $sas;
    }
    $sql = 'SELECT * FROM pengumuman'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY urutan, id';
    return all($sql, $p);
}

/**
 * Daftar SASARAN popup sambutan (kode => label).
 *
 * Permintaan pemilik: *"untuk pop up yang muncul di akun siswa diinput tersendiri oleh super admin"*
 * → isi popup dipisah menjadi dua: untuk **Pengguna Aplikasi** (Pengawas, Kepala Sekolah/Madrasah,
 * Admin Operator, Guru) dan untuk **Siswa**. Masing-masing punya daftarnya sendiri sehingga Super
 * Admin mengisi popup siswa secara terpisah dan tidak tercampur.
 */
function pengumuman_sasaran_list(): array
{
    return [
        'pengguna' => 'Pengguna Aplikasi',
        'siswa'    => 'Siswa',
    ];
}

/** Hanya menerima sasaran yang dikenal; '' berarti "semua sasaran" (dipakai daftar pengelolaan). */
function pengumuman_sasaran_sah(?string $sasaran): string
{
    $s = strtolower(trim((string) $sasaran));
    return array_key_exists($s, pengumuman_sasaran_list()) ? $s : '';
}

/** Sasaran yang berlaku untuk pengguna yang sedang masuk (siswa → 'siswa', peran lain → 'pengguna'). */
function pengumuman_sasaran_saya(): string
{
    return is_siswa() ? 'siswa' : 'pengguna';
}

/** Satu pengumuman berdasarkan id (null bila tidak ada). */
function pengumuman_by_id(int $id): ?array
{
    return $id > 0 ? one('SELECT * FROM pengumuman WHERE id = ?', [$id]) : null;
}

/**
 * Jumlah pengumuman aktif — untuk lencana menu sidebar.
 * Tanpa argumen: seluruh sasaran; dengan argumen: satu sasaran saja.
 */
function pengumuman_jumlah_aktif(?string $sasaran = null): int
{
    $sas = pengumuman_sasaran_sah($sasaran);
    return $sas === ''
        ? (int) val('SELECT COUNT(*) FROM pengumuman WHERE aktif = 1', [], 0)
        : (int) val('SELECT COUNT(*) FROM pengumuman WHERE aktif = 1 AND sasaran = ?', [$sas], 0);
}

/**
 * Nomor urut baru untuk pengumuman yang ditambahkan paling akhir (kelipatan 10, pola yang sama
 * dengan daftar aplikasi & tugas tambahan). Urutannya dihitung PER SASARAN supaya penomoran pada
 * tiap tab rapi sendiri-sendiri.
 */
function pengumuman_urutan_baru(?string $sasaran = null): int
{
    $sas = pengumuman_sasaran_sah($sasaran);
    return $sas === ''
        ? (int) val('SELECT COALESCE(MAX(urutan), 0) + 10 FROM pengumuman', [], 10)
        : (int) val('SELECT COALESCE(MAX(urutan), 0) + 10 FROM pengumuman WHERE sasaran = ?', [$sas], 10);
}

/**
 * Geser satu pengumuman naik/turun di dalam daftar SASARANNYA (menulis ulang urutan 10, 20, 30, …).
 * Dibatasi per sasaran supaya memindahkan pengumuman siswa tidak mengubah urutan pengumuman pengguna.
 */
function pengumuman_geser(int $id, string $arah): bool
{
    $ini = pengumuman_by_id($id);
    if (!$ini) {
        return false;
    }
    $baris = pengumuman_list(false, (string) $ini['sasaran']);
    $pos = -1;
    foreach ($baris as $i => $b) {
        if ((int) $b['id'] === $id) {
            $pos = $i;
            break;
        }
    }
    if ($pos < 0) {
        return false;
    }
    $tukar = $arah === 'naik' ? $pos - 1 : $pos + 1;
    if ($tukar < 0 || $tukar >= count($baris)) {
        return false;
    }
    $tmp = $baris[$pos];
    $baris[$pos] = $baris[$tukar];
    $baris[$tukar] = $tmp;
    db()->exec('BEGIN IMMEDIATE');
    try {
        $u = 0;
        foreach ($baris as $b) {
            $u += 10;
            q('UPDATE pengumuman SET urutan = ? WHERE id = ?', [$u, (int) $b['id']]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return true;
}

/**
 * Apakah popup sambutan harus ditampilkan untuk pengguna yang baru masuk?
 *
 * Permintaan pemilik: popup tampil "sekali setiap kali login" untuk pengguna aplikasi
 * (Pengawas, Kepala Sekolah/Madrasah, Admin Operator, dan Guru). Penanda `sambutan_tampil`
 * dipasang `login.php` saat masuk berhasil dan DIHAPUS setelah popup dicetak.
 * Super Admin sengaja TIDAK ikut (dialah pengisi isi popup) — lihat catatan di AGENTS.md.
 *
 * v1.71.5 — permintaan pemilik: *"Buat Popup selamat datang tetap dimunculkan di lembaga yang masa
 * aktifnya diatas 7 hari dan tidak dimunculkan ketika sisa 7 hari sampai habis."* → selama lembaga
 * berada di JENDELA PERINGATAN AKTIVASI (sisa ≤ `AKTIVASI_INGAT_HARI` hari, lihat
 * `aktivasi_peringatan()`), popup selamat datang TIDAK dicetak supaya popup peringatan aktivasi
 * (yang lebih penting) yang mengambil alih. Penandanya langsung dibersihkan, jadi popup selamat
 * datang tidak "menyusul" muncul di tengah sesi setelah masa aktifnya diperpanjang.
 *
 * Catatan peran: penjaga ini mengikuti SIAPA yang melihat popup peringatan. `aktivasi_peringatan()`
 * mengembalikan null untuk Super Admin & pengawas, sehingga pengawas (akun lintas sekolah yang tidak
 * terkena peringatan) TETAP menerima popup selamat datang seperti biasa.
 */
function sambutan_perlu_tampil(): bool
{
    if (empty($_SESSION['sambutan_tampil'])) {
        return false;
    }
    if (is_super() || current_user() === null) {
        return false;
    }
    // Jendela peringatan aktivasi → popup peringatan yang tampil, bukan popup selamat datang.
    if (aktivasi_peringatan() !== null) {
        sambutan_selesai();
        return false;
    }
    // Isi popup dipisah per sasaran: akun SISWA melihat popup yang diisi tersendiri oleh Super Admin
    // (tab "Siswa"), sedangkan peran lain melihat popup untuk Pengguna Aplikasi.
    return pengumuman_list(true, pengumuman_sasaran_saya()) !== [];
}

/** Bersihkan penanda popup (dipanggil setelah popup dicetak supaya tidak muncul dua kali). */
function sambutan_selesai(): void
{
    unset($_SESSION['sambutan_tampil']);
}

// ------------------------------------------------------------------ menu TUGAS TAMBAHAN (skema v18)

/**
 * Daftar menu TUGAS TAMBAHAN bawaan (GLOBAL, permintaan pemilik) — 10 nama.
 *
 * Dipakai DUA hal: (1) seed `tugas_menu` saat migrasi/pemasangan, dan (2) label kode → nama pada
 * halaman pengelolaan. `kode` tidak dapat diubah (dipakai pada URL `tugas.php?kode=…`), sedangkan
 * `nama` boleh diubah Super Admin.
 *
 * @return array<string,string> kode => nama
 */
function tugas_menu_bawaan(): array
{
    return [
        'kurikulum'     => 'Kurikulum',
        'kesiswaan'     => 'Kesiswaan',
        'sarpras'       => 'Sarana & Prasarana',
        'osim'          => 'Pembina OSIM/OSIS',
        'pramuka'       => 'Pembina Pramuka',
        'pmr'           => 'Pembina PMR',
        'wali_kelas'    => 'Wali Kelas',
        'tata_usaha'    => 'Tata Usaha',
        'kepala_perpus' => 'Kepala Perpus',
        'kepala_lab'    => 'Kepala Lab',
    ];
}

/** Keterangan isi setiap menu Tugas Tambahan (permintaan pemilik: "Masih dalam Pengembangan"). */
const TUGAS_ISI_PESAN = 'Masih dalam Pengembangan';

/**
 * true bila isi menu Tugas Tambahan ini DATANYA PER SEKOLAH sehingga memerlukan konteks sekolah.
 *
 * Dipakai `tugas.php` supaya menu yang datanya sudah diisi TIDAK dilempar diam-diam ke halaman
 * Kelola Sekolah ketika Super Admin belum memilih sekolah (dulu gejalanya: "menu yang isi menunya
 * sudah diinput tidak bisa ditampilkan" — menu kosong tetap tampil, yang berisi data tidak).
 * Menu papan-papan ini hanya memuat data satu sekolah/madrasah:
 *   - Sarana & Prasarana (`sarpras_barang`), Kesiswaan (`kesiswaan_program`),
 *     Pembina OSIM/OSIS, Pembina Pramuka, & Pembina PMR (`osim_pengurus`/`osim_program`),
 *     Ka. Perpustakaan
 *     (hasil impor e-PERPUS), Ka. Lab IPA/Komputer (`lab_barang`/`lab_program`),
 *     **Kurikulum** (`dokumen` — dokumen yang dikirim ke guru), dan
 *     **Wali Kelas** (`wali_*` — empat laporan per KELAS).
 * Menu lain (masih "Masih dalam Pengembangan") tidak menyentuh basis data sehingga tidak butuh konteks.
 */
function tugas_menu_perlu_sekolah(string $kode): bool
{
    $kode = trim($kode);
    if ($kode === '') {
        return false;
    }
    if (in_array($kode, [SARPRAS_KODE_MENU, KS_KODE_MENU, OSIM_KODE_MENU, PERPUS_KODE_MENU,
                         KURIKULUM_KODE_MENU, PRAMUKA_KODE_MENU, PMR_KODE_MENU, WALI_KODE_MENU], true)) {
        return true;
    }
    return in_array($kode, LAB_KODE_MENU, true);
}

/**
 * Daftar menu Tugas Tambahan (`$aktifSaja` = hanya yang diaktifkan Super Admin).
 *
 * @return array<int,array<string,mixed>>
 */
function tugas_menu_list(bool $aktifSaja = false): array
{
    return $aktifSaja
        ? all('SELECT * FROM tugas_menu WHERE aktif = 1 ORDER BY urutan, id')
        : all('SELECT * FROM tugas_menu ORDER BY urutan, id');
}

function tugas_menu_by_id(int $id): ?array
{
    return $id > 0 ? one('SELECT * FROM tugas_menu WHERE id = ?', [$id]) : null;
}

/** Satu menu Tugas Tambahan berdasarkan KODE (dipakai halaman `tugas.php?kode=…`). */
function tugas_menu_by_kode(string $kode): ?array
{
    $kode = trim($kode);
    return $kode !== '' ? one('SELECT * FROM tugas_menu WHERE kode = ?', [$kode]) : null;
}

/**
 * Kode yang dipakai MODUL bawaan aplikasi (menunya punya halaman sendiri).
 *
 * Menu Tugas Tambahan yang DITAMBAHKAN Super Admin tidak boleh memakai kode ini — kalau bentrok,
 * menu baru itu akan terhubung ke modul yang salah (mis. ikut memakai data barang Sarana &
 * Prasarana). Kode modul memakai garis bawah sedangkan kode buatan `tugas_menu_kode_dari_nama()`
 * memakai tanda hubung sehingga sebenarnya tidak mungkin bentrok; daftar ini tetap diperiksa
 * sebagai pengaman bila kode modul berubah di kemudian hari.
 *
 * @return array<int,string>
 */
function tugas_menu_kode_khusus(): array
{
    return array_merge(
        [SARPRAS_KODE_MENU, KS_KODE_MENU, PERPUS_KODE_MENU, KURIKULUM_KODE_MENU, WALI_KODE_MENU],
        OSIM_KODE_LIST,
        LAB_KODE_MENU,
        // Menu Tugas Tambahan yang isinya memakai modul daftar aplikasi (mis. "Bendahara Bos"):
        // kode-kode ini tidak boleh dipakai menu baru supaya tidak "mencuri" modulnya.
        daftar_apps_kode_tugas()
    );
}

/** true bila nama menu Tugas Tambahan sudah dipakai (tanpa peduli besar-kecil huruf). */
function tugas_menu_nama_terpakai(string $nama): bool
{
    $nama = trim($nama);
    if ($nama === '') {
        return false;
    }
    return (int) val('SELECT COUNT(*) FROM tugas_menu WHERE nama = ? COLLATE NOCASE', [$nama], 0) > 0;
}

/**
 * Buat KODE untuk menu Tugas Tambahan BARU dari nama yang diketik Super Admin.
 *
 * `kode` tidak dapat diubah setelah dibuat (dipakai pada URL `tugas.php?kode=…`), jadi dibuat
 * otomatis dan DIJAMIN belum dipakai: huruf kecil, hanya huruf/angka, pemisah tanda hubung —
 * mis. "Wakasek Humas" → `wakasek-humas`. Bila bentrok, ditambah angka (`wakasek-humas-2`, dst),
 * dan kode milik modul bawaan (lihat `tugas_menu_kode_khusus()`) selalu dihindari.
 */
function tugas_menu_kode_dari_nama(string $nama): string
{
    $dasar = strtolower(trim($nama));
    $dasar = (string) preg_replace('/[^a-z0-9]+/', '-', $dasar);
    $dasar = trim($dasar, '-');
    if ($dasar === '') {
        $dasar = 'tugas';
    }
    if (strlen($dasar) > 40) {
        $dasar = rtrim(substr($dasar, 0, 40), '-');
        if ($dasar === '') {
            $dasar = 'tugas';
        }
    }
    $kode = $dasar;
    $i = 2;
    while (tugas_menu_by_kode($kode) !== null || in_array($kode, tugas_menu_kode_khusus(), true)) {
        $kode = $dasar . '-' . $i;
        $i++;
        if ($i > 999) { // pengaman untuk nama yang benar-benar berulang
            $kode = $dasar . '-' . substr(md5(uniqid('tugas', true)), 0, 6);
            break;
        }
    }
    return $kode;
}

/** Geser satu menu naik/turun, lalu rapikan nomor urut (10, 20, 30, …). */
function tugas_menu_geser(int $id, string $arah): bool
{
    $baris = tugas_menu_list();
    $pos = -1;
    foreach ($baris as $i => $b) {
        if ((int) $b['id'] === $id) {
            $pos = $i;
            break;
        }
    }
    $tukar = $arah === 'naik' ? $pos - 1 : $pos + 1;
    if ($pos < 0 || $tukar < 0 || $tukar >= count($baris)) {
        return false;
    }
    $tmp = $baris[$pos];
    $baris[$pos] = $baris[$tukar];
    $baris[$tukar] = $tmp;
    db()->exec('BEGIN IMMEDIATE');
    try {
        $u = 0;
        foreach ($baris as $b) {
            $u += 10;
            q('UPDATE tugas_menu SET urutan = ? WHERE id = ?', [$u, (int) $b['id']]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return true;
}

/** ID menu Tugas Tambahan yang ditugaskan kepada seorang pengguna. */
function tugas_user_ids(int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    $keluar = [];
    foreach (all('SELECT tugas_id FROM user_tugas WHERE user_id = ?', [$userId]) as $r) {
        $keluar[] = (int) $r['tugas_id'];
    }
    return $keluar;
}

/** Jumlah pengguna yang ditugaskan pada sebuah menu (untuk lencana pada halaman pengelolaan). */
function tugas_menu_jumlah_pengguna(int $tugasId): int
{
    return (int) val('SELECT COUNT(*) FROM user_tugas WHERE tugas_id = ?', [$tugasId], 0);
}

/** Ganti seluruh penugasan seorang pengguna (pola sama dengan guru_mapel/guru_kelas). */
/**
 * Tetapkan/lepas KELAS PERWALIAN seorang guru (kolom `kelas.wali_id`) dari halaman Pengaturan
 * Tugas Tambahan.
 *
 * Permintaan pemilik (laporan: *"ketika admin operator menseting guru … dengan tugas tambahan
 * wali kelas tetapi menu wali kelas pada akun guru itu berbunyi 'Anda belum ditugaskan'"*): menu
 * "Wali Kelas" hanya berisi data **kelas perwalian**, jadi penugasan menunya WAJIB disertai
 * penetapan kelasnya — dan penetapan itu dilakukan di halaman yang sama supaya tidak ada langkah
 * yang tertinggal.
 *
 * Aturan:
 *   - hanya kelas sekolah yang sedang aktif yang diterima (anti-IDOR, id asing diabaikan);
 *   - kelas yang dicentang → `wali_id` = guru itu (boleh lebih dari satu kelas);
 *   - kelas yang DULU perwaliannya guru itu tetapi tidak dicentang lagi → dilepas (`wali_id` NULL);
 *   - kelas milik wali LAIN yang tidak dicentang → tidak disentuh (tidak boleh menghapus data guru
 *     lain tanpa sengaja); pengalihan perwalian dilaporkan pada pesan.
 *
 * @param array<int,scalar> $kelasIds kelas yang dicentang pengguna
 * @return array{ada:int,dibuat:int,dilepas:int,pesan:string}
 */
function kelas_perwalian_simpan(int $guruId, array $kelasIds): array
{
    $sid = sekolah_id();
    $guru = $guruId > 0 ? one('SELECT * FROM guru WHERE id = ? AND sekolah_id = ?', [$guruId, $sid]) : null;
    if (!$guru) {
        return ['ada' => 0, 'dibuat' => 0, 'dilepas' => 0, 'pesan' => ''];
    }
    // Daftar kelas sah sekolah ini (hanya yang aktif) + kelas yang sudah menjadi perwaliannya.
    $sah = [];
    foreach (kelas_list(true) as $k) {
        $sah[(int) $k['id']] = $k;
    }
    $diminta = [];
    foreach ($kelasIds as $id) {
        $id = (int) $id;
        if (isset($sah[$id])) {
            $diminta[$id] = true;
        }
    }
    $lama = [];
    foreach (guru_kelas_perwalian($guruId, $sid) as $k) {
        $lama[(int) $k['id']] = (string) $k['nama'];
    }

    $namaGuru = (string) $guru['nama'];
    $dibuat = [];
    $dialihkan = [];
    $dilepas = [];
    db()->exec('BEGIN IMMEDIATE');
    try {
        foreach (array_keys($diminta) as $kid) {
            $waliLama = (int) ($sah[$kid]['wali_id'] ?? 0);
            if ($waliLama === $guruId) {
                continue;
            }
            q('UPDATE kelas SET wali_id = ? WHERE id = ? AND sekolah_id = ?', [$guruId, $kid, $sid]);
            if ($waliLama > 0) {
                $dialihkan[] = (string) $sah[$kid]['nama'] . ' (dari '
                    . (string) ($sah[$kid]['wali_nama'] ?? 'wali sebelumnya') . ')';
            }
            $dibuat[] = (string) $sah[$kid]['nama'];
        }
        foreach ($lama as $kid => $nama) {
            if (!isset($diminta[$kid])) {
                q('UPDATE kelas SET wali_id = NULL WHERE id = ? AND sekolah_id = ?', [$kid, $sid]);
                $dilepas[] = $nama;
            }
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }

    $bagian = [];
    if ($dibuat) {
        $bagian[] = 'ditetapkan sebagai wali kelas ' . implode(', ', $dibuat);
    }
    if ($dilepas) {
        $bagian[] = 'perwalian dilepas pada ' . implode(', ', $dilepas);
    }
    if ($dialihkan) {
        $bagian[] = 'perwalian dipindahkan dari wali sebelumnya pada ' . implode(', ', $dialihkan);
    }
    if ($bagian) {
        $pesan = $namaGuru . ' ' . implode('; ', $bagian) . '.';
    } else {
        $pesan = $namaGuru . ': kelas perwalian tidak berubah ('
            . ($lama ? implode(', ', array_values($lama)) : 'belum ada kelas perwalian') . ').';
    }
    return ['ada' => count($lama), 'dibuat' => count($dibuat), 'dilepas' => count($dilepas), 'pesan' => $pesan];
}

function tugas_user_simpan(int $userId, array $tugasIds): int
{
    if ($userId <= 0) {
        return 0;
    }
    $sah = [];
    foreach (tugas_menu_list() as $t) {
        $sah[(int) $t['id']] = true;
    }
    $pilih = [];
    foreach ($tugasIds as $id) {
        $id = (int) $id;
        if (isset($sah[$id]) && !in_array($id, $pilih, true)) {
            $pilih[] = $id;
        }
    }
    $now = date('Y-m-d H:i:s');
    db()->exec('BEGIN IMMEDIATE');
    try {
        q('DELETE FROM user_tugas WHERE user_id = ?', [$userId]);
        $ins = db()->prepare('INSERT OR IGNORE INTO user_tugas (user_id, tugas_id, dibuat) VALUES (?,?,?)');
        foreach ($pilih as $id) {
            $ins->execute([$userId, $id, $now]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return count($pilih);
}

/**
 * Menu Tugas Tambahan yang TAMPIL di sidebar: seluruh menu yang sudah DIAKTIFKAN Super Admin.
 *
 * Permintaan pemilik: "Tugas Tambahan yang diaktifkan oleh Super Admin muncul sebagai menu baru di
 * semua pengguna (letaknya di atas data master)" — jadi daftar ini TIDAK lagi bergantung pada
 * penugasan. Penugasan hanya menentukan siapa yang boleh membuka ISINYA (lihat boleh_tugas_menu()).
 *
 * @return array<int,array<string,mixed>>
 */
/** Peran yang MELIHAT & dapat membuka SELURUH menu Tugas Tambahan yang diaktifkan. */
function tugas_menu_semua_peran(): bool
{
    return is_super() || is_kepala() || is_operator();
}

/**
 * true bila pengawas yang sedang masuk membina sekolah dalam konteks.
 *
 * Permintaan pemilik: "Laporan Tugas Tambahan tampilkan juga di akun pengawas bina untuk setiap
 * sekolahnya" — pengawas boleh MELIHAT (hanya lihat) laporan Tugas Tambahan tiap sekolah
 * binaannya, satu per satu lewat konteks binaan (sama seperti menu data lain).
 */
function tugas_menu_binaan_pengawas(?int $sekolahId = null): bool
{
    if (!is_pengawas()) {
        return false;
    }
    $sekolahId = $sekolahId ?? (int) (current_sekolah()['id'] ?? 0);
    $userId = (int) (current_user()['id'] ?? 0);
    return $sekolahId > 0 && pengawas_punya($userId, $sekolahId);
}

/**
 * Menu Tugas Tambahan yang TAMPIL di sidebar pengguna yang sedang masuk.
 *
 * Permintaan pemilik: "Menu tugas tambahan pada akun guru yang tampil hanya yang ditugaskan saja.
 * Muncul semua hanya di akun super admin, kepala madrasah dan operator." Jadi:
 *   - **Super Admin / Kepala Sekolah-Madrasah / Admin Operator** → SELURUH menu yang diaktifkan;
 *   - **peran lain (guru, pengawas)** → hanya menu yang DITUGASKAN kepadanya (dan sudah diaktifkan).
 *
 * @return array<int,array<string,mixed>>
 */
function tugas_menu_semua_aktif(): array
{
    // Menu Tugas Tambahan yang isinya DAFTAR APLIKASI ber-akses per aplikasi (mis. "Bendahara Bos")
    // disembunyikan bila sekolah/madrasah yang sedang dibuka tidak punya akses ke satu aplikasi pun
    // — permintaan pemilik: "Menu disembunyikan sama sekali (tidak tampil di sidebar)". Halaman
    // menunya tetap dapat dibuka langsung dan menampilkan keterangannya.
    if (tugas_menu_semua_peran()) {
        return tugas_menu_saring_tampil(tugas_menu_list(true));
    }
    // Pengawas: seluruh menu aktif TAMPIL sebagai laporan, tetapi hanya saat ia sudah memilih satu
    // sekolah binaan (di luar itu tidak ada sekolah yang dilaporkan).
    if (is_pengawas()) {
        return tugas_menu_binaan_pengawas() ? tugas_menu_saring_tampil(tugas_menu_list(true)) : [];
    }
    $userId = (int) (current_user()['id'] ?? 0);
    if ($userId <= 0) {
        return [];
    }
    $daftar = all('SELECT tm.* FROM tugas_menu tm
                   JOIN user_tugas ut ON ut.tugas_id = tm.id
                   WHERE tm.aktif = 1 AND ut.user_id = ?
                   ORDER BY tm.urutan, tm.id', [$userId]);
    // Menu "Wali Kelas" juga tampil bagi guru yang ditetapkan sebagai wali kelas (tanpa penugasan).
    // `guru_wali_ada()` ikut dipakai supaya guru yang kelas perwaliannya sedang dinonaktifkan tetap
    // melihat menunya (isinya menjelaskan sebabnya), sejalan dengan `boleh_tugas_menu()`.
    if (guru_jadi_wali($userId) || guru_wali_ada($userId)) {
        $wali = tugas_menu_by_kode(WALI_KODE_MENU);
        if ($wali && (int) $wali['aktif'] === 1) {
            $ada = false;
            foreach ($daftar as $d) {
                if ((int) $d['id'] === (int) $wali['id']) {
                    $ada = true;
                    break;
                }
            }
            if (!$ada) {
                $daftar[] = $wali;
                usort($daftar, static fn(array $a, array $b): int =>
                    [(int) $a['urutan'], (int) $a['id']] <=> [(int) $b['urutan'], (int) $b['id']]);
            }
        }
    }
    // Aturan yang sama dengan pengelola: menu daftar aplikasi yang aksesnya tertutup disembunyikan.
    return tugas_menu_saring_tampil($daftar);
}

/** Nama lama (dipakai internal/uji): daftar menu yang tampil = sama dengan tugas_menu_semua_aktif(). */
function tugas_menu_saya(): array
{
    return tugas_menu_semua_aktif();
}

/** true bila pengguna sudah DITUGASKAN pada sebuah menu (menentukan boleh-tidaknya membuka isi). */
function tugas_ditugaskan(int $tugasId, ?int $userId = null): bool
{
    if ($tugasId <= 0) {
        return false;
    }
    $userId = $userId ?? (int) (current_user()['id'] ?? 0);
    return $userId > 0 && one('SELECT id FROM user_tugas WHERE user_id = ? AND tugas_id = ?',
        [$userId, $tugasId]) !== null;
}

/**
 * true bila pengguna boleh membuka ISI sebuah menu Tugas Tambahan.
 *
 * Aturannya sejalan dengan tampilnya menu di sidebar (jangan dipisah, nanti ada menu yang terlihat
 * tetapi tidak bisa dibuka):
 *   - **Super Admin / Kepala Sekolah-Madrasah / Admin Operator** → boleh (mereka pengelola menu dan
 *     penentu penugasan);
 *   - **peran lain (guru, pengawas)** → hanya bila menu itu DITUGASKAN kepadanya (`user_tugas`,
 *     diatur Admin Operator / Kepala Sekolah-Madrasah pada halaman `tugas_pengguna.php`).
 */
function boleh_tugas_menu(?array $tugas, ?int $userId = null): bool
{
    if (!$tugas || (int) $tugas['aktif'] !== 1) {
        return false;
    }
    if (tugas_menu_semua_peran() || tugas_menu_binaan_pengawas()) {
        return true;
    }
    if (tugas_ditugaskan((int) $tugas['id'], $userId)) {
        return true;
    }
    // KHUSUS menu "Wali Kelas": guru yang DITETAPKAN sebagai wali kelas cukup dengan penetapan itu
    // (permintaan pemilik: "Guru yang jadi wali kelas itu saja (kelasnya di seting oleh operator)")
    // — tidak perlu penugasan terpisah lewat Pengaturan Tugas Tambahan.
    if ((string) ($tugas['kode'] ?? '') === WALI_KODE_MENU) {
        // Wali kelas yang kelasnya sedang dinonaktifkan juga dibukakan (menu berisi penjelasan),
        // supaya tidak muncul 403 tanpa keterangan — lihat guru_wali_ada().
        return guru_jadi_wali($userId) || guru_wali_ada($userId);
    }
    return false;
}

/**
 * true bila pengguna (guru) itu ditetapkan sebagai wali kelas pada minimal satu kelas aktif
 * sekolah yang sedang aktif. Dipakai aturan akses menu "Wali Kelas".
 */
function guru_jadi_wali(?int $userId = null): bool
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return false;
    }
    $uid = $userId ?? (int) (current_user()['id'] ?? 0);
    if ($uid <= 0) {
        return false;
    }
    return (int) val('SELECT COUNT(*) FROM kelas k JOIN users u ON u.guru_id = k.wali_id
                      WHERE u.id = ? AND k.sekolah_id = ? AND k.aktif = 1', [$uid, $sid], 0) > 0;
}

/**
 * true bila guru itu wali kelas pada kelas mana pun — TERMASUK kelas yang sedang DINONAKTIFKAN.
 *
 * Dipakai bersama `guru_jadi_wali()` untuk menentukan boleh-tidaknya membuka menu "Wali Kelas":
 * guru yang kelasnya dinonaktifkan tetap dibukakan menunya supaya ia mendapat **penjelasan**
 * ("kelas perwalian Anda sedang dinonaktifkan — aktifkan kembali di Data Kelas"), bukan 403 tanpa
 * keterangan.
 */
function guru_wali_ada(?int $userId = null): bool
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return false;
    }
    $uid = $userId ?? (int) (current_user()['id'] ?? 0);
    if ($uid <= 0) {
        return false;
    }
    return (int) val('SELECT COUNT(*) FROM kelas k JOIN users u ON u.guru_id = k.wali_id
                      WHERE u.id = ? AND k.sekolah_id = ?', [$uid, $sid], 0) > 0;
}

/**
 * Kelas PERWALIAN seorang guru (kolom `kelas.wali_id`) — sumber kebenaran tunggal "wali kelas".
 *
 * Dipakai bersama oleh menu Wali Kelas (`wali_kelas_daftar()`) dan halaman Pengaturan Tugas
 * Tambahan (`tugas_pengguna.php`) supaya keduanya tidak pernah berbeda pendapat. Satu guru boleh
 * menjadi wali lebih dari satu kelas.
 *
 * @return array<int,array<string,mixed>> baris `kelas` + `jml_siswa` + `wali_nama`
 */
function guru_kelas_perwalian(int $guruId, int $sekolahId = 0, bool $aktifSaja = true): array
{
    $sid = $sekolahId > 0 ? $sekolahId : sekolah_id();
    if ($guruId <= 0 || $sid <= 0) {
        return [];
    }
    return all('SELECT k.*, (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.aktif = 1) AS jml_siswa,
                       (SELECT nama FROM guru g WHERE g.id = k.wali_id) AS wali_nama
                FROM kelas k WHERE k.wali_id = ? AND k.sekolah_id = ?'
                . ($aktifSaja ? ' AND k.aktif = 1' : '') . '
                ORDER BY k.urutan, k.nama', [$guruId, $sid]);
}

/**
 * Boleh MENGUBAH (menambah/ubah/hapus/impor) ISI sebuah menu Tugas Tambahan.
 *
 * Permintaan pemilik: *"Pada akun kepala madrasah/sekolah menu tugas tambahan hanya bisa melihat dan
 * mencetak. Tidak bisa menambahkan data."* (idem untuk akun **operator**). Jadi pengubahan isi menu
 * Tugas Tambahan dilakukan oleh **guru yang ditugaskan** pada menu itu — bukan lagi kepala/operator:
 *   - **Guru** (yang ditugaskan pada menu itu; menu Wali Kelas cukup dengan penetapan wali kelas) →
 *     **boleh** mengubah;
 *   - **Kepala Sekolah/Madrasah & Admin Operator** → **hanya melihat + mencetak** (tidak boleh);
 *   - **Pengawas** → hanya melihat + mencetak (seperti sebelumnya);
 *   - **Super Admin dalam konteks sekolah** → boleh, karena dialah pemilik aplikasi yang membantu &
 *     memperbaiki data (tanpa itu tidak ada jalan masuk bila penugasannya keliru).
 *
 * Halaman yang boleh dibuka tetap ditentukan `boleh_tugas_menu()` (tidak berubah); fungsi ini hanya
 * menentukan boleh-tidaknya MENGUBAH isinya.
 */
function boleh_ubah_tugas_menu(): bool
{
    if (is_pengawas()) {
        return false;
    }
    if (is_super() && current_sekolah() !== null) {
        return true;
    }
    if (is_kepala() || is_operator()) {
        return false;
    }
    return is_guru();
}

/** Pesan 403 yang dipakai halaman isi menu Tugas Tambahan saat pengubahan ditolak. */
function tolak_ubah_tugas_menu(string $nama = 'Tugas Tambahan'): void
{
    if (is_kepala() || is_operator()) {
        tolak403('Akun Kepala Sekolah/Madrasah dan Admin Operator hanya dapat melihat serta mencetak '
            . $nama . '. Pengisian datanya dilakukan oleh guru yang ditugaskan pada menu ini.');
    }
    tolak403('Akun Pengawas Sekolah/Madrasah hanya dapat melihat dan mencetak ' . $nama . '.');
}

/**
 * Pemberitahuan "mode hanya lihat" pada halaman isi menu Tugas Tambahan — kalimatnya disesuaikan
 * dengan peran yang sedang masuk (kepala/operator/pengawas), karena semuanya kini hanya melihat.
 */
function pemberitahuan_hanya_lihat(string $apa): string
{
    $sebab = is_pengawas()
        ? 'Anda masuk sebagai <b>Pengawas Sekolah/Madrasah</b>'
        : (is_kepala()
            ? 'Akun <b>Kepala Sekolah/Madrasah</b> hanya dapat melihat dan mencetak'
            : 'Akun <b>Admin Operator</b> hanya dapat melihat dan mencetak');
    $binaan = is_pengawas() ? ' binaan ini' : '';
    return $sebab . ' — halaman ini menampilkan <b>' . $apa . '</b>' . $binaan
        . '. Penambahan/pengubahan data dilakukan oleh <b>guru yang ditugaskan</b> pada menu ini, '
        . 'sedangkan lembar <b>Cetak</b> tetap dapat dipakai.';
}

/** Pesan keadaan kosong pada halaman hanya-lihat (dipakai bila belum ada data). */
function pesan_kosong_hanya_lihat(string $apa): string
{
    if (is_pengawas()) {
        return 'Sekolah/madrasah binaan ini belum mengisi ' . $apa . '.';
    }
    return 'Belum ada data ' . $apa . ' pada sekolah/madrasah ini. Pengisiannya dilakukan guru yang '
        . 'ditugaskan pada menu ini.';
}

// ------------------------------------------------------------------ notulen rapat (Ka. Tata Usaha)

/**
 * Baris menu Tugas Tambahan yang dianggap **"Ka. Tata Usaha"** (kode ATAU nama).
 *
 * Dipakai hak akses Notulen Rapat: permintaan pemilik *"Hapus Menu Notulen rapat yang ada di akun
 * operator dan pindahkan ke akun guru yang diberi tugas tambahan sebagai Ka. Tata Usaha."*
 * Pencocokan lewat NAMA dinormalkan (bukan hanya kode) supaya tetap bekerja walau Super Admin
 * menamai menunya "Kepala Tata Usaha" atau menambahkannya dengan kode lain; kode baku `ka-tu`
 * tetap dikenali lebih dulu.
 */
function tugas_menu_ka_tu(): ?array
{
    $kodeBaku = [KA_TU_KODE_MENU, 'ka-tu', 'ka_tu', 'kepala-tu', 'kepala_tu'];
    foreach (tugas_menu_list() as $m) {
        if (in_array((string) $m['kode'], $kodeBaku, true)) {
            return $m;
        }
    }
    $namaBaku = ['ka tata usaha', 'kepala tata usaha', 'ka tu', 'kepala tu'];
    foreach (tugas_menu_list() as $m) {
        if (in_array(tugas_nama_kunci((string) $m['nama']), $namaBaku, true)) {
            return $m;
        }
    }
    return null;
}

/**
 * true bila pengguna yang sedang masuk adalah **guru yang ditugaskan sebagai Ka. Tata Usaha**
 * (punya baris `guru_tugas` ke menu Ka. Tata Usaha).
 */
function guru_jadi_ka_tu(?int $userId = null): bool
{
    $userId = $userId ?? (int) (current_user()['id'] ?? 0);
    if ($userId <= 0) {
        return false;
    }
    $u = one('SELECT role, guru_id FROM users WHERE id = ?', [$userId]);
    if (!$u || (string) $u['role'] !== 'guru' || (int) ($u['guru_id'] ?? 0) <= 0) {
        return false;
    }
    $menu = tugas_menu_ka_tu();
    if (!$menu) {
        return false;
    }
    return (int) val('SELECT COUNT(*) FROM guru_tugas WHERE guru_id = ? AND tugas_id = ?',
        [(int) $u['guru_id'], (int) $menu['id']], 0) > 0;
}

/**
 * Boleh MEMBUAT/MENGUBAH/MENGHAPUS Notulen Rapat.
 *
 * Permintaan pemilik (v1.21.0): *"Pada akun operator menu notulen rubah Kembali menjadi bisa
 * menambahkan seperti sebelumnya. Jadi yang bisa membuat notulen baru adalah akun operator dan guru
 * yang ditugaskan menjadi Ka. Tata Usaha."* → **Admin Operator BOLEH membuat lagi** (seperti sebelum
 * v1.19.0) bersama guru Ka. Tata Usaha; **Super Admin dalam konteks** juga boleh (pemilik aplikasi).
 * **Kepala Sekolah/Madrasah tetap hanya melihat + mencetak** (tidak disebut pemilik sebagai pembuat).
 */
function boleh_ubah_notulen(): bool
{
    if (is_operator()) {
        return true;
    }
    if (is_super() && current_sekolah() !== null) {
        return true;
    }
    return guru_jadi_ka_tu();
}

/** Boleh MELIHAT & MENCETAK Notulen Rapat: semua staf (kepala/operator/super) + guru Ka. Tata Usaha. */
function boleh_lihat_notulen(): bool
{
    return boleh_ubah_notulen() || is_kepala() || (is_super() && current_sekolah() !== null);
}

/**
 * Penjaga halaman daftar/lembar cetak Notulen Rapat.
 *
 * `require_sekolah()` dijalankan LEBIH DULU supaya Super Admin yang belum memilih sekolah dialihkan
 * dengan ramah ke Kelola Sekolah (pola lama), bukan menerima 403.
 */
function require_notulen(): array
{
    $u = require_login();
    require_sekolah();
    if (!boleh_lihat_notulen()) {
        tolak403('Notulen Rapat hanya untuk Super Admin (dalam konteks sekolah), Kepala Sekolah/Madrasah, '
            . 'Admin Operator, dan guru yang ditugaskan sebagai Ka. Tata Usaha.');
    }
    return $u;
}

/** Penjaga halaman pembuatan/pengubahan Notulen Rapat. */
function require_notulen_ubah(): array
{
    $u = require_login();
    require_sekolah();
    if (!boleh_ubah_notulen()) {
        tolak403('Pembuatan & pengubahan Notulen Rapat dilakukan oleh Admin Operator atau guru yang '
            . 'ditugaskan sebagai Ka. Tata Usaha (kepala sekolah/madrasah hanya melihat dan mencetak).');
    }
    return $u;
}

/** Penjaga halaman isi menu Tugas Tambahan (dipakai `tugas.php`). */
function require_tugas_menu(array $tugas): void
{
    require_login();
    if (!boleh_tugas_menu($tugas)) {
        tolak403('Menu Tugas Tambahan ini hanya dapat dibuka oleh pengguna yang ditugaskan oleh '
            . 'Admin Operator atau Kepala Sekolah/Madrasah (pengawas: sekolah binaannya).');
    }
}

/**
 * Penjaga halaman/handler yang MENGUBAH isi menu Tugas Tambahan — halaman impor, unggah, dsb.
 *
 * Permintaan pemilik: kepala sekolah/madrasah & admin operator hanya melihat + mencetak, jadi
 * mereka TIDAK boleh mengimpor/mengunggah; pengisian dilakukan guru yang ditugaskan
 * (+ Super Admin dalam konteks). Dipakai bersama `boleh_ubah_tugas_menu()`.
 */
function require_tugas_menu_ubah(array $tugas, string $nama = ''): void
{
    require_login();
    if (!boleh_tugas_menu($tugas)) {
        tolak403('Menu Tugas Tambahan ini hanya dapat dibuka oleh pengguna yang ditugaskan.');
    }
    if (!boleh_ubah_tugas_menu()) {
        tolak_ubah_tugas_menu($nama !== '' ? $nama : (string) ($tugas['nama'] ?? 'Tugas Tambahan'));
    }
}

/**
 * Pengguna pada sekolah yang sedang dikelola yang dapat DITUGASKAN menu Tugas Tambahan.
 * Dipakai halaman `tugas_pengguna.php` (Admin Operator & Kepala Sekolah/Madrasah).
 *
 * @return array<int,array<string,mixed>>
 */
function tugas_pengguna_sekolah(): array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return [];
    }
    // Akun SISWA dikecualikan: mereka bukan pengguna yang ditugasi menu Tugas Tambahan.
    return all('SELECT * FROM users WHERE sekolah_id = ? AND role NOT IN ("super", "siswa") ORDER BY role, nama', [$sid]);
}

/** Boleh mengatur penugasan Menu Tugas Tambahan: Admin Operator atau Kepala Sekolah/Madrasah. */
function boleh_tugas_pengguna(): bool
{
    return is_kepala() || is_operator();
}

/** Penjaga halaman penugasan Menu Tugas Tambahan. */
function require_tugas_pengguna(): array
{
    $u = require_login();
    require_sekolah();
    if (!boleh_tugas_pengguna()) {
        tolak403('Penugasan Menu Tugas Tambahan dilakukan oleh Admin Operator atau Kepala Sekolah/Madrasah.');
    }
    return $u;
}
