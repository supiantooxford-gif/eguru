<?php
/**
 * HALAMAN DEPAN (LANDING PAGE) — modul bersama e-GURU.
 *
 * Permintaan pemilik: "Buat halaman depan aplikasi menjadi tampilan web landing page yang
 * menampilkan beranda, Slider (Judul, gambar, keterangan), fitur aplikasi, keunggulan,
 * Total Pengguna (Pengawas, Kepala Sekolah, Madrasah, Guru), testimoni, kontak. Panel login tetap
 * dipertahankan. Untuk mengisi halaman landing page di isi lewat akun super admin."
 *
 * Isi halaman depan disimpan di DUA tempat:
 *  - teks tunggal (label beranda, judul tiap bagian, label kartu Total Pengguna, kontak) → tabel
 *    `app_pengaturan` dengan awalan kunci `landing_` (tingkat APLIKASI, dipakai bersama semua
 *    sekolah/madrasah);
 *  - daftar berulang (slider, fitur, keunggulan, testimoni) dan pesan kontak → tabel `landing_*`.
 *
 * ANGKA "Total Pengguna" TIDAK diisi manual (permintaan pemilik: dihitung otomatis dari data
 * aplikasi supaya selalu terbaru) — lihat `landing_pengguna_list()`. Yang boleh diubah Super Admin
 * hanya label tiap kartu + boleh disembunyikan; angkanya selalu dihitung ulang dari tabel `users`.
 *
 * PENTING: seluruh tulisan pada halaman depan memakai istilah "sekolah/madrasah" (halaman ini
 * publik dan melayani sekolah umum maupun madrasah sekaligus).
 *
 * Modul ini boleh dimuat tanpa init.php (dipakai juga oleh `inc/db.php` saat mengisi data awal),
 * jadi JANGAN memanggil fungsi basis data apa pun pada saat modul dimuat.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

const LANDING_JUDUL_MAKS = 160;
const LANDING_TEKS_MAKS  = 700;
const LANDING_ISI_MAKS   = 900;
const LANDING_NAMA_MAKS  = 90;
const LANDING_PESAN_MAKS = 1200;
const LANDING_MAKS_SESI  = 5;   // maksimal pengiriman pesan kontak per sesi

/** Jenis daftar yang boleh dikelola di halaman depan. */
function landing_jenis_list(): array
{
    return ['slider', 'fitur', 'unggulan', 'testimoni'];
}

/** Hanya menerima jenis yang dikenal (menjaga nama tabel tetap aman). */
function landing_jenis_sah(string $jenis): string
{
    $j = strtolower(trim($jenis));
    return in_array($j, landing_jenis_list(), true) ? $j : '';
}

/** Nama tabel daftar halaman depan (WHITELIST — jangan menerima nama tabel dari pengguna). */
function landing_tabel(string $jenis): string
{
    return match (landing_jenis_sah($jenis)) {
        'slider'    => 'landing_slider',
        'fitur'     => 'landing_fitur',
        'unggulan'  => 'landing_unggulan',
        'testimoni' => 'landing_testimoni',
        default     => '',
    };
}

/** Label tampilan tiap jenis daftar. */
function landing_jenis_label(string $jenis): string
{
    return match (landing_jenis_sah($jenis)) {
        'slider'    => 'Slider Beranda',
        'fitur'     => 'Fitur Aplikasi',
        'unggulan'  => 'Keunggulan',
        'testimoni' => 'Testimoni',
        default     => '',
    };
}

/** Jenis daftar yang itemnya menyimpan FOTO (dipakai halaman pengisian & unggahan). */
function landing_jenis_berfoto(string $jenis): bool
{
    return in_array(landing_jenis_sah($jenis), ['slider', 'testimoni'], true);
}

// ---------------------------------------------------------------------------------------------
// ISI BAWAAN
// ---------------------------------------------------------------------------------------------

/**
 * Isi bawaan halaman depan (dipakai satu kali saat pemasangan & sebagai nilai awal isian).
 * Semuanya dapat diubah/dikosongkan Super Admin lewat menu "Halaman Depan (Landing)".
 */
