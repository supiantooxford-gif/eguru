<?php
/**
 * Isi sebuah menu TUGAS TAMBAHAN (Kurikulum, Kesiswaan, Sarana & Prasarana, Pembina OSIM/OSIS,
 * Pembina Pramuka, Pembina PMR, Wali Kelas, Tata Usaha, Kepala Perpus, Kepala Lab).
 *
 * Permintaan pemilik: menu-menu itu tampil di sidebar DI ATAS Data Master **begitu diaktifkan
 * Super Admin**, namanya boleh diubah Super Admin, dan isinya untuk sementara berbunyi
 * **"Masih dalam Pengembangan"**. Aturan tampil & boleh-bukanya sama (lihat tugas_menu_semua_aktif()):
 * Super Admin / Kepala Sekolah-Madrasah / Admin Operator → seluruh menu aktif; guru & peran lain →
 * hanya menu yang DITUGASKAN kepadanya lewat halaman `tugas_pengguna.php`.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/sarpras.php';
require_once __DIR__ . '/inc/kesiswaan.php';
require_once __DIR__ . '/inc/osim.php';
require_once __DIR__ . '/inc/perpus.php';
require_once __DIR__ . '/inc/lab.php';
require_once __DIR__ . '/inc/dokumen.php';
require_once __DIR__ . '/inc/wali.php';

require_login();

// Kode menu dibaca LEBIH DULU: dipakai oleh pemindah konteks (Super Admin & pengawas) dan oleh
// halaman pemilih sekolah di bawah.
$kode = trim((string) ($_GET['kode'] ?? ''));

// ------------------------------------------------------------------ PENGAWAS (hanya lihat) — binaan
// Permintaan pemilik: "Laporan Tugas Tambahan tampilkan juga di akun pengawas bina untuk setiap
// sekolahnya". Pengawas memilih binaan dulu (boleh lewat ?sekolah=ID — anti-IDOR), lalu melihat
// LAPORAN tiap menu Tugas Tambahan yang diaktifkan pada sekolah itu. Semua pengubahan data tetap
// ditolak oleh gerbang pengawas (hanya-lihat).
if (is_pengawas()) {
    $idSekolah = (int) ($_GET['sekolah'] ?? 0);
    if ($idSekolah > 0) {
        if (!pengawas_punya((int) (current_user()['id'] ?? 0), $idSekolah)) {
            tolak403('Sekolah/madrasah itu bukan binaan Anda.');
        }
        if (!one('SELECT id FROM sekolah WHERE id = ? AND aktif = 1 AND verifikasi = "disetujui"', [$idSekolah])) {
            flash('Sekolah/madrasah itu sedang tidak aktif.', 'warn');
            redirect('index.php');
        }
        set_sekolah_context($idSekolah);
        // Parameter kode & bagian ikut dibawa: tanpa ini tautan langsung ke salah satu bagian
        // (mis. Program Kerja OSIM) selalu mendarat di bagian pertama.
        $kodeTujuan = trim((string) ($_GET['kode'] ?? ''));
        $bagianTujuan = trim((string) ($_GET['bagian'] ?? ''));
        $qTujuan = [];
        if ($kodeTujuan !== '') {
            $qTujuan['kode'] = $kodeTujuan;
        }
        if ($bagianTujuan !== '') {
            $qTujuan['bagian'] = $bagianTujuan;
        }
        redirect('tugas.php' . ($qTujuan ? '?' . http_build_query($qTujuan) : ''));
    }
    // Tanpa kode menu = halaman pemilih LAPORAN (butuh konteks binaan). Bila ADA kode menu,
    // eksekusi dilanjutkan: pengawas tanpa konteks akan mendapat halaman PEMILIH BINAAN untuk menu
    // itu (tugas_halaman_pilih_sekolah()) — bukan dialihkan diam-diam ke daftar binaan.
    if ($kode === '') {
        require_sekolah();
    }
}

// ------------------------------------------------------------------ SUPER ADMIN — pindah konteks sekolah
// Permintaan pemilik (laporan "menu yang isi menunya sudah diinput tidak bisa ditampilkan"): ketika
// Super Admin belum memilih sekolah, click menu Tugas Tambahan yang datanya per sekolah DULU
// menampilkan halaman pemilih (tugas_halaman_pilih_sekolah()) dan `?sekolah=ID` di sini memindahkan
// konteks lalu mengembalikannya ke menu yang dituju — pola yang sama dengan lembar cetak butir.
if (is_super() && !current_sekolah()) {
    $idSekolah = (int) ($_GET['sekolah'] ?? 0);
    if ($idSekolah > 0) {
        $target = one('SELECT * FROM sekolah WHERE id = ? AND aktif = 1 AND verifikasi = "disetujui"', [$idSekolah]);
        if (!$target) {
            flash('Sekolah/madrasah itu tidak ditemukan atau sudah dinonaktifkan.', 'err');
            redirect('tugas.php' . ($kode !== '' ? '?kode=' . urlencode($kode) : ''));
        }
        set_sekolah_context($idSekolah);
        flash('Anda kini mengelola ' . nama_sekolah_tampil($target) . '. Seluruh data yang tampil hanya milik sekolah/madrasah ini.');
        redirect('tugas.php' . ($kode !== '' ? '?kode=' . urlencode($kode) : ''));
    }
}

/**
 * Halaman PEMILIH SEKOLAH/BINAAN untuk menu Tugas Tambahan yang datanya per sekolah.
 *
 * Dipanggil `tugas.php` hanya bila menu itu memang butuh konteks sekolah sedangkan konteksnya belum
 * ada. Sebelum ini pengguna langsung dialihkan (302) sehingga terlihat "isi menu tidak bisa
 * ditampilkan". Satu klik pada halaman ini memindahkan konteks dan langsung membuka menunya.
 */
