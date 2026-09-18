<?php
/**
 * Isi / ubah daftar hadir satu pertemuan (status H/S/I/A per siswa).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_transaksi();
$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];
if (!$ta || !$sem) {
    flash('Atur tahun ajaran & semester terlebih dahulu.', 'warn');
    redirect('absensi.php');
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$sesi = $id > 0 ? absensi_sesi($id) : null;
if ($id > 0 && !$sesi) {
    flash('Catatan pertemuan tidak ditemukan.', 'err');
    redirect('absensi.php');
}

// Kelas & mapel bisa datang dari GET (form pilih pertemuan) MAUPUN dari POST (saat menyimpan).
$kelasId = (int) ($sesi['kelas_id'] ?? $_POST['kelas'] ?? $_GET['kelas'] ?? 0);
$mapelId = (int) ($sesi['mapel_id'] ?? $_POST['mapel'] ?? $_GET['mapel'] ?? 0);
// Kelas/mapel belum dipilih → arahkan ke halaman daftar hadir (bukan 403), lalu bila
// kelasnya memang di luar yang diampu barulah ditolak 403 (anti-IDOR tetap berlaku).
if ($kelasId <= 0 || $mapelId <= 0) {
    flash('Pilih kelas dan mata pelajaran terlebih dahulu untuk mengisi daftar hadir.', 'warn');
    redirect('absensi.php' . ajaran_qs($ta, $sem));
}
if (!boleh_kelas($kelasId)) {
    tolak403('Kelas itu tidak Anda ampu.');
}
if (!boleh_mapel($mapelId)) {
    tolak403('Mata pelajaran itu tidak Anda ampu.');
}
if (!kelas_by_id($kelasId) || !mapel_by_id($mapelId)) {
    flash('Kelas atau mata pelajaran tidak valid.', 'err');
    redirect('absensi.php');
}

$tanggal = tgl_ok($sesi['tanggal'] ?? $_GET['tanggal'] ?? null) ?? date('Y-m-d');
$pertemuanDefault = (int) absensi_jumlah_pertemuan($kelasId, $mapelId, (int) $ta['id'], (int) $sem['id']) + 1;
$pertemuan = (int) ($sesi['pertemuan'] ?? $_POST['pertemuan'] ?? $_GET['pertemuan'] ?? $pertemuanDefault);
if ($pertemuan < 1) {
    $pertemuan = 1;
}
$materi = (string) ($sesi['materi'] ?? $_GET['materi'] ?? '');
$catatan = (string) ($sesi['catatan'] ?? '');

// Sudah ada pertemuan yang sama (kelas+mapel+periode+pertemuan)? Buka catatan itu.
// KHUSUS permintaan GET: pada POST pemeriksaan bentrok dilakukan di blok POST memakai nomor
// pertemuan yang benar-benar dikirim formulir (kalau memakai nilai default di sini, isian guru
// dapat dibelokkan ke catatan lama dan datanya tidak tersimpan — pernah terjadi).
if (!$sesi && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $ada = one('SELECT id FROM absensi WHERE kelas_id = ? AND mapel_id = ? AND ta_id = ? AND semester_id = ? AND pertemuan = ? AND sekolah_id = ?',
        [$kelasId, $mapelId, (int) $ta['id'], (int) $sem['id'], $pertemuan, sekolah_id()]);
    if ($ada) {
        flash('Pertemuan ke-' . $pertemuan . ' untuk mata pelajaran ini sudah tercatat — catatan itu dibuka untuk diubah.', 'warn');
        redirect('absensi_form.php?id=' . (int) $ada['id']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $tanggal = tgl_ok($_POST['tanggal'] ?? null) ?? $tanggal;
    $pertemuan = max(1, (int) ($_POST['pertemuan'] ?? $pertemuan));
    $materi = trim((string) ($_POST['materi'] ?? ''));
    $catatan = trim((string) ($_POST['catatan'] ?? ''));
    $status = is_array($_POST['st'] ?? null) ? $_POST['st'] : [];
    $guruId = is_guru() ? guru_id_saya() : ($sesi['guru_id'] ?? (guru_pengampu($kelasId, $mapelId)['id'] ?? null));

    $bentrok = one('SELECT id FROM absensi WHERE kelas_id = ? AND mapel_id = ? AND ta_id = ? AND semester_id = ? AND pertemuan = ? AND id <> ? AND sekolah_id = ?',
        [$kelasId, $mapelId, (int) $ta['id'], (int) $sem['id'], $pertemuan, (int) ($sesi['id'] ?? 0), sekolah_id()]);
    if ($bentrok) {
        flash('Pertemuan ke-' . $pertemuan . ' untuk kelas & mata pelajaran ini sudah ada. Pilih nomor pertemuan lain.', 'err');
        redirect('absensi_form.php' . ($sesi ? '?id=' . (int) $sesi['id'] : '?kelas=' . $kelasId . '&mapel=' . $mapelId));
    }

    $daftarSiswa = siswa_kelas($kelasId, true);
    $now = date('Y-m-d H:i:s');
    $sid = 0;
    db()->exec('BEGIN IMMEDIATE');
    try {
        if ($sesi) {
            q('UPDATE absensi SET tanggal = ?, pertemuan = ?, materi = ?, catatan = ?, guru_id = COALESCE(?, guru_id) WHERE id = ? AND sekolah_id = ?',
                [$tanggal, $pertemuan, $materi, $catatan, $guruId, (int) $sesi['id'], sekolah_id()]);
            $sid = (int) $sesi['id'];
            q('DELETE FROM absensi_detail WHERE absensi_id = ?', [$sid]);
        } else {
            q('INSERT INTO absensi (sekolah_id, tanggal, kelas_id, mapel_id, guru_id, ta_id, semester_id, pertemuan, materi, catatan, dibuat, dibuat_oleh) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [sekolah_id(), $tanggal, $kelasId, $mapelId, $guruId, (int) $ta['id'], (int) $sem['id'], $pertemuan, $materi, $catatan, $now, (int) $u['id']]);
            $sid = (int) db()->lastInsertId();
        }
        foreach ($daftarSiswa as $s) {
            $st = strtoupper((string) ($status[(string) $s['id']] ?? $status[(int) $s['id']] ?? 'H'));
            if (!array_key_exists($st, status_hadir())) {
                $st = 'H';
            }
            q('INSERT INTO absensi_detail (absensi_id, siswa_id, status) VALUES (?,?,?)', [$sid, (int) $s['id'], $st]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        if (strpos($ex->getMessage(), 'UNIQUE') !== false) {
            flash('Pertemuan ke-' . $pertemuan . ' sudah tercatat. Pilih nomor pertemuan lain.', 'err');
            redirect('absensi.php');
        }
        throw $ex;
    }
    flash('Daftar hadir pertemuan ke-' . $pertemuan . ' (' . count($daftarSiswa) . ' siswa) disimpan.');
    redirect('absensi.php' . ajaran_qs($ta, $sem) . '&kelas=' . $kelasId . ($mapelId > 0 ? '&mapel=' . $mapelId : ''));
}

// ---------------------------------------------------------------- data siswa & status terisi
$siswa = siswa_kelas($kelasId, true);
$statusAda = [];
if ($sesi) {
    foreach (all('SELECT siswa_id, status FROM absensi_detail WHERE absensi_id = ?', [(int) $sesi['id']]) as $d) {
        $statusAda[(int) $d['siswa_id']] = (string) $d['status'];
    }
}
$meta = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
foreach ($siswa as $s) {
    $st = $statusAda[(int) $s['id']] ?? 'H';
    $meta[array_key_exists($st, $meta) ? $st : 'H']++;
}

page_top($sesi ? 'Ubah Daftar Hadir' : 'Isi Daftar Hadir');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= e(kelas_nama($kelasId)) ?> &middot; <?= e(mapel_nama($mapelId)) ?></h2>
      <p>Tahun ajaran <?= e(ta_nama($ta)) ?> &middot; Semester <?= e(sem_nama($sem)) ?> &middot; <?= count($siswa) ?> siswa</p>
    </div>
    <div class="flex">
      <a class="btn btn-ghost" href="absensi.php<?= e(ajaran_qs($ta, $sem) . '&kelas=' . $kelasId . '&mapel=' . $mapelId) ?>"><?= ikon('clipboard', 16) ?> Daftar Hadir</a>
      <?php if ($siswa): ?><button class="btn btn-ghost" type="button" id="btnSemuaHadir"><?= ikon('check', 16) ?> Tandai Semua Hadir</button><?php endif; ?>
    </div>
  </div>

  <?php if (!$siswa): ?>
    <div class="empty">
      <?= ikon('student', 34) ?>
      <h3>Belum ada siswa pada kelas ini</h3>
      <p>Tambahkan siswa ke kelas <?= e(kelas_nama($kelasId)) ?> terlebih dahulu.</p>
      <?php if (boleh_master()): ?><a class="btn btn-primary" href="siswa_import.php"><?= ikon('upload', 16) ?> Impor Data Siswa</a><?php endif; ?>
    </div>
  <?php else: ?>
    <form method="post" id="formHadir">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) ($sesi['id'] ?? 0) ?>">
      <div class="filterbar mb16">
        <div>
          <label class="f" for="tanggal">Tanggal</label>
          <input type="date" id="tanggal" name="tanggal" value="<?= e($tanggal) ?>" required>
        </div>
        <div>
          <label class="f" for="pertemuan">Pertemuan ke-</label>
          <input type="number" id="pertemuan" name="pertemuan" min="1" value="<?= (int) $pertemuan ?>" required>
        </div>
        <div style="min-width:260px">
          <label class="f" for="materi">Materi / Kegiatan</label>
          <input type="text" id="materi" name="materi" value="<?= e((string) $materi) ?>">
        </div>
        <div style="min-width:220px">
          <label class="f" for="catatan">Catatan</label>
          <input type="text" id="catatan" name="catatan" value="<?= e($catatan) ?>" placeholder="opsional">
        </div>
      </div>

      <div class="stat-mini mb16">
        <div><b id="jmlH"><?= (int) $meta['H'] ?></b><span>Hadir</span></div>
        <div><b id="jmlS"><?= (int) $meta['S'] ?></b><span>Sakit</span></div>
        <div><b id="jmlI"><?= (int) $meta['I'] ?></b><span>Izin</span></div>
        <div><b id="jmlA"><?= (int) $meta['A'] ?></b><span>Alpa</span></div>
      </div>

      <div class="table-wrap">
        <table class="tbl" id="tblHadir">
          <thead><tr><th class="ctr" style="width:44px">No</th><th>Nama Siswa</th><th class="ctr" style="width:220px">Kehadiran</th></tr></thead>
          <tbody>
          <?php foreach ($siswa as $i => $s): $st = $statusAda[(int) $s['id']] ?? 'H'; ?>
            <tr data-siswa="<?= (int) $s['id'] ?>">
              <td class="ctr"><?= $i + 1 ?></td>
              <td><b><?= e((string) $s['nama']) ?></b><?php if ($s['nis']): ?><span class="tbl-sub">NIS <?= e((string) $s['nis']) ?></span><?php endif; ?></td>
              <td class="ctr">
                <span class="st">
                  <?php foreach (status_hadir() as $kode => $label): ?>
                    <label class="sel-<?= e($kode) ?><?= $st === $kode ? ' sel' : '' ?>" title="<?= e($label) ?>">
                      <input type="radio" name="st[<?= (int) $s['id'] ?>]" value="<?= e($kode) ?>" data-st="1" <?= $st === $kode ? 'checked' : '' ?>>
                      <?= e($kode) ?>
                    </label>
                  <?php endforeach; ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="legend mt16">
        <span><i style="background:#10b981"></i>H = Hadir</span>
        <span><i style="background:#f59e0b"></i>S = Sakit</span>
        <span><i style="background:#0ea5e9"></i>I = Izin</span>
        <span><i style="background:#dc2626"></i>A = Alpa</span>
      </div>

      <div class="form-actions mt16">
        <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Daftar Hadir</button>
        <a class="btn btn-ghost" href="absensi.php<?= e(ajaran_qs($ta, $sem) . '&kelas=' . $kelasId) ?>">Batal</a>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php
page_bottom();
