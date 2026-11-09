<?php
/**
 * LEMBAR CETAK SETORAN HAFALAN — menu Tugas Tambahan "Pembina Tahfidz".
 *
 * Permintaan pemilik: *"Tambahkan fitur cetak dan hasil cetak seperti contoh. Tambahkan tanda tangan
 * Pembina tahfidz dan mengetahui kepala madrasah/sekolah."* — contoh yang dilampirkan berbentuk
 * tabel dengan kolom **No · Nama Siswa · Kelas · Setor Hafalan (Nama Surah · Dari Ayat ke- ·
 * Sampai Ayat ke-) · Keterangan**.
 *
 * DUA MODE (pilihan pemilik):
 *   ?mode=kelas    (bawaan) — satu lembar per KELAS berisi SELURUH siswa kelas itu; sel setoran
 *                    dibiarkan KOSONG bila siswa belum menyetor (persis seperti contoh, bisa dipakai
 *                    sebagai lembar isian). Setoran yang ditampilkan = setoran TERAKHIR siswa pada
 *                    rentang tanggal yang dipilih.
 *   ?mode=rincian  — satu tabel berisi SEMUA setoran pada rentang tanggal/filter yang dipilih,
 *                    dengan kolom Tanggal tambahan.
 *
 * Saringan yang dipakai di halaman data ikut terbawa: `kelas`, `dari`, `sampai`, `cari`.
 * Tanda tangan mengikuti aturan SELURUH lembar cetak e-GURU (permintaan pemilik: "kepala
 * sekolah/madrasah di KIRI, guru di KANAN"): **Mengetahui Kepala Sekolah/Madrasah (KIRI)** dan
 * **Pembina Tahfidz (KANAN)** dengan baris "kecamatan, tanggal" di ATAS nama pembina. Blok kiri
 * memakai `ttd_mengetahui()` (dua baris: "Mengetahui," + jabatan kepala) sehingga NAMA kedua
 * penanda tangan tercetak SEJAJAR.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/tahfidz.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$kode = trim((string) ($_GET['kode'] ?? ''));
$tugas = $kode !== '' ? tugas_menu_by_kode($kode) : null;
if (!$tugas || !tahfidz_tugas_kunci($tugas)) {
    require_login();
    flash('Menu Pembina Tahfidz belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu($tugas);
require_sekolah();

$namaMenu = (string) $tugas['nama'];
$mode = (string) ($_GET['mode'] ?? 'kelas');
if (!in_array($mode, ['kelas', 'rincian'], true)) {
    $mode = 'kelas';
}
$f = [
    'kelas'  => (int) ($_GET['kelas'] ?? 0),
    'dari'   => (string) ($_GET['dari'] ?? ''),
    'sampai' => (string) ($_GET['sampai'] ?? ''),
    'cari'   => (string) ($_GET['cari'] ?? ''),
];
$dari = tgl_ok($f['dari']) ?? '';
$sampai = tgl_ok($f['sampai']) ?? '';
if ($dari !== '' && $sampai !== '' && $dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}
$f['dari'] = $dari;
$f['sampai'] = $sampai;

$labelFilter = tahfidz_label_filter($f);
$autoPrint = isset($_GET['auto']);

// ------------------------------------------------------------------ data sesuai mode
$kelasDipilih = $f['kelas'] > 0 ? kelas_by_id((int) $f['kelas']) : null;
if ($mode === 'kelas') {
    // Satu lembar per kelas: kelas yang dipilih, atau SELURUH kelas aktif bila tidak dipilih.
    $daftarKelas = $kelasDipilih ? [$kelasDipilih] : kelas_list(true);
    $lembar = [];
    foreach ($daftarKelas as $k) {
        $murid = siswa_kelas((int) $k['id'], true);
        // Urut nama (absen kelas biasanya urut nama) — sudah diurutkan `siswa_kelas()`.
        $terakhir = tahfidz_terakhir_map(array_column($murid, 'id'), $dari, $sampai);
        $lembar[] = ['kelas' => $k, 'murid' => $murid, 'setoran' => $terakhir];
    }
    $lembar = array_values(array_filter($lembar, static fn(array $l): bool => $l['murid'] !== []));
    if (!$lembar) {
        flash('Belum ada kelas dengan siswa aktif yang bisa dicetak pada sekolah/madrasah ini.', 'warn');
        redirect('tugas.php?kode=' . urlencode($kode));
    }
    $judul = 'Daftar Setoran Hafalan Siswa';
} else {
    $baris = tahfidz_setoran_list($f);
    if (!$baris) {
        flash('Belum ada setoran hafalan untuk dicetak pada saringan yang dipilih.', 'warn');
        redirect('tugas.php?kode=' . urlencode($kode));
    }
    $judul = 'Rincian Setoran Hafalan Siswa';
}
$ringkas = $mode === 'rincian' ? tahfidz_ringkas($baris) : tahfidz_ringkas(array_merge(
    ...array_map(static fn(array $l): array => array_values($l['setoran']), $lembar)));
$periode = ($dari !== '' && $sampai !== '')
    ? ($dari === $sampai ? tanggal_id($dari) : tanggal_id($dari) . ' s.d. ' . tanggal_id($sampai))
    : (($dari !== '') ? 'Sejak ' . tanggal_id($dari)
        : (($sampai !== '') ? 'Sampai ' . tanggal_id($sampai) : 'Seluruh tanggal yang tercatat'));

// Pembina Tahfidz: bisa dipilih pada toolbar (untuk sekolah dengan lebih dari satu pembina).
$guruList = tahfidz_guru_list();
$pembina = tahfidz_pembina($tugas, (int) ($_GET['pembina'] ?? 0));

$qDasar = array_filter([
    'kode' => $kode, 'mode' => $mode,
    'kelas' => (int) $f['kelas'] > 0 ? (int) $f['kelas'] : '',
    'dari' => $dari, 'sampai' => $sampai, 'cari' => trim($f['cari']),
    'pembina' => (int) ($_GET['pembina'] ?? 0) > 0 ? (int) $_GET['pembina'] : '',
], static fn($v): bool => (string) $v !== '');
$qAuto = $qDasar;
$qAuto['auto'] = 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:880px;margin:0 auto}
  .toolbar{max-width:880px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;align-items:flex-end}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table.sheet.tahfidz th,table.sheet.tahfidz td{padding:4px 6px;font-size:.76rem;line-height:1.3}
  table.sheet.tahfidz th{font-size:.68rem;letter-spacing:0;vertical-align:middle;text-align:center}
  table.sheet.tahfidz .k-no{width:36px}
  table.sheet.tahfidz .k-nama{width:26%}
  table.sheet.tahfidz .k-kelas{width:11%}
  table.sheet.tahfidz .k-ayat{width:11%}
  table.sheet.tahfidz .k-ket{width:20%}
  /* Halaman cetak per kelas dipenggal per kelas (satu lembar satu kelas). */
  .lap-halaman + .lap-halaman{margin-top:22px}
  @media screen and (max-width:760px){
    .print-sheet{padding:18px 14px}
    table.sheet.tahfidz{min-width:600px}
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
    table.sheet.tahfidz{min-width:0}
    table.sheet.tahfidz thead{display:table-header-group}
    table.sheet.tahfidz tr{break-inside:avoid}
    .lap-halaman{break-inside:avoid}
    .lap-halaman + .lap-halaman{break-before:page;margin-top:0}
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap;justify-content:space-between}
    .sheet-foot.dua-ttd .sheet-sign{flex:1 1 0;min-width:0}
  }
  @page{size:A4 portrait;margin:13mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="tugas.php?kode=<?= e($kode) ?>">Kembali</a>
  <?php // Dua mode cetak dari satu menu: lembar per kelas & rincian semua setoran. ?>
  <a class="btn <?= $mode === 'kelas' ? 'btn-primary' : 'btn-ghost' ?>"
     href="cetak_tahfidz.php?<?= e(http_build_query(array_merge($qDasar, ['mode' => 'kelas']))) ?>">Lembar per Kelas</a>
  <a class="btn <?= $mode === 'rincian' ? 'btn-primary' : 'btn-ghost' ?>"
     href="cetak_tahfidz.php?<?= e(http_build_query(array_merge($qDasar, ['mode' => 'rincian']))) ?>">Rincian Setoran</a>
  <?php if ($guruList): ?>
    <form method="get" class="flex" style="gap:6px;align-items:flex-end">
      <?php foreach ($qDasar as $kk => $vv): ?>
        <?php if ($kk !== 'pembina'): ?><input type="hidden" name="<?= e((string) $kk) ?>" value="<?= e((string) $vv) ?>"><?php endif; ?>
      <?php endforeach; ?>
      <div>
        <label class="f" for="pembinaPilih">Pembina Tahfidz</label>
        <select id="pembinaPilih" name="pembina" data-auto>
          <option value="0">— otomatis —</option>
          <?php foreach ($guruList as $g): ?>
            <option value="<?= (int) $g['id'] ?>" <?= (int) ($_GET['pembina'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>>
              <?= e((string) $g['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  <?php endif; ?>
  <a class="btn btn-primary" href="cetak_tahfidz.php?<?= e(http_build_query($qAuto)) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
  <?php if ($mode === 'kelas'): ?>
    <?php foreach ($lembar as $idx => $l): ?>
      <?php $jumlahSetor = count($l['setoran']); ?>
      <div class="lap-halaman">
        <?php kop_cetak($judul, [$namaMenu . ' ' . istilah_sekolah('Sekolah')]); ?>
        <?php sheet_meta([
            'Kelas'        => (string) $l['kelas']['nama'],
            'Periode'      => $periode,
            'Jumlah Siswa' => count($l['murid']) . ' siswa',
            'Sudah Setor'  => $jumlahSetor . ' siswa',
            'Saringan'     => $labelFilter ? implode(' · ', $labelFilter) : 'Seluruh kelas',
        ]); ?>

        <div class="sheet-scroll">
          <table class="sheet tahfidz padat" id="tblTahfidzKelas<?= (int) $l['kelas']['id'] ?>">
            <thead>
              <tr>
                <th class="ctr k-no" rowspan="2">No</th>
                <th class="k-nama" rowspan="2">Nama Siswa</th>
                <th class="ctr k-kelas" rowspan="2">Kelas</th>
                <th class="ctr" colspan="3">Setor Hafalan</th>
                <th class="k-ket" rowspan="2">Keterangan</th>
              </tr>
              <tr>
                <th class="k-nama">Nama Surah</th>
                <th class="ctr k-ayat">Dari Ayat ke-</th>
                <th class="ctr k-ayat">Sampai Ayat ke-</th>
              </tr>
            </thead>
            <tbody>
            <?php $n = 0; foreach ($l['murid'] as $sw): $n++;
                $s = $l['setoran'][(int) $sw['id']] ?? null; ?>
              <tr>
                <td class="ctr"><?= $n ?></td>
                <td><?= e((string) $sw['nama']) ?></td>
                <td class="ctr"><?= e((string) $l['kelas']['nama']) ?></td>
                <td><?= $s ? e((string) $s['surah_nama']) : '' ?></td>
                <td class="ctr"><?= $s ? (int) $s['ayat_dari'] : '' ?></td>
                <td class="ctr"><?= $s ? (int) $s['ayat_sampai'] : '' ?></td>
                <td class="sm"><?= $s ? e((string) ($s['keterangan'] ?: '-')) : '' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="ket-print">
          Keterangan: lembar ini memuat <b>seluruh siswa kelas <?= e((string) $l['kelas']['nama']) ?></b>.
          Sel <b>Setor Hafalan</b> yang kosong berarti siswa belum menyetor pada periode tersebut.
          Nama surah &amp; jumlah ayat mengikuti mushaf Al-Qur'an (114 surah).
        </div>

        <?php // Tanda tangan: Mengetahui Kepala (KIRI) + Pembina Tahfidz (KANAN) — aturan lembar cetak e-GURU. ?>
        <div class="sheet-foot dua-ttd">
          <?php ttd_blok(ttd_mengetahui(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kiri'); ?>
          <?php ttd_blok('Pembina Tahfidz', $pembina['nama'], $pembina['nip'], 'sheet-sign sheet-sign-kanan', true); ?>
        </div>

        <p class="xs mut center" style="margin-top:14px">
          Dicetak dari <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> pada <?= e(tanggal_id(date('Y-m-d'))) ?>.
        </p>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="lap-halaman">
      <?php kop_cetak($judul, [$namaMenu . ' ' . istilah_sekolah('Sekolah')]); ?>
      <?php sheet_meta([
          'Periode'      => $periode,
          'Jumlah'       => (int) $ringkas['setoran'] . ' setoran, ' . (int) $ringkas['siswa'] . ' siswa',
          'Kelas'        => $kelasDipilih ? (string) $kelasDipilih['nama'] : 'Seluruh kelas',
          'Saringan'     => $labelFilter ? implode(' · ', $labelFilter) : 'Seluruh setoran tercatat',
      ]); ?>

      <div class="sheet-scroll">
        <table class="sheet tahfidz padat" id="tblTahfidzRincian">
          <thead>
            <tr>
              <th class="ctr k-no">No</th>
              <th class="ctr" style="width:13%">Tanggal</th>
              <th class="k-nama">Nama Siswa</th>
              <th class="ctr k-kelas">Kelas</th>
              <th>Nama Surah</th>
              <th class="ctr k-ayat">Dari Ayat ke-</th>
              <th class="ctr k-ayat">Sampai Ayat ke-</th>
              <th class="k-ket">Keterangan</th>
            </tr>
          </thead>
          <tbody>
          <?php $n = 0; foreach ($baris as $r): $n++; ?>
            <tr>
              <td class="ctr"><?= $n ?></td>
              <td class="ctr xs"><?= e(tanggal_id((string) $r['tanggal'])) ?></td>
              <td><?= e((string) $r['siswa_nama']) ?></td>
              <td class="ctr"><?= e((string) ($r['kelas_nama'] ?: '-')) ?></td>
              <td><?= e((string) $r['surah_nama']) ?></td>
              <td class="ctr"><?= (int) $r['ayat_dari'] ?></td>
              <td class="ctr"><?= (int) $r['ayat_sampai'] ?></td>
              <td class="sm"><?= e((string) ($r['keterangan'] ?: '-')) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="7" class="ctr">Jumlah</th>
              <th><?= (int) $ringkas['setoran'] ?> setoran (<?= (int) $ringkas['siswa'] ?> siswa,
                <?= (int) $ringkas['kelas'] ?> kelas)</th>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="ket-print">
        Keterangan: satu siswa dicatat <b>satu setoran per tanggal</b>; rentang ayat mengikuti jumlah
        ayat surah yang dipilih (114 surah). Nama surah &amp; jumlah ayat mengikuti mushaf Al-Qur'an.
      </div>

      <div class="sheet-foot dua-ttd">
        <?php ttd_blok(ttd_mengetahui(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kiri'); ?>
        <?php ttd_blok('Pembina Tahfidz', $pembina['nama'], $pembina['nip'], 'sheet-sign sheet-sign-kanan', true); ?>
      </div>

      <p class="xs mut center" style="margin-top:14px">
        Dicetak dari <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> pada <?= e(tanggal_id(date('Y-m-d'))) ?>.
      </p>
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
