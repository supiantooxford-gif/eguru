<?php
/**
 * HALAMAN DATA LEMBAGA (publik) — sasaran KODE QR pada Kartu Pelajar. v1.71.0
 *
 * Permintaan pemilik: *"Pada bagian cetak kartu pelajar tambahkan barcode … Barcode ketika di
 * scan menampilkan data Lembaga/sekolah"*. Kode QR pada kartu berisi alamat halaman INI
 * (`lembaga.php?k=<id>-<token>`), sehingga memindai kartu dengan kamera HP langsung membuka
 * identitas sekolah/madrasah pemilik kartu.
 *
 * Halaman ini TIDAK memerlukan login (pemindai bisa siapa saja), karena itu:
 *  • hanya menampilkan DATA LEMBAGA (nama, NPSN/NSM, alamat, kontak, kepala, tahun ajaran,
 *    jumlah guru/siswa/kelas) — TIDAK ada data pribadi siswa (nama/NISN/nilai) maupun akun;
 *  • alamatnya memakai TOKEN rahasia (HMAC id sekolah) sehingga id sekolah tidak bisa ditebak;
 *  • hanya sekolah AKTIF + DISETUJUI yang ditampilkan;
 *  • diberi `noindex` supaya tidak diindeks mesin pencari.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

// Halaman ini lintas sekolah (tidak memakai konteks sekolah) → istilah generik.
$GLOBALS['__vs_istilah_nonaktif'] = true;

$kode = (string) ($_GET['k'] ?? '');
$sekolahId = lembaga_param_sah($kode);
$s = $sekolahId > 0
    ? one("SELECT * FROM sekolah WHERE id = ? AND aktif = 1 AND verifikasi = 'disetujui'", [$sekolahId])
    : null;

/**
 * Nilai identitas yang layak dicetak ('' untuk kosong / '-' / '–' / '—' / '.' / '_').
 */
$isi = static function ($v): string {
    $v = trim((string) $v);
    if ($v === '' || in_array($v, ['-', '–', '—', '.', '_'], true)) {
        return '';
    }
    return $v;
};

$madrasah = $s !== null && strtolower((string) ($s['jenis'] ?? 'umum')) === 'madrasah';
$istilah = $madrasah ? 'Madrasah' : 'Sekolah';
$jenjang = $s !== null ? trim((string) ($s['jenjang'] ?? '')) : '';
$jenjangLabel = ($jenjang !== '' && isset(JENJANG_LABEL[$jenjang])) ? JENJANG_LABEL[$jenjang] : '';

