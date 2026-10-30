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
        // DUA cara membersihkan (permintaan pemilik setelah fitur ini dikira "tidak berfungsi"):
        //   mode=hari  → hanya yang LEBIH LAMA dari N hari (perilaku lama)
        //   mode=semua → SELURUH riwayat masuk/keluar/gagal dihapus tanpa kecuali
        // Sebelumnya HANYA mode "hari" yang ada dengan bawaan 90 hari, sehingga pada instalasi
        // yang riwayatnya masih muda (mis. baru sepekan) tombol ini menghapus 0 baris dan
        // kelihatan "tidak berfungsi" — padahal memang tidak ada baris yang lebih tua dari 90 hari.
        $mode = (string) ($_POST['mode'] ?? 'hari') === 'semua' ? 'semua' : 'hari';
        // Tombol cepat mengirim `hari_pilih` (nama TERPISAH dari isian `hari`) supaya nilainya tidak
        // bertabrakan dengan isian angka pada formulir yang sama — perilaku itu berbeda antar
        // peramban bila kedua kontrol memakai nama yang sama.
        $hari = max(1, (int) ($_POST['hari_pilih'] ?? 0) ?: (int) ($_POST['hari'] ?? 90));

        if (strtoupper(trim((string) ($_POST['konfirmasi'] ?? ''))) !== 'HAPUS') {
            flash('Ketik HAPUS untuk konfirmasi. Tidak ada data yang dihapus.', 'err');
            redirect('log.php');
        }

        $totalSebelum = (int) val('SELECT COUNT(*) FROM log_aktivitas', [], 0);
        if ($totalSebelum === 0) {
            flash('Riwayat masuk & keluar memang masih kosong — belum ada yang perlu dibersihkan.');
            redirect('log.php');
        }

        if ($mode === 'semua') {
            q('DELETE FROM log_aktivitas');
            flash('Seluruh riwayat dibersihkan: ' . $totalSebelum . ' baris dihapus '
                . '(masuk, keluar, dan gagal masuk).', 'warn');
            redirect('log.php');
        }

        $batas = date('Y-m-d H:i:s', time() - $hari * 86400);
        $st = q('DELETE FROM log_aktivitas WHERE waktu < ?', [$batas]);
        $n = $st->rowCount();
        if ($n > 0) {
            flash($n . ' baris riwayat lebih lama dari ' . $hari . ' hari dihapus '
                . '(sebelum pukul ' . substr($batas, 11, 5) . ' pada ' . tanggal_id(substr($batas, 0, 10)) . ').', 'warn');
        } else {
            // JANGAN hanya menulis "0 baris dihapus": pengguna mengira fiturnya rusak. Jelaskan
            // umur riwayat yang ada + beri jalan keluar yang jelas.
            $tertua = (string) val('SELECT MIN(waktu) FROM log_aktivitas', [], '');
            $umur = $tertua !== '' ? max(0, (int) floor((time() - (int) strtotime($tertua)) / 86400)) : 0;
            flash('Tidak ada riwayat yang lebih lama dari ' . $hari . ' hari, jadi tidak ada yang perlu dihapus. '
                . 'Riwayat terlama Anda baru berumur ' . $umur . ' hari. '
                . ($umur < $hari
                    ? 'Ketik angka hari yang lebih kecil dari ' . max(1, $umur) . ', atau pilih '
                        . '"Seluruh riwayat" untuk mengosongkan semuanya.'
                    : 'Pilih "Seluruh riwayat" untuk mengosongkan semuanya.'), 'warn');
        }
        redirect('log.php');
    }

    if ($aksi === 'hapus_riwayat') {
        // Permintaan pemilik: hapus SATU baris riwayat tertentu (mis. percobaan uji sendiri).
        $id = (int) ($_POST['id'] ?? 0);
        $r = $id > 0 ? one('SELECT * FROM log_aktivitas WHERE id = ?', [$id]) : null;
        if (!$r) {
            flash('Baris riwayat tidak ditemukan (mungkin sudah dihapus).', 'err');
        } else {
            q('DELETE FROM log_aktivitas WHERE id = ?', [$id]);
            $ket = trim((string) ($r['nama'] ?? '')) !== '' ? (string) $r['nama'] : (string) ($r['username'] ?? '');
            flash('Riwayat ' . ($ket !== '' ? $ket . ' — ' : '') . waktu_id((string) $r['waktu'])
                . ' (' . ($AKSI[(string) $r['aktivitas']] ?? (string) $r['aktivitas']) . ') dihapus.', 'warn');
        }
        // Kembali ke halaman & filter yang sama supaya tidak kehilangan konteks.
        $kembali = trim((string) ($_POST['kembali'] ?? ''));
        redirect($kembali !== '' && strncmp($kembali, 'log.php', 7) === 0 ? $kembali : 'log.php');
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

// Paginasi riwayat: 10 baris terakhir per halaman (permintaan pemilik), sisanya lewat
// tombol "Selanjutnya" — supaya halaman tidak terlalu panjang.
$perHal = 10;
$totalRiwayat = (int) val('SELECT COUNT(*) FROM log_aktivitas' . $where, $p, 0);
$jmlHal = max(1, (int) ceil($totalRiwayat / $perHal));
$hal = max(1, min($jmlHal, (int) ($_GET['hal'] ?? 1)));
$rows = all('SELECT * FROM log_aktivitas' . $where . ' ORDER BY waktu DESC, id DESC LIMIT ' . $perHal
    . ' OFFSET ' . (($hal - 1) * $perHal), $p);

// Alamat halaman + filter yang sedang dilihat — dikirim balik setelah hapus satu baris supaya
// pengguna tetap berada di halaman/filter yang sama.
$qFilterAwal = array_filter(['cari' => $fCari, 'aktivitas' => $fAksi, 'dari' => $fDari, 'sampai' => $fSampai],
    static fn($v) => $v !== '' && $v !== null);
$tautanSendiri = 'log.php' . ($qFilterAwal || $hal > 1
    ? '?' . http_build_query(array_merge($qFilterAwal, $hal > 1 ? ['hal' => $hal] : []))
    : '');

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

// ---------------------------------------------------------------- ringkasan pembersihan
// Kartu "Bersihkan Riwayat Lama" menampilkan BERAPA BARIS yang akan terhapus untuk tiap pilihan,
// supaya pengguna tidak memilih angka hari yang menghasilkan 0 baris lalu mengira fiturnya rusak.
$totalAda    = (int) val('SELECT COUNT(*) FROM log_aktivitas', [], 0);
$jmlMasuk    = (int) val('SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas = "masuk"', [], 0);
$jmlKeluar   = (int) val('SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas = "keluar"', [], 0);
$jmlGagal    = (int) val('SELECT COUNT(*) FROM log_aktivitas WHERE aktivitas = "gagal"', [], 0);
$tertuaWaktu = (string) val('SELECT MIN(waktu) FROM log_aktivitas', [], '');
$umurTertua  = $tertuaWaktu !== '' ? max(0, (int) floor((time() - (int) strtotime($tertuaWaktu)) / 86400)) : 0;
// Angka bawaan pada isian hari: pilih yang paling masuk akal agar TIDAK menghasilkan 0 baris.
// Diambil dari pilihan terkecil yang benar-benar menghapus sesuatu; bila tidak ada (semua riwayat
// lebih muda dari sehari), dipakai 1 hari.
$hariBawaan = 1;
foreach ([7, 30, 90, 365] as $cHari) {
    if ($umurTertua >= $cHari) {
        $hariBawaan = $cHari;
        break;
    }
}
$pilihanBersih = [];
foreach ([1 => 'Lebih lama dari 1 hari', 7 => 'Lebih lama dari 7 hari',
          30 => 'Lebih lama dari 30 hari', 90 => 'Lebih lama dari 90 hari',
          365 => 'Lebih lama dari 365 hari'] as $cHari => $labelnya) {
    $n = (int) val('SELECT COUNT(*) FROM log_aktivitas WHERE waktu < ?',
        [date('Y-m-d H:i:s', time() - $cHari * 86400)], 0);
    $pilihanBersih[] = ['label' => $labelnya, 'hapus' => $n, 'sisa' => max(0, $totalAda - $n)];
}
$pilihanBersih[] = ['label' => 'SELURUH riwayat (masuk, keluar, gagal)', 'hapus' => $totalAda, 'sisa' => 0];

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
            <th class="ctr">Aktivitas</th><th>IP</th><th>Peramban</th><th class="ctr">Tindakan</th></tr>
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
            <td class="ctr">
              <?php // Permintaan pemilik: hapus SATU baris riwayat (mis. percobaan masuk yang keliru). ?>
              <form method="post"
                    data-confirm="Hapus riwayat ini?&#10;<?= e($nama) ?> — <?= e(waktu_id((string) $r['waktu'])) ?>&#10;Penghapusan tidak dapat dibatalkan.">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus_riwayat">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <input type="hidden" name="kembali" value="<?= e($tautanSendiri) ?>">
                <button class="btn btn-ghost btn-sm" type="submit" title="Hapus riwayat ini"><?= ikon('trash', 14) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($jmlHal > 1): ?>
      <div class="flex between mt16" style="flex-wrap:wrap;gap:10px">
        <?php $barisAwal = (($hal - 1) * $perHal) + 1; $barisAkhir = min($totalRiwayat, $hal * $perHal); ?>
        <span class="xs mut">Menampilkan <?= $barisAwal ?>&ndash;<?= $barisAkhir ?> dari <?= $totalRiwayat ?> baris
          (<?= (int) $perHal ?> terakhir per halaman)</span>
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
            <a class="btn btn-ghost btn-sm" href="<?= e($tautanHal($hal + 1)) ?>">Selanjutnya &rarr;</a>
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

  <?php // id="cardBersih" dipakai uji antarmuka (check_eguru_log_bersih_ui.js) untuk mengukur
        // kartu ini secara tepat — halaman ini memuat beberapa tabel lain. ?>
  <div class="card" id="cardBersih">
    <div class="card-head"><div><h2>Bersihkan Riwayat Lama</h2><p>Merapikan basis data tanpa mengubah data administrasi.</p></div></div>
    <p class="sm mut" style="margin:0 0 12px">
      Riwayat terakhir tercatat:
      <?= $terakhir
        ? waktu_id((string) $terakhir['waktu']) . ' — ' . e((string) ($terakhir['nama'] ?? '')) . ' (' . e((string) ($terakhir['aktivitas'] ?? '')) . ')'
        : 'belum ada' ?>.
    </p>
    <?php // Ringkasan umur riwayat: supaya pengguna tahu ANGKA HARI yang akan benar-benar menghapus
          // sesuatu. Sebelum ini hanya ada satu pilihan ("lebih lama dari 90 hari") sehingga pada
          // instalasi yang riwayatnya masih muda tombolnya menghapus 0 baris dan kelihatan rusak. ?>
    <?php if ($totalAda === 0): ?>
      <div class="alert alert-ok"><?= ikon('check', 16) ?><span>Riwayat masuk &amp; keluar masih kosong —
        belum ada yang perlu dibersihkan.</span></div>
    <?php else: ?>
      <div class="stat-mini mb16" id="ringkasBersih">
        <div><b><?= (int) $totalAda ?></b><span>Baris riwayat tersimpan</span></div>
        <div><b><?= (int) $umurTertua ?> hari</b><span>Umur riwayat terlama</span>
          <div class="stat-hint"><?= e(tanggal_id(substr((string) $tertuaWaktu, 0, 10))) ?></div></div>
        <div><b><?= (int) $jmlMasuk ?></b><span>Masuk</span></div>
        <div><b><?= (int) $jmlKeluar + (int) $jmlGagal ?></b><span>Keluar + gagal masuk</span></div>
      </div>
      <div class="table-wrap mb16">
        <table class="tbl" id="tblBersih">
          <thead><tr><th>Kalau dibersihkan dengan pilihan …</th><th class="ctr">Baris yang terhapus</th>
            <th class="ctr">Sisa</th></tr></thead>
          <tbody>
            <?php foreach ($pilihanBersih as $pb): ?>
              <tr>
                <td><?= e($pb['label']) ?></td>
                <td class="ctr"><b><?= (int) $pb['hapus'] ?></b></td>
                <td class="ctr"><?= (int) $pb['sisa'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <form method="post" data-confirm="Bersihkan riwayat sesuai pilihan yang aktif?">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="bersihkan">
        <div class="grid g2" style="gap:10px">
          <div>
            <label class="f" for="mode">Yang dibersihkan</label>
            <select id="mode" name="mode">
              <option value="hari">Hanya yang lebih lama dari (hari)</option>
              <option value="semua">SELURUH riwayat (masuk, keluar, gagal)</option>
            </select>
          </div>
          <div>
            <label class="f" for="hari">Lebih lama dari (hari)</label>
            <input type="number" id="hari" name="hari" value="<?= (int) $hariBawaan ?>" min="1" max="3650">
          </div>
        </div>
        <p class="hint" style="margin:8px 0 0">
          Isi angka hari di atas, atau pakai tombol cepat berikut (angka pada tombol = batas hari):
        </p>
        <div class="flex" style="gap:8px;flex-wrap:wrap;margin-top:8px">
          <?php foreach ([1, 7, 30, 90, 365] as $cHari): ?>
            <button class="btn btn-ghost btn-sm" type="submit" name="hari_pilih" value="<?= $cHari ?>">
              &gt; <?= $cHari ?> hari</button>
          <?php endforeach; ?>
        </div>
        <div class="mt16">
          <label class="f" for="konfirmasi">Ketik HAPUS untuk konfirmasi</label>
          <input type="text" id="konfirmasi" name="konfirmasi" placeholder="HAPUS" style="max-width:220px">
        </div>
        <button class="btn btn-danger mt16" type="submit"><?= ikon('trash', 15) ?> Bersihkan riwayat</button>
      </form>
    <?php endif; ?>
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
