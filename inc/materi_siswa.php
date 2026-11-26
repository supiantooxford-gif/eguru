<?php
/**
 * KIRIM MATERI/TUGAS KE SISWA — modul bersama (v1.113.0 / skema v73).
 *
 * Permintaan pemilik: *"Pada akun guru tambahkan menu 'Kirim Materi/Tugas Ke Siswa'. Isi dari menu ini
 * guru bisa mengirim sekaligus ke siswa yang dia ajar atau memilih siswa tertentu. Modulnya: Judul
 * Materi/Tugas, Mata Pelajaran, Unggah (bisa pdf, word, gambar dan Maksimal 2MB) dan ada setingan
 * batas waktu tugas/materi bisa dibuka oleh siswa. Hasilnya akan terkirim ke akun siswa dengan
 * menampilkan nama guru pengirim, mata pelajaran, file/dokumen (bisa di download dan dicetak)"*.
 *
 * Alur lengkapnya:
 *   • **GURU** (`materi_kirim.php`, menu sidebar tepat di bawah "Kirim Perangkat") mengirim satu
 *     materi/tugas: Judul, Mata Pelajaran (hanya yang ia ampu), berkas (pdf/docx/gambar, maks 2 MB),
 *     keterangan, pengaturan waktu buka, dan PENERIMA — **seluruh siswa yang ia ajar** (kelas yang
 *     diampu, lihat kelas_diampu()) atau **siswa tertentu** yang dicentang. Guru juga melihat daftar
 *     kirimannya beserta jumlah penerima dan dapat menghapus kiriman yang salah.
 *   • **SISWA** (`materi_saya.php`, menu "Materi & Tugas") melihat materi/tugas yang ditujukan
 *     kepadanya: **nama guru pengirim**, **mata pelajaran**, berkas (**Unduh**) dan lembar **Cetak**
 *     (`cetak_materi.php`). Materi yang belum waktunya dibuka atau sudah melewati batas waktu TIDAK
 *     dapat dibuka/diunduh/dicetak.
 *
 * Keputusan yang dipakai (jangan diubah tanpa bertanya kepada pemilik):
 *   1. Letak menu guru = TEPAT di bawah "Kirim Perangkat" (kotak merah pada tangkapan layar pemilik).
 *   2. Berkas yang diterima = pdf, word (**.docx**), dan gambar (MATERI_EKSTENSI). `.doc` (Word lama)
 *      tidak tersedia karena media proxy platform tidak menerima ekstensi itu.
 *   3. **Dua** pengaturan waktu: "Dibuka mulai" (boleh kosong = langsung dibuka) dan "Batas waktu"
 *      (boleh kosong = tanpa batas). Keduanya dibandingkan DI SERVER lewat `materi_keadaan()` —
 *      jangan hanya menyembunyikan tombolnya di tampilan.
 *   4. Nama guru/mapel/kelas & nama siswa disimpan sebagai SNAPSHOT supaya riwayat tetap terbaca.
 *
 * Berkas diunggah lewat **FORM MULTIPART BIASA** ke media proxy platform DI SISI SERVER
 * (`media_upload()`); token unggah tidak pernah sampai ke peramban dan yang disimpan ke basis data
 * hanya URL CDN hasil respons. Aturan jenis/ukuran berkas memakai penolong modul Kurikulum
 * (`dok_validasi()`, `dok_ukuran()`, `dok_batas_server()`) supaya tidak ada dua aturan berbeda.
 *
 * 🚨 **Teks yang DIKETIK PENGGUNA dibungkus `tetap_kata()`** saat dicetak (judul, keterangan, nama
 * berkas, nama guru, nama siswa, nama kelas) supaya tidak ikut penyesuaian istilah "Sekolah" →
 * "Madrasah".
 *
 * ⚠️ JANGAN menyalin logika ini ke halaman: `materi_kirim.php`, `materi_saya.php`, dan
 * `cetak_materi.php` hanya meneruskan ke fungsi-fungsi di sini.
 */
declare(strict_types=1);

require_once __DIR__ . '/dokumen.php';

// ============================================================================
// KONSTANTA, LABEL, DAN KEADAAN WAKTU
// ============================================================================

/** Ekstensi berkas yang diterima (pdf, word .docx, gambar) — lihat MATERI_EKSTENSI. */
function materi_ekstensi(): array
{
    return MATERI_EKSTENSI;
}

/** Teks daftar ekstensi untuk keterangan pada formulir, mis. "pdf, docx, jpg, …". */
function materi_ekstensi_teks(): string
{
    return implode(', ', materi_ekstensi());
}

/** Siapa yang boleh MENGIRIM materi/tugas: akun GURU (pemilik fitur ini). */
function materi_boleh_kirim(): bool
{
    return is_guru();
}

/** Daftar keadaan waktu [kunci => label] — lihat MATERI_KEADAAN di inc/config.php. */
function materi_keadaan_list(): array
{
    return MATERI_KEADAAN;
}

/** Label keadaan waktu ("Dapat Dibuka" / "Belum Dibuka" / "Sudah Ditutup"). */
function materi_keadaan_label(string $keadaan): string
{
    return (string) (MATERI_KEADAAN[$keadaan] ?? MATERI_KEADAAN['buka']);
}

/** Kelas pil (tema) untuk sebuah keadaan waktu. */
function materi_keadaan_pil(string $keadaan): string
{
    if ($keadaan === 'belum') {
        return 'pill-warn';
    }
    return $keadaan === 'tutup' ? 'pill-D' : 'pill-ok';
}

/** Nama ikon tema untuk sebuah keadaan waktu. */
function materi_keadaan_ikon(string $keadaan): string
{
    if ($keadaan === 'belum') {
        return 'clock';
    }
    return $keadaan === 'tutup' ? 'x' : 'check';
}

