<?php
/**
 * Dashboard e-GURU.
 * - Super Admin TANPA konteks sekolah : ringkasan lintas sekolah/madrasah
 * - Super Admin dalam konteks / kepala / operator / guru : dashboard sekolah tersebut
 *   (guru hanya melihat data kelas & mata pelajaran yang diampu)
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_login();

// ============================================================================
// CABANG 1 — Super Admin lintas sekolah
// ============================================================================
if (is_super() && !current_sekolah()) {
    $GLOBALS['__vs_istilah_nonaktif'] = true; // ringkasan lintas sekolah/madrasah
    $daftar = sekolah_list(true);
    $jumlahMenunggu = jumlah_pendaftaran_menunggu();
    $semuaAktif = array_values(array_filter($daftar, static fn($s) => ($s['verifikasi'] ?? '') === 'disetujui' && (int) $s['aktif'] === 1));
    $totalGuru = (int) val('SELECT COUNT(*) FROM guru WHERE aktif = 1', [], 0);
    $totalSiswa = (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1', [], 0);
    $totalJurnal = (int) val('SELECT COUNT(*) FROM jurnal', [], 0);
    $totalNilai = (int) val('SELECT COUNT(*) FROM nilai', [], 0);

    // Ringkasan per sekolah untuk tabel & grafik.
    $baris = [];
    $labelGrafik = [];
    $dataGrafik = [];
    foreach ($semuaAktif as $s) {
        $st = sekolah_stat((int) $s['id']);
        $baris[] = ['sekolah' => $s, 'stat' => $st];
        $labelGrafik[] = $s['nama'] !== '' ? (string) $s['nama'] : '(nama belum diisi)';
        $dataGrafik[] = $st['siswa'];
    }

    page_top('Ringkasan Semua Sekolah/Madrasah', 'Super Admin &middot; ' . count($semuaAktif) . ' sekolah/madrasah aktif dari '
        . count($daftar) . ' terdaftar');
    ?>
    <div class="grid g4">
      <div class="card stat card-tight">
        <div class="stat-ic g"><?= ikon('building', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= count($semuaAktif) ?></div><div class="stat-lbl">Sekolah/madrasah aktif</div></div>
      </div>
      <div class="card stat card-tight">
        <div class="stat-ic"><?= ikon('users', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= $totalGuru ?></div><div class="stat-lbl">Guru aktif (semua sekolah)</div></div>
      </div>
      <div class="card stat card-tight">
        <div class="stat-ic s"><?= ikon('student', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= $totalSiswa ?></div><div class="stat-lbl">Siswa aktif (semua sekolah)</div></div>
      </div>
      <div class="card stat card-tight">
        <div class="stat-ic o"><?= ikon('clock', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= $jumlahMenunggu ?></div><div class="stat-lbl">Pendaftaran menunggu</div></div>
      </div>
    </div>

    <?php if ($jumlahMenunggu > 0): ?>
      <div class="alert alert-warn">
        <?= ikon('star', 16) ?>
        <span>Ada <b><?= $jumlahMenunggu ?></b> pendaftaran menunggu persetujuan.
          <a href="pendaftaran.php">Tinjau sekarang</a> — login sekolah tersebut masih ditolak sampai disetujui.</span>
      </div>
    <?php endif; ?>

    <div class="grid g12">
      <div class="card">
        <div class="card-head">
          <div><h2>Sebaran Siswa per Sekolah/Madrasah</h2><p>Hanya sekolah/madrasah yang sudah disetujui &amp; aktif.</p></div>
        </div>
        <?php if ($dataGrafik && array_sum($dataGrafik) > 0): ?>
          <div class="chart-box"><canvas id="chSiswa"></canvas></div>
        <?php else: ?>
          <div class="empty"><?= ikon('chart', 30) ?><h3>Belum ada data siswa</h3>
            <p>Isi data siswa pada masing-masing sekolah/madrasah untuk melihat sebarannya di sini.</p></div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-head">
          <div><h2>Aktivitas Aplikasi</h2><p>Seluruh sekolah/madrasah.</p></div>
        </div>
        <div class="stat-mini">
          <div><b><?= $totalJurnal ?></b><span>Catatan jurnal</span></div>
          <div><b><?= $totalNilai ?></b><span>Baris nilai</span></div>
          <div><b><?= (int) val('SELECT COUNT(*) FROM absensi', [], 0) ?></b><span>Pertemuan daftar hadir</span></div>
          <div><b><?= (int) val('SELECT COUNT(*) FROM kasus', [], 0) ?></b><span>Catatan kasus</span></div>
        </div>
        <div class="divider"></div>
        <div class="flex">
          <a class="btn btn-primary btn-sm" href="sekolah.php"><?= ikon('building', 15) ?> Kelola Sekolah</a>
          <a class="btn btn-ghost btn-sm" href="pendaftaran.php"><?= ikon('star', 15) ?> Pendaftaran</a>
          <a class="btn btn-ghost btn-sm" href="pengguna.php"><?= ikon('key', 15) ?> Akun Super Admin</a>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <div><h2>Daftar Sekolah/Madrasah</h2><p><?= count($baris) ?> aktif &middot; klik "Kelola" untuk masuk ke data sekolah tersebut.</p></div>
      </div>
      <?php if (!$baris): ?>
        <div class="empty"><?= ikon('building', 34) ?><h3>Belum ada sekolah/madrasah aktif</h3>
          <p>Tambahkan lewat menu Kelola Sekolah, atau setujui pendaftaran yang masuk.</p>
          <div class="flex" style="justify-content:center">
            <a class="btn btn-primary" href="sekolah.php"><?= ikon('plus', 16) ?> Tambah Sekolah/Madrasah</a>
            <a class="btn btn-ghost" href="pendaftaran.php"><?= ikon('star', 16) ?> Tinjau Pendaftaran</a>
          </div>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Sekolah/Madrasah</th><th class="ctr">Kelas</th><th class="ctr">Guru</th><th class="ctr">Siswa</th>
              <th class="ctr">Mapel</th><th class="ctr">Jurnal</th><th class="ctr">Nilai</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($baris as $b): $s = $b['sekolah']; $st = $b['stat']; ?>
              <tr>
                <td>
                  <b><?= tetap_kata(nama_sekolah_tampil($s)) ?></b>
                  <span class="tbl-sub"><?= e($s['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah Umum') ?>
                    &middot; NPSN <?= e((string) ($s['npsn'] ?: '-')) ?></span>
                </td>
                <td class="ctr"><?= $st['kelas'] ?></td>
                <td class="ctr"><?= $st['guru'] ?></td>
                <td class="ctr"><b><?= $st['siswa'] ?></b></td>
                <td class="ctr"><?= $st['mapel'] ?></td>
                <td class="ctr"><?= $st['jurnal'] ?></td>
                <td class="ctr"><?= $st['nilai'] ?></td>
                <td>
                  <form method="post" action="sekolah.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="masuk">
                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <button class="btn btn-primary btn-sm" type="submit"><?= ikon('switch', 14) ?> Kelola</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($dataGrafik && array_sum($dataGrafik) > 0): ?>
      <script src="assets/vendor/chart.umd.min.js"></script>
      <script>
      document.addEventListener('DOMContentLoaded', function () {
        EG.bar('chSiswa', <?= json_encode($labelGrafik, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($dataGrafik) ?>,
          <?= count($labelGrafik) > 6 ? 'true' : 'false' ?>);
      });
      </script>
    <?php endif; ?>
    <?php
    page_bottom();
    exit;
}

// ============================================================================
// CABANG 2 — Dashboard satu sekolah
// ============================================================================
$sekolah = require_sekolah();
$sid = (int) $sekolah['id'];
$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];
$guru = is_guru();
$gid = $guru ? guru_id_saya() : 0;
$kelasIds = $guru ? array_map('intval', array_column(kelas_diampu(), 'id')) : [];
$inKelas = '';
$pInKelas = [];
if ($guru) {
    $inKelas = $kelasIds ? ' IN (' . implode(',', array_fill(0, count($kelasIds), '?')) . ')' : ' = -1';
    $pInKelas = $kelasIds;
}

$jmlGuru = (int) val('SELECT COUNT(*) FROM guru WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0);
$jmlSiswa = $guru
    ? (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND kelas_id' . $inKelas, $pInKelas, 0)
    : (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0);
$jmlKelas = $guru ? count($kelasIds) : (int) val('SELECT COUNT(*) FROM kelas WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0);
$jmlMapel = $guru ? count(mapel_diampu()) : (int) val('SELECT COUNT(*) FROM mapel WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0);
$jmlJurnal = $guru
    ? (int) val('SELECT COUNT(*) FROM jurnal WHERE guru_id = ?', [$gid], 0)
    : (int) val('SELECT COUNT(*) FROM jurnal WHERE sekolah_id = ?', [$sid], 0);
$kasusAktif = (int) val('SELECT COUNT(*) FROM kasus WHERE status <> "selesai" AND sekolah_id = ?' . ($guru ? ' AND kelas_id' . $inKelas : ''),
    array_merge([$sid], $guru ? $pInKelas : []), 0);

// Grafik: siswa per kelas
$siswaPerKelas = all('SELECT k.nama, (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.aktif = 1) AS jml
    FROM kelas k WHERE k.aktif = 1 AND k.sekolah_id = ?' . ($guru ? ' AND k.id' . $inKelas : '')
    . ' ORDER BY k.urutan, k.nama', array_merge([$sid], $guru ? $pInKelas : []));

// Grafik: kehadiran bulan ini
$awalBulan = date('Y-m-01');
$akhirBulan = date('Y-m-t');
$kehadiran = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
foreach (all('SELECT d.status, COUNT(*) AS jml FROM absensi a JOIN absensi_detail d ON d.absensi_id = a.id
              WHERE a.tanggal BETWEEN ? AND ? AND a.sekolah_id = ?' . ($guru ? ' AND a.kelas_id' . $inKelas : '') . ' GROUP BY d.status',
        array_merge([$awalBulan, $akhirBulan, $sid], $guru ? $pInKelas : [])) as $r) {
    if (isset($kehadiran[(string) $r['status']])) {
        $kehadiran[(string) $r['status']] = (int) $r['jml'];
    }
}
$totalHadir = array_sum($kehadiran);
$persenHadir = $totalHadir > 0 ? round($kehadiran['H'] / $totalHadir * 100, 1) : null;

// Grafik: jurnal 6 bulan
$jurnalBulan = [];
foreach (all('SELECT strftime("%Y-%m", tanggal) AS bln, COUNT(*) AS jml FROM jurnal
              WHERE tanggal >= date("now", "-5 months", "start of month") AND sekolah_id = ?'
              . ($guru ? ' AND guru_id = ?' : '') . ' GROUP BY bln',
        $guru ? [$sid, $gid] : [$sid]) as $r) {
    $jurnalBulan[(string) $r['bln']] = (int) $r['jml'];
}
$labelBulan = [];
$dataBulan = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime('-' . $i . ' months', strtotime(date('Y-m-01')));
    $kunci = date('Y-m', $ts);
    $labelBulan[] = substr(bulan_id((int) date('n', $ts)), 0, 3) . ' ' . date('y', $ts);
    $dataBulan[] = $jurnalBulan[$kunci] ?? 0;
}

// Predikat & rata-rata nilai
$predikatJml = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
$rataMapel = [];
if ($ta && $sem) {
    $barisNilai = all('SELECT n.siswa_id, n.mapel_id, n.jenis, n.kolom, n.nilai FROM nilai n
                       WHERE n.sekolah_id = ? AND n.ta_id = ? AND n.semester_id = ?' . ($guru ? ' AND n.kelas_id' . $inKelas : ''),
        array_merge([$sid, (int) $ta['id'], (int) $sem['id']], $guru ? $pInKelas : []));
    $kelompok = [];
    foreach ($barisNilai as $r) {
        $kunci = (int) $r['siswa_id'] . '-' . (int) $r['mapel_id'];
        if (!isset($kelompok[$kunci])) {
            $kelompok[$kunci] = ['sumatif' => [], 'asas' => null, 'mapel_id' => (int) $r['mapel_id']];
        }
        $v = (float) $r['nilai'];
        if ($r['jenis'] === 'sumatif') {
            $kelompok[$kunci]['sumatif'][(int) $r['kolom']] = $v;
        } elseif ($r['jenis'] === 'asas') {
            $kelompok[$kunci]['asas'] = $v;
        }
    }
    foreach ($kelompok as $d) {
        $akhir = nilai_akhir($d['sumatif'], $d['asas']);
        if ($akhir === null) {
            continue;
        }
        $p = predikat_dari($akhir);
        if (isset($predikatJml[$p])) {
            $predikatJml[$p]++;
        }
        $mid = (int) $d['mapel_id'];
        $rataMapel[$mid]['total'] = ($rataMapel[$mid]['total'] ?? 0) + $akhir;
        $rataMapel[$mid]['jml'] = ($rataMapel[$mid]['jml'] ?? 0) + 1;
    }
}
$tot = 0;
$jml = 0;
foreach ($rataMapel as $d) {
    $tot += $d['total'];
    $jml += $d['jml'];
}
$rataSemua = $jml > 0 ? round($tot / $jml, 2) : null;
$mapelRata = [];
foreach ($rataMapel as $mid => $d) {
    $mapelRata[] = ['nama' => mapel_nama((int) $mid), 'rata' => round($d['total'] / $d['jml'], 2), 'jml' => $d['jml']];
}
usort($mapelRata, static fn($a, $b) => $b['rata'] <=> $a['rata']);

// Daftar terbaru
$jurnalTerbaru = all('SELECT j.*, k.nama AS kelas_nama, m.nama AS mapel_nama, g.nama AS guru_nama
    FROM jurnal j LEFT JOIN kelas k ON k.id = j.kelas_id LEFT JOIN mapel m ON m.id = j.mapel_id LEFT JOIN guru g ON g.id = j.guru_id
    WHERE j.sekolah_id = ?' . ($guru ? ' AND j.guru_id = ?' : '') . ' ORDER BY j.tanggal DESC, j.id DESC LIMIT 5',
    $guru ? [$sid, $gid] : [$sid]);
$kasusTerbaru = all('SELECT ks.*, s.nama AS siswa_nama, k.nama AS kelas_nama
    FROM kasus ks LEFT JOIN siswa s ON s.id = ks.siswa_id LEFT JOIN kelas k ON k.id = ks.kelas_id
    WHERE ks.status <> "selesai" AND ks.sekolah_id = ?' . ($guru ? ' AND ks.kelas_id' . $inKelas : '')
    . ' ORDER BY ks.tanggal DESC LIMIT 5', array_merge([$sid], $guru ? $pInKelas : []));

$belumSiap = [];
if (trim((string) $sekolah['nama']) === '') {
    $belumSiap[] = 'Identitas ' . istilah_sekolah('sekolah') . ' (nama, alamat, kepala) belum diisi.';
}
if ((int) val('SELECT COUNT(*) FROM kelas WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0) === 0) {
    $belumSiap[] = 'Belum ada kelas.';
}
if ((int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0) === 0) {
    $belumSiap[] = 'Belum ada data siswa.';
}
if ((int) val('SELECT COUNT(*) FROM guru WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0) === 0) {
    $belumSiap[] = 'Belum ada data guru.';
}

page_top('Dashboard');
?>
<?php if ($belumSiap && !$guru): ?>
  <div class="alert alert-warn">
    <?= ikon('alert', 16) ?>
    <span><b>Langkah awal:</b> <?= e(implode(' ', $belumSiap)) ?>
      Mulai dari <a href="kelas.php">Kelas</a>, lalu <a href="guru_edit.php">Data Guru</a> dan <a href="siswa_edit.php">Data Siswa</a>.
      Jangan lupa lengkapi <a href="pengaturan.php">Identitas &amp; Pengaturan</a> agar kop dokumen cetak benar.</span>
  </div>
<?php endif; ?>

<div class="grid g4">
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('student', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $jmlSiswa ?></div><div class="stat-lbl"><?= $guru ? 'Siswa di kelas Anda' : 'Siswa aktif' ?></div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic s"><?= ikon('school', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $jmlKelas ?></div><div class="stat-lbl"><?= $guru ? 'Kelas yang diajar' : 'Kelas aktif' ?></div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic p"><?= ikon('users', 18) ?></div>
    <div>
      <div class="stat-num" style="font-size:1.3rem"><?= $guru ? $jmlMapel : $jmlGuru ?></div>
      <div class="stat-lbl"><?= $guru ? 'Mata pelajaran diampu' : 'Guru aktif' ?></div>
    </div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic o"><?= ikon('book', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $jmlJurnal ?></div><div class="stat-lbl">Catatan jurnal</div></div>
  </div>
</div>

<div class="grid g21">
  <div class="card">
    <div class="card-head"><div><h2>Jumlah Siswa per Kelas</h2><p>Sebaran siswa pada kelas aktif.</p></div></div>
    <?php if ($siswaPerKelas): ?>
      <div class="chart-box"><canvas id="chKelas"></canvas></div>
    <?php else: ?>
      <div class="empty"><?= ikon('school', 30) ?><h3>Belum ada kelas</h3><p>Tambahkan kelas untuk melihat sebaran siswa.</p></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Predikat Nilai</h2><p>Distribusi nilai akhir periode aktif.</p></div></div>
    <?php if (array_sum($predikatJml) > 0): ?>
      <div class="chart-box"><canvas id="chPredikat"></canvas></div>
      <?php if ($rataSemua !== null): ?>
        <p class="hint center">Rata-rata nilai akhir: <b><?= e(r1($rataSemua)) ?></b> (<?= count($mapelRata) ?> mata pelajaran)</p>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty"><?= ikon('chart', 30) ?><h3>Belum ada nilai</h3><p>Isi daftar nilai untuk melihat distribusi predikat.</p></div>
    <?php endif; ?>
  </div>
</div>

<div class="grid g21">
  <div class="card">
    <div class="card-head"><div><h2>Aktivitas Jurnal 6 Bulan Terakhir</h2><p>Jumlah catatan jurnal harian per bulan.</p></div></div>
    <div class="chart-box"><canvas id="chJurnal"></canvas></div>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Kehadiran Bulan Ini</h2><p><?= e(bulan_id((int) date('n'))) . ' ' . date('Y') ?></p></div></div>
    <?php if ($totalHadir > 0): ?>
      <div class="chart-box"><canvas id="chHadir"></canvas></div>
      <p class="hint center">Persentase kehadiran: <b><?= e(r1($persenHadir)) ?>%</b> dari <?= (int) $totalHadir ?> catatan</p>
    <?php else: ?>
      <div class="empty"><?= ikon('clipboard', 30) ?><h3>Belum ada daftar hadir</h3><p>Isi daftar hadir bulan ini untuk melihat rekapnya.</p></div>
    <?php endif; ?>
  </div>
</div>

<div class="grid g2">
  <div class="card">
    <div class="card-head">
      <div><h2>Jurnal Terbaru</h2><p><?= $guru ? 'Catatan Anda' : 'Seluruh guru' ?> &middot; <?= (int) $kasusAktif ?> kasus belum selesai</p></div>
      <a class="btn btn-ghost btn-sm" href="jurnal.php"><?= ikon('book', 14) ?> Semua jurnal</a>
    </div>
    <?php if (!$jurnalTerbaru): ?>
      <p class="mut sm">Belum ada catatan jurnal.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Tanggal</th><th>Kelas / Mapel</th><th>Materi</th><th class="ctr">Kehadiran</th></tr></thead>
          <tbody>
          <?php foreach ($jurnalTerbaru as $j): ?>
            <tr>
              <td class="nowrap"><?= e(tanggal_id((string) $j['tanggal'])) ?></td>
              <td><span class="tagmini"><?= e((string) ($j['kelas_nama'] ?: '-')) ?></span><span class="tagmini"><?= e((string) ($j['mapel_nama'] ?: '-')) ?></span></td>
              <td class="sm"><?= e((string) $j['materi']) ?></td>
              <td class="ctr"><span class="xs mut"><?= e(kehadiran_ringkas($j)) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <div><h2>Kasus Belum Selesai</h2><p><?= $kasusAktif ?> catatan belum selesai</p></div>
      <a class="btn btn-ghost btn-sm" href="kasus.php"><?= ikon('alert', 14) ?> Semua kasus</a>
    </div>
    <?php if (!$kasusTerbaru): ?>
      <p class="mut sm">Tidak ada kasus yang belum selesai.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Tanggal</th><th>Siswa</th><th>Uraian</th><th class="ctr">Status</th></tr></thead>
          <tbody>
          <?php $statusList = kasus_status(); foreach ($kasusTerbaru as $r): ?>
            <tr>
              <td class="nowrap"><?= e(tanggal_id((string) $r['tanggal'])) ?></td>
              <td><?= e((string) ($r['siswa_nama'] ?: '-')) ?><span class="tbl-sub"><?= e((string) ($r['kelas_nama'] ?: '-')) ?></span></td>
              <td class="sm"><?= e((string) ($r['judul'] ?: $r['deskripsi'])) ?></td>
              <td class="ctr"><span class="pill <?= $r['status'] === 'proses' ? 'pill-C' : 'pill-D' ?>"><?= e($statusList[(string) $r['status']] ?? '-') ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($mapelRata): ?>
  <div class="card">
    <div class="card-head">
      <div><h2>Rata-rata Nilai Akhir per Mata Pelajaran</h2><p>Periode <?= e(ta_nama($ta)) ?> &middot; Semester <?= e(sem_nama($sem)) ?></p></div>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Mata Pelajaran</th><th class="ctr">Siswa Dinilai</th><th class="ctr">Rata-rata</th><th class="ctr">Predikat</th><th style="width:220px">Grafik</th></tr></thead>
        <tbody>
        <?php foreach ($mapelRata as $m): $p = predikat_dari($m['rata']); ?>
          <tr>
            <td><b><?= e($m['nama']) ?></b></td>
            <td class="ctr"><?= (int) $m['jml'] ?></td>
            <td class="ctr"><b><?= e(r1($m['rata'])) ?></b></td>
            <td class="ctr"><span class="pill pill-<?= e($p) ?>"><?= e($p) ?></span></td>
            <td><span class="bar-mini"><i class="lv<?= e($p) ?>" style="width:<?= (int) max(0, min(100, $m['rata'])) ?>%"></i></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<script src="assets/vendor/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  <?php if ($siswaPerKelas): ?>
  EG.bar('chKelas', <?= json_encode(array_map(static fn($r) => (string) $r['nama'], $siswaPerKelas), JSON_UNESCAPED_UNICODE) ?>,
    <?= json_encode(array_map(static fn($r) => (int) $r['jml'], $siswaPerKelas)) ?>,
    <?= count($siswaPerKelas) > 6 ? 'true' : 'false' ?>);
  <?php endif; ?>
  <?php if (array_sum($predikatJml) > 0): ?>
  EG.donut('chPredikat', ['A (Sangat Baik)', 'B (Baik)', 'C (Cukup)', 'D (Perlu Bimbingan)'],
    [<?= (int) $predikatJml['A'] ?>, <?= (int) $predikatJml['B'] ?>, <?= (int) $predikatJml['C'] ?>, <?= (int) $predikatJml['D'] ?>],
    ['#0f7a53', '#2aa87a', '#c9a227', '#c0392b']);
  <?php endif; ?>
  EG.line('chJurnal', <?= json_encode($labelBulan) ?>, <?= json_encode($dataBulan) ?>, 'Catatan jurnal');
  <?php if ($totalHadir > 0): ?>
  EG.donut('chHadir', ['Hadir', 'Sakit', 'Izin', 'Alpa'],
    [<?= (int) $kehadiran['H'] ?>, <?= (int) $kehadiran['S'] ?>, <?= (int) $kehadiran['I'] ?>, <?= (int) $kehadiran['A'] ?>],
    ['#0f7a53', '#c9a227', '#2b7bb9', '#c0392b']);
  <?php endif; ?>
});
</script>
<?php
page_bottom();
