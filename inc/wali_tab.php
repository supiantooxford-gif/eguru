<?php
/**
 * ISI EMPAT TAB menu Wali Kelas (di-require oleh wali_halaman() di inc/wali.php).
 *
 * Dipisah ke berkas sendiri supaya `inc/wali.php` (data + logika + penjaga) tetap terbaca; variabel
 * yang dipakai di sini berasal dari wali_halaman(): $tab, $kelasId, $kelas, $siswa, $struktur,
 * $program, $piket, $ringkasP, $baganSusun, $baganStat, $tampilan, $editS, $editW, $bolehUbah,
 * $tanggalAbsen, $sesiAbsen, $detailAbsen, $daftarAbsen, $rekapAbsen, $waliNama.
 */
declare(strict_types=1);
if (!defined('APP_NAME')) {
    exit;
}
?>
<?php // ============================================================ 1. STRUKTUR ORGANISASI ?>
<?php if ($tab === 'struktur'): ?>
  <div class="chip-list warna mb16">
    <a class="chip <?= $tampilan === 'tabel' ? 'on' : '' ?>" href="<?= e(wali_url($kelasId, 'struktur', ['tampilan' => 'tabel'])) ?>"><?= ikon('clipboard', 14) ?> Tabel</a>
    <a class="chip <?= $tampilan === 'bagan' ? 'on' : '' ?>" href="<?= e(wali_url($kelasId, 'struktur', ['tampilan' => 'bagan'])) ?>"><?= ikon('chart', 14) ?> Bagan</a>
    <a class="chip" href="cetak_wali_struktur.php?kelas=<?= $kelasId ?>" target="_blank"><?= ikon('print', 14) ?> Cetak Tabel</a>
    <a class="chip" href="cetak_wali_bagan.php?kelas=<?= $kelasId ?>" target="_blank"><?= ikon('print', 14) ?> Cetak Bagan</a>
    <?php if ($bolehUbah): ?>
      <a class="chip" href="wali_import.php?kelas=<?= $kelasId ?>&bagian=struktur"><?= ikon('upload', 14) ?> Impor Excel/CSV</a>
      <a class="chip" href="wali_export.php?kelas=<?= $kelasId ?>&bagian=struktur"><?= ikon('doc', 14) ?> Ekspor</a>
    <?php endif; ?>
  </div>

  <?php if ($tampilan === 'bagan'): ?>
    <div class="grid g4" style="gap:16px">
      <div class="card stat card-tight" style="margin:0">
        <div class="stat-ic g"><?= ikon('users', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $baganStat['nama'] ?></div><div class="stat-lbl">Nama pada bagan</div></div>
      </div>
      <div class="card stat card-tight" style="margin:0">
        <div class="stat-ic"><?= ikon('folder', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $baganStat['kotak'] ?></div><div class="stat-lbl">Kotak jabatan/seksi</div></div>
      </div>
      <div class="card stat card-tight" style="margin:0">
        <div class="stat-ic o"><?= ikon('chart', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $baganStat['tingkat'] ?></div><div class="stat-lbl">Tingkat/baris</div></div>
      </div>
      <div class="card stat card-tight" style="margin:0">
        <div class="stat-ic p"><?= ikon('school', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.1rem"><?= e((string) $kelas['nama']) ?></div><div class="stat-lbl">Kelas</div></div>
      </div>
    </div>

    <div class="card mt16">
      <div class="card-head">
        <div><h2><?= ikon('chart', 16) ?> <?= e(wali_bagan_judul($kelas)) ?></h2>
          <p>Digambar dari baris <b>Struktur Organisasi</b>. Bagan dapat digeser mendatar di layar
            sempit; gunakan <b>Cetak Bagan</b> untuk hasil A4.</p></div>
        <div class="flex">
          <a class="btn btn-ghost btn-sm" href="cetak_wali_bagan.php?kelas=<?= $kelasId ?>" target="_blank"><?= ikon('print', 15) ?> Cetak Bagan</a>
          <?php if ($bolehUbah): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(wali_url($kelasId, 'struktur', ['tampilan' => 'tabel'])) ?>"><?= ikon('edit', 15) ?> Atur Baris &amp; Nama</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="osim-bagan-luapan">
        <?= wali_bagan_html($struktur, $kelas) ?>
      </div>
    </div>

    <?php if ($bolehUbah && $baganSusun): ?>
      <div class="card">
        <div class="card-head">
          <div><h2><?= ikon('clipboard', 16) ?> Baris pada Bagan (urutan tingkat)</h2>
            <p>Tingkat baku: 1 Wali Kelas · 2 Ketua Kelas · 3 Wakil Ketua Kelas · 4 Sekretaris ·
              5 Bendahara · 6 Seksi-seksi · 7 Anggota. Letaknya dapat diubah pada formulir
              (kolom <b>Baris bagan</b>).</p></div>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th class="ctr" style="width:70px">Tingkat</th><th>Jabatan / Nama Seksi</th>
              <th class="ctr">Jumlah nama</th><th class="ctr" style="width:120px">Tindakan</th></tr></thead>
            <tbody>
            <?php foreach ($baganSusun as $t => $kotak): ?>
              <?php foreach ($kotak as $b): ?>
                <tr>
                  <td class="ctr"><span class="pill pill-B"><?= (int) $t ?></span></td>
                  <td><b><?= e((string) $b['judul']) ?></b></td>
                  <td class="ctr"><?= count($b['nama']) ?></td>
                  <td class="ctr">
                    <a class="btn btn-ghost btn-sm" href="<?= e(wali_url($kelasId, 'struktur', ['edit' => (int) $b['baris'][0]])) ?>#formStruktur"><?= ikon('edit', 14) ?> Ubah</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <?php if ($bolehUbah): ?>
      <div class="card" id="formStruktur" style="margin-bottom:18px">
        <div class="card-head">
          <div>
            <h2><?= ikon($editS ? 'edit' : 'plus', 16) ?> <?= $editS ? 'Ubah Jabatan / Seksi' : 'Tambah Jabatan / Seksi' ?></h2>
            <p>Satu baris = satu jabatan, satu seksi, atau satu anggota. Isi nama pengurusnya pada
              kolom <b>Nama</b> (boleh dikosongkan dulu).</p>
          </div>
          <?php if ($editS): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(wali_url($kelasId, 'struktur')) ?>"><?= ikon('prev', 14) ?> Batal</a>
          <?php endif; ?>
        </div>
        <form method="post" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="struktur_simpan">
          <input type="hidden" name="kelas" value="<?= $kelasId ?>">
          <input type="hidden" name="id" value="<?= (int) ($editS['id'] ?? 0) ?>">
          <div class="grid g3" style="gap:16px">
            <div>
              <label class="f" for="jabatanW">Jabatan / Nama Seksi *</label>
              <input type="text" id="jabatanW" name="jabatan" maxlength="<?= WALI_TEKS_MAKS ?>" required
                     list="saranJabatanW" value="<?= e((string) ($editS['jabatan'] ?? '')) ?>"
                     placeholder="mis. Ketua Kelas atau Seksi Kebersihan">
              <datalist id="saranJabatanW">
                <?php foreach (wali_jabatan() as $j): ?><option value="<?= e($j) ?>"></option><?php endforeach; ?>
              </datalist>
            </div>
            <div>
              <label class="f" for="namaW">Nama</label>
              <input type="text" id="namaW" name="nama" maxlength="<?= WALI_TEKS_MAKS ?>"
                     value="<?= e((string) ($editS['nama'] ?? '')) ?>" placeholder="nama pengurus kelas">
            </div>
            <div>
              <label class="f" for="baganW">Baris bagan (tingkat)</label>
              <select id="baganW" name="bagan">
                <option value="0" <?= (int) ($editS['bagan'] ?? 0) === 0 ? 'selected' : '' ?>>Otomatis dari jabatan</option>
                <?php foreach ([1 => '1 — Wali Kelas', 2 => '2 — Ketua Kelas', 3 => '3 — Wakil Ketua Kelas',
                                4 => '4 — Sekretaris', 5 => '5 — Bendahara', 6 => '6 — Seksi-seksi',
                                7 => '7 — Anggota'] as $angka => $ket): ?>
                  <option value="<?= $angka ?>" <?= (int) ($editS['bagan'] ?? 0) === $angka ? 'selected' : '' ?>><?= e($ket) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="flex">
            <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $editS ? 'Simpan Perubahan' : 'Tambah Baris' ?></button>
          </div>
        </form>

        <?php // Form terpisah: tombol "Susun Jabatan Baku" tidak boleh terkena isian `required`. ?>
        <?php if (!$editS): ?>
          <form method="post" class="flex mt16">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="struktur_baku">
            <input type="hidden" name="kelas" value="<?= $kelasId ?>">
            <button class="btn btn-ghost" type="submit"><?= ikon('star', 16) ?> Susun Jabatan Baku (<?= count(wali_jabatan()) ?> Jabatan)</button>
            <span class="hint" style="align-self:center">Wali Kelas s.d. Anggota sekaligus — tinggal
              diisi namanya. Jabatan yang sudah ada tidak digandakan.</span>
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (!$struktur): ?>
      <div class="empty"><?= ikon('users', 34) ?>
        <h3>Belum ada struktur organisasi kelas</h3>
        <p><?= $bolehUbah
          ? 'Tambahkan lewat formulir di atas, tekan <b>Susun Jabatan Baku</b>, atau impor dari Excel/CSV.'
          : 'Kelas ini belum menyusun struktur organisasinya.' ?></p>
      </div>
    <?php else: ?>
      <div class="section-title mb8"><?= ikon('users', 15) ?> Struktur Organisasi Kelas (<?= count($struktur) ?> baris)</div>
      <?php wali_tabel_struktur($struktur, $bolehUbah, $kelasId); ?>
    <?php endif; ?>
  <?php endif; ?>

<?php // ============================================================ 2. PROGRAM KERJA ?>
<?php elseif ($tab === 'program'): ?>
  <div class="chip-list warna mb16">
    <a class="chip" href="cetak_wali_program.php?kelas=<?= $kelasId ?>" target="_blank"><?= ikon('print', 14) ?> Cetak Program Kerja</a>
    <?php if ($bolehUbah): ?>
      <a class="chip" href="wali_import.php?kelas=<?= $kelasId ?>&bagian=program"><?= ikon('upload', 14) ?> Impor Excel/CSV</a>
      <a class="chip" href="wali_export.php?kelas=<?= $kelasId ?>&bagian=program"><?= ikon('doc', 14) ?> Ekspor</a>
    <?php endif; ?>
  </div>

  <div class="grid g4" style="gap:16px">
    <div class="card stat card-tight" style="margin:0">
      <div class="stat-ic g"><?= ikon('calendar', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasP['program'] ?></div><div class="stat-lbl">Program</div></div>
    </div>
    <div class="card stat card-tight" style="margin:0">
      <div class="stat-ic"><?= ikon('check', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasP['ya'] ?></div><div class="stat-lbl">Terlaksana</div></div>
    </div>
    <div class="card stat card-tight" style="margin:0">
      <div class="stat-ic o"><?= ikon('alert', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasP['tidak'] ?></div><div class="stat-lbl">Belum terlaksana</div></div>
    </div>
    <div class="card stat card-tight" style="margin:0">
      <div class="stat-ic p"><?= ikon('clipboard', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkasP['belum'] ?></div><div class="stat-lbl">Belum ditentukan</div></div>
    </div>
  </div>

  <?php if ($bolehUbah): ?>
    <div class="card" id="formProgramWali" style="margin-top:18px;margin-bottom:18px">
      <div class="card-head">
        <div>
          <h2><?= ikon($editW ? 'edit' : 'plus', 16) ?> <?= $editW ? 'Ubah Program Kerja' : 'Tambah Program Kerja' ?></h2>
          <p>Kolomnya sama dengan Program Kerja Pembina OSIM: Nama Program, Waktu Pelaksanaan,
            Target, Tahun Ajaran, Terlaksana, dan Keterangan.</p>
        </div>
        <?php if ($editW): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(wali_url($kelasId, 'program')) ?>"><?= ikon('prev', 14) ?> Batal</a>
        <?php endif; ?>
      </div>
      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="program_simpan">
        <input type="hidden" name="kelas" value="<?= $kelasId ?>">
        <input type="hidden" name="id" value="<?= (int) ($editW['id'] ?? 0) ?>">
        <div class="grid g2" style="gap:16px">
          <div>
            <label class="f" for="namaProgramW">Nama Program *</label>
            <input type="text" id="namaProgramW" name="nama" maxlength="<?= WALI_TEKS_MAKS ?>" required
                   value="<?= e((string) ($editW['nama'] ?? '')) ?>"
                   placeholder="mis. Pertemuan Wali Murid Semester Ganjil">
          </div>
          <div>
            <label class="f" for="waktuW">Waktu Pelaksanaan</label>
            <input type="date" id="waktuW" name="waktu" value="<?= e((string) ($editW['waktu'] ?? '')) ?>">
            <div class="hint">Tanggal lengkap (hari/bulan/tahun).</div>
          </div>
        </div>
        <div class="grid g3" style="gap:16px">
          <div>
            <label class="f" for="targetW">Target</label>
            <input type="text" id="targetW" name="target" maxlength="<?= WALI_URAIAN_MAKS ?>"
                   value="<?= e((string) ($editW['target'] ?? '')) ?>" placeholder="mis. 32 siswa + orang tua">
          </div>
          <div>
            <label class="f" for="taW">Tahun Ajaran</label>
            <input type="text" id="taW" name="ta" maxlength="40" list="saranTaW"
                   value="<?= e((string) ($editW['ta'] ?? '')) ?>" placeholder="mis. 2026/2027">
            <datalist id="saranTaW">
              <?php foreach (ta_list(false) as $t): ?><option value="<?= e((string) $t['nama']) ?>"></option><?php endforeach; ?>
            </datalist>
          </div>
          <div>
            <label class="f" for="terlaksanaW">Terlaksana</label>
            <select id="terlaksanaW" name="terlaksana">
              <option value="">— belum ditentukan —</option>
              <?php foreach (wali_terlaksana() as $o): ?>
                <option value="<?= e($o) ?>" <?= (string) ($editW['terlaksana'] ?? '') === $o ? 'selected' : '' ?>><?= e($o) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div>
          <label class="f" for="ketW">Keterangan</label>
          <input type="text" id="ketW" name="keterangan" maxlength="<?= WALI_URAIAN_MAKS ?>"
                 value="<?= e((string) ($editW['keterangan'] ?? '')) ?>" placeholder="mis. terlaksana sesuai jadwal">
        </div>
        <div class="flex">
          <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $editW ? 'Simpan Perubahan' : 'Tambah Program' ?></button>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <?php if (!$program): ?>
    <div class="empty mt16"><?= ikon('calendar', 34) ?>
      <h3>Belum ada program kerja</h3>
      <p><?= $bolehUbah
        ? 'Tambahkan program kerja kelas lewat formulir di atas, atau impor dari Excel/CSV.'
        : 'Kelas ini belum menginput program kerja wali kelas.' ?></p>
    </div>
  <?php else: ?>
    <div class="section-title mb8"><?= ikon('calendar', 15) ?> Program Kerja Wali Kelas (<?= count($program) ?> program)</div>
    <?php wali_tabel_program($program, $bolehUbah, $kelasId); ?>
  <?php endif; ?>

<?php // ============================================================ 3. ABSENSI HARIAN ?>
<?php elseif ($tab === 'absensi'): ?>
  <div class="chip-list warna mb16">
    <a class="chip" href="cetak_wali_absensi.php?kelas=<?= $kelasId ?>" target="_blank"><?= ikon('print', 14) ?> Cetak Rekap Absensi</a>
  </div>

  <div class="alert alert-ok" style="background:rgba(201,162,39,.07);border-color:rgba(201,162,39,.45)">
    <?= ikon('clipboard', 16) ?>
    <span>Ini <b>absensi harian kelas</b> (satu tanggal, tanpa mata pelajaran) — berbeda dengan menu
      <b>Daftar Hadir Siswa</b> yang per mata pelajaran &amp; pertemuan. Khusus kelas perwalian Anda.</span>
  </div>

  <?php if ($bolehUbah): ?>
    <div class="card" id="formAbsensiWali" style="margin-bottom:18px">
      <div class="card-head">
        <div>
          <h2><?= ikon('clipboard', 16) ?> Input Absensi Harian</h2>
          <p>Pilih tanggal, lalu tandai kehadiran setiap siswa. Tersimpan sebagai satu catatan
            absensi kelas untuk tanggal itu<?= $sesiAbsen ? ' (tanggal ini sudah pernah dicatat — menyimpan akan memperbaruinya)' : '' ?>.</p>
        </div>
        <div class="flex">
          <a class="btn btn-ghost btn-sm" href="<?= e(wali_url($kelasId, 'absensi', ['tanggal' => date('Y-m-d')])) ?>"><?= ikon('calendar', 14) ?> Hari ini</a>
        </div>
      </div>

      <form method="get" class="filterbar mb16">
        <input type="hidden" name="kode" value="<?= e(WALI_KODE_MENU) ?>">
        <input type="hidden" name="tab" value="absensi">
        <input type="hidden" name="kelas" value="<?= $kelasId ?>">
        <div>
          <label class="f" for="tanggalAbsen">Tanggal absensi</label>
          <input type="date" id="tanggalAbsen" name="tanggal" value="<?= e($tanggalAbsen) ?>">
        </div>
        <button class="btn btn-ghost" type="submit"><?= ikon('search', 15) ?> Tampilkan</button>
        <?php if ($sesiAbsen): ?>
          <span class="chip" style="align-self:flex-end">Sudah tercatat: <?= e((int) ($sesiAbsen['jml_h'] ?? 0)) ?> H
            · <?= e((int) ($sesiAbsen['jml_s'] ?? 0)) ?> S · <?= e((int) ($sesiAbsen['jml_i'] ?? 0)) ?> I
            · <?= e((int) ($sesiAbsen['jml_a'] ?? 0)) ?> A</span>
        <?php endif; ?>
      </form>

      <?php if (!$siswa): ?>
        <div class="empty"><?= ikon('student', 30) ?><h3>Belum ada siswa di kelas ini</h3>
          <p>Tambahkan data siswa dulu lewat menu <b>Data Siswa</b>.</p></div>
      <?php else: ?>
        <form method="post" id="formAbsensiWaliKirim">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="absensi_simpan">
          <input type="hidden" name="kelas" value="<?= $kelasId ?>">
          <input type="hidden" name="tanggal" value="<?= e($tanggalAbsen) ?>">
          <div class="flex mb16" style="flex-wrap:wrap">
            <button class="btn btn-ghost" type="button" id="btnSemuaHadirWali"><?= ikon('check', 15) ?> Tandai Semua Hadir</button>
            <?php if ($sesiAbsen): ?>
              <span class="hint" style="align-self:center">Catatan dibuat
                <?= e(waktu_id_polos((string) $sesiAbsen['dibuat'])) ?><?= trim((string) ($sesiAbsen['guru_nama'] ?? '')) !== '' ? ' oleh ' . e((string) $sesiAbsen['guru_nama']) : '' ?></span>
            <?php endif; ?>
          </div>
          <div class="table-wrap">
            <table class="tbl" id="tblHadirWali">
              <thead><tr><th class="ctr" style="width:44px">No</th><th>Nama Siswa</th>
                <th class="ctr" style="width:220px">Kehadiran</th></tr></thead>
              <tbody>
              <?php foreach ($siswa as $i => $s):
                  $st = (string) ($detailAbsen[(int) $s['id']]['status'] ?? 'H');
                  if (!array_key_exists($st, status_hadir())) { $st = 'H'; }
              ?>
                <tr data-siswa="<?= (int) $s['id'] ?>">
                  <td class="ctr"><?= $i + 1 ?></td>
                  <td><b><?= e((string) $s['nama']) ?></b>
                    <?php if (trim((string) ($s['nis'] ?? '')) !== ''): ?><span class="tbl-sub">NIS <?= e((string) $s['nis']) ?></span><?php endif; ?></td>
                  <td class="ctr">
                    <span class="st">
                      <?php foreach (status_hadir() as $kode => $label): ?>
                        <label class="sel-<?= e($kode) ?><?= $st === $kode ? ' sel' : '' ?>" title="<?= e($label) ?>">
                          <input type="radio" name="st[<?= (int) $s['id'] ?>]" value="<?= e($kode) ?>" data-st="1" <?= $st === $kode ? 'checked' : '' ?>>
                          <?= e($kode) ?>
                        </label>
                      <?php endforeach; ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="legend mt16">
            <span><i style="background:#10b981"></i>H = Hadir</span>
            <span><i style="background:#f59e0b"></i>S = Sakit</span>
            <span><i style="background:#0ea5e9"></i>I = Izin</span>
            <span><i style="background:#dc2626"></i>A = Alpa</span>
          </div>
          <div>
            <label class="f" for="catatanAbsen">Catatan (opsional)</label>
            <input type="text" id="catatanAbsen" name="catatan" maxlength="<?= WALI_URAIAN_MAKS ?>"
                   value="<?= e((string) ($sesiAbsen['catatan'] ?? '')) ?>"
                   placeholder="mis. 2 siswa izin karena lomba, 1 sakit">
          </div>
          <div class="flex">
            <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Absensi <?= e(tanggal_id($tanggalAbsen)) ?></button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="divider"></div>
  <div class="section-title mb8"><?= ikon('calendar', 15) ?> Catatan Absensi Tercatat (<?= count($daftarAbsen) ?> hari)</div>
  <?php if (!$daftarAbsen): ?>
    <div class="empty"><?= ikon('clipboard', 34) ?><h3>Belum ada absensi tercatat</h3>
      <p><?= $bolehUbah ? 'Isi absensi harian lewat formulir di atas.' : 'Kelas ini belum mengisi absensi harian.' ?></p></div>
  <?php else: ?>
    <div class="table-wrap" id="tabelAbsensiWali">
      <table class="tbl tbl-wali">
        <thead><tr><th class="ctr k-no">No</th><th>Tanggal</th><th class="ctr">H</th><th class="ctr">S</th>
          <th class="ctr">I</th><th class="ctr">A</th><th class="ctr">Kehadiran</th><th>Catatan</th>
          <?php if ($bolehUbah): ?><th class="ctr k-aksi">Tindakan</th><?php endif; ?></tr></thead>
        <tbody>
        <?php $n = 0; foreach ($daftarAbsen as $a): $n++;
            $tot = (int) $a['jml_h'] + (int) $a['jml_s'] + (int) $a['jml_i'] + (int) $a['jml_a'];
            $persen = $tot > 0 ? round((int) $a['jml_h'] * 100 / $tot, 1) : null;
        ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e(tanggal_id((string) $a['tanggal'])) ?></b>
              <span class="tbl-sub"><?= e(hari_id((string) $a['tanggal'])) ?></span></td>
            <td class="ctr"><?= (int) $a['jml_h'] ?></td>
            <td class="ctr"><?= (int) $a['jml_s'] ?></td>
            <td class="ctr"><?= (int) $a['jml_i'] ?></td>
            <td class="ctr"><?= (int) $a['jml_a'] ?></td>
            <td class="ctr"><?= $persen === null ? '-' : e(r1($persen)) . '%' ?></td>
            <td class="sm"><?= trim((string) ($a['catatan'] ?? '')) !== '' ? e((string) $a['catatan']) : '-' ?></td>
            <?php if ($bolehUbah): ?>
              <td>
                <div class="row-actions">
                  <a class="btn btn-ghost btn-sm" href="<?= e(wali_url($kelasId, 'absensi', ['tanggal' => (string) $a['tanggal']])) ?>#formAbsensiWali"><?= ikon('edit', 14) ?> Ubah</a>
                  <form method="post" data-confirm="Hapus catatan absensi <?= e(tanggal_id((string) $a['tanggal'])) ?>?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="absensi_hapus">
                    <input type="hidden" name="kelas" value="<?= $kelasId ?>">
                    <input type="hidden" name="tanggal" value="<?= e((string) $a['tanggal']) ?>">
                    <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Hapus</button>
                  </form>
                </div>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><th colspan="2" class="ctr">Jumlah</th>
            <th class="ctr"><?= (int) array_sum(array_column($daftarAbsen, 'jml_h')) ?></th>
            <th class="ctr"><?= (int) array_sum(array_column($daftarAbsen, 'jml_s')) ?></th>
            <th class="ctr"><?= (int) array_sum(array_column($daftarAbsen, 'jml_i')) ?></th>
            <th class="ctr"><?= (int) array_sum(array_column($daftarAbsen, 'jml_a')) ?></th>
            <th colspan="<?= $bolehUbah ? 3 : 2 ?>" class="ctr"><?= count($daftarAbsen) ?> hari tercatat</th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="section-title mb8 mt16"><?= ikon('chart', 15) ?> Rekap Kehadiran per Siswa</div>
    <div class="table-wrap">
      <table class="tbl tbl-wali">
        <thead><tr><th class="ctr k-no">No</th><th>Nama Siswa</th><th class="ctr">Hadir</th>
          <th class="ctr">Sakit</th><th class="ctr">Izin</th><th class="ctr">Alpa</th>
          <th class="ctr">% Kehadiran</th></tr></thead>
        <tbody>
        <?php $n = 0; foreach ($rekapAbsen['siswa'] as $r): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e((string) $r['nama']) ?></b>
              <?php if ($r['nis'] !== ''): ?><span class="tbl-sub">NIS <?= e((string) $r['nis']) ?></span><?php endif; ?></td>
            <td class="ctr"><?= (int) $r['H'] ?></td>
            <td class="ctr"><?= (int) $r['S'] ?></td>
            <td class="ctr"><?= (int) $r['I'] ?></td>
            <td class="ctr"><?= (int) $r['A'] ?></td>
            <td class="ctr"><?= $r['persen'] === null ? '-' : e(r1($r['persen'])) . '%' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

<?php // ============================================================ 4. JADWAL PIKET ?>
<?php // WAJIB `elseif` (BUKAN `else`): tab "Rekap Nilai" (v1.69.0) adalah blok MANDIRI di bawah dan
      // tidak boleh ikut merender blok piket. Dengan `else`, tab apa pun selain struktur/program/
      // absensi — termasuk `tab=nilai` — akan menampilkan Jadwal Piket (pernah terjadi & dilaporkan
      // pemilik: "yang diklik rekap nilai malah muncul jadwal piket").
?>
<?php elseif ($tab === 'piket'): ?>
  <div class="chip-list warna mb16">
    <a class="chip" href="cetak_wali_piket.php?kelas=<?= $kelasId ?>" target="_blank"><?= ikon('print', 14) ?> Cetak Jadwal Piket</a>
  </div>

  <?php if (!$siswa): ?>
    <div class="empty"><?= ikon('student', 34) ?><h3>Belum ada siswa di kelas ini</h3>
      <p>Tambahkan data siswa dulu lewat menu <b>Data Siswa</b>.</p></div>
  <?php elseif ($bolehUbah): ?>
    <div class="alert alert-ok" style="background:rgba(201,162,39,.07);border-color:rgba(201,162,39,.45)">
      <?= ikon('check', 16) ?>
      <span>Centang siswa yang bertugas piket pada tiap hari (nama diambil dari <b>Data Siswa</b> kelas
        ini), lalu tekan <b>Simpan Jadwal Piket</b>.</span>
    </div>
    <form method="post" id="formPiket">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="piket_simpan">
      <input type="hidden" name="kelas" value="<?= $kelasId ?>">
      <div class="grid g2" style="gap:16px">
        <?php foreach (wali_hari() as $h): ?>
          <div class="card" style="margin:0">
            <div class="card-head">
              <div><h2 style="font-size:1rem"><?= e($h) ?></h2>
                <p class="xs mut" style="margin:5px 0 0"><?= count($piket[$h]) ?> siswa piket</p></div>
            </div>
            <div class="table-wrap">
              <table class="tbl piket-pilih">
                <thead><tr><th class="ctr" style="width:52px">Piket</th><th>Nama Siswa</th></tr></thead>
                <tbody>
                <?php foreach ($siswa as $s): ?>
                  <tr>
                    <td class="ctr">
                      <input type="checkbox" name="piket[<?= e($h) ?>][]" value="<?= (int) $s['id'] ?>"
                             style="width:16px;height:16px;accent-color:var(--accent)"
                             <?= in_array((int) $s['id'], array_column($piket[$h], 'siswa_id'), true) ? 'checked' : '' ?>>
                    </td>
                    <td><?= e((string) $s['nama']) ?><?php if (trim((string) ($s['nis'] ?? '')) !== ''): ?>
                      <span class="tbl-sub">NIS <?= e((string) $s['nis']) ?></span><?php endif; ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="flex mt16">
        <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Jadwal Piket</button>
        <span class="hint" style="align-self:center">Menghapus centang berarti siswa itu tidak lagi
          bertugas piket pada hari tersebut.</span>
      </div>
    </form>
  <?php endif; ?>

  <div class="divider"></div>
  <div class="section-title mb8"><?= ikon('check', 15) ?> Jadwal Piket Kelas <?= e((string) $kelas['nama']) ?>
    (<?= (int) wali_piket_jumlah($piket) ?> penugasan)</div>
  <div class="table-wrap">
    <table class="tbl tbl-wali" id="tabelPiket">
      <thead><tr><th class="ctr k-no">No</th><th class="k-hari">Hari</th><th>Nama-nama Siswa Piket</th>
        <th class="ctr k-jml">Jumlah</th></tr></thead>
      <tbody>
      <?php $n = 0; foreach (wali_hari() as $h): $n++; ?>
        <tr>
          <td class="ctr"><?= $n ?></td>
          <td><b><?= e($h) ?></b></td>
          <td>
            <?php if (!$piket[$h]): ?>
              <span class="mut xs">belum ada siswa piket pada hari ini</span>
            <?php else: ?>
              <?php foreach ($piket[$h] as $i => $p): ?><?= $i > 0 ? ', ' : '' ?><?= e((string) $p['nama']) ?><?php endforeach; ?>
            <?php endif; ?>
          </td>
          <td class="ctr"><?= count($piket[$h]) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><th colspan="3" class="ctr">Jumlah</th>
          <th class="ctr"><?= (int) wali_piket_jumlah($piket) ?></th></tr>
      </tfoot>
    </table>
  </div>

<?php endif; ?>

<?php // ============================================================ 5. REKAP NILAI & LEGER ?>
<?php // Permintaan pemilik: *"Tambahkan fitur Rekap Nilai semua mata pelajaran dan Cetak Leger
      // (tambahkan predikat dan peringkat) pada tugas tambahan wali kelas. Jadi semua nilai mata
      // pelajaran yang diisi oleh yang mengajar di kelas itu terkirim ke Wali Kelas menjadi rekapan
      // nilai."* Nilai diisi guru pengampu di menu Penilaian Siswa dan langsung terbaca di sini —
      // tidak ada langkah "kirim" yang bisa terlupa. ?>
<?php if ($tab === 'nilai'): ?>
  <?php
  // Periode yang dipakai ($periode berasal dari wali_halaman(); tahun ajaran & semester aktif bawaan).
  $taPilih = $periode['ta'] ?? null;
  $semPilih = $periode['sem'] ?? null;
  $mapelRekap = $rekapNilai['mapel'];
  $jumlahSiswa = count($rekapNilai['siswa']);
  $qCetak = ['kelas' => $kelasId]
      + ($taPilih ? ['ta' => (int) $taPilih['id']] : [])
      + ($semPilih ? ['sem' => (int) $semPilih['id']] : []);
  ?>
  <div class="chip-list warna mb16">
    <a class="chip" href="cetak_wali_leger.php?<?= e(http_build_query($qCetak)) ?>" target="_blank"><?= ikon('print', 14) ?> Cetak Leger</a>
    <?php if (isset($rekapNilai['mapel'][0])): ?>
      <a class="chip" href="nilai.php?<?= e(http_build_query(array_filter([
          'kelas' => $kelasId,
          'mapel' => (int) $rekapNilai['mapel'][0]['id'],
          'ta' => $taPilih ? (int) $taPilih['id'] : 0,
          'sem' => $semPilih ? (int) $semPilih['id'] : 0,
      ]))) ?>"><?= ikon('chart', 14) ?> Buka Penilaian Siswa</a>
    <?php endif; ?>
  </div>

  <div class="alert alert-ok" style="background:rgba(201,162,41,.08);border-color:rgba(201,162,41,.45)">
    <?= ikon('chart', 16) ?>
    <span><b>Rekap nilai seluruh mata pelajaran kelas <?= e((string) $kelas['nama']) ?></b> — semua nilai
      yang diisi <b>guru pengampu</b> di menu <b>Penilaian Siswa</b> otomatis terbaca di sini (tidak ada
      langkah kirim tambahan). Nilai akhir tiap mapel dihitung dengan rumus yang sama seperti halaman
      Penilaian Siswa, sehingga angkanya sama dengan yang dilihat guru mapel.</span>
  </div>

  <?php // Pemilih PERIODE: tahun ajaran & semester (bawaan: periode aktif sekolah). ?>
  <form method="get" class="filterbar mb16">
    <input type="hidden" name="kode" value="<?= e(WALI_KODE_MENU) ?>">
    <input type="hidden" name="kelas" value="<?= $kelasId ?>">
    <input type="hidden" name="tab" value="nilai">
    <div>
      <label class="f" for="ta">Tahun Ajaran</label>
      <select id="ta" name="ta" data-auto>
        <?php foreach (ta_list() as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= (int) $t['id'] === (int) ($taPilih['id'] ?? 0) ? 'selected' : '' ?>><?= e((string) $t['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="f" for="sem">Semester</label>
      <select id="sem" name="sem" data-auto>
        <?php foreach (semester_list((int) ($taPilih['id'] ?? 0)) as $sm): ?>
          <option value="<?= (int) $sm['id'] ?>" <?= (int) $sm['id'] === (int) ($semPilih['id'] ?? 0) ? 'selected' : '' ?>><?= e((string) $sm['nama']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex" style="align-self:flex-end">
      <span class="hint"><?= e(wali_nilai_ringkas($rekapNilai)) ?></span>
    </div>
  </form>

  <div class="grid g4" style="gap:16px">
    <div class="card stat card-tight" style="margin:0">
      <div class="stat-ic g"><?= ikon('users', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= $jumlahSiswa ?></div><div class="stat-lbl">Siswa di kelas</div></div>
    </div>
    <div class="card stat card-tight" style="margin:0">
      <div class="stat-ic"><?= ikon('folder', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= count($mapelRekap) ?></div><div class="stat-lbl">Mata pelajaran</div></div>
    </div>
    <div class="card stat card-tight" style="margin:0">
      <div class="stat-ic o"><?= ikon('chart', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= $rekapNilai['rata_kelas'] === null ? '-' : e(r1($rekapNilai['rata_kelas'])) ?></div><div class="stat-lbl">Rata-rata kelas</div></div>
    </div>
    <div class="card stat card-tight" style="margin:0">
      <div class="stat-ic p"><?= ikon('check', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $rekapNilai['siswa_ternilai'] ?>/<?= $jumlahSiswa ?></div><div class="stat-lbl">Siswa sudah ternilai</div></div>
    </div>
  </div>

  <?php if (!$mapelRekap): ?>
    <div class="empty mt16" id="legerKosong">
      <?= ikon('chart', 34) ?>
      <h3>Belum ada mata pelajaran untuk kelas ini</h3>
      <p>Rekap terbentuk otomatis dari dua hal: <b>mata pelajaran yang diampu guru pengajar kelas ini</b>
        (Data Guru → Kelas yang Diajar + Mata Pelajaran) dan <b>nilai yang sudah diisi guru</b> pada
        menu Penilaian Siswa untuk periode yang dipilih.</p>
      <p class="mut">Pastikan guru pengampu kelas ini sudah ditugaskan pada <b>Data Guru</b> dan nilainya
        diisi pada <b>Penilaian Siswa</b>.</p>
    </div>
  <?php elseif (!$rekapNilai['siswa']): ?>
    <div class="empty mt16">
      <?= ikon('student', 34) ?>
      <h3>Belum ada siswa di kelas ini</h3>
      <p>Tambahkan siswa lewat menu <b>Data Siswa</b>, lalu rekap nilai akan langsung terbentuk.</p>
    </div>
  <?php else: ?>
    <div class="section-title mb8 mt16"><?= ikon('chart', 15) ?> Leger Nilai
      (<?= e($taPilih ? (string) $taPilih['nama'] : '-') ?> &middot; <?= e($semPilih ? (string) $semPilih['nama'] : '-') ?>)</div>
    <div class="table-wrap" id="tblLegerWali">
      <table class="tbl tbl-wali tbl-leger">
        <thead>
          <tr>
            <th class="ctr k-no">No</th>
            <th class="k-siswa">Nama Siswa</th>
            <?php foreach ($mapelRekap as $m): ?>
              <th class="ctr k-nilai" title="<?= e((string) $m['nama']) ?>"><?= e(wali_potong_judul((string) $m['nama'], 12)) ?></th>
            <?php endforeach; ?>
            <th class="ctr k-jml">Jumlah</th>
            <th class="ctr k-jml">Rata-rata</th>
            <th class="ctr k-pred">Predikat</th>
            <th class="ctr k-peringkat">Peringkat</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($rekapNilai['siswa'] as $sw): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e((string) $sw['nama']) ?></b>
              <?php if ((string) $sw['nis'] !== ''): ?><span class="tbl-sub">NIS <?= e((string) $sw['nis']) ?></span><?php endif; ?></td>
            <?php foreach ($mapelRekap as $m): $v = $sw['nilai'][(int) $m['id']] ?? null; ?>
              <td class="ctr"><?= $v === null ? '<span class="mut xs">-</span>' : e(r1($v)) ?></td>
            <?php endforeach; ?>
            <td class="ctr"><b><?= $sw['jumlah'] === null ? '-' : e(r1($sw['jumlah'])) ?></b></td>
            <td class="ctr"><b><?= $sw['rata'] === null ? '-' : e(r1($sw['rata'])) ?></b></td>
            <td class="ctr"><?php if ($sw['predikat'] === '-'): ?><span class="mut xs">-</span><?php else: ?><span class="pill pill-<?= e($sw['predikat']) ?>"><?= e($sw['predikat']) ?></span><?php endif; ?></td>
            <td class="ctr"><?= $sw['peringkat'] === null ? '<span class="mut xs">-</span>' : '<b>' . (int) $sw['peringkat'] . '</b>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="2" class="ctr">Rata-rata kelas</th>
            <?php foreach ($mapelRekap as $m): ?>
              <th class="ctr"><?= ($rekapNilai['rata_mapel'][(int) $m['id']] ?? null) === null ? '-' : e(r1((float) $rekapNilai['rata_mapel'][(int) $m['id']])) ?></th>
            <?php endforeach; ?>
            <th class="ctr">-</th>
            <th class="ctr"><?= $rekapNilai['rata_kelas'] === null ? '-' : e(r1($rekapNilai['rata_kelas'])) ?></th>
            <th class="ctr">-</th>
            <th class="ctr">-</th>
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="legend mt16">
      <span>Keterangan predikat: <?= e(predikat_keterangan()) ?></span>
    </div>

    <div class="divider"></div>
    <div class="section-title mb8"><?= ikon('users', 15) ?> Rekap per Mata Pelajaran
      (guru yang mengisi nilai di kelas ini)</div>
    <div class="table-wrap" id="tblRekapMapelWali">
      <table class="tbl tbl-wali">
        <thead><tr><th class="ctr k-no">No</th><th>Mata Pelajaran</th><th>Guru Pengampu</th>
          <th class="ctr k-jml">Siswa Ternilai</th><th class="ctr k-jml">Rata-rata</th>
          <th class="ctr k-pred">Predikat</th><th class="ctr k-jml">Bobot ASAS</th></tr></thead>
        <tbody>
        <?php $nm = 0; foreach ($mapelRekap as $m): $nm++; ?>
          <tr>
            <td class="ctr"><?= $nm ?></td>
            <td><b><?= e((string) $m['nama']) ?></b>
              <?php if ((int) $m['aktif'] !== 1): ?><span class="tbl-sub">mata pelajaran nonaktif</span><?php endif; ?></td>
            <td class="sm"><?= (string) $m['guru_nama'] !== '' ? e((string) $m['guru_nama']) : '<span class="mut xs">belum ditetapkan</span>' ?></td>
            <td class="ctr"><?= (int) $m['terisi'] ?>/<?= $jumlahSiswa ?></td>
            <td class="ctr"><?= $m['rata'] === null ? '-' : e(r1((float) $m['rata'])) ?></td>
            <td class="ctr"><?= $m['predikat'] === '-' ? '<span class="mut xs">-</span>' : '<span class="pill pill-' . e((string) $m['predikat']) . '">' . e((string) $m['predikat']) . '</span>' ?></td>
            <td class="ctr sm"><?= e(r0((float) $m['konteks']['bobot_asas'])) ?>%</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><th colspan="3" class="ctr">Rata-rata kelas</th>
            <th class="ctr"><?= (int) $rekapNilai['siswa_ternilai'] ?>/<?= $jumlahSiswa ?></th>
            <th class="ctr"><?= $rekapNilai['rata_kelas'] === null ? '-' : e(r1($rekapNilai['rata_kelas'])) ?></th>
            <th class="ctr"><?= $rekapNilai['rata_kelas'] === null ? '-' : '<span class="pill pill-' . e(predikat_dari($rekapNilai['rata_kelas'])) . '">' . e(predikat_dari($rekapNilai['rata_kelas'])) . '</span>' ?></th>
            <th class="ctr">-</th></tr>
        </tfoot>
      </table>
    </div>
    <div class="hint mt8">Bobot ASAS tiap mapel mengikuti pengaturan penilaian guru pengampunya; bila guru
      belum mengaturnya, dipakai pengaturan sekolah.</div>
  <?php endif; ?>
<?php endif; ?>
