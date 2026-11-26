<?php
/**
 * LEMBAR CETAK — MATERI/TUGAS DARI GURU (A4 portrait) untuk akun siswa — v1.113.0 / skema v73.
 *
 * Permintaan pemilik: materi/tugas yang dikirim guru masuk ke akun siswa dan berkasnya
 * *"bisa di download dan dicetak"*.
 *
 * Isi lembar: kop sekolah/madrasah + identitas materi/tugas (judul, mata pelajaran, GURU PENGIRIM,
 * kelas, berkas, waktu buka & batas waktu) + keterangan guru. Bila berkasnya GAMBAR, gambarnya ikut
 * dicetak (itu inti "bisa dicetak"); bila PDF/Word, lembar ini menerangkan bahwa berkas dicetak dari
 * penampilnya setelah diunduh.
 *
 * 🚨 Hak akses diperiksa di SERVER: hanya siswa PENERIMA materi itu, dan hanya selama materi/tugasnya
 * masih `buka` (lihat materi_keadaan()) — materi yang belum waktunya dibuka atau sudah lewat batas
 * waktu ditolak 403, sama seperti tombol Unduh pada halaman siswa.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$sw = require_siswa();
$id = (int) ($_GET['id'] ?? 0);
$m = materi_terkirim_untuk($id, (int) $sw['id']);
if (!$m) {
    tolak403('Materi/tugas itu tidak ditujukan kepada akun Anda.');
}
$keadaan = materi_keadaan($m);
if ($keadaan !== 'buka') {
    tolak403($keadaan === 'belum'
        ? 'Materi/tugas ini belum waktunya dibuka. Silakan buka kembali pada waktu yang ditetapkan '
            . 'guru mata pelajaran Anda (' . materi_waktu_teks((string) $m['buka_mulai']) . ').'
        : 'Materi/tugas ini sudah melewati batas waktu yang ditetapkan guru mata pelajaran Anda ('
            . materi_waktu_teks((string) $m['batas_waktu']) . ').');
}
$autoPrint = isset($_GET['auto']);
$gambar = materi_gambar($m);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Materi/Tugas — <?= e((string) $m['judul']) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:900px;margin:0 auto}
  .toolbar{max-width:900px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .mt-ket{border:1px solid #cfe0d6;border-radius:10px;padding:10px 12px;background:#f6faf7;margin:12px 0 0}
  .mt-berkas{border:1px dashed #b6d5c4;border-radius:10px;padding:12px;margin-top:14px;
    display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
  .mt-gambar{margin-top:14px;text-align:center}
  .mt-gambar img{max-width:100%;max-height:220mm;border:1px solid #d8e3dc;border-radius:8px}
  @media print{
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
  }
  @page{size:A4 portrait;margin:14mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="materi_saya.php">Kembali</a>
  <a class="btn btn-ghost" href="<?= e((string) $m['url']) ?>" target="_blank" rel="noopener">
    <?= ikon('download', 16) ?> Unduh Berkas</a>
  <a class="btn btn-primary" href="cetak_materi.php?id=<?= (int) $m['id'] ?>&amp;auto=1">
    <?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Materi/Tugas', ['Dokumen Pembelajaran untuk Siswa']); ?>

    <?php sheet_meta([
        'Judul Materi/Tugas' => (string) $m['judul'],
        'Mata Pelajaran'     => (string) $m['mapel_nama'],
        'Guru Pengirim'      => (string) $m['guru_nama'],
        'Kelas'              => (string) ($m['kelas_teks'] !== '' ? $m['kelas_teks'] : '-'),
        // Identitas SISWA yang mencetak — supaya lembar ini sah sebagai bukti penerimaan materi/tugas.
        'Nama Siswa'         => (string) $sw['nama'],
        'NISN'               => (string) ($sw['nisn'] !== '' ? $sw['nisn'] : '-'),
        'Kelas Siswa'        => (string) ($sw['kelas_nama'] !== null ? $sw['kelas_nama'] : '-'),
        'Berkas'             => materi_nama_berkas($m) . ' (' . strtoupper(materi_ext($m)) . ', '
                                    . dok_ukuran((int) $m['ukuran']) . ')',
        'Dibuka Mulai'       => trim((string) $m['buka_mulai']) !== ''
                                    ? materi_waktu_teks((string) $m['buka_mulai']) : 'Langsung dibuka',
        'Batas Waktu'        => trim((string) $m['batas_waktu']) !== ''
                                    ? materi_waktu_teks((string) $m['batas_waktu']) : 'Tanpa batas waktu',
        'Diterima'           => tanggal_id(date('Y-m-d', (int) strtotime((string) $m['dibuat']))),
    ]); ?>

    <?php if (trim((string) $m['keterangan']) !== ''): ?>
      <div class="mt-ket">
        <b>Keterangan / Petunjuk dari guru:</b><br>
        <?= tetap_kata((string) $m['keterangan']) ?>
      </div>
    <?php endif; ?>

    <?php if ($gambar): ?>
      <div class="mt-gambar">
        <img src="<?= e((string) $m['url']) ?>" alt="<?= e(materi_nama_berkas($m)) ?>">
      </div>
      <p class="xs mut center" style="margin-top:8px">Berkas gambar: <?= tetap_kata(materi_nama_berkas($m)) ?></p>
    <?php else: ?>
      <div class="mt-berkas">
        <div>
          <b><?= tetap_kata(materi_nama_berkas($m)) ?></b>
          <div class="sm mut">Berkas <?= e(strtoupper(materi_ext($m))) ?> — dokumen ini dicetak dari
            penampil berkas setelah diunduh.</div>
        </div>
        <div class="no-print">
          <a class="btn btn-primary btn-sm" href="<?= e((string) $m['url']) ?>" target="_blank" rel="noopener">
            <?= ikon('download', 14) ?> Buka / Unduh Berkas</a>
        </div>
      </div>
    <?php endif; ?>

    <p class="xs mut" style="margin-top:16px">
      Materi/tugas ini dikirim oleh guru mata pelajaran melalui menu
      <b>Kirim Materi/Tugas Ke Siswa</b> pada <?= e(APP_NAME) ?>. Bila ada pertanyaan tentang isi
      materi/tugas, hubungi guru pengirim atau wali kelas <?= tetap_kata('sekolah/madrasah') ?> Anda.
    </p>

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
