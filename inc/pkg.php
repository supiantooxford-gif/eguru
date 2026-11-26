<?php
/**
 * MODUL PKG — PENILAIAN KINERJA GURU (akun KEPALA SEKOLAH/MADRASAH) serta hasilnya untuk akun GURU
 * dan akun PENGAWAS.
 *
 * Permintaan pemilik: *"Pada akun kepala sekolah/madrasah tambahkan menu PKG (yang saya tandai warna
 * merah). Dan pada akun Guru tambahkan menu Hasil PKG (yang saya tandai warna biru). Menu PKG
 * (Penilaian Kinerja Guru) pada akun kepala sekolah digunakan untuk menilai setiap guru pada
 * lembaganya sendiri (Instrumen penilaian seperti yang saya upload) dan hasil penilaiannya masuk ke
 * menu Hasil PKG pada akun guru. Selain itu pengawas juga bisa melihat hasil PKG setiap guru
 * binaannya."*
 *
 * Keputusan yang dipakai (jangan diubah tanpa bertanya kepada pemilik):
 *   1. **Instrumen = 13 butir / 4 dimensi** persis berkas "Instrumen PKG tahun 2026" yang dikirim
 *      pemilik (A Perencanaan & Pelaksanaan Pembelajaran Berdiferensiasi 4 butir, B Manajemen Kelas &
 *      Budaya Positif 3 butir, C Asesmen, Umpan Balik & Pelaporan 3 butir, D Pengembangan Profesi &
 *      Perilaku Kerja (BerAKHLAK) 3 butir). Daftarnya ada di KODE (`pkg_instrumen()`), BUKAN di basis
 *      data — nilai & butirnya tidak dapat diubah pengguna dan perbaikan ejaan tidak butuh migrasi.
 *   2. **Skala 1-4** dengan arti yang tercetak pada lembar (1 Belum Memenuhi Standard … 4 Melampaui
 *      Standard). Skor maksimal = 13 x 4 = **52**; persentase = total / 52 x 100.
 *   3. **Predikat** memakai ambang instrumen pemilik (BUKAN ambang A/B/C/D menu Penilaian Siswa):
 *      86-100% Sangat Baik · 70-85% Baik · 55-69% Cukup · < 55% Kurang.
 *   4. **Satu penilaian per guru per periode** (tahun ajaran x semester). Menilai ulang pada periode
 *      yang sama MEMPERBARUI penilaian itu (kepala dapat memperbaiki isiannya).
 *   5. **Penilai = akun kepala sekolah/madrasah** lembaga itu (Super Admin dalam konteks sekolah juga
 *      boleh). Admin Operator & guru tidak dapat menilai.
 *   6. **Guru membaca hasilnya** pada menu "Hasil PKG" (hanya hasil miliknya sendiri), dan **pengawas
 *      membaca hasil PKG setiap guru binaannya** (hanya melihat & mencetak).
 *
 * ⚠️ Teks yang DIKETIK pengguna (catatan butir, kelebihan, area perbaikan, rencana tindak lanjut)
 * dibungkus `tetap_kata()` saat dicetak supaya tidak ikut penyesuaian istilah "Sekolah" → "Madrasah".
 */
declare(strict_types=1);

/** Batas panjang isian (dipotong, bukan ditolak) — catatan butir & catatan RTL. */
const PKG_CATATAN_MAKS = 500;
const PKG_RTL_MAKS = 2000;
const PKG_ISIAN_MAKS = 160;

// ============================================================================
// INSTRUMEN (13 butir, 4 dimensi) — persis berkas "Instrumen PKG tahun 2026"
// ============================================================================

/**
 * Susunan instrumen: dimensi (A..D) → butir.
 *
 * Setiap butir berbentuk:
 *   ['no' => 1, 'judul' => '…', 'uraian' => '…', 'bukti' => '…']
 *
 * `judul` = nama indikator, `uraian` = kalimat indikator pada instrumen, `bukti` = kolom
 * "Catatan / Bukti Fisik" (saran bukti yang dicatat kepala sekolah/madrasah).
 *
 * @return array<int,array{kode:string,nama:string,ringkas:string,maks:int,butir:array<int,array<string,mixed>>}>
 */
function pkg_instrumen(): array
{
    return [
        ['kode' => 'A', 'nama' => 'Perencanaan & Pelaksanaan Pembelajaran Berdiferensiasi',
         'ringkas' => 'Perencanaan & Pelaksanaan Pembelajaran', 'maks' => 16, 'butir' => [
            ['no' => 1, 'judul' => 'Pemetaan Kebutuhan Belajar',
             'uraian' => 'Guru mengidentifikasi kesiapan, minat, dan profil belajar murid sebelum '
                . 'penyusunan modul/RPP.',
             'bukti' => 'Hasil asesmen diagnostik / awal'],
            ['no' => 2, 'judul' => 'Desain Pembelajaran',
             'uraian' => 'Memiliki Modul Ajar/RPP yang mencakup tujuan, strategi diferensiasi '
                . '(konten/proses/produk), serta asesmen yang jelas.',
             'bukti' => 'Dokumen Modul Ajar / RPP'],
            ['no' => 3, 'judul' => 'Penerapan Strategi Interaktif',
             'uraian' => 'Memfasilitasi pembelajaran aktif, kolaboratif, dan berpusat pada peserta didik.',
             'bukti' => 'Observasi Kelas / Pelaksanaan'],
            ['no' => 4, 'judul' => 'Pemanfaatan Teknologi & Media',
             'uraian' => 'Menggunakan media pembelajaran digital/kontekstual yang relevan untuk '
                . 'mendukung pemahaman murid.',
             'bukti' => 'Penggunaan ICT / Alat Peraga'],
         ]],
        ['kode' => 'B', 'nama' => 'Manajemen Kelas & Budaya Positif',
         'ringkas' => 'Manajemen Kelas & Budaya Positif', 'maks' => 12, 'butir' => [
            ['no' => 5, 'judul' => 'Kesepakatan / Keyakinan Kelas',
             'uraian' => 'Membangun budaya disiplin positif berbasis kesepakatan bersama murid.',
             'bukti' => 'Dokumentasi Keyakinan Kelas'],
            ['no' => 6, 'judul' => 'Lingkungan Belajar Inklusif & Aman',
             'uraian' => 'Menjamin lingkungan belajar yang bebas dari perundungan, kekerasan, serta '
                . 'ramah anak.',
             'bukti' => 'Observasi Lingkungan Kelas'],
            ['no' => 7, 'judul' => 'Pengelolaan Dinamika Kelas',
             'uraian' => 'Mengendalikan situasi kelas secara konstruktif dan responsif tanpa kekerasan '
                . 'verbal/fisik.',
             'bukti' => 'Catatan Kejadian / Observasi'],
         ]],
        ['kode' => 'C', 'nama' => 'Asesmen, Umpan Balik, & Pelaporan',
         'ringkas' => 'Asesmen, Umpan Balik, & Pelaporan', 'maks' => 12, 'butir' => [
            ['no' => 8, 'judul' => 'Asesmen Formatif & Sumatif',
             'uraian' => 'Melaksanakan asesmen secara berkala dan bervariasi sesuai pencapaian '
                . 'kompetensi murid.',
             'bukti' => 'Rubrik & Dokumen Asesmen'],
            ['no' => 9, 'judul' => 'Umpan Balik Konstruktif',
             'uraian' => 'Memberikan masukan spesifik dan membangun pada lembar kerja / tugas murid.',
             'bukti' => 'Sampel Hasil Pekerjaan Murid'],
            ['no' => 10, 'judul' => 'Refleksi & Pelaporan',
             'uraian' => 'Mengomunikasikan kemajuan belajar siswa secara terbuka dan berkala kepada '
                . 'orang tua.',
             'bukti' => 'Rapor / Catatan Komunikasi Ortuk'],
         ]],
        ['kode' => 'D', 'nama' => 'Pengembangan Profesi & Perilaku Kerja (BerAKHLAK)',
         'ringkas' => 'Pengembangan Profesi & Perilaku Kerja', 'maks' => 12, 'butir' => [
            ['no' => 11, 'judul' => 'Pengembangan Diri',
             'uraian' => 'Aktif mengikuti pelatihan, komunitas belajar (Kombel), atau berbagi praktik baik.',
             'bukti' => 'Sertifikat / Bukti Karya / PMM'],
            ['no' => 12, 'judul' => 'Refleksi Praktik Mengajar',
             'uraian' => 'Memiliki catatan refleksi diri secara rutin untuk perbaikan pembelajaran '
                . 'berikutnya.',
             'bukti' => 'Jurnal Refleksi Diri'],
            ['no' => 13, 'judul' => 'Perilaku Kerja (BerAKHLAK)',
             'uraian' => 'Menunjukkan sikap Berorientasi Pelayanan, Akuntabel, Kompeten, Harmonis, '
                . 'Loyal, Adaptif, dan Kolaboratif.',
             'bukti' => 'Pengamatan Atasan & Rekan'],
         ]],
    ];
}

/**
 * Daftar butir RATA (13 butir) dengan kode dimensinya — dipakai perhitungan, penyimpanan, &
 * lembar cetak.
 *
 * @return array<int,array{no:int,kode:string,judul:string,uraian:string,bukti:string}>
 */
function pkg_butir_list(): array
{
    static $datar = null;
    if (is_array($datar)) {
        return $datar;
    }
    $datar = [];
    foreach (pkg_instrumen() as $d) {
        foreach ($d['butir'] as $b) {
            $datar[] = ['no' => (int) $b['no'], 'kode' => (string) $d['kode'],
                        'judul' => (string) $b['judul'], 'uraian' => (string) $b['uraian'],
                        'bukti' => (string) $b['bukti']];
        }
    }
    return $datar;
}

/** Satu butir berdasarkan nomornya (1..13); null bila di luar jangkauan. */
function pkg_butir(int $no): ?array
{
    foreach (pkg_butir_list() as $b) {
        if ($b['no'] === $no) {
            return $b;
        }
    }
    return null;
}

/** Jumlah butir instrumen (13). */
function pkg_butir_total(): int
{
    return count(pkg_butir_list());
}

/** Skor maksimal seluruh butir (13 x 4 = 52). */
function pkg_skor_maks(): int
{
    return pkg_butir_total() * 4;
}

/** Arti tiap nilai skala 1-4 — persis legenda pada instrumen pemilik. */
function pkg_skala_list(): array
{
    return [
        1 => 'Belum Memenuhi Standard (Perlu Pembinaan Khusus)',
        2 => 'Mendekati Standard (Perlu Penyesuaian)',
        3 => 'Memenuhi Standard (Sesuai Ekspektasi)',
        4 => 'Melampaui Standard (Di Atas Ekspektasi)',
    ];
}

/** Skala yang sah? (1, 2, 3, atau 4) */
function pkg_skala_sah($skor): bool
{
    return in_array((int) $skor, [1, 2, 3, 4], true) && (string) (int) $skor === trim((string) $skor);
}

/** Label pendek skala untuk pil pada tabel ("1" … "4"); '-' bila belum dinilai. */
function pkg_skala_teks($skor): string
{
    $s = (int) $skor;
    return $s >= 1 && $s <= 4 ? (string) $s : '-';
}

