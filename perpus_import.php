<?php
/**
 * IMPOR LAPORAN PERPUSTAKAAN DARI e-PERPUS (menu Tugas Tambahan "Ka. Perpustakaan").
 *
 * Permintaan pemilik: "Semua laporan ini di impor dari hasil unduhan aplikasi e-perpus."
 *
 * Berkas laporan diunduh dari e-PERPUS (tombol CSV pada menu Laporan, atau Ekspor Data Anggota),
 * dibaca DI PERAMBAN dengan SheetJS (xlsx/xls/csv), lalu dikirim sebagai JSON (`payload`).
 * Berkasnya TIDAK disimpan di server.
 *
 * Berbeda dengan impor Data Barang/Siswa, di sini TIDAK ADA pemetaan ke kolom tetap: judul kolom
 * laporan e-PERPUS dipakai apa adanya sehingga semua kolom ikut tersimpan & tercetak utuh.
 * Yang dipilih pengguna hanya BARIS JUDUL KOLOM (berkas e-PERPUS kadang punya baris judul di atas).
 *
 * Impor ulang untuk jenis yang sama MENGGANTIKAN data yang lama (satu himpunan data per sekolah).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/perpus.php';

$tugas = tugas_menu_by_kode(PERPUS_KODE_MENU);
if (!$tugas) {
    require_login();
    flash('Menu Ka. Perpustakaan belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu_ubah($tugas, 'laporan perpustakaan');   // hanya guru yang ditugaskan (+ Super Admin) yang boleh mengimpor
require_sekolah();
if (is_pengawas()) {
    tolak403('Akun Pengawas Sekolah/Madrasah hanya dapat melihat dan mencetak laporan perpustakaan.');
}

$ringkasan = null;
$galat = [];
$terlaluBesar = false;

$jenisMinta = (string) ($_POST['jenis'] ?? $_GET['jenis'] ?? '');
$jenisSah = perpus_jenis_ada($jenisMinta);
$jenis = $jenisSah ? $jenisMinta : (string) array_key_first(PERPUS_JENIS);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    // PHP MEMBUANG seluruh $_POST bila ukuran kiriman melebihi post_max_size — tanpa pemeriksaan ini
    // gejalanya "tidak terjadi apa-apa" (pelajaran dari unggahan foto bukti supervisi).
    $panjang = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($panjang > 0 && !$_POST) {
        $terlaluBesar = true;
        $galat[] = 'Ukuran kiriman melebihi batas server. Impor berkasnya sebagian, atau pakai berkas CSV '
            . 'hasil ekspor e-PERPUS (bukan Excel yang isinya berisi gambar).';
    } elseif (!$jenisSah) {
        // Jenis yang dikirim tidak dikenal: JANGAN menebak — impor ke laporan lain justru menimpa data
        // yang sudah ada pada laporan yang salah.
        $galat[] = 'Jenis laporan tidak dikenal. Muat ulang halaman impor dari tab laporan yang benar.';
    } else {
        $data = json_decode((string) ($_POST['payload'] ?? ''), true);
        $header = is_array($data['header'] ?? null) ? $data['header'] : [];
        $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
        $berkas = (string) ($data['berkas'] ?? '');
        if (!$header || !$rows) {
            $galat[] = 'Tidak ada data yang dikirim. Pastikan berkas sudah dipilih, baris judul kolom sudah '
                . 'benar, dan pratinjau menampilkan baris data.';
        } else {
            $hasil = perpus_simpan($jenis, $berkas, $header, $rows);
            if (!$hasil['ok']) {
                $galat[] = $hasil['galat'];
            } else {
                $ringkasan = $hasil;
                if ($hasil['dilewati'] > 0) {
                    $galat[] = (int) $hasil['dilewati'] . ' baris tidak diimpor karena melebihi batas '
                        . (int) PERPUS_MAKS_BARIS . ' baris per laporan. Impor sisanya pada berkas terpisah, '
                        . 'atau pakai saringan tanggal di e-PERPUS agar laporannya lebih pendek.';
                }
                if ($hasil['kosong'] > 0) {
                    $galat[] = (int) $hasil['kosong'] . ' baris kosong pada berkas dilewati.';
                }
                if ((int) $hasil['kolom_berkas'] > PERPUS_MAKS_KOLOM) {
                    $galat[] = 'Berkas ini punya ' . (int) $hasil['kolom_berkas'] . ' kolom — hanya '
                        . (int) PERPUS_MAKS_KOLOM . ' kolom pertama yang diimpor.';
                }
            }
        }
    }
}

$tabel = perpus_data($jenis);
$adaData = $tabel['laporan'] !== null && $tabel['total'] > 0;
$kolomEperpus = perpus_kolom_eperpus($jenis);

page_top('Impor ' . perpus_jenis_nama($jenis), 'Ka. Perpustakaan');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('upload', 16) ?> Impor <?= e(perpus_jenis_nama($jenis)) ?> dari e-PERPUS</h2>
      <p>Unggah berkas laporan hasil unduhan aplikasi e-PERPUS, periksa pratinjaunya, lalu impor.
        Berkas tidak disimpan di server.</p>
    </div>
    <a class="btn btn-ghost" href="<?= e(perpus_url($jenis)) ?>"><?= ikon('prev', 16) ?> Kembali ke Laporan</a>
  </div>

  <?php if ($ringkasan): ?>
    <div class="alert alert-ok">
      <?= ikon('check', 16) ?>
      <span>Impor <b><?= e(perpus_jenis_nama($jenis)) ?></b> berhasil:
        <b><?= (int) $ringkasan['baris'] ?></b> baris &times; <b><?= (int) $ringkasan['kolom'] ?></b> kolom
        <?= $ringkasan['kosong'] > 0 ? ' (' . (int) $ringkasan['kosong'] . ' baris kosong dilewati)' : '' ?>.
        Data lama untuk laporan ini sudah digantikan.</span>
    </div>
    <div class="flex mb16" style="flex-wrap:wrap;gap:10px">
      <a class="btn btn-primary" href="<?= e(perpus_url($jenis)) ?>"><?= ikon('eye', 16) ?> Lihat <?= e(perpus_jenis_nama($jenis)) ?></a>
      <a class="btn btn-ghost" href="cetak_perpus.php?jenis=<?= e($jenis) ?>" target="_blank"><?= ikon('print', 16) ?> Cetak <?= e(perpus_jenis_nama($jenis)) ?></a>
    </div>
  <?php endif; ?>
  <?php foreach ($galat as $g): ?>
    <div class="alert alert-warn"><?= ikon('alert', 16) ?><span><?= e($g) ?></span></div>
  <?php endforeach; ?>

  <div class="chip-list mb16">
    <?php foreach (perpus_jenis_list() as $k => $nm): ?>
      <a class="chip <?= $k === $jenis ? 'on' : '' ?>" href="perpus_import.php?jenis=<?= e((string) $k) ?>"><?= e($nm) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="alert alert-ok" style="background:rgba(201,162,39,.08);border-color:rgba(201,162,39,.5)">
    <?= ikon('doc', 16) ?>
    <span>Judul kolom yang biasanya ada pada berkas e-PERPUS untuk laporan ini:
      <?php foreach ($kolomEperpus as $i => $k): ?><?= $i > 0 ? ', ' : '' ?><b><?= e($k) ?></b><?php endforeach; ?>.
      Judul kolom tidak perlu disamakan — semuanya dipakai apa adanya.
      <?= $adaData ? 'Impor ulang akan MENGGANTIKAN ' . (int) $tabel['total'] . ' baris yang sudah ada.' : '' ?></span>
  </div>

  <div class="grid g3" style="gap:16px">
    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">1</div>
        <div><div class="butir-komponen">Pilih berkas</div>
          <div class="butir-indikator">Format .xlsx, .xls, atau .csv hasil unduhan e-PERPUS.</div></div>
      </div>
      <div class="mt16"><input type="file" id="fileInput" accept=".xlsx,.xls,.csv,text/csv" style="padding:9px"></div>
      <div class="hint" id="fileInfo">Belum ada berkas dipilih.</div>
      <div class="mt8"><select id="sheetSelect" style="display:none"></select></div>
    </div>

    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">2</div>
        <div><div class="butir-komponen">Baris judul kolom</div>
          <div class="butir-indikator">Biasanya baris pertama berkas.</div></div>
      </div>
      <div class="mt16">
        <label class="f" for="headerRow">Baris judul kolom</label>
        <select id="headerRow"></select>
        <div class="hint" id="kolomInfo">Pilih berkas dulu untuk melihat judul kolomnya.</div>
      </div>
    </div>

    <div class="butir" style="margin:0">
      <div class="butir-head">
        <div class="butir-no">3</div>
        <div><div class="butir-komponen">Impor</div>
          <div class="butir-indikator">Menggantikan data laporan ini.</div></div>
      </div>
      <div class="mt16 grid" style="gap:10px">
        <span class="chip" id="prevInfo">Belum ada data</span>
        <button class="btn btn-primary" id="btnImpor" type="button" disabled><?= ikon('upload', 16) ?> Impor Sekarang</button>
        <span class="hint" id="pesanImpor">Pilih berkas dan pastikan pratinjau berisi baris data.</span>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <div><h2>Pratinjau Data</h2><p>Maksimal 25 baris pertama sebagai pemeriksaan sebelum disimpan.</p></div>
    <span class="chip"><?= e(perpus_jenis_nama($jenis)) ?></span>
  </div>
  <div id="prevArea">
    <div class="empty"><?= ikon('upload', 34) ?><h3>Belum ada berkas</h3>
      <p>Pilih berkas Excel/CSV hasil unduhan e-PERPUS untuk melihat pratinjau.</p></div>
  </div>
</div>

<form method="post" id="formImpor" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
  <input type="hidden" name="payload" id="payloadInput">
</form>

<script src="assets/vendor/xlsx.full.min.js"></script>
<script>
(function () {
  var JENIS = <?= json_encode($jenis) ?>;
  var LABEL = <?= json_encode(perpus_jenis_nama($jenis), JSON_UNESCAPED_UNICODE) ?>;
  var MAKS_KOLOM = <?= (int) PERPUS_MAKS_KOLOM ?>;
  var MAKS_BARIS = <?= (int) PERPUS_MAKS_BARIS ?>;
  var SEL_MAKS = <?= (int) PERPUS_SEL_MAKS ?>;
  var MAKS_BYTE = 6 * 1024 * 1024;      // batas muatan yang boleh dikirim (post_max_size 21 MB)
  var JML_PRATINJAU = 25;

  var wb = null, rows = [], header = [], dataSiap = [];
  var fInput = document.getElementById('fileInput');
  var sSelect = document.getElementById('sheetSelect');
  var hSelect = document.getElementById('headerRow');
  var prevArea = document.getElementById('prevArea');
  var prevInfo = document.getElementById('prevInfo');
  var fileInfo = document.getElementById('fileInfo');
  var kolomInfo = document.getElementById('kolomInfo');
  var pesanImpor = document.getElementById('pesanImpor');
  var btnImpor = document.getElementById('btnImpor');

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c];
    });
  }
  function bersih(v) {
    var s = String(v == null ? '' : v).replace(/\uFEFF/g, '').replace(/[\r\n\t]+/g, ' ');
    return s.replace(/\s+/g, ' ').trim().substring(0, SEL_MAKS);
  }

  fInput.addEventListener('change', function (ev) {
    var f = ev.target.files && ev.target.files[0];
    if (!f) return;
    var fr = new FileReader();
    fr.onload = function () {
      try { wb = XLSX.read(new Uint8Array(fr.result), { type: 'array' }); }
      catch (e) { fileInfo.textContent = 'Gagal membaca berkas: ' + e.message; return; }
      fileInfo.innerHTML = '<b>' + esc(f.name) + '</b> &middot; ' + Math.round(f.size / 1024) + ' KB &middot; '
        + wb.SheetNames.length + ' sheet';
      fInput.setAttribute('data-nama', f.name);
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
  hSelect.addEventListener('change', render);

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
      o.textContent = 'Baris ' + (j + 1) + ': ' + (rows[j].slice(0, 5).map(bersih).join(' | ').substring(0, 80) || '(kosong)');
      hSelect.appendChild(o);
    }
    hSelect.value = guess;
    render();
  }

  function render() {
    var maxCol = 0;
    rows.forEach(function (r) { if (r.length > maxCol) maxCol = r.length; });
    maxCol = Math.min(maxCol, MAKS_KOLOM);
    header = [];
    var hRow = rows[Number(hSelect.value)] || [];
    for (var c = 0; c < maxCol; c++) {
      var t = bersih(hRow[c]);
      header.push(t === '' ? 'Kolom ' + (c + 1) : t);
    }
    dataSiap = [];
    for (var i = Number(hSelect.value) + 1; i < rows.length; i++) {
      var sel = [];
      var adaIsi = false;
      for (var k = 0; k < maxCol; k++) {
        var v = bersih((rows[i] || [])[k]);
        if (v !== '') adaIsi = true;
        sel.push(v);
      }
      if (adaIsi) dataSiap.push(sel);
    }
    kolomInfo.innerHTML = header.length
      ? '<b>' + header.length + ' kolom</b>: ' + esc(header.slice(0, 6).join(', ')) + (header.length > 6 ? ', …' : '')
      : 'Judul kolom tidak terbaca.';
    renderPreview();
  }

  function renderPreview() {
    var total = dataSiap.length;
    prevInfo.textContent = total + ' baris siap diimpor';
    if (!total) {
      btnImpor.disabled = true;
      pesanImpor.textContent = 'Belum ada baris data. Periksa baris judul kolom berkasnya.';
      prevArea.innerHTML = '<div class="empty"><h3>Belum ada baris data</h3>'
        + '<p>Periksa pilihan baris judul kolom di atas.</p></div>';
      return;
    }
    var muatan = JSON.stringify({ jenis: JENIS, berkas: '', header: header, rows: dataSiap });
    if (muatan.length > MAKS_BYTE) {
      btnImpor.disabled = true;
      pesanImpor.textContent = 'Berkas terlalu besar dikirim sekaligus. Pakai berkas CSV hasil ekspor '
        + 'e-PERPUS atau saring dulu laporannya di e-PERPUS.';
      prevArea.innerHTML = '<div class="empty"><h3>Berkas terlalu besar</h3><p>' + total
        + ' baris &times; ' + header.length + ' kolom melebihi batas kiriman. Pakai berkas CSV e-PERPUS '
        + '(bukan Excel bergambar) atau saring laporannya di e-PERPUS.</p></div>';
      return;
    }
    btnImpor.disabled = false;
    var html = '<div class="table-wrap"><table class="tbl tbl-perpus"><thead><tr><th class="ctr">#</th>';
    header.forEach(function (h) { html += '<th>' + esc(h) + '</th>'; });
    html += '</tr></thead><tbody>';
    dataSiap.slice(0, JML_PRATINJAU).forEach(function (sel, i) {
      html += '<tr><td class="ctr">' + (i + 1) + '</td>';
      sel.forEach(function (v) { html += '<td class="potong" title="' + esc(v) + '">' + (v === '' ? '-' : esc(v)) + '</td>'; });
      html += '</tr>';
    });
    html += '</tbody></table></div>';
    if (total > JML_PRATINJAU) {
      html += '<p class="xs mut mt8">Menampilkan ' + JML_PRATINJAU + ' dari ' + total + ' baris. Seluruh baris akan diimpor.</p>';
    }
    prevArea.innerHTML = html;
    pesanImpor.textContent = 'Pratinjau sudah benar? Klik Impor Sekarang untuk menyimpan '
      + LABEL + ' (data lama laporan ini digantikan).';
  }

  btnImpor.addEventListener('click', function () {
    if (!dataSiap.length) return;
    var muatan = {
      jenis: JENIS,
      berkas: fInput.getAttribute('data-nama') || '',
      header: header,
      rows: dataSiap
    };
    document.getElementById('payloadInput').value = JSON.stringify(muatan);
    btnImpor.disabled = true;
    document.getElementById('formImpor').submit();
  });
})();
</script>
<?php
page_bottom();
