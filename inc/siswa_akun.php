<?php
/**
 * AKUN SISWA — modul bersama (v1.61.0 / skema v45).
 *
 * Permintaan pemilik (v1.61.0): *"Buat siswa bisa login ke aplikasi (login hanya memasukkan no NISN). Pada
 * halaman siswa hanya bisa melihat dan mencetak. Yang ada dalam halaman siswa adalah menampilkan
 * Nama dan NISN (tidak bisa merubah profil), Rekapan Daftar hadir Per Mata Pelajaran, Rekapan Daftar
 * Hadir di Kelas (yang diinput oleh wali kelas), Rekapan Nilai (Menampilkan Tabel Rekapan dari
 * seluruh Mata Pelajaran), Catatan Pelanggaran (yang diinput oleh setiap guru mata Pelajaran)."*
 *
 * Semua pembacaan data siswa ADA DI SINI supaya halaman (layar & lembar cetak) memakai perhitungan
 * yang SAMA dan tidak ada query yang tersebar. Setiap query WAJIB dibatasi `sekolah_id()` **dan**
 * `siswa_id` milik siswa yang sedang masuk (mereka hanya boleh melihat datanya sendiri).
 *
 * Modul ini dipanggil setelah `inc/init.php` (butuh helpers & fungsi basis data).
 *
 * v1.65.0 — KATA SANDI SISWA (permintaan pemilik): *"Pada login siswa tambahkan password. Password
 * adalah tanggal lahir siswa itu sendiri yang terdiri dari 8 angka. Misal 1 januari 2007 maka
 * passwordnya 01012007"*. Kata sandinya **dihitung dari `tanggal_lahir`** (fungsi
 * `siswa_sandi_lahir()`) — TIDAK disimpan di basis data. Lihat juga `login.php` (formulir masuk)
 * dan `login_blokir_detik_siswa()` (batas percobaan per NISN).
 */
declare(strict_types=1);

// ---------------------------------------------------------------------------------------------
// AKUN & MASUK
// ---------------------------------------------------------------------------------------------

/**
 * NISN yang sudah "dinormalkan": hanya angka (spasi, titik, dan tanda hubung diabaikan) —
 * supaya siswa boleh menuliskan NISN-nya dengan atau tanpa pemisah.
 */
function nisn_bersih(?string $nisn): string
{
    return (string) preg_replace('/[^0-9A-Za-z]+/', '', (string) $nisn);
}

// ---------------------------------------------------------------------------------------------
// KATA SANDI SISWA = TANGGAL LAHIR (8 angka, DDMMYYYY) — v1.65.0
// ---------------------------------------------------------------------------------------------
/**
 * KATA SANDI AKUN SISWA — permintaan pemilik: *"Pada login siswa tambahkan password. Password
 * adalah tanggal lahir siswa itu sendiri yang terdiri dari 8 angka. Misal 1 januari 2007 maka
 * passwordnya 01012007"*.
 *
 * Kata sandi TIDAK disimpan di basis data: nilainya DIHITUNG dari `siswa.tanggal_lahir` setiap kali
 * dipakai. Keuntungannya: memperbaiki tanggal lahir siswa otomatis memperbaiki kata sandinya, dan
 * tidak pernah ada kata sandi siswa yang tersimpan sebagai teks.
 *
 * Tanggal lahir disimpan sebagai ISO (`YYYY-MM-DD` — lihat `tgl_impor()`), sedangkan kata sandinya
 * **DDMMYYYY**: 1 Januari 2007 → `01012007`, 30 April 2011 → `30042011`.
 *
 * @return string 8 angka, atau '' bila tanggal lahirnya kosong/tidak sah (pemanggil menolak masuk).
 */
function siswa_sandi_lahir(?string $tanggalLahir): string
{
    $iso = trim((string) $tanggalLahir);
    if ($iso === '') {
        return '';
    }
    // Data lama/hasil impor bisa tersimpan dalam bentuk lain → dinormalkan lebih dulu.
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso) !== 1) {
        $normal = tgl_impor($iso);
        $iso = $normal !== null ? $normal : '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m) !== 1) {
        return '';
    }
    $th = (int) $m[1];
    $bl = (int) $m[2];
    $hr = (int) $m[3];
    if (!checkdate($bl, $hr, $th)) {
        return '';
    }
    return sprintf('%02d%02d%04d', $hr, $bl, $th);
}

