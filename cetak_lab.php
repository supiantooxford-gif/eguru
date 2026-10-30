<?php
/**
 * LEMBAR CETAK DOKUMEN KEPALA LAB (A4 potrait) — permintaan pemilik:
 * "Pastikan ditambahkan fitur cetak dokumen".
 *
 * Dua dokumen dari satu berkas:
 *   ?bagian=barang   → Daftar Inventaris Barang (No, Nama Barang, Jumlah, Keadaan, Keterangan)
 *   ?bagian=program  → Laporan Program Kerja   (No, Program Kerja, Alokasi Waktu, Status Realisasi,
 *                      Keterangan)
 * Kolomnya PERSIS daftar permintaan pemilik, ditutup baris JUMLAH. Saringan yang sedang dipakai di
 * halaman data ikut dibawa (cari/keadaan/status). Tanda tangan kepala sekolah/madrasah di sisi
 * KANAN (pola lembar cetak lain aplikasi ini).
 *
 * Kertas A4 POTRAIT karena tabelnya hanya 5 kolom — sama seperti lembar cetak Daftar Butir
 * Penilaian; dokumennya jadi lebih lega dibaca daripada dipaksa melintang.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/lab.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$kode = (string) ($_GET['kode'] ?? '');
$tugas = lab_kode_ada($kode) ? tugas_menu_by_kode($kode) : null;
if (!$tugas) {
    require_login();
    flash('Menu Kepala Lab belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu($tugas);
require_sekolah();

$bagian = (string) ($_GET['bagian'] ?? '');
if (!in_array($bagian, ['barang', 'program'], true)) {
    $bagian = 'barang';
}
$f = [
    'cari'    => (string) ($_GET['cari'] ?? ''),
    'keadaan' => $bagian === 'barang' ? (string) ($_GET['keadaan'] ?? '') : '',
    'status'  => $bagian === 'program' ? (string) ($_GET['status'] ?? '') : '',
];
$barang = $bagian === 'barang' ? lab_barang($kode, $f) : [];
$program = $bagian === 'program' ? lab_program($kode, $f) : [];
$baris = $bagian === 'barang' ? $barang : $program;
if (!$baris) {
    flash('Belum ada data ' . ($bagian === 'barang' ? 'inventaris barang' : 'program kerja')
        . ' untuk dicetak pada saringan yang dipilih.', 'warn');
    redirect('tugas.php?kode=' . $kode . '&tab=' . $bagian);
}

$namaMenu = (string) $tugas['nama'];
$ringkasB = lab_ringkas_barang($barang);
$ringkasP = lab_ringkas_program($program);
$labelFilter = lab_label_filter($f);
$autoPrint = isset($_GET['auto']);
$judul = $bagian === 'barang' ? 'Daftar Inventaris Barang' : 'Laporan Program Kerja';
$qDasar = http_build_query(array_filter([
    'kode' => $kode, 'bagian' => $bagian, 'cari' => trim((string) $f['cari']),
    'keadaan' => trim((string) $f['keadaan']), 'status' => trim((string) $f['status']),
]));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:880px;margin:0 auto}
  .toolbar{max-width:880px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table.sheet.lab th,table.sheet.lab td{padding:4px 6px;font-size:.75rem;line-height:1.28}
  table.sheet.lab th{font-size:.68rem;letter-spacing:0;vertical-align:middle}
  table.sheet.lab td.sm{font-size:.7rem}
  table.sheet.lab .k-no{width:34px}
  table.sheet.lab .k-nama{width:30%}
  table.sheet.lab .k-jumlah{width:9%}
  table.sheet.lab .k-keadaan{width:12%}
  table.sheet.lab .k-alokasi{width:20%}
  table.sheet.lab .k-status{width:14%}
  /* Tata letak layar sempit HANYA untuk layar (media "screen"): saat MENCETAK, lebar kertas
     A4 potrait ±695px sehingga aturan max-width ikut aktif bila tidak dibatasi. */
  @media screen and (max-width:760px){
    .print-sheet{padding:18px 14px}
    table.sheet.lab{min-width:620px}
    .sheet-meta{grid-template-columns:minmax(0,1fr)}
  }
  @media print{
    /* Tema menetapkan body{min-height:100vh}; saat mencetak 100vh = tinggi kertas sehingga dokumen
       menjadi setinggi halaman dan ikut mencetak halaman kedua yang hampir kosong. */
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .sheet-wrap{max-width:none}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
    .sheet-scroll{overflow:visible}
    table.sheet.lab{min-width:0}
    table.sheet.lab thead{display:table-header-group}   /* judul kolom diulang tiap halaman */
    table.sheet.lab tr{break-inside:avoid}
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap;justify-content:flex-end}
  }
  @page{size:A4 portrait;margin:13mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="tugas.php?kode=<?= e($kode) ?>&tab=<?= e($bagian) ?>">Kembali</a>
  <?php // Dua dokumen dari satu menu: pengalih cepat tanpa kembali ke halaman data. ?>
  <a class="btn <?= $bagian === 'barang' ? 'btn-primary' : 'btn-ghost' ?>"
     href="cetak_lab.php?<?= e(http_build_query(array_filter(['kode' => $kode, 'bagian' => 'barang', 'cari' => trim((string) $f['cari']), 'keadaan' => trim((string) $f['keadaan'])]))) ?>">Inventaris Barang</a>
  <a class="btn <?= $bagian === 'program' ? 'btn-primary' : 'btn-ghost' ?>"
     href="cetak_lab.php?<?= e(http_build_query(array_filter(['kode' => $kode, 'bagian' => 'program', 'cari' => trim((string) $f['cari']), 'status' => trim((string) $f['status'])]))) ?>">Laporan Program</a>
  <a class="btn btn-primary" href="cetak_lab.php?<?= e($qDasar . '&auto=1') ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak($judul, [$namaMenu . ' ' . istilah_sekolah('Sekolah')]); ?>

    <?php if ($bagian === 'barang'): ?>
      <?php sheet_meta([
          'Unit Lab'      => $namaMenu,
          'Jumlah Barang' => (int) $ringkasB['jenis'] . ' jenis, ' . (int) $ringkasB['unit'] . ' unit',
          'Keadaan'       => implode(', ', array_map(
              static fn(string $k): string => $k . ': ' . (int) ($ringkasB['keadaan'][$k] ?? 0),
              LAB_KEADAAN)),
          'Saringan'      => $labelFilter ? implode(' · ', $labelFilter) : 'Seluruh daftar inventaris',
      ]); ?>

      <div class="sheet-scroll">
        <table class="sheet lab padat">
          <thead>
            <tr>
              <th class="ctr k-no">No</th>
              <th class="k-nama">Nama Barang</th>
              <th class="ctr k-jumlah">Jumlah</th>
              <th class="ctr k-keadaan">Keadaan</th>
              <th class="k-ket">Keterangan</th>
            </tr>
          </thead>
          <tbody>
          <?php $n = 0; foreach ($barang as $b): $n++; ?>
            <tr>
              <td class="ctr"><?= $n ?></td>
              <td><?= e((string) $b['nama']) ?></td>
              <td class="ctr"><?= (int) $b['jumlah'] ?></td>
              <td class="ctr"><?= e((string) ($b['keadaan'] ?: '-')) ?></td>
              <td class="sm"><?= e((string) ($b['keterangan'] ?: '-')) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="2" class="ctr">Jumlah</th>
              <th class="ctr"><?= (int) $ringkasB['unit'] ?> unit</th>
              <th colspan="2"><?= (int) $ringkasB['jenis'] ?> jenis barang</th>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="ket-print">
        Keterangan: keadaan barang dicatat <b><?= e(LAB_KEADAAN[0]) ?></b> atau
        <b><?= e(LAB_KEADAAN[1]) ?></b>. Barang yang keadaannya rusak mohon dicantumkan sebab/kerusakannya
        pada kolom Keterangan.
      </div>
    <?php else: ?>
      <?php sheet_meta([
          'Unit Lab'       => $namaMenu,
          'Jumlah Program' => (int) $ringkasP['program'] . ' program',
          'Realisasi'      => LAB_STATUS[0] . ': ' . (int) $ringkasP['terlaksana'] . ', '
              . LAB_STATUS[1] . ': ' . (int) $ringkasP['belum'],
          'Saringan'       => $labelFilter ? implode(' · ', $labelFilter) : 'Seluruh program kerja',
      ]); ?>

      <div class="sheet-scroll">
        <table class="sheet lab padat">
          <thead>
            <tr>
              <th class="ctr k-no">No</th>
              <th class="k-nama">Program Kerja</th>
              <th class="k-alokasi">Alokasi Waktu</th>
              <th class="ctr k-status">Status Realisasi</th>
              <th class="k-ket">Keterangan</th>
            </tr>
          </thead>
          <tbody>
          <?php $n = 0; foreach ($program as $r): $n++; ?>
            <tr>
              <td class="ctr"><?= $n ?></td>
              <td><?= e((string) $r['program']) ?></td>
              <td class="sm"><?= e((string) ($r['alokasi'] ?: '-')) ?></td>
              <td class="ctr"><?= e((string) ($r['status'] ?: '-')) ?></td>
              <td class="sm"><?= e((string) ($r['keterangan'] ?: '-')) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="3" class="ctr">Jumlah</th>
              <th class="ctr"><?= (int) $ringkasP['terlaksana'] ?> terlaksana</th>
              <th><?= (int) $ringkasP['program'] ?> program (<?= (int) $ringkasP['belum'] ?> belum)</th>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="ket-print">
        Keterangan: status realisasi program dicatat <b><?= e(LAB_STATUS[0]) ?></b> atau
        <b><?= e(LAB_STATUS[1]) ?></b>. Program yang belum terlaksana mohon dijelaskan kendalanya pada
        kolom Keterangan.
      </div>
    <?php endif; ?>

    <!-- Tanda tangan: kepala sekolah/madrasah di sisi KANAN (pola lembar cetak lain). -->
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
