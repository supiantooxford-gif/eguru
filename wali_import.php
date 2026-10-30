<?php
/**
 * IMPOR DATA WALI KELAS dari Excel/CSV (dibaca di peramban dengan SheetJS, dikirim sebagai JSON).
 *
 * Permintaan pemilik (seperti Pembina OSIM): struktur organisasi kelas & program kerja wali kelas
 * menyediakan "impor dan ekspor" — berkasnya tidak disimpan di server.
 *   ?kelas=ID&bagian=struktur  → kolom: Jabatan (wajib), Nama
 *   ?kelas=ID&bagian=program   → kolom: Nama Program (wajib), Waktu Pelaksanaan, Target,
 *                                        Tahun Ajaran, Terlaksana, Keterangan
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/wali.php';

$bagian = (string) ($_POST['bagian'] ?? $_GET['bagian'] ?? 'struktur');
if (!in_array($bagian, ['struktur', 'program'], true)) {
    $bagian = 'struktur';
}
$tugas = tugas_menu_by_kode(WALI_KODE_MENU);
if (!$tugas) {
    require_login();
    flash('Menu Wali Kelas belum dibuat. Hubungi Super Admin.', 'err');
    redirect('index.php');
}
require_tugas_menu_ubah($tugas, 'data Wali Kelas');   // hanya guru wali/pengelola terkait yang boleh mengimpor
require_sekolah();
if (is_pengawas()) {
    tolak403('Akun Pengawas Sekolah/Madrasah hanya dapat melihat dan mencetak data wali kelas.');
}
$kelas = wali_kelas_konteks((int) ($_POST['kelas'] ?? $_GET['kelas'] ?? 0));
if (!$kelas) {
    flash('Belum ada kelas yang bisa diimpor.', 'warn');
    redirect('tugas.php?kode=' . WALI_KODE_MENU);
}
$kelasId = (int) $kelas['id'];
$isStruktur = $bagian === 'struktur';
$ringkasan = null;
$galat = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_guard();
    $data = json_decode((string) ($_POST['payload'] ?? ''), true);
    $rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
    $perbarui = !empty($data['perbarui']);
    $tambah = 0; $ubah = 0; $lewat = 0; $tglGagal = 0; $terSalah = 0;

    if (!$rows) {
        $galat[] = 'Tidak ada baris data yang dikirim. Pastikan berkas sudah dipilih dan kolom sudah dipetakan.';
    } else {
        $now = date('Y-m-d H:i:s');
        $urutan = (int) val('SELECT COALESCE(MAX(urutan), 0) FROM ' . ($isStruktur ? 'wali_struktur' : 'wali_program')
            . ' WHERE sekolah_id = ? AND kelas_id = ?', [sekolah_id(), $kelasId], 0);
        db()->exec('BEGIN IMMEDIATE');
        try {
            foreach ($rows as $i => $r) {
                $baris = (int) ($r['_baris'] ?? ($i + 1));
                if ($isStruktur) {
                    $jabatan = wali_pangkas(trim((string) ($r['jabatan'] ?? '')), WALI_TEKS_MAKS);
                    if ($jabatan === '') {
                        $lewat++;
                        if (count($galat) < 8) {
                            $galat[] = 'Baris ' . $baris . ': Jabatan / Nama Seksi kosong, dilewati.';
                        }
                        continue;
                    }
                    $nama = wali_pangkas(trim((string) ($r['nama'] ?? '')), WALI_TEKS_MAKS);
                    // Kunci pembanding = JABATAN + NAMA (jabatan yang sama boleh dipakai berkali-kali).
                    $ada = one('SELECT * FROM wali_struktur
                                WHERE lower(jabatan) = lower(?) AND lower(IFNULL(nama, "")) = lower(?)
                                  AND sekolah_id = ? AND kelas_id = ?',
                        [$jabatan, $nama, sekolah_id(), $kelasId]);
                    if ($ada) {
                        if (!$perbarui) {
                            $lewat++;
                            continue;
                        }
                        q('UPDATE wali_struktur SET nama = CASE WHEN ? <> "" THEN ? ELSE nama END, diubah = ?
                             WHERE id = ? AND sekolah_id = ? AND kelas_id = ?',
                            [$nama, $nama, $now, (int) $ada['id'], sekolah_id(), $kelasId]);
                        $ubah++;
                    } else {
                        $urutan += 10;
                        q('INSERT INTO wali_struktur (sekolah_id, kelas_id, jabatan, nama, bagan, urutan, dibuat)
                           VALUES (?,?,?,?,?,?,?)', [sekolah_id(), $kelasId, $jabatan, $nama, 0, $urutan, $now]);
                        $tambah++;
                    }
                    continue;
                }

                // --- Program Kerja
                $namaProgram = wali_pangkas(trim((string) ($r['nama'] ?? '')), WALI_TEKS_MAKS);
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
                $target = wali_pangkas(trim((string) ($r['target'] ?? '')), WALI_URAIAN_MAKS);
                $ket = wali_pangkas(trim((string) ($r['keterangan'] ?? '')), WALI_URAIAN_MAKS);
                $ta = wali_pangkas(trim((string) ($r['ta'] ?? '')), 40);
                $terMentah = trim((string) ($r['terlaksana'] ?? ''));
                $ter = wali_terlaksana_sah($terMentah);
                if ($terMentah !== '' && $ter === '') {
                    $terSalah++;
                }
                $ada = one('SELECT * FROM wali_program WHERE lower(nama) = lower(?) AND sekolah_id = ? AND kelas_id = ?',
                    [$namaProgram, sekolah_id(), $kelasId]);
                if ($ada) {
                    if (!$perbarui) {
                        $lewat++;
                        continue;
                    }
                    q('UPDATE wali_program SET waktu = ?, target = ?, ta = ?, terlaksana = ?, keterangan = ?, diubah = ?
                         WHERE id = ? AND sekolah_id = ? AND kelas_id = ?',
                        [$waktu, $target, $ta, $ter, $ket, $now, (int) $ada['id'], sekolah_id(), $kelasId]);
                    $ubah++;
                } else {
                    $urutan += 10;
                    q('INSERT INTO wali_program (sekolah_id, kelas_id, nama, waktu, target, ta, terlaksana,
                         keterangan, urutan, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?)',
                        [sekolah_id(), $kelasId, $namaProgram, $waktu, $target, $ta, $ter, $ket, $urutan, $now]);
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
                . 'diimpor tanpa tanggal. Contoh format: 2026-07-14 atau 14/07/2026.';
        }
        if ($terSalah > 0) {
            $galat[] = $terSalah . ' baris memakai nilai Terlaksana di luar pilihan (Ya / Tidak) — nilainya '
                . 'dikosongkan sehingga dianggap belum ditentukan.';
        }
    }
}

$jumlahAda = (int) val($isStruktur
    ? 'SELECT COUNT(*) FROM wali_struktur WHERE sekolah_id = ? AND kelas_id = ?'
    : 'SELECT COUNT(*) FROM wali_program WHERE sekolah_id = ? AND kelas_id = ?', [sekolah_id(), $kelasId], 0);
$judul = $isStruktur ? 'Struktur Organisasi Kelas' : 'Program Kerja Wali Kelas';

page_top('Impor ' . $judul, 'Wali Kelas &middot; ' . (string) $kelas['nama']);
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('upload', 16) ?> Impor <?= e($judul) ?> (Excel / CSV)</h2>
      <p>Kelas <b><?= e((string) $kelas['nama']) ?></b> &middot; unggah berkas, cocokkan kolomnya, lihat
        pratinjau, lalu simpan. Berkas tidak disimpan di server.</p>
    </div>
    <div class="flex">
      <a class="btn btn-ghost" href="<?= e(wali_url($kelasId, $isStruktur ? 'struktur' : 'program')) ?>"><?= ikon('clipboard', 16) ?> Data (<?= $jumlahAda ?>)</a>
      <a class="btn btn-ghost" href="wali_import.php?kelas=<?= $kelasId ?>&bagian=<?= $isStruktur ? 'program' : 'struktur' ?>"><?= ikon('switch', 15) ?> Impor <?= $isStruktur ? 'Program Kerja' : 'Struktur Organisasi' ?></a>
    </div>
  </div>

  <?php if ($ringkasan): ?>
    <div class="alert alert-ok"><?= ikon('check', 16) ?>
      <span>Impor selesai dari <?= (int) $ringkasan['total'] ?> baris: <b><?= (int) $ringkasan['tambah'] ?></b> baru,
        <b><?= (int) $ringkasan['ubah'] ?></b> diperbarui, <b><?= (int) $ringkasan['lewat'] ?></b> dilewati.</span></div>
  <?php endif; ?>
  <?php foreach ($galat as $g): ?>
    <div class="alert alert-warn"><?= ikon('alert', 16) ?><span><?= e($g) ?></span></div>
  <?php endforeach; ?>

  <div class="alert alert-ok" style="background:rgba(201,162,39,.08);border-color:rgba(201,162,39,.5)">
    <?= ikon('doc', 16) ?>
    <?php if ($isStruktur): ?>
      <span>Kolom: <b>Jabatan</b> (wajib) &amp; <b>Nama</b>. Satu baris = satu jabatan/seksi/anggota, jadi
        baris <i>Seksi Kebersihan</i>, <i>Anggota</i>, dan seterusnya ditulis sebagai baris tersendiri.</span>
    <?php else: ?>
      <span>Kolom: <b>Nama Program</b> (wajib), Waktu Pelaksanaan (tanggal), Target, Tahun Ajaran,
        <b>Terlaksana</b> (Ya/Tidak), Keterangan.</span>
    <?php endif; ?>
  </div>

  <div class="grid g3" style="gap:16px">
    <div class="butir" style="margin:0">
      <div class="butir-head"><div class="butir-no">1</div>
        <div><div class="butir-komponen">Pilih berkas</div>
          <div class="butir-indikator">Format .xlsx, .xls, atau .csv.</div></div></div>
      <div class="mt16"><input type="file" id="fileInput" accept=".xlsx,.xls,.csv,text/csv" style="padding:9px"></div>
      <div class="hint" id="fileInfo">Belum ada berkas dipilih.</div>
      <div class="mt8"><select id="sheetSelect" style="display:none"></select></div>
    </div>
    <div class="butir" style="margin:0">
      <div class="butir-head"><div class="butir-no">2</div>
        <div><div class="butir-komponen">Cocokkan kolom</div>
          <div class="butir-indikator">Judul kolom ditebak otomatis.</div></div></div>
      <div class="mt16 grid" style="gap:10px">
        <div><label class="f" for="headerRow">Baris judul kolom</label><select id="headerRow"></select></div>
        <div id="mapArea" class="grid g2" style="gap:10px"></div>
      </div>
    </div>
    <div class="butir" style="margin:0">
      <div class="butir-head"><div class="butir-no">3</div>
        <div><div class="butir-komponen">Pilihan</div>
          <div class="butir-indikator">Baris yang sama dapat diperbarui.</div></div></div>
      <div class="mt16 grid" style="gap:10px">
        <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600">
          <input type="checkbox" id="opsiPerbarui" checked style="width:16px;height:16px;accent-color:var(--accent)">
          Perbarui baris yang sudah ada
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
  <div id="prevArea"><div class="empty"><?= ikon('upload', 34) ?><h3>Belum ada berkas</h3>
    <p>Pilih berkas Excel/CSV untuk melihat pratinjau.</p></div></div>
</div>

<form method="post" id="formImpor" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="bagian" value="<?= e($bagian) ?>">
  <input type="hidden" name="kelas" value="<?= $kelasId ?>">
  <input type="hidden" name="payload" id="payloadInput">
</form>

<script src="assets/vendor/xlsx.full.min.js"></script>
<script>
(function () {
  var STRUKTUR = <?= $isStruktur ? 'true' : 'false' ?>;
  var FIELDS = STRUKTUR
    ? [ { k: 'jabatan', l: 'Jabatan / Nama Seksi *', keys: ['jabatan', 'seksi', 'posisi'] },
        { k: 'nama',    l: 'Nama', keys: ['nama'] } ]
    : [ { k: 'nama',        l: 'Nama Program *', keys: ['namaprogram', 'program', 'kegiatan', 'nama'] },
        { k: 'waktu',       l: 'Waktu Pelaksanaan', keys: ['waktupelaksanaan', 'waktu', 'tanggal'] },
        { k: 'target',      l: 'Target', keys: ['target', 'sasaran'] },
        { k: 'ta',          l: 'Tahun Ajaran', keys: ['tahunajaran', 'ta'] },
        { k: 'terlaksana',  l: 'Terlaksana (Ya/Tidak)', keys: ['terlaksana', 'realisasi', 'status'] },
        { k: 'keterangan',  l: 'Keterangan', keys: ['keterangan', 'ket', 'catatan'] } ];
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
        var o = document.createElement('option'); o.value = n; o.textContent = 'Sheet: ' + n; sSelect.appendChild(o);
      });
      sSelect.style.display = wb.SheetNames.length > 1 ? '' : 'none';
      loadSheet(wb.SheetNames[0]);
    };
    fr.readAsArrayBuffer(f);
  });

  sSelect.addEventListener('change', function () { loadSheet(sSelect.value); });
  hSelect.addEventListener('change', function () { map = {}; render(); });

  function loadSheet(name) {
    rows = XLSX.utils.sheet_to_json(wb.Sheets[name], { header: 1, raw: false, defval: '', blankrows: false });
    if (!rows.length) { prevArea.innerHTML = '<div class="empty"><h3>Sheet kosong</h3></div>'; btnImpor.disabled = true; return; }
    var guess = 0;
    for (var i = 0; i < Math.min(rows.length, 12); i++) {
      if (rows[i].filter(function (c) { return String(c).trim() !== ''; }).length >= 2) { guess = i; break; }
    }
    hSelect.innerHTML = '';
    for (var j = 0; j < Math.min(rows.length, 15); j++) {
      var o = document.createElement('option'); o.value = j;
      o.textContent = 'Baris ' + (j + 1) + ': ' + (rows[j].slice(0, 5).join(' | ').substring(0, 80) || '(kosong)');
      hSelect.appendChild(o);
    }
    hSelect.value = guess; map = {}; render();
  }

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
      wrap.appendChild(lab); wrap.appendChild(sel); mapArea.appendChild(wrap);
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
      var o = { _baris: i + Number(hSelect.value) + 2 };
      FIELDS.forEach(function (fd) { o[fd.k] = valueOf(r, fd.k); });
      if (o[STRUKTUR ? 'jabatan' : 'nama'] === '') return;
      data.push(o);
    });
    prevInfo.textContent = data.length + ' baris siap diimpor';
    btnImpor.disabled = data.length === 0;
    if (!data.length) {
      prevArea.innerHTML = '<div class="empty"><h3>Belum ada baris data</h3><p>Periksa baris judul kolom dan pemetaan kolomnya.</p></div>';
      return;
    }
    var html = '<div class="table-wrap"><table class="tbl tbl-impor"><thead><tr><th>#</th>';
    FIELDS.forEach(function (fd) { html += '<th>' + esc(fd.l) + '</th>'; });
    html += '<th class="ctr">Status</th></tr></thead><tbody>';
    data.slice(0, 25).forEach(function (o, i) {
      html += '<tr><td>' + (i + 1) + '</td>';
      FIELDS.forEach(function (fd) { html += '<td>' + (o[fd.k] === '' ? '-' : esc(o[fd.k])) + '</td>'; });
      html += '<td class="ctr"><span class="pill pill-ok">Siap</span></td></tr>';
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
      rows: data, perbarui: document.getElementById('opsiPerbarui').checked, bagian: STRUKTUR ? 'struktur' : 'program'
    });
    document.getElementById('formImpor').submit();
  });

  document.getElementById('btnContoh').addEventListener('click', function () {
    var csv = STRUKTUR
      ? 'Jabatan,Nama\nWali Kelas,Wali Kelasnya\nKetua Kelas,Ahmad\nWakil Ketua Kelas,Siti\n'
        + 'Sekretaris,Budi,,\nBendahara,Rina\nSeksi Kebersihan,Dedi\nAnggota,Rudi\n'
      : 'Nama Program,Waktu Pelaksanaan,Target,Tahun Ajaran,Terlaksana,Keterangan\n'
        + 'Pertemuan Wali Murid,2026-07-14,32 siswa + orang tua,2026/2027,Ya,di ruang kelas\n'
        + 'Bakti Sosial Kelas,14/03/2027,Seluruh siswa,2026/2027,Tidak,menunggu izin\n';
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'contoh-wali-' + (STRUKTUR ? 'struktur' : 'program') + '.csv';
    a.click();
  });
})();
</script>
<?php
page_bottom();
