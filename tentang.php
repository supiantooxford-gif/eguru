<?php
/**
 * HALAMAN SUPER ADMIN — "TENTANG APLIKASI (Halaman Masuk)" — `tentang.php`.
 *
 * Permintaan pemilik: *"Pada halaman depan di bagian tentang aplikasi tambahkan tempat foto, nama
 * dan keterangan dengan posisi atau letak dibuat sama dengan aplikasi supervisi"*.
 * Pilihan pemilik (jangan diubah tanpa bertanya):
 *   1. **DUA tempat foto** (persis seperti aplikasi Supervisi) dengan **nama di bawah masing-masing
 *      foto**;
 *   2. tulisan "Tentang Aplikasi" yang lama **DIGANTI** oleh keterangan yang diisi pemilik
 *      (keterangan sekaligus menjadi paragraf penjelasan pada halaman masuk);
 *   3. pengisiannya lewat **menu baru "Tentang Aplikasi"** di sidebar Super Admin.
 *
 * TATA LETAK pada halaman masuk mengikuti aplikasi Supervisi: kedua foto berdampingan di KIRI
 * (nama di bawah tiap foto) dan keterangan berada di SAMPING kanannya (lihat `.tentang-baris` di
 * `assets/style.css`); di layar sempit keterangan turun ke bawah dengan sendirinya.
 *
 * Isinya bersifat TINGKAT APLIKASI (halaman masuk dipakai semua sekolah/madrasah), jadi halaman ini
 * khusus **Super Admin** dan penyimpanannya memakai tabel `app_pengaturan`.
 * Satu formulir multipart mengurus semuanya supaya isian tidak pernah hilang separuh; foto diunggah
 * lewat media proxy platform dari sisi server (token tidak pernah sampai ke browser) dan yang
 * disimpan hanya URL CDN-nya.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_super();
// Halaman lintas sekolah/madrasah → istilah generik "Sekolah" (bukan diterjemahkan per jenis).
$GLOBALS['__vs_istilah_nonaktif'] = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Bila total kiriman melebihi post_max_size, PHP membuang SELURUH $_POST & $_FILES tanpa pesan
    // sehingga halaman terlihat "tidak terjadi apa-apa". Kenali keadaan itu dan beri keterangan.
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST) && empty($_FILES)) {
        flash('Ukuran kiriman melebihi batas server (' . ini_get('post_max_size')
            . '). Perkecil foto lalu simpan ulang.', 'err');
        redirect('tentang.php');
    }

    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'simpan') {
        $galat = [];
        foreach (tentang_slot_list() as $n) {
            // Bagian yang TIDAK dikirim tidak diubah (mis. kiriman yang hanya mengganti foto),
            // sehingga isian yang sudah ada tidak pernah terhapus tanpa sengaja.
            $nama = array_key_exists('nama_' . $n, $_POST) ? (string) $_POST['nama_' . $n] : null;
            $foto = null;   // null = bagian ini tidak diubah
            if (!empty($_POST['hapus_' . $n])) {
                $foto = '';
            } elseif (!empty($_FILES['foto_' . $n]['name'])) {
                $hasil = tentang_unggah_foto($n);
                if (empty($hasil['ok'])) {
                    $galat[] = 'Foto ' . $n . ': ' . (string) $hasil['pesan'];
                } else {
                    $foto = (string) $hasil['url'];
                }
            }
            tentang_simpan_slot($n, $nama, $foto);
        }
        if (array_key_exists('keterangan', $_POST)) {
            tentang_simpan_keterangan((string) $_POST['keterangan']);
        }

        if ($galat) {
            flash('Nama & keterangan tersimpan, tetapi ada foto yang gagal diunggah. ' . implode(' ', $galat), 'err');
        } else {
            flash('Isi "Tentang Aplikasi" disimpan dan langsung tampil pada halaman masuk.');
        }
        redirect('tentang.php');
    }
    redirect('tentang.php');
}

$isi = tentang_isi();
$paragraf = tentang_paragraf($isi['keterangan']);
page_top('Tentang Aplikasi (Halaman Masuk)', 'Isi kartu Tentang Aplikasi pada halaman depan');
?>
<div class="alert alert-ok" style="margin-bottom:18px">
  <?= ikon('star', 16) ?>
  <span>Isi di bawah ini tampil pada <b>halaman masuk</b>, di dalam kartu <b>Tentang Aplikasi</b>:
    <?= (int) TENTANG_FOTO_SLOT ?> tempat <b>foto <?= e(TENTANG_FOTO_RASIO) ?></b> dengan nama di bawah
    masing-masing foto, dan <b>keterangan/penjelasan</b> di sisi kanannya — tata letak yang sama
    dengan aplikasi Supervisi. Tampil untuk seluruh sekolah/madrasah (pengaturan tingkat aplikasi).</span>
</div>

<form method="post" enctype="multipart/form-data" class="grid" style="gap:20px" id="formTentang">
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="simpan">

  <div class="grid g2" style="gap:20px;align-items:start">
    <?php foreach (tentang_slot_list() as $n): $sl = $isi['slot'][$n]; ?>
      <div class="card" style="margin:0" id="cardTentang<?= $n ?>">
        <div class="card-head">
          <div>
            <h2>Tempat Foto <?= $n ?> (ukuran <?= e(TENTANG_FOTO_RASIO) ?>)</h2>
            <p>Foto potret <?= e(TENTANG_FOTO_RASIO) ?> dengan nama pemiliknya di bagian bawah.</p>
          </div>
          <span class="pill <?= $sl['foto'] !== '' ? 'pill-ok' : 'pill-gray' ?>">
            <?= $sl['foto'] !== '' ? 'Foto terpasang' : 'Belum ada foto' ?>
          </span>
        </div>

        <div class="tentang-admin">
          <div class="tentang-slot">
            <div class="tentang-bingkai" id="pratinjauBingkai<?= $n ?>">
              <?php if ($sl['foto'] !== ''): ?>
                <img src="<?= e($sl['foto']) ?>" alt="Pratinjau foto <?= $n ?>">
              <?php else: ?>
                <span class="tentang-kosong"><?= ikon('user', 26) ?><span class="xs">Foto <?= e(TENTANG_FOTO_RASIO) ?></span></span>
              <?php endif; ?>
            </div>
            <div class="tentang-nama" id="pratinjauNama<?= $n ?>">
              <?= $sl['nama'] !== '' ? e($sl['nama']) : '<span class="mut">(nama belum diisi)</span>' ?>
            </div>
          </div>

          <div class="tentang-isi">
            <div>
              <label class="f" for="nama_<?= $n ?>">Nama (tampil di bawah foto)</label>
              <input type="text" id="nama_<?= $n ?>" name="nama_<?= $n ?>" maxlength="<?= (int) TENTANG_NAMA_MAKS ?>"
                     value="<?= e($sl['nama']) ?>" placeholder="mis. Supianto, S.Pd.I"
                     data-pratinjau-nama="pratinjauNama<?= $n ?>">
            </div>
            <div class="mt16">
              <label class="f" for="foto_<?= $n ?>"><?= $sl['foto'] !== '' ? 'Ganti foto' : 'Pilih berkas foto' ?></label>
              <input type="file" id="foto_<?= $n ?>" name="foto_<?= $n ?>"
                     accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
                     data-pratinjau-foto="pratinjauBingkai<?= $n ?>">
              <div class="hint" id="hintFoto<?= $n ?>">
                Format PNG, JPG, GIF, WEBP, atau SVG &middot; maksimal <?= e(maks_unggah_teks()) ?> &middot; sebaiknya potret <?= e(TENTANG_FOTO_RASIO) ?>.
                <?= $sl['foto'] !== '' ? 'Mengunggah berkas baru akan menggantikan foto yang sekarang.' : '' ?>
              </div>
            </div>
            <?php if ($sl['foto'] !== ''): ?>
              <label class="f mt16" style="display:flex;gap:9px;align-items:center;font-weight:600">
                <input type="checkbox" name="hapus_<?= $n ?>" value="1" style="width:16px;height:16px;accent-color:var(--accent)">
                Hapus foto ini (nama &amp; keterangan tidak terpengaruh)
              </label>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card" style="margin:0" id="cardTentangKeterangan">
    <div class="card-head">
      <div>
        <h2>Keterangan / Penjelasan</h2>
        <p>Tampil di <b>sisi kanan kedua foto</b> pada kartu Tentang Aplikasi di halaman masuk.
          Tulisan ini juga <b>menggantikan</b> deskripsi tentang aplikasi yang dahulu tertulis tetap
          di halaman masuk.</p>
      </div>
      <span class="chip"><?= strlen($isi['keterangan']) ?> / <?= (int) TENTANG_KET_MAKS ?> karakter</span>
    </div>
    <textarea id="keterangan" name="keterangan" rows="8" maxlength="<?= (int) TENTANG_KET_MAKS ?>"
              placeholder="Tulis penjelasan tentang aplikasi ini. Pisahkan dengan satu baris kosong bila ingin membuat paragraf baru."><?= e($isi['keterangan']) ?></textarea>
    <div class="hint">
      Boleh beberapa paragraf — <b>pisahkan dengan satu baris kosong</b>. Kosongkan bila tidak ingin
      menampilkan keterangan.
    </div>
  </div>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit" id="btnTentang"><?= ikon('check', 16) ?> Simpan</button>
    <span class="xs mut" id="tentangStatus">Perubahan langsung tampil pada halaman masuk.</span>
  </div>
</form>

<div class="card" id="cardPratinjauTentang">
  <div class="card-head">
    <div>
      <h2>Pratinjau pada Halaman Masuk</h2>
      <p>Inilah blok yang tampil di dalam kartu <b>Tentang Aplikasi</b> setelah isian disimpan.</p>
    </div>
    <span class="pill <?= $isi['ada'] ? 'pill-ok' : 'pill-gray' ?>">
      <?= $isi['ada'] ? 'Tampil' : 'Belum tampil (isi belum ada)' ?>
    </span>
  </div>

  <?php if ($isi['ada']): ?>
    <?php // Pratinjau memakai markup & kelas yang SAMA dengan login.php supaya tampilannya meyakinkan. ?>
    <div class="portal-tentang" id="tentangAplikasi" style="margin-bottom:0;padding-bottom:0;border-bottom:none">
      <div class="tentang-baris">
        <?php if ($isi['jumlah_foto'] > 0 || $isi['slot'][1]['nama'] !== ''): ?>
          <div class="tentang-foto-grid">
            <?php foreach (tentang_slot_list() as $n): $sl = $isi['slot'][$n]; ?>
              <figure class="tentang-slot">
                <div class="tentang-bingkai">
                  <?php if ($sl['foto'] !== ''): ?>
                    <img src="<?= e($sl['foto']) ?>" alt="<?= e($sl['nama'] !== '' ? $sl['nama'] : 'Foto ' . $n) ?>" loading="lazy">
                  <?php else: ?>
                    <span class="tentang-kosong"><?= ikon('user', 26) ?></span>
                  <?php endif; ?>
                </div>
                <?php if ($sl['nama'] !== ''): ?>
                  <figcaption class="tentang-nama"><?= e($sl['nama']) ?></figcaption>
                <?php endif; ?>
              </figure>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($paragraf): ?>
          <div class="tentang-ket">
            <?php foreach ($paragraf as $p): ?>
              <p class="portal-lead"><?= nl2br(e($p)) ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <p class="sm mut" style="margin:10px 0 0">Di halaman masuk, blok ini berada di dalam kartu
      <b>Tentang Aplikasi</b> (di atas kartu "Cara Mulai &amp; Keamanan").</p>
  <?php else: ?>
    <p class="sm mut" style="margin:0">Belum ada isi. Unggah foto, isi nama, atau tulis keterangan lalu
      tekan <b>Simpan</b> — bloknya akan muncul pada kartu Tentang Aplikasi di halaman masuk.</p>
  <?php endif; ?>
</div>
<?php
page_bottom();