function tugas_halaman_pilih_sekolah(array $tugas): void
{
    $namaMenu = (string) ($tugas['nama'] ?? 'Tugas Tambahan');
    $kode = (string) ($tugas['kode'] ?? '');
    $pengawas = is_pengawas();
    $daftar = $pengawas
        ? sekolah_binaan((int) (current_user()['id'] ?? 0), true)
        : all('SELECT * FROM sekolah WHERE aktif = 1 AND verifikasi = "disetujui" ORDER BY nama COLLATE NOCASE');

    if (!$pengawas) {
        // Halaman lintas sekolah (Sekolah umum DAN madrasah sekaligus) → istilah generik "Sekolah".
        $GLOBALS['__vs_istilah_nonaktif'] = true;
    }
    page_top('Pilih Sekolah/Madrasah — ' . $namaMenu,
        $pengawas ? 'Pengawas Sekolah/Madrasah' : 'Super Admin');
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('school', 16) ?> Pilih <?= $pengawas ? 'Sekolah/Madrasah Binaan' : 'Sekolah/Madrasah' ?> Dulu</h2>
      <p>Menu <b><?= e($namaMenu) ?></b> menampilkan data <b>satu sekolah/madrasah</b>. Pilih dulu
        sekolahnya di bawah — Anda akan <b>langsung dibawa ke menu itu</b>. Pilihannya tersimpan
        selama sesi ini, jadi menu berikutnya tidak perlu memilih lagi.</p>
    </div>
    <div class="flex">
      <a class="btn btn-ghost btn-sm" href="<?= $pengawas ? 'index.php' : 'sekolah.php' ?>"><?= ikon('switch', 15) ?> <?= $pengawas ? 'Daftar Binaan' : 'Kelola Sekolah/Madrasah' ?></a>
    </div>
  </div>

  <?php if (!$daftar): ?>
    <div class="empty"><?= ikon('school', 34) ?>
      <h3><?= $pengawas ? 'Belum ada sekolah/madrasah binaan yang aktif' : 'Belum ada sekolah/madrasah aktif' ?></h3>
      <p><?= $pengawas
        ? 'Super Admin belum menetapkan sekolah/madrasah binaan untuk akun Anda (atau binaannya sedang dinonaktifkan).'
        : 'Tambahkan sekolah/madrasah dulu lewat Kelola Sekolah/Madrasah.' ?></p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th class="ctr" style="width:44px">No</th>
            <th>Nama Sekolah/Madrasah</th>
            <th class="ctr">Jenis</th>
            <th class="ctr">Guru</th>
            <th class="ctr" style="width:190px">Tindakan</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($daftar as $s): $n++;
            $sid = (int) $s['id'];
            $guru = (int) val('SELECT COUNT(*) FROM guru WHERE sekolah_id = ? AND IFNULL(aktif,1) = 1', [$sid], 0);
        ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e(tetap_kata(nama_sekolah_tampil($s))) ?></b>
              <div class="xs mut"><?= e((string) ($s['jenis'] ?? 'umum') === 'madrasah' ? 'Madrasah' : 'Sekolah Umum') ?>
                &middot; <?= (int) $guru ?> guru</div></td>
            <td class="ctr"><span class="chip"><?= e((string) ($s['jenis'] ?? 'umum') === 'madrasah' ? 'Madrasah' : 'Sekolah') ?></span></td>
            <td class="ctr"><?= (int) $guru ?></td>
            <td class="ctr">
              <a class="btn btn-primary btn-sm"
                 href="tugas.php?sekolah=<?= $sid ?>&amp;kode=<?= e(rawurlencode($kode)) ?>"><?= ikon('eye', 14) ?> Buka <?= e($namaMenu) ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint mt16">Menu ini tidak ditampilkan untuk semua sekolah sekaligus karena datanya
      (daftar barang, program kerja, laporan perpustakaan, dsb.) memang milik masing-masing
      sekolah/madrasah.</p>
  <?php endif; ?>
</div>
    <?php
    page_bottom();
    exit;
}

