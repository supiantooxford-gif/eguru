<?php
/**
 * VIDEO TUTORIAL (v1.96.0 / skema v64) — modul menu "Video Tutorial".
 *
 * Permintaan pemilik: *"Dibawah menu dashboard akun guru, operator, kepala sekolah tambahkan menu
 * Video Tutorial. Isi dari menu ini adalah kumpulan video yang diupload oleh super admin. Video
 * berupa thumbail youtube yang bisa dibuka oleh guru, operator dan kepala sekolah. Admin mengisi
 * menu tersebut dengan memberikan judul video dan link youtube"*
 *
 * Keputusan yang dipakai (jangan diubah tanpa bertanya ke pemilik):
 *   1. **Daftarnya GLOBAL** — satu kumpulan video untuk SEMUA sekolah/madrasah (tabel
 *      `video_tutorial` sengaja TIDAK punya kolom `sekolah_id`), dan hanya **Super Admin** yang
 *      boleh mengisi/mengubahnya. Peran lain hanya melihat & menonton.
 *   2. Yang diisi Super Admin cukup **judul video + link YouTube** (+ keterangan opsional). Judul
 *      TIDAK diambil otomatis dari YouTube (tanpa panggilan jaringan) supaya tidak ada data yang
 *      ditebak dan halaman tetap cepat.
 *   3. Tampilannya **thumbnail YouTube yang bisa diklik**; klik membuka pemutar di dalam aplikasi
 *      (`<dialog>` + iframe youtube-nocookie). Tanpa JavaScript thumbnail tetap berupa TAUTAN biasa
 *      ke YouTube (target baru) — jadi fitur ini tidak pernah "mati" bila skrip gagal dimuat.
 *   4. Video yang dinonaktifkan Super Admin tetap tersimpan tetapi tidak tampil bagi pengguna.
 *
 * ⚠️ JANGAN menyalin modul ini menjadi halaman terpisah: seluruh aturan (pembacaan ID video,
 * penyimpanan, urutan, hak akses, render kartu) ada di sini, dan `video_tutorial.php` hanya berkas
 * tipis. Pola yang sama dipakai `inc/perangkat.php` untuk menu "Jenis Perangkat".
 */
declare(strict_types=1);

/** Batas panjang isian (judul / keterangan / tautan). */
const VIDEO_TUTORIAL_JUDUL_MAKS = 160;
const VIDEO_TUTORIAL_KET_MAKS   = 400;
const VIDEO_TUTORIAL_LINK_MAKS  = 500;

/**
 * Ambil ID video YouTube (11 karakter) dari tautan apa pun yang lazim dipakai.
 *
 * Bentuk yang dikenali: `youtube.com/watch?v=ID`, `youtu.be/ID`, `youtube.com/embed/ID`,
 * `youtube.com/shorts/ID`, `youtube.com/live/ID`, `youtube.com/v/ID`, `m.youtube.com/...`,
 * `youtube-nocookie.com/embed/ID`, dan yang hanya berisi ID-nya saja.
 * Mengembalikan '' bila tautan tidak memuat ID video yang sah — pemanggil WAJIB menanganinya
 * (mis. menampilkan tautan biasa tanpa thumbnail, bukan membuat gambar yang rusak).
 */
function video_tutorial_youtube_id(?string $tautan): string
{
    $t = trim((string) $tautan);
    if ($t === '') {
        return '';
    }
    // Hanya ID-nya saja (mis. "dQw4w9WgXcQ").
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $t) === 1) {
        return $t;
    }
    // watch?v=ID (boleh ada parameter lain sebelum/sesudah v) + bentuk jalur lainnya.
    if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|v/|shorts/|live/)|youtu\.be/)'
        . '([A-Za-z0-9_-]{11})~i', $t, $m) === 1) {
        return $m[1];
    }
    return '';
}

/** URL thumbnail YouTube (hqdefault selalu tersedia untuk video mana pun, termasuk yang tanpa HD). */
function video_tutorial_thumb(string $videoId): string
{
    return $videoId === '' ? '' : 'https://i.ytimg.com/vi/' . $videoId . '/hqdefault.jpg';
}