// ============================================================================
// PERHITUNGAN & PREDIKAT
// ============================================================================

/**
 * Jumlah skor tiap dimensi + total dari peta skor butir.
 *
 * @param array<int,int|null> $skorPerButir [butir_no => skor|null]
 * @return array{a:int,b:int,c:int,d:int,total:int,maks:int,persen:float,predikat:string,terisi:int}
 */
function pkg_hitung(array $skorPerButir): array
{
    $perDimensi = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
    $total = 0;
    $terisi = 0;
    foreach (pkg_butir_list() as $b) {
        $skor = $skorPerButir[$b['no']] ?? null;
        if ($skor === null || $skor === '' || !pkg_skala_sah($skor)) {
            continue;
        }
        $perDimensi[$b['kode']] += (int) $skor;
        $total += (int) $skor;
        $terisi++;
    }
    $maks = pkg_skor_maks();
    $persen = $maks > 0 ? round($total / $maks * 100, 2) : 0.0;
    return [
        'a' => $perDimensi['A'], 'b' => $perDimensi['B'],
        'c' => $perDimensi['C'], 'd' => $perDimensi['D'],
        'total' => $total, 'maks' => $maks, 'persen' => $persen,
        'predikat' => $terisi === 0 ? '-' : pkg_predikat($persen),
        'terisi' => $terisi,
    ];
}

/** Persentase capaian (0..100) dari total skor — dibulatkan 2 desimal. */
function pkg_persen_dari(int $total): float
{
    $maks = pkg_skor_maks();
    return $maks > 0 ? round($total / $maks * 100, 2) : 0.0;
}

/**
 * Predikat kinerja akhir memakai AMBANG INSTRUMEN PKG (bukan ambang A/B/C/D menu Penilaian Siswa):
 *   86-100% Sangat Baik · 70-85% Baik · 55-69% Cukup · < 55% Kurang.
 */
function pkg_predikat(float $persen): string
{
    if ($persen >= 86) {
        return 'Sangat Baik';
    }
    if ($persen >= 70) {
        return 'Baik';
    }
    if ($persen >= 55) {
        return 'Cukup';
    }
    return 'Kurang';
}

/** Keterangan panjang tiap predikat (persis kategori pada instrumen pemilik). */
function pkg_predikat_label(string $p): string
{
    switch ($p) {
        case 'Sangat Baik': return 'Di Atas Ekspektasi';
        case 'Baik':        return 'Sesuai Ekspektasi';
        case 'Cukup':       return 'Perlu Pembinaan';
        case 'Kurang':      return 'Sangat Perlu Pembinaan';
    }
    return '-';
}

/** Kelas pil tema untuk predikat PKG. */
function pkg_predikat_pil(string $p): string
{
    switch ($p) {
        case 'Sangat Baik': return 'pill-ok';
        case 'Baik':        return 'pill-ok';
        case 'Cukup':       return 'pill-warn';
        case 'Kurang':      return 'pill-D';
    }
    return 'pill-uji';
}

/** Kalimat ambang predikat untuk ditampilkan pada formulir & lembar cetak. */
function pkg_predikat_keterangan(): string
{
    return '86% - 100%: Sangat Baik (Di Atas Ekspektasi) - 70% - 85%: Baik (Sesuai Ekspektasi) - '
        . '55% - 69%: Cukup (Perlu Pembinaan) - di bawah 55%: Kurang (Sangat Perlu Pembinaan)';
}

/** Potong teks panjang untuk tampilan tabel (tanpa ketergantungan mbstring). */
function pkg_potong(string $teks, int $maks = 90): string
{
    $teks = trim($teks);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($teks) > $maks ? rtrim(mb_substr($teks, 0, $maks)) . '…' : $teks;
    }
    return strlen($teks) > $maks ? rtrim(substr($teks, 0, $maks)) . '…' : $teks;
}

/** Potong isian pendek (NIP, pangkat, mapel/kelas, tempat) ke batas kolomnya. */
function pkg_isian_potong(string $teks): string
{
    $teks = trim($teks);
    if (function_exists('mb_substr')) {
        return mb_substr($teks, 0, PKG_ISIAN_MAKS);
    }
    return substr($teks, 0, PKG_ISIAN_MAKS);
}

// ============================================================================
// BACA / TULIS BASIS DATA
// ============================================================================

/**
 * Daftar penilaian PKG.
 *
 * @param array<string,mixed> $f sekolah (0 = semua), guru, ta, semester, sekolah_ids, limit
 * @return array<int,array<string,mixed>>
 */
function pkg_list(array $f = []): array
{
    $w = [];
    $p = [];
    $sid = (int) ($f['sekolah'] ?? 0);
    if ($sid > 0) {
        $w[] = 'p.sekolah_id = ?';
        $p[] = $sid;
    }
    $gid = (int) ($f['guru'] ?? 0);
    if ($gid > 0) {
        $w[] = 'p.guru_id = ?';
        $p[] = $gid;
    }
    $taId = (int) ($f['ta'] ?? 0);
    if ($taId > 0) {
        $w[] = 'p.ta_id = ?';
        $p[] = $taId;
    }
    $semId = (int) ($f['semester'] ?? 0);
    if ($semId > 0) {
        $w[] = 'p.semester_id = ?';
        $p[] = $semId;
    }
    $ids = $f['sekolah_ids'] ?? [];
    if (is_array($ids) && $ids) {
        $ids = array_values(array_map('intval', $ids));
        $w[] = 'p.sekolah_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $p = array_merge($p, $ids);
    }
    $sql = 'SELECT p.*, g.nama AS guru_nama, g.nip AS guru_nip, g.nuptk AS guru_nuptk,
                   g.jabatan AS guru_jabatan, sk.nama AS sekolah_nama, sk.jenis AS sekolah_jenis
            FROM pkg_penilaian p
            LEFT JOIN guru g ON g.id = p.guru_id
            LEFT JOIN sekolah sk ON sk.id = p.sekolah_id'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY p.tanggal DESC, p.id DESC';
    $limit = (int) ($f['limit'] ?? 0);
    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }
    return all($sql, $p);
}

/** Satu penilaian berdasarkan id (dibatasi sekolah yang boleh dilihat). */
function pkg_by_id(int $id, array $sekolahIds = []): ?array
{
    if ($id <= 0) {
        return null;
    }
    $sql = 'SELECT p.*, g.nama AS guru_nama, g.nip AS guru_nip, g.nuptk AS guru_nuptk,
                   g.jabatan AS guru_jabatan, sk.nama AS sekolah_nama, sk.jenis AS sekolah_jenis
            FROM pkg_penilaian p
            LEFT JOIN guru g ON g.id = p.guru_id
            LEFT JOIN sekolah sk ON sk.id = p.sekolah_id
            WHERE p.id = ?';
    $p = [$id];
    if ($sekolahIds) {
        $ids = array_values(array_map('intval', $sekolahIds));
        $sql .= ' AND p.sekolah_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $p = array_merge($p, $ids);
    }
    return one($sql, $p);
}

/**
 * Nilai per butir sebuah penilaian: [butir_no => ['skor' => int|null, 'catatan' => string]].
 *
 * @return array<int,array<string,mixed>>
 */
function pkg_nilai_peta(int $pkgId): array
{
    if ($pkgId <= 0) {
        return [];
    }
    $peta = [];
    foreach (all('SELECT * FROM pkg_nilai WHERE pkg_id = ?', [$pkgId]) as $n) {
        $peta[(int) $n['butir_no']] = $n;
    }
    return $peta;
}

/**
 * Simpan penilaian PKG (baru, atau perbarui penilaian periode yang sama) beserta skor tiap butir.
 *
 * `$data['nilai']` = [butir_no => ['skor' => '1'..'4'|'', 'catatan' => '…']].
 * SELURUH 13 butir wajib diberi skor — instrumen yang belum lengkap tidak disimpan supaya hasilnya
 * tidak pernah tercetak setengah jadi.
 *
 * @return array{ok:bool,pesan:string,id:int}
 */
