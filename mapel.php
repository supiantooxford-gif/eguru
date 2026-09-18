<?php
/**
 * Data Master — Mata Pelajaran.
 * Dipakai pada Data Guru (mata pelajaran yang dipegang), daftar nilai, dan jurnal.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_master();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $m = $id > 0 ? mapel_by_id($id) : null;

    if ($aksi === 'simpan') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $kode = trim((string) ($_POST['kode'] ?? ''));
        $kelompok = trim((string) ($_POST['kelompok'] ?? ''));
        $urutan = (int) ($_POST['urutan'] ?? 0);
        if ($nama === '') {
            flash('Nama mata pelajaran wajib diisi.', 'err');
        } else {
            $now = date('Y-m-d H:i:s');
            if ($m) {
                q('UPDATE mapel SET nama = ?, kode = ?, kelompok = ?, urutan = ? WHERE id = ? AND sekolah_id = ?',
                    [$nama, $kode, $kelompok, $urutan, $id, sekolah_id()]);
                flash('Mata pelajaran ' . $nama . ' diperbarui.');
            } else {
                q('INSERT INTO mapel (sekolah_id, kode, nama, kelompok, urutan, aktif) VALUES (?,?,?,?,?,1)',
                    [sekolah_id(), $kode, $nama, $kelompok, $urutan]);
                flash('Mata pelajaran ' . $nama . ' ditambahkan.');
            }
        }
        redirect('mapel.php');
    }

    if (!$m) {
        flash('Mata pelajaran tidak ditemukan.', 'err');
        redirect('mapel.php');
    }

    if ($aksi === 'toggle') {
        q('UPDATE mapel SET aktif = ? WHERE id = ? AND sekolah_id = ?', [(int) $m['aktif'] === 1 ? 0 : 1, $id, sekolah_id()]);
        flash('Status mata pelajaran ' . $m['nama'] . ' diperbarui.', 'warn');
    } elseif ($aksi === 'hapus') {
        $dipakai = (int) val('SELECT COUNT(*) FROM nilai WHERE mapel_id = ? AND sekolah_id = ?', [$id, sekolah_id()], 0)
                 + (int) val('SELECT COUNT(*) FROM absensi WHERE mapel_id = ? AND sekolah_id = ?', [$id, sekolah_id()], 0)
                 + (int) val('SELECT COUNT(*) FROM jurnal WHERE mapel_id = ? AND sekolah_id = ?', [$id, sekolah_id()], 0);
        if ($dipakai > 0) {
            flash('Mata pelajaran ' . $m['nama'] . ' tidak dapat dihapus karena sudah dipakai pada ' . $dipakai . ' catatan (nilai/daftar hadir/jurnal). Nonaktifkan saja bila tidak dipakai lagi.', 'err');
        } else {
            q('DELETE FROM mapel WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
            flash('Mata pelajaran ' . $m['nama'] . ' dihapus.', 'warn');
        }
    }
    redirect('mapel.php');
}

$edit = mapel_by_id((int) ($_GET['edit'] ?? 0));
$rows = all('SELECT m.*,
    (SELECT COUNT(*) FROM guru_mapel gm WHERE gm.mapel_id = m.id) AS jml_guru,
    (SELECT COUNT(*) FROM nilai n WHERE n.mapel_id = m.id) AS jml_nilai
    FROM mapel m WHERE m.sekolah_id = ? ORDER BY m.urutan, m.nama', [sekolah_id()]);

page_top('Mata Pelajaran');
?>
<div class="grid g12">
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= $edit ? 'Ubah Mata Pelajaran' : 'Tambah Mata Pelajaran' ?></h2>
        <p>Mata pelajaran yang dipegang guru dipilih dari daftar ini.</p>
      </div>
    </div>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div>
        <label class="f" for="nama">Nama Mata Pelajaran *</label>
        <input type="text" id="nama" name="nama" value="<?= e((string) ($edit['nama'] ?? '')) ?>" required>
      </div>
      <div>
        <label class="f" for="kode">Kode</label>
        <input type="text" id="kode" name="kode" value="<?= e((string) ($edit['kode'] ?? '')) ?>" placeholder="mis. MTK">
      </div>
      <div>
        <label class="f" for="kelompok">Kelompok</label>
        <input type="text" id="kelompok" name="kelompok" value="<?= e((string) ($edit['kelompok'] ?? '')) ?>" placeholder="mis. Kelompok A / Muatan Lokal">
      </div>
      <div>
        <label class="f" for="urutan">Urutan</label>
        <input type="number" id="urutan" name="urutan" value="<?= (int) ($edit['urutan'] ?? 0) ?>">
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $edit ? 'Simpan Perubahan' : 'Tambah' ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="mapel.php">Batal</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head">
      <div><h2>Daftar Mata Pelajaran</h2><p><span id="tblCount"><?= count($rows) ?></span> mata pelajaran</p></div>
    </div>
    <?php if (!$rows): ?>
      <div class="empty"><?= ikon('folder', 34) ?><h3>Belum ada mata pelajaran</h3><p>Tambahkan lewat formulir di samping.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl" id="tblMapel">
          <thead><tr><th>Mata Pelajaran</th><th>Kelompok</th><th class="ctr">Guru</th><th class="ctr">Nilai</th><th class="ctr">Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><b><?= e((string) $r['nama']) ?></b><span class="tbl-sub"><?= e((string) ($r['kode'] ?: '-')) ?></span></td>
              <td><?= e((string) ($r['kelompok'] ?: '-')) ?></td>
              <td class="ctr"><?= (int) $r['jml_guru'] ?></td>
              <td class="ctr"><?= (int) $r['jml_nilai'] ?></td>
              <td class="ctr"><span class="pill <?= (int) $r['aktif'] === 1 ? 'pill-ok' : 'pill-gray' ?>"><?= (int) $r['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
              <td>
                <div class="row-actions">
                  <a class="btn btn-ghost btn-sm" href="mapel.php?edit=<?= (int) $r['id'] ?>"><?= ikon('edit', 15) ?></a>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="toggle">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $r['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                  </form>
                  <form method="post" data-confirm="Hapus mata pelajaran <?= e((string) $r['nama']) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 15) ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php
page_bottom();