/** Tautan "tonton di YouTube" (dipakai sebagai href cadangan tanpa JavaScript). */
function video_tutorial_tonton(string $videoId): string
{
    return $videoId === '' ? '' : 'https://www.youtube.com/watch?v=' . $videoId;
}

/**
 * URL pemutar untuk `<iframe>` di dalam aplikasi.
 *
 * Memakai domain `youtube-nocookie.com` supaya cookie pelacakan YouTube tidak dipasang sebelum
 * pengguna benar-benar menekan tombol putar.
 */
function video_tutorial_embed(string $videoId): string
{
    if ($videoId === '') {
        return '';
    }
    return 'https://www.youtube-nocookie.com/embed/' . $videoId
        . '?autoplay=1&rel=0&modestbranding=1&playsinline=1';
}

// ----------------------------------------------------------------------------------------------
// BACA / TULIS DAFTAR VIDEO
// ----------------------------------------------------------------------------------------------

/** Daftar video (urut `urutan` lalu `id`). $aktifSaja=false dipakai halaman Super Admin. */
function video_tutorial_list(bool $aktifSaja = true): array
{
    if ($aktifSaja) {
        return all('SELECT * FROM video_tutorial WHERE aktif = 1 ORDER BY urutan, id');
    }
    return all('SELECT * FROM video_tutorial ORDER BY urutan, id');
}

/** Satu baris video (null bila tidak ada). */
function video_tutorial_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return one('SELECT * FROM video_tutorial WHERE id = ?', [$id]);
}

/** Jumlah video (aktif saja bila $aktifSaja). */
function video_tutorial_jumlah(bool $aktifSaja = false): int
{
    return (int) val('SELECT COUNT(*) FROM video_tutorial'
        . ($aktifSaja ? ' WHERE aktif = 1' : ''), [], 0);
}

/** Nomor urut untuk video baru (kelipatan 10, diletakkan paling akhir). */
function video_tutorial_urutan_baru(): int
{
    return (int) val('SELECT COALESCE(MAX(urutan), 0) FROM video_tutorial', [], 0) + 10;
}

/** Apakah video YouTube itu sudah ada pada daftar (dibandingkan lewat ID videonya). */
function video_tutorial_link_terpakai(string $tautan, int $kecualiId = 0): bool
{
    $id = video_tutorial_youtube_id($tautan);
    if ($id === '') {
        return false;
    }
    foreach (all('SELECT id, link FROM video_tutorial WHERE id <> ?', [$kecualiId]) as $v) {
        if (video_tutorial_youtube_id((string) $v['link']) === $id) {
            return true;
        }
    }
    return false;
}

/**
 * Tambah ($id = 0) atau ubah satu video. Kembaliannya ['ok'=>bool,'pesan'=>string,'id'=>int].
 *
 * Validasi (semua pesan berbahasa Indonesia dan dibaca pengguna):
 *   • judul wajib diisi;
 *   • tautan wajib diawali http:// atau https:// (`tautan_aman()`) **dan** harus memuat ID video
 *     YouTube — kalau tidak, thumbnail & pemutarnya tidak akan pernah bisa dibuat;
 *   • video yang sama tidak boleh masuk dua kali.
 */
