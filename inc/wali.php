<?php
/**
 * MODUL WALI KELAS — EMPAT sub-menu dalam satu menu Tugas Tambahan (kode `wali_kelas`).
 *
 * Permintaan pemilik: *"Pada Menu wali kelas diisi dengan:
 *   1. Struktur Organisasi Kelas (baris bagan Tingkat: Wali kelas, Ketua Kelas, Wakil Ketua Kelas,
 *      Sekretaris, Bendahara, Seksi-seksi, Anggota)
 *   2. Program Kerja Wali Kelas (Tabelnya disamakan saja dengan Tabel Program kerja Pembina osim)
 *   3. Absensi Kelas (Input Harian sama dengan Absensi yang ada di guru tapi hanya kelas yang jadi
 *      perwaliannya)
 *   4. Jadwal Piket Kelas (Tabel: Hari dan dibawah tabel ada nama-nama siswa yang piket harian)
 *   Masing-masing tambahkan menu cetak dokumen."*
 *
 * Keputusan pemilik (jangan diubah tanpa bertanya):
 *   1. **Absensi = pencatatan HARIAN tersendiri** (tanggal + H/S/I/A per siswa, TANPA mapel) —
 *      berbeda dari menu "Daftar Hadir Siswa" yang per mata pelajaran & pertemuan.
 *   2. Yang membuka hanya **guru yang menjadi wali kelas itu** (kelasnya diatur operator lewat
 *      Data Kelas / Data Guru). **Pengelola sekolah** (kepala/operator/Super Admin dalam konteks)
 *      boleh MEMILIH kelas; **pengawas** hanya lihat.
 *   3. **Jadwal Piket**: nama siswa **dipilih/centang** dari daftar siswa kelas itu (tidak bisa salah
 *      ketik), dikelompokkan per HARI (Senin–Minggu).
 *   4. **Struktur Organisasi Kelas = kembar dengan Pembina OSIM**: jabatan baku + nama + baris bagan,
 *      beserta impor/ekspor dan cetak (tabel + bagan). Program kerja memakai susunan kolom yang sama
 *      dengan Program Kerja Pembina OSIM.
 *
 * Seluruh data PER KELAS (`kelas_id`) + per sekolah (`sekolah_id`) → setiap query dibatasi keduanya
 * (anti-IDOR). Halaman menu: `wali_halaman()`; halaman cetak memanggil fungsi `wali_*` dari modul ini.
 * **Jangan menyalin logika modul ini ke halaman lain.**
 */
declare(strict_types=1);

// ------------------------------------------------------------------ konfigurasi

/** Panjang maksimal isian teks (jabatan/nama/program/target/keterangan). */
const WALI_TEKS_MAKS = 120;
const WALI_URAIAN_MAKS = 400;

/**
 * Hari pada Jadwal Piket (urut Senin–Minggu).
 *
 * Permintaan pemilik (v1.27.0): *"Pada fitur Jadwal Piket tambahkan Hari Minggu"* → **Minggu**
 * ditambahkan setelah Sabtu (urutan tetap dimulai Senin seperti kebiasaan daftar piket sekolah).
 * Fungsi ini SATU-SATUNYA sumber daftar hari: dipakai form centang, tabel ringkasan, penyimpanan
 * (`aksi=piket_simpan`), dan ketiga lembar cetak — jadi menambah/mengurangi hari cukup di sini
 * (jangan menulis daftar hari di tempat lain).
 */
function wali_hari(): array
{
    return ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
}

/** Jabatan baku Struktur Organisasi Kelas (tombol "Susun Jabatan Baku"). */
function wali_jabatan(): array
{
    return ['Wali Kelas', 'Ketua Kelas', 'Wakil Ketua Kelas', 'Sekretaris', 'Bendahara', 'Anggota'];
}

/** Pilihan "Terlaksana" pada Program Kerja (sama dengan Pembina OSIM). */
function wali_terlaksana(): array
{
    return OSIM_TERLAKSANA;
}

/** Nilai sah untuk kolom `terlaksana` ('Ya'/'Tidak'/''). */
function wali_terlaksana_sah(string $nilai): string
{
    $v = trim($nilai);
    foreach (wali_terlaksana() as $o) {
        if (strcasecmp($o, $v) === 0) {
            return $o;
        }
    }
    return '';
}

/** Hari baku (nilai sah) dari sebuah penulisan. */
function wali_hari_sah(string $nilai): string
{
    foreach (wali_hari() as $h) {
        if (strcasecmp($h, trim($nilai)) === 0) {
            return $h;
        }
    }
    return '';
}

/** Pangkas teks dengan aman (mb_substr bila tersedia — runtime CLI bisa tanpa mbstring). */
function wali_pangkas(string $teks, int $maks): string
{
    if ($maks <= 0) {
        return '';
    }
    if (function_exists('mb_substr')) {
        return (string) mb_substr($teks, 0, $maks, 'UTF-8');
    }
    return substr($teks, 0, $maks);
}

/** Tanggal program untuk tampilan ('-' bila kosong). */
function wali_tanggal_label(?string $ymd): string
{
    return trim((string) $ymd) !== '' ? tanggal_id((string) $ymd, true) : '';
}

// ------------------------------------------------------------------ kelas perwalian

/**
 * Daftar kelas yang boleh dibuka pengguna ini.
 *
 *   - guru  → HANYA kelas yang ia jadi wali kelasnya (permintaan pemilik);
 *   - pengelola (kepala/operator/Super Admin dalam konteks) → seluruh kelas sekolah ini;
 *   - pengawas → seluruh kelas sekolah yang sedang dilihat (hanya lihat).
 */
function wali_kelas_daftar(): array
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return [];
    }
    if (is_guru()) {
        $gid = guru_id_saya();
        if ($gid <= 0) {
            return [];
        }
        return guru_kelas_perwalian($gid, $sid);
    }
    return kelas_list(true);
}

/**
 * Kelas yang sedang dibuka (dari `?kelas=ID`), sudah divalidasi terhadap daftar yang boleh dibuka.
 * Mengembalikan null bila pengguna ini belum punya kelas perwalian sama sekali.
 */
function wali_kelas_konteks(int $minta = 0): ?array
{
    $daftar = wali_kelas_daftar();
    if (!$daftar) {
        return null;
    }
    if ($minta > 0) {
        foreach ($daftar as $k) {
            if ((int) $k['id'] === $minta) {
                return $k;
            }
        }
        // ID di luar daftar (kelas sekolah lain / bukan perwaliannya) → DITOLAK, bukan diam-diam
        // jatuh ke kelas pertama (anti-IDOR).
        tolak403(is_guru()
            ? 'Kelas itu bukan kelas perwalian Anda.'
            : 'Kelas itu bukan kelas sekolah/madrasah ini.');
    }
    return $daftar[0];
}

/** true bila pengguna ini boleh MELIHAT kelas tersebut (dipakai penjaga halaman cetak). */
function wali_kelas_boleh(int $kelasId): bool
{
    foreach (wali_kelas_daftar() as $k) {
        if ((int) $k['id'] === $kelasId) {
            return true;
        }
    }
    return false;
}

/** Daftar siswa kelas itu (untuk absensi, piket, dan pratinjau pilihan). */
function wali_siswa(int $kelasId): array
{
    return $kelasId > 0 ? siswa_list($kelasId) : [];
}

/** Label kelas ringkas untuk lembar cetak: "VIII-A (Wali: Nama)" bila walinya terisi. */
function wali_kelas_label(?array $kelas): string
{
    if (!$kelas) {
        return '-';
    }
    $nama = (string) $kelas['nama'];
    $wali = trim((string) ($kelas['wali_nama'] ?? ''));
    if ($wali === '') {
        $wali = (string) (nama_guru_wali((int) ($kelas['wali_id'] ?? 0)) ?? '');
    }
    return $wali !== '' ? $nama . ' (Wali Kelas: ' . $wali . ')' : $nama;
}

/** Nama guru dari id (cadangan bila kolom wali_nama tidak ikut diambil). */
function nama_guru_wali(int $guruId): string
{
    if ($guruId <= 0) {
        return '';
    }
    return (string) val('SELECT nama FROM guru WHERE id = ? AND sekolah_id = ?', [$guruId, sekolah_id()], '');
}

/** Nama & NIP wali kelas sebuah kelas (untuk tanda tangan lembar cetak). */
function wali_penandatangan(?array $kelas): array
{
    $gid = (int) ($kelas['wali_id'] ?? 0);
    if ($gid <= 0) {
        return ['nama' => '', 'nip' => ''];
    }
    $g = one('SELECT nama, nip FROM guru WHERE id = ? AND sekolah_id = ?', [$gid, sekolah_id()]);
    return $g ? ['nama' => (string) $g['nama'], 'nip' => trim((string) ($g['nip'] ?? ''))] : ['nama' => '', 'nip' => ''];
}

// ------------------------------------------------------------------ 1. STRUKTUR ORGANISASI KELAS

