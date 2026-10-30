<?php
/**
 * MODUL PEMBINA OSIM/OSIS, **PEMBINA PRAMUKA**, dan **PEMBINA PMR** — dua laporan per menu.
 *
 * Dipakai TIGA menu Tugas Tambahan (lihat OSIM_KODE_LIST di inc/config.php):
 *   - `osim`    → "Pembina OSIM/OSIS"  (jabatan baku: OSIM_JABATAN)
 *   - `pramuka` → "Pembina Pramuka"    (jabatan baku: PRAMUKA_JABATAN)
 *   - `pmr`     → "Pembina PMR"        (jabatan baku: PMR_JABATAN)
 * Permintaan pemilik: *"Pada menu pembina pramuka isi menu buat seperti menu Pembina Osim hanya saja
 * pembina osim ganti dengan pembina pramuka"* dan *"Tugas Tambahan Pembina PMR isi menu buat sama
 * seperti Pembina OSIM/OSIS"* — jadi ISI & FITURNYA SAMA (Susunan Organisasi + Program Kerja +
 * impor + ekspor + bagan + cetak); yang berbeda hanya nama menu, satu baris jabatan baku, judul
 * bawaan bagan, dan penyimpanan judul/periode bagan. **Jangan menyalin modul ini menjadi beberapa
 * berkas** — setiap fungsi menerima `$kode` (kode menu) dan memisahkan data per sekolah + per menu.
 *
 * Permintaan pemilik: *"Pada Menu pembina osim/osis isi dengan 'Laporan Susunan Organisasi' Mulai
 * Kepala Sekolah/Madrasah, Wakil Bidang Kesiswaan, Pembina OSIM/OSIS, Ketua OSIM/OSIS, Wakil Ketua
 * OSIM/OSIS, Sekretaris, Bendahara, Seksi-Seksi (tambahkan manual nama-nama Seksi), Anggota
 * OSIM/OSIS. Selain itu buat juga 'Laporan Program Kerja' dengan menu inputan: Nama Program, Waktu
 * Pelaksanaan, Target, Tahun Ajaran, Waktu Pelaksanaan, Terlaksana (Ya atau Tidak), Keterangan.
 * Tambahkan fitur impor dan ekspor dan juga cetak."*
 *
 * Pilihan pemilik: (1) Waktu Pelaksanaan = **tanggal lengkap** (hari/bulan/tahun);
 * (2) **tiap seksi & tiap anggota menjadi baris tersendiri** (nama seksi + nama pengurusnya, boleh
 * ditambah sebanyak apa pun); (3) kolom susunan organisasi = **Jabatan + Nama** saja.
 *
 * Aturan peran mengikuti modul Sarpras/Kesiswaan:
 *  - data PER SEKOLAH (`sekolah_id`), semua query dibatasi `sekolah_id()` (anti-IDOR);
 *  - **Super Admin, Kepala Sekolah/Madrasah, dan Admin Operator langsung LAPORAN**;
 *  - guru (dan peran lain yang ditugaskan) langsung tabel penginputan dan **boleh CRUD**;
 *  - **pengawas hanya lihat & cetak**.
 */
declare(strict_types=1);

// ------------------------------------------------------------------ konfigurasi per MENU

/** Kode menu yang sah untuk modul ini (OSIM/OSIS & Pramuka). */
function osim_kode_valid(string $kode): bool
{
    return in_array(trim($kode), OSIM_KODE_LIST, true);
}

/** Normalkan kode menu: yang tidak dikenal dianggap OSIM/OSIS (menu asli modul ini). */
function osim_kode_normal(string $kode): string
{
    $kode = trim($kode);
    return osim_kode_valid($kode) ? $kode : OSIM_KODE_MENU;
}

/**
 * Konfigurasi ISI satu menu (dipakai seluruh modul supaya kedua menu benar-benar sama perilakunya).
 *
 * @return array{kode:string,nama_organisasi:string,menu_bawaan:string,jabatan:array<int,string>,
 *               judul_bagan_bawaan:string,setting_judul:string,setting_periode:string}
 */
function osim_menu_konfig(string $kode = OSIM_KODE_MENU): array
{
    $kode = osim_kode_normal($kode);
    if ($kode === PMR_KODE_MENU) {
        return [
            'kode' => PMR_KODE_MENU,
            'nama_organisasi' => 'PMR',
            'menu_bawaan' => 'Pembina PMR',
            'jabatan' => PMR_JABATAN,
            'judul_bagan_bawaan' => 'Struktur Organisasi Palang Merah Remaja (PMR)',
            'setting_judul' => 'pmr_bagan_judul',
            'setting_periode' => 'pmr_bagan_periode',
        ];
    }
    if ($kode === PRAMUKA_KODE_MENU) {
        return [
            'kode' => PRAMUKA_KODE_MENU,
            'nama_organisasi' => 'Pramuka',
            'menu_bawaan' => 'Pembina Pramuka',
            'jabatan' => PRAMUKA_JABATAN,
            'judul_bagan_bawaan' => 'Struktur Organisasi Pramuka',
            'setting_judul' => 'pramuka_bagan_judul',
            'setting_periode' => 'pramuka_bagan_periode',
        ];
    }
    return [
        'kode' => OSIM_KODE_MENU,
        'nama_organisasi' => 'OSIM/OSIS',
        'menu_bawaan' => 'Pembina OSIM/OSIS',
        'jabatan' => OSIM_JABATAN,
        'judul_bagan_bawaan' => 'Struktur Organisasi Intra Sekolah (OSIS)',
        'setting_judul' => 'osim_bagan_judul',
        'setting_periode' => 'osim_bagan_periode',
    ];
}

// ------------------------------------------------------------------ bantuan umum

/** Pangkas teks dengan aman (mb_substr bila tersedia — runtime CLI bisa tanpa mbstring). */
function osim_pangkas(string $teks, int $maks): string
{
    if ($maks <= 0) {
        return '';
    }
    if (function_exists('mb_substr')) {
        return (string) mb_substr($teks, 0, $maks);
    }
    return substr($teks, 0, $maks);
}

/** Nilai sah untuk kolom `terlaksana` ('Ya'/'Tidak'/''). */
function osim_terlaksana_sah(string $nilai): string
{
    $v = trim($nilai);
    foreach (OSIM_TERLAKSANA as $o) {
        if (strcasecmp($o, $v) === 0) {
            return $o;
        }
    }
    return '';
}

/** Tanggal program untuk tampilan ('-' bila kosong). */
function osim_tanggal_label(?string $ymd): string
{
    return trim((string) $ymd) !== '' ? tanggal_id((string) $ymd, true) : '';
}

/** Daftar jabatan/seksi yang sudah dipakai sekolah ini (untuk saran pengetikan). */
function osim_jabatan_terpakai(string $kode = OSIM_KODE_MENU): array
{
    $keluar = [];
    foreach (all('SELECT DISTINCT jabatan FROM osim_pengurus
                  WHERE sekolah_id = ? AND kode = ? AND IFNULL(jabatan, "") <> "" ORDER BY urutan, jabatan',
                 [sekolah_id(), osim_kode_normal($kode)]) as $r) {
        $keluar[] = (string) $r['jabatan'];
    }
    // Jabatan tetap didahulukan agar selalu muncul sebagai saran.
    foreach (array_reverse(osim_menu_konfig($kode)['jabatan']) as $j) {
        if (!in_array($j, $keluar, true)) {
            array_unshift($keluar, $j);
        }
    }
    return $keluar;
}

/** Daftar Tahun Ajaran sekolah ini (untuk pilihan). */
function osim_ta_pilihan(): array
{
    return ta_list(false);
}

/** Tahun ajaran pilihan awal: yang aktif, atau yang terbaru. */
function osim_ta_awal(): string
{
    $ta = ta_aktif();
    if ($ta) {
        return (string) $ta['nama'];
    }
    $daftar = ta_list();
    return $daftar ? (string) $daftar[0]['nama'] : '';
}

/** Jumlah seksi pada susunan organisasi (jabatan berawalan "seksi"). */
function osim_jumlah_seksi(array $baris): int
{
    $n = 0;
    foreach ($baris as $r) {
        if (stripos(trim((string) $r['jabatan']), 'seksi') === 0) {
            $n++;
        }
    }
    return $n;
}

// ------------------------------------------------------------------ data: SUSUNAN ORGANISASI

/**
 * Daftar pengurus/seksi sekolah ini.
 *
 * @param array<string,string|int> $f filter: cari, jabatan, isi ('kosong' = nama belum diisi)
 * @return array<int,array<string,mixed>>
 */
