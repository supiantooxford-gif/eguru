<?php
/**
 * Catatan Pelanggaran Siswa — pencatatan dan penanganan pelanggaran/peristiwa siswa.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_sekolah(); // super admin tanpa konteks dialihkan ke Kelola Sekolah
$bolehIsi = boleh_transaksi();
$bolehHapus = boleh_master(); // menghapus catatan kasus hanya Super Admin/Operator
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
        tolak403('Pencatatan kasus hanya untuk Super Admin, Admin Operator, dan Guru.');
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
            tolak403('Menghapus catatan kasus hanya untuk Super Admin dan Admin Operator.');
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
        q('UPDATE kasus SET tanggal=?, siswa_id=?, kelas_id=?, jenis=?, judul=?, deskripsi=?, tindakan=?, status=? WHERE id=? AND sekolah_id=?',
            [$tanggal, $siswaId, (int) $siswa['kelas_id'], $jenis, $judul, $deskripsi, $tindakan, $status, $id, sekolah_id()]);
        flash('Catatan kasus diperbarui.');
    } else {
        $guruId = is_guru() ? guru_id_saya() : (int) ($_POST['guru_id'] ?? 0);
        q('INSERT INTO kasus (sekolah_id, tanggal, siswa_id, kelas_id, jenis, judul, deskripsi, tindakan, status, guru_id, dibuat, dibuat_oleh)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [sekolah_id(), $tanggal, $siswaId, (int) $siswa['kelas_id'], $jenis, $judul, $deskripsi, $tindakan, $status,
             $guruId > 0 ? $guruId : null, date('Y-m-d H:i:s'), (int) $u['id']]);
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
$cari = trim((string) ($_GET['cari'] ?? ''));

$w = ['ks.sekolah_id = ?'];
$p = [sekolah_id()];
if (is_guru()) {
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
if ($cari !== '') {
    $w[] = '(s.nama LIKE ? OR ks.judul LIKE ? OR ks.deskripsi LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($p, $like, $like, $like);
}
$where = ' WHERE ' . implode(' AND ', $w);

$rows = all('SELECT ks.*, s.nama AS siswa_nama, s.nis, k.nama AS kelas_nama, g.nama AS guru_nama
    FROM kasus ks LEFT JOIN siswa s ON s.id = ks.siswa_id LEFT JOIN kelas k ON k.id = ks.kelas_id LEFT JOIN guru g ON g.id = ks.guru_id'
    . $where . ' ORDER BY ks.tanggal DESC, ks.id DESC LIMIT 400', $p);

$ringkas = ['baru' => 0, 'proses' => 0, 'selesai' => 0];
foreach (all('SELECT status, COUNT(*) AS jml FROM kasus WHERE sekolah_id = ? GROUP BY status', [sekolah_id()]) as $r) {
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

page_top('Catatan Pelanggaran Siswa');
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
        <h2><?= $edit ? 'Ubah Catatan Kasus' : 'Catat Kasus Baru' ?></h2>
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
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <div><h2>Daftar Catatan Kasus</h2><p><span id="tblCount"><?= count($rows) ?></span> catatan ditemukan</p></div>
  </div>

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
    <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
    <?php if ($cari !== '' || $fKelas > 0 || $fStatus !== '' || $fJenis !== ''): ?><a class="btn btn-ghost" href="kasus.php">Reset</a><?php endif; ?>
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
        <thead><tr><th>Tanggal</th><th>Siswa</th><th>Jenis</th><th>Uraian &amp; Tindakan</th><th class="ctr">Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="nowrap"><?= e(tanggal_id((string) $r['tanggal'])) ?><span class="tbl-sub"><?= e(hari_id((string) $r['tanggal'])) ?></span></td>
            <td>
              <b><?= e((string) ($r['siswa_nama'] ?: '-')) ?></b>
              <span class="tbl-sub"><?= e((string) ($r['kelas_nama'] ?: '-')) ?><?= $r['nis'] ? ' &middot; NIS ' . e((string) $r['nis']) : '' ?></span>
            </td>
            <td><span class="tagmini"><?= e($jenisList[(string) $r['jenis']] ?? 'Lainnya') ?></span></td>
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
