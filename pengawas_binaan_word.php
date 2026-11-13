<?php
/**
 * UNDUHAN WORD (.docx) "DAFTAR AKUN PENGAWAS" — e-GURU v1.83.0.
 *
 * Permintaan pemilik (tangkapan layar kepala kartu "Daftar Akun Pengawas"): *"Yang saya tandai warna
 * merah tambahkan fitur download dan cetak. Hasil cetak buat seperti contoh agar terlihat rapi"*.
 * Bentuknya sederhana seperti contoh: judul + tabel berkolom **No, Pengawas Bina, Madrasah Binaan
 * (bernomor 1..n), Masa Aktif** — tanpa kop sekolah dan tanpa tanda tangan.
 *
 * Isi kolom Masa Aktif (v1.94.0 — permintaan pemilik): *"Pada bagian daftar akun pengawas pada fitur
 * download word, buat hasil download word pada bagian masa aktif tampilkan masa aktif jumlah hari
 * pada setiap lembaganya"* → kolom itu kini memuat **sisa masa aktif (jumlah hari) TIAP lembaga
 * binaan**, bernomor sama dengan daftar madrasah di kolom sebelahnya (mis. "1. 45 hari",
 * "2. Tanpa batas", "3. 13 hari (uji coba)", "4. Habis"). Baris JUMLAH tetap merangkum seluruh
 * binaan (permanen/aktif/uji coba/terkunci).
 *
 * Sumber data = `pengawas_binaan_ringkas()` (sama dengan halaman Akun Pengawas & lembar cetak);
 * angka harinya dari `pengawas_binaan_hari()` yang membaca `aktivasi_status()` — jangan menghitung
 * sisa hari di berkas ini. Berkas .docx dibuat sendiri oleh `inc/docx.php` (tanpa ZipArchive).
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

// Susun baris tabel: nomor urut, nama pengawas, daftar binaan bernomor, sisa masa aktif TIAP binaan.
$isiBaris = [];
$n = 0;
foreach ($baris as $b) {
    $n++;
    $daftar = [];
    $masaHari = [];
    foreach ($b['binaan'] as $i => $sk) {
        $daftar[] = ($i + 1) . '. ' . (string) $sk['nama'];
        // Nomor di kolom Masa Aktif SENGAJA sama dengan nomor madrasahnya supaya mudah dipasangkan.
        $masaHari[] = ($i + 1) . '. ' . (string) ($b['masa_hari'][$i]['teks'] ?? '-');
    }
    $isiBaris[] = [
        (string) $n,
        (string) $b['nama'] . ($b['aktif'] ? '' : "\n(Nonaktif)"),
        $daftar ? implode("\n", $daftar) : 'Belum ada sekolah/madrasah binaan',
        $masaHari ? implode("\n", $masaHari) : '-',
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
    ['teks' => 'Keterangan: kolom Masa Aktif memuat SISA MASA AKTIF (jumlah hari) setiap madrasah '
        . 'binaan pada baris itu - nomornya sama dengan nomor madrasah di kolom sebelumnya. '
        . 'Tanpa batas = masa aktif permanen / tanpa tanggal berakhir; N hari = sisa masa aktif yang '
        . 'masih berjalan; N hari (uji coba) = sisa masa uji coba; Habis = masa aktif atau masa uji '
        . 'coba sudah berakhir sehingga aplikasi lembaga itu terkunci. Yang dihitung hanya binaan '
        . 'aktif; binaan nonaktif tidak dapat dibuka pengawas. Baris JUMLAH merangkum jumlah binaan '
        . 'per keadaan (permanen, aktif, uji coba, terkunci).', 'ukuran' => 18, 'jarak' => 60],
];

docx_kirim('Daftar-Akun-Pengawas-' . date('Y-m-d') . '.docx',
    docx_bangun($blok, 'Daftar Akun Pengawas'));
