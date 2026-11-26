<?php
/**
 * PERMINTAAN MEDIA — tab "Buatkan Media" pada menu Media Interaktif (akun GURU) dan menu
 * "Permintaan Media" pada akun SUPER ADMIN.
 *
 * Permintaan pemilik (v1.111.0 / skema v69): *"Pada yang saya tandai warna merah tambahkan tab
 * 'Buatkan Media'. Isinya data media yang akan diminta: Nama Guru, Asal Sekolah, Judul Media.
 * Permintaan ini akan terkirim ke super admin. Jadi di halaman super admin tambahkan Menu Permintaan
 * Media dengan isi daftar Guru + Judul media yang diminta dan tambahkan fitur aksi (Sedang ditinjau,
 * media telah dibuat). Status sedang ditinjau atau media telah dibuat akan muncul juga di akun guru
 * yang meminta"*.
 *
 * Keputusan yang dipakai (jangan diubah tanpa bertanya kepada pemilik):
 *   1. Letak permintaan guru = **TAB ke-5** pada baris pil menu Media Interaktif (kotak merah pada
 *      tangkapan layar = tepat setelah pil "Game Edukasi"), BUKAN menu sidebar baru. Tab ini HANYA
 *      tampil untuk akun guru — Super Admin mengelola permintaannya lewat menu sendiri.
 *   2. Isian yang diminta PERSIS tiga kolom: **Nama Guru, Asal Sekolah, Judul Media**. Nama guru &
 *      asal sekolah terisi otomatis dari akun/lembaganya, tetapi tetap boleh disesuaikan guru.
 *   3. Status hanya DUA — `ditinjau` ("Sedang ditinjau", keadaan awal saat dikirim) dan `selesai`
 *      ("Media telah dibuat", ditetapkan Super Admin). Daftar sahnya: MEDIA_PERMINTAAN_STATUS
 *      (inc/config.php).
 *   4. Permintaan disimpan sebagai **snapshot** teks yang diketik guru (`nama_guru`, `asal_sekolah`)
 *      sehingga riwayatnya tetap terbaca walau nama guru/lembaganya kelak diubah atau akunnya
 *      dihapus (kolom id-nya ON DELETE SET NULL, bukan CASCADE).
 *   5. Guru melihat status permintaan MILIKNYA SENDIRI saja (dibatasi `user_id` akun yang masuk);
 *      Super Admin melihat SELURUH permintaan semua sekolah/madrasah.
 *
 * ⚠️ JANGAN menyalin logika ini ke halaman/halaman lain: `permintaan_media.php` dan pemanggilan tab
 * pada `inc/daftar_apps.php` hanya meneruskan ke fungsi-fungsi di sini.
 */
declare(strict_types=1);

// ============================================================================
// STATUS
// ============================================================================

/** Kunci status yang sah ('' bila tidak dikenal). */
function media_permintaan_status_sah($status): string
{
    $s = trim((string) $status);
    return isset(MEDIA_PERMINTAAN_STATUS[$s]) ? $s : '';
}

/** Label status ("Sedang ditinjau" / "Media telah dibuat"). */
function media_permintaan_status_label(string $status): string
{
    $s = media_permintaan_status_sah($status);
    return $s === '' ? '-' : (string) MEDIA_PERMINTAAN_STATUS[$s];
}

/** Kelas pil status: "Sedang ditinjau" = kuning (perlu ditindak), "Media telah dibuat" = hijau. */
function media_permintaan_status_pil(string $status): string
{
    return media_permintaan_status_sah($status) === 'selesai' ? 'pill-ok' : 'pill-warn';
}

/** Daftar [kunci => label] untuk pilihan/penyaring. */
function media_permintaan_status_list(): array
{
    return MEDIA_PERMINTAAN_STATUS;
}

// ============================================================================
// KELAS & FASE (v1.111.1 / skema v70)
// ============================================================================

/**
 * Daftar [nilai => label] pilihan **Kelas** ("1" => "Kelas 1" … "12" => "Kelas 12").
 *
 * @return array<string,string>
 */
function media_permintaan_kelas_list(): array
{
    $out = [];
    for ($k = 1; $k <= MEDIA_PERMINTAAN_KELAS_MAKS; $k++) {
        $out[(string) $k] = 'Kelas ' . $k;
    }
    return $out;
}

/** Kelas yang sah ("4"); '' bila kosong/tidak dikenal (mis. "99", "kelas 4"). */
function media_permintaan_kelas_sah($kelas): string
{
    $teks = trim((string) $kelas);
    if ($teks === '' || !preg_match('/^\d{1,2}$/', $teks)) {
        return '';
    }
    $n = (int) $teks;
    return ($n >= 1 && $n <= MEDIA_PERMINTAAN_KELAS_MAKS) ? (string) $n : '';
}

/** Fase yang sah ("A".."F", huruf besar); '' bila kosong/tidak dikenal. */
function media_permintaan_fase_sah($fase): string
{
    $f = strtoupper(trim((string) $fase));
    return isset(MEDIA_PERMINTAAN_FASE[$f]) ? $f : '';
}

