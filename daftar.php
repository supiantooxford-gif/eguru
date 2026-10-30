<?php
/**
 * Pendaftaran sekolah/madrasah MANDIRI (halaman publik) — tata letak mengikuti halaman
 * pendaftaran aplikasi Supervisi Guru (kepala halaman, langkah 1-2-3, bagian bertajuk, kartu sukses).
 *
 * Alur (permintaan pemilik): sekolah mengisi formulir → sekolah LANGSUNG disetujui otomatis
 * (`verifikasi = disetujui`, `aktif = 1`) tetapi berstatus **MASA UJI COBA** (`PENDAFTARAN_HARI_AKTIF`,
 * sekarang 15 hari) — pendaftar dapat langsung masuk dan mencoba seluruh fitur, tanpa
 * menunggu persetujuan Admin. Setelah masa itu berakhir, aplikasi terkunci sampai KODE AKTIVASI
 * lembaga dimasukkan (mekanisme aktivasi yang sudah ada). Mata pelajaran awal, tahun ajaran, dan
 * semester disiapkan otomatis lewat provision_sekolah().
 * Super Admin tetap dapat mengubah statusnya menjadi AKTIF PERMANEN di halaman Pendaftaran /
 * Kelola Sekolah.
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
    // v37: identitas lembaga memuat NSM + alamat per wilayah (menyesuaikan Identitas & Pengaturan).
    // Semua kunci di sini otomatis dibaca dari POST lewat foreach di bawah.
    // v49: `jenjang` = PILIHAN JENJANG (SD/SMP/SMA/SMK atau MI/MTs/MA/MAK) yang wajib dipilih
    // pendaftar; nilainya harus sesuai dengan jenis lembaga yang dipilih.
    'nama' => '', 'jenis' => 'madrasah', 'jenjang' => '', 'npsn' => '', 'nsm' => '', 'alamat' => '',
    'desa' => '', 'kecamatan' => '', 'kabupaten' => '', 'provinsi' => '',
    'telepon' => '', 'email' => '', 'website' => '',
    'kepala_sekolah' => '', 'nip_kepala_sekolah' => '', 'kontak_nama' => '', 'kontak_telepon' => '',
    // v39: identitas PENGAWAS BINA (nama & NIP) — permintaan pemilik.
    'pengawas_nama' => '', 'pengawas_nip' => '',
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
        $err[] = 'Anda sudah mengirim 3 pendaftaran pada sesi ini. Hubungi Admin bila perlu menambah lagi.';
    }
    // KEAMANAN (v1.73.4): batas di atas hanya berbasis SESI, jadi dapat dilewati dengan mengosongkan
    // cookie. Pembatas per ALAMAT IP PUBLIK di bawah menutup celah itu (pembuatan sekolah tanpa
    // henti akan membengkakkan basis data & menghabiskan kuota penyimpanan). IP privat/loopback
    // dilewati supaya pengujian lokal tetap berjalan.
    $ipDaftar = log_ip();
    if (throttle_ip_layak($ipDaftar)
        && throttle_jumlah('daftar:' . $ipDaftar, PUBLIK_DAFTAR_MENIT) >= PUBLIK_DAFTAR_MAKS) {
        $err[] = 'Terlalu banyak pendaftaran dari jaringan ini dalam waktu singkat. '
            . 'Coba lagi dalam ' . PUBLIK_DAFTAR_MENIT . ' menit atau hubungi Admin aplikasi.';
    }
    if ($nilai['nama'] === '') {
        $err[] = 'Nama sekolah/madrasah wajib diisi.';
    }
    // v49: jenjang yang dikirim HARUS berlaku untuk jenis lembaga yang dipilih
    // (Sekolah Umum → SD/SMP/SMA/SMK · Madrasah → MI/MTs/MA/MAK). Jenjang kosong dibiarkan
    // "belum dipilih" (pilihan di formulir sudah bertanda wajib, jadi pengunjung memilihnya) —
    // sekolah seperti itu tetap berjalan seperti sebelumnya & dapat dilengkapi pengelola nanti.
    if ($nilai['jenjang'] !== '' && !jenjang_sah($nilai['jenjang'], $nilai['jenis'])) {
        $err[] = 'Jenjang yang dipilih tidak berlaku untuk jenis lembaga tersebut. Untuk Sekolah Umum: '
            . implode(', ', jenjang_pilihan('umum')) . '; untuk Madrasah: '
            . implode(', ', jenjang_pilihan('madrasah')) . '.';
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
        $err[] = 'Sekolah/madrasah dengan nama itu sudah terdaftar. Hubungi Admin bila ini kekeliruan.';
    }

    // NSM hanya milik MADRASAH (permintaan pemilik): medan ini TIDAK ditampilkan pada jenis
    // "Sekolah Umum", jadi nilainya pun tidak disimpan — sekolah umum memang tidak punya NSM.
    // (Penjagaannya di SERVER supaya tetap berlaku walau JavaScript gagal dimuat.)
    if ($nilai['jenis'] === 'umum') {
        $nilai['nsm'] = '';
    }

    if ($err) {
        captcha_baru();
    } else {
        $now = date('Y-m-d H:i:s');
        db()->exec('BEGIN IMMEDIATE');
        try {
            // Permintaan pemilik: pendaftar langsung DISETUJUI (aktif) dengan MASA UJI COBA
            // (PENDAFTARAN_HARI_AKTIF, sekarang 15 hari),
            // jadi tidak ada lagi pendaftaran yang menunggu keputusan Admin.
            // v37: identitas lembaga memuat NSM + alamat per wilayah (menyesuaikan Identitas & Pengaturan).
            // v39: identitas memuat NAMA + NIP PENGAWAS BINA (permintaan pemilik) — diposisikan
            // tepat di bawah Nama/NIP Kepala Sekolah pada formulir.
            q('INSERT INTO sekolah (nama, npsn, nsm, alamat, desa, kecamatan, kabupaten, provinsi,
                    telepon, email, website, kepala_sekolah, nip_kepala_sekolah,
                    pengawas_nama, pengawas_nip, jenis, jenjang,
                    verifikasi, aktif, kontak_nama, kontak_telepon, kontak_email, didaftar, dibuat, ditinjau)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"disetujui",1,?,?,?,?,?,?)',
                [$nilai['nama'], $nilai['npsn'], $nilai['nsm'], $nilai['alamat'], $nilai['desa'],
                 $nilai['kecamatan'], $nilai['kabupaten'], $nilai['provinsi'],
                 $nilai['telepon'], $nilai['email'], $nilai['website'],
                 $nilai['kepala_sekolah'], nip_bersih($nilai['nip_kepala_sekolah']),
                 $nilai['pengawas_nama'], nip_bersih($nilai['pengawas_nip']),
                 $nilai['jenis'] === 'umum' ? 'umum' : 'madrasah', $nilai['jenjang'],
                 $nilai['kontak_nama'], $nilai['kontak_telepon'], $nilai['kontak_email'], $now, $now, $now]);
            $sid = (int) db()->lastInsertId();
            q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, aktif, dibuat) VALUES (?,?,?,?,?,1,?)',
                [$sid, $nilai['username'], password_hash($sandi, PASSWORD_DEFAULT),
                 $nilai['kontak_nama'] !== '' ? $nilai['kontak_nama'] : $nilai['nama'], 'kepala', $now]);
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        // DI LUAR transaksi (fungsi-fungsi ini menulis sendiri): siapkan mapel/tahun ajaran/semester
        // lalu beri masa aktif PENDAFTARAN_HARI_AKTIF hari supaya pendaftar bisa langsung memakai aplikasi.
        provision_sekolah(db(), $sid);
        aktivasi_uji_coba($sid, PENDAFTARAN_HARI_AKTIF);
        // Permintaan pemilik: nama pengawas bina yang diisi pendaftar OTOMATIS dibuatkan akun pengawas
        // (nonaktif, menunggu ditinjau Super Admin) sekaligus ditetapkan sebagai binaan sekolah ini.
        // Akunnya TIDAK dapat dipakai masuk sampai Super Admin meninjau & mengaktifkannya.
        $pengawasBaru = pengawas_dari_pendaftaran($sid, (string) $nilai['pengawas_nama']);
        $_SESSION['daftar_jumlah'] = (int) $_SESSION['daftar_jumlah'] + 1;
        // Catat pada pembatas per IP hanya pendaftaran yang BENAR-BENAR tersimpan (v1.73.4).
        if (throttle_ip_layak($ipDaftar)) {
            throttle_lewat('daftar:' . $ipDaftar, PUBLIK_DAFTAR_MAKS, PUBLIK_DAFTAR_MENIT);
        }
        unset($_SESSION['daftar_captcha'], $_SESSION['daftar_captcha2']);
        $sukses = [
            'nama' => $nilai['nama'],
            'username' => $nilai['username'],
            'jenis' => ($nilai['jenis'] === 'umum' ? 'Sekolah Umum' : 'Madrasah')
                . ($nilai['jenjang'] !== '' ? ' — jenjang ' . $nilai['jenjang'] : ''),
            'kontak' => $nilai['kontak_telepon'] !== '' ? $nilai['kontak_telepon'] : $nilai['kontak_email'],
            'hari' => PENDAFTARAN_HARI_AKTIF,
            'sampai' => (string) val('SELECT uji_coba_selesai FROM sekolah WHERE id = ?', [$sid], ''),
            // Hanya nama pengawas yang dipakai di kartu sukses — kata sandi akun TIDAK pernah dicetak.
            'pengawas' => $pengawasBaru !== null ? (string) $pengawasBaru['nama'] : '',
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
<?php // Favicon & logo memakai LOGO APLIKASI bila Super Admin sudah mengunggahnya (menu "Logo Aplikasi"). ?>
<?php if (ada_logo_aplikasi()): ?>
<link rel="icon" href="<?= e(logo_aplikasi()) ?>">
<?php else: ?>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>&#128218;</text></svg>">
<?php endif; ?>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="daftar-wrap">
  <div class="daftar-inner">

    <div class="daftar-head">
      <?php // Logo aplikasi (menu Super Admin "Logo Aplikasi") bila sudah diunggah; kalau belum tetap
            // lambang bawaan. Kelas `daftar-logo` dipertahankan supaya tata letaknya tidak berubah. ?>
      <?php if (ada_logo_aplikasi()): ?>
        <div class="daftar-logo daftar-logo-img"><img src="<?= e(logo_aplikasi()) ?>" alt="Logo <?= e(APP_NAME) ?>"></div>
      <?php else: ?>
        <div class="daftar-logo"><?= ikon('mosque', 28) ?></div>
      <?php endif; ?>
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
          <h3>Pendaftaran berhasil &amp; langsung aktif</h3>
          <p>Terima kasih. Pendaftaran <b><?= e($sukses['nama']) ?></b> (<?= e($sukses['jenis']) ?>) sudah kami terima
            dan <b>langsung aktif selama <?= (int) $sukses['hari'] ?> hari</b> — Anda dapat masuk
            <b>sekarang</b> memakai akun yang baru didaftarkan, tanpa menunggu persetujuan.</p>
        </div>

        <div class="alert alert-ok" style="margin-bottom:16px">
          <?= ikon('clock', 16) ?>
          <span>Masa aktif: <b><?= (int) $sukses['hari'] ?> hari</b><?= $sukses['sampai'] !== '' ? ', sampai <b>' . e(tanggal_id((string) $sukses['sampai'], true)) . '</b>' : '' ?>.
            Setelah masa itu berakhir, aplikasi terkunci sampai <b>kode aktivasi lembaga</b> dimasukkan
            (Admin aplikasi menyediakannya).</span>
        </div>

        <div class="grid g3" style="gap:12px">
          <div class="langkah">
            <i>1</i>
            <div><b>Masuk sekarang</b><span>Gunakan nama pengguna &amp; kata sandi yang baru Anda isi.</span></div>
          </div>
          <div class="langkah">
            <i>2</i>
            <div><b>Lihat fiturnya</b><span>Dashboard, data guru &amp; siswa, nilai, daftar hadir, jurnal, notulen.</span></div>
          </div>
          <div class="langkah">
            <i>3</i>
            <div><b>Siap dipakai</b><span><?= count(mapel_awal()) ?> mata pelajaran, tahun ajaran, dan semester
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
          <p class="hint" style="margin-bottom:0">Simpan nama pengguna dan kata sandi Anda. Bila lupa, minta Admin
            mengatur ulang.</p>
        </div>

        <div class="divider"></div>
        <p class="sm mut" style="margin:0">
          <b>Pendaftaran Gratis (Aktif <?= (int) PENDAFTARAN_HARI_AKTIF ?> Hari).</b> Setelah melakukan pendaftaran silahkan masuk menggunakan
          akun yang telah anda daftarkan untuk melihat secara langsung berbagai fitur yang tersedia.
          Bila ada kendala, hubungi Admin<?= $sukses['kontak'] !== '' ? ' (kontak pendaftaran Anda: ' . e($sukses['kontak']) . ')' : '' ?>.
        </p>
        <?php // Permintaan pemilik: nama pengawas bina diteruskan menjadi akun pengawas yang menunggu
              // ditinjau Admin. Keterangannya memakai istilah "sekolah/madrasah" (aturan halaman ini)
              // dan TIDAK menyebut kata sandi akun pengawas tersebut. ?>
        <?php if ($sukses['pengawas'] !== ''): ?>
          <p class="sm mut" style="margin:8px 0 0">
            <b>Nama Pengawas Bina</b> yang Anda isi (<b><?= e($sukses['pengawas']) ?></b>) sudah diteruskan
            ke Admin untuk ditinjau. Akun pengawas bina tersebut baru dapat dipakai setelah Admin
            meninjau dan mengaktifkannya.
          </p>
        <?php endif; ?>

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

      <?php // Permintaan pemilik: keterangan masa aktif (PENDAFTARAN_HARI_AKTIF, sekarang 15 hari)
            // ditampilkan mencolok pada halaman pendaftaran (pendaftar langsung disetujui otomatis
            // dengan masa uji coba). ?>
      <div class="alert alert-ok daftar-gratis" style="margin-bottom:18px">
        <?= ikon('star', 16) ?>
        <span><b>Pendaftaran Gratis (Aktif <?= (int) PENDAFTARAN_HARI_AKTIF ?> Hari).</b>
          Setelah melakukan pendaftaran silahkan masuk menggunakan akun yang telah anda daftarkan untuk
          melihat secara langsung berbagai fitur yang tersedia.</span>
      </div>

      <div class="card">
        <div class="card-head">
          <div>
            <h2>Formulir Pendaftaran</h2>
            <p>Isi data sekolah/madrasah dan akun pengelolanya. Setelah dikirim, akun
              <b>langsung dapat dipakai</b> selama <?= (int) PENDAFTARAN_HARI_AKTIF ?> hari.</p>
          </div>
        </div>

        <div class="grid g3 mb16" style="gap:12px">
          <div class="langkah"><i>1</i><div><b>Isi formulir</b><span>Data sekolah/madrasah + akun pengelola</span></div></div>
          <div class="langkah"><i>2</i><div><b>Aktif otomatis</b><span>Langsung aktif <?= (int) PENDAFTARAN_HARI_AKTIF ?> hari, tanpa menunggu persetujuan</span></div></div>
          <div class="langkah"><i>3</i><div><b>Masuk &amp; mulai</b><span>Mapel, tahun ajaran &amp; semester disiapkan</span></div></div>
        </div>

        <?php if ($sisa <= 0): ?>
          <div class="alert alert-warn" style="margin-bottom:0">
            <?= ikon('alert', 16) ?>
            <span>Batas 3 pendaftaran per sesi sudah tercapai. Hubungi Admin untuk pendaftaran berikutnya.</span>
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
                    <option value="madrasah" <?= $nilai['jenis'] === 'madrasah' ? 'selected' : '' ?>>Madrasah (MI/MTs/MA/MAK)</option>
                    <option value="umum" <?= $nilai['jenis'] === 'umum' ? 'selected' : '' ?>>Sekolah Umum (SD/SMP/SMA/SMK)</option>
                  </select>
                  <div class="hint">Menentukan istilah pada aplikasi &amp; dokumen cetak sekolah tersebut.</div>
                </div>
                <?php // v49: PILIHAN JENJANG — pilihannya mengikuti jenis lembaga yang dipilih
                      // (Sekolah Umum → SD/SMP/SMA/SMK · Madrasah → MI/MTs/MA/MAK). Server memeriksa
                      // keabsahannya, dan initJenisJenjang() menyusun ulang pilihan saat jenis diganti
                      // sehingga tetap benar walau JavaScript gagal dimuat (pilihan awal dari server). ?>
                <div>
                  <label class="f" for="jenjang">Jenjang</label>
                  <select id="jenjang" name="jenjang"
                          data-jenjang="<?= e(json_encode(JENJANG_PILIHAN, JSON_UNESCAPED_UNICODE)) ?>"
                          data-label="<?= e(json_encode(JENJANG_LABEL, JSON_UNESCAPED_UNICODE)) ?>">
                    <option value="">— pilih jenjang —</option>
                    <?php foreach (jenjang_pilihan($nilai['jenis']) as $jj): ?>
                      <option value="<?= e($jj) ?>" <?= $nilai['jenjang'] === $jj ? 'selected' : '' ?>><?= e(JENJANG_LABEL[$jj] ?? $jj) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="hint">Menentukan acuan tingkat kelas &amp; konsep guru kelas (SD/MI).
                    Boleh dikosongkan dan dilengkapi kemudian oleh pengelola lembaga.</div>
                </div>
                <div>
                  <label class="f" for="npsn">NPSN</label>
                  <input type="text" id="npsn" name="npsn" value="<?= e($nilai['npsn']) ?>" placeholder="8 digit">
                </div>
                <?php // NSM hanya dimiliki MADRASAH — medan ini disembunyikan saat jenisnya
                      // "Sekolah Umum" (permintaan pemilik). Atribut `hidden` sudah dicetak server
                      // saat halaman dirender ulang dengan jenis umum, jadi tanpa JavaScript pun
                      // keadaan awalnya tetap benar; skrip initJenisNsm() hanya mengikuti pilihan. ?>
                <div id="blokNsm"<?= $nilai['jenis'] === 'umum' ? ' hidden' : '' ?>>
                  <label class="f" for="nsm">NSM <span class="mut xs">(madrasah)</span></label>
                  <input type="text" id="nsm" name="nsm" value="<?= e($nilai['nsm']) ?>"
                         placeholder="Nomor Statistik Madrasah">
                </div>
                <div>
                  <label class="f" for="telepon">Telepon Sekolah/Madrasah</label>
                  <input type="text" id="telepon" name="telepon" value="<?= e($nilai['telepon']) ?>" placeholder="08xxxxxxxxxx">
                </div>
                <div style="grid-column:1/-1">
                  <label class="f" for="alamat">Alamat (Nama Jalan, Dusun)</label>
                  <input type="text" id="alamat" name="alamat" value="<?= e($nilai['alamat']) ?>"
                         placeholder="mis. Jl. Dusun Gandaria">
                </div>
                <div>
                  <label class="f" for="desa">Desa/Kelurahan</label>
                  <input type="text" id="desa" name="desa" value="<?= e($nilai['desa']) ?>">
                </div>
                <div>
                  <label class="f" for="kecamatan">Kecamatan</label>
                  <input type="text" id="kecamatan" name="kecamatan" value="<?= e($nilai['kecamatan']) ?>">
                  <div class="hint">Menjadi <b>tempat</b> pada baris "tempat, tanggal" tanda tangan dokumen cetak.</div>
                </div>
                <div>
                  <label class="f" for="kabupaten">Kabupaten</label>
                  <input type="text" id="kabupaten" name="kabupaten" value="<?= e($nilai['kabupaten']) ?>">
                </div>
                <div>
                  <label class="f" for="provinsi">Provinsi</label>
                  <input type="text" id="provinsi" name="provinsi" value="<?= e($nilai['provinsi']) ?>">
                </div>
                <div>
                  <label class="f" for="email">Email Sekolah/Madrasah</label>
                  <input type="text" id="email" name="email" value="<?= e($nilai['email']) ?>" placeholder="nama@email.com">
                </div>
                <div>
                  <label class="f" for="website">Situs/laman</label>
                  <input type="text" id="website" name="website" value="<?= e($nilai['website']) ?>" placeholder="www.sekolah.sch.id">
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
                <?php // v39: identitas PENGAWAS BINA (nama & NIP) — permintaan pemilik: letaknya
                      // "dibawah nama kepala sekolah" (langsung di bawah Nama/NIP Kepala). ?>
                <div>
                  <label class="f" for="pengawas_nama">Nama Pengawas Bina</label>
                  <input type="text" id="pengawas_nama" name="pengawas_nama" value="<?= e($nilai['pengawas_nama']) ?>"
                         placeholder="nama &amp; gelar">
                </div>
                <div>
                  <label class="f" for="pengawas_nip">NIP Pengawas Bina</label>
                  <input type="text" id="pengawas_nip" name="pengawas_nip" value="<?= e($nilai['pengawas_nip']) ?>"
                         placeholder="boleh dikosongkan">
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
              <div class="hint">Isi minimal salah satu (HP/WhatsApp atau email) agar Admin dapat menghubungi Anda.</div>
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
              <div class="hint">Akun ini berperan sebagai <b>Kepala Sekolah/Madrasah</b>. Setelah masuk,
                akun <b>Admin Operator</b> dapat ditambahkan dari dalam aplikasi (menu Akun Operator).</div>
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
            <p class="hint" style="margin:0">Pendaftaran diperiksa Admin. Login baru dapat dipakai setelah
              pendaftaran <b>disetujui</b>.</p>
          </form>
        <?php endif; ?>
      </div>

    <?php endif; ?>

    <div class="daftar-foot">
      <div><?= e(APP_NAME) ?> — <?= e(APP_SUB) ?> Versi <?= e(APP_VERSION) ?></div>
      <div class="mt8" style="font-weight:600;color:var(--ink)">Dikembangkan oleh <?= e(APP_AUTHOR) ?> (<?= e(APP_AUTHOR_JABATAN) ?>)</div>
    </div>
  </div>
</div>
<?php // Halaman pendaftaran ikut memuat assets/app.js (sama seperti login.php) karena dipakai
      // untuk menyembunyikan medan NSM saat jenis "Sekolah Umum" (initJenisNsm()). Skripnya hanya
      // bekerja pada elemen yang ada di halaman, dan tanpa JavaScript pun keadaan awal tetap benar
      // karena atribut `hidden` sudah dicetak server. ?>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