function pkg_simpan(array $data): array
{
    $sekolahId = (int) ($data['sekolah_id'] ?? 0);
    $guruId = (int) ($data['guru_id'] ?? 0);
    $guru = $guruId > 0 && $sekolahId > 0
        ? one('SELECT * FROM guru WHERE id = ? AND sekolah_id = ?', [$guruId, $sekolahId]) : null;
    if (!$guru) {
        return ['ok' => false, 'id' => 0,
                'pesan' => 'Guru yang akan dinilai tidak ditemukan pada sekolah/madrasah ini.'];
    }
    // Periode wajib milik sekolah ini (anti-IDOR) — ta_by_id()/semester_by_id() sudah menyaring
    // sekolah aktif, jadi id dari sekolah lain otomatis menjadi null.
    $ta = ta_by_id((int) ($data['ta_id'] ?? 0));
    $sem = semester_by_id((int) ($data['semester_id'] ?? 0));
    if (!$ta || !$sem || (int) $sem['ta_id'] !== (int) $ta['id']) {
        return ['ok' => false, 'id' => 0,
                'pesan' => 'Tahun ajaran / semester penilaian belum dipilih dengan benar. Atur dulu '
                    . 'periodenya pada menu Tahun Ajaran & Semester.'];
    }
    $tanggal = tgl_ok($data['tanggal'] ?? '');
    if ($tanggal === null || $tanggal === '') {
        return ['ok' => false, 'id' => 0, 'pesan' => 'Tanggal penilaian wajib diisi dengan format yang benar.'];
    }

    $nilai = is_array($data['nilai'] ?? null) ? $data['nilai'] : [];
    $bersih = [];
    $skorPeta = [];
    $kurang = [];
    foreach (pkg_butir_list() as $b) {
        $row = $nilai[$b['no']] ?? [];
        $skorRaw = $row['skor'] ?? '';
        $catatan = trim((string) ($row['catatan'] ?? ''));
        if (function_exists('mb_substr')) {
            $catatan = mb_substr($catatan, 0, PKG_CATATAN_MAKS);
        } else {
            $catatan = substr($catatan, 0, PKG_CATATAN_MAKS);
        }
        $skor = ($skorRaw !== '' && $skorRaw !== null && pkg_skala_sah($skorRaw)) ? (int) $skorRaw : null;
        if ($skor === null) {
            $kurang[] = $b['no'];
        }
        $skorPeta[$b['no']] = $skor;
        $bersih[$b['no']] = ['skor' => $skor, 'catatan' => $catatan];
    }
    if ($kurang) {
        return ['ok' => false, 'id' => 0,
                'pesan' => 'Penilaian belum lengkap: butir nomor ' . implode(', ', $kurang)
                    . ' belum diberi skor (1-4). Lengkapi seluruh ' . pkg_butir_total()
                    . ' butir lalu simpan kembali.'];
    }

    $hitung = pkg_hitung($skorPeta);
    $rtl = static function (string $kunci) use ($data): string {
        $v = trim((string) ($data[$kunci] ?? ''));
        return function_exists('mb_substr') ? mb_substr($v, 0, PKG_RTL_MAKS) : substr($v, 0, PKG_RTL_MAKS);
    };
    $kelebihan = $rtl('kelebihan');
    $perbaikan = $rtl('perbaikan');
    $rencana = $rtl('rtl');
    $pangkat = pkg_isian_potong((string) ($data['pangkat'] ?? ''));
    $mapelKelas = pkg_isian_potong((string) ($data['mapel_kelas'] ?? ''));
    $tempat = pkg_isian_potong((string) ($data['tempat'] ?? ''));
    $penilaiNip = pkg_isian_potong((string) ($data['penilai_nip'] ?? ''));
    $u = current_user();
    $penilaiId = (int) ($u['id'] ?? 0);
    $penilaiNama = (string) ($u['nama'] ?? '');
    $penilaiJabatan = trim((string) ($data['penilai_jabatan'] ?? ''));
    if ($penilaiJabatan === '') {
        $penilaiJabatan = role_label();
    }
    $now = date('Y-m-d H:i:s');

    // Baris yang sudah ada untuk periode ini → DIPERBARUI (satu penilaian per guru per periode).
    $lama = one('SELECT * FROM pkg_penilaian WHERE sekolah_id = ? AND guru_id = ? AND ta_id = ?
                 AND semester_id = ?', [$sekolahId, $guruId, (int) $ta['id'], (int) $sem['id']]);
    $idKirim = (int) ($data['id'] ?? 0);
    if ($idKirim > 0) {
        // Menyunting id tertentu: baris itu wajib milik sekolah ini (anti-IDOR).
        $cek = one('SELECT * FROM pkg_penilaian WHERE id = ? AND sekolah_id = ?', [$idKirim, $sekolahId]);
        if (!$cek) {
            return ['ok' => false, 'id' => 0,
                    'pesan' => 'Penilaian yang akan diubah tidak ditemukan pada sekolah/madrasah ini.'];
        }
        $lama = $cek;
    }

    db()->exec('BEGIN IMMEDIATE');
    try {
        if ($lama) {
            $pkgId = (int) $lama['id'];
            q('UPDATE pkg_penilaian SET guru_id = ?, ta_id = ?, semester_id = ?, ta_nama = ?,
                     semester_nama = ?, pangkat = ?, mapel_kelas = ?, penilai_id = ?, penilai_nama = ?,
                     penilai_jabatan = ?, penilai_nip = ?, tempat = ?, tanggal = ?, skor_a = ?,
                     skor_b = ?, skor_c = ?, skor_d = ?, total_skor = ?, skor_maks = ?, persen = ?,
                     predikat = ?, kelebihan = ?, perbaikan = ?, rtl = ?, diubah = ?
               WHERE id = ?',
                [$guruId, (int) $ta['id'], (int) $sem['id'], (string) $ta['nama'],
                 (string) $sem['nama'], $pangkat, $mapelKelas, $penilaiId, $penilaiNama,
                 $penilaiJabatan, $penilaiNip, $tempat, $tanggal, $hitung['a'], $hitung['b'],
                 $hitung['c'], $hitung['d'], $hitung['total'], $hitung['maks'], $hitung['persen'],
                 $hitung['predikat'], $kelebihan, $perbaikan, $rencana, $now, $pkgId]);
            q('DELETE FROM pkg_nilai WHERE pkg_id = ?', [$pkgId]);
        } else {
            q('INSERT INTO pkg_penilaian (sekolah_id, guru_id, ta_id, semester_id, ta_nama,
                     semester_nama, pangkat, mapel_kelas, penilai_id, penilai_nama, penilai_jabatan,
                     penilai_nip, tempat, tanggal, skor_a, skor_b, skor_c, skor_d, total_skor,
                     skor_maks, persen, predikat, kelebihan, perbaikan, rtl, dibuat, diubah)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$sekolahId, $guruId, (int) $ta['id'], (int) $sem['id'], (string) $ta['nama'],
                 (string) $sem['nama'], $pangkat, $mapelKelas, $penilaiId, $penilaiNama,
                 $penilaiJabatan, $penilaiNip, $tempat, $tanggal, $hitung['a'], $hitung['b'],
                 $hitung['c'], $hitung['d'], $hitung['total'], $hitung['maks'], $hitung['persen'],
                 $hitung['predikat'], $kelebihan, $perbaikan, $rencana, $now, $now]);
            $pkgId = (int) db()->lastInsertId();
        }
        $ins = db()->prepare('INSERT INTO pkg_nilai (pkg_id, butir_no, skor, catatan) VALUES (?,?,?,?)');
        foreach ($bersih as $no => $n) {
            $ins->execute([$pkgId, $no, $n['skor'], $n['catatan']]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        error_log('e-GURU PKG gagal disimpan: ' . $ex->getMessage());
        return ['ok' => false, 'id' => 0,
                'pesan' => 'Penilaian gagal disimpan karena galat pada server. Coba lagi.'];
    }

    return ['ok' => true, 'id' => $pkgId,
            'pesan' => 'PKG untuk ' . tetap_kata((string) $guru['nama']) . ' tersimpan '
                . '(total skor ' . $hitung['total'] . '/' . $hitung['maks'] . ', capaian '
                . r1($hitung['persen']) . '%, predikat ' . $hitung['predikat'] . '). Hasilnya otomatis '
                . 'tampil pada akun guru yang bersangkutan'];
}

/** Hapus satu penilaian PKG beserta skor butirnya (skor ikut terhapus lewat CASCADE). */
function pkg_hapus(int $id, int $sekolahId): bool
{
    $p = one('SELECT id FROM pkg_penilaian WHERE id = ? AND sekolah_id = ?', [$id, $sekolahId]);
    if (!$p) {
        return false;
    }
    q('DELETE FROM pkg_penilaian WHERE id = ?', [$id]);
    return true;
}

/** Penilaian satu guru pada satu periode (null bila belum dinilai). */
function pkg_periode_guru(int $sekolahId, int $guruId, int $taId, int $semId): ?array
{
    if ($sekolahId <= 0 || $guruId <= 0 || $taId <= 0 || $semId <= 0) {
        return null;
    }
    return one('SELECT * FROM pkg_penilaian WHERE sekolah_id = ? AND guru_id = ? AND ta_id = ?
                AND semester_id = ?', [$sekolahId, $guruId, $taId, $semId]);
}

/**
 * Peta penilaian periode tertentu untuk SELURUH guru sebuah sekolah: [guru_id => baris].
 *
 * @return array<int,array<string,mixed>>
 */
function pkg_peta_periode(int $sekolahId, int $taId, int $semId): array
{
    if ($sekolahId <= 0 || $taId <= 0 || $semId <= 0) {
        return [];
    }
    $peta = [];
    foreach (all('SELECT * FROM pkg_penilaian WHERE sekolah_id = ? AND ta_id = ? AND semester_id = ?',
        [$sekolahId, $taId, $semId]) as $r) {
        $peta[(int) $r['guru_id']] = $r;
    }
    return $peta;
}

/** Ringkasan penilaian dari sederet baris: jumlah, rata-rata capaian, sebaran predikat. */
function pkg_ringkas(array $baris): array
{
    $r = ['jumlah' => count($baris), 'rata' => 0.0,
          'Sangat Baik' => 0, 'Baik' => 0, 'Cukup' => 0, 'Kurang' => 0, '-' => 0];
    $total = 0.0;
    foreach ($baris as $b) {
        $p = (string) ($b['predikat'] ?? '-');
        if (isset($r[$p])) {
            $r[$p]++;
        }
        $total += (float) ($b['persen'] ?? 0);
    }
    $r['rata'] = $r['jumlah'] > 0 ? round($total / $r['jumlah'], 2) : 0.0;
    return $r;
}

/**
 * Jumlah guru AKTIF sebuah sekolah/madrasah (dipakai halaman pengawas yang membuka banyak binaan
 * sekaligus — `guru_list()` hanya membaca sekolah dalam konteks).
 */
function pkg_jumlah_guru(int $sekolahId): int
{
    if ($sekolahId <= 0) {
        return 0;
    }
    return (int) val('SELECT COUNT(*) FROM guru WHERE sekolah_id = ? AND aktif = 1', [$sekolahId], 0);
}

/** Hasil PKG milik seorang guru (seluruh periode, terbaru lebih dulu). */
function pkg_hasil_guru(int $sekolahId, int $guruId): array
{
    if ($sekolahId <= 0 || $guruId <= 0) {
        return [];
    }
    return pkg_list(['sekolah' => $sekolahId, 'guru' => $guruId]);
}

/**
 * Lencana menu "PKG" (kepala sekolah/madrasah): jumlah guru aktif yang BELUM dinilai pada periode
 * aktif (tahun ajaran & semester aktif). 0 = semua guru sudah dinilai / belum ada guru.
 */
function pkg_jumlah_belum(): int
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return 0;
    }
    $ta = ta_aktif();
    $sem = semester_aktif();
    if (!$ta || !$sem) {
        return count(guru_list(true));
    }
    $sudah = (int) val('SELECT COUNT(*) FROM pkg_penilaian
                        WHERE sekolah_id = ? AND ta_id = ? AND semester_id = ?',
        [$sid, (int) $ta['id'], (int) $sem['id']], 0);
    return max(0, count(guru_list(true)) - $sudah);
}

/** Lencana menu "Hasil PKG" (akun guru): jumlah hasil penilaian atas dirinya. */
function pkg_jumlah_saya(): int
{
    $sid = sekolah_id();
    $gid = guru_id_saya();
    if ($sid <= 0 || $gid <= 0) {
        return 0;
    }
    return (int) val('SELECT COUNT(*) FROM pkg_penilaian WHERE sekolah_id = ? AND guru_id = ?',
        [$sid, $gid], 0);
}

// ============================================================================
// TAMPILAN BERSAMA
// ============================================================================

/** URL lembar cetak sebuah penilaian. */
function pkg_cetak_url(int $id): string
{
    return 'cetak_pkg.php?id=' . $id;
}

/** Kartu statistik ringkas (dipakai halaman kepala & pengawas). */
function pkg_kartu_ringkas(array $r, int $jumlahGuru = 0): void
{
    $belum = max(0, $jumlahGuru - (int) $r['jumlah']);
    ?>
<div class="grid g4" style="gap:16px">
  <div class="card card-tight" style="margin:0"><div class="stat">
    <div class="stat-ic"><?= ikon('users', 18) ?></div>
    <div><div class="stat-num"><?= (int) $jumlahGuru ?></div><div class="stat-lbl">Guru dinilai periode ini</div></div>
  </div></div>
  <div class="card card-tight" style="margin:0"><div class="stat">
    <div class="stat-ic g"><?= ikon('check', 18) ?></div>
    <div><div class="stat-num"><?= (int) $r['jumlah'] ?></div><div class="stat-lbl">Sudah dinilai</div></div>
  </div></div>
  <div class="card card-tight" style="margin:0"><div class="stat">
    <div class="stat-ic o"><?= ikon('clock', 18) ?></div>
    <div><div class="stat-num"><?= $belum ?></div><div class="stat-lbl">Belum dinilai</div></div>
  </div></div>
  <div class="card card-tight" style="margin:0"><div class="stat">
    <div class="stat-ic s"><?= ikon('medal', 18) ?></div>
    <div><div class="stat-num"><?= e(r1((float) $r['rata'])) ?>%</div>
      <div class="stat-lbl">Rata-rata capaian</div></div>
  </div></div>
</div>
    <?php
}

