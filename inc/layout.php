<?php
/**
 * Layout aplikasi e-GURU: sidebar (sadar peran & konteks sekolah), header, footer.
 * Seluruh keluaran ditampung lalu melewati penyesuaian istilah Sekolah/Madrasah.
 */
declare(strict_types=1);

function ikon(string $name, int $size = 18): string
{
    $p = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/>',
        'users'     => '<circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.3 2.9-5.5 6.5-5.5s6.5 2.2 6.5 5.5"/><path d="M17 11a3 3 0 1 0 0-6"/><path d="M18 19.6c0-2.2-.8-3.8-2.2-4.8 3 .2 5.2 2.2 5.2 4.8"/>',
        'student'   => '<circle cx="12" cy="7.5" r="3.6"/><path d="M4.5 20.5c0-4 3.4-6.6 7.5-6.6s7.5 2.6 7.5 6.6"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2.8h6V4"/><path d="M9 11h6M9 15h4"/>',
        'chart'     => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'report'    => '<path d="M4 4h11l5 5v11H4z"/><path d="M8 14h8M8 17h5M8 11h3"/>',
        'book'      => '<path d="M4 4.5A2 2 0 0 1 6 2.5h13v19H6a2 2 0 0 0-2 2z"/><path d="M4 17.5h15"/>',
        'alert'     => '<path d="M12 3.5l9 16H3z"/><path d="M12 9v5M12 17.2v.1"/>',
        'folder'    => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'doc'       => '<path d="M14 3v5h5"/><path d="M19 8.5V19a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7z"/><path d="M9 13h6M9 17h4"/>',
        'apps'      => '<rect x="3" y="3" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2"/>',
        'eye'       => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="2.5"/>',
        'upload'    => '<path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
        'download'  => '<path d="M12 4v12"/><path d="M7 11l5 5 5-5"/><path d="M4 20h16"/>',
        'card'      => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><circle cx="8" cy="10.7" r="2"/><path d="M5 15.4c.7-1.6 1.8-2.3 3-2.3s2.3.7 3 2.3"/><path d="M14.5 10h4.5M14.5 13.5h3"/>',
        'key'       => '<circle cx="8" cy="15" r="4"/><path d="M11 12l8-8M17 6l2 2M15 8l2 2"/>',
        'slider'    => '<path d="M4 7h16M4 12h16M4 17h16"/><circle cx="9" cy="7" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="8" cy="17" r="2"/>',
        'print'     => '<path d="M7 9V4h10v5"/><rect x="3" y="9" width="18" height="7" rx="2"/><path d="M7 16h10v4H7z"/>',
        'up'        => '<path d="M12 19V5"/><path d="M6 11l6-6 6 6"/>',
        'down'      => '<path d="M12 5v14"/><path d="M6 13l6 6 6-6"/>',
        'prev'      => '<path d="M15 5l-7 7 7 7"/>',
        'next'      => '<path d="M9 5l7 7-7 7"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'edit'      => '<path d="M4 20h4l10-10-4-4L4 16z"/><path d="M14 5l4 4"/>',
        'trash'     => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'logout'    => '<path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="M15 8l4 4-4 4M19 12H9"/>',
        'search'    => '<circle cx="11" cy="11" r="6"/><path d="M20 20l-4.5-4.5"/>',
        'check'     => '<path d="M4 12.5l5 5L20 6.5"/>',
        'star'      => '<path d="M12 3l2.9 5.9 6.1.9-4.5 4.4 1.1 6.2L12 17.6 6.4 20.4l1.1-6.2L3 9.8l6.1-.9z"/>',
        // Ikon WhatsApp/obrolan — dipakai kartu "Gabung Grup e-GURU" pada dashboard.
        'whatsapp'  => '<path d="M20.5 11.6a8.5 8.5 0 0 1-12.6 7.4L3.5 20.5l1.5-4.3A8.5 8.5 0 1 1 20.5 11.6z"/>'
                     . '<path d="M8.9 7.7h1.2l1 2.2-1.1 1.2c.6 1.3 1.7 2.4 3 3l1.2-1.1 2.2 1v1.2c0 .6-.6 1.2-1.3 1.2-3.6-.3-6.6-3.3-6.9-6.9 0-.7.6-1.3 1.2-1.3z"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5S20 17 20 21"/>',
        // Ikon tambahan untuk HALAMAN DEPAN (landing page, skema v44) — supaya daftar ikon yang
        // boleh dipilih Super Admin pada bagian Fitur & Keunggulan seragam dengan ikon aplikasi.
        'camera'    => '<path d="M4 8h3l1.4-2h7.2L17 8h3v12H4z"/><circle cx="12" cy="13.5" r="3.5"/>',
        'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c2.4 2.6 3.6 5.6 3.6 9S14.4 18.4 12 21c-2.4-2.6-3.6-5.6-3.6-9S9.6 5.6 12 3z"/>',
        'medal'     => '<circle cx="12" cy="14" r="6"/><path d="M12 11.5l1.2 2.4 2.6.4-1.9 1.8.5 2.6-2.4-1.3-2.4 1.3.5-2.6-1.9-1.8 2.6-.4z"/><path d="M9 3h6l-2 6h-2z"/>',
        'shield'    => '<path d="M12 3l8 3v6c0 4.6-3.3 8-8 9-4.7-1-8-4.4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'heart'     => '<path d="M12 20S4 14.5 4 9.6A4.6 4.6 0 0 1 12 6.6a4.6 4.6 0 0 1 8 3c0 4.9-8 10.4-8 10.4z"/>',
        'telepon'   => '<path d="M7 3h3l2 5-2 1a12 12 0 0 0 5 5l1-2 5 2v3a2 2 0 0 1-2 2A17 17 0 0 1 5 5a2 2 0 0 1 2-2z"/>',
        'email'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 6.5L12 12.5l8.5-6"/>',
        'lokasi'    => '<path d="M12 21c4-4.6 7-8 7-11a7 7 0 1 0-14 0c0 3 3 6.4 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'support'   => '<path d="M5 13V9a7 7 0 0 1 14 0v4"/><rect x="3" y="13" width="4" height="6" rx="2"/><rect x="17" y="13" width="4" height="6" rx="2"/><path d="M16 20.5h-3"/>',
        'send'      => '<path d="M4 12l16-8-5 16-3.5-6z"/><path d="M11.5 14L20 4"/>',
        'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.6v.1"/>',
        'quote'     => '<path d="M9 6c-3 1.2-5 3.7-5 7v5h6v-6H7c0-2 1-3.4 3-4z"/><path d="M20 6c-3 1.2-5 3.7-5 7v5h6v-6h-3c0-2 1-3.4 3-4z"/>',
        // Ikon baris "Cepat, Mudah, Praktis, Lengkap" pada halaman depan (v1.60.2):
        // `kilat` = cepat, `jempol` = praktis. Dua lainnya memakai ikon yang sudah ada.
        'kilat'     => '<path d="M13 2.5 5 13.5h5.5L10.5 21.5 19 10.5h-5.5z"/>',
        'jempol'    => '<path d="M7 21V10.5l4-7.5 1.6.9a3 3 0 0 1 1.2 3.7L13 10h5.6a2 2 0 0 1 1.9 2.6l-1.5 6A2 2 0 0 1 17.1 20H7z"/><path d="M7 10.5H4.2V20H7"/>',
        // Ikon OBROLAN/CHAT — dipakai tombol kotak obrolan mengambang (v1.51.0).
        'chat'      => '<path d="M20.5 12.2c0 3.9-3.8 7-8.5 7-1 0-2-.15-2.9-.42L4 20.5l1.3-3.4C4.2 15.8 3.5 14.1 3.5 12.2c0-3.9 3.8-7 8.5-7s8.5 3.1 8.5 7z"/><path d="M8.5 11.5h.01M12 11.5h.01M15.5 11.5h.01"/>',
        'school'    => '<path d="M12 3l9 4.5-9 4.5L3 7.5z"/><path d="M6 10v6c0 1.7 2.7 3 6 3s6-1.3 6-3v-6"/>',
        'building'  => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2"/><path d="M10 21v-3h4v3"/>',
        'switch'    => '<path d="M7 4L3 8l4 4"/><path d="M3 8h13a4 4 0 0 1 0 8H4"/>',
        'filter'    => '<path d="M3 5h18l-7 8v6l-4-2v-4z"/>',
        'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'save'      => '<path d="M5 3h11l3 3v15H5z"/><path d="M9 3v6h6V3"/><path d="M8 21v-6h8v6"/>',
        'lock'      => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'mosque'    => '<path d="M12 3c2.6 2 4 4 4 6H8c0-2 1.4-4 4-6z"/><path d="M5 21V11c0-1.5 1.2-2.6 3-3M19 21V11c0-1.5-1.2-2.6-3-3"/><path d="M3 21h18"/><path d="M10 21v-4a2 2 0 0 1 4 0v4"/>',
        // MIKROFON — dipakai kartu daftar aplikasi (v1.75.0) untuk aplikasi bersuara/audio
        // (mis. "Suara Kita" — pengubah teks menjadi suara).
        'mic'       => '<rect x="9" y="2.5" width="6" height="11" rx="3"/><path d="M5.5 11.5a6.5 6.5 0 0 0 13 0"/><path d="M12 18v3.5M9 21.5h6"/>',
        // LAPISAN — dipakai kartu daftar aplikasi (v1.75.0) untuk aplikasi berisi kumpulan
        // materi/rubrik/KKTP (beberapa berkas bertumpuk dalam satu aplikasi).
        'layers'    => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3.5 12.4l8.5 4.7 8.5-4.7"/><path d="M3.5 16.7l8.5 4.8 8.5-4.8"/>',
        // SILANG — dipakai daftar perangkat (v1.67.0) untuk menandai perangkat yang BELUM dikirim
        // (pasangan dari ikon 'check' yang menandai yang sudah ada).
        'x'         => '<path d="M6.5 6.5l11 11M17.5 6.5l-11 11"/>',
    ];
    $d = $p[$name] ?? $p['doc'];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/** Subjudul di bawah judul halaman (nama sekolah + periode aktif). */