function osim_pengurus_list(array $f = [], string $kode = OSIM_KODE_MENU): array
{
    $w = ['sekolah_id = ?', 'kode = ?'];
    $p = [sekolah_id(), osim_kode_normal($kode)];
    $cari = trim((string) ($f['cari'] ?? ''));
    if ($cari !== '') {
        $w[] = '(jabatan LIKE ? OR nama LIKE ?)';
        $p[] = '%' . $cari . '%';
        $p[] = '%' . $cari . '%';
    }
    $jabatan = trim((string) ($f['jabatan'] ?? ''));
    if ($jabatan !== '') {
        $w[] = 'jabatan = ?';
        $p[] = $jabatan;
    }
    if ((string) ($f['isi'] ?? '') === 'kosong') {
        $w[] = 'IFNULL(nama, "") = ""';
    }
    return all('SELECT * FROM osim_pengurus WHERE ' . implode(' AND ', $w) . ' ORDER BY urutan, id', $p);
}

function osim_pengurus_by_id(int $id, string $kode = OSIM_KODE_MENU): ?array
{
    if ($id <= 0) {
        return null;
    }
    return one('SELECT * FROM osim_pengurus WHERE id = ? AND sekolah_id = ? AND kode = ?',
        [$id, sekolah_id(), osim_kode_normal($kode)]);
}

/** Ringkasan susunan organisasi. */
function osim_ringkas_pengurus(array $baris): array
{
    $terisi = 0;
    $kosong = 0;
    foreach ($baris as $r) {
        if (trim((string) ($r['nama'] ?? '')) !== '') {
            $terisi++;
        } else {
            $kosong++;
        }
    }
    return ['baris' => count($baris), 'terisi' => $terisi, 'kosong' => $kosong,
            'seksi' => osim_jumlah_seksi($baris)];
}

