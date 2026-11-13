<?php
/**
 * MODUL MENU TUGAS TAMBAHAN "BIMBINGAN KONSELING" (Guru BK) + SUPERVISI GURU BK (akun pengawas).
 *
 * Permintaan pemilik: *"pada tugas tambahan Bimbingan Konseling tambahkan modul untuk mengirim
 * dokumen (maksimal 2mb) setiap butir penilaian ke pengawas seperti yang saya upload (bisa pdf,
 * gambar, word). Tampilkan progres pengiriman dokumen. Dan pada halaman pengawas tambahkan menu
 * Supervisi Guru BK ( didalamnya akan menampilkan daftar madrasah yang ada guru BKnya untuk di
 * supervisi). Contoh instrumen supervisi dan penilainnya seperti yang saya kirim. Hasil supervisi
 * otomatis akan masuk ke Guru yang ditugaskan Bimbingan Konseling dan ada rekapannya di akun
 * pengawas."*
 *
 * Keputusan yang dipakai (jangan diubah tanpa bertanya kepada pemilik):
 *   1. **Instrumen = 22 butir** persis seperti gambar yang dikirim pemilik (5 kelompok: A Program,
 *      B Kegiatan Pelayanan, C Aktifitas Pelayanan Bimbingan dan Konseling, D Pelaporan, E Evaluasi).
 *      Daftarnya ada di KODE (`bk_instrumen()`), BUKAN di basis data — sehingga tidak perlu migrasi
 *      bila ejaan/urutan diperbaiki, dan nilainya tidak bisa diubah-ubah pengguna.
 *   2. **Setiap butir punya SATU berkas** (PDF / gambar / Word / dokumen lain, maksimal
 *      `UNGGAH_MAKS_BYTE` = 2 MB). Mengunggah ulang pada butir yang sama MENGGANTIKAN berkas lama.
 *      Berkas dikirim ke media proxy platform di sisi SERVER (`media_upload()`); yang disimpan ke
 *      basis data hanya URL CDN hasilnya.
 *   3. **Penilaian dilakukan PENGAWAS** (kolom Ketersediaan Ada/Tidak, Skor 2/1/0, Keterangan —
 *      persis gambar kedua). Skor maksimal tiap butir 2 sehingga skor maksimal = 22 x 2 = 44 dan
 *      nilai = total skor / 44 x 100 (predikat A/B/C/D mengikuti ambang sekolah/madrasah).
 *   4. **Hasil supervisi otomatis tampil di akun Guru BK** (halaman menu ini) dan **rekapannya di
 *      akun pengawas** (halaman `supervisi_bk.php` + lembar cetak `cetak_supervisi_bk.php`).
 *   5. **Pengawas hanya menyupervisi sekolah/madrasah yang punya Guru BK** (guru yang ditugaskan
 *      menu Bimbingan Konseling) — daftar binaan yang tampil sudah tersaring.
 *
 * 🚨 Teks yang DIKETIK pengguna (keterangan butir, kesimpulan, saran, nama berkas) dibungkus
 * `tetap_kata()` saat dicetak supaya tidak ikut penyesuaian istilah "Sekolah" → "Madrasah".
 *
 * Hak ubah mengikuti aturan menu Tugas Tambahan lain: `boleh_ubah_tugas_menu()` — hanya **guru yang
 * ditugaskan** (dan Super Admin dalam konteks untuk halaman lain). Kepala Sekolah/Madrasah, Admin
 * Operator, dan Pengawas hanya melihat & mencetak.
 */
declare(strict_types=1);

require_once __DIR__ . '/dokumen.php';

// ============================================================================
// PENGENALAN MENU (kode MAUPUN nama — kode dibuat otomatis oleh Kelola Tugas Tambahan)
// ============================================================================

/**
 * true bila baris `tugas_menu` ini adalah menu **Bimbingan Konseling**.
 *
 * Dikenali lewat KODE (beberapa bentuk) maupun NAMA menu yang dinormalkan
 * (`tugas_nama_kunci()`), karena kode menu pada basis data pemilik dibuat lewat halaman
 * "Kelola Tugas Tambahan" sehingga tidak selalu sama dengan konstanta modul.
 */
function bk_tugas_kunci(?array $tugas): bool
{
    if (!$tugas) {
        return false;
    }
    $kode = strtolower(trim((string) ($tugas['kode'] ?? '')));
    if ($kode !== '' && ($kode === BK_KODE_MENU || in_array($kode, BK_KODE_LAIN, true)
                         || strpos($kode, 'bimbingan') !== false)) {
        return true;
    }
    $nama = tugas_nama_kunci((string) ($tugas['nama'] ?? ''));
    return $nama !== '' && in_array($nama, BK_NAMA_MENU, true);
}

/** Kode menu yang dipakai URL (kode sebenarnya, agar tautan tetap benar). */
function bk_kode(array $tugas): string
{
    $kode = trim((string) ($tugas['kode'] ?? ''));
    return $kode !== '' ? $kode : BK_KODE_MENU;
}

/**
 * Id seluruh baris `tugas_menu` yang dianggap menu Bimbingan Konseling.
 *
 * Dipakai untuk mencari guru BK sebuah sekolah (`bk_guru_bk()`) — guru yang punya baris
 * `guru_tugas` ke salah satu menu ini, atau akunnya ditugaskan lewat `user_tugas`.
 *
 * @return array<int,int>
 */
function bk_tugas_ids(): array
{
    $ids = [];
    foreach (tugas_menu_list(false) as $t) {
        if (bk_tugas_kunci($t)) {
            $ids[] = (int) $t['id'];
        }
    }
    return $ids;
}

// ============================================================================
// INSTRUMEN SUPERVISI (22 butir, 5 kelompok) — persis gambar pemilik
// ============================================================================

/**
 * Susunan instrumen: kelompok (A..E) → baris.
 *
 * Setiap baris berbentuk:
 *   ['jenis' => 'butir'|'sub', 'no' => '1'|'a', 'label' => '…']
 *
 * `sub` = judul sub-kelompok pada kelompok C ("Pemahaman", "Pelayanan Langsung", …) yang TIDAK
 * dinilai — hanya ditampilkan sebagai baris pemisah.
 *
 * @return array<int,array{kode:string,judul:string,baris:array<int,array<string,string>>}>
 */
function bk_instrumen(): array
{
    return [
        ['kode' => 'A', 'judul' => 'PROGRAM', 'baris' => [
            ['jenis' => 'butir', 'no' => '1',
             'label' => 'Program tahunan, semester, bulanan, agenda kerja konselor dan jadwal kegiatan konselor'],
        ]],
        ['kode' => 'B', 'judul' => 'KEGIATAN PELAYANAN', 'baris' => [
            ['jenis' => 'butir', 'no' => '1', 'label' => 'Daftar Konseling'],
            ['jenis' => 'butir', 'no' => '2', 'label' => 'Data kebutuhan dan permasalahan konseling'],
        ]],
        ['kode' => 'C', 'judul' => 'AKTIFITAS PELAYANAN BIMBINGAN DAN KONSELING', 'baris' => [
            ['jenis' => 'sub', 'no' => '1', 'label' => 'Pemahaman'],
            ['jenis' => 'butir', 'no' => 'a', 'label' => 'Sosiometri'],
            ['jenis' => 'butir', 'no' => 'b', 'label' => 'Kunjungan Rumah'],
            ['jenis' => 'butir', 'no' => 'c', 'label' => 'Catatan Anekdot'],
            ['jenis' => 'butir', 'no' => 'd', 'label' => 'Konferensi Kasus'],
            ['jenis' => 'sub', 'no' => '2', 'label' => 'Pelayanan Langsung'],
            ['jenis' => 'butir', 'no' => 'a', 'label' => 'Konseling Individual'],
            ['jenis' => 'butir', 'no' => 'b', 'label' => 'Konseling Kelompok'],
            ['jenis' => 'butir', 'no' => 'c', 'label' => 'Konsultasi'],
            ['jenis' => 'butir', 'no' => 'd', 'label' => 'Bimbingan Kelompok'],
            ['jenis' => 'butir', 'no' => 'e', 'label' => 'Bimbingan Klasikal'],
            ['jenis' => 'butir', 'no' => 'f', 'label' => 'Referal'],
            ['jenis' => 'sub', 'no' => '3', 'label' => 'Pelayanan Tidak Langsung'],
            ['jenis' => 'butir', 'no' => 'a', 'label' => 'Papan Bimbingan'],
            ['jenis' => 'butir', 'no' => 'b', 'label' => 'Kotak Masalah'],
            ['jenis' => 'butir', 'no' => 'c', 'label' => 'Bibliokonseling'],
            ['jenis' => 'butir', 'no' => 'd', 'label' => 'Audiovisual'],
            ['jenis' => 'butir', 'no' => 'e', 'label' => 'Audio'],
            ['jenis' => 'butir', 'no' => 'f', 'label' => 'Media Cetak (liflet, buku saku)'],
        ]],
        ['kode' => 'D', 'judul' => 'PELAPORAN', 'baris' => [
            ['jenis' => 'butir', 'no' => '1', 'label' => 'Laporan Bulanan'],
            ['jenis' => 'butir', 'no' => '2', 'label' => 'Laporan Semester / Tahunan'],
        ]],
        ['kode' => 'E', 'judul' => 'EVALUASI', 'baris' => [
            ['jenis' => 'butir', 'no' => '1',
             'label' => 'Laporan hasil evaluasi program, proses dan produk BK serta tindak lanjutnya'],
        ]],
    ];
}

/**
 * Daftar butir yang DINILAI (flat), nomor urut 1..22 — dipakai sebagai kunci penyimpanan.
 *
 * @return array<int,array{no:int,kode:string,grup:string,grup_judul:string,label:string}>
 */
function bk_butir_list(): array
{
    static $daftar = null;
    if ($daftar !== null) {
        return $daftar;
    }
    $daftar = [];
    $n = 0;
    foreach (bk_instrumen() as $grup) {
        // Nomor sub-kelompok berjalan (kelompok C memakai "Pemahaman"=1, "Pelayanan Langsung"=2,
        // "Pelayanan Tidak Langsung"=3) supaya kode tiap butir UNIK: C.1.a, C.2.a, C.3.a.
        // Tanpa ini C.1.a dan C.2.a sama-sama berkode "C.a" sehingga pesan/rujukan butir menyesatkan.
        $sub = '';
        foreach ($grup['baris'] as $baris) {
            if ($baris['jenis'] === 'sub') {
                $sub = $baris['no'] . '.';
                continue;
            }
            $n++;
            $daftar[] = [
                'no'         => $n,
                'kode'       => $grup['kode'] . '.' . $sub . $baris['no'],
                'grup'       => $grup['kode'],
                'grup_judul' => $grup['judul'],
                'label'      => $baris['label'],
            ];
        }
    }
    return $daftar;
}

