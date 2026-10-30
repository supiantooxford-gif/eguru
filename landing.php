<?php
/**
 * HALAMAN DEPAN (LANDING PAGE) — pengisian oleh SUPER ADMIN.
 *
 * Permintaan pemilik: "Buat halaman depan aplikasi menjadi tampilan web landing page yang
 * menampilkan beranda, Slider (Judul, gambar, keterangan), fitur aplikasi, keunggulan,
 * Total Pengguna (Pengawas, Kepala Sekolah, Madrasah, Guru), testimoni, kontak. Panel login tetap
 * dipertahankan. Untuk mengisi halaman landing page di isi lewat akun super admin."
 *
 * Isi halaman depan (label beranda, judul tiap bagian, kontak, slider, fitur, keunggulan,
 * testimoni) diisi dari halaman ini dan disimpan pada `app_pengaturan` (awalan kunci `landing_`)
 * serta tabel `landing_slider`/`landing_fitur`/`landing_unggulan`/`landing_testimoni`.
 *
 * ANGKA bagian **Total Pengguna DIHITUNG OTOMATIS** dari data aplikasi (permintaan pemilik: selalu
 * terbaru) — di tab itu Super Admin hanya dapat mengubah LABEL tiap kartu dan menyembunyikan kartu
 * yang tidak ingin ditampilkan; angkanya tidak dapat diketik manual.
 *
 * Blok "Tentang Aplikasi" (dua foto 3x4 + nama + keterangan) TIDAK diduplikasi di sini: isinya
 * tetap diisi pada menu **Tentang Aplikasi** (`tentang.php`) supaya hanya ada SATU sumber data —
 * halaman ini hanya mengatur judul/subjudul bagiannya.
 *
 * Pesan dari formulir kontak halaman depan masuk ke akun Super Admin (tab "Pesan Masuk").
 *
 * Halaman ini bersifat TINGKAT APLIKASI dan LINTAS SEKOLAH → istilah generik "Sekolah/Madrasah".
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

require_super();
$GLOBALS['__vs_istilah_nonaktif'] = true;   // halaman lintas sekolah: istilah generik

$TAB_SAH = ['beranda', 'slider', 'fitur', 'unggulan', 'pengguna', 'testimoni', 'tentang', 'kontak', 'pesan'];

/** Kunci teks yang boleh disimpan per tab (WHITELIST — jangan menerima kunci dari pengguna). */
function landing_kunci_tab(string $tab): array
{
    $pengguna = [];
    foreach (array_keys(landing_pengguna_peran()) as $peran) {
        $pengguna[] = 'pengguna_' . $peran . '_label';
        $pengguna[] = 'pengguna_' . $peran . '_tampil';
    }
    return match ($tab) {
        'beranda'   => ['label'],
        'fitur'     => ['fitur_judul', 'fitur_sub'],
        'unggulan'  => ['unggulan_judul', 'unggulan_sub'],
        'pengguna'  => array_merge(['pengguna_judul', 'pengguna_sub'], $pengguna),
        'testimoni' => ['testimoni_judul', 'testimoni_judul_besar', 'testimoni_sub'],
        'tentang'   => ['tentang_judul', 'tentang_sub'],
        'kontak'    => ['kontak_judul', 'kontak_sub', 'kontak_alamat', 'kontak_telepon',
                        'kontak_email', 'kontak_jam', 'kontak_maps', 'kontak_ajakan'],
        default     => [],
    };
}

/** Batas panjang tiap kunci (yang tidak terdaftar memakai LANDING_TEKS_MAKS). */
function landing_batas_kunci(string $kunci): int
{
    return match (true) {
        str_starts_with($kunci, 'judul') || $kunci === 'label' => LANDING_JUDUL_MAKS,
        (bool) preg_match('/_tampil$/', $kunci)                => 1,
        default                                                => LANDING_TEKS_MAKS,
    };
}

