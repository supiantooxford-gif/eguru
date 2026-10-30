<?php
/**
 * PENGATURAN BIAYA AKTIVASI & NOMOR WA HD ADMIN — halaman Super Admin (`biaya_aktivasi.php`).
 *
 * Permintaan pemilik: *"Tambahkan menu di super admin untuk mengisi nominal biaya aktifasi pertama,
 * biaya langganan, nomer WA HD Admin (pada bagian kirim permintaan aktifasi dan bukti transfer."*
 *
 * Isinya bersifat TINGKAT APLIKASI (semua sekolah/madrasah memakai tarif & nomor WA yang sama),
 * sehingga disimpan pada tabel `app_pengaturan` dengan kunci `aktivasi_biaya`,
 * `aktivasi_biaya_langganan`, dan `aktivasi_wa`. Konstanta `AKTIVASI_BIAYA`,
 * `AKTIVASI_BIAYA_LANGGANAN`, dan `AKTIVASI_WA_PERMOHONAN` (inc/config.php) menjadi NILAI BAWAAN
 * bila belum pernah diubah, dan dipakai kembali oleh tombol "Kembalikan Bawaan".
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
        // Isian nominal ditulis bebas ("1.000.000" / "1000000" / "1.000.000,50") → hanya angkanya
        // yang dipakai (pemisah ribuan & tanda baca diabaikan agar pengguna tidak bingung).
        $pertamaRaw = (string) ($_POST['aktivasi_biaya'] ?? '');
        $langgananRaw = (string) ($_POST['aktivasi_biaya_langganan'] ?? '');
        $waRaw = trim((string) ($_POST['aktivasi_wa'] ?? ''));

        $pertama = (int) preg_replace('/\D/', '', $pertamaRaw);
        $langganan = (int) preg_replace('/\D/', '', $langgananRaw);

        if (preg_replace('/\D/', '', $pertamaRaw) === '') {
            $galat[] = 'Nominal biaya aktivasi pertama wajib diisi (angka rupiah).';
        } elseif ($pertama > AKTIVASI_BIAYA_MAKS) {
            $galat[] = 'Nominal biaya aktivasi pertama terlalu besar (maksimal '
                . rupiah(AKTIVASI_BIAYA_MAKS) . ').';
        }
        if (preg_replace('/\D/', '', $langgananRaw) === '') {
            $galat[] = 'Nominal biaya langganan wajib diisi (angka rupiah).';
        } elseif ($langganan > AKTIVASI_BIAYA_MAKS) {
            $galat[] = 'Nominal biaya langganan terlalu besar (maksimal '
                . rupiah(AKTIVASI_BIAYA_MAKS) . ').';
        }
        if ($pertama > 0 && $langganan > $pertama) {
            // Bukan larangan mutlak, tetapi hampir pasti salah ketik → diberi tahu (tetap disimpan
            // bila pengguna memang menghendakinya? TIDAK: ditolak supaya tidak ada tarif janggal).
            $galat[] = 'Biaya langganan (' . rupiah($langganan) . ') lebih besar daripada biaya aktivasi '
                . 'pertama (' . rupiah($pertama) . '). Periksa kembali nominalnya.';
        }
        if ($waRaw === '') {
            $galat[] = 'Nomor WA HD Admin wajib diisi.';
        } elseif (!aktivasi_wa_sah($waRaw)) {
            $galat[] = 'Nomor WA HD Admin belum benar — tulis angka saja (boleh diawali 0 atau 62), '
                . '9 sampai 15 digit, mis. 08xxxxxxxxxx.';
        }

        if ($galat) {
            // PENTING: jangan mengalihkan halaman saat validasi gagal — pesan galat dan isian
            // pengguna harus tetap terlihat (pola $tampilkanForm).
            $tampilkanForm = true;
        } else {
            aktivasi_biaya_simpan($pertama, $langganan, $waRaw);
            flash('Pengaturan biaya aktivasi disimpan: biaya aktivasi pertama ' . rupiah($pertama)
                . ', biaya langganan ' . rupiah($langganan) . ', nomor WA HD Admin '
                . aktivasi_wa() . '. Halaman Status & Aktivasi serta pesan WhatsApp langsung memakai '
                . 'nilai baru ini.');
            redirect('biaya_aktivasi.php');
        }
    } elseif ($aksi === 'bawaan') {
        // Kembalikan ke nilai bawaan aplikasi (konstanta di inc/config.php).
        aktivasi_biaya_bawaan();
        flash('Biaya aktivasi, biaya langganan, dan nomor WA HD Admin dikembalikan ke bawaan aplikasi.',
            'warn');
        redirect('biaya_aktivasi.php');
    } else {
        redirect('biaya_aktivasi.php');
    }
}

// Isian: dari POST saat validasi gagal, kalau tidak dari pengaturan tersimpan.
$pertama = $tampilkanForm
    ? (string) ($_POST['aktivasi_biaya'] ?? '')
    : (string) aktivasi_biaya_pertama();
$langganan = $tampilkanForm
    ? (string) ($_POST['aktivasi_biaya_langganan'] ?? '')
    : (string) aktivasi_biaya_langganan();
$wa = $tampilkanForm ? trim((string) ($_POST['aktivasi_wa'] ?? '')) : aktivasi_wa();
$pakaiBawaan = aktivasi_biaya_masih_bawaan();

page_top('Biaya & WA Aktivasi', 'Super Admin &middot; nominal biaya aktivasi, langganan, dan nomor WA HD Admin');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('card', 16) ?> Biaya &amp; WA Aktivasi</h2>
      <p>Mengatur <b>nominal biaya aktivasi</b>, <b>biaya langganan</b>, dan <b>nomor WA HD Admin</b>
        yang dipakai pada menu <b>Status &amp; Aktivasi</b> seluruh sekolah/madrasah — termasuk
        <b>tombol permohonan kode aktivasi</b> dan <b>tombol kirim bukti transfer</b>.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= $pakaiBawaan ? 'masih bawaan' : 'sudah disesuaikan' ?></span>
      <?php if (!$pakaiBawaan): ?>
        <form method="post" data-confirm="Kembalikan biaya aktivasi, biaya langganan, dan nomor WA ke bawaan aplikasi?">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="bawaan">
          <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('prev', 14) ?> Kembalikan Bawaan</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <form method="post" class="form-grid" id="formBiayaAktivasi">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">

    <?php foreach ($galat as $g): ?>
      <div class="alert alert-err"><?= ikon('alert', 16) ?><span><?= e($g) ?></span></div>
    <?php endforeach; ?>

    <div class="grid g3" style="gap:16px">
      <div>
        <label class="f" for="aktivasi_biaya">Biaya Aktivasi Pertama *</label>
        <input type="text" inputmode="numeric" id="aktivasi_biaya" name="aktivasi_biaya" required
               value="<?= e($pertama) ?>" placeholder="mis. 1000000">
        <div class="hint">Nominal untuk <b>aktivasi pertama</b> (masa aktif <?= (int) aktivasi_hari() ?> hari).
          Boleh ditulis dengan titik pemisah, mis. <b>1.000.000</b>.</div>
      </div>
      <div>
        <label class="f" for="aktivasi_biaya_langganan">Biaya Langganan *</label>
        <input type="text" inputmode="numeric" id="aktivasi_biaya_langganan" name="aktivasi_biaya_langganan"
               required value="<?= e($langganan) ?>" placeholder="mis. 500000">
        <div class="hint">Nominal <b>perpanjangan tahun berikutnya</b> — muncul sebagai info
          "biaya langganan … untuk tahun depan" pada lembaga yang sudah diaktifasi.</div>
      </div>
      <div>
        <label class="f" for="aktivasi_wa">Nomor WA HD Admin *</label>
        <input type="text" inputmode="tel" id="aktivasi_wa" name="aktivasi_wa" required
               value="<?= e($wa) ?>" placeholder="mis. 085240102180">
        <div class="hint">Tujuan <b>permohonan kode aktivasi</b> sekaligus <b>bukti transfer</b>.
          Boleh diawali <b>0</b> atau <b>62</b>.</div>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Pengaturan</button>
      <span class="xs mut">Berlaku untuk <b>semua sekolah/madrasah</b> — tidak perlu disetel ulang per lembaga.</span>
    </div>
  </form>
</div>

<?php // PRATINJAU: ringkasan apa yang akan dilihat pengelola sekolah pada halaman Status & Aktivasi,
      // supaya Super Admin tahu hasilnya tanpa berpindah halaman. ?>
<div class="card">
  <div class="card-head">
    <div><h2><?= ikon('eye', 16) ?> Pratinjau</h2>
      <p>Kalimat dan tombol yang tampil pada halaman <b>Status &amp; Aktivasi</b> dengan pengaturan di atas.</p></div>
  </div>

  <div class="biaya-aktivasi" id="pratinjauBiaya">
    <div class="biaya-kepala">
      <span class="biaya-ikon"><?= ikon('card', 18) ?></span>
      <div>
        <span class="xs mut">Biaya aktivasi (masa aktif <?= (int) aktivasi_hari() ?> hari)</span>
        <b class="biaya-nominal"><?= e(rupiah(aktivasi_biaya_pertama())) ?></b>
      </div>
    </div>
    <p class="biaya-kalimat">
      Untuk mengaktifasi Lembaga anda silahkan transfer
      <b><?= e(rupiah(aktivasi_biaya_pertama())) ?></b> ke <b><?= e(AKTIVASI_REKENING) ?></b>.
      Jika sudah melakukan transfer silahkan kirim bukti transfer ke wa.
    </p>
    <a class="btn btn-primary btn-bukti" href="<?= e(aktivasi_tautan_permohonan(null)) ?>"
       target="_blank" rel="noopener noreferrer"><?= ikon('whatsapp', 16) ?> Kirim Bukti Transfer ke WA</a>
    <p class="biaya-tarif xs mut">
      Aktivasi pertama (masa aktif <?= (int) aktivasi_hari() ?> hari):
      <b><?= e(rupiah(aktivasi_biaya_pertama())) ?></b> &middot;
      Langganan tahun berikutnya: <b><?= e(rupiah(aktivasi_biaya_langganan())) ?></b>.
    </p>
  </div>

  <div class="alert alert-ok biaya-langganan">
    <?= ikon('check', 16) ?>
    <span>Info yang muncul pada lembaga yang <b>sudah diaktifasi</b>: <b>biaya langganan
      <?= e(rupiah(aktivasi_biaya_langganan())) ?> untuk tahun depan</b>.</span>
  </div>

  <div class="rekening-aktivasi">
    <div class="rekening-baris">
      <span class="rekening-ikon"><?= ikon('whatsapp', 18) ?></span>
      <div>
        <span class="xs mut">Tautan WA yang dibuka kedua tombol (permohonan &amp; bukti transfer)</span>
        <b id="waTeks"><?= e('https://wa.me/' . wa_internasional(aktivasi_wa())) ?></b>
      </div>
      <button class="btn btn-ghost btn-sm" type="button" data-salin="#waTeks"><?= ikon('doc', 14) ?> Salin</button>
    </div>
    <p class="rekening-ket">
      <?= ikon('alert', 14) ?>
      Nomor yang dipakai sekarang: <b><?= e(aktivasi_wa()) ?></b>. Bila nomornya berganti, ubah pada
      formulir di atas — seluruh halaman Status &amp; Aktivasi (tombol permohonan kode aktivasi dan
      tombol kirim bukti transfer) ikut memakai nomor baru.
    </p>
  </div>
</div>
<?php
page_bottom();
