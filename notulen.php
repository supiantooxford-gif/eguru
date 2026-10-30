<?php
/**
 * Notulen Rapat — berita acara rapat sekolah/madrasah beserta daftar hadir guru.
 *
 * Halaman ini memuat daftar notulen (bentuk tabel) + tombol "Buat Notulen" yang membuka
 * formulir terpisah (notulen_form.php), seperti modul lain pada aplikasi ini.
 *
 * Hak akses (permintaan pemilik v1.21.0):
 *  - Super Admin (dalam konteks sekolah), Admin Operator, dan Kepala Sekolah/Madrasah boleh
 *    MELIHAT & MENCETAK.
 *  - Membuat/mengubah/menghapus: **Admin Operator**, **guru yang ditugaskan sebagai Ka. Tata Usaha**,
 *    dan Super Admin (dalam konteks). **Kepala Sekolah/Madrasah hanya melihat & mencetak.**
 *  - Akun guru lain tidak melihat menu ini (403).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

// Permintaan pemilik (v1.21.0): "Pada akun operator menu notulen rubah Kembali menjadi bisa
// menambahkan seperti sebelumnya. Jadi yang bisa membuat notulen baru adalah akun operator dan guru
// yang ditugaskan menjadi Ka. Tata Usaha." → pembuat/pengubah notulen = **Admin Operator** DAN guru
// Ka. Tata Usaha (+ Super Admin dalam konteks); kepala sekolah/madrasah hanya lihat + cetak.
$u = require_notulen();
$bolehUbah = boleh_ubah_notulen();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$bolehUbah) {
        tolak403('Pembuatan & pengubahan Notulen Rapat dilakukan oleh Admin Operator atau guru yang '
            . 'ditugaskan sebagai Ka. Tata Usaha (kepala sekolah/madrasah hanya melihat dan mencetak).');
    }
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $n = $id > 0 ? notulen_by_id($id) : null;

    if ($aksi === 'hapus') {
        if (!$n) {
            flash('Notulen tidak ditemukan.', 'err');
        } else {
            $peserta = (int) val('SELECT COUNT(*) FROM notulen_hadir WHERE notulen_id = ?', [$id], 0);
            q('DELETE FROM notulen WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
            flash('Notulen rapat "' . $n['judul'] . '" beserta ' . $peserta . ' baris daftar hadir dihapus.', 'warn');
        }
        redirect('notulen.php');
    }
    redirect('notulen.php');
}

// ---------------------------------------------------------------- filter (sama polanya dengan modul lain)
$cari = trim((string) ($_GET['cari'] ?? ''));
$fDari = tgl_ok($_GET['dari'] ?? null);
$fSampai = tgl_ok($_GET['sampai'] ?? null);
if ($fDari !== null && $fSampai !== null && $fDari > $fSampai) {
    [$fDari, $fSampai] = [$fSampai, $fDari];
}

$w = ['n.sekolah_id = ?'];
$p = [sekolah_id()];
if ($cari !== '') {
    $w[] = '(n.judul LIKE ? OR n.tempat LIKE ? OR n.agenda LIKE ? OR n.pembahasan LIKE ? OR n.keputusan LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($p, $like, $like, $like, $like, $like);
}
if ($fDari !== null) {
    $w[] = 'n.tanggal >= ?';
    $p[] = $fDari;
}
if ($fSampai !== null) {
    $w[] = 'n.tanggal <= ?';
    $p[] = $fSampai;
}
$where = ' WHERE ' . implode(' AND ', $w);

$rows = all('SELECT n.*,
    (SELECT COUNT(*) FROM notulen_hadir h WHERE h.notulen_id = n.id) AS jml_hadir,
    (SELECT COUNT(*) FROM notulen_foto f WHERE f.notulen_id = n.id) AS jml_foto
    FROM notulen n' . $where . ' ORDER BY n.tanggal DESC, n.id DESC LIMIT 300', $p);

// Ringkasan: dihitung dari SELURUH notulen sekolah ini (bukan hanya baris berfilter).
$totalNotulen = (int) val('SELECT COUNT(*) FROM notulen WHERE sekolah_id = ?', [sekolah_id()], 0);
$bulanIni = (int) val('SELECT COUNT(*) FROM notulen WHERE sekolah_id = ? AND strftime("%Y-%m", tanggal) = ?',
    [sekolah_id(), date('Y-m')], 0);
$guruAktif = (int) val('SELECT COUNT(*) FROM guru WHERE aktif = 1 AND sekolah_id = ?', [sekolah_id()], 0);

// Filter aktif → dipakai tautan cetak supaya mengikuti yang tampil di layar.
$qCetak = array_filter([
    'cari' => $cari !== '' ? $cari : null,
    'dari' => $fDari,
    'sampai' => $fSampai,
]);

page_top('Notulen Rapat');
?>
<div class="grid g4">
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('doc', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $totalNotulen ?></div><div class="stat-lbl">Notulen tersimpan</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic s"><?= ikon('calendar', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $bulanIni ?></div><div class="stat-lbl">Bulan ini</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic o"><?= ikon('users', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $guruAktif ?></div><div class="stat-lbl">Guru aktif (peserta)</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic p"><?= ikon('filter', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= count($rows) ?></div><div class="stat-lbl">Baris ditampilkan</div></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div>
      <h2>Daftar Notulen Rapat</h2>
      <p>Berita acara rapat beserta daftar hadir guru, siap dilihat dan dicetak.</p>
    </div>
    <div class="flex">
      <?php if ($bolehUbah): ?>
        <a class="btn btn-primary" href="notulen_form.php"><?= ikon('plus', 16) ?> Buat Notulen</a>
      <?php else: ?>
        <span class="pill pill-warn"><?= ikon('eye', 13) ?> hanya lihat &amp; cetak</span>
      <?php endif; ?>
    </div>
  </div>

  <form class="filterbar mb16" method="get">
    <div>
      <label class="f" for="cari">Cari</label>
      <input type="search" id="cari" name="cari" value="<?= e($cari) ?>" placeholder="topik / tempat / agenda / hasil rapat...">
    </div>
    <div>
      <label class="f" for="dari">Dari Tanggal</label>
      <input type="date" id="dari" name="dari" value="<?= e((string) ($fDari ?? '')) ?>">
    </div>
    <div>
      <label class="f" for="sampai">Sampai Tanggal</label>
      <input type="date" id="sampai" name="sampai" value="<?= e((string) ($fSampai ?? '')) ?>">
    </div>
    <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
    <?php if ($cari !== '' || $fDari !== null || $fSampai !== null): ?>
      <a class="btn btn-ghost" href="notulen.php">Reset</a>
    <?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="empty">
      <?= ikon('doc', 34) ?>
      <h3>Belum ada notulen rapat</h3>
      <p><?= $bolehUbah
        ? 'Buat notulen rapat lewat tombol "Buat Notulen", lalu pilih guru yang menghadiri rapat.'
        : 'Notulen rapat yang tersimpan akan tampil di sini.' ?></p>
      <?php if ($bolehUbah): ?>
        <div class="flex" style="justify-content:center">
          <a class="btn btn-primary" href="notulen_form.php"><?= ikon('plus', 16) ?> Buat Notulen</a>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblNotulen">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Topik Rapat</th>
            <th class="ctr">Hadir</th>
            <th class="ctr">Foto</th>
            <th>Pimpinan / Notulis</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="nowrap">
              <?= e(tanggal_id((string) $r['tanggal'])) ?>
              <span class="tbl-sub"><?= e(hari_id((string) $r['tanggal'])) ?><?= trim((string) $r['waktu']) !== '' ? ' &middot; ' . e((string) $r['waktu']) : '' ?></span>
            </td>
            <td>
              <b><?= e((string) ($r['judul'] ?: '(topik belum diisi)')) ?></b>
              <span class="tbl-sub"><?= e((string) ($r['tempat'] ?: 'tempat belum diisi')) ?></span>
            </td>
            <td class="ctr"><span class="pill pill-ok"><?= (int) $r['jml_hadir'] ?> guru</span></td>
            <td class="ctr">
              <span class="pill <?= (int) $r['jml_foto'] > 0 ? 'pill-ok' : 'pill-gray' ?>"
                    title="Foto bukti kegiatan (maksimal <?= NOTULEN_FOTO_MAKS ?>)">
                <?= (int) $r['jml_foto'] ?>/<?= NOTULEN_FOTO_MAKS ?>
              </span>
            </td>
            <td class="sm">
              <?= e((string) ($r['pimpinan'] ?: '-')) ?>
              <span class="tbl-sub">Notulis: <?= e((string) ($r['notulis'] ?: '-')) ?></span>
            </td>
            <td>
              <div class="row-actions">
                <a class="btn btn-ghost btn-sm" href="cetak_notulen.php?id=<?= (int) $r['id'] ?>&amp;bagian=berita" target="_blank" title="Cetak Berita Acara"><?= ikon('print', 15) ?> Berita Acara</a>
                <a class="btn btn-ghost btn-sm" href="cetak_notulen.php?id=<?= (int) $r['id'] ?>&amp;bagian=hadir" target="_blank" title="Cetak Daftar Hadir"><?= ikon('clipboard', 15) ?> Daftar Hadir</a>
                <a class="btn btn-ghost btn-sm" href="cetak_notulen.php?id=<?= (int) $r['id'] ?>&amp;bagian=isi" target="_blank" title="Cetak Isi Notulen Rapat"><?= ikon('report', 15) ?> Isi Notulen</a>
                <?php if ((int) $r['jml_foto'] > 0): ?>
                  <?php // Lembar foto dokumentasi hanya masuk akal bila notulen sudah punya foto. ?>
                  <a class="btn btn-ghost btn-sm" href="cetak_notulen.php?id=<?= (int) $r['id'] ?>&amp;bagian=foto" target="_blank" title="Cetak Foto Dokumentasi (<?= (int) $r['jml_foto'] ?> foto)"><?= ikon('star', 15) ?> Foto Dokumentasi</a>
                <?php endif; ?>
                <?php if ($bolehUbah): ?>
                  <a class="btn btn-ghost btn-sm" href="notulen_form.php?id=<?= (int) $r['id'] ?>#cardFotoNotulen"
                     title="Foto bukti kegiatan (<?= (int) $r['jml_foto'] ?> dari <?= NOTULEN_FOTO_MAKS ?>)"><?= ikon('upload', 15) ?> Foto</a>
                  <a class="btn btn-ghost btn-sm" href="notulen_form.php?id=<?= (int) $r['id'] ?>"><?= ikon('edit', 15) ?></a>
                  <form method="post" data-confirm="Hapus notulen &quot;<?= e((string) $r['judul']) ?>&quot; beserta daftar hadirnya?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 15) ?></button>
                  </form>
                <?php endif; ?>
              </div>
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
