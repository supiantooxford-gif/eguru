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
    $tempat = trim((string) ($_POST['tempat_lahir'] ?? ''));
    $lahir = tgl_impor($_POST['tanggal_lahir'] ?? null);
    $ayah = trim((string) ($_POST['nama_ayah'] ?? ''));
    $ibu = trim((string) ($_POST['nama_ibu'] ?? ''));
    $wali = trim((string) ($_POST['nama_wali'] ?? ''));
    $telepon = trim((string) ($_POST['telepon'] ?? ''));
    $alamat = trim((string) ($_POST['alamat'] ?? ''));
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    $hapusFoto = !empty($_POST['hapus_foto']);

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

    // PAS FOTO 3x4 (permintaan pemilik): diunggah ke media proxy SEBELUM data disimpan,
    // sehingga foto yang tidak sah menghentikan penyimpanan (tidak ada siswa setengah jadi).
    $pasfoto = unggah_pas_foto('foto_siswa');
    if (!empty($pasfoto['ada']) && empty($pasfoto['ok'])) {
        flash('Pas foto tidak disimpan: ' . (string) $pasfoto['pesan'], 'err');
        redirect($id > 0 ? 'siswa_edit.php?id=' . $id : 'siswa_edit.php');
    }
    $fotoBaru = $pasfoto['url'] ?? null;

    $kelas = $kelasId > 0 ? $kelasId : null;
    if ($s) {
        q('UPDATE siswa SET nis=?, nisn=?, nama=?, jenis_kelamin=?, kelas_id=?, tempat_lahir=?,
             tanggal_lahir=?, nama_ayah=?, nama_ibu=?, nama_wali=?, telepon=?, alamat=?, aktif=?
           WHERE id=? AND sekolah_id=?',
            [$nis, $nisn, $nama, $jk, $kelas, $tempat, $lahir, $ayah, $ibu, $wali, $telepon, $alamat, $aktif, $id, sekolah_id()]);
        if ($fotoBaru !== null) {
            q('UPDATE siswa SET foto = ? WHERE id = ? AND sekolah_id = ?', [$fotoBaru, $id, sekolah_id()]);
            flash('Pas foto diperbarui dan akan dipakai pada Kartu Pelajar.');
        } elseif ($hapusFoto) {
            q('UPDATE siswa SET foto = NULL WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
            flash('Pas foto dihapus.', 'warn');
        }
        flash('Data siswa ' . $nama . ' disimpan.');
    } else {
        q('INSERT INTO siswa (sekolah_id, nis, nisn, nama, jenis_kelamin, kelas_id, tempat_lahir,
             tanggal_lahir, nama_ayah, nama_ibu, nama_wali, telepon, alamat, aktif, foto, dibuat)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [sekolah_id(), $nis, $nisn, $nama, $jk, $kelas, $tempat, $lahir, $ayah, $ibu, $wali,
             $telepon, $alamat, $aktif, $fotoBaru, date('Y-m-d H:i:s')]);
        $id = (int) db()->lastInsertId();
        flash('Siswa ' . $nama . ' ditambahkan.'
            . ($fotoBaru !== null ? ' Pas foto tersimpan dan siap dipakai pada Kartu Pelajar.' : ''));
    }
    // Peringatan bentuk foto (mis. tidak tegak / bukan 3:4 / resolusi kecil) — data tetap tersimpan.
    foreach (($pasfoto['peringatan'] ?? []) as $p) {
        flash('Pas foto: ' . $p, 'warn');
    }
    redirect('siswa_edit.php?id=' . $id);
}

$kelasAwal = (int) ($s['kelas_id'] ?? ($_GET['kelas'] ?? 0));
$fotoLama = siswa_foto($s);

