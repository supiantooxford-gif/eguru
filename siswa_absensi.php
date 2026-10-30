<?php
/**
 * HALAMAN SISWA — DAFTAR HADIR (v1.61.0).
 *
 * Permintaan pemilik: *"Rekapan Daftar hadir Per Mata Pelajaran, Rekapan Daftar Hadir di Kelas
 * (yang diinput oleh wali kelas)"* → halaman ini memuat DUA bagian:
 *   1. **Per Mata Pelajaran** — ringkasan H/S/I/A tiap mapel + rincian tiap pertemuan
 *      (tanggal, pertemuan, status, keterangan) yang diinput guru pada menu Daftar Hadir Siswa;
 *   2. **Di Kelas (wali kelas)** — absensi harian yang diinput wali kelas.
 * Hanya MELIHAT & MENCETAK (tidak ada formulir; POST ditolak 403 oleh gerbang_siswa()).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$sw = require_siswa();
$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];
$aj = ($ta && $sem) ? ajaran_qs($ta, $sem) : '';
$taId = (int) ($ta['id'] ?? 0);
$semId = (int) ($sem['id'] ?? 0);

$mapel = siswa_absen_mapel((int) $sw['id'], $taId, $semId);
$kelas = siswa_absen_kelas((int) $sw['id'], $taId, $semId);
$ringkasGabung = siswa_absen_ringkas(array_merge($mapel['semua'], $kelas['semua']));

/** Warna pil status kehadiran (H/S/I/A) — memakai kelas .pill-st-* yang sudah ada di tema. */
$pilStatus = static function (string $st): string {
    $st = strtoupper($st);
    if (!in_array($st, ['H', 'S', 'I', 'A'], true)) {
        $st = 'A';
    }
    return '<span class="pill pill-st-' . $st . '">' . e($st) . '</span>';
};

page_top('Daftar Hadir Saya', (string) $sw['nama'] . ' &middot; ' . siswa_periode_teks($ta, $sem));
?>
<div class="alert alert-ok mb16">
  <?= ikon('info', 16) ?>
  <span>Rekapan kehadiran Anda: <b>per mata pelajaran</b> (diinput guru mata pelajaran) dan
    <b>di kelas</b> (diinput wali kelas). Isinya hanya dapat dilihat dan dicetak.</span>
