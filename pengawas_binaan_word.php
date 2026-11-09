<?php
/**
 * UNDUHAN WORD (.docx) "DAFTAR AKUN PENGAWAS" — e-GURU v1.83.0.
 *
 * Permintaan pemilik (tangkapan layar kepala kartu "Daftar Akun Pengawas"): *"Yang saya tandai warna
 * merah tambahkan fitur download dan cetak. Hasil cetak buat seperti contoh agar terlihat rapi"*.
 * Bentuknya sederhana seperti contoh: judul + tabel berkolom **No, Pengawas Bina, Madrasah Binaan
 * (bernomor 1..n), Masa Aktif** — tanpa kop sekolah dan tanpa tanda tangan.
 *
 * Isi kolom Masa Aktif = ringkasan binaan **permanen / aktif / terkunci** (pilihan pemilik).
 * Sumber data = `pengawas_binaan_ringkas()` (sama dengan halaman Akun Pengawas & lembar cetak),
 * jadi urutan akun, daftar binaan, dan angkanya selalu sama. Berkas .docx dibuat sendiri oleh
 * `inc/docx.php` (tanpa pustaka luar/ZipArchive).
 *
 * Akses: Super Admin.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/docx.php';

$u = require_login();
if (!is_super()) {
    tolak403('Unduhan daftar akun pengawas ini hanya untuk akun Super Admin.');
}
$GLOBALS['__vs_istilah_nonaktif'] = true;   // istilah generik "Sekolah/Madrasah"

$ringkas = pengawas_binaan_ringkas();
$baris = $ringkas['baris'];
$tot = $ringkas['tot'];

// Susun baris tabel: nomor urut, nama pengawas, daftar binaan bernomor, ringkasan masa aktif.
$isiBaris = [];
$n = 0;
foreach ($baris as $b) {
    $n++;
    $daftar = [];
    foreach ($b['binaan'] as $i => $sk) {
        $daftar[] = ($i + 1) . '. ' . (string) $sk['nama'];
    }
    $isiBaris[] = [
        (string) $n,
        (string) $b['nama'] . ($b['aktif'] ? '' : "\n(Nonaktif)"),
        $daftar ? implode("\n", $daftar) : 'Belum ada sekolah/madrasah binaan',
        (string) $b['masa_teks'],
    ];
}
$isiBaris[] = [
    '', 'JUMLAH (' . (int) $tot['akun'] . ' pengawas)',
    (int) $tot['binaan'] . ' penugasan binaan aktif',
    pengawas_masa_teks([
        'total' => (int) $tot['binaan'], 'permanen' => (int) $tot['permanen'],
        'aktif' => (int) $tot['aktif_binaan'], 'uji' => (int) $tot['uji'],
        'terkunci' => (int) $tot['terkunci'],
    ]),
];

$blok = [
    ['teks' => 'Daftar Akun Pengawas', 'tebal' => true, 'tengah' => true, 'ukuran' => 32, 'jarak' => 40],
    ['teks' => (int) $tot['akun'] . ' akun pengawas (' . (int) $tot['aktif'] . ' aktif), '
        . (int) $tot['binaan'] . ' penugasan binaan aktif. Dicetak: '
        . tanggal_id(date('Y-m-d'), true) . ', ' . date('H:i')
        . ' oleh ' . (string) $u['nama'] . ' (' . role_label('super') . ').', 'tengah' => true,
        'ukuran' => 18, 'jarak' => 120],
    ['tabel' => [
        ['No', 'Pengawas Bina', 'Madrasah Binaan', 'Masa Aktif'],
        $isiBaris,
    ]],
    ['teks' => 'Keterangan: kolom Masa Aktif merangkum status masa aktif seluruh madrasah binaan aktif '
        . 'pada baris itu - permanen (tanpa batas waktu), aktif (masa aktif berjalan), uji coba '
        . '(hanya dicantumkan bila ada), dan terkunci (masa aktif/uji coba sudah habis sehingga '
        . 'aplikasi lembaga itu terkunci). Yang dihitung hanya binaan aktif; binaan nonaktif tidak '
        . 'dapat dibuka pengawas.', 'ukuran' => 18, 'jarak' => 60],
];

docx_kirim('Daftar-Akun-Pengawas-' . date('Y-m-d') . '.docx',
    docx_bangun($blok, 'Daftar Akun Pengawas'));
