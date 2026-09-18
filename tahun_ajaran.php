<?php
/**
 * Pengaturan tahun ajaran & semester (Data Master).
 * Tahun ajaran/semester AKTIF menentukan periode pengisian baru pada daftar hadir,
 * nilai, dan jurnal. Data lama tetap tersimpan pada periodenya masing-masing.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_staf(); // Super Admin, Kepala, Admin Operator (guru tidak perlu)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    /** Benar bila sebuah periode sudah dipakai data (tidak boleh dihapus). */
    $dipakai = static function (int $taId, int $semId = 0): int {
        // CAST wajib: PDO mengikat nilai sebagai TEXT, sehingga "0 = 0" menjadi perbandingan
        // TEXT vs INTEGER yang selalu salah di SQLite (pernah membuat penolakan hapus gagal).
        $n = (int) val('SELECT COUNT(*) FROM nilai WHERE sekolah_id = ? AND ta_id = ? AND (CAST(? AS INTEGER) = 0 OR semester_id = ?)',
            [sekolah_id(), $taId, $semId, $semId], 0);
        $n += (int) val('SELECT COUNT(*) FROM absensi WHERE sekolah_id = ? AND ta_id = ? AND (CAST(? AS INTEGER) = 0 OR semester_id = ?)',
            [sekolah_id(), $taId, $semId, $semId], 0);
        $n += (int) val('SELECT COUNT(*) FROM jurnal WHERE sekolah_id = ? AND ta_id = ? AND (CAST(? AS INTEGER) = 0 OR semester_id = ?)',
            [sekolah_id(), $taId, $semId, $semId], 0);
        return $n;
    };

    if ($aksi === 'tambah') {
        $nama = trim((string) ($_POST['nama'] ?? ''));
        $mulai = tgl_ok($_POST['tanggal_mulai'] ?? null);
        $selesai = tgl_ok($_POST['tanggal_selesai'] ?? null);
        $ket = trim((string) ($_POST['keterangan'] ?? ''));
        if ($nama === '') {
            flash('Nama tahun ajaran wajib diisi (mis. 2025/2026).', 'err');
        } elseif (one('SELECT id FROM tahun_ajaran WHERE nama = ? AND sekolah_id = ?', [$nama, sekolah_id()])) {
            flash('Tahun ajaran "' . $nama . '" sudah ada.', 'err');
        } else {
            q('INSERT INTO tahun_ajaran (sekolah_id, nama, tanggal_mulai, tanggal_selesai, keterangan, aktif) VALUES (?,?,?,?,?,1)',
                [sekolah_id(), $nama, $mulai, $selesai, $ket]);
            $taBaru = (int) db()->lastInsertId();
            q('INSERT INTO semester (ta_id, nama, urutan, aktif) VALUES (?,?,?,1)', [$taBaru, 'Ganjil', 1]);
            $semGanjil = (int) db()->lastInsertId();
            q('INSERT INTO semester (ta_id, nama, urutan, aktif) VALUES (?,?,?,1)', [$taBaru, 'Genap', 2]);
            flash('Tahun ajaran ' . $nama . ' ditambahkan beserta semester Ganjil dan Genap.');
            if (!empty($_POST['jadikan_aktif'])) {
                set_setting('ta_aktif_id', (string) $taBaru);
                set_setting('semester_aktif_id', (string) $semGanjil);
                flash('Tahun ajaran ' . $nama . ' (semester Ganjil) kini menjadi periode aktif.', 'warn');
            }
        }
        redirect('tahun_ajaran.php');
    }

    if ($aksi === 'ubah') {
        $ta = ta_by_id($id);
        if (!$ta) {
            flash('Tahun ajaran tidak ditemukan.', 'err');
            redirect('tahun_ajaran.php');
        }
        $nama = trim((string) ($_POST['nama'] ?? ''));
        if ($nama === '') {
            flash('Nama tahun ajaran wajib diisi.', 'err');
        } elseif (one('SELECT id FROM tahun_ajaran WHERE nama = ? AND id <> ? AND sekolah_id = ?', [$nama, $id, sekolah_id()])) {
            flash('Nama tahun ajaran sudah dipakai.', 'err');
        } else {
            q('UPDATE tahun_ajaran SET nama = ?, tanggal_mulai = ?, tanggal_selesai = ?, keterangan = ? WHERE id = ? AND sekolah_id = ?',
                [$nama, tgl_ok($_POST['tanggal_mulai'] ?? null), tgl_ok($_POST['tanggal_selesai'] ?? null),
                 trim((string) ($_POST['keterangan'] ?? '')), $id, sekolah_id()]);
            flash('Tahun ajaran diperbarui.');
        }
        redirect('tahun_ajaran.php');
    }

    if ($aksi === 'hapus') {
        $ta = ta_by_id($id);
        if (!$ta) {
            flash('Tahun ajaran tidak ditemukan.', 'err');
        } elseif ((int) val('SELECT COUNT(*) FROM tahun_ajaran WHERE sekolah_id = ?', [sekolah_id()], 0) <= 1) {
            flash('Minimal harus ada satu tahun ajaran.', 'err');
        } elseif ($dipakai($id) > 0) {
            flash('Tahun ajaran ' . $ta['nama'] . ' sudah dipakai data nilai/daftar hadir/jurnal sehingga tidak boleh dihapus.', 'err');
        } else {
            q('DELETE FROM tahun_ajaran WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
            if (setting_int('ta_aktif_id', 0) === $id) {
                $sisa = one('SELECT id FROM tahun_ajaran WHERE sekolah_id = ? ORDER BY nama DESC LIMIT 1', [sekolah_id()]);
                if ($sisa) {
                    set_setting('ta_aktif_id', (string) $sisa['id']);
                    $sem = one('SELECT id FROM semester WHERE ta_id = ? ORDER BY urutan LIMIT 1', [(int) $sisa['id']]);
                    set_setting('semester_aktif_id', (string) ($sem['id'] ?? 0));
                }
            }
            flash('Tahun ajaran ' . $ta['nama'] . ' beserta semester-nya dihapus.', 'warn');
        }
        redirect('tahun_ajaran.php');
    }

    if ($aksi === 'tambah_sem') {
        $taId = (int) ($_POST['ta_id'] ?? 0);
        $nama = trim((string) ($_POST['nama'] ?? ''));
        if (!ta_by_id($taId) || $nama === '') {
            flash('Pilih tahun ajaran dan isi nama semester.', 'err');
        } elseif (one('SELECT id FROM semester WHERE ta_id = ? AND nama = ?', [$taId, $nama])) {
            flash('Semester "' . $nama . '" sudah ada pada tahun ajaran itu.', 'err');
        } else {
            $urutan = (int) val('SELECT COALESCE(MAX(urutan),0) + 1 FROM semester WHERE ta_id = ?', [$taId], 1);
            q('INSERT INTO semester (ta_id, nama, urutan, aktif) VALUES (?,?,?,1)', [$taId, $nama, $urutan]);
            flash('Semester ' . $nama . ' ditambahkan.');
        }
        redirect('tahun_ajaran.php');
    }

    if ($aksi === 'hapus_sem') {
        $sem = semester_by_id($id);
        if (!$sem) {
            flash('Semester tidak ditemukan.', 'err');
        } elseif ((int) val('SELECT COUNT(*) FROM semester WHERE ta_id = ?', [(int) $sem['ta_id']], 0) <= 1) {
            flash('Setiap tahun ajaran minimal memiliki satu semester.', 'err');
        } elseif ($dipakai((int) $sem['ta_id'], $id) > 0) {
            flash('Semester ' . $sem['nama'] . ' sudah dipakai data nilai/daftar hadir sehingga tidak boleh dihapus.', 'err');
        } else {
            q('DELETE FROM semester WHERE id = ? AND ta_id IN (SELECT id FROM tahun_ajaran WHERE sekolah_id = ?)', [$id, sekolah_id()]);
            if (setting_int('semester_aktif_id', 0) === $id) {
                $sisa = one('SELECT id FROM semester WHERE ta_id = ? ORDER BY urutan LIMIT 1', [(int) $sem['ta_id']]);
                set_setting('semester_aktif_id', (string) ($sisa['id'] ?? 0));
            }
            flash('Semester ' . $sem['nama'] . ' dihapus.', 'warn');
        }
        redirect('tahun_ajaran.php');
    }

    if ($aksi === 'aktif') {
        if ($id > 0) { // jadikan semester aktif (tahun ajaran mengikuti)
            $sem = semester_by_id($id);
            if (!$sem) {
                flash('Semester tidak ditemukan.', 'err');
            } else {
                set_setting('ta_aktif_id', (string) (int) $sem['ta_id']);
                set_setting('semester_aktif_id', (string) $id);
                $ta = ta_by_id((int) $sem['ta_id']);
                flash('Periode aktif: ' . ta_nama($ta) . ' — semester ' . $sem['nama'] . '.');
            }
        } else { // jadikan tahun ajaran aktif (semester pertama)
            $ta = ta_by_id((int) ($_POST['ta_id'] ?? 0));
            if (!$ta) {
                flash('Tahun ajaran tidak ditemukan.', 'err');
            } else {
                $sem = one('SELECT id FROM semester WHERE ta_id = ? ORDER BY urutan LIMIT 1', [(int) $ta['id']]);
                set_setting('ta_aktif_id', (string) (int) $ta['id']);
                set_setting('semester_aktif_id', (string) ($sem['id'] ?? 0));
                flash('Periode aktif: ' . $ta['nama'] . '.');
            }
        }
        redirect('tahun_ajaran.php');
    }
    redirect('tahun_ajaran.php');
}

