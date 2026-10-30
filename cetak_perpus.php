<?php
/**
 * LEMBAR CETAK LAPORAN PERPUSTAKAAN (A4 landscape) — permintaan pemilik:
 * "Ditambahkan juga fitur cetak untuk setiap laporannya".
 *
 * Isi lembar = SALINAN APA ADANYA dari laporan e-PERPUS yang diimpor (judul kolom & barisnya),
 * ditambah kop sekolah/madrasah, identitas laporan (jumlah baris, berkas sumber, waktu impor,
 * saringan), baris JUMLAH untuk kolom angka yang bermakna, dan tanda tangan kepala
 * sekolah/madrasah di sisi KANAN (pola lembar cetak lain aplikasi ini).
 *
 * Saringan `?cari=` yang sedang dipakai di halaman laporan ikut dibawa.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/perpus.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$tugas = tugas_menu_by_kode(PERPUS_KODE_MENU);
if (!$tugas) {
    require_login();
    flash('Menu Ka. Perpustakaan belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu($tugas);
require_sekolah();

$jenis = (string) ($_GET['jenis'] ?? '');
if (!perpus_jenis_ada($jenis)) {
    flash('Jenis laporan perpustakaan tidak dikenal.', 'err');
    redirect(perpus_url());
}
$cari = trim((string) ($_GET['cari'] ?? ''));

$D = perpus_data($jenis, $cari);
$laporan = $D['laporan'];
$header = $D['header'];
$baris = $D['baris'];
if (!$laporan || !$baris) {
    flash($laporan
        ? 'Tidak ada baris yang cocok dengan pencarian "' . $cari . '" pada ' . perpus_jenis_nama($jenis) . '.'
        : 'Belum ada data ' . perpus_jenis_nama($jenis) . ' yang diimpor dari e-PERPUS.', 'warn');
    redirect(perpus_url($jenis, $cari));
}

$totals = perpus_totals($header, $baris, $jenis);
$adaNo = perpus_ada_no($header);
$autoPrint = isset($_GET['auto']);
$diimpor = trim((string) $laporan['diimpor']);
$tsImpor = $diimpor !== '' ? strtotime($diimpor) : false;
$judulLaporan = perpus_jenis_nama($jenis);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judulLaporan) ?> — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:1160px;margin:0 auto}
  .toolbar{max-width:1160px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  /* Jumlah kolom mengikuti berkas laporan e-PERPUS (9-12 kolom) → dirapatkan supaya muat satu lembar. */
  table.sheet.perpus th,table.sheet.perpus td{padding:3px 4px;font-size:.68rem;line-height:1.22}
  table.sheet.perpus th{font-size:.6rem;letter-spacing:0;padding:4px 3px;vertical-align:middle}
  table.sheet.perpus td.sm{font-size:.62rem}
  table.sheet.perpus tfoot th{font-size:.66rem;background:#eef3f0}
  /* Tata letak layar sempit HANYA untuk layar (media "screen"): saat MENCETAK, lebar kertas A4
     landscape ±1032px sehingga aturan max-width ikut aktif bila tidak dibatasi. */
  @media screen and (max-width:900px){
    .print-sheet{padding:18px 14px}
    table.sheet.perpus{min-width:860px}
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
    table.sheet.perpus{min-width:0}
    table.sheet.perpus thead{display:table-header-group}   /* judul kolom diulang tiap halaman */
    table.sheet.perpus tr{break-inside:avoid}
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap;justify-content:flex-end}
  }
  @page{size:A4 landscape;margin:12mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="<?= e(perpus_url($jenis, $cari)) ?>">Kembali</a>
  <a class="btn btn-primary" href="cetak_perpus.php?<?= e(http_build_query(array_filter(['jenis' => $jenis, 'cari' => $cari, 'auto' => 1]))) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak($judulLaporan, ['Perpustakaan ' . istilah_sekolah('Sekolah')]); ?>

    <?php sheet_meta([
        'Sumber Data'   => 'Hasil unduhan aplikasi e-PERPUS',
        'Berkas'        => (string) ($laporan['berkas'] ?: '(berkas tanpa nama)'),
        'Diimpor'       => $tsImpor !== false ? tanggal_id(date('Y-m-d', $tsImpor)) . ' pukul ' . date('H:i', $tsImpor) : '-',
        'Jumlah Baris'  => (string) count($baris) . ' baris' . (count($header) > 0 ? ', ' . count($header) . ' kolom' : ''),
        'Saringan'      => $cari !== '' ? 'Kata kunci: ' . $cari : 'Seluruh baris laporan',
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet perpus padat">
        <thead>
          <tr>
            <th class="ctr" style="width:26px">No</th>
            <?php
            $kolom = $adaNo ? array_slice($header, 1) : $header;
            foreach ($kolom as $h):
            ?>
              <th><?= e($h) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($baris as $row): $n++; $sel = $adaNo ? array_slice($row, 1) : $row; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <?php foreach ($sel as $v): ?>
              <td><?= $v === '' ? '-' : e((string) $v) ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($totals): ?>
          <tfoot>
            <tr>
              <th class="ctr">Jumlah</th>
              <?php
              foreach ($kolom as $i => $h):
                  $asli = $adaNo ? $i + 1 : $i;
                  $t = null;
                  foreach ($totals as $info) {
                      if ((int) $info['kolom'] === $asli) {
                          $t = $info;
                          break;
                      }
                  }
              ?>
                <th class="<?= $t ? 'ctr' : '' ?>"><?= $t ? e(perpus_angka((int) $t['nilai'], (bool) $t['rp'])) : '' ?></th>
              <?php endforeach; ?>
            </tr>
          </tfoot>
        <?php endif; ?>
      </table>
    </div>

    <div class="ket-print">
      Keterangan: laporan ini disalin dari berkas hasil unduhan aplikasi e-PERPUS
      (<?= e((string) ($laporan['berkas'] ?: 'berkas tanpa nama')) ?>). Judul & susunan kolomnya
      mengikuti berkas tersebut; baris JUMLAH hanya dihitung untuk kolom angka yang bermakna.
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