// Pengawas tanpa kode menu → halaman pemilih: daftar LAPORAN Tugas Tambahan binaan ini.
if ($kode === '' && is_pengawas()) {
    $menuBinaan = tugas_menu_saring_tampil(tugas_menu_list(true));
    $binaanNama = nama_sekolah_tampil();
    page_top('Laporan Tugas Tambahan', 'Pengawas Sekolah/Madrasah &middot; '
        . tetap_kata($binaanNama) . ' &middot; hanya lihat');
    ?>
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= ikon('chart', 16) ?> Laporan Tugas Tambahan</h2>
        <p>Laporan menu Tugas Tambahan yang diaktifkan untuk
          <b><?= tetap_kata($binaanNama) ?></b> — binaan Anda. Seluruh halaman hanya dapat dilihat
          dan dicetak.</p>
      </div>
      <div class="flex">
        <span class="chip"><?= count($menuBinaan) ?> menu aktif</span>
        <?php // "Ganti Binaan" harus MENGELUAR dari konteks binaan (index.php saja akan menampilkan
              // Dashboard Binaan bila konteks masih aktif) — pola sama dengan tautan sidebar. ?>
        <a class="btn btn-ghost btn-sm" href="index.php?keluar=1"><?= ikon('switch', 15) ?> Ganti Binaan</a>
      </div>
    </div>
    <?php if (!$menuBinaan): ?>
      <div class="empty"><?= ikon('star', 34) ?>
        <h3>Belum ada menu Tugas Tambahan yang diaktifkan</h3>
        <p>Super Admin belum mengaktifkan satu pun menu Tugas Tambahan, jadi belum ada laporan yang
          dapat ditampilkan.</p>
      </div>
    <?php else: ?>
      <div class="grid g3" style="gap:16px">
        <?php foreach ($menuBinaan as $mb): ?>
          <div class="card" style="margin:0">
            <div class="card-head">
              <div><h2 style="font-size:1rem"><?= e((string) $mb['nama']) ?></h2>
                <p class="xs mut" style="margin:6px 0 0">Laporan binaan ini</p></div>
            </div>
            <div class="flex">
              <a class="btn btn-primary btn-sm" href="tugas.php?kode=<?= e((string) $mb['kode']) ?>"><?= ikon('eye', 15) ?> Buka Laporan</a>
              <?php if ((string) $mb['kode'] === SARPRAS_KODE_MENU): ?>
                <a class="btn btn-ghost btn-sm" href="cetak_sarpras.php" target="_blank"><?= ikon('print', 15) ?> Cetak</a>
              <?php elseif ((string) $mb['kode'] === KS_KODE_MENU): ?>
                <a class="btn btn-ghost btn-sm" href="cetak_kesiswaan.php" target="_blank"><?= ikon('print', 15) ?> Cetak</a>
              <?php elseif (in_array((string) $mb['kode'], OSIM_KODE_LIST, true)): ?>
                <?php // Dua menu memakai modul yang sama (Pembina OSIM/OSIS & Pembina Pramuka) →
                      // lembar cetaknya dibedakan oleh ?kode=. ?>
                <a class="btn btn-ghost btn-sm" href="cetak_osim_bagan.php?kode=<?= e((string) $mb['kode']) ?>" target="_blank"><?= ikon('print', 15) ?> Cetak Bagan</a>
                <a class="btn btn-ghost btn-sm" href="cetak_osim_organisasi.php?kode=<?= e((string) $mb['kode']) ?>" target="_blank"><?= ikon('print', 15) ?> Cetak Tabel</a>
                <a class="btn btn-ghost btn-sm" href="cetak_osim_program.php?kode=<?= e((string) $mb['kode']) ?>" target="_blank"><?= ikon('print', 15) ?> Cetak Program</a>
              <?php elseif (lab_kode_ada((string) $mb['kode'])): ?>
                <?php // Dua dokumen lab: daftar inventaris barang & laporan program kerja. ?>
                <a class="btn btn-ghost btn-sm" href="cetak_lab.php?kode=<?= e((string) $mb['kode']) ?>&bagian=barang" target="_blank"><?= ikon('print', 15) ?> Cetak Inventaris</a>
                <a class="btn btn-ghost btn-sm" href="cetak_lab.php?kode=<?= e((string) $mb['kode']) ?>&bagian=program" target="_blank"><?= ikon('print', 15) ?> Cetak Program</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="hint mt16">Akun pengawas <b>hanya dapat melihat dan mencetak</b> laporan ini — tombol
        penginputan (tambah/ubah/hapus/impor) tidak tersedia.</p>
    <?php endif; ?>
  </div>
    <?php
    page_bottom();
    exit;
}