$taAktif = ta_aktif();
$semAktif = semester_aktif();
$daftarTa = ta_list();

page_top('Tahun Ajaran & Semester');
?>
<div class="grid g3">
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('calendar', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= e(ta_nama($taAktif)) ?></div><div class="stat-lbl">Tahun ajaran aktif</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic s"><?= ikon('clock', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= e(sem_nama($semAktif)) ?></div><div class="stat-lbl">Semester aktif</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic p"><?= ikon('folder', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= count($daftarTa) ?></div><div class="stat-lbl">Tahun ajaran terdaftar</div></div>
  </div>
</div>

<div class="grid g12">
  <div class="card">
    <div class="card-head">
      <div><h2>Tambah Tahun Ajaran</h2><p>Semester Ganjil &amp; Genap dibuat otomatis.</p></div>
    </div>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="tambah">
      <div>
        <label class="f" for="nama">Nama Tahun Ajaran</label>
        <input type="text" id="nama" name="nama" placeholder="2025/2026" required>
      </div>
      <div>
        <label class="f" for="tanggal_mulai">Tanggal mulai</label>
        <input type="date" id="tanggal_mulai" name="tanggal_mulai">
      </div>
      <div>
        <label class="f" for="tanggal_selesai">Tanggal selesai</label>
        <input type="date" id="tanggal_selesai" name="tanggal_selesai">
      </div>
      <div>
        <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600">
          <input type="checkbox" name="jadikan_aktif" value="1" style="width:16px;height:16px;accent-color:var(--accent)">
          Langsung jadikan periode aktif
        </label>
      </div>
      <button class="btn btn-primary" type="submit"><?= ikon('plus', 16) ?> Tambah</button>
    </form>
  </div>

  <div class="grid" style="gap:20px;align-content:start">
    <?php foreach ($daftarTa as $ta): $semList = semester_list((int) $ta['id']); ?>
      <div class="card">
        <div class="card-head">
          <div>
            <h2><?= e((string) $ta['nama']) ?>
              <?php if ((int) $ta['id'] === (int) ($taAktif['id'] ?? 0)): ?><span class="pill pill-ok">Aktif</span><?php endif; ?>
            </h2>
            <p><?= e($ta['tanggal_mulai'] ? tanggal_id((string) $ta['tanggal_mulai']) : '-') ?> s/d <?= e($ta['tanggal_selesai'] ? tanggal_id((string) $ta['tanggal_selesai']) : '-') ?></p>
          </div>
          <div class="row-actions">
            <?php if ((int) $ta['id'] !== (int) ($taAktif['id'] ?? 0)): ?>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="aktif">
                <input type="hidden" name="ta_id" value="<?= (int) $ta['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('check', 14) ?> Jadikan aktif</button>
              </form>
            <?php endif; ?>
            <form method="post" data-confirm="Hapus tahun ajaran <?= e((string) $ta['nama']) ?> beserta semester-nya?">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id" value="<?= (int) $ta['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 15) ?></button>
            </form>
          </div>
        </div>

        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Semester</th><th class="ctr">Nilai</th><th class="ctr">Daftar hadir</th><th class="ctr">Jurnal</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($semList as $sm): ?>
              <tr>
                <td>
                  <b><?= e((string) $sm['nama']) ?></b>
                  <?php if ((int) $sm['id'] === (int) ($semAktif['id'] ?? 0)): ?><span class="pill pill-ok">Aktif</span><?php endif; ?>
                </td>
                <td class="ctr"><?= (int) val('SELECT COUNT(*) FROM nilai WHERE sekolah_id = ? AND ta_id = ? AND semester_id = ?', [sekolah_id(), (int) $ta['id'], (int) $sm['id']], 0) ?></td>
                <td class="ctr"><?= (int) val('SELECT COUNT(*) FROM absensi WHERE sekolah_id = ? AND ta_id = ? AND semester_id = ?', [sekolah_id(), (int) $ta['id'], (int) $sm['id']], 0) ?></td>
                <td class="ctr"><?= (int) val('SELECT COUNT(*) FROM jurnal WHERE sekolah_id = ? AND semester_id = ?', [sekolah_id(), (int) $sm['id']], 0) ?></td>
                <td>
                  <div class="row-actions">
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="aktif">
                      <input type="hidden" name="id" value="<?= (int) $sm['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('check', 14) ?> Aktifkan</button>
                    </form>
                    <form method="post" data-confirm="Hapus semester <?= e((string) $sm['nama']) ?>?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus_sem">
                      <input type="hidden" name="id" value="<?= (int) $sm['id'] ?>">
                      <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 15) ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <form method="post" class="flex mt16">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="tambah_sem">
          <input type="hidden" name="ta_id" value="<?= (int) $ta['id'] ?>">
          <input type="text" name="nama" placeholder="Nama semester baru (mis. Semester Antara)" style="max-width:280px">
          <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('plus', 14) ?> Tambah semester</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php
page_bottom();