/** Kembali ke halaman ini pada tab tertentu. */
function landing_kembali(string $tab = 'beranda', array $q = []): void
{
    if (!in_array($tab, ['beranda', 'slider', 'fitur', 'unggulan', 'pengguna', 'testimoni', 'tentang', 'kontak', 'pesan'], true)) {
        $tab = 'beranda';
    }
    $url = 'landing.php?tab=' . $tab;
    foreach ($q as $k => $v) {
        $url .= '&' . rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
    }
    redirect($url);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Total kiriman melebihi post_max_size → PHP membuang SELURUH $_POST & $_FILES tanpa pesan.
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST) && empty($_FILES)) {
        flash('Ukuran kiriman melebihi batas server (' . ini_get('post_max_size') . '). '
            . 'Perkecil foto lalu simpan ulang.', 'err');
        landing_kembali('slider');
    }

    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');
    $tab  = (string) ($_POST['tab'] ?? 'beranda');

    // ---------------------------------------------------------------- teks (per tab)
    if ($aksi === 'teks') {
        $kunci = landing_kunci_tab($tab);
        if (!$kunci) {
            flash('Bagian itu tidak dikenal.', 'err');
            landing_kembali($tab);
        }
        $nilai = [];
        foreach ($kunci as $k) {
            if ((bool) preg_match('/_tampil$/', $k)) {
                // Kotak centang: tidak dikirim berarti TIDAK dicentang.
                $nilai[$k] = !empty($_POST[$k]) ? '1' : '0';
                continue;
            }
            if (!array_key_exists($k, $_POST)) {
                continue;
            }
            $nilai[$k] = landing_potong((string) $_POST[$k], landing_batas_kunci($k));
        }
        landing_simpan_teks($nilai);
        flash('Isi bagian "' . (string) ($tab === 'beranda' ? 'Beranda' : ucfirst($tab)) . '" disimpan dan langsung tampil pada halaman depan.');
        landing_kembali($tab);
    }

    // ---------------------------------------------------------------- daftar item
    $jenis = landing_jenis_sah((string) ($_POST['jenis'] ?? ''));
    $id    = (int) ($_POST['id'] ?? 0);
    if ($aksi === 'tambah' && $jenis !== '') {
        $galat = '';
        if ($jenis === 'slider') {
            $judul = landing_potong((string) ($_POST['judul'] ?? ''), LANDING_JUDUL_MAKS);
            $ket   = landing_potong((string) ($_POST['keterangan'] ?? ''), LANDING_ISI_MAKS);
            if ($judul === '') {
                $galat = 'Judul slider wajib diisi.';
            } else {
                $foto = null;
                if (!empty($_FILES['foto']['name'])) {
                    $hasil = landing_unggah_foto_berkas('foto');
                    if (empty($hasil['ok'])) {
                        $galat = 'Foto: ' . (string) $hasil['pesan'];
                    } else {
                        $foto = (string) $hasil['url'];
                    }
                }
                if ($galat === '') {
                    landing_item_tambah('slider', ['judul' => $judul, 'keterangan' => $ket, 'foto' => $foto]);
                    flash('Slide "' . $judul . '" ditambahkan ke slider beranda.');
                }
            }
        } elseif ($jenis === 'testimoni') {
            $nama = landing_potong((string) ($_POST['nama'] ?? ''), LANDING_NAMA_MAKS);
            $isi  = landing_potong((string) ($_POST['isi'] ?? ''), LANDING_ISI_MAKS);
            if ($nama === '') {
                $galat = 'Nama pemberi testimoni wajib diisi.';
            } elseif ($isi === '') {
                $galat = 'Isi testimoni wajib diisi.';
            } else {
                $foto = null;
                if (!empty($_FILES['foto']['name'])) {
                    $hasil = landing_unggah_foto_berkas('foto');
                    if (empty($hasil['ok'])) {
                        $galat = 'Foto: ' . (string) $hasil['pesan'];
                    } else {
                        $foto = (string) $hasil['url'];
                    }
                }
                if ($galat === '') {
                    landing_item_tambah($jenis, [
                        'nama'    => $nama,
                        'jabatan' => landing_potong((string) ($_POST['jabatan'] ?? ''), LANDING_NAMA_MAKS),
                        'isi'     => $isi,
                        'foto'    => $foto,
                    ]);
                    flash('Testimoni dari "' . $nama . '" ditambahkan ke halaman depan.');
                }
            }
        } else {
            $judul = landing_potong((string) ($_POST['judul'] ?? ''), LANDING_JUDUL_MAKS);
            if ($judul === '') {
                $galat = 'Judul wajib diisi.';
            } else {
                landing_item_tambah($jenis, [
                    'ikon'  => landing_ikon_sah((string) ($_POST['ikon'] ?? '')),
                    'judul' => $judul,
                    'teks'  => landing_potong((string) ($_POST['teks'] ?? ''), LANDING_TEKS_MAKS),
                ]);
                flash(landing_jenis_label($jenis) . ': "' . $judul . '" ditambahkan ke halaman depan.');
            }
        }
        if ($galat !== '') {
            flash($galat, 'err');
        }
        landing_kembali($jenis);
    }

    $item = $jenis !== '' ? landing_item_by_id($jenis, $id) : null;
    if ($item && $aksi === 'ubah') {
        if ($jenis === 'slider') {
            $judul = landing_potong((string) ($_POST['judul'] ?? ''), LANDING_JUDUL_MAKS);
            if ($judul === '') {
                flash('Judul slider wajib diisi.', 'err');
            } else {
                landing_item_simpan('slider', $id, [
                    'judul'      => $judul,
                    'keterangan' => landing_potong((string) ($_POST['keterangan'] ?? ''), LANDING_ISI_MAKS),
                    'aktif'      => !empty($_POST['aktif']) ? 1 : 0,
                ]);
                $galatFoto = '';
                if (!empty($_POST['hapus_foto'])) {
                    landing_slider_foto($id, '');
                } elseif (!empty($_FILES['foto']['name'])) {
                    $hasil = landing_unggah_foto_berkas('foto');
                    if (empty($hasil['ok'])) {
                        $galatFoto = 'Foto gagal diganti: ' . (string) $hasil['pesan'];
                    } else {
                        landing_slider_foto($id, (string) $hasil['url']);
                    }
                }
                flash($galatFoto !== '' ? $galatFoto : 'Slide "' . $judul . '" disimpan.',
                    $galatFoto !== '' ? 'err' : 'ok');
            }
        } elseif ($jenis === 'testimoni') {
            $nama = landing_potong((string) ($_POST['nama'] ?? ''), LANDING_NAMA_MAKS);
            $isi  = landing_potong((string) ($_POST['isi'] ?? ''), LANDING_ISI_MAKS);
            if ($nama === '' || $isi === '') {
                flash('Nama dan isi testimoni wajib diisi.', 'err');
            } else {
                landing_item_simpan($jenis, $id, [
                    'nama'    => $nama,
                    'jabatan' => landing_potong((string) ($_POST['jabatan'] ?? ''), LANDING_NAMA_MAKS),
                    'isi'     => $isi,
                    'aktif'   => !empty($_POST['aktif']) ? 1 : 0,
                ]);
                $galatFoto = '';
                if (!empty($_POST['hapus_foto'])) {
                    landing_testimoni_foto($id, '');
                } elseif (!empty($_FILES['foto']['name'])) {
                    $hasil = landing_unggah_foto_berkas('foto');
                    if (empty($hasil['ok'])) {
                        $galatFoto = 'Foto gagal diganti: ' . (string) $hasil['pesan'];
                    } else {
                        landing_testimoni_foto($id, (string) $hasil['url']);
                    }
                }
                flash($galatFoto !== '' ? $galatFoto : 'Testimoni "' . $nama . '" disimpan.',
                    $galatFoto !== '' ? 'err' : 'ok');
            }
        } else {
            $judul = landing_potong((string) ($_POST['judul'] ?? ''), LANDING_JUDUL_MAKS);
            if ($judul === '') {
                flash('Judul wajib diisi.', 'err');
            } else {
                landing_item_simpan($jenis, $id, [
                    'ikon'  => landing_ikon_sah((string) ($_POST['ikon'] ?? '')),
                    'judul' => $judul,
                    'teks'  => landing_potong((string) ($_POST['teks'] ?? ''), LANDING_TEKS_MAKS),
                    'aktif' => !empty($_POST['aktif']) ? 1 : 0,
                ]);
                flash(landing_jenis_label($jenis) . ': "' . $judul . '" disimpan.');
            }
        }
        landing_kembali($jenis);
    }

    if ($item && ($aksi === 'aktif' || $aksi === 'nonaktif')) {
        q('UPDATE ' . landing_tabel($jenis) . ' SET aktif = ? WHERE id = ?', [$aksi === 'aktif' ? 1 : 0, $id]);
        $namaItem = (string) ($item['judul'] ?? $item['nama'] ?? '');
        flash($aksi === 'aktif'
            ? '"' . $namaItem . '" diaktifkan kembali (tampil di halaman depan).'
            : '"' . $namaItem . '" disembunyikan dari halaman depan (datanya tetap tersimpan).',
            $aksi === 'aktif' ? 'ok' : 'warn');
        landing_kembali($jenis);
    }

    if ($item && ($aksi === 'naik' || $aksi === 'turun')) {
        landing_item_geser($jenis, $id, $aksi === 'naik' ? -1 : 1);
        landing_kembali($jenis);
    }

    if ($item && $aksi === 'hapus') {
        $namaItem = (string) ($item['judul'] ?? $item['nama'] ?? '');
        landing_item_hapus($jenis, $id);
        flash('"' . $namaItem . '" dihapus dari halaman depan.', 'warn');
        landing_kembali($jenis);
    }

    // ---------------------------------------------------------------- pesan kontak
    if ($aksi === 'pesan_baca' || $aksi === 'pesan_belum') {
        landing_pesan_tandai($id, $aksi === 'pesan_baca');
        landing_kembali('pesan');
    }
    if ($aksi === 'pesan_hapus') {
        landing_pesan_hapus($id);
        flash('Pesan kontak dihapus.', 'warn');
        landing_kembali('pesan');
    }
    if ($aksi === 'pesan_semua') {
        $n = landing_pesan_baru();
        q('UPDATE landing_pesan SET dibaca = 1 WHERE dibaca = 0');
        flash($n > 0 ? $n . ' pesan ditandai sudah dibaca.' : 'Tidak ada pesan baru.', $n > 0 ? 'ok' : 'warn');
        landing_kembali('pesan');
    }

    flash('Aksi tidak dikenal.', 'err');
    landing_kembali('beranda');
}

// ------------------------------------------------------------------ data untuk tampilan
$tab = (string) ($_GET['tab'] ?? 'beranda');
if (!in_array($tab, $TAB_SAH, true)) {
    $tab = 'beranda';
}
$editId = (int) ($_GET['edit'] ?? 0);
$editJenis = landing_jenis_sah((string) ($_GET['jenis'] ?? ''));
if ($editId > 0 && $editJenis !== '') {
    $tab = $editJenis;   // membuka panel "Ubah" sekaligus memindahkan tabnya
}

$ringkas = landing_ringkas();
$pesanBaru = landing_pesan_baru();
$pesan = $tab === 'pesan' ? landing_pesan_list(200) : [];
$itemEdit = ($editId > 0 && $editJenis !== '') ? landing_item_by_id($editJenis, $editId) : null;