/**
 * KEADAAN WAKTU sebuah materi/tugas bagi siswa: 'buka' | 'belum' | 'tutup'.
 *
 * SATU sumber aturan (dipakai halaman guru, halaman siswa, lembar cetak, dan lencana menu):
 *   • `buka_mulai` terisi dan sekarang BELUM sampai waktunya → 'belum';
 *   • `batas_waktu` terisi dan sekarang SUDAH melewatinya   → 'tutup';
 *   • selain itu (termasuk keduanya kosong)                 → 'buka'.
 * Waktu kosong/tidak sah dianggap TIDAK membatasi (perilaku paling aman: materi tetap dapat dibuka).
 *
 * @param array<string,mixed> $baris
 */
function materi_keadaan(array $baris, ?int $sekarang = null): string
{
    $now = $sekarang ?? time();
    $mulai = trim((string) ($baris['buka_mulai'] ?? ''));
    if ($mulai !== '') {
        $ts = strtotime($mulai);
        if ($ts !== false && $now < $ts) {
            return 'belum';
        }
    }
    $batas = trim((string) ($baris['batas_waktu'] ?? ''));
    if ($batas !== '') {
        $ts = strtotime($batas);
        if ($ts !== false && $now > $ts) {
            return 'tutup';
        }
    }
    return 'buka';
}

/**
 * Nilai dari `<input type="datetime-local">` ('Y-m-d\TH:i') atau teks tanggal apa pun → 'Y-m-d H:i:s'.
 *
 * @return string|null null bila kosong / tidak dapat dikenali
 */
function materi_waktu_norm(?string $nilai): ?string
{
    $n = trim((string) $nilai);
    if ($n === '') {
        return null;
    }
    $ts = strtotime($n);
    return $ts === false ? null : date('Y-m-d H:i:s', $ts);
}

/** Nilai untuk `value` sebuah `<input type="datetime-local">` ('' bila kosong). */
function materi_waktu_input(?string $nilai): string
{
    $n = trim((string) $nilai);
    if ($n === '') {
        return '';
    }
    $ts = strtotime($n);
    return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
}

/** Tulisan tanggal & jam yang enak dibaca, mis. "2 Juni 2026, 08:00". */
function materi_waktu_teks(?string $nilai): string
{
    $n = trim((string) $nilai);
    if ($n === '') {
        return '-';
    }
    $ts = strtotime($n);
    if ($ts === false) {
        return $n;
    }
    return tanggal_id(date('Y-m-d', $ts)) . ', ' . date('H:i', $ts);
}

/**
 * Ringkasan pengaturan waktu sebuah kiriman untuk ditampilkan, mis.
 * "Dibuka 2 Juni 2026, 08:00 &middot; ditutup 5 Juni 2026, 23:59" atau "Tanpa batas waktu".
 * (Mengandung entitas HTML seperti penolong tampilan lain di aplikasi ini.)
 *
 * @param array<string,mixed> $baris
 */
function materi_waktu_ringkas(array $baris): string
{
    $mulai = trim((string) ($baris['buka_mulai'] ?? ''));
    $batas = trim((string) ($baris['batas_waktu'] ?? ''));
    if ($mulai === '' && $batas === '') {
        return 'Tanpa batas waktu';
    }
    if ($mulai !== '' && $batas !== '') {
        return 'Dibuka ' . materi_waktu_teks($mulai) . ' &middot; ditutup '
            . materi_waktu_teks($batas);
    }
    if ($mulai !== '') {
        return 'Dibuka mulai ' . materi_waktu_teks($mulai);
    }
    return 'Batas waktu ' . materi_waktu_teks($batas);
}

// ============================================================================
// BERKAS
// ============================================================================

/** Ekstensi berkas sebuah kiriman (huruf kecil). */
function materi_ext(array $baris): string
{
    return strtolower(trim((string) ($baris['ext'] ?? '')));
}

/** Benar bila berkas kiriman berupa GAMBAR (boleh ditampilkan pada lembar cetak). */
function materi_gambar(array $baris): bool
{
    $ext = materi_ext($baris);
    return (string) ($baris['jenis_berkas'] ?? '') === 'gambar'
        && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
}

/** Label berkas untuk tampilan, mis. "PDF &middot; 512 KB". */
function materi_berkas_teks(array $baris): string
{
    $ext = strtoupper(materi_ext($baris));
    $ukuran = dok_ukuran((int) ($baris['ukuran'] ?? 0));
    return ($ext !== '' ? $ext : 'Berkas') . ' &middot; ' . $ukuran;
}

/** Nama berkas asli (atau keterangan pengganti bila kosong). */
function materi_nama_berkas(array $baris): string
{
    $nama = trim((string) ($baris['nama_berkas'] ?? ''));
    return $nama !== '' ? $nama : 'Berkas materi';
}

/**
 * Validasi berkas materi/tugas: HARUS salah satu dari MATERI_EKSTENSI, lalu diperiksa isinya lewat
 * `dok_validasi()` (aturan yang sama dengan modul Kurikulum/Perangkat).
 *
 * @return array{ok:bool,pesan:string,jenis:string,ext:string}
 */
function materi_validasi_berkas(string $namaAsli, int $ukuran, string $tmpPath): array
{
    $ext = strtolower((string) pathinfo($namaAsli, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, materi_ekstensi(), true)) {
        return ['ok' => false,
            'pesan' => 'Jenis berkas .' . ($ext !== '' ? $ext : '(tanpa ekstensi)')
                . ' belum didukung. Berkas yang dapat dikirim: ' . materi_ekstensi_teks() . '.',
            'jenis' => '', 'ext' => ''];
    }
    $v = dok_validasi($namaAsli, $ukuran, $tmpPath);
    if (empty($v['ok'])) {
        return $v;
    }
    $batas = min(MATERI_MAKS_BYTE, dok_batas_server());
    if ($ukuran > $batas) {
        return ['ok' => false,
            'pesan' => 'Ukuran berkas maksimal ' . dok_ukuran($batas) . ' (berkas Anda '
                . dok_ukuran($ukuran) . ').',
            'jenis' => '', 'ext' => ''];
    }
    return $v;
}

// ============================================================================
// DATA KIRIMAN
// ============================================================================

/** Satu kiriman materi/tugas (null bila tidak ada / bukan milik sekolah yang sedang dibuka). */
function materi_kirim_by_id(int $id): ?array
{
    $sid = sekolah_id();
    if ($id <= 0 || $sid <= 0) {
        return null;
    }
    return one('SELECT * FROM materi_kirim WHERE id = ? AND sekolah_id = ?', [$id, $sid]);
}

