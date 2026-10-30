<?php
/**
 * HALAMAN SISWA — DATA SAYA (v1.61.0).
 *
 * Permintaan pemilik: *"Yang ada dalam halaman siswa adalah menampilkan Nama dan NISN (tidak bisa
 * merubah profil) …"* → halaman ini HANYA MEMBACA data siswa yang sedang masuk.
 * Tidak ada satu pun formulir pengubahan di sini (dan seluruh POST dari akun siswa ditolak 403
 * oleh `gerbang_siswa()`).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$sw = require_siswa();
$id = siswa_identitas($sw);
$foto = $id['Foto'];
unset($id['Foto']);

page_top('Data Saya', (string) $sw['nama'] . ' &middot; NISN '
    . ($sw['nisn'] !== '' ? (string) $sw['nisn'] : '-'));
?>
<div class="alert alert-ok mb16">
  <?= ikon('info', 16) ?>
  <span>Data ini hanya dapat <b>dilihat dan dicetak</b>. Bila ada yang perlu diperbaiki, hubungi
    <b>Admin Operator</b> sekolah/madrasah Anda — akun siswa tidak dapat mengubah profil.</span>
</div>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('user', 16) ?> Identitas Siswa</h2>
      <p>Nama dan NISN beserta data lain yang tercatat pada <?= tetap_kata('sekolah/madrasah') ?> Anda.</p>
    </div>
    <a class="btn btn-ghost" href="cetak_siswa_data.php" target="_blank"><?= ikon('print', 16) ?> Cetak Data Saya</a>
  </div>

  <div class="sis-profil">
    <div class="sis-foto">
      <?php if ($foto !== null): ?>
        <img src="<?= e($foto) ?>" alt="Pas foto <?= e((string) $sw['nama']) ?>">
      <?php else: ?>
        <span class="sis-foto-kosong"><?= ikon('user', 34) ?><small>Belum ada pas foto</small></span>
      <?php endif; ?>
    </div>
    <div class="table-wrap" style="flex:1 1 320px">
      <table class="tbl">
        <tbody>
        <?php foreach ($id as $label => $nilai): ?>
          <tr>
            <th style="width:210px"><?= e((string) $label) ?></th>
            <td><?= e((string) $nilai) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="grid g4 mt16">
  <a class="card card-tight sis-kartu" href="siswa_absensi.php">
    <div class="stat-ic g"><?= ikon('clipboard', 18) ?></div>
    <div><div class="stat-num" style="font-size:1rem">Daftar Hadir</div>
      <div class="stat-lbl" style="line-height:1.5">Rekapan kehadiran per mata pelajaran &amp; daftar hadir kelas.</div></div>
  </a>
  <a class="card card-tight sis-kartu" href="siswa_nilai.php">
    <div class="stat-ic s"><?= ikon('chart', 18) ?></div>
    <div><div class="stat-num" style="font-size:1rem">Nilai</div>
      <div class="stat-lbl" style="line-height:1.5">Tabel rekapan nilai seluruh mata pelajaran.</div></div>
  </a>
  <a class="card card-tight sis-kartu" href="siswa_pelanggaran.php">
    <div class="stat-ic"><?= ikon('alert', 18) ?></div>
    <div><div class="stat-num" style="font-size:1rem">Pelanggaran</div>
      <div class="stat-lbl" style="line-height:1.5">Catatan pelanggaran dari guru mata pelajaran.</div></div>
  </a>
  <a class="card card-tight sis-kartu" href="index.php">
    <div class="stat-ic"><?= ikon('dashboard', 18) ?></div>
    <div><div class="stat-num" style="font-size:1rem">Beranda</div>
      <div class="stat-lbl" style="line-height:1.5">Ringkasan kehadiran, nilai, dan pelanggaran.</div></div>
  </a>
</div>
<?php
page_bottom();
