<?php
/**
 * MODUL SARANA & PRASARANA — penginputan DATA BARANG untuk menu Tugas Tambahan "Sarana & Prasarana".
 *
 * Permintaan pemilik: "Pada menu sarana prasarana isi menu buat untuk penginputan data barang berupa
 * tabel (No, Nama Barang, Spesifikasi, Lokasi, Kondisi, Jumlah, Sumber Dana, Jenis, Keterangan).
 * Tambahkan juga fitur impor, expor, Cetak. Kalau pada Super Admin, Kepala Lembaga dan Operator
 * langsung laporannya."
 * Pilihan pemilik: (1) hanya menu Sarana & Prasarana; (2) semua pengguna yang dapat membuka menu itu
 * boleh input/ubah/hapus seperti pengelola; (3) Kondisi & Jenis memakai PILIHAN TETAP, sedangkan
 * **Sumber Dana = ISIAN MANUAL** (permintaan pemilik: "Sumber dana rubah menjadi inputan manual").
 *
 * Data barang bersifat PER SEKOLAH (`sarpras_barang.sekolah_id`) dan setiap query dibatasi
 * `sekolah_id()` (anti-IDOR), sama seperti data siswa/guru.
 *
 * **PENGAWAS (hanya lihat)**: permintaan pemilik — "Laporan Tugas Tambahan tampilkan juga di akun
 * pengawas bina untuk setiap sekolahnya". Pengawas yang sedang melihat satu sekolah binaan hanya
 * mendapat **tab Laporan** (ringkasan + rekap + daftar barang) tanpa formulir input, tanpa tombol
 * Ubah/Hapus, tanpa Impor (dan tanpa Ekspor — unduhan memang tidak diizinkan untuk pengawas),
 * tetapi tetap boleh **mencetak**.
 *
 * Dipanggil oleh `tugas.php` bila menunya berkode `SARPRAS_KODE_MENU`; menu Tugas Tambahan lain tetap
 * menampilkan halaman "Masih dalam Pengembangan".
 */
declare(strict_types=1);

// ------------------------------------------------------------------ data & bantuan

/**
 * Pilihan tetap untuk isian data barang: **Kondisi & Jenis** saja.
 *
 * Sumber Dana TIDAK termasuk — atas permintaan pemilik kolom itu kini isian manual (bebas diketik),
 * sehingga tidak boleh divalidasi terhadap daftar apa pun.
 */
function sarpras_pilihan(): array
{
    return [
        'kondisi' => SARPRAS_KONDISI,
        'jenis'   => SARPRAS_JENIS,
    ];
}

/** Panjang maksimal isian teks sumber dana (menjaga basis data tetap rapi). */
const SARPRAS_SUMBER_MAKS = 60;

/**
 * Nilai sah untuk sebuah kolom pilihan; nilai di luar daftar dikembalikan '' (kecuali kondisi yang
 * jatuh ke pilihan pertama) — dipakai isian form, impor, dan filter.
 */