page_top('Halaman Depan (Landing)');
?>
<div class="alert alert-ok" style="margin-bottom:18px">
  <?= ikon('star', 16) ?>
  <span>Seluruh isi di halaman ini tampil pada <b>halaman depan aplikasi</b>
    (<b>beranda, slider, fitur aplikasi, keunggulan, Total Pengguna, testimoni, tentang aplikasi,
    kontak</b>) — halaman yang sama dengan <b>panel masuk</b> pengguna. Isinya berlaku untuk
    <b>semua sekolah/madrasah</b> (pengaturan tingkat aplikasi).</span>
  <a class="btn btn-ghost btn-sm" href="login.php" target="_blank" rel="noopener" style="margin-left:auto">
    <?= ikon('eye', 15) ?> Lihat Halaman Depan
  </a>
</div>

<div class="grid g4" style="gap:12px">
  <div class="nilai-chip"><b><?= (int) $ringkas['slider']['aktif'] ?></b><span>Slide tampil</span></div>
  <div class="nilai-chip"><b><?= (int) $ringkas['fitur']['aktif'] ?></b><span>Fitur tampil</span></div>
  <div class="nilai-chip"><b><?= (int) $ringkas['unggulan']['aktif'] ?></b><span>Keunggulan tampil</span></div>
  <div class="nilai-chip"><b><?= (int) $ringkas['testimoni']['aktif'] ?></b><span>Testimoni tampil</span></div>
</div>
<div class="grid g4 mt16" style="gap:12px">
  <div class="nilai-chip"><b><?= (int) $pesanBaru ?></b><span>Pesan kontak belum dibaca</span></div>
  <div class="nilai-chip"><b><?= (int) landing_pengguna_total() ?></b><span>Pengguna aktif (otomatis)</span></div>
  <div class="nilai-chip"><b><?= (int) landing_sekolah_jumlah() ?></b><span>Sekolah/madrasah aktif</span></div>
  <div class="nilai-chip"><b><?= (int) landing_pesan_jumlah() ?></b><span>Seluruh pesan kontak</span></div>
</div>

<?php
$labelTab = [
    'slider'    => 'Slider Beranda',
    'beranda'   => 'Beranda',
    'fitur'     => 'Fitur Aplikasi',
    'unggulan'  => 'Keunggulan',
    'pengguna'  => 'Total Pengguna',
    'testimoni' => 'Testimoni',
    'tentang'   => 'Tentang Aplikasi',
    'kontak'    => 'Kontak',
    'pesan'     => 'Pesan Masuk',
];
?>
<div class="chips mt16" style="gap:8px">
  <?php foreach ($labelTab as $t => $lb): ?>
    <?php $badge = ($t === 'pesan' && $pesanBaru > 0) ? ' (' . (int) $pesanBaru . ' baru)' : ''; ?>
    <a class="chip <?= $tab === $t ? 'on' : '' ?>" href="landing.php?tab=<?= e($t) ?>"><?= $lb ?><?= $badge ?></a>
  <?php endforeach; ?>
</div>