function landing_bawaan(): array
{
    return [
        // --- Beranda (bagian yang diganti SLIDER: judul, foto, keterangan)
        'label' => 'Aplikasi Administrasi Guru Digital',

        // --- Judul tiap bagian
        'fitur_judul'    => 'Fitur Aplikasi',
        'fitur_sub'      => 'Semua pekerjaan administrasi guru dikelola dalam satu aplikasi: data guru '
            . 'dan siswa, daftar hadir, penilaian, jurnal mengajar, sampai cetak dokumennya.',
        'unggulan_judul' => 'Keunggulan',
        'unggulan_sub'   => 'Alasan sekolah/madrasah memilih aplikasi ini untuk administrasi gurunya.',
        'pengguna_judul' => 'Total Pengguna',
        'pengguna_sub'   => 'Jumlah pengguna aplikasi ini, dihitung langsung dari data yang tersimpan '
            . 'sehingga selalu terbaru.',
        'testimoni_judul'       => 'Testimoni',
        'testimoni_judul_besar' => 'Apa Kata Pengguna Kami?',
        'testimoni_sub'         => 'Pengalaman kepala sekolah/madrasah, operator, dan guru yang memakai aplikasi ini.',
        'tentang_judul'  => 'Tentang Aplikasi',
        'tentang_sub'    => 'Sekilas aplikasi ini beserta siapa yang mengembangkannya.',
        'kontak_judul'   => 'Kontak',
        'kontak_sub'     => 'Ingin mencoba, bertanya, atau mengaktifkan aplikasi? Hubungi kami.',

        // --- Kontak
        'kontak_alamat'  => '',
        'kontak_telepon' => AKTIVASI_WA_PERMOHONAN,
        'kontak_email'   => '',
        'kontak_jam'     => 'Senin - Sabtu, 07.00 - 21.00 WIB',
        'kontak_maps'    => '',
        'kontak_ajakan'  => 'Silakan kirim pesan lewat formulir di samping, atau hubungi langsung '
            . 'lewat WhatsApp/telepon.',
    ];
}

/**
 * Isi bawaan daftar slider/fitur/keunggulan/testimoni.
 * Dipakai SEKALI saat pemasangan; sesudah itu sepenuhnya dikelola Super Admin.
 */