/** Tabel instrumen READ-ONLY (dipakai halaman hasil guru, pengawas, & detail kepala). */
function pkg_tabel_baca(array $nilaiPeta): void
{
    ?>
<div class="table-wrap">
  <table class="tbl pkg-tbl" id="tblInstrumenPkg">
    <thead>
      <tr>
        <th class="ctr" style="width:44px">No</th>
        <th>Indikator Penilaian</th>
        <th class="ctr" style="width:70px">Skor</th>
        <th style="width:30%">Catatan / Bukti Fisik</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach (pkg_instrumen() as $d): ?>
      <tr class="pkg-grup">
        <td class="ctr"><b><?= e((string) $d['kode']) ?></b></td>
        <td colspan="3"><b><?= e((string) $d['nama']) ?></b>
          <span class="tbl-sub">maksimal <?= (int) $d['maks'] ?></span></td>
      </tr>
      <?php foreach ($d['butir'] as $b):
          $n = $nilaiPeta[(int) $b['no']] ?? null;
          $skor = $n['skor'] ?? null;
          $catatan = trim((string) ($n['catatan'] ?? '')); ?>
        <tr>
          <td class="ctr"><?= (int) $b['no'] ?></td>
          <td><b><?= e((string) $b['judul']) ?></b>
            <div class="tbl-sub"><?= e((string) $b['uraian']) ?></div></td>
          <td class="ctr"><span class="pill <?= $skor === null ? 'pill-uji' : 'pill-ok' ?>">
            <?= e(pkg_skala_teks($skor)) ?></span></td>
          <td class="sm"><?= $catatan !== '' ? nl2br(e(tetap_kata($catatan))) : '-' ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
    <?php
}

/** Blok rekapitulasi (skor tiap dimensi, total, persentase, predikat). */
function pkg_rekap_blok(array $p): void
{
    $baris = [
        ['Dimensi A: Perencanaan & Pelaksanaan Pembelajaran', 'a', 16],
        ['Dimensi B: Manajemen Kelas & Budaya Positif', 'b', 12],
        ['Dimensi C: Asesmen, Umpan Balik, & Pelaporan', 'c', 12],
        ['Dimensi D: Pengembangan Profesi & Perilaku Kerja', 'd', 12],
    ];
    ?>
<div class="table-wrap">
  <table class="tbl pkg-rekap" id="tblRekapPkg">
    <tbody>
    <?php foreach ($baris as $b): ?>
      <tr>
        <td><?= e($b[0]) ?></td>
        <td class="ctr" style="width:90px">(Maks. <?= (int) $b[2] ?>)</td>
        <td class="ctr" style="width:120px"><b><?= (int) ($p['skor_' . $b[1]] ?? 0) ?></b></td>
      </tr>
    <?php endforeach; ?>
      <tr class="pkg-total">
        <td><b>TOTAL SKOR KESELURUHAN</b></td>
        <td class="ctr">(Maksimal <?= e((string) ($p['skor_maks'] ?? pkg_skor_maks())) ?>)</td>
        <td class="ctr"><b><?= (int) ($p['total_skor'] ?? 0) ?></b></td>
      </tr>
      <tr>
        <td><b>PERSENTASE CAPAIAN</b></td>
        <td class="ctr">((Total Skor / <?= e((string) ($p['skor_maks'] ?? pkg_skor_maks())) ?>) x 100%)</td>
        <td class="ctr"><b><?= e(r1((float) ($p['persen'] ?? 0))) ?> %</b></td>
      </tr>
      <tr>
        <td><b>PREDIKAT KINERJA AKHIR</b></td>
        <td colspan="2" class="ctr">
          <span class="pill <?= pkg_predikat_pil((string) ($p['predikat'] ?? '-')) ?>">
            <?= e((string) ($p['predikat'] ?? '-')) ?></span>
          <?= pkg_predikat_label((string) ($p['predikat'] ?? '-')) !== '-'
              ? ' <span class="sm">(' . e(pkg_predikat_label((string) ($p['predikat'] ?? '-'))) . ')</span>' : '' ?>
        </td>
      </tr>
    </tbody>
  </table>
</div>
    <?php
}

/**
 * Kartu DETAIL satu penilaian (read-only) — dipakai halaman guru ("Hasil PKG"), halaman pengawas, dan
 * halaman kepala. `$tampilSekolah` = true untuk tampilan lintas binaan (pengawas).
 */
function pkg_detail_kartu(array $p, bool $tampilSekolah = false, bool $tautanCetak = true): void
{
    $peta = pkg_nilai_peta((int) $p['id']);
    $namaGuru = (string) ($p['guru_nama'] ?? '-');
    $nipGuru = trim((string) ($p['guru_nip'] ?? ''));
    $nuptk = trim((string) ($p['guru_nuptk'] ?? ''));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('clipboard', 16) ?> Hasil PKG — <?= e(tetap_kata($namaGuru)) ?></h2>
      <p>Penilaian Kinerja Guru periode <b>Semester <?= e((string) ($p['semester_nama'] ?? '-')) ?></b>
        tahun ajaran <b><?= e((string) ($p['ta_nama'] ?? '-')) ?></b><?php if ($tampilSekolah): ?>,
        <?= e(tetap_kata((string) ($p['sekolah_nama'] ?? '-'))) ?><?php endif; ?>.</p>
    </div>
    <div class="flex">
      <span class="pill <?= pkg_predikat_pil((string) ($p['predikat'] ?? '-')) ?>">
        <?= e((string) ($p['predikat'] ?? '-')) ?> &middot; <?= e(r1((float) ($p['persen'] ?? 0))) ?>%</span>
      <?php if ($tautanCetak): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(pkg_cetak_url((int) $p['id'])) ?>"
           target="_blank" rel="noopener"><?= ikon('print', 14) ?> Cetak</a>
      <?php endif; ?>
    </div>
  </div>

  <table class="pkg-identitas">
    <tr><td>Nama Guru yang Dinilai</td><td class="pisah">:</td>
      <td><b><?= e(tetap_kata($namaGuru)) ?></b></td></tr>
    <tr><td>NIP / NUPTK</td><td class="pisah">:</td>
      <td><?= e($nipGuru !== '' ? $nipGuru : ($nuptk !== '' ? $nuptk : '-')) ?></td></tr>
    <tr><td>Unit Kerja / <?= e(istilah_sekolah('Sekolah')) ?></td><td class="pisah">:</td>
      <td><?= e(tetap_kata((string) ($p['sekolah_nama'] ?? '-'))) ?></td></tr>
    <tr><td>Pangkat / Golongan / Ruang</td><td class="pisah">:</td>
      <td><?= e(trim((string) ($p['pangkat'] ?? '')) !== '' ? tetap_kata((string) $p['pangkat']) : '-') ?></td></tr>
    <tr><td>Mata Pelajaran / Kelas</td><td class="pisah">:</td>
      <td><?= e(trim((string) ($p['mapel_kelas'] ?? '')) !== '' ? tetap_kata((string) $p['mapel_kelas']) : '-') ?></td></tr>
    <tr><td>Nama Penilai / Jabatan</td><td class="pisah">:</td>
      <td><?= e(tetap_kata((string) ($p['penilai_nama'] ?? '-'))) ?>
        (<?= e(tetap_kata((string) ($p['penilai_jabatan'] ?? '-'))) ?>)</td></tr>
    <tr><td>Periode Penilaian</td><td class="pisah">:</td>
      <td>Semester <?= e((string) ($p['semester_nama'] ?? '-')) ?> / Tahun Ajaran
        <?= e((string) ($p['ta_nama'] ?? '-')) ?></td></tr>
    <tr><td>Tanggal Penilaian</td><td class="pisah">:</td>
      <td><?= e(tanggal_id((string) $p['tanggal'])) ?><?= trim((string) ($p['tempat'] ?? '')) !== ''
          ? ', ' . e(tetap_kata((string) $p['tempat'])) : '' ?></td></tr>
  </table>

  <div class="divider"></div>
  <?php pkg_tabel_baca($peta); ?>

  <div class="divider"></div>
  <div class="section-title mb8"><?= ikon('chart', 15) ?> Rekapitulasi &amp; Hasil Akhir</div>
  <?php pkg_rekap_blok($p); ?>

  <div class="divider"></div>
  <div class="section-title mb8"><?= ikon('doc', 15) ?> Catatan &amp; Rencana Tindak Lanjut (RTL)</div>
  <table class="pkg-rtl">
    <tr><th>Kelebihan / Praktik Baik Guru</th>
      <td><?= trim((string) ($p['kelebihan'] ?? '')) !== ''
          ? nl2br(e(tetap_kata((string) $p['kelebihan']))) : '-' ?></td></tr>
    <tr><th>Area yang Perlu Ditingkatkan</th>
      <td><?= trim((string) ($p['perbaikan'] ?? '')) !== ''
          ? nl2br(e(tetap_kata((string) $p['perbaikan']))) : '-' ?></td></tr>
    <tr><th>Rencana Penguatan / Pembinaan</th>
      <td><?= trim((string) ($p['rtl'] ?? '')) !== ''
          ? nl2br(e(tetap_kata((string) $p['rtl']))) : '-' ?></td></tr>
  </table>
</div>
    <?php
}

// ============================================================================
// HALAMAN: PKG (KEPALA SEKOLAH/MADRASAH)
// ============================================================================

/**
 * Halaman menu **PKG** — akun KEPALA SEKOLAH/MADRASAH (dan Super Admin dalam konteks sekolah).
 *
 * Alur: daftar guru → formulir instrumen (`?guru=ID`) → hasil tersimpan (`?lihat=ID`).
 */
