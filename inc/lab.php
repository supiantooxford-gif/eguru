<?php
/**
 * MODUL KEPALA LAB (IPA & KOMPUTER) — DAFTAR INVENTARIS BARANG + LAPORAN PROGRAM KERJA.
 *
 * Permintaan pemilik: "Pada Menu Kepala Lab IPA dan Lab Komputer menu isi dengan:
 *   1. Daftar Inventaris Barang (Tabel yang memuat: No, Nama Barang, Jumlah, Keadaan (Baik atau
 *      Rusak), Keterangan)
 *   2. Laporan Program (Tabel yang memuat: No, Program Kerja, Alokasi Waktu, Status Realisasi
 *      (Terlaksana atau Belum), Keterangan)
 *   Pastikan ditambahkan fitur cetak dokumen"
 *
 * KEDUA menu memakai modul ini (kode `kepala_lab` = "Ka. Lab IPA" dan `tata_usaha` = "Ka. Lab
 * Komputer" pada data pemilik; nama menu boleh diubah Super Admin, yang tetap adalah KODEnya).
 * Data dipisah per menu lewat kolom `kode` dan per sekolah lewat `sekolah_id` — setiap query
 * dibatasi keduanya (anti-IDOR).
 *
 * **PENGAWAS (hanya lihat)**: pengawas sekolah/madrasah binaan boleh membuka kedua tabel & mencetak
 * dokumennya, tetapi tidak boleh mengubah data (POST ditolak 403 di sini + gerbang pengawas).
 *
 * Dipanggil oleh `tugas.php` bila menunya berkode pada LAB_KODE_MENU; menu Tugas Tambahan lain tetap
 * menampilkan halaman "Masih dalam Pengembangan".
 */
declare(strict_types=1);

// ------------------------------------------------------------------ bantuan data

function lab_kode_list(): array
{
    return LAB_KODE_MENU;
}

function lab_kode_ada(string $kode): bool
{
    return in_array(trim($kode), LAB_KODE_MENU, true);
}

/** Pilihan tetap: Keadaan (Baik/Rusak) & Status Realisasi (Terlaksana/Belum). */
function lab_pilihan(): array
{
    return [
        'keadaan' => LAB_KEADAAN,
        'status'  => LAB_STATUS,
    ];
}

/** Nilai sah untuk sebuah kolom pilihan; nilai di luar daftar jatuh ke pilihan pertama bila wajib. */
function lab_pilihan_sah(string $kolom, string $nilai, bool $wajib = false): string
{
    $opsi = lab_pilihan()[$kolom] ?? [];
    foreach ($opsi as $o) {
        if (strcasecmp($o, trim($nilai)) === 0) {
            return $o;
        }
    }
    if ($wajib && $opsi) {
        return (string) $opsi[0];
    }
    return '';
}

/** Nama menu Tugas Tambahan (judul bisa diubah Super Admin) — '- ' bila menunya tidak ada. */
function lab_menu_nama(string $kode): string
{
    $t = tugas_menu_by_kode($kode);
    return $t ? (string) $t['nama'] : '';
}

// ------------------------------------------------------------------ daftar barang

/**
 * Daftar inventaris barang satu menu pada sekolah yang sedang aktif.
 *
 * @param array<string,string> $f filter: cari, keadaan
 * @return array<int,array<string,mixed>>
 */
function lab_barang(string $kode, array $f = []): array
{
    if (!lab_kode_ada($kode)) {
        return [];
    }
    $w = ['sekolah_id = ?', 'kode = ?'];
    $p = [sekolah_id(), $kode];
    $cari = trim((string) ($f['cari'] ?? ''));
    if ($cari !== '') {
        $w[] = '(nama LIKE ? OR keterangan LIKE ?)';
        array_push($p, '%' . $cari . '%', '%' . $cari . '%');
    }
    $keadaan = lab_pilihan_sah('keadaan', (string) ($f['keadaan'] ?? ''));
    if ($keadaan !== '') {
        $w[] = 'keadaan = ?';
        $p[] = $keadaan;
    }
    return all('SELECT * FROM lab_barang WHERE ' . implode(' AND ', $w) . ' ORDER BY urutan, nama', $p);
}

