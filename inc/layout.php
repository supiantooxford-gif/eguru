<?php
/**
 * Layout aplikasi e-GURU: sidebar (sadar peran & konteks sekolah), header, footer.
 * Seluruh keluaran ditampung lalu melewati penyesuaian istilah Sekolah/Madrasah.
 */
declare(strict_types=1);

function ikon(string $name, int $size = 18): string
{
    $p = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/>',
        'users'     => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.3 2.9-5.5 6.5-5.5s6.5 2.2 6.5 5.5"/><path d="M17 11a3 3 0 1 0 0-6"/><path d="M18 19.6c0-2.2-.8-3.8-2.2-4.8 3 .2 5.2 2.2 5.2 4.8"/>',
        'student'   => '<circle cx="12" cy="7.5" r="3.6"/><path d="M4.5 20.5c0-4 3.4-6.6 7.5-6.6s7.5 2.6 7.5 6.6"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2.8h6V4"/><path d="M9 11h6M9 15h4"/>',
        'chart'     => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'report'    => '<path d="M4 4h11l5 5v11H4z"/><path d="M8 14h8M8 17h5M8 11h3"/>',
        'book'      => '<path d="M4 4.5A2 2 0 0 1 6 2.5h13v19H6a2 2 0 0 0-2 2z"/><path d="M4 17.5h15"/>',
        'alert'     => '<path d="M12 3.5l9 16H3z"/><path d="M12 9v5M12 17.2v.1"/>',
        'folder'    => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'doc'       => '<path d="M14 3v5h5"/><path d="M19 8.5V19a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7z"/><path d="M9 13h6M9 17h4"/>',
        'eye'       => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="2.5"/>',
        'upload'    => '<path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
        'key'       => '<circle cx="8" cy="15" r="4"/><path d="M11 12l8-8M17 6l2 2M15 8l2 2"/>',
        'slider'    => '<path d="M4 7h16M4 12h16M4 17h16"/><circle cx="9" cy="7" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="8" cy="17" r="2"/>',
        'print'     => '<path d="M7 9V4h10v5"/><rect x="3" y="9" width="18" height="7" rx="2"/><path d="M7 16h10v4H7z"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'edit'      => '<path d="M4 20h4l10-10-4-4L4 16z"/><path d="M14 5l4 4"/>',
        'trash'     => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'logout'    => '<path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="M15 8l4 4-4 4M19 12H9"/>',
        'search'    => '<circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/>',
        'check'     => '<path d="M4 12.5l5 5L20 6.5"/>',
        'star'      => '<path d="M12 3l2.9 5.9 6.1.9-4.5 4.4 1.1 6.2L12 17.6 6.4 20.4l1.1-6.2L3 9.8l6.1-.9z"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5S20 17 20 21"/>',
        'school'    => '<path d="M12 3l9 4.5-9 4.5L3 7.5z"/><path d="M6 10v6c0 1.7 2.7 3 6 3s6-1.3 6-3v-6"/>',
        'building'  => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2"/><path d="M10 21v-3h4v3"/>',
        'switch'    => '<path d="M7 4L3 8l4 4"/><path d="M3 8h13a4 4 0 0 1 0 8H4"/>',
        'filter'    => '<path d="M3 5h18l-7 8v6l-4-2v-4z"/>',
        'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'save'      => '<path d="M5 3h11l3 3v15H5z"/><path d="M9 3v6h6V3"/><path d="M8 21v-6h8v6"/>',
        'lock'      => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'mosque'    => '<path d="M12 3c2.6 2 4 4 4 6H8c0-2 1.4-4 4-6z"/><path d="M5 21V11c0-1.5 1.2-2.6 3-3M19 21V11c0-1.5-1.2-2.6-3-3"/><path d="M3 21h18"/><path d="M10 21v-4a2 2 0 0 1 4 0v4"/>',
    ];
    $d = $p[$name] ?? $p['doc'];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/** Subjudul di bawah judul halaman (nama sekolah + periode aktif). */
function ajaran_subjudul(): string
{
    if (!current_sekolah()) {
        return 'Super Admin &middot; ' . jumlah_sekolah() . ' sekolah/madrasah terdaftar';
    }
    $ta = ta_aktif();
    $sem = semester_aktif();
    return tetap_kata(nama_sekolah_tampil())
        . ' &middot; Tahun Ajaran ' . e(ta_nama($ta))
        . ' &middot; Semester ' . e(sem_nama($sem));
}

