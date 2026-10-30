<?php
/**
 * Lembar cetak DAFTAR HADIR MEETING/PERTEMUAN — `cetak_meeting.php` (A4 potrait). e-GURU v1.73.0.
 *
 * Permintaan pemilik: *"… Ketika diaktifkan maka akan muncul di seluruh akun binaannya untuk di
 * tanda tangani secara online (tanda tangan digital seperti di notulen rapat). Dokumen daftar hadir
 * meeting/pertemuan ini akan menjadi rekapan setiap pertemuan yang dilakukan oleh pengawas."*
 *
 * Pemakaian:
 *   - `?id=ID`                → SELURUH binaan yang diaktifkan, satu lembar per binaan (pengawas)
 *   - `?id=ID&sekolah=ID`     → satu lembar binaan itu (pengawas & akun sekolah itu)
 *
 * Isi tiap lembar: kop sekolah/madrasah, identitas pertemuan, tabel peserta (No, Nama, Jabatan,
 * Tanda Tangan), dan tanda tangan **Pemimpin Meeting** (pengawas) di sisi KANAN. Halaman ini
 * SENGAJA hanya-BACA (tanpa tombol tanda tangan) — penandatanganan dilakukan pada halaman
 * `meeting.php` (pengawas) dan `meeting_hadir.php` (akun lembaga).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kop.php';
require_once __DIR__ . '/inc/meeting.php';

ob_start();
$GLOBALS['__vs_buf_level'] = ob_get_level();

$u = require_login();
$meetingId = (int) ($_GET['id'] ?? 0);
$m = meeting_by_id($meetingId);
if (!$m) {
    flash('Meeting tidak ditemukan.', 'err');
    redirect(is_pengawas() ? 'meeting.php' : 'meeting_hadir.php');
}

// ---------------------------------------------------------------- hak akses (anti-IDOR)
$super = is_super();
$konteks = current_sekolah();
$konteksId = $konteks ? (int) $konteks['id'] : 0;
$pemilik = is_pengawas() && (int) ($m['pengawas_id'] ?? 0) === (int) $u['id'];
$diaktifkan = meeting_sekolah_ids($meetingId);

$mintaSekolah = (int) ($_GET['sekolah'] ?? 0);
$bolehLihat = [];
if ($pemilik) {
    $bolehLihat = $diaktifkan;
} elseif ($super && $konteksId > 0 && in_array($konteksId, $diaktifkan, true)) {
    $bolehLihat = [$konteksId];
} elseif (in_array(peran(), meeting_peran_peserta(), true) && $konteksId > 0
    && (int) ($u['sekolah_id'] ?? 0) === $konteksId && in_array($konteksId, $diaktifkan, true)) {
    $bolehLihat = [$konteksId];
}
if (!$bolehLihat) {
    tolak403('Daftar hadir meeting ini tidak tersedia untuk akun Anda.');
}
if ($mintaSekolah > 0) {
    if (!in_array($mintaSekolah, $bolehLihat, true)) {
        tolak403('Sekolah/madrasah itu bukan bagian dari meeting ini pada akun Anda.');
    }
    $bolehLihat = [$mintaSekolah];
}
if (!$bolehLihat) {
    flash('Belum ada binaan yang diaktifkan pada meeting ini.', 'err');
    redirect('meeting.php?id=' . $meetingId);
}

$daftarSekolah = [];
foreach ($bolehLihat as $sid) {
    $s = sekolah_by_id($sid);
    if ($s) {
        $daftarSekolah[] = $s;
    }
}
if (!$daftarSekolah) {
    flash('Sekolah/madrasah pada meeting ini tidak ditemukan.', 'err');
    redirect('meeting.php?id=' . $meetingId);
}

$autoPrint = isset($_GET['auto']);
$kembaliKe = $pemilik ? 'meeting.php?id=' . $meetingId : 'meeting_hadir.php?id=' . $meetingId;
$gambarPemimpin = trim((string) ($m['ttd_url'] ?? ''));
$qPrint = array_filter(['id' => $meetingId,
    'sekolah' => count($daftarSekolah) === 1 ? (int) $daftarSekolah[0]['id'] : null]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Daftar Hadir Meeting — <?= e((string) $m['judul']) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<style>
  body{background:#F3F0FF;padding:26px 18px 40px}
  .sheet-wrap{max-width:840px;margin:0 auto}
  .toolbar{max-width:840px;margin:0 auto 18px;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
  .ket-print{font-size:.72rem;color:#635F82;margin-top:10px;line-height:1.5}
  /* Nama pertemuan boleh panjang → biarkan membungkus, jangan memaksa satu baris. */
  .meet-judul{font-size:.92rem;font-weight:700;margin:0 0 2px}
  table.sheet.hadir td,table.sheet.hadir th{padding:6px 7px}
  table.sheet.hadir td.ttd-sel{height:44px}
  table.sheet.hadir .ttd-img{max-height:38px}
  .meet-pemimpin{margin-top:16px}
  .meet-pemimpin .nama{font-weight:700;margin-top:2px}
  .meet-sheet + .meet-sheet{margin-top:22px}
  .meet-sheet{break-inside:avoid}
  @media screen and (max-width:900px){
    .print-sheet{padding:18px 14px}
    .sheet-meta{grid-template-columns:minmax(0,1fr)}
  }
  @media print{
    /* Tema menetapkan body{min-height:100vh} → saat dicetak peramban menambah halaman kedua kosong. */
    html,body{min-height:0 !important;height:auto}
    body{background:#fff;padding:0}
    .toolbar,.no-print{display:none !important}
    .sheet-wrap{max-width:none}
    .print-sheet{box-shadow:none;border:none;padding:0;border-radius:0}
    .meet-sheet + .meet-sheet{break-before:page;margin-top:0}
    .sheet-foot{flex-direction:row !important;flex-wrap:nowrap;justify-content:flex-end}
  }
  @page{size:A4 portrait;margin:18mm 20mm}
</style>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<div class="toolbar no-print">
  <a class="btn btn-ghost" href="<?= e($kembaliKe) ?>">Kembali</a>
  <a class="btn btn-primary" href="cetak_meeting.php?<?= e(http_build_query($qPrint + ['auto' => 1])) ?>">
    <?= ikon('print', 16) ?> Cetak / Simpan PDF</a>
</div>

<div class="sheet-wrap">
  <div class="print-sheet">
    <?php foreach ($daftarSekolah as $i => $s):
        $sid = (int) $s['id'];
        $peserta = meeting_peserta($meetingId, $sid);
        $peta = meeting_hadir_peta($meetingId, $sid);
        ?>
      <div class="meet-sheet">
        <?php
        // Kop memakai sekolah pada konteks: untuk mencetak kop sekolah lain, konteks sekolah perlu
        // dipindahkan dulu. Karena itu kop dicetak lewat $s (bukan current_sekolah()) — lihat
        // kop_cetak_sekolah() di bawah bila sekolahnya bukan konteks aktif.
        kop_cetak_sekolah($s, 'Daftar Hadir Meeting/Pertemuan',
            [$m['judul'] !== '' ? 'Pertemuan: ' . (string) $m['judul'] : '']);
        ?>

        <?php sheet_meta([
            'Judul Pertemuan'    => (string) $m['judul'],
            'Tanggal Pertemuan'  => tanggal_id((string) $m['tanggal'], true),
            'Pemimpin Meeting'   => (string) ($m['pengawas_nama'] ?? '-'),
            'Jumlah Akun Lembaga' => (string) count($peserta),
            'Sudah Tanda Tangan' => count($peta) . ' akun',
        ]); ?>

        <table class="sheet hadir">
          <thead>
            <tr>
              <th class="ctr" style="width:34px">No</th>
              <th style="width:38%">Nama</th>
              <th style="width:26%">Jabatan</th>
              <th class="ctr">Tanda Tangan</th>
            </tr>
          </thead>
          <tbody>
          <?php $n = 0; foreach ($peserta as $p): $n++; $baris = $peta[(int) $p['id']] ?? null; ?>
            <tr>
              <td class="ctr"><?= $n ?></td>
              <td><?= e((string) $p['nama']) ?></td>
              <td><?= e(role_label((string) $p['role'])) ?></td>
              <td class="ctr ttd-sel">
                <?= meeting_ttd_gambar((string) ($baris['url'] ?? ''), (string) $p['nama']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$peserta): ?>
            <tr><td colspan="4" class="ctr mut">Belum ada akun kepala sekolah/madrasah, admin operator,
              atau guru aktif pada sekolah/madrasah ini.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>

        <div class="ket-print">
          Daftar hadir ini dibuat oleh pengawas bina melalui aplikasi <?= e(APP_NAME) ?> dan menjadi
          <b>rekapan pertemuan</b> tersebut. Tanda tangan dibubuhkan langsung oleh masing-masing akun
          lembaga pada layar sentuh; <?= count($peta) ?> dari <?= count($peserta) ?> akun sudah
          menandatangani.
        </div>

        <!-- Tanda tangan Pemimpin Meeting (pengawas) — sisi KANAN, sejajar dengan kebiasaan lembar lain. -->
        <div class="sheet-foot kanan">
          <div class="sheet-sign meet-pemimpin">
            <div class="ttd-tempat"><?= e(ttd_tempat_tanggal()) ?></div>
            <div>Pemimpin Meeting</div>
            <div class="space">
              <?= meeting_ttd_gambar($gambarPemimpin, (string) ($m['pengawas_nama'] ?? ''), 'ttd-img') ?>
            </div>
            <span class="nama"><?= e((string) ($m['pengawas_nama'] ?? '-')) ?></span>
          </div>
        </div>

        <p class="xs mut center" style="margin-top:14px">
          Dicetak dari <?= e(APP_NAME) ?> v<?= e(APP_VERSION) ?> pada <?= e(tanggal_id(date('Y-m-d'))) ?>.
        </p>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
</body>
</html>
<?php terjemahkan_selesai(); ?>