/**
 * Kata sandi yang DIKETIK siswa: hanya angkanya yang dipakai, sehingga penulisan dengan pemisah
 * ("01-01-2007", "01.01.2007", "01 01 2007") tetap diterima selama angkanya tepat 8.
 */
function siswa_sandi_bersih(?string $sandi): string
{
    return (string) preg_replace('/[^0-9]+/', '', (string) $sandi);
}

/**
 * Pembanding kata sandi siswa dengan tanggal lahirnya.
 *
 * Perbandingan memakai `hash_equals()` (waktu tetap) dan hanya menerima **tepat 8 angka** — jadi
 * "01012007" benar, sedangkan "1012007" (tanpa nol di depan) atau "20070101" (urutan dibalik) ditolak.
 */
function siswa_sandi_cocok(array $siswa, string $sandi): bool
{
    $asli = siswa_sandi_lahir((string) ($siswa['tanggal_lahir'] ?? ''));
    if ($asli === '') {
        return false;
    }
    $ketik = siswa_sandi_bersih($sandi);
    if ($ketik === '' || strlen($ketik) !== 8) {
        return false;
    }
    return hash_equals($asli, $ketik);
}

/**
 * Cari siswa dari NISN yang dimasukkan saat masuk.
 *
 * Syaratnya (permintaan pemilik: "Semua siswa aktif & belum lulus di sekolah yang aktif — cukup NISN
 * terisi, langsung bisa masuk"): siswa AKTIF, belum LULUS, sekolahnya AKTIF & sudah DISETUJUI, dan
 * NISN-nya tidak kosong. Hasilnya berupa DAFTAR karena NISN yang sama bisa tercatat di lebih dari
 * satu sekolah (pemanggil menolak bila hasilnya lebih dari satu — lihat login.php).
 *
 * @return array<int,array<string,mixed>>
 */
function siswa_login_cari(string $nisn): array
{
    $kunci = nisn_bersih($nisn);
    if ($kunci === '') {
        return [];
    }
    try {
        // ⚠️ JEBAKAN SQLITE yang pernah menggigit di aplikasi ini: literal teks berkutip GANDA
        // (mis. "aktif") dibaca sebagai NAMA KOLOM bila kolomnya ada — dan pada query ini kolom
        // `aktif` ADA (di tabel siswa DAN kelas) sehingga muncul "ambiguous column name: aktif".
        // Karena itu seluruh nilai teks dikirim sebagai PARAMETER TERIKAT, bukan literal berkutip.
        $rows = all(
            'SELECT s.*, k.nama AS kelas_nama, sk.nama AS sekolah_nama
               FROM siswa s
               JOIN sekolah sk ON sk.id = s.sekolah_id
               LEFT JOIN kelas k ON k.id = s.kelas_id
              WHERE s.aktif = 1
                AND sk.aktif = 1
                AND sk.verifikasi = ?
                AND LENGTH(TRIM(COALESCE(s.nisn, ?))) >= 4
              ORDER BY s.nama',
            ['disetujui', '']
        );
    } catch (Throwable $e) {
        return [];
    }
    // Pembanding NISN dilakukan di PHP supaya penulisan dengan spasi/titik/tanda hubung
    // (mis. "9900 112 233") tetap dikenali sebagai NISN yang sama.
    return array_values(array_filter($rows, static function (array $r) use ($kunci): bool {
        $st = strtolower(trim((string) ($r['status'] ?? 'aktif')));
        return $st !== 'lulus' && nisn_bersih((string) ($r['nisn'] ?? '')) === $kunci;
    }));
}

/**
 * Pastikan ada akun `users` untuk siswa ini, lalu kembalikan id akunnya.
 *
 * Akun dibuat OTOMATIS saat siswa masuk pertama kali: peran `siswa`, terikat pada sekolah siswa, dan
 * `siswa_id` menunjuk barisnya. Kata sandi pada kolom `password_hash` DIACAK dan tidak dipakai untuk
 * masuk: halaman masuk siswa memakai **NISN + kata sandi tanggal lahir** yang dihitung ulang dari
 * data siswa (lihat `siswa_sandi_lahir()`), sehingga tidak ada kata sandi siswa yang tersimpan
 * sebagai teks dan akun tetap tidak dapat dipakai lewat tab "Pengguna". `username` dibuat dari NISN
 * agar unik se-aplikasi.
 */