function ajaran_subjudul(): string
{
    if (!current_sekolah()) {
        return 'Super Admin &middot; ' . jumlah_sekolah() . ' sekolah/madrasah terdaftar';
    }
    $ta = ta_aktif();
    $sem = semester_aktif();
    return tetap_kata(nama_sekolah_tampil())
        . ' &middot; Tahun Ajaran ' . e(ta_nama($ta))
        . ' &middot; Semester ' . e(sem_nama($sem));
}

function page_top(string $judul, string $sub = ''): void
{
    // Seluruh keluaran ditampung agar istilah "Sekolah" bisa menjadi "Madrasah".
    ob_start();
    $GLOBALS['__vs_buf_level'] = ob_get_level();

    $u = current_user();
    $here = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $flashes = flash_take();
    $r = peran();
    $super = is_super();
    $sekolah = current_sekolah();
    $menuSekolah = ['index.php', 'guru.php', 'guru_edit.php', 'guru_import.php', 'cetak_guru.php',
        'siswa.php', 'siswa_edit.php', 'siswa_import.php', 'cetak_siswa.php',
        'absensi.php', 'absensi_form.php', 'cetak_absensi.php', 'nilai.php', 'cetak_nilai.php',
        'jurnal.php', 'cetak_jurnal.php', 'kasus.php', 'cetak_kasus.php', 'kelas.php', 'mapel.php',
        'tahun_ajaran.php', 'kenaikan.php', 'pengaturan.php', 'pengguna.php', 'tugas_tambahan.php',
        'notulen.php', 'notulen_form.php', 'cetak_notulen.php',
        // Laporan Rekap Pelanggaran Siswa (kepala sekolah/madrasah + Super Admin dalam konteks) — v1.26.0.
        'laporan_pelanggaran.php', 'cetak_rekap_pelanggaran.php',
        // Laporan Rekap Jumlah Pertemuan Guru (kepala sekolah/madrasah + Super Admin dalam konteks) — v1.28.0.
        'laporan_pertemuan.php', 'cetak_rekap_pertemuan.php'];
    $menuSekolah[] = 'aktivasi.php';      // halaman aktivasi juga halaman "di sekolah"
    $menuSekolah[] = 'akun_operator.php'; // halaman akun operator (khusus kepala sekolah/madrasah)
    $menuSekolah[] = 'pengawas.php';      // halaman akun pengawas (khusus Super Admin, lintas sekolah)
    $menuSekolah[] = 'tugas.php';         // isi menu Tugas Tambahan (halaman per pengguna)
    $menuSekolah[] = 'tugas_pengguna.php'; // penugasan menu Tugas Tambahan (operator/kepala)
    // KIRIM PERANGKAT (v1.67.0/skema v48): kedua halaman ini memuat data SATU sekolah, jadi Super
    // Admin tanpa konteks mendapat halaman "pilih sekolah dulu" (bukan halaman kosong/403).
    // Halaman "Jenis Perangkat" (Super Admin) SENGAJA tidak didaftarkan — daftarnya tingkat aplikasi.
    $menuSekolah[] = 'perangkat_saya.php';
    $menuSekolah[] = 'perangkat_masuk.php';
    $diSekolah = in_array($here, $menuSekolah, true);
    $menunggu = $super ? jumlah_pendaftaran_menunggu() : 0;

    // Jumlah akun Admin Operator (lencana menu "Akun Operator" milik kepala sekolah/madrasah).
    $jmlOperator = ($sekolah && $r === 'kepala') ? akun_jumlah_peran('operator') : 0;

    // Lencana menu PERANGKAT (v1.67.0/skema v48):
    //   • guru → jumlah perangkat yang sudah ia kirim (menu "Kirim Perangkat");
    //   • kepala sekolah/madrasah & Super Admin dalam konteks → jumlah kiriman guru yang MASIH
    //     "Belum Ditinjau" (menu "Perangkat Masuk"), supaya kepala tahu ada yang perlu ditinjau.
    // Lencana menu DAFTAR HADIR MEETING (v1.73.0/skema v55): jumlah pertemuan yang daftar hadirnya
    // sudah diaktifkan pengawas bina dan BELUM ditandatangani akun ini.
    $jmlMeetingBelum = ($sekolah && in_array($r, ['kepala', 'operator', 'guru'], true))
        ? meeting_belum_ttd((int) $sekolah['id'], (int) ($u['id'] ?? 0)) : 0;
    $jmlPerangkatSaya = ($sekolah && is_guru()) ? perangkat_jumlah_saya() : 0;
    $jmlPerangkatBaru = ($sekolah && in_array($r, ['kepala', 'super'], true)) ? perangkat_jumlah_menunggu() : 0;

    // Menu TUGAS TAMBAHAN yang tampil untuk pengguna ini (permintaan pemilik):
    //   - Super Admin / Kepala Sekolah-Madrasah / Admin Operator → SELURUH menu yang diaktifkan;
    //   - guru → hanya menu yang DITUGASKAN kepadanya (+ menu Wali Kelas bila ia wali kelas);
    //   - pengawas → seluruh menu aktif saat sedang melihat satu binaan.
    // Dihitung SEKALI di sini karena dipakai dua sub menu sidebar: "Tugas Tambahan" milik Super
    // Admin (blok lintas sekolah) dan milik kepala/operator/guru (di atas Data Master).
    $tugasAktif = tugas_menu_semua_aktif();

    // Status aktivasi (permanen / masa aktif 1 tahun / masa uji coba) untuk lencana sidebar +
    // spanduk peringatan. Sejak v1.35.0 ada mode `aktif` (masa aktif 365 hari berhitung mundur).
    $aktivasi = aktivasi_status($sekolah);
    $aktivasiPill = '';
    $aktivasiKelas = 'pill-uji';
    $aktivasiSebut = $aktivasi['mode'] === 'aktif' ? 'masa aktif' : 'uji coba';
    // Frasa LENGKAP untuk kalimat spanduk: "Masa aktif …" / "Masa uji coba …".
    // Sebelumnya kalimatnya diawali kata "Masa " lalu variabel ini, sehingga mode `aktif` berbunyi
    // "Masa masa aktif lembaga ini tersisa …" (v1.71.4 diperbaiki; jangan kembalikan).
    $aktivasiSebutJudul = $aktivasi['mode'] === 'aktif' ? 'Masa aktif' : 'Masa uji coba';
    if ($sekolah && in_array($aktivasi['mode'], ['aktif', 'uji_coba'], true)) {
        $aktivasiPill = $aktivasi['habis'] ? ($aktivasiSebut . ' berakhir') : ($aktivasi['sisa_hari'] . ' hari');
        $aktivasiKelas = $aktivasi['habis'] ? 'pill-D' : ($aktivasi['mode'] === 'aktif' ? 'pill-ok' : 'pill-uji');
    }
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> &middot; <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/style.css?v=<?= e(APP_VERSION) ?>">
<?php // Favicon memakai LOGO APLIKASI bila Super Admin sudah mengunggahnya (menu "Logo Aplikasi");
      // bila belum, tetap lambang bawaan supaya tidak ada permintaan berkas yang gagal. ?>
<?php if (ada_logo_aplikasi()): ?>
<link rel="icon" href="<?= e(logo_aplikasi()) ?>">
<?php else: ?>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>&#128218;</text></svg>">
<?php endif; ?>
</head>
<body>
<div class="bg-deco" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></div>

<button class="nav-toggle" id="navToggle" aria-label="Buka menu"><?= ikon('slider', 20) ?></button>
<div class="scrim" id="scrim"></div>

<aside class="sidebar" id="sidebar">
  <div class="brand">
    <?php // Brand sidebar: memakai LOGO APLIKASI hasil unggahan Super Admin (menu "Logo Aplikasi")
          // bila ada; kalau belum, lambang bawaan seperti semula. ?>
    <?php if (ada_logo_aplikasi()): ?>
      <div class="brand-logo brand-logo-img"><img src="<?= e(logo_aplikasi()) ?>" alt="Logo <?= e(APP_NAME) ?>"></div>
    <?php else: ?>
      <div class="brand-logo"><?= ikon($sekolah && is_madrasah() ? 'mosque' : ($sekolah ? 'school' : 'building'), 24) ?></div>
    <?php endif; ?>
    <div>
      <div class="brand-name"><?= e(APP_NAME) ?></div>
      <div class="brand-sub"><?= e(APP_SUB) ?></div>
    </div>
  </div>

  <?php if ($sekolah): ?>
    <div class="ctx-box">
      <div class="ctx-nama"><?= tetap_kata(nama_sekolah_tampil()) ?></div>
      <div class="ctx-ket"><?= e($sekolah['npsn'] ? 'NPSN ' . $sekolah['npsn'] : 'NPSN belum diisi') ?>
        &middot; <?= e($sekolah['jenis'] === 'madrasah' ? 'Madrasah' : 'Sekolah Umum') ?></div>
    </div>
  <?php endif; ?>

  <nav class="nav">
    <?php // ======================================================================================
          // SIDEBAR SISWA (v1.61.0) — permintaan pemilik: "Pada halaman siswa hanya bisa melihat dan
          // mencetak. Yang ada dalam halaman siswa adalah menampilkan Nama dan NISN (tidak bisa
          // merubah profil), Rekapan Daftar hadir Per Mata Pelajaran, Rekapan Daftar Hadir di Kelas,
          // Rekapan Nilai, Catatan Pelanggaran."
          // Karena itu menu akun siswa HANYA berisi halaman-halaman itu; seluruh menu pengelolaan
          // digantikan (bukan ditambahi) dan aksesnya ditegakkan `gerbang_siswa()`.
          ?>
    <?php if (is_siswa()): ?>
      <?php $sw = siswa_saya(); ?>
      <div class="nav-sec">Data Saya</div>
      <a class="nav-item <?= $here === 'index.php' ? 'on' : '' ?>" href="index.php"><?= ikon('dashboard') ?><span>Beranda</span></a>
      <a class="nav-item <?= $here === 'siswa_saya.php' ? 'on' : '' ?>" href="siswa_saya.php"><?= ikon('user') ?><span>Data Saya</span></a>

      <div class="nav-sec">Rekapan</div>
      <a class="nav-item <?= in_array($here, ['siswa_absensi.php', 'cetak_siswa_absensi.php'], true) ? 'on' : '' ?>" href="siswa_absensi.php"><?= ikon('clipboard') ?><span>Daftar Hadir</span></a>
      <a class="nav-item <?= in_array($here, ['siswa_nilai.php', 'cetak_siswa_nilai.php'], true) ? 'on' : '' ?>" href="siswa_nilai.php"><?= ikon('chart') ?><span>Nilai</span></a>
      <a class="nav-item <?= in_array($here, ['siswa_pelanggaran.php', 'cetak_siswa_pelanggaran.php'], true) ? 'on' : '' ?>" href="siswa_pelanggaran.php"><?= ikon('alert') ?><span>Pelanggaran</span></a>
      <?php if ($sw): ?>
        <div class="nav-sec">Identitas</div>
        <div class="nav-ket-siswa">
          <b><?= e((string) $sw['nama']) ?></b>
          <span>NISN <?= e((string) ($sw['nisn'] !== '' ? $sw['nisn'] : '-')) ?></span>
          <span><?= e((string) ($sw['kelas_nama'] !== null ? $sw['kelas_nama'] : 'Kelas belum ditetapkan')) ?></span>
        </div>
      <?php endif; ?>
    <?php else: ?>
    <?php if ($super): ?>
      <?php
      // ==========================================================================================
      // SIDEBAR SUPER ADMIN — URUTAN BARU + SUB MENU (permintaan pemilik, v1.57.0)
      // ------------------------------------------------------------------------------------------
      // *"Pada halaman super admin menu posisi dirubah dan dijadikan sub menu dengan urutan baru:
      //   Ringkasan semua; Kelolah sekolah; Layanan (sub menu: Pendaftaran, Akun Pengawas, Kelola
      //   Tugas Tambahan, Pengumuman Sambutan, Tentang Aplikasi, logo aplikasi, Gabung Grup e-Guru,
      //   Biaya & WA Aktifasi); Akun super admin; Log Aktivitas; Tugas Tambahan (Sub Menu: …)"*
      //
      // Sub menu memakai <details>/<summary> sehingga BUKA-TUTUPNYA bekerja TANPA JavaScript
      // (klik judul → isinya muncul) dan otomatis terbuka bila salah satu isinya sedang dibuka.
      // Urutan & isi sub menu Tugas Tambahan mengikuti daftar menu yang diatur Super Admin di
      // "Kelola Tugas Tambahan" (lihat `tugas_menu_list()` — urut kolom `urutan`).
      // ==========================================================================================
      $layananAktif = in_array($here, ['pendaftaran.php', 'pengawas.php', 'tugas_menu.php',
          'pengumuman.php', 'tentang.php', 'logo.php', 'grup.php', 'biaya_aktivasi.php',
          'landing.php', 'perangkat_jenis.php'], true);
      $jmlTugasAktif = (int) val('SELECT COUNT(*) FROM tugas_menu WHERE aktif = 1', [], 0);
      $jmlPengumuman = pengumuman_jumlah_aktif();
      // Pesan kontak dari formulir halaman depan yang belum dibaca (menu "Halaman Depan").
      $jmlPesanLanding = landing_pesan_baru();
      $jmlPengawas = (int) val('SELECT COUNT(*) FROM users WHERE role = "pengawas" AND aktif = 1', [], 0);
      // Akun pengawas otomatis dari pendaftaran sekolah yang belum ditinjau (v1.50.0) ditampilkan
      // lebih dulu supaya Super Admin tahu ada yang perlu ditinjau — jumlahnya ikut muncul pada
      // judul sub menu "Layanan" agar tetap terlihat walau sub menunya sedang tertutup.
      $jmlTinjauPengawas = pengawas_jumlah_menunggu_tinjau();
      $layananBaru = ($menunggu > 0 ? 1 : 0) + ($jmlTinjauPengawas > 0 ? 1 : 0);
      $tugasAktifHalaman = $here === 'tugas.php';
      // Jumlah Nama Dokumen perangkat (menu "Jenis Perangkat", v1.67.0) — dipakai lencana sub menu.
      $jmlPerangkatJenis = perangkat_jenis_jumlah(true);
      ?>
      <div class="nav-sec"><?= tetap_kata('Lintas Sekolah/Madrasah') ?></div>
      <a class="nav-item <?= $here === 'index.php' && !$sekolah ? 'on' : '' ?>" href="index.php"><?= ikon('chart') ?><span>Ringkasan Semua</span></a>
      <a class="nav-item <?= $here === 'sekolah.php' ? 'on' : '' ?>" href="sekolah.php"><?= ikon('building') ?><span><?= tetap_kata('Kelola Sekolah') ?></span></a>

      <?php // LAYANAN — sub menu berisi 8 halaman pengelolaan tingkat aplikasi. ?>
      <details class="nav-grup"<?= $layananAktif ? ' open' : '' ?>>
        <summary class="nav-item">
          <?= ikon('apps') ?><span>Layanan</span>
          <?php if ($layananBaru > 0): ?><span class="pill pill-gold"><?= (int) $layananBaru ?> baru</span><?php endif; ?>
          <span class="nav-caret" aria-hidden="true"></span>
        </summary>
        <div class="nav-sub">
          <a class="nav-item <?= $here === 'pendaftaran.php' ? 'on' : '' ?>" href="pendaftaran.php"><?= ikon('star') ?><span>Pendaftaran<?= $menunggu > 0 ? ' (' . $menunggu . ')' : '' ?></span>
            <?php if ($menunggu > 0): ?><span class="pill pill-warn" style="margin-left:auto">baru</span><?php endif; ?>
          </a>
          <?php // HALAMAN DEPAN (LANDING PAGE) — v1.60.0/skema v44. Permintaan pemilik: "Buat halaman
                // depan aplikasi menjadi tampilan web landing page ... Untuk mengisi halaman landing
                // page di isi lewat akun super admin." Lencana = pesan kontak yang belum dibaca. ?>
          <a class="nav-item <?= $here === 'landing.php' ? 'on' : '' ?>" href="landing.php"><?= ikon('apps') ?><span>Halaman Depan (Landing)</span>
            <?php if ($jmlPesanLanding > 0): ?>
              <span class="pill pill-warn" style="margin-left:auto"><?= (int) $jmlPesanLanding ?> pesan</span>
            <?php endif; ?>
          </a>
          <?php // Permintaan pemilik: kelola akun Pengawas Sekolah/Madrasah (seperti aplikasi Supervisi). ?>
          <a class="nav-item <?= $here === 'pengawas.php' ? 'on' : '' ?>" href="pengawas.php"><?= ikon('eye') ?><span>Akun Pengawas</span>
            <?php if ($jmlTinjauPengawas > 0): ?>
              <span class="pill pill-gold" style="margin-left:auto"><?= (int) $jmlTinjauPengawas ?> baru</span>
            <?php elseif ($jmlPengawas > 0): ?>
              <span class="pill pill-B" style="margin-left:auto"><?= $jmlPengawas ?></span>
            <?php endif; ?>
          </a>
          <?php // Permintaan pemilik: mengelola MENU TUGAS TAMBAHAN (menu bawaan + menu yang
                // ditambahkan Super Admin) — mengaktifkan, menambah, mengubah nama, dan mengurutkan. ?>
          <a class="nav-item <?= $here === 'tugas_menu.php' ? 'on' : '' ?>" href="tugas_menu.php"><?= ikon('star') ?><span>Kelola Tugas Tambahan</span>
            <?php if ($jmlTugasAktif > 0): ?><span class="pill pill-ok" style="margin-left:auto"><?= $jmlTugasAktif ?></span><?php endif; ?>
          </a>
          <?php // Permintaan pemilik: isi POPUP "Selamat datang" (muncul sekali setiap pengguna masuk). ?>
          <a class="nav-item <?= $here === 'pengumuman.php' ? 'on' : '' ?>" href="pengumuman.php"><?= ikon('star') ?><span>Pengumuman Sambutan</span>
            <?php if ($jmlPengumuman > 0): ?><span class="pill pill-ok" style="margin-left:auto"><?= (int) $jmlPengumuman ?></span><?php endif; ?>
          </a>
          <?php // TENTANG APLIKASI (v1.34.0): tempat FOTO + NAMA + KETERANGAN pada halaman masuk. ?>
          <a class="nav-item <?= $here === 'tentang.php' ? 'on' : '' ?>" href="tentang.php"><?= ikon('card') ?><span>Tentang Aplikasi</span></a>
          <?php // LOGO APLIKASI (v1.56.0): mengganti lambang aplikasi (halaman masuk, pendaftaran,
                // sidebar, favicon) dengan gambar unggahan Super Admin. ?>
          <a class="nav-item <?= $here === 'logo.php' ? 'on' : '' ?>" href="logo.php"><?= ikon('star') ?><span>Logo Aplikasi</span>
            <?php if (ada_logo_aplikasi()): ?><span class="pill pill-ok" style="margin-left:auto">diganti</span><?php endif; ?>
          </a>
          <?php // GABUNG GRUP e-GURU (v1.48.0): judul, link, dan keterangan kartu grup dashboard. ?>
          <a class="nav-item <?= $here === 'grup.php' ? 'on' : '' ?>" href="grup.php"><?= ikon('whatsapp') ?><span>Gabung Grup e-GURU</span></a>
          <?php // BIAYA & WA AKTIVASI (v1.53.0): nominal biaya aktivasi, biaya langganan, dan nomor
                // WA HD Admin yang dipakai halaman Status & Aktivasi seluruh sekolah. ?>
          <a class="nav-item <?= $here === 'biaya_aktivasi.php' ? 'on' : '' ?>" href="biaya_aktivasi.php"><?= ikon('card') ?><span>Biaya &amp; WA Aktivasi</span>
            <?php if (!aktivasi_biaya_masih_bawaan()): ?><span class="pill pill-gold" style="margin-left:auto">diubah</span><?php endif; ?>
          </a>
          <?php // JENIS PERANGKAT (v1.67.0/skema v48) — permintaan pemilik: *"Sekarang buatkan fitur
                // Kirim Perangkat di akun guru (Seperti Kalender Pendidikan, Analisis Pekan Efektif,
                // Capaian Pembelajaran, Alur Tujuan Pembelajaran, RPP/Modul Ajar. Nama-nama dokumen
                // ini di isi oleh Super Admin, bisa ditambah atau dikurangi."*
                // Menu "Perangkat Guru" lama (halaman publik + kode download) SUDAH DIHAPUS atas
                // permintaan pemilik — jangan dikembalikan. ?>
          <a class="nav-item <?= $here === 'perangkat_jenis.php' ? 'on' : '' ?>" href="perangkat_jenis.php"><?= ikon('folder') ?><span>Jenis Perangkat</span>
            <?php if ($jmlPerangkatJenis > 0): ?><span class="pill pill-ok" style="margin-left:auto"><?= (int) $jmlPerangkatJenis ?></span><?php endif; ?>
          </a>
        </div>
      </details>

      <a class="nav-item <?= $here === 'pengguna.php' && !$sekolah ? 'on' : '' ?>" href="pengguna.php"><?= ikon('key') ?><span>Akun Super Admin</span></a>

      <?php $jmlOnline = log_jumlah_online(); ?>
      <a class="nav-item <?= $here === 'log.php' ? 'on' : '' ?>" href="log.php"><?= ikon('eye') ?><span>Log Aktivitas</span>
        <?php if ($jmlOnline > 0): ?><span class="pill pill-ok" style="margin-left:auto"><?= (int) $jmlOnline ?> online</span><?php endif; ?>
      </a>

      <?php // TUGAS TAMBAHAN — sub menu (permintaan pemilik: "bagian tugas tambahan buat sub menu
            // Ketika di klik baru muncul"). Isinya seluruh menu yang diaktifkan Super Admin, urut
            // sesuai pengaturan di "Kelola Tugas Tambahan" di atas. ?>
      <?php if ($tugasAktif): ?>
        <details class="nav-grup"<?= $tugasAktifHalaman ? ' open' : '' ?>>
          <summary class="nav-item">
            <?= ikon('star') ?><span>Tugas Tambahan</span>
            <span class="pill pill-ok"><?= count($tugasAktif) ?></span>
            <span class="nav-caret" aria-hidden="true"></span>
          </summary>
          <div class="nav-sub">
            <?php foreach ($tugasAktif as $ts): ?>
              <a class="nav-item <?= ($tugasAktifHalaman && (string) ($_GET['kode'] ?? '') === (string) $ts['kode']) ? 'on' : '' ?>"
                 href="tugas.php?kode=<?= e((string) $ts['kode']) ?>"><?= ikon('star') ?><span><?= e((string) $ts['nama']) ?></span></a>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endif; ?>

      <?php if ($sekolah): ?>
        <div class="nav-sec">Sekolah yang Dikelola</div>
        <a class="nav-item <?= $here === 'index.php' && $sekolah ? 'on' : '' ?>" href="index.php?sekolah=1"><?= ikon('dashboard') ?><span>Dashboard Sekolah Ini</span></a>
      <?php endif; ?>
    <?php endif; ?>

    <?php // PENGAWAS SEKOLAH/MADRASAH: lintas sekolah binaan, HANYA MELIHAT & MENCETAK
          // (permintaan pemilik, pola aplikasi Supervisi Guru). ?>
    <?php if (is_pengawas()): ?>
      <div class="nav-sec"><?= tetap_kata('Lintas Sekolah/Madrasah') ?></div>
      <?php // DASHBOARD PENGAWAS (v1.71.6 — permintaan pemilik: *"Tambahkan menu Dashboard di atas
            // menu daftar binaan dengan isi: Grafik atau chart"*). Halaman ini menampilkan grafik
            // lintas binaan (guru/siswa per binaan, sebaran jenis, baris nilai, tren jurnal).
            // Diletakkan TEPAT DI ATAS "Daftar Binaan" — jangan dipindah ke blok lain. ?>
      <a class="nav-item <?= $here === 'dashboard_pengawas.php' ? 'on' : '' ?>" href="dashboard_pengawas.php"><?= ikon('chart') ?><span>Dashboard</span></a>
      <a class="nav-item <?= $here === 'index.php' && !$sekolah ? 'on' : '' ?>" href="index.php<?= $sekolah ? '?keluar=1' : '' ?>"><?= ikon('dashboard') ?><span>Daftar Binaan</span></a>
      <?php // PERANGKAT GURU (v1.72.0/skema v52 — PERMINTAAN PEMILIK): *"Pada akun pengawas tambahkan
            // menu Perangkat Guru (Perangkat guru ditampilkan per lembaga yang di dalamnya ada daftar
            // guru + mata pelajaran dan daftar perangkatnya). Pengawas bisa melihat, memberikan catatan
            // setiap perangkat dan mendownload"*. Diletakkan di blok LINTAS SEKOLAH supaya SELALU
            // terlihat: halaman itu sendiri yang menampilkan PEMILIH BINAAN lebih dulu.
            // Menu lama "Perangkat Guru" (perangkat_masuk.php) pada blok "Data (hanya lihat)" DIHAPUS
            // agar tidak ada dua menu bernama sama — halamannya tetap dapat dibuka langsung dan tetap
            // terdaftar pada `pengawas_berkas_boleh()`. JANGAN dikembalikan ke blok binaan. ?>
      <a class="nav-item <?= $here === 'perangkat_pengawas.php' ? 'on' : '' ?>" href="perangkat_pengawas.php"><?= ikon('folder') ?><span>Perangkat Guru</span></a>
      <?php if ($sekolah): ?>
        <div class="nav-sec">Binaan yang Dilihat</div>
        <a class="nav-item <?= $here === 'index.php' && $sekolah ? 'on' : '' ?>" href="index.php"><?= ikon('chart') ?><span>Dashboard Binaan</span></a>
        <?php // Menu data: hanya lihat (tanpa tombol pengubahan) + lembar cetak. ?>
        <div class="nav-sec">Data (hanya lihat)</div>
        <a class="nav-item <?= in_array($here, ['guru.php', 'cetak_guru.php'], true) ? 'on' : '' ?>" href="guru.php"><?= ikon('users') ?><span>Data Guru</span></a>
        <a class="nav-item <?= in_array($here, ['siswa.php', 'cetak_siswa.php', 'cetak_kartu_siswa.php'], true) ? 'on' : '' ?>" href="siswa.php"><?= ikon('student') ?><span>Data Siswa</span></a>
        <a class="nav-item <?= in_array($here, ['absensi.php', 'cetak_absensi.php'], true) ? 'on' : '' ?>" href="absensi.php"><?= ikon('clipboard') ?><span>Daftar Hadir Siswa</span></a>
        <a class="nav-item <?= in_array($here, ['nilai.php', 'cetak_nilai.php'], true) ? 'on' : '' ?>" href="nilai.php"><?= ikon('chart') ?><span>Penilaian Siswa</span></a>
        <a class="nav-item <?= in_array($here, ['jurnal.php', 'cetak_jurnal.php'], true) ? 'on' : '' ?>" href="jurnal.php"><?= ikon('book') ?><span>Jurnal Harian Guru</span></a>
        <a class="nav-item <?= in_array($here, ['kasus.php', 'cetak_kasus.php'], true) ? 'on' : '' ?>" href="kasus.php"><?= ikon('alert') ?><span>Pelanggaran Siswa</span></a>
        <?php // PERANGKAT GURU untuk binaan ini kini dibuka lewat menu "Perangkat Guru" pada blok
              // LINTAS SEKOLAH (v1.72.0) — halaman `perangkat_pengawas.php` menampilkan pemilih binaan
              // lalu daftar guru + mata pelajaran + perangkatnya beserta catatan pengawas.
              // Jangan mencetak menu bernama sama di sini (dulu `perangkat_masuk.php`). ?>
        <a class="nav-item" href="cetak_rekap_binaan.php"><?= ikon('print') ?><span>Cetak Rekap Binaan</span></a>
        <?php
        // LAPORAN TUGAS TAMBAHAN untuk binaan ini (permintaan pemilik: "Laporan Tugas Tambahan
        // tampilkan juga di akun pengawas bina untuk setiap sekolahnya") — hanya lihat & cetak.
        $tugasBinaan = tugas_menu_saring_tampil(tugas_menu_list(true));
        ?>
        <?php if ($tugasBinaan): ?>
          <div class="nav-sec">Tugas Tambahan (hanya lihat)</div>
          <a class="nav-item <?= $here === 'tugas.php' ? 'on' : '' ?>" href="tugas.php"><?= ikon('chart') ?><span>Laporan Tugas Tambahan</span></a>
          <?php foreach ($tugasBinaan as $tb): ?>
            <a class="nav-item <?= ($here === 'tugas.php' && (string) ($_GET['kode'] ?? '') === (string) $tb['kode']) ? 'on' : '' ?>"
               href="tugas.php?kode=<?= e((string) $tb['kode']) ?>"><?= ikon('star') ?><span><?= e((string) $tb['nama']) ?></span></a>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endif; ?>
      <div class="nav-sec">Aplikasi</div>
      <?php // KIRIM DOKUMEN (permintaan pemilik): pengawas mengunggah dokumen yang langsung terkirim
            // ke seluruh guru sekolah/madrasah binaannya. Diletakkan di blok Aplikasi supaya SELALU
            // terlihat — tidak perlu memilih binaan lebih dulu. Jangan dipindah ke blok binaan. ?>
      <a class="nav-item <?= $here === 'kirim_dokumen.php' ? 'on' : '' ?>" href="kirim_dokumen.php"><?= ikon('upload') ?><span>Kirim Dokumen</span></a>
      <?php // DAFTAR HADIR MEETING (v1.73.0) — permintaan pemilik: *"Pada akun pengawas tambahkan
            // menu aktifkan daftar hadir meeting (pertemuan/zoom) …"*. Diletakkan di blok Aplikasi
            // supaya SELALU terlihat (tidak perlu memilih binaan lebih dulu) — pertemuan dapat
            // diaktifkan untuk satu, beberapa, atau seluruh binaan sekaligus. ?>
      <a class="nav-item <?= in_array($here, ['meeting.php', 'cetak_meeting.php'], true) ? 'on' : '' ?>" href="meeting.php"><?= ikon('calendar') ?><span>Daftar Hadir Meeting</span>
        <?php if (meeting_jumlah_pengawas((int) ($u['id'] ?? 0)) > 0): ?><span class="pill pill-ok" style="margin-left:auto"><?= meeting_jumlah_pengawas((int) ($u['id'] ?? 0)) ?></span><?php endif; ?>
      </a>
      <?php // CATATAN (v1.71.6 — PERMINTAAN PEMILIK): menu daftar aplikasi GLOBAL (Ujian Online,
            // Smart Teacher, Aplikasi Sagu, Hasil Supervisi) DIHAPUS dari sidebar akun pengawas —
            // pemilik menandainya dengan kotak merah: *"Pada akun pengawas hapus menu yang saya
            // tandai warna merah."* JANGAN ditambahkan kembali di blok pengawas. Halaman-halaman itu
            // sendiri TETAP dapat dibuka pengawas bila diakses langsung (tetap ada di
            // `pengawas_berkas_boleh()`), yang dihapus hanya menunya. ?>
      <?php // Blok "Akun" TIDAK dicetak di sini: blok Akun umum di bawah halaman ini sudah memuat
            // "Akun Saya" + "Petunjuk Penggunaan" untuk SEMUA peran. Sebelumnya blok ini dicetak di
            // sini juga sehingga menunya muncul DUA KALI pada sidebar pengawas (v1.71.6 diperbaiki). ?>
    <?php endif; ?>

    <?php if ($sekolah && in_array($r, ['kepala', 'operator', 'guru'], true)): ?>
      <div class="nav-sec">Utama</div>
      <a class="nav-item <?= $here === 'index.php' ? 'on' : '' ?>" href="index.php"><?= ikon('dashboard') ?><span>Dashboard</span></a>
    <?php endif; ?>

    <?php if ($sekolah && in_array($r, ['kepala', 'operator', 'guru', 'super'], true)): ?>
      <div class="nav-sec">Administrasi</div>
      <?php // Data Guru memuat akun login guru lain → TIDAK ditampilkan untuk akun guru (permintaan pemilik). ?>
      <?php if ($r !== 'guru'): ?>
        <a class="nav-item <?= in_array($here, ['guru.php', 'cetak_guru.php'], true) ? 'on' : '' ?>" href="guru.php"><?= ikon('users') ?><span>Data Guru</span></a>
      <?php endif; ?>
      <a class="nav-item <?= in_array($here, ['siswa.php', 'cetak_siswa.php'], true) ? 'on' : '' ?>" href="siswa.php"><?= ikon('student') ?><span>Data Siswa</span></a>
      <a class="nav-item <?= in_array($here, ['absensi.php', 'absensi_form.php', 'cetak_absensi.php'], true) ? 'on' : '' ?>" href="absensi.php"><?= ikon('clipboard') ?><span>Daftar Hadir Siswa</span></a>
      <a class="nav-item <?= in_array($here, ['nilai.php', 'cetak_nilai.php'], true) ? 'on' : '' ?>" href="nilai.php"><?= ikon('chart') ?><span>Penilaian Siswa</span></a>
      <a class="nav-item <?= in_array($here, ['jurnal.php', 'cetak_jurnal.php'], true) ? 'on' : '' ?>" href="jurnal.php"><?= ikon('book') ?><span>Jurnal Harian Guru</span></a>
      <a class="nav-item <?= $here === 'kasus.php' ? 'on' : '' ?>" href="kasus.php"><?= ikon('alert') ?><span>Pelanggaran Siswa</span></a>
      <?php // DAFTAR HADIR MEETING (v1.73.0) — pertemuan pengawas bina yang diaktifkan untuk sekolah
            // ini; tiap akun lembaga menandatanganinya secara online. Lencana = pertemuan yang belum
            // ditandatangani akun ini. ?>
      <?php if (in_array($r, ['kepala', 'operator', 'guru'], true)): ?>
        <a class="nav-item <?= in_array($here, ['meeting_hadir.php', 'cetak_meeting.php'], true) ? 'on' : '' ?>" href="meeting_hadir.php"><?= ikon('calendar') ?><span>Daftar Hadir Meeting</span>
          <?php if ($jmlMeetingBelum > 0): ?><span class="pill pill-warn" style="margin-left:auto"><?= (int) $jmlMeetingBelum ?></span><?php endif; ?>
        </a>
      <?php endif; ?>
      <?php // "Dokumen Saya" — kotak masuk dokumen dari menu Kurikulum. Ditampilkan untuk akun GURU
            // (penerima dokumen, permintaan pemilik). Kepala/operator melihat daftar yang sama pada
            // menu Kurikulum; pengawas juga (hanya lihat). Jangan ditambahkan di blok menu lain. ?>
      <?php if ($r === 'guru'): ?>
        <a class="nav-item <?= $here === 'dokumen_saya.php' ? 'on' : '' ?>" href="dokumen_saya.php"><?= ikon('doc') ?><span>Dokumen Saya</span></a>
        <?php // KIRIM PERANGKAT (v1.67.0/skema v48) — permintaan pemilik: *"Sekarang buatkan fitur
              // Kirim Perangkat di akun guru … File yang terkirim masuk di akun Kepala sekolah …,
              // dan Pengawas … Hasil tinjauan kepala sekolah terkirim ke akun guru"*. ?>
        <a class="nav-item <?= $here === 'perangkat_saya.php' ? 'on' : '' ?>" href="perangkat_saya.php"><?= ikon('upload') ?><span>Kirim Perangkat</span>
          <?php if ($jmlPerangkatSaya > 0): ?><span class="pill pill-ok" style="margin-left:auto"><?= (int) $jmlPerangkatSaya ?></span><?php endif; ?>
        </a>
      <?php endif; ?>
      <?php // PERANGKAT MASUK (v1.67.0) — akun Kepala Sekolah/Madrasah (+ Super Admin dalam konteks)
            // meninjau kiriman perangkat guru: Disetujui / Direvisi / Ditolak + keterangannya.
            // Admin Operator & akun guru tidak melihat menunya (dokumen milik guru ditinjau kepala). ?>
      <?php if (in_array($r, ['kepala', 'super'], true)): ?>
        <a class="nav-item <?= $here === 'perangkat_masuk.php' ? 'on' : '' ?>" href="perangkat_masuk.php"><?= ikon('folder') ?><span>Perangkat Masuk</span>
          <?php if ($jmlPerangkatBaru > 0): ?><span class="pill pill-warn" style="margin-left:auto"><?= (int) $jmlPerangkatBaru ?> baru</span><?php endif; ?>
        </a>
      <?php endif; ?>
      <?php // UJIAN ONLINE — permintaan pemilik: "Dibawah menu pelanggaran siswa tambahkan menu
            // 'Ujian Online'. Isi dari menu ini buat seperti menu smart teacher" (daftar link
            // aplikasi ujian online yang diisi Super Admin). Diletakkan TEPAT di bawah Pelanggaran
            // Siswa, sebelum Smart Teacher. ?>
      <a class="nav-item <?= $here === 'ujian_online.php' ? 'on' : '' ?>" href="ujian_online.php"><?= ikon('clipboard') ?><?= daftar_apps_nama_sidebar('ujian_online', 'Ujian Online') ?><?= daftar_apps_aktivasi_pil('ujian_online') ?></a>
      <?php // Smart Teacher, Aplikasi Sagu & Hasil Supervisi: daftar aplikasi GLOBAL (diisi Super
            // Admin) — tampil untuk SEMUA pengguna, berurutan tepat di bawah Pelanggaran Siswa
            // (permintaan pemilik), dengan "Aplikasi Sagu" di bawah Smart Teacher.
            // Jangan ditambahkan lagi di blok menu lain.
            // ⚠️ v1.75.0: menu **"Studio Foto Pintar" DIHAPUS** atas permintaan pemilik ("Hapus menu
            // yang saya tandai warna merah") — aplikasi studio fotonya tetap ada sebagai salah satu
            // kartu DI DALAM menu Smart Teacher. Jangan dihidupkan kembali tanpa permintaan baru. ?>
      <a class="nav-item <?= $here === 'smart_teacher.php' ? 'on' : '' ?>" href="smart_teacher.php"><?= ikon('apps') ?><?= daftar_apps_nama_sidebar('smart_teacher', 'Smart Teacher') ?><?= daftar_apps_aktivasi_pil('smart_teacher') ?></a>
      <?php // Aplikasi Sagu: dibatasi per sekolah (lihat daftar_apps_tampil()) — sekolah yang tidak
            // dicentang Super Admin tidak melihat menunya sama sekali. ?>
      <?php if (daftar_apps_tampil('aplikasi_sagu')): ?>
        <a class="nav-item <?= $here === 'aplikasi_sagu.php' ? 'on' : '' ?>" href="aplikasi_sagu.php"><?= ikon('star') ?><span>Aplikasi Sagu</span></a>
      <?php endif; ?>
      <a class="nav-item <?= $here === 'hasil_supervisi.php' ? 'on' : '' ?>" href="hasil_supervisi.php"><?= ikon('chart') ?><span>Hasil Supervisi</span></a>
      <?php // CATATAN (v1.32.0): menu "Bendahara" yang dahulu berdiri sendiri di sini SUDAH DIHAPUS
            // — permintaan pemilik: "Menu Bendahara di bawah menu supervisi dihapus. Isi menunya
            // dipindahkan di Menu Tugas Tambahan yang Bendahara Bos." JANGAN ditambahkan kembali;
            // isinya kini dirender oleh `tugas.php?kode=…` (lihat daftar_apps_tugas_kunci()). ?>
    <?php endif; ?>

    <?php
    // ==========================================================================================
    // TUGAS TAMBAHAN — SUB MENU (permintaan pemilik, v1.57.0)
    // ------------------------------------------------------------------------------------------
    // *"Begitu juga pada halaman Kepala Sekolah dan Operator, bagian tugas tambahan buat sub menu
    //   Ketika di klik baru muncul"* → daftarnya dibungkus <details>/<summary> (bekerja tanpa
    // JavaScript) dan otomatis terbuka bila isinya sedang dibuka.
    //
    // Diletakkan DI ATAS Data Master seperti sebelumnya, dan TIDAK dirender untuk:
    //   - Super Admin → sudah punya sub menu yang sama pada blok "Lintas Sekolah/Madrasah"
    //     (kalau ikut di sini, daftarnya tampil DUA kali);
    //   - pengawas → sudah punya blok sendiri "Tugas Tambahan (hanya lihat)" pada bagian binaan
    //     (blok ini dulu ikut dirender sehingga daftarnya muncul dua kali juga).
    // ==========================================================================================
    $tugasGrupTampil = $tugasAktif && in_array($r, ['kepala', 'operator', 'guru'], true);
    $tugasGrupHalaman = $here === 'tugas.php';
    ?>
    <?php if ($tugasGrupTampil): ?>
      <details class="nav-grup"<?= $tugasGrupHalaman ? ' open' : '' ?>>
        <summary class="nav-item">
          <?= ikon('star') ?><span>Tugas Tambahan</span>
          <span class="pill pill-ok"><?= count($tugasAktif) ?></span>
          <span class="nav-caret" aria-hidden="true"></span>
        </summary>
        <div class="nav-sub">
          <?php foreach ($tugasAktif as $ts): ?>
            <a class="nav-item <?= ($tugasGrupHalaman && (string) ($_GET['kode'] ?? '') === (string) $ts['kode']) ? 'on' : '' ?>"
               href="tugas.php?kode=<?= e((string) $ts['kode']) ?>"><?= ikon('star') ?><span><?= e((string) $ts['nama']) ?></span></a>
          <?php endforeach; ?>
        </div>
      </details>
      <?php // Menu penugasan ("Pengaturan Tugas Tambahan", dulu "Pengguna Tugas Tambahan") sudah
            // DIPINDAH ke blok "Pengaturan" — permintaan pemilik: "Menu Pengguna Tugas Tambahan yang
            // ada di akun Admin Operator pindahkan letakknya dibagian Pengaturan dan Ganti nama
            // menunya menjadi 'Pengaturan Tugas Tambahan'." JANGAN dikembalikan ke blok ini. ?>
    <?php endif; ?>

    <?php
    // DATA MASTER — hanya Super Admin (dalam konteks) & Admin Operator.
    // Permintaan pemilik: "Pada akun kepala madrasah/sekolah Hapus menu data master. Pengaturan data
    // master semua lewat akun operator madrasah." → Kelas, Mata Pelajaran, dan Tahun Ajaran TIDAK
    // lagi tampil (dan tidak dapat dibuka) dari akun kepala. Identitas & Pengaturan tetap ada
    // sebagai blok tersendiri di bawah (kepala memang boleh menyesuaikannya).
    ?>
    <?php if ($sekolah && in_array($r, ['operator', 'super'], true)): ?>
      <div class="nav-sec">Data Master</div>
      <a class="nav-item <?= $here === 'kelas.php' ? 'on' : '' ?>" href="kelas.php"><?= ikon('school') ?><span>Kelas</span></a>
      <a class="nav-item <?= $here === 'mapel.php' ? 'on' : '' ?>" href="mapel.php"><?= ikon('folder') ?><span>Mata Pelajaran</span></a>
      <?php // Permintaan pemilik: MENU "Tugas Tambahan" pada blok Data Master sudah DIHAPUS dari
            // sidebar. Halamannya (`tugas_tambahan.php`) tetap ada sebagai DAFTAR RUJUKAN
            // (hanya-lihat) yang dibuka lewat tombol "Lihat Daftar" pada form Data Guru.
            // Sejak skema v30 daftar itu bersumber dari `tugas_menu` (Kelola Tugas Tambahan, Super
            // Admin) — operator/kepala TIDAK lagi menambah/mengubahnya dari akunnya. ?>
      <?php if (boleh_master()): ?>
        <a class="nav-item <?= $here === 'guru_import.php' ? 'on' : '' ?>" href="guru_import.php"><?= ikon('upload') ?><span>Impor Guru</span></a>
        <a class="nav-item <?= $here === 'siswa_import.php' ? 'on' : '' ?>" href="siswa_import.php"><?= ikon('upload') ?><span>Impor Siswa</span></a>
        <a class="nav-item <?= $here === 'kenaikan.php' ? 'on' : '' ?>" href="kenaikan.php"><?= ikon('switch') ?><span>Kenaikan &amp; Kelulusan</span></a>
      <?php endif; ?>
      <a class="nav-item <?= $here === 'tahun_ajaran.php' ? 'on' : '' ?>" href="tahun_ajaran.php"><?= ikon('calendar') ?><span>Tahun Ajaran &amp; Semester</span></a>
    <?php endif; ?>

    <?php // Pengaturan sekolah/madrasah: kepala sekolah/madrasah, Admin Operator, dan Super Admin
          // dalam konteks (permintaan pemilik: kepala tetap boleh menyesuaikan identitas).
          // Blok ini juga memuat **Pengaturan Tugas Tambahan** (penugasan menu Tugas Tambahan kepada
          // pengguna; dulu bernama "Pengguna Tugas Tambahan" dan berada di blok "Tugas Tambahan") —
          // permintaan pemilik v1.22.0. */
    ?>
    <?php if ($sekolah && boleh_pengaturan()): ?>
      <div class="nav-sec">Pengaturan</div>
      <a class="nav-item <?= $here === 'pengaturan.php' ? 'on' : '' ?>" href="pengaturan.php"><?= ikon('slider') ?><span>Identitas &amp; Pengaturan</span></a>
      <?php // Hanya Admin Operator & Kepala Sekolah/Madrasah yang boleh menugaskan (Super Admin
            // memakai Kelola Tugas Tambahan bila perlu mengubah daftar menunya sendiri). ?>
      <?php if (boleh_tugas_pengguna()): ?>
        <a class="nav-item <?= $here === 'tugas_pengguna.php' ? 'on' : '' ?>" href="tugas_pengguna.php"><?= ikon('users') ?><span>Pengaturan Tugas Tambahan</span></a>
      <?php endif; ?>
      <?php // AKUN OPERATOR (permintaan pemilik v1.54.0): DIPINDAH dari blok "Kelola Akun" ke blok
            // "Pengaturan" supaya menu lebih rapi — urutannya: Identitas & Pengaturan, Pengaturan
            // Tugas Tambahan, Akun Operator. Kepala sekolah/madrasah mengelola akun Admin Operator
            // sekolahnya sendiri; Super Admin memakai "Pengguna & Akun". ?>
      <?php if ($r === 'kepala'): ?>
        <a class="nav-item <?= $here === 'akun_operator.php' ? 'on' : '' ?>" href="akun_operator.php"><?= ikon('key') ?><span>Akun Operator</span>
          <?php if ($jmlOperator > 0): ?><span class="pill pill-uji" style="margin-left:auto"><?= (int) $jmlOperator ?></span><?php endif; ?>
        </a>
      <?php endif; ?>
    <?php endif; ?>

    <?php // LAPORAN (permintaan pemilik): blok ini memuat Rekap Jumlah Pertemuan Guru, Rekap
          // Pelanggaran Siswa (v1.26.0), dan — sejak v1.54.0 — **Notulen Rapat** yang DIPINDAH dari
          // seksi "Rapat" agar menu lebih rapi.
          // Notulen Rapat tampil untuk kepala/operator/Super Admin dalam konteks (kepala hanya
          // melihat + mencetak) dan guru yang ditugaskan sebagai Ka. Tata Usaha — cakupannya sengaja
          // LEBIH LUAS daripada dua rekap di atas, jadi syarat seksi ini memakai keduanya. ?>
    <?php $adaRekap = $sekolah && boleh_rekap_pelanggaran(); ?>
    <?php $adaNotulen = $sekolah && boleh_lihat_notulen(); ?>
    <?php if ($adaRekap || $adaNotulen): ?>
      <div class="nav-sec">Laporan</div>
      <?php if ($adaRekap): ?>
        <a class="nav-item <?= in_array($here, ['laporan_pertemuan.php', 'cetak_rekap_pertemuan.php'], true) ? 'on' : '' ?>" href="laporan_pertemuan.php"><?= ikon('chart') ?><span>Rekap Jumlah Pertemuan Guru</span></a>
        <a class="nav-item <?= in_array($here, ['laporan_pelanggaran.php', 'cetak_rekap_pelanggaran.php'], true) ? 'on' : '' ?>" href="laporan_pelanggaran.php"><?= ikon('alert') ?><span>Rekap Pelanggaran Siswa</span></a>
      <?php endif; ?>
      <?php if ($adaNotulen): ?>
        <a class="nav-item <?= in_array($here, ['notulen.php', 'notulen_form.php', 'cetak_notulen.php'], true) ? 'on' : '' ?>" href="notulen.php"><?= ikon('doc') ?><span>Notulen Rapat<?= boleh_ubah_notulen() ? '' : ' (hanya lihat)' ?></span></a>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($sekolah && $super): ?>
      <a class="nav-item <?= $here === 'pengguna.php' ? 'on' : '' ?>" href="pengguna.php"><?= ikon('key') ?><span>Pengguna &amp; Akun</span></a>
    <?php endif; ?>

    <div class="nav-sec">Akun</div>
    <?php // Status & Aktivasi: kepala sekolah/madrasah & admin operator (juga Super Admin dalam
          // konteks). PENGAWAS sengaja TIDAK diberi menu (v1.71.8) — halaman `aktivasi.php` kini boleh
          // dibukanya, tetapi isinya untuk pengawas hanya KETERANGAN status masa aktif binaan
          // (bukan menu pengelolaan), dan pengawas mencapainya lewat peringatan pada Daftar Binaan. ?>
    <?php if ($sekolah && !is_guru() && !is_pengawas()): ?>
      <?php // Halaman aktivasi: melihat sisa masa uji coba & memasukkan kode aktivasi lembaga
            // (kepala sekolah/madrasah & admin operator — permintaan pemilik). ?>
      <a class="nav-item <?= $here === 'aktivasi.php' ? 'on' : '' ?>" href="aktivasi.php"><?= ikon('key') ?><span>Status &amp; Aktivasi</span>
        <?php if ($aktivasiPill !== ''): ?><span class="pill <?= $aktivasiKelas ?>" style="margin-left:auto"><?= e($aktivasiPill) ?></span><?php endif; ?>
      </a>
    <?php endif; ?>
    <a class="nav-item <?= $here === 'akun.php' ? 'on' : '' ?>" href="akun.php"><?= ikon('user') ?><span>Akun Saya</span></a>
    <?php // PETUNJUK PENGGUNAAN — tepat di bawah "Akun Saya" untuk semua peran (permintaan pemilik). ?>
    <a class="nav-item <?= $here === 'petunjuk.php' ? 'on' : '' ?>" href="petunjuk.php"><?= ikon('book') ?><span>Petunjuk Penggunaan</span></a>
    <?php endif; // akhir blok menu untuk selain akun siswa ?>
  </nav>

  <div class="sidebar-foot">
    <div class="who">
      <?= avatar_user($u, 36) ?>
      <div class="who-txt">
        <strong><?= e((string) ($u['nama'] ?? '')) ?></strong>
        <small><?= e(role_label($r)) ?></small>
      </div>
    </div>
    <a class="btn btn-ghost btn-sm full" href="logout.php"><?= ikon('logout', 16) ?> Keluar</a>
  </div>
</aside>

<main class="main">
  <header class="topbar">
    <div>
      <h1><?= e($judul) ?></h1>
      <p class="topbar-sub"><?= $sub !== '' ? $sub : ajaran_subjudul() ?></p>
    </div>
    <div class="topbar-actions">
      <?php if ($super): ?>
        <span class="pill pill-gold"><?= ikon('star', 13) ?> Super Admin</span>
        <?php if ($sekolah): ?>
          <a class="btn btn-ghost btn-sm" href="sekolah.php"><?= ikon('switch', 15) ?> Ganti Sekolah</a>
        <?php endif; ?>
      <?php elseif (is_pengawas()): ?>
        <span class="pill pill-gold"><?= ikon('eye', 13) ?> <?= e(role_label()) ?></span>
        <?php if ($sekolah): ?>
          <?php // WAJIB memakai ?keluar=1: tanpa itu konteks binaan tidak dilepas dan pengawas tetap
                // melihat Dashboard Binaan (bukan Daftar Binaan). ?>
          <a class="btn btn-ghost btn-sm" href="index.php?keluar=1"><?= ikon('switch', 15) ?> Ganti Binaan</a>
        <?php endif; ?>
      <?php else: ?>
        <span class="pill pill-gold"><?= e(role_label($r)) ?></span>
      <?php endif; ?>
      <?php if (is_siswa()): ?>
        <?php // Akun siswa tidak mengubah profil (permintaan pemilik) → tautannya ke Data Saya. ?>
        <a class="btn btn-ghost btn-sm" href="siswa_saya.php"><?= ikon('user', 16) ?> <?= e((string) ($u['nama'] ?? '')) ?></a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="akun.php"><?= ikon('user', 16) ?> <?= e((string) ($u['nama'] ?? '')) ?></a>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($super && $sekolah): ?>
    <div class="alert alert-ok" style="margin-bottom:18px">
      <?= ikon('building', 16) ?>
      <span>Anda sedang <b>mengelola <?= tetap_kata(nama_sekolah_tampil()) ?></b>. Seluruh data yang tampil hanya milik <?= tetap_kata('sekolah/madrasah') ?> ini.</span>
      <form method="post" action="sekolah.php" style="margin-left:auto">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="keluar">
        <button class="btn btn-ghost btn-sm" type="submit">Kembali ke Super Admin</button>
      </form>
    </div>
  <?php elseif ($super && !$sekolah && $diSekolah): ?>
    <div class="alert alert-warn" style="margin-bottom:18px">
      <?= ikon('alert', 16) ?>
      <span>Pilih sekolah/madrasah terlebih dahulu pada halaman <a href="sekolah.php">Kelola Sekolah</a>.</span>
    </div>
  <?php endif; ?>

  <?php if (is_guru() && guru_saya() === null): ?>
    <div class="alert alert-warn">
      <?= ikon('alert', 16) ?>
      <span>Akun Anda belum ditautkan ke data guru, jadi daftar kelas &amp; mata pelajaran yang diampu belum muncul.
      Hubungi Admin Operator atau Super Admin untuk menautkannya.</span>
    </div>
  <?php endif; ?>

  <?php if ($sekolah && in_array($aktivasi['mode'], ['aktif', 'uji_coba'], true) && $aktivasi['habis']): ?>
    <div class="alert alert-err">
      <?= ikon('alert', 16) ?>
      <span><b><?= e($aktivasiSebutJudul) ?> lembaga ini telah berakhir</b> sehingga aplikasi terkunci:
        seluruh fitur tidak dapat dipakai sampai kode aktivasi yang baru dimasukkan.
        <b>Seluruh data lama tetap aman.</b> Hubungi <b>HD Admin</b> untuk meminta kode aktivasi yang
        baru, lalu masukkan pada <a href="aktivasi.php">halaman Status &amp; Aktivasi</a>.</span>
    </div>
  <?php elseif ($sekolah && in_array($aktivasi['mode'], ['aktif', 'uji_coba'], true) && $aktivasi['sisa_hari'] > 0 && $aktivasi['sisa_hari'] <= 7): ?>
    <div class="alert alert-warn">
      <?= ikon('clock', 16) ?>
      <span><?= e($aktivasiSebutJudul) ?> lembaga ini tersisa <b><?= (int) $aktivasi['sisa_hari'] ?> hari</b>
        (berakhir <?= e(tanggal_id($aktivasi['sampai'])) ?>). Setelah berakhir, aplikasi terkunci otomatis
        (data tetap aman) sampai <a href="aktivasi.php">kode aktivasi</a> yang baru dimasukkan — minta
        kodenya ke HD Admin.</span>
    </div>
  <?php endif; ?>

  <?php if (is_pengawas()): ?>
    <?php
    // Halaman "Kirim Dokumen" ADALAH pengecualian: di sana pengawas memang boleh mengunggah dan
    // menghapus dokumen kirimannya sendiri (permintaan pemilik), jadi kalimat "seluruh pengubahan
    // data tidak tersedia" akan menyesatkan bila ditampilkan apa adanya di halaman itu.
    $pengawasBolehKirim = $here === 'kirim_dokumen.php';
    // v1.73.0: pada halaman Daftar Hadir Meeting pengawas juga MEMBUAT pertemuan & menandatangani
    // sebagai Pemimpin Meeting, jadi kalimat "seluruh pengubahan data tidak tersedia" menyesatkan.
    $pengawasBolehMeeting = in_array($here, ['meeting.php', 'upload_meeting_ttd.php'], true);
    ?>
    <div class="alert alert-warn" style="margin-bottom:18px">
      <?= ikon('eye', 16) ?>
      <span>Anda masuk sebagai <b><?= e(role_label()) ?></b> —
        <?= $sekolah ? 'sedang melihat binaan <b>' . tetap_kata(nama_sekolah_tampil()) . '</b>. ' : '' ?>
        <?php if ($pengawasBolehKirim): ?>
          pada halaman ini Anda dapat <b>mengirim dokumen</b> kepada guru sekolah/madrasah binaan, serta
          menghapus dokumen yang Anda kirim sendiri. Data sekolah/madrasah lainnya tetap
          <b>hanya dapat dilihat dan dicetak</b>.
        <?php elseif ($pengawasBolehMeeting): ?>
          pada halaman ini Anda dapat <b>membuat pertemuan/meeting</b>, mengaktifkan daftar hadirnya
          untuk binaan yang Anda pilih (atau seluruh binaan), dan menandatangani sebagai
          <b>Pemimpin Meeting</b>. Data sekolah/madrasah lainnya tetap <b>hanya dapat dilihat dan
          dicetak</b>.
        <?php else: ?>
          akun ini <b>hanya dapat melihat dan mencetak</b>; seluruh pengubahan data tidak tersedia.
        <?php endif; ?>
      </span>
      <?php if ($sekolah): ?>
        <form method="post" action="index.php" style="margin-left:auto">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="keluar">
          <button class="btn btn-ghost btn-sm" type="submit">Kembali ke daftar binaan</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (is_kepala()): ?>
    <div class="alert alert-ok" style="margin-bottom:18px">
      <?= ikon('eye', 16) ?>
      <span>Anda masuk sebagai <b><?= e(role_label()) ?></b> — seluruh data dapat dilihat dan dicetak; pengisian administrasi dilakukan Admin Operator dan Guru.</span>
    </div>
  <?php endif; ?>

  <?php foreach ($flashes as $f): ?>
    <div class="alert <?= $f['tipe'] === 'ok' ? 'alert-ok' : ($f['tipe'] === 'warn' ? 'alert-warn' : 'alert-err') ?>">
      <?= ikon($f['tipe'] === 'ok' ? 'check' : 'alert', 16) ?><span><?= e($f['pesan']) ?></span>
    </div>
  <?php endforeach; ?>

  <?php
  // BANNER WAJIB GANTI KATA SANDI (v1.74.0). Seluruh halaman pengelolaan sudah dialihkan ke
  // akun.php oleh gerbang_sandi(); banner ini untuk halaman yang tetap BOLEH dibuka (Status &
  // Aktivasi, Akun Saya) supaya pengguna tahu mengapa menunya terbatas. Di akun.php sendiri
  // sudah ada popup + kartu formulir, jadi bannernya tidak dicetak.
  $__berkasSandi = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
  if (sandi_wajib_ganti() && $__berkasSandi !== 'akun.php') { ?>
    <div class="banner-sandi" id="bannerWajibSandi">
      <?= ikon('lock', 18) ?>
      <span><b>Kata sandi Anda wajib diganti.</b> Menu lain belum dapat dibuka sampai kata sandi
        memenuhi kebijakan (<?= e(sandi_kebijakan_teks()) ?>).</span>
      <a class="btn btn-primary btn-sm" href="akun.php?wajib_sandi=1#cardSandi">Ganti Sekarang</a>
    </div>
  <?php }
  unset($__berkasSandi); ?>

  <div class="content">
<?php
    // Popup "Selamat datang" (pengumuman sambutan): dicetak page_bottom() di akhir halaman,
    // tetapi PENANDANYA ditetapkan di sini — dan hanya sekali per login (lihat sambutan_* ()).
    $GLOBALS['__vs_sambutan_ditampilkan'] = sambutan_perlu_tampil();
}