function video_tutorial_simpan(int $id, string $judul, string $link, string $keterangan, bool $aktif): array
{
    $judul = trim($judul);
    $link = trim($link);
    $keterangan = trim($keterangan);

    if ($judul === '') {
        return ['ok' => false, 'pesan' => 'Judul video wajib diisi.', 'id' => 0];
    }
    if ($link === '') {
        return ['ok' => false, 'pesan' => 'Link YouTube wajib diisi.', 'id' => 0];
    }
    if (tautan_aman($link) === '') {
        return ['ok' => false, 'pesan' => 'Link YouTube harus dimulai dengan http:// atau https://.', 'id' => 0];
    }
    if (video_tutorial_youtube_id($link) === '') {
        return ['ok' => false, 'pesan' => 'Link itu bukan tautan video YouTube. Contoh yang benar: '
            . 'https://www.youtube.com/watch?v=xxxxxxxxxxx atau https://youtu.be/xxxxxxxxxxx', 'id' => 0];
    }
    if (video_tutorial_link_terpakai($link, $id)) {
        return ['ok' => false, 'pesan' => 'Video YouTube itu sudah ada pada daftar. Sunting baris yang '
            . 'sudah ada bila judulnya ingin diubah.', 'id' => 0];
    }

    $judul = substr($judul, 0, VIDEO_TUTORIAL_JUDUL_MAKS);
    $link = substr($link, 0, VIDEO_TUTORIAL_LINK_MAKS);
    $keterangan = substr($keterangan, 0, VIDEO_TUTORIAL_KET_MAKS);
    $now = date('Y-m-d H:i:s');

    if ($id > 0) {
        $v = video_tutorial_by_id($id);
        if (!$v) {
            return ['ok' => false, 'pesan' => 'Video itu tidak ditemukan.', 'id' => 0];
        }
        q('UPDATE video_tutorial SET judul = ?, link = ?, keterangan = ?, aktif = ?, diubah = ? WHERE id = ?',
            [$judul, $link, $keterangan, $aktif ? 1 : 0, $now, $id]);
        return ['ok' => true, 'pesan' => 'Video "' . $judul . '" diperbarui.', 'id' => $id];
    }

    q('INSERT INTO video_tutorial (judul, link, keterangan, urutan, aktif, dibuat, diubah) VALUES (?,?,?,?,?,?,?)',
        [$judul, $link, $keterangan, video_tutorial_urutan_baru(), $aktif ? 1 : 0, $now, $now]);
    return ['ok' => true, 'pesan' => 'Video "' . $judul . '" ditambahkan ke daftar Video Tutorial.',
        'id' => (int) db()->lastInsertId()];
}

/** Aktifkan / nonaktifkan satu video (nonaktif = tidak tampil bagi guru/operator/kepala). */
function video_tutorial_toggle(int $id): array
{
    $v = video_tutorial_by_id($id);
    if (!$v) {
        return ['ok' => false, 'pesan' => 'Video itu tidak ditemukan.'];
    }
    $baru = (int) $v['aktif'] === 1 ? 0 : 1;
    q('UPDATE video_tutorial SET aktif = ?, diubah = ? WHERE id = ?',
        [$baru, date('Y-m-d H:i:s'), $id]);
    return ['ok' => true, 'pesan' => 'Video "' . (string) $v['judul'] . '" '
        . ($baru === 1 ? 'ditampilkan kembali.' : 'disembunyikan — tidak tampil pada akun guru, '
            . 'admin operator, dan kepala sekolah/madrasah.')];
}

/** Geser urutan satu video (±1 langkah). Urutan ditulis ulang menjadi kelipatan 10. */
function video_tutorial_geser(int $id, int $arah): array
{
    $daftar = video_tutorial_list(false);
    $pos = -1;
    foreach ($daftar as $i => $v) {
        if ((int) $v['id'] === $id) {
            $pos = $i;
            break;
        }
    }
    if ($pos < 0) {
        return ['ok' => false, 'pesan' => 'Video itu tidak ditemukan.'];
    }
    $tukar = $pos + ($arah < 0 ? -1 : 1);
    if ($tukar < 0 || $tukar >= count($daftar)) {
        return ['ok' => false, 'pesan' => 'Urutan video itu sudah berada di ujung.'];
    }
    $a = $daftar[$pos];
    $b = $daftar[$tukar];
    $now = date('Y-m-d H:i:s');
    q('UPDATE video_tutorial SET urutan = ?, diubah = ? WHERE id = ?',
        [(int) $b['urutan'], $now, (int) $a['id']]);
    q('UPDATE video_tutorial SET urutan = ?, diubah = ? WHERE id = ?',
        [(int) $a['urutan'], $now, (int) $b['id']]);
    // Rapikan menjadi kelipatan 10 agar urutannya tidak pernah bertabrakan.
    $n = 0;
    foreach (video_tutorial_list(false) as $v) {
        $n += 10;
        q('UPDATE video_tutorial SET urutan = ? WHERE id = ?', [$n, (int) $v['id']]);
    }
    return ['ok' => true, 'pesan' => 'Urutan video "' . (string) $a['judul'] . '" dipindahkan.'];
}

