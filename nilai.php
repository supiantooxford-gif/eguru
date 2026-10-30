<?php
/**
 * PENILAIAN SISWA — Kurikulum Merdeka.
 *
 * Kolom FORMATIF (penilaian proses, TIDAK dijumlahkan) menerima **angka, huruf, atau tanda centang**
 * (permintaan pemilik, v1.36.0) lewat isian teks + daftar saran ✓/A/B/C/D; kolom Sumatif Lingkup
 * Materi & ASAS tetap ANGKA karena dipakai perhitungan nilai akhir.
 *
 * Kolom: Nilai Formatif (penilaian proses, TIDAK dijumlahkan), Sumatif Lingkup Materi,
 * dan Sumatif Akhir Semester (ASAS). Nilai akhir = rata-rata Sumatif Lingkup Materi +
 * ASAS dengan pembobotan (bobot ASAS, sisanya untuk SLM).
 *
 * PENGATURAN: selain pengaturan SEKOLAH (Identitas & Pengaturan → kepala/operator), **guru dapat
 * mengatur sendiri** jumlah kolom Formatif/Sumatif, bobot ASAS, dan ambang predikat A/B/C/D untuk
 * tiap MATA PELAJARAN yang diampu (tombol "Pengaturan Penilaian"). Pengaturan guru menimpa
 * pengaturan sekolah untuk mapel itu; bila guru belum mengaturnya, pengaturan sekolah yang dipakai
 * (lihat nilai_konteks() di inc/helpers.php).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_sekolah(); // super admin tanpa konteks dialihkan ke Kelola Sekolah
// Permintaan pemilik (v1.21.0): Admin Operator hanya melihat & mencetak daftar nilai.
$bolehIsi = boleh_isi_administrasi();
$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];

if (!$ta || !$sem) {
    page_top('Penilaian Siswa');
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

// Pengaturan nilai awal (sebelum mapel dipilih) → mengikuti pengaturan SEKOLAH.
$konteksNilai = nilai_konteks(0, 0);
$jf = (int) $konteksNilai['jf'];
$js = (int) $konteksNilai['js'];
$bobotAsas = (float) $konteksNilai['bobot_asas'];

// ---------------------------------------------------------------- simpan PENGATURAN PENILAIAN
// Permintaan pemilik: "Pengaturan kolom formatif, sumatif dan bobot asas tambahkan ke akun guru
// untuk diseting sendiri. Begitu juga Pengaturan Bobot nilai dari A, B, C, D di seting sendiri oleh
// guru." → disetel PER MATA PELAJARAN YANG DIAMPU; guru lain/kelas lain tidak terpengaruh.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['aksi'] ?? '') === 'simpan_pengaturan') {
    csrf_guard();
    if (!is_guru()) {
        tolak403('Pengaturan penilaian ini hanya untuk akun guru (pengaturan sekolah diatur pada menu Identitas & Pengaturan).');
    }
    $mapelId = (int) ($_POST['mapel'] ?? 0);
    if ($mapelId <= 0 || !boleh_mapel($mapelId)) {
        tolak403('Mata pelajaran itu tidak Anda ampu.');
    }
    $guruId = guru_id_saya();
    if ($guruId <= 0) {
        flash('Akun Anda belum ditautkan ke data guru, jadi pengaturan penilaian belum dapat disimpan.', 'err');
        redirect('nilai.php');
    }
    $ambil = static function (string $k, float $bawaan, float $min, float $maks) : float {
        $v = str_replace(',', '.', trim((string) ($_POST[$k] ?? '')));
        if ($v === '' || !is_numeric($v)) {
            return $bawaan;
        }
        return max($min, min($maks, (float) $v));
    };
    $batasA = $ambil('batas_a', 90, 0, 100);
    $batasB = $ambil('batas_b', 80, 0, 100);
    $batasC = $ambil('batas_c', 70, 0, 100);
    // Kembalikan ke tampilan yang sama (mapel + kelas yang sedang dibuka).
    $kelasKembali = (int) ($_POST['kelas'] ?? 0);
    if ($kelasKembali > 0 && !boleh_kelas($kelasKembali)) {
        $kelasKembali = 0;
    }
    $kembali = 'nilai.php' . ajaran_qs($ta, $sem) . '&mapel=' . $mapelId
        . ($kelasKembali > 0 ? '&kelas=' . $kelasKembali : '') . '#cardPengaturanNilai';
    if (!($batasA > $batasB && $batasB > $batasC)) {
        flash('Ambang predikat tidak valid. Pastikan A > B > C dan berada pada rentang 0-100.', 'err');
        redirect($kembali);
    }
    nilai_pengaturan_simpan($guruId, $mapelId, [
        'jf'         => (int) $ambil('jumlah_formatif', 4, 1, 12),
        'js'         => (int) $ambil('jumlah_sumatif', 4, 1, 12),
        'bobot_asas' => $ambil('bobot_asas', 50, 0, 100),
        'batas_a'    => $batasA,
        'batas_b'    => $batasB,
        'batas_c'    => $batasC,
    ]);
    flash('Pengaturan penilaian untuk mata pelajaran ' . mapel_nama($mapelId) . ' disimpan. '
        . 'Pengaturan ini berlaku pada daftar nilai, lembar cetak, dan ekspor Anda untuk mapel tersebut.');
    redirect($kembali);
}

// ---------------------------------------------------------------- hapus PENGATURAN PENILAIAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['aksi'] ?? '') === 'hapus_pengaturan') {
    csrf_guard();
    if (!is_guru()) {
        tolak403('Pengaturan penilaian ini hanya untuk akun guru.');
    }
    $mapelId = (int) ($_POST['mapel'] ?? 0);
    if ($mapelId <= 0 || !boleh_mapel($mapelId)) {
        tolak403('Mata pelajaran itu tidak Anda ampu.');
    }
    nilai_pengaturan_hapus(guru_id_saya(), $mapelId);
    flash('Pengaturan penilaian ' . mapel_nama($mapelId) . ' dikembalikan ke pengaturan sekolah.', 'warn');
    $kelasKembali = (int) ($_POST['kelas'] ?? 0);
    if ($kelasKembali > 0 && !boleh_kelas($kelasKembali)) {
        $kelasKembali = 0;
    }
    redirect('nilai.php' . ajaran_qs($ta, $sem) . '&mapel=' . $mapelId
        . ($kelasKembali > 0 ? '&kelas=' . $kelasKembali : '') . '#cardPengaturanNilai');
}

// ---------------------------------------------------------------- simpan nilai
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$bolehIsi) {
        tolak403('Akun Admin Operator hanya dapat melihat dan mencetak daftar nilai. Pengisiannya '
            . 'dilakukan guru pengampu mata pelajaran.');
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

    $konteksSimpan = nilai_konteks($mapelId, $kelasId);
    $daftarSiswa = siswa_kelas($kelasId, true);
    $now = date('Y-m-d H:i:s');
    $jmlTersimpan = 0;
    $jmlTeksFormatif = 0; // sel Formatif yang berisi huruf/tanda centang (bukan angka)

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
                    // Permintaan pemilik (v1.36.0): kolom FORMATIF boleh berisi angka, huruf, atau
                    // tanda centang; kolom Sumatif/ASAS tetap angka saja.
                    $simpan = $jenis === 'formatif' ? nilai_formatif_sah($raw) : nilai_sah($raw);
                    $argsBase = [$sid, $kelasId, $mapelId, (int) $ta['id'], (int) $sem['id'], $jenis, $kolom];
                    if ($simpan === null) {
                        q('DELETE FROM nilai WHERE siswa_id = ? AND kelas_id = ? AND mapel_id = ? AND ta_id = ? AND semester_id = ? AND jenis = ? AND kolom = ? AND sekolah_id = ?',
                            array_merge($argsBase, [sekolah_id()]));
                        continue;
                    }
                    q('INSERT INTO nilai (sekolah_id, siswa_id, kelas_id, mapel_id, ta_id, semester_id, jenis, kolom, nilai, diubah)
                       VALUES (?,?,?,?,?,?,?,?,?,?)
                       ON CONFLICT(siswa_id, kelas_id, mapel_id, ta_id, semester_id, jenis, kolom)
                       DO UPDATE SET nilai = excluded.nilai, diubah = excluded.diubah',
                        [sekolah_id(), $sid, $kelasId, $mapelId, (int) $ta['id'], (int) $sem['id'], $jenis, $kolom, $simpan, $now]);
                    $jmlTersimpan++;
                    if ($jenis === 'formatif' && !nilai_angka_ya($simpan)) {
                        $jmlTeksFormatif++;
                    }
                }
            }
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    flash('Nilai ' . kelas_nama($kelasId) . ' — ' . mapel_nama($mapelId) . ' disimpan (' . $jmlTersimpan
        . ' sel terisi' . ($jmlTeksFormatif > 0 ? ', termasuk ' . $jmlTeksFormatif . ' sel Formatif berisi huruf/centang' : '') . '). Nilai akhir dihitung dari ' . r0(100.0 - (float) $konteksSimpan['bobot_asas']) . '% Sumatif Lingkup Materi + ' . r0((float) $konteksSimpan['bobot_asas']) . '% ASAS.');
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

// Pengaturan yang BERLAKU: pengaturan guru untuk mapel ini bila sudah dibuat, kalau belum → sekolah.
$konteksNilai = nilai_konteks($fMapel, $fKelas);
$jf = (int) $konteksNilai['jf'];
$js = (int) $konteksNilai['js'];
$bobotAsas = (float) $konteksNilai['bobot_asas'];
// Guru hanya boleh mengatur penilaian untuk MATA PELAJARAN yang diampu (halaman ini sudah
// memastikan mapel terpilih memang ampuannya lewat boleh_mapel()).
$bolehAturNilai = is_guru() && $fMapel > 0 && $konteksNilai['guru_id'] > 0;

$siswa = [];
$nilai = [];
if ($pilihLengkap) {
    $siswa = siswa_kelas($fKelas, true);
    $nilai = nilai_map($fKelas, $fMapel, (int) $ta['id'], (int) $sem['id']);
}

page_top('Penilaian Siswa');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('chart', 16) ?> Penilaian Siswa</h2>
      <p>Pilih kelas &amp; mata pelajaran yang Anda ampu. Tahun ajaran
        <b><?= e(ta_nama($ta)) ?></b> &middot; Semester <b><?= e(sem_nama($sem)) ?></b></p>
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
      <?php if ($bolehAturNilai): ?>
        <?php // Permintaan pemilik: guru menyetel sendiri kolom Formatif/Sumatif, bobot ASAS, dan
              // ambang predikat A/B/C/D untuk mata pelajaran yang diampu (form di halaman ini). ?>
        <a class="btn btn-ghost" href="#cardPengaturanNilai" id="btnPengaturanNilai" data-set-nilai><?= ikon('slider', 16) ?> Pengaturan Penilaian</a>
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

<?php // ============================================================ PENGATURAN PENILAIAN (guru)
// Permintaan pemilik: "Pengaturan kolom formatif, sumatif dan bobot asas tambahkan ke akun guru
// untuk diseting sendiri. Begitu juga Pengaturan Bobot nilai dari A, B, C, D di seting sendiri oleh
// guru." → PER MATA PELAJARAN yang diampu; pengaturan guru menimpa pengaturan sekolah untuk mapel
// itu, dan bila guru belum mengaturnya, pengaturan sekolah yang dipakai.
// ⚠️ Kartu ini WAJIB berada DI LUAR <form id="formNilai"> (HTML tidak mengizinkan form bersarang).
$pengaturanGuru = $konteksNilai['guru_id'] > 0 ? nilai_pengaturan_guru((int) $konteksNilai['guru_id'], $fMapel) : null;
?>
<?php if ($bolehAturNilai): ?>
<div class="card" id="cardPengaturanNilai">
  <details class="set-nilai"<?= $pengaturanGuru ? ' open' : '' ?>>
    <summary class="btn btn-ghost"><?= ikon('slider', 16) ?> Pengaturan Penilaian</summary>
    <div class="set-nilai-isi">
      <p class="hint">
        Atur <b>jumlah kolom Formatif &amp; Sumatif</b>, <b>bobot ASAS</b>, dan <b>ambang predikat
        A/B/C/D</b> untuk mata pelajaran <b><?= e(mapel_nama($fMapel)) ?></b>. Pengaturan ini berlaku
        pada daftar nilai, lembar cetak, dan ekspor <b>Anda</b> untuk mapel tersebut — guru lain dan
        mapel lain tidak terpengaruh.
        <?php if ($pengaturanGuru): ?>
          <b>Sedang dipakai: pengaturan Anda sendiri.</b>
        <?php else: ?>
          Anda belum mengatur mapel ini, jadi yang dipakai adalah <b>pengaturan sekolah</b>.
        <?php endif; ?>
      </p>
      <form method="post" id="formSetNilai">
        <?= csrf_field() ?>
        <input type="hidden" name="mapel" value="<?= (int) $fMapel ?>">
        <input type="hidden" name="kelas" value="<?= (int) $fKelas ?>">
        <div class="grid g3" style="gap:16px">
          <div>
            <label class="f" for="setFormatif">Jumlah kolom Formatif</label>
            <input type="number" id="setFormatif" name="jumlah_formatif" min="1" max="12"
                   value="<?= (int) $konteksNilai['jf'] ?>">
            <div class="hint">1–12 kolom. Formatif = penilaian proses (tidak dijumlahkan).</div>
          </div>
          <div>
            <label class="f" for="setSumatif">Jumlah kolom Sumatif Lingkup Materi</label>
            <input type="number" id="setSumatif" name="jumlah_sumatif" min="1" max="12"
                   value="<?= (int) $konteksNilai['js'] ?>">
            <div class="hint">1–12 kolom. Nilai akhir memakai rata-rata kolom ini.
              Mengurangi jumlah kolom hanya <b>menyembunyikan</b> kolom selebihnya (angkanya tidak
              dihapus) — bila jumlahnya dinaikkan lagi, nilai lama muncul kembali.</div>
          </div>
          <div>
            <label class="f" for="setBobot">Bobot ASAS (%)</label>
            <input type="number" id="setBobot" name="bobot_asas" min="0" max="100" step="1"
                   value="<?= e(r0((float) $konteksNilai['bobot_asas'])) ?>">
            <div class="hint">Sumatif Lingkup Materi otomatis <?= e(r0(100.0 - (float) $konteksNilai['bobot_asas'])) ?>%.</div>
          </div>
        </div>
        <div class="note-rumus mt16">
          <b>Rumus nilai akhir:</b> (rata-rata Sumatif Lingkup Materi &times; <?= e(r0(100.0 - (float) $konteksNilai['bobot_asas'])) ?>%)
          + (ASAS &times; <?= e(r0((float) $konteksNilai['bobot_asas'])) ?>%)
        </div>
        <div class="grid g3 mt16" style="gap:16px">
          <div>
            <label class="f" for="setA">Predikat A (Amat Baik) mulai</label>
            <input type="number" id="setA" name="batas_a" min="0" max="100" step="1" value="<?= e(r0((float) $konteksNilai['batas_a'])) ?>">
          </div>
          <div>
            <label class="f" for="setB">Predikat B (Baik) mulai</label>
            <input type="number" id="setB" name="batas_b" min="0" max="100" step="1" value="<?= e(r0((float) $konteksNilai['batas_b'])) ?>">
          </div>
          <div>
            <label class="f" for="setC">Predikat C (Cukup) mulai</label>
            <input type="number" id="setC" name="batas_c" min="0" max="100" step="1" value="<?= e(r0((float) $konteksNilai['batas_c'])) ?>">
          </div>
        </div>
        <p class="hint" style="margin-top:-6px">Di bawah ambang C otomatis berpredikat <b>D (Kurang)</b>.
          Pastikan A &gt; B &gt; C. Ambang ini dipakai pada daftar nilai, lembar cetak, dan ekspor Anda.</p>
        <div class="flex mt16">
          <button class="btn btn-primary" type="submit" name="aksi" value="simpan_pengaturan"><?= ikon('save', 16) ?> Simpan Pengaturan Penilaian</button>
          <?php if ($pengaturanGuru): ?>
            <?php // Tombol ini mengirim aksi=hapus_pengaturan (pengaturan guru dibuang → kembali ke sekolah). ?>
            <button class="btn btn-ghost" type="submit" name="aksi" value="hapus_pengaturan" formnovalidate><?= ikon('prev', 16) ?> Kembalikan ke Pengaturan Sekolah</button>
          <?php endif; ?>
        </div>
        <p class="hint" style="margin-bottom:0">
          Pengaturan <b>sekolah</b> saat ini (dipakai bila mapel ini belum Anda atur):
          <?= (int) jumlah_formatif() ?> kolom Formatif, <?= (int) jumlah_sumatif() ?> kolom Sumatif Lingkup Materi,
          bobot ASAS <?= e(r0(bobot_asas())) ?>%, predikat <?= tetap_kata(predikat_keterangan()) ?>.
          <?php if (is_super()): ?>Anda masuk sebagai Super Admin — pengaturan sekolah diatur pada menu <b>Identitas &amp; Pengaturan</b>.<?php endif; ?>
        </p>
      </form>
    </div>
  </details>
</div>
<?php endif; ?>

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
        <p><?= count($siswa) ?> siswa &middot; <?= $jf ?> kolom Formatif (angka/huruf/✓) &middot; <?= $js ?> kolom Sumatif Lingkup Materi &middot; 1 kolom ASAS</p>
      </div>
      <span class="chip" id="nilaiStat">Menghitung…</span>
    </div>

    <div class="legend mb16">
      <span><i style="background:rgba(79,70,229,.5)"></i>Formatif — penilaian proses, boleh <b>angka</b>, <b>huruf</b>, atau <b>✓</b>; <b>tidak</b> dihitung ke nilai akhir</span>
      <span><i style="background:rgba(14,165,233,.5)"></i>Sumatif Lingkup Materi — bobot <?= e(r0(100.0 - $bobotAsas)) ?>%</span>
      <span><i style="background:rgba(236,72,153,.5)"></i>ASAS — bobot <?= e(r0($bobotAsas)) ?>%</span>
    </div>

    <?php // Keterangan predikat (permintaan pemilik): A (Amat Baik), B (Baik), C (Cukup), D (Kurang). ?>
    <p class="hint mb16" id="ketPredikat">
      <?php // Ambang predikat mengikuti KONTEKS mapel ini (pengaturan guru bila ada, kalau tidak pengaturan sekolah). ?>
      <b>Predikat:</b> <?= tetap_kata(predikat_keterangan_k($konteksNilai)) ?>.
      <?php if (is_guru()): ?>
        Nilai ini diisi oleh guru mata pelajaran yang bersangkutan (<?= e(mapel_nama($fMapel)) ?>)
        untuk kelas yang diampu.
        <?= $konteksNilai['sumber'] === 'guru' ? 'Kolom &amp; ambang memakai <b>pengaturan Anda</b> untuk mapel ini.' : '' ?>
      <?php elseif ($konteksNilai['sumber'] === 'guru'): ?>
        Kolom &amp; ambang memakai <b>pengaturan guru pengampu</b><?= $konteksNilai['guru_nama'] !== '' ? ' (' . e($konteksNilai['guru_nama']) . ')' : '' ?> untuk mapel ini.
      <?php endif; ?>
    </p>

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
                $pred = predikat_dari_k($konteksNilai, $akhir);
                if ($akhir !== null) {
                    $nilaiAkhirSemua[] = $akhir;
                }
            ?>
              <tr data-siswa="<?= $sid ?>">
                <td class="c-no"><?= $i + 1 ?></td>
                <td class="kiri c-nama" title="<?= e((string) $s['nama']) ?>"><b><?= e((string) $s['nama']) ?></b><?php if ($s['nis']): ?><span class="tbl-sub">NIS <?= e((string) $s['nis']) ?></span><?php endif; ?></td>
                <?php
                // Formatif = penilaian proses: boleh ANGKA, HURUF, atau TANDA CENTANG (permintaan
                // pemilik, v1.36.0). Isiannya teks + daftar saran (datalist) ✓/A/B/C/D, tetapi angka
                // tetap bisa diketik langsung. Rata-rata kelas hanya menghitung sel berisi angka.
                for ($k = 1; $k <= $jf; $k++):
                    $v = $d['formatif'][$k] ?? null;
                    $angkaF = nilai_angka($v);
                    if ($angkaF !== null) { $rataKolom['formatif'][$k][] = $angkaF; }
                ?>
                  <td class="sel-nilai c-nilai<?= $k === 1 ? ' awal-grup' : '' ?>"><input class="nil" type="text" autocomplete="off" list="pilihanFormatif" maxlength="<?= (int) NILAI_FORMATIF_HURUF_MAKS ?>" data-jenis="formatif" name="nf[<?= $sid ?>][<?= $k ?>]" value="<?= e(nilai_formatif_input($v)) ?>" aria-label="Formatif <?= $k ?> - <?= e((string) $s['nama']) ?>" <?= $bolehIsi ? '' : 'disabled' ?>></td>
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
                <td class="c-pred awal-grup"><?= $ra === null ? '-' : '<span class="pill pill-' . e(predikat_dari_k($konteksNilai, $ra)) . '">' . e(predikat_dari_k($konteksNilai, $ra)) . '</span>' ?></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <?php // Daftar saran untuk SETIAP sel kolom Formatif (angka tetap bisa diketik langsung).
              // Satu <datalist> dipakai bersama semua kotak isian Formatif — inilah "daftar kecil"
              // yang muncul pada tiap sel saat kotaknya diklik/diketik. ?>
        <datalist id="pilihanFormatif">
          <?php foreach (NILAI_FORMATIF_PILIHAN as $p): ?><option value="<?= e($p) ?>"></option><?php endforeach; ?>
        </datalist>

        <p class="hint mt16" id="ketFormatif">
          <b>Kolom Formatif</b> menerima <b>angka</b> (0-100), <b>huruf</b> (mis. A, B, C, D), atau
          <b>tanda centang</b> (<b>✓</b>) — klik kotaknya untuk memilih dari daftar kecil
          (✓, A, B, C, D) atau ketik langsung. Nilai huruf/centang <b>tidak ikut</b> perhitungan nilai
          akhir dan <b>tidak dihitung</b> pada rata-rata kelas kolom Formatif (hanya sel berisi angka).
          Kolom Sumatif Lingkup Materi &amp; ASAS tetap <b>angka</b>.
        </p>

        <div class="alert alert-ok mt16" style="margin-bottom:0">
          <?= ikon('chart', 16) ?>
          <span><b>Rumus nilai akhir:</b> (rata-rata Sumatif Lingkup Materi &times; <?= e(r0(100.0 - $bobotAsas)) ?>%) + (ASAS &times; <?= e(r0($bobotAsas)) ?>%).
          Kolom Formatif hanya untuk penilaian proses sehingga tidak dijumlahkan (boleh diisi angka, huruf, atau tanda centang ✓).
          Kosongkan sebuah kolom bila belum ada nilainya — isian yang dikosongkan akan dihapus.</span>
        </div>

        <?php if ($bolehIsi): ?>
          <div class="form-actions mt16">
            <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Nilai</button>
            <span class="xs mut">Nilai akhir &amp; predikat pada kolom kanan dihitung ulang saat Anda mengetik.</span>
          </div>
        <?php else: ?>
          <div class="alert alert-warn mt16" style="margin-bottom:0">
            <?= ikon('eye', 16) ?>
            <span><?= pemberitahuan_hanya_lihat_administrasi('daftar nilai siswa') ?></span>
          </div>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
<script>
window.EG_BOBOT = <?= (float) $bobotAsas ?>;
window.EG_TH = { a: <?= (float) $konteksNilai['batas_a'] ?>, b: <?= (float) $konteksNilai['batas_b'] ?>, c: <?= (float) $konteksNilai['batas_c'] ?> };
</script>
<?php
page_bottom();
