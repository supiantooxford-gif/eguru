<?php
/**
 * AKUN OPERATOR — khusus KEPALA SEKOLAH/MADRASAH.
 *
 * Permintaan pemilik: "Pada halaman kepala sekolah tambahkan fitur agar bisa menambahkan akun
 * operatornya sendiri." Pilihan pemilik (jangan diubah tanpa bertanya):
 *   (1) kepala boleh SEMUANYA atas akun operator: tambah, ubah, nonaktifkan, tetapkan sandi baru,
 *       sampai menghapus;
 *   (2) HANYA akun operator — halaman ini tidak menyentuh akun kepala/guru/Super Admin;
 *   (3) tempatnya sebagai menu tersendiri "Akun Operator".
 *
 * PENJAGA PENTING (anti penyalahgunaan wewenang):
 *  - Halaman & seluruh aksinya hanya untuk peran kepala (`require_kepala()`); Admin Operator, guru,
 *    dan Super Admin ditolak 403 (Super Admin memakai halaman `pengguna.php` untuk hal yang sama).
 *  - Setiap akun yang disentuh WAJIB berperan `operator` DAN milik sekolah kepala tersebut
 *    (`sekolah_id = sekolah_id()`). Jadi id sekolah/akun lain → 403, dan akun kepala/guru tidak
 *    dapat diubah dari sini walau id-nya ditebak.
 *  - Peran akun TIDAK PERNAH diubah dari halaman ini (selalu `operator`) sehingga tidak ada jalan
 *    menaikkan/menurunkan peran lewat halaman kepala.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_kepala();
$aku = (int) $u['id'];
$sid = sekolah_id();

/** Ambil akun OPERATOR milik sekolah ini; null bila id kosong/bukan operator sekolah ini (anti-IDOR). */
function op_target(int $id, int $sid): ?array
{
    if ($id <= 0) {
        return null;
    }
    return one('SELECT * FROM users WHERE id = ? AND sekolah_id = ? AND role = "operator"', [$id, $sid]);
}

/** Kembalikan isian formulir dari $_POST (supaya tidak perlu mengetik ulang saat galat). */
function op_isian(): array
{
    return [
        'id'       => (int) ($_POST['id'] ?? 0),
        'nama'     => trim((string) ($_POST['nama'] ?? '')),
        'username' => trim((string) ($_POST['username'] ?? '')),
        'aktif'    => isset($_POST['aktif']) ? 1 : 0,
    ];
}

/** Sandi awal acak yang mudah dibacakan (tanpa huruf/angka mirip: 0/O, 1/I/L). */
function operator_sandi_acak(int $panjang = 8): string
{
    $abjad = '23456789ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
    $sandi = '';
    $maks = strlen($abjad) - 1;
    for ($i = 0; $i < $panjang; $i++) {
        $sandi .= $abjad[random_int(0, $maks)];
    }
    return $sandi;
}