/** Satu butir berdasarkan nomor urutnya (1..22); null bila di luar jangkauan. */
function bk_butir(int $no): ?array
{
    foreach (bk_butir_list() as $b) {
        if ($b['no'] === $no) {
            return $b;
        }
    }
    return null;
}

/** Jumlah butir yang dinilai (22). */
function bk_butir_total(): int
{
    return count(bk_butir_list());
}

/** Skor maksimal seluruh butir (22 x 2 = 44). */
function bk_skor_maks(): int
{
    return bk_butir_total() * BK_SKOR_MAKS;
}

// ------------------------------------------------------------------ nilai & ketersediaan

/** Pilihan skor per butir (2 / 1 / 0) — persis kolom "Skor" pada instrumen pemilik. */
function bk_skor_list(): array
{
    return [BK_SKOR_MAKS => '2', 1 => '1', 0 => '0'];
}

/** Skor sah? (0, 1, atau 2) */
function bk_skor_sah($skor): bool
{
    return in_array((int) $skor, array_keys(bk_skor_list()), true);
}

/** Pilihan "Ketersediaan" (Ada / Tidak) — persis instrumen pemilik. */
function bk_ketersediaan_list(): array
{
    return ['ada' => 'Ada', 'tidak' => 'Tidak'];
}

/** Ketersediaan yang sah? */
function bk_ketersediaan_sah(string $k): bool
{
    return array_key_exists($k, bk_ketersediaan_list());
}

/** Label ketersediaan untuk tampilan ('Ada' / 'Tidak' / '—' bila masih kosong). */
function bk_ketersediaan_teks(?string $k): string
{
    $k = (string) $k;
    return bk_ketersediaan_sah($k) ? (string) bk_ketersediaan_list()[$k] : '-';
}

/** Nilai (0..100) dari total skor — dibulatkan 2 desimal. */
function bk_nilai_dari(int $total, ?int $maks = null): float
{
    $maks = $maks === null || $maks <= 0 ? bk_skor_maks() : $maks;
    if ($maks <= 0) {
        return 0.0;
    }
    return round($total / $maks * 100, 2);
}

/** Predikat (A/B/C/D) memakai AMBANG sekolah/madrasah yang bersangkutan. */
function bk_predikat(float $nilai, int $sekolahId): string
{
    $s = $sekolahId > 0 ? one('SELECT * FROM sekolah WHERE id = ?', [$sekolahId]) : null;
    if (!$s) {
        return predikat_dari($nilai);
    }
    return predikat_dari_k($s, $nilai);
}

/** Kelas pil tema untuk predikat (A/B/C/D). */
function bk_predikat_pil(string $p): string
{
    switch ($p) {
        case 'A':
            return 'pill-A';
        case 'B':
            return 'pill-B';
        case 'C':
            return 'pill-C';
        case 'D':
            return 'pill-D';
    }
    return 'pill-warn';
}

// ============================================================================
// DOKUMEN PER BUTIR (dikirim Guru BK)
// ============================================================================

/**
 * Peta dokumen seorang guru: [butir_no => baris].
 *
 * @return array<int,array<string,mixed>>
 */
function bk_dokumen_peta(int $sekolahId, int $guruId): array
{
    if ($sekolahId <= 0 || $guruId <= 0) {
        return [];
    }
    $peta = [];
    foreach (all('SELECT * FROM bk_dokumen WHERE sekolah_id = ? AND guru_id = ?
                  ORDER BY butir_no', [$sekolahId, $guruId]) as $d) {
        $peta[(int) $d['butir_no']] = $d;
    }
    return $peta;
}

/** Satu dokumen berdasarkan id (WAJIB milik sekolah ini — anti-IDOR). */
function bk_dokumen_by_id(int $id, int $sekolahId): ?array
{
    return $id > 0 ? one('SELECT * FROM bk_dokumen WHERE id = ? AND sekolah_id = ?', [$id, $sekolahId]) : null;
}

/**
 * Simpan (atau ganti) dokumen satu butir.
 *
 * Mengunggah ulang pada butir yang sama MENGGANTIKAN berkas lama (UNIQUE sekolah+guru+butir),
 * sehingga progres pengiriman tidak pernah menghitung butir yang sama dua kali.
 */
function bk_dokumen_simpan(int $sekolahId, int $guruId, int $butir, array $isi): int
{
    $now = date('Y-m-d H:i:s');
    $ada = one('SELECT id FROM bk_dokumen WHERE sekolah_id = ? AND guru_id = ? AND butir_no = ?',
        [$sekolahId, $guruId, $butir]);
    if ($ada) {
        q('UPDATE bk_dokumen SET url = ?, nama_berkas = ?, jenis_berkas = ?, ukuran = ?, keterangan = ?,
                 diubah = ? WHERE id = ?',
            [(string) $isi['url'], (string) ($isi['nama_berkas'] ?? ''), (string) ($isi['jenis_berkas'] ?? ''),
             (int) ($isi['ukuran'] ?? 0), (string) ($isi['keterangan'] ?? ''), $now, (int) $ada['id']]);
        return (int) $ada['id'];
    }
    q('INSERT INTO bk_dokumen (sekolah_id, guru_id, butir_no, url, nama_berkas, jenis_berkas, ukuran,
             keterangan, dibuat, diubah) VALUES (?,?,?,?,?,?,?,?,?,?)',
        [$sekolahId, $guruId, $butir, (string) $isi['url'], (string) ($isi['nama_berkas'] ?? ''),
         (string) ($isi['jenis_berkas'] ?? ''), (int) ($isi['ukuran'] ?? 0),
         (string) ($isi['keterangan'] ?? ''), $now, $now]);
    return (int) db()->lastInsertId();
}

/** Hapus satu dokumen (dibatasi sekolah — anti-IDOR). */
function bk_dokumen_hapus(int $id, int $sekolahId): bool
{
    $d = bk_dokumen_by_id($id, $sekolahId);
    if (!$d) {
        return false;
    }
    q('DELETE FROM bk_dokumen WHERE id = ?', [$id]);
    return true;
}

/**
 * Progres pengiriman dokumen seorang guru BK.
 *
 * @return array{terisi:int,total:int,persen:int,belum:int}
 */
function bk_progress(int $sekolahId, int $guruId): array
{
    $total = bk_butir_total();
    $terisi = 0;
    foreach (bk_dokumen_peta($sekolahId, $guruId) as $no => $d) {
        if ($no >= 1 && $no <= $total) {
            $terisi++;
        }
    }
    return [
        'terisi' => $terisi,
        'total'  => $total,
        'belum'  => max(0, $total - $terisi),
        'persen' => $total > 0 ? (int) round($terisi / $total * 100) : 0,
    ];
}

/** Bar progres (markup bersama halaman guru, pengelola, & pengawas). */
function bk_progress_bar(array $p, string $kelas = ''): void
{
    ?>
    <div class="bk-progress <?= e($kelas) ?>">
      <div class="bk-progress-kepala">
        <b><?= (int) $p['terisi'] ?>/<?= (int) $p['total'] ?> butir</b>
        <span class="xs mut"><?= (int) $p['persen'] ?>% terkirim</span>
      </div>
      <div class="bk-progress-jalur" role="progressbar" aria-valuemin="0" aria-valuemax="100"
           aria-valuenow="<?= (int) $p['persen'] ?>">
        <span class="bk-progress-isi" style="width:<?= (int) $p['persen'] ?>%"></span>
      </div>
    </div>
    <?php
}

// ============================================================================
// GURU BK SEBUAH SEKOLAH
// ============================================================================

/**
 * Guru yang ditugaskan sebagai **Bimbingan Konseling** pada satu sekolah.
 *
 * Dua jalur penugasan diakui (keduanya dipakai aplikasi ini):
 *   1. **Data Guru** → kolom Tugas Tambahan (`guru_tugas`), dan
 *   2. **Pengaturan Tugas Tambahan** → menu ditugaskan ke AKUN guru (`user_tugas` + `users.guru_id`).
 *
 * @return array<int,array<string,mixed>>
 */
function bk_guru_bk(int $sekolahId, bool $aktifSaja = true): array
{
    if ($sekolahId <= 0) {
        return [];
    }
    $ids = bk_tugas_ids();
    if (!$ids) {
        return [];
    }
    $tanda = implode(',', array_fill(0, count($ids), '?'));
    // Placeholder: 1x sekolah_id + 2x daftar id menu BK (dua subquery EXISTS).
    $param = array_merge([$sekolahId], $ids, $ids);
    $rows = all('SELECT g.* FROM guru g
                 WHERE g.sekolah_id = ?' . ($aktifSaja ? ' AND IFNULL(g.aktif,1) = 1' : '') . '
                   AND (
                     EXISTS (SELECT 1 FROM guru_tugas gt WHERE gt.guru_id = g.id AND gt.tugas_id IN (' . $tanda . '))
                     OR EXISTS (SELECT 1 FROM users u JOIN user_tugas ut ON ut.user_id = u.id
                                WHERE u.guru_id = g.id AND ut.tugas_id IN (' . $tanda . '))
                   )
                 ORDER BY g.nama', $param);
    // Jaga-jaga: satu guru bisa muncul sekali saja (EXISTS sudah menjamin, ini pengaman tambahan).
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['id']] = $r;
    }
    return array_values($out);
}

/** Ringkasan seorang guru BK: progres dokumen + jumlah & nilai supervisi terakhir. */
function bk_ringkas_guru(int $sekolahId, int $guruId): array
{
    $p = bk_progress($sekolahId, $guruId);
    $sup = bk_supervisi_list(['sekolah' => $sekolahId, 'guru' => $guruId]);
    $akhir = $sup[0] ?? null;
    return [
        'progress'   => $p,
        'supervisi'  => count($sup),
        'akhir'      => $akhir,
        'nilai'      => $akhir ? (float) $akhir['nilai'] : null,
        'predikat'   => $akhir ? (string) $akhir['predikat'] : '',
        'tanggal'    => $akhir ? (string) $akhir['tanggal'] : '',
    ];
}

// ============================================================================
// SUPERVISI (dilakukan pengawas)
// ============================================================================

/**
 * Daftar supervisi BK.
 *
 * @param array<string,mixed> $f sekolah (0 = semua), guru, limit
 * @return array<int,array<string,mixed>>
 */