<?php if ($tab === 'slider'): ?>
  <?php
  // SLIDER BERANDA: bagian beranda berisi judul slider + foto/gambar + keterangan.
  $daftarSlide = landing_list('slider', false);
  $sedangUbah  = $itemEdit !== null && $editJenis === 'slider';
  ?>
  <div class="alert alert-ok" style="margin:18px 0 0">
    <?= ikon('info', 16) ?>
    <span>Slider ini tampil pada <b>bagian beranda halaman depan</b> (di sebelah panel masuk).
      Tiap slide berisi <b>Judul Slider</b>, <b>foto/gambar</b>, dan <b>keterangan</b>.
      Bila fotonya belum diunggah, slide tetap tampil dengan latar hijau bergradasi — tidak ada
      kotak kosong. Urutan di sini = urutan tampil.</span>
  </div>

  <div class="grid g21 mt16" style="align-items:start">
    <div class="card" style="margin:0">
      <div class="card-head">
        <div>
          <h2>Daftar Slide</h2>
          <p>Slide yang disembunyikan tidak dihapus dan dapat ditampilkan kembali.</p>
        </div>
        <span class="chip"><?= count($daftarSlide) ?> slide</span>
      </div>

      <?php if (!$daftarSlide): ?>
        <div class="empty">
          <?= ikon('camera', 30) ?>
          <h3>Belum ada slide</h3>
          <p>Tambahkan lewat formulir di samping supaya slider tampil pada bagian beranda halaman depan.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tbl" id="tblLandingSlider">
            <thead>
              <tr><th style="width:52px">Urut</th><th style="width:110px">Gambar</th><th>Judul &amp; keterangan</th>
                <th class="ctr">Status</th><th class="ctr" style="width:230px">Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($daftarSlide as $i => $it): ?>
              <tr>
                <td class="ctr xs mut"><?= (int) ($i + 1) ?></td>
                <td class="ctr">
                  <?php $gb = landing_slider_gambar($it); ?>
                  <?php if ($gb !== ''): ?>
                    <img class="landing-gambar-kecil" src="<?= e($gb) ?>" alt="Slide <?= (int) ($i + 1) ?>">
                  <?php else: ?>
                    <span class="pill pill-gray xs">tanpa foto</span>
                  <?php endif; ?>
                </td>
                <td>
                  <b><?= e((string) $it['judul']) ?></b>
                  <?php if (trim((string) $it['keterangan']) !== ''): ?>
                    <span class="tbl-sub"><?= e((string) $it['keterangan']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="ctr">
                  <span class="pill <?= (int) $it['aktif'] === 1 ? 'pill-ok' : 'pill-gray' ?>">
                    <?= (int) $it['aktif'] === 1 ? 'Tampil' : 'Disembunyikan' ?>
                  </span>
                </td>
                <td class="ctr">
                  <div class="row-actions">
                    <form method="post" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="naik">
                      <input type="hidden" name="jenis" value="slider">
                      <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit" title="Naikkan urutan"><?= ikon('up', 14) ?></button>
                    </form>
                    <form method="post" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="turun">
                      <input type="hidden" name="jenis" value="slider">
                      <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit" title="Turunkan urutan"><?= ikon('down', 14) ?></button>
                    </form>
                    <a class="btn btn-ghost btn-sm" href="landing.php?tab=slider&amp;jenis=slider&amp;edit=<?= (int) $it['id'] ?>#formUbahSlider">
                      <?= ikon('edit', 14) ?> Ubah
                    </a>
                    <form method="post" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="<?= (int) $it['aktif'] === 1 ? 'nonaktif' : 'aktif' ?>">
                      <input type="hidden" name="jenis" value="slider">
                      <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $it['aktif'] === 1 ? 'Sembunyikan' : 'Tampilkan' ?></button>
                    </form>
                    <form method="post" style="display:inline" data-confirm="Hapus slide &quot;<?= e((string) $it['judul']) ?>&quot; dari slider beranda?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="jenis" value="slider">
                      <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                      <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($sedangUbah): ?>
      <form method="post" enctype="multipart/form-data" class="card" style="margin:0" id="formUbahSlider">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="ubah">
        <input type="hidden" name="jenis" value="slider">
        <input type="hidden" name="id" value="<?= (int) $itemEdit['id'] ?>">
        <div class="card-head">
          <div>
            <h2>Ubah Slide</h2>
            <p><b><?= e((string) $itemEdit['judul']) ?></b></p>
          </div>
        </div>
        <div>
          <?php // PENTING: id isian WAJIB berawalan "slider" — id generik ("foto", "judul",
                // "keterangan") sudah dipakai penolong lain di assets/app.js (mis. initFoto())
                // sehingga memakainya di sini memicu galat konsol saat berkas dipilih. ?>
          <label class="f" for="slider_judul">Judul slider</label>
          <input type="text" id="slider_judul" name="judul" maxlength="<?= (int) LANDING_JUDUL_MAKS ?>"
                 value="<?= e((string) $itemEdit['judul']) ?>" required>
        </div>
        <div class="mt16">
          <label class="f" for="slider_keterangan">Keterangan</label>
          <textarea id="slider_keterangan" name="keterangan" rows="4" maxlength="<?= (int) LANDING_ISI_MAKS ?>"><?= e((string) $itemEdit['keterangan']) ?></textarea>
        </div>
        <?php $gbEdit = landing_slider_gambar($itemEdit); ?>
        <div class="mt16">
          <label class="f" for="slider_foto">Foto / gambar</label>
          <?php if ($gbEdit !== ''): ?>
            <div class="landing-gambar-pratinjau"><img src="<?= e($gbEdit) ?>" alt="Gambar slide"></div>
          <?php endif; ?>
          <input type="file" id="slider_foto" name="foto" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
          <div class="hint">Format PNG, JPG, GIF, WEBP, atau SVG &middot; maksimal <?= e(maks_unggah_teks()) ?> &middot;
            sebaiknya gambar mendatar (landscape). Mengunggah berkas baru menggantikan gambar lama.</div>
          <?php if ($gbEdit !== ''): ?>
            <label class="f mt16" style="display:flex;gap:9px;align-items:center;font-weight:600">
              <input type="checkbox" name="hapus_foto" value="1" style="width:16px;height:16px;accent-color:var(--accent)">
              Hapus gambar ini (slide tetap tampil dengan latar gradasi)
            </label>
          <?php endif; ?>
        </div>
        <label class="f mt16" style="display:flex;gap:9px;align-items:center;font-weight:600">
          <input type="checkbox" name="aktif" value="1" <?= (int) $itemEdit['aktif'] === 1 ? 'checked' : '' ?>
                 style="width:16px;height:16px;accent-color:var(--accent)">
          Tampilkan pada slider beranda
        </label>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> Simpan</button>
          <a class="btn btn-ghost" href="landing.php?tab=slider">Batal</a>
        </div>
      </form>
    <?php else: ?>
      <form method="post" enctype="multipart/form-data" class="card" style="margin:0" id="formTambahSlider">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tambah">
        <input type="hidden" name="jenis" value="slider">
        <div class="card-head">
          <div>
            <h2>Tambah Slide</h2>
            <p>Satu slide berisi judul, foto/gambar, dan keterangan.</p>
          </div>
        </div>
        <div>
          <label class="f" for="sliderJudulBaru">Judul slider</label>
          <input type="text" id="sliderJudulBaru" name="judul" maxlength="<?= (int) LANDING_JUDUL_MAKS ?>" required
                 placeholder="mis. Administrasi Guru Digital dalam Satu Aplikasi">
        </div>
        <div class="mt16">
          <label class="f" for="sliderKeteranganBaru">Keterangan</label>
          <textarea id="sliderKeteranganBaru" name="keterangan" rows="4" maxlength="<?= (int) LANDING_ISI_MAKS ?>"
                    placeholder="Penjelasan singkat, satu atau dua kalimat."></textarea>
        </div>
        <div class="mt16">
          <label class="f" for="sliderFotoBaru">Foto / gambar</label>
          <input type="file" id="sliderFotoBaru" name="foto" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
          <div class="hint">Format PNG, JPG, GIF, WEBP, atau SVG &middot; maksimal <?= e(maks_unggah_teks()) ?> &middot;
            sebaiknya gambar mendatar. Boleh dikosongkan dulu — slide tetap tampil dengan latar gradasi.</div>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= ikon('plus', 16) ?> Tambah Slide</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'beranda'): ?>
  <form method="post" class="card" style="margin:18px 0 0 0" id="formLandingBeranda">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="teks">
    <input type="hidden" name="tab" value="beranda">
    <div class="card-head">
      <div>
        <h2>Bagian Beranda</h2>
        <p>Label kecil di atas slider. Isi slider (judul, foto, keterangan) diatur pada tab
          <b>Slider Beranda</b>.</p>
      </div>
    </div>
    <div>
      <label class="f" for="label">Label kecil di atas slider</label>
      <input type="text" id="label" name="label" maxlength="<?= (int) LANDING_JUDUL_MAKS ?>"
             value="<?= e(landing_teks('label')) ?>" placeholder="mis. Aplikasi Administrasi Guru Digital">
    </div>
    <div class="hint">Kosongkan bila label itu tidak ingin ditampilkan.</div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> Simpan Bagian Beranda</button>
      <span class="xs mut">Perubahan langsung tampil pada halaman depan.</span>
    </div>
  </form>

<?php elseif ($tab === 'pengguna'): ?>
  <?php // TOTAL PENGGUNA — angka DIHITUNG OTOMATIS (permintaan pemilik), yang diatur hanya label
        // tiap kartu dan apakah kartunya ditampilkan. ?>
  <div class="alert alert-ok" style="margin:18px 0 0">
    <?= ikon('info', 16) ?>
    <span>Angka pada bagian ini <b>dihitung otomatis</b> dari data aplikasi, jadi <b>selalu
      terbaru</b> — tidak perlu diisi manual dan tidak bisa diketik: kartu <b>Pengawas</b>,
      <b>Kepala Sekolah/Madrasah</b>, dan <b>Guru</b> menghitung <b>akun</b> yang masih aktif,
      sedangkan kartu <b>Siswa</b> menghitung <b>data siswa</b> dari seluruh sekolah/madrasah
      (siswa aktif &amp; belum lulus pada sekolah/madrasah yang aktif). Yang dapat diubah di sini
      hanya <b>judul bagian</b>, <b>label tiap kartu</b>, dan <b>kartu mana yang ditampilkan</b>.</span>
    <a class="btn btn-ghost btn-sm" href="login.php#pengguna" target="_blank" rel="noopener" style="margin-left:auto">
      <?= ikon('eye', 15) ?> Lihat
    </a>
  </div>

  <form method="post" class="grid" style="gap:20px;margin-top:18px" id="formLandingPengguna">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="teks">
    <input type="hidden" name="tab" value="pengguna">

    <div class="card" style="margin:0">
      <div class="card-head">
        <div>
          <h2>Judul Bagian "Total Pengguna"</h2>
          <p>Judul dan keterangan singkat di atas kartu-kartu angka.</p>
        </div>
      </div>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="pengguna_judul">Judul bagian</label>
          <input type="text" id="pengguna_judul" name="pengguna_judul" maxlength="<?= (int) LANDING_JUDUL_MAKS ?>"
                 value="<?= e(landing_teks('pengguna_judul')) ?>">
        </div>
        <div>
          <label class="f" for="pengguna_sub">Keterangan singkat</label>
          <input type="text" id="pengguna_sub" name="pengguna_sub" maxlength="<?= (int) LANDING_TEKS_MAKS ?>"
                 value="<?= e(landing_teks('pengguna_sub')) ?>">
        </div>
      </div>
    </div>

    <div class="card" style="margin:0" id="cardPenggunaLabel">
      <div class="card-head">
        <div>
          <h2>Kartu Angka &amp; Labelnya</h2>
          <p>Angka di sebelah kiri dihitung otomatis; yang dapat diubah hanya labelnya. Hilangkan
            centang <b>Tampilkan</b> bila kartu itu tidak ingin dicetak pada halaman depan.</p>
        </div>
      </div>
      <div class="grid g2" style="gap:16px">
        <?php foreach (landing_pengguna_peran() as $peran => $bawaan): ?>
          <div class="card" style="margin:0">
            <div class="grid" style="gap:10px">
              <div class="sm mut">Jumlah terhitung sekarang:
                <b style="font-size:1.05rem;color:var(--hijau-tua)"><?= (int) landing_pengguna_angka($peran) ?></b>
                akun aktif
              </div>
              <div>
                <label class="f" for="pengguna_<?= e($peran) ?>_label">Label kartu</label>
                <input type="text" id="pengguna_<?= e($peran) ?>_label" name="pengguna_<?= e($peran) ?>_label"
                       maxlength="<?= (int) LANDING_JUDUL_MAKS ?>" value="<?= e(landing_pengguna_label($peran)) ?>"
                       placeholder="<?= e($bawaan) ?>">
              </div>
              <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600">
                <input type="checkbox" name="pengguna_<?= e($peran) ?>_tampil" value="1"
                       <?= app_setting('landing_pengguna_' . $peran . '_tampil', '1') === '1' ? 'checked' : '' ?>
                       style="width:16px;height:16px;accent-color:var(--accent)">
                Tampilkan kartu ini
              </label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> Simpan Bagian Total Pengguna</button>
      <span class="xs mut">Angka pada halaman depan selalu dihitung ulang saat halaman dibuka.</span>
    </div>
  </form>

