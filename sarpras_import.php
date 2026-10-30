<?php
/**
 * IMPOR DATA BARANG SARANA & PRASARANA dari Excel/CSV (dibaca di peramban dengan SheetJS, dikirim
 * sebagai JSON) — permintaan pemilik: "Tambahkan juga fitur impor, expor, Cetak".
 *
 * Kolom yang dikenali (urutan pada contoh CSV mengikuti daftar permintaan pemilik):
 *   Nama Barang*, Spesifikasi, Lokasi, Kondisi, Jumlah, Sumber Dana, Jenis, Keterangan
 *
 * Kondisi & Jenis memakai PILIHAN TETAP (SARPRAS_* di inc/config.php): nilai yang tidak cocok TIDAK
 * ditolak, tetapi dikosongkan (kondisi jatuh ke "Baik") dan jumlahnya dilaporkan sebagai peringatan
 * supaya pengguna bisa membetulkan berkasnya.
 * **Sumber Dana = ISIAN MANUAL** (permintaan pemilik: "Sumber dana rubah menjadi inputan manual") →
 * nilainya dibaca apa adanya, tanpa diperiksa terhadap daftar.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/sarpras.php';

$tugas = tugas_menu_by_kode(SARPRAS_KODE_MENU);
if (!$tugas) {
    require_login();
    flash('Menu Sarana & Prasarana belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu_ubah($tugas, 'data Sarana & Prasarana');   // hanya guru yang ditugaskan (+ Super Admin) yang boleh mengimpor
require_sekolah();

$ringkasan = null;
$galat = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $data = json_decode((string) ($_POST['payload'] ?? ''), true);
    $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
    $perbarui = !empty($data['perbarui']);
    $tambah = 0; $ubah = 0; $lewat = 0; $opsiSalah = 0; $galatJumlah = 0;

    if (!$rows) {
        $galat[] = 'Tidak ada baris data yang dikirim. Pastikan berkas sudah dipilih dan kolom sudah dipetakan.';
    } else {
        $now = date('Y-m-d H:i:s');
        $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) FROM sarpras_barang WHERE sekolah_id = ?',
            [sekolah_id()], 0);
        db()->exec('BEGIN IMMEDIATE');
        try {
            foreach ($rows as $i => $r) {
                $nama = trim((string) ($r['nama'] ?? ''));
                $baris = (int) ($r['_baris'] ?? ($i + 1));
                if ($nama === '') {
                    $lewat++;
                    if (count($galat) < 8) {
                        $galat[] = 'Baris ' . $baris . ': nama barang kosong, dilewati.';
                    }
                    continue;
                }
                if (strlen($nama) > 160) {
                    $nama = substr($nama, 0, 160);
                }
                $spesifikasi = trim((string) ($r['spesifikasi'] ?? ''));
                $lokasi = trim((string) ($r['lokasi'] ?? ''));
                $ket = trim((string) ($r['keterangan'] ?? ''));

                // Pilihan tetap: nilai yang tidak dikenal dikosongkan (kondisi → "Baik").
                $kondisiMentah = trim((string) ($r['kondisi'] ?? ''));
                $jenisMentah = trim((string) ($r['jenis'] ?? ''));
                $kondisi = sarpras_pilihan_sah('kondisi', $kondisiMentah, true);
                $jenis = sarpras_pilihan_sah('jenis', $jenisMentah);
                // Sumber Dana = isian manual → dipakai apa adanya (hanya dipangkas & dibatasi panjangnya).
                $sumber = trim((string) ($r['sumber_dana'] ?? ''));
                if (strlen($sumber) > SARPRAS_SUMBER_MAKS) {
                    $sumber = substr($sumber, 0, SARPRAS_SUMBER_MAKS);
                }
                if (($kondisiMentah !== '' && strcasecmp($kondisiMentah, $kondisi) !== 0)
                    || ($jenisMentah !== '' && $jenis === '')) {
                    $opsiSalah++;
                }

                $jumlahMentah = trim((string) ($r['jumlah'] ?? ''));
                $jumlah = (int) preg_replace('/[^0-9\-]/', '', $jumlahMentah);
                if ($jumlahMentah !== '' && !preg_match('/^[0-9.,\s]+$/', $jumlahMentah)) {
                    $galatJumlah++;
                    $jumlah = 1;
                }
                if ($jumlah < 0) {
                    $jumlah = 0;
                }

                $ada = one('SELECT * FROM sarpras_barang WHERE lower(nama) = lower(?) AND sekolah_id = ?',
                    [$nama, sekolah_id()]);
                if ($ada) {
                    if (!$perbarui) {
                        $lewat++;
                        continue;
                    }
                    q('UPDATE sarpras_barang SET spesifikasi=?, lokasi=?, kondisi=?, jumlah=?,
                         sumber_dana=?, jenis=?, keterangan=?, diubah=? WHERE id=? AND sekolah_id=?',
                        [$spesifikasi, $lokasi, $kondisi, $jumlah, $sumber, $jenis, $ket, $now,
                         (int) $ada['id'], sekolah_id()]);
                    $ubah++;
                } else {
                    $urutan += 10;
                    q('INSERT INTO sarpras_barang (sekolah_id, nama, spesifikasi, lokasi, kondisi, jumlah,
                         sumber_dana, jenis, keterangan, urutan, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                        [sekolah_id(), $nama, $spesifikasi, $lokasi, $kondisi, $jumlah, $sumber, $jenis,
                         $ket, $urutan, $now]);
                    $tambah++;
                }
            }
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        $ringkasan = ['tambah' => $tambah, 'ubah' => $ubah, 'lewat' => $lewat, 'total' => count($rows)];
        if ($opsiSalah > 0) {
            $galat[] = $opsiSalah . ' baris memakai Kondisi/Jenis di luar pilihan yang tersedia. '
                . 'Nilai itu dikosongkan (Kondisi menjadi "' . SARPRAS_KONDISI[0] . '"). '
                . 'Pilihan yang sah — Kondisi: ' . implode(', ', SARPRAS_KONDISI)
                . '; Jenis: ' . implode(', ', SARPRAS_JENIS) . '.';
        }
        if ($galatJumlah > 0) {
            $galat[] = $galatJumlah . ' baris punya Jumlah yang bukan angka — baris itu diisi 1. '
                . 'Gunakan angka saja (mis. 5).';
        }
    }
}

$totalBarang = (int) val('SELECT COUNT(*) FROM sarpras_barang WHERE sekolah_id = ?', [sekolah_id()], 0);

page_top('Impor Data Barang', 'Sarana & Prasarana');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('upload', 16) ?> Impor Data Barang (Excel / CSV)</h2>
      <p>Unggah berkas, cocokkan kolomnya, lihat pratinjau, lalu simpan. Berkas tidak disimpan di server.</p>
    </div>
    <a class="btn btn-ghost" href="tugas.php?kode=<?= e(SARPRAS_KODE_MENU) ?>&tab=data"><?= ikon('folder', 16) ?> Data Barang (<?= $totalBarang ?>)</a>
  </div>

  <?php if ($ringkasan): ?>
    <div class="alert alert-ok">
      <?= ikon('check', 16) ?>
      <span>Impor selesai dari <?= (int) $ringkasan['total'] ?> baris: <b><?= (int) $ringkasan['tambah'] ?></b> barang baru,
        <b><?= (int) $ringkasan['ubah'] ?></b> diperbarui, <b><?= (int) $ringkasan['lewat'] ?></b> dilewati.</span>
    </div>
  <?php endif; ?>
  <?php foreach ($galat as $g): ?>
    <div class="alert alert-warn"><?= ikon('alert', 16) ?><span><?= e($g) ?></span></div>
  <?php endforeach; ?>

  <div class="alert alert-ok" style="background:rgba(201,162,39,.08);border-color:rgba(201,162,39,.5)">
    <?= ikon('doc', 16) ?>
    <span>Kolom: <b>Nama Barang</b> (wajib), Spesifikasi, Lokasi, <b>Kondisi</b>, Jumlah,
      <b>Sumber Dana</b> (isian bebas, mis. BOS/APBD/Komite/Sumbangan), <b>Jenis</b>, Keterangan.
      Pilihan yang sah — Kondisi: <?= e(implode(', ', SARPRAS_KONDISI)) ?>;
      Jenis: <?= e(implode(', ', SARPRAS_JENIS)) ?>.</span>
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
        <div><div class="butir-komponen">Pilihan</div><div class="butir-indikator">Barang dengan nama sama dapat diperbarui.</div></div>
      </div>
      <div class="mt16 grid" style="gap:10px">
        <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600">
          <input type="checkbox" id="opsiPerbarui" checked style="width:16px;height:16px;accent-color:var(--accent)">
          Perbarui barang yang namanya sudah ada
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
  // Kolom mengikuti daftar permintaan pemilik: No tidak diimpor (nomor dibuat otomatis oleh tabel).
  var FIELDS = [
    { k: 'nama',        l: 'Nama Barang *', keys: ['namabarang', 'nama', 'barang'] },
    { k: 'spesifikasi', l: 'Spesifikasi',   keys: ['spesifikasi', 'spek', 'spec'] },
    { k: 'lokasi',      l: 'Lokasi',        keys: ['lokasi', 'tempat', 'ruang'] },
    { k: 'kondisi',     l: 'Kondisi',       keys: ['kondisi'] },
    { k: 'jumlah',      l: 'Jumlah',        keys: ['jumlah', 'banyak', 'qty', 'total'] },
    { k: 'sumber_dana', l: 'Sumber Dana',   keys: ['sumberdana', 'sumber', 'dana'] },
    { k: 'jenis',       l: 'Jenis',         keys: ['jenis', 'jenisbarang', 'klasifikasi'] },
    { k: 'keterangan',  l: 'Keterangan',    keys: ['keterangan', 'ket', 'catatan'] }
  ];
  // Hanya Kondisi & Jenis yang punya pilihan tetap; Sumber Dana = isian manual (tidak diperiksa).
  var OPSI = {
    kondisi: <?= json_encode(SARPRAS_KONDISI, JSON_UNESCAPED_UNICODE) ?>,
    jenis: <?= json_encode(SARPRAS_JENIS, JSON_UNESCAPED_UNICODE) ?>
  };
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
  function opsiSah(kunci, nilai) {
    var cocok = '';
    (OPSI[kunci] || []).forEach(function (o) { if (o.toLowerCase() === String(nilai).trim().toLowerCase()) cocok = o; });
    return cocok;
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

  // Tebakan dua tahap (cocok PERSIS dulu, baru awalan) + satu kolom tidak dipakai dua medan —
  // pola yang sama dengan impor siswa.
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
    var data = [], opsiSalah = 0;
    body.forEach(function (r, i) {
      var o = {
        nama: valueOf(r, 'nama'), spesifikasi: valueOf(r, 'spesifikasi'), lokasi: valueOf(r, 'lokasi'),
        kondisi: valueOf(r, 'kondisi'), jumlah: valueOf(r, 'jumlah'),
        sumber_dana: valueOf(r, 'sumber_dana'), jenis: valueOf(r, 'jenis'),
        keterangan: valueOf(r, 'keterangan'),
        _baris: i + Number(hSelect.value) + 2
      };
      if (o.nama === '') return;
      ['kondisi', 'jenis'].forEach(function (k) {
        if (o[k] !== '' && !opsiSah(k, o[k])) { opsiSalah++; o['_' + k + '_salah'] = 1; }
      });
      data.push(o);
    });
    prevInfo.textContent = data.length + ' baris siap diimpor';
    btnImpor.disabled = data.length === 0;
    if (!data.length) {
      prevArea.innerHTML = '<div class="empty"><h3>Belum ada baris data</h3><p>Periksa baris judul kolom dan pemetaan kolom Nama Barang.</p></div>';
      return;
    }
    var html = '<div class="table-wrap"><table class="tbl tbl-impor"><thead><tr>'
      + '<th>#</th><th>Nama Barang</th><th>Spesifikasi</th><th>Lokasi</th><th>Kondisi</th><th class="ctr">Jumlah</th>'
      + '<th>Sumber Dana</th><th>Jenis</th><th>Keterangan</th><th class="ctr">Status</th></tr></thead><tbody>';
    data.slice(0, 25).forEach(function (o, i) {
      function sel(k) {
        var v = o[k] === '' ? '-' : o[k];
        return o['_' + k + '_salah'] ? '<span style="color:#8d2733">' + esc(v) + '</span> <span class="pill pill-warn">?</span>' : esc(v);
      }
      html += '<tr><td>' + (i + 1) + '</td><td><b>' + esc(o.nama) + '</b></td><td>' + esc(o.spesifikasi || '-') + '</td>'
        + '<td>' + esc(o.lokasi || '-') + '</td><td>' + sel('kondisi') + '</td><td class="ctr">' + esc(o.jumlah || '-') + '</td>'
        + '<td>' + esc(o.sumber_dana || '-') + '</td><td>' + sel('jenis') + '</td><td>' + esc(o.keterangan || '-') + '</td>'
        + '<td class="ctr"><span class="pill pill-ok">Siap</span></td></tr>';
    });
    html += '</tbody></table></div>';
    if (data.length > 25) html += '<p class="xs mut mt8">Menampilkan 25 dari ' + data.length + ' baris. Semua baris akan diimpor.</p>';
    if (opsiSalah > 0) {
      html += '<p class="xs mut mt8">' + opsiSalah + ' nilai bertanda <span class="pill pill-warn">?</span> di luar pilihan '
        + '(Kondisi/Jenis) &mdash; nilainya akan dikosongkan. Pilihan sah: '
        + OPSI.kondisi.join(', ') + ' / ' + OPSI.jenis.join(', ') + '. Sumber Dana bebas diisi.</p>';
    }
    prevArea.innerHTML = html;
    prevArea.__data = data;
  }

  btnImpor.addEventListener('click', function () {
    var data = prevArea.__data || [];
    if (!data.length) return;
    document.getElementById('payloadInput').value = JSON.stringify({
      rows: data, perbarui: document.getElementById('opsiPerbarui').checked
    });
    document.getElementById('formImpor').submit();
  });

  document.getElementById('btnContoh').addEventListener('click', function () {
    var csv = 'Nama Barang,Spesifikasi,Lokasi,Kondisi,Jumlah,Sumber Dana,Jenis,Keterangan\n'
      + 'Laptop Asus,Core i5 RAM 8GB SSD 512GB,Ruang Guru,Baik,2,BOS,Bergerak,Untuk administrasi\n'
      + 'Kursi Siswa,Kayu jati,Ruang Kelas VII-A,Baik,40,BOS,Tetap,\n'
      + 'Proyektor,3000 lumens,Ruang Kelas VII-B,Rusak Ringan,1,Komite,Bergerak,Lampu redup\n';
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'contoh-data-barang.csv';
    a.click();
  });
})();
</script>
<?php
page_bottom();