$tugas = tugas_menu_by_kode($kode);

if (!$tugas) {
    flash('Menu Tugas Tambahan itu tidak ditemukan.', 'err');
    redirect(is_pengawas() ? 'tugas.php' : 'index.php');
}

// Menu yang datanya PER SEKOLAH: tanpa konteks sekolah, tampilkan halaman PEMILIH (jangan dilempar
// diam-diam). Hanya untuk Super Admin & pengawas — peran lain memang harus punya konteks sendiri.
// ⚠️ Blok ini WAJIB berada SETELAH halaman pemilih laporan pengawas (`$kode === ''`) di atas:
// kalau diletakkan lebih awal, `$tugas` bernilai null untuk permintaan `tugas.php` tanpa kode
// sehingga pengawas terlempar ke pengalihan "menu tidak ditemukan" (lingkaran pengalihan).
if (tugas_menu_perlu_sekolah($kode) && !current_sekolah() && (is_super() || is_pengawas())) {
    tugas_halaman_pilih_sekolah($tugas);
}

require_tugas_menu($tugas);

// Menu yang punya halaman KHUSUS (permintaan pemilik) — seluruh logikanya di modul `inc/`:
//   - "Sarana & Prasarana"   → penginputan DATA BARANG (`inc/sarpras.php`);
//   - "Kesiswaan"            → LAPORAN PROGRAM KERJA KESISWAAN (`inc/kesiswaan.php`);
//   - "Pembina OSIM/OSIS" &
//     "Pembina Pramuka"      → SUSUNAN ORGANISASI + PROGRAM KERJA (`inc/osim.php`, dibedakan `kode`);
//   - "Ka. Perpustakaan"     → LAPORAN PERPUSTAKAAN hasil IMPOR e-PERPUS + cetak (`inc/perpus.php`);
//   - "Kepala Lab IPA" &
//     "Kepala Lab Komputer"  → DAFTAR INVENTARIS BARANG + LAPORAN PROGRAM KERJA lab (`inc/lab.php`);
//   - "Kurikulum"            → KIRIM DOKUMEN KEPADA GURU (`inc/dokumen.php`);
//   - "Wali Kelas"           → EMPAT laporan kelas (`inc/wali.php`).
// Menu Tugas Tambahan lain tetap "Masih dalam Pengembangan" (blok di bawah).
if ($kode === SARPRAS_KODE_MENU) {
    sarpras_halaman($tugas);
    exit;
}
if ($kode === KS_KODE_MENU) {
    ks_halaman($tugas);
    exit;
}
if (in_array($kode, OSIM_KODE_LIST, true)) {
    // Menu Pembina OSIM/OSIS & Pembina Pramuka memakai modul yang sama (`inc/osim.php`);
    // isi menu mengikuti `kode` pada baris tugas_menu.
    osim_halaman($tugas);
    exit;
}
if ($kode === PERPUS_KODE_MENU) {
    perpus_halaman($tugas);
    exit;
}
if (lab_kode_ada($kode)) {
    lab_halaman($tugas);
    exit;
}
if ($kode === KURIKULUM_KODE_MENU) {
    kurikulum_halaman($tugas);
    exit;
}
if ($kode === WALI_KODE_MENU) {
    wali_halaman($tugas);
    exit;
}
// Menu Tugas Tambahan yang isinya DAFTAR APLIKASI (mis. "Bendahara Bos"). Permintaan pemilik
// (v1.32.0): isi menu "Bendahara" yang dahulu berdiri sendiri dipindahkan ke menu Tugas Tambahan ini
// — lihat `daftar_apps_tugas_kunci()`; pengenalan memakai kode MAUPUN nama menu.
$kunciApps = daftar_apps_tugas_kunci($tugas);
if ($kunciApps !== null) {
    daftar_apps_halaman($kunciApps, $tugas);
    exit;
}

