<?php
/**
 * Lapisan database (SQLite via PDO): skema, migrasi, dan data awal aplikasi e-GURU.
 *
 * Aplikasi ini MULTI-SEKOLAH (lintas sekolah/madrasah). Hampir semua tabel operasional
 * memiliki kolom `sekolah_id`, dan setiap query WAJIB dibatasi ke sekolah yang sedang aktif
 * (lihat current_sekolah()/sekolah_id() di inc/helpers.php).
 *
 * Catatan penting: ensure_schema() dipanggil pada setiap request dan SELURUH isinya dibungkus
 * SATU transaksi (BEGIN IMMEDIATE) — tanpa itu setiap statement idempoten (termasuk yang tidak
 * mengubah apa pun) membayar satu fsync sehingga halaman menjadi lambat.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php'; // untuk setting_default() saat seed (tidak ada query saat di-include)
// Modul HALAMAN DEPAN (landing page, skema v44) — dipakai ensure_schema() saat menyemai isi awal.
// Dimuat di sini (bukan hanya lewat inc/init.php) supaya db.php tetap dapat dijalankan sendiri.
require_once __DIR__ . '/landing.php';

/** Naikkan angka ini setiap kali skema berubah agar migrasi/seed ikut berjalan. */
const SCHEMA_VERSION = '58';

/**
 * Kolom yang MEMASTIKAN isi basis data sudah benar-benar versi terkini — dipakai
 * ensure_schema() sebagai pemeriksaan KEDUA di samping penanda `data/.schema_ok`.
 *
 * Kenapa perlu: penanda saja TIDAK cukup. Penanda ditulis sesudah COMMIT, tetapi bila
 * tulisan terakhir basis data kemudian hilang (mis. berkas `-wal` terpotong/terhapus,
 * atau berkas basis data tertimpa salinan lama) maka penanda bisa LEBIH BARU daripada
 * isi basis data. Karena penanda dianggap cocok, migrasi TIDAK PERNAH berjalan lagi dan
 * halaman yang memakai kolom baru gagal (pernah terjadi pada basis data live:
 * "table siswa has no column named foto" → menyimpan data siswa tidak tersimpan).
 *
 * ATURAN: setiap kali SCHEMA_VERSION dinaikkan, tambahkan kolom TERBARU di sini
 * (tabel => kolom, atau tabel => [beberapa kolom]) supaya keadaan itu bisa memulihkan diri sendiri.
 */
const SCHEMA_KOLOM_WAJIB = [
    // v10 (pas foto) + v17 (impor data siswa: tempat lahir, ayah, ibu)
    'siswa' => ['foto', 'tempat_lahir', 'nama_ayah', 'nama_ibu'],
    // v53: `baru_pada` = waktu aplikasi ditandai Super Admin → lencana "Bonus" berkedip pada sidebar
    // untuk menu Smart Teacher (permintaan pemilik v1.72.2). Sebelumnya fitur ini hanya ada pada
    // `ujian_online` (v43).
    'smart_teacher' => ['link', 'baru_pada'],
    'aplikasi_sagu' => 'link',      // v26: menu "Aplikasi Sagu" (di bawah Smart Teacher)
    // v54: menu "Studio Foto Pintar" (di bawah Smart Teacher) — daftar aplikasi GLOBAL seperti
    // Smart Teacher, dengan lencana "Bonus" (kolom `baru_pada`) & keterangan "Aplikasi Sementara
    // Dikembangkan" selama tautannya belum diisi Super Admin.
    'studio_foto' => ['link', 'baru_pada'],
    // v32: menu "Bendahara" (daftar aplikasi keuangan). `akses_diatur` = penanda "daftar
    // sekolah/madrasah pengaksesnya sudah diatur Super Admin" (lihat daftar_apps_app_boleh()).
    'bendahara' => ['link', 'akses_diatur'],
    // v33: menu Tugas Tambahan "Bendahara Madrasah" — daftar aplikasi TERPISAH dari "Bendahara Bos"
    // (permintaan pemilik), dengan penyetelan akses per aplikasinya sendiri.
    'bendahara_madrasah' => ['link', 'akses_diatur'],
    'hasil_supervisi' => 'link',
    // v42: menu "Ujian Online" (di bawah Pelanggaran Siswa) — daftar link aplikasi ujian online
    // yang diisi Super Admin, bentuknya sama dengan Smart Teacher.
    // v43: `baru_pada` = waktu aplikasi ditandai Super Admin (lencana "Bonus" berkedip pada sidebar;
    // teksnya "NEW" sebelum v1.72.2).
    'ujian_online' => ['link', 'baru_pada'],
    // v13 (aktivasi) + v22 (BAGAN susunan organisasi OSIM: judul & periode yang bisa disesuaikan)
    // + v25 (judul & periode bagan untuk menu PEMBINA PRAMUKA — terpisah dari OSIM)
    // v35: `aktif_sampai` = akhir masa aktif 365 hari (mode 'aktif'), `aktivasi_ke` = periode ke-.
    'sekolah' => ['aktivasi', 'osim_bagan_judul', 'osim_bagan_periode',
                  'pramuka_bagan_judul', 'pramuka_bagan_periode',
                  // v38: judul & periode bagan menu PEMBINA PMR (terpisah dari OSIM/Pramuka).
                  'pmr_bagan_judul', 'pmr_bagan_periode',
                  'aktif_sampai', 'aktivasi_ke',
                  // v37: identitas lembaga (NSM + alamat per wilayah) — dipakai menu
                  // Identitas & Pengaturan, halaman pendaftaran, dan kop dokumen cetak.
                  'nsm', 'desa', 'kecamatan', 'kabupaten', 'provinsi',
                  // v39: identitas PENGAWAS BINA (nama & NIP) — diisi saat pendaftaran sekolah dan
                  // dapat diubah di Identitas & Pengaturan (permintaan pemilik).
                  'pengawas_nama', 'pengawas_nip',
                  // v49: PILIHAN JENJANG — SD/SMP/SMA/SMK (sekolah umum) atau MI/MTs/MA/MAK
                  // (madrasah). Menentukan acuan tingkat kelas & apakah lembaga memakai konsep
                  // GURU KELAS (jenjang SD/MI).
                  'jenjang'],
    // v49: `guru_kelas` = penanda GURU KELAS (jenjang SD/MI). Guru kelas memegang BANYAK mata
    // pelajaran pada SATU kelas dan otomatis ditetapkan sebagai wali kelas kelas itu.
    'guru' => 'guru_kelas',
    'osim_pengurus' => ['bagan', 'kode'],   // v22: tingkat bagan · v25: pemisah menu OSIM/Pramuka
    'osim_program' => 'kode',               // v25: pemisah menu OSIM/Pramuka
    // v46: `sasaran` = pemisah isi popup sambutan (pengguna / siswa).
    'pengumuman' => 'sasaran',
    // v36: Jurnal Harian Guru — `uraian` = Uraian Kegiatan (bawaan 'Mengajar') dan `tempat` =
    // tempat kegiatan bila guru TIDAK memilih kelas (Kelas/Tempat).
    'jurnal' => ['uraian', 'tempat'],
    // v40: `ditinjau` = penanda "sudah ditinjau Super Admin" pada AKUN PENGAWAS. Akun pengawas yang
    // dibuat OTOMATIS dari pendaftaran sekolah ber-nilai 0 (menunggu ditinjau), akun lain 1.
    // v45: `siswa_id` = tautan akun SISWA ke baris `siswa` (akun ini dibuat otomatis saat siswa
    // masuk pertama kali dengan NISN-nya). Daftar beberapa kolom wajib diperbolehkan — lihat
    // catatan "SCHEMA_KOLOM_WAJIB boleh berisi DAFTAR kolom".
    // v40 `ditinjau`, v45 `siswa_id`, v58 `sandi_wajib_ganti`/`sandi_diubah` (kebijakan kata sandi).
    // ⚠️ SATU entri saja untuk tabel `users` — kunci array PHP tidak boleh kembar, entri kedua
    // akan MENIMPA yang pertama sehingga pemeriksaan pemulihan diri untuk kolom lain hilang.
    'users' => ['ditinjau', 'siswa_id', 'sandi_wajib_ganti', 'sandi_diubah'],
    // v9: `mapel_id` = mata pelajaran pada catatan pelanggaran (dulu hanya lewat db_add_column).
    // v51: TINDAK LANJUT oleh guru Tugas Tambahan "Kesiswaan" — 4 kolom (lihat inc/kesiswaan.php).
    'kasus' => ['mapel_id', 'tindak_lanjut', 'tindak_lanjut_jenis',
                'tindak_lanjut_oleh', 'tindak_lanjut_pada'],
];

/**
 * TABEL yang harus ada pada skema terkini — pasangan SCHEMA_KOLOM_WAJIB untuk kasus yang berbeda:
 * bila penanda skema mendahului basis data, bisa jadi yang belum ada adalah TABEL BARU (bukan kolom
 * baru). Pemeriksaan kolom saja tidak menangkapnya — pernah terjadi pada basis data live:
 * penanda sudah 13 sementara tabel `app_pengaturan` belum dibuat sehingga halaman yang memakainya
 * gagal ("no such table: app_pengaturan").
 * ATURAN: setiap kali SCHEMA_VERSION dinaikkan dengan TABEL baru, daftarkan tabelnya di sini.
 */
const SCHEMA_TABEL_WAJIB = ['app_pengaturan', 'pengawas_sekolah', 'guru_tugas',
    // v44: HALAMAN DEPAN (LANDING PAGE) — slider beranda, fitur aplikasi, keunggulan, testimoni,
    // dan pesan kontak dari pengunjung. Seluruhnya GLOBAL (halaman depan dipakai semua
    // sekolah/madrasah), lihat inc/landing.php.
    'landing_slider', 'landing_fitur', 'landing_unggulan', 'landing_testimoni', 'landing_pesan',
    'nilai_pengaturan', // pengaturan penilaian per (guru, mapel) — skema v31
    'pengumuman',      // pengumuman sambutan (skema v16)
    'aplikasi_sagu',   // daftar aplikasi menu "Aplikasi Sagu", GLOBAL (skema v26)
    // v54: daftar aplikasi menu "Studio Foto Pintar" — bentuknya sama dengan `smart_teacher`
    // (nama, link, keterangan, urutan, aktif, baru_pada).
    // ⚠️ v1.75.0: MENU-nya DIHAPUS atas permintaan pemilik, tetapi TABEL-nya sengaja DIBIARKAN supaya
    // isian pemilik tidak hilang. Jangan di-drop tanpa bertanya.
    'studio_foto',
    // v55: DAFTAR HADIR MEETING/PERTEMUAN PENGAWAS — `meeting` (satu pertemuan, GLOBAL oleh pengguna),
    // `meeting_sekolah` (binaan yang daftar hadirnya diaktifkan), dan `meeting_hadir` (tanda tangan
    // tiap akun lembaga). Lihat inc/meeting.php.
    'meeting', 'meeting_sekolah', 'meeting_hadir',
    'ujian_online',    // daftar link aplikasi menu "Ujian Online", GLOBAL (skema v42)
    'tugas_menu',      // daftar menu Tugas Tambahan, GLOBAL (skema v18) — sejak v30 juga menjadi
                       // SATU-SATUNYA daftar Tugas Tambahan (pilihan pada Data Guru);
                       // tabel lama `tugas_tambahan` (per sekolah) sudah DIHAPUS oleh migrasi v30.
    'user_tugas',      // penugasan menu Tugas Tambahan kepada pengguna (skema v18)
    'sarpras_barang',  // data barang Sarana & Prasarana PER SEKOLAH (skema v19)
    'kesiswaan_program', // program kerja Kesiswaan PER SEKOLAH (skema v20)
    'osim_pengurus',   // susunan organisasi OSIM/OSIS PER SEKOLAH (skema v21)
    'osim_program',    // program kerja OSIM/OSIS PER SEKOLAH (skema v21)
    'dokumen',         // dokumen dari menu Kurikulum untuk guru (skema v27)
    'menu_sekolah',    // pembatasan menu daftar aplikasi per sekolah (skema v28)
    'bendahara',       // daftar aplikasi menu "Bendahara", GLOBAL (skema v32)
    'bendahara_madrasah', // daftar aplikasi menu Tugas Tambahan "Bendahara Madrasah", GLOBAL (skema v33)
    'app_sekolah',     // pembatasan akses PER APLIKASI ke sekolah/madrasah tertentu (skema v32)
    'wali_struktur',   // struktur organisasi kelas - menu Wali Kelas (skema v29)
    'wali_program',    // program kerja wali kelas (skema v29)
    'wali_absensi',    // absensi harian kelas (skema v29)
    'wali_absensi_detail',
    'wali_piket',
    // v48: FITUR "KIRIM PERANGKAT" — daftar NAMA DOKUMEN perangkat yang diisi Super Admin (GLOBAL,
    // bebas ditambah/dikurangi) + kiriman perangkat guru per sekolah (berkas, tahun ajaran, status
    // tinjauan kepala sekolah).
    // Catatan: tabel lama `perangkat_berkas`/`perangkat_kode` (halaman publik Perangkat Guru yang
    // DIHAPUS pada v1.67.0) sudah tidak dibuat maupun diperiksa lagi.
    'perangkat_jenis', 'perangkat_kirim',
    // v52: CATATAN PENGAWAS pada tiap perangkat guru — tabel TERPISAH dari status tinjauan kepala
    // sekolah (permintaan pemilik: "Catatan pengawas terpisah dari status tinjauan kepala (guru &
    // kepala sekolah bisa membacanya)"). Satu baris per (kiriman perangkat × pengawas), sehingga
    // beberapa pengawas dapat memberi catatan pada perangkat yang sama dan masing-masing hanya
    // dapat mengubah catatannya sendiri.
    'perangkat_catatan',
    'perpus_laporan',  // kepala laporan perpustakaan hasil impor e-PERPUS (skema v23)
    'perpus_baris',    // baris laporan perpustakaan hasil impor e-PERPUS (skema v23)
    'lab_barang',      // daftar inventaris barang Kepala Lab IPA/Komputer (skema v24)
    'lab_program',     // laporan program kerja Kepala Lab IPA/Komputer (skema v24)
    // OBROLAN/CHAT sesama pengguna aplikasi (skema v41) — SATU ruang bersama untuk semua peran:
    // `chat_pesan` menyimpan snapshot nama/asal sekolah pengirim, `chat_baca` menyimpan id pesan
    // terakhir yang sudah dibaca tiap pengguna (dasar lencana "belum dibaca").
    'chat_pesan', 'chat_baca',
    // v50: TANDA TANGAN DIGITAL notulen rapat (layar sentuh) — hanya URL CDN hasil media proxy.
    'notulen_ttd',
    // v57: PEMBATAS LAJU ENDPOINT PUBLIK (pendaftaran sekolah & formulir pesan halaman depan) —
    // satu baris per kejadian; lihat throttle_lewat() di inc/helpers.php.
    'throttle'];

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = dirname(DB_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');

    ensure_schema($pdo);

    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function val(string $sql, array $params = [], $default = null)
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? $default : $v;
}

function db_has_column(PDO $pdo, string $table, string $column): bool
{
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')') as $r) {
        if (strcasecmp((string) $r['name'], $column) === 0) {
            return true;
        }
    }
    return false;
}

function db_has_table(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
    $st->execute([$table]);
    return (int) $st->fetchColumn() > 0;
}

/** Tambah kolom bila belum ada (untuk basis data yang sudah terisi data). */
function db_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!db_has_column($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }
}

/** Definisi SQL sebuah tabel (untuk mendeteksi skema lama). */
function db_table_sql(PDO $pdo, string $table): string
{
    $st = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?");
    $st->execute([$table]);
    return (string) $st->fetchColumn();
}

/** Nilai kolom pertama dari satu query (dipakai di dalam migrasi/seed). */
function db_val(PDO $pdo, string $sql, array $params = [])
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

/** Data awal mata pelajaran untuk sekolah/madrasah baru. */
function mapel_awal(): array
{
    return [
        ['PABP', 'Pendidikan Agama dan Budi Pekerti', 'Kelompok A'],
        ['PPKn', 'Pendidikan Pancasila', 'Kelompok A'],
        ['BIND', 'Bahasa Indonesia', 'Kelompok A'],
        ['MTK', 'Matematika', 'Kelompok A'],
        ['IPA', 'Ilmu Pengetahuan Alam', 'Kelompok A'],
        ['IPS', 'Ilmu Pengetahuan Sosial', 'Kelompok A'],
        ['BING', 'Bahasa Inggris', 'Kelompok A'],
        ['PJOK', 'Pendidikan Jasmani, Olahraga, dan Kesehatan', 'Kelompok B'],
        ['SENI', 'Seni Budaya', 'Kelompok B'],
        ['INF', 'Informatika', 'Kelompok B'],
        ['BAR', 'Bahasa Arab', 'Muatan Lokal'],
        ['MULOK', 'Muatan Lokal', 'Muatan Lokal'],
    ];
}

