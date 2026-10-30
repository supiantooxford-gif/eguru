<?php
/**
 * LEMBAR CETAK — CATATAN PELANGGARAN SISWA (A4 portrait) untuk akun siswa (v1.61.0).
 *
 * Daftar catatan pelanggaran atas nama siswa yang sedang masuk beserta guru & mata pelajaran yang
 * menginputnya (permintaan pemilik: "Catatan Pelanggaran (yang diinput oleh setiap guru mata
 * Pelajaran)").
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$sw = require_siswa();
$kasus = siswa_pelanggaran((int) $sw['id']);

$jumlahStatus = ['baru' => 0, 'proses' => 0, 'selesai' => 0];
foreach ($kasus as $k) {
    $st = (string) $k['status'];
    $jumlahStatus[$st] = ($jumlahStatus[$st] ?? 0) + 1;
}
$autoPrint = isset($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Catatan Pelanggaran — <?= e((string) $sw['nama']) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:900px;margin:0 auto}
  .toolbar{max-width:900px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-foot{justify-content:flex-end}
  table.sheet.padat th,table.sheet.padat td{padding:4px 6px}
  @media print{
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
  }
  @page{size:A4 portrait;margin:14mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="siswa_pelanggaran.php">Kembali</a>
  <a class="btn btn-primary" href="cetak_siswa_pelanggaran.php?auto=1"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Catatan Pelanggaran Siswa', ['Administrasi Pembinaan Siswa']); ?>

    <?php sheet_meta([
        'Nama Siswa'   => (string) $sw['nama'],
        'NISN'         => (string) ($sw['nisn'] !== '' ? $sw['nisn'] : '-'),
        'Kelas'        => (string) ($sw['kelas_nama'] !== null ? $sw['kelas_nama'] : '-'),
        'Jumlah Catatan' => (string) count($kasus),
        'Rekap Status' => 'Baru ' . (int) ($jumlahStatus['baru'] ?? 0)
            . ', Dalam Proses ' . (int) ($jumlahStatus['proses'] ?? 0)
            . ', Selesai ' . (int) ($jumlahStatus['selesai'] ?? 0),
    ]); ?>

    <table class="sheet padat">
      <thead>
        <tr>
          <th class="ctr" style="width:28px">No</th>
          <th style="width:92px">Tanggal</th>
          <th style="width:86px">Jenis</th>
          <th>Catatan Pelanggaran &amp; Tindakan</th>
          <th style="width:140px">Dicatat oleh</th>
          <th class="ctr" style="width:74px">Status</th>
        </tr>
      </thead>
      <tbody>
      <?php $n = 0; foreach ($kasus as $k): $n++; [$jLbl, $sLbl] = siswa_pelanggaran_label((string) $k['jenis'], (string) $k['status']); ?>
        <tr>
          <td class="ctr"><?= $n ?></td>
          <td><?= e(tanggal_id((string) $k['tanggal'])) ?></td>
          <td><?= e($jLbl) ?></td>
          <td>
            <?= e((string) ($k['judul'] !== '' ? $k['judul'] : '-')) ?>
            <?php if (trim((string) $k['deskripsi']) !== ''): ?><br><span class="sm"><?= e((string) $k['deskripsi']) ?></span><?php endif; ?>
            <?php if (trim((string) $k['tindakan']) !== ''): ?><br><span class="sm"><b>Tindakan:</b> <?= e((string) $k['tindakan']) ?></span><?php endif; ?>
          </td>
          <td><?= e((string) ($k['guru_nama'] ?: '-')) ?>
            <?php if (trim((string) $k['mapel_nama']) !== ''): ?><br><span class="sm"><?= e((string) $k['mapel_nama']) ?></span><?php endif; ?></td>
          <td class="ctr"><?= e($sLbl) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($n === 0): ?>
        <tr><td colspan="6" class="ctr">Tidak ada catatan pelanggaran.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <p class="xs mut" style="margin-top:12px">
      Catatan ini diinput oleh guru mata pelajaran pada menu Pelanggaran Siswa
      <?= e(APP_NAME) ?>. Jenis catatan: Akademik, Kesehatan, Sosial, dan lainnya.
    </p>

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
