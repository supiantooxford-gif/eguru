<?php
/**
 * GAMBAR HALAMAN DEPAN (LANDING) — DILAYANI APLIKASI SENDIRI (v1.91.0).
 *
 * Permintaan pemilik: *"Buat semua gambar yang ditampilkan di halaman depan/landing seperti gambar
 * slider, foto testimoni dan foto pengembang tidak bisa dibuka atau diklik kanan atau dilihat alamat
 * sumbernya agar tidak bisa diambil atau di download oleh pengunjung"*.
 *
 * Cara kerjanya:
 *   1. Halaman depan TIDAK LAGI mencetak alamat foto asli (CDN media proxy) ke dalam HTML. Yang
 *      dicetak hanyalah alamat aplikasi sendiri: `gambar.php?j=<jenis>&id=<id>&v=<sidik>`.
 *      Jadi "View page source" maupun klik kanan tidak memperlihatkan alamat sumber fotonya.
 *   2. `gambar.php` mengambil fotonya dari CDN DI SISI SERVER lalu meneruskannya ke peramban.
 *      Permintaan yang datang BUKAN dari halaman aplikasi ini (Referer kosong atau host lain)
 *      dijawab **403**, sehingga alamat gambar tidak bisa dibuka langsung di tab baru, tidak bisa
 *      dipasang (hotlink) di situs lain, dan tidak bisa dibagikan sebagai tautan gambar.
 *   3. Di sisi tampilan, gambar diberi kelas `lp-lindung` + `data-lindung="1"` yang membuatnya
 *      tidak bisa diklik kanan, tidak bisa diseret, dan tidak memunculkan menu "Simpan gambar"
 *      pada tekan-lama di HP (lihat blok CSS "GAMBAR HALAMAN DEPAN … DILINDUNGI" dan
 *      `initLindungGambar()` di `assets/app.js`).
 *
 * ⚠️ BATAS TEKNIS YANG PERLU DIKETAHUI PEMILIK: gambar yang tampil di layar memang SUDAH
 * diunduh peramban (itu cara peramban menampilkan gambar). Perlindungan di atas menutup jalan
 * yang wajar dipakai pengunjung (klik kanan → Simpan gambar, seret ke desktop, buka alamat
 * gambar, hotlink), tetapi pengunjung yang membuka Developer Tools tetap dapat menyalin berkas
 * yang sudah ada di perambannya. Tidak ada cara menghilangkan kemungkinan itu sepenuhnya.
 *
 * Mengubah foto (Super Admin) TIDAK perlu mengubah apa pun di sini: `v` adalah sidik alamat foto,
 * jadi berkas yang diganti otomatis memakai alamat baru (cache peramban tidak salah tampil).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php'; // memuat helpers.php + landing.php sekaligus (fungsi q/one)

/** Jenis gambar halaman depan yang boleh diminta lewat `gambar.php`. */
const GAMBAR_JENIS = ['slider', 'testimoni', 'tentang', 'logo'];

/** Batas ukuran berkas yang mau diteruskan (pengaman; batas unggah aplikasi 2 MB). */
const GAMBAR_MAKS_BYTE = 6 * 1024 * 1024;

/** Lama cache di peramban pengunjung (detik). Alamat ikut berubah bila fotonya diganti. */
const GAMBAR_CACHE_DETIK = 604800; // 7 hari

/** Jenis MIME yang boleh diteruskan ke peramban. */
const GAMBAR_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

/**
 * Halaman aplikasi yang boleh menjadi asal permintaan gambar (dipakai HANYA sebagai cadangan untuk
 * peramban lama yang belum mengirim `Sec-Fetch-Site`). Yang dipakai adalah NAMA HALAMAN, bukan host —
 * lihat catatan pada gambar_referer_sah().
 */
const GAMBAR_REFERER_HALAMAN = ['login.php', 'index.php', 'daftar.php'];

/**
 * Apakah alamat foto boleh diambil server ini?
 *
 * Hanya http/https pada host yang memang dipakai media proxy platform. Ini sekaligus mencegah
 * aplikasi dipakai mengambil alamat sembarang (SSRF) bila suatu saat nilai di basis data diubah.
 */
function gambar_host_sah(string $url): bool
{
    if ($url === '' || strlen($url) > 500) {
        return false;
    }
    $p = parse_url($url);
    if (!is_array($p)) {
        return false;
    }
    $skema = strtolower((string) ($p['scheme'] ?? ''));
    if ($skema !== 'https' && $skema !== 'http') {
        return false;
    }
    $host = strtolower((string) ($p['host'] ?? ''));
    if ($host === '') {
        return false;
    }
    foreach (GAMBAR_HOST_IZIN as $izin) {
        if ($host === strtolower($izin)) {
            return true;
        }
    }
    // Cadangan: seluruh subdomain media platform.
    return substr($host, -strlen('.vibecoder.co.id')) === '.vibecoder.co.id';
}

/**
 * Alamat foto asli (CDN) untuk satu gambar halaman depan — HANYA dipakai di sisi server.
 *
 * @param string $jenis slider|testimoni|tentang|logo
 * @param int    $id    id baris slider/testimoni
 * @param int    $n     nomor slot foto "Tentang Aplikasi" (1..TENTANG_FOTO_SLOT)
 */
