<?php
/**
 * MODUL BERSAMA: MENU DAFTAR APLIKASI (halaman "nama + link + keterangan").
 *
 * Dipakai oleh beberapa menu yang isinya sama bentuknya dan HANYA diisi Super Admin:
 *   - `smart_teacher.php`   → menu "Smart Teacher"
 *   - `aplikasi_sagu.php`   → menu "Aplikasi Sagu"
 *   - `hasil_supervisi.php` → menu "Hasil Supervisi"
 *   - `ujian_online.php`    → menu "Ujian Online"
 *   - `tugas.php?kode=…`    → "Bendahara Bos" & "Bendahara Madrasah" (menu Tugas Tambahan)
 *
 * ⚠️ Menu **"Studio Foto Pintar"** (`studio_foto.php`) DIHAPUS pada v1.75.0 atas permintaan pemilik
 * (*"Hapus menu yang saya tandai warna merah"*). Tabel `studio_foto` sengaja TIDAK di-drop supaya
 * isian pemilik tidak hilang — tetapi menu, halaman, dan entri konfigurasinya sudah tidak ada.
 * Aplikasi studio foto tetap tersedia sebagai salah satu KARTU di dalam menu Smart Teacher.
 *
 * KENAPA MODUL BERSAMA (jangan disalin menjadi halaman terpisah):
 * dahulu setiap menu dibuat sebagai halaman tersendiri sehingga logika yang sama (validasi tautan,
 * pengurutan, sembunyikan, hapus, render kartu) harus ditulis berulang dan mudah berbeda satu sama
 * lain. Sekarang semuanya ada di sini; halaman menu cukup memanggil `daftar_apps_halaman('<kunci>')`.
 * Menambah menu baru = tambahkan satu entri pada `daftar_apps_konfig()` + satu berkas halaman tipis.
 *
 * ATURAN DATA (permintaan pemilik, berlaku untuk SEMUA menu di modul ini):
 *  - Daftarnya **GLOBAL** — tabelnya TIDAK punya kolom `sekolah_id`, jadi seluruh sekolah/madrasah
 *    melihat daftar yang sama (jangan menambahkan filter per sekolah).
 *    **KECUALI penampilan MENUNYA**: sebuah menu boleh dibatasi hanya untuk sekolah tertentu lewat
 *    `'per_sekolah' => true` pada konfigurasi + tabel `menu_sekolah` (diatur Super Admin di halaman
 *    menu itu sendiri). Permintaan pemilik: "Khusus menu aplikasi sagu hanya muncul di MA. Sirojuth
 *    Tholibiin Mootilango dan MTsS. Sirojuth Tholibiin Sidomukti Mootilango. Selain itu tidak muncul,
 *    bisa juga diseting melalui akun super admin." → menu yang dibatasi dipakai lewat
 *    **`daftar_apps_tampil()`** (satu-satunya penentu: sidebar, pintasan dashboard, dan akses halaman
 *    memakai fungsi yang sama supaya tidak ada menu terlihat yang tidak bisa dibuka).
 *  - Isi menu (nama aplikasi, link, keterangan) **diisi Super Admin**; peran lain hanya melihat
 *    kartu aplikasi yang AKTIF dan membuka tautannya (POST apa pun dari peran lain ditolak 403).
 */
declare(strict_types=1);

/**
 * Konfigurasi setiap menu daftar aplikasi.
 *
 * @return array<string, array<string, string>>|null array seluruh konfigurasi bila $menu null,
 *         atau satu konfigurasi; null bila kuncinya tidak dikenal.
 */
function daftar_apps_konfig(?string $menu = null): ?array
{
    $semua = [
        'smart_teacher' => [
            'tabel'      => 'smart_teacher',
            'menu'       => 'Smart Teacher',
            'judul'      => 'Daftar Aplikasi Smart Teacher',
            'berkas'     => 'smart_teacher.php',
            'form_id'    => 'formSmart',
            'placeholder' => 'mis. CP Mapel Umum',
            'contoh_ket' => 'Penjelasan singkat aplikasi ini…',
            // GRAFIK "APLIKASI YANG SERING DIGUNAKAN" (v1.79.0 — permintaan pemilik): hanya menu
            // Smart Teacher yang menampilkannya, di ATAS daftar kartu aplikasi. Datanya = seluruh
            // klik "Buka Aplikasi" dari SEMUA menu daftar aplikasi, semua sekolah/madrasah, seluruh
            // data yang tercatat (lihat app_klik_ringkas()).
            'grafik_sering' => true,
            // LENCANA "Bonus" BERKEDIP pada sidebar (PERMINTAAN PEMILIK v1.72.2): *"Pada menu Smart
            // Teacher juga tambahkan fitur 'Bonus' berkedip"*. Super Admin menandainya lewat centang
            // pada formulir aplikasinya (kolom `smart_teacher.baru_pada`, skema v53); lencana tampil
            // DI ATAS nama menu SELAMA tanda bonusnya dipasang (v1.99.0: batas hari dihapus).
            'label_baru' => true,
            // HANYA LEMBAGA YANG SUDAH MELAKUKAN AKTIVASI (permintaan pemilik, v1.78.0) — lihat
            // catatan lengkap pada entri `ujian_online` di bawah & fungsi daftar_apps_aktivasi_boleh().
            'perlu_aktivasi' => true,
            // MENU DAPAT DIAKTIFKAN / DINONAKTIFKAN SEMENTARA OLEH SUPER ADMIN (v1.81.0) —
            // permintaan pemilik: *"Pada akun super admin tambahkan fitur/menu untuk mengaktifkan atau
            // menonaktifkan menu Smart Teacher. Ketika dinonaktifkan oleh Super Admin maka menu
            // tersebut jika di klik oleh pengguna/guru akan muncul peringatan 'Server AI Smart
            // Teacher lagi mengalami gangguan. Harap coba lagi nanti. Terima Kasih'"*.
            // Penentunya SATU tempat: daftar_apps_aktif() (lihat blok "MENGAKTIFKAN / MENONAKTIFKAN
            // MENU" di bawah). Menunya TETAP tampil di sidebar supaya peringatannya dapat dibaca.
            'bisa_dinonaktifkan' => true,
        ],
        // ⚠️ v1.75.0: entri menu "Studio Foto Pintar" (`studio_foto`) DIHAPUS atas permintaan pemilik
        // ("Hapus menu yang saya tandai warna merah"). Halaman `studio_foto.php` ikut dihapus dan menu
        // hilang dari sidebar/pintasan/Petunjuk Penggunaan. Tabel `studio_foto` sengaja dibiarkan ada
        // (isian pemilik tidak dihapus); JANGAN dihidupkan kembali tanpa permintaan baru.
        // Menu "Aplikasi Sagu" — permintaan pemilik: "Tambahkan Menu Aplikasi Sagu dibawah menu
        // Smart Teacher. Cara pengisian link aplikasi seperti Smart Teacher" → bentuknya SAMA PERSIS
        // dengan Smart Teacher (cukup satu entri di sini + berkas halaman tipis `aplikasi_sagu.php`;
        // JANGAN menyalin logikanya).
        'aplikasi_sagu' => [
            'tabel'      => 'aplikasi_sagu',
            'menu'       => 'Aplikasi Sagu',
            'judul'      => 'Daftar Aplikasi Sagu',
            'berkas'     => 'aplikasi_sagu.php',
            'form_id'    => 'formSagu',
            'placeholder' => 'mis. Aplikasi Sagu',
            'contoh_ket' => 'Penjelasan singkat aplikasi ini…',
            // Menu ini DIBATASI PER SEKOLAH (permintaan pemilik): hanya muncul pada
            // sekolah/madrasah yang dicentang Super Admin pada halaman ini (tabel `menu_sekolah`).
            'per_sekolah' => true,
        ],
        // Menu "MEDIA INTERAKTIF" (v1.105.0 / skema v67) — permintaan pemilik: *"pada akun guru
        // tambahkan Menu 'Media Interaktif' tepat dibawah Smart Teacher dan beri penanda Bonus
        // berkedip. Isi dari menu ini adalah Kumpulan Media Interaktif (SD/MI - SMA/MA yang dibuatkan
        // menu sendiri-sendiri untuk memisahkan antar jenjang) dan Game Edukasi."*
        // Pilihan pemilik (jangan diubah tanpa bertanya):
        //   1. SATU halaman dengan **TAB per jenjang** (SD/MI · SMP/MTs · SMA/MA) + tab
        //      **Game Edukasi** → kunci `'kategori'` di bawah (`daftar_apps_kategori()`);
        //   2. menunya **HANYA untuk akun guru** → kunci `'hanya_guru'` (kepala & operator tidak
        //      melihat menunya dan halamannya ditolak 403; Super Admin tetap boleh karena dialah
        //      pengisi daftarnya);
        //   3. lencana **"Bonus" berkedip SELALU tampil** pada menu sidebar, bukan mengikuti tanda
        //      bonus per aplikasi seperti Smart Teacher → TIDAK memakai `'label_baru'`, melainkan
        //      `daftar_apps_nama_sidebar_bonus()` di inc/layout.php.
        'media_interaktif' => [
            'tabel'      => 'media_interaktif',
            'menu'       => 'Media Interaktif',
            'judul'      => 'Kumpulan Media Interaktif & Game Edukasi',
            'berkas'     => 'media_interaktif.php',
            'form_id'    => 'formMedia',
            'placeholder' => 'mis. Media Pembelajaran Matematika Kelas 4',
            'contoh_ket' => 'Penjelasan singkat media interaktif ini…',
            'kategori'   => MEDIA_INTERAKTIF_JENJANG,
            'kategori_jenjang' => MEDIA_INTERAKTIF_TAB_JENJANG,
            // SUB TAB FASE (v1.112.0 / skema v72) — permintaan pemilik: *"Pada Tab SD/MI Tambahkan
            // Fase A, B, C untuk memisah media itu difase apa. Begitu juga untuk Tab SMA/MA tambahkan
            // Fase E, F. Kalau SMP/MTs hanya Fase D"*. Kunci luar = kunci TAB JENJANG, kunci dalam =
            // nilai kolom `fase`. Tab yang tidak tercantum (Game Edukasi) tidak punya baris fase.
            // Satu sumber susunannya: MEDIA_INTERAKTIF_FASE (inc/config.php).
            'kategori_fase' => MEDIA_INTERAKTIF_FASE,
            'hanya_guru' => true,
            // PERINGATAN DI SISI KANAN BARIS TAB (v1.106.0) — permintaan pemilik: *"Yang saya tandai
            // warna biru buat keterangan peringatan 'Penggunaan media interaktif (aktif atau tidaknya)
            // tergantung dari kebijakan pihak gemini'. Buat tulisan warna merah dan berkedip sebagai
            // peringatan"*. Kalimatnya DIPAKAI PERSIS seperti yang diketik pemilik (jangan
            // "dirapikan"/diganti istilahnya). Dicetak `daftar_apps_halaman()` sebagai
            // `.apps-kat-ingat` — merah + berkedip (gaya di assets/style.css).
            'ingat_kategori' => 'Penggunaan media interaktif (aktif atau tidaknya) tergantung dari '
                              . 'kebijakan pihak gemini',
            // TEKS TOMBOL PADA KARTU (v1.107.0) — permintaan pemilik: *"Kata 'Buka Aplikasi' pada
            // menu Media Interaktif jenjang MI/SD - SMA/MA ganti dengan 'Buka Media'. Sedangkan kata
            // 'Buka Aplikasi' pada Game Edukasi ganti dengan 'Mainkan Game'"*. Teks per TAB disetel
            // lewat `buka_label_kategori` (kunci = nilai `jenjang`); tab yang tidak disetel memakai
            // `buka_label` (bila kosong → teks bawaan "Buka Aplikasi").
            // Satu sumber aturan: `daftar_apps_buka_label()`; jangan menulis teks tombol di kartu.
            'buka_label'          => 'Buka Media',
            'buka_label_kategori' => ['game' => 'Mainkan Game'],
            // TAB "BUATKAN MEDIA" (v1.111.0 / skema v69) — permintaan pemilik: *"Pada yang saya tandai
            // warna merah tambahkan tab 'Buatkan Media'. Isinya data media yang akan diminta: Nama
            // Guru, Asal Sekolah, Judul Media. Permintaan ini akan terkirim ke super admin. Jadi di
            // halaman super admin tambahkan Menu Permintaan Media …"*. Tab ini menjadi pil KE-5 pada
            // baris pil (tepat setelah "Game Edukasi" — posisi yang ditandai pemilik) dan HANYA tampil
            // untuk akun guru; Super Admin menindaklanjutinya lewat menu "Permintaan Media".
            // Seluruh aturannya ada di inc/media_permintaan.php — jangan menyalinnya ke sini.
            'permintaan_media' => true,
            // POPUP KUTIPAN PEMBUKA (v1.105.1 — permintaan pemilik: *"saat guru mengklik menu Media
            // Interaktif yang muncul duluan adalah popup dengan tulisan: '…' - Supianto (Pengembang)"*).
            // Dicetak sebagai `<dialog>` oleh daftar_apps_halaman() dan dibuka lebih dulu oleh
            // `initPopupAwal()` (assets/app.js). Hanya menu yang memuat kunci ini yang punya popup.
            // v1.105.2 (permintaan pemilik: *"Popup hanya muncul satu kali saja setiap guru mengklik
            // Menu Interaktif. Tidak perlu ditampilkan di super admin ketika akan mengisi"*):
            //  • **SEKALI per sesi login** untuk akun GURU — penandanya `$_SESSION['popup_awal_lihat']`
            //    (dihapus otomatis saat keluar karena logout.php mengosongkan sesi). Aturannya satu
            //    tempat: `popup_awal_perlu_tampil()` di bawah;
            //  • **Super Admin TIDAK PERNAH** melihatnya (dialah pengisi daftar media — popup hanya
            //    menghalangi pekerjaannya).
            'popup_awal' => [
                'teks'   => 'Pendidikan adalah kunci pembuka pintu masa depan. Mari manfaatkan teknologi '
                          . 'untuk keberkahan ilmu dan kemajuan umat.',
                'sumber' => APP_AUTHOR,          // "Supianto" (konstanta aplikasi, bukan tulisan mati)
                'peran'  => 'Pengembang',
            ],
        ],
        'hasil_supervisi' => [
            'tabel'      => 'hasil_supervisi',
            'menu'       => 'Hasil Supervisi',
            'judul'      => 'Daftar Aplikasi Hasil Supervisi',
            'berkas'     => 'hasil_supervisi.php',
            'form_id'    => 'formHasil',
            'placeholder' => 'mis. Supervisi Guru',
            'contoh_ket' => 'Penjelasan singkat aplikasi ini…',
        ],
        // Menu "UJIAN ONLINE" — permintaan pemilik: *"Dibawah menu pelanggaran siswa tambahkan menu
        // 'Ujian Online'. Isi dari menu ini buat seperti menu smart teacher yaitu memasukkan link
        // aplikasi ujian online lewat akun super admin"* → bentuknya SAMA PERSIS dengan Smart Teacher
        // (satu entri di sini + berkas halaman tipis `ujian_online.php`; JANGAN menyalin logikanya).
        'ujian_online' => [
            'tabel'      => 'ujian_online',
            'menu'       => 'Ujian Online',
            'judul'      => 'Daftar Aplikasi Ujian Online',
            'berkas'     => 'ujian_online.php',
            'form_id'    => 'formUjian',
            'placeholder' => 'mis. CBT Sekolah',
            'contoh_ket' => 'Penjelasan singkat aplikasi ujian online ini…',
            // LENCANA "Bonus" BERKEDIP pada sidebar (permintaan pemilik v1.59.0, teksnya diganti
            // menjadi "Bonus" pada v1.72.2): *"Pada menu ujian online tambahkan tulisan new berkedip
            // seperti pada aplikasi smart teacher"* → *"Tulisan new berkedip ganti dengan tulisan
            // Bonus"*. Super Admin menandainya lewat centang pada formulir aplikasinya (kolom
            // `ujian_online.baru_pada`); lencana tampil DI ATAS nama menu pada sidebar SELAMA tanda
            // bonusnya dipasang (v1.99.0: batas 30 hari yang membuat lencana Ujian Online lenyap
            // sendiri DIHAPUS). Sejak v1.72.2 menu **Smart Teacher** juga memakai fitur ini; menu
            // daftar aplikasi lain belum.
            'label_baru' => true,
            // HANYA LEMBAGA YANG SUDAH MELAKUKAN AKTIVASI (permintaan pemilik, v1.78.0):
            // *"Pada Menu Ujian Online dan Menu Smart Teacher hanya bisa diakses oleh lembaga yang
            // telah melakukan aktifasi. Bagi yang belum, menu ini belum bisa diakses dengan
            // keterangan: Menu ini hanya bisa diakses oleh lembaga yang sudah melakukan aktifasi."*
            // → daftar_apps_aktivasi_boleh() menolak lembaga yang masih masa uji coba (belum pernah
            // memasukkan kode aktivasi); halamannya tetap dapat dibuka tetapi hanya menampilkan
            // keterangan tersebut + tombol ke halaman Status & Aktivasi.
            'perlu_aktivasi' => true,
        ],
        // Menu "Bendahara" — permintaan pemilik: "Pada menu Bendahara isinya buat seperti Menu
        // Supervisi yang isinya link aplikasi yang diisi oleh Super Admin. Tapi pada pengisian link
        // aplikasi tambahkan fitur link aplikasi bisa dibuka oleh madrasah yang diinginkan oleh super
        // admin. Madrasah yang tidak dituju buat keterangan madrasah anda belum punya akses ke
        // aplikasi keuangan."
        //
        // PEMBARUAN PERMINTAAN PEMILIK (v1.32.0): *"Menu Bendahara di bawah menu supervisi dihapus.
        // Isi menunya dipindahkan di Menu Tugas Tambahan yang Bendahara Bos."* → menu ini TIDAK LAGI
        // punya halaman/hiasan sidebar sendiri; isinya dirender dari halaman **menu Tugas Tambahan**
        // (`tugas.php?kode=…`) lewat `daftar_apps_tugas_kunci()`. Karena itu tidak ada kunci
        // `'berkas'` yang dipakai untuk tautan (URL dibangun dari baris menu Tugas Tambahannya).
        'bendahara' => [
            'tabel'      => 'bendahara',
            'menu'       => 'Bendahara',
            'judul'      => 'Daftar Aplikasi Bendahara',
            'form_id'    => 'formBendahara',
            'placeholder' => 'mis. e-BOS (Dana BOS)',
            'contoh_ket' => 'Penjelasan singkat aplikasi keuangan ini…',
            // Menu `tugas_menu` yang isinya memakai daftar aplikasi ini. Dikenali dari KODE maupun
            // NAMA (pola yang sama dengan `tugas_menu_ka_tu()`), supaya tetap bekerja walau Super
            // Admin mengubah nama menunya atau kodenya dibuat otomatis ("Bendahara Bos" →
            // `bendahara-bos`).
            'tugas_kode' => ['bendahara', 'bendahara-bos', 'bendahara_bos'],
            'tugas_nama' => ['bendahara bos'],
            // PEMBATASAN PER APLIKASI (bukan per menu): tiap aplikasi disetel sekolah/madrasah mana
            // yang boleh membukanya; sekolah di luar daftar melihat keterangan
            // "Madrasah Anda belum punya akses ke aplikasi keuangan".
            'per_aplikasi' => true,
        ],
        // Menu Tugas Tambahan "BENDAHARA MADRASAH" (v1.33.0/skema v33) — permintaan pemilik:
        // *"Isi menu dari tugas tambahan 'Bendahara Madrasah' buat seperti Bendahara Bos"*.
        // Pilihan pemilik (jangan diubah tanpa bertanya): **(1) daftar aplikasinya TERPISAH** dari
        // "Bendahara Bos" (tabel sendiri + penyetelan aksesnya sendiri); **(2) pembatasan akses per
        // aplikasi tetap berlaku** seperti pada Bendahara Bos.
        // ⚠️ `tugas_kode`/`tugas_nama` WAJIB dibedakan dari entri `bendahara` — kalau nama/kodenya
        // sama, `daftar_apps_tugas_kunci()` akan mengembalikan entri yang lebih dahulu diperiksa dan
        // kedua menu itu berbagi satu daftar aplikasi (padahal pemilik meminta TERPISAH).
        'bendahara_madrasah' => [
            'tabel'      => 'bendahara_madrasah',
            'menu'       => 'Bendahara Madrasah',
            'judul'      => 'Daftar Aplikasi Bendahara Madrasah',
            'form_id'    => 'formBendaharaMadrasah',
            'placeholder' => 'mis. e-BOS (Dana BOS)',
            'contoh_ket' => 'Penjelasan singkat aplikasi keuangan ini…',
            'tugas_kode' => ['bendahara-madrasah', 'bendahara_madrasah', 'bendaharamadrasah'],
            'tugas_nama' => ['bendahara madrasah'],
            'per_aplikasi' => true,
        ],
    ];
    if ($menu === null) {
        return $semua;
    }
    return $semua[$menu] ?? null;
}

