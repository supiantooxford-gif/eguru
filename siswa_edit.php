<?php
/**
 * Tambah / ubah data siswa.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_master();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$s = $id > 0 ? siswa_by_id($id) : null;
if ($id > 0 && !$s) {
    flash('Data siswa tidak ditemukan.', 'err');
    redirect('siswa.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $nis = trim((string) ($_POST['nis'] ?? ''));
    $nisn = trim((string) ($_POST['nisn'] ?? ''));
    $jk = in_array((string) ($_POST['jenis_kelamin'] ?? ''), ['L', 'P'], true) ? (string) $_POST['jenis_kelamin'] : '';
    $kelasId = (int) ($_POST['kelas_id'] ?? 0);
    $lahir = tgl_ok($_POST['tanggal_lahir'] ?? null);
    $wali = trim((string) ($_POST['nama_wali'] ?? ''));
    $telepon = trim((string) ($_POST['telepon'] ?? ''));
    $alamat = trim((string) ($_POST['alamat'] ?? ''));
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    $galat = [];
    if ($nama === '') {
        $galat[] = 'Nama siswa wajib diisi.';
    }
    if ($kelasId > 0 && !kelas_by_id($kelasId)) {
        $galat[] = 'Kelas yang dipilih tidak ditemukan.';
    }
    if ($nis !== '' && one('SELECT id FROM siswa WHERE nis = ? AND id <> ? AND sekolah_id = ?', [$nis, $id, sekolah_id()])) {
        $galat[] = 'NIS ' . $nis . ' sudah dipakai siswa lain.';
    }
    if ($nisn !== '' && one('SELECT id FROM siswa WHERE nisn = ? AND id <> ? AND sekolah_id = ?', [$nisn, $id, sekolah_id()])) {
        $galat[] = 'NISN ' . $nisn . ' sudah dipakai siswa lain.';
    }
    if ($galat) {
        foreach ($galat as $x) {
            flash($x, 'err');
        }
        redirect($id > 0 ? 'siswa_edit.php?id=' . $id : 'siswa_edit.php');
    }

    $kelas = $kelasId > 0 ? $kelasId : null;
    if ($s) {
        q('UPDATE siswa SET nis=?, nisn=?, nama=?, jenis_kelamin=?, kelas_id=?, tanggal_lahir=?, nama_wali=?, telepon=?, alamat=?, aktif=? WHERE id=? AND sekolah_id=?',
            [$nis, $nisn, $nama, $jk, $kelas, $lahir, $wali, $telepon, $alamat, $aktif, $id, sekolah_id()]);
        flash('Data siswa ' . $nama . ' disimpan.');
    } else {
        q('INSERT INTO siswa (sekolah_id, nis, nisn, nama, jenis_kelamin, kelas_id, tanggal_lahir, nama_wali, telepon, alamat, aktif, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [sekolah_id(), $nis, $nisn, $nama, $jk, $kelas, $lahir, $wali, $telepon, $alamat, $aktif, date('Y-m-d H:i:s')]);
        $id = (int) db()->lastInsertId();
        flash('Siswa ' . $nama . ' ditambahkan.');
    }
    redirect('siswa_edit.php?id=' . $id);
}

$kelasAwal = (int) ($s['kelas_id'] ?? ($_GET['kelas'] ?? 0));

page_top($s ? 'Ubah Data Siswa' : 'Tambah Data Siswa');
?>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) ($s['id'] ?? 0) ?>">
  <div class="grid g21">
    <div class="card">
      <div class="card-head">
        <div><h2>Identitas Siswa</h2><p>NIS/NISN boleh dikosongkan bila belum ada.</p></div>
      </div>
      <div class="form-grid">
        <div>
          <label class="f" for="nama">Nama Lengkap *</label>
          <input type="text" id="nama" name="nama" value="<?= e((string) ($s['nama'] ?? '')) ?>" required>
        </div>
        <div class="grid g2" style="gap:16px">
          <div>
            <label class="f" for="nis">NIS</label>
            <input type="text" id="nis" name="nis" value="<?= e((string) ($s['nis'] ?? '')) ?>">
          </div>
          <div>
            <label class="f" for="nisn">NISN</label>
            <input type="text" id="nisn" name="nisn" value="<?= e((string) ($s['nisn'] ?? '')) ?>">
          </div>
        </div>
        <div class="grid g2" style="gap:16px">
          <div>
            <label class="f" for="jenis_kelamin">Jenis Kelamin</label>
            <select id="jenis_kelamin" name="jenis_kelamin">
              <option value="">—</option>
              <option value="L" <?= ($s['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
              <option value="P" <?= ($s['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
            </select>
          </div>
          <div>
            <label class="f" for="tanggal_lahir">Tanggal Lahir</label>
            <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?= e((string) ($s['tanggal_lahir'] ?? '')) ?>">
          </div>
        </div>
        <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600;margin:0">
          <input type="checkbox" name="aktif" value="1" <?= (int) ($s['aktif'] ?? 1) === 1 ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--accent)">
          Siswa aktif (tampil pada daftar hadir &amp; nilai)
        </label>
      </div>
    </div>

    <div class="grid" style="gap:20px;align-content:start">
      <div class="card">
        <div class="card-head"><div><h2>Kelas</h2><p>Siswa dapat dikosongkan kelasnya sementara.</p></div></div>
        <label class="f" for="kelas_id">Kelas</label>
        <select id="kelas_id" name="kelas_id">
          <option value="">— belum berkelas —</option>
          <?php foreach (kelas_list(true) as $k): ?>
            <option value="<?= (int) $k['id'] ?>" <?= $kelasAwal === (int) $k['id'] ? 'selected' : '' ?>><?= e((string) $k['nama']) ?> (<?= (int) $k['jml_siswa'] ?> siswa)</option>
          <?php endforeach; ?>
        </select>
        <?php if (!kelas_list(true)): ?>
          <p class="hint">Belum ada kelas. <a href="kelas.php">Tambahkan kelas</a> terlebih dahulu.</p>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-head"><div><h2>Data Wali</h2><p>Untuk keperluan komunikasi.</p></div></div>
        <div class="form-grid">
          <div>
            <label class="f" for="nama_wali">Nama Orang Tua / Wali</label>
            <input type="text" id="nama_wali" name="nama_wali" value="<?= e((string) ($s['nama_wali'] ?? '')) ?>">
          </div>
          <div>
            <label class="f" for="telepon">No. HP</label>
            <input type="text" id="telepon" name="telepon" value="<?= e((string) ($s['telepon'] ?? '')) ?>">
          </div>
          <div>
            <label class="f" for="alamat">Alamat</label>
            <textarea id="alamat" name="alamat" rows="2"><?= e((string) ($s['alamat'] ?? '')) ?></textarea>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="flex mt24">
    <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Data Siswa</button>
    <a class="btn btn-ghost" href="siswa.php">Batal</a>
  </div>
</form>
<?php
page_bottom();
