<?php
/**
 * MODUL KESISWAAN — LAPORAN PROGRAM KERJA KESISWAAN (menu Tugas Tambahan berkode `kesiswaan`).
 *
 * Permintaan pemilik: "Pada Menu kesiswaan isi dengan 'Laporan Program Kerja Kesiswaan' dengan menu
 * inputan: Jenis Kegiatan, Bentuk Kegiatan, Tujuan, Target, Tahun Ajaran, Waktu Pelaksanaan
 * (Pilihan centang dari Bulan Juli ke Juni), Sumber Dana, Keterangan. Tambahkan fitur impor dan
 * ekspor dan juga cetak."
 *
 * Aturan yang dipakai (mengikuti modul Sarana & Prasarana, `inc/sarpras.php`):
 *  - data PROGRAM KERJA **PER SEKOLAH** (`kesiswaan_program.sekolah_id`); setiap query dibatasi
 *    `sekolah_id()` (anti-IDOR);
 *  - **Super Admin, Kepala Sekolah/Madrasah, dan Admin Operator langsung LAPORAN**;
 *    pengguna lain (mis. guru yang ditugaskan) langsung ke tabel penginputan;
 *  - semua pengguna yang boleh membuka menu ini **boleh input/ubah/hapus**;
 *  - **Pengawas = HANYA LIHAT & CETAK** (tab laporan saja, tanpa pengubahan);
 *  - **Waktu Pelaksanaan = centang BULAN** Juli s.d. Juni (`KS_BULAN`), boleh lebih dari satu.
 */
declare(strict_types=1);

// ------------------------------------------------------------------ bantuan umum

/**
 * Pangkas teks dengan aman: memakai `mb_substr()` bila tersedia (runtime CLI sandbox bisa tidak
 * memuat mbstring — lihat catatan di AGENTS.md), kalau tidak memakai `substr()`.
 */
function ks_pangkas(string $teks, int $maks): string
{
    if ($maks <= 0) {
        return '';
    }
    if (function_exists('mb_substr')) {
        return (string) mb_substr($teks, 0, $maks);
    }
    return substr($teks, 0, $maks);
}

// ------------------------------------------------------------------ bantuan bulan & normalisasi

/**
 * Normalisasi satu penulisan bulan menjadi nama bulan baku (`KS_BULAN`), atau '' bila tidak dikenal.
 *
 * Dipakai isian/centang, impor (nama bulan boleh disingkat: "Jul", "agu", "des"), dan angka
 * ("7" = Juli karena urutan tahun ajaran dimulai Juli, "1" = Januari).
 */
function ks_bulan_baku(string $nilai): string
{
    $v = trim($nilai);
    if ($v === '') {
        return '';
    }
    $kunci = strtolower(preg_replace('/[^A-Za-z0-9]+/u', '', $v) ?? '');
    // Angka bulan (1-12) memakai penomoran KALENDER: 7 = Juli, 1 = Januari.
    if (preg_match('/^[0-9]{1,2}$/', $v) === 1) {
        $n = (int) $v;
        $kalender = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                     'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return ($n >= 1 && $n <= 12) ? $kalender[$n - 1] : '';
    }
    foreach (KS_BULAN as $b) {
        if ($kunci !== '' && strpos(strtolower($b), $kunci) === 0 && strlen($kunci) >= 3) {
            return $b;
        }
    }
    return '';
}

/**
 * Baca daftar bulan dari teks bebas (hasil impor) → daftar bulan baku unik, urut `KS_BULAN`.
 *
 * Pemisah yang diterima: koma, titik koma, pipe (`|`), atau garis miring.
 *
 * @return array<int,string>
 */
function ks_waktu_daftar(string $teks): array
{
    $teks = trim($teks);
    if ($teks === '') {
        return [];
    }
    $potong = preg_split('/[,;|\/]+/u', $teks) ?: [];
    $ada = [];
    foreach ($potong as $p) {
        $b = ks_bulan_baku($p);
        if ($b !== '') {
            $ada[$b] = true;
        }
    }
    // Bila pemisahnya tidak ada (mis. "Juli Agustus"), coba kenali seluruh teks sebagai rangkaian bulan.
    if (!$ada) {
        foreach (KS_BULAN as $b) {
            if (stripos($teks, $b) !== false) {
                $ada[$b] = true;
            }
        }
    }
    $keluar = [];
    foreach (KS_BULAN as $b) {
        if (isset($ada[$b])) {
            $keluar[] = $b;
        }
    }
    return $keluar;
}

/** Simpan daftar bulan sebagai teks (dipisah koma) — bentuk yang disimpan ke basis data. */
function ks_waktu_simpan(array $bulan): string
{
    $baku = [];
    foreach ($bulan as $b) {
        $x = ks_bulan_baku((string) $b);
        if ($x !== '' && !in_array($x, $baku, true)) {
            $baku[] = $x;
        }
    }
    // Urutkan mengikuti urutan tahun ajaran (Juli..Juni).
    $keluar = [];
    foreach (KS_BULAN as $b) {
        if (in_array($b, $baku, true)) {
            $keluar[] = $b;
        }
    }
    return implode(', ', $keluar);
}

/** Label waktu pelaksanaan untuk tampilan ('' → 'belum diisi'). */
function ks_waktu_label(?string $waktu): string
{
    $d = ks_waktu_daftar((string) $waktu);
    return $d ? implode(', ', $d) : '';
}

/** Daftar Tahun Ajaran sekolah ini (untuk pilihan) — diambil dari master Tahun Ajaran. */
function ks_ta_pilihan(): array
{
    return ta_list(false);
}

/**
 * Tahun ajaran yang dipakai sebagai pilihan awal pada formulir / impor: yang AKTIF, atau yang
 * terbaru (daftar `ta_list()` sudah diurut nama menurun).
 */
function ks_ta_awal(): string
{
    $ta = ta_aktif();
    if ($ta) {
        return (string) $ta['nama'];
    }
    $daftar = ta_list();
    return $daftar ? (string) $daftar[0]['nama'] : '';
}

// ------------------------------------------------------------------ data

/**
 * Daftar program kerja sekolah ini.
 *
 * @param array<string,string|int> $f filter: cari, jenis, sumber_dana, ta, bulan
 * @return array<int,array<string,mixed>>
 */
function ks_list(array $f = []): array
{
    $w = ['sekolah_id = ?'];
    $p = [sekolah_id()];
    $cari = trim((string) ($f['cari'] ?? ''));
    if ($cari !== '') {
        $w[] = '(jenis LIKE ? OR bentuk LIKE ? OR tujuan LIKE ? OR target LIKE ? OR keterangan LIKE ?)';
        $like = '%' . $cari . '%';
        array_push($p, $like, $like, $like, $like, $like);
    }
    $jenis = trim((string) ($f['jenis'] ?? ''));
    if ($jenis !== '') {
        $w[] = 'jenis = ?';
        $p[] = $jenis;
    }
    $sumber = trim((string) ($f['sumber_dana'] ?? ''));
    if ($sumber !== '') {
        $w[] = 'sumber_dana LIKE ?';
        $p[] = '%' . $sumber . '%';
    }
    $ta = trim((string) ($f['ta'] ?? ''));
    if ($ta !== '') {
        $w[] = 'ta = ?';
        $p[] = $ta;
    }
    $bulan = ks_bulan_baku((string) ($f['bulan'] ?? ''));
    if ($bulan !== '') {
        $w[] = 'waktu LIKE ?';
        $p[] = '%' . $bulan . '%';
    }
    return all('SELECT * FROM kesiswaan_program WHERE ' . implode(' AND ', $w) . ' ORDER BY urutan, id', $p);
}

function ks_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return one('SELECT * FROM kesiswaan_program WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
}

/** Ringkasan laporan: jumlah kegiatan, jumlah jenis kegiatan, dan sebaran bulan pelaksanaan. */
function ks_ringkas(array $baris): array
{
    $jenis = [];
    $bulan = [];
    foreach (KS_BULAN as $b) {
        $bulan[$b] = 0;
    }
    foreach ($baris as $r) {
        $j = trim((string) ($r['jenis'] ?? ''));
        if ($j !== '') {
            $jenis[$j] = true;
        }
        foreach (ks_waktu_daftar((string) ($r['waktu'] ?? '')) as $b) {
            $bulan[$b]++;
        }
    }
    $tanpaWaktu = 0;
    foreach ($baris as $r) {
        if (ks_waktu_daftar((string) ($r['waktu'] ?? '')) === []) {
            $tanpaWaktu++;
        }
    }
    return [
        'kegiatan'    => count($baris),
        'jenis'       => count($jenis),
        'bulan'       => $bulan,
        'tanpa_waktu' => $tanpaWaktu,
    ];
}

