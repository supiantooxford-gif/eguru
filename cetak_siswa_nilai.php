<?php
/**
 * LEMBAR CETAK — REKAPAN NILAI SISWA (A4 landscape) untuk akun siswa (v1.61.0).
 *
 * Tabel rekapan seluruh mata pelajaran: rata-rata Formatif, Sumatif Lingkup Materi, ASAS, nilai
 * akhir, dan predikat — memakai rumus yang sama dengan halaman Penilaian.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$sw = require_siswa();
$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];
$taId = (int) ($ta['id'] ?? 0);
$semId = (int) ($sem['id'] ?? 0);
$rekap = siswa_nilai_rekap((int) $sw['id'], $taId, $semId);

$jumlah = 0.0;
$ada = 0;
$jmlSumatif = 0;
$jmlBelum = 0;
foreach ($rekap as $r) {
    if ($r['nilai_akhir'] !== null) {
        $ada++;
        $jumlah += (float) $r['nilai_akhir'];
    }
    // Keterangan sumatif (permintaan pemilik): jumlah sumatif yang sudah diadakan di kelas &
    // jumlah sumatif yang belum diikuti siswa ini.
    $jmlSumatif += (int) $r['sumatif_dilakukan'];
    $jmlBelum += count($r['sumatif_belum']);
}
$rata = $ada > 0 ? round($jumlah / $ada, 2) : null;

/** Label daftar "S1, S2" dari nomor kolom sumatif. */
$labelSumatif = static function (array $kolom): string {
    $bag = [];
    foreach ($kolom as $k) {
        $bag[] = 'S' . (int) $k;
    }
    return implode(', ', $bag);
};
$autoPrint = isset($_GET['auto']);
$qs = array_filter(['ta' => $taId ?: null, 'sem' => $semId ?: null]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rekap Nilai — <?= e((string) $sw['nama']) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:1130px;margin:0 auto}
  .toolbar{max-width:1130px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-foot{justify-content:flex-end}
  table.sheet.padat th,table.sheet.padat td{padding:5px 7px}
  @media print{
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
  }
  @page{size:A4 landscape;margin:13mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="siswa_nilai.php<?= e($taId ? '?ta=' . $taId . '&sem=' . $semId : '') ?>">Kembali</a>
  <a class="btn btn-primary" href="cetak_siswa_nilai.php?<?= e(http_build_query(array_merge($qs, ['auto' => 1]))) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Rekapan Nilai Siswa', ['Daftar Nilai Siswa']); ?>

    <?php sheet_meta([
        'Nama Siswa' => (string) $sw['nama'],
        'NISN'       => (string) ($sw['nisn'] !== '' ? $sw['nisn'] : '-'),
        'Kelas'      => (string) ($sw['kelas_nama'] !== null ? $sw['kelas_nama'] : '-'),
        'Periode'    => siswa_periode_teks($ta, $sem),
        'Jumlah Mapel' => (string) count($rekap),
        'Rata-rata'  => $rata === null ? '-' : r1($rata),
        'Sumatif Diadakan' => (string) $jmlSumatif . ' kali',
        'Sumatif Belum Diikuti' => (string) $jmlBelum,
    ]); ?>

    <table class="sheet padat">
      <thead>
        <tr>
          <th class="ctr" style="width:30px">No</th>
          <th>Mata Pelajaran</th>
          <th style="width:150px">Guru Pengampu</th>
          <th class="ctr" style="width:96px">Sumatif Lingkup Materi</th>
          <th class="ctr" style="width:92px">Sudah Berapa Kali Sumatif</th>
          <th class="ctr" style="width:110px">Sumatif yang Belum Diikuti</th>
          <th class="ctr" style="width:64px">ASAS</th>
          <th class="ctr" style="width:74px">Nilai Akhir</th>
          <th class="ctr" style="width:140px">Predikat</th>
        </tr>
      </thead>
      <tbody>
      <?php $n = 0; foreach ($rekap as $r): $n++; ?>
        <tr>
          <td class="ctr"><?= $n ?></td>
          <td><?= e((string) $r['mapel_nama']) ?></td>
          <td><?= e((string) ($r['guru_nama'] !== '' ? $r['guru_nama'] : '-')) ?></td>
          <td class="ctr"><?= $r['rata_sumatif'] === null ? '-' : e(r1($r['rata_sumatif'])) ?></td>
          <td class="ctr">
            <?php if ((int) $r['sumatif_dilakukan'] === 0): ?>-
            <?php else: ?>
              <?= (int) $r['sumatif_dilakukan'] ?> kali<br><span class="sm"><?= e($labelSumatif($r['sumatif_berjalan'])) ?></span>
            <?php endif; ?>
          </td>
          <td class="ctr">
            <?php if (!$r['sumatif_belum']): ?>Semua diikuti
            <?php else: ?><span class="sm"><?= e($labelSumatif($r['sumatif_belum'])) ?></span><?php endif; ?>
          </td>
          <td class="ctr"><?= $r['asas'] === null ? '-' : e(r1($r['asas'])) ?></td>
          <td class="ctr"><b><?= $r['nilai_akhir'] === null ? '-' : e(r1($r['nilai_akhir'])) ?></b></td>
          <td class="ctr"><?= $r['predikat'] === '-' ? '-' : e((string) $r['predikat']) . ' — ' . e(predikat_label((string) $r['predikat'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($n === 0): ?>
        <tr><td colspan="9" class="ctr">Belum ada nilai pada periode ini.</td></tr>
      <?php endif; ?>
      </tbody>
      <?php if ($n > 0): ?>
        <tfoot>
          <tr>
            <th colspan="4" class="ctr">Jumlah</th>
            <th class="ctr"><?= (int) $jmlSumatif ?> kali</th>
            <th class="ctr"><?= (int) $jmlBelum ?> belum</th>
            <th class="ctr" colspan="2">Rata-rata <?= $rata === null ? '-' : e(r1($rata)) ?></th>
            <th class="ctr"><?= $n ?> mapel</th>
          </tr>
        </tfoot>
      <?php endif; ?>
    </table>

    <p class="xs mut" style="margin-top:12px">
      Nilai akhir = rata-rata Sumatif Lingkup Materi dan Sumatif Akhir Semester (ASAS) sesuai pembobotan
      tiap mata pelajaran. <b>Penilaian proses (Formatif) tidak dicantumkan</b> karena tidak
      dijumlahkan. <b>Sudah Berapa Kali Sumatif</b> = jumlah sumatif yang sudah diadakan di kelas
      (kolom sumatif yang sudah ada nilainya), diikuti daftar kolomnya (S1, S2, ...).
      <b>Sumatif yang Belum Diikuti</b> = sumatif yang sudah diadakan di kelas tetapi siswa ini belum
      memiliki nilainya. Predikat: A = Amat Baik, B = Baik, C = Cukup, D = Kurang.
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
