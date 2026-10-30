<?php
/**
 * PENUGASAN MENU TUGAS TAMBAHAN (Admin Operator & Kepala Sekolah/Madrasah) — `tugas_pengguna.php`.
 *
 * Permintaan pemilik: "Menu tugas tambahan pada akun guru yang tampil hanya yang ditugaskan saja.
 * Muncul semua hanya di akun super admin, kepala madrasah dan operator."
 *
 * Jadi pembagiannya:
 *   - **Super Admin** (`tugas_menu.php`): mengaktifkan menu, mengubah namanya, mengatur urutannya;
 *     seluruh menu aktif tampil & dapat dibuka di akunnya.
 *   - **Admin Operator / Kepala Sekolah-Madrasah** (halaman ini): memilih PENGGUNA mana di
 *     sekolahnya yang melihat (dan boleh membuka) menu Tugas Tambahan; akun mereka sendiri juga
 *     menampilkan seluruh menu yang diaktifkan.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_tugas_pengguna();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'simpan') {
        // Hanya pengguna SEKOLAH INI yang boleh disentuh (anti-IDOR), dan perannya tidak boleh super.
        $uid = (int) ($_POST['user_id'] ?? 0);
        $target = $uid > 0
            ? one('SELECT * FROM users WHERE id = ? AND sekolah_id = ? AND role <> "super"', [$uid, sekolah_id()])
            : null;
        if (!$target) {
            flash('Pengguna itu tidak ada pada sekolah/madrasah ini.', 'err');
            redirect('tugas_pengguna.php');
        }
        $pilih = is_array($_POST['tugas'] ?? null) ? (array) $_POST['tugas'] : [];
        $jumlah = tugas_user_simpan($uid, $pilih);

        // ---------------------------------------------------------------- kelas perwalian
        // Menu "Wali Kelas" hanya berisi data KELAS PERWALIAN, jadi penugasan menu itu belum cukup:
        // kelasnya harus ditetapkan pada kolom `kelas.wali_id` (permintaan pemilik: kelasnya diatur
        // operator). Penetapan itu dilakukan dari halaman ini sekaligus supaya penugasan menu Wali
        // Kelas benar-benar bisa dipakai — dulu penugasannya tersimpan tetapi isi menunya hanya
        // berbunyi "Anda belum ditetapkan sebagai wali kelas".
        $gid = (int) ($target['guru_id'] ?? 0);
        $waliId = 0;
        $wm = tugas_menu_by_kode(WALI_KODE_MENU);
        if ($wm) {
            $waliId = (int) $wm['id'];
        }
        $khususWali = $waliId > 0 && in_array($waliId, array_map('intval', $pilih), true);
        $pesanKelas = '';
        if (array_key_exists('wali_kelas_atur', $_POST)) {
            if ($gid <= 0) {
                if ($khususWali) {
                    flash('Akun itu bukan akun guru (belum tertaut data guru), jadi tidak dapat '
                        . 'ditetapkan sebagai wali kelas. Tautkan dulu akunnya pada Data Guru.', 'err');
                }
            } else {
                $hasil = kelas_perwalian_simpan($gid, is_array($_POST['kelas'] ?? null) ? (array) $_POST['kelas'] : []);
                $pesanKelas = $hasil['pesan'];
                if ($hasil['pesan'] !== '') {
                    flash($hasil['pesan'], $hasil['ada'] ? 'ok' : 'warn');
                }
            }
        } elseif ($khususWali && $gid > 0) {
            // Menu Wali Kelas ditugaskan tetapi tidak ada kelas perwalian → beri tahu apa adanya,
            // supaya tidak terlihat "penugasan tidak berfungsi".
            flash('Menu Wali Kelas sudah ditugaskan, tetapi belum ada kelas perwalian yang dipilih. '
                . 'Selama belum ada kelasnya, isi menu itu masih menampilkan pesan '
                . '"belum ditetapkan sebagai wali kelas".', 'warn');
        }

        flash('Penugasan untuk ' . $target['username'] . ' disimpan: ' . $jumlah . ' Menu Tugas Tambahan. '
            . ($jumlah === 0
                ? 'Pengguna itu tidak akan dapat membuka isi menu Tugas Tambahan.'
                : 'Menu tersebut dapat dibukanya.'), $jumlah > 0 ? 'ok' : 'warn');
        redirect('tugas_pengguna.php?user=' . $uid . '#formPengguna');
    }

    if ($aksi === 'bersihkan') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $target = $uid > 0
            ? one('SELECT * FROM users WHERE id = ? AND sekolah_id = ? AND role <> "super"', [$uid, sekolah_id()])
            : null;
        if ($target) {
            q('DELETE FROM user_tugas WHERE user_id = ?', [$uid]);
            flash('Seluruh penugasan Menu Tugas Tambahan untuk ' . $target['username'] . ' dibersihkan.', 'warn');
        } else {
            flash('Pengguna itu tidak ada pada sekolah/madrasah ini.', 'err');
        }
        redirect('tugas_pengguna.php');
    }

    redirect('tugas_pengguna.php');
}

$menuAktif = tugas_menu_list(true);
$menuSemua = tugas_menu_list();
$pengguna = tugas_pengguna_sekolah();

$pilihUser = (int) ($_GET['user'] ?? 0);
$userTerpilih = null;
foreach ($pengguna as $p) {
    if ((int) $p['id'] === $pilihUser) {
        $userTerpilih = $p;
        break;
    }
}
if (!$userTerpilih) {
    $pilihUser = 0;
}
$tugasUser = $pilihUser > 0 ? tugas_user_ids($pilihUser) : [];

// Kelas perwalian (menu Wali Kelas). Menu itu hanya berisi data KELAS PERWALIAN, jadi halaman ini
// sekaligus menjadi tempat menetapkan kelasnya — lihat kelas_perwalian_simpan().
$guruId = $userTerpilih ? (int) ($userTerpilih['guru_id'] ?? 0) : 0;
$daftarKelas = $guruId > 0 ? kelas_list(true) : [];
$kelasPerwalian = $guruId > 0
    ? array_map(static fn(array $r): int => (int) $r['id'], guru_kelas_perwalian($guruId))
    : [];
$waliLainAda = false;
foreach ($daftarKelas as $k) {
    if ((int) ($k['wali_id'] ?? 0) > 0 && (int) $k['wali_id'] !== $guruId) {
        $waliLainAda = true;
        break;
    }
}
$menuWali = tugas_menu_by_kode(WALI_KODE_MENU);
$namaWali = $menuWali ? (string) $menuWali['nama'] : 'Wali Kelas';

// Ringkasan penugasan di sekolah ini — termasuk guru yang menjadi WALI KELAS tanpa penugasan menu
// (wali kelas otomatis melihat menunya lewat `kelas.wali_id`), supaya keduanya terlihat di satu daftar.
$penugasan = all('SELECT u.id AS user_id, u.nama, u.username, u.role
                  FROM users u
                  WHERE u.sekolah_id = ? AND u.role <> "super"
                    AND (EXISTS (SELECT 1 FROM user_tugas ut WHERE ut.user_id = u.id)
                         OR EXISTS (SELECT 1 FROM kelas k WHERE k.wali_id = u.guru_id
                                    AND k.wali_id IS NOT NULL AND k.sekolah_id = u.sekolah_id AND k.aktif = 1))
                  ORDER BY u.nama', [sekolah_id()]);
$namaTugasByUser = [];
foreach (all('SELECT ut.user_id, tm.nama FROM user_tugas ut
              JOIN tugas_menu tm ON tm.id = ut.tugas_id
              JOIN users u ON u.id = ut.user_id
              WHERE u.sekolah_id = ? ORDER BY tm.urutan, tm.id', [sekolah_id()]) as $r) {
    $namaTugasByUser[(int) $r['user_id']][] = (string) $r['nama'];
}

// Kelas perwalian tiap pengguna pada daftar penugasan (diambil dari kolom `kelas.wali_id`).
$waliByUser = [];
foreach (all('SELECT u.id AS user_id, k.nama FROM users u
              JOIN kelas k ON k.wali_id = u.guru_id
              WHERE u.sekolah_id = ? AND u.guru_id IS NOT NULL AND k.sekolah_id = ? AND k.aktif = 1
              ORDER BY k.urutan, k.nama', [sekolah_id(), sekolah_id()]) as $r) {
    $waliByUser[(int) $r['user_id']][] = (string) $r['nama'];
}

// Permintaan pemilik v1.22.0: menu ini bernama **"Pengaturan Tugas Tambahan"** dan letaknya di
// bagian **Pengaturan** pada sidebar (dulu "Pengguna Tugas Tambahan" di bagian Tugas Tambahan).
page_top('Pengaturan Tugas Tambahan', 'Tentukan siapa yang dapat membuka isi menu Tugas Tambahan');
?>
<div class="alert alert-ok" style="background:rgba(201,162,39,.08);border-color:rgba(201,162,39,.5)">
  <?= ikon('star', 16) ?>
  <span>Menu Tugas Tambahan yang diaktifkan Super Admin tampil <b>seluruhnya</b> pada akun
    <b>Super Admin, Kepala Sekolah/Madrasah, dan Admin Operator</b>. Pada akun <b>Guru</b> (dan peran
    lain) sidebar hanya menampilkan menu yang <b>ditugaskan di halaman ini</b>.
    <?= count($menuAktif) > 0
        ? 'Saat ini <b>' . count($menuAktif) . ' menu</b> sudah diaktifkan Super Admin.'
        : 'Belum ada menu yang diaktifkan Super Admin.' ?></span>
</div>

<?php if (!$menuAktif): ?>
  <div class="card">
    <div class="empty"><?= ikon('clock', 34) ?>
      <h3>Belum ada menu yang diaktifkan</h3>
      <p>Super Admin belum mengaktifkan satu pun Menu Tugas Tambahan, jadi belum ada isi menu yang
        dapat ditugaskan. Silakan tunggu atau hubungi Admin aplikasi.</p>
    </div>
  </div>
<?php else: ?>
  <div class="card" id="formPengguna">
    <div class="card-head">
      <div>
        <h2><?= ikon('users', 16) ?> Tugaskan Menu kepada Pengguna</h2>
        <p>Pilih pengguna di sekolah/madrasah ini, centang menu Tugas Tambahan yang boleh dilihat &
          dibukanya, lalu simpan. Akun yang tidak ditugaskan tidak melihat menu itu pada sidebar.</p>
      </div>
      <span class="chip"><?= count($pengguna) ?> pengguna</span>
    </div>

    <?php if (!$pengguna): ?>
      <p class="hint" style="margin:0">Belum ada pengguna lain di sekolah/madrasah ini.</p>
    <?php else: ?>
      <form method="get" class="filterbar mb16">
        <div>
          <label class="f" for="user">Pilih pengguna</label>
          <select id="user" name="user">
            <option value="">— pilih pengguna —</option>
            <?php foreach ($pengguna as $p): ?>
              <option value="<?= (int) $p['id'] ?>" <?= $pilihUser === (int) $p['id'] ? 'selected' : '' ?>>
                <?= e((string) $p['nama']) ?> &middot; <?= e((string) $p['username']) ?> &middot; <?= e(role_label((string) $p['role'])) ?>
                <?= (int) $p['aktif'] === 1 ? '' : ' (nonaktif)' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-primary" type="submit"><?= ikon('edit', 15) ?> Buka Penugasan</button>
      </form>

      <?php if ($userTerpilih): ?>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="simpan">
          <input type="hidden" name="user_id" value="<?= (int) $userTerpilih['id'] ?>">
          <div class="alert alert-ok" style="margin-bottom:14px">
            <?= ikon('user', 16) ?>
            <span>Menugaskan untuk <b><?= e((string) $userTerpilih['nama']) ?></b>
              (<?= e((string) $userTerpilih['username']) ?> &middot; <?= e(role_label((string) $userTerpilih['role'])) ?>)
              pada <?= tetap_kata(nama_sekolah_tampil()) ?>.</span>
          </div>
          <div class="pengawas-pilih">
            <?php foreach ($menuAktif as $t): ?>
              <label class="pengawas-item">
                <input type="checkbox" name="tugas[]" value="<?= (int) $t['id'] ?>"
                       <?= in_array((int) $t['id'], $tugasUser, true) ? 'checked' : '' ?>>
                <span><b><?= e((string) $t['nama']) ?></b>
                  <span class="xs mut">isi: <?= e(TUGAS_ISI_PESAN) ?></span></span>
              </label>
            <?php endforeach; ?>
          </div>

          <?php // KELAS PERWALIAN (menu Wali Kelas) — permintaan pemilik: menu Wali Kelas hanya
                // berisi data kelas perwalian, jadi kelasnya WAJIB ditetapkan bersamaan dengan
                // penugasan menunya. Nilainya disimpan pada kolom `kelas.wali_id` (satu sumber
                // kebenaran, sama dengan menu Data Kelas → kolom Wali Kelas). ?>
          <?php if ($guruId > 0): ?>
            <div class="divider"></div>
            <div class="card-head" style="margin-bottom:8px">
              <div>
                <h3><?= ikon('school', 15) ?> Kelas Perwalian<?= $namaWali !== '' ? ' — menu ' . e($namaWali) : '' ?></h3>
                <p>Centang kelas yang menjadi perwalian <b><?= e((string) $userTerpilih['nama']) ?></b>.
                  Guru yang ditetapkan sebagai wali kelas otomatis melihat menu <b>Wali Kelas</b> beserta
                  data kelasnya — tanpa ini isi menunya masih berbunyi
                  "belum ditetapkan sebagai wali kelas".
                  Menghilangkan centang berarti <b>melepas</b> perwalian kelas itu.</p>
              </div>
              <span class="chip"><?= count($kelasPerwalian) ?> kelas dipilih</span>
            </div>
            <input type="hidden" name="wali_kelas_atur" value="1">
            <div class="pengawas-pilih">
              <?php foreach ($daftarKelas as $k): $kid = (int) $k['id'];
                    $punya = in_array($kid, $kelasPerwalian, true);
                    $waliLain = (int) ($k['wali_id'] ?? 0) > 0 && (int) $k['wali_id'] !== $guruId; ?>
                <label class="pengawas-item">
                  <input type="checkbox" name="kelas[]" value="<?= $kid ?>" <?= $punya ? 'checked' : '' ?>>
                  <span><b>Kelas <?= e((string) $k['nama']) ?></b>
                    <span class="xs mut"><?= (int) $k['jml_siswa'] ?> siswa
                      &middot; <?= (int) ($k['wali_id'] ?? 0) === $guruId
                          ? 'wali kelas saat ini'
                          : ($waliLain ? 'wali: ' . e((string) ($k['wali_nama'] ?? '-')) : 'belum ada wali kelas') ?></span></span>
                </label>
              <?php endforeach; ?>
              <?php if (!$daftarKelas): ?>
                <p class="hint" style="margin:0">Belum ada kelas aktif pada sekolah/madrasah ini.
                  Tambahkan kelasnya lebih dulu pada menu <b>Data Kelas</b>.</p>
              <?php endif; ?>
            </div>
            <?php if ($waliLainAda): ?>
              <p class="hint" style="margin:10px 0 0">
                <?= ikon('alert', 14) ?> Kelas yang sudah punya wali kelas lain ditandai dengan nama walinya.
                Bila dicentang, perwaliannya <b>dipindahkan</b> kepada
                <?= e((string) $userTerpilih['nama']) ?>.
              </p>
            <?php endif; ?>
          <?php else: ?>
            <p class="hint" style="margin-top:14px">
              <?= ikon('alert', 14) ?> Akun ini <b>bukan akun guru</b> (belum tertaut data guru), jadi tidak
              dapat ditetapkan sebagai wali kelas. Menu Tugas Tambahan lain tetap dapat ditugaskan.
            </p>
          <?php endif; ?>

          <div class="flex mt16">
            <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Penugasan</button>
            <a class="btn btn-ghost" href="tugas_pengguna.php">Batal</a>
          </div>
        </form>
      <?php else: ?>
        <p class="hint" style="margin:0">Pilih pengguna di atas untuk mengatur menu Tugas Tambahan yang
          boleh dibukanya.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <div><h2>Daftar Penugasan</h2><p>Hanya pengguna pada daftar ini yang melihat menu Tugas
        Tambahan pada sidebar-nya (akun Kepala/Operator melihat semuanya). Guru yang ditetapkan
        sebagai <b>wali kelas</b> ikut terdaftar di sini.</p></div>
      <span class="chip"><?= count($penugasan) ?> pengguna ditugaskan</span>
    </div>
    <?php if (!$penugasan): ?>
      <p class="hint" style="margin:0">Belum ada pengguna yang ditugaskan — menu Tugas Tambahan belum
        tampil pada akun guru mana pun.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl" id="tblPenugasan">
          <thead><tr><th class="ctr" style="width:34px">No</th><th>Pengguna</th><th>Peran</th>
            <th>Menu yang Dapat Dibuka</th><th class="ctr" style="width:200px">Tindakan</th></tr></thead>
          <tbody>
          <?php $n = 0; foreach ($penugasan as $b): $n++; $bid = (int) $b['user_id']; ?>
            <tr>
              <td class="ctr"><?= $n ?></td>
              <td><b><?= e((string) $b['nama']) ?></b><span class="tbl-sub"><?= e((string) $b['username']) ?></span>
                <?php if (!empty($waliByUser[$bid])): ?>
                  <span class="tbl-sub">Wali kelas: <?= e(implode(', ', $waliByUser[$bid])) ?></span>
                <?php endif; ?>
              </td>
              <td><?= e(role_label((string) $b['role'])) ?></td>
              <td class="sm"><?= e(implode(', ', $namaTugasByUser[$bid] ?? [])) ?></td>
              <td>
                <div class="row-actions">
                  <a class="btn btn-ghost btn-sm" href="tugas_pengguna.php?user=<?= $bid ?>#formPengguna"><?= ikon('edit', 14) ?> Ubah</a>
                  <form method="post" data-confirm="Bersihkan seluruh penugasan menu untuk <?= e((string) $b['username']) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="bersihkan">
                    <input type="hidden" name="user_id" value="<?= $bid ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Bersihkan</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <?php if (count($menuSemua) > count($menuAktif)): ?>
      <div class="divider"></div>
      <p class="hint" style="margin:0">
        <b><?= count($menuSemua) - count($menuAktif) ?> menu</b> belum diaktifkan Super Admin sehingga
        belum muncul di sidebar dan belum dapat ditugaskan:
        <?php $nonaktif = [];
        foreach ($menuSemua as $t) {
            if ((int) $t['aktif'] !== 1) {
                $nonaktif[] = (string) $t['nama'];
            }
        } ?>
        <?= e(implode(', ', $nonaktif)) ?>.
      </p>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php
page_bottom();
