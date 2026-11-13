<?php
/**
 * LEMBAR CETAK "REKAP KEPALA SEKOLAH/MADRASAH & ADMIN OPERATOR" — A4 LANDSCAPE
 * (`cetak_rekap_kepala_operator.php`, v1.89.0).
 *
 * Permintaan pemilik: *"Pada halaman super admin pada bagian bagian layanan tambahkan menu yang
 * menampilkan rekapan: No, nama sekolah/madrasah, nama kepala sekolah, nama operator, Nomer HP kepala
 * sekolah. … tambahkan fitur cetak pdf"*.
 *
 * Kolom lembar ini PERSIS lima seperti permintaan: No, Sekolah/Madrasah, Nama Kepala
 * Sekolah/Madrasah, Nama Operator, No. HP Kepala Sekolah/Madrasah.
 * Data diambil dari helper bersama `rekap_kepala_operator()` (+ saringan
 * `rekap_kepala_operator_saring()`) sehingga isinya SAMA dengan tabel pada halaman
 * `rekap_kepala_operator.php` — jangan menulis query sendiri di sini.
 *
 * Akses: Super Admin (lintas sekolah). Halaman ini hanya membaca data.
 * Pemakaian: cetak_rekap_kepala_operator.php[?cari=…]  /  …&auto=1 (langsung membuka dialog cetak)
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$u = require_login();
if (!is_super()) {
    tolak403('Lembar rekap kepala & operator ini hanya untuk akun Super Admin.');
}
// Lembar LINTAS sekolah → istilah generik "Sekolah/Madrasah".
$GLOBALS['__vs_istilah_nonaktif'] = true;

$rekap = rekap_kepala_operator();
$tot = $rekap['tot'];
$cari = trim((string) ($_GET['cari'] ?? ''));
$baris = rekap_kepala_operator_saring($rekap['baris'], $cari);
$autoPrint = isset($_GET['auto']);
$waktuCetak = tanggal_id(date('Y-m-d'), true) . ', ' . date('H:i');

// Tautan pada bilah tombol membawa saringan yang sedang dilihat.
$q = $cari !== '' ? '?cari=' . rawurlencode($cari) : '';
$qAuto = $cari !== '' ? '?cari=' . rawurlencode($cari) . '&amp;auto=1' : '?auto=1';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rekap Kepala &amp; Operator — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:1160px;margin:0 auto}
  .toolbar{max-width:1160px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table.sheet.rekap th,table.sheet.rekap td{padding:5px 7px}
  .ket-print{font-size:.72rem;color:#635F82;margin-top:10px;line-height:1.5}
  @media print{
    /* Tema menetapkan body{min-height:100vh} — saat MENCETAK itu setinggi kertas sehingga
       peramban menambah halaman kosong. Dimatikan di media cetak. */
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .sheet-wrap{max-width:none}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
    .sheet-scroll{overflow:visible}
    .sheet-head{padding-bottom:8px;margin-bottom:10px}
    .sheet-meta{gap:4px 14px}
    .ket-print{margin-top:6px}
  }
  @page{size:A4 landscape;margin:12mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="rekap_kepala_operator.php<?= e($q) ?>">Kembali</a>
  <a class="btn btn-primary" href="cetak_rekap_kepala_operator.php<?= $qAuto ?>">
    <?= ikon('print', 16) ?> Cetak / Simpan PDF
  </a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php // Kop TIDAK memakai kop_cetak(): lembar ini lintas sekolah (tidak ada satu lembaga yang
          // menjadi identitas lembar). Judul + identitas aplikasi dipakai sebagai kepala lembar. ?>
    <div class="sheet-head">
      <div class="kop-teks">
        <h2>Rekap Kepala Sekolah/Madrasah &amp; Admin Operator</h2>
        <p class="kop-nama"><?= e(tetap_kata(APP_NAME . ' — ' . APP_SUB)) ?></p>
      </div>
    </div>

    <?php sheet_meta([
        'Lingkup'  => 'Seluruh sekolah/madrasah terdaftar (' . count($baris) . ' lembaga'
            . ($cari !== '' ? ', saringan "' . $cari . '"' : '') . ')',
        'Urutan'   => 'Nama sekolah/madrasah A–Z',
        'Dicetak'  => $waktuCetak,
        'Oleh'     => (string) $u['nama'] . ' (' . role_label('super') . ')',
    ]); ?>

    <?php if (!$baris): ?>
      <p><i>Tidak ada lembaga yang dapat dicetak<?= $cari !== '' ? ' untuk saringan itu' : '' ?>.</i></p>
    <?php else: ?>
      <div class="sheet-scroll">
        <table class="sheet rekap padat">
          <thead>
            <tr>
              <th class="ctr" style="width:34px">No</th>
              <th style="width:28%">Sekolah/Madrasah</th>
              <th style="width:22%">Nama Kepala Sekolah/Madrasah</th>
              <th style="width:22%">Nama Operator</th>
              <th style="width:18%">No. HP Kepala Sekolah/Madrasah</th>
            </tr>
          </thead>
          <tbody>
          <?php $n = 0; foreach ($baris as $b): $n++; ?>
            <tr>
              <td class="ctr"><?= $n ?></td>
              <td><b><?= e(tetap_kata($b['nama'])) ?></b><br>
                <span style="font-size:.7rem"><?= e($b['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah Umum') ?>,
                  NPSN <?= e(identitas_nilai((string) ($b['sekolah']['npsn'] ?? '')) !== ''
                      ? (string) $b['sekolah']['npsn'] : '-') ?>,
                  <?= $b['aktif'] ? 'Aktif' : ($b['verifikasi'] !== 'disetujui' ? 'Belum disetujui' : 'Nonaktif') ?></span></td>
              <td><?= $b['kepala_teks'] !== '' ? e(tetap_kata($b['kepala_teks'])) : '<span class="mut">—</span>' ?>
                <?php if ($b['kepala_dari_identitas']): ?>
                  <br><span style="font-size:.68rem">(dari identitas lembaga<?= $b['kepala_nonaktif'] > 0
                    ? ', akun kepala nonaktif' : ', belum ada akun kepala' ?>)</span>
                <?php elseif ($b['kepala_akun']): ?>
                  <br><span style="font-size:.68rem">Akun: <?= e(implode(', ', array_column($b['kepala_akun'], 'username'))) ?></span>
                <?php endif; ?></td>
              <td><?= $b['operator_teks'] !== '' ? e(tetap_kata($b['operator_teks'])) : '<span class="mut">—</span>' ?>
                <?php if ($b['operator_akun']): ?>
                  <br><span style="font-size:.68rem">Akun: <?= e(implode(', ', array_column($b['operator_akun'], 'username'))) ?></span>
                <?php endif; ?></td>
              <td><?= $b['hp'] !== '' ? e($b['hp']) : '<span class="mut">—</span>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="2" style="text-align:right">JUMLAH (<?= count($baris) ?> sekolah/madrasah)</th>
              <th><?= (int) $tot['punya_kepala'] ?> punya akun kepala<?= $tot['tanpa_kepala'] > 0
                ? ', ' . (int) $tot['tanpa_kepala'] . ' belum' : '' ?></th>
              <th><?= (int) $tot['punya_operator'] ?> punya operator (<?= (int) $tot['akun_operator'] ?> akun)<?= $tot['tanpa_operator'] > 0
                ? ', ' . (int) $tot['tanpa_operator'] . ' belum' : '' ?></th>
              <th><?= (int) $tot['tanpa_hp'] ?> belum mengisi nomor HP</th>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>

    <div class="ket-print">
      Keterangan: <b>Nama Kepala</b> dibaca dari akun kepala sekolah/madrasah tiap lembaga; bila belum
      ada akunnya, yang dicetak adalah nama kepala pada identitas lembaga (bertanda "dari identitas
      lembaga"). <b>Nama Operator</b> dapat lebih dari satu (ditambahkan akun kepala sekolah/madrasah
      lewat menu Akun Operator). <b>No. HP</b> dibaca dari kolom Telepon / HP (WhatsApp) pada identitas
      lembaga. Tanda "—" berarti belum diisi.
    </div>
  </div>
</div>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
<?php terjemahkan_selesai(); ?>