$isian = null;          // isian yang perlu ditampilkan kembali bila validasi gagal
$tampilkanForm = false; // true = formulir dirender ulang (validasi gagal), aksi lain dilewati
$aksiSelesai = false;   // true = permintaan sudah ditangani (redirect)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $target = op_target($id, $sid);

    // Aksi yang menyentuh akun tertentu: pastikan akunnya operator milik sekolah ini.
    if ($id > 0 && !$target) {
        tolak403('Akun operator itu bukan milik sekolah/madrasah Anda.');
    }

    if ($aksi === 'tambah' || $aksi === 'ubah') {
        $in = op_isian();
        $sandi = (string) ($_POST['password'] ?? '');

        $galat = [];
        if ($in['nama'] === '') {
            $galat[] = 'Nama wajib diisi.';
        }
        if ($in['username'] === '' || strlen($in['username']) < 3) {
            $galat[] = 'Nama pengguna minimal 3 karakter.';
        }
        if (one('SELECT id FROM users WHERE username = ? AND id <> ?', [$in['username'], $in['id']])) {
            $galat[] = 'Nama pengguna "' . $in['username'] . '" sudah dipakai (harus unik se-aplikasi).';
        }
        if ($aksi === 'tambah' && strlen($sandi) < 6) {
            $galat[] = 'Kata sandi minimal 6 karakter.';
        }
        if ($aksi === 'ubah' && $sandi !== '' && strlen($sandi) < 6) {
            $galat[] = 'Kata sandi baru minimal 6 karakter.';
        }
        if ($galat) {
            foreach ($galat as $g) {
                flash($g, 'err');
            }
            // Kembalikan isian ke formulir (tidak perlu mengetik ulang). PENTING: jangan
            // meneruskan ke blok aksi lain — dulu alurnya jatuh ke pemeriksaan target sehingga
            // isian pengguna hilang (tertangkap uji). Lihat pola $tampilkanForm.
            $isian = $in;
            $tampilkanForm = true;
        } else {
            if ($aksi === 'tambah') {
                // Peran SELALU operator; sekolah selalu sekolah kepala yang sedang masuk.
                q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, guru_id, aktif, dibuat)
                   VALUES (?,?,?,?,"operator",NULL,?,?)',
                    [$sid, $in['username'], password_hash($sandi, PASSWORD_DEFAULT), $in['nama'], $in['aktif'], date('Y-m-d H:i:s')]);
                // Sandi ditampilkan SEKALI pada halaman berikutnya (disimpan teracak di basis data).
                $_SESSION['operator_sandi_sekali'] = ['username' => $in['username'], 'sandi' => $sandi];
                flash('Akun operator ' . $in['nama'] . ' dibuat. Berikan nama pengguna & kata sandi kepada yang bersangkutan.');
            } else {
                q('UPDATE users SET username = ?, nama = ?, aktif = ? WHERE id = ? AND sekolah_id = ? AND role = "operator"',
                    [$in['username'], $in['nama'], $in['aktif'], (int) $target['id'], $sid]);
                if ($sandi !== '') {
                    q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($sandi, PASSWORD_DEFAULT), (int) $target['id']]);
                    flash('Akun operator ' . $in['nama'] . ' diperbarui beserta kata sandinya.');
                } else {
                    flash('Akun operator ' . $in['nama'] . ' diperbarui.');
                }
            }
            $aksiSelesai = true;
            redirect('akun_operator.php');
        }
    }

    if (!$tampilkanForm && !$aksiSelesai && !$target) {
        flash('Akun operator tidak ditemukan.', 'err');
        redirect('akun_operator.php');
    }

    if ($tampilkanForm || $aksiSelesai) {
        // Validasi gagal (atau sudah ditangani) → jangan jalankan aksi lain; formulir dirender ulang.
    } elseif ($aksi === 'reset') {
        // "Sandi awal" = sandi acak yang ditampilkan SEKALI (pola sama dengan buat akun guru).
        $sandi = operator_sandi_acak();
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($sandi, PASSWORD_DEFAULT), (int) $target['id']]);
        $_SESSION['operator_sandi_sekali'] = ['username' => (string) $target['username'], 'sandi' => $sandi];
        flash('Sandi akun operator ' . $target['nama'] . ' ditetapkan ulang. Sandi barunya ditampilkan sekali di bawah.');
    } elseif ($aksi === 'toggle') {
        q('UPDATE users SET aktif = ? WHERE id = ? AND sekolah_id = ? AND role = "operator"',
            [(int) $target['aktif'] === 1 ? 0 : 1, (int) $target['id'], $sid]);
        flash('Status akun operator ' . $target['nama'] . ((int) $target['aktif'] === 1 ? ' dinonaktifkan.' : ' diaktifkan kembali.'), 'warn');
    } elseif ($aksi === 'hapus') {
        // Peringatan bila operator ini satu-satunya: setelah dihapus tidak ada yang membantu administrasi.
        $sisa = akun_jumlah_peran('operator') - 1;
        q('DELETE FROM users WHERE id = ? AND sekolah_id = ? AND role = "operator"', [(int) $target['id'], $sid]);
        flash('Akun operator ' . $target['nama'] . ' dihapus.'
            . ($sisa <= 0 ? ' Sekolah/madrasah ini sekarang tidak punya akun operator — tambahkan lagi bila diperlukan.' : ''), 'warn');
    }

    // Hanya mengalihkan bila permintaan selesai. Saat validasi gagal ($tampilkanForm) halaman
    // dirender ulang supaya isian pengguna tidak hilang — jangan mengalihkan di kondisi itu.
    if (!$tampilkanForm) {
        redirect('akun_operator.php');
    }
}

