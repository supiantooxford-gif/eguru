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
 * Nilai identitas kop yang dianggap TERISI. Isian kosong, spasi, atau tanda
 * "-"/"–"/"—" saja (kebiasaan pemilik mengisi "belum ada") tidak ikut dicetak.
 */
function kop_nilai(string $v): string
{
    $v = trim($v);
    return in_array($v, ['', '-', '–', '—', '.', '_'], true) ? '' : $v;
}

/**
 * Kop dokumen cetak: logo (bila ada) di kiri, judul & identitas di tengah.
 *
 * URUTAN & PENULISAN IDENTITAS — contoh nyata yang diminta pemilik (jangan diubah):
 *   MA. Sirojuth Tholibiin Mootilango
 *   Jl. Dusun Gandaria, Desa Sidomukti, Kec. Mootilango
 *   email: ma.sirojuththolibiinmootilngo@gmail.com, website: www.ma.stm.sch.id
 *   NPSN: 70027902, HP: 085394726089
 * Jadi: nama sekolah, alamat lengkap, lalu baris email+website, lalu baris NPSN+HP.
 * Label "email:" & "website:" huruf kecil, "NPSN:" & "HP:" huruf besar, pemisah ", ".
 * Tanda "·" (middot) TIDAK dipakai pada hasil cetak.
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

    // Baris 2: alamat lengkap (dilindungi tetap_kata() agar kata "Sekolah" di dalam
    // alamat tidak ikut berubah menjadi "Madrasah" pada dokumen madrasah).
    $alamat = kop_nilai(setting('alamat_sekolah', ''));
    if ($alamat !== '') {
        echo '<p style="font-size:.78rem">' . e(tetap_kata($alamat)) . '</p>';
    }

    // Baris 3 & 4: "email: …, website: …" lalu "NPSN: …, HP: …". Baris yang seluruh
    // isiannya kosong tidak dicetak. Dilindungi tetap_kata() supaya alamat email/situs
    // yang memuat kata "sekolah" tidak diterjemahkan menjadi "madrasah".
    foreach ([
        [['email: ', setting('email_sekolah', '')], ['website: ', setting('website', '')]],
        [['NPSN: ', setting('npsn', '')], ['HP: ', setting('telepon_sekolah', '')]],
    ] as $kelompok) {
        $bagian = [];
        foreach ($kelompok as [$label, $nilai]) {
            $nilai = kop_nilai($nilai);
            if ($nilai !== '') {
                $bagian[] = $label . $nilai;
            }
        }
        if ($bagian) {
            echo '<p style="font-size:.78rem">' . e(tetap_kata(implode(', ', $bagian))) . '</p>';
        }
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
