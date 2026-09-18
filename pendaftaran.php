<?php
/**
 * Pendaftaran Sekolah/Madrasah (khusus Super Admin): tinjau, setujui, atau tolak
 * pendaftaran mandiri dari halaman publik daftar.php.
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

    if (!$s) {
        flash('Pendaftaran tidak ditemukan.', 'err');
        redirect('pendaftaran.php');
    }

    if ($aksi === 'setujui') {
        db()->exec('BEGIN IMMEDIATE');
        try {
            q('UPDATE sekolah SET verifikasi = "disetujui", aktif = 1, alasan_tolak = NULL, ditinjau = ? WHERE id = ?',
                [date('Y-m-d H:i:s'), $id]);
            provision_sekolah(db(), $id); // mata pelajaran, tahun ajaran, dan semester bila belum ada
            q('UPDATE users SET aktif = 1 WHERE sekolah_id = ? AND role <> "super"', [$id]);
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        $st = sekolah_stat($id);
        flash('Pendaftaran ' . nama_sekolah_tampil($s) . ' DISETUJUI. Sekolah/madrasah dapat dipakai sekarang ('
            . $st['mapel'] . ' mata pelajaran awal, tahun ajaran & semester sudah disiapkan).');
        redirect('pendaftaran.php');
    }

    if ($aksi === 'tolak') {
        $alasan = trim((string) ($_POST['alasan_tolak'] ?? ''));
        if ($alasan === '') {
            flash('Isi alasan penolakan agar pendaftar tahu sebabnya.', 'err');
            redirect('pendaftaran.php');
        }
        // Akun sengaja TIDAK dinonaktifkan supaya pendaftar bisa melihat alasannya saat mencoba masuk.
        q('UPDATE sekolah SET verifikasi = "ditolak", aktif = 0, alasan_tolak = ?, ditinjau = ? WHERE id = ?',
            [$alasan, date('Y-m-d H:i:s'), $id]);
        flash('Pendaftaran ' . nama_sekolah_tampil($s) . ' DITOLAK dengan alasan: ' . $alasan, 'warn');
        redirect('pendaftaran.php');
    }

    if ($aksi === 'kembalikan') {
        q('UPDATE sekolah SET verifikasi = "menunggu", alasan_tolak = NULL, ditinjau = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $id]);
        flash('Pendaftaran ' . nama_sekolah_tampil($s) . ' dikembalikan ke status menunggu persetujuan.', 'warn');
        redirect('pendaftaran.php');
    }

    if ($aksi === 'hapus') {
        $jmlAkun = (int) val('SELECT COUNT(*) FROM users WHERE sekolah_id = ?', [$id], 0);
        db()->exec('BEGIN IMMEDIATE');
        try {
            q('DELETE FROM users WHERE sekolah_id = ?', [$id]);
            q('DELETE FROM sekolah WHERE id = ?', [$id]);
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        if (sekolah_id() === $id) {
            set_sekolah_context(null);
        }
        flash('Pendaftaran ' . nama_sekolah_tampil($s) . ' beserta ' . $jmlAkun . ' akunnya dihapus.', 'warn');
        redirect('pendaftaran.php');
    }

    redirect('pendaftaran.php');
}

$fStatus = in_array((string) ($_GET['status'] ?? ''), ['menunggu', 'disetujui', 'ditolak'], true) ? (string) $_GET['status'] : '';
$w = $fStatus !== '' ? ' WHERE verifikasi = ?' : '';
$p = $fStatus !== '' ? [$fStatus] : [];
$rows = all('SELECT * FROM sekolah' . $w . ' ORDER BY (verifikasi = "menunggu") DESC, COALESCE(didaftar, dibuat) DESC', $p);

$jumlah = [
    'menunggu'  => (int) val('SELECT COUNT(*) FROM sekolah WHERE verifikasi = "menunggu"', [], 0),
    'disetujui' => (int) val('SELECT COUNT(*) FROM sekolah WHERE verifikasi = "disetujui"', [], 0),
    'ditolak'   => (int) val('SELECT COUNT(*) FROM sekolah WHERE verifikasi = "ditolak"', [], 0),
];

page_top('Pendaftaran Sekolah/Madrasah', 'Super Admin &middot; ' . $jumlah['menunggu'] . ' pendaftaran menunggu keputusan');
?>
<div class="grid g3">
  <div class="card stat card-tight">
    <div class="stat-ic o"><?= ikon('clock', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $jumlah['menunggu'] ?></div><div class="stat-lbl">Menunggu persetujuan</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic g"><?= ikon('check', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $jumlah['disetujui'] ?></div><div class="stat-lbl">Disetujui</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic p"><?= ikon('alert', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $jumlah['ditolak'] ?></div><div class="stat-lbl">Ditolak</div></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div>
      <h2>Pendaftaran Masuk</h2>
      <p>Sekolah/madrasah yang mendaftar sendiri lewat halaman <b>daftar.php</b>. Login mereka masih ditolak
        sampai pendaftaran disetujui.</p>
    </div>
    <div class="flex">
      <a class="btn btn-ghost btn-sm" href="sekolah.php"><?= ikon('building', 15) ?> Kelola Sekolah</a>
      <a class="btn btn-ghost btn-sm" href="daftar.php" target="_blank"><?= ikon('eye', 15) ?> Lihat halaman pendaftaran</a>
    </div>
  </div>

  <form class="filterbar mb16" method="get">
    <div>
      <label class="f" for="status">Status</label>
      <select id="status" name="status" data-auto>
        <option value="">Semua status</option>
        <option value="menunggu" <?= $fStatus === 'menunggu' ? 'selected' : '' ?>>Menunggu persetujuan</option>
        <option value="disetujui" <?= $fStatus === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
        <option value="ditolak" <?= $fStatus === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
      </select>
    </div>
    <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
    <?php if ($fStatus !== ''): ?><a class="btn btn-ghost" href="pendaftaran.php">Reset</a><?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="empty">
      <?= ikon('star', 34) ?>
      <h3>Belum ada pendaftaran</h3>
      <p>Sekolah/madrasah dapat mendaftar sendiri melalui halaman <a href="daftar.php">Pendaftaran Sekolah/Madrasah</a>.</p>
    </div>
  <?php else: ?>
    <div class="grid" style="gap:20px">
      <?php foreach ($rows as $r): $sid = (int) $r['id']; $ver = (string) $r['verifikasi']; ?>
        <div class="card <?= $ver === 'menunggu' ? 'card-terpilih' : '' ?>">
          <div class="card-head">
            <div>
              <h2><?= tetap_kata(nama_sekolah_tampil($r)) ?>
                <span class="pill <?= $ver === 'disetujui' ? 'pill-ok' : ($ver === 'menunggu' ? 'pill-warn' : 'pill-D') ?>"><?= e(verifikasi_label($ver)) ?></span>
                <span class="pill pill-gold"><?= $r['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah Umum' ?></span>
                <?php if ((int) $r['aktif'] !== 1): ?><span class="pill pill-gray">nonaktif</span><?php endif; ?>
              </h2>
              <p>Didaftarkan <?= e(tanggal_id((string) ($r['didaftar'] ?: $r['dibuat']), true)) ?>
                &middot; NPSN <?= e((string) ($r['npsn'] ?: '-')) ?></p>
            </div>
            <div class="row-actions">
              <?php if ($ver === 'menunggu'): ?>
                <form method="post" data-confirm="Setujui pendaftaran <?= e((string) $r['nama']) ?>? Sekolah/madrasah langsung dapat dipakai.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="setujui">
                  <input type="hidden" name="id" value="<?= $sid ?>">
                  <button class="btn btn-primary btn-sm" type="submit"><?= ikon('check', 14) ?> Setujui</button>
                </form>
              <?php elseif ($ver === 'ditolak'): ?>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="kembalikan">
                  <input type="hidden" name="id" value="<?= $sid ?>">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('switch', 14) ?> Tinjau ulang</button>
                </form>
              <?php endif; ?>
              <?php if ($ver === 'disetujui'): ?>
                <a class="btn btn-ghost btn-sm" href="sekolah.php?edit=<?= $sid ?>"><?= ikon('edit', 14) ?> Kelola</a>
              <?php endif; ?>
              <form method="post" data-confirm="Hapus data pendaftaran <?= e((string) $r['nama']) ?> beserta akunnya?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= $sid ?>">
                <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Hapus</button>
              </form>
            </div>
          </div>

          <div class="grid g2" style="gap:16px">
            <div>
              <div class="sheet-meta" style="margin:0">
                <div><b>Alamat</b>: <?= e((string) ($r['alamat'] ?: '-')) ?></div>
                <div><b>Telepon</b>: <?= e((string) ($r['telepon'] ?: '-')) ?></div>
                <div><b>Email</b>: <?= e((string) ($r['email'] ?: '-')) ?></div>
                <div><b>Kepala</b>: <?= e((string) ($r['kepala_sekolah'] ?: '-')) ?></div>
                <div><b>NIP Kepala</b>: <?= e((string) ($r['nip_kepala_sekolah'] ?: '-')) ?></div>
                <div><b>Kontak pendaftar</b>: <?= e((string) ($r['kontak_nama'] ?: '-')) ?>
                  <?= $r['kontak_telepon'] ? '&middot; ' . e((string) $r['kontak_telepon']) : '' ?></div>
              </div>
            </div>
            <div>
              <?php if ($ver === 'ditolak' && !empty($r['alasan_tolak'])): ?>
                <div class="alert alert-err" style="margin:0">
                  <?= ikon('alert', 16) ?><span><b>Alasan penolakan:</b> <?= e((string) $r['alasan_tolak']) ?></span>
                </div>
              <?php elseif ($ver === 'menunggu'): ?>
                <form method="post" class="form-grid">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="tolak">
                  <input type="hidden" name="id" value="<?= $sid ?>">
                  <div>
                    <label class="f" for="alasan<?= $sid ?>">Alasan penolakan (wajib bila ditolak)</label>
                    <input type="text" id="alasan<?= $sid ?>" name="alasan_tolak" placeholder="mis. data sekolah belum lengkap">
                  </div>
                  <button class="btn btn-danger" type="submit"><?= ikon('alert', 15) ?> Tolak pendaftaran</button>
                </form>
              <?php else: ?>
                <div class="stat-mini">
                  <?php $st = sekolah_stat($sid); ?>
                  <div><b><?= $st['kelas'] ?></b><span>Kelas</span></div>
                  <div><b><?= $st['guru'] ?></b><span>Guru</span></div>
                  <div><b><?= $st['siswa'] ?></b><span>Siswa</span></div>
                  <div><b><?= $st['akun'] ?></b><span>Akun</span></div>
                </div>
                <div class="chip-list mt8">
                  <?php foreach (all('SELECT nama, username, role FROM users WHERE sekolah_id = ?', [$sid]) as $a): ?>
                    <span class="chip"><?= e((string) $a['nama']) ?> &middot; <?= e((string) $a['username']) ?> &middot; <?= e(role_label((string) $a['role'])) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php
page_bottom();
