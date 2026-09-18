<?php
/**
 * Daftar Nilai Siswa — Kurikulum Merdeka.
 *
 * Kolom: Nilai Formatif (penilaian proses, TIDAK dijumlahkan), Sumatif Lingkup Materi,
 * dan Sumatif Akhir Semester (ASAS). Nilai akhir = rata-rata Sumatif Lingkup Materi +
 * ASAS dengan pembobotan yang diatur pada Pengaturan (bobot ASAS, sisanya untuk SLM).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_sekolah(); // super admin tanpa konteks dialihkan ke Kelola Sekolah
$bolehIsi = boleh_transaksi();
$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];

if (!$ta || !$sem) {
    page_top('Daftar Nilai Siswa');
    ?>
    <div class="card">
      <div class="empty">
        <?= ikon('calendar', 34) ?>
        <h3>Tahun ajaran / semester belum tersedia</h3>
        <p>Nilai disimpan per tahun ajaran &amp; semester. Atur periodenya terlebih dahulu.</p>
        <a class="btn btn-primary" href="tahun_ajaran.php"><?= ikon('calendar', 16) ?> Atur Tahun Ajaran &amp; Semester</a>
      </div>
    </div>
    <?php
    page_bottom();
    exit;
}

$jf = jumlah_formatif();
$js = jumlah_sumatif();
$bobotAsas = bobot_asas();

// ---------------------------------------------------------------- simpan nilai
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$bolehIsi) {
        tolak403('Pengisian nilai hanya untuk Super Admin, Admin Operator, dan Guru.');
    }
    csrf_guard();
    $kelasId = (int) ($_POST['kelas'] ?? 0);
    $mapelId = (int) ($_POST['mapel'] ?? 0);
    if (!boleh_kelas($kelasId)) {
        tolak403('Kelas itu tidak Anda ampu.');
    }
    if (!boleh_mapel($mapelId)) {
        tolak403('Mata pelajaran itu tidak Anda ampu.');
    }
    if (!kelas_by_id($kelasId) || !mapel_by_id($mapelId)) {
        flash('Kelas atau mata pelajaran tidak valid.', 'err');
        redirect('nilai.php');
    }

    $daftarSiswa = siswa_kelas($kelasId, true);
    $now = date('Y-m-d H:i:s');
    $jmlTersimpan = 0;

    db()->exec('BEGIN IMMEDIATE');
    try {
        foreach ($daftarSiswa as $s) {
            $sid = (int) $s['id'];
            $peta = [
                'formatif' => is_array($_POST['nf'][$sid] ?? null) ? $_POST['nf'][$sid] : [],
                'sumatif'  => is_array($_POST['ns'][$sid] ?? null) ? $_POST['ns'][$sid] : [],
                'asas'     => [1 => $_POST['na'][$sid] ?? null],
            ];
            foreach ($peta as $jenis => $nilaiArr) {
                foreach ($nilaiArr as $kolom => $raw) {
                    $kolom = (int) $kolom;
                    if ($kolom < 1) {
                        continue;
                    }
                    $angka = nilai_sah($raw);
                    $argsBase = [$sid, $kelasId, $mapelId, (int) $ta['id'], (int) $sem['id'], $jenis, $kolom];
                    if ($angka === null) {
                        q('DELETE FROM nilai WHERE siswa_id = ? AND kelas_id = ? AND mapel_id = ? AND ta_id = ? AND semester_id = ? AND jenis = ? AND kolom = ? AND sekolah_id = ?',
                            array_merge($argsBase, [sekolah_id()]));
                        continue;
                    }
                    q('INSERT INTO nilai (sekolah_id, siswa_id, kelas_id, mapel_id, ta_id, semester_id, jenis, kolom, nilai, diubah)
                       VALUES (?,?,?,?,?,?,?,?,?,?)
                       ON CONFLICT(siswa_id, kelas_id, mapel_id, ta_id, semester_id, jenis, kolom)
                       DO UPDATE SET nilai = excluded.nilai, diubah = excluded.diubah',
                        [sekolah_id(), $sid, $kelasId, $mapelId, (int) $ta['id'], (int) $sem['id'], $jenis, $kolom, $angka, $now]);
                    $jmlTersimpan++;
                }
            }
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    flash('Nilai ' . kelas_nama($kelasId) . ' — ' . mapel_nama($mapelId) . ' disimpan (' . $jmlTersimpan . ' angka). Nilai akhir dihitung dari ' . r0(bobot_sumatif()) . '% Sumatif Lingkup Materi + ' . r0($bobotAsas) . '% ASAS.');
    redirect('nilai.php' . ajaran_qs($ta, $sem) . '&kelas=' . $kelasId . '&mapel=' . $mapelId);
}

$fKelas = (int) ($_GET['kelas'] ?? 0);
if ($fKelas > 0 && !boleh_kelas($fKelas)) {
    tolak403('Kelas itu tidak Anda ampu.');
}
$fMapel = (int) ($_GET['mapel'] ?? 0);
if ($fMapel > 0 && !boleh_mapel($fMapel)) {
    tolak403('Mata pelajaran itu tidak Anda ampu.');
}

$daftarKelas = kelas_pilih();
$daftarMapel = mapel_pilih();
$pilihLengkap = $fKelas > 0 && $fMapel > 0 && kelas_by_id($fKelas) && mapel_by_id($fMapel);

$siswa = [];
$nilai = [];
if ($pilihLengkap) {
    $siswa = siswa_kelas($fKelas, true);
    $nilai = nilai_map($fKelas, $fMapel, (int) $ta['id'], (int) $sem['id']);
}

page_top('Daftar Nilai Siswa');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2>Pilih Kelas &amp; Mata Pelajaran</h2>
      <p>Tahun ajaran <b><?= e(ta_nama($ta)) ?></b> &middot; Semester <b><?= e(sem_nama($sem)) ?></b></p>
    </div>
    <div class="flex">
      <?php if (count(ta_list()) > 1): ?>
        <form method="get" class="flex">
          <input type="hidden" name="kelas" value="<?= (int) $fKelas ?>">
          <input type="hidden" name="mapel" value="<?= (int) $fMapel ?>">
          <select name="ta" data-auto>
            <?php foreach (ta_list() as $t): ?>
              <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === (int) $ta['id'] ? 'selected' : '' ?>>T.A. <?= e((string) $t['nama']) ?></option>
            <?php endforeach; ?>
          </select>
          <select name="sem" data-auto>
            <?php foreach (semester_list((int) $ta['id']) as $sm): ?>
              <option value="<?= (int) $sm['id'] ?>" <?= (int) $sm['id'] === (int) $sem['id'] ? 'selected' : '' ?>>Semester <?= e((string) $sm['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
      <?php if ($pilihLengkap): ?>
        <a class="btn btn-ghost" href="cetak_nilai.php?<?= e(http_build_query(['kelas' => $fKelas, 'mapel' => $fMapel, 'ta' => (int) $ta['id'], 'sem' => (int) $sem['id']])) ?>" target="_blank"><?= ikon('print', 16) ?> Cetak Daftar Nilai</a>
        <a class="btn btn-ghost" href="nilai_export.php?<?= e(http_build_query(['kelas' => $fKelas, 'mapel' => $fMapel, 'ta' => (int) $ta['id'], 'sem' => (int) $sem['id']])) ?>"><?= ikon('doc', 16) ?> Unduh CSV</a>
      <?php endif; ?>
    </div>
  </div>

  <form class="filterbar" method="get">
    <input type="hidden" name="ta" value="<?= (int) $ta['id'] ?>">
    <input type="hidden" name="sem" value="<?= (int) $sem['id'] ?>">
    <div>
      <label class="f" for="kelas">Kelas</label>
      <select id="kelas" name="kelas" data-auto>
        <option value="">— pilih kelas —</option>
        <?php foreach ($daftarKelas as $k): ?>
          <option value="<?= (int) $k['id'] ?>" <?= $fKelas === (int) $k['id'] ? 'selected' : '' ?>><?= e((string) $k['nama']) ?> (<?= (int) $k['jml_siswa'] ?> siswa)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="f" for="mapel">Mata Pelajaran</label>
      <select id="mapel" name="mapel" data-auto>
        <option value="">— pilih mata pelajaran —</option>
        <?php foreach ($daftarMapel as $m): ?>
          <option value="<?= (int) $m['id'] ?>" <?= $fMapel === (int) $m['id'] ? 'selected' : '' ?>><?= e((string) $m['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary" type="submit"><?= ikon('chart', 16) ?> Tampilkan Daftar Nilai</button>
  </form>
  <?php if (!$daftarKelas || !$daftarMapel): ?>
    <p class="hint"><?= !$daftarKelas ? 'Belum ada kelas yang dapat dipilih. ' : '' ?><?= !$daftarMapel ? 'Belum ada mata pelajaran yang dapat dipilih.' : '' ?></p>
  <?php endif; ?>
</div>

<?php if (!$pilihLengkap && is_guru() && ($daftarKelas === [] || $daftarMapel === [])): ?>
  <?= kartu_tanpa_penugasan('Anda belum ditugaskan ke kelas atau mata pelajaran',
      'Daftar nilai diisi untuk kelas yang Anda ajar dan mata pelajaran yang Anda pegang.') ?>
<?php elseif (!$pilihLengkap): ?>
  <div class="card">
    <div class="empty">
      <?= ikon('chart', 34) ?>
      <h3>Belum ada kelas/mata pelajaran yang dipilih</h3>
      <p>Pilih kelas dan mata pelajaran di atas untuk mulai mengisi nilai.</p>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="card-head">
      <div>
        <h2><?= e(kelas_nama($fKelas)) ?> &middot; <?= e(mapel_nama($fMapel)) ?></h2>
        <p><?= count($siswa) ?> siswa &middot; <?= $jf ?> kolom Formatif &middot; <?= $js ?> kolom Sumatif Lingkup Materi &middot; 1 kolom ASAS</p>
      </div>
      <span class="chip" id="nilaiStat">Menghitung…</span>
    </div>

    <div class="legend mb16">
      <span><i style="background:rgba(79,70,229,.5)"></i>Formatif — penilaian proses, <b>tidak</b> dihitung ke nilai akhir</span>
      <span><i style="background:rgba(14,165,233,.5)"></i>Sumatif Lingkup Materi — bobot <?= e(r0(bobot_sumatif())) ?>%</span>
      <span><i style="background:rgba(236,72,153,.5)"></i>ASAS — bobot <?= e(r0($bobotAsas)) ?>%</span>
    </div>

    <?php if (!$siswa): ?>
      <div class="empty">
        <?= ikon('student', 34) ?>
        <h3>Belum ada siswa pada kelas ini</h3>
        <p>Tambahkan data siswa terlebih dahulu sebelum mengisi nilai.</p>
        <?php if (boleh_master()): ?><a class="btn btn-primary" href="siswa_edit.php?kelas=<?= (int) $fKelas ?>"><?= ikon('plus', 16) ?> Tambah Siswa</a><?php endif; ?>
      </div>
    <?php else: ?>
      <form method="post" id="formNilai">
        <?= csrf_field() ?>
        <input type="hidden" name="kelas" value="<?= (int) $fKelas ?>">
        <input type="hidden" name="mapel" value="<?= (int) $fMapel ?>">
        <?php
        // Lebar kolom angka dinyatakan eksplisit (kelas c-nilai/c-na/c-pred) dan kolom Nama
        // dibiarkan lentur, sehingga judul kolom (F1..Fn, S1..Sn, ASAS, Nilai Akhir, Predikat)
        // selalu persis di atas kotak isiannya; tabel menggeser (scroll) bila kolomnya banyak.
        $wNo = 40; $wNama = 260; $wNilai = 58; $wNA = 92; $wPred = 76;
        $lebarTabel = $wNo + $wNama + ($jf + $js + 1) * $wNilai + $wNA + $wPred;
        ?>
        <div class="nil-wrap">
          <table class="grid" id="tblNilai" style="width:<?= $lebarTabel ?>px">
            <colgroup>
              <col style="width:<?= $wNo ?>px">
              <col style="width:<?= $wNama ?>px">
              <?php for ($k = 1; $k <= $jf; $k++): ?><col style="width:<?= $wNilai ?>px"><?php endfor; ?>
              <?php for ($k = 1; $k <= $js; $k++): ?><col style="width:<?= $wNilai ?>px"><?php endfor; ?>
              <col style="width:<?= $wNilai ?>px">
              <col style="width:<?= $wNA ?>px">
              <col style="width:<?= $wPred ?>px">
            </colgroup>
            <thead>
              <tr>
                <th rowspan="2" class="kiri c-no">No</th>
                <th rowspan="2" class="kiri c-nama">Nama Siswa</th>
                <th colspan="<?= $jf ?>" class="grp-fmt">Formatif<?= $jf > 1 ? ' 1-' . $jf : '' ?></th>
                <th colspan="<?= $js ?>" class="grp-smt">Sumatif Lingkup Materi<?= $js > 1 ? ' 1-' . $js : '' ?></th>
                <th rowspan="2" class="grp-asas c-nilai">ASAS</th>
                <th rowspan="2" class="grp-na c-na">Nilai Akhir</th>
                <th rowspan="2" class="c-pred">Predikat</th>
              </tr>
              <tr class="sub">
                <?php for ($k = 1; $k <= $jf; $k++): ?><th class="grp-fmt c-nilai<?= $k === 1 ? ' awal-grup' : '' ?>">F<?= $k ?></th><?php endfor; ?>
                <?php for ($k = 1; $k <= $js; $k++): ?><th class="grp-smt c-nilai<?= $k === 1 ? ' awal-grup' : '' ?>">S<?= $k ?></th><?php endfor; ?>
              </tr>
            </thead>
            <tbody>
            <?php
            $rataKolom = ['formatif' => array_fill(1, $jf, []), 'sumatif' => array_fill(1, $js, []), 'asas' => []];
            $nilaiAkhirSemua = [];
            foreach ($siswa as $i => $s):
                $sid = (int) $s['id'];
                $d = $nilai[$sid] ?? ['formatif' => [], 'sumatif' => [], 'asas' => null];
                $sumArr = [];
                for ($k = 1; $k <= $js; $k++) {
                    $v = $d['sumatif'][$k] ?? null;
                    $sumArr[$k] = $v;
                    if ($v !== null) {
                        $rataKolom['sumatif'][$k][] = (float) $v;
                    }
                }
                $asas = $d['asas'];
                if ($asas !== null) {
                    $rataKolom['asas'][] = (float) $asas;
                }
                $akhir = nilai_akhir($sumArr, $asas);
                $pred = predikat_dari($akhir);
                if ($akhir !== null) {
                    $nilaiAkhirSemua[] = $akhir;
                }
            ?>
              <tr data-siswa="<?= $sid ?>">
                <td class="c-no"><?= $i + 1 ?></td>
                <td class="kiri c-nama" title="<?= e((string) $s['nama']) ?>"><b><?= e((string) $s['nama']) ?></b><?php if ($s['nis']): ?><span class="tbl-sub">NIS <?= e((string) $s['nis']) ?></span><?php endif; ?></td>
                <?php for ($k = 1; $k <= $jf; $k++): $v = $d['formatif'][$k] ?? null; if ($v !== null) { $rataKolom['formatif'][$k][] = (float) $v; } ?>
                  <td class="sel-nilai c-nilai<?= $k === 1 ? ' awal-grup' : '' ?>"><input class="nil" type="number" step="0.01" min="0" max="100" data-jenis="formatif" name="nf[<?= $sid ?>][<?= $k ?>]" value="<?= e(num_input($v)) ?>" aria-label="Formatif <?= $k ?> - <?= e((string) $s['nama']) ?>" <?= $bolehIsi ? '' : 'disabled' ?>></td>
                <?php endfor; ?>
                <?php for ($k = 1; $k <= $js; $k++): $v = $d['sumatif'][$k] ?? null; ?>
                  <td class="sel-nilai c-nilai<?= $k === 1 ? ' awal-grup' : '' ?>"><input class="nil" type="number" step="0.01" min="0" max="100" data-jenis="sumatif" name="ns[<?= $sid ?>][<?= $k ?>]" value="<?= e(num_input($v)) ?>" aria-label="Sumatif Lingkup Materi <?= $k ?> - <?= e((string) $s['nama']) ?>" <?= $bolehIsi ? '' : 'disabled' ?>></td>
                <?php endfor; ?>
                <td class="sel-nilai c-nilai awal-grup"><input class="nil" type="number" step="0.01" min="0" max="100" data-jenis="asas" name="na[<?= $sid ?>]" value="<?= e(num_input($asas)) ?>" aria-label="ASAS - <?= e((string) $s['nama']) ?>" <?= $bolehIsi ? '' : 'disabled' ?>></td>
                <td class="na c-na awal-grup<?= $akhir === null ? ' belum' : '' ?>"><?= $akhir === null ? '—' : e(r1($akhir)) ?></td>
                <td class="predikat c-pred awal-grup"><?= $akhir === null ? '<span class="mut xs">-</span>' : '<span class="pill pill-' . e($pred) . '">' . e($pred) . '</span>' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <td class="c-no"></td>
                <td class="kiri c-nama">Rata-rata kelas</td>
                <?php for ($k = 1; $k <= $jf; $k++): $ra = rata($rataKolom['formatif'][$k]); ?>
                  <td class="c-nilai<?= $k === 1 ? ' awal-grup' : '' ?>"><?= $ra === null ? '-' : e(r1($ra)) ?></td>
                <?php endfor; ?>
                <?php for ($k = 1; $k <= $js; $k++): $ra = rata($rataKolom['sumatif'][$k]); ?>
                  <td class="c-nilai<?= $k === 1 ? ' awal-grup' : '' ?>"><?= $ra === null ? '-' : e(r1($ra)) ?></td>
                <?php endfor; ?>
                <td class="c-nilai awal-grup"><?= ($ra = rata($rataKolom['asas'])) === null ? '-' : e(r1($ra)) ?></td>
                <td class="na c-na awal-grup"><?= ($ra = rata($nilaiAkhirSemua)) === null ? '—' : e(r1($ra)) ?></td>
                <td class="c-pred awal-grup"><?= $ra === null ? '-' : '<span class="pill pill-' . e(predikat_dari($ra)) . '">' . e(predikat_dari($ra)) . '</span>' ?></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <div class="alert alert-ok mt16" style="margin-bottom:0">
          <?= ikon('chart', 16) ?>
          <span><b>Rumus nilai akhir:</b> (rata-rata Sumatif Lingkup Materi &times; <?= e(r0(bobot_sumatif())) ?>%) + (ASAS &times; <?= e(r0($bobotAsas)) ?>%).
          Kolom Formatif hanya untuk penilaian proses sehingga tidak dijumlahkan. Kosongkan sebuah kolom bila belum ada nilainya — angka yang dikosongkan akan dihapus.</span>
        </div>

        <?php if ($bolehIsi): ?>
          <div class="form-actions mt16">
            <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Nilai</button>
            <span class="xs mut">Nilai akhir &amp; predikat pada kolom kanan dihitung ulang saat Anda mengetik.</span>
          </div>
        <?php else: ?>
          <div class="alert alert-warn mt16" style="margin-bottom:0">
            <?= ikon('eye', 16) ?>
            <span>Anda masuk sebagai <?= e(role_label()) ?> — daftar nilai dapat dilihat dan dicetak, namun pengisian dilakukan Admin Operator atau Guru pengampu.</span>
          </div>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
<script>
window.EG_BOBOT = <?= (float) $bobotAsas ?>;
window.EG_TH = { a: <?= (float) thresholds()['batas_a'] ?>, b: <?= (float) thresholds()['batas_b'] ?>, c: <?= (float) thresholds()['batas_c'] ?> };
</script>
<?php
page_bottom();