function landing_daftar_bawaan(): array
{
    return [
        // Slider beranda: gambar/foto + judul + keterangan (permintaan pemilik).
        // Isi bawaan ini SENGAJA tanpa foto — Super Admin melengkapi fotonya sendiri; selama belum
        // ada foto, slider memakai latar gradasi bergambar ikon (bukan kotak rusak).
        'slider' => [
            ['Administrasi Guru Digital dalam Satu Aplikasi',
                'Data guru dan siswa, daftar hadir, penilaian Kurikulum Merdeka, jurnal harian mengajar, '
                . 'sampai cetak dokumen — semuanya tersimpan rapi dalam satu tempat.'],
            ['Nilai, Rekap, dan Laporan Terhitung Otomatis',
                'Nilai akhir serta predikat dihitung aplikasi mengikuti pengaturan sekolah/madrasah, '
                . 'dan rekapan kehadiran maupun pelanggaran tersedia setiap saat.'],
            ['Satu Pemasangan untuk Banyak Sekolah/Madrasah',
                'Data setiap sekolah/madrasah terpisah dengan akun berjenjang: kepala sekolah/madrasah, '
                . 'admin operator, guru, dan pengawas sekolah/madrasah.'],
        ],
        'fitur' => [
            ['users', 'Data Guru & Data Siswa', 'Data guru (beserta akunnya) dan data siswa per kelas, '
                . 'lengkap dengan wali serta kontaknya — bisa diimpor dari Excel/CSV.'],
            ['clipboard', 'Daftar Hadir Siswa', 'Catatan kehadiran tiap pertemuan (H/S/I/A) beserta rekap '
                . 'persentase kehadiran setiap siswa.'],
            ['chart', 'Penilaian Siswa', 'Nilai Formatif, Sumatif Lingkup Materi, dan ASAS dengan nilai akhir '
                . 'serta predikat A/B/C/D otomatis; guru boleh mengatur sendiri pembobotannya.'],
            ['book', 'Jurnal Harian Guru', 'Jurnal mengajar: uraian kegiatan, kelas/tempat, materi, jam ke-, '
                . 'kehadiran S/I/A/B/T, dan keterangan.'],
            ['alert', 'Pelanggaran Siswa', 'Pencatatan pelanggaran siswa beserta tindakan, status penanganan, '
                . 'dan rekapan per mata pelajaran.'],
            ['star', 'Tugas Tambahan Guru', 'Menu tugas tambahan: Kurikulum, Kesiswaan, Sarana & Prasarana, '
                . 'OSIM/OSIS, Pramuka, PMR, Wali Kelas, Perpustakaan, dan Kepala Lab — lengkap dengan laporannya.'],
            ['doc', 'Kirim Dokumen & Ujian Online', 'Kirim dokumen kepada guru pada sekolah/madrasah yang '
                . 'bersangkutan, dan bagikan tautan aplikasi pendukung seperti ujian online.'],
            ['card', 'Kartu Pelajar & Ekspor Data', 'Cetak kartu pelajar berpas foto, cetak lembar data guru '
                . 'dan siswa, serta ekspor datanya agar bisa dipakai aplikasi lain.'],
            ['school', 'Multi-Sekolah/Madrasah & Multi-Akun', 'Satu pemasangan melayani banyak '
                . 'sekolah/madrasah sekaligus, dengan akun berjenjang sesuai tugas masing-masing.'],
        ],
        'unggulan' => [
            ['clock', 'Mudah Digunakan', 'Tampilan sederhana dan alur yang jelas, sehingga guru maupun '
                . 'operator langsung paham tanpa pelatihan panjang.'],
            ['shield', 'Data Setiap Sekolah/Madrasah Terpisah', 'Akun hanya melihat data sekolah/madrasah '
                . 'yang bersangkutan, dan kata sandi tersimpan teracak.'],
            ['chart', 'Nilai & Rekap Otomatis', 'Nilai akhir, predikat, rekap kehadiran, dan rekapan '
                . 'pelanggaran dihitung aplikasi — tanpa hitung manual.'],
            ['print', 'Dokumen Siap Cetak', 'Lembar nilai, daftar hadir, jurnal, kartu pelajar, dan laporan '
                . 'lain dicetak lengkap dengan kop serta tanda tangan kepala sekolah/madrasah.'],
            ['globe', 'Bisa Dibuka di Mana Saja', 'Cukup memakai peramban: laptop, komputer, maupun '
                . 'telepon pintar — tanpa pemasangan aplikasi tambahan.'],
            ['support', 'Dukungan Penuh', 'Admin aplikasi siap membantu saat dibutuhkan, dan '
                . 'sekolah/madrasah baru dapat mendaftar sendiri.'],
        ],
        'testimoni' => [
            ['Kepala Sekolah/Madrasah (contoh)', 'Pengguna Aplikasi', 'Semua administrasi guru akhirnya '
                . 'rapi di satu tempat, dan laporannya bisa saya lihat kapan saja tanpa harus meminta berkas.'],
            ['Admin Operator (contoh)', 'Pengguna Aplikasi', 'Data guru dan siswa cukup diimpor dari Excel, '
                . 'lalu akun guru dibuatkan otomatis — pekerjaan saya jauh lebih ringan.'],
            ['Guru (contoh)', 'Pengguna Aplikasi', 'Nilai dan jurnal mengajar bisa diisi langsung dari '
                . 'telepon, dan hasilnya tinggal dicetak saat diperlukan.'],
        ],
    ];
}

// ---------------------------------------------------------------------------------------------
// TEKS TUNGGAL (app_pengaturan)
// ---------------------------------------------------------------------------------------------

/** Isi satu kunci halaman depan (kunci tanpa awalan "landing_"). */
function landing_teks(string $kunci, ?string $def = null): string
{
    $bawaan = landing_bawaan();
    $nilaiBawaan = $bawaan[$kunci] ?? ($def ?? '');
    return app_setting('landing_' . $kunci, (string) $nilaiBawaan);
}

