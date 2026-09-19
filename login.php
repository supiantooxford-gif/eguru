<?php
/**
 * Halaman masuk e-GURU — berbentuk PORTAL: penjelasan singkat aplikasi, gambaran umum
 * (peran pengguna, menu utama, alur kerja, keamanan) di sisi kiri, dan formulir masuk di sisi kanan.
 *
 * Login baru dapat dipakai bila sekolah/madrasahnya sudah DISETUJUI dan masih AKTIF;
 * akun guru harus tertaut ke data guru.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

if (current_user()) {
    redirect('index.php');
}

$err = '';
$user = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');

    if ($user === '' || $pass === '') {
        $err = 'Nama pengguna dan kata sandi wajib diisi.';
        log_gagal(null, $user, 'nama pengguna atau kata sandi kosong');
    } else {
        $u = one('SELECT * FROM users WHERE username = ? COLLATE NOCASE', [$user]);
        $sekolahUser = ($u && $u['role'] !== 'super' && (int) ($u['sekolah_id'] ?? 0) > 0)
            ? one('SELECT * FROM sekolah WHERE id = ?', [(int) $u['sekolah_id']])
            : null;

        if (!$u || !password_verify($pass, (string) $u['password_hash'])) {
            $err = 'Nama pengguna atau kata sandi salah.';
            log_gagal($u, $user, $u ? 'kata sandi salah' : 'nama pengguna tidak dikenal');
        } elseif ((int) $u['aktif'] !== 1) {
            $err = 'Akun ini sedang dinonaktifkan. Hubungi Super Admin.';
            log_gagal($u, $user, 'akun dinonaktifkan');
        } elseif ($u['role'] !== 'super' && !$sekolahUser) {
            $err = 'Akun ini belum ditautkan ke sekolah/madrasah mana pun. Hubungi Super Admin.';
            log_gagal($u, $user, 'akun belum ditautkan ke sekolah');
        } elseif ($sekolahUser && ($sekolahUser['verifikasi'] ?? 'disetujui') === 'menunggu') {
            $err = 'Pendaftaran ' . $sekolahUser['nama'] . ' masih MENUNGGU PERSETUJUAN Super Admin. '
                 . 'Anda dapat masuk setelah pendaftaran disetujui.';
            log_gagal($u, $user, 'pendaftaran sekolah masih menunggu persetujuan');
        } elseif ($sekolahUser && ($sekolahUser['verifikasi'] ?? 'disetujui') === 'ditolak') {
            $err = 'Pendaftaran ' . $sekolahUser['nama'] . ' belum disetujui'
                 . (!empty($sekolahUser['alasan_tolak']) ? ': ' . $sekolahUser['alasan_tolak'] : '.')
                 . ' Silakan hubungi Super Admin.';
            log_gagal($u, $user, 'pendaftaran sekolah ditolak');
        } elseif ($sekolahUser && (int) $sekolahUser['aktif'] !== 1) {
            $err = 'Sekolah/madrasah ' . $sekolahUser['nama'] . ' sedang dinonaktifkan. Hubungi Super Admin.';
            log_gagal($u, $user, 'sekolah dinonaktifkan');
        } elseif ($u['role'] === 'guru' && (int) ($u['guru_id'] ?? 0) <= 0) {
            $err = 'Akun guru ini belum ditautkan ke data guru. Hubungi Admin Operator atau Super Admin.';
            log_gagal($u, $user, 'akun guru belum ditautkan ke data guru');
        } else {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $u['id'];
            log_sesi_mulai($u);   // daftarkan sebagai online + catat aktivitas "masuk"
            // Konteks sekolah dimulai bersih: super admin memilih sekolah sendiri lewat Kelola Sekolah.
            unset($_SESSION['ctx_sekolah_id']);
            q('UPDATE users SET login_terakhir = ? WHERE id = ?', [date('Y-m-d H:i:s'), (int) $u['id']]);
            $dest = $_SESSION['redirect_after'] ?? '';
            unset($_SESSION['redirect_after']);
            if (is_string($dest) && $dest !== '' && strpos($dest, '://') === false) {
                header('Location: ' . $dest);
                exit;
            }
            redirect('index.php');
        }
    }
}

// Portal hanya menampilkan penjelasan aplikasi. Angka statistik sekolah/guru/siswa/nilai/jurnal
// TIDAK ditampilkan (permintaan pemilik) dan keterangan akun bawaan juga tidak ditampilkan.
$jmlPendaftar = jumlah_pendaftaran_menunggu();

$menuPortal = [
    ['dashboard', 'Dashboard', 'Ringkasan jumlah guru, siswa, kelas, serta grafik nilai dan kehadiran.'],
    ['users', 'Data Guru', 'Nama guru, kelas yang diajar, dan mata pelajaran yang dipegang — bisa impor Excel/CSV.'],
    ['student', 'Data Siswa', 'Data siswa per kelas beserta wali dan kontaknya, bisa impor Excel/CSV.'],
    ['clipboard', 'Daftar Hadir Siswa', 'Catatan kehadiran per pertemuan (H/S/I/A) dan rekap persentase kehadiran.'],
    ['chart', 'Daftar Nilai Siswa', 'Nilai Formatif, Sumatif Lingkup Materi, dan ASAS dengan nilai akhir otomatis.'],
    ['book', 'Jurnal Harian Guru', 'Jurnal mengajar: materi, jam ke-, kehadiran S/I/A/B/T, dan keterangan.'],
    ['alert', 'Catatan Pelanggaran Siswa', 'Pencatatan pelanggaran siswa beserta tindakan dan status penanganannya.'],
    ['slider', 'Pengaturan', 'Identitas sekolah/madrasah, tahun ajaran & semester, kelas, dan aturan penilaian.'],
];

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk &middot; <?= e(APP_NAME) ?> — <?= e(APP_SUB) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>&#128218;</text></svg>">
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="portal-wrap">
  <div class="portal-inner">

    <!-- ============================ KEPALA PORTAL ============================ -->
    <header class="portal-head">
      <div class="portal-brand">
        <div class="portal-logo"><?= ikon('mosque', 30) ?></div>
        <div>
          <h1><?= e(APP_NAME) ?> <span class="portal-tagline"><?= e(APP_SUB) ?></span></h1>
          <p>Satu aplikasi untuk administrasi guru — melayani banyak sekolah &amp; madrasah</p>
        </div>
      </div>
      <div class="portal-head-kanan">
        <?php if ($jmlPendaftar > 0): ?>
          <span class="pill pill-warn"><?= (int) $jmlPendaftar ?> pendaftaran menunggu</span>
        <?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="daftar.php"><?= ikon('school', 15) ?> Daftarkan Sekolah/Madrasah</a>
      </div>
    </header>

    <div class="portal-grid">

      <!-- ============================ KOLOM KIRI: PENJELASAN ============================ -->
      <div class="portal-info">

        <section class="card portal-hero">
          <div class="section-title mb8"><?= ikon('star', 14) ?> Tentang Aplikasi</div>
          <p class="portal-lead">
            <b><?= e(APP_NAME) ?></b> adalah aplikasi <b>administrasi guru digital</b> untuk sekolah dan madrasah.
            Semua pekerjaan administrasi pembelajaran dikelola dalam satu tempat: data guru dan siswa, daftar hadir,
            daftar nilai Kurikulum Merdeka, jurnal harian mengajar, hingga catatan pelanggaran siswa — lengkap dengan
            pengaturan identitas sekolah, tahun ajaran, semester, dan kelas.
          </p>
          <p class="portal-lead mut">
            Aplikasi ini <b>multi-sekolah</b>: satu pemasangan dapat dipakai banyak sekolah/madrasah sekaligus,
            dengan data setiap sekolah yang terpisah dan akun berjenjang sesuai tugas masing-masing.
          </p>
        </section>

        <section class="card">
          <div class="section-title mb8"><?= ikon('chart', 14) ?> Gambaran Umum Menu</div>
          <div class="portal-menu">
            <?php foreach ($menuPortal as [$iko, $nama, $ket]): ?>
              <div class="portal-menu-item">
                <span class="portal-menu-ico"><?= ikon((string) $iko, 16) ?></span>
                <div><b><?= e((string) $nama) ?></b><span><?= e((string) $ket) ?></span></div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="card">
          <div class="section-title mb8"><?= ikon('check', 14) ?> Cara Mulai &amp; Keamanan</div>
          <ol class="portal-alur">
            <li><b>Daftarkan sekolah/madrasah</b> melalui halaman pendaftaran (akun pengelola dibuat saat mendaftar).</li>
            <li><b>Super Admin menyetujui</b> pendaftaran; setelah itu mata pelajaran awal, tahun ajaran, dan semester disiapkan otomatis.</li>
            <li><b>Lengkapi data</b> kelas, guru, dan siswa — lalu mulai mengisi daftar hadir, nilai, dan jurnal.</li>
          </ol>
          <ul class="portal-cek">
            <li>Data setiap sekolah/madrasah terpisah — akun hanya melihat data sekolahnya sendiri.</li>
            <li>Dokumen dapat dicetak (daftar nilai, rekap kehadiran, jurnal) dengan kop dan tanda tangan kepala.</li>
            <li>Kata sandi tersimpan teracak; setiap formulir dilindungi token keamanan (CSRF).</li>
          </ul>
        </section>

      </div>

      <!-- ============================ KOLOM KANAN: FORM MASUK ============================ -->
      <div class="portal-side">
        <div class="card portal-login">
          <div class="card-head">
            <div>
              <h2><?= ikon('lock', 16) ?> Masuk Aplikasi</h2>
              <p>Gunakan akun yang diberikan pengelola sekolah/madrasah Anda.</p>
            </div>
          </div>

          <?php if ($err !== ''): ?>
            <div class="alert alert-err"><?= ikon('alert', 16) ?><span><?= e($err) ?></span></div>
          <?php endif; ?>

          <form method="post" class="form-grid" autocomplete="off">
            <?= csrf_field() ?>
            <div>
              <label class="f" for="username">Nama Pengguna</label>
              <input type="text" id="username" name="username" value="<?= e($user) ?>" required autofocus
                     placeholder="nama pengguna akun Anda">
            </div>
            <div>
              <label class="f" for="password">Kata Sandi</label>
              <input type="password" id="password" name="password" required>
            </div>
            <button class="btn btn-primary full" type="submit"><?= ikon('lock', 16) ?> Masuk</button>
          </form>

          <div class="divider"></div>
          <p class="sm mut" style="margin:0 0 10px">
            Belum punya akun? Sekolah/madrasah Anda dapat mendaftar sendiri, lalu aplikasi dipakai setelah
            pendaftaran disetujui Admin.
          </p>
          <a class="btn btn-ghost full" href="daftar.php"><?= ikon('school', 16) ?> Daftarkan Sekolah/Madrasah</a>

          <div class="divider"></div>
          <div class="portal-bantuan">
            <b>Butuh bantuan?</b>
            <span>Lupa kata sandi atau akun belum bisa masuk — hubungi Admin
              <?= e(APP_NAME) ?> atau pengelola sekolah/madrasah Anda.</span>
          </div>
        </div>

      </div>

    </div>

    <footer class="portal-foot">
      <span><?= e(APP_NAME) ?> — <?= e(APP_SUB) ?> v<?= e(APP_VERSION) ?></span>
      <span class="portal-pengembang">Dikembangkan oleh <?= e(APP_AUTHOR) ?> (<?= e(APP_AUTHOR_JABATAN) ?>)</span>
    </footer>

  </div>
</div>
</body>
</html>
