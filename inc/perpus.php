<?php
/**
 * MODUL KEPALA PERPUSTAKAAN — LAPORAN HASIL IMPOR APLIKASI e-PERPUS.
 *
 * Permintaan pemilik: "Pada tugas tambahan menu Ka. Perpustakaan, menunya diisi dengan: Laporan
 * Anggota, Laporan Data Buku, Laporan Peminjaman, Laporan Pengembalian, Laporan Pengunjung. Semua
 * laporan ini di impor dari hasil unduhan aplikasi e-perpus. Ditambahkan juga fitur cetak untuk
 * setiap laporannya."
 *
 * Jadi modul ini TIDAK menginput data sendiri: pengguna mengunduh laporan dari aplikasi e-PERPUS
 * (menu Laporan → tombol CSV, atau Data Anggota → Ekspor) lalu mengimpornya lewat `perpus_import.php`.
 * Isi berkas disimpan APA ADANYA (judul kolom + baris) sehingga laporan tetap utuh walau versi
 * e-PERPUS berubah — lihat tabel `perpus_laporan`/`perpus_baris` di inc/db.php.
 *
 * Satu himpunan data per (sekolah, jenis): impor ulang MENGGANTIKAN isi yang lama, dan ada tombol
 * "Kosongkan" bila ingin membersihkannya.
 *
 * **PENGAWAS (hanya lihat)**: pengawas sekolah/madrasah binaan boleh membuka laporan & mencetaknya,
 * tetapi tidak boleh mengimpor/mengosongkan (ditegakkan di sini + gerbang pengawas).
 *
 * Dipanggil oleh `tugas.php` bila menunya berkode `PERPUS_KODE_MENU`.
 */
declare(strict_types=1);

// ------------------------------------------------------------------ bantuan data

/** Daftar jenis laporan (urutan tampil = urutan pada PERPUS_JENIS di inc/config.php). */
function perpus_jenis_list(): array
{
    return PERPUS_JENIS;
}

function perpus_jenis_ada(string $jenis): bool
{
    return array_key_exists($jenis, PERPUS_JENIS);
}

function perpus_jenis_nama(string $jenis): string
{
    return PERPUS_JENIS[$jenis] ?? 'Laporan Perpustakaan';
}

/**
 * Judul kolom yang biasa ada pada berkas ekspor e-PERPUS untuk tiap laporan.
 *
 * HANYA dipakai sebagai PETUNJUK pada halaman impor (supaya pengguna yakin berkas yang dipilih sudah
 * benar). Impor sendiri tidak memetakan kolom — semuanya disimpan apa adanya.
 * Sumber: `perpustakaan/laporan_export.php` (CSV kelima laporan) dan `anggota_export.php`.
 */
function perpus_kolom_eperpus(string $jenis): array
{
    switch ($jenis) {
        case 'anggota':
            return ['No', 'Kode', 'Nama Anggota', 'JK', 'Kelas', 'Jenis', 'Kontak', 'Terdaftar',
                    'Sedang Pinjam', 'Total Pinjam', 'Status'];
        case 'buku':
            return ['No', 'Kode', 'Judul Buku', 'Pengarang', 'Penerbit', 'Tahun', 'Kategori', 'Total',
                    'Dipinjam', 'Tersedia', 'Lokasi', 'Status'];
        case 'peminjaman':
            return ['No', 'Kode', 'Tgl Pinjam', 'Anggota', 'Kelas', 'Judul Buku', 'Jatuh Tempo',
                    'Status', 'Terlambat', 'Petugas'];
        case 'pengembalian':
            return ['No', 'Kode', 'Anggota', 'Kelas', 'Judul Buku', 'Tgl Pinjam', 'Tgl Kembali',
                    'Terlambat', 'Denda', 'Kondisi', 'Petugas'];
        case 'pengunjung':
            return ['No', 'Hari', 'Tanggal', 'Jam', 'Nama Pengunjung', 'JK', 'Kelas', 'Keperluan', 'Saran'];
    }
    return [];
}

/** Bentuk normal teks untuk pembandingan judul kolom (huruf kecil, tanpa tanda baca/spasi/BOM). */
function perpus_norm(?string $s): string
{
    return (string) preg_replace('/[^a-z0-9]+/', '', strtolower((string) $s));
}

/**
 * Bersihkan satu nilai (judul kolom atau isi sel) dari berkas impor.
 *
 * Berkas CSV hasil Excel sering membawa BOM pada judul kolom pertama dan bisa memuat baris baru di
 * dalam sel; keduanya dibersihkan di sini. Panjangnya dibatasi supaya ukuran basis data & halaman
 * tetap wajar. Nilai yang bukan UTF-8 sah dinilaikan lebih dulu agar json_encode tidak gagal.
 */
function perpus_bersih($v, int $maks): string
{
    if (is_array($v) || is_object($v)) {
        return '';
    }
    $s = (string) $v;
    // Byte kendali 0x00-0x1F & 0x7F tidak pernah muncul di tengah karakter UTF-8, jadi aman dibuang
    // tanpa menghapus huruf beraksen.
    $s = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $s);
    $s = str_replace(["\xEF\xBB\xBF", "\xC2\xA0"], ['', ' '], $s);
    $s = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $s);
    $s = trim((string) preg_replace('/ {2,}/', ' ', $s));
    if ($s !== '' && !preg_match('//u', $s)) {
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
            if ($t !== false) {
                $s = $t;
            }
        }
        if (!preg_match('//u', $s)) {
            $s = (string) preg_replace('/[\x80-\xFF]/', '', $s);
        }
    }
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $maks, 'UTF-8');
    }
    if (strlen($s) <= $maks) {
        return $s;
    }
    $s = substr($s, 0, $maks);
    while ($s !== '' && !preg_match('//u', $s)) {
        $s = substr($s, 0, -1);
    }
    return $s;
}

/**
 * Judul kolom yang sah: dibersihkan, kolom kosong di ujung dibuang, kolom kosong di tengah diberi nama.
 *
 * @return array{kolom:array<int,string>,asli:int} `kolom` = judul yang dipakai (dibatasi
 *         PERPUS_MAKS_KOLOM), `asli` = jumlah kolom berkas sebenarnya (untuk pesan bila dipotong).
 */
function perpus_header_validasi($header): array
{
    if (!is_array($header)) {
        return ['kolom' => [], 'asli' => 0];
    }
    $mentah = [];
    foreach ($header as $h) {
        if (count($mentah) >= 200) {   // batas aman saat membaca masukan
            break;
        }
        $mentah[] = perpus_bersih($h, 60);
    }
    while ($mentah && trim((string) end($mentah)) === '') {
        array_pop($mentah);
    }
    $asli = count($mentah);
    $kolom = array_slice($mentah, 0, PERPUS_MAKS_KOLOM);
    foreach ($kolom as $i => $h) {
        if ($h === '') {
            $kolom[$i] = 'Kolom ' . ($i + 1);
        }
    }
    return ['kolom' => $kolom, 'asli' => $asli];
}

