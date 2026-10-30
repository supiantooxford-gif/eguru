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

$u = require_lihat_cetak(); // super admin tanpa konteks dialihkan ke Kelola Sekolah; kepala & guru 403

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
  table.sheet.siswa th,table.sheet.siswa td{padding:3px 4px;font-size:.7rem;line-height:1.2}
  table.sheet.siswa th{font-size:.62rem;letter-spacing:0;padding:4px 3px;vertical-align:middle}
  table.sheet.siswa td.nowrap{white-space:nowrap}
  /* Alamat di bawah nomor HP: di tema .tbl-sub punya margin-top yang membuat baris melar
     — di lembar padat ini cukup kecil dan tanpa jarak atas. */
  table.sheet.siswa .tbl-sub{display:block;font-size:.58rem;line-height:1.15;margin-top:0;color:#41504a}
  /* Lebar kolom dipatok agar 13 kolom muat satu halaman A4 landscape tanpa ada yang melipat. */
  table.sheet.siswa .k-no{width:22px}
  table.sheet.siswa .k-nis{width:44px}
  table.sheet.siswa .k-nisn{width:58px}
  table.sheet.siswa .k-nama{width:16%}
  table.sheet.siswa .k-jk{width:44px}
  table.sheet.siswa .k-tempat{width:8%}
  table.sheet.siswa .k-tgl{width:8%}
  table.sheet.siswa .k-hp{width:11%}
  table.sheet.siswa .k-ayah{width:10%}
  table.sheet.siswa .k-ibu{width:10%}
  table.sheet.siswa .k-wali{width:9%}
  table.sheet.siswa .k-kelas{width:6%}
  table.sheet.siswa .k-status{width:7%}
  .ket-print{font-size:.72rem;color:#635F82;margin-top:10px;line-height:1.5}
  /* Tata letak layar sempit HANYA untuk layar (media "screen"): kertas A4 portrait hanya
     selebar ±695px, jadi "@media (max-width:900px)" ikut aktif saat MENCETAK dan membuat tanda
     tangan menumpuk atas-bawah — padahal harus tetap kiri-kanan. */
  @media screen and (max-width:900px){
    .print-sheet{padding:18px 14px}
    table.sheet.siswa{min-width:900px}
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
    table.sheet.siswa{min-width:0}
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
      <?php // Susunan kolom sesuai permintaan pemilik: NIS, NISN, Nama Lengkap, Jenis Kelamin,
            // Tempat Lahir, Tanggal Lahir, Nomor Telepon (HP), Nama Ayah, Nama Ibu, Wali, Status —
            // ditambah Kelas (dipertahankan) dan Alamat (tetap di bawah Nomor Telepon). ?>
      <table class="sheet siswa padat">
        <thead>
          <tr>
            <th class="ctr k-no">No</th>
            <th class="k-nis">NIS</th>
            <th class="k-nisn">NISN</th>
            <th class="k-nama">Nama Lengkap</th>
            <th class="ctr k-jk">Jenis Kelamin</th>
            <th class="k-tempat">Tempat Lahir</th>
            <th class="k-tgl">Tanggal Lahir</th>
            <th class="k-hp">Nomor Telepon (HP)</th>
            <th class="k-ayah">Nama Ayah</th>
            <th class="k-ibu">Nama Ibu</th>
            <th class="k-wali">Wali</th>
            <th class="k-kelas">Kelas</th>
            <th class="ctr k-status">Status</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($rows as $s): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><?= e((string) ($s['nis'] ?: '-')) ?></td>
            <td><?= e((string) ($s['nisn'] ?: '-')) ?></td>
            <td><b><?= e((string) $s['nama']) ?></b></td>
            <?php // Nilai tetap L/P (kebiasaan daftar siswa) supaya kolomnya satu baris; judul kolomnya
                  // "Jenis Kelamin" sesuai permintaan pemilik. ?>
            <td class="ctr"><?= e((string) ($s['jenis_kelamin'] ?: '-')) ?></td>
            <td><?= e((string) ($s['tempat_lahir'] ?: '-')) ?></td>
            <td class="nowrap"><?= e((string) ($s['tanggal_lahir'] ? tanggal_id((string) $s['tanggal_lahir']) : '-')) ?></td>
            <td>
              <?= e((string) ($s['telepon'] ?: '-')) ?>
              <?php if (trim((string) ($s['alamat'] ?? '')) !== ''): ?><br><span class="tbl-sub"><?= e((string) $s['alamat']) ?></span><?php endif; ?>
            </td>
            <td><?= e((string) ($s['nama_ayah'] ?: '-')) ?></td>
            <td><?= e((string) ($s['nama_ibu'] ?: '-')) ?></td>
            <td><?= e((string) ($s['nama_wali'] ?: '-')) ?></td>
            <td><?= e((string) ($s['kelas_nama'] ?: 'belum berkelas')) ?></td>
            <td class="ctr"><?= (string) $s['status'] === 'lulus' ? 'Lulus' . ($s['tanggal_status'] ? ' (' . e(tanggal_id((string) $s['tanggal_status'])) . ')' : '') : ((int) $s['aktif'] === 1 ? 'Aktif' : 'Nonaktif') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="4" class="ctr">Jumlah</th>
            <th class="ctr"><?= $laki ?> L / <?= $perempuan ?> P</th>
            <th colspan="8" class="ctr"><?= count($rows) ?> siswa</th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="ket-print">
      Keterangan: daftar ini memuat data <?= e(istilah_sekolah('siswa sekolah')) ?> — NIS, NISN, nama lengkap,
      jenis kelamin, tempat &amp; tanggal lahir, nomor telepon (HP) beserta alamat, nama ayah, nama ibu,
      wali, kelas, dan statusnya. Status <b>Lulus</b> menandakan siswa sudah dinyatakan lulus (tidak lagi
      muncul pada daftar hadir &amp; penilaian), sedangkan <b>Nonaktif</b> berarti data administrasinya
      dinonaktifkan.
    </div>

    <!-- Tanda tangan: kepala sekolah/madrasah di KIRI, wali kelas (bila lembar dibatasi satu kelas) di KANAN. -->
    <div class="sheet-foot dua-ttd">
      <?php ttd_blok(ttd_mengetahui(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kiri'); ?>
      <?php ttd_blok('Wali Kelas', (string) $wali, '', 'sheet-sign sheet-sign-kanan', true); ?>
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