$nama = (string) $tugas['nama'];
$jumlahPengguna = tugas_menu_jumlah_pengguna((int) $tugas['id']);

page_top($nama, 'Tugas Tambahan');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('star', 16) ?> <?= e($nama) ?></h2>
      <p>Menu ini diaktifkan oleh Super Admin
        <?= $jumlahPengguna > 0 ? ' dan dapat dibuka oleh <b>' . (int) $jumlahPengguna . ' pengguna</b> yang ditugaskan' : '' ?>.</p>
    </div>
    <span class="chip">Tugas Tambahan</span>
  </div>

  <div class="empty" style="padding:34px 20px">
    <?= ikon('apps', 46) ?>
    <h3><?= e(TUGAS_ISI_PESAN) ?></h3>
    <p>Halaman <b><?= e($nama) ?></b> sedang disiapkan. Fitur pengelolaan untuk tugas ini akan
      ditambahkan pada pembaruan berikutnya — sementara ini menu hanya menandai bahwa tugas tersebut
      dipegang oleh akun Anda.</p>
  </div>

  <div class="grid g2" style="gap:16px">
    <div class="card" style="margin:0">
      <div class="card-head"><div><h2>Keterangan Tugas</h2><p>Ringkasan singkat.</p></div></div>
      <div class="sheet-meta" style="margin:0">
        <div><b>Nama Tugas</b>: <?= e($nama) ?></div>
        <div><b>Status</b>: <?= (int) $tugas['aktif'] === 1 ? 'Aktif (tampil di sidebar)' : 'Nonaktif' ?></div>
        <div><b>Pengguna Ditugaskan</b>: <?= (int) $jumlahPengguna ?> orang</div>
        <div><b>Isi Menu</b>: <?= e(TUGAS_ISI_PESAN) ?></div>
      </div>
    </div>
    <div class="card" style="margin:0">
      <div class="card-head"><div><h2>Catatan</h2><p>Untuk pengguna yang ditugaskan.</p></div></div>
      <ul class="apps-catatan">
        <li>Pada akun <b>Super Admin</b>, <b>Kepala Sekolah/Madrasah</b>, dan <b>Admin Operator</b>,
          seluruh menu Tugas Tambahan yang diaktifkan tampil di sidebar.</li>
        <li>Pada akun <b>Guru</b> (dan peran lain), sidebar hanya menampilkan menu yang
          <b>ditugaskan</b> kepadanya.</li>
        <li>Penugasan diatur oleh <b>Admin Operator</b> atau <b>Kepala Sekolah/Madrasah</b> melalui menu
          <b>Pengaturan Tugas Tambahan</b>.</li>
        <li>Isi menu masih <b><?= e(TUGAS_ISI_PESAN) ?></b>.</li>
      </ul>
    </div>
  </div>
</div>
<?php
page_bottom();
