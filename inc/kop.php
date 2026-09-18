<?php
/**
 * Bagian bersama untuk lembar CETAK: kop sekolah/madrasah, identitas, dan tanda tangan.
 * Halaman cetak tidak memakai layout, jadi harus memanggil ob_start() +
 * terjemahkan_selesai() sendiri agar istilah Sekolah/Madrasah ikut menyesuaikan.
 */
declare(strict_types=1);

/** Nama kepala sekolah/madrasah dari Pengaturan (dipakai pada tanda tangan). */
function kepala_nama(): string
{
    $n = trim(setting('kepala_sekolah', ''));
    return $n !== '' ? $n : '-';
}

function kepala_nip(): string
{
    return trim(setting('nip_kepala_sekolah', ''));
}

/** "Kepala Sekolah" (otomatis menjadi "Kepala Madrasah" pada jenis madrasah). */
function jabatan_kepala(): string
{
    return istilah_sekolah('Kepala Sekolah');
}

/**
 * Kop dokumen cetak: logo (bila ada) di kiri, judul & identitas di tengah.
 *
 * @param array<int,string> $baris baris tambahan di bawah identitas
 */
function kop_cetak(string $judul, array $baris = []): void
{
    $logo = logo_sekolah();
    echo '<div class="sheet-head' . ($logo !== '' ? ' kop-logo' : '') . '">';
    if ($logo !== '') {
        echo '<div class="kop-gambar"><img src="' . e($logo) . '" alt="Logo"></div>';
    }
    echo '<div class="kop-teks">';
    echo '<h2>' . e($judul) . '</h2>';
    echo '<p>' . e(tetap_kata(nama_sekolah() !== '' ? nama_sekolah() : '-')) . '</p>';
    $info = [];
    if (setting('alamat_sekolah', '') !== '') {
        $info[] = setting('alamat_sekolah');
    }
    if (setting('npsn', '') !== '') {
        $info[] = 'NPSN ' . setting('npsn');
    }
    if (setting('telepon_sekolah', '') !== '') {
        $info[] = 'Telp. ' . setting('telepon_sekolah');
    }
    if ($info) {
        echo '<p style="font-size:.78rem">' . e(implode(' &middot; ', $info)) . '</p>';
    }
    foreach ($baris as $b) {
        echo '<p style="font-size:.78rem">' . e((string) $b) . '</p>';
    }
    echo '</div></div>';
}

/** Blok tanda tangan (dipakai di lembar cetak). */
function ttd_blok(string $jabatan, string $nama, string $nip = '', string $kelas = 'sheet-sign'): void
{
    echo '<div class="' . e($kelas) . '">';
    echo '<div>' . e($jabatan) . '</div>';
    echo '<div class="space"></div>';
    echo '<u>' . e($nama !== '' ? $nama : '-') . '</u><br>';
    echo '<span class="sm">NIP. ' . e($nip !== '' ? $nip : '-') . '</span>';
    echo '</div>';
}

/** Baris identitas lembar cetak dalam bentuk dua kolom. */
function sheet_meta(array $pasangan): void
{
    echo '<div class="sheet-meta">';
    foreach ($pasangan as $label => $nilai) {
        echo '<div><b>' . e((string) $label) . '</b>: ' . e((string) $nilai) . '</div>';
    }
    echo '</div>';
}
