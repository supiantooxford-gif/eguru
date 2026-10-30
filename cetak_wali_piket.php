<?php
/**
 * LEMBAR CETAK JADWAL PIKET KELAS (A4 potrait) — menu Tugas Tambahan "Wali Kelas".
 *
 * Permintaan pemilik (v1.69.2): bentuknya dibuat seperti contoh yang dikirim — KARTU PER HARI
 * (tiga kolom) berisi nama-nama siswa piket, TANPA gambar kartun dan TANPA kotak "CATATAN".
 * Hari tetap mengikuti wali_hari() (Senin–Minggu), jadi Minggu ikut tercetak.
 * Tanda tangan: Kepala Sekolah/Madrasah (kiri) + Wali Kelas (kanan).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/wali.php';

$kelas = wali_cetak_siapkan();
$piket = wali_piket_jadwal((int) $kelas['id']);
$jumlah = wali_piket_jumlah($piket);
if ($jumlah === 0) {
    flash('Jadwal piket kelas itu belum diisi, jadi belum ada yang bisa dicetak.', 'warn');
    redirect(wali_url((int) $kelas['id'], 'piket'));
}
$hariTerisi = 0;
$hariTampil = [];
foreach ($piket as $hari => $daftar) {
    // Permintaan pemilik: kartu hari yang TIDAK ada siswa piketnya jangan dicetak — hanya hari
    // yang benar-benar ada petugasnya yang tampil.
    if ($daftar) {
        $hariTerisi++;
        $hariTampil[$hari] = $daftar;
    }
}

wali_cetak_head('Jadwal Piket Kelas', $kelas, 'portrait');
?>
    <?php sheet_meta([
        'Kelas'        => (string) $kelas['nama'],
        'Jumlah Siswa' => (int) $jumlah . ' penugasan piket',
        'Hari Terisi'  => $hariTerisi . ' dari ' . count(wali_hari()) . ' hari',
    ]); ?>

    <?php // Kartu per hari (3 kolom) — bentuknya mengikuti contoh dari pemilik. Tidak ada gambar
          // kartun maupun kotak catatan; isinya murni nama siswa yang bertugas piket.
          // HANYA hari yang punya petugas piket yang dicetak (hari kosong tidak dibuatkan kartu). ?>
    <div class="piket-cetak-grid">
      <?php foreach ($hariTampil as $h => $daftarPiket): ?>
        <div class="piket-cetak-kartu">
          <div class="piket-cetak-hari"><?= e(strtoupper((string) $h)) ?></div>
          <div class="piket-cetak-isi">
            <ul>
              <?php foreach ($daftarPiket as $p): ?>
                <li><?= e((string) $p['nama']) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
<?php
wali_cetak_kaki($kelas, 'Keterangan: nama siswa yang bertugas piket kebersihan kelas pada setiap hari.');