function page_top(string $judul, string $sub = ''): void
{
    // Seluruh keluaran ditampung agar istilah "Sekolah" bisa menjadi "Madrasah".
    ob_start();
    $GLOBALS['__vs_buf_level'] = ob_get_level();

    $u = current_user();
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $flashes = flash_take();
    $r = peran();
    $super = is_super();
    $sekolah = current_sekolah();
    $menuSekolah = ['index.php', 'guru.php', 'guru_edit.php', 'guru_import.php', 'cetak_guru.php',
        'siswa.php', 'siswa_edit.php', 'siswa_import.php', 'cetak_siswa.php',
        'absensi.php', 'absensi_form.php', 'cetak_absensi.php', 'nilai.php', 'cetak_nilai.php',
        'jurnal.php', 'cetak_jurnal.php', 'kasus.php', 'cetak_kasus.php', 'kelas.php', 'mapel.php',
        'tahun_ajaran.php', 'kenaikan.php', 'pengaturan.php', 'pengguna.php'];
    $diSekolah = in_array($here, $menuSekolah, true);
    $menunggu = $super ? jumlah_pendaftaran_menunggu() : 0;
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> &middot; <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>&#128218;</text></svg>">
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<button class="nav-toggle" id="navToggle" aria-label="Buka menu"><?= ikon('slider', 20) ?></button>
<div class="scrim" id="scrim"></div>

<aside class="sidebar" id="sidebar">
  <div class="brand">
    <div class="brand-logo"><?= ikon($sekolah && is_madrasah() ? 'mosque' : ($sekolah ? 'school' : 'building'), 24) ?></div>
    <div>
      <div class="brand-name"><?= e(APP_NAME) ?></div>
      <div class="brand-sub"><?= e(APP_SUB) ?></div>
    </div>
  </div>

  <?php if ($sekolah): ?>
    <div class="ctx-box">
      <div class="ctx-nama"><?= tetap_kata(nama_sekolah_tampil()) ?></div>
      <div class="ctx-ket"><?= e($sekolah['npsn'] ? 'NPSN ' . $sekolah['npsn'] : 'NPSN belum diisi') ?>
        &middot; <?= e($sekolah['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah Umum') ?></div>
    </div>
  <?php endif; ?>

  <nav class="nav">
    <?php if ($super): ?>
      <div class="nav-sec"><?= tetap_kata('Lintas Sekolah/Madrasah') ?></div>
      <a class="nav-item <?= $here === 'index.php' && !$sekolah ? 'on' : '' ?>" href="index.php"><?= ikon('chart') ?><span>Ringkasan Semua</span></a>
      <a class="nav-item <?= $here === 'sekolah.php' ? 'on' : '' ?>" href="sekolah.php"><?= ikon('building') ?><span><?= tetap_kata('Kelola Sekolah') ?></span></a>
      <a class="nav-item <?= $here === 'pendaftaran.php' ? 'on' : '' ?>" href="pendaftaran.php"><?= ikon('star') ?><span>Pendaftaran<?= $menunggu > 0 ? ' (' . $menunggu . ')' : '' ?></span>
        <?php if ($menunggu > 0): ?><span class="pill pill-warn" style="margin-left:auto">baru</span><?php endif; ?>
      </a>
      <a class="nav-item <?= $here === 'pengguna.php' && !$sekolah ? 'on' : '' ?>" href="pengguna.php"><?= ikon('key') ?><span>Akun Super Admin</span></a>
      <?php $jmlOnline = log_jumlah_online(); ?>
      <a class="nav-item <?= $here === 'log.php' ? 'on' : '' ?>" href="log.php"><?= ikon('eye') ?><span>Log Aktivitas</span>
        <?php if ($jmlOnline > 0): ?><span class="pill pill-ok" style="margin-left:auto"><?= (int) $jmlOnline ?> online</span><?php endif; ?>
      </a>
      <?php if ($sekolah): ?>
        <div class="nav-sec">Sekolah yang Dikelola</div>
        <a class="nav-item <?= $here === 'index.php' && $sekolah ? 'on' : '' ?>" href="index.php?sekolah=1"><?= ikon('dashboard') ?><span>Dashboard Sekolah Ini</span></a>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($sekolah && in_array($r, ['kepala', 'operator', 'guru'], true)): ?>
      <div class="nav-sec">Utama</div>
      <a class="nav-item <?= $here === 'index.php' ? 'on' : '' ?>" href="index.php"><?= ikon('dashboard') ?><span>Dashboard</span></a>
    <?php endif; ?>

    <?php if ($sekolah && in_array($r, ['kepala', 'operator', 'guru', 'super'], true)): ?>
      <div class="nav-sec">Administrasi</div>
      <?php // Data Guru memuat akun login guru lain → TIDAK ditampilkan untuk akun guru (permintaan pemilik). ?>
      <?php if ($r !== 'guru'): ?>
        <a class="nav-item <?= in_array($here, ['guru.php', 'cetak_guru.php'], true) ? 'on' : '' ?>" href="guru.php"><?= ikon('users') ?><span>Data Guru</span></a>
      <?php endif; ?>
      <a class="nav-item <?= in_array($here, ['siswa.php', 'cetak_siswa.php'], true) ? 'on' : '' ?>" href="siswa.php"><?= ikon('student') ?><span>Data Siswa</span></a>
      <a class="nav-item <?= in_array($here, ['absensi.php', 'absensi_form.php', 'cetak_absensi.php'], true) ? 'on' : '' ?>" href="absensi.php"><?= ikon('clipboard') ?><span>Daftar Hadir Siswa</span></a>
      <a class="nav-item <?= in_array($here, ['nilai.php', 'cetak_nilai.php'], true) ? 'on' : '' ?>" href="nilai.php"><?= ikon('chart') ?><span>Daftar Nilai Siswa</span></a>
      <a class="nav-item <?= in_array($here, ['jurnal.php', 'cetak_jurnal.php'], true) ? 'on' : '' ?>" href="jurnal.php"><?= ikon('book') ?><span>Jurnal Harian Guru</span></a>
      <a class="nav-item <?= $here === 'kasus.php' ? 'on' : '' ?>" href="kasus.php"><?= ikon('alert') ?><span>Catatan Pelanggaran Siswa</span></a>
    <?php endif; ?>

    <?php if ($sekolah && in_array($r, ['kepala', 'operator', 'super'], true)): ?>
      <div class="nav-sec"><?= $r === 'kepala' ? 'Data Master (hanya lihat)' : 'Data Master' ?></div>
      <a class="nav-item <?= $here === 'kelas.php' ? 'on' : '' ?>" href="kelas.php"><?= ikon('school') ?><span>Kelas</span></a>
      <a class="nav-item <?= $here === 'mapel.php' ? 'on' : '' ?>" href="mapel.php"><?= ikon('folder') ?><span>Mata Pelajaran</span></a>
      <?php if (boleh_master()): ?>
        <a class="nav-item <?= $here === 'guru_import.php' ? 'on' : '' ?>" href="guru_import.php"><?= ikon('upload') ?><span>Impor Guru</span></a>
        <a class="nav-item <?= $here === 'siswa_import.php' ? 'on' : '' ?>" href="siswa_import.php"><?= ikon('upload') ?><span>Impor Siswa</span></a>
        <a class="nav-item <?= $here === 'kenaikan.php' ? 'on' : '' ?>" href="kenaikan.php"><?= ikon('switch') ?><span>Kenaikan &amp; Kelulusan</span></a>
      <?php endif; ?>
      <a class="nav-item <?= $here === 'tahun_ajaran.php' ? 'on' : '' ?>" href="tahun_ajaran.php"><?= ikon('calendar') ?><span>Tahun Ajaran &amp; Semester</span></a>
      <?php if (boleh_pengaturan()): ?>
        <a class="nav-item <?= $here === 'pengaturan.php' ? 'on' : '' ?>" href="pengaturan.php"><?= ikon('slider') ?><span>Identitas &amp; Pengaturan</span></a>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($sekolah && $super): ?>
      <a class="nav-item <?= $here === 'pengguna.php' ? 'on' : '' ?>" href="pengguna.php"><?= ikon('key') ?><span>Pengguna &amp; Akun</span></a>
    <?php endif; ?>

    <div class="nav-sec">Akun</div>
    <a class="nav-item <?= $here === 'akun.php' ? 'on' : '' ?>" href="akun.php"><?= ikon('user') ?><span>Akun Saya</span></a>
  </nav>

  <div class="sidebar-foot">
    <div class="who">
      <?= avatar_user($u, 36) ?>
      <div class="who-txt">
        <strong><?= e((string) ($u['nama'] ?? '')) ?></strong>
        <small><?= e(role_label($r)) ?></small>
      </div>
    </div>
    <a class="btn btn-ghost btn-sm full" href="logout.php"><?= ikon('logout', 16) ?> Keluar</a>
  </div>
</aside>

<main class="main">
  <header class="topbar">
    <div>
      <h1><?= e($judul) ?></h1>
      <p class="topbar-sub"><?= $sub !== '' ? $sub : ajaran_subjudul() ?></p>
    </div>
    <div class="topbar-actions">
      <?php if ($super): ?>
        <span class="pill pill-gold"><?= ikon('star', 13) ?> Super Admin</span>
        <?php if ($sekolah): ?>
          <a class="btn btn-ghost btn-sm" href="sekolah.php"><?= ikon('switch', 15) ?> Ganti Sekolah</a>
        <?php endif; ?>
      <?php else: ?>
        <span class="pill pill-gold"><?= e(role_label($r)) ?></span>
      <?php endif; ?>
      <a class="btn btn-ghost btn-sm" href="akun.php"><?= ikon('user', 16) ?> <?= e((string) ($u['nama'] ?? '')) ?></a>
    </div>
  </header>

  <?php if ($super && $sekolah): ?>
    <div class="alert alert-ok" style="margin-bottom:18px">
      <?= ikon('building', 16) ?>
      <span>Anda sedang <b>mengelola <?= tetap_kata(nama_sekolah_tampil()) ?></b>. Seluruh data yang tampil hanya milik <?= tetap_kata('sekolah/madrasah') ?> ini.</span>
      <form method="post" action="sekolah.php" style="margin-left:auto">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="keluar">
        <button class="btn btn-ghost btn-sm" type="submit">Kembali ke Super Admin</button>
      </form>
    </div>
  <?php elseif ($super && !$sekolah && $diSekolah): ?>
    <div class="alert alert-warn" style="margin-bottom:18px">
      <?= ikon('alert', 16) ?>
      <span>Pilih sekolah/madrasah terlebih dahulu pada halaman <a href="sekolah.php">Kelola Sekolah</a>.</span>
    </div>
  <?php endif; ?>

  <?php if (is_guru() && guru_saya() === null): ?>
    <div class="alert alert-warn">
      <?= ikon('alert', 16) ?>
      <span>Akun Anda belum ditautkan ke data guru, jadi daftar kelas &amp; mata pelajaran yang diampu belum muncul.
      Hubungi Admin Operator atau Super Admin untuk menautkannya.</span>
    </div>
  <?php endif; ?>

  <?php if (is_kepala()): ?>
    <div class="alert alert-ok" style="margin-bottom:18px">
      <?= ikon('eye', 16) ?>
      <span>Anda masuk sebagai <b><?= e(role_label()) ?></b> — seluruh data dapat dilihat dan dicetak; pengisian administrasi dilakukan Admin Operator dan Guru.</span>
    </div>
  <?php endif; ?>

  <?php foreach ($flashes as $f): ?>
    <div class="alert <?= $f['tipe'] === 'ok' ? 'alert-ok' : ($f['tipe'] === 'warn' ? 'alert-warn' : 'alert-err') ?>">
      <?= ikon($f['tipe'] === 'ok' ? 'check' : 'alert', 16) ?><span><?= e($f['pesan']) ?></span>
    </div>
  <?php endforeach; ?>

  <div class="content">
<?php
}

function page_bottom(): void
{
    $sekolah = current_sekolah();
    ?>
  </div>
  <footer class="foot">
    <span><?= e(APP_NAME) ?> — <?= e(APP_SUB) ?> v<?= e(APP_VERSION) ?> &middot;
      <?= $sekolah ? tetap_kata(nama_sekolah_tampil()) : 'Super Admin &middot; ' . jumlah_sekolah() . ' sekolah/madrasah' ?></span>
    <span>Dikembangkan oleh <?= e(APP_AUTHOR) ?> (<?= e(APP_AUTHOR_JABATAN) ?>)</span>
  </footer>
</main>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
<?php
    terjemahkan_selesai();
}

/** Tutup penampung keluaran halaman & terapkan penyesuaian istilah Sekolah/Madrasah. */
function terjemahkan_selesai(): void
{
    $level = (int) ($GLOBALS['__vs_buf_level'] ?? 0);
    if ($level > 0 && ob_get_level() === $level) {
        echo terjemahkan_html((string) ob_get_clean());
        $GLOBALS['__vs_buf_level'] = 0;
    }
}
