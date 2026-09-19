<?php
/**
 * KENAIKAN KELAS & KELULUSAN SISWA.
 *
 * Hanya Super Admin (dalam konteks sekolah) dan Admin Operator yang boleh memproses
 * (kepala sekolah/madrasah & guru ditolak 403 — permintaan pemilik).
 *
 * Cara kerja:
 *  - Naik kelas : siswa terpilih dipindahkan ke kelas tujuan (mis. Kelas X → Kelas XI).
 *  - Luluskan   : siswa terpilih diberi status "lulus" dan tidak lagi dihitung sebagai siswa aktif
 *                 (kelas asalnya tetap tersimpan sebagai asal kelulusan).
 *  - Setiap proses dicatat di tabel `siswa_kenaikan` (siapa, kapan, dari kelas mana ke mana)
 *    sehingga dapat ditelusuri dan DIBATALKAN bila salah proses.
 * Siswa yang tidak dicentang tidak diubah sama sekali (mis. tinggal kelas).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_master();                 // super admin dalam konteks sekolah, atau admin operator
$sid = sekolah_id();
$konteks = konteks_ajaran();
$ta = $konteks['ta'];

$aksiList = ['naik' => 'Naik kelas', 'lulus' => 'Luluskan'];

// ---------------------------------------------------------------- aksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');

    // ------ batalkan proses (mengembalikan kelas/status siswa seperti semula)
    if ($aksi === 'batalkan') {
        $id = (int) ($_POST['id'] ?? 0);
        $r = one('SELECT * FROM siswa_kenaikan WHERE id = ? AND sekolah_id = ?', [$id, $sid]);
        if (!$r) {
            flash('Riwayat kenaikan tidak ditemukan.', 'err');
        } elseif (!empty($r['dibatalkan'])) {
            flash('Riwayat ini sudah dibatalkan sebelumnya.', 'warn');
        } else {
            db()->exec('BEGIN IMMEDIATE');
            try {
                if ((string) $r['jenis'] === 'lulus') {
                    q("UPDATE siswa SET status = 'aktif', aktif = 1, tanggal_status = ? WHERE id = ? AND sekolah_id = ?",
                        [date('Y-m-d H:i:s'), (int) $r['siswa_id'], $sid]);
                } else {
                    q("UPDATE siswa SET kelas_id = ?, status = 'aktif', aktif = 1, tanggal_status = ? WHERE id = ? AND sekolah_id = ?",
                        [(int) $r['dari_kelas_id'], date('Y-m-d H:i:s'), (int) $r['siswa_id'], $sid]);
                }
                q('UPDATE siswa_kenaikan SET dibatalkan = ? WHERE id = ? AND sekolah_id = ?',
                    [date('Y-m-d H:i:s'), $id, $sid]);
                db()->exec('COMMIT');
            } catch (Throwable $ex) {
                db()->exec('ROLLBACK');
                throw $ex;
            }
            flash('Proses untuk ' . (string) ($r['siswa_nama'] ?? 'siswa') . ' dibatalkan — kelas/statusnya dikembalikan.', 'warn');
        }
        redirect('kenaikan.php');
    }

    // ------ proses naik kelas / kelulusan
    $kelasAsal = (int) ($_POST['kelas_asal'] ?? 0);
    $jenis = array_key_exists((string) ($_POST['jenis'] ?? ''), $aksiList) ? (string) $_POST['jenis'] : 'naik';
    $kelasTujuan = (int) ($_POST['kelas_tujuan'] ?? 0);
    $catatan = trim((string) ($_POST['catatan'] ?? ''));
    $pilih = array_values(array_unique(array_map('intval', (array) ($_POST['siswa'] ?? []))));

    $asal = kelas_by_id($kelasAsal);
    if (!$asal) {
        flash('Pilih kelas asal terlebih dahulu.', 'err');
        redirect('kenaikan.php');
    }
    if ($jenis === 'naik') {
        if (!$kelasTujuan || !kelas_by_id($kelasTujuan)) {
            flash('Pilih kelas tujuan untuk kenaikan kelas.', 'err');
            redirect('kenaikan.php?kelas_asal=' . $kelasAsal);
        }
        if ($kelasTujuan === $kelasAsal) {
            flash('Kelas tujuan harus berbeda dengan kelas asal.', 'err');
            redirect('kenaikan.php?kelas_asal=' . $kelasAsal);
        }
    }
    if (!$pilih) {
        flash('Pilih dulu siswa yang akan diproses (centang namanya).', 'err');
        redirect('kenaikan.php?kelas_asal=' . $kelasAsal);
    }

    // Hanya siswa yang benar-benar ada di kelas asal, milik sekolah ini, dan masih aktif (belum lulus).
    $tanda = implode(',', array_fill(0, count($pilih), '?'));
    $daftar = all('SELECT * FROM siswa WHERE id IN (' . $tanda . ') AND sekolah_id = ? AND kelas_id = ? AND aktif = 1',
        array_merge($pilih, [$sid, $kelasAsal]));

    if (!$daftar) {
        flash('Tidak ada siswa yang cocok untuk diproses (mungkin sudah dipindahkan atau diluluskan).', 'warn');
        redirect('kenaikan.php?kelas_asal=' . $kelasAsal);
    }

    $now = date('Y-m-d H:i:s');
    $berhasil = 0;
    $dilewati = count($pilih) - count($daftar);
    db()->exec('BEGIN IMMEDIATE');
    try {
        foreach ($daftar as $s) {
            if ($jenis === 'naik') {
                // Syarat `kelas_id = ?` mencegah menimpa siswa yang sudah dipindahkan lebih dulu.
                q("UPDATE siswa SET kelas_id = ?, status = 'aktif', tanggal_status = ? WHERE id = ? AND sekolah_id = ? AND kelas_id = ?",
                    [$kelasTujuan, $now, (int) $s['id'], $sid, $kelasAsal]);
            } else {
                q("UPDATE siswa SET status = 'lulus', aktif = 0, tanggal_status = ? WHERE id = ? AND sekolah_id = ? AND kelas_id = ?",
                    [$now, (int) $s['id'], $sid, $kelasAsal]);
            }
            q('INSERT INTO siswa_kenaikan (sekolah_id, siswa_id, dari_kelas_id, ke_kelas_id, jenis, ta_id, ta_nama,
                    catatan, diproses_oleh, diproses_nama, dibuat)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$sid, (int) $s['id'], $kelasAsal, $jenis === 'naik' ? $kelasTujuan : null, $jenis,
                 $ta ? (int) $ta['id'] : null, $ta ? (string) $ta['nama'] : null,
                 $catatan !== '' ? $catatan : null, (int) $u['id'], (string) $u['nama'], $now]);
            $berhasil++;
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }

    if ($jenis === 'naik') {
        flash($berhasil . ' siswa dinaikkan dari ' . $asal['nama'] . ' ke ' . kelas_nama($kelasTujuan) . '.'
            . ($dilewati > 0 ? ' ' . $dilewati . ' siswa dilewati karena tidak lagi berada di kelas asal.' : ''));
    } else {
        flash($berhasil . ' siswa dari ' . $asal['nama'] . ' dinyatakan LULUS dan tidak lagi dihitung sebagai siswa aktif.'
            . ($dilewati > 0 ? ' ' . $dilewati . ' siswa dilewati karena tidak lagi berada di kelas asal.' : ''));
    }
    redirect('kenaikan.php?kelas_asal=' . $kelasAsal);
}

// ---------------------------------------------------------------- data halaman
$kelasSemua = kelas_list(true);
$fKelasAsal = (int) ($_GET['kelas_asal'] ?? 0);
if ($fKelasAsal > 0 && !kelas_by_id($fKelasAsal)) {
    $fKelasAsal = 0;
}
if ($fKelasAsal <= 0 && $kelasSemua) {
    $fKelasAsal = (int) $kelasSemua[0]['id'];
}

$siswaAsal = $fKelasAsal > 0 ? siswa_kelas_status($fKelasAsal) : [];
$fKelasRiwayat = (int) ($_GET['riwayat_kelas'] ?? 0);
$riwayat = kenaikan_riwayat(200);
if ($fKelasRiwayat > 0) {
    $riwayat = array_values(array_filter($riwayat, static fn(array $r): bool =>
        (int) $r['dari_kelas_id'] === $fKelasRiwayat || (int) $r['ke_kelas_id'] === $fKelasRiwayat));
}

$jmlLulus = jumlah_siswa_status('lulus');
$jmlAktif = jumlah_siswa_status('aktif');
$jmlRiwayatNaik = (int) val('SELECT COUNT(*) FROM siswa_kenaikan WHERE sekolah_id = ? AND jenis = "naik" AND dibatalkan IS NULL', [$sid], 0);
$jmlRiwayatLulus = (int) val('SELECT COUNT(*) FROM siswa_kenaikan WHERE sekolah_id = ? AND jenis = "lulus" AND dibatalkan IS NULL', [$sid], 0);

page_top('Kenaikan Kelas & Kelulusan', e(istilah_sekolah('Sekolah')) . ' ini &middot; hanya Super Admin &amp; Admin Operator');
?>
<div class="grid g4">
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('student', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $jmlAktif ?></div><div class="stat-lbl">Siswa aktif</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic g"><?= ikon('star', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $jmlLulus ?></div><div class="stat-lbl">Siswa lulus</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic s"><?= ikon('switch', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $jmlRiwayatNaik ?></div><div class="stat-lbl">Kenaikan tercatat</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic o"><?= ikon('check', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $jmlRiwayatLulus ?></div><div class="stat-lbl">Kelulusan tercatat</div></div>
  </div>
</div>

<?php if (!$kelasSemua): ?>
  <div class="card">
    <div class="empty">
      <?= ikon('school', 34) ?>
      <h3>Belum ada kelas</h3>
      <p>Kenaikan kelas memerlukan data kelas terlebih dahulu.</p>
      <a class="btn btn-primary" href="kelas.php"><?= ikon('plus', 16) ?> Tambah Kelas</a>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-head">
      <div>
        <h2>Proses Naik Kelas / Kelulusan</h2>
        <p>Pilih kelas asal, tentukan tindakannya, lalu centang siswa yang diproses.
          Siswa yang <b>tidak dicentang tidak diubah</b> (mis. tinggal kelas).</p>
      </div>
      <a class="btn btn-ghost btn-sm" href="siswa.php"><?= ikon('student', 15) ?> Data Siswa</a>
    </div>

    <!-- Pemilihan kelas asal memakai form GET tersendiri supaya berpindah kelas TIDAK
         mengirim formulir proses (dulu select-nya berada di dalam form POST sehingga ikut ter-submit). -->
    <form method="get" class="filterbar mb16">
      <div style="min-width:240px">
        <label class="f" for="kelas_asal">Kelas Asal</label>
        <select id="kelas_asal" name="kelas_asal" data-auto>
          <?php foreach ($kelasSemua as $k): ?>
            <option value="<?= (int) $k['id'] ?>" <?= $fKelasAsal === (int) $k['id'] ? 'selected' : '' ?>>
              <?= e((string) $k['nama']) ?> (<?= (int) $k['jml_siswa'] ?> siswa aktif)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Tampilkan Siswa</button>
    </form>

    <form method="post" id="formKenaikan" class="grid" style="gap:16px"
          data-confirm="Proses kenaikan/kelulusan untuk siswa yang dicentang? Perubahan langsung tersimpan.">
      <?= csrf_field() ?>
      <input type="hidden" name="kelas_asal" value="<?= (int) $fKelasAsal ?>">
      <div class="grid g3" style="gap:16px">
        <div>
          <label class="f" for="jenis">Tindakan *</label>
          <select id="jenis" name="jenis">
            <option value="naik">Naik kelas</option>
            <option value="lulus">Luluskan</option>
          </select>
        </div>
        <div>
          <label class="f" for="kelas_tujuan">Kelas Tujuan (untuk naik kelas) *</label>
          <select id="kelas_tujuan" name="kelas_tujuan">
            <option value="">— pilih kelas tujuan —</option>
            <?php foreach ($kelasSemua as $k): ?>
              <?php if ((int) $k['id'] !== $fKelasAsal): ?>
                <option value="<?= (int) $k['id'] ?>"><?= e((string) $k['nama']) ?>
                  <?= (int) $k['jml_siswa'] > 0 ? '(' . (int) $k['jml_siswa'] . ' siswa aktif)' : '' ?></option>
              <?php endif; ?>
            <?php endforeach; ?>
          </select>
          <div class="hint">Diabaikan bila tindakannya "Luluskan".</div>
        </div>
      </div>
      <div>
        <label class="f" for="catatan">Catatan</label>
        <input type="text" id="catatan" name="catatan" placeholder="mis. hasil rapat kenaikan kelas">
      </div>

      <?php if (!$siswaAsal): ?>
        <div class="alert alert-warn" style="margin:0">
          <?= ikon('alert', 16) ?>
          <span>Kelas <b><?= e(kelas_nama($fKelasAsal)) ?></b> belum memiliki siswa aktif.
            Pindahkan dulu siswa ke kelas ini pada menu Data Siswa.</span>
        </div>
      <?php else: ?>
        <div class="flex between">
          <div class="section-title mb0"><?= ikon('student', 15) ?> Siswa di <?= e(kelas_nama($fKelasAsal)) ?>
            (<?= count($siswaAsal) ?>)</div>
          <div class="flex">
            <button class="btn btn-ghost btn-sm" type="button" id="btnPilihSemua">Pilih semua</button>
            <button class="btn btn-ghost btn-sm" type="button" id="btnKosongkan">Kosongkan</button>
          </div>
        </div>

        <div class="table-wrap">
          <table class="tbl" id="tblKenaikan">
            <thead>
              <tr><th class="ctr" style="width:44px">Pilih</th><th class="ctr" style="width:44px">No</th>
                <th>Nama Siswa</th><th>NIS / NISN</th><th class="ctr">JK</th><th>Wali</th>
                <th class="ctr">Nilai</th><th class="ctr">Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($siswaAsal as $i => $s):
                $jmlNilai = (int) val('SELECT COUNT(*) FROM nilai WHERE siswa_id = ? AND sekolah_id = ?', [(int) $s['id'], $sid], 0);
                $lulus = strcasecmp((string) ($s['status'] ?? 'aktif'), 'lulus') === 0;
            ?>
              <tr>
                <td class="ctr">
                  <input type="checkbox" name="siswa[]" value="<?= (int) $s['id'] ?>" checked
                         class="pilih-siswa" style="width:16px;height:16px;accent-color:var(--hijau)"
                         aria-label="Pilih <?= e((string) $s['nama']) ?>">
                </td>
                <td class="ctr"><?= $i + 1 ?></td>
                <td><b><?= e((string) $s['nama']) ?></b></td>
                <td class="nowrap"><?= e((string) ($s['nis'] ?: '-')) ?>
                  <span class="tbl-sub"><?= e((string) ($s['nisn'] ?: '-')) ?></span></td>
                <td class="ctr"><?= e((string) ($s['jenis_kelamin'] ?: '-')) ?></td>
                <td class="sm"><?= e((string) ($s['nama_wali'] ?: '-')) ?></td>
                <td class="ctr"><?= $jmlNilai ?></td>
                <td class="ctr"><span class="pill <?= $lulus ? 'pill-A' : 'pill-ok' ?>"><?= e(status_siswa($s['status'] ?? 'aktif')) ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="alert alert-warn" style="margin:0">
          <?= ikon('alert', 16) ?>
          <span><b>Perhatikan:</b> proses ini langsung mengubah data siswa. Setelah diproses, riwayatnya
            tercatat di bawah dan <b>dapat dibatalkan</b> bila keliru. Kelulusan membuat siswa tidak lagi
            muncul pada daftar hadir/nilai (riwayat nilainya tetap tersimpan).</span>
        </div>

        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Proses Sekarang</button>
          <span class="xs mut">Terpilih: <b id="jmlPilih"><?= count($siswaAsal) ?></b> dari <?= count($siswaAsal) ?> siswa</span>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h2>Riwayat Kenaikan &amp; Kelulusan</h2>
        <p><span id="tblCount"><?= count($riwayat) ?></span> baris<?= $fKelasRiwayat > 0 ? ' (difilter kelas ' . e(kelas_nama($fKelasRiwayat)) . ')' : '' ?>.
          Baris yang dibatalkan tetap tampil sebagai jejak.</p>
      </div>
      <form method="get" class="flex">
        <input type="hidden" name="kelas_asal" value="<?= (int) $fKelasAsal ?>">
        <select name="riwayat_kelas" data-auto style="min-width:190px">
          <option value="">Semua kelas</option>
          <?php foreach ($kelasSemua as $k): ?>
            <option value="<?= (int) $k['id'] ?>" <?= $fKelasRiwayat === (int) $k['id'] ? 'selected' : '' ?>><?= e((string) $k['nama']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($fKelasRiwayat > 0): ?><a class="btn btn-ghost btn-sm" href="kenaikan.php?kelas_asal=<?= (int) $fKelasAsal ?>">Reset</a><?php endif; ?>
      </form>
    </div>

    <?php if (!$riwayat): ?>
      <div class="empty">
        <?= ikon('report', 34) ?>
        <h3>Belum ada riwayat</h3>
        <p>Riwayat terisi otomatis setiap kali Anda memproses kenaikan kelas atau kelulusan siswa.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl" id="tblRiwayatKenaikan">
          <thead>
            <tr><th>Tanggal</th><th>Siswa</th><th>Dari Kelas</th><th>Ke Kelas</th>
              <th class="ctr">Tindakan</th><th>Tahun Ajaran</th><th>Diproses oleh</th><th>Catatan</th><th class="ctr">Status</th><th></th></tr>
          </thead>
          <tbody>
          <?php foreach ($riwayat as $r):
              $lulus = (string) $r['jenis'] === 'lulus';
              $dibatalkan = !empty($r['dibatalkan']);
          ?>
            <tr>
              <td class="nowrap"><?= waktu_id((string) $r['dibuat']) ?></td>
              <td><b><?= e((string) ($r['siswa_nama'] ?: '(siswa dihapus)')) ?></b>
                <?php if (trim((string) ($r['nis'] ?? '')) !== ''): ?><span class="tbl-sub">NIS <?= e((string) $r['nis']) ?></span><?php endif; ?></td>
              <td><?= e((string) ($r['dari_kelas'] ?: '-')) ?></td>
              <td><?= e((string) ($r['ke_kelas'] ?: '-')) ?></td>
              <td class="ctr"><span class="pill <?= $lulus ? 'pill-A' : 'pill-B' ?>"><?= e($lulus ? 'Lulus' : 'Naik kelas') ?></span></td>
              <td class="sm"><?= e((string) ($r['ta_nama'] ?: '-')) ?></td>
              <td class="sm"><?= e((string) ($r['diproses_nama'] ?: '-')) ?></td>
              <td class="sm mut"><?= e((string) ($r['catatan'] ?: '-')) ?></td>
              <td class="ctr">
                <?php if ($dibatalkan): ?>
                  <span class="pill pill-gray">Dibatalkan</span>
                  <span class="tbl-sub"><?= waktu_id((string) $r['dibatalkan']) ?></span>
                <?php else: ?>
                  <span class="pill pill-ok">Berlaku</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!$dibatalkan): ?>
                  <form method="post" data-confirm="Batalkan proses ini dan kembalikan kelas/status siswa seperti semula?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="batalkan">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('switch', 14) ?> Batalkan</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="legend mt16">
        <span><b>Naik kelas</b> = siswa dipindahkan ke kelas tujuan</span>
        <span><b>Lulus</b> = status siswa menjadi lulus &amp; tidak lagi aktif</span>
        <span><b>Dibatalkan</b> = kelas/status siswa sudah dikembalikan</span>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<script>
(function () {
  var kotak = document.querySelectorAll('.pilih-siswa');
  if (!kotak.length) return;
  var info = document.getElementById('jmlPilih');

  function segarkan() {
    var n = 0;
    kotak.forEach(function (c) { if (c.checked) n++; });
    if (info) info.textContent = n;
  }

  kotak.forEach(function (c) { c.addEventListener('change', segarkan); });
  var semua = document.getElementById('btnPilihSemua');
  var kosong = document.getElementById('btnKosongkan');
  if (semua) semua.addEventListener('click', function () { kotak.forEach(function (c) { c.checked = true; }); segarkan(); });
  if (kosong) kosong.addEventListener('click', function () { kotak.forEach(function (c) { c.checked = false; }); segarkan(); });

  // Kelas tujuan dimatikan bila tindakannya "Luluskan".
  var jenis = document.getElementById('jenis');
  var tujuan = document.getElementById('kelas_tujuan');
  function cekJenis() {
    if (!jenis || !tujuan) return;
    var lulus = jenis.value === 'lulus';
    tujuan.disabled = lulus;
    tujuan.required = !lulus;
    tujuan.style.opacity = lulus ? '.55' : '1';
  }
  if (jenis) { jenis.addEventListener('change', cekJenis); cekJenis(); }
  segarkan();
})();
</script>
<?php
page_bottom();