/** Simpan banyak kunci sekaligus: ['judul' => '...', 'lead' => '...']. */
function landing_simpan_teks(array $nilai): void
{
    foreach ($nilai as $k => $v) {
        app_setting_set('landing_' . (string) $k, (string) $v);
    }
}

// ---------------------------------------------------------------------------------------------
// TOTAL PENGGUNA (angka dihitung otomatis dari data aplikasi — permintaan pemilik)
// ---------------------------------------------------------------------------------------------

/**
 * Peran yang dihitung pada bagian "Total Pengguna" beserta label bawaannya.
 *
 * Permintaan pemilik (terbaru): *"pada halaman depan bagian total pengguna dirubah menjadi: Pengawas
 * Sekolah/Madrasah, Kepala sekolah/Madrasah, Guru, Siswa. Admin Operator dihapus saja"* → kartu
 * **Admin Operator DIHAPUS** dan digantikan kartu **Siswa** (akun siswa yang masuk dengan NISN).
 * Kartunya dibuat per PERAN supaya angkanya benar-benar dihitung dari data, bukan ditulis manual.
 */
function landing_pengguna_peran(): array
{
    return [
        'pengawas' => 'Pengawas Sekolah/Madrasah',
        'kepala'   => 'Kepala Sekolah/Madrasah',
        'guru'     => 'Guru',
        'siswa'    => 'Siswa',
    ];
}

/**
 * Jumlah pengguna AKTIF satu peran di seluruh sekolah/madrasah yang aktif & sudah disetujui.
 * Akun yang tidak terikat sekolah (Super Admin, pengawas) tetap dihitung meskipun `sekolah_id` NULL,
 * tetapi akun sekolah yang dinonaktifkan/belum disetujui TIDAK ikut dihitung supaya angkanya wajar.
 */
function landing_pengguna_angka(string $peran): int
{
    if (!array_key_exists($peran, landing_pengguna_peran())) {
        return 0;
    }
    // Kartu SISWA dihitung dari DATA SISWA (tabel `siswa`), bukan dari akun pengguna: permintaan
    // pemilik — *"total siswa di ambil dari database siswa yang dimasukkan oleh seluruh
    // sekolah/madrasah"*.
    if ($peran === 'siswa') {
        return landing_siswa_angka();
    }
    try {
        return (int) val(
            "SELECT COUNT(*) FROM users u
               LEFT JOIN sekolah s ON s.id = u.sekolah_id
              WHERE u.role = ? AND u.aktif = 1
                AND (u.sekolah_id IS NULL OR (s.aktif = 1 AND s.verifikasi = 'disetujui'))",
            [$peran],
            0
        );
    } catch (Throwable $e) {
        return 0;   // basis data belum siap → halaman depan tidak boleh gagal
    }
}

/**
 * Jumlah SISWA dari SELURUH sekolah/madrasah (tabel `siswa`).
 *
 * Permintaan pemilik: *"Pada halaman depan aplikasi, total siswa diambil dari database siswa yang
 * dimasukkan oleh seluruh sekolah/madrasah dan ditampilkan di total pengguna (siswa)."*
 * Aturannya mengikuti kartu peran lain supaya angkanya masuk akal sebagai "pengguna aktif":
 * siswa berstatus AKTIF, belum lulus, dan sekolahnya aktif & sudah disetujui.
 */
function landing_siswa_angka(): int
{
    try {
        return (int) val(
            "SELECT COUNT(*) FROM siswa sw
               JOIN sekolah s ON s.id = sw.sekolah_id
              WHERE sw.aktif = 1
                AND LOWER(COALESCE(sw.status, 'aktif')) <> 'lulus'
                AND s.aktif = 1 AND s.verifikasi = 'disetujui'",
            [],
            0
        );
    } catch (Throwable $e) {
        return 0;
    }
}

/** Label satu kartu pengguna (dapat diubah Super Admin; kosong → label bawaan). */
function landing_pengguna_label(string $peran): string
{
    $bawaan = landing_pengguna_peran()[$peran] ?? '';
    $label = trim(app_setting('landing_pengguna_' . $peran . '_label', ''));
    return $label !== '' ? $label : $bawaan;
}