function lab_barang_by_id(string $kode, int $id): ?array
{
    if ($id <= 0 || !lab_kode_ada($kode)) {
        return null;
    }
    return one('SELECT * FROM lab_barang WHERE id = ? AND kode = ? AND sekolah_id = ?',
        [$id, $kode, sekolah_id()]);
}

/** Ringkasan inventaris: jumlah jenis, total unit, dan sebaran keadaan. */
function lab_ringkas_barang(array $baris): array
{
    $keadaan = [];
    foreach (LAB_KEADAAN as $k) {
        $keadaan[$k] = 0;
    }
    $unit = 0;
    foreach ($baris as $b) {
        $unit += (int) $b['jumlah'];
        $k = (string) ($b['keadaan'] ?? '');
        $keadaan[$k] = ($keadaan[$k] ?? 0) + 1;
    }
    return ['jenis' => count($baris), 'unit' => $unit, 'keadaan' => $keadaan];
}

// ------------------------------------------------------------------ laporan program

/**
 * Daftar laporan program kerja satu menu pada sekolah yang sedang aktif.
 *
 * @param array<string,string> $f filter: cari, status
 * @return array<int,array<string,mixed>>
 */
function lab_program(string $kode, array $f = []): array
{
    if (!lab_kode_ada($kode)) {
        return [];
    }
    $w = ['sekolah_id = ?', 'kode = ?'];
    $p = [sekolah_id(), $kode];
    $cari = trim((string) ($f['cari'] ?? ''));
    if ($cari !== '') {
        $w[] = '(program LIKE ? OR alokasi LIKE ? OR keterangan LIKE ?)';
        array_push($p, '%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%');
    }
    $status = lab_pilihan_sah('status', (string) ($f['status'] ?? ''));
    if ($status !== '') {
        $w[] = 'status = ?';
        $p[] = $status;
    }
    return all('SELECT * FROM lab_program WHERE ' . implode(' AND ', $w) . ' ORDER BY urutan, program', $p);
}

function lab_program_by_id(string $kode, int $id): ?array
{
    if ($id <= 0 || !lab_kode_ada($kode)) {
        return null;
    }
    return one('SELECT * FROM lab_program WHERE id = ? AND kode = ? AND sekolah_id = ?',
        [$id, $kode, sekolah_id()]);
}

/** Ringkasan program: jumlah program, sudah terlaksana, dan yang belum. */
function lab_ringkas_program(array $baris): array
{
    $terlaksana = 0;
    foreach ($baris as $r) {
        if ((string) ($r['status'] ?? '') === LAB_STATUS[0]) {
            $terlaksana++;
        }
    }
    return [
        'program'    => count($baris),
        'terlaksana' => $terlaksana,
        'belum'      => count($baris) - $terlaksana,
    ];
}

// ------------------------------------------------------------------ saringan

/** URL saringan aktif (dipakai agar lembar cetak mengikuti saringan yang sedang dipakai). */
function lab_query(array $f, string $kode, string $tab): string
{
    $q = ['kode' => $kode, 'tab' => $tab];
    foreach (['cari', 'keadaan', 'status'] as $k) {
        $v = trim((string) ($f[$k] ?? ''));
        if ($v !== '') {
            $q[$k] = $v;
        }
    }
    return http_build_query($q);
}

/** Label saringan yang sedang aktif (untuk identitas lembar cetak). */
function lab_label_filter(array $f): array
{
    $keluar = [];
    if (trim((string) ($f['keadaan'] ?? '')) !== '') {
        $keluar[] = 'Keadaan: ' . lab_pilihan_sah('keadaan', (string) $f['keadaan']);
    }
    if (trim((string) ($f['status'] ?? '')) !== '') {
        $keluar[] = 'Status Realisasi: ' . lab_pilihan_sah('status', (string) $f['status']);
    }
    if (trim((string) ($f['cari'] ?? '')) !== '') {
        $keluar[] = 'Kata kunci: ' . trim((string) $f['cari']);
    }
    return $keluar;
}