/** Teks pencarian yang disimpan bersama tiap baris (huruf kecil, digabung). */
function perpus_cari_teks(array $sel): string
{
    $t = implode(' ', array_map(static fn($v): string => (string) $v, $sel));
    $t = trim((string) preg_replace('/ {2,}/', ' ', $t));
    return function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
}

/** Satu himpunan laporan (dataset) untuk satu jenis pada sekolah yang sedang aktif. */
function perpus_laporan(string $jenis): ?array
{
    if (!perpus_jenis_ada($jenis)) {
        return null;
    }
    return one('SELECT * FROM perpus_laporan WHERE sekolah_id = ? AND jenis = ?', [sekolah_id(), $jenis]);
}

/** Judul kolom dataset (hasil decode kolom `header`). */
function perpus_header(array $laporan): array
{
    $h = json_decode((string) ($laporan['header'] ?? ''), true);
    if (!is_array($h)) {
        return [];
    }
    return array_values(array_map(static fn($v): string => (string) $v, $h));
}

/** Baris dataset (mentah, `isi` masih JSON) — dipakai halaman (berpaginasi) & impor. */
function perpus_baris(int $laporanId, string $cari = '', int $limit = 0, int $offset = 0): array
{
    if ($laporanId <= 0) {
        return [];
    }
    $w = ['laporan_id = ?'];
    $p = [$laporanId];
    $cari = trim($cari);
    if ($cari !== '') {
        $w[] = 'cari LIKE ?';
        $p[] = '%' . perpus_cari_teks([$cari]) . '%';
    }
    $sql = 'SELECT * FROM perpus_baris WHERE ' . implode(' AND ', $w) . ' ORDER BY urutan, id';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) max(0, $offset);
    }
    return all($sql, $p);
}

/** Satu baris (isi sel) dari hasil query perpus_baris(). */
function perpus_isi(array $row): array
{
    $isi = json_decode((string) ($row['isi'] ?? ''), true);
    if (!is_array($isi)) {
        return [];
    }
    return array_values(array_map(static fn($v): string => (string) $v, $isi));
}

/** Jumlah baris dataset (boleh disaring kata kunci). */
function perpus_jumlah_baris(int $laporanId, string $cari = ''): int
{
    if ($laporanId <= 0) {
        return 0;
    }
    $w = ['laporan_id = ?'];
    $p = [$laporanId];
    $cari = trim($cari);
    if ($cari !== '') {
        $w[] = 'cari LIKE ?';
        $p[] = '%' . perpus_cari_teks([$cari]) . '%';
    }
    return (int) val('SELECT COUNT(*) FROM perpus_baris WHERE ' . implode(' AND ', $w), $p, 0);
}

/**
 * Seluruh isi satu laporan (judul kolom + baris) untuk ditampilkan/dicetak.
 *
 * @return array{laporan:?array,header:array<int,string>,baris:array<int,array<int,string>>,total:int,cocok:int}
 */
function perpus_data(string $jenis, string $cari = ''): array
{
    $lap = perpus_laporan($jenis);
    if (!$lap) {
        return ['laporan' => null, 'header' => [], 'baris' => [], 'ids' => [], 'total' => 0, 'cocok' => 0];
    }
    $header = perpus_header($lap);
    $baris = [];
    $ids = [];
    foreach (perpus_baris((int) $lap['id'], $cari) as $r) {
        $ids[] = (int) $r['id'];
        $baris[] = perpus_isi($r);
    }
    return [
        'laporan' => $lap,
        'header'  => $header,
        'baris'   => $baris,
        'ids'     => $ids,     // id baris (sejajar dengan `baris`) — dipakai tombol Ubah/Hapus manual
        'total'   => (int) $lap['jumlah_baris'],
        'cocok'   => count($baris),
    ];
}

/** true bila judul kolom pertama adalah "No" — nomornya dicetak ulang berurutan (tetap rapi setelah disaring). */
function perpus_ada_no(array $header): bool
{
    return isset($header[0]) && perpus_norm($header[0]) === 'no';
}

/**
 * Kolom yang dijumlahkan pada ringkasan/lembar cetak (dikenali dari JUDUL kolomnya).
 *
 * Hanya diisi untuk laporan yang angkanya memang bermakna dijumlahkan — jangan menambahkan kolom
 * seperti Tahun/Tanggal/Kode yang akan menghasilkan "jumlah" tak berarti.
 */
function perpus_kolom_jumlah(string $jenis): array
{
    switch ($jenis) {
        case 'anggota':
            return ['Sedang Pinjam', 'Total Pinjam'];
        case 'buku':
            return ['Total', 'Dipinjam', 'Tersedia'];
        case 'pengembalian':
            return ['Denda'];
        default:
            return [];
    }
}

/**
 * Jumlahkan kolom-kolom pada `perpus_kolom_jumlah()`.
 *
 * @return array<string,array{kolom:int,nilai:int,rp:bool}>
 */
function perpus_totals(array $header, array $baris, string $jenis): array
{
    $namaKolom = perpus_kolom_jumlah($jenis);
    if (!$namaKolom || !$header) {
        return [];
    }
    $idx = [];
    foreach ($header as $i => $h) {
        foreach ($namaKolom as $nama) {
            if (!isset($idx[$nama]) && perpus_norm($h) === perpus_norm($nama)) {
                $idx[$nama] = (int) $i;
            }
        }
    }
    $keluar = [];
    foreach ($idx as $nama => $i) {
        $sum = 0;
        $rp = false;
        $adaAngka = false;
        foreach ($baris as $row) {
            $v = trim((string) ($row[$i] ?? ''));
            if ($v === '') {
                continue;
            }
            if (stripos($v, 'rp') !== false) {
                $rp = true;
            }
            if (preg_match('/\d/', $v)) {
                $adaAngka = true;
            }
            $bersih = (string) preg_replace('/[^0-9\-]/', '', $v);
            $sum += (int) ($bersih === '' || $bersih === '-' ? 0 : $bersih);
        }
        if (!$adaAngka) {
            continue;
        }
        $keluar[$nama] = ['kolom' => $i, 'nilai' => $sum, 'rp' => $rp];
    }
    return $keluar;
}

/** Tulisan angka untuk ringkasan/jumlah ("Rp 1.000" bila kolomnya berisi nilai rupiah). */
function perpus_angka(int $nilai, bool $rp): string
{
    return ($rp ? 'Rp ' : '') . r0($nilai);
}

/**
 * Perbarui `jumlah_baris` sebuah himpunan data (dipakai setelah input manual menambah/menghapus baris).
 */
