<?php
/**
 * LEMBAR CETAK LAPORAN PROGRAM KERJA KESISWAAN (A4 landscape) — permintaan pemilik:
 * "Tambahkan fitur impor dan ekspor dan juga cetak".
 *
 * Kolom: No, Jenis Kegiatan, Bentuk Kegiatan, Tujuan, Target, Tahun Ajaran, Waktu Pelaksanaan,
 * Sumber Dana, Keterangan (persis daftar permintaan pemilik), ditutup baris jumlah. Ditambah
 * ringkasan (jumlah kegiatan, jenis kegiatan, bulan pelaksanaan). Tanda tangan kepala
 * sekolah/madrasah di sisi KANAN (pola lembar cetak lain).
 * Saringan yang sedang dipakai di halaman data ikut dibawa (jenis/tahun ajaran/bulan/sumber dana/cari).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/kesiswaan.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

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
if (!$baris) {
    flash('Belum ada program kerja untuk dicetak pada saringan yang dipilih.', 'warn');
    redirect('tugas.php?kode=' . KS_KODE_MENU . '&tab=data');
}

$ringkas = ks_ringkas($baris);
$labelFilter = ks_label_filter($f);
$qFilter = ks_query($f);
$autoPrint = isset($_GET['auto']);

// Bulan yang ADA kegiatannya (untuk identitas lembar).
$bulanAda = [];
foreach ($ringkas['bulan'] as $b => $jml) {
    if ($jml > 0) {
        $bulanAda[] = $b;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Program Kerja Kesiswaan — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:1160px;margin:0 auto}
  .toolbar{max-width:1160px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table.sheet.ks th,table.sheet.ks td{padding:3px 4px;font-size:.7rem;line-height:1.25}
  table.sheet.ks th{font-size:.62rem;letter-spacing:0;padding:4px 3px;vertical-align:middle}
  table.sheet.ks td.sm{font-size:.64rem}
  table.sheet.ks .k-no{width:22px}
  table.sheet.ks .k-jenis{width:9%}
  table.sheet.ks .k-bentuk{width:14%}
  table.sheet.ks .k-tujuan{width:15%}
  table.sheet.ks .k-target{width:14%}
  table.sheet.ks .k-ta{width:7%}
  table.sheet.ks .k-waktu{width:12%}
  table.sheet.ks .k-sumber{width:8%}
  /* Tata letak layar sempit HANYA untuk layar (media "screen"): saat mencetak, lebar kertas
     A4 landscape ±1032px sehingga aturan max-width ikut aktif bila tidak dibatasi. */
  @media screen and (max-width:900px){
    .print-sheet{padding:18px 14px}
    table.sheet.ks{min-width:900px}
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
    table.sheet.ks{min-width:0}
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap;justify-content:flex-end}
  }
  @page{size:A4 landscape;margin:12mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="tugas.php?kode=<?= e(KS_KODE_MENU) ?>&tab=data<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>">Kembali</a>
  <a class="btn btn-ghost" href="kesiswaan_export.php<?= $qFilter !== '' ? '?' . e($qFilter) : '' ?>"><?= ikon('doc', 16) ?> Ekspor</a>
  <a class="btn btn-primary" href="cetak_kesiswaan.php?<?= e(http_build_query(array_filter($f + ['auto' => 1]))) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Laporan Program Kerja Kesiswaan', ['Bidang Kesiswaan ' . istilah_sekolah('Sekolah')]); ?>

    <?php sheet_meta([
        'Jumlah Kegiatan'   => (string) $ringkas['kegiatan'],
        'Jenis Kegiatan'    => (string) $ringkas['jenis'],
        'Waktu Pelaksanaan' => $bulanAda ? implode(', ', $bulanAda) . ($ringkas['tanpa_waktu'] > 0
            ? ' (+' . $ringkas['tanpa_waktu'] . ' belum diisi)' : '') : 'Belum ada waktu pelaksanaan',
        'Saringan'          => $labelFilter ? implode(' · ', $labelFilter) : 'Seluruh program kerja',
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet ks padat">
        <thead>
          <tr>
            <th class="ctr k-no">No</th>
            <th class="k-jenis">Jenis Kegiatan</th>
            <th class="k-bentuk">Bentuk Kegiatan</th>
            <th class="k-tujuan">Tujuan</th>
            <th class="k-target">Target</th>
            <th class="ctr k-ta">Tahun Ajaran</th>
            <th class="k-waktu">Waktu Pelaksanaan</th>
            <th class="k-sumber">Sumber Dana</th>
            <th class="k-ket">Keterangan</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($baris as $r): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><?= e((string) ($r['jenis'] ?: '-')) ?></td>
            <td><b><?= e((string) $r['bentuk']) ?></b></td>
            <td class="sm"><?= e((string) ($r['tujuan'] ?: '-')) ?></td>
            <td class="sm"><?= e((string) ($r['target'] ?: '-')) ?></td>
            <td class="ctr"><?= e((string) ($r['ta'] ?: '-')) ?></td>
            <td class="sm"><?= e(ks_waktu_label((string) ($r['waktu'] ?? '')) ?: '-') ?></td>
            <td><?= e((string) ($r['sumber_dana'] ?: '-')) ?></td>
            <td class="sm"><?= e((string) ($r['keterangan'] ?: '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="8" class="ctr">Jumlah</th>
            <th class="ctr"><?= count($baris) ?> kegiatan</th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="ket-print">
      Keterangan: daftar ini memuat program kerja bidang Kesiswaan <?= e(istilah_sekolah('sekolah')) ?>
      beserta tujuan, target, tahun ajaran, waktu pelaksanaan, sumber dana, dan keterangannya.
      Waktu pelaksanaan ditulis dengan nama bulan mengikuti tahun ajaran (Juli s.d. Juni).
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