/**
 * Kunci menu daftar aplikasi dari sebuah baris menu **TUGAS TAMBAHAN**, atau null bila menu itu
 * bukan menu daftar aplikasi.
 *
 * Dipakai `tugas.php` untuk merender isi menu Tugas Tambahan yang memakai modul ini — permintaan
 * pemilik (v1.32.0): *"Menu Bendahara di bawah menu supervisi dihapus. Isi menunya dipindahkan di
 * Menu Tugas Tambahan yang Bendahara Bos."*
 *
 * Dikenali dari **kode** MAUPUN **nama** (pola yang sama dengan `tugas_menu_ka_tu()`), supaya tetap
 * bekerja walau Super Admin mengubah nama menunya, atau kodenya dibuat otomatis dari nama
 * ("Bendahara Bos" → `bendahara-bos`).
 */
function daftar_apps_tugas_kunci(?array $tugas): ?string
{
    if (!$tugas) {
        return null;
    }
    $kode = strtolower(trim((string) ($tugas['kode'] ?? '')));
    $nama = tugas_nama_kunci((string) ($tugas['nama'] ?? ''));
    foreach (daftar_apps_konfig() as $kunci => $k) {
        foreach ((array) ($k['tugas_kode'] ?? []) as $sah) {
            if ($kode !== '' && $kode === strtolower(trim((string) $sah))) {
                return $kunci;
            }
        }
        foreach ((array) ($k['tugas_nama'] ?? []) as $sah) {
            if ($nama !== '' && $nama === tugas_nama_kunci((string) $sah)) {
                return $kunci;
            }
        }
    }
    return null;
}

/** Seluruh kode menu Tugas Tambahan yang sudah punya modul daftar aplikasi (dipakai anti-tabrakan). */
function daftar_apps_kode_tugas(): array
{
    $keluar = [];
    foreach (daftar_apps_konfig() as $k) {
        foreach ((array) ($k['tugas_kode'] ?? []) as $sah) {
            $keluar[] = (string) $sah;
        }
    }
    return $keluar;
}

/** true bila menu Tugas Tambahan itu isinya memakai daftar aplikasi DAN boleh ditampilkan sekarang. */
function tugas_menu_daftar_apps_tampil(array $tugas): bool
{
    $kunci = daftar_apps_tugas_kunci($tugas);
    return $kunci === null || daftar_apps_tampil($kunci);
}

/** Saring daftar menu Tugas Tambahan: buang menu daftar aplikasi yang aksesnya sedang tertutup. */
function tugas_menu_saring_tampil(array $daftar): array
{
    return array_values(array_filter($daftar, 'tugas_menu_daftar_apps_tampil'));
}

/**
 * Bangun alamat halaman daftar aplikasi.
 *
 * Menu yang berdiri sendiri memakai berkasnya sendiri (mis. `smart_teacher.php`), sedangkan menu
 * yang isinya dipindahkan ke menu Tugas Tambahan memakai alamat menunya
 * (`tugas.php?kode=…`) — sehingga pemisah query-nya harus diperiksa ('?' vs '&').
 *
 * @param array<string,scalar|null> $q parameter tambahan (nilai null/kosong dibuang)
 */
function daftar_apps_url(string $berkas, array $q = []): string
{
    $q = array_filter($q, static fn($v): bool => $v !== null && $v !== '');
    if (!$q) {
        return $berkas;
    }
    return $berkas . (str_contains($berkas, '?') ? '&' : '?') . http_build_query($q);
}

/**
 * Nama TABEL untuk sebuah menu. Nilainya diambil dari daftar konfigurasi (whitelist), bukan dari
 * masukan pengguna, sehingga aman dipakai menyusun SQL.
 */
function daftar_apps_tabel(string $menu): string
{
    $k = daftar_apps_konfig($menu);
    if ($k === null) {
        throw new RuntimeException('Menu daftar aplikasi tidak dikenal: ' . $menu);
    }
    return $k['tabel'];
}

/**
 * true bila menu ini dibatasi per sekolah (`'per_sekolah' => true` pada konfigurasi).
 *
 * Menu yang TIDAK dibatasi tetap global seperti semula; hanya menu bertanda inilah yang memakai
 * tabel `menu_sekolah`.
 */
function daftar_apps_terbatas(string $menu): bool
{
    $k = daftar_apps_konfig($menu);
    return $k !== null && !empty($k['per_sekolah']);
}

/**
 * true bila aplikasi pada menu ini DIBATASI PER APLIKASI (`'per_aplikasi' => true`) — dipakai menu
 * **Bendahara** (permintaan pemilik: "pada pengisian link aplikasi tambahkan fitur link aplikasi
 * bisa dibuka oleh madrasah yang diinginkan oleh super admin").
 *
 * Perbedaannya dengan `daftar_apps_terbatas()`:
 *   - `per_sekolah`  → yang dibatasi MENUNYA (satu daftar centang untuk seluruh menu; Aplikasi Sagu);
 *   - `per_aplikasi` → yang dibatasi tiap APLIKASI (satu daftar centang per baris aplikasi; Bendahara).
 */
function daftar_apps_per_aplikasi(string $menu): bool
{
    $k = daftar_apps_konfig($menu);
    return $k !== null && !empty($k['per_aplikasi']);
}

/** Daftar id sekolah/madrasah yang boleh membuka SATU aplikasi (urut nama). */
function daftar_apps_app_sekolah_ids(string $menu, int $appId): array
{
    if (!daftar_apps_per_aplikasi($menu) || $appId <= 0) {
        return [];
    }
    $keluar = [];
    foreach (all('SELECT sekolah_id FROM app_sekolah WHERE menu = ? AND app_id = ?', [$menu, $appId]) as $r) {
        $keluar[] = (int) $r['sekolah_id'];
    }
    return $keluar;
}

/**
 * true bila SEKOLAH itu boleh membuka aplikasi tersebut.
 *
 * ⚠️ **Aplikasi yang aksesnya BELUM DIATUR boleh dibuka semua sekolah** (`akses_diatur = 0`) —
 * perilaku bawaan yang sama dengan menu daftar aplikasi lain, supaya aplikasi yang sudah ada tidak
 * mendadak tertutup bagi semua orang. Begitu Super Admin menyimpan formulirnya (penanda
 * `app_sekolah_atur`), kolom `akses_diatur` menjadi 1 dan **hanya sekolah yang dicentang** yang boleh
 * membukanya — jadi "tidak ada yang dicentang" = tidak tampil untuk sekolah mana pun.
 */
function daftar_apps_app_boleh(string $menu, int $appId, int $sekolahId): bool
{
    if (!daftar_apps_per_aplikasi($menu)) {
        return true;
    }
    if ($sekolahId <= 0) {
        return false;
    }
    $app = daftar_apps_by_id($menu, $appId);
    if (!$app) {
        return false;
    }
    if ((int) ($app['akses_diatur'] ?? 0) !== 1) {
        return true;   // akses belum diatur Super Admin → masih terbuka untuk semua sekolah/madrasah
    }
    return (int) val('SELECT COUNT(*) FROM app_sekolah WHERE menu = ? AND app_id = ? AND sekolah_id = ?',
        [$menu, $appId, $sekolahId], 0) > 0;
}

/**
 * Simpan pilihan sekolah/madrasah yang boleh membuka SATU aplikasi (hanya dipakai Super Admin).
 * Daftar yang kosong berarti aplikasi itu TIDAK tampil untuk sekolah mana pun — keadaan yang sah
 * (Super Admin memang tidak mencentang satu pun).
 *
 * @param array<int,mixed> $ids id sekolah yang dicentang
 * @return array{simpan:int,buang:int}
 */