function page_bottom(): void
{
    $sekolah = current_sekolah();
    ?>
  </div>
  <footer class="foot">
    <span><?= e(APP_NAME) ?> — <?= e(APP_SUB) ?> v<?= e(APP_VERSION) ?> &middot;
      <?= $sekolah ? tetap_kata(nama_sekolah_tampil()) : 'Super Admin &middot; ' . jumlah_sekolah() . ' sekolah/madrasah' ?></span>
    <span>Dikembangkan oleh <?= e(APP_AUTHOR) ?> (<?= e(APP_AUTHOR_JABATAN) ?>)</span>
  </footer>
</main>
<?php // POPUP "Selamat datang": muncul SEKALI setiap kali pengguna masuk (isi diatur Super Admin
      // pada halaman Pengumuman Sambutan). Diletakkan paling bawah supaya tidak mengganggu tata letak.
      // Sejak v1.71.5 popup ini TIDAK dicetak saat lembaga berada di jendela peringatan aktivasi
      // (sisa ≤ 7 hari) — lihat sambutan_perlu_tampil(); penjaga `$__adaSambutan` di bawah tinggal
      // sebagai jaring pengaman agar dua <dialog> tidak pernah bertumpuk. ?>
<?php $__adaSambutan = !empty($GLOBALS['__vs_sambutan_ditampilkan']) ? sambutan_render() : false; ?>
<?php // POPUP PERINGATAN AKTIVASI (v1.71.4 — permintaan pemilik): masa aktif/uji coba lembaga tersisa
      // ≤ 7 hari. Tampil di SETIAP halaman seluruh pengguna lembaga itu sampai benar-benar habis.
      // Tidak dicetak pada halaman popup sambutan (agar tidak ada dua dialog bertumpuk) dan pada
      // halaman Status & Aktivasi (di sana sudah ada hitung mundur + kolom kode — popup hanya
      // menghalangi pekerjaan yang sedang dilakukan). ?>
<?php if (empty($__adaSambutan) && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'aktivasi.php') { aktivasi_ingat_render(); }
      unset($__adaSambutan); ?>
<?php // KOTAK OBROLAN MENGAMBANG (permintaan pemilik, v1.51.0): komunikasi sesama pengguna aplikasi
      // yang sedang online — satu ruang bersama untuk semua peran, tampil di pojok kanan bawah pada
      // SEMUA halaman berpengguna (lembar cetak tidak memakai layout sehingga tidak ikut). ?>
<?php chat_kotak(); ?>
<script src="assets/app.js?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
<?php
    terjemahkan_selesai();
}