function gambar_remote(string $jenis, int $id = 0, int $n = 0): string
{
    if (!in_array($jenis, GAMBAR_JENIS, true)) {
        return '';
    }
    $url = '';
    try {
        if ($jenis === 'slider' || $jenis === 'testimoni') {
            if ($id <= 0) {
                return '';
            }
            $tabel = $jenis === 'slider' ? 'landing_slider' : 'landing_testimoni';
            $baris = one('SELECT foto FROM ' . $tabel . ' WHERE id = ? AND aktif = 1', [$id]);
            $url = trim((string) ($baris['foto'] ?? ''));
        } elseif ($jenis === 'tentang') {
            if ($n < 1 || $n > TENTANG_FOTO_SLOT) {
                return '';
            }
            $url = trim(app_setting('tentang_foto_' . $n));
        } else { // logo
            $url = trim(app_setting('logo_app'));
        }
    } catch (Throwable $e) {
        return '';
    }
    return gambar_host_sah($url) ? $url : '';
}

/** Sidik pendek alamat foto — dipakai sebagai `v=` (pengusir cache) dan ETag. */
function gambar_versi(string $remote): string
{
    return substr(sha1($remote), 0, 12);
}

/**
 * Alamat LOKAL gambar (yang dicetak ke HTML). Selalu relatif — aplikasi berada di subfolder.
 */
function gambar_url(string $jenis, string $remote, int $id = 0, int $n = 0): string
{
    if ($remote === '' || !in_array($jenis, GAMBAR_JENIS, true)) {
        return '';
    }
    $q = 'j=' . rawurlencode($jenis);
    if ($id > 0) {
        $q .= '&id=' . $id;
    }
    if ($n > 0) {
        $q .= '&n=' . $n;
    }
    $q .= '&v=' . gambar_versi($remote);
    return 'gambar.php?' . $q;
}

/**
 * Markup `<img>` gambar halaman depan yang sudah dilindungi.
 *
 * Semua gambar halaman depan WAJIB memakai fungsi ini supaya (a) alamat CDN tidak pernah bocor ke
 * HTML dan (b) penjagaan klik-kanan/seret selalu terpasang.
 */
function gambar_tag(string $jenis, string $remote, string $alt, int $id = 0, int $n = 0,
                    string $kelas = '', bool $lazy = false): string
{
    $src = gambar_url($jenis, $remote, $id, $n);
    if ($src === '') {
        return '';
    }
    $kelas = trim($kelas . ' lp-lindung');
    return '<img src="' . e($src) . '" alt="' . e($alt) . '" class="' . e($kelas) . '"'
        . ' data-lindung="1" draggable="false" decoding="async"'
        . ($lazy ? ' loading="lazy"' : '') . '>';
}

/**
 * Apakah permintaan gambar datang dari halaman aplikasi ini sendiri?
 *
 * 🚨 JEBAKAN YANG SUDAH MENGGIGIT (jangan diulang): pada **domain sendiri** (mis. asisten.guru)
 * platform meneruskan permintaan dengan `HTTP_HOST` = host INTERNAL (supianto.vibecoder.co.id),
 * sedangkan peramban mengirim `Referer: https://asisten.guru/login.php`. Pencocokan HOST Referer
 * dengan `HTTP_HOST` karena itu GAGAL → seluruh gambar halaman depan dijawab 403 dan tidak tampil.
 *
 * Aturannya sekarang: **jangan membandingkan host sama sekali.** Yang dipakai:
 *   1. `Sec-Fetch-Site` — diisi PERAMBAN sendiri (tidak dapat dipalsukan dari JavaScript) dan
 *      berbasis origin yang DILIHAT pengunjung, jadi tetap benar di alamat bawaan MAUPUN domain
 *      sendiri:
 *        same-origin / same-site → berasal dari halaman aplikasi ini (termasuk favicon) → boleh;
 *        cross-site              → dipasang di situs lain (hotlink) → tolak;
 *        none                    → alamat ditempel/dibuka langsung di tab baru → tolak.
 *   2. Peramban lama yang belum mengirim `Sec-Fetch-Site` (mis. Safari < 16.4) → pakai `Referer`,
 *      tetapi dicocokkan lewat **NAMA HALAMAN**-nya (`GAMBAR_REFERER_HALAMAN`), bukan host.
 */
function gambar_referer_sah(): bool
{
    $sit = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($sit !== '') {
        return $sit === 'same-origin' || $sit === 'same-site';
    }

    $ref = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($ref === '') {
        return false;
    }
    $jalur = (string) parse_url($ref, PHP_URL_PATH);
    $nama = strtolower(basename($jalur));
    return in_array($nama, GAMBAR_REFERER_HALAMAN, true);
}

/** Jenis gambar dari ekstensi (cadangan bila CDN tidak mengirim Content-Type yang jelas). */
function gambar_mime_dari_url(string $url): string
{
    $jalur = strtolower((string) parse_url($url, PHP_URL_PATH));
    $ext = (string) pathinfo($jalur, PATHINFO_EXTENSION);
    $peta = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
    ];
    return $peta[$ext] ?? '';
}