function daftar_apps_app_simpan(string $menu, int $appId, array $ids): array
{
    if (!daftar_apps_per_aplikasi($menu) || $appId <= 0) {
        return ['simpan' => 0, 'buang' => 0];
    }
    $sebelum = daftar_apps_app_sekolah_ids($menu, $appId);
    $sah = [];
    foreach (all('SELECT id FROM sekolah') as $r) {
        $sah[(int) $r['id']] = true;
    }
    $pilih = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0 && isset($sah[$id])) {
            $pilih[$id] = true;
        }
    }
    $now = date('Y-m-d H:i:s');
    $simpan = 0;
    $buang = 0;
    db()->exec('BEGIN IMMEDIATE');
    try {
        // Tandai bahwa daftar sekolah aplikasi ini SUDAH diatur Super Admin → "tidak ada yang
        // dicentang" berarti aplikasi tidak tampil untuk sekolah mana pun.
        q('UPDATE ' . daftar_apps_tabel($menu) . ' SET akses_diatur = 1 WHERE id = ?', [$appId]);
        foreach (array_keys($pilih) as $id) {
            if (!in_array($id, $sebelum, true)) {
                q('INSERT OR IGNORE INTO app_sekolah (menu, app_id, sekolah_id, dibuat) VALUES (?,?,?,?)',
                    [$menu, $appId, $id, $now]);
                $simpan++;
            }
        }
        foreach ($sebelum as $id) {
            if (!isset($pilih[$id])) {
                q('DELETE FROM app_sekolah WHERE menu = ? AND app_id = ? AND sekolah_id = ?',
                    [$menu, $appId, $id]);
                $buang++;
            }
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return ['simpan' => $simpan, 'buang' => $buang];
}

/** Hapus seluruh penyetelan sekolah sebuah aplikasi (dipakai saat aplikasinya dihapus). */
function daftar_apps_app_bersihkan(string $menu, int $appId): void
{
    if ($appId > 0 && daftar_apps_per_aplikasi($menu)) {
        q('DELETE FROM app_sekolah WHERE menu = ? AND app_id = ?', [$menu, $appId]);
    }
}

/**
 * true bila SEKOLAH/madrasah itu punya AKSES ke minimal satu aplikasi AKTIF pada menu ini.
 * Dipakai memutuskan apakah MENUNYA ditampilkan sama sekali (pilihan pemilik: "Menu disembunyikan
 * sama sekali (tidak tampil di sidebar)"). Menu tanpa pembatasan per aplikasi selalu true.
 */
function daftar_apps_sekolah_punya_akses(string $menu, int $sekolahId): bool
{
    if (!daftar_apps_per_aplikasi($menu)) {
        return true;
    }
    foreach (daftar_apps_list($menu, true) as $a) {
        if (daftar_apps_app_boleh($menu, (int) $a['id'], $sekolahId)) {
            return true;
        }
    }
    return false;
}

/** Daftar id sekolah/madrasah yang boleh melihat sebuah menu (urut nama). */
function daftar_apps_sekolah_ids(string $menu): array
{
    if (!daftar_apps_terbatas($menu)) {
        return [];
    }
    $keluar = [];
    foreach (all('SELECT sekolah_id FROM menu_sekolah WHERE menu = ?', [$menu]) as $r) {
        $keluar[] = (int) $r['sekolah_id'];
    }
    return $keluar;
}

/** true bila SEKOLAH itu boleh melihat menu (tidak melihat peran pengguna). */
function daftar_apps_sekolah_boleh(string $menu, int $sekolahId): bool
{
    if (!daftar_apps_terbatas($menu)) {
        return true;
    }
    if ($sekolahId <= 0) {
        return false;
    }
    return (int) val('SELECT COUNT(*) FROM menu_sekolah WHERE menu = ? AND sekolah_id = ?',
        [$menu, $sekolahId], 0) > 0;
}

/**
 * SATU-SATUNYA penentu menu daftar aplikasi boleh DITAMPILKAN/dibuka untuk pengguna yang sedang
 * masuk. Dipakai oleh sidebar, pintasan dashboard, dan penjaga halaman (`daftar_apps_halaman()`)
 * sehingga tidak pernah ada menu yang terlihat tetapi tidak bisa dibuka (atau sebaliknya).
 *
 * Aturan:
 *   - menu yang tidak dibatasi → tampil untuk semua sekolah/pengguna (perilaku lama);
 *   - menu yang dibatasi PER SEKOLAH (`menu_sekolah`) → tampil HANYA bila sekolah yang sedang
 *     dibuka termasuk yang dicentang;
 *   - menu yang dibatasi PER APLIKASI (`app_sekolah`, menu Bendahara) → tampil hanya bila sekolah itu
 *     punya akses ke **minimal satu aplikasi aktif**; bila tidak punya sama sekali, **menunya
 *     disembunyikan** (pilihan pemilik: "Menu disembunyikan sama sekali (tidak tampil di sidebar)");
 *   - **Super Admin SELALU boleh** (dialah pengelola daftar aplikasi & sekolahnya).
 */
function daftar_apps_tampil(string $menu): bool
{
    if (is_super()) {
        return true;
    }
    $s = current_sekolah();
    if (!$s) {
        // Tanpa konteks sekolah: menu yang dibatasi tidak dapat diputuskan → jangan ditampilkan
        // (peran non-Super Admin selalu punya konteks sekolah; pengawas setelah memilih binaan).
        return !daftar_apps_terbatas($menu) && !daftar_apps_per_aplikasi($menu);
    }
    $sid = (int) $s['id'];
    if (daftar_apps_terbatas($menu) && !daftar_apps_sekolah_boleh($menu, $sid)) {
        return false;
    }
    return daftar_apps_sekolah_punya_akses($menu, $sid);
}

// ============================================================================================
// HANYA LEMBAGA YANG SUDAH MELAKUKAN AKTIVASI (v1.78.0 — permintaan pemilik)
// --------------------------------------------------------------------------------------------
// Permintaan pemilik: *"Pada Menu Ujian Online dan Menu Smart Teacher hanya bisa diakses oleh
// lembaga yang telah melakukan aktifasi. Bagi yang belum, menu ini belum bisa diakses dengan
// keterangan: Menu ini hanya bisa diakses oleh lembaga yang sudah melakukan aktifasi."*
//
// Aturan yang dipakai (SATU tempat — jangan menyalinnya ke halaman/sidebar):
//  • Menu yang ditandai `'perlu_aktivasi' => true` pada `daftar_apps_konfig()` (sekarang: **Smart
//    Teacher** & **Ujian Online**) hanya boleh dibuka lembaga yang status aktivasinya BUKAN masa uji
//    coba — yaitu `aktif` (365 hari, sudah memasukkan kode dari HD Admin) atau `permanen` (diberikan
//    Super Admin). Lembaga yang masih `uji_coba` = **belum melakukan aktivasi** → hanya melihat
//    keterangan pada halamannya (menu sidebar TETAP tampil supaya keterangannya dapat dibaca).
//  • Masa aktif yang sudah HABIS (`aktivasi_terkunci()`) juga tidak boleh membuka daftar — seluruh
//    aplikasi memang sudah terkunci lewat `gerbang_aktivasi()`; untuk pengawas (yang tidak terkena
//    gerbang) penjagaan ini tetap berlaku.
//  • **Super Admin SELALU boleh**: dialah pengelola daftar aplikasinya (menambah/mengubah link).
//    Tanpa pengecualian ini, Super Admin tidak dapat mengisi daftar untuk sekolah mana pun.
// ============================================================================================

/** Apakah menu daftar aplikasi ini mensyaratkan lembaga sudah melakukan aktivasi? */
function daftar_apps_perlu_aktivasi(string $menu): bool
{
    $k = daftar_apps_konfig($menu);
    return $k !== null && !empty($k['perlu_aktivasi']);
}

/**
 * true bila pengguna yang sedang masuk boleh MEMBUKA ISI menu daftar aplikasi ini.
 * Lihat aturan lengkap pada blok komentar di atas.
 */
function daftar_apps_aktivasi_boleh(string $menu): bool
{
    if (!daftar_apps_perlu_aktivasi($menu)) {
        return true;
    }
    if (is_super()) {
        return true;
    }
    $s = current_sekolah();
    if (!$s) {
        return false;
    }
    $st = aktivasi_status($s);
    return in_array($st['mode'], ['permanen', 'aktif'], true) && !$st['habis'];
}

/**
 * Keterangan yang WAJIB tampil pada menu yang belum boleh diakses (kalimat permintaan pemilik,
 * jangan diubah): dipakai halaman menu, lencana sidebar, dan panduan Petunjuk Penggunaan.
 */
function daftar_apps_aktivasi_pesan(): string
{
    return 'Menu ini hanya bisa diakses oleh lembaga yang sudah melakukan aktifasi.';
}

/**
 * Lencana kecil pada sidebar untuk menu yang sedang terkunci karena lembaga belum melakukan
 * aktivasi (`''` bila menu tetap boleh dibuka). Dipakai `inc/layout.php`.
 */
function daftar_apps_aktivasi_pil(string $menu): string
{
    if (daftar_apps_aktivasi_boleh($menu)) {
        return '';
    }
    return '<span class="pill pill-warn" style="margin-left:auto" title="'
        . e(daftar_apps_aktivasi_pesan()) . '">Aktivasi</span>';
}

// ============================================================================================
// MENGAKTIFKAN / MENONAKTIFKAN MENU (v1.81.0 — permintaan pemilik)
// --------------------------------------------------------------------------------------------
// Permintaan pemilik: *"Pada akun super admin tambahkan fitur/menu untuk mengaktifkan atau
// menonaktifkan menu Smart Teacher. Ketika dinonaktifkan oleh Super Admin maka menu tersebut jika
// di klik oleh pengguna/guru akan muncul peringatan 'Server AI Smart Teacher lagi mengalami
// gangguan. Harap coba lagi nanti. Terima Kasih'"*
//
// Aturan yang dipakai (SATU tempat — jangan menyalin ke halaman/sidebar):
//  • Keadaannya TINGKAT APLIKASI (tabel `app_pengaturan`, kunci `menu_aktif_<kunci menu>`), karena
//    daftar aplikasi ini global (tidak per sekolah) → TIDAK ada perubahan skema.
//  • Hanya menu yang ditandai `'bisa_dinonaktifkan' => true` pada `daftar_apps_konfig()` (sekarang:
//    **Smart Teacher**). Menu lain selalu dianggap aktif.
//  • Menunya **TETAP tampil di sidebar** (permintaan pemilik: "jika di klik … muncul peringatan"),
//    diberi lencana **"Gangguan"**; halamannya tetap dapat dibuka (200) tetapi isinya diganti kartu
//    peringatan gangguan untuk SELURUH peran **kecuali Super Admin** — Super Admin harus tetap
//    melihat halaman penuh beserta kartu pengaktif, kalau tidak ia tidak bisa menghidupkannya lagi.
//  • Tombol "Buka Aplikasi" ikut ditutup (`app_buka.php`) supaya tautan aplikasinya tidak bisa
//    dibuka lewat alamat langsung selama menunya dinonaktifkan.
// ============================================================================================

/** Apakah menu ini boleh diaktifkan/dinonaktifkan Super Admin? */
function daftar_apps_bisa_dinonaktifkan(string $menu): bool
{
    $k = daftar_apps_konfig($menu);
    return $k !== null && !empty($k['bisa_dinonaktifkan']);
}

/** Kunci penyimpanan keadaan menu pada tabel `app_pengaturan`. */
function daftar_apps_aktif_kunci(string $menu): string
{
    return 'menu_aktif_' . $menu;
}

/** true bila menu sedang AKTIF (menu tanpa fitur ini selalu aktif). */
function daftar_apps_aktif(string $menu): bool
{
    if (!daftar_apps_bisa_dinonaktifkan($menu)) {
        return true;
    }
    return app_setting(daftar_apps_aktif_kunci($menu), '1') !== '0';
}

/** Aktifkan/nonaktifkan sebuah menu (hanya berlaku untuk menu ber-`bisa_dinonaktifkan`). */
function daftar_apps_aktif_set(string $menu, bool $aktif): void
{
    if (!daftar_apps_bisa_dinonaktifkan($menu)) {
        return;
    }
    app_setting_set(daftar_apps_aktif_kunci($menu), $aktif ? '1' : '0');
    app_setting_set(daftar_apps_aktif_kunci($menu) . '_pada', date('Y-m-d H:i:s'));
}

/** Kapan keadaan menu terakhir diubah ('' bila belum pernah) — dipakai kartu Super Admin. */
function daftar_apps_aktif_diubah(string $menu): string
{
    return trim(app_setting(daftar_apps_aktif_kunci($menu) . '_pada', ''));
}

/**
 * Kalimat peringatan saat menu dinonaktifkan — PERSIS permintaan pemilik untuk Smart Teacher:
 * "Server AI Smart Teacher lagi mengalami gangguan. Harap coba lagi nanti. Terima Kasih".
 */
function daftar_apps_gangguan_pesan(string $menu): string
{
    $k = daftar_apps_konfig($menu);
    $nama = $k !== null ? (string) ($k['menu'] ?? 'Aplikasi') : 'Aplikasi';
    return 'Server AI ' . $nama . ' lagi mengalami gangguan. Harap coba lagi nanti. Terima Kasih';
}

/** Lencana sidebar "Gangguan" ('' bila menu aktif / menu tidak punya fitur ini). */
function daftar_apps_gangguan_pil(string $menu): string
{
    if (daftar_apps_aktif($menu)) {
        return '';
    }
    return '<span class="pill pill-warn" style="margin-left:auto" title="'
        . e(daftar_apps_gangguan_pesan($menu)) . '">Gangguan</span>';
}

/**
 * Simpan pilihan sekolah/madrasah yang boleh melihat sebuah menu (hanya dipakai Super Admin).
 *
 * @param array<int,mixed> $ids id sekolah yang dicentang
 * @return array{simpan:int,buang:int}
 */
function daftar_apps_sekolah_simpan(string $menu, array $ids): array
{
    if (!daftar_apps_terbatas($menu)) {
        return ['simpan' => 0, 'buang' => 0];
    }
    $sebelum = daftar_apps_sekolah_ids($menu);
    $sah = [];
    foreach (all('SELECT id FROM sekolah') as $r) {
        $sah[(int) $r['id']] = true;
    }
    $pilih = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0 && isset($sah[$id])) {
            $pilih[$id] = true;
        }
    }
    $now = date('Y-m-d H:i:s');
    $simpan = 0;
    $buang = 0;
    db()->exec('BEGIN IMMEDIATE');
    try {
        foreach (array_keys($pilih) as $id) {
            if (!in_array($id, $sebelum, true)) {
                q('INSERT OR IGNORE INTO menu_sekolah (menu, sekolah_id, dibuat) VALUES (?,?,?)',
                    [$menu, $id, $now]);
                $simpan++;
            }
        }
        foreach ($sebelum as $id) {
            if (!isset($pilih[$id])) {
                q('DELETE FROM menu_sekolah WHERE menu = ? AND sekolah_id = ?', [$menu, $id]);
                $buang++;
            }
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return ['simpan' => $simpan, 'buang' => $buang];
}

/** Daftar aplikasi sebuah menu (`$aktifSaja` menyembunyikan yang nonaktif). */
function daftar_apps_list(string $menu, bool $aktifSaja = true): array
{
    $tabel = daftar_apps_tabel($menu);
    return all('SELECT * FROM ' . $tabel . ($aktifSaja ? ' WHERE aktif = 1' : '') . ' ORDER BY urutan, nama');
}

function daftar_apps_by_id(string $menu, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return one('SELECT * FROM ' . daftar_apps_tabel($menu) . ' WHERE id = ?', [$id]);
}

/** Nomor urut berikutnya untuk aplikasi baru (diletakkan paling bawah). */
function daftar_apps_urutan_baru(string $menu): int
{
    return (int) val('SELECT IFNULL(MAX(urutan), 0) + 10 FROM ' . daftar_apps_tabel($menu), [], 10);
}

// ================================================================================================
// LENCANA "Bonus" BERKEDIP PADA MENU SIDEBAR (permintaan pemilik)
// ------------------------------------------------------------------------------------------------
// Riwayat: v1.59.0 = *"Pada menu ujian online tambahkan tulisan new berkedip seperti pada aplikasi
// smart teacher"* (teks lencana **NEW**). **v1.72.2** = *"Tulisan new berkedip ganti dengan tulisan
// 'Bonus'. Pada menu Smart Teacher juga tambahkan fitur 'Bonus' berkedip"* → teks lencana kini
// **`DAFTAR_APPS_BARU_TEKS` = "Bonus"** dan fiturnya dipakai menu **Ujian Online** + **Smart
// Teacher**.
//
// Cara kerjanya: Super Admin mencentang **"Tandai sebagai bonus"** pada formulir aplikasinya, lalu
// lencana (merah, berkedip) tampil DI ATAS nama menu pada sidebar **selama tanda itu dipasang** dan
// hanya hilang bila centangnya dilepas (disimpan). ⚠️ v1.99.0 — sebelumnya lencana HILANG SENDIRI
// setelah `DAFTAR_APPS_BARU_HARI` (30) hari; batas itu DIHAPUS atas permintaan pemilik (*"Pada menu
// Ujian Online tambahkan tulisan Bonus Berkedip seperti Smart Teacher"*) karena lencana di menu Ujian
// Online lenyap sendiri tanpa ada yang melepasnya. Pilihan pemilik (jangan diubah tanpa bertanya):
// **hanya pada MENU SIDEBAR** — tidak dipasang pada kartu aplikasi maupun tabel pengelolaan.
//
// Yang disimpan hanya WAKTU penandaan (`baru_pada`) sebagai penanda "berlabel bonus"; tanggalnya
// ditampilkan pada formulir. Menu yang belum memakai fitur ini (mis. Aplikasi Sagu, Hasil Supervisi,
// Bendahara) TIDAK terpengaruh: kolomnya hanya ada pada tabel menu yang entri konfigurasinya memuat
// `'label_baru' => true`.
// ================================================================================================

/** Apakah sebuah menu daftar aplikasi memakai lencana "Bonus" pada sidebar. */
function daftar_apps_label_baru(string $menu): bool
{
    $k = daftar_apps_konfig($menu);
    return $k !== null && !empty($k['label_baru']);
}

/**
 * Apakah SATU aplikasi berstatus bonus (mendapat lencana "Bonus").
 * v1.99.0: TIDAK lagi dibatasi umur — lencana tampil selama tanda bonusnya dipasang (permintaan
 * pemilik: lencana di menu Ujian Online tidak boleh lenyap sendiri).
 */
function daftar_apps_baru(?array $app): bool
{
    $waktu = trim((string) ($app['baru_pada'] ?? ''));
    if ($waktu === '') {
        return false;
    }
    return strtotime($waktu) !== false;
}

/** Tanggal penandaan bonus ('' bila tidak berlabel) — untuk keterangan pada formulir. */
function daftar_apps_baru_tanggal(?array $app): string
{
    $waktu = trim((string) ($app['baru_pada'] ?? ''));
    if ($waktu === '' || strtotime($waktu) === false) {
        return '';
    }
    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts = (int) strtotime($waktu);
    return (int) date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * Jumlah aplikasi AKTIF yang masih berstatus baru pada sebuah menu.
 * Menu sidebar mendapat lencana "Bonus" bila angka ini > 0 (aplikasi yang disembunyikan tidak dihitung —
 * kalau aplikasinya tidak tampil, tidak ada yang "baru" bagi pengguna).
 */
function daftar_apps_baru_jumlah(string $menu): int
{
    if (!daftar_apps_label_baru($menu)) {
        return 0;
    }
    $n = 0;
    foreach (daftar_apps_list($menu, true) as $a) {
        if (daftar_apps_baru($a)) {
            $n++;
        }
    }
    return $n;
}

/**
 * Tandai / lepas lencana "Bonus" sebuah aplikasi.
 * Bila aplikasi SUDAH bertanda dan tetap dicentang, waktu lamanya DIPERTAHANKAN — supaya menyimpan
 * formulir berulang kali (mis. saat memperbaiki tautannya) tidak memperpanjang labelnya selamanya.
 */
function daftar_apps_set_baru(string $menu, int $id, bool $baru): void
{
    if ($id <= 0 || !daftar_apps_label_baru($menu)) {
        return;
    }
    $tabel = daftar_apps_tabel($menu);
    $a = one('SELECT baru_pada FROM ' . $tabel . ' WHERE id = ?', [$id]);
    if (!$a) {
        return;
    }
    if (!$baru) {
        q('UPDATE ' . $tabel . ' SET baru_pada = NULL WHERE id = ?', [$id]);
        return;
    }
    if (trim((string) ($a['baru_pada'] ?? '')) === '') {
        q('UPDATE ' . $tabel . ' SET baru_pada = ? WHERE id = ?', [date('Y-m-d H:i:s'), $id]);
    }
}

/** Lencana "Bonus" (merah, berkedip) siap cetak — '' bila menu itu tidak punya aplikasi bonus. */
function daftar_apps_badge_new(string $menu, string $kelas = ''): string
{
    $jml = daftar_apps_baru_jumlah($menu);
    if ($jml <= 0) {
        return '';
    }
    $teks = DAFTAR_APPS_BARU_TEKS;
    $judul = 'Ada ' . $jml . ' aplikasi bonus · lencana tampil selama tanda bonus dipasang';
    return '<span class="new-tag' . ($kelas !== '' ? ' ' . $kelas : '') . '" title="' . e($judul) . '">'
        . e($teks) . '</span>';
}

/**
 * Nama menu untuk SIDEBAR — lencana "Bonus" diletakkan SEBARIS DI ATAS nama menu lewat wadah
 * `.nav-nama`, sedangkan menu tanpa aplikasi bonus tetap satu `<span>` biasa sehingga tampilannya
 * tidak berubah sama sekali.
 */
function daftar_apps_nama_sidebar(string $menu, string $nama): string
{
    $badge = daftar_apps_badge_new($menu);
    if ($badge === '') {
        return '<span>' . e($nama) . '</span>';
    }
    return '<span class="nav-nama">' . $badge . '<span>' . e($nama) . '</span></span>';
}

/**
 * Nama menu untuk SIDEBAR dengan lencana "Bonus" yang **SELALU** tampil (permintaan pemilik
 * v1.105.0 untuk menu **Media Interaktif**: *"beri penanda Bonus berkedip"*).
 *
 * Perbedaannya dengan `daftar_apps_nama_sidebar()`: lencana di sini TIDAK bergantung pada tanda
 * bonus per aplikasi (menu Media Interaktif tidak punya kolom `baru_pada`) — pilihannya memang
 * "lencana selalu tampil". Jangan dipakai untuk menu yang memakai `'label_baru' => true`, karena
 * lencananya akan tampil terus walau Super Admin melepas semua tanda bonusnya.
 */
function daftar_apps_nama_sidebar_bonus(string $nama): string
{
    return '<span class="nav-nama"><span class="new-tag">' . e(DAFTAR_APPS_BARU_TEKS) . '</span>'
        . '<span>' . e($nama) . '</span></span>';
}

// ================================================================================================
// TAB KATEGORI PADA MENU DAFTAR APLIKASI (v1.105.0 — menu "Media Interaktif")
// ------------------------------------------------------------------------------------------------
// Permintaan pemilik: *"Isi dari menu ini adalah Kumpulan Media Interaktif (SD/MI - SMA/MA yang
// dibuatkan menu sendiri-sendiri untuk memisahkan antar jenjang) dan Game Edukasi."* Pilihan
// pemilik: SATU halaman dengan **tab** (bukan sub menu di sidebar), jadi satu menu = satu halaman
// dengan pil tab per jenjang.
//
// Cara kerjanya: menu yang konfigurasinya memuat `'kategori'` (kunci => label) punya kolom
// `jenjang` pada tabelnya. Tab yang sedang dibuka dibaca dari `?jenjang=<kunci>`; bila kosong,
// tabnya mengikuti **jenjang lembaga** guru (`'kategori_jenjang'`, mis. MI → SD/MI) supaya guru
// langsung melihat media untuk jenjangnya sendiri, dan bila jenjangnya belum dipilih → tab pertama.
//
// ATURAN: baris yang jenjangnya KOSONG atau tidak dikenal tetap ditampilkan pada tab PERTAMA —
// jangan pernah membuat baris yang tidak tampil di tab mana pun (isian Super Admin bisa hilang
// dari pandangan tanpa sebab yang jelas).
// ================================================================================================

/** Daftar tab sebuah menu daftar aplikasi (kunci => label); [] bila menu tidak bertab. */
function daftar_apps_kategori(string $menu): array
{
    $k = daftar_apps_konfig($menu);
    return ($k !== null && is_array($k['kategori'] ?? null)) ? $k['kategori'] : [];
}

/** Kunci tab yang SAH dari sebuah nilai masukan ('' bila tidak sah). */
function daftar_apps_kategori_sah(string $menu, $nilai): string
{
    $n = trim((string) $nilai);
    $kat = daftar_apps_kategori($menu);
    return ($n !== '' && isset($kat[$n])) ? $n : '';
}

/** Label tab ('' bila menu tidak bertab / kuncinya tidak dikenal). */
function daftar_apps_kategori_label(string $menu, string $kunci): string
{
    return (string) (daftar_apps_kategori($menu)[$kunci] ?? '');
}

/** Tab milik SATU baris aplikasi (baris tanpa tab dianggap milik tab PERTAMA). */
function daftar_apps_kategori_baris(string $menu, ?array $app): string
{
    $kat = daftar_apps_kategori($menu);
    if (!$kat) {
        return '';
    }
    $sah = daftar_apps_kategori_sah($menu, $app['jenjang'] ?? '');
    return $sah !== '' ? $sah : (string) array_key_first($kat);
}

/** Tab yang sedang dibuka pada halaman menu ini ('' bila menu tidak bertab). */
function daftar_apps_kategori_aktif(string $menu): string
{
    $kat = daftar_apps_kategori($menu);
    if (!$kat) {
        return '';
    }
    $dariUrl = daftar_apps_kategori_sah($menu, $_GET['jenjang'] ?? '');
    if ($dariUrl !== '') {
        return $dariUrl;
    }
    $k = daftar_apps_konfig($menu);
    $peta = (array) ($k['kategori_jenjang'] ?? []);
    $ikutJenjang = daftar_apps_kategori_sah($menu, $peta[jenjang_sekolah()] ?? '');
    if ($ikutJenjang !== '') {
        return $ikutJenjang;
    }
    return (string) array_key_first($kat);
}

// ================================================================================================
// SUB TAB FASE di dalam tab jenjang (v1.112.0 / skema v72) — permintaan pemilik: *"Pada Tab SD/MI
// Tambahkan Fase A, B, C untuk memisah media itu difase apa. Begitu juga untuk Tab SMA/MA tambahkan
// Fase E, F. Kalau SMP/MTs hanya Fase D"*.
//
// Bentuknya SATU tingkat di bawah tab jenjang: baris pil fase muncul HANYA untuk tab yang punya fase
// (SD/MI · SMP/MTs · SMA/MA). Tab **Game Edukasi** tidak punya fase — isinya game, bukan media per
// fase. Susunannya dari konfigurasi menu (kunci `'kategori_fase'` → MEDIA_INTERAKTIF_FASE).
//
// ATURAN: media yang `fase`-nya KOSONG atau tidak dikenal **tetap tampil pada SETIAP fase** jenjangnya
// (`daftar_apps_fase_tampil()`), jadi media lama yang belum dipecah per fase tidak pernah hilang dari
// pandangan guru. Jangan mengubahnya menjadi "hanya tampil di fase pertama" tanpa bertanya pemilik.
// ================================================================================================

/** Peta fase sebuah menu (`jenjang => [fase => label]`); [] bila menu ini tidak berfase. */
function daftar_apps_fase_menu(string $menu): array
{
    $k = daftar_apps_konfig($menu);
    return ($k !== null && is_array($k['kategori_fase'] ?? null)) ? $k['kategori_fase'] : [];
}

/** Daftar fase pada SATU tab jenjang (kunci => label); [] bila tab itu tidak berfase. */
function daftar_apps_fase(string $menu, string $kat): array
{
    $peta = daftar_apps_fase_menu($menu);
    $isi = $peta[$kat] ?? [];
    return is_array($isi) ? $isi : [];
}

/** true bila menu ini punya fase pada salah satu tabnya (dipakai formulir Super Admin). */
function daftar_apps_berfase(string $menu): bool
{
    foreach (daftar_apps_fase_menu($menu) as $isi) {
        if (is_array($isi) && $isi) {
            return true;
        }
    }
    return false;
}

/** Kunci fase yang SAH untuk sebuah tab ('' bila kosong/tidak dikenal / tab itu tidak berfase). */
function daftar_apps_fase_sah(string $menu, string $kat, $nilai): string
{
    $n = strtolower(trim((string) $nilai));
    return ($n !== '' && isset(daftar_apps_fase($menu, $kat)[$n])) ? $n : '';
}

/** Label fase ('' bila tab tidak berfase / kuncinya tidak dikenal). */
function daftar_apps_fase_label(string $menu, string $kat, string $kunci): string
{
    return (string) (daftar_apps_fase($menu, $kat)[$kunci] ?? '');
}

/**
 * Fase milik SATU baris media pada tab jenjangnya ('' = media itu berlaku untuk SEMUA fase tab itu).
 *
 * Nilai `fase` yang tidak sah untuk jenjang baris itu (mis. Fase E pada baris SMP/MTs) diperlakukan
 * seperti kosong supaya media tetap tampil (tidak hilang) — bukan disembunyikan.
 */
function daftar_apps_fase_baris(string $menu, string $kat, ?array $app): string
{
    return daftar_apps_fase_sah($menu, $kat, $app['fase'] ?? '');
}

/** true bila media ini tampil pada fase yang sedang dibuka (media tanpa fase selalu tampil). */
function daftar_apps_fase_tampil(string $menu, string $kat, ?array $app, string $faseAktif): bool
{
    $f = daftar_apps_fase_baris($menu, $kat, $app);
    return $f === '' || $faseAktif === '' || $f === $faseAktif;
}

/** Fase yang sedang dibuka pada tab jenjang ini ('' bila tab itu tidak berfase). */
function daftar_apps_fase_aktif(string $menu, string $kat): string
{
    $daftar = daftar_apps_fase($menu, $kat);
    if (!$daftar) {
        return '';
    }
    $dariUrl = daftar_apps_fase_sah($menu, $kat, $_GET['fase'] ?? '');
    return $dariUrl !== '' ? $dariUrl : (string) array_key_first($daftar);
}

/**
 * true bila menu ini HANYA boleh dibuka akun guru (kunci `'hanya_guru'`; sekarang hanya menu
 * "Media Interaktif"). Super Admin DILEWATKAN: dialah pengisi daftarnya — tanpa pengecualian ini
 * tidak ada seorang pun yang dapat mengisi media untuk guru.
 */
function daftar_apps_hanya_guru(string $menu): bool
{
    $k = daftar_apps_konfig($menu);
    return $k !== null && !empty($k['hanya_guru']);
}

/** true bila pengguna yang sedang masuk boleh membuka menu ini menurut batasan perannya. */
function daftar_apps_peran_boleh(string $menu): bool
{
    if (!daftar_apps_hanya_guru($menu)) {
        return true;
    }
    if (is_super()) {
        return true;
    }
    $u = current_user();
    return $u !== null && (string) ($u['role'] ?? '') === 'guru';
}

/** Keterangan yang tampil saat menu khusus guru dibuka peran lain (dipakai tolak403()). */
function daftar_apps_peran_pesan(): string
{
    return 'Menu ini hanya tersedia untuk akun guru.';
}

/**
 * true bila menu ini punya TAB "Buatkan Media" (kunci `'permintaan_media'`; sekarang hanya menu
 * "Media Interaktif"). Permintaannya sendiri disimpan & ditampilkan oleh `inc/media_permintaan.php`.
 */
function daftar_apps_permintaan_media(string $menu): bool
{
    $k = daftar_apps_konfig($menu);
    return $k !== null && !empty($k['permintaan_media']);
}

/**
 * Apakah halaman ini sedang membuka TAB "Buatkan Media" (`?buatkan=1`)?
 *
 * Hanya untuk akun GURU — Super Admin tetap melihat halaman pengisian daftar media (dialah pengisi
 * media untuk guru) dan menindaklanjuti permintaannya lewat menu "Permintaan Media".
 */
function daftar_apps_mode_buatkan(string $menu): bool
{
    return daftar_apps_permintaan_media($menu)
        && !is_super()
        && (string) ($_GET['buatkan'] ?? '') !== '';
}

// ================================================================================================
// TEKS TOMBOL BUKA PADA KARTU (v1.107.0) — permintaan pemilik: *"Kata 'Buka Aplikasi' pada menu
// Media Interaktif jenjang MI/SD - SMA/MA ganti dengan 'Buka Media'. Sedangkan kata 'Buka Aplikasi'
// pada Game Edukasi ganti dengan 'Mainkan Game'"*.
// Karena itu teks tombol dapat disetel per MENU (`'buka_label'`) dan per TAB (`'buka_label_kategori'`,
// kunci = nilai `jenjang`). Modul lain tidak menyetelnya sehingga tetap berbunyi "Buka Aplikasi".
// JANGAN menulis teks tombol langsung di halaman/kartu — satu sumber di konfigurasi menu.
// ================================================================================================

/** Teks bawaan tombol buka pada kartu bila menunya tidak menyetel sendiri. */
const DAFTAR_APPS_BUKA_TEKS = 'Buka Aplikasi';

/**
 * Teks tombol buka pada kartu untuk sebuah menu (dan tab yang sedang dibuka, bila menu bertab).
 * Tab yang tidak punya teks sendiri memakai teks menu; menu tanpa setelan memakai teks bawaan.
 */
function daftar_apps_buka_label(?string $menu = null, ?string $katAktif = null): string
{
    $k = $menu !== null ? daftar_apps_konfig($menu) : null;
    $teks = trim((string) ($k['buka_label'] ?? ''));
    if ($teks === '') {
        $teks = DAFTAR_APPS_BUKA_TEKS;
    }
    $peta = (array) ($k['buka_label_kategori'] ?? []);
    $perTab = trim((string) ($peta[(string) $katAktif] ?? ''));
    return $perTab !== '' ? $perTab : $teks;
}

/**
 * BARIS PIL TAB sebuah menu bertab (sekarang hanya "Media Interaktif").
 *
 * Sejak v1.111.0 baris ini dipakai DUA tempat: halaman daftar media (mode biasa) dan TAB
 * "Buatkan Media" (`?buatkan=1`, lihat inc/media_permintaan.php) — karena itu markup-nya dipindahkan
 * ke fungsi ini supaya keduanya persis sama (termasuk peringatan `.apps-kat-ingat` di sisi kanan).
 *
 * ⚠️ Pil jenjang WAJIB tetap membawa `data-kat` dan `<span class="mut">angka</span>`: uji
 * `tests_eguru_media_interaktif.sh` & `check_eguru_media_interaktif_ui.sh` membaca keduanya untuk
 * memeriksa jumlah isi tiap tab. Pil "Buatkan Media" sengaja TIDAK memakai `data-kat`/`mut`.
 *
 * @param array<string,string> $jmlPerTab  jumlah aplikasi per tab
 * @param array<string,mixed>  $k          konfigurasi menu (dari daftar_apps_konfig())
 * @param array<string,string> $jmlPerFase jumlah media per fase pada TAB yang sedang dibuka
 *                                         (v1.112.0; kosong = tab itu tidak punya fase)
 */
function daftar_apps_tab_bar(string $menu, string $berkasDasar, string $katAktif,
                             array $jmlPerTab, bool $super, array $k,
                             array $jmlPerFase = []): void
{
    $kategori = daftar_apps_kategori($menu);
    if (!$kategori) {
        return;
    }
    $ingatKat = trim((string) ($k['ingat_kategori'] ?? ''));
    $buatkan = daftar_apps_permintaan_media($menu) && !$super;
    $buatkanAktif = $buatkan && (string) ($_GET['buatkan'] ?? '') !== '';
    ?>
    <div class="apps-kat-baris">
      <div class="chip-list warna apps-kat" id="kat-<?= e($menu) ?>">
        <?php foreach ($kategori as $katKunci => $katLabel): ?>
          <a class="chip <?= !$buatkanAktif && $katKunci === $katAktif ? 'on' : '' ?>"
             href="<?= e(daftar_apps_url($berkasDasar, ['jenjang' => (string) $katKunci])) ?>#<?= e($k['form_id']) ?>"
             data-kat="<?= e((string) $katKunci) ?>">
            <?= e((string) $katLabel) ?>
            <span class="mut"><?= (int) ($jmlPerTab[$katKunci] ?? 0) ?></span>
          </a>
        <?php endforeach; ?>
        <?php // TAB "BUATKAN MEDIA" (v1.111.0) — pil KE-5, tepat setelah "Game Edukasi" (posisi yang
              // ditandai pemilik pada tangkapan layar). Hanya untuk akun guru; Super Admin mengelola
              // permintaannya pada menu "Permintaan Media". ?>
        <?php if ($buatkan): ?>
          <a class="chip chip-buatkan <?= $buatkanAktif ? 'on' : '' ?>"
             href="<?= e(daftar_apps_url($berkasDasar, ['buatkan' => 1])) ?>#cardBuatkanMedia"
             id="pilBuatkanMedia">
            <?= ikon('plus', 14) ?>Buatkan Media
          </a>
        <?php endif; ?>
      </div>
      <?php if ($ingatKat !== ''): ?>
        <div class="apps-kat-ingat" id="ingat-<?= e($menu) ?>" role="note">
          <?= ikon('alert', 15) ?><span class="apps-kat-ingat-teks"><?= e($ingatKat) ?></span>
        </div>
      <?php endif; ?>
    </div>
    <?php // SUB TAB FASE (v1.112.0) — baris pil KEDUA di bawah baris jenjang, hanya untuk tab yang
          // punya fase (SD/MI · SMP/MTs · SMA/MA). Tidak dicetak pada mode "Buatkan Media" (permintaan
          // media tidak terikat fase) dan pada tab Game Edukasi. Tautan biasa + mempertahankan
          // `?jenjang=` supaya tetap bekerja tanpa JavaScript. ?>
    <?php $daftarFase = $buatkanAktif ? [] : daftar_apps_fase($menu, $katAktif);
          $faseAktif = $daftarFase ? daftar_apps_fase_aktif($menu, $katAktif) : ''; ?>
    <?php if ($daftarFase): ?>
      <div class="chip-list apps-fase" id="fase-<?= e($menu) ?>">
        <?php foreach ($daftarFase as $faseKunci => $faseLabel): ?>
          <a class="chip<?= $faseKunci === $faseAktif ? ' on' : '' ?>"
             href="<?= e(daftar_apps_url($berkasDasar,
                    ['jenjang' => (string) $katAktif, 'fase' => (string) $faseKunci])) ?>#<?= e($k['form_id']) ?>"
             data-fase="<?= e((string) $faseKunci) ?>">
            <?= e((string) $faseLabel) ?>
            <span class="mut"><?= (int) ($jmlPerFase[$faseKunci] ?? 0) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($buatkanAktif): ?>
      <p class="apps-kat-ket">Anda sedang membuka tab <b>Buatkan Media</b>.
        Isi <b>Nama Guru</b>, <b>Asal <?= e(istilah_sekolah('Sekolah')) ?></b>, dan <b>Judul Media</b>
        yang ingin dibuatkan — permintaan langsung masuk ke pengelola aplikasi (Super Admin), dan
        <b>statusnya</b> dapat Anda pantau di bawah.</p>
    <?php elseif ($daftarFase): ?>
      <p class="apps-kat-ket">Pilih jenjang, lalu <b>fase</b> untuk melihat media interaktifnya.
        Tab <b><?= e((string) ($kategori[$katAktif] ?? '')) ?></b> &middot;
        <b><?= e(daftar_apps_fase_label($menu, $katAktif, $faseAktif)) ?></b> sedang dibuka<?= $super
            ? ' — media baru yang Anda tambahkan masuk ke fase ini (bisa diubah pada pilihan <b>Fase</b> di formulir).' : '.' ?></p>
    <?php else: ?>
      <p class="apps-kat-ket">Pilih jenjang untuk melihat media interaktifnya.
        Tab <b><?= e((string) ($kategori[$katAktif] ?? '')) ?></b> sedang dibuka<?= $super
            ? ' — aplikasi baru yang Anda tambahkan masuk ke tab ini (bisa diubah pada pilihan <b>Jenjang</b> di formulir).' : '.' ?></p>
    <?php endif; ?>
    <?php
}

