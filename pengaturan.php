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

    // Simpan KHUSUS jenis (tombol sendiri pada kartu "Jenis Sekolah/Madrasah").
    if ($aksi === 'simpan_jenis') {
        if (!array_key_exists('jenis_sekolah', $_POST)) {
            flash('Pilih jenis sekolah terlebih dahulu.', 'err');
            redirect('pengaturan.php');
        }
        $jenis = ((string) $_POST['jenis_sekolah']) === 'madrasah' ? 'madrasah' : 'umum';
        set_setting('jenis_sekolah', $jenis);
        // Pesan disusun tanpa kata umum "Sekolah" pada cabang madrasah (pesan ikut melewati istilah).
        flash($jenis === 'madrasah'
            ? 'Jenis disimpan: MADRASAH. Seluruh istilah kini memakai kata "Madrasah" — termasuk pada hasil cetak.'
            : 'Jenis disimpan: SEKOLAH UMUM. Istilah kembali memakai kata "Sekolah" pada aplikasi dan hasil cetak.');
        redirect('pengaturan.php');
    }

    if ($aksi === 'simpan') {
        foreach (['nama_sekolah', 'npsn', 'alamat_sekolah', 'telepon_sekolah', 'email_sekolah', 'website', 'kepala_sekolah', 'nip_kepala_sekolah'] as $k) {
            set_setting($k, trim((string) ($_POST[$k] ?? '')));
        }
        if (array_key_exists('jenis_sekolah', $_POST)) {
            set_setting('jenis_sekolah', ((string) $_POST['jenis_sekolah']) === 'madrasah' ? 'madrasah' : 'umum');
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
          <label class="f" for="telepon_sekolah">Telepon</label>
          <input type="text" id="telepon_sekolah" name="telepon_sekolah" value="<?= e((string) $s['telepon_sekolah']) ?>">
        </div>
      </div>
      <div>
        <label class="f" for="alamat_sekolah">Alamat</label>
        <textarea id="alamat_sekolah" name="alamat_sekolah" rows="2"><?= e((string) $s['alamat_sekolah']) ?></textarea>
      </div>
      <div class="grid g2" style="gap:16px">
        <div>
          <label class="f" for="email_sekolah">Email</label>
          <input type="text" id="email_sekolah" name="email_sekolah" value="<?= e((string) $s['email_sekolah']) ?>">
        </div>
        <div>
          <label class="f" for="website">Situs/laman</label>
          <input type="text" id="website" name="website" value="<?= e((string) $s['website']) ?>">
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
      </div>

      <div class="divider"></div>
      <div class="section-title"><?= ikon('chart', 15) ?> Penilaian Kurikulum Merdeka</div>
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
          <label class="f" for="batas_a">Predikat A mulai</label>
          <input type="number" id="batas_a" name="batas_a" min="0" max="100" value="<?= e(r0($th['batas_a'])) ?>">
        </div>
        <div>
          <label class="f" for="batas_b">Predikat B mulai</label>
          <input type="number" id="batas_b" name="batas_b" min="0" max="100" value="<?= e(r0($th['batas_b'])) ?>">
        </div>
        <div>
          <label class="f" for="batas_c">Predikat C mulai</label>
          <input type="number" id="batas_c" name="batas_c" min="0" max="100" value="<?= e(r0($th['batas_c'])) ?>">
        </div>
      </div>
      <p class="hint" style="margin-top:-6px">Di bawah ambang C otomatis berpredikat D. Ambang ini dipakai pada daftar nilai, ekspor, dan lembar cetak.</p>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Pengaturan</button>
      </div>
    </form>
  </div>

  <div class="grid" style="gap:20px;align-content:start">
    <div class="card">
      <div class="card-head">
        <div><h2>Jenis <?= e(istilah_sekolah('Sekolah')) ?></h2><p>Menentukan istilah pada aplikasi &amp; dokumen cetak.</p></div>
      </div>
      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan_jenis">
        <div>
          <label class="f" for="jenisSekolah">Jenis</label>
          <select id="jenisSekolah" name="jenis_sekolah" data-tersimpan="<?= e(jenis_sekolah()) ?>">
            <option value="umum" <?= jenis_sekolah() === 'umum' ? 'selected' : '' ?>>Sekolah Umum (SD/SMP/SMA/SMK)</option>
            <option value="madrasah" <?= jenis_sekolah() === 'madrasah' ? 'selected' : '' ?>>Madrasah (MI/MTs/MA)</option>
          </select>
          <div class="hint" id="jenisCatatan">Istilah yang dipakai saat ini.</div>
        </div>
        <button class="btn" id="btnSimpanJenis" type="submit"><?= ikon('save', 16) ?> Simpan Jenis</button>
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
              <div class="hint" id="logoHint">Maksimal 15 MB. Disarankan PNG/SVG persegi latar transparan.</div>
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
