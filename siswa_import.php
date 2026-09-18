<?php
/**
 * Impor data siswa dari Excel/CSV (dibaca di browser dengan SheetJS, dikirim sebagai JSON).
 * Nama kelas pada berkas dicocokkan dengan Data Kelas; bila tidak ketemu, dipakai
 * "Kelas tujuan" yang dipilih di bawah ini.
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
                $lahir = tgl_ok($r['tanggal_lahir'] ?? null);
                $wali = trim((string) ($r['nama_wali'] ?? ''));
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
                         tanggal_lahir=CASE WHEN ? IS NOT NULL THEN ? ELSE tanggal_lahir END,
                         nama_wali=CASE WHEN ? <> "" THEN ? ELSE nama_wali END, telepon=CASE WHEN ? <> "" THEN ? ELSE telepon END, aktif=1 WHERE id=? AND sekolah_id=?',
                        [$nama, $nis, $nis, $nisn, $nisn, $jk, $jk, $kelasId, $kelasId,
                         $lahir, $lahir, $wali, $wali, $telepon, $telepon, (int) $ada['id'], sekolah_id()]);
                    $ubah++;
                } else {
                    q('INSERT INTO siswa (sekolah_id, nis, nisn, nama, jenis_kelamin, kelas_id, tanggal_lahir, nama_wali, telepon, aktif, dibuat) VALUES (?,?,?,?,?,?,?,?,?,1,?)',
                        [sekolah_id(), $nis, $nisn, $nama, $jk, $kelasId > 0 ? $kelasId : null, $lahir, $wali, $telepon, $now]);
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
        <div><div class="butir-komponen">Cocokkan kolom</div><div class="butir-indikator">Pilih kolom yang berisi data siswa.</div></div>
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
  var FIELDS = [
    { k: 'nama',          l: 'Nama Siswa *',    keys: ['nama', 'namasiswa', 'siswa', 'name'] },
    { k: 'nis',           l: 'NIS',             keys: ['nis'] },
    { k: 'nisn',          l: 'NISN',            keys: ['nisn'] },
    { k: 'jenis_kelamin', l: 'Jenis Kelamin',   keys: ['jeniskelamin', 'jk', 'gender', 'lp'] },
    { k: 'kelas',         l: 'Kelas',           keys: ['kelas', 'rombel', 'rombonganbelajar'] },
    { k: 'tanggal_lahir', l: 'Tanggal Lahir',   keys: ['tanggallahir', 'lahir', 'tgllahir', 'birth'] },
    { k: 'nama_wali',     l: 'Nama Orang Tua/Wali', keys: ['namawali', 'wali', 'orangtua', 'ayah', 'ibu', 'namaorangtua'] },
    { k: 'telepon',       l: 'No. HP Wali',     keys: ['telepon', 'hp', 'wa', 'whatsapp', 'nohp', 'telp'] }
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

  function render() {
    header = rows[Number(hSelect.value)] || [];
    var maxCol = 0;
    rows.forEach(function (r) { if (r.length > maxCol) maxCol = r.length; });
    if (!Object.keys(map).length) {
      FIELDS.forEach(function (fd) {
        map[fd.k] = '';
        header.forEach(function (h, ci) {
          var nh = norm(h);
          if (!nh) return;
          fd.keys.forEach(function (k) {
            if (!map[fd.k] && (nh === k || nh.indexOf(k) === 0)) { map[fd.k] = String(ci); }
          });
        });
      });
    }
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
    body.forEach(function (r, i) {
      var o = {
        nama: valueOf(r, 'nama'), nis: valueOf(r, 'nis'), nisn: valueOf(r, 'nisn'),
        jenis_kelamin: valueOf(r, 'jenis_kelamin'), kelas: valueOf(r, 'kelas'),
        tanggal_lahir: valueOf(r, 'tanggal_lahir'), nama_wali: valueOf(r, 'nama_wali'),
        telepon: valueOf(r, 'telepon'), _baris: i + Number(hSelect.value) + 2
      };
      if (o.nama === '' && o.nis === '' && o.nisn === '') return;
      data.push(o);
    });
    prevInfo.textContent = data.length + ' baris siap diimpor';
    btnImpor.disabled = data.length === 0;
    if (!data.length) {
      prevArea.innerHTML = '<div class="empty"><h3>Belum ada baris data</h3><p>Periksa baris judul kolom dan pemetaan kolom Nama Siswa.</p></div>';
      return;
    }
    var html = '<div class="table-wrap"><table class="tbl"><thead><tr><th>#</th><th>Nama</th><th>NIS</th><th>NISN</th><th>JK</th><th>Kelas</th><th>Tgl Lahir</th><th>Wali</th><th>No. HP</th><th class="ctr">Status</th></tr></thead><tbody>';
    data.slice(0, 25).forEach(function (o, i) {
      html += '<tr><td>' + (i + 1) + '</td><td><b>' + esc(o.nama || '-') + '</b></td><td>' + esc(o.nis || '-') + '</td><td>' + esc(o.nisn || '-') + '</td>'
        + '<td>' + esc(o.jenis_kelamin || '-') + '</td><td>' + esc(o.kelas || '-') + '</td><td>' + esc(o.tanggal_lahir || '-') + '</td>'
        + '<td>' + esc(o.nama_wali || '-') + '</td><td>' + esc(o.telepon || '-') + '</td>'
        + '<td class="ctr">' + (o.nama !== '' ? '<span class="pill pill-ok">Siap</span>' : '<span class="pill pill-D">Nama kosong</span>') + '</td></tr>';
    });
    html += '</tbody></table></div>';
    if (data.length > 25) html += '<p class="xs mut mt8">Menampilkan 25 dari ' + data.length + ' baris. Semua baris akan diimpor.</p>';
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

  document.getElementById('btnContoh').addEventListener('click', function () {
    var csv = 'Nama,NIS,NISN,Jenis Kelamin,Kelas,Tanggal Lahir,Nama Wali,No HP\n'
      + 'Ahmad Fauzi,2024001,0091234567,L,VII-A,2011-04-12,Muhammad Yusuf,081200000010\n'
      + 'Nur Aisyah,2024002,0091234568,P,VII-A,2011-06-20,Siti Fatimah,081200000011\n';
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