// ================================================================================================
// POPUP KUTIPAN PEMBUKA — SEKALI per sesi login, dan TIDAK untuk Super Admin (v1.105.2)
// ------------------------------------------------------------------------------------------------
// Permintaan pemilik: *"Popup hanya muncul satu kali saja setiap guru mengklik Menu Interaktif.
// Tidak perlu ditampilkan di super admin ketika akan mengisi"*.
//
// Aturan (SATU tempat — jangan mengulang pemeriksaannya di halaman/skrip):
//  • hanya untuk menu yang konfigurasinya memuat `'popup_awal'` (sekarang: Media Interaktif);
//  • **Super Admin TIDAK PERNAH** melihatnya — dialah pengisi daftar media, popup hanya menghalangi;
//  • akun GURU melihatnya **SEKALI per sesi login**. Penanda `$_SESSION['popup_awal_lihat'][<menu>]`
//    dipasang setelah popup dicetak; sesi dikosongkan `logout.php` (`$_SESSION = []` +
//    `session_destroy()`), jadi guru melihatnya lagi setelah keluar–masuk kembali. Ini pola yang sama
//    dengan penanda popup "Selamat datang" (`$_SESSION['sambutan_tampil']`) — bukan batas waktu/hari.
//  • Tamu (belum masuk) tidak pernah — halamannya sendiri sudah dialihkan ke halaman masuk.
// ================================================================================================

/** Konfigurasi popup pembuka sebuah menu (`null` bila menu itu tidak punya popup). */
function popup_awal_konfig(string $menu): ?array
{
    $k = daftar_apps_konfig($menu);
    $p = $k['popup_awal'] ?? null;
    return is_array($p) ? $p : null;
}

/** true bila popup pembuka menu ini harus dicetak SEKARANG (lihat aturan di blok komentar atas). */
function popup_awal_perlu_tampil(string $menu): bool
{
    if (popup_awal_konfig($menu) === null) {
        return false;
    }
    if (is_super()) {
        return false;    // Super Admin = pengisi daftar, jangan dihalangi popup
    }
    if (current_user() === null) {
        return false;
    }
    return empty($_SESSION['popup_awal_lihat'][$menu]);
}

/** Tandai popup pembuka menu ini SUDAH dilihat pada sesi ini (dipanggil setelah dicetak). */
function popup_awal_selesai(string $menu): void
{
    if (!isset($_SESSION['popup_awal_lihat']) || !is_array($_SESSION['popup_awal_lihat'])) {
        $_SESSION['popup_awal_lihat'] = [];
    }
    $_SESSION['popup_awal_lihat'][$menu] = 1;
}


/**
 * Inisial nama aplikasi untuk lencana pada kartu (maks. 2 huruf).
 * Dipisah pada spasi DAN tanda hubung supaya "e-Prestasi" menjadi "eP" (bukan "E").
 */
function apps_inisial(string $nama): string
{
    $inisial = '';
    foreach (preg_split('/[\s\-_]+/u', trim($nama)) ?: [] as $kata) {
        if ($kata !== '' && strlen($inisial) < 2 && preg_match('/^[A-Za-z0-9]/', $kata) === 1) {
            $inisial .= strtoupper(substr($kata, 0, 1));
        }
    }
    return $inisial !== '' ? $inisial : 'AP';
}

/**
 * PETA IKON KARTU APLIKASI — "ikon yang sesuai dengan nama aplikasi" (v1.75.0).
 *
 * PERMINTAAN PEMILIK: *"Pada menu smart teacher isinya tambahkan ikon yang sesuai dengan nama
 * aplikasi (yang saya tandai warna hitam)"* — kotak huruf awal (CM, AP, RC, …) diganti IKON yang
 * maknanya cocok dengan nama aplikasinya.
 *
 * Cara kerjanya: nama aplikasi (dibuat Super Admin) dicocokkan dengan daftar KATA KUNCI di bawah;
 * kata kunci pertama yang cocok menentukan ikonnya (lihat `ikon()` di inc/layout.php). Pencocokan
 * memakai batas kata sehingga "pai" tidak cocok dengan "ca-PAI-an" dan sebaliknya.
 *
 * ATURAN:
 *  - Urutan PENTING (yang lebih khusus lebih dulu). Contoh: "Analisis Pekan Efektif" cocok pada
 *    "pekan" (kalender) sebelum "analisis" (grafik); "CP Mapel PAI & Bahasa Arab" cocok pada "pai"
 *    (masjid) sebelum "cp" (buku).
 *  - Nama aplikasi yang TIDAK cocok kata kunci apa pun tetap memakai LENCANA INISIAL seperti
 *    sebelumnya (`apps_inisial()`) — jadi tidak pernah ada kartu tanpa penanda.
 *  - Menambah ikon baru cukup: tambahkan satu baris di sini + pastikan nama ikonnya ada pada `ikon()`.
 *    Tidak perlu perubahan basis data (tidak ada kolom baru) — seluruh aplikasi lama ikut berikon
 *    otomatis tanpa disunting satu per satu.
 *
 * @return array<string, string> kata kunci (huruf kecil) => nama ikon pada ikon()
 */
function apps_ikon_peta(): array
{
    return [
        // ---- foto / kamera
        'studio foto'  => 'camera',
        'pas foto'     => 'camera',
        'foto'         => 'camera',
        'photo'        => 'camera',
        'kamera'       => 'camera',
        // ---- keagamaan (PAI & Bahasa Arab)
        'pai'          => 'mosque',
        'arab'         => 'mosque',
        "qur'an"       => 'mosque',
        'quran'        => 'mosque',
        'mengaji'      => 'mosque',
        // ---- buku / kitab / perpustakaan
        'maktabah'     => 'book',
        'kitab'        => 'book',
        'buku'         => 'book',
        'perpustaka'   => 'book',
        // ---- audio (teks → suara)
        'suara'        => 'mic',
        'voice'        => 'mic',
        'audio'        => 'mic',
        'tts'          => 'mic',
        // ---- pengubah/konversi berkas
        'konversi'     => 'switch',
        'convert'      => 'switch',
        'kompres'      => 'switch',
        // ---- asesmen / soal / ujian
        'soal'         => 'clipboard',
        'ujian'        => 'clipboard',
        'asesmen'      => 'clipboard',
        'kuis'         => 'clipboard',
        'cbt'          => 'clipboard',
        // ---- perangkat ajar & dokumen
        'rpp'          => 'doc',
        'modul ajar'   => 'doc',
        'perangkat'    => 'doc',
        'dokumen'      => 'doc',
        // ---- kumpulan materi
        'materi'       => 'layers',
        'rubrik'       => 'layers',
        'kktp'         => 'layers',
        'bahan ajar'   => 'layers',
        // ---- kalender / pekan efektif / jadwal
        'pekan'        => 'calendar',
        'kalender'     => 'calendar',
        'kaldik'       => 'calendar',
        'jadwal'       => 'calendar',
        // ---- kurikulum & capaian pembelajaran
        'cp'           => 'book',
        'capaian'      => 'book',
        'atp'          => 'book',
        'kurikulum'    => 'book',
        // ---- laporan & analisis
        'analisis'     => 'chart',
        'grafik'       => 'chart',
        'rekap'        => 'chart',
        'laporan'      => 'report',
        // ---- media
        'video'        => 'slider',
        'media'        => 'slider',
        // ---- web / portal
        'portal'       => 'globe',
        'web'          => 'globe',
        // ---- komunikasi
        'whatsapp'     => 'whatsapp',
        'obrolan'      => 'chat',
        'chat'         => 'chat',
        // ---- permainan / latihan
        'permainan'    => 'star',
        'game'         => 'star',
        'latihan'      => 'star',
        // ---- pengguna aplikasi lain
        'teacher'      => 'apps',
        'guru'         => 'users',
        'siswa'        => 'student',
        'sekolah'      => 'school',
        // ---- data (paling akhir: nama yang menyebut guru/siswa/sekolah sudah tercocokkan di atas)
        'data'         => 'folder',
    ];
}

