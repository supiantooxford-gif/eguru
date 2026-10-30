<?php
/**
 * PENGUMUMAN SAMBUTAN — isi POPUP "Selamat datang" yang muncul sekali setiap kali pengguna masuk.
 *
 * Permintaan pemilik: "Pada saat login tampilkan popup selamat datang kepada pengguna aplikasi
 * (Pengawas, Kepala sekolah, operator dan guru). Isi dari popup di isi oleh super admin lewat
 * halaman super admin."
 * Pilihan pemilik: (1) BEBERAPA pengumuman sekaligus, masing-masing bisa diaktifkan/nonaktifkan;
 * (2) popup tampil SEKALI setiap kali login; (3) di luar isi dari Super Admin, popup tetap memuat
 * sapaan selamat datang + nama pengguna + tombol Tutup.
 *
 * PEMBARUAN (permintaan pemilik): *"untuk pop up yang muncul di akun siswa diinput tersendiri oleh
 * super admin"* → isi popup dipisah menjadi DUA SASARAN pada halaman ini:
 *   • tab **Pengguna Aplikasi** — tampil untuk Pengawas, Kepala Sekolah/Madrasah, Admin Operator, Guru;
 *   • tab **Siswa** — tampil HANYA pada akun siswa (diisi tersendiri, tidak tercampur).
 * Pemisahnya kolom `pengumuman.sasaran` (skema v46).
 *
 * Halaman ini KHUSUS Super Admin (peran lain 403). Daftarnya GLOBAL — tabel `pengumuman` tidak
 * punya kolom `sekolah_id`, jadi semua sekolah/madrasah melihat pengumuman yang sama.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_super();
// Halaman lintas sekolah/madrasah → istilah generik "Sekolah".
$GLOBALS['__vs_istilah_nonaktif'] = true;

$galat = [];
$tampilkanForm = false;   // true = validasi gagal → halaman dirender ulang dengan isian dikembalikan
$isian = ['id' => 0, 'judul' => '', 'isi' => '', 'aktif' => 1, 'sasaran' => 'pengguna'];
// Tab yang sedang dibuka = sasaran yang sedang dikelola ('pengguna' | 'siswa').
$tabSah = array_keys(pengumuman_sasaran_list());
$tab = (string) ($_GET['tab'] ?? '');
if (!in_array($tab, $tabSah, true)) {
    $tab = 'pengguna';
}
/** Kembali ke halaman ini pada tab tertentu (boleh sambil membuka form ubah). */
$kembali = static function (string $t, int $editId = 0): void {
    redirect('pengumuman.php?tab=' . rawurlencode($t) . ($editId > 0 ? '&edit=' . $editId : '') . '#formPengumuman');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $p = pengumuman_by_id($id);

    if ($aksi === 'simpan') {
        $judul = trim((string) ($_POST['judul'] ?? ''));
        $isi = trim((string) ($_POST['isi'] ?? ''));
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        // Sasaran hanya diambil dari pilihan yang sah; formulir/skrip gaya lama (tanpa `sasaran`)
        // diperlakukan sebagai 'pengguna' supaya tidak ada popup siswa yang dibuat tanpa sengaja.
        $sasaran = pengumuman_sasaran_sah((string) ($_POST['sasaran'] ?? ''));
        if ($sasaran === '') {
            $sasaran = $p ? pengumuman_sasaran_sah((string) $p['sasaran']) : 'pengguna';
        }
        if ($sasaran === '') {
            $sasaran = 'pengguna';
        }
        $tab = $sasaran;
        $isian = ['id' => $id, 'judul' => $judul, 'isi' => $isi, 'aktif' => $aktif, 'sasaran' => $sasaran];

        if ($judul === '') {
            $galat[] = 'Judul pengumuman wajib diisi.';
        }
        if ($isi === '') {
            $galat[] = 'Isi pengumuman wajib diisi.';
        }
        if (strlen($judul) > 160) {
            $isian['judul'] = substr($judul, 0, 160);
        }
        if (strlen($isi) > 4000) {
            $isian['isi'] = substr($isi, 0, 4000);
        }

        if ($galat) {
            // PENTING: jangan mengalihkan halaman saat validasi gagal — pesan galat dan isian
            // pengguna harus tetap terlihat (pola $tampilkanForm).
            $tampilkanForm = true;
        } else {
            $now = date('Y-m-d H:i:s');
            $labelSasaran = pengumuman_sasaran_list()[$sasaran];
            if ($p) {
                q('UPDATE pengumuman SET judul = ?, isi = ?, sasaran = ?, aktif = ?, diubah = ? WHERE id = ?',
                    [$isian['judul'], $isian['isi'], $sasaran, $aktif, $now, (int) $p['id']]);
                flash('Pengumuman "' . $isian['judul'] . '" (' . $labelSasaran . ') diperbarui.');
            } else {
                q('INSERT INTO pengumuman (judul, isi, sasaran, urutan, aktif, dibuat, diubah) VALUES (?,?,?,?,?,?,?)',
                    [$isian['judul'], $isian['isi'], $sasaran,
                        pengumuman_urutan_baru($sasaran), $aktif, $now, $now]);
                flash('Pengumuman "' . $isian['judul'] . '" untuk ' . $labelSasaran . ' ditambahkan'
                    . ($aktif === 1 ? ' dan akan tampil pada popup sambutan.' : ' (belum diaktifkan).'));
            }
            $kembali($sasaran);
        }
    } elseif (!$p) {
        flash('Pengumuman tidak ditemukan.', 'err');
        redirect('pengumuman.php');
    } elseif ($aksi === 'toggle') {
        q('UPDATE pengumuman SET aktif = ?, diubah = ? WHERE id = ?',
            [(int) $p['aktif'] === 1 ? 0 : 1, date('Y-m-d H:i:s'), $id]);
        flash('Pengumuman "' . $p['judul'] . '" ' . ((int) $p['aktif'] === 1
            ? 'dinonaktifkan — tidak lagi tampil pada popup sambutan.'
            : 'diaktifkan — akan tampil pada popup sambutan.'), 'warn');
        $kembali(pengumuman_sasaran_sah((string) $p['sasaran']) ?: 'pengguna');
    } elseif ($aksi === 'geser') {
        if (pengumuman_geser($id, (string) ($_POST['arah'] ?? '') === 'naik' ? 'naik' : 'turun')) {
            flash('Urutan pengumuman "' . $p['judul'] . '" dipindahkan.', 'warn');
        }
        $kembali(pengumuman_sasaran_sah((string) $p['sasaran']) ?: 'pengguna');
    } elseif ($aksi === 'hapus') {
        q('DELETE FROM pengumuman WHERE id = ?', [$id]);
        flash('Pengumuman "' . $p['judul'] . '" dihapus.', 'warn');
        $kembali(pengumuman_sasaran_sah((string) $p['sasaran']) ?: 'pengguna');
    } else {
        redirect('pengumuman.php');
    }
}