<?php elseif ($tab === 'fitur' || $tab === 'unggulan'): ?>
  <?php
  $jenis = $tab;
  $daftar = landing_list($jenis, false);
  $judulKunci = $jenis === 'fitur' ? 'fitur_judul' : 'unggulan_judul';
  $subKunci   = $jenis === 'fitur' ? 'fitur_sub' : 'unggulan_sub';
  $sedangUbah = $itemEdit !== null && $editJenis === $jenis;
  ?>
  <form method="post" class="card mt16" style="margin:18px 0 0 0" id="formJudul<?= e(ucfirst($jenis)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="teks">
    <input type="hidden" name="tab" value="<?= e($jenis) ?>">
    <div class="card-head">
      <div>
        <h2>Judul Bagian "<?= e(landing_jenis_label($jenis)) ?>"</h2>
        <p>Judul dan keterangan singkat di atas daftar kartu.</p>
      </div>
    </div>
    <div class="grid g2" style="gap:16px">
      <div>
        <label class="f" for="<?= e($judulKunci) ?>">Judul bagian</label>
        <input type="text" id="<?= e($judulKunci) ?>" name="<?= e($judulKunci) ?>" maxlength="<?= (int) LANDING_JUDUL_MAKS ?>"
               value="<?= e(landing_teks($judulKunci)) ?>">
      </div>
      <div>
        <label class="f" for="<?= e($subKunci) ?>">Keterangan singkat</label>
        <input type="text" id="<?= e($subKunci) ?>" name="<?= e($subKunci) ?>" maxlength="<?= (int) LANDING_TEKS_MAKS ?>"
               value="<?= e(landing_teks($subKunci)) ?>">
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> Simpan Judul</button>
    </div>
  </form>

  <div class="grid g21 mt16" style="align-items:start">
    <div class="card" style="margin:0">
      <div class="card-head">
        <div>
          <h2>Daftar <?= e(landing_jenis_label($jenis)) ?></h2>
          <p>Urutan di sini = urutan tampil pada halaman depan. Kartu yang disembunyikan tidak dihapus.</p>
        </div>
        <span class="chip"><?= count($daftar) ?> baris</span>
      </div>

      <?php if (!$daftar): ?>
        <div class="empty">
          <?= ikon('star', 30) ?>
          <h3>Belum ada isi</h3>
          <p>Tambahkan lewat formulir di samping supaya bagian ini muncul pada halaman depan.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tbl" id="tblLanding<?= e(ucfirst($jenis)) ?>">
            <thead>
              <tr><th style="width:52px">Urut</th><th style="width:64px">Ikon</th><th>Judul &amp; keterangan</th>
                <th class="ctr">Status</th><th class="ctr" style="width:230px">Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($daftar as $i => $it): ?>
              <tr>
                <td class="ctr xs mut"><?= (int) ($i + 1) ?></td>
                <td class="ctr">
                  <span class="landing-ikon-pratinjau"><?= ikon(landing_ikon_sah((string) $it['ikon']), 18) ?></span>
                </td>
                <td>
                  <b><?= e((string) $it['judul']) ?></b>
                  <?php if (trim((string) $it['teks']) !== ''): ?>
                    <span class="tbl-sub"><?= e((string) $it['teks']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="ctr">
                  <span class="pill <?= (int) $it['aktif'] === 1 ? 'pill-ok' : 'pill-gray' ?>">
                    <?= (int) $it['aktif'] === 1 ? 'Tampil' : 'Disembunyikan' ?>
                  </span>
                </td>
                <td class="ctr">
                  <div class="row-actions">
                    <form method="post" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="naik">
                      <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
                      <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit" title="Naikkan urutan"><?= ikon('up', 14) ?></button>
                    </form>
                    <form method="post" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="turun">
                      <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
                      <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit" title="Turunkan urutan"><?= ikon('down', 14) ?></button>
                    </form>
                    <a class="btn btn-ghost btn-sm" href="landing.php?tab=<?= e($jenis) ?>&amp;jenis=<?= e($jenis) ?>&amp;edit=<?= (int) $it['id'] ?>#formUbah">
                      <?= ikon('edit', 14) ?> Ubah
                    </a>
                    <form method="post" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="<?= (int) $it['aktif'] === 1 ? 'nonaktif' : 'aktif' ?>">
                      <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
                      <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $it['aktif'] === 1 ? 'Sembunyikan' : 'Tampilkan' ?></button>
                    </form>
                    <form method="post" style="display:inline" data-confirm="Hapus &quot;<?= e((string) $it['judul']) ?>&quot; dari halaman depan?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
                      <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                      <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($sedangUbah): ?>
      <form method="post" class="card" style="margin:0" id="formUbah">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="ubah">
        <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
        <input type="hidden" name="id" value="<?= (int) $itemEdit['id'] ?>">
        <div class="card-head">
          <div>
            <h2>Ubah <?= e(landing_jenis_label($jenis)) ?></h2>
            <p><b><?= e((string) $itemEdit['judul']) ?></b></p>
          </div>
        </div>
        <div>
          <label class="f" for="ikon">Ikon</label>
          <select id="ikon" name="ikon">
            <?php foreach (landing_ikon_list() as $k => $lb): ?>
              <option value="<?= e($k) ?>" <?= (string) $itemEdit['ikon'] === $k ? 'selected' : '' ?>><?= e($lb) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mt16">
          <label class="f" for="judul">Judul</label>
          <input type="text" id="judul" name="judul" maxlength="<?= (int) LANDING_JUDUL_MAKS ?>"
                 value="<?= e((string) $itemEdit['judul']) ?>" required>
        </div>
        <div class="mt16">
          <label class="f" for="teks">Keterangan</label>
          <textarea id="teks" name="teks" rows="4" maxlength="<?= (int) LANDING_TEKS_MAKS ?>"><?= e((string) $itemEdit['teks']) ?></textarea>
        </div>
        <label class="f mt16" style="display:flex;gap:9px;align-items:center;font-weight:600">
          <input type="checkbox" name="aktif" value="1" <?= (int) $itemEdit['aktif'] === 1 ? 'checked' : '' ?>
                 style="width:16px;height:16px;accent-color:var(--accent)">
          Tampilkan pada halaman depan
        </label>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> Simpan</button>
          <a class="btn btn-ghost" href="landing.php?tab=<?= e($jenis) ?>">Batal</a>
        </div>
      </form>
    <?php else: ?>
      <form method="post" class="card" style="margin:0" id="formTambah<?= e(ucfirst($jenis)) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tambah">
        <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
        <div class="card-head">
          <div>
            <h2>Tambah <?= e(landing_jenis_label($jenis)) ?></h2>
            <p>Tambahkan satu kartu baru pada bagian ini.</p>
          </div>
        </div>
        <div>
          <label class="f" for="ikonBaru">Ikon</label>
          <select id="ikonBaru" name="ikon">
            <?php $ikonAwal = $jenis === 'unggulan' ? 'star' : 'check'; ?>
            <?php foreach (landing_ikon_list() as $k => $lb): ?>
              <option value="<?= e($k) ?>" <?= $k === $ikonAwal ? 'selected' : '' ?>><?= e($lb) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mt16">
          <label class="f" for="judulBaru">Judul</label>
          <input type="text" id="judulBaru" name="judul" maxlength="<?= (int) LANDING_JUDUL_MAKS ?>" required
                 placeholder="mis. Nilai &amp; Rekap Otomatis">
        </div>
        <div class="mt16">
          <label class="f" for="teksBaru">Keterangan</label>
          <textarea id="teksBaru" name="teks" rows="4" maxlength="<?= (int) LANDING_TEKS_MAKS ?>"
                    placeholder="Penjelasan singkat, satu atau dua kalimat."></textarea>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= ikon('plus', 16) ?> Tambah</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'testimoni'): ?>
  <?php
  $daftar = landing_list('testimoni', false);
  $sedangUbah = $itemEdit !== null && $editJenis === 'testimoni';
  ?>
  <form method="post" class="card mt16" style="margin:18px 0 0 0" id="formJudulTestimoni">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="teks">
    <input type="hidden" name="tab" value="testimoni">
    <div class="card-head">
      <div>
        <h2>Judul Bagian "Testimoni"</h2>
        <p>Judul kecil, judul besar, dan keterangan singkat di atas daftar testimoni.</p>
      </div>
    </div>
    <div class="grid g2" style="gap:16px">
      <div>
        <label class="f" for="testimoni_judul">Judul kecil (label)</label>
        <input type="text" id="testimoni_judul" name="testimoni_judul" maxlength="<?= (int) LANDING_JUDUL_MAKS ?>"
               value="<?= e(landing_teks('testimoni_judul')) ?>">
      </div>
      <div>
        <label class="f" for="testimoni_judul_besar">Judul besar</label>
        <input type="text" id="testimoni_judul_besar" name="testimoni_judul_besar" maxlength="<?= (int) LANDING_JUDUL_MAKS ?>"
               value="<?= e(landing_teks('testimoni_judul_besar')) ?>">
      </div>
    </div>
    <div class="mt16">
      <label class="f" for="testimoni_sub">Keterangan singkat</label>
      <input type="text" id="testimoni_sub" name="testimoni_sub" maxlength="<?= (int) LANDING_TEKS_MAKS ?>"
             value="<?= e(landing_teks('testimoni_sub')) ?>">
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> Simpan Judul</button>
    </div>
  </form>

  <div class="grid g21 mt16" style="align-items:start">
    <div class="card" style="margin:0">
      <div class="card-head">
        <div>
          <h2>Daftar Testimoni</h2>
          <p>Testimoni tampil sebagai kartu yang dapat digeser pada halaman depan.</p>
        </div>
        <span class="chip"><?= count($daftar) ?> baris</span>
      </div>

      <?php if (!$daftar): ?>
        <div class="empty">
          <?= ikon('star', 30) ?>
          <h3>Belum ada testimoni</h3>
          <p>Tambahkan lewat formulir di samping supaya bagian ini muncul pada halaman depan.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tbl" id="tblLandingTestimoni">
            <thead>
              <tr><th style="width:52px">Urut</th><th style="width:64px">Foto</th><th>Nama &amp; testimoni</th>
                <th class="ctr">Status</th><th class="ctr" style="width:230px">Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($daftar as $i => $it): ?>
              <tr>
                <td class="ctr xs mut"><?= (int) ($i + 1) ?></td>
                <td class="ctr">
                  <?php $fotoIt = trim((string) ($it['foto'] ?? '')); ?>
                  <?php if ($fotoIt !== ''): ?>
                    <img class="landing-foto-kecil" src="<?= e($fotoIt) ?>" alt="Foto <?= e((string) $it['nama']) ?>">
                  <?php else: ?>
                    <span class="pill pill-gray xs">tanpa foto</span>
                  <?php endif; ?>
                </td>
                <td>
                  <b><?= e((string) $it['nama']) ?></b>
                  <?php if (trim((string) $it['jabatan']) !== ''): ?>
                    <span class="tbl-sub"><?= e((string) $it['jabatan']) ?></span>
                  <?php endif; ?>
                  <span class="tbl-sub"><?= e((string) $it['isi']) ?></span>
                </td>
                <td class="ctr">
                  <span class="pill <?= (int) $it['aktif'] === 1 ? 'pill-ok' : 'pill-gray' ?>">
                    <?= (int) $it['aktif'] === 1 ? 'Tampil' : 'Disembunyikan' ?>
                  </span>
                </td>
                <td class="ctr">
                  <div class="row-actions">
                    <form method="post" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="naik">
                      <input type="hidden" name="jenis" value="testimoni">
                      <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit" title="Naikkan urutan"><?= ikon('up', 14) ?></button>
                    </form>
                    <form method="post" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="turun">
                      <input type="hidden" name="jenis" value="testimoni">
                      <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit" title="Turunkan urutan"><?= ikon('down', 14) ?></button>
                    </form>
                    <a class="btn btn-ghost btn-sm" href="landing.php?tab=testimoni&amp;jenis=testimoni&amp;edit=<?= (int) $it['id'] ?>#formUbahTestimoni">
                      <?= ikon('edit', 14) ?> Ubah
                    </a>
                    <form method="post" style="display:inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="<?= (int) $it['aktif'] === 1 ? 'nonaktif' : 'aktif' ?>">
                      <input type="hidden" name="jenis" value="testimoni">
                      <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit"><?= (int) $it['aktif'] === 1 ? 'Sembunyikan' : 'Tampilkan' ?></button>
                    </form>
                    <form method="post" style="display:inline" data-confirm="Hapus testimoni dari &quot;<?= e((string) $it['nama']) ?>&quot;?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="jenis" value="testimoni">
                      <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                      <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($sedangUbah): ?>
      <form method="post" enctype="multipart/form-data" class="card" style="margin:0" id="formUbahTestimoni">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="ubah">
        <input type="hidden" name="jenis" value="testimoni">
        <input type="hidden" name="id" value="<?= (int) $itemEdit['id'] ?>">
        <div class="card-head">
          <div>
            <h2>Ubah Testimoni</h2>
            <p><b><?= e((string) $itemEdit['nama']) ?></b></p>
          </div>
        </div>
        <div>
          <label class="f" for="nama">Nama</label>
          <input type="text" id="nama" name="nama" maxlength="<?= (int) LANDING_NAMA_MAKS ?>"
                 value="<?= e((string) $itemEdit['nama']) ?>" required>
        </div>
        <div class="mt16">
          <label class="f" for="jabatan">Jabatan / keterangan</label>
          <input type="text" id="jabatan" name="jabatan" maxlength="<?= (int) LANDING_NAMA_MAKS ?>"
                 value="<?= e((string) $itemEdit['jabatan']) ?>" placeholder="mis. Kepala Sekolah/Madrasah">
        </div>
        <div class="mt16">
          <label class="f" for="isi">Isi testimoni</label>
          <textarea id="isi" name="isi" rows="4" maxlength="<?= (int) LANDING_ISI_MAKS ?>" required><?= e((string) $itemEdit['isi']) ?></textarea>
        </div>
        <?php $fotoEdit = trim((string) ($itemEdit['foto'] ?? '')); ?>
        <div class="mt16">
          <label class="f" for="testimoni_foto">Foto (opsional)</label>
          <?php if ($fotoEdit !== ''): ?>
            <div class="landing-foto-pratinjau"><img src="<?= e($fotoEdit) ?>" alt="Foto testimoni"></div>
          <?php endif; ?>
          <input type="file" id="testimoni_foto" name="foto" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
          <div class="hint">Format PNG, JPG, GIF, WEBP, atau SVG &middot; maksimal <?= e(maks_unggah_teks()) ?>.
            Bila kosong, inisial nama yang dipakai.</div>
          <?php if ($fotoEdit !== ''): ?>
            <label class="f mt16" style="display:flex;gap:9px;align-items:center;font-weight:600">
              <input type="checkbox" name="hapus_foto" value="1" style="width:16px;height:16px;accent-color:var(--accent)">
              Hapus foto ini (kembali memakai inisial nama)
            </label>
          <?php endif; ?>
        </div>
        <label class="f mt16" style="display:flex;gap:9px;align-items:center;font-weight:600">
          <input type="checkbox" name="aktif" value="1" <?= (int) $itemEdit['aktif'] === 1 ? 'checked' : '' ?>
                 style="width:16px;height:16px;accent-color:var(--accent)">
          Tampilkan pada halaman depan
        </label>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> Simpan</button>
          <a class="btn btn-ghost" href="landing.php?tab=testimoni">Batal</a>
        </div>
      </form>
    <?php else: ?>
      <form method="post" enctype="multipart/form-data" class="card" style="margin:0" id="formTambahTestimoni">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tambah">
        <input type="hidden" name="jenis" value="testimoni">
        <div class="card-head">
          <div>
            <h2>Tambah Testimoni</h2>
            <p>Satu kartu berisi nama, jabatan (opsional), isi testimoni, dan foto (opsional).</p>
          </div>
        </div>
        <div>
          <label class="f" for="testimoniNamaBaru">Nama</label>
          <input type="text" id="testimoniNamaBaru" name="nama" maxlength="<?= (int) LANDING_NAMA_MAKS ?>" required
                 placeholder="mis. Nama Kepala Sekolah/Madrasah">
        </div>
        <div class="mt16">
          <label class="f" for="testimoniJabatanBaru">Jabatan / keterangan</label>
          <input type="text" id="testimoniJabatanBaru" name="jabatan" maxlength="<?= (int) LANDING_NAMA_MAKS ?>"
                 placeholder="mis. Kepala Sekolah/Madrasah">
        </div>
        <div class="mt16">
          <label class="f" for="testimoniIsiBaru">Isi testimoni</label>
          <textarea id="testimoniIsiBaru" name="isi" rows="4" maxlength="<?= (int) LANDING_ISI_MAKS ?>" required
                    placeholder="Pengalaman memakai aplikasi ini."></textarea>
        </div>
        <div class="mt16">
          <label class="f" for="testimoniFotoBaru">Foto (opsional)</label>
          <input type="file" id="testimoniFotoBaru" name="foto" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
          <div class="hint">Bila dikosongkan, inisial nama yang dipakai pada kartu testimoni.</div>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit"><?= ikon('plus', 16) ?> Tambah Testimoni</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'tentang'): ?>
  <?php $tentang = tentang_isi(); ?>
  <div class="alert alert-ok" style="margin:18px 0 0">
    <?= ikon('info', 16) ?>
    <span>Isi bagian <b>Tentang Aplikasi</b> pada halaman depan (dua foto ukuran <b>3x4</b>, nama di
      bawah masing-masing foto, dan keterangan/penjelasan) diatur pada menu
      <b>Tentang Aplikasi</b> — jadi hanya ada satu sumber data yang perlu diisi.</span>
    <a class="btn btn-ghost btn-sm" href="tentang.php" style="margin-left:auto"><?= ikon('edit', 15) ?> Buka Tentang Aplikasi</a>
  </div>

  <div class="card mt16">
    <div class="card-head">
      <div>
        <h2>Keadaan Isi "Tentang Aplikasi"</h2>
        <p>Ringkasan isi yang sekarang tampil pada halaman depan.</p>
      </div>
      <span class="pill <?= !empty($tentang['ada']) ? 'pill-ok' : 'pill-gray' ?>">
        <?= !empty($tentang['ada']) ? 'Tampil' : 'Belum ada isi' ?>
      </span>
    </div>
    <div class="portal-tentang" style="margin:0;padding-bottom:0;border-bottom:none">
      <div class="tentang-baris">
        <div class="tentang-foto-grid">
          <?php foreach (tentang_slot_list() as $n): ?>
            <?php $sl = $tentang['slot'][$n]; ?>
            <figure class="tentang-slot">
              <div class="tentang-bingkai">
                <?php if ($sl['foto'] !== ''): ?>
                  <img src="<?= e($sl['foto']) ?>" alt="Foto <?= (int) $n ?>" loading="lazy">
                <?php else: ?>
                  <span class="tentang-kosong"><?= ikon('user', 26) ?><span class="xs">Foto 3x4</span></span>
                <?php endif; ?>
              </div>
              <figcaption class="tentang-nama"><?= $sl['nama'] !== '' ? e($sl['nama']) : '<span class="mut">(nama belum diisi)</span>' ?></figcaption>
            </figure>
          <?php endforeach; ?>
        </div>
        <?php if (trim((string) $tentang['keterangan']) !== ''): ?>
          <p class="tentang-ket"><?= nl2br(e((string) $tentang['keterangan'])) ?></p>
        <?php else: ?>
          <p class="sm mut" style="flex:1 1 220px;margin:0">Keterangan/penjelasan belum diisi.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <form method="post" class="card mt16" id="formJudulTentang">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="teks">
    <input type="hidden" name="tab" value="tentang">
    <div class="card-head">
      <div>
        <h2>Judul Bagian "Tentang Aplikasi"</h2>
        <p>Judul dan keterangan singkat di atas blok foto &amp; keterangan.</p>
      </div>
    </div>
    <div class="grid g2" style="gap:16px">
      <div>
        <label class="f" for="tentang_judul">Judul bagian</label>
        <input type="text" id="tentang_judul" name="tentang_judul" maxlength="<?= (int) LANDING_JUDUL_MAKS ?>"
               value="<?= e(landing_teks('tentang_judul')) ?>">
      </div>
      <div>
        <label class="f" for="tentang_sub">Keterangan singkat</label>
        <input type="text" id="tentang_sub" name="tentang_sub" maxlength="<?= (int) LANDING_TEKS_MAKS ?>"
               value="<?= e(landing_teks('tentang_sub')) ?>">
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> Simpan</button>
    </div>
  </form>