function siswa_akun_tautan(array $siswa): int
{
    $siswaId = (int) ($siswa['id'] ?? 0);
    if ($siswaId <= 0) {
        return 0;
    }
    $ada = one('SELECT id FROM users WHERE siswa_id = ?', [$siswaId]);
    if ($ada) {
        // Pastikan akunnya aktif & tetap terhubung ke sekolah siswa (mis. setelah pindah kelas).
        q('UPDATE users SET aktif = 1, sekolah_id = ?, nama = ? WHERE id = ?',
            [(int) $siswa['sekolah_id'], (string) $siswa['nama'], (int) $ada['id']]);
        return (int) $ada['id'];
    }
    $dasar = 'siswa' . nisn_bersih((string) ($siswa['nisn'] ?? ''));
    if ($dasar === 'siswa') {
        $dasar = 'siswa' . $siswaId;
    }
    $username = $dasar;
    $n = 1;
    while (one('SELECT id FROM users WHERE username = ?', [$username])) {
        $n++;
        $username = $dasar . '-' . $n;
    }
    q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, siswa_id, aktif, dibuat)
       VALUES (?,?,?,?,?,?,1,?)', [
        (int) $siswa['sekolah_id'], $username,
        // Kata sandi acak: tidak ada jalan masuk lewat kata sandi untuk akun siswa.
        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        (string) $siswa['nama'], 'siswa', $siswaId, date('Y-m-d H:i:s'),
    ]);
    return (int) db()->lastInsertId();
}

/**
 * Hapus akun siswa yang datanya sudah tidak ada lagi (dipanggil saat data siswa dihapus) —
 * mencegah akun "yatim" yang masih bisa masuk. Sekaligus menonaktifkan akun siswa yang sudah
 * LULUS/dinonaktifkan supaya sesi lamanya tidak terus dipakai.
 */
function siswa_akun_bersihkan(): void
{
    try {
        // Akun yang datanya hilang → dihapus.
        // ⚠️ Nilai teks dikirim sebagai PARAMETER TERIKAT (bukan literal berkutip ganda) — lihat
        // catatan jebakan SQLite pada siswa_login_cari() di atas.
        q('DELETE FROM users WHERE role = ? AND (siswa_id IS NULL OR siswa_id NOT IN (SELECT id FROM siswa))',
            ['siswa']);
        // Akun yang siswanya sudah lulus/dinonaktifkan → dinonaktifkan (akun tetap ada untuk jejak).
        q('UPDATE users SET aktif = 0 WHERE role = ? AND aktif = 1 AND siswa_id IN
             (SELECT id FROM siswa WHERE aktif = 0 OR LOWER(COALESCE(status, ?)) = ?)',
            ['siswa', 'aktif', 'lulus']);
    } catch (Throwable $e) {
        // Kegagalan di sini tidak boleh menggagalkan penghapusan data siswa.
    }
}

/** Akun siswa milik pengguna yang sedang masuk (null bila bukan akun siswa / datanya hilang). */
function siswa_akun_user(): ?array
{
    $u = current_user();
    if (!$u || (string) $u['role'] !== 'siswa') {
        return null;
    }
    return siswa_lengkap_by_id((int) ($u['siswa_id'] ?? 0));
}

/**
 * Baris data SISWA yang sedang masuk (lengkap dengan nama kelas). Null bila akunnya bukan akun
 * siswa, datanya sudah dihapus, atau siswanya sudah lulus/dinonaktifkan.
 */
function siswa_saya(): ?array
{
    $s = siswa_akun_user();
    if (!$s) {
        return null;
    }
    if ((int) $s['aktif'] !== 1 || strtolower((string) ($s['status'] ?? 'aktif')) === 'lulus') {
        return null;
    }
    return $s;
}

