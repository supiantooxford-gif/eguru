<?php
/**
 * Akun Saya — profil pengguna yang sedang masuk & penggantian kata sandi sendiri.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'profil') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        if ($nama === '') {
            flash('Nama pengguna wajib diisi.', 'err');
        } else {
            q('UPDATE users SET nama = ? WHERE id = ?', [$nama, (int) $u['id']]);
            flash('Nama pengguna diperbarui.');
        }
    } elseif ($aksi === 'sandi') {
        $lama = (string) ($_POST['sandi_lama'] ?? '');
        $baru = (string) ($_POST['sandi_baru'] ?? '');
        $ulang = (string) ($_POST['sandi_ulang'] ?? '');
        if (!password_verify($lama, (string) $u['password_hash'])) {
            flash('Kata sandi lama tidak cocok.', 'err');
        } elseif (strlen($baru) < 6) {
            flash('Kata sandi baru minimal 6 karakter.', 'err');
        } elseif ($baru !== $ulang) {
            flash('Ulangi kata sandi baru dengan tepat sama.', 'err');
        } else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), (int) $u['id']]);
            flash('Kata sandi berhasil diganti.');
        }
    }
    redirect('akun.php');
}

$aku = current_user();
$g = guru_saya();
$hak = [];
if (is_super()) {
    $hak = ['Seluruh data administrasi', 'Data master (guru, siswa, kelas, mata pelajaran, tahun ajaran)', 'Identitas & pengaturan aplikasi', 'Akun pengguna semua level'];
} elseif (is_kepala()) {
    $hak = ['Melihat & mencetak seluruh data administrasi', 'Identitas & pengaturan aplikasi (termasuk ambang predikat & bobot ASAS)', 'Tahun ajaran & semester'];
} elseif (is_operator()) {
    $hak = ['Seluruh data administrasi (daftar hadir, nilai, jurnal, kasus)', 'Data master (guru, siswa, kelas, mata pelajaran)', 'Tahun ajaran & semester', 'Impor data Excel/CSV'];
} else {
    $hak = ['Daftar hadir & nilai pada kelas yang diajar', 'Jurnal harian dan catatan kasus', 'Data siswa pada kelas yang diajar (hanya lihat)'];
}

page_top('Akun Saya');
?>
<div class="grid g21">
  <div class="card">
    <div class="card-head"><div><h2>Profil</h2><p>Data akun yang Anda pakai untuk masuk.</p></div></div>
    <div class="foto-baris">
      <div class="foto-sisi">
        <?= avatar_user($aku, 76, 'avatar avatar-besar') ?>
        <span class="pill pill-B"><?= e(role_label()) ?></span>
      </div>
      <div class="foto-form">
        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="profil">
          <div>
            <label class="f" for="nama">Nama Tampilan</label>
            <input type="text" id="nama" name="nama" value="<?= e((string) $aku['nama']) ?>" required>
          </div>
          <div>
            <label class="f">Nama Pengguna</label>
            <input type="text" value="<?= e((string) $aku['username']) ?>" disabled>
            <div class="hint">Nama pengguna hanya dapat diubah oleh Super Admin.</div>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Profil</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="grid" style="gap:20px;align-content:start">
    <div class="card" id="cardFoto">
      <div class="card-head">
        <div><h2>Foto Profil</h2><p>Tampil pada sidebar dan daftar pengguna aplikasi.</p></div>
      </div>
      <div class="foto-baris">
        <div class="foto-sisi">
          <div class="foto-preview">
            <?php if (foto_user($aku)): ?>
              <img src="<?= e((string) foto_user($aku)) ?>" alt="Foto profil <?= e((string) $aku['nama']) ?>">
            <?php else: ?>
              <span class="mut xs">Belum ada foto</span>
            <?php endif; ?>
          </div>
          <?php if (foto_user($aku)): ?>
            <form method="post" action="upload_foto.php" data-confirm="Hapus foto profil Anda?">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="hapus">
              <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 15) ?> Hapus foto</button>
            </form>
          <?php endif; ?>
        </div>
        <form class="foto-form" id="formFoto" method="post" action="upload_foto.php" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <label class="f" for="foto">Pilih berkas foto</label>
          <input type="file" id="foto" name="foto" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,image/*">
          <div class="foto-input-baris mt8">
            <div class="foto-preview-wrap" id="fotoPreviewWrap" <?= foto_user($aku) ? '' : 'hidden' ?>>
              <div class="foto-preview" id="fotoPreview">
                <?php if (foto_user($aku)): ?><img src="<?= e((string) foto_user($aku)) ?>" alt="Pratinjau foto"><?php endif; ?>
              </div>
              <span class="xs mut" id="fotoUkuran">Foto saat ini</span>
            </div>
            <div>
              <div class="hint" id="fotoHint">Format JPG, PNG, GIF, WEBP, atau SVG — maksimal 15 MB. Gunakan foto persegi agar hasilnya rapi.</div>
              <div class="form-actions">
                <button class="btn btn-primary" id="btnFoto" type="submit"><?= ikon('upload', 16) ?> <?= foto_user($aku) ? 'Ganti foto' : 'Unggah foto' ?></button>
                <span class="xs mut" id="fotoStatus"></span>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><div><h2>Ganti Kata Sandi</h2><p>Minimal 6 karakter.</p></div></div>
      <form method="post" class="form-grid" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="sandi">
        <div>
          <label class="f" for="sandi_lama">Kata sandi lama</label>
          <input type="password" id="sandi_lama" name="sandi_lama" required>
        </div>
        <div>
          <label class="f" for="sandi_baru">Kata sandi baru</label>
          <input type="password" id="sandi_baru" name="sandi_baru" required minlength="6">
        </div>
        <div>
          <label class="f" for="sandi_ulang">Ulangi kata sandi baru</label>
          <input type="password" id="sandi_ulang" name="sandi_ulang" required minlength="6">
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= ikon('lock', 16) ?> Ganti Kata Sandi</button>
        </div>
      </form>
    </div>

    <div class="card">
      <div class="card-head"><div><h2>Hak Akses Peran Anda</h2><p><?= e(role_label()) ?></p></div></div>
      <ul class="list-ok">
        <?php foreach ($hak as $h): ?><li><?= e($h) ?></li><?php endforeach; ?>
      </ul>
      <?php if ($g): ?>
        <div class="divider"></div>
        <p class="sm" style="margin:0">
          Tertaut ke data guru: <b><?= e((string) $g['nama']) ?></b><br>
          <span class="mut">Kelas diajar: <?= e(implode(', ', array_map(static fn($k) => (string) $k['nama'], kelas_diampu())) ?: '-') ?><br>
          Mata pelajaran: <?= e(implode(', ', array_map(static fn($m) => (string) $m['nama'], mapel_diampu())) ?: '-') ?></span>
        </p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
page_bottom();