// ---------------------------------------------------------------- statistik ringkas lembaga
$jmlGuru = 0;
$jmlSiswa = 0;
$jmlKelas = 0;
$namaTa = '';
$namaSem = '';
if ($s !== null) {
    $sid = (int) $s['id'];
    $jmlGuru = (int) val('SELECT COUNT(*) FROM guru WHERE sekolah_id = ? AND aktif = 1', [$sid], 0);
    $jmlSiswa = (int) val("SELECT COUNT(*) FROM siswa WHERE sekolah_id = ? AND aktif = 1
        AND (status = 'aktif' OR status IS NULL)", [$sid], 0);
    $jmlKelas = (int) val('SELECT COUNT(*) FROM kelas WHERE sekolah_id = ? AND aktif = 1', [$sid], 0);
    if ((int) ($s['ta_aktif_id'] ?? 0) > 0) {
        $namaTa = trim((string) val('SELECT nama FROM tahun_ajaran WHERE id = ? AND sekolah_id = ?',
            [(int) $s['ta_aktif_id'], $sid], ''));
    }
    if ((int) ($s['semester_aktif_id'] ?? 0) > 0) {
        $namaSem = trim((string) val('SELECT sm.nama FROM semester sm
            JOIN tahun_ajaran ta ON ta.id = sm.ta_id
            WHERE sm.id = ? AND ta.sekolah_id = ?',
            [(int) $s['semester_aktif_id'], $sid], ''));
    }
}

// Baris identitas: label => nilai (baris kosong dilewati supaya tidak ada "Alamat: -").
$alamat = $s !== null ? $isi($s['alamat'] ?? '') : '';
$wilayah = [];
if ($s !== null) {
    foreach ([
        'Desa/Kelurahan' => $s['desa'] ?? '',
        'Kecamatan'      => $s['kecamatan'] ?? '',
        'Kabupaten'      => $s['kabupaten'] ?? '',
        'Provinsi'       => $s['provinsi'] ?? '',
    ] as $label => $nilai) {
        $v = $isi($nilai);
        if ($v !== '') {
            $wilayah[] = $label . ': ' . $v;
        }
    }
}
$identitas = [];
if ($s !== null) {
    $identitas['NPSN'] = $isi($s['npsn'] ?? '');
    // NSM hanya dimiliki madrasah (sekolah umum tidak punya) — sama seperti aturan pendaftaran.
    if ($madrasah) {
        $identitas['NSM'] = $isi($s['nsm'] ?? '');
    }
    $identitas['Alamat'] = $alamat;
    foreach ($wilayah as $baris) {
        [$label, $nilai] = explode(': ', $baris, 2);
        $identitas[$label] = $nilai;
    }
    $identitas['Telepon / HP'] = $isi($s['telepon'] ?? '');
    $identitas['Email'] = $isi($s['email'] ?? '');
    $identitas['Situs / Laman'] = $isi($s['website'] ?? '');
    $identitas['Kepala ' . $istilah] = $isi($s['kepala_sekolah'] ?? '');
    $identitas['NIP Kepala ' . $istilah] = $isi($s['nip_kepala_sekolah'] ?? '');
    $identitas['Tahun Ajaran'] = $namaTa;
    $identitas['Semester'] = $namaSem;
    $identitas = array_filter($identitas, static fn($v) => trim((string) $v) !== '');
}
$logo = $s !== null ? $isi($s['logo'] ?? '') : '';
$namaLembaga = $s !== null ? ($isi($s['nama'] ?? '') !== '' ? (string) $s['nama'] : '-') : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Data <?= e($istilah) ?> — <?= e($namaLembaga !== '' ? $namaLembaga : APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="lembaga-wrap">
  <?php if ($s === null): ?>
    <div class="card lembaga-kartu lembaga-kosong">
      <div class="lembaga-emblem"><?= ikon('school', 26) ?></div>
      <h1>Data lembaga tidak ditemukan</h1>
      <p>Kode pada barcode/kartu tidak dikenali, atau lembaga ini tidak lagi aktif. Silakan
        hubungi sekolah/madrasah yang menerbitkan kartu.</p>
      <a class="btn btn-ghost" href="login.php">Ke halaman masuk</a>
    </div>
  <?php else: ?>
    <div class="card lembaga-kartu">
      <div class="lembaga-kepala">
        <?php if ($logo !== ''): ?>
          <img class="lembaga-logo" src="<?= e($logo) ?>" alt="Logo <?= e($namaLembaga) ?>">
        <?php else: ?>
          <div class="lembaga-emblem"><?= ikon('school', 26) ?></div>
        <?php endif; ?>
        <div class="lembaga-judul">
          <div class="lembaga-label">Data <?= e($istilah) ?></div>
          <h1><?= e($namaLembaga) ?></h1>
          <div class="chip-list">
            <span class="chip on"><?= e($istilah) ?></span>
            <?php if ($jenjangLabel !== ''): ?>
              <span class="chip"><?= e($jenjangLabel) ?></span>
            <?php endif; ?>
            <span class="chip">Terverifikasi</span>
          </div>
        </div>
      </div>

      <div class="lembaga-stat">
        <div class="lembaga-stat-item"><b><?= (int) $jmlGuru ?></b><span>Guru</span></div>
        <div class="lembaga-stat-item"><b><?= (int) $jmlSiswa ?></b><span>Siswa</span></div>
        <div class="lembaga-stat-item"><b><?= (int) $jmlKelas ?></b><span>Kelas</span></div>
      </div>

      <?php if ($identitas): ?>
        <table class="tbl lembaga-tabel">
          <tbody>
          <?php foreach ($identitas as $label => $nilai): ?>
            <tr>
              <th scope="row"><?= e((string) $label) ?></th>
              <td><?php
                // Situs/laman & email dibuat dapat diklik; tautan hanya http/https (tautan_aman()).
                if ($label === 'Situs / Laman') {
                    $aman = tautan_aman((string) $nilai);
                    echo $aman !== '' ? '<a href="' . e($aman) . '" target="_blank" rel="noopener noreferrer">'
                        . e((string) $nilai) . '</a>' : e((string) $nilai);
                } elseif ($label === 'Email' && str_contains((string) $nilai, '@')) {
                    echo '<a href="mailto:' . e((string) $nilai) . '">' . e((string) $nilai) . '</a>';
                } else {
                    echo e((string) $nilai);
                }
              ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="mut">Identitas lengkap lembaga ini belum diisi oleh pengelola.</p>
      <?php endif; ?>

      <p class="lembaga-ket">
        Halaman ini dibuka dari <b>barcode/QR Kartu Pelajar</b>. Data ditampilkan langsung dari
        aplikasi <?= e(APP_NAME) ?> milik lembaga — tidak memuat data pribadi siswa.
      </p>
    </div>
  <?php endif; ?>

  <p class="lembaga-foot"><?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?></p>
</div>
</body>
</html>