/** Daftar kartu Total Pengguna yang harus tampil (angka selalu dihitung ulang). */
function landing_pengguna_list(): array
{
    $out = [];
    foreach (landing_pengguna_peran() as $peran => $bawaan) {
        if (app_setting('landing_pengguna_' . $peran . '_tampil', '1') !== '1') {
            continue;
        }
        $out[] = [
            'peran' => $peran,
            'angka' => landing_pengguna_angka($peran),
            'label' => landing_pengguna_label($peran),
        ];
    }
    return $out;
}

/** Jumlah sekolah/madrasah AKTIF (untuk baris ringkasan di bawah kartu pengguna). */
function landing_sekolah_jumlah(): int
{
    try {
        return (int) val("SELECT COUNT(*) FROM sekolah WHERE aktif = 1 AND verifikasi = 'disetujui'", [], 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Jumlah AKUN PENGGUNA aktif (baris ringkasan di bawah kartu).
 *
 * Akun berperan `siswa` TIDAK ikut dihitung: kartu "Siswa" menampilkan DATA SISWA (tabel `siswa`),
 * bukan akun, sehingga mencampurnya akan membuat angka ringkasan menyesatkan.
 */
function landing_pengguna_total(): int
{
    try {
        return (int) val(
            "SELECT COUNT(*) FROM users u
               LEFT JOIN sekolah s ON s.id = u.sekolah_id
              WHERE u.aktif = 1 AND u.role <> 'siswa'
                AND (u.sekolah_id IS NULL OR (s.aktif = 1 AND s.verifikasi = 'disetujui'))",
            [],
            0
        );
    } catch (Throwable $e) {
        return 0;
    }
}

// ---------------------------------------------------------------------------------------------
// DAFTAR (slider / fitur / keunggulan / testimoni)
// ---------------------------------------------------------------------------------------------

/** Isi satu daftar (hanya yang aktif bila $aktifSaja). */
function landing_list(string $jenis, bool $aktifSaja = true): array
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '') {
        return [];
    }
    $sql = 'SELECT * FROM ' . $tabel;
    if ($aktifSaja) {
        $sql .= ' WHERE aktif = 1';
    }
    $sql .= ' ORDER BY urutan, id';
    try {
        return all($sql);
    } catch (Throwable $e) {
        return [];
    }
}

function landing_item_by_id(string $jenis, int $id): ?array
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '' || $id <= 0) {
        return null;
    }
    return one('SELECT * FROM ' . $tabel . ' WHERE id = ?', [$id]);
}

function landing_urutan_baru(string $jenis): int
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '') {
        return 10;
    }
    $maks = (int) val('SELECT COALESCE(MAX(urutan), 0) FROM ' . $tabel, [], 0);
    return $maks + 10;
}

function landing_item_tambah(string $jenis, array $d): void
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '') {
        return;
    }
    $kini = date('Y-m-d H:i:s');
    if ($jenis === 'testimoni') {
        q('INSERT INTO ' . $tabel . ' (nama, jabatan, isi, foto, urutan, aktif, dibuat) VALUES (?,?,?,?,?,?,?)',
            [$d['nama'] ?? '', $d['jabatan'] ?? '', $d['isi'] ?? '', $d['foto'] ?? null,
                landing_urutan_baru($jenis), 1, $kini]);
        return;
    }
    if ($jenis === 'slider') {
        q('INSERT INTO ' . $tabel . ' (judul, keterangan, foto, urutan, aktif, dibuat) VALUES (?,?,?,?,?,?)',
            [$d['judul'] ?? '', $d['keterangan'] ?? '', $d['foto'] ?? null,
                landing_urutan_baru($jenis), 1, $kini]);
        return;
    }
    q('INSERT INTO ' . $tabel . ' (ikon, judul, teks, urutan, aktif, dibuat) VALUES (?,?,?,?,?,?)',
        [$d['ikon'] ?? 'check', $d['judul'] ?? '', $d['teks'] ?? '', landing_urutan_baru($jenis), 1, $kini]);
}