/**
 * Baris siswa + nama kelas & nama sekolahnya.
 *
 * ⚠️ Nama `siswa_by_id()` sudah dipakai `inc/helpers.php` (baris siswa pada sekolah yang sedang
 * dikelola, tanpa nama kelas) — fungsi ini SENGAJA bernama lain supaya tidak bertabrakan.
 * Dipakai halaman siswa (termasuk saat masuk, ketika konteks sekolah belum tentu terpasang).
 */
function siswa_lengkap_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return one('SELECT s.*, k.nama AS kelas_nama, sk.nama AS sekolah_nama
                FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
                LEFT JOIN sekolah sk ON sk.id = s.sekolah_id
                WHERE s.id = ?', [$id]);
}

/**
 * Penjaga halaman siswa: harus akun siswa DAN datanya masih aktif.
 * Dipakai SELURUH halaman siswa (layar & lembar cetak).
 */
function require_siswa(): array
{
    $u = require_login();
    if (!is_siswa()) {
        tolak403('Halaman ini khusus untuk akun siswa.');
    }
    $s = siswa_saya();
    if (!$s) {
        unset($_SESSION['uid']);
        flash('Akun siswa ini sudah tidak aktif. Hubungi Admin Operator sekolah/madrasah Anda.', 'warn');
        redirect('login.php');
    }
    return $s;
}

// ---------------------------------------------------------------------------------------------
// RINGKASAN (dipakai dashboard siswa)
// ---------------------------------------------------------------------------------------------

/** Ringkasan singkat untuk dasbor siswa: kehadiran, nilai, dan pelanggaran. */
function siswa_ringkas(int $siswaId, ?int $taId = null, ?int $semId = null): array
{
    $ta = $taId ?: (int) (ta_aktif()['id'] ?? 0);
    $sem = $semId ?: (int) (semester_aktif()['id'] ?? 0);

    $absen = siswa_absen_ringkas(array_merge(
        siswa_absen_mapel($siswaId, $ta, $sem)['semua'] ?? [],
        siswa_absen_kelas($siswaId, $ta, $sem)['semua'] ?? []
    ));
    $nilai = siswa_nilai_rekap($siswaId, $ta, $sem);
    $kasus = siswa_pelanggaran($siswaId);

    $mapelNilai = 0;
    $jumlahAkhir = 0.0;
    foreach ($nilai as $n) {
        if ($n['nilai_akhir'] !== null) {
            $mapelNilai++;
            $jumlahAkhir += (float) $n['nilai_akhir'];
        }
    }
    return [
        'ta' => $ta,
        'sem' => $sem,
        'kehadiran' => $absen,
        'mapel_dinilai' => $mapelNilai,
        'rata_nilai' => $mapelNilai > 0 ? round($jumlahAkhir / $mapelNilai, 2) : null,
        'jumlah_mapel' => count($nilai),
        'pelanggaran' => count($kasus),
    ];
}

// ---------------------------------------------------------------------------------------------
// DAFTAR HADIR
// ---------------------------------------------------------------------------------------------

/** Ringkasan H/S/I/A dari daftar baris (masing-masing berisi `status`). */
function siswa_absen_ringkas(array $baris): array
{
    $r = ['h' => 0, 's' => 0, 'i' => 0, 'a' => 0, 'total' => 0, 'persen' => null];
    foreach ($baris as $b) {
        $st = strtoupper((string) ($b['status'] ?? 'H'));
        $r['total']++;
        if ($st === 'H') {
            $r['h']++;
        } elseif ($st === 'S') {
            $r['s']++;
        } elseif ($st === 'I') {
            $r['i']++;
        } else {
            $r['a']++;
        }
    }
    $r['persen'] = $r['total'] > 0 ? round($r['h'] / $r['total'] * 100, 2) : null;
    return $r;
}

/**
 * REKAPAN DAFTAR HADIR PER MATA PELAJARAN (yang diinput guru pada menu Daftar Hadir Siswa).
 *
 * @return array{per_mapel:array<int,array<string,mixed>>, semua:array<int,array<string,mixed>>}
 *   `per_mapel` = satu baris per mata pelajaran (berisi ringkasan + rincian tiap pertemuan),
 *   `semua` = seluruh baris pertemuan (untuk ringkasan gabungan).
 */
