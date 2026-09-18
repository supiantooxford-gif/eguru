<?php
/**
 * Kelola Sekolah/Madrasah (khusus Super Admin): tambah sekolah, ubah profil & ambang predikat,
 * atur akun kepala/operator, masuk/keluar konteks sekolah, nonaktifkan, dan hapus permanen.
 *
 * Pemilik memilih: sekolah/madrasah ditambahkan oleh Super Admin ATAU lewat pendaftaran mandiri
 * yang disetujui Super Admin (pendaftaran.php / daftar.php).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_super();
// Halaman lintas sekolah/madrasah → istilah generik "Sekolah".
$GLOBALS['__vs_istilah_nonaktif'] = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $s = $id > 0 ? sekolah_by_id($id) : null;

    // ---------------- keluar dari konteks sekolah
    if ($aksi === 'keluar') {
        set_sekolah_context(null);
        flash('Anda kembali ke tampilan lintas sekolah/madrasah.', 'warn');
        redirect('sekolah.php');
    }

    // ---------------- masuk (mengelola) sebuah sekolah
    if ($aksi === 'masuk') {
        if (!$s) {
            flash('Sekolah/madrasah tidak ditemukan.', 'err');
        } elseif ((int) $s['aktif'] !== 1) {
            flash(nama_sekolah_tampil($s) . ' sedang dinonaktifkan. Aktifkan kembali sebelum dikelola.', 'err');
        } elseif (($s['verifikasi'] ?? 'disetujui') !== 'disetujui') {
            flash(nama_sekolah_tampil($s) . ' belum disetujui sehingga belum dapat dipakai.', 'err');
        } else {
            set_sekolah_context($id);
            flash('Anda kini mengelola ' . nama_sekolah_tampil($s) . '. Seluruh data yang tampil hanya milik sekolah/madrasah ini.');
            redirect('index.php?sekolah=1');
        }
        redirect('sekolah.php');
    }

    // ---------------- tambah sekolah baru
    if ($aksi === 'tambah') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $jenis = ((string) ($_POST['jenis'] ?? 'umum')) === 'madrasah' ? 'madrasah' : 'umum';
        $npsn = trim((string) ($_POST['npsn'] ?? ''));
        $alamat = trim((string) ($_POST['alamat'] ?? ''));
        $telepon = trim((string) ($_POST['telepon'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $kepala = trim((string) ($_POST['kepala_sekolah'] ?? ''));
        $nip = nip_bersih($_POST['nip_kepala_sekolah'] ?? '');
        $admNama = trim((string) ($_POST['admin_nama'] ?? ''));
        $admUser = trim((string) ($_POST['admin_username'] ?? ''));
        $admPass = (string) ($_POST['admin_password'] ?? '');
        $admRole = ((string) ($_POST['admin_role'] ?? 'kepala')) === 'operator' ? 'operator' : 'kepala';

        $galat = [];
        if ($nama === '') {
            $galat[] = 'Nama sekolah/madrasah wajib diisi.';
        }
        if ($admNama === '' || $admUser === '' || strlen($admPass) < 6) {
            $galat[] = 'Nama, nama pengguna, dan kata sandi (min. 6 karakter) akun pengelola wajib diisi.';
        }
        if ($admUser !== '' && one('SELECT id FROM users WHERE username = ?', [$admUser])) {
            $galat[] = 'Nama pengguna "' . $admUser . '" sudah dipakai (harus unik se-aplikasi).';
        }
        if ($galat) {
            foreach ($galat as $g) {
                flash($g, 'err');
            }
            redirect('sekolah.php');
        }

        $now = date('Y-m-d H:i:s');
        db()->exec('BEGIN IMMEDIATE');
        try {
            q('INSERT INTO sekolah (nama, npsn, alamat, telepon, email, kepala_sekolah, nip_kepala_sekolah, jenis,
                    verifikasi, aktif, didaftar, ditinjau, dibuat) VALUES (?,?,?,?,?,?,?,?,"disetujui",1,?,?,?)',
                [$nama, $npsn, $alamat, $telepon, $email, $kepala, $nip, $jenis, $now, $now, $now]);
            $sid = (int) db()->lastInsertId();
            q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, aktif, dibuat) VALUES (?,?,?,?,?,1,?)',
                [$sid, $admUser, password_hash($admPass, PASSWORD_DEFAULT), $admNama, $admRole, $now]);
            provision_sekolah(db(), $sid);
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        flash('Sekolah/madrasah ' . $nama . ' ditambahkan beserta ' . count(mapel_awal()) . ' mata pelajaran awal, '
            . 'tahun ajaran, dan semester. Akun ' . role_label($admRole) . ': ' . $admUser . '.');
        redirect('sekolah.php');
    }

    if (!$s) {
        flash('Sekolah/madrasah tidak ditemukan.', 'err');
        redirect('sekolah.php');
    }

    // ---------------- simpan profil & aturan penilaian
    if ($aksi === 'simpan') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        if ($nama === '') {
            flash('Nama sekolah/madrasah wajib diisi.', 'err');
            redirect('sekolah.php');
        }
        $jenis = ((string) ($_POST['jenis'] ?? 'umum')) === 'madrasah' ? 'madrasah' : 'umum';
        $a = (float) str_replace(',', '.', (string) ($_POST['batas_a'] ?? 90));
        $b = (float) str_replace(',', '.', (string) ($_POST['batas_b'] ?? 80));
        $c = (float) str_replace(',', '.', (string) ($_POST['batas_c'] ?? 70));
        $bobot = (float) str_replace(',', '.', (string) ($_POST['bobot_asas'] ?? 50));
        $jf = max(1, min(12, (int) ($_POST['jumlah_formatif'] ?? 4)));
        $js = max(1, min(12, (int) ($_POST['jumlah_sumatif'] ?? 4)));
        if (!($a > $b && $b > $c && $c >= 0 && $a <= 100) || $bobot < 0 || $bobot > 100) {
            flash('Ambang predikat harus A > B > C (0-100) dan bobot ASAS 0-100%.', 'err');
            redirect('sekolah.php');
        }
        q('UPDATE sekolah SET nama=?, jenis=?, npsn=?, alamat=?, telepon=?, email=?, website=?, kepala_sekolah=?, nip_kepala_sekolah=?,
             batas_a=?, batas_b=?, batas_c=?, bobot_asas=?, jumlah_formatif=?, jumlah_sumatif=? WHERE id=?',
            [$nama, $jenis, trim((string) ($_POST['npsn'] ?? '')), trim((string) ($_POST['alamat'] ?? '')),
             trim((string) ($_POST['telepon'] ?? '')), trim((string) ($_POST['email'] ?? '')), trim((string) ($_POST['website'] ?? '')),
             trim((string) ($_POST['kepala_sekolah'] ?? '')), nip_bersih($_POST['nip_kepala_sekolah'] ?? ''),
             $a, $b, $c, $bobot, $jf, $js, $id]);
        settings_all(true);
        current_sekolah(true);
        flash('Profil & pengaturan ' . $nama . ' disimpan.');
        redirect('sekolah.php');
    }

    // ---------------- tambah akun pengelola sekolah
    if ($aksi === 'akun') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $sandi = (string) ($_POST['password'] ?? '');
        $role = in_array((string) ($_POST['role'] ?? ''), ['kepala', 'operator'], true) ? (string) $_POST['role'] : 'kepala';
        if ($nama === '' || $username === '' || strlen($sandi) < 6) {
            flash('Nama, nama pengguna, dan kata sandi (min. 6 karakter) wajib diisi.', 'err');
        } elseif (one('SELECT id FROM users WHERE username = ?', [$username])) {
            flash('Nama pengguna "' . $username . '" sudah dipakai.', 'err');
        } else {
            q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, aktif, dibuat) VALUES (?,?,?,?,?,1,?)',
                [$id, $username, password_hash($sandi, PASSWORD_DEFAULT), $nama, $role, date('Y-m-d H:i:s')]);
            flash('Akun ' . role_label($role) . ' untuk ' . nama_sekolah_tampil($s) . ' dibuat: ' . $username . '.');
        }
        redirect('sekolah.php?edit=' . $id);
    }

    // ---------------- nonaktifkan / aktifkan kembali
    if ($aksi === 'nonaktif' || $aksi === 'aktifkan') {
        if ($aksi === 'nonaktif') {
            $jumlahAktif = (int) val('SELECT COUNT(*) FROM sekolah WHERE aktif = 1 AND verifikasi = "disetujui"', [], 0);
            if ($jumlahAktif <= 1) {
                flash('Minimal harus ada satu sekolah/madrasah aktif.', 'err');
                redirect('sekolah.php');
            }
            q('UPDATE sekolah SET aktif = 0 WHERE id = ?', [$id]);
            if (sekolah_id() === $id) {
                set_sekolah_context(null);
            }
            $d = sekolah_dampak($id);
            flash(nama_sekolah_tampil($s) . ' dinonaktifkan. Datanya tetap utuh (' . $d['guru'] . ' guru, ' . $d['siswa']
                . ' siswa, ' . $d['nilai'] . ' baris nilai) dan dapat diaktifkan kembali.');
        } else {
            q('UPDATE sekolah SET aktif = 1 WHERE id = ?', [$id]);
            flash(nama_sekolah_tampil($s) . ' diaktifkan kembali.');
        }
        redirect('sekolah.php');
    }

    // ---------------- hapus permanen
    if ($aksi === 'hapus') {
        if (strtoupper(trim((string) ($_POST['konfirmasi'] ?? ''))) !== 'HAPUS') {
            flash('Ketik HAPUS pada kotak konfirmasi untuk menghapus sekolah/madrasah secara permanen.', 'err');
            redirect('sekolah.php');
        }
        if ((int) val('SELECT COUNT(*) FROM sekolah', [], 0) <= 1) {
            flash('Sekolah/madrasah terakhir tidak dapat dihapus.', 'err');
            redirect('sekolah.php');
        }
        $d = sekolah_dampak($id); // dihitung SEBELUM penghapusan agar laporannya benar
        $jmlAkun = (int) val('SELECT COUNT(*) FROM users WHERE sekolah_id = ?', [$id], 0);
        db()->exec('BEGIN IMMEDIATE');
        try {
            q('DELETE FROM absensi_detail WHERE absensi_id IN (SELECT id FROM absensi WHERE sekolah_id = ?)', [$id]);
            foreach (['absensi', 'nilai', 'jurnal', 'kasus', 'siswa', 'guru_mapel', 'guru_kelas'] as $tabel) {
                if ($tabel === 'guru_mapel') {
                    q('DELETE FROM guru_mapel WHERE guru_id IN (SELECT id FROM guru WHERE sekolah_id = ?)', [$id]);
                } elseif ($tabel === 'guru_kelas') {
                    q('DELETE FROM guru_kelas WHERE guru_id IN (SELECT id FROM guru WHERE sekolah_id = ?)', [$id]);
                } else {
                    q('DELETE FROM ' . $tabel . ' WHERE sekolah_id = ?', [$id]);
                }
            }
            $taIds = array_map('intval', array_column(all('SELECT id FROM tahun_ajaran WHERE sekolah_id = ?', [$id]), 'id'));
            foreach ($taIds as $ta) {
                q('DELETE FROM semester WHERE ta_id = ?', [$ta]);
            }
            q('DELETE FROM tahun_ajaran WHERE sekolah_id = ?', [$id]);
            q('DELETE FROM users WHERE sekolah_id = ?', [$id]);
            q('DELETE FROM kelas WHERE sekolah_id = ?', [$id]);
            q('DELETE FROM mapel WHERE sekolah_id = ?', [$id]);
            q('DELETE FROM guru WHERE sekolah_id = ?', [$id]);
            q('DELETE FROM sekolah WHERE id = ?', [$id]);
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        if (sekolah_id() === $id) {
            set_sekolah_context(null);
        }
        flash('Sekolah/madrasah ' . nama_sekolah_tampil($s) . ' dihapus permanen: ' . $d['kelas'] . ' kelas, ' . $d['siswa']
            . ' siswa, ' . $d['guru'] . ' guru, ' . $d['nilai'] . ' baris nilai, ' . $jmlAkun . ' akun.', 'warn');
        redirect('sekolah.php');
    }

    redirect('sekolah.php');
}

// ---------------------------------------------------------------- data halaman
$cari = trim((string) ($_GET['cari'] ?? ''));
$fStatus = (string) ($_GET['status'] ?? '');
$edit = (int) ($_GET['edit'] ?? 0);
$editSekolah = $edit > 0 ? sekolah_by_id($edit) : null;

$w = [];
$p = [];
if ($cari !== '') {
    $w[] = '(nama LIKE ? OR npsn LIKE ? OR alamat LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($p, $like, $like, $like);
}
$daftar = all('SELECT * FROM sekolah' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY nama', $p);
$diaktifkan = [];
$nonaktif = [];
$belumDisetujui = [];
foreach ($daftar as $r) {
    if (($r['verifikasi'] ?? 'disetujui') !== 'disetujui') {
        $belumDisetujui[] = $r;
    } elseif ((int) $r['aktif'] === 1) {
        $diaktifkan[] = $r;
    } else {
        $nonaktif[] = $r;
    }
}
$kodeSekarang = sekolah_id();

/** Kartu ringkas sebuah sekolah + daftar akunnya. */
function kartu_sekolah(array $r, int $kodeSekarang, bool $aktif): void
{
    $sid = (int) $r['id'];
    $st = sekolah_stat($sid);
    $d = sekolah_dampak($sid);
    $akun = all('SELECT nama, username, role, aktif FROM users WHERE sekolah_id = ? ORDER BY role, nama', [$sid]);
    $tandaKelola = $kodeSekarang === $sid;
    ?>
    <div class="card<?= $tandaKelola ? ' card-terpilih' : '' ?>">
      <div class="card-head">
        <div>
          <h2><?= tetap_kata(nama_sekolah_tampil($r)) ?>
            <span class="pill <?= $aktif ? 'pill-ok' : 'pill-gray' ?>"><?= $aktif ? 'Aktif' : 'Nonaktif' ?></span>
            <span class="pill pill-gold"><?= $r['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah Umum' ?></span>
            <?php if ($tandaKelola): ?><span class="pill pill-B">sedang dikelola</span><?php endif; ?>
          </h2>
          <p>NPSN <?= e((string) ($r['npsn'] ?: '-')) ?> &middot; <?= e((string) ($r['alamat'] ?: 'alamat belum diisi')) ?>
            &middot; Kepala: <?= e((string) ($r['kepala_sekolah'] ?: '-')) ?></p>
        </div>
        <div class="row-actions">
          <?php if ($aktif && !$tandaKelola): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="masuk">
              <input type="hidden" name="id" value="<?= $sid ?>">
              <button class="btn btn-primary btn-sm" type="submit"><?= ikon('switch', 14) ?> Kelola</button>
            </form>
          <?php elseif ($tandaKelola): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="keluar">
              <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('logout', 14) ?> Keluar dari konteks</button>
            </form>
          <?php endif; ?>
          <a class="btn btn-ghost btn-sm" href="sekolah.php?edit=<?= $sid ?>"><?= ikon('edit', 14) ?> Ubah</a>
          <?php if ($aktif): ?>
            <form method="post" data-confirm="Nonaktifkan <?= e((string) $r['nama']) ?>? Datanya tetap tersimpan dan dapat diaktifkan kembali.">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="nonaktif">
              <input type="hidden" name="id" value="<?= $sid ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('lock', 14) ?> Nonaktifkan</button>
            </form>
          <?php else: ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="aktifkan">
              <input type="hidden" name="id" value="<?= $sid ?>">
              <button class="btn btn-ok btn-sm" type="submit"><?= ikon('check', 14) ?> Aktifkan</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <div class="stat-mini mb16">
        <div><b><?= $st['kelas'] ?></b><span>Kelas</span></div>
        <div><b><?= $st['guru'] ?></b><span>Guru aktif</span></div>
        <div><b><?= $st['siswa'] ?></b><span>Siswa aktif</span></div>
        <div><b><?= $st['mapel'] ?></b><span>Mata pelajaran</span></div>
        <div><b><?= $st['akun'] ?></b><span>Akun</span></div>
        <div><b><?= $st['jurnal'] ?></b><span>Jurnal</span></div>
        <div><b><?= $st['nilai'] ?></b><span>Baris nilai</span></div>
        <div><b><?= $st['absensi'] ?></b><span>Pertemuan</span></div>
      </div>

      <details class="editbox" <?= $tandaKelola ? 'open' : '' ?>>
        <summary><?= ikon('users', 14) ?> Akun pengelola (<?= count($akun) ?>)</summary>
        <?php if ($akun): ?>
          <div class="chip-list mb8">
            <?php foreach ($akun as $a): ?>
              <span class="chip<?= (int) $a['aktif'] === 1 ? '' : ' chip-mati' ?>">
                <?= e((string) $a['nama']) ?> &middot; <?= e((string) $a['username']) ?> &middot; <?= e(role_label((string) $a['role'])) ?>
              </span>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="hint">Belum ada akun untuk sekolah/madrasah ini.</p>
        <?php endif; ?>
      </details>

      <?php if (!$aktif): ?>
        <details class="editbox danger-zone">
          <summary><?= ikon('alert', 14) ?> Zona berbahaya — hapus permanen</summary>
          <p class="sm mut">Menghapus sekolah/madrasah ini akan menghapus permanen:
            <?= $d['kelas'] ?> kelas, <?= $d['mapel'] ?> mata pelajaran, <?= $d['guru'] ?> data guru,
            <?= $d['siswa'] ?> siswa, <?= $d['nilai'] ?> baris nilai, <?= $d['absensi'] ?> pertemuan daftar hadir,
            <?= $d['jurnal'] ?> jurnal, <?= $d['kasus'] ?> catatan kasus, dan <?= $d['akun'] ?> akun.</p>
          <form method="post" class="flex mt8" data-confirm="Hapus permanen <?= e((string) $r['nama']) ?> beserta seluruh datanya?">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" value="<?= $sid ?>">
            <input type="text" name="konfirmasi" placeholder="ketik HAPUS" style="max-width:180px">
            <button class="btn btn-danger" type="submit"><?= ikon('trash', 15) ?> Hapus permanen</button>
          </form>
        </details>
      <?php endif; ?>
    </div>
    <?php
}

page_top('Kelola Sekolah/Madrasah', 'Super Admin &middot; ' . count($daftar) . ' sekolah/madrasah terdaftar &middot; '
    . jumlah_pendaftaran_menunggu() . ' menunggu persetujuan');
?>
<div class="grid g4">
  <div class="card stat card-tight">
    <div class="stat-ic g"><?= ikon('building', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= count($diaktifkan) ?></div><div class="stat-lbl">Sekolah/madrasah aktif</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic o"><?= ikon('clock', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= count($belumDisetujui) ?></div><div class="stat-lbl">Belum disetujui</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic p"><?= ikon('lock', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= count($nonaktif) ?></div><div class="stat-lbl">Nonaktif</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('users', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) val('SELECT COUNT(*) FROM users', [], 0) ?></div><div class="stat-lbl">Akun terdaftar</div></div>
  </div>
</div>

<?php if (count($belumDisetujui) > 0): ?>
  <div class="alert alert-warn">
    <?= ikon('star', 16) ?>
    <span>Ada <b><?= count($belumDisetujui) ?></b> pendaftaran yang menunggu persetujuan.
      <a href="pendaftaran.php">Tinjau pendaftaran</a> — login sekolah tersebut masih ditolak sampai disetujui.</span>
  </div>
<?php endif; ?>

<div class="grid g12">
  <div class="card">
    <div class="card-head">
      <div><h2>Tambah Sekolah/Madrasah</h2><p><?= count(mapel_awal()) ?> mata pelajaran awal, tahun ajaran, dan semester dibuat otomatis.</p></div>
    </div>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="tambah">
      <div>
        <label class="f" for="nama">Nama Sekolah/Madrasah *</label>
        <input type="text" id="nama" name="nama" required placeholder="mis. MA. Sirojuth Tholibiin Mootilango">
      </div>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="jenis">Jenis</label>
          <select id="jenis" name="jenis">
            <option value="umum">Sekolah Umum (SD/SMP/SMA/SMK)</option>
            <option value="madrasah">Madrasah (MI/MTs/MA)</option>
          </select>
          <div class="hint">Menentukan istilah pada aplikasi &amp; dokumen cetak sekolah tersebut.</div>
        </div>
        <div>
          <label class="f" for="npsn">NPSN</label>
          <input type="text" id="npsn" name="npsn">
        </div>
      </div>
      <div>
        <label class="f" for="alamat">Alamat</label>
        <textarea id="alamat" name="alamat" rows="2"></textarea>
      </div>
      <div class="grid g3" style="gap:16px">
        <div>
          <label class="f" for="telepon">Telepon</label>
          <input type="text" id="telepon" name="telepon">
        </div>
        <div>
          <label class="f" for="email">Email</label>
          <input type="text" id="email" name="email">
        </div>
        <div>
          <label class="f" for="nip_kepala_sekolah">NIP Kepala</label>
          <input type="text" id="nip_kepala_sekolah" name="nip_kepala_sekolah">
        </div>
      </div>
      <div>
        <label class="f" for="kepala_sekolah">Nama Kepala Sekolah/Madrasah</label>
        <input type="text" id="kepala_sekolah" name="kepala_sekolah">
      </div>
      <div class="divider"></div>
      <div class="section-title"><?= ikon('key', 15) ?> Akun Pengelola Pertama</div>
      <div class="grid g3" style="gap:16px">
        <div>
          <label class="f" for="admin_nama">Nama *</label>
          <input type="text" id="admin_nama" name="admin_nama" required>
        </div>
        <div>
          <label class="f" for="admin_username">Nama Pengguna *</label>
          <input type="text" id="admin_username" name="admin_username" required>
        </div>
        <div>
          <label class="f" for="admin_role">Peran</label>
          <select id="admin_role" name="admin_role">
            <option value="kepala">Kepala Sekolah/Madrasah</option>
            <option value="operator">Admin Operator</option>
          </select>
        </div>
      </div>
      <div>
        <label class="f" for="admin_password">Kata Sandi * (min. 6 karakter)</label>
        <input type="password" id="admin_password" name="admin_password" minlength="6" required>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= ikon('plus', 16) ?> Tambah Sekolah/Madrasah</button>
      </div>
    </form>
  </div>

  <div class="grid" style="gap:20px;align-content:start">
    <?php if ($editSekolah): ?>
      <div class="card">
        <div class="card-head">
          <div><h2>Ubah: <?= tetap_kata(nama_sekolah_tampil($editSekolah)) ?></h2><p>Profil, ambang predikat, dan aturan penilaian.</p></div>
          <a class="btn btn-ghost btn-sm" href="sekolah.php">Tutup</a>
        </div>
        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="simpan">
          <input type="hidden" name="id" value="<?= (int) $editSekolah['id'] ?>">
          <div>
            <label class="f" for="enama">Nama *</label>
            <input type="text" id="enama" name="nama" value="<?= e((string) $editSekolah['nama']) ?>" required>
          </div>
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="ejenis">Jenis</label>
              <select id="ejenis" name="jenis">
                <option value="umum" <?= $editSekolah['jenis'] === 'umum' ? 'selected' : '' ?>>Sekolah Umum</option>
                <option value="madrasah" <?= $editSekolah['jenis'] === 'madrasah' ? 'selected' : '' ?>>Madrasah</option>
              </select>
            </div>
            <div>
              <label class="f" for="enpsn">NPSN</label>
              <input type="text" id="enpsn" name="npsn" value="<?= e((string) $editSekolah['npsn']) ?>">
            </div>
          </div>
          <div>
            <label class="f" for="ealamat">Alamat</label>
            <textarea id="ealamat" name="alamat" rows="2"><?= e((string) $editSekolah['alamat']) ?></textarea>
          </div>
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="etelepon">Telepon</label>
              <input type="text" id="etelepon" name="telepon" value="<?= e((string) $editSekolah['telepon']) ?>">
            </div>
            <div>
              <label class="f" for="eemail">Email</label>
              <input type="text" id="eemail" name="email" value="<?= e((string) $editSekolah['email']) ?>">
            </div>
          </div>
          <div>
            <label class="f" for="ewebsite">Situs/laman</label>
            <input type="text" id="ewebsite" name="website" value="<?= e((string) $editSekolah['website']) ?>">
          </div>
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="ekepala">Nama Kepala</label>
              <input type="text" id="ekepala" name="kepala_sekolah" value="<?= e((string) $editSekolah['kepala_sekolah']) ?>">
            </div>
            <div>
              <label class="f" for="enip">NIP Kepala</label>
              <input type="text" id="enip" name="nip_kepala_sekolah" value="<?= e((string) $editSekolah['nip_kepala_sekolah']) ?>">
            </div>
          </div>
          <div class="divider"></div>
          <div class="section-title"><?= ikon('chart', 15) ?> Aturan Penilaian</div>
          <div class="grid g3" style="gap:16px">
            <div>
              <label class="f" for="ejf">Kolom Formatif</label>
              <input type="number" id="ejf" name="jumlah_formatif" min="1" max="12" value="<?= (int) $editSekolah['jumlah_formatif'] ?>">
            </div>
            <div>
              <label class="f" for="ejs">Kolom Sumatif Lingkup Materi</label>
              <input type="number" id="ejs" name="jumlah_sumatif" min="1" max="12" value="<?= (int) $editSekolah['jumlah_sumatif'] ?>">
            </div>
            <div>
              <label class="f" for="ebobot">Bobot ASAS (%)</label>
              <input type="number" id="ebobot" name="bobot_asas" min="0" max="100" step="1" value="<?= e(r0((float) $editSekolah['bobot_asas'])) ?>">
            </div>
          </div>
          <div class="grid g3" style="gap:16px">
            <div>
              <label class="f" for="ea">Predikat A mulai</label>
              <input type="number" id="ea" name="batas_a" min="0" max="100" value="<?= e(r0((float) $editSekolah['batas_a'])) ?>">
            </div>
            <div>
              <label class="f" for="eb">Predikat B mulai</label>
              <input type="number" id="eb" name="batas_b" min="0" max="100" value="<?= e(r0((float) $editSekolah['batas_b'])) ?>">
            </div>
            <div>
              <label class="f" for="ec">Predikat C mulai</label>
              <input type="number" id="ec" name="batas_c" min="0" max="100" value="<?= e(r0((float) $editSekolah['batas_c'])) ?>">
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan</button>
          </div>
        </form>

        <div class="divider"></div>
        <div class="section-title mb8"><?= ikon('key', 15) ?> Tambah Akun Pengelola</div>
        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="akun">
          <input type="hidden" name="id" value="<?= (int) $editSekolah['id'] ?>">
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="akun_nama">Nama *</label>
              <input type="text" id="akun_nama" name="nama" required>
            </div>
            <div>
              <label class="f" for="akun_user">Nama Pengguna *</label>
              <input type="text" id="akun_user" name="username" required>
            </div>
          </div>
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="akun_role">Peran</label>
              <select id="akun_role" name="role">
                <option value="kepala">Kepala Sekolah/Madrasah</option>
                <option value="operator">Admin Operator</option>
              </select>
            </div>
            <div>
              <label class="f" for="akun_pass">Kata Sandi * (min. 6)</label>
              <input type="password" id="akun_pass" name="password" minlength="6" required>
            </div>
          </div>
          <button class="btn btn-ghost" type="submit"><?= ikon('plus', 16) ?> Tambah akun</button>
        </form>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head">
        <div><h2>Daftar Sekolah/Madrasah</h2><p><?= count($daftar) ?> data ditemukan</p></div>
      </div>
      <form class="filterbar" method="get">
        <div style="min-width:240px">
          <label class="f" for="cari">Cari</label>
          <input type="search" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="nama / NPSN / alamat...">
        </div>
        <button class="btn btn-ghost" type="submit"><?= ikon('search', 15) ?> Cari</button>
        <?php if ($cari !== ''): ?><a class="btn btn-ghost" href="sekolah.php">Reset</a><?php endif; ?>
      </form>
    </div>
  </div>
</div>

<div class="grid" style="gap:20px">
  <div class="section-title"><?= ikon('building', 15) ?> Sekolah/Madrasah Aktif (<?= count($diaktifkan) ?>)</div>
  <?php if (!$diaktifkan): ?>
    <div class="card"><p class="mut sm" style="margin:0">Belum ada sekolah/madrasah aktif.</p></div>
  <?php endif; ?>
  <?php foreach ($diaktifkan as $r) { kartu_sekolah($r, $kodeSekarang, true); } ?>

  <?php if ($nonaktif): ?>
    <div class="section-title mt24"><?= ikon('lock', 15) ?> Sekolah/Madrasah Nonaktif (<?= count($nonaktif) ?>)</div>
    <div class="alert alert-warn" style="margin-bottom:0">
      <?= ikon('alert', 16) ?>
      <span>Sekolah/madrasah nonaktif tidak dapat dipakai: akunnya tidak bisa masuk, hilang dari daftar lintas sekolah,
        dan tidak dapat dikelola sampai diaktifkan kembali. Datanya tetap utuh.</span>
    </div>
    <?php foreach ($nonaktif as $r) { kartu_sekolah($r, $kodeSekarang, false); } ?>
  <?php endif; ?>

  <?php if ($belumDisetujui): ?>
    <div class="section-title mt24"><?= ikon('clock', 15) ?> Belum Disetujui (<?= count($belumDisetujui) ?>)</div>
    <div class="card">
      <p class="sm mut" style="margin:0 0 12px">Pendaftaran berikut menunggu keputusan Super Admin pada halaman
        <a href="pendaftaran.php">Pendaftaran</a>.</p>
      <div class="chip-list">
        <?php foreach ($belumDisetujui as $r): ?>
          <span class="chip"><?= tetap_kata(nama_sekolah_tampil($r)) ?> &middot; <?= e(verifikasi_label((string) $r['verifikasi'])) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php
page_bottom();