/**
 * Fase yang sesuai satu kelas pada Kurikulum Merdeka — SATU sumber padanan:
 * Kelas 1-2 = Fase A · 3-4 = B · 5-6 = C · 7-9 = D · 10 = E · 11-12 = F.
 *
 * Dipakai server sebagai cadangan (kiriman tanpa fase / peramban tanpa JavaScript) dan oleh
 * `initKelasFase()` di assets/app.js (padanan yang sama, disalin ke sana untuk mengisi otomatis).
 */
function media_permintaan_fase_dari_kelas($kelas): string
{
    $k = (int) media_permintaan_kelas_sah($kelas);
    if ($k <= 0) {
        return '';
    }
    if ($k <= 2) {
        return 'A';
    }
    if ($k <= 4) {
        return 'B';
    }
    if ($k <= 6) {
        return 'C';
    }
    if ($k <= 9) {
        return 'D';
    }
    return $k === 10 ? 'E' : 'F';
}

/** Label tampil satu kelas ("Kelas 4"); "-" bila kosong (permintaan lama sebelum skema v70). */
function media_permintaan_kelas_label($kelas): string
{
    $k = media_permintaan_kelas_sah($kelas);
    return $k === '' ? '-' : 'Kelas ' . $k;
}

/** Label tampil satu fase ("Fase B"); "-" bila kosong. */
function media_permintaan_fase_label($fase): string
{
    $f = media_permintaan_fase_sah($fase);
    return $f === '' ? '-' : 'Fase ' . $f;
}

/** Label tampil mata pelajaran ("Matematika"); "-" bila kosong (permintaan lama sebelum skema v71). */
function media_permintaan_mapel_label($mapel): string
{
    $m = trim((string) $mapel);
    return $m === '' ? '-' : $m;
}

/**
 * Ringkasan untuk baris kecil di bawah judul media: "Matematika · Kelas 4 · Fase B".
 *
 * Bagian yang kosong dilewati; bila SEMUANYA kosong hasilnya '' (barisnya tidak dicetak) — permintaan
 * lama (sebelum skema v70/v71) tidak dipaksa menampilkan deretan "-". Dipakai tabel "Permintaan Media
 * Saya" (guru); tabel Super Admin memakai kolom sendiri.
 */
function media_permintaan_ringkas_teks($mapel, $kelas, $fase): string
{
    $bagian = [];
    if (media_permintaan_mapel_label($mapel) !== '-') {
        $bagian[] = media_permintaan_mapel_label($mapel);
    }
    if (media_permintaan_kelas_sah($kelas) !== '') {
        $bagian[] = media_permintaan_kelas_label($kelas);
    }
    if (media_permintaan_fase_sah($fase) !== '') {
        $bagian[] = media_permintaan_fase_label($fase);
    }
    return implode(' · ', $bagian);
}

/**
 * Ringkasan dua kolom untuk baris kecil di bawah judul media: "Kelas 4 · Fase B".
 *
 * Bagian yang kosong dilewati; bila keduanya kosong hasilnya '' (barisnya tidak dicetak) —
 * permintaan lama tidak dipaksa menampilkan "-". Tanpa mata pelajaran: lihat
 * `media_permintaan_ringkas_teks()` (satu sumber susunan teksnya).
 */
function media_permintaan_kelas_fase_teks($kelas, $fase): string
{
    return media_permintaan_ringkas_teks('', $kelas, $fase);
}

// ============================================================================
// BACA DATA
// ============================================================================

/** Satu permintaan berdasarkan id (null bila tidak ada). */
function media_permintaan_by_id(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return one('SELECT * FROM media_permintaan WHERE id = ?', [$id]);
}

/**
 * Permintaan milik SATU akun guru (urut terbaru di atas).
 *
 * Dibatasi `user_id` — bukan `sekolah_id`/`guru_id` — supaya guru yang pernah dimutasi ke lembaga
 * lain tetap melihat permintaan lamanya sendiri (dan tidak pernah melihat milik guru lain).
 *
 * @return array<int,array<string,mixed>>
 */
