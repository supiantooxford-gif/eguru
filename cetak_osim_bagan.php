<?php
/**
 * LEMBAR CETAK BAGAN SUSUNAN ORGANISASI OSIM/OSIS (A4 landscape) — permintaan pemilik:
 * susunan organisasi selain dalam bentuk tabel juga dibuat dalam bentuk **bagan**.
 *
 * Isi lembar: kop sekolah/madrasah (kop_cetak) + BAGAN (judul, periode, kotak jabatan/seksi) +
 * garis penghubung, ditutup tanda tangan kepala sekolah/madrasah di sisi KANAN (pola lembar cetak
 * lain pada aplikasi ini). Bagan memakai `osim_bagan_html($pengurus, true)` — kepala bagan
 * dilewati karena kop sudah memuat nama sekolah, dan judul+periode dicetak sebagai baris identitas.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/osim.php';

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

// Kode menu dari URL (?kode=pramuka memakai modul yang sama dengan ?kode=osim).
$kode = osim_kode_normal((string) ($_GET['kode'] ?? OSIM_KODE_MENU));
$konfig = osim_menu_konfig($kode);
$tugas = tugas_menu_by_kode($kode);
if (!$tugas) {
    require_login();
    flash('Menu ' . $konfig['menu_bawaan'] . ' belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu($tugas);
require_sekolah();

$pengurus = osim_pengurus_list([], $kode);
if (!$pengurus) {
    flash('Belum ada susunan organisasi untuk dicetak. Isi dulu pada menu Susunan Organisasi.', 'warn');
    redirect('tugas.php?kode=' . $kode . '&bagian=organisasi&tampilan=tabel&tab=data');
}

$baganStat = osim_bagan_statistik(osim_bagan_susun($pengurus));
$autoPrint = isset($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bagan Susunan Organisasi <?= e($konfig['nama_organisasi']) ?> — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:1160px;margin:0 auto}
  .toolbar{max-width:1160px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .bagan-identitas{font-size:.82rem;color:#38474a;margin:2px 0 12px;display:flex;flex-wrap:wrap;gap:4px 18px}
  .bagan-identitas b{color:var(--hijau-tua)}
  .bagan-kaki{display:flex;gap:18px;align-items:flex-start;justify-content:space-between;margin-top:8px}
  .bagan-kaki .ket-print{margin:0;flex:1 1 auto}
  .bagan-kaki .sheet-foot{flex:0 0 auto;margin:0}
  /* Tata letak layar sempit HANYA untuk layar (media "screen"): saat mencetak, lebar kertas
     A4 landscape ±1032px sehingga aturan max-width ikut aktif bila tidak dibatasi. */
  @media screen and (max-width:900px){
    .print-sheet{padding:18px 14px}
    .sheet-meta{grid-template-columns:minmax(0,1fr)}
  }
  @media print{
    /* Tema menetapkan body{min-height:100vh}; saat mencetak 100vh = tinggi kertas sehingga dokumen
       menjadi setinggi halaman dan ikut mencetak halaman kedua yang hampir kosong. */
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .sheet-wrap{max-width:none}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap;justify-content:flex-end}
    /* Pemadatan lembar bagan agar MUAT SATU halaman A4 landscape (tinggi area cetak ±703px pada
       lebar 1032px): kop dirapatkan, identitas satu baris kecil, keterangan+tanda tangan sebaris,
       jarak tanda tangan & jarak baris gambar diperkecil. UKUR ULANG bila tata letak bagan diubah
       (bash cek_bagan_ukur_tmp.sh saat mengembangkan, atau bagian uji cetak bagan). */
    .sheet-head{padding-bottom:4px}
    .sheet-head h2{font-size:.95rem;margin:1px 0 3px}
    .sheet-head .kop-nama{font-size:.86rem}
    .bagan-identitas{font-size:.68rem;margin:2px 0 6px;gap:2px 12px}
    .bagan-kaki{gap:12px;margin-top:4px}
    .ket-print{font-size:.64rem;line-height:1.35;margin-top:0}
    .bagan-kaki .sheet-foot{margin:0}
    /* v1.40.0: label kepala kini DUA baris ("Mengetahui," + jabatan) sehingga tinggi lembar
       bertambah ±20px — jarak ruang tanda tangan & catatan dikurangi agar bagan TETAP muat satu
       halaman A4 landscape. UKUR ULANG (bagian uji cetak bagan) bila tata letaknya diubah. */
    .sheet-sign .space{height:4px}
    .sheet-sign b{font-size:.74rem}
    .print-sheet > .xs.mut.center{margin-top:4px !important}
  }
  @page{size:A4 landscape;margin:12mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="tugas.php?kode=<?= e($kode) ?>&bagian=organisasi&tampilan=bagan">Kembali</a>
  <a class="btn btn-ghost" href="cetak_osim_organisasi.php?kode=<?= e($kode) ?>"><?= ikon('clipboard', 16) ?> Cetak Tabel</a>
  <a class="btn btn-primary" href="cetak_osim_bagan.php?kode=<?= e($kode) ?>&auto=1"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak('Bagan Susunan Organisasi OSIM/OSIS', ['Bidang Kesiswaan ' . istilah_sekolah('Sekolah')]); ?>

    <div class="bagan-identitas">
      <span><b>Judul bagan</b>: <?= e(osim_bagan_judul($kode)) ?></span>
      <?php if (osim_bagan_periode($kode) !== ''): ?>
        <span><b>Periode</b>: <?= e(osim_bagan_periode($kode)) ?></span>
      <?php endif; ?>
      <span><b>Jumlah</b>: <?= (int) $baganStat['nama'] ?> nama,
        <?= (int) $baganStat['kotak'] ?> kotak jabatan/seksi,
        <?= (int) $baganStat['tingkat'] ?> tingkat</span>
    </div>

    <?php // Kepala bagan dilewati (tanpaKepala = true) karena kop sudah memuat nama sekolah. ?>
    <div class="osim-bagan-luapan">
      <?= osim_bagan_html($pengurus, true, $kode) ?>
    </div>

    <?php // Keterangan & tanda tangan dibuat SEBARIS supaya bagan tetap muat satu halaman A4
          // landscape (kalau ditumpuk, tinggi lembar melebihi area cetak ±703px). ?>
    <div class="bagan-kaki">
      <div class="ket-print">
        Keterangan: bagan digambar otomatis dari baris <b>Susunan Organisasi</b> OSIM/OSIS
        <?= e(istilah_sekolah('sekolah')) ?>. Letak tiap kotak mengikuti <b>baris bagan</b> (tingkat)
        pada data susunan organisasi, sehingga urutannya dapat disesuaikan pengelola kapan saja.
      </div>
      <!-- Tanda tangan: kepala sekolah/madrasah di sisi KANAN (pola lembar cetak lain). -->
      <div class="sheet-foot kanan">
        <?php ttd_blok(ttd_mengetahui(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kanan'); ?>
      </div>
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
