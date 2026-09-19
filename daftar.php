<?php
/**
 * Pendaftaran sekolah/madrasah MANDIRI (halaman publik) — tata letak mengikuti halaman
 * pendaftaran aplikasi Supervisi Guru (kepala halaman, langkah 1-2-3, bagian bertajuk, kartu sukses).
 *
 * Alur: sekolah mengisi formulir → dibuat dengan status `menunggu` dan `aktif = 0`
 * (login ditolak sampai Super Admin menyetujui di pendaftaran.php) → setelah disetujui,
 * aplikasi langsung dapat dipakai (mata pelajaran awal, tahun ajaran, dan semester disiapkan otomatis).
 *
 * Penjaga: captcha penjumlahan, maksimal 3 pendaftaran per sesi, pernyataan data benar, CSRF,
 * dan validasi kontak.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

// Halaman pendaftaran bersifat lintas sekolah/madrasah → istilah generik "Sekolah".
$GLOBALS['__vs_istilah_nonaktif'] = true;

if (current_user()) {
    redirect('index.php');
}

$err = [];
$sukses = null;

$nilai = [
    'nama' => '', 'jenis' => 'madrasah', 'npsn' => '', 'alamat' => '', 'telepon' => '', 'email' => '',
    'kepala_sekolah' => '', 'nip_kepala_sekolah' => '', 'kontak_nama' => '', 'kontak_telepon' => '',
    'kontak_email' => '', 'username' => '',
];

/** Pertanyaan pengaman (penjumlahan) — jawabannya disimpan di sesi, tidak dikirim ke peramban. */
function captcha_baru(): array
{
    $a = random_int(3, 12);
    $b = random_int(2, 9);
    $_SESSION['daftar_captcha'] = $a;
    $_SESSION['daftar_captcha2'] = $b;
    return [$a, $b];
}