/** Hapus satu video dari daftar. */
function video_tutorial_hapus(int $id): array
{
    $v = video_tutorial_by_id($id);
    if (!$v) {
        return ['ok' => false, 'pesan' => 'Video itu tidak ditemukan.'];
    }
    q('DELETE FROM video_tutorial WHERE id = ?', [$id]);
    return ['ok' => true, 'pesan' => 'Video "' . (string) $v['judul'] . '" dihapus dari daftar.'];
}

/** Peran yang boleh membuka menu Video Tutorial (Super Admin selalu boleh). */
function video_tutorial_boleh_lihat(): bool
{
    return is_super() || in_array(peran(), ['kepala', 'operator', 'guru'], true);
}

// ----------------------------------------------------------------------------------------------
// HALAMAN
// ----------------------------------------------------------------------------------------------

/**
 * Halaman menu "Video Tutorial" — dipakai `video_tutorial.php` (berkas tipis).
 *
 * Super Admin : kartu pengisian (tambah/ubah/urutkan/sembunyikan/hapus) + pratinjau kartu video.
 * Guru/operator/kepala : HANYA daftar kartu video yang bisa ditonton (POST apa pun ditolak 403).
 */
function video_tutorial_halaman(): void
{
    require_login();
    if (!video_tutorial_boleh_lihat()) {
        tolak403('Menu Video Tutorial ini tersedia untuk akun Guru, Admin Operator, dan Kepala '
            . 'Sekolah/Madrasah.');
    }
    $super = is_super();
    // Halaman Super Admin bersifat lintas sekolah → istilah generik "Sekolah" (bukan "Madrasah").
    if ($super) {
        $GLOBALS['__vs_istilah_nonaktif'] = true;
    }

    // ---------------------------------------------------------------- pengubahan (Super Admin saja)
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!$super) {
            tolak403('Daftar Video Tutorial hanya dapat diubah oleh Super Admin.');
        }
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        switch ($aksi) {
            case 'simpan':
                $hasil = video_tutorial_simpan($id,
                    (string) ($_POST['judul'] ?? ''),
                    (string) ($_POST['link'] ?? ''),
                    (string) ($_POST['keterangan'] ?? ''),
                    isset($_POST['aktif']));
                break;
            case 'toggle':
                $hasil = video_tutorial_toggle($id);
                break;
            case 'geser':
                $hasil = video_tutorial_geser($id, (int) ($_POST['arah'] ?? 1));
                break;
            case 'hapus':
                $hasil = video_tutorial_hapus($id);
                break;
            default:
                $hasil = ['ok' => false, 'pesan' => 'Aksi tidak dikenal.'];
        }
        flash((string) $hasil['pesan'], !empty($hasil['ok']) ? 'ok' : 'err');
        redirect('video_tutorial.php#daftarVideo');
    }

    $edit = $super ? video_tutorial_by_id((int) ($_GET['edit'] ?? 0)) : null;
    $semua = video_tutorial_list(!$super);
    $total = $super ? count($semua) : 0;
    $aktif = video_tutorial_jumlah(true);

    page_top('Video Tutorial', $super
        ? 'Kumpulan video tutorial YouTube (berlaku semua sekolah/madrasah)'
        : 'Kumpulan video panduan penggunaan aplikasi');
    ?>
<?php if ($super): ?>
  <?php
  // ---------------------------------------------------------------- KARTU PENGISIAN (Super Admin)
  // Bentuknya mengikuti halaman "Jenis Perangkat": daftar di kiri, formulir di kanan.
  ?>