function landing_item_simpan(string $jenis, int $id, array $d): void
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '' || $id <= 0) {
        return;
    }
    if ($jenis === 'testimoni') {
        q('UPDATE ' . $tabel . ' SET nama = ?, jabatan = ?, isi = ?, aktif = ? WHERE id = ?',
            [$d['nama'] ?? '', $d['jabatan'] ?? '', $d['isi'] ?? '', (int) ($d['aktif'] ?? 1), $id]);
        return;
    }
    if ($jenis === 'slider') {
        q('UPDATE ' . $tabel . ' SET judul = ?, keterangan = ?, aktif = ? WHERE id = ?',
            [$d['judul'] ?? '', $d['keterangan'] ?? '', (int) ($d['aktif'] ?? 1), $id]);
        return;
    }
    q('UPDATE ' . $tabel . ' SET ikon = ?, judul = ?, teks = ?, aktif = ? WHERE id = ?',
        [$d['ikon'] ?? 'check', $d['judul'] ?? '', $d['teks'] ?? '', (int) ($d['aktif'] ?? 1), $id]);
}

/** Simpan hanya kolom foto testimoni (dipakai setelah unggahan berhasil). */
function landing_testimoni_foto(int $id, string $url): void
{
    if ($id <= 0) {
        return;
    }
    q('UPDATE landing_testimoni SET foto = ? WHERE id = ?', [$url, $id]);
}

/** Simpan hanya kolom foto satu slide slider beranda (dipakai setelah unggahan berhasil). */
function landing_slider_foto(int $id, string $url): void
{
    if ($id <= 0) {
        return;
    }
    q('UPDATE landing_slider SET foto = ? WHERE id = ?', [$url, $id]);
}

/** Foto satu slide slider (string kosong bila belum diunggah). */
function landing_slider_gambar(array $slide): string
{
    return trim((string) ($slide['foto'] ?? ''));
}

function landing_item_hapus(string $jenis, int $id): void
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '' || $id <= 0) {
        return;
    }
    q('DELETE FROM ' . $tabel . ' WHERE id = ?', [$id]);
}

/** Tukar urutan dengan tetangga terdekat (naik = -1, turun = +1). */
function landing_item_geser(string $jenis, int $id, int $arah): void
{
    $tabel = landing_tabel($jenis);
    if ($tabel === '' || $id <= 0) {
        return;
    }
    $ini = one('SELECT id, urutan FROM ' . $tabel . ' WHERE id = ?', [$id]);
    if (!$ini) {
        return;
    }
    $op = $arah < 0 ? '<' : '>';
    $urut = $arah < 0 ? 'DESC' : 'ASC';
    $tetangga = one('SELECT id, urutan FROM ' . $tabel . ' WHERE (urutan ' . $op . ' ? OR (urutan = ? AND id ' . $op . ' ?))
                     ORDER BY urutan ' . $urut . ', id ' . $urut . ' LIMIT 1',
        [(int) $ini['urutan'], (int) $ini['urutan'], $id]);
    if (!$tetangga) {
        return;
    }
    q('UPDATE ' . $tabel . ' SET urutan = ? WHERE id = ?', [(int) $tetangga['urutan'], $id]);
    q('UPDATE ' . $tabel . ' SET urutan = ? WHERE id = ?', [(int) $ini['urutan'], (int) $tetangga['id']]);
}

// ---------------------------------------------------------------------------------------------
// PESAN KONTAK
// ---------------------------------------------------------------------------------------------

function landing_pesan_list(int $maks = 100): array
{
    $maks = max(1, min(500, $maks));
    try {
        return all('SELECT * FROM landing_pesan ORDER BY id DESC LIMIT ' . $maks);
    } catch (Throwable $e) {
        return [];
    }
}

