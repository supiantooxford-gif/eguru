<?php
/**
 * CHAT / OBROLAN sesama pengguna aplikasi yang sedang online — SATU ruang bersama untuk seluruh
 * pengguna (lintas sekolah/madrasah, termasuk Super Admin).
 *
 * Permintaan pemilik: *"Tambahkan fitur chat agar sesama pengguna aplikasi yang sedang online bisa
 * berkomunikasi"*. Pilihan pemilik (jangan diubah tanpa bertanya):
 *   1. **SATU ruang bersama** untuk semua pengguna aplikasi (lintas sekolah + Super Admin);
 *   2. dipakai **SEMUA PERAN** (Super Admin, kepala, operator, guru, dan pengawas);
 *   3. pesan baru muncul **OTOMATIS tiap beberapa detik** (tanpa mengklik apa pun);
 *   4. tampil sebagai **KOTAK MENGAMBANG di pojok kanan bawah, di semua halaman**.
 *
 * Karena satu ruang bersama, percakapan TIDAK dibatasi `sekolah_id` — setiap baris menyimpan
 * **snapshot** nama/asal sekolah pengirimnya (seperti `log_aktivitas`) supaya riwayat tetap terbaca
 * walau akun atau sekolahnya kelak dihapus.
 *
 * Modul ini di-require dari `inc/init.php` (lihat komentar di sana) karena dipakai layout
 * (kotak mengambang) maupun endpoint `chat.php`.
 */
declare(strict_types=1);

/** Batas panjang satu pesan (karakter) — dijaga di SERVER, bukan hanya di peramban. */
const CHAT_MAKS = 500;
/** Jumlah pesan yang dikirim pada pemuatan pertama kotak obrolan. */
const CHAT_MUAT = 50;
/** Berapa pesan terbaru yang DISIMPAN (yang lebih lama dipangkas otomatis agar tabel tidak membengkak). */
const CHAT_SIMPAN_MAKS = 2000;
/** Jeda antar-permintaan (detik) pada kotak mengambang — "otomatis tiap beberapa detik". */
const CHAT_JEDA_DETIK = 5;
/** Batas jumlah pesan yang boleh dikirim satu sesi dalam satu menit (pengaman anti-spam sederhana). */
const CHAT_MAKS_MENIT = 20;

/** Isi pesan yang sudah dirapikan (spasi berlebih dirapatkan, dipotong sesuai batas). */
function chat_isi_bersih(string $isi): string
{
    $isi = (string) preg_replace('/[ \t]+/u', ' ', trim($isi));
    $isi = (string) preg_replace('/\n{3,}/', "\n\n", $isi);
    if (strlen($isi) > CHAT_MAKS) {
        // Sisakan ruang untuk tanda "…" supaya hasil akhirnya TIDAK melebihi batas.
        $potong = substr($isi, 0, CHAT_MAKS - 3);
        // Jangan memotong di tengah karakter UTF-8.
        while ($potong !== '' && !preg_match('//u', $potong)) {
            $potong = substr($potong, 0, -1);
        }
        $isi = rtrim($potong) . '…';
    }
    return $isi;
}

/**
 * Simpan satu pesan obrolan atas nama pengguna yang diberikan.
 * Mengembalikan baris pesan yang baru disimpan (siap dikirim sebagai JSON) atau null bila isinya kosong.
 */
function chat_kirim(string $isi, ?array $u = null): ?array
{
    $u = $u ?? current_user();
    if (!$u) {
        return null;
    }
    $isi = chat_isi_bersih($isi);
    if ($isi === '') {
        return null;
    }
    $s = current_sekolah();
    $now = date('Y-m-d H:i:s');
    q('INSERT INTO chat_pesan (user_id, nama, username, role, sekolah_id, sekolah, isi, waktu)
       VALUES (?,?,?,?,?,?,?,?)',
        [(int) $u['id'], (string) $u['nama'], (string) $u['username'], (string) $u['role'],
         $s ? (int) $s['id'] : null, $s ? (string) $s['nama'] : null, $isi, $now]);
    $id = (int) db()->lastInsertId();
    // Pangkas pesan lama supaya tabel tidak tumbuh tanpa batas (dijalankan jarang: hanya tiap 100 pesan).
    if ($id % 100 === 0) {
        chat_pangkas();
    }
    return ['id' => $id, 'user_id' => (int) $u['id'], 'nama' => (string) $u['nama'],
            'username' => (string) $u['username'], 'role' => (string) $u['role'],
            'sekolah' => $s ? (string) $s['nama'] : '', 'isi' => $isi, 'waktu' => $now];
}

/** Buang pesan paling lama sehingga hanya CHAT_SIMPAN_MAKS pesan terbaru yang tersimpan. */
function chat_pangkas(): void
{
    try {
        q('DELETE FROM chat_pesan WHERE id <= (SELECT MAX(id) FROM chat_pesan) - ?', [CHAT_SIMPAN_MAKS]);
    } catch (Throwable $e) {
        // Kegagalan pemangkasan tidak boleh menggagalkan pengiriman pesan.
    }
}