<div class="card" id="cardKelolaVideo">
  <div class="card-head">
    <div>
      <h2><?= ikon('play', 16) ?> Kelola Video Tutorial</h2>
      <p>Isi <b>judul video</b> dan <b>link YouTube</b>-nya. Video tampil sebagai <b>thumbnail</b> yang
        dapat diklik oleh akun <b>guru</b>, <b>admin operator</b>, dan <b>kepala
        sekolah/madrasah</b> pada menu <b>Video Tutorial</b> di bawah Dashboard mereka. Daftar ini
        berlaku untuk <b>seluruh sekolah/madrasah</b>.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $aktif ?> tampil</span>
      <span class="chip"><?= (int) $total ?> total</span>
    </div>
  </div>

  <div class="alert alert-ok" style="background:rgba(201,162,39,.07);border-color:rgba(201,162,39,.45)">
    <?= ikon('info', 16) ?>
    <span>Link yang diterima: <b>https://www.youtube.com/watch?v=…</b>, <b>https://youtu.be/…</b>,
      atau <b>https://www.youtube.com/shorts/…</b>. Judul video <b>tidak</b> diambil otomatis dari
      YouTube — tulislah sendiri supaya sesuai kebutuhan lembaga. Video yang
      <b>disembunyikan</b> tetap tersimpan dan dapat ditampilkan kembali kapan saja.</span>
  </div>

  <div class="divider"></div>
  <div class="grid g21" style="gap:18px">
    <div>
      <div class="section-title mb8" id="tabelVideo"><?= ikon('apps', 15) ?> Daftar Video (<?= (int) $total ?>)</div>
      <?php if (!$semua): ?>
        <div class="empty"><?= ikon('play', 34) ?>
          <h3>Belum ada video tutorial</h3>
          <p>Tambahkan video pertama lewat formulir di sebelah kanan (judul + link YouTube). Selama
            daftarnya kosong, akun guru, admin operator, dan kepala sekolah/madrasah melihat
            keterangan bahwa video tutorial belum diisi.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tbl" id="tblVideo">
            <thead>
              <tr>
                <th class="ctr" style="width:44px">No</th>
                <th style="width:120px">Video</th>
                <th>Judul</th>
                <th class="ctr" style="width:104px">Keadaan</th>
                <th class="ctr" style="width:92px">Urutan</th>
                <th class="ctr" style="width:190px">Tindakan</th>
              </tr>
            </thead>
            <tbody>
            <?php $n = 0; foreach ($semua as $i => $v): $n++; $vid = (int) $v['id'];
                $yt = video_tutorial_youtube_id((string) $v['link']); ?>
              <tr>
                <td class="ctr"><?= $n ?></td>
                <td>
                  <?php if ($yt !== ''): ?>
                    <img class="video-mini" src="<?= e(video_tutorial_thumb($yt)) ?>"
                         alt="Thumbnail <?= e((string) $v['judul']) ?>" loading="lazy">
                  <?php else: ?>
                    <span class="pill pill-warn">tanpa thumbnail</span>
                  <?php endif; ?>
                </td>
                <td>
                  <b><?= e(tetap_kata((string) $v['judul'])) ?></b>
                  <div class="xs mut" style="word-break:break-all"><?= e((string) $v['link']) ?></div>
                </td>
                <td class="ctr">
                  <?php if ((int) $v['aktif'] === 1): ?>
                    <span class="pill pill-ok"><?= ikon('check', 12) ?> tampil</span>
                  <?php else: ?>
                    <span class="pill pill-warn">disembunyikan</span>
                  <?php endif; ?>
                </td>
                <td class="ctr nowrap">
                  <form method="post" class="row-actions" style="justify-content:center">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="geser">
                    <input type="hidden" name="id" value="<?= $vid ?>">
                    <button class="btn btn-ghost btn-sm" type="submit" name="arah" value="-1"
                            title="Naikkan urutan" <?= $i === 0 ? 'disabled' : '' ?>><?= ikon('up', 13) ?></button>
                    <button class="btn btn-ghost btn-sm" type="submit" name="arah" value="1"
                            title="Turunkan urutan" <?= $n === $total ? 'disabled' : '' ?>><?= ikon('down', 13) ?></button>
                  </form>
                </td>
                <td class="ctr">
                  <div class="row-actions">
                    <a class="btn btn-ghost btn-sm" href="video_tutorial.php?edit=<?= $vid ?>#formVideo"><?= ikon('edit', 13) ?> Ubah</a>
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="toggle">
                      <input type="hidden" name="id" value="<?= $vid ?>">
                      <button class="btn <?= (int) $v['aktif'] === 1 ? 'btn-ghost' : 'btn-ok' ?> btn-sm" type="submit">
                        <?= ikon((int) $v['aktif'] === 1 ? 'x' : 'check', 13) ?>
                        <?= (int) $v['aktif'] === 1 ? 'Sembunyikan' : 'Tampilkan' ?></button>
                    </form>
                    <form method="post" data-confirm="Hapus video &quot;<?= e((string) $v['judul']) ?>&quot; dari daftar Video Tutorial?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="id" value="<?= $vid ?>">
                      <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 13) ?> Hapus</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="3" class="ctr">Jumlah</th>
                <th colspan="3" class="ctr"><span class="tbl-sub"><?= (int) $aktif ?> tampil dari
                  <?= (int) $total ?> video</span></th>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div>
      <div class="card card-tight" id="formVideo" style="margin:0">
        <div class="section-title mb8">
          <?= ikon($edit ? 'edit' : 'plus', 15) ?> <?= $edit ? 'Ubah Video' : 'Tambah Video' ?>
        </div>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="simpan">
          <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
          <label class="f" for="judul">Judul Video *</label>
          <input type="text" id="judul" name="judul" required
                 maxlength="<?= (int) VIDEO_TUTORIAL_JUDUL_MAKS ?>"
                 value="<?= e((string) ($edit['judul'] ?? '')) ?>"
                 placeholder="mis. Cara Mengisi Jurnal Harian Guru">
          <label class="f" for="link" style="margin-top:10px">Link YouTube *</label>
          <input type="url" id="link" name="link" required
                 maxlength="<?= (int) VIDEO_TUTORIAL_LINK_MAKS ?>"
                 value="<?= e((string) ($edit['link'] ?? '')) ?>"
                 placeholder="https://www.youtube.com/watch?v=…">
          <div class="hint">Boleh juga <b>https://youtu.be/…</b> atau tautan <b>/shorts/…</b>.</div>
          <label class="f" for="keterangan" style="margin-top:10px">Keterangan <span class="xs mut">(opsional)</span></label>
          <textarea id="keterangan" name="keterangan" rows="3"
                    maxlength="<?= (int) VIDEO_TUTORIAL_KET_MAKS ?>"
                    placeholder="Penjelasan singkat isi video…"><?= e((string) ($edit['keterangan'] ?? '')) ?></textarea>
          <label class="pk-cek" style="margin-top:10px">
            <input type="checkbox" name="aktif" value="1"
                   <?= (int) ($edit['aktif'] ?? 1) === 1 ? 'checked' : '' ?>> Tampilkan pada akun guru, operator &amp; kepala
          </label>
          <div class="flex" style="margin-top:14px">
            <button class="btn btn-primary" type="submit"><?= ikon('save', 15) ?>
              <?= $edit ? 'Simpan Perubahan' : 'Tambah Video' ?></button>
            <?php if ($edit): ?>
              <a class="btn btn-ghost" href="video_tutorial.php">Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php