function bk_supervisi_list(array $f = []): array
{
    $w = [];
    $p = [];
    $sid = (int) ($f['sekolah'] ?? 0);
    if ($sid > 0) {
        $w[] = 's.sekolah_id = ?';
        $p[] = $sid;
    }
    $gid = (int) ($f['guru'] ?? 0);
    if ($gid > 0) {
        $w[] = 's.guru_id = ?';
        $p[] = $gid;
    }
    $pid = (int) ($f['pengawas'] ?? 0);
    if ($pid > 0) {
        $w[] = 's.pengawas_id = ?';
        $p[] = $pid;
    }
    $ids = $f['sekolah_ids'] ?? [];
    if (is_array($ids) && $ids) {
        $ids = array_values(array_map('intval', $ids));
        $w[] = 's.sekolah_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $p = array_merge($p, $ids);
    }
    $sql = 'SELECT s.*, g.nama AS guru_nama, g.nip AS guru_nip,
                   sk.nama AS sekolah_nama, sk.jenis AS sekolah_jenis
            FROM bk_supervisi s
            LEFT JOIN guru g ON g.id = s.guru_id
            LEFT JOIN sekolah sk ON sk.id = s.sekolah_id'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY s.tanggal DESC, s.id DESC';
    $limit = (int) ($f['limit'] ?? 0);
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }
    return all($sql, $p);
}

/** Satu supervisi berdasarkan id (dibatasi daftar sekolah yang boleh dilihat). */
function bk_supervisi_by_id(int $id, array $sekolahIds = []): ?array
{
    if ($id <= 0) {
        return null;
    }
    $sql = 'SELECT s.*, g.nama AS guru_nama, g.nip AS guru_nip, g.jabatan AS guru_jabatan,
                   g.sertifikasi AS guru_sertifikasi,
                   sk.nama AS sekolah_nama, sk.jenis AS sekolah_jenis
            FROM bk_supervisi s
            LEFT JOIN guru g ON g.id = s.guru_id
            LEFT JOIN sekolah sk ON sk.id = s.sekolah_id
            WHERE s.id = ?';
    $p = [$id];
    if ($sekolahIds) {
        $ids = array_values(array_map('intval', $sekolahIds));
        $sql .= ' AND s.sekolah_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $p = array_merge($p, $ids);
    }
    return one($sql, $p);
}

/**
 * Nilai per butir sebuah supervisi: [butir_no => ['ketersediaan','skor','keterangan']].
 *
 * @return array<int,array<string,mixed>>
 */
function bk_supervisi_nilai(int $supervisiId): array
{
    if ($supervisiId <= 0) {
        return [];
    }
    $peta = [];
    foreach (all('SELECT * FROM bk_supervisi_nilai WHERE supervisi_id = ?', [$supervisiId]) as $n) {
        $peta[(int) $n['butir_no']] = $n;
    }
    return $peta;
}

/**
 * Simpan supervisi (baru atau perbarui yang sudah ada) beserta nilai tiap butir.
 *
 * `$nilai` = [butir_no => ['ketersediaan' => 'ada'|'tidak'|'', 'skor' => '0'|'1'|'2'|'', 'keterangan' => '…']].
 * Butir yang belum diisi tetap disimpan sebagai baris kosong supaya pengawas dapat melanjutkan
 * penilaian (skornya dianggap 0 pada perhitungan).
 *
 * @return array{ok:bool,pesan:string,id:int}
 */
function bk_supervisi_simpan(array $data): array
{
    $sekolahId = (int) ($data['sekolah_id'] ?? 0);
    $guruId = (int) ($data['guru_id'] ?? 0);
    $tanggal = tgl_ok($data['tanggal'] ?? '');
    $guru = $guruId > 0 ? one('SELECT * FROM guru WHERE id = ? AND sekolah_id = ?', [$guruId, $sekolahId]) : null;
    if ($sekolahId <= 0 || !$guru) {
        return ['ok' => false, 'pesan' => 'Guru Bimbingan Konseling itu tidak ditemukan pada sekolah/madrasah ini.', 'id' => 0];
    }
    if ($tanggal === null || $tanggal === '') {
        return ['ok' => false, 'pesan' => 'Tanggal supervisi wajib diisi dengan format yang benar.', 'id' => 0];
    }
    $nilai = is_array($data['nilai'] ?? null) ? $data['nilai'] : [];
    $kesimpulan = trim((string) ($data['kesimpulan'] ?? ''));
    $saran = trim((string) ($data['saran'] ?? ''));
    if (function_exists('mb_substr')) {
        $kesimpulan = mb_substr($kesimpulan, 0, BK_KESIMPULAN_MAKS);
        $saran = mb_substr($saran, 0, BK_KESIMPULAN_MAKS);
    } else {
        $kesimpulan = substr($kesimpulan, 0, BK_KESIMPULAN_MAKS);
        $saran = substr($saran, 0, BK_KESIMPULAN_MAKS);
    }

    $bersih = [];
    $total = 0;
    $adaSkor = 0;
    foreach (bk_butir_list() as $b) {
        $row = $nilai[$b['no']] ?? [];
        $ket = trim((string) ($row['ketersediaan'] ?? ''));
        $skorRaw = $row['skor'] ?? '';
        $catatan = trim((string) ($row['keterangan'] ?? ''));
        if (function_exists('mb_substr')) {
            $catatan = mb_substr($catatan, 0, BK_KET_MAKS);
        } else {
            $catatan = substr($catatan, 0, BK_KET_MAKS);
        }
        $ket = bk_ketersediaan_sah($ket) ? $ket : '';
        $skor = ($skorRaw !== '' && $skorRaw !== null && bk_skor_sah($skorRaw)) ? (int) $skorRaw : null;
        if ($skor !== null) {
            $total += $skor;
            $adaSkor++;
        }
        $bersih[$b['no']] = ['ketersediaan' => $ket, 'skor' => $skor, 'keterangan' => $catatan];
    }

    $maks = bk_skor_maks();
    $nilaiAkhir = bk_nilai_dari($total, $maks);
    $predikat = bk_predikat($nilaiAkhir, $sekolahId);
    if ($adaSkor === 0) {
        $nilaiAkhir = 0.0;
        $predikat = '-';
    }
    $uid = (int) (current_user()['id'] ?? 0);
    $namaPengawas = (string) (current_user()['nama'] ?? '');
    $now = date('Y-m-d H:i:s');
    $id = (int) ($data['id'] ?? 0);
    $lama = null;
    if ($id > 0) {
        // Menyunting supervisi yang SUDAH ADA: barisnya wajib milik sekolah/madrasah ini
        // (anti-IDOR). Id asing TIDAK boleh diam-diam berubah menjadi supervisi baru.
        $lama = one('SELECT * FROM bk_supervisi WHERE id = ? AND sekolah_id = ?', [$id, $sekolahId]);
        if (!$lama) {
            return ['ok' => false, 'id' => 0,
                    'pesan' => 'Supervisi yang akan diubah tidak ditemukan pada sekolah/madrasah ini.'];
        }
    }

    db()->exec('BEGIN IMMEDIATE');
    try {
        if ($lama) {
            q('UPDATE bk_supervisi SET guru_id = ?, pengawas_id = ?, pengawas_nama = ?, tanggal = ?,
                     total_skor = ?, skor_maks = ?, nilai = ?, predikat = ?, kesimpulan = ?, saran = ?,
                     diubah = ? WHERE id = ?',
                [$guruId, $uid, $namaPengawas, $tanggal, $total, $maks, $nilaiAkhir, $predikat,
                 $kesimpulan, $saran, $now, (int) $lama['id']]);
            $supId = (int) $lama['id'];
            q('DELETE FROM bk_supervisi_nilai WHERE supervisi_id = ?', [$supId]);
        } else {
            q('INSERT INTO bk_supervisi (sekolah_id, guru_id, pengawas_id, pengawas_nama, tanggal,
                     total_skor, skor_maks, nilai, predikat, kesimpulan, saran, dibuat, diubah)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$sekolahId, $guruId, $uid, $namaPengawas, $tanggal, $total, $maks, $nilaiAkhir,
                 $predikat, $kesimpulan, $saran, $now, $now]);
            $supId = (int) db()->lastInsertId();
        }
        $ins = db()->prepare('INSERT INTO bk_supervisi_nilai (supervisi_id, butir_no, ketersediaan, skor, keterangan)
                              VALUES (?,?,?,?,?)');
        foreach ($bersih as $no => $n) {
            $ins->execute([$supId, $no, $n['ketersediaan'], $n['skor'], $n['keterangan']]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        error_log('e-GURU supervisi BK gagal disimpan: ' . $ex->getMessage());
        return ['ok' => false, 'pesan' => 'Supervisi gagal disimpan karena galat pada server. Coba lagi.', 'id' => 0];
    }

    return ['ok' => true, 'id' => $supId,
            'pesan' => 'Supervisi Bimbingan Konseling untuk ' . tetap_kata((string) $guru['nama'])
                . ' tersimpan (skor ' . $total . '/' . $maks . ', nilai ' . r1($nilaiAkhir)
                . ($predikat !== '-' ? ', predikat ' . $predikat : '') . '). Hasilnya otomatis tampil '
                . 'pada akun guru Bimbingan Konseling.'];
}

/** Hapus satu supervisi beserta nilai butirnya (nilai ikut terhapus lewat CASCADE). */
function bk_supervisi_hapus(int $id, int $sekolahId): bool
{
    $s = one('SELECT id FROM bk_supervisi WHERE id = ? AND sekolah_id = ?', [$id, $sekolahId]);
    if (!$s) {
        return false;
    }
    q('DELETE FROM bk_supervisi WHERE id = ?', [$id]);
    return true;
}

/** Ringkasan supervisi dari sederet baris: jumlah + rata-rata nilai + sebaran predikat. */
function bk_supervisi_ringkas(array $baris): array
{
    $r = ['jumlah' => count($baris), 'rata' => 0.0, 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, '-' => 0];
    $jumlahNilai = 0;
    $totalNilai = 0.0;
    foreach ($baris as $b) {
        $p = (string) ($b['predikat'] ?? '-');
        if (isset($r[$p])) {
            $r[$p]++;
        }
        $totalNilai += (float) ($b['nilai'] ?? 0);
        $jumlahNilai++;
    }
    $r['rata'] = $jumlahNilai > 0 ? round($totalNilai / $jumlahNilai, 2) : 0.0;
    return $r;
}

/**
 * NIP pengawas yang sedang masuk, BILA identitas pengawas tercatat pada sekolah/madrasah itu
 * (kolom `pengawas_nama`/`pengawas_nip` diisi saat pendaftaran sekolah atau di Identitas &
 * Pengaturan). Akun `users` tidak menyimpan NIP, jadi bila namanya tidak cocok dipakai '-'
 * (tidak menebak).
 */
function bk_pengawas_nip(array $sekolah): string
{
    $nama = (string) ($sekolah['pengawas_nama'] ?? '');
    $nip = trim((string) ($sekolah['pengawas_nip'] ?? ''));
    $saya = (string) (current_user()['nama'] ?? '');
    if ($nip === '' || $nama === '' || $saya === '') {
        return '';
    }
    return sekolah_nama_kunci($nama) === sekolah_nama_kunci($saya) ? $nip : '';
}

/**
 * Potong teks panjang untuk tampilan tabel (tanpa ketergantungan mbstring — modul ini juga dipakai
 * halaman yang tidak memuat inc/perangkat.php).
 */
function bk_potong(string $teks, int $maks = 90): string
{
    $teks = trim($teks);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($teks) > $maks ? rtrim(mb_substr($teks, 0, $maks)) . '…' : $teks;
    }
    return strlen($teks) > $maks ? rtrim(substr($teks, 0, $maks)) . '…' : $teks;
}