/**
 * Daftar pesan obrolan.
 * @param int $sejak id pesan terakhir yang SUDAH dimiliki pemanggil (0 = ambil yang terbaru saja)
 */
function chat_pesan_list(int $sejak = 0, int $batas = CHAT_MUAT): array
{
    $batas = max(1, min(200, $batas));
    if ($sejak > 0) {
        return all('SELECT * FROM chat_pesan WHERE id > ? ORDER BY id LIMIT ' . $batas, [$sejak]);
    }
    $baris = all('SELECT * FROM chat_pesan ORDER BY id DESC LIMIT ' . $batas);
    return array_reverse($baris);
}

/** id pesan TERAKHIR yang dibaca seorang pengguna (0 = belum pernah membaca). */
function chat_baca_id(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    return (int) val('SELECT pesan_id FROM chat_baca WHERE user_id = ?', [$userId], 0);
}

/** Tandai pesan sampai id tertentu sudah dibaca pengguna (dipakai saat kotak obrolan terbuka). */
function chat_tandai_dibaca(int $userId, int $pesanId): void
{
    if ($userId <= 0 || $pesanId <= 0 || $pesanId <= chat_baca_id($userId)) {
        return;
    }
    q('INSERT INTO chat_baca (user_id, pesan_id, waktu) VALUES (?,?,?)
       ON CONFLICT(user_id) DO UPDATE SET pesan_id = excluded.pesan_id, waktu = excluded.waktu',
        [$userId, $pesanId, date('Y-m-d H:i:s')]);
}

