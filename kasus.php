<?php
/**
 * PELANGGARAN SISWA (dulu "Catatan Pelanggaran Siswa"; permintaan pemilik v1.23.0 mengubah
 * nama menunya menjadi "Pelanggaran Siswa") — pencatatan dan penanganan pelanggaran/peristiwa siswa.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_sekolah(); // super admin tanpa konteks dialihkan ke Kelola Sekolah
// Permintaan pemilik (v1.21.0): Admin Operator hanya melihat & mencetak catatan pelanggaran siswa.
$bolehIsi = boleh_isi_administrasi();
$bolehHapus = boleh_hapus_administrasi(); // menghapus catatan kasus (sejak v1.21.0: Super Admin)
$konteks = konteks_ajaran();

$jenisList = kasus_jenis();
$statusList = kasus_status();

/** Siswa yang boleh dipilih/dilihat sesuai peran. */
function kasus_siswa_pilihan(): array
{
    if (is_guru()) {
        $kelas = array_map('intval', array_column(kelas_diampu(), 'id'));
        if (!$kelas) {
            return [];
        }
        return all('SELECT s.id, s.nama, s.nis, s.kelas_id, k.nama AS kelas_nama
                    FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
                    WHERE s.aktif = 1 AND s.sekolah_id = ? AND s.kelas_id IN (' . implode(',', array_fill(0, count($kelas), '?')) . ')
                    ORDER BY k.urutan, k.nama, s.nama', array_merge([sekolah_id()], $kelas));
    }
    return all('SELECT s.id, s.nama, s.nis, s.kelas_id, k.nama AS kelas_nama
                FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
                WHERE s.aktif = 1 AND s.sekolah_id = ? ORDER BY k.urutan, k.nama, s.nama', [sekolah_id()]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$bolehIsi) {
        tolak403('Akun Admin Operator hanya dapat melihat dan mencetak pelanggaran siswa. '
            . 'Pencatatannya dilakukan guru yang bersangkutan.');
    }
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $k = $id > 0 ? one('SELECT * FROM kasus WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]) : null;

    if ($aksi === 'status') {
        if (!$k) {
            flash('Catatan kasus tidak ditemukan.', 'err');
        } elseif (is_guru() && !in_array((int) $k['kelas_id'], array_map('intval', array_column(kelas_diampu(), 'id')), true)) {
            tolak403('Catatan kasus ini di luar kelas yang Anda ampu.');
        } else {
            $baru = (string) ($_POST['status'] ?? 'baru');
            if (!array_key_exists($baru, $statusList)) {
                $baru = 'baru';
            }
            q('UPDATE kasus SET status = ? WHERE id = ? AND sekolah_id = ?', [$baru, $id, sekolah_id()]);
            flash('Status kasus diperbarui menjadi "' . $statusList[$baru] . '".', 'warn');
        }
        redirect('kasus.php');
    }

    if ($aksi === 'hapus') {
        if (!$k) {
            flash('Catatan kasus tidak ditemukan.', 'err');
        } elseif (is_guru() && (int) $k['guru_id'] !== guru_id_saya()) {
            tolak403('Catatan kasus ini bukan Anda yang membuat.');
        } elseif (!$bolehHapus) {
            tolak403('Menghapus pelanggaran siswa hanya untuk Super Admin. '
                . 'Admin Operator hanya dapat melihat dan mencetak.');
        } else {
            q('DELETE FROM kasus WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
            flash('Catatan kasus dihapus.', 'warn');
        }
        redirect('kasus.php');
    }

    // simpan (tambah / ubah)
    $tanggal = tgl_ok($_POST['tanggal'] ?? null);
    $siswaId = (int) ($_POST['siswa_id'] ?? 0);
    $jenis = array_key_exists((string) ($_POST['jenis'] ?? ''), $jenisList) ? (string) $_POST['jenis'] : 'lainnya';
    // Mata pelajaran kasus (permintaan pemilik: pelanggaran dapat difilter per mata pelajaran).
    // Wajib milik sekolah ini & dalam cakupan peran (guru hanya mapel yang diampu) — anti-IDOR.
    $mapelId = (int) ($_POST['mapel_id'] ?? 0);
    if ($mapelId > 0 && (!mapel_by_id($mapelId) || !boleh_mapel($mapelId))) {
        tolak403('Mata pelajaran itu tidak dapat dipakai pada catatan ini.');
    }
    $judul = trim((string) ($_POST['judul'] ?? ''));
    $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
    $tindakan = trim((string) ($_POST['tindakan'] ?? ''));
    $status = array_key_exists((string) ($_POST['status'] ?? ''), $statusList) ? (string) $_POST['status'] : 'baru';
    $siswa = siswa_by_id($siswaId);

    $galat = [];
    if ($tanggal === null) {
        $galat[] = 'Tanggal kejadian wajib diisi.';
    }
    if (!$siswa) {
        $galat[] = 'Pilih siswa yang bersangkutan.';
    } elseif (is_guru() && !in_array((int) $siswa['kelas_id'], array_map('intval', array_column(kelas_diampu(), 'id')), true)) {
        tolak403('Siswa itu di luar kelas yang Anda ampu.');
    }
    if ($deskripsi === '') {
        $galat[] = 'Uraian kasus wajib diisi.';
    }
    if ($galat) {
        foreach ($galat as $g) {
            flash($g, 'err');
        }
        redirect('kasus.php' . ($k ? '?edit=' . $id : ''));
    }

    if ($k) {
        if (is_guru() && (int) $k['guru_id'] !== guru_id_saya()) {
            tolak403('Catatan kasus ini bukan Anda yang membuat.');
        }
        q('UPDATE kasus SET tanggal=?, siswa_id=?, kelas_id=?, mapel_id=?, jenis=?, judul=?, deskripsi=?, tindakan=?, status=? WHERE id=? AND sekolah_id=?',
            [$tanggal, $siswaId, (int) $siswa['kelas_id'], $mapelId > 0 ? $mapelId : null, $jenis, $judul, $deskripsi,
             $tindakan, $status, $id, sekolah_id()]);
        flash('Catatan kasus diperbarui.');
    } else {
        $guruId = is_guru() ? guru_id_saya() : (int) ($_POST['guru_id'] ?? 0);
        q('INSERT INTO kasus (sekolah_id, tanggal, siswa_id, kelas_id, mapel_id, jenis, judul, deskripsi, tindakan, status, guru_id, dibuat, dibuat_oleh)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [sekolah_id(), $tanggal, $siswaId, (int) $siswa['kelas_id'], $mapelId > 0 ? $mapelId : null, $jenis, $judul,
             $deskripsi, $tindakan, $status, $guruId > 0 ? $guruId : null, date('Y-m-d H:i:s'), (int) $u['id']]);
        flash('Catatan kasus untuk ' . $siswa['nama'] . ' disimpan.');
    }
    redirect('kasus.php');
}

// ---------------------------------------------------------------- filter
$fKelas = (int) ($_GET['kelas'] ?? 0);
if ($fKelas > 0 && !boleh_kelas($fKelas)) {
    tolak403('Kelas itu tidak Anda ampu.');
}
$fStatus = array_key_exists((string) ($_GET['status'] ?? ''), $statusList) ? (string) $_GET['status'] : '';
$fJenis = array_key_exists((string) ($_GET['jenis'] ?? ''), $jenisList) ? (string) $_GET['jenis'] : '';
// Filter mata pelajaran (permintaan pemilik). Penjaga cakupan: guru hanya mapel yang diampu.
$fMapel = (int) ($_GET['mapel'] ?? 0);
if ($fMapel > 0 && (!mapel_by_id($fMapel) || !boleh_mapel($fMapel))) {
    tolak403('Mata pelajaran itu tidak Anda ampu.');
}
$cari = trim((string) ($_GET['cari'] ?? ''));
// Filter SATU SISWA (permintaan pemilik v1.26.0): tautan "Lihat Pelanggaran" pada halaman
// Laporan Rekap Pelanggaran Siswa mengarah ke sini dengan ?siswa=ID sehingga langsung tersaring.
// Anti-IDOR: siswa WAJIB milik sekolah ini; akun guru hanya untuk siswa pada kelas yang diajar.
$fSiswa = (int) ($_GET['siswa'] ?? 0);
$siswaFilter = null;
if ($fSiswa > 0) {
    $siswaFilter = one('SELECT s.*, k.nama AS kelas_nama FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id
                        WHERE s.id = ? AND s.sekolah_id = ?', [$fSiswa, sekolah_id()]);
    if (!$siswaFilter) {
        flash('Siswa itu tidak ada pada sekolah/madrasah ini.', 'warn');
        redirect('kasus.php');
    }
    if (is_guru() && !in_array((int) ($siswaFilter['kelas_id'] ?? 0), array_map('intval', array_column(kelas_diampu(), 'id')), true)) {
        tolak403('Siswa itu di luar kelas yang Anda ampu.');
    }
}

$w = ['ks.sekolah_id = ?'];
$p = [sekolah_id()];
if (is_guru()) {
    // Guru HANYA melihat catatan pelanggaran yang IA buat sendiri (permintaan pemilik) —
    // bukan lagi seluruh catatan pada kelas yang diajar (yang bisa ditulis guru lain).
    $w[] = 'ks.guru_id = ?';
    $p[] = guru_id_saya();
    $kelasGuru = array_map('intval', array_column(kelas_diampu(), 'id'));
    if (!$kelasGuru) {
        $w[] = '1 = 0';
    } else {
        $w[] = 'ks.kelas_id IN (' . implode(',', array_fill(0, count($kelasGuru), '?')) . ')';
        array_push($p, ...$kelasGuru);
    }
}
if ($fKelas > 0) {
    $w[] = 'ks.kelas_id = ?';
    $p[] = $fKelas;
}
if ($fStatus !== '') {
    $w[] = 'ks.status = ?';
    $p[] = $fStatus;
}
if ($fJenis !== '') {
    $w[] = 'ks.jenis = ?';
    $p[] = $fJenis;
}
if ($fMapel > 0) {
    $w[] = 'ks.mapel_id = ?';
    $p[] = $fMapel;
}
if ($fSiswa > 0) {
    $w[] = 'ks.siswa_id = ?';
    $p[] = $fSiswa;
}
if ($cari !== '') {
    $w[] = '(s.nama LIKE ? OR ks.judul LIKE ? OR ks.deskripsi LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($p, $like, $like, $like);
}
$where = ' WHERE ' . implode(' AND ', $w);

$rows = all('SELECT ks.*, s.nama AS siswa_nama, s.nis, k.nama AS kelas_nama, g.nama AS guru_nama,
                       m.nama AS mapel_nama
    FROM kasus ks LEFT JOIN siswa s ON s.id = ks.siswa_id LEFT JOIN kelas k ON k.id = ks.kelas_id
    LEFT JOIN guru g ON g.id = ks.guru_id LEFT JOIN mapel m ON m.id = ks.mapel_id'
    . $where . ' ORDER BY ks.tanggal DESC, ks.id DESC LIMIT 400', $p);

$ringkas = ['baru' => 0, 'proses' => 0, 'selesai' => 0];
// Kartu ringkasan status memakai aturan yang SAMA dengan daftar (guru = catatannya sendiri saja).
$wRingkas = ['sekolah_id = ?'];
$pRingkas = [sekolah_id()];
if (is_guru()) {
    $wRingkas[] = 'guru_id = ?';
    $pRingkas[] = guru_id_saya();
}
if ($fSiswa > 0) {
    $wRingkas[] = 'siswa_id = ?';
    $pRingkas[] = $fSiswa;
}
foreach (all('SELECT status, COUNT(*) AS jml FROM kasus WHERE ' . implode(' AND ', $wRingkas) . ' GROUP BY status', $pRingkas) as $r) {
    if (isset($ringkas[(string) $r['status']])) {
        $ringkas[(string) $r['status']] = (int) $r['jml'];
    }
}

$edit = (int) ($_GET['edit'] ?? 0) > 0
    ? one('SELECT * FROM kasus WHERE id = ? AND sekolah_id = ?', [(int) $_GET['edit'], sekolah_id()]) : null;
if ($edit && is_guru() && (int) $edit['guru_id'] !== guru_id_saya()) {
    tolak403('Catatan kasus ini bukan Anda yang membuat.');
}
$siswaPilihan = kasus_siswa_pilihan();

// Filter yang sedang aktif → dipakai tombol "Cetak Laporan" supaya lembar cetak sama dengan layar.
$qCetak = array_filter([
    'kelas' => $fKelas ?: null, 'status' => $fStatus ?: null, 'jenis' => $fJenis ?: null,
    'mapel' => $fMapel ?: null, 'cari' => $cari ?: null, 'siswa' => $fSiswa ?: null,
]);

page_top('Pelanggaran Siswa');
?>
<div class="grid g4">
  <?php foreach ($statusList as $kode => $label): ?>
    <div class="card stat card-tight">
      <div class="stat-ic <?= $kode === 'selesai' ? 'g' : ($kode === 'proses' ? 'o' : 'p') ?>"><?= ikon('alert', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) ($ringkas[$kode] ?? 0) ?></div><div class="stat-lbl"><?= e($label) ?></div></div>
    </div>
  <?php endforeach; ?>
  <div class="card stat card-tight">
    <div class="stat-ic s"><?= ikon('doc', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= count($rows) ?></div><div class="stat-lbl">Baris ditampilkan</div></div>
  </div>
</div>

<?php if (is_guru() && guru_tanpa_kelas()): ?>
  <?= kartu_tanpa_penugasan('Anda belum ditugaskan ke kelas mana pun',
      'Catatan pelanggaran siswa diisi untuk siswa pada kelas yang Anda ajar, tetapi belum ada kelas yang ditetapkan untuk akun Anda.') ?>
<?php elseif ($bolehIsi): ?>
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= $edit ? 'Ubah Pelanggaran Siswa' : 'Catat Pelanggaran Baru' ?></h2>
        <p>Catat peristiwa penting siswa beserta penanganannya sebagai bukti pembinaan.</p>
      </div>
      <?php if ($edit): ?><a class="btn btn-ghost btn-sm" href="kasus.php">Batal</a><?php endif; ?>
    </div>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div class="grid g4" style="gap:16px">
        <div>
          <label class="f" for="tanggal">Tanggal Kejadian *</label>
          <input type="date" id="tanggal" name="tanggal" value="<?= e((string) ($edit['tanggal'] ?? date('Y-m-d'))) ?>" required>
        </div>
        <div style="grid-column:span 2">
          <label class="f" for="siswa_id">Siswa *</label>
          <select id="siswa_id" name="siswa_id" required>
            <option value="">— pilih siswa —</option>
            <?php
            $grupTerakhir = null;
            foreach ($siswaPilihan as $s):
                $grup = (string) ($s['kelas_nama'] ?? 'Belum berkelas');
                if ($grup !== $grupTerakhir) {
                    if ($grupTerakhir !== null) {
                        echo '</optgroup>';
                    }
                    echo '<optgroup label="' . e($grup) . '">';
                    $grupTerakhir = $grup;
                }
            ?>
              <option value="<?= (int) $s['id'] ?>" <?= (int) ($edit['siswa_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e((string) $s['nama']) ?><?= $s['nis'] ? ' (' . e((string) $s['nis']) . ')' : '' ?></option>
            <?php endforeach; ?>
            <?php if ($grupTerakhir !== null) { echo '</optgroup>'; } ?>
          </select>
        </div>
        <div>
          <label class="f" for="jenis">Jenis Kasus</label>
          <select id="jenis" name="jenis">
            <?php foreach ($jenisList as $kode => $label): ?>
              <option value="<?= e($kode) ?>" <?= (string) ($edit['jenis'] ?? '') === $kode ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <?php // Mata pelajaran kasus (permintaan pemilik): dipakai untuk memfilter pelanggaran
                // per mata pelajaran. Guru hanya dapat memilih mapel yang diampu. ?>
          <label class="f" for="mapel_id">Mata Pelajaran</label>
          <select id="mapel_id" name="mapel_id">
            <option value="">— tidak terkait mata pelajaran —</option>
            <?php foreach (mapel_pilih() as $m): ?>
              <option value="<?= (int) $m['id'] ?>" <?= (int) ($edit['mapel_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= e((string) $m['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="judul">Judul Singkat</label>
          <input type="text" id="judul" name="judul" value="<?= e((string) ($edit['judul'] ?? '')) ?>" placeholder="mis. Tidak mengumpulkan tugas 3 kali">
        </div>
        <div>
          <label class="f" for="status">Status Penanganan</label>
          <select id="status" name="status">
            <?php foreach ($statusList as $kode => $label): ?>
              <option value="<?= e($kode) ?>" <?= (string) ($edit['status'] ?? 'baru') === $kode ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div>
        <label class="f" for="deskripsi">Uraian Kasus *</label>
        <textarea id="deskripsi" name="deskripsi" rows="3" required placeholder="Kronologi kejadian..."><?= e((string) ($edit['deskripsi'] ?? '')) ?></textarea>
      </div>
      <div>
        <label class="f" for="tindakan">Tindakan / Penanganan</label>
        <textarea id="tindakan" name="tindakan" rows="2" placeholder="Langkah yang sudah dilakukan, hasil pembinaan, komunikasi dengan orang tua..."><?= e((string) ($edit['tindakan'] ?? '')) ?></textarea>
      </div>
      <?php if (!is_guru()): ?>
        <div>
          <label class="f" for="guru_id">Petugas Pencatat</label>
          <select id="guru_id" name="guru_id" <?= $edit ? 'disabled' : '' ?>>
            <option value="">— tidak ditentukan —</option>
            <?php foreach (guru_list(true) as $g): ?>
              <option value="<?= (int) $g['id'] ?>" <?= (int) ($edit['guru_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= e((string) $g['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $edit ? 'Simpan Perubahan' : 'Simpan Catatan Kasus' ?></button>
        <?php if (!$siswaPilihan): ?><span class="hint">Belum ada siswa yang dapat dipilih — tambahkan data siswa terlebih dahulu.</span><?php endif; ?>
      </div>
    </form>
  </div>
<?php elseif (!$bolehIsi): ?>
  <?php // Permintaan pemilik v1.21.0: Admin Operator hanya melihat & mencetak catatan pelanggaran siswa. ?>
  <div class="alert alert-warn">
    <?= ikon('eye', 16) ?>
    <span><?= pemberitahuan_hanya_lihat_administrasi('pelanggaran siswa') ?></span>
  </div>
<?php endif; ?>

<div class="card" id="tabelKasus">
  <div class="card-head">
    <div><h2>Daftar Pelanggaran Siswa</h2><p><span id="tblCount"><?= count($rows) ?></span> catatan ditemukan<?= $qCetak ? ' (sesuai filter)' : '' ?></p></div>
    <div class="flex">
      <a class="btn btn-ghost" href="cetak_kasus.php?<?= e(http_build_query($qCetak)) ?>" target="_blank"><?= ikon('print', 16) ?> Cetak Laporan</a>
    </div>
  </div>

  <?php // Penanda filter satu siswa (datang dari tautan "Lihat Pelanggaran" pada Laporan Rekap
        // Pelanggaran Siswa) supaya jelas daftar ini sedang tersaring untuk siswa tersebut. ?>
  <?php if ($siswaFilter): ?>
    <div class="alert alert-ok mb16" style="background:rgba(201,162,39,.08);border-color:rgba(201,162,39,.5)">
      <?= ikon('student', 16) ?>
      <span>Menampilkan pelanggaran <b><?= e((string) $siswaFilter['nama']) ?></b>
        (<?= e((string) ($siswaFilter['kelas_nama'] ?: 'tanpa kelas')) ?><?= trim((string) ($siswaFilter['nisn'] ?? '')) !== '' ? ' &middot; NISN ' . e((string) $siswaFilter['nisn']) : '' ?>)
        — <b><?= count($rows) ?></b> catatan.</span>
      <a class="btn btn-ghost btn-sm" href="kasus.php"><?= ikon('prev', 15) ?> Tampilkan Semua</a>
    </div>
  <?php endif; ?>

  <form class="filterbar mb16" method="get">
    <div>
      <label class="f" for="cari">Cari</label>
      <input type="search" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="nama siswa / judul / uraian...">
    </div>
    <div>
      <label class="f" for="kelas">Kelas</label>
      <select id="kelas" name="kelas" data-auto>
        <option value=""><?= is_guru() ? 'Semua kelas yang saya ajar' : 'Semua kelas' ?></option>
        <?php foreach (kelas_pilih() as $k): ?>
          <option value="<?= (int) $k['id'] ?>" <?= $fKelas === (int) $k['id'] ? 'selected' : '' ?>><?= e((string) $k['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="f" for="status">Status</label>
      <select id="status" name="status" data-auto>
        <option value="">Semua status</option>
        <?php foreach ($statusList as $kode => $label): ?>
          <option value="<?= e($kode) ?>" <?= $fStatus === $kode ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="f" for="jenis">Jenis</label>
      <select id="jenis" name="jenis" data-auto>
        <option value="">Semua jenis</option>
        <?php foreach ($jenisList as $kode => $label): ?>
          <option value="<?= e($kode) ?>" <?= $fJenis === $kode ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php // Filter mata pelajaran: ditampilkan untuk Super Admin, Kepala Sekolah/Madrasah, dan
          // Admin Operator (permintaan pemilik) supaya pelanggaran per mata pelajaran dapat diketahui.
          // Daftar akun guru sudah dibatasi hanya kelas/mapelnya sendiri, jadi filter ini tidak
          // ditampilkan untuk guru. ?>
    <?php if (!is_guru()): ?>
      <div>
        <label class="f" for="mapel">Mata Pelajaran</label>
        <select id="mapel" name="mapel" data-auto>
          <option value="">Semua mata pelajaran</option>
          <?php foreach (mapel_list(true) as $m): ?>
            <option value="<?= (int) $m['id'] ?>" <?= $fMapel === (int) $m['id'] ? 'selected' : '' ?>><?= e((string) $m['nama']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Pilih satu mata pelajaran untuk melihat kasus pada mapel tersebut.</div>
      </div>
    <?php endif; ?>
    <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
    <?php if ($cari !== '' || $fKelas > 0 || $fStatus !== '' || $fJenis !== '' || $fMapel > 0 || $fSiswa > 0): ?><a class="btn btn-ghost" href="kasus.php">Reset</a><?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="empty">
      <?= ikon('alert', 34) ?>
      <h3>Belum ada catatan kasus</h3>
      <p><?= $bolehIsi ? 'Catat kasus siswa lewat formulir di atas.' : 'Belum ada kasus yang tercatat.' ?></p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblKasus">
        <thead><tr><th>Tanggal</th><th>Siswa</th><th>Jenis</th><th>Mata Pelajaran</th><th>Uraian &amp; Tindakan</th><th class="ctr">Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="nowrap"><?= e(tanggal_id((string) $r['tanggal'])) ?><span class="tbl-sub"><?= e(hari_id((string) $r['tanggal'])) ?></span></td>
            <td>
              <b><?= e((string) ($r['siswa_nama'] ?: '-')) ?></b>
              <span class="tbl-sub"><?= e((string) ($r['kelas_nama'] ?: '-')) ?><?= $r['nis'] ? ' &middot; NIS ' . e((string) $r['nis']) : '' ?></span>
            </td>
            <td><span class="tagmini"><?= e($jenisList[(string) $r['jenis']] ?? 'Lainnya') ?></span></td>
            <td>
              <?php if (trim((string) ($r['mapel_nama'] ?? '')) !== ''): ?>
                <span class="tagmini"><?= e((string) $r['mapel_nama']) ?></span>
              <?php else: ?>
                <span class="mut xs">—</span>
              <?php endif; ?>
            </td>
            <td class="sm">
              <?php if ($r['judul']): ?><b><?= e((string) $r['judul']) ?></b><br><?php endif; ?>
              <?= e((string) ($r['deskripsi'] ?: '-')) ?>
              <?php if ($r['tindakan']): ?><br><span class="mut"><b>Tindakan:</b> <?= e((string) $r['tindakan']) ?></span><?php endif; ?>
              <?php if ($r['guru_nama']): ?><br><span class="xs mut">Dicatat oleh: <?= e((string) $r['guru_nama']) ?></span><?php endif; ?>
            </td>
            <td class="ctr">
              <span class="pill <?= $r['status'] === 'selesai' ? 'pill-A' : ($r['status'] === 'proses' ? 'pill-C' : 'pill-D') ?>"><?= e($statusList[(string) $r['status']] ?? '-') ?></span>
            </td>
            <td>
              <div class="row-actions">
                <?php if ($bolehIsi): ?>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="status">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <select name="status" data-auto style="width:auto;padding:6px 30px 6px 10px;font-size:.78rem">
                      <?php foreach ($statusList as $kode => $label): ?>
                        <option value="<?= e($kode) ?>" <?= (string) $r['status'] === $kode ? 'selected' : '' ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <noscript><button class="btn btn-ghost btn-sm" type="submit">Ubah</button></noscript>
                  </form>
                  <a class="btn btn-ghost btn-sm" href="kasus.php?edit=<?= (int) $r['id'] ?>"><?= ikon('edit', 15) ?></a>
                <?php endif; ?>
                <?php if ($bolehHapus): ?>
                  <form method="post" data-confirm="Hapus catatan kasus ini?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
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
<?php
page_bottom();
