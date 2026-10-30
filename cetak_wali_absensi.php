<?php
/**
 * LEMBAR CETAK REKAP ABSENSI KELAS (A4 landscape) — menu Tugas Tambahan "Wali Kelas".
 *
 * Isinya dua bagian: (1) daftar tanggal absensi yang tercatat (hari + jumlah H/S/I/A), dan
 * (2) rekap kehadiran per siswa (Hadir/Sakit/Izin/Alpa + % kehadiran).
 * Dapat disaring rentang tanggal lewat `?dari=&sampai=`.
 * Tanda tangan: Kepala (kiri) + Wali Kelas (kanan).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/wali.php';

$kelas = wali_cetak_siapkan();
$dari = tgl_impor($_GET['dari'] ?? null) ?? '';
$sampai = tgl_impor($_GET['sampai'] ?? null) ?? '';
if ($dari !== '' && $sampai !== '' && $dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}
$sesi = wali_absensi_list((int) $kelas['id'], $dari, $sampai);
if (!$sesi) {
    flash('Belum ada catatan absensi untuk dicetak pada kelas/periode yang dipilih.', 'warn');
    redirect(wali_url((int) $kelas['id'], 'absensi'));
}
$rekap = wali_absensi_rekap((int) $kelas['id'], $dari, $sampai);
$periode = $dari !== '' || $sampai !== ''
    ? ($dari !== '' && $sampai !== '' ? tanggal_id($dari) . ' s.d. ' . tanggal_id($sampai)
        : ($dari !== '' ? 'Sejak ' . tanggal_id($dari) : 'Sampai ' . tanggal_id($sampai)))
    : 'Seluruh absensi yang tercatat';

wali_cetak_head('Rekap Absensi Kelas', $kelas, 'landscape');
?>
    <?php sheet_meta([
        'Kelas'         => (string) $kelas['nama'],
        'Periode'       => $periode,
        'Hari Tercatat' => count($sesi) . ' hari',
        'Jumlah Siswa'  => count($rekap['siswa']) . ' siswa',
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet wali padat">
        <thead>
          <tr>
            <th class="ctr k-no">No</th>
            <th class="k-tanggal">Hari / Tanggal</th>
            <th class="ctr">Hadir</th>
            <th class="ctr">Sakit</th>
            <th class="ctr">Izin</th>
            <th class="ctr">Alpa</th>
            <th class="ctr k-jml">%</th>
            <th>Catatan</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($sesi as $a): $n++;
            $tot = (int) $a['jml_h'] + (int) $a['jml_s'] + (int) $a['jml_i'] + (int) $a['jml_a'];
            $persen = $tot > 0 ? round((int) $a['jml_h'] * 100 / $tot, 1) : null;
        ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e(hari_id((string) $a['tanggal'])) ?></b>, <?= e(tanggal_id((string) $a['tanggal'])) ?></td>
            <td class="ctr"><?= (int) $a['jml_h'] ?></td>
            <td class="ctr"><?= (int) $a['jml_s'] ?></td>
            <td class="ctr"><?= (int) $a['jml_i'] ?></td>
            <td class="ctr"><?= (int) $a['jml_a'] ?></td>
            <td class="ctr"><?= $persen === null ? '-' : e(r1($persen)) . '%' ?></td>
            <td class="sm"><?= e((string) ($a['catatan'] ?: '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><th colspan="2" class="ctr">Jumlah</th>
            <th class="ctr"><?= (int) array_sum(array_column($sesi, 'jml_h')) ?></th>
            <th class="ctr"><?= (int) array_sum(array_column($sesi, 'jml_s')) ?></th>
            <th class="ctr"><?= (int) array_sum(array_column($sesi, 'jml_i')) ?></th>
            <th class="ctr"><?= (int) array_sum(array_column($sesi, 'jml_a')) ?></th>
            <th colspan="2"><?= count($sesi) ?> hari tercatat</th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="section-title mb8 mt16" style="margin-top:16px"><?= ikon('chart', 15) ?> Rekap Kehadiran per Siswa</div>
    <div class="sheet-scroll">
      <table class="sheet wali padat">
        <thead>
          <tr>
            <th class="ctr k-no">No</th>
            <th class="k-nama">Nama Siswa</th>
            <th class="ctr">Hadir</th>
            <th class="ctr">Sakit</th>
            <th class="ctr">Izin</th>
            <th class="ctr">Alpa</th>
            <th class="ctr k-jml">% Kehadiran</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($rekap['siswa'] as $r): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><?= e((string) $r['nama']) ?><?= (string) $r['nis'] !== '' ? ' <span class="mut xs">(' . e((string) $r['nis']) . ')</span>' : '' ?></td>
            <td class="ctr"><?= (int) $r['H'] ?></td>
            <td class="ctr"><?= (int) $r['S'] ?></td>
            <td class="ctr"><?= (int) $r['I'] ?></td>
            <td class="ctr"><?= (int) $r['A'] ?></td>
            <td class="ctr"><?= $r['persen'] === null ? '-' : e(r1((float) $r['persen'])) . '%' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
<?php
wali_cetak_kaki($kelas, 'Keterangan: absensi kelas dicatat <b>harian</b> (satu tanggal tanpa mata '
    . 'pelajaran). H = Hadir, S = Sakit, I = Izin, A = Alpa; % Kehadiran = Hadir dibagi jumlah hari tercatat.');