<?php elseif ($tab === 'kontak'): ?>
  <form method="post" class="grid" style="gap:20px;margin-top:18px" id="formLandingKontak">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="teks">
    <input type="hidden" name="tab" value="kontak">

    <div class="card" style="margin:0">
      <div class="card-head">
        <div>
          <h2>Judul Bagian "Kontak"</h2>
          <p>Judul dan keterangan singkat di atas informasi kontak.</p>
        </div>
      </div>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="kontak_judul">Judul bagian</label>
          <input type="text" id="kontak_judul" name="kontak_judul" maxlength="<?= (int) LANDING_JUDUL_MAKS ?>"
                 value="<?= e(landing_teks('kontak_judul')) ?>">
        </div>
        <div>
          <label class="f" for="kontak_sub">Keterangan singkat</label>
          <input type="text" id="kontak_sub" name="kontak_sub" maxlength="<?= (int) LANDING_TEKS_MAKS ?>"
                 value="<?= e(landing_teks('kontak_sub')) ?>">
        </div>
      </div>
    </div>

    <div class="card" style="margin:0" id="cardInfoKontak">
      <div class="card-head">
        <div>
          <h2>Informasi Kontak</h2>
          <p>Baris yang dikosongkan tidak dicetak pada halaman depan.</p>
        </div>
      </div>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="kontak_telepon">Telepon / WhatsApp (dipakai tombol WhatsApp)</label>
          <input type="text" id="kontak_telepon" name="kontak_telepon" maxlength="<?= (int) LANDING_TEKS_MAKS ?>"
                 value="<?= e(landing_teks('kontak_telepon')) ?>" placeholder="mis. 085240102180">
        </div>
        <div>
          <label class="f" for="kontak_email">Email</label>
          <input type="text" id="kontak_email" name="kontak_email" maxlength="<?= (int) LANDING_TEKS_MAKS ?>"
                 value="<?= e(landing_teks('kontak_email')) ?>" placeholder="mis. admin@sekolah.sch.id">
        </div>
      </div>
      <div class="mt16">
        <label class="f" for="kontak_alamat">Alamat</label>
        <textarea id="kontak_alamat" name="kontak_alamat" rows="2" maxlength="<?= (int) LANDING_TEKS_MAKS ?>"><?= e(landing_teks('kontak_alamat')) ?></textarea>
      </div>
      <div class="grid g2 mt16" style="gap:16px">
        <div>
          <label class="f" for="kontak_jam">Jam layanan</label>
          <input type="text" id="kontak_jam" name="kontak_jam" maxlength="<?= (int) LANDING_TEKS_MAKS ?>"
                 value="<?= e(landing_teks('kontak_jam')) ?>">
        </div>
        <div>
          <label class="f" for="kontak_maps">Tautan peta (opsional)</label>
          <input type="text" id="kontak_maps" name="kontak_maps" maxlength="<?= (int) LANDING_TEKS_MAKS ?>"
                 value="<?= e(landing_teks('kontak_maps')) ?>" placeholder="https://maps.google.com/...">
        </div>
      </div>
      <div class="mt16">
        <label class="f" for="kontak_ajakan">Kalimat ajakan pada kartu kontak</label>
        <textarea id="kontak_ajakan" name="kontak_ajakan" rows="3" maxlength="<?= (int) LANDING_TEKS_MAKS ?>"><?= e(landing_teks('kontak_ajakan')) ?></textarea>
      </div>
    </div>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= ikon('check', 16) ?> Simpan Bagian Kontak</button>
      <span class="xs mut">Pesan dari formulir kontak halaman depan masuk ke tab <b>Pesan Masuk</b>.</span>
    </div>
  </form>

