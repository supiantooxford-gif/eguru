<?php
/**
 * Data Guru — nama guru, kelas yang diajar, dan mata pelajaran yang dipegang.
 * Halaman ini juga menampilkan nama pengguna (akun login) guru, sehingga akun GURU DILARANG
 * membukanya (permintaan pemilik) — lihat require_data_guru().
 * Pengubahan hanya Super Admin & Admin Operator; cetak tersedia untuk keduanya.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_data_guru(); // super admin tanpa konteks dialihkan ke Kelola Sekolah; guru 403
$bolehUbah = boleh_master();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$bolehUbah) {
        tolak403('Hanya Super Admin dan Admin Operator yang dapat mengubah data guru.');
    }
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $g = guru_by_id($id);
    if (!$g) {
        flash('Data guru tidak ditemukan.', 'err');
        redirect('guru.php');
    }

    if ($aksi === 'buat_akun') {
        if (one('SELECT id FROM users WHERE guru_id = ?', [$id])) {
            flash('Guru ' . $g['nama'] . ' sudah memiliki akun.', 'warn');
        } else {
            $username = guru_username_unik($g);
            $sandi = guru_sandi_awal($g);
            q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, guru_id, aktif, dibuat) VALUES (?,?,?,?,?,?,1,?)',
                [sekolah_id(), $username, password_hash($sandi, PASSWORD_DEFAULT), $g['nama'], 'guru', $id, date('Y-m-d H:i:s')]);
            flash('Akun login dibuat untuk ' . $g['nama'] . ' — nama pengguna: ' . $username . ', kata sandi awal: ' . $sandi . '. Minta guru segera menggantinya.');
        }
    } elseif ($aksi === 'toggle') {
        q('UPDATE guru SET aktif = ? WHERE id = ? AND sekolah_id = ?', [(int) $g['aktif'] === 1 ? 0 : 1, $id, sekolah_id()]);
        flash('Status guru ' . $g['nama'] . ' diperbarui.', 'warn');
    } elseif ($aksi === 'hapus') {
        $jmlJurnal = (int) val('SELECT COUNT(*) FROM jurnal WHERE guru_id = ? AND sekolah_id = ?', [$id, sekolah_id()], 0);
        $jmlHadir = (int) val('SELECT COUNT(*) FROM absensi WHERE guru_id = ? AND sekolah_id = ?', [$id, sekolah_id()], 0);
        db()->exec('BEGIN IMMEDIATE');
        try {
            q('DELETE FROM guru WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        flash('Data guru ' . $g['nama'] . ' dihapus.'
            . ($jmlJurnal + $jmlHadir > 0 ? ' Catatan jurnal/daftar hadir yang pernah dibuatnya tetap tersimpan (nama guru pada catatan itu dikosongkan).' : '')
            . ' Akun loginnya (bila ada) tetap ada, tetapi tidak lagi tertaut ke data guru.');
    }
    redirect('guru.php');
}

$cari = trim((string) ($_GET['cari'] ?? ''));
$fStatus = (string) ($_GET['status'] ?? '');
$fMapel = (int) ($_GET['mapel'] ?? 0);
$fKelas = (int) ($_GET['kelas'] ?? 0);
$fAkun = (string) ($_GET['akun'] ?? '');

$w = ['g.sekolah_id = ?'];
$p = [sekolah_id()];
if ($cari !== '') {
    $w[] = '(g.nama LIKE ? OR g.nip LIKE ? OR g.nuptk LIKE ? OR g.jabatan LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($p, $like, $like, $like, $like);
}
if (in_array($fStatus, ['1', '0'], true)) {
    $w[] = 'g.aktif = ' . (int) $fStatus;
}
if ($fMapel > 0) {
    $w[] = 'EXISTS (SELECT 1 FROM guru_mapel gm WHERE gm.guru_id = g.id AND gm.mapel_id = ?)';
    $p[] = $fMapel;
}
if ($fKelas > 0) {
    $w[] = 'EXISTS (SELECT 1 FROM guru_kelas gk WHERE gk.guru_id = g.id AND gk.kelas_id = ?)';
    $p[] = $fKelas;
}
if ($fAkun === 'punya') {
    $w[] = 'EXISTS (SELECT 1 FROM users us WHERE us.guru_id = g.id)';
} elseif ($fAkun === 'belum') {
    $w[] = 'NOT EXISTS (SELECT 1 FROM users us WHERE us.guru_id = g.id)';
}
$where = ' WHERE ' . implode(' AND ', $w);

$rows = all('SELECT g.*,
    (SELECT GROUP_CONCAT(m.nama, "||") FROM guru_mapel gm JOIN mapel m ON m.id = gm.mapel_id WHERE gm.guru_id = g.id) AS mapel_nama,
    (SELECT GROUP_CONCAT(k.nama, "||") FROM guru_kelas gk JOIN kelas k ON k.id = gk.kelas_id WHERE gk.guru_id = g.id) AS kelas_nama,
    (SELECT username FROM users us WHERE us.guru_id = g.id LIMIT 1) AS username,
    (SELECT foto FROM users us WHERE us.guru_id = g.id AND us.foto IS NOT NULL AND us.foto <> "" LIMIT 1) AS foto,
    (SELECT COUNT(*) FROM jurnal j WHERE j.guru_id = g.id) AS jml_jurnal
    FROM guru g' . $where . ' ORDER BY g.nama', $p);

$total = (int) val('SELECT COUNT(*) FROM guru WHERE aktif = 1 AND sekolah_id = ?', [sekolah_id()], 0);
$totalAkun = (int) val('SELECT COUNT(*) FROM users WHERE role = "guru" AND sekolah_id = ?', [sekolah_id()], 0);

// Parameter filter yang diwariskan ke lembar cetak (hanya bila pengguna berhak mencetak).
$qCetak = array_filter([
    'cari' => $cari !== '' ? $cari : null,
    'mapel' => $fMapel > 0 ? $fMapel : null,
    'kelas' => $fKelas > 0 ? $fKelas : null,
    'akun' => $fAkun !== '' ? $fAkun : null,
    'status' => $fStatus !== '' ? $fStatus : null,
]);

page_top('Data Guru');
?>
<div class="grid g4">
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('users', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $total ?></div><div class="stat-lbl">Guru aktif</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic s"><?= ikon('key', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $totalAkun ?></div><div class="stat-lbl">Punya akun login</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic o"><?= ikon('book', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) val('SELECT COUNT(*) FROM mapel WHERE aktif = 1 AND sekolah_id = ?', [sekolah_id()], 0) ?></div><div class="stat-lbl">Mata pelajaran aktif</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic p"><?= ikon('school', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) val('SELECT COUNT(*) FROM kelas WHERE aktif = 1 AND sekolah_id = ?', [sekolah_id()], 0) ?></div><div class="stat-lbl">Kelas aktif</div></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Daftar Guru</h2><p><span id="tblCount"><?= count($rows) ?></span> data ditemukan</p></div>
    <div class="flex">
      <?php if ($bolehUbah): ?>
        <a class="btn btn-primary" href="guru_edit.php"><?= ikon('plus', 16) ?> Tambah Guru</a>
        <a class="btn btn-ghost" href="guru_import.php"><?= ikon('upload', 16) ?> Impor Excel/CSV</a>
        <?php // Cetak Data Guru: Super Admin (dalam konteks sekolah) & Admin Operator. Mengikuti filter aktif. ?>
        <a class="btn btn-ghost" href="cetak_guru.php?<?= e(http_build_query($qCetak)) ?>"><?= ikon('print', 16) ?> Cetak Data Guru</a>
      <?php endif; ?>
    </div>
  </div>

  <form class="filterbar mb16" method="get">
    <div>
      <label class="f" for="cari">Cari</label>
      <input type="search" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="Nama / NIP / jabatan...">
    </div>
    <div>
      <label class="f" for="mapel">Mata Pelajaran</label>
      <select id="mapel" name="mapel" data-auto>
        <option value="">Semua</option>
        <?php foreach (mapel_list(true) as $m): ?>
          <option value="<?= (int) $m['id'] ?>" <?= $fMapel === (int) $m['id'] ? 'selected' : '' ?>><?= e((string) $m['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="f" for="kelas">Kelas</label>
      <select id="kelas" name="kelas" data-auto>
        <option value="">Semua</option>
        <?php foreach (kelas_list(true) as $k): ?>
          <option value="<?= (int) $k['id'] ?>" <?= $fKelas === (int) $k['id'] ? 'selected' : '' ?>><?= e((string) $k['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="f" for="akun">Akun login</label>
      <select id="akun" name="akun" data-auto>
        <option value="">Semua</option>
        <option value="punya" <?= $fAkun === 'punya' ? 'selected' : '' ?>>Sudah punya akun</option>
        <option value="belum" <?= $fAkun === 'belum' ? 'selected' : '' ?>>Belum punya akun</option>
      </select>
    </div>
    <div>
      <label class="f" for="status">Status</label>
      <select id="status" name="status" data-auto>
        <option value="">Semua</option>
        <option value="1" <?= $fStatus === '1' ? 'selected' : '' ?>>Aktif</option>
        <option value="0" <?= $fStatus === '0' ? 'selected' : '' ?>>Nonaktif</option>
      </select>
    </div>
    <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
    <?php if ($cari !== '' || $fStatus !== '' || $fMapel > 0 || $fKelas > 0 || $fAkun !== ''): ?>
      <a class="btn btn-ghost" href="guru.php">Reset</a>
    <?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="empty">
      <?= ikon('users', 34) ?>
      <h3>Belum ada data guru</h3>
      <p>Tambahkan satu per satu atau impor sekaligus dari berkas Excel/CSV.</p>
      <?php if ($bolehUbah): ?>
        <div class="flex" style="justify-content:center">
          <a class="btn btn-primary" href="guru_edit.php"><?= ikon('plus', 16) ?> Tambah Guru</a>
          <a class="btn btn-ghost" href="guru_import.php"><?= ikon('upload', 16) ?> Impor Excel/CSV</a>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblGuru">
        <thead><tr><th>Nama</th><th>NIP / NUPTK</th><th>Mata Pelajaran</th><th>Kelas Diajar</th><th class="ctr">Jurnal</th><th class="ctr">Akun</th><th class="ctr">Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $g):
            $mapelArr = $g['mapel_nama'] ? explode('||', (string) $g['mapel_nama']) : [];
            $kelasArr = $g['kelas_nama'] ? explode('||', (string) $g['kelas_nama']) : [];
        ?>
          <tr>
            <td>
              <span class="mini-avatar">
                <?= avatar_html((string) $g['nama'], 34, 'avatar', $g['foto'] !== null ? (string) $g['foto'] : null) ?>
                <span>
                  <b><?= e((string) $g['nama']) ?></b>
                  <span class="tbl-sub"><?= e((string) ($g['jabatan'] ?: '-')) ?><?= $g['jenis_kelamin'] ? ' &middot; ' . e((string) $g['jenis_kelamin']) : '' ?></span>
                </span>
              </span>
            </td>
            <td class="nowrap"><?= e((string) ($g['nip'] ?: '-')) ?><?php if ($g['nuptk']): ?><span class="tbl-sub">NUPTK <?= e((string) $g['nuptk']) ?></span><?php endif; ?></td>
            <td>
              <?php if ($mapelArr): foreach ($mapelArr as $mp): ?><span class="tagmini"><?= e($mp) ?></span><?php endforeach; else: ?><span class="mut xs">belum ditentukan</span><?php endif; ?>
            </td>
            <td>
              <?php if ($kelasArr): foreach ($kelasArr as $kl): ?><span class="tagmini"><?= e($kl) ?></span><?php endforeach; else: ?><span class="mut xs">belum ditentukan</span><?php endif; ?>
            </td>
            <td class="ctr"><?= (int) $g['jml_jurnal'] ?></td>
            <td class="ctr">
              <?php if ($g['username']): ?>
                <span class="pill pill-ok"><?= e((string) $g['username']) ?></span>
              <?php elseif ($bolehUbah): ?>
                <form method="post" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="buat_akun">
                  <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                  <button class="btn btn-ghost btn-sm" type="submit" title="Buatkan akun login"><?= ikon('key', 14) ?> Buat akun</button>
                </form>
              <?php else: ?>
                <span class="mut xs">belum ada</span>
              <?php endif; ?>
            </td>
            <td class="ctr"><span class="pill <?= (int) $g['aktif'] === 1 ? 'pill-ok' : 'pill-gray' ?>"><?= (int) $g['aktif'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
            <td>
              <?php if ($bolehUbah): ?>
                <div class="row-actions">
                  <a class="btn btn-ghost btn-sm" href="guru_edit.php?id=<?= (int) $g['id'] ?>"><?= ikon('edit', 15) ?></a>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="toggle">
                    <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $g['aktif'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                  </form>
                  <form method="post" data-confirm="Hapus data guru <?= e((string) $g['nama']) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 15) ?></button>
                  </form>
                </div>
              <?php else: ?>
                <span class="mut xs">hanya lihat</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php
page_bottom();
