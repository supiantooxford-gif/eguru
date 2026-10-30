<?php
/**
 * MODUL KURIKULUM — KIRIM DOKUMEN KEPADA GURU (menu Tugas Tambahan berkode `kurikulum`).
 *
 * Permintaan pemilik: *"Pada menu kurikulum isi menu dengan fitur untuk mengirim dokumen kepada guru.
 * Dokumen bisa berupa pdf, gambar atau lainnya. Dokumen yang dikirim akan masuk di akun guru pada
 * sekolah tersebut. Fiturnya Unggah Dokumen ke guru: Judul Dokumen, Catatan, Unggah file"*
 *
 * Keputusan pemilik (jangan diubah tanpa bertanya):
 *   1. **satu kiriman untuk SEMUA guru** di sekolah itu (bukan per guru);
 *   2. yang boleh mengirim: **Kepala Sekolah/Madrasah & Admin Operator**, serta **guru yang
 *      ditugaskan** menu Kurikulum (ditegakkan `require_tugas_menu()` di `tugas.php`);
 *   3. guru melihat dokumen pada **kartu di dashboard** (`index.php`) dan halaman khusus
 *      **"Dokumen Saya"** (`dokumen_saya.php`).
 *
 * 🚨 **Teks yang DIKETIK PENGIRIM tidak boleh ikut penyesuaian istilah**: judul dokumen, catatan,
 * nama berkas, dan nama pengirim dibungkus **`tetap_kata()`** saat dicetak. Tanpa itu, dokumen
 * berjudul "Program Kerja Sekolah" akan TAMPAIL sebagai "Program Kerja Madrasah" pada sekolah
 * berjenis madrasah — guru melihat judul yang berbeda dari yang dikirim (perilaku yang sama
 * dengan aturan "nama resmi sekolah tidak boleh ikut berubah").
 *
 * Berkas diunggah lewat FORM MULTIPART BIASA ke media proxy platform DI SISI SERVER
 * (`media_upload()`); token unggah TIDAK pernah sampai ke browser. Yang disimpan ke basis data hanya
 * URL CDN hasil respons (tabel `dokumen`). Form multipart dipilih supaya unggahan tetap bekerja
 * walau JavaScript gagal dimuat (pelajaran dari kartu foto bukti pengamatan).
 *
 * **PENGAWAS (hanya lihat)**: pengawas sekolah/madrasah binaan boleh membuka daftar dokumen yang
 * dikirim sekolah itu, tetapi tidak boleh mengirim/menghapus (POST ditolak 403).
 *
 * Seluruh logika modul ini ada di sini — halaman `dokumen_saya.php` hanya memanggil
 * `dokumen_halaman_guru()`. **Jangan menyalin logikanya ke halaman lain.**
 */
declare(strict_types=1);

// ------------------------------------------------------------------ bantuan berkas

/** Ubah nilai setelan PHP (mis. "20M", "1G") menjadi byte. */
function dok_ini_bytes(string $nilai): int
{
    $nilai = trim($nilai);
    if ($nilai === '' || $nilai === '-1') {
        return 0;   // 0 = tidak dibatasi
    }
    $satuan = strtolower(substr($nilai, -1));
    $angka = (float) $nilai;
    switch ($satuan) {
        case 'g':
        case 'gb':
            $angka *= 1024 * 1024 * 1024;
            break;
        case 'm':
        case 'mb':
            $angka *= 1024 * 1024;
            break;
        case 'k':
        case 'kb':
            $angka *= 1024;
            break;
    }
    return (int) $angka;
}

/**
 * Batas ukuran yang REALISTIS di server ini.
 *
 * Media proxy platform mengizinkan 15/50/300 MB, tetapi permintaan unggahan harus lebih dulu melewati
 * PHP (`upload_max_filesize` & `post_max_size`). Bila berkas melebihi batas PHP, PHP MEMBUANG seluruh
 * isian tanpa pesan sehingga gejalanya "tidak terjadi apa-apa" — karena itu batas itu dibaca dari
 * setelan server dan ditampilkan ke pengguna (lihat juga pemeriksaan CONTENT_LENGTH pada aksi kirim).
 */
function dok_batas_server(): int
{
    $post = dok_ini_bytes((string) ini_get('post_max_size'));
    $up = dok_ini_bytes((string) ini_get('upload_max_filesize'));
    $kandidat = [];
    if ($post > 0) {
        // Sisakan ruang untuk field teks (judul, catatan, token CSRF).
        $kandidat[] = max(0, $post - 512 * 1024);
    }
    if ($up > 0) {
        $kandidat[] = $up;
    }
    $min = $kandidat ? min($kandidat) : 8 * 1024 * 1024;
    return max(1024 * 1024, $min);
}

/** Jenis sebuah berkas dari ekstensinya: 'gambar' | 'video' | 'dokumen' | '' (tidak didukung). */
function dok_jenis_ext(string $ext): string
{
    $ext = strtolower(trim($ext));
    if (in_array($ext, DOK_EKSTENSI_GAMBAR, true)) {
        return 'gambar';
    }
    if (in_array($ext, DOK_EKSTENSI_VIDEO, true)) {
        return 'video';
    }
    if (in_array($ext, DOK_EKSTENSI_DOKUMEN, true)) {
        return 'dokumen';
    }
    return '';
}

/** Seluruh ekstensi yang diterima (untuk keterangan pada halaman). */
function dok_ekstensi_semua(): array
{
    return array_merge(DOK_EKSTENSI_DOKUMEN, DOK_EKSTENSI_GAMBAR, DOK_EKSTENSI_VIDEO);
}

/** Batas ukuran menurut jenis (aturan platform, belum menghitung batas server). */
function dok_batas_jenis(string $jenis): int
{
    if ($jenis === 'gambar') {
        return DOK_MAKS_GAMBAR;
    }
    if ($jenis === 'video') {
        return DOK_MAKS_VIDEO;
    }
    return DOK_MAKS_DOKUMEN;
}

/** Batas ukuran yang berlaku di server ini untuk sebuah jenis berkas. */
function dok_batas_efektif(string $jenis): int
{
    return min(dok_batas_jenis($jenis), dok_batas_server());
}

/** Tulisan ukuran berkas yang enak dibaca (mis. "1,5 MB"). */
function dok_ukuran(int $byte): string
{
    if ($byte <= 0) {
        return '-';
    }
    if ($byte >= 1024 * 1024) {
        return r1($byte / 1048576) . ' MB';
    }
    if ($byte >= 1024) {
        return r0($byte / 1024) . ' KB';
    }
    return $byte . ' byte';
}