// ---------------------------------------------------------------- DAFTAR KARTU VIDEO (semua peran)
$tampil = video_tutorial_list(true);
?>
<div class="card" id="cardDaftarVideo">
  <div class="card-head">
    <div>
      <h2><?= ikon('play', 16) ?> Kumpulan Video Tutorial</h2>
      <p>
        <?php if ($super): ?>
          Pratinjau tampilan yang dilihat akun guru, admin operator, dan kepala sekolah/madrasah.
        <?php else: ?>
          Klik thumbnail untuk menonton videonya di sini. Video juga dapat dibuka langsung di YouTube.
        <?php endif; ?>
      </p>
    </div>
    <div class="flex"><span class="chip" id="chipJumlahVideo"><?= count($tampil) ?> video</span></div>
  </div>

  <?php if (!$tampil): ?>
    <div class="empty" id="videoKosong"><?= ikon('play', 34) ?>
      <h3>Belum ada video tutorial</h3>
      <p>
        <?php if ($super): ?>
          Tambahkan video lewat formulir <b>Tambah Video</b> di atas — begitu diisi, kartu videonya
          langsung tampil di sini dan pada menu Video Tutorial seluruh pengguna.
        <?php else: ?>
          Admin aplikasi belum menambahkan video tutorial. Silakan periksa kembali nanti.
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <div class="grid g3 video-grid" id="daftarVideo">
      <?php foreach ($tampil as $v): $yt = video_tutorial_youtube_id((string) $v['link']);
          $judulV = (string) $v['judul'];
          $ketV = trim((string) ($v['keterangan'] ?? '')); ?>
        <article class="video-kartu" data-video="<?= e($yt) ?>">
          <?php if ($yt !== ''): ?>
            <a class="video-thumb" href="<?= e(video_tutorial_tonton($yt)) ?>"
               target="_blank" rel="noopener noreferrer"
               data-video-id="<?= e($yt) ?>" data-video-judul="<?= e($judulV) ?>"
               title="Tonton: <?= e($judulV) ?>">
              <img src="<?= e(video_tutorial_thumb($yt)) ?>" alt="Thumbnail video: <?= e($judulV) ?>"
                   loading="lazy" width="480" height="360">
              <span class="video-play" aria-hidden="true"><?= ikon('play', 30) ?></span>
            </a>
          <?php else: ?>
            <a class="video-thumb video-thumb-tautan" href="<?= e(tautan_aman((string) $v['link'])) ?>"
               target="_blank" rel="noopener noreferrer">
              <span class="video-play" aria-hidden="true"><?= ikon('play', 30) ?></span>
            </a>
          <?php endif; ?>
          <div class="video-isi">
            <h3><?= e(tetap_kata($judulV)) ?></h3>
            <?php if ($ketV !== ''): ?><p><?= e(tetap_kata($ketV)) ?></p><?php endif; ?>
            <div class="flex">
              <a class="btn btn-primary btn-sm"
                 href="<?= e($yt !== '' ? video_tutorial_tonton($yt) : tautan_aman((string) $v['link'])) ?>"
                 target="_blank" rel="noopener noreferrer"><?= ikon('play', 14) ?> Buka di YouTube</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php // PEMUTAR DI DALAM APLIKASI (dimuat saat thumbnail diklik — lihat initVideoTutorial()). ?>
<dialog class="popup video-dialog" id="dialogVideo" aria-label="Pemutar video tutorial">
  <div class="card card-tight" style="margin:0">
    <div class="card-head">
      <div>
        <h2 id="judulDialogVideo"><?= ikon('play', 16) ?> Video Tutorial</h2>
        <p class="xs mut">Bila video tidak muncul, gunakan tombol <b>Buka di YouTube</b> di bawah.</p>
      </div>
      <div class="flex">
        <button class="btn btn-ghost btn-sm" type="button" id="tutupVideo"><?= ikon('x', 14) ?> Tutup</button>
      </div>
    </div>
    <div class="video-frame" id="videoFrame"></div>
    <div class="flex" style="margin-top:12px">
      <a class="btn btn-primary btn-sm" id="tautanVideoYt" href="https://www.youtube.com/" target="_blank"
         rel="noopener noreferrer"><?= ikon('play', 14) ?> Buka di YouTube</a>
    </div>
  </div>
</dialog>
    <?php
    page_bottom();
}
