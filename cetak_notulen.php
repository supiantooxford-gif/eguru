<?php
/**
 * Lembar cetak Notulen Rapat — DUA halaman terpisah (permintaan pemilik):
 *   ?bagian=berita → Berita Acara Rapat (A4 portrait)
 *   ?bagian=hadir  → Daftar Hadir Guru (A4 portrait)
 *
 * Hak akses: Super Admin (dalam konteks sekolah), Admin Operator, dan Kepala Sekolah/Madrasah
 * (kepala hanya melihat & mencetak). Akun guru 403.
 *
 * Tanda tangan: Berita acara → Pimpinan Rapat (kiri) & Notulis (kanan).
 *               Daftar hadir  → "Mengetahui, Kepala Sekolah/Madrasah" (kanan, satu blok).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/notulen_ttd.php';   // tanda tangan langsung di layar sentuh (v1.70.0)

// Tampung keluaran agar istilah Sekolah/Madrasah bisa disesuaikan sebelum dikirim.
ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$u = require_notulen(); // kepala boleh mencetak (hanya lihat); operator juga boleh membuat notulen

$id = (int) ($_GET['id'] ?? 0);
$n = notulen_by_id($id);
if (!$n) {
    flash('Notulen tidak ditemukan.', 'err');
    redirect('notulen.php');
}

$bagianDiminta = (string) ($_GET['bagian'] ?? 'berita');
$bagian = in_array($bagianDiminta, ['hadir', 'isi', 'foto'], true) ? $bagianDiminta : 'berita';
$judulBagian = [
    'berita' => 'Berita Acara Rapat',
    'hadir'  => 'Daftar Hadir Rapat',
    'isi'    => 'Isi Notulen Rapat',
    'foto'   => 'Foto Dokumentasi Kegiatan Rapat',
][$bagian];
$peserta = notulen_peserta($id);
$jmlHadir = count($peserta);
$autoPrint = isset($_GET['auto']);

// ---------------- TANDA TANGAN LANGSUNG DI LAYAR SENTUH (v1.70.0, permintaan pemilik) ----------------
// Pihak yang boleh menandatangani: Kepala Sekolah/Madrasah, Pimpinan Rapat, Notulis, dan SETIAP nama
// pada Daftar Hadir. Tombol "Tanda Tangan" hanya dimunculkan skrip pada perangkat berlayar sentuh
// (HTML-nya ber-atribut hidden), sehingga komputer biasa tetap mencetak kolom tanda tangan kosong.
$ttdPihak = notulen_ttd_pihak($n, $peserta);
$ttdPeta = notulen_ttd_peta($id);
// Hanya hitung tanda tangan milik pihak yang MASIH ada (peserta bisa berubah saat notulen diubah).
$ttdJumlah = count(array_intersect_key($ttdPeta, $ttdPihak));
// Kunci pihak yang dipakai pada blok tanda tangan lembar cetak ('' = blok tidak dapat ditandatangani,
// mis. Pimpinan/Notulis yang namanya belum diisi pada notulen).
$pihakKepala = 'kepala';
$pihakPimpinan = isset($ttdPihak['pimpinan']) ? 'pimpinan' : '';
$pihakNotulis = isset($ttdPihak['notulis']) ? 'notulis' : '';

// Foto bukti kegiatan untuk lembar "Foto Dokumentasi" (maksimal NOTULEN_FOTO_MAKS).
// Tanpa foto, halaman foto dialihkan dengan pesan ramah (bukan lembar kosong).
$fotoDok = notulen_foto($id);
$fotoUk = foto_cetak_ukuran();
$istilahFoto = istilah_sekolah('Foto kegiatan');
if ($bagian === 'foto' && !$fotoDok) {
    flash('Belum ada foto bukti kegiatan pada notulen ini, jadi lembar foto dokumentasi belum dapat dicetak.', 'warn');
    redirect('notulen_form.php?id=' . $id . '#cardFotoNotulen');
}

$tanggal = (string) $n['tanggal'];
$tanggalPanjang = hari_id($tanggal) . ', ' . tanggal_id($tanggal);
$ta = ta_by_id((int) ($n['ta_id'] ?? 0));
$sem = semester_by_id((int) ($n['semester_id'] ?? 0));

// Paragraf pembuka berita acara (dirangkai dari isian notulen).
$pembuka = 'Pada hari ini ' . $tanggalPanjang . ',';
if (trim((string) $n['waktu']) !== '') {
    $pembuka .= ' pukul ' . trim((string) $n['waktu']) . ',';
}
$pembuka .= ' bertempat di ' . (trim((string) $n['tempat']) !== '' ? trim((string) $n['tempat']) : '-')
    . ', telah dilaksanakan rapat ' . istilah_sekolah('Sekolah') . ' '
    . tetap_kata(nama_sekolah()) . ' dengan topik "' . trim((string) $n['judul']) . '", '
    . 'dihadiri oleh ' . $jmlHadir . ' guru (daftar hadir terlampir).';

$bagianBertajuk = static function (string $judul, string $isi): void {
    $isi = trim($isi);
    if ($isi === '') {
        return;
    }
    echo '<div class="not-bagian">';
    echo '<h3>' . e($judul) . '</h3>';
    foreach (preg_split('/\r\n|\r|\n/', $isi) ?: [] as $baris) {
        $baris = trim($baris);
        if ($baris === '') {
            continue;
        }
        echo '<p>' . e($baris) . '</p>';
    }
    echo '</div>';
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judulBagian) ?> — <?= e(tetap_kata(nama_sekolah())) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:<?= $bagian === 'foto' ? '1160px' : '900px' ?>;margin:0 auto}
  .toolbar{max-width:900px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .not-judul{text-align:center;margin:2px 0 6px;font-size:1.02rem;font-weight:700;letter-spacing:.03em;
    text-transform:uppercase;color:var(--hijau-tua)}
  .not-nomor{text-align:center;font-size:.78rem;color:var(--muted);margin:0 0 16px}
  .not-pembuka{font-size:.86rem;line-height:1.7;text-align:justify;text-justify:inter-word;margin:0 0 14px}
  .not-bagian{margin:0 0 12px}
  .not-bagian h3{margin:0 0 5px;font-size:.84rem;color:var(--hijau-tua);text-transform:uppercase;letter-spacing:.02em}
  .not-bagian p{margin:0 0 4px;font-size:.85rem;line-height:1.65;text-align:justify;text-justify:inter-word}
  .not-tutup{font-size:.85rem;line-height:1.7;text-align:justify;text-justify:inter-word;margin:14px 0 0}
  .not-ket{font-size:.74rem;color:var(--muted);margin-top:10px;line-height:1.5}
  table.sheet.hadir th,table.sheet.hadir td{padding:6px 7px}
  /* Tabel Isi Notulen (permintaan pemilik: "dibuat dalam bentuk tabel yang rapi") */
  table.sheet.isinotulen{width:100%;table-layout:fixed}
  table.sheet.isinotulen th,table.sheet.isinotulen td{padding:7px 9px;vertical-align:top;font-size:.82rem;line-height:1.6}
  table.sheet.isinotulen thead th{background:var(--hijau-muda);text-align:left}
  table.sheet.isinotulen thead th.ctr{text-align:center}
  table.sheet.isinotulen td.isinotulen-label{font-weight:700;color:var(--hijau-tua);background:#fafbf8}

  /* SATU JENIS HURUF untuk seluruh dokumen (permintaan pemilik: "pastikan jenis fontnya sama semua").
     Tema memakai Georgia/Times untuk <h1>/<h2> (judul kop), sehingga tadinya judul kop berbeda jenis
     dengan isi berita acara. Aturan ini menyamakan SEMUA teks dokumen dengan huruf isi dokumen. */
  .print-sheet,.print-sheet *{
    font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,Roboto,sans-serif;
  }

  /* Tata letak layar sempit: HANYA berlaku di layar (media "screen").
     PENTING: jangan memakai "@media (max-width:900px)" di sini — kertas A4 portrait hanya selebar
     ±695px, jadi aturan itu ikut aktif saat MENCETAK dan membuat tanda tangan menumpuk atas-bawah
     (permintaan pemilik: pimpinan rapat & notulis harus kiri-kanan). */
  @media screen and (max-width:900px){
    .print-sheet{padding:18px 14px}
    .sheet-meta{grid-template-columns:minmax(0,1fr)}
    .sheet-foot{flex-direction:column;gap:20px}
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
    /* Tanda tangan WAJIB berdampingan (kiri & kanan), bukan bertumpuk, saat dicetak. */
    .sheet-foot{flex-direction:row;flex-wrap:nowrap;justify-content:space-between}
    .sheet-foot.dua-ttd .sheet-sign{flex:1 1 0;min-width:0}
  }
  <?php $fotoUk = foto_cetak_ukuran(); ?>
  /* Lembar "Foto Dokumentasi" — SELURUH dokumentasi WAJIB muat SATU halaman A4 landscape
     (permintaan pemilik). Cara mencapainya:
       1. bingkai foto berukuran mm hasil foto_cetak_ukuran() (rasio 3:2, dibatasi agar muat);
       2. tata letak dipadatkan khusus lewat kelas .sheet-foto-dok (kop, judul, identitas, tanda tangan). */
  .foto-dok-grid{display:grid;grid-template-columns:repeat(<?= (int) $fotoUk['kolom'] ?>,<?= $fotoUk['lebar'] ?>mm);
    gap:8mm;justify-content:center;margin:0 0 3mm}
  .foto-dok{display:flex;flex-direction:column;gap:1.5mm;align-items:center;break-inside:avoid;page-break-inside:avoid}
  .foto-dok-kotak{width:<?= $fotoUk['lebar'] ?>mm;height:<?= $fotoUk['tinggi'] ?>mm;background:#fff;
    border:1px solid #9aa79f;box-sizing:border-box;display:grid;place-items:center;overflow:hidden;padding:1.5mm}
  .foto-dok-kotak img{max-width:100%;max-height:100%;object-fit:contain;display:block}
  .foto-dok-ket{width:<?= $fotoUk['lebar'] ?>mm;text-align:center;font-size:.72rem;line-height:1.4;color:#374151}
  .foto-dok-ket b{color:var(--hijau-tua)}
  .foto-dok-ukuran{font-size:.68rem;color:var(--muted);letter-spacing:.02em}
  /* --- Pemandatan khusus lembar foto (agar tetap satu halaman) --- */
  .sheet-foto-dok .sheet-head{padding-bottom:6px;margin-bottom:8px}
  .sheet-foto-dok .sheet-head.kop-logo .kop-gambar img{width:18mm;height:18mm}
  .sheet-foto-dok .sheet-head h2{font-size:1rem}
  .sheet-foto-dok .sheet-head p{margin:2px 0 0;font-size:.78rem}
  .sheet-foto-dok .kop-nama{font-size:.9rem}
  .sheet-foto-dok .not-judul{margin:0 0 1px;font-size:.95rem}
  .sheet-foto-dok .not-nomor{margin:0 0 6px}
  .sheet-foto-dok .sheet-meta{gap:2px 18px;font-size:.8rem;margin-bottom:8px;grid-template-columns:repeat(3,minmax(0,1fr))}
  .sheet-foto-dok .sheet-meta b{min-width:0;display:block}
  .sheet-foto-dok .not-ket{margin-top:2mm;font-size:.7rem}
  .sheet-foto-dok .sheet-foot{margin-top:5mm}
  .sheet-foto-dok .sheet-sign .space{height:34px}
  .sheet-foto-dok .sheet-sign{font-size:.8rem}
  @page{size:A4 <?= $bagian === 'foto' ? 'landscape' : 'portrait' ?>;margin:<?= $bagian === 'foto' ? '10mm' : '13mm' ?>}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="notulen.php">Kembali</a>
  <a class="btn <?= $bagian === 'berita' ? 'btn-primary' : 'btn-ghost' ?>" href="cetak_notulen.php?id=<?= (int) $n['id'] ?>&amp;bagian=berita"><?= ikon('doc', 16) ?> Berita Acara</a>
  <a class="btn <?= $bagian === 'hadir' ? 'btn-primary' : 'btn-ghost' ?>" href="cetak_notulen.php?id=<?= (int) $n['id'] ?>&amp;bagian=hadir"><?= ikon('clipboard', 16) ?> Daftar Hadir</a>
  <a class="btn <?= $bagian === 'isi' ? 'btn-primary' : 'btn-ghost' ?>" href="cetak_notulen.php?id=<?= (int) $n['id'] ?>&amp;bagian=isi"><?= ikon('report', 16) ?> Isi Notulen</a>
  <a class="btn <?= $bagian === 'foto' ? 'btn-primary' : 'btn-ghost' ?>" href="cetak_notulen.php?id=<?= (int) $n['id'] ?>&amp;bagian=foto"><?= ikon('upload', 16) ?> Foto Dokumentasi</a>
  <a class="btn btn-primary" href="cetak_notulen.php?<?= e(http_build_query(['id' => (int) $n['id'], 'bagian' => $bagian, 'auto' => 1])) ?>"><?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet<?= $bagian === 'foto' ? ' sheet-foto-dok' : '' ?>">
    <?php if ($bagian === 'berita'): ?>

      <?php // Permintaan pemilik: tulisan "Berita Acara Rapat" pada KOP dihapus (judul dokumen tetap
            // tampil di bawah kop). ?>
      <?php kop_cetak('', ['Notulen Rapat ' . istilah_sekolah('Sekolah')]); ?>

      <div class="not-judul">Berita Acara Rapat</div>
      <p class="not-nomor">
        Tanggal: <?= e(tanggal_id($tanggal, true)) ?>
        <?php if ($ta): ?>, Tahun Ajaran <?= e(ta_nama($ta)) ?><?php endif; ?>
        <?php if ($sem): ?>, Semester <?= e(sem_nama($sem)) ?><?php endif; ?>
      </p>

      <?php sheet_meta([
          'Topik Rapat'   => trim((string) $n['judul']) !== '' ? (string) $n['judul'] : '-',
          'Hari / Tanggal' => $tanggalPanjang,
          'Waktu'         => trim((string) $n['waktu']) !== '' ? (string) $n['waktu'] : '-',
          'Tempat'        => trim((string) $n['tempat']) !== '' ? (string) $n['tempat'] : '-',
          'Jumlah Peserta' => $jmlHadir . ' guru',
          'Pimpinan Rapat' => trim((string) $n['pimpinan']) !== '' ? (string) $n['pimpinan']
              . (trim((string) $n['pimpinan_jabatan']) !== '' ? ' (' . $n['pimpinan_jabatan'] . ')' : '') : '-',
          'Notulis'       => trim((string) $n['notulis']) !== '' ? (string) $n['notulis'] : '-',
      ]); ?>

      <p class="not-pembuka"><?= e($pembuka) ?></p>

      <?php
      $bagianBertajuk('Agenda Rapat', (string) $n['agenda']);
      $bagianBertajuk('Hasil Pembahasan', (string) $n['pembahasan']);
      $bagianBertajuk('Keputusan / Kesimpulan Rapat', (string) $n['keputusan']);
      ?>

      <?php // Permintaan pemilik: kalimat "Daftar hadir peserta rapat dicetak pada lembar terpisah"
            // DIHAPUS dari berita acara. ?>
      <p class="not-tutup">
        Demikian berita acara rapat ini dibuat dengan sebenarnya dan disetujui bersama, untuk dipergunakan
        sebagaimana mestinya.
      </p>

      <?php // Permintaan pemilik: keterangan "Notulen ini dibuat melalui aplikasi e-GURU v… pada …
            // oleh …" DIHAPUS dari lembar berita acara. ?>

      <!-- Berita acara: Pimpinan Rapat di KIRI, Notulis di KANAN. -->
      <div class="sheet-foot dua-ttd">
        <?php // Spacer: blok kanan (Notulis) memakai baris "tempat, tanggal", jadi blok kiri diberi
              // baris kosong setinggi baris itu supaya NAMA pimpinan & notulis tetap sejajar. ?>
        <?php ttd_blok('Pimpinan Rapat', trim((string) $n['pimpinan']) !== '' ? (string) $n['pimpinan'] : '-',
            '', 'sheet-sign sheet-sign-kiri', false, true,
            $pihakPimpinan, notulen_ttd_gambar($ttdPeta, 'pimpinan')); ?>
        <?php ttd_blok('Notulis', trim((string) $n['notulis']) !== '' ? (string) $n['notulis'] : '-',
            '', 'sheet-sign sheet-sign-kanan', true, false,
            $pihakNotulis, notulen_ttd_gambar($ttdPeta, 'notulis')); ?>
      </div>

    <?php elseif ($bagian === 'isi'): ?>

      <?php // Halaman cetak ISI NOTULEN RAPAT (permintaan pemilik). Memuat: judul/topik, hari/tanggal,
            // tempat, pimpinan rapat, jumlah peserta, pembahasan rapat, dan isi rapat.
            // "Isi rapat" diambil dari kolom **Agenda Rapat** (pilihan pemilik), sedangkan
            // "Pembahasan rapat" dari kolom Hasil Pembahasan.
            // Tanda tangan: Kepala Sekolah/Madrasah di KIRI, Notulen di KANAN. ?>
      <?php // Permintaan pemilik: tulisan "Isi Notulen Rapat" pada KOP dihapus, jadi judul kop
            // dikosongkan (kop hanya memuat nama sekolah, alamat, dan identitas). Judul lembar
            // ditampilkan sebagai judul dokumen di bawah kop. ?>
      <?php kop_cetak('', ['Notulen Rapat ' . istilah_sekolah('Sekolah')]); ?>

      <div class="not-judul">Isi Notulen Rapat</div>

      <?php
      // Permintaan pemilik: hasil cetak Isi Notulen dibuat dalam BENTUK TABEL yang rapi —
      // satu tabel dengan kolom No / Uraian / Keterangan, bukan daftar identitas + paragraf.
      // Baris yang datanya kosong tidak dicetak (mis. "Isi Rapat" pada notulen baru, karena
      // kolom Agenda sudah dihapus dari formulir) sehingga tabel tetap rapi tanpa baris menggantung.
      $barisIsi = [];
      $teksAtau = static fn(string $v): string => trim($v) !== '' ? trim($v) : '-';
      $pimpinanIsi = $teksAtau((string) $n['pimpinan']);
      if ($pimpinanIsi !== '-' && trim((string) $n['pimpinan_jabatan']) !== '') {
          $pimpinanIsi .= ' (' . trim((string) $n['pimpinan_jabatan']) . ')';
      }
      $barisIsi[] = ['Judul / Topik Rapat', $teksAtau((string) $n['judul']), false];
      $barisIsi[] = ['Hari / Tanggal', $tanggalPanjang, false];
      $barisIsi[] = ['Tempat', $teksAtau((string) $n['tempat']), false];
      $barisIsi[] = ['Pimpinan Rapat', $pimpinanIsi, false];
      $barisIsi[] = ['Jumlah Peserta Rapat', $jmlHadir . ' guru', false];
      $barisIsi[] = ['Notulis', $teksAtau((string) $n['notulis']), false];
      // Dua baris berikut menampilkan teks BERBARIS (baris baru dipertahankan).
      $barisIsi[] = ['Pembahasan Rapat', trim((string) $n['pembahasan']), true];
      if (trim((string) $n['agenda']) !== '') {
          $barisIsi[] = ['Isi Rapat', trim((string) $n['agenda']), true];
      }
      ?>
      <table class="sheet isinotulen">
        <thead>
          <tr>
            <th class="ctr" style="width:34px">No</th>
            <th style="width:32%">Uraian</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
        <?php $noIsi = 0; foreach ($barisIsi as [$label, $nilaiIsi, $berbaris]): $noIsi++;
          $kosong = $nilaiIsi === '';
        ?>
          <tr>
            <td class="ctr"><?= $noIsi ?></td>
            <td class="isinotulen-label"><?= e($label) ?></td>
            <td<?= $kosong ? ' class="mut xs"' : '' ?>><?= $kosong
              ? 'belum diisi'
              : ($berbaris ? nl2br(e($nilaiIsi)) : e($nilaiIsi)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <div class="not-ket">
        Notulen ini dibuat melalui aplikasi <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> pada
        <?= e(tanggal_id(date('Y-m-d'))) ?> oleh <?= e((string) ($u['nama'] ?? '-')) ?>.
      </div>

      <!-- Tanda tangan mengikuti aturan aplikasi (pejabat di KIRI, pelaksana di KANAN):
           KEPALA SEKOLAH/MADRASAH di KIRI, NOTULEN di KANAN — sama seperti lembar cetak lain
           (cetak_nilai/cetak_kasus: "Mengetahui, Kepala …" di kiri). -->
      <div class="sheet-foot dua-ttd">
        <?php ttd_blok(ttd_mengetahui(), kepala_nama(), kepala_nip(),
            'sheet-sign sheet-sign-kiri', false, false,
            $pihakKepala, notulen_ttd_gambar($ttdPeta, 'kepala')); ?>
        <?php ttd_blok('Notulen', trim((string) $n['notulis']) !== '' ? (string) $n['notulis'] : '-',
            '', 'sheet-sign sheet-sign-kanan', true, false,
            $pihakNotulis, notulen_ttd_gambar($ttdPeta, 'notulis')); ?>
      </div>

    <?php elseif ($bagian === 'foto'): ?>

      <?php // Lembar FOTO DOKUMENTASI (permintaan pemilik): foto bukti kegiatan dicetak DI DALAM
            // dokumen pada ukuran nyata 3R/4R (NOTULEN_FOTO_CETAK, diatur di inc/config.php).
            // Judul kop dikosongkan (judul dokumen tampil di bawah kop), kertas A4 landscape. ?>
      <?php kop_cetak('', ['Lampiran Dokumentasi ' . istilah_sekolah('Sekolah')]); ?>

      <div class="not-judul">Foto Dokumentasi Kegiatan Rapat</div>
      <p class="not-nomor">
        <?= e(trim((string) $n['judul']) !== '' ? (string) $n['judul'] : '-') ?>,
        <?= e($tanggalPanjang) ?>
      </p>

      <?php // Permintaan pemilik: baris identitas "Jumlah Foto" dan "Ukuran Foto" DIHAPUS dari lembar
            // foto dokumentasi (identitas cukup topik, hari/tanggal, dan tempat). ?>
      <?php sheet_meta([
          'Topik Rapat'         => trim((string) $n['judul']) !== '' ? (string) $n['judul'] : '-',
          'Hari / Tanggal'      => $tanggalPanjang,
          'Tempat'              => trim((string) $n['tempat']) !== '' ? (string) $n['tempat'] : '-',
      ]); ?>

      <div class="foto-dok-grid">
        <?php foreach ($fotoDok as $i => $f): ?>
          <div class="foto-dok">
            <div class="foto-dok-kotak">
              <img src="<?= e((string) $f['url']) ?>" alt="Foto dokumentasi kegiatan rapat <?= $i + 1 ?>">
            </div>
            <div class="foto-dok-ket">
              <b>Foto <?= $i + 1 ?></b><?= trim((string) ($f['keterangan'] ?? '')) !== ''
                ? ' — ' . e((string) $f['keterangan']) : '' ?>
              <div class="foto-dok-ukuran"><?= e($istilahFoto) ?> rapat <?= e(tetap_kata(nama_sekolah())) ?>
                &mdash; <?= e(tanggal_id($tanggal, true)) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="not-ket">
        Keterangan: foto di atas adalah bukti dokumentasi kegiatan rapat dan menjadi lampiran
        yang tidak terpisahkan dari berita acara rapat ini. Seluruh dokumentasi dicetak dalam satu lembar.
      </div>

      <!-- Lampiran dokumentasi: "Mengetahui, Kepala …" di sisi KANAN (satu blok), sama seperti
           lembar daftar hadir. -->
      <div class="sheet-foot kanan">
        <?php ttd_blok(ttd_mengetahui(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kanan',
            false, false, $pihakKepala, notulen_ttd_gambar($ttdPeta, 'kepala')); ?>
      </div>

    <?php else: ?>

      <?php // Permintaan pemilik: tulisan "Daftar Hadir Rapat" pada KOP dihapus. ?>
      <?php kop_cetak('', ['Peserta Rapat ' . istilah_sekolah('Sekolah')]); ?>

      <div class="not-judul">Daftar Hadir Rapat</div>
      <p class="not-nomor">
        <?= e(trim((string) $n['judul']) !== '' ? (string) $n['judul'] : '-') ?>,
        <?= e(tanggal_id($tanggal, true)) ?>
      </p>

      <?php sheet_meta([
          'Hari / Tanggal' => $tanggalPanjang,
          'Waktu'         => trim((string) $n['waktu']) !== '' ? (string) $n['waktu'] : '-',
          'Tempat'        => trim((string) $n['tempat']) !== '' ? (string) $n['tempat'] : '-',
          'Topik Rapat'   => trim((string) $n['judul']) !== '' ? (string) $n['judul'] : '-',
          'Jumlah Hadir'  => (string) $jmlHadir . ' guru',
      ]); ?>

      <?php if (!$peserta): ?>
        <p class="not-ket">Belum ada guru yang tercatat menghadiri rapat ini.</p>
      <?php else: ?>
        <table class="sheet hadir padat">
          <thead>
            <tr>
              <th class="ctr" style="width:30px">No</th>
              <th style="width:34%">Nama Guru</th>
              <th style="width:22%">NIP / NUPTK</th>
              <th style="width:22%">Jabatan</th>
              <th class="ctr" style="width:22%">Tanda Tangan</th>
            </tr>
          </thead>
          <tbody>
          <?php $no = 0; foreach ($peserta as $h): $no++;
            // Kunci pihak memakai ID GURU (lihat notulen_ttd_pihak()) supaya tanda tangan tetap
            // menempel pada orang yang sama walau daftar hadir notulen disunting.
            $gidBaris = (int) ($h['guru_id'] ?? 0);
            $pihakBaris = $gidBaris > 0 ? 'hadir:g' . $gidBaris : 'hadir:h' . (int) ($h['id'] ?? 0);
          ?>
            <tr>
              <td class="ctr"><?= $no ?></td>
              <td><b><?= e((string) $h['nama']) ?></b></td>
              <td><?= trim((string) $h['nip']) !== '' ? e((string) $h['nip']) : '-' ?></td>
              <td><?= trim((string) $h['jabatan']) !== '' ? e((string) $h['jabatan']) : '-' ?></td>
              <td class="ctr ttd-sel" data-ttd-sel="<?= e($pihakBaris) ?>">
                <?= notulen_ttd_gambar($ttdPeta, $pihakBaris) ?>
                <?= notulen_ttd_tombol($pihakBaris, isset($ttdPeta[$pihakBaris])) ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <th colspan="4" class="ctr">Jumlah Hadir</th>
              <th class="ctr"><?= $jmlHadir ?> guru</th>
            </tr>
          </tfoot>
        </table>

        <div class="not-ket">
          Keterangan: daftar hadir ini memuat guru yang mengikuti rapat
          <?= e(istilah_sekolah('sekolah')) ?> pada tanggal tersebut. Kolom tanda tangan dapat diisi
          tangan saat rapat berlangsung, atau dibubuhkan langsung pada layar sentuh (HP/tablet)
          dengan menekan tombol <b>Tanda Tangan</b>.
        </div>
      <?php endif; ?>

      <!-- Daftar hadir: "Mengetahui" Kepala Sekolah/Madrasah di sisi KANAN. -->
      <div class="sheet-foot kanan">
        <?php ttd_blok(ttd_mengetahui(), kepala_nama(), kepala_nip(), 'sheet-sign sheet-sign-kanan',
            false, false, $pihakKepala, notulen_ttd_gambar($ttdPeta, 'kepala')); ?>
      </div>

    <?php endif; ?>
  </div>
</div>

<?php // ---------------- TANDA TANGAN LANGSUNG DI LAYAR SENTUH (v1.70.0) ----------------
      // Catatan & dialog hanya ditampilkan pada perangkat berlayar sentuh (skrip yang memunculkannya),
      // dan keduanya disembunyikan saat MENCETAK. Data pihak yang boleh menandatangani dicetak
      // server pada <script type="application/json"> supaya skrip tidak menebak apa pun. ?>
<p class="ttd-catatan no-print" id="ttdCatatan" hidden>
  Tanda tangan dapat dibubuhkan <b>langsung pada layar sentuh</b>: tekan tombol
  <b>Tanda Tangan</b> pada kolom/ruang tanda tangan di atas. Sudah ditandatangani:
  <b data-ttd-jumlah><?= (int) $ttdJumlah ?></b> dari <?= count($ttdPihak) ?> pihak.
</p>
<?php if ($ttdPihak): ?>
<script type="application/json" id="ttdData"><?= notulen_ttd_skrip_json($id, $ttdPihak, $ttdPeta) ?></script>
<?= notulen_ttd_dialog($id) ?>
<?php endif; ?>

<?php // Skrip tema: dipakai dialog TANDA TANGAN LANGSUNG DI LAYAR SENTUH (initTtdSentuh). ?>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
<?php terjemahkan_selesai(); ?>
