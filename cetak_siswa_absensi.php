<?php
/**
 * LEMBAR CETAK — REKAPAN DAFTAR HADIR SISWA (A4 portrait) untuk akun siswa (v1.61.0).
 *
 * Permintaan pemilik: rekapan daftar hadir **per mata pelajaran** (ringkasan + rincian tiap
 * pertemuan) dan **di kelas** (absensi harian dari wali kelas). Keduanya dicetak pada lembar ini;
 * `?bagian=mapel|kelas` dapat dipakai untuk mencetak salah satunya saja.
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

$bagian = (string) ($_GET['bagian'] ?? '');
if (!in_array($bagian, ['mapel', 'kelas'], true)) {
    $bagian = '';
}
$tampilMapel = $bagian !== 'kelas';
$tampilKelas = $bagian !== 'mapel';

$mapel = $tampilMapel ? siswa_absen_mapel((int) $sw['id'], $taId, $semId) : ['per_mapel' => [], 'semua' => []];
$kelas = $tampilKelas ? siswa_absen_kelas((int) $sw['id'], $taId, $semId) : ['baris' => [], 'semua' => [], 'ringkas' => siswa_absen_ringkas([])];
$ringkasGabung = siswa_absen_ringkas(array_merge($mapel['semua'], $kelas['semua']));

$stTeks = static function (string $st): string {
    $st = strtoupper($st);
    $label = ['H' => 'Hadir', 'S' => 'Sakit', 'I' => 'Izin', 'A' => 'Alpa'];
    return $st . ' (' . ($label[$st] ?? '-') . ')';
};
$autoPrint = isset($_GET['auto']);
$qs = array_filter(['ta' => $taId ?: null, 'sem' => $semId ?: null, 'bagian' => $bagian ?: null]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rekap Daftar Hadir — <?= e((string) $sw['nama']) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:900px;margin:0 auto}
  .toolbar{max-width:900px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-foot{justify-content:flex-end}
  h3.sub-cetak{font-size:.88rem;color:var(--hijau-tua);margin:16px 0 7px}
  table.sheet.padat th,table.sheet.padat td{padding:4px 6px}
  @media print{
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
    h3.sub-cetak{margin-top:12px}
  }
  @page{size:A4 portrait;margin:13mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="siswa_absensi.php<?= e($taId ? '?ta=' . $taId . '&sem=' . $semId : '') ?>">Kembali</a>
  <a class="btn btn-ghost" href="cetak_siswa_absensi.php?<?= e(http_build_query(array_merge($qs, ['bagian' => 'mapel']))) ?>">Hanya per Mapel</a>
  <a class="btn btn-ghost" href="cetak_siswa_absensi.php?<?= e(http_build_query(array_merge($qs, ['bagian' => 'kelas']))) ?>">Hanya Kelas</a>
  <a class="btn btn-primary" href="cetak_siswa_absensi.php?<?= e(http_build_query(array_merge($qs, ['auto' => 1]))) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Rekapan Daftar Hadir Siswa', ['Daftar Hadir Siswa']); ?>

    <?php sheet_meta([
        'Nama Siswa'   => (string) $sw['nama'],
        'NISN'         => (string) ($sw['nisn'] !== '' ? $sw['nisn'] : '-'),
        'NIS'          => (string) ($sw['nis'] !== '' ? $sw['nis'] : '-'),
        'Kelas'        => (string) ($sw['kelas_nama'] !== null ? $sw['kelas_nama'] : '-'),
        'Periode'      => siswa_periode_teks($ta, $sem),
        'Total Catatan' => (int) $ringkasGabung['total'] . ' pertemuan',
        'Kehadiran'    => $ringkasGabung['persen'] === null ? '-' : r1($ringkasGabung['persen']) . '%',
        'Rincian'      => 'H ' . (int) $ringkasGabung['h'] . ', S ' . (int) $ringkasGabung['s']
            . ', I ' . (int) $ringkasGabung['i'] . ', A ' . (int) $ringkasGabung['a'],
    ]); ?>

    <?php if ($tampilMapel): ?>
      <h3 class="sub-cetak">A. Rekapan per Mata Pelajaran</h3>
      <table class="sheet padat">
        <thead>
          <tr>
            <th class="ctr" style="width:28px">No</th>
            <th>Mata Pelajaran</th>
            <th style="width:150px">Guru</th>
            <th class="ctr" style="width:34px">H</th>
            <th class="ctr" style="width:34px">S</th>
            <th class="ctr" style="width:34px">I</th>
            <th class="ctr" style="width:34px">A</th>
            <th class="ctr" style="width:62px">Pertemuan</th>
            <th class="ctr" style="width:62px">% Hadir</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; $t = ['h' => 0, 's' => 0, 'i' => 0, 'a' => 0, 'total' => 0];
        foreach ($mapel['per_mapel'] as $m): $r = $m['ringkas']; $n++;
            $t['h'] += (int) $r['h']; $t['s'] += (int) $r['s']; $t['i'] += (int) $r['i'];
            $t['a'] += (int) $r['a']; $t['total'] += (int) $r['total']; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><?= e((string) $m['mapel_nama']) ?></td>
            <td><?= e((string) ($m['guru_nama'] !== '' ? $m['guru_nama'] : '-')) ?></td>
            <td class="ctr"><?= (int) $r['h'] ?></td>
            <td class="ctr"><?= (int) $r['s'] ?></td>
            <td class="ctr"><?= (int) $r['i'] ?></td>
            <td class="ctr"><?= (int) $r['a'] ?></td>
            <td class="ctr"><?= (int) $r['total'] ?></td>
            <td class="ctr"><?= $r['persen'] === null ? '-' : e(r1($r['persen'])) . '%' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($n === 0): ?>
          <tr><td colspan="9" class="ctr">Belum ada catatan kehadiran dari guru mata pelajaran.</td></tr>
        <?php endif; ?>
        </tbody>
        <?php if ($n > 0): ?>
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
        <?php endif; ?>
      </table>

      <h3 class="sub-cetak">Rincian tiap pertemuan per mata pelajaran</h3>
      <?php if (!$mapel['per_mapel']): ?>
        <p class="sm mut">Belum ada rincian pertemuan.</p>
      <?php else: ?>
        <?php foreach ($mapel['per_mapel'] as $m): ?>
          <p class="sm" style="margin:8px 0 4px"><b><?= e((string) $m['mapel_nama']) ?></b>
            <span class="mut">(<?= (int) $m['ringkas']['total'] ?> pertemuan)</span></p>
          <table class="sheet padat">
            <thead>
              <tr><th class="ctr" style="width:28px">No</th><th class="ctr" style="width:74px">Pertemuan</th>
                <th style="width:110px">Tanggal</th><th class="ctr" style="width:80px">Status</th>
                <th>Materi / Kegiatan</th><th style="width:140px">Keterangan</th></tr>
            </thead>
            <tbody>
            <?php foreach ($m['baris'] as $i => $b): ?>
              <tr>
                <td class="ctr"><?= $i + 1 ?></td>
                <td class="ctr"><?= (int) $b['pertemuan'] ?></td>
                <td><?= e(tanggal_id((string) $b['tanggal'])) ?></td>
                <td class="ctr"><?= e($stTeks((string) $b['status'])) ?></td>
                <td><?= e((string) ($b['materi'] !== '' ? $b['materi'] : '-')) ?></td>
                <td><?= e((string) ($b['ket'] !== '' ? $b['ket'] : '-')) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($tampilKelas): ?>
      <h3 class="sub-cetak"><?= $tampilMapel ? 'B.' : 'A.' ?> Rekapan Daftar Hadir di Kelas (input wali kelas)</h3>
      <table class="sheet padat">
        <thead>
          <tr><th class="ctr" style="width:28px">No</th><th style="width:110px">Tanggal</th>
            <th class="ctr" style="width:80px">Status</th><th style="width:130px">Keterangan</th>
            <th>Catatan Kelas</th><th style="width:150px">Wali Kelas</th></tr>
        </thead>
        <tbody>
        <?php $nk = 0; foreach ($kelas['baris'] as $b): $nk++; ?>
          <tr>
            <td class="ctr"><?= $nk ?></td>
            <td><?= e(tanggal_id((string) $b['tanggal'])) ?></td>
            <td class="ctr"><?= e($stTeks((string) $b['status'])) ?></td>
            <td><?= e((string) ($b['ket'] !== '' ? $b['ket'] : '-')) ?></td>
            <td><?= e((string) ($b['catatan'] !== '' ? $b['catatan'] : '-')) ?></td>
            <td><?= e((string) ($b['wali_nama'] ?: '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($nk === 0): ?>
          <tr><td colspan="6" class="ctr">Belum ada catatan absensi kelas dari wali kelas.</td></tr>
        <?php endif; ?>
        </tbody>
        <?php if ($nk > 0): ?>
          <tfoot>
            <tr>
              <th colspan="2" class="ctr">Jumlah</th>
              <?php // Tanda "middot" TIDAK dipakai pada hasil cetak (permintaan pemilik) → pemisahnya koma. ?>
              <th class="ctr">H <?= (int) $kelas['ringkas']['h'] ?>, S <?= (int) $kelas['ringkas']['s'] ?>,
                I <?= (int) $kelas['ringkas']['i'] ?>, A <?= (int) $kelas['ringkas']['a'] ?></th>
              <th colspan="3" class="ctr">Kehadiran <?= $kelas['ringkas']['persen'] === null ? '-' : e(r1($kelas['ringkas']['persen'])) . '%' ?></th>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    <?php endif; ?>

    <p class="xs mut" style="margin-top:12px">
      Keterangan status: H = Hadir, S = Sakit, I = Izin, A = Alpa. Persentase kehadiran dihitung dari
      jumlah kehadiran dibagi seluruh catatan pertemuan.
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