// Saat validasi gagal, ?edit= menentukan baris mana yang sedang diubah (isian dari POST diutamakan).
$edit = null;
if ($tampilkanForm) {
    $edit = $isian['id'] > 0 ? pengumuman_by_id((int) $isian['id']) : null;
} else {
    $edit = pengumuman_by_id((int) ($_GET['edit'] ?? 0));
}
$semua = pengumuman_list(false, $tab);
$jmlAktif = pengumuman_jumlah_aktif($tab);
$jmlAktifSasaranLain = pengumuman_jumlah_aktif($tab === 'siswa' ? 'pengguna' : 'siswa');
$nilai = static fn(string $k, $bawaan = '') => $tampilkanForm
    ? (string) ($isian[$k] ?? '')
    : (string) ($edit[$k] ?? $bawaan);
$cekAktif = $tampilkanForm ? (int) $isian['aktif'] === 1 : (int) ($edit['aktif'] ?? 1) === 1;
// Sasaran pada formulir: hasil POST diutamakan, lalu sasaran baris yang sedang diubah, terakhir tab.
$sasaranForm = $tampilkanForm
    ? pengumuman_sasaran_sah((string) ($isian['sasaran'] ?? ''))
    : pengumuman_sasaran_sah((string) ($edit['sasaran'] ?? ''));
