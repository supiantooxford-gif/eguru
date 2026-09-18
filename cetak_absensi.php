<?php
/**
 * Lembar cetak Rekap Kehadiran Siswa (A4 portrait).
 * Periode & filter mengikuti halaman Daftar Hadir: kelas, mapel (opsional), rentang tanggal.
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
    redirect('absensi.php');
}

$kelasId = (int) ($_GET['kelas'] ?? 0);
$mapelId = (int) ($_GET['mapel'] ?? 0);
$kelas = kelas_by_id($kelasId);
if (!$kelas) {
    flash('Pilih kelas yang akan dicetak rekapnya.', 'warn');
    redirect('absensi.php');
}
// Penjaga cakupan dijalankan SETELAH kelas dipastikan ada, supaya permintaan tanpa
// ?kelas= memberi arahan ramah alih-alih "Kelas itu tidak Anda ampu".
if (!boleh_kelas($kelasId)) {
    tolak403('Kelas itu tidak Anda ampu.');
}
if ($mapelId > 0 && !boleh_mapel($mapelId)) {
    tolak403('Mata pelajaran itu tidak Anda ampu.');
}

$fDari = tgl_ok($_GET['dari'] ?? null);
$fSampai = tgl_ok($_GET['sampai'] ?? null);
if ($fDari !== null && $fSampai !== null && $fDari > $fSampai) {
    [$fDari, $fSampai] = [$fSampai, $fDari];
}

$siswa = siswa_kelas($kelasId, true);
$rekap = absensi_rekap($kelasId, $mapelId, (int) $ta['id'], (int) $sem['id'], $fDari, $fSampai);
$guru = $mapelId > 0 ? guru_pengampu($kelasId, $mapelId) : null;

$periode = ($fDari || $fSampai)
    ? (($fDari ? tanggal_id($fDari) : 'awal') . ' s/d ' . ($fSampai ? tanggal_id($fSampai) : 'akhir'))
    : 'Seluruh periode semester ini';

$jmlPertemuan = (int) val('SELECT COUNT(*) FROM absensi WHERE sekolah_id = ' . sekolah_id() . ' AND kelas_id = ? AND ta_id = ? AND semester_id = ?'
    . ($mapelId > 0 ? ' AND mapel_id = ?' : '')
    . ($fDari !== null ? ' AND tanggal >= ?' : '')
    . ($fSampai !== null ? ' AND tanggal <= ?' : ''),
    array_merge([$kelasId, (int) $ta['id'], (int) $sem['id']], $mapelId > 0 ? [$mapelId] : [], $fDari !== null ? [$fDari] : [], $fSampai !== null ? [$fSampai] : []), 0);

$autoPrint = isset($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rekap Kehadiran <?= e(kelas_nama($kelasId)) ?> — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:900px;margin:0 auto}
  .toolbar{max-width:900px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-foot{justify-content:space-between}
  @media print{body{background:#fff;padding:0}.toolbar,.no-print{display:none !important}.print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}}
  @page{size:A4 portrait;margin:13mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="absensi.php?<?= e(http_build_query(array_filter(['kelas' => $kelasId, 'mapel' => $mapelId, 'dari' => $fDari, 'sampai' => $fSampai, 'ta' => (int) $ta['id'], 'sem' => (int) $sem['id']]))) ?>">Kembali</a>
  <a class="btn btn-primary" href="cetak_absensi.php?<?= e(http_build_query(['kelas' => $kelasId, 'mapel' => $mapelId, 'dari' => $fDari, 'sampai' => $fSampai, 'ta' => (int) $ta['id'], 'sem' => (int) $sem['id'], 'auto' => 1])) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Rekap Kehadiran Siswa', ['Daftar Hadir ' . istilah_sekolah('Sekolah')]); ?>

    <?php sheet_meta([
        'Kelas'          => (string) $kelas['nama'],
        'Mata Pelajaran' => $mapelId > 0 ? mapel_nama($mapelId) : 'Semua mata pelajaran',
        'Tahun Ajaran'   => ta_nama($ta) . ' — Semester ' . sem_nama($sem),
        'Periode'        => $periode,
        'Guru Pengampu'  => $mapelId > 0 ? ($guru ? (string) $guru['nama'] : '-') : '-',
        'Jumlah Pertemuan' => (string) $jmlPertemuan,
    ]); ?>

    <table class="sheet padat">
      <thead>
        <tr>
          <th class="ctr" style="width:30px">No</th>
          <th style="width:70px">NIS</th>
          <th>Nama Siswa</th>
          <th class="ctr" style="width:42px">H</th>
          <th class="ctr" style="width:42px">S</th>
          <th class="ctr" style="width:42px">I</th>
          <th class="ctr" style="width:42px">A</th>
          <th class="ctr" style="width:60px">Jumlah</th>
          <th class="ctr" style="width:60px">% Hadir</th>
        </tr>
      </thead>
      <tbody>
      <?php $n = 0; $t = ['h' => 0, 's' => 0, 'i' => 0, 'a' => 0, 'total' => 0];
      foreach ($siswa as $s):
          $n++;
          $r = $rekap[(int) $s['id']] ?? ['h' => 0, 's' => 0, 'i' => 0, 'a' => 0, 'total' => 0, 'persen' => null];
          $t['h'] += (int) $r['h']; $t['s'] += (int) $r['s']; $t['i'] += (int) $r['i'];
          $t['a'] += (int) $r['a']; $t['total'] += (int) $r['total'];
      ?>
        <tr>
          <td class="ctr"><?= $n ?></td>
          <td><?= e((string) ($s['nis'] ?: '-')) ?></td>
          <td><?= e((string) $s['nama']) ?></td>
          <td class="ctr"><?= (int) $r['h'] ?></td>
          <td class="ctr"><?= (int) $r['s'] ?></td>
          <td class="ctr"><?= (int) $r['i'] ?></td>
          <td class="ctr"><?= (int) $r['a'] ?></td>
          <td class="ctr"><?= (int) $r['total'] ?></td>
          <td class="ctr"><?= $r['persen'] === null ? '-' : e(r1($r['persen'])) . '%' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($n === 0): ?>
        <tr><td colspan="9" class="ctr">Belum ada siswa pada kelas ini.</td></tr>
      <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="3" class="ctr">Jumlah</th>
          <th class="ctr"><?= (int) $t['h'] ?></th>
          <th class="ctr"><?= (int) $t['s'] ?></th>
          <th class="ctr"><?= (int) $t['i'] ?></th>
          <th class="ctr"><?= (int) $t['a'] ?></th>
          <th class="ctr"><?= (int) $t['total'] ?></th>
          <th class="ctr"><?= $t['total'] > 0 ? e(r1($t['h'] / $t['total'] * 100)) . '%' : '-' ?></th>
        </tr>
      </tfoot>
    </table>

    <p class="sm" style="margin-top:12px;color:#635F82">
      Keterangan: H = Hadir, S = Sakit, I = Izin, A = Alpa. Persentase kehadiran dihitung dari jumlah kehadiran dibagi seluruh catatan pertemuan.
    </p>

    <div class="sheet-foot">
      <?php ttd_blok('Guru Mata Pelajaran', $guru ? (string) $guru['nama'] : '-', '', 'sheet-sign sheet-sign-kiri'); ?>
      <?php ttd_blok('Mengetahui, ' . jabatan_kepala() . '', kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kanan'); ?>
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
