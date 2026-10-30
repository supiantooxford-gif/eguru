<?php
/**
 * EKSPOR DATA SISWA — mengunduh daftar siswa sebagai berkas **Excel (.xlsx)** atau **CSV**
 * agar bisa diunggah (diimpor) ke aplikasi lain, mis. e-PERPUS (Data Anggota → Impor Excel/CSV)
 * atau e-GURU sekolah/madrasah lain (Data Siswa → Impor Excel/CSV).
 *
 * Catatan penting (jangan diubah tanpa alasan):
 *  - Urutan & nama kolom sengaja dipilih supaya PENGIMPOR OTOMATIS mengenalinya:
 *    "Nama", "Jenis Anggota", "NIS", "NISN", "Jenis Kelamin", "Kelas", "Tanggal Lahir",
 *    "Nama Wali", "Telepon". Kolom **Jenis Anggota** (berisi "Siswa") WAJIB ada dan diletakkan
 *    SEBELUM "Jenis Kelamin"/"Status": pengimpor e-PERPUS memetakan kolom "Jenis Anggota" dari
 *    kolom pertama yang namanya berawalan "jenis" — tanpa kolom itu "Jenis Kelamin" (mis. "L")
 *    yang terpakai dan siswa malah tercatat sebagai anggota "umum" (sudah terbukti lewat uji).
 *  - Tanggal dikirim dalam format `Y-m-d` (lihat ekspor_tgl()) karena pengimpor hanya menerima ISO.
 *  - Tanggal Daftar diletakkan SEBELUM "Tanggal Lahir" supaya tidak tertukar oleh pemetaan otomatis.
 *  - Berkas Excel dibuat di PERAMBAN memakai SheetJS yang sudah di-vendor (sama seperti halaman impor);
 *    berkas CSV dilayani server (csv_kirim) sehingga ekspor tetap bekerja walau skrip gagal dimuat.
 *    CSV juga lebih aman dipakai untuk pengimpor yang membaca berkas sebagai teks; namun perlu
 *    diketahui pengimpor berbasis SheetJS (termasuk e-PERPUS) membaca CSV sebagai CP1252, sehingga
 *    untuk data berisi huruf/karakter khusus lebih baik memakai berkas Excel.
 *
 * Akses: Super Admin (dalam konteks sekolah) & Admin Operator — sama dengan tombol "Cetak Data Siswa"
 * (super tanpa konteks tidak dialihkan, melainkan 403, karena penjaganya require_master()).
 * Filter mengikuti halaman Data Siswa (kelas, status siswa, aktif/nonaktif, kata kunci).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_master(); // Super Admin (dalam konteks sekolah) & Admin Operator; super tanpa konteks, kepala, guru 403

// ---------------------------------------------------------------- filter (sama dengan siswa.php)
$fKelas = (int) ($_GET['kelas'] ?? 0);
$cari = trim((string) ($_GET['cari'] ?? ''));
$fStatus = (string) ($_GET['status'] ?? '');
$fLulus = in_array((string) ($_GET['status_siswa'] ?? ''), ['aktif', 'lulus'], true) ? (string) $_GET['status_siswa'] : '';

$w = ['s.sekolah_id = ?'];
$p = [sekolah_id()];
if ($fKelas > 0) {
    if (!kelas_by_id($fKelas)) {
        tolak403('Kelas itu bukan milik sekolah/madrasah yang sedang dikelola.');
    }
    $w[] = 's.kelas_id = ?';
    $p[] = $fKelas;
}
if (in_array($fStatus, ['1', '0'], true)) {
    $w[] = 's.aktif = ' . (int) $fStatus;
}
if ($fLulus === 'lulus') {
    $w[] = "s.status = 'lulus'";
} elseif ($fLulus === 'aktif') {
    $w[] = "(s.status = 'aktif' OR s.status IS NULL)";
}
if ($cari !== '') {
    $w[] = '(s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR s.nama_wali LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($p, $like, $like, $like, $like);
}
$where = ' WHERE ' . implode(' AND ', $w);

$rows = all('SELECT s.*, k.nama AS kelas_nama
    FROM siswa s LEFT JOIN kelas k ON k.id = s.kelas_id' . $where . '
    ORDER BY k.urutan, k.nama, s.nama', $p);

if (!$rows) {
    flash('Belum ada data siswa untuk diekspor pada filter yang dipilih.', 'warn');
    redirect('siswa.php');
}

// ---------------------------------------------------------------- susun kolom & baris ekspor
$kolom = ['Nama', 'Jenis Anggota', 'NIS', 'NISN', 'Jenis Kelamin', 'Kelas',
          'Tanggal Daftar', 'Tanggal Lahir', 'Nama Wali', 'Telepon', 'Alamat', 'Status Siswa'];

$data = [];
foreach ($rows as $s) {
    $status = (int) $s['aktif'] !== 1 ? 'Nonaktif' : ((string) $s['status'] === 'lulus' ? 'Lulus' : 'Aktif');
    $data[] = [
        (string) $s['nama'],
        'Siswa',
        (string) ($s['nis'] ?? ''),
        (string) ($s['nisn'] ?? ''),
        (string) ($s['jenis_kelamin'] ?? ''),
        (string) ($s['kelas_nama'] ?? ''),
        ekspor_tgl($s['dibuat'] ?? ''),
        ekspor_tgl($s['tanggal_lahir'] ?? ''),
        (string) ($s['nama_wali'] ?? ''),
        (string) ($s['telepon'] ?? ''),
        (string) ($s['alamat'] ?? ''),
        $status,
    ];
}

$labelFilter = array_filter([
    'cari' => $cari !== '' ? $cari : null,
    'kelas' => $fKelas > 0 ? $fKelas : null,
    'status' => $fStatus !== '' ? $fStatus : null,
    'status_siswa' => $fLulus !== '' ? $fLulus : null,
]);

$slugSekolah = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', nama_sekolah()), '-'));
$namaBerkas = 'data-siswa' . ($slugSekolah !== '' ? '-' . $slugSekolah : '') . '-' . date('Y-m-d');

// Jalur CSV dilayani server (harus terjadi SEBELUM ada keluaran halaman).
if ((string) ($_GET['unduh'] ?? '') === 'csv') {
    csv_kirim($namaBerkas . '.csv', $kolom, $data);
}

page_top('Ekspor Data Siswa');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2>Ekspor Data Siswa</h2>
      <p>Unduh daftar siswa sebagai berkas Excel (.xlsx) atau CSV. Berkasnya bisa diunggah
      (diimpor) ke aplikasi lain yang menyediakan menu impor Excel/CSV.</p>
    </div>
    <div class="flex">
      <a class="btn btn-ghost" href="siswa_import.php"><?= ikon('upload', 16) ?> Impor Excel/CSV</a>
      <a class="btn btn-ghost" href="siswa.php?<?= e(http_build_query($labelFilter)) ?>"><?= ikon('student', 16) ?> Data Siswa</a>
    </div>
  </div>

  <div class="grid g3" style="gap:16px">
    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">1</div>
        <div><div class="butir-komponen">Data yang diekspor</div><div class="butir-indikator">Mengikuti filter yang sedang aktif di halaman Data Siswa.</div></div>
      </div>
      <div class="chip-list mt16">
        <span class="chip on"><?= count($data) ?> baris</span>
        <span class="chip"><?= $fKelas > 0 ? e((string) kelas_nama($fKelas)) : 'Semua kelas' ?></span>
        <span class="chip"><?= $cari !== '' ? 'Cari: ' . e($cari) : 'Semua nama' ?></span>
        <span class="chip"><?= $fLulus === 'lulus' ? 'Status: Lulus' : ($fLulus === 'aktif' ? 'Status: Aktif' : (in_array($fStatus, ['1', '0'], true) ? ((int) $fStatus === 1 ? 'Aktif' : 'Nonaktif') : 'Semua status')) ?></span>
      </div>
      <div class="hint">Ingin mengekspor sebagian data saja? Atur dulu filter di halaman Data Siswa, lalu tekan tombol Ekspor di sana.</div>
    </div>

    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">2</div>
        <div><div class="butir-komponen">Unduh berkas</div><div class="butir-indikator"><b>Excel (.xlsx)</b> dianjurkan untuk diunggah ke aplikasi lain.</div></div>
      </div>
      <div class="mt16 flex">
        <button type="button" class="btn btn-primary" id="btnXlsx"
                data-berkas="<?= e($namaBerkas . '.xlsx') ?>" data-sheet="Data Siswa"><?= ikon('download', 16) ?> Unduh Excel (.xlsx)</button>
        <a class="btn btn-ghost" href="siswa_export.php?<?= e(http_build_query($labelFilter + ['unduh' => 'csv'])) ?>"><?= ikon('doc', 16) ?> Unduh CSV</a>
      </div>
      <div class="hint" id="xlsxInfo">Bila tombol Excel tidak berfungsi (skrip peramban diblokir), gunakan Unduh CSV.</div>
    </div>

    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">3</div>
        <div><div class="butir-komponen">Unggah di aplikasi lain</div><div class="butir-indikator">Mis. e-PERPUS → menu <b>Data Anggota</b> → <b>Impor Excel/CSV</b>.</div></div>
      </div>
      <div class="mt16 xs mut" style="line-height:1.6">
        Pilih berkas hasil ekspor ini, cocokkan kolomnya (biasanya sudah terisi otomatis), lalu simpan.
        Nama kolom sudah disesuaikan dengan pengimpor aplikasi lain: <b>Nama</b>, <b>Jenis Anggota</b>
        (berisi "Siswa" agar tercatat sebagai anggota siswa), <b>NIS</b>, <b>NISN</b>, <b>Jenis Kelamin</b>,
        <b>Kelas</b>, <b>Tanggal Daftar</b>, <b>Tanggal Lahir</b>, <b>Nama Wali</b>, <b>Telepon</b>.
        Kolom yang tidak diperlukan di aplikasi tujuan boleh dibiarkan tidak dipetakan.
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Pratinjau Berkas</h2><p>Menampilkan 25 baris pertama sebagai pemeriksaan sebelum diimpor di aplikasi lain.</p></div>
    <span class="chip"><?= count($data) ?> baris siap diekspor</span>
  </div>
  <div class="table-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th class="ctr" style="width:38px">#</th>
          <?php foreach ($kolom as $k): ?><th><?= e($k) ?></th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_slice($data, 0, 25) as $i => $r): ?>
          <tr>
            <td class="ctr"><?= $i + 1 ?></td>
            <?php foreach ($r as $ci => $v): ?>
              <td><?= $ci === 0 ? '<b>' . e($v) . '</b>' : e($v !== '' ? $v : '-') ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (count($data) > 25): ?>
    <p class="xs mut mt8">Menampilkan 25 dari <?= count($data) ?> baris. Seluruh baris ikut diekspor.</p>
  <?php endif; ?>
</div>

<?php // Data untuk berkas Excel: dibaca skrip di bawah (tanpa permintaan tambahan ke server). ?>
<script id="dataEkspor" type="application/json"><?= json_encode(array_merge([$kolom], $data), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/vendor/xlsx.full.min.js"></script>
<script>
(function () {
  var sumber = document.getElementById('dataEkspor');
  var btn = document.getElementById('btnXlsx');
  var info = document.getElementById('xlsxInfo');
  if (!sumber || !btn) return;
  btn.addEventListener('click', function () {
    if (typeof XLSX === 'undefined') {
      if (info) info.textContent = 'Pustaka Excel tidak termuat — silakan pakai Unduh CSV.';
      return;
    }
    try {
      var aoa = JSON.parse(sumber.textContent);
      var ws = XLSX.utils.aoa_to_sheet(aoa);
      ws['!cols'] = aoa[0].map(function (h) { return { wch: Math.min(34, Math.max(12, String(h).length + 6)) }; });
      var wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, btn.getAttribute('data-sheet') || 'Data');
      XLSX.writeFile(wb, btn.getAttribute('data-berkas') || 'data.xlsx');
      if (info) info.textContent = 'Berkas Excel dibuat — periksa folder Unduhan.';
    } catch (e) {
      if (info) info.textContent = 'Gagal membuat berkas Excel (' + e.message + ') — silakan pakai Unduh CSV.';
    }
  });
})();
</script>
<?php
page_bottom();