</div>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('clipboard', 16) ?> Ringkasan Kehadiran</h2>
      <p><?= e(siswa_periode_teks($ta, $sem)) ?></p>
    </div>
    <div class="flex">
      <?php siswa_form_periode($ta, $sem); ?>
      <a class="btn btn-ghost" href="cetak_siswa_absensi.php<?= e($aj) ?>" target="_blank"><?= ikon('print', 16) ?> Cetak Daftar Hadir</a>
    </div>
  </div>
  <div class="grid g4">
    <div class="card stat card-tight">
      <div class="stat-ic g"><?= ikon('check', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasGabung['h'] ?></div><div class="stat-lbl">Hadir</div></div>
    </div>
    <div class="card stat card-tight">
      <div class="stat-ic s"><?= ikon('clock', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasGabung['s'] ?></div><div class="stat-lbl">Sakit</div></div>
    </div>
    <div class="card stat card-tight">
      <div class="stat-ic"><?= ikon('calendar', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasGabung['i'] ?></div><div class="stat-lbl">Izin</div></div>
    </div>
    <div class="card stat card-tight">
      <div class="stat-ic"><?= ikon('alert', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasGabung['a'] ?></div><div class="stat-lbl">Alpa</div></div>
    </div>
  </div>
  <div class="legend mt16">
    <span><i style="background:#10b981"></i>H = Hadir</span>
    <span><i style="background:#f59e0b"></i>S = Sakit</span>
    <span><i style="background:#0ea5e9"></i>I = Izin</span>
    <span><i style="background:#dc2626"></i>A = Alpa</span>
    <span><b>Total catatan:</b> <?= (int) $ringkasGabung['total'] ?> pertemuan<?= $ringkasGabung['persen'] === null ? '' : ' &middot; kehadiran ' . e(r1($ringkasGabung['persen'])) . '%' ?></span>
  </div>
</div>

<!-- ============================ 1. PER MATA PELAJARAN ============================ -->
<div class="card mt16">
  <div class="card-head">
    <div>
      <h2><?= ikon('book', 16) ?> Rekapan per Mata Pelajaran</h2>
      <p>Ringkasan tiap mata pelajaran beserta rincian pertemuannya (diinput guru mata pelajaran).</p>
    </div>
    <span class="chip"><?= count($mapel['per_mapel']) ?> mata pelajaran</span>
  </div>

  <?php if (!$mapel['per_mapel']): ?>
    <div class="empty">
      <?= ikon('clipboard', 34) ?>
      <h3>Belum ada catatan kehadiran</h3>
      <p>Guru mata pelajaran belum mencatat pertemuan pada periode ini, atau Anda memang belum
        tercatat pada pertemuan mana pun.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblHadirMapel">
        <thead>
          <tr><th style="width:44px">No</th><th>Mata Pelajaran</th><th>Guru</th>
            <th class="ctr" style="width:54px">H</th><th class="ctr" style="width:54px">S</th>
            <th class="ctr" style="width:54px">I</th><th class="ctr" style="width:54px">A</th>
            <th class="ctr" style="width:80px">Pertemuan</th><th class="ctr" style="width:90px">% Hadir</th>
            <th class="ctr" style="width:120px">Rincian</th></tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($mapel['per_mapel'] as $mid => $m): $r = $m['ringkas']; $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e((string) $m['mapel_nama']) ?></b></td>
            <td><?= e((string) ($m['guru_nama'] !== '' ? $m['guru_nama'] : '-')) ?></td>
            <td class="ctr"><b><?= (int) $r['h'] ?></b></td>
            <td class="ctr"><?= (int) $r['s'] ?></td>
            <td class="ctr"><?= (int) $r['i'] ?></td>
            <td class="ctr"><?= (int) $r['a'] ?></td>
            <td class="ctr"><?= (int) $r['total'] ?></td>
            <td class="ctr">
              <?php if ($r['persen'] === null): ?><span class="mut xs">-</span>
              <?php else: ?><span class="pill <?= $r['persen'] >= 90 ? 'pill-A' : ($r['persen'] >= 75 ? 'pill-C' : 'pill-D') ?>"><?= e(r1($r['persen'])) ?>%</span><?php endif; ?>
            </td>
            <td class="ctr"><a class="btn btn-ghost btn-sm" href="#rincian-mapel-<?= (int) $mid ?>"><?= ikon('eye', 14) ?> Lihat</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php foreach ($mapel['per_mapel'] as $mid => $m): ?>
      <details class="sis-rincian" id="rincian-mapel-<?= (int) $mid ?>">
        <summary><?= ikon('clipboard', 15) ?> Rincian pertemuan — <b><?= e((string) $m['mapel_nama']) ?></b>
          <span class="mut xs">(<?= (int) $m['ringkas']['total'] ?> pertemuan, kehadiran
            <?= $m['ringkas']['persen'] === null ? '-' : e(r1($m['ringkas']['persen'])) . '%' ?>)</span>
        </summary>
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr><th style="width:52px">No</th><th style="width:110px">Pertemuan ke-</th>
                <th style="width:150px">Tanggal</th><th class="ctr" style="width:90px">Status</th>
                <th>Materi / Kegiatan</th><th style="width:170px">Keterangan</th></tr>
            </thead>
            <tbody>
            <?php foreach ($m['baris'] as $i => $b): ?>
              <tr>
                <td class="ctr xs mut"><?= $i + 1 ?></td>
                <td class="ctr"><b><?= (int) $b['pertemuan'] ?></b></td>
                <td class="nowrap"><?= e(tanggal_id((string) $b['tanggal'], true)) ?></td>
                <td class="ctr"><?= $pilStatus((string) $b['status']) ?></td>
                <td><?= e((string) ($b['materi'] !== '' ? $b['materi'] : '-')) ?></td>
                <td><?= e((string) ($b['ket'] !== '' ? $b['ket'] : '-')) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </details>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- ============================ 2. DI KELAS (WALI KELAS) ============================ -->
<div class="card mt16">
  <div class="card-head">
    <div>
      <h2><?= ikon('school', 16) ?> Rekapan Daftar Hadir di Kelas</h2>
      <p>Absensi harian kelas<?= $sw['kelas_nama'] !== null ? ' ' . e((string) $sw['kelas_nama']) : '' ?>
        yang diinput <b>wali kelas</b>.</p>
    </div>
    <span class="chip"><?= (int) $kelas['ringkas']['total'] ?> hari tercatat</span>
  </div>

  <?php if (!$kelas['baris']): ?>
    <div class="empty">
      <?= ikon('school', 34) ?>
      <h3>Belum ada catatan absensi kelas</h3>
      <p>Wali kelas belum mencatat absensi harian pada periode ini.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblHadirKelas">
        <thead>
          <tr><th style="width:44px">No</th><th style="width:150px">Tanggal</th>
            <th class="ctr" style="width:90px">Status</th>
            <th style="width:160px">Keterangan</th><th>Catatan Kelas</th>
            <th style="width:170px">Wali Kelas</th></tr>
        </thead>
        <tbody>
        <?php foreach ($kelas['baris'] as $i => $b): ?>
          <tr>
            <td class="ctr xs mut"><?= $i + 1 ?></td>
            <td class="nowrap"><?= e(tanggal_id((string) $b['tanggal'], true)) ?></td>
            <td class="ctr"><?= $pilStatus((string) $b['status']) ?></td>
            <td><?= e((string) ($b['ket'] !== '' ? $b['ket'] : '-')) ?></td>
            <td><?= e((string) ($b['catatan'] !== '' ? $b['catatan'] : '-')) ?></td>
            <td><?= e((string) ($b['wali_nama'] ?: '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="2" class="ctr">Jumlah</th>
            <th class="ctr"><?= (int) $kelas['ringkas']['h'] ?> H &middot; <?= (int) $kelas['ringkas']['s'] ?> S &middot; <?= (int) $kelas['ringkas']['i'] ?> I &middot; <?= (int) $kelas['ringkas']['a'] ?> A</th>
            <th colspan="3" class="ctr">Kehadiran <?= $kelas['ringkas']['persen'] === null ? '-' : e(r1($kelas['ringkas']['persen'])) . '%' ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php
page_bottom();
