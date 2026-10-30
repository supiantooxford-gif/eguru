<?php
/**
 * LEMBAR CETAK PROGRAM KERJA WALI KELAS (A4 landscape) — menu Tugas Tambahan "Wali Kelas".
 * Kolomnya SAMA dengan Laporan Program Kerja Pembina OSIM: No, Nama Program, Waktu Pelaksanaan,
 * Target, Tahun Ajaran, Terlaksana, Keterangan + ringkasan keterlaksanaan.
 * Tanda tangan: Kepala (kiri) + Wali Kelas (kanan).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/wali.php';

$kelas = wali_cetak_siapkan();
$f = [
    'cari' => (string) ($_GET['cari'] ?? ''),
    'ta' => (string) ($_GET['ta'] ?? ''),
    'terlaksana' => (string) ($_GET['terlaksana'] ?? ''),
];
$baris = wali_program_list((int) $kelas['id'], $f);
if (!$baris) {
    flash('Belum ada program kerja untuk dicetak pada saringan/kelas yang dipilih.', 'warn');
    redirect(wali_url((int) $kelas['id'], 'program'));
}
$ringkas = wali_ringkas_program($baris);
$labelFilter = [];
if ($f['ta'] !== '') {
    $labelFilter[] = 'Tahun Ajaran: ' . $f['ta'];
}
if ($f['terlaksana'] !== '') {
    $labelFilter[] = 'Terlaksana: ' . $f['terlaksana'];
}
if ($f['cari'] !== '') {
    $labelFilter[] = 'Kata kunci: ' . $f['cari'];
}

wali_cetak_head('Program Kerja Wali Kelas', $kelas, 'landscape');
?>
    <?php sheet_meta([
        'Kelas'          => (string) $kelas['nama'],
        'Jumlah Program' => (int) $ringkas['program'] . ' program',
        'Realisasi'      => 'Terlaksana: ' . (int) $ringkas['ya'] . ', Belum: ' . (int) $ringkas['tidak']
            . ((int) $ringkas['belum'] > 0 ? ', Belum ditentukan: ' . (int) $ringkas['belum'] : ''),
        'Saringan'       => $labelFilter ? implode(' · ', $labelFilter) : 'Seluruh program kerja',
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet wali padat">
        <thead>
          <tr>
            <th class="ctr k-no">No</th>
            <th class="k-nama">Nama Program</th>
            <th class="k-waktu">Waktu Pelaksanaan</th>
            <th class="k-target">Target</th>
            <th class="ctr k-ta">Tahun Ajaran</th>
            <th class="ctr k-ter">Terlaksana</th>
            <th class="k-ket">Keterangan</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($baris as $r): $n++; $ter = wali_terlaksana_sah((string) ($r['terlaksana'] ?? '')); ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e((string) $r['nama']) ?></b></td>
            <td class="sm"><?= trim((string) ($r['waktu'] ?? '')) !== '' ? e(wali_tanggal_label((string) $r['waktu'])) : '-' ?></td>
            <td class="sm"><?= e((string) ($r['target'] ?: '-')) ?></td>
            <td class="ctr"><?= e((string) ($r['ta'] ?: '-')) ?></td>
            <td class="ctr"><?= $ter !== '' ? e($ter) : '-' ?></td>
            <td class="sm"><?= e((string) ($r['keterangan'] ?: '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="5" class="ctr">Jumlah</th>
            <th class="ctr"><?= (int) $ringkas['ya'] ?> terlaksana</th>
            <th><?= count($baris) ?> program</th>
          </tr>
        </tfoot>
      </table>
    </div>
<?php
wali_cetak_kaki($kelas, 'Keterangan: program kerja wali kelas. Status realisasi dicatat '
    . '<b>Terlaksana</b> atau <b>Tidak</b>; program yang belum ditentukan statusnya ditandai "-".');