/**
 * Cetak popup sambutan + tandai sudah ditampilkan.
 *
 * Dipisah dari page_top() supaya markup-nya berada di akhir halaman (pola yang sama dengan popup
 * Donasi/Sedekah pada aplikasi Smart Teacher), sedangkan PENANDA-nya (`$GLOBALS`) ditetapkan
 * page_top() saat halaman mulai dirender. Penanda sesi langsung dibersihkan di sini agar popup
 * hanya muncul sekali per login walau pengguna berpindah-pindah halaman.
 */
function sambutan_render(): bool
{
    // Hanya pengumuman untuk SASARAN pengguna yang sedang masuk (siswa ↔ pengguna aplikasi).
    $daftar = pengumuman_list(true, pengumuman_sasaran_saya());
    sambutan_selesai();
    if ($daftar === []) {
        return false;
    }
    $u = current_user();
    $nama = (string) ($u['nama'] ?? '');
    ?>
<dialog class="popup popup-sambutan" id="popupSambutan" aria-labelledby="popupSambutanJudul">
  <div class="popup-head">
    <div class="popup-sambutan-ic"><?= ikon('star', 20) ?></div>
    <div>
      <h3 id="popupSambutanJudul">Selamat Datang<?= $nama !== '' ? ', ' . e($nama) : '' ?></h3>
      <p class="popup-sub"><?= e(role_label()) ?><?= current_sekolah() ? ' &middot; ' . tetap_kata(nama_sekolah_tampil()) : '' ?></p>
    </div>
  </div>

  <div class="popup-sambutan-isi">
    <?php foreach ($daftar as $i => $p): ?>
      <div class="pengumuman-item">
        <div class="pengumuman-item-head">
          <span class="pengumuman-no"><?= $i + 1 ?></span>
          <b><?= e((string) $p['judul']) ?></b>
        </div>
        <div class="pengumuman-teks"><?= nl2br(e((string) $p['isi'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="popup-foot">
    <span class="xs mut">Semoga Aplikasi ini membawa kemudahan dan keberkahan untuk kita semua</span>
    <button class="btn btn-primary" type="button" data-sambutan="tutup" autofocus>Tutup</button>
  </div>
</dialog>
    <?php
    return true;
}

/**
 * Cetak POPUP PERINGATAN AKTIVASI (v1.71.4) — masa aktif / masa uji coba lembaga tersisa ≤ 7 hari.
 *
 * Permintaan pemilik: *"Pada madrasah yang masa aktifnya sisa 7 hari tampilkan pop up peringatan agar
 * segera melakukan aktifasi melalui akun kepala sekolah atau operator. Popup ini muncul di akun semua
 * pengguna pada lembaga tersebut sampai masa aktif benar-benar habis."*
 *
 * Kapan dicetak: `aktivasi_peringatan()` (inc/helpers.php) — satu tempat aturan, jangan diulang di
 * sini. Popup dicetak pada SETIAP halaman berpengguna (bukan sekali per login) SELAMA sisanya ≤ 7
 * hari dan belum habis; setelah masa aktif habis, `gerbang_aktivasi()` mengunci aplikasi dan kartu
 * "Aplikasi Terkunci" di halaman Status & Aktivasi yang mengambil alih.
 *
 * Popup memakai `<dialog>` + atribut `open` sehingga isinya tetap terbaca walau `assets/app.js` gagal
 * dimuat; `initPeringatanAktivasi()` menaikkannya menjadi modal & menangani tombol Tutup.
 */
function aktivasi_ingat_render(): void
{
    $p = aktivasi_peringatan();
    if ($p === null) {
        return;
    }
    $sekolah = current_sekolah();
    $namaSekolah = $sekolah ? nama_sekolah_tampil($sekolah) : '';
    ?>
<dialog class="popup popup-peringatan" id="popupPeringatanAktivasi" aria-labelledby="popupPeringatanJudul">
  <div class="popup-head">
    <div class="popup-peringatan-ic"><?= ikon('clock', 20) ?></div>
    <div>
      <h3 id="popupPeringatanJudul"><?= e($p['judul']) ?></h3>
      <p class="popup-sub"><?= e(role_label()) ?><?= $namaSekolah !== '' ? ' &middot; ' . tetap_kata($namaSekolah) : '' ?></p>
    </div>
  </div>

  <div class="popup-peringatan-isi">
    <?php $sisa = (int) $p['sisa_hari']; ?>
    <div class="peringatan-angka">
      <?php if ($sisa > 0): ?>
        <b><?= $sisa ?></b><span>hari lagi</span>
      <?php else: ?>
        <b class="peringatan-angka-kecil">Hari terakhir</b><span>berakhir hari ini</span>
      <?php endif; ?>
    </div>
    <div class="peringatan-teks">
      <p><?= ucfirst(e($p['sebut'])) ?> aplikasi ini <?= $sisa > 0 ? 'tinggal <b>' . $sisa . ' hari</b>' : 'berakhir <b>hari ini</b>' ?><?= $p['sampai'] !== '' ? ' (berakhir <b>' . e(tanggal_id($p['sampai'], true)) . '</b>)' : '' ?>.
        Setelah berakhir, <b>aplikasi terkunci otomatis</b> dan seluruh fitur tidak dapat dipakai
        sampai kode aktivasi yang baru dimasukkan. <b>Seluruh data lama tetap aman.</b></p>
      <?php if ($p['boleh']): ?>
        <p><b>Segera lakukan aktivasi</b> melalui akun Kepala <?= tetap_kata('Sekolah/Madrasah') ?>
          atau <b>Admin Operator</b> — tombol di bawah membuka halaman Status &amp; Aktivasi untuk
          memasukkan kode aktivasi yang dikirim HD Admin.</p>
      <?php else: ?>
        <p><b>Aktivasi dilakukan oleh akun Kepala <?= tetap_kata('Sekolah/Madrasah') ?> atau Admin
          Operator</b> lembaga ini. Silakan hubungi mereka agar masa <?= e($p['sebut']) ?> segera
          diperpanjang sebelum aplikasi terkunci.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="popup-foot">
    <span class="xs mut">Peringatan ini tampil sampai masa <?= e($p['sebut']) ?> berakhir atau lembaganya diaktifasi.</span>
    <button class="btn btn-ghost" type="button" data-peringatan="tutup">Tutup</button>
    <?php if ($p['boleh']): ?>
      <a class="btn btn-primary" href="aktivasi.php">Lakukan Aktivasi Sekarang</a>
    <?php endif; ?>
  </div>
</dialog>
    <?php
}

/** Tutup penampung keluaran halaman & terapkan penyesuaian istilah Sekolah/Madrasah. */
function terjemahkan_selesai(): void
{
    $level = (int) ($GLOBALS['__vs_buf_level'] ?? 0);
    if ($level > 0 && ob_get_level() === $level) {
        // buang_penanda() = jaring pengaman terakhir: penanda internal tetap_kata() (\x01/\x02)
        // tidak boleh pernah sampai ke peramban (peramban menggambarnya sebagai kotak).
        echo buang_penanda(terjemahkan_html((string) ob_get_clean()));
        $GLOBALS['__vs_buf_level'] = 0;
    }
}
