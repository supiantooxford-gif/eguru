<?php
/**
 * HALAMAN SISWA — NILAI (v1.61.0).
 *
 * Permintaan pemilik: *"Rekapan Nilai (Menampilkan Tabel Rekapan dari seluruh Mata Pelajaran)"* →
 * satu tabel berisi seluruh mata pelajaran beserta nilai akhir & predikatnya. Nilai dihitung dengan
 * rumus yang SAMA seperti halaman Penilaian (`nilai_akhir()` + `nilai_konteks()`), jadi angkanya
 * identik dengan yang dilihat guru pengampu.
 * Hanya MELIHAT & MENCETAK.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$sw = require_siswa();
$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];
$aj = ($ta && $sem) ? ajaran_qs($ta, $sem) : '';
$taId = (int) ($ta['id'] ?? 0);
$semId = (int) ($sem['id'] ?? 0);

$rekap = siswa_nilai_rekap((int) $sw['id'], $taId, $semId);

$jumlahAkhir = 0.0;
$adaAkhir = 0;
$hitungPredikat = [];
$jmlSumatif = 0;        // seluruh sumatif yang sudah berjalan di semua mata pelajaran
$jmlBelum = 0;          // sumatif yang belum diikuti siswa ini
foreach ($rekap as $r) {
    if ($r['nilai_akhir'] !== null) {
        $adaAkhir++;
        $jumlahAkhir += (float) $r['nilai_akhir'];
        $p = (string) $r['predikat'];
        $hitungPredikat[$p] = ($hitungPredikat[$p] ?? 0) + 1;
    }
    $jmlSumatif += (int) $r['sumatif_dilakukan'];
    $jmlBelum += count($r['sumatif_belum']);
}
$rataAkhir = $adaAkhir > 0 ? round($jumlahAkhir / $adaAkhir, 2) : null;

/** Label daftar "S1, S2" dari nomor kolom sumatif. */
$labelSumatif = static function (array $kolom): string {
    $bag = [];
    foreach ($kolom as $k) {
        $bag[] = 'S' . (int) $k;
    }
    return implode(', ', $bag);
};

page_top('Nilai Saya', (string) $sw['nama'] . ' &middot; ' . siswa_periode_teks($ta, $sem));
?>
<div class="alert alert-ok mb16">
  <?= ikon('info', 16) ?>
  <span>Rekapan nilai seluruh mata pelajaran pada periode ini — <b>cukup nilai Sumatif</b>
    (kolom Formatif tidak dicantumkan). Nilai akhir dihitung dari <b>rata-rata Sumatif Lingkup
    Materi</b> dan <b>Sumatif Akhir Semester (ASAS)</b> mengikuti pembobotan yang ditetapkan guru
    mata pelajaran. Tiap baris juga memuat keterangan <b>sudah berapa kali sumatif</b> diadakan di
    kelas itu dan <b>sumatif ke berapa yang belum Anda ikuti</b>.</span>
</div>