function siswa_absen_mapel(int $siswaId, int $taId = 0, int $semId = 0): array
{
    $sid = sekolah_id();
    if ($siswaId <= 0 || $sid <= 0 || $taId <= 0 || $semId <= 0) {
        return ['per_mapel' => [], 'semua' => []];
    }
    $rows = all('SELECT d.id, d.status, d.ket, a.tanggal, a.pertemuan, a.materi,
                        a.mapel_id, m.nama AS mapel_nama, g.nama AS guru_nama
                   FROM absensi_detail d
                   JOIN absensi a ON a.id = d.absensi_id
                   LEFT JOIN mapel m ON m.id = a.mapel_id
                   LEFT JOIN guru g ON g.id = a.guru_id
                  WHERE d.siswa_id = ? AND a.sekolah_id = ? AND a.ta_id = ? AND a.semester_id = ?
                  ORDER BY m.nama, a.tanggal, a.pertemuan, a.id',
        [$siswaId, $sid, $taId, $semId]);

    $per = [];
    foreach ($rows as $r) {
        $mid = (int) $r['mapel_id'];
        if (!isset($per[$mid])) {
            $per[$mid] = ['mapel_id' => $mid, 'mapel_nama' => (string) ($r['mapel_nama'] ?: '-'),
                          'guru_nama' => '', 'baris' => []];
        }
        if (trim((string) $per[$mid]['guru_nama']) === '' && trim((string) $r['guru_nama']) !== '') {
            $per[$mid]['guru_nama'] = (string) $r['guru_nama'];
        }
        $per[$mid]['baris'][] = $r;
    }
    foreach ($per as $mid => $d) {
        $per[$mid]['ringkas'] = siswa_absen_ringkas($d['baris']);
    }
    return ['per_mapel' => $per, 'semua' => $rows];
}

/**
 * REKAPAN DAFTAR HADIR DI KELAS — absensi HARIAN yang diinput WALI KELAS
 * (tabel `wali_absensi` + `wali_absensi_detail`, menu Tugas Tambahan "Wali Kelas").
 *
 * Baris yang belum punya tahun ajaran/semester (data lama) tetap ikut dihitung pada periode mana
 * pun — sama seperti aturan pada menu Jurnal & Rekap Pertemuan.
 */
