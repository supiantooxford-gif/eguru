<?php
/**
 * Lembar cetak DAFTAR DATA SISWA (A4 landscape).
 *
 * Akses: Super Admin (dalam konteks sekolah) & Admin Operator — sama dengan tombol cetaknya
 * di siswa.php. Konteks sekolah default TIDAK membatasi per kelas: seluruh siswa
 * sekolah/madrasah yang sedang dikelola ikut tercetak (kecuali filter kelas dipilih),
 * termasuk siswa berstatus LULUS bila filter status diarahkan ke sana.
 * Filter mengikuti halaman Data Siswa: kelas, status siswa, aktif/niraktif, dan kata kunci.
 *
 * Tanda tangan: Kepala Sekolah/Madrasah di KIRI, Wali Kelas (bila kelas difilter) di KANAN.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$u = require_master(); // super admin tanpa konteks dialihkan ke Kelola Sekolah; kepala & guru 403

// ---------------------------------------------------------------- filter (sama dengan siswa.php)
$fKelas = (int) ($_GET['kelas'] ?? 0);
$cari = trim((string) ($_GET['cari'] ?? ''));
$fStatus = (string) ($_GET['status'] ?? '');
$fLulus = in_array((string) ($_GET['status_siswa'] ?? ''), ['aktif', 'lulus'], true) ? (string) $_GET['status_siswa'] : '';

$w = ['s.sekolah_id = ?'];
$p = [sekolah_id()];
if ($fKelas > 0) {
    if (!kelas_by_id($fKelas)) {
        tolak403('Kelas itu bukan milik sekolah/madrasah yang sedang dikelola.');
    }
    $w[] = 's.kelas_id = ?';
    $p[] = $fKelas;
}
if (in_array($fStatus, ['1', '0'], true)) {
    $w[] = 's.aktif = ' . (int) $fStatus;
}
if ($fLulus === 'lulus') {
    $w[] = "s.status = 'lulus'";
} elseif ($fLulus === 'aktif') {
    $w[] = "(s.status = 'aktif' OR s.status IS NULL)";
}
if ($cari !== '') {
    $w[] = '(s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR s.nama_wali LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($p, $like, $like, $like, $like);
}
$where = ' WHERE ' . implode(' AND ', $w);

$rows = all('SELECT s.*, k.nama AS kelas_nama, k.urutan AS kelas_urutan
    FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id' . $where . '
    ORDER BY k.urutan, k.nama, s.nama', $p);

if (!$rows) {
    flash('Belum ada data siswa untuk dicetak pada filter yang dipilih.', 'warn');
    redirect('siswa.php');
}

// ---------------------------------------------------------------- rekap identitas lembar
$laki = 0;
$perempuan = 0;
$tanpaKelas = 0;
$kelasTercakup = [];
foreach ($rows as $s) {
    if ((string) $s['jenis_kelamin'] === 'L') {
        $laki++;
    } elseif ((string) $s['jenis_kelamin'] === 'P') {
        $perempuan++;
    }
    if ((int) ($s['kelas_id'] ?? 0) <= 0) {
        $tanpaKelas++;
        continue;
    }
    $kelasTercakup[(string) ($s['kelas_nama'] ?? '')] = true;
}

$labelStatus = 'Semua status';
if ($fLulus === 'lulus') {
    $labelStatus = 'Lulus';
} elseif ($fLulus === 'aktif') {
    $labelStatus = 'Aktif (belum lulus)';
} elseif (in_array($fStatus, ['1', '0'], true)) {
    $labelStatus = (int) $fStatus === 1 ? 'Aktif' : 'Nonaktif';
}

$rekap = 'L ' . $laki . ' / P ' . $perempuan;
if ($tanpaKelas > 0) {
    $rekap .= ' / belum berkelas ' . $tanpaKelas;
}

// Wali kelas penanda tangan (bila lembar dibatasi ke satu kelas).
$wali = '-';
if ($fKelas > 0) {
    $k = kelas_by_id($fKelas);
    if ($k && (int) ($k['wali_id'] ?? 0) > 0) {
        $wali = guru_nama((int) $k['wali_id']);
    }
}

$qPrint = array_filter([
    'cari' => $cari !== '' ? $cari : null,
    'kelas' => $fKelas > 0 ? $fKelas : null,
    'status' => $fStatus !== '' ? $fStatus : null,
    'status_siswa' => $fLulus !== '' ? $fLulus : null,
]);
$autoPrint = isset($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Siswa — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:1160px;margin:0 auto}
  .toolbar{max-width:1160px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table.sheet.siswa th,table.sheet.siswa td{padding:5px 6px}
  .ket-print{font-size:.72rem;color:#635F82;margin-top:10px;line-height:1.5}
  @media (max-width:900px){
    .print-sheet{padding:18px 14px}
    table.sheet.siswa{min-width:900px}
    .sheet-meta{grid-template-columns:minmax(0,1fr)}
    .sheet-foot{flex-direction:column;gap:20px}
  }
  @media print{
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .sheet-wrap{max-width:none}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
    .sheet-scroll{overflow:visible}
    table.sheet.siswa{min-width:0}
  }
  @page{size:A4 landscape;margin:12mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="siswa.php?<?= e(http_build_query($qPrint)) ?>">Kembali</a>
  <a class="btn btn-primary" href="cetak_siswa.php?<?= e(http_build_query(array_filter($qPrint + ['auto' => 1]))) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Daftar Data Siswa', ['Administrasi Kesiswaan ' . istilah_sekolah('Sekolah')]); ?>

    <?php sheet_meta([
        'Jumlah Siswa'   => (string) count($rows),
        'Laki-laki / Perempuan' => $rekap,
        'Kelas'          => $fKelas > 0 ? kelas_nama($fKelas) : (count($kelasTercakup) > 0 ? 'Semua kelas (' . count($kelasTercakup) . ' kelas)' : 'Semua kelas'),
        'Status Siswa'   => $labelStatus,
        'Kata Kunci'     => $cari !== '' ? $cari : 'Semua data',
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet siswa padat">
        <thead>
          <tr>
            <th class="ctr" style="width:26px">No</th>
            <th style="width:8%">NIS</th>
            <th style="width:9%">NISN</th>
            <th style="width:20%">Nama Siswa</th>
            <th class="ctr" style="width:5%">JK</th>
            <th style="width:8%">Kelas</th>
            <th style="width:9%">Tanggal Lahir</th>
            <th style="width:14%">Nama Wali</th>
            <th style="width:12%">No. HP / Alamat</th>
            <th class="ctr" style="width:8%">Status</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($rows as $s): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><?= e((string) ($s['nis'] ?: '-')) ?></td>
            <td><?= e((string) ($s['nisn'] ?: '-')) ?></td>
            <td><b><?= e((string) $s['nama']) ?></b></td>
            <td class="ctr"><?= e((string) ($s['jenis_kelamin'] ?: '-')) ?></td>
            <td><?= e((string) ($s['kelas_nama'] ?: 'belum berkelas')) ?></td>
            <td><?= e((string) ($s['tanggal_lahir'] ? tanggal_id((string) $s['tanggal_lahir']) : '-')) ?></td>
            <td><?= e((string) ($s['nama_wali'] ?: '-')) ?></td>
            <td>
              <?= e((string) ($s['telepon'] ?: '-')) ?>
              <?php if (trim((string) ($s['alamat'] ?? '')) !== ''): ?><br><span style="font-size:.7rem"><?= e((string) $s['alamat']) ?></span><?php endif; ?>
            </td>
            <td class="ctr"><?= (string) $s['status'] === 'lulus' ? 'Lulus' . ($s['tanggal_status'] ? ' (' . e(tanggal_id((string) $s['tanggal_status'])) . ')' : '') : ((int) $s['aktif'] === 1 ? 'Aktif' : 'Nonaktif') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="4" class="ctr">Jumlah</th>
            <th class="ctr"><?= $laki ?> L / <?= $perempuan ?> P</th>
            <th colspan="5" class="ctr"><?= count($rows) ?> siswa</th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="ket-print">
      Keterangan: daftar ini memuat data <?= e(istilah_sekolah('siswa sekolah')) ?> beserta kelas, wali,
      dan kontaknya. Status <b>Lulus</b> menandakan siswa sudah dinyatakan lulus (tidak lagi muncul pada
      daftar hadir &amp; penilaian), sedangkan <b>Nonaktif</b> berarti data administrasinya dinonaktifkan.
    </div>

    <!-- Tanda tangan: kepala sekolah/madrasah di KIRI, wali kelas (bila lembar dibatasi satu kelas) di KANAN. -->
    <div class="sheet-foot dua-ttd">
      <?php ttd_blok('Mengetahui, ' . jabatan_kepala(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kiri'); ?>
      <?php ttd_blok('Wali Kelas', (string) $wali, '', 'sheet-sign sheet-sign-kanan'); ?>
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
