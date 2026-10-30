<?php
/**
 * LEMBAR CETAK LAPORAN SUSUNAN ORGANISASI OSIM/OSIS (A4 portrait) — permintaan pemilik:
 * "Pada Menu pembina osim/osis isi dengan 'Laporan Susunan Organisasi' … Tambahkan fitur impor dan
 * ekspor dan juga cetak".
 *
 * Kolom: **No, Jabatan / Nama Seksi, Nama** (pilihan pemilik: Jabatan + Nama saja). Tiap seksi &
 * tiap anggota tercetak sebagai barisnya sendiri. Tanda tangan kepala sekolah/madrasah di sisi KANAN
 * (pola lembar cetak lain), plus kolom tanda tangan kosong seperti lembar daftar hadir.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/osim.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

// Kode menu dari URL (?kode=pramuka memakai modul yang sama dengan ?kode=osim).
$kode = osim_kode_normal((string) ($_GET['kode'] ?? OSIM_KODE_MENU));
$konfig = osim_menu_konfig($kode);
$tugas = tugas_menu_by_kode($kode);
if (!$tugas) {
    require_login();
    flash('Menu ' . $konfig['menu_bawaan'] . ' belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu($tugas);
require_sekolah();

$f = [
    'cari' => (string) ($_GET['cari'] ?? ''),
    'jabatan' => (string) ($_GET['jabatan'] ?? ''),
    'isi' => (string) ($_GET['isi'] ?? ''),
];
$baris = osim_pengurus_list($f, $kode);
if (!$baris) {
    flash('Belum ada susunan organisasi untuk dicetak pada saringan yang dipilih.', 'warn');
    redirect('tugas.php?kode=' . $kode . '&bagian=organisasi&tab=data');
}

$ringkas = osim_ringkas_pengurus($baris);
$labelFilter = osim_label_filter($f, 'organisasi');
$qFilter = osim_query($f);
$autoPrint = isset($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Susunan Organisasi OSIM/OSIS — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:900px;margin:0 auto}
  .toolbar{max-width:900px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  /* Tabel susunan organisasi: 3 kolom sehingga dibuat lapang & mudah dibaca. */
  table.sheet.osim th,table.sheet.osim td{padding:7px 10px;font-size:.82rem}
  table.sheet.osim .k-no{width:46px}
  table.sheet.osim .k-jabatan{width:34%}
  table.sheet.osim td.ttd{width:120px}
  /* Tata letak layar sempit HANYA untuk layar (media "screen"). */
  @media screen and (max-width:900px){
    .print-sheet{padding:18px 14px}
    table.sheet.osim{min-width:560px}
    .sheet-meta{grid-template-columns:minmax(0,1fr)}
  }
  @media print{
    /* Tema menetapkan body{min-height:100vh}; saat mencetak 100vh = tinggi kertas sehingga
       dokumen menjadi setinggi halaman dan ikut mencetak halaman kedua yang hampir kosong. */
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .sheet-wrap{max-width:none}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
    .sheet-scroll{overflow:visible}
    table.sheet.osim{min-width:0}
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap;justify-content:flex-end}
  }
  @page{size:A4 portrait;margin:14mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="tugas.php?kode=<?= e($kode) ?>&bagian=organisasi&tab=data<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>">Kembali</a>
  <a class="btn btn-ghost" href="osim_export.php?<?= e('kode=' . $kode . '&bagian=organisasi' . ($qFilter !== '' ? '&' . $qFilter : '')) ?>"><?= ikon('doc', 16) ?> Ekspor</a>
  <a class="btn btn-ghost" href="cetak_osim_program.php?kode=<?= e($kode) ?>" target="_blank"><?= ikon('print', 16) ?> Cetak Program Kerja</a>
  <a class="btn btn-primary" href="cetak_osim_organisasi.php?<?= e(http_build_query(array_filter($f + ['kode' => $kode, 'auto' => 1]))) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Susunan Organisasi OSIM/OSIS', ['Bidang Kesiswaan ' . istilah_sekolah('Sekolah')]); ?>

    <?php sheet_meta([
        'Jumlah Baris'   => (string) $ringkas['baris'],
        'Pengurus Terisi' => (string) $ringkas['terisi'],
        'Jumlah Seksi'   => (string) $ringkas['seksi'],
        'Saringan'       => $labelFilter ? implode(' · ', $labelFilter) : 'Seluruh susunan organisasi',
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet osim">
        <thead>
          <tr>
            <th class="ctr k-no">No</th>
            <th class="k-jabatan">Jabatan / Nama Seksi</th>
            <th>Nama</th>
            <th class="ctr ttd">Tanda Tangan</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($baris as $r): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e((string) $r['jabatan']) ?></b></td>
            <td><?= e((string) ($r['nama'] ?: '-')) ?></td>
            <td class="ttd"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="3" class="ctr">Jumlah</th>
            <th class="ctr"><?= count($baris) ?> baris</th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="ket-print">
      Keterangan: daftar ini memuat susunan organisasi OSIM/OSIS <?= e(istilah_sekolah('sekolah')) ?>
      mulai dari kepala sekolah/madrasah, wakil bidang kesiswaan, pembina OSIM/OSIS, ketua, wakil ketua,
      sekretaris, bendahara, seksi-seksi, hingga anggota. Kolom tanda tangan dikosongkan untuk diisi
      tangan bila diperlukan.
    </div>

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
