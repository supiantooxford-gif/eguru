<?php
/**
 * ENDPOINT OBROLAN (chat) — dipakai kotak mengambang di pojok kanan bawah pada SEMUA halaman.
 *
 * Permintaan pemilik: *"Tambahkan fitur chat agar sesama pengguna aplikasi yang sedang online bisa
 * berkomunikasi"* — satu ruang bersama, semua peran, pesan baru muncul otomatis tiap beberapa detik.
 *
 * Selalu membalas **JSON** (`Content-Type: application/json`), kecuali kegagalan keamanan yang tetap
 * memakai kode HTTP yang benar:
 *   • belum masuk          → 302 (dialihkan ke halaman masuk oleh require_login());
 *   • POST tanpa CSRF      → 419 + JSON;
 *   • permintaan tak dikenal → 400 + JSON.
 *
 * Aksi:
 *   GET  ?aksi=ambil&sejak=ID[&baca=1] → pesan baru (id > sejak), jumlah belum dibaca, jumlah online
 *   POST  aksi=kirim (+csrf, isi)      → menyimpan satu pesan lalu mengembalikannya
 *
 * `baca=1` dikirim kotak obrolan saat panelnya TERBUKA: pesan yang tampil langsung ditandai terbaca
 * (lencana belum dibaca hilang), sedangkan saat panel tertutup lencananya dibiarkan bertambah.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

/** Balasan JSON + kode HTTP. */
function chat_json(array $data, int $kode = 200): void
{
    http_response_code($kode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$u = require_login();          // mencatat "online" (log_sesi_ping) + gerbang aktivasi/pengawas
$uid = (int) $u['id'];
$aksi = (string) ($_POST['aksi'] ?? $_GET['aksi'] ?? 'ambil');

if ($aksi === 'kirim') {
    // Endpoint ini bisa dipanggil tanpa JavaScript (klien uji / formulir biasa). Tanpa CSRF → 419.
    if (!csrf_ok()) {
        chat_json(['ok' => false, 'pesan_galat' => 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.'], 419);
    }
    if (chat_sisa_kuota() <= 0) {
        chat_json(['ok' => false, 'pesan_galat' =>
            'Terlalu banyak pesan dalam satu menit. Tunggu sebentar sebelum mengirim lagi.'], 429);
    }
    $isi = (string) ($_POST['isi'] ?? '');
    if (trim($isi) === '') {
        chat_json(['ok' => false, 'pesan_galat' => 'Pesan masih kosong.'], 400);
    }
    $baru = chat_kirim($isi, $u);
    if ($baru === null) {
        chat_json(['ok' => false, 'pesan_galat' => 'Pesan tidak dapat disimpan.'], 400);
    }
    chat_catat_kuota();
    chat_tandai_dibaca($uid, (int) $baru['id']);   // pesan sendiri otomatis "terbaca"
    chat_json(['ok' => true, 'pesan' => chat_baris($baru, $uid),
               'terakhir' => (int) $baru['id'], 'belum' => chat_belum_dibaca($uid),
               'online' => log_jumlah_online()]);
}

if ($aksi !== 'ambil') {
    chat_json(['ok' => false, 'pesan_galat' => 'Permintaan tidak dikenal.'], 400);
}

$sejak = max(0, (int) ($_GET['sejak'] ?? 0));
$baris = chat_pesan_list($sejak);
$pesan = array_map(static fn(array $p): array => chat_baris($p, $uid), $baris);
$terakhir = $pesan ? (int) end($pesan)['id'] : $sejak;

// Panel obrolan sedang terbuka → yang tampil dianggap sudah dibaca.
if ((int) ($_GET['baca'] ?? 0) === 1 && $terakhir > 0) {
    chat_tandai_dibaca($uid, $terakhir);
}

chat_json([
    'ok' => true,
    'pesan' => $pesan,
    'terakhir' => $terakhir,
    'belum' => chat_belum_dibaca($uid),
    'online' => log_jumlah_online(),
    'saya' => ['id' => $uid, 'nama' => (string) $u['nama']],
]);
