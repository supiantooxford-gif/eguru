<?php
/**
 * Lembar cetak LAPORAN DAFTAR CATATAN PELANGGARAN SISWA (A4 landscape).
 *
 * Filter mengikuti halaman Pelanggaran Siswa (dulu "Catatan Pelanggaran Siswa"): kelas,
 * status, jenis, dan kata kunci —
 * sehingga lembar yang tercetak sama dengan yang tampil di layar.
 *
 * Posisi tanda tangan: kepala sekolah/madrasah di KIRI, guru di KANAN (permintaan pemilik).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$u = require_sekolah(); // super admin tanpa konteks dialihkan ke Kelola Sekolah
$jenisList = kasus_jenis();
$statusList = kasus_status();

// ---------------------------------------------------------------- filter (sama dengan kasus.php)
$fKelas = (int) ($_GET['kelas'] ?? 0);
if ($fKelas > 0 && !boleh_kelas($fKelas)) {
    tolak403('Kelas itu tidak Anda ampu.');
}
$fStatus = array_key_exists((string) ($_GET['status'] ?? ''), $statusList) ? (string) $_GET['status'] : '';
$fJenis = array_key_exists((string) ($_GET['jenis'] ?? ''), $jenisList) ? (string) $_GET['jenis'] : '';
// Filter mata pelajaran (permintaan pemilik) — mengikuti filter pada halaman Pelanggaran Siswa.
$fMapel = (int) ($_GET['mapel'] ?? 0);
if ($fMapel > 0 && (!mapel_by_id($fMapel) || !boleh_mapel($fMapel))) {
    tolak403('Mata pelajaran itu tidak Anda ampu.');
}
$cari = trim((string) ($_GET['cari'] ?? ''));
// Filter satu siswa (datang dari "Lihat Pelanggaran" pada Laporan Rekap Pelanggaran Siswa).
$fSiswa = (int) ($_GET['siswa'] ?? 0);
$siswaCetak = null;
if ($fSiswa > 0) {
    $siswaCetak = one('SELECT s.*, k.nama AS kelas_nama FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
                       WHERE s.id = ? AND s.sekolah_id = ?', [$fSiswa, sekolah_id()]);
    if (!$siswaCetak) {
        $fSiswa = 0;
    } elseif (is_guru() && !in_array((int) ($siswaCetak['kelas_id'] ?? 0), array_map('intval', array_column(kelas_diampu(), 'id')), true)) {
        tolak403('Siswa itu di luar kelas yang Anda ampu.');
    }
}
$fDari = tgl_ok($_GET['dari'] ?? null);
$fSampai = tgl_ok($_GET['sampai'] ?? null);
if ($fDari !== null && $fSampai !== null && $fDari > $fSampai) {
    [$fDari, $fSampai] = [$fSampai, $fDari];
}

$w = ['ks.sekolah_id = ?'];
$p = [sekolah_id()];
if (is_guru()) {
    // Sama seperti halaman kasus: guru hanya mencetak catatan pelanggaran yang IA buat sendiri.
    $w[] = 'ks.guru_id = ?';
    $p[] = guru_id_saya();
    $kelasGuru = array_map('intval', array_column(kelas_diampu(), 'id'));
    if (!$kelasGuru) {
        $w[] = '1 = 0';
    } else {
        $w[] = 'ks.kelas_id IN (' . implode(',', array_fill(0, count($kelasGuru), '?')) . ')';
        array_push($p, ...$kelasGuru);
    }
}
if ($fSiswa > 0) {
    $w[] = 'ks.siswa_id = ?';
    $p[] = $fSiswa;
}
if ($fKelas > 0) {
    $w[] = 'ks.kelas_id = ?';
    $p[] = $fKelas;
}
if ($fStatus !== '') {
    $w[] = 'ks.status = ?';
    $p[] = $fStatus;
}
if ($fJenis !== '') {
    $w[] = 'ks.jenis = ?';
    $p[] = $fJenis;
}
if ($fMapel > 0) {
    $w[] = 'ks.mapel_id = ?';
    $p[] = $fMapel;
}
if ($cari !== '') {
    $w[] = '(s.nama LIKE ? OR ks.judul LIKE ? OR ks.deskripsi LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($p, $like, $like, $like);
}
if ($fDari !== null) {
    $w[] = 'ks.tanggal >= ?';
    $p[] = $fDari;
}
if ($fSampai !== null) {
    $w[] = 'ks.tanggal <= ?';
    $p[] = $fSampai;
}
$where = ' WHERE ' . implode(' AND ', $w);

$rows = all('SELECT ks.*, s.nama AS siswa_nama, s.nis, k.nama AS kelas_nama, g.nama AS guru_nama,
                       m.nama AS mapel_nama
    FROM kasus ks LEFT JOIN siswa s ON s.id = ks.siswa_id
    LEFT JOIN kelas k ON k.id = ks.kelas_id LEFT JOIN guru g ON g.id = ks.guru_id
    LEFT JOIN mapel m ON m.id = ks.mapel_id'
    . $where . ' ORDER BY ks.tanggal, ks.id', $p);

if (!$rows) {
    flash('Belum ada pelanggaran siswa untuk dicetak pada filter yang dipilih.', 'warn');
    redirect('kasus.php');
}

// ---------------------------------------------------------------- identitas lembar
$periode = ($fDari || $fSampai)
    ? (($fDari ? tanggal_id($fDari) : 'awal') . ' s/d ' . ($fSampai ? tanggal_id($fSampai) : 'akhir'))
    : 'Seluruh catatan yang tercatat';

$ringkas = ['baru' => 0, 'proses' => 0, 'selesai' => 0];
foreach ($rows as $r) {
    $st = (string) $r['status'];
    if (isset($ringkas[$st])) {
        $ringkas[$st]++;
    }
}

// Guru penanda tangan: akun guru yang sedang masuk, atau wali kelas yang difilter, atau guru pencatat.
$guruCetak = '';
if (is_guru() && guru_saya()) {
    $guruCetak = (string) guru_saya()['nama'];
} elseif ($fKelas > 0) {
    $k = kelas_by_id($fKelas);
    if ($k && (int) ($k['wali_id'] ?? 0) > 0) {
        $guruCetak = guru_nama((int) $k['wali_id']);
    }
}
if ($guruCetak === '') {
    foreach ($rows as $r) {
        if (trim((string) ($r['guru_nama'] ?? '')) !== '') {
            $guruCetak = (string) $r['guru_nama'];
            break;
        }
    }
}
if ($guruCetak === '') {
    $guruCetak = '-';
}

$autoPrint = isset($_GET['auto']);
$qPrint = array_filter([
    'kelas' => $fKelas ?: null, 'status' => $fStatus ?: null, 'jenis' => $fJenis ?: null,
    'mapel' => $fMapel ?: null, 'cari' => $cari ?: null, 'dari' => $fDari, 'sampai' => $fSampai,
]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pelanggaran Siswa — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:1160px;margin:0 auto}
  .toolbar{max-width:1160px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table.sheet.kasus th,table.sheet.kasus td{padding:5px 6px}
  .sheet-foot{justify-content:space-between}
  .ket-print{font-size:.72rem;color:#635F82;margin-top:10px;line-height:1.5}
  /* Tata letak layar sempit HANYA untuk layar (media "screen"): kertas A4 portrait hanya
     selebar ±695px, jadi "@media (max-width:900px)" ikut aktif saat MENCETAK dan membuat tanda
     tangan menumpuk atas-bawah — padahal harus tetap kiri-kanan. */
  @media screen and (max-width:900px){
    .print-sheet{padding:18px 14px}
    table.sheet.kasus{min-width:900px}
    .sheet-meta{grid-template-columns:minmax(0,1fr)}
    .sheet-foot{flex-direction:column;gap:20px}
  }
  @media print{
    /* PENTING: tema menetapkan body{min-height:100vh}. Saat MENCETAK, 100vh = tinggi kertas,
       sehingga tinggi dokumen menjadi setinggi halaman dan peramban mencetak halaman KEDUA yang
       hampir kosong. Karena itu min-height dimatikan pada media cetak. */
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .sheet-wrap{max-width:none}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
    .sheet-scroll{overflow:visible}
    table.sheet.kasus{min-width:0}
  }
  /* Saat dicetak, dua blok tanda tangan WAJIB berdampingan (kiri & kanan). */
  @media print{
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap;justify-content:space-between}
    .sheet-foot.dua-ttd .sheet-sign{flex:1 1 0;min-width:0}
  }
  @page{size:A4 landscape;margin:12mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="kasus.php?<?= e(http_build_query($qPrint)) ?>">Kembali</a>
  <a class="btn btn-primary" href="cetak_kasus.php?<?= e(http_build_query(array_filter($qPrint + ['auto' => 1]))) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Laporan Pelanggaran Siswa', ['Administrasi Pembinaan Siswa']); ?>

    <?php sheet_meta(array_filter([
        'Siswa'          => $siswaCetak ? (string) $siswaCetak['nama'] . ' (' . (string) ($siswaCetak['kelas_nama'] ?: 'tanpa kelas') . ')' : null,
        'Periode'        => $periode,
        'Kelas'          => $fKelas > 0 ? kelas_nama($fKelas) : 'Semua kelas',
        'Jenis'          => $fJenis !== '' ? $jenisList[$fJenis] : 'Semua jenis',
        'Mata Pelajaran' => $fMapel > 0 ? mapel_nama($fMapel) : 'Semua mata pelajaran',
        'Status'         => $fStatus !== '' ? $statusList[$fStatus] : 'Semua status',
        'Jumlah Catatan' => (string) count($rows),
        'Rekap Status'   => 'Baru ' . $ringkas['baru'] . ', Dalam Penanganan ' . $ringkas['proses'] . ', Selesai ' . $ringkas['selesai'],
    ])); ?>

    <div class="sheet-scroll">
      <table class="sheet kasus padat">
        <thead>
          <tr>
            <th class="ctr" style="width:26px">No</th>
            <th style="width:10%">Hari / Tanggal</th>
            <th style="width:15%">Nama Siswa</th>
            <th class="ctr" style="width:7%">Kelas</th>
            <th style="width:11%">Jenis</th>
            <th style="width:13%">Mata Pelajaran</th>
            <th style="width:25%">Uraian Pelanggaran &amp; Tindakan</th>
            <th class="ctr" style="width:11%">Status</th>
            <th style="width:11%">Dicatat oleh</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($rows as $r): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><?= e(tanggal_id((string) $r['tanggal'], true)) ?></td>
            <td>
              <?= e((string) ($r['siswa_nama'] ?: '-')) ?>
              <?php if (trim((string) ($r['nis'] ?? '')) !== ''): ?><br><span style="font-size:.7rem">NIS <?= e((string) $r['nis']) ?></span><?php endif; ?>
            </td>
            <td class="ctr"><?= e((string) ($r['kelas_nama'] ?: '-')) ?></td>
            <td><?= e($jenisList[(string) $r['jenis']] ?? 'Lainnya') ?></td>
            <td><?= trim((string) ($r['mapel_nama'] ?? '')) !== '' ? e((string) $r['mapel_nama']) : '-' ?></td>
            <td>
              <?php if (trim((string) ($r['judul'] ?? '')) !== ''): ?><b><?= e((string) $r['judul']) ?></b><br><?php endif; ?>
              <?= e((string) ($r['deskripsi'] ?: '-')) ?>
              <?php if (trim((string) ($r['tindakan'] ?? '')) !== ''): ?><br><i>Tindakan:</i> <?= e((string) $r['tindakan']) ?><?php endif; ?>
            </td>
            <td class="ctr"><?= e($statusList[(string) $r['status']] ?? '-') ?></td>
            <td><?= e((string) ($r['guru_nama'] ?: '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="ket-print">
      Keterangan: daftar ini memuat catatan pelanggaran/peristiwa siswa beserta tindakan yang sudah
      dilakukan dan status penanganannya. Jenis catatan meliputi: <?= e(implode(', ', array_values($jenisList))) ?>.
      Status: Baru, Dalam Penanganan, dan Selesai.
    </div>

    <!-- Posisi tanda tangan: kepala sekolah/madrasah di KIRI, guru di KANAN (permintaan pemilik). -->
    <div class="sheet-foot dua-ttd">
      <?php ttd_blok(ttd_mengetahui(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kiri'); ?>
      <?php ttd_blok('Guru Pembina / Pencatat', (string) $guruCetak, '', 'sheet-sign sheet-sign-kanan', true); ?>
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
