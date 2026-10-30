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
        return ['laporan' => null, 'header' => [], 'baris' => [], 'total' => 0, 'cocok' => 0];
    }
    $header = perpus_header($lap);
    $baris = [];
    foreach (perpus_baris((int) $lap['id'], $cari) as $r) {
        $baris[] = perpus_isi($r);
    }
    return [
        'laporan' => $lap,
        'header'  => $header,
        'baris'   => $baris,
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
            flash('Data ' . perpus_jenis_nama($jenisAksi) . ' dikosongkan. Impor ulang dari e-PERPUS bila diperlukan.', 'warn');
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
    $totals = perpus_totals($header, $semua, $jenis);
    $adaNo = perpus_ada_no($header);
    $jumlahJenis = perpus_ringkas_jenis();
    $namaSekolah = nama_sekolah_tampil();

    page_top($namaMenu, 'Laporan Perpustakaan &middot; ' . tetap_kata($namaSekolah));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('book', 16) ?> <?= e($namaMenu) ?> &mdash; Laporan Perpustakaan</h2>
      <p>Lima laporan perpustakaan <?= tetap_kata($namaSekolah) ?> yang <b>diimpor dari hasil unduhan
        aplikasi e-PERPUS</b>, lengkap dengan fitur cetak untuk setiap laporan.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $total ?> baris pada laporan ini</span>
      <?php if ($laporan): ?>
        <a class="btn btn-ghost btn-sm" href="cetak_perpus.php?jenis=<?= e($jenis) ?><?= $cari !== '' ? '&cari=' . e(rawurlencode($cari)) : '' ?>" target="_blank"><?= ikon('print', 15) ?> Cetak <?= e(perpus_jenis_nama($jenis)) ?></a>
      <?php endif; ?>
      <?php if ($bolehUbah): ?>
        <a class="btn btn-primary btn-sm" href="perpus_import.php?jenis=<?= e($jenis) ?>"><?= ikon('upload', 15) ?> Impor dari e-PERPUS</a>
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

  <?php if (!$laporan): ?>
    <div class="empty">
      <?= ikon('upload', 38) ?>
      <h3>Belum ada data <?= e(perpus_jenis_nama($jenis)) ?></h3>
      <?php // Petunjuk pengimporan hanya untuk yang BISA mengimpor (guru yang ditugaskan /
            // Super Admin); kepala & operator hanya melihat, jadi diberi keterangan singkat. ?>
      <p><?= $bolehUbah
        ? 'Laporan ini belum diimpor dari aplikasi e-PERPUS. Cara mengisinya:'
        : pesan_kosong_hanya_lihat('laporan ' . perpus_jenis_nama($jenis)) ?></p>
      <?php if ($bolehUbah): ?>
      <ol class="list-ok" style="text-align:left;max-width:640px;margin:14px auto 0">
        <li>Buka aplikasi <b>e-PERPUS</b> → menu <b>Laporan</b> → pilih
          <b><?= e(perpus_jenis_nama($jenis)) ?></b> (untuk Data Anggota bisa juga lewat
          <b>Data Anggota → Ekspor Data Anggota</b>).</li>
        <li>Klik tombol <b>CSV</b> (atau <b>Unduh Excel</b>) sampai berkas laporannya terunduh.</li>
        <li>Kembali ke halaman ini, klik <b>Impor dari e-PERPUS</b>, pilih berkasnya, periksa
          pratinjaunya, lalu klik <b>Impor Sekarang</b>.</li>
      </ol>
      <?php endif; ?>
      <?php if ($bolehUbah): ?>
        <div class="flex" style="justify-content:center">
          <a class="btn btn-primary" href="perpus_import.php?jenis=<?= e($jenis) ?>"><?= ikon('upload', 16) ?> Impor dari e-PERPUS</a>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-ok" style="background:rgba(201,162,39,.07);border-color:rgba(201,162,39,.45)">
      <?= ikon('doc', 16) ?>
      <span>Sumber data: <b><?= e((string) ($laporan['berkas'] ?: '(berkas tanpa nama)')) ?></b>
        &middot; diimpor <?= waktu_id((string) $laporan['diimpor']) ?>
        <?= trim((string) $laporan['oleh']) !== '' ? 'oleh ' . e((string) $laporan['oleh']) : '' ?>
        &middot; <?= (int) $laporan['jumlah_baris'] ?> baris, <?= count($header) ?> kolom.
        <?php if ($bolehUbah): ?>Impor ulang akan <b>menggantikan</b> data laporan ini.<?php endif; ?></span>
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
            </tr>
          </thead>
          <tbody>
          <?php
          $no = ($hal - 1) * $perHalaman;
          foreach ($tampil as $row):
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
          <?= tetap_kata($namaSekolah) ?>? Lembar cetak ikut kosong sampai diimpor ulang.</span>
        <button class="btn btn-ghost btn-sm" type="submit"
                data-confirm="Kosongkan seluruh data <?= e(perpus_jenis_nama($jenis)) ?>?"><?= ikon('trash', 15) ?> Kosongkan Data</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
    <?php
    page_bottom();
}