/** Daftar baris struktur organisasi sebuah kelas. */
function wali_struktur_list(int $kelasId): array
{
    if ($kelasId <= 0) {
        return [];
    }
    return all('SELECT * FROM wali_struktur WHERE sekolah_id = ? AND kelas_id = ? ORDER BY urutan, id',
        [sekolah_id(), $kelasId]);
}

function wali_struktur_by_id(int $id, int $kelasId): ?array
{
    if ($id <= 0 || $kelasId <= 0) {
        return null;
    }
    return one('SELECT * FROM wali_struktur WHERE id = ? AND sekolah_id = ? AND kelas_id = ?',
        [$id, sekolah_id(), $kelasId]);
}

/** Geser satu baris struktur naik/turun, lalu rapikan nomor urut (10, 20, 30, …). */
function wali_struktur_geser(int $id, string $arah, int $kelasId): bool
{
    $baris = wali_struktur_list($kelasId);
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
            q('UPDATE wali_struktur SET urutan = ? WHERE id = ?', [$u, (int) $b['id']]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return true;
}

/** Tambahkan baris jabatan baku (tidak menggandakan yang sudah ada). @return int jumlah baris baru */
function wali_struktur_susun_baku(int $kelasId): int
{
    if ($kelasId <= 0) {
        return 0;
    }
    $ada = [];
    foreach (wali_struktur_list($kelasId) as $b) {
        $ada[strtolower(trim((string) $b['jabatan']))] = true;
    }
    $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) FROM wali_struktur WHERE sekolah_id = ? AND kelas_id = ?',
        [sekolah_id(), $kelasId], 0);
    $now = date('Y-m-d H:i:s');
    $tambah = 0;
    db()->exec('BEGIN IMMEDIATE');
    try {
        foreach (wali_jabatan() as $j) {
            $urutan += 10;
            if (isset($ada[strtolower($j)])) {
                continue;
            }
            q('INSERT INTO wali_struktur (sekolah_id, kelas_id, jabatan, nama, bagan, urutan, dibuat)
               VALUES (?,?,?,?,?,?,?)', [sekolah_id(), $kelasId, $j, '', 0, $urutan, $now]);
            $tambah++;
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return $tambah;
}

/**
 * Tingkat (baris) BAKU sebuah jabatan pada bagan struktur kelas.
 *
 * Mengikuti daftar permintaan pemilik: 1 Wali Kelas · 2 Ketua Kelas · 3 Wakil Ketua Kelas ·
 * 4 Sekretaris · 5 Bendahara · 6 Seksi-seksi · 7 Anggota.
 * Nama seksi apa pun yang tidak dikenali masuk tingkat 6 (kotak seksi), sedangkan "anggota" → 7.
 */
function wali_bagan_tingkat_baku(string $jabatan): int
{
    $j = strtolower(trim($jabatan));
    if ($j === '') {
        return 6;
    }
    // "wakil ketua" diperiksa LEBIH DULU daripada "ketua" (kalau tidak, keduanya jatuh ke tingkat 2).
    if (strpos($j, 'wakil') !== false && strpos($j, 'ketua') !== false) {
        return 3;
    }
    if (strpos($j, 'wali') !== false) {
        return 1;
    }
    if (strpos($j, 'ketua') !== false) {
        return 2;
    }
    if (strpos($j, 'sekretaris') !== false) {
        return 4;
    }
    if (strpos($j, 'bendahara') !== false) {
        return 5;
    }
    if (strpos($j, 'anggota') !== false) {
        return 7;
    }
    return 6;   // seksi/divisi (nama seksi ditulis manual)
}

/** Tingkat bagan efektif satu baris: kolom `bagan` bila diisi (1–7), kalau tidak otomatis. */
function wali_bagan_tingkat(array $baris): int
{
    $pilih = (int) ($baris['bagan'] ?? 0);
    if ($pilih >= 1 && $pilih <= 7) {
        return $pilih;
    }
    return wali_bagan_tingkat_baku((string) ($baris['jabatan'] ?? ''));
}

/** Judul kepala bagan struktur kelas. */
function wali_bagan_judul(?array $kelas): string
{
    return 'Struktur Organisasi Kelas ' . ($kelas ? (string) $kelas['nama'] : '');
}

/**
 * Susun baris struktur menjadi BAGAN (tingkat → kotak).
 *
 * Baris berjabatan sama pada tingkat yang sama digabung menjadi SATU kotak (cara menambahkan
 * anggota sebuah seksi: tambah baris dengan nama seksi yang sama) — perilaku yang sama dengan
 * bagan OSIM/OSIS supaya mudah dipahami pengguna.
 */
function wali_bagan_susun(array $baris): array
{
    $tingkat = [];
    foreach ($baris as $b) {
        $t = wali_bagan_tingkat($b);
        $judul = trim((string) ($b['jabatan'] ?? ''));
        if ($judul === '') {
            continue;
        }
        $nama = trim((string) ($b['nama'] ?? ''));
        $kunci = strtolower($judul);
        if (!isset($tingkat[$t])) {
            $tingkat[$t] = [];
        }
        if (!isset($tingkat[$t][$kunci])) {
            $tingkat[$t][$kunci] = ['judul' => $judul, 'nama' => [], 'baris' => []];
        }
        if ($nama !== '') {
            $tingkat[$t][$kunci]['nama'][] = $nama;
        }
        $tingkat[$t][$kunci]['baris'][] = (int) $b['id'];
    }
    ksort($tingkat);
    $keluar = [];
    foreach ($tingkat as $t => $kotak) {
        $keluar[$t] = array_values($kotak);
    }
    return $keluar;
}

/** Jumlah kotak & nama pada bagan (untuk chip ringkasan). */
function wali_bagan_statistik(array $bagan): array
{
    $kotak = 0;
    $nama = 0;
    foreach ($bagan as $daftar) {
        $kotak += count($daftar);
        foreach ($daftar as $b) {
            $nama += count($b['nama']);
        }
    }
    return ['tingkat' => count($bagan), 'kotak' => $kotak, 'nama' => $nama];
}

/**
 * Markup BAGAN struktur kelas. Memakai KELAS GAYA yang sama dengan bagan OSIM/OSIS
 * (`.osim-bagan*`) sehingga tampil konsisten tanpa perlu CSS baru.
 */
function wali_bagan_html(array $baris, ?array $kelas, bool $tanpaKepala = false): string
{
    $bagan = wali_bagan_susun($baris);
    if (!$bagan) {
        return '<div class="osim-bagan-kosong">Belum ada struktur organisasi kelas untuk digambar.</div>';
    }
    $kepala = '';
    if (!$tanpaKepala) {
        $kepala = '<div class="osim-bagan-kepala">'
            . '<div class="osim-bagan-kepala-judul">' . e(wali_bagan_judul($kelas)) . '</div>'
            . '<div class="osim-bagan-kepala-sekolah">' . tetap_kata(nama_sekolah_tampil()) . '</div>'
            . '</div>';
    }
    $html = '<div class="osim-bagan">' . $kepala;
    foreach ($bagan as $tingkat => $kotak) {
        $banyak = count($kotak) > 1;
        $html .= '<div class="osim-bagan-baris' . ($banyak ? ' banyak' : '') . '" data-tingkat="' . (int) $tingkat . '">';
        foreach ($kotak as $b) {
            $html .= '<div class="osim-bagan-kotak"><div class="osim-bagan-jabatan">'
                . e((string) $b['judul']) . '</div>';
            if ($b['nama']) {
                $labelKadiv = ((int) $tingkat === 6 && count($b['nama']) > 1);
                foreach (array_values($b['nama']) as $i => $nm) {
                    if ($labelKadiv && $i === 0) {
                        $html .= '<div class="osim-bagan-kadiv-label">Ketua Seksi:</div>';
                    }
                    $html .= '<div class="osim-bagan-nama">' . e((string) $nm) . '</div>';
                }
            } else {
                $html .= '<div class="osim-bagan-nama mut">(nama belum diisi)</div>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    return $html . '</div>';
}

// ------------------------------------------------------------------ 2. PROGRAM KERJA WALI KELAS

/** Daftar program kerja sebuah kelas (kolom sama dengan Program Kerja Pembina OSIM). */
function wali_program_list(int $kelasId, array $f = []): array
{
    if ($kelasId <= 0) {
        return [];
    }
    $w = ['sekolah_id = ?', 'kelas_id = ?'];
    $p = [sekolah_id(), $kelasId];
    $cari = trim((string) ($f['cari'] ?? ''));
    if ($cari !== '') {
        $w[] = '(nama LIKE ? OR target LIKE ? OR keterangan LIKE ?)';
        array_push($p, '%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%');
    }
    $ta = trim((string) ($f['ta'] ?? ''));
    if ($ta !== '') {
        $w[] = 'ta = ?';
        $p[] = $ta;
    }
    $ter = wali_terlaksana_sah((string) ($f['terlaksana'] ?? ''));
    if ($ter !== '') {
        $w[] = 'terlaksana = ?';
        $p[] = $ter;
    }
    return all('SELECT * FROM wali_program WHERE ' . implode(' AND ', $w) . ' ORDER BY urutan, id', $p);
}

function wali_program_by_id(int $id, int $kelasId): ?array
{
    if ($id <= 0 || $kelasId <= 0) {
        return null;
    }
    return one('SELECT * FROM wali_program WHERE id = ? AND sekolah_id = ? AND kelas_id = ?',
        [$id, sekolah_id(), $kelasId]);
}

/** Ringkasan program kerja (jumlah, terlaksana, belum, belum ditentukan). */
function wali_ringkas_program(array $baris): array
{
    $ya = 0;
    $tidak = 0;
    $belum = 0;
    foreach ($baris as $r) {
        $t = wali_terlaksana_sah((string) ($r['terlaksana'] ?? ''));
        if ($t === 'Ya') {
            $ya++;
        } elseif ($t === 'Tidak') {
            $tidak++;
        } else {
            $belum++;
        }
    }
    return ['program' => count($baris), 'ya' => $ya, 'tidak' => $tidak, 'belum' => $belum];
}

// ------------------------------------------------------------------ 3. ABSENSI HARIAN KELAS

/** Catatan absensi harian sebuah (kelas, tanggal). */
function wali_absensi_sesi(int $kelasId, string $tanggal): ?array
{
    if ($kelasId <= 0 || trim($tanggal) === '') {
        return null;
    }
    return one('SELECT * FROM wali_absensi WHERE sekolah_id = ? AND kelas_id = ? AND tanggal = ?',
        [sekolah_id(), $kelasId, $tanggal]);
}

/**
 * Daftar tanggal absensi tercatat (terbaru lebih dulu), boleh disaring rentang tanggal.
 *
 * @return array<int,array<string,mixed>> baris wali_absensi + jumlah H/S/I/A
 */
function wali_absensi_list(int $kelasId, string $dari = '', string $sampai = ''): array
{
    if ($kelasId <= 0) {
        return [];
    }
    $w = ['a.sekolah_id = ?', 'a.kelas_id = ?'];
    $p = [sekolah_id(), $kelasId];
    if (trim($dari) !== '') {
        $w[] = 'a.tanggal >= ?';
        $p[] = $dari;
    }
    if (trim($sampai) !== '') {
        $w[] = 'a.tanggal <= ?';
        $p[] = $sampai;
    }
    return all('SELECT a.*, g.nama AS guru_nama,
                       (SELECT COUNT(*) FROM wali_absensi_detail d WHERE d.absensi_id = a.id AND d.status = "H") AS jml_h,
                       (SELECT COUNT(*) FROM wali_absensi_detail d WHERE d.absensi_id = a.id AND d.status = "S") AS jml_s,
                       (SELECT COUNT(*) FROM wali_absensi_detail d WHERE d.absensi_id = a.id AND d.status = "I") AS jml_i,
                       (SELECT COUNT(*) FROM wali_absensi_detail d WHERE d.absensi_id = a.id AND d.status = "A") AS jml_a
                FROM wali_absensi a LEFT JOIN guru g ON g.id = a.guru_id
                WHERE ' . implode(' AND ', $w) . ' ORDER BY a.tanggal DESC', $p);
}

/** Rincian status per siswa pada sebuah catatan absensi. */
function wali_absensi_detail(int $absensiId): array
{
    if ($absensiId <= 0) {
        return [];
    }
    $keluar = [];
    foreach (all('SELECT * FROM wali_absensi_detail WHERE absensi_id = ?', [$absensiId]) as $r) {
        $keluar[(int) $r['siswa_id']] = $r;
    }
    return $keluar;
}

/**
 * Rekap kehadiran per siswa dari catatan harian sebuah kelas.
 *
 * @return array{siswa:array<int,array<string,mixed>>,hari:int,detail:array<int,array<int,string>>,
 *               dari:string,sampai:string}
 */
function wali_absensi_rekap(int $kelasId, string $dari = '', string $sampai = ''): array
{
    $siswa = wali_siswa($kelasId);
    $sesi = wali_absensi_list($kelasId, $dari, $sampai);
    $peta = [];      // siswa_id => ['H'=>n, 'S'=>n, 'I'=>n, 'A'=>n, 'total'=>n]
    $harian = [];    // tanggal => siswa_id => status
    foreach (array_reverse($sesi) as $s) {
        $harian[(string) $s['tanggal']] = [];
        foreach (wali_absensi_detail((int) $s['id']) as $sid => $d) {
            $st = strtoupper((string) $d['status']);
            if (!array_key_exists($st, status_hadir())) {
                $st = 'H';
            }
            $harian[(string) $s['tanggal']][$sid] = $st;
            if (!isset($peta[$sid])) {
                $peta[$sid] = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0, 'total' => 0];
            }
            $peta[$sid][$st]++;
            $peta[$sid]['total']++;
        }
    }
    $keluar = [];
    foreach ($siswa as $s) {
        $sid = (int) $s['id'];
        $d = $peta[$sid] ?? ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0, 'total' => 0];
        $d['nama'] = (string) $s['nama'];
        $d['nis'] = (string) ($s['nis'] ?? '');
        $d['persen'] = $d['total'] > 0 ? round($d['H'] * 100 / $d['total'], 1) : null;
        $d['id'] = $sid;
        $keluar[] = $d;
    }
    return [
        'siswa' => $keluar,
        'hari' => count($sesi),
        'detail' => $harian,
        'dari' => $dari,
        'sampai' => $sampai,
        'sesi' => $sesi,
    ];
}

// ------------------------------------------------------------------ 4. JADWAL PIKET KELAS

/**
 * Jadwal piket sebuah kelas: [hari => [ ['siswa_id'=>…, 'nama'=>…, 'urutan'=>…], … ]].
 *
 * Selalu memuat SELURUH hari (Senin–Minggu) walau belum ada isinya, supaya tabelnya tetap berbentuk
 * daftar Hari seperti permintaan pemilik.
 */
function wali_piket_jadwal(int $kelasId): array
{
    $keluar = [];
    foreach (wali_hari() as $h) {
        $keluar[$h] = [];
    }
    if ($kelasId <= 0) {
        return $keluar;
    }
    foreach (all('SELECT p.*, s.nama, s.nis, k.nama AS kelas_nama
                  FROM wali_piket p JOIN siswa s ON s.id = p.siswa_id LEFT JOIN kelas k ON k.id = s.kelas_id
                  WHERE p.sekolah_id = ? AND p.kelas_id = ? ORDER BY p.hari, p.urutan, s.nama',
                 [sekolah_id(), $kelasId]) as $r) {
        $h = wali_hari_sah((string) $r['hari']);
        if ($h === '') {
            continue;
        }
        $keluar[$h][] = [
            'siswa_id' => (int) $r['siswa_id'],
            'nama' => (string) $r['nama'],
            'nis' => trim((string) ($r['nis'] ?? '')),
            'urutan' => (int) $r['urutan'],
        ];
    }
    return $keluar;
}

/** Jumlah siswa terjadwal piket (seluruh hari). */
function wali_piket_jumlah(array $jadwal): int
{
    $n = 0;
    foreach ($jadwal as $daftar) {
        $n += count($daftar);
    }
    return $n;
}

// ------------------------------------------------------------------ 5. REKAP NILAI & LEGER
// Permintaan pemilik: *"Tambahkan fitur Rekap Nilai semua mata pelajaran dan Cetak Leger
// (tambahkan predikat dan peringkat) pada tugas tambahan wali kelas. Jadi semua nilai mata pelajaran
// yang diisi oleh yang mengajar di kelas itu terkirim ke Wali Kelas menjadi rekapan nilai."*
//
// Catatan rancangan: nilai TIDAK perlu "dikirim" ke mana pun — guru mata pelajaran menyimpan nilainya
// di tabel `nilai` (menu Penilaian Siswa), dan rekap ini MEMBACA langsung nilai tersebut untuk kelas
// perwalian. Karena itu rekap selalu ikut begitu guru pengampu menyimpan nilai (tanpa langkah kirim
// tambahan yang bisa terlupa). Nilai akhir tiap mapel dihitung dengan rumus yang SAMA seperti halaman
// nilai (nilai_akhir() + pengaturan bobot guru/sekolah lewat nilai_konteks()), sehingga angka pada
// leger wali kelas identik dengan yang dilihat guru mapel & yang tercetak pada lembarnya.

/**
 * Mata pelajaran yang berlaku untuk sebuah kelas dalam satu periode:
 *   (a) mapel yang SUDAH punya nilai pada kelas+periode itu (diisi guru pengampu), dan
 *   (b) mapel yang diampu guru yang ditugaskan mengajar kelas itu (guru_kelas × guru_mapel),
 * supaya mapel yang belum dinilai pun tetap muncul pada rekap (kolomnya kosong, bukan hilang).
 * Mapel nonaktif hanya ditampilkan bila benar-benar sudah punya nilai pada periode itu.
 */
function wali_mapel_kelas(int $kelasId, int $taId, int $semId): array
{
    $sid = sekolah_id();
    if ($kelasId <= 0 || $sid <= 0) {
        return [];
    }
    // Mapel yang punya nilai pada kelas + periode ini.
    $adaNilai = [];
    if ($taId > 0 && $semId > 0) {
        foreach (all('SELECT DISTINCT mapel_id FROM nilai
                      WHERE sekolah_id = ? AND kelas_id = ? AND ta_id = ? AND semester_id = ?',
                     [$sid, $kelasId, $taId, $semId]) as $r) {
            $adaNilai[(int) $r['mapel_id']] = true;
        }
    }
    // Mapel yang diampu guru pengajar kelas ini.
    $diampu = [];
    foreach (all('SELECT DISTINCT gm.mapel_id FROM guru_kelas gk
                  JOIN guru g ON g.id = gk.guru_id
                  JOIN guru_mapel gm ON gm.guru_id = gk.guru_id
                  WHERE gk.kelas_id = ? AND g.sekolah_id = ? AND g.aktif = 1',
                 [$kelasId, $sid]) as $r) {
        $diampu[(int) $r['mapel_id']] = true;
    }
    $keluar = [];
    foreach (mapel_list(false) as $m) {
        $mid = (int) $m['id'];
        $pakai = isset($adaNilai[$mid]) || (isset($diampu[$mid]) && (int) $m['aktif'] === 1);
        if (!$pakai) {
            continue;
        }
        $guru = guru_pengampu($kelasId, $mid);
        $keluar[] = [
            'id' => $mid,
            'nama' => (string) $m['nama'],
            'kelompok' => trim((string) ($m['kelompok'] ?? '')),
            'aktif' => (int) $m['aktif'],
            // Guru pengampu dipakai sebagai keterangan "diisi oleh siapa" pada rekap per mapel.
            'guru_nama' => $guru ? (string) $guru['nama'] : '',
        ];
    }
    return $keluar;
}

/**
 * Seluruh baris nilai satu kelas + periode, dikelompokkan per mata pelajaran & siswa:
 *   [mapel_id][siswa_id] = ['formatif'=>[kolom=>nilai], 'sumatif'=>[…], 'asas'=>?float]
 * Satu query saja (bukan satu query per mapel) supaya rekap tetap ringan.
 */
function wali_nilai_baris(int $kelasId, int $taId, int $semId): array
{
    $sid = sekolah_id();
    if ($kelasId <= 0 || $taId <= 0 || $semId <= 0 || $sid <= 0) {
        return [];
    }
    $keluar = [];
    foreach (all('SELECT siswa_id, mapel_id, jenis, kolom, nilai FROM nilai
                  WHERE sekolah_id = ? AND kelas_id = ? AND ta_id = ? AND semester_id = ?',
                 [$sid, $kelasId, $taId, $semId]) as $r) {
        $mid = (int) $r['mapel_id'];
        $sidS = (int) $r['siswa_id'];
        if (!isset($keluar[$mid][$sidS])) {
            $keluar[$mid][$sidS] = ['formatif' => [], 'sumatif' => [], 'asas' => null];
        }
        // Kolom Formatif boleh berisi huruf/centang (v1.36.0) → nilai non-angka TIDAK boleh di-cast
        // ke float (huruf akan menjadi 0). nilai_akhir()/rata() hanya memakai sel angka.
        $raw = $r['nilai'];
        $v = ($raw === null || $raw === '') ? null : (is_numeric($raw) ? (float) $raw : (string) $raw);
        if ($r['jenis'] === 'formatif') {
            $keluar[$mid][$sidS]['formatif'][(int) $r['kolom']] = $v;
        } elseif ($r['jenis'] === 'sumatif') {
            $keluar[$mid][$sidS]['sumatif'][(int) $r['kolom']] = $v;
        } else {
            $keluar[$mid][$sidS]['asas'] = is_float($v) || is_int($v) ? (float) $v : nilai_angka($v);
        }
    }
    return $keluar;
}

/**
 * REKAP NILAI seluruh mata pelajaran sebuah kelas pada satu periode, lengkap dengan
 * predikat & peringkat (permintaan pemilik).
 *
 * @return array{
 *   mapel: list<array{id:int,nama:string,guru_nama:string,terisi:int,rata:?float,predikat:string,
 *                     konteks:array}>,
 *   siswa: list<array{id:int,nama:string,nis:string,nilai:array<int,?float>,terisi:int,
 *                     jumlah:?float,rata:?float,predikat:string,peringkat:?int}>,
 *   rata_mapel: array<int,?float>, rata_kelas: ?float, siswa_ternilai:int
 * }
 */
function wali_nilai_rekap(int $kelasId, int $taId, int $semId): array
{
    $daftarMapel = wali_mapel_kelas($kelasId, $taId, $semId);
    $baris = wali_nilai_baris($kelasId, $taId, $semId);
    $daftarSiswa = wali_siswa($kelasId);

    $hasil = [
        'mapel' => [],
        'siswa' => [],
        'rata_mapel' => [],
        'rata_kelas' => null,
        'siswa_ternilai' => 0,
    ];

    // Nilai akhir tiap (mapel, siswa) — rumusnya SAMA dengan halaman Penilaian Siswa.
    $nilaiPerMapel = [];
    foreach ($daftarMapel as $m) {
        $mid = (int) $m['id'];
        // Bobot & ambang predikat mengikuti pengaturan yang BERLAKU untuk mapel itu
        // (pengaturan guru pengampu bila sudah dibuat, kalau belum → pengaturan sekolah).
        $konteks = nilai_konteks($mid, $kelasId);
        $nilaiMapel = [];
        foreach ($daftarSiswa as $s) {
            $sidS = (int) $s['id'];
            $sel = $baris[$mid][$sidS] ?? null;
            $na = null;
            if ($sel !== null) {
                $asas = is_float($sel['asas']) ? $sel['asas'] : null;
                $na = nilai_akhir($sel['sumatif'], $asas, (float) $konteks['bobot_asas']);
            }
            $nilaiMapel[$sidS] = $na;
        }
        $nilaiPerMapel[$mid] = $nilaiMapel;
        $terisi = count(array_filter($nilaiMapel, static fn($v): bool => $v !== null));
        $rataMapel = rata(array_values($nilaiMapel));
        $hasil['mapel'][] = $m + [
            'konteks' => $konteks,
            'terisi' => $terisi,
            'rata' => $rataMapel,
            'predikat' => $terisi > 0 ? predikat_dari_k($konteks, $rataMapel) : '-',
        ];
        $hasil['rata_mapel'][$mid] = $rataMapel;
    }

    // Baris per siswa: nilai tiap mapel + jumlah, rata-rata, dan predikat menyeluruh.
    foreach ($daftarSiswa as $s) {
        $sidS = (int) $s['id'];
        $nilai = [];
        foreach ($hasil['mapel'] as $m) {
            $nilai[(int) $m['id']] = $nilaiPerMapel[(int) $m['id']][$sidS] ?? null;
        }
        $angka = array_values(array_filter($nilai, static fn($v): bool => $v !== null));
        $jumlah = $angka ? round(array_sum($angka), 2) : null;
        $rataSiswa = $angka ? round(array_sum($angka) / count($angka), 2) : null;
        $hasil['siswa'][] = [
            'id' => $sidS,
            'nama' => (string) $s['nama'],
            'nis' => trim((string) ($s['nis'] ?? '')),
            'nilai' => $nilai,
            'terisi' => count($angka),
            'jumlah' => $jumlah,
            'rata' => $rataSiswa,
            // Predikat menyeluruh memakai AMBANG SEKOLAH (gabungan semua mapel, bukan satu mapel).
            'predikat' => predikat_dari($rataSiswa),
            'peringkat' => null,
        ];
        if ($angka) {
            $hasil['siswa_ternilai']++;
        }
    }

    // PERINGKAT: urut rata-rata (tertinggi lebih dulu); nilai SERI mendapat peringkat yang sama
    // (competition ranking), lalu jumlah nilai & nama menjadi penentu urutan tampil. Siswa yang
    // belum punya nilai tetap ditampilkan dengan peringkat kosong ("-") agar seluruh kelas terlihat.
    $urut = array_keys($hasil['siswa']);
    usort($urut, static function (int $a, int $b) use ($hasil): int {
        $ra = $hasil['siswa'][$a]['rata'];
        $rb = $hasil['siswa'][$b]['rata'];
        if ($ra === null && $rb === null) {
            return strcmp($hasil['siswa'][$a]['nama'], $hasil['siswa'][$b]['nama']);
        }
        if ($ra === null) {
            return 1;
        }
        if ($rb === null) {
            return -1;
        }
        if ($ra != $rb) {
            return $ra < $rb ? 1 : -1;
        }
        $ja = (float) ($hasil['siswa'][$a]['jumlah'] ?? 0);
        $jb = (float) ($hasil['siswa'][$b]['jumlah'] ?? 0);
        if ($ja != $jb) {
            return $ja < $jb ? 1 : -1;
        }
        return strcmp($hasil['siswa'][$a]['nama'], $hasil['siswa'][$b]['nama']);
    });
    $peringkat = 0;
    $sebelumnya = null;
    $posisi = 0;
    foreach ($urut as $i) {
        $rataSiswa = $hasil['siswa'][$i]['rata'];
        if ($rataSiswa === null) {
            continue;
        }
        $posisi++;
        if ($sebelumnya === null || $rataSiswa < $sebelumnya) {
            $peringkat = $posisi;      // seri → peringkat sama; sesudahnya melompat
            $sebelumnya = $rataSiswa;
        }
        $hasil['siswa'][$i]['peringkat'] = $peringkat;
    }
    // Tampilkan yang berperingkat lebih dulu (urut peringkat); tanpa nilai di belakang.
    usort($hasil['siswa'], static function (array $a, array $b): int {
        $pa = $a['peringkat'] ?? PHP_INT_MAX;
        $pb = $b['peringkat'] ?? PHP_INT_MAX;
        if ($pa !== $pb) {
            return $pa <=> $pb;
        }
        return strcmp($a['nama'], $b['nama']);
    });

    $hasil['rata_kelas'] = rata(array_values(array_filter($hasil['rata_mapel'],
        static fn($v): bool => $v !== null)));
    return $hasil;
}

/**
 * Potong judul mata pelajaran untuk kepala kolom leger (tanpa mb_* — runtime sandbox tidak selalu
 * memuat mbstring; pemotongan berbasis preg_split('//u') tetap aman untuk huruf ber-UTF-8).
 */
function wali_potong_judul(string $teks, int $maks = 12): string
{
    $teks = trim($teks);
    if ($teks === '' || $maks < 1) {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($teks) > $maks ? mb_substr($teks, 0, $maks) . '…' : $teks;
    }
    $huruf = preg_split('//u', $teks, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($huruf)) {
        return strlen($teks) > $maks ? substr($teks, 0, $maks) . '…' : $teks;
    }
    return count($huruf) > $maks ? implode('', array_slice($huruf, 0, $maks)) . '…' : $teks;
}

/** Keterangan singkat "N dari M siswa sudah punya nilai" untuk kepala kartu rekap. */
function wali_nilai_ringkas(array $rekap): string
{
    $total = count($rekap['siswa'] ?? []);
    $isi = (int) ($rekap['siswa_ternilai'] ?? 0);
    return $isi . ' dari ' . $total . ' siswa sudah punya nilai';
}


// ------------------------------------------------------------------ tabel di halaman

/** Tabel struktur organisasi kelas (No, Jabatan/Nama Seksi, Nama, Baris bagan, Tindakan). */
function wali_tabel_struktur(array $baris, bool $aksi, int $kelasId): void
{
    ?>
<div class="table-wrap" id="tabelStruktur">
  <table class="tbl tbl-wali">
    <thead>
      <tr>
        <th class="ctr k-no">No</th>
        <th class="k-jabatan">Jabatan / Nama Seksi</th>
        <th class="k-nama">Nama</th>
        <th class="ctr k-bagan">Baris bagan</th>
        <th class="ctr k-aksi">Tindakan</th>
      </tr>
    </thead>
    <tbody>
    <?php $n = 0; foreach ($baris as $b): $n++; $tingkat = wali_bagan_tingkat($b); ?>
      <tr>
        <td class="ctr"><?= $n ?></td>
        <td><b><?= e((string) $b['jabatan']) ?></b></td>
        <td><?= trim((string) ($b['nama'] ?? '')) !== '' ? e((string) $b['nama']) : '<span class="mut xs">belum diisi</span>' ?></td>
        <td class="ctr"><span class="pill pill-B"><?= (int) $tingkat ?></span>
          <?= (int) ($b['bagan'] ?? 0) === 0 ? '<span class="tbl-sub">otomatis</span>' : '' ?></td>
        <td>
          <div class="row-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(wali_url($kelasId, 'struktur', ['edit' => (int) $b['id']])) ?>#formStruktur"><?= ikon('edit', 14) ?> Ubah</a>
            <?php if ($aksi): ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="struktur_geser">
                <input type="hidden" name="kelas" value="<?= $kelasId ?>">
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit" name="arah" value="naik" title="Naikkan urutan"><?= ikon('up', 14) ?></button>
                <button class="btn btn-ghost btn-sm" type="submit" name="arah" value="turun" title="Turunkan urutan"><?= ikon('down', 14) ?></button>
              </form>
              <form method="post" data-confirm="Hapus baris &quot;<?= e((string) $b['jabatan']) ?>&quot; dari struktur kelas?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="struktur_hapus">
                <input type="hidden" name="kelas" value="<?= $kelasId ?>">
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Hapus</button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><th colspan="2" class="ctr">Jumlah</th>
        <th><?= count($baris) ?> baris</th>
        <th colspan="<?= $aksi ? 2 : 1 ?>"><?= (int) wali_bagan_statistik(wali_bagan_susun($baris))['nama'] ?> nama terisi</th>
      </tr>
    </tfoot>
  </table>
</div>
    <?php
}

/** Tabel program kerja wali kelas (kolom sama dengan Program Kerja Pembina OSIM). */
function wali_tabel_program(array $baris, bool $aksi, int $kelasId): void
{
    ?>
<div class="table-wrap" id="tabelProgramWali">
  <table class="tbl tbl-wali">
    <thead>
      <tr>
        <th class="ctr k-no">No</th>
        <th class="k-nama">Nama Program</th>
        <th class="k-waktu">Waktu Pelaksanaan</th>
        <th class="k-target">Target</th>
        <th class="ctr k-ta">Tahun Ajaran</th>
        <th class="ctr k-ter">Terlaksana</th>
        <th class="k-ket">Keterangan</th>
        <th class="ctr k-aksi">Tindakan</th>
      </tr>
    </thead>
    <tbody>
    <?php $n = 0; foreach ($baris as $r): $n++; $ter = wali_terlaksana_sah((string) ($r['terlaksana'] ?? '')); ?>
      <tr>
        <td class="ctr"><?= $n ?></td>
        <td><b><?= e((string) $r['nama']) ?></b></td>
        <td class="sm"><?= trim((string) ($r['waktu'] ?? '')) !== '' ? e(wali_tanggal_label((string) $r['waktu'])) : '<span class="mut xs">belum diisi</span>' ?></td>
        <td class="sm"><?= e((string) ($r['target'] ?: '-')) ?></td>
        <td class="ctr"><?= e((string) ($r['ta'] ?: '-')) ?></td>
        <td class="ctr">
          <?php if ($ter === '') : ?><span class="pill pill-gray">—</span>
          <?php else: ?><span class="pill <?= $ter === 'Ya' ? 'pill-ok' : 'pill-warn' ?>"><?= e($ter) ?></span><?php endif; ?>
        </td>
        <td class="sm"><?= e((string) ($r['keterangan'] ?: '-')) ?></td>
        <td>
          <div class="row-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(wali_url($kelasId, 'program', ['edit' => (int) $r['id']])) ?>#formProgramWali"><?= ikon('edit', 14) ?> Ubah</a>
            <?php if ($aksi): ?>
              <form method="post" data-confirm="Hapus program &quot;<?= e((string) $r['nama']) ?>&quot;?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="program_hapus">
                <input type="hidden" name="kelas" value="<?= $kelasId ?>">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Hapus</button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><th colspan="5" class="ctr">Jumlah</th>
        <th class="ctr"><?= count($baris) ?> program</th>
        <th colspan="<?= $aksi ? 2 : 1 ?>"><?= (int) wali_ringkas_program($baris)['ya'] ?> terlaksana</th>
      </tr>
    </tfoot>
  </table>
</div>
    <?php
}

// ------------------------------------------------------------------ bantuan LEMBAR CETAK

/**
 * Kepala lembar cetak modul Wali Kelas (dipakai kelima berkas cetak).
 *
 * Menyiapkan: <head> + gaya lembar + toolbar (Kembali & Cetak/Simpan PDF) + `.print-sheet`
 * + kop sekolah (`kop_cetak($judul, …)`). Penanda kertas ("A4 potrait/landscape") mengikuti
 * `$orientasi` supaya tata letaknya sama seperti lembar cetak lain aplikasi ini.
 */
function wali_cetak_head(string $judul, ?array $kelas, string $orientasi = 'landscape', array $barisKop = []): void
{
    $land = $orientasi === 'landscape';
    $auto = isset($_GET['auto']);
    // Parameter yang harus dibawa ke tombol "Cetak / Simpan PDF".
    $q = array_filter(array_merge([
        'kelas' => (int) ($kelas['id'] ?? 0),
        'dari' => trim((string) ($_GET['dari'] ?? '')),
        'sampai' => trim((string) ($_GET['sampai'] ?? '')),
    ], $barisKop), static fn($v): bool => (string) $v !== '' && (string) $v !== '0');
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:<?= $land ? '1160' : '880' ?>px;margin:0 auto}
  .toolbar{max-width:<?= $land ? '1160' : '880' ?>px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table.sheet.wali th,table.sheet.wali td{padding:4px 6px;font-size:.74rem;line-height:1.28}
  table.sheet.wali th{font-size:.66rem;letter-spacing:0;vertical-align:middle}
  table.sheet.wali td.sm{font-size:.7rem}
  table.sheet.wali .k-no{width:34px}
  /* Tata letak layar sempit HANYA untuk layar (media "screen"): saat MENCETAK, lebar kertas
     <?= $land ? 'A4 landscape ±1032px' : 'A4 potrait ±695px' ?> sehingga aturan max-width ikut aktif
     bila tidak dibatasi. */
  @media screen and (max-width:<?= $land ? '900' : '760' ?>px){
    .print-sheet{padding:18px 14px}
    table.sheet.wali{min-width:<?= $land ? '760' : '560' ?>px}
    .sheet-meta{grid-template-columns:minmax(0,1fr)}
  }
  @media print{
    /* Tema menetapkan body{min-height:100vh}; saat mencetak 100vh = tinggi kertas sehingga dokumen
       menjadi setinggi halaman dan ikut mencetak halaman kedua yang hampir kosong. */
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .sheet-wrap{max-width:none}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
    .sheet-scroll{overflow:visible}
    table.sheet.wali{min-width:0}
    table.sheet.wali thead{display:table-header-group}
    table.sheet.wali tr{break-inside:avoid}
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap;justify-content:space-between}
    .sheet-foot.dua-ttd .sheet-sign{flex:1 1 0;min-width:0}
    /* v1.39.0: baris "tempat, tanggal" di atas tanda tangan Wali Kelas menambah tinggi ±20px.
       Karena lembar BAGAN harus tetap muat SATU halaman A4 landscape (±703px), footer lembar
       Wali Kelas dirapatkan saat mencetak (jarak kaki + tinggi ruang tanda tangan). */
    .sheet-foot{margin-top:16px}
    .sheet-sign .space{height:46px}
  }
  @page{size:A4 <?= $land ? 'landscape' : 'portrait' ?>;margin:<?= $land ? '12mm' : '13mm' ?>}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="<?= e(wali_url((int) ($kelas['id'] ?? 0))) ?>">Kembali</a>
  <a class="btn btn-primary" href="<?= e(basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'cetak_wali.php')) . '?' . http_build_query(array_merge($q, ['auto' => 1]))) ?>">
    <?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak($judul, [wali_kelas_label($kelas)]); ?>
    <?php
}

/**
 * Kaki lembar cetak modul Wali Kelas: keterangan + tanda tangan (Kepala Sekolah/Madrasah di KIRI,
 * Wali Kelas di KANAN) + baris "Dicetak dari …", lalu menutup halaman.
 */
function wali_cetak_kaki(?array $kelas, string $keterangan = ''): void
{
    $wali = wali_penandatangan($kelas);
    if ($keterangan !== '') {
        echo '<div class="ket-print">' . $keterangan . '</div>';
    }
    ?>
    <div class="sheet-foot dua-ttd">
      <?php ttd_blok(ttd_mengetahui(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kiri'); ?>
      <?php ttd_blok('Wali Kelas ' . (string) ($kelas['nama'] ?? ''), $wali['nama'], $wali['nip'], 'sheet-sign sheet-sign-kanan', true); ?>
    </div>

    <p class="xs mut center" style="margin-top:18px">
      Dicetak dari <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> pada <?= e(tanggal_id(date('Y-m-d'))) ?>.
    </p>
  </div>
</div>

<?php if (isset($_GET['auto'])): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
    <?php
    terjemahkan_selesai();
}

/** Siapkan keluaran tertampung + konteks kelas untuk sebuah lembar cetak (dipakai kelima berkas). */
function wali_cetak_siapkan(): array
{
    ob_start();
    $GLOBALS['__vs_buf_level'] = ob_get_level();

    require_login();
    require_sekolah();
    $kelas = wali_kelas_konteks((int) ($_GET['kelas'] ?? 0));
    if (!$kelas) {
        flash(is_guru()
            ? 'Anda belum ditetapkan sebagai wali kelas, jadi belum ada data kelas yang bisa dicetak.'
            : 'Belum ada kelas yang bisa dicetak pada sekolah/madrasah ini.', 'warn');
        redirect('tugas.php?kode=' . WALI_KODE_MENU);
    }
    return $kelas;
}

// ------------------------------------------------------------------ halaman menu

/** URL halaman menu Wali Kelas (dengan kelas & tab terpilih). */
function wali_url(int $kelasId = 0, string $tab = '', array $tambah = []): string
{
    $q = ['kode' => WALI_KODE_MENU];
    if ($kelasId > 0) {
        $q['kelas'] = $kelasId;
    }
    if ($tab !== '') {
        $q['tab'] = $tab;
    }
    foreach ($tambah as $k => $v) {
        if ((string) $v !== '') {
            $q[$k] = $v;
        }
    }
    return 'tugas.php?' . http_build_query($q);
}

/**
 * Jalankan halaman menu "Wali Kelas" (dipakai `tugas.php?kode=wali_kelas`).
 *
 * @param array<string,mixed> $tugas baris `tugas_menu` (judul mengikuti nama menu hasil ubah)
 */
function wali_halaman(array $tugas): void
{
    require_sekolah();

    $namaMenu = (string) ($tugas['nama'] ?? 'Wali Kelas');
    // Permintaan pemilik: kepala sekolah/madrasah & admin operator HANYA melihat + mencetak;
    // pengisian menu Tugas Tambahan dilakukan guru yang ditugaskan (lihat boleh_ubah_tugas_menu()).
    $bolehUbah = boleh_ubah_tugas_menu();
    if (!$bolehUbah && (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        tolak_ubah_tugas_menu('data Wali Kelas');
    }

    $daftarKelas = wali_kelas_daftar();
    $kelas = wali_kelas_konteks((int) ($_POST['kelas'] ?? $_GET['kelas'] ?? 0));

    // Guru yang belum ditetapkan sebagai wali kelas → kartu bantuan (bukan halaman kosong/403).
    if (!$kelas) {
        // Ada TIGA sebab yang mungkin, dan masing-masing butuh penjelasan berbeda supaya
        // penugasan yang sudah dilakukan tidak terlihat seperti "tidak berfungsi":
        //   (a) kelas perwaliannya ada tetapi sedang DINONAKTIFKAN;
        //   (b) menu sudah ditugaskan (Pengaturan Tugas Tambahan) tetapi kelasnya belum dipilih;
        //   (c) belum ditetapkan sebagai wali kelas sama sekali.
        $ditugaskan = tugas_ditugaskan((int) ($tugas['id'] ?? 0));
        $nonaktif = [];
        if (is_guru() && guru_id_saya() > 0) {
            foreach (guru_kelas_perwalian(guru_id_saya(), sekolah_id(), false) as $k) {
                if ((int) ($k['aktif'] ?? 0) !== 1) {
                    $nonaktif[] = (string) $k['nama'];
                }
            }
        }
        $judul = $nonaktif
            ? 'Kelas perwalian Anda sedang dinonaktifkan'
            : ($ditugaskan
                ? 'Menu ini sudah ditugaskan, tetapi kelas perwalian Anda belum ditetapkan'
                : 'Anda belum ditetapkan sebagai wali kelas');
        page_top($namaMenu, istilah_sekolah('Sekolah') . ' ' . tetap_kata(nama_sekolah_tampil()));
        ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('users', 16) ?> <?= e($namaMenu) ?></h2>
      <p>Lima laporan kelas: struktur organisasi, program kerja, absensi harian, jadwal piket, dan rekap nilai.</p>
    </div>
  </div>
  <div class="empty">
    <?= ikon('school', 34) ?>
    <h3><?= e($judul) ?></h3>
    <?php if ($nonaktif): ?>
      <p>Kelas <b><?= e(implode(', ', $nonaktif)) ?></b> adalah kelas perwalian Anda, tetapi kelas itu
        sedang <b>dinonaktifkan</b> sehingga datanya tidak ditampilkan. Aktifkan kembali kelasnya pada
        menu <b>Data Kelas</b>, lalu menu ini akan terisi kembali.</p>
    <?php elseif ($ditugaskan): ?>
      <p>Menu <b><?= e($namaMenu) ?></b> sudah ditugaskan kepada akun Anda, tetapi belum ada kelas
        yang ditetapkan sebagai perwalian Anda — jadi belum ada data kelas yang bisa ditampilkan.</p>
    <?php else: ?>
      <p>Menu ini menampilkan data <b>kelas perwalian Anda</b>.</p>
    <?php endif; ?>
    <p class="mut">Kelas perwalian ditetapkan bersama penugasan menunya oleh
      <b>Admin Operator</b> (atau Kepala Sekolah/Madrasah) pada menu
      <b>Pengaturan Tugas Tambahan</b> — bagian <b>Kelas Perwalian</b> — atau pada menu
      <b>Data Kelas</b> kolom <b>Wali Kelas</b>. Setelah kelasnya ditetapkan, seluruh laporan kelas ini
      langsung dapat Anda isi dan cetak.</p>
    <?php if (boleh_master()): ?>
      <div class="flex" style="justify-content:center">
        <a class="btn btn-primary" href="tugas_pengguna.php"><?= ikon('users', 16) ?> Buka Pengaturan Tugas Tambahan</a>
        <a class="btn btn-ghost" href="kelas.php"><?= ikon('school', 16) ?> Buka Data Kelas</a>
      </div>
    <?php endif; ?>
  </div>
</div>
        <?php
        page_bottom();
        return;
    }

    $kelasId = (int) $kelas['id'];
    $tab = (string) ($_GET['tab'] ?? 'struktur');
    // v1.69.0: tab KEEMPAT "Rekap Nilai" (rekap seluruh mata pelajaran + Leger) — permintaan pemilik.
    if (!in_array($tab, ['struktur', 'program', 'absensi', 'piket', 'nilai'], true)) {
        $tab = 'struktur';
    }
    $now = date('Y-m-d H:i:s');

    // ---------------------------------------------------------------- POST
    if ((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);

        if ($aksi === 'struktur_simpan') {
            $jabatan = wali_pangkas(trim((string) ($_POST['jabatan'] ?? '')), WALI_TEKS_MAKS);
            $nama = wali_pangkas(trim((string) ($_POST['nama'] ?? '')), WALI_TEKS_MAKS);
            $bagan = (int) ($_POST['bagan'] ?? 0);
            $bagan = ($bagan >= 1 && $bagan <= 7) ? $bagan : 0;
            if ($jabatan === '') {
                flash('Jabatan / nama seksi wajib diisi.', 'err');
                redirect(wali_url($kelasId, 'struktur', ['edit' => $id]) . '#formStruktur');
            }
            $b = wali_struktur_by_id($id, $kelasId);
            if ($b) {
                q('UPDATE wali_struktur SET jabatan = ?, nama = ?, bagan = ?, diubah = ?
                   WHERE id = ? AND sekolah_id = ? AND kelas_id = ?',
                    [$jabatan, $nama, $bagan, $now, $id, sekolah_id(), $kelasId]);
                flash('Baris "' . $jabatan . '" diperbarui.');
            } else {
                $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) + 10 FROM wali_struktur
                                     WHERE sekolah_id = ? AND kelas_id = ?', [sekolah_id(), $kelasId], 10);
                q('INSERT INTO wali_struktur (sekolah_id, kelas_id, jabatan, nama, bagan, urutan, dibuat)
                   VALUES (?,?,?,?,?,?,?)',
                    [sekolah_id(), $kelasId, $jabatan, $nama, $bagan, $urutan, $now]);
                flash('Baris "' . $jabatan . '" ditambahkan.');
            }
            redirect(wali_url($kelasId, 'struktur') . '#tabelStruktur');
        }

        if ($aksi === 'struktur_hapus') {
            $b = wali_struktur_by_id($id, $kelasId);
            if (!$b) {
                flash('Baris tidak ditemukan.', 'err');
                redirect(wali_url($kelasId, 'struktur'));
            }
            q('DELETE FROM wali_struktur WHERE id = ? AND sekolah_id = ? AND kelas_id = ?',
                [$id, sekolah_id(), $kelasId]);
            flash('Baris "' . $b['jabatan'] . '" dihapus.', 'warn');
            redirect(wali_url($kelasId, 'struktur') . '#tabelStruktur');
        }

        if ($aksi === 'struktur_geser') {
            $b = wali_struktur_by_id($id, $kelasId);
            if ($b && wali_struktur_geser($id, (string) ($_POST['arah'] ?? '') === 'naik' ? 'naik' : 'turun', $kelasId)) {
                flash('Urutan "' . $b['jabatan'] . '" dipindahkan.', 'warn');
            }
            redirect(wali_url($kelasId, 'struktur'));
        }

        if ($aksi === 'struktur_baku') {
            $n = wali_struktur_susun_baku($kelasId);
            flash($n > 0
                ? $n . ' jabatan baku ditambahkan — silakan isi nama pengurusnya.'
                : 'Seluruh jabatan baku sudah ada pada struktur organisasi kelas ini.', $n > 0 ? 'ok' : 'warn');
            redirect(wali_url($kelasId, 'struktur') . '#tabelStruktur');
        }

        if ($aksi === 'program_simpan') {
            $nama = wali_pangkas(trim((string) ($_POST['nama'] ?? '')), WALI_TEKS_MAKS);
            $waktu = tgl_impor($_POST['waktu'] ?? null) ?? '';
            $target = wali_pangkas(trim((string) ($_POST['target'] ?? '')), WALI_URAIAN_MAKS);
            $ta = wali_pangkas(trim((string) ($_POST['ta'] ?? '')), 40);
            $ter = wali_terlaksana_sah((string) ($_POST['terlaksana'] ?? ''));
            $ket = wali_pangkas(trim((string) ($_POST['keterangan'] ?? '')), WALI_URAIAN_MAKS);
            if ($nama === '') {
                flash('Nama Program wajib diisi.', 'err');
                redirect(wali_url($kelasId, 'program', ['edit' => $id]) . '#formProgramWali');
            }
            $b = wali_program_by_id($id, $kelasId);
            if ($b) {
                q('UPDATE wali_program SET nama = ?, waktu = ?, target = ?, ta = ?, terlaksana = ?,
                     keterangan = ?, diubah = ? WHERE id = ? AND sekolah_id = ? AND kelas_id = ?',
                    [$nama, $waktu, $target, $ta, $ter, $ket, $now, $id, sekolah_id(), $kelasId]);
                flash('Program "' . $nama . '" diperbarui.');
            } else {
                $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) + 10 FROM wali_program
                                     WHERE sekolah_id = ? AND kelas_id = ?', [sekolah_id(), $kelasId], 10);
                q('INSERT INTO wali_program (sekolah_id, kelas_id, nama, waktu, target, ta, terlaksana,
                     keterangan, urutan, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [sekolah_id(), $kelasId, $nama, $waktu, $target, $ta, $ter, $ket, $urutan, $now]);
                flash('Program "' . $nama . '" ditambahkan.');
            }
            redirect(wali_url($kelasId, 'program') . '#tabelProgramWali');
        }

        if ($aksi === 'program_hapus') {
            $b = wali_program_by_id($id, $kelasId);
            if (!$b) {
                flash('Program tidak ditemukan.', 'err');
                redirect(wali_url($kelasId, 'program'));
            }
            q('DELETE FROM wali_program WHERE id = ? AND sekolah_id = ? AND kelas_id = ?',
                [$id, sekolah_id(), $kelasId]);
            flash('Program "' . $b['nama'] . '" dihapus.', 'warn');
            redirect(wali_url($kelasId, 'program') . '#tabelProgramWali');
        }

        if ($aksi === 'absensi_simpan') {
            $tanggal = tgl_impor($_POST['tanggal'] ?? null);
            if ($tanggal === null) {
                flash('Tanggal absensi wajib diisi dengan benar.', 'err');
                redirect(wali_url($kelasId, 'absensi') . '#formAbsensiWali');
            }
            $catatan = wali_pangkas(trim((string) ($_POST['catatan'] ?? '')), WALI_URAIAN_MAKS);
            $status = is_array($_POST['st'] ?? null) ? $_POST['st'] : [];
            $konteks = konteks_ajaran();
            $sesi = wali_absensi_sesi($kelasId, $tanggal);
            db()->exec('BEGIN IMMEDIATE');
            try {
                if ($sesi) {
                    q('UPDATE wali_absensi SET catatan = ?, guru_id = ?, dibuat_oleh = ? WHERE id = ?',
                        [$catatan, guru_id_saya() ?: null, (int) (current_user()['id'] ?? 0), (int) $sesi['id']]);
                    $absensiId = (int) $sesi['id'];
                } else {
                    q('INSERT INTO wali_absensi (sekolah_id, kelas_id, tanggal, catatan, guru_id, ta_id,
                         semester_id, dibuat, dibuat_oleh) VALUES (?,?,?,?,?,?,?,?,?)',
                        [sekolah_id(), $kelasId, $tanggal, $catatan, guru_id_saya() ?: null,
                         (int) $konteks['ta']['id'], (int) $konteks['sem']['id'], $now,
                         (int) (current_user()['id'] ?? 0)]);
                    $absensiId = (int) db()->lastInsertId();
                }
                q('DELETE FROM wali_absensi_detail WHERE absensi_id = ?', [$absensiId]);
                foreach (wali_siswa($kelasId) as $s) {
                    $st = strtoupper((string) ($status[(string) $s['id']] ?? $status[(int) $s['id']] ?? 'H'));
                    if (!array_key_exists($st, status_hadir())) {
                        $st = 'H';
                    }
                    q('INSERT INTO wali_absensi_detail (absensi_id, siswa_id, status) VALUES (?,?,?)',
                        [$absensiId, (int) $s['id'], $st]);
                }
                db()->exec('COMMIT');
            } catch (Throwable $ex) {
                db()->exec('ROLLBACK');
                throw $ex;
            }
            flash('Absensi kelas ' . $kelas['nama'] . ' tanggal ' . tanggal_id($tanggal) . ' tersimpan.');
            redirect(wali_url($kelasId, 'absensi', ['tanggal' => $tanggal]) . '#tabelAbsensiWali');
        }

        if ($aksi === 'absensi_hapus') {
            $tanggal = (string) ($_POST['tanggal'] ?? '');
            $sesi = wali_absensi_sesi($kelasId, $tanggal);
            if (!$sesi) {
                flash('Catatan absensi tidak ditemukan.', 'err');
                redirect(wali_url($kelasId, 'absensi'));
            }
            q('DELETE FROM wali_absensi WHERE id = ? AND sekolah_id = ? AND kelas_id = ?',
                [(int) $sesi['id'], sekolah_id(), $kelasId]);
            flash('Catatan absensi ' . tanggal_id($tanggal) . ' dihapus.', 'warn');
            redirect(wali_url($kelasId, 'absensi') . '#tabelAbsensiWali');
        }

        if ($aksi === 'piket_simpan') {
            $sah = [];
            foreach (wali_siswa($kelasId) as $s) {
                $sah[(int) $s['id']] = true;
            }
            $jumlah = 0;
            db()->exec('BEGIN IMMEDIATE');
            try {
                foreach (wali_hari() as $h) {
                    $pilih = $_POST['piket'][$h] ?? [];
                    $pilih = is_array($pilih) ? $pilih : [];
                    q('DELETE FROM wali_piket WHERE sekolah_id = ? AND kelas_id = ? AND hari = ?',
                        [sekolah_id(), $kelasId, $h]);
                    $u = 0;
                    foreach ($pilih as $sid) {
                        $sid = (int) $sid;
                        // Hanya siswa kelas ini yang diterima (anti-IDOR).
                        if ($sid <= 0 || !isset($sah[$sid])) {
                            continue;
                        }
                        $u += 10;
                        q('INSERT OR IGNORE INTO wali_piket (sekolah_id, kelas_id, hari, siswa_id, urutan, dibuat)
                           VALUES (?,?,?,?,?,?)', [sekolah_id(), $kelasId, $h, $sid, $u, $now]);
                        $jumlah++;
                    }
                }
                db()->exec('COMMIT');
            } catch (Throwable $ex) {
                db()->exec('ROLLBACK');
                throw $ex;
            }
            flash('Jadwal piket kelas ' . $kelas['nama'] . ' tersimpan (' . $jumlah . ' penugasan).');
            redirect(wali_url($kelasId, 'piket') . '#formPiket');
        }

        redirect(wali_url($kelasId));
    }

    // ---------------------------------------------------------------- tampilan
    $siswa = wali_siswa($kelasId);
    $struktur = wali_struktur_list($kelasId);
    $program = wali_program_list($kelasId);
    $piket = wali_piket_jadwal($kelasId);
    $ringkasP = wali_ringkas_program($program);
    $baganSusun = wali_bagan_susun($struktur);
    $baganStat = wali_bagan_statistik($baganSusun);
    $tampilan = (string) ($_GET['tampilan'] ?? '');
    if (!in_array($tampilan, ['tabel', 'bagan'], true)) {
        $tampilan = 'tabel';
    }
    $editS = $tab === 'struktur' ? wali_struktur_by_id((int) ($_GET['edit'] ?? 0), $kelasId) : null;
    $editW = $tab === 'program' ? wali_program_by_id((int) ($_GET['edit'] ?? 0), $kelasId) : null;
    $pilihTab = $tab;
    $tanggalAbsen = tgl_impor($_GET['tanggal'] ?? null) ?? date('Y-m-d');
    $sesiAbsen = $tab === 'absensi' ? wali_absensi_sesi($kelasId, $tanggalAbsen) : null;
    $detailAbsen = $sesiAbsen ? wali_absensi_detail((int) $sesiAbsen['id']) : [];
    $daftarAbsen = $tab === 'absensi' ? wali_absensi_list($kelasId) : [];
    $rekapAbsen = $tab === 'absensi' ? wali_absensi_rekap($kelasId) : ['siswa' => [], 'hari' => 0, 'detail' => []];
    // v1.69.0: REKAP NILAI seluruh mata pelajaran (dibaca dari nilai yang diisi guru pengampu) pada
    // periode yang dipilih (bawaan: tahun ajaran & semester aktif).
    $periode = konteks_ajaran();
    $rekapNilai = $tab === 'nilai' ? wali_nilai_rekap($kelasId, (int) ($periode['ta']['id'] ?? 0),
        (int) ($periode['sem']['id'] ?? 0)) : ['mapel' => [], 'siswa' => [], 'rata_mapel' => [],
        'rata_kelas' => null, 'siswa_ternilai' => 0];
    $qKelas = $kelasId;
    $isGuru = is_guru();
    $waliNama = trim((string) (($kelas['wali_nama'] ?? '') ?: nama_guru_wali((int) ($kelas['wali_id'] ?? 0))));

    page_top($namaMenu, 'Lima laporan kelas &middot; ' . tetap_kata(nama_sekolah_tampil()));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('users', 16) ?> <?= e($namaMenu) ?></h2>
      <p>Lima laporan untuk kelas <b><?= e((string) $kelas['nama']) ?></b>:
        <b>Struktur Organisasi Kelas</b>, <b>Program Kerja</b>, <b>Absensi Kelas</b> (harian),
        <b>Jadwal Piket</b>, dan <b>Rekap Nilai</b> (leger seluruh mata pelajaran) — masing-masing
        dapat dicetak.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($siswa) ?> siswa</span>
      <span class="chip"><?= (int) $baganStat['nama'] ?> nama pada struktur</span>
      <span class="chip"><?= (int) $ringkasP['program'] ?> program kerja</span>
      <span class="chip"><?= (int) $rekapAbsen['hari'] ?> catatan absensi</span>
      <span class="chip"><?= count(wali_mapel_kelas($kelasId, (int) ($periode['ta']['id'] ?? 0), (int) ($periode['sem']['id'] ?? 0))) ?> mata pelajaran</span>
    </div>
  </div>

  <?php // Pemilih KELAS: guru hanya kelas perwaliannya (otomatis), pengelola boleh memilih. ?>
  <?php if (count($daftarKelas) > 1): ?>
    <form method="get" class="filterbar mb16">
      <input type="hidden" name="kode" value="<?= e(WALI_KODE_MENU) ?>">
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
      <div>
        <label class="f" for="kelas">Kelompok/Kelas <?= $isGuru ? 'perwalian' : 'yang dikelola' ?></label>
        <select id="kelas" name="kelas" data-auto>
          <?php foreach ($daftarKelas as $k): ?>
            <option value="<?= (int) $k['id'] ?>" <?= (int) $k['id'] === $kelasId ? 'selected' : '' ?>>
              <?= e((string) $k['nama']) ?><?= (int) $k['jml_siswa'] > 0 ? ' (' . (int) $k['jml_siswa'] . ' siswa)' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="flex" style="align-self:flex-end">
        <span class="hint">Wali Kelas: <b><?= $waliNama !== '' ? e($waliNama) : 'belum ditetapkan' ?></b></span>
      </div>
    </form>
  <?php else: ?>
    <div class="alert alert-ok" style="background:rgba(201,162,39,.07);border-color:rgba(201,162,39,.45);margin-bottom:16px">
      <?= ikon('school', 16) ?>
      <span>Kelas <b><?= e((string) $kelas['nama']) ?></b>
        <?= $waliNama !== '' ? ' — Wali Kelas: <b>' . e($waliNama) . '</b>' : '' ?>.
        <?= $isGuru ? 'Ini kelas perwalian Anda.' : 'Wali kelasnya ditetapkan lewat Data Kelas.' ?></span>
    </div>
  <?php endif; ?>

  <?php // ---------------------------------------------------------------- empat tab ?>
  <div class="chip-list warna mb16">
    <a class="chip <?= $tab === 'struktur' ? 'on' : '' ?>" href="<?= e(wali_url($kelasId, 'struktur')) ?>"><?= ikon('users', 14) ?> Struktur Organisasi</a>
    <a class="chip <?= $tab === 'program' ? 'on' : '' ?>" href="<?= e(wali_url($kelasId, 'program')) ?>"><?= ikon('calendar', 14) ?> Program Kerja</a>
    <a class="chip <?= $tab === 'absensi' ? 'on' : '' ?>" href="<?= e(wali_url($kelasId, 'absensi')) ?>"><?= ikon('clipboard', 14) ?> Absensi Kelas</a>
    <a class="chip <?= $tab === 'piket' ? 'on' : '' ?>" href="<?= e(wali_url($kelasId, 'piket')) ?>"><?= ikon('check', 14) ?> Jadwal Piket</a>
    <a class="chip <?= $tab === 'nilai' ? 'on' : '' ?>" href="<?= e(wali_url($kelasId, 'nilai')) ?>"><?= ikon('chart', 14) ?> Rekap Nilai</a>
  </div>

  <?php if (!$bolehUbah): ?>
    <div class="alert alert-warn" style="margin-bottom:16px">
      <?= ikon('eye', 16) ?>
      <span><?= pemberitahuan_hanya_lihat('data Wali Kelas') ?></span>
    </div>
  <?php endif; ?>

  <?php require __DIR__ . '/wali_tab.php'; ?>
</div>
    <?php
    page_bottom();
}