if ($sasaranForm === '') {
    $sasaranForm = $tab;
}
if ($edit && pengumuman_sasaran_sah((string) $edit['sasaran']) === 'siswa') {
    $tab = 'siswa';   // panel "Ubah" pada baris siswa membuka tab Siswa
}
$semua = pengumuman_list(false, $tab);
$jmlAktif = pengumuman_jumlah_aktif($tab);

page_top('Pengumuman Sambutan', 'Super Admin &middot; popup selamat datang saat pengguna masuk aplikasi');
?>
<?php
// Dua tab = dua SASARAN popup. Permintaan pemilik: popup untuk akun siswa diisi TERSENDIRI,
// sehingga isinya tidak pernah tercampur dengan popup untuk pengguna aplikasi.
$labelSasaran = pengumuman_sasaran_list();
$keteranganSasaran = [
    'pengguna' => 'Pop up yang muncul pada akun <b>Pengawas</b>, <b>Kepala Sekolah/Madrasah</b>, '
        . '<b>Admin Operator</b>, dan <b>Guru</b>.',
    'siswa'    => 'Pop up yang muncul pada akun <b>Siswa</b> — diisi tersendiri di tab ini sehingga '
        . 'isinya terpisah dari pop up pengguna aplikasi.',
];
?>
<div class="chips" style="gap:8px">
  <?php foreach ($labelSasaran as $kode => $label): ?>
    <a class="chip <?= $tab === $kode ? 'on' : '' ?>" href="pengumuman.php?tab=<?= e($kode) ?>">
      <?= ikon($kode === 'siswa' ? 'student' : 'users', 14) ?> <?= e($label) ?>
      <?php if (pengumuman_jumlah_aktif($kode) > 0): ?>(<?= (int) pengumuman_jumlah_aktif($kode) ?> aktif)<?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<div class="card mt16">
  <div class="card-head">
    <div>
      <h2><?= ikon('star', 16) ?> Pengumuman Sambutan — <?= e($labelSasaran[$tab]) ?></h2>
      <p>Isi popup <b>Selamat datang</b> yang tampil <b>sekali setiap kali pengguna masuk</b>.
        <?= $keteranganSasaran[$tab] ?>
        Beberapa pengumuman boleh aktif sekaligus.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $jmlAktif ?> aktif</span>
      <span class="chip"><?= count($semua) ?> total</span>
      <?php if ($jmlAktifSasaranLain > 0): ?>
        <span class="chip"><?= ikon('info', 13) ?> <?= (int) $jmlAktifSasaranLain ?> aktif untuk
          <?= e($labelSasaran[$tab === 'siswa' ? 'pengguna' : 'siswa']) ?></span>
      <?php endif; ?>
      <a class="btn btn-primary" href="pengumuman.php?tab=<?= e($tab) ?>#formPengumuman"><?= ikon('plus', 16) ?> Tambah Pengumuman</a>
    </div>
  </div>

  <form method="post" class="apps-form" id="formPengumuman">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <input type="hidden" name="sasaran" value="<?= e($sasaranForm) ?>">
    <div class="apps-form-judul">
      <?= ikon($edit ? 'edit' : 'plus', 16) ?>
      <b><?= $edit ? 'Ubah Pengumuman' : 'Tambah Pengumuman' ?></b>
      <span class="pill <?= $sasaranForm === 'siswa' ? 'pill-warn' : 'pill-B' ?>">
        untuk <?= e($labelSasaran[$sasaranForm]) ?></span>
      <?php if ($edit): ?><a class="xs" href="pengumuman.php?tab=<?= e($tab) ?>">(batal)</a><?php endif; ?>
    </div>

    <?php foreach ($galat as $g): ?>
      <div class="alert alert-err"><?= ikon('alert', 16) ?><span><?= e($g) ?></span></div>
    <?php endforeach; ?>

    <div class="form-grid">
      <div>
        <label class="f" for="judul">Judul Pengumuman *</label>
        <input type="text" id="judul" name="judul" maxlength="160" required
               value="<?= e($nilai('judul')) ?>" placeholder="mis. Selamat Tahun Ajaran Baru">
      </div>
      <div>
        <label class="f" for="isi">Isi Pengumuman *</label>
        <textarea id="isi" name="isi" rows="5" maxlength="4000" required
                  placeholder="Tuliskan pesan yang ingin disampaikan kepada pengguna aplikasi…"><?= e($nilai('isi')) ?></textarea>
        <div class="hint">Boleh beberapa baris. Tampil di dalam popup setelah sapaan selamat datang.</div>
      </div>
      <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600">
        <input type="checkbox" name="aktif" value="1" style="width:16px;height:16px;accent-color:var(--accent)"
               <?= $cekAktif ? 'checked' : '' ?>> Tampilkan pada popup sambutan (aktif)
      </label>
      <div>
        <label class="f" for="sasaranPilih">Pop up ini untuk</label>
        <select id="sasaranPilih" name="sasaran">
          <?php foreach ($labelSasaran as $kode => $label): ?>
            <option value="<?= e($kode) ?>" <?= $sasaranForm === $kode ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Pop up untuk <b>Siswa</b> diisi tersendiri di sini — tidak muncul pada akun
          Pengawas/Kepala Sekolah/Admin Operator/Guru, dan sebaliknya.</div>
      </div>
      <div class="flex">
        <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $edit ? 'Simpan Perubahan' : 'Tambah Pengumuman' ?></button>
        <?php if ($edit): ?><a class="btn btn-ghost" href="pengumuman.php?tab=<?= e($tab) ?>">Batal</a><?php endif; ?>
      </div>
    </div>
  </form>
