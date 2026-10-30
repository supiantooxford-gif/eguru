<?php
/**
 * Kelola Sekolah/Madrasah (khusus Super Admin): tambah sekolah, ubah profil & ambang predikat,
 * atur akun kepala/operator, masuk/keluar konteks sekolah, nonaktifkan, dan hapus permanen.
 *
 * Pemilik memilih: sekolah/madrasah ditambahkan oleh Super Admin ATAU lewat pendaftaran mandiri
 * yang disetujui Super Admin (pendaftaran.php / daftar.php).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_super();
// Halaman lintas sekolah/madrasah → istilah generik "Sekolah".
$GLOBALS['__vs_istilah_nonaktif'] = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $s = $id > 0 ? sekolah_by_id($id) : null;

    // ---------------- keluar dari konteks sekolah
    if ($aksi === 'keluar') {
        set_sekolah_context(null);
        flash('Anda kembali ke tampilan lintas sekolah/madrasah.', 'warn');
        redirect('sekolah.php');
    }

    // ---------------- masuk (mengelola) sebuah sekolah
    if ($aksi === 'masuk') {
        if (!$s) {
            flash('Sekolah/madrasah tidak ditemukan.', 'err');
        } elseif ((int) $s['aktif'] !== 1) {
            flash(nama_sekolah_tampil($s) . ' sedang dinonaktifkan. Aktifkan kembali sebelum dikelola.', 'err');
        } elseif (($s['verifikasi'] ?? 'disetujui') !== 'disetujui') {
            flash(nama_sekolah_tampil($s) . ' belum disetujui sehingga belum dapat dipakai.', 'err');
        } else {
            set_sekolah_context($id);
            flash('Anda kini mengelola ' . nama_sekolah_tampil($s) . '. Seluruh data yang tampil hanya milik sekolah/madrasah ini.');
            redirect('index.php?sekolah=1');
        }
        redirect('sekolah.php');
    }

    // ---------------- tambah sekolah baru
    if ($aksi === 'tambah') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $jenis = ((string) ($_POST['jenis'] ?? 'umum')) === 'madrasah' ? 'madrasah' : 'umum';
        // v49: PILIHAN JENJANG (SD/SMP/SMA/SMK atau MI/MTs/MA/MAK) — pilihannya mengikuti jenis.
        $jenjang = trim((string) ($_POST['jenjang'] ?? ''));
        $npsn = trim((string) ($_POST['npsn'] ?? ''));
        $alamat = trim((string) ($_POST['alamat'] ?? ''));
        $telepon = trim((string) ($_POST['telepon'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $kepala = trim((string) ($_POST['kepala_sekolah'] ?? ''));
        $nip = nip_bersih($_POST['nip_kepala_sekolah'] ?? '');
        // v37: identitas lembaga — NSM & alamat per wilayah.
        // v39: identitas — NAMA + NIP PENGAWAS BINA (permintaan pemilik).
        $nsm = trim((string) ($_POST['nsm'] ?? ''));
        $desa = trim((string) ($_POST['desa'] ?? ''));
        $kecamatan = trim((string) ($_POST['kecamatan'] ?? ''));
        $kabupaten = trim((string) ($_POST['kabupaten'] ?? ''));
        $provinsi = trim((string) ($_POST['provinsi'] ?? ''));
        $pengawasNama = trim((string) ($_POST['pengawas_nama'] ?? ''));
        $pengawasNip = nip_bersih($_POST['pengawas_nip'] ?? '');
        $admNama = trim((string) ($_POST['admin_nama'] ?? ''));
        $admUser = trim((string) ($_POST['admin_username'] ?? ''));
        $admPass = (string) ($_POST['admin_password'] ?? '');
        $admRole = ((string) ($_POST['admin_role'] ?? 'kepala')) === 'operator' ? 'operator' : 'kepala';

        $galat = [];
        if ($nama === '') {
            $galat[] = 'Nama sekolah/madrasah wajib diisi.';
        }
        if ($jenjang !== '' && !jenjang_sah($jenjang, $jenis)) {
            $galat[] = 'Jenjang "' . $jenjang . '" tidak berlaku untuk jenis lembaga yang dipilih.';
        }
        if ($admNama === '' || $admUser === '' || strlen($admPass) < 6) {
            $galat[] = 'Nama, nama pengguna, dan kata sandi (min. ' . SANDI_MIN . ' karakter) akun pengelola wajib diisi.';
        }
        if ($admUser !== '' && one('SELECT id FROM users WHERE username = ?', [$admUser])) {
            $galat[] = 'Nama pengguna "' . $admUser . '" sudah dipakai (harus unik se-aplikasi).';
        }
        if ($galat) {
            foreach ($galat as $g) {
                flash($g, 'err');
            }
            redirect('sekolah.php');
        }

        $now = date('Y-m-d H:i:s');
        db()->exec('BEGIN IMMEDIATE');
        try {
            q('INSERT INTO sekolah (nama, npsn, nsm, alamat, desa, kecamatan, kabupaten, provinsi, telepon, email,
                    website, kepala_sekolah, nip_kepala_sekolah, pengawas_nama, pengawas_nip, jenis, jenjang,
                    verifikasi, aktif, didaftar, ditinjau, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"disetujui",1,?,?,?)',
                [$nama, $npsn, $nsm, $alamat, $desa, $kecamatan, $kabupaten, $provinsi, $telepon, $email,
                 trim((string) ($_POST['website'] ?? '')), $kepala, $nip, $pengawasNama, $pengawasNip,
                 $jenis, $jenjang, $now, $now, $now]);
            $sid = (int) db()->lastInsertId();
            q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, aktif, dibuat) VALUES (?,?,?,?,?,1,?)',
                [$sid, $admUser, password_hash($admPass, PASSWORD_DEFAULT), $admNama, $admRole, $now]);
                if (sandi_lemah_dasar($admPass)) {
                    q('UPDATE users SET sandi_wajib_ganti = 1 WHERE username = ?', [$admUser]);
                }
            provision_sekolah(db(), $sid);
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        flash('Sekolah/madrasah ' . $nama . ' ditambahkan beserta ' . count(mapel_awal()) . ' mata pelajaran awal, '
            . 'tahun ajaran, dan semester. Akun ' . role_label($admRole) . ': ' . $admUser . '.');
        redirect('sekolah.php');
    }

    if (!$s) {
        flash('Sekolah/madrasah tidak ditemukan.', 'err');
        redirect('sekolah.php');
    }

    // ---------------- simpan profil & aturan penilaian
    if ($aksi === 'simpan') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        if ($nama === '') {
            flash('Nama sekolah/madrasah wajib diisi.', 'err');
            redirect('sekolah.php');
        }
        $jenis = ((string) ($_POST['jenis'] ?? 'umum')) === 'madrasah' ? 'madrasah' : 'umum';
        // v49: jenjang hanya ditulis bila medannya benar-benar dikirim (form lama tetap aman); nilai
        // yang tidak berlaku untuk jenis tersebut dibuang (dikosongkan) — tidak ada nilai yang ditebak.
        $kirimJenjang = array_key_exists('jenjang', $_POST);
        $jenjang = trim((string) ($_POST['jenjang'] ?? ''));
        if ($jenjang !== '' && !jenjang_sah($jenjang, $jenis)) {
            flash('Jenjang "' . $jenjang . '" tidak berlaku untuk jenis lembaga yang dipilih — jenjang dikosongkan.', 'warn');
            $jenjang = '';
        }
        $a = (float) str_replace(',', '.', (string) ($_POST['batas_a'] ?? 90));
        $b = (float) str_replace(',', '.', (string) ($_POST['batas_b'] ?? 80));
        $c = (float) str_replace(',', '.', (string) ($_POST['batas_c'] ?? 70));
        $bobot = (float) str_replace(',', '.', (string) ($_POST['bobot_asas'] ?? 50));
        $jf = max(1, min(12, (int) ($_POST['jumlah_formatif'] ?? 4)));
        $js = max(1, min(12, (int) ($_POST['jumlah_sumatif'] ?? 4)));
        if (!($a > $b && $b > $c && $c >= 0 && $a <= 100) || $bobot < 0 || $bobot > 100) {
            flash('Ambang predikat harus A > B > C (0-100) dan bobot ASAS 0-100%.', 'err');
            redirect('sekolah.php');
        }
        q('UPDATE sekolah SET nama=?, jenis=?, '
            . ($kirimJenjang ? 'jenjang=?, ' : '')
            . 'npsn=?, nsm=?, alamat=?, desa=?, kecamatan=?, kabupaten=?, provinsi=?,
             telepon=?, email=?, website=?, kepala_sekolah=?, nip_kepala_sekolah=?,
             pengawas_nama=?, pengawas_nip=?,
             batas_a=?, batas_b=?, batas_c=?, bobot_asas=?, jumlah_formatif=?, jumlah_sumatif=? WHERE id=?',
            array_merge([$nama, $jenis], ($kirimJenjang ? [$jenjang] : []), [
             trim((string) ($_POST['npsn'] ?? '')), trim((string) ($_POST['nsm'] ?? '')),
             trim((string) ($_POST['alamat'] ?? '')), trim((string) ($_POST['desa'] ?? '')),
             trim((string) ($_POST['kecamatan'] ?? '')), trim((string) ($_POST['kabupaten'] ?? '')),
             trim((string) ($_POST['provinsi'] ?? '')),
             trim((string) ($_POST['telepon'] ?? '')), trim((string) ($_POST['email'] ?? '')), trim((string) ($_POST['website'] ?? '')),
             trim((string) ($_POST['kepala_sekolah'] ?? '')), nip_bersih($_POST['nip_kepala_sekolah'] ?? ''),
             trim((string) ($_POST['pengawas_nama'] ?? '')), nip_bersih($_POST['pengawas_nip'] ?? ''),
             $a, $b, $c, $bobot, $jf, $js, $id]));
        settings_all(true);
        current_sekolah(true);
        flash('Profil & pengaturan ' . $nama . ' disimpan.');
        redirect('sekolah.php');
    }

    // ---------------- tambah akun pengelola sekolah
    if ($aksi === 'akun') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $sandi = (string) ($_POST['password'] ?? '');
        $role = in_array((string) ($_POST['role'] ?? ''), ['kepala', 'operator'], true) ? (string) $_POST['role'] : 'kepala';
        if ($nama === '' || $username === '' || !sandi_pemeriksa($sandi, false)['ok']) {
            flash('Nama, nama pengguna, dan kata sandi (min. ' . SANDI_MIN . ' karakter) wajib diisi.', 'err');
        } elseif (one('SELECT id FROM users WHERE username = ?', [$username])) {
            flash('Nama pengguna "' . $username . '" sudah dipakai.', 'err');
        } else {
            q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, aktif, dibuat) VALUES (?,?,?,?,?,1,?)',
                [$id, $username, password_hash($sandi, PASSWORD_DEFAULT), $nama, $role, date('Y-m-d H:i:s')]);
            if (sandi_lemah_dasar($sandi)) {
                q('UPDATE users SET sandi_wajib_ganti = 1 WHERE username = ?', [$username]);
            }
            flash('Akun ' . role_label($role) . ' untuk ' . nama_sekolah_tampil($s) . ' dibuat: ' . $username . '.');
        }
        redirect('sekolah.php?edit=' . $id);
    }

    // ---------------- nonaktifkan / aktifkan kembali
    if ($aksi === 'aktivasi') {
        // Permintaan pemilik: Super Admin menentukan AKTIF PERMANEN atau MASA UJI COBA (30/60/90 hari).
        $mode = (string) ($_POST['mode'] ?? 'permanen');
        $hari = (int) ($_POST['hari'] ?? UJI_COBA_HARI_DEFAULT);
        if (!in_array($hari, aktivasi_pilihan_hari(), true)) {
            $hari = UJI_COBA_HARI_DEFAULT;
        }
        if ($mode === 'uji_coba') {
            aktivasi_uji_coba((int) $s['id'], $hari);
            $sBaru = one('SELECT * FROM sekolah WHERE id = ?', [(int) $s['id']]) ?: $s;
            flash('Masa uji coba ' . nama_sekolah_tampil($sBaru) . ' diberikan selama ' . $hari . ' hari. '
                . 'Kode aktivasi lembaga (periode ke-' . aktivasi_periode($sBaru)
                . ', hanya bisa dipakai sekali): ' . kode_aktivasi_sekolah($sBaru), 'warn');
        } else {
            aktivasi_permanen((int) $s['id']);
            flash('Sekolah/madrasah ' . nama_sekolah_tampil($s) . ' diaktifkan PERMANEN.');
        }
        redirect('sekolah.php');
    }

    if ($aksi === 'nonaktif' || $aksi === 'aktifkan') {
        // Aturannya satu sumber: sekolah_set_aktif() (dipakai juga oleh daftar sekolah pada dasbor).
        $hasil = sekolah_set_aktif($id, $aksi === 'aktifkan');
        if (!$hasil['ok']) {
            flash($hasil['pesan'], 'err');
        } elseif ($aksi === 'nonaktif') {
            $d = sekolah_dampak($id);
            flash(nama_sekolah_tampil($s) . ' dinonaktifkan. Datanya tetap utuh (' . $d['guru'] . ' guru, ' . $d['siswa']
                . ' siswa, ' . $d['nilai'] . ' baris nilai) dan dapat diaktifkan kembali.');
        } else {
            flash(nama_sekolah_tampil($s) . ' diaktifkan kembali.');
        }
        redirect('sekolah.php');
    }

    // ---------------- hapus permanen
    // SANDI KONFIRMASI (permintaan pemilik, v1.72.3): *"Sebelum menghapus super admin akan
    // dimintakan sandi (sandinya: 181288). Tujuan sandi ini agar ketika tidak sengaja di klik hapus
    // lembaga tidak langsung terhapus"*. Diperiksa di SERVER (bukan hanya di peramban).
    if ($aksi === 'hapus') {
        if (strtoupper(trim((string) ($_POST['konfirmasi'] ?? ''))) !== 'HAPUS') {
            flash('Ketik HAPUS pada kotak konfirmasi untuk menghapus sekolah/madrasah secara permanen.', 'err');
            redirect('sekolah.php');
        }
        if (!sekolah_hapus_sandi_sah($_POST['sandi'] ?? '')) {
            flash('Sandi konfirmasi salah — sekolah/madrasah TIDAK dihapus. Periksa kembali sandi yang '
                . 'diminta sebelum menekan Hapus permanen.', 'err');
            redirect('sekolah.php');
        }
        $hasil = sekolah_hapus_permanen($id);
        flash($hasil['pesan'], $hasil['ok'] ? 'warn' : 'err');
        redirect('sekolah.php');
    }

    redirect('sekolah.php');
}

// ---------------------------------------------------------------- data halaman
$cari = trim((string) ($_GET['cari'] ?? ''));
$fStatus = (string) ($_GET['status'] ?? '');
$edit = (int) ($_GET['edit'] ?? 0);
$editSekolah = $edit > 0 ? sekolah_by_id($edit) : null;

$w = [];
$p = [];
if ($cari !== '') {
    $w[] = '(nama LIKE ? OR npsn LIKE ? OR alamat LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($p, $like, $like, $like);
}
$daftar = all('SELECT * FROM sekolah' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY nama', $p);
$diaktifkan = [];
$nonaktif = [];
$belumDisetujui = [];
foreach ($daftar as $r) {
    if (($r['verifikasi'] ?? 'disetujui') !== 'disetujui') {
        $belumDisetujui[] = $r;
    } elseif ((int) $r['aktif'] === 1) {
        $diaktifkan[] = $r;
    } else {
        $nonaktif[] = $r;
    }
}
$kodeSekarang = sekolah_id();

/** Kartu ringkas sebuah sekolah + daftar akunnya. */
function kartu_sekolah(array $r, int $kodeSekarang, bool $aktif): void
{
    $sid = (int) $r['id'];
    $st = sekolah_stat($sid);
    $d = sekolah_dampak($sid);
    $akun = all('SELECT nama, username, role, aktif FROM users WHERE sekolah_id = ? ORDER BY role, nama', [$sid]);
    $tandaKelola = $kodeSekarang === $sid;
    ?>
    <div class="card<?= $tandaKelola ? ' card-terpilih' : '' ?>">
      <div class="card-head">
        <div>
          <?php $ak = aktivasi_status($r); ?>
          <h2><?= tetap_kata(nama_sekolah_tampil($r)) ?>
            <span class="pill <?= $aktif ? 'pill-ok' : 'pill-gray' ?>"><?= $aktif ? 'Aktif' : 'Nonaktif' ?></span>
            <span class="pill pill-gold"><?= $r['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah Umum' ?></span>
            <?php // v49: PILIHAN JENJANG tiap sekolah (Super Admin). Belum dipilih → ditandai abu-abu. ?>
            <span class="pill <?= ((string) ($r['jenjang'] ?? '')) !== '' ? 'pill-B' : 'pill-gray' ?>"><?= ((string) ($r['jenjang'] ?? '')) !== '' ? 'Jenjang ' . e((string) $r['jenjang']) : 'jenjang belum dipilih' ?></span>
            <span class="pill <?= $ak['mode'] === 'permanen' ? 'pill-ok' : ($ak['habis'] ? 'pill-D' : 'pill-uji') ?>"><?= e($ak['label']) ?></span>
            <?php if ($tandaKelola): ?><span class="pill pill-B">sedang dikelola</span><?php endif; ?>
          </h2>
          <p>NPSN <?= e((string) ($r['npsn'] ?: '-')) ?> &middot; <?= e((string) ($r['alamat'] ?: 'alamat belum diisi')) ?>
            &middot; Kepala: <?= e((string) ($r['kepala_sekolah'] ?: '-')) ?></p>
        </div>
        <div class="row-actions">
          <?php if ($aktif && !$tandaKelola): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="masuk">
              <input type="hidden" name="id" value="<?= $sid ?>">
              <button class="btn btn-primary btn-sm" type="submit"><?= ikon('switch', 14) ?> Kelola</button>
            </form>
          <?php elseif ($tandaKelola): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="keluar">
              <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('logout', 14) ?> Keluar dari konteks</button>
            </form>
          <?php endif; ?>
          <a class="btn btn-ghost btn-sm" href="sekolah.php?edit=<?= $sid ?>"><?= ikon('edit', 14) ?> Ubah</a>
          <?php if ($aktif): ?>
            <form method="post" data-confirm="Nonaktifkan <?= e((string) $r['nama']) ?>? Datanya tetap tersimpan dan dapat diaktifkan kembali.">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="nonaktif">
              <input type="hidden" name="id" value="<?= $sid ?>">
              <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('lock', 14) ?> Nonaktifkan</button>
            </form>
          <?php else: ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="aktifkan">
              <input type="hidden" name="id" value="<?= $sid ?>">
              <button class="btn btn-ok btn-sm" type="submit"><?= ikon('check', 14) ?> Aktifkan</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <?php // Kode aktivasi lembaga (unik berdasar nama + id + PERIODE) + pengaturan masa uji coba/aktivasi.
            // Aturan SEKALI PAKAI: setelah lembaga memakai kodenya, `aktivasi_ke` naik sehingga kode
            // di sini otomatis BERGANTI (lihat kode_aktivasi_sekolah()). ?>
      <div class="kode-kotak mb8">
        <span class="xs mut">Kode aktivasi lembaga (periode ke-<?= (int) aktivasi_periode($r) ?>,
          hanya bisa dipakai sekali):</span>
        <code id="kode<?= $sid ?>"><?= e(kode_aktivasi_sekolah($r)) ?></code>
        <button class="btn btn-ghost btn-sm" type="button" data-salin="#kode<?= $sid ?>"><?= ikon('doc', 14) ?> Salin kode</button>
      </div>
      <form method="post" class="form-setujui mb16">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="aktivasi">
        <input type="hidden" name="id" value="<?= $sid ?>">
        <span class="xs mut">Aktivasi:</span>
        <select name="mode" aria-label="Status aktivasi">
          <option value="permanen" <?= $ak['mode'] === 'permanen' ? 'selected' : '' ?>>Aktif permanen</option>
          <option value="uji_coba" <?= $ak['mode'] === 'uji_coba' ? 'selected' : '' ?>>Masa uji coba</option>
        </select>
        <select name="hari" aria-label="Lama uji coba">
          <?php foreach (aktivasi_pilihan_hari() as $h): ?>
            <option value="<?= (int) $h ?>" <?= (int) $h === ($ak['hari'] ?: UJI_COBA_HARI_DEFAULT) ? 'selected' : '' ?>><?= (int) $h ?> hari</option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('save', 14) ?> Simpan status aktivasi</button>
      </form>

      <div class="stat-mini mb16">
        <div><b><?= $st['kelas'] ?></b><span>Kelas</span></div>
        <div><b><?= $st['guru'] ?></b><span>Guru aktif</span></div>
        <div><b><?= $st['siswa'] ?></b><span>Siswa aktif</span></div>
        <div><b><?= $st['mapel'] ?></b><span>Mata pelajaran</span></div>
        <div><b><?= $st['akun'] ?></b><span>Akun</span></div>
        <div><b><?= $st['jurnal'] ?></b><span>Jurnal</span></div>
        <div><b><?= $st['nilai'] ?></b><span>Baris nilai</span></div>
        <div><b><?= $st['absensi'] ?></b><span>Pertemuan</span></div>
      </div>

      <details class="editbox" <?= $tandaKelola ? 'open' : '' ?>>
        <summary><?= ikon('users', 14) ?> Akun pengelola (<?= count($akun) ?>)</summary>
        <?php if ($akun): ?>
          <div class="chip-list mb8">
            <?php foreach ($akun as $a): ?>
              <span class="chip<?= (int) $a['aktif'] === 1 ? '' : ' chip-mati' ?>">
                <?= e((string) $a['nama']) ?> &middot; <?= e((string) $a['username']) ?> &middot; <?= e(role_label((string) $a['role'])) ?>
              </span>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="hint">Belum ada akun untuk sekolah/madrasah ini.</p>
        <?php endif; ?>
      </details>

      <?php if (!$aktif): ?>
        <details class="editbox danger-zone">
          <summary><?= ikon('alert', 14) ?> Zona berbahaya — hapus permanen</summary>
          <p class="sm mut">Menghapus sekolah/madrasah ini akan menghapus permanen:
            <?= $d['kelas'] ?> kelas, <?= $d['mapel'] ?> mata pelajaran, <?= $d['guru'] ?> data guru,
            <?= $d['siswa'] ?> siswa, <?= $d['nilai'] ?> baris nilai, <?= $d['absensi'] ?> pertemuan daftar hadir,
            <?= $d['jurnal'] ?> jurnal, <?= $d['kasus'] ?> catatan kasus, <?= $d['notulen'] ?> notulen rapat, dan <?= $d['akun'] ?> akun.</p>
          <?php // SANDI KONFIRMASI (permintaan pemilik): hapus TIDAK langsung berjalan —
                // sandi diperiksa di server oleh sekolah_hapus_sandi_sah(). ?>
          <form method="post" class="flex mt8" data-confirm="Hapus permanen <?= e((string) $r['nama']) ?> beserta seluruh datanya?">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id" value="<?= $sid ?>">
            <input type="text" name="konfirmasi" placeholder="ketik HAPUS" style="max-width:180px">
            <input type="password" name="sandi" placeholder="sandi konfirmasi" autocomplete="off"
                   style="max-width:190px" required>
            <button class="btn btn-danger" type="submit"><?= ikon('trash', 15) ?> Hapus permanen</button>
          </form>
          <p class="hint" style="margin:6px 0 0">Hapus permanen meminta <b>sandi konfirmasi</b> lebih dulu,
            agar lembaga tidak terhapus karena salah klik.</p>
        </details>
      <?php endif; ?>
    </div>
    <?php
}

page_top('Kelola Sekolah/Madrasah', 'Super Admin &middot; ' . count($daftar) . ' sekolah/madrasah terdaftar &middot; '
    . jumlah_pendaftaran_menunggu() . ' menunggu persetujuan');
?>
<div class="grid g4">
  <div class="card stat card-tight">
    <div class="stat-ic g"><?= ikon('building', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= count($diaktifkan) ?></div><div class="stat-lbl">Sekolah/madrasah aktif</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic o"><?= ikon('clock', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= count($belumDisetujui) ?></div><div class="stat-lbl">Belum disetujui</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic p"><?= ikon('lock', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= count($nonaktif) ?></div><div class="stat-lbl">Nonaktif</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('users', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) val('SELECT COUNT(*) FROM users', [], 0) ?></div><div class="stat-lbl">Akun terdaftar</div></div>
  </div>
</div>

<?php if (count($belumDisetujui) > 0): ?>
  <div class="alert alert-warn">
    <?= ikon('star', 16) ?>
    <span>Ada <b><?= count($belumDisetujui) ?></b> pendaftaran yang menunggu persetujuan.
      <a href="pendaftaran.php">Tinjau pendaftaran</a> — login sekolah tersebut masih ditolak sampai disetujui.</span>
  </div>
<?php endif; ?>

<?php // Kartu "Daftar Sekolah/Madrasah" yang dulu berada di sebelah kanan kartu ini sudah
      // DIHAPUS atas permintaan pemilik. Karena itu grid hanya dua kolom saat ada form "Ubah"
      // (kalau tidak, kolom kanan kosong dan kartu Tambah jadi sempit).
      // Catatan: $cari masih dihormati lewat ?cari= (filter daftar di bawah) bila nanti kotak
      // pencariannya dipasang lagi. ?>
<div class="grid<?= $editSekolah ? ' g12' : '' ?>">
  <div class="card">
    <div class="card-head">
      <div><h2>Tambah Sekolah/Madrasah</h2><p><?= count(mapel_awal()) ?> mata pelajaran awal, tahun ajaran, dan semester dibuat otomatis.</p></div>
    </div>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="tambah">
      <div>
        <label class="f" for="nama">Nama Sekolah/Madrasah *</label>
        <input type="text" id="nama" name="nama" required placeholder="mis. MA. Sirojuth Tholibiin Mootilango">
      </div>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="jenis">Jenis Lembaga</label>
          <select id="jenis" name="jenis">
            <option value="umum">Sekolah Umum (SD/SMP/SMA/SMK)</option>
            <option value="madrasah">Madrasah (MI/MTs/MA/MAK)</option>
          </select>
          <div class="hint">Menentukan istilah pada aplikasi &amp; dokumen cetak sekolah tersebut.</div>
        </div>
        <?php // v49: PILIHAN JENJANG — pilihan mengikuti jenis lembaga (Sekolah Umum → SD/SMP/SMA/SMK,
              // Madrasah → MI/MTs/MA/MAK). initJenisJenjang() menyusun ulang pilihan saat jenis
              // diganti; server tetap memeriksa keabsahannya. ?>
        <div>
          <label class="f" for="jenjang">Jenjang</label>
          <select id="jenjang" name="jenjang"
                  data-jenjang="<?= e(json_encode(JENJANG_PILIHAN, JSON_UNESCAPED_UNICODE)) ?>"
                  data-label="<?= e(json_encode(JENJANG_LABEL, JSON_UNESCAPED_UNICODE)) ?>">
            <option value="">— belum dipilih —</option>
            <?php foreach (jenjang_pilihan('umum') as $jj): ?>
              <option value="<?= e($jj) ?>"><?= e(JENJANG_LABEL[$jj] ?? $jj) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Acuan tingkat kelas &amp; konsep guru kelas (SD/MI).</div>
        </div>
      </div>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="npsn">NPSN</label>
          <input type="text" id="npsn" name="npsn">
        </div>
        <div>
          <label class="f" for="nsm">NSM <span class="mut xs">(madrasah)</span></label>
          <input type="text" id="nsm" name="nsm">
        </div>
      </div>
      <div>
        <label class="f" for="telepon">Telepon / HP</label>
        <input type="text" id="telepon" name="telepon">
      </div>
      <div>
        <label class="f" for="alamat">Alamat (Nama Jalan, Dusun)</label>
        <textarea id="alamat" name="alamat" rows="2"></textarea>
      </div>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="desa">Desa/Kelurahan</label>
          <input type="text" id="desa" name="desa">
        </div>
        <div>
          <label class="f" for="kecamatan">Kecamatan</label>
          <input type="text" id="kecamatan" name="kecamatan">
        </div>
      </div>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="kabupaten">Kabupaten</label>
          <input type="text" id="kabupaten" name="kabupaten">
        </div>
        <div>
          <label class="f" for="provinsi">Provinsi</label>
          <input type="text" id="provinsi" name="provinsi">
        </div>
      </div>
      <div class="grid g3" style="gap:16px">
        <div>
          <label class="f" for="email">Email</label>
          <input type="text" id="email" name="email">
        </div>
        <div>
          <label class="f" for="website">Situs/laman</label>
          <input type="text" id="website" name="website">
        </div>
        <div>
          <label class="f" for="nip_kepala_sekolah">NIP Kepala</label>
          <input type="text" id="nip_kepala_sekolah" name="nip_kepala_sekolah">
        </div>
      </div>
      <div>
        <label class="f" for="kepala_sekolah">Nama Kepala Sekolah/Madrasah</label>
        <input type="text" id="kepala_sekolah" name="kepala_sekolah">
      </div>
      <?php // v39: identitas PENGAWAS BINA (nama & NIP) — letaknya di bawah Nama/NIP Kepala Sekolah. ?>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="pengawas_nama">Nama Pengawas Bina</label>
          <input type="text" id="pengawas_nama" name="pengawas_nama" placeholder="nama &amp; gelar">
        </div>
        <div>
          <label class="f" for="pengawas_nip">NIP Pengawas Bina</label>
          <input type="text" id="pengawas_nip" name="pengawas_nip" placeholder="boleh dikosongkan">
        </div>
      </div>
      <div class="divider"></div>
      <div class="section-title"><?= ikon('key', 15) ?> Akun Pengelola Pertama</div>
      <div class="grid g3" style="gap:16px">
        <div>
          <label class="f" for="admin_nama">Nama *</label>
          <input type="text" id="admin_nama" name="admin_nama" required>
        </div>
        <div>
          <label class="f" for="admin_username">Nama Pengguna *</label>
          <input type="text" id="admin_username" name="admin_username" required>
        </div>
        <div>
          <label class="f" for="admin_role">Peran</label>
          <select id="admin_role" name="admin_role">
            <option value="kepala">Kepala Sekolah/Madrasah</option>
            <option value="operator">Admin Operator</option>
          </select>
        </div>
      </div>
      <div>
        <label class="f" for="admin_password">Kata Sandi * (min. 6 karakter)</label>
        <input type="password" id="admin_password" name="admin_password" minlength="<?= (int) SANDI_MIN ?>" required>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= ikon('plus', 16) ?> Tambah Sekolah/Madrasah</button>
      </div>
    </form>
  </div>

  <div class="grid" style="gap:20px;align-content:start">
    <?php if ($editSekolah): ?>
      <div class="card">
        <div class="card-head">
          <div><h2>Ubah: <?= tetap_kata(nama_sekolah_tampil($editSekolah)) ?></h2><p>Profil, ambang predikat, dan aturan penilaian.</p></div>
          <a class="btn btn-ghost btn-sm" href="sekolah.php">Tutup</a>
        </div>
        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="simpan">
          <input type="hidden" name="id" value="<?= (int) $editSekolah['id'] ?>">
          <div>
            <label class="f" for="enama">Nama *</label>
            <input type="text" id="enama" name="nama" value="<?= e((string) $editSekolah['nama']) ?>" required>
          </div>
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="ejenis">Jenis Lembaga</label>
              <select id="ejenis" name="jenis">
                <option value="umum" <?= $editSekolah['jenis'] === 'umum' ? 'selected' : '' ?>>Sekolah Umum (SD/SMP/SMA/SMK)</option>
                <option value="madrasah" <?= $editSekolah['jenis'] === 'madrasah' ? 'selected' : '' ?>>Madrasah (MI/MTs/MA/MAK)</option>
              </select>
            </div>
            <?php // v49: PILIHAN JENJANG pada panel ubah. Pilihannya mengikuti jenis sekolah yang
                  // sedang diubah; nilai di luar pilihan itu dikosongkan server. ?>
            <div>
              <label class="f" for="ejenjang">Jenjang</label>
              <select id="ejenjang" name="jenjang"
                      data-jenjang="<?= e(json_encode(JENJANG_PILIHAN, JSON_UNESCAPED_UNICODE)) ?>"
                      data-label="<?= e(json_encode(JENJANG_LABEL, JSON_UNESCAPED_UNICODE)) ?>">
                <option value="">— belum dipilih —</option>
                <?php foreach (jenjang_pilihan((string) $editSekolah['jenis']) as $jj): ?>
                  <option value="<?= e($jj) ?>" <?= ((string) ($editSekolah['jenjang'] ?? '')) === $jj ? 'selected' : '' ?>><?= e(JENJANG_LABEL[$jj] ?? $jj) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="enpsn">NPSN</label>
              <input type="text" id="enpsn" name="npsn" value="<?= e((string) $editSekolah['npsn']) ?>">
            </div>
            <div>
              <label class="f" for="ensm">NSM <span class="mut xs">(madrasah)</span></label>
              <input type="text" id="ensm" name="nsm" value="<?= e((string) ($editSekolah['nsm'] ?? '')) ?>">
            </div>
          </div>
          <div>
            <label class="f" for="ealamat">Alamat (Nama Jalan, Dusun)</label>
            <textarea id="ealamat" name="alamat" rows="2"><?= e((string) $editSekolah['alamat']) ?></textarea>
          </div>
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="edesa">Desa/Kelurahan</label>
              <input type="text" id="edesa" name="desa" value="<?= e((string) ($editSekolah['desa'] ?? '')) ?>">
            </div>
            <div>
              <label class="f" for="ekecamatan">Kecamatan</label>
              <input type="text" id="ekecamatan" name="kecamatan" value="<?= e((string) ($editSekolah['kecamatan'] ?? '')) ?>">
              <div class="hint">Dipakai sebagai tempat pada baris "tempat, tanggal" tanda tangan dokumen cetak.</div>
            </div>
          </div>
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="ekabupaten">Kabupaten</label>
              <input type="text" id="ekabupaten" name="kabupaten" value="<?= e((string) ($editSekolah['kabupaten'] ?? '')) ?>">
            </div>
            <div>
              <label class="f" for="eprovinsi">Provinsi</label>
              <input type="text" id="eprovinsi" name="provinsi" value="<?= e((string) ($editSekolah['provinsi'] ?? '')) ?>">
            </div>
          </div>
          <div class="grid g3" style="gap:16px">
            <div>
              <label class="f" for="etelepon">Telepon / HP</label>
              <input type="text" id="etelepon" name="telepon" value="<?= e((string) $editSekolah['telepon']) ?>">
            </div>
            <div>
              <label class="f" for="eemail">Email</label>
              <input type="text" id="eemail" name="email" value="<?= e((string) $editSekolah['email']) ?>">
            </div>
            <div>
              <label class="f" for="ewebsite">Situs/laman</label>
              <input type="text" id="ewebsite" name="website" value="<?= e((string) $editSekolah['website']) ?>">
            </div>
          </div>
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="ekepala">Nama Kepala</label>
              <input type="text" id="ekepala" name="kepala_sekolah" value="<?= e((string) $editSekolah['kepala_sekolah']) ?>">
            </div>
            <div>
              <label class="f" for="enip">NIP Kepala</label>
              <input type="text" id="enip" name="nip_kepala_sekolah" value="<?= e((string) $editSekolah['nip_kepala_sekolah']) ?>">
            </div>
            <?php // v39: identitas PENGAWAS BINA (nama & NIP) — di bawah Nama/NIP Kepala. ?>
            <div>
              <label class="f" for="epengawas">Nama Pengawas Bina</label>
              <input type="text" id="epengawas" name="pengawas_nama" value="<?= e((string) ($editSekolah['pengawas_nama'] ?? '')) ?>">
            </div>
            <div>
              <label class="f" for="epengawasnip">NIP Pengawas Bina</label>
              <input type="text" id="epengawasnip" name="pengawas_nip" value="<?= e((string) ($editSekolah['pengawas_nip'] ?? '')) ?>">
            </div>
          </div>
          <div class="divider"></div>
          <div class="section-title"><?= ikon('chart', 15) ?> Aturan Penilaian</div>
          <div class="grid g3" style="gap:16px">
            <div>
              <label class="f" for="ejf">Kolom Formatif</label>
              <input type="number" id="ejf" name="jumlah_formatif" min="1" max="12" value="<?= (int) $editSekolah['jumlah_formatif'] ?>">
            </div>
            <div>
              <label class="f" for="ejs">Kolom Sumatif Lingkup Materi</label>
              <input type="number" id="ejs" name="jumlah_sumatif" min="1" max="12" value="<?= (int) $editSekolah['jumlah_sumatif'] ?>">
            </div>
            <div>
              <label class="f" for="ebobot">Bobot ASAS (%)</label>
              <input type="number" id="ebobot" name="bobot_asas" min="0" max="100" step="1" value="<?= e(r0((float) $editSekolah['bobot_asas'])) ?>">
            </div>
          </div>
          <div class="grid g3" style="gap:16px">
            <div>
              <label class="f" for="ea">Predikat A mulai</label>
              <input type="number" id="ea" name="batas_a" min="0" max="100" value="<?= e(r0((float) $editSekolah['batas_a'])) ?>">
            </div>
            <div>
              <label class="f" for="eb">Predikat B mulai</label>
              <input type="number" id="eb" name="batas_b" min="0" max="100" value="<?= e(r0((float) $editSekolah['batas_b'])) ?>">
            </div>
            <div>
              <label class="f" for="ec">Predikat C mulai</label>
              <input type="number" id="ec" name="batas_c" min="0" max="100" value="<?= e(r0((float) $editSekolah['batas_c'])) ?>">
            </div>
          </div>
          <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan</button>
          </div>
        </form>

        <div class="divider"></div>
        <div class="section-title mb8"><?= ikon('key', 15) ?> Tambah Akun Pengelola</div>
        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="akun">
          <input type="hidden" name="id" value="<?= (int) $editSekolah['id'] ?>">
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="akun_nama">Nama *</label>
              <input type="text" id="akun_nama" name="nama" required>
            </div>
            <div>
              <label class="f" for="akun_user">Nama Pengguna *</label>
              <input type="text" id="akun_user" name="username" required>
            </div>
          </div>
          <div class="grid g2" style="gap:16px">
            <div>
              <label class="f" for="akun_role">Peran</label>
              <select id="akun_role" name="role">
                <option value="kepala">Kepala Sekolah/Madrasah</option>
                <option value="operator">Admin Operator</option>
              </select>
            </div>
            <div>
              <label class="f" for="akun_pass">Kata Sandi * (min. 6)</label>
              <input type="password" id="akun_pass" name="password" minlength="<?= (int) SANDI_MIN ?>" required>
            </div>
          </div>
          <button class="btn btn-ghost" type="submit"><?= ikon('plus', 16) ?> Tambah akun</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="grid" style="gap:20px">
  <div class="section-title"><?= ikon('building', 15) ?> Sekolah/Madrasah Aktif (<?= count($diaktifkan) ?>)</div>
  <?php if (!$diaktifkan): ?>
    <div class="card"><p class="mut sm" style="margin:0">Belum ada sekolah/madrasah aktif.</p></div>
  <?php endif; ?>
  <?php foreach ($diaktifkan as $r) { kartu_sekolah($r, $kodeSekarang, true); } ?>

  <?php if ($nonaktif): ?>
    <div class="section-title mt24"><?= ikon('lock', 15) ?> Sekolah/Madrasah Nonaktif (<?= count($nonaktif) ?>)</div>
    <div class="alert alert-warn" style="margin-bottom:0">
      <?= ikon('alert', 16) ?>
      <span>Sekolah/madrasah nonaktif tidak dapat dipakai: akunnya tidak bisa masuk, hilang dari daftar lintas sekolah,
        dan tidak dapat dikelola sampai diaktifkan kembali. Datanya tetap utuh.</span>
    </div>
    <?php foreach ($nonaktif as $r) { kartu_sekolah($r, $kodeSekarang, false); } ?>
  <?php endif; ?>

  <?php if ($belumDisetujui): ?>
    <div class="section-title mt24"><?= ikon('clock', 15) ?> Belum Disetujui (<?= count($belumDisetujui) ?>)</div>
    <div class="card">
      <p class="sm mut" style="margin:0 0 12px">Pendaftaran berikut menunggu keputusan Super Admin pada halaman
        <a href="pendaftaran.php">Pendaftaran</a>.</p>
      <div class="chip-list">
        <?php foreach ($belumDisetujui as $r): ?>
          <span class="chip"><?= tetap_kata(nama_sekolah_tampil($r)) ?> &middot; <?= e(verifikasi_label((string) $r['verifikasi'])) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php
page_bottom();
