<?php
/**
 * HALAMAN SISWA — PELANGGARAN (v1.61.0).
 *
 * Permintaan pemilik: *"Catatan Pelanggaran (yang diinput oleh setiap guru mata Pelajaran)"* →
 * daftar catatan pelanggaran atas nama siswa yang sedang masuk, lengkap dengan guru & mata pelajaran
 * yang mencatatnya, tindakannya, dan statusnya.
 * Hanya MELIHAT & MENCETAK.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$sw = require_siswa();
$kasus = siswa_pelanggaran((int) $sw['id']);

$jumlahStatus = ['baru' => 0, 'proses' => 0, 'selesai' => 0];
$jumlahJenis = [];
foreach ($kasus as $k) {
    $st = (string) $k['status'];
    $jumlahStatus[$st] = ($jumlahStatus[$st] ?? 0) + 1;
    $jn = (string) $k['jenis'];
    $jumlahJenis[$jn] = ($jumlahJenis[$jn] ?? 0) + 1;
}

page_top('Pelanggaran Saya', (string) $sw['nama'] . ' &middot; NISN '
    . ($sw['nisn'] !== '' ? (string) $sw['nisn'] : '-'));
?>
<div class="alert alert-ok mb16">
  <?= ikon('info', 16) ?>
  <span>Catatan pelanggaran di bawah ini diinput oleh <b>guru mata pelajaran</b> pada menu Pelanggaran
    Siswa. Isinya hanya dapat dilihat dan dicetak — untuk menanyakan atau memperbaikinya, hubungi
    guru/wali kelas atau Admin Operator <?= tetap_kata('sekolah/madrasah') ?> Anda.</span>
</div>

<div class="grid g4">
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('alert', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= count($kasus) ?></div><div class="stat-lbl">Seluruh catatan</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic s"><?= ikon('clock', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) ($jumlahStatus['baru'] ?? 0) ?></div><div class="stat-lbl">Baru</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic g"><?= ikon('edit', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) ($jumlahStatus['proses'] ?? 0) ?></div><div class="stat-lbl">Dalam proses</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('check', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) ($jumlahStatus['selesai'] ?? 0) ?></div><div class="stat-lbl">Selesai ditangani</div></div>
  </div>
</div>

<div class="card mt16">
  <div class="card-head">
    <div>
      <h2><?= ikon('alert', 16) ?> Catatan Pelanggaran</h2>
      <p>Urut dari yang terbaru. Kolom "Dicatat oleh" memuat guru mata pelajaran yang menginput.</p>
    </div>
    <a class="btn btn-ghost" href="cetak_siswa_pelanggaran.php" target="_blank"><?= ikon('print', 16) ?> Cetak Pelanggaran</a>
  </div>

  <?php if (!$kasus): ?>
    <div class="empty">
      <?= ikon('check', 34) ?>
      <h3>Tidak ada catatan pelanggaran</h3>
      <p>Bagus — belum ada catatan pelanggaran atas nama Anda.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblPelanggaranSiswa">
        <thead>
          <tr><th style="width:44px">No</th><th style="width:120px">Tanggal</th>
            <th style="width:120px">Jenis</th><th>Catatan &amp; Tindakan</th>
            <th style="width:180px">Dicatat oleh</th><th class="ctr" style="width:110px">Status</th></tr>
        </thead>
        <tbody>
        <?php foreach ($kasus as $i => $k): [$jLbl, $sLbl] = siswa_pelanggaran_label((string) $k['jenis'], (string) $k['status']); ?>
          <tr>
            <td class="ctr xs mut"><?= $i + 1 ?></td>
            <td class="nowrap"><?= e(tanggal_id((string) $k['tanggal'], true)) ?></td>
            <td><?= e($jLbl) ?></td>
            <td>
              <b><?= e((string) ($k['judul'] !== '' ? $k['judul'] : '-')) ?></b>
              <?php if (trim((string) $k['deskripsi']) !== ''): ?>
                <span class="tbl-sub"><?= nl2br(e((string) $k['deskripsi'])) ?></span>
              <?php endif; ?>
              <?php if (trim((string) $k['tindakan']) !== ''): ?>
                <span class="tbl-sub"><b>Tindakan:</b> <?= e((string) $k['tindakan']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?= e((string) ($k['guru_nama'] ?: '-')) ?>
              <?php if (trim((string) $k['mapel_nama']) !== ''): ?>
                <span class="tbl-sub"><?= e((string) $k['mapel_nama']) ?></span>
              <?php endif; ?>
            </td>
            <td class="ctr">
              <span class="pill <?= $k['status'] === 'selesai' ? 'pill-A' : ($k['status'] === 'proses' ? 'pill-C' : 'pill-D') ?>"><?= e($sLbl) ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($jumlahJenis): ?>
      <div class="legend mt16">
        <?php foreach ($jumlahJenis as $jn => $n): [$jLbl] = siswa_pelanggaran_label((string) $jn, 'baru'); ?>
          <span><b><?= e($jLbl) ?>:</b> <?= (int) $n ?> catatan</span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php
page_bottom();
