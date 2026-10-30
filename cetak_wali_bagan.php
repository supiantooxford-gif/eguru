<?php
/**
 * LEMBAR CETAK BAGAN STRUKTUR ORGANISASI KELAS (A4 landscape) — menu Tugas Tambahan "Wali Kelas".
 * Digambar dari baris Struktur Organisasi kelas; tanda tangan Kepala (kiri) + Wali Kelas (kanan).
 * Tata letaknya sengaja dipadatkan saat mencetak supaya muat SATU halaman.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/wali.php';

$kelas = wali_cetak_siapkan();
$baris = wali_struktur_list((int) $kelas['id']);
if (!$baris) {
    flash('Struktur organisasi kelas itu belum diisi, jadi bagannya belum bisa dicetak.', 'warn');
    redirect(wali_url((int) $kelas['id'], 'struktur'));
}
$stat = wali_bagan_statistik(wali_bagan_susun($baris));

wali_cetak_head('Bagan Struktur Organisasi Kelas', $kelas, 'landscape');
?>
    <div class="bagan-identitas">
      <span><b>Kelas</b>: <?= e((string) $kelas['nama']) ?></span>
      <span><b>Wali Kelas</b>: <?= e(trim((string) ($kelas['wali_nama'] ?? '')) ?: (wali_penandatangan($kelas)['nama'] ?: '-')) ?></span>
      <span><b>Jumlah Nama</b>: <?= (int) $stat['nama'] ?></span>
    </div>

    <div class="osim-bagan-luapan">
      <?= wali_bagan_html($baris, $kelas, true) ?>
    </div>
<?php
wali_cetak_kaki($kelas, '');