page_top($s ? 'Ubah Data Siswa' : 'Tambah Data Siswa');
?>
<form method="post" enctype="multipart/form-data" id="formSiswa">
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
            <label class="f" for="tempat_lahir">Tempat Lahir</label>
            <input type="text" id="tempat_lahir" name="tempat_lahir" value="<?= e((string) ($s['tempat_lahir'] ?? '')) ?>"
                   placeholder="mis. Mootilango">
          </div>
          <div>
            <label class="f" for="tanggal_lahir">Tanggal Lahir</label>
            <input type="date" id="tanggal_lahir" name="tanggal_lahir" value="<?= e((string) ($s['tanggal_lahir'] ?? '')) ?>">
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
            <div class="hint">Tempat &amp; tanggal lahir boleh dikosongkan bila belum diketahui.</div>
          </div>
        </div>
        <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600;margin:0">
          <input type="checkbox" name="aktif" value="1" <?= (int) ($s['aktif'] ?? 1) === 1 ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--accent)">
          Siswa aktif (tampil pada daftar hadir &amp; nilai)
        </label>
      </div>
    </div>

    <div class="grid" style="gap:20px;align-content:start">
      <?php // PAS FOTO 3x4 (permintaan pemilik) — diunggah bersama data siswa dan dipakai pada Kartu Pelajar. ?>
      <div class="card" id="cardPasFoto">
        <div class="card-head">
          <div>
            <h2>Pas Foto 3 x 4</h2>
            <p>Foto ini dipakai pada <b>Kartu Pelajar</b>. Boleh dikosongkan dan diunggah menyusul.</p>
          </div>
        </div>
        <div class="pasfoto-baris">
          <div class="pasfoto-sisi">
            <div class="pasfoto-kotak" id="pasfotoKotak">
              <?php if ($fotoLama !== null): ?>
                <img src="<?= e($fotoLama) ?>" alt="Pas foto <?= e((string) ($s['nama'] ?? '')) ?>" id="pasfotoAda">
              <?php else: ?>
                <span class="mut xs" id="pasfotoKosong">Belum ada foto<br>(3 x 4)</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="pasfoto-form">
            <label class="f" for="fotoSiswa">Pilih berkas pas foto</label>
            <?php // id/name SENGAJA bukan "foto": id itu dipakai input foto PROFIL (akun.php) yang
                  // ditangani initFoto() di assets/app.js — memakai id yang sama membuat skrip itu
                  // ikut menangani berkas ini dan gagal (galat konsol). ?>
            <input type="file" id="fotoSiswa" name="foto_siswa" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,image/*" style="padding:9px">
            <div class="hint" id="pasfotoHint">
              Format JPG, PNG, GIF, WEBP, atau SVG. Maksimal 15 MB. Paling rapi bila foto <b>tegak dengan
              rasio 3 : 4</b> (mis. 240 x 320 px atau lebih besar) &mdash; foto lain tetap diterima dan
              dipotong otomatis pada kartu.
            </div>
            <?php if ($fotoLama !== null): ?>
              <label class="f mt12" style="display:flex;gap:9px;align-items:center;font-weight:600">
                <input type="checkbox" name="hapus_foto" value="1" id="hapusFoto" style="width:16px;height:16px;accent-color:var(--accent)">
                Hapus pas foto
              </label>
            <?php endif; ?>
            <div class="mt8 xs mut" id="pasfotoStatus">
              <?= $fotoLama !== null
                ? 'Pas foto sudah tersimpan. Pilih berkas baru untuk menggantinya.'
                : 'Belum ada pas foto untuk siswa ini.' ?>
            </div>
          </div>
        </div>
      </div>

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
        <div class="card-head"><div><h2>Data Orang Tua / Wali</h2><p>Terisi otomatis dari hasil impor Excel/CSV; boleh disunting.</p></div></div>
        <div class="form-grid">
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="nama_ayah">Nama Ayah</label>
              <input type="text" id="nama_ayah" name="nama_ayah" value="<?= e((string) ($s['nama_ayah'] ?? '')) ?>">
            </div>
            <div>
              <label class="f" for="nama_ibu">Nama Ibu</label>
              <input type="text" id="nama_ibu" name="nama_ibu" value="<?= e((string) ($s['nama_ibu'] ?? '')) ?>">
            </div>
          </div>
          <div>
            <label class="f" for="nama_wali">Nama Wali</label>
            <input type="text" id="nama_wali" name="nama_wali" value="<?= e((string) ($s['nama_wali'] ?? '')) ?>">
            <div class="hint">Boleh dikosongkan bila wali sama dengan ayah/ibu.</div>
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
    <button class="btn btn-primary" type="submit" id="btnSiswa"><?= ikon('save', 16) ?> Simpan Data Siswa</button>
    <a class="btn btn-ghost" href="siswa.php">Batal</a>
    <?php if ($fotoLama !== null): ?>
      <a class="btn btn-ghost" href="cetak_kartu_siswa.php?id=<?= (int) ($s['id'] ?? 0) ?>"><?= ikon('card', 16) ?> Lihat Kartu Pelajar</a>
    <?php endif; ?>
  </div>
</form>
<?php
page_bottom();
