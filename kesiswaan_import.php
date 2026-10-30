<?php
/**
 * IMPOR PROGRAM KERJA KESISWAAN dari Excel/CSV (dibaca di peramban dengan SheetJS, dikirim sebagai
 * JSON) — permintaan pemilik: "Tambahkan fitur impor dan ekspor dan juga cetak".
 *
 * Kolom (urutan pada contoh CSV mengikuti daftar permintaan pemilik):
 *   Jenis Kegiatan, Bentuk Kegiatan*, Tujuan, Target, Tahun Ajaran, Waktu Pelaksanaan, Sumber Dana,
 *   Keterangan.
 *
 * **Waktu Pelaksanaan** boleh ditulis sebagai nama bulan (boleh disingkat: "Jul", "agu", "des"),
 * beberapa bulan sekaligus (dipisah koma/titik koma/pipe), atau angka bulan kalender ("7" = Juli).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/kesiswaan.php';

$tugas = tugas_menu_by_kode(KS_KODE_MENU);
if (!$tugas) {
    require_login();
    flash('Menu Kesiswaan belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu_ubah($tugas, 'laporan Kesiswaan');   // hanya guru yang ditugaskan (+ Super Admin) yang boleh mengimpor
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
        $taBawaan = ks_ta_awal();
    }
    $tambah = 0; $ubah = 0; $lewat = 0; $bulanTidakDikenal = 0; $tanpaBulan = 0;

    if (!$rows) {
        $galat[] = 'Tidak ada baris data yang dikirim. Pastikan berkas sudah dipilih dan kolom sudah dipetakan.';
    } else {
        $now = date('Y-m-d H:i:s');
        $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) FROM kesiswaan_program WHERE sekolah_id = ?',
            [sekolah_id()], 0);
        db()->exec('BEGIN IMMEDIATE');
        try {
            foreach ($rows as $i => $r) {
                $bentuk = trim((string) ($r['bentuk'] ?? ''));
                $baris = (int) ($r['_baris'] ?? ($i + 1));
                if ($bentuk === '') {
                    $lewat++;
                    if (count($galat) < 8) {
                        $galat[] = 'Baris ' . $baris . ': Bentuk Kegiatan kosong, dilewati.';
                    }
                    continue;
                }
                if (strlen($bentuk) > KS_TEKS_MAKS) {
                    $bentuk = substr($bentuk, 0, KS_TEKS_MAKS);
                }
                $jenis = ks_pangkas(trim((string) ($r['jenis'] ?? '')), KS_TEKS_MAKS);
                $tujuan = ks_pangkas(trim((string) ($r['tujuan'] ?? '')), KS_URAIAN_MAKS);
                $target = ks_pangkas(trim((string) ($r['target'] ?? '')), KS_URAIAN_MAKS);
                $ket = ks_pangkas(trim((string) ($r['keterangan'] ?? '')), KS_URAIAN_MAKS);
                $sumber = ks_pangkas(trim((string) ($r['sumber_dana'] ?? '')), KS_SUMBER_MAKS);
                $ta = ks_pangkas(trim((string) ($r['ta'] ?? '')), 40);
                if ($ta === '') {
                    $ta = $taBawaan;   // tahun ajaran berkas kosong → pakai pilihan pada formulir impor
                }

                // Waktu pelaksanaan → daftar bulan baku. Sisa kata yang tidak dikenali dilaporkan.
                $waktuMentah = trim((string) ($r['waktu'] ?? ''));
                $waktuDaftar = ks_waktu_daftar($waktuMentah);
                $waktu = ks_waktu_simpan($waktuDaftar);
                if ($waktuMentah !== '' && $waktu === '') {
                    $bulanTidakDikenal++;
                } elseif ($waktu === '') {
                    $tanpaBulan++;
                }

                $ada = one('SELECT * FROM kesiswaan_program WHERE lower(bentuk) = lower(?) AND sekolah_id = ?',
                    [$bentuk, sekolah_id()]);
                if ($ada) {
                    if (!$perbarui) {
                        $lewat++;
                        continue;
                    }
                    q('UPDATE kesiswaan_program SET jenis=?, tujuan=?, target=?, ta=?, waktu=?,
                         sumber_dana=?, keterangan=?, diubah=? WHERE id=? AND sekolah_id=?',
                        [$jenis, $tujuan, $target, $ta, $waktu, $sumber, $ket, $now,
                         (int) $ada['id'], sekolah_id()]);
                    $ubah++;
                } else {
                    $urutan += 10;
                    q('INSERT INTO kesiswaan_program (sekolah_id, jenis, bentuk, tujuan, target, ta, waktu,
                         sumber_dana, keterangan, urutan, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                        [sekolah_id(), $jenis, $bentuk, $tujuan, $target, $ta, $waktu, $sumber, $ket,
                         $urutan, $now]);
                    $tambah++;
                }
            }
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        $ringkasan = ['tambah' => $tambah, 'ubah' => $ubah, 'lewat' => $lewat, 'total' => count($rows)];
        if ($bulanTidakDikenal > 0) {
            $galat[] = $bulanTidakDikenal . ' baris memuat Waktu Pelaksanaan yang tidak dikenali, sehingga '
                . 'waktunya dikosongkan. Tulislah nama bulan (Juli s.d. Juni) — boleh disingkat, dipisah '
                . 'koma (mis. "Juli, Agustus"), atau angka bulan (mis. 7 = Juli).';
        }
        if ($tanpaBulan > 0) {
            $galat[] = $tanpaBulan . ' baris tidak memuat Waktu Pelaksanaan — baris itu tetap diimpor '
                . 'dan dapat dilengkapi lewat tombol Ubah.';
        }
    }
}

$totalProgram = (int) val('SELECT COUNT(*) FROM kesiswaan_program WHERE sekolah_id = ?', [sekolah_id()], 0);
$taPilihan = ks_ta_pilihan();
$taAwal = ks_ta_awal();

page_top('Impor Program Kerja Kesiswaan', 'Laporan Program Kerja Kesiswaan');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('upload', 16) ?> Impor Program Kerja Kesiswaan (Excel / CSV)</h2>
      <p>Unggah berkas, cocokkan kolomnya, lihat pratinjau, lalu simpan. Berkas tidak disimpan di server.</p>
    </div>
    <a class="btn btn-ghost" href="tugas.php?kode=<?= e(KS_KODE_MENU) ?>&tab=data"><?= ikon('clipboard', 16) ?> Data Program (<?= $totalProgram ?>)</a>
  </div>

  <?php if ($ringkasan): ?>
    <div class="alert alert-ok">
      <?= ikon('check', 16) ?>
      <span>Impor selesai dari <?= (int) $ringkasan['total'] ?> baris: <b><?= (int) $ringkasan['tambah'] ?></b> kegiatan baru,
        <b><?= (int) $ringkasan['ubah'] ?></b> diperbarui, <b><?= (int) $ringkasan['lewat'] ?></b> dilewati.</span>
    </div>
  <?php endif; ?>
  <?php foreach ($galat as $g): ?>
    <div class="alert alert-warn"><?= ikon('alert', 16) ?><span><?= e($g) ?></span></div>
  <?php endforeach; ?>

  <div class="alert alert-ok" style="background:rgba(201,162,39,.08);border-color:rgba(201,162,39,.5)">
    <?= ikon('doc', 16) ?>
    <span>Kolom: Jenis Kegiatan, <b>Bentuk Kegiatan</b> (wajib), Tujuan, Target, Tahun Ajaran,
      <b>Waktu Pelaksanaan</b>, Sumber Dana, Keterangan. Waktu Pelaksanaan = nama bulan
      <b><?= e(implode(', ', KS_BULAN)) ?></b> — boleh disingkat, boleh beberapa (dipisah koma),
      atau angka bulan (7 = Juli).</span>
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
        <div><div class="butir-komponen">Pilihan</div><div class="butir-indikator">Kegiatan dengan nama sama dapat diperbarui.</div></div>
      </div>
      <div class="mt16 grid" style="gap:10px">
        <div>
          <label class="f" for="taBawaan">Tahun Ajaran (bila kolomnya kosong)</label>
          <select id="taBawaan">
            <option value="">— biarkan kosong —</option>
            <?php foreach ($taPilihan as $tp): ?>
              <option value="<?= e((string) $tp['nama']) ?>" <?= (string) $tp['nama'] === $taAwal ? 'selected' : '' ?>><?= e((string) $tp['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600">
          <input type="checkbox" id="opsiPerbarui" checked style="width:16px;height:16px;accent-color:var(--accent)">
          Perbarui kegiatan yang Bentuk Kegiatan-nya sudah ada
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
  <input type="hidden" name="payload" id="payloadInput">
</form>

<script src="assets/vendor/xlsx.full.min.js"></script>
<script>
(function () {
  // Kolom mengikuti daftar permintaan pemilik (No tidak diimpor — dibuat otomatis oleh tabel).
  var FIELDS = [
    { k: 'jenis',       l: 'Jenis Kegiatan',    keys: ['jeniskegiatan', 'jenis', 'kelompok'] },
    { k: 'bentuk',      l: 'Bentuk Kegiatan *', keys: ['bentukkegiatan', 'bentuk', 'kegiatan', 'nama'] },
    { k: 'tujuan',      l: 'Tujuan',            keys: ['tujuan'] },
    { k: 'target',      l: 'Target',            keys: ['target', 'sasaran'] },
    { k: 'ta',          l: 'Tahun Ajaran',      keys: ['tahunajaran', 'ta', 'tahun'] },
    { k: 'waktu',       l: 'Waktu Pelaksanaan', keys: ['waktupelaksanaan', 'waktu', 'pelaksanaan', 'bulan'] },
    { k: 'sumber_dana', l: 'Sumber Dana',       keys: ['sumberdana', 'sumber', 'dana'] },
    { k: 'keterangan',  l: 'Keterangan',        keys: ['keterangan', 'ket', 'catatan'] }
  ];
  var BULAN = <?= json_encode(KS_BULAN, JSON_UNESCAPED_UNICODE) ?>;
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

  // Penyamaan bulan untuk PRATINJAU saja (aturan yang menentukan saat menyimpan ada di server).
  var KALENDER = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  function bulanBaku(nilai) {
    var v = String(nilai == null ? '' : nilai).trim();
    if (!v) return '';
    if (/^[0-9]{1,2}$/.test(v)) { var n = parseInt(v, 10); return (n >= 1 && n <= 12) ? KALENDER[n - 1] : ''; }
    var kunci = v.toLowerCase().replace(/[^a-z0-9]+/g, '');
    for (var i = 0; i < BULAN.length; i++) {
      var nb = BULAN[i].toLowerCase();
      if (kunci.length >= 3 && nb.indexOf(kunci) === 0) return BULAN[i];
    }
    return '';
  }
  function waktuDaftar(teks) {
    var t = String(teks == null ? '' : teks).trim();
    if (!t) return [];
    var potong = t.split(/[,;|\/]+/), ada = {}, i, b;
    for (i = 0; i < potong.length; i++) { b = bulanBaku(potong[i]); if (b) ada[b] = 1; }
    if (!Object.keys(ada).length) {
      for (i = 0; i < BULAN.length; i++) { if (t.toLowerCase().indexOf(BULAN[i].toLowerCase()) >= 0) ada[BULAN[i]] = 1; }
    }
    var keluar = [];
    for (i = 0; i < BULAN.length; i++) { if (ada[BULAN[i]]) keluar.push(BULAN[i]); }
    return keluar;
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
    var data = [], bulanRagu = 0;
    body.forEach(function (r, i) {
      var waktuMentah = valueOf(r, 'waktu');
      var daftar = waktuDaftar(waktuMentah);
      if (waktuMentah !== '' && !daftar.length) bulanRagu++;
      var o = {
        jenis: valueOf(r, 'jenis'), bentuk: valueOf(r, 'bentuk'), tujuan: valueOf(r, 'tujuan'),
        target: valueOf(r, 'target'), ta: valueOf(r, 'ta'), waktu: daftar.join(', '),
        sumber_dana: valueOf(r, 'sumber_dana'), keterangan: valueOf(r, 'keterangan'),
        _waktu_mentah: waktuMentah,
        _baris: i + Number(hSelect.value) + 2
      };
      if (o.bentuk === '') return;
      data.push(o);
    });
    prevInfo.textContent = data.length + ' baris siap diimpor';
    btnImpor.disabled = data.length === 0;
    if (!data.length) {
      prevArea.innerHTML = '<div class="empty"><h3>Belum ada baris data</h3><p>Periksa baris judul kolom dan pemetaan kolom Bentuk Kegiatan.</p></div>';
      return;
    }
    var html = '<div class="table-wrap"><table class="tbl tbl-impor"><thead><tr>'
      + '<th>#</th><th>Jenis Kegiatan</th><th>Bentuk Kegiatan</th><th>Tujuan</th><th>Target</th>'
      + '<th>Tahun Ajaran</th><th>Waktu Pelaksanaan</th><th>Sumber Dana</th><th>Keterangan</th>'
      + '<th class="ctr">Status</th></tr></thead><tbody>';
    data.slice(0, 25).forEach(function (o, i) {
      var waktuSel = (o._waktu_mentah !== '' && !o.waktu)
        ? esc(o._waktu_mentah) + ' <span class="pill pill-warn">?</span>'
        : (o.waktu ? esc(o.waktu) : '<span class="mut">belum diisi</span>');
      html += '<tr><td>' + (i + 1) + '</td><td>' + esc(o.jenis || '-') + '</td><td><b>' + esc(o.bentuk) + '</b></td>'
        + '<td>' + esc(o.tujuan || '-') + '</td><td>' + esc(o.target || '-') + '</td>'
        + '<td class="ctr">' + esc(o.ta || '-') + '</td><td class="sm">' + waktuSel + '</td>'
        + '<td>' + esc(o.sumber_dana || '-') + '</td><td>' + esc(o.keterangan || '-') + '</td>'
        + '<td class="ctr"><span class="pill pill-ok">Siap</span></td></tr>';
    });
    html += '</tbody></table></div>';
    if (data.length > 25) html += '<p class="xs mut mt8">Menampilkan 25 dari ' + data.length + ' baris. Semua baris akan diimpor.</p>';
    if (bulanRagu > 0) {
      html += '<p class="xs mut mt8">' + bulanRagu + ' baris bertanda <span class="pill pill-warn">?</span> waktunya belum dikenali '
        + '&mdash; baris itu tetap diimpor tanpa waktu. Gunakan nama bulan (' + BULAN.join(', ') + ') atau angka bulan (7 = Juli).</p>';
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
      ta_bawaan: document.getElementById('taBawaan').value
    });
    document.getElementById('formImpor').submit();
  });

  document.getElementById('btnContoh').addEventListener('click', function () {
    var csv = 'Jenis Kegiatan,Bentuk Kegiatan,Tujuan,Target,Tahun Ajaran,Waktu Pelaksanaan,Sumber Dana,Keterangan\n'
      + 'Ekstrakurikuler,Latihan Pramuka mingguan,Membentuk kedisiplinan dan kemandirian,30 siswa aktif & 1 kali lomba,2026/2027,"Juli, Agustus",BOS,rutin setiap Sabtu\n'
      + 'Keagamaan,Peringatan Maulid Nabi,Menumbuhkan kecintaan kepada Rasulullah,Seluruh siswa hadir,2026/2027,Oktober,Komite,panitia OSIS\n'
      + 'Lomba,Pekan Olahraga Siswa,Meningkatkan kebugaran dan sportivitas,Juara tingkat kabupaten,2026/2027,"9,10",Sumbangan,\n';
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'contoh-program-kerja-kesiswaan.csv';
    a.click();
  });
})();
</script>
<?php
page_bottom();
