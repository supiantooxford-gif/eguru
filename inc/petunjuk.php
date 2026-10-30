<?php
/**
 * PETUNJUK PENGGUNAAN — isi halaman `petunjuk.php`.
 *
 * Permintaan pemilik: *"Di akun guru tambahkan Menu Petunjuk Penggunaan (maksudnya petunjuk
 * penggunaan setiap menu yang ada di akun guru agar guru bisa memahami fitur yang ada di dalam menu
 * tersebut). Menu letakkan dibawah Akun Saya."*
 * Pilihan pemilik (jangan diubah tanpa bertanya):
 *   1. **semua peran** (guru, kepala sekolah/madrasah, admin operator, pengawas) — isinya menyesuaikan
 *      menu yang benar-benar ada pada peran itu;
 *   2. tampilannya **daftar menu yang bisa dibuka-tutup** (akordeon, memakai `<details>` bawaan
 *      peramban sehingga tetap bekerja tanpa JavaScript);
 *   3. **tanpa tombol cetak** — cukup dibaca di layar.
 *
 * ATURAN PENTING saat menambah/mengubah isi:
 *   - Petunjuk WAJIB menyesuaikan peran: pakai predikat yang SAMA dengan sidebar (`inc/layout.php`)
 *     supaya tidak pernah menjelaskan menu yang tidak ada di akun itu. Syarat yang tersedia:
 *     `boleh_master()`, `boleh_pengaturan()`, `boleh_tugas_pengguna()`, `boleh_lihat_cetak()`,
 *     `boleh_isi_administrasi()`, `boleh_ubah_tugas_menu()`, `boleh_rekap_pertemuan()`,
 *     `boleh_rekap_pelanggaran()`, `boleh_lihat_notulen()`, `tugas_menu_semua_aktif()`,
 *     `daftar_apps_tampil()`, `is_guru()`/`is_kepala()`/`is_operator()`/`is_pengawas()`/`is_super()`.
 *   - Nama resmi sekolah/madrasah dibungkus `tetap_kata()` bila ditulis sebagai teks.
 *   - Teks bebas ditulis apa adanya: mesin istilah aplikasi otomatis mengubah "Sekolah" menjadi
 *     "Madrasah" pada madrasah (dan "Sekolah/Madrasah" tetap utuh karena ada penjaga lookahead).
 */

/**
 * Ringkasan panduan untuk menu Tugas Tambahan yang isinya memakai modul DAFTAR APLIKASI
 * (mis. "Bendahara Bos" — lihat `daftar_apps_tugas_kunci()`). Dipisah agar daftar kode menunya tidak
 * ditulis ulang dan tetap sejalan dengan konfigurasi modulnya.
 *
 * @return array<string,string> kode menu → kalimat ringkas
 */
function petunjuk_ringkas_apps(): array
{
    $keluar = [];
    foreach (daftar_apps_konfig() as $k) {
        foreach ((array) ($k['tugas_kode'] ?? []) as $kode) {
            // Tanpa mb_strtolower: CLI sandbox tidak memuat mbstring (pelajaran `judul_nama()`).
            $keluar[(string) $kode] = 'Daftar aplikasi ' . (string) ($k['menu'] ?? 'aplikasi')
                . ' (nama aplikasi, link, dan keterangan) yang diisi pengelola aplikasi, beserta '
                . 'sekolah/madrasah yang boleh membukanya.';
        }
    }
    return $keluar;
}

/**
 * Satu baris panduan.
 *
 * @param string        $nama     nama menu (sama dengan tulisan di sidebar)
 * @param string        $ikon     nama ikon pada `ikon()`
 * @param string|null   $tautan   alamat menu (null = tidak ada halaman tersendiri)
 * @param string        $ringkas  satu-dua kalimat: menu ini untuk apa
 * @param string[]      $langkah  langkah pemakaian
 * @param string        $catatan  keterangan tambahan (boleh kosong)
 * @return array<string,mixed>
 */
function petunjuk_baris(string $nama, string $ikon, ?string $tautan, string $ringkas,
                        array $langkah = [], string $catatan = ''): array
{
    return ['nama' => $nama, 'ikon' => $ikon, 'tautan' => $tautan, 'ringkas' => $ringkas,
            'langkah' => $langkah, 'catatan' => $catatan];
}

/** Susun satu kelompok panduan (judul bagian + daftar menunya). */
function petunjuk_grup(string $judul, array $menu, string $keterangan = ''): array
{
    return $menu ? ['judul' => $judul, 'keterangan' => $keterangan, 'menu' => $menu] : [];
}

/**
 * Seluruh panduan yang berlaku untuk PENGGUNA YANG SEDANG MASUK.
 *
 * @return array<int,array<string,mixed>> daftar kelompok (kelompok kosong dibuang)
 */
