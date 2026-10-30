<?php
/**
 * LEMBAR CETAK STRUKTUR ORGANISASI KELAS (A4 potrait) — menu Tugas Tambahan "Wali Kelas".
 * Permintaan pemilik: "Masing-masing tambahkan menu cetak dokumen."
 * Kolom: No, Jabatan / Nama Seksi, Nama. Tanda tangan: Kepala (kiri) + Wali Kelas (kanan).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/wali.php';

$kelas = wali_cetak_siapkan();
$baris = wali_struktur_list((int) $kelas['id']);
if (!$baris) {
    flash('Struktur organisasi kelas itu belum diisi, jadi belum ada yang bisa dicetak.', 'warn');
    redirect(wali_url((int) $kelas['id'], 'struktur'));
}
$stat = wali_bagan_statistik(wali_bagan_susun($baris));

wali_cetak_head('Struktur Organisasi Kelas', $kelas, 'portrait');
?>
    <?php sheet_meta([
        'Kelas'             => (string) $kelas['nama'],
        'Wali Kelas'        => trim((string) ($kelas['wali_nama'] ?? '')) ?: (wali_penandatangan($kelas)['nama'] ?: '-'),
        'Jumlah Baris'      => count($baris) . ' baris',
        'Nama Terisi'       => (int) $stat['nama'] . ' nama',
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet wali padat">
        <thead>
          <tr>
            <th class="ctr k-no">No</th>
            <th class="k-jabatan">Jabatan / Nama Seksi</th>
            <th class="k-nama">Nama</th>
            <th class="ctr k-bagan">Baris Bagan</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($baris as $b): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e((string) $b['jabatan']) ?></b></td>
            <td><?= trim((string) ($b['nama'] ?? '')) !== '' ? e((string) $b['nama']) : '-' ?></td>
            <td class="ctr"><?= (int) wali_bagan_tingkat($b) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><th colspan="2" class="ctr">Jumlah</th>
            <th><?= count($baris) ?> baris</th>
            <th class="ctr"><?= (int) $stat['nama'] ?> nama</th></tr>
        </tfoot>
      </table>
    </div>
<?php
wali_cetak_kaki($kelas, 'Keterangan: susunan pengurus kelas beserta wali kelasnya. Kolom '
    . '<b>Baris Bagan</b> adalah tingkat pada gambar bagan struktur organisasi kelas.');
