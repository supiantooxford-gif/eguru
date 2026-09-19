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

/** Naikkan angka ini setiap kali skema berubah agar migrasi/seed ikut berjalan. */
const SCHEMA_VERSION = '6';

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

function ensure_schema(PDO $pdo): void
{
    // Penanda cepat: skema + seed hanya dihitung sekali, bukan tiap request.
    $flag = dirname(DB_FILE) . '/.schema_ok';
    if (is_file($flag) && is_file(DB_FILE) && filesize(DB_FILE) > 0
        && trim((string) @file_get_contents($flag)) === SCHEMA_VERSION) {
        return;
    }

    $fkOn = ((int) $pdo->query('PRAGMA foreign_keys')->fetchColumn()) === 1;
    if ($fkOn) {
        $pdo->exec('PRAGMA foreign_keys = OFF');
    }

    $pdo->exec('BEGIN IMMEDIATE');
    try {
        // ---------------------------------------------------------------- tabel sekolah
        $pdo->exec('CREATE TABLE IF NOT EXISTS sekolah (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL DEFAULT "",
            npsn TEXT,
            alamat TEXT,
            telepon TEXT,
            email TEXT,
            website TEXT,
            kepala_sekolah TEXT,
            nip_kepala_sekolah TEXT,
            jenis TEXT NOT NULL DEFAULT "umum",
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
            foto TEXT,
            aktif INTEGER NOT NULL DEFAULT 1,
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
            tanggal_lahir TEXT,
            nama_wali TEXT,
            telepon TEXT,
            alamat TEXT,
            aktif INTEGER NOT NULL DEFAULT 1,
            status TEXT NOT NULL DEFAULT "aktif",
            tanggal_status TEXT,
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
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sesi_aktif_terakhir ON sesi_aktif(terakhir)');

        // ---------------------------------------------------------------- data awal
        $now = date('Y-m-d H:i:s');
        provision_sekolah($pdo, 1);

        if ((int) db_val($pdo, 'SELECT COUNT(*) FROM users') === 0) {
            $pdo->prepare('INSERT INTO users (sekolah_id, username, password_hash, nama, role, aktif, dibuat) VALUES (NULL,?,?,?,?,1,?)')
                ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Super Admin', 'super', $now]);
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