function sarpras_pilihan_sah(string $kolom, string $nilai, bool $wajib = false): string
{
    $opsi = sarpras_pilihan()[$kolom] ?? [];
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

/**
 * Daftar data barang sekolah ini.
 *
 * @param array<string,string|int> $f filter: cari, kondisi, sumber_dana, jenis, lokasi
 * @return array<int,array<string,mixed>>
 */
function sarpras_list(array $f = []): array
{
    $w = ['sekolah_id = ?'];
    $p = [sekolah_id()];
    $cari = trim((string) ($f['cari'] ?? ''));
    if ($cari !== '') {
        $w[] = '(nama LIKE ? OR spesifikasi LIKE ? OR lokasi LIKE ? OR keterangan LIKE ?)';
        $like = '%' . $cari . '%';
        array_push($p, $like, $like, $like, $like);
    }
    $kondisi = sarpras_pilihan_sah('kondisi', (string) ($f['kondisi'] ?? ''));
    if ($kondisi !== '') {
        $w[] = 'kondisi = ?';
        $p[] = $kondisi;
    }
    // Sumber Dana = isian manual, jadi saringannya pencarian teks (bukan pilihan tetap).
    $sumber = trim((string) ($f['sumber_dana'] ?? ''));
    if ($sumber !== '') {
        $w[] = 'sumber_dana LIKE ?';
        $p[] = '%' . $sumber . '%';
    }
    $jenis = sarpras_pilihan_sah('jenis', (string) ($f['jenis'] ?? ''));
    if ($jenis !== '') {
        $w[] = 'jenis = ?';
        $p[] = $jenis;
    }
    $lokasi = trim((string) ($f['lokasi'] ?? ''));
    if ($lokasi !== '') {
        $w[] = 'lokasi LIKE ?';
        $p[] = '%' . $lokasi . '%';
    }
    return all('SELECT * FROM sarpras_barang WHERE ' . implode(' AND ', $w) . ' ORDER BY urutan, nama', $p);
}

function sarpras_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return one('SELECT * FROM sarpras_barang WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
}

/** Ringkasan (untuk kartu laporan): total jenis barang, total unit, dan sebaran kondisi. */
function sarpras_ringkas(array $baris): array
{
    $unit = 0;
    $kondisi = [];
    foreach (SARPRAS_KONDISI as $k) {
        $kondisi[$k] = 0;
    }
    foreach ($baris as $b) {
        $unit += (int) $b['jumlah'];
        $k = (string) ($b['kondisi'] ?? '');
        $kondisi[$k] = ($kondisi[$k] ?? 0) + 1;
    }
    return ['jenis_barang' => count($baris), 'total_unit' => $unit, 'kondisi' => $kondisi];
}

/** Rekap sebuah kolom (`jenis`/`lokasi`/`sumber_dana`) → [nilai => ['barang' => n, 'unit' => n]]. */
function sarpras_rekap(array $baris, string $kolom): array
{
    $keluar = [];
    foreach ($baris as $b) {
        $nilai = trim((string) ($b[$kolom] ?? ''));
        if ($nilai === '') {
            $nilai = '(belum diisi)';
        }
        if (!isset($keluar[$nilai])) {
            $keluar[$nilai] = ['barang' => 0, 'unit' => 0];
        }
        $keluar[$nilai]['barang']++;
        $keluar[$nilai]['unit'] += (int) $b['jumlah'];
    }
    // Urutkan dari unit terbanyak agar laporan mudah dibaca.
    uasort($keluar, static fn(array $a, array $b): int => $b['unit'] <=> $a['unit']);
    return $keluar;
}

/** URL filter aktif (dipakai agar ekspor/cetak/pratinjau mengikuti saringan yang sedang dipakai). */
function sarpras_query(array $f): string
{
    $q = [];
    foreach (['cari', 'kondisi', 'sumber_dana', 'jenis', 'lokasi'] as $k) {
        $v = trim((string) ($f[$k] ?? ''));
        if ($v !== '') {
            $q[$k] = $v;
        }
    }
    return http_build_query($q);
}

// ------------------------------------------------------------------ halaman

/**
 * Jalankan halaman Data Barang Sarana & Prasarana (dipakai `tugas.php?kode=sarpras`).
 *
 * @param array<string,mixed> $tugas baris `tugas_menu` (agar judul mengikuti nama menu hasil ubah)
 */
function sarpras_halaman(array $tugas): void
{
    // Data barang selalu milik satu sekolah → Super Admin harus memilih sekolah dulu
    // (pola yang sama dengan halaman Data Master lain).
    require_sekolah();

    $namaMenu = (string) ($tugas['nama'] ?? 'Sarana & Prasarana');
    // Permintaan pemilik: Super Admin, Kepala Lembaga, dan Admin Operator "langsung laporannya",
    // sedangkan pengguna lain (mis. guru) langsung ke tabel penginputan.
    $peranPengelola = tugas_menu_semua_peran();
    // Pengawas = HANYA LIHAT & CETAK (permintaan pemilik fitur pengawas) → tidak ada pengubahan.
    // Permintaan pemilik: kepala sekolah/madrasah & admin operator HANYA melihat + mencetak;
    // pengisian menu Tugas Tambahan dilakukan guru yang ditugaskan (lihat boleh_ubah_tugas_menu()).
    $bolehUbah = boleh_ubah_tugas_menu();
    if (!$bolehUbah && $_SERVER['REQUEST_METHOD'] === 'POST') {
        tolak_ubah_tugas_menu('data Sarana & Prasarana');
    }

    // ---------------------------------------------------------------- POST (semua pengguna menu)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $b = $id > 0 ? sarpras_by_id($id) : null;

        if ($aksi === 'simpan') {
            $nama = trim((string) ($_POST['nama'] ?? ''));
            $spesifikasi = trim((string) ($_POST['spesifikasi'] ?? ''));
            $lokasi = trim((string) ($_POST['lokasi'] ?? ''));
            $kondisi = sarpras_pilihan_sah('kondisi', (string) ($_POST['kondisi'] ?? ''), true);
            $jumlah = (int) ($_POST['jumlah'] ?? 1);
            // Sumber Dana = ISIAN MANUAL (permintaan pemilik) → dibaca apa adanya, hanya dipangkas.
            $sumber = trim((string) ($_POST['sumber_dana'] ?? ''));
            if (strlen($sumber) > SARPRAS_SUMBER_MAKS) {
                $sumber = substr($sumber, 0, SARPRAS_SUMBER_MAKS);
            }
            $jenis = sarpras_pilihan_sah('jenis', (string) ($_POST['jenis'] ?? ''));
            $keterangan = trim((string) ($_POST['keterangan'] ?? ''));
            $now = date('Y-m-d H:i:s');

            if ($nama === '') {
                flash('Nama barang wajib diisi.', 'err');
                redirect('tugas.php?kode=' . e(SARPRAS_KODE_MENU) . ($id > 0 ? '&edit=' . $id : '') . '&tab=data#formBarang');
            }
            if (strlen($nama) > 160) {
                $nama = substr($nama, 0, 160);
            }
            if ($jumlah < 0) {
                $jumlah = 0;
            }
            if ($jumlah > 1000000) {
                $jumlah = 1000000;
            }

            if ($b) {
                q('UPDATE sarpras_barang SET nama=?, spesifikasi=?, lokasi=?, kondisi=?, jumlah=?,
                     sumber_dana=?, jenis=?, keterangan=?, diubah=? WHERE id=? AND sekolah_id=?',
                    [$nama, $spesifikasi, $lokasi, $kondisi, $jumlah, $sumber, $jenis, $keterangan,
                     $now, $id, sekolah_id()]);
                flash('Data barang "' . $nama . '" diperbarui.');
            } else {
                $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) + 10 FROM sarpras_barang WHERE sekolah_id = ?',
                    [sekolah_id()], 10);
                q('INSERT INTO sarpras_barang (sekolah_id, nama, spesifikasi, lokasi, kondisi, jumlah,
                     sumber_dana, jenis, keterangan, urutan, dibuat)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [sekolah_id(), $nama, $spesifikasi, $lokasi, $kondisi, $jumlah, $sumber, $jenis,
                     $keterangan, $urutan, $now]);
                flash('Data barang "' . $nama . '" ditambahkan.');
            }
            redirect('tugas.php?kode=' . e(SARPRAS_KODE_MENU) . '&tab=data' . ($peranPengelola ? '#tabelBarang' : ''));
        }

        if ($aksi === 'hapus') {
            if (!$b) {
                flash('Data barang tidak ditemukan.', 'err');
                redirect('tugas.php?kode=' . e(SARPRAS_KODE_MENU));
            }
            q('DELETE FROM sarpras_barang WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
            flash('Data barang "' . $b['nama'] . '" dihapus.', 'warn');
            redirect('tugas.php?kode=' . e(SARPRAS_KODE_MENU) . '&tab=data');
        }

        redirect('tugas.php?kode=' . e(SARPRAS_KODE_MENU));
    }

    // ---------------------------------------------------------------- tampilan
    $f = [
        'cari' => (string) ($_GET['cari'] ?? ''),
        'kondisi' => (string) ($_GET['kondisi'] ?? ''),
        'sumber_dana' => (string) ($_GET['sumber_dana'] ?? ''),
        'jenis' => (string) ($_GET['jenis'] ?? ''),
        'lokasi' => (string) ($_GET['lokasi'] ?? ''),
    ];
    $baris = sarpras_list($f);
    $ringkas = sarpras_ringkas($baris);
    $edit = sarpras_by_id((int) ($_GET['edit'] ?? 0));
    $tab = (string) ($_GET['tab'] ?? '');
    if (!in_array($tab, ['data', 'laporan'], true)) {
        $tab = $peranPengelola ? 'laporan' : 'data';
    }
    if (!$bolehUbah) {
        $tab = 'laporan';   // pengawas selalu melihat LAPORAN (tidak ada tab penginputan)
    }
    $qFilter = sarpras_query($f);
    $qLink = 'tugas.php?kode=' . e(SARPRAS_KODE_MENU) . ($qFilter !== '' ? '&' . e($qFilter) : '');
    $opsi = sarpras_pilihan();

    page_top($namaMenu, 'Data Barang & Laporan ' . istilah_sekolah('Sekolah'));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('star', 16) ?> <?= e($namaMenu) ?></h2>
      <p>Penginputan <b>data barang</b> milik <?= tetap_kata(nama_sekolah_tampil()) ?>:
        Nama Barang, Spesifikasi, Lokasi, Kondisi, Jumlah, Sumber Dana, Jenis, dan Keterangan.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $ringkas['jenis_barang'] ?> jenis barang</span>
      <span class="chip"><?= (int) $ringkas['total_unit'] ?> unit</span>
      <?php if ($bolehUbah): ?>
        <a class="btn btn-ghost btn-sm" href="sarpras_import.php"><?= ikon('upload', 15) ?> Impor Excel/CSV</a>
        <a class="btn btn-ghost btn-sm" href="sarpras_export.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>"><?= ikon('doc', 15) ?> Ekspor</a>
      <?php endif; ?>
      <a class="btn btn-ghost btn-sm" href="cetak_sarpras.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>" target="_blank"><?= ikon('print', 15) ?> Cetak</a>
    </div>
  </div>

  <?php if ($bolehUbah): ?>
    <div class="chip-list warna mb16">
      <a class="chip <?= $tab === 'laporan' ? 'on' : '' ?>" href="<?= $qLink ?>&tab=laporan"><?= ikon('chart', 14) ?> Laporan</a>
      <a class="chip <?= $tab === 'data' ? 'on' : '' ?>" href="<?= $qLink ?>&tab=data"><?= ikon('clipboard', 14) ?> Data Barang (Input)</a>
    </div>
  <?php else: ?>
    <div class="alert alert-warn" style="margin-bottom:16px">
      <?= ikon('eye', 16) ?>
      <span><?= pemberitahuan_hanya_lihat('laporan data barang') ?></span>
    </div>
  <?php endif; ?>

  <?php // ---------------------------------------------------------------- LAPORAN ?>
  <?php if ($tab === 'laporan'): ?>
    <div class="grid g4" style="gap:16px">
      <div class="card stat card-tight" style="margin:0">
        <div class="stat-ic g"><?= ikon('folder', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['jenis_barang'] ?></div><div class="stat-lbl">Jenis barang</div></div>
      </div>
      <div class="card stat card-tight" style="margin:0">
        <div class="stat-ic"><?= ikon('apps', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['total_unit'] ?></div><div class="stat-lbl">Total unit</div></div>
      </div>
      <?php foreach (SARPRAS_KONDISI as $i => $k): ?>
        <div class="card stat card-tight" style="margin:0">
          <div class="stat-ic <?= $i === 0 ? 'g' : ($i === 1 ? 'o' : 'p') ?>"><?= ikon('check', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= (int) ($ringkas['kondisi'][$k] ?? 0) ?></div>
            <div class="stat-lbl"><?= e($k) ?></div></div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (!$baris): ?>
      <div class="empty mt16"><?= ikon('folder', 34) ?>
        <h3>Belum ada data barang</h3>
        <p><?= $bolehUbah
          ? 'Isi lewat tab <b>Data Barang (Input)</b>, impor dari Excel/CSV, atau ubah saringan di bawah.'
          : pesan_kosong_hanya_lihat('barang') ?></p>
        <?php if ($bolehUbah): ?>
          <div class="flex" style="justify-content:center">
            <a class="btn btn-primary" href="<?= $qLink ?>&tab=data#formBarang"><?= ikon('plus', 16) ?> Input Data Barang</a>
            <a class="btn btn-ghost" href="sarpras_import.php"><?= ikon('upload', 16) ?> Impor Excel/CSV</a>
          </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="grid g2 mt16" style="gap:16px">
        <div class="card" style="margin:0">
          <div class="card-head"><div><h2>Rekap per Jenis</h2><p>Kelompok barang tetap/tidak tetap/bergerak.</p></div></div>
          <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Jenis</th><th class="ctr">Jenis Barang</th><th class="ctr">Unit</th></tr></thead>
            <tbody>
            <?php foreach (sarpras_rekap($baris, 'jenis') as $nm => $r): ?>
              <tr><td><?= e((string) $nm) ?></td><td class="ctr"><?= (int) $r['barang'] ?></td><td class="ctr"><?= (int) $r['unit'] ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </div>
        <div class="card" style="margin:0">
          <div class="card-head"><div><h2>Rekap per Lokasi</h2><p>Sebaran barang pada tiap ruangan.</p></div></div>
          <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Lokasi</th><th class="ctr">Jenis Barang</th><th class="ctr">Unit</th></tr></thead>
            <tbody>
            <?php foreach (sarpras_rekap($baris, 'lokasi') as $nm => $r): ?>
              <tr><td><?= e((string) $nm) ?></td><td class="ctr"><?= (int) $r['barang'] ?></td><td class="ctr"><?= (int) $r['unit'] ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </div>
        <div class="card" style="margin:0">
          <div class="card-head"><div><h2>Rekap per Sumber Dana</h2><p>Asal perolehan barang.</p></div></div>
          <div class="table-wrap"><table class="tbl">
            <thead><tr><th>Sumber Dana</th><th class="ctr">Jenis Barang</th><th class="ctr">Unit</th></tr></thead>
            <tbody>
            <?php foreach (sarpras_rekap($baris, 'sumber_dana') as $nm => $r): ?>
              <tr><td><?= e((string) $nm) ?></td><td class="ctr"><?= (int) $r['barang'] ?></td><td class="ctr"><?= (int) $r['unit'] ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </div>
        <div class="card" style="margin:0">
          <div class="card-head"><div><h2>Barang Perlu Perhatian</h2><p>Kondisi rusak ringan/berat.</p></div></div>
          <?php $rusak = array_values(array_filter($baris, static fn(array $b): bool => (string) $b['kondisi'] !== SARPRAS_KONDISI[0])); ?>
          <?php if (!$rusak): ?>
            <p class="hint" style="margin:0">Tidak ada barang berkondisi rusak. Semua tercatat
              <b><?= e(SARPRAS_KONDISI[0]) ?></b>.</p>
          <?php else: ?>
            <div class="table-wrap"><table class="tbl">
              <thead><tr><th>Nama Barang</th><th>Lokasi</th><th class="ctr">Kondisi</th><th class="ctr">Jml</th></tr></thead>
              <tbody>
              <?php foreach ($rusak as $b): ?>
                <tr><td><b><?= e((string) $b['nama']) ?></b></td><td><?= e((string) ($b['lokasi'] ?: '-')) ?></td>
                  <td class="ctr"><span class="pill <?= (string) $b['kondisi'] === SARPRAS_KONDISI[2] ? 'pill-D' : 'pill-warn' ?>"><?= e((string) $b['kondisi']) ?></span></td>
                  <td class="ctr"><?= (int) $b['jumlah'] ?></td></tr>
              <?php endforeach; ?>
              </tbody></table></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="divider"></div>
      <div class="section-title mb8"><?= ikon('clipboard', 15) ?> Daftar Barang (<?= count($baris) ?> jenis)</div>
      <?php sarpras_tabel($baris, false, $peranPengelola && $bolehUbah); ?>
    <?php endif; ?>

  <?php // ---------------------------------------------------------------- DATA (INPUT) ?>
  <?php else: ?>
    <div class="card" id="formBarang" style="margin-bottom:18px">
      <div class="card-head">
        <div>
          <h2><?= ikon($edit ? 'edit' : 'plus', 16) ?> <?= $edit ? 'Ubah Data Barang' : 'Tambah Data Barang' ?></h2>
          <p>Isi data barang, lalu simpan. Kondisi, Sumber Dana, dan Jenis dipilih dari daftar.</p>
        </div>
        <?php if ($edit): ?><a class="btn btn-ghost btn-sm" href="<?= $qLink ?>&tab=data"><?= ikon('prev', 14) ?> Batal</a><?php endif; ?>
      </div>
      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
        <div class="grid g2" style="gap:16px">
          <div>
            <label class="f" for="nama">Nama Barang *</label>
            <input type="text" id="nama" name="nama" maxlength="160" required
                   value="<?= e((string) ($edit['nama'] ?? '')) ?>" placeholder="mis. Laptop Asus">
          </div>
          <div>
            <label class="f" for="spesifikasi">Spesifikasi</label>
            <input type="text" id="spesifikasi" name="spesifikasi" maxlength="240"
                   value="<?= e((string) ($edit['spesifikasi'] ?? '')) ?>" placeholder="mis. Core i5, RAM 8 GB, SSD 512 GB">
          </div>
        </div>
        <div class="grid g3" style="gap:16px">
          <div>
            <label class="f" for="lokasi">Lokasi</label>
            <input type="text" id="lokasi" name="lokasi" maxlength="120"
                   value="<?= e((string) ($edit['lokasi'] ?? '')) ?>" placeholder="mis. Ruang Guru">
          </div>
          <div>
            <label class="f" for="kondisi">Kondisi *</label>
            <select id="kondisi" name="kondisi">
              <?php foreach ($opsi['kondisi'] as $o): ?>
                <option value="<?= e($o) ?>" <?= (string) ($edit['kondisi'] ?? SARPRAS_KONDISI[0]) === $o ? 'selected' : '' ?>><?= e($o) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="f" for="jumlah">Jumlah *</label>
            <input type="number" id="jumlah" name="jumlah" min="0" max="1000000" required
                   value="<?= (int) ($edit['jumlah'] ?? 1) ?>">
          </div>
        </div>
        <div class="grid g2" style="gap:16px">
          <div>
            <label class="f" for="sumber_dana">Sumber Dana</label>
            <?php // Isian MANUAL (permintaan pemilik): bebas diketik, mis. BOS, APBD, Komite, Sumbangan. ?>
            <input type="text" id="sumber_dana" name="sumber_dana" maxlength="<?= (int) SARPRAS_SUMBER_MAKS ?>"
                   list="saranSumberDana" value="<?= e((string) ($edit['sumber_dana'] ?? '')) ?>"
                   placeholder="mis. BOS, APBD, Komite, Sumbangan">
            <div class="hint">Isian <b>isian bebas</b> (manual): tuliskan sumber dana apa pun, boleh
              berbeda di tiap barang.</div>
            <?php
            // Daftar saran dari sumber dana yang PERNAH dipakai sekolah ini — hanya membantu
            // pengetikan (isian tetap bebas), tidak membatasi nilai yang boleh diisi.
            $saranSumber = [];
            foreach (all('SELECT DISTINCT sumber_dana FROM sarpras_barang
                          WHERE sekolah_id = ? AND IFNULL(sumber_dana, "") <> "" ORDER BY sumber_dana',
                         [sekolah_id()]) as $sr) {
                $saranSumber[] = (string) $sr['sumber_dana'];
            }
            ?>
            <?php if ($saranSumber): ?>
              <datalist id="saranSumberDana">
                <?php foreach ($saranSumber as $sr): ?><option value="<?= e($sr) ?>"></option><?php endforeach; ?>
              </datalist>
            <?php endif; ?>
          </div>
          <div>
            <label class="f" for="jenis">Jenis</label>
            <select id="jenis" name="jenis">
              <option value="">—</option>
              <?php foreach ($opsi['jenis'] as $o): ?>
                <option value="<?= e($o) ?>" <?= (string) ($edit['jenis'] ?? '') === $o ? 'selected' : '' ?>><?= e($o) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="f" for="keterangan">Keterangan</label>
          <textarea id="keterangan" name="keterangan" rows="2" maxlength="400"><?= e((string) ($edit['keterangan'] ?? '')) ?></textarea>
        </div>
        <div class="flex">
          <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $edit ? 'Simpan Perubahan' : 'Tambah Data Barang' ?></button>
          <?php if ($edit): ?><a class="btn btn-ghost" href="<?= $qLink ?>&tab=data">Batal</a><?php endif; ?>
        </div>
      </form>
    </div>

    <form class="filterbar mb16" method="get">
      <input type="hidden" name="kode" value="<?= e(SARPRAS_KODE_MENU) ?>">
      <input type="hidden" name="tab" value="data">
      <div>
        <label class="f" for="cari">Cari</label>
        <input type="text" id="cari" name="cari" value="<?= e($f['cari']) ?>" placeholder="nama / spesifikasi / lokasi">
      </div>
      <div>
        <label class="f" for="fKondisi">Kondisi</label>
        <select id="fKondisi" name="kondisi" data-auto>
          <option value="">Semua kondisi</option>
          <?php foreach ($opsi['kondisi'] as $o): ?>
            <option value="<?= e($o) ?>" <?= $f['kondisi'] === $o ? 'selected' : '' ?>><?= e($o) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="f" for="fSumber">Sumber Dana</label>
        <input type="text" id="fSumber" name="sumber_dana" value="<?= e($f['sumber_dana']) ?>"
               placeholder="mis. BOS (boleh sebagian)">
      </div>
      <div>
        <label class="f" for="fJenis">Jenis</label>
        <select id="fJenis" name="jenis" data-auto>
          <option value="">Semua jenis</option>
          <?php foreach ($opsi['jenis'] as $o): ?>
            <option value="<?= e($o) ?>" <?= $f['jenis'] === $o ? 'selected' : '' ?>><?= e($o) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
      <?php if ($qFilter !== ''): ?><a class="btn btn-ghost" href="tugas.php?kode=<?= e(SARPRAS_KODE_MENU) ?>&tab=data">Reset</a><?php endif; ?>
    </form>

    <?php if (!$baris): ?>
      <div class="empty"><?= ikon('folder', 34) ?>
        <h3>Belum ada data barang</h3>
        <p>Tambahkan lewat formulir di atas, atau <a href="sarpras_import.php">impor dari Excel/CSV</a>.</p>
      </div>
    <?php else: ?>
      <?php sarpras_tabel($baris, true, $peranPengelola); ?>
    <?php endif; ?>
  <?php endif; ?>
</div>
    <?php
    // WAJIB dipanggil: tugas.php memanggil fungsi ini lalu `exit`, sehingga penutup halaman
    // (footer, </main>, dan <script src="assets/app.js">) hanya keluar dari sini. Tanpa ini
    // seluruh skrip halaman (filter `data-auto`, dialog konfirmasi hapus) tidak pernah aktif.
    page_bottom();
}

/**
 * Tabel data barang.
 *
 * @param array<int,array<string,mixed>> $baris
 * @param bool $aksi true = tampilkan kolom Tindakan (Ubah/Hapus) — semua pengguna menu boleh
 * @param bool $tombolLaporan true = tampilkan tombol Laporan/Ekspor/Cetak di kepala tabel
 */
function sarpras_tabel(array $baris, bool $aksi, bool $tombolLaporan = false): void
{
    $qFilter = sarpras_query($_GET);
    ?>
<div class="table-wrap" id="tabelBarang">
  <table class="tbl tbl-sarpras">
    <thead>
      <tr>
        <th class="ctr k-no">No</th>
        <th class="k-nama">Nama Barang</th>
        <th class="k-spek">Spesifikasi</th>
        <th class="k-lokasi">Lokasi</th>
        <th class="ctr k-kondisi">Kondisi</th>
        <th class="ctr k-jumlah">Jumlah</th>
        <th class="k-sumber">Sumber Dana</th>
        <th class="k-jenis">Jenis</th>
        <th class="k-ket">Keterangan</th>
        <?php if ($aksi): ?><th class="ctr k-aksi">Tindakan</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php $n = 0; $total = 0; foreach ($baris as $b): $n++; $total += (int) $b['jumlah']; ?>
      <tr>
        <td class="ctr"><?= $n ?></td>
        <td><b><?= e((string) $b['nama']) ?></b></td>
        <td class="sm"><?= e((string) ($b['spesifikasi'] ?: '-')) ?></td>
        <td><?= e((string) ($b['lokasi'] ?: '-')) ?></td>
        <td class="ctr">
          <?php $k = (string) $b['kondisi']; ?>
          <span class="pill <?= $k === SARPRAS_KONDISI[0] ? 'pill-ok' : ($k === SARPRAS_KONDISI[1] ? 'pill-warn' : 'pill-D') ?>"><?= e($k ?: '-') ?></span>
        </td>
        <td class="ctr"><?= (int) $b['jumlah'] ?></td>
        <td><?= e((string) ($b['sumber_dana'] ?: '-')) ?></td>
        <td><?= e((string) ($b['jenis'] ?: '-')) ?></td>
        <td class="sm"><?= e((string) ($b['keterangan'] ?: '-')) ?></td>
        <?php if ($aksi): ?>
          <td>
            <div class="row-actions">
              <a class="btn btn-ghost btn-sm" href="tugas.php?kode=<?= e(SARPRAS_KODE_MENU) ?>&tab=data&edit=<?= (int) $b['id'] ?>#formBarang"><?= ikon('edit', 14) ?> Ubah</a>
              <form method="post" data-confirm="Hapus data barang &quot;<?= e((string) $b['nama']) ?>&quot;?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
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
        <th colspan="5" class="ctr">Jumlah</th>
        <th class="ctr"><?= (int) $total ?></th>
        <th colspan="<?= $aksi ? 4 : 3 ?>" class="ctr"><?= count($baris) ?> jenis barang</th>
      </tr>
    </tfoot>
  </table>
</div>
<?php if ($tombolLaporan): ?>
  <p class="hint mt8">Ingin ringkasannya? Buka tab <a href="tugas.php?kode=<?= e(SARPRAS_KODE_MENU) ?>&tab=laporan<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>">Laporan</a>,
    atau <a href="sarpras_export.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>">ekspor</a> /
    <a href="cetak_sarpras.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>" target="_blank">cetak</a> daftar ini.</p>
<?php endif; ?>
    <?php
}