/** Rekap sebuah kolom (`jenis`/`sumber_dana`/`ta`) → [nilai => jumlah kegiatan]. */
function ks_rekap(array $baris, string $kolom): array
{
    $keluar = [];
    foreach ($baris as $r) {
        $nilai = trim((string) ($r[$kolom] ?? ''));
        if ($nilai === '') {
            $nilai = '(belum diisi)';
        }
        $keluar[$nilai] = ($keluar[$nilai] ?? 0) + 1;
    }
    arsort($keluar);
    return $keluar;
}

/** Daftar pilihan "jenis kegiatan" yang sudah dipakai sekolah ini (untuk saran & saringan). */
function ks_jenis_terpakai(): array
{
    $keluar = [];
    foreach (all('SELECT DISTINCT jenis FROM kesiswaan_program
                  WHERE sekolah_id = ? AND IFNULL(jenis, "") <> "" ORDER BY jenis', [sekolah_id()]) as $r) {
        $keluar[] = (string) $r['jenis'];
    }
    return $keluar;
}

/** URL filter aktif (dipakai agar ekspor/cetak/pratinjau mengikuti saringan yang sedang dipakai). */
function ks_query(array $f): string
{
    $q = [];
    foreach (['cari', 'jenis', 'sumber_dana', 'ta', 'bulan'] as $k) {
        $v = trim((string) ($f[$k] ?? ''));
        if ($v !== '') {
            $q[$k] = $v;
        }
    }
    return http_build_query($q);
}

/** Label ringkas saringan aktif (untuk identitas lembar cetak & halaman ekspor). */
function ks_label_filter(array $f): array
{
    $label = [];
    if (trim((string) ($f['jenis'] ?? '')) !== '') {
        $label[] = 'Jenis Kegiatan: ' . $f['jenis'];
    }
    if (trim((string) ($f['ta'] ?? '')) !== '') {
        $label[] = 'Tahun Ajaran: ' . $f['ta'];
    }
    if (trim((string) ($f['bulan'] ?? '')) !== '') {
        $label[] = 'Bulan: ' . $f['bulan'];
    }
    if (trim((string) ($f['sumber_dana'] ?? '')) !== '') {
        $label[] = 'Sumber Dana: ' . $f['sumber_dana'];
    }
    if (trim((string) ($f['cari'] ?? '')) !== '') {
        $label[] = 'Kata kunci: ' . $f['cari'];
    }
    return $label;
}

// ================================================================== REKAP PELANGGARAN SISWA
// Permintaan pemilik: *"Pada menu kesiswaan tambahkan fitur rekap pelanggaran dari seluruh guru
// mata pelajaran. Jadi guru yang dapat tugas tambahan kesiswaan bisa melihat, memberikan tindak
// lanjut, dan mencetak seluruh catatan pelanggaran dari setiap guru mata pelajaran."*
//
// Sumber data = tabel `kasus` (menu Pelanggaran Siswa) milik sekolah ini — SELURUH catatan dari
// guru mata pelajaran mana pun (bukan hanya yang dibuat guru Kesiswaan). Tindak lanjutnya disimpan
// pada baris `kasus` itu sendiri (kolom `tindak_lanjut*`, skema v51) sehingga hasilnya langsung
// terlihat juga di menu Pelanggaran Siswa dan lembar cetaknya.

/**
 * Pilihan JENIS TINDAK LANJUT yang boleh diberikan guru Tugas Tambahan Kesiswaan.
 * Kunci kosong = belum ditindaklanjuti.
 */
function ks_tl_list(): array
{
    return [
        'dibina'     => 'Dibina / Dinasihati',
        'tertulis'   => 'Peringatan Tertulis',
        'orang_tua'  => 'Panggilan Orang Tua/Wali',
        'sanksi'     => 'Sanksi Sekolah',
        'selesai'    => 'Selesai Ditangani',
    ];
}

/** Label jenis tindak lanjut ('' bila belum ditindaklanjuti / kode tak dikenal). */
function ks_tl_label(?string $kode): string
{
    $kode = trim((string) $kode);
    return $kode === '' ? '' : (string) (ks_tl_list()[$kode] ?? '');
}

/** Benar bila catatan pelanggaran itu SUDAH ditindaklanjuti. */
function ks_tl_ada(array $baris): bool
{
    return ks_tl_label((string) ($baris['tindak_lanjut_jenis'] ?? '')) !== '';
}

/**
 * Saring daftar pelanggaran sekolah ini.
 *
 * @param array<string,string|int> $f cari, kelas, mapel, guru, jenis, status,
 *                                      tl ('sudah'|'belum'), dari, sampai
 * @return array<int,array<string,mixed>> baris `kasus` + nama siswa/kelas/mapel/guru
 */
function ks_pelanggaran_list(array $f = [], bool $lamaDulu = false): array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return [];
    }
    $w = ['ks.sekolah_id = ?'];
    $p = [$sid];

    $cari = trim((string) ($f['cari'] ?? ''));
    if ($cari !== '') {
        $w[] = '(s.nama LIKE ? OR s.nis LIKE ? OR ks.judul LIKE ? OR ks.deskripsi LIKE ?
                 OR ks.tindak_lanjut LIKE ? OR g.nama LIKE ? OR m.nama LIKE ?)';
        $like = '%' . $cari . '%';
        array_push($p, $like, $like, $like, $like, $like, $like, $like);
    }
    $kelas = (int) ($f['kelas'] ?? 0);
    if ($kelas > 0 && kelas_by_id($kelas)) {
        $w[] = 'ks.kelas_id = ?';
        $p[] = $kelas;
    }
    $mapel = (int) ($f['mapel'] ?? 0);
    if ($mapel > 0 && mapel_by_id($mapel)) {
        $w[] = 'ks.mapel_id = ?';
        $p[] = $mapel;
    }
    $guru = (int) ($f['guru'] ?? 0);
    if ($guru > 0) {
        $w[] = 'ks.guru_id = ?';
        $p[] = $guru;
    }
    $jenis = trim((string) ($f['jenis'] ?? ''));
    if ($jenis !== '' && array_key_exists($jenis, kasus_jenis())) {
        $w[] = 'ks.jenis = ?';
        $p[] = $jenis;
    }
    $status = trim((string) ($f['status'] ?? ''));
    if ($status !== '' && array_key_exists($status, kasus_status())) {
        $w[] = 'ks.status = ?';
        $p[] = $status;
    }
    $tl = trim((string) ($f['tl'] ?? ''));
    if ($tl === 'sudah') {
        $w[] = "IFNULL(ks.tindak_lanjut_jenis, '') <> ''";
    } elseif ($tl === 'belum') {
        $w[] = "IFNULL(ks.tindak_lanjut_jenis, '') = ''";
    }
    $dari = tgl_ok($f['dari'] ?? null);
    $sampai = tgl_ok($f['sampai'] ?? null);
    if ($dari !== null && $sampai !== null && $dari > $sampai) {
        [$dari, $sampai] = [$sampai, $dari];
    }
    if ($dari !== null) {
        $w[] = 'ks.tanggal >= ?';
        $p[] = $dari;
    }
    if ($sampai !== null) {
        $w[] = 'ks.tanggal <= ?';
        $p[] = $sampai;
    }

    $urut = $lamaDulu ? 'ks.tanggal, ks.id' : 'ks.tanggal DESC, ks.id DESC';
    return all('SELECT ks.*, s.nama AS siswa_nama, s.nis, s.nisn,
                       k.nama AS kelas_nama, m.nama AS mapel_nama, g.nama AS guru_nama
                FROM kasus ks
                LEFT JOIN siswa s ON s.id = ks.siswa_id
                LEFT JOIN kelas k ON k.id = ks.kelas_id
                LEFT JOIN mapel m ON m.id = ks.mapel_id
                LEFT JOIN guru g ON g.id = ks.guru_id
                WHERE ' . implode(' AND ', $w) . '
                ORDER BY ' . $urut, $p);
}

