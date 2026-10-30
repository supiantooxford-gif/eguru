<?php
/**
 * MODUL "KIRIM PERANGKAT" — skema v48 / v1.67.0.
 *
 * Permintaan pemilik: *"Menu Perangkat Guru yang ada di Super Admin dihapus saja dengan isinya. Menu
 * ini sudah tidak dibutuhkan. Sekarang buatkan fitur Kirim Perangkat di akun guru (Seperti Kalender
 * Pendidikan, Analisis Pekan Efektif, Capaian Pembelajaran, Alur Tujuan Pembelajaran, RPP/Modul Ajar.
 * Nama-nama dokumen ini di isi oleh Super Admin, bisa ditambah atau dikurangi. File yang terkirim
 * masuk di akun Kepala sekolah (Kepala Sekolah bisa meninjau dokumen tersebut dengan: Dokumen
 * Disetujui, Dokumen Direvisi, Dokumen Ditolak sekaligus bisa mendownload), dan Pengawas (Pengawas
 * hanya lihat dan download). Hasil tinjauan kepala sekolah terkirim ke akun guru"*.
 *
 * Keputusan pemilik (jangan diubah tanpa bertanya):
 *   1. menu & halaman "Perangkat Guru" yang lama (halaman PUBLIK + KODE DOWNLOAD) **DIHAPUS**;
 *   2. daftar Nama Dokumen = **SATU daftar GLOBAL** (tabel `perangkat_jenis`), diisi Super Admin
 *      lewat menu **"Jenis Perangkat"** — bisa ditambah maupun dikurangi;
 *   3. satu berkas **per Nama Dokumen × per mata pelajaran yang diampu** → mengunggah ulang pada sel
 *      yang sama MENGGANTIKAN berkas lama dan mengembalikan statusnya ke "Belum Ditinjau";
 *   4. memakai **Tahun Ajaran** (tersimpan pada tiap kiriman, jadi perangkat tahun lalu tetap ada).
 *
 * Alur lengkapnya:
 *   • **GURU** (`perangkat_saya.php`, menu "Kirim Perangkat") mengunggah berkas dan melihat hasil
 *     tinjauan kepala sekolah (status + keterangan/alasan + nama peninjau + waktunya);
 *   • **KEPALA SEKOLAH/MADRASAH** (`perangkat_masuk.php`, menu "Perangkat Masuk") + Super Admin dalam
 *     konteks meninjau tiap kiriman (Disetujui / Direvisi / Ditolak) beserta keterangannya, dan dapat
 *     mengunduh berkasnya;
 *   • **PENGAWAS** membuka halaman yang SAMA (`perangkat_masuk.php`) tetapi hanya melihat & mengunduh.
 *     Akun pengawas memang hanya-lihat, ditegakkan `gerbang_pengawas()` (berkas ini terdaftar pada
 *     `pengawas_berkas_boleh()` dan **tidak** pada `pengawas_berkas_post()` → setiap POST dijawab 403).
 *
 * Berkas diunggah lewat **FORM MULTIPART BIASA** ke media proxy platform DI SISI SERVER
 * (`media_upload()`); token unggah tidak pernah sampai ke peramban dan yang disimpan ke basis data
 * hanya URL CDN hasil respons. Aturan jenis/ukuran berkas memakai penolong modul Kurikulum
 * (`dok_validasi()`, `dok_ukuran()`, `dok_batas_server()`, `dok_ekstensi_semua()`) supaya tidak ada
 * dua aturan berbeda di aplikasi ini.
 *
 * 🚨 **Teks yang DIKETIK PENGGUNA dibungkus `tetap_kata()`** saat dicetak (nama dokumen, keterangan,
 * catatan tinjauan, nama berkas, nama guru) supaya tidak ikut penyesuaian istilah "Sekolah" →
 * "Madrasah" pada madrasah — teks itu tulisan orang, bukan label aplikasi.
 */
declare(strict_types=1);

require_once __DIR__ . '/dokumen.php';

// ------------------------------------------------------------------ hak akses

/** Siapa yang boleh MENGIRIM perangkat: akun GURU (pemilik fitur ini). */
function boleh_kirim_perangkat(): bool
{
    return is_guru();
}

/** Siapa yang boleh MENINJAU kiriman perangkat: kepala sekolah/madrasah (+ Super Admin di konteks). */
function boleh_tinjau_perangkat(): bool
{
    return is_kepala() || (is_super() && current_sekolah() !== null);
}

// ------------------------------------------------------------------ keadaan tinjauan

/** Daftar keadaan tinjauan (kode => label) — lihat PERANGKAT_STATUS. */
function perangkat_status_list(): array
{
    return PERANGKAT_STATUS;
}

/** Apakah sebuah kode keadaan sah? */
function perangkat_status_sah(string $status): bool
{
    return array_key_exists($status, PERANGKAT_STATUS);
}

/** Nilai keadaan yang DINORMALKAN (kode tak dikenal → 'menunggu', pola status tinjauan dokumen). */
function perangkat_status_awal(?string $status): string
{
    $status = (string) $status;
    return perangkat_status_sah($status) ? $status : 'menunggu';
}

/** Label keadaan tinjauan (mis. "Dokumen Disetujui"). */
function perangkat_status_label(?string $status): string
{
    $status = perangkat_status_awal($status);
    return (string) PERANGKAT_STATUS[$status];
}

/** Kelas pil (tema) untuk sebuah keadaan tinjauan. */
function perangkat_status_pil(?string $status): string
{
    switch (perangkat_status_awal($status)) {
        case 'disetujui':
            return 'pill-ok';
        case 'revisi':
            return 'pill-uji';
        case 'ditolak':
            return 'pill-D';
        default:
            return 'pill-warn';
    }
}

/** Nama ikon untuk sebuah keadaan tinjauan. */
function perangkat_status_ikon(?string $status): string
{
    switch (perangkat_status_awal($status)) {
        case 'disetujui':
            return 'check';
        case 'revisi':
            return 'edit';
        case 'ditolak':
            return 'x';
        default:
            return 'clock';
    }
}

/** Keterangan/alasan WAJIB diisi untuk keadaan ini? (revisi & ditolak — itu "alasannya"). */
function perangkat_status_alasan_wajib(?string $status): bool
{
    return in_array(perangkat_status_awal($status), PERANGKAT_STATUS_ALASAN_WAJIB, true);
}

// ------------------------------------------------------------------ daftar nama dokumen (GLOBAL)

/**
 * Daftar Nama Dokumen perangkat (GLOBAL — satu daftar untuk semua sekolah/madrasah).
 *
 * @param bool $aktifSaja true = hanya yang diaktifkan Super Admin (dipakai pilihan & kolom tampilan)
 * @return array<int,array<string,mixed>>
 */
function perangkat_jenis_list(bool $aktifSaja = false): array
{
    if ($aktifSaja) {
        return all('SELECT * FROM perangkat_jenis WHERE aktif = 1 ORDER BY urutan, id');
    }
    return all('SELECT * FROM perangkat_jenis ORDER BY urutan, id');
}

/** Baris satu Nama Dokumen (null bila tidak ada). */
function perangkat_jenis_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return one('SELECT * FROM perangkat_jenis WHERE id = ?', [$id]);
}

/** Jumlah Nama Dokumen (aktif saja bila $aktifSaja) — dipakai lencana menu & ringkasan. */
function perangkat_jenis_jumlah(bool $aktifSaja = false): int
{
    return (int) val('SELECT COUNT(*) FROM perangkat_jenis'
        . ($aktifSaja ? ' WHERE aktif = 1' : ''), [], 0);
}

/** Apakah nama itu sudah dipakai Nama Dokumen lain (tanpa peduli besar-kecil huruf)? */
function perangkat_jenis_nama_terpakai(string $nama, int $kecualiId = 0): bool
{
    $nama = trim($nama);
    if ($nama === '') {
        return false;
    }
    return (int) val('SELECT COUNT(*) FROM perangkat_jenis WHERE LOWER(nama) = LOWER(?) AND id <> ?',
        [$nama, $kecualiId], 0) > 0;
}

/** Nomor urut untuk Nama Dokumen baru (selalu kelipatan 10, diletakkan paling akhir). */
function perangkat_jenis_urutan_baru(): int
{
    $maks = (int) val('SELECT COALESCE(MAX(urutan), 0) FROM perangkat_jenis', [], 0);
    return $maks + 10;
}

/** Tambah Nama Dokumen baru. Kembaliannya ['ok'=>bool,'pesan'=>string,'id'=>int]. */
function perangkat_jenis_tambah(string $nama, bool $aktif = true): array
{
    $nama = trim($nama);
    if ($nama === '') {
        return ['ok' => false, 'pesan' => 'Nama Dokumen wajib diisi.', 'id' => 0];
    }
    if (perangkat_jenis_nama_terpakai($nama)) {
        return ['ok' => false, 'pesan' => 'Nama Dokumen "' . $nama . '" sudah ada. Pakai nama lain.', 'id' => 0];
    }
    $nama = substr($nama, 0, PERANGKAT_NAMA_MAKS);
    $now = date('Y-m-d H:i:s');
    q('INSERT INTO perangkat_jenis (nama, urutan, aktif, dibuat, diubah) VALUES (?,?,?,?,?)',
        [$nama, perangkat_jenis_urutan_baru(), $aktif ? 1 : 0, $now, $now]);
    return ['ok' => true, 'pesan' => 'Nama Dokumen "' . $nama . '" ditambahkan.', 'id' => (int) db()->lastInsertId()];
}

/** Ubah nama sebuah Nama Dokumen. */
function perangkat_jenis_ubah(int $id, string $nama): array
{
    $j = perangkat_jenis_by_id($id);
    if (!$j) {
        return ['ok' => false, 'pesan' => 'Nama Dokumen itu tidak ditemukan.'];
    }
    $nama = substr(trim($nama), 0, PERANGKAT_NAMA_MAKS);
    if ($nama === '') {
        return ['ok' => false, 'pesan' => 'Nama Dokumen wajib diisi.'];
    }
    if (perangkat_jenis_nama_terpakai($nama, $id)) {
        return ['ok' => false, 'pesan' => 'Nama Dokumen "' . $nama . '" sudah dipakai baris lain.'];
    }
    q('UPDATE perangkat_jenis SET nama = ?, diubah = ? WHERE id = ?', [$nama, date('Y-m-d H:i:s'), $id]);
    return ['ok' => true, 'pesan' => 'Nama Dokumen diubah menjadi "' . $nama . '".'];
}

/** Aktifkan / nonaktifkan sebuah Nama Dokumen (nonaktif = hilang dari pilihan, kiriman lama tetap). */
function perangkat_jenis_toggle(int $id): array
{
    $j = perangkat_jenis_by_id($id);
    if (!$j) {
        return ['ok' => false, 'pesan' => 'Nama Dokumen itu tidak ditemukan.'];
    }
    $baru = (int) $j['aktif'] === 1 ? 0 : 1;
    q('UPDATE perangkat_jenis SET aktif = ?, diubah = ? WHERE id = ?',
        [$baru, date('Y-m-d H:i:s'), $id]);
    return ['ok' => true, 'pesan' => 'Nama Dokumen "' . (string) $j['nama'] . '" '
        . ($baru === 1 ? 'diaktifkan.' : 'dinonaktifkan — tidak lagi muncul pada pilihan guru.')];
}

/**
 * Hapus sebuah Nama Dokumen.
 *
 * Kiriman guru yang memakai nama itu **TIDAK dihapus**: relasi `jenis_id` menjadi NULL dan namanya
 * tetap tampil dari snapshot `nama_jenis` (jadi riwayat & status tinjauannya tetap terbaca).
 */
function perangkat_jenis_hapus(int $id): array
{
    $j = perangkat_jenis_by_id($id);
    if (!$j) {
        return ['ok' => false, 'pesan' => 'Nama Dokumen itu tidak ditemukan.'];
    }
    $pakai = (int) val('SELECT COUNT(*) FROM perangkat_kirim WHERE jenis_id = ?', [$id], 0);
    q('DELETE FROM perangkat_jenis WHERE id = ?', [$id]);
    return ['ok' => true, 'pesan' => 'Nama Dokumen "' . (string) $j['nama'] . '" dihapus.'
        . ($pakai > 0
            ? ' ' . $pakai . ' kiriman guru yang memakai nama itu tetap tersimpan (namanya diambil dari catatan kiriman).'
            : '')];
}

