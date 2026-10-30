<?php
/**
 * Pengguna & Akun (khusus Super Admin).
 *
 * Dua mode:
 *  - TANPA konteks sekolah : mengelola akun Super Admin (lintas sekolah).
 *  - DALAM konteks sekolah : mengelola akun kepala/operator/guru milik sekolah tersebut
 *    (termasuk membuat akun untuk guru yang belum punya).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_super();
$aku = (int) $u['id'];
$sekolah = current_sekolah();
$sid = $sekolah ? (int) $sekolah['id'] : 0;
$lintas = $sid <= 0;                    // mode akun Super Admin
$roleSah = $lintas ? ['super'] : ['kepala', 'operator', 'guru'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $target = $id > 0 ? one('SELECT * FROM users WHERE id = ?', [$id]) : null;
    // Akun super hanya boleh dikelola pada mode lintas; akun sekolah hanya pada konteksnya.
    if ($target && ($lintas ? $target['role'] !== 'super' : (int) $target['sekolah_id'] !== $sid)) {
        flash('Akun itu bukan bagian dari ' . ($lintas ? 'akun Super Admin' : 'sekolah/madrasah ini') . '.', 'err');
        redirect('pengguna.php');
    }

    if ($aksi === 'tambah' || $aksi === 'ubah') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $role = in_array((string) ($_POST['role'] ?? ''), $roleSah, true) ? (string) $_POST['role'] : ($lintas ? 'super' : 'guru');
        $guruId = (int) ($_POST['guru_id'] ?? 0);
        $sandi = (string) ($_POST['password'] ?? '');
        $aktif = isset($_POST['aktif']) ? 1 : 0;

        $galat = [];
        if ($nama === '') {
            $galat[] = 'Nama wajib diisi.';
        }
        if ($username === '' || strlen($username) < 3) {
            $galat[] = 'Nama pengguna minimal 3 karakter.';
        }
        if (one('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $id])) {
            $galat[] = 'Nama pengguna "' . $username . '" sudah dipakai (harus unik se-aplikasi).';
        }
        if ($aksi === 'tambah' && !sandi_pemeriksa($sandi, false)['ok']) {
            $galat[] = 'Kata sandi minimal ' . SANDI_MIN . ' karakter.';
        }
        if ($aksi === 'ubah' && $sandi !== '' && !sandi_pemeriksa($sandi, false)['ok']) {
            $galat[] = 'Kata sandi baru minimal ' . SANDI_MIN . ' karakter.';
        }
        if ($role === 'guru' && $guruId <= 0) {
            $galat[] = 'Akun guru harus ditautkan ke salah satu data guru.';
        }
        if ($role === 'guru' && $guruId > 0 && !guru_by_id($guruId)) {
            $galat[] = 'Data guru yang dipilih tidak ada pada sekolah/madrasah ini.';
        }
        if ($galat) {
            foreach ($galat as $x) {
                flash($x, 'err');
            }
            redirect($aksi === 'ubah' ? 'pengguna.php?edit=' . $id : 'pengguna.php');
        }

        $taut = ($role === 'guru' && $guruId > 0) ? $guruId : null;
        $sekolahAkun = $role === 'super' ? null : $sid;
        if ($aksi === 'tambah') {
            q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, guru_id, aktif, dibuat) VALUES (?,?,?,?,?,?,?,?)',
                [$sekolahAkun, $username, password_hash($sandi, PASSWORD_DEFAULT), $nama, $role, $taut, $aktif, date('Y-m-d H:i:s')]);
            // Sandi lemah (mis. guru123) → akun WAJIB mengganti sandi saat masuk pertama.
            if (sandi_lemah_dasar($sandi)) {
                q('UPDATE users SET sandi_wajib_ganti = 1 WHERE username = ?', [$username]);
            }
            flash('Pengguna ' . $nama . ' (' . role_label($role) . ') ditambahkan.');
        } else {
            if ($id === $aku && ($role !== 'super' || $aktif !== 1)) {
                flash('Akun Anda sendiri tidak dapat diturunkan perannya atau dinonaktifkan.', 'err');
                redirect('pengguna.php?edit=' . $id);
            }
            if ($target['role'] === 'super' && $role !== 'super'
                && (int) val('SELECT COUNT(*) FROM users WHERE role = "super" AND aktif = 1', [], 0) <= 1) {
                flash('Minimal harus ada satu akun Super Admin aktif.', 'err');
                redirect('pengguna.php?edit=' . $id);
            }
            q('UPDATE users SET username=?, nama=?, role=?, guru_id=?, sekolah_id=?, aktif=? WHERE id=?',
                [$username, $nama, $role, $taut, $sekolahAkun, $aktif, $id]);
            if ($sandi !== '') {
                // sandi_simpan() sekaligus menandai wajib-ganti bila sandinya lemah.
                sandi_simpan($id, $sandi, sandi_lemah_dasar($sandi));
            }
            flash('Pengguna ' . $nama . ' diperbarui.');
        }
        redirect('pengguna.php');
    }

    if ($aksi === 'massal') {
        if ($lintas) {
            flash('Pilih sekolah/madrasah terlebih dahulu untuk membuat akun guru.', 'err');
            redirect('pengguna.php');
        }
        $belum = all('SELECT * FROM guru g WHERE g.aktif = 1 AND g.sekolah_id = ?
                      AND NOT EXISTS (SELECT 1 FROM users us WHERE us.guru_id = g.id)', [$sid]);
        $n = 0;
        $daftar = [];
        db()->exec('BEGIN IMMEDIATE');
        try {
            foreach ($belum as $g) {
                $username = guru_username_unik($g);
                $sandi = guru_sandi_awal($g);
                q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, guru_id, aktif, dibuat) VALUES (?,?,?,?,?,?,1,?)',
                    [$sid, $username, password_hash($sandi, PASSWORD_DEFAULT), $g['nama'], 'guru', (int) $g['id'], date('Y-m-d H:i:s')]);
                // Sandi awal (guru123) selalu ditandai wajib ganti saat guru pertama kali masuk.
                q('UPDATE users SET sandi_wajib_ganti = 1 WHERE username = ?', [$username]);
                $daftar[] = $username . ' / ' . $sandi;
                $n++;
            }
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        if ($n > 0) {
            $_SESSION['akun_sekali'] = ['jumlah' => $n, 'daftar' => array_slice($daftar, 0, 60)];
            flash($n . ' akun guru dibuat. Daftar nama pengguna & kata sandi awal ditampilkan sekali di bawah.');
        } else {
            flash('Semua guru aktif pada sekolah/madrasah ini sudah memiliki akun.', 'warn');
        }
        redirect('pengguna.php');
    }

    if (!$target) {
        flash('Pengguna tidak ditemukan.', 'err');
        redirect('pengguna.php');
    }

    if ($aksi === 'reset') {
        $sandi = (string) ($_POST['password'] ?? '');
        if (!sandi_pemeriksa($sandi, false)['ok']) {
            flash('Kata sandi baru minimal ' . SANDI_MIN . ' karakter.', 'err');
        } else {
            // Reset oleh Super Admin: sandi lemah → pemilik akun WAJIB menggantinya saat masuk.
            sandi_simpan($id, $sandi, sandi_lemah_dasar($sandi));
            flash('Kata sandi ' . $target['nama'] . ' berhasil diganti.');
        }
    } elseif ($aksi === 'toggle') {
        if ($id === $aku) {
            flash('Akun Anda sendiri tidak dapat dinonaktifkan.', 'err');
        } elseif ($target['role'] === 'super' && (int) $target['aktif'] === 1
            && (int) val('SELECT COUNT(*) FROM users WHERE role = "super" AND aktif = 1', [], 0) <= 1) {
            flash('Minimal harus ada satu akun Super Admin aktif.', 'err');
        } else {
            q('UPDATE users SET aktif = ? WHERE id = ?', [(int) $target['aktif'] === 1 ? 0 : 1, $id]);
            flash('Status akun ' . $target['nama'] . ' diperbarui.', 'warn');
        }
    } elseif ($aksi === 'hapus') {
        if ($id === $aku) {
            flash('Akun Anda sendiri tidak dapat dihapus.', 'err');
        } elseif ($target['role'] === 'super' && (int) val('SELECT COUNT(*) FROM users WHERE role = "super"', [], 0) <= 1) {
            flash('Minimal harus ada satu akun Super Admin.', 'err');
        } else {
            q('DELETE FROM users WHERE id = ?', [$id]);
            flash('Akun ' . $target['nama'] . ' dihapus.', 'warn');
        }
    }
    redirect('pengguna.php');
}

$edit = (int) ($_GET['edit'] ?? 0) > 0 ? one('SELECT * FROM users WHERE id = ?', [(int) $_GET['edit']]) : null;
if ($edit && ($lintas ? $edit['role'] !== 'super' : (int) $edit['sekolah_id'] !== $sid)) {
    $edit = null;
}

// Daftar akun yang DIKELOLA dari halaman ini. Akun SISWA (peran `siswa`, dibuat otomatis saat siswa
// masuk dengan NISN) sengaja TIDAK ditampilkan — lihat gerbang_siswa() di inc/helpers.php.
$users = $lintas
    ? all('SELECT * FROM users WHERE role = "super" ORDER BY nama')
    : all('SELECT us.*, g.nama AS guru_nama,
             (SELECT nama FROM sekolah s WHERE s.id = us.sekolah_id) AS sekolah_nama
           FROM users us LEFT JOIN guru g ON g.id = us.guru_id
           WHERE us.sekolah_id = ? AND us.role <> "siswa"
           ORDER BY CASE us.role WHEN "kepala" THEN 1 WHEN "operator" THEN 2 ELSE 3 END, us.nama', [$sid]);
$semuaSuper = all('SELECT * FROM users WHERE role = "super" ORDER BY nama');
$guruTanpaAkun = $lintas ? 0 : (int) val('SELECT COUNT(*) FROM guru g WHERE g.aktif = 1 AND g.sekolah_id = ?
    AND NOT EXISTS (SELECT 1 FROM users us WHERE us.guru_id = g.id)', [$sid], 0);
$sekali = $_SESSION['akun_sekali'] ?? null;
unset($_SESSION['akun_sekali']);

page_top($lintas ? 'Akun Super Admin' : 'Pengguna & Akun: ' . nama_sekolah_tampil($sekolah),
    $lintas ? 'Lintas sekolah/madrasah &middot; ' . count($users) . ' akun Super Admin'
            : 'Peran kepala, operator, dan guru pada ' . e(istilah_sekolah('sekolah')) . ' ini');
?>
<?php if ($lintas): ?>
  <div class="alert alert-warn">
    <?= ikon('alert', 16) ?>
    <span>Anda sedang mengelola <b>akun Super Admin</b>. Untuk mengelola akun kepala/operator/guru,
      masuk dulu ke sekolah/madrasah yang bersangkutan pada halaman <a href="sekolah.php">Kelola Sekolah</a>.</span>
  </div>
<?php endif; ?>

<?php if (!$lintas): ?>
  <div class="grid g4">
    <?php foreach ([['kepala', 'Kepala'], ['operator', 'Admin Operator'], ['guru', 'Guru']] as $r): ?>
      <div class="card stat card-tight">
        <div class="stat-ic <?= $r[0] === 'guru' ? 'g' : ($r[0] === 'kepala' ? '' : 'p') ?>"><?= ikon($r[0] === 'guru' ? 'user' : 'key', 18) ?></div>
        <div>
          <div class="stat-num" style="font-size:1.3rem"><?= (int) val('SELECT COUNT(*) FROM users WHERE role = ? AND aktif = 1 AND sekolah_id = ?', [$r[0], $sid], 0) ?></div>
          <div class="stat-lbl"><?= e($r[1]) ?> aktif</div>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="card stat card-tight">
      <div class="stat-ic s"><?= ikon('school', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= $guruTanpaAkun ?></div><div class="stat-lbl">Guru tanpa akun</div></div>
    </div>
  </div>
<?php endif; ?>

<?php if ($sekali): ?>
  <div class="card">
    <div class="card-head"><div><h2>Kata Sandi Awal (ditampilkan sekali)</h2><p>Catat sekarang — sandi disimpan dalam bentuk teracak sehingga tidak dapat ditampilkan lagi.</p></div></div>
    <div class="chip-list">
      <?php foreach ($sekali['daftar'] as $d): ?><span class="chip"><?= e((string) $d) ?></span><?php endforeach; ?>
    </div>
    <?php if ((int) $sekali['jumlah'] > count($sekali['daftar'])): ?>
      <p class="hint">Ditampilkan <?= count($sekali['daftar']) ?> dari <?= (int) $sekali['jumlah'] ?> akun. Sisanya dapat diubah satu per satu di bawah.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="grid g12">
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= $edit ? 'Ubah Pengguna' : 'Tambah Pengguna' ?></h2>
        <p><?= $lintas ? 'Akun Super Admin bersifat lintas sekolah/madrasah.' : 'Level akun menentukan menu &amp; hak akses pada sekolah ini.' ?></p>
      </div>
      <?php if ($edit): ?><a class="btn btn-ghost btn-sm" href="pengguna.php">Batal</a><?php endif; ?>
    </div>
    <form method="post" class="form-grid" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="<?= $edit ? 'ubah' : 'tambah' ?>">
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div>
        <label class="f" for="nama">Nama *</label>
        <input type="text" id="nama" name="nama" value="<?= e((string) ($edit['nama'] ?? '')) ?>" required>
      </div>
      <div>
        <label class="f" for="username">Nama Pengguna *</label>
        <input type="text" id="username" name="username" value="<?= e((string) ($edit['username'] ?? '')) ?>" required>
      </div>
      <div>
        <label class="f" for="role">Level / Peran *</label>
        <select id="role" name="role">
          <?php if ($lintas): ?>
            <option value="super" selected>Super Admin — seluruh sekolah/madrasah</option>
          <?php else: ?>
            <option value="kepala" <?= (string) ($edit['role'] ?? '') === 'kepala' ? 'selected' : '' ?>>Kepala Sekolah/Madrasah — melihat semua &amp; pengaturan</option>
            <option value="operator" <?= (string) ($edit['role'] ?? '') === 'operator' ? 'selected' : '' ?>>Admin Operator — data master &amp; administrasi</option>
            <option value="guru" <?= (string) ($edit['role'] ?? '') === 'guru' ? 'selected' : '' ?>>Guru — kelas &amp; mapel yang diampu</option>
          <?php endif; ?>
        </select>
      </div>
      <?php if (!$lintas): ?>
        <div>
          <label class="f" for="guru_id">Tautkan ke Data Guru (wajib untuk peran Guru)</label>
          <select id="guru_id" name="guru_id">
            <option value="">— tidak ditautkan —</option>
            <?php foreach (guru_list(true) as $g): ?>
              <option value="<?= (int) $g['id'] ?>" <?= (int) ($edit['guru_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= e((string) $g['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div>
        <label class="f" for="password">Kata Sandi <?= $edit ? '(kosongkan bila tidak diubah)' : '*' ?></label>
        <input type="password" id="password" name="password" <?= $edit ? '' : 'required' ?> minlength="<?= (int) SANDI_MIN ?>">
      </div>
      <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600;margin:0">
        <input type="checkbox" name="aktif" value="1" <?= (int) ($edit['aktif'] ?? 1) === 1 ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--hijau)">
        Akun aktif
      </label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $edit ? 'Simpan Perubahan' : 'Tambah Pengguna' ?></button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= $lintas ? 'Daftar Akun Super Admin' : 'Daftar Akun Sekolah Ini' ?></h2>
        <p><?= count($users) ?> akun<?= !$lintas && $guruTanpaAkun > 0 ? ' &middot; ' . $guruTanpaAkun . ' guru belum punya akun' : '' ?></p>
      </div>
      <?php if (!$lintas && $guruTanpaAkun > 0): ?>
        <form method="post" data-confirm="Buat akun untuk <?= $guruTanpaAkun ?> guru aktif yang belum punya akun?">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="massal">
          <button class="btn btn-primary" type="submit"><?= ikon('key', 16) ?> Buat Akun Semua Guru (<?= $guruTanpaAkun ?>)</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="filterbar mb16">
      <div style="min-width:240px">
        <label class="f" for="tblSearch">Saring cepat</label>
        <input type="search" id="tblSearch" data-target="tblUsers" placeholder="nama / nama pengguna / peran...">
      </div>
      <span class="chip"><b id="tblCount"><?= count($users) ?></b> akun</span>
    </div>

    <div class="table-wrap">
      <table class="tbl" id="tblUsers">
        <thead><tr><th>Nama</th><th>Nama Pengguna</th><th>Peran</th><?= $lintas ? '' : '<th>Data Guru</th>' ?><th class="ctr">Login terakhir</th><th class="ctr">Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $us): ?>
          <tr>
            <td>
              <span class="mini-avatar">
                <?= avatar_user($us, 34) ?>
                <span>
                  <b><?= e((string) $us['nama']) ?></b>
                  <?php if ((int) $us['id'] === $aku): ?><span class="tbl-sub">akun Anda</span><?php endif; ?>
                </span>
              </span>
            </td>
            <td class="nowrap"><?= e((string) $us['username']) ?></td>
            <td><span class="pill <?= $us['role'] === 'super' ? 'pill-B' : ($us['role'] === 'guru' ? 'pill-ok' : 'pill-gold') ?>"><?= e(role_label((string) $us['role'])) ?></span></td>
            <?php if (!$lintas): ?><td><?= e((string) ($us['guru_nama'] ?? '-')) ?></td><?php endif; ?>
            <td class="ctr xs mut"><?= e($us['login_terakhir'] ? date('d/m/Y H:i', strtotime((string) $us['login_terakhir'])) : '-') ?></td>
            <td class="ctr"><span class="pill <?= (int) $us['aktif'] === 1 ? 'pill-ok' : 'pill-gray' ?>"><?= (int) $us['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
            <td>
              <div class="row-actions">
                <a class="btn btn-ghost btn-sm" href="pengguna.php?edit=<?= (int) $us['id'] ?>" title="Ubah data / ganti kata sandi"><?= ikon('edit', 15) ?></a>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $us['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $us['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                </form>
                <form method="post" data-confirm="Hapus akun <?= e((string) $us['nama']) ?>?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="hapus">
                  <input type="hidden" name="id" value="<?= (int) $us['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 15) ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
page_bottom();
