<?php
/**
 * LEMBAR CETAK LEGER NILAI KELAS (A4 landscape) — menu Tugas Tambahan "Wali Kelas".
 *
 * Permintaan pemilik: *"Tambahkan fitur Rekap Nilai semua mata pelajaran dan Cetak Leger (tambahkan
 * predikat dan peringkat) pada tugas tambahan wali kelas. Jadi semua nilai mata pelajaran yang diisi
 * oleh yang mengajar di kelas itu terkirim ke Wali Kelas menjadi rekapan nilai."*
 *
 * Isi: kop sekolah + identitas (kelas, tahun ajaran, semester, jumlah siswa/mata pelajaran, rata-rata
 * kelas) + tabel LEGER (No, Nama, NIS, nilai akhir SETIAP mata pelajaran, Jumlah, Rata-rata, Predikat,
 * Peringkat) + baris rata-rata kelas + keterangan predikat + tanda tangan Kepala (kiri) & Wali Kelas
 * (kanan). Nilai dibaca dari tabel `nilai` yang diisi guru pengampu (lihat wali_nilai_rekap()).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/wali.php';

$kelas = wali_cetak_siapkan();
$kelasId = (int) $kelas['id'];

// Periode: ?ta=&sem= (bawaan tahun ajaran & semester aktif sekolah). Nilai tak dikenal diabaikan
// sehingga tidak bisa dipakai membaca data sekolah lain.
$ta = ta_by_id((int) ($_GET['ta'] ?? 0)) ?? ta_aktif();
$sem = semester_by_id((int) ($_GET['sem'] ?? 0));
if (!$ta) {
    flash('Tahun ajaran belum tersedia, jadi leger belum dapat dicetak.', 'warn');
    redirect(wali_url($kelasId, 'nilai'));
}
if (!$sem || (int) $sem['ta_id'] !== (int) $ta['id']) {
    $sem = one('SELECT * FROM semester WHERE ta_id = ? ORDER BY urutan, id LIMIT 1', [(int) $ta['id']]);
}
if (!$sem) {
    flash('Semester pada tahun ajaran itu belum ada, jadi leger belum dapat dicetak.', 'warn');
    redirect(wali_url($kelasId, 'nilai'));
}

$rekap = wali_nilai_rekap($kelasId, (int) $ta['id'], (int) $sem['id']);
$mapel = $rekap['mapel'];
if (!$mapel) {
    flash('Belum ada mata pelajaran yang dapat dijadikan leger untuk kelas ini pada periode tersebut.', 'warn');
    redirect(wali_url($kelasId, 'nilai'));
}
if (!$rekap['siswa']) {
    flash('Kelas ini belum memiliki siswa, jadi leger belum dapat dicetak.', 'warn');
    redirect(wali_url($kelasId, 'nilai'));
}

wali_cetak_head('Leger Nilai Kelas', $kelas, 'landscape', [
    'ta' => (int) $ta['id'],
    'sem' => (int) $sem['id'],
]);
?>
    <?php sheet_meta([
        'Kelas'        => (string) $kelas['nama'],
        'Tahun Ajaran' => (string) $ta['nama'],
        'Semester'     => (string) $sem['nama'],
        'Jumlah'       => count($rekap['siswa']) . ' siswa · ' . count($mapel) . ' mata pelajaran',
        'Rata-rata Kelas' => $rekap['rata_kelas'] === null ? '-' : r1($rekap['rata_kelas']),
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet wali padat leger">
        <thead>
          <tr>
            <th class="ctr k-no">No</th>
            <th class="k-nama-siswa">Nama Siswa</th>
            <th class="ctr k-nis">NIS</th>
            <?php foreach ($mapel as $m): ?>
              <th class="ctr k-mapel" title="<?= e((string) $m['nama']) ?>"><?= e((string) $m['nama']) ?></th>
            <?php endforeach; ?>
            <th class="ctr k-total">Jumlah</th>
            <th class="ctr k-total">Rata-rata</th>
            <th class="ctr k-pred">Predikat</th>
            <th class="ctr k-peringkat">Peringkat</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($rekap['siswa'] as $sw): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><?= e((string) $sw['nama']) ?></td>
            <td class="ctr xs"><?= (string) $sw['nis'] !== '' ? e((string) $sw['nis']) : '-' ?></td>
            <?php foreach ($mapel as $m): $v = $sw['nilai'][(int) $m['id']] ?? null; ?>
              <td class="ctr"><?= $v === null ? '-' : e(r1($v)) ?></td>
            <?php endforeach; ?>
            <td class="ctr"><b><?= $sw['jumlah'] === null ? '-' : e(r1($sw['jumlah'])) ?></b></td>
            <td class="ctr"><b><?= $sw['rata'] === null ? '-' : e(r1($sw['rata'])) ?></b></td>
            <td class="ctr"><?= e((string) $sw['predikat']) ?></td>
            <td class="ctr"><b><?= $sw['peringkat'] === null ? '-' : (int) $sw['peringkat'] ?></b></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="3" class="ctr">Rata-rata kelas</th>
            <?php foreach ($mapel as $m): ?>
              <th class="ctr"><?= ($rekap['rata_mapel'][(int) $m['id']] ?? null) === null ? '-' : e(r1((float) $rekap['rata_mapel'][(int) $m['id']])) ?></th>
            <?php endforeach; ?>
            <th class="ctr">-</th>
            <th class="ctr"><?= $rekap['rata_kelas'] === null ? '-' : e(r1($rekap['rata_kelas'])) ?></th>
            <th class="ctr"><?= $rekap['rata_kelas'] === null ? '-' : e(predikat_dari($rekap['rata_kelas'])) ?></th>
            <th class="ctr">-</th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="leger-catatan">
      Keterangan: nilai setiap kolom adalah <b>nilai akhir</b> mata pelajaran (rata-rata Sumatif Lingkup
      Materi + Sumatif Akhir Semester sesuai bobot penilaian guru pengampu). <b>Peringkat</b> diurutkan
      dari rata-rata seluruh mata pelajaran (nilai seri memperoleh peringkat yang sama).
      Predikat: <?= e(predikat_keterangan()) ?>.
    </div>
<?php
wali_cetak_kaki($kelas, 'Leger nilai seluruh mata pelajaran kelas, disusun dari nilai yang diisi guru pengampu.');
