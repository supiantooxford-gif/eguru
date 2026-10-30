<?php
/**
 * HALAMAN DEPAN e-GURU — berbentuk LANDING PAGE + PANEL MASUK.
 *
 * Permintaan pemilik: "Buat halaman depan aplikasi menjadi tampilan web landing page yang
 * menampilkan beranda, Slider (Judul, gambar, keterangan), fitur aplikasi, keunggulan,
 * Total Pengguna (Pengawas, Kepala Sekolah, Madrasah, Guru), testimoni, kontak. Panel login tetap
 * dipertahankan. Untuk mengisi halaman landing page di isi lewat akun super admin."
 *
 * Urutan bagian: Bilah Navigasi → Beranda (slider + panel masuk) → Fitur Aplikasi → Keunggulan →
 * Total Pengguna → Testimoni → Tentang Aplikasi → Kontak → Kaki halaman.
 *
 * Seluruh isi (label, judul tiap bagian, slider, fitur, keunggulan, testimoni, kontak) DIISI oleh
 * Super Admin lewat menu **Halaman Depan (Landing)** (`landing.php`) dan tersimpan di
 * `app_pengaturan` (awalan `landing_`) + tabel `landing_*`; nilai bawaannya di `inc/landing.php`.
 * Angka bagian **Total Pengguna DIHITUNG OTOMATIS** dari data aplikasi (selalu terbaru).
 * Blok "Tentang Aplikasi" (dua foto 3x4 + nama + keterangan) tetap memakai menu
 * **Tentang Aplikasi** (`tentang.php`) — satu sumber data, tidak diduplikasi.
 *
 * Halaman ini tetap menjadi tempat MASUK (login) dan menerima PESAN KONTAK dari pengunjung
 * (masuk ke akun Super Admin, tab "Pesan Masuk" pada menu Halaman Depan (Landing)).
 *
 * ISTILAH: halaman ini bersifat PUBLIK, jadi seluruh tulisan yang tampil memakai
 * "sekolah/madrasah" — halaman ini melayani sekolah umum maupun madrasah sekaligus.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

if (current_user()) {
    redirect('index.php');
}

$err = '';
$errSiswa = '';
$user = '';
$nisn = '';
$pesanGalat = [];
$nilaiPesan = ['nama' => '', 'kontak' => '', 'pesan' => ''];

// Tab panel masuk yang sedang dibuka ('' | 'pengguna' | 'siswa'). Dipakai agar halaman dapat
// dirender tanpa JavaScript: tabnya berupa tautan, jadi keadaan tab tersimpan pada alamat halaman.
$tabMasuk = (string) ($_GET['tab'] ?? '');

// Pengaman sederhana formulir kontak (pengunjung tanpa akun).
if (empty($_SESSION['landing_captcha'])) {
    $_SESSION['landing_captcha'] = ['a' => random_int(2, 9), 'b' => random_int(2, 9)];
}
$cap = $_SESSION['landing_captcha'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');

    // ------------------------------------------------------------------ kirim pesan kontak
    if ($aksi === 'pesan') {
        $nilaiPesan['nama']   = landing_potong((string) ($_POST['nama'] ?? ''), LANDING_NAMA_MAKS);
        $nilaiPesan['kontak'] = landing_potong((string) ($_POST['kontak'] ?? ''), 120);
        $nilaiPesan['pesan']  = landing_potong((string) ($_POST['pesan'] ?? ''), LANDING_PESAN_MAKS);
        $jawab = trim((string) ($_POST['captcha'] ?? ''));

        if ((int) ($_SESSION['landing_pesan_jumlah'] ?? 0) >= LANDING_MAKS_SESI) {
            $pesanGalat[] = 'Pengiriman pesan dari sesi ini sudah mencapai batas (' . LANDING_MAKS_SESI . ' kali).';
        }
        if ($nilaiPesan['nama'] === '') { $pesanGalat[] = 'Nama wajib diisi.'; }
        if ($nilaiPesan['kontak'] === '') { $pesanGalat[] = 'Email atau nomor WhatsApp wajib diisi.'; }
        if (strlen($nilaiPesan['pesan']) < 5) { $pesanGalat[] = 'Tuliskan pesan Anda (minimal 5 karakter).'; }
        if ($jawab === '' || (int) $jawab !== ((int) $cap['a'] + (int) $cap['b'])) {
            $pesanGalat[] = 'Jawaban pengaman (captcha) salah.';
        }

        if (!$pesanGalat) {
            try {
                q('INSERT INTO landing_pesan (nama, kontak, pesan, ip, dibaca, dibuat) VALUES (?,?,?,?,0,?)', [
                    $nilaiPesan['nama'], $nilaiPesan['kontak'], $nilaiPesan['pesan'],
                    log_ip(), date('Y-m-d H:i:s'),
                ]);
                $_SESSION['landing_pesan_jumlah'] = (int) ($_SESSION['landing_pesan_jumlah'] ?? 0) + 1;
                unset($_SESSION['landing_captcha']);
                redirect('login.php?terkirim=1#kontak');
            } catch (Throwable $ex) {
                $pesanGalat[] = 'Pesan gagal disimpan. Coba lagi sebentar lagi.';
            }
        }
        if ($pesanGalat) {
            $_SESSION['landing_captcha'] = ['a' => random_int(2, 9), 'b' => random_int(2, 9)];
            $cap = $_SESSION['landing_captcha'];
        }
    } elseif ($aksi === 'masuk_siswa') {
        // -------------------------------------------------------------- masuk sebagai SISWA
        // Permintaan pemilik: "Buat siswa bisa login ke aplikasi (login hanya memasukkan no NISN)."
        // lalu (v1.65.0): "Pada login siswa tambahkan password. Password adalah tanggal lahir siswa
        // itu sendiri yang terdiri dari 8 angka. Misal 1 januari 2007 maka passwordnya 01012007".
        // Jadi sekarang: NISN + kata sandi (tanggal lahir 8 angka). Syaratnya tetap siswa AKTIF,
        // belum lulus, sekolahnya aktif & sudah disetujui, dan NISN-nya terisi.
        $tabMasuk = 'siswa';
        $nisn = nisn_bersih((string) ($_POST['nisn'] ?? ''));
        $sandiSiswa = (string) ($_POST['sandi'] ?? '');

        // Pembatas percobaan masuk siswa (anti penebakan): per NISN + per alamat IP publik —
        // lihat login_blokir_detik_siswa().
        $blokirSiswa = login_blokir_detik_siswa(log_ip(), $nisn);

        if ($nisn === '') {
            $errSiswa = 'NISN wajib diisi.';
            log_gagal(null, 'siswa:' . $nisn, 'NISN kosong');
        } elseif ($sandiSiswa === '') {
            // Isian yang belum lengkap tidak dihitung sebagai percobaan gagal (tidak menambah
            // hitungan pembatas), tetapi tetap diberi pesan yang jelas.
            $errSiswa = 'Kata sandi wajib diisi. Kata sandi adalah tanggal lahir Anda dalam 8 angka '
                . '(contoh: 1 Januari 2007 → 01012007).';
        } elseif ($blokirSiswa > 0) {
            $errSiswa = login_pesan_blokir($blokirSiswa);
            log_gagal(null, 'siswa:' . $nisn, 'diblokir sementara (percobaan masuk siswa berlebihan)');
        } else {
            $kandidat = siswa_login_cari($nisn);
            if (!$kandidat) {
                $errSiswa = 'NISN ' . $nisn . ' tidak terdaftar sebagai siswa aktif. '
                    . 'Periksa kembali NISN Anda atau hubungi Admin Operator sekolah/madrasah Anda.';
                log_gagal(null, 'siswa:' . $nisn, 'NISN siswa tidak ditemukan / tidak aktif');
            } elseif (count($kandidat) > 1) {
                $errSiswa = 'NISN ' . $nisn . ' tercatat pada lebih dari satu sekolah/madrasah sehingga '
                    . 'tidak dapat dipakai untuk masuk. Hubungi Admin Operator sekolah/madrasah Anda.';
                log_gagal(null, 'siswa:' . $nisn, 'NISN siswa tercatat di lebih dari satu sekolah');
            } else {
                $siswa = $kandidat[0];
                // ---- Kata sandi = tanggal lahir siswa (8 angka, DDMMYYYY) -------------------------
                if (siswa_sandi_lahir((string) ($siswa['tanggal_lahir'] ?? '')) === '') {
                    $errSiswa = 'Tanggal lahir Anda belum diisi pada data siswa sehingga kata sandi '
                        . 'belum dapat dipakai. Hubungi Admin Operator sekolah/madrasah Anda.';
                    log_gagal(null, 'siswa:' . $nisn, 'tanggal lahir siswa belum diisi');
                } elseif (!siswa_sandi_cocok($siswa, $sandiSiswa)) {
                    $errSiswa = 'NISN atau kata sandi salah. Kata sandi adalah tanggal lahir Anda '
                        . 'dalam 8 angka (DDMMYYYY) — contoh: lahir 1 Januari 2007 → 01012007.';
                    log_gagal(null, 'siswa:' . $nisn, 'kata sandi siswa (tanggal lahir) salah');
                } else {
                    // Kata sandi BENAR → baru akun siswa disiapkan/ditautkan (akun tidak pernah
                    // dibuat untuk percobaan yang gagal).
                    $uid = siswa_akun_tautan($siswa);
                    $akun = $uid > 0 ? one('SELECT * FROM users WHERE id = ?', [$uid]) : null;
                    if (!$akun) {
                        $errSiswa = 'Akun siswa gagal disiapkan. Coba lagi sebentar lagi atau hubungi Admin.';
                        log_gagal(null, 'siswa:' . $nisn, 'akun siswa gagal dibuat');
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['uid'] = (int) $akun['id'];
                        $_SESSION['sambutan_tampil'] = 1;
                        log_sesi_mulai($akun);
                        unset($_SESSION['ctx_sekolah_id']);
                        q('UPDATE users SET login_terakhir = ? WHERE id = ?', [date('Y-m-d H:i:s'), (int) $akun['id']]);
                        redirect('index.php');
                    }
                }
            }
        }
    } else {
        // -------------------------------------------------------------- masuk aplikasi
        $user = trim((string) ($_POST['username'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');

        // Pembatas percobaan masuk (anti brute force): diperiksa SEBELUM kata sandi diverifikasi
        // supaya program penebak tidak dapat terus mencoba walau jawabannya selalu salah.
        $blokir = login_blokir_detik(log_ip(), $user);

        if ($user === '' || $pass === '') {
            $err = 'Nama pengguna dan kata sandi wajib diisi.';
            log_gagal(null, $user, 'nama pengguna atau kata sandi kosong');
        } elseif ($blokir > 0) {
            $err = login_pesan_blokir($blokir);
            log_gagal(null, $user, 'diblokir sementara (percobaan masuk berlebihan)');
        } else {
            $u = one('SELECT * FROM users WHERE username = ? COLLATE NOCASE', [$user]);
            $sekolahUser = ($u && $u['role'] !== 'super' && (int) ($u['sekolah_id'] ?? 0) > 0)
                ? one('SELECT * FROM sekolah WHERE id = ?', [(int) $u['sekolah_id']])
                : null;

            if (!$u || !password_verify($pass, (string) $u['password_hash'])) {
                $err = 'Nama pengguna atau kata sandi salah.';
                log_gagal($u, $user, $u ? 'kata sandi salah' : 'nama pengguna tidak dikenal');
            } elseif ((int) $u['aktif'] !== 1) {
                $err = 'Akun ini sedang dinonaktifkan. Hubungi Admin.';
                log_gagal($u, $user, 'akun dinonaktifkan');
            } elseif (!in_array((string) $u['role'], ['super', 'pengawas'], true) && !$sekolahUser) {
                $err = 'Akun ini belum ditautkan ke sekolah/madrasah mana pun. Hubungi Admin.';
                log_gagal($u, $user, 'akun belum ditautkan ke sekolah');
            } elseif ($sekolahUser && ($sekolahUser['verifikasi'] ?? 'disetujui') === 'menunggu') {
                $err = 'Pendaftaran ' . $sekolahUser['nama'] . ' masih MENUNGGU PERSETUJUAN Admin. '
                     . 'Anda dapat masuk setelah pendaftaran disetujui.';
                log_gagal($u, $user, 'pendaftaran sekolah masih menunggu persetujuan');
            } elseif ($sekolahUser && ($sekolahUser['verifikasi'] ?? 'disetujui') === 'ditolak') {
                $err = 'Pendaftaran ' . $sekolahUser['nama'] . ' belum disetujui'
                     . (!empty($sekolahUser['alasan_tolak']) ? ': ' . $sekolahUser['alasan_tolak'] : '.')
                     . ' Silakan hubungi Admin.';
                log_gagal($u, $user, 'pendaftaran sekolah ditolak');
            } elseif ($sekolahUser && (int) $sekolahUser['aktif'] !== 1) {
                $err = 'Sekolah/madrasah ' . $sekolahUser['nama'] . ' sedang dinonaktifkan. Hubungi Admin.';
                log_gagal($u, $user, 'sekolah dinonaktifkan');
            } elseif ($u['role'] === 'guru' && (int) ($u['guru_id'] ?? 0) <= 0) {
                $err = 'Akun guru ini belum ditautkan ke data guru. Hubungi Admin Operator atau Admin.';
                log_gagal($u, $user, 'akun guru belum ditautkan ke data guru');
            } else {
                session_regenerate_id(true);
                $tabMasuk = 'pengguna';
                $_SESSION['uid'] = (int) $u['id'];
                // Popup "Selamat datang" ditampilkan SEKALI setelah masuk berhasil (isi diatur Super
                // Admin). Penanda dihapus oleh page_bottom() setelah popup dicetak — lihat
                // sambutan_perlu_tampil()/sambutan_selesai() di inc/helpers.php.
                $_SESSION['sambutan_tampil'] = 1;
                log_sesi_mulai($u);   // daftarkan sebagai online + catat aktivitas "masuk"
                // Konteks sekolah dimulai bersih: super admin memilih sekolah sendiri lewat Kelola Sekolah.
                unset($_SESSION['ctx_sekolah_id']);
                q('UPDATE users SET login_terakhir = ? WHERE id = ?', [date('Y-m-d H:i:s'), (int) $u['id']]);
                $dest = $_SESSION['redirect_after'] ?? '';
                unset($_SESSION['redirect_after']);
                // KEAMANAN (anti OPEN REDIRECT): tujuan pengalihan setelah masuk hanya boleh
                // berupa jalur DI DALAM aplikasi ini. Nilainya berasal dari REQUEST_URI yang
                // tercatat saat pengguna diarahkan ke halaman masuk, sehingga WAJIB diperiksa:
                //   - wajib diawali satu garis miring ("/..."),
                //   - ditolak bila dimulai "//" (URL protokol-relatif ke situs lain),
                //   - ditolak bila memuat skema ("://" atau ":"), backslash, spasi, dan
                //     karakter kendali (pemecah header/URL).
                // Versi lama hanya memeriksa "://" sehingga "//situs-penyerang.example" LOLOS.
                $destOk = is_string($dest) && $dest !== ''
                    && substr($dest, 0, 1) === '/'
                    && substr($dest, 0, 2) !== '//'
                    && strpos($dest, ':') === false
                    && strpos($dest, '\\') === false
                    && preg_match('/[\x00-\x20\x7F]/', $dest) !== 1;
                if ($destOk) {
                    header('Location: ' . $dest);
                    exit;
                }
                redirect('index.php');
            }
        }
    }
}

// ------------------------------------------------------------------ data halaman depan
$slider    = landing_list('slider');
$fitur     = landing_list('fitur');
$unggulan  = landing_list('unggulan');
$testimoni = landing_list('testimoni');
$pengguna  = landing_pengguna_list();
$tentang   = tentang_isi();
$waTautan  = landing_wa_tautan('Assalamualaikum, saya ingin bertanya tentang aplikasi ' . APP_NAME . '.');
$jmlPendaftar = jumlah_pendaftaran_menunggu();

// Menu bilah navigasi = bagian-bagian halaman depan.
// Catatan (permintaan pemilik): tautan "Total Pengguna" TIDAK ditampilkan pada bilah navigasi —
// bagian/isi Total Pengguna di halaman depan tetap ada, hanya TAUTAN menunya yang dihapus.
$menuLanding = [
    ['#beranda', 'Beranda'],
    ['#fitur', 'Fitur Aplikasi'],
    ['#keunggulan', 'Keunggulan'],
    ['#testimoni', 'Testimoni'],
    ['#tentang', 'Tentang Aplikasi'],
    ['#kontak', 'Kontak'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(APP_NAME) ?> &middot; <?= e(APP_SUB) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<?php // Favicon & logo memakai LOGO APLIKASI bila Super Admin sudah mengunggahnya (menu "Logo Aplikasi"). ?>
<?php if (ada_logo_aplikasi()): ?>
<link rel="icon" href="<?= e(logo_aplikasi()) ?>">
<?php else: ?>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>&#128218;</text></svg>">
<?php endif; ?>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<!-- ================================================ BILAH NAVIGASI -->
<header class="lp-nav" id="lpNav">
  <div class="lp-wrap lp-nav-isi">
    <?php // Brand memakai kelas `portal-brand`/`portal-logo` supaya gaya & uji lama tetap berlaku. ?>
    <div class="lp-brand portal-brand">
      <?php if (ada_logo_aplikasi()): ?>
        <span class="lp-logo portal-logo portal-logo-img"><img src="<?= e(logo_aplikasi()) ?>" alt="Logo <?= e(APP_NAME) ?>"></span>
      <?php else: ?>
        <span class="lp-logo portal-logo"><?= ikon('mosque', 26) ?></span>
      <?php endif; ?>
      <div class="lp-brand-teks">
        <b><?= e(APP_NAME) ?> <span class="portal-tagline"><?= e(APP_SUB) ?></span></b>
        <?php // Permintaan pemilik: keterangan dalam kurung "(Satu aplikasi untuk administrasi guru)"
              // DIHAPUS — sisanya "Asisten Cerdas Guru" tetap dipakai (nilainya diperiksa uji lama). ?>
        <p>Asisten Cerdas Guru</p>
      </div>
    </div>
    <button class="lp-nav-toggle" type="button" id="lpNavToggle" aria-label="Buka menu"><?= ikon('slider', 20) ?></button>
    <nav class="lp-nav-link" id="lpNavLink">
      <?php foreach ($menuLanding as $m): ?>
        <a href="<?= e($m[0]) ?>"><?= e($m[1]) ?></a>
      <?php endforeach; ?>
      <a class="btn btn-primary btn-sm" href="#masuk"><?= ikon('lock', 15) ?> Masuk</a>
    </nav>
  </div>
</header>

<main>
  <!-- ================================================ BERANDA (SLIDER + PANEL MASUK) -->
  <section class="lp-hero" id="beranda">
    <div class="lp-wrap lp-hero-grid">
      <div class="lp-hero-teks">
        <?php if (trim(landing_teks('label')) !== ''): ?>
          <span class="lp-label"><?= e(landing_teks('label')) ?></span>
        <?php endif; ?>

        <?php
        // SLIDER BERANDA — permintaan pemilik: berisi JUDUL SLIDER, FOTO/GAMBAR, dan KETERANGAN,
        // isinya diatur Super Admin pada menu "Halaman Depan (Landing)" → tab "Slider Beranda".
        // Tanpa JavaScript tetap bekerja: lintasan memakai overflow-x + scroll-snap.
        ?>
        <?php if ($slider): ?>
          <div class="lp-slider" id="lpSlider" data-otomatis="6000">
            <div class="lp-slider-track">
              <?php foreach ($slider as $i => $sl): ?>
                <?php $gambar = landing_slider_gambar($sl); ?>
                <figure class="lp-slide">
                  <div class="lp-slide-gambar">
                    <?php if ($gambar !== ''): ?>
                      <img src="<?= e($gambar) ?>" alt="<?= e(trim((string) $sl['judul']) !== '' ? (string) $sl['judul'] : 'Gambar slider ' . ($i + 1)) ?>"<?= $i === 0 ? '' : ' loading="lazy"' ?>>
                    <?php else: ?>
                      <span class="lp-slide-kosong">
                        <?= ikon('school', 46) ?>
                        <small>Foto slider belum diunggah</small>
                      </span>
                    <?php endif; ?>
                  </div>
                  <figcaption class="lp-slide-teks">
                    <?php if (trim((string) $sl['judul']) !== ''): ?>
                      <?php if ($i === 0): ?>
                        <h1 class="lp-slide-judul"><?= e((string) $sl['judul']) ?></h1>
                      <?php else: ?>
                        <h2 class="lp-slide-judul"><?= e((string) $sl['judul']) ?></h2>
                      <?php endif; ?>
                    <?php endif; ?>
                    <?php if (trim((string) $sl['keterangan']) !== ''): ?>
                      <p class="lp-slide-ket"><?= nl2br(e((string) $sl['keterangan'])) ?></p>
                    <?php endif; ?>
                  </figcaption>
                </figure>
              <?php endforeach; ?>
            </div>

            <?php if (count($slider) > 1): ?>
              <div class="lp-slider-nav" id="lpSliderNav">
                <button type="button" class="lp-slider-panah" data-arah="-1" aria-label="Slide sebelumnya">&lsaquo;</button>
                <span class="lp-slider-titik" id="lpSliderTitik"></span>
                <button type="button" class="lp-slider-panah" data-arah="1" aria-label="Slide berikutnya">&rsaquo;</button>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php
        // BARIS KEUNGGULAN SINGKAT di bawah slider beranda (permintaan pemilik): tulisan
        // "Cepat, Mudah, Praktis, Lengkap" beserta ikon yang sesuai untuk masing-masing.
        // Diletakkan di KOLOM KIRI (di bawah slider & tombol navigasinya) sehingga tetap terlihat
        // walau slider belum diisi. Teksnya SENGAJA tetap di berkas ini (bukan diisi dari basis data)
        // karena istilahnya baku.
        $lpCepat = [
            ['kilat',  'Cepat'],
            ['check',  'Mudah'],
            ['jempol', 'Praktis'],
            ['apps',   'Lengkap'],
        ];
        ?>
        <ul class="lp-cepat" id="lpCepat">
          <?php foreach ($lpCepat as [$iko, $teks]): ?>
            <li class="lp-cepat-item">
              <span class="lp-cepat-ikon" aria-hidden="true"><?= ikon((string) $iko, 18) ?></span>
              <span class="lp-cepat-teks"><?= e((string) $teks) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- ------------------------------- PANEL MASUK (tetap dipertahankan) -->
      <div class="lp-hero-side">
        <div class="card lp-login portal-login" id="masuk">
          <div class="card-head">
            <div>
              <h2><?= ikon('lock', 16) ?> Masuk Aplikasi</h2>
              <p>Pilih jenis akun Anda: <b>Pengguna</b> (pengelola/guru sekolah) atau <b>Siswa</b>.</p>
            </div>
          </div>

          <?php
          // DUA TAB panel masuk (permintaan pemilik): "Pengguna" dan "Siswa".
          // Tabnya berupa TAUTAN (bukan radio/JavaScript) sehingga halaman tetap benar walau skrip
          // gagal dimuat dan keadaan tab tersimpan pada alamat halaman (?tab=siswa).
          $tabAktif = in_array($tabMasuk, ['pengguna', 'siswa'], true) ? $tabMasuk : 'pengguna';
          ?>
          <div class="lp-tab" role="tablist" aria-label="Jenis akun">
            <a class="lp-tab-btn <?= $tabAktif === 'pengguna' ? 'on' : '' ?>" role="tab"
               aria-selected="<?= $tabAktif === 'pengguna' ? 'true' : 'false' ?>"
               href="login.php?tab=pengguna#masuk"><?= ikon('user', 15) ?> Pengguna</a>
            <a class="lp-tab-btn <?= $tabAktif === 'siswa' ? 'on' : '' ?>" role="tab"
               aria-selected="<?= $tabAktif === 'siswa' ? 'true' : 'false' ?>"
               href="login.php?tab=siswa#masuk"><?= ikon('student', 15) ?> Siswa</a>
          </div>

          <?php if ($tabAktif === 'siswa'): ?>
            <!-- ================== TAB SISWA: NISN + kata sandi (tanggal lahir) ================== -->
            <?php if ($errSiswa !== ''): ?>
              <div class="alert alert-err"><?= ikon('alert', 16) ?><span><?= e($errSiswa) ?></span></div>
            <?php endif; ?>

            <form method="post" autocomplete="off" id="formMasukSiswa">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="masuk_siswa">
              <div>
                <label class="f" for="nisn">NISN</label>
                <input type="text" id="nisn" name="nisn" value="<?= e($nisn) ?>" required autofocus
                       inputmode="numeric" maxlength="30" placeholder="nomor NISN Anda">
              </div>
              <div class="mt16">
                <label class="f" for="sandiSiswa">Kata Sandi</label>
                <input type="password" id="sandiSiswa" name="sandi" required
                       inputmode="numeric" maxlength="12" placeholder="8 angka tanggal lahir Anda">
              </div>
              <div class="hint">Kata sandi adalah <b>tanggal lahir Anda dalam 8 angka</b> (DDMMYYYY) —
                contoh: lahir 1 Januari 2007 maka kata sandinya <b>01012007</b>. NISN tertera pada Kartu
                Pelajar atau dapat ditanyakan kepada Admin Operator sekolah/madrasah Anda.</div>
              <button class="btn btn-primary full mt16" type="submit"><?= ikon('lock', 16) ?> Masuk sebagai Siswa</button>
            </form>

            <div class="divider"></div>
            <p class="sm mut" style="margin:0">
              Halaman siswa hanya menampilkan data Anda sendiri (nama &amp; NISN, rekapan daftar hadir,
              rekapan nilai, dan catatan pelanggaran) dan tidak dapat diubah dari sini.
            </p>
          <?php else: ?>
            <!-- ============================ TAB PENGGUNA ============================ -->
            <?php if ($err !== ''): ?>
              <div class="alert alert-err"><?= ikon('alert', 16) ?><span><?= e($err) ?></span></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
              <?= csrf_field() ?>
              <div>
                <label class="f" for="username">Nama Pengguna</label>
                <input type="text" id="username" name="username" value="<?= e($user) ?>" required autofocus
                       placeholder="nama pengguna akun Anda">
              </div>
              <div class="mt16">
                <label class="f" for="password">Kata Sandi</label>
                <input type="password" id="password" name="password" required>
              </div>
              <button class="btn btn-primary full mt16" type="submit"><?= ikon('lock', 16) ?> Masuk</button>
            </form>
          <?php endif; ?>

          <div class="divider"></div>
          <p class="sm mut" style="margin:0 0 10px">
            Belum punya akun? Sekolah/madrasah Anda dapat mendaftar sendiri — pendaftaran disetujui Admin,
            lalu aplikasi langsung dapat dipakai.
          </p>
          <a class="btn btn-ghost full" href="daftar.php"><?= ikon('school', 16) ?> Daftarkan Sekolah/Madrasah</a>

          <div class="divider"></div>
          <div class="portal-bantuan">
            <b>Butuh bantuan?</b>
            <span>Lupa kata sandi, akun belum bisa masuk, NISN belum terdaftar, atau pendaftaran belum
              disetujui — hubungi Admin <?= e(APP_NAME) ?> atau pengelola sekolah/madrasah Anda.</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ================================================ FITUR APLIKASI -->
  <?php if ($fitur): ?>
    <section class="lp-seksi" id="fitur">
      <div class="lp-wrap">
        <div class="lp-seksi-head">
          <h2><?= e(landing_teks('fitur_judul')) ?></h2>
          <?php if (trim(landing_teks('fitur_sub')) !== ''): ?><p><?= e(landing_teks('fitur_sub')) ?></p><?php endif; ?>
        </div>
        <div class="lp-grid lp-grid-3">
          <?php foreach ($fitur as $f): ?>
            <article class="lp-kartu lp-timbul">
              <span class="lp-ikon"><?= ikon(landing_ikon_sah((string) $f['ikon']), 24) ?></span>
              <h3><?= e((string) $f['judul']) ?></h3>
              <?php if (trim((string) $f['teks']) !== ''): ?><p><?= nl2br(e((string) $f['teks'])) ?></p><?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ================================================ KEUNGGULAN -->
  <?php if ($unggulan): ?>
    <section class="lp-seksi lp-alt" id="keunggulan">
      <div class="lp-wrap">
        <div class="lp-seksi-head">
          <h2><?= e(landing_teks('unggulan_judul')) ?></h2>
          <?php if (trim(landing_teks('unggulan_sub')) !== ''): ?><p><?= e(landing_teks('unggulan_sub')) ?></p><?php endif; ?>
        </div>
        <div class="lp-grid lp-grid-3">
          <?php foreach ($unggulan as $f): ?>
            <article class="lp-kartu lp-timbul lp-unggulan">
              <span class="lp-ikon lp-ikon-emas"><?= ikon(landing_ikon_sah((string) $f['ikon']), 24) ?></span>
              <h3><?= e((string) $f['judul']) ?></h3>
              <?php if (trim((string) $f['teks']) !== ''): ?><p><?= nl2br(e((string) $f['teks'])) ?></p><?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <!-- ================================================ TOTAL PENGGUNA (angka OTOMATIS) -->
  <?php if ($pengguna): ?>
    <section class="lp-seksi lp-pengguna" id="pengguna">
      <div class="lp-wrap">
        <div class="lp-seksi-head">
          <h2><?= e(landing_teks('pengguna_judul')) ?></h2>
          <?php if (trim(landing_teks('pengguna_sub')) !== ''): ?><p><?= e(landing_teks('pengguna_sub')) ?></p><?php endif; ?>
        </div>
        <div class="lp-grid lp-pengguna-grid">
          <?php foreach ($pengguna as $p): ?>
            <div class="lp-pengguna-kartu lp-timbul">
              <span class="lp-pengguna-ikon"><?= ikon($p['peran'] === 'guru' ? 'user' : 'users', 22) ?></span>
              <b class="lp-pengguna-angka"><?= e(number_format((int) $p['angka'], 0, ',', '.')) ?></b>
              <span class="lp-pengguna-label"><?= e((string) $p['label']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <?php // Baris ringkasan "N sekolah … · N akun pengguna aktif · N siswa aktif" DIHAPUS atas
              // permintaan pemilik (ditandai merah pada tangkapan layar): informasinya sudah terwakili
              // keempat kartu Total Pengguna di atas. Jangan dikembalikan. ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- ================================================ TESTIMONI -->
  <?php if ($testimoni): ?>
    <section class="lp-seksi" id="testimoni">
      <div class="lp-wrap">
        <div class="lp-seksi-head">
          <?php if (trim(landing_teks('testimoni_judul')) !== ''): ?>
            <span class="lp-label"><?= e(landing_teks('testimoni_judul')) ?></span>
          <?php endif; ?>
          <?php if (trim(landing_teks('testimoni_judul_besar')) !== ''): ?>
            <h2><?= e(landing_teks('testimoni_judul_besar')) ?></h2>
          <?php endif; ?>
          <?php if (trim(landing_teks('testimoni_sub')) !== ''): ?><p><?= e(landing_teks('testimoni_sub')) ?></p><?php endif; ?>
        </div>

        <div class="lp-testi-slider" id="testiSlider" data-otomatis="6000">
          <div class="lp-testi-track">
            <?php foreach ($testimoni as $t): ?>
              <figure class="lp-testi-slide">
                <span class="lp-kutip">&ldquo;</span>
                <?php if (trim((string) $t['isi']) !== ''): ?>
                  <blockquote><?= nl2br(e((string) $t['isi'])) ?></blockquote>
                <?php endif; ?>
                <figcaption class="lp-testi-sumber">
                  <?php $foto = trim((string) ($t['foto'] ?? '')); ?>
                  <?php if ($foto !== ''): ?>
                    <img class="lp-testi-foto" src="<?= e($foto) ?>" alt="Foto <?= e((string) $t['nama']) ?>">
                  <?php else: ?>
                    <span class="lp-testi-foto lp-testi-inisial"><?= e(strtoupper(substr(trim((string) $t['nama']), 0, 1))) ?></span>
                  <?php endif; ?>
                  <span class="lp-testi-teks">
                    <b><?= e((string) $t['nama']) ?></b>
                    <?php if (trim((string) $t['jabatan']) !== ''): ?><small><?= e((string) $t['jabatan']) ?></small><?php endif; ?>
                  </span>
                </figcaption>
              </figure>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if (count($testimoni) > 1): ?>
          <div class="lp-testi-nav" id="testiNav">
            <button type="button" class="lp-testi-panah" data-arah="-1" aria-label="Testimoni sebelumnya">&lsaquo;</button>
            <span class="lp-testi-titik" id="testiTitik"></span>
            <button type="button" class="lp-testi-panah" data-arah="1" aria-label="Testimoni berikutnya">&rsaquo;</button>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>

  <!-- ================================================ TENTANG APLIKASI -->
  <?php // Kelas `card portal-hero` DIPERTAHANKAN: gaya `.portal-hero .portal-lead` (rata kiri-kanan)
        // dan uji lama yang membaca kartu ini bergantung padanya. ?>
  <section class="card portal-hero lp-tentang-kartu" id="tentang">
    <div class="lp-wrap">
      <div class="lp-seksi-head">
        <h2><?= e(landing_teks('tentang_judul')) ?></h2>
        <?php if (trim(landing_teks('tentang_sub')) !== ''): ?><p><?= e(landing_teks('tentang_sub')) ?></p><?php endif; ?>
      </div>
      <?php if (!empty($tentang['ada'])): ?>
        <div class="portal-tentang" id="tentangAplikasi">
          <div class="tentang-baris">
            <?php if ($tentang['jumlah_foto'] > 0 || $tentang['slot'][1]['nama'] !== '' || $tentang['slot'][2]['nama'] !== ''): ?>
              <div class="tentang-foto-grid">
                <?php foreach (tentang_slot_list() as $n): ?>
                  <?php $sl = $tentang['slot'][$n]; ?>
                  <?php if ($sl['foto'] === '' && $sl['nama'] === '') { continue; } ?>
                  <figure class="tentang-slot">
                    <div class="tentang-bingkai">
                      <?php if ($sl['foto'] !== ''): ?>
                        <img src="<?= e($sl['foto']) ?>" alt="<?= e($sl['nama'] !== '' ? $sl['nama'] : 'Foto ' . $n) ?>" loading="lazy">
                      <?php else: ?>
                        <span class="tentang-kosong"><?= ikon('user', 26) ?></span>
                      <?php endif; ?>
                    </div>
                    <?php if ($sl['nama'] !== ''): ?>
                      <figcaption class="tentang-nama"><?= e($sl['nama']) ?></figcaption>
                    <?php endif; ?>
                  </figure>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php // Keterangan di SISI KANAN kedua foto (`.tentang-ket` = flex:1 1 240px) — paragrafnya
                  // memakai kelas `.portal-lead` supaya RATA KIRI-KANAN seperti sebelumnya. ?>
            <?php $tentangParagraf = tentang_paragraf($tentang['keterangan']); ?>
            <?php if ($tentangParagraf): ?>
              <div class="tentang-ket">
                <?php foreach ($tentangParagraf as $p): ?>
                  <p class="portal-lead"><?= nl2br(e($p)) ?></p>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ================================================ KONTAK -->
  <section class="lp-seksi lp-alt" id="kontak">
    <div class="lp-wrap">
      <div class="lp-seksi-head">
        <h2><?= e(landing_teks('kontak_judul')) ?></h2>
        <?php if (trim(landing_teks('kontak_sub')) !== ''): ?><p><?= e(landing_teks('kontak_sub')) ?></p><?php endif; ?>
      </div>
      <div class="lp-grid lp-kontak-grid">
        <div class="card lp-kontak">
          <div class="card-head">
            <div>
              <h3><?= ikon('telepon', 16) ?> Informasi Kontak</h3>
              <p>Alamat, telepon, dan jam layanan Admin aplikasi.</p>
            </div>
          </div>
          <?php if (trim(landing_teks('kontak_ajakan')) !== ''): ?>
            <p><?= nl2br(e(landing_teks('kontak_ajakan'))) ?></p>
          <?php endif; ?>
          <ul class="lp-kontak-list">
            <?php if (trim(landing_teks('kontak_alamat')) !== ''): ?>
              <li><span class="lp-ikon lp-ikon-kecil"><?= ikon('lokasi', 18) ?></span>
                <span><small>Alamat</small><?= nl2br(e(landing_teks('kontak_alamat'))) ?></span></li>
            <?php endif; ?>
            <?php if (trim(landing_teks('kontak_telepon')) !== ''): ?>
              <li><span class="lp-ikon lp-ikon-kecil"><?= ikon('telepon', 18) ?></span>
                <span><small>Telepon / WhatsApp</small><?= e(landing_teks('kontak_telepon')) ?></span></li>
            <?php endif; ?>
            <?php if (trim(landing_teks('kontak_email')) !== ''): ?>
              <li><span class="lp-ikon lp-ikon-kecil"><?= ikon('email', 18) ?></span>
                <span><small>Email</small><a href="mailto:<?= e(landing_teks('kontak_email')) ?>"><?= e(landing_teks('kontak_email')) ?></a></span></li>
            <?php endif; ?>
            <?php if (trim(landing_teks('kontak_jam')) !== ''): ?>
              <li><span class="lp-ikon lp-ikon-kecil"><?= ikon('clock', 18) ?></span>
                <span><small>Jam Layanan</small><?= e(landing_teks('kontak_jam')) ?></span></li>
            <?php endif; ?>
          </ul>
          <div class="lp-kontak-aksi">
            <?php if ($waTautan !== ''): ?>
              <a class="btn btn-primary" href="<?= e($waTautan) ?>" target="_blank" rel="noopener noreferrer">
                <?= ikon('whatsapp', 16) ?> Hubungi lewat WhatsApp</a>
            <?php endif; ?>
            <?php if (trim(landing_teks('kontak_maps')) !== ''): ?>
              <a class="btn btn-ghost" href="<?= e(landing_teks('kontak_maps')) ?>" target="_blank" rel="noopener noreferrer">
                <?= ikon('lokasi', 16) ?> Lihat Lokasi</a>
            <?php endif; ?>
            <a class="btn btn-ghost" href="daftar.php"><?= ikon('school', 16) ?> Daftarkan Sekolah/Madrasah</a>
          </div>
        </div>

        <div class="card lp-kontak" id="cardPesanKontak">
          <div class="card-head">
            <div>
              <h3><?= ikon('chat', 16) ?> Kirim Pesan</h3>
              <p>Pesan Anda tercatat pada akun Admin aplikasi.</p>
            </div>
          </div>
          <?php if (!$pesanGalat && !empty($_GET['terkirim'])): ?>
            <div class="alert alert-ok"><?= ikon('check', 16) ?><span>Terima kasih, pesan Anda sudah terkirim.</span></div>
          <?php endif; ?>
          <?php if ($pesanGalat): ?>
            <div class="alert alert-err">
              <?= ikon('alert', 16) ?>
              <ul class="poin-list" style="margin:0">
                <?php foreach ($pesanGalat as $g): ?><li><?= e($g) ?></li><?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="pesan">
            <div>
              <label class="f" for="namaPesan">Nama</label>
              <input type="text" id="namaPesan" name="nama" value="<?= e($nilaiPesan['nama']) ?>"
                     maxlength="<?= (int) LANDING_NAMA_MAKS ?>" required placeholder="nama Anda">
            </div>
            <div class="mt16">
              <label class="f" for="kontakPesan">Email / No. WhatsApp</label>
              <input type="text" id="kontakPesan" name="kontak" value="<?= e($nilaiPesan['kontak']) ?>"
                     maxlength="120" required placeholder="agar Admin dapat membalas">
            </div>
            <div class="mt16">
              <label class="f" for="isiPesan">Pesan</label>
              <textarea id="isiPesan" name="pesan" rows="4" maxlength="<?= (int) LANDING_PESAN_MAKS ?>"
                        required placeholder="Tulis pertanyaan atau pesan Anda."><?= e($nilaiPesan['pesan']) ?></textarea>
            </div>
            <div class="mt16">
              <label class="f" for="captchaPesan">Pengaman: <?= (int) $cap['a'] ?> + <?= (int) $cap['b'] ?> = ?</label>
              <input type="text" id="captchaPesan" name="captcha" required placeholder="jawaban berupa angka">
            </div>
            <button class="btn btn-primary full mt16" type="submit"><?= ikon('send', 16) ?> Kirim Pesan</button>
          </form>
        </div>
      </div>
    </div>
  </section>
</main>

<!-- ================================================ KAKI HALAMAN -->
<?php
// KAKI HALAMAN — permintaan pemilik: blok kiri (nama aplikasi + daftar tautan bagian) DIHAPUS,
// dan keterangan versi + baris pengembang dipindahkan ke TENGAH. Kelas `portal-foot` sengaja
// tetap dipasang sebagai penanda (dipakai pemeriksaan uji), sedangkan gayanya memakai `.lp-foot`.
?>
<footer class="lp-foot portal-foot">
  <div class="lp-wrap lp-foot-isi">
    <div class="lp-foot-ket">
      <div><?= e(APP_NAME) ?> Versi <?= e(APP_VERSION) ?> &middot; <?= e(APP_SUB) ?></div>
      <div class="portal-pengembang">Dikembangkan oleh <?= e(APP_AUTHOR) ?> (<?= e(APP_AUTHOR_JABATAN) ?>)</div>
    </div>
  </div>
</footer>
<?php // Skrip bersama: menggerakkan slider beranda & slider testimoni (lihat initLanding()).
      // Tanpa skrip ini, kedua lintasan tetap bisa digeser sendiri (overflow-x + scroll-snap). ?>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
