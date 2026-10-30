<?php
/**
 * Bagian bersama untuk lembar CETAK: kop sekolah/madrasah, identitas, dan tanda tangan.
 * Halaman cetak tidak memakai layout, jadi harus memanggil ob_start() +
 * terjemahkan_selesai() sendiri agar istilah Sekolah/Madrasah ikut menyesuaikan.
 *
 * CATATAN: kepala_nama(), kepala_nip(), dan jabatan_kepala() ada di inc/helpers.php
 * (BUKAN di sini) karena halaman non-cetak seperti notulen_form.php juga memakainya —
 * fungsi data wajib ada di helpers agar tidak perlu meng-include berkas kop.
 */
declare(strict_types=1);

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
    // Nilai identitas diambil dari PENGATURAN sekolah yang sedang aktif (konteks) — perilaku yang
    // sudah dipakai seluruh lembar cetak lain.
    kop_render([
        'logo'    => logo_sekolah(),
        'nama'    => nama_sekolah(),
        'alamat'  => setting('alamat_sekolah', ''),
        'wilayah' => alamat_wilayah(),
        'email'   => setting('email_sekolah', ''),
        'website' => setting('website', ''),
        'npsn'    => setting('npsn', ''),
        'nsm'     => setting('nsm', ''),
        'hp'      => setting('telepon_sekolah', ''),
    ], $judul, $baris);
}

/**
 * Kop dokumen cetak untuk SEKOLAH/MADRASAH TERTENTU (bukan sekolah dalam konteks).
 *
 * Dibutuhkan lembar lintas sekolah yang mencetak beberapa binaan sekaligus dalam satu berkas
 * (mis. `cetak_meeting.php` — daftar hadir meeting pengawas untuk SELURUH binaannya). Tanpa fungsi
 * ini kop semua lembar akan memakai sekolah konteks, sehingga kop binaan lain ikut salah.
 *
 * @param array<string,mixed> $sekolah baris tabel `sekolah`
 * @param array<int,string>   $baris   baris tambahan di bawah identitas
 */
function kop_cetak_sekolah(array $sekolah, string $judul, array $baris = []): void
{
    kop_render([
        'logo'    => (string) ($sekolah['logo'] ?? ''),
        'nama'    => (string) ($sekolah['nama'] ?? ''),
        'alamat'  => (string) ($sekolah['alamat'] ?? ''),
        'wilayah' => alamat_wilayah_dari($sekolah),
        'email'   => (string) ($sekolah['email'] ?? ''),
        'website' => (string) ($sekolah['website'] ?? ''),
        'npsn'    => (string) ($sekolah['npsn'] ?? ''),
        'nsm'     => (string) ($sekolah['nsm'] ?? ''),
        'hp'      => (string) ($sekolah['telepon'] ?? ''),
    ], $judul, $baris);
}

/**
 * Inti pencetakan kop — dipakai `kop_cetak()` (sekolah konteks) dan `kop_cetak_sekolah()`
 * (sekolah tertentu). Jangan memanggilnya langsung dari halaman; pakai salah satu pembungkusnya.
 *
 * @param array<string,string> $n nilai identitas: logo, nama, alamat, wilayah, email, website, npsn, nsm, hp
 * @param array<int,string>    $baris baris tambahan
 */
