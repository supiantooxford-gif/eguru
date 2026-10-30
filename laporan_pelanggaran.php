<?php
/**
 * LAPORAN REKAP PELANGGARAN SISWA — `laporan_pelanggaran.php`.
 *
 * Permintaan pemilik (v1.26.0): *"Pada halaman akun kepala Madrasah/sekolah tambahkan Menu Laporan
 * Rekap Pelanggaran Siswa (Total Pelanggaran yang diinput oleh setiap guru mata pelajaran). Isinya
 * dalam bentuk tabel menampilkan 20 orang siswa dengan paling banyak melakukan pelanggaran.
 * Tabelnya: Nama Siswa, NISN, Kelas, Jumlah Pelanggaran, Lihat Pelanggaran"*
 *
 * Isi halaman:
 *   1. kartu ringkasan (total pelanggaran, siswa tercatat, guru penginput, kelas terlibat);
 *   2. **tabel 20 siswa dengan pelanggaran TERBANYAK** — kolom Nama Siswa, NISN, Kelas,
 *      Jumlah Pelanggaran, dan **Lihat Pelanggaran** (tautan ke daftar pelanggaran siswa itu pada
 *      menu Pelanggaran Siswa: `kasus.php?siswa=ID`);
 *   3. **rekap per guru mata pelajaran yang menginput** (nama guru, mata pelajaran, jumlah
 *      pelanggaran, jumlah siswa) — bagian "Total Pelanggaran yang diinput oleh setiap guru mapel".
 *
 * Hak akses: **Kepala Sekolah/Madrasah** dan **Super Admin dalam konteks sekolah**
 * (`require_rekap_pelanggaran()`); guru & pengawas 403.
 *
 * Tidak ada pengubahan data di halaman ini (murni laporan) — lembar cetaknya
 * `cetak_rekap_pelanggaran.php`.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_rekap_pelanggaran();

$ringkas = pelanggaran_ringkas();
$siswa = pelanggaran_rekap_siswa(20);
$guru = pelanggaran_rekap_guru();

// Filter kelas (opsional) — rekap siswa bisa dipersempit ke satu kelas; rekap guru tetap sekolah itu.
$daftarKelas = kelas_list(true);
$fKelas = (int) ($_GET['kelas'] ?? 0);
if ($fKelas > 0 && !kelas_by_id($fKelas)) {
    // kelas sekolah lain / tidak ada → diabaikan (bukan kebocoran data)
    $fKelas = 0;
}
if ($fKelas > 0) {
    $siswa = array_values(array_filter($siswa, static fn(array $r): bool =>
        (string) ($r['kelas_nama'] ?? '') === (string) (kelas_by_id($fKelas)['nama'] ?? '')
    ));
}
$qCetak = array_filter(['kelas' => $fKelas ?: null]);

page_top('Rekap Pelanggaran Siswa', 'Total pelanggaran per siswa & per guru mata pelajaran');
?>
<?php // Kartu ringkasan memakai pola `.card.stat.card-tight` + `.stat-ic` seperti dashboard. ?>
<div class="grid g4" style="gap:16px">
  <div class="card stat card-tight"><div class="stat-ic p"><?= ikon('alert', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['total'] ?></div><div class="stat-lbl">Total Pelanggaran</div></div></div>
  <div class="card stat card-tight"><div class="stat-ic s"><?= ikon('student', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['siswa'] ?></div><div class="stat-lbl">Siswa Tercatat</div></div></div>
  <div class="card stat card-tight"><div class="stat-ic o"><?= ikon('users', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['guru'] ?></div><div class="stat-lbl">Guru Penginput</div></div></div>
  <div class="card stat card-tight"><div class="stat-ic g"><?= ikon('school', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['kelas'] ?></div><div class="stat-lbl">Kelas Terlibat</div></div></div>
</div>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('alert', 16) ?> 20 Siswa dengan Pelanggaran Terbanyak</h2>
      <p>Diurutkan dari jumlah pelanggaran terbanyak. Klik <b>Lihat Pelanggaran</b> untuk membuka
        daftar pelanggaran siswa tersebut pada menu <b>Pelanggaran Siswa</b>.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($siswa) ?> siswa</span>
      <a class="btn btn-ghost btn-sm" href="cetak_rekap_pelanggaran.php<?= $qCetak ? '?' . e(http_build_query($qCetak)) : '' ?>" target="_blank"><?= ikon('print', 15) ?> Cetak Rekap</a>
    </div>
  </div>

  <?php if ($daftarKelas): ?>
    <form class="filterbar mb16" method="get">
      <div>
        <label class="f" for="kelas">Kelas</label>
        <select id="kelas" name="kelas" data-auto>
          <option value="">— semua kelas —</option>
          <?php foreach ($daftarKelas as $k): ?>
            <option value="<?= (int) $k['id'] ?>" <?= $fKelas === (int) $k['id'] ? 'selected' : '' ?>><?= e((string) $k['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($fKelas > 0): ?><a class="btn btn-ghost" href="laporan_pelanggaran.php">Reset</a><?php endif; ?>
    </form>
  <?php endif; ?>

  <?php if (!$siswa): ?>
    <div class="empty">
      <?= ikon('alert', 34) ?>
      <h3>Belum ada pelanggaran tercatat</h3>
      <p><?= $fKelas > 0
        ? 'Tidak ada pelanggaran pada kelas yang dipilih. Pilih kelas lain atau tampilkan semuanya.'
        : 'Rekap akan terisi setelah guru mencatat pelanggaran siswa pada menu Pelanggaran Siswa.' ?></p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblRekapPelanggaran">
        <thead>
          <tr>
            <th class="ctr" style="width:44px">No</th>
            <th>Nama Siswa</th>
            <th>NISN</th>
            <th>Kelas</th>
            <th class="ctr">Jumlah Pelanggaran</th>
            <th class="ctr" style="width:170px">Lihat Pelanggaran</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($siswa as $i => $r): ?>
          <tr>
            <td class="ctr"><?= $i + 1 ?></td>
            <td><b><?= e((string) $r['nama']) ?></b>
              <?php if (trim((string) ($r['nis'] ?? '')) !== ''): ?><span class="tbl-sub">NIS <?= e((string) $r['nis']) ?></span><?php endif; ?>
            </td>
            <td><?= trim((string) ($r['nisn'] ?? '')) !== '' ? e((string) $r['nisn']) : '-' ?></td>
            <td><?= e((string) ($r['kelas_nama'] ?: '-')) ?></td>
            <td class="ctr"><span class="pill <?= (int) $r['jml'] >= 3 ? 'pill-D' : ((int) $r['jml'] >= 2 ? 'pill-C' : 'pill-ok') ?>"><?= (int) $r['jml'] ?></span></td>
            <td class="ctr">
              <a class="btn btn-ghost btn-sm" href="kasus.php?siswa=<?= (int) $r['siswa_id'] ?>#tabelKasus"><?= ikon('eye', 14) ?> Lihat Pelanggaran</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="4" class="kiri"><b>Jumlah</b></td>
            <td class="ctr"><b><?= array_sum(array_map(static fn(array $r): int => (int) $r['jml'], $siswa)) ?></b></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="hint">Tabel menampilkan <b>20 siswa teratas</b>. Kolom <b>Jumlah Pelanggaran</b> menghitung
      seluruh catatan pelanggaran siswa tersebut — dari guru mata pelajaran mana pun.</p>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('users', 16) ?> Total Pelanggaran yang Diinput Setiap Guru Mata Pelajaran</h2>
      <p>Banyaknya catatan pelanggaran yang dibuat masing-masing guru, beserta mata pelajaran yang
        tercatat pada catatan itu.</p>
    </div>
    <span class="chip"><?= count($guru) ?> guru</span>
  </div>

  <?php if (!$guru): ?>
    <div class="empty">
      <?= ikon('users', 34) ?>
      <h3>Belum ada guru yang mencatat pelanggaran</h3>
      <p>Kolom ini terisi setelah guru menyimpan catatan pelanggaran siswa.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblRekapGuru">
        <thead>
          <tr>
            <th class="ctr" style="width:44px">No</th>
            <th>Nama Guru</th>
            <th>Mata Pelajaran</th>
            <th class="ctr">Jumlah Pelanggaran</th>
            <th class="ctr">Jumlah Siswa</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($guru as $i => $r): ?>
          <tr>
            <td class="ctr"><?= $i + 1 ?></td>
            <td><b><?= e((string) $r['guru_nama']) ?></b></td>
            <td><?= trim((string) ($r['mapel_nama'] ?? '')) !== '' ? e((string) $r['mapel_nama']) : '-' ?></td>
            <td class="ctr"><b><?= (int) $r['jml'] ?></b></td>
            <td class="ctr"><?= (int) $r['jml_siswa'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" class="kiri"><b>Jumlah</b></td>
            <td class="ctr"><b><?= array_sum(array_map(static fn(array $r): int => (int) $r['jml'], $guru)) ?></b></td>
            <td class="ctr"><b><?= (int) $ringkas['siswa'] ?></b></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="hint">Satu catatan pelanggaran dihitung untuk guru yang <b>mencatatnya</b>, sehingga jumlah
      seluruh baris sama dengan total pelanggaran sekolah ini (<?= (int) $ringkas['total'] ?>).</p>
  <?php endif; ?>
</div>
<?php
page_bottom();
