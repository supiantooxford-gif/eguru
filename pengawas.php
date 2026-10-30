<?php
/**
 * AKUN PENGAWAS SEKOLAH/MADRASAH (khusus Super Admin) — ditiru dari aplikasi Supervisi Guru.
 *
 * Pengawas bersifat LINTAS sekolah (`users.sekolah_id` NULL, seperti Super Admin) namun TERBATAS:
 * hanya sekolah/madrasah yang dicentang di sini (tabel `pengawas_sekolah`) yang boleh dilihat
 * rekapan & datanya, dan hanya untuk MELIHAT & MENCETAK (tanpa mengubah apa pun).
 * Pembatasan itu ditegakkan `gerbang_pengawas()` (dipanggil dari `require_login()`).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

// Halaman ini mengelola sekolah umum DAN madrasah sekaligus → istilah generik "Sekolah".
$GLOBALS['__vs_istilah_nonaktif'] = true;

$u = require_super();
$aku = (int) $u['id'];

/**
 * Ganti daftar sekolah binaan seorang pengawas sesuai centang pada formulir.
 *
 * ATURAN "1 SEKOLAH/MADRASAH HANYA UNTUK 1 PENGAWAS" (permintaan pemilik: *"Pada saat memilih
 * madrasah binaan untuk pengawas, pastikan madrasah yang sudah di pilih tidak bisa di pilih atau
 * dicentang lagi untuk pengawas lainnya. Sehingga 1 madrasah hanya untuk 1 pangawas"*):
 * sekolah yang SUDAH dibina pengawas lain **diabaikan** (tidak disimpan) dan namanya dilaporkan,
 * sehingga tidak ada sekolah yang berpindah/berbagi pembina secara diam-diam. Baris binaan milik
 * pengawas LAIN tidak pernah diubah/dihapus dari sini.
 *
 * @return array{jumlah:int,ditolak:array<int,string>} jumlah binaan tersimpan + nama sekolah yang ditolak
 */
