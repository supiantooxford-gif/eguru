<?php
/**
 * Daftar Hadir Siswa — daftar pertemuan yang sudah dicatat + rekap kehadiran per siswa.
 * Pengisian status H/S/I/A dilakukan pada absensi_form.php.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_sekolah(); // super admin tanpa konteks dialihkan ke Kelola Sekolah
// Permintaan pemilik (v1.21.0): Admin Operator hanya melihat & mencetak daftar hadir.
$bolehIsi = boleh_isi_administrasi();
$bolehKelola = boleh_hapus_administrasi(); // menghapus catatan pertemuan (sejak v1.21.0: Super Admin)
$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];
$aj = ajaran_qs($ta, $sem);

if (!$ta || !$sem) {
    page_top('Daftar Hadir Siswa');
    ?>
    <div class="card">
      <div class="empty">
        <?= ikon('calendar', 34) ?>
        <h3>Tahun ajaran / semester belum tersedia</h3>
        <p>Daftar hadir disimpan per tahun ajaran &amp; semester. Atur periodenya terlebih dahulu.</p>
        <a class="btn btn-primary" href="tahun_ajaran.php"><?= ikon('calendar', 16) ?> Atur Tahun Ajaran &amp; Semester</a>
      </div>
    </div>
    <?php
    page_bottom();
    exit;
}

// ---------------------------------------------------------------- aksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $sesi = absensi_sesi($id);

    if ($aksi === 'hapus') {
        if (!$sesi) {
            flash('Catatan pertemuan tidak ditemukan.', 'err');
        } elseif (!boleh_kelas((int) $sesi['kelas_id']) || !boleh_mapel((int) $sesi['mapel_id'])) {
            tolak403('Catatan pertemuan ini di luar kelas/mata pelajaran yang Anda ampu.');
        } elseif (is_guru() && (int) ($sesi['guru_id'] ?? 0) !== guru_id_saya()) {
            // Guru hanya mengelola pertemuan miliknya sendiri (daftar pun hanya menampilkan itu).
            tolak403('Catatan pertemuan ini milik guru lain.');
        } elseif (!$bolehKelola) {
            tolak403('Menghapus catatan pertemuan daftar hadir hanya untuk Super Admin. '
                . 'Admin Operator hanya dapat melihat dan mencetak.');
        } else {
            q('DELETE FROM absensi WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
            flash('Catatan pertemuan ' . tanggal_id((string) $sesi['tanggal']) . ' (' . $sesi['mapel_nama'] . ') dihapus.', 'warn');
        }
        redirect('absensi.php' . $aj . '&kelas=' . (int) ($sesi['kelas_id'] ?? 0));
    }
    redirect('absensi.php' . $aj);
}

// ---------------------------------------------------------------- filter
$daftarKelas = kelas_pilih();
$daftarMapel = mapel_pilih();
$guruAkun = is_guru();   // guru hanya melihat pertemuan miliknya → kolom "Guru" tidak perlu ditampilkan

$fKelas = (int) ($_GET['kelas'] ?? 0);
// Penting: kelas dipilih otomatis LEBIH DULU. Kalau penjaga cakupan dijalankan sebelum ini,
// id kelas masih 0 sehingga boleh_kelas(0) = false dan guru langsung mendapat 403
// "Kelas itu tidak Anda ampu" hanya karena membuka menu tanpa ?kelas=.
if ($fKelas <= 0) {
    $fKelas = $daftarKelas ? (int) $daftarKelas[0]['id'] : 0;
}
if ($fKelas > 0 && !boleh_kelas($fKelas)) {
    tolak403('Kelas itu tidak Anda ampu.');
}
$fMapel = (int) ($_GET['mapel'] ?? 0);
if ($fMapel > 0 && !boleh_mapel($fMapel)) {
    tolak403('Mata pelajaran itu tidak Anda ampu.');
}
$fDari = tgl_ok($_GET['dari'] ?? null);
$fSampai = tgl_ok($_GET['sampai'] ?? null);
if ($fDari !== null && $fSampai !== null && $fDari > $fSampai) {
    [$fDari, $fSampai] = [$fSampai, $fDari];
}

// ---------------------------------------------------------------- data
$sesiList = [];
$rekap = [];
$siswa = [];
if ($fKelas > 0) {
    $w = ['a.sekolah_id = ?', 'a.kelas_id = ?', 'a.ta_id = ?', 'a.semester_id = ?'];
    $p = [sekolah_id(), $fKelas, (int) $ta['id'], (int) $sem['id']];
    if ($fMapel > 0) {
        $w[] = 'a.mapel_id = ?';
        $p[] = $fMapel;
    }
    if ($fDari !== null) {
        $w[] = 'a.tanggal >= ?';
        $p[] = $fDari;
    }
    if ($fSampai !== null) {
        $w[] = 'a.tanggal <= ?';
        $p[] = $fSampai;
    }
    // Guru HANYA melihat pertemuan yang IA catat sendiri (permintaan pemilik) — tidak melihat
    // daftar hadir guru/mata pelajaran lain pada kelas yang sama.
    if (is_guru()) {
        $w[] = 'a.guru_id = ?';
        $p[] = guru_id_saya();
    }
    $sesiList = all('SELECT a.*, m.nama AS mapel_nama, g.nama AS guru_nama,
        (SELECT COUNT(*) FROM absensi_detail d WHERE d.absensi_id = a.id AND d.status = "H") AS jml_h,
        (SELECT COUNT(*) FROM absensi_detail d WHERE d.absensi_id = a.id AND d.status = "S") AS jml_s,
        (SELECT COUNT(*) FROM absensi_detail d WHERE d.absensi_id = a.id AND d.status = "I") AS jml_i,
        (SELECT COUNT(*) FROM absensi_detail d WHERE d.absensi_id = a.id AND d.status = "A") AS jml_a
        FROM absensi a LEFT JOIN mapel m ON m.id = a.mapel_id LEFT JOIN guru g ON g.id = a.guru_id
        WHERE ' . implode(' AND ', $w) . ' ORDER BY a.tanggal DESC, a.pertemuan DESC', $p);

    $siswa = siswa_kelas($fKelas, true);
    // Rekap kehadiran juga dibatasi pada pertemuan guru ini sendiri (bukan milik guru lain).
    $rekap = absensi_rekap($fKelas, $fMapel, (int) $ta['id'], (int) $sem['id'], $fDari, $fSampai,
        is_guru() ? guru_id_saya() : 0);
}

$qBase = ['kelas' => $fKelas, 'mapel' => $fMapel ?: null, 'dari' => $fDari, 'sampai' => $fSampai,
          'ta' => (int) $ta['id'], 'sem' => (int) $sem['id']];

// Ekspor CSV rekap (dilakukan SEBELUM keluaran halaman dikirim).
if (isset($_GET['csv']) && $fKelas > 0 && $siswa) {
    $baris = [];
    foreach ($siswa as $s) {
        $r = $rekap[(int) $s['id']] ?? ['h' => 0, 's' => 0, 'i' => 0, 'a' => 0, 'total' => 0, 'persen' => null];
        $baris[] = [$s['nis'] ?: '', $s['nama'], $r['h'], $r['s'], $r['i'], $r['a'], $r['total'], $r['persen'] === null ? '' : $r['persen']];
    }
    csv_kirim('rekap-kehadiran-' . preg_replace('/[^A-Za-z0-9]+/', '-', kelas_nama($fKelas)) . '.csv',
        ['NIS', 'Nama', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Pertemuan', '% Hadir'], $baris);
}

page_top('Daftar Hadir Siswa');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2>Periode Kehadiran</h2>
      <p>Tahun ajaran <b><?= e(ta_nama($ta)) ?></b> &middot; Semester <b><?= e(sem_nama($sem)) ?></b></p>
    </div>
    <div class="flex">
      <?php if (count(ta_list()) > 1): ?>
        <form method="get" class="flex">
          <input type="hidden" name="kelas" value="<?= (int) $fKelas ?>">
          <select name="ta" data-auto>
            <?php foreach (ta_list() as $t): ?>
              <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === (int) $ta['id'] ? 'selected' : '' ?>>T.A. <?= e((string) $t['nama']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="sem" data-auto>
            <?php foreach (semester_list((int) $ta['id']) as $sm): ?>
              <option value="<?= (int) $sm['id'] ?>" <?= (int) $sm['id'] === (int) $sem['id'] ? 'selected' : '' ?>>Semester <?= e((string) $sm['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
      <?php if ($fKelas > 0): ?>
        <a class="btn btn-ghost" href="cetak_absensi.php?<?= e(http_build_query($qBase)) ?>" target="_blank"><?= ikon('print', 16) ?> Cetak Rekap</a>
        <a class="btn btn-ghost" href="absensi.php?<?= e(http_build_query($qBase + ['csv' => 1])) ?>"><?= ikon('doc', 16) ?> Unduh CSV</a>
      <?php endif; ?>
    </div>
  </div>

  <form class="filterbar" method="get">
    <input type="hidden" name="ta" value="<?= (int) $ta['id'] ?>">
    <input type="hidden" name="sem" value="<?= (int) $sem['id'] ?>">
    <div>
      <label class="f" for="kelas">Kelas</label>
      <select id="kelas" name="kelas" data-auto>
        <?php if (!$daftarKelas): ?><option value="">— belum ada kelas —</option><?php endif; ?>
        <?php foreach ($daftarKelas as $k): ?>
          <option value="<?= (int) $k['id'] ?>" <?= $fKelas === (int) $k['id'] ? 'selected' : '' ?>><?= e((string) $k['nama']) ?> (<?= (int) $k['jml_siswa'] ?> siswa)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="f" for="mapel">Mata Pelajaran</label>
      <select id="mapel" name="mapel" data-auto>
        <option value="">Semua mata pelajaran</option>
        <?php foreach ($daftarMapel as $m): ?>
          <option value="<?= (int) $m['id'] ?>" <?= $fMapel === (int) $m['id'] ? 'selected' : '' ?>><?= e((string) $m['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="f" for="dari">Dari tanggal</label>
      <input type="date" id="dari" name="dari" value="<?= e((string) $fDari) ?>">
    </div>
    <div>
      <label class="f" for="sampai">Sampai tanggal</label>
      <input type="date" id="sampai" name="sampai" value="<?= e((string) $fSampai) ?>">
    </div>
    <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
    <a class="btn btn-ghost" href="absensi.php?ta=<?= (int) $ta['id'] ?>&sem=<?= (int) $sem['id'] ?>">Reset</a>
  </form>
</div>

<?php if (!$daftarKelas): ?>
  <?php if (is_guru()): ?>
    <?= kartu_tanpa_penugasan('Anda belum ditugaskan ke kelas mana pun',
        'Daftar hadir diisi untuk kelas yang Anda ajar, tetapi belum ada kelas yang ditetapkan untuk akun Anda.') ?>
  <?php else: ?>
    <div class="card">
      <div class="empty">
        <?= ikon('school', 34) ?>
        <h3>Belum ada kelas</h3>
        <p>Daftar hadir memerlukan kelas yang berisi siswa. Tambahkan kelas dan data siswa terlebih dahulu.</p>
        <?php if (boleh_master()): ?><a class="btn btn-primary" href="kelas.php"><?= ikon('plus', 16) ?> Tambah Kelas</a><?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
<?php elseif ($fKelas <= 0): ?>
  <div class="card">
    <div class="empty">
      <?= ikon('school', 34) ?>
      <h3>Kelas belum dipilih</h3>
      <p>Pilih kelas pada daftar di atas untuk melihat pertemuan dan rekap kehadirannya.</p>
    </div>
  </div>
<?php else: ?>
  <?php if (!$bolehIsi): ?>
    <div class="alert alert-warn mb16">
      <?= ikon('eye', 16) ?>
      <span><?= pemberitahuan_hanya_lihat_administrasi('daftar hadir siswa') ?></span>
    </div>
  <?php endif; ?>
  <?php if ($bolehIsi): ?>
    <div class="card">
      <div class="card-head">
        <div><h2>Catat Pertemuan Baru</h2><p>Pilih tanggal, mata pelajaran, dan pertemuan — lalu isi status kehadiran tiap siswa.</p></div>
      </div>
      <form method="get" action="absensi_form.php" class="filterbar">
        <input type="hidden" name="kelas" value="<?= (int) $fKelas ?>">
        <input type="hidden" name="ta" value="<?= (int) $ta['id'] ?>">
        <input type="hidden" name="sem" value="<?= (int) $sem['id'] ?>">
        <div>
          <label class="f" for="tgl">Tanggal</label>
          <input type="date" id="tgl" name="tanggal" value="<?= e(date('Y-m-d')) ?>" required>
        </div>
        <div>
          <label class="f" for="mapelBaru">Mata Pelajaran</label>
          <select id="mapelBaru" name="mapel" required>
            <?php foreach ($daftarMapel as $m): ?>
              <option value="<?= (int) $m['id'] ?>" <?= $fMapel === (int) $m['id'] ? 'selected' : '' ?>><?= e((string) $m['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="f" for="pertemuan">Pertemuan ke-</label>
          <input type="number" id="pertemuan" name="pertemuan" min="1" value="<?= (int) absensi_jumlah_pertemuan($fKelas, $fMapel, (int) $ta['id'], (int) $sem['id']) + 1 ?>">
        </div>
        <div>
          <label class="f" for="materi">Materi / Kegiatan</label>
          <input type="text" id="materi" name="materi" placeholder="mis. Operasi bilangan bulat">
        </div>
        <button class="btn btn-primary" type="submit"><?= ikon('clipboard', 16) ?> Isi Daftar Hadir</button>
      </form>
      <?php if (!$daftarMapel): ?>
        <div class="alert alert-warn" style="margin:12px 0 0">
          <?= ikon('alert', 16) ?>
          <span>Belum ada mata pelajaran yang dapat Anda pilih, sehingga pertemuan baru belum bisa dicatat.
            Minta Admin Operator menetapkan <b>mata pelajaran yang dipegang</b> pada menu Data Guru.</span>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-head">
      <div><h2>Pertemuan Tercatat</h2><p><span id="tblCount"><?= count($sesiList) ?></span> pertemuan<?= $fMapel > 0 ? ' untuk ' . e(mapel_nama($fMapel)) : '' ?></p></div>
    </div>
    <?php if (!$sesiList): ?>
      <div class="empty"><?= ikon('clipboard', 34) ?><h3>Belum ada catatan kehadiran</h3><p>Catat pertemuan pertama lewat formulir di atas.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl" id="tblSesi">
          <thead><tr><th class="ctr">Pertemuan</th><th>Tanggal</th><th>Mata Pelajaran</th><th>Materi</th><?php if (!$guruAkun): ?><th>Guru</th><?php endif; ?><th class="ctr">H</th><th class="ctr">S</th><th class="ctr">I</th><th class="ctr">A</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($sesiList as $s): ?>
            <tr>
              <td class="ctr"><b><?= (int) $s['pertemuan'] ?></b></td>
              <td class="nowrap"><?= e(tanggal_id((string) $s['tanggal'], true)) ?></td>
              <td><?= e((string) $s['mapel_nama']) ?></td>
              <td><?= e((string) ($s['materi'] ?: '-')) ?></td>
              <?php if (!$guruAkun): ?><td><?= e((string) ($s['guru_nama'] ?: '-')) ?></td><?php endif; ?>
              <td class="ctr"><span class="pill pill-st-H"><?= (int) $s['jml_h'] ?></span></td>
              <td class="ctr"><span class="pill pill-st-S"><?= (int) $s['jml_s'] ?></span></td>
              <td class="ctr"><span class="pill pill-st-I"><?= (int) $s['jml_i'] ?></span></td>
              <td class="ctr"><span class="pill pill-st-A"><?= (int) $s['jml_a'] ?></span></td>
              <td>
                <div class="row-actions">
                  <?php if ($bolehIsi): ?>
                    <a class="btn btn-ghost btn-sm" href="absensi_form.php?id=<?= (int) $s['id'] ?>"><?= ikon('edit', 15) ?> Ubah</a>
                  <?php endif; ?>
                  <?php if ($bolehKelola): ?>
                    <form method="post" data-confirm="Hapus catatan pertemuan <?= e((string) $s['pertemuan']) ?> tanggal <?= e(tanggal_id((string) $s['tanggal'])) ?>?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                      <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 15) ?></button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h2>Rekap Kehadiran per Siswa</h2>
        <p><?= e(kelas_nama($fKelas)) ?><?= $fMapel > 0 ? ' &middot; ' . e(mapel_nama($fMapel)) : ' &middot; semua mata pelajaran' ?>
          <?= ($fDari || $fSampai) ? ' &middot; ' . ($fDari ? e(tanggal_id($fDari)) : 'awal') . ' s/d ' . ($fSampai ? e(tanggal_id($fSampai)) : 'akhir') : '' ?></p>
      </div>
    </div>
    <?php if (!$siswa): ?>
      <div class="empty"><?= ikon('student', 34) ?><h3>Belum ada siswa pada kelas ini</h3><p>Tambahkan data siswa terlebih dahulu.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl" id="tblRekap">
          <thead><tr><th>#</th><th>Nama Siswa</th><th class="ctr">Hadir</th><th class="ctr">Sakit</th><th class="ctr">Izin</th><th class="ctr">Alpa</th><th class="ctr">Pertemuan</th><th class="ctr">% Hadir</th></tr></thead>
          <tbody>
          <?php foreach ($siswa as $i => $s): $r = $rekap[(int) $s['id']] ?? ['h' => 0, 's' => 0, 'i' => 0, 'a' => 0, 'total' => 0, 'persen' => null]; ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><b><?= e((string) $s['nama']) ?></b><?php if ($s['nis']): ?><span class="tbl-sub">NIS <?= e((string) $s['nis']) ?></span><?php endif; ?></td>
              <td class="ctr"><b><?= (int) $r['h'] ?></b></td>
              <td class="ctr"><?= (int) $r['s'] ?></td>
              <td class="ctr"><?= (int) $r['i'] ?></td>
              <td class="ctr"><?= (int) $r['a'] ?></td>
              <td class="ctr"><?= (int) $r['total'] ?></td>
              <td class="ctr">
                <?php if ($r['persen'] === null): ?>
                  <span class="mut xs">-</span>
                <?php else: ?>
                  <span class="pill <?= $r['persen'] >= 90 ? 'pill-A' : ($r['persen'] >= 75 ? 'pill-C' : 'pill-D') ?>"><?= e(r1($r['persen'])) ?>%</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="legend mt16">
        <span><i style="background:#10b981"></i>H = Hadir</span>
        <span><i style="background:#f59e0b"></i>S = Sakit</span>
        <span><i style="background:#0ea5e9"></i>I = Izin</span>
        <span><i style="background:#dc2626"></i>A = Alpa</span>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php
page_bottom();
