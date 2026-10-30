<?php
/**
 * LAPORAN REKAP JUMLAH PERTEMUAN GURU — `laporan_pertemuan.php`.
 *
 * Permintaan pemilik (v1.28.0): *"Pada halaman akun kepala Madrasah/sekolah tambahkan Menu Laporan
 * Rekap Jumlah Pertemuan Guru. Data diambil dari jurnal yang diisi oleh masing-masing guru pada
 * setiap mata pelajaran di setiap kelasnya. Isinya dalam bentuk tabel: Nama Guru, Kelas, Mata
 * Pelajaran, Jumlah Tatap Muka, Lihat Jurnal Pembelajaran"*
 *
 * Keputusan pemilik (jangan diubah tanpa bertanya):
 *   1. **Jumlah Tatap Muka = banyaknya CATATAN JURNAL** (satu catatan jurnal = satu tatap muka);
 *   2. tersedia **lembar cetak A4** (`cetak_rekap_pertemuan.php`);
 *   3. data mengikuti **tahun ajaran & semester aktif** (bisa diganti) + opsi **semua periode**.
 *
 * Isi halaman:
 *   1. kartu **Periode** (pemilih tahun ajaran & semester + tombol "Semua periode");
 *   2. kartu ringkasan (total tatap muka, guru pengisi jurnal, kelas, mata pelajaran, kombinasi);
 *   3. saringan kelas / nama guru / mata pelajaran;
 *   4. **tabel rekap** — satu baris per kombinasi Guru + Kelas + Mata Pelajaran dengan kolom
 *      Nama Guru, Kelas, Mata Pelajaran, Jumlah Tatap Muka, dan **Lihat Jurnal Pembelajaran**
 *      (membuka `jurnal.php` yang sudah tersaring untuk baris itu).
 *
 * Hak akses: **Kepala Sekolah/Madrasah** dan **Super Admin dalam konteks sekolah**
 * (`require_rekap_pertemuan()`); guru/operator/pengawas 403.
 *
 * Halaman ini murni laporan — tidak ada pengubahan data.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_rekap_pertemuan();

$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];
$semuaPeriode = ($_GET['periode'] ?? '') === 'semua';

// Saringan (opsional). Nilai asing/kelas sekolah lain diabaikan — bukan kebocoran data.
$fKelas = (int) ($_GET['kelas'] ?? 0);
if ($fKelas > 0 && !kelas_by_id($fKelas)) {
    $fKelas = 0;
}
$fGuru = (int) ($_GET['guru'] ?? 0);
if ($fGuru > 0 && !guru_by_id($fGuru)) {
    $fGuru = 0;
}
$fMapel = (int) ($_GET['mapel'] ?? 0);
if ($fMapel > 0 && !mapel_by_id($fMapel)) {
    $fMapel = 0;
}

$f = ['ta' => $ta, 'sem' => $sem, 'semua' => $semuaPeriode,
      'kelas' => $fKelas, 'guru' => $fGuru, 'mapel' => $fMapel];

$rekap = pertemuan_rekap($f);
$ringkas = pertemuan_ringkas($f);
$adaFilter = $fKelas > 0 || $fGuru > 0 || $fMapel > 0;

// Parameter untuk lembar cetak — persis filter yang sedang dilihat.
$qCetak = array_filter([
    'ta' => $ta ? (int) $ta['id'] : null,
    'sem' => $sem ? (int) $sem['id'] : null,
    'periode' => $semuaPeriode ? 'semua' : null,
    'kelas' => $fKelas ?: null,
    'guru' => $fGuru ?: null,
    'mapel' => $fMapel ?: null,
]);

// Tautan "kembali ke periode aktif" / "semua periode" — saringan lain tetap dibawa.
$qDasar = array_filter(['kelas' => $fKelas ?: null, 'guru' => $fGuru ?: null, 'mapel' => $fMapel ?: null]);
$qAktif = array_filter($qDasar + ['ta' => $ta ? (int) $ta['id'] : null, 'sem' => $sem ? (int) $sem['id'] : null]);
$qSemua = array_filter($qDasar + ['periode' => 'semua']);

page_top('Rekap Jumlah Pertemuan Guru', 'Jumlah tatap muka tiap guru dari jurnal harian pembelajaran');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('calendar', 16) ?> Periode Rekap</h2>
      <p><?= $semuaPeriode
        ? 'Seluruh catatan jurnal yang tercatat (tanpa pembatasan tahun ajaran/semester).'
        : 'Tahun ajaran <b>' . e(ta_nama($ta)) . '</b> &middot; Semester <b>' . e(sem_nama($sem)) . '</b>' ?></p>
    </div>
    <div class="flex">
      <?php if (count(ta_list()) > 1 && !$semuaPeriode): ?>
        <form method="get" class="flex">
          <input type="hidden" name="kelas" value="<?= (int) $fKelas ?>">
          <input type="hidden" name="guru" value="<?= (int) $fGuru ?>">
          <input type="hidden" name="mapel" value="<?= (int) $fMapel ?>">
          <select name="ta" data-auto>
            <?php foreach (ta_list() as $t): ?>
              <option value="<?= (int) $t['id'] ?>" <?= $ta && (int) $t['id'] === (int) $ta['id'] ? 'selected' : '' ?>>T.A. <?= e((string) $t['nama']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($ta): ?>
            <select name="sem" data-auto>
              <?php foreach (semester_list((int) $ta['id']) as $sm): ?>
                <option value="<?= (int) $sm['id'] ?>" <?= $sem && (int) $sm['id'] === (int) $sem['id'] ? 'selected' : '' ?>>Semester <?= e((string) $sm['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </form>
      <?php endif; ?>
      <?php if ($semuaPeriode): ?>
        <a class="btn btn-ghost btn-sm" href="laporan_pertemuan.php<?= $qAktif ? '?' . e(http_build_query($qAktif)) : '' ?>"><?= ikon('calendar', 15) ?> Kembali ke periode aktif</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="laporan_pertemuan.php?<?= e(http_build_query($qSemua)) ?>"><?= ikon('clock', 15) ?> Semua periode</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php // Kartu ringkasan memakai pola `.card.stat.card-tight` + `.stat-ic` seperti dashboard. ?>
<div class="grid g4" style="gap:16px">
  <div class="card stat card-tight"><div class="stat-ic p"><?= ikon('clipboard', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['total'] ?></div><div class="stat-lbl">Jumlah Tatap Muka</div></div></div>
  <div class="card stat card-tight"><div class="stat-ic s"><?= ikon('users', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['guru'] ?></div><div class="stat-lbl">Guru Mengisi Jurnal</div></div></div>
  <div class="card stat card-tight"><div class="stat-ic o"><?= ikon('school', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['kelas'] ?></div><div class="stat-lbl">Kelas Diajar</div></div></div>
  <div class="card stat card-tight"><div class="stat-ic g"><?= ikon('book', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['mapel'] ?></div><div class="stat-lbl">Mata Pelajaran</div></div></div>
</div>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('chart', 16) ?> Rekap Jumlah Pertemuan per Guru, Kelas &amp; Mata Pelajaran</h2>
      <p>Data diambil dari <b>jurnal harian</b> yang diisi masing-masing guru. <b>Satu catatan jurnal
        dihitung satu tatap muka.</b> Klik <b>Lihat Jurnal Pembelajaran</b> untuk membuka jurnal guru
        tersebut pada kelas &amp; mata pelajaran itu.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($rekap) ?> baris</span>
      <a class="btn btn-ghost btn-sm" href="cetak_rekap_pertemuan.php<?= $qCetak ? '?' . e(http_build_query($qCetak)) : '' ?>" target="_blank"><?= ikon('print', 15) ?> Cetak Rekap</a>
    </div>
  </div>

  <form class="filterbar mb16" method="get">
    <?php if ($semuaPeriode): ?><input type="hidden" name="periode" value="semua"><?php else: ?>
      <input type="hidden" name="ta" value="<?= (int) ($ta['id'] ?? 0) ?>">
      <input type="hidden" name="sem" value="<?= (int) ($sem['id'] ?? 0) ?>">
    <?php endif; ?>
    <div style="min-width:180px">
      <label class="f" for="kelas">Kelas</label>
      <select id="kelas" name="kelas" data-auto>
        <option value="">Semua kelas</option>
        <?php foreach (kelas_list(true) as $k): ?>
          <option value="<?= (int) $k['id'] ?>" <?= $fKelas === (int) $k['id'] ? 'selected' : '' ?>><?= e((string) $k['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:220px">
      <label class="f" for="guru">Nama Guru</label>
      <select id="guru" name="guru" data-auto>
        <option value="">Semua guru</option>
        <?php foreach (guru_list(true) as $g): ?>
          <option value="<?= (int) $g['id'] ?>" <?= $fGuru === (int) $g['id'] ? 'selected' : '' ?>><?= e((string) $g['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:200px">
      <label class="f" for="mapel">Mata Pelajaran</label>
      <select id="mapel" name="mapel" data-auto>
        <option value="">Semua mata pelajaran</option>
        <?php foreach (mapel_list(true) as $m): ?>
          <option value="<?= (int) $m['id'] ?>" <?= $fMapel === (int) $m['id'] ? 'selected' : '' ?>><?= e((string) $m['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
    <?php if ($adaFilter): ?>
      <a class="btn btn-ghost" href="laporan_pertemuan.php<?= $semuaPeriode ? '?periode=semua' : ajaran_qs($ta, $sem) ?>">Reset</a>
    <?php endif; ?>
  </form>

  <?php if (!$rekap): ?>
    <div class="empty">
      <?= ikon('clipboard', 34) ?>
      <h3>Belum ada jurnal pembelajaran pada periode ini</h3>
      <p><?= $adaFilter
        ? 'Tidak ada jurnal yang cocok dengan saringan yang dipilih. Ubah saringan atau tampilkan semuanya.'
        : 'Rekap akan terisi setelah guru mengisi jurnal harian pembelajaran pada menu Jurnal Harian Guru.' ?></p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblRekapPertemuan">
        <thead>
          <tr>
            <th class="ctr" style="width:44px">No</th>
            <th>Nama Guru</th>
            <th>Kelas</th>
            <th>Mata Pelajaran</th>
            <th class="ctr">Jumlah Tatap Muka</th>
            <th class="ctr" style="width:190px">Lihat Jurnal Pembelajaran</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rekap as $i => $r): ?>
          <tr>
            <td class="ctr"><?= $i + 1 ?></td>
            <td><b><?= e((string) $r['guru_nama']) ?></b>
              <?php if (trim((string) ($r['tgl_awal'] ?? '')) !== ''): ?>
                <span class="tbl-sub"><?= e(tanggal_id((string) $r['tgl_awal'])) ?> &ndash; <?= e(tanggal_id((string) $r['tgl_akhir'])) ?></span>
              <?php endif; ?>
            </td>
            <td><?= e((string) $r['kelas_nama']) ?></td>
            <td><?= e((string) $r['mapel_nama']) ?></td>
            <td class="ctr"><span class="pill pill-ok"><?= (int) $r['jml'] ?></span>
              <?php if ((int) $r['jml_hari'] !== (int) $r['jml']): ?>
                <span class="tbl-sub"><?= (int) $r['jml_hari'] ?> hari</span>
              <?php endif; ?>
            </td>
            <td class="ctr">
              <a class="btn btn-ghost btn-sm" href="<?= e(pertemuan_link_jurnal($r, $ta, $sem, $semuaPeriode)) ?>"><?= ikon('eye', 14) ?> Lihat Jurnal</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="4" class="kiri"><b>Jumlah</b></td>
            <td class="ctr"><b><?= array_sum(array_map(static fn(array $r): int => (int) $r['jml'], $rekap)) ?></b></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="hint">Tabel memuat <b>satu baris per kombinasi Guru + Kelas + Mata Pelajaran</b>.
      <b>Jumlah Tatap Muka</b> adalah banyaknya catatan jurnal pada kombinasi tersebut
      (<?= (int) $ringkas['total'] ?> tatap muka dari <?= (int) $ringkas['hari'] ?> hari mengajar).</p>
  <?php endif; ?>
</div>
<?php
page_bottom();