/**
 * Ambil isi foto dari CDN dengan `file_get_contents` (cadangan bila curl tidak tersedia/gagal).
 *
 * @return array{0:string,1:string,2:int} [isi, content-type, kode HTTP]
 */
function gambar_ambil_stream(string $url): array
{
    $konteks = stream_context_create([
        'http' => ['timeout' => 10, 'user_agent' => 'e-GURU/' . APP_VERSION, 'follow_location' => 1, 'max_redirects' => 2],
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $isi = (string) @file_get_contents($url, false, $konteks, 0, GAMBAR_MAKS_BYTE + 1);
    $tipe = '';
    foreach (($http_response_header ?? []) as $h) {
        if (stripos($h, 'content-type:') === 0) {
            $tipe = trim(substr($h, 13));
        }
    }
    return [$isi, $tipe, $isi !== '' ? 200 : 0];
}

/**
 * Ambil isi foto dari CDN (sisi server).
 *
 * curl dipakai lebih dulu (paling andal & cepat); bila curl tidak tersedia ATAU gagal (mis. sertifikat
 * CA tidak terpasang pada setelan PHP server), jalur cadangan `file_get_contents` dicoba. Tanpa
 * cadangan itu satu kegagalan curl membuat seluruh gambar halaman depan tidak tampil.
 *
 * @return array{ok:bool,isi:string,mime:string,kode:int}
 */
function gambar_ambil(string $url): array
{
    $keluar = ['ok' => false, 'isi' => '', 'mime' => '', 'kode' => 0];
    if (!gambar_host_sah($url)) {
        return $keluar;
    }

    $isi = '';
    $tipe = '';
    $kode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 2,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'e-GURU/' . APP_VERSION,
        ]);
        $hasilCurl = curl_exec($ch);
        $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tipe = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        if (is_string($hasilCurl)) {
            $isi = $hasilCurl;
        }
        curl_close($ch);
    }

    if (($kode !== 200 || $isi === '') && (bool) ini_get('allow_url_fopen')) {
        [$isiCadangan, $tipeCadangan, $kodeCadangan] = gambar_ambil_stream($url);
        if ($kodeCadangan === 200 && $isiCadangan !== '') {
            $isi = $isiCadangan;
            $tipe = $tipeCadangan;
            $kode = $kodeCadangan;
        }
    }

    if ($kode !== 200 || $isi === '' || strlen($isi) > GAMBAR_MAKS_BYTE) {
        return $keluar;
    }

    $mime = strtolower(trim((string) explode(';', $tipe)[0]));
    if (!in_array($mime, GAMBAR_MIME, true)) {
        $mime = gambar_mime_dari_url($url);
    }
    if ($mime === '') {
        return $keluar;
    }
    // SVG dapat memuat skrip: hanya diteruskan bila benar-benar bersih (lihat svg_aman()).
    if ($mime === 'image/svg+xml') {
        $cek = svg_aman($isi);
        if (empty($cek['ok'])) {
            return $keluar;
        }
    }

    return ['ok' => true, 'isi' => $isi, 'mime' => $mime, 'kode' => $kode];
}

/** Balasan galat singkat (tanpa isi halaman aplikasi). */
function gambar_galat(int $kode): void
{
    if (!headers_sent()) {
        http_response_code($kode);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo $kode === 403 ? 'Gambar hanya dapat ditampilkan pada halaman aplikasi ini.'
                       : 'Gambar tidak ditemukan.';
}

/**
 * Titik masuk `gambar.php`: validasi → ambil dari CDN → teruskan ke peramban.
 */
function gambar_halaman(): void
{
    $jenis = (string) ($_GET['j'] ?? '');
    $id    = (int) ($_GET['id'] ?? 0);
    $n     = (int) ($_GET['n'] ?? 0);

    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Robots-Tag: noindex, nofollow, noimageindex');
    }

    $remote = gambar_remote($jenis, $id, $n);
    if ($remote === '') {
        gambar_galat(404);
        return;
    }

    // Hanya boleh dibuka dari halaman aplikasi ini (bukan dibuka langsung / dipasang di situs lain).
    if (!gambar_referer_sah()) {
        gambar_galat(403);
        return;
    }

    $versi = gambar_versi($remote);
    $etag  = '"' . $versi . '"';
    if (!headers_sent()) {
        header('ETag: ' . $etag);
        header('Cache-Control: private, max-age=' . GAMBAR_CACHE_DETIK . ', immutable');
    }
    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        return;
    }

    $hasil = gambar_ambil($remote);
    if (empty($hasil['ok'])) {
        error_log('[e-GURU] gambar: gagal mengambil "' . $jenis . '" (kode ' . (int) $hasil['kode'] . ')');
        gambar_galat(404);
        return;
    }

    if (!headers_sent()) {
        header('Content-Type: ' . $hasil['mime']);
        header('Content-Length: ' . strlen($hasil['isi']));
        header('Content-Disposition: inline');
        if ($hasil['mime'] === 'image/svg+xml') {
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
        }
    }
    echo $hasil['isi'];
}