function perpus_jumlah_ulang(int $laporanId): void
{
    if ($laporanId <= 0) {
        return;
    }
    q('UPDATE perpus_laporan SET jumlah_baris = (SELECT COUNT(*) FROM perpus_baris WHERE laporan_id = ?)
       WHERE id = ?', [$laporanId, $laporanId]);
}

/** Hapus satu himpunan data (dipakai tombol "Kosongkan"). */
function perpus_hapus(string $jenis): void
{
    $lap = perpus_laporan($jenis);
    if (!$lap) {
        return;
    }
    db()->exec('BEGIN IMMEDIATE');
    try {
        q('DELETE FROM perpus_baris WHERE laporan_id = ?', [(int) $lap['id']]);
        q('DELETE FROM perpus_laporan WHERE id = ?', [(int) $lap['id']]);
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
}

/**
 * Simpan hasil impor: MENGGANTIKAN isi laporan jenis itu pada sekolah ini.
 *
 * @param array<int,mixed> $headerMentah judul kolom dari berkas
 * @param array<int,mixed> $rowsMentah   baris-baris dari berkas
 * @return array{ok:bool,galat:string,baris:int,kolom:int,kolom_berkas:int,kosong:int,dilewati:int}
 */
function perpus_simpan(string $jenis, string $berkas, $headerMentah, $rowsMentah): array
{
    $hasil = ['ok' => false, 'galat' => '', 'baris' => 0, 'kolom' => 0, 'kolom_berkas' => 0,
              'kosong' => 0, 'dilewati' => 0];
    if (!perpus_jenis_ada($jenis)) {
        $hasil['galat'] = 'Jenis laporan tidak dikenal.';
        return $hasil;
    }
    $validasi = perpus_header_validasi($headerMentah);
    $header = $validasi['kolom'];
    $hasil['kolom_berkas'] = (int) $validasi['asli'];
    if (!$header) {
        $hasil['galat'] = 'Judul kolom laporan tidak terbaca. Pastikan baris judul kolom pada berkas dipilih dengan benar.';
        return $hasil;
    }
    $kolom = count($header);

    $baris = [];
    $kosong = 0;
    $dilewati = 0;
    foreach ((array) $rowsMentah as $r) {
        if (!is_array($r)) {
            continue;
        }
        $sel = [];
        for ($i = 0; $i < $kolom; $i++) {
            $sel[] = perpus_bersih($r[$i] ?? '', PERPUS_SEL_MAKS);
        }
        if (implode('', $sel) === '') {
            $kosong++;
            continue;
        }
        if (count($baris) >= PERPUS_MAKS_BARIS) {
            $dilewati++;
            continue;
        }
        $baris[] = $sel;
    }
    if (!$baris) {
        $hasil['galat'] = 'Tidak ada baris data yang bisa diimpor dari berkas itu.';
        return $hasil;
    }

    $berkas = perpus_bersih($berkas, 160);
    $user = current_user();
    $now = date('Y-m-d H:i:s');

    db()->exec('BEGIN IMMEDIATE');
    try {
        $lama = one('SELECT id FROM perpus_laporan WHERE sekolah_id = ? AND jenis = ?', [sekolah_id(), $jenis]);
        if ($lama) {
            q('DELETE FROM perpus_baris WHERE laporan_id = ?', [(int) $lama['id']]);
            q('DELETE FROM perpus_laporan WHERE id = ?', [(int) $lama['id']]);
        }
        q('INSERT INTO perpus_laporan (sekolah_id, jenis, berkas, sumber, header, jumlah_baris, diimpor, oleh)
           VALUES (?,?,?,?,?,?,?,?)',
            [sekolah_id(), $jenis, $berkas, 'e-PERPUS',
             (string) json_encode($header, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
             count($baris), $now, (string) ($user['nama'] ?? '')]);
        $id = (int) db()->lastInsertId();
        $ins = db()->prepare('INSERT INTO perpus_baris (laporan_id, urutan, isi, cari) VALUES (?,?,?,?)');
        $u = 0;
        foreach ($baris as $sel) {
            $u += 10;
            $json = json_encode($sel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                $json = json_encode(array_map(static fn($v): string => preg_replace('/[^\x20-\x7E]/', '', $v), $sel));
            }
            $ins->execute([$id, $u, $json === false ? '[]' : $json, perpus_cari_teks($sel)]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }

    $hasil['ok'] = true;
    $hasil['baris'] = count($baris);
    $hasil['kolom'] = $kolom;
    $hasil['kosong'] = $kosong;
    $hasil['dilewati'] = $dilewati;
    return $hasil;
}

/** Jumlah baris tiap jenis (chip tab) — satu query ringan untuk seluruh jenis. */
function perpus_ringkas_jenis(): array
{
    $keluar = [];
    foreach (array_keys(PERPUS_JENIS) as $j) {
        $keluar[$j] = 0;
    }
    foreach (all('SELECT jenis, jumlah_baris FROM perpus_laporan WHERE sekolah_id = ?', [sekolah_id()]) as $r) {
        $keluar[(string) $r['jenis']] = (int) $r['jumlah_baris'];
    }
    return $keluar;
}

// ------------------------------------------------------------------ IZIN IMPOR e-PERPUS
//
// Permintaan pemilik (v1.93.0): *"Impor dari e-Perpus itu hanya berlaku untuk MA. Sirojuth Tholibiin
// Mootilango. Madrasah/sekolah lain tidak bisa melakukan impor dari e-Perus tapi mereka bisa mengisi
// atau menginput manual"*.
//
// Daftar sekolahnya disimpan di tingkat APLIKASI (`app_pengaturan` kunci `perpus_impor_sekolah`),
// disemai SEKALI dari `PERPUS_IMPOR_NAMA` (inc/config.php) lalu dapat diubah Super Admin. Sekolah di
// luar daftar tetap bisa MENGISI laporan secara manual — lihat bagian INPUT MANUAL di bawah.

/**
 * ID sekolah/madrasah yang boleh MENGIMPOR laporan dari e-PERPUS.
 *
 * Penyemaian pertama memakai nama pada `PERPUS_IMPOR_NAMA` (dinormalkan `sekolah_nama_kunci()`),
 * setelah itu nilai tersimpan dipakai apa adanya supaya suntingan Super Admin tidak pernah ditimpa.
 *
 * @return array<int,int>
 */
function perpus_impor_sekolah_ids(): array
{
    $mentah = app_setting('perpus_impor_sekolah', '');
    if (app_setting('perpus_impor_seed', '') === '') {
        $ids = [];
        // Nama pada konstanta dinormalkan lebih dulu — `sekolah_nama_kunci()` membuang SEMUA tanda
        // baca & spasi ("MA. Sirojuth …" → "masirojuththolibiinmootilango"), jadi pembandingannya
        // harus memakai bentuk yang sama di kedua sisi.
        $baku = array_map(static fn($n): string => sekolah_nama_kunci((string) $n), PERPUS_IMPOR_NAMA);
        try {
            foreach (all('SELECT id, nama FROM sekolah ORDER BY id') as $s) {
                if (in_array(sekolah_nama_kunci((string) $s['nama']), $baku, true)) {
                    $ids[] = (int) $s['id'];
                }
            }
            app_setting_set('perpus_impor_sekolah', implode(',', $ids));
            app_setting_set('perpus_impor_seed', date('Y-m-d H:i:s'));
        } catch (Throwable $ex) {
            // Tabel belum siap → jangan gagalkan halaman; daftar kosong = hanya input manual.
        }
        return $ids;
    }
    $ids = [];
    foreach (explode(',', $mentah) as $v) {
        $n = (int) trim($v);
        if ($n > 0 && !in_array($n, $ids, true)) {
            $ids[] = $n;
        }
    }
    return $ids;
}

/**
 * Simpan daftar sekolah yang boleh mengimpor (hanya Super Admin yang memanggil ini).
 *
 * @param array<int,mixed> $ids
 */
function perpus_impor_sekolah_simpan(array $ids): void
{
    $bersih = [];
    foreach ($ids as $v) {
        $n = (int) $v;
        if ($n > 0 && !in_array($n, $bersih, true)) {
            $bersih[] = $n;
        }
    }
    if ($bersih) {
        $tanda = implode(',', array_fill(0, count($bersih), '?'));
        $sah = [];
        foreach (all('SELECT id FROM sekolah WHERE id IN (' . $tanda . ')', $bersih) as $r) {
            $sah[] = (int) $r['id'];
        }
        $bersih = $sah;
    }
    app_setting_set('perpus_impor_sekolah', implode(',', $bersih));
    app_setting_set('perpus_impor_seed', date('Y-m-d H:i:s'));
}

/** true bila sekolah ini (bawaan: konteks) boleh MENGIMPOR laporan dari e-PERPUS. */
function perpus_impor_boleh(?int $sekolahId = null): bool
{
    $sid = $sekolahId ?? sekolah_id();
    if ($sid <= 0) {
        return false;
    }
    return in_array($sid, perpus_impor_sekolah_ids(), true);
}

/** Nama sekolah/madrasah yang boleh mengimpor, dipisah koma (untuk keterangan di halaman). */
function perpus_impor_sekolah_nama(): string
{
    $ids = perpus_impor_sekolah_ids();
    if (!$ids) {
        return '';
    }
    $nama = [];
    $tanda = implode(',', array_fill(0, count($ids), '?'));
    foreach (all('SELECT nama FROM sekolah WHERE id IN (' . $tanda . ') ORDER BY nama', $ids) as $r) {
        $nama[] = (string) $r['nama'];
    }
    return implode(', ', $nama);
}

/** Tulisan sumber data sebuah himpunan laporan ("e-PERPUS" / "Input manual"). */
function perpus_sumber_teks(?array $laporan): string
{
    $s = strtolower(trim((string) ($laporan['sumber'] ?? '')));
    return $s === 'eperpus' || $s === 'e-perpus' ? 'e-PERPUS' : 'Input manual';
}

// ------------------------------------------------------------------ INPUT MANUAL
//
// Sekolah/madrasah yang TIDAK termasuk daftar impor mengisi kelima laporan perpustakaan sendiri.
// Datanya memakai bentuk yang SAMA dengan hasil impor (judul kolom pada `header` + baris JSON),
// sehingga pencarian, ringkasan jumlah, dan lembar cetak bekerja tanpa perbedaan. Judul kolomnya
// memakai kolom BAKU laporan e-PERPUS (`perpus_kolom_eperpus()`) supaya bentuk laporannya tetap sama.

/** Judul kolom baku untuk pengisian manual satu jenis laporan. */
function perpus_manual_kolom(string $jenis): array
{
    $k = perpus_kolom_eperpus($jenis);
    return $k ?: ['No', 'Uraian'];
}

/**
 * Pastikan himpunan data untuk satu jenis ADA (dibuat dengan kolom baku bila belum ada).
 *
 * @return array<string,mixed> baris `perpus_laporan`
 */
function perpus_manual_siap(string $jenis): array
{
    $lap = perpus_laporan($jenis);
    if ($lap) {
        return $lap;
    }
    $header = perpus_manual_kolom($jenis);
    $user = current_user();
    q('INSERT INTO perpus_laporan (sekolah_id, jenis, berkas, sumber, header, jumlah_baris, diimpor, oleh)
       VALUES (?,?,?,?,?,?,?,?)',
        [sekolah_id(), $jenis, '', 'Manual',
         (string) json_encode($header, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
         0, date('Y-m-d H:i:s'), (string) ($user['nama'] ?? '')]);
    return perpus_laporan($jenis) ?? [];
}

/** Satu baris dataset berdasarkan id — dibatasi sekolah + jenis laporan (anti-IDOR). */
function perpus_baris_by_id(string $jenis, int $barisId): ?array
{
    $lap = perpus_laporan($jenis);
    if (!$lap || $barisId <= 0) {
        return null;
    }
    return one('SELECT * FROM perpus_baris WHERE id = ? AND laporan_id = ?', [$barisId, (int) $lap['id']]);
}

/**
 * Susun isi satu baris dari isian formulir manual.
 *
 * Formulir TIDAK menanyakan kolom nomor ("No") — nomornya selalu dicetak ulang berurutan — jadi
 * daftar nilai dari formulir digeser satu posisi bila judul kolom pertamanya "No".
 *
 * @param array<int,string> $header judul kolom dataset
 * @param array<int,mixed>  $nilai  nilai per kolom sesuai urutan formulir
 * @return array<int,string> isi baris sepanjang `$header`
 */
function perpus_manual_sel(array $header, array $nilai): array
{
    $nilai = array_values($nilai);
    $sel = [];
    foreach ($header as $i => $h) {
        $j = perpus_ada_no($header) ? $i - 1 : $i;
        $sel[] = $j < 0 ? '' : perpus_bersih($nilai[$j] ?? '', PERPUS_SEL_MAKS);
    }
    return $sel;
}

/**
 * Tambah (atau ubah) SATU baris laporan secara manual.
 *
 * @param array<int,mixed> $nilai nilai per kolom formulir (tanpa kolom "No")
 * @return array{ok:bool,galat:string,id:int,diubah:bool}
 */
function perpus_manual_simpan(string $jenis, array $nilai, int $barisId = 0): array
{
    $hasil = ['ok' => false, 'galat' => '', 'id' => 0, 'diubah' => false];
    if (!perpus_jenis_ada($jenis)) {
        $hasil['galat'] = 'Jenis laporan tidak dikenal.';
        return $hasil;
    }
    $lap = perpus_manual_siap($jenis);
    $header = perpus_header($lap);
    if (!$header) {
        $hasil['galat'] = 'Judul kolom laporan ini tidak terbaca. Kosongkan data lalu isi ulang.';
        return $hasil;
    }
    $sel = perpus_manual_sel($header, $nilai);
    if (implode('', $sel) === '') {
        $hasil['galat'] = 'Isi minimal satu kolom — baris kosong tidak disimpan.';
        return $hasil;
    }
    $laporanId = (int) $lap['id'];
    $json = (string) json_encode($sel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $cari = perpus_cari_teks($sel);
    $user = current_user();
    $now = date('Y-m-d H:i:s');

    $lama = $barisId > 0 ? perpus_baris_by_id($jenis, $barisId) : null;
    db()->exec('BEGIN IMMEDIATE');
    try {
        if ($lama) {
            q('UPDATE perpus_baris SET isi = ?, cari = ? WHERE id = ?', [$json, $cari, (int) $lama['id']]);
            $hasil['id'] = (int) $lama['id'];
            $hasil['diubah'] = true;
        } else {
            $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) FROM perpus_baris WHERE laporan_id = ?',
                [$laporanId], 0) + 10;
            q('INSERT INTO perpus_baris (laporan_id, urutan, isi, cari) VALUES (?,?,?,?)',
                [$laporanId, $urutan, $json, $cari]);
            $hasil['id'] = (int) db()->lastInsertId();
        }
        q('UPDATE perpus_laporan SET diimpor = ?, oleh = ? WHERE id = ?',
            [$now, (string) ($user['nama'] ?? ''), $laporanId]);
        perpus_jumlah_ulang($laporanId);
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    $hasil['ok'] = true;
    return $hasil;
}

/** Hapus SATU baris laporan hasil input manual (anti-IDOR: harus milik sekolah + jenis ini). */
function perpus_manual_hapus(string $jenis, int $barisId): bool
{
    $lama = perpus_baris_by_id($jenis, $barisId);
    if (!$lama) {
        return false;
    }
    $lap = perpus_laporan($jenis);
    $laporanId = (int) ($lap['id'] ?? 0);
    db()->exec('BEGIN IMMEDIATE');
    try {
        q('DELETE FROM perpus_baris WHERE id = ?', [(int) $lama['id']]);
        perpus_jumlah_ulang($laporanId);
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return true;
}

/** URL halaman menu ini beserta pilihan jenis & saringan aktif. */
function perpus_url(string $jenis = '', string $cari = '', int $hal = 0): string
{
    $q = [];
    if ($jenis !== '') {
        $q['jenis'] = $jenis;
    }
    if (trim($cari) !== '') {
        $q['cari'] = $cari;
    }
    if ($hal > 1) {
        $q['hal'] = $hal;
    }
    return 'tugas.php?kode=' . PERPUS_KODE_MENU . ($q ? '&' . http_build_query($q) : '');
}

// ------------------------------------------------------------------ halaman

/**
 * Jalankan halaman Laporan Perpustakaan (dipakai `tugas.php?kode=kepala_perpus`).
 *
 * @param array<string,mixed> $tugas baris `tugas_menu` (judul mengikuti nama menu hasil ubah Super Admin)
 */
function perpus_halaman(array $tugas): void
{
    require_sekolah();

    $namaMenu = (string) ($tugas['nama'] ?? 'Ka. Perpustakaan');
    // Pengawas = HANYA LIHAT & CETAK (aturan yang sama dengan menu Tugas Tambahan lain).
    // Permintaan pemilik: kepala sekolah/madrasah & admin operator HANYA melihat + mencetak;
    // pengisian menu Tugas Tambahan dilakukan guru yang ditugaskan (lihat boleh_ubah_tugas_menu()).
    $bolehUbah = boleh_ubah_tugas_menu();
    if (!$bolehUbah && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        tolak_ubah_tugas_menu('laporan perpustakaan');
    }

    // ---------------------------------------------------------------- POST (kosongkan data)
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        $jenisAksi = (string) ($_POST['jenis'] ?? '');
        if ($aksi === 'kosongkan' && perpus_jenis_ada($jenisAksi)) {
            perpus_hapus($jenisAksi);
            flash('Data ' . perpus_jenis_nama($jenisAksi) . ' dikosongkan. Isi kembali secara manual'
                . (perpus_impor_boleh() ? ' atau impor ulang dari e-PERPUS' : '') . ' bila diperlukan.', 'warn');
            redirect(perpus_url($jenisAksi));
        }

        // INPUT MANUAL (v1.93.0) — menambah / mengubah SATU baris laporan.
        if ($aksi === 'manual_simpan' && perpus_jenis_ada($jenisAksi)) {
            $hasil = perpus_manual_simpan($jenisAksi, (array) ($_POST['sel'] ?? []),
                (int) ($_POST['baris'] ?? 0));
            if (!$hasil['ok']) {
                flash($hasil['galat'], 'err');
            } else {
                flash('Baris ' . perpus_jenis_nama($jenisAksi) . ' ' . ($hasil['diubah'] ? 'diperbarui' : 'ditambahkan')
                    . '. Isi terus baris berikutnya, atau cetak laporannya bila sudah lengkap.', 'ok');
            }
            redirect(perpus_url($jenisAksi));
        }

        // INPUT MANUAL — menghapus SATU baris laporan.
        if ($aksi === 'manual_hapus' && perpus_jenis_ada($jenisAksi)) {
            if (perpus_manual_hapus($jenisAksi, (int) ($_POST['baris'] ?? 0))) {
                flash('Satu baris ' . perpus_jenis_nama($jenisAksi) . ' dihapus.', 'warn');
            } else {
                flash('Baris yang ingin dihapus tidak ditemukan — mungkin sudah dihapus sebelumnya.', 'err');
            }
            redirect(perpus_url($jenisAksi));
        }

        // PENGATURAN SUPER ADMIN: sekolah/madrasah yang boleh mengimpor dari e-PERPUS.
        if ($aksi === 'impor_sekolah_simpan') {
            if (!is_super()) {
                tolak403('Hanya Super Admin yang dapat mengatur sekolah/madrasah yang boleh mengimpor dari e-PERPUS.');
            }
            perpus_impor_sekolah_simpan((array) ($_POST['sekolah'] ?? []));
            $daftar = perpus_impor_sekolah_nama();
            flash('Penyetelan impor e-PERPUS disimpan. ' . ($daftar === ''
                ? 'Tidak ada sekolah/madrasah yang boleh mengimpor — seluruhnya mengisi laporan secara manual.'
                : 'Yang boleh mengimpor: ' . $daftar . '. Sekolah/madrasah lain mengisi laporan secara manual.'), 'ok');
            redirect(perpus_url($jenisAksi));
        }

        redirect(perpus_url());
    }

    $jenis = (string) ($_GET['jenis'] ?? '');
    if (!perpus_jenis_ada($jenis)) {
        $kunci = array_keys(PERPUS_JENIS);
        $jenis = (string) $kunci[0];
    }
    $cari = trim((string) ($_GET['cari'] ?? ''));
    $hal = max(1, (int) ($_GET['hal'] ?? 1));

    $D = perpus_data($jenis, $cari);
    $laporan = $D['laporan'];
    $header = $D['header'];
    $semua = $D['baris'];
    $cocok = $D['cocok'];
    $total = $D['total'];
    $perHalaman = PERPUS_PER_HALAMAN;
    $halamanTotal = max(1, (int) ceil($cocok / $perHalaman));
    if ($hal > $halamanTotal) {
        $hal = $halamanTotal;
    }
    $tampil = array_slice($semua, ($hal - 1) * $perHalaman, $perHalaman);
    $tampilId = array_slice($D['ids'], ($hal - 1) * $perHalaman, $perHalaman);
    $totals = perpus_totals($header, $semua, $jenis);
    $adaNo = perpus_ada_no($header);
    $jumlahJenis = perpus_ringkas_jenis();
    $namaSekolah = nama_sekolah_tampil();

    // Impor dari e-PERPUS hanya untuk sekolah/madrasah pada daftar (permintaan pemilik v1.93.0);
    // sekolah lain mengisi laporan lewat INPUT MANUAL di halaman ini.
    $bolehImpor = perpus_impor_boleh();
    $imporNama = perpus_impor_sekolah_nama();
    // Kolom yang diisikan pada formulir manual = judul kolom dataset (kolom "No" tidak ditanyakan).
    $kolomManual = $laporan ? $header : perpus_manual_kolom($jenis);
    $adaNoManual = perpus_ada_no($kolomManual);
    if ($adaNoManual) {
        $kolomManual = array_slice($kolomManual, 1);
    }
    $editId = (int) ($_GET['edit'] ?? 0);
    $editRow = $editId > 0 ? perpus_baris_by_id($jenis, $editId) : null;
    $editSel = $editRow ? perpus_isi($editRow) : [];
    if ($adaNoManual && $editSel) {
        $editSel = array_slice($editSel, 1);   // kolom "No" tidak ditampilkan pada formulir
    }

    page_top($namaMenu, 'Laporan Perpustakaan &middot; ' . tetap_kata($namaSekolah));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('book', 16) ?> <?= e($namaMenu) ?> &mdash; Laporan Perpustakaan</h2>
      <p>Lima laporan perpustakaan <?= tetap_kata($namaSekolah) ?>:
        <?php if ($bolehImpor): ?>
          <b>diimpor dari hasil unduhan aplikasi e-PERPUS</b> atau diisi manual, lengkap dengan fitur
          cetak untuk setiap laporan.
        <?php else: ?>
          <b>diisi secara manual</b> pada halaman ini (impor dari e-PERPUS hanya berlaku untuk
          <?= $imporNama !== '' ? tetap_kata($imporNama) : 'sekolah/madrasah yang ditetapkan Admin' ?>),
          lengkap dengan fitur cetak untuk setiap laporan.
        <?php endif; ?>
      </p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $total ?> baris pada laporan ini</span>
      <?php if ($laporan): ?>
        <a class="btn btn-ghost btn-sm" href="cetak_perpus.php?jenis=<?= e($jenis) ?><?= $cari !== '' ? '&cari=' . e(rawurlencode($cari)) : '' ?>" target="_blank"><?= ikon('print', 15) ?> Cetak <?= e(perpus_jenis_nama($jenis)) ?></a>
      <?php endif; ?>
      <?php if ($bolehUbah): ?>
        <a class="btn btn-primary btn-sm" href="#cardManual"><?= ikon('plus', 15) ?> Isi Manual</a>
        <?php if ($bolehImpor): ?>
          <a class="btn btn-ghost btn-sm" href="perpus_import.php?jenis=<?= e($jenis) ?>"><?= ikon('upload', 15) ?> Impor dari e-PERPUS</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$bolehUbah): ?>
    <div class="alert alert-warn" style="margin-bottom:16px">
      <?= ikon('eye', 16) ?>
      <span><?= pemberitahuan_hanya_lihat('laporan perpustakaan') ?></span>
    </div>
  <?php endif; ?>

  <?php // ---------------------------------------------------------------- tab kelima laporan ?>
  <div class="chip-list warna mb16">
    <?php foreach (perpus_jenis_list() as $k => $nm): ?>
      <a class="chip <?= $k === $jenis ? 'on' : '' ?>" href="<?= e(perpus_url((string) $k)) ?>">
        <?= ikon($k === 'buku' ? 'book' : ($k === 'anggota' ? 'users' : 'report'), 14) ?>
        <?= e($nm) ?>
        <span class="xs mut">(<?= (int) ($jumlahJenis[$k] ?? 0) ?>)</span>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (!$laporan || ($total === 0 && $cari === '')): ?>
    <div class="empty">
      <?= ikon($bolehImpor ? 'upload' : 'edit', 38) ?>
      <h3>Belum ada data <?= e(perpus_jenis_nama($jenis)) ?></h3>
      <?php // Petunjuk pengisian hanya untuk yang BISA mengubah (guru yang ditugaskan / Super Admin);
            // kepala & operator hanya melihat, jadi diberi keterangan singkat. ?>
      <p><?= $bolehUbah
        ? ($bolehImpor
            ? 'Laporan ini belum diisi. Pilih salah satu cara di bawah (impor dari e-PERPUS atau isi manual):'
            : 'Laporan ini belum diisi. Impor dari e-PERPUS tidak tersedia untuk '
              . tetap_kata($namaSekolah) . ', jadi isilah datanya secara manual:')
        : pesan_kosong_hanya_lihat('laporan ' . perpus_jenis_nama($jenis)) ?></p>
      <?php if ($bolehUbah && $bolehImpor): ?>
      <ol class="list-ok" style="text-align:left;max-width:640px;margin:14px auto 0">
        <li>Buka aplikasi <b>e-PERPUS</b> → menu <b>Laporan</b> → pilih
          <b><?= e(perpus_jenis_nama($jenis)) ?></b> (untuk Data Anggota bisa juga lewat
          <b>Data Anggota → Ekspor Data Anggota</b>).</li>
        <li>Klik tombol <b>CSV</b> (atau <b>Unduh Excel</b>) sampai berkas laporannya terunduh.</li>
        <li>Kembali ke halaman ini, klik <b>Impor dari e-PERPUS</b>, pilih berkasnya, periksa
          pratinjaunya, lalu klik <b>Impor Sekarang</b>.</li>
      </ol>
      <?php elseif ($bolehUbah): ?>
      <ol class="list-ok" style="text-align:left;max-width:640px;margin:14px auto 0">
        <li>Gulir ke kartu <b>Input Manual — <?= e(perpus_jenis_nama($jenis)) ?></b> di bagian bawah
          halaman ini.</li>
        <li>Isi kolom <b><?= e(implode(', ', $kolomManual)) ?></b> untuk satu baris data, lalu klik
          <b>Simpan Baris</b>.</li>
        <li>Ulangi untuk baris berikutnya — tombol <b>Ubah</b>/<b>Hapus</b> tersedia pada tabel di
          atas. Cetak laporannya bila sudah lengkap.</li>
      </ol>
      <?php endif; ?>
      <?php if ($bolehUbah): ?>
        <div class="flex" style="justify-content:center">
          <a class="btn btn-primary" href="#cardManual"><?= ikon('plus', 16) ?> Isi Manual Sekarang</a>
          <?php if ($bolehImpor): ?>
            <a class="btn btn-ghost" href="perpus_import.php?jenis=<?= e($jenis) ?>"><?= ikon('upload', 16) ?> Impor dari e-PERPUS</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-ok" style="background:rgba(201,162,39,.07);border-color:rgba(201,162,39,.45)">
      <?= ikon('doc', 16) ?>
      <span>Sumber data: <b><?= e(perpus_sumber_teks($laporan)) ?></b>
        <?php if (trim((string) $laporan['berkas']) !== ''): ?>
          &middot; berkas <b><?= e((string) $laporan['berkas']) ?></b>
        <?php endif; ?>
        &middot; diperbarui <?= waktu_id((string) $laporan['diimpor']) ?>
        <?= trim((string) $laporan['oleh']) !== '' ? 'oleh ' . e((string) $laporan['oleh']) : '' ?>
        &middot; <?= (int) $laporan['jumlah_baris'] ?> baris, <?= count($header) ?> kolom.
        <?php if ($bolehUbah && $bolehImpor): ?>Impor ulang akan <b>menggantikan</b> data laporan ini.<?php endif; ?></span>
    </div>

    <div class="flex mb16" style="flex-wrap:wrap;gap:10px;align-items:flex-end">
      <form method="get" class="flex" style="gap:8px;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="kode" value="<?= e(PERPUS_KODE_MENU) ?>">
        <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
        <div>
          <label class="f" for="cari">Cari pada laporan ini</label>
          <input type="text" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="mis. nama anggota, judul buku, kelas…">
        </div>
        <button class="btn btn-primary" type="submit"><?= ikon('search', 15) ?> Cari</button>
        <?php if ($cari !== ''): ?>
          <a class="btn btn-ghost" href="<?= e(perpus_url($jenis)) ?>"><?= ikon('prev', 15) ?> Reset</a>
        <?php endif; ?>
      </form>
    </div>

    <?php if ($totals): ?>
      <div class="flex mb16" style="flex-wrap:wrap;gap:10px">
        <?php foreach ($totals as $nm => $t): ?>
          <span class="chip"><?= e($nm) ?>: <b><?= e(perpus_angka((int) $t['nilai'], (bool) $t['rp'])) ?></b></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$cocok): ?>
      <div class="empty"><?= ikon('search', 34) ?>
        <h3>Tidak ada baris yang cocok</h3>
        <p>Kata kunci <b><?= e($cari) ?></b> tidak ditemukan pada <?= e(perpus_jenis_nama($jenis)) ?>.
          <a href="<?= e(perpus_url($jenis)) ?>">Tampilkan seluruh baris</a>.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap" id="tabelLaporan">
        <table class="tbl tbl-perpus">
          <thead>
            <tr>
              <?php if ($adaNo): ?>
                <th class="ctr">No</th>
                <?php foreach (array_slice($header, 1) as $h): ?><th><?= e($h) ?></th><?php endforeach; ?>
              <?php else: ?>
                <th class="ctr">No</th>
                <?php foreach ($header as $h): ?><th><?= e($h) ?></th><?php endforeach; ?>
              <?php endif; ?>
              <?php if ($bolehUbah): ?><th class="ctr" style="width:132px">Tindakan</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
          <?php
          $no = ($hal - 1) * $perHalaman;
          foreach ($tampil as $barisKe => $row):
              $no++;
              $sel = $row;
              if ($adaNo) {
                  array_shift($sel);
              }
          ?>
            <tr>
              <td class="ctr"><?= $no ?></td>
              <?php foreach ($sel as $v): ?>
                <td class="potong" title="<?= e((string) $v) ?>"><?= e((string) $v) ?></td>
              <?php endforeach; ?>
              <?php if ($bolehUbah): $barisId = (int) ($tampilId[$barisKe] ?? 0); ?>
                <td class="ctr">
                  <div class="row-actions">
                    <?php if ($barisId > 0): ?>
                      <a class="btn btn-ghost btn-sm" href="<?= e(perpus_url($jenis)) ?>&edit=<?= $barisId ?>#cardManual"><?= ikon('edit', 13) ?> Ubah</a>
                      <form method="post" data-confirm="Hapus satu baris <?= e(perpus_jenis_nama($jenis)) ?> ini?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="manual_hapus">
                        <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
                        <input type="hidden" name="baris" value="<?= $barisId ?>">
                        <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 13) ?> Hapus</button>
                      </form>
                    <?php else: ?>
                      <span class="mut xs">—</span>
                    <?php endif; ?>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <?php if ($totals): ?>
            <tfoot>
              <tr>
                <th class="ctr">Jumlah</th>
                <?php
                $mulai = $adaNo ? 1 : 0;
                for ($i = $mulai; $i < count($header); $i++):
                    $t = null;
                    foreach ($totals as $info) {
                        if ((int) $info['kolom'] === $i) {
                            $t = $info;
                            break;
                        }
                    }
                ?>
                  <th class="<?= $t ? 'ctr' : '' ?>"><?= $t ? e(perpus_angka((int) $t['nilai'], (bool) $t['rp'])) : '' ?></th>
                <?php endfor; ?>
                <?php if ($bolehUbah): ?><th></th><?php endif; ?>
              </tr>
            </tfoot>
          <?php endif; ?>
        </table>
      </div>
      <p class="xs mut mt8">
        Menampilkan baris <?= (int) (($hal - 1) * $perHalaman + 1) ?>&ndash;<?= (int) (($hal - 1) * $perHalaman + count($tampil)) ?>
        dari <?= (int) $cocok ?> baris<?= $cari !== '' ? ' (hasil pencarian)' : '' ?><?= $cocok < $total ? ' — total ' . (int) $total . ' baris pada laporan ini' : '' ?>.
        Lembar cetak memuat seluruh baris yang tampil.
      </p>

      <?php if ($halamanTotal > 1): ?>
        <div class="flex mt16" style="justify-content:space-between;flex-wrap:wrap;gap:10px">
          <?php if ($hal > 1): ?>
            <a class="btn btn-ghost" href="<?= e(perpus_url($jenis, $cari, $hal - 1)) ?>#tabelLaporan"><?= ikon('prev', 15) ?> Sebelumnya</a>
          <?php else: ?><span></span><?php endif; ?>
          <span class="chip">Halaman <?= (int) $hal ?> dari <?= (int) $halamanTotal ?> &middot; <?= (int) $perHalaman ?> baris per halaman</span>
          <?php if ($hal < $halamanTotal): ?>
            <a class="btn btn-primary" href="<?= e(perpus_url($jenis, $cari, $hal + 1)) ?>#tabelLaporan">Selanjutnya: <?= (int) min($perHalaman, $cocok - $hal * $perHalaman) ?> baris berikutnya <?= ikon('next', 15) ?></a>
          <?php else: ?><span></span><?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($bolehUbah): ?>
      <div class="divider"></div>
      <form method="post" class="flex" style="align-items:center;gap:10px;flex-wrap:wrap">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="kosongkan">
        <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
        <span class="xs mut">Hapus seluruh data <?= e(perpus_jenis_nama($jenis)) ?> pada
          <?= tetap_kata($namaSekolah) ?>? Lembar cetak ikut kosong sampai datanya diisi lagi.</span>
        <button class="btn btn-ghost btn-sm" type="submit"
                data-confirm="Kosongkan seluruh data <?= e(perpus_jenis_nama($jenis)) ?>?"><?= ikon('trash', 15) ?> Kosongkan Data</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php if ($bolehUbah): ?>
<?php // ---------------------------------------------------------------- INPUT MANUAL ?>
<div class="card" id="cardManual">
  <div class="card-head">
    <div>
      <h2><?= ikon($editRow ? 'edit' : 'plus', 16) ?> Input Manual &mdash; <?= e(perpus_jenis_nama($jenis)) ?></h2>
      <p>Isi laporan <b>satu baris sekaligus</b> — kolomnya sama dengan kolom baku laporan e-PERPUS
        supaya bentuk cetaknya tetap seragam.
        <?php if ($bolehImpor): ?>
          Data hasil impor dari e-PERPUS juga dapat ditambah/diubah di sini.
        <?php else: ?>
          <b>Impor dari e-PERPUS tidak berlaku</b> untuk <?= tetap_kata($namaSekolah) ?>, jadi seluruh
          data laporan diisi lewat formulir ini.
        <?php endif; ?>
      </p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $total ?> baris tersimpan</span>
      <?php if ($editRow): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(perpus_url($jenis)) ?>#cardManual"><?= ikon('prev', 14) ?> Batal Ubah</a>
      <?php endif; ?>
    </div>
  </div>

  <form method="post" class="apps-form" id="formManual" style="margin-bottom:0">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="manual_simpan">
    <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
    <input type="hidden" name="baris" value="<?= $editRow ? (int) $editRow['id'] : 0 ?>">
    <div class="apps-form-judul">
      <?= ikon($editRow ? 'edit' : 'plus', 16) ?>
      <b><?= $editRow ? 'Ubah Baris' : 'Baris Baru' ?></b>
      <span class="xs mut"><?= $editRow ? 'Perbarui isinya lalu klik Simpan Baris.' : 'Isi minimal satu kolom.' ?></span>
    </div>
    <div class="form-grid g3">
      <?php foreach ($kolomManual as $i => $label): ?>
        <div>
          <label class="f" for="sel<?= (int) $i ?>"><?= e($label) ?></label>
          <input type="text" id="sel<?= (int) $i ?>" name="sel[]" maxlength="<?= (int) PERPUS_SEL_MAKS ?>"
                 value="<?= e((string) ($editSel[$i] ?? '')) ?>"
                 <?= $i === 0 ? 'autofocus' : '' ?>>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="flex mt16">
      <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $editRow ? 'Simpan Perubahan' : 'Simpan Baris' ?></button>
      <span class="xs mut">Baris baru diletakkan paling bawah; kolom <b>No</b> selalu dinomori ulang
        otomatis, jadi tidak perlu diisi.</span>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if (is_super()): ?>
<?php // ---------------------------------------------------------------- pengaturan impor e-PERPUS (Super Admin) ?>
<div class="card" id="cardImporSekolah">
  <div class="card-head">
    <div>
      <h2><?= ikon('upload', 16) ?> Sekolah/Madrasah yang Boleh Mengimpor dari e-PERPUS</h2>
      <p>Hanya sekolah/madrasah yang <b>dicentang</b> di bawah ini yang dapat mengimpor laporan
        perpustakaan dari e-PERPUS. Sekolah/madrasah lain <b>tetap dapat mengisi laporan secara
        manual</b> (menu ini tidak tertutup untuk mereka).</p>
    </div>
    <div class="flex"><span class="chip"><?= count(perpus_impor_sekolah_ids()) ?> sekolah boleh impor</span></div>
  </div>

  <?php
  $semuaSekolah = [];
  try {
      $semuaSekolah = all('SELECT id, nama, jenis FROM sekolah ORDER BY nama');
  } catch (Throwable $ex) {
      $semuaSekolah = [];
  }
  $idImpor = perpus_impor_sekolah_ids();
  ?>
  <?php if (!$semuaSekolah): ?>
    <div class="empty"><?= ikon('school', 30) ?><h3>Belum ada sekolah/madrasah</h3>
      <p>Tambahkan sekolah/madrasah dulu lewat menu Kelola Sekolah/Madrasah.</p></div>
  <?php else: ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="impor_sekolah_simpan">
      <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
      <div class="table-wrap">
        <table class="tbl" id="tblImporSekolah">
          <thead><tr><th class="ctr" style="width:44px">Impor</th><th>Nama Sekolah/Madrasah</th>
            <th>Jenis</th><th class="ctr">Laporan Tersimpan</th></tr></thead>
          <tbody>
          <?php foreach ($semuaSekolah as $s): $sid = (int) $s['id']; ?>
            <tr>
              <td class="ctr"><input type="checkbox" name="sekolah[]" value="<?= $sid ?>"
                    style="width:16px;height:16px;accent-color:var(--accent)"
                    <?= in_array($sid, $idImpor, true) ? 'checked' : '' ?>></td>
              <td><?= tetap_kata((string) $s['nama']) ?></td>
              <td class="xs mut"><?= e(($s['jenis'] ?? '') === 'madrasah' ? 'Madrasah' : 'Sekolah Umum') ?></td>
              <td class="ctr xs mut">
                <?= (int) val('SELECT COUNT(*) FROM perpus_laporan WHERE sekolah_id = ?', [$sid], 0) ?> laporan
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="flex mt16">
        <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Penyetelan Impor</button>
        <span class="xs mut">Tidak ada yang dicentang = <b>tidak ada satu pun</b> sekolah yang boleh impor;
          semuanya mengisi laporan secara manual.</span>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>
    <?php
    page_bottom();
}
