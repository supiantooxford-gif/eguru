<?php
/**
 * EKSPOR DATA BARANG SARANA & PRASARANA (Excel .xlsx dibuat di peramban + CSV dari server) —
 * permintaan pemilik: "Tambahkan juga fitur impor, expor, Cetak".
 *
 * Kolom persis seperti tabel penginputan: No, Nama Barang, Spesifikasi, Lokasi, Kondisi, Jumlah,
 * Sumber Dana, Jenis, Keterangan. Mengikuti saringan yang sedang aktif di halaman data barang.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/sarpras.php';

$tugas = tugas_menu_by_kode(SARPRAS_KODE_MENU);
if (!$tugas) {
    require_login();
    flash('Menu Sarana & Prasarana belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu($tugas);
require_sekolah();

$f = [
    'cari' => (string) ($_GET['cari'] ?? ''),
    'kondisi' => (string) ($_GET['kondisi'] ?? ''),
    'sumber_dana' => (string) ($_GET['sumber_dana'] ?? ''),
    'jenis' => (string) ($_GET['jenis'] ?? ''),
    'lokasi' => (string) ($_GET['lokasi'] ?? ''),
];
$baris = sarpras_list($f);
$ringkas = sarpras_ringkas($baris);

$KEPALA = ['No', 'Nama Barang', 'Spesifikasi', 'Lokasi', 'Kondisi', 'Jumlah', 'Sumber Dana', 'Jenis', 'Keterangan'];
$data = [];
$n = 0;
foreach ($baris as $b) {
    $n++;
    $data[] = [
        $n, (string) $b['nama'], (string) ($b['spesifikasi'] ?? ''), (string) ($b['lokasi'] ?? ''),
        (string) ($b['kondisi'] ?? ''), (int) $b['jumlah'], (string) ($b['sumber_dana'] ?? ''),
        (string) ($b['jenis'] ?? ''), (string) ($b['keterangan'] ?? ''),
    ];
}

// CSV dilayani server supaya ekspor tetap bekerja walau skrip peramban gagal dimuat.
if (($_GET['unduh'] ?? '') === 'csv') {
    csv_kirim('data-barang-sarpras-' . date('Y-m-d') . '.csv', $KEPALA, $data);
}

$qFilter = sarpras_query($f);
$labelFilter = [];
foreach (['kondisi', 'sumber_dana', 'jenis', 'lokasi', 'cari'] as $k) {
    if (trim((string) $f[$k]) !== '') {
        $labelFilter[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . $f[$k];
    }
}

page_top('Ekspor Data Barang', 'Sarana & Prasarana');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('doc', 16) ?> Ekspor Data Barang</h2>
      <p>Berkas berisi kolom <b>No, Nama Barang, Spesifikasi, Lokasi, Kondisi, Jumlah, Sumber Dana,
        Jenis, Keterangan</b> sesuai data yang tampil (saringan ikut dibawa).</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($data) ?> jenis barang</span>
      <span class="chip"><?= (int) $ringkas['total_unit'] ?> unit</span>
      <a class="btn btn-ghost btn-sm" href="tugas.php?kode=<?= e(SARPRAS_KODE_MENU) ?>&tab=data<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>"><?= ikon('folder', 15) ?> Kembali ke Data Barang</a>
    </div>
  </div>

  <?php if (!$data): ?>
    <div class="empty"><?= ikon('folder', 34) ?>
      <h3>Belum ada data untuk diekspor</h3>
      <p>Isi data barang terlebih dahulu, atau ubah/bersihkan saringan.</p>
      <div class="flex" style="justify-content:center">
        <a class="btn btn-primary" href="tugas.php?kode=<?= e(SARPRAS_KODE_MENU) ?>&tab=data#formBarang"><?= ikon('plus', 16) ?> Input Data Barang</a>
        <a class="btn btn-ghost" href="sarpras_import.php"><?= ikon('upload', 16) ?> Impor Excel/CSV</a>
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
          <a class="btn btn-ghost" href="sarpras_export.php?unduh=csv<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>"><?= ikon('download', 16) ?> Unduh CSV</a>
        </div>
        <p class="hint" id="statusExcel" style="margin-bottom:0">Berkas Excel dibuat di peramban. Bila tombol Excel tidak bekerja,
          gunakan <b>Unduh CSV</b>.</p>
      </div>
      <div class="card" style="margin:0">
        <div class="card-head"><div><h2>2. Cetak / PDF</h2><p>Lembar A4 untuk arsip atau lampiran.</p></div></div>
        <div class="flex">
          <a class="btn btn-ghost" href="cetak_sarpras.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>" target="_blank"><?= ikon('print', 16) ?> Cetak Daftar Barang</a>
        </div>
        <p class="hint" style="margin-bottom:0">Lembar cetak memuat kop sekolah/madrasah dan tanda tangan kepala.</p>
      </div>
    </div>

    <div class="divider"></div>
    <div class="section-title mb8"><?= ikon('clipboard', 15) ?> Pratinjau 25 baris pertama</div>
    <div class="table-wrap">
      <table class="tbl tbl-impor">
        <thead><tr><?php foreach ($KEPALA as $i => $h): ?><th<?= $i === 0 || $i === 5 ? ' class="ctr"' : '' ?>><?= e($h) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach (array_slice($data, 0, 25) as $r): ?>
          <tr><?php foreach ($r as $i => $v): ?><td<?= $i === 0 || $i === 5 ? ' class="ctr"' : '' ?>><?= e((string) ($v === '' ? '-' : $v)) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
          <th colspan="5" class="ctr">Jumlah</th>
          <th class="ctr"><?= (int) $ringkas['total_unit'] ?></th>
          <th colspan="3" class="ctr"><?= count($data) ?> jenis barang</th>
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
    'nama' => 'Data Barang ' . nama_sekolah_tampil(),
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
      XLSX.utils.book_append_sheet(wb, ws, 'Data Barang');
      XLSX.writeFile(wb, 'data-barang-sarpras-<?= e(date('Y-m-d')) ?>.xlsx');
      if (status) status.textContent = 'Berkas Excel dibuat: ' + muatan.baris.length + ' baris.';
    } catch (e) {
      if (status) status.textContent = 'Gagal membuat berkas Excel (' + e.message + '). Gunakan Unduh CSV.';
    }
  });
})();
</script>
<?php
page_bottom();