/** Label jenis berkas untuk tampilan (mis. "PDF", "Gambar (JPG)"). */
function dok_label_jenis(string $jenis, string $ext): string
{
    $ext = strtoupper($ext);
    if ($jenis === 'gambar') {
        return 'Gambar (' . ($ext !== '' ? $ext : 'GAMBAR') . ')';
    }
    if ($jenis === 'video') {
        return 'Video (' . ($ext !== '' ? $ext : 'VIDEO') . ')';
    }
    return $ext !== '' ? $ext : 'Dokumen';
}

/** Nama ikon tema untuk sebuah jenis berkas. */
function dok_ikon(string $jenis): string
{
    if ($jenis === 'gambar') {
        return 'eye';
    }
    if ($jenis === 'video') {
        return 'apps';
    }
    return 'doc';
}

/**
 * Validasi berkas yang akan dikirim.
 *
 * @return array{ok:bool,pesan:string,jenis:string,ext:string}
 */
function dok_validasi(string $namaAsli, int $ukuran, string $tmpPath): array
{
    $gagal = static fn(string $pesan): array => ['ok' => false, 'pesan' => $pesan, 'jenis' => '', 'ext' => ''];
    $ext = strtolower((string) pathinfo($namaAsli, PATHINFO_EXTENSION));
    $jenis = dok_jenis_ext($ext);
    if ($jenis === '') {
        return $gagal('Jenis berkas .' . ($ext !== '' ? $ext : '(tanpa ekstensi)')
            . ' belum didukung. Berkas yang dapat dikirim: ' . implode(', ', dok_ekstensi_semua()) . '.');
    }
    if ($ukuran <= 0) {
        return $gagal('Berkas kosong atau gagal diunggah.');
    }
    $batas = dok_batas_efektif($jenis);
    if ($ukuran > $batas) {
        return $gagal('Ukuran berkas maksimal ' . dok_ukuran($batas) . ' untuk ' . $jenis
            . ' (berkas Anda ' . dok_ukuran($ukuran) . ').'
            . ($batas < dok_batas_jenis($jenis) ? ' Batas ini mengikuti setelan server aplikasi.' : ''));
    }
    if ($tmpPath === '' || !is_file($tmpPath)) {
        return $gagal('Berkas tidak terbaca oleh server. Coba ulangi pengirimannya.');
    }

    // Isi berkas diperiksa sekadarnya supaya berkas yang hanya DIGANTI NAMANYA ikut tertolak
    // (pola yang sama dengan validasi_foto() untuk gambar).
    $kepala = (string) @file_get_contents($tmpPath, false, null, 0, 1024);
    if ($jenis === 'gambar' && $ext !== 'svg') {
        if (@getimagesize($tmpPath) === false) {
            return $gagal('Berkas ini sepertinya bukan gambar yang sah.');
        }
    } elseif ($ext === 'pdf') {
        if (strpos($kepala, '%PDF') === false) {
            return $gagal('Berkas ini sepertinya bukan PDF yang sah.');
        }
    } elseif (in_array($ext, ['docx', 'xlsx', 'pptx', 'zip'], true)) {
        if (strncmp($kepala, "PK", 2) !== 0) {
            return $gagal('Berkas ' . strtoupper($ext) . ' ini rusak atau bukan berkas ' . strtoupper($ext) . ' yang sah.');
        }
    }

    return ['ok' => true, 'pesan' => '', 'jenis' => $jenis, 'ext' => $ext];
}

// ------------------------------------------------------------------ data dokumen

/**
 * Daftar dokumen sekolah yang sedang aktif (terbaru lebih dulu).
 *
 * @param int $limit 0 = seluruhnya
 * @return array<int,array<string,mixed>>
 */
function dokumen_list(int $limit = 0): array
{
    // `pengirim_role` dipakai untuk membedakan kiriman **Kurikulum** (dari dalam sekolah) dengan
    // kiriman **Pengawas Binaan** (`kirim_dokumen.php`) tanpa perlu kolom baru — lihat dokumen_sumber().
    $sql = 'SELECT d.*, (SELECT u.role FROM users u WHERE u.id = d.pengirim_id) AS pengirim_role
            FROM dokumen d WHERE d.sekolah_id = ? ORDER BY d.dibuat DESC, d.id DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    return all($sql, [sekolah_id()]);
}

/**
 * Asal sebuah dokumen: **"Pengawas Binaan"** bila pengirimnya berperan `pengawas`, selain itu
 * **"Kurikulum"** (kiriman dari dalam sekolah / pengelola). Dipakai pada tampilan guru supaya jelas
 * dari mana dokumennya datang. Peran pengirim diambil lewat `dokumen_list()`/`dokumen_kirim_saya()`.
 */
function dokumen_sumber(array $d): string
{
    return (string) ($d['pengirim_role'] ?? '') === 'pengawas' ? 'Pengawas Binaan' : 'Kurikulum';
}

/**
 * Dokumen yang dikirim OLEH PENGGUNA YANG SEDANG MASUK (dipakai halaman "Kirim Dokumen" pengawas).
 * Dibatasi `pengirim_id` supaya seorang pengawas hanya melihat/menghapus kirimannya sendiri.
 *
 * @param int $sekolahId 0 = seluruh binaan
 */
function dokumen_kirim_saya(int $sekolahId = 0, int $limit = 0): array
{
    $uid = (int) (current_user()['id'] ?? 0);
    if ($uid <= 0) {
        return [];
    }
    $w = ['d.pengirim_id = ?'];
    $p = [$uid];
    if ($sekolahId > 0) {
        $w[] = 'd.sekolah_id = ?';
        $p[] = $sekolahId;
    }
    $sql = 'SELECT d.*, s.nama AS sekolah_nama FROM dokumen d
            LEFT JOIN sekolah s ON s.id = d.sekolah_id
            WHERE ' . implode(' AND ', $w) . '
            ORDER BY d.dibuat DESC, d.id DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    return all($sql, $p);
}

/** Ringkasan kiriman pengguna ini: jumlah dokumen + jumlah sekolah binaan yang pernah dikirimi. */
function dokumen_kirim_ringkas(): array
{
    $uid = (int) (current_user()['id'] ?? 0);
    if ($uid <= 0) {
        return ['dokumen' => 0, 'sekolah' => 0, 'bulan' => 0];
    }
    return [
        'dokumen' => (int) val('SELECT COUNT(*) FROM dokumen WHERE pengirim_id = ?', [$uid], 0),
        'sekolah' => (int) val('SELECT COUNT(DISTINCT sekolah_id) FROM dokumen WHERE pengirim_id = ?', [$uid], 0),
        'bulan'   => (int) val('SELECT COUNT(*) FROM dokumen WHERE pengirim_id = ? AND dibuat >= ?',
            [$uid, date('Y-m-d H:i:s', strtotime('-30 days'))], 0),
    ];
}