if (empty($_SESSION['daftar_captcha'])) {
    captcha_baru();
}
if (!isset($_SESSION['daftar_jumlah'])) {
    $_SESSION['daftar_jumlah'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    foreach ($nilai as $k => $v) {
        $nilai[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $sandi = (string) ($_POST['password'] ?? '');
    $sandi2 = (string) ($_POST['password2'] ?? '');
    $jawab = trim((string) ($_POST['captcha'] ?? ''));
    $setuju = !empty($_POST['setuju']);
    $harap = (int) ($_SESSION['daftar_captcha'] ?? 0) + (int) ($_SESSION['daftar_captcha2'] ?? 0);

    if ((int) $_SESSION['daftar_jumlah'] >= 3) {
        $err[] = 'Anda sudah mengirim 3 pendaftaran pada sesi ini. Hubungi Super Admin bila perlu menambah lagi.';
    }
    if ($nilai['nama'] === '') {
        $err[] = 'Nama sekolah/madrasah wajib diisi.';
    }
    if ($nilai['kontak_nama'] === '') {
        $err[] = 'Nama penanggung jawab (yang dapat dihubungi) wajib diisi.';
    }
    if ($nilai['kontak_telepon'] === '' && $nilai['kontak_email'] === '') {
        $err[] = 'Isi minimal satu kontak yang dapat dihubungi: nomor HP/WhatsApp atau email.';
    }
    if ($nilai['kontak_email'] !== '' && !filter_var($nilai['kontak_email'], FILTER_VALIDATE_EMAIL)) {
        $err[] = 'Alamat email kontak belum valid.';
    }
    if ($nilai['email'] !== '' && !filter_var($nilai['email'], FILTER_VALIDATE_EMAIL)) {
        $err[] = 'Alamat email sekolah/madrasah belum valid.';
    }
    if ($nilai['username'] === '' || strlen($nilai['username']) < 4) {
        $err[] = 'Nama pengguna akun pengelola minimal 4 karakter tanpa spasi.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $nilai['username'])) {
        $err[] = 'Nama pengguna hanya boleh huruf, angka, titik, garis bawah, atau tanda hubung.';
    }
    if (strlen($sandi) < 8) {
        $err[] = 'Kata sandi minimal 8 karakter.';
    } elseif ($sandi !== $sandi2) {
        $err[] = 'Ulangi kata sandi dengan tepat sama.';
    }
    if ($jawab === '' || (int) $jawab !== $harap) {
        $err[] = 'Jawaban pertanyaan pengaman belum benar. Coba lagi.';
    }
    if (!$setuju) {
        $err[] = 'Centang pernyataan bahwa data yang diisi benar dan dapat dipertanggungjawabkan.';
    }
    if (!$err && one('SELECT id FROM users WHERE username = ?', [$nilai['username']])) {
        $err[] = 'Nama pengguna "' . $nilai['username'] . '" sudah dipakai akun lain. Pilih nama pengguna lain.';
    }
    if (!$err && one('SELECT id FROM sekolah WHERE lower(nama) = lower(?)', [$nilai['nama']])) {
        $err[] = 'Sekolah/madrasah dengan nama itu sudah terdaftar. Hubungi Super Admin bila ini kekeliruan.';
    }

    if ($err) {
        captcha_baru();
    } else {
        $now = date('Y-m-d H:i:s');
        db()->exec('BEGIN IMMEDIATE');
        try {
            q('INSERT INTO sekolah (nama, npsn, alamat, telepon, email, kepala_sekolah, nip_kepala_sekolah, jenis,
                    verifikasi, aktif, kontak_nama, kontak_telepon, kontak_email, didaftar, dibuat)
               VALUES (?,?,?,?,?,?,?,?,"menunggu",0,?,?,?,?,?)',
                [$nilai['nama'], $nilai['npsn'], $nilai['alamat'], $nilai['telepon'], $nilai['email'],
                 $nilai['kepala_sekolah'], nip_bersih($nilai['nip_kepala_sekolah']),
                 $nilai['jenis'] === 'umum' ? 'umum' : 'madrasah',
                 $nilai['kontak_nama'], $nilai['kontak_telepon'], $nilai['kontak_email'], $now, $now]);
            $sid = (int) db()->lastInsertId();
            q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, aktif, dibuat) VALUES (?,?,?,?,?,1,?)',
                [$sid, $nilai['username'], password_hash($sandi, PASSWORD_DEFAULT),
                 $nilai['kontak_nama'] !== '' ? $nilai['kontak_nama'] : $nilai['nama'], 'kepala', $now]);
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        $_SESSION['daftar_jumlah'] = (int) $_SESSION['daftar_jumlah'] + 1;
        unset($_SESSION['daftar_captcha'], $_SESSION['daftar_captcha2']);
        $sukses = [
            'nama' => $nilai['nama'],
            'username' => $nilai['username'],
            'jenis' => $nilai['jenis'] === 'umum' ? 'Sekolah Umum' : 'Madrasah',
            'kontak' => $nilai['kontak_telepon'] !== '' ? $nilai['kontak_telepon'] : $nilai['kontak_email'],
        ];
    }
}

$soal = [0, 0];
if ($sukses === null) {
    $soal = [
        (int) ($_SESSION['daftar_captcha'] ?? 0),
        (int) ($_SESSION['daftar_captcha2'] ?? 0),
    ];
    if ($soal[0] <= 0) {
        $soal = captcha_baru();
    }
}
$sisa = max(0, 3 - (int) $_SESSION['daftar_jumlah']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pendaftaran Sekolah/Madrasah &middot; <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>&#128218;</text></svg>">
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="daftar-wrap">
  <div class="daftar-inner">

    <div class="daftar-head">
      <div class="daftar-logo"><?= ikon('mosque', 28) ?></div>
      <div>
        <h1>Pendaftaran Sekolah/Madrasah</h1>
        <p><?= e(APP_NAME) ?> &middot; <?= e(APP_SUB) ?> &middot; satu aplikasi melayani banyak sekolah/madrasah</p>
      </div>
      <div class="daftar-head-kanan">
        <a class="btn btn-ghost btn-sm" href="login.php"><?= ikon('lock', 15) ?> Halaman Masuk</a>
      </div>
    </div>

    <?php if ($sukses !== null): ?>
      <div class="card">
        <div class="empty" style="padding:26px 18px 20px">
          <?= ikon('check', 42) ?>
          <h3>Pendaftaran terkirim</h3>
          <p>Terima kasih. Pendaftaran <b><?= e($sukses['nama']) ?></b> (<?= e($sukses['jenis']) ?>) sudah kami terima
            dan sekarang <b>menunggu persetujuan Super Admin</b>.</p>
        </div>

        <div class="grid g3" style="gap:12px">
          <div class="langkah">
            <i>1</i>
            <div><b>Data diperiksa</b><span>Super Admin memeriksa kelengkapan data sekolah/madrasah Anda.</span></div>
          </div>
          <div class="langkah">
            <i>2</i>
            <div><b>Persetujuan</b><span>Setelah disetujui, status sekolah menjadi aktif dan aplikasi siap dipakai.</span></div>
          </div>
          <div class="langkah">
            <i>3</i>
            <div><b>Masuk &amp; mulai</b><span><?= count(mapel_awal()) ?> mata pelajaran, tahun ajaran, dan semester
              sudah disiapkan otomatis.</span></div>
          </div>
        </div>

        <div class="divider"></div>
        <div class="kredensial">
          <div class="grid g2" style="gap:14px">
            <div>
              <label class="f">Nama Pengguna</label>
              <div class="b"><?= e($sukses['username']) ?></div>
            </div>
            <div>
              <label class="f">Kata Sandi</label>
              <div class="b">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull; <span class="xs mut">(yang Anda isi tadi)</span></div>
            </div>
          </div>
          <p class="hint" style="margin-bottom:0">Simpan nama pengguna dan kata sandi Anda. Bila lupa, minta Super Admin
            mengatur ulang.</p>
        </div>

        <div class="divider"></div>
        <p class="sm mut" style="margin:0">
          Login Anda masih ditolak selama pendaftaran belum disetujui — Anda akan melihat pesan
          <b>menunggu persetujuan</b> saat mencoba masuk. Bila dalam beberapa hari belum ada kabar,
          hubungi Super Admin<?= $sukses['kontak'] !== '' ? ' (kontak pendaftaran Anda: ' . e($sukses['kontak']) . ')' : '' ?>.
        </p>

        <div class="flex mt16" style="justify-content:center">
          <a class="btn btn-primary" href="login.php"><?= ikon('lock', 16) ?> Ke Halaman Masuk</a>
          <a class="btn btn-ghost" href="daftar.php"><?= ikon('plus', 16) ?> Daftarkan sekolah/madrasah lain</a>
        </div>
      </div>
    <?php else: ?>
      <?php if ($err): ?>
        <div class="alert alert-err">
          <?= ikon('alert', 16) ?>
          <span><b>Periksa kembali isian Anda:</b><br><?php foreach ($err as $m): ?>&bull; <?= e($m) ?><br><?php endforeach; ?></span>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-head">
          <div>
            <h2>Formulir Pendaftaran</h2>
            <p>Isi data sekolah/madrasah dan akun pengelolanya. Login dapat dipakai setelah pendaftaran disetujui Admin.</p>
          </div>
        </div>

        <div class="grid g3 mb16" style="gap:12px">
          <div class="langkah"><i>1</i><div><b>Isi formulir</b><span>Data sekolah/madrasah + akun pengelola</span></div></div>
          <div class="langkah"><i>2</i><div><b>Diperiksa Super Admin</b><span>Persetujuan penggunaan aplikasi</span></div></div>
          <div class="langkah"><i>3</i><div><b>Masuk &amp; mulai</b><span>Mapel, tahun ajaran &amp; semester disiapkan</span></div></div>
        </div>

        <?php if ($sisa <= 0): ?>
          <div class="alert alert-warn" style="margin-bottom:0">
            <?= ikon('alert', 16) ?>
            <span>Batas 3 pendaftaran per sesi sudah tercapai. Hubungi Super Admin untuk pendaftaran berikutnya.</span>
          </div>
        <?php else: ?>
          <form method="post" class="grid" style="gap:18px" autocomplete="off">
            <?= csrf_field() ?>

            <div>
              <div class="section-title mb8"><?= ikon('school', 14) ?> Identitas Sekolah/Madrasah</div>
              <div class="grid g2">
                <div>
                  <label class="f" for="nama">Nama Sekolah/Madrasah *</label>
                  <input type="text" id="nama" name="nama" value="<?= e($nilai['nama']) ?>" required
                         placeholder="mis. MA. Sirojuth Tholibiin Mootilango">
                </div>
                <div>
                  <label class="f" for="jenis">Jenis *</label>
                  <select id="jenis" name="jenis">
                    <option value="madrasah" <?= $nilai['jenis'] === 'madrasah' ? 'selected' : '' ?>>Madrasah (MI/MTs/MA)</option>
                    <option value="umum" <?= $nilai['jenis'] === 'umum' ? 'selected' : '' ?>>Sekolah Umum (SD/SMP/SMA/SMK)</option>
                  </select>
                  <div class="hint">Menentukan istilah pada aplikasi &amp; dokumen cetak sekolah tersebut.</div>
                </div>
                <div>
                  <label class="f" for="npsn">NPSN</label>
                  <input type="text" id="npsn" name="npsn" value="<?= e($nilai['npsn']) ?>" placeholder="8 digit">
                </div>
                <div>
                  <label class="f" for="telepon">Telepon Sekolah/Madrasah</label>
                  <input type="text" id="telepon" name="telepon" value="<?= e($nilai['telepon']) ?>" placeholder="08xxxxxxxxxx">
                </div>
                <div style="grid-column:1/-1">
                  <label class="f" for="alamat">Alamat</label>
                  <input type="text" id="alamat" name="alamat" value="<?= e($nilai['alamat']) ?>"
                         placeholder="Jl. ..., Desa ..., Kec. ..., Kab. ...">
                </div>
                <div>
                  <label class="f" for="email">Email Sekolah/Madrasah</label>
                  <input type="text" id="email" name="email" value="<?= e($nilai['email']) ?>" placeholder="nama@email.com">
                </div>
                <div>
                  <label class="f" for="kepala_sekolah">Nama Kepala</label>
                  <input type="text" id="kepala_sekolah" name="kepala_sekolah" value="<?= e($nilai['kepala_sekolah']) ?>"
                         placeholder="nama &amp; gelar">
                </div>
                <div>
                  <label class="f" for="nip_kepala_sekolah">NIP Kepala</label>
                  <input type="text" id="nip_kepala_sekolah" name="nip_kepala_sekolah" value="<?= e($nilai['nip_kepala_sekolah']) ?>">
                </div>
              </div>
            </div>

            <div class="divider" style="margin:0"></div>

            <div>
              <div class="section-title mb8"><?= ikon('user', 14) ?> Penanggung Jawab / Kontak</div>
              <div class="grid g3">
                <div>
                  <label class="f" for="kontak_nama">Nama Penanggung Jawab *</label>
                  <input type="text" id="kontak_nama" name="kontak_nama" value="<?= e($nilai['kontak_nama']) ?>" required
                         placeholder="mis. kepala sekolah / operator">
                </div>
                <div>
                  <label class="f" for="kontak_telepon">No. HP / WhatsApp</label>
                  <input type="text" id="kontak_telepon" name="kontak_telepon" value="<?= e($nilai['kontak_telepon']) ?>"
                         placeholder="08xxxxxxxxxx">
                </div>
                <div>
                  <label class="f" for="kontak_email">Email</label>
                  <input type="text" id="kontak_email" name="kontak_email" value="<?= e($nilai['kontak_email']) ?>"
                         placeholder="nama@email.com">
                </div>
              </div>
              <div class="hint">Isi minimal salah satu (HP/WhatsApp atau email) agar Super Admin dapat menghubungi Anda.</div>
            </div>

            <div class="divider" style="margin:0"></div>

            <div>
              <div class="section-title mb8"><?= ikon('key', 14) ?> Akun Pengelola</div>
              <div class="grid g2">
                <div>
                  <label class="f" for="username">Nama Pengguna *</label>
                  <input type="text" id="username" name="username" value="<?= e($nilai['username']) ?>" required minlength="4"
                         placeholder="huruf/angka, tanpa spasi">
                  <div class="hint">Dipakai untuk masuk. Harus belum dipakai akun lain.</div>
                </div>
                <div>
                  <label class="f" for="password">Kata Sandi * <span class="mut">(min. 8 karakter)</span></label>
                  <input type="password" id="password" name="password" required minlength="8" placeholder="minimal 8 karakter">
                </div>
                <div>
                  <label class="f" for="password2">Ulangi Kata Sandi *</label>
                  <input type="password" id="password2" name="password2" required minlength="8">
                </div>
              </div>
              <div class="hint">Akun ini berperan sebagai <b>Kepala Sekolah/Madrasah</b>. Super Admin dapat menambahkan
                akun Admin Operator setelah pendaftaran disetujui.</div>
            </div>

            <div class="divider" style="margin:0"></div>

            <div class="grid g2" style="gap:14px;align-items:end">
              <div>
                <label class="f" for="captcha">Pertanyaan Pengaman *</label>
                <div class="flex" style="gap:10px;align-items:center">
                  <span class="chip" id="soalCaptcha"><?= (int) $soal[0] ?> + <?= (int) $soal[1] ?> = ?</span>
                  <input type="text" id="captcha" name="captcha" required inputmode="numeric" placeholder="jawaban" style="max-width:150px">
                </div>
              </div>
              <div>
                <label class="f" style="display:flex;gap:9px;align-items:flex-start;font-weight:600">
                  <input type="checkbox" name="setuju" value="1" required style="width:16px;height:16px;margin-top:2px;accent-color:var(--hijau)">
                  <span>Saya menyatakan data yang diisi benar dan menyetujui aplikasi ini digunakan untuk
                    administrasi guru di sekolah/madrasah kami.</span>
                </label>
              </div>
            </div>

            <div class="flex">
              <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> Kirim Pendaftaran</button>
              <a class="btn btn-ghost" href="login.php">Saya sudah punya akun</a>
            </div>
            <p class="hint" style="margin:0">Pendaftaran diperiksa Super Admin. Login baru dapat dipakai setelah
              pendaftaran <b>disetujui</b>.</p>
          </form>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-head">
          <div><h2>Apa yang Anda dapatkan</h2><p>Satu aplikasi untuk administrasi guru, dipakai banyak sekolah/madrasah.</p></div>
        </div>
        <div class="grid g3" style="gap:12px">
          <div class="langkah">
            <span class="langkah-ico"><?= ikon('users', 16) ?></span>
            <div><b>Data guru &amp; siswa</b><span>Kelas yang diajar, mapel yang dipegang, impor Excel/CSV</span></div>
          </div>
          <div class="langkah">
            <span class="langkah-ico"><?= ikon('chart', 16) ?></span>
            <div><b>Daftar nilai &amp; hadir</b><span>Formatif, Sumatif Lingkup Materi, dan ASAS berbobot</span></div>
          </div>
          <div class="langkah">
            <span class="langkah-ico"><?= ikon('book', 16) ?></span>
            <div><b>Jurnal &amp; catatan pelanggaran</b><span>Jurnal harian (S/I/A/B/T) dan catatan pelanggaran siswa</span></div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="daftar-foot">
      <div><?= e(APP_NAME) ?> — <?= e(APP_SUB) ?> Versi <?= e(APP_VERSION) ?></div>
      <div class="mt8" style="font-weight:600;color:var(--ink)">Dikembangkan oleh <?= e(APP_AUTHOR) ?> (<?= e(APP_AUTHOR_JABATAN) ?>)</div>
    </div>
  </div>
</div>
</body>
</html>
