<?php
/**
 * Data Master — Kelas (rombel) beserta wali kelas.
 * Kelas dipakai pada Data Siswa, Data Guru (kelas yang diajar), daftar hadir, nilai, dan jurnal.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_master();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $k = $id > 0 ? kelas_by_id($id) : null;

    if ($aksi === 'simpan') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $tingkat = trim((string) ($_POST['tingkat'] ?? ''));
        $wali = (int) ($_POST['wali_id'] ?? 0);
        $ket = trim((string) ($_POST['keterangan'] ?? ''));
        $urutan = (int) ($_POST['urutan'] ?? 0);
        if ($nama === '') {
            flash('Nama kelas wajib diisi.', 'err');
        } elseif (one('SELECT id FROM kelas WHERE nama = ? AND id <> ? AND sekolah_id = ?', [$nama, $id, sekolah_id()])) {
            flash('Kelas "' . $nama . '" sudah ada.', 'err');
        } else {
            $waliId = $wali > 0 ? $wali : null;
            if ($k) {
                q('UPDATE kelas SET nama = ?, tingkat = ?, wali_id = ?, keterangan = ?, urutan = ? WHERE id = ? AND sekolah_id = ?',
                    [$nama, $tingkat, $waliId, $ket, $urutan, $id, sekolah_id()]);
                flash('Kelas ' . $nama . ' diperbarui.');
            } else {
                q('INSERT INTO kelas (sekolah_id, nama, tingkat, wali_id, keterangan, urutan, aktif) VALUES (?,?,?,?,?,?,1)',
                    [sekolah_id(), $nama, $tingkat, $waliId, $ket, $urutan]);
                flash('Kelas ' . $nama . ' ditambahkan.');
            }
        }
        redirect('kelas.php');
    }

    if (!$k) {
        flash('Kelas tidak ditemukan.', 'err');
        redirect('kelas.php');
    }

    if ($aksi === 'toggle') {
        q('UPDATE kelas SET aktif = ? WHERE id = ? AND sekolah_id = ?', [(int) $k['aktif'] === 1 ? 0 : 1, $id, sekolah_id()]);
        flash('Status kelas ' . $k['nama'] . ' diperbarui.', 'warn');
    } elseif ($aksi === 'hapus') {
        $siswa = (int) val('SELECT COUNT(*) FROM siswa WHERE kelas_id = ? AND sekolah_id = ?', [$id, sekolah_id()], 0);
        $lain = (int) val('SELECT COUNT(*) FROM nilai WHERE kelas_id = ? AND sekolah_id = ?', [$id, sekolah_id()], 0)
              + (int) val('SELECT COUNT(*) FROM absensi WHERE kelas_id = ? AND sekolah_id = ?', [$id, sekolah_id()], 0)
              + (int) val('SELECT COUNT(*) FROM jurnal WHERE kelas_id = ? AND sekolah_id = ?', [$id, sekolah_id()], 0)
              + (int) val('SELECT COUNT(*) FROM kasus WHERE kelas_id = ? AND sekolah_id = ?', [$id, sekolah_id()], 0);
        if ($siswa > 0) {
            flash('Kelas ' . $k['nama'] . ' masih memiliki ' . $siswa . ' siswa. Pindahkan siswa terlebih dahulu.', 'err');
        } elseif ($lain > 0) {
            flash('Kelas ' . $k['nama'] . ' tidak dapat dihapus karena sudah dipakai pada ' . $lain . ' catatan (nilai/daftar hadir/jurnal/kasus). Nonaktifkan saja bila tidak dipakai lagi.', 'err');
        } else {
            q('DELETE FROM kelas WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
            flash('Kelas ' . $k['nama'] . ' dihapus.', 'warn');
        }
    }
    redirect('kelas.php');
}

$edit = kelas_by_id((int) ($_GET['edit'] ?? 0));
$rows = kelas_list(false);
$guru = guru_list(true);

page_top('Kelas');
?>
<div class="grid g12">
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= $edit ? 'Ubah Kelas' : 'Tambah Kelas' ?></h2>
        <p>Wali kelas boleh dikosongkan.</p>
      </div>
    </div>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div>
        <label class="f" for="nama">Nama Kelas *</label>
        <input type="text" id="nama" name="nama" value="<?= e((string) ($edit['nama'] ?? '')) ?>" placeholder="mis. VII-A" required>
      </div>
      <?php // v49: TINGKAT memakai acuan JENJANG lembaga (permintaan pemilik). Tetap berupa isian
            // bebas + daftar saran (datalist) supaya penulisan khusus (mis. "VII-A") tetap mungkin. ?>
      <?php $tingkatAcuan = jenjang_tingkat_list(); ?>
      <div>
        <label class="f" for="tingkat">Tingkat</label>
        <input type="text" id="tingkat" name="tingkat" list="tingkatJenjang"
               value="<?= e((string) ($edit['tingkat'] ?? '')) ?>" placeholder="mis. <?= e($tingkatAcuan[0] ?? 'VII') ?>">
        <datalist id="tingkatJenjang">
          <?php foreach ($tingkatAcuan as $tk): ?><option value="<?= e($tk) ?>"></option><?php endforeach; ?>
        </datalist>
        <div class="hint">
          <?php if ($tingkatAcuan): ?>
            Acuan jenjang <b><?= e(jenjang_sekolah()) ?></b>: <?= e(jenjang_tingkat_teks()) ?>.
          <?php else: ?>
            Jenjang lembaga belum dipilih — pilih dulu pada <?= boleh_pengaturan()
              ? '<a href="pengaturan.php">Identitas &amp; Pengaturan</a>' : 'menu Identitas &amp; Pengaturan (pengelola)' ?>
            agar tingkat kelas punya acuan.
          <?php endif; ?>
        </div>
      </div>
      <div>
        <label class="f" for="wali_id">Wali Kelas</label>
        <select id="wali_id" name="wali_id">
          <option value="">— belum ditentukan —</option>
          <?php foreach ($guru as $g): ?>
            <option value="<?= (int) $g['id'] ?>" <?= (int) ($edit['wali_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= e((string) $g['nama']) ?><?= (int) ($g['guru_kelas'] ?? 0) === 1 ? ' (guru kelas)' : '' ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (is_guru_kelas_jenjang()): ?>
          <div class="hint">Guru yang ditandai <b>Guru Kelas</b> pada Data Guru otomatis menjadi wali kelas kelasnya.</div>
        <?php endif; ?>
      </div>
      <div>
        <label class="f" for="urutan">Urutan</label>
        <input type="number" id="urutan" name="urutan" value="<?= (int) ($edit['urutan'] ?? 0) ?>">
      </div>
      <div>
        <label class="f" for="keterangan">Keterangan</label>
        <input type="text" id="keterangan" name="keterangan" value="<?= e((string) ($edit['keterangan'] ?? '')) ?>">
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $edit ? 'Simpan Perubahan' : 'Tambah' ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="kelas.php">Batal</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head">
      <div><h2>Daftar Kelas</h2><p><span id="tblCount"><?= count($rows) ?></span> kelas</p></div>
    </div>
    <?php if (!$rows): ?>
      <div class="empty"><?= ikon('school', 34) ?><h3>Belum ada kelas</h3><p>Tambahkan kelas terlebih dahulu sebelum mengisi data siswa.</p></div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl" id="tblKelas">
          <thead><tr><th>Kelas</th><th>Wali Kelas</th><th class="ctr">Siswa</th><th class="ctr">Guru Pengajar</th><th class="ctr">Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><b><?= e((string) $r['nama']) ?></b><span class="tbl-sub"><?= e((string) ($r['tingkat'] ?: '-')) ?></span></td>
              <td><?= e((string) ($r['wali_nama'] ?: '-')) ?></td>
              <td class="ctr"><?= (int) $r['jml_siswa'] ?></td>
              <td class="ctr"><?= (int) $r['jml_guru'] ?></td>
              <td class="ctr"><span class="pill <?= (int) $r['aktif'] === 1 ? 'pill-ok' : 'pill-gray' ?>"><?= (int) $r['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
              <td>
                <div class="row-actions">
                  <a class="btn btn-ghost btn-sm" href="siswa.php?kelas=<?= (int) $r['id'] ?>" title="Data siswa kelas ini"><?= ikon('student', 15) ?></a>
                  <a class="btn btn-ghost btn-sm" href="kelas.php?edit=<?= (int) $r['id'] ?>"><?= ikon('edit', 15) ?></a>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="toggle">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $r['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                  </form>
                  <form method="post" data-confirm="Hapus kelas <?= e((string) $r['nama']) ?>?">
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
