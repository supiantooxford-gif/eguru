<?php
/**
 * Lembar cetak **LAPORAN REKAP PELANGGARAN SISWA** (A4 portrait) — `cetak_rekap_pelanggaran.php`.
 *
 * Melengkapi halaman `laporan_pelanggaran.php` (permintaan pemilik v1.26.0): tabel 20 siswa dengan
 * pelanggaran terbanyak + rekap total pelanggaran yang diinput setiap guru mata pelajaran.
 * Filter kelas (`?kelas=ID`) diikuti supaya lembar ini sama dengan yang tampil di layar.
 *
 * Tanda tangan: **hanya kepala sekolah/madrasah di sisi KANAN** (pola `cetak_butir.php`).
 * Hak akses: kepala sekolah/madrasah + Super Admin dalam konteks (`require_rekap_pelanggaran()`).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$u = require_rekap_pelanggaran();

$ringkas = pelanggaran_ringkas();
$siswa = pelanggaran_rekap_siswa(20);
$guru = pelanggaran_rekap_guru();

$fKelas = (int) ($_GET['kelas'] ?? 0);
$kelas = $fKelas > 0 ? kelas_by_id($fKelas) : null;
if (!$kelas) {
    $fKelas = 0;
}
if ($fKelas > 0) {
    $siswa = array_values(array_filter($siswa, static fn(array $r): bool =>
        (string) ($r['kelas_nama'] ?? '') === (string) $kelas['nama']));
}
$labelKelas = $fKelas > 0 ? (string) $kelas['nama'] : 'Semua kelas';
$autoPrint = isset($_GET['auto']);
$qPrint = array_filter(['kelas' => $fKelas ?: null]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rekap Pelanggaran Siswa — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:900px;margin:0 auto}
  .toolbar{max-width:900px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table.sheet.rekap th,table.sheet.rekap td{padding:5px 6px}
  .ket-print{font-size:.72rem;color:#635F82;margin-top:10px;line-height:1.5}
  h3.sub-lap{font-size:.9rem;color:var(--hijau-tua);margin:18px 0 8px}
  /* Tata letak sempit HANYA untuk layar (saat mencetak lebar kertas ±695px, jadi aturan
     "max-width:900px" akan ikut aktif dan merusak tata letak lembar). */
  @media screen and (max-width:900px){
    .print-sheet{padding:18px 14px}
    .sheet-meta{grid-template-columns:minmax(0,1fr)}
  }
  @media print{
    /* Tema menetapkan body{min-height:100vh} → saat mencetak, 100vh = tinggi kertas dan peramban
       ikut mencetak halaman KEDUA yang hampir kosong. Matikan pada media cetak. */
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .sheet-wrap{max-width:none}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
    .sheet-scroll{overflow:visible}
  }
  @media print{
    /* Tanda tangan tetap di sisi KANAN (satu blok). */
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap;justify-content:flex-end}
  }
  @page{size:A4 portrait;margin:13mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="laporan_pelanggaran.php<?= $qPrint ? '?' . e(http_build_query($qPrint)) : '' ?>">Kembali</a>
  <a class="btn btn-primary" href="cetak_rekap_pelanggaran.php?<?= e(http_build_query(array_filter($qPrint + ['auto' => 1]))) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Laporan Rekap Pelanggaran Siswa', ['Administrasi Pembinaan Siswa']); ?>

    <?php sheet_meta([
        'Kelas'            => $labelKelas,
        'Total Pelanggaran' => (string) $ringkas['total'],
        'Siswa Tercatat'    => (string) $ringkas['siswa'],
        'Guru Penginput'    => (string) $ringkas['guru'],
        'Kelas Terlibat'    => (string) $ringkas['kelas'],
    ]); ?>

    <h3 class="sub-lap">20 Siswa dengan Pelanggaran Terbanyak</h3>
    <div class="sheet-scroll">
      <table class="sheet rekap padat">
        <thead>
          <tr>
            <th class="ctr" style="width:26px">No</th>
            <th style="width:30%">Nama Siswa</th>
            <th style="width:16%">NISN</th>
            <th class="ctr" style="width:14%">Kelas</th>
            <th class="ctr" style="width:20%">Jumlah Pelanggaran</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($siswa as $r): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><?= e((string) $r['nama']) ?></td>
            <td><?= trim((string) ($r['nisn'] ?? '')) !== '' ? e((string) $r['nisn']) : '-' ?></td>
            <td class="ctr"><?= e((string) ($r['kelas_nama'] ?: '-')) ?></td>
            <td class="ctr"><?= (int) $r['jml'] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$siswa): ?>
          <tr><td colspan="5" class="ctr mut">Belum ada pelanggaran tercatat pada filter ini.</td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="4">Jumlah</th>
            <th class="ctr"><?= array_sum(array_map(static fn(array $r): int => (int) $r['jml'], $siswa)) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <h3 class="sub-lap">Total Pelanggaran yang Diinput Setiap Guru Mata Pelajaran</h3>
    <div class="sheet-scroll">
      <table class="sheet rekap padat">
        <thead>
          <tr>
            <th class="ctr" style="width:26px">No</th>
            <th style="width:34%">Nama Guru</th>
            <th style="width:30%">Mata Pelajaran</th>
            <th class="ctr" style="width:18%">Jumlah Pelanggaran</th>
            <th class="ctr" style="width:14%">Siswa</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($guru as $r): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><?= e((string) $r['guru_nama']) ?></td>
            <td><?= trim((string) ($r['mapel_nama'] ?? '')) !== '' ? e((string) $r['mapel_nama']) : '-' ?></td>
            <td class="ctr"><?= (int) $r['jml'] ?></td>
            <td class="ctr"><?= (int) $r['jml_siswa'] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$guru): ?>
          <tr><td colspan="5" class="ctr mut">Belum ada guru yang mencatat pelanggaran.</td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="3">Jumlah</th>
            <th class="ctr"><?= array_sum(array_map(static fn(array $r): int => (int) $r['jml'], $guru)) ?></th>
            <th class="ctr"><?= (int) $ringkas['siswa'] ?></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="ket-print">
      Keterangan: tabel pertama memuat <b>20 siswa dengan pelanggaran terbanyak</b> (diurutkan dari
      jumlah terbanyak); tabel kedua memuat <b>jumlah pelanggaran yang diinput setiap guru mata
      pelajaran</b>. Satu catatan pelanggaran dihitung untuk guru yang mencatatnya, sehingga jumlah
      seluruh baris tabel kedua sama dengan total pelanggaran sekolah ini (<?= (int) $ringkas['total'] ?>).
    </div>

    <!-- Tanda tangan: HANYA kepala sekolah/madrasah, di sisi KANAN. -->
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
