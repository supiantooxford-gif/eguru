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
        // Permintaan pemilik: saat menyetujui, Super Admin memilih AKTIF PERMANEN atau
        // MASA UJI COBA (30/60/90 hari). Tanpa pilihan (mis. formulir lama) → permanen.
        $mode = (string) ($_POST['mode'] ?? 'permanen') === 'uji_coba' ? 'uji_coba' : 'permanen';
        $hari = (int) ($_POST['hari'] ?? UJI_COBA_HARI_DEFAULT);
        if (!in_array($hari, aktivasi_pilihan_hari(), true)) {
            $hari = UJI_COBA_HARI_DEFAULT;
        }
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
        if ($mode === 'uji_coba') {
            aktivasi_uji_coba($id, $hari);
        } else {
            aktivasi_permanen($id);
        }
        $st = sekolah_stat($id);
        flash('Pendaftaran ' . nama_sekolah_tampil($s) . ' DISETUJUI dengan '
            . ($mode === 'uji_coba'
                ? 'MASA UJI COBA ' . $hari . ' hari (kode aktivasi sudah disiapkan untuk diberikan bila uji coba berakhir)'
                : 'status AKTIF PERMANEN')
            . '. ' . $st['mapel'] . ' mata pelajaran awal, tahun ajaran & semester sudah disiapkan.');
        redirect('pendaftaran.php');
    }

    if ($aksi === 'aktivasi') {
        // Pintasan dari halaman Pendaftaran: langsung ubah status aktivasi tanpa membuka Kelola Sekolah.
        $mode = (string) ($_POST['mode'] ?? 'permanen');
        $hari = (int) ($_POST['hari'] ?? UJI_COBA_HARI_DEFAULT);
        if (!in_array($hari, aktivasi_pilihan_hari(), true)) {
            $hari = UJI_COBA_HARI_DEFAULT;
        }
        if ($mode === 'uji_coba') {
            aktivasi_uji_coba($id, $hari);
            flash('Masa uji coba ' . nama_sekolah_tampil($s) . ' diberikan selama ' . $hari . ' hari.', 'warn');
        } else {
            aktivasi_permanen($id);
            flash('Sekolah/madrasah ' . nama_sekolah_tampil($s) . ' diaktifkan PERMANEN.');
        }
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
      <p>Sekolah/madrasah yang mendaftar sendiri lewat halaman <b>daftar.php</b>.
        Sejak pendaftaran gratis dibuka, pendaftar <b>langsung disetujui otomatis</b> dengan
        <b>masa aktif <?= (int) PENDAFTARAN_HARI_AKTIF ?> hari</b> — Super Admin tinggal memutuskan
        apakah lembaga itu dilanjutkan <b>AKTIF PERMANEN</b> atau dimatikan.</p>
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

  <div class="alert alert-ok" style="background:rgba(201,162,39,.08);border-color:rgba(201,162,39,.5)">
    <?= ikon('star', 16) ?>
    <span><b>Pendaftaran Gratis (Aktif <?= (int) PENDAFTARAN_HARI_AKTIF ?> Hari).</b>
      Pendaftar baru <b>tidak perlu disetujui lagi</b>: sekolahnya langsung aktif
      <?= (int) PENDAFTARAN_HARI_AKTIF ?> hari, lalu otomatis terkunci sampai <b>kode aktivasi lembaga</b>
      dimasukkan. Gunakan pilihan <b>Aktif permanen</b> pada kartu di bawah bila lembaga itu layak
      dilanjutkan tanpa batas waktu.</span>
  </div>

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
                &middot; NPSN <?= e((string) ($r['npsn'] ?: '-')) ?>
                <?php if (identitas_nilai((string) ($r['nsm'] ?? '')) !== ''): ?>
                  &middot; NSM <?= e((string) $r['nsm']) ?>
                <?php endif; ?></p>
            </div>
            <div class="row-actions">
              <?php if ($ver === 'menunggu'): ?>
                <?php // Permintaan pemilik: saat menyetujui, pilih AKTIF PERMANEN atau MASA UJI COBA 30/60/90 hari. ?>
                <form method="post" class="form-setujui" id="setujui<?= $sid ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="setujui">
                  <input type="hidden" name="id" value="<?= $sid ?>">
                  <select name="mode" aria-label="Jenis aktivasi">
                    <option value="permanen">Aktif permanen</option>
                    <option value="uji_coba">Uji coba</option>
                  </select>
                  <select name="hari" aria-label="Lama uji coba">
                    <?php foreach (aktivasi_pilihan_hari() as $h): ?>
                      <option value="<?= (int) $h ?>" <?= (int) $h === UJI_COBA_HARI_DEFAULT ? 'selected' : '' ?>><?= (int) $h ?> hari</option>
                    <?php endforeach; ?>
                  </select>
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
                <?php $ak = aktivasi_status($r); ?>
                <span class="pill <?= $ak['mode'] === 'permanen' ? 'pill-ok' : ($ak['habis'] ? 'pill-D' : 'pill-uji') ?>"><?= e($ak['label']) ?></span>
                <form method="post" class="form-setujui">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="aktivasi">
                  <input type="hidden" name="id" value="<?= $sid ?>">
                  <select name="mode" aria-label="Ubah status aktivasi">
                    <option value="permanen">Aktif permanen</option>
                    <option value="uji_coba" <?= $ak['mode'] === 'uji_coba' ? 'selected' : '' ?>>Uji coba</option>
                  </select>
                  <select name="hari" aria-label="Lama uji coba">
                    <?php foreach (aktivasi_pilihan_hari() as $h): ?>
                      <option value="<?= (int) $h ?>"><?= (int) $h ?> hari</option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('save', 14) ?> Simpan</button>
                </form>
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

          <?php if ($ver === 'disetujui'): ?>
            <?php // KODE AKTIVASI lembaga ini (unik berdasar nama sekolah) — hanya halaman Super Admin. ?>
            <div class="kode-kotak mb16">
              <span class="xs mut">Kode aktivasi lembaga (periode ke-<?= (int) aktivasi_periode($r) ?>,
                hanya bisa dipakai sekali):</span>
              <code id="kode<?= $sid ?>"><?= e(kode_aktivasi_sekolah($r)) ?></code>
              <button class="btn btn-ghost btn-sm" type="button" data-salin="#kode<?= $sid ?>"><?= ikon('doc', 14) ?> Salin kode</button>
            </div>
          <?php endif; ?>

          <div class="grid g2" style="gap:16px">
            <div>
              <div class="sheet-meta" style="margin:0">
                <div><b>Alamat</b>: <?= e((string) ($r['alamat'] ?: '-')) ?></div>
                <?php // v37: wilayah ikut ditampilkan bila pendaftar mengisinya. ?>
                <?php if (($wil = alamat_wilayah_dari($r)) !== ''): ?>
                  <div><b>Desa/Kec./Kab./Provinsi</b>: <?= e($wil) ?></div>
                <?php endif; ?>
                <div><b>Telepon</b>: <?= e((string) ($r['telepon'] ?: '-')) ?></div>
                <div><b>Situs/laman</b>: <?= e((string) ($r['website'] ?: '-')) ?></div>
                <div><b>Email</b>: <?= e((string) ($r['email'] ?: '-')) ?></div>
                <div><b>Kepala</b>: <?= e((string) ($r['kepala_sekolah'] ?: '-')) ?></div>
                <div><b>NIP Kepala</b>: <?= e((string) ($r['nip_kepala_sekolah'] ?: '-')) ?></div>
                <?php // v39: identitas pengawas bina — ditampilkan bila pendaftar mengisinya. ?>
                <?php if (($png = identitas_nilai($r['pengawas_nama'] ?? '')) !== ''): ?>
                  <div><b>Pengawas Bina</b>: <?= e($png) ?>
                    <?= identitas_nilai($r['pengawas_nip'] ?? '') !== '' ? '&middot; NIP ' . e(identitas_nilai($r['pengawas_nip'])) : '' ?></div>
                <?php endif; ?>
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
                <?php
                // Permintaan pemilik: pada menu Pendaftaran cukup REKAPANNYA saja —
                // nama guru / akun pengelola TIDAK ditampilkan di sini (lihat juga sekolah.php
                // yang menyimpan daftar akun di dalam blok "Akun pengelola" yang tertutup).
                ?>
                <div class="xs mut mt8 mb8">Rekap akun pengelola</div>
                <div class="chip-list">
                  <?php // Urutan rekap: Kepala -> Operator -> Guru -> Pengawas ?>
                  <?php foreach (all('SELECT role, COUNT(*) AS jumlah FROM users WHERE sekolah_id = ? GROUP BY role
                                      ORDER BY CASE role WHEN \'kepala\' THEN 1 WHEN \'operator\' THEN 2 WHEN \'guru\' THEN 3 WHEN \'pengawas\' THEN 4 ELSE 5 END, role',
                                     [$sid]) as $a): ?>
                    <span class="chip"><?= (int) $a['jumlah'] ?> <?= e(role_label((string) $a['role'])) ?></span>
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
