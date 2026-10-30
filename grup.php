<?php
/**
 * PENGATURAN "GABUNG GRUP e-GURU" — halaman Super Admin (`grup.php`).
 *
 * Permintaan pemilik: *"Tambahkan menu baru di akun super admin untuk mengedit fitur Gabung Grup
 * e-Guru. Inputan berupa: Judul Grup, Link Grup, Keterangan"*.
 *
 * Kartu "Gabung Grup e-GURU" tampil pada dashboard akun Kepala Sekolah/Madrasah, Admin Operator, dan
 * Guru (lihat `kartu_grup_eguru()` di inc/helpers.php). Karena kartunya tampil untuk SEMUA sekolah,
 * isinya bersifat TINGKAT APLIKASI: disimpan di tabel `app_pengaturan` dengan kunci `grup_judul`,
 * `grup_url`, dan `grup_ket`. Konstanta `GRUP_EGURU_JUDUL`/`GRUP_EGURU_URL`/`GRUP_EGURU_KET`
 * (inc/config.php) menjadi NILAI BAWAAN bila belum pernah diubah.
 *
 * Halaman ini KHUSUS Super Admin (peran lain 403) dan lintas sekolah → istilah generik "Sekolah".
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_super();
// Halaman lintas sekolah/madrasah → istilah generik "Sekolah".
$GLOBALS['__vs_istilah_nonaktif'] = true;

$galat = [];
$tampilkanForm = false;   // true = validasi gagal → halaman dirender ulang dengan isian dikembalikan

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'simpan') {
        $judul = trim((string) ($_POST['judul'] ?? ''));
        $url = trim((string) ($_POST['url'] ?? ''));
        $ket = trim((string) ($_POST['keterangan'] ?? ''));

        // Judul & tautan wajib; keterangan boleh dikosongkan (kartunya tetap tampil rapi).
        if ($judul === '') {
            $galat[] = 'Judul Grup wajib diisi.';
        }
        if ($url === '') {
            $galat[] = 'Link Grup wajib diisi.';
        } elseif (!grup_eguru_url_sah($url)) {
            $galat[] = 'Link Grup harus berupa alamat lengkap yang diawali http:// atau https:// '
                . '(mis. https://chat.whatsapp.com/…).';
        }
        if (strlen($judul) > GRUP_EGURU_JUDUL_MAKS) {
            $galat[] = 'Judul Grup terlalu panjang (maksimal ' . GRUP_EGURU_JUDUL_MAKS . ' karakter).';
        }
        if (strlen($url) > GRUP_EGURU_URL_MAKS) {
            $galat[] = 'Link Grup terlalu panjang (maksimal ' . GRUP_EGURU_URL_MAKS . ' karakter).';
        }
        if (strlen($ket) > GRUP_EGURU_KET_MAKS) {
            $galat[] = 'Keterangan terlalu panjang (maksimal ' . GRUP_EGURU_KET_MAKS . ' karakter).';
        }

        if ($galat) {
            // PENTING: jangan mengalihkan halaman saat validasi gagal — pesan galat dan isian
            // pengguna harus tetap terlihat (pola $tampilkanForm).
            $tampilkanForm = true;
        } else {
            grup_eguru_simpan($judul, $url, $ket);
            flash('Pengaturan Gabung Grup e-GURU disimpan. Kartu pada dashboard kini menampilkan '
                . 'perubahan itu.');
            redirect('grup.php');
        }
    } elseif ($aksi === 'bawaan') {
        // Kembalikan ke nilai bawaan aplikasi (konstanta di inc/config.php).
        app_setting_set('grup_judul', GRUP_EGURU_JUDUL);
        app_setting_set('grup_url', GRUP_EGURU_URL);
        app_setting_set('grup_ket', GRUP_EGURU_KET);
        flash('Pengaturan Gabung Grup e-GURU dikembalikan ke bawaan aplikasi.', 'warn');
        redirect('grup.php');
    } else {
        redirect('grup.php');
    }
}

// Isian: dari POST saat validasi gagal, kalau tidak dari pengaturan tersimpan.
$judul = $tampilkanForm ? trim((string) ($_POST['judul'] ?? '')) : grup_eguru_judul();
$url = $tampilkanForm ? trim((string) ($_POST['url'] ?? '')) : grup_eguru_url();
$ket = $tampilkanForm ? trim((string) ($_POST['keterangan'] ?? '')) : grup_eguru_ket();
$pakaiBawaan = (grup_eguru_judul() === GRUP_EGURU_JUDUL)
    && (grup_eguru_url() === GRUP_EGURU_URL)
    && (grup_eguru_ket() === GRUP_EGURU_KET);

page_top('Gabung Grup e-GURU', 'Super Admin &middot; judul, link, dan keterangan kartu grup pada dashboard');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('whatsapp', 16) ?> Gabung Grup e-GURU</h2>
      <p>Mengatur kartu <b>Gabung Grup e-GURU</b> yang tampil pada <b>dashboard</b> akun
        Kepala Sekolah/Madrasah, Admin Operator, dan Guru — sejajar dengan kartu <b>Pengawas Bina</b>.
        Perubahan berlaku untuk semua sekolah/madrasah.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= $pakaiBawaan ? 'masih bawaan' : 'sudah disesuaikan' ?></span>
      <?php if (!$pakaiBawaan): ?>
        <form method="post" data-confirm="Kembalikan judul, link, dan keterangan ke bawaan aplikasi?">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="bawaan">
          <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('prev', 14) ?> Kembalikan Bawaan</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <form method="post" class="form-grid" id="formGrup">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">

    <?php foreach ($galat as $g): ?>
      <div class="alert alert-err"><?= ikon('alert', 16) ?><span><?= e($g) ?></span></div>
    <?php endforeach; ?>

    <div class="grid g2" style="gap:16px">
      <div>
        <label class="f" for="judul">Judul Grup *</label>
        <input type="text" id="judul" name="judul" required maxlength="<?= (int) GRUP_EGURU_JUDUL_MAKS ?>"
               value="<?= e($judul) ?>" placeholder="mis. Gabung Grup e-GURU">
        <div class="hint">Judul kartu pada dashboard (maksimal <?= (int) GRUP_EGURU_JUDUL_MAKS ?> karakter).</div>
      </div>
      <div>
        <label class="f" for="url">Link Grup *</label>
        <input type="url" id="url" name="url" required maxlength="<?= (int) GRUP_EGURU_URL_MAKS ?>"
               value="<?= e($url) ?>" placeholder="https://chat.whatsapp.com/…">
        <div class="hint">Wajib diawali <b>http://</b> atau <b>https://</b> — tombol pada kartu membuka
          tautan ini di tab baru.</div>
      </div>
    </div>

    <div>
      <label class="f" for="keterangan">Keterangan</label>
      <textarea id="keterangan" name="keterangan" rows="3" maxlength="<?= (int) GRUP_EGURU_KET_MAKS ?>"
                placeholder="mis. Dapatkan info menarik dan update aplikasi dengan bergabung bersama grup premium e-Guru"><?= e($ket) ?></textarea>
      <div class="hint">Kalimat yang tampil di bawah judul kartu (maksimal
        <?= (int) GRUP_EGURU_KET_MAKS ?> karakter). Boleh dikosongkan.</div>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Pengaturan</button>
      <span class="xs mut">Perubahan langsung terlihat pada dashboard akun kepala sekolah/madrasah, operator, dan guru.</span>
    </div>
  </form>
</div>

<?php // PRATINJAU: kartu yang sama persis dengan yang tampil pada dashboard, supaya Super Admin tahu
      // hasilnya tanpa harus berpindah halaman. ?>
<div class="card">
  <div class="card-head">
    <div><h2><?= ikon('eye', 16) ?> Pratinjau Kartu</h2>
      <p>Tampilan kartu pada dashboard (posisinya sejajar dengan kartu Pengawas Bina).</p></div>
  </div>
  <div class="grid g2 atas-dashboard">
    <div>
      <?php kartu_grup_eguru(); ?>
    </div>
    <div>
      <div class="card card-pengawas">
        <div class="card-head">
          <div><h2><?= ikon('eye', 16) ?> Pengawas Bina</h2>
            <p>Contoh kartu di sebelahnya (diisi otomatis dari data pengawas bina).</p></div>
        </div>
        <div class="bina-kosong">
          <?= ikon('users', 22) ?>
          <span>Kartu ini diisi otomatis dari <b>Akun Pengawas</b> — tidak diatur di halaman ini.</span>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
page_bottom();