<?php elseif ($tab === 'pesan'): ?>
  <?php $jml = landing_pesan_jumlah(); ?>
  <div class="grid g3 mt16" style="gap:12px">
    <div class="nilai-chip"><b><?= (int) $jml ?></b><span>Seluruh pesan</span></div>
    <div class="nilai-chip"><b><?= (int) $pesanBaru ?></b><span>Belum dibaca</span></div>
    <div class="nilai-chip"><b><?= count($pesan) ?></b><span>Ditampilkan (maks. 200 terakhir)</span></div>
  </div>

  <div class="card mt16" style="margin-bottom:0" id="cardPesanKontak">
    <div class="card-head">
      <div>
        <h2>Pesan dari Formulir Kontak</h2>
        <p>Pesan yang dikirim pengunjung melalui formulir kontak pada halaman depan.</p>
      </div>
      <form method="post" class="row-actions" style="margin-left:auto">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="pesan_semua">
        <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('check', 14) ?> Tandai semua sudah dibaca</button>
      </form>
    </div>

    <?php if (!$pesan): ?>
      <div class="empty">
        <?= ikon('chat', 30) ?>
        <h3>Belum ada pesan</h3>
        <p>Pesan yang dikirim lewat formulir kontak pada halaman depan akan muncul di sini.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl" id="tblPesanKontak">
          <thead>
            <tr><th style="width:52px">No</th><th style="width:210px">Pengirim</th><th>Pesan</th>
              <th style="width:150px">Diterima</th><th class="ctr" style="width:190px">Aksi</th></tr>
          </thead>
          <tbody>
          <?php foreach ($pesan as $i => $p): ?>
            <tr class="<?= (int) $p['dibaca'] === 0 ? 'baris-baru' : '' ?>">
              <td class="ctr xs mut"><?= (int) ($i + 1) ?></td>
              <td>
                <b><?= e((string) $p['nama']) ?></b>
                <span class="tbl-sub"><?= e((string) $p['kontak']) ?></span>
                <?php if (trim((string) ($p['ip'] ?? '')) !== ''): ?>
                  <span class="tbl-sub xs mut">IP <?= e((string) $p['ip']) ?></span>
                <?php endif; ?>
              </td>
              <td><?= nl2br(e((string) $p['pesan'])) ?></td>
              <td class="xs mut"><?= waktu_id_polos((string) $p['dibuat']) ?><br>
                <span class="pill <?= (int) $p['dibaca'] === 0 ? 'pill-warn' : 'pill-gray' ?>">
                  <?= (int) $p['dibaca'] === 0 ? 'Belum dibaca' : 'Sudah dibaca' ?>
                </span>
              </td>
              <td class="ctr">
                <div class="row-actions">
                  <form method="post" style="display:inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="<?= (int) $p['dibaca'] === 0 ? 'pesan_baca' : 'pesan_belum' ?>">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button class="btn btn-ghost btn-sm" type="submit">
                      <?= (int) $p['dibaca'] === 0 ? 'Tandai dibaca' : 'Tandai belum dibaca' ?>
                    </button>
                  </form>
                  <form method="post" style="display:inline" data-confirm="Hapus pesan dari &quot;<?= e((string) $p['nama']) ?>&quot;?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="pesan_hapus">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card mt16" id="cardCatatanLanding">
  <div class="card-head">
    <div>
      <h2>Catatan</h2>
      <p>Hal-hal yang perlu diketahui saat mengisi halaman depan.</p>
    </div>
  </div>
  <ul class="portal-cek">
    <li>Tab <b>Slider Beranda</b> mengatur gambar/foto slider pada bagian beranda halaman depan:
      tiap slide berisi <b>judul</b>, <b>foto/gambar</b>, dan <b>keterangan</b>. Bila fotonya belum
      diunggah, slide tetap tampil dengan latar hijau bergradasi.</li>
    <li>Angka bagian <b>Total Pengguna</b> dihitung otomatis dari data aplikasi, jadi selalu terbaru —
      yang dapat diubah hanya label tiap kartu dan apakah kartunya ditampilkan.</li>
    <li>Bagian <b>Tentang Aplikasi</b> (dua foto 3x4, nama, dan keterangan) diisi pada menu
      <b>Tentang Aplikasi</b> — terpisah dari halaman ini.</li>
    <li>Bagian yang <b>disembunyikan</b> tidak dihapus; tekan <b>Tampilkan</b> untuk memunculkannya lagi.</li>
    <li>Seluruh isi halaman depan dipakai bersama oleh <b>semua sekolah/madrasah</b>
      (pengaturan tingkat aplikasi, bukan per sekolah/madrasah).</li>
    <li>Nomor <b>Telepon / WhatsApp</b> pada bagian kontak dipakai oleh tombol WhatsApp pada halaman
      depan (permohonan aktivasi &amp; bukti transfer tetap memakai nomornya masing-masing).</li>
  </ul>
</div>
<?php
page_bottom();