</div>

<div class="grid g12">
  <div class="card">
    <div class="card-head">
      <div>
        <h2>Daftar Pengumuman — <?= e($labelSasaran[$tab]) ?></h2>
        <p>Urutan di sini sama dengan urutan di dalam popup. Yang <b>nonaktif</b> tetap tersimpan
          tetapi tidak ikut tampil. Daftar ini hanya memuat pop up untuk
          <b><?= e($labelSasaran[$tab]) ?></b>.</p>
      </div>
    </div>

    <?php if (!$semua): ?>
      <div class="empty">
        <?= ikon('star', 34) ?>
        <h3>Belum ada pengumuman untuk <?= e($labelSasaran[$tab]) ?></h3>
        <p>Tambahkan pengumuman pertama pada formulir di atas. Setelah diaktifkan, popup akan muncul
          sekali kepada <?= $tab === 'siswa' ? 'akun siswa' : 'pengguna aplikasi' ?> yang baru masuk.</p>
      </div>
    <?php else: ?>
      <div class="grid" style="gap:16px">
        <?php foreach ($semua as $b): ?>
          <div class="card pengumuman-kartu<?= (int) $b['aktif'] !== 1 ? ' pengumuman-mati' : '' ?>">
            <div class="card-head">
              <div>
                <h2 style="font-size:1rem"><?= e((string) $b['judul']) ?>
                  <span class="pill <?= (int) $b['aktif'] === 1 ? 'pill-ok' : 'pill-gray' ?>">
                    <?= (int) $b['aktif'] === 1 ? 'Tampil di popup' : 'Nonaktif' ?>
                  </span>
                </h2>
                <p class="xs mut" style="margin:6px 0 0">Ditambahkan <?= e(tanggal_id((string) ($b['dibuat'] ?: ''), true)) ?>
                  <?= !empty($b['diubah']) ? '&middot; terakhir diubah ' . e(tanggal_id((string) $b['diubah'], true)) : '' ?></p>
              </div>
              <div class="row-actions">
                <a class="btn btn-ghost btn-sm" href="pengumuman.php?tab=<?= e($tab) ?>&amp;edit=<?= (int) $b['id'] ?>#formPengumuman"><?= ikon('edit', 14) ?> Ubah</a>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="geser">
                  <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit" name="arah" value="naik" title="Naikkan urutan"><?= ikon('up', 14) ?></button>
                  <button class="btn btn-ghost btn-sm" type="submit" name="arah" value="turun" title="Turunkan urutan"><?= ikon('down', 14) ?></button>
                </form>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('eye', 14) ?> <?= (int) $b['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                </form>
                <form method="post" data-confirm="Hapus pengumuman &quot;<?= e((string) $b['judul']) ?>&quot;?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="hapus">
                  <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Hapus</button>
                </form>
              </div>
            </div>
            <div class="pengumuman-teks"><?= nl2br(e((string) $b['isi'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <div><h2>Pratinjau Popup — <?= e($labelSasaran[$tab]) ?></h2>
        <p>Bentuk yang dilihat pengguna setelah mereka masuk.</p></div>
    </div>
    <div class="popup-contoh">
      <div class="popup-sambutan-ic"><?= ikon('star', 20) ?></div>
      <div>
        <b>Selamat Datang, Nama <?= $tab === 'siswa' ? 'Siswa' : 'Pengguna' ?></b>
        <div class="xs mut"><?= $tab === 'siswa' ? 'Siswa' : 'Kepala Sekolah/Madrasah' ?> &middot; nama sekolah/madrasah</div>
      </div>
    </div>
    <?php $aktifList = pengumuman_list(true, $tab); ?>
    <?php if (!$aktifList): ?>
      <p class="hint" style="margin-top:12px">Belum ada pengumuman aktif untuk
        <b><?= e($labelSasaran[$tab]) ?></b>, jadi popup belum akan muncul kepada
        <?= $tab === 'siswa' ? 'akun siswa' : 'pengguna aplikasi' ?>.</p>
    <?php else: ?>
      <div class="popup-contoh-isi">
        <?php foreach ($aktifList as $i => $b): ?>
          <div class="pengumuman-item">
            <div class="pengumuman-item-head">
              <span class="pengumuman-no"><?= $i + 1 ?></span>
              <b><?= e((string) $b['judul']) ?></b>
            </div>
            <div class="pengumuman-teks"><?= nl2br(e((string) $b['isi'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="hint" style="margin-top:12px">Isi di atas persis seperti yang tampil pada popup
        (hanya judul &amp; isi; tanggal tidak ditampilkan).</p>
    <?php endif; ?>
    <div class="divider"></div>
    <ul class="apps-catatan">
      <li><b>Tampil sekali per login</b> — popup muncul pada halaman pertama setelah pengguna masuk,
        lalu tertutup dan tidak muncul lagi sampai ia masuk ulang.</li>
      <li><b>Hanya untuk pengguna aplikasi</b> — Pengawas, Kepala Sekolah/Madrasah, Admin Operator, dan Guru.
        Super Admin (pengisi isi popup) tidak ikut melihatnya.</li>
      <li><b>Dua tab, dua sasaran</b> — tab <b>Pengguna Aplikasi</b> untuk Pengawas, Kepala Sekolah/Madrasah, Admin Operator, dan Guru,
        tab <b>Siswa</b> untuk akun siswa. Keduanya diisi terpisah dan tidak pernah tercampur.</li>
      <li><b>Sapaan otomatis</b> — di luar isi di atas, popup selalu memuat sapaan
        “Selamat Datang, &lt;nama pengguna&gt;”, peran, sekolah/madrasah, dan tombol <b>Tutup</b>.</li>
      <li><b>Bisa dinonaktifkan</b> — pengumuman yang tidak dipakai cukup dinonaktifkan (tetap tersimpan
        dan dapat diaktifkan kembali); popup tidak muncul sama sekali bila tidak ada pengumuman aktif.</li>
    </ul>
  </div>
</div>
<?php
page_bottom();
