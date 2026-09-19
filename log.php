<?php
/**
 * LOG AKTIVITAS (khusus Super Admin).
 *
 * Dua bagian:
 *   1. SEDANG ONLINE — akun yang masih memakai aplikasi (aktif dalam LOG_MENIT_ONLINE menit terakhir),
 *                      sumbernya tabel `sesi_aktif`.
 *   2. RIWAYAT       — masuk / keluar / gagal masuk beserta nama, peran, sekolah/madrasah, tanggal-jam,
 *                      IP, dan peramban (tabel `log_aktivitas`).
 *
 * Halaman ini mengelola sekolah umum DAN madrasah sekaligus → istilah generik "Sekolah"
 * (lihat $GLOBALS['__vs_istilah_nonaktif']).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$GLOBALS['__vs_istilah_nonaktif'] = true;

$u = require_super();

/** Tanggal valid (YYYY-MM-DD) atau null. */
function log_tgl_ok($v): ?string
{
    $v = trim((string) $v);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : null;
}

$AKSI = ['masuk' => 'Masuk', 'keluar' => 'Keluar', 'gagal' => 'Gagal masuk'];
$AKSI_KELAS = ['masuk' => 'pill-ok', 'keluar' => 'pill-gray', 'gagal' => 'pill-D'];

// ---------------------------------------------------------------- aksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'bersihkan') {
        $hari = max(1, (int) ($_POST['hari'] ?? 90));
        if (strtoupper(trim((string) ($_POST['konfirmasi'] ?? ''))) !== 'HAPUS') {
            flash('Ketik HAPUS untuk menghapus riwayat lama. Tidak ada data yang dihapus.', 'err');
            redirect('log.php');
        }
        $st = q('DELETE FROM log_aktivitas WHERE waktu < ?', [date('Y-m-d H:i:s', time() - $hari * 86400)]);
        flash($st->rowCount() . ' baris riwayat lebih lama dari ' . $hari . ' hari dihapus.', 'warn');
        redirect('log.php');
    }

    if ($aksi === 'bersihkan_online') {
        $n = log_sesi_bersihkan(1);
        flash($n > 0
            ? $n . ' catatan sesi lama dibuang dari daftar (riwayat masuk/keluar tidak terpengaruh).'
            : 'Tidak ada catatan sesi lama yang perlu dibuang.');
        redirect('log.php');
    }

    redirect('log.php');
}

// ---------------------------------------------------------------- filter riwayat
$fCari = trim((string) ($_GET['cari'] ?? ''));
$fAksi = (string) ($_GET['aktivitas'] ?? '');
$fDari = log_tgl_ok($_GET['dari'] ?? null);
$fSampai = log_tgl_ok($_GET['sampai'] ?? null);
if ($fDari !== null && $fSampai !== null && $fDari > $fSampai) {
    [$fDari, $fSampai] = [$fSampai, $fDari];
}
if (!isset($AKSI[$fAksi])) {
    $fAksi = '';
}

$w = [];
$p = [];
if ($fCari !== '') {
    $w[] = '(nama LIKE ? OR username LIKE ? OR ip LIKE ? OR peramban LIKE ? OR keterangan LIKE ? OR sekolah LIKE ?)';
    $like = '%' . $fCari . '%';
    array_push($p, $like, $like, $like, $like, $like, $like);
}
if ($fAksi !== '') {
    $w[] = 'aktivitas = ?';
    $p[] = $fAksi;
}
if ($fDari !== null) {
    $w[] = 'waktu >= ?';
    $p[] = $fDari . ' 00:00:00';
}
if ($fSampai !== null) {
    $w[] = 'waktu <= ?';
    $p[] = $fSampai . ' 23:59:59';
}
$where = $w ? ' WHERE ' . implode(' AND ', $w) : '';

// Paginasi riwayat.
$perHal = 40;
$totalRiwayat = (int) val('SELECT COUNT(*) FROM log_aktivitas' . $where, $p, 0);
$jmlHal = max(1, (int) ceil($totalRiwayat / $perHal));
$hal = max(1, min($jmlHal, (int) ($_GET['hal'] ?? 1)));
$rows = all('SELECT * FROM log_aktivitas' . $where . ' ORDER BY waktu DESC, id DESC LIMIT ' . $perHal
    . ' OFFSET ' . (($hal - 1) * $perHal), $p);

// ---------------------------------------------------------------- sedang online
log_sesi_bersihkan(2);   // catatan sesi lama dibuang (riwayat masuk/keluar tidak disentuh)
$online = log_online();
$onlineAkun = count(array_unique(array_map(static fn(array $r): int => (int) $r['user_id'], $online)));