/**
 * Kirim SATU dokumen ke seluruh guru pada sekolah-sekolah tujuan (satu baris `dokumen` per sekolah,
 * karena data dokumen memang dipisah per sekolah).
 *
 * @param array<int,scalar> $sekolahIds sekolah tujuan (hanya sekolah aktif & disetujui yang diterima)
 * @param array<string,mixed> $isi judul, catatan, url, nama_berkas, jenis, ukuran
 * @return int jumlah sekolah yang benar-benar menerima dokumennya
 */
function dokumen_kirim(array $sekolahIds, array $isi): int
{
    $sid = [];
    foreach ($sekolahIds as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $sid[$id] = true;
        }
    }
    if (!$sid) {
        return 0;
    }
    $sah = [];
    foreach (all('SELECT id FROM sekolah WHERE aktif = 1 AND verifikasi = "disetujui" ORDER BY id') as $r) {
        $sah[(int) $r['id']] = true;
    }
    $user = current_user();
    $nama = (string) ($user['nama'] ?? '');
    $uid = (int) ($user['id'] ?? 0);
    $now = date('Y-m-d H:i:s');
    $kirim = 0;
    db()->exec('BEGIN IMMEDIATE');
    try {
        foreach (array_keys($sid) as $id) {
            if (!isset($sah[$id])) {
                continue;   // sekolah nonaktif / tidak disetujui tidak pernah dikirimi
            }
            q('INSERT INTO dokumen (sekolah_id, judul, catatan, url, nama_berkas, jenis, ukuran, pengirim, pengirim_id, dibuat)
               VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$id, (string) $isi['judul'], (string) ($isi['catatan'] ?? ''), (string) $isi['url'],
                 (string) ($isi['nama_berkas'] ?? ''), (string) ($isi['jenis'] ?? ''),
                 (int) ($isi['ukuran'] ?? 0), $nama, $uid, $now]);
            $kirim++;
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return $kirim;
}

/**
 * Hapus satu dokumen HANYA bila pengguna ini yang mengirimnya (anti-IDOR).
 * Dipakai halaman "Kirim Dokumen" pengawas (permintaan pemilik: pengawas hanya dapat menghapus
 * dokumen yang ia kirim sendiri).
 */
function dokumen_hapus_saya(int $id): ?array
{
    $uid = (int) (current_user()['id'] ?? 0);
    if ($id <= 0 || $uid <= 0) {
        return null;
    }
    $d = one('SELECT * FROM dokumen WHERE id = ? AND pengirim_id = ?', [$id, $uid]);
    if (!$d) {
        return null;
    }
    q('DELETE FROM dokumen WHERE id = ? AND pengirim_id = ?', [$id, $uid]);
    return $d;
}

function dokumen_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return one('SELECT * FROM dokumen WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
}

/** Jumlah dokumen sekolah ini. */
function dokumen_jumlah(): int
{
    return (int) val('SELECT COUNT(*) FROM dokumen WHERE sekolah_id = ?', [sekolah_id()], 0);
}

/** Jumlah dokumen yang dikirim dalam 30 hari terakhir (untuk kartu ringkasan). */
function dokumen_jumlah_bulan(): int
{
    return (int) val('SELECT COUNT(*) FROM dokumen WHERE sekolah_id = ? AND dibuat >= ?',
        [sekolah_id(), date('Y-m-d H:i:s', strtotime('-30 days'))], 0);
}

/** Ekstensi tersimpan pada sebuah baris dokumen (dari nama berkas aslinya). */
function dokumen_ext(array $d): string
{
    return strtolower((string) pathinfo((string) ($d['nama_berkas'] ?? ''), PATHINFO_EXTENSION));
}

/** Hapus satu dokumen sekolah ini (berkas di CDN dibiarkan — tidak ada API hapus di platform). */
function dokumen_hapus(int $id): bool
{
    $d = dokumen_by_id($id);
    if (!$d) {
        return false;
    }
    q('DELETE FROM dokumen WHERE id = ? AND sekolah_id = ?', [$id, sekolah_id()]);
    return true;
}

// ------------------------------------------------------------------ tabel & kartu

/** Tabel daftar dokumen (dipakai halaman Kurikulum & halaman pengawas).
 *
 * @param bool $tampilBinaan true = tambahkan kolom "Binaan" (halaman pengawas yang lintas sekolah)
 */
function dokumen_tabel(array $baris, bool $aksi, bool $tampilBinaan = false): void
{
    $kolom = $tampilBinaan ? 8 : 7;
    ?>
<div class="table-wrap" id="tabelDokumen">
  <table class="tbl tbl-dokumen">
    <thead>
      <tr>
        <th class="ctr k-no">No</th>
        <th class="k-judul">Judul Dokumen</th>
        <?php if ($tampilBinaan): ?><th class="k-binaan">Binaan</th><?php endif; ?>
        <th class="k-jenis">Jenis</th>
        <th class="ctr k-ukuran">Ukuran</th>
        <th class="k-dikirim">Dikirim</th>
        <th class="k-catatan">Catatan</th>
        <th class="ctr k-aksi">Tindakan</th>
      </tr>
    </thead>
    <tbody>
    <?php $n = 0; foreach ($baris as $d): $n++;
        $ext = dokumen_ext($d);
        $jenis = (string) ($d['jenis'] ?? dok_jenis_ext($ext));
        $tautan = tautan_aman((string) ($d['url'] ?? ''));
        $arsip = trim((string) ($d['nama_berkas'] ?? '')) !== '' ? (string) $d['nama_berkas'] : 'dokumen';
    ?>
      <tr>
        <td class="ctr"><?= $n ?></td>
        <td><b><?= e(tetap_kata((string) $d['judul'])) ?></b></td>
        <?php if ($tampilBinaan): ?>
          <td class="sm"><?= e(tetap_kata((string) ($d['sekolah_nama'] ?? '-'))) ?></td>
        <?php endif; ?>
        <td><span class="pill pill-B"><?= e(dok_label_jenis($jenis, $ext)) ?></span></td>
        <td class="ctr xs"><?= e(dok_ukuran((int) $d['ukuran'])) ?></td>
        <td class="sm"><?= e(waktu_id_polos((string) $d['dibuat'])) ?>
          <?php if (trim((string) ($d['pengirim'] ?? '')) !== ''): ?>
            <span class="tbl-sub">oleh <?= e(tetap_kata((string) $d['pengirim'])) ?></span>
          <?php endif; ?>
        </td>
        <td class="sm"><?= trim((string) ($d['catatan'] ?? '')) !== '' ? nl2br(e(tetap_kata((string) $d['catatan']))) : '-' ?></td>
        <td>
          <div class="row-actions">
            <?php if ($tautan !== ''): ?>
              <a class="btn btn-ghost btn-sm" href="<?= e($tautan) ?>" target="_blank" rel="noopener noreferrer"><?= ikon('download', 14) ?> Buka</a>
            <?php else: ?>
              <span class="hint xs">tautan tidak sah</span>
            <?php endif; ?>
            <?php if ($aksi): ?>
              <form method="post" data-confirm="Hapus dokumen &quot;<?= e((string) $d['judul']) ?>&quot; dari daftar guru?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Hapus</button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th colspan="2" class="ctr">Jumlah</th>
        <th colspan="<?= $kolom - 2 ?>"><?= count($baris) ?> dokumen dikirim ke seluruh guru</th>
      </tr>
    </tfoot>
  </table>
</div>
    <?php
}

