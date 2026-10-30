<?php
/**
 * LEMBAR CETAK REKAP PELANGGARAN SISWA — menu Tugas Tambahan "Kesiswaan" (A4 landscape).
 *
 * Permintaan pemilik: *"Pada menu kesiswaan tambahkan fitur rekap pelanggaran dari seluruh guru
 * mata pelajaran. Jadi guru yang dapat tugas tambahan kesiswaan bisa melihat, memberikan tindak
 * lanjut, dan mencetak seluruh catatan pelanggaran dari setiap guru mata pelajaran."*
 *
 * Isi: kop sekolah, identitas saringan (periode/kelas/mapel/guru/jenis/status/tindak lanjut),
 * ringkasan jumlah, lalu SATU tabel berisi SELURUH catatan pelanggaran sekolah ini dari guru mata
 * pelajaran mana pun — lengkap dengan kolom **Tindak Lanjut** (jenis, catatan, penindak, tanggal).
 * Tanda tangan kepala sekolah/madrasah di sisi KANAN (pola lembar cetak lain); tanpa tanda "·".
 *
 * Saringan mengikuti halaman menu Kesiswaan → tab Rekap Pelanggaran (`ks_pelanggaran_list()`).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/kesiswaan.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$tugas = tugas_menu_by_kode(KS_KODE_MENU);
if (!$tugas) {
    require_login();
    flash('Menu Kesiswaan belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu($tugas);   // siapa pun yang boleh membuka menu Kesiswaan boleh mencetak
require_sekolah();

$f = [
    'cari' => (string) ($_GET['cari'] ?? ''),
    'kelas' => (int) ($_GET['kelas'] ?? 0),
    'mapel' => (int) ($_GET['mapel'] ?? 0),
    'guru' => (int) ($_GET['guru'] ?? 0),
    'jenis' => (string) ($_GET['jenis'] ?? ''),
    'status' => (string) ($_GET['status'] ?? ''),
    'tl' => (string) ($_GET['tl'] ?? ''),
    'dari' => (string) ($_GET['dari'] ?? ''),
    'sampai' => (string) ($_GET['sampai'] ?? ''),
];
$baris = ks_pelanggaran_list($f, true);   // urut kronologis untuk lembar cetak
if (!$baris) {
    flash('Belum ada catatan pelanggaran untuk dicetak pada saringan yang dipilih.', 'warn');
    redirect('tugas.php?kode=' . KS_KODE_MENU . '&tab=pelanggaran');
}
$ringkas = ks_pelanggaran_ringkas($baris);
$jenisList = kasus_jenis();
$statusList = kasus_status();

// ---------------------------------------------------------------- identitas lembar
$label = [];
if ((int) $f['kelas'] > 0 && kelas_by_id((int) $f['kelas'])) {
    $label['Kelas'] = (string) (kelas_by_id((int) $f['kelas'])['nama'] ?? '');
}
if ((int) $f['mapel'] > 0 && mapel_by_id((int) $f['mapel'])) {
    $label['Mata Pelajaran'] = (string) (mapel_by_id((int) $f['mapel'])['nama'] ?? '');
}
if ((int) $f['guru'] > 0) {
    $label['Dicatat oleh'] = (string) val('SELECT nama FROM guru WHERE id = ? AND sekolah_id = ?',
        [(int) $f['guru'], sekolah_id()], '');
}
if (array_key_exists((string) $f['jenis'], $jenisList)) {
    $label['Jenis'] = (string) $jenisList[(string) $f['jenis']];
}
if (array_key_exists((string) $f['status'], $statusList)) {
    $label['Status'] = (string) $statusList[(string) $f['status']];
}
if ((string) $f['tl'] === 'sudah') {
    $label['Tindak Lanjut'] = 'Sudah ditindaklanjuti';
} elseif ((string) $f['tl'] === 'belum') {
    $label['Tindak Lanjut'] = 'Belum ditindaklanjuti';
}
$dari = tgl_ok($f['dari'] ?? null);
$sampai = tgl_ok($f['sampai'] ?? null);
if ($dari !== null && $sampai !== null && $dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}
$label['Periode'] = ($dari || $sampai)
    ? (($dari ? tanggal_id($dari) : 'awal') . ' s/d ' . ($sampai ? tanggal_id($sampai) : 'akhir'))
    : 'Seluruh catatan yang tercatat';
if (trim((string) $f['cari']) !== '') {
    $label['Kata kunci'] = (string) $f['cari'];
}
$label['Sumber'] = 'Catatan seluruh guru mata pelajaran';

$autoPrint = isset($_GET['auto']);
$qKembali = ks_pelanggaran_query($f);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rekap Pelanggaran Siswa — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:1160px;margin:0 auto}
  .toolbar{max-width:1160px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table.sheet.pk th,table.sheet.pk td{padding:4px 6px;font-size:.72rem;line-height:1.3;vertical-align:top}
  table.sheet.pk th{font-size:.65rem;letter-spacing:0;vertical-align:middle}
  table.sheet.pk td.puk{text-align:center;white-space:nowrap}
  table.sheet.pk .tl-jenis{font-weight:700;color:var(--hijau-tua);display:block}
  table.sheet.pk .tl-oleh{font-size:.64rem;color:var(--muted);display:block;margin-top:2px}
  table.sheet.pk .tl-catatan{margin-top:2px}
  .ket-print{max-width:1160px;margin:0 auto;font-size:.72rem;color:#635F82;line-height:1.5}
  /* Tata letak layar sempit: HANYA untuk layar supaya saat dicetak tanda tangan tetap kanan. */
  @media screen and (max-width:900px){ .print-sheet{padding:18px 14px} }
  @media print{
    /* Tema menetapkan body{min-height:100vh} → tanpa ini tercetak halaman kedua yang kosong. */
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print,.ket-print{display:none !important}
    .sheet-wrap{max-width:none}
    .sheet-scroll{overflow:visible}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
    table.sheet.pk th,table.sheet.pk td{padding:3px 5px;font-size:.66rem}
    .sheet-foot{margin-top:18px}
    .sheet-foot .space{height:52px}
  }
  @page{size:A4 landscape;margin:10mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="tugas.php?kode=<?= e(KS_KODE_MENU) ?>&tab=pelanggaran<?= $qKembali !== '' ? '&' . e($qKembali) : '' ?>">Kembali</a>
  <a class="btn btn-primary" href="cetak_kesiswaan_pelanggaran.php?<?= e(http_build_query(array_merge($_GET, ['auto' => 1]))) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php // Judul lembar memakai kop (pola lembar cetak lain) + baris keterangan sumber data. ?>
    <?php kop_cetak('Rekap Pelanggaran Siswa',
        ['Catatan pelanggaran dari seluruh guru mata pelajaran ' . istilah_sekolah('Sekolah')]); ?>

    <?php sheet_meta(array_merge($label, [
        'Jumlah Catatan'        => (int) $ringkas['total'] . ' catatan',
        'Jumlah Siswa'          => (int) $ringkas['siswa'] . ' siswa',
        'Sudah Ditindaklanjuti' => (int) $ringkas['sudah'] . ' catatan',
        'Belum Ditindaklanjuti' => (int) $ringkas['belum'] . ' catatan',
    ])); ?>

    <div class="sheet-scroll">
      <table class="sheet pk">
        <thead>
          <tr>
            <th class="ctr" style="width:28px">No</th>
            <th style="width:78px">Hari / Tanggal</th>
            <th style="width:120px">Nama Siswa</th>
            <th style="width:60px">Kelas</th>
            <th style="width:82px">Mata Pelajaran</th>
            <th style="width:78px">Jenis</th>
            <th>Uraian Pelanggaran</th>
            <th style="width:92px">Dicatat oleh</th>
            <th class="ctr" style="width:64px">Status</th>
            <th style="width:170px">Tindak Lanjut</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($baris as $r): $n++;
          $tlJenis = ks_tl_label((string) ($r['tindak_lanjut_jenis'] ?? ''));
          $tlCatatan = trim((string) ($r['tindak_lanjut'] ?? ''));
          $statusKasus = (string) ($r['status'] ?? 'baru');
        ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><?= e(hari_id((string) $r['tanggal'])) ?>, <?= e(tanggal_id((string) $r['tanggal'])) ?></td>
            <td><b><?= e((string) ($r['siswa_nama'] ?: '-')) ?></b>
              <?php if (trim((string) ($r['nis'] ?? '')) !== ''): ?><br><span class="mut">NIS <?= e((string) $r['nis']) ?></span><?php endif; ?>
            </td>
            <td><?= e((string) ($r['kelas_nama'] ?: '-')) ?></td>
            <td><?= e((string) ($r['mapel_nama'] ?: '-')) ?></td>
            <td><?= e((string) ($jenisList[(string) ($r['jenis'] ?? '')] ?? 'Lainnya')) ?></td>
            <td>
              <?php if (trim((string) ($r['judul'] ?? '')) !== ''): ?><b><?= e((string) $r['judul']) ?></b><br><?php endif; ?>
              <?= nl2br(e((string) ($r['deskripsi'] ?? '-'))) ?>
              <?php if (trim((string) ($r['tindakan'] ?? '')) !== ''): ?>
                <br><span class="mut">Tindakan guru: <?= e((string) $r['tindakan']) ?></span>
              <?php endif; ?>
            </td>
            <td><?= e((string) ($r['guru_nama'] ?: '(tidak tercatat)')) ?></td>
            <td class="ctr"><?= e((string) ($statusList[$statusKasus] ?? 'Baru')) ?></td>
            <td>
              <?php if ($tlJenis !== ''): ?>
                <span class="tl-jenis"><?= e($tlJenis) ?></span>
                <?php if ($tlCatatan !== ''): ?><span class="tl-catatan"><?= nl2br(e($tlCatatan)) ?></span><?php endif; ?>
                <span class="tl-oleh"><?= e((string) ($r['tindak_lanjut_oleh'] ?? '')) ?><?= trim((string) ($r['tindak_lanjut_pada'] ?? '')) !== ''
                  ? ', ' . e(tanggal_id(substr((string) $r['tindak_lanjut_pada'], 0, 10), true)) : '' ?></span>
              <?php else: ?>
                <span class="mut">Belum ditindaklanjuti</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="6" class="ctr">Jumlah</th>
            <th class="ctr"><?= (int) $ringkas['total'] ?> catatan pelanggaran</th>
            <th colspan="2" class="ctr"><?= (int) $ringkas['sudah'] ?> sudah ditindaklanjuti</th>
            <th class="ctr"><?= (int) $ringkas['belum'] ?> belum</th>
          </tr>
        </tfoot>
      </table>
    </div>

    <p class="ket-print no-print">
      Lembar ini memuat SELURUH catatan pelanggaran yang dibuat setiap guru mata pelajaran pada
      sekolah/madrasah ini, beserta tindak lanjut dari guru Tugas Tambahan Kesiswaan.
    </p>

    <!-- Tanda tangan kepala sekolah/madrasah di sisi KANAN (pola lembar cetak lain). -->
    <div class="sheet-foot kanan">
      <?php ttd_blok(ttd_mengetahui(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kanan'); ?>
    </div>
  </div>
</div>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
<?php terjemahkan_selesai(); ?>
