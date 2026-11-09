<?php
/**
 * LEMBAR CETAK "DAFTAR AKUN PENGAWAS" (Akun Pengawas) — A4 POTRAIT, e-GURU v1.83.0.
 *
 * Permintaan pemilik (tangkapan layar tombol pada kepala kartu "Daftar Akun Pengawas"):
 * *"Yang saya tandai warna merah tambahkan fitur download dan cetak. Hasil cetak buat seperti contoh
 * agar terlihat rapi"* — contohnya tabel sederhana berkolom **No, Pengawas Bina, Madrasah Binaan
 * (bernomor 1..n), Masa Aktif**, TANPA kop sekolah dan TANPA tanda tangan.
 *
 * Isi kolom Masa Aktif (pilihan pemilik): **ringkasan berapa binaan permanen / aktif / sudah
 * terkunci** — bukan tanggal, sebab yang bermasa aktif adalah madrasah binaannya, bukan akun
 * pengawasnya. Teksnya disusun `pengawas_masa_teks()` dari `aktivasi_status()`.
 *
 * Data diambil dari helper bersama `pengawas_binaan_ringkas()` sehingga urutan akun, daftar binaan,
 * dan angka masa aktifnya SAMA dengan tabel di halaman Akun Pengawas dan dengan unduhan Word
 * (`pengawas_binaan_word.php`).
 *
 * Akses: Super Admin (sama seperti halaman Akun Pengawas). Halaman ini hanya MEMBACA data.
 * Pemakaian: cetak_pengawas_binaan.php  /  cetak_pengawas_binaan.php?auto=1 (langsung dialog cetak)
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

// Lembar LINTAS sekolah (sekolah umum & madrasah sekaligus) → istilah generik "Sekolah/Madrasah".
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();
$GLOBALS['__vs_istilah_nonaktif'] = true;

$u = require_login();
if (!is_super()) {
    tolak403('Lembar daftar akun pengawas ini hanya untuk akun Super Admin.');
}

$ringkas = pengawas_binaan_ringkas();
$baris = $ringkas['baris'];
$tot = $ringkas['tot'];
$autoPrint = isset($_GET['auto']);
$waktuCetak = tanggal_id(date('Y-m-d'), true) . ', ' . date('H:i');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Daftar Akun Pengawas — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:840px;margin:0 auto}
  .toolbar{max-width:840px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .ket-print{font-size:.72rem;color:#635F82;margin-top:10px;line-height:1.5}
  /* Judul lembar sederhana seperti contoh pemilik: hanya judul + tabel (tanpa kop, tanpa tanda tangan). */
  .sheet-judul{text-align:center;margin:0 0 2px;font-size:1.02rem;font-weight:800;color:var(--hijau-tua)}
  .sheet-sub{text-align:center;margin:0 0 14px;font-size:.76rem;color:var(--muted)}
  table.sheet.pengawas th,table.sheet.pengawas td{padding:6px 8px;vertical-align:top}
  table.sheet.pengawas th{text-align:center}
  table.sheet.pengawas ol{margin:0;padding-left:20px}
  table.sheet.pengawas ol li{margin:0 0 1px;font-size:.8rem;line-height:1.35}
  .p-masa{font-size:.78rem;line-height:1.4}
  /* Baris tidak boleh terpotong antar halaman saat dicetak. */
  table.sheet.pengawas tr{break-inside:avoid}
  @media print{
    /* Tema menetapkan body{min-height:100vh} → saat dicetak peramban menambah halaman kosong. */
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .sheet-wrap{max-width:none}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
    .ket-print{margin-top:6px}
  }
  @page{size:A4 portrait;margin:16mm 18mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="pengawas.php">Kembali</a>
  <a class="btn btn-ghost" href="pengawas_binaan_word.php"><?= ikon('doc', 16) ?> Unduh Word</a>
  <a class="btn btn-primary" href="cetak_pengawas_binaan.php?auto=1">
    <?= ikon('print', 16) ?> Cetak / Simpan PDF
  </a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <h2 class="sheet-judul">Daftar Akun Pengawas</h2>
    <p class="sheet-sub"><?= (int) $tot['akun'] ?> akun pengawas (<?= (int) $tot['aktif'] ?> aktif),
      <?= (int) $tot['binaan'] ?> penugasan binaan aktif. Dicetak <?= e($waktuCetak) ?>.</p>

    <?php if (!$baris): ?>
      <p><i>Belum ada akun pengawas untuk dicetak.</i></p>
    <?php else: ?>
      <div class="sheet-scroll">
        <table class="sheet pengawas padat">
          <thead>
            <tr>
              <th class="ctr" style="width:34px">No</th>
              <th style="width:22%">Pengawas Bina</th>
              <th>Madrasah Binaan</th>
              <th style="width:24%">Masa Aktif</th>
            </tr>
          </thead>
          <tbody>
          <?php $n = 0; foreach ($baris as $b): $n++; ?>
            <tr>
              <td class="ctr"><?= $n ?></td>
              <td>
                <b><?= e($b['nama']) ?></b>
                <?php if (!$b['aktif']): ?>
                  <br><span class="tbl-sub">Nonaktif</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!$b['binaan']): ?>
                  <span class="tbl-sub">Belum ada sekolah/madrasah binaan</span>
                <?php else: ?>
                  <ol>
                    <?php foreach ($b['binaan'] as $sk): ?>
                      <li><?= e((string) $sk['nama']) ?></li>
                    <?php endforeach; ?>
                  </ol>
                  <?php if ((int) $b['nonaktif'] > 0): ?>
                    <span class="tbl-sub"><?= (int) $b['nonaktif'] ?> binaan nonaktif tidak dihitung</span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td class="p-masa"><?= e($b['masa_teks']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th class="ctr">&nbsp;</th>
              <th>JUMLAH (<?= (int) $tot['akun'] ?> pengawas)</th>
              <th><?= (int) $tot['binaan'] ?> penugasan binaan aktif</th>
              <th><?= e(pengawas_masa_teks([
                    'total' => (int) $tot['binaan'], 'permanen' => (int) $tot['permanen'],
                    'aktif' => (int) $tot['aktif_binaan'], 'uji' => (int) $tot['uji'],
                    'terkunci' => (int) $tot['terkunci'],
                  ])) ?></th>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>

    <div class="ket-print">
      Keterangan: kolom <b>Masa Aktif</b> merangkum status masa aktif seluruh madrasah binaan aktif
      pada baris itu — <b>permanen</b> (tanpa batas waktu), <b>aktif</b> (masa aktif berjalan),
      <b>uji coba</b> (hanya dicantumkan bila ada), dan <b>terkunci</b> (masa aktif/uji coba sudah
      habis sehingga aplikasi lembaga itu terkunci). Yang dihitung hanya binaan aktif; binaan
      nonaktif tidak dapat dibuka pengawas.
    </div>
  </div>
</div>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
<?php terjemahkan_selesai(); ?>