function media_permintaan_saya(int $userId, string $status = ''): array
{
    if ($userId <= 0) {
        return [];
    }
    $st = media_permintaan_status_sah($status);
    if ($st !== '') {
        return all('SELECT * FROM media_permintaan WHERE user_id = ? AND status = ?
                    ORDER BY dibuat DESC, id DESC', [$userId, $st]);
    }
    return all('SELECT * FROM media_permintaan WHERE user_id = ?
                ORDER BY dibuat DESC, id DESC', [$userId]);
}

/** Jumlah permintaan milik satu akun yang MASIH "Sedang ditinjau". */
function media_permintaan_jumlah_saya(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    return (int) val('SELECT COUNT(*) FROM media_permintaan WHERE user_id = ? AND status = ?',
        [$userId, 'ditinjau'], 0);
}

/** Jumlah SELURUH permintaan yang masih "Sedang ditinjau" (lencana menu Super Admin). */
function media_permintaan_jumlah_ditinjau(): int
{
    return (int) val("SELECT COUNT(*) FROM media_permintaan WHERE status = 'ditinjau'", [], 0);
}

/**
 * Daftar permintaan untuk halaman Super Admin (seluruh sekolah/madrasah).
 *
 * @param array{status?:string,cari?:string} $f
 * @return array<int,array<string,mixed>>
 */
function media_permintaan_list(array $f = []): array
{
    $where = [];
    $args = [];
    $st = media_permintaan_status_sah($f['status'] ?? '');
    if ($st !== '') {
        $where[] = 'status = ?';
        $args[] = $st;
    }
    $cari = trim((string) ($f['cari'] ?? ''));
    if ($cari !== '') {
        // Cari di kolom yang tampil (nama guru, asal sekolah, judul media, mata pelajaran) — AND per
        // kata. Mata pelajaran ikut sejak v1.111.2 karena kini menjadi kolom tabel tersendiri.
        foreach (preg_split('/\s+/', $cari) ?: [] as $kata) {
            $kata = trim($kata);
            if ($kata === '') {
                continue;
            }
            $where[] = '(nama_guru LIKE ? OR asal_sekolah LIKE ? OR judul LIKE ? OR mata_pelajaran LIKE ?)';
            $like = '%' . $kata . '%';
            array_push($args, $like, $like, $like, $like);
        }
    }
    $sql = 'SELECT * FROM media_permintaan'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY CASE status WHEN \'ditinjau\' THEN 0 ELSE 1 END, dibuat DESC, id DESC';
    return all($sql, $args);
}

/**
 * Ringkasan angka untuk kartu halaman Super Admin.
 *
 * @return array{jumlah:int,ditinjau:int,selesai:int,guru:int}
 */
function media_permintaan_ringkas(): array
{
    return [
        'jumlah'   => (int) val('SELECT COUNT(*) FROM media_permintaan', [], 0),
        'ditinjau' => media_permintaan_jumlah_ditinjau(),
        'selesai'  => (int) val("SELECT COUNT(*) FROM media_permintaan WHERE status = 'selesai'", [], 0),
        'guru'     => (int) val('SELECT COUNT(DISTINCT nama_guru) FROM media_permintaan '
            . "WHERE TRIM(COALESCE(nama_guru, '')) <> ''", [], 0),
    ];
}

// ============================================================================
// SIMPAN / UBAH
// ============================================================================

/** Potong isian sesuai batas kolomnya (spasi dirapatkan). */
function media_permintaan_potong(string $teks, int $maks): string
{
    $t = trim((string) preg_replace('/\s+/u', ' ', $teks));
    return function_exists('mb_substr') ? mb_substr($t, 0, $maks) : substr($t, 0, $maks);
}

/**
 * Simpan permintaan media dari akun GURU.
 *
 * Status AWAL selalu "Sedang ditinjau" (`ditinjau`) — bukan masukan dari formulir, sehingga guru
 * tidak dapat mengirim permintaan yang sudah berstatus "Media telah dibuat".
 *
 * @param array{nama_guru?:string,asal_sekolah?:string,judul?:string,mata_pelajaran?:string,kelas?:string,fase?:string} $data
 * @return array{ok:bool,pesan:string,id:int}
 */
function media_permintaan_simpan(array $data): array
{
    $u = current_user();
    $userId = (int) ($u['id'] ?? 0);
    if ($userId <= 0) {
        return ['ok' => false, 'pesan' => 'Sesi Anda sudah berakhir. Silakan masuk kembali.', 'id' => 0];
    }

    $nama    = media_permintaan_potong((string) ($data['nama_guru'] ?? ''), MEDIA_PERMINTAAN_NAMA_MAKS);
    $sekolah = media_permintaan_potong((string) ($data['asal_sekolah'] ?? ''), MEDIA_PERMINTAAN_SEKOLAH_MAKS);
    $judul   = media_permintaan_potong((string) ($data['judul'] ?? ''), MEDIA_PERMINTAAN_JUDUL_MAKS);
    $mapel   = media_permintaan_potong((string) ($data['mata_pelajaran'] ?? ''), MEDIA_PERMINTAAN_MAPEL_MAKS);
    $kelas   = media_permintaan_kelas_sah($data['kelas'] ?? '');
    $fase    = media_permintaan_fase_sah($data['fase'] ?? '');

    // Validasi isi (server — bukan hanya atribut `required` pada formulir).
    if ($nama === '') {
        return ['ok' => false, 'pesan' => 'Nama Guru wajib diisi.', 'id' => 0];
    }
    if (function_exists('mb_strlen') ? mb_strlen($nama) < 3 : strlen($nama) < 3) {
        return ['ok' => false, 'pesan' => 'Nama Guru terlalu pendek (minimal 3 huruf).', 'id' => 0];
    }
    if ($sekolah === '') {
        return ['ok' => false, 'pesan' => 'Asal Sekolah/Madrasah wajib diisi.', 'id' => 0];
    }
    if (function_exists('mb_strlen') ? mb_strlen($sekolah) < 3 : strlen($sekolah) < 3) {
        return ['ok' => false, 'pesan' => 'Asal Sekolah/Madrasah terlalu pendek (minimal 3 huruf).', 'id' => 0];
    }
    if ($judul === '') {
        return ['ok' => false, 'pesan' => 'Judul Media wajib diisi.', 'id' => 0];
    }
    if (function_exists('mb_strlen') ? mb_strlen($judul) < 3 : strlen($judul) < 3) {
        return ['ok' => false, 'pesan' => 'Judul Media terlalu pendek (minimal 3 huruf).', 'id' => 0];
    }
    // MATA PELAJARAN (v1.111.2): wajib diisi, minimal 2 huruf — batas 2 (bukan 3) supaya singkatan
    // mapel yang lazim, mis. "BK", tetap sah.
    if ($mapel === '') {
        return ['ok' => false, 'pesan' => 'Mata Pelajaran wajib diisi.', 'id' => 0];
    }
    if (function_exists('mb_strlen') ? mb_strlen($mapel) < 2 : strlen($mapel) < 2) {
        return ['ok' => false, 'pesan' => 'Mata Pelajaran terlalu pendek (minimal 2 huruf).', 'id' => 0];
    }
    // KELAS & FASE (v1.111.1): kelas WAJIB dipilih dari daftar 1..12. Fase boleh dikirim kosong —
    // server menurunkannya dari kelas (padanan Kurikulum Merdeka), jadi kiriman dari peramban tanpa
    // JavaScript / tanpa isian fase tetap tersimpan dengan fase yang benar.
    if ($kelas === '') {
        return ['ok' => false, 'pesan' => 'Kelas wajib dipilih (Kelas 1 sampai Kelas '
            . MEDIA_PERMINTAAN_KELAS_MAKS . ').', 'id' => 0];
    }
    if ($fase === '') {
        if (trim((string) ($data['fase'] ?? '')) !== '') {
            return ['ok' => false, 'pesan' => 'Fase yang dipilih tidak dikenal (Fase A sampai Fase F).',
                'id' => 0];
        }
        $fase = media_permintaan_fase_dari_kelas($kelas);
    }

    // Pengaman: batas kiriman per hari per akun (lihat MEDIA_PERMINTAAN_MAKS_HARI).
    $hariIni = (int) val('SELECT COUNT(*) FROM media_permintaan WHERE user_id = ? AND dibuat >= ?',
        [$userId, date('Y-m-d') . ' 00:00:00'], 0);
    if ($hariIni >= MEDIA_PERMINTAAN_MAKS_HARI) {
        return ['ok' => false, 'pesan' => 'Permintaan media hari ini sudah mencapai batas '
            . MEDIA_PERMINTAAN_MAKS_HARI . ' permintaan. Silakan lanjutkan besok.', 'id' => 0];
    }

    $now = date('Y-m-d H:i:s');
    $sekolahId = current_sekolah() ? (int) current_sekolah()['id'] : null;
    $guruId = guru_id_saya() > 0 ? guru_id_saya() : null;
    q('INSERT INTO media_permintaan
          (sekolah_id, guru_id, user_id, nama_guru, asal_sekolah, judul, mata_pelajaran, kelas, fase,
           status, dibuat, diperbarui)
       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [$sekolahId, $guruId, $userId, $nama, $sekolah, $judul, $mapel, $kelas, $fase, 'ditinjau',
         $now, $now]);
    $id = (int) db()->lastInsertId();

    return ['ok' => true, 'id' => $id,
        'pesan' => 'Permintaan media "' . $judul . '" (' . media_permintaan_ringkas_teks($mapel, $kelas, $fase)
            . ') terkirim ke pengelola aplikasi (Super Admin) dengan status Sedang ditinjau. '
            . 'Statusnya dapat Anda pantau pada tab Buatkan Media.'];
}

/**
 * Tetapkan status satu permintaan (dipakai halaman Super Admin).
 *
 * @return array{ok:bool,pesan:string}
 */
function media_permintaan_status_set(int $id, string $status): array
{
    $p = media_permintaan_by_id($id);
    if (!$p) {
        return ['ok' => false, 'pesan' => 'Permintaan media itu tidak ditemukan.'];
    }
    $st = media_permintaan_status_sah($status);
    if ($st === '') {
        return ['ok' => false, 'pesan' => 'Status permintaan tidak dikenal.'];
    }
    $oleh = (string) (current_user()['nama'] ?? 'Super Admin');
    q('UPDATE media_permintaan SET status = ?, diubah_oleh = ?, diperbarui = ? WHERE id = ?',
        [$st, $oleh, date('Y-m-d H:i:s'), $id]);
    return ['ok' => true, 'pesan' => 'Status permintaan "' . (string) $p['judul'] . '" untuk '
        . (string) $p['nama_guru'] . ' menjadi '
        . media_permintaan_status_label($st) . '.'];
}

// ============================================================================
// TAB "BUATKAN MEDIA" (AKUN GURU)
// ============================================================================

/** Nilai bawaan isian: nama guru & asal sekolah dari akun/lembaganya. */
function media_permintaan_nama_bawaan(): string
{
    $g = guru_saya();
    if ($g && trim((string) ($g['nama'] ?? '')) !== '') {
        return (string) $g['nama'];
    }
    return (string) (current_user()['nama'] ?? '');
}

/** Alamat tab "Buatkan Media" (mempertahankan menu & tab jenjang yang sedang dibuka). */
function media_permintaan_url(?string $menu = 'media_interaktif'): string
{
    $menu = $menu === null || $menu === '' ? 'media_interaktif' : $menu;
    return $menu . '.php?buatkan=1';
}

/**
 * Isi tab **Buatkan Media** pada menu Media Interaktif (akun guru): baris pil tab, formulir
 * permintaan, lalu daftar permintaan milik guru itu sendiri beserta statusnya.
 *
 * @param array<string,string> $jmlPerTab   jumlah aplikasi tiap tab (pil tab dicetak ulang di sini)
 * @param array<string,mixed>  $k           konfigurasi menu (dari daftar_apps_konfig())
 */
function media_permintaan_tab_guru(string $menu, string $berkasDasar, string $katAktif,
                                   array $jmlPerTab, array $k): void
{
    $u = current_user();
    $userId = (int) ($u['id'] ?? 0);
    $saya = media_permintaan_saya($userId);
    $jmlDitinjau = media_permintaan_jumlah_saya($userId);
    $jmlSelesai = count($saya) - $jmlDitinjau;
    $formUrl = media_permintaan_url($menu);

    page_top('Media Interaktif — Buatkan Media', 'Guru &middot; ' . tetap_kata(nama_sekolah_tampil()));
    ?>
<div class="card" id="cardBuatkanMedia">
  <div class="card-head">
    <div>
      <h2><?= ikon('send', 16) ?> Buatkan Media</h2>
      <p>Ajukan permintaan pembuatan media interaktif kepada <b>pengelola aplikasi (Super Admin)</b>.
        Cukup isi <b>Nama Guru</b>, <b>Asal <?= e(istilah_sekolah('Sekolah')) ?></b>, <b>Judul Media</b>,
        <b>Mata Pelajaran</b>, lalu <b>Kelas</b> dan <b>Fase</b> sasarannya agar medianya tepat sasaran.
        Permintaan langsung masuk ke menu <b>Permintaan Media</b> pada akun Super Admin.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= count($saya) ?> permintaan</span>
      <?php if ($jmlDitinjau > 0): ?>
        <span class="pill pill-warn"><?= $jmlDitinjau ?> sedang ditinjau</span>
      <?php endif; ?>
      <?php if ($jmlSelesai > 0): ?>
        <span class="pill pill-ok"><?= $jmlSelesai ?> telah dibuat</span>
      <?php endif; ?>
    </div>
  </div>

  <?php // Baris pil tab yang SAMA dengan halaman daftar media (4 tab jenjang + pil "Buatkan Media"
        // yang sedang aktif) — supaya guru dapat berpindah kembali ke daftar media tanpa kehilangan
        // konteks. ?>
  <?php daftar_apps_tab_bar($menu, $berkasDasar, $katAktif, $jmlPerTab, false, $k); ?>

  <form method="post" class="apps-form" id="formBuatkanMedia" action="<?= e($formUrl) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="minta_media">
    <div class="apps-form-judul">
      <?= ikon('plus', 16) ?> <b>Formulir Permintaan Media</b>
    </div>
    <div class="form-grid">
      <div>
        <label class="f" for="mp_nama">Nama Guru *</label>
        <input type="text" id="mp_nama" name="nama_guru" required
               maxlength="<?= (int) MEDIA_PERMINTAAN_NAMA_MAKS ?>"
               value="<?= e(media_permintaan_nama_bawaan()) ?>">
        <div class="hint">Terisi otomatis dari akun Anda; boleh disesuaikan.</div>
      </div>
      <div>
        <label class="f" for="mp_sekolah">Asal <?= e(istilah_sekolah('Sekolah')) ?> *</label>
        <input type="text" id="mp_sekolah" name="asal_sekolah" required
               maxlength="<?= (int) MEDIA_PERMINTAAN_SEKOLAH_MAKS ?>"
               value="<?= e(nama_sekolah_tampil()) ?>">
        <div class="hint">Terisi otomatis dari lembaga Anda; boleh disesuaikan.</div>
      </div>
      <div class="apps-ket">
        <label class="f" for="mp_judul">Judul Media *</label>
        <input type="text" id="mp_judul" name="judul" required
               maxlength="<?= (int) MEDIA_PERMINTAAN_JUDUL_MAKS ?>"
               placeholder="mis. Media Pembelajaran Pecahan">
        <div class="hint">Tuliskan judul/topik media yang ingin dibuatkan, mis. “Game Kuis Tata Surya”
          atau “Media Bangun Ruang”.</div>
      </div>
      <?php // MATA PELAJARAN (v1.111.2) — permintaan pemilik: *"Tambahkan Mata pelajaran sebelum
            // kelas"*, jadi isian ini berada TEPAT DI ATAS Kelas & Fase. Teks bebas (nama mapel tiap
            // lembaga berbeda) dengan saran dari daftar mapel lembaga guru itu (<datalist>) — bukan
            // daftar tetap di kode, supaya mapel khusus madrasah (mis. Akidah Akhlak) tetap muncul. ?>
      <div class="apps-ket">
        <label class="f" for="mp_mapel">Mata Pelajaran *</label>
        <input type="text" id="mp_mapel" name="mata_pelajaran" required autocomplete="off"
               list="mp_mapel_saran" maxlength="<?= (int) MEDIA_PERMINTAAN_MAPEL_MAKS ?>"
               placeholder="mis. Matematika">
        <?php $saranMapel = mapel_list();
              if ($saranMapel): ?>
          <datalist id="mp_mapel_saran">
            <?php foreach ($saranMapel as $m): ?>
              <option value="<?= e((string) $m['nama']) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        <?php endif; ?>
        <div class="hint">Mata pelajaran sasaran media, mis. “Matematika”, “IPAS”, atau “Akidah Akhlak”.</div>
      </div>
      <?php // KELAS & FASE (v1.111.1) — permintaan pemilik: *"Dibawah Judul Media tambahkan Kelas dan
            // Fase agar media pembelajaran lebih fokus ke kelas dan fasenya"*. Dua pilihan (bukan
            // teks bebas) supaya isiannya seragam; fase terisi otomatis mengikuti kelas lewat
            // initKelasFase() (assets/app.js) dan server tetap menurunkannya sendiri bila kosong. ?>
      <div class="apps-ket">
        <div class="form-grid-dua">
          <div>
            <label class="f" for="mp_kelas">Kelas *</label>
            <select id="mp_kelas" name="kelas" required>
              <option value="">— Pilih Kelas —</option>
              <?php foreach (media_permintaan_kelas_list() as $vKelas => $lKelas): ?>
                <option value="<?= e((string) $vKelas) ?>"><?= e($lKelas) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="hint">Kelas sasaran media yang Anda butuhkan.</div>
          </div>
          <div>
            <label class="f" for="mp_fase">Fase *</label>
            <select id="mp_fase" name="fase" required>
              <option value="">— Pilih Fase —</option>
              <?php foreach (MEDIA_PERMINTAAN_FASE as $vFase => $lFase): ?>
                <option value="<?= e((string) $vFase) ?>"><?= e((string) $lFase) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="hint">Terisi otomatis mengikuti Kelas (Kurikulum Merdeka); boleh disesuaikan.</div>
          </div>
        </div>
      </div>
    </div>
    <div class="flex mt16">
      <button class="btn btn-primary" type="submit"><?= ikon('send', 16) ?> Kirim Permintaan</button>
      <span class="hint" style="align-self:center">Permintaan berstatus <b>Sedang ditinjau</b> sampai
        pengelola aplikasi menyelesaikan medianya (<b>Media telah dibuat</b>).</span>
    </div>
  </form>
</div>

<div class="card" id="cardPermintaanSaya">
  <div class="card-head">
    <div>
      <h2><?= ikon('clipboard', 16) ?> Permintaan Media Saya</h2>
      <p>Seluruh permintaan media yang Anda kirim beserta <b>statusnya</b>.</p>
    </div>
    <span class="chip"><?= count($saya) ?> permintaan</span>
  </div>
  <?php if (!$saya): ?>
    <div class="empty">
      <?= ikon('send', 34) ?>
      <h3>Belum ada permintaan media</h3>
      <p>Isi formulir <b>Buatkan Media</b> di atas. Setelah dikirim, permintaan Anda tampil di sini
        dengan status <b>Sedang ditinjau</b>, lalu berubah menjadi <b>Media telah dibuat</b> begitu
        pengelola aplikasi menyelesaikannya.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblPermintaanSaya">
        <thead>
          <tr>
            <th class="ctr" style="width:44px">No</th>
            <th>Judul Media</th>
            <th class="ctr" style="width:170px">Dikirim</th>
            <th class="ctr" style="width:180px">Status</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($saya as $r): $n++; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e(tetap_kata((string) $r['judul'])) ?></b>
              <?php // Ringkasan "Mata Pelajaran · Kelas N · Fase X" (v1.111.2) — tabel milik guru tetap
                    // ringkas (4 kolom); bagian yang belum diisi dilewati, jadi permintaan lama
                    // (sebelum skema v70/v71) tidak menampilkan deretan "-". ?>
              <?php $ringkas = media_permintaan_ringkas_teks($r['mata_pelajaran'] ?? '',
                      $r['kelas'] ?? '', $r['fase'] ?? '');
                    if ($ringkas !== ''): ?>
                <div class="tbl-sub"><?= e(tetap_kata($ringkas)) ?></div>
              <?php endif; ?>
              <div class="tbl-sub"><?= e(tetap_kata((string) $r['asal_sekolah'])) ?></div></td>
            <td class="ctr"><?= e(tanggal_id((string) $r['dibuat'])) ?>
              <div class="tbl-sub"><?= e(substr((string) $r['dibuat'], 11, 5)) ?></div></td>
            <td class="ctr">
              <span class="pill <?= media_permintaan_status_pil((string) $r['status']) ?>">
                <?= e(media_permintaan_status_label((string) $r['status'])) ?></span>
              <?php if (trim((string) ($r['diubah_oleh'] ?? '')) !== ''
                  && media_permintaan_status_sah((string) $r['status']) === 'selesai'): ?>
                <div class="tbl-sub">oleh <?= e(tetap_kata((string) $r['diubah_oleh'])) ?></div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint" style="margin-top:10px">Status <b>Sedang ditinjau</b> berarti permintaan sudah
      diterima pengelola aplikasi dan sedang dikerjakan; <b>Media telah dibuat</b> berarti medianya
      sudah siap — silakan periksa tab jenjang yang sesuai.</p>
  <?php endif; ?>
</div>
    <?php
    page_bottom();
}

// ============================================================================
// HALAMAN "PERMINTAAN MEDIA" (SUPER ADMIN)
// ============================================================================

/**
 * Halaman menu **Permintaan Media** — akun Super Admin: daftar permintaan dari seluruh akun guru
 * (nama guru + judul media) dengan aksi **Sedang ditinjau** / **Media telah dibuat**.
 */
function media_permintaan_halaman(): void
{
    require_login();
    if (!is_super()) {
        tolak403('Menu Permintaan Media hanya tersedia untuk Super Admin. Akun guru mengirim '
            . 'permintaan lewat tab "Buatkan Media" pada menu Media Interaktif.');
    }
    // Halaman ini lintas sekolah → istilah generik "Sekolah" (bukan "Madrasah").
    $GLOBALS['__vs_istilah_nonaktif'] = true;

    // ---------------------------------------------------------------- POST (ubah status)
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        if ($aksi === 'status') {
            $hasil = media_permintaan_status_set((int) ($_POST['id'] ?? 0),
                (string) ($_POST['status'] ?? ''));
            flash((string) $hasil['pesan'], !empty($hasil['ok']) ? 'ok' : 'err');
        } else {
            flash('Aksi tidak dikenal.', 'err');
        }
        // Kembali ke daftar dengan penyaring yang sedang dipakai supaya posisinya tidak hilang.
        $q = [];
        if (trim((string) ($_POST['f_status'] ?? '')) !== '') {
            $q['status'] = (string) $_POST['f_status'];
        }
        if (trim((string) ($_POST['f_cari'] ?? '')) !== '') {
            $q['cari'] = (string) $_POST['f_cari'];
        }
        redirect('permintaan_media.php' . ($q ? '?' . http_build_query($q) : '') . '#tabelPermintaanMedia');
    }

    $fStatus = media_permintaan_status_sah($_GET['status'] ?? '');
    $fCari = trim((string) ($_GET['cari'] ?? ''));
    $daftar = media_permintaan_list(['status' => $fStatus, 'cari' => $fCari]);
    $r = media_permintaan_ringkas();
    $csrf = csrf_field();

    page_top('Permintaan Media', 'Permintaan pembuatan media interaktif dari akun guru');
    ?>
<div class="card" id="cardPermintaanMedia">
  <div class="card-head">
    <div>
      <h2><?= ikon('send', 16) ?> Permintaan Media</h2>
      <p>Permintaan pembuatan media interaktif yang dikirim akun <b>guru</b> lewat tab
        <b>Buatkan Media</b> pada menu Media Interaktif. <b>Kelas</b> &amp; <b>Fase</b> yang diminta
        tampil di bawah judul medianya. Tetapkan statusnya: <b>Sedang ditinjau</b> atau
        <b>Media telah dibuat</b> — status yang Anda pilih langsung tampil pada akun guru yang
        meminta.</p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $r['jumlah'] ?> permintaan</span>
      <span class="chip"><?= (int) $r['guru'] ?> guru</span>
    </div>
  </div>

  <div class="stat-mini flex" style="margin-bottom:18px">
    <div><b><?= (int) $r['ditinjau'] ?></b><span>Sedang ditinjau</span></div>
    <div><b><?= (int) $r['selesai'] ?></b><span>Media telah dibuat</span></div>
    <div><b><?= (int) $r['jumlah'] ?></b><span>Total permintaan</span></div>
  </div>

  <form class="filterbar" method="get" id="filterPermintaanMedia">
    <div style="min-width:190px">
      <label class="f" for="fStatus">Status</label>
      <select id="fStatus" name="status" data-auto>
        <option value="">Semua status</option>
        <?php foreach (media_permintaan_status_list() as $kunci => $label): ?>
          <option value="<?= e((string) $kunci) ?>" <?= $fStatus === $kunci ? 'selected' : '' ?>>
            <?= e((string) $label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="min-width:240px;flex:1 1 240px">
      <label class="f" for="fCari">Cari</label>
      <input type="text" id="fCari" name="cari" value="<?= e($fCari) ?>"
             placeholder="nama guru / sekolah / judul media / mata pelajaran">
    </div>
    <div class="flex" style="gap:8px">
      <button class="btn btn-primary" type="submit"><?= ikon('search', 15) ?> Tampilkan</button>
      <?php if ($fStatus !== '' || $fCari !== ''): ?>
        <a class="btn btn-ghost" href="permintaan_media.php"><?= ikon('x', 15) ?> Bersihkan</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="divider"></div>
  <div class="section-title mb8" id="tabelPermintaanMedia">
    <?= ikon('users', 15) ?> Daftar Permintaan (<?= count($daftar) ?>)</div>
  <?php if (!$daftar): ?>
    <div class="empty">
      <?= ikon('send', 34) ?>
      <h3>Belum ada permintaan media</h3>
      <p><?= $fStatus !== '' || $fCari !== ''
          ? 'Tidak ada permintaan yang cocok dengan penyaring yang dipakai. Coba bersihkan penyaringnya.'
          : 'Permintaan akan tampil di sini begitu akun guru mengirimkannya lewat tab Buatkan Media '
            . 'pada menu Media Interaktif.' ?></p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl" id="tblPermintaanMedia">
        <?php // KOLOM TABEL = KOLOM FORMULIR (v1.111.2, permintaan pemilik: *"pada super admin tabelnya
              // disesuaikan dengan Formulir Permintaan Media"*): Guru · Asal Sekolah · Judul Media ·
              // Mata Pelajaran · Kelas · Fase — ditambah Dikirim, Status, dan Aksi.
              // 🚨 Kolom Aksi WAJIB 310px: dua tombol aksi (131px + 147px + jarak 6px = 284px) harus
              // muat SATU baris. Kolom Guru / Asal Sekolah / Judul / Mata Pelajaran sengaja TANPA
              // lebar tetap (dibiarkan berbagi sisa lebar); dengan persentase, kolom Aksi menyusut dan
              // kedua tombol terlipat dua baris. Kolom Kelas & Fase diberi lebar kecil tetap; tabel
              // boleh digeser mendatar di layar sempit karena .table-wrap sudah `overflow-x:auto`. ?>
        <thead>
          <tr>
            <th class="ctr" style="width:44px">No</th>
            <th>Guru</th>
            <th><?= e(istilah_sekolah('Asal Sekolah')) ?></th>
            <th>Judul Media</th>
            <th>Mata Pelajaran</th>
            <th class="ctr" style="width:86px">Kelas</th>
            <th class="ctr" style="width:78px">Fase</th>
            <th class="ctr" style="width:116px">Dikirim</th>
            <th class="ctr" style="width:144px">Status</th>
            <th class="ctr" style="width:310px">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php $n = 0; foreach ($daftar as $baris): $n++; $st = (string) $baris['status']; ?>
          <tr>
            <td class="ctr"><?= $n ?></td>
            <td><b><?= e(tetap_kata((string) $baris['nama_guru'])) ?></b></td>
            <td><?= e(tetap_kata((string) $baris['asal_sekolah'])) ?></td>
            <td><b><?= e(tetap_kata((string) $baris['judul'])) ?></b></td>
            <td><?= e(tetap_kata(media_permintaan_mapel_label($baris['mata_pelajaran'] ?? ''))) ?></td>
            <td class="ctr"><?= e(media_permintaan_kelas_label($baris['kelas'] ?? '')) ?></td>
            <td class="ctr"><?= e(media_permintaan_fase_label($baris['fase'] ?? '')) ?></td>
            <td class="ctr"><?= e(tanggal_id((string) $baris['dibuat'])) ?>
              <div class="tbl-sub"><?= e(substr((string) $baris['dibuat'], 11, 5)) ?></div></td>
            <td class="ctr">
              <span class="pill <?= media_permintaan_status_pil($st) ?>">
                <?= e(media_permintaan_status_label($st)) ?></span>
              <?php if (trim((string) ($baris['diubah_oleh'] ?? '')) !== ''): ?>
                <div class="tbl-sub">oleh <?= e(tetap_kata((string) $baris['diubah_oleh'])) ?></div>
              <?php endif; ?>
            </td>
            <td class="ctr">
              <form method="post" class="row-actions" id="formAksi<?= (int) $baris['id'] ?>">
                <?= $csrf ?>
                <input type="hidden" name="aksi" value="status">
                <input type="hidden" name="id" value="<?= (int) $baris['id'] ?>">
                <input type="hidden" name="f_status" value="<?= e($fStatus) ?>">
                <input type="hidden" name="f_cari" value="<?= e($fCari) ?>">
                <?php // DUA AKSI sesuai permintaan pemilik; tombol yang sesuai keadaan sekarang
                      // ditandai (class `on` + nonaktif) supaya jelas mana yang sedang berlaku. ?>
                <button class="btn btn-ghost btn-sm<?= $st === 'ditinjau' ? ' on' : '' ?>"
                        type="submit" name="status" value="ditinjau"
                        <?= $st === 'ditinjau' ? 'disabled' : '' ?>>
                  <?= ikon('eye', 13) ?> Sedang ditinjau</button>
                <button class="btn btn-primary btn-sm<?= $st === 'selesai' ? ' on' : '' ?>"
                        type="submit" name="status" value="selesai"
                        <?= $st === 'selesai' ? 'disabled' : '' ?>>
                  <?= ikon('check', 13) ?> Media telah dibuat</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="hint" style="margin-top:10px">Perubahan status langsung terlihat pada tab
      <b>Buatkan Media</b> di akun guru yang meminta (menu Media Interaktif).</p>
  <?php endif; ?>
</div>
    <?php
    page_bottom();
}