// ---------------------------------------------------------------- ringkasan
$hariIni = date('Y-m-d');
$jumlahMasukHariIni = (int) val('SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas = "masuk" AND waktu >= ?', [$hariIni . ' 00:00:00'], 0);
$jumlahGagalHariIni = (int) val('SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas = "gagal" AND waktu >= ?', [$hariIni . ' 00:00:00'], 0);
$jumlahKeluarHariIni = (int) val('SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas = "keluar" AND waktu >= ?', [$hariIni . ' 00:00:00'], 0);
$terakhir = one('SELECT * FROM log_aktivitas ORDER BY waktu DESC, id DESC LIMIT 1');
$perambanUmum = all('SELECT peramban, COUNT(*) AS jml FROM log_aktivitas WHERE peramban IS NOT NULL GROUP BY peramban ORDER BY jml DESC LIMIT 5');
$qFilter = array_filter(['cari' => $fCari, 'aktivitas' => $fAksi, 'dari' => $fDari, 'sampai' => $fSampai],
    static fn($v) => $v !== '' && $v !== null);

page_top('Log Aktivitas', 'Super Admin &middot; pemantauan akun &amp; aktivitas masuk');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2>Log Aktivitas &amp; Pengguna Online</h2>
      <p>Pantau siapa saja yang sedang memakai aplikasi (online) dan riwayat masuk/keluar setiap akun
        beserta tanggal, jam, IP, dan peramban yang dipakai. Sesi dianggap online bila aktif dalam
        <?= (int) LOG_MENIT_ONLINE ?> menit terakhir.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($online) ?> sesi online</span>
      <span class="chip"><?= $totalRiwayat ?> baris riwayat</span>
    </div>
  </div>

  <div class="grid g4">
    <div class="card stat card-tight">
      <div class="stat-ic g"><?= ikon('eye', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= count($online) ?></div>
        <div class="stat-lbl">Sedang online<?= $onlineAkun !== count($online) ? ' (' . $onlineAkun . ' akun)' : '' ?></div></div>
    </div>
    <div class="card stat card-tight">
      <div class="stat-ic"><?= ikon('key', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= $jumlahMasukHariIni ?></div>
        <div class="stat-lbl">Masuk hari ini<?= $jumlahKeluarHariIni > 0 ? ' &middot; ' . $jumlahKeluarHariIni . ' keluar' : '' ?></div></div>
    </div>
    <div class="card stat card-tight">
      <div class="stat-ic o"><?= ikon('star', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= $jumlahGagalHariIni ?></div>
        <div class="stat-lbl">Gagal masuk hari ini</div></div>
    </div>
    <div class="card stat card-tight">
      <div class="stat-ic p"><?= ikon('report', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= $totalRiwayat ?></div>
        <div class="stat-lbl">Riwayat<?= $qFilter ? ' (sesuai filter)' : ' tercatat' ?></div></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div>
      <h2>Sedang Online Sekarang</h2>
      <p>Akun yang aktif dalam <?= (int) LOG_MENIT_ONLINE ?> menit terakhir. Daftar diperbarui setiap kali
        yang bersangkutan membuka halaman.</p>
    </div>
    <div class="flex">
      <?php if ($online): ?><span class="chip"><?= count($online) ?> sesi</span><?php endif; ?>
      <form method="post" data-confirm="Buang catatan sesi yang sudah lama tidak aktif? Riwayat masuk/keluar tidak dihapus.">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="bersihkan_online">
        <button class="btn btn-ghost btn-sm" type="submit">Buang sesi lama</button>
      </form>
    </div>
  </div>

  <?php if (!$online): ?>
    <div class="empty"><?= ikon('eye', 34) ?><h3>Belum ada pengguna online</h3>
      <p>Tidak ada akun yang aktif dalam <?= (int) LOG_MENIT_ONLINE ?> menit terakhir.</p></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblOnline">
        <thead>
          <tr><th>Nama</th><th>Peran</th><th>Asal</th><th>Tanggal &amp; Jam Masuk</th>
            <th>Terakhir aktif</th><th>IP</th><th>Peramban</th></tr>
        </thead>
        <tbody>
        <?php foreach ($online as $o):
            $nama = trim((string) $o['nama']) !== '' ? (string) $o['nama'] : (string) $o['username'];
            $aktifMenit = max(0, (int) floor((time() - (int) strtotime((string) $o['terakhir'])) / 60));
            ?>
          <tr>
            <td>
              <div class="flex" style="gap:9px;align-items:center">
                <span class="dot-online" title="online"></span>
                <?= avatar_html($nama, 30, 'avatar', $o['foto'] !== null ? (string) $o['foto'] : null) ?>
                <div>
                  <b><?= e($nama) ?></b>
                  <span class="tbl-sub"><?= e((string) $o['username']) ?></span>
                </div>
              </div>
            </td>
            <td><span class="xs"><?= e(role_label((string) $o['role'])) ?></span></td>
            <td class="xs"><?= e(log_sekolah_label((string) $o['role'], (string) ($o['sekolah_nama'] ?? ''))) ?></td>
            <td class="nowrap xs"><?= waktu_id((string) $o['mulai']) ?></td>
            <td class="nowrap xs"><span class="pill pill-ok"><?= $aktifMenit < 1 ? 'baru saja' : $aktifMenit . ' menit lalu' ?></span></td>
            <td class="xs nowrap"><?= e((string) ($o['ip'] ?: '-')) ?></td>
            <td class="xs"><?= e((string) ($o['peramban'] ?: '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-head">
    <div>
      <h2>Riwayat Masuk &amp; Keluar</h2>
      <p>Seluruh riwayat, termasuk percobaan masuk yang gagal (sandi salah, akun atau sekolah nonaktif).
        Nama akun dan sekolah/madrasah disimpan apa adanya pada saat kejadian.</p>
    </div>
  </div>

  <form class="filterbar" method="get">
    <div>
      <label class="f" for="cari">Cari</label>
      <input type="search" id="cari" name="cari" value="<?= e($fCari) ?>" placeholder="nama / pengguna / IP / sekolah">
    </div>
    <div>
      <label class="f" for="aktivitas">Aktivitas</label>
      <select id="aktivitas" name="aktivitas" data-auto>
        <option value="">Semua</option>
        <?php foreach ($AKSI as $k => $lbl): ?>
          <option value="<?= e($k) ?>" <?= $fAksi === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="f" for="dari">Dari tanggal</label>
      <input type="date" id="dari" name="dari" value="<?= e((string) $fDari) ?>">
    </div>
    <div>
      <label class="f" for="sampai">Sampai tanggal</label>
      <input type="date" id="sampai" name="sampai" value="<?= e((string) $fSampai) ?>">
    </div>
    <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
    <?php if ($qFilter): ?><a class="btn btn-ghost" href="log.php">Reset</a><?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="empty"><?= ikon('report', 34) ?>
      <h3><?= $qFilter ? 'Tidak ada riwayat yang cocok' : 'Belum ada riwayat aktivitas' ?></h3>
      <p><?= $qFilter
        ? 'Coba ubah kata kunci, jenis aktivitas, atau rentang tanggalnya.'
        : 'Riwayat terisi otomatis setiap kali ada akun masuk atau keluar dari aplikasi.' ?></p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblRiwayat">
        <thead>
          <tr><th>Tanggal &amp; Jam</th><th>Nama</th><th>Peran</th><th>Asal</th>
            <th class="ctr">Aktivitas</th><th>IP</th><th>Peramban</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $nama = trim((string) ($r['nama'] ?? ''));
            if ($nama === '') {
                $nama = (string) ($r['username'] ?? '-');
            }
            $akt = (string) $r['aktivitas'];
            $ket = trim((string) ($r['keterangan'] ?? ''));
            ?>
          <tr>
            <td class="nowrap"><?= waktu_id((string) $r['waktu']) ?></td>
            <td>
              <b><?= e($nama) ?></b>
              <?php if (trim((string) ($r['username'] ?? '')) !== ''): ?>
                <span class="tbl-sub"><?= e((string) $r['username']) ?></span>
              <?php endif; ?>
            </td>
            <td class="xs"><?= e(trim((string) ($r['role'] ?? '')) !== '' ? role_label((string) $r['role']) : '-') ?></td>
            <td class="xs"><?= e(log_sekolah_label($r['role'] ?? '', $r['sekolah'] ?? '')) ?></td>
            <td class="ctr">
              <span class="pill <?= e($AKSI_KELAS[$akt] ?? 'pill-gray') ?>"><?= e($AKSI[$akt] ?? $akt) ?></span>
              <?php if ($ket !== ''): ?><span class="tbl-sub"><?= e($ket) ?></span><?php endif; ?>
            </td>
            <td class="xs nowrap"><?= e((string) ($r['ip'] ?: '-')) ?></td>
            <td class="xs"><?= e((string) ($r['peramban'] ?: '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($jmlHal > 1): ?>
      <div class="flex between mt16" style="flex-wrap:wrap;gap:10px">
        <span class="xs mut">Menampilkan <?= (($hal - 1) * $perHal) + 1 ?>&ndash;<?= min($totalRiwayat, $hal * $perHal) ?>
          dari <?= $totalRiwayat ?> baris</span>
        <div class="flex" style="gap:6px">
          <?php
          $tautanHal = static function (int $n) use ($qFilter): string {
              return 'log.php?' . http_build_query(array_merge($qFilter, ['hal' => $n]));
          };
          ?>
          <?php if ($hal > 1): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($tautanHal(1)) ?>">Awal</a>
            <a class="btn btn-ghost btn-sm" href="<?= e($tautanHal($hal - 1)) ?>">&larr; Sebelumnya</a>
          <?php endif; ?>
          <span class="chip">Halaman <?= $hal ?> / <?= $jmlHal ?></span>
          <?php if ($hal < $jmlHal): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($tautanHal($hal + 1)) ?>">Berikutnya &rarr;</a>
            <a class="btn btn-ghost btn-sm" href="<?= e($tautanHal($jmlHal)) ?>">Akhir</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="grid g21">
  <div class="card">
    <div class="card-head">
      <div><h2>Peramban yang Paling Sering Dipakai</h2><p>Dihitung dari seluruh riwayat yang tercatat.</p></div>
    </div>
    <?php if ($perambanUmum): ?>
      <div class="grid" style="gap:10px">
        <?php foreach ($perambanUmum as $pb): ?>
          <div class="flex between" style="gap:12px">
            <span class="sm"><?= e((string) $pb['peramban']) ?></span>
            <span class="chip"><?= (int) $pb['jml'] ?> kali</span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="sm mut" style="margin:0">Belum ada data peramban.</p>
    <?php endif; ?>
    <p class="hint mt16">
      IP diambil dari permintaan yang diterima aplikasi. Bila pengguna berada di balik jaringan/proxy,
      IP yang tampil bisa berupa IP jaringan tersebut.
    </p>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Bersihkan Riwayat Lama</h2><p>Merapikan basis data tanpa mengubah data administrasi.</p></div></div>
    <p class="sm mut" style="margin:0 0 12px">
      Riwayat terakhir tercatat:
      <?= $terakhir
        ? waktu_id((string) $terakhir['waktu']) . ' — ' . e((string) ($terakhir['nama'] ?? '')) . ' (' . e((string) ($terakhir['aktivitas'] ?? '')) . ')'
        : 'belum ada' ?>.
    </p>
    <form method="post" data-confirm="Hapus riwayat aktivitas yang lebih lama dari rentang yang dipilih?">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="bersihkan">
      <div class="grid g2" style="gap:10px">
        <div>
          <label class="f" for="hari">Lebih lama dari (hari)</label>
          <input type="number" id="hari" name="hari" value="90" min="1" max="3650">
        </div>
        <div>
          <label class="f" for="konfirmasi">Ketik HAPUS untuk konfirmasi</label>
          <input type="text" id="konfirmasi" name="konfirmasi" placeholder="HAPUS">
        </div>
      </div>
      <button class="btn btn-danger mt16" type="submit"><?= ikon('trash', 15) ?> Bersihkan riwayat lama</button>
    </form>
  </div>
</div>

<div class="card card-tight">
  <div class="flex between">
    <div>
      <h3>Catatan</h3>
      <p class="sm mut" style="margin:4px 0 0">
        Daftar <b>online</b> dihitung dari aktivitas terakhir tiap akun (<?= (int) LOG_MENIT_ONLINE ?> menit),
        jadi akun yang menutup peramban tanpa menekan <i>Keluar</i> akan hilang sendiri dari daftar.
        Riwayat <b>keluar</b> hanya tercatat bila pengguna benar-benar menekan tombol Keluar; masa berlaku
        sesi yang habis tidak dihitung sebagai keluar. Riwayat tidak pernah menampilkan kata sandi dan
        tidak menyimpan id sesi. Percobaan masuk yang gagal dibatasi <b>2000 baris terbaru</b> agar
        tabelnya tidak membengkak (halaman masuk dapat diakses siapa saja). Akun/sekolah yang
        dinonaktifkan tidak lagi dihitung online.
      </p>
    </div>
    <span class="chip">Hanya Super Admin</span>
  </div>
</div>
<?php
page_bottom();
