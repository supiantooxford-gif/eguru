<?php
/**
 * LEMBAR CETAK DATA BARANG SARANA & PRASARANA (A4 landscape) — permintaan pemilik:
 * "Tambahkan juga fitur impor, expor, Cetak".
 *
 * Kolom: No, Nama Barang, Spesifikasi, Lokasi, Kondisi, Jumlah, Sumber Dana, Jenis, Keterangan
 * (persis daftar permintaan pemilik), ditutup baris jumlah. Tanda tangan kepala
 * sekolah/madrasah di sisi KANAN (pola yang sama dengan lembar cetak lain aplikasi ini).
 * Saringan yang sedang dipakai di halaman data barang ikut dibawa (kondisi/sumber dana/jenis/lokasi/cari).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/sarpras.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

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
if (!$baris) {
    flash('Belum ada data barang untuk dicetak pada saringan yang dipilih.', 'warn');
    redirect('tugas.php?kode=' . SARPRAS_KODE_MENU . '&tab=data');
}

$ringkas = sarpras_ringkas($baris);
$labelFilter = [];
foreach (['kondisi', 'sumber_dana', 'jenis', 'lokasi', 'cari'] as $k) {
    if (trim((string) $f[$k]) !== '') {
        $labelFilter[] = ucfirst(str_replace('_', ' ', $k)) . ': ' . $f[$k];
    }
}
$qFilter = sarpras_query($f);
$autoPrint = isset($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Barang Sarana &amp; Prasarana — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:1160px;margin:0 auto}
  .toolbar{max-width:1160px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table.sheet.barang th,table.sheet.barang td{padding:3px 4px;font-size:.7rem;line-height:1.25}
  table.sheet.barang th{font-size:.62rem;letter-spacing:0;padding:4px 3px;vertical-align:middle}
  table.sheet.barang td.sm{font-size:.65rem}
  table.sheet.barang .k-no{width:22px}
  table.sheet.barang .k-nama{width:15%}
  table.sheet.barang .k-spek{width:19%}
  table.sheet.barang .k-lokasi{width:11%}
  table.sheet.barang .k-kondisi{width:9%}
  table.sheet.barang .k-jumlah{width:6%}
  table.sheet.barang .k-sumber{width:9%}
  table.sheet.barang .k-jenis{width:9%}
  /* Tata letak layar sempit HANYA untuk layar (media "screen"): saat MENCETAK, lebar kertas
     A4 landscape ±1032px, jadi aturan max-width ikut aktif bila tidak dibatasi. */
  @media screen and (max-width:900px){
    .print-sheet{padding:18px 14px}
    table.sheet.barang{min-width:900px}
    .sheet-meta{grid-template-columns:minmax(0,1fr)}
  }
  @media print{
    /* Tema menetapkan body{min-height:100vh}; saat mencetak 100vh = tinggi kertas sehingga
       dokumen menjadi setinggi halaman dan ikut mencetak halaman kedua yang hampir kosong. */
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .sheet-wrap{max-width:none}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
    .sheet-scroll{overflow:visible}
    table.sheet.barang{min-width:0}
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap;justify-content:flex-end}
  }
  @page{size:A4 landscape;margin:12mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="tugas.php?kode=<?= e(SARPRAS_KODE_MENU) ?>&tab=data<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>">Kembali</a>
  <a class="btn btn-ghost" href="sarpras_export.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>"><?= ikon('doc', 16) ?> Ekspor</a>
  <a class="btn btn-primary" href="cetak_sarpras.php?<?= e(http_build_query(array_filter(($f + []) + ['auto' => 1]))) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Data Barang Sarana & Prasarana', ['Daftar Inventaris ' . istilah_sekolah('Sekolah')]); ?>

    <?php sheet_meta([
        'Jumlah Jenis Barang' => (string) $ringkas['jenis_barang'],
        'Total Unit'          => (string) $ringkas['total_unit'],
        'Kondisi'             => implode(' / ', array_map(
            static fn(string $k, int $n): string => $k . ' ' . $n,
            array_keys($ringkas['kondisi']),
            array_values($ringkas['kondisi']))),
        'Saringan'            => $labelFilter ? implode(' · ', $labelFilter) : 'Seluruh data barang',
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet barang padat">
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
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($baris as $b): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e((string) $b['nama']) ?></b></td>
            <td class="sm"><?= e((string) ($b['spesifikasi'] ?: '-')) ?></td>
            <td><?= e((string) ($b['lokasi'] ?: '-')) ?></td>
            <td class="ctr"><?= e((string) ($b['kondisi'] ?: '-')) ?></td>
            <td class="ctr"><?= (int) $b['jumlah'] ?></td>
            <td><?= e((string) ($b['sumber_dana'] ?: '-')) ?></td>
            <td><?= e((string) ($b['jenis'] ?: '-')) ?></td>
            <td class="sm"><?= e((string) ($b['keterangan'] ?: '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="5" class="ctr">Jumlah</th>
            <th class="ctr"><?= (int) $ringkas['total_unit'] ?></th>
            <th colspan="3" class="ctr"><?= count($baris) ?> jenis barang</th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="ket-print">
      Keterangan: daftar ini memuat seluruh data barang <?= e(istilah_sekolah('sekolah')) ?> beserta
      spesifikasi, lokasi, kondisi, jumlah, sumber dana, jenis, dan keterangannya. Kondisi
      <b><?= e(SARPRAS_KONDISI[0]) ?></b> berarti barang layak pakai; <b><?= e(SARPRAS_KONDISI[1]) ?></b>
      dan <b><?= e(SARPRAS_KONDISI[2]) ?></b> perlu perbaikan atau penggantian.
    </div>

    <!-- Tanda tangan: kepala sekolah/madrasah di sisi KANAN (pola lembar cetak lain). -->
    <div class="sheet-foot kanan">
      <?php ttd_blok(ttd_mengetahui(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kanan'); ?>
    </div>

    <p class="xs mut center" style="margin-top:18px">
      Dicetak dari <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> pada <?= e(tanggal_id(date('Y-m-d'))) ?>.
    </p>
  </div>
</div>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
<?php terjemahkan_selesai(); ?>