/** Jumlah pesan BELUM DIBACA seorang pengguna (pesan sendiri tidak dihitung). */
function chat_belum_dibaca(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    try {
        return (int) val('SELECT COUNT(*) FROM chat_pesan WHERE id > ? AND IFNULL(user_id,0) <> ?',
            [chat_baca_id($userId), $userId], 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/** id pesan terakhir (0 bila belum ada pesan sama sekali). */
function chat_id_terakhir(): int
{
    try {
        return (int) val('SELECT IFNULL(MAX(id),0) FROM chat_pesan', [], 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/** Label asal pengguna pada daftar obrolan — nama sekolah/madrasahnya, atau "Super Admin". */
function chat_asal_label(?string $role, ?string $sekolah): string
{
    if ((string) $role === 'super') {
        return 'Super Admin';
    }
    $sekolah = trim((string) $sekolah);
    return $sekolah !== '' ? $sekolah : 'Lintas sekolah/madrasah';
}

/**
 * Baris pesan siap dipakai lapisan tampilan/JSON: melengkapi keterangan asal, penanda "milik saya",
 * dan waktu yang ditulis ringkas.
 */
function chat_baris(array $p, int $userId = 0): array
{
    $uid = (int) ($p['user_id'] ?? 0);
    return [
        'id' => (int) $p['id'],
        'user_id' => $uid,
        'nama' => (string) ($p['nama'] ?? ''),
        'username' => (string) ($p['username'] ?? ''),
        'role' => (string) ($p['role'] ?? ''),
        'peran' => role_label((string) ($p['role'] ?? '')),
        'sekolah' => (string) ($p['sekolah'] ?? ''),
        'asal' => chat_asal_label((string) ($p['role'] ?? ''), (string) ($p['sekolah'] ?? '')),
        'isi' => (string) ($p['isi'] ?? ''),
        'waktu' => (string) ($p['waktu'] ?? ''),
        'jam' => chat_jam((string) ($p['waktu'] ?? '')),
        'saya' => $uid > 0 && $uid === $userId,
    ];
}

/** Jam:menit dari waktu database ('' bila tidak dikenali). */
function chat_jam(string $waktu): string
{
    $ts = strtotime($waktu);
    return $ts === false ? '' : date('H:i', $ts);
}

/**
 * Jumlah pesan yang boleh dikirim lagi pada menit ini (pengaman sederhana anti-spam per sesi).
 * Dikembalikan SISA kuota; 0 berarti sudah penuh.
 */
function chat_sisa_kuota(): int
{
    $kini = time();
    $jendela = (int) ($_SESSION['chat_jendela'] ?? 0);
    $jumlah = (int) ($_SESSION['chat_jumlah'] ?? 0);
    if ($kini - $jendela >= 60) {
        $_SESSION['chat_jendela'] = $kini;
        $_SESSION['chat_jumlah'] = 0;
        $jumlah = 0;
    }
    return max(0, CHAT_MAKS_MENIT - $jumlah);
}

/** Catat satu pesan terkirim untuk hitungan kuota per menit. */
function chat_catat_kuota(): void
{
    $_SESSION['chat_jumlah'] = (int) ($_SESSION['chat_jumlah'] ?? 0) + 1;
}

// ------------------------------------------------------------------ kotak mengambang

/** Apakah kotak obrolan perlu dicetak pada halaman ini? */
function chat_kotak_tampil(): bool
{
    if (!current_user()) {
        return false;
    }
    // Akun SISWA tidak ikut mengobrol: halaman siswa hanya untuk MELIHAT & MENCETAK datanya sendiri
    // (permintaan pemilik), sehingga kotak obrolannya tidak dicetak sama sekali.
    if (is_siswa()) {
        return false;
    }
    // Saat aplikasi TERKUNCI (masa aktif habis) obrolan ikut mati — halaman lain pun dialihkan.
    $s = current_sekolah();
    return !($s && aktivasi_terkunci($s));
}

/**
 * KOTAK OBROLAN MENGAMBANG (pojok kanan bawah) — dicetak `page_bottom()` sehingga tampil di SEMUA
 * halaman berpengguna (halaman cetak tidak memakai layout, jadi tidak ikut).
 *
 * Isi pesan awal dicetak SERVER (tetap terbaca walau skrip gagal dimuat) dan dibungkus `tetap_kata()`
 * supaya tulisan yang diketik pengguna TIDAK ikut penyesuaian istilah (mis. "rapat sekolah" tidak
 * berubah menjadi "rapat madrasah" pada halaman madrasah).
 */
function chat_kotak(): void
{
    if (!chat_kotak_tampil()) {
        return;
    }
    $u = current_user();
    $uid = (int) $u['id'];
    $pesan = chat_pesan_list(0, CHAT_MUAT);
    $belum = chat_belum_dibaca($uid);
    $online = function_exists('log_jumlah_online') ? log_jumlah_online() : 0;
    $terakhir = $pesan ? (int) end($pesan)['id'] : 0;
    ?>
<div class="chat-kotak" id="chatKotak"
     data-terakhir="<?= (int) $terakhir ?>"
     data-belum="<?= (int) $belum ?>"
     data-online="<?= (int) $online ?>"
     data-jeda="<?= (int) CHAT_JEDA_DETIK * 1000 ?>"
     data-maks="<?= (int) CHAT_MAKS ?>"
     data-url="chat.php">
  <button class="chat-tombol" type="button" id="chatTombol" aria-expanded="false"
          aria-controls="chatPanel" title="Obrolan sesama pengguna aplikasi">
    <?= ikon('chat', 22) ?>
    <span class="chat-lencana" id="chatLencana"<?= $belum > 0 ? '' : ' hidden' ?>><?= (int) $belum ?></span>
  </button>

  <div class="chat-panel" id="chatPanel" role="dialog" aria-label="Obrolan" hidden>
    <div class="chat-kepala">
      <div>
        <b><?= ikon('chat', 15) ?> Obrolan</b>
        <span class="xs mut" id="chatStatus">
          Satu ruang untuk semua pengguna &middot; <b><?= (int) $online ?></b> online
        </span>
      </div>
      <button class="chat-tutup" type="button" id="chatTutup" title="Tutup"><?= ikon('prev', 16) ?></button>
    </div>

    <div class="chat-isi" id="chatIsi" aria-live="polite">
      <?php if (!$pesan): ?>
        <p class="chat-kosong" id="chatKosong">Belum ada obrolan. Sapa rekan Anda yang sedang online.</p>
      <?php else: ?>
        <?php foreach ($pesan as $p): $pb = chat_baris($p, $uid); ?>
          <div class="chat-pesan<?= $pb['saya'] ? ' saya' : '' ?>" data-id="<?= (int) $pb['id'] ?>">
            <?php if (!$pb['saya']): ?>
              <span class="chat-dari"><?= e($pb['nama']) ?> &middot; <?= e($pb['asal']) ?></span>
            <?php endif; ?>
            <span class="chat-gelembung"><?= nl2br(e(tetap_kata($pb['isi']))) ?></span>
            <span class="chat-jam"><?= e($pb['jam']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <form class="chat-form" id="chatForm" method="post" action="chat.php">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="kirim">
      <textarea id="chatIsian" name="isi" rows="2" maxlength="<?= (int) CHAT_MAKS ?>"
                aria-label="Tulis pesan obrolan"
                placeholder="Tulis pesan… (Enter untuk kirim)"></textarea>
      <div class="chat-aksi">
        <span class="xs mut" id="chatSisa"><?= (int) CHAT_MAKS ?> karakter</span>
        <button class="btn btn-primary btn-sm" type="submit" id="chatKirim"><?= ikon('next', 14) ?> Kirim</button>
      </div>
    </form>
    <p class="chat-catatan xs mut">
      Pesan tampil otomatis setiap <?= (int) CHAT_JEDA_DETIK ?> detik &middot; satu ruang untuk
      seluruh pengguna aplikasi.
    </p>
  </div>
</div>
    <?php
}