/** Ringkasan rekap pelanggaran (dihitung dari baris yang SEDANG ditampilkan). */
function ks_pelanggaran_ringkas(array $baris): array
{
    $siswa = [];
    $guru = [];
    $mapel = [];
    $sudah = 0;
    foreach ($baris as $r) {
        $sid = (int) ($r['siswa_id'] ?? 0);
        if ($sid > 0) {
            $siswa[$sid] = true;
        }
        $gid = (int) ($r['guru_id'] ?? 0);
        if ($gid > 0) {
            $guru[$gid] = true;
        }
        $mid = (int) ($r['mapel_id'] ?? 0);
        if ($mid > 0) {
            $mapel[$mid] = true;
        }
        if (ks_tl_ada($r)) {
            $sudah++;
        }
    }
    return [
        'total'   => count($baris),
        'siswa'   => count($siswa),
        'guru'    => count($guru),
        'mapel'   => count($mapel),
        'sudah'   => $sudah,
        'belum'   => count($baris) - $sudah,
    ];
}

/** Satu catatan pelanggaran milik sekolah ini (anti-IDOR). */
function ks_pelanggaran_by_id(int $id): ?array
{
    if ($id <= 0 || sekolah_id() <= 0) {
        return null;
    }
    return one('SELECT * FROM kasus WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
}

/**
 * Menyimpan TINDAK LANJUT satu catatan pelanggaran.
 *
 * `$jenis` kosong = membatalkan tindak lanjut (catatan, penindak, dan waktunya dikosongkan).
 * Mengembalikan ['ok'=>bool,'pesan'=>string].
 */
function ks_tl_simpan(int $id, string $jenis, string $catatan): array
{
    $k = ks_pelanggaran_by_id($id);
    if (!$k) {
        return ['ok' => false, 'pesan' => 'Catatan pelanggaran tidak ditemukan.'];
    }
    $daftar = ks_tl_list();
    $jenis = trim($jenis);
    if ($jenis === '') {
        q('UPDATE kasus SET tindak_lanjut = NULL, tindak_lanjut_jenis = NULL,
             tindak_lanjut_oleh = NULL, tindak_lanjut_pada = NULL WHERE id = ? AND sekolah_id = ?',
            [$id, sekolah_id()]);
        return ['ok' => true, 'pesan' => 'Tindak lanjut dibatalkan — catatan kembali "belum ditindaklanjuti".'];
    }
    if (!array_key_exists($jenis, $daftar)) {
        return ['ok' => false, 'pesan' => 'Jenis tindak lanjut tidak dikenal.'];
    }
    $catatan = ks_pangkas(trim($catatan), KS_TL_CATATAN_MAKS);
    $u = current_user();
    q('UPDATE kasus SET tindak_lanjut = ?, tindak_lanjut_jenis = ?, tindak_lanjut_oleh = ?, tindak_lanjut_pada = ?
       WHERE id = ? AND sekolah_id = ?',
        [$catatan !== '' ? $catatan : null, $jenis, (string) ($u['nama'] ?? ''), date('Y-m-d H:i:s'),
         $id, sekolah_id()]);
    return ['ok' => true, 'pesan' => 'Tindak lanjut "' . $daftar[$jenis] . '" tersimpan.'];
}

/** Daftar guru yang pernah mencatat pelanggaran (untuk penyaring "Dicatat oleh"). */
function ks_pelanggaran_guru(): array
{
    if (sekolah_id() <= 0) {
        return [];
    }
    return all('SELECT DISTINCT g.id, g.nama FROM kasus ks JOIN guru g ON g.id = ks.guru_id
                WHERE ks.sekolah_id = ? ORDER BY g.nama', [sekolah_id()]);
}

/** Jumlah catatan pelanggaran yang BELUM ditindaklanjuti (untuk lencana chip tab). */
function ks_pelanggaran_belum(int $sekolahId = 0): int
{
    $sid = $sekolahId > 0 ? $sekolahId : sekolah_id();
    if ($sid <= 0) {
        return 0;
    }
    return (int) val("SELECT COUNT(*) FROM kasus WHERE sekolah_id = ? AND IFNULL(tindak_lanjut_jenis, '') = ''",
        [$sid], 0);
}

/** URL filter rekap pelanggaran (dipakai agar cetak mengikuti saringan yang sedang dipakai). */
function ks_pelanggaran_query(array $f): string
{
    $q = [];
    foreach (['cari', 'kelas', 'mapel', 'guru', 'jenis', 'status', 'tl', 'dari', 'sampai'] as $k) {
        $v = trim((string) ($f[$k] ?? ''));
        if ($v !== '' && $v !== '0') {
            $q[$k] = $v;
        }
    }
    return http_build_query($q);
}

// ------------------------------------------------------------------ halaman

/**
 * Jalankan halaman "Laporan Program Kerja Kesiswaan" (dipakai `tugas.php?kode=kesiswaan`).
 *
 * @param array<string,mixed> $tugas baris `tugas_menu` (judul mengikuti nama menu hasil ubah)
 */
function ks_halaman(array $tugas): void
{
    // Data program kerja selalu milik satu sekolah → Super Admin harus memilih sekolah dulu.
    require_sekolah();

    $namaMenu = (string) ($tugas['nama'] ?? 'Kesiswaan');
    $peranPengelola = tugas_menu_semua_peran();
    // Permintaan pemilik: kepala sekolah/madrasah & admin operator HANYA melihat + mencetak;
    // pengisian menu Tugas Tambahan dilakukan guru yang ditugaskan (lihat boleh_ubah_tugas_menu()).
    $bolehUbah = boleh_ubah_tugas_menu();
    if (!$bolehUbah && $_SERVER['REQUEST_METHOD'] === 'POST') {
        tolak_ubah_tugas_menu('laporan Program Kerja Kesiswaan');
    }

    // ---------------------------------------------------------------- POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $r = $id > 0 ? ks_by_id($id) : null;

        if ($aksi === 'simpan') {
            $jenis = trim((string) ($_POST['jenis'] ?? ''));
            $bentuk = trim((string) ($_POST['bentuk'] ?? ''));
            $tujuan = trim((string) ($_POST['tujuan'] ?? ''));
            $target = trim((string) ($_POST['target'] ?? ''));
            $ta = trim((string) ($_POST['ta'] ?? ''));
            $sumber = trim((string) ($_POST['sumber_dana'] ?? ''));
            $ket = trim((string) ($_POST['keterangan'] ?? ''));
            // Waktu Pelaksanaan = centang bulan (boleh beberapa).
            $waktu = ks_waktu_simpan(is_array($_POST['waktu'] ?? null) ? (array) $_POST['waktu'] : []);
            $now = date('Y-m-d H:i:s');

            $batas = [
                'jenis' => KS_TEKS_MAKS, 'bentuk' => KS_TEKS_MAKS, 'tujuan' => KS_URAIAN_MAKS,
                'target' => KS_URAIAN_MAKS, 'ta' => 40, 'sumber_dana' => KS_SUMBER_MAKS,
                'keterangan' => KS_URAIAN_MAKS,
            ];
            foreach (['jenis' => $jenis, 'bentuk' => $bentuk, 'tujuan' => $tujuan, 'target' => $target,
                      'ta' => $ta, 'sumber_dana' => $sumber, 'keterangan' => $ket] as $k => $v) {
                if (strlen((string) $v) > $batas[$k]) {
                    ${$k} = substr((string) $v, 0, $batas[$k]);
                }
            }

            if ($bentuk === '') {
                flash('Bentuk Kegiatan wajib diisi.', 'err');
                redirect('tugas.php?kode=' . e(KS_KODE_MENU) . '&tab=data' . ($id > 0 ? '&edit=' . $id : '') . '#formProgram');
            }

            if ($r) {
                q('UPDATE kesiswaan_program SET jenis=?, bentuk=?, tujuan=?, target=?, ta=?, waktu=?,
                     sumber_dana=?, keterangan=?, diubah=? WHERE id=? AND sekolah_id=?',
                    [$jenis, $bentuk, $tujuan, $target, $ta, $waktu, $sumber, $ket, $now, $id, sekolah_id()]);
                flash('Program kerja "' . $bentuk . '" diperbarui.');
            } else {
                $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) + 10 FROM kesiswaan_program WHERE sekolah_id = ?',
                    [sekolah_id()], 10);
                q('INSERT INTO kesiswaan_program (sekolah_id, jenis, bentuk, tujuan, target, ta, waktu,
                     sumber_dana, keterangan, urutan, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [sekolah_id(), $jenis, $bentuk, $tujuan, $target, $ta, $waktu, $sumber, $ket, $urutan, $now]);
                flash('Program kerja "' . $bentuk . '" ditambahkan.');
            }
            redirect('tugas.php?kode=' . e(KS_KODE_MENU) . '&tab=data' . ($peranPengelola ? '#tabelProgram' : ''));
        }

        if ($aksi === 'hapus') {
            if (!$r) {
                flash('Program kerja tidak ditemukan.', 'err');
                redirect('tugas.php?kode=' . e(KS_KODE_MENU));
            }
            q('DELETE FROM kesiswaan_program WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
            flash('Program kerja "' . $r['bentuk'] . '" dihapus.', 'warn');
            redirect('tugas.php?kode=' . e(KS_KODE_MENU) . '&tab=data');
        }

        // ---------------------------------------------------------- TINDAK LANJUT pelanggaran
        // Diberikan guru pemegang Tugas Tambahan "Kesiswaan" (dan Super Admin dalam konteks) atas
        // catatan pelanggaran yang dibuat GURU MATA PELAJARAN mana pun di sekolah ini.
        if ($aksi === 'tl_simpan' || $aksi === 'tl_hapus') {
            $pelanggaranId = (int) ($_POST['pelanggaran_id'] ?? 0);
            $jenisTl = $aksi === 'tl_hapus' ? '' : trim((string) ($_POST['tindak_lanjut_jenis'] ?? ''));
            $catatanTl = trim((string) ($_POST['tindak_lanjut'] ?? ''));
            $hasil = ks_tl_simpan($pelanggaranId, $jenisTl, $catatanTl);
            flash((string) $hasil['pesan'], $hasil['ok'] ? 'ok' : 'err');
            // Saringan yang sedang dilihat dibawa kembali supaya pengguna tidak kehilangan konteks.
            $balik = ks_pelanggaran_query([
                'cari' => $_POST['f_cari'] ?? '', 'kelas' => $_POST['f_kelas'] ?? '',
                'mapel' => $_POST['f_mapel'] ?? '', 'guru' => $_POST['f_guru'] ?? '',
                'jenis' => $_POST['f_jenis'] ?? '', 'status' => $_POST['f_status'] ?? '',
                'tl' => $_POST['f_tl'] ?? '', 'dari' => $_POST['f_dari'] ?? '',
                'sampai' => $_POST['f_sampai'] ?? '',
            ]);
            redirect('tugas.php?kode=' . e(KS_KODE_MENU) . '&tab=pelanggaran'
                . ($balik !== '' ? '&' . $balik : '') . '#tblRekapPelanggaranKesiswaan');
        }

        redirect('tugas.php?kode=' . e(KS_KODE_MENU));
    }

    // ---------------------------------------------------------------- tampilan
    $f = [
        'cari' => (string) ($_GET['cari'] ?? ''),
        'jenis' => (string) ($_GET['jenis'] ?? ''),
        'sumber_dana' => (string) ($_GET['sumber_dana'] ?? ''),
        'ta' => (string) ($_GET['ta'] ?? ''),
        'bulan' => (string) ($_GET['bulan'] ?? ''),
    ];
    $baris = ks_list($f);
    $ringkas = ks_ringkas($baris);
    $edit = ks_by_id((int) ($_GET['edit'] ?? 0));
    $tab = (string) ($_GET['tab'] ?? '');
    // Tab: laporan program kerja, data program (input), dan REKAP PELANGGARAN (v1.71.1).
    if (!in_array($tab, ['data', 'laporan', 'pelanggaran'], true)) {
        $tab = $peranPengelola ? 'laporan' : 'data';
    }
    if (!$bolehUbah && $tab === 'data') {
        // Kepala/operator/pengawas hanya melihat & mencetak isi menu Tugas Tambahan.
        $tab = 'laporan';
    }

    // ---------------------------------------------------------------- data rekap pelanggaran
    $fPel = [
        'cari' => (string) ($_GET['cari'] ?? ''),
        'kelas' => (int) ($_GET['kelas'] ?? 0),
        'mapel' => (int) ($_GET['mapel'] ?? 0),
        'guru' => (int) ($_GET['guru'] ?? 0),
        'jenis' => (string) ($_GET['jenis'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
        'tl' => (string) ($_GET['tl'] ?? ''),
        'dari' => (string) ($_GET['dari'] ?? ''),
        'sampai' => (string) ($_GET['sampai'] ?? ''),
    ];
    // Saringan `cari`/`jenis` dipakai bersama tab program kerja, jadi hanya dibaca pada tab ini.
    if ($tab !== 'pelanggaran') {
        $fPel['cari'] = '';
        $fPel['kelas'] = 0;
        $fPel['mapel'] = 0;
        $fPel['guru'] = 0;
        $fPel['jenis'] = '';
        $fPel['status'] = '';
        $fPel['tl'] = '';
        $fPel['dari'] = '';
        $fPel['sampai'] = '';
    }
    $pelanggaran = $tab === 'pelanggaran' ? ks_pelanggaran_list($fPel) : [];
    $ringkasPel = ks_pelanggaran_ringkas($pelanggaran);
    $qPel = ks_pelanggaran_query($fPel);
    $linkTab = 'tugas.php?kode=' . e(KS_KODE_MENU) . ($tab === 'pelanggaran'
        ? ($qPel !== '' ? '&' . e($qPel) : '') : ($qFilter !== '' ? '&' . e($qFilter) : ''));
    $jumlahBelumTl = ks_pelanggaran_belum();
    $qFilter = ks_query($f);
    $qLink = 'tugas.php?kode=' . e(KS_KODE_MENU) . ($qFilter !== '' ? '&' . e($qFilter) : '');
    $jenisTerpakai = ks_jenis_terpakai();
    $taPilihan = ks_ta_pilihan();
    $waktuTerpilih = $edit ? ks_waktu_daftar((string) ($edit['waktu'] ?? '')) : [];

    page_top($namaMenu, 'Laporan Program Kerja Kesiswaan ' . istilah_sekolah('Sekolah'));
    ?>
<div class="card">
  <div class="card-head">
    <?php if ($tab === 'pelanggaran'): ?>
      <div>
        <h2><?= ikon('alert', 16) ?> Rekap Pelanggaran Siswa dari Seluruh Guru Mata Pelajaran</h2>
        <p>Seluruh catatan pelanggaran yang dibuat <b>setiap guru mata pelajaran</b> pada
          <?= tetap_kata(nama_sekolah_tampil()) ?>, lengkap dengan <b>tindak lanjut</b> dari guru
          Tugas Tambahan Kesiswaan.</p>
      </div>
      <div class="flex">
        <span class="chip"><?= (int) $ringkasPel['total'] ?> catatan</span>
        <span class="chip"><?= (int) $ringkasPel['belum'] ?> belum ditindaklanjuti</span>
        <a class="btn btn-ghost btn-sm" href="cetak_kesiswaan_pelanggaran.php<?= $qPel !== '' ? '?' . e($qPel) : '' ?>" target="_blank"><?= ikon('print', 15) ?> Cetak Rekap</a>
      </div>
    <?php else: ?>
      <div>
        <h2><?= ikon('star', 16) ?> Laporan Program Kerja Kesiswaan</h2>
        <p>Program kerja Kesiswaan <b><?= tetap_kata(nama_sekolah_tampil()) ?></b>:
          Jenis Kegiatan, Bentuk Kegiatan, Tujuan, Target, Tahun Ajaran, Waktu Pelaksanaan, Sumber Dana,
          dan Keterangan.</p>
      </div>
      <div class="flex">
        <span class="chip"><?= (int) $ringkas['kegiatan'] ?> kegiatan</span>
        <span class="chip"><?= (int) $ringkas['jenis'] ?> jenis kegiatan</span>
        <?php if ($bolehUbah): ?>
          <a class="btn btn-ghost btn-sm" href="kesiswaan_import.php"><?= ikon('upload', 15) ?> Impor Excel/CSV</a>
          <a class="btn btn-ghost btn-sm" href="kesiswaan_export.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>"><?= ikon('doc', 15) ?> Ekspor</a>
        <?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="cetak_kesiswaan.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>" target="_blank"><?= ikon('print', 15) ?> Cetak</a>
      </div>
    <?php endif; ?>
  </div>

  <?php // Baris tab: "Rekap Pelanggaran" terlihat untuk SEMUA yang boleh membuka menu Kesiswaan
        // (kepala/operator/pengawas boleh melihat & mencetaknya; yang memberi tindak lanjut hanya
        // guru pemegang menu ini + Super Admin dalam konteks). ?>
  <div class="chip-list warna mb16">
    <a class="chip <?= $tab === 'laporan' ? 'on' : '' ?>" href="tugas.php?kode=<?= e(KS_KODE_MENU) ?>&tab=laporan<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>"><?= ikon('chart', 14) ?> Laporan</a>
    <?php if ($bolehUbah): ?>
      <a class="chip <?= $tab === 'data' ? 'on' : '' ?>" href="<?= $qLink ?>&tab=data"><?= ikon('clipboard', 14) ?> Data Program (Input)</a>
    <?php endif; ?>
    <a class="chip <?= $tab === 'pelanggaran' ? 'on' : '' ?>" href="tugas.php?kode=<?= e(KS_KODE_MENU) ?>&tab=pelanggaran"><?= ikon('alert', 14) ?> Rekap Pelanggaran<?= $jumlahBelumTl > 0 ? ' (' . (int) $jumlahBelumTl . ')' : '' ?></a>
  </div>
  <?php if (!$bolehUbah): ?>
    <div class="alert alert-warn" style="margin-bottom:16px">
      <?= ikon('eye', 16) ?>
      <span><?= pemberitahuan_hanya_lihat('laporan program kerja Kesiswaan dan rekap pelanggaran siswa') ?></span>
    </div>
  <?php endif; ?>

  <?php // ---------------------------------------------------------------- LAPORAN ?>
  <?php if ($tab === 'laporan'): ?>
    <div class="grid g4" style="gap:16px">
      <div class="card stat card-tight" style="margin:0">
        <div class="stat-ic g"><?= ikon('clipboard', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['kegiatan'] ?></div><div class="stat-lbl">Kegiatan</div></div>
      </div>
      <div class="card stat card-tight" style="margin:0">
        <div class="stat-ic"><?= ikon('folder', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['jenis'] ?></div><div class="stat-lbl">Jenis kegiatan</div></div>
      </div>
      <div class="card stat card-tight" style="margin:0">
        <div class="stat-ic o"><?= ikon('calendar', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= (int) count(array_filter($ringkas['bulan'])) ?></div>
          <div class="stat-lbl">Bulan ada kegiatan</div></div>
      </div>
      <div class="card stat card-tight" style="margin:0">
        <div class="stat-ic p"><?= ikon('alert', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['tanpa_waktu'] ?></div>
          <div class="stat-lbl">Waktu belum diisi</div></div>
      </div>
    </div>

    <?php if (!$baris): ?>
      <div class="empty mt16"><?= ikon('clipboard', 34) ?>
        <h3>Belum ada program kerja</h3>
        <p><?= $bolehUbah
          ? 'Isi lewat tab <b>Data Program (Input)</b>, impor dari Excel/CSV, atau ubah saringan di bawah.'
          : pesan_kosong_hanya_lihat('program kerja Kesiswaan') ?></p>
        <?php if ($bolehUbah): ?>
          <div class="flex" style="justify-content:center">
            <a class="btn btn-primary" href="<?= $qLink ?>&tab=data#formProgram"><?= ikon('plus', 16) ?> Input Program Kerja</a>
            <a class="btn btn-ghost" href="kesiswaan_import.php"><?= ikon('upload', 16) ?> Impor Excel/CSV</a>
          </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="grid g2 mt16" style="gap:16px">
        <div class="card" style="margin:0">
          <div class="card-head"><div><h2>Rekap per Jenis Kegiatan</h2><p>Banyaknya kegiatan pada tiap jenis.</p></div></div>
          <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Jenis Kegiatan</th><th class="ctr">Kegiatan</th></tr></thead>
            <tbody>
            <?php foreach (ks_rekap($baris, 'jenis') as $nm => $jml): ?>
              <tr><td><?= e((string) $nm) ?></td><td class="ctr"><?= (int) $jml ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </div>
        <div class="card" style="margin:0">
          <div class="card-head"><div><h2>Rekap Waktu Pelaksanaan</h2><p>Kegiatan per bulan (Juli s.d. Juni).</p></div></div>
          <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Bulan</th><th class="ctr">Kegiatan</th></tr></thead>
            <tbody>
            <?php foreach ($ringkas['bulan'] as $b => $jml): ?>
              <tr<?= $jml > 0 ? '' : ' class="baris-kosong"' ?>><td><?= e($b) ?></td>
                <td class="ctr"><?= $jml > 0 ? (int) $jml : '<span class="mut">-</span>' ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </div>
        <div class="card" style="margin:0">
          <div class="card-head"><div><h2>Rekap per Sumber Dana</h2><p>Asal pembiayaan kegiatan.</p></div></div>
          <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Sumber Dana</th><th class="ctr">Kegiatan</th></tr></thead>
            <tbody>
            <?php foreach (ks_rekap($baris, 'sumber_dana') as $nm => $jml): ?>
              <tr><td><?= e((string) $nm) ?></td><td class="ctr"><?= (int) $jml ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </div>
        <div class="card" style="margin:0">
          <div class="card-head"><div><h2>Rekap per Tahun Ajaran</h2><p>Sebaran program pada tiap tahun ajaran.</p></div></div>
          <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Tahun Ajaran</th><th class="ctr">Kegiatan</th></tr></thead>
            <tbody>
            <?php foreach (ks_rekap($baris, 'ta') as $nm => $jml): ?>
              <tr><td><?= e((string) $nm) ?></td><td class="ctr"><?= (int) $jml ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </div>
      </div>

      <div class="divider"></div>
      <div class="section-title mb8"><?= ikon('clipboard', 15) ?> Daftar Program Kerja (<?= count($baris) ?> kegiatan)</div>
      <?php ks_tabel($baris, false, $peranPengelola && $bolehUbah); ?>
    <?php endif; ?>

  <?php // ---------------------------------------------------------------- DATA (INPUT) ?>
  <?php elseif ($tab === 'data'): ?>
    <div class="card" id="formProgram" style="margin-bottom:18px">
      <div class="card-head">
        <div>
          <h2><?= ikon($edit ? 'edit' : 'plus', 16) ?> <?= $edit ? 'Ubah Program Kerja' : 'Tambah Program Kerja' ?></h2>
          <p>Isi program kerja, centang <b>Waktu Pelaksanaan</b> (boleh lebih dari satu bulan, Juli s.d. Juni).</p>
        </div>
        <?php if ($edit): ?><a class="btn btn-ghost btn-sm" href="<?= $qLink ?>&tab=data"><?= ikon('prev', 14) ?> Batal</a><?php endif; ?>
      </div>
      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="grid g2" style="gap:16px">
          <div>
            <label class="f" for="jenis">Jenis Kegiatan</label>
            <input type="text" id="jenis" name="jenis" maxlength="<?= (int) KS_TEKS_MAKS ?>"
                   list="saranJenis" value="<?= e((string) ($edit['jenis'] ?? '')) ?>"
                   placeholder="mis. Ekstrakurikuler">
            <?php if ($jenisTerpakai): ?>
              <datalist id="saranJenis">
                <?php foreach ($jenisTerpakai as $jt): ?><option value="<?= e($jt) ?>"></option><?php endforeach; ?>
              </datalist>
            <?php endif; ?>
            <div class="hint">Kelompok kegiatan (mis. Ekstrakurikuler, Keagamaan, Lomba).</div>
          </div>
          <div>
            <label class="f" for="bentuk">Bentuk Kegiatan *</label>
            <input type="text" id="bentuk" name="bentuk" maxlength="<?= (int) KS_TEKS_MAKS ?>" required
                   value="<?= e((string) ($edit['bentuk'] ?? '')) ?>" placeholder="mis. Latihan Pramuka mingguan">
          </div>
        </div>
        <div class="grid g2" style="gap:16px">
          <div>
            <label class="f" for="tujuan">Tujuan</label>
            <textarea id="tujuan" name="tujuan" rows="2" maxlength="<?= (int) KS_URAIAN_MAKS ?>"
                      placeholder="mis. Membentuk karakter disiplin &amp; kemandirian siswa"><?= e((string) ($edit['tujuan'] ?? '')) ?></textarea>
          </div>
          <div>
            <label class="f" for="target">Target</label>
            <textarea id="target" name="target" rows="2" maxlength="<?= (int) KS_URAIAN_MAKS ?>"
                      placeholder="mis. 30 siswa aktif, 1 kali lomba tingkat kabupaten"><?= e((string) ($edit['target'] ?? '')) ?></textarea>
          </div>
        </div>
        <div class="grid g3" style="gap:16px">
          <div>
            <label class="f" for="ta">Tahun Ajaran</label>
            <?php // Pilihan dari master Tahun Ajaran; boleh dikosongkan. ?>
            <select id="ta" name="ta">
              <option value="">— belum ditentukan —</option>
              <?php foreach ($taPilihan as $tp): ?>
                <option value="<?= e((string) $tp['nama']) ?>" <?= (string) ($edit['ta'] ?? '') === (string) $tp['nama'] ? 'selected' : '' ?>>
                  <?= e((string) $tp['nama']) ?>
                </option>
              <?php endforeach; ?>
              <?php // Nilai lama yang tidak ada di master (mis. hasil impor) tetap dicantumkan. ?>
              <?php if (($edit['ta'] ?? '') !== '' && !in_array((string) $edit['ta'], array_column($taPilihan, 'nama'), true)): ?>
                <option value="<?= e((string) $edit['ta']) ?>" selected><?= e((string) $edit['ta']) ?> (tidak ada di master)</option>
              <?php endif; ?>
            </select>
            <?php if (!$taPilihan): ?>
              <div class="hint">Belum ada Tahun Ajaran di Data Master — boleh dikosongkan.</div>
            <?php endif; ?>
          </div>
          <div>
            <label class="f" for="sumber_dana">Sumber Dana</label>
            <input type="text" id="sumber_dana" name="sumber_dana" maxlength="<?= (int) KS_SUMBER_MAKS ?>"
                   list="saranSumberDana" value="<?= e((string) ($edit['sumber_dana'] ?? '')) ?>"
                   placeholder="mis. BOS, Komite, Sumbangan">
            <div class="hint">Isian <b>isian bebas</b> (manual): tuliskan sumber dana apa pun.</div>
          </div>
          <div>
            <label class="f" for="keterangan">Keterangan</label>
            <input type="text" id="keterangan" name="keterangan" maxlength="<?= (int) KS_URAIAN_MAKS ?>"
                   value="<?= e((string) ($edit['keterangan'] ?? '')) ?>" placeholder="mis. rutin setiap Sabtu">
          </div>
        </div>
        <?php
        // Saran sumber dana dari nilai yang PERNAH dipakai sekolah ini (membantu pengetikan saja).
        $saranSumber = [];
        foreach (all('SELECT DISTINCT sumber_dana FROM kesiswaan_program
                      WHERE sekolah_id = ? AND IFNULL(sumber_dana, "") <> "" ORDER BY sumber_dana',
                     [sekolah_id()]) as $sr) {
            $saranSumber[] = (string) $sr['sumber_dana'];
        }
        ?>
        <?php if ($saranSumber): ?>
          <datalist id="saranSumberDana">
            <?php foreach ($saranSumber as $sr): ?><option value="<?= e($sr) ?>"></option><?php endforeach; ?>
          </datalist>
        <?php endif; ?>

        <div>
          <label class="f">Waktu Pelaksanaan</label>
          <div class="pengawas-pilih" style="grid-template-columns:repeat(4,minmax(0,1fr))">
            <?php foreach (KS_BULAN as $b): ?>
              <label class="pengawas-item">
                <input type="checkbox" name="waktu[]" value="<?= e($b) ?>"
                       <?= in_array($b, $waktuTerpilih, true) ? 'checked' : '' ?>>
                <span><b><?= e($b) ?></b></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="hint">Centang bulan pelaksanaan (Juli s.d. Juni) — boleh lebih dari satu.</div>
        </div>

        <div class="flex">
          <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $edit ? 'Simpan Perubahan' : 'Tambah Program Kerja' ?></button>
          <?php if ($edit): ?><a class="btn btn-ghost" href="<?= $qLink ?>&tab=data">Batal</a><?php endif; ?>
        </div>
      </form>
    </div>

    <form class="filterbar mb16" method="get">
      <input type="hidden" name="kode" value="<?= e(KS_KODE_MENU) ?>">
      <input type="hidden" name="tab" value="data">
      <div>
        <label class="f" for="cari">Cari</label>
        <input type="text" id="cari" name="cari" value="<?= e($f['cari']) ?>" placeholder="kegiatan / tujuan / target">
      </div>
      <div>
        <label class="f" for="fJenis">Jenis Kegiatan</label>
        <input type="text" id="fJenis" name="jenis" list="saranJenisFilter" value="<?= e($f['jenis']) ?>" placeholder="mis. Ekstrakurikuler">
        <?php if ($jenisTerpakai): ?>
          <datalist id="saranJenisFilter">
            <?php foreach ($jenisTerpakai as $jt): ?><option value="<?= e($jt) ?>"></option><?php endforeach; ?>
          </datalist>
        <?php endif; ?>
      </div>
      <div>
        <label class="f" for="fTa">Tahun Ajaran</label>
        <select id="fTa" name="ta" data-auto>
          <option value="">Semua tahun ajaran</option>
          <?php foreach ($taPilihan as $tp): ?>
            <option value="<?= e((string) $tp['nama']) ?>" <?= $f['ta'] === (string) $tp['nama'] ? 'selected' : '' ?>><?= e((string) $tp['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="f" for="fBulan">Bulan</label>
        <select id="fBulan" name="bulan" data-auto>
          <option value="">Semua bulan</option>
          <?php foreach (KS_BULAN as $b): ?>
            <option value="<?= e($b) ?>" <?= $f['bulan'] === $b ? 'selected' : '' ?>><?= e($b) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="f" for="fSumber">Sumber Dana</label>
        <input type="text" id="fSumber" name="sumber_dana" value="<?= e($f['sumber_dana']) ?>" placeholder="mis. BOS (boleh sebagian)">
      </div>
      <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
      <?php if ($qFilter !== ''): ?><a class="btn btn-ghost" href="tugas.php?kode=<?= e(KS_KODE_MENU) ?>&tab=data">Reset</a><?php endif; ?>
    </form>

    <?php if (!$baris): ?>
      <div class="empty"><?= ikon('clipboard', 34) ?>
        <h3>Belum ada program kerja</h3>
        <p>Tambahkan lewat formulir di atas, atau <a href="kesiswaan_import.php">impor dari Excel/CSV</a>.</p>
      </div>
    <?php else: ?>
      <?php ks_tabel($baris, true, $peranPengelola); ?>
    <?php endif; ?>

  <?php // ---------------------------------------------------------------- REKAP PELANGGARAN ?>
  <?php else: ?>
    <?php ks_pelanggaran_tampilan($pelanggaran, $ringkasPel, $fPel, $qPel, $bolehUbah); ?>
  <?php endif; ?>
</div>
    <?php
    // WAJIB dipanggil: tugas.php memanggil fungsi ini lalu `exit`, sehingga penutup halaman
    // (footer, </main>, dan <script src="assets/app.js">) hanya keluar dari sini. Tanpa ini
    // seluruh skrip halaman (filter `data-auto`, dialog konfirmasi hapus) tidak pernah aktif.
    page_bottom();
}

/**
 * Tabel program kerja.
 *
 * @param array<int,array<string,mixed>> $baris
 * @param bool $aksi true = kolom Tindakan (Ubah/Hapus) — semua pengguna menu boleh
 * @param bool $tombolBantuan true = catatan tautan ekspor/cetak di bawah tabel
 */
function ks_tabel(array $baris, bool $aksi, bool $tombolBantuan = false): void
{
    $qFilter = ks_query($_GET);
    ?>
<div class="table-wrap" id="tabelProgram">
  <table class="tbl tbl-kesiswaan">
    <thead>
      <tr>
        <th class="ctr k-no">No</th>
        <th class="k-jenis">Jenis Kegiatan</th>
        <th class="k-bentuk">Bentuk Kegiatan</th>
        <th class="k-tujuan">Tujuan</th>
        <th class="k-target">Target</th>
        <th class="ctr k-ta">Tahun Ajaran</th>
        <th class="k-waktu">Waktu Pelaksanaan</th>
        <th class="k-sumber">Sumber Dana</th>
        <th class="k-ket">Keterangan</th>
        <?php if ($aksi): ?><th class="ctr k-aksi">Tindakan</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php $n = 0; foreach ($baris as $r): $n++; ?>
      <tr>
        <td class="ctr"><?= $n ?></td>
        <td><?= e((string) ($r['jenis'] ?: '-')) ?></td>
        <td><b><?= e((string) $r['bentuk']) ?></b></td>
        <td class="sm"><?= e((string) ($r['tujuan'] ?: '-')) ?></td>
        <td class="sm"><?= e((string) ($r['target'] ?: '-')) ?></td>
        <td class="ctr"><?= e((string) ($r['ta'] ?: '-')) ?></td>
        <td class="sm"><?= ks_waktu_label((string) ($r['waktu'] ?? '')) !== ''
            ? e(ks_waktu_label((string) ($r['waktu'] ?? '')))
            : '<span class="mut xs">belum diisi</span>' ?></td>
        <td><?= e((string) ($r['sumber_dana'] ?: '-')) ?></td>
        <td class="sm"><?= e((string) ($r['keterangan'] ?: '-')) ?></td>
        <?php if ($aksi): ?>
          <td>
            <div class="row-actions">
              <a class="btn btn-ghost btn-sm" href="tugas.php?kode=<?= e(KS_KODE_MENU) ?>&tab=data&edit=<?= (int) $r['id'] ?>#formProgram"><?= ikon('edit', 14) ?> Ubah</a>
              <form method="post" data-confirm="Hapus program kerja &quot;<?= e((string) $r['bentuk']) ?>&quot;?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Hapus</button>
              </form>
            </div>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th colspan="6" class="ctr">Jumlah</th>
        <th colspan="<?= $aksi ? 4 : 3 ?>" class="ctr"><?= count($baris) ?> kegiatan</th>
      </tr>
    </tfoot>
  </table>
</div>
<?php if ($tombolBantuan): ?>
  <p class="hint mt8">Ingin ringkasannya? Buka tab <a href="tugas.php?kode=<?= e(KS_KODE_MENU) ?>&tab=laporan<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>">Laporan</a>,
    atau <a href="kesiswaan_export.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>">ekspor</a> /
    <a href="cetak_kesiswaan.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>" target="_blank">cetak</a> daftar ini.</p>
<?php endif; ?>
    <?php
}

// ================================================================== tampilan rekap pelanggaran

/**
 * Isi tab **Rekap Pelanggaran** pada menu Kesiswaan (permintaan pemilik).
 *
 * Menampilkan SELURUH catatan pelanggaran sekolah ini dari guru mata pelajaran mana pun, beserta
 * form tindak lanjut untuk guru pemegang Tugas Tambahan Kesiswaan (dan Super Admin dalam konteks).
 *
 * @param array<int,array<string,mixed>> $baris   hasil ks_pelanggaran_list()
 * @param array<string,int>              $ringkas hasil ks_pelanggaran_ringkas()
 * @param array<string,string|int>       $f       saringan aktif (dibawa kembali sesudah menyimpan)
 * @param string                         $qPel    query saringan aktif (untuk tautan cetak)
 * @param bool                           $bolehUbah true = boleh memberi tindak lanjut
 */
function ks_pelanggaran_tampilan(array $baris, array $ringkas, array $f, string $qPel, bool $bolehUbah): void
{
    $jenisList = kasus_jenis();
    $statusList = kasus_status();
    $tlList = ks_tl_list();
    $tautan = 'tugas.php?kode=' . KS_KODE_MENU . '&tab=pelanggaran';
    $daftarKelas = kelas_list(true);
    $daftarMapel = mapel_list(true);
    $daftarGuru = ks_pelanggaran_guru();
    ?>
<div class="grid g4" style="gap:16px">
  <div class="card stat card-tight" style="margin:0">
    <div class="stat-ic p"><?= ikon('alert', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['total'] ?></div>
      <div class="stat-lbl">Catatan Pelanggaran</div></div>
  </div>
  <div class="card stat card-tight" style="margin:0">
    <div class="stat-ic s"><?= ikon('student', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['siswa'] ?></div>
      <div class="stat-lbl">Siswa Tercatat</div></div>
  </div>
  <div class="card stat card-tight" style="margin:0">
    <div class="stat-ic o"><?= ikon('users', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['guru'] ?></div>
      <div class="stat-lbl">Guru Pencatat</div></div>
  </div>
  <div class="card stat card-tight" style="margin:0">
    <div class="stat-ic g"><?= ikon('check', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['belum'] ?></div>
      <div class="stat-lbl">Belum Ditindaklanjuti</div></div>
  </div>
</div>

<form class="filterbar mt16" method="get" id="formSaringPelanggaran">
  <input type="hidden" name="kode" value="<?= e(KS_KODE_MENU) ?>">
  <input type="hidden" name="tab" value="pelanggaran">
  <div>
    <label class="f" for="saringCari">Cari</label>
    <input type="search" id="saringCari" name="cari" value="<?= e((string) $f['cari']) ?>"
           placeholder="nama siswa, uraian, guru, mapel">
  </div>
  <div>
    <label class="f" for="saringKelas">Kelas</label>
    <select id="saringKelas" name="kelas" data-auto>
      <option value="">— semua kelas —</option>
      <?php foreach ($daftarKelas as $k): ?>
        <option value="<?= (int) $k['id'] ?>" <?= (int) $f['kelas'] === (int) $k['id'] ? 'selected' : '' ?>><?= e((string) $k['nama']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="f" for="saringMapel">Mata Pelajaran</label>
    <select id="saringMapel" name="mapel" data-auto>
      <option value="">— semua mapel —</option>
      <?php foreach ($daftarMapel as $m): ?>
        <option value="<?= (int) $m['id'] ?>" <?= (int) $f['mapel'] === (int) $m['id'] ? 'selected' : '' ?>><?= e((string) $m['nama']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="f" for="saringGuru">Dicatat oleh (Guru)</label>
    <select id="saringGuru" name="guru" data-auto>
      <option value="">— semua guru —</option>
      <?php foreach ($daftarGuru as $g): ?>
        <option value="<?= (int) $g['id'] ?>" <?= (int) $f['guru'] === (int) $g['id'] ? 'selected' : '' ?>><?= e((string) $g['nama']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="f" for="saringJenisPel">Jenis</label>
    <select id="saringJenisPel" name="jenis" data-auto>
      <option value="">— semua jenis —</option>
      <?php foreach ($jenisList as $kk => $nn): ?>
        <option value="<?= e((string) $kk) ?>" <?= (string) $f['jenis'] === (string) $kk ? 'selected' : '' ?>><?= e((string) $nn) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="f" for="saringStatusPel">Status</label>
    <select id="saringStatusPel" name="status" data-auto>
      <option value="">— semua status —</option>
      <?php foreach ($statusList as $kk => $nn): ?>
        <option value="<?= e((string) $kk) ?>" <?= (string) $f['status'] === (string) $kk ? 'selected' : '' ?>><?= e((string) $nn) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="f" for="saringTl">Tindak Lanjut</label>
    <select id="saringTl" name="tl" data-auto>
      <option value="">— semua —</option>
      <option value="belum" <?= (string) $f['tl'] === 'belum' ? 'selected' : '' ?>>Belum ditindaklanjuti</option>
      <option value="sudah" <?= (string) $f['tl'] === 'sudah' ? 'selected' : '' ?>>Sudah ditindaklanjuti</option>
    </select>
  </div>
  <div>
    <label class="f" for="saringDariPel">Dari tanggal</label>
    <input type="date" id="saringDariPel" name="dari" value="<?= e((string) $f['dari']) ?>">
  </div>
  <div>
    <label class="f" for="saringSampaiPel">Sampai tanggal</label>
    <input type="date" id="saringSampaiPel" name="sampai" value="<?= e((string) $f['sampai']) ?>">
  </div>
  <div class="flex" style="align-items:flex-end;gap:8px">
    <button class="btn btn-primary" type="submit"><?= ikon('search', 15) ?> Terapkan</button>
    <?php if ($qPel !== ''): ?>
      <a class="btn btn-ghost" href="<?= e($tautan) ?>">Reset</a>
    <?php endif; ?>
  </div>
</form>

<?php if (!$baris): ?>
  <div class="empty">
    <?= ikon('alert', 34) ?>
    <h3>Belum ada catatan pelanggaran</h3>
    <p><?= $qPel !== ''
      ? 'Tidak ada catatan yang cocok dengan saringan yang dipilih. Ubah saringan atau tekan Reset.'
      : 'Rekap ini terisi otomatis dari catatan yang dibuat guru mata pelajaran pada menu '
        . '<b>Pelanggaran Siswa</b>.' ?></p>
    <?php if ($qPel !== ''): ?>
      <a class="btn btn-ghost" href="<?= e($tautan) ?>"><?= ikon('prev', 15) ?> Tampilkan Semua</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="tbl tbl-pelanggaran-kesiswaan" id="tblRekapPelanggaranKesiswaan">
      <thead>
        <tr>
          <th class="ctr" style="width:44px">No</th>
          <th style="width:110px">Hari / Tanggal</th>
          <th>Nama Siswa</th>
          <th style="width:90px">Kelas</th>
          <th style="width:120px">Mata Pelajaran</th>
          <th style="width:110px">Jenis</th>
          <th>Uraian Pelanggaran</th>
          <th style="width:130px">Dicatat oleh</th>
          <th class="ctr" style="width:90px">Status</th>
          <th style="width:250px">Tindak Lanjut</th>
        </tr>
      </thead>
      <tbody>
      <?php $n = 0; foreach ($baris as $r): $n++;
        $tlJenis = ks_tl_label((string) ($r['tindak_lanjut_jenis'] ?? ''));
        $tlCatatan = trim((string) ($r['tindak_lanjut'] ?? ''));
        $jenisKasus = (string) ($r['jenis'] ?? '');
        $statusKasus = (string) ($r['status'] ?? 'baru');
      ?>
        <tr>
          <td class="ctr"><?= $n ?></td>
          <td class="sm"><?= e(tanggal_id((string) $r['tanggal'], true)) ?></td>
          <td><b><?= e((string) ($r['siswa_nama'] ?: '-')) ?></b>
            <?php if (trim((string) ($r['nis'] ?? '')) !== ''): ?>
              <span class="tbl-sub">NIS <?= e((string) $r['nis']) ?></span>
            <?php endif; ?>
          </td>
          <td><?= e((string) ($r['kelas_nama'] ?: '-')) ?></td>
          <td class="sm"><?= e((string) ($r['mapel_nama'] ?: '-')) ?></td>
          <td class="sm"><?= e((string) ($jenisList[$jenisKasus] ?? 'Lainnya')) ?></td>
          <td class="sm">
            <?php if (trim((string) ($r['judul'] ?? '')) !== ''): ?><b><?= e((string) $r['judul']) ?></b><br><?php endif; ?>
            <?= nl2br(e((string) ($r['deskripsi'] ?? '-'))) ?>
            <?php if (trim((string) ($r['tindakan'] ?? '')) !== ''): ?>
              <span class="tbl-sub">Tindakan guru: <?= e((string) $r['tindakan']) ?></span>
            <?php endif; ?>
          </td>
          <td class="sm">
            <?= e((string) ($r['guru_nama'] ?: '(tidak tercatat)')) ?>
            <?php if (trim((string) $r['tanggal']) !== ''): ?>
              <span class="tbl-sub"><?= e(tanggal_id((string) $r['tanggal'])) ?></span>
            <?php endif; ?>
          </td>
          <td class="ctr"><span class="pill <?= $statusKasus === 'selesai' ? 'pill-ok' : ($statusKasus === 'proses' ? 'pill-warn' : 'pill-gray') ?>">
            <?= e((string) ($statusList[$statusKasus] ?? 'Baru')) ?></span></td>
          <td>
            <?php if ($tlJenis !== ''): ?>
              <span class="pill pill-ok"><?= e($tlJenis) ?></span>
              <?php if ($tlCatatan !== ''): ?><div class="sm mt8"><?= nl2br(e($tlCatatan)) ?></div><?php endif; ?>
              <div class="tbl-sub"><?= e((string) ($r['tindak_lanjut_oleh'] ?? '')) ?>
                <?= trim((string) ($r['tindak_lanjut_pada'] ?? '')) !== ''
                  ? '· ' . e(tanggal_id(substr((string) $r['tindak_lanjut_pada'], 0, 10), true)) : '' ?></div>
            <?php else: ?>
              <span class="mut sm">Belum ditindaklanjuti</span>
            <?php endif; ?>

            <?php if ($bolehUbah): ?>
              <details class="tl-form">
                <summary><?= $tlJenis !== '' ? 'Ubah tindak lanjut' : 'Beri tindak lanjut' ?></summary>
                <form method="post" class="form-grid mt8">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="tl_simpan">
                  <input type="hidden" name="pelanggaran_id" value="<?= (int) $r['id'] ?>">
                  <?php // Saringan aktif dibawa kembali supaya pengguna tidak kehilangan konteks. ?>
                  <?php foreach (['cari', 'kelas', 'mapel', 'guru', 'jenis', 'status', 'tl', 'dari', 'sampai'] as $fk): ?>
                    <input type="hidden" name="f_<?= e($fk) ?>" value="<?= e((string) ($f[$fk] ?? '')) ?>">
                  <?php endforeach; ?>
                  <div>
                    <label class="f" for="tl_jenis_<?= (int) $r['id'] ?>">Jenis Tindak Lanjut</label>
                    <select id="tl_jenis_<?= (int) $r['id'] ?>" name="tindak_lanjut_jenis">
                      <option value="">— belum ditindaklanjuti —</option>
                      <?php foreach ($tlList as $kk => $nn): ?>
                        <option value="<?= e((string) $kk) ?>" <?= (string) ($r['tindak_lanjut_jenis'] ?? '') === (string) $kk ? 'selected' : '' ?>><?= e((string) $nn) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="f" for="tl_catatan_<?= (int) $r['id'] ?>">Catatan / Keterangan</label>
                    <textarea id="tl_catatan_<?= (int) $r['id'] ?>" name="tindak_lanjut" rows="2"
                              maxlength="<?= (int) KS_TL_CATATAN_MAKS ?>"
                              placeholder="mis. siswa dibina bersama wali kelas, orang tua dipanggil 12 Okt"><?= e((string) ($r['tindak_lanjut'] ?? '')) ?></textarea>
                  </div>
                  <div class="flex">
                    <button class="btn btn-primary btn-sm" type="submit"><?= ikon('check', 14) ?> Simpan Tindak Lanjut</button>
                  </div>
                </form>
                <?php if ($tlJenis !== ''): ?>
                  <form method="post" class="mt8" data-confirm="Batalkan tindak lanjut catatan ini?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="tl_hapus">
                    <input type="hidden" name="pelanggaran_id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Batalkan Tindak Lanjut</button>
                  </form>
                <?php endif; ?>
              </details>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="6" class="ctr">Jumlah</th>
          <th colspan="2" class="ctr"><?= (int) $ringkas['total'] ?> catatan ·
            <?= (int) $ringkas['sudah'] ?> sudah ditindaklanjuti</th>
          <th colspan="2" class="ctr"><?= (int) $ringkas['belum'] ?> belum ditindaklanjuti</th>
        </tr>
      </tfoot>
    </table>
  </div>
  <p class="hint mt8">Rekap ini memuat catatan pelanggaran dari <b>seluruh guru mata pelajaran</b>
    (bukan hanya yang dicatat guru Kesiswaan). Tombol <b>Cetak Rekap</b> di atas mencetak daftar yang
    sedang tampil, mengikuti saringan yang dipakai.</p>
<?php endif; ?>
    <?php
}
