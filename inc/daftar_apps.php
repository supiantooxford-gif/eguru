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
            // DI ATAS nama menu dan hilang sendiri setelah DAFTAR_APPS_BARU_HARI hari.
            'label_baru' => true,
            // HANYA LEMBAGA YANG SUDAH MELAKUKAN AKTIVASI (permintaan pemilik, v1.78.0) — lihat
            // catatan lengkap pada entri `ujian_online` di bawah & fungsi daftar_apps_aktivasi_boleh().
            'perlu_aktivasi' => true,
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
            // `ujian_online.baru_pada`); lencana tampil DI ATAS nama menu pada sidebar dan hilang
            // sendiri setelah DAFTAR_APPS_BARU_HARI hari. Sejak v1.72.2 menu **Smart Teacher** juga
            // memakai fitur ini; menu daftar aplikasi lain belum.
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
// lencana (merah, berkedip) tampil DI ATAS nama menu pada sidebar **selama `DAFTAR_APPS_BARU_HARI`
// (30) hari** dan hilang sendiri setelahnya. Pilihan pemilik (jangan diubah tanpa bertanya): **hanya
// pada MENU SIDEBAR** — tidak dipasang pada kartu aplikasi maupun tabel pengelolaan.
//
// Yang disimpan hanya WAKTU penandaan (`baru_pada`), bukan angka hari, sehingga hitungannya tetap
// benar walau aplikasi tidak dibuka berhari-hari. Menu yang belum memakai fitur ini (mis. Aplikasi
// Sagu, Hasil Supervisi, Bendahara) TIDAK terpengaruh: kolomnya hanya ada pada tabel menu yang entri
// konfigurasinya memuat `'label_baru' => true`.
// ================================================================================================

/** Apakah sebuah menu daftar aplikasi memakai lencana "Bonus" pada sidebar. */
function daftar_apps_label_baru(string $menu): bool
{
    $k = daftar_apps_konfig($menu);
    return $k !== null && !empty($k['label_baru']);
}

/** Apakah SATU aplikasi masih berstatus bonus (mendapat lencana "Bonus"). */
function daftar_apps_baru(?array $app): bool
{
    $waktu = trim((string) ($app['baru_pada'] ?? ''));
    if ($waktu === '') {
        return false;
    }
    $ts = strtotime($waktu);
    if ($ts === false) {
        return false;
    }
    return $ts > time() - (DAFTAR_APPS_BARU_HARI * 86400);
}

