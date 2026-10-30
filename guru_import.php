<?php
/**
 * Impor data guru dari Excel/CSV (dibaca di browser dengan SheetJS, dikirim sebagai JSON).
 * Nama kelas pada berkas dicocokkan dengan Data Kelas (harus sudah ada) dan mata pelajaran baru
 * DIBUAT OTOMATIS agar impor tidak terhenti. **Tugas tambahan TIDAK dibuat otomatis** (sejak skema
 * v30): daftarnya satu, diisi Super Admin lewat menu Kelola Tugas Tambahan — nama yang tidak ada di
 * sana dilewati & dilaporkan.
 *
 * Nama pengguna & kata sandi akun guru mengikuti aturan aplikasi: nama pengguna = KATA AWAL nama
 * guru (bila sudah dipakai ditambah angka) dan kata sandi awal `guru123` — lewat helper bersama
 * guru_username_unik() + guru_sandi_awal() (jangan menulis ulang logikanya di sini).
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
    $buatAkun = !empty($data['buat_akun']);

    $tambah = 0; $ubah = 0; $lewat = 0; $mapelBaru = 0; $akunBaru = 0; $kelasLewat = []; $tugasLewat = [];

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
                        $galat[] = 'Baris ' . $baris . ': nama guru kosong, dilewati.';
                    }
                    continue;
                }
                if (strlen($nama) > 160) {
                    $nama = substr($nama, 0, 160);
                }
                $nip = nip_bersih($r['nip'] ?? '');       // "-" atau kosong dianggap belum punya NIP
                $nuptk = nip_bersih($r['nuptk'] ?? '');
                $jk = strtoupper(trim((string) ($r['jenis_kelamin'] ?? '')));
                $jk = in_array(substr($jk, 0, 1), ['L', 'P'], true) ? substr($jk, 0, 1) : '';
                $jabatan = trim((string) ($r['jabatan'] ?? ''));
                $telepon = trim((string) ($r['telepon'] ?? ''));
                $email = trim((string) ($r['email'] ?? ''));
                if ($email !== '' && strpos($email, '@') === false) {
                    $email = '';
                }

                $ada = $nip !== ''
                    ? one('SELECT * FROM guru WHERE nip = ? AND sekolah_id = ?', [$nip, sekolah_id()])
                    : one('SELECT * FROM guru WHERE lower(nama) = lower(?) AND sekolah_id = ?', [$nama, sekolah_id()]);

                if ($ada) {
                    if (!$perbarui) {
                        $lewat++;
                        continue;
                    }
                    q('UPDATE guru SET nama=?, nuptk=CASE WHEN ? <> "" THEN ? ELSE nuptk END,
                         jenis_kelamin=CASE WHEN ? <> "" THEN ? ELSE jenis_kelamin END,
                         jabatan=CASE WHEN ? <> "" THEN ? ELSE jabatan END,
                         telepon=CASE WHEN ? <> "" THEN ? ELSE telepon END,
                         email=CASE WHEN ? <> "" THEN ? ELSE email END,
                         nip=CASE WHEN ? <> "" THEN ? ELSE nip END, aktif=1
                       WHERE id=? AND sekolah_id=?',
                        [$nama, $nuptk, $nuptk, $jk, $jk, $jabatan, $jabatan, $telepon, $telepon,
                         $email, $email, $nip, $nip, (int) $ada['id'], sekolah_id()]);
                    $gid = (int) $ada['id'];
                    $ubah++;
                } else {
                    q('INSERT INTO guru (sekolah_id, nama, nip, nuptk, jenis_kelamin, jabatan, telepon, email, aktif, dibuat)
                       VALUES (?,?,?,?,?,?,?,?,1,?)',
                        [sekolah_id(), $nama, $nip, $nuptk, $jk, $jabatan, $telepon, $email, $now]);
                    $gid = (int) db()->lastInsertId();
                    $tambah++;
                }

                // ---- mata pelajaran (dibuat otomatis bila belum ada) ----
                foreach (pecah_daftar($r['mapel'] ?? '') as $namaMapel) {
                    $mid = db_val(db(), 'SELECT id FROM mapel WHERE sekolah_id = ? AND (lower(nama) = lower(?) OR lower(kode) = lower(?))',
                        [sekolah_id(), $namaMapel, $namaMapel]);
                    if (!$mid) {
                        db()->prepare('INSERT INTO mapel (sekolah_id, kode, nama, kelompok, urutan, aktif)
                                       VALUES (?, "", ?, "Hasil impor", (SELECT COALESCE(MAX(urutan),0)+10 FROM mapel WHERE sekolah_id = ?), 1)')
                            ->execute([sekolah_id(), $namaMapel, sekolah_id()]);
                        $mid = (int) db()->lastInsertId();
                        $mapelBaru++;
                    }
                    q('INSERT OR IGNORE INTO guru_mapel (guru_id, mapel_id) VALUES (?,?)', [$gid, (int) $mid]);
                }

                // ---- kelas (harus sudah ada di Data Kelas) ----
                foreach (pecah_daftar($r['kelas'] ?? '') as $namaKelas) {
                    $kid = db_val(db(), 'SELECT id FROM kelas WHERE lower(nama) = lower(?) AND sekolah_id = ?', [$namaKelas, sekolah_id()]);
                    if ($kid) {
                        q('INSERT OR IGNORE INTO guru_kelas (guru_id, kelas_id) VALUES (?,?)', [$gid, (int) $kid]);
                    } elseif (!in_array($namaKelas, $kelasLewat, true)) {
                        $kelasLewat[] = $namaKelas;
                    }
                }

                // ---- tugas tambahan (HANYA dari daftar Tugas Tambahan yang diisi Super Admin) ----
                // Sejak skema v30 operator/kepala TIDAK lagi menambah tugas: nama yang tidak ada
                // pada daftar (atau belum diaktifkan) dilewati & dilaporkan, supaya daftar tetap
                // satu sumber dan tidak tercemar nama asal-asalan dari berkas impor.
                foreach (pecah_daftar($r['tugas'] ?? '') as $namaTugas) {
                    $tid = db_val(db(), 'SELECT id FROM tugas_menu WHERE lower(nama) = lower(?) AND aktif = 1',
                        [$namaTugas]);
                    if (!$tid) {
                        if (!in_array($namaTugas, $tugasLewat, true)) {
                            $tugasLewat[] = $namaTugas;
                        }
                        continue;
                    }
                    q('INSERT OR IGNORE INTO guru_tugas (guru_id, tugas_id) VALUES (?,?)', [$gid, (int) $tid]);
                }

                // ---- akun login (nama pengguna = kata awal nama guru, sandi awal guru123) ----
                if ($buatAkun && !one('SELECT id FROM users WHERE guru_id = ?', [$gid])) {
                    $baru = ['nama' => $nama, 'nip' => $nip];
                    $username = guru_username_unik($baru);
                    $sandi = guru_sandi_awal($baru);
                    q('INSERT INTO users (sekolah_id, username, password_hash, nama, role, guru_id, aktif, dibuat) VALUES (?,?,?,?,?,?,1,?)',
                        [sekolah_id(), $username, password_hash($sandi, PASSWORD_DEFAULT), $nama, 'guru', $gid, $now]);
                    $akunBaru++;
                }
            }
            db()->exec('COMMIT');
        } catch (Throwable $ex) {
            db()->exec('ROLLBACK');
            throw $ex;
        }
        $ringkasan = ['tambah' => $tambah, 'ubah' => $ubah, 'lewat' => $lewat, 'mapel' => $mapelBaru,
                      'akun' => $akunBaru, 'kelas_lewat' => count($kelasLewat),
                      'tugas_lewat' => array_values($tugasLewat), 'total' => count($rows)];
        if ($kelasLewat) {
            $galat[] = 'Nama kelas berikut tidak ada di Data Kelas: ' . implode(', ', array_slice($kelasLewat, 0, 8))
                     . (count($kelasLewat) > 8 ? ' …' : '') . '. Tambahkan kelasnya lebih dahulu, lalu impor ulang.';
        }
        if ($tugasLewat) {
            $galat[] = 'Nama tugas tambahan berikut tidak ada pada daftar Tugas Tambahan yang diisi '
                     . 'Super Admin (atau belum diaktifkan), jadi dilewati: '
                     . implode(', ', array_slice($tugasLewat, 0, 8))
                     . (count($tugasLewat) > 8 ? ' …' : '') . '. Minta Super Admin menambahkannya lewat menu '
                     . 'Kelola Tugas Tambahan, lalu impor ulang.';
        }
    }
}

$totalGuru = (int) val('SELECT COUNT(*) FROM guru WHERE sekolah_id = ?', [sekolah_id()], 0);

page_top('Impor Data Guru');
?>
<div class="card">
  <div class="card-head">
    <div>
      <h2>Impor Data Guru dari Excel / CSV</h2>
      <p>Unggah berkas, cocokkan kolomnya, lihat pratinjau, lalu simpan. Berkas tidak disimpan di server.</p>
    </div>
    <a class="btn btn-ghost" href="guru.php"><?= ikon('users', 16) ?> Data Guru (<?= $totalGuru ?>)</a>
  </div>

  <?php if ($ringkasan): ?>
    <div class="alert alert-ok">
      <?= ikon('check', 16) ?>
      <span>Impor selesai dari <?= (int) $ringkasan['total'] ?> baris: <b><?= (int) $ringkasan['tambah'] ?></b> guru baru,
      <b><?= (int) $ringkasan['ubah'] ?></b> diperbarui, <b><?= (int) $ringkasan['lewat'] ?></b> dilewati<?= $ringkasan['mapel'] > 0 ? ', <b>' . (int) $ringkasan['mapel'] . '</b> mata pelajaran baru dibuat' : '' ?><?= $ringkasan['akun'] > 0 ? ', <b>' . (int) $ringkasan['akun'] . '</b> akun login dibuat' : '' ?><?= $ringkasan['tugas_lewat'] ? ', <b>' . count($ringkasan['tugas_lewat']) . '</b> nama tugas tambahan dilewati' : '' ?>.</span>
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
        <div><div class="butir-komponen">Cocokkan kolom</div><div class="butir-indikator">Pilih kolom yang berisi data guru.</div></div>
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
        <div><div class="butir-komponen">Pilihan impor</div><div class="butir-indikator">Mata pelajaran baru dibuat otomatis; tugas tambahan memakai daftar yang diisi Super Admin.</div></div>
      </div>
      <div class="mt16 grid" style="gap:10px">
        <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600">
          <input type="checkbox" id="opsiPerbarui" checked style="width:16px;height:16px;accent-color:var(--accent)"> Perbarui guru yang NIP-nya sudah ada
        </label>
        <label class="f" style="display:flex;gap:9px;align-items:flex-start;font-weight:600">
          <input type="checkbox" id="opsiAkun" style="width:16px;height:16px;accent-color:var(--accent);margin-top:2px">
          <span>Sekaligus buatkan akun login guru<br>
            <span class="hint">Nama pengguna = <b>nama awal guru</b> (bila sudah dipakai ditambah angka),
            kata sandi awal <b>guru123</b>.</span></span>
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
    { k: 'nama',          l: 'Nama Guru *',         keys: ['nama', 'namaguru', 'guru', 'name'] },
    { k: 'nip',           l: 'NIP / NUPTK',         keys: ['nip', 'nuptk', 'nik'] },
    { k: 'jenis_kelamin', l: 'Jenis Kelamin',       keys: ['jeniskelamin', 'jk', 'gender', 'lp'] },
    { k: 'jabatan',       l: 'Jabatan',             keys: ['jabatan', 'posisi', 'status'] },
    { k: 'mapel',         l: 'Mata Pelajaran',      keys: ['mapel', 'matapelajaran', 'pelajaran', 'mata'] },
    { k: 'kelas',         l: 'Kelas Diajar',        keys: ['kelas', 'rombel', 'kelasdiajar'] },
    { k: 'tugas',         l: 'Tugas Tambahan',      keys: ['tugastambahan', 'tugas', 'wakasek', 'pembina'] },
    { k: 'telepon',       l: 'No. HP',              keys: ['telepon', 'hp', 'wa', 'whatsapp', 'nohp', 'telp'] },
    { k: 'email',         l: 'Email',               keys: ['email', 'surel'] }
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
        nama: valueOf(r, 'nama'), nip: valueOf(r, 'nip'), jenis_kelamin: valueOf(r, 'jenis_kelamin'),
        jabatan: valueOf(r, 'jabatan'), mapel: valueOf(r, 'mapel'), kelas: valueOf(r, 'kelas'),
        tugas: valueOf(r, 'tugas'), telepon: valueOf(r, 'telepon'), email: valueOf(r, 'email'),
        _baris: i + Number(hSelect.value) + 2
      };
      if (o.nama === '' && o.nip === '') return;
      data.push(o);
    });
    prevInfo.textContent = data.length + ' baris siap diimpor';
    btnImpor.disabled = data.length === 0;
    if (!data.length) {
      prevArea.innerHTML = '<div class="empty"><h3>Belum ada baris data</h3><p>Periksa baris judul kolom dan pemetaan kolom Nama Guru.</p></div>';
      return;
    }
    var html = '<div class="table-wrap"><table class="tbl"><thead><tr><th>#</th><th>Nama</th><th>NIP</th><th>JK</th><th>Jabatan</th><th>Mata Pelajaran</th><th>Kelas</th><th>Tugas Tambahan</th><th>No. HP</th><th class="ctr">Status</th></tr></thead><tbody>';
    data.slice(0, 25).forEach(function (o, i) {
      html += '<tr><td>' + (i + 1) + '</td><td><b>' + esc(o.nama || '-') + '</b></td><td>' + esc(o.nip || '-') + '</td>'
        + '<td>' + esc(o.jenis_kelamin || '-') + '</td><td>' + esc(o.jabatan || '-') + '</td><td>' + esc(o.mapel || '-') + '</td>'
        + '<td>' + esc(o.kelas || '-') + '</td><td>' + esc(o.tugas || '-') + '</td><td>' + esc(o.telepon || '-') + '</td>'
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
      buat_akun: document.getElementById('opsiAkun').checked
    });
    document.getElementById('formImpor').submit();
  });

  document.getElementById('btnContoh').addEventListener('click', function () {
    var csv = 'Nama,NIP,Jenis Kelamin,Jabatan,Mata Pelajaran,Kelas,Tugas Tambahan,No HP,Email\n'
      + 'Ahmad Fauzi,199001012015011001,L,Guru Mata Pelajaran,Matematika,VII-A,Wakasek Kesiswaan,081200000010,ahmad@example.com\n'
      + 'Nur Aisyah,199202022016012002,P,Guru Mata Pelajaran,Bahasa Indonesia,VII-A,Pembina Pramuka,081200000011,nur@example.com\n';
    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'contoh-data-guru.csv';
    a.click();
  });
})();
</script>
<?php
page_bottom();
