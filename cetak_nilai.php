<?php
/**
 * Lembar cetak Daftar Nilai Siswa (A4 landscape).
 * Kolom: Formatif, Sumatif Lingkup Materi, ASAS, Nilai Akhir, Predikat.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$u = require_sekolah(); // super admin tanpa konteks dialihkan ke Kelola Sekolah
$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];
if (!$ta || !$sem) {
    flash('Atur tahun ajaran & semester terlebih dahulu.', 'warn');
    redirect('nilai.php');
}

$kelasId = (int) ($_GET['kelas'] ?? 0);
$mapelId = (int) ($_GET['mapel'] ?? 0);
$kelas = kelas_by_id($kelasId);
$mapel = mapel_by_id($mapelId);
if (!$kelas || !$mapel) {
    flash('Pilih kelas dan mata pelajaran yang akan dicetak nilainya.', 'warn');
    redirect('nilai.php');
}
// Penjaga cakupan SETELAH memastikan kelas/mapel ada: permintaan tanpa parameter
// memberi arahan ramah, bukan 403 "Kelas itu tidak Anda ampu".
if (!boleh_kelas($kelasId)) {
    tolak403('Kelas itu tidak Anda ampu.');
}
if (!boleh_mapel($mapelId)) {
    tolak403('Mata pelajaran itu tidak Anda ampu.');
}

$jf = jumlah_formatif();
$js = jumlah_sumatif();
$bobotAsas = bobot_asas();
$siswa = siswa_kelas($kelasId, true);
$nilai = nilai_map($kelasId, $mapelId, (int) $ta['id'], (int) $sem['id']);
$guru = guru_pengampu($kelasId, $mapelId);
$autoPrint = isset($_GET['auto']);

// Rata-rata kelas per kolom
$kolF = array_fill(1, $jf, []);
$kolS = array_fill(1, $js, []);
$kolA = [];
$kolNA = [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Daftar Nilai <?= e(kelas_nama($kelasId)) ?> — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:1180px;margin:0 auto}
  .toolbar{max-width:1180px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  .sheet-foot{justify-content:space-between}
  .ket{font-size:.72rem;color:#635F82;margin-top:10px;line-height:1.5}
  @media (max-width:900px){
    .print-sheet{padding:18px 14px}
    table.sheet.nilai{min-width:900px}
    .sheet-meta{grid-template-columns:minmax(0,1fr)}
    .sheet-foot{flex-direction:column;gap:20px}
  }
  @media print{body{background:#fff;padding:0}.toolbar,.no-print{display:none !important}.sheet-wrap{max-width:none}.print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}.sheet-scroll{overflow:visible}table.sheet.nilai{min-width:0}}
  @page{size:A4 landscape;margin:12mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="nilai.php?<?= e(http_build_query(['kelas' => $kelasId, 'mapel' => $mapelId, 'ta' => (int) $ta['id'], 'sem' => (int) $sem['id']])) ?>">Kembali</a>
  <a class="btn btn-ghost" href="nilai_export.php?<?= e(http_build_query(['kelas' => $kelasId, 'mapel' => $mapelId, 'ta' => (int) $ta['id'], 'sem' => (int) $sem['id']])) ?>"><?= ikon('doc', 16) ?> Unduh CSV</a>
  <a class="btn btn-primary" href="cetak_nilai.php?<?= e(http_build_query(['kelas' => $kelasId, 'mapel' => $mapelId, 'ta' => (int) $ta['id'], 'sem' => (int) $sem['id'], 'auto' => 1])) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Daftar Nilai Siswa', ['Kurikulum Merdeka']); ?>

    <?php sheet_meta([
        'Kelas'          => (string) $kelas['nama'],
        'Mata Pelajaran' => (string) $mapel['nama'],
        'Tahun Ajaran'   => ta_nama($ta) . ' — Semester ' . sem_nama($sem),
        'Guru Pengampu'  => $guru ? (string) $guru['nama'] : '-',
        'Jumlah Siswa'   => (string) count($siswa),
        'Pembobotan'     => 'Sumatif Lingkup Materi ' . r0(bobot_sumatif()) . '% + ASAS ' . r0($bobotAsas) . '%',
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet nilai padat">
        <thead>
          <?php // Baris judul KELOMPOK kolom ("Formatif" & "Sumatif Lingkup Materi") dibuat sama
                // seperti tabel pada halaman input nilai (nilai.php), lalu di bawahnya baris
                // singkatan F1..Fn / S1..Sn seperti sebelumnya. Lebar kolom diletakkan pada baris
                // singkatan (sel yang benar-benar menampung kolom angka). ?>
          <tr>
            <th rowspan="2" class="ctr" style="width:28px">No</th>
            <th rowspan="2" style="min-width:150px">Nama Siswa</th>
            <th rowspan="2" class="ctr" style="width:74px">NIS</th>
            <th colspan="<?= $jf ?>" class="ctr grp-fmt">Formatif<?= $jf > 1 ? ' 1-' . $jf : '' ?></th>
            <th colspan="<?= $js ?>" class="ctr grp-smt">Sumatif Lingkup Materi<?= $js > 1 ? ' 1-' . $js : '' ?></th>
            <th rowspan="2" class="ctr grp-asas" style="width:44px">ASAS</th>
            <th rowspan="2" class="ctr grp-na" style="width:56px">Nilai Akhir</th>
            <th rowspan="2" class="ctr" style="width:52px">Predikat</th>
          </tr>
          <tr class="sub">
            <?php for ($k = 1; $k <= $jf; $k++): ?><th class="ctr grp-fmt<?= $k === 1 ? ' awal-grup' : '' ?>" style="width:34px">F<?= $k ?></th><?php endfor; ?>
            <?php for ($k = 1; $k <= $js; $k++): ?><th class="ctr grp-smt<?= $k === 1 ? ' awal-grup' : '' ?>" style="width:34px">S<?= $k ?></th><?php endfor; ?>
          </tr>
        </thead>
        <tbody>
        <?php $no = 0; foreach ($siswa as $s):
            $no++;
            $d = $nilai[(int) $s['id']] ?? ['formatif' => [], 'sumatif' => [], 'asas' => null];
            $sumArr = [];
        ?>
          <tr>
            <td class="ctr"><?= $no ?></td>
            <td><?= e((string) $s['nama']) ?></td>
            <td class="ctr"><?= e((string) ($s['nis'] ?: '-')) ?></td>
            <?php for ($k = 1; $k <= $jf; $k++): $v = $d['formatif'][$k] ?? null; if ($v !== null) { $kolF[$k][] = (float) $v; } ?>
              <td class="ctr"><?= $v === null ? '-' : e(r1($v)) ?></td>
            <?php endfor; ?>
            <?php for ($k = 1; $k <= $js; $k++): $v = $d['sumatif'][$k] ?? null; $sumArr[$k] = $v; if ($v !== null) { $kolS[$k][] = (float) $v; } ?>
              <td class="ctr"><?= $v === null ? '-' : e(r1($v)) ?></td>
            <?php endfor; ?>
            <?php $asas = $d['asas']; if ($asas !== null) { $kolA[] = (float) $asas; }
                  $akhir = nilai_akhir($sumArr, $asas); if ($akhir !== null) { $kolNA[] = $akhir; } ?>
            <td class="ctr"><?= $asas === null ? '-' : e(r1($asas)) ?></td>
            <td class="ctr"><b><?= $akhir === null ? '-' : e(r1($akhir)) ?></b></td>
            <td class="ctr"><?= $akhir === null ? '-' : e(predikat_dari($akhir)) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($no === 0): ?>
          <tr><td colspan="<?= 5 + $jf + $js ?>" class="ctr">Belum ada siswa pada kelas ini.</td></tr>
        <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="3" class="ctr">Rata-rata kelas</th>
            <?php for ($k = 1; $k <= $jf; $k++): $r = rata($kolF[$k]); ?><th class="ctr"><?= $r === null ? '-' : e(r1($r)) ?></th><?php endfor; ?>
            <?php for ($k = 1; $k <= $js; $k++): $r = rata($kolS[$k]); ?><th class="ctr"><?= $r === null ? '-' : e(r1($r)) ?></th><?php endfor; ?>
            <th class="ctr"><?= ($r = rata($kolA)) === null ? '-' : e(r1($r)) ?></th>
            <th class="ctr"><?= ($r = rata($kolNA)) === null ? '-' : e(r1($r)) ?></th>
            <th class="ctr"><?= $r === null ? '-' : e(predikat_dari($r)) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="ket">
      Keterangan: F = Nilai Formatif (penilaian proses, tidak dihitung ke nilai akhir) &middot;
      S = Sumatif Lingkup Materi (bobot <?= e(r0(bobot_sumatif())) ?>%) &middot; ASAS = Sumatif Akhir Semester (bobot <?= e(r0($bobotAsas)) ?>%).<br>
      Nilai Akhir = (rata-rata Sumatif Lingkup Materi &times; <?= e(r0(bobot_sumatif())) ?>%) + (ASAS &times; <?= e(r0($bobotAsas)) ?>%).
      Predikat: A &ge; <?= e(r0(thresholds()['batas_a'])) ?>, B &ge; <?= e(r0(thresholds()['batas_b'])) ?>, C &ge; <?= e(r0(thresholds()['batas_c'])) ?>, D di bawahnya.
    </div>

    <div class="sheet-foot">
      <?php ttd_blok('Guru Mata Pelajaran', $guru ? (string) $guru['nama'] : '-', '', 'sheet-sign sheet-sign-kiri'); ?>
      <?php ttd_blok('Mengetahui, ' . jabatan_kepala(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kanan'); ?>
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