/** Judul halaman/menu yang dipakai (nama menu dapat diubah Super Admin). */
function bk_nama_menu(?array $tugas): string
{
    $nama = trim((string) ($tugas['nama'] ?? ''));
    return $nama !== '' ? $nama : 'Bimbingan Konseling';
}

/** URL halaman modul ini (halaman `tugas.php?kode=…`). */
function bk_url(?array $tugas = null, array $q = []): string
{
    $kode = $tugas ? bk_kode($tugas) : BK_KODE_MENU;
    $q = array_merge(['kode' => $kode], $q);
    return 'tugas.php?' . http_build_query($q);
}

// ============================================================================
// BAGIAN TAMPILAN BERSAMA
// ============================================================================

/**
 * Tabel instrumen per butir.
 *
 * @param array<string,mixed> $opsi
 *   - boleh_ubah (bool)   : tampilkan tombol Hapus dokumen & isian penilaian
 *   - dok (array)         : peta dokumen guru
 *   - nilai (array)       : peta nilai supervisi (mode pengawas)
 *   - aksi_hapus (bool)   : tampilkan form hapus dokumen
 */
function bk_tabel_butir(array $opsi = []): void
{
    $dok = is_array($opsi['dok'] ?? null) ? $opsi['dok'] : [];
    $nilai = is_array($opsi['nilai'] ?? null) ? $opsi['nilai'] : [];
    $penilaian = !empty($opsi['penilaian']);          // mode pengawas: kolom ketersediaan/skor/keterangan
    $aksiHapus = !empty($opsi['aksi_hapus']) && !empty($opsi['csrf']);
    $nomor = 0;
    ?>
    <div class="table-wrap">
      <table class="tbl bk-tbl">
        <thead>
          <tr>
            <th class="ctr" style="width:52px">No</th>
            <th>Kegiatan / Komponen</th>
            <th style="width:190px">Dokumen</th>
            <?php if ($penilaian): ?>
              <th class="ctr" style="width:112px">Ketersediaan</th>
              <th class="ctr" style="width:88px">Skor</th>
              <th style="width:190px">Keterangan</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach (bk_instrumen() as $grup): ?>
          <tr class="bk-grup">
            <td class="ctr"><b><?= e($grup['kode']) ?></b></td>
            <td colspan="<?= $penilaian ? 5 : 2 ?>"><b><?= e($grup['judul']) ?></b></td>
          </tr>
          <?php foreach ($grup['baris'] as $baris): ?>
            <?php if ($baris['jenis'] === 'sub'): ?>
              <tr class="bk-sub">
                <td class="ctr"><?= e($baris['no']) ?></td>
                <td colspan="<?= $penilaian ? 5 : 2 ?>"><b><?= e($baris['label']) ?></b></td>
              </tr>
              <?php continue; ?>
            <?php endif; ?>
            <?php
            $nomor++;
            $d = $dok[$nomor] ?? null;
            $n = $nilai[$nomor] ?? null;
            ?>
            <tr>
              <td class="ctr"><?= e($baris['no']) ?></td>
              <td><?= e($baris['label']) ?></td>
              <td class="bk-dok">
                <?php if ($d): ?>
                  <a class="bk-berkas" href="<?= e(tautan_aman((string) $d['url']) ?: '#') ?>"
                     target="_blank" rel="noopener noreferrer">
                    <?= ikon('doc', 13) ?> <span><?= e(tetap_kata((string) ($d['nama_berkas'] !== '' ? $d['nama_berkas'] : 'Buka dokumen'))) ?></span>
                  </a>
                  <div class="tbl-sub"><?= e(dok_ukuran((int) $d['ukuran'])) ?>
                    <?= trim((string) $d['keterangan']) !== '' ? '&middot; ' . e(tetap_kata((string) $d['keterangan'])) : '' ?></div>
                  <?php if ($aksiHapus): ?>
                    <form method="post" action="<?= e((string) $opsi['form_aksi']) ?>" class="bk-hapus-form"
                          data-confirm="Hapus dokumen butir ini?">
                      <?= $opsi['csrf'] ?>
                      <input type="hidden" name="aksi" value="hapus_dokumen">
                      <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                      <?php if (!empty($opsi['kembali_guru'])): ?>
                        <input type="hidden" name="guru" value="<?= (int) $opsi['kembali_guru'] ?>">
                      <?php endif; ?>
                      <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 13) ?> Hapus</button>
                    </form>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="pill pill-warn">belum dikirim</span>
                <?php endif; ?>
              </td>
              <?php if ($penilaian): ?>
                <td class="ctr">
                  <select name="nilai[<?= $nomor ?>][ketersediaan]" class="bk-sel" aria-label="Ketersediaan butir <?= $nomor ?>">
                    <option value="">-</option>
                    <?php foreach (bk_ketersediaan_list() as $vk => $vt): ?>
                      <option value="<?= e($vk) ?>"<?= (string) ($n['ketersediaan'] ?? '') === $vk ? ' selected' : '' ?>><?= e($vt) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td class="ctr">
                  <select name="nilai[<?= $nomor ?>][skor]" class="bk-sel bk-sel-skor" aria-label="Skor butir <?= $nomor ?>">
                    <option value="">-</option>
                    <?php foreach (bk_skor_list() as $sk => $st): ?>
                      <option value="<?= $sk ?>"<?= (string) ($n['skor'] ?? '') !== '' && (int) ($n['skor'] ?? -1) === (int) $sk ? ' selected' : '' ?>><?= e($st) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <input type="text" name="nilai[<?= $nomor ?>][keterangan]" class="bk-ket"
                         value="<?= e(tetap_kata((string) ($n['keterangan'] ?? ''))) ?>"
                         placeholder="Keterangan (opsional)" aria-label="Keterangan butir <?= $nomor ?>">
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="<?= $penilaian ? 5 : 2 ?>" class="r"><b>Jumlah butir</b></td>
            <td class="ctr"><b><?= bk_butir_total() ?></b> butir</td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php
}

/** Kartu hasil supervisi seorang guru (dibaca guru BK, kepala, & pengawas). */
function bk_kartu_hasil(array $daftar, bool $tampilSekolah = false, bool $tautanCetak = true): void
{
    ?>
    <div class="card" id="cardHasilSupervisi">
      <div class="card-head">
        <div>
          <h2><?= ikon('chart', 16) ?> Hasil Supervisi Bimbingan Konseling</h2>
          <p>Hasil penilaian <b>Pengawas Sekolah/Madrasah</b> atas dokumen Bimbingan Konseling —
            tampil otomatis di sini setelah pengawas menyimpan penilaiannya.</p>
        </div>
        <span class="chip"><?= count($daftar) ?> supervisi</span>
      </div>
      <?php if (!$daftar): ?>
        <div class="empty" style="padding:22px">
          <?= ikon('clock', 34) ?>
          <h3>Belum ada supervisi</h3>
          <p>Pengawas belum menilai dokumen Bimbingan Konseling ini. Kirim seluruh dokumen butir
            terlebih dahulu agar pengawas dapat melakukan supervisi.</p>
        </div>
      <?php else: ?>
        <div class="grid g2" style="gap:16px">
          <?php foreach ($daftar as $s): ?>
            <div class="card card-tight bk-hasil" style="margin:0">
              <div class="bk-hasil-kepala">
                <div>
                  <b><?= e(tanggal_id((string) $s['tanggal'], true)) ?></b>
                  <div class="tbl-sub"><?= e($s['pengawas_nama'] !== '' ? $s['pengawas_nama'] : 'Pengawas') ?>
                    <?= $tampilSekolah && trim((string) ($s['sekolah_nama'] ?? '')) !== ''
                        ? ' &middot; ' . e(tetap_kata((string) $s['sekolah_nama'])) : '' ?></div>
                </div>
                <span class="pill <?= bk_predikat_pil((string) $s['predikat']) ?>">
                  <?= e((string) $s['predikat']) ?> &middot; <?= e(r1((float) $s['nilai'])) ?>
                </span>
              </div>
              <div class="sheet-meta" style="margin:10px 0 0">
                <div><b>Skor</b>: <?= (int) $s['total_skor'] ?> / <?= (int) $s['skor_maks'] ?></div>
                <div><b>Nilai</b>: <?= e(r1((float) $s['nilai'])) ?> (<?= e(predikat_label((string) $s['predikat'])) ?>)</div>
              </div>
              <?php if (trim((string) $s['kesimpulan']) !== ''): ?>
                <p class="bk-hasil-teks"><b>Kesimpulan</b><br><?= nl2br(e(tetap_kata((string) $s['kesimpulan']))) ?></p>
              <?php endif; ?>
              <?php if (trim((string) $s['saran']) !== ''): ?>
                <p class="bk-hasil-teks"><b>Saran</b><br><?= nl2br(e(tetap_kata((string) $s['saran']))) ?></p>
              <?php endif; ?>
              <?php
              // Rincian penilaian tiap butir — guru BK dapat menindaklanjuti butir yang dinilai
              // rendah atau dokumennya belum tersedia ("Ada/Tidak").
              $detail = bk_supervisi_nilai((int) $s['id']);
              if ($detail):
              ?>
                <details class="bk-hasil-detail mt8">
                  <summary>Rincian penilaian tiap butir</summary>
                  <div class="table-wrap">
                    <table class="tbl bk-tbl-detail">
                      <thead>
                        <tr>
                          <th style="width:64px">Butir</th>
                          <th>Kegiatan / Komponen</th>
                          <th class="ctr" style="width:96px">Ketersediaan</th>
                          <th class="ctr" style="width:56px">Skor</th>
                          <th style="width:150px">Keterangan</th>
                        </tr>
                      </thead>
                      <tbody>
                      <?php foreach (bk_butir_list() as $b): $n = $detail[$b['no']] ?? null; ?>
                        <tr>
                          <td><?= e($b['kode']) ?></td>
                          <td class="xs"><?= e($b['label']) ?></td>
                          <td class="ctr"><?= e(bk_ketersediaan_teks((string) ($n['ketersediaan'] ?? ''))) ?></td>
                          <td class="ctr"><?= $n && $n['skor'] !== null ? (int) $n['skor'] : '-' ?></td>
                          <td class="xs"><?= trim((string) ($n['keterangan'] ?? '')) !== ''
                              ? e(tetap_kata((string) $n['keterangan'])) : '-' ?></td>
                        </tr>
                      <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </details>
              <?php endif; ?>
              <?php if ($tautanCetak): ?>
                <div class="flex mt8">
                  <a class="btn btn-ghost btn-sm" href="cetak_supervisi_bk.php?id=<?= (int) $s['id'] ?>"
                     target="_blank" rel="noopener"><?= ikon('print', 14) ?> Cetak Lembar Supervisi</a>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
}