/**
 * Nama ikon yang sesuai untuk sebuah nama aplikasi (null = tidak ada yang cocok → pakai inisial).
 * Lihat `apps_ikon_peta()` untuk daftar kata kuncinya.
 */
function apps_ikon(string $nama): ?string
{
    $nama = trim($nama);
    if ($nama === '') {
        return null;
    }
    $nama = strtolower($nama);
    foreach (apps_ikon_peta() as $kata => $ikon) {
        // Batas kata: "pai" TIDAK cocok pada "capaian", "cp" TIDAK cocok pada "cbt".
        if (preg_match('/(?<![a-z0-9])' . preg_quote((string) $kata, '/') . '(?![a-z0-9])/u', $nama) === 1) {
            return $ikon;
        }
    }
    return null;
}

/**
 * Tukar posisi satu baris dengan tetangganya pada daftar terurut `urutan, nama`
 * (tombol naik/turun), lalu tulis ulang nomor urut 10, 20, 30, … supaya tetap rapi.
 */
function daftar_apps_geser(string $menu, int $id, int $arah): bool
{
    $tabel = daftar_apps_tabel($menu);
    $daftar = daftar_apps_list($menu, false);
    $pos = -1;
    foreach ($daftar as $i => $r) {
        if ((int) $r['id'] === $id) {
            $pos = $i;
            break;
        }
    }
    $tujuan = $arah < 0 ? $pos - 1 : $pos + 1;
    if ($pos < 0 || $tujuan < 0 || $tujuan >= count($daftar)) {
        return false;
    }
    $tukar = $daftar[$pos];
    $daftar[$pos] = $daftar[$tujuan];
    $daftar[$tujuan] = $tukar;
    db()->exec('BEGIN IMMEDIATE');
    try {
        $upd = db()->prepare('UPDATE ' . $tabel . ' SET urutan = ? WHERE id = ?');
        $n = 0;
        foreach ($daftar as $r) {
            $n += 10;
            $upd->execute([$n, (int) $r['id']]);
        }
        db()->exec('COMMIT');
    } catch (Throwable $ex) {
        db()->exec('ROLLBACK');
        throw $ex;
    }
    return true;
}

/**
 * ============================================================================
 *  PENCATATAN PENGGUNAAN APLIKASI (skema v59) — GRAFIK "Aplikasi yang Sering Digunakan"
 * ============================================================================
 * Permintaan pemilik: *"Pada halaman smart teacher tambahkan Grafik Aplikasi yang Sering
 * Digunakan. Data grafik diambil dari data seluruh pengguna yang menggunakan … Tujuannya agar
 * pengguna tau aplikasi mana yang sering digunakan."*
 *
 * Pilihan pemilik (jangan diubah tanpa bertanya):
 *  1. grafik diletakkan di **halaman Smart Teacher** (di atas daftar kartu aplikasi);
 *  2. yang dihitung = **klik tombol "Buka Aplikasi"** pada SEMUA menu daftar aplikasi
 *     (Smart Teacher, Aplikasi Sagu, Ujian Online, Hasil Supervisi, Bendahara Bos/Madrasah);
 *  3. rentangnya = **seluruh data yang tercatat** (tanpa batas waktu / 30 hari).
 *
 * Karena itu tombol "Buka Aplikasi" TIDAK lagi menunjuk langsung ke tautan aplikasinya, melainkan
 * ke `app_buka.php` (mencatat klik lebih dulu, lalu mengalihkan ke tautan aslinya). Pencatatan
 * dilakukan di SERVER, jadi tetap bekerja tanpa JavaScript dan tidak bisa dilewati dari tampilan.
 */

/** Alamat tombol "Buka Aplikasi" (pencatat klik + pengalihan). `$berkas` = halaman asal untuk kembali. */
function app_buka_url(string $menu, int $appId, string $berkas = ''): string
{
    $q = ['m' => $menu, 'id' => $appId];
    if ($berkas !== '') {
        $q['b'] = $berkas;
    }
    return 'app_buka.php?' . http_build_query($q);
}

/**
 * Catat SATU klik "Buka Aplikasi".
 *
 * Nama aplikasi, nama pengguna, dan nama sekolah disimpan sebagai SNAPSHOT supaya rekapnya tetap
 * terbaca walau aplikasi/akun/sekolahnya kelak dihapus. Kegagalan mencatat TIDAK BOLEH menggagalkan
 * pengalihan pengguna (pola yang sama dengan log aktivitas) — karena itu seluruh isinya try/catch.
 */