function landing_pesan_baru(): int
{
    try {
        return (int) val('SELECT COUNT(*) FROM landing_pesan WHERE dibaca = 0', [], 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function landing_pesan_jumlah(): int
{
    try {
        return (int) val('SELECT COUNT(*) FROM landing_pesan', [], 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function landing_pesan_hapus(int $id): void
{
    if ($id <= 0) {
        return;
    }
    q('DELETE FROM landing_pesan WHERE id = ?', [$id]);
}

function landing_pesan_tandai(int $id, bool $dibaca = true): void
{
    if ($id <= 0) {
        return;
    }
    q('UPDATE landing_pesan SET dibaca = ? WHERE id = ?', [$dibaca ? 1 : 0, $id]);
}

/** Ringkasan isi tabel landing (dipakai halaman Super Admin). */
function landing_ringkas(): array
{
    $out = [];
    foreach (['slider', 'fitur', 'unggulan', 'testimoni'] as $j) {
        $tabel = landing_tabel($j);
        try {
            $total = (int) val('SELECT COUNT(*) FROM ' . $tabel, [], 0);
            $aktif = (int) val('SELECT COUNT(*) FROM ' . $tabel . ' WHERE aktif = 1', [], 0);
        } catch (Throwable $e) {
            $total = 0;
            $aktif = 0;
        }
        $out[$j] = ['total' => $total, 'aktif' => $aktif];
    }
    return $out;
}

// ---------------------------------------------------------------------------------------------
// KONTAK & IKON
// ---------------------------------------------------------------------------------------------

/** Tautan WhatsApp dari nomor pada isian kontak (08… → 628…). */
function landing_wa_tautan(string $pesan = ''): string
{
    $nomor = preg_replace('/\D+/', '', landing_teks('kontak_telepon')) ?? '';
    if ($nomor === '') {
        return '';
    }
    $nomor = wa_internasional($nomor);
    $tautan = 'https://wa.me/' . $nomor;
    if ($pesan !== '') {
        $tautan .= '?text=' . rawurlencode($pesan);
    }
    return $tautan;
}

/**
 * Daftar ikon yang boleh dipilih untuk fitur & keunggulan.
 * Seluruhnya MEMAKAI ikon yang sudah ada di `ikon()` (inc/layout.php) supaya tampilannya
 * seragam dengan ikon di dalam aplikasi — tidak ada pustaka luar.
 */
function landing_ikon_list(): array
{
    return [
        'check'    => 'Centang / Selesai',
        'chart'    => 'Grafik / Nilai Otomatis',
        'report'   => 'Laporan & Rekap',
        'calendar' => 'Jadwal / Tanggal',
        'clock'    => 'Jam / Waktu',
        'camera'   => 'Kamera / Bukti Foto',
        'doc'      => 'Dokumen / Berkas',
        'print'    => 'Cetak / Printer',
        'upload'   => 'Impor / Unggah',
        'download' => 'Ekspor / Unduh',
        'school'   => 'Sekolah / Madrasah',
        'globe'    => 'Internet / Dunia',
        'star'     => 'Bintang / Kualitas',
        'medal'    => 'Medali / Penghargaan',
        'shield'   => 'Perisai / Keamanan',
        'eye'      => 'Mata / Pengawasan',
        'users'    => 'Beberapa Pengguna',
        'user'     => 'Pengguna',
        'student'  => 'Siswa',
        'book'     => 'Buku / Jurnal',
        'clipboard'=> 'Daftar Hadir',
        'alert'    => 'Peringatan / Pelanggaran',
        'card'     => 'Kartu Pelajar',
        'key'      => 'Kunci / Akun',
        'support'  => 'Dukungan / Bantuan',
        'chat'     => 'Percakapan',
        'heart'    => 'Hati / Kepuasan',
        'apps'     => 'Aplikasi',
    ];
}

/** Nama ikon yang sah (tidak dikenal → 'check'). */
function landing_ikon_sah(string $nama): string
{
    return array_key_exists($nama, landing_ikon_list()) ? $nama : 'check';
}

// ---------------------------------------------------------------------------------------------
// ALAT BANTU (pemotongan teks + unggahan foto)
// ---------------------------------------------------------------------------------------------

/** Potong teks ke sejumlah karakter (memakai mb_* bila tersedia). */
function landing_potong(?string $teks, int $maks): string
{
    $teks = trim((string) $teks);
    if ($maks <= 0) {
        return '';
    }
    if (function_exists('mb_substr')) {
        return (string) mb_substr($teks, 0, $maks);
    }
    return strlen($teks) > $maks ? substr($teks, 0, $maks) : $teks;
}

/**
 * Unggah satu berkas foto dari formulir ($_FILES[$field]) ke media proxy platform.
 * Yang dikembalikan hanya URL hasil respons; URL itulah yang disimpan ke basis data.
 *
 * @return array{ok:bool, url:string, pesan:string}
 */
function landing_unggah_foto_berkas(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!is_array($f) || trim((string) ($f['name'] ?? '')) === '') {
        return ['ok' => false, 'url' => '', 'pesan' => 'Tidak ada berkas foto yang dipilih.'];
    }
    $kode = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($kode !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'url' => '', 'pesan' => ($kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE)
            ? 'Berkasnya melebihi batas server (' . ini_get('upload_max_filesize') . '). Perkecil dulu fotonya.'
            : 'Gagal diunggah (kode ' . $kode . '). Silakan coba lagi.'];
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

// ---------------------------------------------------------------------------------------------
// ISI AWAL (dijalankan SEKALI oleh migrasi)
// ---------------------------------------------------------------------------------------------

/**
 * Isi halaman depan dengan isi bawaan SEKALI SAJA.
 * Penanda `landing_seed` membuat isi yang sudah disunting Super Admin tidak pernah ditimpa
 * (termasuk bila ia mengosongkan seluruh daftar atau seluruh teksnya).
 *
 * Pemeriksaan "tabel masih kosong" pada bagian slider & daftar lain melindungi keadaan ketika
 * basis data sudah berisi isian pemilik sementara penandanya belum sempat ditulis.
 */
function landing_seed(PDO $pdo): void
{
    $sudah = (int) $pdo->query("SELECT COUNT(*) FROM app_pengaturan WHERE kunci = 'landing_seed'")->fetchColumn();
    if ($sudah > 0) {
        return;
    }

    $st = $pdo->prepare('INSERT INTO app_pengaturan (kunci, nilai) VALUES (?, ?)
                         ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai');
    foreach (landing_bawaan() as $k => $v) {
        $st->execute(['landing_' . $k, (string) $v]);
    }

    $kini = date('Y-m-d H:i:s');
    $isi = landing_daftar_bawaan();

    if ((int) $pdo->query('SELECT COUNT(*) FROM landing_slider')->fetchColumn() === 0) {
        $urut = 10;
        foreach ($isi['slider'] as $s) {
            $pdo->prepare('INSERT INTO landing_slider (judul, keterangan, foto, urutan, aktif, dibuat) VALUES (?,?,NULL,?,1,?)')
                ->execute([$s[0], $s[1], $urut, $kini]);
            $urut += 10;
        }
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM landing_fitur')->fetchColumn() === 0) {
        $urut = 10;
        foreach ($isi['fitur'] as $f) {
            $pdo->prepare('INSERT INTO landing_fitur (ikon, judul, teks, urutan, aktif, dibuat) VALUES (?,?,?,?,1,?)')
                ->execute([$f[0], $f[1], $f[2], $urut, $kini]);
            $urut += 10;
        }
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM landing_unggulan')->fetchColumn() === 0) {
        $urut = 10;
        foreach ($isi['unggulan'] as $f) {
            $pdo->prepare('INSERT INTO landing_unggulan (ikon, judul, teks, urutan, aktif, dibuat) VALUES (?,?,?,?,1,?)')
                ->execute([$f[0], $f[1], $f[2], $urut, $kini]);
            $urut += 10;
        }
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM landing_testimoni')->fetchColumn() === 0) {
        $urut = 10;
        foreach ($isi['testimoni'] as $f) {
            $pdo->prepare('INSERT INTO landing_testimoni (nama, jabatan, isi, urutan, aktif, dibuat) VALUES (?,?,?,?,1,?)')
                ->execute([$f[0], $f[1], $f[2], $urut, $kini]);
            $urut += 10;
        }
    }

    $st->execute(['landing_seed', $kini]);
}
