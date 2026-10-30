<?php
/**
 * PETUNJUK PENGGUNAAN — `petunjuk.php`.
 *
 * Permintaan pemilik: *"Di akun guru tambahkan Menu Petunjuk Penggunaan (maksudnya petunjuk
 * penggunaan setiap menu yang ada di akun guru agar guru bisa memahami fitur yang ada di dalam menu
 * tersebut). Menu letakkan dibawah Akun Saya."*
 * Pilihan pemilik (jangan diubah tanpa bertanya):
 *   1. **semua peran** (guru, kepala sekolah/madrasah, admin operator, pengawas) — isi menyesuaikan
 *      menu yang benar-benar ada pada peran itu;
 *   2. tampilan **daftar menu yang bisa dibuka-tutup** (akordeon `<details>` bawaan peramban —
 *      tetap bekerja tanpa JavaScript);
 *   3. **tanpa tombol cetak**.
 *
 * Isi panduannya ada di `inc/petunjuk.php` (`petunjuk_daftar()`) supaya halaman ini tetap tipis dan
 * aturannya mudah diuji. Menu sidebar-nya ada di `inc/layout.php`, tepat di bawah "Akun Saya".
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/petunjuk.php';   // isi panduan ada di modul ini

$u = require_login();
$peran = peran();
$sekolah = current_sekolah();
$grup = petunjuk_daftar();
$jumlah = petunjuk_jumlah();

// Catatan pembuka yang menyesuaikan peran & konteks.
$catatan = [];
if (is_pengawas()) {
    $catatan[] = 'Akun Pengawas Sekolah/Madrasah bersifat <b>lintas sekolah</b>: pilih binaan pada '
        . 'menu <b>Daftar Binaan</b> untuk melihat datanya. Seluruh menu hanya dapat dilihat dan '
        . 'dicetak — penginputan dilakukan di sekolah/madrasah masing-masing.';
} elseif (is_super()) {
    $catatan[] = $sekolah
        ? 'Anda sedang <b>mengelola ' . tetap_kata(nama_sekolah_tampil()) . '</b>, jadi menu sekolah/madrasah '
            . 'ikut tampil di bawah menu lintas sekolah.'
        : 'Anda belum masuk ke sekolah/madrasah mana pun, jadi yang tampil baru menu lintas sekolah. '
            . 'Masuk ke salah satu sekolah/madrasah pada menu <b>Kelola Sekolah</b> untuk membuka menu sekolahnya.';
} elseif (is_operator()) {
    $catatan[] = 'Pada empat menu administrasi (Daftar Hadir Siswa, Penilaian Siswa, Jurnal Harian Guru, '
        . 'dan Pelanggaran Siswa) akun Admin Operator <b>hanya dapat melihat dan mencetak</b> — '
        . 'pengisiannya dilakukan guru pengampu. Anda tetap dapat mengelola seluruh Data Master.';
} elseif (is_kepala()) {
    $catatan[] = 'Akun Kepala Sekolah/Madrasah dapat melihat dan mencetak seluruh administrasi, serta '
        . 'memantau kelengkapan data guru melalui menu <b>Laporan</b>.';
} else {
    $catatan[] = 'Daftar berikut mengikuti menu yang tampil di akun Anda. Bila suatu menu tidak ada di '
        . 'sini, berarti menu itu tidak tersedia untuk peran Anda.';
}

page_top('Petunjuk Penggunaan', 'Panduan singkat setiap menu, sesuai peran Anda');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('book', 16) ?> Cara Membaca Halaman Ini</h2>
      <p>Setiap menu dijelaskan satu per satu: kegunaannya, langkah pemakaiannya, dan hal yang perlu
        diperhatikan.</p>
    </div>
    <span class="chip"><?= (int) $jumlah ?> menu dijelaskan</span>
  </div>

  <div class="alert alert-ok" style="background:rgba(201,162,39,.08);border-color:rgba(201,162,39,.45)">
    <?= ikon('user', 16) ?>
    <span>Anda masuk sebagai <b><?= e(role_label($peran)) ?></b><?= $sekolah
      ? ' pada <b>' . tetap_kata(nama_sekolah_tampil()) . '</b>' : '' ?>.</span>
  </div>

  <?php foreach ($catatan as $c): ?>
    <p class="hint" style="margin:0 0 10px"><?= $c ?></p>
  <?php endforeach; ?>

  <div class="divider"></div>
  <p class="hint" style="margin:0">
    Klik nama menu untuk <b>membuka atau menutup</b> penjelasannya. Beberapa menu boleh dibuka
    bersamaan, dan seluruh isi halaman ini tetap terbaca walau skrip peramban gagal dimuat.
  </p>
</div>

<?php if (!$grup): ?>
  <div class="card">
    <div class="empty">
      <?= ikon('book', 34) ?>
      <h3>Belum ada menu yang dapat dijelaskan</h3>
      <p>Panduan akan tampil setelah akun Anda memiliki menu yang dapat dibuka.</p>
    </div>
  </div>
<?php endif; ?>

<?php foreach ($grup as $i => $g): ?>
  <div class="card" id="petunjuk-<?= (int) ($i + 1) ?>">
    <div class="card-head">
      <div>
        <h2><?= ikon('folder', 16) ?> <?= e((string) $g['judul']) ?></h2>
        <?php if ((string) $g['keterangan'] !== ''): ?><p><?= e((string) $g['keterangan']) ?></p><?php endif; ?>
      </div>
      <span class="chip"><?= count($g['menu']) ?> menu</span>
    </div>

    <div class="panduan-wrap">
      <?php foreach ($g['menu'] as $m): ?>
        <details class="panduan">
          <summary>
            <span class="panduan-ikon"><?= ikon((string) $m['ikon'], 16) ?></span>
            <span class="panduan-nama"><?= e((string) $m['nama']) ?></span>
            <span class="panduan-ringkas"><?= e((string) $m['ringkas']) ?></span>
          </summary>
          <div class="panduan-isi">
            <?php if (!empty($m['langkah'])): ?>
              <div class="panduan-sub"><?= ikon('check', 14) ?> Cara memakai</div>
              <ul class="panduan-langkah">
                <?php foreach ($m['langkah'] as $l): ?><li><?= $l ?></li><?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <?php if ((string) ($m['catatan'] ?? '') !== ''): ?>
              <p class="panduan-ket"><?= ikon('star', 13) ?> <?= $m['catatan'] ?></p>
            <?php endif; ?>
            <?php if (!empty($m['tautan'])): ?>
              <div class="panduan-aksi">
                <a class="btn btn-ghost btn-sm" href="<?= e((string) $m['tautan']) ?>">
                  <?= ikon('next', 14) ?> Buka menu <?= e((string) $m['nama']) ?>
                </a>
              </div>
            <?php endif; ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('star', 16) ?> Masih Bingung?</h2>
      <p>Panduan ini menjelaskan cara memakai, bukan menggantikan kebijakan sekolah/madrasah.</p>
    </div>
  </div>
  <p class="hint" style="margin:0">
    Bila ada tombol yang tidak muncul padahal seharusnya ada, periksa dulu <b>peran akun Anda</b> —
    sebagian tombol memang hanya tersedia bagi peran tertentu. Untuk hal lain (data yang keliru,
    penugasan menu, atau akun), hubungi <b>Admin Operator</b> atau <b>Kepala Sekolah/Madrasah</b>.
  </p>
</div>
<?php
page_bottom();