function kop_render(array $n, string $judul, array $baris = []): void
{
    $logo = kop_nilai((string) ($n['logo'] ?? ''));
    echo '<div class="sheet-head' . ($logo !== '' ? ' kop-logo' : '') . '">';
    if ($logo !== '') {
        echo '<div class="kop-gambar"><img src="' . e($logo) . '" alt="Logo"></div>';
    }
    echo '<div class="kop-teks">';
    // Judul kop boleh dikosongkan oleh halaman pemanggil: saat judulnya '' <h2> tidak dicetak,
    // sehingga kop hanya memuat nama sekolah, alamat, dan identitas (dipakai lembar Isi Notulen).
    if (trim($judul) !== '') {
        echo '<h2>' . e($judul) . '</h2>';
    }
    // Nama sekolah/madrasah dibuat TEBAL (permintaan pemilik) — kelas .kop-nama diatur
    // pada assets/style.css supaya berlaku di seluruh lembar cetak.
    $nama = (string) ($n['nama'] ?? '');
    echo '<p class="kop-nama">' . e(tetap_kata($nama !== '' ? $nama : '-')) . '</p>';

    // Baris 2: alamat lengkap — "Alamat (Nama Jalan, Dusun)" (dilindungi tetap_kata() agar kata
    // "Sekolah" di dalam alamat tidak ikut berubah menjadi "Madrasah" pada dokumen madrasah).
    $alamat = kop_nilai((string) ($n['alamat'] ?? ''));
    if ($alamat !== '') {
        echo '<p style="font-size:.78rem">' . e(tetap_kata($alamat)) . '</p>';
    }

    // Baris 2b (v37): wilayah — "Desa/Kelurahan …, Kec. …, Kab. …, Provinsi …". Hanya dicetak bila
    // ada isinya, sehingga kop sekolah yang belum mengisi wilayah tampil persis seperti semula.
    $wilayah = (string) ($n['wilayah'] ?? '');
    if ($wilayah !== '') {
        echo '<p style="font-size:.78rem">' . e(tetap_kata($wilayah)) . '</p>';
    }

    // Baris 3 & 4: "email: …, website: …" lalu "NPSN: …, NSM: …, HP: …" (NSM ikut dicetak bila
    // diisi — permintaan pemilik). Baris yang seluruh isiannya kosong tidak dicetak.
    // Dilindungi tetap_kata() supaya alamat email/situs yang memuat kata "sekolah" tidak
    // diterjemahkan menjadi "madrasah".
    foreach ([
        [['email: ', (string) ($n['email'] ?? '')], ['website: ', (string) ($n['website'] ?? '')]],
        [['NPSN: ', (string) ($n['npsn'] ?? '')], ['NSM: ', (string) ($n['nsm'] ?? '')],
         ['HP: ', (string) ($n['hp'] ?? '')]],
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

/**
 * Blok tanda tangan (dipakai di lembar cetak).
 *
 * @param bool $denganTempat true → cetak baris "tempat, tanggal" DI ATAS blok ini (permintaan
 *   pemilik: pada bagian tanda tangan, tepatnya di atas tanda tangan GURU, ditambahkan nama
 *   KECAMATAN dari identitas lembaga disambung tanggal aktif sistem saat mencetak). Dipakai pada
 *   blok tanda tangan pihak yang menandatangani di sisi KANAN (guru mata pelajaran, wali kelas,
 *   notulis, dst.) — lihat ttd_tempat_tanggal() di inc/helpers.php.
 * @param bool $spacerTempat true → cetak BARIS KOSONG setinggi baris "tempat, tanggal" di blok ini
 *   supaya NAMA kedua blok (kiri & kanan) tetap sejajar walau blok kanannya memakai $denganTempat.
 *   Dipakai bila label blok kiri HANYA satu baris (mis. "Pimpinan Rapat" pada berita acara notulen).
 * @param string $pihak kunci pihak penanda tangan untuk TANDA TANGAN LANGSUNG DI LAYAR SENTUH
 *   ('' = blok ini tidak dapat ditandatangani). Hanya dipakai lembar Notulen Rapat, lihat
 *   inc/notulen_ttd.php.
 * @param string $gambar HTML <img> tanda tangan digital yang sudah tersimpan ('' = belum ada).
 */
function ttd_blok(string $jabatan, string $nama, string $nip = '', string $kelas = 'sheet-sign',
                  bool $denganTempat = false, bool $spacerTempat = false,
                  string $pihak = '', string $gambar = ''): void
{
    echo '<div class="' . e($kelas) . '">';
    if ($denganTempat) {
        echo '<div class="ttd-tempat">' . e(ttd_tempat_tanggal()) . '</div>';
    } elseif ($spacerTempat) {
        // Tingginya WAJIB sama dengan .ttd-tempat (lihat assets/style.css) supaya nama sejajar.
        echo '<div class="ttd-tempat ttd-spacer" aria-hidden="true">&nbsp;</div>';
    }
    // Jabatan boleh berisi BARIS BARU (nl2br) — contoh: "Mengetahui,\nKepala Madrasah" dari
    // ttd_mengetahui(), supaya kedua baris itu setinggi dua baris pada sisi seberangnya sehingga
    // NAMA guru & NAMA kepala tercetak sejajar (permintaan pemilik).
    echo '<div>' . nl2br(e($jabatan)) . '</div>';
    // Ruang tanda tangan: tanda tangan digital (bila ada) + tombol pembuka yang HANYA dimunculkan
    // skrip pada perangkat berlayar sentuh. Untuk lembar selain Notulen Rapat, $pihak kosong
    // sehingga hasilnya persis seperti sebelumnya (<div class="space"></div>).
    echo '<div class="space"' . ($pihak !== '' ? ' data-ttd-sel="' . e($pihak) . '"' : '') . '>';
    echo $gambar;
    if ($pihak !== '' && function_exists('notulen_ttd_tombol')) {
        echo notulen_ttd_tombol($pihak, $gambar !== '');
    }
    echo '</div>';
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