/**
 * Kartu "inbox" dokumen untuk guru (dipakai dashboard & halaman "Dokumen Saya").
 *
 * @param array<int,array<string,mixed>> $baris
 * @param bool $denganJudulHalaman true = kartu besar dengan kepala (halaman Dokumen Saya)
 */
function dokumen_kartu(array $baris, bool $denganJudulHalaman = false): void
{
    if (!$baris) {
        ?>
        <div class="empty">
          <?= ikon('doc', 34) ?>
          <h3>Belum ada dokumen</h3>
          <p>Dokumen yang dikirim lewat menu <b>Kurikulum</b> oleh sekolah/madrasah ini, atau oleh
            <b>Pengawas Binaan</b>, akan muncul di sini — terlihat oleh seluruh guru
            <?= tetap_kata(nama_sekolah_tampil()) ?>.</p>
        </div>
        <?php
        return;
    }
    ?>
    <div class="grid g3 dok-grid">
      <?php foreach ($baris as $d): $ext = dokumen_ext($d);
          $jenis = (string) ($d['jenis'] ?? dok_jenis_ext($ext));
          $tautan = tautan_aman((string) ($d['url'] ?? ''));
      ?>
        <div class="card dok-kartu">
          <div class="dok-kartu-head">
            <div class="dok-ikon dok-<?= e($jenis !== '' ? $jenis : 'dokumen') ?>"><?= ikon(dok_ikon($jenis), 18) ?></div>
            <div class="dok-judul">
              <h3><?= e(tetap_kata((string) $d['judul'])) ?></h3>
              <span class="pill pill-B"><?= e(dok_label_jenis($jenis, $ext)) ?></span>
              <?php if ($denganJudulHalaman): // sumber hanya ditampilkan bila pengirimnya diketahui ?>
                <span class="pill <?= dokumen_sumber($d) === 'Pengawas Binaan' ? 'pill-warn' : 'pill-ok' ?>">
                  <?= e(dokumen_sumber($d)) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <p class="dok-catatan">
            <?= trim((string) ($d['catatan'] ?? '')) !== ''
                ? nl2br(e(tetap_kata((string) $d['catatan'])))
                : '<span class="mut">Tanpa catatan.</span>' ?>
          </p>
          <div class="dok-kaki">
            <span class="xs mut"><?= e(waktu_id_polos((string) $d['dibuat'])) ?>
              <?= trim((string) ($d['pengirim'] ?? '')) !== '' ? ' &middot; ' . e(tetap_kata((string) $d['pengirim'])) : '' ?>
              &middot; <?= e(dok_ukuran((int) $d['ukuran'])) ?></span>
          </div>
          <div class="dok-aksi">
            <?php if ($tautan !== ''): ?>
              <a class="btn btn-primary btn-sm" href="<?= e($tautan) ?>" target="_blank" rel="noopener noreferrer">
                <?= ikon('download', 15) ?> <?= $jenis === 'gambar' ? 'Lihat' : 'Buka / Unduh' ?>
              </a>
            <?php else: ?>
              <span class="hint">Tautan dokumen tidak sah — hubungi pengirimnya.</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
}

// ------------------------------------------------------------------ halaman: KURIKULUM (kirim)

/**
 * Jalankan halaman menu "Kurikulum" (dipakai `tugas.php?kode=kurikulum`).
 *
 * @param array<string,mixed> $tugas baris `tugas_menu` (judul mengikuti nama menu hasil ubah)
 */
