<?php
/**
 * Tambah / ubah data guru, termasuk kelas yang diajar dan mata pelajaran yang dipegang.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_master();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$g = $id > 0 ? guru_by_id($id) : null;
if ($id > 0 && !$g) {
    flash('Data guru tidak ditemukan.', 'err');
    redirect('guru.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $nip = nip_bersih($_POST['nip'] ?? '');
    $nuptk = nip_bersih($_POST['nuptk'] ?? '');
    $jk = in_array((string) ($_POST['jenis_kelamin'] ?? ''), ['L', 'P'], true) ? (string) $_POST['jenis_kelamin'] : '';
    $jabatan = trim((string) ($_POST['jabatan'] ?? ''));
    $telepon = trim((string) ($_POST['telepon'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    $mapelPilih = array_values(array_unique(array_map('intval', (array) ($_POST['mapel'] ?? []))));
    $kelasPilih = array_values(array_unique(array_map('intval', (array) ($_POST['kelas'] ?? []))));

    $galat = [];
    if ($nama === '') {
        $galat[] = 'Nama guru wajib diisi.';
    }
    if ($nip !== '' && one('SELECT id FROM guru WHERE nip = ? AND id <> ? AND sekolah_id = ?', [$nip, $id, sekolah_id()])) {
        $galat[] = 'NIP ' . $nip . ' sudah dipakai guru lain.';
    }
    if ($email !== '' && strpos($email, '@') === false) {
        $galat[] = 'Alamat email tidak valid.';
    }
    if ($galat) {
        foreach ($galat as $x) {
            flash($x, 'err');
        }
        redirect($id > 0 ? 'guru_edit.php?id=' . $id : 'guru_edit.php');
    }

    $now = date('Y-m-d H:i:s');
    db()->exec('BEGIN IMMEDIATE');
    try {
        if ($g) {
            q('UPDATE guru SET nama=?, nip=?, nuptk=?, jenis_kelamin=?, jabatan=?, telepon=?, email=?, aktif=? WHERE id=? AND sekolah_id=?',
                [$nama, $nip, $nuptk, $jk, $jabatan, $telepon, $email, $aktif, $id, sekolah_id()]);
        } else {
            q('INSERT INTO guru (sekolah_id, nama, nip, nuptk, jenis_kelamin, jabatan, telepon, email, aktif, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [sekolah_id(), $nama, $nip, $nuptk, $jk, $jabatan, $telepon, $email, $aktif, $now]);
            $id = (int) db()->lastInsertId();
        }
        q('DELETE FROM guru_mapel WHERE guru_id = ?', [$id]);
        foreach ($mapelPilih as $mid) {
            if (mapel_by_id($mid)) {
                q('INSERT OR IGNORE INTO guru_mapel (guru_id, mapel_id) VALUES (?,?)', [$id, $mid]);
            }
        }
        q('DELETE FROM guru_kelas WHERE guru_id = ?', [$id]);
        foreach ($kelasPilih as $kid) {
            if (kelas_by_id($kid)) {
                q('INSERT OR IGNORE INTO guru_kelas (guru_id, kelas_id) VALUES (?,?)', [$id, $kid]);
            }
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }

    flash('Data guru ' . $nama . ' disimpan.');

    // Sekaligus buatkan akun login bila diminta dan belum ada.
    if (!empty($_POST['buat_akun'])) {
        if (one('SELECT id FROM users WHERE guru_id = ?', [$id])) {
            flash('Guru ini sudah memiliki akun login.', 'warn');
        } else {
            $guruBaru = guru_by_id($id) ?? ['nama' => $nama, 'nip' => $nip];
            $username = guru_username_unik($guruBaru);
            $sandi = guru_sandi_awal($guruBaru);
            q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, guru_id, aktif, dibuat) VALUES (?,?,?,?,?,?,1,?)',
                [sekolah_id(), $username, password_hash($sandi, PASSWORD_DEFAULT), $nama, 'guru', $id, date('Y-m-d H:i:s')]);
            flash('Akun login dibuat — nama pengguna: ' . $username . ', kata sandi awal: ' . $sandi . '.');
        }
    }
    redirect('guru_edit.php?id=' . $id);
}

$mapelSekarang = $g ? guru_mapel_ids((int) $g['id']) : [];
$kelasSekarang = $g ? guru_kelas_ids((int) $g['id']) : [];
$akun = $g ? one('SELECT username FROM users WHERE guru_id = ? AND sekolah_id = ?', [(int) $g['id'], sekolah_id()]) : null;

page_top($g ? 'Ubah Data Guru' : 'Tambah Data Guru');
?>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) ($g['id'] ?? 0) ?>">
  <div class="grid g21">
    <div class="card">
      <div class="card-head">
        <div><h2>Identitas Guru</h2><p>NIP/NUPTK boleh dikosongkan bila belum ada.</p></div>
      </div>
      <div class="form-grid">
        <div>
          <label class="f" for="nama">Nama Lengkap *</label>
          <input type="text" id="nama" name="nama" value="<?= e((string) ($g['nama'] ?? '')) ?>" required>
        </div>
        <div class="grid g2" style="gap:16px">
          <div>
            <label class="f" for="nip">NIP</label>
            <input type="text" id="nip" name="nip" value="<?= e((string) ($g['nip'] ?? '')) ?>" placeholder="boleh dikosongkan">
          </div>
          <div>
            <label class="f" for="nuptk">NUPTK</label>
            <input type="text" id="nuptk" name="nuptk" value="<?= e((string) ($g['nuptk'] ?? '')) ?>" placeholder="boleh dikosongkan">
          </div>
        </div>
        <div class="grid g3" style="gap:16px">
          <div>
            <label class="f" for="jenis_kelamin">Jenis Kelamin</label>
            <select id="jenis_kelamin" name="jenis_kelamin">
              <option value="">—</option>
              <option value="L" <?= ($g['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
              <option value="P" <?= ($g['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
            </select>
          </div>
          <div>
            <label class="f" for="jabatan">Jabatan</label>
            <input type="text" id="jabatan" name="jabatan" value="<?= e((string) ($g['jabatan'] ?? '')) ?>" placeholder="mis. Guru Madya">
          </div>
          <div>
            <label class="f" for="telepon">No. HP</label>
            <input type="text" id="telepon" name="telepon" value="<?= e((string) ($g['telepon'] ?? '')) ?>">
          </div>
        </div>
        <div>
          <label class="f" for="email">Email</label>
          <input type="text" id="email" name="email" value="<?= e((string) ($g['email'] ?? '')) ?>">
        </div>
        <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600;margin:0">
          <input type="checkbox" name="aktif" value="1" <?= (int) ($g['aktif'] ?? 1) === 1 ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--accent)">
          Guru aktif (tampil pada pilihan kelas/mapel)
        </label>
      </div>
    </div>

    <div class="grid" style="gap:20px;align-content:start">
      <div class="card">
        <div class="card-head">
          <div><h2>Mata Pelajaran yang Dipegang</h2><p>Pilih satu atau lebih.</p></div>
          <?php if (boleh_master()): ?><a class="btn btn-ghost btn-sm" href="mapel.php"><?= ikon('folder', 14) ?> Kelola</a><?php endif; ?>
        </div>
        <?php $semuaMapel = mapel_list(true); ?>
        <?php if (!$semuaMapel): ?>
          <p class="mut sm">Belum ada mata pelajaran. Tambahkan lewat menu Mata Pelajaran.</p>
        <?php else: ?>
          <div class="grid g2" style="gap:8px">
            <?php foreach ($semuaMapel as $m): ?>
              <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:500;margin:0">
                <input type="checkbox" name="mapel[]" value="<?= (int) $m['id'] ?>" <?= in_array((int) $m['id'], $mapelSekarang, true) ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--accent)">
                <?= e((string) $m['nama']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-head">
          <div><h2>Kelas yang Diajar</h2><p>Pilih kelas yang dibina/diajar.</p></div>
          <?php if (boleh_master()): ?><a class="btn btn-ghost btn-sm" href="kelas.php"><?= ikon('school', 14) ?> Kelola</a><?php endif; ?>
        </div>
        <?php $semuaKelas = kelas_list(true); ?>
        <?php if (!$semuaKelas): ?>
          <p class="mut sm">Belum ada kelas. Tambahkan lewat menu Kelas.</p>
        <?php else: ?>
          <div class="grid g2" style="gap:8px">
            <?php foreach ($semuaKelas as $k): ?>
              <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:500;margin:0">
                <input type="checkbox" name="kelas[]" value="<?= (int) $k['id'] ?>" <?= in_array((int) $k['id'], $kelasSekarang, true) ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--accent)">
                <?= e((string) $k['nama']) ?> <span class="mut xs">(<?= (int) $k['jml_siswa'] ?> siswa)</span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-head"><div><h2>Akun Login</h2><p>Untuk masuk aplikasi sebagai guru.</p></div></div>
        <?php if ($akun): ?>
          <p class="sm" style="margin:0">Nama pengguna: <b><?= e((string) $akun['username']) ?></b></p>
          <p class="hint">Kata sandi diatur/diubah melalui menu Pengguna &amp; Akun (Super Admin).</p>
        <?php else: ?>
          <label class="f" style="display:flex;gap:9px;align-items:flex-start;font-weight:600">
            <input type="checkbox" name="buat_akun" value="1" style="width:16px;height:16px;accent-color:var(--accent);margin-top:2px">
            <span>Sekaligus buatkan akun login<br><span class="hint">Kata sandi awal = NIP bila NIP 6 karakter atau lebih, kalau tidak <b>guru123</b>.</span></span>
          </label>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="flex mt24">
    <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Data Guru</button>
    <a class="btn btn-ghost" href="guru.php">Batal</a>
  </div>
</form>
<?php
page_bottom();
