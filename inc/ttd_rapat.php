<?php
/**
 * TANDA TANGAN RAPAT pada AKUN GURU — e-GURU v1.101.0 (tanpa perubahan skema).
 *
 * Permintaan pemilik: *"Pada saat operator memilih guru hadir pada kegiatan rapat. Buat guru bisa
 * menandatangani lewat akun guru masing-masing. Pada akun guru muncul tanda tangani rapat. Yang
 * muncul hanya yang dicentang oleh operator."*
 *
 * Artinya:
 *   1. **Sumber daftar** = centang pada formulir Notulen Rapat (`notulen_form.php` → tabel
 *      `notulen_hadir`). Guru hanya melihat rapat yang barisnya BENAR-BENAR dicentang operator —
 *      tidak ada daftar lain, tidak ada rapat sekolah lain (difilter `sekolah_id`).
 *   2. **Tanda tangan** memakai mekanisme yang SAMA dengan Notulen Rapat (v1.70.0): coretan pada
 *      `<canvas>` dikirim sebagai PNG ke `upload_notulen_ttd.php` → media proxy platform → yang
 *      disimpan di tabel `notulen_ttd` HANYA URL CDN-nya. Kunci pihaknya **`hadir:g<id guru>`**
 *      (lihat `notulen_ttd_pihak()`), jadi tanda tangan guru langsung muncul pada kolom tanda
 *      tangan di lembar cetak Daftar Hadir (`cetak_notulen.php`) milik operator.
 *   3. **Hak tanda tangan** akun guru dibatasi pada barisnya SENDIRI; itu ditegakkan di server
 *      (`upload_notulen_ttd.php`), bukan hanya dengan menyembunyikan tombol.
 *
 * Halaman: `ttd_rapat.php` (daftar rapat) dan `ttd_rapat.php?id=N` (rincian + tanda tangan).
 * Markup dialog & JSON `#ttdData` sengaja sama dengan modul lain supaya `initTtdSentuh()`
 * (`assets/app.js`) dipakai apa adanya — JANGAN menulis skrip tanda tangan baru.
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/notulen_ttd.php';
// JEMBATAN TUSMART (v1.102.0): rapat yang dibuat Kepala Tata Usaha di aplikasi TUSmart ikut tampil
// di sini untuk guru lembaga yang sama (dicocokkan lewat NPSN lembaga).
require_once __DIR__ . '/ttd_rapat_tusmart.php';

/** ID guru milik akun yang sedang masuk (0 bila akun tidak tertaut ke data guru). */
function ttd_rapat_guru_id(): int
{
    return guru_id_saya();
}

/** Apakah akun ini boleh memakai menu "Tanda Tangan Rapat" (akun tertaut ke data guru). */
function ttd_rapat_boleh(): bool
{
    return ttd_rapat_guru_id() > 0 && current_sekolah() !== null;
}

/** Kunci pihak tanda tangan milik satu guru (`hadir:g<id guru>`); '' bila bukan guru. */
function ttd_rapat_kunci(int $guruId): string
{
    return $guruId > 0 ? 'hadir:g' . $guruId : '';
}

/** Sumber rapat yang sah: 'eguru' (Notulen Rapat e-GURU) atau 'tusmart' (TUSmart). */
function ttd_rapat_sumber_sah(string $sumber): string
{
    return $sumber === 'tusmart' ? 'tusmart' : 'eguru';
}

/** Label sumber rapat untuk ditampilkan ('' = e-GURU, tidak perlu label). */
function ttd_rapat_sumber_label(string $sumber): string
{
    return $sumber === 'tusmart' ? 'TUSmart' : '';
}

/**
 * Rapat (notulen) yang daftar hadirnya DICENTANG operator untuk guru ini, terbaru lebih dulu,
 * lengkap dengan keadaan tanda tangannya.
 *
 * Sejak v1.102.0 daftar ini MENGGABUNGKAN dua sumber:
 *   - `eguru`  → Notulen Rapat e-GURU (`notulen`/`notulen_hadir`, difilter `sekolah_id`);
 *   - `tusmart` → Notulen Rapat aplikasi TUSmart (lihat `inc/ttd_rapat_tusmart.php`), hanya bila
 *     lembaga TUSmart = sekolah yang sedang dibuka (permintaan pemilik: khusus MA. Sirojuth
 *     Tholibiin Mootilango).
 *
 * @return array<int, array<string, mixed>>
 */
