<?php
/**
 * KELOLA MENU TUGAS TAMBAHAN (khusus Super Admin) — `tugas_menu.php`.
 *
 * Permintaan pemilik: "Diatas data master tambahkan Tugas Tambahan yang isi menunya: Kurikulum,
 * Kesiswaan, Sarana & Prasarana, Pembina OSIM/OSIS, Pembina Pramuka, Pembina PMR, Wali Kelas,
 * Tata Usaha, Kepala Perpus, Kepala Lab. Menu-menu tersebut akan muncul jika diaktifkan oleh Super
 * Admin. Nama menu juga bisa di edit oleh super admin. Dan untuk menu-menu tersebut isinya tulis
 * 'Masih dalam Pengembangan'. Dan pengguna yang diaktifkan tugas tambahan (yang dipilihkan) yang
 * bisa membuka menu tersebut nantinya."
 *
 * Isi halaman (permintaan pemilik):
 *   1. tabel menu bawaan — **ubah NAMA menu**, **aktif/nonaktif** (begitu diaktifkan, menu muncul di
 *      sidebar SEMUA pengguna, di atas Data Master), dan urutan ▲▼;
 *   2. keterangan bahwa **penugasan (siapa yang boleh membuka isinya) dilakukan Admin Operator atau
 *      Kepala Sekolah/Madrasah** pada menu "Pengaturan Tugas Tambahan" — BUKAN di halaman ini.
 *   3. **MENAMBAH tugas tambahan BARU** (permintaan pemilik: "Pada Menu kelola tugas tambahan pada
 *      halaman super admin tambahkan fitur untuk menambah tugas tambahan") — di atas 10 menu bawaan.
 *      Kode menu dibuat OTOMATIS dari namanya (`tugas_menu_kode_dari_nama()`) karena `kode` tidak
 *      dapat diubah setelah dibuat (dipakai pada URL `tugas.php?kode=…`); namanya tidak boleh kembar
 *      dan isinya sama seperti menu papan pengembangan lain: **"Masih dalam Pengembangan"**.
 *
 * Halaman ini LINTAS sekolah/madrasah (daftar menunya global) sehingga memakai istilah generik
 * "Sekolah".
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_super();
// Halaman lintas sekolah/madrasah → istilah generik "Sekolah".
$GLOBALS['__vs_istilah_nonaktif'] = true;

$galat = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    // MENAMBAH tugas tambahan BARU (permintaan pemilik). Kode menu dibuat otomatis dari namanya
    // karena `kode` dipakai pada URL `tugas.php?kode=…` dan tidak dapat diubah setelah dibuat.
    if ($aksi === 'tambah') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        if ($nama === '') {
            flash('Nama tugas tambahan wajib diisi.', 'err');
            redirect('tugas_menu.php#formTambah');
        }
        if (strlen($nama) > 60) {
            $nama = substr($nama, 0, 60);
        }
        if (tugas_menu_nama_terpakai($nama)) {
            flash('Sudah ada menu Tugas Tambahan bernama "' . $nama . '". Pakai nama lain, atau ubah '
                . 'menu yang sudah ada lewat tombol Ubah.', 'err');
            redirect('tugas_menu.php#formTambah');
        }
        $kode = tugas_menu_kode_dari_nama($nama);
        $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) FROM tugas_menu', [], 0) + 10;
        q('INSERT INTO tugas_menu (kode, nama, urutan, aktif, dibuat) VALUES (?,?,?,?,?)',
            [$kode, $nama, $urutan, $aktif, date('Y-m-d H:i:s')]);
        flash('Tugas Tambahan "' . $nama . '" ditambahkan (kode ' . $kode . ') ' . ($aktif === 1
            ? 'dan langsung tampil di sidebar pengguna yang ditugaskan.'
            : '(belum diaktifkan, jadi belum tampil di sidebar).'), $aktif === 1 ? 'ok' : 'warn');
        redirect('tugas_menu.php');
    }

    if ($aksi === 'simpan_nama' || $aksi === 'simpan_menu') {
        $t = tugas_menu_by_id($id);
        if (!$t) {
            flash('Menu Tugas Tambahan tidak ditemukan.', 'err');
            redirect('tugas_menu.php');
        }
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        if ($nama === '') {
            flash('Nama menu wajib diisi.', 'err');
            redirect('tugas_menu.php?edit=' . $id . '#formMenu');
        }
        if (strlen($nama) > 60) {
            $nama = substr($nama, 0, 60);
        }
        q('UPDATE tugas_menu SET nama = ?, aktif = ?, diubah = ? WHERE id = ?',
            [$nama, $aktif, date('Y-m-d H:i:s'), $id]);
        flash('Menu "' . $nama . '" ' . ($aktif === 1
            ? 'disimpan dan akan tampil di sidebar pengguna yang ditugaskan.'
            : 'disimpan (belum diaktifkan, jadi belum tampil di sidebar).'), $aktif === 1 ? 'ok' : 'warn');
        redirect('tugas_menu.php');
    }

    if ($aksi === 'toggle') {
        $t = tugas_menu_by_id($id);
        if (!$t) {
            flash('Menu Tugas Tambahan tidak ditemukan.', 'err');
            redirect('tugas_menu.php');
        }
        q('UPDATE tugas_menu SET aktif = ?, diubah = ? WHERE id = ?',
            [(int) $t['aktif'] === 1 ? 0 : 1, date('Y-m-d H:i:s'), $id]);
        flash('Menu "' . $t['nama'] . '" ' . ((int) $t['aktif'] === 1
            ? 'dinonaktifkan — tidak lagi tampil di sidebar.'
            : 'diaktifkan — siap ditampilkan kepada pengguna yang ditugaskan.'), 'warn');
        redirect('tugas_menu.php');
    }

    if ($aksi === 'geser') {
        $t = tugas_menu_by_id($id);
        if ($t && tugas_menu_geser($id, (string) ($_POST['arah'] ?? '') === 'naik' ? 'naik' : 'turun')) {
            flash('Urutan menu "' . $t['nama'] . '" dipindahkan.', 'warn');
        }
        redirect('tugas_menu.php');
    }

    redirect('tugas_menu.php');
}

$daftar = tugas_menu_list();
$jumlahAktif = 0;
foreach ($daftar as $t) {
    if ((int) $t['aktif'] === 1) {
        $jumlahAktif++;
    }
}

// Ringkasan: berapa pengguna yang ditugaskan pada tiap menu (penugasan diatur operator/kepala).
$jumlahDitugaskan = [];
foreach (all('SELECT tugas_id, COUNT(*) AS jumlah FROM user_tugas GROUP BY tugas_id') as $r) {
    $jumlahDitugaskan[(int) $r['tugas_id']] = (int) $r['jumlah'];
}

page_top('Menu Tugas Tambahan', 'Super Admin &middot; ' . $jumlahAktif . ' dari ' . count($daftar) . ' menu diaktifkan');
?>
<div class="alert alert-ok" style="background:rgba(201,162,39,.08);border-color:rgba(201,162,39,.5)">
  <?= ikon('star', 16) ?>
  <span>Menu yang <b>diaktifkan</b> di bawah ini langsung tampil pada sidebar <b>SEMUA pengguna</b>
    (di atas Data Master). <b>Isinya hanya dapat dibuka</b> pengguna yang ditugaskan oleh
    <b>Admin Operator</b> atau <b>Kepala Sekolah/Madrasah</b>. Isi setiap menu saat ini:
    <b><?= e(TUGAS_ISI_PESAN) ?></b>. Di halaman ini juga bisa <b>menambah tugas tambahan baru</b>
    di samping 10 menu bawaan.</span>
</div>

<?php // Kartu DAFTAR MENU lebih dulu (isi utama halaman), kartu "Tambah Tugas Tambahan" di bawahnya. ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('star', 16) ?> Daftar Menu Tugas Tambahan</h2>
      <p>Ubah nama menu, aktifkan/nonaktifkan, dan atur urutannya. Menu <b>nonaktif</b> belum tampil
        di sidebar siapa pun; begitu <b>diaktifkan</b>, menu langsung muncul untuk semua pengguna.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $jumlahAktif ?> aktif</span>
      <span class="chip"><?= count($daftar) ?> menu</span>
      <a class="btn btn-primary btn-sm" href="<?= e('tugas_menu.php') ?>#formTambah"><?= ikon('plus', 15) ?> Tambah Tugas Tambahan</a>
      <a class="btn btn-ghost btn-sm" href="<?= e('tugas_menu.php') ?>#keterangan"><?= ikon('users', 15) ?> Siapa yang Boleh Membuka?</a>
    </div>
  </div>

  <div class="table-wrap">
    <table class="tbl" id="tblMenu">
      <thead><tr><th class="ctr" style="width:34px">No</th><th>Nama Menu</th><th>Kode</th>
        <th class="ctr">Status</th><th class="ctr">Ditugaskan</th><th class="ctr" style="width:190px">Tindakan</th></tr></thead>
      <tbody>
      <?php $n = 0; foreach ($daftar as $t): $n++; $tid = (int) $t['id']; ?>
        <tr>
          <td class="ctr"><?= $n ?></td>
          <td><b><?= e((string) $t['nama']) ?></b></td>
          <td class="mut xs"><?= e((string) $t['kode']) ?></td>
          <td class="ctr">
            <span class="pill <?= (int) $t['aktif'] === 1 ? 'pill-ok' : 'pill-gray' ?>">
              <?= (int) $t['aktif'] === 1 ? 'Diaktifkan' : 'Nonaktif' ?>
            </span>
          </td>
          <td class="ctr"><?= (int) ($jumlahDitugaskan[$tid] ?? 0) ?></td>
          <td>
            <div class="row-actions">
              <a class="btn btn-ghost btn-sm" href="tugas_menu.php?edit=<?= $tid ?>#formMenu"><?= ikon('edit', 14) ?> Ubah</a>
              <a class="btn btn-ghost btn-sm" href="tugas.php?kode=<?= e((string) $t['kode']) ?>" target="_blank"><?= ikon('eye', 14) ?> Lihat</a>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="geser">
                <input type="hidden" name="id" value="<?= $tid ?>">
                <button class="btn btn-ghost btn-sm" type="submit" name="arah" value="naik" title="Naikkan urutan"><?= ikon('up', 14) ?></button>
                <button class="btn btn-ghost btn-sm" type="submit" name="arah" value="turun" title="Turunkan urutan"><?= ikon('down', 14) ?></button>
              </form>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="toggle">
                <input type="hidden" name="id" value="<?= $tid ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('eye', 14) ?> <?= (int) $t['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php $edit = tugas_menu_by_id((int) ($_GET['edit'] ?? 0)); ?>
  <div class="divider"></div>
  <form method="post" class="apps-form" id="formMenu" style="margin-bottom:0">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan_nama">
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="apps-form-judul">
      <?= ikon($edit ? 'edit' : 'plus', 16) ?>
      <b><?= $edit ? 'Ubah Menu: ' . e((string) $edit['nama']) : 'Ubah Nama Menu (pilih "Ubah" pada tabel di atas)' ?></b>
      <?php if ($edit): ?><a class="xs" href="tugas_menu.php">(batal)</a><?php endif; ?>
    </div>
    <?php if ($edit): ?>
      <div class="form-grid">
        <div>
          <label class="f" for="nama">Nama Menu *</label>
          <input type="text" id="nama" name="nama" maxlength="60" required value="<?= e((string) $edit['nama']) ?>">
          <div class="hint">Nama inilah yang tampil di sidebar &amp; judul halaman. Kode
            <b><?= e((string) $edit['kode']) ?></b> sengaja tidak berubah agar tautan yang sudah dipakai tetap jalan.</div>
        </div>
        <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600">
          <input type="checkbox" name="aktif" value="1" style="width:16px;height:16px;accent-color:var(--accent)"
                 <?= (int) $edit['aktif'] === 1 ? 'checked' : '' ?>> Aktifkan menu ini (tampil di sidebar pengguna yang ditugaskan)
        </label>
        <div class="flex">
          <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Menu</button>
        </div>
      </div>
    <?php else: ?>
      <p class="hint" style="margin:0">Klik <b>Ubah</b> pada salah satu baris untuk mengganti namanya
        atau mengaktifkan/menonaktifkannya.</p>
    <?php endif; ?>
  </form>
</div>

<?php // MENAMBAH tugas tambahan BARU (permintaan pemilik). Kode dibuat otomatis dari nama menu. ?>
<div class="card" id="tambahTugas">
  <div class="card-head">
    <div>
      <h2><?= ikon('plus', 16) ?> Tambah Tugas Tambahan</h2>
      <p>Tambahkan menu Tugas Tambahan <b>baru</b> di samping menu bawaan — misalnya
        <b>Wakasek Humas</b>, <b>Kesiswaan &amp; Humas</b>, atau <b>Pembina Rohis</b>. Menu baru
        langsung masuk daftar di atas dan dapat diubah, diurutkan, serta ditugaskan seperti menu
        lain. Isinya sementara berbunyi <b><?= e(TUGAS_ISI_PESAN) ?></b>.</p>
    </div>
  </div>
  <form method="post" class="apps-form" id="formTambah" style="margin-bottom:0">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="tambah">
    <div class="apps-form-judul"><?= ikon('plus', 16) ?> <b>Tugas Tambahan Baru</b></div>
    <div class="form-grid">
      <div>
        <label class="f" for="namaBaru">Nama Tugas Tambahan *</label>
        <input type="text" id="namaBaru" name="nama" maxlength="60" required
               placeholder="mis. Wakasek Humas">
        <div class="hint">Nama inilah yang tampil di sidebar &amp; judul halamannya. Nama yang sudah
          dipakai menu lain tidak dapat dipakai lagi. Kode menu dibuat otomatis dari nama ini
          (huruf kecil dengan pemisah tanda hubung) dan <b>tidak dapat diubah</b> setelah
          ditambahkan — karena itu tidak perlu diisi.</div>
      </div>
      <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600">
        <input type="checkbox" name="aktif" value="1" style="width:16px;height:16px;accent-color:var(--accent)" checked>
        Aktifkan menu ini sekarang (tampil di sidebar; isinya dapat dibuka pengguna yang ditugaskan)
      </label>
      <div class="flex">
        <button class="btn btn-primary" type="submit"><?= ikon('plus', 16) ?> Tambah Tugas Tambahan</button>
        <span class="hint" style="margin:0">Bila centangnya dilepas, menu tetap dibuat tetapi belum
          tampil di sidebar sampai diaktifkan pada tabel di atas.</span>
      </div>
    </div>
  </form>
</div>

<div class="card" id="keterangan">
  <div class="card-head">
    <div>
      <h2><?= ikon('users', 16) ?> Siapa yang Boleh Membuka Isinya?</h2>
      <p>Penugasan <b>tidak</b> dilakukan di sini. Super Admin <b>menambah</b> menu baru,
        mengaktifkan menu &amp; mengubah namanya, sedangkan <b>Admin Operator</b> atau
        <b>Kepala Sekolah/Madrasah</b> tiap sekolah menentukan pengguna yang boleh membuka isi menu
        (menu <b>Pengaturan Tugas Tambahan</b>).</p>
    </div>
  </div>
  <ul class="apps-catatan">
    <li><b>Menambah tugas tambahan baru</b> — tulis namanya pada kartu
      <b>Tambah Tugas Tambahan</b> di atas. Kode menu dibuat otomatis dari namanya dan tidak dapat
      diubah (dipakai pada tautan menu), sedangkan <b>namanya tetap bisa diubah</b> kapan saja lewat
      tombol Ubah.</li>
    <li><b>Tampil untuk semua pengguna</b> — begitu diaktifkan di halaman ini, menu muncul pada
      sidebar SEMUA pengguna (di atas Data Master).</li>
    <li><b>Isinya terbatas</b> — hanya pengguna yang ditugaskan oleh Admin Operator / Kepala
      Sekolah/Madrasah yang dapat membukanya; pengguna lain menerima pesan bahwa menu itu belum
      ditugaskan kepadanya.</li>
    <li><b>Isi menu</b> saat ini: <b><?= e(TUGAS_ISI_PESAN) ?></b>.</li>
    <li><b>Daftar penugasan per sekolah</b> dapat dilihat pada menu <b>Pengaturan Tugas Tambahan</b>
      di akun Admin Operator / Kepala Sekolah/Madrasah.</li>
  </ul>
</div>
<?php
page_bottom();