/** Geser satu baris pengurus naik/turun, lalu rapikan nomor urut (10, 20, 30, …). */
function osim_pengurus_geser(int $id, string $arah, string $kode = OSIM_KODE_MENU): bool
{
    $baris = osim_pengurus_list([], $kode);
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
            q('UPDATE osim_pengurus SET urutan = ? WHERE id = ?', [$u, (int) $b['id']]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return true;
}

/**
 * Susun baris BAKU susunan organisasi (8 jabatan tetap) — nama dibiarkan kosong supaya pengelola
 * tinggal mengisi. Jabatan yang sudah ada tidak digandakan.
 *
 * @return int jumlah baris baru
 */
function osim_susun_baku(string $kode = OSIM_KODE_MENU): int
{
    $kode = osim_kode_normal($kode);
    $ada = [];
    foreach (osim_pengurus_list([], $kode) as $b) {
        $ada[strtolower(trim((string) $b['jabatan']))] = true;
    }
    $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) FROM osim_pengurus WHERE sekolah_id = ? AND kode = ?',
        [sekolah_id(), $kode], 0);
    $now = date('Y-m-d H:i:s');
    $tambah = 0;
    db()->exec('BEGIN IMMEDIATE');
    try {
        foreach (osim_menu_konfig($kode)['jabatan'] as $j) {
            if (isset($ada[strtolower($j)])) {
                $urutan += 10;
                continue;
            }
            $urutan += 10;
            q('INSERT INTO osim_pengurus (sekolah_id, kode, jabatan, nama, urutan, dibuat) VALUES (?,?,?,?,?,?)',
                [sekolah_id(), $kode, $j, '', $urutan, $now]);
            $tambah++;
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return $tambah;
}

// ------------------------------------------------------------------ BAGAN susunan organisasi

/**
 * Tingkat (baris) BAKU sebuah jabatan pada bagan susunan organisasi.
 *
 * Dipakai sebagai nilai OTOMATIS ketika kolom `osim_pengurus.bagan` bernilai 0, sehingga data yang
 * sudah ada langsung tampil rapi pada bagan tanpa perlu diatur satu per satu. Pemilik tetap dapat
 * menimpanya per baris (kolom "Baris bagan" pada formulir) — itulah bagian "kolom bagan dan isinya
 * bisa disesuaikan lagi".
 *
 * Urutan tingkat mengikuti contoh struktur organisasi pada umumnya:
 *   1 Kepala Sekolah/Madrasah · 2 Wakil Bidang Kesiswaan · 3 Pembina OSIM/OSIS ·
 *   4 Ketua · 5 Wakil Ketua · 6 Sekretaris & Bendahara · 7 Seksi/Divisi (beserta anggotanya) ·
 *   8 Anggota.
 * Jabatan yang tidak dikenali masuk tingkat 7 (kotak seksi/divisi) karena di situlah nama-nama
 * seksi bebas diisi pengguna.
 */
function osim_bagan_tingkat_baku(string $jabatan): int
{
    $j = strtolower(trim($jabatan));
    if ($j === '') {
        return 7;
    }
    // PENTING: "wakil ketua" & "wakil bidang" diperiksa LEBIH DULU daripada "ketua"/"bidang",
    // kalau tidak keduanya akan jatuh ke tingkat yang salah.
    if (strpos($j, 'wakil ketua') !== false) {
        return 5;
    }
    if (strpos($j, 'wakil') !== false && strpos($j, 'kesiswaan') !== false) {
        return 2;
    }
    if (strpos($j, 'wakil') !== false) {
        return 5;
    }
    if (strpos($j, 'kepala') !== false) {
        return 1;
    }
    if (strpos($j, 'kesiswaan') !== false) {
        return 2;
    }
    if (strpos($j, 'pembina') !== false) {
        return 3;
    }
    if (strpos($j, 'sekretaris') !== false || strpos($j, 'bendahara') !== false) {
        return 6;
    }
    if (strpos($j, 'ketua') !== false) {
        return 4;
    }
    if (strpos($j, 'anggota') !== false) {
        return 8;
    }
    return 7;
}

/** Tingkat bagan efektif satu baris: kolom `bagan` bila diisi (>0), kalau tidak otomatis. */
function osim_bagan_tingkat(array $baris): int
{
    $pilih = (int) ($baris['bagan'] ?? 0);
    if ($pilih >= 1 && $pilih <= 8) {
        return $pilih;
    }
    return osim_bagan_tingkat_baku((string) ($baris['jabatan'] ?? ''));
}

/** Judul pada kepala bagan (dapat diubah pengelola; kosong → memakai bawaan per MENU). */
function osim_bagan_judul(string $kode = OSIM_KODE_MENU): string
{
    $cfg = osim_menu_konfig($kode);
    $simpan = trim((string) setting($cfg['setting_judul']));
    return $simpan !== '' ? $simpan : $cfg['judul_bagan_bawaan'];
}

/** Periode pada kepala bagan (dapat diubah; kosong → memakai tahun ajaran aktif). */
function osim_bagan_periode(string $kode = OSIM_KODE_MENU): string
{
    $simpan = trim((string) setting(osim_menu_konfig($kode)['setting_periode']));
    if ($simpan !== '') {
        return $simpan;
    }
    $ta = ta_aktif();
    return $ta ? 'Periode ' . (string) $ta['nama'] : '';
}

/**
 * Susun daftar pengurus menjadi struktur BAGAN.
 *
 * Hasilnya: `tingkat` => daftar kotak; tiap kotak berisi `judul` (jabatan/seksi) + `nama` (daftar
 * nama di dalamnya). Baris berjabatan sama pada tingkat yang sama digabung menjadi SATU kotak
 * (itulah cara menambahkan anggota pada sebuah seksi: tambah baris dengan nama seksi yang sama).
 *
 * @param array<int,array<string,mixed>> $baris hasil `osim_pengurus_list()`
 * @return array<int,array<int,array{judul:string,nama:array<int,string>,baris:array<int,int>>>
 */
function osim_bagan_susun(array $baris): array
{
    $tingkat = [];
    foreach ($baris as $b) {
        $t = osim_bagan_tingkat($b);
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

/** Jumlah kotak & baris yang tampil pada bagan (untuk chip ringkasan). */
function osim_bagan_statistik(array $bagan): array
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
 * Markup BAGAN (HTML) — dipakai halaman Susunan Organisasi maupun lembar cetak.
 *
 * Strukturnya sengaja sederhana (div bersarang + garis penghubung CSS) supaya tampil sama pada
 * layar dan saat dicetak, tanpa pustaka luar. Layout:
 *   - kepala bagan: judul + nama sekolah + periode;
 *   - kotak tingkat 1..6 ditumpuk tegak (satu kotak per tingkat);
 *   - tingkat 7 (seksi/divisi): kotak berjajar, tiap kotak memuat judul seksi + Kadiv + anggotanya;
 *   - tingkat 8 (anggota): satu kotak di bawah.
 *
 * @param array<int,array<string,mixed>> $pengurus hasil `osim_pengurus_list()`
 * @param bool $tanpaKepala true = tanpa kepala bagan (dipakai bila lembar cetak sudah punya kop)
 */
function osim_bagan_html(array $pengurus, bool $tanpaKepala = false, string $kode = OSIM_KODE_MENU): string
{
    $bagan = osim_bagan_susun($pengurus);
    if (!$bagan) {
        return '<div class="osim-bagan-kosong">Belum ada susunan organisasi untuk digambar.</div>';
    }
    $kepala = '';
    if (!$tanpaKepala) {
        $periode = osim_bagan_periode($kode);
        $kepala = '<div class="osim-bagan-kepala">'
            . '<div class="osim-bagan-kepala-judul">' . e(osim_bagan_judul($kode)) . '</div>'
            . '<div class="osim-bagan-kepala-sekolah">' . tetap_kata(nama_sekolah_tampil()) . '</div>'
            . ($periode !== '' ? '<div class="osim-bagan-kepala-periode">' . e($periode) . '</div>' : '')
            . '</div>';
    }
    $html = '<div class="osim-bagan">' . $kepala;
    foreach ($bagan as $tingkat => $kotak) {
        $banyak = count($kotak) > 1;
        $html .= '<div class="osim-bagan-baris' . ($banyak ? ' banyak' : '') . '" data-tingkat="' . (int) $tingkat . '">';
        foreach ($kotak as $b) {
            $html .= '<div class="osim-bagan-kotak">'
                . '<div class="osim-bagan-jabatan">' . e((string) $b['judul']) . '</div>';
            if ($b['nama']) {
                // Kotak seksi/anggota memuat beberapa nama: yang pertama diberi label "Kadiv"
                // (ketua seksi), sisanya menjadi anggotanya.
                $labelKadiv = ((int) $tingkat === 7 && count($b['nama']) > 1);
                foreach (array_values($b['nama']) as $i => $nm) {
                    $kls = 'osim-bagan-nama';
                    if ($labelKadiv && $i === 0) {
                        $html .= '<div class="osim-bagan-kadiv-label">Kadiv:</div>';
                    }
                    $html .= '<div class="' . $kls . '">' . e((string) $nm) . '</div>';
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

// ------------------------------------------------------------------ data: PROGRAM KERJA

/**
 * Daftar program kerja OSIM/OSIS sekolah ini.
 *
 * @param array<string,string|int> $f filter: cari, ta, terlaksana, dari, sampai
 * @return array<int,array<string,mixed>>
 */
function osim_program_list(array $f = [], string $kode = OSIM_KODE_MENU): array
{
    $w = ['sekolah_id = ?', 'kode = ?'];
    $p = [sekolah_id(), osim_kode_normal($kode)];
    $cari = trim((string) ($f['cari'] ?? ''));
    if ($cari !== '') {
        $w[] = '(nama LIKE ? OR target LIKE ? OR keterangan LIKE ?)';
        $p[] = '%' . $cari . '%';
        $p[] = '%' . $cari . '%';
        $p[] = '%' . $cari . '%';
    }
    $ta = trim((string) ($f['ta'] ?? ''));
    if ($ta !== '') {
        $w[] = 'ta = ?';
        $p[] = $ta;
    }
    $ter = osim_terlaksana_sah((string) ($f['terlaksana'] ?? ''));
    if ($ter !== '') {
        $w[] = 'terlaksana = ?';
        $p[] = $ter;
    }
    $dari = tgl_ok($f['dari'] ?? null);
    if ($dari !== null) {
        $w[] = 'waktu IS NOT NULL AND waktu <> "" AND date(waktu) >= date(?)';
        $p[] = $dari;
    }
    $sampai = tgl_ok($f['sampai'] ?? null);
    if ($sampai !== null) {
        $w[] = 'waktu IS NOT NULL AND waktu <> "" AND date(waktu) <= date(?)';
        $p[] = $sampai;
    }
    return all('SELECT * FROM osim_program WHERE ' . implode(' AND ', $w) . ' ORDER BY urutan, id', $p);
}

function osim_program_by_id(int $id, string $kode = OSIM_KODE_MENU): ?array
{
    if ($id <= 0) {
        return null;
    }
    return one('SELECT * FROM osim_program WHERE id = ? AND sekolah_id = ? AND kode = ?',
        [$id, sekolah_id(), osim_kode_normal($kode)]);
}

/** Ringkasan program kerja: jumlah, terlaksana, belum, dan yang belum bertanggal. */
function osim_ringkas_program(array $baris): array
{
    $ya = 0; $tidak = 0; $belum = 0;
    foreach ($baris as $r) {
        $t = osim_terlaksana_sah((string) ($r['terlaksana'] ?? ''));
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

/** Rekap sebuah kolom (`ta`/`terlaksana`) → [nilai => jumlah program]. */
function osim_rekap(array $baris, string $kolom): array
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

/** URL filter aktif (bagian + saringan) agar ekspor/cetak mengikuti yang sedang dilihat. */
function osim_query(array $f): string
{
    $q = [];
    foreach (['cari', 'jabatan', 'isi', 'ta', 'terlaksana', 'dari', 'sampai'] as $k) {
        $v = trim((string) ($f[$k] ?? ''));
        if ($v !== '') {
            $q[$k] = $v;
        }
    }
    return http_build_query($q);
}

/** Label ringkas saringan aktif (untuk halaman ekspor & lembar cetak). */
function osim_label_filter(array $f, string $bagian): array
{
    $label = [];
    if ($bagian === 'organisasi') {
        if (trim((string) ($f['jabatan'] ?? '')) !== '') {
            $label[] = 'Jabatan/Seksi: ' . $f['jabatan'];
        }
        if ((string) ($f['isi'] ?? '') === 'kosong') {
            $label[] = 'Hanya yang belum diisi namanya';
        }
    } else {
        if (trim((string) ($f['ta'] ?? '')) !== '') {
            $label[] = 'Tahun Ajaran: ' . $f['ta'];
        }
        if (trim((string) ($f['terlaksana'] ?? '')) !== '') {
            $label[] = 'Terlaksana: ' . $f['terlaksana'];
        }
        if (trim((string) ($f['dari'] ?? '')) !== '') {
            $label[] = 'Dari: ' . tanggal_id((string) $f['dari']);
        }
        if (trim((string) ($f['sampai'] ?? '')) !== '') {
            $label[] = 'Sampai: ' . tanggal_id((string) $f['sampai']);
        }
    }
    if (trim((string) ($f['cari'] ?? '')) !== '') {
        $label[] = 'Kata kunci: ' . $f['cari'];
    }
    return $label;
}

// ------------------------------------------------------------------ halaman

/**
 * Jalankan halaman salah satu menu modul ini
 * (`tugas.php?kode=osim` / `?kode=pramuka` / `?kode=pmr`).
 *
 * @param array<string,mixed> $tugas baris `tugas_menu`; `kode`-nya menentukan jabatan baku,
 *                                   judul bawaan bagan, dan penyimpanan judul/periode bagan
 */
function osim_halaman(array $tugas): void
{
    require_sekolah();

    // Kode menu dari baris `tugas_menu` — inilah yang membedakan menu OSIM dengan menu Pramuka
    // (isi & fiturnya sama; lihat osim_menu_konfig()).
    $kode = osim_kode_normal((string) ($tugas['kode'] ?? OSIM_KODE_MENU));
    $cfg = osim_menu_konfig($kode);
    $istilah = (string) $cfg['nama_organisasi'];
    $namaMenu = (string) ($tugas['nama'] ?? $cfg['menu_bawaan']);
    $peranPengelola = tugas_menu_semua_peran();
    // Permintaan pemilik: kepala sekolah/madrasah & admin operator HANYA melihat + mencetak;
    // pengisian menu Tugas Tambahan dilakukan guru yang ditugaskan (lihat boleh_ubah_tugas_menu()).
    $bolehUbah = boleh_ubah_tugas_menu();
    if (!$bolehUbah && $_SERVER['REQUEST_METHOD'] === 'POST') {
        tolak_ubah_tugas_menu('susunan organisasi & program kerja');
    }

    $bagian = (string) ($_GET['bagian'] ?? '');
    if (!in_array($bagian, ['organisasi', 'program'], true)) {
        $bagian = 'organisasi';
    }

    // ---------------------------------------------------------------- POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $now = date('Y-m-d H:i:s');

        if ($aksi === 'pengurus_simpan') {
            $jabatan = osim_pangkas(trim((string) ($_POST['jabatan'] ?? '')), OSIM_TEKS_MAKS);
            $nama = osim_pangkas(trim((string) ($_POST['nama'] ?? '')), OSIM_TEKS_MAKS);
            // "Baris bagan": 0 = otomatis dari jabatan (lihat osim_bagan_tingkat_baku()).
            $baganPilih = (int) ($_POST['bagan'] ?? 0);
            $baganPilih = ($baganPilih >= 1 && $baganPilih <= 8) ? $baganPilih : 0;
            if ($jabatan === '') {
                flash('Jabatan / nama seksi wajib diisi.', 'err');
                redirect('tugas.php?kode=' . e($kode) . '&bagian=organisasi&tab=data'
                    . ($id > 0 ? '&edit=' . $id : '') . '#formPengurus');
            }
            $b = osim_pengurus_by_id($id, $kode);
            if ($b) {
                q('UPDATE osim_pengurus SET jabatan = ?, nama = ?, bagan = ?, diubah = ?
                   WHERE id = ? AND sekolah_id = ? AND kode = ?',
                    [$jabatan, $nama, $baganPilih, $now, $id, sekolah_id(), $kode]);
                flash('Baris "' . $jabatan . '" diperbarui.');
            } else {
                $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) + 10 FROM osim_pengurus
                                     WHERE sekolah_id = ? AND kode = ?', [sekolah_id(), $kode], 10);
                q('INSERT INTO osim_pengurus (sekolah_id, kode, jabatan, nama, bagan, urutan, dibuat)
                   VALUES (?,?,?,?,?,?,?)',
                    [sekolah_id(), $kode, $jabatan, $nama, $baganPilih, $urutan, $now]);
                flash('Baris "' . $jabatan . '" ditambahkan.');
            }
            redirect('tugas.php?kode=' . e($kode) . '&bagian=organisasi&tab=data'
                . ($peranPengelola ? '#tabelPengurus' : ''));
        }

        if ($aksi === 'pengurus_hapus') {
            $b = osim_pengurus_by_id($id, $kode);
            if (!$b) {
                flash('Baris tidak ditemukan.', 'err');
                redirect('tugas.php?kode=' . e($kode) . '&bagian=organisasi');
            }
            q('DELETE FROM osim_pengurus WHERE id = ? AND sekolah_id = ? AND kode = ?',
                [$id, sekolah_id(), $kode]);
            flash('Baris "' . $b['jabatan'] . '" dihapus.', 'warn');
            redirect('tugas.php?kode=' . e($kode) . '&bagian=organisasi&tab=data');
        }

        if ($aksi === 'pengurus_geser') {
            $b = osim_pengurus_by_id($id, $kode);
            if ($b && osim_pengurus_geser($id, (string) ($_POST['arah'] ?? '') === 'naik' ? 'naik' : 'turun', $kode)) {
                flash('Urutan "' . $b['jabatan'] . '" dipindahkan.', 'warn');
            }
            redirect('tugas.php?kode=' . e($kode) . '&bagian=organisasi&tab=data');
        }

        if ($aksi === 'bagan_simpan') {
            // Judul & periode pada kepala bagan — bagian "kolom bagan dan isinya bisa disesuaikan".
            $judul = osim_pangkas(trim((string) ($_POST['judul'] ?? '')), 160);
            $periode = osim_pangkas(trim((string) ($_POST['periode'] ?? '')), 80);
            set_setting($cfg['setting_judul'], $judul);
            set_setting($cfg['setting_periode'], $periode);
            flash('Judul & periode bagan disimpan. Kosongkan bila ingin memakai bawaan (judul tetap + tahun ajaran aktif).');
            redirect('tugas.php?kode=' . e($kode) . '&bagian=organisasi&tampilan=bagan#formBagan');
        }

        if ($aksi === 'susun_baku') {
            $n = osim_susun_baku($kode);
            flash($n > 0
                ? $n . ' jabatan baku ditambahkan — silakan isi nama pengurusnya.'
                : 'Seluruh jabatan baku sudah ada pada susunan organisasi.', $n > 0 ? 'ok' : 'warn');
            redirect('tugas.php?kode=' . e($kode) . '&bagian=organisasi&tab=data');
        }

        if ($aksi === 'program_simpan') {
            $nama = osim_pangkas(trim((string) ($_POST['nama'] ?? '')), OSIM_TEKS_MAKS);
            $waktu = tgl_impor($_POST['waktu'] ?? null) ?? '';
            $target = osim_pangkas(trim((string) ($_POST['target'] ?? '')), OSIM_URAIAN_MAKS);
            $ta = osim_pangkas(trim((string) ($_POST['ta'] ?? '')), 40);
            $ter = osim_terlaksana_sah((string) ($_POST['terlaksana'] ?? ''));
            $ket = osim_pangkas(trim((string) ($_POST['keterangan'] ?? '')), OSIM_URAIAN_MAKS);
            if ($nama === '') {
                flash('Nama Program wajib diisi.', 'err');
                redirect('tugas.php?kode=' . e($kode) . '&bagian=program&tab=data'
                    . ($id > 0 ? '&edit=' . $id : '') . '#formProgramOsim');
            }
            $b = osim_program_by_id($id, $kode);
            if ($b) {
                q('UPDATE osim_program SET nama = ?, waktu = ?, target = ?, ta = ?, terlaksana = ?,
                     keterangan = ?, diubah = ? WHERE id = ? AND sekolah_id = ? AND kode = ?',
                    [$nama, $waktu, $target, $ta, $ter, $ket, $now, $id, sekolah_id(), $kode]);
                flash('Program "' . $nama . '" diperbarui.');
            } else {
                $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) + 10 FROM osim_program
                                     WHERE sekolah_id = ? AND kode = ?', [sekolah_id(), $kode], 10);
                q('INSERT INTO osim_program (sekolah_id, kode, nama, waktu, target, ta, terlaksana,
                     keterangan, urutan, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [sekolah_id(), $kode, $nama, $waktu, $target, $ta, $ter, $ket, $urutan, $now]);
                flash('Program "' . $nama . '" ditambahkan.');
            }
            redirect('tugas.php?kode=' . e($kode) . '&bagian=program&tab=data'
                . ($peranPengelola ? '#tabelProgramOsim' : ''));
        }

        if ($aksi === 'program_hapus') {
            $b = osim_program_by_id($id, $kode);
            if (!$b) {
                flash('Program tidak ditemukan.', 'err');
                redirect('tugas.php?kode=' . e($kode) . '&bagian=program');
            }
            q('DELETE FROM osim_program WHERE id = ? AND sekolah_id = ? AND kode = ?',
                [$id, sekolah_id(), $kode]);
            flash('Program "' . $b['nama'] . '" dihapus.', 'warn');
            redirect('tugas.php?kode=' . e($kode) . '&bagian=program&tab=data');
        }

        redirect('tugas.php?kode=' . e($kode));
    }

    // ---------------------------------------------------------------- tampilan
    $tab = (string) ($_GET['tab'] ?? '');
    if (!in_array($tab, ['data', 'laporan'], true)) {
        $tab = $peranPengelola ? 'laporan' : 'data';
    }
    if (!$bolehUbah) {
        $tab = 'laporan';   // pengawas selalu laporan
    }

    // Filter (kedua bagian memakai satu array; hanya yang relevan yang dipakai).
    $f = [
        'cari' => (string) ($_GET['cari'] ?? ''),
        'jabatan' => (string) ($_GET['jabatan'] ?? ''),
        'isi' => (string) ($_GET['isi'] ?? ''),
        'ta' => (string) ($_GET['ta'] ?? ''),
        'terlaksana' => (string) ($_GET['terlaksana'] ?? ''),
        'dari' => (string) ($_GET['dari'] ?? ''),
        'sampai' => (string) ($_GET['sampai'] ?? ''),
    ];
    $qFilter = osim_query($f);
    $qBagian = 'tugas.php?kode=' . e($kode) . '&bagian=' . $bagian;
    $qLink = $qBagian . ($qFilter !== '' ? '&' . e($qFilter) : '');
    $qTautan = 'kode=' . e($kode) . '&bagian=' . $bagian . ($qFilter !== '' ? '&' . e($qFilter) : '');

    $pengurus = $bagian === 'organisasi' ? osim_pengurus_list($f, $kode) : [];
    $program = $bagian === 'program' ? osim_program_list($f, $kode) : [];
    $ringkasP = osim_ringkas_pengurus($pengurus);
    $ringkasW = osim_ringkas_program($program);
    // Bentuk tampilan susunan organisasi: Tabel (bawaan) atau Bagan (permintaan pemilik).
    $tampilan = (string) ($_GET['tampilan'] ?? '');
    if (!in_array($tampilan, ['tabel', 'bagan'], true)) {
        $tampilan = 'tabel';
    }
    // Bagan memakai SELURUH baris (bukan hasil saringan) supaya strukturnya utuh.
    $baganSusun = $bagian === 'organisasi' ? osim_bagan_susun(osim_pengurus_list([], $kode)) : [];
    $baganStat = osim_bagan_statistik($baganSusun);
    $editP = $bagian === 'organisasi' ? osim_pengurus_by_id((int) ($_GET['edit'] ?? 0), $kode) : null;
    $editW = $bagian === 'program' ? osim_program_by_id((int) ($_GET['edit'] ?? 0), $kode) : null;
    $taPilihan = osim_ta_pilihan();
    $jabatanSaran = osim_jabatan_terpakai($kode);

    page_top($namaMenu, 'Laporan Susunan Organisasi & Program Kerja ' . $istilah);
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('star', 16) ?> <?= e($namaMenu) ?></h2>
      <p>Dua laporan untuk <b><?= tetap_kata(nama_sekolah_tampil()) ?></b>:
        <b>Susunan Organisasi</b> (jabatan &amp; nama pengurus) dan <b>Program Kerja</b> <?= e($istilah) ?>.</p>
    </div>
    <div class="flex">
      <?php if ($bagian === 'organisasi'): ?>
        <span class="chip"><?= (int) $ringkasP['baris'] ?> baris</span>
        <span class="chip"><?= (int) $ringkasP['seksi'] ?> seksi</span>
      <?php else: ?>
        <span class="chip"><?= (int) $ringkasW['program'] ?> program</span>
        <span class="chip"><?= (int) $ringkasW['ya'] ?> terlaksana</span>
      <?php endif; ?>
      <?php if ($bolehUbah): ?>
        <a class="btn btn-ghost btn-sm" href="osim_import.php?kode=<?= e($kode) ?>&bagian=<?= e($bagian) ?>"><?= ikon('upload', 15) ?> Impor Excel/CSV</a>
        <a class="btn btn-ghost btn-sm" href="osim_export.php?<?= e($qTautan) ?>"><?= ikon('doc', 15) ?> Ekspor</a>
      <?php endif; ?>
      <?php if ($bagian === 'organisasi') : ?>
        <a class="btn btn-ghost btn-sm" href="<?= $tampilan === 'bagan' ? 'cetak_osim_bagan.php' : 'cetak_osim_organisasi.php' ?><?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>" target="_blank"><?= ikon('print', 15) ?> Cetak <?= $tampilan === 'bagan' ? 'Bagan' : 'Tabel' ?></a>
      <?php else : ?>
        <a class="btn btn-ghost btn-sm" href="cetak_osim_program.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>" target="_blank"><?= ikon('print', 15) ?> Cetak</a>
      <?php endif; ?>
    </div>
  </div>

  <?php // Pengalih DUA laporan (Susunan Organisasi / Program Kerja). ?>
  <div class="chip-list warna mb16">
    <a class="chip <?= $bagian === 'organisasi' ? 'on' : '' ?>" href="tugas.php?kode=<?= e($kode) ?>&bagian=organisasi&tab=<?= e($tab) ?>"><?= ikon('users', 14) ?> Susunan Organisasi</a>
    <a class="chip <?= $bagian === 'program' ? 'on' : '' ?>" href="tugas.php?kode=<?= e($kode) ?>&bagian=program&tab=<?= e($tab) ?>"><?= ikon('calendar', 14) ?> Program Kerja</a>
  </div>

  <?php if ($bagian === 'organisasi'): ?>
    <?php // Bentuk tampilan susunan organisasi: TABEL (untuk menyunting) atau BAGAN (untuk dilihat/dicetak). ?>
    <div class="chip-list warna mb16">
      <a class="chip <?= $tampilan === 'tabel' ? 'on' : '' ?>" href="<?= $qBagian ?>&tampilan=tabel&tab=<?= e($tab) ?>"><?= ikon('clipboard', 14) ?> Tabel</a>
      <a class="chip <?= $tampilan === 'bagan' ? 'on' : '' ?>" href="<?= $qBagian ?>&tampilan=bagan"><?= ikon('chart', 14) ?> Bagan</a>
    </div>
  <?php endif; ?>

  <?php if ($bolehUbah && $tampilan === 'tabel'): ?>
    <div class="chip-list warna mb16">
      <a class="chip <?= $tab === 'laporan' ? 'on' : '' ?>" href="<?= $qBagian ?>&tab=laporan"><?= ikon('chart', 14) ?> Laporan</a>
      <a class="chip <?= $tab === 'data' ? 'on' : '' ?>" href="<?= $qBagian ?>&tab=data"><?= ikon('clipboard', 14) ?> Data (Input)</a>
    </div>
  <?php else: ?>
    <div class="alert alert-warn" style="margin-bottom:16px">
      <?= ikon('eye', 16) ?>
      <span><?= pemberitahuan_hanya_lihat('laporan ' . $istilah) ?></span>
    </div>
  <?php endif; ?>

  <?php // ============================================================ SUSUNAN ORGANISASI ?>
  <?php if ($bagian === 'organisasi'): ?>

    <?php if ($tampilan === 'bagan'): ?>
      <?php // ---- BAGAN (permintaan pemilik: susunan organisasi juga dalam bentuk bagan) ?>
      <div class="grid g4" style="gap:16px">
        <div class="card stat card-tight" style="margin:0">
          <div class="stat-ic g"><?= ikon('users', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $baganStat['nama'] ?></div><div class="stat-lbl">Nama pada bagan</div></div>
        </div>
        <div class="card stat card-tight" style="margin:0">
          <div class="stat-ic"><?= ikon('folder', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $baganStat['kotak'] ?></div><div class="stat-lbl">Kotak jabatan/seksi</div></div>
        </div>
        <div class="card stat card-tight" style="margin:0">
          <div class="stat-ic o"><?= ikon('chart', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $baganStat['tingkat'] ?></div><div class="stat-lbl">Tingkat/baris</div></div>
        </div>
        <div class="card stat card-tight" style="margin:0">
          <div class="stat-ic p"><?= ikon('calendar', 18) ?></div>
          <div><div class="stat-num" style="font-size:1rem"><?= e(osim_bagan_periode($kode) !== '' ? osim_bagan_periode($kode) : '—') ?></div>
            <div class="stat-lbl">Periode bagan</div></div>
        </div>
      </div>

      <div class="card mt16">
        <div class="card-head">
          <div><h2><?= ikon('chart', 16) ?> Bagan <?= e(osim_bagan_judul($kode)) ?></h2>
            <p>Digambar dari baris <b>Susunan Organisasi</b>. Bagan dapat digeser mendatar di layar
              sempit; gunakan <b>Cetak Bagan</b> untuk hasil A4.</p></div>
          <div class="flex">
            <a class="btn btn-ghost btn-sm" href="cetak_osim_bagan.php" target="_blank"><?= ikon('print', 15) ?> Cetak Bagan</a>
            <?php if ($bolehUbah): ?>
              <a class="btn btn-ghost btn-sm" href="<?= $qBagian ?>&tampilan=tabel&tab=data"><?= ikon('edit', 15) ?> Atur Baris & Nama</a>
            <?php endif; ?>
          </div>
        </div>
        <div class="osim-bagan-luapan">
          <?= osim_bagan_html(osim_pengurus_list([], $kode), false, $kode) ?>
        </div>
      </div>

      <?php if ($bolehUbah): ?>
        <?php // "Kolom bagan dan isinya bisa disesuaikan": judul & periode kepala bagan dapat diubah. ?>
        <div class="card" id="formBagan">
          <div class="card-head">
            <div><h2><?= ikon('slider', 16) ?> Sesuaikan Judul &amp; Periode Bagan</h2>
              <p>Kosongkan isian bila ingin memakai bawaan (judul tetap + tahun ajaran aktif).</p></div>
          </div>
          <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="bagan_simpan">
            <div>
              <label class="f" for="judulBagan">Judul bagan</label>
              <input type="text" id="judulBagan" name="judul" maxlength="160"
                     value="<?= e((string) setting($cfg['setting_judul'])) ?>"
                     placeholder="<?= e((string) $cfg['judul_bagan_bawaan']) ?>">
            </div>
            <div>
              <label class="f" for="periodeBagan">Periode</label>
              <input type="text" id="periodeBagan" name="periode" maxlength="80"
                     value="<?= e((string) setting($cfg['setting_periode'])) ?>"
                     placeholder="mis. Periode 2026/2027">
              <div class="hint">Bawaan: <?= e(osim_bagan_periode($kode) !== '' ? osim_bagan_periode($kode) : 'belum ada tahun ajaran aktif') ?></div>
            </div>
            <div class="flex">
              <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Judul &amp; Periode</button>
            </div>
          </form>
        </div>

        <div class="card">
          <div class="card-head">
            <div><h2><?= ikon('clipboard', 16) ?> Baris pada Bagan (urutan tingkat)</h2>
              <p>Tiap baris susunan organisasi menempati <b>tingkat</b> bagan. Nilai bawaan dihitung
                otomatis dari jabatannya, dan dapat diubah pada formulir (kolom <b>Baris bagan</b>) —
                itulah cara menyesuaikan letak kotak pada bagan.</p></div>
          </div>
          <div class="table-wrap">
            <table class="tbl">
              <thead><tr><th class="ctr" style="width:60px">Tingkat</th><th>Jabatan / Nama Seksi</th>
                <th class="ctr">Jumlah nama</th><th class="ctr" style="width:120px">Tindakan</th></tr></thead>
              <tbody>
              <?php foreach ($baganSusun as $t => $kotak): ?>
                <?php foreach ($kotak as $b): ?>
                  <tr>
                    <td class="ctr"><span class="pill pill-B"><?= (int) $t ?></span></td>
                    <td><b><?= e((string) $b['judul']) ?></b></td>
                    <td class="ctr"><?= count($b['nama']) ?></td>
                    <td class="ctr">
                      <a class="btn btn-ghost btn-sm" href="<?= $qBagian ?>&tampilan=tabel&tab=data&edit=<?= (int) $b['baris'][0] ?>#formPengurus"><?= ikon('edit', 14) ?> Ubah</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    <?php elseif ($tab === 'laporan'): ?>
      <div class="grid g4" style="gap:16px">
        <div class="card stat card-tight" style="margin:0">
          <div class="stat-ic g"><?= ikon('users', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasP['terisi'] ?></div><div class="stat-lbl">Pengurus terisi</div></div>
        </div>
        <div class="card stat card-tight" style="margin:0">
          <div class="stat-ic"><?= ikon('folder', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasP['seksi'] ?></div><div class="stat-lbl">Seksi</div></div>
        </div>
        <div class="card stat card-tight" style="margin:0">
          <div class="stat-ic o"><?= ikon('clipboard', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasP['baris'] ?></div><div class="stat-lbl">Baris jabatan/seksi</div></div>
        </div>
        <div class="card stat card-tight" style="margin:0">
          <div class="stat-ic p"><?= ikon('alert', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasP['kosong'] ?></div><div class="stat-lbl">Nama belum diisi</div></div>
        </div>
      </div>

      <?php if (!$pengurus): ?>
        <div class="empty mt16"><?= ikon('users', 34) ?>
          <h3>Belum ada susunan organisasi</h3>
          <p><?= $bolehUbah
            ? 'Isi lewat tab <b>Data (Input)</b>, tekan tombol <b>Susun Jabatan Baku</b>, atau impor dari Excel/CSV.'
            : pesan_kosong_hanya_lihat('susunan organisasi ' . $istilah) ?></p>
          <?php if ($bolehUbah): ?>
            <div class="flex" style="justify-content:center">
              <a class="btn btn-primary" href="<?= $qBagian ?>&tab=data#formPengurus"><?= ikon('plus', 16) ?> Input Susunan Organisasi</a>
              <a class="btn btn-ghost" href="osim_import.php?kode=<?= e($kode) ?>&bagian=organisasi"><?= ikon('upload', 16) ?> Impor Excel/CSV</a>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="divider"></div>
        <div class="section-title mb8"><?= ikon('users', 15) ?> Susunan Organisasi (<?= count($pengurus) ?> baris)</div>
        <?php osim_tabel_pengurus($pengurus, false, $peranPengelola && $bolehUbah, $kode); ?>
      <?php endif; ?>

    <?php else: ?>
      <div class="card" id="formPengurus" style="margin-bottom:18px">
        <div class="card-head">
          <div>
            <h2><?= ikon($editP ? 'edit' : 'plus', 16) ?> <?= $editP ? 'Ubah Jabatan / Seksi' : 'Tambah Jabatan / Seksi' ?></h2>
            <p>Satu baris = satu jabatan, satu seksi, atau satu anggota. Isi nama pengurusnya pada kolom
              <b>Nama</b> (boleh dikosongkan dulu).</p>
          </div>
          <?php if ($editP): ?><a class="btn btn-ghost btn-sm" href="<?= $qBagian ?>&tab=data"><?= ikon('prev', 14) ?> Batal</a><?php endif; ?>
        </div>
        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="pengurus_simpan">
          <input type="hidden" name="id" value="<?= (int) ($editP['id'] ?? 0) ?>">
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="baganP">Baris bagan (tingkat)</label>
              <?php // Kosong/otomatis = tingkat dihitung dari jabatan (lihat osim_bagan_tingkat_baku()). ?>
              <select id="baganP" name="bagan">
                <option value="0" <?= (int) ($editP['bagan'] ?? 0) === 0 ? 'selected' : '' ?>>Otomatis dari jabatan</option>
                <?php foreach ([1 => '1 — ' . $cfg['jabatan'][0], 2 => '2 — ' . $cfg['jabatan'][1],
                                3 => '3 — ' . $cfg['jabatan'][2], 4 => '4 — Ketua', 5 => '5 — Wakil Ketua',
                                6 => '6 — Sekretaris / Bendahara', 7 => '7 — Seksi / Divisi',
                                8 => '8 — Anggota'] as $angka => $ket): ?>
                  <option value="<?= (int) $angka ?>" <?= (int) ($editP['bagan'] ?? 0) === $angka ? 'selected' : '' ?>><?= e($ket) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="hint">Menentukan letak kotak pada <b>Bagan</b>. Biarkan otomatis bila ragu.</div>
            </div>
            <div>
              <label class="f" for="jabatan">Jabatan / Nama Seksi *</label>
              <input type="text" id="jabatan" name="jabatan" maxlength="<?= (int) OSIM_TEKS_MAKS ?>" required
                     list="saranJabatan" value="<?= e((string) ($editP['jabatan'] ?? '')) ?>"
                     placeholder="mis. <?= e((string) ($cfg['jabatan'][3] ?? 'Ketua')) ?> atau Seksi Keagamaan">
              <datalist id="saranJabatan">
                <?php foreach ($jabatanSaran as $j): ?><option value="<?= e($j) ?>"></option><?php endforeach; ?>
              </datalist>
              <div class="hint">Pilih jabatan baku dari daftar, atau tulis nama seksi sendiri
                (mis. <b>Seksi Keagamaan</b>, <b><?= e((string) end($cfg['jabatan'])) ?></b>).</div>
            </div>
            <div>
              <label class="f" for="nama">Nama</label>
              <input type="text" id="nama" name="nama" maxlength="<?= (int) OSIM_TEKS_MAKS ?>"
                     value="<?= e((string) ($editP['nama'] ?? '')) ?>" placeholder="nama pengurus">
            </div>
          </div>
          <div class="flex">
            <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $editP ? 'Simpan Perubahan' : 'Tambah Baris' ?></button>
            <?php if ($editP): ?><a class="btn btn-ghost" href="<?= $qBagian ?>&tab=data">Batal</a><?php endif; ?>
          </div>
        </form>

        <?php // "Susun Jabatan Baku" memakai FORM TERPISAH: kalau tombolnya diletakkan di dalam form
              // di atas, isian `required` (#jabatan) membuat peramban menahan kiriman saat tabel
              // masih kosong — justru saat tombol ini paling dibutuhkan. ?>
        <?php if (!$editP): ?>
          <form method="post" class="flex mt16">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="susun_baku">
            <button class="btn btn-ghost" type="submit"><?= ikon('star', 16) ?> Susun Jabatan Baku (<?= count($cfg['jabatan']) ?> Jabatan)</button>
            <span class="hint" style="align-self:center">Menambahkan baris
              <b><?= e((string) $cfg['jabatan'][0]) ?></b> s.d. <b><?= e((string) end($cfg['jabatan'])) ?></b>
              sekaligus — tinggal diisi namanya. Jabatan yang sudah ada tidak digandakan.</span>
          </form>
        <?php endif; ?>
      </div>

      <form class="filterbar mb16" method="get">
        <input type="hidden" name="kode" value="<?= e($kode) ?>">
        <input type="hidden" name="bagian" value="organisasi">
        <input type="hidden" name="tab" value="data">
        <div>
          <label class="f" for="cari">Cari</label>
          <input type="text" id="cari" name="cari" value="<?= e($f['cari']) ?>" placeholder="jabatan / nama">
        </div>
        <div>
          <label class="f" for="fJabatan">Jabatan / Seksi</label>
          <input type="text" id="fJabatan" name="jabatan" list="saranJabatanFilter" value="<?= e($f['jabatan']) ?>">
          <datalist id="saranJabatanFilter">
            <?php foreach ($jabatanSaran as $j): ?><option value="<?= e($j) ?>"></option><?php endforeach; ?>
          </datalist>
        </div>
        <div>
          <label class="f" for="fIsi">Kelengkapan</label>
          <select id="fIsi" name="isi" data-auto>
            <option value="">Semua baris</option>
            <option value="kosong" <?= $f['isi'] === 'kosong' ? 'selected' : '' ?>>Nama belum diisi</option>
          </select>
        </div>
        <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
        <?php if ($qFilter !== ''): ?><a class="btn btn-ghost" href="<?= $qBagian ?>&tab=data">Reset</a><?php endif; ?>
      </form>

      <?php if (!$pengurus): ?>
        <div class="empty"><?= ikon('users', 34) ?>
          <h3>Belum ada baris</h3>
          <p>Tambahkan jabatan/seksi lewat formulir di atas, tekan <b>Susun Jabatan Baku</b>, atau
            <a href="osim_import.php?kode=<?= e($kode) ?>&bagian=organisasi">impor dari Excel/CSV</a>.</p>
        </div>
      <?php else: ?>
        <?php osim_tabel_pengurus($pengurus, true, $peranPengelola, $kode); ?>
      <?php endif; ?>
    <?php endif; ?>

  <?php // ============================================================ PROGRAM KERJA ?>
  <?php else: ?>
    <?php if ($tab === 'laporan'): ?>
      <div class="grid g4" style="gap:16px">
        <div class="card stat card-tight" style="margin:0">
          <div class="stat-ic g"><?= ikon('calendar', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasW['program'] ?></div><div class="stat-lbl">Program</div></div>
        </div>
        <div class="card stat card-tight" style="margin:0">
          <div class="stat-ic"><?= ikon('check', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasW['ya'] ?></div><div class="stat-lbl">Terlaksana</div></div>
        </div>
        <div class="card stat card-tight" style="margin:0">
          <div class="stat-ic o"><?= ikon('alert', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasW['tidak'] ?></div><div class="stat-lbl">Belum terlaksana</div></div>
        </div>
        <div class="card stat card-tight" style="margin:0">
          <div class="stat-ic p"><?= ikon('clipboard', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasW['belum'] ?></div><div class="stat-lbl">Belum ditentukan</div></div>
        </div>
      </div>

      <?php if (!$program): ?>
        <div class="empty mt16"><?= ikon('calendar', 34) ?>
          <h3>Belum ada program kerja</h3>
          <p><?= $bolehUbah
            ? 'Isi lewat tab <b>Data (Input)</b>, atau impor dari Excel/CSV.'
            : pesan_kosong_hanya_lihat('program kerja ' . $istilah) ?></p>
          <?php if ($bolehUbah): ?>
            <div class="flex" style="justify-content:center">
              <a class="btn btn-primary" href="<?= $qBagian ?>&tab=data#formProgramOsim"><?= ikon('plus', 16) ?> Input Program Kerja</a>
              <a class="btn btn-ghost" href="osim_import.php?kode=<?= e($kode) ?>&bagian=program"><?= ikon('upload', 16) ?> Impor Excel/CSV</a>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="grid g2 mt16" style="gap:16px">
          <div class="card" style="margin:0">
            <div class="card-head"><div><h2>Rekap Keterlaksanaan</h2><p>Program sudah/belum terlaksana.</p></div></div>
            <div class="table-wrap"><table class="tbl">
              <thead><tr><th>Terlaksana</th><th class="ctr">Program</th></tr></thead>
              <tbody>
              <?php foreach (osim_rekap($program, 'terlaksana') as $nm => $jml): ?>
                <tr><td><?= e((string) $nm) ?></td><td class="ctr"><?= (int) $jml ?></td></tr>
              <?php endforeach; ?>
              </tbody></table></div>
          </div>
          <div class="card" style="margin:0">
            <div class="card-head"><div><h2>Rekap per Tahun Ajaran</h2><p>Sebaran program pada tiap tahun ajaran.</p></div></div>
            <div class="table-wrap"><table class="tbl">
              <thead><tr><th>Tahun Ajaran</th><th class="ctr">Program</th></tr></thead>
              <tbody>
              <?php foreach (osim_rekap($program, 'ta') as $nm => $jml): ?>
                <tr><td><?= e((string) $nm) ?></td><td class="ctr"><?= (int) $jml ?></td></tr>
              <?php endforeach; ?>
              </tbody></table></div>
          </div>
        </div>

        <div class="divider"></div>
        <div class="section-title mb8"><?= ikon('calendar', 15) ?> Daftar Program Kerja (<?= count($program) ?> program)</div>
        <?php osim_tabel_program($program, false, $peranPengelola && $bolehUbah, $kode); ?>
      <?php endif; ?>

    <?php else: ?>
      <div class="card" id="formProgramOsim" style="margin-bottom:18px">
        <div class="card-head">
          <div>
            <h2><?= ikon($editW ? 'edit' : 'plus', 16) ?> <?= $editW ? 'Ubah Program Kerja' : 'Tambah Program Kerja' ?></h2>
            <p>Isi program kerja <?= e($istilah) ?> — <b>Waktu Pelaksanaan</b> memakai tanggal lengkap (hari/bulan/tahun).</p>
          </div>
          <?php if ($editW): ?><a class="btn btn-ghost btn-sm" href="<?= $qBagian ?>&tab=data"><?= ikon('prev', 14) ?> Batal</a><?php endif; ?>
        </div>
        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="program_simpan">
          <input type="hidden" name="id" value="<?= (int) ($editW['id'] ?? 0) ?>">
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="namaProgram">Nama Program *</label>
              <input type="text" id="namaProgram" name="nama" maxlength="<?= (int) OSIM_TEKS_MAKS ?>" required
                     value="<?= e((string) ($editW['nama'] ?? '')) ?>" placeholder="mis. Latihan Dasar Kepemimpinan Siswa">
            </div>
            <div>
              <label class="f" for="waktu">Waktu Pelaksanaan</label>
              <input type="date" id="waktu" name="waktu" value="<?= e((string) ($editW['waktu'] ?? '')) ?>">
              <div class="hint">Tanggal lengkap (hari/bulan/tahun).</div>
            </div>
          </div>
          <div class="grid g3" style="gap:16px">
            <div>
              <label class="f" for="target">Target</label>
              <input type="text" id="target" name="target" maxlength="<?= (int) OSIM_URAIAN_MAKS ?>"
                     value="<?= e((string) ($editW['target'] ?? '')) ?>" placeholder="mis. 30 pengurus aktif">
            </div>
            <div>
              <label class="f" for="taProgram">Tahun Ajaran</label>
              <select id="taProgram" name="ta">
                <option value="">— belum ditentukan —</option>
                <?php foreach ($taPilihan as $tp): ?>
                  <option value="<?= e((string) $tp['nama']) ?>" <?= (string) ($editW['ta'] ?? '') === (string) $tp['nama'] ? 'selected' : '' ?>><?= e((string) $tp['nama']) ?></option>
                <?php endforeach; ?>
                <?php if (($editW['ta'] ?? '') !== '' && !in_array((string) $editW['ta'], array_column($taPilihan, 'nama'), true)): ?>
                  <option value="<?= e((string) $editW['ta']) ?>" selected><?= e((string) $editW['ta']) ?> (tidak ada di master)</option>
                <?php endif; ?>
              </select>
            </div>
            <div>
              <label class="f" for="terlaksana">Terlaksana</label>
              <select id="terlaksana" name="terlaksana">
                <option value="">— belum ditentukan —</option>
                <?php foreach (OSIM_TERLAKSANA as $o): ?>
                  <option value="<?= e($o) ?>" <?= (string) ($editW['terlaksana'] ?? '') === $o ? 'selected' : '' ?>><?= e($o) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div>
            <label class="f" for="ketProgram">Keterangan</label>
            <input type="text" id="ketProgram" name="keterangan" maxlength="<?= (int) OSIM_URAIAN_MAKS ?>"
                   value="<?= e((string) ($editW['keterangan'] ?? '')) ?>" placeholder="mis. bekerja sama dengan OSIS kabupaten">
          </div>
          <div class="flex">
            <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $editW ? 'Simpan Perubahan' : 'Tambah Program Kerja' ?></button>
            <?php if ($editW): ?><a class="btn btn-ghost" href="<?= $qBagian ?>&tab=data">Batal</a><?php endif; ?>
          </div>
        </form>
      </div>

      <form class="filterbar mb16" method="get">
        <input type="hidden" name="kode" value="<?= e($kode) ?>">
        <input type="hidden" name="bagian" value="program">
        <input type="hidden" name="tab" value="data">
        <div>
          <label class="f" for="cariW">Cari</label>
          <input type="text" id="cariW" name="cari" value="<?= e($f['cari']) ?>" placeholder="nama program / target">
        </div>
        <div>
          <label class="f" for="fTaW">Tahun Ajaran</label>
          <select id="fTaW" name="ta" data-auto>
            <option value="">Semua tahun ajaran</option>
            <?php foreach ($taPilihan as $tp): ?>
              <option value="<?= e((string) $tp['nama']) ?>" <?= $f['ta'] === (string) $tp['nama'] ? 'selected' : '' ?>><?= e((string) $tp['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="f" for="fTer">Terlaksana</label>
          <select id="fTer" name="terlaksana" data-auto>
            <option value="">Semua</option>
            <?php foreach (OSIM_TERLAKSANA as $o): ?>
              <option value="<?= e($o) ?>" <?= $f['terlaksana'] === $o ? 'selected' : '' ?>><?= e($o) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="f" for="fDari">Dari tanggal</label>
          <input type="date" id="fDari" name="dari" value="<?= e($f['dari']) ?>">
        </div>
        <div>
          <label class="f" for="fSampai">Sampai tanggal</label>
          <input type="date" id="fSampai" name="sampai" value="<?= e($f['sampai']) ?>">
        </div>
        <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
        <?php if ($qFilter !== ''): ?><a class="btn btn-ghost" href="<?= $qBagian ?>&tab=data">Reset</a><?php endif; ?>
      </form>

      <?php if (!$program): ?>
        <div class="empty"><?= ikon('calendar', 34) ?>
          <h3>Belum ada program kerja</h3>
          <p>Tambahkan lewat formulir di atas, atau <a href="osim_import.php?kode=<?= e($kode) ?>&bagian=program">impor dari Excel/CSV</a>.</p>
        </div>
      <?php else: ?>
        <?php osim_tabel_program($program, true, $peranPengelola, $kode); ?>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</div>
    <?php
    // WAJIB: tugas.php memanggil fungsi ini lalu `exit`, jadi penutup halaman (footer, </main>, dan
    // <script src="assets/app.js">) hanya keluar dari sini.
    page_bottom();
}

/** Tabel susunan organisasi (No, Jabatan/Nama Seksi, Nama, Tindakan). */
function osim_tabel_pengurus(array $baris, bool $aksi, bool $tombolBantuan = false,
                             string $kode = OSIM_KODE_MENU): void
{
    $qFilter = osim_query($_GET);
    ?>
<div class="table-wrap" id="tabelPengurus">
  <table class="tbl tbl-osim">
    <thead>
      <tr>
        <th class="ctr k-no">No</th>
        <th class="k-jabatan">Jabatan / Nama Seksi</th>
        <th class="k-nama">Nama</th>
        <?php if ($aksi): ?><th class="ctr k-aksi">Tindakan</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php $n = 0; foreach ($baris as $r): $n++; ?>
      <tr>
        <td class="ctr"><?= $n ?></td>
        <td><b><?= e((string) $r['jabatan']) ?></b></td>
        <td><?= trim((string) ($r['nama'] ?? '')) !== ''
            ? e((string) $r['nama'])
            : '<span class="mut xs">belum diisi</span>' ?></td>
        <?php if ($aksi): ?>
          <td>
            <div class="row-actions">
              <a class="btn btn-ghost btn-sm" href="tugas.php?kode=<?= e($kode) ?>&bagian=organisasi&tab=data&edit=<?= (int) $r['id'] ?>#formPengurus"><?= ikon('edit', 14) ?> Ubah</a>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="pengurus_geser">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit" name="arah" value="naik" title="Naikkan urutan"><?= ikon('up', 14) ?></button>
                <button class="btn btn-ghost btn-sm" type="submit" name="arah" value="turun" title="Turunkan urutan"><?= ikon('down', 14) ?></button>
              </form>
              <form method="post" data-confirm="Hapus baris &quot;<?= e((string) $r['jabatan']) ?>&quot;?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="pengurus_hapus">
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
        <th colspan="2" class="ctr">Jumlah</th>
        <th class="ctr"><?= count($baris) ?> baris</th>
        <?php if ($aksi): ?><th></th><?php endif; ?>
      </tr>
    </tfoot>
  </table>
</div>
<?php if ($tombolBantuan): ?>
  <p class="hint mt8">Ingin ringkasannya? Buka tab <a href="tugas.php?kode=<?= e($kode) ?>&bagian=organisasi&tab=laporan<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>">Laporan</a>,
    atau <a href="osim_export.php?kode=<?= e($kode) ?>&bagian=organisasi<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>">ekspor</a> /
    <a href="cetak_osim_organisasi.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>" target="_blank">cetak</a> daftar ini.</p>
<?php endif; ?>
    <?php
}

/** Tabel program kerja OSIM/OSIS (No, Nama Program, Waktu, Target, Tahun Ajaran, Terlaksana, Keterangan). */
function osim_tabel_program(array $baris, bool $aksi, bool $tombolBantuan = false,
                            string $kode = OSIM_KODE_MENU): void
{
    $qFilter = osim_query($_GET);
    ?>
<div class="table-wrap" id="tabelProgramOsim">
  <table class="tbl tbl-osim">
    <thead>
      <tr>
        <th class="ctr k-no">No</th>
        <th class="k-program">Nama Program</th>
        <th class="k-waktu">Waktu Pelaksanaan</th>
        <th class="k-target">Target</th>
        <th class="ctr k-ta">Tahun Ajaran</th>
        <th class="ctr k-terlaksana">Terlaksana</th>
        <th class="k-ket">Keterangan</th>
        <?php if ($aksi): ?><th class="ctr k-aksi">Tindakan</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php $n = 0; foreach ($baris as $r): $n++; ?>
      <tr>
        <td class="ctr"><?= $n ?></td>
        <td><b><?= e((string) $r['nama']) ?></b></td>
        <td class="sm"><?= osim_tanggal_label((string) ($r['waktu'] ?? '')) !== ''
            ? e(osim_tanggal_label((string) ($r['waktu'] ?? '')))
            : '<span class="mut xs">belum ditentukan</span>' ?></td>
        <td class="sm"><?= e((string) ($r['target'] ?: '-')) ?></td>
        <td class="ctr"><?= e((string) ($r['ta'] ?: '-')) ?></td>
        <td class="ctr">
          <?php $t = osim_terlaksana_sah((string) ($r['terlaksana'] ?? '')); ?>
          <?php if ($t === 'Ya'): ?><span class="pill pill-ok">Ya</span>
          <?php elseif ($t === 'Tidak'): ?><span class="pill pill-warn">Tidak</span>
          <?php else: ?><span class="mut xs">-</span><?php endif; ?>
        </td>
        <td class="sm"><?= e((string) ($r['keterangan'] ?: '-')) ?></td>
        <?php if ($aksi): ?>
          <td>
            <div class="row-actions">
              <a class="btn btn-ghost btn-sm" href="tugas.php?kode=<?= e($kode) ?>&bagian=program&tab=data&edit=<?= (int) $r['id'] ?>#formProgramOsim"><?= ikon('edit', 14) ?> Ubah</a>
              <form method="post" data-confirm="Hapus program &quot;<?= e((string) $r['nama']) ?>&quot;?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="program_hapus">
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
        <th class="ctr"><?= count($baris) ?> program</th>
        <?php if ($aksi): ?><th></th><?php endif; ?>
      </tr>
    </tfoot>
  </table>
</div>
<?php if ($tombolBantuan): ?>
  <p class="hint mt8">Ingin ringkasannya? Buka tab <a href="tugas.php?kode=<?= e($kode) ?>&bagian=program&tab=laporan<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>">Laporan</a>,
    atau <a href="osim_export.php?kode=<?= e($kode) ?>&bagian=program<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>">ekspor</a> /
    <a href="cetak_osim_program.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>" target="_blank">cetak</a> daftar ini.</p>
<?php endif; ?>
    <?php
}
