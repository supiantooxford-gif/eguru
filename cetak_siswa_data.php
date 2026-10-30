<?php
/**
 * LEMBAR CETAK — DATA SISWA (A4 portrait) untuk akun siswa (v1.61.0).
 *
 * Permintaan pemilik: setiap halaman siswa punya tombol Cetak; lembar ini mencetak IDENTITAS siswa
 * (Nama & NISN beserta data lain) apa adanya — tanpa kolom yang dapat diubah.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$sw = require_siswa();
$id = siswa_identitas($sw);
$foto = $id['Foto'];
unset($id['Foto']);
$autoPrint = isset($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Siswa — <?= e((string) $sw['nama']) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:900px;margin:0 auto}
  .toolbar{max-width:900px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-foot{justify-content:flex-end}
  .sis-cetak-profil{display:flex;gap:18px;align-items:flex-start;margin-top:6px}
  .sis-cetak-foto{
    width:30mm;height:40mm;flex:0 0 auto;border:1px solid #999;border-radius:6px;overflow:hidden;
    display:grid;place-items:center;background:#fff;
  }
  .sis-cetak-foto img{width:100%;height:100%;object-fit:cover;display:block}
  .sis-cetak-foto span{font-size:.62rem;color:#666;text-align:center;padding:4px}
  @media print{
    /* Tema menetapkan body{min-height:100vh}; saat mencetak nilainya = tinggi kertas sehingga
       peramban mencetak halaman kedua yang hampir kosong. Karena itu dimatikan saat mencetak. */
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
  }
  @page{size:A4 portrait;margin:15mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="siswa_saya.php">Kembali</a>
  <a class="btn btn-primary" href="cetak_siswa_data.php?auto=1"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Data Siswa', ['Keterangan Identitas Siswa']); ?>

    <div class="sis-cetak-profil">
      <div class="sis-cetak-foto">
        <?php if ($foto !== null): ?>
          <img src="<?= e($foto) ?>" alt="Pas foto <?= e((string) $sw['nama']) ?>">
        <?php else: ?>
          <span>Pas foto 3 x 4</span>
        <?php endif; ?>
      </div>
      <div style="flex:1 1 auto">
        <table class="sheet padat">
          <tbody>
          <?php foreach ($id as $label => $nilai): ?>
            <tr>
              <th style="width:38%;text-align:left"><?= e((string) $label) ?></th>
              <td><?= e((string) $nilai) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <p class="xs mut" style="margin-top:12px">
      Lembar ini dicetak dari akun siswa <?= e(APP_NAME) ?> dan memuat data sebagaimana tercatat pada
      <?= e(tetap_kata('sekolah/madrasah')) ?> (tidak dapat diubah dari akun siswa).
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