/** Geser urutan sebuah Nama Dokumen (±1 langkah). Menulis ulang urutan menjadi kelipatan 10. */
function perangkat_jenis_geser(int $id, int $arah): array
{
    $daftar = perangkat_jenis_list(false);
    $pos = -1;
    foreach ($daftar as $i => $j) {
        if ((int) $j['id'] === $id) {
            $pos = $i;
            break;
        }
    }
    if ($pos < 0) {
        return ['ok' => false, 'pesan' => 'Nama Dokumen itu tidak ditemukan.'];
    }
    $tujuan = $pos + ($arah < 0 ? -1 : 1);
    if ($tujuan < 0 || $tujuan >= count($daftar)) {
        return ['ok' => false, 'pesan' => 'Nama Dokumen itu sudah berada di urutan paling '
            . ($arah < 0 ? 'atas.' : 'bawah.')];
    }
    $a = $daftar[$pos];
    $daftar[$pos] = $daftar[$tujuan];
    $daftar[$tujuan] = $a;
    $now = date('Y-m-d H:i:s');
    $urut = 10;
    db()->exec('BEGIN IMMEDIATE');
    try {
        foreach ($daftar as $j) {
            q('UPDATE perangkat_jenis SET urutan = ?, diubah = ? WHERE id = ?',
                [$urut, $now, (int) $j['id']]);
            $urut += 10;
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return ['ok' => true, 'pesan' => 'Urutan Nama Dokumen diperbarui.'];
}

// ------------------------------------------------------------------ data kiriman

/**
 * Syarat & parameter penyaring kiriman perangkat (dipakai halaman kepala/pengawas).
 *
 * Nilai yang tidak sah/asing **diabaikan** (bukan 403) — pola halaman rekap lain di aplikasi ini.
 *
 * @param array<string,mixed> $get biasanya $_GET
 * @return array{0:string,1:array<int,mixed>,2:array<string,mixed>} [klausa WHERE, parameter, filter bersih]
 */
function perangkat_filter(array $get): array
{
    $f = [
        'ta'    => (int) ($get['ta'] ?? 0),
        'guru'  => (int) ($get['guru'] ?? 0),
        'mapel' => (int) ($get['mapel'] ?? 0),
        'jenis' => (int) ($get['jenis'] ?? 0),
        'status' => perangkat_status_awal((string) ($get['status'] ?? '')),
        'status_ada' => trim((string) ($get['status'] ?? '')) !== '',
        'cari'  => trim((string) ($get['cari'] ?? '')),
    ];
    $w = ['k.sekolah_id = ?'];
    $p = [sekolah_id()];
    if ($f['ta'] > 0 && ta_by_id($f['ta'])) {
        $w[] = 'k.ta_id = ?';
        $p[] = $f['ta'];
    } else {
        $f['ta'] = 0;
    }
    if ($f['guru'] > 0) {
        $w[] = 'k.guru_id = ?';
        $p[] = $f['guru'];
    }
    if ($f['mapel'] > 0 && mapel_by_id($f['mapel'])) {
        $w[] = 'k.mapel_id = ?';
        $p[] = $f['mapel'];
    } else {
        $f['mapel'] = 0;
    }
    if ($f['jenis'] > 0 && perangkat_jenis_by_id($f['jenis'])) {
        $w[] = 'k.jenis_id = ?';
        $p[] = $f['jenis'];
    } else {
        $f['jenis'] = 0;
    }
    if ($f['status_ada'] && perangkat_status_sah($f['status'])) {
        $w[] = 'k.status = ?';
        $p[] = $f['status'];
    } elseif ($f['status_ada']) {
        $f['status_ada'] = false;
    }
    if ($f['cari'] !== '') {
        $w[] = '(k.nama_jenis LIKE ? OR k.keterangan LIKE ? OR k.nama_berkas LIKE ?'
            . ' OR g.nama LIKE ? OR m.nama LIKE ? OR k.catatan LIKE ?)';
        $like = '%' . $f['cari'] . '%';
        array_push($p, $like, $like, $like, $like, $like, $like);
    }
    return [implode(' AND ', $w), $p, $f];
}

/**
 * Daftar kiriman perangkat SATU SEKOLAH (dipakai halaman kepala & pengawas).
 *
 * @param array<string,mixed> $f hasil perangkat_filter()
 * @return array<int,array<string,mixed>>
 */
function perangkat_kirim_sekolah(array $f): array
{
    return all('SELECT k.*, g.nama AS guru_nama, m.nama AS mapel_nama,
                       COALESCE(j.nama, k.nama_jenis) AS jenis_nama,
                       COALESCE(t.nama, k.ta_nama) AS ta_teks,
                       (SELECT u.nama FROM users u WHERE u.id = k.ditinjau_oleh) AS peninjau_nama
                  FROM perangkat_kirim k
                  JOIN guru g ON g.id = k.guru_id
                  JOIN mapel m ON m.id = k.mapel_id
                  LEFT JOIN perangkat_jenis j ON j.id = k.jenis_id
                  LEFT JOIN tahun_ajaran t ON t.id = k.ta_id
                 WHERE ' . $f[0] . '
                 ORDER BY g.nama, m.urutan, m.nama, j.urutan, k.nama_jenis, k.id', $f[1]);
}

/** Daftar kiriman milik SEORANG GURU (halaman "Kirim Perangkat"), bisa disaring per tahun ajaran. */
function perangkat_kirim_guru(int $guruId, int $taId = 0): array
{
    if ($guruId <= 0) {
        return [];
    }
    $w = ['k.guru_id = ?'];
    $p = [$guruId];
    if ($taId > 0) {
        $w[] = 'k.ta_id = ?';
        $p[] = $taId;
    }
    return all('SELECT k.*, m.nama AS mapel_nama,
                       COALESCE(j.nama, k.nama_jenis) AS jenis_nama,
                       COALESCE(t.nama, k.ta_nama) AS ta_teks,
                       (SELECT u.nama FROM users u WHERE u.id = k.ditinjau_oleh) AS peninjau_nama
                  FROM perangkat_kirim k
                  JOIN mapel m ON m.id = k.mapel_id
                  LEFT JOIN perangkat_jenis j ON j.id = k.jenis_id
                  LEFT JOIN tahun_ajaran t ON t.id = k.ta_id
                 WHERE ' . implode(' AND ', $w) . '
                 ORDER BY COALESCE(t.nama, k.ta_nama) DESC, m.urutan, m.nama, k.nama_jenis', $p);
}

/** Satu baris kiriman menurut id — dibatasi sekolah yang sedang aktif (anti-IDOR). */
function perangkat_kirim_by_id(int $id, int $sekolahId = 0): ?array
{
    $sid = $sekolahId > 0 ? $sekolahId : sekolah_id();
    if ($id <= 0 || $sid <= 0) {
        return null;
    }
    return one('SELECT k.*, g.nama AS guru_nama, m.nama AS mapel_nama,
                       COALESCE(j.nama, k.nama_jenis) AS jenis_nama,
                       COALESCE(t.nama, k.ta_nama) AS ta_teks
                  FROM perangkat_kirim k
                  JOIN guru g ON g.id = k.guru_id
                  JOIN mapel m ON m.id = k.mapel_id
                  LEFT JOIN perangkat_jenis j ON j.id = k.jenis_id
                  LEFT JOIN tahun_ajaran t ON t.id = k.ta_id
                 WHERE k.id = ? AND k.sekolah_id = ?', [$id, $sid]);
}

/** Satu baris kiriman milik seorang guru (dipakai pembatalan/penghapusan oleh guru sendiri). */
function perangkat_kirim_milik(int $id, int $guruId): ?array
{
    if ($id <= 0 || $guruId <= 0) {
        return null;
    }
    return one('SELECT * FROM perangkat_kirim WHERE id = ? AND guru_id = ?', [$id, $guruId]);
}

/**
 * Simpan (kirim atau GANTI) satu berkas perangkat pada sel (guru × mata pelajaran × tahun ajaran ×
 * nama dokumen).
 *
 * Mengunggah ulang pada sel yang sama MENGGANTIKAN berkas lama dan mengembalikan status tinjauan ke
 * **'menunggu'** (catatan & peninjau dikosongkan) — kepala sekolah perlu meninjau berkas yang baru.
 */
function perangkat_kirim_simpan(int $guruId, int $mapelId, int $taId, int $jenisId,
                                array $isi, int $sekolahId = 0): int
{
    $sid = $sekolahId > 0 ? $sekolahId : sekolah_id();
    $ta = $taId > 0 ? one('SELECT * FROM tahun_ajaran WHERE id = ?', [$taId]) : null;
    $jenis = perangkat_jenis_by_id($jenisId);
    $now = date('Y-m-d H:i:s');
    $namaJenis = $jenis ? (string) $jenis['nama'] : (string) ($isi['nama_jenis'] ?? 'Perangkat');
    $ada = one('SELECT id FROM perangkat_kirim WHERE guru_id = ? AND mapel_id = ? AND ta_id = ? AND jenis_id = ?',
        [$guruId, $mapelId, $taId, $jenisId]);
    if ($ada) {
        q('UPDATE perangkat_kirim SET sekolah_id = ?, nama_jenis = ?, keterangan = ?, url = ?,
                 nama_berkas = ?, jenis_berkas = ?, ukuran = ?, status = ?, catatan = NULL,
                 ditinjau_oleh = NULL, ditinjau_pada = NULL, diubah = ?
            WHERE id = ?',
            [$sid, $namaJenis, (string) ($isi['keterangan'] ?? ''), (string) $isi['url'],
             (string) ($isi['nama_berkas'] ?? ''), (string) ($isi['jenis_berkas'] ?? ''),
             (int) ($isi['ukuran'] ?? 0), 'menunggu', $now, (int) $ada['id']]);
        return (int) $ada['id'];
    }
    q('INSERT INTO perangkat_kirim (sekolah_id, guru_id, mapel_id, ta_id, ta_nama, jenis_id, nama_jenis,
             keterangan, url, nama_berkas, jenis_berkas, ukuran, status, dikirim, diubah)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$sid, $guruId, $mapelId, $taId, $ta ? (string) $ta['nama'] : '', $jenisId, $namaJenis,
         (string) ($isi['keterangan'] ?? ''), (string) $isi['url'], (string) ($isi['nama_berkas'] ?? ''),
         (string) ($isi['jenis_berkas'] ?? ''), (int) ($isi['ukuran'] ?? 0), 'menunggu', $now, $now]);
    return (int) db()->lastInsertId();
}

/** Hapus satu kiriman perangkat milik sendiri (guru) atau oleh pengelola yang berhak. */
function perangkat_kirim_hapus(int $id, int $sekolahId = 0): bool
{
    $k = perangkat_kirim_by_id($id, $sekolahId);
    if (!$k) {
        return false;
    }
    q('DELETE FROM perangkat_kirim WHERE id = ?', [$id]);
    return true;
}

/**
 * Tinjau satu kiriman: Disetujui / Direvisi / Ditolak, atau kembali ke "Belum Ditinjau".
 *
 * Aturan (mengikuti status tinjauan dokumen pada aplikasi Supervisi Guru):
 *   • keterangan/alasan **WAJIB** untuk `revisi` & `ditolak`; opsional untuk `disetujui`;
 *   • memilih `menunggu` = **MEMBATALKAN** tinjauan → catatan & peninjau dikosongkan.
 *
 * @return array{ok:bool,pesan:string,status:string,catatan:string}
 */
function perangkat_tinjau(int $id, string $status, string $catatan, int $sekolahId = 0): array
{
    $k = perangkat_kirim_by_id($id, $sekolahId);
    if (!$k) {
        return ['ok' => false, 'pesan' => 'Kiriman perangkat itu tidak ditemukan pada sekolah/madrasah ini.', 'status' => '', 'catatan' => ''];
    }
    $status = trim($status);
    if (!perangkat_status_sah($status)) {
        return ['ok' => false, 'pesan' => 'Keadaan tinjauan tidak dikenal.', 'status' => '', 'catatan' => ''];
    }
    $catatan = trim($catatan);
    if (strlen($catatan) > PERANGKAT_CATATAN_MAKS) {
        $catatan = substr($catatan, 0, PERANGKAT_CATATAN_MAKS);
    }
    if (perangkat_status_alasan_wajib($status) && $catatan === '') {
        return ['ok' => false,
                'pesan' => 'Keterangan/alasan wajib diisi bila dokumen dinyatakan '
                    . ($status === 'revisi' ? 'DIREVISI.' : 'DITOLAK.'),
                'status' => '', 'catatan' => ''];
    }
    $now = date('Y-m-d H:i:s');
    $uid = (int) (current_user()['id'] ?? 0);
    if ($status === 'menunggu') {
        q('UPDATE perangkat_kirim SET status = ?, catatan = NULL, ditinjau_oleh = NULL,
                 ditinjau_pada = NULL, diubah = ? WHERE id = ?', ['menunggu', $now, (int) $k['id']]);
        return ['ok' => true, 'status' => 'menunggu', 'catatan' => '',
                'pesan' => 'Tinjauan dibatalkan — dokumen "' . $k['jenis_nama'] . '" kembali berkeadaan '
                    . 'Belum Ditinjau.'];
    }
    q('UPDATE perangkat_kirim SET status = ?, catatan = ?, ditinjau_oleh = ?, ditinjau_pada = ?, diubah = ?
        WHERE id = ?', [$status, $catatan, $uid, $now, $now, (int) $k['id']]);
    return ['ok' => true, 'status' => $status, 'catatan' => $catatan,
            'pesan' => strtoupper(perangkat_status_label($status)) . ' — ' . $k['jenis_nama'] . ' ('
                . $k['guru_nama'] . ' · ' . $k['mapel_nama'] . ').'];
}

// ------------------------------------------------------------------ ringkasan & kelengkapan

/** Ringkasan keadaan kiriman dari sederet baris: ['kirim','menunggu','disetujui','revisi','ditolak']. */
function perangkat_ringkas(array $baris): array
{
    $r = ['kirim' => count($baris), 'menunggu' => 0, 'disetujui' => 0, 'revisi' => 0, 'ditolak' => 0];
    foreach ($baris as $k) {
        $s = perangkat_status_awal((string) ($k['status'] ?? ''));
        $r[$s]++;
    }
    return $r;
}

/** Jumlah perangkat yang sudah dikirim GURU yang sedang masuk (lencana menu "Kirim Perangkat"). */
function perangkat_jumlah_saya(): int
{
    $gid = guru_id_saya();
    if ($gid <= 0) {
        return 0;
    }
    return (int) val('SELECT COUNT(*) FROM perangkat_kirim WHERE guru_id = ?', [$gid], 0);
}

/** Jumlah kiriman yang BELUM ditinjau pada sekolah aktif (lencana menu "Perangkat Masuk"). */
function perangkat_jumlah_menunggu(int $sekolahId = 0): int
{
    $sid = $sekolahId > 0 ? $sekolahId : sekolah_id();
    if ($sid <= 0) {
        return 0;
    }
    return (int) val("SELECT COUNT(*) FROM perangkat_kirim WHERE sekolah_id = ? AND status = 'menunggu'",
        [$sid], 0);
}

/**
 * Kelengkapan perangkat seorang guru pada satu tahun ajaran:
 * mapel_id => ['id','nama','sel' => [jenis_id => baris|null]].
 *
 * Dipakai matriks "Kelengkapan Perangkat Saya" yang menunjukkan sel mana yang belum dikirim.
 *
 * @return array<int,array<string,mixed>>
 */
function perangkat_kelengkapan(int $guruId, int $taId): array
{
    if ($guruId <= 0) {
        return [];
    }
    $jenis = perangkat_jenis_list(true);
    $ada = [];
    foreach (perangkat_kirim_guru($guruId, $taId) as $k) {
        $ada[(int) $k['mapel_id']][(int) $k['jenis_id']] = $k;
    }
    $out = [];
    foreach (mapel_diampu($guruId) as $m) {
        $mid = (int) $m['id'];
        $sel = [];
        foreach ($jenis as $j) {
            $sel[(int) $j['id']] = $ada[$mid][(int) $j['id']] ?? null;
        }
        $out[$mid] = ['id' => $mid, 'nama' => (string) $m['nama'], 'sel' => $sel];
    }
    return $out;
}

/** Hitung berapa sel yang sudah terisi & total sel dari hasil perangkat_kelengkapan(). */
function perangkat_kelengkapan_hitung(array $kelengkapan): array
{
    $terisi = 0;
    $total = 0;
    foreach ($kelengkapan as $row) {
        foreach ($row['sel'] as $b) {
            $total++;
            if ($b !== null) {
                $terisi++;
            }
        }
    }
    return ['terisi' => $terisi, 'total' => $total,
            'persen' => $total > 0 ? (int) round($terisi / $total * 100) : 0];
}

/**
 * Hasil tinjauan TERBARU untuk seorang guru (dipakai kartu dasbor guru: "hasil tinjauan kepala
 * sekolah terkirim ke akun guru" — permintaan pemilik). Hanya baris yang sudah ditinjau.
 */
function perangkat_tinjauan_terbaru(int $guruId, int $limit = 3): array
{
    if ($guruId <= 0) {
        return [];
    }
    $limit = max(1, min(20, $limit));
    return all('SELECT k.*, m.nama AS mapel_nama,
                       COALESCE(j.nama, k.nama_jenis) AS jenis_nama,
                       (SELECT u.nama FROM users u WHERE u.id = k.ditinjau_oleh) AS peninjau_nama
                  FROM perangkat_kirim k
                  JOIN mapel m ON m.id = k.mapel_id
                  LEFT JOIN perangkat_jenis j ON j.id = k.jenis_id
                 WHERE k.guru_id = ? AND k.status <> \'menunggu\'
                 ORDER BY k.ditinjau_pada DESC, k.id DESC LIMIT ' . $limit, [$guruId]);
}

// ------------------------------------------------------------------ CATATAN PENGAWAS (skema v52)

/**
 * Siapa yang boleh MEMBERI CATATAN perangkat: akun **Pengawas Sekolah/Madrasah**.
 *
 * Permintaan pemilik: *"Pada akun pengawas tambahkan menu Perangkat Guru … Pengawas bisa melihat,
 * memberikan catatan setiap perangkat dan mendownload"*.
 */
function boleh_catat_perangkat(): bool
{
    return is_pengawas();
}

/**
 * Daftar catatan pengawas untuk SATU kiriman perangkat (urut waktu dibuat).
 *
 * Catatan ini **terpisah** dari `perangkat_kirim.catatan` milik kepala sekolah (pilihan pemilik) dan
 * dapat dibaca guru pengirim maupun kepala sekolah/madrasah.
 *
 * @return array<int,array<string,mixed>>
 */
function perangkat_catatan_list(int $kirimId): array
{
    if ($kirimId <= 0) {
        return [];
    }
    return all('SELECT c.*, (SELECT u.nama FROM users u WHERE u.id = c.pengawas_id) AS pengawas_nama
                  FROM perangkat_catatan c
                 WHERE c.kirim_id = ?
                 ORDER BY c.dibuat, c.id', [$kirimId]);
}

/**
 * Peta `kirim_id => daftar catatan` untuk SEKUMPULAN kiriman (SATU query).
 *
 * Dipakai tabel-tabel perangkat supaya tidak menembak basis data sekali per baris (N+1).
 *
 * @param array<int,mixed> $kirimIds
 * @return array<int,array<int,array<string,mixed>>>
 */
function perangkat_catatan_peta(array $kirimIds): array
{
    $ids = [];
    foreach ($kirimIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $rows = all('SELECT c.*, (SELECT u.nama FROM users u WHERE u.id = c.pengawas_id) AS pengawas_nama
                   FROM perangkat_catatan c
                  WHERE c.kirim_id IN (' . $in . ')
                  ORDER BY c.dibuat, c.id', array_values($ids));
    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['kirim_id']][] = $r;
    }
    return $out;
}

/** Catatan milik seorang pengawas pada satu kiriman (null bila belum pernah menulis). */
function perangkat_catatan_milik(int $kirimId, int $pengawasId): ?array
{
    if ($kirimId <= 0 || $pengawasId <= 0) {
        return null;
    }
    return one('SELECT * FROM perangkat_catatan WHERE kirim_id = ? AND pengawas_id = ?',
        [$kirimId, $pengawasId]);
}

/**
 * Simpan / perbarui / hapus catatan pengawas pada sebuah perangkat.
 *
 * Aturan (pilihan pemilik — jangan diubah tanpa bertanya):
 *   • hanya akun **pengawas** binaan sekolah itu yang boleh menulis (anti-IDOR);
 *   • satu pengawas = SATU catatan per perangkat, jadi menyimpan ulang = MEMPERBARUI catatannya;
 *   • **isi kosong = menghapus** catatan pengawas itu (bukan error), sehingga pengawas dapat
 *     mencabut catatannya sendiri;
 *   • catatan ini **tidak menyentuh** status tinjauan kepala sekolah.
 *
 * @return array{ok:bool,pesan:string}
 */
function perangkat_catatan_simpan(int $kirimId, string $isi): array
{
    if (!boleh_catat_perangkat()) {
        return ['ok' => false, 'pesan' => 'Hanya akun Pengawas Sekolah/Madrasah yang dapat memberikan '
            . 'catatan pada perangkat guru.'];
    }
    $uid = (int) (current_user()['id'] ?? 0);
    $sid = sekolah_id();
    if ($uid <= 0 || $sid <= 0) {
        return ['ok' => false, 'pesan' => 'Sekolah/madrasah yang dilihat belum dipilih.'];
    }
    if (!pengawas_punya($uid, $sid)) {
        return ['ok' => false, 'pesan' => 'Sekolah/madrasah itu bukan binaan Anda.'];
    }
    $k = perangkat_kirim_by_id($kirimId, $sid);
    if (!$k) {
        return ['ok' => false, 'pesan' => 'Perangkat itu tidak ditemukan pada sekolah/madrasah ini.'];
    }
    $namaJenis = tetap_kata((string) ($k['jenis_nama'] ?? 'perangkat'));
    $namaGuru = tetap_kata((string) ($k['guru_nama'] ?? 'guru'));
    $isi = trim($isi);
    if (strlen($isi) > PERANGKAT_CATATAN_PENGAWAS_MAKS) {
        $isi = substr($isi, 0, PERANGKAT_CATATAN_PENGAWAS_MAKS);
    }
    $now = date('Y-m-d H:i:s');
    $ada = perangkat_catatan_milik($kirimId, $uid);
    if ($isi === '') {
        if (!$ada) {
            return ['ok' => false, 'pesan' => 'Catatan masih kosong — tulis catatan lebih dulu, atau '
                . 'biarkan kosong bila memang tidak ingin memberi catatan.'];
        }
        q('DELETE FROM perangkat_catatan WHERE id = ?', [(int) $ada['id']]);
        return ['ok' => true, 'pesan' => 'Catatan Anda pada ' . $namaJenis . ' (' . $namaGuru
            . ') dihapus.'];
    }
    if ($ada) {
        q('UPDATE perangkat_catatan SET isi = ?, diubah = ? WHERE id = ?', [$isi, $now, (int) $ada['id']]);
        return ['ok' => true, 'pesan' => 'Catatan Anda pada ' . $namaJenis . ' (' . $namaGuru
            . ') diperbarui — guru & kepala sekolah/madrasah dapat membacanya.'];
    }
    q('INSERT INTO perangkat_catatan (kirim_id, pengawas_id, isi, dibuat, diubah) VALUES (?,?,?,?,?)',
        [$kirimId, $uid, $isi, $now, $now]);
    return ['ok' => true, 'pesan' => 'Catatan untuk ' . $namaJenis . ' (' . $namaGuru
        . ') terkirim — guru & kepala sekolah/madrasah dapat membacanya.'];
}

/**
 * Blok CATATAN PENGAWAS untuk satu perangkat (dipakai halaman pengawas, guru, dan kepala sekolah).
 *
 * Formulir hanya dicetak untuk akun pengawas (`boleh_catat_perangkat()`); guru & kepala sekolah
 * hanya membaca. Tanpa JavaScript: formulir POST biasa di dalam sel tabel (pola `pk-tinjau`).
 *
 * @param array<int,array<string,mixed>> $catatan hasil perangkat_catatan_list()/peta()
 * @param array<string,mixed>            $kirim   baris kiriman (butuh id)
 */
function perangkat_catatan_tampil(array $catatan, array $kirim): void
{
    $kid = (int) ($kirim['id'] ?? 0);
    $boleh = boleh_catat_perangkat();
    $uid = (int) (current_user()['id'] ?? 0);
    $milikSendiri = '';
    foreach ($catatan as $c) {
        if ((int) $c['pengawas_id'] === $uid) {
            $milikSendiri = (string) $c['isi'];
            break;
        }
    }
    if (!$catatan) {
        echo '<span class="hint xs pw-kosong">Belum ada catatan pengawas.</span>';
    } else {
        echo '<div class="pw-catatan-list">';
        foreach ($catatan as $c) {
            $sendiri = (int) $c['pengawas_id'] === $uid;
            ?>
        <div class="pw-catatan-item<?= $sendiri ? ' milik' : '' ?>">
          <div class="pw-catatan-kepala">
            <b><?= e(tetap_kata((string) ($c['pengawas_nama'] ?? 'Pengawas'))) ?></b>
            <?php if ($sendiri): ?><span class="pill pill-ok">Catatan Anda</span><?php endif; ?>
            <span class="tbl-sub"><?= e(waktu_id_polos((string) ($c['diubah'] ?? $c['dibuat'] ?? ''))) ?></span>
          </div>
          <div class="pw-catatan-isi"><?= nl2br(e(tetap_kata((string) $c['isi']))) ?></div>
        </div>
            <?php
        }
        echo '</div>';
    }
    if (!$boleh || $kid <= 0) {
        return;
    }
    ?>
    <form method="post" class="pw-catatan-form">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="catatan">
      <input type="hidden" name="id" value="<?= $kid ?>">
      <input type="hidden" name="kembali" value="<?= e(perangkat_kembali_q()) ?>">
      <label class="f" for="catatanPw<?= $kid ?>">Catatan Pengawas</label>
      <textarea id="catatanPw<?= $kid ?>" name="isi" rows="2"
                maxlength="<?= (int) PERANGKAT_CATATAN_PENGAWAS_MAKS ?>"
                placeholder="mis. RPP sudah baik, lengkapi penilaian sikap pada pertemuan berikutnya."><?= e($milikSendiri) ?></textarea>
      <button class="btn btn-primary btn-sm" type="submit"><?= ikon('save', 13) ?> Simpan Catatan</button>
      <div class="hint xs">Catatan ini dibaca guru &amp; kepala sekolah/madrasah, terpisah dari status
        tinjauan kepala sekolah. Kosongkan lalu simpan untuk menghapus catatan Anda.</div>
    </form>
    <?php
}

/**
 * Ringkasan perangkat sebuah sekolah/madrasah binaan (dipakai halaman PEMILIH BINAAN pengawas).
 *
 * @return array{guru_sekolah:int,guru:int,mapel:int,kirim:int,menunggu:int,catatan:int}
 */
function perangkat_ringkas_binaan(int $sekolahId, int $pengawasId = 0): array
{
    $kosong = ['guru_sekolah' => 0, 'guru' => 0, 'mapel' => 0, 'kirim' => 0, 'menunggu' => 0, 'catatan' => 0];
    if ($sekolahId <= 0) {
        return $kosong;
    }
    return [
        'guru_sekolah' => (int) val('SELECT COUNT(*) FROM guru WHERE sekolah_id = ? AND aktif = 1',
            [$sekolahId], 0),
        'guru'     => (int) val('SELECT COUNT(DISTINCT guru_id) FROM perangkat_kirim WHERE sekolah_id = ?',
            [$sekolahId], 0),
        'mapel'    => (int) val('SELECT COUNT(DISTINCT mapel_id) FROM perangkat_kirim WHERE sekolah_id = ?',
            [$sekolahId], 0),
        'kirim'    => (int) val('SELECT COUNT(*) FROM perangkat_kirim WHERE sekolah_id = ?', [$sekolahId], 0),
        'menunggu' => (int) val("SELECT COUNT(*) FROM perangkat_kirim WHERE sekolah_id = ?
                                  AND status = 'menunggu'", [$sekolahId], 0),
        'catatan'  => $pengawasId > 0
            ? (int) val('SELECT COUNT(*) FROM perangkat_catatan c
                           JOIN perangkat_kirim k ON k.id = c.kirim_id
                          WHERE k.sekolah_id = ? AND c.pengawas_id = ?', [$sekolahId, $pengawasId], 0)
            : 0,
    ];
}

// ------------------------------------------------------------------ tampilan bersama

/**
 * Tabel ringkas daftar kiriman perangkat (dipakai halaman kepala/pengawas & halaman guru).
 *
 * @param array<int,array<string,mixed>> $baris
 * @param bool $tampilGuru  true = tambahkan kolom "Nama Guru" (halaman kepala/pengawas)
 * @param bool $bolehHapus  true = sediakan tombol Hapus (halaman guru / kepala)
 */
function perangkat_tabel(array $baris, bool $tampilGuru, bool $bolehHapus = false): void
{
    $kolom = 6 + ($tampilGuru ? 1 : 0) + ($bolehHapus ? 1 : 0);
    // CATATAN PENGAWAS (skema v52) — diambil sekali untuk semua baris supaya tidak N+1.
    $catatanPw = perangkat_catatan_peta(array_column($baris, 'id'));
    ?>
<div class="table-wrap" id="tabelPerangkat">
  <table class="tbl tbl-perangkat">
    <thead>
      <tr>
        <th class="ctr pk-no">No</th>
        <?php if ($tampilGuru): ?><th class="pk-guru">Nama Guru</th><?php endif; ?>
        <th class="pk-mapel">Mata Pelajaran</th>
        <th class="pk-jenis">Nama Dokumen</th>
        <th class="ctr pk-ta">Tahun Ajaran</th>
        <th class="pk-berkas">Berkas</th>
        <th class="pk-status">Status Tinjauan</th>
        <?php if ($bolehHapus): ?><th class="ctr pk-aksi">Tindakan</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php $n = 0; foreach ($baris as $k): $n++;
        $status = perangkat_status_awal((string) $k['status']);
        $tautan = tautan_aman((string) ($k['url'] ?? ''));
        $namaBerkas = trim((string) ($k['nama_berkas'] ?? '')) !== '' ? (string) $k['nama_berkas'] : 'berkas';
        $ext = strtolower((string) pathinfo($namaBerkas, PATHINFO_EXTENSION));
        $jenisBerkas = (string) ($k['jenis_berkas'] ?? dok_jenis_ext($ext));
    ?>
      <tr>
        <td class="ctr"><?= $n ?></td>
        <?php if ($tampilGuru): ?>
          <td class="pk-guru-cell"><b><?= e(tetap_kata((string) ($k['guru_nama'] ?? '-'))) ?></b></td>
        <?php endif; ?>
        <td class="pk-mapel-cell"><?= e(tetap_kata((string) ($k['mapel_nama'] ?? '-'))) ?></td>
        <td>
          <b><?= e(tetap_kata((string) ($k['jenis_nama'] ?? $k['nama_jenis'] ?? '-'))) ?></b>
          <?php if (trim((string) ($k['keterangan'] ?? '')) !== ''): ?>
            <span class="tbl-sub"><?= nl2br(e(tetap_kata((string) $k['keterangan']))) ?></span>
          <?php endif; ?>
        </td>
        <td class="ctr nowrap"><?= e((string) ($k['ta_teks'] !== '' ? $k['ta_teks'] : '-')) ?></td>
        <td class="pk-berkas-cell">
          <?php if ($tautan !== ''): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($tautan) ?>" target="_blank" rel="noopener noreferrer">
              <?= ikon('download', 14) ?> <?= e(mb_substr_fallback($namaBerkas, 0, 26)) ?>
            </a>
            <span class="tbl-sub"><?= e(dok_label_jenis($jenisBerkas, $ext)) ?> &middot;
              <?= e(dok_ukuran((int) $k['ukuran'])) ?></span>
          <?php else: ?>
            <span class="hint xs">tautan tidak sah</span>
          <?php endif; ?>
        </td>
        <td class="pk-status-cell">
          <span class="pill <?= e(perangkat_status_pil($status)) ?>">
            <?= ikon(perangkat_status_ikon($status), 12) ?> <?= e(perangkat_status_label($status)) ?></span>
          <?php if (trim((string) ($k['catatan'] ?? '')) !== ''): ?>
            <div class="pk-catatan"><?= nl2br(e(tetap_kata((string) $k['catatan']))) ?></div>
          <?php endif; ?>
          <?php if ($status !== 'menunggu' && trim((string) ($k['peninjau_nama'] ?? '')) !== ''): ?>
            <div class="tbl-sub">oleh <?= e(tetap_kata((string) $k['peninjau_nama'])) ?>
              &middot; <?= e(waktu_id_polos((string) ($k['ditinjau_pada'] ?? ''))) ?></div>
          <?php endif; ?>
          <?php // CATATAN PENGAWAS (skema v52) — terpisah dari status tinjauan kepala sekolah, dan
                // memang DIBACA guru pengirim (pilihan pemilik). ?>
          <?php if (!empty($catatanPw[(int) $k['id']])): ?>
            <div class="pk-catatan-pw">
              <div class="pk-catatan-pw-judul"><?= ikon('eye', 12) ?> Catatan Pengawas</div>
              <?php perangkat_catatan_tampil($catatanPw[(int) $k['id']], $k); ?>
            </div>
          <?php endif; ?>
        </td>
        <?php if ($bolehHapus): ?>
          <td class="ctr pk-aksi">
            <form method="post" data-confirm="Hapus kiriman perangkat &quot;<?= e((string) ($k['jenis_nama'] ?? '')) ?>&quot; untuk mata pelajaran <?= e((string) ($k['mapel_nama'] ?? '')) ?>?">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Hapus</button>
            </form>
          </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$baris): ?>
      <tr><td colspan="<?= $kolom ?>" class="ctr">
        <div class="empty" style="padding:26px 14px">
          <?= ikon('folder', 34) ?>
          <h3>Belum ada perangkat yang dikirim</h3>
          <p>Perangkat yang dikirim guru akan tampil di sini beserta keadaan tinjuannya.</p>
        </div>
      </td></tr>
    <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <th colspan="2" class="ctr">Jumlah</th>
        <th colspan="<?= $kolom - 2 ?>" class="ctr">
          <span class="tbl-sub"><?= count($baris) ?> kiriman perangkat</span>
        </th>
      </tr>
    </tfoot>
  </table>
</div>
    <?php
}

/** Pemotong teks yang aman walau ekstensi mbstring tidak tersedia (CLI sandbox). */
function mb_substr_fallback(string $teks, int $mulai, int $panjang): string
{
    if (function_exists('mb_substr')) {
        return (string) mb_substr($teks, $mulai, $panjang);
    }
    $potong = substr($teks, $mulai, $panjang);
    return $panjang < strlen($teks) ? $potong . '…' : $potong;
}

/** Kartu ringkasan keadaan tinjauan (empat kotak). */
function perangkat_kartu_ringkas(array $ringkas): void
{
    ?>
<div class="grid g4" style="gap:16px;margin-top:16px">
  <div class="card stat card-tight" style="margin:0">
    <div class="stat-ic o"><?= ikon('clock', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['menunggu'] ?></div>
      <div class="stat-lbl">Belum ditinjau</div></div>
  </div>
  <div class="card stat card-tight" style="margin:0">
    <div class="stat-ic g"><?= ikon('check', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['disetujui'] ?></div>
      <div class="stat-lbl">Dokumen disetujui</div></div>
  </div>
  <div class="card stat card-tight" style="margin:0">
    <div class="stat-ic"><?= ikon('edit', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['revisi'] ?></div>
      <div class="stat-lbl">Dokumen direvisi</div></div>
  </div>
  <div class="card stat card-tight" style="margin:0">
    <div class="stat-ic s"><?= ikon('x', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['ditolak'] ?></div>
      <div class="stat-lbl">Dokumen ditolak</div></div>
  </div>
</div>
    <?php
}

// ------------------------------------------------------------------ halaman GURU: KIRIM PERANGKAT

/**
 * Halaman **"Kirim Perangkat"** (`perangkat_saya.php`) — akun GURU.
 *
 * Guru mengunggah perangkat pembelajaran per **Nama Dokumen × mata pelajaran yang diampu × Tahun
 * Ajaran**, melihat kelengkapannya, dan membaca hasil tinjauan kepala sekolah.
 */
function perangkat_saya_halaman(): void
{
    require_login();
    require_sekolah();
    if (!boleh_kirim_perangkat()) {
        tolak403('Menu Kirim Perangkat ini khusus akun guru. Kiriman perangkat guru ditinjau kepala '
            . 'sekolah/madrasah pada menu "Perangkat Masuk".');
    }
    $guru = guru_saya();
    if (!$guru) {
        flash('Akun Anda belum ditautkan ke data guru, jadi belum dapat mengirim perangkat. '
            . 'Hubungi Admin Operator sekolah/madrasah Anda.', 'err');
        redirect('index.php');
    }
    $gid = (int) $guru['id'];
    $sid = sekolah_id();
    $jenis = perangkat_jenis_list(true);
    $mapelSaya = mapel_diampu($gid);
    $batasKirim = dok_batas_server();
    $petaMapel = [];
    foreach ($mapelSaya as $m) {
        $petaMapel[(int) $m['id']] = (string) $m['nama'];
    }
    $petaJenis = [];
    foreach ($jenis as $j) {
        $petaJenis[(int) $j['id']] = (string) $j['nama'];
    }

    // ---------------------------------------------------------------- POST
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');

        if ($aksi === 'hapus') {
            $id = (int) ($_POST['id'] ?? 0);
            $k = perangkat_kirim_milik($id, $gid);
            if (!$k) {
                flash('Kiriman itu tidak ditemukan, atau bukan perangkat yang Anda kirim.', 'err');
            } else {
                perangkat_kirim_hapus($id, $sid);
                flash('Kiriman perangkat "' . tetap_kata((string) $k['nama_jenis']) . '" dihapus.', 'warn');
            }
            redirect('perangkat_saya.php#tabelPerangkat');
        }

        if ($aksi === 'kirim') {
            // PHP MEMBUANG seluruh $_POST/$_FILES bila kiriman melebihi post_max_size — tanpa
            // pemeriksaan ini gejalanya "tidak terjadi apa-apa" (pelajaran dari foto bukti supervisi).
            $panjang = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($panjang > 0 && !$_POST && !$_FILES) {
                flash('Ukuran kiriman melebihi batas server (' . dok_ukuran(dok_batas_server())
                    . '). Kirim berkas yang lebih kecil.', 'err');
                redirect('perangkat_saya.php#formKirimPerangkat');
            }

            $mapelId = (int) ($_POST['mapel'] ?? 0);
            $taId = (int) ($_POST['ta'] ?? 0);
            $jenisId = (int) ($_POST['jenis'] ?? 0);
            $keterangan = trim((string) ($_POST['keterangan'] ?? ''));
            if (strlen($keterangan) > PERANGKAT_KETERANGAN_MAKS) {
                $keterangan = substr($keterangan, 0, PERANGKAT_KETERANGAN_MAKS);
            }
            // Anti-IDOR: mata pelajaran HARUS milik guru ini, nama dokumen HARUS ada & aktif, dan
            // tahun ajaran harus milik sekolah ini.
            if (!isset($petaMapel[$mapelId])) {
                tolak403('Mata pelajaran itu bukan mata pelajaran yang Anda ampu.');
            }
            if (!isset($petaJenis[$jenisId])) {
                flash('Nama Dokumen yang dipilih tidak dikenal atau sudah dinonaktifkan Admin.', 'err');
                redirect('perangkat_saya.php#formKirimPerangkat');
            }
            $ta = ta_by_id($taId);
            if (!$ta) {
                flash('Tahun Ajaran wajib dipilih.', 'err');
                redirect('perangkat_saya.php#formKirimPerangkat');
            }

            $berkas = $_FILES['berkas'] ?? null;
            if (!is_array($berkas) || (int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $kode = is_array($berkas) ? (int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
                $pesan = ($kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE)
                    ? 'Berkas terlalu besar untuk setelan server (maksimal ' . dok_ukuran(dok_batas_server()) . ').'
                    : 'Berkas perangkat wajib dipilih.';
                flash($pesan, 'err');
                redirect('perangkat_saya.php#formKirimPerangkat');
            }
            $namaAsli = (string) ($berkas['name'] ?? 'perangkat');
            $ukuran = (int) ($berkas['size'] ?? 0);
            $tmp = (string) ($berkas['tmp_name'] ?? '');
            $v = dok_validasi($namaAsli, $ukuran, $tmp);
            if (!$v['ok']) {
                flash($v['pesan'], 'err');
                redirect('perangkat_saya.php#formKirimPerangkat');
            }
            $bytes = @file_get_contents($tmp);
            if ($bytes === false || $bytes === '') {
                flash('Berkas tidak dapat dibaca server. Coba ulangi.', 'err');
                redirect('perangkat_saya.php#formKirimPerangkat');
            }
            $unggah = media_upload($bytes, $namaAsli);
            if (empty($unggah['ok'])) {
                flash((string) ($unggah['pesan'] ?? 'Berkas gagal diunggah.'), 'err');
                redirect('perangkat_saya.php#formKirimPerangkat');
            }

            $lama = one('SELECT id, status FROM perangkat_kirim WHERE guru_id = ? AND mapel_id = ? AND ta_id = ? AND jenis_id = ?',
                [$gid, $mapelId, $taId, $jenisId]);
            perangkat_kirim_simpan($gid, $mapelId, $taId, $jenisId, [
                'keterangan' => $keterangan,
                'url' => (string) $unggah['url'],
                'nama_berkas' => $namaAsli,
                'jenis_berkas' => (string) $v['jenis'],
                'ukuran' => $ukuran,
            ], $sid);
            $labelJenis = $petaJenis[$jenisId];
            flash('Perangkat "' . tetap_kata($labelJenis) . '" untuk mata pelajaran '
                . tetap_kata($petaMapel[$mapelId]) . ' (' . tetap_kata((string) $ta['nama']) . ') '
                . ($lama ? 'berhasil DIGANTI' : 'berhasil dikirim')
                . ' dan sudah masuk ke akun Kepala Sekolah/Madrasah untuk ditinjau.', 'ok');
            redirect('perangkat_saya.php?ta=' . $taId . '#tabelPerangkat');
        }

        flash('Aksi tidak dikenal.', 'err');
        redirect('perangkat_saya.php');
    }

    // ---------------------------------------------------------------- tampilan
    $taPilih = ta_by_id((int) ($_GET['ta'] ?? 0)) ?? ta_aktif();
    $taId = $taPilih ? (int) $taPilih['id'] : 0;
    $baris = perangkat_kirim_guru($gid, $taId);
    $ringkas = perangkat_ringkas($baris);
    $kelengkapan = perangkat_kelengkapan($gid, $taId);
    $hitung = perangkat_kelengkapan_hitung($kelengkapan);
    $namaSekolah = nama_sekolah_tampil();
    $taSemua = ta_list(false);

    page_top('Kirim Perangkat', 'Perangkat Pembelajaran Saya &middot; ' . tetap_kata($namaSekolah));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('folder', 16) ?> Kirim Perangkat Pembelajaran</h2>
      <p>Unggah perangkat pembelajaran Anda — <b>satu berkas untuk setiap Nama Dokumen pada tiap mata
        pelajaran yang Anda ampu</b> (mis. <?= e(implode(', ', array_slice(PERANGKAT_JENIS_BAWAAN, 0, 4))) ?>).
        Kiriman langsung masuk ke akun <b>Kepala Sekolah/Madrasah</b> untuk ditinjau, dan hasil
        tinjuannya tampil di halaman ini.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($mapelSaya) ?> mata pelajaran</span>
      <span class="chip"><?= count($jenis) ?> nama dokumen</span>
      <span class="chip"><?= (int) $hitung['terisi'] ?>/<?= (int) $hitung['total'] ?> terkirim</span>
    </div>
  </div>

  <?php if (!$jenis): ?>
    <div class="alert alert-warn"><?= ikon('info', 16) ?><span>Belum ada <b>Nama Dokumen</b> perangkat
      yang diaktifkan Admin, jadi belum ada yang dapat dikirim. Hubungi Admin aplikasi.</span></div>
  <?php endif; ?>
  <?php if (!$mapelSaya): ?>
    <div class="alert alert-warn"><?= ikon('info', 16) ?><span>Akun Anda belum memiliki <b>mata
      pelajaran</b> yang diampu. Hubungi Admin Operator sekolah/madrasah agar mata pelajaran Anda
      ditetapkan pada menu Data Guru.</span></div>
  <?php endif; ?>

  <div class="divider"></div>
  <div class="section-title mb8"><?= ikon('chart', 15) ?> Keadaan Perangkat
    <?= $taPilih ? '&middot; ' . e(tetap_kata((string) $taPilih['nama'])) : '' ?></div>
  <?php perangkat_kartu_ringkas($ringkas); ?>

  <div class="grid g2" style="gap:16px;margin-top:18px">
    <div class="card card-tight" style="margin:0">
      <div class="pk-total">
        <div class="pk-total-angka"><?= (int) $hitung['persen'] ?>%</div>
        <div>
          <b>Kelengkapan perangkat</b>
          <div class="tbl-sub"><?= (int) $hitung['terisi'] ?> dari <?= (int) $hitung['total'] ?> sel
            (mata pelajaran &times; nama dokumen) sudah dikirim.</div>
        </div>
      </div>
      <div class="pk-bar sm mt16"><span style="width:<?= (int) $hitung['persen'] ?>%"></span></div>
    </div>
    <div class="card card-tight" style="margin:0">
      <b>Kiriman pada tahun ajaran ini</b>
      <div class="tbl-sub"><?= (int) $ringkas['kirim'] ?> kiriman &middot;
        <?= (int) $ringkas['menunggu'] ?> belum ditinjau</div>
      <p class="hint" style="margin-bottom:0">Mengunggah ulang pada sel yang sama akan
        <b>menggantikan</b> berkas lama, dan keadaannya kembali menjadi <b>Belum Ditinjau</b> —
        jadi pastikan berkas yang diunggah sudah final.</p>
    </div>
  </div>

  <?php if ($jenis && $mapelSaya): ?>
    <?php // Form multipart BIASA (bukan fetch): unggahan tetap bekerja walau skrip gagal dimuat.
          // Pilihan mapel/jenis/TA dapat diisi lebih dulu lewat tautan "Unggah" pada matriks bawah. ?>
    <div class="card" id="formKirimPerangkat" style="margin-top:18px">
      <div class="card-head">
        <div>
          <h2><?= ikon('upload', 16) ?> Unggah Perangkat</h2>
          <p>Pilih <b>Tahun Ajaran</b>, <b>Mata Pelajaran</b>, dan <b>Nama Dokumen</b>, lalu pilih
            berkasnya.</p>
        </div>
      </div>
      <?php
      $mPilih = (int) ($_GET['mapel'] ?? 0);
      $jPilih = (int) ($_GET['jenis'] ?? 0);
      if (!isset($petaMapel[$mPilih])) {
          $mPilih = (int) array_key_first($petaMapel);
      }
      if (!isset($petaJenis[$jPilih])) {
          $jPilih = (int) array_key_first($petaJenis);
      }
      ?>
      <form method="post" enctype="multipart/form-data" class="form-grid" id="formPerangkat">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="kirim">
        <div class="grid g3" style="gap:16px">
          <div>
            <label class="f" for="ta">Tahun Ajaran *</label>
            <?php if (!$taSemua): ?>
              <input type="text" value="Belum ada tahun ajaran" disabled>
            <?php else: ?>
              <select id="ta" name="ta" required>
                <?php foreach ($taSemua as $t): $tid = (int) $t['id']; ?>
                  <option value="<?= $tid ?>" <?= $tid === $taId ? 'selected' : '' ?>>
                    <?= e((string) $t['nama']) ?><?= (int) $t['aktif'] === 1 ? ' (berjalan)' : '' ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
          <div>
            <label class="f" for="mapel">Mata Pelajaran *</label>
            <select id="mapel" name="mapel" required>
              <?php foreach ($mapelSaya as $m): $mid = (int) $m['id']; ?>
                <option value="<?= $mid ?>" <?= $mid === $mPilih ? 'selected' : '' ?>><?= e((string) $m['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="f" for="jenis">Nama Dokumen *</label>
            <select id="jenis" name="jenis" required>
              <?php foreach ($jenis as $j): $jid = (int) $j['id']; ?>
                <option value="<?= $jid ?>" <?= $jid === $jPilih ? 'selected' : '' ?>><?= e((string) $j['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="grid g2" style="gap:16px">
          <div>
            <label class="f" for="berkas">Berkas Perangkat *</label>
            <input type="file" id="berkas" name="berkas" required style="padding:8px"
                   accept="<?= e('.' . implode(',.', dok_ekstensi_semua())) ?>">
            <div class="hint" id="infoBerkas">
              Diterima: <?= e(implode(', ', dok_ekstensi_semua())) ?>.
              Ukuran maksimal <b><?= e(dok_ukuran($batasKirim)) ?></b> per berkas.
            </div>
          </div>
          <div>
            <label class="f" for="keterangan">Keterangan</label>
            <textarea id="keterangan" name="keterangan" rows="3" maxlength="<?= (int) PERANGKAT_KETERANGAN_MAKS ?>"
                      placeholder="mis. Perangkat semester ganjil, disusun bersama MGMP."></textarea>
          </div>
        </div>
        <div class="flex">
          <button class="btn btn-primary" type="submit"><?= ikon('upload', 16) ?> Kirim ke Kepala Sekolah</button>
          <span class="hint" style="align-self:center">Berkas disimpan pada penyimpanan platform; yang
            tercatat di aplikasi hanya tautannya.</span>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <div class="divider"></div>
  <div class="section-title mb8"><?= ikon('clipboard', 15) ?> Kelengkapan Perangkat
    <?= $taPilih ? '&middot; ' . e(tetap_kata((string) $taPilih['nama'])) : '' ?></div>
  <?php if (count($taSemua) > 1): ?>
    <form class="filterbar mb16" method="get">
      <div style="min-width:240px">
        <label class="f" for="taFilter">Tampilkan tahun ajaran</label>
        <select id="taFilter" name="ta" data-auto>
          <?php foreach ($taSemua as $t): $tid = (int) $t['id']; ?>
            <option value="<?= $tid ?>" <?= $tid === $taId ? 'selected' : '' ?>><?= e((string) $t['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  <?php endif; ?>

  <?php if (!$kelengkapan): ?>
    <div class="empty"><?= ikon('book', 34) ?>
      <h3>Belum ada mata pelajaran yang diampu</h3>
      <p>Perangkat dapat dikirim setelah Admin Operator menetapkan mata pelajaran Anda pada menu
        <b>Data Guru</b>.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl tbl-perangkat">
        <thead>
          <tr>
            <th class="ctr pk-no">No</th>
            <th class="pk-mapel">Mata Pelajaran</th>
            <?php foreach ($jenis as $j): ?>
              <th class="ctr pk-jenis-sel"><?= e((string) $j['nama']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($kelengkapan as $row): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td class="pk-mapel-cell"><b><?= e(tetap_kata((string) $row['nama'])) ?></b></td>
            <?php foreach ($jenis as $j): $jid = (int) $j['id'];
                $b = $row['sel'][$jid] ?? null;
                $status = $b ? perangkat_status_awal((string) $b['status']) : '';
            ?>
              <td class="ctr pk-sel">
                <?php if ($b): ?>
                  <span class="pk-ikon ok" title="Sudah dikirim"><?= ikon('check', 15) ?></span>
                  <div class="pill <?= e(perangkat_status_pil($status)) ?>" style="margin-top:5px">
                    <?= e(perangkat_status_label($status)) ?></div>
                  <a class="pk-link" href="perangkat_saya.php?ta=<?= $taId ?>&amp;mapel=<?= (int) $row['id'] ?>&amp;jenis=<?= $jid ?>#formKirimPerangkat">
                    Ganti berkas</a>
                <?php else: ?>
                  <span class="pk-ikon no" title="Belum dikirim"><?= ikon('x', 15) ?></span>
                  <a class="pk-link" href="perangkat_saya.php?ta=<?= $taId ?>&amp;mapel=<?= (int) $row['id'] ?>&amp;jenis=<?= $jid ?>#formKirimPerangkat">
                    Unggah</a>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="2" class="ctr">Jumlah</th>
            <th colspan="<?= count($jenis) ?>" class="ctr">
              <span class="tbl-sub"><?= (int) $hitung['terisi'] ?> dari <?= (int) $hitung['total'] ?> sel
                sudah dikirim (<?= (int) $hitung['persen'] ?>%)</span>
            </th>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="hint mt16"><b>Centang hijau</b> = perangkat sudah dikirim, <b>silang merah</b> = belum
      dikirim. Klik <b>Unggah</b>/<b>Ganti berkas</b> untuk mengisi sel itu.</p>
  <?php endif; ?>

  <div class="divider"></div>
  <div class="section-title mb8"><?= ikon('folder', 15) ?> Perangkat yang Saya Kirim (<?= (int) $ringkas['kirim'] ?>)</div>
  <?php if (!$baris): ?>
    <div class="empty"><?= ikon('folder', 34) ?>
      <h3>Belum ada perangkat yang dikirim</h3>
      <p>Isi formulir <b>Unggah Perangkat</b> di atas untuk mengirim perangkat pertama Anda.</p>
    </div>
  <?php else: ?>
    <?php perangkat_tabel($baris, false, true); ?>
    <p class="hint mt16">Hasil tinjauan kepala sekolah tampil pada kolom <b>Status Tinjauan</b>
      beserta keterangan/alasannya. Bila dinyatakan <b>Direvisi</b> atau <b>Ditolak</b>, perbaiki
      berkasnya lalu unggah ulang pada sel yang sama.</p>
  <?php endif; ?>
</div>
    <?php
    page_bottom();
}

// ------------------------------------------------------------------ KEPALA & PENGAWAS: PERANGKAT MASUK

/**
 * Halaman **"Perangkat Masuk"** (`perangkat_masuk.php`).
 *
 * Dipakai DUA peran:
 *   • **Kepala Sekolah/Madrasah** (+ Super Admin dalam konteks) — meninjau kiriman guru
 *     (Disetujui / Direvisi / Ditolak + keterangan) dan mengunduh berkasnya;
 *   • **Pengawas** — hanya melihat & mengunduh kiriman binaan yang sedang dibuka (tanpa formulir
 *     tinjauan; setiap POST sudah ditolak gerbang pengawas dengan 403).
 */
function perangkat_masuk_halaman(): void
{
    require_login();

    // Pengawas: pindah konteks binaan lewat ?sekolah=ID (anti-IDOR), lalu hanya melihat.
    if (is_pengawas()) {
        $idSekolah = (int) ($_GET['sekolah'] ?? 0);
        $konteksKini = current_sekolah();
        // Bila binaan yang diminta SAMA dengan yang sedang dibuka, permintaan dilanjutkan (tidak
        // dialihkan) supaya tautan/penyegaran halaman tidak memantul tanpa alasan.
        if ($idSekolah > 0 && (!$konteksKini || (int) $konteksKini['id'] !== $idSekolah)) {
            if (!pengawas_punya((int) (current_user()['id'] ?? 0), $idSekolah)) {
                tolak403('Sekolah/madrasah itu bukan binaan Anda.');
            }
            if (!one('SELECT id FROM sekolah WHERE id = ? AND aktif = 1 AND verifikasi = "disetujui"', [$idSekolah])) {
                flash('Sekolah/madrasah itu sedang tidak aktif.', 'warn');
                redirect('index.php');
            }
            set_sekolah_context($idSekolah);
            flash('Anda kini melihat perangkat ' . nama_sekolah_tampil() . '.');
            redirect('perangkat_masuk.php');
        }
    }

    require_sekolah();
    $lihatSaja = !boleh_tinjau_perangkat();
    if ($lihatSaja && !is_pengawas()) {
        tolak403('Menu Perangkat Masuk ini untuk Kepala Sekolah/Madrasah (dan Super Admin dalam '
            . 'konteks sekolah). Kiriman Anda sendiri ada pada menu "Kirim Perangkat".');
    }

    // ---------------------------------------------------------------- POST: tinjau
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        if (!$lihatSaja && $aksi === 'tinjau') {
            $hasil = perangkat_tinjau((int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''),
                (string) ($_POST['catatan'] ?? ''));
            flash($hasil['pesan'], $hasil['ok'] ? 'ok' : 'err');
        } elseif ($aksi === 'hapus') {
            $id = (int) ($_POST['id'] ?? 0);
            $k = perangkat_kirim_by_id($id);
            if (!$k) {
                flash('Kiriman itu tidak ditemukan pada sekolah/madrasah ini.', 'err');
            } else {
                perangkat_kirim_hapus($id);
                flash('Kiriman perangkat "' . tetap_kata((string) $k['jenis_nama']) . '" milik '
                    . tetap_kata((string) $k['guru_nama']) . ' dihapus.', 'warn');
            }
        } else {
            flash('Aksi tidak dikenal.', 'err');
        }
        redirect('perangkat_masuk.php' . ($_GET ? '?' . http_build_query($_GET) : '') . '#tabelPerangkat');
    }

    // ---------------------------------------------------------------- tampilan
    [$where, $param, $f] = perangkat_filter($_GET);
    $baris = perangkat_kirim_sekolah([$where, $param, $f]);
    $ringkas = perangkat_ringkas($baris);
    $namaSekolah = nama_sekolah_tampil();
    // Yang ditampilkan pada penyaring: guru & mapel yang BENAR-BENAR punya kiriman (bukan seluruh
    // data sekolah), jadi penyaringnya selalu berguna.
    $guruAda = all('SELECT DISTINCT g.id, g.nama FROM perangkat_kirim k JOIN guru g ON g.id = k.guru_id
                     WHERE k.sekolah_id = ? ORDER BY g.nama', [sekolah_id()]);
    $mapelAda = all('SELECT DISTINCT m.id, m.nama FROM perangkat_kirim k JOIN mapel m ON m.id = k.mapel_id
                      WHERE k.sekolah_id = ? ORDER BY m.nama', [sekolah_id()]);
    $jenisAda = perangkat_jenis_list(false);
    $taAda = all('SELECT DISTINCT COALESCE(t.id, 0) AS id, COALESCE(t.nama, k.ta_nama) AS nama
                    FROM perangkat_kirim k LEFT JOIN tahun_ajaran t ON t.id = k.ta_id
                   WHERE k.sekolah_id = ? ORDER BY nama DESC', [sekolah_id()]);
    // CATATAN PENGAWAS (skema v52) — terpisah dari status tinjauan kepala sekolah; kepala sekolah
    // memang DAPAT MEMBACANYA (pilihan pemilik), tanpa formulir (hanya pengawas yang menulis).
    $catatanPw = perangkat_catatan_peta(array_column($baris, 'id'));

    page_top('Perangkat Masuk',
        ($lihatSaja ? 'Pengawas Sekolah/Madrasah &middot; hanya lihat &amp; unduh' : 'Tinjau Perangkat Guru')
        . ' &middot; ' . tetap_kata($namaSekolah));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('folder', 16) ?> Perangkat Guru yang Masuk</h2>
      <p><?= $lihatSaja
          ? 'Perangkat pembelajaran yang dikirim guru sekolah/madrasah binaan ini. Akun pengawas hanya dapat melihat dan mengunduh.'
          : 'Perangkat pembelajaran yang dikirim guru. Tinjau tiap dokumen dengan <b>Dokumen Disetujui</b>, <b>Dokumen Direvisi</b>, atau <b>Dokumen Ditolak</b> beserta keterangannya — hasilnya langsung tampil pada akun guru yang bersangkutan.' ?></p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $ringkas['kirim'] ?> kiriman tersaring</span>
      <span class="chip"><?= (int) $ringkas['menunggu'] ?> belum ditinjau</span>
    </div>
  </div>

  <?php if ($lihatSaja): ?>
    <div class="alert alert-warn"><?= ikon('eye', 16) ?><span>Akun pengawas bersifat
      <b>hanya lihat</b>: dokumen tidak dapat ditinjau atau diubah dari akun ini. Peninjauan dilakukan
      kepala sekolah/madrasah pada akunnya.</span></div>
  <?php endif; ?>

  <?php perangkat_kartu_ringkas($ringkas); ?>

  <div class="divider"></div>
  <div class="section-title mb8"><?= ikon('filter', 15) ?> Penyaring</div>
  <form class="filterbar" method="get">
    <div style="min-width:170px">
      <label class="f" for="saringTa">Tahun Ajaran</label>
      <select id="saringTa" name="ta" data-auto>
        <option value="">— semua tahun ajaran —</option>
        <?php foreach ($taAda as $t): $tid = (int) $t['id']; ?>
          <option value="<?= $tid ?>" <?= $f['ta'] === $tid ? 'selected' : '' ?>><?= e((string) $t['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:170px">
      <label class="f" for="saringGuru">Nama Guru</label>
      <select id="saringGuru" name="guru" data-auto>
        <option value="">— semua guru —</option>
        <?php foreach ($guruAda as $g): $gid = (int) $g['id']; ?>
          <option value="<?= $gid ?>" <?= $f['guru'] === $gid ? 'selected' : '' ?>><?= e(tetap_kata((string) $g['nama'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:150px">
      <label class="f" for="saringMapel">Mata Pelajaran</label>
      <select id="saringMapel" name="mapel" data-auto>
        <option value="">— semua mapel —</option>
        <?php foreach ($mapelAda as $m): $mid = (int) $m['id']; ?>
          <option value="<?= $mid ?>" <?= $f['mapel'] === $mid ? 'selected' : '' ?>><?= e(tetap_kata((string) $m['nama'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:150px">
      <label class="f" for="saringJenis">Nama Dokumen</label>
      <select id="saringJenis" name="jenis" data-auto>
        <option value="">— semua dokumen —</option>
        <?php foreach ($jenisAda as $j): $jid = (int) $j['id']; ?>
          <option value="<?= $jid ?>" <?= $f['jenis'] === $jid ? 'selected' : '' ?>><?= e((string) $j['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:150px">
      <label class="f" for="saringStatus">Status Tinjauan</label>
      <select id="saringStatus" name="status" data-auto>
        <option value="">— semua status —</option>
        <?php foreach (perangkat_status_list() as $kode => $label): ?>
          <option value="<?= e((string) $kode) ?>" <?= $f['status_ada'] && $f['status'] === (string) $kode ? 'selected' : '' ?>>
            <?= e((string) $label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:190px">
      <label class="f" for="saringCari">Cari</label>
      <input type="search" id="saringCari" name="cari" value="<?= e((string) $f['cari']) ?>"
             placeholder="nama guru, dokumen, keterangan…">
    </div>
    <div class="flex" style="gap:8px">
      <button class="btn btn-primary" type="submit"><?= ikon('search', 15) ?> Terapkan</button>
      <?php if (array_filter([$f['ta'], $f['guru'], $f['mapel'], $f['jenis'], $f['cari']]) || $f['status_ada']): ?>
        <a class="btn btn-ghost" href="perangkat_masuk.php">Reset</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="divider"></div>
  <div class="section-title mb8"><?= ikon('clipboard', 15) ?> Daftar Kiriman (<?= (int) $ringkas['kirim'] ?>)</div>

  <?php if (!$baris): ?>
    <div class="empty"><?= ikon('folder', 34) ?>
      <h3>Belum ada perangkat yang masuk</h3>
      <p><?= $lihatSaja
          ? 'Guru sekolah/madrasah ini belum mengirim perangkat, atau penyaring di atas tidak cocok dengan datanya.'
          : 'Perangkat yang dikirim guru akan tampil di sini untuk ditinjau. Guru mengirimnya lewat menu <b>Kirim Perangkat</b> pada akunnya.' ?></p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl tbl-perangkat" id="tblPerangkatMasuk">
        <thead>
          <tr>
            <th class="ctr pk-no">No</th>
            <th class="pk-guru">Nama Guru</th>
            <th class="pk-mapel">Mata Pelajaran</th>
            <th class="pk-jenis">Nama Dokumen</th>
            <th class="ctr pk-ta">T.A.</th>
            <th class="pk-berkas">Berkas</th>
            <th class="pk-status">Status Tinjauan</th>
            <?php if (!$lihatSaja): ?><th class="ctr pk-aksi">Tinjau / Hapus</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($baris as $k): $n++;
            $status = perangkat_status_awal((string) $k['status']);
            $tautan = tautan_aman((string) ($k['url'] ?? ''));
            $namaBerkas = trim((string) ($k['nama_berkas'] ?? '')) !== '' ? (string) $k['nama_berkas'] : 'berkas';
            $ext = strtolower((string) pathinfo($namaBerkas, PATHINFO_EXTENSION));
            $jenisBerkas = (string) ($k['jenis_berkas'] ?? dok_jenis_ext($ext));
        ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td class="pk-guru-cell"><b><?= e(tetap_kata((string) $k['guru_nama'])) ?></b>
              <span class="tbl-sub"><?= e(waktu_id_polos((string) ($k['dikirim'] ?? ''))) ?></span></td>
            <td class="pk-mapel-cell"><?= e(tetap_kata((string) $k['mapel_nama'])) ?></td>
            <td><b><?= e(tetap_kata((string) $k['jenis_nama'])) ?></b>
              <?php if (trim((string) ($k['keterangan'] ?? '')) !== ''): ?>
                <span class="tbl-sub"><?= nl2br(e(tetap_kata((string) $k['keterangan']))) ?></span>
              <?php endif; ?>
            </td>
            <td class="ctr nowrap"><?= e((string) ($k['ta_teks'] !== '' ? $k['ta_teks'] : '-')) ?></td>
            <td>
              <?php if ($tautan !== ''): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e($tautan) ?>" target="_blank" rel="noopener noreferrer">
                  <?= ikon('download', 14) ?> Unduh</a>
                <span class="tbl-sub"><?= e(mb_substr_fallback($namaBerkas, 0, 26)) ?> &middot;
                  <?= e(dok_label_jenis($jenisBerkas, $ext)) ?> &middot; <?= e(dok_ukuran((int) $k['ukuran'])) ?></span>
              <?php else: ?>
                <span class="hint xs">tautan tidak sah</span>
              <?php endif; ?>
            </td>
            <td class="pk-status-cell">
              <span class="pill <?= e(perangkat_status_pil($status)) ?>">
                <?= ikon(perangkat_status_ikon($status), 12) ?> <?= e(perangkat_status_label($status)) ?></span>
              <?php if (trim((string) ($k['catatan'] ?? '')) !== ''): ?>
                <div class="pk-catatan"><?= nl2br(e(tetap_kata((string) $k['catatan']))) ?></div>
              <?php endif; ?>
              <?php if ($status !== 'menunggu' && trim((string) ($k['peninjau_nama'] ?? '')) !== ''): ?>
                <div class="tbl-sub">oleh <?= e(tetap_kata((string) $k['peninjau_nama'])) ?>
                  &middot; <?= e(waktu_id_polos((string) ($k['ditinjau_pada'] ?? ''))) ?></div>
              <?php endif; ?>
              <?php // CATATAN PENGAWAS (skema v52) — TERPISAH dari status tinjauan di atas. ?>
              <?php if (!empty($catatanPw[(int) $k['id']])): ?>
                <div class="pk-catatan-pw">
                  <div class="pk-catatan-pw-judul"><?= ikon('eye', 12) ?> Catatan Pengawas</div>
                  <?php perangkat_catatan_tampil($catatanPw[(int) $k['id']], $k); ?>
                </div>
              <?php endif; ?>
            </td>
            <?php if (!$lihatSaja): ?>
              <td class="ctr pk-aksi">
                <?php // Formulir tinjauan per baris di dalam <details>: tetap bekerja TANPA JavaScript
                      // (dibuka dengan mengeklik ringkasannya), pola yang sama dengan status tinjauan
                      // dokumen madrasah pada aplikasi Supervisi Guru. ?>
                <details class="pk-tinjau">
                  <summary class="btn btn-primary btn-sm"><?= ikon('edit', 13) ?> Tinjau</summary>
                  <form method="post" class="pk-tinjau-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="tinjau">
                    <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                    <label class="f" for="status<?= (int) $k['id'] ?>">Keadaan Dokumen</label>
                    <select id="status<?= (int) $k['id'] ?>" name="status" required>
                      <?php foreach (perangkat_status_list() as $kode => $label): ?>
                        <option value="<?= e((string) $kode) ?>" <?= $status === (string) $kode ? 'selected' : '' ?>>
                          <?= e((string) $label) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <label class="f" for="catatan<?= (int) $k['id'] ?>">Keterangan / Alasan</label>
                    <textarea id="catatan<?= (int) $k['id'] ?>" name="catatan" rows="2"
                              maxlength="<?= (int) PERANGKAT_CATATAN_MAKS ?>"
                              placeholder="mis. RPP sudah baik, tambahkan penilaian sikap."><?= e((string) ($k['catatan'] ?? '')) ?></textarea>
                    <div class="hint xs">Keterangan <b>wajib</b> diisi untuk <b>Dokumen Direvisi</b> &
                      <b>Dokumen Ditolak</b>. Memilih <b>Belum Ditinjau</b> = membatalkan tinjauan.</div>
                    <button class="btn btn-ok btn-sm" type="submit"><?= ikon('save', 13) ?> Simpan Tinjauan</button>
                  </form>
                </details>
                <form method="post" data-confirm="Hapus kiriman perangkat &quot;<?= e((string) $k['jenis_nama']) ?>&quot; milik <?= e((string) $k['guru_nama']) ?>?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="hapus">
                  <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 13) ?> Hapus</button>
                </form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="2" class="ctr">Jumlah</th>
            <th colspan="<?= $lihatSaja ? 5 : 6 ?>" class="ctr">
              <span class="tbl-sub"><?= (int) $ringkas['kirim'] ?> kiriman &middot;
                <?= (int) $ringkas['menunggu'] ?> belum ditinjau &middot;
                <?= (int) $ringkas['disetujui'] ?> disetujui &middot;
                <?= (int) $ringkas['revisi'] ?> direvisi &middot;
                <?= (int) $ringkas['ditolak'] ?> ditolak</span>
            </th>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="hint mt16"><?= $lihatSaja
      ? 'Tombol <b>Unduh</b> membuka berkasnya pada tab baru. Seluruh kiriman hanya dapat dilihat — peninjauan dilakukan kepala sekolah/madrasah.'
      : 'Berkas diunduh lewat tombol <b>Unduh</b>. Hasil tinjauan langsung tampil pada akun guru yang mengirim (menu <b>Kirim Perangkat</b>) beserta keterangan yang Anda tulis.' ?></p>
  <?php endif; ?>
</div>
    <?php
    page_bottom();
}

// ------------------------------------------------------------------ SUPER ADMIN: JENIS PERANGKAT

/**
 * Halaman **"Jenis Perangkat"** (`perangkat_jenis.php`) — khusus Super Admin.
 *
 * Permintaan pemilik: *"Nama-nama dokumen ini di isi oleh Super Admin, bisa ditambah atau
 * dikurangi."* Daftarnya GLOBAL (berlaku untuk semua sekolah/madrasah) — sama seperti daftar aplikasi.
 */
function perangkat_jenis_halaman(): void
{
    require_super();
    // Halaman lintas sekolah → istilah generik "Sekolah" (bukan istilah salah satu jenis).
    $GLOBALS['__vs_istilah_nonaktif'] = true;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        $hasil = ['ok' => false, 'pesan' => 'Aksi tidak dikenal.'];
        switch ($aksi) {
            case 'tambah':
                $hasil = perangkat_jenis_tambah((string) ($_POST['nama'] ?? ''),
                    (string) ($_POST['aktif'] ?? '') === '1');
                break;
            case 'ubah':
                $hasil = perangkat_jenis_ubah((int) ($_POST['id'] ?? 0), (string) ($_POST['nama'] ?? ''));
                break;
            case 'toggle':
                $hasil = perangkat_jenis_toggle((int) ($_POST['id'] ?? 0));
                break;
            case 'geser':
                $hasil = perangkat_jenis_geser((int) ($_POST['id'] ?? 0), (int) ($_POST['arah'] ?? 1));
                break;
            case 'hapus':
                $hasil = perangkat_jenis_hapus((int) ($_POST['id'] ?? 0));
                break;
        }
        flash($hasil['pesan'], $hasil['ok'] ? 'ok' : 'err');
        redirect('perangkat_jenis.php#tabelJenis');
    }

    $daftar = perangkat_jenis_list(false);
    $aktif = perangkat_jenis_jumlah(true);
    $total = count($daftar);
    $kiriman = (int) val('SELECT COUNT(*) FROM perangkat_kirim', [], 0);
    $editId = (int) ($_GET['edit'] ?? 0);
    $edit = $editId > 0 ? perangkat_jenis_by_id($editId) : null;

    page_top('Jenis Perangkat', 'Nama Dokumen Perangkat Guru (berlaku semua sekolah/madrasah)');
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('folder', 16) ?> Nama Dokumen Perangkat Guru</h2>
      <p>Daftar <b>Nama Dokumen</b> yang dapat dikirim guru pada menu <b>Kirim Perangkat</b> (mis.
        Kalender Pendidikan, Analisis Pekan Efektif, Capaian Pembelajaran, Alur Tujuan Pembelajaran,
        RPP/Modul Ajar). Daftar ini berlaku untuk <b>seluruh sekolah/madrasah</b> — tambah, ubah,
        urutkan, nonaktifkan, atau hapus sesuai kebutuhan.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $aktif ?> aktif</span>
      <span class="chip"><?= (int) $total ?> total</span>
      <span class="chip"><?= (int) $kiriman ?> kiriman guru</span>
    </div>
  </div>

  <div class="alert alert-ok" style="background:rgba(201,162,39,.07);border-color:rgba(201,162,39,.45)">
    <?= ikon('info', 16) ?>
    <span>Menghapus sebuah Nama Dokumen <b>tidak menghapus kiriman guru</b>: berkas yang sudah masuk
      tetap tersimpan beserta status tinjuannya (namanya diambil dari catatan kiriman). Menonaktifkan
      hanya menyembunyikannya dari pilihan guru.</span>
  </div>

  <div class="divider"></div>
  <div class="grid g21" style="gap:18px">
    <div>
      <div class="section-title mb8" id="tabelJenis"><?= ikon('apps', 15) ?> Daftar Nama Dokumen (<?= (int) $total ?>)</div>
      <?php if (!$daftar): ?>
        <div class="empty"><?= ikon('folder', 34) ?>
          <h3>Belum ada Nama Dokumen</h3>
          <p>Tambahkan lewat formulir di sebelah kanan. Tanpa nama dokumen, guru tidak dapat mengirim
            perangkat apa pun.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tbl" id="tblJenis">
            <thead>
              <tr>
                <th class="ctr" style="width:44px">No</th>
                <th>Nama Dokumen</th>
                <th class="ctr" style="width:130px">Dipakai</th>
                <th class="ctr" style="width:110px">Keadaan</th>
                <th class="ctr" style="width:96px">Urutan</th>
                <th class="ctr" style="width:190px">Tindakan</th>
              </tr>
            </thead>
            <tbody>
            <?php $n = 0; foreach ($daftar as $i => $j): $n++; $jid = (int) $j['id'];
                $pakai = (int) val('SELECT COUNT(*) FROM perangkat_kirim WHERE jenis_id = ?', [$jid], 0); ?>
              <tr>
                <td class="ctr"><?= $n ?></td>
                <td>
                  <?php if ($edit && (int) $edit['id'] === $jid): ?>
                    <form method="post" class="flex" style="gap:8px" id="formUbahJenis">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="ubah">
                      <input type="hidden" name="id" value="<?= $jid ?>">
                      <input type="text" name="nama" id="jenisNamaUbah" required
                             maxlength="<?= (int) PERANGKAT_NAMA_MAKS ?>" value="<?= e((string) $j['nama']) ?>">
                      <button class="btn btn-ok btn-sm" type="submit"><?= ikon('save', 13) ?> Simpan</button>
                      <a class="btn btn-ghost btn-sm" href="perangkat_jenis.php">Batal</a>
                    </form>
                  <?php else: ?>
                    <b><?= e((string) $j['nama']) ?></b>
                  <?php endif; ?>
                </td>
                <td class="ctr"><?= (int) $pakai ?> kiriman</td>
                <td class="ctr">
                  <?php if ((int) $j['aktif'] === 1): ?>
                    <span class="pill pill-ok"><?= ikon('check', 12) ?> aktif</span>
                  <?php else: ?>
                    <span class="pill pill-warn">nonaktif</span>
                  <?php endif; ?>
                </td>
                <td class="ctr nowrap">
                  <form method="post" class="row-actions" style="justify-content:center">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="geser">
                    <input type="hidden" name="id" value="<?= $jid ?>">
                    <button class="btn btn-ghost btn-sm" type="submit" name="arah" value="-1"
                            title="Naikkan urutan" <?= $i === 0 ? 'disabled' : '' ?>><?= ikon('up', 13) ?></button>
                    <button class="btn btn-ghost btn-sm" type="submit" name="arah" value="1"
                            title="Turunkan urutan" <?= $n === $total ? 'disabled' : '' ?>><?= ikon('down', 13) ?></button>
                  </form>
                </td>
                <td class="ctr">
                  <div class="row-actions">
                    <a class="btn btn-ghost btn-sm" href="perangkat_jenis.php?edit=<?= $jid ?>#tabelJenis"><?= ikon('edit', 13) ?> Ubah</a>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="toggle">
                      <input type="hidden" name="id" value="<?= $jid ?>">
                      <button class="btn <?= (int) $j['aktif'] === 1 ? 'btn-ghost' : 'btn-ok' ?> btn-sm" type="submit">
                        <?= ikon((int) $j['aktif'] === 1 ? 'x' : 'check', 13) ?>
                        <?= (int) $j['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                    </form>
                    <form method="post" data-confirm="Hapus Nama Dokumen &quot;<?= e((string) $j['nama']) ?>&quot;? Kiriman guru yang sudah masuk TIDAK ikut terhapus.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="id" value="<?= $jid ?>">
                      <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 13) ?> Hapus</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="2" class="ctr">Jumlah</th>
                <th colspan="4" class="ctr"><span class="tbl-sub"><?= (int) $aktif ?> aktif dari
                  <?= (int) $total ?> Nama Dokumen</span></th>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div>
      <div class="card card-tight" id="formTambah" style="margin:0">
        <div class="section-title mb8"><?= ikon('plus', 15) ?> Tambah Nama Dokumen</div>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="tambah">
          <label class="f" for="nama">Nama Dokumen *</label>
          <input type="text" id="nama" name="nama" required maxlength="<?= (int) PERANGKAT_NAMA_MAKS ?>"
                 placeholder="mis. Kalender Pendidikan">
          <label class="pk-cek" style="margin-top:10px">
            <input type="checkbox" name="aktif" value="1" checked> Langsung aktifkan
          </label>
          <div class="hint">Nama yang sama tidak dapat didaftarkan dua kali. Nama baru diletakkan pada
            urutan paling akhir — atur dengan tombol ▲▼.</div>
          <button class="btn btn-primary mt16" type="submit"><?= ikon('plus', 15) ?> Tambah Nama Dokumen</button>
        </form>
      </div>

      <div class="card card-tight" style="margin-top:16px">
        <div class="section-title mb8"><?= ikon('info', 15) ?> Cara kerja</div>
        <ul class="portal-cek" style="margin:0">
          <li>Guru mengunggah <b>satu berkas per Nama Dokumen pada tiap mata pelajaran</b> yang diampu,
            per tahun ajaran.</li>
          <li>Kiriman masuk ke akun <b>Kepala Sekolah/Madrasah</b> untuk ditinjau
            (Disetujui / Direvisi / Ditolak) dan dapat dilihat <b>Pengawas</b> binaannya.</li>
          <li>Hasil tinjauan tampil pada akun guru yang mengirim.</li>
          <li>Menambah Nama Dokumen berarti guru dapat mengirim perangkat baru untuk semua mata
            pelajaran; mengurangi berarti pilihannya menyempit.</li>
        </ul>
      </div>
    </div>
  </div>
</div>
    <?php
    page_bottom();
}

// ------------------------------------------------------------------ PENGAWAS: PERANGKAT GURU PER LEMBAGA

/**
 * Query string penyaring yang dibawa kembali setelah menyimpan catatan (hanya kunci yang sah).
 *
 * Nilai yang dikirim ke peramban TIDAK PERNAH dipakai langsung sebagai alamat: di halaman penerima
 * nilai ini diperiksa lagi terhadap daftar kunci penyaring yang dikenal (anti open-redirect).
 */
function perangkat_kembali_q(): string
{
    $izin = ['ta', 'guru', 'mapel', 'jenis', 'status', 'cari'];
    $q = [];
    foreach ($izin as $k) {
        $v = trim((string) ($_GET[$k] ?? ''));
        if ($v !== '') {
            $q[$k] = $v;
        }
    }
    return http_build_query($q);
}

/**
 * Halaman **"Perangkat Guru"** pada akun PENGAWAS (`perangkat_pengawas.php`) — skema v52.
 *
 * Permintaan pemilik: *"Pada akun pengawas tambahkan menu Perangkat Guru (Perangkat guru ditampilkan
 * per lembaga yang di dalamnya ada daftar guru + mata pelajaran dan daftar perangkatnya). Pengawas
 * bisa melihat, memberikan catatan setiap perangkat dan mendownload"*.
 *
 * Pilihan pemilik (jangan diubah tanpa bertanya):
 *   1. **catatan pengawas TERPISAH** dari status tinjauan kepala sekolah — guru & kepala sekolah
 *      dapat membacanya (lihat `perangkat_catatan_tampil()`);
 *   2. **pilih lembaga dulu**, lalu tampil daftar guru + mata pelajaran + daftar perangkatnya.
 *
 * Alur halaman:
 *   • TANPA konteks binaan → halaman **PEMILIH BINAAN** (kartu per sekolah binaan + ringkasannya);
 *   • `?sekolah=ID` → memindahkan konteks binaan (anti-IDOR `pengawas_punya()`), lalu dialihkan;
 *   • konteks binaan aktif → daftar perangkat **dikelompokkan per GURU lalu per MATA PELAJARAN**,
 *     lengkap dengan berkas (Unduh), status tinjauan kepala sekolah, serta catatan pengawas
 *     (formulir tulis/ubah/hapus ada di tiap baris perangkat).
 *
 * Hanya-BACA selain catatan: pengawas TIDAK meninjau/menghapus perangkat. Berkas ini terdaftar pada
 * `pengawas_berkas_boleh()` **dan** `pengawas_berkas_post()` di `inc/helpers.php` — tanpanya seluruh
 * POST (termasuk formulir catatan) dijawab 403 oleh gerbang pengawas.
 */
function perangkat_pengawas_halaman(): void
{
    require_login();
    if (!is_pengawas()) {
        tolak403('Menu Perangkat Guru ini khusus akun Pengawas Sekolah/Madrasah. Akun guru mengirim '
            . 'perangkat lewat menu "Kirim Perangkat", sedangkan kepala sekolah/madrasah meninjaunya '
            . 'lewat menu "Perangkat Masuk".');
    }
    $uid = (int) (current_user()['id'] ?? 0);
    $binaan = sekolah_binaan($uid, true);

    // ---------------------------------------------------------------- pindah konteks binaan
    $idSekolah = (int) ($_GET['sekolah'] ?? 0);
    $konteks = current_sekolah();
    if ($idSekolah > 0 && (!$konteks || (int) $konteks['id'] !== $idSekolah)) {
        if (!pengawas_punya($uid, $idSekolah)) {
            tolak403('Sekolah/madrasah itu bukan binaan Anda.');
        }
        if (!one('SELECT id FROM sekolah WHERE id = ? AND aktif = 1 AND verifikasi = "disetujui"', [$idSekolah])) {
            flash('Sekolah/madrasah itu sedang tidak aktif sehingga perangkatnya tidak dapat dibuka.', 'warn');
            redirect('perangkat_pengawas.php');
        }
        set_sekolah_context($idSekolah);
        flash('Anda kini melihat perangkat ' . tetap_kata(nama_sekolah_tampil()) . '.');
        redirect('perangkat_pengawas.php');
    }

    // ---------------------------------------------------------------- POST: simpan catatan
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        if ($aksi === 'catatan') {
            $hasil = perangkat_catatan_simpan((int) ($_POST['id'] ?? 0), (string) ($_POST['isi'] ?? ''));
            flash($hasil['pesan'], $hasil['ok'] ? 'ok' : 'err');
        } else {
            flash('Aksi tidak dikenal.', 'err');
        }
        $kembali = trim((string) ($_POST['kembali'] ?? ''));
        // Hanya bentuk query-string penyaring yang diterima (tidak boleh ada alamat/skema).
        if ($kembali !== '' && (!preg_match('/^[A-Za-z0-9=&_%+.\-]+$/', $kembali) || strpos($kembali, '//') !== false)) {
            $kembali = '';
        }
        redirect('perangkat_pengawas.php' . ($kembali !== '' ? '?' . $kembali : '') . '#daftarPerangkat');
    }

    // ---------------------------------------------------------------- halaman PEMILIH BINAAN
    if (isset($_GET['pilih']) || !current_sekolah()) {
        $GLOBALS['__vs_istilah_nonaktif'] = true;
        $ringkasBinaan = [];
        foreach ($binaan as $b) {
            $ringkasBinaan[(int) $b['id']] = perangkat_ringkas_binaan((int) $b['id'], $uid);
        }
        $sedangDibuka = current_sekolah();
        page_top('Perangkat Guru', 'Pilih Sekolah/Madrasah Binaan &middot; Perangkat Pembelajaran Guru');
        ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('folder', 16) ?> Perangkat Guru Sekolah/Madrasah Binaan</h2>
      <p>Pilih salah satu sekolah/madrasah binaan Anda untuk melihat <b>daftar guru beserta mata
        pelajaran dan perangkat pembelajarannya</b>. Anda dapat mengunduh berkasnya dan memberikan
        <b>catatan pengawas</b> pada setiap perangkat — catatan itu terpisah dari status tinjauan kepala
        sekolah/madrasah dan dapat dibaca guru pengirim maupun kepala sekolah/madrasah.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($binaan) ?> binaan aktif</span>
    </div>
  </div>

  <?php if ($sedangDibuka): ?>
    <div class="alert alert-ok">
      <?= ikon('info', 16) ?>
      <span>Binaan yang sedang dibuka: <b><?= e(tetap_kata(nama_sekolah_tampil($sedangDibuka))) ?></b>.
        <a href="perangkat_pengawas.php">Lanjut melihat perangkat binaan ini</a>.</span>
    </div>
  <?php endif; ?>

  <?php if (!$binaan): ?>
    <div class="empty"><?= ikon('folder', 34) ?>
      <h3>Belum ada sekolah/madrasah binaan</h3>
      <p>Akun pengawas hanya dapat melihat sekolah/madrasah yang ditetapkan oleh Admin aplikasi
        (Super Admin). Hubungi Admin aplikasi bila binaan Anda belum ditetapkan.</p>
    </div>
  <?php else: ?>
    <div class="divider"></div>
    <div class="section-title mb8" id="daftarBinaan"><?= ikon('apps', 15) ?> Daftar Binaan (<?= count($binaan) ?>)</div>
    <div class="grid g2" style="gap:18px">
      <?php foreach ($binaan as $b):
          $bid = (int) $b['id'];
          $r = $ringkasBinaan[$bid] ?? ['guru_sekolah' => 0, 'guru' => 0, 'mapel' => 0, 'kirim' => 0, 'menunggu' => 0, 'catatan' => 0];
          $buka = $sedangDibuka && (int) $sedangDibuka['id'] === $bid;
      ?>
        <div class="card card-tight pw-binaan" style="margin:0">
          <div class="pw-binaan-kepala">
            <div>
              <b class="pw-binaan-nama"><?= e(tetap_kata((string) $b['nama'])) ?></b>
              <div class="tbl-sub"><?= ((string) ($b['jenis'] ?? 'umum')) === 'madrasah' ? 'Madrasah' : 'Sekolah Umum' ?>
                <?php if (trim((string) ($b['jenjang'] ?? '')) !== ''): ?>
                  &middot; <?= e((string) $b['jenjang']) ?>
                <?php endif; ?></div>
            </div>
            <?php if ($buka): ?><span class="pill pill-ok"><?= ikon('check', 12) ?> sedang dibuka</span><?php endif; ?>
          </div>
          <div class="flex mt16" style="gap:8px;flex-wrap:wrap">
            <span class="chip"><?= (int) $r['guru_sekolah'] ?> guru aktif</span>
            <span class="chip"><?= (int) $r['kirim'] ?> perangkat</span>
            <span class="chip"><?= (int) $r['mapel'] ?> mata pelajaran</span>
            <span class="chip"><?= (int) $r['catatan'] ?> catatan Anda</span>
          </div>
          <?php if ((int) $r['menunggu'] > 0): ?>
            <p class="hint" style="margin:10px 0 0"><?= ikon('clock', 13) ?>
              <?= (int) $r['menunggu'] ?> perangkat belum ditinjau kepala sekolah/madrasah.</p>
          <?php endif; ?>
          <div class="mt16">
            <a class="btn <?= $buka ? 'btn-primary' : 'btn-ok' ?>" href="perangkat_pengawas.php?sekolah=<?= $bid ?>">
              <?= ikon('folder', 15) ?> Buka Perangkat
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="hint mt16">Data perangkat diambil dari menu <b>Kirim Perangkat</b> pada akun guru. Angka
      <b>catatan Anda</b> menunjukkan berapa perangkat pada binaan itu yang sudah Anda beri catatan.</p>
  <?php endif; ?>
</div>
        <?php
        page_bottom();
        return;
    }

    // ---------------------------------------------------------------- halaman DATA (konteks binaan)
    require_sekolah();
    $sid = sekolah_id();
    [$where, $param, $f] = perangkat_filter($_GET);
    $baris = perangkat_kirim_sekolah([$where, $param, $f]);
    $ringkas = perangkat_ringkas($baris);
    $catatanPw = perangkat_catatan_peta(array_column($baris, 'id'));
    $namaSekolah = nama_sekolah_tampil();

    // Nama & NIP guru (dipakai judul kelompok; tetap tampil walau penyaring menyembunyikan sebagian).
    $guruSekolah = [];
    foreach (all('SELECT id, nama, nip FROM guru WHERE sekolah_id = ? AND aktif = 1 ORDER BY nama', [$sid]) as $g) {
        $guruSekolah[(int) $g['id']] = $g;
    }

    // Kelompokkan: GURU → MATA PELAJARAN → daftar perangkat (permintaan pemilik: "daftar guru + mata
    // pelajaran dan daftar perangkatnya").
    $grup = [];
    foreach ($baris as $k) {
        $gid = (int) $k['guru_id'];
        $mid = (int) $k['mapel_id'];
        if (!isset($grup[$gid])) {
            $grup[$gid] = ['id' => $gid, 'nama' => (string) $k['guru_nama'],
                           'nip' => (string) ($guruSekolah[$gid]['nip'] ?? ''), 'mapel' => [], 'kirim' => 0, 'menunggu' => 0];
        }
        if (!isset($grup[$gid]['mapel'][$mid])) {
            $grup[$gid]['mapel'][$mid] = ['id' => $mid, 'nama' => (string) $k['mapel_nama'], 'baris' => []];
        }
        $grup[$gid]['mapel'][$mid]['baris'][] = $k;
        $grup[$gid]['kirim']++;
        if (perangkat_status_awal((string) $k['status']) === 'menunggu') {
            $grup[$gid]['menunggu']++;
        }
    }

    // Guru yang SAMA SEKALI belum mengirim perangkat (hanya ditampilkan saat tidak ada penyaring,
    // supaya tidak membingungkan saat menyaring).
    $adaSaring = ($f['ta'] > 0 || $f['guru'] > 0 || $f['mapel'] > 0 || $f['jenis'] > 0
        || $f['status_ada'] || $f['cari'] !== '');
    $guruKosong = [];
    if (!$adaSaring) {
        foreach ($guruSekolah as $g) {
            if (!isset($grup[(int) $g['id']])) {
                $guruKosong[] = $g;
            }
        }
    }

    // Pilihan penyaring: diambil dari data yang MEMANG ada pada sekolah ini (selalu berguna), kecuali
    // nama guru yang diambil dari seluruh guru aktif supaya guru tanpa kiriman tetap bisa dicari.
    $mapelAda = all('SELECT DISTINCT m.id, m.nama FROM perangkat_kirim k JOIN mapel m ON m.id = k.mapel_id
                      WHERE k.sekolah_id = ? ORDER BY m.nama', [$sid]);
    $jenisAda = perangkat_jenis_list(false);
    $taAda = all('SELECT DISTINCT COALESCE(t.id, 0) AS id, COALESCE(t.nama, k.ta_nama) AS nama
                    FROM perangkat_kirim k LEFT JOIN tahun_ajaran t ON t.id = k.ta_id
                   WHERE k.sekolah_id = ? ORDER BY nama DESC', [$sid]);
    $jumlahGuru = count($grup);
    $jumlahMapel = 0;
    foreach ($grup as $g) {
        $jumlahMapel = max($jumlahMapel, count($g['mapel']));
    }

    page_top('Perangkat Guru', 'Pengawas Sekolah/Madrasah &middot; ' . tetap_kata($namaSekolah));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('folder', 16) ?> Perangkat Guru &mdash; <?= e(tetap_kata($namaSekolah)) ?></h2>
      <p>Perangkat pembelajaran yang dikirim guru sekolah/madrasah binaan ini, tersusun
        <b>per guru &rarr; per mata pelajaran &rarr; daftar perangkatnya</b>. Anda dapat
        <b>mengunduh</b> setiap berkas dan <b>memberi catatan</b> pada tiap perangkat. Catatan pengawas
        <b>terpisah</b> dari status tinjauan kepala sekolah/madrasah dan dapat dibaca guru pengirim
        maupun kepala sekolah/madrasah.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $jumlahGuru ?> guru</span>
      <span class="chip"><?= (int) $ringkas['kirim'] ?> perangkat</span>
      <span class="chip"><?= (int) $ringkas['menunggu'] ?> belum ditinjau</span>
      <a class="btn btn-ghost btn-sm" href="perangkat_pengawas.php?pilih=1"><?= ikon('apps', 14) ?> Ganti Binaan</a>
    </div>
  </div>

  <div class="divider"></div>
  <div class="section-title mb8"><?= ikon('filter', 15) ?> Penyaring</div>
  <form class="filterbar" method="get">
    <div style="min-width:170px">
      <label class="f" for="saringTa">Tahun Ajaran</label>
      <select id="saringTa" name="ta" data-auto>
        <option value="">— semua tahun ajaran —</option>
        <?php foreach ($taAda as $t): $tid = (int) $t['id']; ?>
          <option value="<?= $tid ?>" <?= $f['ta'] === $tid ? 'selected' : '' ?>><?= e((string) $t['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:180px">
      <label class="f" for="saringGuru">Nama Guru</label>
      <select id="saringGuru" name="guru" data-auto>
        <option value="">— semua guru —</option>
        <?php foreach ($guruSekolah as $g): $gid = (int) $g['id']; ?>
          <option value="<?= $gid ?>" <?= $f['guru'] === $gid ? 'selected' : '' ?>><?= e(tetap_kata((string) $g['nama'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:150px">
      <label class="f" for="saringMapel">Mata Pelajaran</label>
      <select id="saringMapel" name="mapel" data-auto>
        <option value="">— semua mapel —</option>
        <?php foreach ($mapelAda as $m): $mid = (int) $m['id']; ?>
          <option value="<?= $mid ?>" <?= $f['mapel'] === $mid ? 'selected' : '' ?>><?= e(tetap_kata((string) $m['nama'])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:150px">
      <label class="f" for="saringJenis">Nama Dokumen</label>
      <select id="saringJenis" name="jenis" data-auto>
        <option value="">— semua dokumen —</option>
        <?php foreach ($jenisAda as $j): $jid = (int) $j['id']; ?>
          <option value="<?= $jid ?>" <?= $f['jenis'] === $jid ? 'selected' : '' ?>><?= e((string) $j['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:150px">
      <label class="f" for="saringStatus">Status Tinjauan Kepala</label>
      <select id="saringStatus" name="status" data-auto>
        <option value="">— semua status —</option>
        <?php foreach (perangkat_status_list() as $kode => $label): ?>
          <option value="<?= e((string) $kode) ?>" <?= $f['status_ada'] && $f['status'] === (string) $kode ? 'selected' : '' ?>>
            <?= e((string) $label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:190px">
      <label class="f" for="saringCari">Cari</label>
      <input type="search" id="saringCari" name="cari" value="<?= e((string) $f['cari']) ?>"
             placeholder="nama guru, dokumen, keterangan…">
    </div>
    <div class="flex" style="gap:8px">
      <button class="btn btn-primary" type="submit"><?= ikon('search', 15) ?> Terapkan</button>
      <?php if ($adaSaring): ?>
        <a class="btn btn-ghost" href="perangkat_pengawas.php">Reset</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="divider"></div>
  <div class="section-title mb8" id="daftarPerangkat"><?= ikon('clipboard', 15) ?>
    Daftar Guru &amp; Perangkatnya (<?= (int) $jumlahGuru ?> guru &middot; <?= (int) $ringkas['kirim'] ?> perangkat)</div>

  <?php if (!$grup): ?>
    <div class="empty"><?= ikon('folder', 34) ?>
      <h3>Belum ada perangkat yang dikirim</h3>
      <p><?= $adaSaring
          ? 'Tidak ada perangkat yang cocok dengan penyaring di atas. Coba longgarkan penyaringnya.'
          : 'Guru sekolah/madrasah ini belum mengirim perangkat pembelajaran. Guru mengirimnya lewat menu <b>Kirim Perangkat</b> pada akunnya.' ?></p>
    </div>
  <?php else: ?>
    <?php foreach ($grup as $g): ?>
      <div class="card pw-guru">
        <details open>
          <summary>
            <span class="pw-guru-nama"><?= ikon('users', 16) ?> <?= e(tetap_kata($g['nama'])) ?></span>
            <span class="pw-guru-ket">
              <?php if (trim((string) $g['nip']) !== ''): ?>NIP <?= e((string) $g['nip']) ?> &middot; <?php endif; ?>
              <?= count($g['mapel']) ?> mata pelajaran &middot; <?= (int) $g['kirim'] ?> perangkat
              <?php if ((int) $g['menunggu'] > 0): ?>
                &middot; <b><?= (int) $g['menunggu'] ?> belum ditinjau</b>
              <?php endif; ?>
            </span>
          </summary>
          <div class="pw-guru-isi">
            <?php foreach ($g['mapel'] as $m): ?>
              <div class="pw-mapel">
                <div class="pw-mapel-judul">
                  <?= ikon('book', 14) ?> <?= e(tetap_kata($m['nama'])) ?>
                  <span class="chip"><?= count($m['baris']) ?> perangkat</span>
                </div>
                <div class="table-wrap">
                  <table class="tbl tbl-perangkat tbl-pw">
                    <thead>
                      <tr>
                        <th class="ctr pk-no">No</th>
                        <th class="pk-jenis">Nama Dokumen</th>
                        <th class="ctr pk-ta">T.A.</th>
                        <th class="pk-berkas">Berkas</th>
                        <th class="pk-status">Status Tinjauan Kepala</th>
                        <th class="pk-catatan-pw-kol">Catatan Pengawas</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php $n = 0; foreach ($m['baris'] as $k): $n++;
                        $status = perangkat_status_awal((string) $k['status']);
                        $tautan = tautan_aman((string) ($k['url'] ?? ''));
                        $namaBerkas = trim((string) ($k['nama_berkas'] ?? '')) !== '' ? (string) $k['nama_berkas'] : 'berkas';
                        $ext = strtolower((string) pathinfo($namaBerkas, PATHINFO_EXTENSION));
                        $jenisBerkas = (string) ($k['jenis_berkas'] ?? dok_jenis_ext($ext));
                    ?>
                      <tr>
                        <td class="ctr"><?= $n ?></td>
                        <td>
                          <b><?= e(tetap_kata((string) $k['jenis_nama'])) ?></b>
                          <?php if (trim((string) ($k['keterangan'] ?? '')) !== ''): ?>
                            <span class="tbl-sub"><?= nl2br(e(tetap_kata((string) $k['keterangan']))) ?></span>
                          <?php endif; ?>
                        </td>
                        <td class="ctr nowrap"><?= e((string) ($k['ta_teks'] !== '' ? $k['ta_teks'] : '-')) ?></td>
                        <td>
                          <?php if ($tautan !== ''): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= e($tautan) ?>" target="_blank" rel="noopener noreferrer">
                              <?= ikon('download', 14) ?> Unduh</a>
                            <span class="tbl-sub"><?= e(mb_substr_fallback($namaBerkas, 0, 24)) ?> &middot;
                              <?= e(dok_label_jenis($jenisBerkas, $ext)) ?> &middot; <?= e(dok_ukuran((int) $k['ukuran'])) ?></span>
                          <?php else: ?>
                            <span class="hint xs">tautan tidak sah</span>
                          <?php endif; ?>
                        </td>
                        <td class="pk-status-cell">
                          <span class="pill <?= e(perangkat_status_pil($status)) ?>">
                            <?= ikon(perangkat_status_ikon($status), 12) ?> <?= e(perangkat_status_label($status)) ?></span>
                          <?php if (trim((string) ($k['catatan'] ?? '')) !== ''): ?>
                            <div class="pk-catatan"><?= nl2br(e(tetap_kata((string) $k['catatan']))) ?></div>
                          <?php endif; ?>
                          <?php if ($status !== 'menunggu' && trim((string) ($k['peninjau_nama'] ?? '')) !== ''): ?>
                            <div class="tbl-sub">oleh <?= e(tetap_kata((string) $k['peninjau_nama'])) ?>
                              &middot; <?= e(waktu_id_polos((string) ($k['ditinjau_pada'] ?? ''))) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="pk-catatan-pw-kol">
                          <?php perangkat_catatan_tampil($catatanPw[(int) $k['id']] ?? [], $k); ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </details>
      </div>
    <?php endforeach; ?>
    <p class="hint mt16">Klik judul guru untuk membuka/menutup daftar perangkatnya. Tombol
      <b>Unduh</b> membuka berkas pada tab baru; <b>Simpan Catatan</b> mengirim catatan Anda untuk
      perangkat itu (terpisah dari status tinjauan kepala sekolah/madrasah).</p>
  <?php endif; ?>

  <?php if ($guruKosong): ?>
    <div class="divider"></div>
    <div class="section-title mb8"><?= ikon('alert', 15) ?> Guru yang Belum Mengirim Perangkat (<?= count($guruKosong) ?>)</div>
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr><th class="ctr" style="width:52px">No</th><th>Nama Guru</th><th>NIP</th>
            <th class="ctr" style="width:180px">Mata Pelajaran Diampu</th></tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($guruKosong as $g): $n++; $gid = (int) $g['id']; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e(tetap_kata((string) $g['nama'])) ?></b></td>
            <td><?= e(trim((string) ($g['nip'] ?? '')) !== '' ? (string) $g['nip'] : '-') ?></td>
            <td class="ctr"><?= count(mapel_diampu($gid)) ?> mapel</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint mt16">Guru di atas belum mengirim perangkat apa pun. Ingatkan yang bersangkutan
      melalui menu <b>Kirim Dokumen</b> atau saat pertemuan supervisi.</p>
  <?php endif; ?>
</div>
    <?php
    page_bottom();
}
