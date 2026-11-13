<?php
/**
 * REKAP KEPALA SEKOLAH/MADRASAH & ADMIN OPERATOR — halaman Super Admin
 * (`rekap_kepala_operator.php`, v1.89.0).
 *
 * Permintaan pemilik: *"Pada halaman super admin pada bagian layanan tambahkan menu yang menampilkan
 * rekapan: No, nama sekolah/madrasah, nama kepala sekolah, nama operator, Nomer HP kepala sekolah.
 * Tujuannya untuk mempermudah super admin mengecek kepala sekolah dan operator disetiap lembaga.
 * tambahkan fitur cetak pdf"*.
 *
 * Isinya hanya MEMBACA data (tidak mengubah apa pun). Seluruh datanya diambil dari
 * `rekap_kepala_operator()` di `inc/helpers.php` supaya hasil layar & hasil cetak
 * (`cetak_rekap_kepala_operator.php`) selalu sama — jangan menulis query sendiri di sini.
 *
 * Halaman ini LINTAS sekolah → istilah generik "Sekolah/Madrasah" (tidak diterjemahkan menjadi
 * "Madrasah" walaupun konteks sekolah yang sedang dibuka adalah madrasah), dan hanya untuk Super Admin.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_super();
$GLOBALS['__vs_istilah_nonaktif'] = true;

$rekap = rekap_kepala_operator();
$tot = $rekap['tot'];
$cari = trim((string) ($_GET['cari'] ?? ''));
$baris = rekap_kepala_operator_saring($rekap['baris'], $cari);

// Tautan lembar cetak membawa saringan yang sedang dilihat (yang tampil = yang tercetak).
$qCetak = $cari !== '' ? ['cari' => $cari] : [];

page_top('Rekap Kepala & Operator', 'Super Admin &middot; nama kepala sekolah/madrasah, admin operator, '
    . 'dan nomor HP tiap lembaga');
?>

<div class="grid g4">
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('building', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $tot['lembaga'] ?></div>
      <div class="stat-lbl">Lembaga terdaftar (<?= (int) $tot['aktif'] ?> aktif,
        <?= (int) $tot['nonaktif'] ?> nonaktif/belum disetujui)</div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic g"><?= ikon('users', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $tot['punya_kepala'] ?></div>
      <div class="stat-lbl">Lembaga punya akun kepala sekolah/madrasah<?= $tot['tanpa_kepala'] > 0
        ? ' &middot; ' . (int) $tot['tanpa_kepala'] . ' belum' : '' ?></div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic o"><?= ikon('key', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $tot['punya_operator'] ?></div>
      <div class="stat-lbl">Lembaga punya akun operator (<?= (int) $tot['akun_operator'] ?> akun)<?= $tot['tanpa_operator'] > 0
        ? ' &middot; ' . (int) $tot['tanpa_operator'] . ' belum' : '' ?></div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic s"><?= ikon('whatsapp', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $tot['tanpa_hp'] ?></div>
      <div class="stat-lbl">Lembaga belum mengisi nomor HP/telepon kepala</div></div>
  </div>
</div>

<div class="card mt16">
  <div class="card-head">
    <div>
      <h2>Rekap Kepala Sekolah/Madrasah &amp; Admin Operator</h2>
      <p><?= count($baris) ?> lembaga ditampilkan<?= $cari !== '' ? ' (disaring dengan kata kunci
        &ldquo;' . e($cari) . '&rdquo;)' : '' ?>. Halaman ini hanya menampilkan data — untuk mengubah
        nama kepala atau menambah operator, buka <b>Kelola Sekolah</b> atau menu <b>Akun Operator</b>
        pada akun kepala sekolah/madrasah.</p>
    </div>
    <div class="flex no-print" style="gap:8px">
      <a class="btn btn-primary btn-sm" target="_blank" rel="noopener"
         href="cetak_rekap_kepala_operator.php<?= $qCetak ? '?' . e(http_build_query($qCetak)) : '' ?>">
        <?= ikon('print', 15) ?> Cetak / Simpan PDF
      </a>
    </div>
  </div>

  <form class="filterbar mb16" method="get">
    <div>
      <label class="f" for="cari">Cari lembaga / kepala / operator / nomor HP</label>
      <input type="search" id="cari" name="cari" value="<?= e($cari) ?>"
             placeholder="mis. MTsS, Ahmad, operator, 0812...">
    </div>
    <button class="btn btn-ghost" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
    <?php if ($cari !== ''): ?><a class="btn btn-ghost" href="rekap_kepala_operator.php">Reset</a><?php endif; ?>
  </form>

  <?php if (!$baris): ?>
    <div class="empty">
      <?= ikon('building', 34) ?>
      <h3>Belum ada lembaga yang cocok</h3>
      <p><?= $cari !== '' ? 'Tidak ada lembaga yang cocok dengan kata kunci itu — coba kata kunci lain.'
        : 'Belum ada sekolah/madrasah yang terdaftar pada aplikasi ini.' ?></p>
      <?php if ($cari !== ''): ?>
        <div class="flex" style="justify-content:center">
          <a class="btn btn-ghost" href="rekap_kepala_operator.php">Tampilkan semua lembaga</a>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblRekapKepalaOperator">
        <thead>
          <tr>
            <th class="ctr" style="width:46px">No</th>
            <th style="min-width:210px">Sekolah/Madrasah</th>
            <th style="min-width:190px">Nama Kepala Sekolah/Madrasah</th>
            <th style="min-width:190px">Nama Operator</th>
            <th style="min-width:150px">No. HP Kepala Sekolah/Madrasah</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($baris as $b): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td>
              <a href="sekolah.php?edit=<?= (int) $b['sekolah']['id'] ?>"><b><?= e(tetap_kata($b['nama'])) ?></b></a>
              <div class="tbl-sub">
                <?= e($b['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah Umum') ?><?php
                  $npsn = identitas_nilai((string) ($b['sekolah']['npsn'] ?? ''));
                  if ($npsn !== ''): ?> &middot; NPSN <?= e($npsn) ?><?php endif; ?>
                <?php if ($b['aktif']): ?>
                  <span class="pill pill-ok">Aktif</span>
                <?php elseif ($b['verifikasi'] !== 'disetujui'): ?>
                  <span class="pill pill-warn"><?= e(ucfirst($b['verifikasi'] !== '' ? $b['verifikasi'] : 'menunggu')) ?></span>
                <?php else: ?>
                  <span class="pill pill-err">Nonaktif</span>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <?php if ($b['kepala_teks'] !== ''): ?>
                <b><?= e(tetap_kata($b['kepala_teks'])) ?></b>
                <?php if ($b['kepala_dari_identitas']): ?>
                  <div class="tbl-sub"><span class="pill pill-warn">dari identitas lembaga</span>
                    <?= $b['kepala_nonaktif'] > 0 ? 'akun kepala nonaktif' : 'belum ada akun kepala' ?></div>
                <?php elseif ($b['kepala_akun']): ?>
                  <div class="tbl-sub">Akun: <?= e(implode(', ', array_column($b['kepala_akun'], 'username'))) ?>
                    <?php if ($b['kepala_nonaktif'] > 0): ?>
                      &middot; <?= (int) $b['kepala_nonaktif'] ?> akun nonaktif
                    <?php endif; ?></div>
                <?php endif; ?>
              <?php else: ?>
                <span class="mut">&mdash;</span>
                <div class="tbl-sub"><span class="pill pill-err"><?= $b['kepala_nonaktif'] > 0
                  ? 'akun kepala nonaktif' : 'belum ada akun kepala' ?></span></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($b['operator_teks'] !== ''): ?>
                <b><?= e(tetap_kata($b['operator_teks'])) ?></b>
                <div class="tbl-sub">Akun: <?= e(implode(', ', array_column($b['operator_akun'], 'username'))) ?>
                  <?php if (count($b['operator_akun']) > 1): ?>
                    &middot; <?= count($b['operator_akun']) ?> akun
                  <?php endif; ?></div>
              <?php else: ?>
                <span class="mut">&mdash;</span>
                <div class="tbl-sub"><span class="pill pill-warn"><?= $b['operator_nonaktif'] > 0
                  ? 'operator nonaktif' : 'belum ada operator' ?></span></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($b['hp'] !== ''): ?>
                <?= e($b['hp']) ?>
                <?php // Tombol WA di sini hanya MEMBUKA percakapan (tanpa pesan otomatis): halaman ini
                      // untuk mengecek/menghubungi, bukan mengirim pengingat aktivasi seperti pada
                      // kolom Aksi Daftar Sekolah/Madrasah (yang memakai wa_lembaga_tombol()). ?>
                <?php if ($b['hp_wa'] !== ''): ?>
                  <div class="tbl-sub">
                    <a class="btn btn-wa btn-sm" href="https://wa.me/<?= e($b['hp_wa']) ?>"
                       target="_blank" rel="noopener noreferrer"
                       title="Buka WhatsApp nomor kepala sekolah/madrasah"
                       aria-label="Buka WhatsApp nomor kepala sekolah/madrasah"><?= ikon('whatsapp', 14) ?> WA</a>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <span class="mut">&mdash;</span>
                <div class="tbl-sub"><span class="pill pill-warn">belum diisi</span></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="2" style="text-align:right">JUMLAH (<?= count($baris) ?> lembaga)</th>
            <th><?= (int) $tot['punya_kepala'] ?> punya akun kepala<?= $tot['tanpa_kepala'] > 0
              ? ', ' . (int) $tot['tanpa_kepala'] . ' belum' : '' ?></th>
            <th><?= (int) $tot['punya_operator'] ?> punya operator<?= $tot['tanpa_operator'] > 0
              ? ', ' . (int) $tot['tanpa_operator'] . ' belum' : '' ?></th>
            <th><?= (int) $tot['tanpa_hp'] ?> belum mengisi nomor HP</th>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card mt16">
  <div class="card-head">
    <div><h2>Catatan</h2>
      <p>Nama kepala sekolah/madrasah diambil dari <b>akun kepala</b> tiap lembaga. Bila lembaga belum
        punya akun kepala, yang ditampilkan adalah <b>nama kepala pada identitas lembaga</b> dan diberi
        penanda &ldquo;dari identitas lembaga&rdquo;.</p>
    </div>
  </div>
  <ul class="apps-catatan">
    <li><b>Nama operator</b> dapat lebih dari satu — akun kepala sekolah/madrasah menambahkannya
      sendiri lewat menu <b>Akun Operator</b> pada akunnya.</li>
    <li><b>No. HP Kepala Sekolah/Madrasah</b> dibaca dari kolom <b>Telepon / HP (WhatsApp)</b> pada
      Identitas &amp; Pengaturan lembaga (cadangan: nomor kontak saat mendaftar). Tombol <b>WA</b>
      langsung membuka WhatsApp nomor itu.</li>
    <li>Lembaga <b>nonaktif</b> tetap ditampilkan supaya tidak ada yang terlewat; keadaan
      aktif/nonaktif terlihat pada kolom Sekolah/Madrasah.</li>
    <li>Tombol <b>Cetak / Simpan PDF</b> membuka lembar cetak A4 landscape yang isinya sama dengan
      tabel ini (saringan yang sedang dipakai ikut tercetak).</li>
  </ul>
</div>

<?php
page_bottom();