/** Kelas pil warna untuk Keadaan (Baik hijau, Rusak merah) & Status (Terlaksana hijau, Belum kuning). */
function lab_pil_keadaan(string $keadaan): string
{
    return $keadaan === LAB_KEADAAN[0] ? 'pill-ok' : 'pill-D';
}

function lab_pil_status(string $status): string
{
    if ($status === LAB_STATUS[0]) {
        return 'pill-ok';
    }
    return 'pill-warn';
}

// ------------------------------------------------------------------ tabel

/** Tabel Daftar Inventaris Barang (dipakai halaman & menjadi acuan kolom lembar cetak). */
function lab_tabel_barang(array $baris, bool $aksi, string $kode): void
{
    $ringkas = lab_ringkas_barang($baris);
    ?>
<div class="table-wrap" id="tabelBarang">
  <table class="tbl tbl-lab">
    <thead>
      <tr>
        <th class="ctr k-no">No</th>
        <th class="k-nama">Nama Barang</th>
        <th class="ctr k-jumlah">Jumlah</th>
        <th class="ctr k-keadaan">Keadaan</th>
        <th class="k-ket">Keterangan</th>
        <?php if ($aksi): ?><th class="ctr k-aksi">Tindakan</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php $n = 0; foreach ($baris as $b): $n++; ?>
      <tr>
        <td class="ctr"><?= $n ?></td>
        <td><b><?= e((string) $b['nama']) ?></b></td>
        <td class="ctr"><?= (int) $b['jumlah'] ?></td>
        <td class="ctr">
          <?php $k = (string) ($b['keadaan'] ?? ''); ?>
          <span class="pill <?= lab_pil_keadaan($k) ?>"><?= e($k ?: '-') ?></span>
        </td>
        <td class="sm"><?= e((string) ($b['keterangan'] ?: '-')) ?></td>
        <?php if ($aksi): ?>
          <td>
            <div class="row-actions">
              <a class="btn btn-ghost btn-sm" href="tugas.php?kode=<?= e($kode) ?>&tab=barang&edit=<?= (int) $b['id'] ?>#formBarang"><?= ikon('edit', 14) ?> Ubah</a>
              <form method="post" data-confirm="Hapus barang &quot;<?= e((string) $b['nama']) ?>&quot; dari daftar inventaris?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus_barang">
                <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
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
        <th class="ctr"><?= (int) $ringkas['unit'] ?> unit</th>
        <th colspan="<?= $aksi ? 3 : 2 ?>"><?= (int) $ringkas['jenis'] ?> jenis barang</th>
      </tr>
    </tfoot>
  </table>
</div>
    <?php
}

/** Tabel Laporan Program (dipakai halaman & menjadi acuan kolom lembar cetak). */
function lab_tabel_program(array $baris, bool $aksi, string $kode): void
{
    $ringkas = lab_ringkas_program($baris);
    ?>
<div class="table-wrap" id="tabelProgram">
  <table class="tbl tbl-lab">
    <thead>
      <tr>
        <th class="ctr k-no">No</th>
        <th class="k-nama">Program Kerja</th>
        <th class="k-alokasi">Alokasi Waktu</th>
        <th class="ctr k-status">Status Realisasi</th>
        <th class="k-ket">Keterangan</th>
        <?php if ($aksi): ?><th class="ctr k-aksi">Tindakan</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php $n = 0; foreach ($baris as $r): $n++; ?>
      <tr>
        <td class="ctr"><?= $n ?></td>
        <td><b><?= e((string) $r['program']) ?></b></td>
        <td class="sm"><?= e((string) ($r['alokasi'] ?: '-')) ?></td>
        <td class="ctr">
          <?php $s = (string) ($r['status'] ?? ''); ?>
          <span class="pill <?= lab_pil_status($s) ?>"><?= e($s ?: '-') ?></span>
        </td>
        <td class="sm"><?= e((string) ($r['keterangan'] ?: '-')) ?></td>
        <?php if ($aksi): ?>
          <td>
            <div class="row-actions">
              <a class="btn btn-ghost btn-sm" href="tugas.php?kode=<?= e($kode) ?>&tab=program&edit=<?= (int) $r['id'] ?>#formProgram"><?= ikon('edit', 14) ?> Ubah</a>
              <form method="post" data-confirm="Hapus program &quot;<?= e((string) $r['program']) ?>&quot;?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus_program">
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
        <th colspan="3" class="ctr">Jumlah</th>
        <th class="ctr"><?= (int) $ringkas['terlaksana'] ?> terlaksana</th>
        <th colspan="<?= $aksi ? 2 : 1 ?>"><?= (int) $ringkas['program'] ?> program (<?= (int) $ringkas['belum'] ?> belum)</th>
      </tr>
    </tfoot>
  </table>
</div>
    <?php
}