$edit = op_target((int) ($_GET['edit'] ?? 0), $sid);
$rows = all('SELECT * FROM users WHERE sekolah_id = ? AND role = "operator" ORDER BY aktif DESC, nama', [$sid]);
$jmlAktif = akun_jumlah_peran('operator');
$sekali = $_SESSION['operator_sandi_sekali'] ?? null;
unset($_SESSION['operator_sandi_sekali']);

// Nilai awal formulir: dari isian yang gagal divalidasi, atau dari akun yang sedang diubah.
$f = [
    'id'       => (int) ($isian['id'] ?? ($edit['id'] ?? 0)),
    'nama'     => (string) ($isian['nama'] ?? ($edit['nama'] ?? '')),
    'username' => (string) ($isian['username'] ?? ($edit['username'] ?? '')),
    'aktif'    => (int) ($isian['aktif'] ?? ($edit['aktif'] ?? 1)),
];
$sedangUbah = $f['id'] > 0;

page_top('Akun Operator', 'Kelola akun Admin Operator ' . istilah_sekolah('sekolah') . ' ini');
?>
<div class="grid g4">
  <div class="card stat card-tight">
    <div class="stat-ic p"><?= ikon('key', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $jmlAktif ?></div><div class="stat-lbl">Operator aktif</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('users', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= count($rows) ?></div><div class="stat-lbl">Total akun operator</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic s"><?= ikon('school', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= akun_jumlah_peran('kepala') ?></div><div class="stat-lbl">Akun kepala</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic o"><?= ikon('student', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= akun_jumlah_peran('guru') ?></div><div class="stat-lbl">Akun guru</div></div>
  </div>
</div>

<?php if ($sekali): ?>
  <div class="alert alert-ok">
    <?= ikon('check', 16) ?>
    <span>Kata sandi awal akun operator <b><?= e((string) $sekali['username']) ?></b>:
      <b><?= e((string) $sekali['sandi']) ?></b> — catat sekarang, sandi disimpan teracak sehingga
      tidak dapat ditampilkan lagi. Minta yang bersangkutan segera menggantinya pada menu Akun Saya.</span>
  </div>
<?php endif; ?>

<div class="grid g12">
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= $sedangUbah ? 'Ubah Akun Operator' : 'Tambah Akun Operator' ?></h2>
        <p>Akun ini berperan <b>Admin Operator</b> — data master &amp; seluruh administrasi
          <?= e(istilah_sekolah('sekolah')) ?>. Hanya kepala <?= e(istilah_sekolah('sekolah')) ?>
          yang dapat mengelola akun ini.</p>
      </div>
      <?php if ($sedangUbah): ?><a class="btn btn-ghost btn-sm" href="akun_operator.php">Batal</a><?php endif; ?>
    </div>
    <form method="post" class="form-grid" autocomplete="off" id="formOperator">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="<?= $sedangUbah ? 'ubah' : 'tambah' ?>">
      <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
      <div>
        <label class="f" for="nama">Nama Operator *</label>
        <input type="text" id="nama" name="nama" required value="<?= e($f['nama']) ?>" placeholder="mis. Operator Madrasah">
      </div>
      <div>
        <label class="f" for="username">Nama Pengguna * (min. 3, unik se-aplikasi)</label>
        <input type="text" id="username" name="username" required value="<?= e($f['username']) ?>" placeholder="mis. operator2">
      </div>
      <div>
        <label class="f" for="password"><?= $sedangUbah ? 'Kata Sandi Baru (kosongkan bila tidak diubah)' : 'Kata Sandi * (min. 6)' ?></label>
        <input type="password" id="password" name="password" <?= $sedangUbah ? '' : 'required' ?> minlength="6">
        <div class="hint">Sandi disimpan teracak (tidak dapat dilihat kembali). Sampaikan langsung kepada operatornya.</div>
      </div>
      <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600">
        <input type="checkbox" name="aktif" value="1" style="width:16px;height:16px;accent-color:var(--accent)"
               <?= $f['aktif'] === 1 ? 'checked' : '' ?>> Akun aktif (boleh masuk aplikasi)
      </label>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= ikon($sedangUbah ? 'save' : 'plus', 16) ?> <?= $sedangUbah ? 'Simpan Perubahan' : 'Tambah Akun Operator' ?></button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-head">
      <div><h2>Aturan Halaman Ini</h2><p>Ringkas supaya tidak salah paham wewenangnya.</p></div>
    </div>
    <ul class="portal-cek">
      <li>Kepala <?= e(istilah_sekolah('sekolah')) ?> dapat <b>menambah, mengubah, menonaktifkan,
        mengganti sandi, dan menghapus</b> akun Admin Operator.</li>
      <li>Halaman ini <b>hanya</b> mengelola akun <b>Admin Operator</b> — akun kepala, guru, dan
        Super Admin tidak dapat diubah dari sini.</li>
      <li>Akun operator hanya dapat melihat data <?= e(istilah_sekolah('sekolah')) ?> ini; data
        <?= e(istilah_sekolah('sekolah')) ?> lain tetap terpisah.</li>
      <li>Sebaiknya minimal ada <b>satu</b> akun operator aktif agar pengisian administrasi tetap berjalan
        saat kepala tidak sempat mengerjakannya.</li>
    </ul>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Daftar Akun Operator</h2><p><?= count($rows) ?> akun terdaftar &middot; <?= $jmlAktif ?> aktif</p></div>
  </div>

  <?php if (!$rows): ?>
    <div class="empty">
      <?= ikon('key', 34) ?>
      <h3>Belum ada akun operator</h3>
      <p>Tambahkan akun Admin Operator pada formulir di atas agar pengisian administrasi
        <?= e(istilah_sekolah('sekolah')) ?> dapat dibantu.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Nama</th>
            <th>Nama Pengguna</th>
            <th class="ctr">Status</th>
            <th>Masuk Terakhir</th>
            <th class="ctr">Tindakan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><b><?= e((string) $r['nama']) ?></b><?= (int) $r['id'] === $f['id'] ? ' <span class="pill pill-B">sedang diubah</span>' : '' ?></td>
              <td><?= e((string) $r['username']) ?></td>
              <td class="ctr">
                <span class="pill <?= (int) $r['aktif'] === 1 ? 'pill-ok' : 'pill-gray' ?>"><?= (int) $r['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
              </td>
              <td><?= e(!empty($r['login_terakhir']) ? tanggal_id((string) $r['login_terakhir'], true) : 'belum pernah') ?></td>
              <td class="ctr">
                <div class="row-actions" style="justify-content:center">
                  <a class="btn btn-ghost btn-sm" href="akun_operator.php?edit=<?= (int) $r['id'] ?>#formOperator"><?= ikon('edit', 14) ?> Ubah</a>
                  <form method="post" data-confirm="Tetapkan sandi awal yang baru untuk akun operator <?= e((string) $r['nama']) ?>?&#10;Sandi barunya ditampilkan sekali setelah ini.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="reset">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('key', 14) ?> Sandi awal</button>
                  </form>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="toggle">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('lock', 14) ?> <?= (int) $r['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                  </form>
                  <form method="post" data-confirm="Hapus akun operator <?= e((string) $r['nama']) ?>? Data administrasi yang pernah diisinya tetap tersimpan.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Hapus</button>
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
<?php
page_bottom();
