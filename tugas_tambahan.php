<?php
/**
 * DAFTAR TUGAS TAMBAHAN GURU — **HANYA LIHAT** untuk Admin Operator & Kepala Sekolah/Madrasah.
 *
 * Permintaan pemilik (berlaku sekarang):
 *   "Daftar Tugas Tambahan yang ada di akun operator harus sama atau bersumber dari Daftar Tugas
 *    tambahan yang diinput oleh Super Admin. Hapus fitur Tambah Tugas Tambahan yang ada di akun
 *    operator."
 *
 * Karena itu halaman ini **tidak punya formulir/tombol pengubahan sama sekali** (tambah, ubah,
 * urut, aktif/nonaktif, hapus sudah DIHAPUS). Sumber daftarnya satu: menu **Kelola Tugas Tambahan**
 * (`tugas_menu.php`, Super Admin) — daftar yang sama dipakai sebagai pilihan centang pada form Data
 * Guru. Halaman ini berguna sebagai daftar rujukan (dibuka lewat tombol "Daftar" di form Data Guru)
 * dan hanya menampilkan keadaan daftar tersebut beserta jumlah guru pemakainya.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

// Halaman daftar: operator & kepala sekolah/madrasah (dan Super Admin dalam konteks sekolah).
$u = require_pengaturan();

// Setiap POST ditolak: seluruh Pengubahan data dilakukan Super Admin pada menu Kelola Tugas Tambahan.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tolak403('Daftar Tugas Tambahan diisi oleh Super Admin pada menu Kelola Tugas Tambahan, '
        . 'jadi halaman ini hanya untuk melihat.');
}

$rows = tugas_tambahan_list(false);
$aktif = 0;
foreach ($rows as $r) {
    if ((int) $r['aktif'] === 1) {
        $aktif++;
    }
}
$dipakai = 0;
foreach ($rows as $r) {
    $dipakai += (int) $r['jml_guru'];
}

page_top('Tugas Tambahan', 'Satu daftar untuk semua ' . istilah_sekolah('sekolah')
    . ' &middot; diisi Super Admin &middot; hanya lihat');
?>
<div class="alert alert-ok" style="background:rgba(201,162,39,.08);border-color:rgba(201,162,39,.5)">
  <?= ikon('star', 16) ?>
  <span>Daftar ini <b>bersumber dari daftar menu Tugas Tambahan</b> yang diisi <b>Super Admin</b>
    (menu <b>Kelola Tugas Tambahan</b>) — jadi tidak ada daftar terpisah lagi dan tidak bisa
    ditambah/diubah dari halaman ini. Tugas yang <b>diaktifkan</b> Super Admin muncul sebagai pilihan
    centang pada form <b>Data Guru</b>; tugas nonaktif tetap tercatat pada guru yang sudah
    memakainya. Bila perlu menambah atau mengubah nama tugas, hubungi <b>Super Admin</b>.</span>
</div>

<div class="card">
  <div class="card-head">
    <div>
      <h2>Daftar Tugas Tambahan</h2>
      <p><?= count($rows) ?> data &middot; <?= (int) $aktif ?> aktif &middot; <?= (int) $dipakai ?> pemakaian oleh guru</p>
    </div>
    <div class="flex">
      <a class="btn btn-ghost btn-sm" href="guru.php"><?= ikon('users', 14) ?> Data Guru</a>
      <?php if (is_super()): ?>
        <a class="btn btn-primary btn-sm" href="tugas_menu.php"><?= ikon('edit', 14) ?> Kelola Tugas Tambahan</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="empty">
      <?= ikon('star', 34) ?>
      <h3>Belum ada Tugas Tambahan</h3>
      <p>Super Admin belum menambahkan/mengaktifkan satu pun menu Tugas Tambahan. Hubungi Super Admin
        untuk mengisinya lewat menu <b>Kelola Tugas Tambahan</b>.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblTugas">
        <thead>
          <tr><th style="width:44px" class="ctr">Urut</th><th>Nama Tugas Tambahan</th>
            <th class="ctr">Guru</th><th class="ctr">Status</th>
            <th class="ctr">Dipakai sebagai pilihan Data Guru</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="ctr"><?= (int) $r['urutan'] ?></td>
              <td><b><?= e((string) $r['nama']) ?></b>
                <div class="mut xs">kode: <?= e((string) $r['kode']) ?></div></td>
              <td class="ctr"><?= (int) $r['jml_guru'] ?></td>
              <td class="ctr">
                <span class="pill <?= (int) $r['aktif'] === 1 ? 'pill-ok' : 'pill-gray' ?>"><?= (int) $r['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span>
              </td>
              <td class="ctr"><?= (int) $r['aktif'] === 1 ? 'Ya' : 'Tidak' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint">Tugas yang <b>nonaktif</b> tidak muncul sebagai pilihan pada form Data Guru,
      tetapi data guru yang sudah memakainya tetap tersimpan &amp; tetap tercetak. Daftar ini
      <b>tidak dapat diubah</b> dari akun operator/kepala — Super Admin mengelolanya pada menu
      <b>Kelola Tugas Tambahan</b>.</p>
  <?php endif; ?>
</div>
<?php
page_bottom();
