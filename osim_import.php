<?php
/**
 * IMPOR DATA OSIM/OSIS (Excel/CSV dibaca di peramban dengan SheetJS, dikirim sebagai JSON) — satu
 * halaman untuk DUA laporan (permintaan pemilik: "Tambahkan fitur impor dan ekspor dan juga cetak"):
 *
 *   osim_import.php?bagian=organisasi  → Susunan Organisasi (kolom: Jabatan, Nama)
 *   osim_import.php?bagian=program     → Program Kerja (kolom: Nama Program, Waktu Pelaksanaan,
 *                                        Target, Tahun Ajaran, Terlaksana, Keterangan)
 *
 * Aturan penting:
 *  - Satu baris Susunan Organisasi = satu jabatan ATAU satu seksi ATAU satu anggota (permintaan
 *    pemilik), jadi berkas yang memuat banyak baris "Seksi …" / "Anggota …" langsung tersusun.
 *  - Waktu Pelaksanaan = TANGGAL; dibaca lenient lewat `tgl_impor()` (menerima 2011-04-12,
 *    12/04/2011, 12 April 2011, angka serial Excel).
 *  - Sama seperti modul lain: baris dengan kunci sama diperbarui bila dicentang, kalau tidak dilewati.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/osim.php';

// `bagian` dibaca dari POST MAUPUN GET — formulir impor mengirimnya pada POST (tanpa ini, impor
// Program Kerja selalu jatuh ke Susunan Organisasi karena GET-nya tidak memuat `bagian`).
$bagian = (string) ($_POST['bagian'] ?? $_GET['bagian'] ?? 'organisasi');
if (!in_array($bagian, ['organisasi', 'program'], true)) {
    $bagian = 'organisasi';
}

// Kode menu dari URL (?kode=pramuka memakai modul yang sama dengan ?kode=osim).
$kode = osim_kode_normal((string) ($_POST['kode'] ?? $_GET['kode'] ?? OSIM_KODE_MENU));
$konfig = osim_menu_konfig($kode);
$tugas = tugas_menu_by_kode($kode);
if (!$tugas) {
    require_login();
    flash('Menu ' . $konfig['menu_bawaan'] . ' belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu_ubah($tugas, 'susunan organisasi & program kerja');   // hanya guru yang ditugaskan (+ Super Admin) yang boleh mengimpor
require_sekolah();

$ringkasan = null;
$galat = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $data = json_decode((string) ($_POST['payload'] ?? ''), true);
    $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
    $perbarui = !empty($data['perbarui']);
    $taBawaan = trim((string) ($data['ta_bawaan'] ?? ''));
    if ($taBawaan === '') {
        $taBawaan = osim_ta_awal();
    }
    $tambah = 0; $ubah = 0; $lewat = 0;
    $tglGagal = 0; $bentukSalah = 0;

    if (!$rows) {
        $galat[] = 'Tidak ada baris data yang dikirim. Pastikan berkas sudah dipilih dan kolom sudah dipetakan.';
    } else {
        $now = date('Y-m-d H:i:s');
        $tabel = $bagian === 'organisasi' ? 'osim_pengurus' : 'osim_program';
        $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) FROM ' . $tabel . ' WHERE sekolah_id = ? AND kode = ?',
            [sekolah_id(), $kode], 0);
        db()->exec('BEGIN IMMEDIATE');
        try {
            foreach ($rows as $i => $r) {
                $baris = (int) ($r['_baris'] ?? ($i + 1));

                if ($bagian === 'organisasi') {
                    $jabatan = osim_pangkas(trim((string) ($r['jabatan'] ?? '')), OSIM_TEKS_MAKS);
                    if ($jabatan === '') {
                        $lewat++;
                        if (count($galat) < 8) {
                            $galat[] = 'Baris ' . $baris . ': Jabatan / Nama Seksi kosong, dilewati.';
                        }
                        continue;
                    }
                    $nama = osim_pangkas(trim((string) ($r['nama'] ?? '')), OSIM_TEKS_MAKS);
                    // Kunci pembanding = JABATAN + NAMA: jabatan yang sama boleh dipakai berkali-kali
                    // (mis. beberapa "Anggota OSIM/OSIS" sebagai baris tersendiri), jadi memakai
                    // jabatan saja akan menimpa anggota sebelumnya.
                    $ada = one('SELECT * FROM osim_pengurus
                                WHERE lower(jabatan) = lower(?) AND lower(IFNULL(nama, "")) = lower(?)
                                  AND sekolah_id = ? AND kode = ?',
                        [$jabatan, $nama, sekolah_id(), $kode]);
                    if ($ada) {
                        // Nama kosong pada berkas tidak menghapus nama yang sudah ada.
                        if (!$perbarui) {
                            $lewat++;
                            continue;
                        }
                        q('UPDATE osim_pengurus SET nama = CASE WHEN ? <> "" THEN ? ELSE nama END, diubah = ?
                             WHERE id = ? AND sekolah_id = ? AND kode = ?',
                            [$nama, $nama, $now, (int) $ada['id'], sekolah_id(), $kode]);
                        $ubah++;
                    } else {
                        $urutan += 10;
                        q('INSERT INTO osim_pengurus (sekolah_id, kode, jabatan, nama, urutan, dibuat)
                             VALUES (?,?,?,?,?,?)',
                            [sekolah_id(), $kode, $jabatan, $nama, $urutan, $now]);
                        $tambah++;
                    }
                    continue;
                }

                // --- Program Kerja
                $namaProgram = osim_pangkas(trim((string) ($r['nama'] ?? '')), OSIM_TEKS_MAKS);
                if ($namaProgram === '') {
                    $lewat++;
                    if (count($galat) < 8) {
                        $galat[] = 'Baris ' . $baris . ': Nama Program kosong, dilewati.';
                    }
                    continue;
                }
                $waktuMentah = trim((string) ($r['waktu'] ?? ''));
                $waktu = $waktuMentah !== '' ? tgl_impor($waktuMentah) : null;
                if ($waktu === null && $waktuMentah !== '') {
                    $tglGagal++;
                }
                $target = osim_pangkas(trim((string) ($r['target'] ?? '')), OSIM_URAIAN_MAKS);
                $ket = osim_pangkas(trim((string) ($r['keterangan'] ?? '')), OSIM_URAIAN_MAKS);
                $ta = osim_pangkas(trim((string) ($r['ta'] ?? '')), 40);
                if ($ta === '') {
                    $ta = $taBawaan;
                }
                $terMentah = trim((string) ($r['terlaksana'] ?? ''));
                $ter = osim_terlaksana_sah($terMentah);
                if ($terMentah !== '' && $ter === '') {
                    $bentukSalah++;
                }

                $ada = one('SELECT * FROM osim_program WHERE lower(nama) = lower(?) AND sekolah_id = ? AND kode = ?',
                    [$namaProgram, sekolah_id(), $kode]);
                if ($ada) {
                    if (!$perbarui) {
                        $lewat++;
                        continue;
                    }
                    q('UPDATE osim_program SET waktu = ?, target = ?, ta = ?, terlaksana = ?,
                         keterangan = ?, diubah = ? WHERE id = ? AND sekolah_id = ? AND kode = ?',
                        [$waktu, $target, $ta, $ter, $ket, $now, (int) $ada['id'], sekolah_id(), $kode]);
                    $ubah++;
                } else {
                    $urutan += 10;
                    q('INSERT INTO osim_program (sekolah_id, kode, nama, waktu, target, ta, terlaksana,
                         keterangan, urutan, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?)',
                        [sekolah_id(), $kode, $namaProgram, $waktu, $target, $ta, $ter, $ket, $urutan, $now]);
                    $tambah++;
                }
            }
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        $ringkasan = ['tambah' => $tambah, 'ubah' => $ubah, 'lewat' => $lewat, 'total' => count($rows)];
        if ($tglGagal > 0) {
            $galat[] = $tglGagal . ' baris punya Waktu Pelaksanaan yang tidak dapat dikenali — baris itu tetap '
                . 'diimpor tanpa tanggal. Tulislah tanggal dengan format tahun-bulan-hari (mis. 2026-07-14) '
                . 'atau hari/bulan/tahun (mis. 14/07/2026).';
        }
        if ($bentukSalah > 0) {
            $galat[] = $bentukSalah . ' baris memakai Terlaksana di luar pilihan "Ya"/"Tidak" — nilainya '
                . 'dikosongkan (dapat diubah lewat tombol Ubah).';
        }
    }
}

$isOrganisasi = $bagian === 'organisasi';
$judul = $isOrganisasi ? 'Susunan Organisasi' : 'Program Kerja';
$jumlahAda = (int) val($isOrganisasi
    ? 'SELECT COUNT(*) FROM osim_pengurus WHERE sekolah_id = ? AND kode = ?'
    : 'SELECT COUNT(*) FROM osim_program WHERE sekolah_id = ? AND kode = ?', [sekolah_id(), $kode], 0);
$taPilihan = osim_ta_pilihan();
$taAwal = osim_ta_awal();

page_top('Impor ' . $judul . ' ' . $konfig['nama_organisasi'], $konfig['menu_bawaan']);
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('upload', 16) ?> Impor <?= e($judul) ?> (Excel / CSV)</h2>
      <p>Unggah berkas, cocokkan kolomnya, lihat pratinjau, lalu simpan. Berkas tidak disimpan di server.</p>
    </div>
    <div class="flex">
      <a class="btn btn-ghost btn-sm" href="osim_import.php?kode=<?= e($kode) ?>&bagian=<?= $isOrganisasi ? 'program' : 'organisasi' ?>"><?= ikon('switch', 15) ?> Impor <?= $isOrganisasi ? 'Program Kerja' : 'Susunan Organisasi' ?></a>
      <a class="btn btn-ghost" href="tugas.php?kode=<?= e($kode) ?>&bagian=<?= e($bagian) ?>&tab=data"><?= ikon('clipboard', 16) ?> Data (<?= $jumlahAda ?>)</a>
    </div>
  </div>

  <?php if ($ringkasan): ?>
    <div class="alert alert-ok">
      <?= ikon('check', 16) ?>
      <span>Impor selesai dari <?= (int) $ringkasan['total'] ?> baris: <b><?= (int) $ringkasan['tambah'] ?></b> baru,
        <b><?= (int) $ringkasan['ubah'] ?></b> diperbarui, <b><?= (int) $ringkasan['lewat'] ?></b> dilewati.</span>
    </div>
  <?php endif; ?>
  <?php foreach ($galat as $g): ?>
    <div class="alert alert-warn"><?= ikon('alert', 16) ?><span><?= e($g) ?></span></div>
  <?php endforeach; ?>

  <div class="alert alert-ok" style="background:rgba(201,162,39,.08);border-color:rgba(201,162,39,.5)">
    <?= ikon('doc', 16) ?>
    <?php if ($isOrganisasi): ?>
      <span>Kolom: <b>Jabatan</b> (wajib) &amp; <b>Nama</b>. Satu baris = satu jabatan/seksi/anggota, jadi
        baris <i>Seksi Keagamaan</i>, <i><?= e((string) end($konfig['jabatan'])) ?></i>, dan seterusnya ditulis sebagai baris
        tersendiri — boleh sebanyak apa pun.</span>
    <?php else: ?>
      <span>Kolom: <b>Nama Program</b> (wajib), Waktu Pelaksanaan (tanggal), Target, Tahun Ajaran,
        <b>Terlaksana</b> (Ya/Tidak), Keterangan.</span>
    <?php endif; ?>
  </div>

  <div class="grid g3" style="gap:16px">
    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">1</div>
        <div><div class="butir-komponen">Pilih berkas</div><div class="butir-indikator">Format .xlsx, .xls, atau .csv.</div></div>
      </div>
      <div class="mt16"><input type="file" id="fileInput" accept=".xlsx,.xls,.csv,text/csv" style="padding:9px"></div>
      <div class="hint" id="fileInfo">Belum ada berkas dipilih.</div>
      <div class="mt8"><select id="sheetSelect" style="display:none"></select></div>
    </div>

    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">2</div>
        <div><div class="butir-komponen">Cocokkan kolom</div><div class="butir-indikator">Judul kolom ditebak otomatis.</div></div>
      </div>
      <div class="mt16 grid" style="gap:10px">
        <div>
          <label class="f" for="headerRow">Baris judul kolom</label>
          <select id="headerRow"></select>
        </div>
        <div id="mapArea" class="grid g2" style="gap:10px"></div>
      </div>
    </div>

    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">3</div>
        <div><div class="butir-komponen">Pilihan</div><div class="butir-indikator">Baris dengan kunci sama dapat diperbarui.</div></div>
      </div>
      <div class="mt16 grid" style="gap:10px">
        <?php if (!$isOrganisasi): ?>
          <div>
            <label class="f" for="taBawaan">Tahun Ajaran (bila kolomnya kosong)</label>
            <select id="taBawaan">
              <option value="">— biarkan kosong —</option>
              <?php foreach ($taPilihan as $tp): ?>
                <option value="<?= e((string) $tp['nama']) ?>" <?= (string) $tp['nama'] === $taAwal ? 'selected' : '' ?>><?= e((string) $tp['nama']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600">
          <input type="checkbox" id="opsiPerbarui" checked style="width:16px;height:16px;accent-color:var(--accent)">
          <?= $isOrganisasi ? 'Perbarui jabatan yang sudah ada' : 'Perbarui program yang namanya sudah ada' ?>
        </label>
        <button class="btn btn-primary" id="btnImpor" type="button" disabled><?= ikon('upload', 16) ?> Impor Sekarang</button>
        <button class="btn btn-ghost" id="btnContoh" type="button">Unduh contoh format CSV</button>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Pratinjau Data</h2><p>Maksimal 25 baris pertama sebagai pemeriksaan sebelum disimpan.</p></div>
    <span class="chip" id="prevInfo">Belum ada data</span>
  </div>
  <div id="prevArea">
    <div class="empty"><?= ikon('upload', 34) ?><h3>Belum ada berkas</h3><p>Pilih berkas Excel/CSV untuk melihat pratinjau.</p></div>
  </div>
</div>

<form method="post" id="formImpor" style="display:none">
  <?= csrf_field() ?>
  <?php // Kode menu WAJIB ikut dikirim: impor Pramuka tidak boleh masuk ke data OSIM/OSIS. ?>
  <input type="hidden" name="kode" value="<?= e($kode) ?>">
  <input type="hidden" name="payload" id="payloadInput">
</form>

<script src="assets/vendor/xlsx.full.min.js"></script>
<script>
(function () {
  var ORGANISASI = <?= $isOrganisasi ? 'true' : 'false' ?>;
  var FIELDS = ORGANISASI
    ? [
        { k: 'jabatan', l: 'Jabatan / Nama Seksi *', keys: ['jabatan', 'namaseksi', 'seksi', 'posisi'] },
        { k: 'nama',    l: 'Nama',                    keys: ['nama', 'namapengurus', 'pengurus'] }
      ]
    : [
        { k: 'nama',        l: 'Nama Program *',   keys: ['namaprogram', 'program', 'nama', 'kegiatan'] },
        { k: 'waktu',       l: 'Waktu Pelaksanaan', keys: ['waktupelaksanaan', 'waktu', 'pelaksanaan', 'tanggal'] },
        { k: 'target',      l: 'Target',            keys: ['target', 'sasaran'] },
        { k: 'ta',          l: 'Tahun Ajaran',      keys: ['tahunajaran', 'ta', 'tahun'] },
        { k: 'terlaksana',  l: 'Terlaksana',        keys: ['terlaksana', 'status', 'keterlaksanaan'] },
        { k: 'keterangan',  l: 'Keterangan',        keys: ['keterangan', 'ket', 'catatan'] }
      ];
  var DUA_BULAN = { januari: 1, februari: 2, maret: 3, april: 4, mei: 5, juni: 6, juli: 7,
    agustus: 8, september: 9, oktober: 10, november: 11, desember: 12, jan: 1, feb: 2, mar: 3,
    apr: 4, jun: 6, jul: 7, agu: 8, ags: 8, sep: 9, sept: 9, okt: 10, nov: 11, des: 12 };
  var wb = null, rows = [], header = [], map = {};
  var fInput = document.getElementById('fileInput');
  var sSelect = document.getElementById('sheetSelect');
  var hSelect = document.getElementById('headerRow');
  var mapArea = document.getElementById('mapArea');
  var prevArea = document.getElementById('prevArea');
  var prevInfo = document.getElementById('prevInfo');
  var fileInfo = document.getElementById('fileInfo');
  var btnImpor = document.getElementById('btnImpor');

  function norm(s) { return String(s == null ? '' : s).toLowerCase().replace(/[^a-z0-9]+/g, ''); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]; }); }
  function colLetter(i) { var s = ''; i = i + 1; while (i > 0) { var m = (i - 1) % 26; s = String.fromCharCode(65 + m) + s; i = Math.floor((i - m) / 26); } return s; }
  function dua(n) { return (n < 10 ? '0' : '') + n; }

  // Penyamaan TANGGAL untuk pratinjau saja (aturan yang menentukan saat menyimpan ada di server).
  function tglSama(v) {
    var s = String(v == null ? '' : v).trim();
    if (!s) return '';
    var m = /^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/.exec(s);
    if (m) return m[1] + '-' + dua(+m[2]) + '-' + dua(+m[3]);
    m = /^(\d{4})(\d{2})(\d{2})$/.exec(s);
    if (m) return m[1] + '-' + m[2] + '-' + m[3];
    m = /^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/.exec(s);
    if (m) {
      var a = +m[1], b = +m[2], y = +m[3];
      if (y < 100) y += (y < 50 ? 2000 : 1900);
      var h = a, bl = b;
      if (a <= 12 && b > 12) { h = b; bl = a; }
      else if (a > 12 && b <= 12) { h = a; bl = b; }
      return y + '-' + dua(bl) + '-' + dua(h);
    }
    m = /^(\d{1,2})\s+([A-Za-z]+)\s+(\d{2,4})$/.exec(s);
    if (m) {
      var bl2 = DUA_BULAN[m[2].toLowerCase()];
      if (bl2) { var yy = +m[3]; if (yy < 100) yy += 2000; return yy + '-' + dua(bl2) + '-' + dua(+m[1]); }
    }
    if (/^\d{5}$/.test(s)) {
      var n = +s;
      if (n >= 20000 && n <= 80000) return new Date(Date.UTC(1899, 11, 30) + n * 86400000).toISOString().slice(0, 10);
    }
    return '';
  }
  function terSama(v) {
    var s = String(v == null ? '' : v).trim().toLowerCase();
    if (s === 'ya' || s === 'sudah' || s === 'terlaksana' || s === 'yes' || s === 'y') return 'Ya';
    if (s === 'tidak' || s === 'belum' || s === 'no' || s === 't') return 'Tidak';
    return '';
  }

  fInput.addEventListener('change', function (ev) {
    var f = ev.target.files && ev.target.files[0];
    if (!f) return;
    var fr = new FileReader();
    fr.onload = function () {
      try { wb = XLSX.read(new Uint8Array(fr.result), { type: 'array' }); }
      catch (e) { fileInfo.textContent = 'Gagal membaca berkas: ' + e.message; return; }
      fileInfo.innerHTML = '<b>' + esc(f.name) + '</b> &middot; ' + Math.round(f.size / 1024) + ' KB &middot; ' + wb.SheetNames.length + ' sheet';
      sSelect.innerHTML = '';
      wb.SheetNames.forEach(function (n) {
        var o = document.createElement('option'); o.value = n; o.textContent = 'Sheet: ' + n; sSelect.appendChild(o);
      });
      sSelect.style.display = wb.SheetNames.length > 1 ? '' : 'none';
      loadSheet(wb.SheetNames[0]);
    };
    fr.readAsArrayBuffer(f);
  });

  sSelect.addEventListener('change', function () { loadSheet(sSelect.value); });

  function loadSheet(name) {
    rows = XLSX.utils.sheet_to_json(wb.Sheets[name], { header: 1, raw: false, defval: '', blankrows: false });
    if (!rows.length) {
      prevArea.innerHTML = '<div class="empty"><h3>Sheet kosong</h3></div>';
      btnImpor.disabled = true;
      return;
    }
    var guess = 0;
    for (var i = 0; i < Math.min(rows.length, 12); i++) {
      if (rows[i].filter(function (c) { return String(c).trim() !== ''; }).length >= 2) { guess = i; break; }
    }
    hSelect.innerHTML = '';
    for (var j = 0; j < Math.min(rows.length, 15); j++) {
      var o = document.createElement('option'); o.value = j;
      o.textContent = 'Baris ' + (j + 1) + ': ' + (rows[j].slice(0, 6).join(' | ').substring(0, 80) || '(kosong)');
      hSelect.appendChild(o);
    }
    hSelect.value = guess;
    map = {};
    render();
  }

  hSelect.addEventListener('change', function () { map = {}; render(); });

  // Tebakan dua tahap (cocok PERSIS dulu, baru awalan) + satu kolom tidak dipakai dua medan.
  function tebakKolom() {
    var dipakai = {}, hasil = {};
    FIELDS.forEach(function (fd) { hasil[fd.k] = ''; });
    FIELDS.forEach(function (fd) {
      for (var p = 0; p < fd.keys.length; p++) {
        for (var c = 0; c < header.length; c++) {
          if (dipakai[c]) continue;
          if (norm(header[c]) === fd.keys[p]) { hasil[fd.k] = String(c); dipakai[c] = 1; return; }
        }
      }
    });
    FIELDS.forEach(function (fd) {
      if (hasil[fd.k] !== '') return;
      for (var p = 0; p < fd.keys.length; p++) {
        for (var c = 0; c < header.length; c++) {
          if (dipakai[c]) continue;
          var nh = norm(header[c]);
          if (nh && nh.indexOf(fd.keys[p]) === 0) { hasil[fd.k] = String(c); dipakai[c] = 1; return; }
        }
      }
    });
    return hasil;
  }

  function render() {
    header = rows[Number(hSelect.value)] || [];
    var maxCol = 0;
    rows.forEach(function (r) { if (r.length > maxCol) maxCol = r.length; });
    if (!Object.keys(map).length) { map = tebakKolom(); }
    mapArea.innerHTML = '';
    FIELDS.forEach(function (fd) {
      var wrap = document.createElement('div');
      var lab = document.createElement('label'); lab.className = 'f'; lab.textContent = fd.l;
      var sel = document.createElement('select');
      var o0 = document.createElement('option'); o0.value = ''; o0.textContent = '— tidak dipakai —'; sel.appendChild(o0);
      for (var c = 0; c < maxCol; c++) {
        var o = document.createElement('option'); o.value = String(c);
        o.textContent = 'Kolom ' + colLetter(c) + (header[c] ? ': ' + String(header[c]).substring(0, 24) : '');
        sel.appendChild(o);
      }
      sel.value = map[fd.k] || '';
      sel.addEventListener('change', function () { map[fd.k] = sel.value; renderPreview(); });
      wrap.appendChild(lab); wrap.appendChild(sel);
      mapArea.appendChild(wrap);
    });
    renderPreview();
  }

  function valueOf(row, key) {
    var c = map[key];
    if (c === '' || c === undefined) return '';
    return String(row[Number(c)] == null ? '' : row[Number(c)]).trim();
  }

  function renderPreview() {
    var body = rows.slice(Number(hSelect.value) + 1);
    var data = [], tglRagu = 0, terSalah = 0;
    body.forEach(function (r, i) {
      var o = { _baris: i + Number(hSelect.value) + 2 };
      if (ORGANISASI) {
        o.jabatan = valueOf(r, 'jabatan');
        o.nama = valueOf(r, 'nama');
        if (o.jabatan === '') return;
      } else {
        o.nama = valueOf(r, 'nama');
        if (o.nama === '') return;
        var wm = valueOf(r, 'waktu');
        o.waktu = tglSama(wm);
        o._waktu_mentah = wm;
        if (wm !== '' && o.waktu === '') tglRagu++;
        o.target = valueOf(r, 'target');
        o.ta = valueOf(r, 'ta');
        var tm = valueOf(r, 'terlaksana');
        o.terlaksana = terSama(tm);
        o._ter_mentah = tm;
        if (tm !== '' && o.terlaksana === '') terSalah++;
        o.keterangan = valueOf(r, 'keterangan');
      }
      data.push(o);
    });
    prevInfo.textContent = data.length + ' baris siap diimpor';
    btnImpor.disabled = data.length === 0;
    if (!data.length) {
      prevArea.innerHTML = '<div class="empty"><h3>Belum ada baris data</h3><p>Periksa baris judul kolom dan pemetaan kolom wajib.</p></div>';
      return;
    }
    var html = '<div class="table-wrap"><table class="tbl tbl-impor"><thead><tr>';
    html += ORGANISASI
      ? '<th>#</th><th>Jabatan / Nama Seksi</th><th>Nama</th>'
      : '<th>#</th><th>Nama Program</th><th>Waktu Pelaksanaan</th><th>Target</th><th>Tahun Ajaran</th><th>Terlaksana</th><th>Keterangan</th>';
    html += '<th class="ctr">Status</th></tr></thead><tbody>';
    data.slice(0, 25).forEach(function (o, i) {
      html += '<tr><td>' + (i + 1) + '</td>';
      if (ORGANISASI) {
        html += '<td><b>' + esc(o.jabatan) + '</b></td><td>' + esc(o.nama || '-') + '</td>';
      } else {
        var waktuSel = (o._waktu_mentah !== '' && o.waktu === '')
          ? esc(o._waktu_mentah) + ' <span class="pill pill-warn">?</span>'
          : (o.waktu ? esc(o.waktu) : '<span class="mut">-</span>');
        var terSel = (o._ter_mentah !== '' && o.terlaksana === '')
          ? esc(o._ter_mentah) + ' <span class="pill pill-warn">?</span>'
          : esc(o.terlaksana || '-');
        html += '<td><b>' + esc(o.nama) + '</b></td><td class="sm">' + waktuSel + '</td>'
          + '<td>' + esc(o.target || '-') + '</td><td class="ctr">' + esc(o.ta || '-') + '</td>'
          + '<td class="ctr">' + terSel + '</td><td>' + esc(o.keterangan || '-') + '</td>';
      }
      html += '<td class="ctr"><span class="pill pill-ok">Siap</span></td></tr>';
    });
    html += '</tbody></table></div>';
    if (data.length > 25) html += '<p class="xs mut mt8">Menampilkan 25 dari ' + data.length + ' baris. Semua baris akan diimpor.</p>';
    if (tglRagu > 0) {
      html += '<p class="xs mut mt8">' + tglRagu + ' baris bertanda <span class="pill pill-warn">?</span> tanggalnya belum dikenali '
        + '&mdash; baris itu tetap diimpor tanpa tanggal. Gunakan format 2026-07-14 atau 14/07/2026.</p>';
    }
    if (terSalah > 0) {
      html += '<p class="xs mut mt8">' + terSalah + ' nilai Terlaksana di luar "Ya"/"Tidak" akan dikosongkan.</p>';
    }
    prevArea.innerHTML = html;
    prevArea.__data = data;
  }

  btnImpor.addEventListener('click', function () {
    var data = prevArea.__data || [];
    if (!data.length) return;
    var taEl = document.getElementById('taBawaan');
    document.getElementById('payloadInput').value = JSON.stringify({
      rows: data,
      perbarui: document.getElementById('opsiPerbarui').checked,
      ta_bawaan: taEl ? taEl.value : ''
    });
    document.getElementById('formImpor').submit();
  });

  document.getElementById('btnContoh').addEventListener('click', function () {
    var csv = ORGANISASI
      ? 'Jabatan,Nama\n'
        + 'Kepala Sekolah/Madrasah,Supianto, S.Pd.I\n'
        + 'Wakil Bidang Kesiswaan,Yelfis Djamaludin, S.Pd.I\n'
        + '<?= e((string) $konfig['jabatan'][2]) ?>,, \n'
        + '<?= e((string) $konfig['jabatan'][3]) ?>,, \n'
        + 'Seksi Keagamaan,, \n'
        + '<?= e((string) end($konfig['jabatan'])) ?>,, \n'
      : 'Nama Program,Waktu Pelaksanaan,Target,Tahun Ajaran,Terlaksana,Keterangan\n'
        + 'Latihan Dasar Kepemimpinan Siswa,2026-07-14,30 pengurus aktif,2026/2027,Ya,bekerja sama dengan OSIS kabupaten\n'
        + 'Bakti Sosial Ramadhan,14/03/2027,Seluruh pengurus,2026/2027,Tidak,menunggu izin\n';
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = ORGANISASI ? 'contoh-susunan-organisasi-osim.csv' : 'contoh-program-kerja-osim.csv';
    a.click();
  });
})();
</script>
<?php
page_bottom();
