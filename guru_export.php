<?php
/**
 * EKSPOR DATA GURU — mengunduh daftar guru sebagai berkas **Excel (.xlsx)** atau **CSV**
 * agar bisa diunggah (diimpor) ke aplikasi lain, mis. e-PERPUS (Data Anggota → Impor Excel/CSV).
 *
 * Catatan penting (jangan diubah tanpa alasan):
 *  - Kolom **Jenis Anggota** (berisi "Guru") WAJIB ada dan diletakkan SEBELUM kolom
 *    "Jenis Kelamin"/"Status": pengimpor e-PERPUS memetakan jenis anggota dari kolom pertama
 *    yang namanya berawalan "jenis"/"status", sehingga tanpa kolom itu guru bisa tercatat
 *    sebagai anggota "umum" (dibuktikan lewat uji tests_eguru_ekspor.sh bagian 7).
 *  - Kolom "NIP/NUPTK" sengaja diberi nama itu (dikenali pengimpor e-GURU lain sebagai NIP);
 *    pada pengimpor e-PERPUS kolom ini boleh dipetakan ke "Kode Anggota" secara manual, atau
 *    dibiarkan supaya kode anggota dibuat otomatis oleh aplikasi tujuan.
 *  - Berkas Excel dibuat di PERAMBAN memakai SheetJS yang sudah di-vendor (sama seperti halaman
 *    impor); berkas CSV dilayani server (csv_kirim) sehingga ekspor tetap bekerja walau skrip
 *    gagal dimuat. Pengimpor berbasis SheetJS (termasuk e-PERPUS) membaca CSV sebagai CP1252,
 *    jadi untuk data berisi karakter khusus lebih baik memakai berkas Excel.
 *
 * Akses: Super Admin (dalam konteks sekolah) & Admin Operator — sama dengan tombol "Cetak Data Guru".
 * Kata sandi TIDAK pernah diekspor (disimpan sebagai hash) — hanya nama pengguna akunnya.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_master(); // Super Admin (dalam konteks sekolah) & Admin Operator; super tanpa konteks, kepala, guru 403

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
    flash('Belum ada data guru untuk diekspor pada filter yang dipilih.', 'warn');
    redirect('guru.php');
}

// ---------------------------------------------------------------- susun kolom & baris ekspor
$kolom = ['Nama', 'Jenis Anggota', 'NIP/NUPTK', 'Jenis Kelamin', 'Jabatan', 'Mata Pelajaran',
          'Kelas Diajar', 'Tugas Tambahan', 'Telepon', 'Email', 'Akun (Nama Pengguna)', 'Status', 'Tanggal Daftar'];

$data = [];
foreach ($rows as $g) {
    $mapelArr = trim((string) ($g['mapel_nama'] ?? '')) !== '' ? explode('||', (string) $g['mapel_nama']) : [];
    $kelasArr = trim((string) ($g['kelas_nama'] ?? '')) !== '' ? explode('||', (string) $g['kelas_nama']) : [];
    $tugasArr = trim((string) ($g['tugas_nama'] ?? '')) !== '' ? explode('||', (string) $g['tugas_nama']) : [];
    $nip = trim((string) ($g['nip'] ?? ''));
    if ($nip === '' || $nip === '-') {
        $nip = trim((string) ($g['nuptk'] ?? ''));
    }
    $data[] = [
        (string) $g['nama'],
        'Guru',
        $nip === '-' ? '' : $nip,
        (string) ($g['jenis_kelamin'] ?? ''),
        (string) ($g['jabatan'] ?? ''),
        implode(', ', $mapelArr),
        implode(', ', $kelasArr),
        implode(', ', $tugasArr),
        (string) ($g['telepon'] ?? ''),
        (string) ($g['email'] ?? ''),
        trim((string) ($g['username'] ?? '')),
        (int) $g['aktif'] === 1 ? 'Aktif' : 'Nonaktif',
        ekspor_tgl($g['dibuat'] ?? ''),
    ];
}

$labelFilter = array_filter([
    'cari' => $cari !== '' ? $cari : null,
    'mapel' => $fMapel > 0 ? $fMapel : null,
    'kelas' => $fKelas > 0 ? $fKelas : null,
    'akun' => $fAkun !== '' ? $fAkun : null,
    'status' => $fStatus !== '' ? $fStatus : null,
]);

$slugSekolah = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', nama_sekolah()), '-'));
$namaBerkas = 'data-guru' . ($slugSekolah !== '' ? '-' . $slugSekolah : '') . '-' . date('Y-m-d');

// Jalur CSV dilayani server (harus terjadi SEBELUM ada keluaran halaman).
if ((string) ($_GET['unduh'] ?? '') === 'csv') {
    csv_kirim($namaBerkas . '.csv', $kolom, $data);
}

page_top('Ekspor Data Guru');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2>Ekspor Data Guru</h2>
      <p>Unduh daftar guru sebagai berkas Excel (.xlsx) atau CSV. Berkasnya bisa diunggah
      (diimpor) ke aplikasi lain yang menyediakan menu impor Excel/CSV.</p>
    </div>
    <div class="flex">
      <a class="btn btn-ghost" href="guru_import.php"><?= ikon('upload', 16) ?> Impor Excel/CSV</a>
      <a class="btn btn-ghost" href="guru.php?<?= e(http_build_query($labelFilter)) ?>"><?= ikon('users', 16) ?> Data Guru</a>
    </div>
  </div>

  <div class="grid g3" style="gap:16px">
    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">1</div>
        <div><div class="butir-komponen">Data yang diekspor</div><div class="butir-indikator">Mengikuti filter yang sedang aktif di halaman Data Guru.</div></div>
      </div>
      <div class="chip-list mt16">
        <span class="chip on"><?= count($data) ?> baris</span>
        <span class="chip"><?= $fMapel > 0 ? e((string) mapel_nama($fMapel)) : 'Semua mata pelajaran' ?></span>
        <span class="chip"><?= $fKelas > 0 ? e((string) kelas_nama($fKelas)) : 'Semua kelas' ?></span>
        <span class="chip"><?= in_array($fStatus, ['1', '0'], true) ? ((int) $fStatus === 1 ? 'Aktif' : 'Nonaktif') : 'Semua status' ?></span>
      </div>
      <div class="hint">Ingin mengekspor sebagian data saja? Atur dulu filter di halaman Data Guru, lalu tekan tombol Ekspor di sana.</div>
    </div>

    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">2</div>
        <div><div class="butir-komponen">Unduh berkas</div><div class="butir-indikator"><b>Excel (.xlsx)</b> dianjurkan untuk diunggah ke aplikasi lain.</div></div>
      </div>
      <div class="mt16 flex">
        <button type="button" class="btn btn-primary" id="btnXlsx"
                data-berkas="<?= e($namaBerkas . '.xlsx') ?>" data-sheet="Data Guru"><?= ikon('download', 16) ?> Unduh Excel (.xlsx)</button>
        <a class="btn btn-ghost" href="guru_export.php?<?= e(http_build_query($labelFilter + ['unduh' => 'csv'])) ?>"><?= ikon('doc', 16) ?> Unduh CSV</a>
      </div>
      <div class="hint" id="xlsxInfo">Bila tombol Excel tidak berfungsi (skrip peramban diblokir), gunakan Unduh CSV.</div>
    </div>

    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">3</div>
        <div><div class="butir-komponen">Unggah di aplikasi lain</div><div class="butir-indikator">Mis. e-PERPUS → menu <b>Data Anggota</b> → <b>Impor Excel/CSV</b>.</div></div>
      </div>
      <div class="mt16 xs mut" style="line-height:1.6">
        Pilih berkas hasil ekspor ini, cocokkan kolomnya, lalu simpan. Kolom <b>Jenis Anggota</b> berisi
        "Guru" agar data tercatat sebagai anggota guru di aplikasi tujuan. Kolom <b>NIP/NUPTK</b> boleh
        dipetakan ke <i>Kode Anggota</i> atau dibiarkan supaya kode anggota dibuat otomatis. Kata sandi
        akun tidak ikut diekspor (disimpan teracak), begitu pula status keanggotaan yang diatur aplikasi tujuan.
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