// ------------------------------------------------------------------ halaman

/**
 * Jalankan halaman Kepala Lab (dipakai `tugas.php?kode=kepala_lab|tata_usaha`).
 *
 * @param array<string,mixed> $tugas baris `tugas_menu` (judul mengikuti nama menu hasil ubah Super Admin)
 */
function lab_halaman(array $tugas): void
{
    require_sekolah();

    $kode = (string) ($tugas['kode'] ?? '');
    if (!lab_kode_ada($kode)) {
        flash('Menu Kepala Lab tidak dikenal.', 'err');
        redirect(is_pengawas() ? 'tugas.php' : 'index.php');
    }
    $namaMenu = (string) ($tugas['nama'] ?? 'Kepala Lab');
    // Pengawas = HANYA LIHAT & CETAK (aturan yang sama dengan menu Tugas Tambahan lain).
    // Permintaan pemilik: kepala sekolah/madrasah & admin operator HANYA melihat + mencetak;
    // pengisian menu Tugas Tambahan dilakukan guru yang ditugaskan (lihat boleh_ubah_tugas_menu()).
    $bolehUbah = boleh_ubah_tugas_menu();
    if (!$bolehUbah && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        tolak_ubah_tugas_menu('data inventaris & program kerja lab');
    }

    // ---------------------------------------------------------------- POST (tambah/ubah/hapus)
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $now = date('Y-m-d H:i:s');
        $pangkas = static function (string $v, int $maks): string {
            $v = trim($v);
            return strlen($v) > $maks ? substr($v, 0, $maks) : $v;
        };

        if ($aksi === 'simpan_barang') {
            $nama = $pangkas((string) ($_POST['nama'] ?? ''), LAB_TEKS_MAKS);
            $jumlah = (int) ($_POST['jumlah'] ?? 1);
            $keadaan = lab_pilihan_sah('keadaan', (string) ($_POST['keadaan'] ?? ''), true);
            $keterangan = $pangkas((string) ($_POST['keterangan'] ?? ''), LAB_KET_MAKS);
            if ($nama === '') {
                flash('Nama barang wajib diisi.', 'err');
                redirect('tugas.php?kode=' . e($kode) . '&tab=barang' . ($id > 0 ? '&edit=' . $id : '') . '#formBarang');
            }
            if ($jumlah < 0) {
                $jumlah = 0;
            }
            if ($jumlah > 1000000) {
                $jumlah = 1000000;
            }
            $b = lab_barang_by_id($kode, $id);
            if ($b) {
                q('UPDATE lab_barang SET nama=?, jumlah=?, keadaan=?, keterangan=?, diubah=?
                   WHERE id=? AND kode=? AND sekolah_id=?',
                    [$nama, $jumlah, $keadaan, $keterangan, $now, $id, $kode, sekolah_id()]);
                flash('Barang "' . $nama . '" diperbarui.');
            } else {
                $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) + 10 FROM lab_barang
                                     WHERE sekolah_id = ? AND kode = ?', [sekolah_id(), $kode], 0);
                q('INSERT INTO lab_barang (sekolah_id, kode, nama, jumlah, keadaan, keterangan, urutan, dibuat)
                   VALUES (?,?,?,?,?,?,?,?)',
                    [sekolah_id(), $kode, $nama, $jumlah, $keadaan, $keterangan, $urutan, $now]);
                flash('Barang "' . $nama . '" ditambahkan ke daftar inventaris.');
            }
            redirect('tugas.php?kode=' . e($kode) . '&tab=barang#tabelBarang');
        }

        if ($aksi === 'hapus_barang') {
            $b = lab_barang_by_id($kode, $id);
            if (!$b) {
                flash('Data barang tidak ditemukan.', 'err');
                redirect('tugas.php?kode=' . e($kode) . '&tab=barang');
            }
            q('DELETE FROM lab_barang WHERE id = ? AND kode = ? AND sekolah_id = ?',
                [$id, $kode, sekolah_id()]);
            flash('Barang "' . $b['nama'] . '" dihapus.', 'warn');
            redirect('tugas.php?kode=' . e($kode) . '&tab=barang');
        }

        if ($aksi === 'simpan_program') {
            $program = $pangkas((string) ($_POST['program'] ?? ''), LAB_TEKS_MAKS);
            $alokasi = $pangkas((string) ($_POST['alokasi'] ?? ''), LAB_TEKS_MAKS);
            $status = lab_pilihan_sah('status', (string) ($_POST['status'] ?? ''), true);
            $keterangan = $pangkas((string) ($_POST['keterangan'] ?? ''), LAB_KET_MAKS);
            if ($program === '') {
                flash('Nama program kerja wajib diisi.', 'err');
                redirect('tugas.php?kode=' . e($kode) . '&tab=program' . ($id > 0 ? '&edit=' . $id : '') . '#formProgram');
            }
            $r = lab_program_by_id($kode, $id);
            if ($r) {
                q('UPDATE lab_program SET program=?, alokasi=?, status=?, keterangan=?, diubah=?
                   WHERE id=? AND kode=? AND sekolah_id=?',
                    [$program, $alokasi, $status, $keterangan, $now, $id, $kode, sekolah_id()]);
                flash('Program "' . $program . '" diperbarui.');
            } else {
                $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) + 10 FROM lab_program
                                     WHERE sekolah_id = ? AND kode = ?', [sekolah_id(), $kode], 0);
                q('INSERT INTO lab_program (sekolah_id, kode, program, alokasi, status, keterangan, urutan, dibuat)
                   VALUES (?,?,?,?,?,?,?,?)',
                    [sekolah_id(), $kode, $program, $alokasi, $status, $keterangan, $urutan, $now]);
                flash('Program "' . $program . '" ditambahkan.');
            }
            redirect('tugas.php?kode=' . e($kode) . '&tab=program#tabelProgram');
        }

        if ($aksi === 'hapus_program') {
            $r = lab_program_by_id($kode, $id);
            if (!$r) {
                flash('Data program tidak ditemukan.', 'err');
                redirect('tugas.php?kode=' . e($kode) . '&tab=program');
            }
            q('DELETE FROM lab_program WHERE id = ? AND kode = ? AND sekolah_id = ?',
                [$id, $kode, sekolah_id()]);
            flash('Program "' . $r['program'] . '" dihapus.', 'warn');
            redirect('tugas.php?kode=' . e($kode) . '&tab=program');
        }

        redirect('tugas.php?kode=' . e($kode));
    }

    // ---------------------------------------------------------------- tampilan
    $f = [
        'cari'    => (string) ($_GET['cari'] ?? ''),
        'keadaan' => (string) ($_GET['keadaan'] ?? ''),
        'status'  => (string) ($_GET['status'] ?? ''),
    ];
    $tab = (string) ($_GET['tab'] ?? '');
    if (!in_array($tab, ['barang', 'program'], true)) {
        $tab = 'barang';
    }
    $barang = lab_barang($kode, $f);
    $program = lab_program($kode, $f);
    $ringkasB = lab_ringkas_barang($barang);
    $ringkasP = lab_ringkas_program($program);
    $editBarang = $tab === 'barang' ? lab_barang_by_id($kode, (int) ($_GET['edit'] ?? 0)) : null;
    $editProgram = $tab === 'program' ? lab_program_by_id($kode, (int) ($_GET['edit'] ?? 0)) : null;
    $qFilter = lab_query($f, $kode, $tab);
    $qCetak = http_build_query(array_filter(['kode' => $kode, 'bagian' => $tab,
        'cari' => trim((string) $f['cari']),
        'keadaan' => $tab === 'barang' ? trim((string) $f['keadaan']) : '',
        'status' => $tab === 'program' ? trim((string) $f['status']) : '']));
    $opsi = lab_pilihan();
    $namaSekolah = nama_sekolah_tampil();

    page_top($namaMenu, 'Inventaris & Laporan Program ' . istilah_sekolah('Sekolah'));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('clipboard', 16) ?> <?= e($namaMenu) ?></h2>
      <p>Dua laporan untuk <b><?= tetap_kata($namaSekolah) ?></b>: <b>Daftar Inventaris Barang</b> dan
        <b>Laporan Program Kerja</b> laboratorium, masing-masing lengkap dengan <b>cetak dokumen</b>.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $ringkasB['jenis'] ?> jenis barang</span>
      <span class="chip"><?= (int) $ringkasP['program'] ?> program kerja</span>
      <a class="btn btn-ghost btn-sm" href="cetak_lab.php?<?= e($qCetak) ?>" target="_blank"><?= ikon('print', 15) ?> Cetak <?= $tab === 'barang' ? 'Inventaris' : 'Program' ?></a>
    </div>
  </div>

  <?php if (!$bolehUbah): ?>
    <div class="alert alert-warn" style="margin-bottom:16px">
      <?= ikon('eye', 16) ?>
      <span><?= pemberitahuan_hanya_lihat('data Kepala Lab') ?></span>
    </div>
  <?php endif; ?>

  <?php // ---------------------------------------------------------------- tab dua laporan ?>
  <div class="chip-list warna mb16">
    <a class="chip <?= $tab === 'barang' ? 'on' : '' ?>" href="tugas.php?kode=<?= e($kode) ?>&tab=barang">
      <?= ikon('folder', 14) ?> Daftar Inventaris Barang
      <span class="xs mut">(<?= (int) $ringkasB['jenis'] ?>)</span></a>
    <a class="chip <?= $tab === 'program' ? 'on' : '' ?>" href="tugas.php?kode=<?= e($kode) ?>&tab=program">
      <?= ikon('report', 14) ?> Laporan Program
      <span class="xs mut">(<?= (int) $ringkasP['program'] ?>)</span></a>
  </div>

  <div class="flex mb16" style="flex-wrap:wrap;gap:10px;align-items:flex-end">
    <form method="get" id="formSaring" class="flex" style="gap:8px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="kode" value="<?= e($kode) ?>">
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
      <div>
        <label class="f" for="saringCari">Cari</label>
        <input type="text" id="saringCari" name="cari" value="<?= e((string) $f['cari']) ?>"
               placeholder="mis. nama barang / program kerja">
      </div>
      <?php // ID saringan diberi awalan "saring" supaya TIDAK bentrok dengan id isian pada formulir
            // tambah/ubah di bawahnya (dua elemen ber-id sama membuat label & skrip bisa salah sasaran). ?>
      <?php if ($tab === 'barang'): ?>
        <div>
          <label class="f" for="saringKeadaan">Keadaan</label>
          <select id="saringKeadaan" name="keadaan">
            <option value="">Semua keadaan</option>
            <?php foreach (LAB_KEADAAN as $o): ?>
              <option value="<?= e($o) ?>" <?= (string) $f['keadaan'] === $o ? 'selected' : '' ?>><?= e($o) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php else: ?>
        <div>
          <label class="f" for="saringStatus">Status Realisasi</label>
          <select id="saringStatus" name="status">
            <option value="">Semua status</option>
            <?php foreach (LAB_STATUS as $o): ?>
              <option value="<?= e($o) ?>" <?= (string) $f['status'] === $o ? 'selected' : '' ?>><?= e($o) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <button class="btn btn-primary" type="submit"><?= ikon('search', 15) ?> Tampilkan</button>
      <?php if (lab_label_filter($f)): ?>
        <a class="btn btn-ghost" href="tugas.php?kode=<?= e($kode) ?>&tab=<?= e($tab) ?>"><?= ikon('prev', 15) ?> Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if ($tab === 'barang'): ?>
    <?php // ---------------------------------------------------------------- 1. INVENTARIS BARANG ?>
    <div class="flex mb16" style="flex-wrap:wrap;gap:10px">
      <span class="chip"><?= (int) $ringkasB['jenis'] ?> jenis barang</span>
      <span class="chip"><?= (int) $ringkasB['unit'] ?> unit</span>
      <?php foreach (LAB_KEADAAN as $k): ?>
        <span class="chip"><?= e($k) ?>: <b><?= (int) ($ringkasB['keadaan'][$k] ?? 0) ?></b></span>
      <?php endforeach; ?>
    </div>

    <?php if ($bolehUbah): ?>
      <div class="card" id="formBarang" style="margin-bottom:18px">
        <div class="card-head">
          <div>
            <h2><?= ikon($editBarang ? 'edit' : 'plus', 16) ?> <?= $editBarang ? 'Ubah Barang' : 'Tambah Barang' ?></h2>
            <p>Isi nama barang, jumlah, keadaan (Baik/Rusak), dan keterangannya.</p>
          </div>
          <?php if ($editBarang): ?>
            <a class="btn btn-ghost btn-sm" href="tugas.php?kode=<?= e($kode) ?>&tab=barang#tabelBarang"><?= ikon('prev', 14) ?> Batal</a>
          <?php endif; ?>
        </div>
        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="simpan_barang">
          <input type="hidden" name="id" value="<?= (int) ($editBarang['id'] ?? 0) ?>">
          <div class="grid g3" style="gap:16px">
            <div>
              <label class="f" for="nama">Nama Barang *</label>
              <input type="text" id="nama" name="nama" maxlength="<?= (int) LAB_TEKS_MAKS ?>" required
                     value="<?= e((string) ($editBarang['nama'] ?? '')) ?>" placeholder="mis. Mikroskop Binokuler">
            </div>
            <div>
              <label class="f" for="jumlah">Jumlah *</label>
              <input type="number" id="jumlah" name="jumlah" min="0" max="1000000" required
                     value="<?= (int) ($editBarang['jumlah'] ?? 1) ?>">
            </div>
            <div>
              <label class="f" for="keadaan">Keadaan *</label>
              <select id="keadaan" name="keadaan">
                <?php foreach (LAB_KEADAAN as $o): ?>
                  <option value="<?= e($o) ?>" <?= (string) ($editBarang['keadaan'] ?? LAB_KEADAAN[0]) === $o ? 'selected' : '' ?>><?= e($o) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div>
            <label class="f" for="keterangan">Keterangan</label>
            <input type="text" id="keterangan" name="keterangan" maxlength="<?= (int) LAB_KET_MAKS ?>"
                   value="<?= e((string) ($editBarang['keterangan'] ?? '')) ?>"
                   placeholder="mis. rusak pada lensa okuler, menunggu perbaikan">
          </div>
          <div class="flex">
            <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $editBarang ? 'Simpan Perubahan' : 'Tambah Barang' ?></button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <?php if (!$barang): ?>
      <div class="empty"><?= ikon('folder', 34) ?>
        <h3>Belum ada barang pada daftar inventaris</h3>
        <p><?= $bolehUbah
          ? 'Tambahkan barang lewat formulir di atas, atau ubah saringan bila barangnya tidak tampil.'
          : pesan_kosong_hanya_lihat('daftar inventaris barang') ?></p>
      </div>
    <?php else: ?>
      <div class="section-title mb8"><?= ikon('folder', 15) ?> Daftar Inventaris Barang (<?= count($barang) ?> jenis)</div>
      <?php lab_tabel_barang($barang, $bolehUbah, $kode); ?>
    <?php endif; ?>

  <?php else: ?>
    <?php // ---------------------------------------------------------------- 2. LAPORAN PROGRAM ?>
    <div class="flex mb16" style="flex-wrap:wrap;gap:10px">
      <span class="chip"><?= (int) $ringkasP['program'] ?> program</span>
      <span class="chip">Terlaksana: <b><?= (int) $ringkasP['terlaksana'] ?></b></span>
      <span class="chip">Belum: <b><?= (int) $ringkasP['belum'] ?></b></span>
    </div>

    <?php if ($bolehUbah): ?>
      <div class="card" id="formProgram" style="margin-bottom:18px">
        <div class="card-head">
          <div>
            <h2><?= ikon($editProgram ? 'edit' : 'plus', 16) ?> <?= $editProgram ? 'Ubah Program' : 'Tambah Program' ?></h2>
            <p>Isi program kerja, alokasi waktu, status realisasi (Terlaksana/Belum), dan keterangannya.</p>
          </div>
          <?php if ($editProgram): ?>
            <a class="btn btn-ghost btn-sm" href="tugas.php?kode=<?= e($kode) ?>&tab=program#tabelProgram"><?= ikon('prev', 14) ?> Batal</a>
          <?php endif; ?>
        </div>
        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="simpan_program">
          <input type="hidden" name="id" value="<?= (int) ($editProgram['id'] ?? 0) ?>">
          <div class="grid g3" style="gap:16px">
            <div>
              <label class="f" for="program">Program Kerja *</label>
              <input type="text" id="program" name="program" maxlength="<?= (int) LAB_TEKS_MAKS ?>" required
                     value="<?= e((string) ($editProgram['program'] ?? '')) ?>"
                     placeholder="mis. Praktikum Fisika Kelas X">
            </div>
            <div>
              <label class="f" for="alokasi">Alokasi Waktu</label>
              <input type="text" id="alokasi" name="alokasi" maxlength="<?= (int) LAB_TEKS_MAKS ?>"
                     value="<?= e((string) ($editProgram['alokasi'] ?? '')) ?>"
                     placeholder="mis. Juli s.d. Agustus (8 jam pelajaran)">
            </div>
            <div>
              <label class="f" for="status">Status Realisasi *</label>
              <select id="status" name="status">
                <?php foreach (LAB_STATUS as $o): ?>
                  <option value="<?= e($o) ?>" <?= (string) ($editProgram['status'] ?? LAB_STATUS[1]) === $o ? 'selected' : '' ?>><?= e($o) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div>
            <label class="f" for="keterangan">Keterangan</label>
            <input type="text" id="keterangan" name="keterangan" maxlength="<?= (int) LAB_KET_MAKS ?>"
                   value="<?= e((string) ($editProgram['keterangan'] ?? '')) ?>"
                   placeholder="mis. terlaksana sesuai jadwal, alat tersedia lengkap">
          </div>
          <div class="flex">
            <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $editProgram ? 'Simpan Perubahan' : 'Tambah Program' ?></button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <?php if (!$program): ?>
      <div class="empty"><?= ikon('report', 34) ?>
        <h3>Belum ada program kerja</h3>
        <p><?= $bolehUbah
          ? 'Tambahkan program kerja lewat formulir di atas, atau ubah saringan bila programnya tidak tampil.'
          : pesan_kosong_hanya_lihat('laporan program kerja lab') ?></p>
      </div>
    <?php else: ?>
      <div class="section-title mb8"><?= ikon('report', 15) ?> Laporan Program Kerja (<?= count($program) ?> program)</div>
      <?php lab_tabel_program($program, $bolehUbah, $kode); ?>
    <?php endif; ?>
  <?php endif; ?>
</div>
    <?php
    page_bottom();
}