function pkg_halaman(): void
{
    require_login();
    if (!in_array(peran(), ['kepala', 'super'], true)) {
        tolak403('Menu PKG hanya untuk Kepala Sekolah/Madrasah (dan Super Admin dalam konteks '
            . 'sekolah/madrasah). Hasil penilaian dapat dibaca pada menu "Hasil PKG" akun guru.');
    }
    require_sekolah();
    $sid = sekolah_id();
    $konteks = konteks_ajaran();
    $ta = $konteks['ta'];
    $sem = $konteks['sem'];
    $qsPeriode = ajaran_qs($ta, $sem);

    // ---------------------------------------------------------------- POST
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        $guruKembali = (int) ($_POST['guru'] ?? 0);
        if ($aksi === 'simpan') {
            $hasil = pkg_simpan([
                'id'             => (int) ($_POST['id'] ?? 0),
                'sekolah_id'     => $sid,
                'guru_id'        => $guruKembali,
                'ta_id'          => (int) ($_POST['ta'] ?? 0),
                'semester_id'    => (int) ($_POST['sem'] ?? 0),
                'tanggal'        => (string) ($_POST['tanggal'] ?? ''),
                'tempat'         => (string) ($_POST['tempat'] ?? ''),
                'pangkat'        => (string) ($_POST['pangkat'] ?? ''),
                'mapel_kelas'    => (string) ($_POST['mapel_kelas'] ?? ''),
                'penilai_nip'    => (string) ($_POST['penilai_nip'] ?? ''),
                'nilai'          => $_POST['nilai'] ?? [],
                'kelebihan'      => (string) ($_POST['kelebihan'] ?? ''),
                'perbaikan'      => (string) ($_POST['perbaikan'] ?? ''),
                'rtl'            => (string) ($_POST['rtl'] ?? ''),
            ]);
            flash($hasil['pesan'], $hasil['ok'] ? 'ok' : 'err');
            $taKirim = ta_by_id((int) ($_POST['ta'] ?? 0));
            $semKirim = semester_by_id((int) ($_POST['sem'] ?? 0));
            $q = ajaran_qs($taKirim, $semKirim);
            if ($hasil['ok']) {
                redirect('pkg.php' . $q . '&guru=' . $guruKembali . '&lihat=' . (int) $hasil['id']);
            }
            redirect('pkg.php' . $q . '&guru=' . $guruKembali);
        }
        // "Hapus Penilaian" (halaman detail) dan "Batal Nilai" (kolom Tindakan pada Riwayat Penilaian
        // Tersimpan) melakukan hal yang sama: baris `pkg_penilaian` + 13 butir `pkg_nilai` dihapus
        // sehingga guru kembali berstatus BELUM DINILAI pada periode itu dan hasilnya hilang dari menu
        // Hasil PKG akun guru. Yang membedakan hanya kalimat pesannya.
        if ($aksi === 'hapus' || $aksi === 'batal') {
            $idHapus = (int) ($_POST['id'] ?? 0);
            $namaHapus = '';
            if ($aksi === 'batal') {
                $target = pkg_by_id($idHapus, [$sid]);
                $namaHapus = trim((string) ($target['guru_nama'] ?? ''));
            }
            if (pkg_hapus($idHapus, $sid)) {
                flash($aksi === 'batal'
                    ? 'Penilaian PKG' . ($namaHapus !== '' ? ' untuk ' . $namaHapus : '') . ' dibatalkan. '
                        . 'Guru kembali berstatus belum dinilai pada periode tersebut dan hasilnya tidak '
                        . 'lagi tampil pada menu Hasil PKG. Data guru tidak terpengaruh.'
                    : 'Penilaian PKG dihapus. Data guru tidak terpengaruh.', 'warn');
            } else {
                flash('Penilaian itu tidak ditemukan pada sekolah/madrasah ini.', 'err');
            }
            redirect('pkg.php' . $qsPeriode . ($guruKembali > 0 ? '&guru=' . $guruKembali : ''));
        }
        flash('Aksi tidak dikenal.', 'err');
        redirect('pkg.php');
    }

    if (!$ta || !$sem) {
        page_top('PKG — Penilaian Kinerja Guru');
        ?>
<div class="card"><div class="empty">
  <?= ikon('calendar', 34) ?>
  <h3>Tahun ajaran / semester belum tersedia</h3>
  <p>PKG disimpan per tahun ajaran &amp; semester. Atur periodenya terlebih dahulu.</p>
  <a class="btn btn-primary" href="tahun_ajaran.php"><?= ikon('calendar', 16) ?> Atur Tahun Ajaran &amp; Semester</a>
</div></div>
        <?php
        page_bottom();
        return;
    }

    $namaSekolah = nama_sekolah_tampil();
    $guruSemua = guru_list(true);
    // Peta penilaian periode terpilih: [guru_id => baris] — dasar status "sudah/belum dinilai".
    $peta = pkg_peta_periode($sid, (int) $ta['id'], (int) $sem['id']);
    $ringkas = pkg_ringkas(array_values($peta));
    $idGuru = (int) ($_GET['guru'] ?? 0);
    $idLihat = (int) ($_GET['lihat'] ?? 0);
    $guru = $idGuru > 0 ? guru_by_id($idGuru) : null;
    if ($idGuru > 0 && !$guru) {
        flash('Guru itu tidak ditemukan pada sekolah/madrasah ini.', 'err');
        redirect('pkg.php' . $qsPeriode);
    }
    $csrf = csrf_field();

    // ---------------------------------------------------------------- DETAIL HASIL TERSIMPAN
    if ($idLihat > 0) {
        $p = pkg_by_id($idLihat, [$sid]);
        if (!$p) {
            flash('Penilaian itu tidak ditemukan pada sekolah/madrasah ini.', 'err');
            redirect('pkg.php' . $qsPeriode);
        }
        page_top('PKG — Hasil Penilaian', 'Kepala Sekolah/Madrasah &middot; ' . tetap_kata($namaSekolah));
        pkg_detail_kartu($p);
        ?>
<div class="card">
  <div class="flex" style="flex-wrap:wrap">
    <a class="btn btn-ghost" href="pkg.php<?= e($qsPeriode) ?>"><?= ikon('prev', 15) ?> Daftar Guru</a>
    <a class="btn btn-primary" href="pkg.php<?= e($qsPeriode) ?>&amp;guru=<?= (int) $p['guru_id'] ?>">
      <?= ikon('edit', 15) ?> Ubah Penilaian</a>
    <form method="post" class="inline-form" onsubmit="return confirm('Hapus penilaian PKG ini? Data guru tidak terpengaruh.')">
      <?= $csrf ?>
      <input type="hidden" name="aksi" value="hapus">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <input type="hidden" name="guru" value="<?= (int) $p['guru_id'] ?>">
      <button class="btn btn-ghost" type="submit"><?= ikon('trash', 15) ?> Hapus Penilaian</button>
    </form>
  </div>
</div>
        <?php
        page_bottom();
        return;
    }

    // ---------------------------------------------------------------- FORMULIR INSTRUMEN
    if ($guru) {
        $lama = $peta[(int) $guru['id']] ?? null;
        $petaNilai = $lama ? pkg_nilai_peta((int) $lama['id']) : [];
        $mapel = array_map(static fn(array $m): string => (string) $m['nama'], mapel_diampu((int) $guru['id']));
        $kelas = array_map(static fn(array $k): string => (string) $k['nama'], kelas_diampu((int) $guru['id']));
        $mapelKelasBawaan = trim(implode(', ', $mapel) . ($kelas ? ' — Kelas ' . implode(', ', $kelas) : ''));
        $mapelKelas = $lama ? (string) $lama['mapel_kelas'] : $mapelKelasBawaan;
        $nip = trim((string) ($guru['nip'] ?? '')) !== '' ? (string) $guru['nip'] : (string) ($guru['nuptk'] ?? '');
        page_top('PKG — Instrumen Penilaian',
            'Kepala Sekolah/Madrasah &middot; ' . tetap_kata($namaSekolah));
        ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('clipboard', 16) ?> Instrumen PKG — <?= e(tetap_kata((string) $guru['nama'])) ?></h2>
      <p>Menilai <b><?= (int) pkg_butir_total() ?> butir indikator</b> (4 dimensi, skala 1-4). Seluruh
        butir wajib diberi skor sebelum dapat disimpan. Hasilnya otomatis tampil pada akun guru yang
        dinilai (menu <b>Hasil PKG</b>) dan dapat dilihat pengawas sekolah/madrasah binaan.</p>
    </div>
    <div class="flex">
      <span class="chip">Semester <?= e(sem_nama($sem)) ?></span>
      <span class="chip">TA <?= e(ta_nama($ta)) ?></span>
      <a class="btn btn-ghost btn-sm" href="pkg.php<?= e($qsPeriode) ?>"><?= ikon('prev', 15) ?> Kembali</a>
    </div>
  </div>

  <?php if ($lama): ?>
    <div class="alert alert-warn"><?= ikon('info', 16) ?><span>Penilaian periode ini <b>sudah ada</b>
      (tersimpan <?= e(tanggal_id((string) $lama['tanggal'])) ?>). Menyimpan formulir ini akan
      <b>memperbarui</b> penilaian tersebut — bukan menambah penilaian baru.</span></div>
  <?php endif; ?>

  <form method="post" id="formPkg">
    <?= $csrf ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="guru" value="<?= (int) $guru['id'] ?>">
    <input type="hidden" name="ta" value="<?= (int) $ta['id'] ?>">
    <input type="hidden" name="sem" value="<?= (int) $sem['id'] ?>">
    <?php if ($lama): ?><input type="hidden" name="id" value="<?= (int) $lama['id'] ?>"><?php endif; ?>

    <div class="section-title mb8"><?= ikon('user', 15) ?> Identitas Guru &amp; Penilai</div>
    <div class="grid g2" style="gap:16px">
      <div>
        <label class="f">Nama Guru yang Dinilai</label>
        <input type="text" value="<?= e((string) $guru['nama']) ?>" disabled>
      </div>
      <div>
        <label class="f">NIP / NUPTK</label>
        <input type="text" value="<?= e($nip !== '' ? $nip : '-') ?>" disabled>
      </div>
      <div>
        <label class="f">Unit Kerja / <?= e(istilah_sekolah('Sekolah')) ?></label>
        <input type="text" value="<?= e($namaSekolah) ?>" disabled>
      </div>
      <div>
        <label class="f" for="pangkat">Pangkat / Golongan / Ruang</label>
        <input type="text" id="pangkat" name="pangkat" maxlength="<?= (int) PKG_ISIAN_MAKS ?>"
               value="<?= e((string) ($lama['pangkat'] ?? '')) ?>" placeholder="mis. Penata Muda / III-a">
      </div>
      <div>
        <label class="f" for="mapel_kelas">Mata Pelajaran / Kelas</label>
        <input type="text" id="mapel_kelas" name="mapel_kelas" maxlength="<?= (int) PKG_ISIAN_MAKS ?>"
               value="<?= e($mapelKelas) ?>" placeholder="mis. Matematika — Kelas VII, VIII">
        <div class="hint">Terisi otomatis dari data guru; boleh disesuaikan.</div>
      </div>
      <div>
        <label class="f">Nama Penilai / Jabatan</label>
        <input type="text" disabled
               value="<?= e((string) (current_user()['nama'] ?? '')) ?> (<?= e(role_label()) ?>)">
      </div>
      <div>
        <label class="f" for="penilai_nip">NIP Penilai</label>
        <input type="text" id="penilai_nip" name="penilai_nip" maxlength="<?= (int) PKG_ISIAN_MAKS ?>"
               value="<?= e((string) ($lama['penilai_nip'] ?? '')) ?>" placeholder="boleh dikosongkan">
      </div>
      <div>
        <label class="f">Periode Penilaian</label>
        <input type="text" disabled value="Semester <?= e(sem_nama($sem)) ?> / TA <?= e(ta_nama($ta)) ?>">
        <div class="hint">Periode lain dipilih lewat penyaring pada halaman daftar guru.</div>
      </div>
      <div>
        <label class="f" for="tanggal">Tanggal Penilaian *</label>
        <input type="date" id="tanggal" name="tanggal" required
               value="<?= e((string) ($lama['tanggal'] ?? date('Y-m-d'))) ?>">
      </div>
      <div>
        <label class="f" for="tempat">Ditetapkan di</label>
        <input type="text" id="tempat" name="tempat" maxlength="<?= (int) PKG_ISIAN_MAKS ?>"
               value="<?= e((string) ($lama['tempat'] ?? kecamatan_sekolah())) ?>"
               placeholder="mis. kecamatan lembaga">
      </div>
    </div>

    <div class="divider"></div>
    <div class="section-title mb8"><?= ikon('clipboard', 15) ?> Rubrik Indikator Kinerja Utama</div>
    <p class="hint" style="margin:0 0 10px">Skala penilaian:
      <?php foreach (pkg_skala_list() as $s => $teks): ?><b><?= (int) $s ?></b> =
        <?= e($teks) ?><?= $s < 4 ? ' &middot; ' : '' ?><?php endforeach; ?>.</p>
    <div class="table-wrap">
      <table class="tbl pkg-tbl pkg-form" id="tblFormPkg">
        <thead>
          <tr>
            <th class="ctr" style="width:44px">No</th>
            <th>Indikator Penilaian</th>
            <th class="ctr" style="width:190px">Skala (1-4)</th>
            <th style="width:28%">Catatan / Bukti Fisik</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach (pkg_instrumen() as $d): ?>
          <tr class="pkg-grup">
            <td class="ctr"><b><?= e((string) $d['kode']) ?></b></td>
            <td colspan="3"><b><?= e((string) $d['nama']) ?></b>
              <span class="tbl-sub">maksimal <?= (int) $d['maks'] ?></span></td>
          </tr>
          <?php foreach ($d['butir'] as $b):
              $no = (int) $b['no'];
              $n = $petaNilai[$no] ?? null;
              $skorLama = $n && $n['skor'] !== null ? (int) $n['skor'] : 0; ?>
            <tr>
              <td class="ctr"><?= $no ?></td>
              <td><b><?= e((string) $b['judul']) ?></b>
                <div class="tbl-sub"><?= e((string) $b['uraian']) ?></div></td>
              <td class="ctr">
                <div class="pkg-skala" data-butir="<?= $no ?>">
                  <?php foreach (pkg_skala_list() as $s => $teks): ?>
                    <label title="<?= e($teks) ?>">
                      <input type="radio" name="nilai[<?= $no ?>][skor]" value="<?= (int) $s ?>"
                             data-kode="<?= e((string) $d['kode']) ?>" <?= $skorLama === (int) $s ? 'checked' : '' ?>>
                      <span><?= (int) $s ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </td>
              <td>
                <input type="text" name="nilai[<?= $no ?>][catatan]" maxlength="<?= (int) PKG_CATATAN_MAKS ?>"
                       value="<?= e((string) ($n['catatan'] ?? '')) ?>"
                       placeholder="<?= e((string) $b['bukti']) ?>">
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pkg-rekap-live" id="pkgRekapLive">
      <div class="section-title mb8"><?= ikon('chart', 15) ?> Rekapitulasi (terisi otomatis)</div>
      <div class="table-wrap">
        <table class="tbl pkg-rekap">
          <tbody>
            <tr><td>Dimensi A: Perencanaan &amp; Pelaksanaan Pembelajaran (Maks. 16)</td>
              <td class="ctr" style="width:120px"><b data-rekap="A">0</b></td></tr>
            <tr><td>Dimensi B: Manajemen Kelas &amp; Budaya Positif (Maks. 12)</td>
              <td class="ctr"><b data-rekap="B">0</b></td></tr>
            <tr><td>Dimensi C: Asesmen, Umpan Balik, &amp; Pelaporan (Maks. 12)</td>
              <td class="ctr"><b data-rekap="C">0</b></td></tr>
            <tr><td>Dimensi D: Pengembangan Profesi &amp; Perilaku Kerja (Maks. 12)</td>
              <td class="ctr"><b data-rekap="D">0</b></td></tr>
            <tr class="pkg-total"><td><b>TOTAL SKOR (Maksimal <?= (int) pkg_skor_maks() ?>)</b></td>
              <td class="ctr"><b data-rekap="total">0</b></td></tr>
            <tr><td><b>PERSENTASE CAPAIAN</b></td>
              <td class="ctr"><b data-rekap="persen">0</b> %</td></tr>
            <tr><td><b>PREDIKAT</b></td>
              <td class="ctr"><b data-rekap="predikat">-</b></td></tr>
          </tbody>
        </table>
      </div>
      <p class="hint" style="margin-top:8px">Kategori predikat: <?= e(pkg_predikat_keterangan()) ?>.</p>
    </div>

    <div class="divider"></div>
    <div class="section-title mb8"><?= ikon('doc', 15) ?> Catatan &amp; Rencana Tindak Lanjut (RTL)</div>
    <div class="grid g1" style="gap:16px">
      <div>
        <label class="f" for="kelebihan">Kelebihan / Praktik Baik Guru</label>
        <textarea id="kelebihan" name="kelebihan" rows="3" maxlength="<?= (int) PKG_RTL_MAKS ?>"
                  placeholder="Praktik baik yang perlu dipertahankan…"><?= e((string) ($lama['kelebihan'] ?? '')) ?></textarea>
      </div>
      <div>
        <label class="f" for="perbaikan">Area yang Perlu Ditingkatkan</label>
        <textarea id="perbaikan" name="perbaikan" rows="3" maxlength="<?= (int) PKG_RTL_MAKS ?>"
                  placeholder="Bagian yang masih perlu diperbaiki…"><?= e((string) ($lama['perbaikan'] ?? '')) ?></textarea>
      </div>
      <div>
        <label class="f" for="rtl">Rencana Penguatan / Pembinaan</label>
        <textarea id="rtl" name="rtl" rows="3" maxlength="<?= (int) PKG_RTL_MAKS ?>"
                  placeholder="Rencana tindak lanjut yang disepakati…"><?= e((string) ($lama['rtl'] ?? '')) ?></textarea>
      </div>
    </div>

    <div class="flex mt16">
      <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Penilaian</button>
      <a class="btn btn-ghost" href="pkg.php<?= e($qsPeriode) ?>">Batal</a>
    </div>
  </form>
</div>

<script>
/* Rekapitulasi langsung pada formulir (tanpa server): menjumlahkan skala yang dipilih per dimensi.
   Angka final tetap dihitung ulang di server saat disimpan — skrip ini hanya bantuan tampilan. */
(function () {
  var form = document.getElementById('formPkg');
  if (!form) { return; }
  var MAKS = <?= (int) pkg_skor_maks() ?>;
  function predikat(p) {
    if (p >= 86) { return 'Sangat Baik'; }
    if (p >= 70) { return 'Baik'; }
    if (p >= 55) { return 'Cukup'; }
    return 'Kurang';
  }
  function hitung() {
    var dim = {A: 0, B: 0, C: 0, D: 0};
    var input = form.querySelectorAll('input[type=radio]:checked');
    for (var i = 0; i < input.length; i++) {
      var k = input[i].getAttribute('data-kode');
      if (dim[k] === undefined) { continue; }
      dim[k] += parseInt(input[i].value, 10) || 0;
    }
    var total = dim.A + dim.B + dim.C + dim.D;
    var persen = MAKS > 0 ? Math.round((total / MAKS) * 10000) / 100 : 0;
    ['A', 'B', 'C', 'D', 'total'].forEach(function (k) {
      var el = form.querySelector('[data-rekap="' + k + '"]');
      if (el) { el.textContent = (k === 'total' ? total : dim[k]); }
    });
    var ep = form.querySelector('[data-rekap="persen"]');
    if (ep) { ep.textContent = persen.toFixed(2); }
    var ed = form.querySelector('[data-rekap="predikat"]');
    if (ed) { ed.textContent = input.length ? predikat(persen) : '-'; }
  }
  form.addEventListener('change', hitung);
  hitung();
})();
</script>
        <?php
        page_bottom();
        return;
    }

    // ---------------------------------------------------------------- DAFTAR GURU
    $semua = pkg_list(['sekolah' => $sid]);
    $ringkasSemua = pkg_ringkas($semua);
    page_top('PKG — Penilaian Kinerja Guru',
        'Kepala Sekolah/Madrasah &middot; ' . tetap_kata($namaSekolah));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('clipboard', 16) ?> Penilaian Kinerja Guru (PKG)</h2>
      <p>Menilai setiap guru pada <b><?= tetap_kata($namaSekolah) ?></b> dengan instrumen
        <b><?= (int) pkg_butir_total() ?> butir</b> (4 dimensi, skala 1-4, skor maksimal
        <?= (int) pkg_skor_maks() ?>). Hasilnya otomatis masuk ke menu <b>Hasil PKG</b> pada akun guru
        dan dapat dilihat pengawas sekolah/madrasah binaan.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($guruSemua) ?> guru</span>
      <span class="chip"><?= (int) $ringkasSemua['jumlah'] ?> penilaian tersimpan</span>
    </div>
  </div>

  <?php pkg_kartu_ringkas($ringkas, count($guruSemua)); ?>

  <div class="divider"></div>
  <form class="filterbar" method="get" id="filterPkg">
    <div style="min-width:170px">
      <label class="f" for="pkgTa">Tahun Ajaran</label>
      <select id="pkgTa" name="ta" data-auto>
        <?php foreach (ta_list() as $t): $tid = (int) $t['id']; ?>
          <option value="<?= $tid ?>" <?= $tid === (int) $ta['id'] ? 'selected' : '' ?>>
            <?= e((string) $t['nama']) ?><?= (int) $t['aktif'] === 1 ? ' (berjalan)' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:150px">
      <label class="f" for="pkgSem">Semester</label>
      <select id="pkgSem" name="sem" data-auto>
        <?php foreach (semester_list((int) $ta['id']) as $s): $sidS = (int) $s['id']; ?>
          <option value="<?= $sidS ?>" <?= $sidS === (int) $sem['id'] ? 'selected' : '' ?>>
            <?= e((string) $s['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex" style="gap:8px">
      <button class="btn btn-primary" type="submit"><?= ikon('search', 15) ?> Tampilkan</button>
    </div>
  </form>

  <div class="divider"></div>
  <div class="section-title mb8"><?= ikon('users', 15) ?> Daftar Guru (<?= count($guruSemua) ?>)</div>
  <?php if (!$guruSemua): ?>
    <div class="empty"><?= ikon('users', 34) ?>
      <h3>Belum ada data guru</h3>
      <p>Data guru diisi lewat menu <b>Data Guru</b> (akun kepala sekolah/madrasah atau Admin
        Operator). Setelah ada guru, mereka dapat dinilai di halaman ini.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblGuruPkg">
        <thead>
          <tr>
            <th class="ctr" style="width:44px">No</th>
            <th>Nama Guru</th>
            <th class="ctr" style="width:160px">Status Periode Ini</th>
            <th class="ctr" style="width:160px">Hasil Terakhir</th>
            <th class="ctr" style="width:274px">Tindakan</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($guruSemua as $g): $n++; $row = $peta[(int) $g['id']] ?? null; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e(tetap_kata((string) $g['nama'])) ?></b>
              <div class="tbl-sub">NIP: <?= e(trim((string) $g['nip']) !== '' ? (string) $g['nip'] : '-') ?></div></td>
            <td class="ctr">
              <?php if ($row): ?>
                <span class="pill pill-ok">sudah dinilai</span>
                <div class="tbl-sub"><?= e(tanggal_id((string) $row['tanggal'])) ?></div>
              <?php else: ?>
                <span class="pill pill-warn">belum dinilai</span>
              <?php endif; ?>
            </td>
            <td class="ctr">
              <?php if ($row): ?>
                <span class="pill <?= pkg_predikat_pil((string) $row['predikat']) ?>">
                  <?= e((string) $row['predikat']) ?></span>
                <div class="tbl-sub"><?= (int) $row['total_skor'] ?>/<?= (int) $row['skor_maks'] ?> &middot;
                  <?= e(r1((float) $row['persen'])) ?>%</div>
              <?php else: ?>
                <span class="sm mut">-</span>
              <?php endif; ?>
            </td>
            <td class="ctr">
              <div class="row-actions">
                <a class="btn btn-primary btn-sm" href="pkg.php<?= e($qsPeriode) ?>&amp;guru=<?= (int) $g['id'] ?>">
                  <?= ikon('edit', 13) ?> <?= $row ? 'Ubah' : 'Nilai' ?></a>
                <?php if ($row): ?>
                  <a class="btn btn-ghost btn-sm" href="pkg.php<?= e($qsPeriode) ?>&amp;lihat=<?= (int) $row['id'] ?>">
                    <?= ikon('eye', 13) ?> Lihat</a>
                  <a class="btn btn-ghost btn-sm" href="<?= e(pkg_cetak_url((int) $row['id'])) ?>"
                     target="_blank" rel="noopener"><?= ikon('print', 13) ?> Cetak</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($semua): ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('chart', 16) ?> Riwayat Penilaian Tersimpan</h2>
      <p>Seluruh penilaian PKG yang sudah tersimpan di sekolah/madrasah ini (semua periode).</p>
    </div>
    <span class="chip"><?= (int) $ringkasSemua['jumlah'] ?> penilaian</span>
  </div>
  <div class="table-wrap">
    <table class="tbl" id="tblRiwayatPkg">
      <thead>
        <tr>
          <th class="ctr" style="width:44px">No</th>
          <th>Guru</th>
          <th class="ctr" style="width:150px">Periode</th>
          <th class="ctr" style="width:110px">Tanggal</th>
          <th class="ctr" style="width:90px">Skor</th>
          <th class="ctr" style="width:120px">Capaian</th>
          <th class="ctr" style="width:270px">Tindakan</th>
        </tr>
      </thead>
      <tbody>
      <?php $n = 0; foreach ($semua as $s): $n++; ?>
        <tr>
          <td class="ctr"><?= $n ?></td>
          <td><?= e(tetap_kata((string) ($s['guru_nama'] ?? '-'))) ?></td>
          <td class="ctr">Semester <?= e((string) $s['semester_nama']) ?>
            <div class="tbl-sub">TA <?= e((string) $s['ta_nama']) ?></div></td>
          <td class="ctr"><?= e(tanggal_id((string) $s['tanggal'])) ?></td>
          <td class="ctr"><?= (int) $s['total_skor'] ?>/<?= (int) $s['skor_maks'] ?></td>
          <td class="ctr"><span class="pill <?= pkg_predikat_pil((string) $s['predikat']) ?>">
            <?= e((string) $s['predikat']) ?></span>
            <div class="tbl-sub"><?= e(r1((float) $s['persen'])) ?>%</div></td>
          <td class="ctr">
            <div class="row-actions">
              <a class="btn btn-ghost btn-sm"
                 href="pkg.php?ta=<?= (int) $s['ta_id'] ?>&amp;sem=<?= (int) $s['semester_id'] ?>&amp;lihat=<?= (int) $s['id'] ?>">
                <?= ikon('eye', 13) ?> Lihat</a>
              <a class="btn btn-ghost btn-sm" href="<?= e(pkg_cetak_url((int) $s['id'])) ?>"
                 target="_blank" rel="noopener"><?= ikon('print', 13) ?> Cetak</a>
              <?php // BATAL NILAI (permintaan pemilik): penilaian yang sudah disimpan oleh kepala
                    // sekolah/madrasah dapat dibatalkan dari baris riwayat ini. Sama dengan "Hapus
                    // Penilaian" pada halaman detail — guru kembali berstatus belum dinilai. ?>
              <form method="post" class="row-form"
                    onsubmit="return confirm(<?= e(json_encode(
                        'Batalkan penilaian PKG untuk ' . (string) ($s['guru_nama'] ?? 'guru ini')
                        . ' pada Semester ' . (string) $s['semester_nama'] . ' TA ' . (string) $s['ta_nama']
                        . '? Penilaian itu akan dihapus sehingga guru kembali berstatus belum dinilai '
                        . 'dan hasilnya tidak lagi tampil pada menu Hasil PKG.', JSON_UNESCAPED_UNICODE)) ?>)">
                <?= $csrf ?>
                <input type="hidden" name="aksi" value="batal">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 13) ?> Batal</button>
              </form>
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
    page_bottom();
}