function simpan_binaan(int $userId, array $sekolahIds): array
{
    $bersih = [];
    $ditolak = [];
    foreach ($sekolahIds as $sid) {
        $sid = (int) $sid;
        if ($sid <= 0 || !one('SELECT id FROM sekolah WHERE id = ? AND verifikasi = "disetujui" AND aktif = 1', [$sid])) {
            continue;
        }
        // Sudah dibina pengawas LAIN → tidak boleh dicentang lagi (ditegakkan juga di SERVER,
        // bukan hanya dengan menonaktifkan kotaknya di peramban).
        $pembina = pengawas_binaan_oleh($sid, $userId);
        if ($pembina !== null) {
            $ditolak[$sid] = (string) val('SELECT nama FROM sekolah WHERE id = ?', [$sid], 'sekolah #' . $sid);
            continue;
        }
        $bersih[$sid] = true;
    }
    $lama = array_map('intval', array_column(all('SELECT sekolah_id FROM pengawas_sekolah WHERE user_id = ?', [$userId]), 'sekolah_id'));
    $tambah = array_diff(array_keys($bersih), $lama);
    $hapus = array_diff($lama, array_keys($bersih));

    db()->exec('BEGIN IMMEDIATE');
    try {
        foreach ($tambah as $sid) {
            q('INSERT OR IGNORE INTO pengawas_sekolah (user_id, sekolah_id, dibuat) VALUES (?,?,?)',
                [$userId, (int) $sid, date('Y-m-d H:i:s')]);
        }
        foreach ($hapus as $sid) {
            q('DELETE FROM pengawas_sekolah WHERE user_id = ? AND sekolah_id = ?', [$userId, (int) $sid]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return ['jumlah' => count($bersih), 'ditolak' => array_values($ditolak)];
}

$sekolahPilihan = all('SELECT * FROM sekolah WHERE verifikasi = "disetujui" AND aktif = 1 ORDER BY nama');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $p = $id > 0 ? one('SELECT * FROM users WHERE id = ? AND role = "pengawas"', [$id]) : null;

    if ($aksi === 'tambah') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        $binaan = is_array($_POST['sekolah'] ?? null) ? $_POST['sekolah'] : [];

        $galat = [];
        if ($nama === '') {
            $galat[] = 'Nama pengawas wajib diisi.';
        }
        if ($username === '' || strlen($username) < 3) {
            $galat[] = 'Nama pengguna minimal 3 karakter.';
        }
        if (one('SELECT id FROM users WHERE username = ?', [$username])) {
            $galat[] = 'Nama pengguna "' . $username . '" sudah dipakai (harus unik se-aplikasi).';
        }
        if (strlen($password) < 6) {
            $galat[] = 'Kata sandi minimal 6 karakter.';
        }
        if ($galat) {
            foreach ($galat as $g) {
                flash($g, 'err');
            }
            redirect('pengawas.php');
        }

        db()->exec('BEGIN IMMEDIATE');
        try {
            q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, aktif, dibuat)
               VALUES (NULL,?,?,?,"pengawas",?,?)',
                [$username, password_hash($password, PASSWORD_DEFAULT), $nama, $aktif, date('Y-m-d H:i:s')]);
            $uid = (int) db()->lastInsertId();
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        $hasil = simpan_binaan($uid, $binaan);
        flash('Akun pengawas ' . $nama . ' dibuat dengan ' . $hasil['jumlah'] . ' sekolah/madrasah binaan. '
            . 'Akun ini hanya dapat melihat & mencetak data binaannya.');
        if ($hasil['ditolak']) {
            flash('Tidak dipilih karena sudah dibina pengawas lain (1 sekolah/madrasah hanya untuk 1 pengawas): '
                . implode(', ', $hasil['ditolak']) . '.', 'warn');
        }
        redirect('pengawas.php');
    }

    if (!$p) {
        flash('Akun pengawas tidak ditemukan.', 'err');
        redirect('pengawas.php');
    }

    // PERMINTAAN PEMILIK (v1.50.0): akun pengawas yang dibuat OTOMATIS dari pendaftaran sekolah
    // berstatus "menunggu ditinjau". Super Admin menandainya sudah ditinjau dari sini — setelah itu
    // pil "Menunggu ditinjau" hilang, tanpa mengubah status aktif/nonaktifnya.
    if ($aksi === 'tinjau') {
        q('UPDATE users SET ditinjau = 1 WHERE id = ? AND role = "pengawas"', [$id]);
        flash('Akun pengawas ' . $p['nama'] . ' ditandai sudah ditinjau.');
        redirect('pengawas.php');
    }

    if ($aksi === 'ubah') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        // Medan "Kata Sandi Baru" pada formulir Ubah: diisi = sandi DIGANTI, dikosongkan = dibiarkan.
        // (Sebelumnya isian itu diabaikan sehingga labelnya menyesatkan — dan kini penting: akun
        // pengawas otomatis dari pendaftaran bersandi ACAK, jadi sandinya harus dapat ditetapkan
        // Super Admin saat meninjau akun tersebut.)
        $sandiBaru = (string) ($_POST['password'] ?? '');
        $binaan = is_array($_POST['sekolah'] ?? null) ? $_POST['sekolah'] : [];
        $galat = [];
        if ($nama === '') {
            $galat[] = 'Nama pengawas wajib diisi.';
        }
        if ($username === '' || strlen($username) < 3) {
            $galat[] = 'Nama pengguna minimal 3 karakter.';
        }
        if (one('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $id])) {
            $galat[] = 'Nama pengguna "' . $username . '" sudah dipakai akun lain.';
        }
        if ($sandiBaru !== '' && strlen($sandiBaru) < 6) {
            $galat[] = 'Kata sandi baru minimal 6 karakter (kosongkan bila tidak diubah).';
        }
        if ($galat) {
            foreach ($galat as $g) {
                flash($g, 'err');
            }
            redirect('pengawas.php?edit=' . $id);
        }
        // `ditinjau = 1`: menyimpan perubahan berarti akun itu SUDAH ditinjau Super Admin.
        if ($sandiBaru !== '') {
            q('UPDATE users SET nama = ?, username = ?, aktif = ?, ditinjau = 1, password_hash = ?
               WHERE id = ? AND role = "pengawas"',
                [$nama, $username, $aktif, password_hash($sandiBaru, PASSWORD_DEFAULT), $id]);
        } else {
            q('UPDATE users SET nama = ?, username = ?, aktif = ?, ditinjau = 1
               WHERE id = ? AND role = "pengawas"',
                [$nama, $username, $aktif, $id]);
        }
        $hasil = simpan_binaan($id, $binaan);
        flash('Akun pengawas ' . $nama . ' diperbarui (' . $hasil['jumlah'] . ' sekolah/madrasah binaan)'
            . ($sandiBaru !== '' ? ' beserta kata sandi barunya' : '') . '.');
        if ($hasil['ditolak']) {
            flash('Tidak dapat dicentang karena sudah dibina pengawas lain (1 sekolah/madrasah hanya untuk 1 pengawas): '
                . implode(', ', $hasil['ditolak']) . '.', 'warn');
        }
        redirect('pengawas.php');
    }

    if ($aksi === 'reset') {
        $sandi = (string) ($_POST['password'] ?? '');
        if (strlen($sandi) < 6) {
            flash('Kata sandi baru minimal 6 karakter.', 'err');
        } else {
            q('UPDATE users SET password_hash = ?, ditinjau = 1 WHERE id = ?', [password_hash($sandi, PASSWORD_DEFAULT), $id]);
            flash('Kata sandi akun pengawas ' . $p['nama'] . ' berhasil diganti.');
        }
        redirect('pengawas.php');
    }

    if ($aksi === 'toggle') {
        if ($id === $aku) {
            flash('Akun Anda sendiri tidak dapat dinonaktifkan.', 'err');
        } else {
            q('UPDATE users SET aktif = ?, ditinjau = 1 WHERE id = ? AND role = "pengawas"',
                [(int) $p['aktif'] === 1 ? 0 : 1, $id]);
            flash('Status akun pengawas ' . $p['nama'] . ' diperbarui.', 'warn');
        }
        redirect('pengawas.php');
    }

    if ($aksi === 'hapus') {
        db()->exec('BEGIN IMMEDIATE');
        try {
            q('DELETE FROM pengawas_sekolah WHERE user_id = ?', [$id]);
            q('DELETE FROM users WHERE id = ? AND role = "pengawas"', [$id]);
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        flash('Akun pengawas ' . $p['nama'] . ' beserta binaannya dihapus.', 'warn');
        redirect('pengawas.php');
    }

    redirect('pengawas.php');
}

$edit = (int) ($_GET['edit'] ?? 0) > 0
    ? one('SELECT * FROM users WHERE id = ? AND role = "pengawas"', [(int) $_GET['edit']])
    : null;
$rows = all('SELECT * FROM users WHERE role = "pengawas" ORDER BY aktif DESC, nama');
$binaanEdit = $edit ? array_map('intval', array_column(
    all('SELECT sekolah_id FROM pengawas_sekolah WHERE user_id = ?', [(int) $edit['id']]), 'sekolah_id')) : [];

// ATURAN "1 sekolah/madrasah hanya untuk 1 pengawas" (permintaan pemilik): peta binaan milik pengawas
// LAIN dipakai untuk menonaktifkan pilihannya di formulir. Saat sedang mengubah akun, binaan akun itu
// sendiri dikecualikan supaya tetap bisa dipertahankan/dicentang ulang.
// ⚠️ WAJIB dihitung SETELAH $edit (variabel itu baru ada di baris atas) — bila ditaruh lebih awal,
// $edit masih null sehingga sekolah milik akun yang sedang diubah ikut terkunci (pernah terjadi).
$editId = (int) ($edit['id'] ?? 0);
$binaanLain = pengawas_binaan_peta($editId);
// Data LAMA yang (sebelum aturan ini) masih dibina lebih dari satu pengawas — dilaporkan, tidak dihapus.
$binaanGanda = pengawas_binaan_ganda();
$jmlAktif = (int) val('SELECT COUNT(*) FROM users WHERE role = "pengawas" AND aktif = 1', [], 0);
// Akun pengawas yang dibuat OTOMATIS dari pendaftaran sekolah (permintaan pemilik, v1.50.0).
$menunggu = pengawas_menunggu_tinjau();

page_top('Akun Pengawas', 'Super Admin &middot; ' . count($rows) . ' akun pengawas (' . $jmlAktif . ' aktif)');
?>

<?php // PERMINTAAN PEMILIK (v1.50.0): "Nama Pengawas yang diinput dari halaman pendaftaran, otomatis
      // masuk di akun super admin di daftar akun pengawas tapi status masih non aktif untuk ditinjau
      // oleh super admin". Kartu ini mengumpulkan akun-akun tersebut supaya mudah ditinjau: masing-masing
      // masih NONAKTIF (tidak dapat dipakai masuk) sampai Super Admin meninjau & mengaktifkannya. ?>
<?php if ($menunggu): ?>
  <div class="card card-tight" id="cardTinjauPengawas">
    <div class="card-head">
      <div>
        <h2><?= ikon('alert', 16) ?> Akun Pengawas Menunggu Ditinjau</h2>
        <p><b><?= count($menunggu) ?> akun</b> dibuat <b>otomatis</b> dari <b>pendaftaran sekolah/madrasah</b>
          (nama pengawas bina yang diisi pendaftar). Statusnya masih <b>NONAKTIF</b> — belum dapat
          dipakai masuk sampai Anda meninjau: periksa namanya, tetapkan kata sandi baru, lalu aktifkan.</p>
      </div>
      <span class="chip"><?= count($menunggu) ?> menunggu</span>
    </div>
    <div class="table-wrap">
      <table class="tbl" id="tblTinjauPengawas">
        <thead>
          <tr>
            <th class="kiri">Nama Pengawas (dari pendaftaran)</th>
            <th class="kiri">Sekolah/Madrasah Binaan</th>
            <th class="ctr">Status</th>
            <th class="ctr">Tindakan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($menunggu as $m):
              $mid = (int) $m['id'];
              $mbinaan = sekolah_binaan($mid, true);
              ?>
            <tr>
              <td class="kiri">
                <b><?= e((string) $m['nama']) ?></b>
                <span class="tbl-sub">Nama pengguna <b><?= e((string) $m['username']) ?></b> &middot;
                  dibuat <?= e(tanggal_id((string) $m['dibuat'], true)) ?></span>
              </td>
              <td class="kiri">
                <?php if (!$mbinaan): ?>
                  <span class="mut sm">Belum ada binaan — tetapkan pada formulir <b>Ubah &amp; Binaan</b>.</span>
                <?php else: ?>
                  <div class="chip-list">
                    <?php foreach ($mbinaan as $sk): ?>
                      <span class="chip"><?= e((string) $sk['nama']) ?> &middot; <?= e($sk['jenis'] === 'madrasah' ? 'Madrasah' : 'Umum') ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="ctr"><span class="pill pill-gray">Nonaktif</span></td>
              <td class="ctr">
                <div class="row-actions" style="justify-content:center">
                  <a class="btn btn-primary btn-sm" href="pengawas.php?edit=<?= $mid ?>#formPengawas"><?= ikon('edit', 14) ?> Tinjau &amp; Ubah</a>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="tinjau">
                    <input type="hidden" name="id" value="<?= $mid ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('check', 14) ?> Tandai sudah ditinjau</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint" style="margin-bottom:0">Akun ini dibuat dengan <b>kata sandi acak</b> (tidak ditampilkan
      di mana pun). Buka <b>Tinjau &amp; Ubah</b> untuk menetapkan kata sandi baru sekaligus mengaktifkannya,
      atau tekan <b>Aktifkan</b> pada tabel di bawah bila kata sandinya akan diatur kemudian.</p>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <div>
      <h2>Pengawas Sekolah/Madrasah</h2>
      <p>Akun pengawas bersifat <b>lintas sekolah/madrasah</b>, tetapi hanya boleh
        <b>melihat &amp; mencetak</b> data sekolah/madrasah yang Anda tetapkan sebagai <b>binaan</b>.
        Pengawas tidak dapat mengubah data apa pun.</p>
    </div>
    <span class="chip"><?= count($rows) ?> akun</span>
  </div>

  <form method="post" class="form-grid" id="formPengawas">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="<?= $edit ? 'ubah' : 'tambah' ?>">
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="grid g3" style="gap:16px">
      <div>
        <label class="f" for="nama">Nama Pengawas *</label>
        <input type="text" id="nama" name="nama" required value="<?= e((string) ($edit['nama'] ?? '')) ?>"
               placeholder="mis. Drs. H. Ahmad Syaifuddin">
      </div>
      <div>
        <label class="f" for="username">Nama Pengguna *</label>
        <input type="text" id="username" name="username" required value="<?= e((string) ($edit['username'] ?? '')) ?>"
               placeholder="mis. pengawas1">
      </div>
      <div>
        <label class="f" for="password"><?= $edit ? 'Kata Sandi Baru (kosongkan bila tidak diubah)' : 'Kata Sandi * (min. 6)' ?></label>
        <input type="password" id="password" name="password" <?= $edit ? '' : 'required' ?> minlength="6">
      </div>
    </div>

    <div>
      <div class="section-title mb8"><?= ikon('school', 14) ?> Sekolah/Madrasah Binaan</div>
      <?php if (!$sekolahPilihan): ?>
        <div class="alert alert-warn">
          <?= ikon('alert', 16) ?>
          <span>Belum ada sekolah/madrasah aktif &amp; disetujui. Tambahkan pada halaman
            <a href="sekolah.php">Kelola Sekolah</a> terlebih dahulu.</span>
        </div>
      <?php else: ?>
        <?php // Permintaan pemilik: sekolah/madrasah yang SUDAH dipilih untuk pengawas lain tidak boleh
              // dicentang lagi — kotaknya dinonaktifkan & diberi keterangan pembinanya (ditegakkan juga
              // di server oleh simpan_binaan(), jadi mengakalinya dari sisi peramban tetap gagal). ?>
        <div class="pengawas-pilih">
          <?php foreach ($sekolahPilihan as $sk):
              $skId = (int) $sk['id'];
              $dibinaOleh = $binaanLain[$skId] ?? '';   // nama pengawas lain yang membina sekolah ini
              ?>
            <label class="pengawas-item<?= $dibinaOleh !== '' ? ' terkunci' : '' ?>"
                   title="<?= $dibinaOleh !== '' ? 'Sudah dibina ' . e($dibinaOleh) : '' ?>">
              <input type="checkbox" name="sekolah[]" value="<?= $skId ?>"
                     <?= in_array($skId, $binaanEdit, true) ? 'checked' : '' ?>
                     <?= $dibinaOleh !== '' ? 'disabled' : '' ?>>
              <span>
                <b><?= e((string) $sk['nama']) ?></b>
                <span class="xs mut"><?= e($sk['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah Umum') ?>
                  &middot; NPSN <?= e((string) ($sk['npsn'] ?: '-')) ?></span>
                <?php if ($dibinaOleh !== ''): ?>
                  <span class="xs binaan-terkunci"><?= ikon('lock', 12) ?> Sudah dibina <b><?= e($dibinaOleh) ?></b></span>
                <?php endif; ?>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="flex mt8">
          <button class="btn btn-ghost btn-sm" type="button" id="pilihSemua">Pilih semua</button>
          <button class="btn btn-ghost btn-sm" type="button" id="pilihKosong">Kosongkan</button>
          <span class="hint" id="hitungBinaan"></span>
        </div>
        <p class="hint">Satu sekolah/madrasah hanya boleh dibina oleh <b>satu pengawas</b>. Pilihan yang
          sudah dibina pengawas lain <b>terkunci</b> (kosongkan dulu binaan pengawas tersebut bila ingin
          memindahkannya).</p>
      <?php endif; ?>
    </div>

    <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600">
      <input type="checkbox" name="aktif" value="1" style="width:16px;height:16px;accent-color:var(--accent)"
             <?= (int) ($edit['aktif'] ?? 1) === 1 ? 'checked' : '' ?>> Akun aktif (boleh masuk aplikasi)
    </label>

    <?php // Akun yang dibuat OTOMATIS dari pendaftaran: diberi tahu bahwa kata sandinya acak sehingga
          // Super Admin perlu menetapkan sandi baru sebelum akun itu dapat dipakai masuk. ?>
    <?php if ($edit && (int) ($edit['ditinjau'] ?? 1) === 0): ?>
      <div class="alert alert-warn" id="infoTinjauPengawas">
        <?= ikon('alert', 16) ?>
        <span>Akun ini <b>dibuat otomatis dari pendaftaran sekolah/madrasah</b> dan masih
          <b>menunggu ditinjau</b>. Kata sandinya <b>acak</b> (tidak diketahui siapa pun) — isi
          <b>Kata Sandi Baru</b> di atas bila pengawas harus dapat masuk, lalu centang
          <b>Akun aktif</b> dan simpan.</span>
      </div>
    <?php endif; ?>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= ikon($edit ? 'save' : 'plus', 16) ?> <?= $edit ? 'Simpan Perubahan' : 'Tambah Akun Pengawas' ?></button>
      <?php if ($edit): ?><a class="btn btn-ghost" href="pengawas.php">Batal</a><?php endif; ?>
    </div>
  </form>
</div>

<?php // Permintaan pemilik: "Daftar Akun pengawas dirubah menjadi tabel rapi yang isinya:
      // Nama Pengawas, Madrasah Binaan (Aktif), Aksi (Ubah & Binaan, Non aktifkan, Hapus)".
      // Seluruh baris ditampilkan sebagai satu tabel (bukan kartu per akun) supaya mudah dipindai;
      // nama binaan tetap berupa chip dan hanya binaan AKTIF yang ditampilkan (yang nonaktif
      // dihitung pada keterangan) karena sekolah nonaktif tidak dapat dibuka pengawas. ?>
<?php // Data LAMA (dibuat sebelum aturan 1-pengawas berlaku) yang masih dibina lebih dari satu
      // pengawas: dilaporkan agar Super Admin dapat merapikannya — sistem TIDAK menghapusnya sendiri. ?>
<?php if ($binaanGanda): ?>
  <div class="alert alert-warn">
    <?= ikon('alert', 16) ?>
    <span><b><?= count($binaanGanda) ?> sekolah/madrasah</b> masih dibina oleh lebih dari satu pengawas
      (data lama). Sesuai aturan <b>1 sekolah/madrasah hanya untuk 1 pengawas</b>, mohon rapikan dengan
      mengosongkan centangnya pada salah satu akun:
      <?= e(implode('; ', array_map(
          static fn(array $g): string => (string) $g['sekolah'] . ' — ' . (string) $g['pengawas'],
          $binaanGanda))) ?>.</span>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <div><h2>Daftar Akun Pengawas</h2>
      <p><?= count($rows) ?> akun terdaftar &middot; <?= (int) $jmlAktif ?> aktif.
        Klik <b>Ubah &amp; Binaan</b> untuk mengubah akun atau menetapkan sekolah/madrasah binaannya.</p>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="empty">
      <?= ikon('users', 34) ?>
      <h3>Belum ada akun pengawas</h3>
      <p>Tambahkan akun pengawas pada formulir di atas, lalu centang sekolah/madrasah binaannya.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblPengawas">
        <thead>
          <tr>
            <th class="kiri">Nama Pengawas</th>
            <th class="kiri">Madrasah Binaan (Aktif)</th>
            <th class="ctr">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
              $sid = (int) $r['id'];
              $binaan = sekolah_binaan($sid, true);          // binaan AKTIF (yang dapat dibuka pengawas)
              $binaanSemua = sekolah_binaan($sid, false);
              $jumlahNonaktif = count($binaanSemua) - count($binaan);
              $sedangDiubah = $sid === (int) ($edit['id'] ?? 0);
              ?>
            <tr>
              <td class="kiri">
                <b><?= e((string) $r['nama']) ?></b>
                <span class="pill <?= (int) $r['aktif'] === 1 ? 'pill-ok' : 'pill-gray' ?>"><?= (int) $r['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
                <span class="pill pill-B">Pengawas</span>
                <?php // Akun otomatis dari pendaftaran sekolah: masih menunggu ditinjau Super Admin. ?>
                <?php if ((int) ($r['ditinjau'] ?? 1) === 0): ?>
                  <span class="pill pill-gold" id="pilTinjau<?= $sid ?>">Menunggu ditinjau</span>
                <?php endif; ?>
                <?php if ($sedangDiubah): ?><span class="pill pill-gold">sedang diubah</span><?php endif; ?>
                <span class="tbl-sub">Nama pengguna <b><?= e((string) $r['username']) ?></b>
                  &middot; terakhir masuk: <?= e(!empty($r['login_terakhir']) ? tanggal_id((string) $r['login_terakhir'], true) : 'belum pernah') ?></span>
              </td>
              <td class="kiri">
                <?php if (!$binaan): ?>
                  <span class="mut sm">Belum ada sekolah/madrasah binaan — pengawas ini belum dapat melihat data apa pun.</span>
                <?php else: ?>
                  <div class="chip-list">
                    <?php foreach ($binaan as $sk): ?>
                      <span class="chip"><?= e((string) $sk['nama']) ?> &middot; <?= e($sk['jenis'] === 'madrasah' ? 'Madrasah' : 'Umum') ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <span class="tbl-sub"><?= count($binaan) ?> binaan aktif<?= $jumlahNonaktif > 0 ? ' &middot; ' . (int) $jumlahNonaktif . ' nonaktif (tidak dapat dibuka pengawas)' : '' ?></span>
              </td>
              <td class="ctr">
                <div class="row-actions" style="justify-content:center">
                  <a class="btn btn-ghost btn-sm" href="pengawas.php?edit=<?= $sid ?>#formPengawas"><?= ikon('edit', 14) ?> Ubah &amp; Binaan</a>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="toggle">
                    <input type="hidden" name="id" value="<?= $sid ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('lock', 14) ?> <?= (int) $r['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                  </form>
                  <form method="post" data-confirm="Hapus akun pengawas <?= e((string) $r['nama']) ?> beserta daftar binaannya?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= $sid ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td class="kiri"><b>JUMLAH</b> <span class="tbl-sub"><?= count($rows) ?> akun pengawas terdaftar</span></td>
            <td class="kiri"><span class="tbl-sub"><?= (int) array_sum(array_map(
                static fn(array $r): int => count(sekolah_binaan((int) $r['id'], true)), $rows)) ?> penugasan binaan aktif</span></td>
            <td class="ctr"><span class="tbl-sub"><?= (int) $jmlAktif ?> akun aktif</span></td>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card card-tight">
  <div class="flex between">
    <div>
      <h3>Catatan</h3>
      <p class="sm mut" style="margin:4px 0 0">
        Akun pengawas dapat <b>masuk aplikasi</b>, memilih satu sekolah/madrasah binaan, lalu melihat
        rekapan &amp; data binaan tersebut (data guru, siswa, daftar hadir, daftar nilai, jurnal, dan
        catatan pelanggaran) serta <b>mencetak</b> lembar-lembarnya. Satu sekolah/madrasah hanya boleh
        dibina oleh <b>satu pengawas</b>. Nama <b>pengawas bina</b> yang diisi pada halaman
        <b>pendaftaran sekolah/madrasah</b> otomatis masuk ke daftar ini sebagai akun
        <b>NONAKTIF</b> bertanda <b>"Menunggu ditinjau"</b> — periksa dulu sebelum mengaktifkannya.
        <b>Semua pengubahan data ditolak</b>
        (tombol pengubahan disembunyikan &amp; permintaan POST dijawab 403), begitu pula menu pengaturan,
        pengguna, aktivasi, dan <b>unduhan (ekspor CSV)</b>. Sekolah/madrasah yang dinonaktifkan otomatis
        hilang dari daftar binaan.
      </p>
    </div>
    <span class="chip">Hanya Super Admin</span>
  </div>
</div>
<script>
(function () {
  var area = document.querySelector('.pengawas-pilih');
  if (!area) return;
  var kotak = area.querySelectorAll('input[type=checkbox]');
  var hitung = document.getElementById('hitungBinaan');
  function perbarui() {
    var n = 0, kunci = 0;
    kotak.forEach(function (k) { if (k.checked) n++; if (k.disabled) kunci++; });
    if (hitung) {
      hitung.textContent = n + ' sekolah/madrasah dipilih'
        + (kunci > 0 ? ' \u00b7 ' + kunci + ' terkunci (sudah dibina pengawas lain)' : '');
    }
  }
  kotak.forEach(function (k) { k.addEventListener('change', perbarui); });
  var semua = document.getElementById('pilihSemua');
  var kosong = document.getElementById('pilihKosong');
  // Kotak yang terkunci (sudah dibina pengawas lain) TIDAK ikut tercentang oleh "Pilih semua".
  if (semua) semua.addEventListener('click', function () {
    kotak.forEach(function (k) { if (!k.disabled) k.checked = true; });
    perbarui();
  });
  if (kosong) kosong.addEventListener('click', function () { kotak.forEach(function (k) { k.checked = false; }); perbarui(); });
  perbarui();
})();
</script>
<?php
page_bottom();