function ttd_rapat_daftar(int $guruId): array
{
    $sid = sekolah_id();
    if ($guruId <= 0 || $sid <= 0) {
        return [];
    }
    $baris = all('SELECT n.*, h.nama AS nama_hadir, h.jabatan AS jabatan_hadir,
                       t.url AS ttd_url, t.dibuat AS ttd_pada, t.lebar AS ttd_lebar,
                       t.tinggi AS ttd_tinggi, t.oleh AS ttd_oleh
                FROM notulen_hadir h
                JOIN notulen n ON n.id = h.notulen_id
                LEFT JOIN notulen_ttd t ON t.notulen_id = h.notulen_id AND t.pihak = ?
                WHERE h.guru_id = ? AND n.sekolah_id = ?
                ORDER BY n.tanggal DESC, n.id DESC', [ttd_rapat_kunci($guruId), $guruId, $sid]);
    foreach ($baris as $i => $r) {
        $baris[$i]['sumber'] = 'eguru';
        $baris[$i]['kunci'] = ttd_rapat_kunci($guruId);
        $baris[$i]['guru_id_sumber'] = $guruId;
        $baris[$i]['jumlah_hadir'] = count(notulen_peserta((int) $r['id']));
    }
    foreach (tusmart_rapat_daftar($guruId) as $r) {
        $baris[] = $r;
    }
    // Urutkan ulang (tanggal terbaru lebih dulu) karena dua sumber berbeda digabung.
    usort($baris, static function (array $a, array $b): int {
        $t = strcmp((string) ($b['tanggal'] ?? ''), (string) ($a['tanggal'] ?? ''));
        return $t !== 0 ? $t : ((int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0));
    });
    return $baris;
}

/**
 * Satu rapat yang boleh dilihat/ditandatangani guru ini — null bila rapat itu bukan milik
 * sekolahnya ATAU namanya tidak dicentang operator (anti-IDOR, satu sumber aturan dengan daftar).
 */
function ttd_rapat_satu(int $notulenId, int $guruId, string $sumber = 'eguru'): ?array
{
    if ($notulenId <= 0 || $guruId <= 0) {
        return null;
    }
    $sumber = ttd_rapat_sumber_sah($sumber);
    foreach (ttd_rapat_daftar($guruId) as $r) {
        if ((int) $r['id'] === $notulenId && (string) ($r['sumber'] ?? 'eguru') === $sumber) {
            return $r;
        }
    }
    return null;
}

/** Berapa rapat yang sudah dicentang operator tetapi BELUM guru ini tandatangani (lencana menu). */
function ttd_rapat_belum(int $guruId): int
{
    if ($guruId <= 0) {
        return 0;
    }
    $n = 0;
    foreach (ttd_rapat_daftar($guruId) as $r) {
        if (trim((string) ($r['ttd_url'] ?? '')) === '') {
            $n++;
        }
    }
    return $n;
}

/**
 * Halaman "Tanda Tangan Rapat" pada akun guru (`ttd_rapat.php`).
 *
 * Tanpa parameter → daftar rapat; dengan `?id=N` → rincian rapat + panel tanda tangan.
 */
function ttd_rapat_halaman(): void
{
    require_login();
    require_sekolah();

    $guruId = ttd_rapat_guru_id();
    $guru = $guruId > 0
        ? one('SELECT * FROM guru WHERE id = ? AND sekolah_id = ?', [$guruId, sekolah_id()])
        : null;
    if (!$guru) {
        tolak403('Menu Tanda Tangan Rapat hanya untuk akun guru yang tertaut ke data guru pada '
            . 'sekolah/madrasah ini. Hubungi Admin Operator bila akun Anda belum ditautkan.');
    }

    $daftar = ttd_rapat_daftar($guruId);
    $belum = 0;
    $sudah = 0;
    foreach ($daftar as $r) {
        if (trim((string) ($r['ttd_url'] ?? '')) === '') {
            $belum++;
        } else {
            $sudah++;
        }
    }

    $idLihat = (int) ($_GET['id'] ?? 0);
    $sumberLihat = ttd_rapat_sumber_sah((string) ($_GET['sumber'] ?? 'eguru'));
    $lihat = $idLihat > 0 ? ttd_rapat_satu($idLihat, $guruId, $sumberLihat) : null;
    if ($idLihat > 0 && !$lihat) {
        flash('Rapat itu tidak tercatat atas nama Anda pada daftar hadir, sehingga tidak dapat '
            . 'ditandatangani.', 'err');
        redirect('ttd_rapat.php');
    }

    $namaSekolah = nama_sekolah_tampil();
    page_top('Tanda Tangan Rapat',
        $lihat !== null ? 'Rapat ' . tanggal_id((string) $lihat['tanggal'], true)
            : 'Daftar hadir rapat yang perlu Anda tandatangani &middot; ' . tetap_kata($namaSekolah));

    if ($lihat === null) {
        ?>
<div class="grid g3">
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('calendar', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= count($daftar) ?></div>
      <div class="stat-lbl">Rapat atas nama Anda</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic s"><?= ikon('check', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $sudah ?></div>
      <div class="stat-lbl">Sudah ditandatangani</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic o"><?= ikon('edit', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $belum ?></div>
      <div class="stat-lbl">Belum ditandatangani</div></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('edit', 16) ?> Tanda Tangani Rapat</h2>
      <p>Rapat yang <b>daftar hadirnya dicentang atas nama Anda</b> oleh Admin Operator pada menu
        Notulen Rapat <?= tetap_kata($namaSekolah) ?><?php if (tusmart_aktif()): ?>, termasuk rapat yang
        dibuat <b>Kepala Tata Usaha di aplikasi TUSmart</b><?php endif; ?>. Tanda tangan dibuat
        langsung pada layar (jari, stylus, atau tetikus) dan langsung tercetak pada lembar Daftar
        Hadir rapat.</p>
    </div>
    <span class="chip"><?= $belum ?> belum ditandatangani</span>
  </div>

  <?php if (!$daftar): ?>
    <div class="empty">
      <?= ikon('calendar', 34) ?>
      <h3>Belum ada rapat yang perlu ditandatangani</h3>
      <p>Rapat akan muncul di sini setelah Admin Operator mencentang nama Anda pada daftar hadir
        rapat (menu <b>Notulen Rapat</b>). Rapat yang tidak dicentang tidak ditampilkan.</p>
    </div>
  <?php else: ?>
    <div class="grid g2 ttdrapat-kartu-grid">
      <?php foreach ($daftar as $r):
          $url = trim((string) ($r['ttd_url'] ?? ''));
          $nid = (int) $r['id'];
          $sumber = (string) ($r['sumber'] ?? 'eguru');
          $tautan = 'ttd_rapat.php?id=' . $nid . ($sumber === 'tusmart' ? '&sumber=tusmart' : ''); ?>
        <div class="card ttdrapat-kartu">
          <div class="ttdrapat-kartu-head">
            <div>
              <div class="xs mut"><?= e(tanggal_id((string) $r['tanggal'], true)) ?>
                <?php if (ttd_rapat_sumber_label($sumber) !== ''): ?>
                  <span class="pill pill-gold" style="margin-left:6px"><?= e(ttd_rapat_sumber_label($sumber)) ?></span>
                <?php endif; ?>
              </div>
              <h3><?= tetap_kata((string) ($r['judul'] ?? 'Rapat')) ?></h3>
              <div class="xs mut">
                <?php if (trim((string) ($r['tempat'] ?? '')) !== ''): ?>
                  <?= ikon('lokasi', 12) ?> <?= e((string) $r['tempat']) ?> &middot;
                <?php endif; ?>
                Pimpinan: <?= e(trim((string) ($r['pimpinan'] ?? '')) !== '' ? (string) $r['pimpinan'] : '-') ?>
              </div>
              <div class="xs mut">Tercatat sebagai: <b><?= e((string) ($r['nama_hadir'] ?? '')) ?></b>
                <?= e(trim((string) ($r['jabatan_hadir'] ?? '')) !== '' ? '(' . $r['jabatan_hadir'] . ')' : '') ?></div>
            </div>
            <?php if ($url !== ''): ?>
              <span class="pill pill-ok">Sudah tanda tangan</span>
            <?php else: ?>
              <span class="pill pill-warn">Belum tanda tangan</span>
            <?php endif; ?>
          </div>
          <?php if ($url !== ''): ?>
            <div class="xs mut">Ditandatangani
              <?= e($r['ttd_pada'] ? waktu_id_polos((string) $r['ttd_pada']) : '') ?></div>
          <?php endif; ?>
          <div class="ttdrapat-kartu-kaki">
            <span class="xs mut"><?= (int) ($r['jumlah_hadir'] ?? 0) ?> guru tercatat hadir</span>
            <a class="btn <?= $url !== '' ? 'btn-ghost' : 'btn-primary' ?> btn-sm" href="<?= e($tautan) ?>">
              <?= ikon('edit', 14) ?> <?= $url !== '' ? 'Lihat &amp; Ulangi' : 'Buka &amp; Tanda Tangan' ?></a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
        <?php
        page_bottom();
        return;
    }

    // ---------------------------------------------------------------- rincian + tanda tangan
    $nid = (int) $lihat['id'];
    $sumber = ttd_rapat_sumber_sah((string) ($lihat['sumber'] ?? 'eguru'));
    $kunci = (string) ($lihat['kunci'] ?? ttd_rapat_kunci($guruId));
    // Rapat TUSmart: tanda tangan tersimpan pada basis data TUSmart (kunci `hadir:g<id guru TUSmart>`).
    $peta = $sumber === 'tusmart' ? tusmart_ttd_peta($lihat) : notulen_ttd_peta($nid);
    $barisTtd = $peta[$kunci] ?? null;
    $urlTtd = trim((string) ($lihat['ttd_url'] ?? ''));
    $jumlahHadir = (int) ($lihat['jumlah_hadir'] ?? 0);
    // JSON tanda tangan HANYA memuat baris milik guru ini: halaman ini bukan lembar cetak, guru
    // tidak boleh menawarkan tanda tangan pihak lain (server juga menolaknya).
    $pihak = [$kunci => [
        'label' => (string) ($lihat['nama_hadir'] ?? ''),
        'nama'  => (string) ($lihat['nama_hadir'] ?? ''),
        'jenis' => 'hadir',
    ]];
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= tetap_kata((string) ($lihat['judul'] ?? 'Rapat')) ?>
        <?php if (ttd_rapat_sumber_label($sumber) !== ''): ?>
          <span class="pill pill-gold" style="vertical-align:middle"><?= e(ttd_rapat_sumber_label($sumber)) ?></span>
        <?php endif; ?>
      </h2>
      <p>Rapat <b><?= e(tanggal_id((string) $lihat['tanggal'], true)) ?></b>
        <?php if (trim((string) ($lihat['waktu'] ?? '')) !== ''): ?>
          &middot; <?= e((string) $lihat['waktu']) ?>
        <?php endif; ?>
        <?php if (trim((string) ($lihat['tempat'] ?? '')) !== ''): ?>
          &middot; <?= e((string) $lihat['tempat']) ?>
        <?php endif; ?>
      </p>
    </div>
    <div class="flex">
      <a class="btn btn-ghost btn-sm" href="ttd_rapat.php"><?= ikon('prev', 15) ?> Daftar Rapat</a>
    </div>  </div>

  <?php if ($urlTtd === ''): ?>
    <div class="alert alert-warn" style="margin-bottom:16px">
      <?= ikon('edit', 16) ?>
      <span>Daftar hadir rapat ini <b>belum Anda tandatangani</b>. Tekan <b>Tanda Tangan</b> di bawah,
        lalu coretkan tanda tangan pada kotak yang muncul.</span>
    </div>
  <?php else: ?>
    <div class="alert alert-ok" style="margin-bottom:16px">
      <?= ikon('check', 16) ?>
      <span>Tanda tangan Anda sudah tersimpan
        <?= $barisTtd && trim((string) ($barisTtd['dibuat'] ?? '')) !== ''
            ? 'pada ' . e(waktu_id_polos((string) $barisTtd['dibuat'])) : '' ?>.
        Tekan <b>Ulangi</b> bila ingin menggantinya.</span>
    </div>
  <?php endif; ?>

  <div class="table-wrap">
    <table class="tbl ttdrapat-info">
      <tbody>
        <tr><td>Nama pada daftar hadir</td><td><b><?= e((string) ($lihat['nama_hadir'] ?? '')) ?></b></td></tr>
        <tr><td>Jabatan</td><td><?= e(trim((string) ($lihat['jabatan_hadir'] ?? '')) !== ''
            ? (string) $lihat['jabatan_hadir'] : '-') ?></td></tr>
        <tr><td>Tanggal</td><td><?= e(tanggal_id((string) $lihat['tanggal'], true)) ?></td></tr>
        <tr><td>Waktu</td><td><?= e(trim((string) ($lihat['waktu'] ?? '')) !== ''
            ? (string) $lihat['waktu'] : '-') ?></td></tr>
        <tr><td>Tempat</td><td><?= e(trim((string) ($lihat['tempat'] ?? '')) !== ''
            ? (string) $lihat['tempat'] : '-') ?></td></tr>
        <tr><td>Pimpinan Rapat</td><td><?= e(trim((string) ($lihat['pimpinan'] ?? '')) !== ''
            ? (string) $lihat['pimpinan'] : '-') ?><?php
              if (trim((string) ($lihat['pimpinan_jabatan'] ?? '')) !== '') {
                  echo ' <span class="xs mut">(' . e((string) $lihat['pimpinan_jabatan']) . ')</span>';
              } ?></td></tr>
        <tr><td>Notulis</td><td><?= e(trim((string) ($lihat['notulis'] ?? '')) !== ''
            ? (string) $lihat['notulis'] : '-') ?></td></tr>
        <tr><td>Jumlah guru hadir</td><td><?= $jumlahHadir ?> guru</td></tr>
      </tbody>
    </table>
  </div>

  <?php if (trim((string) ($lihat['pembahasan'] ?? '')) !== ''): ?>
    <div style="margin-top:18px">
      <div class="nav-sec" style="margin-top:0">Pembahasan Rapat</div>
      <p style="white-space:pre-line;line-height:1.6"><?= tetap_kata((string) $lihat['pembahasan']) ?></p>
    </div>
  <?php endif; ?>

  <div class="nav-sec">Tanda Tangan Anda</div>
  <div class="ttdrapat-panel" data-ttd-sel="<?= e($kunci) ?>">
    <?= notulen_ttd_gambar($peta, $kunci) ?>
    <?= notulen_ttd_tombol($kunci, $barisTtd !== null, 'ttd-tombol', true) ?>
  </div>
  <p class="hint">Tanda tangan dibuat langsung pada kotak yang muncul — bisa memakai jari/stylus di
    HP atau tablet, dan tetikus pada komputer. Tanda tangan yang sudah tersimpan akan tampil pada
    kolom tanda tangan lembar <b>Daftar Hadir Rapat</b> yang dicetak
    <?= $sumber === 'tusmart' ? 'Kepala Tata Usaha (TUSmart)' : 'Admin Operator' ?>.</p>

  <script type="application/json" id="ttdData"><?= notulen_ttd_skrip_json($nid, $pihak, $peta, $sumber) ?></script>
  <?= notulen_ttd_dialog($nid) ?>
</div>
    <?php
    page_bottom();
}
