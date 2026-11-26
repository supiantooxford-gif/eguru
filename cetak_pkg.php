<?php
/**
 * LEMBAR CETAK HASIL PKG (PENILAIAN KINERJA GURU) — A4 potrait.
 *
 * Permintaan pemilik: *"Menu PKG (Penilaian Kinerja Guru) pada akun kepala sekolah digunakan untuk
 * menilai setiap guru pada lembaganya sendiri (Instrumen penilaian seperti yang saya upload) dan
 * hasil penilaiannya masuk ke menu Hasil PKG pada akun guru. Selain itu pengawas juga bisa melihat
 * hasil PKG setiap guru binaannya."* — lembar ini bentuk cetak penilaian itu: identitas guru &
 * penilai, tabel 13 butir (skala 1-4 + catatan/bukti fisik), rekapitulasi per dimensi, persentase &
 * predikat, catatan RTL, lalu tanda tangan **Guru yang Dinilai di KIRI** dan
 * **Kepala Sekolah/Madrasah (Penilai) di KANAN**.
 *
 * Hak akses: kepala sekolah/madrasah & Super Admin (sekolah dalam konteks), guru yang dinilai, serta
 * pengawas binaan sekolah itu. Selain itu 403.
 *
 * Halaman ini TIDAK memakai layout, jadi memanggil ob_start() + terjemahkan_selesai() sendiri dan
 * menulis `@page{size:…}` di <style> halaman ini (bukan di assets/style.css).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';

ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

require_login();

$id = (int) ($_GET['id'] ?? 0);
// Sekolah yang boleh dilihat: pengawas → binaannya; selain itu sekolah dalam konteks.
$sekolahIds = [];
$uid = (int) (current_user()['id'] ?? 0);
if (is_pengawas()) {
    foreach (sekolah_binaan($uid, true) as $b) {
        $sekolahIds[] = (int) $b['id'];
    }
} elseif (current_sekolah()) {
    $sekolahIds[] = sekolah_id();
}
$p = pkg_by_id($id, $sekolahIds);
if (!$p) {
    flash('Penilaian PKG itu tidak ditemukan, atau bukan sekolah/madrasah yang dapat Anda buka.', 'err');
    redirect(is_pengawas() ? 'pkg_pengawas.php' : (is_guru() ? 'pkg_hasil.php' : 'index.php'));
}

// Pemeriksaan akhir per peran (anti-IDOR).
$sid = (int) $p['sekolah_id'];
if (is_pengawas()) {
    if (!pengawas_punya($uid, $sid)) {
        tolak403('Sekolah/madrasah itu bukan binaan Anda.');
    }
} elseif (is_super()) {
    // Super Admin: boleh melihat (pemilik aplikasi).
} elseif (is_guru()) {
    if ((int) $p['guru_id'] !== guru_id_saya()) {
        tolak403('Lembar PKG ini milik guru lain.');
    }
} elseif (is_kepala()) {
    if ($sid !== sekolah_id()) {
        tolak403('Lembar PKG ini milik sekolah/madrasah lain.');
    }
} else {
    tolak403('Anda tidak berhak membuka lembar PKG ini.');
}

// Kop memakai data sekolah penilaian ini — bukan sekolah yang kebetulan aktif.
$sekolah = one('SELECT * FROM sekolah WHERE id = ?', [$sid]);
if (!$sekolah) {
    flash('Sekolah/madrasah penilaian itu sudah tidak ada.', 'err');
    redirect(is_pengawas() ? 'pkg_pengawas.php' : 'index.php');
}
if (!is_pengawas() && !is_super() && current_sekolah() && (int) current_sekolah()['id'] !== $sid) {
    set_sekolah_context($sid);
}

$nilai = pkg_nilai_peta($id);
$auto = isset($_GET['auto']) && (string) $_GET['auto'] !== '0';
$namaSekolah = (string) ($sekolah['nama'] ?? '');
$namaGuru = (string) ($p['guru_nama'] ?? '-');
$nipGuru = trim((string) ($p['guru_nip'] ?? ''));
if ($nipGuru === '') {
    $nipGuru = trim((string) ($p['guru_nuptk'] ?? ''));
}
$tanggalTeks = tanggal_id((string) $p['tanggal']);
$tempatTeks = trim((string) ($p['tempat'] ?? ''));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hasil PKG — <?= e(tetap_kata($namaGuru)) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:860px;margin:0 auto}
  .toolbar{max-width:860px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  /* Identitas hasil PKG (nama guru, NIP, unit kerja, penilai, periode, …) */
  .pkg-identitas{width:100%;border-collapse:collapse;margin:14px 0 6px}
  .pkg-identitas td{padding:3px 6px;font-size:.86rem;vertical-align:top;border:none}
  .pkg-identitas td:first-child{width:190px}
  .pkg-identitas td.pisah{width:14px;text-align:center}
  /* ⚠️ `table-layout:fixed` + lebar kolom PERSENTASE wajib: tanpa itu catatan yang panjang membuat
     tabel melebar melewati area cetak A4 potrait. */
  table.sheet.pkg-instrumen{width:100%;table-layout:fixed}
  table.sheet.pkg-instrumen th,table.sheet.pkg-instrumen td{padding:4px 5px;font-size:.74rem;line-height:1.3;
    overflow-wrap:anywhere;word-break:break-word}
  table.sheet.pkg-instrumen th{font-size:.68rem;letter-spacing:0;vertical-align:middle}
  table.sheet.pkg-instrumen .k-no{width:6%}
  table.sheet.pkg-instrumen .k-skor{width:9%}
  table.sheet.pkg-instrumen .k-cat{width:37%}
  table.sheet.pkg-instrumen tr.pkg-grup td{background:rgba(11,74,51,.07);font-weight:700}
  /* LEGENDA SKALA (v1.109.1, dirapikan lagi pada v1.110.0) — dibuat KOTAK TABEL berbingkai supaya
     sejajar dengan tabel rubrik di bawahnya. 🚨 `table-layout:fixed` mengambil lebar kolom dari baris
     PERTAMA; karena baris pertama di sini adalah sel judul ber-`colspan="2"`, kolom sempat terbelah
     50:50 sehingga nomor skala melayang jauh di tengah kotak dan keterangannya terdorong ke kanan
     (keluhan pemilik: bagian bertanda hitam "agar dirapikan"). Karena itu lebar kolom nomor WAJIB
     ditetapkan lewat <colgroup><col> — bukan hanya `th.n{width:…}`. */
  table.pkg-skala-cetak{width:100%;border-collapse:collapse;margin:6px 0 12px;table-layout:fixed}
  table.pkg-skala-cetak col.c-n{width:58px}
  table.pkg-skala-cetak th,table.pkg-skala-cetak td{font-size:.76rem;line-height:1.35;padding:3px 7px;
    border:1px solid #7d8f86;text-align:left;vertical-align:top;font-weight:400;background:none}
  table.pkg-skala-cetak thead th{background:var(--hijau-muda);font-weight:700;font-size:.74rem;
    color:var(--hijau-tua);text-align:left}
  table.pkg-skala-cetak th.n{width:58px;text-align:center;font-weight:700;background:rgba(11,74,51,.05)}
  .pkg-rekap-cetak{width:100%;border-collapse:collapse;margin-top:12px}
  .pkg-rekap-cetak th,.pkg-rekap-cetak td{font-size:.8rem;padding:4px 6px;border:1px solid rgba(31,27,58,.18)}
  .pkg-rekap-cetak th{text-align:left;width:auto;background:rgba(11,74,51,.06)}
  /* CATATAN & RTL (v1.109.1, kolom dirapikan v1.110.1) — permintaan pemilik: bagian bertanda biru
     "dibuat di dalam kolom agar terlihat rapi". Berupa TABEL berbingkai DUA KOLOM: kolom label (judul)
     di KIRI dan kolom isi di KANAN, sehingga teks catatan tidak lagi menggantung tanpa kolom.
     🚨 `table-layout:fixed` mengambil lebar kolom dari baris PERTAMA — dan baris pertama di sini
     adalah sel judul ber-`colspan="2"`, sehingga kolom terbelah **50:50** (label jadi 450px dari
     900px) walau `tbody th{width:34%}` ditulis. Akibatnya separuh kolom label kosong: teks isi baru
     mulai di tengah lembar (keluhan pemilik: bagian bertanda hitam "agar dirapikan"). Lebar kolom
     label WAJIB ditetapkan lewat <colgroup><col class="c-l">, bukan lewat `tbody th{width:…}`. */
  table.sheet.pkg-rtl-instrumen{width:100%;table-layout:fixed;margin:14px 0 0}
  table.sheet.pkg-rtl-instrumen col.c-l{width:34%}
  table.sheet.pkg-rtl-instrumen th,table.sheet.pkg-rtl-instrumen td{font-size:.78rem;line-height:1.4;
    padding:4px 7px;vertical-align:top;overflow-wrap:anywhere;word-break:break-word}
  table.sheet.pkg-rtl-instrumen thead th{font-size:.74rem;text-align:left}
  table.sheet.pkg-rtl-instrumen tbody th{background:rgba(11,74,51,.05);font-weight:700;
    text-align:left;color:var(--hijau-tua)}
  /* DITETAPKAN DI / PADA TANGGAL (v1.109.1) — sebelumnya dua baris teks rata kanan yang menempel ke
     tepi area cetak (padding 0 saat mencetak). Kini kotak tabel kecil di KANAN dengan label sejajar. */
  table.pkg-tetap-cetak{margin:14px 0 0 auto;border-collapse:collapse;width:52%;min-width:250px;
    table-layout:fixed}
  table.pkg-tetap-cetak th,table.pkg-tetap-cetak td{font-size:.78rem;padding:3px 7px;
    border:1px solid #7d8f86;text-align:left;vertical-align:top}
  table.pkg-tetap-cetak th{width:44%;background:rgba(11,74,51,.05);font-weight:700;color:var(--hijau-tua)}
  @media screen and (max-width:760px){
    .print-sheet{padding:18px 14px}
    table.sheet.pkg-instrumen{min-width:660px}
    .pkg-identitas td:first-child{width:120px}
    /* Di layar sempit label dilebarkan; WAJIB menulis ke <col> — `tbody th{width:…}` tidak berlaku
       pada `table-layout:fixed` yang lebar kolomnya diambil dari baris pertama. */
    table.pkg-rtl-instrumen col.c-l{width:44%}
    table.pkg-tetap-cetak{width:100%;min-width:0}
  }
  @media print{
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .sheet-wrap{max-width:none;width:100%}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0;width:100%;max-width:none}
    .sheet-scroll{overflow:visible}
    table.sheet.pkg-instrumen{min-width:0}
    /* Kotak legenda, tabel RTL, kotak "Ditetapkan di", dan blok tanda tangan TIDAK boleh terpotong
       di tengah halaman saat dicetak (v1.109.1). */
    #tblSkalaPkgCetak,#tblRtlPkgCetak,#tblTetapPkgCetak,.sheet-foot{break-inside:avoid;page-break-inside:avoid}
    #tblRtlPkgCetak thead{display:table-header-group}
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap}
  }
  @page{size:A4 portrait;margin:14mm 15mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <?php if (is_pengawas()): ?>
    <a class="btn btn-ghost" href="pkg_pengawas.php">Kembali</a>
  <?php elseif (is_guru()): ?>
    <a class="btn btn-ghost" href="pkg_hasil.php">Kembali</a>
  <?php else: ?>
    <a class="btn btn-ghost" href="pkg.php">Kembali</a>
  <?php endif; ?>
  <a class="btn btn-primary" href="cetak_pkg.php?id=<?= $id ?>&amp;auto=1">
    <?= ikon('print', 16) ?> Cetak / Simpan PDF
  </a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak_sekolah($sekolah, 'INSTRUMEN PENILAIAN KINERJA GURU (PKG)',
        ['Tahun Ajaran ' . (string) ($p['ta_nama'] ?? '-') . ' - Semester ' . (string) ($p['semester_nama'] ?? '-')]); ?>

    <table class="pkg-identitas">
      <tr>
        <td>Nama Guru yang Dinilai</td><td class="pisah">:</td>
        <td colspan="3"><b><?= e(tetap_kata($namaGuru)) ?></b></td>
      </tr>
      <tr>
        <td>NIP / NUPTK</td><td class="pisah">:</td>
        <td colspan="3"><?= e($nipGuru !== '' ? $nipGuru : '-') ?></td>
      </tr>
      <tr>
        <td>Unit Kerja / <?= e(istilah_sekolah('Sekolah')) ?></td><td class="pisah">:</td>
        <td colspan="3"><?= e(tetap_kata($namaSekolah)) ?></td>
      </tr>
      <tr>
        <td>Pangkat / Golongan / Ruang</td><td class="pisah">:</td>
        <td colspan="3"><?= e(trim((string) ($p['pangkat'] ?? '')) !== ''
            ? tetap_kata((string) $p['pangkat']) : '-') ?></td>
      </tr>
      <tr>
        <td>Mata Pelajaran / Kelas</td><td class="pisah">:</td>
        <td colspan="3"><?= e(trim((string) ($p['mapel_kelas'] ?? '')) !== ''
            ? tetap_kata((string) $p['mapel_kelas']) : '-') ?></td>
      </tr>
      <tr>
        <td>Nama Penilai / Jabatan</td><td class="pisah">:</td>
        <td colspan="3"><?= e(tetap_kata((string) ($p['penilai_nama'] ?? '-'))) ?>
          (<?= e(tetap_kata((string) ($p['penilai_jabatan'] ?? '-'))) ?>)</td>
      </tr>
      <tr>
        <td>Periode Penilaian</td><td class="pisah">:</td>
        <td colspan="3">Semester <?= e((string) ($p['semester_nama'] ?? '-')) ?> / Tahun Ajaran
          <?= e((string) ($p['ta_nama'] ?? '-')) ?></td>
      </tr>
    </table>

    <?php // LEGENDA SKALA — kotak tabel berbingkai: nomor skala di kolom sempit (rata tengah) dan
          // artinya di kolom lebar. `colgroup` WAJIB ada: dengan `table-layout:fixed`, baris pertama
          // (sel judul ber-colspan) membuat kolom terbelah 50:50 tanpa lebar kolom eksplisit.
          // Jangan dikembalikan menjadi daftar tanpa bingkai: barisnya menempel/menumpuk pada tabel
          // rubrik di bawahnya (keluhan pemilik). ?>
    <table class="pkg-skala-cetak" id="tblSkalaPkgCetak">
      <colgroup><col class="c-n"><col></colgroup>
      <thead>
        <tr><th colspan="2">Skala Penilaian</th></tr>
      </thead>
      <tbody>
      <?php foreach (pkg_skala_list() as $s => $teks): ?>
        <tr><th class="n"><?= (int) $s ?></th><td><?= e($teks) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div class="sheet-scroll">
      <table class="sheet pkg-instrumen" id="tblInstrumenPkgCetak">
        <thead>
          <tr>
            <th class="ctr k-no">No</th>
            <th>Indikator Penilaian</th>
            <th class="ctr k-skor">Skala<br>(1-4)</th>
            <th class="k-cat">Catatan / Bukti Fisik</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach (pkg_instrumen() as $d): ?>
          <tr class="pkg-grup">
            <td class="ctr"><b><?= e((string) $d['kode']) ?></b></td>
            <td colspan="3"><b>Dimensi <?= e((string) $d['kode']) ?>:
              <?= e((string) $d['nama']) ?></b> (maks. <?= (int) $d['maks'] ?>)</td>
          </tr>
          <?php foreach ($d['butir'] as $b):
              $n = $nilai[(int) $b['no']] ?? null;
              $skor = $n['skor'] ?? null;
              $catatan = trim((string) ($n['catatan'] ?? '')); ?>
            <tr>
              <td class="ctr"><?= (int) $b['no'] ?></td>
              <td><b><?= e((string) $b['judul']) ?></b> — <?= e((string) $b['uraian']) ?></td>
              <td class="ctr"><b><?= e(pkg_skala_teks($skor)) ?></b></td>
              <td><?= $catatan !== '' ? e(tetap_kata(pkg_potong($catatan, 400))) : '-' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <table class="pkg-rekap-cetak" id="tblRekapPkgCetak">
      <tr>
        <th>Rekapitulasi</th>
        <th class="ctr" style="width:110px">Maksimal</th>
        <th class="ctr" style="width:110px">Skor</th>
      </tr>
      <tr><td>Dimensi A: Perencanaan &amp; Pelaksanaan Pembelajaran</td>
        <td class="ctr">16</td><td class="ctr"><?= (int) $p['skor_a'] ?></td></tr>
      <tr><td>Dimensi B: Manajemen Kelas &amp; Budaya Positif</td>
        <td class="ctr">12</td><td class="ctr"><?= (int) $p['skor_b'] ?></td></tr>
      <tr><td>Dimensi C: Asesmen, Umpan Balik, &amp; Pelaporan</td>
        <td class="ctr">12</td><td class="ctr"><?= (int) $p['skor_c'] ?></td></tr>
      <tr><td>Dimensi D: Pengembangan Profesi &amp; Perilaku Kerja</td>
        <td class="ctr">12</td><td class="ctr"><?= (int) $p['skor_d'] ?></td></tr>
      <tr><td><b>TOTAL SKOR KESELURUHAN</b></td>
        <td class="ctr"><b><?= (int) $p['skor_maks'] ?></b></td>
        <td class="ctr"><b><?= (int) $p['total_skor'] ?></b></td></tr>
      <tr><td><b>PERSENTASE CAPAIAN</b> ((Total Skor / <?= (int) $p['skor_maks'] ?>) x 100%)</td>
        <td class="ctr">100%</td>
        <td class="ctr"><b><?= e(r1((float) $p['persen'])) ?>%</b></td></tr>
      <tr><td><b>PREDIKAT KINERJA AKHIR</b></td>
        <td class="ctr" colspan="2"><b><?= e((string) $p['predikat']) ?></b>
          (<?= e(pkg_predikat_label((string) $p['predikat'])) ?>)</td></tr>
    </table>
    <p style="font-size:.74rem;margin:6px 0 0">Kategori predikat:
      <?= e(pkg_predikat_keterangan()) ?>.</p>

    <?php // CATATAN & RTL (v1.109.1, kolom dirapikan v1.110.1) — TABEL berbingkai DUA KOLOM
          // (label | isi). <colgroup> WAJIB: dengan `table-layout:fixed`, baris judul ber-colspan
          // membuat kolom terbelah 50:50 sehingga separuh kolom label kosong. ?>
    <table class="sheet pkg-rtl-instrumen" id="tblRtlPkgCetak">
      <colgroup><col class="c-l"><col></colgroup>
      <thead>
        <tr><th colspan="2">Catatan &amp; Rencana Tindak Lanjut (RTL)</th></tr>
      </thead>
      <tbody>
        <tr><th>Kelebihan / Praktik Baik Guru</th>
          <td><?= trim((string) $p['kelebihan']) !== ''
              ? nl2br(e(tetap_kata((string) $p['kelebihan']))) : '&hellip;' ?></td></tr>
        <tr><th>Area yang Perlu Ditingkatkan</th>
          <td><?= trim((string) $p['perbaikan']) !== ''
              ? nl2br(e(tetap_kata((string) $p['perbaikan']))) : '&hellip;' ?></td></tr>
        <tr><th>Rencana Penguatan / Pembinaan</th>
          <td><?= trim((string) $p['rtl']) !== ''
              ? nl2br(e(tetap_kata((string) $p['rtl']))) : '&hellip;' ?></td></tr>
      </tbody>
    </table>

    <?php // DITETAPKAN DI / PADA TANGGAL (v1.109.1) — kotak tabel kecil di KANAN supaya label &
          // nilainya sejajar dan tidak menempel ke tepi area cetak. ?>
    <table class="pkg-tetap-cetak" id="tblTetapPkgCetak">
      <tr><th>Ditetapkan di</th>
        <td><?= e($tempatTeks !== '' ? tetap_kata($tempatTeks) : '-') ?></td></tr>
      <tr><th>Pada Tanggal</th><td><?= e($tanggalTeks) ?></td></tr>
    </table>

    <?php
    // Tanda tangan: GURU YANG DINILAI di KIRI, KEPALA SEKOLAH/MADRASAH (PENILAI) di KANAN.
    // Baris "tempat/tanggal" sudah dicetak di atas keduanya, jadi kedua sisi memakai blok yang sama
    // supaya NAMA-nya sejajar.
    ?>
    <div class="sheet-foot dua-ttd">
      <?php ttd_blok('Guru yang Dinilai,', $namaGuru, $nipGuru, 'sheet-sign sheet-sign-kiri'); ?>
      <?php ttd_blok(jabatan_kepala() . ' / Penilai,', (string) ($p['penilai_nama'] ?? ''),
            (string) ($p['penilai_nip'] ?? ''), 'sheet-sign sheet-sign-kanan'); ?>
    </div>

    <p class="xs mut center" style="margin-top:16px">
      Dicetak dari <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> pada <?= e(tanggal_id(date('Y-m-d'))) ?>
      <?= $tempatTeks !== '' ? '- ' . e($tempatTeks) : '' ?>.
    </p>
  </div>
</div>

<?php if ($auto): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
<?php terjemahkan_selesai(); ?>
