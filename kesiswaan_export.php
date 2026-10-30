<?php
/**
 * EKSPOR PROGRAM KERJA KESISWAAN (Excel .xlsx dibuat di peramban + CSV dari server) — permintaan
 * pemilik: "Tambahkan fitur impor dan ekspor dan juga cetak".
 *
 * Kolom persis seperti tabel penginputan: No, Jenis Kegiatan, Bentuk Kegiatan, Tujuan, Target,
 * Tahun Ajaran, Waktu Pelaksanaan, Sumber Dana, Keterangan. Mengikuti saringan yang sedang aktif.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kesiswaan.php';

$tugas = tugas_menu_by_kode(KS_KODE_MENU);
if (!$tugas) {
    require_login();
    flash('Menu Kesiswaan belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu($tugas);
require_sekolah();

$f = [
    'cari' => (string) ($_GET['cari'] ?? ''),
    'jenis' => (string) ($_GET['jenis'] ?? ''),
    'sumber_dana' => (string) ($_GET['sumber_dana'] ?? ''),
    'ta' => (string) ($_GET['ta'] ?? ''),
    'bulan' => (string) ($_GET['bulan'] ?? ''),
];
$baris = ks_list($f);
$ringkas = ks_ringkas($baris);

$KEPALA = ['No', 'Jenis Kegiatan', 'Bentuk Kegiatan', 'Tujuan', 'Target', 'Tahun Ajaran',
           'Waktu Pelaksanaan', 'Sumber Dana', 'Keterangan'];
$data = [];
$n = 0;
foreach ($baris as $r) {
    $n++;
    $data[] = [
        $n, (string) ($r['jenis'] ?? ''), (string) $r['bentuk'], (string) ($r['tujuan'] ?? ''),
        (string) ($r['target'] ?? ''), (string) ($r['ta'] ?? ''),
        ks_waktu_label((string) ($r['waktu'] ?? '')), (string) ($r['sumber_dana'] ?? ''),
        (string) ($r['keterangan'] ?? ''),
    ];
}

// CSV dilayani server supaya ekspor tetap bekerja walau skrip peramban gagal dimuat.
if (($_GET['unduh'] ?? '') === 'csv') {
    csv_kirim('program-kerja-kesiswaan-' . date('Y-m-d') . '.csv', $KEPALA, $data);
}

$qFilter = ks_query($f);
$labelFilter = ks_label_filter($f);

page_top('Ekspor Program Kerja Kesiswaan', 'Laporan Program Kerja Kesiswaan');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('doc', 16) ?> Ekspor Program Kerja Kesiswaan</h2>
      <p>Berkas berisi kolom <b>No, Jenis Kegiatan, Bentuk Kegiatan, Tujuan, Target, Tahun Ajaran,
        Waktu Pelaksanaan, Sumber Dana, Keterangan</b> sesuai data yang tampil (saringan ikut dibawa).</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($data) ?> kegiatan</span>
      <a class="btn btn-ghost btn-sm" href="tugas.php?kode=<?= e(KS_KODE_MENU) ?>&tab=data<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>"><?= ikon('clipboard', 15) ?> Kembali ke Data Program</a>
    </div>
  </div>

  <?php if (!$data): ?>
    <div class="empty"><?= ikon('clipboard', 34) ?>
      <h3>Belum ada data untuk diekspor</h3>
      <p>Isi program kerja terlebih dahulu, atau ubah/bersihkan saringan.</p>
      <div class="flex" style="justify-content:center">
        <a class="btn btn-primary" href="tugas.php?kode=<?= e(KS_KODE_MENU) ?>&tab=data#formProgram"><?= ikon('plus', 16) ?> Input Program Kerja</a>
        <a class="btn btn-ghost" href="kesiswaan_import.php"><?= ikon('upload', 16) ?> Impor Excel/CSV</a>
      </div>
    </div>
  <?php else: ?>
    <?php if ($labelFilter): ?>
      <div class="chip-list mb16">
        <?php foreach ($labelFilter as $l): ?><span class="chip"><?= e($l) ?></span><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="grid g2" style="gap:16px">
      <div class="card" style="margin:0">
        <div class="card-head"><div><h2>1. Unduh berkas</h2><p>Pilih format yang diinginkan.</p></div></div>
        <div class="flex">
          <button class="btn btn-primary" type="button" id="btnExcel"><?= ikon('doc', 16) ?> Unduh Excel (.xlsx)</button>
          <a class="btn btn-ghost" href="kesiswaan_export.php?unduh=csv<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>"><?= ikon('download', 16) ?> Unduh CSV</a>
        </div>
        <p class="hint" id="statusExcel" style="margin-bottom:0">Berkas Excel dibuat di peramban. Bila tombol Excel tidak bekerja,
          gunakan <b>Unduh CSV</b>.</p>
      </div>
      <div class="card" style="margin:0">
        <div class="card-head"><div><h2>2. Cetak / PDF</h2><p>Lembar A4 untuk arsip atau lampiran.</p></div></div>
        <div class="flex">
          <a class="btn btn-ghost" href="cetak_kesiswaan.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>" target="_blank"><?= ikon('print', 16) ?> Cetak Program Kerja</a>
        </div>
        <p class="hint" style="margin-bottom:0">Lembar cetak memuat kop sekolah/madrasah, rekap, dan tanda tangan kepala.</p>
      </div>
    </div>

    <div class="divider"></div>
    <div class="section-title mb8"><?= ikon('clipboard', 15) ?> Pratinjau 25 baris pertama</div>
    <div class="table-wrap">
      <table class="tbl tbl-impor">
        <thead><tr><?php foreach ($KEPALA as $i => $h): ?><th<?= $i === 0 || $i === 5 ? ' class="ctr"' : '' ?>><?= e($h) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach (array_slice($data, 0, 25) as $row): ?>
          <tr><?php foreach ($row as $i => $v): ?><td<?= $i === 0 || $i === 5 ? ' class="ctr"' : '' ?>><?= e((string) ($v === '' ? '-' : $v)) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
          <th colspan="8" class="ctr">Jumlah</th>
          <th class="ctr"><?= count($data) ?> kegiatan</th>
        </tr></tfoot>
      </table>
    </div>
    <?php if (count($data) > 25): ?>
      <p class="xs mut mt8">Menampilkan 25 dari <?= count($data) ?> baris. Berkas ekspor memuat SELURUH data.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script id="dataEkspor" type="application/json"><?= json_encode([
    'kepala' => $KEPALA,
    'baris' => $data,
    'nama' => 'Program Kerja Kesiswaan ' . nama_sekolah_tampil(),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/vendor/xlsx.full.min.js"></script>
<script>
(function () {
  var btn = document.getElementById('btnExcel');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var status = document.getElementById('statusExcel');
    try {
      var muatan = JSON.parse(document.getElementById('dataEkspor').textContent);
      var aoa = [muatan.kepala].concat(muatan.baris);
      var ws = XLSX.utils.aoa_to_sheet(aoa);
      var wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Program Kesiswaan');
      XLSX.writeFile(wb, 'program-kerja-kesiswaan-<?= e(date('Y-m-d')) ?>.xlsx');
      if (status) status.textContent = 'Berkas Excel dibuat: ' + muatan.baris.length + ' baris.';
    } catch (e) {
      if (status) status.textContent = 'Gagal membuat berkas Excel (' + e.message + '). Gunakan Unduh CSV.';
    }
  });
})();
</script>
<?php
page_bottom();