function petunjuk_daftar(): array
{
    $adaSekolah = current_sekolah() !== null;
    $grup = [];
    $tambah = static function (array $g) use (&$grup): void {
        if ($g) {
            $grup[] = $g;
        }
    };

    // ============================================================ UTAMA
    if ($adaSekolah && in_array(peran(), ['kepala', 'operator', 'guru'], true)) {
        $tambah(petunjuk_grup('Utama', [
            petunjuk_baris('Dashboard', 'dashboard', 'index.php',
                is_guru()
                    ? 'Halaman pertama setelah masuk. Memuat kartu pintasan seluruh menu yang boleh Anda buka, '
                      . 'ringkasan nilai dan kehadiran Anda, serta salam pengembang aplikasi.'
                    : 'Ringkasan keadaan sekolah/madrasah: jumlah siswa & guru, grafik nilai per menu supervisi, '
                      . 'kehadiran, jurnal, dan daftar yang perlu ditindaklanjuti.',
                is_guru()
                    ? ['Klik salah satu kartu menu untuk langsung membuka menu itu.',
                       'Kartu "Donasi / Sedekah" berisi data rekening bila Anda ingin memberi dukungan.',
                       'Angka pada kartu dihitung dari data yang Anda isi sendiri (kelas & mata pelajaran yang diampu).']
                    : ['Periksa kartu ringkasan untuk mengetahui keadaan administrasi sekolah/madrasah.',
                       'Grafik & daftar "perlu ditindaklanjuti" dipakai untuk memantau kelengkapan data guru.'],
                'Isi dashboard otomatis mengikuti data yang sudah diisi — tidak ada yang perlu disetel di halaman ini.'),
        ]));
    }

    // ============================================================ LINTAS SEKOLAH (Super Admin)
    if (is_super()) {
        $tambah(petunjuk_grup('Lintas Sekolah/Madrasah', [
            petunjuk_baris('Ringkasan Semua', 'chart', 'index.php',
                'Ringkasan seluruh sekolah/madrasah yang terdaftar: jumlah sekolah aktif, guru, siswa, dan '
                . 'pengguna yang sedang online.',
                ['Buka halaman ini saat belum "masuk" ke sekolah mana pun.',
                 'Klik salah satu sekolah pada tabel untuk masuk ke konteks sekolah itu.'],
                'Selagi belum masuk konteks sekolah, halaman data sekolah akan meminta Anda memilih sekolah dulu.'),
            petunjuk_baris('Kelola Sekolah', 'building', 'sekolah.php',
                'Menambah sekolah/madrasah baru, mengubah profil & ambang predikat, mengatur akun adminnya, '
                . 'serta menonaktifkan atau menghapus sekolah.',
                ['Gunakan "Tambah Sekolah/Madrasah" untuk membuat sekolah baru beserta akun adminnya.',
                 'Tombol "Masuk" memindahkan Anda ke konteks sekolah tersebut.',
                 'Penonaktifan menyembunyikan sekolah tanpa menghapus datanya; penghapusan permanen wajib mengetik HAPUS.'],
                'Setiap kartu sekolah menampilkan kode aktivasi lembaganya dan tombol untuk mengaktifkan/membatasi.'),
            petunjuk_baris('Pendaftaran', 'star', 'pendaftaran.php',
                'Meninjau sekolah/madrasah yang mendaftar sendiri lewat halaman pendaftaran: menyetujui, '
                . 'menolak dengan alasan, meninjau ulang, atau menghapus.',
                ['Pendaftar baru disetujui otomatis dengan masa aktif ' . (int) PENDAFTARAN_HARI_AKTIF
                 . ' hari, jadi hanya perlu ditinjau bila ada masalah.',
                 'Tombol "Tolak" mewajibkan alasan; alasannya akan terlihat oleh pendaftar saat mencoba masuk.'],
                'Halaman ini hanya menampilkan rekapan akun tiap sekolah, bukan daftar nama penggunanya.'),
            petunjuk_baris('Akun Pengawas', 'eye', 'pengawas.php',
                'Membuat akun Pengawas Sekolah/Madrasah beserta sekolah/madrasah binaannya.',
                ['Tambah akun, lalu centang sekolah binaannya.',
                 'Akun pengawas bersifat lintas sekolah dan hanya dapat melihat serta mencetak.',
                 'Nama <b>Pengawas Bina</b> yang diisi pada halaman pendaftaran sekolah/madrasah masuk ke '
                 . 'daftar ini OTOMATIS sebagai akun NONAKTIF bertanda "Menunggu ditinjau" — tinjau dulu '
                 . '(tetapkan kata sandi baru), lalu aktifkan.'],
                'Pengawas tidak dapat mengubah data apa pun — semua tombol pengubahan disembunyikan untuknya. '
                . 'Satu sekolah/madrasah hanya dibina satu pengawas.'),
            petunjuk_baris('Kelola Tugas Tambahan', 'star', 'tugas_menu.php',
                'Mengaktifkan menu Tugas Tambahan, mengubah namanya, mengatur urutannya, dan menambah tugas '
                . 'tambahan baru.',
                ['Aktifkan menu yang diperlukan; menu nonaktif tidak tampil pada pengguna mana pun.',
                 'Ubah nama menu bila nama bakunya kurang sesuai — kode menu di belakangnya tidak berubah.',
                 'Tugas tambahan baru dibuat dengan nama bebas; kodenya dibuat otomatis.'],
                'Daftar ini juga menjadi sumber pilihan Tugas Tambahan pada Data Guru.'),
            petunjuk_baris('Pengumuman Sambutan', 'star', 'pengumuman.php',
                'Mengisi isi popup "Selamat datang" yang muncul sekali setiap pengguna masuk aplikasi.',
                ['Tulis judul dan isi pengumuman, lalu simpan.',
                 'Beberapa pengumuman dapat diaktifkan sekaligus dan diurutkan tampilannya.'],
                'Popup tidak muncul bila tidak ada pengumuman yang aktif; akun Super Admin sendiri tidak melihat popup.'),
            petunjuk_baris('Halaman Depan (Landing)', 'apps', 'landing.php',
                'Mengisi seluruh isi HALAMAN DEPAN aplikasi: beranda, slider beranda (judul, gambar, '
                . 'keterangan), fitur aplikasi, keunggulan, total pengguna, testimoni, tentang aplikasi, '
                . 'dan kontak.',
                ['Tab <b>Slider Beranda</b>: tambah slide berisi Judul Slider, foto/gambar '
                 . '(PNG/JPG/GIF/WEBP/SVG, maks 15 MB), dan keterangan; atur urutannya dengan ▲▼.',
                 'Tab <b>Fitur Aplikasi</b> &amp; <b>Keunggulan</b>: tambah/ubah kartu beserta ikon dan '
                 . 'keterangannya, atau sembunyikan kartu yang tidak dipakai.',
                 'Tab <b>Total Pengguna</b>: angkanya dihitung OTOMATIS dari data aplikasi — yang dapat '
                 . 'diubah hanya label tiap kartu dan apakah kartunya ditampilkan.',
                 'Tab <b>Testimoni</b>: nama, jabatan, isi, dan foto (opsional).',
                 'Tab <b>Kontak</b>: alamat, telepon/WhatsApp, email, jam layanan, dan kalimat ajakan.',
                 'Tab <b>Pesan Masuk</b>: pesan dari formulir kontak pengunjung pada halaman depan.'],
                'Seluruh isi berlaku untuk semua sekolah/madrasah (pengaturan tingkat aplikasi). '
                . 'Blok "Tentang Aplikasi" (dua foto 3x4 + nama + keterangan) diisi pada menu '
                . '<b>Tentang Aplikasi</b> supaya hanya ada satu sumber data. Panel masuk pengguna '
                . 'tetap dipertahankan pada halaman depan.'),
            petunjuk_baris('Tentang Aplikasi', 'card', 'tentang.php',
                'Mengisi kartu "Tentang Aplikasi" pada halaman masuk: dua tempat foto 3x4 dengan nama di '
                . 'bawah masing-masing foto, dan keterangan/penjelasan di sisi kanannya.',
                ['Isi nama yang tampil di bawah tiap foto, lalu pilih berkas fotonya (PNG/JPG/GIF/WEBP/SVG, maks 15 MB).',
                 'Tulis keterangan pada kotak yang tersedia — pisahkan dengan satu baris kosong bila ingin membuat paragraf baru.',
                 'Tekan Simpan; perubahan langsung tampil pada halaman masuk (lihat kartu Pratinjau).'],
                'Keterangan ini menggantikan deskripsi tentang aplikasi yang dahulu tertulis tetap pada '
                . 'halaman masuk. Tampil untuk seluruh sekolah/madrasah (pengaturan tingkat aplikasi).'),
            petunjuk_baris('Logo Aplikasi', 'star', 'logo.php',
                'Mengganti lambang aplikasi dengan gambar milik sendiri — dipakai pada halaman masuk, '
                . 'halaman pendaftaran, sidebar seluruh pengguna, dan ikon tab peramban (favicon).',
                ['Pilih berkas gambar (PNG/JPG/GIF/WEBP/SVG, maks 15 MB), lalu tekan "Unggah / Ganti Logo".',
                 'Gunakan gambar persegi (1:1), minimal 512 x 512 piksel, agar tidak terpotong saat ditampilkan kecil.',
                 'Tombol "Kembalikan Lambang Bawaan" memulihkan lambang bawaan aplikasi.'],
                'Berlaku untuk seluruh sekolah/madrasah (pengaturan tingkat aplikasi). Logo ini berbeda '
                . 'dari logo kop dokumen cetak yang diatur operator di menu Identitas &amp; Pengaturan.'),
            petunjuk_baris('Gabung Grup e-GURU', 'whatsapp', 'grup.php',
                'Mengatur kartu "Gabung Grup e-GURU" pada dashboard akun Kepala Sekolah/Madrasah, '
                . 'Admin Operator, dan Guru (sejajar dengan kartu Pengawas Bina).',
                ['Isi Judul Grup, Link Grup (diawali http:// atau https://), dan Keterangannya, lalu simpan.',
                 'Perubahan langsung terlihat pada dashboard — lihat kartu Pratinjau di halaman itu.',
                 'Tombol "Kembalikan Bawaan" memulihkan judul, link, dan keterangan bawaan aplikasi.'],
                'Berlaku untuk seluruh sekolah/madrasah (pengaturan tingkat aplikasi).'),
            petunjuk_baris('Biaya & WA Aktivasi', 'card', 'biaya_aktivasi.php',
                'Mengatur nominal biaya aktivasi, biaya langganan, dan nomor WA HD Admin yang dipakai '
                . 'seluruh sekolah/madrasah pada menu Status &amp; Aktivasi.',
                ['Isi Biaya Aktivasi Pertama (masa aktif 1 tahun) dan Biaya Langganan (perpanjangan tahun '
                 . 'berikutnya) — boleh ditulis dengan titik pemisah, mis. 1.000.000.',
                 'Isi Nomor WA HD Admin: tujuan tombol "Kirim Permohonan Kode Aktifasi" sekaligus '
                 . '"Kirim Bukti Transfer ke WA" pada halaman Status & Aktivasi.',
                 'Periksa kartu Pratinjau untuk memastikan kalimat & nominalnya sudah benar.'],
                'Perubahan berlaku untuk seluruh sekolah/madrasah (pengaturan tingkat aplikasi). Tombol '
                . '"Kembalikan Bawaan" memulihkan ketiga nilai bawaannya.'),
            petunjuk_baris('Jenis Perangkat', 'folder', 'perangkat_jenis.php',
                'Mengisi daftar NAMA DOKUMEN perangkat guru yang dapat dikirim dari akun guru — '
                . 'mis. Kalender Pendidikan, Analisis Pekan Efektif, Capaian Pembelajaran, Alur Tujuan '
                . 'Pembelajaran, dan RPP/Modul Ajar. Daftar ini dapat ditambah maupun dikurangi.',
                ['Isi Nama Dokumen pada formulir <b>Tambah Nama Dokumen</b> (biarkan centang "Langsung aktifkan" agar langsung tampil), lalu simpan.',
                 'Atur urutannya dengan tombol ▲▼, ubah namanya lewat <b>Ubah</b>, atau nonaktifkan sesuatu yang sedang tidak dipakai.',
                 'Gunakan <b>Hapus</b> untuk membuang Nama Dokumen yang tidak diperlukan — kiriman guru yang sudah masuk TIDAK ikut terhapus.'],
                'Daftar ini berlaku untuk SELURUH sekolah/madrasah (pengaturan tingkat aplikasi). '
                . 'Guru mengunggah perangkat lewat menu <b>Kirim Perangkat</b> pada akunnya (satu berkas '
                . 'per Nama Dokumen pada tiap mata pelajaran yang diampu), lalu kepala sekolah/madrasah '
                . 'meninjaunya pada menu <b>Perangkat Masuk</b>. Menu "Perangkat Guru" yang lama (halaman '
                . 'publik + kode download) sudah dihapus karena tidak dipakai lagi.'),
            petunjuk_baris('Akun Super Admin', 'key', 'pengguna.php',                'Mengelola seluruh akun aplikasi (Super Admin, kepala sekolah/madrasah, admin operator, guru, pengawas).',
                ['Pilih peran dan sekolah/madrasah saat menambah akun baru.',
                 'Gunakan "Tetapkan sandi baru" bila pengguna lupa sandinya.'],
                'Aplikasi selalu menyisakan minimal satu Super Admin aktif, dan akun sendiri tidak dapat dihapus. '
                . 'Akun SISWA tidak dikelola dari halaman ini: akunnya dibuat OTOMATIS saat siswa masuk di '
                . 'tab "Siswa" pada halaman depan dengan NISN + KATA SANDI = TANGGAL LAHIRNYA dalam 8 angka '
                . '(DDMMYYYY, mis. 1 Januari 2007 → 01012007); siswa hanya melihat &amp; mencetak datanya '
                . 'sendiri (lihat menu Log Aktivitas untuk riwayatnya).'),
            petunjuk_baris('Log Aktivitas', 'eye', 'log.php',
                'Riwayat masuk/keluar aplikasi dan daftar pengguna yang sedang online.',
                ['Bagian "Sedang Online" menampilkan akun yang aktif dalam 15 menit terakhir.',
                 'Gunakan penyaring untuk mencari nama, jenis aktivitas, atau rentang tanggal.',
                 'Riwayat gagal masuk ikut tercatat beserta alasannya.'],
                'Percobaan masuk yang gagal dibatasi 2000 baris terbaru agar tidak membengkak.'),
        ], 'Bagian ini hanya tampil pada akun Super Admin (pemilik aplikasi). Seluruh menu pengelolaan '
            . 'aplikasi (Pendaftaran sampai Jenis Perangkat) dikelompokkan pada SUB MENU '
            . '"Layanan" dan daftar menu Tugas Tambahan juga berupa SUB MENU — klik judulnya '
            . 'untuk membuka isinya (bekerja tanpa JavaScript).'));
    }

    // ============================================================ PENGAWAS
    if (is_pengawas()) {
        $menuBinaan = [];
        $menuBinaan[] = petunjuk_baris('Daftar Binaan', 'dashboard', 'index.php',
            'Halaman pertama akun pengawas: daftar sekolah/madrasah binaan beserta rekapan jumlah guru, '
            . 'siswa, dan baris nilai tiap binaan.',
            ['Klik "Lihat Data" untuk masuk melihat data satu binaan.',
             'Klik "Cetak" pada baris binaan untuk mencetak rekapan binaan itu.',
             'Tombol "Cetak Rekap Semua Binaan" mencetak rekapan seluruh binaan sekaligus.'],
            'Sekolah/madrasah nonaktif otomatis hilang dari daftar binaan.');
        if ($adaSekolah) {
            $menuBinaan[] = petunjuk_baris('Dashboard Binaan', 'chart', 'index.php',
                'Ringkasan keadaan binaan yang sedang dilihat: jumlah guru, siswa, baris nilai, kehadiran '
                . 'bulan berjalan, jurnal, dan pelanggaran siswa.',
                ['Bagian "Nilai Terisi per Mata Pelajaran" menunjukkan mata pelajaran yang belum diisi nilainya.',
                 'Tombol "Cetak Rekap" mencetak rekapan binaan ini; "Ganti Binaan" kembali ke daftar binaan.']);
        }
        $menuBinaan[] = petunjuk_baris('Daftar Hadir Meeting', 'calendar', 'meeting.php',
            'Membuat pertemuan/meeting (tanggal & judul), mengaktifkan daftar hadirnya untuk binaan '
            . 'yang dipilih (atau seluruh binaan), dan memantau tanda tangan yang masuk.',
            ['Isi <b>Judul Pertemuan/Meeting</b> dan <b>Tanggal Pertemuan</b>, lalu centang binaan yang '
             . 'daftar hadirnya diaktifkan — atau centang <b>Seluruh binaan</b>.',
             'Buka <b>Rincian</b> pada daftar meeting untuk melihat jumlah akun yang sudah menandatangani '
             . 'tiap binaan dan mencetak lembarnya (satu binaan atau seluruh binaan).',
             'Bubuhkan <b>tanda tangan Pemimpin Meeting</b> pada halaman Rincian (layar sentuh HP/tablet) — '
             . 'satu tanda tangan berlaku untuk lembar seluruh binaan pada pertemuan itu.',
             'Centang/lepas binaan pada kartu <b>Aktifkan / Tutup Binaan</b> untuk membuka atau menutup '
             . 'daftar hadir binaan tersebut.'],
            'Daftar hadir yang diaktifkan langsung muncul di akun kepala sekolah/madrasah, admin operator, '
            . 'dan guru binaan untuk ditandatangani secara online. Menutup sebuah binaan tidak menghapus '
            . 'tanda tangan yang sudah masuk.');
        $menuBinaan[] = petunjuk_baris('Cetak Rekap Binaan', 'print', 'cetak_rekap_binaan.php',
            'Lembar cetak rekapan binaan (A4 melintang) berisi jumlah kelas, guru, siswa, baris nilai, '
            . 'pertemuan hadir, catatan jurnal, dan pelanggaran.',
            ['Isi lembar mengikuti binaan yang sedang Anda lihat.',
             'Untuk satu sekolah/madrasah, lembar ini ditandatangani kepala sekolah/madrasah.']);
        $tambah(petunjuk_grup('Binaan', $menuBinaan,
            'Akun Pengawas Sekolah/Madrasah bersifat lintas sekolah dan hanya dapat MELIHAT serta MENCETAK.'));

        $tambah(petunjuk_grup('Data Binaan (hanya lihat)', array_filter([
            petunjuk_baris('Data Guru', 'users', 'guru.php',
                'Daftar guru pada binaan yang sedang dilihat beserta mata pelajaran dan kelas yang diampu.',
                ['Gunakan penyaring untuk mencari guru, mata pelajaran, atau kelas.',
                 'Tombol "Cetak Data Guru" mencetak daftar sesuai penyaring yang aktif.'],
                'Halaman ini juga menampilkan nama pengguna akun tiap guru, jadi tidak dibuka untuk akun guru.'),
            petunjuk_baris('Data Siswa', 'student', 'siswa.php',
                'Daftar siswa binaan beserta kelas, wali, dan statusnya (aktif/lulus).',
                ['Saring berdasarkan kelas atau status siswa bila datanya banyak.',
                 'Tombol "Cetak Data Siswa" dan "Cetak Kartu Pelajar" tersedia untuk pengawas.']),
            petunjuk_baris('Daftar Hadir Siswa', 'clipboard', 'absensi.php',
                'Rekap kehadiran siswa per pertemuan untuk tiap kelas dan mata pelajaran.',
                ['Pilih kelas dan mata pelajaran untuk melihat pertemuan yang sudah tercatat.',
                 'Tombol "Cetak Rekap" mencetak daftar hadir sesuai pilihan itu.']),
            petunjuk_baris('Penilaian Siswa', 'chart', 'nilai.php',
                'Daftar nilai Kurikulum Merdeka: Formatif, Sumatif Lingkup Materi, dan ASAS beserta nilai akhir '
                . 'dan predikatnya.',
                ['Pilih kelas dan mata pelajaran untuk melihat nilainya.',
                 'Predikat memakai ambang yang diatur guru pengampu mata pelajaran tersebut.']),
            petunjuk_baris('Jurnal Harian Guru', 'book', 'jurnal.php',
                'Catatan kegiatan harian tiap guru: tanggal, uraian kegiatan, kelas/tempat, mata pelajaran, '
                . 'materi, dan kehadiran siswa.',
                ['Saring berdasarkan bulan, nama guru, kelas, mata pelajaran, atau uraian kegiatan.',
                 'Tombol "Cetak Jurnal" mencetak sesuai penyaring yang aktif.']),
            petunjuk_baris('Pelanggaran Siswa', 'alert', 'kasus.php',
                'Catatan pelanggaran siswa yang diinput guru beserta tindakan penanganannya.',
                ['Saring berdasarkan kelas, jenis pelanggaran, mata pelajaran, atau kata kunci.',
                 'Tombol "Cetak Laporan" mencetak daftar pelanggaran sesuai penyaring.']),
            petunjuk_baris('Perangkat Guru', 'folder', 'perangkat_pengawas.php',
                'Perangkat pembelajaran guru setiap sekolah/madrasah binaan — ditampilkan per lembaga, '
                . 'berisi daftar guru, mata pelajarannya, dan daftar perangkatnya.',
                ['Pilih salah satu sekolah/madrasah binaan pada halaman pertama (tombol <b>Buka Perangkat</b>).',
                 'Setiap guru tampil sebagai satu kartu — klik judulnya untuk membuka/menutup daftar perangkatnya; perangkat dikelompokkan per <b>mata pelajaran</b>.',
                 'Klik <b>Unduh</b> untuk membuka berkas perangkat pada tab baru.',
                 'Isi <b>Catatan Pengawas</b> pada baris perangkat yang dituju lalu tekan <b>Simpan Catatan</b> — catatan itu terpisah dari status tinjauan kepala sekolah/madrasah dan dibaca guru &amp; kepala sekolah/madrasah.'],
                'Anda dapat memakai penyaring tahun ajaran, nama guru, mata pelajaran, nama dokumen, atau '
                . 'status tinjauan kepala sekolah. Mengosongkan isi catatan lalu menyimpannya akan menghapus '
                . 'catatan Anda. Peninjauan (Disetujui / Direvisi / Ditolak) tetap dilakukan kepala '
                . 'sekolah/madrasah pada akunnya.'),
        ]), 'Menu ini untuk memeriksa keadaan binaan — penginputan data dilakukan oleh guru dan admin sekolah/madrasah.'));

        $tugasBinaan = tugas_menu_semua_aktif();
        if ($tugasBinaan && $adaSekolah) {
            $isi = [petunjuk_baris('Laporan Tugas Tambahan', 'chart', 'tugas.php',
                'Halaman pemilih laporan Tugas Tambahan untuk binaan yang sedang dilihat.',
                ['Pilih salah satu menu yang diaktifkan untuk membuka laporannya.',
                 'Laporan hanya memuat data binaan itu, dan hanya bisa dilihat serta dicetak.'])];
            foreach ($tugasBinaan as $t) {
                $adaIsi = in_array((string) $t['kode'], ['sarpras', 'kesiswaan', 'osim', 'pramuka', 'pmr',
                    'kepala_perpus', 'kepala_lab', 'tata_usaha', 'kurikulum', 'wali_kelas'], true);
                $isi[] = petunjuk_baris((string) $t['nama'], 'star', 'tugas.php?kode=' . urlencode((string) $t['kode']),
                    $adaIsi
                        ? 'Laporan isi menu ' . (string) $t['nama'] . ' pada binaan yang sedang dilihat.'
                        : 'Menu ini belum berisi modul laporan (masih dalam pengembangan).',
                    $adaIsi ? ['Buka laporannya, lalu gunakan tombol cetak bila perlu lembar cetak.'] : []);
            }
            $tambah(petunjuk_grup('Tugas Tambahan (hanya lihat)', $isi,
                'Isi menu Tugas Tambahan diisi oleh guru yang ditugaskan di sekolah/madrasah masing-masing.'));
        }
    }

    // ============================================================ ADMINISTRASI (sekolah)
    if ($adaSekolah) {
        $adm = [];
        if (!is_guru()) {
            $adm[] = petunjuk_baris('Data Guru', 'users', 'guru.php',
                'Identitas guru beserta akun loginnya, mata pelajaran, kelas yang diampu, dan tugas tambahannya.',
                boleh_master()
                    ? ['Gunakan "Tambah Guru" untuk menambah data guru satu per satu, atau "Impor Excel/CSV" untuk banyak data sekaligus.',
                       'Saat menambah guru, centang "Buat akun login" bila guru itu perlu masuk aplikasi — nama penggunanya otomatis diambil dari kata awal nama guru dan sandi awalnya guru123.',
                       'Pada lembaga berjenjang SD/MI, centang "Guru Kelas" + satu kelas: guru itu otomatis menjadi wali kelas kelas tersebut (mata pelajaran tetap dicentang manual).',
                       'Tombol "Buat akun" pada daftar dipakai untuk membuat akun bagi guru yang belum punya.']
                    : ['Gunakan penyaring untuk mencari guru, mata pelajaran, atau kelas.',
                       'Tombol "Cetak Data Guru" mencetak daftar sesuai penyaring yang aktif.',
                       'Penyuntingan data guru dilakukan oleh Admin Operator pada akunnya.'],
                'Halaman ini memuat nama pengguna akun guru, jadi tidak dibuka untuk akun guru.');
        }
        $adm[] = petunjuk_baris('Data Siswa', 'student', 'siswa.php',
            'Daftar siswa beserta NIS/NISN, kelas, data orang tua/wali, status, dan pas fotonya.',
            boleh_master()
                ? ['Tambahkan siswa satu per satu, atau gunakan "Impor Excel/CSV" untuk banyak data sekaligus.',
                   'Tombol Cetak Data Siswa, Cetak Kartu Pelajar, dan Ekspor menyertakan penyaring yang sedang aktif.',
                   'Kartu Pelajar memuat BARCODE (kode QR) — bila dipindai kamera HP, terbuka halaman '
                   . 'berisi data sekolah/madrasah (identitas lembaga).',
                   'Menu "Kenaikan & Kelulusan" dipakai memindahkan siswa ke kelas berikutnya atau meluluskannya.']
                : ['Gunakan penyaring kelas/status untuk mempersempit daftar.',
                   'Tombol cetak menyertakan penyaring yang sedang aktif.']);
        $adm[] = petunjuk_baris('Daftar Hadir Siswa', 'clipboard', 'absensi.php',
            'Pencatatan kehadiran siswa pada setiap pertemuan pembelajaran.',
            [boleh_isi_administrasi()
                ? 'Pilih kelas dan mata pelajaran, lalu isi nomor pertemuan dan tanggalnya.'
                : 'Pilih kelas dan mata pelajaran untuk melihat pertemuan yang sudah tercatat.',
             'Tandai kehadiran tiap siswa dengan tombol H (hadir), S (sakit), I (izin), atau A (alpa).',
             'Tombol "Semua Hadir" menandai seluruh siswa hadir sekaligus agar pengisian lebih cepat.',
             'Setelah disimpan, daftar pertemuan tercatat dan rekapan kehadirannya langsung tampil.'],
            boleh_isi_administrasi()
                ? 'Kehadiran satu pertemuan boleh diisi ulang bila ada kekeliruan; jumlah pertemuan menyesuaikan keadaan sebenarnya.'
                : 'Akun Admin Operator hanya dapat melihat dan mencetak; pengisian dilakukan guru pengampu.');
        $adm[] = petunjuk_baris('Penilaian Siswa', 'chart', 'nilai.php',
            'Pengisian nilai Kurikulum Merdeka: Nilai Formatif, Sumatif Lingkup Materi, dan Sumatif Akhir '
            . 'Semester (ASAS) beserta nilai akhir dan predikatnya.',
            [boleh_isi_administrasi()
                ? 'Pilih kelas dan mata pelajaran yang Anda ampu.'
                : 'Pilih kelas dan mata pelajaran untuk melihat nilainya.',
             'Isi nilai Formatif (proses) dan Sumatif Lingkup Materi; keduanya boleh beberapa kolom.',
             'Isi ASAS pada kolomnya; nilai akhir dihitung dari rata-rata Sumatif Lingkup Materi berbobot '
                 . 'ditambah ASAS berbobot, dan predikatnya muncul otomatis.',
             'Tombol "Pengaturan Penilaian" dipakai menyetel sendiri jumlah kolom dan bobot untuk mata pelajaran itu.',
             'Gunakan tombol Cetak atau CSV bila nilainya perlu diserahkan dalam bentuk berkas.'],
            'Predikat memakai sebutan A (Amat Baik), B (Baik), C (Cukup), dan D (Kurang). Nilai Formatif '
            . 'tidak dijumlahkan ke nilai akhir — dipakai sebagai catatan proses belajar.');
        $adm[] = petunjuk_baris('Jurnal Harian Guru', 'book', 'jurnal.php',
            'Catatan kegiatan harian guru: uraian kegiatan, kelas/tempat, materi, jam ke-, dan kehadiran siswa.',
            [boleh_isi_administrasi()
                ? 'Isi tanggal & uraian kegiatan (pilihan "Mengajar" — kegiatan lain boleh diketik manual).'
                : 'Gunakan penyaring bulan, nama guru, kelas, mata pelajaran, atau uraian kegiatan.',
             'Kelas/Tempat: pilih kelas ATAU isi tempat kegiatan (mis. Ruang Guru, Lab IPA) — cukup salah satu.',
             'Mata pelajaran & materi boleh dikosongkan bila kegiatannya selain mengajar.',
             'Isi kolom kehadiran S (sakit), I (izin), A (alpa), B (bolos), dan T (terlambat).',
             'Tombol "Cetak Jurnal" menghasilkan lembar cetak sesuai penyaring yang aktif.'],
            boleh_isi_administrasi()
                ? 'Jurnal yang sudah tersimpan dapat diubah atau dihapus melalui tombol pada barisnya.'
                : 'Akun Admin Operator hanya dapat melihat dan mencetak jurnal harian.');
        $adm[] = petunjuk_baris('Pelanggaran Siswa', 'alert', 'kasus.php',
            'Pencatatan pelanggaran siswa beserta tindakan penanganannya, agar dapat dipantau per kelas '
            . 'maupun per mata pelajaran.',
            [boleh_isi_administrasi()
                ? 'Isi tanggal, siswa, jenis dan uraian pelanggaran, tindakan yang dilakukan, serta statusnya.'
                : 'Gunakan penyaring kelas, jenis pelanggaran, mata pelajaran, atau kata kunci.',
             'Kolom Mata Pelajaran dipakai untuk mengetahui pelanggaran pada tiap mata pelajaran.',
             'Tombol "Cetak Laporan" menghasilkan lembar cetak sesuai penyaring.'],
            is_guru() ? 'Akun guru hanya menampilkan catatan pelanggaran yang dibuatnya sendiri.'
                      : 'Catatan pelanggaran terhubung ke siswa, sehingga rekapannya bisa dilihat pada menu Rekap Pelanggaran Siswa.');
        if (is_guru()) {
            $adm[] = petunjuk_baris('Dokumen Saya', 'doc', 'dokumen_saya.php',
                'Kotak masuk dokumen yang dikirim sekolah/madrasah kepada guru, misalnya dari menu Kurikulum.',
                ['Klik "Buka / Unduh" pada dokumen untuk membukanya pada tab baru.',
                 'Setiap dokumen disertai judul dan catatan dari pengirimnya.']);
            $adm[] = petunjuk_baris('Kirim Perangkat', 'upload', 'perangkat_saya.php',
                'Mengirim perangkat pembelajaran sendiri ke kepala sekolah/madrasah: Kalender Pendidikan, '
                . 'Analisis Pekan Efektif, Capaian Pembelajaran, Alur Tujuan Pembelajaran, RPP/Modul Ajar '
                . '(nama dokumennya ditetapkan pengelola aplikasi).',
                ['Pilih <b>Tahun Ajaran</b>, <b>Mata Pelajaran</b> yang Anda ampu, dan <b>Nama Dokumen</b>, lalu pilih berkasnya dan tekan Kirim.',
                 'Satu sel = satu berkas (per nama dokumen pada tiap mata pelajaran). Mengunggah ulang pada sel yang sama menggantikan berkas lama.',
                 'Tabel "Kelengkapan Perangkat" memakai centang hijau (sudah dikirim) &amp; silang merah (belum) — klik Unggah pada sel yang masih merah.',
                 'Hasil tinjauan kepala sekolah (Disetujui / Direvisi / Ditolak beserta keterangannya) tampil pada kolom Status Tinjauan dan pada dashboard Anda.'],
                'Berkas yang dinyatakan <b>Direvisi</b> atau <b>Ditolak</b> sebaiknya diperbaiki lalu diunggah '
                . 'ulang pada sel yang sama. Kiriman juga dapat dihapus lewat tombol Hapus pada barisnya. '
                . 'Pengawas sekolah/madrasah binaan juga dapat memberi <b>catatan pengawas</b> pada tiap '
                . 'perangkat; catatan itu tampil di kolom Status Tinjauan (terpisah dari hasil tinjauan '
                . 'kepala sekolah/madrasah).');
        }
        if (in_array(peran(), ['kepala', 'super'], true) && $adaSekolah) {
            $adm[] = petunjuk_baris('Perangkat Masuk', 'folder', 'perangkat_masuk.php',
                'Perangkat pembelajaran yang dikirim guru sekolah/madrasah ini, lengkap dengan berkasnya '
                . 'dan keadaan tinjuannya.',
                ['Gunakan penyaring tahun ajaran, nama guru, mata pelajaran, nama dokumen, atau status tinjauan.',
                 'Klik <b>Unduh</b> untuk membuka berkas perangkat guru itu pada tab baru.',
                 'Tekan <b>Tinjau</b> pada baris yang dituju, pilih <b>Dokumen Disetujui / Direvisi / Ditolak</b>, tulis keterangannya, lalu Simpan.'],
                'Keterangan <b>wajib</b> diisi untuk <b>Dokumen Direvisi</b> dan <b>Dokumen Ditolak</b> — itulah '
                . 'alasan yang dibaca guru. Hasil tinjauan langsung tampil pada akun guru yang mengirim '
                . '(menu <b>Kirim Perangkat</b> dan dashboard). Memilih "Belum Ditinjau" membatalkan tinjauan. '
                . 'Pengawas sekolah/madrasah binaan juga dapat menulis <b>catatan pengawas</b> pada tiap '
                . 'perangkat; catatan itu tampil di kolom Status Tinjauan (terpisah dari hasil tinjauan Anda).');
        }
        $adm[] = petunjuk_baris('Daftar Hadir Meeting', 'calendar', 'meeting_hadir.php',
            'Daftar hadir pertemuan/meeting yang diaktifkan pengawas bina untuk sekolah/madrasah ini.',
            ['Buka salah satu meeting, lalu tekan <b>Tanda Tangan</b> pada baris nama Anda.',
             'Tanda tangan dibuat langsung pada layar sentuh (jari atau stylus) — sama seperti tanda '
             . 'tangan Notulen Rapat.',
             'Tekan <b>Ulangi</b> untuk mengganti tanda tangan Anda, atau <b>Hapus Tersimpan</b> pada dialog '
             . 'untuk menghapusnya.',
             'Tombol <b>Cetak Daftar Hadir</b> mencetak lembar A4 berisi tanda tangan yang sudah masuk '
             . 'beserta tanda tangan Pemimpin Meeting.'],
            'Lencana angka pada menu menunjukkan pertemuan yang belum Anda tandatangani. Halaman ini hanya '
            . 'memuat data sekolah/madrasah Anda sendiri.');
        $adm[] = petunjuk_baris('Ujian Online', 'clipboard', 'ujian_online.php',
            'Daftar tautan aplikasi ujian online yang disiapkan pengelola aplikasi (mis. aplikasi CBT).',
            ['Klik "Buka Aplikasi" untuk membuka aplikasinya pada tab baru.',
             'Keterangan pada tiap kartu menjelaskan aplikasi ujiannya.']);
        $adm[] = petunjuk_baris('Smart Teacher', 'apps', 'smart_teacher.php',
            'Daftar aplikasi pembelajaran pilihan yang disiapkan pengelola aplikasi.',
            ['Klik "Buka Aplikasi" untuk membuka aplikasinya pada tab baru.',
             'Keterangan pada tiap kartu menjelaskan isi aplikasinya.']);
        $adm[] = petunjuk_baris('Studio Foto Pintar', 'camera', 'studio_foto.php',
            'Daftar aplikasi studio foto yang disiapkan pengelola aplikasi.',
            ['Klik "Buka Aplikasi" untuk membuka aplikasinya pada tab baru.'],
            'Selama tautan aplikasinya belum diisi pengelola aplikasi, menu ini menampilkan keterangan '
            . '<b>Aplikasi Sementara Dikembangkan</b>.');
        if (daftar_apps_tampil('aplikasi_sagu')) {
            $adm[] = petunjuk_baris('Aplikasi Sagu', 'star', 'aplikasi_sagu.php',
                'Daftar aplikasi tambahan yang disiapkan pengelola aplikasi untuk sekolah/madrasah ini.',
                ['Klik "Buka Aplikasi" untuk membukanya pada tab baru.'],
                'Menu ini hanya tampil pada sekolah/madrasah yang diaktifkan pengelola aplikasi.');
        }
        $adm[] = petunjuk_baris('Hasil Supervisi', 'chart', 'hasil_supervisi.php',
            'Daftar aplikasi atau tautan hasil supervisi yang disiapkan pengelola aplikasi.',
            ['Klik "Buka Aplikasi" untuk membukanya pada tab baru.']);
        $tambah(petunjuk_grup('Administrasi', $adm,
            'Menu harian administrasi pembelajaran. Tombol yang muncul menyesuaikan peran Anda.'));
    }

    // ============================================================ TUGAS TAMBAHAN
    $tugasSaya = tugas_menu_semua_aktif();
    if ($tugasSaya && $adaSekolah) {
        $isi = [];
        $bolehUbah = boleh_ubah_tugas_menu();
        // Kode menu yang SUDAH punya modul sendiri. Menu daftar aplikasi (mis. "Bendahara Bos")
        // kodenya diambil dari modulnya supaya tidak perlu didaftarkan dua kali.
        $adaModul = array_merge(['sarpras', 'kesiswaan', 'osim', 'pramuka', 'pmr', 'kepala_perpus', 'kepala_lab',
                     'tata_usaha', 'kurikulum', 'wali_kelas'], daftar_apps_kode_tugas());
        foreach ($tugasSaya as $t) {
            $kode = (string) $t['kode'];
            if (!in_array($kode, $adaModul, true)) {
                $isi[] = petunjuk_baris((string) $t['nama'], 'star', 'tugas.php?kode=' . urlencode($kode),
                    'Menu ini belum berisi modul (masih dalam pengembangan).',
                    ['Menu akan berisi fitur setelah pengelola aplikasi menyiapkannya.']);
                continue;
            }
            $ringkas = [
                'sarpras' => 'Data barang sarana & prasarana: nama barang, spesifikasi, lokasi, kondisi, jumlah, '
                    . 'sumber dana, jenis, dan keterangannya.',
                'kesiswaan' => 'Laporan program kerja kesiswaan (jenis & bentuk kegiatan, tujuan, target, tahun '
                    . 'ajaran, waktu pelaksanaan bulan Juli–Juni, sumber dana, keterangan). Tab '
                    . '"Rekap Pelanggaran" memuat seluruh catatan pelanggaran dari setiap guru mata '
                    . 'pelajaran beserta tindak lanjutnya, lengkap dengan lembar cetaknya.',
                'osim' => 'Susunan organisasi OSIM/OSIS beserta bagannya dan laporan program kerjanya.',
                'pramuka' => 'Susunan organisasi Pramuka beserta bagannya dan laporan program kerjanya.',
                'pmr' => 'Susunan organisasi PMR beserta bagannya dan laporan program kerjanya.',
                'kepala_perpus' => 'Laporan perpustakaan: anggota, data buku, peminjaman, pengembalian, dan '
                    . 'pengunjung, diimpor dari hasil unduhan aplikasi e-PERPUS.',
                'kepala_lab' => 'Daftar inventaris barang laboratorium dan laporan program kerja lab.',
                'tata_usaha' => 'Daftar inventaris barang laboratorium komputer dan laporan program kerja unitnya.',
                'kurikulum' => 'Mengirim dokumen kepada guru sekolah/madrasah ini beserta judul dan catatannya.',
                'wali_kelas' => 'Lima laporan kelas perwalian: struktur organisasi kelas, program kerja wali '
                    . 'kelas, absensi harian kelas, jadwal piket, serta REKAP NILAI seluruh mata pelajaran '
                    . '(leger dengan predikat & peringkat).',
            ];
            // Menu Tugas Tambahan yang isinya daftar aplikasi (mis. "Bendahara Bos") —
            // kode & ringkasannya diambil dari konfigurasi modulnya agar tidak ditulis dua kali.
            $ringkas += petunjuk_ringkas_apps();
            $ringkas = $ringkas[$kode] ?? 'Isi menu Tugas Tambahan.';
            $langkah = $bolehUbah
                ? ['Isi atau ubah datanya melalui formulir yang tersedia.',
                   'Gunakan tombol Impor untuk memasukkan banyak data dari berkas Excel/CSV bila perlu.',
                   'Gunakan tombol Cetak untuk menghasilkan lembar cetak, dan Ekspor untuk mengunduh datanya.']
                : ['Isi menu ini hanya dapat dilihat; gunakan penyaring untuk menelusuri datanya.',
                   'Tombol Cetak dan Ekspor tetap tersedia bila datanya perlu dijadikan berkas.'];
            $catatan = $bolehUbah
                ? 'Data menu ini milik sekolah/madrasah Anda sendiri; sekolah lain tidak melihatnya.'
                : 'Pengubahan data dilakukan guru yang ditugaskan pada menu ini (dan Super Admin dalam konteks sekolah).';
            $isi[] = petunjuk_baris((string) $t['nama'], 'star', 'tugas.php?kode=' . urlencode($kode),
                $ringkas, $langkah, $catatan);
        }
        $tambah(petunjuk_grup('Tugas Tambahan', $isi,
            'Menu ini tampil sesuai penugasan yang diberikan Admin Operator/Kepala Sekolah/Madrasah kepada Anda.'));
    }

    // ============================================================ DATA MASTER
    if ($adaSekolah && boleh_master()) {
        $tambah(petunjuk_grup('Data Master', [
            petunjuk_baris('Kelas', 'school', 'kelas.php',
                'Daftar kelas/kelompok belajar beserta tingkat, wali kelas, dan jumlah siswanya.',
                ['Tambahkan kelas yang diperlukan, lalu tetapkan wali kelasnya.',
                 'Urutan kelas dapat diatur dengan tombol naik/turun.',
                 'Hapus kelas hanya bila belum ada siswa dan belum dipakai pada data lain.'],
                'Wali kelas yang ditetapkan di sini otomatis boleh membuka menu Wali Kelas.'),
            petunjuk_baris('Mata Pelajaran', 'folder', 'mapel.php',
                'Daftar mata pelajaran sekolah/madrasah ini; setiap sekolah/madrasah memiliki daftarnya sendiri.',
                ['Tambahkan mata pelajaran beserta kelompoknya bila perlu.',
                 'Pemetaan guru ke mata pelajaran dilakukan pada Data Guru.']),
            petunjuk_baris('Tahun Ajaran & Semester', 'calendar', 'tahun_ajaran.php',
                'Periode akademik yang dipakai seluruh pencatatan: tahun ajaran dan semester beserta '
                . 'semester yang aktif.',
                ['Tambahkan tahun ajaran baru; semester Ganjil dan Genap dibuat otomatis.',
                 'Tetapkan semester yang aktif agar absensi, nilai, dan jurnal tercatat pada periode yang benar.',
                 'Tahun ajaran yang sudah dipakai transaksi tidak dapat dihapus.'],
                'Periode aktif tampil pada bilah atas aplikasi dan pada lembar cetak.'),
            petunjuk_baris('Impor Guru', 'upload', 'guru_import.php',
                'Menambahkan banyak data guru sekaligus dari berkas Excel/CSV.',
                ['Unduh contoh format, isi datanya, lalu unggah kembali.',
                 'Kolom Tugas Tambahan dicocokkan dengan daftar tugas tambahan yang sudah diaktifkan.',
                 'Nama pengguna akun guru diambil dari kata awal namanya dan sandi awal guru123.']),
            petunjuk_baris('Impor Siswa', 'upload', 'siswa_import.php',
                'Menambahkan banyak data siswa sekaligus dari berkas Excel/CSV.',
                ['Unduh contoh format, isi datanya, lalu unggah kembali.',
                 'Kolom yang tersedia: NIS, NISN, nama lengkap, jenis kelamin, tempat & tanggal lahir, '
                     . 'nomor HP, nama ayah, nama ibu, wali, serta kelas.']),
            petunjuk_baris('Kenaikan & Kelulusan', 'switch', 'kenaikan.php',
                'Memindahkan siswa ke kelas berikutnya atau meluluskannya di akhir tahun ajaran.',
                ['Pilih kelas asal, centang siswa yang naik, lalu pilih kelas tujuannya.',
                 'Untuk siswa akhir, gunakan tombol Luluskan — riwayat nilainya tetap tersimpan.',
                 'Setiap proses tercatat dan dapat dibatalkan bila salah.'],
                'Siswa yang tidak dicentang tidak diubah (misalnya tinggal kelas).'),
        ], 'Data induk sekolah/madrasah: kelas, mata pelajaran, periode akademik, serta impor dan proses '
           . 'kenaikan siswa.'));
    }

    // ============================================================ PENGATURAN
    if ($adaSekolah && boleh_pengaturan()) {
        $isi = [petunjuk_baris('Identitas & Pengaturan', 'slider', 'pengaturan.php',
            'Identitas sekolah/madrasah (nama, NPSN, alamat, telepon, email, situs, kepala sekolah dan NIP-nya, '
            . 'logo) serta aturan penilaian.',
            ['Perbarui identitasnya bila ada perubahan; data ini dipakai pada seluruh lembar cetak.',
               'Tentukan jenis lembaga (Sekolah Umum atau Madrasah) lalu simpan dengan tombolnya sendiri '
                   . 'agar istilah pada aplikasi ikut menyesuaikan.',
               'Pilih juga JENJANG lembaga (SD/SMP/SMA/SMK untuk Sekolah Umum, MI/MTs/MA/MAK untuk '
                   . 'Madrasah): jenjang menjadi acuan tingkat kelas, dan jenjang SD/MI memakai konsep '
                   . 'guru kelas (satu guru, banyak mata pelajaran, otomatis wali kelas).',
               'Atur jumlah kolom Formatif/Sumatif, bobot ASAS, dan ambang predikat sebagai pengaturan '
                   . 'bawaan sekolah/madrasah.'],
            'Guru dapat menyetel sendiri kolom dan ambang predikat untuk mata pelajaran yang diampunya '
            . 'melalui tombol "Pengaturan Penilaian" pada halaman Penilaian Siswa.')];
        if (boleh_tugas_pengguna()) {
            $isi[] = petunjuk_baris('Pengaturan Tugas Tambahan', 'users', 'tugas_pengguna.php',
                'Menentukan pengguna mana yang dapat membuka isi tiap menu Tugas Tambahan.',
                ['Pilih pengguna, lalu centang menu Tugas Tambahan yang boleh dibukanya.',
                 'Untuk akun guru, tetapkan juga "Kelas Perwalian" bila menunya Wali Kelas.',
                 'Tombol "Bersihkan" mencabut seluruh penugasan pengguna itu.'],
                'Akun Kepala Sekolah/Madrasah dan Admin Operator melihat seluruh menu yang diaktifkan; '
                . 'akun guru hanya melihat menu yang ditugaskan kepadanya.');
        }
        // AKUN OPERATOR (permintaan pemilik v1.54.0): menu ini DIPINDAH dari kelompok "Kelola Akun"
        // ke kelompok "Pengaturan" pada sidebar — urutannya: Identitas & Pengaturan, Pengaturan Tugas
        // Tambahan, Akun Operator.
        if (is_kepala()) {
            $isi[] = petunjuk_baris('Akun Operator', 'key', 'akun_operator.php',
                'Mengelola akun Admin Operator sekolah/madrasah Anda sendiri.',
                ['Tambahkan akun operator baru beserta namanya.',
                 'Gunakan "Tetapkan sandi baru" bila operator lupa sandinya; sandi barunya ditampilkan sekali.',
                 'Akun operator dapat dinonaktifkan atau dihapus dari halaman ini.']);
        }
        $tambah(petunjuk_grup('Pengaturan', $isi));
    }

    // ============================================================ LAPORAN
    $isiLap = [];
    if (boleh_rekap_pertemuan()) {
        $isiLap[] = petunjuk_baris('Rekap Jumlah Pertemuan Guru', 'chart', 'laporan_pertemuan.php',
            'Rekapan jumlah tatap muka setiap guru pada tiap kelas dan mata pelajaran, dihitung dari jurnal '
            . 'harian yang diisi guru.',
            ['Pilih tahun ajaran dan semester (atau "Semua periode") yang ingin direkap.',
             'Gunakan penyaring kelas, nama guru, atau mata pelajaran bila perlu.',
             'Klik "Lihat Jurnal" pada satu baris untuk membuka jurnal guru tersebut sesuai kelas dan '
                 . 'mata pelajaran baris itu.',
             'Tombol "Cetak Rekap" menghasilkan lembar cetak A4 sesuai penyaring yang aktif.'],
            'Satu catatan jurnal dihitung satu tatap muka — jadi rekapannya langsung mengikuti '
            . 'kelengkapan pengisian jurnal guru.');
    }
    if (boleh_rekap_pelanggaran()) {
        $isiLap[] = petunjuk_baris('Rekap Pelanggaran Siswa', 'alert', 'laporan_pelanggaran.php',
            'Daftar 20 siswa dengan pelanggaran terbanyak beserta rekapan jumlah pelanggaran yang diinput '
            . 'setiap guru mata pelajaran.',
            ['Gunakan penyaring kelas bila ingin melihat satu kelas saja.',
             'Klik "Lihat Pelanggaran" pada baris siswa untuk membuka daftar pelanggaran siswa tersebut.',
             'Tombol "Cetak Rekap" menghasilkan lembar cetak untuk kedua tabel sekaligus.'],
            'Bagian rekap per guru mata pelajaran berguna untuk mengetahui dari mata pelajaran mana '
            . 'pelanggaran banyak tercatat.');
    }
    // NOTULEN RAPAT (permintaan pemilik v1.54.0): menu ini DIPINDAH dari kelompok "Rapat" ke
    // kelompok "Laporan" pada sidebar — urutannya: Rekap Jumlah Pertemuan, Rekap Pelanggaran Siswa,
    // Notulen Rapat.
    if ($adaSekolah && boleh_lihat_notulen()) {
        $isiLap[] = petunjuk_baris('Notulen Rapat', 'doc', 'notulen.php',
                'Notulen rapat sekolah/madrasah: berita acara, isi rapat, daftar hadir guru, dan foto '
                . 'kegiatan rapat.',
                [boleh_ubah_notulen()
                    ? 'Klik "Buat Notulen" untuk mengisi hasil rapat, lalu pilih guru yang hadir satu per satu.'
                    : 'Buka notulen yang sudah ada untuk melihat atau mencetaknya.',
                 'Setiap notulen dapat dicetak pada empat bagian yang berbeda: berita acara, isi rapat, '
                     . 'daftar hadir, dan foto dokumentasi.',
                 'Tanda tangan dapat dibubuhkan LANGSUNG PADA LAYAR SENTUH (HP/tablet): buka lembar '
                     . 'daftar hadir lalu tekan tombol "Tanda Tangan" pada nama peserta, Kepala '
                     . 'Sekolah/Madrasah, Pimpinan Rapat, atau Notulis — coretkan dengan jari lalu Simpan.'],
                boleh_ubah_notulen()
                ? 'Foto bukti kegiatan dapat dilampirkan maksimal dua foto per notulen.'
                : 'Akun Kepala Sekolah/Madrasah hanya dapat melihat dan mencetak notulen rapat.');
    }
    $tambah(petunjuk_grup('Laporan', $isiLap,
        'Laporan lintas kelas yang dipakai untuk memantau kelengkapan administrasi guru.'));

    // ============================================================ KELOLA AKUN
    $isiAkun = [];
    if (is_super() && $adaSekolah) {
        $isiAkun[] = petunjuk_baris('Pengguna & Akun', 'key', 'pengguna.php',
            'Mengelola akun pengguna sekolah/madrasah yang sedang dikelola beserta perannya.',
            ['Tambahkan akun kepala sekolah/madrasah, operator, atau guru sesuai kebutuhan.',
             'Daftar akun guru dapat dibuat sekaligus melalui tombolnya; sandinya ditampilkan sekali.']);
    }
    $tambah(petunjuk_grup('Kelola Akun', $isiAkun));

    // ============================================================ AKUN
    $isiAkunSaya = [];
    if ($adaSekolah && !is_guru() && !is_pengawas()) {
        $isiAkunSaya[] = petunjuk_baris('Status & Aktivasi', 'key', 'aktivasi.php',
            'Keadaan aktivasi sekolah/madrasah, biaya yang berlaku, dan formulir untuk memasukkan kode '
            . 'aktivasi pertama maupun kode aktivasi perpanjangan.',
            ['Periksa keterangan statusnya: aplikasi aktif sepenuhnya, masih dibatasi, atau masa aktif habis.',
             boleh_aktivasi()
                 ? 'Masukkan kode aktivasi (pertama atau perpanjangan) pada formulirnya, lalu simpan — '
                     . 'seluruh fitur langsung terbuka.'
                 : 'Kode aktivasi dimasukkan oleh Kepala Sekolah/Madrasah atau Admin Operator.',
             'Kode perpanjangan boleh dimasukkan selagi masa aktif masih berjalan; masa aktifnya '
                 . 'ditambahkan dari tanggal berakhir yang sedang berlaku.'],
            'Setiap kode hanya dapat dipakai SATU KALI: setelah dipakai, kode yang tampil di akun Super '
            . 'Admin berganti otomatis dan kode berikutnya diminta kembali kepada HD Admin. '
            . 'Selama masih dibatasi, aplikasi menampilkan banner dan membatasi jumlah data yang dapat '
            . 'dimasukkan (satu data guru, satu penilaian tiap menu, dan satu baris jadwal).');
    }
    // OBROLAN/CHAT (v1.51.0) — tampil untuk SEMUA PERAN (kotak mengambang di pojok kanan bawah,
    // bukan menu sidebar). Dijelaskan di grup "Akun" karena letaknya menempel pada setiap halaman.
    $isiAkunSaya[] = petunjuk_baris('Obrolan (kotak mengambang)', 'chat', null,
        'Berkomunikasi dengan sesama pengguna aplikasi yang sedang online. Berupa kotak mengambang '
        . 'di pojok kanan bawah yang tampil di semua halaman.',
        ['Klik tombol bulat bertanda obrolan di pojok kanan bawah untuk membuka/menutup panelnya.',
         'Tulis pesan lalu tekan Enter (Shift+Enter untuk menambah baris baru) atau klik Kirim.',
         'Pesan baru dari pengguna lain muncul otomatis setiap beberapa detik — tidak perlu memuat ulang.',
         'Lencana merah pada tombol menunjukkan jumlah pesan yang belum Anda buka.'],
        'Satu ruang bersama untuk seluruh pengguna aplikasi (lintas sekolah/madrasah, termasuk '
        . 'Super Admin) dan dapat dipakai semua peran — termasuk pengawas.');
    $isiAkunSaya[] = petunjuk_baris('Akun Saya', 'user', 'akun.php',
        'Profil Anda sendiri: foto profil, nama, dan penggantian kata sandi.',
        ['Unggah foto profil dengan tombol yang tersedia (format gambar, maksimal 15 MB).',
         'Untuk mengganti sandi, isi sandi lama, sandi baru, dan ulangi sandi barunya.',
         'Riwayat nilai dan kehadiran yang terkait akun ini tampil ringkas di halaman yang sama.'],
        'Jaga kerahasiaan sandi Anda; seluruh catatan yang dibuat tercatat atas nama akun ini.');
    $tambah(petunjuk_grup('Akun', $isiAkunSaya,
        'Pengaturan akun pribadi. Menu Petunjuk Penggunaan berada tepat di bawah Akun Saya.'));

    return array_values(array_filter($grup));
}

/** Jumlah seluruh panduan (dipakai halaman & pengujian). */
function petunjuk_jumlah(): int
{
    $n = 0;
    foreach (petunjuk_daftar() as $g) {
        $n += count($g['menu']);
    }
    return $n;
}