/** Satu kiriman MILIK guru tertentu (anti-IDOR: id + sekolah + guru). */
function materi_kirim_milik(int $id, int $guruId): ?array
{
    $sid = sekolah_id();
    if ($id <= 0 || $guruId <= 0 || $sid <= 0) {
        return null;
    }
    return one('SELECT * FROM materi_kirim WHERE id = ? AND sekolah_id = ? AND guru_id = ?',
        [$id, $sid, $guruId]);
}

/**
 * Daftar kiriman materi/tugas MILIK seorang guru (terbaru lebih dulu).
 *
 * @return array<int,array<string,mixed>>
 */
function materi_guru_list(int $guruId): array
{
    $sid = sekolah_id();
    if ($guruId <= 0 || $sid <= 0) {
        return [];
    }
    $baris = all('SELECT m.*, (SELECT COUNT(*) FROM materi_penerima p WHERE p.materi_id = m.id)
                         AS jumlah_penerima
                  FROM materi_kirim m
                  WHERE m.sekolah_id = ? AND m.guru_id = ?
                  ORDER BY m.dibuat DESC, m.id DESC', [$sid, $guruId]);
    foreach ($baris as $i => $b) {
        $baris[$i]['penerima'] = materi_penerima((int) $b['id']);
    }
    return $baris;
}

/** Jumlah kiriman materi/tugas milik guru (lencana menu). */
function materi_jumlah_guru(int $guruId): int
{
    $sid = sekolah_id();
    if ($guruId <= 0 || $sid <= 0) {
        return 0;
    }
    return (int) val('SELECT COUNT(*) FROM materi_kirim WHERE sekolah_id = ? AND guru_id = ?',
        [$sid, $guruId], 0);
}

/**
 * Daftar penerima satu kiriman (nama siswa & kelas = SNAPSHOT pada baris penerima).
 *
 * @return array<int,array<string,mixed>>
 */
function materi_penerima(int $materiId): array
{
    if ($materiId <= 0) {
        return [];
    }
    return all('SELECT * FROM materi_penerima WHERE materi_id = ? ORDER BY kelas_nama, siswa_nama',
        [$materiId]);
}

/** Daftar nama kelas penerima (unik) untuk ringkasan pada tabel guru. */
function materi_kelas_penerima(array $penerima): array
{
    $kelas = [];
    foreach ($penerima as $p) {
        $nama = trim((string) ($p['kelas_nama'] ?? ''));
        if ($nama !== '' && !in_array($nama, $kelas, true)) {
            $kelas[] = $nama;
        }
    }
    sort($kelas);
    return $kelas;
}

/**
 * Daftar materi/tugas yang DITUJUKAN kepada seorang siswa (terbaru lebih dulu), beserta nama guru &
 * mata pelajaran sebagai SNAPSHOT dari kirimannya.
 *
 * @return array<int,array<string,mixed>>
 */
function materi_siswa_list(int $siswaId): array
{
    $sid = sekolah_id();
    if ($siswaId <= 0 || $sid <= 0) {
        return [];
    }
    return all('SELECT m.*, p.kelas_nama AS kelas_penerima, (SELECT COUNT(*) FROM materi_penerima p2
                        WHERE p2.materi_id = m.id) AS jumlah_penerima
                FROM materi_penerima p
                JOIN materi_kirim m ON m.id = p.materi_id
                WHERE p.siswa_id = ? AND m.sekolah_id = ?
                ORDER BY m.dibuat DESC, m.id DESC', [$siswaId, $sid]);
}

/** Jumlah materi/tugas yang SEDANG dapat dibuka oleh siswa (lencana menu "Materi & Tugas"). */
function materi_jumlah_siswa(int $siswaId): int
{
    $n = 0;
    foreach (materi_siswa_list($siswaId) as $m) {
        if (materi_keadaan($m) === 'buka') {
            $n++;
        }
    }
    return $n;
}

/**
 * Baris kiriman yang ditujukan kepada seorang siswa (null bila bukan penerimanya).
 * Dipakai lembar cetak & tautan unduh supaya hak akses di periksa di SERVER.
 */
function materi_terkirim_untuk(int $materiId, int $siswaId): ?array
{
    $sid = sekolah_id();
    if ($materiId <= 0 || $siswaId <= 0 || $sid <= 0) {
        return null;
    }
    $baris = one('SELECT m.* FROM materi_penerima p JOIN materi_kirim m ON m.id = p.materi_id
                  WHERE p.materi_id = ? AND p.siswa_id = ? AND m.sekolah_id = ?',
        [$materiId, $siswaId, $sid]);
    return $baris ?: null;
}

// ============================================================================
// SISWA YANG DIAJAR GURU (sasaran "seluruh siswa yang saya ajar")
// ============================================================================

/**
 * Siswa yang DIAJAR seorang guru, dikelompokkan per kelas yang diampu.
 *
 * Sumber kelasnya `kelas_diampu()` (kelas perwalian ∪ kelas lain yang diajar — v65), dan hanya siswa
 * AKTIF yang disertakan. Bentuknya:
 *   [ ['kelas_id'=>1, 'kelas_nama'=>'VII-A', 'siswa'=>[ ['id','nama','nisn'], … ]], … ]
 *
 * @return array<int,array<string,mixed>>
 */
function materi_siswa_diajar(int $guruId = 0): array
{
    $guruId = $guruId > 0 ? $guruId : guru_id_saya();
    if ($guruId <= 0) {
        return [];
    }
    $hasil = [];
    foreach (kelas_diampu($guruId) as $k) {
        $kelasId = (int) $k['id'];
        $siswa = [];
        foreach (siswa_list($kelasId, '', true) as $s) {
            $siswa[] = ['id' => (int) $s['id'],
                        'nama' => (string) $s['nama'],
                        'nisn' => (string) ($s['nisn'] ?? '')];
        }
        if (!$siswa) {
            continue;
        }
        $hasil[] = ['kelas_id' => $kelasId, 'kelas_nama' => (string) $k['nama'], 'siswa' => $siswa];
    }
    return $hasil;
}

/** Peta id siswa => ['nama','nisn','kelas_id','kelas_nama'] untuk SELURUH siswa yang diajar guru. */
function materi_siswa_peta(int $guruId = 0): array
{
    $peta = [];
    foreach (materi_siswa_diajar($guruId) as $grup) {
        foreach ($grup['siswa'] as $s) {
            $peta[(int) $s['id']] = [
                'nama' => (string) $s['nama'],
                'nisn' => (string) $s['nisn'],
                'kelas_id' => (int) $grup['kelas_id'],
                'kelas_nama' => (string) $grup['kelas_nama'],
            ];
        }
    }
    return $peta;
}

/** Jumlah seluruh siswa yang diajar guru (untuk keterangan pilihan "seluruh siswa"). */
function materi_jumlah_diajar(int $guruId = 0): int
{
    return count(materi_siswa_peta($guruId));
}

// ============================================================================
// SIMPAN & HAPUS
// ============================================================================

/**
 * Simpan satu kiriman materi/tugas.
 *
 * Data yang diharapkan (sudah divalidasi pemanggil):
 *   guru_id, guru_nama, mapel_id, mapel_nama, kelas_teks, judul, keterangan,
 *   url, nama_berkas, jenis_berkas, ext, ukuran, buka_mulai, batas_waktu
 *
 * @param array<string,mixed> $data
 * @return int id kiriman baru (0 bila gagal)
 */
function materi_simpan(array $data): int
{
    $sid = sekolah_id();
    if ($sid <= 0) {
        return 0;
    }
    $now = date('Y-m-d H:i:s');
    q('INSERT INTO materi_kirim (sekolah_id, guru_id, guru_nama, mapel_id, mapel_nama, kelas_teks,
            judul, keterangan, url, nama_berkas, jenis_berkas, ext, ukuran, buka_mulai, batas_waktu,
            jml_penerima, dibuat, diubah)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?)',
        [$sid, (int) ($data['guru_id'] ?? 0), (string) ($data['guru_nama'] ?? ''),
         (int) ($data['mapel_id'] ?? 0), (string) ($data['mapel_nama'] ?? ''),
         (string) ($data['kelas_teks'] ?? ''), (string) ($data['judul'] ?? ''),
         (string) ($data['keterangan'] ?? ''), (string) ($data['url'] ?? ''),
         (string) ($data['nama_berkas'] ?? ''), (string) ($data['jenis_berkas'] ?? ''),
         strtolower((string) ($data['ext'] ?? '')), (int) ($data['ukuran'] ?? 0),
         $data['buka_mulai'] ?? null, $data['batas_waktu'] ?? null, $now, $now]);
    return (int) db()->lastInsertId();
}