/**
 * MIGRASI v30 — gabungkan `tugas_tambahan` (per sekolah) ke SATU daftar `tugas_menu` (GLOBAL).
 *
 * Permintaan pemilik: "Daftar Tugas Tambahan yang ada di akun operator harus sama atau bersumber
 * dari Daftar Tugas tambahan yang diinput oleh Super Admin. Hapus fitur Tambah Tugas Tambahan yang
 * ada di akun operator." Dulu setiap sekolah punya salinan 9 contoh bawaan sendiri (sumber
 * duplikat + diisi tiap sekolah), sekarang hanya ada SATU daftar yang diisi Super Admin.
 *
 * Urutan kerja:
 *   1. setiap nama `tugas_tambahan` dicocokkan dengan `tugas_menu` (nama dinormalkan lewat
 *      `tugas_nama_kunci()`: huruf kecil, tanda baca → spasi, spasi dirapikan);
 *   2. nama lama yang punya padanan (mis. "Wakasek Kesiswaan" → menu "Kesiswaan",
 *      "Sarana & Prasarana" → "Sarpras") memakai menu itu — lihat `tugas_legacy_alias()`;
 *   3. nama lama yang TIDAK punya padanan dibuatkan baris menu BARU dengan nama yang sama dan
 *      **aktif = 0** (supaya tidak mendadak muncul sebagai menu sidebar), sehingga data guru yang
 *      sudah memakainya tidak hilang; Super Admin boleh mengubah/aktifkan namanya kemudian;
 *   4. nama yang tidak bermakna (mis. "-") dilewati — relasinya dibuang karena memang bukan tugas;
 *   5. `guru_tugas` diisi ulang dengan id menu BARU (id lama tidak dipakai lagi) lalu tabel
 *      `tugas_tambahan` DIHAPUS.
 *
 * Fungsi ini dipanggil dari dalam transaksi `ensure_schema()` dan hanya berjalan bila tabel lama
 * masih ada (jadi idempoten: sekali tabelnya terhapus, tidak pernah dijalankan lagi).
 */
function tugas_gabung_ke_menu(PDO $pdo, string $now): void
{
    // ---- 0. tugas lama yang BENAR-BENAR dipakai guru (hanya itu yang perlu dipertahankan)
    $dipakai = [];
    foreach ($pdo->query('SELECT DISTINCT tugas_id FROM guru_tugas')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $dipakai[(int) $r['tugas_id']] = true;
    }

    // ---- 1. peta nama menu (dinormalkan) → id, sekaligus kode yang sudah dipakai + urutan akhir
    $petaNama = [];
    $kodeAda = [];
    $urutMax = 0;
    $barisMenu = $pdo->query('SELECT id, kode, nama, urutan FROM tugas_menu')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($barisMenu as $m) {
        $petaNama[tugas_nama_kunci((string) $m['nama'])] = (int) $m['id'];
        $kodeAda[(string) $m['kode']] = true;
        $urutMax = max($urutMax, (int) $m['urutan']);
    }
    $alias = tugas_legacy_alias();

    // ---- 2/3/4. tentukan id menu untuk SETIAP baris tugas_tambahan lama
    $peta = [];          // tugas_tambahan.id => tugas_menu.id  (0 = dibuang)
    $dilewati = 0;       // nama tanpa makna / tidak dipakai siapa pun
    $dibuat = 0;         // nama lama yang dibuatkan menu baru (nonaktif)
    $insMenu = $pdo->prepare('INSERT INTO tugas_menu (kode, nama, urutan, aktif, dibuat) VALUES (?,?,?,0,?)');
    foreach ($pdo->query('SELECT id, nama FROM tugas_tambahan')->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $idLama = (int) $t['id'];
        $nama = trim((string) $t['nama']);
        $kunci = tugas_nama_kunci($nama);
        if ($kunci === '') {
            $peta[$idLama] = 0;
            if (isset($dipakai[$idLama])) {
                $dilewati++;
            }
            continue;
        }
        if (isset($petaNama[$kunci])) {
            $peta[$idLama] = $petaNama[$kunci];
            continue;
        }
        // Padanan nama lama → nama menu (hanya dipakai bila menu tujuannya memang ada).
        $tujuan = $alias[$kunci] ?? '';
        $kunciTujuan = $tujuan !== '' ? tugas_nama_kunci($tujuan) : '';
        if ($kunciTujuan !== '' && isset($petaNama[$kunciTujuan])) {
            $peta[$idLama] = $petaNama[$kunciTujuan];
            continue;
        }
        // Tidak ada padanan: menu baru (NONAKTIF) dibuat HANYA bila tugas lama itu dipakai guru —
        // kalau tidak dipakai siapa pun, tidak ada data yang perlu diselamatkan (jangan menambah
        // baris baru ke daftar Super Admin tanpa alasan).
        if (!isset($dipakai[$idLama])) {
            $peta[$idLama] = 0;
            continue;
        }
        if (strlen($nama) > 60) {
            $nama = substr($nama, 0, 60);
        }
        $kunciBaru = tugas_nama_kunci($nama);
        if (isset($petaNama[$kunciBaru])) {  // bisa terjadi karena pemotongan di atas
            $peta[$idLama] = $petaNama[$kunciBaru];
            continue;
        }
        $urutMax += 10;
        $kode = tugas_kode_baru(strtolower((string) preg_replace('/[^a-z0-9]+/', '-', $kunciBaru)), $kodeAda);
        $insMenu->execute([$kode, $nama, $urutMax, $now]);
        $idBaru = (int) $pdo->lastInsertId();
        $petaNama[$kunciBaru] = $idBaru;
        $kodeAda[$kode] = true;
        $peta[$idLama] = $idBaru;
        $dibuat++;
    }

    // ---- 5. guru_tugas diisi ulang dengan id menu yang baru, lalu tabel lama dihapus
    $relasi = $pdo->query('SELECT guru_id, tugas_id FROM guru_tugas')->fetchAll(PDO::FETCH_ASSOC);
    $pdo->exec('DROP TABLE guru_tugas');
    $pdo->exec('CREATE TABLE guru_tugas (
        guru_id INTEGER NOT NULL REFERENCES guru(id) ON DELETE CASCADE,
        tugas_id INTEGER NOT NULL REFERENCES tugas_menu(id) ON DELETE CASCADE,
        PRIMARY KEY (guru_id, tugas_id)
    )');
    $insRel = $pdo->prepare('INSERT OR IGNORE INTO guru_tugas (guru_id, tugas_id) VALUES (?,?)');
    $dipetakan = 0;
    foreach ($relasi as $r) {
        $tujuan = $peta[(int) $r['tugas_id']] ?? 0;
        if ($tujuan <= 0) {
            continue;
        }
        $insRel->execute([(int) $r['guru_id'], $tujuan]);
        $dipetakan++;
    }
    $pdo->exec('DROP TABLE tugas_tambahan');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_guru_tugas ON guru_tugas(tugas_id)');

    // Jejak migrasi ditulis ke log server supaya hasilnya bisa diperiksa bila perlu.
    error_log(sprintf('e-GURU migrasi v30: %d relasi guru-tugas dipetakan, %d menu baru dibuat, %d nama dilewati.',
        $dipetakan, $dibuat, $dilewati));
}

/**
 * Kode unik untuk menu Tugas Tambahan yang BARU (dipakai migrasi v30). `$dasar` sudah berupa slug
 * huruf kecil; bila sudah dipakai, ditambah angka. Kode milik modul bawaan selalu dihindari.
 *
 * @param array<string,bool> $kodeAda
 */
function tugas_kode_baru(string $dasar, array $kodeAda): string
{
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
    $khusus = array_merge(
        [SARPRAS_KODE_MENU, KS_KODE_MENU, PERPUS_KODE_MENU, KURIKULUM_KODE_MENU, WALI_KODE_MENU],
        OSIM_KODE_LIST,
        LAB_KODE_MENU
    );
    $kode = $dasar;
    $i = 2;
    while (isset($kodeAda[$kode]) || in_array($kode, $khusus, true)) {
        $kode = $dasar . '-' . $i;
        $i++;
    }
    return $kode;
}

/**
 * Siapkan isi dasar sebuah sekolah/madrasah baru: 12 mata pelajaran awal,
 * tahun ajaran berjalan (Juli–Juni) beserta semester Ganjil & Genap,
 * lalu tandai semester Ganjil sebagai periode aktif.
 */
function provision_sekolah(PDO $pdo, int $sekolahId): void
{
    if ($sekolahId <= 0) {
        return;
    }
    $now = date('Y-m-d H:i:s');

    if ((int) db_val($pdo, 'SELECT COUNT(*) FROM mapel WHERE sekolah_id = ?', [$sekolahId]) === 0) {
        $insM = $pdo->prepare('INSERT INTO mapel (sekolah_id, kode, nama, kelompok, urutan, aktif) VALUES (?,?,?,?,?,1)');
        $u = 0;
        foreach (mapel_awal() as $m) {
            $u += 10;
            $insM->execute([$sekolahId, $m[0], $m[1], $m[2], $u]);
        }
    }

    // CATATAN (sejak skema v30): TIDAK ADA lagi contoh Tugas Tambahan per sekolah — daftar tugas
    // tambahan memakai satu daftar GLOBAL (`tugas_menu`) yang diisi Super Admin. Tabel
    // `tugas_tambahan` sudah dihapus oleh migrasi v30 (lihat tugas_gabung_ke_menu()).

    if ((int) db_val($pdo, 'SELECT COUNT(*) FROM tahun_ajaran WHERE sekolah_id = ?', [$sekolahId]) === 0) {
        $y = (int) date('n') >= 7 ? (int) date('Y') : (int) date('Y') - 1;
        $nama = $y . '/' . ($y + 1);
        $pdo->prepare('INSERT INTO tahun_ajaran (sekolah_id, nama, tanggal_mulai, tanggal_selesai, keterangan, aktif) VALUES (?,?,?,?,?,1)')
            ->execute([$sekolahId, $nama, $y . '-07-01', ($y + 1) . '-06-30', 'Tahun ajaran bawaan aplikasi']);
        $taId = (int) $pdo->lastInsertId();
        $sem = $pdo->prepare('INSERT INTO semester (ta_id, nama, urutan, aktif) VALUES (?,?,?,1)');
        $sem->execute([$taId, 'Ganjil', 1]);
        $semGanjil = (int) $pdo->lastInsertId();
        $sem->execute([$taId, 'Genap', 2]);
        $pdo->prepare('UPDATE sekolah SET ta_aktif_id = ?, semester_aktif_id = ? WHERE id = ?')->execute([$taId, $semGanjil, $sekolahId]);
    } else {
        // Pastikan periode aktif terisi walau tahun ajaran sudah ada.
        $taAktif = (int) db_val($pdo, 'SELECT ta_aktif_id FROM sekolah WHERE id = ?', [$sekolahId]);
        if ($taAktif <= 0) {
            $ta = db_val($pdo, 'SELECT id FROM tahun_ajaran WHERE sekolah_id = ? ORDER BY nama DESC LIMIT 1', [$sekolahId]);
            if ($ta) {
                $sem = db_val($pdo, 'SELECT id FROM semester WHERE ta_id = ? ORDER BY urutan LIMIT 1', [(int) $ta]);
                $pdo->prepare('UPDATE sekolah SET ta_aktif_id = ?, semester_aktif_id = ? WHERE id = ?')
                    ->execute([(int) $ta, (int) ($sem ?? 0), $sekolahId]);
            }
        }
    }

    // Semester Ganjil/Genap untuk tahun ajaran yang belum punya semester.
    foreach ($pdo->query('SELECT ta.id FROM tahun_ajaran ta WHERE ta.sekolah_id = ' . (int) $sekolahId
        . ' AND NOT EXISTS (SELECT 1 FROM semester s WHERE s.ta_id = ta.id)')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $sem = $pdo->prepare('INSERT INTO semester (ta_id, nama, urutan, aktif) VALUES (?,?,?,1)');
        $sem->execute([(int) $r['id'], 'Ganjil', 1]);
        $pdo->prepare('UPDATE sekolah SET ta_aktif_id = ?, semester_aktif_id = ? WHERE id = ?')
            ->execute([(int) $r['id'], (int) $pdo->lastInsertId(), $sekolahId]);
    }
}

/**
 * true bila SELURUH kolom pada SCHEMA_KOLOM_WAJIB benar-benar ada di basis data.
 * Dipakai untuk mendeteksi keadaan "penanda skema lebih baru daripada isi basis data".
 */
function skema_terkini(PDO $pdo): bool
{
    foreach (SCHEMA_TABEL_WAJIB as $tabel) {
        if (!db_has_table($pdo, (string) $tabel)) {
            return false;
        }
    }
    foreach (SCHEMA_KOLOM_WAJIB as $tabel => $kolom) {
        // Nilai boleh berupa satu nama kolom atau DAFTAR kolom (tabel yang punya beberapa kolom
        // wajib, mis. `siswa` pada skema v17) — jangan diubah menjadi pemeriksaan tunggal.
        foreach ((array) $kolom as $k) {
            if (!db_has_column($pdo, (string) $tabel, (string) $k)) {
                return false;
            }
        }
    }
    return true;
}

