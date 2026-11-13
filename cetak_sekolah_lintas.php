<?php
/**
 * LEMBAR CETAK "DAFTAR SEKOLAH/MADRASAH" (Ringkasan Semua Sekolah) — A4 LANDSCAPE.
 *
 * Permintaan pemilik (v1.82.0): *"Pada yang saya tandai warna merah tambahkan fitur download word
 * dan cetak (kolom mapel, jurnal dan nilai tidak perlu di cetak/download). Hanya No, Nama Madrasah,
 * Kelas, Guru, Siswa, Masa Aktif"*.
 *
 * Jadi lembar ini SENGAJA hanya memuat enam kolom: No, Sekolah/Madrasah, Kelas, Guru, Siswa,
 * Masa Aktif. Kolom **Mapel, Jurnal, dan Nilai TIDAK dicetak** (permintaan pemilik) — jangan
 * menambahkannya kembali tanpa bertanya.
 *
 * Data diambil dari helper bersama `sekolah_lintas_ringkas()` sehingga angka, keadaan masa aktif,
 * dan urutannya SAMA dengan tabel pada dasbor (masa aktif paling lama di atas) dan dengan unduhan
 * Word (`sekolah_lintas_word.php`).
 *
 * Akses: Super Admin (lintas sekolah). Halaman ini hanya membaca data — tidak mengubah apa pun.
 * Pemakaian: cetak_sekolah_lintas.php  /  cetak_sekolah_lintas.php?auto=1 (langsung membuka dialog cetak)
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$u = require_login();
if (!is_super()) {
    tolak403('Lembar daftar sekolah/madrasah ini hanya untuk akun Super Admin.');
}
// Lembar LINTAS sekolah → istilah generik "Sekolah/Madrasah" (tidak diterjemahkan menjadi
// "Madrasah" walaupun konteks sekolah yang sedang dibuka adalah madrasah).
$GLOBALS['__vs_istilah_nonaktif'] = true;

// PENCARIAN (v1.90.0): lembar cetak mengikuti kata kunci yang sedang dipakai pada dasbor
// (`index.php?semua=1&cari=…`) sehingga YANG TAMPIL = YANG TERCETAK.
$cari = trim((string) ($_GET['cari'] ?? ''));
$ringkas = sekolah_lintas_ringkas($cari);
$baris = $ringkas['baris'];
$tot = $ringkas['tot'];
$q = $cari !== '' ? '?cari=' . rawurlencode($cari) : '';
$qAuto = $cari !== '' ? '?cari=' . rawurlencode($cari) . '&amp;auto=1' : '?auto=1';
$autoPrint = isset($_GET['auto']);
$waktuCetak = tanggal_id(date('Y-m-d'), true) . ', ' . date('H:i');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Daftar Sekolah/Madrasah — <?= e(APP_NAME) ?></title>
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
  <a class="btn btn-ghost" href="index.php?semua=1<?= $cari !== '' ? '&amp;cari=' . e(rawurlencode($cari)) : '' ?>">Kembali</a>
  <a class="btn btn-ghost" href="sekolah_lintas_word.php<?= $q ?>"><?= ikon('doc', 16) ?> Unduh Word</a>
  <a class="btn btn-primary" href="cetak_sekolah_lintas.php<?= $qAuto ?>">
    <?= ikon('print', 16) ?> Cetak / Simpan PDF
  </a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php // Kop TIDAK memakai kop_cetak(): lembar ini lintas sekolah (tidak ada satu lembaga yang
          // menjadi identitas lembar). Judul + identitas aplikasi dipakai sebagai kepala lembar. ?>
    <div class="sheet-head">
      <div class="kop-teks">
        <h2>Daftar Sekolah/Madrasah</h2>
        <p class="kop-nama"><?= e(tetap_kata(APP_NAME . ' — ' . APP_SUB)) ?></p>
      </div>
    </div>

    <?php sheet_meta([
        'Lingkup'        => ($cari !== '' ? 'Pencarian "' . $cari . '" — ' : 'Seluruh sekolah/madrasah aktif (')
            . count($baris) . ' lembaga' . ($cari !== '' ? ' cocok' : '') . ')',
        'Urutan'         => 'Masa aktif paling lama di atas',
        'Dicetak'        => $waktuCetak,
        'Oleh'           => (string) $u['nama'] . ' (' . role_label('super') . ')',
    ]); ?>

    <?php if (!$baris): ?>
      <p><i><?= $cari !== '' ? 'Tidak ada sekolah/madrasah yang cocok dengan pencarian itu.'
        : 'Belum ada sekolah/madrasah aktif untuk dicetak.' ?></i></p>
    <?php else: ?>
      <div class="sheet-scroll">
        <table class="sheet rekap padat">
          <thead>
            <tr>
              <th class="ctr" style="width:34px">No</th>
              <th style="width:38%">Sekolah/Madrasah</th>
              <th class="ctr" style="width:8%">Kelas</th>
              <th class="ctr" style="width:8%">Guru</th>
              <th class="ctr" style="width:8%">Siswa</th>
              <th style="width:30%">Masa Aktif</th>
            </tr>
          </thead>
          <tbody>
          <?php $n = 0; foreach ($baris as $b): $n++; $s = $b['sekolah']; $st = $b['stat']; ?>
            <tr>
              <td class="ctr"><?= $n ?></td>
              <td><b><?= e(tetap_kata(nama_sekolah_tampil($s))) ?></b><br>
                <span style="font-size:.7rem"><?= e($s['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah Umum') ?>,
                  NPSN <?= e((string) ($s['npsn'] ?: '-')) ?></span></td>
              <td class="ctr"><?= (int) $st['kelas'] ?></td>
              <td class="ctr"><?= (int) $st['guru'] ?></td>
              <td class="ctr"><?= (int) $st['siswa'] ?></td>
              <td><?= e(masa_aktif_teks($s)) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="2" style="text-align:right">JUMLAH (<?= count($baris) ?> sekolah/madrasah)</th>
              <th class="ctr"><?= (int) $tot['kelas'] ?></th>
              <th class="ctr"><?= (int) $tot['guru'] ?></th>
              <th class="ctr"><?= (int) $tot['siswa'] ?></th>
              <th></th>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>

    <div class="ket-print">
      Keterangan: kolom <b>Masa Aktif</b> dibaca dari status aktivasi tiap lembaga (permanen, masa
      aktif 365 hari, masa uji coba, atau sudah berakhir/terkunci). Angka diambil saat lembar dicetak;
      rincian lengkap tiap lembaga tersedia pada dasbor Ringkasan Semua Sekolah.
    </div>
  </div>
</div>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
<?php terjemahkan_selesai(); ?>
