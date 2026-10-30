<?php
/**
 * LOGO APLIKASI (khusus Super Admin) — tingkat APLIKASI, bukan per sekolah.
 *
 * Permintaan pemilik: logo aplikasi diganti dengan gambar miliknya sendiri. Berkas diunggah lewat
 * halaman ini, diteruskan ke media proxy platform dari SISI SERVER (`upload_logo_app.php`), dan yang
 * disimpan ke basis data hanya URL CDN hasilnya (`app_pengaturan` kunci `logo_app`).
 *
 * Logo itu tampil pada: halaman masuk (portal), halaman pendaftaran publik, sidebar seluruh pengguna,
 * dan sebagai favicon. Selama belum diunggah, tampilannya memakai lambang bawaan (ikon SVG) —
 * jadi mengganti logo TIDAK pernah membuat halaman kosong.
 *
 * Halaman ini KHUSUS Super Admin (peran lain 403) dan lintas sekolah → istilah generik "Sekolah".
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_super();
// Halaman lintas sekolah/madrasah → istilah generik "Sekolah".
$GLOBALS['__vs_istilah_nonaktif'] = true;

$logo = logo_aplikasi();
$ada = ada_logo_aplikasi();

page_top('Logo Aplikasi', 'Super Admin &middot; logo aplikasi pada halaman masuk, pendaftaran, dan sidebar');
?>
<div class="grid g21">
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= ikon('star', 16) ?> Logo Aplikasi</h2>
        <p>Dipakai pada <b>halaman masuk</b>, <b>halaman pendaftaran</b>, dan <b>sidebar</b> seluruh
          pengguna. Berlaku untuk semua sekolah/madrasah.</p>
      </div>
      <div class="kanan">
        <?php if ($ada): ?>
          <span class="chip on">logo unggahan</span>
        <?php else: ?>
          <span class="chip">lambang bawaan</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="alert alert-info">
      <?= ikon('alert', 16) ?>
      <span>Disarankan gambar <b>persegi (1:1)</b>, minimal <b>512 x 512 piksel</b>, format
        <b>PNG</b> (latar transparan) atau <b>SVG</b>, maksimal <?= e(maks_unggah_teks()) ?>. Logo tampil kecil (46–62 px),
        jadi hindari detail yang terlalu halus.</span>
    </div>

    <div class="grid g2" style="align-items:center;gap:20px">
      <div class="center">
        <div class="logo-kotak">
          <?php if ($ada): ?>
            <img src="<?= e($logo) ?>" alt="Logo aplikasi">
          <?php else: ?>
            <span class="mut xs">Belum ada logo<br>(lambang bawaan dipakai)</span>
          <?php endif; ?>
        </div>
        <p class="mut xs" style="margin-top:8px"><?= $ada ? 'Logo yang sedang dipakai' : 'Lambang bawaan aplikasi' ?></p>
      </div>
      <div>
        <div class="section-title">Contoh pemakaian</div>
        <div class="center logo-contoh">
          <?php if ($ada): ?>
            <div class="contoh-baris">
              <div class="portal-logo portal-logo-img"><img src="<?= e($logo) ?>" alt="Contoh di halaman masuk"></div>
              <span class="mut xs">halaman masuk (62 px)</span>
            </div>
            <div class="contoh-baris">
              <div class="brand-logo brand-logo-img"><img src="<?= e($logo) ?>" alt="Contoh di sidebar"></div>
              <span class="mut xs">sidebar (46 px)</span>
            </div>
          <?php else: ?>
            <div class="contoh-baris">
              <div class="portal-logo"><?= ikon('mosque', 30) ?></div>
              <span class="mut xs">halaman masuk (62 px)</span>
            </div>
            <div class="contoh-baris">
              <div class="brand-logo"><?= ikon('school', 24) ?></div>
              <span class="mut xs">sidebar (46 px)</span>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="divider"></div>

    <form method="post" action="upload_logo_app.php" enctype="multipart/form-data" id="formLogoApp">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="unggah">
      <div>
        <label class="f" for="fileLogoApp">Pilih Berkas Logo *</label>
        <input type="file" id="fileLogoApp" name="logo" accept="image/*" required>
        <div class="hint" id="pesanLogoApp">PNG, JPG, GIF, WEBP, atau SVG — maksimal <?= e(maks_unggah_teks()) ?>.</div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= ikon('upload', 16) ?> Unggah / Ganti Logo</button>
        <span class="xs mut">Perubahan langsung berlaku setelah unggahan selesai.</span>
      </div>
    </form>

    <?php if ($ada): ?>
      <form method="post" action="upload_logo_app.php" class="mt16"
            data-confirm="Kembalikan logo aplikasi ke lambang bawaan?">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="hapus">
        <button class="btn btn-danger" type="submit"><?= ikon('trash', 16) ?> Kembalikan Lambang Bawaan</button>
      </form>
    <?php endif; ?>

    <p class="mut xs mt16" style="margin-bottom:0">
      Logo disimpan di layanan penyimpanan berkas aplikasi; yang dicatat pada basis data hanya
      alamat gambarnya. Mengganti logo <b>tidak</b> mengubah logo sekolah pada kop dokumen cetak —
      logo kop diatur terpisah di menu <b>Identitas &amp; Pengaturan</b> oleh operator sekolah.
    </p>
  </div>

  <div class="card">
    <div class="card-head"><h2><?= ikon('book', 16) ?> Petunjuk</h2></div>
    <ul class="list-ok">
      <li>Gunakan gambar <b>persegi</b> agar tidak terpotong saat ditampilkan kecil.</li>
      <li>Untuk logo bertulisan panjang, pakai <b>PNG latar transparan</b> atau <b>SVG</b> supaya tetap
        tajam pada latar hijau sidebar.</li>
      <li>Bila logo baru tidak tampak, <b>muat ulang halaman</b> dengan Ctrl+F5 (peramban mungkin masih
        menyimpan gambar lama).</li>
      <li>Logo ini <b>tingkat aplikasi</b> — dipakai bersama seluruh sekolah/madrasah.</li>
      <li>Nama aplikasi diatur pada berkas <code>inc/config.php</code> (<code>APP_NAME</code>) —
        saat ini: <b><?= e(APP_NAME) ?></b>.</li>
    </ul>
  </div>
</div>

<script>
(function () {
  // Pratinjau berkas sebelum diunggah (pelengkap; unggahan tetap bekerja tanpa JavaScript).
  var inp = document.getElementById('fileLogoApp');
  if (!inp) { return; }
  inp.addEventListener('change', function () {
    var f = inp.files && inp.files[0];
    var pesan = document.getElementById('pesanLogoApp');
    if (!f) { return; }
    if (f.size > <?= (int) UNGGAH_MAKS_BYTE ?>) {
      if (pesan) { pesan.textContent = 'Ukuran gambar maksimal <?= e(maks_unggah_teks()) ?>.'; }
      inp.value = '';
      return;
    }
    if (pesan) { pesan.textContent = 'Berkas siap diunggah: ' + f.name; }
    var baca = new FileReader();
    baca.onload = function (e) {
      var kotak = document.querySelector('#formLogoApp').closest('.card').querySelector('.logo-kotak');
      if (kotak) { kotak.innerHTML = '<img src="' + e.target.result + '" alt="Pratinjau logo">'; }
    };
    baca.readAsDataURL(f);
  });
})();
</script>
<?php
page_bottom();
