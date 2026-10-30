<?php
/**
 * EKSPOR DATA WALI KELAS (Excel .xlsx dibuat di peramban + CSV dari server).
 *
 * Permintaan pemilik (seperti Pembina OSIM): "Tambahkan fitur impor dan ekspor".
 *   wali_export.php?kelas=ID&bagian=struktur  → No, Jabatan / Nama Seksi, Nama
 *   wali_export.php?kelas=ID&bagian=program   → No, Nama Program, Waktu Pelaksanaan, Target,
 *                                               Tahun Ajaran, Terlaksana, Keterangan
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/wali.php';

$bagian = (string) ($_GET['bagian'] ?? 'struktur');
if (!in_array($bagian, ['struktur', 'program'], true)) {
    $bagian = 'struktur';
}
$tugas = tugas_menu_by_kode(WALI_KODE_MENU);
if (!$tugas) {
    require_login();
    flash('Menu Wali Kelas belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu($tugas);
require_sekolah();
if (is_pengawas()) {
    tolak403('Akun Pengawas Sekolah/Madrasah tidak mengunduh data (hanya melihat & mencetak).');
}
$kelas = wali_kelas_konteks((int) ($_GET['kelas'] ?? 0));
if (!$kelas) {
    flash('Belum ada kelas yang bisa diekspor.', 'warn');
    redirect('tugas.php?kode=' . WALI_KODE_MENU);
}
$kelasId = (int) $kelas['id'];
$isStruktur = $bagian === 'struktur';
$f = ['cari' => (string) ($_GET['cari'] ?? ''), 'ta' => (string) ($_GET['ta'] ?? ''),
      'terlaksana' => (string) ($_GET['terlaksana'] ?? '')];

$KEPALA = $isStruktur
    ? ['No', 'Jabatan / Nama Seksi', 'Nama', 'Baris Bagan']
    : ['No', 'Nama Program', 'Waktu Pelaksanaan', 'Target', 'Tahun Ajaran', 'Terlaksana', 'Keterangan'];
$data = [];
$n = 0;
if ($isStruktur) {
    foreach (wali_struktur_list($kelasId) as $r) {
        $n++;
        $data[] = [$n, (string) $r['jabatan'], (string) ($r['nama'] ?? ''), wali_bagan_tingkat($r)];
    }
} else {
    foreach (wali_program_list($kelasId, $f) as $r) {
        $n++;
        $data[] = [$n, (string) $r['nama'], wali_tanggal_label((string) ($r['waktu'] ?? '')),
                   (string) ($r['target'] ?? ''), (string) ($r['ta'] ?? ''),
                   (string) ($r['terlaksana'] ?? ''), (string) ($r['keterangan'] ?? '')];
    }
}

if (($_GET['unduh'] ?? '') === 'csv') {
    csv_kirim('wali-' . $bagian . '-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $kelas['nama'])
        . '-' . date('Y-m-d') . '.csv', $KEPALA, $data);
}

$judul = $isStruktur ? 'Struktur Organisasi Kelas' : 'Program Kerja Wali Kelas';
page_top('Ekspor ' . $judul, 'Wali Kelas &middot; ' . (string) $kelas['nama']);
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('doc', 16) ?> Ekspor <?= e($judul) ?></h2>
      <p>Kelas <b><?= e((string) $kelas['nama']) ?></b> &middot; kolom
        <?= $isStruktur ? '<b>No, Jabatan / Nama Seksi, Nama, Baris Bagan</b>'
            : '<b>No, Nama Program, Waktu Pelaksanaan, Target, Tahun Ajaran, Terlaksana, Keterangan</b>' ?>.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($data) ?> baris</span>
      <a class="btn btn-ghost btn-sm" href="wali_export.php?kelas=<?= $kelasId ?>&bagian=<?= $isStruktur ? 'program' : 'struktur' ?>"><?= ikon('switch', 15) ?> Ekspor <?= $isStruktur ? 'Program Kerja' : 'Struktur Organisasi' ?></a>
      <a class="btn btn-ghost btn-sm" href="<?= e(wali_url($kelasId, $isStruktur ? 'struktur' : 'program')) ?>"><?= ikon('clipboard', 15) ?> Kembali ke Data</a>
    </div>
  </div>

  <?php if (!$data): ?>
    <div class="empty"><?= ikon('folder', 34) ?>
      <h3>Belum ada data untuk diekspor</h3>
      <p>Isi datanya terlebih dahulu, atau ubah saringan.</p>
      <div class="flex" style="justify-content:center">
        <a class="btn btn-primary" href="<?= e(wali_url($kelasId, $isStruktur ? 'struktur' : 'program')) ?>"><?= ikon('plus', 16) ?> Input Data</a>
        <a class="btn btn-ghost" href="wali_import.php?kelas=<?= $kelasId ?>&bagian=<?= e($bagian) ?>"><?= ikon('upload', 16) ?> Impor Excel/CSV</a>
      </div>
    </div>
  <?php else: ?>
    <div class="grid g2" style="gap:16px">
      <div class="card" style="margin:0">
        <div class="card-head"><div><h2>1. Unduh berkas</h2><p>Pilih format yang diinginkan.</p></div></div>
        <div class="flex">
          <button class="btn btn-primary" type="button" id="btnExcel"><?= ikon('doc', 16) ?> Unduh Excel (.xlsx)</button>
          <a class="btn btn-ghost" href="wali_export.php?unduh=csv&kelas=<?= $kelasId ?>&bagian=<?= e($bagian) ?>"><?= ikon('download', 16) ?> Unduh CSV</a>
        </div>
        <p class="hint" id="statusExcel" style="margin-bottom:0">Berkas Excel dibuat di peramban. Bila tombol Excel tidak bekerja, gunakan <b>Unduh CSV</b>.</p>
      </div>
      <div class="card" style="margin:0">
        <div class="card-head"><div><h2>2. Cetak / PDF</h2><p>Lembar A4 untuk arsip atau lampiran.</p></div></div>
        <div class="flex">
          <a class="btn btn-ghost" href="<?= $isStruktur ? 'cetak_wali_struktur.php' : 'cetak_wali_program.php' ?>?kelas=<?= $kelasId ?>" target="_blank"><?= ikon('print', 16) ?> Cetak <?= e($judul) ?></a>
          <?php if ($isStruktur): ?>
            <a class="btn btn-ghost" href="cetak_wali_bagan.php?kelas=<?= $kelasId ?>" target="_blank"><?= ikon('print', 16) ?> Cetak Bagan</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="divider"></div>
    <div class="section-title mb8"><?= ikon('clipboard', 15) ?> Pratinjau 25 baris pertama</div>
    <div class="table-wrap">
      <table class="tbl tbl-impor">
        <thead><tr><?php foreach ($KEPALA as $i => $h): ?><th<?= $i === 0 ? ' class="ctr"' : '' ?>><?= e($h) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach (array_slice($data, 0, 25) as $row): ?>
          <tr><?php foreach ($row as $i => $v): ?><td<?= $i === 0 ? ' class="ctr"' : '' ?>><?= e((string) ($v === '' ? '-' : $v)) ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><th colspan="<?= count($KEPALA) - 1 ?>" class="ctr">Jumlah</th>
          <th class="ctr"><?= count($data) ?> baris</th></tr></tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<script id="dataEkspor" type="application/json"><?= json_encode([
    'kepala' => $KEPALA, 'baris' => $data, 'bagian' => $bagian,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/vendor/xlsx.full.min.js"></script>
<script>
(function () {
  var btn = document.getElementById('btnExcel');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var status = document.getElementById('statusExcel');
    try {
      var m = JSON.parse(document.getElementById('dataEkspor').textContent);
      var ws = XLSX.utils.aoa_to_sheet([m.kepala].concat(m.baris));
      var wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, m.bagian === 'struktur' ? 'Struktur Kelas' : 'Program Kerja');
      XLSX.writeFile(wb, 'wali-' + m.bagian + '-<?= e(date('Y-m-d')) ?>.xlsx');
      if (status) status.textContent = 'Berkas Excel dibuat: ' + m.baris.length + ' baris.';
    } catch (e) {
      if (status) status.textContent = 'Gagal membuat berkas Excel (' + e.message + '). Gunakan Unduh CSV.';
    }
  });
})();
</script>
<?php
page_bottom();
