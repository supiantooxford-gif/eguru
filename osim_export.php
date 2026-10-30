<?php
/**
 * EKSPOR DATA OSIM/OSIS (Excel .xlsx dibuat di peramban + CSV dari server) — satu halaman untuk DUA
 * laporan (permintaan pemilik: "Tambahkan fitur impor dan ekspor dan juga cetak"):
 *
 *   osim_export.php?bagian=organisasi  → No, Jabatan / Nama Seksi, Nama
 *   osim_export.php?bagian=program     → No, Nama Program, Waktu Pelaksanaan, Target, Tahun Ajaran,
 *                                        Terlaksana, Keterangan
 *
 * Saringan yang sedang aktif di halaman data ikut dibawa.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/osim.php';

$bagian = (string) ($_GET['bagian'] ?? 'organisasi');
if (!in_array($bagian, ['organisasi', 'program'], true)) {
    $bagian = 'organisasi';
}

// Kode menu dari URL (?kode=pramuka memakai modul yang sama dengan ?kode=osim).
$kode = osim_kode_normal((string) ($_GET['kode'] ?? OSIM_KODE_MENU));
$konfig = osim_menu_konfig($kode);
$tugas = tugas_menu_by_kode($kode);
if (!$tugas) {
    require_login();
    flash('Menu Pembina OSIM/OSIS belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu($tugas);
require_sekolah();

$isOrganisasi = $bagian === 'organisasi';
$f = [
    'cari' => (string) ($_GET['cari'] ?? ''),
    'jabatan' => (string) ($_GET['jabatan'] ?? ''),
    'isi' => (string) ($_GET['isi'] ?? ''),
    'ta' => (string) ($_GET['ta'] ?? ''),
    'terlaksana' => (string) ($_GET['terlaksana'] ?? ''),
    'dari' => (string) ($_GET['dari'] ?? ''),
    'sampai' => (string) ($_GET['sampai'] ?? ''),
];
$pengurus = $isOrganisasi ? osim_pengurus_list($f, $kode) : [];
$program = $isOrganisasi ? [] : osim_program_list($f, $kode);

$KEPALA = $isOrganisasi
    ? ['No', 'Jabatan / Nama Seksi', 'Nama']
    : ['No', 'Nama Program', 'Waktu Pelaksanaan', 'Target', 'Tahun Ajaran', 'Terlaksana', 'Keterangan'];
$data = [];
$n = 0;
if ($isOrganisasi) {
    foreach ($pengurus as $r) {
        $n++;
        $data[] = [$n, (string) $r['jabatan'], (string) ($r['nama'] ?? '')];
    }
} else {
    foreach ($program as $r) {
        $n++;
        $data[] = [$n, (string) $r['nama'], osim_tanggal_label((string) ($r['waktu'] ?? '')),
                   (string) ($r['target'] ?? ''), (string) ($r['ta'] ?? ''),
                   (string) ($r['terlaksana'] ?? ''), (string) ($r['keterangan'] ?? '')];
    }
}

// CSV dilayani server supaya ekspor tetap bekerja walau skrip peramban gagal dimuat.
if (($_GET['unduh'] ?? '') === 'csv') {
    csv_kirim('osim-' . $bagian . '-' . date('Y-m-d') . '.csv', $KEPALA, $data);
}

$qFilter = osim_query($f);
$qTautan = 'kode=' . $kode . '&bagian=' . $bagian . ($qFilter !== '' ? '&' . $qFilter : '');
$labelFilter = osim_label_filter($f, $bagian);
$judul = $isOrganisasi ? 'Susunan Organisasi' : 'Program Kerja';

page_top('Ekspor ' . $judul . ' OSIM/OSIS', 'Pembina OSIM/OSIS');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('doc', 16) ?> Ekspor <?= e($judul) ?> OSIM/OSIS</h2>
      <p>Berkas berisi kolom <?= $isOrganisasi
        ? '<b>No, Jabatan / Nama Seksi, Nama</b>'
        : '<b>No, Nama Program, Waktu Pelaksanaan, Target, Tahun Ajaran, Terlaksana, Keterangan</b>' ?>
        sesuai data yang tampil (saringan ikut dibawa).</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($data) ?> baris</span>
      <a class="btn btn-ghost btn-sm" href="osim_export.php?kode=<?= e($kode) ?>&<?= $isOrganisasi ? 'bagian=program' : 'bagian=organisasi' ?>"><?= ikon('switch', 15) ?> Ekspor <?= $isOrganisasi ? 'Program Kerja' : 'Susunan Organisasi' ?></a>
      <a class="btn btn-ghost btn-sm" href="tugas.php?kode=<?= e($kode) ?>&bagian=<?= e($bagian) ?>&tab=data<?= $qFilter !== '' ? '&' . e($qFilter) : '' ?>"><?= ikon('clipboard', 15) ?> Kembali ke Data</a>
    </div>
  </div>

  <?php if (!$data): ?>
    <div class="empty"><?= ikon('folder', 34) ?>
      <h3>Belum ada data untuk diekspor</h3>
      <p>Isi data terlebih dahulu, atau ubah/bersihkan saringan.</p>
      <div class="flex" style="justify-content:center">
        <a class="btn btn-primary" href="tugas.php?kode=<?= e($kode) ?>&bagian=<?= e($bagian) ?>&tab=data"><?= ikon('plus', 16) ?> Input Data</a>
        <a class="btn btn-ghost" href="osim_import.php?bagian=<?= e($bagian) ?>"><?= ikon('upload', 16) ?> Impor Excel/CSV</a>
      </div>
    </div>
  <?php else: ?>
    <?php if ($labelFilter): ?>
      <div class="chip-list mb16">
        <?php foreach ($labelFilter as $l): ?><span class="chip"><?= e($l) ?></span><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="grid g2" style="gap:16px">
      <div class="card" style="margin:0">
        <div class="card-head"><div><h2>1. Unduh berkas</h2><p>Pilih format yang diinginkan.</p></div></div>
        <div class="flex">
          <button class="btn btn-primary" type="button" id="btnExcel"><?= ikon('doc', 16) ?> Unduh Excel (.xlsx)</button>
          <a class="btn btn-ghost" href="osim_export.php?unduh=csv&<?= e($qTautan) ?>"><?= ikon('download', 16) ?> Unduh CSV</a>
        </div>
        <p class="hint" id="statusExcel" style="margin-bottom:0">Berkas Excel dibuat di peramban. Bila tombol Excel tidak bekerja,
          gunakan <b>Unduh CSV</b>.</p>
      </div>
      <div class="card" style="margin:0">
        <div class="card-head"><div><h2>2. Cetak / PDF</h2><p>Lembar A4 untuk arsip atau lampiran.</p></div></div>
        <div class="flex">
          <a class="btn btn-ghost" href="<?= $isOrganisasi ? 'cetak_osim_organisasi.php' : 'cetak_osim_program.php' ?>?<?= e(http_build_query(array_filter(array_merge(['kode' => $kode], $f)))) ?>" target="_blank"><?= ikon('print', 16) ?> Cetak <?= e($judul) ?></a>
        </div>
        <p class="hint" style="margin-bottom:0">Lembar cetak memuat kop sekolah/madrasah dan tanda tangan kepala.</p>
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
        <tfoot><tr>
          <th colspan="<?= count($KEPALA) - 1 ?>" class="ctr">Jumlah</th>
          <th class="ctr"><?= count($data) ?> baris</th>
        </tr></tfoot>
      </table>
    </div>
    <?php if (count($data) > 25): ?>
      <p class="xs mut mt8">Menampilkan 25 dari <?= count($data) ?> baris. Berkas ekspor memuat SELURUH data.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script id="dataEkspor" type="application/json"><?= json_encode([
    'kepala' => $KEPALA,
    'baris' => $data,
    'nama' => $judul . ' OSIM/OSIS ' . nama_sekolah_tampil(),
    'bagian' => $bagian,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/vendor/xlsx.full.min.js"></script>
<script>
(function () {
  var btn = document.getElementById('btnExcel');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var status = document.getElementById('statusExcel');
    try {
      var muatan = JSON.parse(document.getElementById('dataEkspor').textContent);
      var aoa = [muatan.kepala].concat(muatan.baris);
      var ws = XLSX.utils.aoa_to_sheet(aoa);
      var wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, muatan.bagian === 'organisasi' ? 'Susunan Organisasi' : 'Program Kerja');
      XLSX.writeFile(wb, 'osim-' + muatan.bagian + '-<?= e(date('Y-m-d')) ?>.xlsx');
      if (status) status.textContent = 'Berkas Excel dibuat: ' + muatan.baris.length + ' baris.';
    } catch (e) {
      if (status) status.textContent = 'Gagal membuat berkas Excel (' + e.message + '). Gunakan Unduh CSV.';
    }
  });
})();
</script>
<?php
page_bottom();
