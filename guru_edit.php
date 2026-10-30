<?php
/**
 * Tambah / ubah data guru, termasuk kelas yang diajar dan mata pelajaran yang dipegang.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_master();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$g = $id > 0 ? guru_by_id($id) : null;
if ($id > 0 && !$g) {
    flash('Data guru tidak ditemukan.', 'err');
    redirect('guru.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $nama = trim((string) ($_POST['nama'] ?? ''));
    $nip = nip_bersih($_POST['nip'] ?? '');
    $nuptk = nip_bersih($_POST['nuptk'] ?? '');
    $jk = in_array((string) ($_POST['jenis_kelamin'] ?? ''), ['L', 'P'], true) ? (string) $_POST['jenis_kelamin'] : '';
    $jabatan = trim((string) ($_POST['jabatan'] ?? ''));
    $telepon = trim((string) ($_POST['telepon'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    $mapelPilih = array_values(array_unique(array_map('intval', (array) ($_POST['mapel'] ?? []))));
    $kelasPilih = array_values(array_unique(array_map('intval', (array) ($_POST['kelas'] ?? []))));
    // Tugas tambahan guru (permintaan pemilik: pilihan centang pada form Data Guru).
    $tugasPilih = array_values(array_unique(array_map('intval', (array) ($_POST['tugas'] ?? []))));
    // v49: PENANDA GURU KELAS (jenjang SD/MI). Hanya dibaca bila formulirnya benar-benar dikirim
    // (ada medan `guru_kelas_ada`) supaya formulir lama / skrip lain tidak menghapus penandanya.
    $guruKelasAda = array_key_exists('guru_kelas_ada', $_POST);
    $guruKelas = ($guruKelasAda && !empty($_POST['guru_kelas']) && is_guru_kelas_jenjang()) ? 1 : 0;

    $galat = [];
    if ($nama === '') {
        $galat[] = 'Nama guru wajib diisi.';
    }
    if ($guruKelas === 1 && count($kelasPilih) !== 1) {
        $galat[] = 'Guru kelas harus memilih TEPAT SATU kelas pada bagian "Kelas yang Diajar" '
            . '(guru kelas memegang banyak mata pelajaran pada satu kelas).';
    }
    if ($nip !== '' && one('SELECT id FROM guru WHERE nip = ? AND id <> ? AND sekolah_id = ?', [$nip, $id, sekolah_id()])) {
        $galat[] = 'NIP ' . $nip . ' sudah dipakai guru lain.';
    }
    if ($email !== '' && strpos($email, '@') === false) {
        $galat[] = 'Alamat email tidak valid.';
    }
    if ($galat) {
        foreach ($galat as $x) {
            flash($x, 'err');
        }
        redirect($id > 0 ? 'guru_edit.php?id=' . $id : 'guru_edit.php');
    }

    $now = date('Y-m-d H:i:s');
    db()->exec('BEGIN IMMEDIATE');
    try {
        // v49: penanda GURU KELAS hanya ditulis bila formulirnya memuat medan `guru_kelas_ada`,
        // sehingga penyimpanan dari formulir lama tidak pernah menghapus penandanya.
        $setKelas = $guruKelasAda ? ', guru_kelas = ' . $guruKelas : '';
        if ($g) {
            q('UPDATE guru SET nama=?, nip=?, nuptk=?, jenis_kelamin=?, jabatan=?, telepon=?, email=?, aktif=?'
                . $setKelas . ' WHERE id=? AND sekolah_id=?',
                [$nama, $nip, $nuptk, $jk, $jabatan, $telepon, $email, $aktif, $id, sekolah_id()]);
        } else {
            q('INSERT INTO guru (sekolah_id, nama, nip, nuptk, jenis_kelamin, jabatan, telepon, email, guru_kelas, aktif, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [sekolah_id(), $nama, $nip, $nuptk, $jk, $jabatan, $telepon, $email, $guruKelas, $aktif, $now]);
            $id = (int) db()->lastInsertId();
        }
        q('DELETE FROM guru_mapel WHERE guru_id = ?', [$id]);
        foreach ($mapelPilih as $mid) {
            if (mapel_by_id($mid)) {
                q('INSERT OR IGNORE INTO guru_mapel (guru_id, mapel_id) VALUES (?,?)', [$id, $mid]);
            }
        }
        q('DELETE FROM guru_kelas WHERE guru_id = ?', [$id]);
        foreach ($kelasPilih as $kid) {
            if (kelas_by_id($kid)) {
                q('INSERT OR IGNORE INTO guru_kelas (guru_id, kelas_id) VALUES (?,?)', [$id, $kid]);
            }
        }
        // GURU KELAS otomatis menjadi WALI KELAS kelas yang diampunya (permintaan pemilik). Hanya
        // dijalankan bila guru ditandai guru kelas dan tepat satu kelas dipilih — kelas lain tidak
        // pernah tersentuh.
        if ($guruKelas === 1 && count($kelasPilih) === 1) {
            $kelasIdGk = (int) $kelasPilih[0];
            if (kelas_by_id($kelasIdGk)) {
                $waliLama = (int) val('SELECT wali_id FROM kelas WHERE id = ? AND sekolah_id = ?',
                    [$kelasIdGk, sekolah_id()], 0);
                q('UPDATE kelas SET wali_id = ? WHERE id = ? AND sekolah_id = ?', [$id, $kelasIdGk, sekolah_id()]);
                if ($waliLama > 0 && $waliLama !== $id) {
                    $namaKelasGk = (string) val('SELECT nama FROM kelas WHERE id = ?', [$kelasIdGk], '');
                    $guruLama = (string) val('SELECT nama FROM guru WHERE id = ?', [$waliLama], '');
                    flash('Kelas ' . $namaKelasGk . ' sebelumnya diwalikan ' . $guruLama
                        . ' — kini wali kelasnya ' . $nama . ' (guru kelas).', 'warn');
                } else {
                    // Pesan flash di-ESCAPE saat dicetak (inc/layout.php) → teks biasa saja.
                    flash('Guru kelas otomatis ditetapkan sebagai wali kelas '
                        . (string) val('SELECT nama FROM kelas WHERE id = ?', [$kelasIdGk], '') . '.');
                }
            }
        }
        q('DELETE FROM guru_tugas WHERE guru_id = ?', [$id]);
        foreach ($tugasPilih as $tid) {
            // Hanya menu Tugas Tambahan yang ADA dan DIAKTIFKAN yang boleh ditautkan (daftarnya
            // global & diisi Super Admin) — id karangan/menu nonaktif diabaikan.
            $tugas = tugas_tambahan_by_id($tid);
            if ($tugas && (int) $tugas['aktif'] === 1) {
                q('INSERT OR IGNORE INTO guru_tugas (guru_id, tugas_id) VALUES (?,?)', [$id, $tid]);
            }
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }

    flash('Data guru ' . $nama . ' disimpan.');

    // Sekaligus buatkan akun login bila diminta dan belum ada.
    if (!empty($_POST['buat_akun'])) {
        if (one('SELECT id FROM users WHERE guru_id = ?', [$id])) {
            flash('Guru ini sudah memiliki akun login.', 'warn');
        } else {
            $guruBaru = guru_by_id($id) ?? ['nama' => $nama, 'nip' => $nip];
            $username = guru_username_unik($guruBaru);
            $sandi = guru_sandi_awal($guruBaru);
            q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, guru_id, aktif, dibuat) VALUES (?,?,?,?,?,?,1,?)',
                [sekolah_id(), $username, password_hash($sandi, PASSWORD_DEFAULT), $nama, 'guru', $id, date('Y-m-d H:i:s')]);
            flash('Akun login dibuat — nama pengguna: ' . $username . ', kata sandi awal: ' . $sandi . '.');
        }
    }
    redirect('guru_edit.php?id=' . $id);
}

$mapelSekarang = $g ? guru_mapel_ids((int) $g['id']) : [];
$kelasSekarang = $g ? guru_kelas_ids((int) $g['id']) : [];
$tugasSekarang = $g ? guru_tugas_ids((int) $g['id']) : [];
$akun = $g ? one('SELECT username FROM users WHERE guru_id = ? AND sekolah_id = ?', [(int) $g['id'], sekolah_id()]) : null;
// v49: keadaan GURU KELAS (hanya berarti pada jenjang SD/MI) + wali kelas yang sedang tercatat.
$guruKelasKini = $g ? (int) ($g['guru_kelas'] ?? 0) === 1 : false;
$pakaiGuruKelas = is_guru_kelas_jenjang();

page_top($g ? 'Ubah Data Guru' : 'Tambah Data Guru');
?>
<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) ($g['id'] ?? 0) ?>">
  <div class="grid g21">
    <div class="card">
      <div class="card-head">
        <div><h2>Identitas Guru</h2><p>NIP/NUPTK boleh dikosongkan bila belum ada.</p></div>
      </div>
      <div class="form-grid">
        <div>
          <label class="f" for="nama">Nama Lengkap *</label>
          <input type="text" id="nama" name="nama" value="<?= e((string) ($g['nama'] ?? '')) ?>" required>
        </div>
        <div class="grid g2" style="gap:16px">
          <div>
            <label class="f" for="nip">NIP</label>
            <input type="text" id="nip" name="nip" value="<?= e((string) ($g['nip'] ?? '')) ?>" placeholder="boleh dikosongkan">
          </div>
          <div>
            <label class="f" for="nuptk">NUPTK</label>
            <input type="text" id="nuptk" name="nuptk" value="<?= e((string) ($g['nuptk'] ?? '')) ?>" placeholder="boleh dikosongkan">
          </div>
        </div>
        <div class="grid g3" style="gap:16px">
          <div>
            <label class="f" for="jenis_kelamin">Jenis Kelamin</label>
            <select id="jenis_kelamin" name="jenis_kelamin">
              <option value="">—</option>
              <option value="L" <?= ($g['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
              <option value="P" <?= ($g['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
            </select>
          </div>
          <div>
            <label class="f" for="jabatan">Jabatan</label>
            <input type="text" id="jabatan" name="jabatan" value="<?= e((string) ($g['jabatan'] ?? '')) ?>" placeholder="mis. Guru Madya">
          </div>
          <div>
            <label class="f" for="telepon">No. HP</label>
            <input type="text" id="telepon" name="telepon" value="<?= e((string) ($g['telepon'] ?? '')) ?>">
          </div>
        </div>
        <div>
          <label class="f" for="email">Email</label>
          <input type="text" id="email" name="email" value="<?= e((string) ($g['email'] ?? '')) ?>">
        </div>
        <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600;margin:0">
          <input type="checkbox" name="aktif" value="1" <?= (int) ($g['aktif'] ?? 1) === 1 ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--accent)">
          Guru aktif (tampil pada pilihan kelas/mapel)
        </label>
      </div>
    </div>

    <div class="grid" style="gap:20px;align-content:start">
      <div class="card">
        <div class="card-head">
          <div><h2>Mata Pelajaran yang Dipegang</h2><p>Pilih satu atau lebih.</p></div>
          <?php if (boleh_master()): ?><a class="btn btn-ghost btn-sm" href="mapel.php"><?= ikon('folder', 14) ?> Kelola</a><?php endif; ?>
        </div>
        <?php $semuaMapel = mapel_list(true); ?>
        <?php if (!$semuaMapel): ?>
          <p class="mut sm">Belum ada mata pelajaran. Tambahkan lewat menu Mata Pelajaran.</p>
        <?php else: ?>
          <div class="grid g2" style="gap:8px">
            <?php foreach ($semuaMapel as $m): ?>
              <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:500;margin:0">
                <input type="checkbox" name="mapel[]" value="<?= (int) $m['id'] ?>" <?= in_array((int) $m['id'], $mapelSekarang, true) ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--accent)">
                <?= e((string) $m['nama']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($pakaiGuruKelas): ?>
      <?php // v49: PENANDA GURU KELAS — hanya tampil pada jenjang SD/MI (permintaan pemilik: pada
            // jenjang itu guru memegang banyak mata pelajaran dalam satu kelas). Guru kelas otomatis
            // menjadi wali kelas kelas yang dipilih; mata pelajaran TETAP dicentang manual di atas. ?>
      <div class="card" id="cardGuruKelas">
        <div class="card-head">
          <div><h2>Guru Kelas</h2><p>Khusus jenjang <?= e(jenjang_sekolah()) ?> — guru memegang banyak mata pelajaran pada satu kelas.</p></div>
          <span class="pill <?= $guruKelasKini ? 'pill-ok' : 'pill-gray' ?>" id="pilGuruKelas"><?= $guruKelasKini ? 'Guru Kelas' : 'Guru Mata Pelajaran' ?></span>
        </div>
        <input type="hidden" name="guru_kelas_ada" value="1">
        <label class="f" style="display:flex;gap:9px;align-items:flex-start;font-weight:600;margin:0">
          <input type="checkbox" id="guruKelas" name="guru_kelas" value="1" <?= $guruKelasKini ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--accent);margin-top:2px">
          <span>Tandai sebagai <b>Guru Kelas</b><br>
            <span class="hint">Guru kelas mengajar banyak mata pelajaran pada <b>satu kelas</b> dan
              <b>otomatis menjadi wali kelas</b> kelas itu. Mata pelajaran tetap dapat dicentang
              pada bagian "Mata Pelajaran yang Dipegang".</span></span>
        </label>
        <div class="hint" id="guruKelasCatatan" style="margin-top:10px"><?= $guruKelasKini
          ? 'Centang kelas yang menjadi kelas perwaliannya (tepat satu kelas) pada bagian "Kelas yang Diajar".'
          : 'Jenjang ' . e(jenjang_sekolah()) . ' memakai konsep guru kelas: satu kelas, banyak mata pelajaran.' ?></div>
      </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-head">
          <div><h2>Kelas yang Diajar</h2><p><?= $pakaiGuruKelas
            ? 'Pilih SATU kelas bila guru ditandai sebagai Guru Kelas.'
            : 'Pilih kelas yang dibina/diajar.' ?></p></div>
          <?php if (boleh_master()): ?><a class="btn btn-ghost btn-sm" href="kelas.php"><?= ikon('school', 14) ?> Kelola</a><?php endif; ?>
        </div>
        <?php $semuaKelas = kelas_list(true); ?>
        <?php if (!$semuaKelas): ?>
          <p class="mut sm">Belum ada kelas. Tambahkan lewat menu Kelas.</p>
        <?php else: ?>
          <div class="grid g2" style="gap:8px">
            <?php foreach ($semuaKelas as $k): ?>
              <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:500;margin:0">
                <input type="checkbox" name="kelas[]" value="<?= (int) $k['id'] ?>" <?= in_array((int) $k['id'], $kelasSekarang, true) ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--accent)">
                <?= e((string) $k['nama']) ?> <span class="mut xs">(<?= (int) $k['jml_siswa'] ?> siswa)</span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-head">
          <div><h2>Tugas Tambahan</h2><p>Pilih satu atau lebih tugas tambahan yang dipegang guru ini.
            Daftarnya sama dengan menu <b>Tugas Tambahan</b> yang diisi Super Admin.</p></div>
          <?php if (is_super()): ?>
            <a class="btn btn-primary btn-sm" href="tugas_menu.php"><?= ikon('edit', 14) ?> Kelola</a>
          <?php elseif (boleh_pengaturan()): ?>
            <a class="btn btn-ghost btn-sm" href="tugas_tambahan.php"><?= ikon('star', 14) ?> Lihat Daftar</a>
          <?php endif; ?>
        </div>
        <?php $semuaTugas = tugas_tambahan_list(true); ?>
        <?php if (!$semuaTugas): ?>
          <p class="mut sm">Belum ada tugas tambahan yang <b>diaktifkan</b>. Super Admin mengisi dan
            mengaktifkannya lewat menu <b>Kelola Tugas Tambahan</b> (daftar ini tidak lagi diisi per
            sekolah).</p>
        <?php else: ?>
          <div class="grid g2" style="gap:8px">
            <?php foreach ($semuaTugas as $t): ?>
              <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:500;margin:0">
                <input type="checkbox" name="tugas[]" value="<?= (int) $t['id'] ?>" <?= in_array((int) $t['id'], $tugasSekarang, true) ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:var(--accent)">
                <?= e((string) $t['nama']) ?>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-head"><div><h2>Akun Login</h2><p>Untuk masuk aplikasi sebagai guru.</p></div></div>
        <?php if ($akun): ?>
          <p class="sm" style="margin:0">Nama pengguna: <b><?= e((string) $akun['username']) ?></b></p>
          <p class="hint">Kata sandi diatur/diubah melalui menu Pengguna &amp; Akun (Super Admin).</p>
        <?php else: ?>
          <label class="f" style="display:flex;gap:9px;align-items:flex-start;font-weight:600">
            <input type="checkbox" name="buat_akun" value="1" style="width:16px;height:16px;accent-color:var(--accent);margin-top:2px">
            <span>Sekaligus buatkan akun login<br><span class="hint">Nama pengguna diambil dari
              <b>nama awal guru</b> (mis. "Ahmad Fauzi" &rarr; <b>ahmad</b>; bila sudah dipakai
              ditambah angka: <b>ahmad2</b>). Kata sandi awal <b>guru123</b>.</span></span>
          </label>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="flex mt24">
    <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Data Guru</button>
    <a class="btn btn-ghost" href="guru.php">Batal</a>
  </div>
</form>
<?php
page_bottom();
