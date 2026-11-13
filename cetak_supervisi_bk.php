<?php
/**
 * LEMBAR CETAK HASIL SUPERVISI BIMBINGAN KONSELING (A4 potrait).
 *
 * Permintaan pemilik: *"Contoh instrumen supervisi dan penilainnya seperti yang saya kirim. Hasil
 * supervisi otomatis akan masuk ke Guru yang ditugaskan Bimbingan Konseling dan ada rekapannya di
 * akun pengawas."* — lembar ini adalah bentuk cetak dari penilaian itu: identitas madrasah & guru BK,
 * tabel 22 butir dengan kolom **Dokumen / Ketersediaan (Ada-Tidak) / Skor (2-1-0) / Keterangan**
 * persis contoh, baris **Jumlah Skor**, lalu **Kesimpulan** & **Saran**, ditutup tanda tangan
 * **Konselor/Guru BK di KIRI** dan **Pengawas Madrasah di KANAN** (baris "tempat, tanggal" di atas
 * nama pengawas — pola seluruh lembar cetak aplikasi ini).
 *
 * Hak akses: pengawas binaan sekolah itu, guru BK bersangkutan, kepala sekolah/madrasah & Admin
 * Operator sekolah itu, serta Super Admin. Selain itu 403. Halaman ini TIDAK memakai layout, jadi
 * memanggil ob_start() + terjemahkan_selesai() sendiri.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/bk.php';

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
$sup = bk_supervisi_by_id($id, $sekolahIds);
if (!$sup) {
    flash('Supervisi itu tidak ditemukan, atau bukan sekolah/madrasah yang dapat Anda buka.', 'err');
    redirect(is_pengawas() ? 'supervisi_bk.php' : 'index.php');
}

// Pemeriksaan akhir per peran (anti-IDOR).
$sid = (int) $sup['sekolah_id'];
if (is_pengawas()) {
    if (!pengawas_punya($uid, $sid)) {
        tolak403('Sekolah/madrasah itu bukan binaan Anda.');
    }
} elseif (is_super()) {
    // Super Admin: boleh melihat (pemilik aplikasi).
} elseif (is_guru()) {
    if ((int) $sup['guru_id'] !== guru_id_saya()) {
        tolak403('Lembar supervisi ini milik guru Bimbingan Konseling lain.');
    }
} elseif (is_kepala() || is_operator()) {
    if ($sid !== sekolah_id()) {
        tolak403('Lembar supervisi ini milik sekolah/madrasah lain.');
    }
} else {
    tolak403('Anda tidak berhak membuka lembar supervisi ini.');
}

// Kop & tanda tangan memakai data sekolah supervisi ini — bukan sekolah yang kebetulan aktif.
$sekolah = one('SELECT * FROM sekolah WHERE id = ?', [$sid]);
if (!$sekolah) {
    flash('Sekolah/madrasah supervisi itu sudah tidak ada.', 'err');
    redirect(is_pengawas() ? 'supervisi_bk.php' : 'index.php');
}
if (!is_pengawas() && !is_super() && current_sekolah() && (int) current_sekolah()['id'] !== $sid) {
    set_sekolah_context($sid);
}

$nilai = bk_supervisi_nilai($id);
$dok = bk_dokumen_peta($sid, (int) $sup['guru_id']);
$auto = isset($_GET['auto']) && (string) $_GET['auto'] !== '0';
$namaSekolah = (string) ($sekolah['nama'] ?? '');
$tempat = trim((string) ($sekolah['kecamatan'] ?? ''));
$hariTanggal = tanggal_id((string) $sup['tanggal'], true);
$jabatanGuru = trim((string) ($sup['guru_jabatan'] ?? ''));
$sertifikasi = (string) ($sup['guru_sertifikasi'] ?? '') === 'sertifikasi'
    ? 'Sudah'
    : ((string) ($sup['guru_sertifikasi'] ?? '') === 'belum' ? 'Belum' : '-');
$alamat = trim((string) ($sekolah['alamat'] ?? ''));
$wilayah = function_exists('alamat_wilayah_dari') ? alamat_wilayah_dari($sekolah) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Supervisi Bimbingan dan Konseling — <?= e(tetap_kata($namaSekolah)) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:860px;margin:0 auto}
  .toolbar{max-width:860px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .sheet-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
  /* Identitas hasil supervisi (Nama Madrasah, Alamat, Hari/Tanggal, Guru BK, …) */
  .bk-identitas{width:100%;border-collapse:collapse;margin:14px 0 6px}
  .bk-identitas td{padding:3px 6px;font-size:.86rem;vertical-align:top;border:none}
  .bk-identitas td:first-child{width:150px}
  .bk-identitas td.pisah{width:14px;text-align:center}
  /* ⚠️ `table-layout:fixed` + lebar kolom PERSENTASE wajib: tanpa itu nama berkas yang panjang
     (mis. "Program-Tahunan-Semester-Ganjil-2026.pdf") membuat tabel melebar melewati area cetak
     A4 potrait (terukur 1000px pada viewport 1000px, padahal area cetak hanya ±680px). */
  table.sheet.bk-instrumen{width:100%;table-layout:fixed}
  table.sheet.bk-instrumen th,table.sheet.bk-instrumen td{padding:4px 5px;font-size:.74rem;line-height:1.3;
    overflow-wrap:anywhere;word-break:break-word}
  table.sheet.bk-instrumen th{font-size:.68rem;letter-spacing:0;vertical-align:middle}
  table.sheet.bk-instrumen .k-no{width:7%}
  table.sheet.bk-instrumen .k-dok{width:21%}
  table.sheet.bk-instrumen .k-ada{width:12%}
  table.sheet.bk-instrumen .k-skor{width:9%}
  table.sheet.bk-instrumen .k-ket{width:21%}
  table.sheet.bk-instrumen tr.bk-grup td{background:rgba(11,74,51,.07);font-weight:700}
  table.sheet.bk-instrumen tr.bk-sub td{padding-left:18px;font-style:italic}
  .bk-kesimpulan{margin-top:14px}
  .bk-kesimpulan table{width:100%;border-collapse:collapse}
  .bk-kesimpulan th{width:110px;text-align:left;vertical-align:top;font-size:.82rem;padding:4px 6px;border:none}
  .bk-kesimpulan td{font-size:.84rem;padding:4px 6px;vertical-align:top;border:none}
  @media screen and (max-width:760px){
    .print-sheet{padding:18px 14px}
    table.sheet.bk-instrumen{min-width:660px}
    .bk-identitas td:first-child{width:110px}
  }
  @media print{
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .sheet-wrap{max-width:none;width:100%}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0;width:100%;max-width:none}
    .sheet-scroll{overflow:visible}
    table.sheet.bk-instrumen{min-width:0}
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap}
  }
  @page{size:A4 portrait;margin:14mm 15mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <?php if (is_pengawas()): ?>
    <a class="btn btn-ghost" href="supervisi_bk.php?guru=<?= (int) $sup['guru_id'] ?>">Kembali</a>
  <?php elseif (is_guru()): ?>
    <a class="btn btn-ghost" href="tugas.php?kode=<?= e(BK_KODE_MENU) ?>">Kembali</a>
  <?php else: ?>
    <a class="btn btn-ghost" href="index.php">Kembali</a>
  <?php endif; ?>
  <a class="btn btn-primary" href="cetak_supervisi_bk.php?id=<?= $id ?>&amp;auto=1">
    <?= ikon('print', 16) ?> Cetak / Simpan PDF
  </a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php kop_cetak_sekolah($sekolah, 'SUPERVISI BIMBINGAN DAN KONSELING',
        ['Instrumen ' . bk_butir_total() . ' butir - skor maksimal ' . bk_skor_maks()]); ?>

    <table class="bk-identitas">
      <tr>
        <td>Nama Madrasah</td><td class="pisah">:</td>
        <td colspan="3"><b><?= e(tetap_kata($namaSekolah)) ?></b></td>
      </tr>
      <tr>
        <td>Alamat</td><td class="pisah">:</td>
        <td colspan="3"><?= e(tetap_kata(trim($alamat . ($wilayah !== '' ? ' - ' . $wilayah : '')) ?: '-')) ?></td>
      </tr>
      <tr>
        <td>Hari / Tanggal</td><td class="pisah">:</td>
        <td colspan="3"><?= e($hariTanggal) ?></td>
      </tr>
      <tr>
        <td>Guru BK / Konselor</td><td class="pisah">:</td>
        <td colspan="3"><b><?= e(tetap_kata((string) ($sup['guru_nama'] ?? '-'))) ?></b></td>
      </tr>
      <tr>
        <td>NIP</td><td class="pisah">:</td>
        <td><?= e(trim((string) ($sup['guru_nip'] ?? '')) !== '' ? (string) $sup['guru_nip'] : '-') ?></td>
        <td style="width:120px">Sertifikasi</td><td>: <?= e($sertifikasi) ?></td>
      </tr>
      <?php if ($jabatanGuru !== ''): ?>
        <tr>
          <td>Jabatan</td><td class="pisah">:</td>
          <td colspan="3"><?= e(tetap_kata($jabatanGuru)) ?></td>
        </tr>
      <?php endif; ?>
    </table>

    <div class="sheet-scroll">
      <table class="sheet bk-instrumen" id="tblInstrumenBk">
        <thead>
          <tr>
            <th class="ctr k-no">No</th>
            <th>Kegiatan / Komponen</th>
            <th class="k-dok">Dokumen</th>
            <th class="ctr k-ada">Ketersediaan<br>Ada / Tidak</th>
            <th class="ctr k-skor">Skor<br>2 / 1 / 0</th>
            <th class="k-ket">Keterangan</th>
          </tr>
        </thead>
        <tbody>
        <?php $nomor = 0; foreach (bk_instrumen() as $grup): ?>
          <tr class="bk-grup">
            <td class="ctr"><b><?= e($grup['kode']) ?></b></td>
            <td colspan="5"><b><?= e($grup['judul']) ?></b></td>
          </tr>
          <?php foreach ($grup['baris'] as $baris): ?>
            <?php if ($baris['jenis'] === 'sub'): ?>
              <tr class="bk-sub">
                <td class="ctr"><?= e($baris['no']) ?></td>
                <td colspan="5"><b><?= e($baris['label']) ?></b></td>
              </tr>
              <?php continue; ?>
            <?php endif; ?>
            <?php
            $nomor++;
            $n = $nilai[$nomor] ?? null;
            $d = $dok[$nomor] ?? null;
            ?>
            <tr>
              <td class="ctr"><?= e($baris['no']) ?></td>
              <td><?= e($baris['label']) ?></td>
              <td class="sm"><?= $d ? e(tetap_kata((string) ($d['nama_berkas'] !== '' ? $d['nama_berkas'] : 'Ada berkas'))) : '-' ?></td>
              <td class="ctr"><?= $n ? e(bk_ketersediaan_teks((string) $n['ketersediaan'])) : '-' ?></td>
              <td class="ctr"><?= $n && $n['skor'] !== null ? (int) $n['skor'] : '-' ?></td>
              <td class="sm"><?= $n && trim((string) $n['keterangan']) !== '' ? e(tetap_kata((string) $n['keterangan'])) : '-' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="4" class="ctr">Jumlah Skor</th>
            <th class="ctr"><?= (int) $sup['total_skor'] ?></th>
            <th class="ctr sm">maks. <?= (int) $sup['skor_maks'] ?></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="bk-kesimpulan">
      <table>
        <tr><th>Nilai Akhir</th><td><b><?= e(r1((float) $sup['nilai'])) ?></b>
          dari 100 — predikat <b><?= e((string) $sup['predikat']) ?></b>
          (<?= e(predikat_label((string) $sup['predikat'])) ?>)</td></tr>
        <tr><th>Kesimpulan</th><td><?= trim((string) $sup['kesimpulan']) !== ''
            ? nl2br(e(tetap_kata((string) $sup['kesimpulan']))) : '&hellip;' ?></td></tr>
        <tr><th>Saran</th><td><?= trim((string) $sup['saran']) !== ''
            ? nl2br(e(tetap_kata((string) $sup['saran']))) : '&hellip;' ?></td></tr>
      </table>
    </div>

    <?php
    // Tanda tangan: Konselor/Guru BK di KIRI, Pengawas Madrasah di KANAN.
    // Baris "tempat, tanggal" hanya pada sisi pengawas; sisi kiri memakai baris pengganjal supaya
    // kedua NAMA tercetak sejajar (pola seluruh lembar cetak aplikasi ini).
    ?>
    <div class="sheet-foot dua-ttd">
      <?php ttd_blok('Konselor / Guru BK,', (string) ($sup['guru_nama'] ?? ''), (string) ($sup['guru_nip'] ?? ''),
            'sheet-sign sheet-sign-kiri', false, true); ?>
      <?php ttd_blok('Pengawas Madrasah,', (string) ($sup['pengawas_nama'] ?? ''),
            bk_pengawas_nip($sekolah), 'sheet-sign sheet-sign-kanan', true, false); ?>
    </div>

    <p class="xs mut center" style="margin-top:16px">
      Dicetak dari <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> pada <?= e(tanggal_id(date('Y-m-d'))) ?>
      <?= $tempat !== '' ? '- ' . e($tempat) : '' ?>.
    </p>
  </div>
</div>

<?php if ($auto): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
<?php terjemahkan_selesai(); ?>
