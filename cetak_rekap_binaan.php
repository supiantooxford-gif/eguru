<?php
/**
 * CETAK REKAPAN BINAAN (khusus akun PENGAWAS SEKOLAH/MADRASAH) — lembar A4 landscape.
 *
 * Permintaan pemilik: "Tambahkan fitur untuk Akun pengawas seperti aplikasi Supervisi.
 * Hanya bisa melihat hasil rekapan dan mencetak."
 *
 * Pemakaian:
 *   cetak_rekap_binaan.php              → rekapan SELURUH sekolah/madrasah binaan pengawas
 *   cetak_rekap_binaan.php?sekolah=ID   → rekapan satu binaan (harus binaannya — anti-IDOR)
 *
 * Hanya membaca data (tidak mengubah apa pun). Tanda tangan: hanya Kepala Sekolah/Madrasah dari
 * Pengaturan sekolah yang bersangkutan, di sisi KANAN (pola yang sama dengan lembar cetak lain),
 * dan hanya dicetak pada rekapan satu sekolah (rekapan lintas binaan tidak ditandatangani).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$u = require_login();
if (!is_pengawas()) {
    tolak403('Lembar rekapan binaan ini hanya untuk akun Pengawas Sekolah/Madrasah.');
}

$idSekolah = (int) ($_GET['sekolah'] ?? 0);
if ($idSekolah > 0) {
    // Anti-IDOR: hanya sekolah binaan pengawas yang bersangkutan.
    if (!pengawas_punya((int) $u['id'], $idSekolah)) {
        tolak403('Sekolah/madrasah itu bukan binaan Anda.');
    }
    $binaan = array_values(array_filter(sekolah_binaan((int) $u['id'], true),
        static fn(array $s): bool => (int) $s['id'] === $idSekolah));
    if (!$binaan) {
        flash('Sekolah/madrasah itu sedang tidak aktif.', 'warn');
        redirect('index.php');
    }
    // MASA AKTIF HABIS (v1.71.8 — permintaan pemilik): data binaan yang masa aktif penggunaan
    // aplikasinya berakhir TIDAK dapat dibuka maupun dicetak; pengawas diberi keterangannya.
    if (aktivasi_terkunci($binaan[0])) {
        flash(aktivasi_pesan_binaan_terkunci($binaan[0]), 'warn');
        redirect('index.php');
    }
} else {
    // Rekapan seluruh binaan: binaan yang masa aktifnya habis TIDAK ikut dicetak.
    $binaan = array_values(array_filter(sekolah_binaan((int) $u['id'], true),
        static fn(array $s): bool => !aktivasi_terkunci($s)));
    $adaTerkunci = count($binaan) < count(sekolah_binaan((int) $u['id'], true));
}

if (!$binaan) {
    flash('Belum ada sekolah/madrasah binaan untuk dicetak. Hubungi Super Admin.', 'warn');
    redirect('index.php');
}

$satuBinaan = count($binaan) === 1 ? $binaan[0] : null;
$awalBulan = date('Y-m-01');
$akhirBulan = date('Y-m-t');

/** Rekapan angka satu sekolah/madrasah. */
function rekap_binaan(int $sid, string $awalBulan, string $akhirBulan): array
{
    return [
        'guru'      => (int) val('SELECT COUNT(*) FROM guru WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0),
        'siswa'     => (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0),
        'kelas'     => (int) val('SELECT COUNT(*) FROM kelas WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0),
        'mapel'     => (int) val('SELECT COUNT(*) FROM mapel WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0),
        'nilai'     => (int) val('SELECT COUNT(*) FROM nilai WHERE sekolah_id = ?', [$sid], 0),
        'jurnal'    => (int) val('SELECT COUNT(*) FROM jurnal WHERE sekolah_id = ?', [$sid], 0),
        'hadir'     => (int) val('SELECT COUNT(*) FROM absensi WHERE sekolah_id = ? AND tanggal BETWEEN ? AND ?',
            [$sid, $awalBulan, $akhirBulan], 0),
        'kasus'     => (int) val('SELECT COUNT(*) FROM kasus WHERE sekolah_id = ?', [$sid], 0),
        'kasus_akt' => (int) val('SELECT COUNT(*) FROM kasus WHERE sekolah_id = ? AND status <> "selesai"', [$sid], 0),
    ];
}

$baris = [];
$total = ['guru' => 0, 'siswa' => 0, 'kelas' => 0, 'nilai' => 0, 'jurnal' => 0, 'hadir' => 0, 'kasus' => 0];
foreach ($binaan as $b) {
    $r = rekap_binaan((int) $b['id'], $awalBulan, $akhirBulan);
    $baris[] = ['s' => $b, 'r' => $r];
    foreach ($total as $k => $v) {
        $total[$k] = $v + (int) $r[$k];
    }
}

$autoPrint = isset($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rekapan Binaan Pengawas — <?= e(APP_NAME) ?></title>
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
  }
  /* Tanda tangan kepala sekolah di KANAN lembar + tata letak dipadatkan agar seluruh rekapan
     muat SATU halaman A4 landscape (terukur: tinggi isi <= 703px = tinggi area cetak). */
  @media print{
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap;justify-content:flex-end;margin-top:16px}
    .sheet-foot.kanan .sheet-sign{flex:0 0 auto;min-width:250px;text-align:center}
    .sheet-sign .space{height:38px}
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
  <a class="btn btn-ghost" href="index.php<?= $satuBinaan ? '?keluar=1' : '' ?>">Kembali</a>
  <a class="btn btn-primary" href="cetak_rekap_binaan.php?<?= e(http_build_query(array_filter(['sekolah' => $idSekolah ?: null, 'auto' => 1]))) ?>">
    <?= ikon('print', 16) ?> Cetak / Simpan PDF
  </a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Rekapan Hasil Data Binaan Pengawas', [
        'Lingkup: ' . ($satuBinaan ? tetap_kata(nama_sekolah_tampil($satuBinaan)) : count($binaan) . ' sekolah/madrasah binaan'),
    ]); ?>

    <?php sheet_meta([
        'Pengawas'          => (string) $u['nama'] . ' (' . role_label('pengawas') . ')',
        'Jumlah Binaan'     => (string) count($binaan),
        'Periode Kehadiran' => tanggal_id($awalBulan) . ' s.d. ' . tanggal_id($akhirBulan),
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet rekap padat">
        <thead>
          <tr>
            <th class="ctr" style="width:26px">No</th>
            <th style="width:24%">Nama Sekolah/Madrasah</th>
            <th class="ctr" style="width:7%">Jenis</th>
            <th class="ctr" style="width:6%">Kelas</th>
            <th class="ctr" style="width:6%">Guru</th>
            <th class="ctr" style="width:6%">Siswa</th>
            <th class="ctr" style="width:9%">Baris Nilai</th>
            <th class="ctr" style="width:9%">Pertemuan Hadir</th>
            <th class="ctr" style="width:8%">Catatan Jurnal</th>
            <th class="ctr" style="width:9%">Pelanggaran</th>
            <th class="ctr" style="width:10%">Belum Selesai</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($baris as $b): $n++; $r = $b['r']; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e((string) $b['s']['nama']) ?></b><br>
              <span style="font-size:.7rem">NPSN <?= e((string) ($b['s']['npsn'] ?: '-')) ?></span></td>
            <td class="ctr"><?= e($b['s']['jenis'] === 'madrasah' ? 'Madrasah' : 'Umum') ?></td>
            <td class="ctr"><?= (int) $r['kelas'] ?></td>
            <td class="ctr"><?= (int) $r['guru'] ?></td>
            <td class="ctr"><?= (int) $r['siswa'] ?></td>
            <td class="ctr"><?= (int) $r['nilai'] ?></td>
            <td class="ctr"><?= (int) $r['hadir'] ?></td>
            <td class="ctr"><?= (int) $r['jurnal'] ?></td>
            <td class="ctr"><?= (int) $r['kasus'] ?></td>
            <td class="ctr"><?= (int) $r['kasus_akt'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="3" style="text-align:right">JUMLAH</th>
            <th class="ctr"><?= (int) $total['kelas'] ?></th>
            <th class="ctr"><?= (int) $total['guru'] ?></th>
            <th class="ctr"><?= (int) $total['siswa'] ?></th>
            <th class="ctr"><?= (int) $total['nilai'] ?></th>
            <th class="ctr"><?= (int) $total['hadir'] ?></th>
            <th class="ctr"><?= (int) $total['jurnal'] ?></th>
            <th class="ctr"><?= (int) $total['kasus'] ?></th>
            <th class="ctr"><?= (int) $total['kasus_akt'] ?></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="ket-print">
      Keterangan: <b>Pertemuan Hadir</b> dihitung dari pertemuan daftar hadir pada periode bulan berjalan
      (<?= e(tanggal_id($awalBulan)) ?> s.d. <?= e(tanggal_id($akhirBulan)) ?>); kolom lain dihitung dari
      seluruh data yang tercatat. Lembar ini hanya <b>rekapan hasil data</b> — akun pengawas tidak mengubah
      data apa pun.
      <?php if (!empty($adaTerkunci)): ?>
        <br>Catatan: sekolah/madrasah binaan yang <b>masa aktif penggunaan aplikasinya telah berakhir
        tidak dicetak</b> pada rekapan ini (datanya tidak dapat diakses sampai masa aktifnya diperpanjang).
      <?php endif; ?>
    </div>

    <?php if ($satuBinaan): ?>
      <!-- Tanda tangan: hanya kepala sekolah/madrasah binaan tersebut, di sisi KANAN lembar. -->
      <div class="sheet-foot kanan">
        <?php ttd_blok(ttd_mengetahui(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kanan'); ?>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
<?php terjemahkan_selesai(); ?>