// ============================================================================
// HALAMAN: HASIL PKG (AKUN GURU)
// ============================================================================

/**
 * Halaman menu **Hasil PKG** — akun GURU: hasil penilaian kinerjanya sendiri.
 *
 * Hanya hasil MILIKNYA (guru_id_saya()) yang ditampilkan; id pada URL tidak pernah dipercaya
 * (detail diambil lewat `pkg_by_id()` yang dibatasi sekolah + guru sendiri).
 */
function pkg_hasil_halaman(): void
{
    require_login();
    if (!in_array(peran(), ['guru'], true)) {
        tolak403('Menu Hasil PKG khusus akun guru. Kepala sekolah/madrasah menilai lewat menu PKG, '
            . 'sedangkan pengawas melihat hasil binaannya lewat menu Hasil PKG pada akunnya.');
    }
    $u = require_sekolah();
    $sid = sekolah_id();
    $gid = guru_id_saya();
    $namaSekolah = nama_sekolah_tampil();
    $guru = $gid > 0 ? one('SELECT * FROM guru WHERE id = ? AND sekolah_id = ?', [$gid, $sid]) : null;

    $idLihat = (int) ($_GET['lihat'] ?? 0);
    if ($idLihat > 0) {
        $p = pkg_by_id($idLihat, [$sid]);
        if (!$p || (int) $p['guru_id'] !== $gid) {
            flash('Hasil PKG itu bukan milik Anda.', 'err');
            redirect('pkg_hasil.php');
        }
        page_top('Hasil PKG', 'Guru &middot; ' . tetap_kata($namaSekolah));
        pkg_detail_kartu($p);
        ?>
<div class="card">
  <div class="flex">
    <a class="btn btn-ghost" href="pkg_hasil.php"><?= ikon('prev', 15) ?> Kembali ke Daftar</a>
    <a class="btn btn-primary" href="<?= e(pkg_cetak_url((int) $p['id'])) ?>" target="_blank" rel="noopener">
      <?= ikon('print', 15) ?> Cetak / Simpan PDF</a>
  </div>
</div>
        <?php
        page_bottom();
        return;
    }

    $hasil = $gid > 0 ? pkg_hasil_guru($sid, $gid) : [];
    $ringkas = pkg_ringkas($hasil);
    page_top('Hasil PKG', 'Guru &middot; ' . tetap_kata($namaSekolah));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('clipboard', 16) ?> Hasil Penilaian Kinerja Guru (PKG)</h2>
      <p>Hasil penilaian kinerja Anda yang diisi <b>Kepala Sekolah/Madrasah</b>
        (<?= e(tetap_kata($namaSekolah)) ?>). Instrumen <?= (int) pkg_butir_total() ?> butir,
        skala 1-4, skor maksimal <?= (int) pkg_skor_maks() ?>.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $ringkas['jumlah'] ?> hasil penilaian</span>
      <?php if ($ringkas['jumlah'] > 0): ?>
        <span class="chip">rata-rata <?= e(r1((float) $ringkas['rata'])) ?>%</span>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$guru): ?>
    <div class="alert alert-warn"><?= ikon('info', 16) ?><span>Akun Anda belum ditautkan ke data guru
      pada sekolah/madrasah ini, sehingga hasil PKG belum dapat ditampilkan. Hubungi Admin Operator
      atau kepala sekolah/madrasah untuk menautkan akun Anda.</span></div>
  <?php endif; ?>

  <?php if ($guru && !$hasil): ?>
    <div class="empty"><?= ikon('clipboard', 34) ?>
      <h3>Belum ada hasil PKG</h3>
      <p>Hasil penilaian kinerja akan tampil di sini setelah <b>Kepala Sekolah/Madrasah</b> menyimpan
        penilaian PKG untuk Anda. Penilaian dilakukan tiap semester, jadi hasilnya dapat berbeda antar
        periode.</p>
    </div>
  <?php elseif ($hasil): ?>
    <div class="table-wrap">
      <table class="tbl" id="tblHasilPkg">
        <thead>
          <tr>
            <th class="ctr" style="width:44px">No</th>
            <th class="ctr" style="width:170px">Periode</th>
            <th class="ctr" style="width:110px">Tanggal</th>
            <th class="ctr" style="width:100px">Skor</th>
            <th class="ctr" style="width:130px">Capaian</th>
            <th>Penilai</th>
            <th class="ctr" style="width:150px">Tindakan</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($hasil as $h): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td class="ctr">Semester <?= e((string) $h['semester_nama']) ?>
              <div class="tbl-sub">TA <?= e((string) $h['ta_nama']) ?></div></td>
            <td class="ctr"><?= e(tanggal_id((string) $h['tanggal'])) ?></td>
            <td class="ctr"><?= (int) $h['total_skor'] ?>/<?= (int) $h['skor_maks'] ?></td>
            <td class="ctr"><span class="pill <?= pkg_predikat_pil((string) $h['predikat']) ?>">
              <?= e((string) $h['predikat']) ?></span>
              <div class="tbl-sub"><?= e(r1((float) $h['persen'])) ?>%</div></td>
            <td><?= e(tetap_kata((string) ($h['penilai_nama'] ?? '-'))) ?>
              <div class="tbl-sub"><?= e(tetap_kata((string) ($h['penilai_jabatan'] ?? ''))) ?></div></td>
            <td class="ctr">
              <div class="row-actions">
                <a class="btn btn-primary btn-sm" href="pkg_hasil.php?lihat=<?= (int) $h['id'] ?>">
                  <?= ikon('eye', 13) ?> Lihat</a>
                <a class="btn btn-ghost btn-sm" href="<?= e(pkg_cetak_url((int) $h['id'])) ?>"
                   target="_blank" rel="noopener"><?= ikon('print', 13) ?></a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="divider"></div>
    <div class="section-title mb8"><?= ikon('chart', 15) ?> Sebaran Predikat</div>
    <div class="flex" style="gap:10px;flex-wrap:wrap">
      <span class="chip">Sangat Baik: <?= (int) $ringkas['Sangat Baik'] ?></span>
      <span class="chip">Baik: <?= (int) $ringkas['Baik'] ?></span>
      <span class="chip">Cukup: <?= (int) $ringkas['Cukup'] ?></span>
      <span class="chip">Kurang: <?= (int) $ringkas['Kurang'] ?></span>
    </div>
    <p class="hint" style="margin-top:10px">Kategori predikat: <?= e(pkg_predikat_keterangan()) ?>.</p>
  <?php endif; ?>
