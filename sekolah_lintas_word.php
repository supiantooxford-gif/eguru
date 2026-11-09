<?php
/**
 * UNDUHAN WORD (.docx) "DAFTAR SEKOLAH/MADRASAH" (Ringkasan Semua Sekolah).
 *
 * Permintaan pemilik (v1.82.0): *"Pada yang saya tandai warna merah tambahkan fitur download word
 * dan cetak (kolom mapel, jurnal dan nilai tidak perlu di cetak/download). Hanya No, Nama Madrasah,
 * Kelas, Guru, Siswa, Masa Aktif"*.
 *
 * Isi berkas = ENAM kolom yang diminta: No, Sekolah/Madrasah, Kelas, Guru, Siswa, Masa Aktif.
 * Kolom Mapel, Jurnal, dan Nilai SENGAJA tidak ikut — jangan ditambahkan tanpa bertanya.
 *
 * Sumber data sama dengan dasbor & lembar cetak (`sekolah_lintas_ringkas()`), jadi angka, keadaan
 * masa aktif, dan urutannya selalu sama. Berkas .docx dibuat sendiri oleh `inc/docx.php`
 * (tanpa ZipArchive/pustaka luar).
 *
 * Akses: Super Admin (sama seperti dasbor lintas sekolah).
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/docx.php';

$u = require_login();
if (!is_super()) {
    tolak403('Unduhan daftar sekolah/madrasah ini hanya untuk akun Super Admin.');
}
$GLOBALS['__vs_istilah_nonaktif'] = true;   // istilah generik "Sekolah/Madrasah"

$ringkas = sekolah_lintas_ringkas();
$baris = $ringkas['baris'];
$tot = $ringkas['tot'];

// Susun baris tabel: nomor urut, nama lembaga, kelas, guru, siswa, masa aktif (teks biasa).
$isiBaris = [];
$n = 0;
foreach ($baris as $b) {
    $n++;
    $s = $b['sekolah'];
    $st = $b['stat'];
    $jenis = $s['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah Umum';
    $npsn = trim((string) ($s['npsn'] ?? '')) !== '' ? 'NPSN ' . (string) $s['npsn'] : 'NPSN -';
    $isiBaris[] = [
        (string) $n,
        nama_sekolah_tampil($s) . "\n" . $jenis . ', ' . $npsn,
        (string) (int) $st['kelas'],
        (string) (int) $st['guru'],
        (string) (int) $st['siswa'],
        masa_aktif_teks($s),
    ];
}
$isiBaris[] = [
    '', 'JUMLAH (' . count($baris) . ' sekolah/madrasah)',
    (string) (int) $tot['kelas'], (string) (int) $tot['guru'], (string) (int) $tot['siswa'], '',
];

$blok = [
    ['teks' => APP_NAME . ' — ' . APP_SUB, 'ukuran' => 20, 'jarak' => 20],
    ['teks' => 'Daftar Sekolah/Madrasah', 'tebal' => true, 'tengah' => true, 'ukuran' => 32, 'jarak' => 40],
    ['teks' => 'Seluruh sekolah/madrasah aktif: ' . count($baris) . ' lembaga', 'tengah' => true, 'ukuran' => 20, 'jarak' => 20],
    ['teks' => 'Urutan: masa aktif paling lama di atas. Dicetak: '
        . tanggal_id(date('Y-m-d'), true) . ', ' . date('H:i')
        . ' oleh ' . (string) $u['nama'] . ' (' . role_label('super') . ').', 'ukuran' => 18, 'jarak' => 120],
    ['tabel' => [
        ['No', 'Sekolah/Madrasah', 'Kelas', 'Guru', 'Siswa', 'Masa Aktif'],
        $isiBaris,
    ]],
    ['teks' => 'Keterangan: kolom Masa Aktif dibaca dari status aktivasi tiap lembaga (permanen, masa '
        . 'aktif 365 hari, masa uji coba, atau sudah berakhir/terkunci). Angka diambil saat berkas '
        . 'ini dibuat; rincian lengkap tiap lembaga tersedia pada dasbor Ringkasan Semua Sekolah.',
        'ukuran' => 18, 'jarak' => 60],
];

$namaBerkas = 'Daftar-Sekolah-Madrasah-' . date('Y-m-d') . '.docx';
docx_kirim($namaBerkas, docx_bangun($blok, 'Daftar Sekolah/Madrasah'));
