<?php
/**
 * Pengaturan aplikasi: identitas sekolah/madrasah, jenis (Sekolah/Madrasah), logo kop,
 * dan aturan penilaian Kurikulum Merdeka (jumlah kolom Formatif/Sumatif + bobot ASAS).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_pengaturan();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $aksi = (string) ($_POST['aksi'] ?? '');

    // Simpan KHUSUS jenis (tombol sendiri pada kartu "Jenis Lembaga").
    if ($aksi === 'simpan_jenis') {
        if (!array_key_exists('jenis_sekolah', $_POST)) {
            flash('Pilih jenis lembaga terlebih dahulu.', 'err');
            redirect('pengaturan.php');
        }
        $jenis = ((string) $_POST['jenis_sekolah']) === 'madrasah' ? 'madrasah' : 'umum';
        set_setting('jenis_sekolah', $jenis);
        // v49: jenjang yang tidak berlaku untuk jenis baru DIKOSONGKAN (mis. sebelumnya MI lalu jenis
        // diubah menjadi Sekolah Umum) supaya acuan tingkat kelas & konsep guru kelas tidak salah.
        $jenjangLama = trim((string) setting('jenjang_sekolah', ''));
        $jenjangHapus = $jenjangLama !== '' && !jenjang_sah($jenjangLama, $jenis);
        if ($jenjangHapus) {
            set_setting('jenjang_sekolah', '');
        }
        // Pesan disusun tanpa kata umum "Sekolah" pada cabang madrasah (pesan ikut melewati istilah).
        flash($jenis === 'madrasah'
            ? 'Jenis lembaga disimpan: MADRASAH. Seluruh istilah kini memakai kata "Madrasah" — termasuk pada hasil cetak.'
            : 'Jenis lembaga disimpan: SEKOLAH UMUM. Istilah kembali memakai kata "Sekolah" pada aplikasi dan hasil cetak.');
        if ($jenjangHapus) {
            flash('Pilihan jenjang (' . $jenjangLama . ') tidak berlaku untuk jenis lembaga yang baru — silakan pilih jenjang kembali.', 'warn');
        }
        redirect('pengaturan.php');
    }

    // Simpan KHUSUS jenjang (tombol sendiri pada bagian "Pilihan Jenjang").
    if ($aksi === 'simpan_jenjang') {
        $jenjang = trim((string) ($_POST['jenjang_sekolah'] ?? ''));
        if ($jenjang !== '' && !jenjang_sah($jenjang)) {
            flash('Jenjang "' . $jenjang . '" tidak berlaku untuk jenis lembaga ini. Pilihan yang sah: '
                . implode(', ', jenjang_pilihan()) . '.', 'err');
            redirect('pengaturan.php');
        }
        set_setting('jenjang_sekolah', $jenjang);
        if ($jenjang === '') {
            flash('Pilihan jenjang dikosongkan.', 'warn');
        } elseif (is_guru_kelas_jenjang()) {
            flash('Jenjang disimpan: ' . jenjang_label($jenjang) . '. Karena jenjang SD/MI, guru dapat '
                . 'ditandai sebagai GURU KELAS (memegang banyak mata pelajaran pada satu kelas) pada '
                . 'menu Data Guru.');
        } else {
            flash('Jenjang disimpan: ' . jenjang_label($jenjang) . '. Pengaturan penilaian & data guru '
                . 'tetap seperti sebelumnya.');
        }
        redirect('pengaturan.php');
    }

    if ($aksi === 'simpan') {
        // v37: identitas lembaga — NSM + alamat per wilayah (permintaan pemilik).
        // v39: identitas — NAMA + NIP PENGAWAS BINA (permintaan pemilik).
        foreach (['nama_sekolah', 'npsn', 'nsm', 'alamat_sekolah', 'desa', 'kecamatan', 'kabupaten',
                  'provinsi', 'telepon_sekolah', 'email_sekolah', 'website',
                  'kepala_sekolah', 'nip_kepala_sekolah', 'pengawas_nama', 'pengawas_nip'] as $k) {
            set_setting($k, trim((string) ($_POST[$k] ?? '')));
        }
        if (array_key_exists('jenis_sekolah', $_POST)) {
            set_setting('jenis_sekolah', ((string) $_POST['jenis_sekolah']) === 'madrasah' ? 'madrasah' : 'umum');
        }
        // v49: jenjang hanya ditulis bila medannya benar-benar dikirim (form lama tetap aman) dan
        // nilainya sah untuk jenis lembaga yang sedang dipakai.
        if (array_key_exists('jenjang_sekolah', $_POST)) {
            $jenjangKirim = trim((string) $_POST['jenjang_sekolah']);
            if ($jenjangKirim === '' || jenjang_sah($jenjangKirim)) {
                set_setting('jenjang_sekolah', $jenjangKirim);
            }
        }

        $jf = (int) ($_POST['jumlah_formatif'] ?? 4);
        $js = (int) ($_POST['jumlah_sumatif'] ?? 4);
        $bobot = (float) str_replace(',', '.', (string) ($_POST['bobot_asas'] ?? 50));
        $a = (float) str_replace(',', '.', (string) ($_POST['batas_a'] ?? 90));
        $b = (float) str_replace(',', '.', (string) ($_POST['batas_b'] ?? 80));
        $c = (float) str_replace(',', '.', (string) ($_POST['batas_c'] ?? 70));

        $galat = [];
        if ($jf < 1 || $jf > 12) {
            $galat[] = 'Jumlah kolom Formatif harus 1 sampai 12.';
        }
        if ($js < 1 || $js > 12) {
            $galat[] = 'Jumlah kolom Sumatif Lingkup Materi harus 1 sampai 12.';
        }
        if ($bobot < 0 || $bobot > 100) {
            $galat[] = 'Bobot Sumatif Akhir Semester (ASAS) harus 0 sampai 100 persen.';
        }
        if (!($a > $b && $b > $c && $c >= 0 && $a <= 100)) {
            $galat[] = 'Ambang predikat tidak valid. Pastikan A > B > C dan berada pada rentang 0-100.';
        }
        if ($galat) {
            foreach ($galat as $g) {
                flash($g, 'err');
            }
        } else {
            set_setting('jumlah_formatif', (string) $jf);
            set_setting('jumlah_sumatif', (string) $js);
            set_setting('bobot_asas', (string) $bobot);
            set_setting('batas_a', (string) $a);
            set_setting('batas_b', (string) $b);
            set_setting('batas_c', (string) $c);
            flash('Pengaturan aplikasi disimpan. Bobot ASAS ' . r1($bobot) . '% dan Sumatif Lingkup Materi ' . r1(100 - $bobot) . '%.');
        }
        redirect('pengaturan.php');
    }
}

$s = settings_all(true);
$ta = ta_aktif();
$sem = semester_aktif();
$th = thresholds();
$bobotAsas = bobot_asas();

page_top('Identitas & Pengaturan');
?>
<div class="grid g2">
  <div class="card">
    <div class="card-head">
      <div><h2>Identitas <?= e(istilah_sekolah('Sekolah')) ?></h2><p>Tampil pada kop seluruh dokumen cetak dan halaman aplikasi.</p></div>
    </div>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <?php // Permintaan pemilik (v37): identitas lembaga memuat Nama, NPSN, NSM, alamat terpisah per
            // wilayah (Alamat/Nama Jalan+Dusun, Desa/Kelurahan, Kecamatan, Kabupaten, Provinsi),
            // email, situs/laman, serta Nama & NIP kepala. Kecamatan juga dipakai sebagai TEMPAT pada
            // baris "tempat, tanggal" di atas tanda tangan seluruh dokumen cetak. ?>
      <div>
        <label class="f" for="nama_sekolah">Nama <?= e(istilah_sekolah('Sekolah')) ?></label>
        <input type="text" id="nama_sekolah" name="nama_sekolah" value="<?= e((string) $s['nama_sekolah']) ?>" placeholder="mis. MA. Sirojuth Tholibiin Mootilango">
      </div>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="npsn">NPSN</label>
          <input type="text" id="npsn" name="npsn" value="<?= e((string) $s['npsn']) ?>">
        </div>
        <div>
          <label class="f" for="nsm">NSM</label>
          <input type="text" id="nsm" name="nsm" value="<?= e((string) $s['nsm']) ?>" placeholder="Nomor Statistik Madrasah">
          <div class="hint">Diisi untuk madrasah; ikut tercetak pada kop dokumen bila diisi.</div>
        </div>
      </div>
      <div>
        <label class="f" for="alamat_sekolah">Alamat (Nama Jalan, Dusun)</label>
        <textarea id="alamat_sekolah" name="alamat_sekolah" rows="2" placeholder="mis. Jl. Dusun Gandaria"><?= e((string) $s['alamat_sekolah']) ?></textarea>
      </div>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="desa">Desa/Kelurahan</label>
          <input type="text" id="desa" name="desa" value="<?= e((string) $s['desa']) ?>">
        </div>
        <div>
          <label class="f" for="kecamatan">Kecamatan</label>
          <input type="text" id="kecamatan" name="kecamatan" value="<?= e((string) $s['kecamatan']) ?>">
          <div class="hint">Dipakai sebagai <b>tempat</b> pada baris "tempat, tanggal" di atas tanda tangan dokumen cetak.</div>
        </div>
      </div>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="kabupaten">Kabupaten</label>
          <input type="text" id="kabupaten" name="kabupaten" value="<?= e((string) $s['kabupaten']) ?>">
        </div>
        <div>
          <label class="f" for="provinsi">Provinsi</label>
          <input type="text" id="provinsi" name="provinsi" value="<?= e((string) $s['provinsi']) ?>">
        </div>
      </div>
      <div class="grid g3" style="gap:16px">
        <div>
          <label class="f" for="email_sekolah">Email</label>
          <input type="text" id="email_sekolah" name="email_sekolah" value="<?= e((string) $s['email_sekolah']) ?>">
        </div>
        <div>
          <label class="f" for="website">Situs/laman</label>
          <input type="text" id="website" name="website" value="<?= e((string) $s['website']) ?>">
        </div>
        <div>
          <label class="f" for="telepon_sekolah">Telepon / HP</label>
          <input type="text" id="telepon_sekolah" name="telepon_sekolah" value="<?= e((string) $s['telepon_sekolah']) ?>">
          <div class="hint">Tercetak sebagai "HP" pada kop dokumen.</div>
        </div>
      </div>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="kepala_sekolah">Nama <?= e(istilah_sekolah('Kepala Sekolah')) ?></label>
          <input type="text" id="kepala_sekolah" name="kepala_sekolah" value="<?= e((string) $s['kepala_sekolah']) ?>">
        </div>
        <div>
          <label class="f" for="nip_kepala_sekolah">NIP <?= e(istilah_sekolah('Kepala Sekolah')) ?></label>
          <input type="text" id="nip_kepala_sekolah" name="nip_kepala_sekolah" value="<?= e((string) $s['nip_kepala_sekolah']) ?>" placeholder="boleh dikosongkan">
        </div>
        <?php // v39: identitas PENGAWAS BINA (nama & NIP) — permintaan pemilik; letaknya tepat di
              // bawah Nama/NIP Kepala, sama seperti pada halaman pendaftaran. Data ini diisi pertama
              // kali saat sekolah mendaftar, dan tetap dapat diubah/dilengkapi di sini. ?>
        <div>
          <label class="f" for="pengawas_nama">Nama Pengawas Bina</label>
          <input type="text" id="pengawas_nama" name="pengawas_nama" value="<?= e((string) $s['pengawas_nama']) ?>" placeholder="nama &amp; gelar">
          <div class="hint">Pengawas sekolah/madrasah yang membina lembaga ini.</div>
        </div>
        <div>
          <label class="f" for="pengawas_nip">NIP Pengawas Bina</label>
          <input type="text" id="pengawas_nip" name="pengawas_nip" value="<?= e((string) $s['pengawas_nip']) ?>" placeholder="boleh dikosongkan">
        </div>
      </div>

      <div class="divider"></div>
      <div class="section-title"><?= ikon('chart', 15) ?> Penilaian Siswa (Pengaturan Sekolah)</div>
      <p class="hint" style="margin-top:0">Pengaturan ini dipakai untuk <b>seluruh mata pelajaran</b> dan
        menjadi cadangan bila seorang guru belum mengatur penilaian mapelnya sendiri — guru dapat
        menyesuaikannya di halaman <b>Penilaian Siswa</b> (tombol "Pengaturan Penilaian").</p>
      <div class="grid g3" style="gap:16px">
        <div>
          <label class="f" for="jumlah_formatif">Kolom Nilai Formatif</label>
          <input type="number" id="jumlah_formatif" name="jumlah_formatif" min="1" max="12" value="<?= (int) setting_int('jumlah_formatif', 4) ?>">
          <div class="hint">Penilaian proses — tidak dihitung ke nilai akhir.</div>
        </div>
        <div>
          <label class="f" for="jumlah_sumatif">Kolom Sumatif Lingkup Materi</label>
          <input type="number" id="jumlah_sumatif" name="jumlah_sumatif" min="1" max="12" value="<?= (int) setting_int('jumlah_sumatif', 4) ?>">
          <div class="hint">Dipakai untuk rata-rata Sumatif Lingkup Materi.</div>
        </div>
        <div>
          <label class="f" for="bobot_asas">Bobot ASAS (%)</label>
          <input type="number" id="bobot_asas" name="bobot_asas" min="0" max="100" step="1" value="<?= e(r0($bobotAsas)) ?>">
          <div class="hint">Bobot Sumatif Lingkup Materi otomatis <?= e(r0(bobot_sumatif())) ?>%.</div>
        </div>
      </div>
      <div class="note-rumus">
        <b>Rumus nilai akhir:</b>
        (rata-rata Sumatif Lingkup Materi &times; <?= e(r0(bobot_sumatif())) ?>%) + (Sumatif Akhir Semester &times; <?= e(r0($bobotAsas)) ?>%)
      </div>

      <div class="grid g3" style="gap:16px">
        <div>
          <label class="f" for="batas_a">Predikat A (Amat Baik) mulai</label>
          <input type="number" id="batas_a" name="batas_a" min="0" max="100" value="<?= e(r0($th['batas_a'])) ?>">
        </div>
        <div>
          <label class="f" for="batas_b">Predikat B (Baik) mulai</label>
          <input type="number" id="batas_b" name="batas_b" min="0" max="100" value="<?= e(r0($th['batas_b'])) ?>">
        </div>
        <div>
          <label class="f" for="batas_c">Predikat C (Cukup) mulai</label>
          <input type="number" id="batas_c" name="batas_c" min="0" max="100" value="<?= e(r0($th['batas_c'])) ?>">
        </div>
      </div>
      <p class="hint" style="margin-top:-6px">Di bawah ambang C otomatis berpredikat <b>D (Kurang)</b>. Ambang ini dipakai pada daftar nilai, ekspor, dan lembar cetak. Keterangan tiap predikat: <?= e(predikat_keterangan()) ?>.</p>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Pengaturan</button>
      </div>
    </form>
  </div>

  <div class="grid" style="gap:20px;align-content:start">
    <?php // v49: judul kartu diganti "Jenis Lembaga" (permintaan pemilik), dan fitur PILIHAN JENJANG
          // ditambahkan pada bagian kanan kepala kartu (posisi yang ditandai pemilik) + formulirnya
          // di dalam kartu yang sama. Pilihan jenjang mengikuti jenis lembaga: Sekolah Umum →
          // SD/SMP/SMA/SMK · Madrasah → MI/MTs/MA/MAK. Jenjang SD/MI memakai konsep GURU KELAS. ?>
    <?php $jenjangKini = jenjang_sekolah(); ?>
    <div class="card" id="cardJenis">
      <div class="card-head">
        <div><h2>Jenis Lembaga</h2><p>Menentukan istilah pada aplikasi &amp; dokumen cetak.</p></div>
        <span class="pill <?= $jenjangKini !== '' ? 'pill-gold' : 'pill-gray' ?>" id="pilJenjang">
          <?= $jenjangKini !== '' ? 'Jenjang: ' . e($jenjangKini) : 'Jenjang belum dipilih' ?>
        </span>
      </div>
      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan_jenis">
        <div>
          <label class="f" for="jenisSekolah">Jenis Lembaga</label>
          <?php // LABEL PILIHAN DILINDUNGI tetap_kata(): pada sekolah berjenis MADRASAH, penyesuaian
                // istilah (terjemahkan_html) akan mengubah kata "Sekolah" menjadi "Madrasah" —
                // sehingga pilihan "Sekolah Umum (SD/SMP/SMA/SMK)" tampil keliru sebagai
                // "Madrasah Umum (SD/SMP/SMA/SMK)" (dilaporkan pemilik). Nama kedua jenis lembaga
                // adalah istilah TETAP (bukan label yang mengikuti jenis sekolah), jadi wajib
                // dicetak apa adanya. JANGAN melepas tetap_kata() di sini. ?>
          <select id="jenisSekolah" name="jenis_sekolah" data-tersimpan="<?= e(jenis_sekolah()) ?>">
            <option value="umum" <?= jenis_sekolah() === 'umum' ? 'selected' : '' ?>><?= e(tetap_kata('Sekolah Umum (SD/SMP/SMA/SMK)')) ?></option>
            <option value="madrasah" <?= jenis_sekolah() === 'madrasah' ? 'selected' : '' ?>><?= e(tetap_kata('Madrasah (MI/MTs/MA/MAK)')) ?></option>
          </select>
          <div class="hint" id="jenisCatatan">Istilah yang dipakai saat ini.</div>
        </div>
        <button class="btn" id="btnSimpanJenis" type="submit"><?= ikon('save', 16) ?> Simpan Jenis</button>
      </form>

      <div class="divider"></div>
      <div class="section-title"><?= ikon('school', 15) ?> Pilihan Jenjang</div>
      <form method="post" class="form-grid" id="formJenjang">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan_jenjang">
        <div>
          <label class="f" for="jenjangSekolah">Jenjang</label>
          <?php // Pilihan jenjang mengikuti JENIS lembaga. Atribut data-jenjang membawa daftar pilihan
                // supaya initJenisJenjang() dapat menyusun ulang pilihan saat jenis diganti tanpa
                // memuat ulang halaman; server tetap memeriksa keabsahannya. ?>
          <select id="jenjangSekolah" name="jenjang_sekolah"
                  data-jenjang="<?= e(json_encode(JENJANG_PILIHAN, JSON_UNESCAPED_UNICODE)) ?>"
                  data-label="<?= e(json_encode(JENJANG_LABEL, JSON_UNESCAPED_UNICODE)) ?>">
            <option value="">— belum dipilih —</option>
            <?php foreach (jenjang_pilihan() as $jj): ?>
              <option value="<?= e($jj) ?>" <?= $jenjangKini === $jj ? 'selected' : '' ?>><?= e(JENJANG_LABEL[$jj] ?? $jj) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint" id="jenjangCatatan">
            <?php if ($jenjangKini === ''): ?>
              Belum dipilih — acuan tingkat kelas belum tersedia.
            <?php elseif (is_guru_kelas_jenjang()): ?>
              Acuan tingkat kelas: <b><?= e(jenjang_tingkat_teks()) ?></b>. Jenjang ini memakai konsep
              <b>guru kelas</b> — satu guru memegang banyak mata pelajaran pada satu kelas dan otomatis
              menjadi wali kelas.
            <?php else: ?>
              Acuan tingkat kelas: <b><?= e(jenjang_tingkat_teks()) ?></b>. Pengaturan lainnya tetap
              seperti sekarang.
            <?php endif; ?>
          </div>
        </div>
        <button class="btn btn-primary" id="btnSimpanJenjang" type="submit"><?= ikon('save', 16) ?> Simpan Jenjang</button>
      </form>
    </div>

    <div class="card" id="cardLogo">
      <div class="card-head">
        <div><h2>Logo (Kop Dokumen Cetak)</h2><p>Tampil pada kop seluruh dokumen cetak: daftar nilai, rekap kehadiran, jurnal, catatan pelanggaran, serta daftar data guru &amp; siswa.</p></div>
      </div>
      <div class="foto-baris">
        <div class="foto-sisi">
          <div class="logo-kotak">
            <?php if (ada_logo()): ?>
              <img src="<?= e(logo_sekolah()) ?>" alt="Logo <?= e(istilah_sekolah('Sekolah')) ?>">
            <?php else: ?>
              <span class="mut xs">Belum ada logo</span>
            <?php endif; ?>
          </div>
          <?php if (ada_logo()): ?>
            <form method="post" action="upload_logo.php" data-confirm="Hapus logo <?= e(istilah_sekolah('Sekolah')) ?>?">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="hapus">
              <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 15) ?> Hapus logo</button>
            </form>
          <?php endif; ?>
        </div>
        <form class="foto-form" id="formLogo" method="post" action="upload_logo.php" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <label class="f" for="logo">Pilih berkas gambar</label>
          <input type="file" id="logo" name="logo" accept=".jpg,.jpeg,.png,.gif,.webp,.svg,image/*">
          <div class="foto-input-baris mt8">
            <div class="foto-preview-wrap" id="logoPreviewWrap" <?= ada_logo() ? '' : 'hidden' ?>>
              <div class="foto-preview" id="logoPreview">
                <?php if (ada_logo()): ?><img src="<?= e(logo_sekolah()) ?>" alt="Pratinjau logo"><?php endif; ?>
              </div>
              <span class="xs mut" id="logoUkuran"><?= e(istilah_sekolah('Sekolah')) ?></span>
            </div>
            <div>
              <div class="hint" id="logoHint">Maksimal <?= e(maks_unggah_teks()) ?>. Disarankan PNG/SVG persegi latar transparan.</div>
              <div class="form-actions">
                <button class="btn btn-primary" id="btnLogo" type="submit"><?= ikon('upload', 16) ?> <?= ada_logo() ? 'Ganti logo' : 'Unggah logo' ?></button>
                <span class="xs mut" id="logoStatus"></span>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <div><h2>Periode Aktif</h2><p>Periode yang dipakai untuk pengisian baru <?= e(istilah_sekolah('sekolah')) ?> ini.</p></div>
        <a class="btn btn-ghost btn-sm" href="tahun_ajaran.php"><?= ikon('calendar', 15) ?> Kelola</a>
      </div>
      <div class="stat-mini">
        <div><b><?= e(ta_nama($ta)) ?></b><span>Tahun ajaran aktif</span></div>
        <div><b><?= e(sem_nama($sem)) ?></b><span>Semester aktif</span></div>
        <div><b><?= (int) val('SELECT COUNT(*) FROM kelas WHERE aktif = 1 AND sekolah_id = ?', [sekolah_id()], 0) ?></b><span>Kelas aktif</span></div>
        <div><b><?= (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND sekolah_id = ?', [sekolah_id()], 0) ?></b><span>Siswa aktif</span></div>
      </div>
    </div>
  </div>
</div>
<?php
page_bottom();