</div>
    <?php
    page_bottom();
}

// ============================================================================
// HALAMAN: HASIL PKG (AKUN PENGAWAS — LINTAS BINAAN)
// ============================================================================

/**
 * Halaman menu **Hasil PKG** — akun PENGAWAS: hasil PKG setiap guru sekolah/madrasah binaannya
 * (hanya melihat & mencetak; penilaian dilakukan kepala sekolah/madrasah masing-masing).
 */
function pkg_pengawas_halaman(): void
{
    require_login();
    if (!is_pengawas()) {
        tolak403('Menu Hasil PKG pada akun ini khusus Pengawas Sekolah/Madrasah. Hasil PKG Anda ada '
            . 'pada menu "Hasil PKG" akun guru, dan penilaian dilakukan pada menu "PKG" akun kepala '
            . 'sekolah/madrasah.');
    }
    $uid = (int) (current_user()['id'] ?? 0);
    $binaan = sekolah_binaan($uid, true);

    // ---------------------------------------------------------------- lepas / pindah konteks binaan
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
            flash('Sekolah/madrasah itu sedang tidak aktif sehingga hasil PKG-nya tidak dapat dibuka.', 'warn');
            redirect('pkg_pengawas.php');
        }
        set_sekolah_context($idSekolah);
        flash('Anda kini melihat hasil PKG ' . tetap_kata(nama_sekolah_tampil()) . '.');
        redirect('pkg_pengawas.php');
    }

    // ---------------------------------------------------------------- PEMILIH BINAAN
    if (!current_sekolah()) {
        $GLOBALS['__vs_istilah_nonaktif'] = true;
        $ringkasBinaan = [];
        foreach ($binaan as $b) {
            $hasil = pkg_list(['sekolah' => (int) $b['id']]);
            $ringkasBinaan[(int) $b['id']] = [
                'guru'  => pkg_jumlah_guru((int) $b['id']),
                'hasil' => $hasil,
                'r'     => pkg_ringkas($hasil),
            ];
        }
        $semuaHasil = $binaan
            ? pkg_list(['sekolah_ids' => array_map(static fn(array $b): int => (int) $b['id'], $binaan),
                        'limit' => 50])
            : [];
        page_top('Hasil PKG', 'Pengawas Sekolah/Madrasah');
        ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('clipboard', 16) ?> Hasil PKG — Pilih Sekolah/Madrasah</h2>
      <p>Hasil Penilaian Kinerja Guru (PKG) yang diisi kepala sekolah/madrasah binaan Anda. Akun
        pengawas hanya dapat <b>melihat dan mencetak</b>; penilaian dilakukan kepala
        sekolah/madrasah masing-masing.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($binaan) ?> binaan</span>
      <span class="chip"><?= count($semuaHasil) ?> hasil penilaian</span>
    </div>
  </div>

  <?php if (!$ringkasBinaan): ?>
    <div class="empty" style="padding:26px">
      <?= ikon('building', 34) ?>
      <h3>Belum ada sekolah/madrasah binaan</h3>
      <p>Sekolah/madrasah binaan ditetapkan Super Admin pada menu <b>Akun Pengawas</b>. Setelah ada
        binaan, hasil PKG guru-gurunya tampil di sini.</p>
    </div>
  <?php else: ?>
    <div class="grid g2" style="gap:18px">
      <?php foreach ($binaan as $b): $bid = (int) $b['id']; $r = $ringkasBinaan[$bid]; ?>
        <div class="card card-tight" style="margin:0">
          <div class="flex" style="justify-content:space-between;align-items:flex-start;gap:12px">
            <b><?= e(tetap_kata((string) nama_sekolah_tampil($b))) ?></b>
            <span class="pill <?= (int) $r['r']['jumlah'] > 0 ? 'pill-ok' : 'pill-warn' ?>">
              <?= (int) $r['r']['jumlah'] ?> hasil</span>
          </div>
          <div class="flex mt8" style="gap:8px;flex-wrap:wrap">
            <span class="chip"><?= (int) $r['guru'] ?> guru</span>
            <?php if ((int) $r['r']['jumlah'] > 0): ?>
              <span class="chip">rata-rata <?= e(r1((float) $r['r']['rata'])) ?>%</span>
            <?php endif; ?>
          </div>
          <div class="mt16">
            <a class="btn btn-ok" href="pkg_pengawas.php?sekolah=<?= $bid ?>">
              <?= ikon('eye', 15) ?> Buka Hasil PKG
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="divider"></div>
    <div class="section-title mb8"><?= ikon('chart', 15) ?> Rekapan Hasil PKG (semua binaan)</div>
    <?php if (!$semuaHasil): ?>
      <p class="hint">Belum ada penilaian PKG yang tersimpan pada binaan Anda.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl" id="tblRekapPkgAll">
          <thead>
            <tr>
              <th class="ctr" style="width:44px">No</th>
              <th>Sekolah/Madrasah</th>
              <th>Guru</th>              <th class="ctr" style="width:150px">Periode</th>
              <th class="ctr" style="width:110px">Tanggal</th>
              <th class="ctr" style="width:80px">Skor</th>
              <th class="ctr" style="width:130px">Capaian</th>
              <th class="ctr" style="width:80px">Cetak</th>
            </tr>
          </thead>
          <tbody>
          <?php $n = 0; foreach ($semuaHasil as $h): $n++; ?>
            <tr>
              <td class="ctr"><?= $n ?></td>
              <td><?= e(tetap_kata((string) ($h['sekolah_nama'] ?? '-'))) ?></td>
              <td><?= e(tetap_kata((string) ($h['guru_nama'] ?? '-'))) ?></td>
              <td class="ctr">Semester <?= e((string) $h['semester_nama']) ?>
                <div class="tbl-sub">TA <?= e((string) $h['ta_nama']) ?></div></td>
              <td class="ctr"><?= e(tanggal_id((string) $h['tanggal'])) ?></td>
              <td class="ctr"><?= (int) $h['total_skor'] ?>/<?= (int) $h['skor_maks'] ?></td>
              <td class="ctr"><span class="pill <?= pkg_predikat_pil((string) $h['predikat']) ?>">
                <?= e((string) $h['predikat']) ?></span>
                <div class="tbl-sub"><?= e(r1((float) $h['persen'])) ?>%</div></td>
              <td class="ctr">
                <a class="btn btn-ghost btn-sm" href="<?= e(pkg_cetak_url((int) $h['id'])) ?>"
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
    $guru = guru_list(true);
    $semua = pkg_list(['sekolah' => $sid]);
    $ringkas = pkg_ringkas($semua);
    $idLihat = (int) ($_GET['lihat'] ?? 0);

    page_top('Hasil PKG', 'Pengawas Sekolah/Madrasah &middot; ' . tetap_kata($namaSekolah));
    if ($idLihat > 0) {
        $p = pkg_by_id($idLihat, [$sid]);
        if (!$p) {
            flash('Penilaian itu tidak ditemukan pada sekolah/madrasah ini.', 'err');
            redirect('pkg_pengawas.php');
        }
        pkg_detail_kartu($p);
        ?>
<div class="card">
  <div class="flex">
    <a class="btn btn-ghost" href="pkg_pengawas.php"><?= ikon('prev', 15) ?> Kembali</a>
    <a class="btn btn-primary" href="<?= e(pkg_cetak_url((int) $p['id'])) ?>" target="_blank" rel="noopener">
      <?= ikon('print', 15) ?> Cetak / Simpan PDF</a>
  </div>
</div>
        <?php
        page_bottom();
        return;
    }
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('clipboard', 16) ?> Hasil PKG Guru</h2>
      <p>Hasil penilaian kinerja guru <b><?= tetap_kata($namaSekolah) ?></b> (binaan Anda) yang diisi
        kepala sekolah/madrasah. Akun pengawas bersifat <b>hanya lihat &amp; cetak</b>.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($guru) ?> guru</span>
      <span class="chip"><?= (int) $ringkas['jumlah'] ?> hasil</span>
      <a class="btn btn-ghost btn-sm" href="pkg_pengawas.php?pilih=1"><?= ikon('switch', 15) ?> Ganti Binaan</a>
    </div>
  </div>

  <div class="alert alert-warn"><?= ikon('eye', 16) ?><span>Akun pengawas <b>hanya melihat dan
    mencetak</b>. Penilaian PKG dilakukan kepala sekolah/madrasah pada menu <b>PKG</b> akunnya.</span></div>

  <?php pkg_kartu_ringkas($ringkas, count($guru)); ?>

  <div class="divider"></div>
  <div class="section-title mb8"><?= ikon('users', 15) ?> Guru &amp; Hasil PKG-nya</div>
  <?php if (!$guru): ?>
    <div class="empty"><?= ikon('users', 34) ?>
      <h3>Belum ada data guru</h3>
      <p>Data guru binaan ini belum diisi sekolah/madrasahnya.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblGuruPkgPengawas">
        <thead>
          <tr>
            <th class="ctr" style="width:44px">No</th>
            <th>Nama Guru</th>
            <th class="ctr" style="width:100px">Hasil PKG</th>
            <th class="ctr" style="width:190px">Hasil Terakhir</th>
            <th class="ctr" style="width:140px">Tindakan</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $perGuru = [];
        foreach ($semua as $h) {
            $perGuru[(int) $h['guru_id']][] = $h;
        }
        $n = 0;
        foreach ($guru as $g): $n++;
            $daftar = $perGuru[(int) $g['id']] ?? [];
            $akhir = $daftar[0] ?? null; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e(tetap_kata((string) $g['nama'])) ?></b>
              <div class="tbl-sub">NIP: <?= e(trim((string) $g['nip']) !== '' ? (string) $g['nip'] : '-') ?></div></td>
            <td class="ctr"><?= count($daftar) ?> kali</td>
            <td class="ctr">
              <?php if (!$akhir): ?>
                <span class="pill pill-warn">belum dinilai</span>
              <?php else: ?>
                <span class="pill <?= pkg_predikat_pil((string) $akhir['predikat']) ?>">
                  <?= e((string) $akhir['predikat']) ?> &middot; <?= e(r1((float) $akhir['persen'])) ?>%</span>
                <div class="tbl-sub">Semester <?= e((string) $akhir['semester_nama']) ?>
                  TA <?= e((string) $akhir['ta_nama']) ?></div>
              <?php endif; ?>
            </td>
            <td class="ctr">
              <?php if ($akhir): ?>
                <div class="row-actions">
                  <a class="btn btn-primary btn-sm" href="pkg_pengawas.php?lihat=<?= (int) $akhir['id'] ?>">
                    <?= ikon('eye', 13) ?> Lihat</a>
                  <a class="btn btn-ghost btn-sm" href="<?= e(pkg_cetak_url((int) $akhir['id'])) ?>"
                     target="_blank" rel="noopener"><?= ikon('print', 13) ?></a>
                </div>
              <?php else: ?>
                <span class="sm mut">-</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
    <?php
    page_bottom();
}