/** Sisa hari lencana "Bonus" sebuah aplikasi (0 bila sudah tidak berlaku). */
function daftar_apps_baru_sisa(?array $app): int
{
    if (!daftar_apps_baru($app)) {
        return 0;
    }
    $ts = (int) strtotime((string) $app['baru_pada']);
    $sisa = (int) ceil(($ts + DAFTAR_APPS_BARU_HARI * 86400 - time()) / 86400);
    return max(0, min(DAFTAR_APPS_BARU_HARI, $sisa));
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
    $judul = 'Ada ' . $jml . ' aplikasi bonus · tampil ' . DAFTAR_APPS_BARU_HARI . ' hari sejak ditandai';
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

    require_login(); // daftar GLOBAL: tidak memerlukan konteks sekolah
    $super = is_super();

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
                q('UPDATE ' . $tabel . ' SET nama = ?, link = ?, keterangan = ?, aktif = ?, diubah = ? WHERE id = ?',
                    [$nama, $link, $ket, $aktif, $now, (int) $s['id']]);
                $appId = (int) $s['id'];
                flash('Aplikasi ' . $nama . ' diperbarui.');
            } else {
                q('INSERT INTO ' . $tabel . ' (nama, link, keterangan, urutan, aktif, dibuat, diubah) VALUES (?,?,?,?,?,?,?)',
                    [$nama, $link, $ket, daftar_apps_urutan_baru($menu), $aktif, $now, $now]);
                $appId = (int) db()->lastInsertId();
                flash('Aplikasi ' . $nama . ' ditambahkan ke daftar ' . $namaMenu . '.');
            }

            // LENCANA "Bonus" pada menu sidebar (permintaan pemilik) — hanya untuk menu yang
            // memakai fitur ini, dan hanya bila bagian itu benar-benar dikirim formulirnya.
            if ($aturBaru && $appId > 0) {
                daftar_apps_set_baru($menu, $appId, $baru);
                if ($baru) {
                    flash('Aplikasi ' . $nama . ' ditandai BONUS — tulisan ' . DAFTAR_APPS_BARU_TEKS
                        . ' tampil pada menu ' . $namaMenu . ' di sidebar selama '
                        . DAFTAR_APPS_BARU_HARI . ' hari.');
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

    page_top($namaMenu, $tugas !== null ? 'Tugas Tambahan' : '');
    ?>
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
          Klik <b>Buka Aplikasi</b> untuk membuka aplikasinya pada tab baru.
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
        <div>
          <label class="f" for="link">Link Aplikasi *</label>
          <input type="url" id="link" name="link" maxlength="500" required value="<?= e((string) ($edit['link'] ?? '')) ?>"
                 placeholder="https://…">
          <div class="hint">Harus dimulai <b>http://</b> atau <b>https://</b>. Tombol “Buka Aplikasi” membuka tautan ini di tab baru.</div>
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
                // menandai aplikasi, lalu tulisan Bonus tampil DI ATAS nama menu pada sidebar selama
                // DAFTAR_APPS_BARU_HARI hari. Penanda `app_baru_atur` menjaga agar formulir/skrip
                // gaya lama (tanpa bagian ini) tidak menghapus tanda yang sudah ada. ?>
          <div class="apps-baru">
            <input type="hidden" name="app_baru_atur" value="1">
            <label class="apps-baru-setelan">
              <input type="checkbox" name="baru" value="1" style="width:16px;height:16px;accent-color:var(--accent)"
                     <?= $edit && daftar_apps_baru($edit) ? 'checked' : '' ?>>
              <span><b>Tandai sebagai bonus</b>
                <span class="new-tag"><?= e(DAFTAR_APPS_BARU_TEKS) ?></span>
                <span class="xs mut">tulisan <?= e(DAFTAR_APPS_BARU_TEKS) ?> (merah, berkedip) tampil di atas
                  nama menu <b><?= e($namaMenu) ?></b> pada sidebar selama <?= (int) DAFTAR_APPS_BARU_HARI ?> hari.</span>
              </span>
            </label>
            <?php if ($edit && daftar_apps_baru($edit)): ?>
              <div class="hint" style="margin:8px 0 0">
                Aplikasi ini masih berlabel baru — sisa
                <b><?= (int) daftar_apps_baru_sisa($edit) ?> hari</b>. Hilangkan centangnya lalu simpan
                bila ingin melepas labelnya sekarang.
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
            Daftar <?= e($namaMenu) ?> masih kosong. Tambahkan aplikasi pertama pada formulir di atas
            (nama, link, dan keterangannya).
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
                <?= ikon('eye', 15) ?> Buka Aplikasi
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
      <?php if (daftar_apps_per_aplikasi($menu)): ?>
        <li><b>Tampil bila punya akses</b> — menu <?= e($namaMenu) ?> muncul pada sidebar sekolah/madrasah
          yang punya akses ke minimal satu aplikasi; selain itu menunya disembunyikan.</li>
      <?php else: ?>
        <li><b>Tampil di semua akun</b> — menu <?= e($namaMenu) ?> ada pada sidebar setiap pengguna.</li>
      <?php endif; ?>
      <li><b>Tautan diperiksa</b> — hanya tautan <b>http://</b> atau <b>https://</b> yang diterima; aplikasi yang tidak dipakai bisa disembunyikan (tanpa dihapus).</li>
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
    page_bottom();
}
