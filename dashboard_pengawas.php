<?php
/**
 * DASHBOARD PENGAWAS SEKOLAH/MADRASAH — v1.71.6
 *
 * Permintaan pemilik: *"Tambahkan menu Dashboard di atas menu daftar binaan dengan isi: Grafik atau
 * chart (isi grafik atau chart di sesuaikan saja)"*.
 *
 * Halaman ini adalah GAMBARAN LINTAS BINAAN (semua sekolah/madrasah binaan pengawas sekaligus),
 * berbeda dari:
 *   • `index.php` tanpa konteks → Daftar Binaan (tabel + tombol masuk ke satu binaan), dan
 *   • `index.php` dalam konteks → Dashboard Binaan (satu sekolah).
 * Seluruh angkanya dihitung dari `sekolah_binaan()` sehingga pengawas TIDAK MUNGKIN melihat data
 * sekolah yang bukan binaannya (anti-IDOR). Halaman ini hanya-baca: tidak ada POST.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_login();
if (!is_pengawas()) {
    // Halaman ini khusus akun pengawas (pemilik aplikasi memintanya pada menu akun pengawas).
    http_response_code(403);
    page_top('Akses ditolak', 'Halaman khusus akun pengawas');
    ?>
    <div class="card"><div class="empty"><?= ikon('lock', 34) ?>
      <h3>Halaman ini khusus akun Pengawas Sekolah/Madrasah</h3>
      <p>Akun Anda tidak memiliki akses ke halaman ini.</p>
      <a class="btn btn-primary" href="index.php"><?= ikon('dashboard', 16) ?> Ke Dashboard</a>
    </div></div>
    <?php
    page_bottom();
    exit;
}

$binaan = sekolah_binaan((int) $u['id'], true);
$binaanSemua = sekolah_binaan((int) $u['id'], false);
$nonaktif = max(0, count($binaanSemua) - count($binaan));

// ---------------------------------------------------------------------------
// Angka per binaan (satu query ringkas per sekolah; jumlah binaan kecil sehingga aman).
// ---------------------------------------------------------------------------
$baris = [];
$tot = ['guru' => 0, 'siswa' => 0, 'nilai' => 0, 'jurnal' => 0, 'hadir' => 0, 'kasus' => 0, 'kelas' => 0, 'mapel' => 0];
$jenisJml = ['madrasah' => 0, 'umum' => 0];
// SEBARAN JENJANG (v1.71.7 — permintaan pemilik: *"sebaran jenis lembaga ganti dengan sebaran
// jenjang"*). Kuncinya = KODE jenjang (SD/SMP/SMA/SMK/MI/MTs/MA/MAK) dalam urutan baku
// JENJANG_PILIHAN, ditambah kunci '' untuk binaan yang jenjangnya BELUM diisi sekolahnya.
// Satu sumber: kolom `sekolah.jenjang` + `jenjang_sah()` — jenjang yang tidak cocok dengan jenis
// lembaganya dianggap belum diisi (tidak boleh muncul sebagai jenjang yang salah).
$jenjangJml = [];
foreach (JENJANG_PILIHAN['umum'] as $jj) { $jenjangJml[$jj] = 0; }
foreach (JENJANG_PILIHAN['madrasah'] as $jj) { $jenjangJml[$jj] = 0; }
$jenjangJml[''] = 0;
$awalBulan = date('Y-m-01');
$akhirBulan = date('Y-m-t');

foreach ($binaan as $b) {
    $bid = (int) $b['id'];
    $r = [
        's'      => $b,
        'guru'   => (int) val('SELECT COUNT(*) FROM guru WHERE aktif = 1 AND sekolah_id = ?', [$bid], 0),
        'siswa'  => (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND sekolah_id = ?', [$bid], 0),
        'nilai'  => (int) val('SELECT COUNT(*) FROM nilai WHERE sekolah_id = ?', [$bid], 0),
        'jurnal' => (int) val('SELECT COUNT(*) FROM jurnal WHERE sekolah_id = ?', [$bid], 0),
        'hadir'  => (int) val('SELECT COUNT(*) FROM absensi WHERE sekolah_id = ? AND tanggal BETWEEN ? AND ?',
                              [$bid, $awalBulan, $akhirBulan], 0),
        'kasus'  => (int) val('SELECT COUNT(*) FROM kasus WHERE sekolah_id = ? AND status <> ?', [$bid, 'selesai'], 0),
        'kelas'  => (int) val('SELECT COUNT(*) FROM kelas WHERE aktif = 1 AND sekolah_id = ?', [$bid], 0),
        'mapel'  => (int) val('SELECT COUNT(*) FROM mapel WHERE aktif = 1 AND sekolah_id = ?', [$bid], 0),
        'dokumen' => 0,
    ];
    $jenisJml[$b['jenis'] === 'madrasah' ? 'madrasah' : 'umum']++;
    $jgj = trim((string) ($b['jenjang'] ?? ''));
    $jenjangJml[jenjang_sah($jgj, (string) ($b['jenis'] ?? 'umum')) ? $jgj : '']++;
    foreach (['guru', 'siswa', 'nilai', 'jurnal', 'hadir', 'kasus', 'kelas', 'mapel'] as $k) {
        $tot[$k] += $r[$k];
    }
    $baris[] = $r;
}

// Hanya jenjang yang ADA binaannya yang digambar (urutan tetap urutan baku); '' = belum diisi.
$jenjangAda = [];
foreach ($jenjangJml as $kode => $jml) {
    if ($jml > 0) {
        $jenjangAda[$kode] = $jml;
    }
}
$jenjangLabelPendek = static fn(string $kode): string => $kode === '' ? 'Belum diisi' : $kode;
$jenjangById = [];   // untuk grafik: label => jumlah
foreach ($jenjangAda as $kode => $jml) {
    $jenjangById[$jenjangLabelPendek($kode)] = $jml;
}

// Progres pengiriman dokumen tiap binaan (bila menu dokumen tersedia) — angka persen, aman walau kosong.
if (function_exists('dokumen_progress')) {
    foreach ($baris as $i => $r) {
        $pg = dokumen_progress((int) $r['s']['id'], '');
        $baris[$i]['dokumen'] = (int) ($pg['persen'] ?? 0);
    }
}

// Urut dari siswa terbanyak (grafik batang horizontal jadi mudah dibaca).
usort($baris, static fn(array $a, array $b): int => $b['siswa'] <=> $a['siswa']);

$labelNama = array_map(static fn(array $r): string => (string) $r['s']['nama'], $baris);
$dataGuru = array_map(static fn(array $r): int => $r['guru'], $baris);
$dataSiswa = array_map(static fn(array $r): int => $r['siswa'], $baris);
$dataNilai = array_map(static fn(array $r): int => $r['nilai'], $baris);
$dataJurnal = array_map(static fn(array $r): int => $r['jurnal'], $baris);

// ---------------------------------------------------------------------------
// Catatan jurnal 6 bulan terakhir (seluruh binaan digabung) — tren aktivitas guru.
// ---------------------------------------------------------------------------
$labelBulan = [];
$dataBulan = [];
$idBinaan = array_map(static fn(array $r): int => (int) $r['s']['id'], $baris);
$in = $idBinaan ? implode(',', array_fill(0, count($idBinaan), '?')) : '';
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime(date('Y-m-01') . ' -' . $i . ' month');
    $awal = date('Y-m-01', $ts);
    $akhir = date('Y-m-t', $ts);
    $labelBulan[] = bulan_id((int) date('n', $ts)) . ' ' . date('y', $ts);
    $dataBulan[] = $in === '' ? 0 : (int) val(
        'SELECT COUNT(*) FROM jurnal WHERE tanggal BETWEEN ? AND ? AND sekolah_id IN (' . $in . ')',
        array_merge([$awal, $akhir], $idBinaan), 0);
}

// Jumlah menu Tugas Tambahan yang diaktifkan (untuk pintasan laporan).
$menuTugasAktif = count(tugas_menu_saring_tampil(tugas_menu_list(true)));

page_top('Dashboard Pengawas',
    'Pengawas Sekolah/Madrasah &middot; ' . count($binaan) . ' binaan &middot; hanya lihat &amp; cetak');
?>
<?php if (!$binaan): ?>
  <div class="card">
    <div class="empty">
      <?= ikon('school', 36) ?>
      <h3>Belum ada sekolah/madrasah binaan</h3>
      <p>Grafik akan muncul setelah Super Admin menetapkan sekolah/madrasah binaan akun Anda.</p>
      <a class="btn btn-ghost" href="index.php"><?= ikon('dashboard', 16) ?> Daftar Binaan</a>
    </div>
  </div>
<?php else: ?>
  <div class="grid g4">
    <div class="card stat card-tight"><div class="stat-ic"><?= ikon('school', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= count($binaan) ?></div>
        <div class="stat-lbl">Binaan aktif</div>
        <div class="stat-hint"><?= $jenisJml['madrasah'] ?> madrasah &middot; <?= $jenisJml['umum'] ?> sekolah umum<?= $nonaktif > 0 ? ' &middot; ' . $nonaktif . ' nonaktif' : '' ?></div></div></div>
    <div class="card stat card-tight"><div class="stat-ic"><?= ikon('users', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $tot['guru'] ?></div><div class="stat-lbl">Guru aktif</div>
      <div class="stat-hint"><?= (int) $tot['kelas'] ?> kelas &middot; <?= (int) $tot['mapel'] ?> mata pelajaran</div></div></div>
    <div class="card stat card-tight"><div class="stat-ic s"><?= ikon('student', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $tot['siswa'] ?></div><div class="stat-lbl">Siswa aktif</div>
      <div class="stat-hint">seluruh binaan</div></div></div>
    <div class="card stat card-tight"><div class="stat-ic o"><?= ikon('chart', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $tot['nilai'] ?></div><div class="stat-lbl">Baris nilai terisi</div>
      <div class="stat-hint"><?= (int) $tot['jurnal'] ?> catatan jurnal &middot; <?= (int) $tot['hadir'] ?> pertemuan hadir bulan ini</div></div></div>
  </div>

  <?php if ($nonaktif > 0): ?>
    <div class="alert alert-warn">
      <?= ikon('alert', 16) ?>
      <span><?= (int) $nonaktif ?> sekolah/madrasah binaan Anda sudah <b>dinonaktifkan</b> sehingga
        datanya tidak dihitung pada dashboard ini.</span>
    </div>
  <?php endif; ?>

  <div class="grid g21">
    <div class="card">
      <div class="card-head">
        <div><h2>Guru &amp; Siswa per Binaan</h2>
          <p>Perbandingan jumlah guru aktif dan siswa aktif pada setiap sekolah/madrasah binaan.</p></div>
        <a class="btn btn-ghost btn-sm" href="index.php"><?= ikon('dashboard', 15) ?> Daftar Binaan</a>
      </div>
      <div class="chart-box tall"><canvas id="chBinaanOrang"></canvas></div>
    </div>
    <div class="card">
      <?php // SEBARAN JENJANG (v1.71.7 — permintaan pemilik: *"sebaran jenis lembaga ganti dengan
            // sebaran jenjang"*). Menggantikan donut "Sebaran Jenis Lembaga"; sumbernya kolom
            // `sekolah.jenjang` (SD/SMP/SMA/SMK/MI/MTs/MA/MAK). Binaan yang jenjangnya belum diisi
            // sekolahnya dikumpulkan pada potongan "Belum diisi" — data lama memang belum punya
            // jenjang, jadi jangan menyembunyikannya (kalau disembunyikan, jumlahnya tidak cocok
            // dengan jumlah binaan aktif). ?>
      <div class="card-head"><div><h2>Sebaran Jenjang</h2>
        <p>Binaan Anda menurut jenjang lembaganya.</p></div></div>
      <?php if ($jenjangById): ?>
        <div class="chart-box"><canvas id="chJenjang"></canvas></div>
        <div class="stat-mini">
          <?php foreach ($jenjangAda as $kode => $jml): ?>
            <div><b><?= (int) $jml ?></b><span><?= e($jenjangLabelPendek($kode)) ?></span></div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty">
          <?= ikon('school', 30) ?>
          <h3>Belum ada jenjang yang tercatat</h3>
          <p>Jenjang lembaga diisi oleh tiap sekolah/madrasah pada menu
            <b>Identitas &amp; Pengaturan</b>.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid g2">
    <div class="card">
      <div class="card-head"><div><h2>Baris Nilai Terisi per Binaan</h2>
        <p>Semakin banyak baris nilai, semakin lengkap pengisian penilaian di binaan tersebut.</p></div></div>
      <div class="chart-box tall"><canvas id="chNilaiBinaan"></canvas></div>
    </div>
    <div class="card">
      <div class="card-head"><div><h2>Catatan Jurnal 6 Bulan Terakhir</h2>
        <p>Tren jumlah catatan jurnal harian guru pada seluruh binaan.</p></div></div>
      <div class="chart-box tall"><canvas id="chJurnalBulan"></canvas></div>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <div><h2>Ringkasan Aktivitas per Binaan</h2>
        <p>Angka pendukung: catatan jurnal, pertemuan hadir bulan ini, pelanggaran belum selesai,
          dan progres pengiriman dokumen.</p></div>
      <a class="btn btn-primary btn-sm" href="cetak_rekap_binaan.php" target="_blank"><?= ikon('print', 15) ?> Cetak Rekap Semua Binaan</a>
    </div>
    <div class="table-wrap">
      <table class="tbl" id="tblDashboardPengawas">
        <thead>
          <tr>
            <th>Nama <?= tetap_kata('Sekolah/Madrasah') ?></th>
            <th class="ctr">Guru</th>
            <th class="ctr">Siswa</th>
            <th class="ctr">Baris Nilai</th>
            <th class="ctr">Jurnal</th>
            <th class="ctr">Hadir Bulan Ini</th>
            <th class="ctr">Pelanggaran Belum Selesai</th>
            <th class="ctr">Dokumen</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($baris as $r): ?>
            <tr>
              <td><b><?= tetap_kata((string) $r['s']['nama']) ?></b>
                <span class="tbl-sub"><?= e($r['s']['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah Umum') ?>
                  &middot; NPSN <?= e((string) ($r['s']['npsn'] ?: '-')) ?></span></td>
              <td class="ctr"><?= (int) $r['guru'] ?></td>
              <td class="ctr"><b><?= (int) $r['siswa'] ?></b></td>
              <td class="ctr"><?= (int) $r['nilai'] ?></td>
              <td class="ctr"><?= (int) $r['jurnal'] ?></td>
              <td class="ctr"><?= (int) $r['hadir'] ?></td>
              <td class="ctr"><?= (int) $r['kasus'] ?></td>
              <td class="ctr"><span class="pill <?= $r['dokumen'] >= 100 ? 'pill-ok' : ($r['dokumen'] > 0 ? 'pill-C' : 'pill-gray') ?>"><?= (int) $r['dokumen'] ?>%</span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td><b>JUMLAH (<?= count($baris) ?> binaan)</b>
              <span class="tbl-sub">Seluruh kolom dijumlahkan dari daftar di atas</span></td>
            <td class="ctr"><b><?= (int) $tot['guru'] ?></b></td>
            <td class="ctr"><b><?= (int) $tot['siswa'] ?></b></td>
            <td class="ctr"><b><?= (int) $tot['nilai'] ?></b></td>
            <td class="ctr"><b><?= (int) $tot['jurnal'] ?></b></td>
            <td class="ctr"><b><?= (int) $tot['hadir'] ?></b></td>
            <td class="ctr"><b><?= (int) $tot['kasus'] ?></b></td>
            <td class="ctr"><span class="tbl-sub">lihat baris</span></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="hint">Akun pengawas <b>hanya dapat melihat dan mencetak</b> — halaman ini tidak mengubah data apa pun.</p>
  </div>

  <?php if ($menuTugasAktif > 0): ?>
    <div class="card card-tight">
      <div class="flex between">
        <div>
          <h3>Laporan Tugas Tambahan Binaan</h3>
          <p class="mut sm">Buka laporan menu Tugas Tambahan per sekolah/madrasah (hanya lihat &amp; cetak).</p>
        </div>
        <a class="btn btn-ghost btn-sm" href="index.php"><?= ikon('dashboard', 15) ?> Pilih Binaan</a>
      </div>
    </div>
  <?php endif; ?>

  <script src="assets/vendor/chart.umd.min.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var nama = <?= json_encode($labelNama, JSON_UNESCAPED_UNICODE) ?>;
    <?php // Grafik batang GANDA (guru & siswa per binaan). ?>
    EG.barGanda('chBinaanOrang', nama, [
      { label: 'Guru aktif', data: <?= json_encode($dataGuru) ?> },
      { label: 'Siswa aktif', data: <?= json_encode($dataSiswa) ?> }
    ], true);
    <?php // Sebaran JENJANG (v1.71.7) — donut + potongan per jenjang; '' = belum diisi.
          // Warna memakai palet tema; "Belum diisi" diberi abu-abu agar tidak tertukar dengan
          // jenjang sungguhan. ?>
    <?php if ($jenjangById): ?>
    EG.donut('chJenjang',
      <?= json_encode(array_keys($jenjangById), JSON_UNESCAPED_UNICODE) ?>,
      <?= json_encode(array_values($jenjangById)) ?>,
      <?= json_encode(array_map(static fn(string $kode): string => $kode === '' ? '#9aa8a1' : '', array_keys($jenjangAda)), JSON_UNESCAPED_UNICODE) ?>
        .map(function (w, i) { return w !== '' ? w : EG.palette[i % EG.palette.length]; }));
    <?php endif; ?>
    EG.bar('chNilaiBinaan', nama, <?= json_encode($dataNilai) ?>, true, 'rgba(23,138,95,.8)');
    EG.line('chJurnalBulan', <?= json_encode($labelBulan) ?>, <?= json_encode($dataBulan) ?>, 'Catatan jurnal');
  });
  </script>
<?php endif; ?>
<?php
page_bottom();
