<?php
/**
 * Formulir Notulen Rapat — berita acara rapat + pemilih daftar hadir guru.
 *
 * Daftar hadir diisi dengan MEMILIH GURU SATU PER SATU dari daftar guru aktif
 * (hanya guru yang dicentang yang dimasukkan ke daftar hadir) — permintaan pemilik.
 *
 * Hak akses (v1.21.0): **Admin Operator** dan **guru yang ditugaskan sebagai Ka. Tata Usaha**
 * (+ Super Admin dalam konteks sekolah). Kepala Sekolah/Madrasah hanya melihat & mencetak (403),
 * akun guru lain 403.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/notulen_ttd.php';   // tanda tangan digital notulen (v1.70.0)

// Halaman pembuatan/pengubahan notulen: Admin Operator & guru Ka. Tata Usaha (+ Super Admin dalam
// konteks). Kepala sekolah/madrasah hanya lihat + cetak.
$u = require_notulen_ubah();

$id = (int) ($_GET['id'] ?? 0);
$edit = $id > 0 ? notulen_by_id($id) : null;
if ($id > 0 && !$edit) {
    flash('Notulen tidak ditemukan.', 'err');
    redirect('notulen.php');
}

$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];
$daftarGuru = guru_list(true);
$pesertaTersimpan = $edit ? notulen_peserta((int) $edit['id']) : [];
$pilihTercentang = [];
foreach ($pesertaTersimpan as $h) {
    if ((int) ($h['guru_id'] ?? 0) > 0) {
        $pilihTercentang[(int) $h['guru_id']] = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi !== 'simpan') {
        redirect('notulen.php');
    }

    $tanggal = tgl_ok($_POST['tanggal'] ?? null);
    $waktu = trim((string) ($_POST['waktu'] ?? ''));
    $tempat = trim((string) ($_POST['tempat'] ?? ''));
    $judul = trim((string) ($_POST['judul'] ?? ''));
    $pembahasan = trim((string) ($_POST['pembahasan'] ?? ''));
    // "Agenda Rapat" & "Keputusan Rapat" sudah TIDAK ada di formulir (permintaan pemilik).
    // Kolomnya tetap ada di basis data: nilainya hanya diperbarui bila field-nya benar-benar
    // dikirim, supaya saat MENGUBAH notulen lama teks yang sudah tersimpan TIDAK terhapus.
    $agenda = array_key_exists('agenda', $_POST)
        ? trim((string) $_POST['agenda'])
        : (string) ($edit['agenda'] ?? '');
    $keputusan = array_key_exists('keputusan', $_POST)
        ? trim((string) $_POST['keputusan'])
        : (string) ($edit['keputusan'] ?? '');
    $pimpinan = trim((string) ($_POST['pimpinan'] ?? ''));
    $pimpinanJabatan = trim((string) ($_POST['pimpinan_jabatan'] ?? ''));
    $notulis = trim((string) ($_POST['notulis'] ?? ''));
    $pilih = array_map('intval', (array) ($_POST['hadir'] ?? []));

    // Susun daftar peserta DULU: hanya guru aktif milik sekolah ini yang diterima (anti-IDOR),
    // urutannya mengikuti urutan daftar guru yang tampil pada formulir.
    $peserta = [];
    foreach ($daftarGuru as $g) {
        $gid = (int) $g['id'];
        if (!in_array($gid, $pilih, true)) {
            continue;
        }
        $peserta[] = [
            'guru_id' => $gid,
            'nama'    => (string) $g['nama'],
            'nip'     => nip_bersih($g['nip'] ?? '') !== '' ? nip_bersih($g['nip'] ?? '') : nip_bersih($g['nuptk'] ?? ''),
            'jabatan' => (string) ($g['jabatan'] ?? ''),
        ];
    }

    $galat = [];
    if ($tanggal === null) {
        $galat[] = 'Tanggal rapat wajib diisi.';
    }
    if ($judul === '') {
        $galat[] = 'Topik / judul rapat wajib diisi.';
    }
    if (!$peserta) {
        $galat[] = 'Pilih minimal satu guru yang menghadiri rapat pada daftar hadir.';
    }
    if ($galat) {
        foreach ($galat as $g) {
            flash($g, 'err');
        }
        redirect('notulen_form.php' . ($edit ? '?id=' . (int) $edit['id'] : ''));
    }

    try {
        db()->exec('BEGIN IMMEDIATE');
        if ($edit) {
            q('UPDATE notulen SET tanggal=?, waktu=?, tempat=?, judul=?, agenda=?, pembahasan=?, keputusan=?,
                      pimpinan=?, pimpinan_jabatan=?, notulis=?, ta_id=?, semester_id=? WHERE id=? AND sekolah_id=?',
                [$tanggal, $waktu, $tempat, $judul, $agenda, $pembahasan, $keputusan, $pimpinan, $pimpinanJabatan,
                 $notulis, $ta ? (int) $ta['id'] : null, $sem ? (int) $sem['id'] : null,
                 (int) $edit['id'], sekolah_id()]);
            $notulenId = (int) $edit['id'];
            q('DELETE FROM notulen_hadir WHERE notulen_id = ?', [$notulenId]);
            flash('Notulen rapat "' . $judul . '" diperbarui.');
        } else {
            q('INSERT INTO notulen (sekolah_id, tanggal, waktu, tempat, judul, agenda, pembahasan, keputusan,
                      pimpinan, pimpinan_jabatan, notulis, ta_id, semester_id, dibuat, dibuat_oleh)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [sekolah_id(), $tanggal, $waktu, $tempat, $judul, $agenda, $pembahasan, $keputusan,
                 $pimpinan, $pimpinanJabatan, $notulis, $ta ? (int) $ta['id'] : null,
                 $sem ? (int) $sem['id'] : null, date('Y-m-d H:i:s'), (int) $u['id']]);
            $notulenId = (int) db()->lastInsertId();
            flash('Notulen rapat "' . $judul . '" disimpan beserta daftar hadir ' . count($peserta) . ' guru.');
        }
        foreach ($peserta as $i => $s) {
            q('INSERT INTO notulen_hadir (notulen_id, guru_id, nama, nip, jabatan, urutan) VALUES (?,?,?,?,?,?)',
                [$notulenId, $s['guru_id'], $s['nama'], $s['nip'], $s['jabatan'], $i]);
        }
        // Tanda tangan digital milik pihak yang sudah tidak ada (peserta diganti, Notulis/Pimpinan
        // dikosongkan) dibuang supaya tidak tertinggal sebagai data yatim.
        notulen_ttd_bersihkan($notulenId);
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }

    redirect('notulen.php');
}

$n = $edit ?? [];
$nilai = static fn(string $kunci, string $bawaan = ''): string => (string) ($n[$kunci] ?? $bawaan);

// Nilai awal untuk notulen BARU: pimpinan = kepala sekolah/madrasah dari Pengaturan,
// notulis = nama akun yang sedang masuk.
$pimpinanAwal = $edit ? $nilai('pimpinan') : kepala_nama();
$pimpinanJabatanAwal = $edit ? $nilai('pimpinan_jabatan') : (kepala_nama() !== '-' ? istilah_sekolah('Kepala Sekolah') : '');
$notulisAwal = $edit ? $nilai('notulis') : trim((string) ($u['nama'] ?? ''));

// Foto bukti kegiatan (maksimal NOTULEN_FOTO_MAKS = 2 per notulen) — hanya ada setelah tersimpan.
$fotoTersimpan = $edit ? notulen_foto((int) $edit['id']) : [];
$jmlFoto = count($fotoTersimpan);
$sisaFoto = max(0, NOTULEN_FOTO_MAKS - $jmlFoto);

page_top($edit ? 'Ubah Notulen Rapat' : 'Buat Notulen Rapat');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= $edit ? 'Ubah Notulen Rapat' : 'Buat Notulen Rapat' ?></h2>
      <p>Isi berita acara rapat, lalu pilih guru yang menghadiri rapat pada daftar hadir di bawah.</p>
    </div>
    <div class="flex">
      <a class="btn btn-ghost" href="notulen.php"><?= ikon('switch', 16) ?> Kembali ke Daftar</a>
      <?php if ($edit): ?>
        <a class="btn btn-ghost" href="cetak_notulen.php?id=<?= (int) $edit['id'] ?>&amp;bagian=berita" target="_blank"><?= ikon('print', 16) ?> Berita Acara</a>
        <a class="btn btn-ghost" href="cetak_notulen.php?id=<?= (int) $edit['id'] ?>&amp;bagian=hadir" target="_blank"><?= ikon('clipboard', 16) ?> Daftar Hadir</a>
        <a class="btn btn-ghost" href="cetak_notulen.php?id=<?= (int) $edit['id'] ?>&amp;bagian=isi" target="_blank"><?= ikon('report', 16) ?> Isi Notulen</a>
        <?php if ($jmlFoto > 0): ?>
          <a class="btn btn-ghost" href="cetak_notulen.php?id=<?= (int) $edit['id'] ?>&amp;bagian=foto" target="_blank"><?= ikon('star', 16) ?> Foto Dokumentasi</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <form method="post" class="form-grid" id="formNotulen">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id" value="<?= (int) ($n['id'] ?? 0) ?>">

    <div class="section-title"><?= ikon('calendar', 15) ?> Waktu &amp; Tempat</div>
    <div class="grid g3" style="gap:16px">
      <div>
        <label class="f" for="tanggal">Tanggal Rapat *</label>
        <input type="date" id="tanggal" name="tanggal" value="<?= e($nilai('tanggal', date('Y-m-d'))) ?>" required>
      </div>
      <div>
        <label class="f" for="waktu">Waktu</label>
        <input type="text" id="waktu" name="waktu" value="<?= e($nilai('waktu')) ?>" placeholder="mis. 09.00 - 11.00">
      </div>
      <div>
        <label class="f" for="tempat">Tempat</label>
        <input type="text" id="tempat" name="tempat" value="<?= e($nilai('tempat')) ?>" placeholder="mis. Ruang Guru">
      </div>
    </div>

    <div class="divider"></div>
    <?php // Permintaan pemilik: judul bagian "Isi Berita Acara" diganti "Hasil Rapat", dan isian
          // "Agenda Rapat" serta "Keputusan / Kesimpulan Rapat" DIHAPUS dari formulir.
          // Kolom `agenda`/`keputusan` tetap ada di basis data (data notulen lama tetap utuh) —
          // lihat penanganan simpan di atas: nilai lama TIDAK ditimpa bila field tidak dikirim. ?>
    <div class="section-title"><?= ikon('doc', 15) ?> Hasil Rapat</div>
    <div>
      <label class="f" for="judul">Topik / Judul Rapat *</label>
      <input type="text" id="judul" name="judul" value="<?= e($nilai('judul')) ?>" required
             placeholder="mis. Rapat Evaluasi Hasil Belajar Semester Ganjil">
    </div>
    <div>
      <label class="f" for="pembahasan">Hasil Pembahasan</label>
      <textarea id="pembahasan" name="pembahasan" rows="5" placeholder="Uraian hasil rapat..."><?= e($nilai('pembahasan')) ?></textarea>
    </div>

    <div class="divider"></div>
    <div class="section-title"><?= ikon('user', 15) ?> Pimpinan &amp; Notulis</div>
    <div class="grid g2" style="gap:16px">
      <div>
        <label class="f" for="pimpinan">Pimpinan Rapat</label>
        <input type="text" id="pimpinan" name="pimpinan" value="<?= e($pimpinanAwal) ?>">
      </div>
      <div>
        <label class="f" for="pimpinan_jabatan">Jabatan Pimpinan Rapat</label>
        <input type="text" id="pimpinan_jabatan" name="pimpinan_jabatan" value="<?= e($pimpinanJabatanAwal) ?>"
               placeholder="mis. <?= e(istilah_sekolah('Kepala Sekolah')) ?>">
      </div>
    </div>
    <div class="grid g2" style="gap:16px">
      <div>
        <label class="f" for="notulis">Notulis</label>
        <input type="text" id="notulis" name="notulis" value="<?= e($notulisAwal) ?>">
      </div>
      <div>
        <label class="f" for="ringkasHadir">Guru hadir terpilih</label>
        <input type="text" id="ringkasHadir" value="<?= $edit ? count($pilihTercentang) . ' guru' : '0 guru' ?>" readonly>
        <div class="hint">Berubah otomatis mengikuti centang pada daftar hadir di bawah.</div>
      </div>
    </div>

    <div class="divider"></div>
    <div class="section-title"><?= ikon('clipboard', 15) ?> Daftar Hadir Guru *</div>
    <?php if (!$daftarGuru): ?>
      <div class="alert alert-warn">
        <?= ikon('alert', 16) ?>
        <span>Belum ada data guru aktif di <?= tetap_kata(istilah_sekolah('sekolah')) ?> ini. Tambahkan data guru
          terlebih dahulu lewat menu <a href="guru.php">Data Guru</a> agar daftar hadir dapat diisi.</span>
      </div>
    <?php else: ?>
      <div>
        <div class="flex mb8" style="align-items:center;gap:10px;flex-wrap:wrap">
          <input type="search" id="cariGuru" placeholder="Cari nama guru / NIP / jabatan..." style="max-width:320px">
          <button class="btn btn-ghost btn-sm" type="button" id="btnHadirSemua"><?= ikon('check', 14) ?> Tandai semua</button>
          <button class="btn btn-ghost btn-sm" type="button" id="btnHadirKosong">Kosongkan</button>
          <span class="hint">Centang guru yang menghadiri rapat — hanya yang dicentang yang masuk ke daftar hadir.</span>
        </div>
        <div class="table-wrap" style="max-height:420px;overflow:auto">
          <table class="tbl" id="tblPilihGuru">
            <thead>
              <tr>
                <th class="ctr" style="width:48px">Hadir</th>
                <th>Nama Guru</th>
                <th>NIP / NUPTK</th>
                <th>Jabatan</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($daftarGuru as $g): $gid = (int) $g['id'];
              $nip = nip_bersih($g['nip'] ?? '') !== '' ? nip_bersih($g['nip'] ?? '') : nip_bersih($g['nuptk'] ?? '');
            ?>
              <tr data-nama="<?= e(strtolower((string) $g['nama'] . ' ' . $nip . ' ' . (string) ($g['jabatan'] ?? ''))) ?>">
                <td class="ctr">
                  <input type="checkbox" class="chk-hadir" name="hadir[]" value="<?= $gid ?>"
                         <?= isset($pilihTercentang[$gid]) ? 'checked' : '' ?> aria-label="Hadir: <?= e((string) $g['nama']) ?>">
                </td>
                <td><b><?= e((string) $g['nama']) ?></b></td>
                <td><?= $nip !== '' ? e($nip) : '-' ?></td>
                <td><?= e((string) ($g['jabatan'] ?: '-')) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <div class="form-actions">
      <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $edit ? 'Simpan Perubahan' : 'Simpan Notulen' ?></button>
      <a class="btn btn-ghost" href="notulen.php">Batal</a>
      <?php if (!$daftarGuru): ?>
        <span class="hint">Daftar hadir wajib berisi minimal satu guru, jadi notulen belum dapat disimpan.</span>
      <?php endif; ?>
    </div>
  </form>
</div>

<?php // ---------------------------------------------------------------- foto bukti kegiatan
      // Maksimal NOTULEN_FOTO_MAKS (2) foto per notulen. Foto menempel pada notulen yang sudah
      // tersimpan, jadi kartu ini aktif setelah notulen disimpan (mode ubah). ?>
<div class="card" id="cardFotoNotulen">
  <div class="card-head">
    <div>
      <h2>Foto Bukti Kegiatan Rapat</h2>
      <p>Dokumentasi kegiatan rapat sebagai bukti pelaksanaan &mdash; maksimal <?= NOTULEN_FOTO_MAKS ?> foto.</p>
    </div>
    <?php if ($edit): ?>
      <span class="pill <?= $jmlFoto >= NOTULEN_FOTO_MAKS ? 'pill-warn' : 'pill-ok' ?>">
        <?= $jmlFoto ?> dari <?= NOTULEN_FOTO_MAKS ?> foto
      </span>
    <?php endif; ?>
  </div>

  <?php if (!$edit): ?>
    <div class="alert alert-warn">
      <?= ikon('alert', 16) ?>
      <span>Simpan notulen ini terlebih dahulu. Setelah tersimpan, halaman ini menampilkan pilihan
        untuk mengunggah foto bukti kegiatan (maksimal <?= NOTULEN_FOTO_MAKS ?> foto).</span>
    </div>
  <?php else: ?>
    <?php if ($fotoTersimpan): ?>
      <div class="not-foto-grid mb16">
        <?php foreach ($fotoTersimpan as $i => $f): ?>
          <div class="not-foto-item">
            <div class="not-foto-gambar">
              <img src="<?= e((string) $f['url']) ?>" alt="Foto bukti kegiatan <?= $i + 1 ?>">
            </div>
            <form method="post" action="upload_notulen_foto.php" class="not-foto-ket">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="keterangan">
              <input type="hidden" name="notulen_id" value="<?= (int) $edit['id'] ?>">
              <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
              <input type="text" name="keterangan" value="<?= e((string) ($f['keterangan'] ?? '')) ?>"
                     placeholder="Keterangan foto (mis. suasana rapat)" maxlength="160">
              <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('save', 14) ?> Simpan</button>
            </form>
            <form method="post" action="upload_notulen_foto.php" data-confirm="Hapus foto bukti kegiatan ini?">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="notulen_id" value="<?= (int) $edit['id'] ?>">
              <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
              <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Hapus foto <?= $i + 1 ?></button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="mut sm mb16">Belum ada foto bukti kegiatan pada notulen ini.</p>
    <?php endif; ?>

    <?php if ($jmlFoto >= NOTULEN_FOTO_MAKS): ?>
      <div class="alert alert-warn">
        <?= ikon('alert', 16) ?>
        <span>Sudah <?= NOTULEN_FOTO_MAKS ?> foto (batas maksimal). Hapus salah satu foto di atas bila ingin menggantinya.</span>
      </div>
    <?php else: ?>
      <form class="foto-form" id="formFotoNotulen" method="post" action="upload_notulen_foto.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="unggah">
        <input type="hidden" name="notulen_id" value="<?= (int) $edit['id'] ?>">
        <label class="f" for="fotoNotulen">Pilih berkas gambar (foto kegiatan rapat)</label>
        <input type="file" id="fotoNotulen" name="foto" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,image/*">
        <div class="foto-input-baris mt8">
          <div class="foto-preview-wrap" id="fotoNotulenPreviewWrap" hidden>
            <div class="foto-preview" id="fotoNotulenPreview"></div>
            <span class="xs mut" id="fotoNotulenUkuran"></span>
          </div>
          <div style="flex:1 1 260px;min-width:220px">
            <label class="f" for="fotoNotulenKet">Keterangan (opsional)</label>
            <input type="text" id="fotoNotulenKet" name="keterangan" maxlength="160" placeholder="mis. suasana pembahasan rapat">
            <div class="hint" id="fotoNotulenHint">
              Maksimal 15 MB, format JPG/PNG/GIF/WEBP/SVG. Sisa slot: <b><?= $sisaFoto ?></b> foto.
            </div>
            <div class="form-actions">
              <button class="btn btn-primary" id="btnFotoNotulen" type="submit"><?= ikon('upload', 16) ?> Unggah foto bukti</button>
              <span class="xs mut" id="fotoNotulenStatus"></span>
            </div>
          </div>
        </div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php
page_bottom();
