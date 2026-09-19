<?php
/**
 * Lembar cetak Jurnal Harian Guru (A4 landscape).
 *
 * Kolom: No, Hari/Tanggal, Kelas, Mata Pelajaran, Jam Ke, Materi/Topik Pembelajaran,
 *        Kehadiran Siswa (S, I, A, B, T), dan Keterangan.
 * Filter mengikuti halaman jurnal, termasuk BULAN dan NAMA GURU (pencarian jurnal guru).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$u = require_sekolah(); // super admin tanpa konteks dialihkan ke Kelola Sekolah
$lihatSemua = !is_guru();
$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];
$kolomKehadiran = jurnal_kehadiran();

$fKelas = (int) ($_GET['kelas'] ?? 0);
if ($fKelas > 0 && !boleh_kelas($fKelas)) {
    tolak403('Kelas itu tidak Anda ampu.');
}
$fMapel = (int) ($_GET['mapel'] ?? 0);
if ($fMapel > 0 && !boleh_mapel($fMapel)) {
    tolak403('Mata pelajaran itu tidak Anda ampu.');
}
$fBulan = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['bulan'] ?? '')) === 1 ? (string) $_GET['bulan'] : '';
$fGuru = $lihatSemua ? (int) ($_GET['guru'] ?? 0) : 0;
if ($fGuru > 0 && !guru_by_id($fGuru)) {
    $fGuru = 0;
}
$fCari = trim((string) ($_GET['cari'] ?? ''));
$fSemuaPeriode = ($_GET['periode'] ?? '') === 'semua';
$fDari = tgl_ok($_GET['dari'] ?? null);
$fSampai = tgl_ok($_GET['sampai'] ?? null);
if ($fDari !== null && $fSampai !== null && $fDari > $fSampai) {
    [$fDari, $fSampai] = [$fSampai, $fDari];
}

$w = ['j.sekolah_id = ?'];
$p = [sekolah_id()];
if ($ta && $sem && !$fSemuaPeriode) {
    $w[] = '(j.ta_id = ? OR j.ta_id IS NULL)';
    $p[] = (int) $ta['id'];
    $w[] = '(j.semester_id = ? OR j.semester_id IS NULL)';
    $p[] = (int) $sem['id'];
}
if (is_guru()) {
    $w[] = 'j.guru_id = ?';
    $p[] = guru_id_saya();
} elseif ($fGuru > 0) {
    $w[] = 'j.guru_id = ?';
    $p[] = $fGuru;
}
if ($fKelas > 0) {
    $w[] = 'j.kelas_id = ?';
    $p[] = $fKelas;
}
if ($fMapel > 0) {
    $w[] = 'j.mapel_id = ?';
    $p[] = $fMapel;
}
if ($fBulan !== '') {
    $w[] = "strftime('%Y-%m', j.tanggal) = ?";
    $p[] = $fBulan;
}
if ($fDari !== null) {
    $w[] = 'j.tanggal >= ?';
    $p[] = $fDari;
}
if ($fSampai !== null) {
    $w[] = 'j.tanggal <= ?';
    $p[] = $fSampai;
}
if ($fCari !== '') {
    $w[] = '(j.materi LIKE ? OR j.keterangan LIKE ? OR g.nama LIKE ?)';
    $like = '%' . $fCari . '%';
    array_push($p, $like, $like, $like);
}
$where = ' WHERE ' . implode(' AND ', $w);

$rows = all('SELECT j.*, k.nama AS kelas_nama, m.nama AS mapel_nama, g.nama AS guru_nama
    FROM jurnal j LEFT JOIN kelas k ON k.id = j.kelas_id LEFT JOIN mapel m ON m.id = j.mapel_id LEFT JOIN guru g ON g.id = j.guru_id'
    . $where . ' ORDER BY j.tanggal, j.id', $p);

if (!$rows) {
    flash('Belum ada catatan jurnal untuk dicetak pada filter yang dipilih.', 'warn');
    redirect('jurnal.php');
}

if ($fBulan !== '') {
    $periode = 'Bulan ' . bulan_id((int) substr($fBulan, 5, 2)) . ' ' . substr($fBulan, 0, 4);
} elseif ($fDari !== null && $fSampai !== null) {
    $periode = tanggal_id($fDari) . ' s/d ' . tanggal_id($fSampai);
} elseif ($fDari !== null) {
    $periode = 'mulai ' . tanggal_id($fDari);
} elseif ($fSampai !== null) {
    $periode = 'sampai ' . tanggal_id($fSampai);
} elseif ($fSemuaPeriode) {
    $periode = 'Seluruh catatan jurnal (semua periode)';
} else {
    $periode = 'Seluruh catatan jurnal pada periode aktif';
}

// Nama guru pada tanda tangan: guru yang dipilih/difilter, guru pengampu, atau '-' (bila campuran).
if (is_guru()) {
    $guruCetak = (string) (guru_saya()['nama'] ?? '-');
} elseif ($fGuru > 0) {
    $guruCetak = guru_nama($fGuru);
} elseif ($fKelas > 0 && $fMapel > 0 && guru_pengampu($fKelas, $fMapel)) {
    $guruCetak = (string) guru_pengampu($fKelas, $fMapel)['nama'];
} else {
    $guruCetak = '-';
}

// Jumlah per kode kehadiran (baris "Jumlah" pada lembar).
$total = array_fill_keys(array_keys($kolomKehadiran), 0);
foreach ($rows as $r) {
    foreach ($total as $kolom => $n) {
        $total[$kolom] += (int) ($r[$kolom] ?? 0);
    }
}

$autoPrint = isset($_GET['auto']);
$qPrint = array_filter([
    'kelas' => $fKelas ?: null, 'mapel' => $fMapel ?: null, 'bulan' => $fBulan ?: null,
    'guru' => $fGuru ?: null, 'cari' => $fCari ?: null, 'periode' => $fSemuaPeriode ? 'semua' : null,
    'dari' => $fDari, 'sampai' => $fSampai,
    'ta' => $ta ? (int) $ta['id'] : null, 'sem' => $sem ? (int) $sem['id'] : null,
]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Jurnal Harian Guru — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:1140px;margin:0 auto}
  .toolbar{max-width:1140px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  .sheet-foot{justify-content:space-between}
  table.sheet.jurnal th,table.sheet.jurnal td{padding:5px 6px}
  table.sheet.jurnal td.kh{text-align:center;font-variant-numeric:tabular-nums}
  .ket-kode{font-size:.72rem;color:#635F82;margin-top:10px;line-height:1.5}
  @media (max-width:900px){
    .print-sheet{padding:18px 14px}
    table.sheet.jurnal{min-width:900px}
    .sheet-meta{grid-template-columns:minmax(0,1fr)}
    .sheet-foot{flex-direction:column;gap:20px}
  }
  @media print{body{background:#fff;padding:0}.toolbar,.no-print{display:none !important}.sheet-wrap{max-width:none}.print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}.sheet-scroll{overflow:visible}table.sheet.jurnal{min-width:0}}
  @page{size:A4 landscape;margin:12mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="jurnal.php?<?= e(http_build_query(array_filter($qPrint))) ?>">Kembali</a>
  <a class="btn btn-primary" href="cetak_jurnal.php?<?= e(http_build_query(array_filter($qPrint + ['auto' => 1]))) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Jurnal Harian Guru', ['Administrasi Pembelajaran']); ?>

    <?php sheet_meta([
        'Periode'        => $periode,
        'Tahun Ajaran'   => ta_nama($ta) . ' — Semester ' . sem_nama($sem),
        'Kelas'          => $fKelas > 0 ? kelas_nama($fKelas) : 'Semua kelas',
        'Mata Pelajaran' => $fMapel > 0 ? mapel_nama($fMapel) : 'Semua mata pelajaran',
        'Guru'           => (string) $guruCetak,
        'Jumlah Catatan' => (string) count($rows),
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet jurnal padat">
        <thead>
          <tr>
            <th class="ctr" rowspan="2" style="width:26px">No</th>
            <th rowspan="2" style="width:13%">Hari / Tanggal</th>
            <th class="ctr" rowspan="2" style="width:7%">Kelas</th>
            <th rowspan="2" style="width:13%">Mata Pelajaran</th>
            <th class="ctr" rowspan="2" style="width:5%">Jam Ke</th>
            <th rowspan="2" style="width:18%">Materi / Topik Pembelajaran</th>
            <th class="ctr" colspan="<?= count($kolomKehadiran) ?>">Kehadiran Siswa</th>
            <th rowspan="2" style="width:17%">Keterangan</th>
          </tr>
          <tr>
            <?php foreach ($kolomKehadiran as [$kode, $label]): ?>
              <th class="ctr" style="width:3%" title="<?= e($label) ?>"><?= e($kode) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($rows as $j): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><?= e(tanggal_id((string) $j['tanggal'], true)) ?></td>
            <td class="ctr"><?= e((string) ($j['kelas_nama'] ?: '-')) ?></td>
            <td><?= e((string) ($j['mapel_nama'] ?: '-')) ?></td>
            <td class="ctr"><?= e((string) ($j['jam_ke'] ?: '-')) ?></td>
            <td><?= e((string) $j['materi']) ?></td>
            <?php foreach (array_keys($kolomKehadiran) as $kolom): $v = (int) ($j[$kolom] ?? 0); ?>
              <td class="kh"><?= $v > 0 ? $v : '-' ?></td>
            <?php endforeach; ?>
            <td><?= e((string) ($j['keterangan'] ?: '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="6" class="ctr">Jumlah</th>
            <?php foreach (array_keys($kolomKehadiran) as $kolom): ?>
              <th class="ctr"><?= (int) $total[$kolom] ?></th>
            <?php endforeach; ?>
            <th></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="ket-kode">
      Keterangan kehadiran siswa:
      <?php $kodeKet = []; foreach ($kolomKehadiran as [$kode, $label]) { $kodeKet[] = '<b>' . e($kode) . '</b> = ' . e($label); } ?>
      <?= implode(', ', $kodeKet) ?>.
    </div>

    <!-- Posisi tanda tangan: kepala sekolah/madrasah di KIRI, guru di KANAN (permintaan pemilik). -->
    <div class="sheet-foot dua-ttd">
      <?php ttd_blok('Mengetahui, ' . jabatan_kepala(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kiri'); ?>
      <?php ttd_blok('Guru Mata Pelajaran', (string) $guruCetak, '', 'sheet-sign sheet-sign-kanan'); ?>
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