function ensure_schema(PDO $pdo): void
{
    // Penanda cepat: skema + seed hanya dihitung sekali, bukan tiap request.
    // Penanda juga diperiksa terhadap kolom WAJIB skema terkini (skema_terkini()): bila isi
    // basis data tertinggal dari penanda (mis. tulisan terakhir di berkas -wal hilang),
    // migrasi — yang seluruhnya idempoten — dijalankan lagi supaya basis data menyusul.
    $flag = dirname(DB_FILE) . '/.schema_ok';
    if (is_file($flag) && is_file(DB_FILE) && filesize(DB_FILE) > 0
        && trim((string) @file_get_contents($flag)) === SCHEMA_VERSION
        && skema_terkini($pdo)) {
        return;
    }

    $fkOn = ((int) $pdo->query('PRAGMA foreign_keys')->fetchColumn()) === 1;
    if ($fkOn) {
        $pdo->exec('PRAGMA foreign_keys = OFF');
    }

    // Apakah ini basis data yang SUDAH ADA (bukan instalasi baru)? Dipakai migrasi v58: akun lama
    // (dibuat sebelum kebijakan kata sandi berlaku) wajib mengganti sandi, sedangkan akun yang
    // dibuat oleh seed instalasi baru (admin bawaan) TIDAK boleh ikut ditandai.
    $databaseLama = db_has_table($pdo, 'users');

    $pdo->exec('BEGIN IMMEDIATE');
    try {
        // ---------------------------------------------------------------- tabel sekolah
        $pdo->exec('CREATE TABLE IF NOT EXISTS sekolah (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL DEFAULT "",
            npsn TEXT,
            -- v37: identitas lembaga dilengkapi (permintaan pemilik): NSM (Nomor Statistik Madrasah)
            -- serta alamat yang terpisah per wilayah (desa/kelurahan, kecamatan, kabupaten, provinsi).
            nsm TEXT,
            alamat TEXT,
            desa TEXT,
            kecamatan TEXT,
            kabupaten TEXT,
            provinsi TEXT,
            telepon TEXT,
            email TEXT,
            website TEXT,
            kepala_sekolah TEXT,
            nip_kepala_sekolah TEXT,
            -- v39: identitas PENGAWAS BINA (nama & NIP) — diisi saat pendaftaran sekolah
            -- (permintaan pemilik) dan dapat diubah di Identitas & Pengaturan.
            pengawas_nama TEXT,
            pengawas_nip TEXT,
            jenis TEXT NOT NULL DEFAULT "umum",
            -- v49: PILIHAN JENJANG (kosong = belum dipilih). Nilai sahnya bergantung pada kolom
            -- jenis: umum -> SD/SMP/SMA/SMK · madrasah -> MI/MTs/MA/MAK.
            jenjang TEXT,
            logo TEXT,
            ta_aktif_id INTEGER,
            semester_aktif_id INTEGER,
            jumlah_formatif INTEGER NOT NULL DEFAULT 4,
            jumlah_sumatif INTEGER NOT NULL DEFAULT 4,
            bobot_asas REAL NOT NULL DEFAULT 50,
            batas_a REAL NOT NULL DEFAULT 90,
            batas_b REAL NOT NULL DEFAULT 80,
            batas_c REAL NOT NULL DEFAULT 70,
            catatan TEXT,
            verifikasi TEXT NOT NULL DEFAULT "disetujui",
            aktif INTEGER NOT NULL DEFAULT 1,
            alasan_tolak TEXT,
            aktivasi TEXT NOT NULL DEFAULT "permanen",
            uji_coba_mulai TEXT,
            uji_coba_selesai TEXT,
            aktivasi_pada TEXT,
            -- v22/v25/v38: judul & periode bagan susunan organisasi PER MENU modul pembina
            -- (OSIM/OSIS, Pramuka, PMR) supaya menyimpan judul satu menu tidak menimpa menu lain.
            osim_bagan_judul TEXT,
            osim_bagan_periode TEXT,
            pramuka_bagan_judul TEXT,
            pramuka_bagan_periode TEXT,
            pmr_bagan_judul TEXT,
            pmr_bagan_periode TEXT,
            -- v35: masa aktif setelah AKTIVASI via kode (365 hari) + periode aktivasi ke-.
            aktif_sampai TEXT,
            aktivasi_ke INTEGER NOT NULL DEFAULT 1,
            kontak_nama TEXT,
            kontak_telepon TEXT,
            kontak_email TEXT,
            didaftar TEXT,
            ditinjau TEXT,
            dibuat TEXT
        )');

        // ---------------------------------------------------------------- tabel utama
        $pdo->exec('CREATE TABLE IF NOT EXISTS guru (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            nama TEXT NOT NULL,
            nip TEXT,
            nuptk TEXT,
            jenis_kelamin TEXT,
            jabatan TEXT,
            telepon TEXT,
            email TEXT,
            -- v49: 1 = GURU KELAS (jenjang SD/MI) — memegang banyak mata pelajaran pada satu kelas
            -- dan otomatis menjadi wali kelas kelas itu.
            guru_kelas INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            dibuat TEXT
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            nama TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT "guru",
            guru_id INTEGER REFERENCES guru(id) ON DELETE SET NULL,
            -- v58: kebijakan kata sandi (wajib ganti + waktu sandi terakhir diubah)
            sandi_wajib_ganti INTEGER NOT NULL DEFAULT 0,
            sandi_diubah TEXT,
            -- v45: tautan akun SISWA ke baris pada tabel siswa (peran baru bernama siswa yang
            -- masuk HANYA dengan NISN). SENGAJA TANPA klausa REFERENCES: tabel siswa dibuat
            -- SETELAH tabel ini sehingga rujukan ke depan itu berisiko; penghapusan siswa yang
            -- tertaut dibersihkan oleh aplikasi.
            siswa_id INTEGER,
            foto TEXT,
            aktif INTEGER NOT NULL DEFAULT 1,
            -- v40: 1 = tidak perlu ditinjau (bawaan) · 0 = akun pengawas yang dibuat OTOMATIS dari
            -- pendaftaran sekolah/madrasah dan masih menunggu tinjauan Super Admin.
            ditinjau INTEGER NOT NULL DEFAULT 1,
            login_terakhir TEXT,
            dibuat TEXT
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS mapel (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            kode TEXT,
            nama TEXT NOT NULL,
            kelompok TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS kelas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            nama TEXT NOT NULL,
            tingkat TEXT,
            wali_id INTEGER REFERENCES guru(id) ON DELETE SET NULL,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            UNIQUE (sekolah_id, nama)
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS guru_mapel (
            guru_id INTEGER NOT NULL REFERENCES guru(id) ON DELETE CASCADE,
            mapel_id INTEGER NOT NULL REFERENCES mapel(id) ON DELETE CASCADE,
            PRIMARY KEY (guru_id, mapel_id)
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS guru_kelas (
            guru_id INTEGER NOT NULL REFERENCES guru(id) ON DELETE CASCADE,
            kelas_id INTEGER NOT NULL REFERENCES kelas(id) ON DELETE CASCADE,
            PRIMARY KEY (guru_id, kelas_id)
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS siswa (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            nis TEXT,
            nisn TEXT,
            nama TEXT NOT NULL,
            jenis_kelamin TEXT,
            kelas_id INTEGER REFERENCES kelas(id) ON DELETE SET NULL,
            tempat_lahir TEXT,
            tanggal_lahir TEXT,
            nama_ayah TEXT,
            nama_ibu TEXT,
            nama_wali TEXT,
            telepon TEXT,
            alamat TEXT,
            aktif INTEGER NOT NULL DEFAULT 1,
            status TEXT NOT NULL DEFAULT "aktif",
            tanggal_status TEXT,
            foto TEXT,
            dibuat TEXT
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS tahun_ajaran (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            nama TEXT NOT NULL,
            tanggal_mulai TEXT,
            tanggal_selesai TEXT,
            keterangan TEXT,
            aktif INTEGER NOT NULL DEFAULT 1,
            UNIQUE (sekolah_id, nama)
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS semester (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ta_id INTEGER NOT NULL REFERENCES tahun_ajaran(id) ON DELETE CASCADE,
            nama TEXT NOT NULL,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            UNIQUE (ta_id, nama)
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS absensi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            tanggal TEXT NOT NULL,
            kelas_id INTEGER NOT NULL REFERENCES kelas(id) ON DELETE CASCADE,
            mapel_id INTEGER NOT NULL REFERENCES mapel(id) ON DELETE CASCADE,
            guru_id INTEGER REFERENCES guru(id) ON DELETE SET NULL,
            ta_id INTEGER NOT NULL,
            semester_id INTEGER NOT NULL,
            pertemuan INTEGER NOT NULL DEFAULT 1,
            materi TEXT,
            catatan TEXT,
            dibuat TEXT,
            dibuat_oleh INTEGER,
            UNIQUE (kelas_id, mapel_id, ta_id, semester_id, pertemuan)
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS absensi_detail (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            absensi_id INTEGER NOT NULL REFERENCES absensi(id) ON DELETE CASCADE,
            siswa_id INTEGER NOT NULL REFERENCES siswa(id) ON DELETE CASCADE,
            status TEXT NOT NULL DEFAULT "H",
            ket TEXT,
            UNIQUE (absensi_id, siswa_id)
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS nilai (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            siswa_id INTEGER NOT NULL REFERENCES siswa(id) ON DELETE CASCADE,
            kelas_id INTEGER NOT NULL REFERENCES kelas(id) ON DELETE CASCADE,
            mapel_id INTEGER NOT NULL REFERENCES mapel(id) ON DELETE CASCADE,
            ta_id INTEGER NOT NULL,
            semester_id INTEGER NOT NULL,
            jenis TEXT NOT NULL,
            kolom INTEGER NOT NULL DEFAULT 1,
            nilai REAL,
            diubah TEXT,
            UNIQUE (siswa_id, kelas_id, mapel_id, ta_id, semester_id, jenis, kolom)
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS jurnal (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            tanggal TEXT NOT NULL,
            kelas_id INTEGER REFERENCES kelas(id) ON DELETE CASCADE,
            mapel_id INTEGER REFERENCES mapel(id) ON DELETE CASCADE,
            guru_id INTEGER REFERENCES guru(id) ON DELETE SET NULL,
            ta_id INTEGER,
            semester_id INTEGER,
            jam_ke TEXT,
            materi TEXT,
            -- v36: Uraian Kegiatan (mis. Mengajar - pilihan bawaan, kegiatan lain diketik manual)
            -- dan TEMPAT kegiatan bila kegiatan dilakukan di luar kelas (Kelas/Tempat).
            uraian TEXT NOT NULL DEFAULT "Mengajar",
            tempat TEXT,
            sakit INTEGER NOT NULL DEFAULT 0,
            izin INTEGER NOT NULL DEFAULT 0,
            alpa INTEGER NOT NULL DEFAULT 0,
            bolos INTEGER NOT NULL DEFAULT 0,
            terlambat INTEGER NOT NULL DEFAULT 0,
            keterangan TEXT,
            kegiatan TEXT,
            kendala TEXT,
            tindak_lanjut TEXT,
            dibuat TEXT,
            dibuat_oleh INTEGER
        )');

        // ---------------------------------------------------------------- Notulen rapat (skema v7)
        // Berita acara rapat sekolah/madrasah + daftar hadir guru yang menghadiri rapat.
        // Satu baris `notulen` = satu berita acara; `notulen_hadir` menyimpan peserta yang dipilih
        // satu per satu dari daftar guru (nama/NIP/jabatan disimpan sebagai SNAPSHOT supaya lembar
        // yang sudah dicetak tetap utuh walau data guru kelak berubah atau dihapus).
        $pdo->exec('CREATE TABLE IF NOT EXISTS notulen (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            tanggal TEXT NOT NULL,
            waktu TEXT,
            tempat TEXT,
            judul TEXT,
            agenda TEXT,
            pembahasan TEXT,
            keputusan TEXT,
            pimpinan TEXT,
            pimpinan_jabatan TEXT,
            notulis TEXT,
            ta_id INTEGER,
            semester_id INTEGER,
            dibuat TEXT,
            dibuat_oleh INTEGER
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS notulen_hadir (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            notulen_id INTEGER NOT NULL REFERENCES notulen(id) ON DELETE CASCADE,
            guru_id INTEGER REFERENCES guru(id) ON DELETE SET NULL,
            nama TEXT NOT NULL,
            nip TEXT,
            jabatan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0
        )');

        // ---------------------------------------------------------------- Tanda tangan digital notulen (skema v50)
        // TANDA TANGAN LANGSUNG DI LAYAR SENTUH (permintaan pemilik: "Pada notulen rapat buat tanda
        // tangan langsung lewat HP seperti aplikasi TUSmart"). Coretan jari/stylus pada <canvas>
        // dikirim sebagai PNG ke `upload_notulen_ttd.php`, diunggah lewat media proxy platform, dan
        // yang disimpan di sini HANYA URL CDN-nya (bukan berkasnya).
        // Satu baris per (notulen, pihak); kunci `pihak` memakai ID GURU untuk peserta daftar hadir
        // (`hadir:g<guru_id>`) supaya tanda tangan tetap menempel pada orang yang sama walau daftar
        // hadir disunting (baris `notulen_hadir` dibuat ulang saat notulen diubah).
        // Dipakai bersama oleh `cetak_notulen.php` (lembar Daftar Hadir & blok tanda tangan) dan
        // `inc/notulen_ttd.php`.
        $pdo->exec('CREATE TABLE IF NOT EXISTS notulen_ttd (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            notulen_id INTEGER NOT NULL REFERENCES notulen(id) ON DELETE CASCADE,
            pihak TEXT NOT NULL,
            url TEXT NOT NULL,
            lebar INTEGER NOT NULL DEFAULT 0,
            tinggi INTEGER NOT NULL DEFAULT 0,
            oleh TEXT,
            ip TEXT,
            dibuat TEXT,
            diubah TEXT,
            UNIQUE (notulen_id, pihak)
        )');

        // ---------------------------------------------------------------- Foto bukti kegiatan (skema v8)
        // Foto bukti kegiatan rapat pada sebuah notulen. MAKSIMAL 2 foto per notulen
        // (NOTULEN_FOTO_MAKS di inc/config.php) — batasnya ditegakkan di sisi server oleh
        // upload_notulen_foto.php, bukan hanya oleh JavaScript.
        // Yang disimpan HANYA URL hasil media proxy platform, bukan berkasnya di server aplikasi.
        $pdo->exec('CREATE TABLE IF NOT EXISTS notulen_foto (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            notulen_id INTEGER NOT NULL REFERENCES notulen(id) ON DELETE CASCADE,
            url TEXT NOT NULL,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT,
            dibuat_oleh INTEGER
        )');

        // ---------------------------------------------------------------- Daftar aplikasi (skema v11, v12 & v26)
        // Tiga menu berisi daftar aplikasi (nama, tautan, keterangan) yang DIISI SUPER ADMIN dan
        // GLOBAL — TIDAK punya kolom `sekolah_id` (permintaan pemilik): satu daftar dipakai bersama
        // semua sekolah/madrasah. Hanya Super Admin yang boleh menambah/mengubah/menghapus.
        // Seluruh perilakunya satu modul (`inc/daftar_apps.php`) — menambah menu = satu entri
        // konfigurasi + satu berkas halaman tipis + satu tabel seperti di bawah.
        //   v11: `smart_teacher`   → menu "Smart Teacher"
        //   v12: `hasil_supervisi` → menu "Hasil Supervisi" (di bawah Smart Teacher)
        //   v26: `aplikasi_sagu`   → menu "Aplikasi Sagu" (di bawah Smart Teacher)
        // v53: kolom `baru_pada` (lencana "Bonus" berkedip) ikut ditulis di sini supaya instalasi
        // BARU langsung memilikinya; basis data lama mendapatkannya lewat migrasi v53 di bawah.
        $pdo->exec('CREATE TABLE IF NOT EXISTS smart_teacher (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            link TEXT,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            baru_pada TEXT,
            dibuat TEXT,
            diubah TEXT
        )');
        // APLIKASI SAGU (skema v26) — permintaan pemilik: "Tambahkan Menu Aplikasi Sagu dibawah
        // menu Smart Teacher. Cara pengisian link aplikasi seperti Smart Teacher" → bentuk tabelnya
        // SAMA dengan `smart_teacher` (GLOBAL, tanpa `sekolah_id`, diisi Super Admin saja).
        $pdo->exec('CREATE TABLE IF NOT EXISTS aplikasi_sagu (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            link TEXT,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            dibuat TEXT,
            diubah TEXT
        )');
        // STUDIO FOTO PINTAR (skema v54) — permintaan pemilik: *"dibawah menu Smart Teacher
        // tambahkan menu Studio Foto Pintar (tambahkan label Bonus seperti Menu Smart Teacher) dan
        // linknya akan di isi oleh super admin"* → bentuk tabelnya SAMA dengan `smart_teacher`
        // (GLOBAL, tanpa `sekolah_id`, hanya diisi Super Admin), termasuk kolom `baru_pada` untuk
        // lencana "Bonus" pada menu sidebar.
        $pdo->exec('CREATE TABLE IF NOT EXISTS studio_foto (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            link TEXT,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            baru_pada TEXT,
            dibuat TEXT,
            diubah TEXT
        )');
        // DAFTAR HADIR MEETING/PERTEMUAN PENGAWAS (skema v55) — lihat inc/meeting.php.
        // `meeting` sengaja TIDAK punya kolom `sekolah_id`: satu pertemuan dapat diaktifkan untuk
        // banyak binaan sekaligus (pilihan pemilik: "binaan yang dia pilih atau seluruh binaannya").
        // Tanda tangan Pemimpin Meeting (pengawas) hanya SATU per pertemuan → disimpan di baris ini.
        $pdo->exec('CREATE TABLE IF NOT EXISTS meeting (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pengawas_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            pengawas_nama TEXT,
            judul TEXT NOT NULL,
            tanggal TEXT NOT NULL,
            ttd_url TEXT,
            ttd_lebar INTEGER NOT NULL DEFAULT 0,
            ttd_tinggi INTEGER NOT NULL DEFAULT 0,
            ttd_pada TEXT,
            dibuat TEXT,
            diubah TEXT
        )');
        // Binaan yang daftar hadirnya DIAKTIFKAN untuk satu pertemuan (UNIQUE: satu baris per
        // pertemuan × sekolah). Menutup centang = baris dihapus, tetapi tanda tangan yang sudah
        // masuk tetap tersimpan di `meeting_hadir` (riwayat tidak hilang).
        $pdo->exec('CREATE TABLE IF NOT EXISTS meeting_sekolah (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            meeting_id INTEGER NOT NULL REFERENCES meeting(id) ON DELETE CASCADE,
            sekolah_id INTEGER NOT NULL REFERENCES sekolah(id) ON DELETE CASCADE,
            diaktifkan TEXT,
            UNIQUE (meeting_id, sekolah_id)
        )');
        // Tanda tangan PESERTA (kepala/operator/guru) per pertemuan × sekolah × akun.
        // Yang disimpan HANYA URL CDN hasil media proxy (bukan berkas gambar di server aplikasi).
        $pdo->exec('CREATE TABLE IF NOT EXISTS meeting_hadir (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            meeting_id INTEGER NOT NULL REFERENCES meeting(id) ON DELETE CASCADE,
            sekolah_id INTEGER NOT NULL REFERENCES sekolah(id) ON DELETE CASCADE,
            user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            nama TEXT,
            username TEXT,
            peran TEXT,
            url TEXT NOT NULL,
            lebar INTEGER NOT NULL DEFAULT 0,
            tinggi INTEGER NOT NULL DEFAULT 0,
            ip TEXT,
            dibuat TEXT,
            diubah TEXT,
            UNIQUE (meeting_id, sekolah_id, user_id)
        )');

        // MENU BENDAHARA (skema v32) — permintaan pemilik: "Pada menu Bendahara isinya buat seperti
        // Menu Supervisi yang isinya link aplikasi yang diisi oleh Super Admin." Bentuknya SAMA
        // dengan `hasil_supervisi`/`aplikasi_sagu` (GLOBAL, tanpa `sekolah_id`, diisi Super Admin).
        // Pembatasan aksesnya PER APLIKASI (lihat tabel `app_sekolah` di bawah).
        $pdo->exec('CREATE TABLE IF NOT EXISTS bendahara (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            link TEXT,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            -- 0 = akses sekolah BELUM diatur (berlaku untuk semua sekolah/madrasah);
            -- 1 = Super Admin sudah menyetel daftar sekolahnya (`app_sekolah`) — sehingga
            -- "tidak ada yang dicentang" berarti aplikasi itu tidak tampil untuk sekolah mana pun.
            akses_diatur INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT,
            diubah TEXT
        )');
        // MENU TUGAS TAMBAHAN "BENDAHARA MADRASAH" (skema v33) — permintaan pemilik: *"Isi menu dari
        // tugas tambahan 'Bendahara Madrasah' buat seperti Bendahara Bos"*, dengan pilihan pemilik:
        // **daftar aplikasinya TERPISAH** dari "Bendahara Bos" (tabel sendiri, penyetelan aksesnya
        // juga sendiri) dan pembatasan per aplikasinya tetap berlaku. Bentuk tabelnya SAMA dengan
        // `bendahara` — hanya namanya berbeda supaya datanya tidak tercampur.
        $pdo->exec('CREATE TABLE IF NOT EXISTS bendahara_madrasah (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            link TEXT,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            -- Lihat penjelasan `bendahara.akses_diatur`: 0 = belum diatur (semua sekolah boleh),
            -- 1 = sudah diatur (hanya sekolah yang dicentang di `app_sekolah`).
            akses_diatur INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT,
            diubah TEXT
        )');
        // TUGAS TAMBAHAN GURU (sejak skema v30): daftar tugas tambahan memakai **SATU daftar GLOBAL
        // yang sama dengan menu Tugas Tambahan** (`tugas_menu`) — permintaan pemilik: "Daftar Tugas
        // Tambahan yang ada di akun operator harus sama atau bersumber dari Daftar Tugas tambahan
        // yang diinput oleh Super Admin" (tanpa duplikat per sekolah). Tabel lama `tugas_tambahan`
        // (per sekolah) dihapus oleh migrasi v30.
        // ⚠️ Tabel relasinya (`guru_tugas`) sengaja dibuat SESUDAH `tugas_menu` di bawah supaya
        // FOREIGN KEY-nya menunjuk tabel yang sudah ada.

        // PENGAWAS SEKOLAH/MADRASAH (skema v14): akun pengawas bersifat LINTAS sekolah
        // (`users.sekolah_id` NULL, seperti Super Admin) namun TERBATAS — hanya sekolah binaan
        // yang ditetapkan Super Admin di tabel ini yang boleh dilihat rekapannya, dan hanya
        // untuk MELIHAT & MENCETAK (tanpa mengubah data apa pun).
        $pdo->exec('CREATE TABLE IF NOT EXISTS pengawas_sekolah (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            sekolah_id INTEGER NOT NULL REFERENCES sekolah(id) ON DELETE CASCADE,
            dibuat TEXT
        )');

        // Pengaturan tingkat APLIKASI (skema v13) — pasangan kunci/nilai yang tidak terikat sekolah.
        // Dipakai antara lain untuk menyimpan KUNCI RAHASIA generator kode aktivasi. Nilainya
        // SENGAJA disimpan di basis data (bukan berkas di `data/`) karena berkas di folder itu
        // dapat diunduh lewat web — berkas basis data sendiri tidak.
        $pdo->exec('CREATE TABLE IF NOT EXISTS app_pengaturan (
            kunci TEXT PRIMARY KEY,
            nilai TEXT,
            diubah TEXT
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS hasil_supervisi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            link TEXT,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            dibuat TEXT,
            diubah TEXT
        )');

        // UJIAN ONLINE (skema v42) — permintaan pemilik: "Dibawah menu pelanggaran siswa tambahkan
        // menu 'Ujian Online'. Isi dari menu ini buat seperti menu smart teacher yaitu memasukkan
        // link aplikasi ujian online lewat akun super admin" → bentuk tabelnya SAMA PERSIS dengan
        // `smart_teacher`/`hasil_supervisi` (GLOBAL, tanpa `sekolah_id`, hanya diisi Super Admin).
        // v43: kolom `baru_pada` — waktu aplikasi ditandai oleh Super Admin; selama
        // DAFTAR_APPS_BARU_HARI hari menu sidebar-nya mendapat lencana "Bonus" berkedip (permintaan
        // pemilik: "tambahkan tulisan new berkedip seperti pada aplikasi smart teacher").
        $pdo->exec('CREATE TABLE IF NOT EXISTS ujian_online (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            link TEXT,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            baru_pada TEXT,
            dibuat TEXT,
            diubah TEXT
        )');

        // PENGUMUMAN SAMBUTAN (skema v16) — isi POPUP "Selamat datang" yang muncul sekali setiap
        // kali pengguna masuk aplikasi. Daftarnya GLOBAL (tanpa sekolah_id) dan HANYA diisi
        // Super Admin lewat halaman `pengumuman.php`. Beberapa pengumuman boleh aktif sekaligus
        // dan masing-masing dapat diaktifkan/nonaktifkan tanpa dihapus.
        $pdo->exec('CREATE TABLE IF NOT EXISTS pengumuman (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            judul TEXT NOT NULL,
            isi TEXT NOT NULL,
            -- v46: SASARAN popup — pengguna (Pengawas/Kepala/Operator/Guru, BAWAAN supaya
            -- pengumuman LAMA tetap seperti semula) atau siswa (diisi TERSENDIRI oleh Super
            -- Admin, permintaan pemilik).
            sasaran TEXT NOT NULL DEFAULT "pengguna",
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            dibuat TEXT,
            diubah TEXT
        )');

        // MENU TUGAS TAMBAHAN (skema v18) — daftar menu GLOBAL (Kurikulum, Kesiswaan, dst) yang
        // ditampilkan di sidebar DI ATAS Data Master. Permintaan pemilik: "Menu-menu tersebut akan
        // muncul jika diaktifkan oleh Super Admin", "Nama menu juga bisa di edit oleh super admin",
        // dan "pengguna yang diaktifkan tugas tambahan (yang dipilihkan) yang bisa membuka menu
        // tersebut nantinya" → penugasannya per PENGGUNA di tabel `user_tugas`.
        $pdo->exec('CREATE TABLE IF NOT EXISTS tugas_menu (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kode TEXT NOT NULL UNIQUE,
            nama TEXT NOT NULL,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT,
            diubah TEXT
        )');

        // TUGAS TAMBAHAN GURU — relasi guru ↔ menu Tugas Tambahan (`tugas_menu`), lihat catatan di
        // atas. Tabelnya dibuat SETELAH `tugas_menu` supaya foreign key-nya sah sejak awal.
        $pdo->exec('CREATE TABLE IF NOT EXISTS guru_tugas (
            guru_id INTEGER NOT NULL REFERENCES guru(id) ON DELETE CASCADE,
            tugas_id INTEGER NOT NULL REFERENCES tugas_menu(id) ON DELETE CASCADE,
            PRIMARY KEY (guru_id, tugas_id)
        )');

        // PENGATURAN PENILAIAN PER (GURU, MATA PELAJARAN) — skema v31.
        // Permintaan pemilik: "Pengaturan kolom formatif, sumatif dan bobot asas tambahkan ke akun
        // guru untuk diseting sendiri. Begitu juga Pengaturan Bobot nilai dari A, B, C, D di seting
        // sendiri oleh guru." → guru tiap MATA PELAJARAN yang diampu boleh mengatur jumlah kolom
        // Formatif/Sumatif, bobot ASAS, dan ambang predikat A/B/C/D. Bila guru belum mengaturnya,
        // yang dipakai adalah pengaturan SEKOLAH (kolom `sekolah.jumlah_formatif`, dst.).
        $pdo->exec('CREATE TABLE IF NOT EXISTS nilai_pengaturan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            guru_id INTEGER NOT NULL REFERENCES guru(id) ON DELETE CASCADE,
            mapel_id INTEGER NOT NULL REFERENCES mapel(id) ON DELETE CASCADE,
            jumlah_formatif INTEGER NOT NULL DEFAULT 4,
            jumlah_sumatif INTEGER NOT NULL DEFAULT 4,
            bobot_asas REAL NOT NULL DEFAULT 50,
            batas_a REAL NOT NULL DEFAULT 90,
            batas_b REAL NOT NULL DEFAULT 80,
            batas_c REAL NOT NULL DEFAULT 70,
            diubah TEXT,
            UNIQUE (guru_id, mapel_id)
        )');

        // DATA BARANG SARANA & PRASARANA (skema v19) — dipakai menu Tugas Tambahan "Sarana &
        // Prasarana". PER SEKOLAH (kolom `sekolah_id`), karena daftar barang milik tiap lembaga
        // berbeda. Kondisi/sumber dana/jenis memakai pilihan tetap (lihat SARPRAS_* di config.php).
        $pdo->exec('CREATE TABLE IF NOT EXISTS sarpras_barang (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            nama TEXT NOT NULL,
            spesifikasi TEXT,
            lokasi TEXT,
            kondisi TEXT,
            jumlah INTEGER NOT NULL DEFAULT 1,
            sumber_dana TEXT,
            jenis TEXT,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT,
            diubah TEXT
        )');

        // BAGAN SUSUNAN ORGANISASI OSIM/OSIS (skema v22). Permintaan pemilik: susunan organisasi
        // juga ditampilkan sebagai BAGAN (seperti struktur organisasi pada umumnya) dan "kolom bagan
        // dan isinya bisa disesuaikan lagi".
        //   - `osim_pengurus.bagan` = nomor BARIS pada bagan (1 = puncak). Nilai 0 = otomatis dari
        //     jabatan (lihat osim_bagan_tingkat_baku()), jadi data lama langsung tampil rapi.
        //   - `sekolah.osim_bagan_judul` & `osim_bagan_periode` = judul & periode pada kepala bagan
        //     (boleh dikosongkan → memakai bawaan: judul tetap + tahun ajaran aktif).
        // Hanya db_add_column (tabel lama tidak dibangun ulang) sehingga data yang ada aman.
        if (db_has_table($pdo, 'osim_pengurus')) {
            db_add_column($pdo, 'osim_pengurus', 'bagan', 'INTEGER NOT NULL DEFAULT 0');
        }
        if (db_has_table($pdo, 'sekolah')) {
            db_add_column($pdo, 'sekolah', 'osim_bagan_judul', 'TEXT');
            db_add_column($pdo, 'sekolah', 'osim_bagan_periode', 'TEXT');
        }

        // MENU "PEMBINA PRAMUKA" MEMAKAI MODUL OSIM (skema v25). Permintaan pemilik: *"Pada menu
        // pembina pramuka isi menu buat seperti menu Pembina Osim hanya saja pembina osim ganti
        // dengan pembina pramuka"* — isi & fiturnya sama, jadi kedua menu memakai tabel yang sama
        // dan dipisahkan oleh kolom **`kode`** ('osim' = pembina OSIM/OSIS, 'pramuka' = pembina
        // Pramuka). Data lama otomatis bernilai 'osim' lewat DEFAULT sehingga tidak perlu dipindah.
        // Judul & periode bagan punya kolomnya sendiri per menu (sekolah.pramuka_bagan_*).
        if (db_has_table($pdo, 'osim_pengurus')) {
            db_add_column($pdo, 'osim_pengurus', 'kode', "TEXT NOT NULL DEFAULT 'osim'");
        }
        if (db_has_table($pdo, 'osim_program')) {
            db_add_column($pdo, 'osim_program', 'kode', "TEXT NOT NULL DEFAULT 'osim'");
        }
        if (db_has_table($pdo, 'sekolah')) {
            db_add_column($pdo, 'sekolah', 'pramuka_bagan_judul', 'TEXT');
            db_add_column($pdo, 'sekolah', 'pramuka_bagan_periode', 'TEXT');
            // MENU "PEMBINA PMR" (skema v38) — permintaan pemilik: *"Tugas Tambahan Pembina PMR isi
            // menu buat sama seperti Pembina OSIM/OSIS"*. Menu ini memakai modul yang SAMA
            // (`inc/osim.php`), datanya juga dipisah lewat kolom `kode` yang sudah ada ('pmr'),
            // jadi yang perlu ditambah hanya kolom judul & periode bagannya sendiri.
            db_add_column($pdo, 'sekolah', 'pmr_bagan_judul', 'TEXT');
            db_add_column($pdo, 'sekolah', 'pmr_bagan_periode', 'TEXT');
        }

        // PROGRAM KERJA KESISWAAN (skema v20) — dipakai menu Tugas Tambahan "Kesiswaan"
        // ("Laporan Program Kerja Kesiswaan"). PER SEKOLAH, karena program kerja tiap lembaga
        // berbeda. `waktu` menyimpan BULAN pelaksanaan (nama bulan Indonesia, dipisah koma) sesuai
        // centang Juli s.d. Juni — lihat KS_BULAN di config.php.
        $pdo->exec('CREATE TABLE IF NOT EXISTS kesiswaan_program (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            jenis TEXT,
            bentuk TEXT NOT NULL,
            tujuan TEXT,
            target TEXT,
            ta TEXT,
            waktu TEXT,
            sumber_dana TEXT,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT,
            diubah TEXT
        )');

        // SUSUNAN ORGANISASI OSIM/OSIS (skema v21) — menu Tugas Tambahan "Pembina OSIM/OSIS".
        // PER SEKOLAH. Satu baris = satu jabatan ATAU satu seksi ATAU satu anggota; `jabatan` diisi
        // nama jabatan/seksi (mis. "Ketua OSIM/OSIS" atau "Seksi Keagamaan"), `nama` nama pengurusnya.
        $pdo->exec('CREATE TABLE IF NOT EXISTS osim_pengurus (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            kode TEXT NOT NULL DEFAULT "osim",
            jabatan TEXT NOT NULL,
            nama TEXT,
            bagan INTEGER NOT NULL DEFAULT 0,
            urutan INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT,
            diubah TEXT
        )');

        // PROGRAM KERJA OSIM/OSIS (skema v21) — PER SEKOLAH. `waktu` = TANGGAL lengkap (Y-m-d),
        // `terlaksana` = 'Ya'/'Tidak' (boleh kosong bila belum ditentukan).
        $pdo->exec('CREATE TABLE IF NOT EXISTS osim_program (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            kode TEXT NOT NULL DEFAULT "osim",
            nama TEXT NOT NULL,
            waktu TEXT,
            target TEXT,
            ta TEXT,
            terlaksana TEXT,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT,
            diubah TEXT
        )');

        // LAPORAN PERPUSTAKAAN hasil IMPOR dari aplikasi e-PERPUS (skema v23) — dipakai menu Tugas
        // Tambahan "Ka. Perpustakaan". Permintaan pemilik: "Semua laporan ini di impor dari hasil
        // unduhan aplikasi e-perpus".
        //
        // Datanya adalah SALINAN APA ADANYA dari berkas laporan e-PERPUS (lima jenis: anggota, buku,
        // peminjaman, pengembalian, pengunjung), sehingga judul & jumlah kolomnya tidak tetap
        // tergantung versi berkas yang diunduh. Karena itu:
        //   - `perpus_laporan` menyimpan satu HIMPUNAN data per (sekolah, jenis) — impor ulang
        //     MENGGANTIKAN isi yang lama (UNIQUE), plus judul kolom pada `header` (JSON);
        //   - `perpus_baris` menyimpan tiap barisnya sebagai JSON array (`isi`) + `cari` (teks huruf
        //     kecil untuk pencarian). Menyimpannya sebagai JSON membuat semua kolom ikut tersimpan
        //     utuh tanpa perlu membangun tabel berkolom tetap.
        $pdo->exec('CREATE TABLE IF NOT EXISTS perpus_laporan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            jenis TEXT NOT NULL,
            berkas TEXT,
            sumber TEXT,
            header TEXT NOT NULL,
            jumlah_baris INTEGER NOT NULL DEFAULT 0,
            diimpor TEXT,
            oleh TEXT,
            UNIQUE (sekolah_id, jenis)
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS perpus_baris (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            laporan_id INTEGER NOT NULL REFERENCES perpus_laporan(id) ON DELETE CASCADE,
            urutan INTEGER NOT NULL DEFAULT 0,
            isi TEXT NOT NULL,
            cari TEXT
        )');

        // DAFTAR INVENTARIS BARANG & LAPORAN PROGRAM KEPALA LAB (skema v24) — dipakai menu Tugas
        // Tambahan "Kepala Lab IPA" (kode `kepala_lab`) dan "Kepala Lab Komputer" (kode
        // `tata_usaha`). Permintaan pemilik: "Pada Menu Kepala Lab IPA dan Lab Komputer menu isi
        // dengan: 1. Daftar Inventaris Barang (No, Nama Barang, Jumlah, Keadaan, Keterangan);
        // 2. Laporan Program (No, Program Kerja, Alokasi Waktu, Status Realisasi, Keterangan).
        // Pastikan ditambahkan fitur cetak dokumen."
        //
        // Kedua menu memakai struktur SAMA, jadi cukup dua tabel dengan kolom `kode` sebagai pemisah
        // data antar menu (lihat LAB_KODE_MENU di inc/config.php). Data PER SEKOLAH (`sekolah_id`).
        $pdo->exec('CREATE TABLE IF NOT EXISTS lab_barang (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            kode TEXT NOT NULL,
            nama TEXT NOT NULL,
            jumlah INTEGER NOT NULL DEFAULT 1,
            keadaan TEXT,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT,
            diubah TEXT
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS lab_program (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            kode TEXT NOT NULL,
            program TEXT NOT NULL,
            alokasi TEXT,
            status TEXT,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT,
            diubah TEXT
        )');

        // DOKUMEN KURIKULUM (skema v27) — permintaan pemilik: "Pada menu kurikulum isi menu dengan
        // fitur untuk mengirim dokumen kepada guru… Dokumen yang dikirim akan masuk di akun guru
        // pada sekolah tersebut." Satu baris = satu dokumen yang dikirim ke SEMUA guru sekolah itu
        // (keputusan pemilik), berisi judul + catatan + URL berkas pada media proxy platform.
        // Data PER SEKOLAH (`sekolah_id`) dan setiap query dibatasi `sekolah_id()` (anti-IDOR).
        $pdo->exec('CREATE TABLE IF NOT EXISTS dokumen (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            judul TEXT NOT NULL,
            catatan TEXT,
            url TEXT NOT NULL,
            nama_berkas TEXT,
            jenis TEXT,
            ukuran INTEGER NOT NULL DEFAULT 0,
            pengirim TEXT,
            pengirim_id INTEGER,
            dibuat TEXT
        )');

        // MENU WALI KELAS (skema v29). Permintaan pemilik: "Pada Menu wali kelas diisi dengan:
        //   1. Struktur Organisasi Kelas (baris bagan Tingkat: Wali kelas, Ketua Kelas, Wakil Ketua
        //      Kelas, Sekretaris, Bendahara, Seksi-seksi, Anggota)
        //   2. Program Kerja Wali Kelas (Tabelnya disamakan saja dengan Tabel Program kerja Pembina osim)
        //   3. Absensi Kelas (Input Harian sama dengan Absensi yang ada di guru tapi hanya kelas yang
        //      jadi perwaliannya)
        //   4. Jadwal Piket Kelas (Tabel: Hari dan dibawah tabel ada nama-nama siswa yang piket harian)
        //   Masing-masing tambahkan menu cetak dokumen."
        // Keputusan pemilik: absensi = PENCATATAN HARIAN tersendiri (tanggal + H/S/I/A per siswa,
        // tanpa mapel); yang boleh membuka = guru yang menjadi wali kelas itu (kelasnya diatur
        // operator lewat Data Kelas/Data Guru) — pengelola sekolah boleh memilih kelas;
        // piket = siswa DIPILIH dari daftar siswa kelas itu; struktur = kembar dengan OSIM
        // (jabatan baku + nama + baris bagan + impor/ekspor + cetak bagan).
        //
        // Semua tabel PER KELAS (`kelas_id`) dan per sekolah (`sekolah_id`); setiap query dibatasi
        // keduanya (anti-IDOR).
        $pdo->exec('CREATE TABLE IF NOT EXISTS wali_struktur (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            kelas_id INTEGER NOT NULL REFERENCES kelas(id) ON DELETE CASCADE,
            jabatan TEXT NOT NULL,
            nama TEXT,
            bagan INTEGER NOT NULL DEFAULT 0,
            urutan INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT,
            diubah TEXT
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS wali_program (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            kelas_id INTEGER NOT NULL REFERENCES kelas(id) ON DELETE CASCADE,
            nama TEXT NOT NULL,
            waktu TEXT,
            target TEXT,
            ta TEXT,
            terlaksana TEXT,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT,
            diubah TEXT
        )');
        // Absensi harian kelas: SATU baris per (kelas, tanggal) — tanpa mapel/pertemuan.
        $pdo->exec('CREATE TABLE IF NOT EXISTS wali_absensi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            kelas_id INTEGER NOT NULL REFERENCES kelas(id) ON DELETE CASCADE,
            tanggal TEXT NOT NULL,
            catatan TEXT,
            guru_id INTEGER REFERENCES guru(id) ON DELETE SET NULL,
            ta_id INTEGER,
            semester_id INTEGER,
            dibuat TEXT,
            dibuat_oleh INTEGER,
            UNIQUE (kelas_id, tanggal)
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS wali_absensi_detail (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            absensi_id INTEGER NOT NULL REFERENCES wali_absensi(id) ON DELETE CASCADE,
            siswa_id INTEGER NOT NULL REFERENCES siswa(id) ON DELETE CASCADE,
            status TEXT NOT NULL DEFAULT "H",
            ket TEXT,
            UNIQUE (absensi_id, siswa_id)
        )');
        // Jadwal piket: satu baris = satu siswa pada satu HARI untuk satu kelas.
        $pdo->exec('CREATE TABLE IF NOT EXISTS wali_piket (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            kelas_id INTEGER NOT NULL REFERENCES kelas(id) ON DELETE CASCADE,
            hari TEXT NOT NULL,
            siswa_id INTEGER NOT NULL REFERENCES siswa(id) ON DELETE CASCADE,
            urutan INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT,
            UNIQUE (kelas_id, hari, siswa_id)
        )');

        // ---------------------------------------------------------------- MIGRASI v44
        // HALAMAN DEPAN (LANDING PAGE). Permintaan pemilik: "Buat halaman depan aplikasi menjadi
        // tampilan web landing page yang menampilkan beranda, Slider (Judul, gambar, keterangan),
        // fitur aplikasi, keunggulan, Total Pengguna (Pengawas, Kepala Sekolah, Madrasah, Guru),
        // testimoni, kontak. Panel login tetap dipertahankan. Untuk mengisi halaman landing page
        // di isi lewat akun super admin."
        // Semua tabel bersifat GLOBAL (halaman depan dipakai bersama semua sekolah/madrasah) —
        // TIDAK ada kolom `sekolah_id`. Hanya CREATE TABLE/CREATE INDEX: tidak ada tabel lama yang
        // dibangun ulang sehingga data sekolah/guru/siswa/nilai tidak tersentuh.
        $pdo->exec('CREATE TABLE IF NOT EXISTS landing_slider (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            judul TEXT NOT NULL DEFAULT "",
            keterangan TEXT NOT NULL DEFAULT "",
            foto TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            dibuat TEXT
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS landing_fitur (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ikon TEXT NOT NULL DEFAULT "check",
            judul TEXT NOT NULL DEFAULT "",
            teks TEXT NOT NULL DEFAULT "",
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            dibuat TEXT
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS landing_unggulan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ikon TEXT NOT NULL DEFAULT "check",
            judul TEXT NOT NULL DEFAULT "",
            teks TEXT NOT NULL DEFAULT "",
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            dibuat TEXT
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS landing_testimoni (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL DEFAULT "",
            jabatan TEXT NOT NULL DEFAULT "",
            isi TEXT NOT NULL DEFAULT "",
            foto TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            dibuat TEXT
        )');

        // Pesan dari formulir kontak halaman depan → masuk ke akun Super Admin (tab "Pesan Masuk").
        $pdo->exec('CREATE TABLE IF NOT EXISTS landing_pesan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL DEFAULT "",
            kontak TEXT NOT NULL DEFAULT "",
            pesan TEXT NOT NULL DEFAULT "",
            ip TEXT,
            dibaca INTEGER NOT NULL DEFAULT 0,
            dibuat TEXT
        )');

        // PEMBATASAN MENU DAFTAR APLIKASI PER SEKOLAH (skema v28). Permintaan pemilik:
        // "Khusus menu aplikasi sagu hanya muncul di MA. Sirojuth Tholibiin Mootilango dan
        // MTsS. Sirojuth Tholibiin Sidomukti Mootilango. Selain itu tidak muncul, bisa juga
        // diseting melalui akun super admin."
        // Satu baris = menu `<menu>` (kunci konfigurasi di inc/daftar_apps.php) boleh dilihat
        // sekolah `<sekolah_id>`. Menu yang TIDAK dibatasi tidak memakai tabel ini sama sekali
        // (tetap global seperti Smart Teacher & Hasil Supervisi).
        $pdo->exec('CREATE TABLE IF NOT EXISTS menu_sekolah (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            menu TEXT NOT NULL,
            sekolah_id INTEGER NOT NULL REFERENCES sekolah(id) ON DELETE CASCADE,
            dibuat TEXT,
            UNIQUE (menu, sekolah_id)
        )');

        // PEMBATASAN AKSES PER APLIKASI (skema v32) — permintaan pemilik: "pada pengisian link
        // aplikasi tambahkan fitur link aplikasi bisa dibuka oleh madrasah yang diinginkan oleh
        // super admin" (menu Bendahara). Berbeda dari `menu_sekolah` yang membatasi MENUNYA,
        // tabel ini membatasi tiap APLIKASI: satu baris = satu aplikasi boleh dibuka oleh satu
        // sekolah/madrasah. **Aplikasi tanpa satu pun baris di sini dianggap boleh dibuka semua
        // sekolah** (perilaku bawaan yang sama dengan menu daftar aplikasi lain), sehingga
        // pembatasan hanya berlaku pada aplikasi yang memang disetel.
        $pdo->exec('CREATE TABLE IF NOT EXISTS app_sekolah (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            menu TEXT NOT NULL,
            app_id INTEGER NOT NULL,
            sekolah_id INTEGER NOT NULL REFERENCES sekolah(id) ON DELETE CASCADE,
            dibuat TEXT,
            UNIQUE (menu, app_id, sekolah_id)
        )');

        // Penugasan menu Tugas Tambahan kepada pengguna (boleh semua peran: kepala, operator,
        // guru, pengawas). Hanya pengguna yang ditugaskan (dan menunya aktif) yang dapat membuka
        // halaman menunya; Super Admin selalu dapat membukanya untuk memeriksa.
        $pdo->exec('CREATE TABLE IF NOT EXISTS user_tugas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            tugas_id INTEGER NOT NULL REFERENCES tugas_menu(id) ON DELETE CASCADE,
            dibuat TEXT,
            UNIQUE (user_id, tugas_id)
        )');

        // ---------------------------------------------------------------- kenaikan kelas & kelulusan (skema v6)
        // Riwayat proses naik kelas / kelulusan siswa. Disimpan sebagai riwayat agar perubahan
        // kelas seorang siswa dapat ditelusuri (dan dibatalkan bila salah proses).
        $pdo->exec('CREATE TABLE IF NOT EXISTS siswa_kenaikan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            siswa_id INTEGER REFERENCES siswa(id) ON DELETE CASCADE,
            dari_kelas_id INTEGER REFERENCES kelas(id) ON DELETE SET NULL,
            ke_kelas_id INTEGER REFERENCES kelas(id) ON DELETE SET NULL,
            jenis TEXT NOT NULL DEFAULT "naik",
            ta_id INTEGER,
            ta_nama TEXT,
            catatan TEXT,
            diproses_oleh INTEGER,
            diproses_nama TEXT,
            dibatalkan TEXT,
            dibuat TEXT NOT NULL
        )');

        // ---------------------------------------------------------------- log aktivitas & sesi (skema v5)
        // Riwayat masuk/keluar/gagal masuk + daftar pengguna yang sedang online, dipakai
        // halaman Log Aktivitas (khusus Super Admin). Nama akun & sekolah disimpan sebagai
        // SNAPSHOT supaya riwayat tetap terbaca walau akun/sekolahnya kelak dihapus
        // (relasinya SET NULL, bukan CASCADE).
        $pdo->exec('CREATE TABLE IF NOT EXISTS log_aktivitas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            nama TEXT,
            username TEXT,
            role TEXT,
            sekolah_id INTEGER REFERENCES sekolah(id) ON DELETE SET NULL,
            sekolah TEXT,
            aktivitas TEXT NOT NULL,
            keterangan TEXT,
            ip TEXT,
            peramban TEXT,
            waktu TEXT NOT NULL
        )');

        // Sesi yang masih berjalan → dasar daftar "sedang online". `id` adalah HASH id sesi;
        // id sesi yang sebenarnya tidak pernah disimpan.
        $pdo->exec('CREATE TABLE IF NOT EXISTS sesi_aktif (
            id TEXT PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            ip TEXT,
            peramban TEXT,
            mulai TEXT NOT NULL,
            terakhir TEXT NOT NULL
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS kasus (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1,
            tanggal TEXT NOT NULL,
            siswa_id INTEGER REFERENCES siswa(id) ON DELETE CASCADE,
            kelas_id INTEGER REFERENCES kelas(id) ON DELETE CASCADE,
            jenis TEXT NOT NULL DEFAULT "lainnya",
            judul TEXT,
            deskripsi TEXT,
            tindakan TEXT,
            status TEXT NOT NULL DEFAULT "baru",
            guru_id INTEGER REFERENCES guru(id) ON DELETE SET NULL,
            -- v9: mata pelajaran tempat kejadian (dipakai penyaring Pelanggaran Siswa).
            -- (Dahulu hanya ditambahkan lewat db_add_column; sekarang ikut ditulis di sini supaya
            -- instalasi baru langsung lengkap.)
            mapel_id INTEGER REFERENCES mapel(id) ON DELETE SET NULL,
            -- v51: TINDAK LANJUT oleh guru Tugas Tambahan "Kesiswaan" atas catatan pelanggaran
            -- yang dibuat guru mata pelajaran mana pun (lihat inc/kesiswaan.php). Jenis kosong/NULL
            -- berarti BELUM ditindaklanjuti. Nama penindak disimpan sebagai SNAPSHOT.
            tindak_lanjut TEXT,
            tindak_lanjut_jenis TEXT,
            tindak_lanjut_oleh TEXT,
            tindak_lanjut_pada TEXT,
            dibuat TEXT,
            dibuat_oleh INTEGER
        )');

        // ------------------------------------------------- migrasi skema lama (v1/v2 -> v3)
        // Kolom sekolah_id ditambahkan ke tabel yang sudah ada (data lama dianggap milik
        // sekolah/madrasah #1), lalu tabel dengan batasan UNIQUE "satu sekolah" dibangun ulang
        // agar dua sekolah boleh memakai nama kelas/tahun ajaran yang sama.
        foreach ([
            'guru' => 'INTEGER NOT NULL DEFAULT 1',
            'mapel' => 'INTEGER NOT NULL DEFAULT 1',
            'kelas' => 'INTEGER NOT NULL DEFAULT 1',
            'siswa' => 'INTEGER NOT NULL DEFAULT 1',
            'tahun_ajaran' => 'INTEGER NOT NULL DEFAULT 1',
            'absensi' => 'INTEGER NOT NULL DEFAULT 1',
            'nilai' => 'INTEGER NOT NULL DEFAULT 1',
            'jurnal' => 'INTEGER NOT NULL DEFAULT 1',
            'kasus' => 'INTEGER NOT NULL DEFAULT 1',
            'users' => 'INTEGER',
        ] as $tabel => $def) {
            if (db_has_table($pdo, $tabel)) {
                db_add_column($pdo, $tabel, 'sekolah_id', $def);
            }
        }

        // Foto profil pengguna (skema v4): URL CDN hasil media proxy platform.
        if (db_has_table($pdo, 'users')) {
            db_add_column($pdo, 'users', 'foto', 'TEXT');
        }

        // Mata pelajaran pada Pelanggaran Siswa (skema v9): kolom ditambahkan lewat
        // db_add_column (tanpa membangun ulang tabel) supaya data kasus yang sudah ada aman.
        // Dipakai untuk MEMFILTER pelanggaran per mata pelajaran (permintaan pemilik).
        if (db_has_table($pdo, 'kasus')) {
            db_add_column($pdo, 'kasus', 'mapel_id', 'INTEGER REFERENCES mapel(id) ON DELETE SET NULL');
        }

        // PAS FOTO SISWA 3x4 (skema v10): URL CDN hasil media proxy platform, diunggah dari
        // form Tambah/Ubah Data Siswa dan dipakai pada KARTU PELAJAR. Ditambahkan lewat
        // db_add_column (tanpa membangun ulang tabel) supaya data siswa yang ada aman —
        // siswa lama bernilai NULL dan kartunya memakai kotak foto kosong.
        if (db_has_table($pdo, 'siswa')) {
            db_add_column($pdo, 'siswa', 'foto', 'TEXT');
        }

        // DATA SISWA: tempat lahir, nama ayah, nama ibu (skema v17). Diminta pemilik agar
        // IMPOR DATA SISWA bisa memuat: NIS, NISN, Nama Lengkap, Jenis Kelamin, Tempat Lahir,
        // Tanggal Lahir, Nomor Telepon (HP), Nama Ayah, Nama Ibu, dan Wali. Sebelumnya hanya ada
        // satu kolom `nama_wali` sehingga ayah/ibu/tempat lahir tidak punya tempat penyimpanan.
        // Hanya db_add_column (tanpa membangun ulang tabel) → data siswa lama aman (NULL).
        if (db_has_table($pdo, 'siswa')) {
            db_add_column($pdo, 'siswa', 'tempat_lahir', 'TEXT');
            db_add_column($pdo, 'siswa', 'nama_ayah', 'TEXT');
            db_add_column($pdo, 'siswa', 'nama_ibu', 'TEXT');
        }

        // Kenaikan kelas & kelulusan (skema v6): status siswa + waktu perubahan statusnya.
        if (db_has_table($pdo, 'siswa')) {
            db_add_column($pdo, 'siswa', 'status', 'TEXT NOT NULL DEFAULT "aktif"');
            db_add_column($pdo, 'siswa', 'tanggal_status', 'TEXT');
        }

        // Jurnal Harian: kolom kehadiran (S,I,A,B,T) + keterangan (skema v2).
        if (db_has_table($pdo, 'jurnal')) {
            db_add_column($pdo, 'jurnal', 'sakit', 'INTEGER NOT NULL DEFAULT 0');
            db_add_column($pdo, 'jurnal', 'izin', 'INTEGER NOT NULL DEFAULT 0');
            db_add_column($pdo, 'jurnal', 'alpa', 'INTEGER NOT NULL DEFAULT 0');
            db_add_column($pdo, 'jurnal', 'bolos', 'INTEGER NOT NULL DEFAULT 0');
            db_add_column($pdo, 'jurnal', 'terlambat', 'INTEGER NOT NULL DEFAULT 0');
            db_add_column($pdo, 'jurnal', 'keterangan', 'TEXT');
            // v36: Jurnal Harian Guru — Uraian Kegiatan + Tempat kegiatan (Kelas/Tempat).
            // ALTER TABLE dengan DEFAULT konstanta mengisi baris LAMA dengan 'Mengajar' sehingga
            // catatan yang sudah ada tetap terhitung sebagai tatap muka pada rekap pertemuan.
            db_add_column($pdo, 'jurnal', 'uraian', "TEXT NOT NULL DEFAULT 'Mengajar'");
            db_add_column($pdo, 'jurnal', 'tempat', 'TEXT');
            // Rapikan nilai yang kosong/NULL (idempoten) supaya SEMUA catatan lama terbaca
            // sebagai kegiatan mengajar — bila tidak, catatan pemilik hilang dari rekap.
            $pdo->exec("UPDATE jurnal SET uraian = 'Mengajar' WHERE uraian IS NULL OR TRIM(uraian) = ''");
        }

        // AKTIVASI sekolah/madrasah (skema v13): Super Admin menyetujui pendaftaran dengan pilihan
        // "aktif PERMANEN" atau "MASA UJI COBA 30/60/90 hari". Bila masa uji coba habis, pengelola
        // sekolah tidak dapat memakai menu apa pun sampai memasukkan KODE AKTIVASI lembaga
        // (kode unik berdasarkan nama sekolah — lihat kode_aktivasi_sekolah() di inc/helpers.php).
        // Ditambahkan lewat db_add_column (tanpa membangun ulang tabel). Sekolah yang SUDAH ADA
        // diberi status "permanen" supaya tidak ada sekolah yang terlanjur terkunci.
        if (db_has_table($pdo, 'sekolah')) {
            db_add_column($pdo, 'sekolah', 'aktivasi', 'TEXT');
            db_add_column($pdo, 'sekolah', 'uji_coba_mulai', 'TEXT');
            db_add_column($pdo, 'sekolah', 'uji_coba_selesai', 'TEXT');
            db_add_column($pdo, 'sekolah', 'aktivasi_pada', 'TEXT');
            $pdo->exec('UPDATE sekolah SET aktivasi = "permanen"
                        WHERE aktivasi IS NULL OR TRIM(aktivasi) = ""');
        }

        // MASA AKTIF 365 HARI (skema v35) — permintaan pemilik: *"Pada saat pengguna aktifasi
        // melakukan aktifasi (memasukkan kode aktifasi dari admin) Pastikan aplikasi aktif dengan
        // terbuka semua fitur dengan masa aktif 1 tahun (365 hari). Buat hitungan mundur. Dan jika
        // masa aktif habis bisa menghubungi kembali HD Admin untuk meminta kode aktifas yang baru."*
        // Pilihan pemilik untuk sekolah yang SUDAH aktif: *"Tetap aktif, dan diberi masa aktif 365
        // hari mulai sekarang (hitung mundur berjalan)."*
        // Karena itu sekolah lama yang berstatus `permanen` dipindahkan ke mode `aktif` dengan
        // `aktif_sampai` = 365 hari dari sekarang — SEKALI saja (dijaga oleh `aktif_sampai` yang
        // masih kosong), sehingga aktivasi/perpanjangan berikutnya tidak pernah tertimpa.
        // Sekolah dengan mode `permanen` yang MEMANG diminta Super Admin (mis. disetel setelah
        // migrasi ini) tetap tanpa batas waktu.
        if (db_has_table($pdo, 'sekolah')) {
            db_add_column($pdo, 'sekolah', 'aktif_sampai', 'TEXT');
            db_add_column($pdo, 'sekolah', 'aktivasi_ke', 'INTEGER NOT NULL DEFAULT 1');
        }

        // v37: IDENTITAS LEMBAGA — NSM + alamat terpisah per wilayah (permintaan pemilik:
        // "Pada menu identitas dan pengaturan pada bagian identitas madrasah di rubah menjadi:
        // Nama Madrasah/Sekolah, NPSN, NSM, Alamat (Nama Jalan, Dusun), Desa/kelurahan, Kecamatan,
        // Kabupaten, Provinsi, email, situs/laman, Nama Kepala Madrasah/Sekolah, NIP ...").
        // Hanya MENAMBAH kolom — tidak ada tabel yang dibangun ulang; sekolah lama bernilai NULL.
        if (db_has_table($pdo, 'sekolah')) {
            foreach (['nsm' => 'TEXT', 'desa' => 'TEXT', 'kecamatan' => 'TEXT',
                      'kabupaten' => 'TEXT', 'provinsi' => 'TEXT'] as $kolom => $def) {
                db_add_column($pdo, 'sekolah', $kolom, $def);
            }
            // Perbaikan nilai `aktivasi` yang RUSAK/ tidak dikenal (mis. angka 1 karena SQL bertulis
            // `aktivasi = "aktif"` dibaca SQLite sebagai nama kolom `aktif`): sekolah yang sudah punya
            // `aktif_sampai` dinormalkan kembali menjadi teks 'aktif' supaya berstatus BERJANGKA.
            // Idempoten: baris yang sudah benar tidak tersentuh.
            $pdo->exec("UPDATE sekolah SET aktivasi = 'aktif'
                        WHERE aktivasi NOT IN ('permanen', 'aktif', 'uji_coba')
                          AND aktif_sampai IS NOT NULL AND TRIM(aktif_sampai) <> ''");
            $sampai = date('Y-m-d', strtotime('+' . (int) AKTIVASI_HARI_AKTIF . ' days')) . ' 23:59:59';
            // ⚠️ NILAI TEKS DIKIRIM SEBAGAI PARAMETER (bukan ditulis dalam kutip ganda):
            // menulis `SET aktivasi = "aktif"` membuat SQLite membacanya sebagai NAMA KOLOM `aktif`
            // (kolom itu memang ada di tabel `sekolah`) sehingga yang tersimpan ANGKA 1, bukan teks
            // 'aktif' — jebakan yang sudah pernah menggigit di fitur ini. Bound parameter selalu aman.
            $pdo->prepare('UPDATE sekolah SET aktivasi = ?, aktif_sampai = ?, aktivasi_ke = 1
                           WHERE aktivasi = ?
                             AND (aktif_sampai IS NULL OR TRIM(aktif_sampai) = "")')
                ->execute(['aktif', $sampai, 'permanen']);
        }

        // v39: IDENTITAS PENGAWAS BINA — nama & NIP (permintaan pemilik: "Pada halaman pendaftaran
        // tambahkan inputan Nama Pengawas Bina dan NIP. Posisi dibawah nama kepala sekolah").
        // Hanya MENAMBAH kolom lewat db_add_column (tidak ada tabel yang dibangun ulang); sekolah
        // lama bernilai NULL — tidak ada nilai yang ditebak — dan dapat diisi lewat
        // Identitas & Pengaturan / Kelola Sekolah.
        if (db_has_table($pdo, 'sekolah')) {
            db_add_column($pdo, 'sekolah', 'pengawas_nama', 'TEXT');
            db_add_column($pdo, 'sekolah', 'pengawas_nip', 'TEXT');
        }

        // v40: AKUN PENGAWAS OTOMATIS DARI PENDAFTARAN (permintaan pemilik: "Nama Pengawas yang
        // diinput dari halaman pendaftaran, otomatis masuk di akun super admin di daftar akun
        // pengawas tapi status masih non aktif untuk ditinjau oleh super admin").
        // Kolom `users.ditinjau` menandai akun yang masih MENUNGGU TINJAUAN (0); seluruh akun yang
        // sudah ada diberi nilai bawaan 1 sehingga tidak ada akun lama yang mendadak "menunggu".
        if (db_has_table($pdo, 'users')) {
            db_add_column($pdo, 'users', 'ditinjau', 'INTEGER NOT NULL DEFAULT 1');
        }

        // v43: LENCANA BERKEDIP pada menu "Ujian Online" (permintaan pemilik: "Pada menu ujian
        // online tambahkan tulisan new berkedip seperti pada aplikasi smart teacher"; teksnya kini
        // "Bonus", dan menu Smart Teacher menyusul lewat migrasi v53).
        // Kolom `baru_pada` = waktu aplikasi ditandai baru; aplikasi LAMA bernilai NULL (bukan baru —
        // tidak ada nilai yang ditebak) sehingga tidak ada menu yang mendadak berlabel "Bonus".
        if (db_has_table($pdo, 'ujian_online')) {
            db_add_column($pdo, 'ujian_online', 'baru_pada', 'TEXT');
        }

        // MENU BENDAHARA (skema v32): kolom `akses_diatur` — penanda bahwa daftar sekolah/madrasah
        // yang boleh membuka tiap aplikasi SUDAH diatur Super Admin (`daftar_apps_app_boleh()`).
        // Ditambahkan lewat `db_add_column` (bukan hanya pada `CREATE TABLE`) karena tabel
        // `bendahara` sudah lebih dulu dibuat tanpa kolom ini — peristiwa NYATA pada basis data
        // pemilik: penanda skema berbunyi 31/32 sementara kolomnya belum ada, sehingga menyimpan
        // penyetelan akses aplikasi akan gagal dengan "no such column: akses_diatur".
        if (db_has_table($pdo, 'bendahara')) {
            db_add_column($pdo, 'bendahara', 'akses_diatur', 'INTEGER NOT NULL DEFAULT 0');
        }
        // Tabel `bendahara_madrasah` (v33) dibuat lengkap oleh CREATE TABLE, tetapi `db_add_column`
        // tetap dipasang sebagai jaring pengaman (pelajaran dari kejadian `bendahara.akses_diatur`
        // yang hilang pada basis data pemilik — lihat cek_live_bendahara_kolom.sh).
        if (db_has_table($pdo, 'bendahara_madrasah')) {
            db_add_column($pdo, 'bendahara_madrasah', 'akses_diatur', 'INTEGER NOT NULL DEFAULT 0');
        }

        // Tabel `pengaturan` (skema <= v2) = penyimpanan pengaturan SATU sekolah.
        // Isinya dipindahkan sekali ke baris sekolah #1; tabelnya dibiarkan ada sebagai arsip.
        $legacy = [];
        if (db_has_table($pdo, 'pengaturan')) {
            foreach ($pdo->query('SELECT kunci, nilai FROM pengaturan')->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $legacy[(string) $r['kunci']] = (string) ($r['nilai'] ?? '');
            }
        }

        if ((int) db_val($pdo, 'SELECT COUNT(*) FROM sekolah') === 0) {
            $now = date('Y-m-d H:i:s');
            $ambil = static function (string $kunci, string $default = '') use ($legacy): string {
                $v = trim((string) ($legacy[$kunci] ?? ''));
                return $v === '' ? $default : $v;
            };
            $nama = $ambil('nama_sekolah');
            if ($nama === '') {
                $nama = (($ambil('jenis_sekolah', 'umum')) === 'madrasah' ? 'Madrasah' : 'Sekolah') . ' (nama belum diisi)';
            }
            $pdo->prepare('INSERT INTO sekolah (nama, npsn, alamat, telepon, email, website, kepala_sekolah,
                    nip_kepala_sekolah, jenis, logo, ta_aktif_id, semester_aktif_id, jumlah_formatif, jumlah_sumatif,
                    bobot_asas, batas_a, batas_b, batas_c, verifikasi, aktif, didaftar, ditinjau, dibuat)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"disetujui",1,?,?,?)')
                ->execute([
                    $nama,
                    $ambil('npsn'),
                    $ambil('alamat_sekolah'),
                    $ambil('telepon_sekolah'),
                    $ambil('email_sekolah'),
                    $ambil('website'),
                    $ambil('kepala_sekolah'),
                    $ambil('nip_kepala_sekolah'),
                    $ambil('jenis_sekolah', 'umum') === 'madrasah' ? 'madrasah' : 'umum',
                    $ambil('logo_sekolah'),
                    (int) $ambil('ta_aktif_id', '0'),
                    (int) $ambil('semester_aktif_id', '0'),
                    max(1, min(12, (int) $ambil('jumlah_formatif', '4'))),
                    max(1, min(12, (int) $ambil('jumlah_sumatif', '4'))),
                    (float) $ambil('bobot_asas', '50'),
                    (float) $ambil('batas_a', '90'),
                    (float) $ambil('batas_b', '80'),
                    (float) $ambil('batas_c', '70'),
                    $now, $now, $now,
                ]);
            // Isi kolom sekolah_id data lama dengan sekolah pertama.
            foreach (['guru', 'mapel', 'kelas', 'siswa', 'tahun_ajaran', 'absensi', 'nilai', 'jurnal', 'kasus'] as $t) {
                if (db_has_table($pdo, $t)) {
                    $pdo->exec('UPDATE ' . $t . ' SET sekolah_id = 1 WHERE sekolah_id IS NULL OR sekolah_id = 0');
                }
            }
            if (db_has_table($pdo, 'users')) {
                // Super Admin bersifat lintas sekolah (sekolah_id NULL); akun lain milik sekolah #1.
                $pdo->exec('UPDATE users SET sekolah_id = 1 WHERE role <> "super" AND (sekolah_id IS NULL OR sekolah_id = 0)');
                $pdo->exec('UPDATE users SET sekolah_id = NULL WHERE role = "super"');
            }
        }

        // Bangun ulang kelas & tahun_ajaran bila masih memakai UNIQUE se-aplikasi.
        foreach ([
            'kelas' => [
                'baru' => 'CREATE TABLE kelas_baru (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sekolah_id INTEGER NOT NULL DEFAULT 1,
                    nama TEXT NOT NULL,
                    tingkat TEXT,
                    wali_id INTEGER REFERENCES guru(id) ON DELETE SET NULL,
                    keterangan TEXT,
                    urutan INTEGER NOT NULL DEFAULT 0,
                    aktif INTEGER NOT NULL DEFAULT 1,
                    UNIQUE (sekolah_id, nama)
                )',
                'kolom' => 'id, sekolah_id, nama, tingkat, wali_id, keterangan, urutan, aktif',
            ],
            'tahun_ajaran' => [
                'baru' => 'CREATE TABLE tahun_ajaran_baru (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sekolah_id INTEGER NOT NULL DEFAULT 1,
                    nama TEXT NOT NULL,
                    tanggal_mulai TEXT,
                    tanggal_selesai TEXT,
                    keterangan TEXT,
                    aktif INTEGER NOT NULL DEFAULT 1,
                    UNIQUE (sekolah_id, nama)
                )',
                'kolom' => 'id, sekolah_id, nama, tanggal_mulai, tanggal_selesai, keterangan, aktif',
            ],
        ] as $tabel => $info) {
            $sql = db_table_sql($pdo, $tabel);
            $perluRebuild = $sql !== '' && stripos($sql, 'UNIQUE') !== false && stripos($sql, '(sekolah_id') === false;
            if ($perluRebuild) {
                // Tidak memakai ALTER TABLE ... RENAME pada tabel LAMA: RENAME akan menulis ulang
                // definisi FOREIGN KEY tabel lain yang menunjuk tabel tersebut (pernah menyebabkan
                // ratusan pelanggaran FK). Yang di-rename adalah tabel BARU (tanpa referensi).
                $pdo->exec('DROP TABLE IF EXISTS ' . $tabel . '_baru');
                $pdo->exec($info['baru']);
                $pdo->exec('INSERT INTO ' . $tabel . '_baru (' . $info['kolom'] . ') SELECT ' . $info['kolom'] . ' FROM ' . $tabel);
                $pdo->exec('DROP TABLE ' . $tabel);
                $pdo->exec('ALTER TABLE ' . $tabel . '_baru RENAME TO ' . $tabel);
            }
        }

        // ---------------------------------------------------------------- obrolan (chat, v41)
        // SATU ruang bersama untuk SELURUH pengguna aplikasi (lintas sekolah/madrasah) — permintaan
        // pemilik: "Tambahkan fitur chat agar sesama pengguna aplikasi yang sedang online bisa
        // berkomunikasi". Karena lintas sekolah, tabelnya SENGAJA TANPA `sekolah_id` yang mengikat;
        // nama & asal sekolah pengirim disimpan sebagai SNAPSHOT (pola yang sama dengan log_aktivitas)
        // supaya riwayat tetap terbaca walau akun/sekolahnya kelak dihapus.
        $pdo->exec('CREATE TABLE IF NOT EXISTS chat_pesan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            nama TEXT,
            username TEXT,
            role TEXT,
            sekolah_id INTEGER,
            sekolah TEXT,
            isi TEXT NOT NULL,
            waktu TEXT NOT NULL
        )');

        // id pesan TERAKHIR yang sudah dibaca tiap pengguna → dasar lencana "belum dibaca".
        $pdo->exec('CREATE TABLE IF NOT EXISTS chat_baca (
            user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
            pesan_id INTEGER NOT NULL DEFAULT 0,
            waktu TEXT
        )');

        // ---------------------------------------------------------------- indeks
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_guru_sekolah ON guru(sekolah_id, nama)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_mapel_sekolah ON mapel(sekolah_id, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_kelas_sekolah ON kelas(sekolah_id, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_siswa_sekolah ON siswa(sekolah_id, kelas_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ta_sekolah ON tahun_ajaran(sekolah_id, nama)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_siswa_kelas ON siswa(kelas_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_nilai_scope ON nilai(sekolah_id, kelas_id, mapel_id, ta_id, semester_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_nilai_siswa ON nilai(siswa_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_absensi_scope ON absensi(sekolah_id, kelas_id, mapel_id, ta_id, semester_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_absensi_tanggal ON absensi(tanggal)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_absensi_detail_siswa ON absensi_detail(siswa_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_jurnal_tanggal ON jurnal(sekolah_id, tanggal)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_jurnal_guru ON jurnal(guru_id, tanggal)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_kasus_tanggal ON kasus(sekolah_id, tanggal)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_sekolah ON users(sekolah_id, role)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_siswa_status ON siswa(sekolah_id, status, kelas_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_kenaikan_siswa ON siswa_kenaikan(siswa_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_kenaikan_sekolah ON siswa_kenaikan(sekolah_id, dibuat)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_log_waktu ON log_aktivitas(waktu)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_log_aktivitas ON log_aktivitas(aktivitas)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_notulen_tanggal ON notulen(sekolah_id, tanggal)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_notulen_hadir ON notulen_hadir(notulen_id, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_notulen_hadir_guru ON notulen_hadir(guru_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_notulen_foto ON notulen_foto(notulen_id, urutan)');
        // v50: tanda tangan digital notulen (lihat inc/notulen_ttd.php).
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_notulen_ttd ON notulen_ttd(notulen_id, pihak)');
        // v55: daftar hadir meeting pengawas (lihat inc/meeting.php).
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_meeting_pengawas ON meeting(pengawas_id, tanggal)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_meeting_sekolah ON meeting_sekolah(sekolah_id, meeting_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_meeting_hadir ON meeting_hadir(meeting_id, sekolah_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sesi_aktif_terakhir ON sesi_aktif(terakhir)');
        // Obrolan (chat, v41): pencarian pesan baru & id pesan terakhir selalu memakai `id` + `waktu`.
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_chat_pesan_waktu ON chat_pesan(waktu)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_chat_pesan_user ON chat_pesan(user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_guru_tugas ON guru_tugas(tugas_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_nilai_pengaturan ON nilai_pengaturan(sekolah_id, guru_id, mapel_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pengawas_user ON pengawas_sekolah(user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pengawas_sekolah ON pengawas_sekolah(sekolah_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pengumuman_aktif ON pengumuman(aktif, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_tugas_menu_aktif ON tugas_menu(aktif, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_tugas_user ON user_tugas(user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_tugas_tugas ON user_tugas(tugas_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sarpras_sekolah ON sarpras_barang(sekolah_id, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_kesiswaan_sekolah ON kesiswaan_program(sekolah_id, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_osim_pengurus ON osim_pengurus(sekolah_id, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_osim_program ON osim_program(sekolah_id, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_osim_pengurus_kode ON osim_pengurus(sekolah_id, kode, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_osim_program_kode ON osim_program(sekolah_id, kode, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_perpus_laporan ON perpus_laporan(sekolah_id, jenis)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_perpus_baris ON perpus_baris(laporan_id, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_dokumen_sekolah ON dokumen(sekolah_id, dibuat)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_menu_sekolah_menu ON menu_sekolah(menu, sekolah_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_app_sekolah ON app_sekolah(menu, app_id, sekolah_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_bendahara_madrasah ON bendahara_madrasah(urutan, id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_wali_struktur ON wali_struktur(kelas_id, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_wali_program ON wali_program(kelas_id, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_wali_absensi ON wali_absensi(kelas_id, tanggal)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_wali_absensi_detail ON wali_absensi_detail(absensi_id, siswa_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_wali_piket ON wali_piket(kelas_id, hari, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_lab_barang ON lab_barang(sekolah_id, kode, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_lab_program ON lab_program(sekolah_id, kode, urutan)');
        // v44: halaman depan (landing page) — GLOBAL, tanpa sekolah_id.
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_landing_slider ON landing_slider(aktif, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_landing_fitur ON landing_fitur(aktif, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_landing_unggulan ON landing_unggulan(aktif, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_landing_testimoni ON landing_testimoni(aktif, urutan)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_landing_pesan ON landing_pesan(dibaca, id)');
        // CATATAN: indeks `idx_pengumuman_sasaran` (v46) SENGAJA dibuat di dalam blok MIGRASI v46
        // di bawah — bukan di sini — karena blok ini dijalankan SEBELUM `db_add_column()` sehingga
        // pada basis data yang belum punya kolomnya pembuatan indeks GAGAL ("no such column:
        // sasaran") dan SELURUH transaksi migrasi ter-ROLLBACK (pernah terjadi).

        // ---------------------------------------------------------------- data awal
        $now = date('Y-m-d H:i:s');
        provision_sekolah($pdo, 1);

        // Daftar menu TUGAS TAMBAHAN bawaan (10 menu, permintaan pemilik). Idempoten lewat kode:
        // INSERT OR IGNORE hanya mengisi yang belum ada, dan TIDAK mengubah nama/status yang sudah
        // diubah Super Admin (jadi penggantian nama & aktivasi tidak pernah tertimpa migrasi).
        if (db_has_table($pdo, 'tugas_menu')) {
            $insMenu = $pdo->prepare('INSERT OR IGNORE INTO tugas_menu (kode, nama, urutan, aktif, dibuat) VALUES (?,?,?,0,?)');
            $u = 0;
            foreach (tugas_menu_bawaan() as $kode => $nama) {
                $u += 10;
                $insMenu->execute([$kode, $nama, $u, $now]);
            }
        }

        // ---------------------------------------------------------------- MIGRASI v30
        // SATU DAFTAR TUGAS TAMBAHAN (permintaan pemilik): "Daftar Tugas Tambahan yang ada di akun
        // operator harus sama atau bersumber dari Daftar Tugas tambahan yang diinput oleh Super
        // Admin. Hapus fitur Tambah Tugas Tambahan yang ada di akun operator." → daftar
        // `tugas_tambahan` (per sekolah, 9 nama bawaan tiap sekolah = sumber duplikat) digabungkan
        // ke `tugas_menu` (GLOBAL, diisi Super Admin) lalu tabelnya DIHAPUS.
        // Dijalankan SESUDAH menu bawaan dibuat supaya nama lama bisa dicocokkan dengan namanya.
        if (db_has_table($pdo, 'tugas_tambahan')) {
            tugas_gabung_ke_menu($pdo, $now);
        }

        if ((int) db_val($pdo, 'SELECT COUNT(*) FROM users') === 0) {
            $pdo->prepare('INSERT INTO users (sekolah_id, username, password_hash, nama, role, aktif, dibuat) VALUES (NULL,?,?,?,?,1,?)')
                ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Super Admin', 'super', $now]);
        }

        // Penyetelan AWAL pembatasan menu "Aplikasi Sagu": dua sekolah yang disebut pemilik.
        // Dijalankan HANYA bila daftarnya masih kosong supaya pilihan Super Admin tidak pernah
        // ditimpa migrasi berikutnya. Pencocokan memakai NAMA yang dinormalkan
        // (sekolah_nama_kunci()) agar beda penulisan tetap dianggap sekolah yang sama.
        if (db_has_table($pdo, 'menu_sekolah') && db_has_table($pdo, 'sekolah')
            && (int) db_val($pdo, "SELECT COUNT(*) FROM menu_sekolah WHERE menu = 'aplikasi_sagu'") === 0) {
            $saguBawaan = [];
            foreach (['MA. Sirojuth Tholibiin Mootilango',
                      'MTsS. Sirojuth Tholibiin Sidomukti Mootilango'] as $nm) {
                $saguBawaan[sekolah_nama_kunci($nm)] = true;
            }
            $insMenuSekolah = $pdo->prepare('INSERT OR IGNORE INTO menu_sekolah (menu, sekolah_id, dibuat) VALUES (?,?,?)');
            foreach ($pdo->query('SELECT id, nama FROM sekolah')->fetchAll(PDO::FETCH_ASSOC) as $sk) {
                if (isset($saguBawaan[sekolah_nama_kunci((string) $sk['nama'])])) {
                    $insMenuSekolah->execute(['aplikasi_sagu', (int) $sk['id'], $now]);
                }
            }
        }

        // Catatan lama jurnal (skema <= v1) dipindahkan ke Keterangan satu kali.
        if (db_has_column($pdo, 'jurnal', 'kegiatan')) {
            $pindah = $pdo->query('SELECT id, kegiatan, kendala, tindak_lanjut FROM jurnal
                                   WHERE keterangan IS NULL OR TRIM(keterangan) = ""');
            $updKet = $pdo->prepare('UPDATE jurnal SET keterangan = ? WHERE id = ?');
            foreach ($pindah->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $bagian = [];
                if (trim((string) $r['kegiatan']) !== '') {
                    $bagian[] = trim((string) $r['kegiatan']);
                }
                if (trim((string) $r['kendala']) !== '') {
                    $bagian[] = 'Kendala: ' . trim((string) $r['kendala']);
                }
                if (trim((string) $r['tindak_lanjut']) !== '') {
                    $bagian[] = 'Tindak lanjut: ' . trim((string) $r['tindak_lanjut']);
                }
                if ($bagian) {
                    $updKet->execute([implode("\n", $bagian), (int) $r['id']]);
                }
            }
        }

        // ISI "TENTANG APLIKASI" PADA HALAMAN MASUK (skema v34) — permintaan pemilik: tulisan
        // Tentang Aplikasi yang lama DIGANTI oleh keterangan yang diisi pemilik pada menu baru
        // "Tentang Aplikasi" (tentang.php). Supaya halaman masuk tidak kosong sesaat setelah
        // pembaruan ini, keterangan diisi SEKALI dengan teks yang berlaku sebelumnya — pemilik
        // dapat menggantinya kapan saja lewat halaman itu.
        // Penjaga `tentang_seed` membuatnya benar-benar sekali-jalan: bila pemilik mengosongkan
        // keterangannya (memang disengaja), migrasi TIDAK mengisinya kembali.
        $adaSeed = db_val($pdo, 'SELECT nilai FROM app_pengaturan WHERE kunci = ?', ['tentang_seed']);
        if ($adaSeed === null) {
            $ketAwal = 'e-GURU adalah aplikasi administrasi guru digital untuk sekolah dan madrasah. '
                . 'Semua pekerjaan administrasi pembelajaran dikelola dalam satu tempat: data guru dan siswa, '
                . 'daftar hadir, daftar nilai Kurikulum Merdeka, jurnal harian mengajar, catatan pelanggaran '
                . 'siswa, tugas tambahan guru (kesiswaan, sarpras, wali kelas, kepala lab, kepala perpustakaan, '
                . 'kepala tu, pembina pramuka, pembina Osim/Osis, pembina PMR dan bendahara) — lengkap dengan '
                . 'pengaturan identitas sekolah, tahun ajaran, semester, dan kelas. Semua dokumen tersimpan '
                . 'dengan rapi dan bisa dicetak saat diperlukan.'
                . "\n\n"
                . 'Aplikasi ini multi-sekolah/madrasah: satu pemasangan dapat dipakai banyak '
                . 'sekolah/madrasah sekaligus, dengan data setiap sekolah yang terpisah dan akun berjenjang '
                . 'sesuai tugas masing-masing.';
            $nowSeed = date('Y-m-d H:i:s');
            $insSeed = $pdo->prepare('INSERT OR IGNORE INTO app_pengaturan (kunci, nilai, diubah) VALUES (?,?,?)');
            $insSeed->execute(['tentang_keterangan', $ketAwal, $nowSeed]);
            $insSeed->execute(['tentang_seed', '1', $nowSeed]);
        }

        // ---------------------------------------------------------------- MIGRASI v49
        // JENIS LEMBAGA + PILIHAN JENJANG + GURU KELAS — permintaan pemilik: *"pada yang ditandai
        // warna biru ganti dengan Jenis Lembaga. dan pada yang saya tandai warna merah tambahkan
        // fitur Pilihan Jenjang. Ketika Jenis lembaga yang dipilih sekolah umum maka fitur pilihan
        // jenjang yang aktif SD, SMP, SMA, SMK. Ketika yang dipilih madrasah pilihan jenjangnya MI,
        // MTs, MA, MAK. Untuk setingan MTs/SMP, SMA/MA/SMK pengaturan tetap seperti sekarang ini
        // tidak ada perubahan. Tapi ketika yang dipilih jenjang SD atau MI maka setingan berbeda.
        // Karena guru MI atau SD itu guru kelas yang memegang banyak mata pelajaran"*.
        //
        // Dua kolom BARU (hanya MENAMBAH kolom — tidak ada tabel yang dibangun ulang):
        //   • `sekolah.jenjang` — pilihan jenjang lembaga. SENGAJA dibiarkan KOSONG untuk sekolah
        //     yang sudah ada: tidak ada nilai yang ditebak (sekolah lama bisa saja SD, SMP, MA, …),
        //     dan selama kosong seluruh pengaturan berjalan persis seperti sebelumnya.
        //   • `guru.guru_kelas` — penanda GURU KELAS (dipakai pada jenjang SD/MI saja). Bawaannya 0
        //     sehingga tidak ada guru yang mendadak berubah perilakunya.
        db_add_column($pdo, 'sekolah', 'jenjang', 'TEXT');
        db_add_column($pdo, 'guru', 'guru_kelas', 'INTEGER NOT NULL DEFAULT 0');
        // Nilai kosong/tak dikenal dinormalkan (idempoten) — penanda guru kelas hanya 0 atau 1.
        $pdo->exec('UPDATE guru SET guru_kelas = 0 WHERE guru_kelas IS NULL OR guru_kelas <> 1');

        // ---------------------------------------------------------------- MIGRASI v48
        // FITUR "KIRIM PERANGKAT" — pemilik: *"Menu Perangkat Guru yang ada di Super Admin dihapus
        // saja dengan isinya. Menu ini sudah tidak dibutuhkan. Sekarang buatkan fitur Kirim Perangkat
        // di akun guru (Seperti Kalender Pendidikan, Analisis Pekan Efektif, Capaian Pembelajaran,
        // Alur Tujuan Pembelajaran, RPP/Modul Ajar. Nama-nama dokumen ini di isi oleh Super Admin,
        // bisa ditambah atau dikurangi. File yang terkirim masuk di akun Kepala sekolah … dan
        // Pengawas (Pengawas hanya lihat dan download). Hasil tinjauan kepala sekolah terkirim ke
        // akun guru"*.
        //
        // Menu & halaman "Perangkat Guru" yang LAMA (publik + kode download) sudah DIHAPUS dari kode;
        // instalasi baru tidak lagi membuat tabel lamanya (`perangkat_berkas`/`perangkat_kode`).
        // Tabel lama pada basis data yang sudah ada sengaja DIBIARKAN (tidak ada DROP) supaya tidak
        // ada penghapusan data yang berisiko — tabel itu kini tidak dipakai kode mana pun.
        //
        // Dua tabel BARU (hanya CREATE TABLE/CREATE INDEX — tidak ada tabel lama yang dibangun ulang):
        //   • `perangkat_jenis` — daftar NAMA DOKUMEN perangkat, **GLOBAL** (satu daftar untuk semua
        //     sekolah/madrasah), diisi Super Admin lewat menu "Jenis Perangkat" (bisa ditambah atau
        //     dikurangi). `aktif` = 0 hanya menyembunyikan dari pilihan, tidak menghapus.
        //   • `perangkat_kirim` — kiriman perangkat guru, **PER SEKOLAH**: satu baris untuk setiap
        //     kombinasi (guru × mata pelajaran × tahun ajaran × nama dokumen) — UNIQUE, sehingga
        //     mengunggah ulang pada sel yang sama MENGGANTIKAN berkasnya (dan mengembalikan status
        //     tinjauan ke 'menunggu'). `nama_jenis` disimpan sebagai SNAPSHOT supaya kiriman lama
        //     tetap terbaca setelah nama dokumennya diubah/dihapus Super Admin (relasi `jenis_id`
        //     menjadi NULL). Yang disimpan ke basis data HANYA URL CDN hasil media proxy platform.
        $pdo->exec('CREATE TABLE IF NOT EXISTS perangkat_jenis (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            urutan INTEGER NOT NULL DEFAULT 10,
            aktif INTEGER NOT NULL DEFAULT 1,
            dibuat TEXT,
            diubah TEXT
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS perangkat_kirim (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sekolah_id INTEGER NOT NULL DEFAULT 1 REFERENCES sekolah(id) ON DELETE CASCADE,
            guru_id INTEGER NOT NULL REFERENCES guru(id) ON DELETE CASCADE,
            mapel_id INTEGER NOT NULL REFERENCES mapel(id) ON DELETE CASCADE,
            ta_id INTEGER REFERENCES tahun_ajaran(id) ON DELETE SET NULL,
            ta_nama TEXT,
            jenis_id INTEGER REFERENCES perangkat_jenis(id) ON DELETE SET NULL,
            nama_jenis TEXT NOT NULL,
            keterangan TEXT,
            url TEXT NOT NULL,
            nama_berkas TEXT,
            jenis_berkas TEXT,
            ukuran INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT "menunggu",
            catatan TEXT,
            ditinjau_oleh INTEGER,
            ditinjau_pada TEXT,
            dikirim TEXT,
            diubah TEXT,
            UNIQUE (guru_id, mapel_id, ta_id, jenis_id)
        )');

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_perangkat_kirim_sekolah
                    ON perangkat_kirim(sekolah_id, status, dikirim)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_perangkat_kirim_guru
                    ON perangkat_kirim(guru_id, ta_id, mapel_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_perangkat_jenis_urut ON perangkat_jenis(aktif, urutan)');

        // Nama dokumen BAWAAN — disemai SEKALI saja (hanya bila tabelnya masih kosong) supaya nama
        // yang sudah diubah/ditambah/dikurangi Super Admin tidak pernah dikembalikan migrasi
        // berikutnya (pola yang sama dengan seed jenis dokumen & daftar aplikasi).
        $jmlJenis = (int) db_val($pdo, 'SELECT COUNT(*) FROM perangkat_jenis', [], 0);
        if ($jmlJenis === 0) {
            $nowJenis = date('Y-m-d H:i:s');
            $insJenis = $pdo->prepare('INSERT INTO perangkat_jenis (nama, urutan, aktif, dibuat, diubah)
                                       VALUES (?,?,1,?,?)');
            $urut = 10;
            foreach (PERANGKAT_JENIS_BAWAAN as $namaJenis) {
                $insJenis->execute([$namaJenis, $urut, $nowJenis, $nowJenis]);
                $urut += 10;
            }
        }

        // ---------------------------------------------------------------- MIGRASI v56
        // PERBAIKAN TABEL `meeting_hadir` YANG TERLANJUR MEMILIKI FOREIGN KEY KE TABEL YANG TIDAK ADA.
        //
        // 🚨 KASUS NYATA (dilaporkan pemilik: *"Saat peserta meeting melakukan tanda tangan, tanda
        // tangan tidak bisa disimpan dengan pesan Gagal menghubungi server"*): versi pertama kode
        // fitur ini sempat menulis `sekolah_id … REFERENCES schools_placeholder(id)` pada
        // `CREATE TABLE meeting_hadir` (salah tulis nama tabel). SQLite TIDAK memeriksa sasaran
        // foreign key saat CREATE TABLE — kesalahannya baru muncul sebagai galat saat INSERT
        // (`no such table: main.schools_placeholder`), dan karena `CREATE TABLE IF NOT EXISTS`
        // tidak pernah memperbaiki tabel yang sudah ada, basis data yang sempat menjalankan versi
        // itu terus gagal menyimpan tanda tangan. Perbaikannya: bangun ULANG tabelnya bila
        // definisi tersimpan masih memuat `schools_placeholder`.
        //
        // Isi tabel DISALIN (id tetap) sehingga tidak ada tanda tangan yang hilang; tabelnya memang
        // baru (kosong) saat perbaikan ini dibuat. `PRAGMA foreign_keys` sudah OFF di luar
        // transaksi (lihat awal ensure_schema) sehingga DROP/ALTER aman, dan tidak ada tabel lain
        // yang menunjuk `meeting_hadir` (jadi RENAME tidak menulis ulang FK tabel lain).
        $sqlHadir = (string) db_val($pdo,
            "SELECT IFNULL(sql, '') FROM sqlite_master WHERE type = 'table' AND name = 'meeting_hadir'", [], '');
        if ($sqlHadir !== '' && strpos($sqlHadir, 'schools_placeholder') !== false) {
            $pdo->exec('CREATE TABLE IF NOT EXISTS meeting_hadir_baru (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                meeting_id INTEGER NOT NULL REFERENCES meeting(id) ON DELETE CASCADE,
                sekolah_id INTEGER NOT NULL REFERENCES sekolah(id) ON DELETE CASCADE,
                user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                nama TEXT,
                username TEXT,
                peran TEXT,
                url TEXT NOT NULL,
                lebar INTEGER NOT NULL DEFAULT 0,
                tinggi INTEGER NOT NULL DEFAULT 0,
                ip TEXT,
                dibuat TEXT,
                diubah TEXT,
                UNIQUE (meeting_id, sekolah_id, user_id)
            )');
            $pdo->exec('INSERT INTO meeting_hadir_baru
                          (id, meeting_id, sekolah_id, user_id, nama, username, peran, url,
                           lebar, tinggi, ip, dibuat, diubah)
                        SELECT id, meeting_id, sekolah_id, user_id, nama, username, peran, url,
                               lebar, tinggi, ip, dibuat, diubah
                        FROM meeting_hadir');
            $pdo->exec('DROP TABLE meeting_hadir');
            $pdo->exec('ALTER TABLE meeting_hadir_baru RENAME TO meeting_hadir');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_meeting_hadir ON meeting_hadir(meeting_id, sekolah_id)');
            error_log('e-GURU migrasi v56: tabel meeting_hadir dibangun ulang (FK schools_placeholder diperbaiki).');
        }

        // ---------------------------------------------------------------- MIGRASI v57
        // PEMBATAS LAJU ENDPOINT PUBLIK (v1.73.4). Dua endpoint yang dapat dipanggil TANPA login
        // hanya dibatasi "N kali per sesi"; cukup mengosongkan cookie untuk mengulang tanpa henti
        // (pendaftaran sekolah membuat baris `sekolah` + akun + perlengkapan, formulir pesan
        // mengisi `landing_pesan`). Tabel kecil ini mencatat kejadian per kunci (mis. `daftar:<ip>`)
        // supaya batasnya berlaku per ALAMAT IP, bukan per sesi.
        // Hanya CREATE TABLE/CREATE INDEX — tidak ada tabel lama yang dibangun ulang dan tabelnya
        // tetap KOSONG setelah migrasi.
        $pdo->exec('CREATE TABLE IF NOT EXISTS throttle (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kunci TEXT NOT NULL,
            waktu TEXT NOT NULL
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_throttle ON throttle(kunci, waktu)');

        // ---------------------------------------------------------------- MIGRASI v58
        // KEBIJAKAN KATA SANDI BARU (v1.74.0) — minimal 8 karakter + kombinasi huruf besar/kecil/angka.
        //
        // Kolom `users.sandi_wajib_ganti` = 1 berarti akun itu WAJIB mengganti sandinya lebih dulu
        // (popup + seluruh halaman dialihkan ke formulir ganti sandi, lihat gerbang_sandi()).
        //
        // Migrasi menandai SELURUH akun yang SUDAH ADA saat kebijakan ini dipasang: sandi mereka
        // dibuat dengan aturan lama (minimal 6 karakter, boleh sandi awal guru123/admin123) dan
        // TIDAK dapat dibuktikan memenuhi kebijakan baru — hash bcrypt tidak menyimpan panjang
        // sandi. Akun yang dibuat SESUDAHNYA tidak ditandai otomatis; penandanya diatur di jalur
        // pembuatan/penetapan sandi (sandi_simpan()).
        //
        // ⚠️ Penanda sekali-jalan (`app_pengaturan.sandi_kebijakan_v1`) WAJIB ada: tanpa itu,
        // setiap kali migrasi dipulihkan (mis. penanda skema hilang) akun yang sudah mengganti
        // sandinya akan ditandai ulang sehingga pengguna terjebak memutar.
        // Tambah kolomnya LEBIH DULU: pada basis data lama blok ini berjalan sebelum blok
        // migrasi lain, dan UPDATE di bawah membutuhkannya. Ditulis juga pada CREATE TABLE
        // (instalasi baru) dan didaftarkan di SCHEMA_KOLOM_WAJIB (pemulihan diri).
        db_add_column($pdo, 'users', 'sandi_wajib_ganti', 'INTEGER NOT NULL DEFAULT 0');
        db_add_column($pdo, 'users', 'sandi_diubah', 'TEXT');
        $pdo->exec('CREATE TABLE IF NOT EXISTS app_pengaturan (
            kunci TEXT PRIMARY KEY,
            nilai TEXT,
            diubah TEXT
        )');
        $sudahV58 = (int) $pdo->query("SELECT COUNT(*) FROM app_pengaturan WHERE kunci = 'sandi_kebijakan_v1'")->fetchColumn();
        if ($sudahV58 === 0) {
            $jmlLama = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            if ($jmlLama > 0 && !$databaseLama) {
                // INSTALASI BARU: akun yang baru dibuat oleh seed (admin bawaan) TIDAK ditandai —
                // syarat "wajib ganti" berlaku untuk sandi yang dibuat SEBELUM kebijakan ini.
                $jmlLama = 0;
            } elseif ($jmlLama > 0) {
                $pdo->exec('UPDATE users SET sandi_wajib_ganti = 1 WHERE sandi_wajib_ganti = 0');
            }
            $st = $pdo->prepare('INSERT INTO app_pengaturan (kunci, nilai, diubah) VALUES (?,?,?)');
            $st->execute(['sandi_kebijakan_v1', (string) $jmlLama, date('Y-m-d H:i:s')]);
            error_log('e-GURU migrasi v58: kebijakan sandi baru — ' . $jmlLama
                . ' akun lama ditandai WAJIB ganti sandi.');
        }

        // ---------------------------------------------------------------- MIGRASI v47
        // (Catatan riwayat) Halaman publik "Perangkat Guru" + KODE DOWNLOAD — kini DIHAPUS karena
        // digantikan fitur "Kirim Perangkat" di atas (v1.67.0). Jangan menghidupkan kembali.

        // ---------------------------------------------------------------- MIGRASI v55
        // DAFTAR HADIR MEETING/PERTEMUAN PENGAWAS — permintaan pemilik: *"Pada akun pengawas
        // tambahkan menu aktifkan daftar hadir meeting (pertemuan/zoom) … Ketika diaktifkan maka akan
        // muncul di seluruh akun binaannya untuk di tanda tangani secara online …"*.
        // Hanya CREATE TABLE/CREATE INDEX (dijalankan juga di blok atas) — tidak ada tabel lama yang
        // dibangun ulang dan tabelnya tetap KOSONG setelah migrasi (tidak ada pertemuan yang ditebak).
        $pdo->exec('CREATE TABLE IF NOT EXISTS meeting (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pengawas_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            pengawas_nama TEXT,
            judul TEXT NOT NULL,
            tanggal TEXT NOT NULL,
            ttd_url TEXT,
            ttd_lebar INTEGER NOT NULL DEFAULT 0,
            ttd_tinggi INTEGER NOT NULL DEFAULT 0,
            ttd_pada TEXT,
            dibuat TEXT,
            diubah TEXT
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS meeting_sekolah (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            meeting_id INTEGER NOT NULL REFERENCES meeting(id) ON DELETE CASCADE,
            sekolah_id INTEGER NOT NULL REFERENCES sekolah(id) ON DELETE CASCADE,
            diaktifkan TEXT,
            UNIQUE (meeting_id, sekolah_id)
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS meeting_hadir (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            meeting_id INTEGER NOT NULL REFERENCES meeting(id) ON DELETE CASCADE,
            sekolah_id INTEGER NOT NULL REFERENCES sekolah(id) ON DELETE CASCADE,
            user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
            nama TEXT,
            username TEXT,
            peran TEXT,
            url TEXT NOT NULL,
            lebar INTEGER NOT NULL DEFAULT 0,
            tinggi INTEGER NOT NULL DEFAULT 0,
            ip TEXT,
            dibuat TEXT,
            diubah TEXT,
            UNIQUE (meeting_id, sekolah_id, user_id)
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_meeting_pengawas ON meeting(pengawas_id, tanggal)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_meeting_sekolah ON meeting_sekolah(sekolah_id, meeting_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_meeting_hadir ON meeting_hadir(meeting_id, sekolah_id)');

        // ---------------------------------------------------------------- MIGRASI v54
        // MENU "STUDIO FOTO PINTAR" (v1.72.4) — permintaan pemilik: *"dibawah menu Smart Teacher
        // tambahkan menu Studio Foto Pintar (tambahkan label Bonus seperti Menu Smart Teacher) dan
        // linknya akan di isi oleh super admin. Dan jika link belum di isi ketika klik menu tersebut
        // tampilkan keterangan aplikasi sementara dikembangkan"*.
        // Hanya CREATE TABLE (bentuknya sama dengan `smart_teacher`, GLOBAL tanpa `sekolah_id`) dan
        // tabelnya sengaja dibiarkan KOSONG — tidak ada aplikasi contoh yang dipaksakan, sehingga
        // sebelum Super Admin mengisi tautannya menu ini menampilkan keterangan
        // "Aplikasi Sementara Dikembangkan". Tidak ada tabel lama yang dibangun ulang.
        $pdo->exec('CREATE TABLE IF NOT EXISTS studio_foto (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            link TEXT,
            keterangan TEXT,
            urutan INTEGER NOT NULL DEFAULT 0,
            aktif INTEGER NOT NULL DEFAULT 1,
            baru_pada TEXT,
            dibuat TEXT,
            diubah TEXT
        )');

        // ---------------------------------------------------------------- MIGRASI v53
        // LENCANA "Bonus" BERKEDIP PADA MENU SMART TEACHER — permintaan pemilik: *"Tulisan new
        // berkedip ganti dengan tulisan 'Bonus'. Pada menu Smart Teacher juga tambahkan fitur 'Bonus'
        // berkedip"*. Fitur lencana ini sebelumnya hanya ada pada menu Ujian Online (v43).
        // Hanya MENAMBAH kolom (tidak ada tabel yang dibangun ulang) dan kolomnya KOSONG — tidak ada
        // aplikasi yang tiba-tiba dianggap "bonus" (tidak ada nilai yang ditebak).
        db_add_column($pdo, 'smart_teacher', 'baru_pada', 'TEXT');

        // ---------------------------------------------------------------- MIGRASI v52
        // CATATAN PENGAWAS PADA PERANGKAT GURU — permintaan pemilik: *"Pada akun pengawas tambahkan
        // menu Perangkat Guru (Perangkat guru ditampilkan per lembaga yang di dalamnya ada daftar
        // guru + mata pelajaran dan daftar perangkatnya). Pengawas bisa melihat, memberikan catatan
        // setiap perangkat dan mendownload"*.
        //
        // Catatan pengawas **TERPISAH** dari status tinjauan kepala sekolah (pilihan pemilik): kolom
        // `perangkat_kirim.status`/`catatan` tetap milik kepala sekolah/madrasah, sedangkan catatan
        // pengawas disimpan di tabel sendiri sehingga **guru & kepala sekolah dapat membacanya**
        // tanpa mengubah hasil tinjauan.
        //
        // Satu baris per (kiriman perangkat × pengawas) — UNIQUE, jadi seorang pengawas hanya punya
        // SATU catatan pada satu perangkat (boleh diperbarui); beberapa pengawas pada perangkat yang
        // sama berdampingan (pola yang sama dengan `komentar_pengawas` pada aplikasi Supervisi Guru).
        // Menghapus kiriman perangkat/sekolah/akun pengawas otomatis menghapus catatannya (CASCADE).
        // Hanya CREATE TABLE/CREATE INDEX — tidak ada tabel lama yang dibangun ulang, dan tabel baru
        // ini tetap KOSONG setelah migrasi (tidak ada catatan yang ditebak).
        $pdo->exec('CREATE TABLE IF NOT EXISTS perangkat_catatan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kirim_id INTEGER NOT NULL REFERENCES perangkat_kirim(id) ON DELETE CASCADE,
            pengawas_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            isi TEXT NOT NULL,
            dibuat TEXT,
            diubah TEXT,
            UNIQUE (kirim_id, pengawas_id)
        )');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_perangkat_catatan_kirim
                    ON perangkat_catatan(kirim_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_perangkat_catatan_pengawas
                    ON perangkat_catatan(pengawas_id)');

        // ---------------------------------------------------------------- MIGRASI v51
        // TINDAK LANJUT PELANGGARAN oleh guru Tugas Tambahan "Kesiswaan" — permintaan pemilik:
        // *"Pada menu kesiswaan tambahkan fitur rekap pelanggaran dari seluruh guru mata pelajaran.
        //  Jadi guru yang dapat tugas tambahan kesiswaan bisa melihat, memberikan tindak lanjut, dan
        //  mencetak seluruh catatan pelanggaran dari setiap guru mata pelajaran."*
        // Empat kolom baru pada tabel `kasus` (hanya MENAMBAH kolom — tidak ada tabel yang dibangun
        // ulang). Catatan pelanggaran yang SUDAH ADA tetap bernilai kosong = belum ditindaklanjuti
        // (tidak ada nilai yang ditebak). `mapel_id` (v9) ikut dipastikan ada lewat skema wajib.
        db_add_column($pdo, 'kasus', 'mapel_id', 'INTEGER');
        db_add_column($pdo, 'kasus', 'tindak_lanjut', 'TEXT');
        db_add_column($pdo, 'kasus', 'tindak_lanjut_jenis', 'TEXT');
        db_add_column($pdo, 'kasus', 'tindak_lanjut_oleh', 'TEXT');
        db_add_column($pdo, 'kasus', 'tindak_lanjut_pada', 'TEXT');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_kasus_tindak_lanjut
                    ON kasus(sekolah_id, tindak_lanjut_jenis)');

        // ---------------------------------------------------------------- MIGRASI v46
        // POPUP UNTUK AKUN SISWA DIISI TERSENDIRI (permintaan pemilik): *"untuk pop up yang muncul
        // di akun siswa diinput tersendiri oleh super admin"*. Kolom `sasaran` memisahkan isi popup
        // untuk **Pengguna** (Pengawas/Kepala/Operator/Guru) dan untuk **Siswa**.
        // Bawaannya `pengguna` supaya pengumuman yang SUDAH ADA tetap tampil seperti semula.
        // Hanya MENAMBAH kolom — tidak ada tabel yang dibangun ulang.
        db_add_column($pdo, 'pengumuman', 'sasaran', 'TEXT NOT NULL DEFAULT "pengguna"');
        // Nilai kosong/tak dikenal dinormalkan ke 'pengguna' (idempoten).
        $pdo->exec('UPDATE pengumuman SET sasaran = "pengguna" WHERE sasaran IS NULL OR TRIM(sasaran) = ""');
        // Indeks WAJIB dibuat SETELAH kolomnya ada (lihat catatan pada blok indeks di atas).
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pengumuman_sasaran ON pengumuman(sasaran, aktif, urutan)');

        // ---------------------------------------------------------------- MIGRASI v45
        // AKUN SISWA: siswa dapat masuk ke aplikasi HANYA dengan memasukkan NISN-nya, dan hanya
        // boleh melihat serta mencetak datanya sendiri (permintaan pemilik). Akun dibuat OTOMATIS
        // saat siswa berhasil masuk pertama kali sehingga kolom `siswa_id` di tautkan ke baris
        // `siswa`. Hanya MENAMBAH kolom — tidak ada tabel yang dibangun ulang.
        db_add_column($pdo, 'users', 'siswa_id', 'INTEGER');

        // ISI AWAL HALAMAN DEPAN (landing page, skema v44) — dijalankan SEKALI saja; penanda
        // `landing_seed` (dipasang di dalam landing_seed()) membuat seluruh suntingan Super Admin
        // tidak pernah ditimpa migrasi berikutnya, termasuk bila daftarnya dikosongkan.
        if (db_has_table($pdo, 'landing_slider') && db_has_table($pdo, 'landing_fitur')
            && db_has_table($pdo, 'landing_unggulan') && db_has_table($pdo, 'landing_testimoni')) {
            landing_seed($pdo);
        }

        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        if ($fkOn) {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        throw $ex;
    }

    if ($fkOn) {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    @file_put_contents($flag, SCHEMA_VERSION);
}