/**
 * Simpan baris penerima sebuah kiriman + perbarui `jml_penerima`.
 *
 * `$siswa` = peta id => ['nama','nisn','kelas_id','kelas_nama'] (hasil materi_siswa_peta()), sehingga
 * nama & kelas tersimpan sebagai SNAPSHOT dan id di luar daftar itu TIDAK pernah masuk.
 *
 * @param array<int,array<string,mixed>> $siswa
 * @return int jumlah baris penerima yang tersimpan
 */
function materi_penerima_simpan(int $materiId, array $siswa): int
{
    $sid = sekolah_id();
    if ($materiId <= 0 || $sid <= 0 || !$siswa) {
        return 0;
    }
    $now = date('Y-m-d H:i:s');
    $n = 0;
    db()->exec('BEGIN IMMEDIATE');
    try {
        $st = db()->prepare('INSERT OR IGNORE INTO materi_penerima
            (materi_id, sekolah_id, siswa_id, siswa_nama, kelas_id, kelas_nama, dibuat)
            VALUES (?,?,?,?,?,?,?)');
        foreach ($siswa as $id => $s) {
            $st->execute([$materiId, $sid, (int) $id, (string) ($s['nama'] ?? ''),
                (int) ($s['kelas_id'] ?? 0), (string) ($s['kelas_nama'] ?? ''), $now]);
            $n++;
        }
        q('UPDATE materi_kirim SET jml_penerima = ?, diubah = ? WHERE id = ? AND sekolah_id = ?',
            [$n, $now, $materiId, $sid]);
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return $n;
}

/**
 * Hapus satu kiriman materi/tugas beserta baris penerimanya.
 * Baris penerima dihapus EKSPLISIT (cascade SQLite tidak selalu aktif).
 */
function materi_hapus(int $id): bool
{
    $sid = sekolah_id();
    if ($id <= 0 || $sid <= 0) {
        return false;
    }
    db()->exec('BEGIN IMMEDIATE');
    try {
        q('DELETE FROM materi_penerima WHERE materi_id = ?', [$id]);
        q('DELETE FROM materi_kirim WHERE id = ? AND sekolah_id = ?', [$id, $sid]);
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return true;
}

// ============================================================================
// HALAMAN GURU — "KIRIM MATERI/TUGAS KE SISWA"
// ============================================================================

/**
 * Halaman guru: formulir kirim materi/tugas + daftar kiriman yang sudah dikirim.
 *
 * Seluruh validasi (judul, mapel yang diampu, penerima, berkas, waktu) dilakukan DI SINI — halaman
 * `materi_kirim.php` hanya memanggil fungsi ini.
 */
function materi_halaman_guru(): void
{
    require_login();
    require_sekolah();
    if (!materi_boleh_kirim()) {
        tolak403('Menu Kirim Materi/Tugas ini khusus akun guru. Materi/tugas yang dikirim guru otomatis '
            . 'masuk ke akun siswa yang diajar (menu "Materi & Tugas").');
    }
    $guru = guru_saya();
    if (!$guru) {
        flash('Akun Anda belum ditautkan ke data guru, jadi belum dapat mengirim materi/tugas. '
            . 'Hubungi Admin Operator sekolah/madrasah Anda.', 'err');
        redirect('index.php');
    }
    $gid = (int) $guru['id'];
    $sid = sekolah_id();
    $namaGuru = (string) $guru['nama'];
    $mapelSaya = mapel_diampu($gid);
    $petaMapel = [];
    foreach ($mapelSaya as $m) {
        $petaMapel[(int) $m['id']] = (string) $m['nama'];
    }
    $grupSiswa = materi_siswa_diajar($gid);
    $petaSiswa = materi_siswa_peta($gid);
    $jmlDiajar = count($petaSiswa);
    $batasBerkas = min(MATERI_MAKS_BYTE, dok_batas_server());

    // ---------------------------------------------------------------- POST
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');

        if ($aksi === 'hapus') {
            $id = (int) ($_POST['id'] ?? 0);
            $k = materi_kirim_milik($id, $gid);
            if (!$k) {
                flash('Kiriman itu tidak ditemukan, atau bukan materi/tugas yang Anda kirim.', 'err');
            } else {
                materi_hapus($id);
                flash('Materi/Tugas "' . tetap_kata((string) $k['judul']) . '" dihapus.', 'warn');
            }
            redirect('materi_kirim.php#tabelMateri');
        }

        if ($aksi === 'kirim') {
            // PHP MEMBUANG seluruh $_POST/$_FILES bila kiriman melebihi post_max_size — tanpa
            // pemeriksaan ini gejalanya "tidak terjadi apa-apa".
            $panjang = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($panjang > 0 && !$_POST && !$_FILES) {
                flash('Ukuran kiriman melebihi batas server (' . dok_ukuran(dok_batas_server())
                    . '). Kirim berkas yang lebih kecil.', 'err');
                redirect('materi_kirim.php#formKirimMateri');
            }

            $judul = trim((string) ($_POST['judul'] ?? ''));
            $keterangan = trim((string) ($_POST['keterangan'] ?? ''));
            if (strlen($keterangan) > MATERI_KETERANGAN_MAKS) {
                $keterangan = substr($keterangan, 0, MATERI_KETERANGAN_MAKS);
            }
            $mapelId = (int) ($_POST['mapel'] ?? 0);
            $sasaran = (string) ($_POST['sasaran'] ?? '');
            $bukaMulai = materi_waktu_norm((string) ($_POST['buka_mulai'] ?? ''));
            $batasWaktu = materi_waktu_norm((string) ($_POST['batas_waktu'] ?? ''));

            if ($judul === '' || strlen($judul) < 3) {
                flash('Judul Materi/Tugas wajib diisi (minimal 3 huruf).', 'err');
                redirect('materi_kirim.php#formKirimMateri');
            }
            if (strlen($judul) > MATERI_JUDUL_MAKS) {
                $judul = substr($judul, 0, MATERI_JUDUL_MAKS);
            }
            // Anti-IDOR: mata pelajaran HARUS mapel yang diampu guru ini.
            if (!isset($petaMapel[$mapelId])) {
                flash('Mata pelajaran wajib dipilih dari mata pelajaran yang Anda ampu.', 'err');
                redirect('materi_kirim.php#formKirimMateri');
            }
            if (!in_array($sasaran, ['diajar', 'pilih'], true)) {
                flash('Pilih sasaran pengiriman: seluruh siswa yang Anda ajar atau siswa tertentu.', 'err');
                redirect('materi_kirim.php#formKirimMateri');
            }
            // Penerima ditentukan SEBELUM berkas diunggah supaya tidak ada unggahan yang terbuang.
            $penerima = [];
            if ($sasaran === 'diajar') {
                $penerima = $petaSiswa;
            } else {
                foreach ((array) ($_POST['siswa'] ?? []) as $v) {
                    $vid = (int) $v;
                    if ($vid <= 0) {
                        continue;
                    }
                    if (!isset($petaSiswa[$vid])) {
                        tolak403('Siswa yang dipilih bukan siswa yang Anda ajar.');
                    }
                    $penerima[$vid] = $petaSiswa[$vid];
                }
            }
            if (!$penerima) {
                flash($jmlDiajar <= 0
                    ? 'Belum ada siswa yang dapat dikirimi materi/tugas. Hubungi Admin Operator agar '
                        . 'data kelas, siswa, dan mata pelajaran Anda diisi lebih dulu.'
                    : 'Pilih minimal satu siswa penerima (centang namanya pada daftar), atau pilih '
                        . '"Seluruh siswa yang saya ajar".', 'err');
                redirect('materi_kirim.php#formKirimMateri');
            }
            if (count($penerima) > MATERI_PENERIMA_MAKS) {
                flash('Penerima terlalu banyak (' . count($penerima) . ' siswa) — maksimal '
                    . MATERI_PENERIMA_MAKS . ' siswa per pengiriman.', 'err');
                redirect('materi_kirim.php#formKirimMateri');
            }
            if ($bukaMulai !== null && $batasWaktu !== null && strtotime($batasWaktu) <= strtotime($bukaMulai)) {
                flash('Batas waktu harus SETELAH waktu "Dibuka mulai".', 'err');
                redirect('materi_kirim.php#formKirimMateri');
            }

            $berkas = $_FILES['berkas'] ?? null;
            if (!is_array($berkas) || (int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $kode = is_array($berkas) ? (int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
                $pesan = ($kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE)
                    ? 'Berkas terlalu besar untuk setelan server (maksimal ' . dok_ukuran(dok_batas_server()) . ').'
                    : 'Berkas materi/tugas wajib dipilih.';
                flash($pesan, 'err');
                redirect('materi_kirim.php#formKirimMateri');
            }
            $namaAsli = (string) ($berkas['name'] ?? 'materi');
            $ukuran = (int) ($berkas['size'] ?? 0);
            $tmp = (string) ($berkas['tmp_name'] ?? '');
            $v = materi_validasi_berkas($namaAsli, $ukuran, $tmp);
            if (empty($v['ok'])) {
                flash((string) ($v['pesan'] ?? 'Berkas tidak dapat dipakai.'), 'err');
                redirect('materi_kirim.php#formKirimMateri');
            }
            $bytes = @file_get_contents($tmp);
            if ($bytes === false || $bytes === '') {
                flash('Berkas tidak dapat dibaca server. Coba ulangi.', 'err');
                redirect('materi_kirim.php#formKirimMateri');
            }
            $unggah = media_upload($bytes, $namaAsli);
            if (empty($unggah['ok'])) {
                flash((string) ($unggah['pesan'] ?? 'Berkas gagal diunggah.'), 'err');
                redirect('materi_kirim.php#formKirimMateri');
            }

            $kelasTeks = implode(', ', materi_kelas_penerima($penerima));
            $materiId = materi_simpan([
                'guru_id' => $gid,
                'guru_nama' => $namaGuru,
                'mapel_id' => $mapelId,
                'mapel_nama' => $petaMapel[$mapelId],
                'kelas_teks' => $kelasTeks,
                'judul' => $judul,
                'keterangan' => $keterangan,
                'url' => (string) $unggah['url'],
                'nama_berkas' => $namaAsli,
                'jenis_berkas' => (string) $v['jenis'],
                'ext' => (string) $v['ext'],
                'ukuran' => $ukuran,
                'buka_mulai' => $bukaMulai,
                'batas_waktu' => $batasWaktu,
            ]);
            if ($materiId <= 0) {
                flash('Materi/Tugas gagal disimpan. Coba ulangi.', 'err');
                redirect('materi_kirim.php#formKirimMateri');
            }
            $n = materi_penerima_simpan($materiId, $penerima);
            flash('Materi/Tugas "' . tetap_kata($judul) . '" (' . tetap_kata($petaMapel[$mapelId])
                . ') berhasil dikirim ke ' . $n . ' siswa' . ($kelasTeks !== '' ? ' kelas '
                . tetap_kata($kelasTeks) : '') . ' dan sudah tampil pada menu "Materi & Tugas" akun '
                . 'siswa masing-masing.', 'ok');
            redirect('materi_kirim.php#tabelMateri');
        }

        flash('Aksi tidak dikenal.', 'err');
        redirect('materi_kirim.php');
    }

    // ---------------------------------------------------------------- tampilan
    $baris = materi_guru_list($gid);
    $jmlBuka = 0;
    foreach ($baris as $b) {
        if (materi_keadaan($b) === 'buka') {
            $jmlBuka++;
        }
    }
    $csrf = csrf_field();
    $namaSekolah = nama_sekolah_tampil();

    page_top('Kirim Materi/Tugas', 'Materi &amp; Tugas untuk Siswa &middot; ' . tetap_kata($namaSekolah));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('send', 16) ?> Kirim Materi/Tugas Ke Siswa</h2>
      <p>Kirim <b>materi pelajaran</b> atau <b>tugas</b> kepada siswa yang Anda ajar. Kiriman langsung
        tampil pada akun siswa masing-masing (menu <b>Materi &amp; Tugas</b>) beserta nama Anda, mata
        pelajaran, dan berkas yang dapat diunduh serta dicetak.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($mapelSaya) ?> mata pelajaran</span>
      <span class="chip"><?= (int) $jmlDiajar ?> siswa diajar</span>
      <span class="chip"><?= count($baris) ?> terkirim</span>
      <span class="chip"><?= (int) $jmlBuka ?> sedang dapat dibuka</span>
    </div>
  </div>

  <?php if (!$mapelSaya): ?>
    <div class="alert alert-warn"><?= ikon('info', 16) ?><span>Akun Anda belum memiliki <b>mata
      pelajaran</b> yang diampu, jadi materi/tugas belum dapat dikirim. Hubungi Admin Operator
      <?= tetap_kata('sekolah/madrasah') ?> agar mata pelajaran Anda ditetapkan pada menu Data Guru.</span></div>
  <?php endif; ?>
  <?php if ($jmlDiajar <= 0): ?>
    <div class="alert alert-warn"><?= ikon('info', 16) ?><span>Belum ada <b>siswa aktif</b> pada kelas
      yang Anda ampu, jadi belum ada penerima. Hubungi Admin Operator agar data <b>Kelas</b>,
      <b>Siswa</b>, dan kelas yang Anda ampu pada menu Data Guru diisi lebih dulu.</span></div>
  <?php endif; ?>

  <?php if ($mapelSaya && $jmlDiajar > 0): ?>
    <div class="divider"></div>
    <div class="section-title mb16"><?= ikon('upload', 15) ?> Formulir Materi/Tugas</div>
    <?php // Form multipart BIASA (bukan fetch): unggahan tetap bekerja walau skrip gagal dimuat. ?>
    <form method="post" enctype="multipart/form-data" class="form-grid" id="formKirimMateri">
      <?= $csrf ?>
      <input type="hidden" name="aksi" value="kirim">
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="judul">Judul Materi/Tugas *</label>
          <input type="text" id="judul" name="judul" required maxlength="<?= (int) MATERI_JUDUL_MAKS ?>"
                 placeholder="mis. Ringkasan Bab 3 — Pertemuan 2">
        </div>
        <div>
          <label class="f" for="mapel">Mata Pelajaran *</label>
          <select id="mapel" name="mapel" required>
            <?php foreach ($mapelSaya as $m): $mid = (int) $m['id']; ?>
              <option value="<?= $mid ?>"><?= e((string) $m['nama']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Hanya mata pelajaran yang Anda ampu yang dapat dipilih.</div>
        </div>
      </div>

      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="berkas">Berkas Materi/Tugas *</label>
          <input type="file" id="berkas" name="berkas" required style="padding:8px"
                 accept="<?= e('.' . implode(',.', materi_ekstensi())) ?>">
          <div class="hint">Diterima: <b><?= e(materi_ekstensi_teks()) ?></b> (pdf, Word .docx, atau
            gambar). Ukuran maksimal <b><?= e(dok_ukuran($batasBerkas)) ?></b> per berkas.</div>
        </div>
        <div>
          <label class="f" for="keterangan">Keterangan / Petunjuk</label>
          <textarea id="keterangan" name="keterangan" rows="3" maxlength="<?= (int) MATERI_KETERANGAN_MAKS ?>"
                    placeholder="mis. Kerjakan pada buku tulis, kumpulkan paling lambat saat jam pelajaran."></textarea>
        </div>
      </div>

      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="buka_mulai">Dibuka Mulai (boleh dikosongkan)</label>
          <input type="datetime-local" id="buka_mulai" name="buka_mulai">
          <div class="hint">Kosongkan bila materi/tugas <b>langsung</b> dapat dibuka siswa.</div>
        </div>
        <div>
          <label class="f" for="batas_waktu">Batas Waktu Dibuka (boleh dikosongkan)</label>
          <input type="datetime-local" id="batas_waktu" name="batas_waktu">
          <div class="hint">Setelah waktu ini siswa <b>tidak dapat lagi</b> membuka, mengunduh, atau
            mencetak materi/tugas ini. Kosongkan bila tanpa batas.</div>
        </div>
      </div>

      <div class="divider"></div>
      <div>
        <label class="f" for="sasaran">Penerima *</label>
        <select id="sasaran" name="sasaran" required>
          <option value="diajar">Seluruh siswa yang saya ajar (<?= (int) $jmlDiajar ?> siswa)</option>
          <option value="pilih">Pilih siswa tertentu (centang pada daftar di bawah)</option>
        </select>
        <div class="hint">Pilihan <b>Seluruh siswa yang saya ajar</b> mengabaikan centang di bawah —
          semua siswa aktif pada kelas yang Anda ampu langsung menerima materi/tugas ini.</div>
      </div>

      <div class="materi-pilih">
        <div class="section-title mb8"><?= ikon('student', 15) ?> Pilih Siswa Tertentu</div>
        <?php foreach ($grupSiswa as $grup): ?>
          <div class="materi-pilih-kelas">
            <div class="materi-pilih-kepala">
              <b><?= tetap_kata((string) $grup['kelas_nama']) ?></b>
              <span class="tbl-sub"><?= count($grup['siswa']) ?> siswa</span>
            </div>
            <div class="materi-pilih-isi">
              <?php foreach ($grup['siswa'] as $s): ?>
                <label class="materi-cek">
                  <input type="checkbox" name="siswa[]" value="<?= (int) $s['id'] ?>">
                  <span><?= tetap_kata((string) $s['nama']) ?>
                    <?php if ((string) $s['nisn'] !== ''): ?>
                      <small>NISN <?= e((string) $s['nisn']) ?></small>
                    <?php endif; ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="flex">
        <button class="btn btn-primary" type="submit"><?= ikon('send', 16) ?> Kirim ke Akun Siswa</button>
        <span class="hint" style="align-self:center">Berkas disimpan pada penyimpanan platform; yang
          tercatat di aplikasi hanya tautannya.</span>
      </div>
    </form>
  <?php endif; ?>

  <div class="divider"></div>
  <div class="section-title mb8" id="tabelMateri"><?= ikon('doc', 15) ?> Materi/Tugas yang Saya Kirim
    (<?= count($baris) ?>)</div>
  <?php if (!$baris): ?>
    <div class="empty"><?= ikon('send', 34) ?>
      <h3>Belum ada materi/tugas yang dikirim</h3>
      <p>Isi formulir <b>Materi/Tugas</b> di atas untuk mengirim materi atau tugas pertama Anda.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl tbl-materi">
        <thead>
          <tr>
            <th class="ctr" style="width:42px">No</th>
            <th>Judul &amp; Mata Pelajaran</th>
            <th style="width:190px">Penerima</th>
            <th style="width:170px">Berkas</th>
            <th style="width:200px">Waktu Dibuka</th>
            <th class="ctr" style="width:110px">Keadaan</th>
            <th style="width:120px">Dikirim</th>
            <th class="ctr" style="width:90px">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($baris as $b): $n++;
            $keadaan = materi_keadaan($b);
            $terima = (array) ($b['penerima'] ?? []);
            $kelasTerima = materi_kelas_penerima($terima);
        ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td>
              <b><?= tetap_kata((string) $b['judul']) ?></b>
              <span class="tbl-sub"><?= tetap_kata((string) $b['mapel_nama']) ?></span>
              <?php if (trim((string) $b['keterangan']) !== ''): ?>
                <span class="tbl-sub"><?= tetap_kata((string) $b['keterangan']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <b><?= count($terima) ?> siswa</b>
              <span class="tbl-sub"><?= $kelasTerima ? tetap_kata(implode(', ', $kelasTerima)) : '-' ?></span>
              <?php if ($terima): ?>
                <details class="materi-rinci">
                  <summary>Lihat nama</summary>
                  <div class="materi-rinci-isi">
                    <?php foreach ($terima as $p): ?>
                      <div><?= tetap_kata((string) $p['siswa_nama']) ?>
                        <span class="mut xs"><?= tetap_kata((string) $p['kelas_nama']) ?></span></div>
                    <?php endforeach; ?>
                  </div>
                </details>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn btn-ghost btn-sm" href="<?= e((string) $b['url']) ?>" target="_blank" rel="noopener">
                <?= ikon('download', 13) ?> Unduh</a>
              <span class="tbl-sub"><?= materi_berkas_teks($b) ?></span>
              <span class="tbl-sub"><?= tetap_kata(materi_nama_berkas($b)) ?></span>
            </td>
            <td><span class="sm"><?= materi_waktu_ringkas($b) ?></span></td>
            <td class="ctr"><span class="pill <?= e(materi_keadaan_pil($keadaan)) ?>">
              <?= e(materi_keadaan_label($keadaan)) ?></span></td>
            <td class="nowrap"><?= waktu_id((string) $b['dibuat']) ?></td>
            <td class="ctr">
              <form method="post" class="row-form"
                    onsubmit="return confirm(<?= e(json_encode(
                        'Hapus materi/tugas "' . (string) $b['judul'] . '"? Kiriman ini akan hilang dari '
                        . 'akun ' . count($terima) . ' siswa penerimanya.', JSON_UNESCAPED_UNICODE)) ?>)">
                <?= $csrf ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 13) ?> Hapus</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint mt16">Keadaan <b>Dapat Dibuka</b> = siswa sudah bisa membuka, mengunduh, dan
      mencetak materi/tugas ini. <b>Belum Dibuka</b> = menunggu waktu "Dibuka mulai".
      <b>Sudah Ditutup</b> = sudah melewati batas waktu yang Anda tetapkan.</p>
  <?php endif; ?>
</div>
    <?php
    page_bottom();
}

// ============================================================================
// HALAMAN SISWA — "MATERI & TUGAS"
// ============================================================================

/**
 * Halaman siswa: daftar materi/tugas yang ditujukan kepadanya, lengkap dengan nama guru pengirim,
 * mata pelajaran, berkas (Unduh), dan lembar cetak (Cetak).
 *
 * Materi yang BELUM waktunya dibuka atau SUDAH melewati batas waktu tetap ditampilkan (supaya siswa
 * tahu ada materi/tugas yang belum/tidak dapat dibuka), tetapi tautan unduh & cetak TIDAK dicetak —
 * dan `cetak_materi.php` tetap menolaknya di server lewat `materi_keadaan()`.
 */
function materi_halaman_siswa(): void
{
    $sw = require_siswa();
    $baris = materi_siswa_list((int) $sw['id']);
    $jml = ['buka' => 0, 'belum' => 0, 'tutup' => 0];
    foreach ($baris as $b) {
        $k = materi_keadaan($b);
        $jml[$k] = ($jml[$k] ?? 0) + 1;
    }

    page_top('Materi & Tugas', (string) $sw['nama'] . ' &middot; NISN '
        . ($sw['nisn'] !== '' ? (string) $sw['nisn'] : '-')
        . ($sw['kelas_nama'] !== null ? ' &middot; ' . (string) $sw['kelas_nama'] : ''));
    ?>
<div class="alert alert-ok mb16">
  <?= ikon('info', 16) ?>
  <span>Materi dan tugas di halaman ini dikirim oleh <b>guru mata pelajaran</b> Anda. Berkasnya dapat
    <b>diunduh</b> dan <b>dicetak</b> selama materi/tugas itu masih dapat dibuka — setelah batas
    waktunya lewat, berkas tidak dapat dibuka lagi.</span>
</div>

<div class="grid g4">
  <div class="card stat card-tight">
    <div class="stat-ic s"><?= ikon('doc', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= count($baris) ?></div>
      <div class="stat-lbl">Seluruh materi/tugas</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic g"><?= ikon('check', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $jml['buka'] ?></div>
      <div class="stat-lbl">Dapat dibuka</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('clock', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $jml['belum'] ?></div>
      <div class="stat-lbl">Belum dibuka</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('x', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $jml['tutup'] ?></div>
      <div class="stat-lbl">Sudah ditutup</div></div>
  </div>
</div>

<div class="card mt16">
  <div class="card-head">
    <div>
      <h2><?= ikon('send', 16) ?> Materi &amp; Tugas dari Guru</h2>
      <p>Urut dari yang terbaru. Setiap kartu memuat nama guru pengirim, mata pelajaran, berkas, dan
        waktu berlakunya.</p>
    </div>
  </div>

  <?php if (!$baris): ?>
    <div class="empty"><?= ikon('doc', 34) ?>
      <h3>Belum ada materi/tugas</h3>
      <p>Belum ada guru yang mengirim materi atau tugas kepada Anda. Halaman ini akan terisi sendiri
        begitu guru mengirimkannya dari akunnya.</p>
    </div>
  <?php else: ?>
    <div class="materi-daftar">
      <?php foreach ($baris as $b): $keadaan = materi_keadaan($b); ?>
        <div class="materi-kartu">
          <div class="materi-kartu-kepala">
            <div>
              <div class="materi-judul"><?= tetap_kata((string) $b['judul']) ?></div>
              <div class="materi-meta">
                <span><?= ikon('user', 13) ?> Guru: <b><?= tetap_kata((string) $b['guru_nama']) ?></b></span>
                <span><?= ikon('book', 13) ?> Mata Pelajaran:
                  <b><?= tetap_kata((string) $b['mapel_nama']) ?></b></span>
                <span><?= ikon('calendar', 13) ?> Diterima: <?= waktu_id((string) $b['dibuat']) ?></span>
              </div>
            </div>
            <span class="pill <?= e(materi_keadaan_pil($keadaan)) ?>"><?= e(materi_keadaan_label($keadaan)) ?></span>
          </div>

          <?php if (trim((string) $b['keterangan']) !== ''): ?>
            <div class="materi-ket"><?= tetap_kata((string) $b['keterangan']) ?></div>
          <?php endif; ?>

          <div class="materi-berkas">
            <div class="materi-berkas-info">
              <span class="stat-ic"><?= ikon('doc', 18) ?></span>
              <div>
                <b><?= tetap_kata(materi_nama_berkas($b)) ?></b>
                <span class="tbl-sub"><?= materi_berkas_teks($b) ?></span>
              </div>
            </div>
            <div class="flex">
              <?php if ($keadaan === 'buka'): ?>
                <a class="btn btn-primary btn-sm" href="<?= e((string) $b['url']) ?>"
                   target="_blank" rel="noopener"><?= ikon('download', 14) ?> Unduh</a>
                <a class="btn btn-ghost btn-sm" href="cetak_materi.php?id=<?= (int) $b['id'] ?>"
                   target="_blank" rel="noopener"><?= ikon('print', 14) ?> Cetak</a>
              <?php else: ?>
                <span class="btn btn-ghost btn-sm btn-mati" aria-disabled="true">
                  <?= ikon($keadaan === 'belum' ? 'clock' : 'x', 14) ?>
                  <?= $keadaan === 'belum' ? 'Belum dapat dibuka' : 'Sudah ditutup' ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="materi-waktu"><?= ikon('clock', 13) ?> <?= materi_waktu_ringkas($b) ?>
            <?php if ($keadaan === 'belum'): ?>
              — materi/tugas ini dapat dibuka mulai waktu tersebut.
            <?php elseif ($keadaan === 'tutup'): ?>
              — batas waktunya sudah lewat, hubungi guru mata pelajaran bila perlu dibuka kembali.
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
    <?php
    page_bottom();
}
