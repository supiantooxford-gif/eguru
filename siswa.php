<?php
/**
 * Data Siswa. Guru hanya melihat siswa pada kelas yang diajar;
 * pengubahan hanya Super Admin & Admin Operator.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_sekolah(); // super admin tanpa konteks dialihkan ke Kelola Sekolah
$bolehUbah = boleh_master();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$bolehUbah) {
        tolak403('Hanya Super Admin dan Admin Operator yang dapat mengubah data siswa.');
    }
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $s = siswa_by_id($id);
    if (!$s) {
        flash('Data siswa tidak ditemukan.', 'err');
        redirect('siswa.php');
    }

    if ($aksi === 'toggle') {
        q('UPDATE siswa SET aktif = ? WHERE id = ? AND sekolah_id = ?', [(int) $s['aktif'] === 1 ? 0 : 1, $id, sekolah_id()]);
        flash('Status siswa ' . $s['nama'] . ' diperbarui.', 'warn');
    } elseif ($aksi === 'hapus') {
        $nilai = (int) val('SELECT COUNT(*) FROM nilai WHERE siswa_id = ? AND sekolah_id = ?', [$id, sekolah_id()], 0);
        $hadir = (int) val('SELECT COUNT(*) FROM absensi_detail WHERE siswa_id = ?', [$id], 0);
        $kasus = (int) val('SELECT COUNT(*) FROM kasus WHERE siswa_id = ? AND sekolah_id = ?', [$id, sekolah_id()], 0);
        q('DELETE FROM siswa WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
        flash('Siswa ' . $s['nama'] . ' beserta ' . $nilai . ' baris nilai, ' . $hadir . ' catatan kehadiran, dan ' . $kasus . ' catatan kasus dihapus.', 'warn');
    }
    redirect('siswa.php');
}

$fKelas = (int) ($_GET['kelas'] ?? 0);
$cari = trim((string) ($_GET['cari'] ?? ''));
$fStatus = (string) ($_GET['status'] ?? '');

$w = ['s.sekolah_id = ?'];
$p = [sekolah_id()];
if (is_guru()) {
    $diampu = array_map('intval', array_column(kelas_diampu(), 'id'));
    if (!$diampu) {
        $w[] = '1 = 0';
    } else {
        if ($fKelas > 0 && !in_array($fKelas, $diampu, true)) {
            tolak403('Kelas itu tidak Anda ampu.');
        }
        $w[] = 's.kelas_id IN (' . implode(',', array_fill(0, count($diampu), '?')) . ')';
        array_push($p, ...$diampu);
    }
}
if ($fKelas > 0) {
    $w[] = 's.kelas_id = ?';
    $p[] = $fKelas;
}
if (in_array($fStatus, ['1', '0'], true)) {
    $w[] = 's.aktif = ' . (int) $fStatus;
}
if ($cari !== '') {
    $w[] = '(s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR s.nama_wali LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($p, $like, $like, $like, $like);
}
$where = ' WHERE ' . implode(' AND ', $w);

$rows = all('SELECT s.*, k.nama AS kelas_nama,
    (SELECT COUNT(*) FROM nilai n WHERE n.siswa_id = s.id) AS jml_nilai,
    (SELECT COUNT(*) FROM kasus ks WHERE ks.siswa_id = s.id AND ks.status <> "selesai") AS kasus_aktif
    FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id' . $where . ' ORDER BY k.urutan, k.nama, s.nama', $p);

$sid = sekolah_id();
$totalAktif = (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0);
$totalL = (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND jenis_kelamin = "L" AND sekolah_id = ?', [$sid], 0);
$totalP = (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND jenis_kelamin = "P" AND sekolah_id = ?', [$sid], 0);

page_top('Data Siswa');
?>
<div class="grid g4">
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('student', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $totalAktif ?></div><div class="stat-lbl">Siswa aktif</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic s"><?= ikon('user', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $totalL ?></div><div class="stat-lbl">Laki-laki</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic p"><?= ikon('user', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $totalP ?></div><div class="stat-lbl">Perempuan</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic o"><?= ikon('school', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND kelas_id IS NULL AND sekolah_id = ?', [$sid], 0) ?></div><div class="stat-lbl">Belum berkelas</div></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Daftar Siswa</h2><p><span id="tblCount"><?= count($rows) ?></span> data ditemukan</p></div>
    <div class="flex">
      <?php if ($bolehUbah): ?>
        <a class="btn btn-primary" href="siswa_edit.php"><?= ikon('plus', 16) ?> Tambah Siswa</a>
        <a class="btn btn-ghost" href="siswa_import.php"><?= ikon('upload', 16) ?> Impor Excel/CSV</a>
      <?php endif; ?>
      <a class="btn btn-ghost" href="absensi.php<?= $fKelas > 0 ? '?kelas=' . (int) $fKelas : '' ?>"><?= ikon('clipboard', 16) ?> Daftar Hadir</a>
    </div>
  </div>

  <form class="filterbar mb16" method="get">
    <div>
      <label class="f" for="cari">Cari</label>
      <input type="search" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="Nama / NIS / NISN / wali...">
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
        <option value="">Semua</option>
        <option value="1" <?= $fStatus === '1' ? 'selected' : '' ?>>Aktif</option>
        <option value="0" <?= $fStatus === '0' ? 'selected' : '' ?>>Nonaktif</option>
      </select>
    </div>
    <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
    <?php if ($cari !== '' || $fKelas > 0 || $fStatus !== ''): ?><a class="btn btn-ghost" href="siswa.php">Reset</a><?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="empty">
      <?= ikon('student', 34) ?>
      <h3>Belum ada data siswa</h3>
      <p><?= is_guru() ? 'Belum ada siswa pada kelas yang Anda ajar.' : 'Tambahkan satu per satu atau impor sekaligus dari berkas Excel/CSV.' ?></p>
      <?php if ($bolehUbah): ?>
        <div class="flex" style="justify-content:center">
          <a class="btn btn-primary" href="siswa_edit.php"><?= ikon('plus', 16) ?> Tambah Siswa</a>
          <a class="btn btn-ghost" href="siswa_import.php"><?= ikon('upload', 16) ?> Impor Excel/CSV</a>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblSiswa">
        <thead><tr><th>Nama</th><th>NIS / NISN</th><th class="ctr">JK</th><th>Kelas</th><th>Wali</th><th class="ctr">Nilai</th><th class="ctr">Kasus</th><th class="ctr">Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $s): ?>
          <tr>
            <td>
              <span class="mini-avatar">
                <?= avatar_html((string) $s['nama'], 32) ?>
                <span><b><?= e((string) $s['nama']) ?></b><span class="tbl-sub"><?= e((string) ($s['tanggal_lahir'] ? tanggal_id((string) $s['tanggal_lahir']) : '-')) ?></span></span>
              </span>
            </td>
            <td class="nowrap"><?= e((string) ($s['nis'] ?: '-')) ?><span class="tbl-sub"><?= e((string) ($s['nisn'] ?: '-')) ?></span></td>
            <td class="ctr"><?= e((string) ($s['jenis_kelamin'] ?: '-')) ?></td>
            <td><?= $s['kelas_nama'] ? '<span class="tagmini">' . e((string) $s['kelas_nama']) . '</span>' : '<span class="mut xs">belum berkelas</span>' ?></td>
            <td><?= e((string) ($s['nama_wali'] ?: '-')) ?><?php if ($s['telepon']): ?><span class="tbl-sub"><?= e((string) $s['telepon']) ?></span><?php endif; ?></td>
            <td class="ctr"><?= (int) $s['jml_nilai'] ?></td>
            <td class="ctr">
              <?php if ((int) $s['kasus_aktif'] > 0): ?>
                <span class="pill pill-C"><?= (int) $s['kasus_aktif'] ?></span>
              <?php else: ?>
                <span class="mut xs">-</span>
              <?php endif; ?>
            </td>
            <td class="ctr"><span class="pill <?= (int) $s['aktif'] === 1 ? 'pill-ok' : 'pill-gray' ?>"><?= (int) $s['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
            <td>
              <?php if ($bolehUbah): ?>
                <div class="row-actions">
                  <a class="btn btn-ghost btn-sm" href="nilai.php?kelas=<?= (int) $s['kelas_id'] ?>" title="Daftar nilai"><?= ikon('chart', 15) ?></a>
                  <a class="btn btn-ghost btn-sm" href="siswa_edit.php?id=<?= (int) $s['id'] ?>"><?= ikon('edit', 15) ?></a>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="toggle">
                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $s['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                  </form>
                  <form method="post" data-confirm="Hapus siswa <?= e((string) $s['nama']) ?>? Nilai, catatan kehadiran, dan catatan kasusnya ikut terhapus.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 15) ?></button>
                  </form>
                </div>
              <?php else: ?>
                <span class="mut xs">hanya lihat</span>
              <?php endif; ?>
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
