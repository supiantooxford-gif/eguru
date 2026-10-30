<?php
/**
 * LEMBAR CETAK LAPORAN PROGRAM KERJA OSIM/OSIS (A4 landscape) — permintaan pemilik:
 * "Selain itu buat juga 'Laporan Program Kerja' dengan menu inputan: Nama Program, Waktu Pelaksanaan,
 * Target, Tahun Ajaran, Waktu Pelaksanaan, Terlaksana (Ya atau Tidak), Keterangan. Tambahkan fitur
 * impor dan ekspor dan juga cetak".
 *
 * Kolom: No, Nama Program, Waktu Pelaksanaan (tanggal lengkap), Target, Tahun Ajaran, Terlaksana,
 * Keterangan + ringkasan keterlaksanaan. Tanda tangan kepala di sisi KANAN.
 * Saringan (cari/tahun ajaran/terlaksana/rentang tanggal) ikut dibawa.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/osim.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

// Kode menu dari URL (?kode=pramuka memakai modul yang sama dengan ?kode=osim).
$kode = osim_kode_normal((string) ($_GET['kode'] ?? OSIM_KODE_MENU));
$konfig = osim_menu_konfig($kode);
$tugas = tugas_menu_by_kode($kode);
if (!$tugas) {
    require_login();
    flash('Menu ' . $konfig['menu_bawaan'] . ' belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu($tugas);
require_sekolah();

$f = [
    'cari' => (string) ($_GET['cari'] ?? ''),
    'ta' => (string) ($_GET['ta'] ?? ''),
    'terlaksana' => (string) ($_GET['terlaksana'] ?? ''),
    'dari' => (string) ($_GET['dari'] ?? ''),
    'sampai' => (string) ($_GET['sampai'] ?? ''),
];
$baris = osim_program_list($f, $kode);
if (!$baris) {
    flash('Belum ada program kerja untuk dicetak pada saringan yang dipilih.', 'warn');
    redirect('tugas.php?kode=' . $kode . '&bagian=program&tab=data');
}

$ringkas = osim_ringkas_program($baris);
$labelFilter = osim_label_filter($f, 'program');
$qFilter = osim_query($f);
$autoPrint = isset($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Program Kerja OSIM/OSIS — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:1160px;margin:0 auto}
  .toolbar{max-width:1160px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table.sheet.osimw th,table.sheet.osimw td{padding:4px 5px;font-size:.72rem;line-height:1.25}
  table.sheet.osimw th{font-size:.64rem;letter-spacing:0;padding:5px 4px;vertical-align:middle}
  table.sheet.osimw td.sm{font-size:.68rem}
  table.sheet.osimw .k-no{width:24px}
  table.sheet.osimw .k-program{width:20%}
  table.sheet.osimw .k-waktu{width:12%}
  table.sheet.osimw .k-target{width:16%}
  table.sheet.osimw .k-ta{width:9%}
  table.sheet.osimw .k-terlaksana{width:9%}
  /* Tata letak layar sempit HANYA untuk layar (media "screen"). */
  @media screen and (max-width:900px){
    .print-sheet{padding:18px 14px}
    table.sheet.osimw{min-width:900px}
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
    table.sheet.osimw{min-width:0}
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap;justify-content:flex-end}
  }
  @page{size:A4 landscape;margin:12mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="tugas.php?kode=<?= e($kode) ?>&bagian=program&tab=data<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>">Kembali</a>
  <a class="btn btn-ghost" href="osim_export.php?<?= e('kode=' . $kode . '&bagian=program' . ($qFilter !== '' ? '&' . $qFilter : '')) ?>"><?= ikon('doc', 16) ?> Ekspor</a>
  <a class="btn btn-ghost" href="cetak_osim_organisasi.php?kode=<?= e($kode) ?>" target="_blank"><?= ikon('print', 16) ?> Cetak Susunan Organisasi</a>
  <a class="btn btn-primary" href="cetak_osim_program.php?<?= e(http_build_query(array_filter($f + ['kode' => $kode, 'auto' => 1]))) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Laporan Program Kerja OSIM/OSIS', ['Bidang Kesiswaan ' . istilah_sekolah('Sekolah')]); ?>

    <?php sheet_meta([
        'Jumlah Program' => (string) $ringkas['program'],
        'Terlaksana'     => (string) $ringkas['ya'],
        'Belum Terlaksana' => (string) $ringkas['tidak'],
        'Belum Ditentukan' => (string) $ringkas['belum'],
        'Saringan'       => $labelFilter ? implode(' · ', $labelFilter) : 'Seluruh program kerja',
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet osimw padat">
        <thead>
          <tr>
            <th class="ctr k-no">No</th>
            <th class="k-program">Nama Program</th>
            <th class="k-waktu">Waktu Pelaksanaan</th>
            <th class="k-target">Target</th>
            <th class="ctr k-ta">Tahun Ajaran</th>
            <th class="ctr k-terlaksana">Terlaksana</th>
            <th class="k-ket">Keterangan</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($baris as $r): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e((string) $r['nama']) ?></b></td>
            <td class="sm"><?= e(osim_tanggal_label((string) ($r['waktu'] ?? '')) ?: '-') ?></td>
            <td class="sm"><?= e((string) ($r['target'] ?: '-')) ?></td>
            <td class="ctr"><?= e((string) ($r['ta'] ?: '-')) ?></td>
            <td class="ctr"><?= e(osim_terlaksana_sah((string) ($r['terlaksana'] ?? '')) ?: '-') ?></td>
            <td class="sm"><?= e((string) ($r['keterangan'] ?: '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="6" class="ctr">Jumlah</th>
            <th class="ctr"><?= count($baris) ?> program</th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="ket-print">
      Keterangan: daftar ini memuat program kerja OSIM/OSIS <?= e(istilah_sekolah('sekolah')) ?> beserta
      waktu pelaksanaan, target, tahun ajaran, keterlaksanaan, dan keterangannya. Kolom
      <b>Terlaksana</b> berisi <b>Ya</b> bila program sudah dilaksanakan dan <b>Tidak</b> bila belum.
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
