<?php
/**
 * Dashboard e-GURU.
 * - Super Admin TANPA konteks sekolah : ringkasan lintas sekolah/madrasah
 * - Super Admin dalam konteks / kepala / operator / guru : dashboard sekolah tersebut
 *   (guru hanya melihat data kelas & mata pelajaran yang diampu)
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/dokumen.php';

$u = require_login();

// ============================================================================
// CABANG 0 — SISWA (v1.61.0)
// Permintaan pemilik: *"Pada halaman siswa hanya bisa melihat dan mencetak. Yang ada dalam halaman
// siswa adalah menampilkan Nama dan NISN (tidak bisa merubah profil), Rekapan Daftar hadir Per Mata
// Pelajaran, Rekapan Daftar Hadir di Kelas (yang diinput oleh wali kelas), Rekapan Nilai, Catatan
// Pelanggaran."* → beranda ini hanya MERINGKAS data siswa yang sedang masuk + pintasan ke empat
// halaman rekapan (masing-masing dapat dicetak).
// ============================================================================
if (is_siswa()) {
    $sw = require_siswa();
    $konteks = konteks_ajaran();
    $taS = $konteks['ta'];
    $semS = $konteks['sem'];
    $ajS = ($taS && $semS) ? ajaran_qs($taS, $semS) : '';
    $ringkas = siswa_ringkas((int) $sw['id'], (int) ($taS['id'] ?? 0), (int) ($semS['id'] ?? 0));
    $hadir = $ringkas['kehadiran'];
    $kasusTerakhir = array_slice(siswa_pelanggaran((int) $sw['id']), 0, 3);

    page_top('Beranda Siswa', (string) $sw['nama'] . ' &middot; NISN '
        . ($sw['nisn'] !== '' ? (string) $sw['nisn'] : '-')
        . ($sw['kelas_nama'] !== null ? ' &middot; ' . (string) $sw['kelas_nama'] : ''));
    ?>
    <div class="alert alert-ok mb16">
      <?= ikon('info', 16) ?>
      <span>Halaman ini menampilkan <b>data Anda sendiri</b> (nama &amp; NISN, daftar hadir, nilai, dan
        catatan pelanggaran). Semuanya hanya dapat <b>dilihat dan dicetak</b> — tidak ada yang dapat
        diubah dari akun siswa.</span>
    </div>

    <div class="grid g4">
      <div class="card stat card-tight">
        <div class="stat-ic g"><?= ikon('clipboard', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $hadir['h'] ?></div>
          <div class="stat-lbl">Kehadiran tercatat</div></div>
      </div>
      <div class="card stat card-tight">
        <div class="stat-ic s"><?= ikon('student', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= $hadir['persen'] === null ? '-' : e(r1($hadir['persen'])) . '%' ?></div>
          <div class="stat-lbl">Persentase kehadiran</div></div>
      </div>
      <div class="card stat card-tight">
        <div class="stat-ic"><?= ikon('chart', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= $ringkas['rata_nilai'] === null ? '-' : e(r1($ringkas['rata_nilai'])) ?></div>
          <div class="stat-lbl">Rata-rata nilai akhir (<?= (int) $ringkas['mapel_dinilai'] ?> mapel)</div></div>
      </div>
      <div class="card stat card-tight">
        <div class="stat-ic"><?= ikon('alert', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['pelanggaran'] ?></div>
          <div class="stat-lbl">Catatan pelanggaran</div></div>
      </div>
    </div>

    <div class="card mt16">
      <div class="card-head">
        <div>
          <h2>Rekapan Data Saya</h2>
          <p>Tahun ajaran <b><?= e($taS ? ta_nama($taS) : '-') ?></b> &middot;
            Semester <b><?= e($semS ? sem_nama($semS) : '-') ?></b></p>
        </div>
      </div>
      <div class="grid g4">
        <?php
        // Pintasan keempat halaman rekapan siswa (masing-masing punya tombol Cetak sendiri).
        $pintasanSiswa = [
            ['siswa_saya.php', 'user', 'Data Saya', 'Nama, NISN, kelas, dan identitas lain (hanya dibaca).', true],
            ['siswa_absensi.php', 'clipboard', 'Daftar Hadir', 'Rekapan per mata pelajaran & daftar hadir kelas dari wali kelas.', true],
            ['siswa_nilai.php', 'chart', 'Nilai', 'Tabel rekapan nilai seluruh mata pelajaran beserta predikatnya.', true],
            ['siswa_pelanggaran.php', 'alert', 'Pelanggaran', 'Catatan pelanggaran yang diinput guru mata pelajaran.', false],
        ];
        foreach ($pintasanSiswa as [$berkas, $iko, $judul, $ket, $pakaiPeriode]):
            $alamat = $berkas . ($pakaiPeriode ? $ajS : '');
            ?>
          <a class="card card-tight sis-kartu" href="<?= e($alamat) ?>">
            <div class="stat-ic"><?= ikon((string) $iko, 18) ?></div>
            <div>
              <div class="stat-num" style="font-size:1rem"><?= e((string) $judul) ?></div>
              <div class="stat-lbl" style="line-height:1.5"><?= e((string) $ket) ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card mt16">
      <div class="card-head">
        <div><h2>Pelanggaran Terbaru</h2><p>Tiga catatan terakhir yang tercatat atas nama Anda.</p></div>
        <a class="btn btn-ghost btn-sm" href="siswa_pelanggaran.php"><?= ikon('eye', 15) ?> Lihat semua</a>
      </div>
      <?php if (!$kasusTerakhir): ?>
        <div class="empty"><?= ikon('check', 30) ?><h3>Tidak ada catatan pelanggaran</h3>
          <p>Bagus — belum ada catatan pelanggaran atas nama Anda.</p></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Tanggal</th><th>Jenis</th><th>Catatan</th><th>Dicatat oleh</th><th class="ctr">Status</th></tr></thead>
            <tbody>
            <?php foreach ($kasusTerakhir as $k): [$jLbl, $sLbl] = siswa_pelanggaran_label((string) $k['jenis'], (string) $k['status']); ?>
              <tr>
                <td class="nowrap"><?= e(tanggal_id((string) $k['tanggal'], true)) ?></td>
                <td><?= e($jLbl) ?></td>
                <td><?= e((string) ($k['judul'] !== '' ? $k['judul'] : $k['deskripsi'])) ?></td>
                <td><?= e((string) ($k['guru_nama'] ?: '-')) ?>
                  <?php if (trim((string) $k['mapel_nama']) !== ''): ?><span class="tbl-sub"><?= e((string) $k['mapel_nama']) ?></span><?php endif; ?></td>
                <td class="ctr"><span class="pill <?= $k['status'] === 'selesai' ? 'pill-A' : ($k['status'] === 'proses' ? 'pill-C' : 'pill-D') ?>"><?= e($sLbl) ?></span></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php
    page_bottom();
    exit;
}

// ============================================================================
// CABANG 1 — Super Admin lintas sekolah
// ============================================================================
if (is_super() && !current_sekolah()) {
    $GLOBALS['__vs_istilah_nonaktif'] = true; // ringkasan lintas sekolah/madrasah

    // ========================================================================
    // AKSI PADA DAFTAR SEKOLAH/MADRASAH — tombol Nonaktifkan & Hapus (v1.72.3)
    // Permintaan pemilik: *"Tambahkan fitur non aktifkan dan fitur hapus pada yang ditandai warna
    // merah. Sebelum menghapus super admin akan dimintakan sandi (sandinya: 181288). Tujuan sandi
    // ini agar ketika tidak sengaja di klik hapus lembaga tidak langsung terhapus"*.
    //
    // Jalurnya SENGAJA memakai helper bersama `sekolah_set_aktif()` / `sekolah_hapus_permanen()`
    // (inc/helpers.php) — jalur yang SAMA dengan halaman Kelola Sekolah, jadi aturannya tidak
    // bercabang (mis. "sekolah aktif terakhir tidak boleh dinonaktifkan").
    // Sandi diperiksa di SERVER: `sandi` yang salah/kosong → lembaga TIDAK dihapus.
    // ========================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_guard();
        $aksiSek = (string) ($_POST['aksi'] ?? '');
        $idSek = (int) ($_POST['id'] ?? 0);
        $sasaran = $idSek > 0 ? sekolah_by_id($idSek) : null;
        if (in_array($aksiSek, ['nonaktifkan', 'hapus'], true) && !$sasaran) {
            flash('Sekolah/madrasah tidak ditemukan.', 'err');
            redirect('index.php');
        }
        if ($aksiSek === 'nonaktifkan') {
            $hasil = sekolah_set_aktif($idSek, false);
            if ($hasil['ok']) {
                $d = sekolah_dampak($idSek);
                flash(nama_sekolah_tampil($sasaran) . ' dinonaktifkan. Datanya tetap utuh ('
                    . $d['guru'] . ' guru, ' . $d['siswa'] . ' siswa, ' . $d['nilai']
                    . ' baris nilai) dan dapat diaktifkan kembali dari menu Kelola Sekolah.', 'warn');
            } else {
                flash($hasil['pesan'], 'err');
            }
            redirect('index.php');
        }
        if ($aksiSek === 'hapus') {
            if (!sekolah_hapus_sandi_sah($_POST['sandi'] ?? '')) {
                flash('Sandi konfirmasi salah — ' . nama_sekolah_tampil($sasaran)
                    . ' TIDAK dihapus. Hapus permanen meminta sandi agar lembaga tidak terhapus '
                    . 'karena salah klik.', 'err');
                redirect('index.php');
            }
            $hasil = sekolah_hapus_permanen($idSek);
            flash($hasil['pesan'], $hasil['ok'] ? 'warn' : 'err');
            redirect('index.php');
        }
        // Aksi lain (mis. keluar konteks) bukan milik halaman ini.
        redirect('index.php');
    }

    $daftar = sekolah_list(true);
    $jumlahMenunggu = jumlah_pendaftaran_menunggu();
    $semuaAktif = array_values(array_filter($daftar, static fn($s) => ($s['verifikasi'] ?? '') === 'disetujui' && (int) $s['aktif'] === 1));
    $totalGuru = (int) val('SELECT COUNT(*) FROM guru WHERE aktif = 1', [], 0);
    $totalSiswa = (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1', [], 0);
    $totalJurnal = (int) val('SELECT COUNT(*) FROM jurnal', [], 0);
    $totalNilai = (int) val('SELECT COUNT(*) FROM nilai', [], 0);

    // Ringkasan per sekolah untuk tabel & grafik.
    // Permintaan pemilik: "pada bagian daftar sekolah/madrasah tambahkan jumlah total agar bisa
    // mengetahui jumlah total kelas, siswa, guru, jurnal, nilai" → total DIHITUNG dari baris yang
    // benar-benar ditampilkan ($baris) sehingga baris JUMLAH selalu sama dengan tabelnya.
    $baris = [];
    $labelGrafik = [];
    $dataGrafik = [];
    $tot = ['kelas' => 0, 'guru' => 0, 'siswa' => 0, 'mapel' => 0, 'jurnal' => 0, 'nilai' => 0];
    foreach ($semuaAktif as $s) {
        $st = sekolah_stat((int) $s['id']);
        $baris[] = ['sekolah' => $s, 'stat' => $st];
        foreach ($tot as $k => $n) {
            $tot[$k] = $n + (int) ($st[$k] ?? 0);
        }
        $labelGrafik[] = $s['nama'] !== '' ? (string) $s['nama'] : '(nama belum diisi)';
        $dataGrafik[] = $st['siswa'];
    }

    // URUTAN TABEL (permintaan pemilik, v1.71.9): *"Urutkan: Paling atas adalah yang masa aktifnya
    // paling lama."* → permanen (tanpa batas) paling atas, lalu sisa masa aktif terpanjang, dan
    // sekolah yang masa aktifnya sudah habis di paling bawah. Nama sekolah dipakai sebagai
    // penentu kedua supaya urutannya tetap sama setiap kali halaman dibuka.
    // Dijalankan SESUDAH loop di atas agar urutan grafik "Sebaran Siswa" (abjad) tidak ikut berubah.
    usort($baris, static function (array $a, array $b): int {
        $ka = aktivasi_urut_kunci($a['sekolah']);
        $kb = aktivasi_urut_kunci($b['sekolah']);
        if ($ka !== $kb) {
            return $kb <=> $ka;   // menurun: masa aktif paling lama di atas
        }
        return strcasecmp((string) ($a['sekolah']['nama'] ?? ''), (string) ($b['sekolah']['nama'] ?? ''));
    });

    page_top('Ringkasan Semua Sekolah/Madrasah', 'Super Admin &middot; ' . count($semuaAktif) . ' sekolah/madrasah aktif dari '
        . count($daftar) . ' terdaftar');
    ?>
    <div class="grid g4">
      <div class="card stat card-tight">
        <div class="stat-ic g"><?= ikon('building', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= count($semuaAktif) ?></div><div class="stat-lbl">Sekolah/madrasah aktif</div></div>
      </div>
      <div class="card stat card-tight">
        <div class="stat-ic"><?= ikon('users', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= $totalGuru ?></div><div class="stat-lbl">Guru aktif (semua sekolah)</div></div>
      </div>
      <div class="card stat card-tight">
        <div class="stat-ic s"><?= ikon('student', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= $totalSiswa ?></div><div class="stat-lbl">Siswa aktif (semua sekolah)</div></div>
      </div>
      <div class="card stat card-tight">
        <div class="stat-ic o"><?= ikon('clock', 18) ?></div>
        <div><div class="stat-num" style="font-size:1.3rem"><?= $jumlahMenunggu ?></div><div class="stat-lbl">Pendaftaran menunggu</div></div>
      </div>
    </div>

    <?php if ($jumlahMenunggu > 0): ?>
      <div class="alert alert-warn">
        <?= ikon('star', 16) ?>
        <span>Ada <b><?= $jumlahMenunggu ?></b> pendaftaran menunggu persetujuan.
          <a href="pendaftaran.php">Tinjau sekarang</a> — login sekolah tersebut masih ditolak sampai disetujui.</span>
      </div>
    <?php endif; ?>

    <div class="grid g12">
      <div class="card">
        <div class="card-head">
          <div><h2>Sebaran Siswa per Sekolah/Madrasah</h2><p>Hanya sekolah/madrasah yang sudah disetujui &amp; aktif.</p></div>
        </div>
        <?php if ($dataGrafik && array_sum($dataGrafik) > 0): ?>
          <div class="chart-box"><canvas id="chSiswa"></canvas></div>
        <?php else: ?>
          <div class="empty"><?= ikon('chart', 30) ?><h3>Belum ada data siswa</h3>
            <p>Isi data siswa pada masing-masing sekolah/madrasah untuk melihat sebarannya di sini.</p></div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-head">
          <div><h2>Aktivitas Aplikasi</h2><p>Seluruh sekolah/madrasah.</p></div>
        </div>
        <div class="stat-mini">
          <div><b><?= $totalJurnal ?></b><span>Catatan jurnal</span></div>
          <div><b><?= $totalNilai ?></b><span>Baris nilai</span></div>
          <div><b><?= (int) val('SELECT COUNT(*) FROM absensi', [], 0) ?></b><span>Pertemuan daftar hadir</span></div>
          <div><b><?= (int) val('SELECT COUNT(*) FROM kasus', [], 0) ?></b><span>Catatan kasus</span></div>
        </div>
        <div class="divider"></div>
        <div class="flex">
          <a class="btn btn-primary btn-sm" href="sekolah.php"><?= ikon('building', 15) ?> Kelola Sekolah</a>
          <a class="btn btn-ghost btn-sm" href="pendaftaran.php"><?= ikon('star', 15) ?> Pendaftaran</a>
          <a class="btn btn-ghost btn-sm" href="pengguna.php"><?= ikon('key', 15) ?> Akun Super Admin</a>
          <?php // Pintasan ke daftar aplikasi GLOBAL (Ujian Online, Smart Teacher, Aplikasi Sagu &
                // Hasil Supervisi) — keempatnya berada pada blok Administrasi (di bawah Pelanggaran
                // Siswa) sehingga di tampilan lintas sekolah perlu pintasan agar tetap terjangkau.
                // (Menu "Studio Foto Pintar" DIHAPUS pada v1.75.0 atas permintaan pemilik.) ?>
          <a class="btn btn-ghost btn-sm" href="ujian_online.php"><?= ikon('clipboard', 15) ?> Ujian Online</a>
          <a class="btn btn-ghost btn-sm" href="smart_teacher.php"><?= ikon('apps', 15) ?> Smart Teacher</a>
          <?php // Aplikasi Sagu dibatasi per sekolah; pintasan ini SELALU tampil untuk Super Admin
                // karena dialah yang menyetel daftar sekolahnya (daftar_apps_tampil() → true). ?>
          <a class="btn btn-ghost btn-sm" href="aplikasi_sagu.php"><?= ikon('star', 15) ?> Aplikasi Sagu</a>
          <a class="btn btn-ghost btn-sm" href="hasil_supervisi.php"><?= ikon('chart', 15) ?> Hasil Supervisi</a>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head">
        <?php // Total keseluruhan ditulis juga di kepala kartu supaya langsung terbaca tanpa
              // menggulir ke baris JUMLAH di bawah tabel (permintaan pemilik). ?>
        <div>
          <h2>Daftar Sekolah/Madrasah</h2>
          <p><?= count($baris) ?> sekolah/madrasah aktif &middot; jumlah total:
            <b><?= (int) $tot['kelas'] ?></b> kelas,
            <b><?= (int) $tot['siswa'] ?></b> siswa,
            <b><?= (int) $tot['guru'] ?></b> guru,
            <b><?= (int) $tot['jurnal'] ?></b> jurnal,
            <b><?= (int) $tot['nilai'] ?></b> nilai.
            Klik "Kelola" untuk masuk ke data sekolah tersebut. Diurutkan dari <b>masa aktif paling
            lama</b> (permanen di paling atas, yang sudah habis di paling bawah).</p>
        </div>
      </div>
      <?php if (!$baris): ?>
        <div class="empty"><?= ikon('building', 34) ?><h3>Belum ada sekolah/madrasah aktif</h3>
          <p>Tambahkan lewat menu Kelola Sekolah, atau setujui pendaftaran yang masuk.</p>
          <div class="flex" style="justify-content:center">
            <a class="btn btn-primary" href="sekolah.php"><?= ikon('plus', 16) ?> Tambah Sekolah/Madrasah</a>
            <a class="btn btn-ghost" href="pendaftaran.php"><?= ikon('star', 16) ?> Tinjau Pendaftaran</a>
          </div>
        </div>
      <?php else: ?>
        <?php
        // Ringkasan MASA AKTIF untuk baris JUMLAH — dihitung dari daftar yang benar-benar tampil
        // (bukan query terpisah), memakai sumber yang sama dengan kolom di atas.
        $ringkasAkt = ['permanen' => 0, 'aktif' => 0, 'uji_coba' => 0, 'habis' => 0];
        foreach ($baris as $bb) {
            $aa = aktivasi_status($bb['sekolah']);
            if ($aa['mode'] === 'permanen') {
                $ringkasAkt['permanen']++;
            } elseif ($aa['habis']) {
                $ringkasAkt['habis']++;
            } else {
                $ringkasAkt[$aa['mode']]++;
            }
        }
        ?>
        <div class="table-wrap">
          <?php // id dipakai oleh pengukuran uji (baris JUMLAH vs baris data). ?>
          <table class="tbl" id="tblSekolahLintas">
            <thead><tr><th>Sekolah/Madrasah</th><th class="ctr">Masa Aktif</th><th class="ctr">Kelas</th>
              <th class="ctr">Guru</th><th class="ctr">Siswa</th>
              <th class="ctr">Mapel</th><th class="ctr">Jurnal</th><th class="ctr">Nilai</th>
              <th class="ctr">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($baris as $b): $s = $b['sekolah']; $st = $b['stat']; ?>
              <tr>
                <td>
                  <b><?= tetap_kata(nama_sekolah_tampil($s)) ?></b>
                  <span class="tbl-sub"><?= e($s['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah Umum') ?>
                    &middot; NPSN <?= e((string) ($s['npsn'] ?: '-')) ?></span>
                </td>
                <?php
                // MASA AKTIF (permintaan pemilik, v1.71.3): keadaan masa aktif tiap sekolah/madrasah
                // pada daftar lintas sekolah — permanen, masa aktif 365 hari (dengan sisa harinya),
                // masa uji coba, atau sudah berakhir (aplikasi terkunci). Satu sumber kebenaran:
                // aktivasi_status($sekolah) — jangan menghitung sisa hari sendiri di sini.
                $akt = aktivasi_status($s);
                ?>
                <td class="ctr">
                  <?php if ($akt['mode'] === 'permanen'): ?>
                    <span class="pill pill-ok">Permanen</span>
                  <?php elseif ($akt['habis']): ?>
                    <span class="pill pill-D"><?= $akt['mode'] === 'uji_coba' ? 'Uji coba habis' : 'Masa aktif habis' ?></span>
                    <span class="tbl-sub">s.d. <?= e(tanggal_id($akt['sampai'])) ?></span>
                  <?php elseif ($akt['mode'] === 'uji_coba'): ?>
                    <span class="pill pill-uji">Uji coba</span>
                    <span class="tbl-sub"><b><?= (int) $akt['sisa_hari'] ?> hari lagi</b><?= $akt['sampai'] !== '' ? ' &middot; s.d. ' . e(tanggal_id($akt['sampai'])) : '' ?></span>
                  <?php else: ?>
                    <span class="pill pill-ok">Aktif</span>
                    <span class="tbl-sub"><b><?= (int) $akt['sisa_hari'] ?> hari lagi</b><?= $akt['sampai'] !== '' ? ' &middot; s.d. ' . e(tanggal_id($akt['sampai'])) : '' ?></span>
                  <?php endif; ?>
                </td>
                <td class="ctr"><?= $st['kelas'] ?></td>
                <td class="ctr"><?= $st['guru'] ?></td>
                <td class="ctr"><b><?= $st['siswa'] ?></b></td>
                <td class="ctr"><?= $st['mapel'] ?></td>
                <td class="ctr"><?= $st['jurnal'] ?></td>
                <td class="ctr"><?= $st['nilai'] ?></td>
                <td class="sekolah-aksi">
                  <?php // KOLOM AKSI (v1.72.3 — permintaan pemilik: "Tambahkan fitur non aktifkan dan
                        // fitur hapus pada yang ditandai warna merah"). Tiga tombol per baris:
                        // Kelola (masuk konteks), Nonaktifkan (data tetap utuh, bisa diaktifkan
                        // kembali), dan Hapus yang WAJIB disertai SANDI KONFIRMASI
                        // (`SEKOLAH_HAPUS_SANDI`) supaya lembaga tidak terhapus karena salah klik.
                        // Formulir hapus memakai <details> agar tetap bekerja TANPA JavaScript.
                        //
                        // v1.72.4 — KETIGA TOMBOL DISEJATANKAN DALAM SATU BARIS. Permintaan pemilik:
                        // *"Yang saya beri tanda merah dirapikan agar fitur hapus, kelola dan
                        // nonaktifkan sejajar rapi"*. Sebelumnya wadah "row-actions" (Kelola +
                        // Nonaktifkan) dan <details> (Hapus) berada pada DUA baris terpisah sehingga
                        // tombol Hapus menggantung di kiri bawah tanpa sejajar — sekarang ketiganya
                        // menjadi ANAK dari satu wadah flex `.sekolah-aksi-baris`
                        // (lihat blok CSS "KOLOM AKSI pada tabel Daftar Sekolah/Madrasah"). ?>
                  <div class="sekolah-aksi-baris">
                    <?php // KIRIM WA (v1.76.0 — permintaan pemilik: *"Pada yang saya tandai warna merah
                          // tambahkan fitur kirim WA seperti aplikasi Supervisi."*). Tombol ringkas "WA"
                          // membuka WhatsApp (tab baru) ke nomor HP/telepon lembaga dengan pesan OTOMATIS
                          // berisi nama kepala sekolah, nama lembaga, dan status masa aktif/masa uji
                          // cobanya. Nomor yang belum diisi ditandai "&mdash;" agar kolom tetap rapi
                          // (bukan tombol mati). Handler-nya di `wa_lembaga_tombol()` (inc/helpers.php). ?>
                    <?= wa_lembaga_tombol($s, 'btn btn-wa btn-sm', 'WA', '<span class="mut xs">&mdash;</span>') ?>
                    <?php // "Kelola" tetap memakai handler di sekolah.php (aksi=masuk). ?>
                    <form method="post" action="sekolah.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="masuk">
                      <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                      <button class="btn btn-primary btn-sm" type="submit"><?= ikon('switch', 14) ?> Kelola</button>
                    </form>
                    <form method="post" action="index.php"
                          data-confirm="Nonaktifkan <?= e(nama_sekolah_tampil($s)) ?>? Seluruh datanya TETAP tersimpan dan dapat diaktifkan kembali.">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="nonaktifkan">
                      <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                      <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('x', 14) ?> Nonaktifkan</button>
                    </form>
                    <details class="sekolah-hapus">
                      <summary class="btn btn-danger btn-sm"><?= ikon('trash', 14) ?> Hapus</summary>
                      <form method="post" action="index.php" class="sekolah-hapus-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="hapus">
                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                        <label class="f" for="sandiHapus<?= (int) $s['id'] ?>">Sandi konfirmasi</label>
                        <input type="password" id="sandiHapus<?= (int) $s['id'] ?>" name="sandi"
                               placeholder="sandi" autocomplete="off" required>
                        <button class="btn btn-danger btn-sm" type="submit">
                          <?= ikon('trash', 14) ?> Hapus permanen</button>
                        <div class="hint xs">Sandi diminta agar lembaga tidak terhapus karena salah klik.
                          Seluruh data (guru, siswa, nilai, akun) ikut terhapus.</div>
                      </form>
                    </details>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
            <?php // Baris JUMLAH (permintaan pemilik): total kelas, guru, siswa, mapel, jurnal, nilai
                  // dari SELURUH sekolah/madrasah aktif yang terdaftar pada tabel di atas. ?>
            <tfoot>
              <tr>
                <td class="kiri"><b>JUMLAH (<?= count($baris) ?> sekolah/madrasah)</b>
                  <span class="tbl-sub">Seluruh kolom dijumlahkan dari daftar di atas</span></td>
                <td class="ctr"><span class="tbl-sub"><?= (int) $ringkasAkt['permanen'] ?> permanen
                  &middot; <?= (int) $ringkasAkt['aktif'] ?> berjangka<?= $ringkasAkt['uji_coba'] > 0 ? ' &middot; ' . (int) $ringkasAkt['uji_coba'] . ' uji coba' : '' ?><?= $ringkasAkt['habis'] > 0 ? ' &middot; ' . (int) $ringkasAkt['habis'] . ' habis' : '' ?></span></td>
                <td class="ctr"><b><?= (int) $tot['kelas'] ?></b></td>
                <td class="ctr"><b><?= (int) $tot['guru'] ?></b></td>
                <td class="ctr"><b><?= (int) $tot['siswa'] ?></b></td>
                <td class="ctr"><b><?= (int) $tot['mapel'] ?></b></td>
                <td class="ctr"><b><?= (int) $tot['jurnal'] ?></b></td>
                <td class="ctr"><b><?= (int) $tot['nilai'] ?></b></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($dataGrafik && array_sum($dataGrafik) > 0): ?>
      <script src="assets/vendor/chart.umd.min.js"></script>
      <script>
      document.addEventListener('DOMContentLoaded', function () {
        EG.bar('chSiswa', <?= json_encode($labelGrafik, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($dataGrafik) ?>,
          <?= count($labelGrafik) > 6 ? 'true' : 'false' ?>);
      });
      </script>
    <?php endif; ?>
    <?php
    page_bottom();
    exit;
}

// ============================================================================
// CABANG 1b — PENGAWAS SEKOLAH/MADRASAH (lintas binaan, HANYA MELIHAT & MENCETAK)
// Permintaan pemilik: "Tambahkan fitur untuk Akun pengawas seperti aplikasi Supervisi.
// Hanya bisa melihat hasil rekapan dan mencetak. Halaman dashboardnya disesuaikan."
// ============================================================================
if (is_pengawas()) {
    $binaan = sekolah_binaan((int) $u['id'], true);
    $binaanSemua = sekolah_binaan((int) $u['id'], false);
    $nonaktif = count($binaanSemua) - count($binaan);

    // Pilih / keluar dari satu binaan (konteks), seperti Super Admin memilih sekolah.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        if ($aksi === 'masuk') {
            $id = (int) ($_POST['id'] ?? 0);
            $target = $id > 0
                ? one('SELECT * FROM sekolah WHERE id = ? AND aktif = 1 AND verifikasi = "disetujui"', [$id])
                : null;
            if (!pengawas_punya((int) $u['id'], $id)) {
                flash('Sekolah/madrasah itu bukan binaan Anda.', 'err');
            } elseif (!$target) {
                flash('Sekolah/madrasah itu sedang tidak aktif.', 'err');
            } elseif (aktivasi_terkunci($target)) {
                // MASA AKTIF HABIS (v1.71.8 — permintaan pemilik): binaan TIDAK dibuka, pengawas
                // diberi keterangan bahwa sekolah/madrasah itu tidak bisa diakses. Dulu konteksnya
                // terlanjur dipasang sehingga halaman berikutnya dialihkan ke aktivasi.php dan
                // berakhir sebagai halaman 403 mentah (terlihat seperti aplikasi rusak).
                flash(aktivasi_pesan_binaan_terkunci($target), 'warn');
            } else {
                set_sekolah_context($id);
                redirect('index.php?binaan=1');
            }
        } elseif ($aksi === 'keluar') {
            set_sekolah_context(null);
            flash('Kembali ke daftar sekolah/madrasah binaan Anda.');
        }
        redirect('index.php');
    }

    // Keluar dari konteks binaan (tautan "Daftar Binaan" pada sidebar). Ini hanya mengubah
    // konteks tampilan — bukan data — sehingga aman lewat GET.
    if (isset($_GET['keluar'])) {
        set_sekolah_context(null);
        redirect('index.php');
    }
    $sekolah = current_sekolah();   // terisi hanya bila pengawas sudah masuk ke satu binaan
    $binaanPill = $binaan ? count($binaan) . ' binaan' : 'belum ada binaan';

    page_top($sekolah ? 'Dashboard Binaan' : 'Daftar Binaan Pengawas',
        $sekolah
            ? 'Pengawas Sekolah/Madrasah &middot; ' . tetap_kata(nama_sekolah_tampil()) . ' &middot; hanya lihat'
            : 'Pengawas Sekolah/Madrasah &middot; ' . $binaanPill . ' &middot; hanya lihat &amp; cetak');
    ?>
    <?php if ($sekolah):
        $sid = (int) $sekolah['id'];
        // ---- rekapan binaan yang sedang dilihat
        $rGuru = (int) val('SELECT COUNT(*) FROM guru WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0);
        $rSiswa = (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0);
        $rKelas = (int) val('SELECT COUNT(*) FROM kelas WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0);
        $rMapel = (int) val('SELECT COUNT(*) FROM mapel WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0);
        $rNilai = (int) val('SELECT COUNT(*) FROM nilai WHERE sekolah_id = ?', [$sid], 0);
        $rJurnal = (int) val('SELECT COUNT(*) FROM jurnal WHERE sekolah_id = ?', [$sid], 0);
        $rKasus = (int) val('SELECT COUNT(*) FROM kasus WHERE sekolah_id = ? AND status <> "selesai"', [$sid], 0);
        $awalB = date('Y-m-01'); $akhirB = date('Y-m-t');
        $rHadir = (int) val('SELECT COUNT(*) FROM absensi WHERE sekolah_id = ? AND tanggal BETWEEN ? AND ?',
            [$sid, $awalB, $akhirB], 0);
        // Rata-rata nilai per mata pelajaran (rekapan hasil belajar)
        $perMapel = all('SELECT m.nama AS mapel,
                           (SELECT COUNT(*) FROM nilai n WHERE n.mapel_id = m.id AND n.sekolah_id = m.sekolah_id) AS jml
                         FROM mapel m WHERE m.sekolah_id = ? AND m.aktif = 1 ORDER BY m.urutan, m.nama', [$sid]);
        ?>
      <div class="grid g4">
        <div class="card stat card-tight"><div class="stat-ic"><?= ikon('users', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= $rGuru ?></div><div class="stat-lbl">Guru aktif</div></div></div>
        <div class="card stat card-tight"><div class="stat-ic s"><?= ikon('student', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= $rSiswa ?></div><div class="stat-lbl">Siswa aktif</div></div></div>
        <div class="card stat card-tight"><div class="stat-ic o"><?= ikon('chart', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= $rNilai ?></div><div class="stat-lbl">Baris nilai terisi</div></div></div>
        <div class="card stat card-tight"><div class="stat-ic p"><?= ikon('clipboard', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= $rHadir ?></div><div class="stat-lbl">Pertemuan hadir bulan ini</div></div></div>
      </div>

      <div class="card">
        <div class="card-head">
          <div><h2>Rekapan <?= tetap_kata(nama_sekolah_tampil()) ?></h2>
            <p>Ringkasan data binaan. Seluruh halaman hanya dapat dilihat dan dicetak.</p></div>
          <div class="flex">
            <?php // ?keluar=1 melepas konteks binaan (tanpa itu halaman ini tetap Dashboard Binaan). ?>
            <a class="btn btn-ghost btn-sm" href="index.php?keluar=1"><?= ikon('switch', 15) ?> Ganti Binaan</a>
            <a class="btn btn-primary btn-sm" href="cetak_rekap_binaan.php?sekolah=<?= $sid ?>" target="_blank"><?= ikon('print', 15) ?> Cetak Rekap</a>
          </div>
        </div>
        <div class="stat-mini mb16">
          <div><b><?= $rKelas ?></b><span>Kelas aktif</span></div>
          <div><b><?= $rMapel ?></b><span>Mata pelajaran</span></div>
          <div><b><?= $rJurnal ?></b><span>Catatan jurnal</span></div>
          <div><b><?= $rKasus ?></b><span>Pelanggaran belum selesai</span></div>
        </div>
        <div class="flex" style="gap:10px;flex-wrap:wrap">
          <a class="btn btn-ghost btn-sm" href="guru.php"><?= ikon('users', 15) ?> Data Guru</a>
          <a class="btn btn-ghost btn-sm" href="siswa.php"><?= ikon('student', 15) ?> Data Siswa</a>
          <a class="btn btn-ghost btn-sm" href="absensi.php"><?= ikon('clipboard', 15) ?> Daftar Hadir</a>
          <a class="btn btn-ghost btn-sm" href="nilai.php"><?= ikon('chart', 15) ?> Daftar Nilai</a>
          <a class="btn btn-ghost btn-sm" href="jurnal.php"><?= ikon('book', 15) ?> Jurnal Harian</a>
          <a class="btn btn-ghost btn-sm" href="kasus.php"><?= ikon('alert', 15) ?> Pelanggaran Siswa</a>
          <?php $tugasBinaanDash = tugas_menu_saring_tampil(tugas_menu_list(true)); ?>
          <?php if ($tugasBinaanDash): ?>
            <a class="btn btn-ghost btn-sm" href="tugas.php"><?= ikon('star', 15) ?> Laporan Tugas Tambahan</a>
          <?php endif; ?>
        </div>
      </div>

      <?php $tugasBinaan = tugas_menu_saring_tampil(tugas_menu_list(true)); ?>
      <?php if ($tugasBinaan): ?>
        <div class="card">
          <div class="card-head">
            <div><h2>Laporan Tugas Tambahan</h2>
              <p>Laporan menu Tugas Tambahan binaan ini (hanya lihat &amp; cetak).</p></div>
            <a class="btn btn-ghost btn-sm" href="tugas.php"><?= ikon('chart', 15) ?> Semua Laporan</a>
          </div>
          <div class="flex" style="gap:10px;flex-wrap:wrap">
            <?php foreach ($tugasBinaan as $tb): ?>
              <a class="btn btn-ghost btn-sm" href="tugas.php?kode=<?= e((string) $tb['kode']) ?>"><?= ikon('star', 14) ?> <?= e((string) $tb['nama']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-head">
          <div><h2>Nilai Terisi per Mata Pelajaran</h2><p>Jumlah baris nilai yang sudah diisi pada binaan ini.</p></div>
          <a class="btn btn-ghost btn-sm" href="cetak_rekap_binaan.php?sekolah=<?= $sid ?>" target="_blank"><?= ikon('print', 15) ?> Cetak</a>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>Mata Pelajaran</th><th class="ctr">Baris Nilai</th></tr></thead>
            <tbody>
              <?php foreach ($perMapel as $m): ?>
                <tr><td><?= e((string) $m['mapel']) ?></td><td class="ctr"><?= (int) $m['jml'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php else:
        // ---- daftar binaan (belum masuk ke satu binaan)
        $totGuru = 0; $totSiswa = 0; $berdata = 0;
        $barisBinaan = [];
        $binaanTerkunci = [];   // binaan yang masa aktifnya habis (tidak dapat dibuka)
        // Jumlah menu Tugas Tambahan yang diaktifkan Super Admin (0 = belum ada laporan apa pun).
        $menubinaanAktif = count(tugas_menu_list(true));
        foreach ($binaan as $b) {
            $bid = (int) $b['id'];
            $jGuru = (int) val('SELECT COUNT(*) FROM guru WHERE aktif = 1 AND sekolah_id = ?', [$bid], 0);
            $jSiswa = (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND sekolah_id = ?', [$bid], 0);
            $jNilai = (int) val('SELECT COUNT(*) FROM nilai WHERE sekolah_id = ?', [$bid], 0);
            $totGuru += $jGuru; $totSiswa += $jSiswa;
            if ($jNilai > 0) { $berdata++; }
            // MASA AKTIF HABIS (v1.71.8): binaan yang masa aktif penggunaan aplikasinya berakhir
            // TIDAK dapat dibuka. Ditandai pada tabel + tidak dapat dimasuki (lihat handler POST).
            $kunciB = aktivasi_terkunci($b);
            if ($kunciB) { $binaanTerkunci[] = $b; }
            $barisBinaan[] = ['s' => $b, 'guru' => $jGuru, 'siswa' => $jSiswa, 'nilai' => $jNilai, 'kunci' => $kunciB];
        }
        ?>
      <div class="grid g4">
        <div class="card stat card-tight"><div class="stat-ic"><?= ikon('school', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= count($binaan) ?></div>
            <div class="stat-lbl">Binaan aktif</div>
            <div class="stat-hint"><?= $berdata ?> sudah ada nilai<?= $nonaktif > 0 ? ' &middot; ' . $nonaktif . ' nonaktif' : '' ?></div></div></div>
        <div class="card stat card-tight"><div class="stat-ic"><?= ikon('users', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= $totGuru ?></div><div class="stat-lbl">Guru aktif (binaan)</div></div></div>
        <div class="card stat card-tight"><div class="stat-ic s"><?= ikon('student', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= $totSiswa ?></div><div class="stat-lbl">Siswa aktif (binaan)</div></div></div>
        <div class="card stat card-tight"><div class="stat-ic o"><?= ikon('print', 18) ?></div>
          <div><div class="stat-num" style="font-size:1.3rem"><?= count($binaan) ?></div><div class="stat-lbl">Rekap dapat dicetak</div></div></div>
      </div>

      <?php if ($nonaktif > 0): ?>
        <div class="alert alert-warn">
          <?= ikon('alert', 16) ?>
          <span><?= (int) $nonaktif ?> sekolah/madrasah binaan Anda sudah <b>dinonaktifkan</b> sehingga tidak
            ditampilkan lagi dan tidak dapat dibuka.</span>
        </div>
      <?php endif; ?>

      <?php if ($binaanTerkunci): ?>
        <?php // v1.71.8 (permintaan pemilik): keterangan bahwa binaan tidak bisa diakses karena masa
              // aktif penggunaan aplikasinya telah berakhir. ?>
        <div class="alert alert-err" id="alertBinaanTerkunci">
          <?= ikon('lock', 16) ?>
          <span><b>Sekolah/madrasah ini tidak bisa diakses karena masa aktif penggunaan aplikasi telah berakhir</b>
            — <?= count($binaanTerkunci) ?> binaan:
            <?= e(implode(', ', array_map(static fn(array $b): string => (string) $b['nama'], $binaanTerkunci))) ?>.
            Data binaan tetap tersimpan (tidak ada yang dihapus); masa aktifnya dapat diperpanjang oleh
            <b>Kepala Sekolah/Madrasah</b> atau <b>Admin Operator</b> lembaga itu dengan kode aktivasi
            dari HD Admin<?= aktivasi_wa() !== '' ? ' (WA ' . e(aktivasi_wa()) . ')' : '' ?>.
            <a href="aktivasi.php">Lihat status masa aktif binaan</a>.</span>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-head">
          <div><h2>Hasil Rekapan Sekolah/Madrasah Binaan</h2>
            <p>Pilih satu binaan untuk melihat datanya, atau cetak rekapan seluruh binaan.</p></div>
          <?php if ($binaan): ?>
            <a class="btn btn-primary" href="cetak_rekap_binaan.php" target="_blank"><?= ikon('print', 16) ?> Cetak Rekap Semua Binaan</a>
          <?php endif; ?>
        </div>

        <?php if (!$binaan): ?>
          <div class="empty">
            <?= ikon('school', 34) ?>
            <h3>Belum ada sekolah/madrasah binaan</h3>
            <p>Hubungi Super Admin aplikasi untuk menetapkan sekolah/madrasah binaan akun Anda.</p>
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="tbl">
              <thead>
                <tr><th>Nama <?= tetap_kata('Sekolah/Madrasah') ?></th><th class="ctr">Jenis</th>
                  <th class="ctr">Guru</th><th class="ctr">Siswa</th><th class="ctr">Baris Nilai</th>
                  <th class="ctr">Tugas Tambahan</th>
                  <th class="ctr">Tindakan</th></tr>
              </thead>
              <tbody>
                <?php foreach ($barisBinaan as $b): ?>
                  <tr>
                    <td><b><?= e((string) $b['s']['nama']) ?></b>
                      <?php if ($b['kunci']): ?>
                        <span class="pill pill-D">masa aktif habis</span>
                      <?php endif; ?>
                      <span class="tbl-sub">NPSN <?= e((string) ($b['s']['npsn'] ?: '-')) ?></span></td>
                    <td class="ctr xs"><?= e($b['s']['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah Umum') ?></td>
                    <td class="ctr"><?= (int) $b['guru'] ?></td>
                    <td class="ctr"><?= (int) $b['siswa'] ?></td>
                    <td class="ctr"><?= (int) $b['nilai'] ?></td>
                    <?php // Laporan Tugas Tambahan binaan ini (permintaan pemilik) — hanya lihat & cetak. ?>
                    <td class="ctr">
                      <?php if ($menubinaanAktif > 0): ?>
                        <a class="btn btn-ghost btn-sm" href="tugas.php?sekolah=<?= (int) $b['s']['id'] ?>"><?= ikon('star', 14) ?> Laporan (<?= (int) $menubinaanAktif ?>)</a>
                      <?php else: ?>
                        <span class="mut xs">belum diaktifkan</span>
                      <?php endif; ?>
                    </td>
                    <td class="ctr">
                      <?php if ($b['kunci']): ?>
                        <?php // Masa aktif habis → data binaan TIDAK dapat dibuka maupun dicetak. ?>
                        <span class="mut xs">tidak bisa diakses<br>masa aktif berakhir</span>
                      <?php else: ?>
                        <div class="row-actions" style="justify-content:center">
                          <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="masuk">
                            <input type="hidden" name="id" value="<?= (int) $b['s']['id'] ?>">
                            <button class="btn btn-primary btn-sm" type="submit"><?= ikon('eye', 14) ?> Lihat Data</button>
                          </form>
                          <a class="btn btn-ghost btn-sm" target="_blank"
                             href="cetak_rekap_binaan.php?sekolah=<?= (int) $b['s']['id'] ?>"><?= ikon('print', 14) ?> Cetak</a>
                        </div>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="hint">Akun pengawas <b>hanya dapat melihat dan mencetak</b> — seluruh tombol pengubahan data
            disembunyikan dan tidak tersedia.</p>
        <?php endif; ?>
      </div>

      <div class="card card-tight">
        <div class="flex between">
          <div>
            <h3>Cara kerja akun pengawas</h3>
            <p class="sm mut" style="margin:4px 0 0">
              Pengawas bersifat lintas sekolah/madrasah, tetapi <b>terbatas pada binaan</b> yang ditetapkan
              Super Admin. Setelah memilih satu binaan (<b>Lihat Data</b>), seluruh menu data binaan tersebut
              dapat dibaca dan dicetak: data guru, data siswa, daftar hadir, daftar nilai, jurnal harian, dan
              catatan pelanggaran siswa. Pengubahan data, pengaturan, akun pengguna, status aktivasi, dan
              unduhan (ekspor CSV) tidak tersedia untuk akun pengawas. Bila <b>masa aktif</b> sebuah
              binaan berakhir, binaan itu <b>tidak dapat dibuka</b> (ditandai “masa aktif habis”) sampai
              pengelolanya memperpanjang masa aktif dengan kode aktivasi dari HD Admin.
            </p>
          </div>
          <span class="chip"><?= e(role_label()) ?></span>
        </div>
      </div>
    <?php endif; ?>
    <?php
    page_bottom();
    exit;
}

// ============================================================================
// CABANG 2 — Dashboard satu sekolah
// ============================================================================
$sekolah = require_sekolah();
$sid = (int) $sekolah['id'];
$konteks = konteks_ajaran();
$ta = $konteks['ta'];
$sem = $konteks['sem'];
$guru = is_guru();
$gid = $guru ? guru_id_saya() : 0;
$kelasIds = $guru ? array_map('intval', array_column(kelas_diampu(), 'id')) : [];
$inKelas = '';
$pInKelas = [];
$mapelIds = $guru ? array_map('intval', array_column(mapel_diampu(), 'id')) : [];
$inMapel = '';
$pInMapel = [];
if ($guru) {
    $inKelas = $kelasIds ? ' IN (' . implode(',', array_fill(0, count($kelasIds), '?')) . ')' : ' = -1';
    $pInKelas = $kelasIds;
    // Dashboard guru HANYA menampilkan data miliknya sendiri: kelas yang diajar, MATA PELAJARAN
    // yang diampu, serta catatan/pertemuan yang ia buat sendiri (tidak melihat mapel atau
    // rekapan guru lain) — permintaan pemilik.
    $inMapel = $mapelIds ? ' IN (' . implode(',', array_fill(0, count($mapelIds), '?')) . ')' : ' = -1';
    $pInMapel = $mapelIds;
}

$jmlGuru = (int) val('SELECT COUNT(*) FROM guru WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0);
$jmlSiswa = $guru
    ? (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND kelas_id' . $inKelas, $pInKelas, 0)
    : (int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0);
$jmlKelas = $guru ? count($kelasIds) : (int) val('SELECT COUNT(*) FROM kelas WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0);
$jmlMapel = $guru ? count(mapel_diampu()) : (int) val('SELECT COUNT(*) FROM mapel WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0);
$jmlJurnal = $guru
    ? (int) val('SELECT COUNT(*) FROM jurnal WHERE guru_id = ?', [$gid], 0)
    : (int) val('SELECT COUNT(*) FROM jurnal WHERE sekolah_id = ?', [$sid], 0);
$kasusAktif = $guru
    ? (int) val('SELECT COUNT(*) FROM kasus WHERE status <> "selesai" AND sekolah_id = ? AND guru_id = ?', [$sid, $gid], 0)
    : (int) val('SELECT COUNT(*) FROM kasus WHERE status <> "selesai" AND sekolah_id = ?', [$sid], 0);

// Grafik: siswa per kelas
$siswaPerKelas = all('SELECT k.nama, (SELECT COUNT(*) FROM siswa s WHERE s.kelas_id = k.id AND s.aktif = 1) AS jml
    FROM kelas k WHERE k.aktif = 1 AND k.sekolah_id = ?' . ($guru ? ' AND k.id' . $inKelas : '')
    . ' ORDER BY k.urutan, k.nama', array_merge([$sid], $guru ? $pInKelas : []));

// Grafik: kehadiran bulan ini
$awalBulan = date('Y-m-01');
$akhirBulan = date('Y-m-t');
$kehadiran = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
foreach (all('SELECT d.status, COUNT(*) AS jml FROM absensi a JOIN absensi_detail d ON d.absensi_id = a.id
              WHERE a.tanggal BETWEEN ? AND ? AND a.sekolah_id = ?' . ($guru ? ' AND a.guru_id = ?' : '') . ' GROUP BY d.status',
        $guru ? [$awalBulan, $akhirBulan, $sid, $gid] : [$awalBulan, $akhirBulan, $sid]) as $r) {
    if (isset($kehadiran[(string) $r['status']])) {
        $kehadiran[(string) $r['status']] = (int) $r['jml'];
    }
}
$totalHadir = array_sum($kehadiran);
$persenHadir = $totalHadir > 0 ? round($kehadiran['H'] / $totalHadir * 100, 1) : null;

// Grafik: jurnal 6 bulan
$jurnalBulan = [];
foreach (all('SELECT strftime("%Y-%m", tanggal) AS bln, COUNT(*) AS jml FROM jurnal
              WHERE tanggal >= date("now", "-5 months", "start of month") AND sekolah_id = ?'
              . ($guru ? ' AND guru_id = ?' : '') . ' GROUP BY bln',
        $guru ? [$sid, $gid] : [$sid]) as $r) {
    $jurnalBulan[(string) $r['bln']] = (int) $r['jml'];
}
$labelBulan = [];
$dataBulan = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime('-' . $i . ' months', strtotime(date('Y-m-01')));
    $kunci = date('Y-m', $ts);
    $labelBulan[] = substr(bulan_id((int) date('n', $ts)), 0, 3) . ' ' . date('y', $ts);
    $dataBulan[] = $jurnalBulan[$kunci] ?? 0;
}

// Predikat & rata-rata nilai
$predikatJml = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
$rataMapel = [];
if ($ta && $sem) {
    $barisNilai = all('SELECT n.siswa_id, n.mapel_id, n.jenis, n.kolom, n.nilai FROM nilai n
                       WHERE n.sekolah_id = ? AND n.ta_id = ? AND n.semester_id = ?'
        . ($guru ? ' AND n.kelas_id' . $inKelas . ' AND n.mapel_id' . $inMapel : ''),
        array_merge([$sid, (int) $ta['id'], (int) $sem['id']], $guru ? array_merge($pInKelas, $pInMapel) : []));
    $kelompok = [];
    foreach ($barisNilai as $r) {
        $kunci = (int) $r['siswa_id'] . '-' . (int) $r['mapel_id'];
        if (!isset($kelompok[$kunci])) {
            $kelompok[$kunci] = ['sumatif' => [], 'asas' => null, 'mapel_id' => (int) $r['mapel_id']];
        }
        $v = (float) $r['nilai'];
        if ($r['jenis'] === 'sumatif') {
            $kelompok[$kunci]['sumatif'][(int) $r['kolom']] = $v;
        } elseif ($r['jenis'] === 'asas') {
            $kelompok[$kunci]['asas'] = $v;
        }
    }
    foreach ($kelompok as $d) {
        $akhir = nilai_akhir($d['sumatif'], $d['asas']);
        if ($akhir === null) {
            continue;
        }
        $p = predikat_dari($akhir);
        if (isset($predikatJml[$p])) {
            $predikatJml[$p]++;
        }
        $mid = (int) $d['mapel_id'];
        $rataMapel[$mid]['total'] = ($rataMapel[$mid]['total'] ?? 0) + $akhir;
        $rataMapel[$mid]['jml'] = ($rataMapel[$mid]['jml'] ?? 0) + 1;
    }
}
$tot = 0;
$jml = 0;
foreach ($rataMapel as $d) {
    $tot += $d['total'];
    $jml += $d['jml'];
}
$rataSemua = $jml > 0 ? round($tot / $jml, 2) : null;
$mapelRata = [];
foreach ($rataMapel as $mid => $d) {
    $mapelRata[] = ['nama' => mapel_nama((int) $mid), 'rata' => round($d['total'] / $d['jml'], 2), 'jml' => $d['jml']];
}
usort($mapelRata, static fn($a, $b) => $b['rata'] <=> $a['rata']);

// DOKUMEN DARI KURIKULUM (permintaan pemilik: dokumen yang dikirim masuk di akun guru sekolah itu) —
// hanya dihitung/diambil untuk akun GURU, karena kartunya khusus di dashboard guru.
$dokumenTerbaru = $guru ? dokumen_list(3) : [];
$dokumenJumlah = $guru ? dokumen_jumlah() : 0;

// HASIL TINJAUAN PERANGKAT oleh kepala sekolah (v1.67.0/skema v48) — permintaan pemilik: "Hasil
// tinjauan kepala sekolah terkirim ke akun guru". Ditampilkan sebagai kartu pada dashboard guru,
// berisi hasil tinjauan TERBARU, supaya guru langsung tahu dokumennya disetujui/direvisi/ditolak.
$perangkatTinjau = $guru ? perangkat_tinjauan_terbaru($gid, 3) : [];
$perangkatRingkas = $guru ? perangkat_ringkas(perangkat_kirim_guru($gid, 0)) : perangkat_ringkas([]);

// Daftar terbaru
$jurnalTerbaru = all('SELECT j.*, k.nama AS kelas_nama, m.nama AS mapel_nama, g.nama AS guru_nama
    FROM jurnal j LEFT JOIN kelas k ON k.id = j.kelas_id LEFT JOIN mapel m ON m.id = j.mapel_id LEFT JOIN guru g ON g.id = j.guru_id
    WHERE j.sekolah_id = ?' . ($guru ? ' AND j.guru_id = ?' : '') . ' ORDER BY j.tanggal DESC, j.id DESC LIMIT 5',
    $guru ? [$sid, $gid] : [$sid]);
$kasusTerbaru = all('SELECT ks.*, s.nama AS siswa_nama, k.nama AS kelas_nama
    FROM kasus ks LEFT JOIN siswa s ON s.id = ks.siswa_id LEFT JOIN kelas k ON k.id = ks.kelas_id
    WHERE ks.status <> "selesai" AND ks.sekolah_id = ?' . ($guru ? ' AND ks.guru_id = ?' : '')
    . ' ORDER BY ks.tanggal DESC LIMIT 5', $guru ? [$sid, $gid] : [$sid]);

$belumSiap = [];
if (trim((string) $sekolah['nama']) === '') {
    $belumSiap[] = 'Identitas ' . istilah_sekolah('sekolah') . ' (nama, alamat, kepala) belum diisi.';
}
if ((int) val('SELECT COUNT(*) FROM kelas WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0) === 0) {
    $belumSiap[] = 'Belum ada kelas.';
}
if ((int) val('SELECT COUNT(*) FROM siswa WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0) === 0) {
    $belumSiap[] = 'Belum ada data siswa.';
}
if ((int) val('SELECT COUNT(*) FROM guru WHERE aktif = 1 AND sekolah_id = ?', [$sid], 0) === 0) {
    $belumSiap[] = 'Belum ada data guru.';
}

page_top('Dashboard');
?>
<?php if ($belumSiap && !$guru): ?>
  <div class="alert alert-warn">
    <?= ikon('alert', 16) ?>
    <span><b>Langkah awal:</b> <?= e(implode(' ', $belumSiap)) ?>
      Mulai dari <a href="kelas.php">Kelas</a>, lalu <a href="guru_edit.php">Data Guru</a> dan <a href="siswa_edit.php">Data Siswa</a>.
      Jangan lupa lengkapi <a href="pengaturan.php">Identitas &amp; Pengaturan</a> agar kop dokumen cetak benar.</span>
  </div>
<?php endif; ?>

<?php // DUA KARTU DI BAGIAN ATAS DASHBOARD (sebelum kartu statistik):
      // 1. PENGAWAS BINA (permintaan pemilik) — akun Kepala Sekolah/Madrasah & GURU: siapa pengawas
      //    pembina sekolah ini (foto + nama). Super Admin (dalam konteks) & Admin Operator tidak.
      // 2. GABUNG GRUP e-GURU (permintaan pemilik: "Pada Akun Kepala Sekolah, Operator dan Guru pada
      //    halaman dasboard tambahkan Fitur Gabung Grup e-Guru … Posisi fitur letakkan sejajar dengan
      //    kartu pengawas bina (kartu pengawas bina di dashboard guru dan kepala madrasah jangan
      //    dipanjangkan)") — akun Kepala Sekolah/Madrasah, Admin Operator, dan GURU.
      // ⚠️ Pembungkus `.atas-dashboard` memakai `align-items:start` (assets/style.css) supaya kartu
      // Pengawas Bina TIDAK ikut dipanjangkan setinggi kartu di sebelahnya.
      $adaBina = is_kepala() || $guru;
      $adaGrup = is_kepala() || is_operator() || $guru;
      ?>
<?php if ($adaBina || $adaGrup): ?>
  <div class="grid<?= ($adaBina && $adaGrup) ? ' g2' : '' ?> atas-dashboard">
    <?php if ($adaBina): ?><?php kartu_pengawas_bina(); ?><?php endif; ?>
    <?php if ($adaGrup): ?><?php kartu_grup_eguru(); ?><?php endif; ?>
  </div>
<?php endif; ?>

<div class="grid g4">
  <div class="card stat card-tight">
    <div class="stat-ic"><?= ikon('student', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $jmlSiswa ?></div><div class="stat-lbl"><?= $guru ? 'Siswa di kelas Anda' : 'Siswa aktif' ?></div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic s"><?= ikon('school', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $jmlKelas ?></div><div class="stat-lbl"><?= $guru ? 'Kelas yang diajar' : 'Kelas aktif' ?></div></div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic p"><?= ikon('users', 18) ?></div>
    <div>
      <div class="stat-num" style="font-size:1.3rem"><?= $guru ? $jmlMapel : $jmlGuru ?></div>
      <div class="stat-lbl"><?= $guru ? 'Mata pelajaran diampu' : 'Guru aktif' ?></div>
    </div>
  </div>
  <div class="card stat card-tight">
    <div class="stat-ic o"><?= ikon('book', 18) ?></div>
    <div><div class="stat-num" style="font-size:1.3rem"><?= $jmlJurnal ?></div><div class="stat-lbl">Catatan jurnal</div></div>
  </div>
</div>

<?php if ($guru): ?>
  <?php // KARTU DOKUMEN (permintaan pemilik): dokumen dari menu Kurikulum DAN dari Pengawas Binaan
        // (menu "Kirim Dokumen") masuk ke akun guru. Judul kartu dibuat netral karena ada dua sumber. ?>
  <div class="card card-dokumen">
    <div class="card-head">
      <div>
        <h2><?= ikon('doc', 16) ?> Dokumen untuk Guru</h2>
        <p>Dokumen yang dikirim <b>Kurikulum</b> <?= tetap_kata(nama_sekolah_tampil()) ?> maupun
          <b>Pengawas Binaan</b> kepada seluruh guru.<?= $dokumenJumlah > 0 ? ' <b>' . (int) $dokumenJumlah . ' dokumen</b> tersedia.' : '' ?></p>
      </div>
      <div class="flex">
        <a class="btn btn-ghost btn-sm" href="dokumen_saya.php"><?= ikon('doc', 15) ?> Semua dokumen</a>
      </div>
    </div>
    <?php if (!$dokumenTerbaru): ?>
      <div class="empty">
        <?= ikon('doc', 30) ?>
        <h3>Belum ada dokumen</h3>
        <p>Dokumen (PDF, gambar, atau berkas lain) yang dikirim lewat menu <b>Kurikulum</b> atau oleh
          <b>Pengawas Binaan</b> akan muncul di sini.</p>
      </div>
    <?php else: ?>
      <?= dokumen_kartu($dokumenTerbaru) ?>
      <?php if ($dokumenJumlah > count($dokumenTerbaru)): ?>
        <p class="hint mt16">Menampilkan <b><?= count($dokumenTerbaru) ?></b> dokumen terbaru dari
          <b><?= (int) $dokumenJumlah ?></b> dokumen —
          <a href="dokumen_saya.php">lihat semuanya</a>.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php // KARTU HASIL TINJAUAN PERANGKAT (v1.67.0/skema v48) — permintaan pemilik: "Hasil tinjauan
        // kepala sekolah terkirim ke akun guru". Menampilkan hasil tinjauan TERBARU + ringkasan
        // keadaan perangkat yang sudah dikirim, lengkap dengan tautan ke menu Kirim Perangkat. ?>
  <div class="card card-perangkat" id="cardPerangkatGuru">
    <div class="card-head">
      <div>
        <h2><?= ikon('folder', 16) ?> Hasil Tinjauan Perangkat Saya</h2>
        <p>Perangkat pembelajaran yang Anda kirim ditinjau oleh <b>Kepala Sekolah/Madrasah</b>.
          <?= (int) $perangkatRingkas['kirim'] > 0
              ? '<b>' . (int) $perangkatRingkas['kirim'] . ' kiriman</b> tercatat — ' . (int) $perangkatRingkas['disetujui'] . ' disetujui, '
                . (int) $perangkatRingkas['revisi'] . ' direvisi, ' . (int) $perangkatRingkas['ditolak'] . ' ditolak, '
                . (int) $perangkatRingkas['menunggu'] . ' belum ditinjau.'
              : '' ?></p>
      </div>
      <div class="flex">
        <a class="btn btn-ghost btn-sm" href="perangkat_saya.php"><?= ikon('upload', 15) ?> Kirim Perangkat</a>
      </div>
    </div>
    <?php if (!$perangkatTinjau): ?>
      <div class="empty">
        <?= ikon('folder', 30) ?>
        <h3>Belum ada hasil tinjauan</h3>
        <p>Kirim perangkat pembelajaran Anda (mis. Kalender Pendidikan, Capaian Pembelajaran,
          RPP/Modul Ajar) lewat menu <b>Kirim Perangkat</b> — hasil tinjauan kepala sekolah akan
          tampil di sini.</p>
      </div>
    <?php else: ?>
      <div class="grid g3 dok-grid">
        <?php foreach ($perangkatTinjau as $tj): $st = perangkat_status_awal((string) $tj['status']); ?>
          <div class="card dok-kartu pk-kartu-tinjau">
            <div class="dok-kartu-head">
              <div class="dok-ikon dok-dokumen"><?= ikon(perangkat_status_ikon($st), 18) ?></div>
              <div class="dok-judul">
                <h3><?= e(tetap_kata((string) $tj['jenis_nama'])) ?></h3>
                <span class="pill <?= e(perangkat_status_pil($st)) ?>">
                  <?= e(perangkat_status_label($st)) ?></span>
              </div>
            </div>
            <p class="dok-catatan">
              <?= trim((string) ($tj['catatan'] ?? '')) !== ''
                  ? nl2br(e(tetap_kata((string) $tj['catatan'])))
                  : '<span class="mut">Tanpa keterangan dari peninjau.</span>' ?>
            </p>
            <div class="dok-kaki">
              <span class="xs mut"><?= e(tetap_kata((string) $tj['mapel_nama'])) ?> &middot;
                <?= e((string) ($tj['ta_teks'] !== '' ? $tj['ta_teks'] : '-')) ?>
                <?= trim((string) ($tj['peninjau_nama'] ?? '')) !== ''
                    ? ' &middot; oleh ' . e(tetap_kata((string) $tj['peninjau_nama'])) : '' ?>
                &middot; <?= e(waktu_id_polos((string) ($tj['ditinjau_pada'] ?? ''))) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="hint mt16">Bila dokumen dinyatakan <b>Direvisi</b> atau <b>Ditolak</b>, perbaiki
        berkasnya lalu unggah ulang pada sel yang sama di menu
        <a href="perangkat_saya.php">Kirim Perangkat</a>.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="grid g21">
  <div class="card">
    <div class="card-head"><div><h2>Jumlah Siswa per Kelas</h2><p>Sebaran siswa pada kelas aktif.</p></div></div>
    <?php if ($siswaPerKelas): ?>
      <div class="chart-box"><canvas id="chKelas"></canvas></div>
    <?php else: ?>
      <div class="empty"><?= ikon('school', 30) ?><h3>Belum ada kelas</h3><p>Tambahkan kelas untuk melihat sebaran siswa.</p></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Predikat Nilai</h2><p>Distribusi nilai akhir periode aktif. <?= tetap_kata(predikat_keterangan()) ?>.</p></div></div>
    <?php if (array_sum($predikatJml) > 0): ?>
      <div class="chart-box"><canvas id="chPredikat"></canvas></div>
      <?php if ($rataSemua !== null): ?>
        <p class="hint center">Rata-rata nilai akhir: <b><?= e(r1($rataSemua)) ?></b> (<?= count($mapelRata) ?> mata pelajaran)</p>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty"><?= ikon('chart', 30) ?><h3>Belum ada nilai</h3><p>Isi daftar nilai untuk melihat distribusi predikat.</p></div>
    <?php endif; ?>
  </div>
</div>

<div class="grid g21">
  <div class="card">
    <div class="card-head"><div><h2>Aktivitas Jurnal 6 Bulan Terakhir</h2><p>Jumlah catatan jurnal harian per bulan.</p></div></div>
    <div class="chart-box"><canvas id="chJurnal"></canvas></div>
  </div>

  <div class="card">
    <div class="card-head"><div><h2>Kehadiran Bulan Ini</h2><p><?= e(bulan_id((int) date('n'))) . ' ' . date('Y') ?></p></div></div>
    <?php if ($totalHadir > 0): ?>
      <div class="chart-box"><canvas id="chHadir"></canvas></div>
      <p class="hint center">Persentase kehadiran: <b><?= e(r1($persenHadir)) ?>%</b> dari <?= (int) $totalHadir ?> catatan</p>
    <?php else: ?>
      <div class="empty"><?= ikon('clipboard', 30) ?><h3>Belum ada daftar hadir</h3><p>Isi daftar hadir bulan ini untuk melihat rekapnya.</p></div>
    <?php endif; ?>
  </div>
</div>

<div class="grid g2">
  <div class="card">
    <div class="card-head">
      <div><h2>Jurnal Terbaru</h2><p><?= $guru ? 'Catatan Anda' : 'Seluruh guru' ?> &middot; <?= (int) $kasusAktif ?> kasus belum selesai</p></div>
      <a class="btn btn-ghost btn-sm" href="jurnal.php"><?= ikon('book', 14) ?> Semua jurnal</a>
    </div>
    <?php if (!$jurnalTerbaru): ?>
      <p class="mut sm">Belum ada catatan jurnal.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Tanggal</th><th>Kelas/Tempat · Mapel</th><th>Kegiatan / Materi</th><th class="ctr">Kehadiran</th></tr></thead>
          <tbody>
          <?php foreach ($jurnalTerbaru as $j): ?>
            <tr>
              <td class="nowrap"><?= e(tanggal_id((string) $j['tanggal'])) ?></td>
              <td><span class="tagmini"><?= e(jurnal_kelas_tempat($j)) ?></span><span class="tagmini"><?= e((string) ($j['mapel_nama'] ?: '-')) ?></span></td>
              <?php // Kegiatan selain mengajar tidak punya materi → tampilkan uraian kegiatannya. ?>
              <td class="sm"><?= e((string) (($j['materi'] !== '' && $j['materi'] !== null) ? $j['materi'] : jurnal_uraian($j))) ?></td>
              <td class="ctr"><span class="xs mut"><?= e(kehadiran_ringkas($j)) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <div><h2>Kasus Belum Selesai</h2><p><?= $kasusAktif ?> catatan belum selesai</p></div>
      <a class="btn btn-ghost btn-sm" href="kasus.php"><?= ikon('alert', 14) ?> Semua kasus</a>
    </div>
    <?php if (!$kasusTerbaru): ?>
      <p class="mut sm">Tidak ada kasus yang belum selesai.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>Tanggal</th><th>Siswa</th><th>Uraian</th><th class="ctr">Status</th></tr></thead>
          <tbody>
          <?php $statusList = kasus_status(); foreach ($kasusTerbaru as $r): ?>
            <tr>
              <td class="nowrap"><?= e(tanggal_id((string) $r['tanggal'])) ?></td>
              <td><?= e((string) ($r['siswa_nama'] ?: '-')) ?><span class="tbl-sub"><?= e((string) ($r['kelas_nama'] ?: '-')) ?></span></td>
              <td class="sm"><?= e((string) ($r['judul'] ?: $r['deskripsi'])) ?></td>
              <td class="ctr"><span class="pill <?= $r['status'] === 'proses' ? 'pill-C' : 'pill-D' ?>"><?= e($statusList[(string) $r['status']] ?? '-') ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($mapelRata): ?>
  <div class="card">
    <div class="card-head">
      <div><h2>Rata-rata Nilai Akhir per Mata Pelajaran</h2><p>Periode <?= e(ta_nama($ta)) ?> &middot; Semester <?= e(sem_nama($sem)) ?></p></div>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Mata Pelajaran</th><th class="ctr">Siswa Dinilai</th><th class="ctr">Rata-rata</th><th class="ctr">Predikat</th><th style="width:220px">Grafik</th></tr></thead>
        <tbody>
        <?php foreach ($mapelRata as $m): $p = predikat_dari($m['rata']); ?>
          <tr>
            <td><b><?= e($m['nama']) ?></b></td>
            <td class="ctr"><?= (int) $m['jml'] ?></td>
            <td class="ctr"><b><?= e(r1($m['rata'])) ?></b></td>
            <td class="ctr"><span class="pill pill-<?= e($p) ?>"><?= e($p) ?></span></td>
            <td><span class="bar-mini"><i class="lv<?= e($p) ?>" style="width:<?= (int) max(0, min(100, $m['rata'])) ?>%"></i></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<script src="assets/vendor/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  <?php if ($siswaPerKelas): ?>
  EG.bar('chKelas', <?= json_encode(array_map(static fn($r) => (string) $r['nama'], $siswaPerKelas), JSON_UNESCAPED_UNICODE) ?>,
    <?= json_encode(array_map(static fn($r) => (int) $r['jml'], $siswaPerKelas)) ?>,
    <?= count($siswaPerKelas) > 6 ? 'true' : 'false' ?>);
  <?php endif; ?>
  <?php if (array_sum($predikatJml) > 0): ?>
  EG.donut('chPredikat', <?= json_encode(array_map(
      static fn(string $p): string => $p . ' (' . predikat_label($p) . ')', ['A', 'B', 'C', 'D']), JSON_UNESCAPED_UNICODE) ?>,
    [<?= (int) $predikatJml['A'] ?>, <?= (int) $predikatJml['B'] ?>, <?= (int) $predikatJml['C'] ?>, <?= (int) $predikatJml['D'] ?>],
    ['#0f7a53', '#2aa87a', '#c9a227', '#c0392b']);
  <?php endif; ?>
  EG.line('chJurnal', <?= json_encode($labelBulan) ?>, <?= json_encode($dataBulan) ?>, 'Catatan jurnal');
  <?php if ($totalHadir > 0): ?>
  EG.donut('chHadir', ['Hadir', 'Sakit', 'Izin', 'Alpa'],
    [<?= (int) $kehadiran['H'] ?>, <?= (int) $kehadiran['S'] ?>, <?= (int) $kehadiran['I'] ?>, <?= (int) $kehadiran['A'] ?>],
    ['#0f7a53', '#c9a227', '#2b7bb9', '#c0392b']);
  <?php endif; ?>
});
</script>
<?php
page_bottom();
