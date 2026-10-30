<?php
/**
 * AKTIVASI SEKOLAH/MADRASAH — halaman kunci masa uji coba.
 *
 * Permintaan pemilik: "Tambahkan fitur Aktifasi Sekolah/Madrasah yang mendaftar. Setelah Madrasah
 * mendaftar Super Admin akan mengaktifasi permanen atau memberikan masa uji coba 30 hari. Jika yang
 * dipilih masa uji coba maka setelah masa uji coba madrasah tidak bisa menggunakan aplikasi dan
 * diminta untuk memasukkan kode aktifasi lembaga. Generator kode aktifasi bersifat unik berdasarkan
 * nama sekolah."
 * Pilihan pemilik: (1) pengelola tetap BISA MASUK, tetapi seluruh menu terkunci ke halaman ini
 * sampai kode dimasukkan; (2) yang boleh memasukkan kode = Kepala Sekolah/Madrasah & Admin Operator;
 * (3) lama uji coba DIPILIH Super Admin (30/60/90 hari).
 *
 * Halaman ini juga bisa dibuka sewaktu masa uji coba MASIH berjalan (untuk melihat sisa hari), dan
 * Super Admin (di luar/dalam konteks) memakai halaman ini untuk melihat KODE AKTIVASI lembaga.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_login();          // gerbang_aktivasi() membebaskan berkas ini
$s = current_sekolah();
$super = is_super();
$boleh = boleh_aktivasi();     // kepala & admin operator (atau Super Admin dalam konteks)
$status = aktivasi_status($s);
$pesanKode = '';
$berhasil = false;

// Batas percobaan kode (anti tebak) — disimpan di sesi, 5 percobaan per 10 menit.
const AKTIVASI_MAKS_COBA = 5;
const AKTIVASI_JEDA_DETIK = 600;

// ============================================================================
// TAMPILAN KHUSUS AKUN PENGAWAS (v1.71.8 — permintaan pemilik)
//
// Permintaan pemilik: *"jika pengawas kelola madrasah yang masa aktif habis ada keterangan madrasah
// ini tidak bisa diakses karena masa aktif penggunaan aplikasi telah berakhir"*.
//
// Pengawas BUKAN pengguna lembaga binaannya: ia hanya melihat & mencetak, tidak dapat memasukkan
// kode aktivasi, dan TIDAK boleh ikut terkunci. Karena itu halaman ini untuk pengawas hanya memuat
// KETERANGAN: binaan mana yang dapat dibuka dan mana yang masa aktifnya sudah berakhir.
// Konteks binaan yang masa aktifnya habis dilepas di sini supaya spanduk "lembaga ini terkunci"
// (yang ditujukan kepada pengelola) tidak menyesatkan dan seluruh halaman lain tetap dapat dipakai.
// ============================================================================
if (is_pengawas()) {
    $binaanTerkunci = ($s !== null && aktivasi_terkunci($s)) ? $s : null;
    if ($binaanTerkunci !== null) {
        set_sekolah_context(null);
        $s = null;
        $status = aktivasi_status(null);
    }
    $binaanSaya = sekolah_binaan((int) $u['id'], true);
    // Warna pil mengikuti keadaan masa aktif (sama seperti pil pada halaman pengelola).
    $pilAktivasi = static function (array $st): string {
        if ($st['habis']) {
            return 'pill-D';
        }
        return $st['mode'] === 'permanen' ? 'pill-ok' : ($st['mode'] === 'aktif' ? 'pill-ok' : 'pill-uji');
    };
    page_top('Status & Aktivasi Binaan', 'Pengawas Sekolah/Madrasah &middot; hanya lihat');
    ?>
    <?php if ($binaanTerkunci !== null): ?>
      <div class="alert alert-err" id="pesanBinaanTerkunci">
        <?= ikon('lock', 16) ?>
        <span><b>Sekolah/madrasah ini tidak bisa diakses karena masa aktif penggunaan aplikasi telah berakhir.</b>
          Binaan <b><?= tetap_kata(nama_sekolah_tampil($binaanTerkunci)) ?></b> tidak dapat dibuka
          sampai masa aktifnya diperpanjang (data sebelumnya tetap aman, tidak ada yang dihapus).
          Kode aktivasi hanya dapat dimasukkan oleh <b>Kepala Sekolah/Madrasah</b> atau
          <b>Admin Operator</b> lembaga tersebut<?= aktivasi_wa() !== ''
              ? ' — kodenya diminta kepada HD Admin (WA ' . e(aktivasi_wa()) . ')' : '' ?>.</span>
      </div>
    <?php elseif ($s !== null): ?>
      <div class="alert alert-warn" id="pesanBinaanAktif">
        <?= ikon('eye', 16) ?>
        <span>Binaan <b><?= tetap_kata(nama_sekolah_tampil($s)) ?></b> masih dapat dibuka — status
          masa aktif: <b><?= e($status['label']) ?></b>. Akun pengawas hanya melihat &amp; mencetak;
          status aktivasi lembaga diatur oleh pengelola lembaga bersama HD Admin.</span>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head">
        <div>
          <h2><?= ikon('key', 16) ?> Daftar Binaan &amp; Status Masa Aktif</h2>
          <p>Hanya binaan yang masa aktif penggunaan aplikasinya masih berjalan yang dapat dibuka.</p>
        </div>
        <div class="flex">
          <a class="btn btn-ghost btn-sm" href="index.php?keluar=1"><?= ikon('switch', 15) ?> Daftar Binaan</a>
        </div>
      </div>

      <?php if (!$binaanSaya): ?>
        <div class="empty">
          <?= ikon('school', 34) ?>
          <h3>Belum ada sekolah/madrasah binaan</h3>
          <p>Hubungi Super Admin aplikasi untuk menetapkan sekolah/madrasah binaan akun Anda.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tbl" id="tblStatusBinaan">
            <thead>
              <tr><th>Nama <?= tetap_kata('Sekolah/Madrasah') ?></th>
                <th class="ctr">Status Masa Aktif</th>
                <th>Keterangan</th>
                <th class="ctr">Tindakan</th></tr>
            </thead>
            <tbody>
              <?php foreach ($binaanSaya as $b):
                  $stB = aktivasi_status($b);
                  $kunciB = aktivasi_terkunci($b);
                  ?>
                <tr>
                  <td><b><?= e((string) $b['nama']) ?></b>
                    <span class="tbl-sub">NPSN <?= e((string) (($b['npsn'] ?? '') !== '' ? $b['npsn'] : '-')) ?></span></td>
                  <td class="ctr"><span class="pill <?= $pilAktivasi($stB) ?>"><?= e($stB['label']) ?></span></td>
                  <td class="xs">
                    <?php if ($kunciB): ?>
                      <b>Tidak bisa diakses</b> — masa aktif penggunaan aplikasi telah berakhir<?= $stB['sampai_ts'] > 0
                          ? ' pada ' . e(tanggal_id($stB['sampai'], true)) : '' ?>. Data binaan tetap tersimpan.
                    <?php elseif ($stB['mode'] === 'permanen'): ?>
                      Dapat dibuka — masa aktif tanpa batas waktu.
                    <?php elseif ($stB['sampai_ts'] > 0): ?>
                      Dapat dibuka — masa aktif berakhir <?= e(tanggal_id($stB['sampai'], true)) ?>.
                    <?php else: ?>
                      Dapat dibuka.
                    <?php endif; ?>
                  </td>
                  <td class="ctr">
                    <?php if ($kunciB): ?>
                      <span class="mut xs">hanya keterangan</span>
                    <?php else: ?>
                      <form method="post" action="index.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="masuk">
                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                        <button class="btn btn-primary btn-sm" type="submit"><?= ikon('eye', 14) ?> Buka Data</button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="hint">Akun pengawas <b>tidak dapat</b> memasukkan kode aktivasi maupun mengubah status
          aktivasi lembaga — keduanya wewenang Kepala Sekolah/Madrasah atau Admin Operator bersama
          HD Admin.</p>
      <?php endif; ?>
    </div>
    <?php
    page_bottom();
    exit;
}


$coba = $_SESSION['aktivasi_coba'] ?? ['jumlah' => 0, 'sejak' => 0];
if ((int) $coba['sejak'] + AKTIVASI_JEDA_DETIK < time()) {
    $coba = ['jumlah' => 0, 'sejak' => 0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $s) {
    // Pemeriksaan HAK AKSES lebih dulu daripada token: akun yang tidak berhak (mis. guru) mendapat
    // jawaban 403 yang jelas — bukan 419 karena formulirnya memang tidak pernah ditampilkan.
    if (!$boleh) {
        tolak403('Hanya Kepala Sekolah/Madrasah dan Admin Operator yang dapat mengaktivasi lembaga ini.');
    }
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? 'aktivasi');

    if ($aksi === 'aktivasi') {
        if ((int) $coba['jumlah'] >= AKTIVASI_MAKS_COBA) {
            $pesanKode = 'Terlalu banyak percobaan kode. Tunggu 10 menit lalu coba lagi, atau hubungi Admin aplikasi.';
        } else {
            $masuk = (string) ($_POST['kode'] ?? '');
            if (trim($masuk) === '') {
                $pesanKode = 'Kode aktivasi belum diisi.';
            } elseif (kode_aktivasi_cocok($s, $masuk)) {
                // MASA AKTIF 365 HARI (permintaan pemilik): seluruh fitur terbuka, lengkap dengan
                // hitung mundur; bila masa aktif habis, aplikasi terkunci lagi dan kode baru dapat
                // diminta kembali ke HD Admin.
                db()->exec('BEGIN IMMEDIATE');
                try {
                    $hari = aktivasi_aktifkan((int) $s['id']);
                    db()->exec('COMMIT');
                } catch (Throwable $ex) {
                    db()->exec('ROLLBACK');
                    throw $ex;
                }
                unset($_SESSION['aktivasi_coba']);
                flash('Terima kasih. ' . nama_sekolah_tampil($s) . ' sudah AKTIF dan seluruh fitur '
                    . 'terbuka dengan masa aktif ' . $hari . ' hari (1 tahun). Hitung mundur masa aktif '
                    . 'dapat dilihat pada halaman ini.');
                // Pola POST→Redirect→GET supaya memuat ulang halaman tidak mengirim kode lagi.
                redirect('aktivasi.php');
            } else {
                $coba['jumlah'] = (int) $coba['jumlah'] + 1;
                $coba['sejak'] = (int) ($coba['sejak'] ?: time());
                $_SESSION['aktivasi_coba'] = $coba;
                $pesanKode = 'Kode aktivasi tidak sesuai untuk lembaga ini. Periksa kembali penulisannya '
                    . '(huruf besar/kecil & tanda hubung tidak berpengaruh), lalu coba lagi atau hubungi Admin aplikasi.';
            }
        }
    } elseif ($aksi === 'perpanjang' && $super) {
        // Pintasan Super Admin (dalam konteks sekolah): beri MASA AKTIF 1 tahun, uji coba, atau permanen.
        $mode = (string) ($_POST['mode'] ?? 'aktif');
        if ($mode === 'permanen') {
            aktivasi_permanen((int) $s['id']);
            flash('Sekolah/madrasah ' . nama_sekolah_tampil($s) . ' diaktifkan PERMANEN oleh Super Admin.');
        } elseif ($mode === 'aktif') {
            $hari = aktivasi_aktifkan((int) $s['id'], (int) ($_POST['hari_aktif'] ?? aktivasi_hari()));
            flash('Masa aktif ' . nama_sekolah_tampil($s) . ' diberikan selama ' . $hari . ' hari oleh Super Admin.');
        } else {
            $hari = aktivasi_uji_coba((int) $s['id'], (int) ($_POST['hari'] ?? UJI_COBA_HARI_DEFAULT));
            flash('Masa uji coba ' . nama_sekolah_tampil($s) . ' diberikan selama ' . $hari . ' hari.');
        }
        redirect('aktivasi.php');
    }
}

// ⚠️ Seluruh nilai turunan dihitung di SINI — SEBELUM markup apa pun. Nilai-nilai ini dipakai pada
// formulir kode (judul "pertama/perpanjangan"), kartu biaya, dan kartu permohonan; bila dihitung di
// tengah halaman, pemakaian pertamanya membaca variabel yang BELUM ada (halaman menampilkan label
// yang salah tanpa terlihat "rusak" — pernah terjadi).
$waTautan = aktivasi_tautan_permohonan($s);
$biayaBerlaku = aktivasi_biaya_berlaku($status, $s);
$pernahAktif = aktivasi_pernah_aktif($status, $s);
$waBukti = aktivasi_tautan_bukti($s, $status);

page_top('Aktivasi Sekolah/Madrasah', 'Status penggunaan aplikasi untuk lembaga ini');
?>
<div class="grid g21">
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= ikon('lock', 16) ?> Status Aktivasi Lembaga</h2>
        <p>
          <?php if ($status['habis']): ?>
            Masa aktif lembaga ini telah berakhir, sehingga aplikasi <b>terkunci</b>: seluruh fitur tidak
            dapat dipakai sampai <b>kode aktivasi</b> yang baru dimasukkan. Seluruh data sebelumnya tetap
            aman. Masukkan kodenya pada formulir di bawah — bila belum punya, kirim <b>permohonan</b>
            kepada HD Admin (lihat bagian <b>Aktivasi Sekolah/Madrasah</b>).
          <?php elseif ($status['mode'] === 'aktif'): ?>
            Lembaga ini <b>AKTIF</b> — seluruh fitur terbuka dengan <b>masa aktif 1 tahun</b>.
            Hitung mundur masa aktifnya terlihat di bawah.
          <?php elseif ($status['mode'] === 'uji_coba'): ?>
            Lembaga ini sedang memakai <b>masa uji coba</b>. Semua menu tetap dapat dipakai sampai masa
            uji coba berakhir.
          <?php else: ?>
            Lembaga ini sudah <b>aktif permanen</b> — tidak ada batas waktu penggunaan.
          <?php endif; ?>
        </p>
      </div>
      <span class="pill <?= $status['habis'] ? 'pill-D' : ($status['mode'] === 'uji_coba' ? 'pill-warn' : 'pill-ok') ?>">
        <?= e($status['label']) ?>
      </span>
    </div>

    <?php if (!$s): ?>
      <div class="alert alert-warn">
        <?= ikon('alert', 16) ?>
        <span>Belum ada sekolah/madrasah dalam konteks. <?= $super ? 'Pilih sekolah pada halaman Kelola Sekolah.' : 'Hubungi Admin aplikasi.' ?></span>
      </div>
    <?php else: ?>
      <div class="sheet-meta" style="margin:0 0 16px">
        <div><b>Lembaga</b>: <?= tetap_kata(nama_sekolah_tampil($s)) ?></div>
        <div><b>Status</b>: <?= e($status['label']) ?></div>
        <?php if ($status['mode'] === 'uji_coba'): ?>
          <div><b>Uji coba mulai</b>: <?= e($status['mulai'] !== '' ? tanggal_id($status['mulai'], true) : '-') ?></div>
          <div><b>Berakhir</b>: <?= e($status['sampai'] !== '' ? tanggal_id($status['sampai'], true) : '-') ?></div>
        <?php elseif ($status['mode'] === 'aktif'): ?>
          <div><b>Diaktifkan</b>: <?= e($status['mulai'] !== '' ? tanggal_id($status['mulai'], true) : '-') ?></div>
          <div><b>Masa aktif berakhir</b>: <?= e($status['sampai'] !== '' ? tanggal_id($status['sampai'], true) : '-') ?></div>
          <div><b>Periode aktivasi</b>: ke-<?= (int) $status['periode'] ?></div>
        <?php else: ?>
          <div><b>Diaktifkan</b>: <?= e(!empty($s['aktivasi_pada']) ? tanggal_id((string) $s['aktivasi_pada'], true) : '-') ?></div>
        <?php endif; ?>
      </div>

      <?php
      // HITUNG MUNDUR masa uji coba (permintaan pemilik: "Pada bagian Status Aktivasi Lembaga
      // tampilkan sisa hari dan jam dalam hitungan mundur"). Nilai awal dicetak PHP (tetap terbaca
      // walau JavaScript gagal dimuat) lalu dihitung ulang tiap detik oleh initHitungMundur() dengan
      // waktu SERVER sebagai acuan supaya jam perangkat pengguna tidak berpengaruh.
      ?>
      <?php if (in_array($status['mode'], ['aktif', 'uji_coba'], true) && !$status['habis']): ?>
        <?php
        $sisaDetik = max(0, (int) $status['sisa_detik']);
        $hm = [
            'hari'  => intdiv($sisaDetik, 86400),
            'jam'   => (int) floor(($sisaDetik % 86400) / 3600),
            'menit' => (int) floor(($sisaDetik % 3600) / 60),
            'detik' => (int) ($sisaDetik % 60),
        ];
        $hmLabel = 'Sisa ' . $hm['hari'] . ' hari ' . str_pad((string) $hm['jam'], 2, '0', STR_PAD_LEFT)
            . ':' . str_pad((string) $hm['menit'], 2, '0', STR_PAD_LEFT)
            . ':' . str_pad((string) $hm['detik'], 2, '0', STR_PAD_LEFT);
        ?>
        <div class="hitung-mundur" data-hitung-mundur data-berakhir="<?= (int) $status['sampai_ts'] ?>" data-sekarang="<?= time() ?>">
          <div class="hitung-mundur-head">
            <span class="hitung-mundur-judul"><?= ikon('clock', 15) ?> Hitung mundur <?= $status['mode'] === 'aktif' ? 'masa aktif' : 'masa uji coba' ?></span>
            <span class="hitung-mundur-ket" aria-live="polite"><?= e($hmLabel) ?></span>
          </div>
          <div class="hitung-mundur-unit">
            <?php foreach ([['hari', 'Hari'], ['jam', 'Jam'], ['menit', 'Menit'], ['detik', 'Detik']] as [$kunci, $label]): ?>
              <div class="hm-unit">
                <b data-hm="<?= $kunci ?>"><?= $kunci === 'hari'
                    ? (int) $hm[$kunci] : e(str_pad((string) $hm[$kunci], 2, '0', STR_PAD_LEFT)) ?></b>
                <span><?= $label ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="hitung-mundur-kaki">Berakhir <b><?= e(tanggal_id($status['sampai'], true)) ?></b>
            pukul <b><?= e(date('H:i', (int) $status['sampai_ts'])) ?></b> WIB. Setelah itu seluruh
            fitur <b>terkunci otomatis</b> (data lama tetap aman) sampai kode aktivasi yang baru
            dimasukkan — minta kode barunya ke HD Admin.</div>
        </div>
      <?php elseif (in_array($status['mode'], ['aktif', 'uji_coba'], true)): ?>
        <div class="hitung-mundur habis">
          <?= ikon('clock', 15) ?>
          <span><?= $status['mode'] === 'aktif' ? 'Masa aktif' : 'Masa uji coba' ?> telah berakhir pada
            <b><?= e(tanggal_id($status['sampai'], true)) ?></b> pukul
            <b><?= e(date('H:i', (int) $status['sampai_ts'])) ?></b> WIB. Seluruh fitur terkunci —
            data sebelumnya tetap aman. Masukkan kode aktivasi yang baru pada formulir di bawah.</span>
        </div>
      <?php endif; ?>

      <?php if ($pesanKode !== ''): ?>
        <div class="alert alert-err"><?= ikon('alert', 16) ?><span><?= e($pesanKode) ?></span></div>
      <?php endif; ?>

      <?php if ($status['mode'] === 'permanen' && !$status['habis']): ?>
        <div class="alert alert-ok">
          <?= ikon('check', 16) ?>
          <span>Aplikasi siap dipakai tanpa batas waktu. Terima kasih.</span>
        </div>
        <div class="flex">
          <a class="btn btn-primary" href="index.php"><?= ikon('dashboard', 16) ?> Buka Dashboard</a>
        </div>
      <?php endif; ?>

      <?php if ($status['mode'] === 'aktif' && !$status['habis']): ?>
        <div class="alert alert-ok">
          <?= ikon('check', 16) ?>
          <span>Aplikasi <b>AKTIF</b> — seluruh fitur terbuka sampai
            <b><?= e(tanggal_id($status['sampai'], true)) ?></b> (masa aktif
            <?= (int) aktivasi_hari() ?> hari / 1 tahun). Bila masa aktifnya habis, aplikasi terkunci
            otomatis dan Anda dapat meminta <b>kode aktivasi yang baru</b> kepada HD Admin lewat bagian
            <b>Aktivasi Sekolah/Madrasah</b> di bawah.</span>
        </div>
        <div class="flex" style="margin-bottom:16px">
          <a class="btn btn-primary" href="index.php"><?= ikon('dashboard', 16) ?> Buka Dashboard</a>
        </div>
      <?php endif; ?>

      <?php
      // ==========================================================================================
      // INPUT KODE AKTIVASI — permintaan pemilik: *"Tampilkan fitur untuk menginput kode aktifasi
      // perpanjangan. Pastikan 1 kode aktifasi hanya bisa digunakan sekali input (tidak bisa
      // digunakan lebih dari 1 kali). Sehingga kode aktifasi yang muncul di super admin akan berubah
      // jika sudah dipakai"*.
      // Judul & tombolnya menyesuaikan: "pertama" bagi yang belum pernah aktivasi, "perpanjangan"
      // bagi yang sudah. Kode aktivasi BERGANTUNG PERIODE (lihat kode_aktivasi_sekolah()), jadi
      // setelah dipakai kode itu tidak sah lagi dan kode di akun Super Admin ikut berganti.
      // Tersedia juga selagi masa aktif MASIH berjalan (perpanjangan lebih awal — harinya
      // ditambahkan dari tanggal berakhir yang masih berlaku).
      // ==========================================================================================
      ?>
      <?php if ($status['mode'] === 'permanen'): ?>
        <div class="alert alert-ok">
          <?= ikon('check', 16) ?>
          <span>Aplikasi ini <b>aktif permanen</b> — tidak ada masa aktif yang perlu diperpanjang,
            sehingga <b>kode aktivasi tidak diperlukan</b>.</span>
        </div>
      <?php elseif ($boleh): ?>
        <form method="post" class="form-grid" id="formKodeAktivasi" style="max-width:560px">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="aktivasi">
          <div class="section-title mb8">
            <?= ikon('key', 14) ?>
            <?= $pernahAktif ? 'Kode Aktivasi Perpanjangan' : 'Kode Aktivasi Pertama' ?>
          </div>
          <div>
            <label class="f" for="kode"><?= $pernahAktif
                ? 'Kode Aktivasi Perpanjangan *' : 'Kode Aktivasi Lembaga *' ?></label>
            <input type="text" id="kode" name="kode" required autocomplete="off"
                   placeholder="mis. 4K7P-9QX2-8WL3" style="letter-spacing:.12em;text-transform:uppercase">
            <div class="hint">
              <?php if ($pernahAktif): ?>
                Masukkan <b>kode aktivasi perpanjangan</b> yang dikirim HD Admin. Masa aktif
                <b><?= (int) aktivasi_hari() ?> hari (1 tahun)</b> akan <b>ditambahkan</b> dari
                <?= $status['sampai'] !== '' && !$status['habis']
                    ? '<b>tanggal berakhir yang sedang berjalan</b> (' . e(tanggal_id($status['sampai'], true)) . ')'
                    : '<b>hari ini</b>' ?> — sisa waktu yang sudah dibayar tidak hangus.
              <?php else: ?>
                Masukkan kode aktivasi yang dikirim HD Admin. Kode membuka seluruh fitur dengan
                <b>masa aktif <?= (int) aktivasi_hari() ?> hari (1 tahun)</b>.
              <?php endif; ?>
              Penulisan huruf besar/kecil dan tanda hubung tidak berpengaruh.
            </div>
          </div>
          <div class="flex">
            <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?>
              <?= $pernahAktif ? 'Perpanjang Masa Aktif' : 'Aktivasi Sekarang' ?></button>
            <a class="btn btn-ghost" href="index.php"><?= ikon('dashboard', 16) ?> Buka Dashboard</a>
          </div>
          <?php // Aturan SEKALI PAKAI (permintaan pemilik) — dijelaskan apa adanya supaya pengelola
                // tidak mencoba memakai kode lama lagi. ?>
          <p class="hint" id="ketKodeSekaliPakai" style="margin:0">
            <b>Setiap kode hanya dapat dipakai satu kali.</b> Setelah kode ini dipakai, kode tersebut
            tidak berlaku lagi dan kode yang tampil di akun Super Admin <b>berganti otomatis</b> —
            kode berikutnya diminta kembali kepada HD Admin<?= aktivasi_wa() !== ''
                ? ' (WA ' . e(aktivasi_wa()) . ')' : '' ?>.
          </p>
        </form>
      <?php else: ?>
        <div class="alert alert-warn">
          <?= ikon('alert', 16) ?>
          <span>Kolom <b>kode aktivasi<?= $pernahAktif ? ' perpanjangan' : '' ?></b> hanya dapat diisi oleh
            <b>Kepala Sekolah/Madrasah</b> atau <b>Admin Operator</b> (dan Super Admin). Hubungi
            pengelola lembaga Anda — kodenya diminta kepada HD Admin<?= aktivasi_wa() !== ''
                ? ' (WA ' . e(aktivasi_wa()) . ')' : '' ?>.</span>
        </div>
      <?php endif; ?>

      <?php
      // ==========================================================================================
      // KARTU "AKTIVASI SEKOLAH/MADRASAH" — permohonan kode aktivasi (permintaan pemilik):
      // "Pada menu status & Aktifasi pada bagian Aktivasi Sekolah/Madrasah tambahkan 'Hubungi HD
      //  Admin untuk melakukan aktifasi. Kirim permohonan kode aktifasi (pada saat klik permohonan
      //  otomatis akan diarahkan ke WA 085240102180 dengan pesan WA: …'. Pada menu status & Aktifasi
      //  ini juga tambahkan Nomer Rekening "Bank Mandiri 1500010135547. An. Supianto" dan tambahkan
      //  keterangan "Pastikan anda transfer … tidak akan aktif"."
      // Tautan WhatsApp-nya dibangun SERVER (wa_internasional + pesan siap kirim) sehingga klik
      // permohonan langsung membuka WA dengan pesan yang sudah terisi.
      // ==========================================================================================
      // BIAYA AKTIVASI (permintaan pemilik, v1.52.0): nominal ditampilkan apa adanya beserta kalimat
      // permintaan pembayaran, dan tombol kirim BUKTI TRANSFER ke WA nomor permohonan kode aktivasi.
      // Nilainya sudah dihitung di blok keadaan di atas halaman.
      ?>
      <div class="divider"></div>
      <div class="permohonan-aktivasi" id="permohonanAktivasi">
        <div class="permohonan-judul"><?= ikon('key', 16) ?> Aktivasi Sekolah/Madrasah</div>
        <p class="permohonan-ajak">
          <b>Hubungi HD Admin untuk melakukan aktifasi.</b> Kirim permohonan kode aktifasi lewat tombol
          di bawah ini (menuju WhatsApp <b><?= e(aktivasi_wa()) ?></b>).
        </p>
        <?php if ($waTautan !== ''): ?>
          <a class="btn btn-primary btn-permohonan" href="<?= e($waTautan) ?>" target="_blank" rel="noopener noreferrer">
            <?= ikon('upload', 16) ?> Kirim Permohonan Kode Aktifasi
          </a>
        <?php endif; ?>

        <?php // ---------------------------------------------------------------- NOMINAL BIAYA ?>
        <div class="biaya-aktivasi" id="biayaAktivasi">
          <div class="biaya-kepala">
            <span class="biaya-ikon"><?= ikon('card', 18) ?></span>
            <div>
              <span class="xs mut" id="biayaLabel"><?= e(aktivasi_biaya_label($status, $s)) ?></span>
              <b class="biaya-nominal" id="nominalAktivasi"><?= e(rupiah($biayaBerlaku)) ?></b>
            </div>
          </div>
          <p class="biaya-kalimat" id="kalimatBiaya">
            <?= e(aktivasi_kalimat_biaya($status, $s)) ?>
          </p>
          <?php if ($waBukti !== ''): ?>
            <a class="btn btn-primary btn-bukti" id="btnKirimBukti" href="<?= e($waBukti) ?>"
               target="_blank" rel="noopener noreferrer">
              <?= ikon('whatsapp', 16) ?> Kirim Bukti Transfer ke WA
            </a>
          <?php endif; ?>
          <?php // Permintaan pemilik: "Biaya aktifasi pertama hanya berlaku untuk yang belum pernah
                // melakukan aktifasi. Sehingga bagi yang sudah melakukan aktifasi pertama, hilangkan
                // biaya untuk aktifasi pertama dan yang ditampilkan hanya biaya langganan untuk tahun
                // berikutnya." → baris tarif HANYA menyebut biaya aktivasi pertama bagi lembaga yang
                // BELUM pernah diaktifasi. ?>
          <p class="biaya-tarif xs mut">
            <?php if ($pernahAktif): ?>
              Langganan tahun berikutnya: <b><?= e(rupiah(aktivasi_biaya_langganan())) ?></b>
              (masa aktif <?= (int) aktivasi_hari() ?> hari).
            <?php else: ?>
              Aktivasi pertama (masa aktif <?= (int) aktivasi_hari() ?> hari):
              <b><?= e(rupiah(aktivasi_biaya_pertama())) ?></b> &middot;
              Langganan tahun berikutnya: <b><?= e(rupiah(aktivasi_biaya_langganan())) ?></b>.
            <?php endif; ?>
          </p>
        </div>

        <?php // Permintaan pemilik: "Jika aplikasi sudah di aktifasi maka muncul info: biaya langganan
              // Rp. 500.000 untuk tahun depan". ?>
        <?php if ($pernahAktif): ?>
          <div class="alert alert-ok biaya-langganan" id="infoLangganan">
            <?= ikon('check', 16) ?>
            <span>Aplikasi ini <b>sudah diaktifasi</b> &mdash; <b>biaya langganan
              <?= e(rupiah(aktivasi_biaya_langganan())) ?> untuk tahun depan</b>.
              <?= $status['mode'] === 'aktif' && !$status['habis'] && $status['sampai'] !== ''
                  ? 'Masa aktif sekarang berjalan sampai <b>' . e(tanggal_id($status['sampai'], true)) . '</b>, '
                    . 'jadi pembayaran langganan dilakukan menjelang masa aktif itu berakhir.'
                  : ($status['habis']
                      ? 'Masa aktif sudah berakhir &mdash; lakukan pembayaran langganan lalu minta kode aktivasinya kepada HD Admin.'
                      : 'Pembayaran langganan dilakukan saat masa aktif akan berakhir.') ?></span>
          </div>
        <?php endif; ?>

        <div class="rekening-aktivasi mt16">
          <div class="rekening-baris">
            <span class="rekening-ikon"><?= ikon('card', 18) ?></span>
            <div>
              <span class="xs mut">Nomor Rekening untuk Aktifasi</span>
              <b id="rekeningTeks"><?= e(AKTIVASI_REKENING) ?></b>
            </div>
            <button class="btn btn-ghost btn-sm" type="button" data-salin="#rekeningTeks"><?= ikon('doc', 14) ?> Salin</button>
          </div>
          <p class="rekening-ket"><?= ikon('alert', 14) ?> <?= e(AKTIVASI_REKENING_KET) ?></p>
        </div>
      </div>

      <?php if ($super): ?>
        <div class="divider"></div>
        <?php // KODE AKTIVASI hanya terlihat Super Admin — JANGAN ditampilkan untuk pengelola sekolah. ?>
        <div class="section-title mb8"><?= ikon('key', 15) ?> Kode Aktivasi Lembaga
          (hanya Super Admin) &middot; <b>periode ke-<?= (int) $status['periode'] ?></b></div>
        <div class="kode-kotak">
          <code id="kodeAktivasi"><?= e(kode_aktivasi_sekolah($s)) ?></code>
          <button class="btn btn-ghost btn-sm" type="button" data-salin="#kodeAktivasi"><?= ikon('doc', 14) ?> Salin kode</button>
        </div>
        <p class="hint" style="margin:8px 0 16px">
          Kode ini <b>unik untuk <?= tetap_kata(nama_sekolah_tampil($s)) ?></b>, hanya berlaku untuk
          lembaga ini, dan <b>hanya dapat dipakai satu kali</b>. Begitu lembaga memasukkannya, kode di
          atas <b>berganti sendiri</b> (periode berikutnya) — itulah kode untuk perpanjangan
          selanjutnya. Berikan kepada pengelola lembaga bila masa aktifnya berakhir (atau langsung ubah
          statusnya di bawah).
        </p>
        <form method="post" class="flex" style="gap:10px;flex-wrap:wrap">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="perpanjang">
          <select name="mode" style="max-width:230px">
            <option value="aktif">Beri masa aktif 1 tahun</option>
            <option value="permanen">Aktifkan permanen</option>
            <option value="uji_coba">Beri masa uji coba</option>
          </select>
          <select name="hari_aktif" style="max-width:150px">
            <?php foreach ([aktivasi_hari(), 180, 90, 30] as $h): ?>
              <option value="<?= (int) $h ?>"><?= (int) $h ?> hari</option>
            <?php endforeach; ?>
          </select>
          <select name="hari" style="max-width:130px">
            <?php foreach (aktivasi_pilihan_hari() as $h): ?>
              <option value="<?= (int) $h ?>"><?= (int) $h ?> hari (uji coba)</option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Status</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <div><h2>Cara Kerja Aktivasi</h2><p>Ringkasan singkat untuk pengelola lembaga.</p></div>
    </div>
    <ol class="portal-alur">
      <li><b>Mendaftar</b> — sekolah/madrasah mengisi formulir pendaftaran pada halaman daftar
        (langsung dapat dipakai dalam <b>mode terbatas</b>).</li>
      <li><b>Permohonan aktifasi</b> — kirim permohonan kode aktifasi kepada <b>HD Admin</b> lewat
        tombol WhatsApp pada kartu <b>Aktivasi Sekolah/Madrasah</b> (atau hubungi HD Admin sesuai
        nomor WA yang tertera).</li>
      <?php // Permintaan pemilik: biaya aktivasi pertama HANYA berlaku bagi yang belum pernah
            // diaktifasi — bagi yang sudah, langkah ini menyebut biaya LANGGANAN saja. ?>
      <li><b>Transfer &amp; verifikasi</b> —
        <?php if ($pernahAktif): ?>
          transfer <b><?= e(rupiah(aktivasi_biaya_langganan())) ?></b> (langganan tahun berikutnya) ke
        <?php else: ?>
          transfer <b><?= e(rupiah(aktivasi_biaya_pertama())) ?></b> (aktivasi pertama; langganan tahun
          berikutnya <b><?= e(rupiah(aktivasi_biaya_langganan())) ?></b>) ke
        <?php endif; ?>
        nomor rekening yang tertera, lalu <b>kirim bukti transfer</b> ke WA HD Admin
        <b><?= e(aktivasi_wa()) ?></b> lewat tombol <b>Kirim Bukti Transfer ke WA</b>.</li>
      <li><b>Masukkan kode</b> — HD Admin mengirim <b>kode aktivasi lembaga</b>; masukkan pada formulir
        di halaman ini. Seluruh fitur langsung terbuka dengan <b>masa aktif <?= (int) aktivasi_hari() ?>
        hari (1 tahun)</b>, lengkap dengan hitung mundur.</li>
      <li><b>Masa aktif habis</b> — aplikasi <b>terkunci otomatis</b> (seluruh data lama tetap aman)
        dan kolom kode aktivasi muncul kembali. Minta <b>kode aktivasi yang baru</b> ke HD Admin untuk
        memperpanjang.</li>
    </ol>
    <div class="divider"></div>
    <ul class="portal-cek">
      <li>Kode aktivasi <b>unik per lembaga</b> (dibuat dari nama sekolah/madrasah), jadi kode milik
        lembaga lain tidak dapat dipakai di sini.</li>
      <li>Seluruh data tetap aman saat masa aktif berakhir — yang terkunci hanya penggunaan fiturnya,
        tidak ada data yang dihapus.</li>
      <li>Yang dapat memasukkan kode: <b>Kepala Sekolah/Madrasah</b> dan <b>Admin Operator</b>
        (Super Admin juga dapat membantu).</li>
      <li>Masa aktif dihitung <b>satu tahun (<?= (int) aktivasi_hari() ?> hari)</b> untuk setiap kode;
        kode berikutnya diperlukan bila masa aktif itu habis.</li>
      <?php if ($pernahAktif): ?>
        <li><b>Biaya langganan tahun berikutnya <?= e(rupiah(aktivasi_biaya_langganan())) ?></b> —
          ditransfer ke <b><?= e(AKTIVASI_REKENING) ?></b>, bukti transfernya dikirim ke WA
          <b><?= e(aktivasi_wa()) ?></b>.</li>
      <?php else: ?>
        <li><b>Biaya aktivasi pertama <?= e(rupiah(aktivasi_biaya_pertama())) ?></b> dan <b>biaya langganan tahun
          berikutnya <?= e(rupiah(aktivasi_biaya_langganan())) ?></b> — ditransfer ke
          <b><?= e(AKTIVASI_REKENING) ?></b>, bukti transfernya dikirim ke WA
          <b><?= e(aktivasi_wa()) ?></b>.</li>
      <?php endif; ?>
    </ul>
  </div>
</div>
<?php
page_bottom();