function app_klik_catat(string $menu, array $app): void
{
    try {
        $u = current_user();
        $s = current_sekolah();
        $nama = trim((string) ($app['nama'] ?? ''));
        q('INSERT INTO app_klik
              (menu, app_id, nama, sekolah_id, sekolah_nama, user_id, user_nama, role, waktu)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $menu,
            (int) ($app['id'] ?? 0),
            $nama !== '' ? $nama : '(tanpa nama)',
            $s ? (int) $s['id'] : null,
            $s ? nama_sekolah_tampil($s) : null,
            $u ? (int) $u['id'] : null,
            $u ? (string) $u['nama'] : null,
            $u ? (string) $u['role'] : null,
            date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $ex) {
        error_log('e-GURU app_klik: gagal mencatat penggunaan aplikasi — ' . $ex->getMessage());
    }
}

/**
 * Rekap aplikasi yang paling sering dibuka — SELURUH menu, SELURUH sekolah/madrasah, SEMUA data
 * yang tercatat (permintaan pemilik: "total keseluruhan", bukan 30/90 hari).
 *
 * Pengelompokan memakai **menu + nama aplikasi** (tanpa membedakan huruf besar/kecil & spasi di
 * tepi). Dua aplikasi pada menu berbeda tetap dihitung TERPISAH karena keduanya memang dua entri
 * kartu yang berbeda (masing-masing punya tautannya sendiri), dan nama yang ditampilkan adalah
 * ejaan dari klik TERAKHIR pada menu itu sehingga selalu sama dengan yang tertulis di kartunya.
 * Aplikasi yang dihapus Super Admin tetap muncul karena namanya tersimpan sebagai snapshot.
 *
 * @return array<int, array{nama:string, jml:int, menu:string}>
 */
function app_klik_ringkas(int $batas = 8): array
{
    $batas = max(1, min(50, $batas));
    $baris = all('SELECT a1.menu AS menu,
                         (SELECT TRIM(a2.nama) FROM app_klik a2
                           WHERE a2.menu = a1.menu
                             AND LOWER(TRIM(a2.nama)) = LOWER(TRIM(a1.nama))
                           ORDER BY a2.id DESC LIMIT 1) AS nama,
                         COUNT(*) AS jml
                    FROM app_klik a1
                   GROUP BY a1.menu, LOWER(TRIM(a1.nama))
                   ORDER BY jml DESC, nama COLLATE NOCASE ASC
                   LIMIT ' . $batas);
    $hasil = [];
    foreach ($baris as $r) {
        $hasil[] = [
            'nama' => (string) $r['nama'],
            'jml'  => (int) $r['jml'],
            'menu' => (string) $r['menu'],
        ];
    }
    return $hasil;
}

/** Jumlah SELURUH klik "Buka Aplikasi" yang tercatat (semua menu & semua sekolah). */
function app_klik_total(): int
{
    return (int) val('SELECT COUNT(*) FROM app_klik', [], 0);
}

/** Jumlah aplikasi BERBEDA yang pernah dibuka (dasar chip "N aplikasi" pada kartu grafik). */
function app_klik_app_jumlah(): int
{
    return (int) val('SELECT COUNT(*) FROM (SELECT 1 FROM app_klik GROUP BY menu, LOWER(TRIM(nama)))', [], 0);
}

/**
 * REKAP PENGGUNAAN MEDIA INTERAKTIF (media pembelajaran & game edukasi) — v1.108.0.
 *
 * Permintaan pemilik: *"tambahkan data total media pembelajaran dan game edukasi dibuka atau
 * digunakan oleh seluruh pengguna. Data per jenjang untuk media. tujuannya untuk mengetahui media
 * atau game mana yang paling sering dibuka/digunakan"* (kotak merah pada kartu "Aktivitas Aplikasi"
 * dasbor Super Admin lintas sekolah).
 *
 * Sumbernya sama dengan grafik "Aplikasi yang Sering Digunakan": tabel `app_klik` (SELURUH
 * pengguna, SELURUH sekolah/madrasah, seluruh data yang tercatat — tanpa batas waktu).
 * Pengelompokan per jenjang memakai `media_interaktif.jenjang` baris yang diKLIK (`app_id`), dengan
 * cadangan pencocokan NAMA untuk baris lama. Baris yang medianya sudah dihapus tetap dihitung pada
 * `total`, tetapi tidak masuk jenjang mana pun (tidak ada data jenjang yang bisa ditebak).
 *
 * @return array{total:int, per_jenjang:array<string,int>, teratas:array<int,array{nama:string,jml:int,jenjang:string}>}
 */
function app_klik_media_rekap(int $batas = 5): array
{
    $batas = max(1, min(20, $batas));
    $hasil = ['total' => 0, 'per_jenjang' => [], 'teratas' => []];
    foreach (array_keys(daftar_apps_kategori('media_interaktif')) as $kunci) {
        $hasil['per_jenjang'][(string) $kunci] = 0;
    }
    $hasil['total'] = (int) val("SELECT COUNT(*) FROM app_klik WHERE menu = 'media_interaktif'", [], 0);
    if ($hasil['total'] === 0) {
        return $hasil;
    }

    // Per jenjang: media yang diklik (app_id) → jenjangnya; cadangan lewat nama bila app_id sudah
    // tidak ada (mis. baris lama/media yang pernah dihapus lalu dibuat ulang).
    foreach (all("SELECT COALESCE(NULLIF(TRIM(m.jenjang), ''), NULLIF(TRIM(mn.jenjang), ''), '') AS jenjang,
                         COUNT(*) AS jml
                    FROM app_klik a
                    LEFT JOIN media_interaktif m ON m.id = a.app_id
                    LEFT JOIN media_interaktif mn ON mn.id =
                         (SELECT x.id FROM media_interaktif x
                           WHERE LOWER(TRIM(x.nama)) = LOWER(TRIM(a.nama))
                           ORDER BY x.id DESC LIMIT 1)
                   WHERE a.menu = 'media_interaktif'
                   GROUP BY 1") as $r) {
        $j = (string) $r['jenjang'];
        if (isset($hasil['per_jenjang'][$j])) {
            $hasil['per_jenjang'][$j] += (int) $r['jml'];
        }
    }

    // Paling sering dibuka: satu baris per MEDIA (app_id), namanya ejaan klik TERAKHIR.
    foreach (all("SELECT a1.app_id AS app_id,
                         COUNT(*) AS jml,
                         (SELECT TRIM(a2.nama) FROM app_klik a2
                           WHERE a2.menu = 'media_interaktif' AND a2.app_id = a1.app_id
                           ORDER BY a2.id DESC LIMIT 1) AS nama,
                         (SELECT TRIM(m.jenjang) FROM media_interaktif m WHERE m.id = a1.app_id) AS jenjang
                    FROM app_klik a1
                   WHERE a1.menu = 'media_interaktif'
                   GROUP BY a1.app_id
                   ORDER BY jml DESC, nama COLLATE NOCASE ASC
                   LIMIT " . $batas) as $r) {
        $hasil['teratas'][] = [
            'nama'    => (string) ($r['nama'] ?? ''),
            'jml'     => (int) $r['jml'],
            'jenjang' => (string) ($r['jenjang'] ?? ''),
        ];
    }
    return $hasil;
}

/** Label menu daftar aplikasi dari kuncinya — untuk keterangan pada grafik. */
function app_klik_menu_label(string $menu): string
{
    $k = daftar_apps_konfig($menu);
    return $k !== null ? (string) ($k['menu'] ?? $menu) : $menu;
}

/**
 * Jalankan satu halaman menu daftar aplikasi: tangani POST (Super Admin saja) lalu render halaman.
 * Halaman pemanggil cukup berupa berkas tipis, mis.:
 *
 *     require_once __DIR__ . '/inc/init.php';
 *     require_once __DIR__ . '/inc/daftar_apps.php';
 *     daftar_apps_halaman('smart_teacher');
 *
 * @param string     $menu  kunci menu pada `daftar_apps_konfig()`
 * @param array|null $tugas baris menu TUGAS TAMBAHAN yang isinya memakai daftar ini (mis. menu
 *                          "Bendahara Bos"). Bila diisi, judul halaman & semua tautan memakai menu
 *                          Tugas Tambahan itu (`tugas.php?kode=…`) — bukan berkas sendiri.
 */
function daftar_apps_halaman(string $menu, ?array $tugas = null): void
{
    $k = daftar_apps_konfig($menu);
    if ($k === null) {
        tolak403('Menu daftar aplikasi tidak dikenal.');
    }
    $tabel = $k['tabel'];
    $namaMenu = (string) ($k['menu'] ?? 'Daftar Aplikasi');
    // "Berkas" = alamat halaman ini. Untuk menu yang isinya dipindahkan ke menu Tugas Tambahan,
    // seluruh tautan & pengalihan harus menunjuk alamat menu itu (termasuk kode menunya).
    if ($tugas !== null) {
        $berkas = 'tugas.php?kode=' . urlencode((string) ($tugas['kode'] ?? ''));
        $namaMenu = (string) ($tugas['nama'] ?? $namaMenu);
    } else {
        $berkas = (string) ($k['berkas'] ?? '');
        if ($berkas === '') {
            tolak403('Menu daftar aplikasi ini hanya tersedia lewat menu Tugas Tambahan.');
        }
    }

    // TAB KATEGORI (v1.105.0) — menu bertab (sekarang hanya "Media Interaktif"). Semua tautan &
    // pengalihan halaman ini membawa `?jenjang=<tab aktif>` supaya menyimpan/mengubah aplikasi pada
    // tab SMP/MTs misalnya tidak melompat kembali ke tab pertama.
    $kategori = daftar_apps_kategori($menu);
    $katAktif = $kategori ? daftar_apps_kategori_aktif($menu) : '';
    $berkasDasar = $berkas;   // alamat TANPA tab — dipakai tautan pil tab
    if ($kategori) {
        $berkas = daftar_apps_url($berkasDasar, ['jenjang' => $katAktif]);
    }

    require_login(); // daftar GLOBAL: tidak memerlukan konteks sekolah
    $super = is_super();

    // MENU KHUSUS AKUN GURU (v1.105.0) — "Media Interaktif" hanya untuk guru; kepala & operator
    // ditolak. Aturan satu tempat: daftar_apps_peran_boleh() (Super Admin selalu boleh).
    if (!daftar_apps_peran_boleh($menu)) {
        tolak403(daftar_apps_peran_pesan());
    }

    // Menu yang DIBATASI PER SEKOLAH: hanya boleh dibuka dari sekolah yang dicentang Super Admin
    // (Super Admin sendiri selalu boleh — di halaman inilah ia menyetel daftar sekolahnya).
    if (!daftar_apps_tampil($menu) && !daftar_apps_per_aplikasi($menu)) {
        // Menu yang dibatasi PER SEKOLAH: sekolah di luar daftar benar-benar ditolak.
        tolak403('Menu ' . $namaMenu . ' tidak tersedia untuk sekolah/madrasah ini. '
            . 'Hubungi Admin aplikasi bila menu ini memang dibutuhkan.');
    }
    // Menu PER APLIKASI (Bendahara) sengaja TIDAK ditolak walaupun menunya disembunyikan dari
    // sidebar: halaman tetap dirender dengan keterangan "belum punya akses" (permintaan pemilik),
    // dan tautan aplikasinya tetap tidak dibuka (kartu terkunci).

    // ------------------------------------ MENU DINONAKTIFKAN SEMENTARA oleh Super Admin (v1.81.0)
    // Permintaan pemilik: *"Ketika dinonaktifkan oleh Super Admin maka menu tersebut jika di klik
    // oleh pengguna/guru akan muncul peringatan 'Server AI Smart Teacher lagi mengalami gangguan.
    // Harap coba lagi nanti. Terima Kasih'"*. Halamannya sengaja TETAP 200 (bukan 403) dan menunya
    // tetap tampil di sidebar supaya peringatan itu benar-benar terbaca oleh pengguna. Super Admin
    // DILEWATKAN: ia harus tetap melihat halaman penuh + kartu pengaktif (kalau tidak, menu yang
    // sudah dinonaktifkan tidak akan pernah bisa dihidupkan lagi).
    if (!$super && !daftar_apps_aktif($menu)) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            tolak403('Menu ' . $namaMenu . ' sedang dinonaktifkan oleh pengelola aplikasi.');
        }
        page_top($namaMenu, $tugas !== null ? 'Tugas Tambahan' : '');
        ?>
<div class="card" id="cardMenuGangguan">
  <div class="card-head">
    <div>
      <h2><?= ikon('alert', 16) ?> <?= e($namaMenu) ?></h2>
    </div>
    <div class="flex"><span class="pill pill-gangguan" id="pilGangguan">Gangguan</span></div>
  </div>
  <div class="alert alert-err" id="pesanGangguan">
    <?= ikon('alert', 16) ?>
    <span><b><?= e(daftar_apps_gangguan_pesan($menu)) ?></b></span>
  </div>
</div>
        <?php
        page_bottom();
        return;
    }

    // ------------------------------------------------- HANYA LEMBAGA YANG SUDAH AKTIVASI (v1.78.0)
    // Permintaan pemilik: *"Pada Menu Ujian Online dan Menu Smart Teacher hanya bisa diakses oleh
    // lembaga yang telah melakukan aktifasi. Bagi yang belum, menu ini belum bisa diakses dengan
    // keterangan: Menu ini hanya bisa diakses oleh lembaga yang sudah melakukan aktifasi."*
    // Halamannya sengaja TETAP dapat dibuka (menu sidebar juga tetap tampil) supaya keterangannya
    // terbaca — yang tidak ditampilkan hanyalah daftar aplikasinya. Aturannya satu tempat:
    // daftar_apps_aktivasi_boleh().
    if (!daftar_apps_aktivasi_boleh($menu)) {
        // POST dari peran selain Super Admin tetap ditolak 403 (bukan dirender 200) supaya
        // penjagaan hak akses tidak berubah hanya karena lembaga belum beraktivasi.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            tolak403('Daftar ' . $namaMenu . ' hanya dapat diubah oleh Super Admin.');
        }
        $waAdmin = aktivasi_wa();
        page_top($namaMenu, $tugas !== null ? 'Tugas Tambahan' : '');
        ?>
<div class="card" id="cardPerluAktivasi">
  <div class="card-head">
    <div>
      <h2><?= ikon('lock', 16) ?> <?= e($namaMenu) ?></h2>
      <p>Daftar aplikasi ini hanya tersedia bagi lembaga yang <b>sudah melakukan aktivasi</b>.</p>
    </div>
    <div class="flex"><span class="pill pill-warn">Aktivasi diperlukan</span></div>
  </div>
  <div class="alert alert-warn" id="pesanPerluAktivasi">
    <?= ikon('lock', 16) ?>
    <span><b><?= e(daftar_apps_aktivasi_pesan()) ?></b>
      Lembaga ini masih memakai <b>masa uji coba</b> / belum pernah memasukkan kode aktivasi, sehingga
      daftar aplikasi <b><?= e($namaMenu) ?></b> belum dapat dibuka. Menu ini <b>terbuka otomatis</b>
      begitu aktivasi lembaga berhasil — tidak ada data yang perlu diisi ulang.</span>
  </div>
  <div class="flex" style="margin-top:16px">
    <?php if (boleh_aktivasi()): ?>
      <a class="btn btn-primary" href="aktivasi.php"><?= ikon('key', 16) ?> Buka Status &amp; Aktivasi</a>
      <span class="mut xs">Masukkan kode aktivasi yang dikirim HD Admin pada halaman itu
        <?= $waAdmin !== '' ? '(WA ' . e($waAdmin) . ')' : '' ?>.</span>
    <?php else: ?>
      <span class="mut xs">Kode aktivasi dimasukkan oleh <b>Kepala Sekolah/Madrasah</b> atau
        <b>Admin Operator</b> melalui menu <b>Status &amp; Aktivasi</b><?= $waAdmin !== ''
            ? ' — kodenya diminta kepada HD Admin (WA ' . e($waAdmin) . ')' : '' ?>.</span>
    <?php endif; ?>
  </div>
</div>
        <?php
        page_bottom();
        return;
    }

    // ---------------------------------------- PERMINTAAN MEDIA: kiriman dari akun GURU (v1.111.0)
    // Tab "Buatkan Media" mengirim `aksi=minta_media`. Ditangani SEBELUM blok pengubahan Super Admin
    // (yang menolak seluruh POST dari peran lain) karena justru akun guru-lah yang mengirimnya.
    if (daftar_apps_permintaan_media($menu) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && (string) ($_POST['aksi'] ?? '') === 'minta_media') {
        if (!is_guru()) {
            tolak403('Permintaan media hanya dapat dikirim dari akun guru.');
        }
        csrf_guard();
        $hasil = media_permintaan_simpan([
            'nama_guru'    => (string) ($_POST['nama_guru'] ?? ''),
            'asal_sekolah' => (string) ($_POST['asal_sekolah'] ?? ''),
            'judul'        => (string) ($_POST['judul'] ?? ''),
            'mata_pelajaran' => (string) ($_POST['mata_pelajaran'] ?? ''),
            'kelas'        => (string) ($_POST['kelas'] ?? ''),
            'fase'         => (string) ($_POST['fase'] ?? ''),
        ]);
        flash((string) $hasil['pesan'], !empty($hasil['ok']) ? 'ok' : 'err');
        redirect(media_permintaan_url($menu) . '#cardPermintaanSaya');
    }

    // ---------------------------------------------------------------- pengubahan (Super Admin saja)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$super) {
            tolak403('Daftar ' . $namaMenu . ' hanya dapat diubah oleh Super Admin.');
        }
        csrf_guard();
        $aksi = (string) ($_POST['aksi'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $s = daftar_apps_by_id($menu, $id);

        if ($aksi === 'simpan') {
            $nama = trim((string) ($_POST['nama'] ?? ''));
            $link = trim((string) ($_POST['link'] ?? ''));
            $ket = trim((string) ($_POST['keterangan'] ?? ''));
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            $now = date('Y-m-d H:i:s');
            // Penanda bahwa daftar sekolah/madrasah pengakses ikut dikirim (hanya menu per-aplikasi).
            // Tanpa penanda ini, penyetelan sekolah TIDAK disentuh (formulir/skrip gaya lama aman).
            $aturSekolah = daftar_apps_per_aplikasi($menu) && array_key_exists('app_sekolah_atur', $_POST);
            // Penanda yang sama untuk LENCANA "Bonus": hanya diubah bila bagian itu benar-benar dikirim,
            // sehingga kiriman gaya lama (tanpa penanda) tidak menghapus tanda baru yang sudah ada.
            $aturBaru = daftar_apps_label_baru($menu) && array_key_exists('app_baru_atur', $_POST);
            $baru = $aturBaru && isset($_POST['baru']);

            $galat = [];
            if ($nama === '') {
                $galat[] = 'Nama aplikasi wajib diisi.';
            }
            if ($link === '') {
                $galat[] = 'Link aplikasi wajib diisi.';
            } elseif (tautan_aman($link) === '') {
                $galat[] = 'Link aplikasi harus dimulai dengan http:// atau https://.';
            }
            // TAB JENJANG (menu "Media Interaktif", v1.105.0): wajib salah satu tab yang terdaftar
            // supaya barisnya tidak pernah "hilang" dari semua tab.
            if ($kategori && daftar_apps_kategori_sah($menu, $_POST['jenjang'] ?? '') === '') {
                $galat[] = 'Tab jenjang wajib dipilih.';
            }
            // SUB TAB FASE (v1.112.0): boleh KOSONG (= media berlaku untuk semua fase jenjangnya),
            // tetapi bila diisi harus fase milik jenjang yang dipilih (Fase A–C hanya untuk SD/MI,
            // Fase D untuk SMP/MTs, Fase E–F untuk SMA/MA). Fase yang tidak cocok jenjangnya ditolak
            // supaya media tidak "hilang" dari tab fase mana pun.
            $daftarFaseMenu = daftar_apps_berfase($menu);
            $faseDipilih = '';
            if ($daftarFaseMenu) {
                $jenjangDipilih = daftar_apps_kategori_sah($menu, $_POST['jenjang'] ?? '');
                $faseKiriman = trim((string) ($_POST['fase'] ?? ''));
                if ($faseKiriman !== '') {
                    $faseDipilih = daftar_apps_fase_sah($menu, $jenjangDipilih, $faseKiriman);
                    if ($faseDipilih === '') {
                        $galat[] = 'Fase tidak sesuai dengan jenjang yang dipilih.';
                    }
                }
            }
            if (strlen($nama) > 160) {
                $nama = substr($nama, 0, 160);
            }
            if (strlen($link) > 500) {
                $link = substr($link, 0, 500);
            }
            if ($galat) {
                // Pesan dikirim lewat flash (halaman berikutnya) supaya isian tidak hilang.
                foreach ($galat as $g) {
                    flash($g, 'err');
                }
                redirect(daftar_apps_url($berkas, ['edit' => $s ? (int) $s['id'] : null]));
            }

            if ($s) {
                if ($kategori && $daftarFaseMenu) {
                    q('UPDATE ' . $tabel . ' SET nama = ?, link = ?, keterangan = ?, jenjang = ?, fase = ?, aktif = ?, diubah = ? WHERE id = ?',
                        [$nama, $link, $ket, daftar_apps_kategori_sah($menu, $_POST['jenjang'] ?? ''),
                         $faseDipilih, $aktif, $now, (int) $s['id']]);
                } elseif ($kategori) {
                    q('UPDATE ' . $tabel . ' SET nama = ?, link = ?, keterangan = ?, jenjang = ?, aktif = ?, diubah = ? WHERE id = ?',
                        [$nama, $link, $ket, daftar_apps_kategori_sah($menu, $_POST['jenjang'] ?? ''), $aktif, $now, (int) $s['id']]);
                } else {
                    q('UPDATE ' . $tabel . ' SET nama = ?, link = ?, keterangan = ?, aktif = ?, diubah = ? WHERE id = ?',
                        [$nama, $link, $ket, $aktif, $now, (int) $s['id']]);
                }
                $appId = (int) $s['id'];
                flash('Aplikasi ' . $nama . ' diperbarui.');
            } else {
                if ($kategori && $daftarFaseMenu) {
                    q('INSERT INTO ' . $tabel . ' (nama, link, keterangan, jenjang, fase, urutan, aktif, dibuat, diubah) VALUES (?,?,?,?,?,?,?,?,?)',
                        [$nama, $link, $ket, daftar_apps_kategori_sah($menu, $_POST['jenjang'] ?? ''),
                         $faseDipilih, daftar_apps_urutan_baru($menu), $aktif, $now, $now]);
                } elseif ($kategori) {
                    q('INSERT INTO ' . $tabel . ' (nama, link, keterangan, jenjang, urutan, aktif, dibuat, diubah) VALUES (?,?,?,?,?,?,?,?)',
                        [$nama, $link, $ket, daftar_apps_kategori_sah($menu, $_POST['jenjang'] ?? ''),
                         daftar_apps_urutan_baru($menu), $aktif, $now, $now]);
                } else {
                    q('INSERT INTO ' . $tabel . ' (nama, link, keterangan, urutan, aktif, dibuat, diubah) VALUES (?,?,?,?,?,?,?)',
                        [$nama, $link, $ket, daftar_apps_urutan_baru($menu), $aktif, $now, $now]);
                }
                $appId = (int) db()->lastInsertId();
                flash('Aplikasi ' . $nama . ' ditambahkan ke daftar ' . $namaMenu . '.');
            }

            // LENCANA "Bonus" pada menu sidebar (permintaan pemilik) — hanya untuk menu yang
            // memakai fitur ini, dan hanya bila bagian itu benar-benar dikirim formulirnya.
            if ($aturBaru && $appId > 0) {
                daftar_apps_set_baru($menu, $appId, $baru);
                if ($baru) {
                    flash('Aplikasi ' . $nama . ' ditandai BONUS — tulisan ' . DAFTAR_APPS_BARU_TEKS
                        . ' tampil pada menu ' . $namaMenu . ' di sidebar sampai tanda bonusnya dilepas.');
                }
            }

            // Penyetelan sekolah/madrasah yang boleh membuka aplikasi ini (menu Bendahara).
            if ($aturSekolah && $appId > 0) {
                $hasil = daftar_apps_app_simpan($menu, $appId, (array) ($_POST['sekolah'] ?? []));
                $jumlah = count(daftar_apps_app_sekolah_ids($menu, $appId));
                flash($jumlah > 0
                    ? 'Aplikasi ' . $nama . ' dapat dibuka oleh ' . $jumlah . ' sekolah/madrasah.'
                    : 'Aplikasi ' . $nama . ' sekarang TIDAK dapat dibuka oleh sekolah/madrasah mana pun '
                      . '(tidak ada yang dicentang).',
                    $jumlah > 0 ? 'ok' : 'warn');
            }
            redirect($berkas . '#tabelAplikasi');
        }

        if ($aksi === 'sekolah_simpan') {
            if (!daftar_apps_terbatas($menu)) {
                flash('Menu ' . $namaMenu . ' tampil untuk semua sekolah/madrasah.', 'warn');
                redirect($berkas);
            }
            $hasil = daftar_apps_sekolah_simpan($menu, (array) ($_POST['sekolah'] ?? []));
            $jumlah = count(daftar_apps_sekolah_ids($menu));
            flash($jumlah > 0
                ? 'Penyetelan disimpan: menu ' . $namaMenu . ' tampil pada ' . $jumlah . ' sekolah/madrasah.'
                : 'Penyetelan disimpan: menu ' . $namaMenu . ' sekarang tidak tampil pada sekolah/madrasah mana pun.',
                $jumlah > 0 ? 'ok' : 'warn');
            redirect($berkas . '#cardSekolahMenu');
        }

        // MENGAKTIFKAN / MENONAKTIFKAN MENU (v1.81.0 — permintaan pemilik). Ditangani SEBELUM
        // pemeriksaan "aplikasi tidak ditemukan" karena aksi ini tidak memakai satu aplikasi pun.
        if ($aksi === 'menu_aktifkan' || $aksi === 'menu_nonaktifkan') {
            if (!daftar_apps_bisa_dinonaktifkan($menu)) {
                flash('Menu ' . $namaMenu . ' tidak dapat dinonaktifkan.', 'err');
                redirect($berkas);
            }
            $jadi = $aksi === 'menu_aktifkan';
            daftar_apps_aktif_set($menu, $jadi);
            flash($jadi
                ? 'Menu ' . $namaMenu . ' DIAKTIFKAN kembali — seluruh pengguna dapat membukanya seperti biasa.'
                : 'Menu ' . $namaMenu . ' DINONAKTIFKAN. Pengguna yang membuka menu ini akan melihat '
                  . 'peringatan: "' . daftar_apps_gangguan_pesan($menu) . '"',
                $jadi ? 'ok' : 'warn');
            redirect($berkas . '#cardStatusMenu');
        }

        if (!$s) {
            flash('Aplikasi tidak ditemukan.', 'err');
            redirect($berkas);
        }

        if ($aksi === 'toggle') {
            q('UPDATE ' . $tabel . ' SET aktif = ?, diubah = ? WHERE id = ?',
                [(int) $s['aktif'] === 1 ? 0 : 1, date('Y-m-d H:i:s'), $id]);
            flash('Aplikasi ' . $s['nama'] . ((int) $s['aktif'] === 1 ? ' disembunyikan dari daftar.' : ' ditampilkan kembali.'), 'warn');
        } elseif ($aksi === 'geser') {
            if (daftar_apps_geser($menu, $id, (int) ($_POST['arah'] ?? 0))) {
                flash('Urutan aplikasi ' . $s['nama'] . ' dipindahkan.', 'warn');
            }
        } elseif ($aksi === 'hapus') {
            daftar_apps_app_bersihkan($menu, $id);   // jangan tinggalkan baris yatim penyetelan sekolah
            q('DELETE FROM ' . $tabel . ' WHERE id = ?', [$id]);
            flash('Aplikasi ' . $s['nama'] . ' dihapus dari daftar ' . $namaMenu . '.', 'warn');
        }
        redirect($berkas);
    }

    // ---------------------------------------------------------------- tampilan
    $edit = $super ? daftar_apps_by_id($menu, (int) ($_GET['edit'] ?? 0)) : null;
    $semua = $super ? daftar_apps_list($menu, false) : daftar_apps_list($menu, true);

    // TAB KATEGORI (v1.105.0): hitung isi tiap tab DULU (untuk angka pada pil), lalu sisakan baris
    // milik tab yang sedang dibuka. Baris bertab kosong/tak dikenal ikut tab PERTAMA (lihat
    // daftar_apps_kategori_baris()) sehingga tidak pernah ada isian yang tak tampil di tab mana pun.
    $jmlPerTab = [];
    if ($kategori) {
        foreach ($kategori as $katKunci => $katLabel) {
            $jmlPerTab[$katKunci] = 0;
        }
        foreach ($semua as $a) {
            $jmlPerTab[daftar_apps_kategori_baris($menu, $a)]++;
        }
        $semua = array_values(array_filter($semua,
            static fn(array $a): bool => daftar_apps_kategori_baris($menu, $a) === $katAktif));
    }

    // SUB TAB FASE (v1.112.0): angka tiap pil fase dihitung dari baris TAB yang sedang dibuka
    // (media tanpa fase ikut dihitung pada SETIAP fase karena memang tampil di sana), lalu daftar
    // disaring ke fase yang sedang dibuka. Tab tanpa fase (Game Edukasi) tidak tersentuh.
    $daftarFase = $kategori ? daftar_apps_fase($menu, $katAktif) : [];
    $faseAktif = $daftarFase ? daftar_apps_fase_aktif($menu, $katAktif) : '';
    $jmlPerFase = [];
    if ($daftarFase) {
        foreach ($daftarFase as $faseKunci => $faseLabel) {
            $jmlPerFase[$faseKunci] = 0;
            foreach ($semua as $a) {
                if (daftar_apps_fase_tampil($menu, $katAktif, $a, (string) $faseKunci)) {
                    $jmlPerFase[$faseKunci]++;
                }
            }
        }
        $semua = array_values(array_filter($semua,
            static fn(array $a): bool => daftar_apps_fase_tampil($menu, $katAktif, $a, $faseAktif)));
    }

    $jmlAktif = 0;
    foreach ($semua as $a) {
        if ((int) $a['aktif'] === 1) {
            $jmlAktif++;
        }
    }
    // Chip: Super Admin melihat JUMLAH SELURUH aplikasi (termasuk yang disembunyikan),
    // peran lain hanya menghitung yang aktif (daftarnya memang sudah tersaring).
    $jmlChip = count($semua);
    $jmlDisembunyikan = $jmlChip - $jmlAktif;

    // TEKS TOMBOL BUKA (v1.107.0) — dihitung SEKALI: menu Media Interaktif memakai "Buka Media"
    // (dan "Mainkan Game" pada tab Game Edukasi); menu lain tetap "Buka Aplikasi".
    $labelBuka = daftar_apps_buka_label($menu, $katAktif);

    // AKSES PER APLIKASI: sekolah yang tidak dituju Super Admin melihat keterangan
    // "Madrasah Anda belum punya akses ke aplikasi keuangan" (permintaan pemilik), bukan tombol buka.
    // Dihitung di sini supaya keterangannya juga berlaku saat daftar aplikasinya masih kosong.
    $perApp = daftar_apps_per_aplikasi($menu);
    $sekolahSaya = current_sekolah();
    $idSekolahSaya = $sekolahSaya ? (int) $sekolahSaya['id'] : 0;
    $jumlahBoleh = 0;
    if (!$super && $perApp) {
        foreach ($semua as $a) {
            if (daftar_apps_app_boleh($menu, (int) $a['id'], $idSekolahSaya)) {
                $jumlahBoleh++;
            }
        }
    }

    // GRAFIK "APLIKASI YANG SERING DIGUNAKAN" (v1.79.0) — dihitung SEKALI di sini karena dipakai
    // kartu grafik di atas daftar aplikasi DAN skrip penggambar grafik di bawah halaman.
    $grafikSering = !empty($k['grafik_sering']);
    $klikRingkas = $grafikSering ? app_klik_ringkas(8) : [];
    $klikTotal = $grafikSering ? app_klik_total() : 0;
    $klikAppJml = $grafikSering ? app_klik_app_jumlah() : 0;

    // ------------------------------------------------- TAB "BUATKAN MEDIA" (v1.111.0)
    // Saat guru membuka `?buatkan=1`, halaman ini menampilkan formulir permintaan media + daftar
    // permintaan miliknya (bukan daftar kartu media). Seluruh isinya dari inc/media_permintaan.php —
    // baris pil tab ikut dicetak di dalamnya lewat daftar_apps_tab_bar() supaya tampilannya sama.
    if (daftar_apps_mode_buatkan($menu)) {
        media_permintaan_tab_guru($menu, $berkasDasar, $katAktif, $jmlPerTab, $k);
        return;
    }

    page_top($namaMenu, $tugas !== null ? 'Tugas Tambahan' : '');
    ?>
<?php if ($super && daftar_apps_bisa_dinonaktifkan($menu)): ?>
  <?php
  // KARTU "STATUS MENU" (v1.81.0 — permintaan pemilik: *"Pada akun super admin tambahkan
  // fitur/menu untuk mengaktifkan atau menonaktifkan menu Smart Teacher."*).
  // Hanya Super Admin yang melihat kartu ini; peran lain tidak pernah melihat tombol pengubah.
  $menuAktif = daftar_apps_aktif($menu);
  $menuDiubah = daftar_apps_aktif_diubah($menu);
  ?>
  <div class="card" id="cardStatusMenu">
    <div class="card-head">
      <div>
        <h2><?= ikon('switch', 16) ?> Status Menu <?= e($namaMenu) ?></h2>
        <p>Aktifkan atau nonaktifkan menu <b><?= e($namaMenu) ?></b> untuk <b>seluruh pengguna</b>
          (guru, kepala sekolah/madrasah, admin operator, dan pengawas) di semua
          sekolah/madrasah.</p>
      </div>
      <div class="flex">
        <span class="pill <?= $menuAktif ? 'pill-ok' : 'pill-warn' ?>" id="pilStatusMenu"><?= $menuAktif ? 'Aktif' : 'Nonaktif' ?></span>
      </div>
    </div>
    <div class="alert <?= $menuAktif ? 'alert-warn' : 'alert-err' ?>" id="pratinjauGangguan">
      <?= ikon($menuAktif ? 'info' : 'alert', 16) ?>
      <?php if ($menuAktif): ?>
        <span>Bila menunya <b>dinonaktifkan</b>, pengguna yang mengklik menu ini akan melihat peringatan
          <b>“<?= e(daftar_apps_gangguan_pesan($menu)) ?>”</b> dan daftar aplikasi beserta tombol
          <b>Buka Aplikasi</b> ikut ditutup sampai menunya diaktifkan kembali. Anda sebagai
          <b>Super Admin tetap dapat membuka</b> halaman ini untuk menghidupkannya lagi.</span>
      <?php else: ?>
        <span>Menu ini <b>sedang NONAKTIF</b> — guru, kepala sekolah/madrasah, admin operator, dan
          pengawas melihat peringatan <b>“<?= e(daftar_apps_gangguan_pesan($menu)) ?>”</b> dan tidak
          dapat membuka daftar aplikasinya. Tekan tombol di bawah untuk mengaktifkannya kembali
          (tidak ada data yang hilang).</span>
      <?php endif; ?>
    </div>
    <form method="post" class="flex" id="formStatusMenu"
          style="flex-wrap:wrap"<?= $menuAktif
              ? ' data-confirm="Nonaktifkan menu ' . e($namaMenu) . ' untuk seluruh pengguna? Pengguna akan melihat peringatan gangguan sampai menunya diaktifkan kembali."'
              : '' ?>>
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="<?= $menuAktif ? 'menu_nonaktifkan' : 'menu_aktifkan' ?>">
      <button class="btn <?= $menuAktif ? 'btn-danger' : 'btn-primary' ?>" type="submit" id="btnStatusMenu">
        <?= ikon($menuAktif ? 'x' : 'check', 16) ?>
        <?= $menuAktif ? 'Nonaktifkan Menu ' . e($namaMenu) : 'Aktifkan Menu ' . e($namaMenu) ?>
      </button>
      <span class="hint" style="align-self:center">
        <?= $menuDiubah !== ''
            ? 'Keadaan menu terakhir diubah: <b>' . e(waktu_id_polos($menuDiubah)) . '</b>.'
            : 'Keadaan menu belum pernah diubah.' ?>
      </span>
    </form>
  </div>
<?php endif; ?>
<?php if ($grafikSering): ?>
  <?php // Kartu GRAFIK diletakkan PALING ATAS (di atas daftar kartu aplikasi) sesuai permintaan
        // pemilik: *"Pada halaman smart teacher tambahkan Grafik Aplikasi yang Sering Digunakan."*
        // Datanya GLOBAL (seluruh pengguna, semua sekolah/madrasah, seluruh data yang tercatat) —
        // sama seperti ringkasan pada dasbor Super Admin — supaya pengguna tahu aplikasi mana yang
        // paling sering dipakai. ?>
  <div class="card" id="cardGrafikApps">
    <div class="card-head">
      <div>
        <h2><?= ikon('chart', 16) ?> Aplikasi yang Sering Digunakan</h2>
        <p>Jumlah klik tombol <b>Buka Aplikasi</b> oleh <b>seluruh pengguna</b> dari semua
          sekolah/madrasah — seluruh data yang tercatat (tanpa batas waktu).</p>
      </div>
      <div class="flex">
        <span class="chip" id="chipKlikApps"><?= (int) $klikTotal ?> klik</span>
        <span class="chip" id="chipAppApps"><?= (int) $klikAppJml ?> aplikasi</span>
      </div>
    </div>
    <?php if (!$klikRingkas): ?>
      <div class="empty" id="grafikAppsKosong">
        <?= ikon('chart', 32) ?>
        <h3>Belum ada data penggunaan</h3>
        <p>Grafik ini terisi otomatis setiap kali pengguna menekan tombol <b>Buka Aplikasi</b> pada
          daftar aplikasi (Smart Teacher, Aplikasi Sagu, Ujian Online, Hasil Supervisi, serta
          Bendahara Bos/Madrasah).</p>
      </div>
    <?php else: ?>
      <div class="chart-box grafik-apps" id="grafikAppsBox"><canvas id="chAppsSering"></canvas></div>
      <p class="hint center" id="ketGrafikApps">
        Urutan dari aplikasi yang paling sering dibuka<?= $klikAppJml > count($klikRingkas)
            ? ' — menampilkan ' . count($klikRingkas) . ' teratas dari ' . (int) $klikAppJml . ' aplikasi' : '' ?>.
        Angka dihitung sejak fitur ini dipasang.
      </p>
    <?php endif; ?>
  </div>
<?php endif; ?>
<div class="card">
  <div class="card-head">
    <div>
      <h2><?= e($k['judul']) ?></h2>
      <p>
        <?php if ($super): ?>
          Isi <b>nama aplikasi</b>, <b>link</b>, dan <b>keterangannya</b>.
          <?php if (daftar_apps_per_aplikasi($menu)): ?>
            Setiap aplikasi juga disetel <b>sekolah/madrasah mana yang boleh membukanya</b>.
          <?php else: ?>
            Daftar ini <b>global</b>, jadi terlihat sama bagi semua sekolah/madrasah dan semua pengguna.
          <?php endif; ?>
        <?php else: ?>
          <?php // v1.107.0: teks tombol mengikuti konfigurasi menu (Media Interaktif memakai
                // "Buka Media" / "Mainkan Game"); menu lain tetap "Buka Aplikasi". ?>
          <?php if ($labelBuka === DAFTAR_APPS_BUKA_TEKS): ?>
            Klik <b><?= e($labelBuka) ?></b> untuk membuka aplikasinya pada tab baru.
          <?php else: ?>
            Klik <b><?= e($labelBuka) ?></b> untuk membukanya pada tab baru.
          <?php endif; ?>
        <?php endif; ?>
      </p>
    </div>
    <div class="flex">
      <span class="chip"><?= (int) $jmlChip ?> aplikasi</span>
      <?php if ($super && $jmlDisembunyikan > 0): ?>
        <span class="chip"><?= (int) $jmlDisembunyikan ?> disembunyikan</span>
      <?php endif; ?>
      <?php if ($super): ?>
        <a class="btn btn-primary" href="<?= e($berkas) ?>#<?= e($k['form_id']) ?>"><?= ikon('plus', 16) ?> Tambah Aplikasi</a>
      <?php endif; ?>
    </div>
  </div>

  <?php // TAB KATEGORI (v1.105.0 — menu "Media Interaktif"): satu pil per jenjang + Game Edukasi.
        // Tautan biasa (bukan JavaScript) supaya tetap bekerja tanpa skrip, dan angka pada tiap pil
        // = jumlah aplikasi pada tab itu. Gaya memakai pola "chip tab berwarna" yang sudah dipakai
        // menu Tugas Tambahan (.chip-list.warna).
        // v1.105.1: jarak baris pil ke keterangannya DIRAPIKAN (permintaan pemilik: *"dibuat lebih
        // rapi karena saat ini saling berdempetan antara tulisan dan tab"*) — dulu keterangan memakai
        // `margin-top` NEGATIF (-6px) sehingga tulisannya menumpuk di bawah pil. Sekarang jaraknya
        // diatur kelas `.apps-kat` / `.apps-kat-ket` (assets/style.css), tanpa margin negatif.
        // v1.106.0 (permintaan pemilik): *"Yang saya tandai warna merah buat tombol timbul agar lebih
        // menarik. Dan yang saya tandai warna biru buat keterangan peringatan 'Penggunaan media
        // interaktif (aktif atau tidaknya) tergantung dari kebijakan pihak gemini'. Buat tulisan warna
        // merah dan berkedip sebagai peringatan"* — pil tab dibuat TIMBUL (gaya `.apps-kat > .chip`)
        // dan peringatan diletakkan pada SISI KANAN baris pil (`.apps-kat-baris` + `.apps-kat-ingat`).
        // Teksnya disimpan pada konfigurasi menu (kunci `ingat_kategori`) supaya modul bersama ini
        // tetap umum: menu bertab lain nanti bisa punya peringatannya sendiri atau tidak sama sekali. ?>
  <?php if ($kategori): ?>
    <?php
    // Tab "Buatkan Media" (v1.111.0): pil ke-5, hanya untuk akun guru pada menu yang memintanya.
    daftar_apps_tab_bar($menu, $berkasDasar, $katAktif, $jmlPerTab, $super, $k, $jmlPerFase);
    ?>
  <?php endif; ?>

  <?php if ($super): ?>
    <?php // Formulir pengisian — hanya Super Admin (permintaan pemilik). ?>
    <form method="post" class="apps-form" id="<?= e($k['form_id']) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
      <div class="apps-form-judul">
        <?= ikon($edit ? 'edit' : 'plus', 16) ?>
        <b><?= $edit ? 'Ubah Aplikasi' : 'Tambah Aplikasi ' . e($namaMenu) ?></b>
        <?php if ($edit): ?><a class="xs" href="<?= e($berkas) ?>">(batal)</a><?php endif; ?>
      </div>
      <div class="form-grid">
        <div>
          <label class="f" for="nama">Nama Aplikasi *</label>
          <input type="text" id="nama" name="nama" maxlength="160" required value="<?= e((string) ($edit['nama'] ?? '')) ?>"
                 placeholder="<?= e($k['placeholder']) ?>">
        </div>
        <?php if ($kategori): ?>
          <?php // TAB JENJANG (v1.105.0) — aplikasi masuk ke tab mana. Bawaannya tab yang sedang
                // dibuka supaya menambah beberapa media pada satu jenjang tidak perlu memilih ulang. ?>
          <div>
            <label class="f" for="jenjang">Jenjang (tab) *</label>
            <select id="jenjang" name="jenjang" required>
              <?php foreach ($kategori as $katKunci => $katLabel):
                  $dipilih = daftar_apps_kategori_sah($menu, $edit['jenjang'] ?? '') !== ''
                      ? daftar_apps_kategori_sah($menu, $edit['jenjang'] ?? '') : $katAktif;
              ?>
                <option value="<?= e((string) $katKunci) ?>" <?= $dipilih === $katKunci ? 'selected' : '' ?>>
                  <?= e((string) $katLabel) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="hint">Menentukan <b>tab</b> tempat aplikasi ini tampil pada halaman guru.</div>
          </div>
        <?php endif; ?>
        <?php if ($kategori && daftar_apps_berfase($menu)): ?>
          <?php
          // SUB TAB FASE (v1.112.0) — media masuk ke fase mana pada tab jenjangnya. Pilihannya
          // DIKELOMPOKKAN per jenjang (`<optgroup>`), dan `initFaseJenjang()` (assets/app.js)
          // menyembunyikan kelompok yang bukan jenjang terpilih supaya Super Admin tidak salah pilih.
          // Server TETAP memeriksa sendiri (fase yang tidak cocok jenjangnya ditolak), jadi formulir
          // ini tetap benar walau JavaScript mati. Kosong = tampil pada semua fase jenjangnya.
          $faseTerpilih = $edit
              ? daftar_apps_fase_sah($menu, daftar_apps_kategori_baris($menu, $edit), $edit['fase'] ?? '')
              : '';
          ?>
          <div>
            <label class="f" for="fase">Fase (sub tab)</label>
            <select id="fase" name="fase">
              <option value="">— Semua fase pada jenjangnya —</option>
              <?php foreach (daftar_apps_fase_menu($menu) as $faseKat => $faseIsi): ?>
                <?php if (!is_array($faseIsi) || !$faseIsi) { continue; } ?>
                <optgroup label="<?= e(daftar_apps_kategori_label($menu, (string) $faseKat)) ?>"
                          data-jenjang="<?= e((string) $faseKat) ?>">
                  <?php foreach ($faseIsi as $faseKunci => $faseLabel): ?>
                    <option value="<?= e((string) $faseKunci) ?>" <?= $faseTerpilih === $faseKunci ? 'selected' : '' ?>>
                      <?= e((string) $faseLabel) ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
            <div class="hint">Memisahkan media per <b>fase</b> di dalam tab jenjang
              (SD/MI: Fase A–C &middot; SMP/MTs: Fase D &middot; SMA/MA: Fase E–F).
              Biarkan <b>kosong</b> bila media ini berlaku untuk semua fase pada jenjangnya.</div>
          </div>
        <?php endif; ?>
        <div>
          <label class="f" for="link">Link Aplikasi *</label>
          <input type="url" id="link" name="link" maxlength="500" required value="<?= e((string) ($edit['link'] ?? '')) ?>"
                 placeholder="https://…">
          <div class="hint">Harus dimulai <b>http://</b> atau <b>https://</b>. Tombol “<?= e(daftar_apps_buka_label($menu)) ?>” membuka tautan ini di tab baru.</div>
        </div>
        <div class="apps-ket">
          <label class="f" for="keterangan">Keterangan Aplikasi</label>
          <textarea id="keterangan" name="keterangan" rows="3" maxlength="600"
                    placeholder="<?= e($k['contoh_ket']) ?>"><?= e((string) ($edit['keterangan'] ?? '')) ?></textarea>
        </div>
        <label class="f" style="display:flex;gap:9px;align-items:center;font-weight:600">
          <input type="checkbox" name="aktif" value="1" style="width:16px;height:16px;accent-color:var(--accent)"
                 <?= (int) ($edit['aktif'] ?? 1) === 1 ? 'checked' : '' ?>> Tampilkan di daftar (aktif)
        </label>
        <?php if ($super && daftar_apps_label_baru($menu)): ?>
          <?php // LENCANA "Bonus" BERKEDIP pada menu sidebar (permintaan pemilik): Super Admin
                // menandai aplikasi, lalu tulisan Bonus tampil DI ATAS nama menu pada sidebar SELAMA
                // tanda itu dipasang (v1.99.0: tidak lagi hilang sendiri setelah sekian hari).
                // Penanda `app_baru_atur` menjaga agar formulir/skrip gaya lama (tanpa bagian ini)
                // tidak menghapus tanda yang sudah ada. ?>
          <div class="apps-baru">
            <input type="hidden" name="app_baru_atur" value="1">
            <label class="apps-baru-setelan">
              <input type="checkbox" name="baru" value="1" style="width:16px;height:16px;accent-color:var(--accent)"
                     <?= $edit && daftar_apps_baru($edit) ? 'checked' : '' ?>>
              <span><b>Tandai sebagai bonus</b>
                <span class="new-tag"><?= e(DAFTAR_APPS_BARU_TEKS) ?></span>
                <span class="xs mut">tulisan <?= e(DAFTAR_APPS_BARU_TEKS) ?> (merah, berkedip) tampil di atas
                  nama menu <b><?= e($namaMenu) ?></b> pada sidebar — tampil terus selama centang ini
                  dipasang, dan hilang bila centangnya dilepas lalu disimpan.</span>
              </span>
            </label>
            <?php if ($edit && daftar_apps_baru($edit)): ?>
              <div class="hint" style="margin:8px 0 0">
                Aplikasi ini berlabel bonus sejak <b><?= e(daftar_apps_baru_tanggal($edit)) ?></b>.
                Hilangkan centangnya lalu simpan bila ingin melepas labelnya.
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if ($super && daftar_apps_per_aplikasi($menu)): ?>
          <?php
          // AKSES PER APLIKASI (permintaan pemilik): link aplikasi bisa dibuka oleh sekolah/madrasah
          // yang diinginkan Super Admin. Penanda `app_sekolah_atur` WAJIB ada supaya formulir/skrip
          // gaya lama (tanpa bagian ini) tidak menghapus penyetelan yang sudah dibuat.
          $idDipakaiApp = $edit ? daftar_apps_app_sekolah_ids($menu, (int) $edit['id']) : [];
          $semuaSekolahApp = all('SELECT * FROM sekolah ORDER BY aktif DESC, nama COLLATE NOCASE');
          ?>
          <div class="apps-akses">
            <div class="apps-akses-judul">
              <?= ikon('school', 15) ?>
              <b>Link aplikasi ini dapat dibuka oleh</b>
              <?php if ($edit): ?>
                <span class="chip"><?= count($idDipakaiApp) ?> dari <?= count($semuaSekolahApp) ?> sekolah</span>
              <?php else: ?>
                <span class="chip">aplikasi baru</span>
              <?php endif; ?>
            </div>
            <?php if (!$semuaSekolahApp): ?>
              <p class="hint" style="margin:0">Belum ada sekolah/madrasah pada aplikasi ini.</p>
            <?php else: ?>
              <input type="hidden" name="app_sekolah_atur" value="1">
              <div class="apps-akses-daftar">
                <?php foreach ($semuaSekolahApp as $sk):
                    $sid = (int) $sk['id'];
                    $aktifSek = (int) $sk['aktif'] === 1 && (string) ($sk['verifikasi'] ?? 'disetujui') === 'disetujui';
                ?>
                  <label class="apps-akses-item">
                    <input type="checkbox" name="sekolah[]" value="<?= $sid ?>"
                           style="width:16px;height:16px;accent-color:var(--accent)"
                           <?= in_array($sid, $idDipakaiApp, true) ? 'checked' : '' ?>>
                    <span><?= e(tetap_kata(nama_sekolah_tampil($sk))) ?>
                      <span class="xs mut"><?= (string) ($sk['jenis'] ?? 'umum') === 'madrasah' ? 'Madrasah' : 'Sekolah' ?>
                        &middot; <?= $aktifSek ? 'aktif' : 'nonaktif' ?></span></span>
                  </label>
                <?php endforeach; ?>
              </div>
              <p class="hint" style="margin:10px 0 0">
                Centang sekolah/madrasah yang boleh membuka link ini. Sekolah/madrasah di luar daftar
                akan melihat keterangan <b>“<?= e(tetap_kata('Madrasah Anda belum punya akses ke aplikasi keuangan.')) ?>”</b>,
                dan bila <b>tidak ada satu aplikasi pun</b> yang boleh dibukanya, menu
                <?= e($namaMenu) ?> disembunyikan dari sidebar-nya.
                <b>Tidak ada yang dicentang = link ini tidak dapat dibuka sekolah/madrasah mana pun.</b>
              </p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="flex">
          <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> <?= $edit ? 'Simpan Perubahan' : 'Tambah ke Daftar' ?></button>
        </div>
      </div>
    </form>
  <?php endif; ?>

  <?php // Keterangan bagi sekolah/madrasah yang TIDAK punya akses ke satu aplikasi pun — juga saat
        // aplikasinya belum diisi sama sekali (permintaan pemilik). ?>
  <?php if (!$super && $perApp && $jumlahBoleh === 0): ?>
    <div class="alert alert-err" style="margin-bottom:16px">
      <?= ikon('lock', 16) ?>
      <span><b><?= e(tetap_kata('Madrasah Anda belum punya akses ke aplikasi keuangan.')) ?></b>
        Hubungi Admin aplikasi bila sekolah/madrasah ini memang membutuhkannya.</span>
    </div>
  <?php endif; ?>

  <?php if (!$semua): ?>
    <?php if (trim((string) ($k['kosong_judul'] ?? '')) !== ''): ?>
      <?php // KETERANGAN "Aplikasi Sementara Dikembangkan" (v1.72.4) — permintaan pemilik:
            // *"jika link belum di isi ketika klik menu tersebut tampilkan keterangan aplikasi
            // sementara dikembangkan"*. Hanya menu yang memuat kunci `kosong_judul` pada
            // konfigurasinya yang memakainya. Sejak v1.75.0 menu "Studio Foto Pintar" dihapus,
            // jadi TIDAK ada menu yang memakai kunci ini; bloknya sengaja DIBIARKAN supaya menu
            // baru yang ingin tampil begitu cukup menambahkan `kosong_judul`/`kosong_teks`.
            // Menu lain tetap memakai keadaan kosong "Belum ada aplikasi" di bawah. ?>
      <div class="empty apps-dikembangkan" id="appsDikembangkan">
        <?= ikon('clock', 34) ?>
        <h3><?= e((string) $k['kosong_judul']) ?></h3>
        <p><?= e(tetap_kata((string) ($k['kosong_teks'] ?? ''))) ?></p>
        <?php if ($super): ?>
          <p class="hint xs">Sebagai Super Admin: isi <b>nama aplikasi</b> &amp; <b>link</b>-nya pada
            formulir di atas — keterangan ini otomatis berganti menjadi kartu aplikasi setelah
            tautannya diisi.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="empty">
        <?= ikon('apps', 34) ?>
        <h3>Belum ada aplikasi</h3>
        <p>
          <?php if ($super): ?>
            <?php if ($kategori && $daftarFase): ?>
              Tab <b><?= e((string) ($kategori[$katAktif] ?? '')) ?></b> &middot;
              <b><?= e(daftar_apps_fase_label($menu, $katAktif, (string) $faseAktif)) ?></b> masih kosong.
              Pilih <b>Jenjang (tab)</b> dan <b>Fase</b> pada formulir di atas, lalu isi nama, link, dan
              keterangannya — media yang fasenya dikosongkan tampil pada semua fase jenjang itu.
            <?php elseif ($kategori): ?>
              Tab <b><?= e((string) ($kategori[$katAktif] ?? '')) ?></b> masih kosong. Pilih
              <b>Jenjang (tab)</b> pada formulir di atas lalu isi nama, link, dan keterangannya.
            <?php else: ?>
              Daftar <?= e($namaMenu) ?> masih kosong. Tambahkan aplikasi pertama pada formulir di atas
              (nama, link, dan keterangannya).
            <?php endif; ?>
          <?php elseif ($kategori && $daftarFase): ?>
            Belum ada media pada <b><?= e(daftar_apps_fase_label($menu, $katAktif, (string) $faseAktif)) ?></b>
            di tab <b><?= e((string) ($kategori[$katAktif] ?? '')) ?></b>.
            Coba buka fase lain — pengelola aplikasi memisahkan media per fase.
          <?php elseif ($kategori): ?>
            Belum ada media pada tab <b><?= e((string) ($kategori[$katAktif] ?? '')) ?></b>.
            Coba buka tab jenjang lain — pengelola aplikasi mengisi setiap jenjang secara terpisah.
          <?php else: ?>
            Super Admin belum menambahkan aplikasi pada menu <?= e($namaMenu) ?>.
          <?php endif; ?>
        </p>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="grid g3 apps-grid">
      <?php foreach ($semua as $a): $link = tautan_aman($a['link']);
          $bolehBuka = $super || !$perApp || daftar_apps_app_boleh($menu, (int) $a['id'], $idSekolahSaya);
          // v1.75.0 (permintaan pemilik): penanda kartu memakai IKON yang sesuai dengan nama
          // aplikasinya; bila tidak ada kata kunci yang cocok tetap memakai lencana inisial.
          $ikonApp = apps_ikon((string) $a['nama']);
      ?>
        <div class="card apps-kartu<?= (int) $a['aktif'] !== 1 ? ' apps-mati' : '' ?><?= $bolehBuka ? '' : ' apps-kunci' ?>">
          <div class="apps-kartu-head">
            <div class="apps-ikon<?= $ikonApp !== null ? ' apps-ikon-svg' : '' ?>"<?= $ikonApp !== null ? ' title="' . e((string) $a['nama']) . '"' : '' ?>>
              <?= $ikonApp !== null ? ikon($ikonApp, 22) : e(apps_inisial((string) $a['nama'])) ?>
            </div>
            <div class="apps-judul">
              <h3><?= e((string) $a['nama']) ?></h3>
              <?php if ($super && (int) $a['aktif'] !== 1): ?>
                <span class="pill pill-gray">Disembunyikan</span>
              <?php endif; ?>
            </div>
          </div>
          <p class="apps-ket-isi">
            <?= trim((string) ($a['keterangan'] ?? '')) !== ''
                ? nl2br(e((string) $a['keterangan']))
                : '<span class="mut">Belum ada keterangan.</span>' ?>
          </p>
          <div class="apps-kartu-aksi">
            <?php if (!$bolehBuka): ?>
              <span class="apps-tanpa-akses"><?= ikon('lock', 15) ?>
                <?= e(tetap_kata('Madrasah Anda belum punya akses ke aplikasi keuangan.')) ?></span>
            <?php elseif ($link !== ''): ?>
              <?php // v1.79.0: tombol ini menunjuk ke `app_buka.php` supaya SETIAP klik tercatat untuk
                    // GRAFIK "Aplikasi yang Sering Digunakan" (data seluruh pengguna, semua sekolah),
                    // lalu pengguna dialihkan ke tautan aplikasinya. Jangan dikembalikan ke `$link`. ?>
              <a class="btn btn-primary btn-sm" href="<?= e(app_buka_url($menu, (int) $a['id'], $berkas)) ?>"
                 target="_blank" rel="noopener noreferrer">
                <?= ikon('eye', 15) ?> <?= e($labelBuka) ?>
              </a>
            <?php else: ?>
              <span class="hint">Tautan aplikasi belum diisi.</span>
            <?php endif; ?>
          </div>
          <?php if ($super && $perApp): ?>
            <?php
            $idApp = daftar_apps_app_sekolah_ids($menu, (int) $a['id']);
            $jmlSekolah = (int) val('SELECT COUNT(*) FROM sekolah', [], 0);
            ?>
            <div class="apps-akses-ringkas">
              <?= ikon('school', 13) ?>
              <?php if (!$idApp): ?>
                <span>Dapat dibuka <b>semua sekolah/madrasah</b> (belum dibatasi)</span>
              <?php elseif (count($idApp) >= $jmlSekolah): ?>
                <span>Dapat dibuka <b>semua sekolah/madrasah</b> (<?= count($idApp) ?> dicentang)</span>
              <?php else: ?>
                <span>Dapat dibuka <b><?= count($idApp) ?></b> dari <?= $jmlSekolah ?> sekolah/madrasah</span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <?php if ($super): ?>
            <div class="apps-kartu-kelola">
              <a class="btn btn-ghost btn-sm" href="<?= e(daftar_apps_url($berkas, ['edit' => (int) $a['id']])) ?>#<?= e($k['form_id']) ?>" title="Ubah"><?= ikon('edit', 14) ?> Ubah</a>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="geser">
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit" name="arah" value="-1" title="Naikkan urutan"><?= ikon('up', 14) ?></button>
                <button class="btn btn-ghost btn-sm" type="submit" name="arah" value="1" title="Turunkan urutan"><?= ikon('down', 14) ?></button>
              </form>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="toggle">
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= ikon('eye', 14) ?> <?= (int) $a['aktif'] === 1 ? 'Sembunyikan' : 'Tampilkan' ?></button>
              </form>
              <form method="post" data-confirm="Hapus aplikasi <?= e((string) $a['nama']) ?> dari daftar <?= e($namaMenu) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit"><?= ikon('trash', 14) ?> Hapus</button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($super): ?>
  <div class="card">
    <div class="card-head">
      <div>
        <h2>Keterangan Halaman Ini</h2>
        <p>Ringkasan aturan menu <?= e($namaMenu) ?> agar mudah dijelaskan ke pengguna lain.</p>
      </div>
    </div>
    <ul class="apps-catatan">
      <?php if (daftar_apps_per_aplikasi($menu)): ?>
        <li><b>Satu daftar aplikasi</b> — daftar aplikasinya sama untuk semua sekolah/madrasah, tetapi
          <b>setiap link punya daftar sekolah/madrasah sendiri</b> yang boleh membukanya.</li>
        <li><b>Akses diatur pada formulir</b> — buka <b>Ubah</b> pada aplikasi yang dimaksud, lalu centang
          sekolah/madrasah pada bagian <b>“Link aplikasi ini dapat dibuka oleh”</b>.</li>
        <li><b>Sekolah di luar daftar</b> — sekolah/madrasah yang tidak dicentang melihat keterangan
          <b>“<?= e(tetap_kata('Madrasah Anda belum punya akses ke aplikasi keuangan.')) ?>”</b> pada
          aplikasi itu; bila <b>tidak ada satu aplikasi pun</b> yang boleh dibukanya, menu
          <b><?= e($namaMenu) ?></b> tidak muncul sama sekali di sidebar-nya.</li>
      <?php else: ?>
      <li><b>Satu daftar untuk semua</b> — daftar ini global (tidak dibedakan per sekolah/madrasah), jadi semua pengguna melihat daftar yang sama.</li>
      <?php endif; ?>
      <li><b>Diisi Super Admin</b> — guru, kepala sekolah/madrasah, dan admin operator hanya melihat dan membuka tautannya (tidak dapat mengubah).</li>
      <?php if ($kategori): ?>
        <li><b>Dipisah per TAB</b> — daftar ini dibagi ke tab
          <b><?= e(implode(' · ', array_values($kategori))) ?></b>. Tab yang dibuka ditentukan oleh
          pilihan <b>Jenjang (tab)</b> pada formulir; guru otomatis dibukakan tab yang sesuai
          <b>jenjang lembaganya</b> (bila jenjangnya sudah diisi di Identitas &amp; Pengaturan).</li>
        <li><b>Tidak ada media yang tersembunyi</b> — aplikasi yang jenjangnya kosong/tidak dikenal
          tetap tampil pada tab pertama, jadi isian tidak pernah "hilang" dari daftar.</li>
      <?php endif; ?>
      <?php if (daftar_apps_hanya_guru($menu)): ?>
        <li><b>Hanya akun guru</b> — menu <?= e($namaMenu) ?> tidak tampil pada akun kepala
          sekolah/madrasah maupun Admin Operator (halamannya ditolak bila dibuka langsung). Super
          Admin tetap dapat membukanya untuk mengisi daftar ini.</li>
      <?php endif; ?>
      <?php if (daftar_apps_per_aplikasi($menu)): ?>
        <li><b>Tampil bila punya akses</b> — menu <?= e($namaMenu) ?> muncul pada sidebar sekolah/madrasah
          yang punya akses ke minimal satu aplikasi; selain itu menunya disembunyikan.</li>
      <?php elseif (daftar_apps_hanya_guru($menu)): ?>
        <li><b>Tampil pada akun guru</b> — menu <?= e($namaMenu) ?> muncul di sidebar akun
          <b>guru</b> saja (tepat di bawah Smart Teacher), dengan lencana <b>Bonus</b> berkedip.</li>
      <?php else: ?>
        <li><b>Tampil di semua akun</b> — menu <?= e($namaMenu) ?> ada pada sidebar setiap pengguna.</li>
      <?php endif; ?>
      <li><b>Tautan diperiksa</b> — hanya tautan <b>http://</b> atau <b>https://</b> yang diterima; aplikasi yang tidak dipakai bisa disembunyikan (tanpa dihapus).</li>
      <?php if (daftar_apps_bisa_dinonaktifkan($menu)): ?>
        <li><b>Dapat dinonaktifkan sementara</b> — kartu <b>“Status Menu <?= e($namaMenu) ?>”</b> di atas
          mengaktifkan/menonaktifkan menu ini untuk seluruh pengguna. Selama nonaktif, menunya tetap
          tampil di sidebar dengan lencana <b>“Gangguan”</b> dan halamannya menampilkan peringatan
          <b>“<?= e(daftar_apps_gangguan_pesan($menu)) ?>”</b> — Super Admin tetap dapat membukanya
          untuk menghidupkan kembali.</li>
      <?php endif; ?>
      <?php if (daftar_apps_terbatas($menu)): ?>
        <li><b>Dibatasi per sekolah/madrasah</b> — menu ini hanya tampil pada sekolah/madrasah yang
          dicentang pada kartu <b>“Sekolah/Madrasah yang Melihat Menu Ini”</b> di bawah. Administrasi
          (Guru, Kepala Sekolah/Madrasah) di sekolah lain tidak melihat menu ini sama sekali.</li>
      <?php endif; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($super && daftar_apps_terbatas($menu)): ?>
  <?php
  // PERMINTAAN PEMILIK: "bisa juga diseting melalui akun super admin" → Super Admin memilih
  // sekolah/madrasah mana yang melihat menu ini. Sekolah yang tidak dicentang tidak melihat
  // menunya sama sekali (sidebar, pintasan, dan akses langsung halaman ini ditolak 403).
  $idDipakai = daftar_apps_sekolah_ids($menu);
  $semuaSekolah = all('SELECT * FROM sekolah ORDER BY aktif DESC, nama COLLATE NOCASE');
  ?>
  <div class="card" id="cardSekolahMenu">
    <div class="card-head">
      <div>
        <h2><?= ikon('school', 16) ?> Sekolah/Madrasah yang Melihat Menu Ini</h2>
        <p>Centang sekolah/madrasah yang boleh melihat menu <b><?= e($namaMenu) ?></b>. Sekolah yang
          tidak dicentang <b>tidak melihat menunya sama sekali</b> (di sidebar maupun pintasan
          dashboard, dan halaman ini ditolak bila dibuka dari sana).</p>
      </div>
      <div class="flex">
        <span class="chip"><?= count($idDipakai) ?> dari <?= count($semuaSekolah) ?> sekolah</span>
      </div>
    </div>

    <?php if (!$semuaSekolah): ?>
      <div class="empty"><?= ikon('school', 30) ?><h3>Belum ada sekolah/madrasah</h3>
        <p>Tambahkan sekolah/madrasah dulu lewat menu Kelola Sekolah/Madrasah.</p></div>
    <?php else: ?>
      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="sekolah_simpan">
        <div class="table-wrap">
          <table class="tbl" id="tblSekolahMenu">
            <thead>
              <tr>
                <th class="ctr" style="width:52px">Tampil</th>
                <th>Nama Sekolah/Madrasah</th>
                <th class="ctr">Jenis</th>
                <th class="ctr">Status</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($semuaSekolah as $sk):
                $sid = (int) $sk['id'];
                $dicentang = in_array($sid, $idDipakai, true);
            ?>
              <tr>
                <td class="ctr">
                  <input type="checkbox" name="sekolah[]" value="<?= $sid ?>"
                         style="width:16px;height:16px;accent-color:var(--accent)"
                         <?= $dicentang ? 'checked' : '' ?>>
                </td>
                <td><b><?= e(tetap_kata(nama_sekolah_tampil($sk))) ?></b></td>
                <td class="ctr"><span class="chip"><?= (string) ($sk['jenis'] ?? 'umum') === 'madrasah' ? 'Madrasah' : 'Sekolah' ?></span></td>
                <td class="ctr">
                  <?php $aktifSek = (int) $sk['aktif'] === 1 && (string) ($sk['verifikasi'] ?? 'disetujui') === 'disetujui'; ?>
                  <span class="pill <?= $aktifSek ? 'pill-ok' : 'pill-gray' ?>"><?= $aktifSek ? 'Aktif' : 'Nonaktif' ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="flex" style="flex-wrap:wrap">
          <button class="btn btn-primary" type="submit"><?= ikon('save', 16) ?> Simpan Penyetelan Sekolah</button>
          <span class="hint" style="align-self:center">Tidak ada yang dicentang = menu ini tidak tampil
            pada sekolah/madrasah mana pun (Anda sebagai Super Admin tetap dapat membukanya).</span>
        </div>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php
    // GRAFIK "APLIKASI YANG SERING DIGUNAKAN" (v1.79.0) — digambar di klien memakai Chart.js yang
    // sudah di-vendor lokal (pola sama dengan index.php). Bila belum ada data, kanvas tidak dicetak
    // sama sekali sehingga tidak ada grafik kosong.
    if ($grafikSering && $klikRingkas):
        $labelApp = array_map(static fn(array $r): string => $r['nama'], $klikRingkas);
        $dataApp = array_map(static fn(array $r): int => $r['jml'], $klikRingkas);
?>
<script src="assets/vendor/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  EG.bar('chAppsSering', <?= json_encode($labelApp, JSON_UNESCAPED_UNICODE) ?>,
    <?= json_encode($dataApp) ?>, true);
});
</script>
<?php
    endif;
    // ------------------------------------------------------------------ POPUP KUTIPAN PEMBUKA
    // (v1.105.1 — permintaan pemilik: *"saat guru mengklik menu Media Interaktif yang muncul duluan
    // adalah popup dengan tulisan: 'Pendidikan adalah kunci pembuka pintu masa depan. …' - Supianto
    // (Pengembang)"*.) Hanya menu yang konfigurasinya memuat `popup_awal`.
    // v1.105.2 — muncul **SEKALI per sesi login** dan **TIDAK untuk Super Admin**; aturannya satu
    // tempat pada `popup_awal_perlu_tampil()` (`popup_awal_selesai()` dipanggil setelah dicetak).
    // Markup dicetak TANPA atribut `open` dan dibuka oleh `initPopupAwal()` (assets/app.js) memakai
    // showModal() sehingga Esc/klik latar/tombol Tutup menutupnya. Tanpa JavaScript popup TIDAK
    // menghalangi apa pun — isi halaman tetap terbaca & bisa dipakai.
    if (popup_awal_perlu_tampil($menu)):
        $popupAwal = (array) popup_awal_konfig($menu);
        popup_awal_selesai($menu);
        $kutipan = (string) ($popupAwal['teks'] ?? '');
        $sumber  = trim((string) ($popupAwal['sumber'] ?? ''));
        $peran   = trim((string) ($popupAwal['peran'] ?? ''));
?>
<dialog class="popup popup-kutipan" id="popupAwal" aria-labelledby="popupAwalTeks">
  <div class="popup-kutipan-isi">
    <div class="popup-kutipan-ic"><?= ikon('quote', 22) ?></div>
    <p class="popup-kutipan-teks" id="popupAwalTeks"><?= e($kutipan) ?></p>
    <?php if ($sumber !== ''): ?>
      <p class="popup-kutipan-oleh">&mdash; <?= e($sumber) ?><?= $peran !== '' ? ' (' . e($peran) . ')' : '' ?></p>
    <?php endif; ?>
  </div>
  <div class="popup-foot">
    <span class="xs mut">Semoga bermanfaat untuk kegiatan belajar kita semua.</span>
    <button class="btn btn-primary" type="button" data-popup-awal="tutup" autofocus>Tutup</button>
  </div>
</dialog>
<?php
    endif;
    page_bottom();
}
