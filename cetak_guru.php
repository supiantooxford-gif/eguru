<?php
/**
 * Lembar cetak DAFTAR DATA GURU (A4 landscape).
 *
 * Akses: Super Admin (dalam konteks sekolah) & Admin Operator — sama dengan tombol cetaknya
 * di guru.php. Filter mengikuti halaman Data Guru (cari, mapel, kelas, akun, status), sehingga
 * lembar yang tercetak sama dengan yang tampil di layar.
 *
 * Kolom "Akun" memuat NAMA PENGGUNA saja — kata sandi disimpan sebagai hash sehingga
 * TIDAK BISA dicetak/ditampilkan.
 *
 * Tanda tangan: hanya Kepala Sekolah/Madrasah, di sisi KANAN lembar.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$u = require_lihat_cetak(); // super admin tanpa konteks dialihkan ke Kelola Sekolah; kepala & guru 403

// ---------------------------------------------------------------- filter (sama dengan guru.php)
$cari = trim((string) ($_GET['cari'] ?? ''));
$fStatus = (string) ($_GET['status'] ?? '');
$fMapel = (int) ($_GET['mapel'] ?? 0);
$fKelas = (int) ($_GET['kelas'] ?? 0);
$fAkun = (string) ($_GET['akun'] ?? '');

$w = ['g.sekolah_id = ?'];
$p = [sekolah_id()];
if ($cari !== '') {
    $w[] = '(g.nama LIKE ? OR g.nip LIKE ? OR g.nuptk LIKE ? OR g.jabatan LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($p, $like, $like, $like, $like);
}
if (in_array($fStatus, ['1', '0'], true)) {
    $w[] = 'g.aktif = ' . (int) $fStatus;
}
if ($fMapel > 0) {
    $w[] = 'EXISTS (SELECT 1 FROM guru_mapel gm WHERE gm.guru_id = g.id AND gm.mapel_id = ?)';
    $p[] = $fMapel;
}
if ($fKelas > 0) {
    $w[] = 'EXISTS (SELECT 1 FROM guru_kelas gk WHERE gk.guru_id = g.id AND gk.kelas_id = ?)';
    $p[] = $fKelas;
}
if ($fAkun === 'punya') {
    $w[] = 'EXISTS (SELECT 1 FROM users us WHERE us.guru_id = g.id)';
} elseif ($fAkun === 'belum') {
    $w[] = 'NOT EXISTS (SELECT 1 FROM users us WHERE us.guru_id = g.id)';
}
$where = ' WHERE ' . implode(' AND ', $w);

$rows = all('SELECT g.*,
    (SELECT GROUP_CONCAT(m.nama, "||") FROM guru_mapel gm JOIN mapel m ON m.id = gm.mapel_id WHERE gm.guru_id = g.id) AS mapel_nama,
    (SELECT GROUP_CONCAT(k.nama, "||") FROM guru_kelas gk JOIN kelas k ON k.id = gk.kelas_id WHERE gk.guru_id = g.id) AS kelas_nama,
    (SELECT GROUP_CONCAT(t.nama, "||") FROM guru_tugas gt JOIN tugas_menu t ON t.id = gt.tugas_id WHERE gt.guru_id = g.id) AS tugas_nama,
    (SELECT username FROM users us WHERE us.guru_id = g.id LIMIT 1) AS username
    FROM guru g' . $where . ' ORDER BY g.nama', $p);

if (!$rows) {
    flash('Belum ada data guru untuk dicetak pada filter yang dipilih.', 'warn');
    redirect('guru.php');
}

$aktif = 0;
$punyaAkun = 0;
foreach ($rows as $g) {
    if ((int) $g['aktif'] === 1) {
        $aktif++;
    }
    if (trim((string) ($g['username'] ?? '')) !== '') {
        $punyaAkun++;
    }
}

$qPrint = array_filter([
    'cari' => $cari !== '' ? $cari : null,
    'mapel' => $fMapel > 0 ? $fMapel : null,
    'kelas' => $fKelas > 0 ? $fKelas : null,
    'akun' => $fAkun !== '' ? $fAkun : null,
    'status' => $fStatus !== '' ? $fStatus : null,
]);
$autoPrint = isset($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(istilah_sekolah('Data Guru')) ?> — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:1160px;margin:0 auto}
  .toolbar{max-width:1160px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  table.sheet.guru th,table.sheet.guru td{padding:5px 6px}
  .ket-print{font-size:.72rem;color:#635F82;margin-top:10px;line-height:1.5}
  @media (max-width:900px){
    .print-sheet{padding:18px 14px}
    table.sheet.guru{min-width:900px}
    .sheet-meta{grid-template-columns:minmax(0,1fr)}
  }
  @media print{
    /* PENTING: tema menetapkan body{min-height:100vh}. Saat MENCETAK, 100vh = tinggi kertas,
       sehingga tinggi dokumen menjadi setinggi halaman dan peramban mencetak halaman KEDUA yang
       hampir kosong. Karena itu min-height dimatikan pada media cetak. */
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .sheet-wrap{max-width:none}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
    .sheet-scroll{overflow:visible}
    table.sheet.guru{min-width:0}
  }
  @page{size:A4 landscape;margin:12mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="guru.php?<?= e(http_build_query($qPrint)) ?>">Kembali</a>
  <a class="btn btn-primary" href="cetak_guru.php?<?= e(http_build_query(array_filter($qPrint + ['auto' => 1]))) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak(istilah_sekolah('Daftar Data Guru'), ['Administrasi Kepegawaian ' . istilah_sekolah('Sekolah')]); ?>

    <?php sheet_meta([
        'Jumlah Data'    => (string) count($rows) . ' (aktif ' . $aktif . ')',
        'Sudah Berakun'  => (string) $punyaAkun,
        'Mata Pelajaran' => $fMapel > 0 ? mapel_nama($fMapel) : 'Semua mata pelajaran',
        'Kelas Diajar'   => $fKelas > 0 ? kelas_nama($fKelas) : 'Semua kelas',
        'Akun Login'     => $fAkun === 'punya' ? 'Sudah punya akun' : ($fAkun === 'belum' ? 'Belum punya akun' : 'Semua'),
        'Status'         => in_array($fStatus, ['1', '0'], true) ? ((int) $fStatus === 1 ? 'Aktif' : 'Nonaktif') : 'Semua status',
    ]); ?>

    <div class="sheet-scroll">
      <table class="sheet guru padat">
        <thead>
          <tr>
            <th class="ctr" style="width:26px">No</th>
            <th style="width:17%">Nama Guru</th>
            <th style="width:10%">NIP / NUPTK</th>
            <th class="ctr" style="width:5%">JK</th>
            <th style="width:10%">Jabatan</th>
            <th style="width:17%">Mata Pelajaran</th>
            <th style="width:10%">Kelas Diajar</th>
            <th style="width:12%">Tugas Tambahan</th>
            <th style="width:10%">Kontak</th>
            <th style="width:8%">Akun (Nama Pengguna)</th>
            <th class="ctr" style="width:7%">Status</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($rows as $g):
            $mapelArr = $g['mapel_nama'] ? explode('||', (string) $g['mapel_nama']) : [];
            $kelasArr = $g['kelas_nama'] ? explode('||', (string) $g['kelas_nama']) : [];
            $tugasArr = $g['tugas_nama'] ? explode('||', (string) $g['tugas_nama']) : [];
            $kontak = [];
            if (trim((string) ($g['telepon'] ?? '')) !== '') {
                $kontak[] = (string) $g['telepon'];
            }
            if (trim((string) ($g['email'] ?? '')) !== '') {
                $kontak[] = (string) $g['email'];
            }
            $n++;
        ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e((string) $g['nama']) ?></b></td>
            <td><?= e((string) ($g['nip'] ?: '-')) ?><?php if (trim((string) ($g['nuptk'] ?? '')) !== ''): ?><br><span style="font-size:.7rem">NUPTK <?= e((string) $g['nuptk']) ?></span><?php endif; ?></td>
            <td class="ctr"><?= e((string) ($g['jenis_kelamin'] ?: '-')) ?></td>
            <td><?= e((string) ($g['jabatan'] ?: '-')) ?></td>
            <td><?= $mapelArr ? e(implode(', ', $mapelArr)) : '-' ?></td>
            <td><?= $kelasArr ? e(implode(', ', $kelasArr)) : '-' ?></td>
            <td><?= $tugasArr ? e(implode(', ', $tugasArr)) : '-' ?></td>
            <td><?= $kontak ? e(implode(' / ', $kontak)) : '-' ?></td>
            <td><?= trim((string) ($g['username'] ?? '')) !== '' ? e((string) $g['username']) : '-' ?></td>
            <td class="ctr"><?= (int) $g['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="ket-print">
      Keterangan: kolom <b>Akun (Nama Pengguna)</b> adalah nama pengguna untuk masuk aplikasi.
      Kata sandi disimpan dalam bentuk teracak (hash) sehingga <b>tidak dapat dicetak</b>; bila guru
      lupa sandinya, Admin Operator dapat meresetnya lewat tombol <b>Reset sandi</b> pada halaman
      <b>Data Guru</b> (sandi hasil reset: <b>guru123</b>, lalu guru membuat sandi baru sendiri saat
      masuk), atau Super Admin menetapkan sandi baru lewat menu <b>Pengguna &amp; Akun</b>.
    </div>

    <!-- Tanda tangan: hanya kepala sekolah/madrasah, di sisi KANAN lembar (kelas .kanan). -->
    <div class="sheet-foot kanan">
      <?php ttd_blok(ttd_mengetahui(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kanan'); ?>
    </div>

    <p class="xs mut center" style="margin-top:18px">
      Dicetak dari <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> pada <?= e(tanggal_id(date('Y-m-d'))) ?>.
    </p>
  </div>
</div>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
<?php terjemahkan_selesai(); ?>