function siswa_absen_kelas(int $siswaId, int $taId = 0, int $semId = 0): array
{
    $sid = sekolah_id();
    if ($siswaId <= 0 || $sid <= 0) {
        return ['baris' => [], 'semua' => [], 'ringkas' => siswa_absen_ringkas([])];
    }
    $w = ['d.siswa_id = ?', 'a.sekolah_id = ?'];
    $p = [$siswaId, $sid];
    if ($taId > 0) {
        $w[] = '(a.ta_id = ? OR a.ta_id IS NULL)';
        $p[] = $taId;
    }
    if ($semId > 0) {
        $w[] = '(a.semester_id = ? OR a.semester_id IS NULL)';
        $p[] = $semId;
    }
    $rows = all('SELECT a.id, a.tanggal, a.catatan, d.status, d.ket,
                        k.nama AS kelas_nama, g.nama AS wali_nama
                   FROM wali_absensi_detail d
                   JOIN wali_absensi a ON a.id = d.absensi_id
                   LEFT JOIN kelas k ON k.id = a.kelas_id
                   LEFT JOIN guru g ON g.id = a.guru_id
                  WHERE ' . implode(' AND ', $w) . '
                  ORDER BY a.tanggal DESC, a.id DESC', $p);
    return ['baris' => $rows, 'semua' => $rows, 'ringkas' => siswa_absen_ringkas($rows)];
}

// ---------------------------------------------------------------------------------------------
// NILAI
// ---------------------------------------------------------------------------------------------

/**
 * REKAPAN NILAI SELURUH MATA PELAJARAN untuk seorang siswa.
 *
 * Nilai akhir memakai rumus yang sama dengan halaman Penilaian (`nilai_akhir()`), dan pembobotan
 * (serta ambang predikat) diambil dari `nilai_konteks()` — jadi angka yang dilihat siswa SAMA dengan
 * yang dilihat guru mapel yang mengisinya.
 *
 * @return array<int,array<string,mixed>> satu baris per mata pelajaran
 */
function siswa_nilai_rekap(int $siswaId, int $taId = 0, int $semId = 0): array
{
    $sid = sekolah_id();
    if ($siswaId <= 0 || $sid <= 0 || $taId <= 0 || $semId <= 0) {
        return [];
    }
    $rows = all('SELECT n.kelas_id, n.mapel_id, n.jenis, n.kolom, n.nilai,
                        m.nama AS mapel_nama
                   FROM nilai n
                   LEFT JOIN mapel m ON m.id = n.mapel_id
                  WHERE n.sekolah_id = ? AND n.siswa_id = ? AND n.ta_id = ? AND n.semester_id = ?
                  ORDER BY m.nama, n.jenis, n.kolom',
        [$sid, $siswaId, $taId, $semId]);

    // KOLOM SUMATIF YANG SUDAH BERJALAN DI KELAS (permintaan pemilik): kolom yang **sudah ada
    // nilainya di kelas itu** — baik nilai siswa ini maupun nilai siswa lain — dihitung "sudah
    // berapa kali sumatif". Sedangkan kolom yang sudah ada nilai SISWA LAIN tetapi siswa ini belum
    // punya nilainya = sumatif yang BELUM DIIKUTI siswa tersebut.
    // Dipakai halaman siswa (`siswa_nilai.php`) & lembar cetaknya.
    $kelasSumatif = [];
    for ($i = 0; $i < count($rows); $i++) {
        $r = $rows[$i];
        if ((string) $r['jenis'] !== 'sumatif') {
            continue;
        }
        $kunci = (int) $r['kelas_id'] . '|' . (int) $r['mapel_id'];
        $kol = (int) $r['kolom'];
        if (!isset($kelasSumatif[$kunci])) {
            $kelasSumatif[$kunci] = ['semua' => [], 'siswa_lain' => [], 'milik_siswa' => []];
        }
        if ($r['nilai'] !== null && (string) $r['nilai'] !== '') {
            $kelasSumatif[$kunci]['semua'][$kol] = true;
            $kelasSumatif[$kunci]['milik_siswa'][$kol] = true;   // baris ini milik siswa yang dibuka
        }
    }

    // Kolom sumatif yang sudah ada nilai SISWA LAIN pada kelas+mapel yang sama.
    $kelasPasangan = [];
    foreach ($rows as $r) {
        if ((string) $r['jenis'] === 'sumatif') {
            $kelasPasangan[(int) $r['kelas_id'] . '|' . (int) $r['mapel_id']] = [(int) $r['kelas_id'], (int) $r['mapel_id']];
        }
    }
    foreach ($kelasPasangan as $kunci => [$kId, $mId]) {
        $lain = all('SELECT DISTINCT n.kolom FROM nilai n
                      WHERE n.sekolah_id = ? AND n.kelas_id = ? AND n.mapel_id = ?
                        AND n.ta_id = ? AND n.semester_id = ? AND n.jenis = ?
                        AND n.siswa_id <> ? AND n.nilai IS NOT NULL AND TRIM(n.nilai) <> ""',
            [$sid, $kId, $mId, $taId, $semId, 'sumatif', $siswaId]);
        foreach ($lain as $barisLain) {
            $kelasSumatif[$kunci]['siswa_lain'][(int) $barisLain['kolom']] = true;
        }
    }

    $kumpul = [];
    foreach ($rows as $r) {
        $mid = (int) $r['mapel_id'];
        if (!isset($kumpul[$mid])) {
            $kumpul[$mid] = ['mapel_id' => $mid, 'mapel_nama' => (string) ($r['mapel_nama'] ?: '-'),
                             'kelas_id' => (int) $r['kelas_id'], 'sumatif' => [], 'asas' => null];
        }
        $raw = $r['nilai'];
        $v = ($raw === null || $raw === '') ? null : (is_numeric($raw) ? (float) $raw : (string) $raw);
        // Kolom FORMATIF sengaja TIDAK dikumpulkan: pada halaman siswa (dan lembar cetaknya) kolom
        // itu tidak ditampilkan lagi (permintaan pemilik) — cukup nilai sumatif.
        if ($r['jenis'] === 'sumatif') {
            $kumpul[$mid]['sumatif'][(int) $r['kolom']] = $v;
        } elseif ($r['jenis'] === 'asas') {
            $kumpul[$mid]['asas'] = $v;
        }
    }

    $out = [];
    foreach ($kumpul as $mid => $d) {
        // Konteks nilai: siswa termasuk "pemirsa non-guru" sehingga yang dipakai adalah pengaturan
        // guru pengampu kelas+mapel itu (atau pengaturan sekolah bila guru belum mengatur).
        $konteks = nilai_konteks($mid, (int) $d['kelas_id']);
        $sumatif = [];
        foreach ($d['sumatif'] as $k => $v) {
            if (is_numeric($v)) {
                $sumatif[$k] = (float) $v;
            }
        }
        $asas = is_numeric($d['asas']) ? (float) $d['asas'] : null;
        $akhir = nilai_akhir($sumatif, $asas, (float) $konteks['bobot_asas']);
        // Keterangan sumatif: "sudah berapa kali" dihitung dari kolom yang sudah ada nilainya di
        // kelas itu (milik siswa ini ATAU siswa lain), sedangkan "belum diikuti" = kolom yang sudah
        // ada nilai siswa lain tetapi siswa ini belum punya nilainya.
        $kunciKelas = (int) $d['kelas_id'] . '|' . $mid;
        $semuaKolom = array_keys($kelasSumatif[$kunciKelas]['semua'] ?? []);
        $kolomLain = array_keys($kelasSumatif[$kunciKelas]['siswa_lain'] ?? []);
        sort($semuaKolom);
        sort($kolomLain);
        $belumDiikuti = [];
        foreach ($kolomLain as $k) {
            if (!isset($d['sumatif'][$k]) || $d['sumatif'][$k] === null || (string) $d['sumatif'][$k] === '') {
                $belumDiikuti[] = $k;
            }
        }
        $out[] = [
            'mapel_id'    => $mid,
            'mapel_nama'  => $d['mapel_nama'],
            'guru_nama'   => (string) ($konteks['guru_nama'] ?? ''),
            'rata_sumatif'  => $sumatif ? round(array_sum($sumatif) / count($sumatif), 2) : null,
            'asas'        => $asas,
            'nilai_akhir' => $akhir,
            'predikat'    => $akhir === null ? '-' : predikat_dari_k($konteks, $akhir),
            'bobot_asas'  => (float) $konteks['bobot_asas'],
            // Keterangan sumatif (permintaan pemilik).
            'jumlah_kolom_sumatif' => count($d['sumatif']),
            'sumatif_dilakukan'    => count($semuaKolom),
            'sumatif_berjalan'     => $semuaKolom,
            'sumatif_belum'        => $belumDiikuti,
        ];
    }
    return $out;
}

// ---------------------------------------------------------------------------------------------
// PELANGGARAN
// ---------------------------------------------------------------------------------------------

/**
 * CATATAN PELANGGARAN siswa (diinput setiap guru mata pelajaran pada menu Pelanggaran Siswa),
 * urut terbaru lebih dulu. Nama guru & mata pelajaran ikut ditampilkan.
 *
 * @return array<int,array<string,mixed>>
 */
function siswa_pelanggaran(int $siswaId): array
{
    $sid = sekolah_id();
    if ($siswaId <= 0 || $sid <= 0) {
        return [];
    }
    return all('SELECT ks.*, g.nama AS guru_nama, m.nama AS mapel_nama, k.nama AS kelas_nama
                FROM kasus ks
                LEFT JOIN guru g ON g.id = ks.guru_id
                LEFT JOIN mapel m ON m.id = ks.mapel_id
                LEFT JOIN kelas k ON k.id = ks.kelas_id
                WHERE ks.sekolah_id = ? AND ks.siswa_id = ?
                ORDER BY ks.tanggal DESC, ks.id DESC',
        [$sid, $siswaId]);
}

/** Label jenis & status pelanggaran (dipakai daftar pada layar maupun lembar cetak). */
function siswa_pelanggaran_label(string $jenis, string $status): array
{
    $jenisList = ['akademik' => 'Akademik', 'kesehatan' => 'Kesehatan', 'sosial' => 'Sosial',
                  'kedisiplinan' => 'Kedisiplinan', 'lainnya' => 'Lainnya'];
    $statusList = ['baru' => 'Baru', 'proses' => 'Dalam Proses', 'selesai' => 'Selesai'];
    return [$jenisList[$jenis] ?? ucfirst($jenis), $statusList[$status] ?? ucfirst($status)];
}

// ---------------------------------------------------------------------------------------------
// TAMPILAN BERSAMA HALAMAN SISWA
// ---------------------------------------------------------------------------------------------

/**
 * Pemilih periode (tahun ajaran & semester) untuk halaman siswa — dipakai halaman Daftar Hadir,
 * Nilai, dan lembar cetaknya supaya seluruhnya menampilkan periode yang SAMA.
 * Bila sekolah hanya punya satu tahun ajaran, pemilihnya tidak perlu ditampilkan.
 */
function siswa_form_periode(?array $ta, ?array $sem): void
{
    if (!$ta || count(ta_list()) <= 1) {
        return;
    }
    ?>
    <form method="get" class="flex">
      <select name="ta" data-auto aria-label="Tahun ajaran">
        <?php foreach (ta_list() as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === (int) $ta['id'] ? 'selected' : '' ?>>T.A. <?= e((string) $t['nama']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="sem" data-auto aria-label="Semester">
        <?php foreach (semester_list((int) $ta['id']) as $sm): ?>
          <option value="<?= (int) $sm['id'] ?>" <?= (int) $sm['id'] === (int) ($sem['id'] ?? 0) ? 'selected' : '' ?>>Semester <?= e((string) $sm['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php
}

/** Nama periode untuk ditampilkan pada kepala halaman & lembar cetak. */
function siswa_periode_teks(?array $ta, ?array $sem): string
{
    $a = $ta ? ta_nama($ta) : '-';
    $b = $sem ? sem_nama($sem) : '-';
    return 'Tahun Ajaran ' . $a . ' — Semester ' . $b;
}

/** Baris identitas siswa untuk kartu "Data Saya" (label => nilai) — dipakai layar & lembar cetak. */
function siswa_identitas(array $sw): array
{
    $jk = strtoupper((string) ($sw['jenis_kelamin'] ?? '')) === 'P' ? 'Perempuan'
        : (strtoupper((string) ($sw['jenis_kelamin'] ?? '')) === 'L' ? 'Laki-laki' : '-');
    $foto = siswa_foto($sw);
    $status = strtolower((string) ($sw['status'] ?? 'aktif'));
    return [
        'Nama Lengkap'                  => (string) $sw['nama'],
        'NISN'                          => (string) ($sw['nisn'] !== '' ? $sw['nisn'] : '-'),
        'NIS'                           => (string) ($sw['nis'] !== '' ? $sw['nis'] : '-'),
        (istilah_sekolah('Sekolah/Madrasah')) => (string) ($sw['sekolah_nama'] ?? nama_sekolah()),
        'Kelas'                         => (string) ($sw['kelas_nama'] !== null ? $sw['kelas_nama'] : '-'),
        'Jenis Kelamin'                 => $jk,
        'Tempat, Tanggal Lahir'         => trim((string) ($sw['tempat_lahir'] ?? '')) !== ''
            ? (string) $sw['tempat_lahir'] . ', ' . tanggal_id((string) $sw['tanggal_lahir'])
            : tanggal_id((string) $sw['tanggal_lahir']),
        'Nama Ayah'                     => (string) ($sw['nama_ayah'] !== '' ? $sw['nama_ayah'] : '-'),
        'Nama Ibu'                      => (string) ($sw['nama_ibu'] !== '' ? $sw['nama_ibu'] : '-'),
        'Wali'                          => (string) ($sw['nama_wali'] !== '' ? $sw['nama_wali'] : '-'),
        'Telepon / HP'                  => (string) ($sw['telepon'] !== '' ? $sw['telepon'] : '-'),
        'Alamat'                        => (string) ($sw['alamat'] !== '' ? $sw['alamat'] : '-'),
        'Status'                        => $status === 'lulus' ? 'Lulus' : ((int) $sw['aktif'] === 1 ? 'Aktif' : 'Nonaktif'),
        'Foto'                          => $foto,
    ];
}
