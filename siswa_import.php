<?php
/**
 * Impor data siswa dari Excel/CSV (dibaca di browser dengan SheetJS, dikirim sebagai JSON).
 *
 * Kolom yang didukung (permintaan pemilik): NIS, NISN, Nama Lengkap, Jenis Kelamin, Tempat Lahir,
 * Tanggal Lahir, Nomor Telepon (HP), Nama Ayah, Nama Ibu, dan Wali — ditambah Kelas (dipakai untuk
 * menautkan siswa ke Data Kelas). Nama kelas pada berkas dicocokkan dengan Data Kelas; bila tidak
 * ketemu, dipakai "Kelas tujuan" yang dipilih di bawah.
 *
 * Tanggal lahir dibaca LENIENT lewat tgl_impor() (inc/helpers.php) supaya tanggal dari Excel/CSV
 * seperti "12/04/2011", "12 April 2011", atau angka serial Excel tidak hilang tanpa pesan.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

$u = require_master();

$ringkasan = null;
$galat = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $data = json_decode((string) ($_POST['payload'] ?? ''), true);
    $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
    $perbarui = !empty($data['perbarui']);
    $kelasTujuan = (int) ($data['kelas_tujuan'] ?? 0);
    if ($kelasTujuan > 0 && !kelas_by_id($kelasTujuan)) {
        $kelasTujuan = 0;
    }

    $tambah = 0; $ubah = 0; $lewat = 0; $tanpaKelas = 0; $namaKelasAda = [];
    $tglGagal = 0;

    if (!$rows) {
        $galat[] = 'Tidak ada baris data yang dikirim. Pastikan berkas sudah dipilih dan kolom sudah dipetakan.';
    } else {
        $now = date('Y-m-d H:i:s');
        db()->exec('BEGIN IMMEDIATE');
        try {
            foreach ($rows as $i => $r) {
                $nama = trim((string) ($r['nama'] ?? ''));
                $baris = (int) ($r['_baris'] ?? ($i + 1));
                if ($nama === '') {
                    $lewat++;
                    if (count($galat) < 8) {
                        $galat[] = 'Baris ' . $baris . ': nama siswa kosong, dilewati.';
                    }
                    continue;
                }
                if (strlen($nama) > 160) {
                    $nama = substr($nama, 0, 160);
                }
                $nis = trim((string) ($r['nis'] ?? ''));
                $nisn = trim((string) ($r['nisn'] ?? ''));
                $jk = strtoupper(trim((string) ($r['jenis_kelamin'] ?? '')));
                $jk = in_array(substr($jk, 0, 1), ['L', 'P'], true) ? substr($jk, 0, 1) : '';
                $tempat = trim((string) ($r['tempat_lahir'] ?? ''));
                $lahirMentah = trim((string) ($r['tanggal_lahir'] ?? ''));
                $lahir = tgl_impor($lahirMentah);
                if ($lahir === null && $lahirMentah !== '') {
                    $tglGagal++;
                }
                $ayah = trim((string) ($r['nama_ayah'] ?? ''));
                $ibu = trim((string) ($r['nama_ibu'] ?? ''));
                // Kolom "Wali" boleh tidak ada pada berkas. Untuk siswa BARU, ayah/ibu dipakai
                // sebagai cadangan supaya kolom wali tidak kosong; untuk siswa yang SUDAH ADA,
                // nilai wali yang tersimpan TIDAK ditimpa bila berkasnya tidak memuat kolom wali
                // (menimpa dengan nama ayah akan menghapus isian wali yang sudah benar).
                $wali = trim((string) ($r['nama_wali'] ?? ''));
                $waliBaru = $wali !== '' ? $wali : ($ayah !== '' ? $ayah : $ibu);
                $telepon = trim((string) ($r['telepon'] ?? ''));

                $namaKelas = trim((string) ($r['kelas'] ?? ''));
                $kelasId = 0;
                if ($namaKelas !== '') {
                    $kelasId = (int) (db_val(db(), 'SELECT id FROM kelas WHERE lower(nama) = lower(?) AND sekolah_id = ?',
                        [$namaKelas, sekolah_id()]) ?? 0);
                    if ($kelasId <= 0 && !in_array($namaKelas, $namaKelasAda, true)) {
                        $namaKelasAda[] = $namaKelas;
                    }
                }
                if ($kelasId <= 0) {
                    $kelasId = $kelasTujuan;
                }
                if ($kelasId <= 0) {
                    $tanpaKelas++;
                }

                $ada = $nisn !== '' ? one('SELECT * FROM siswa WHERE nisn = ? AND sekolah_id = ?', [$nisn, sekolah_id()])
                     : ($nis !== '' ? one('SELECT * FROM siswa WHERE nis = ? AND sekolah_id = ?', [$nis, sekolah_id()])
                     : one('SELECT * FROM siswa WHERE lower(nama) = lower(?) AND IFNULL(kelas_id,0) = ? AND sekolah_id = ?',
                           [$nama, $kelasId, sekolah_id()]));

                if ($ada) {
                    if (!$perbarui) {
                        $lewat++;
                        continue;
                    }
                    q('UPDATE siswa SET nama=?, nis=CASE WHEN ? <> "" THEN ? ELSE nis END, nisn=CASE WHEN ? <> "" THEN ? ELSE nisn END,
                         jenis_kelamin=CASE WHEN ? <> "" THEN ? ELSE jenis_kelamin END, kelas_id=CASE WHEN CAST(? AS INTEGER) > 0 THEN ? ELSE kelas_id END,
                         tempat_lahir=CASE WHEN ? <> "" THEN ? ELSE tempat_lahir END,
                         tanggal_lahir=CASE WHEN ? IS NOT NULL THEN ? ELSE tanggal_lahir END,
                         nama_ayah=CASE WHEN ? <> "" THEN ? ELSE nama_ayah END,
                         nama_ibu=CASE WHEN ? <> "" THEN ? ELSE nama_ibu END,
                         nama_wali=CASE WHEN ? <> "" THEN ? ELSE nama_wali END,
                         telepon=CASE WHEN ? <> "" THEN ? ELSE telepon END, aktif=1 WHERE id=? AND sekolah_id=?',
                        // Perhatikan: untuk WALI dipakai nilai asli dari berkas ($wali) — kosong
                        // berarti "jangan ubah", bukan "timpa dengan nama ayah".
                        [$nama, $nis, $nis, $nisn, $nisn, $jk, $jk, $kelasId, $kelasId,
                         $tempat, $tempat, $lahir, $lahir, $ayah, $ayah, $ibu, $ibu,
                         $wali, $wali, $telepon, $telepon, (int) $ada['id'], sekolah_id()]);
                    $ubah++;
                } else {
                    q('INSERT INTO siswa (sekolah_id, nis, nisn, nama, jenis_kelamin, kelas_id, tempat_lahir,
                         tanggal_lahir, nama_ayah, nama_ibu, nama_wali, telepon, aktif, dibuat)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,?)',
                        [sekolah_id(), $nis, $nisn, $nama, $jk, $kelasId > 0 ? $kelasId : null, $tempat,
                         $lahir, $ayah, $ibu, $waliBaru, $telepon, $now]);
                    $tambah++;
                }
            }
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        $ringkasan = ['tambah' => $tambah, 'ubah' => $ubah, 'lewat' => $lewat, 'tanpa_kelas' => $tanpaKelas, 'total' => count($rows)];
        if ($namaKelasAda) {
            $galat[] = 'Nama kelas berikut tidak ada di Data Kelas: ' . implode(', ', array_slice($namaKelasAda, 0, 8))
                     . (count($namaKelasAda) > 8 ? ' …' : '') . '. Baris tersebut memakai "Kelas tujuan" (bila dipilih).';
        }
        if ($tglGagal > 0) {
            $galat[] = $tglGagal . ' baris punya tanggal lahir yang tidak dapat dikenali — baris itu tetap diimpor '
                     . 'tanpa tanggal lahir. Tulislah tanggal dengan format tahun-bulan-hari (mis. 2011-04-12) '
                     . 'atau hari/bulan/tahun (mis. 12/04/2011).';
        }
    }
}

$totalSiswa = (int) val('SELECT COUNT(*) FROM siswa WHERE sekolah_id = ?', [sekolah_id()], 0);

page_top('Impor Data Siswa');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2>Impor Data Siswa dari Excel / CSV</h2>
      <p>Unggah berkas, cocokkan kolomnya, lihat pratinjau, lalu simpan. Berkas tidak disimpan di server.</p>
    </div>
    <a class="btn btn-ghost" href="siswa.php"><?= ikon('student', 16) ?> Data Siswa (<?= $totalSiswa ?>)</a>
  </div>

  <?php if ($ringkasan): ?>
    <div class="alert alert-ok">
      <?= ikon('check', 16) ?>
      <span>Impor selesai dari <?= (int) $ringkasan['total'] ?> baris: <b><?= (int) $ringkasan['tambah'] ?></b> siswa baru,
      <b><?= (int) $ringkasan['ubah'] ?></b> diperbarui, <b><?= (int) $ringkasan['lewat'] ?></b> dilewati<?= $ringkasan['tanpa_kelas'] > 0 ? ', <b>' . (int) $ringkasan['tanpa_kelas'] . '</b> tanpa kelas' : '' ?>.</span>
    </div>
  <?php endif; ?>
  <?php foreach ($galat as $g): ?>
    <div class="alert alert-warn"><?= ikon('alert', 16) ?><span><?= e($g) ?></span></div>
  <?php endforeach; ?>

  <div class="alert alert-ok" style="background:rgba(201,162,39,.08);border-color:rgba(201,162,39,.5)">
    <?= ikon('doc', 16) ?>
    <span>Kolom yang diimpor ke Data Siswa: <b>NIS, NISN, Nama Lengkap, Jenis Kelamin, Tempat Lahir,
      Tanggal Lahir, Nomor Telepon (HP), Nama Ayah, Nama Ibu, Wali</b> &mdash; ditambah <b>Kelas</b>
      (dipakai untuk menautkan ke Data Kelas). Kolom yang tidak ada pada berkas boleh dibiarkan
      "tidak dipakai".</span>
  </div>

  <div class="grid g3" style="gap:16px">
    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">1</div>
        <div><div class="butir-komponen">Pilih berkas</div><div class="butir-indikator">Format .xlsx, .xls, atau .csv. Baris pertama boleh judul kolom.</div></div>
      </div>
      <div class="mt16"><input type="file" id="fileInput" accept=".xlsx,.xls,.csv,text/csv" style="padding:9px"></div>
      <div class="hint" id="fileInfo">Belum ada berkas dipilih.</div>
      <div class="mt8"><select id="sheetSelect" style="display:none"></select></div>
    </div>

    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">2</div>
        <div><div class="butir-komponen">Cocokkan kolom</div><div class="butir-indikator">Pilih kolom yang berisi data siswa. Judul kolom ditebak otomatis.</div></div>
      </div>
      <div class="mt16 grid" style="gap:10px">
        <div>
          <label class="f" for="headerRow">Baris judul kolom (header)</label>
          <select id="headerRow"></select>
        </div>
        <div id="mapArea" class="grid g2" style="gap:10px"></div>
      </div>
    </div>

    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">3</div>
        <div><div class="butir-komponen">Kelas &amp; pilihan</div><div class="butir-indikator">Dipakai bila nama kelas pada berkas tidak cocok.</div></div>
      </div>
      <div class="mt16 grid" style="gap:10px">
        <div>
          <label class="f" for="kelasTujuan">Kelas tujuan (cadangan)</label>
          <select id="kelasTujuan">
            <option value="">— kosongkan (belum berkelas) —</option>
            <?php foreach (kelas_list(true) as $k): ?>
              <option value="<?= (int) $k['id'] ?>"><?= e((string) $k['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600">
          <input type="checkbox" id="opsiPerbarui" checked style="width:16px;height:16px;accent-color:var(--accent)"> Perbarui siswa yang NIS/NISN-nya sudah ada
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
    <div class="empty"><?= ikon('upload', 34) ?><h3>Belum ada berkas</h3><p>Pilih berkas Excel/CSV untuk melihat pratinjau datanya di sini.</p></div>
  </div>
</div>

<form method="post" id="formImpor" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="payload" id="payloadInput">
</form>

<script src="assets/vendor/xlsx.full.min.js"></script>
<script>
(function () {
  // Kolom yang diimpor (urutan mengikuti permintaan pemilik; Kelas di akhir karena hanya dipakai
  // untuk menautkan ke Data Kelas). `keys` = tebakan judul kolom pada berkas.
  var FIELDS = [
    { k: 'nama',          l: 'Nama Lengkap *',  keys: ['namalengkap', 'namasiswa', 'nama', 'name', 'siswa'] },
    { k: 'nis',           l: 'NIS',             keys: ['nis'] },
    { k: 'nisn',          l: 'NISN',            keys: ['nisn'] },
    { k: 'jenis_kelamin', l: 'Jenis Kelamin',   keys: ['jeniskelamin', 'jk', 'jenis', 'gender', 'lp'] },
    { k: 'tempat_lahir',  l: 'Tempat Lahir',    keys: ['tempatlahir', 'tempat', 'tmplahir'] },
    { k: 'tanggal_lahir', l: 'Tanggal Lahir',   keys: ['tanggallahir', 'tgllahir', 'birth', 'tgl'] },
    { k: 'telepon',       l: 'Nomor Telepon (HP)', keys: ['nomortelepon', 'notelepon', 'telepon', 'nohp', 'nomorhp', 'hp', 'wa', 'whatsapp', 'telp'] },
    { k: 'nama_ayah',     l: 'Nama Ayah',       keys: ['namaayah', 'ayah', 'father'] },
    { k: 'nama_ibu',      l: 'Nama Ibu',        keys: ['namaibu', 'ibu', 'mother'] },
    { k: 'nama_wali',     l: 'Wali',            keys: ['namawali', 'wali', 'orangtua', 'namaorangtua', 'ortu'] },
    { k: 'kelas',         l: 'Kelas',           keys: ['kelas', 'rombel', 'rombonganbelajar', 'class'] }
  ];
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

  // Penyamaan tanggal untuk PRATINJAU saja (yang menentukan saat menyimpan adalah tgl_impor() di
  // server, aturannya sama): hari lebih dulu kecuali angkanya jelas menunjukkan sebaliknya.
  var BULAN = { januari: 1, februari: 2, maret: 3, april: 4, mei: 5, juni: 6, juli: 7, agustus: 8,
    september: 9, oktober: 10, november: 11, desember: 12, jan: 1, feb: 2, mar: 3, apr: 4, jun: 6,
    jul: 7, agu: 8, ags: 8, sep: 9, sept: 9, okt: 10, nov: 11, des: 12 };
  function dua(n) { return (n < 10 ? '0' : '') + n; }
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
      var h, b2;
      if (a > 12 && b <= 12) { h = a; b2 = b; }
      else if (b > 12 && a <= 12) { h = b; b2 = a; }
      else { h = a; b2 = b; }
      return y + '-' + dua(b2) + '-' + dua(h);
    }
    m = /^(\d{1,2})\s+([A-Za-z]+)\s+(\d{2,4})$/.exec(s);
    if (m) {
      var bl = BULAN[m[2].toLowerCase()];
      if (bl) {
        var yy = +m[3];
        if (yy < 100) yy += 2000;
        return yy + '-' + dua(bl) + '-' + dua(+m[1]);
      }
    }
    if (/^\d{5}$/.test(s)) {
      var n = +s;
      if (n >= 20000 && n <= 80000) {
        var d = new Date(Date.UTC(1899, 11, 30) + n * 86400000);
        return d.toISOString().slice(0, 10);
      }
    }
    return '';
  }

  fInput.addEventListener('change', function (ev) {
    var f = ev.target.files && ev.target.files[0];
    if (!f) return;
    var fr = new FileReader();
    fr.onload = function () {
      try { wb = XLSX.read(new Uint8Array(fr.result), { type: 'array', cellDates: true }); }
      catch (e) { fileInfo.textContent = 'Gagal membaca berkas: ' + e.message; return; }
      fileInfo.innerHTML = '<b>' + esc(f.name) + '</b> &middot; ' + Math.round(f.size / 1024) + ' KB &middot; ' + wb.SheetNames.length + ' sheet';
      sSelect.innerHTML = '';
      wb.SheetNames.forEach(function (n) {
        var o = document.createElement('option');
        o.value = n; o.textContent = 'Sheet: ' + n;
        sSelect.appendChild(o);
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
      prevArea.innerHTML = '<div class="empty"><h3>Sheet kosong</h3><p>Tidak ada data pada sheet ini.</p></div>';
      btnImpor.disabled = true;
      return;
    }
    var guess = 0;
    for (var i = 0; i < Math.min(rows.length, 12); i++) {
      if (rows[i].filter(function (c) { return String(c).trim() !== ''; }).length >= 2) { guess = i; break; }
    }
    hSelect.innerHTML = '';
    for (var j = 0; j < Math.min(rows.length, 15); j++) {
      var o = document.createElement('option');
      o.value = j;
      o.textContent = 'Baris ' + (j + 1) + ': ' + (rows[j].slice(0, 6).join(' | ').substring(0, 80) || '(kosong)');
      hSelect.appendChild(o);
    }
    hSelect.value = guess;
    map = {};
    render();
  }

  hSelect.addEventListener('change', function () { map = {}; render(); });

  // Tebakan kolom: cocok PERSIS diutamakan (dua tahap) dan satu kolom tidak dipakai dua medan.
  // Tanpa aturan ini, judul seperti "Nama Ayah" bisa "dirampas" oleh medan Nama karena cocok awalan.
  function tebakKolom() {
    var dipakai = {};
    var hasil = {};
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
      var lab = document.createElement('label');
      lab.className = 'f'; lab.textContent = fd.l;
      var sel = document.createElement('select');
      var o0 = document.createElement('option');
      o0.value = ''; o0.textContent = '— tidak dipakai —';
      sel.appendChild(o0);
      for (var c = 0; c < maxCol; c++) {
        var o = document.createElement('option');
        o.value = String(c);
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
    var data = [];
    var tglRagu = 0;
    body.forEach(function (r, i) {
      var tglMentah = valueOf(r, 'tanggal_lahir');
      var tgl = tglSama(tglMentah);
      if (tglMentah !== '' && tgl === '') tglRagu++;
      var o = {
        nama: valueOf(r, 'nama'), nis: valueOf(r, 'nis'), nisn: valueOf(r, 'nisn'),
        jenis_kelamin: valueOf(r, 'jenis_kelamin'), kelas: valueOf(r, 'kelas'),
        tempat_lahir: valueOf(r, 'tempat_lahir'),
        tanggal_lahir: tgl !== '' ? tgl : tglMentah,
        _tgl_mentah: tglMentah,
        nama_ayah: valueOf(r, 'nama_ayah'), nama_ibu: valueOf(r, 'nama_ibu'),
        nama_wali: valueOf(r, 'nama_wali'), telepon: valueOf(r, 'telepon'),
        _baris: i + Number(hSelect.value) + 2
      };
      if (o.nama === '' && o.nis === '' && o.nisn === '') return;
      data.push(o);
    });
    prevInfo.textContent = data.length + ' baris siap diimpor';
    btnImpor.disabled = data.length === 0;
    if (!data.length) {
      prevArea.innerHTML = '<div class="empty"><h3>Belum ada baris data</h3><p>Periksa baris judul kolom dan pemetaan kolom Nama Lengkap.</p></div>';
      return;
    }
    var html = '<div class="table-wrap"><table class="tbl tbl-impor"><thead><tr>'
      + '<th>#</th><th>Nama Lengkap</th><th>NIS</th><th>NISN</th><th class="ctr">JK</th>'
      + '<th>Tempat Lahir</th><th>Tanggal Lahir</th><th>No. HP</th><th>Nama Ayah</th><th>Nama Ibu</th><th>Wali</th>'
      + '<th>Kelas</th><th class="ctr">Status</th></tr></thead><tbody>';
    data.slice(0, 25).forEach(function (o, i) {
      var tglSel = (o._tgl_mentah !== '' && tglSama(o._tgl_mentah) === '')
        ? esc(o._tgl_mentah) + ' <span class="pill pill-warn">?</span>' : esc(o.tanggal_lahir || '-');
      html += '<tr><td>' + (i + 1) + '</td><td><b>' + esc(o.nama || '-') + '</b></td><td>' + esc(o.nis || '-') + '</td>'
        + '<td>' + esc(o.nisn || '-') + '</td><td class="ctr">' + esc(o.jenis_kelamin || '-') + '</td>'
        + '<td>' + esc(o.tempat_lahir || '-') + '</td><td class="nowrap">' + tglSel + '</td>'
        + '<td class="nowrap">' + esc(o.telepon || '-') + '</td>'
        + '<td>' + esc(o.nama_ayah || '-') + '</td><td>' + esc(o.nama_ibu || '-') + '</td>'
        + '<td>' + esc(o.nama_wali || (o.nama_ayah || o.nama_ibu) || '-') + '</td>'
        + '<td>' + esc(o.kelas || '-') + '</td>'
        + '<td class="ctr">' + (o.nama !== '' ? '<span class="pill pill-ok">Siap</span>' : '<span class="pill pill-D">Nama kosong</span>') + '</td></tr>';
    });
    html += '</tbody></table></div>';
    if (data.length > 25) html += '<p class="xs mut mt8">Menampilkan 25 dari ' + data.length + ' baris. Semua baris akan diimpor.</p>';
    if (tglRagu > 0) {
      html += '<p class="xs mut mt8">' + tglRagu + ' baris bertanda <span class="pill pill-warn">?</span> tanggalnya belum dikenali '
        + '&mdash; baris itu tetap diimpor tanpa tanggal lahir. Gunakan format tahun-bulan-hari (2011-04-12) '
        + 'atau hari/bulan/tahun (12/04/2011).</p>';
    }
    prevArea.innerHTML = html;
    prevArea.__data = data;
  }

  btnImpor.addEventListener('click', function () {
    var data = prevArea.__data || [];
    if (!data.length) return;
    document.getElementById('payloadInput').value = JSON.stringify({
      rows: data,
      perbarui: document.getElementById('opsiPerbarui').checked,
      kelas_tujuan: document.getElementById('kelasTujuan').value
    });
    document.getElementById('formImpor').submit();
  });

  // Contoh format mengikuti daftar kolom yang diminta pemilik (10 kolom + Kelas).
  document.getElementById('btnContoh').addEventListener('click', function () {
    var csv = 'NIS,NISN,Nama Lengkap,Jenis Kelamin,Tempat Lahir,Tanggal Lahir,No. HP,Nama Ayah,Nama Ibu,Wali,Kelas\n'
      + '2024001,0091234567,Ahmad Fauzi,L,Mootilango,2011-04-12,081200000010,Muhammad Yusuf,Siti Aminah,Muhammad Yusuf,VII-A\n'
      + '2024002,0091234568,Nur Aisyah,P,Mootilango,2011-06-20,081200000011,Abdul Karim,Nur Halimah,Nur Halimah,VII-A\n';
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'contoh-data-siswa.csv';
    a.click();
  });
})();
</script>
<?php
page_bottom();