function kurikulum_halaman(array $tugas): void
{
    require_sekolah();

    $namaMenu = (string) ($tugas['nama'] ?? 'Kurikulum');
    // Pengawas = HANYA LIHAT (aturan yang sama dengan menu Tugas Tambahan lain).
    // Permintaan pemilik: kepala sekolah/madrasah & admin operator HANYA melihat + mencetak;
    // pengisian menu Tugas Tambahan dilakukan guru yang ditugaskan (lihat boleh_ubah_tugas_menu()).
    $bolehUbah = boleh_ubah_tugas_menu();
    if (!$bolehUbah && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        tolak_ubah_tugas_menu('dokumen Kurikulum');
    }

    // ---------------------------------------------------------------- POST
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');

        if ($aksi === 'hapus') {
            $id = (int) ($_POST['id'] ?? 0);
            $d = dokumen_by_id($id);
            if (!$d) {
                flash('Dokumen tidak ditemukan.', 'err');
                redirect('tugas.php?kode=' . e(KURIKULUM_KODE_MENU));
            }
            dokumen_hapus($id);
            flash('Dokumen "' . tetap_kata((string) $d['judul']) . '" dihapus dari daftar guru.', 'warn');
            redirect('tugas.php?kode=' . e(KURIKULUM_KODE_MENU) . '#tabelDokumen');
        }

        if ($aksi === 'kirim') {
            // PHP MEMBUANG seluruh $_POST/$_FILES bila ukuran kiriman melebihi post_max_size — tanpa
            // pemeriksaan ini gejalanya "tidak terjadi apa-apa" (pelajaran dari foto bukti supervisi).
            $panjang = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($panjang > 0 && !$_POST && !$_FILES) {
                flash('Ukuran kiriman melebihi batas server (' . dok_ukuran(dok_batas_server())
                    . '). Kirim berkas yang lebih kecil, atau minta pengelola platform menaikkan batasnya.', 'err');
                redirect('tugas.php?kode=' . e(KURIKULUM_KODE_MENU) . '#formDokumen');
            }

            $judul = trim((string) ($_POST['judul'] ?? ''));
            $catatan = trim((string) ($_POST['catatan'] ?? ''));
            if (strlen($judul) > DOK_JUDUL_MAKS) {
                $judul = substr($judul, 0, DOK_JUDUL_MAKS);
            }
            if (strlen($catatan) > DOK_CATATAN_MAKS) {
                $catatan = substr($catatan, 0, DOK_CATATAN_MAKS);
            }
            if ($judul === '') {
                flash('Judul Dokumen wajib diisi.', 'err');
                redirect('tugas.php?kode=' . e(KURIKULUM_KODE_MENU) . '#formDokumen');
            }

            $berkas = $_FILES['berkas'] ?? null;
            if (!is_array($berkas) || (int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $kode = is_array($berkas) ? (int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
                $pesan = $kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE
                    ? 'Berkas terlalu besar untuk setelan server (maksimal ' . dok_ukuran(dok_batas_server()) . ').'
                    : 'Berkas wajib dipilih untuk dikirim.';
                flash($pesan, 'err');
                redirect('tugas.php?kode=' . e(KURIKULUM_KODE_MENU) . '#formDokumen');
            }

            $namaAsli = (string) ($berkas['name'] ?? 'dokumen');
            $ukuran = (int) ($berkas['size'] ?? 0);
            $tmp = (string) ($berkas['tmp_name'] ?? '');
            $v = dok_validasi($namaAsli, $ukuran, $tmp);
            if (!$v['ok']) {
                flash($v['pesan'], 'err');
                redirect('tugas.php?kode=' . e(KURIKULUM_KODE_MENU) . '#formDokumen');
            }

            $bytes = @file_get_contents($tmp);
            if ($bytes === false || $bytes === '') {
                flash('Berkas tidak dapat dibaca dari server. Coba ulangi.', 'err');
                redirect('tugas.php?kode=' . e(KURIKULUM_KODE_MENU) . '#formDokumen');
            }
            $unggah = media_upload($bytes, $namaAsli);
            if (empty($unggah['ok'])) {
                flash((string) ($unggah['pesan'] ?? 'Berkas gagal diunggah.'), 'err');
                redirect('tugas.php?kode=' . e(KURIKULUM_KODE_MENU) . '#formDokumen');
            }

            $user = current_user();
            q('INSERT INTO dokumen (sekolah_id, judul, catatan, url, nama_berkas, jenis, ukuran, pengirim, pengirim_id, dibuat)
               VALUES (?,?,?,?,?,?,?,?,?,?)',
                [sekolah_id(), $judul, $catatan, (string) $unggah['url'], $namaAsli,
                 $v['jenis'], $ukuran, (string) ($user['nama'] ?? ''), (int) ($user['id'] ?? 0),
                 date('Y-m-d H:i:s')]);
            flash('Dokumen "' . tetap_kata($judul) . '" terkirim ke seluruh guru ' . nama_sekolah_tampil() . '.', 'ok');
            redirect('tugas.php?kode=' . e(KURIKULUM_KODE_MENU) . '#tabelDokumen');
        }

        redirect('tugas.php?kode=' . e(KURIKULUM_KODE_MENU));
    }

    // ---------------------------------------------------------------- tampilan
    $baris = dokumen_list();
    $jumlah = count($baris);
    $jumlahBulan = dokumen_jumlah_bulan();
    $guruAktif = (int) val('SELECT COUNT(*) FROM guru WHERE aktif = 1 AND sekolah_id = ?', [sekolah_id()], 0);
    $namaSekolah = nama_sekolah_tampil();
    $batasKirim = dok_batas_server();

    page_top($namaMenu, 'Kirim Dokumen ke Guru &middot; ' . tetap_kata($namaSekolah));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('doc', 16) ?> <?= e($namaMenu) ?> &mdash; Kirim Dokumen ke Guru</h2>
      <p>Kirim <b>dokumen</b> (PDF, gambar, atau berkas lain) kepada <b>seluruh guru
        <?= tetap_kata($namaSekolah) ?></b>. Dokumen yang dikirim langsung muncul di
        <b>dashboard guru</b> dan halaman <b>Dokumen Saya</b> pada akun guru sekolah ini.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $jumlah ?> dokumen</span>
      <span class="chip"><?= (int) $guruAktif ?> guru penerima</span>
    </div>
  </div>

  <div class="grid g3" style="gap:16px">
    <div class="card stat card-tight" style="margin:0">
      <div class="stat-ic g"><?= ikon('doc', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $jumlah ?></div><div class="stat-lbl">Dokumen terkirim</div></div>
    </div>
    <div class="card stat card-tight" style="margin:0">
      <div class="stat-ic"><?= ikon('calendar', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $jumlahBulan ?></div><div class="stat-lbl">30 hari terakhir</div></div>
    </div>
    <div class="card stat card-tight" style="margin:0">
      <div class="stat-ic p"><?= ikon('users', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $guruAktif ?></div><div class="stat-lbl">Guru penerima</div></div>
    </div>
  </div>

  <?php if (!$bolehUbah): ?>
    <div class="alert alert-warn" style="margin-top:16px">
      <?= ikon('eye', 16) ?>
      <span><?= pemberitahuan_hanya_lihat('dokumen yang dikirim ke guru') ?></span>
    </div>
  <?php else: ?>
    <?php // Form multipart BIASA (bukan fetch): unggahan tetap bekerja walau skrip gagal dimuat. ?>
    <div class="card" id="formDokumen" style="margin-top:18px">
      <div class="card-head">
        <div>
          <h2><?= ikon('upload', 16) ?> Unggah Dokumen ke Guru</h2>
          <p>Isi <b>Judul Dokumen</b> dan <b>Catatan</b>, lalu pilih <b>berkasnya</b>. Berkas langsung
            dikirim ke seluruh guru sekolah ini.</p>
        </div>
      </div>
      <form method="post" enctype="multipart/form-data" class="form-grid" id="formKirimDokumen">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="kirim">
        <div class="grid g2" style="gap:16px">
          <div>
            <label class="f" for="judul">Judul Dokumen *</label>
            <input type="text" id="judul" name="judul" maxlength="<?= (int) DOK_JUDUL_MAKS ?>" required
                   value="" placeholder="mis. Program Kerja Kurikulum Semester Ganjil">
          </div>
          <div>
            <label class="f" for="berkas">Unggah Berkas *</label>
            <input type="file" id="berkas" name="berkas" required
                   accept="<?= e('.' . implode(',.', dok_ekstensi_semua())) ?>" style="padding:8px">
            <div class="hint" id="infoBerkas">
              Diterima: <?= e(implode(', ', dok_ekstensi_semua())) ?>.
              Ukuran maksimal <b><?= e(dok_ukuran($batasKirim)) ?></b> per berkas
              (batas server aplikasi ini).
            </div>
          </div>
        </div>
        <div>
          <label class="f" for="catatan">Catatan</label>
          <textarea id="catatan" name="catatan" rows="3" maxlength="<?= (int) DOK_CATATAN_MAKS ?>"
                    placeholder="mis. Mohon dipelajari sebelum rapat kurikulum, lalu diterapkan di kelas masing-masing."></textarea>
        </div>
        <div class="flex">
          <button class="btn btn-primary" type="submit"><?= ikon('upload', 16) ?> Kirim ke Semua Guru</button>
          <span class="hint" style="align-self:center">Berkas disimpan di penyimpanan platform; yang
            tercatat di aplikasi hanya tautannya.</span>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <div class="divider"></div>
  <div class="section-title mb8"><?= ikon('clipboard', 15) ?> Daftar Dokumen Terkirim (<?= (int) $jumlah ?>)</div>
  <?php if (!$baris): ?>
    <div class="empty"><?= ikon('doc', 34) ?>
      <h3>Belum ada dokumen yang dikirim</h3>
      <p><?= $bolehUbah
        ? 'Isi formulir <b>Unggah Dokumen ke Guru</b> di atas untuk mengirim dokumen pertama.'
        : pesan_kosong_hanya_lihat('dokumen yang dikirim ke guru') ?></p>
    </div>
  <?php else: ?>
    <?php dokumen_tabel($baris, $bolehUbah); ?>
    <?php if ($bolehUbah): ?>
      <p class="hint mt16">Dokumen yang dihapus akan hilang dari akun seluruh guru
        <?= tetap_kata($namaSekolah) ?>.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
    <?php
    page_bottom();
}

// ------------------------------------------------------------------ halaman: DOKUMEN SAYA (guru)

/**
 * Jalankan halaman "Dokumen Saya" (dipakai `dokumen_saya.php`) — daftar dokumen dari Kurikulum
 * untuk sekolah pengguna yang sedang masuk.
 */
function dokumen_halaman_guru(): void
{
    require_login();
    require_sekolah();
    // Halaman ini memuat data satu sekolah → pengawas dibiarkan memakai menu Kurikulum (hanya lihat),
    // jadi di sini cukup penjaga konteks sekolah seperti halaman data lain.

    $baris = dokumen_list();
    $jumlah = count($baris);
    $namaSekolah = nama_sekolah_tampil();
    $terbaru = $baris ? waktu_id_polos((string) $baris[0]['dibuat']) : '';

    page_top('Dokumen Saya', 'Dokumen dari Kurikulum &middot; ' . tetap_kata($namaSekolah));
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('doc', 16) ?> Dokumen Saya</h2>
      <p>Dokumen yang dikirim <b>Kurikulum</b> <?= tetap_kata($namaSekolah) ?> maupun
        <b>Pengawas Binaan</b> kepada seluruh guru.
        <?php if ($terbaru !== ''): ?>Dokumen terbaru: <b><?= e($terbaru) ?></b>.<?php endif; ?></p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $jumlah ?> dokumen</span>
    </div>
  </div>

  <?php if ($baris): ?>
    <div class="alert alert-ok" style="background:rgba(201,162,39,.07);border-color:rgba(201,162,39,.45)">
      <?= ikon('doc', 16) ?>
      <span>Klik <b>Buka / Unduh</b> untuk membuka dokumennya. Berkas terbuka pada tab baru —
        dokumen gambar bisa langsung dilihat, yang lain bisa diunduh.</span>
    </div>
  <?php endif; ?>

  <?php dokumen_kartu($baris, true); ?>
</div>
    <?php
    page_bottom();
}

// ------------------------------------------------------------------ halaman: KIRIM DOKUMEN (pengawas)

/**
 * Jalankan halaman **"Kirim Dokumen"** milik akun **Pengawas Sekolah/Madrasah**
 * (`kirim_dokumen.php`).
 *
 * Permintaan pemilik: *"Pada halaman Pengawas tambahkan Menu Kirim Dokumen (Dokumen yang diunggah
 * oleh pengawas akan terkirim ke semua akun guru madrasah binaannya). Fiturnya seperti pada menu
 * kurikulum."*
 * Pilihan pemilik (jangan diubah tanpa bertanya):
 *   1. tujuan kiriman bisa **binaan ini saja** (satu sekolah) atau **semua binaan sekaligus**;
 *   2. menunya diletakkan di blok **Aplikasi** sidebar pengawas — selalu terlihat, **tanpa perlu
 *      memilih binaan lebih dulu**;
 *   3. pengawas hanya dapat **menghapus dokumen yang ia kirim sendiri** (`dokumen_hapus_saya()`).
 *
 * Karena tidak memerlukan konteks sekolah, halaman ini **tidak** memanggil `require_sekolah()`.
 * Daftar binaan diambil dari `sekolah_binaan()`; pengiriman ke satu sekolah memakai `dokumen_kirim()`
 * dengan satu id, sedangkan "semua binaan" memakai seluruh id binaan sekaligus (satu baris `dokumen`
 * per sekolah, karena data dokumen memang dipisah per sekolah).
 *
 * Pembatasan akun pengawas tetap berlaku lapis kedua lewat `gerbang_pengawas()`: berkas ini
 * didaftarkan pada `pengawas_berkas_boleh()` **dan** `pengawas_berkas_post()` (`inc/helpers.php`).
 */
function dokumen_halaman_pengawas(): void
{
    $u = require_login();
    if (!is_pengawas()) {
        tolak403('Menu Kirim Dokumen ini khusus akun Pengawas Sekolah/Madrasah.');
    }

    $uid = (int) $u['id'];
    $binaan = sekolah_binaan($uid, true);
    $namaBinaan = [];
    foreach ($binaan as $s) {
        $namaBinaan[(int) $s['id']] = (string) $s['nama'];
    }

    // Binaan yang sedang dibuka (konteks) — dipakai sebagai pilihan awal & penyaring daftar.
    $konteks = current_sekolah();
    $konteksId = $konteks ? (int) $konteks['id'] : 0;
    if ($konteksId > 0 && !isset($namaBinaan[$konteksId])) {
        $konteksId = 0;   // konteks di luar binaan aktif → diabaikan
    }

    $fBinaan = (int) ($_GET['binaan'] ?? 0);
    if ($fBinaan > 0 && !isset($namaBinaan[$fBinaan])) {
        $fBinaan = 0;
    }

    // ---------------------------------------------------------------- POST
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');

        if ($aksi === 'hapus') {
            $id = (int) ($_POST['id'] ?? 0);
            $d = dokumen_hapus_saya($id);
            if (!$d) {
                flash('Dokumen itu tidak ditemukan, atau bukan dokumen yang Anda kirim. '
                    . 'Pengawas hanya dapat menghapus dokumen yang ia kirim sendiri.', 'err');
            } else {
                flash('Dokumen "' . tetap_kata((string) $d['judul']) . '" dihapus dari daftar guru.',
                    'warn');
            }
            redirect('kirim_dokumen.php' . ($fBinaan > 0 ? '?binaan=' . $fBinaan : '') . '#tabelDokumen');
        }

        if ($aksi === 'kirim') {
            // PHP MEMBUANG seluruh $_POST/$_FILES bila kiriman melebihi post_max_size (pelajaran dari
            // foto bukti supervisi & menu Kurikulum) → tanpa pemeriksaan ini gejalanya "tidak terjadi apa-apa".
            $panjang = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
            if ($panjang > 0 && !$_POST && !$_FILES) {
                flash('Ukuran kiriman melebihi batas server (' . dok_ukuran(dok_batas_server())
                    . '). Kirim berkas yang lebih kecil.', 'err');
                redirect('kirim_dokumen.php#formDokumen');
            }

            $tujuan = (string) ($_POST['tujuan'] ?? 'semua');
            $sekolahTujuan = [];
            if ($tujuan === 'semua') {
                foreach (array_keys($namaBinaan) as $id) {
                    $sekolahTujuan[] = $id;
                }
            } else {
                $id = (int) $tujuan;
                if (!isset($namaBinaan[$id])) {
                    flash('Binaan tujuan tidak sah. Pilih binaan yang menjadi binaan Anda.', 'err');
                    redirect('kirim_dokumen.php#formDokumen');
                }
                $sekolahTujuan[] = $id;
            }
            if (!$sekolahTujuan) {
                flash('Akun Anda belum memiliki sekolah/madrasah binaan, jadi belum ada tujuan '
                    . 'pengiriman. Hubungi Super Admin untuk menetapkan binaan Anda.', 'err');
                redirect('kirim_dokumen.php#formDokumen');
            }

            $judul = trim((string) ($_POST['judul'] ?? ''));
            $catatan = trim((string) ($_POST['catatan'] ?? ''));
            if (strlen($judul) > DOK_JUDUL_MAKS) {
                $judul = substr($judul, 0, DOK_JUDUL_MAKS);
            }
            if (strlen($catatan) > DOK_CATATAN_MAKS) {
                $catatan = substr($catatan, 0, DOK_CATATAN_MAKS);
            }
            if ($judul === '') {
                flash('Judul Dokumen wajib diisi.', 'err');
                redirect('kirim_dokumen.php#formDokumen');
            }

            $berkas = $_FILES['berkas'] ?? null;
            if (!is_array($berkas) || (int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $kode = is_array($berkas) ? (int) ($berkas['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
                $pesan = $kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE
                    ? 'Berkas terlalu besar untuk setelan server (maksimal ' . dok_ukuran(dok_batas_server()) . ').'
                    : 'Berkas wajib dipilih untuk dikirim.';
                flash($pesan, 'err');
                redirect('kirim_dokumen.php#formDokumen');
            }

            $namaAsli = (string) ($berkas['name'] ?? 'dokumen');
            $ukuran = (int) ($berkas['size'] ?? 0);
            $tmp = (string) ($berkas['tmp_name'] ?? '');
            $v = dok_validasi($namaAsli, $ukuran, $tmp);
            if (!$v['ok']) {
                flash($v['pesan'], 'err');
                redirect('kirim_dokumen.php#formDokumen');
            }

            $bytes = @file_get_contents($tmp);
            if ($bytes === false || $bytes === '') {
                flash('Berkas tidak dapat dibaca dari server. Coba ulangi.', 'err');
                redirect('kirim_dokumen.php#formDokumen');
            }
            $unggah = media_upload($bytes, $namaAsli);
            if (empty($unggah['ok'])) {
                flash((string) ($unggah['pesan'] ?? 'Berkas gagal diunggah.'), 'err');
                redirect('kirim_dokumen.php#formDokumen');
            }

            $kirim = dokumen_kirim($sekolahTujuan, [
                'judul' => $judul,
                'catatan' => $catatan,
                'url' => (string) $unggah['url'],
                'nama_berkas' => $namaAsli,
                'jenis' => (string) $v['jenis'],
                'ukuran' => $ukuran,
            ]);
            if ($kirim <= 0) {
                flash('Dokumen gagal dikirim: tidak ada sekolah/madrasah binaan yang aktif.', 'err');
                redirect('kirim_dokumen.php#formDokumen');
            }
            $label = $tujuan === 'semua'
                ? 'seluruh guru pada ' . $kirim . ' sekolah/madrasah binaan'
                : 'seluruh guru ' . ($namaBinaan[(int) $sekolahTujuan[0]] ?? 'binaan');
            flash('Dokumen "' . tetap_kata($judul) . '" terkirim ke ' . tetap_kata($label) . '.', 'ok');
            redirect('kirim_dokumen.php' . ($tujuan === 'semua' ? '' : '?binaan=' . (int) $sekolahTujuan[0]) . '#tabelDokumen');
        }

        redirect('kirim_dokumen.php');
    }

    // ---------------------------------------------------------------- tampilan
    $baris = dokumen_kirim_saya($fBinaan);
    $jumlah = count($baris);
    $ringkas = dokumen_kirim_ringkas();
    $batasKirim = dok_batas_server();
    $jumlahGuru = 0;
    foreach (array_keys($namaBinaan) as $id) {
        $jumlahGuru += (int) val('SELECT COUNT(*) FROM guru WHERE aktif = 1 AND sekolah_id = ?', [$id], 0);
    }

    page_top('Kirim Dokumen', 'Kirim dokumen ke seluruh guru sekolah/madrasah binaan');
    ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= ikon('doc', 16) ?> Kirim Dokumen ke Guru Binaan</h2>
      <p>Kirim <b>dokumen</b> (PDF, gambar, atau berkas lain) kepada <b>seluruh guru</b> sekolah/madrasah
        binaan Anda. Dokumen yang dikirim langsung muncul di <b>dashboard guru</b> dan halaman
        <b>Dokumen Saya</b> pada akun guru sekolah/madrasah tujuan — sama seperti dokumen dari menu
        <b>Kurikulum</b> di sekolahnya.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($binaan) ?> binaan</span>
      <span class="chip"><?= (int) $jumlahGuru ?> guru penerima</span>
    </div>
  </div>

  <div class="grid g3" style="gap:16px">
    <div class="card stat card-tight" style="margin:0">
      <div class="stat-ic g"><?= ikon('doc', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['dokumen'] ?></div>
        <div class="stat-lbl">Dokumen yang saya kirim</div></div>
    </div>
    <div class="card stat card-tight" style="margin:0">
      <div class="stat-ic s"><?= ikon('school', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['sekolah'] ?></div>
        <div class="stat-lbl">Binaan pernah dikirimi</div></div>
    </div>
    <div class="card stat card-tight" style="margin:0">
      <div class="stat-ic o"><?= ikon('calendar', 18) ?></div>
      <div><div class="stat-num" style="font-size:1.3rem"><?= (int) $ringkas['bulan'] ?></div>
        <div class="stat-lbl">30 hari terakhir</div></div>
    </div>
  </div>

  <?php if (!$binaan): ?>
    <div class="empty" style="margin-top:18px">
      <?= ikon('school', 34) ?>
      <h3>Belum ada sekolah/madrasah binaan</h3>
      <p>Akun pengawas Anda belum ditetapkan sekolah/madrasah binaan, jadi belum ada tujuan
        pengiriman. Hubungi <b>Super Admin</b> untuk menetapkan binaan Anda pada menu
        <b>Akun Pengawas</b>.</p>
    </div>
  <?php else: ?>
    <?php // Form multipart BIASA (bukan fetch): unggahan tetap bekerja walau skrip gagal dimuat. ?>
    <div class="card" id="formDokumen" style="margin-top:18px">
      <div class="card-head">
        <div>
          <h2><?= ikon('upload', 16) ?> Unggah Dokumen ke Guru</h2>
          <p>Isi <b>Judul Dokumen</b> dan <b>Catatan</b>, pilih <b>tujuan pengiriman</b>, lalu pilih
            <b>berkasnya</b>.</p>
        </div>
      </div>
      <form method="post" enctype="multipart/form-data" class="form-grid" id="formKirimDokumen">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="kirim">
        <div class="grid g2" style="gap:16px">
          <div>
            <label class="f" for="judul">Judul Dokumen *</label>
            <input type="text" id="judul" name="judul" maxlength="<?= (int) DOK_JUDUL_MAKS ?>" required
                   value="" placeholder="mis. Panduan Penilaian Kurikulum Merdeka">
          </div>
          <div>
            <label class="f" for="tujuan">Kirim ke *</label>
            <?php $tujuanAwal = $konteksId > 0 ? (string) $konteksId : 'semua'; ?>
            <select id="tujuan" name="tujuan" required>
              <option value="semua" <?= $tujuanAwal === 'semua' ? 'selected' : '' ?>>
                Semua binaan (<?= count($binaan) ?> sekolah/madrasah)
              </option>
              <?php foreach ($binaan as $s): $bid = (int) $s['id']; ?>
                <option value="<?= $bid ?>" <?= $tujuanAwal === (string) $bid ? 'selected' : '' ?>>
                  Binaan ini saja: <?= e((string) $s['nama']) ?><?= $bid === $konteksId ? ' (sedang dibuka)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="hint">Pilih <b>satu binaan</b> bila dokumennya hanya untuk sekolah itu, atau
              <b>semua binaan</b> bila sama untuk seluruh sekolah/madrasah binaan Anda.</div>
          </div>
        </div>
        <div class="grid g2" style="gap:16px">
          <div>
            <label class="f" for="berkas">Unggah Berkas *</label>
            <input type="file" id="berkas" name="berkas" required
                   accept="<?= e('.' . implode(',.', dok_ekstensi_semua())) ?>" style="padding:8px">
            <div class="hint" id="infoBerkas">
              Diterima: <?= e(implode(', ', dok_ekstensi_semua())) ?>.
              Ukuran maksimal <b><?= e(dok_ukuran($batasKirim)) ?></b> per berkas (batas server aplikasi ini).
            </div>
          </div>
        </div>
        <div>
          <label class="f" for="catatan">Catatan</label>
          <textarea id="catatan" name="catatan" rows="3" maxlength="<?= (int) DOK_CATATAN_MAKS ?>"
                    placeholder="mis. Mohon dipedomani dalam penyusunan administrasi pembelajaran."></textarea>
        </div>
        <div class="flex">
          <button class="btn btn-primary" type="submit"><?= ikon('upload', 16) ?> Kirim ke Guru</button>
          <span class="hint" style="align-self:center">Berkas disimpan di penyimpanan platform; yang
            tercatat di aplikasi hanya tautannya.</span>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <div class="divider"></div>
  <div class="section-title mb8"><?= ikon('clipboard', 15) ?> Dokumen yang Saya Kirim (<?= (int) $jumlah ?>)</div>

  <?php if ($binaan && count($binaan) > 1): ?>
    <form class="filterbar mb16" method="get">
      <div style="min-width:260px">
        <label class="f" for="binaan">Saring binaan</label>
        <select id="binaan" name="binaan" data-auto>
          <option value="">— semua binaan —</option>
          <?php foreach ($binaan as $s): $bid = (int) $s['id']; ?>
            <option value="<?= $bid ?>" <?= $fBinaan === $bid ? 'selected' : '' ?>><?= e((string) $s['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($fBinaan > 0): ?>
        <a class="btn btn-ghost" href="kirim_dokumen.php">Reset</a>
      <?php endif; ?>
    </form>
  <?php endif; ?>

  <?php if (!$baris): ?>
    <div class="empty"><?= ikon('doc', 34) ?>
      <h3>Belum ada dokumen yang Anda kirim</h3>
      <p><?= $binaan
        ? 'Isi formulir <b>Unggah Dokumen ke Guru</b> di atas untuk mengirim dokumen pertama.'
        : 'Dokumen dapat dikirim setelah Super Admin menetapkan sekolah/madrasah binaan untuk akun Anda.' ?></p>
    </div>
  <?php else: ?>
    <?php dokumen_tabel($baris, true, true); ?>
    <p class="hint mt16">Dokumen yang dihapus akan hilang dari akun guru sekolah/madrasah tujuan.
      Anda hanya dapat menghapus dokumen yang <b>Anda kirim sendiri</b>.</p>
  <?php endif; ?>
</div>
    <?php
    page_bottom();
}