// ============================================================================
// HALAMAN MODUL (menu Tugas Tambahan "Bimbingan Konseling")
// ============================================================================

/**
 * Halaman menu Bimbingan Konseling.
 *
 * • **Guru BK** (yang ditugaskan menu ini): mengunggah dokumen tiap butir, melihat progres, dan
 *   membaca hasil supervisi pengawas.
 * • **Kepala/Operator/Super Admin/Pengawas**: hanya melihat — daftar guru BK sekolah itu beserta
 *   progres & hasil supervisinya (`?guru=ID` untuk rincian).
 */
function bk_halaman(array $tugas): void
{
    require_sekolah();
    $namaMenu = bk_nama_menu($tugas);
    $sid = sekolah_id();
    $guruSaya = is_guru() ? guru_saya() : null;
    $gidSaya = $guruSaya ? (int) $guruSaya['id'] : 0;
    // Hanya GURU yang mengisi (dokumen ini milik guru BK). Pengawas/kepala/operator/super melihat.
    $bolehUbah = is_guru() && $gidSaya > 0;

    // ---------------------------------------------------------------- POST
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!$bolehUbah) {
            if (is_super()) {
                tolak403('Dokumen Bimbingan Konseling diisi oleh guru yang ditugaskan sebagai Guru BK. '
                    . 'Super Admin hanya dapat melihat dan mencetak.');
            }
            if (is_guru()) {
                tolak403('Akun Anda belum ditautkan ke data guru, jadi belum dapat mengirim dokumen. '
                    . 'Hubungi Admin Operator sekolah/madrasah Anda.');
            }
            tolak_ubah_tugas_menu('dokumen Bimbingan Konseling');
        }
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');

        if ($aksi === 'hapus_dokumen') {
            $id = (int) ($_POST['id'] ?? 0);
            $d = bk_dokumen_by_id($id, $sid);
            if (!$d || (int) $d['guru_id'] !== $gidSaya) {
                flash('Dokumen itu tidak ditemukan, atau bukan dokumen Anda.', 'err');
            } else {
                bk_dokumen_hapus($id, $sid);
                $b = bk_butir((int) $d['butir_no']);
                flash('Dokumen butir ' . ($b ? $b['kode'] : '-') . ' dihapus.', 'warn');
            }
            redirect(bk_url($tugas) . '#tabelButir');
        }

        if ($aksi === 'kirim') {
            // PHP membuang seluruh $_POST/$_FILES bila kiriman melebihi post_max_size.
            $panjang = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($panjang > 0 && !$_POST && !$_FILES) {
                flash('Ukuran kiriman melebihi batas server (' . dok_ukuran(dok_batas_server())
                    . '). Kirim berkas yang lebih kecil.', 'err');
                redirect(bk_url($tugas) . '#formDokumen');
            }
            $butirNo = (int) ($_POST['butir'] ?? 0);
            $b = bk_butir($butirNo);
            if (!$b) {
                flash('Butir penilaian yang dipilih tidak dikenal.', 'err');
                redirect(bk_url($tugas) . '#formDokumen');
            }
            $keterangan = trim((string) ($_POST['keterangan'] ?? ''));
            if (strlen($keterangan) > BK_KET_MAKS) {
                $keterangan = substr($keterangan, 0, BK_KET_MAKS);
            }
            $berkas = $_FILES['berkas'] ?? null;
            if (!is_array($berkas) || (int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $kode = is_array($berkas) ? (int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
                $pesan = ($kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE)
                    ? 'Berkas terlalu besar untuk setelan server (maksimal ' . dok_ukuran(dok_batas_server()) . ').'
                    : 'Berkas dokumen wajib dipilih.';
                flash($pesan, 'err');
                redirect(bk_url($tugas) . '#formDokumen');
            }
            $namaAsli = (string) ($berkas['name'] ?? 'dokumen');
            $ukuran = (int) ($berkas['size'] ?? 0);
            $tmp = (string) ($berkas['tmp_name'] ?? '');
            $v = dok_validasi($namaAsli, $ukuran, $tmp);
            if (!$v['ok']) {
                flash($v['pesan'], 'err');
                redirect(bk_url($tugas) . '#formDokumen');
            }
            $bytes = @file_get_contents($tmp);
            if ($bytes === false || $bytes === '') {
                flash('Berkas tidak dapat dibaca server. Coba ulangi.', 'err');
                redirect(bk_url($tugas) . '#formDokumen');
            }
            $unggah = media_upload($bytes, $namaAsli);
            if (empty($unggah['ok'])) {
                flash((string) ($unggah['pesan'] ?? 'Berkas gagal diunggah.'), 'err');
                redirect(bk_url($tugas) . '#formDokumen');
            }
            $lama = one('SELECT id FROM bk_dokumen WHERE sekolah_id = ? AND guru_id = ? AND butir_no = ?',
                [$sid, $gidSaya, $butirNo]);
            bk_dokumen_simpan($sid, $gidSaya, $butirNo, [
                'url'          => (string) $unggah['url'],
                'nama_berkas'  => $namaAsli,
                'jenis_berkas' => (string) $v['jenis'],
                'ukuran'       => $ukuran,
                'keterangan'   => $keterangan,
            ]);
            $p = bk_progress($sid, $gidSaya);
            flash('Dokumen butir ' . $b['kode'] . ' berhasil '
                . ($lama ? 'DIGANTI' : 'dikirim') . '. Progres pengiriman kini '
                . $p['terisi'] . '/' . $p['total'] . ' butir (' . $p['persen'] . '%) dan dapat dibaca '
                . 'pengawas pada menu Supervisi Guru BK.', 'ok');
            redirect(bk_url($tugas) . '#tabelButir');
        }

        flash('Aksi tidak dikenal.', 'err');
        redirect(bk_url($tugas));
    }

    // ---------------------------------------------------------------- tampilan
    $guruDilihat = null;
    if ($bolehUbah) {
        $guruDilihat = $guruSaya;
    } else {
        $idGuru = (int) ($_GET['guru'] ?? 0);
        if ($idGuru > 0) {
            $g = one('SELECT * FROM guru WHERE id = ? AND sekolah_id = ?', [$idGuru, $sid]);
            if ($g) {
                $guruDilihat = $g;
            } else {
                flash('Guru itu tidak ditemukan pada sekolah/madrasah ini.', 'err');
                redirect(bk_url($tugas));
            }
        }
    }
    $guruBk = bk_guru_bk($sid);
    $batas = dok_batas_server();

    page_top($namaMenu, 'Bimbingan Konseling &middot; ' . tetap_kata(nama_sekolah_tampil()));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('support', 16) ?> <?= e($namaMenu) ?></h2>
      <p>Kirim <b>dokumen setiap butir penilaian Bimbingan Konseling</b> (satu berkas per butir,
        maksimal <?= e(dok_ukuran($batas)) ?> — PDF, gambar, Word, atau dokumen lain) kepada
        <b>Pengawas Sekolah/Madrasah</b>. Progres pengiriman ditampilkan di bawah, dan hasil supervisi
        pengawas otomatis masuk ke halaman ini.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($guruBk) ?> guru BK</span>
      <span class="chip"><?= bk_butir_total() ?> butir penilaian</span>
      <?php if (is_pengawas()): ?>
        <a class="btn btn-ghost btn-sm" href="supervisi_bk.php"><?= ikon('chart', 15) ?> Supervisi Guru BK</a>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!$bolehUbah): ?>
    <div class="alert alert-warn">
      <?= ikon('eye', 16) ?>
      <span><?= pemberitahuan_hanya_lihat('dokumen Bimbingan Konseling') ?>
        <?php if ($guruDilihat): ?>
          Anda membuka data <b><?= e(tetap_kata((string) $guruDilihat['nama'])) ?></b>.
          <a href="<?= e(bk_url($tugas)) ?>">Kembali ke daftar guru BK</a>.
        <?php endif; ?></span>
    </div>
  <?php endif; ?>
</div>