<div class="grid g4">
  <div class="card stat card-tight">
    <div class="stat-ic g"><?= ikon('book', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= count($rekap) ?></div><div class="stat-lbl">Mata pelajaran dinilai</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic s"><?= ikon('chart', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $rataAkhir === null ? '-' : e(r1($rataAkhir)) ?></div><div class="stat-lbl">Rata-rata nilai akhir</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('check', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $adaAkhir ?></div><div class="stat-lbl">Mapel sudah bernilai akhir</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('star', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $jmlSumatif ?></div>
      <div class="stat-lbl">Sumatif sudah diadakan</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('alert', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $jmlBelum ?></div>
      <div class="stat-lbl">Sumatif belum Anda ikuti</div></div>
  </div>
</div>

<div class="card mt16">
  <div class="card-head">
    <div>
      <h2><?= ikon('chart', 16) ?> Tabel Rekapan Nilai</h2>
      <p><?= e(siswa_periode_teks($ta, $sem)) ?></p>
    </div>
    <div class="flex">
      <?php siswa_form_periode($ta, $sem); ?>
      <a class="btn btn-ghost" href="cetak_siswa_nilai.php<?= e($aj) ?>" target="_blank"><?= ikon('print', 16) ?> Cetak Nilai</a>
    </div>
  </div>

  <?php if (!$rekap): ?>
    <div class="empty">
      <?= ikon('chart', 34) ?>
      <h3>Belum ada nilai pada periode ini</h3>
      <p>Guru mata pelajaran belum mengisi nilai Anda, atau periode yang dipilih belum memiliki nilai.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblNilaiSiswa">
        <thead>
          <tr><th style="width:44px">No</th><th>Mata Pelajaran</th><th>Guru</th>
            <th class="ctr" style="width:120px">Sumatif<br>Lingkup Materi</th>
            <th class="ctr" style="width:110px">Sudah Berapa Kali<br>Sumatif</th>
            <th class="ctr" style="width:150px">Sumatif yang<br>Belum Diikuti</th>
            <th class="ctr" style="width:80px">ASAS</th>
            <th class="ctr" style="width:100px">Nilai Akhir</th>
            <th class="ctr" style="width:130px">Predikat</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rekap as $i => $r): ?>
          <tr>
            <td class="ctr xs mut"><?= $i + 1 ?></td>
            <td><b><?= e((string) $r['mapel_nama']) ?></b>
              <?php if ((float) $r['bobot_asas'] !== 50.0): ?>
                <span class="tbl-sub">Bobot ASAS <?= e(r0((float) $r['bobot_asas'])) ?>%</span>
              <?php endif; ?>
            </td>
            <td><?= e((string) ($r['guru_nama'] !== '' ? $r['guru_nama'] : '-')) ?></td>
            <td class="ctr">
              <?php if ($r['rata_sumatif'] === null): ?>
                <span class="mut xs">-</span>
              <?php else: ?>
                <b><?= e(r1($r['rata_sumatif'])) ?></b>
                <span class="tbl-sub"><?= (int) $r['jumlah_kolom_sumatif'] ?> nilai Anda</span>
              <?php endif; ?>
            </td>
            <td class="ctr">
              <?php if ((int) $r['sumatif_dilakukan'] === 0): ?>
                <span class="mut xs">belum ada</span>
              <?php else: ?>
                <b><?= (int) $r['sumatif_dilakukan'] ?> kali</b>
                <span class="tbl-sub"><?= e($labelSumatif($r['sumatif_berjalan'])) ?></span>
              <?php endif; ?>
            </td>
            <td class="ctr">
              <?php if (!$r['sumatif_belum']): ?>
                <span class="pill pill-A"><?= ikon('check', 12) ?> Semua diikuti</span>
              <?php else: ?>
                <?php foreach ($r['sumatif_belum'] as $kb): ?>
                  <span class="pill pill-D">S<?= (int) $kb ?></span>
                <?php endforeach; ?>
                <span class="tbl-sub"><?= (int) count($r['sumatif_belum']) ?> sumatif belum diikuti</span>
              <?php endif; ?>
            </td>
            <td class="ctr"><?= $r['asas'] === null ? '<span class="mut xs">-</span>' : e(r1($r['asas'])) ?></td>
            <td class="ctr"><?= $r['asas'] === null ? '<span class="mut xs">-</span>' : e(r1($r['asas'])) ?></td>
            <td class="ctr"><b><?= $r['nilai_akhir'] === null ? '-' : e(r1($r['nilai_akhir'])) ?></b></td>
            <td class="ctr">
              <?php $pr = (string) $r['predikat']; ?>
              <?php if ($pr === '-') : ?>
                <span class="mut xs">-</span>
              <?php else: ?>
                <span class="pill <?= $pr === 'A' ? 'pill-A' : ($pr === 'B' ? 'pill-B' : ($pr === 'C' ? 'pill-C' : 'pill-D')) ?>"><?= e($pr) ?> — <?= e(predikat_label($pr)) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="4" class="ctr">Rata-rata nilai akhir seluruh mata pelajaran</th>
            <th class="ctr"><?= (int) $jmlSumatif ?> kali</th>
            <th class="ctr"><?= (int) $jmlBelum ?> belum</th>
            <th class="ctr" colspan="2"><?= $rataAkhir === null ? '-' : e(r1($rataAkhir)) ?></th>
            <th class="ctr"><?= count($rekap) ?> mapel</th>
          </tr>
        </tfoot>
      </table>
    </div>

    <ul class="portal-cek mt16" style="margin-bottom:0">
      <li><b>Sumatif Lingkup Materi</b> — rata-rata nilai sumatif yang <b>sudah Anda peroleh</b> pada
        mata pelajaran itu; keterangan kecil di bawahnya menunjukkan berapa nilai yang sudah masuk.</li>
      <li><b>Sudah Berapa Kali Sumatif</b> — jumlah sumatif yang sudah diadakan di kelas Anda untuk
        mata pelajaran tersebut (dihitung dari kolom sumatif yang sudah ada nilainya di kelas itu),
        diikuti daftar kolomnya (mis. <b>S1, S2, S3</b>).</li>
      <li><b>Sumatif yang Belum Diikuti</b> — kolom sumatif yang sudah diadakan di kelas
        (sudah ada nilai teman sekelas) tetapi <b>Anda belum memiliki nilainya</b>. Bila tertulis
        “Semua diikuti”, tidak ada sumatif yang tertinggal. Bila ada, segera ikuti sumatif
        susulan/ulangan tersebut kepada guru mata pelajaran.</li>
      <li><b>ASAS</b> = Sumatif Akhir Semester. <b>Nilai Akhir</b> = rata-rata Sumatif Lingkup Materi
        dan ASAS sesuai pembobotan tiap mata pelajaran. Predikat: <b>A</b> = Amat Baik, <b>B</b> = Baik,
        <b>C</b> = Cukup, <b>D</b> = Kurang (ambang mengikuti pengaturan guru pengampu atau
        <?= tetap_kata('sekolah/madrasah') ?>).</li>
      <li>Kolom <b>Formatif</b> sengaja <b>tidak dicantumkan</b> pada halaman ini karena hanya
        penilaian proses (tidak dijumlahkan pada nilai akhir).</li>
    </ul>
  <?php endif; ?>
</div>
<?php
page_bottom();
