<?php
/**
 * Jurnal Harian Guru.
 *
 * Susunan kolom (permintaan pemilik aplikasi):
 *   Tanggal, Uraian Kegiatan, Kelas/Tempat, Mata Pelajaran, Jam Ke, Materi/Topik Pembelajaran,
 *   Kehadiran Siswa (S, I, A, B, T), Keterangan.
 *
 * Pembaruan v1.37.0 (permintaan pemilik): *"Pada bagian Input Jurnal Harian Baru tambahkan kolom
 * 'Uraian Kegiatan (Pilihan Mengajar bisa juga input manual jika kegiatan selain mengajar)' setelah
 * kolom tanggal. Pada kolom kelas berubah menjadi 'Kelas/Tempat' selain pilih kelas guru bisa
 * menginput manual tempatnya. Mata Pelajaran dan materi/topik pembelajaran bisa dikosongkan jika
 * kegiatan guru selain mengajar"*.
 *   • Uraian Kegiatan — saran "Mengajar"; kegiatan lain (Rapat, Bimbingan, …) diketik manual.
 *   • Kelas/Tempat   — pilih KELAS **atau** isi TEMPAT manual (salah satu wajib, tidak keduanya).
 *   • Mapel & Materi — wajib HANYA bila uraiannya "Mengajar".
 *   • Rekap Jumlah Pertemuan hanya menghitung catatan "Mengajar" (lihat jurnal_sql_mengajar()).
 *
 * Guru hanya melihat jurnalnya sendiri; Super Admin/Admin Operator/Kepala Sekolah-Madrasah
 * melihat seluruh jurnal dan dapat menyaringnya berdasarkan BULAN dan NAMA GURU
 * (pencarian jurnal guru).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_sekolah(); // super admin tanpa konteks dialihkan ke Kelola Sekolah
// Permintaan pemilik (v1.21.0): Admin Operator hanya melihat & mencetak jurnal harian.
$bolehIsi = boleh_isi_administrasi();
$lihatSemua = !is_guru(); // super, kepala, operator
$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];
$kolomKehadiran = jurnal_kehadiran();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$bolehIsi) {
        tolak403('Akun Admin Operator hanya dapat melihat dan mencetak jurnal harian. Pengisiannya '
            . 'dilakukan guru yang bersangkutan.');
    }
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $j = $id > 0 ? one('SELECT * FROM jurnal WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]) : null;

    if ($aksi === 'hapus') {
        if (!$j) {
            flash('Catatan jurnal tidak ditemukan.', 'err');
        } elseif (is_guru() && (int) $j['guru_id'] !== guru_id_saya()) {
            tolak403('Catatan jurnal ini bukan milik Anda.');
        } else {
            q('DELETE FROM jurnal WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
            flash('Catatan jurnal tanggal ' . tanggal_id((string) $j['tanggal']) . ' dihapus.', 'warn');
        }
        redirect('jurnal.php' . ajaran_qs($ta, $sem));
    }

    $tanggal = tgl_ok($_POST['tanggal'] ?? null);
    $uraian = jurnal_uraian_sah($_POST['uraian'] ?? '');
    $tempat = jurnal_tempat_sah($_POST['tempat'] ?? '');
    $mengajar = jurnal_mengajar($uraian);
    $kelasId = (int) ($_POST['kelas_id'] ?? 0);
    $mapelId = (int) ($_POST['mapel_id'] ?? 0);
    $jamKe = trim((string) ($_POST['jam_ke'] ?? ''));
    $materi = trim((string) ($_POST['materi'] ?? ''));
    $keterangan = trim((string) ($_POST['keterangan'] ?? ''));

    // Kehadiran Siswa (S, I, A, B, T): angka 0-999, kosong berarti 0.
    $hadir = [];
    foreach (array_keys($kolomKehadiran) as $kolom) {
        $hadir[$kolom] = max(0, min(999, (int) num($_POST[$kolom] ?? null)));
    }

    $galat = [];
    if ($tanggal === null) {
        $galat[] = 'Tanggal jurnal wajib diisi.';
    }
    // Kelas/Tempat: pilih KELAS **atau** isi TEMPAT manual — salah satu wajib, tidak boleh keduanya
    // (pilihan pemilik, v1.37.0).
    if ($kelasId <= 0 && $tempat === '') {
        $galat[] = 'Pilih kelas atau isi tempat kegiatan terlebih dahulu.';
    } elseif ($kelasId > 0 && $tempat !== '') {
        $galat[] = 'Pilih salah satu: kelas ATAU isi tempat kegiatan (tidak boleh keduanya).';
    } elseif ($kelasId > 0 && !kelas_by_id($kelasId)) {
        $galat[] = 'Kelas yang dipilih tidak ditemukan.';
    } elseif ($kelasId > 0 && is_guru() && !boleh_kelas($kelasId)) {
        tolak403('Kelas itu tidak Anda ampu.');
    }
    // Mata pelajaran & materi wajib HANYA untuk kegiatan mengajar (permintaan pemilik).
    if ($mengajar) {
        if (!$mapelId || !mapel_by_id($mapelId)) {
            $galat[] = 'Pilih mata pelajaran terlebih dahulu (kegiatan mengajar).';
        } elseif (is_guru() && !boleh_mapel($mapelId)) {
            tolak403('Mata pelajaran itu tidak Anda ampu.');
        }
        if ($materi === '') {
            $galat[] = 'Materi/Topik pembelajaran wajib diisi untuk kegiatan mengajar.';
        }
    } elseif ($mapelId > 0) {
        // Kegiatan selain mengajar BOLEH memakai mata pelajaran, tetapi tetap dijaga kepemilikannya.
        if (!mapel_by_id($mapelId)) {
            $galat[] = 'Mata pelajaran yang dipilih tidak ditemukan.';
        } elseif (is_guru() && !boleh_mapel($mapelId)) {
            tolak403('Mata pelajaran itu tidak Anda ampu.');
        }
    }
    if ($galat) {
        foreach ($galat as $g) {
            flash($g, 'err');
        }
        redirect('jurnal.php' . ajaran_qs($ta, $sem) . ($j ? '&edit=' . $id : ''));
    }

    if (!$ta || !$sem) {
        flash('Atur tahun ajaran & semester terlebih dahulu.', 'err');
        redirect('jurnal.php');
    }

    if ($j) {
        if (is_guru() && (int) $j['guru_id'] !== guru_id_saya()) {
            tolak403('Catatan jurnal ini bukan milik Anda.');
        }
        q('UPDATE jurnal SET tanggal=?, uraian=?, tempat=?, kelas_id=?, mapel_id=?, jam_ke=?, materi=?, sakit=?, izin=?, alpa=?, bolos=?, terlambat=?, keterangan=? WHERE id=? AND sekolah_id=?',
            [$tanggal, $uraian, $tempat !== '' ? $tempat : null,
             $kelasId > 0 ? $kelasId : null, $mapelId > 0 ? $mapelId : null, $jamKe,
             $materi !== '' ? $materi : null,
             $hadir['sakit'], $hadir['izin'], $hadir['alpa'], $hadir['bolos'], $hadir['terlambat'], $keterangan, $id, sekolah_id()]);
        flash('Catatan jurnal tanggal ' . tanggal_id($tanggal) . ' diperbarui.');
    } else {
        $guruId = is_guru() ? guru_id_saya() : (int) ($_POST['guru_id'] ?? 0);
        if (is_guru() && $guruId <= 0) {
            flash('Akun Anda belum ditautkan ke data guru sehingga jurnal tidak dapat disimpan.', 'err');
            redirect('jurnal.php');
        }
        q('INSERT INTO jurnal (sekolah_id, tanggal, uraian, tempat, kelas_id, mapel_id, guru_id, ta_id, semester_id, jam_ke, materi,
                               sakit, izin, alpa, bolos, terlambat, keterangan, dibuat, dibuat_oleh)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [sekolah_id(), $tanggal, $uraian, $tempat !== '' ? $tempat : null,
             $kelasId > 0 ? $kelasId : null, $mapelId > 0 ? $mapelId : null,
             $guruId > 0 ? $guruId : null, (int) $ta['id'], (int) $sem['id'], $jamKe,
             $materi !== '' ? $materi : null,
             $hadir['sakit'], $hadir['izin'], $hadir['alpa'], $hadir['bolos'], $hadir['terlambat'], $keterangan,
             date('Y-m-d H:i:s'), (int) $u['id']]);
        flash('Catatan jurnal tanggal ' . tanggal_id($tanggal) . ' disimpan.');
    }
    redirect('jurnal.php' . ajaran_qs($ta, $sem));
}

// ---------------------------------------------------------------- filter
$fKelas = (int) ($_GET['kelas'] ?? 0);
if ($fKelas > 0 && !boleh_kelas($fKelas)) {
    tolak403('Kelas itu tidak Anda ampu.');
}
$fMapel = (int) ($_GET['mapel'] ?? 0);
if ($fMapel > 0 && !boleh_mapel($fMapel)) {
    tolak403('Mata pelajaran itu tidak Anda ampu.');
}
$fBulan = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['bulan'] ?? '')) === 1 ? (string) $_GET['bulan'] : '';
$fGuru = $lihatSemua ? (int) ($_GET['guru'] ?? 0) : 0;
if ($fGuru > 0 && !guru_by_id($fGuru)) {
    $fGuru = 0;
}
$fCari = trim((string) ($_GET['cari'] ?? ''));
// Penyaring Uraian Kegiatan (v1.37.0): '' = semua, 'mengajar' = hanya kegiatan mengajar,
// 'lain' = selain mengajar. Tautan "Lihat Jurnal Pembelajaran" pada Rekap Pertemuan memakai
// 'mengajar' agar jumlah barisnya sama dengan Jumlah Tatap Muka.
$fUraian = (string) ($_GET['uraian'] ?? '');
if (!in_array($fUraian, ['mengajar', 'lain'], true)) {
    $fUraian = '';
}
$fSemuaPeriode = ($_GET['periode'] ?? '') === 'semua';
$fDari = tgl_ok($_GET['dari'] ?? null);
$fSampai = tgl_ok($_GET['sampai'] ?? null);
if ($fDari !== null && $fSampai !== null && $fDari > $fSampai) {
    [$fDari, $fSampai] = [$fSampai, $fDari];
}

$w = ['j.sekolah_id = ?'];
$p = [sekolah_id()];
if ($ta && $sem && !$fSemuaPeriode) {
    $w[] = '(j.ta_id = ? OR j.ta_id IS NULL)';
    $p[] = (int) $ta['id'];
    $w[] = '(j.semester_id = ? OR j.semester_id IS NULL)';
    $p[] = (int) $sem['id'];
}
if (is_guru()) {
    $w[] = 'j.guru_id = ?';
    $p[] = guru_id_saya();
} elseif ($fGuru > 0) {
    $w[] = 'j.guru_id = ?';
    $p[] = $fGuru;
}
if ($fKelas > 0) {
    $w[] = 'j.kelas_id = ?';
    $p[] = $fKelas;
}
if ($fMapel > 0) {
    $w[] = 'j.mapel_id = ?';
    $p[] = $fMapel;
}
if ($fBulan !== '') {
    $w[] = "strftime('%Y-%m', j.tanggal) = ?";
    $p[] = $fBulan;
}
if ($fDari !== null) {
    $w[] = 'j.tanggal >= ?';
    $p[] = $fDari;
}
if ($fSampai !== null) {
    $w[] = 'j.tanggal <= ?';
    $p[] = $fSampai;
}
if ($fUraian === 'mengajar') {
    $w[] = jurnal_sql_mengajar('j');
} elseif ($fUraian === 'lain') {
    $w[] = 'NOT ' . jurnal_sql_mengajar('j');
}
if ($fCari !== '') {
    // Pencarian juga menjangkau Uraian Kegiatan & Tempat (v1.37.0) supaya kegiatan non-mengajar
    // (mis. "Rapat", "Ruang Guru") mudah ditelusuri.
    $w[] = '(j.materi LIKE ? OR j.keterangan LIKE ? OR g.nama LIKE ? OR j.uraian LIKE ? OR j.tempat LIKE ?)';
    $like = '%' . $fCari . '%';
    array_push($p, $like, $like, $like, $like, $like);
}
$where = ' WHERE ' . implode(' AND ', $w);

$rows = all('SELECT j.*, k.nama AS kelas_nama, m.nama AS mapel_nama, g.nama AS guru_nama
    FROM jurnal j LEFT JOIN kelas k ON k.id = j.kelas_id LEFT JOIN mapel m ON m.id = j.mapel_id LEFT JOIN guru g ON g.id = j.guru_id'
    . $where . ' ORDER BY j.tanggal DESC, j.id DESC LIMIT 400', $p);

$edit = (int) ($_GET['edit'] ?? 0) > 0
    ? one('SELECT * FROM jurnal WHERE id = ? AND sekolah_id = ?', [(int) $_GET['edit'], sekolah_id()]) : null;
if ($edit && is_guru() && (int) $edit['guru_id'] !== guru_id_saya()) {
    tolak403('Catatan jurnal ini bukan milik Anda.');
}

$qPrint = array_filter([
    'kelas' => $fKelas ?: null, 'mapel' => $fMapel ?: null, 'bulan' => $fBulan ?: null,
    'guru' => $fGuru ?: null, 'cari' => $fCari ?: null, 'periode' => $fSemuaPeriode ? 'semua' : null,
    'uraian' => $fUraian ?: null,
    'dari' => $fDari, 'sampai' => $fSampai, 'ta' => $ta ? (int) $ta['id'] : null, 'sem' => $sem ? (int) $sem['id'] : null,
]);
$qDasar = ['ta' => $ta ? (int) $ta['id'] : null, 'sem' => $sem ? (int) $sem['id'] : null];
$adaFilter = $fBulan !== '' || $fGuru > 0 || $fKelas > 0 || $fMapel > 0 || $fCari !== '' || $fDari !== null || $fSampai !== null || $fSemuaPeriode || $fUraian !== '';

/** Jumlah catatan per guru pada filter yang sedang aktif (untuk pencarian jurnal guru). */
$perGuru = [];
if ($lihatSemua) {
    $perGuru = all('SELECT COALESCE(g.nama, "(tanpa guru)") AS nama, COUNT(*) AS jml
        FROM jurnal j LEFT JOIN guru g ON g.id = j.guru_id' . $where . '
        GROUP BY COALESCE(j.guru_id, 0) ORDER BY jml DESC, nama LIMIT 12', $p);
}

page_top('Jurnal Harian Guru');
?>
<?php if (is_guru() && guru_tanpa_kelas()): ?>
  <?= kartu_tanpa_penugasan('Anda belum ditugaskan ke kelas mana pun',
      'Jurnal harian diisi untuk kelas yang Anda ajar, tetapi belum ada kelas yang ditetapkan untuk akun Anda.') ?>
<?php elseif ($bolehIsi): ?>
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= $edit ? 'Ubah Catatan Jurnal' : 'Catatan Jurnal Baru' ?></h2>
        <p><?= is_guru() ? 'Jurnal atas nama Anda: ' . e((string) (guru_saya()['nama'] ?? '-')) : 'Pilih guru pelaksana pembelajaran.' ?></p>
      </div>
      <?php if ($edit): ?><a class="btn btn-ghost btn-sm" href="jurnal.php<?= e(ajaran_qs($ta, $sem)) ?>">Batal</a><?php endif; ?>
    </div>
    <form method="post" class="form-grid" id="formJurnal">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <?php
      // Nilai awal formulir (mode Ubah memakai data yang tersimpan).
      $nTanggal = (string) ($edit['tanggal'] ?? date('Y-m-d'));
      $nUraian  = $edit ? jurnal_uraian($edit) : JURNAL_URAIAN_BAWAAN;
      $nTempat  = $edit ? jurnal_tempat($edit) : '';
      $nMengajar = jurnal_mengajar($nUraian);
      ?>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="tanggal">Tanggal *</label>
          <input type="date" id="tanggal" name="tanggal" value="<?= e($nTanggal) ?>" required>
        </div>
        <div>
          <?php // Uraian Kegiatan (permintaan pemilik): saran "Mengajar", kegiatan lain diketik manual. ?>
          <label class="f" for="uraian">Uraian Kegiatan *</label>
          <input type="text" id="uraian" name="uraian" list="pilihanUraian" autocomplete="off" maxlength="<?= (int) JURNAL_URAIAN_MAKS ?>"
                 value="<?= e($nUraian) ?>" placeholder="mis. Mengajar, Rapat, Bimbingan">
          <datalist id="pilihanUraian">
            <?php foreach (jurnal_uraian_list() as $p): ?><option value="<?= e($p) ?>"></option><?php endforeach; ?>
          </datalist>
          <div class="hint">Pilih <b>Mengajar</b>, atau ketik kegiatan lain (mis. Rapat, Bimbingan, Dinas Luar).</div>
        </div>
      </div>

      <div class="grid g2" style="gap:16px">
        <div>
          <?php // Kelas/Tempat (permintaan pemilik): pilih KELAS **atau** isi TEMPAT manual —
                // salah satu wajib, tidak boleh keduanya. Penjagaan utamanya di server. ?>
          <label class="f" for="kelas_id">Kelas / Tempat *</label>
          <div class="kelas-tempat">
            <select id="kelas_id" name="kelas_id">
              <option value="">— pilih kelas —</option>
              <?php foreach (kelas_pilih() as $k): ?>
                <option value="<?= (int) $k['id'] ?>" <?= (int) ($edit['kelas_id'] ?? 0) === (int) $k['id'] ? 'selected' : '' ?>><?= e((string) $k['nama']) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" id="tempat" name="tempat" maxlength="<?= (int) JURNAL_TEMPAT_MAKS ?>"
                   value="<?= e($nTempat) ?>" placeholder="atau isi tempat, mis. Ruang Guru / Lab IPA">
          </div>
          <div class="hint">Pilih <b>kelas</b> <b>atau</b> isi <b>tempat</b> kegiatan (untuk kegiatan di luar kelas) — cukup salah satu.</div>
        </div>
        <div>
          <label class="f" for="mapel_id">Mata Pelajaran <span data-wajib-mengajar<?= $nMengajar ? '' : ' hidden' ?>>*</span></label>
          <select id="mapel_id" name="mapel_id" data-wajib-jurnal>
            <option value="">— pilih mata pelajaran —</option>
            <?php foreach (mapel_pilih() as $m): ?>
              <option value="<?= (int) $m['id'] ?>" <?= (int) ($edit['mapel_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= e((string) $m['nama']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Boleh dikosongkan bila kegiatannya <b>selain mengajar</b>.</div>
        </div>
      </div>

      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="jam_ke">Jam Ke-</label>
          <input type="text" id="jam_ke" name="jam_ke" value="<?= e((string) ($edit['jam_ke'] ?? '')) ?>" placeholder="mis. 3-4">
        </div>
        <div>
          <label class="f" for="materi">Materi / Topik Pembelajaran <span data-wajib-mengajar<?= $nMengajar ? '' : ' hidden' ?>>*</span></label>
          <input type="text" id="materi" name="materi" data-wajib-jurnal value="<?= e((string) ($edit['materi'] ?? '')) ?>">
          <div class="hint">Boleh dikosongkan bila kegiatannya <b>selain mengajar</b>.</div>
        </div>
      </div>

      <div>
        <div class="section-title mb8"><?= ikon('student', 15) ?> Kehadiran Siswa</div>
        <div class="grid g5" style="gap:12px">
          <?php foreach ($kolomKehadiran as $kolom => [$kode, $label]): ?>
            <div>
              <label class="f" for="<?= e($kolom) ?>"><?= e($kode) ?> — <?= e($label) ?></label>
              <input type="number" id="<?= e($kolom) ?>" name="<?= e($kolom) ?>" min="0" max="999" step="1"
                     value="<?= e(num_input((int) ($edit[$kolom] ?? 0))) ?>" placeholder="0">
            </div>
          <?php endforeach; ?>
        </div>
        <div class="hint">Isi jumlah siswa untuk setiap jenis ketidakhadiran. Kosongkan bila tidak ada (dianggap 0).</div>
      </div>

      <?php if (!is_guru()): ?>
        <div>
          <label class="f" for="guru_id">Guru Pelaksana</label>
          <select id="guru_id" name="guru_id" <?= $edit ? 'disabled' : '' ?>>
            <option value="">— tidak ditentukan —</option>
            <?php foreach (guru_list(true) as $g): ?>
              <option value="<?= (int) $g['id'] ?>" <?= (int) ($edit['guru_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= e((string) $g['nama']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($edit): ?><div class="hint">Guru pelaksana tidak diubah saat mengedit catatan lama.</div><?php endif; ?>
        </div>
      <?php endif; ?>

      <div>
        <label class="f" for="keterangan">Keterangan</label>
        <textarea id="keterangan" name="keterangan" rows="2" placeholder="Catatan tambahan, mis. siswa yang perlu perhatian, tindak lanjut, atau kendala pembelajaran"><?= e((string) ($edit['keterangan'] ?? '')) ?></textarea>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $edit ? 'Simpan Perubahan' : 'Simpan Jurnal' ?></button>
        <span class="xs mut">Periode aktif: T.A. <?= e(ta_nama($ta)) ?> &middot; Semester <?= e(sem_nama($sem)) ?></span>
      </div>
    </form>
  </div>
<?php elseif (!$bolehIsi): ?>
  <?php // Permintaan pemilik v1.21.0: Admin Operator hanya melihat & mencetak jurnal harian. ?>
  <div class="alert alert-warn">
    <?= ikon('eye', 16) ?>
    <span><?= pemberitahuan_hanya_lihat_administrasi('jurnal harian guru') ?></span>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= $lihatSemua ? 'Pencarian Jurnal Guru' : 'Daftar Jurnal Saya' ?></h2>
      <p><span id="tblCount"><?= count($rows) ?></span> catatan<?= $adaFilter ? ' sesuai filter' : '' ?><?= $fBulan !== '' ? ' &middot; bulan ' . e(bulan_id((int) substr($fBulan, 5, 2)) . ' ' . substr($fBulan, 0, 4)) : '' ?><?= $fGuru > 0 ? ' &middot; ' . e(guru_nama($fGuru)) : '' ?></p>
    </div>
    <div class="flex">
      <a class="btn btn-ghost" href="cetak_jurnal.php?<?= e(http_build_query($qPrint)) ?>" target="_blank"><?= ikon('print', 16) ?> Cetak Jurnal</a>
    </div>
  </div>

  <form class="filterbar mb16" method="get">
    <?php foreach ($qDasar as $k => $v): ?><?php if ($v !== null): ?><input type="hidden" name="<?= e($k) ?>" value="<?= (int) $v ?>"><?php endif; ?><?php endforeach; ?>
    <div>
      <label class="f" for="bulan">Bulan</label>
      <input type="month" id="bulan" name="bulan" value="<?= e($fBulan) ?>">
    </div>
    <?php if ($lihatSemua): ?>
      <div style="min-width:220px">
        <label class="f" for="guru">Nama Guru</label>
        <select id="guru" name="guru" data-auto>
          <option value="">Semua guru</option>
          <?php foreach (guru_list(true) as $g): ?>
            <option value="<?= (int) $g['id'] ?>" <?= $fGuru === (int) $g['id'] ? 'selected' : '' ?>><?= e((string) $g['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:200px">
        <label class="f" for="cari">Cari (materi / keterangan / guru)</label>
        <input type="search" id="cari" name="cari" value="<?= e($fCari) ?>" placeholder="kata kunci...">
      </div>
    <?php endif; ?>
    <div>
      <label class="f" for="kelas">Kelas</label>
      <select id="kelas" name="kelas" data-auto>
        <option value="">Semua kelas</option>
        <?php foreach (kelas_pilih() as $k): ?>
          <option value="<?= (int) $k['id'] ?>" <?= $fKelas === (int) $k['id'] ? 'selected' : '' ?>><?= e((string) $k['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="f" for="mapel">Mata Pelajaran</label>
      <select id="mapel" name="mapel" data-auto>
        <option value="">Semua mapel</option>
        <?php foreach (mapel_pilih() as $m): ?>
          <option value="<?= (int) $m['id'] ?>" <?= $fMapel === (int) $m['id'] ? 'selected' : '' ?>><?= e((string) $m['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($lihatSemua): ?>
      <div>
        <label class="f" for="periode">Periode</label>
        <select id="periode" name="periode" data-auto>
          <option value="aktif" <?= !$fSemuaPeriode ? 'selected' : '' ?>>Periode aktif (<?= e(ta_nama($ta)) ?> — <?= e(sem_nama($sem)) ?>)</option>
          <option value="semua" <?= $fSemuaPeriode ? 'selected' : '' ?>>Semua periode</option>
        </select>
      </div>
    <?php endif; ?>
    <div>
      <label class="f" for="dari">Dari tanggal</label>
      <input type="date" id="dari" name="dari" value="<?= e((string) $fDari) ?>">
    </div>
    <?php // ⚠️ id penyaring DIBERI AWALAN "saring" supaya TIDAK kembar dengan isian Uraian Kegiatan
          // pada formulir di halaman yang sama (id kembar = HTML tidak sah & skrip bisa salah sasaran). ?>
    <div>
      <label class="f" for="saringUraian">Uraian Kegiatan</label>
      <select id="saringUraian" name="uraian" data-auto>
        <option value="">Semua kegiatan</option>
        <option value="mengajar" <?= $fUraian === 'mengajar' ? 'selected' : '' ?>>Kegiatan mengajar</option>
        <option value="lain" <?= $fUraian === 'lain' ? 'selected' : '' ?>>Selain mengajar</option>
      </select>
    </div>
    <div>
      <label class="f" for="sampai">Sampai tanggal</label>
      <input type="date" id="sampai" name="sampai" value="<?= e((string) $fSampai) ?>">
    </div>
    <button class="btn btn-ghost" type="submit"><?= ikon('search', 15) ?> Cari</button>
    <?php if ($adaFilter): ?>
      <a class="btn btn-ghost" href="jurnal.php<?= e($ta ? ajaran_qs($ta, $sem) : '') ?>">Reset</a>
    <?php endif; ?>
  </form>

  <?php if ($perGuru && $lihatSemua): ?>
    <div class="chip-list mb16">
      <span class="chip">Jumlah per guru pada filter ini:</span>
      <?php foreach ($perGuru as $pg): ?>
        <span class="chip<?= $fGuru > 0 && $pg['nama'] === guru_nama($fGuru) ? ' on' : '' ?>"><?= e((string) $pg['nama']) ?> &middot; <?= (int) $pg['jml'] ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$rows): ?>
    <div class="empty">
      <?= ikon('book', 34) ?>
      <h3>Belum ada catatan jurnal</h3>
      <p><?= $adaFilter ? 'Tidak ada jurnal yang cocok dengan filter/bulan & nama guru yang dipilih. Coba ubah bulan atau pilih "Semua periode".' : 'Catat kegiatan pembelajaran harian lewat formulir di atas.' ?></p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblJurnal">
        <thead>
          <tr>
            <th>Tanggal</th><th>Uraian Kegiatan</th><th>Kelas / Tempat</th><th>Mata Pelajaran</th>
            <th class="ctr">Jam Ke</th>
            <th>Materi / Topik Pembelajaran</th>
            <th class="ctr">Kehadiran Siswa<br><span class="xs mut">S / I / A / B / T</span></th>
            <th>Keterangan</th><th>Guru</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $j):
            $jmlTidakHadir = kehadiran_jumlah($j);
        ?>
          <tr>
            <td class="nowrap"><b><?= e(tanggal_id((string) $j['tanggal'])) ?></b><span class="tbl-sub"><?= e(hari_id((string) $j['tanggal'])) ?></span></td>
            <?php // Uraian Kegiatan + Kelas/Tempat (v1.37.0): kegiatan selain mengajar ditandai pill. ?>
            <td>
              <span class="pill <?= jurnal_mengajar($j) ? 'pill-ok' : 'pill-gray' ?>"><?= e(jurnal_uraian($j)) ?></span>
            </td>
            <td>
              <span class="tagmini"><?= e(jurnal_kelas_tempat($j)) ?></span>
              <?php if (jurnal_tempat($j) !== '' && trim((string) ($j['kelas_nama'] ?? '')) === ''): ?><span class="tbl-sub">tempat (manual)</span><?php endif; ?>
            </td>
            <td><?= e((string) ($j['mapel_nama'] ?: '-')) ?></td>
            <td class="ctr"><?= e((string) ($j['jam_ke'] ?: '-')) ?></td>
            <td><?= e((string) ($j['materi'] !== '' && $j['materi'] !== null ? $j['materi'] : '-')) ?></td>
            <td class="ctr">
              <span class="kehadiran-kode">
                <?php foreach ($kolomKehadiran as $kolom => [$kode, $label]): $n = (int) ($j[$kolom] ?? 0); ?>
                  <span class="kh<?= $n > 0 ? ' ada' : '' ?>" title="<?= e($label) ?>"><i><?= e($kode) ?></i><?= $n > 0 ? $n : '-' ?></span>
                <?php endforeach; ?>
              </span>
              <?php if ($jmlTidakHadir > 0): ?><span class="tbl-sub"><?= $jmlTidakHadir ?> siswa</span><?php endif; ?>
            </td>
            <td class="sm mut"><?= nl2br(e((string) ($j['keterangan'] ?: '-'))) ?></td>
            <td class="td-guru"><?= e((string) ($j['guru_nama'] ?: '-')) ?></td>
            <td>
              <?php if ($bolehIsi && (!is_guru() || (int) $j['guru_id'] === guru_id_saya())): ?>
                <div class="row-actions">
                  <a class="btn btn-ghost btn-sm" href="jurnal.php<?= e(ajaran_qs($ta, $sem) . '&edit=' . (int) $j['id']) ?>"><?= ikon('edit', 15) ?></a>
                  <form method="post" data-confirm="Hapus catatan jurnal <?= e(tanggal_id((string) $j['tanggal'])) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= (int) $j['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 15) ?></button>
                  </form>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="legend mt16">
      <?php foreach ($kolomKehadiran as $kolom => [$kode, $label]): ?>
        <span><b><?= e($kode) ?></b> = <?= e($label) ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php
page_bottom();