<?php if (!$bolehUbah && !$guruDilihat): ?>
  <?php // ------------------------------------------------------------ DAFTAR GURU BK (pengelola/pengawas) ?>
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= ikon('users', 16) ?> Guru Bimbingan Konseling</h2>
        <p>Daftar guru yang ditugaskan sebagai <b>Bimbingan Konseling</b> beserta
          <b>progres pengiriman dokumen</b> dan hasil supervisinya.</p>
      </div>
      <span class="chip"><?= count($guruBk) ?> guru</span>
    </div>
    <?php if (!$guruBk): ?>
      <div class="empty" style="padding:22px">
        <?= ikon('users', 34) ?>
        <h3>Belum ada guru Bimbingan Konseling</h3>
        <p>Belum ada guru yang ditugaskan pada menu <b><?= e($namaMenu) ?></b> di
          <?= tetap_kata('sekolah/madrasah') ?> ini. Penugasan dilakukan lewat
          <b>Data Guru</b> (kolom Tugas Tambahan) atau <b>Pengaturan Tugas Tambahan</b>.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl" id="tblGuruBk">
          <thead>
            <tr>
              <th class="ctr" style="width:44px">No</th>
              <th>Nama Guru BK / Konselor</th>
              <th>Progres Dokumen</th>
              <th class="ctr" style="width:120px">Supervisi</th>
              <th class="ctr" style="width:120px">Nilai Terakhir</th>
              <th class="ctr" style="width:110px">Tindakan</th>
            </tr>
          </thead>
          <tbody>
          <?php $n = 0; foreach ($guruBk as $g): $n++;
              $r = bk_ringkas_guru($sid, (int) $g['id']); ?>
            <tr>
              <td class="ctr"><?= $n ?></td>
              <td><b><?= e(tetap_kata((string) $g['nama'])) ?></b>
                <div class="tbl-sub">NIP: <?= e(trim((string) $g['nip']) !== '' ? (string) $g['nip'] : '-') ?>
                  <?= trim((string) $g['jabatan']) !== '' ? ' &middot; ' . e(tetap_kata((string) $g['jabatan'])) : '' ?></div></td>
              <td style="min-width:200px"><?php bk_progress_bar($r['progress']); ?></td>
              <td class="ctr"><?= (int) $r['supervisi'] ?> kali</td>
              <td class="ctr">
                <?php if ($r['nilai'] === null): ?>
                  <span class="pill pill-warn">belum</span>
                <?php else: ?>
                  <span class="pill <?= bk_predikat_pil($r['predikat']) ?>">
                    <?= e($r['predikat']) ?> &middot; <?= e(r1($r['nilai'])) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td class="ctr">
                <a class="btn btn-ghost btn-sm" href="<?= e(bk_url($tugas, ['guru' => (int) $g['id']])) ?>">
                  <?= ikon('eye', 14) ?> Rincian
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <?php
  // ------------------------------------------------------------ HALAMAN SATU GURU
  $gid = $guruDilihat ? (int) $guruDilihat['id'] : 0;
  $dok = bk_dokumen_peta($sid, $gid);
  $prog = bk_progress($sid, $gid);
  $hasil = bk_supervisi_list(['sekolah' => $sid, 'guru' => $gid]);
  $csrf = csrf_field();
  ?>
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= ikon('user', 16) ?> <?= e(tetap_kata((string) ($guruDilihat['nama'] ?? ''))) ?></h2>
        <p>NIP: <?= e(trim((string) ($guruDilihat['nip'] ?? '')) !== '' ? (string) $guruDilihat['nip'] : '-') ?>
          <?= trim((string) ($guruDilihat['jabatan'] ?? '')) !== ''
              ? ' &middot; ' . e(tetap_kata((string) $guruDilihat['jabatan'])) : '' ?>
          &middot; <?= bk_butir_total() ?> butir penilaian</p>
      </div>
      <div class="flex">
        <span class="chip"><?= (int) $prog['terisi'] ?>/<?= (int) $prog['total'] ?> dokumen</span>
        <span class="chip"><?= count($hasil) ?> supervisi</span>
      </div>
    </div>
    <?php bk_progress_bar($prog, 'bk-progress-besar'); ?>
    <?php if ($bolehUbah && $prog['terisi'] < $prog['total']): ?>
      <p class="hint mt8"><?= ikon('info', 13) ?>
        Masih ada <b><?= (int) $prog['belum'] ?> butir</b> yang belum dikirim. Lengkapi agar pengawas
        dapat menilai seluruh butir instrumen supervisi.</p>
    <?php endif; ?>
  </div>

  <?php if ($bolehUbah): ?>
    <div class="card" id="formDokumen">
      <div class="card-head">
        <div>
          <h2><?= ikon('upload', 16) ?> Unggah Dokumen Butir Penilaian</h2>
          <p>Pilih butirnya, lalu unggah <b>satu berkas</b> (maksimal <?= e(dok_ukuran($batas)) ?>).
            Mengunggah ulang pada butir yang sama akan <b>menggantikan</b> berkas sebelumnya.</p>
        </div>
      </div>
      <form method="post" action="<?= e(bk_url($tugas)) ?>" enctype="multipart/form-data"
            class="bk-form" id="formDokumenBk">
        <?= $csrf ?>
        <input type="hidden" name="aksi" value="kirim">
        <div class="grid g21" style="gap:16px">
          <div>
            <label class="f" for="bkButir">Kegiatan / Komponen *</label>
            <select id="bkButir" name="butir" required>
              <option value="">— pilih butir penilaian —</option>
              <?php
              // Pilihan dibangun dari daftar butir (bukan dari struktur kelompok) supaya kodenya
              // sama dengan yang dipakai pada tabel & pesan: A.1 … C.1.a … E.1.
              $grupTerbuka = '';
              foreach (bk_butir_list() as $bb):
                  if ($bb['grup'] !== $grupTerbuka):
                      if ($grupTerbuka !== '') { echo '</optgroup>'; }
                      $grupTerbuka = $bb['grup'];
                      echo '<optgroup label="' . e($bb['grup'] . '. ' . $bb['grup_judul']) . '">';
                  endif;
              ?>
                <option value="<?= (int) $bb['no'] ?>"><?= e($bb['kode'] . ' — ' . $bb['label']) ?><?= isset($dok[$bb['no']]) ? ' (sudah dikirim)' : '' ?></option>
              <?php endforeach; ?>
              <?php if ($grupTerbuka !== '') { echo '</optgroup>'; } ?>
            </select>
          </div>
          <div>
            <label class="f" for="bkBerkas">Berkas Dokumen *</label>
            <input type="file" id="bkBerkas" name="berkas" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,.xls,.xlsx,.ppt,.pptx,.txt,.zip">
            <div class="hint">PDF, Word, Excel, gambar, atau dokumen lain — maksimal
              <?= e(dok_ukuran($batas)) ?> per berkas.</div>
          </div>
        </div>
        <div class="mt16">
          <label class="f" for="bkKeterangan">Keterangan Berkas (opsional)</label>
          <input type="text" id="bkKeterangan" name="keterangan" maxlength="<?= (int) BK_KET_MAKS ?>"
                 placeholder="mis. Program tahunan semester ganjil">
        </div>
        <div class="flex mt16">
          <button class="btn btn-primary" type="submit"><?= ikon('upload', 16) ?> Kirim Dokumen</button>
          <span class="hint">Dokumen langsung dapat dibaca Pengawas pada menu <b>Supervisi Guru BK</b>.</span>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <div class="card" id="tabelButir">
    <div class="card-head">
      <div>
        <h2><?= ikon('clipboard', 16) ?> Butir Penilaian &amp; Dokumen</h2>
        <p>Instrumen supervisi Bimbingan Konseling — 22 butir dalam 5 kelompok. Kolom
          <b>Dokumen</b> menunjukkan berkas yang sudah dikirim.</p>
      </div>
      <span class="chip"><?= (int) $prog['persen'] ?>% terkirim</span>
    </div>
    <?php
    bk_tabel_butir([
        'dok'         => $dok,
        'nama_berkas' => bk_nama_menu($tugas),
        'aksi_hapus'  => $bolehUbah,
        'csrf'        => $csrf,
        'form_aksi'   => bk_url($tugas),
    ]);
    ?>
  </div>

  <?php bk_kartu_hasil($hasil, false, true); ?>
<?php endif; ?>
    <?php
    page_bottom();
}

// ============================================================================
// HALAMAN PENGAWAS — SUPERVISI GURU BK
// ============================================================================

/**
 * Halaman **Supervisi Guru BK** (khusus akun pengawas).
 *
 * Daftar sekolah/madrasah binaan yang **punya Guru BK** → pilih binaan → daftar guru BK binaan itu
 * → formulir penilaian instrumen 22 butir → rekap seluruh supervisi binaan.
 */
