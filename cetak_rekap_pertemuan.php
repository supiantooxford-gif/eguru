<?php
/**
 * Lembar cetak **REKAP JUMLAH PERTEMUAN GURU** (A4 landscape) — `cetak_rekap_pertemuan.php`.
 *
 * Melengkapi halaman `laporan_pertemuan.php` (permintaan pemilik v1.28.0): jumlah tatap muka tiap
 * guru per kelas & mata pelajaran, diambil dari jurnal harian pembelajaran.
 * Seluruh saringan halaman diikuti (`?ta=&sem=&periode=semua&kelas=&guru=&mapel=`) supaya lembar
 * ini sama dengan yang tampil di layar.
 *
 * Kolom: No, Nama Guru, Kelas, Mata Pelajaran, Jumlah Tatap Muka (+ Jumlah hari mengajar).
 * Tanda tangan: **hanya kepala sekolah/madrasah di sisi KANAN** (pola `cetak_rekap_pelanggaran.php`).
 * Hak akses: kepala sekolah/madrasah + Super Admin dalam konteks (`require_rekap_pertemuan()`).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$u = require_rekap_pertemuan();

$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];
$semuaPeriode = ($_GET['periode'] ?? '') === 'semua';

$fKelas = (int) ($_GET['kelas'] ?? 0);
$kelas = $fKelas > 0 ? kelas_by_id($fKelas) : null;
if (!$kelas) {
    $fKelas = 0;
}
$fGuru = (int) ($_GET['guru'] ?? 0);
$guru = $fGuru > 0 ? guru_by_id($fGuru) : null;
if (!$guru) {
    $fGuru = 0;
}
$fMapel = (int) ($_GET['mapel'] ?? 0);
$mapel = $fMapel > 0 ? mapel_by_id($fMapel) : null;
if (!$mapel) {
    $fMapel = 0;
}

$f = ['ta' => $ta, 'sem' => $sem, 'semua' => $semuaPeriode,
      'kelas' => $fKelas, 'guru' => $fGuru, 'mapel' => $fMapel];
$rekap = pertemuan_rekap($f);
$ringkas = pertemuan_ringkas($f);

$autoPrint = isset($_GET['auto']);
$qPrint = array_filter([
    'ta' => $ta ? (int) $ta['id'] : null,
    'sem' => $sem ? (int) $sem['id'] : null,
    'periode' => $semuaPeriode ? 'semua' : null,
    'kelas' => $fKelas ?: null,
    'guru' => $fGuru ?: null,
    'mapel' => $fMapel ?: null,
]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rekap Jumlah Pertemuan Guru — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:1040px;margin:0 auto}
  .toolbar{max-width:1040px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table.sheet.rekap th,table.sheet.rekap td{padding:5px 6px}
  .ket-print{font-size:.72rem;color:#635F82;margin-top:10px;line-height:1.5}
  /* Tata letak sempit HANYA untuk layar (saat mencetak lebar kertas ±1040px, jadi aturan
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
  @page{size:A4 landscape;margin:12mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="laporan_pertemuan.php<?= $qPrint ? '?' . e(http_build_query($qPrint)) : '' ?>">Kembali</a>
  <a class="btn btn-primary" href="cetak_rekap_pertemuan.php?<?= e(http_build_query($qPrint + ['auto' => 1])) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Rekap Jumlah Pertemuan Guru', ['Jurnal Harian Pembelajaran', 'Tahun Ajaran ' . ta_nama($ta)]); ?>

    <?php sheet_meta([
        'Periode'            => $semuaPeriode ? 'Seluruh jurnal yang tercatat' : ('T.A. ' . ta_nama($ta) . ' - Semester ' . sem_nama($sem)),
        'Kelas'              => $fKelas > 0 ? (string) $kelas['nama'] : 'Semua kelas',
        'Nama Guru'          => $fGuru > 0 ? (string) $guru['nama'] : 'Semua guru',
        'Mata Pelajaran'     => $fMapel > 0 ? (string) $mapel['nama'] : 'Semua mata pelajaran',
        'Jumlah Tatap Muka'  => (string) $ringkas['total'],
        'Guru Mengisi Jurnal' => (string) $ringkas['guru'],
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet rekap padat">
        <thead>
          <tr>
            <th class="ctr" style="width:26px">No</th>
            <th style="width:30%">Nama Guru</th>
            <th class="ctr" style="width:14%">Kelas</th>
            <th style="width:28%">Mata Pelajaran</th>
            <th class="ctr" style="width:15%">Jumlah Tatap Muka</th>
            <th class="ctr" style="width:13%">Hari Mengajar</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($rekap as $r): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><?= e((string) $r['guru_nama']) ?></td>
            <td class="ctr"><?= e((string) $r['kelas_nama']) ?></td>
            <td><?= e((string) $r['mapel_nama']) ?></td>
            <td class="ctr"><b><?= (int) $r['jml'] ?></b></td>
            <td class="ctr"><?= (int) $r['jml_hari'] ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rekap): ?>
          <tr><td colspan="6" class="ctr mut">Belum ada jurnal pembelajaran pada periode/filter ini.</td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="4">Jumlah</th>
            <th class="ctr"><?= array_sum(array_map(static fn(array $r): int => (int) $r['jml'], $rekap)) ?></th>
            <th class="ctr"><?= (int) $ringkas['hari'] ?></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="ket-print">
      Keterangan: <b>Jumlah Tatap Muka</b> adalah banyaknya catatan jurnal yang diisi guru pada
      kombinasi kelas &amp; mata pelajaran tersebut, sedangkan <b>Hari Mengajar</b> adalah jumlah
      tanggal uniknya (satu hari dapat berisi lebih dari satu catatan jurnal). Rekap ini dihitung
      dari <?= (int) $ringkas['total'] ?> catatan jurnal, <?= (int) $ringkas['guru'] ?> guru,
      <?= (int) $ringkas['kelas'] ?> kelas, dan <?= (int) $ringkas['mapel'] ?> mata pelajaran.
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