function bk_pengawas_halaman(): void
{
    require_login();
    if (!is_pengawas()) {
        tolak403('Menu Supervisi Guru BK khusus akun Pengawas Sekolah/Madrasah. Guru Bimbingan '
            . 'Konseling mengirim dokumen lewat menu Tugas Tambahan "Bimbingan Konseling".');
    }
    $uid = (int) (current_user()['id'] ?? 0);
    $binaan = sekolah_binaan($uid, true);

    // ---------------------------------------------------------------- lepas / pindah konteks binaan
    // `?pilih=1` = kembali ke daftar binaan (dipakai tombol "Ganti Binaan"). Tanpa ini konteks
    // binaan tetap aktif sehingga halamannya terus menampilkan binaan yang sama.
    if (isset($_GET['pilih']) && current_sekolah()) {
        set_sekolah_context(null);
    }
    $idSekolah = (int) ($_GET['sekolah'] ?? 0);
    $konteks = current_sekolah();
    if ($idSekolah > 0 && (!$konteks || (int) $konteks['id'] !== $idSekolah)) {
        if (!pengawas_punya($uid, $idSekolah)) {
            tolak403('Sekolah/madrasah itu bukan binaan Anda.');
        }
        if (!one('SELECT id FROM sekolah WHERE id = ? AND aktif = 1 AND verifikasi = "disetujui"', [$idSekolah])) {
            flash('Sekolah/madrasah itu sedang tidak aktif sehingga tidak dapat disupervisi.', 'warn');
            redirect('supervisi_bk.php');
        }
        set_sekolah_context($idSekolah);
        flash('Anda kini menyupervisi ' . tetap_kata(nama_sekolah_tampil()) . '.');
        redirect('supervisi_bk.php');
    }

    // ---------------------------------------------------------------- POST
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        $sid = sekolah_id();
        if ($sid <= 0 || !pengawas_punya($uid, $sid)) {
            tolak403('Pilih sekolah/madrasah binaan terlebih dahulu.');
        }
        if ($aksi === 'simpan') {
            $hasil = bk_supervisi_simpan([
                'id'         => (int) ($_POST['id'] ?? 0),
                'sekolah_id' => $sid,
                'guru_id'    => (int) ($_POST['guru'] ?? 0),
                'tanggal'    => (string) ($_POST['tanggal'] ?? ''),
                'nilai'      => $_POST['nilai'] ?? [],
                'kesimpulan' => (string) ($_POST['kesimpulan'] ?? ''),
                'saran'      => (string) ($_POST['saran'] ?? ''),
            ]);
            flash($hasil['pesan'], $hasil['ok'] ? 'ok' : 'err');
            if ($hasil['ok']) {
                redirect('supervisi_bk.php?guru=' . (int) ($_POST['guru'] ?? 0)
                    . ($hasil['id'] > 0 ? '&lihat=' . (int) $hasil['id'] : ''));
            }
            redirect('supervisi_bk.php?guru=' . (int) ($_POST['guru'] ?? 0));
        }
        if ($aksi === 'hapus') {
            $id = (int) ($_POST['id'] ?? 0);
            $gid = (int) ($_POST['guru'] ?? 0);
            if (bk_supervisi_hapus($id, $sid)) {
                flash('Supervisi dihapus. Data guru & dokumen tidak terpengaruh.', 'warn');
            } else {
                flash('Supervisi itu tidak ditemukan pada sekolah/madrasah ini.', 'err');
            }
            redirect('supervisi_bk.php' . ($gid > 0 ? '?guru=' . $gid : ''));
        }
        flash('Aksi tidak dikenal.', 'err');
        redirect('supervisi_bk.php');
    }

    // ---------------------------------------------------------------- PEMILIH BINAAN
    if (!current_sekolah()) {
        $GLOBALS['__vs_istilah_nonaktif'] = true;
        $ringkas = [];
        $jumlahBk = 0;
        foreach ($binaan as $b) {
            $guru = bk_guru_bk((int) $b['id']);
            if (!$guru) {
                continue;
            }
            $jumlahBk += count($guru);
            $prog = 0;
            foreach ($guru as $g) {
                $p = bk_progress((int) $b['id'], (int) $g['id']);
                $prog += $p['terisi'];
            }
            $sup = bk_supervisi_list(['sekolah' => (int) $b['id']]);
            $ringkas[(int) $b['id']] = ['guru' => $guru, 'dok' => $prog, 'supervisi' => $sup];
        }
        // Rekapan lintas binaan. Dihitung hanya bila ada binaan — `sekolah_ids` kosong berarti
        // "tanpa saringan" pada bk_supervisi_list(), jadi jangan sampai kosong lalu menampilkan
        // supervisi milik sekolah yang bukan binaan pengawas ini.
        $semuaSup = $binaan
            ? bk_supervisi_list([
                'sekolah_ids' => array_map(static fn(array $b): int => (int) $b['id'], $binaan),
                'limit'       => 50,
            ])
            : [];
        page_top('Supervisi Guru BK', 'Pengawas Sekolah/Madrasah');
        ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('support', 16) ?> Supervisi Guru BK — Pilih Sekolah/Madrasah</h2>
      <p>Daftar di bawah hanya memuat <b>sekolah/madrasah binaan Anda yang punya Guru Bimbingan
        Konseling</b>. Pilih binaannya untuk menilai instrumen supervisi (22 butir) — hasilnya
        otomatis masuk ke akun guru Bimbingan Konseling sekolah itu.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($ringkas) ?> binaan berguru BK</span>
      <span class="chip"><?= (int) $jumlahBk ?> guru BK</span>
    </div>
  </div>
  <?php if (!$ringkas): ?>
    <div class="empty" style="padding:26px">
      <?= ikon('users', 34) ?>
      <h3>Belum ada binaan yang punya Guru BK</h3>
      <p>Menu Bimbingan Konseling belum ditugaskan kepada guru mana pun pada sekolah/madrasah
        binaan Anda (penugasan lewat <b>Data Guru → Tugas Tambahan</b> atau <b>Pengaturan Tugas
        Tambahan</b> di akun sekolah). Setelah ditugaskan, binaan itu otomatis muncul di sini.</p>
    </div>
  <?php else: ?>
    <div class="grid g2" style="gap:18px">
      <?php foreach ($ringkas as $sidB => $r): ?>
        <div class="card card-tight bk-binaan" style="margin:0">
          <div class="bk-binaan-kepala">
            <b><?= e(tetap_kata((string) (nama_sekolah_tampil(sekolah_by_id($sidB))))) ?></b>
            <span class="pill pill-ok"><?= count($r['guru']) ?> guru BK</span>
          </div>
          <div class="flex mt8" style="gap:8px;flex-wrap:wrap">
            <span class="chip"><?= (int) $r['dok'] ?> dokumen terkirim</span>
            <span class="chip"><?= count($r['supervisi']) ?> supervisi</span>
            <?php if ($r['supervisi']): ?>
              <span class="pill <?= bk_predikat_pil((string) $r['supervisi'][0]['predikat']) ?>">
                terakhir: <?= e($r['supervisi'][0]['predikat']) ?> &middot; <?= e(r1((float) $r['supervisi'][0]['nilai'])) ?>
              </span>
            <?php endif; ?>
          </div>
          <div class="mt16">
            <a class="btn btn-ok" href="supervisi_bk.php?sekolah=<?= (int) $sidB ?>">
              <?= ikon('clipboard', 15) ?> Buka &amp; Supervisi
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="divider"></div>
    <div class="section-title mb8"><?= ikon('chart', 15) ?> Rekapan Supervisi Guru BK (semua binaan)</div>
    <?php if (!$semuaSup): ?>
      <p class="hint">Belum ada supervisi yang tersimpan. Pilih sekolah/madrasah di atas untuk memulai.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl" id="tblRekapBkAll">
          <thead>
            <tr>
              <th class="ctr" style="width:44px">No</th>
              <th>Sekolah/Madrasah</th>
              <th>Guru BK</th>
              <th class="ctr" style="width:110px">Tanggal</th>
              <th class="ctr" style="width:80px">Skor</th>
              <th class="ctr" style="width:80px">Nilai</th>
              <th class="ctr" style="width:80px">Predikat</th>
              <th class="ctr" style="width:90px">Cetak</th>
            </tr>
          </thead>
          <tbody>
          <?php $n = 0; foreach ($semuaSup as $s): $n++; ?>
            <tr>
              <td class="ctr"><?= $n ?></td>
              <td><?= e(tetap_kata((string) ($s['sekolah_nama'] ?? '-'))) ?></td>
              <td><?= e(tetap_kata((string) ($s['guru_nama'] ?? '-'))) ?></td>
              <td class="ctr"><?= e(tanggal_id((string) $s['tanggal'])) ?></td>
              <td class="ctr"><?= (int) $s['total_skor'] ?>/<?= (int) $s['skor_maks'] ?></td>
              <td class="ctr"><?= e(r1((float) $s['nilai'])) ?></td>
              <td class="ctr"><span class="pill <?= bk_predikat_pil((string) $s['predikat']) ?>"><?= e((string) $s['predikat']) ?></span></td>
              <td class="ctr">
                <a class="btn btn-ghost btn-sm" href="cetak_supervisi_bk.php?id=<?= (int) $s['id'] ?>"
                   target="_blank" rel="noopener"><?= ikon('print', 13) ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
        <?php
        page_bottom();
        return;
    }

    // ---------------------------------------------------------------- HALAMAN BINAAN
    require_sekolah();
    $sid = sekolah_id();
    $namaSekolah = nama_sekolah_tampil();
    $guruBk = bk_guru_bk($sid);
    $idGuru = (int) ($_GET['guru'] ?? 0);
    if ($idGuru > 0 && !array_filter($guruBk, static fn(array $g): bool => (int) $g['id'] === $idGuru)) {
        flash('Guru itu bukan guru Bimbingan Konseling pada sekolah/madrasah ini.', 'err');
        redirect('supervisi_bk.php');
    }
    $idLihat = (int) ($_GET['lihat'] ?? 0);
    $semuaSup = bk_supervisi_list(['sekolah' => $sid]);
    $ringkasSup = bk_supervisi_ringkas($semuaSup);
    $csrf = csrf_field();

    page_top('Supervisi Guru BK', 'Pengawas Sekolah/Madrasah &middot; ' . tetap_kata($namaSekolah));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('support', 16) ?> Supervisi Bimbingan Konseling</h2>
      <p>Menilai <b>instrumen supervisi Bimbingan Konseling (22 butir)</b> milik
        <b><?= tetap_kata($namaSekolah) ?></b> — kolom <b>Ketersediaan</b> (Ada/Tidak),
        <b>Skor</b> (2/1/0), dan <b>Keterangan</b> per butir. Hasilnya otomatis masuk ke akun guru
        Bimbingan Konseling.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($guruBk) ?> guru BK</span>
      <span class="chip"><?= (int) $ringkasSup['jumlah'] ?> supervisi</span>
      <a class="btn btn-ghost btn-sm" href="supervisi_bk.php?pilih=1"><?= ikon('switch', 15) ?> Ganti Binaan</a>
    </div>
  </div>

  <div class="grid g4" style="gap:16px">
    <div class="card card-tight" style="margin:0"><div class="stat">
      <div class="stat-ic"><?= ikon('users', 18) ?></div>
      <div><div class="stat-num"><?= count($guruBk) ?></div><div class="stat-lbl">Guru BK</div></div>
    </div></div>
    <div class="card card-tight" style="margin:0"><div class="stat">
      <div class="stat-ic g"><?= ikon('chart', 18) ?></div>
      <div><div class="stat-num"><?= (int) $ringkasSup['jumlah'] ?></div><div class="stat-lbl">Supervisi tersimpan</div></div>
    </div></div>
    <div class="card card-tight" style="margin:0"><div class="stat">
      <div class="stat-ic o"><?= ikon('star', 18) ?></div>
      <div><div class="stat-num"><?= e(r1((float) $ringkasSup['rata'])) ?></div><div class="stat-lbl">Rata-rata nilai</div></div>
    </div></div>
    <div class="card card-tight" style="margin:0"><div class="stat">
      <div class="stat-ic s"><?= ikon('medal', 18) ?></div>
      <div><div class="stat-num">A <?= (int) $ringkasSup['A'] ?> &middot; B <?= (int) $ringkasSup['B'] ?></div>
        <div class="stat-lbl">Predikat A / B</div></div>
    </div></div>
  </div>
</div>

<?php if (!$guruBk): ?>
  <div class="card">
    <div class="empty" style="padding:26px">
      <?= ikon('users', 34) ?>
      <h3>Belum ada Guru BK pada binaan ini</h3>
      <p>Menu Bimbingan Konseling belum ditugaskan kepada guru mana pun di
        <?= tetap_kata('sekolah/madrasah') ?> ini, sehingga belum ada yang bisa disupervisi.</p>
    </div>
  </div>
<?php else: ?>
  <?php if ($idGuru === 0): ?>
    <div class="card" id="daftarGuruBk">
      <div class="card-head">
        <div>
          <h2><?= ikon('users', 16) ?> Guru Bimbingan Konseling</h2>
          <p>Pilih guru untuk dinilai. Tombol <b>Supervisi</b> membuka instrumen 22 butir; tombol
            <b>Riwayat</b> menampilkan penilaian yang sudah tersimpan.</p>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl" id="tblGuruBkPengawas">
          <thead>
            <tr>
              <th class="ctr" style="width:44px">No</th>
              <th>Nama Guru BK / Konselor</th>
              <th>Progres Dokumen</th>
              <th class="ctr" style="width:110px">Supervisi</th>
              <th class="ctr" style="width:130px">Nilai Terakhir</th>
              <th class="ctr" style="width:220px">Tindakan</th>
            </tr>
          </thead>
          <tbody>
          <?php $n = 0; foreach ($guruBk as $g): $n++;
              $r = bk_ringkas_guru($sid, (int) $g['id']); ?>
            <tr>
              <td class="ctr"><?= $n ?></td>
              <td><b><?= e(tetap_kata((string) $g['nama'])) ?></b>
                <div class="tbl-sub">NIP: <?= e(trim((string) $g['nip']) !== '' ? (string) $g['nip'] : '-') ?></div></td>
              <td style="min-width:190px"><?php bk_progress_bar($r['progress']); ?></td>
              <td class="ctr"><?= (int) $r['supervisi'] ?> kali</td>
              <td class="ctr">
                <?php if ($r['nilai'] === null): ?>
                  <span class="pill pill-warn">belum dinilai</span>
                <?php else: ?>
                  <span class="pill <?= bk_predikat_pil($r['predikat']) ?>">
                    <?= e($r['predikat']) ?> &middot; <?= e(r1($r['nilai'])) ?>
                  </span>
                  <div class="tbl-sub"><?= e(tanggal_id($r['tanggal'])) ?></div>
                <?php endif; ?>
              </td>
              <td class="ctr">
                <div class="row-actions">
                  <a class="btn btn-primary btn-sm" href="supervisi_bk.php?guru=<?= (int) $g['id'] ?>">
                    <?= ikon('clipboard', 14) ?> Supervisi
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <?php
  // ---------------------------------------------------------------- FORM / RINCIAN SATU GURU
  if ($idGuru > 0):
      $guru = null;
      foreach ($guruBk as $g) {
          if ((int) $g['id'] === $idGuru) {
              $guru = $g;
              break;
          }
      }
      $dok = bk_dokumen_peta($sid, $idGuru);
      $prog = bk_progress($sid, $idGuru);
      $riwayat = bk_supervisi_list(['sekolah' => $sid, 'guru' => $idGuru]);
      // Yang sedang dinilai: supervisi terpilih (?lihat=ID) atau supervisi terakhir guru ini.
      $diedit = null;
      if ($idLihat > 0) {
          foreach ($riwayat as $r) {
              if ((int) $r['id'] === $idLihat) {
                  $diedit = $r;
                  break;
              }
          }
      }
      if (!$diedit && ($_GET['baru'] ?? '') !== '1' && $riwayat) {
          $diedit = $riwayat[0];   // membuka halaman = menampilkan penilaian terakhir (bisa disunting)
      }
      $nilaiButir = $diedit ? bk_supervisi_nilai((int) $diedit['id']) : [];
      $tanggal = $diedit ? (string) $diedit['tanggal'] : date('Y-m-d');
      ?>
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= ikon('edit', 16) ?> Instrumen Supervisi — <?= e(tetap_kata((string) $guru['nama'])) ?></h2>
        <p><?= $diedit
              ? 'Menyunting penilaian tanggal <b>' . e(tanggal_id((string) $diedit['tanggal'], true)) . '</b>.'
              : 'Penilaian baru. Isi Ketersediaan, Skor (2/1/0), dan Keterangan tiap butir lalu simpan.' ?>
          Dokumen yang sudah dikirim guru dapat dibuka dari kolom <b>Dokumen</b>.</p>
      </div>
      <div class="flex">
        <a class="btn btn-ghost btn-sm" href="supervisi_bk.php"><?= ikon('prev', 15) ?> Daftar Guru BK</a>
        <?php if ($diedit): ?>
          <a class="btn btn-ghost btn-sm" href="supervisi_bk.php?guru=<?= $idGuru ?>&baru=1">
            <?= ikon('plus', 15) ?> Supervisi Baru
          </a>
        <?php endif; ?>
      </div>
    </div>
    <?php bk_progress_bar($prog); ?>
    <form method="post" action="supervisi_bk.php" id="formSupervisiBk" class="mt16">
      <?= $csrf ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= $diedit ? (int) $diedit['id'] : 0 ?>">
      <input type="hidden" name="guru" value="<?= $idGuru ?>">
      <div class="grid g3" style="gap:16px">
        <div>
          <label class="f" for="bkTanggal">Tanggal Supervisi *</label>
          <input type="date" id="bkTanggal" name="tanggal" required value="<?= e($tanggal) ?>">
        </div>
        <div>
          <label class="f">Guru BK / Konselor</label>
          <input type="text" value="<?= e(tetap_kata((string) $guru['nama'])) ?>" readonly>
        </div>
        <div>
          <label class="f">NIP</label>
          <input type="text" value="<?= e(trim((string) $guru['nip']) !== '' ? (string) $guru['nip'] : '-') ?>" readonly>
        </div>
      </div>
      <?php
      bk_tabel_butir([
          'dok'       => $dok,
          'nilai'     => $nilaiButir,
          'penilaian' => true,
          'csrf'      => $csrf,
          'form_aksi' => 'supervisi_bk.php',
      ]);
      ?>
      <div class="grid g2 mt16" style="gap:16px">
        <div>
          <label class="f" for="bkKesimpulan">Kesimpulan</label>
          <textarea id="bkKesimpulan" name="kesimpulan" rows="4" maxlength="<?= (int) BK_KESIMPULAN_MAKS ?>"
                    placeholder="Kesimpulan hasil supervisi Bimbingan Konseling"><?= e(tetap_kata((string) ($diedit['kesimpulan'] ?? ''))) ?></textarea>
        </div>
        <div>
          <label class="f" for="bkSaran">Saran</label>
          <textarea id="bkSaran" name="saran" rows="4" maxlength="<?= (int) BK_KESIMPULAN_MAKS ?>"
                    placeholder="Saran / tindak lanjut"><?= e(tetap_kata((string) ($diedit['saran'] ?? ''))) ?></textarea>
        </div>
      </div>
      <p class="hint mt8">Skor tiap butir 0–2 (maksimal <?= bk_skor_maks() ?> untuk
        <?= bk_butir_total() ?> butir). Nilai akhir = total skor / <?= bk_skor_maks() ?> x 100,
        predikat mengikuti ambang sekolah/madrasah ini.</p>
      <div class="flex mt16">
        <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> Simpan Supervisi</button>
        <?php if ($diedit): ?>
          <a class="btn btn-ghost" href="cetak_supervisi_bk.php?id=<?= (int) $diedit['id'] ?>" target="_blank" rel="noopener">
            <?= ikon('print', 16) ?> Cetak Lembar Supervisi
          </a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card" id="riwayatSupervisi">
    <div class="card-head">
      <div>
        <h2><?= ikon('chart', 16) ?> Riwayat Supervisi Guru Ini</h2>
        <p>Seluruh penilaian yang pernah disimpan untuk guru Bimbingan Konseling ini.</p>
      </div>
      <span class="chip"><?= count($riwayat) ?> supervisi</span>
    </div>
    <?php if (!$riwayat): ?>
      <p class="hint">Belum ada supervisi tersimpan untuk guru ini.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl" id="tblRiwayatBk">
          <thead>
            <tr>
              <th class="ctr" style="width:44px">No</th>
              <th class="ctr" style="width:130px">Tanggal</th>
              <th class="ctr" style="width:90px">Skor</th>
              <th class="ctr" style="width:90px">Nilai</th>
              <th class="ctr" style="width:90px">Predikat</th>
              <th>Kesimpulan</th>
              <th class="ctr" style="width:210px">Tindakan</th>
            </tr>
          </thead>
          <tbody>
          <?php $n = 0; foreach ($riwayat as $r): $n++; ?>
            <tr<?= $diedit && (int) $diedit['id'] === (int) $r['id'] ? ' class="baris-terpilih"' : '' ?>>
              <td class="ctr"><?= $n ?></td>
              <td class="ctr"><?= e(tanggal_id((string) $r['tanggal'])) ?></td>
              <td class="ctr"><?= (int) $r['total_skor'] ?>/<?= (int) $r['skor_maks'] ?></td>
              <td class="ctr"><?= e(r1((float) $r['nilai'])) ?></td>
              <td class="ctr"><span class="pill <?= bk_predikat_pil((string) $r['predikat']) ?>"><?= e((string) $r['predikat']) ?></span></td>
              <td class="xs"><?= e(tetap_kata(bk_potong((string) $r['kesimpulan'], 90))) ?></td>
              <td class="ctr">
                <div class="row-actions">
                  <a class="btn btn-ghost btn-sm" href="supervisi_bk.php?guru=<?= $idGuru ?>&lihat=<?= (int) $r['id'] ?>"><?= ikon('edit', 13) ?> Ubah</a>
                  <a class="btn btn-ghost btn-sm" href="cetak_supervisi_bk.php?id=<?= (int) $r['id'] ?>" target="_blank" rel="noopener"><?= ikon('print', 13) ?> Cetak</a>
                  <form method="post" action="supervisi_bk.php" class="inline" data-confirm="Hapus supervisi ini?">
                    <?= $csrf ?>
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="guru" value="<?= $idGuru ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 13) ?> Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php // ---------------------------------------------------------------- REKAP BINAAN ?>
  <div class="card" id="rekapBk">
    <div class="card-head">
      <div>
        <h2><?= ikon('chart', 16) ?> Rekapan Supervisi Guru BK — <?= tetap_kata($namaSekolah) ?></h2>
        <p>Seluruh supervisi Bimbingan Konseling pada binaan ini beserta nilai &amp; predikatnya.</p>
      </div>
      <span class="chip"><?= count($semuaSup) ?> supervisi</span>
    </div>
    <?php if (!$semuaSup): ?>
      <p class="hint">Belum ada supervisi tersimpan pada binaan ini.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl" id="tblRekapBk">
          <thead>
            <tr>
              <th class="ctr" style="width:44px">No</th>
              <th>Guru BK</th>
              <th class="ctr" style="width:120px">Tanggal</th>
              <th class="ctr" style="width:80px">Skor</th>
              <th class="ctr" style="width:80px">Nilai</th>
              <th class="ctr" style="width:90px">Predikat</th>
              <th>Pengawas</th>
              <th class="ctr" style="width:150px">Cetak</th>
            </tr>
          </thead>
          <tbody>
          <?php $n = 0; foreach ($semuaSup as $r): $n++; ?>
            <tr>
              <td class="ctr"><?= $n ?></td>
              <td><?= e(tetap_kata((string) ($r['guru_nama'] ?? '-'))) ?></td>
              <td class="ctr"><?= e(tanggal_id((string) $r['tanggal'])) ?></td>
              <td class="ctr"><?= (int) $r['total_skor'] ?>/<?= (int) $r['skor_maks'] ?></td>
              <td class="ctr"><?= e(r1((float) $r['nilai'])) ?></td>
              <td class="ctr"><span class="pill <?= bk_predikat_pil((string) $r['predikat']) ?>"><?= e((string) $r['predikat']) ?></span></td>
              <td class="xs"><?= e((string) $r['pengawas_nama']) ?></td>
              <td class="ctr">
                <a class="btn btn-ghost btn-sm" href="cetak_supervisi_bk.php?id=<?= (int) $r['id'] ?>"
                   target="_blank" rel="noopener"><?= ikon('print', 13) ?> Cetak</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" class="r"><b>Rata-rata</b></td>
              <td class="ctr" colspan="2"><b><?= e(r1((float) $ringkasSup['rata'])) ?></b></td>
              <td class="ctr">A <?= (int) $ringkasSup['A'] ?> &middot; B <?= (int) $ringkasSup['B'] ?>
                &middot; C <?= (int) $ringkasSup['C'] ?> &middot; D <?= (int) $ringkasSup['D'] ?></td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
    <?php
    page_bottom();
}
