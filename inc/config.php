<?php
/**
 * Konfigurasi dasar aplikasi e-GURU (Administrasi Guru Digital).
 *
 * Aplikasi MULTI-SEKOLAH (lintas sekolah/madrasah): identitas sekolah & tahun ajaran
 * disimpan pada tabel `sekolah` (lihat inc/db.php + inc/helpers.php).
 * Unggahan berkas pengguna (foto profil, logo sekolah) lewat media proxy platform.
 */
declare(strict_types=1);

define('APP_NAME', 'e-GURU');
define('APP_SUB', 'Administrasi Guru Digital');
// 1.16.0 = menu Wali Kelas (skema v29); 1.17.0 = Super Admin dapat MENAMBAH tugas tambahan baru
// pada menu Kelola Tugas Tambahan (kode menu dibuat otomatis dari namanya);
// 1.18.0 = SATU daftar Tugas Tambahan: pilihan pada Data Guru bersumber dari `tugas_menu`
// (diisi Super Admin) — tabel per sekolah `tugas_tambahan` dihapus (skema v30) dan halaman
// "Tugas Tambahan" di akun operator/kepala menjadi HANYA LIHAT;
// 1.19.0 = (a) MENU DATA MASTER (Kelas, Mata Pelajaran, Tahun Ajaran) DIHAPUS dari akun kepala
// sekolah/madrasah — pengelolaannya lewat akun operator (Identitas & Pengaturan tetap untuk kepala);
// (b) kepala & operator HANYA melihat + mencetak ISI menu Tugas Tambahan (pengisian oleh guru yang
// ditugaskan / Super Admin); (c) menu NOTULEN RAPAT pindah ke akun guru "Ka. Tata Usaha"
// (kepala & operator hanya lihat + cetak); (d) menu nilai menjadi "Penilaian Kurikulum Merdeka"
// dengan keterangan predikat A (Amat Baik), B (Baik), C (Cukup), D (Kurang);
// 1.20.0 = (a) KEPALA boleh MENCETAK Data Guru & Data Siswa (cetak_guru.php, cetak_siswa.php,
// cetak_kartu_siswa.php) — ekspor CSV tetap operator/Super Admin; (b) **GURU dapat mengatur sendiri**
// penilaian tiap MATA PELAJARAN yang diampu (jumlah kolom Formatif/Sumatif, bobot ASAS, ambang
// predikat A/B/C/D) lewat tombol "Pengaturan Penilaian" di halaman Penilaian Siswa — pengaturan guru
// menimpa pengaturan sekolah untuk mapel itu (tabel `nilai_pengaturan`, skema v31);
// (c) tulisan menu "Penilaian Kurikulum Merdeka" menjadi **"Penilaian Siswa"**.
// 1.20.1 = perbaikan kartu Pengaturan Penilaian: kartu/ tombolnya bisa dibuka (kartu <details>
// dibuka skrip saat tombol diklik / hash #cardPengaturanNilai) dan pengalihan setelah menyimpan
// membawa KELAS yang sedang dilihat supaya grid nilai tidak hilang.
// 1.21.0 = (a) **Admin Operator HANYA melihat & mencetak** pada menu Daftar Hadir Siswa, Penilaian
// Siswa, Jurnal Harian Guru, dan Pelanggaran Siswa (tidak bisa menambah pertemuan/nilai/
// jurnal/pelanggaran baru — pengisian oleh guru pengampu, Super Admin boleh membantu); penghapusan
// catatan pertemuan/pelanggaran kini hanya Super Admin; (b) **Notulen Rapat: Admin Operator boleh
// MEMBUAT lagi** (bersama guru yang ditugaskan sebagai Ka. Tata Usaha); kepala sekolah/madrasah
// tetap hanya melihat + mencetak.
// 1.22.0 = menu penugasan Tugas Tambahan ("Pengguna Tugas Tambahan") DIPINDAH ke bagian **Pengaturan**
// pada sidebar dan namanya diganti menjadi **"Pengaturan Tugas Tambahan"** (halaman, judul, & seluruh
// rujukan nama menu disesuaikan).
// 1.23.0 = menu **"Catatan Pelanggaran Siswa" berganti nama menjadi "Pelanggaran Siswa"**
// (sidebar, judul halaman, daftar menu portal login, kop & judul lembar cetak, pintasan dashboard,
// serta judul kartu di halaman itu). Label lain yang memakai kata "kasus" tidak diubah.
// 1.24.0 = (a) SLIDER "Gambaran Umum Menu" pada halaman masuk ditambah 3 menu (Smart Teacher,
// Hasil Supervisi, Tugas Tambahan Guru) sehingga total 11 menu (6 slide) dan entri "Daftar Nilai
// Siswa" diselaraskan menjadi "Penilaian Siswa"; (b) kartu "Apa yang Anda dapatkan" pada halaman
// pendaftaran sekolah DIHAPUS beserta gaya `.langkah-ico` yang jadi tidak terpakai.
// 1.25.0 = ISI kartu **"Tentang Aplikasi"** pada halaman masuk diganti dengan teks yang ditetapkan
// pemilik: menyebut daftar tugas tambahan guru (kesiswaan, sarpras, wali kelas, kepala lab, kepala
// perpustakaan, kepala tu, pembina pramuka, pembina OSIM/OSIS, pembina PMR, bendahara), kalimat
// penutup "semua dokumen tersimpan rapi & bisa dicetak", dan paragraf multi-sekolah/madrasah.
// 1.26.0 = MENU **"Rekap Pelanggaran Siswa"** pada akun kepala sekolah/madrasah (+ Super Admin dalam
// konteks): tabel 20 siswa dengan pelanggaran terbanyak (Nama Siswa, NISN, Kelas, Jumlah Pelanggaran,
// Lihat Pelanggaran) + rekap total pelanggaran yang diinput setiap guru mata pelajaran, dan lembar
// cetaknya. Tautan "Lihat Pelanggaran" membuka menu Pelanggaran Siswa dengan filter siswa tersebut
// (`kasus.php?siswa=ID`, ikut pada cetak laporan).
// 1.27.0 = **Jadwal Piket (menu Wali Kelas) menambah HARI MINGGU** — `wali_hari()` di
// `inc/wali.php` kini 7 hari (Senin…Sabtu + Minggu) sehingga form centang, tabel ringkasan,
// penyimpanan, dan lembar cetak `cetak_wali_piket.php` semuanya ikut (satu sumber daftar hari).
// 1.48.0 = **MENU SUPER ADMIN "GABUNG GRUP e-GURU"** — permintaan pemilik: "Tambahkan menu baru di
// akun super admin untuk mengedit fitur Gabung Grup e-Guru. Inputan berupa: Judul Grup, Link Grup,
// Keterangan" → halaman baru `grup.php` (khusus Super Admin) untuk mengubah JUDUL, LINK, dan
// KETERANGAN kartu grup yang tampil di dashboard; isinya disimpan tingkat APLIKASI
// (`app_pengaturan`: `grup_judul`, `grup_url`, `grup_ket`) dengan konstanta `GRUP_EGURU_*` sebagai
// nilai bawaan. Tautan wajib http/https (validasi server), dan kartu hanya mencetak tombol bila
// tautannya sah.
// 1.47.0 = **KARTU "GABUNG GRUP e-GURU"** pada dashboard akun Kepala Sekolah/Madrasah, Admin
// Operator, dan Guru — permintaan pemilik: "Pada Akun Kepala Sekolah, Operator dan Guru pada halaman
// dasboard tambahkan Fitur Gabung Grup e-Guru (link: https://chat.whatsapp.com/CSfXdQ8NE07250AJtZ4tJL)
// tambahkan keterangan 'Dapatkan info menarik dan update aplikasi dengan bergabung bersama grup
// premium e-Guru'. Posisi fitur letakkan sejajar dengan kartu pengawas bina (kartu pengawas bina di
// dashboard guru dan kepala madrasah jangan dipanjangkan)" → `kartu_grup_eguru()` (inc/helpers.php)
// diletakkan SEJAJAR dengan kartu Pengawas Bina (`index.php`, pembungkus `.grid.g2.atas-dashboard`)
// dengan `align-items:start` supaya kartu Pengawas Bina TIDAK ikut dipanjangkan mengikuti tinggi
// kartu di sebelahnya. Tautan & keterangan ada di konstanta `GRUP_EGURU_URL`/`GRUP_EGURU_KET`.
// 1.46.0 = **KARTU "PENGAWAS BINA" HANYA NAMA + FOTO** — permintaan pemilik: "Pengawas Bina yang di
// tampilkan di dashboard akun guru dan kepala sekolah Hanya Nama dan Foto profil saja. Nama Pengguna
// dan terakhir masuk tidak perlu" → kartu di `index.php` (lewat `kartu_pengawas_bina()`) TIDAK lagi
// mencetak "Nama pengguna: …" maupun "Terakhir masuk: …"; yang tampil hanya foto profil + nama (+
// keterangan perannya).
// 1.45.0 = **KARTU "PENGAWAS BINA" PADA DASHBOARD KEPALA & GURU** — permintaan pemilik: "Pada
// halaman akun kepala madrasah dan akun guru tampilkan Pengawas Binanya (Foto dan Nama) agar mereka
// tahu siapa pengawas binanya. Ditampilkan pada bagian dashboard (pada bagian atas atau sesuaikan
// agar terlihat rapi)" → `kartu_pengawas_bina()` (inc/helpers.php) menampilkan FOTO (avatar_user,
// 56 px) + NAMA + peran + nama pengguna + terakhir masuk, dicetak di `index.php` cabang dashboard
// sekolah **sebelum kartu statistik** untuk peran `kepala` dan `guru` (Super Admin dalam konteks &
// Admin Operator tidak — bukan pengguna yang diminta). Bila belum ada pengawas bina, kartunya tetap
// dicetak dengan keterangan ramah. Helper: `pengawas_bina_sekolah()`, `pengawas_bina_jumlah()`.
// 1.44.0 = **ATURAN "1 SEKOLAH/MADRASAH HANYA UNTUK 1 PENGAWAS** — permintaan pemilik: "Pada saat
// memilih madrasah binaan untuk pengawas, pastikan madrasah yang sudah di pilih tidak bisa di pilih
// atau dicentang lagi untuk pengawas lainnya. Sehingga 1 madrasah hanya untuk 1 pangawas" → kotak
// sekolah yang sudah dibina pengawas lain **dinonaktifkan** di formulir `pengawas.php` (dengan
// keterangan pembinanya) dan **ditegakkan juga di SERVER** (`simpan_binaan()` melewatinya + memberi
// pesan); "Pilih semua" tidak mencentang kotak terkunci; data LAMA yang masih dibina >1 pengawas
// hanya DIPERINGATKAN (tidak dihapus otomatis). Helper: `pengawas_binaan_oleh()`,
// `pengawas_binaan_peta()`, `pengawas_binaan_ganda()`.
// 1.43.0 = **DAFTAR AKUN PENGAWAS MENJADI TABEL RAPI** — permintaan pemilik: "Pada halaman super
// admin bagian daftar akun pengawas. Daftar Akun pengawas dirubah menjadi tabel rapi yang isinya:
// Nama Pengawas, Madrasah Binaan (Aktif), Aksi (Ubah & Binaan, Non aktifkan, Hapus)" → halaman
// `pengawas.php` memakai satu tabel `table.tbl` (#tblPengawas) berkolom **Nama Pengawas** (nama,
// pil status/peran, nama pengguna, masuk terakhir), **Madrasah Binaan (Aktif)** (chip binaan aktif +
// hitungan; binaan nonaktif hanya dihitung pada keterangan), dan **Aksi** (Ubah & Binaan,
// Nonaktifkan/Aktifkan, Hapus) + baris JUMLAH. Kartu per akun dihapus.
// 1.42.0 = **MENU TUGAS TAMBAHAN "PEMBINA PMR" BERISI SAMA SEPERTI PEMBINA OSIM/OSIS** —
// permintaan pemilik: "Tugas Tambahan Pembina PMR isi menu buat sama seperti Pembina OSIM/OSIS"
// → menu `pmr` memakai modul yang sama (`inc/osim.php`) sehingga berisi Susunan Organisasi + bagan +
// Program Kerja + impor + ekspor + cetak, dengan SATU perbedaan mengikuti pola Pembina Pramuka:
// jabatan baku baris ke-3 berbunyi **"Pembina PMR"**. Data dipisah kolom `kode` (`osim`/`pramuka`/`pmr`)
// dan judul/periode bagannya disimpan pada kolom sendiri (`sekolah.pmr_bagan_*`, skema v38).
// 1.41.0 = **MEDAN NSM HANYA UNTUK MADRASAH PADA HALAMAN PENDAFTARAN** — permintaan pemilik:
// "Pada halaman pendaftaran ketika Jenis yang dipilih sekolah umum maka kolom NSM tidak perlu
// ditampilkan karena di sekolah umum tidak ada NSM" → blok NSM (#blokNsm) disembunyikan saat jenis
// "Sekolah Umum" (atribut `hidden` dicetak SERVER saat halaman dirender ulang + skrip
// initJenisNsm() mengikuti pilihan di peramban), nilainya pun tidak disimpan untuk sekolah umum.
// Sekaligus tema diberi aturan `[hidden]{display:none !important}` karena banyak wadah memakai
// display:grid/flex yang mengalahkan gaya bawaan peramban.
// 1.40.0 = **LABEL TANDA TANGAN KEPALA MENJADI DUA BARIS** — permintaan pemilik: *"Pada dokumen cetak
// tulisan: Mengetahui, Kepala Madrasah (sejajar) Ganti dengan: 'Mengetahui,' dibawahnya 'Kepala
// Madrasah'. Sehingga nama guru dan nama kepala sekolah/madrasah menjadi sejajar/lurus"* →
// helper `ttd_mengetahui()` ("Mengetahui,\n" + jabatan kepala) dipakai SELURUH lembar cetak lewat
// `ttd_blok()` (jabatan dirender `nl2br`), dan tinggi baris "tempat, tanggal" disamakan dengan satu
// baris jabatan sehingga NAMA kepala & NAMA guru tercetak sejajar (terukur 0 px). Blok kiri yang
// labelnya hanya satu baris (Pimpinan Rapat pada berita acara notulen) diberi `ttd-spacer`.
// 1.39.0 = **IDENTITAS LEMBAGA diperluas + TEMPAT/TANGGAL pada tanda tangan dokumen cetak** —
// permintaan pemilik: (1) pilihan jenis menjadi "Sekolah Umum (SD/SMP/SMA/SMK)" & "Madrasah
// (MI/MTs/MA/MAK)"; (2) Identitas & Pengaturan memuat Nama, NPSN, **NSM**, Alamat (Nama Jalan,
// Dusun), **Desa/Kelurahan, Kecamatan, Kabupaten, Provinsi**, email, situs/laman, Nama & NIP kepala
// (halaman pendaftaran ikut menyesuaikan; skema v37); (3) pada SEMUA hasil cetak, di atas tanda
// tangan guru ditambahkan **tempat (nama kecamatan dari identitas) + tanggal aktif sistem**, mis.
// "Mootilango, 30 September 2026" — lihat ttd_tempat_tanggal() & ttd_blok().
// 1.38.0 = **RINGKASAN LINTAS SEKOLAH: baris JUMLAH pada tabel "Daftar Sekolah/Madrasah"** —
// permintaan pemilik: "Pada halaman super admin bagian Ringkasan Sekolah - pada bagian daftar
// sekolah/madrasah tambahkan jumlah total agar bisa mengetahui jumlah total kelas, siswa, guru,
// jurnal, nilai" → total kelas, guru, siswa, mapel, jurnal, dan nilai dijumlahkan dari seluruh
// sekolah/madrasah aktif yang terdaftar (baris JUMLAH + disebut pada kepala kartu).
// 1.37.0 = **JURNAL HARIAN GURU: kolom "Uraian Kegiatan" + "Kelas/Tempat"** — permintaan pemilik:
// "Pada bagian Input Jurnal Harian Baru tambahkan kolom 'Uraian Kegiatan (Pilihan Mengajar bisa juga
// input manual jika kegiatan selain mengajar)' setelah kolom tanggal. Pada kolom kelas berubah
// menjadi 'Kelas/Tempat' selain pilih kelas guru bisa menginput manual tempatnya. Mata Pelajaran dan
// materi/topik pembelajaran bisa dikosongkan jika kegiatan guru selain mengajar" (skema v36).
// 1.36.0 = **KOLOM FORMATIF boleh berisi ANGKA, HURUF, atau TANDA CENTANG** — permintaan pemilik:
// "Pada lembar penilaian formatif guru bisa menginput nilai berupa angka, huruf atau tanda centang."
// Isian Formatif menjadi isian teks dengan **daftar saran** ✓/A/B/C/D (angka tetap bisa diketik),
// nilainya disimpan apa adanya (angka tetap tersimpan sebagai angka), dan **rata-rata kelas pada
// kolom Formatif hanya menghitung sel yang berisi angka** sehingga huruf/centang tidak dianggap 0.
// Nilai Sumatif Lingkup Materi & ASAS tetap ANGKA (tetap dipakai perhitungan nilai akhir).
// 1.49.0 = **IDENTITAS PENGAWAS BINA (nama & NIP)** pada identitas lembaga (skema v39) —
// permintaan pemilik: "Pada halaman pendaftaran tambahkan inputan Nama Pengawas Bina dan NIP.
// Posisi dibawah nama kepala sekolah". Medan baru diletakkan tepat di bawah Nama/NIP Kepala pada
// halaman pendaftaran (`daftar.php`), tersimpan pada kolom `sekolah.pengawas_nama`/`pengawas_nip`,
// dan dapat diubah/dilengkapi di Identitas & Pengaturan maupun Kelola Sekolah (Super Admin).
// 1.50.0 = **AKUN PENGAWAS OTOMATIS DARI PENDAFTARAN** (skema v40) — permintaan pemilik:
// "Nama Pengawas yang diinput dari halaman pendaftaran, otomatis masuk di akun super admin di daftar
// akun pengawas tapi status masih non aktif untuk ditinjau oleh super admin". Nama pengawas bina yang
// diisi pendaftar langsung membuat akun pengawas (role `pengawas`, NONAKTIF, kata sandi acak) yang
// ditandai `users.ditinjau = 0` sehingga muncul sebagai **"Menunggu ditinjau"** pada halaman
// **Akun Pengawas**, lengkap dengan kartu tinjauan + lencana pada menu sidebar; sekolah pendaftar
// otomatis menjadi **binaan** akun itu. Menyimpan perubahan/mengaktifkan/menandai tinjauan
// menghilangkan penanda tersebut. Bonus: isian "Kata Sandi Baru" pada formulir Ubah akun pengawas
// kini benar-benar bekerja (sebelumnya diabaikan) sehingga sandi akun hasil pendaftaran dapat
// ditetapkan Super Admin saat meninjau.
// 1.50.1 = **panduan menu Akun Pengawas dilengkapi**: Petunjuk Penggunaan (Super Admin) menjelaskan
// bahwa nama pengawas bina yang diisi pada halaman pendaftaran masuk otomatis sebagai akun NONAKTIF
// bertanda "Menunggu ditinjau" yang perlu ditinjau lebih dulu.
// 1.51.0 = **FITUR OBROLAN (CHAT) sesama pengguna aplikasi yang sedang online** (skema v41) —
// permintaan pemilik: "Tambahkan fitur chat agar sesama pengguna aplikasi yang sedang online bisa
// berkomunikasi". Pilihan pemilik: SATU ruang bersama untuk semua pengguna (lintas sekolah/madrasah
// + Super Admin), dipakai SEMUA PERAN (termasuk pengawas), pesan baru muncul OTOMATIS tiap beberapa
// detik, dan tampil sebagai **KOTAK MENGAMBANG di pojok kanan bawah pada semua halaman**.
// Tabel `chat_pesan` (snapshot nama/asal sekolah pengirim) + `chat_baca` (id pesan terakhir dibaca
// → lencana belum dibaca); endpoint `chat.php` (JSON) + modul `inc/chat.php`.
// 1.51.1 = **panduan menu Obrolan ditambahkan pada Petunjuk Penggunaan** (baris "Obrolan (kotak
// mengambang)" untuk semua peran; satu-satunya panduan tanpa halaman tersendiri). TANPA perubahan
// perilaku.
// 1.55.0 = **KODE AKTIVASI PERPANJANGAN + ATURAN SEKALI PAKAI** — permintaan pemilik: "Tampilkan
// fitur untuk menginput kode aktifasi perpanjangan. Pastikan 1 kode aktifasi hanya bisa digunakan
// sekali input (tidak bisa digunakan lebih dari 1 kali). Sehingga kode aktifasi yang muncul di
// super admin akan berubah jika sudah dipakai". Perbaikannya:
//   • `kode_aktivasi_sekolah()` kini ikut menghitung **periode** (`sekolah.aktivasi_ke`) pada HMAC-nya
//     → kode BERGANTI setiap periode, sehingga satu kode hanya sah SEKALI pakai (sebelumnya satu kode
//     berlaku selamanya / bisa dipakai berulang — persis keluhan pemilik);
//   • helper baru `aktivasi_periode()` (tampilan "periode ke-N") dan tampilan Super Admin
//     (Kelola Sekolah, Pendaftaran, halaman Aktivasi) menyebut periode + aturan sekali pakai;
//   • `aktivasi_aktifkan()` untuk **PERPANJANGAN**: bila masa aktif masih berjalan, tambahan hari
//     dihitung dari **tanggal berakhir yang masih berlaku** (sisa waktu tidak hangus); bila sudah
//     habis/belum pernah ada, dihitung dari hari ini;
//   • halaman Status & Aktivasi menampilkan **fitur input kode perpanjangan** (judul, label, dan
//     tombol berubah menjadi "perpanjangan") dan tetap tersedia selagi masa aktif berjalan;
//     sekolah `permanen` diberi keterangan tidak perlu kode.
//
// 1.54.0 = **BIAYA AKTIVASI PERTAMA HANYA UNTUK YANG BELUM PERNAH AKTIVASI + PENATAAN MENU**
// — permintaan pemilik:
//   (1) "Biaya aktifasi pertama hanya berlaku untuk yang belum pernah melakukan aktifasi. Sehingga
//       Bagi yang sudah melakukan aktifasi pertama, hilangkan biaya untuk aktifasi pertama dan yang
//       ditampilkan hanya biaya langganan untuk tahun berikutnya." → pada halaman Status & Aktivasi,
//       lembaga yang SUDAH pernah aktivasi tidak lagi melihat nominal biaya aktivasi pertama di mana
//       pun (baris tarif, langkah "Cara Kerja Aktivasi", dan daftar ketentuan) — hanya biaya
//       langganan tahun depan.
//   (2) "Pindah Menu agar lebih rapi. Menu Akun Operator pindahkan ke Pengaturan (urutannya:
//       Identitas & Pengaturan, Pengaturan tugas tambahan, Akun Operator. Menu Notulen Rapat (Hanya
//       lihat) pindahkan ke Laporan (Urutannya: Rekap Jumlah pertemuan, Rekap pelanggaran siswa,
//       Notulen Rapat." → sidebar (`inc/layout.php`) & Petunjuk Penggunaan (`inc/petunjuk.php`)
//       ditata ulang; seksi "Kelola Akun" dan "Rapat" tidak dipakai lagi.
//
// 1.53.0 = **MENU SUPER ADMIN "BIAYA & WA AKTIVASI"** — permintaan pemilik: "Tambahkan menu di
// super admin untuk mengisi nominal biaya aktifasi pertama, biaya langganan, nomer WA HD Admin
// (pada bagian kirim permintaan aktifasi dan bukti transfer." Halaman baru `biaya_aktivasi.php`
// (khusus Super Admin) mengisi ketiga nilai itu; tersimpan tingkat APLIKASI di `app_pengaturan`
// (`aktivasi_biaya`, `aktivasi_biaya_langganan`, `aktivasi_wa`) dengan konstanta di berkas ini
// sebagai NILAI BAWAAN, dan langsung dipakai halaman Status & Aktivasi seluruh sekolah (nominal,
// kalimat permintaan, tombol permohonan kode aktivasi, serta tombol kirim bukti transfer).
// Sekaligus: kalimat "— pada saat tombol permohonan diklik, Anda akan langsung diarahkan ke
// WhatsApp … dengan pesan yang sudah disiapkan." pada kartu Aktivasi Sekolah/Madrasah DIHAPUS
// (permintaan pemilik) dan diganti keterangan singkat "Kirim permohonan kode aktifasi lewat tombol
// di bawah ini (menuju WhatsApp <nomor>)."
//
// 1.52.0 = **NOMINAL BIAYA AKTIVASI pada menu Status & Aktivasi** (permintaan pemilik: "Pada menu
// status dan aktifasi tampilkan nominal aktifasi. Sehingga menjadi seperti ini: Untuk mengaktifasi
// Lembaga anda silahkan transfer Rp. 1.000.000 ke Bank Mandiri 1500010135547. An. Supianto. Jika
// sudah melakukan transfer silahkan kirim bukti transfer ke wa (menuju ke nomer permohonan kode
// aktifasi). Jika aplikasi sudah di aktifasi maka muncul info: biaya langganan Rp. 500.000 untuk
// tahun depan"). Konstanta `AKTIVASI_BIAYA` (1 juta, aktivasi pertama) & `AKTIVASI_BIAYA_LANGGANAN`
// (500 ribu, perpanjangan tahun berikutnya) di `inc/config.php` + helper `rupiah()`,
// `aktivasi_pernah_aktif()`, `aktivasi_biaya_berlaku()`, `aktivasi_biaya_label()`,
// `aktivasi_kalimat_biaya()`, `aktivasi_pesan_bukti()`/`aktivasi_tautan_bukti()` — tombol
// **Kirim Bukti Transfer ke WA** menuju nomor permohonan kode aktivasi.
// 1.56.0 = **LOGO APLIKASI (menu Super Admin "Logo Aplikasi")** — permintaan pemilik: "Logo
// aplikasi ganti dengan yang saya upload". Halaman baru `logo.php` + handler `upload_logo_app.php`
// (khusus Super Admin) untuk mengunggah logo aplikasi sendiri (PNG/JPG/GIF/WEBP/SVG, maks 15 MB);
// berkasnya diteruskan ke media proxy platform dari sisi server dan yang disimpan hanya URL-nya
// (`app_pengaturan` kunci `logo_app`). Logo itu dipakai pada **halaman masuk**, **halaman
// pendaftaran**, **sidebar seluruh pengguna**, dan **favicon**; selama belum diunggah tampilannya
// tetap memakai lambang bawaan (helper `logo_aplikasi()`/`ada_logo_aplikasi()` di inc/helpers.php,
// gaya `.brand-logo-img`/`.portal-logo-img`/`.daftar-logo-img` di assets/style.css). Menu juga
// didaftarkan pada Petunjuk Penggunaan. TIDAK ada perubahan skema.
// 1.57.0 = **SIDEBAR SUPER ADMIN: URUTAN BARU + SUB MENU** — permintaan pemilik: "Pada halaman
// super admin menu posisi dirubah dan dijadikan sub menu dengan urutan baru: Ringkasan semua;
// Kelolah sekolah; Layanan (sub menu: Pendaftaran, Akun Pengawas, Kelola Tugas Tambahan, Pengumuman
// Sambutan, Tentang Aplikasi, logo aplikasi, Gabung Grup e-Guru, Biaya & WA Aktifasi); Akun super
// admin; Log Aktivitas; Tugas Tambahan (Sub Menu: …). Begitu juga pada halaman Kepala Sekolah dan
// Operator, bagian tugas tambahan buat sub menu Ketika di klik baru muncul". Sub menu memakai
// <details>/<summary> (bekerja TANPA JavaScript, otomatis terbuka bila halaman aktif ada di
// dalamnya) — lihat `inc/layout.php` (dua sub menu pada blok Super Admin + satu pada blok sekolah)
// dan gaya `.nav-grup`/`.nav-sub`/`.nav-caret` di `assets/style.css`. Sekaligus: daftar menu Tugas
// Tambahan tidak lagi dirender DUA KALI (dulu Super Admin & pengawas mendapat dua blok yang sama)
// dan **perbaikan bug** `tentang_paragraf()` yang tidak mengenali CRLF sehingga dua paragraf yang
// ditulis pengelola tampil menyatu.
// 1.58.0 = **MENU "UJIAN ONLINE"** (skema v42) — permintaan pemilik: "Dibawah menu pelanggaran
// siswa tambahkan menu 'Ujian Online'. Isi dari menu ini buat seperti menu smart teacher yaitu
// memasukkan link aplikasi ujian online lewat akun super admin" → menu baru `ujian_online.php` +
// tabel GLOBAL `ujian_online` (bentuknya sama dengan `smart_teacher`), dikelola Super Admin pada
// modul bersama `inc/daftar_apps.php` (satu entri konfigurasi saja — JANGAN menyalin logikanya).
// Menu tampil pada blok Administrasi TEPAT di bawah "Pelanggaran Siswa" (blok Aplikasi untuk
// pengawas), plus pintasan pada dashboard lintas sekolah & panduan Petunjuk Penggunaan.
// 1.59.0 = **LABEL BERKEDIP PADA MENU "UJIAN ONLINE"** (skema v43) — permintaan pemilik:
// "Pada menu ujian online tambahkan tulisan new berkedip seperti pada aplikasi smart teacher"
// → Super Admin mencentang pada formulir aplikasinya (kolom `ujian_online.baru_pada`), lalu lencana
// (merah, berkedip) tampil DI ATAS nama menu pada sidebar selama `DAFTAR_APPS_BARU_HARI` (30) hari
// dan hilang sendiri. **Pilihan pemilik: tanda hanya di menu sidebar** (tidak pada kartu aplikasi).
// **v1.72.2** (permintaan pemilik): *"Tulisan new berkedip ganti dengan tulisan 'Bonus'. Pada menu
// Smart Teacher juga tambahkan fitur 'Bonus' berkedip"* → teks lencana menjadi **Bonus**
// (`DAFTAR_APPS_BARU_TEKS`) dan fitur ini juga dipakai menu **Smart Teacher** (kolom
// `smart_teacher.baru_pada`, skema v53).
// 1.64.0 = **PENGUATAN KEAMANAN (tanpa perubahan skema)** — permintaan pemilik: "Pastikan sistem
// keamanan aplikasi aman dari peretasan atau serangan hacker. Pastikan tidak ada celah bug yang bisa
// membuat aplikasi mudah untuk diretas." Yang dilakukan:
//   (a) **cookie sesi dikeraskan** (`inc/config.php`): `use_strict_mode` (anti SESSION FIXATION),
//       `use_only_cookies`, `cookie_httponly`, `cookie_samesite=Lax`, dan `cookie_secure` otomatis
//       saat permintaan HTTPS — nilai bawaan PHP di server ini TIDAK aman (httponly Off,
//       samesite kosong, strict mode Off);
//   (b) **pembatas percobaan masuk** (anti BRUTE FORCE) berbasis riwayat `log_aktivitas`
//       (`LOGIN_MENIT_GAGAL`/`LOGIN_MAKS_GAGAL_IP`/`LOGIN_MAKS_GAGAL_USER`/`LOGIN_MAKS_GAGAL_SISWA`
//       di config.php + `login_blokir_detik()`/`login_blokir_detik_siswa()` di helpers.php):
//       percobaan gagal berulang diblokir sementara, kata sandi yang BENAR pun ditolak selama
//       diblokir, dan hitungan dikosongkan begitu masuk berhasil;
//   (c) **alamat IP tidak dapat dipalsukan lagi** (`log_ip()`): `REMOTE_ADDR` diutamakan dan, bila
//       aplikasi di belakang proxy lokal, entri TERAKHIR `X-Forwarded-For` (nilai yang ditambahkan
//       proxy) yang dipakai — versi lama memakai entri PERTAMA yang dapat ditulis klien, sehingga
//       riwayat Log Aktivitas bisa menyesatkan dan pembatas IP bisa dilewati;
//   (d) **anti OPEN REDIRECT** pada pengalihan sesudah masuk (`login.php`) — versi lama hanya
//       menolak "://" sehingga `//situs-penyerang.example` masih lolos;
//   (e) **kepala keamanan** (`inc/init.php`): `Content-Security-Policy` (frame-ancestors/object-src/
//       base-uri/form-action — anti CLICKJACKING) + `Permissions-Policy`;
//   Uji: `bash tests_eguru_keamanan.sh` (48 pemeriksaan).
// 1.64.1 = **PERBAIKAN "Bersihkan Riwayat Lama" pada halaman Log Aktivitas** (permintaan pemilik:
// "Fitur bersihkan riwayat lama pada super admin tidak berfungsi. Ketika dicoba bersihkan, riwayat
// masuk keluar masih ada."). Sebabnya BUKAN kerusakan server: satu-satunya pilihan yang tersedia
// adalah "lebih lama dari N hari" dengan angka bawaan 90 hari, sedangkan riwayat instalasi ini
// baru berumur beberapa hari — jadi DELETE-nya sah tetapi menghapus 0 baris sementara pesannya
// ("0 baris dihapus") mudah terlewat. Perbaikannya di `log.php`:
//   (a) pilihan baru **mode=semua** — "SELURUH riwayat (masuk, keluar, gagal)" tanpa kecuali;
//   (b) **tabel pratinjau** di kartu: berapa baris yang terhapus & sisanya untuk tiap batas hari
//       (1/7/30/90/365) + SELURUH, plus ringkasan jumlah baris & umur riwayat terlama;
//   (c) **angka hari bawaan** dipilih otomatis dari batas terkecil yang benar-benar menghapus
//       sesuatu (dulu selalu 90) dan **tombol cepat** per batas hari;
//   (d) pesan saat 0 baris TIDAK lagi hanya "0 baris dihapus" — dijelaskan umur riwayat terlama
//       + disarankan memilih angka yang lebih kecil atau "Seluruh riwayat".
//   Tombol cepat memakai nama field **`hari_pilih`** (terpisah dari isian `hari`) supaya nilainya
//   tidak bertabrakan dengan isian angka pada formulir yang sama.
//   Uji: bagian pembersihan pada `tests_eguru_log.sh` (+27 → 137) dan
//   `bash check_eguru_log_bersih_ui.sh` (+ `check_eguru_log_bersih_ui.js`, 22 pemeriksaan Playwright).
// 1.65.0 = **KATA SANDI AKUN SISWA (tanpa perubahan skema)** — permintaan pemilik: *"Pada login
// siswa tambahkan password. Password adalah tanggal lahir siswa itu sendiri yang terdiri dari 8
// angka. Misal 1 januari 2007 maka passwordnya 01012007"*. Yang berubah:
//   (a) tab **Siswa** pada halaman depan kini memuat isian **Kata Sandi** (`name="sandi"`,
//       `id="sandiSiswa"`) — 8 angka DDMMYYYY, boleh ditulis dengan pemisah ("01-01-2007");
//   (b) kata sandinya **dihitung dari `siswa.tanggal_lahir`** (`siswa_sandi_lahir()`,
//       `siswa_sandi_bersih()`, `siswa_sandi_cocok()` di inc/siswa_akun.php) — TIDAK disimpan di
//       basis data, jadi memperbaiki tanggal lahir siswa otomatis memperbaiki kata sandinya dan
//       tidak pernah ada kata sandi siswa yang tersimpan sebagai teks; perbandingannya memakai
//       `hash_equals()` dan WAJIB tepat 8 angka (01012007 diterima, 1012007/20070101 ditolak);
//   (c) akun siswa **hanya dibuat setelah kata sandi benar** dan siswa yang **tanggal lahirnya
//       belum diisi** ditolak dengan pesan jelas (hubungi Admin Operator) — bukan sekadar gagal;
//   (d) pembatas percobaan masuk siswa (`login_blokir_detik_siswa()`) kini memeriksa **per NISN**
//       (`LOGIN_MAKS_GAGAL_USER` kegagalan / `LOGIN_MENIT_GAGAL` menit, berlaku tanpa memandang
//       alamat IP) DI SAMPING aturan lama per alamat IP PUBLIK;
//   (e) panduan **Petunjuk Penggunaan** (akun Super Admin) menjelaskan bahwa akun siswa masuk dengan
//       NISN + kata sandi tanggal lahir (8 angka).
//   Uji: `bash tests_eguru_siswa.sh`, `bash check_eguru_siswa_ui.sh` (+ `check_eguru_siswa_ui.js`),
//   `bash tests_eguru_pengumuman.sh` (akun siswa dipakai di sana), `bash cek_live_siswa_eguru.sh`.
// 1.67.0 = **FITUR "KIRIM PERANGKAT" (skema v48) — MENGGANTIKAN menu/halaman publik "Perangkat Guru"**.
// Permintaan pemilik: *"Menu Perangkat Guru yang ada di Super Admin dihapus saja dengan isinya. Menu
// ini sudah tidak dibutuhkan. Sekarang buatkan fitur Kirim Perangkat di akun guru (Seperti Kalender
// Pendidikan, Analisis Pekan Efektif, Capaian Pembelajaran, Alur Tujuan Pembelajaran, RPP/Modul Ajar.
// Nama-nama dokumen ini di isi oleh Super Admin, bisa ditambah atau dikurangi. File yang terkirim
// masuk di akun Kepala sekolah (Kepala Sekolah bisa meninjau dokumen tersebut dengan: Dokumen
// Disetujui, Dokumen Direvisi, Dokumen Ditolak sekaligus bisa mendownload), dan Pengawas (Pengawas
// hanya lihat dan download). Hasil tinjauan kepala sekolah terkirim ke akun guru"*.
//   (a) **YANG DIHAPUS**: menu Super Admin "Perangkat Guru" beserta halaman publiknya
//       (`perangkat_guru.php`), halaman pengunggahan Super Admin (`perangkat_guru_admin.php`),
//       modul `inc/perangkat.php` lama, dan seluruh aturan KODE DOWNLOAD (kode diminta setiap kali
//       mengunduh). Instalasi baru tidak lagi membuat tabel lama `perangkat_berkas`/`perangkat_kode`.
//   (b) **DUA TABEL BARU** (hanya CREATE TABLE/CREATE INDEX — tidak ada tabel lama yang dibangun
//       ulang): `perangkat_jenis` (**GLOBAL** — daftar Nama Dokumen yang diisi Super Admin; 5 nama
//       bawaan) dan `perangkat_kirim` (**PER SEKOLAH** — kiriman guru: guru, mapel, tahun ajaran,
//       jenis dokumen, berkas, status tinjauan, catatan). Keduanya di `SCHEMA_TABEL_WAJIB`.
//   (c) **`perangkat_saya.php`** — menu **"Kirim Perangkat"** pada akun **GURU**: mengunggah berkas
//       **per Nama Dokumen × per mata pelajaran yang diampu × per Tahun Ajaran** (unggah ulang pada
//       sel yang sama MENGGANTIKAN berkas lama dan mengembalikan status ke "Belum Ditinjau"), melihat
//       kelengkapan perangkat (matriks mapel × nama dokumen), dan melihat **hasil tinjauan kepala
//       sekolah** (status + keterangan/alasan + nama peninjau + waktunya).
//   (d) **`perangkat_masuk.php`** — menu **"Perangkat Masuk"** pada akun **KEPALA SEKOLAH/MADRASAH**
//       (+ Super Admin dalam konteks): meninjau kiriman guru dengan **Dokumen Disetujui / Dokumen
//       Direvisi / Dokumen Ditolak** beserta keterangan/alasannya (WAJIB untuk revisi & ditolak),
//       mengunduh berkasnya, serta menyaring per tahun ajaran/guru/mapel/nama dokumen/status.
//       **Pengawas** hanya melihat & mengunduh (tanpa formulir tinjauan, POST ditolak 403).
//   (e) **`perangkat_jenis.php`** — menu Super Admin **"Jenis Perangkat"**: menambah/mengurangi nama
//       dokumen perangkat (ubah nama, ▲▼ urutan, aktif/nonaktif, hapus — menghapus TIDAK menghapus
//       kiriman guru karena nama jenis disimpan sebagai snapshot).
//   Uji: `bash tests_eguru_perangkat.sh`, `bash check_eguru_perangkat_ui.sh`
//   (+ `check_eguru_perangkat_ui.js`), `bash cek_live_perangkat_eguru.sh`.
// 1.68.0 (skema v49) = JENIS LEMBAGA + PILIHAN JENJANG + GURU KELAS — permintaan pemilik:
//   judul kartu pada Identitas & Pengaturan diganti menjadi "Jenis Lembaga" dan ditambahkan fitur
//   **Pilihan Jenjang** (Sekolah Umum → SD/SMP/SMA/SMK · Madrasah → MI/MTs/MA/MAK). Jenjang menjadi
//   acuan tingkat kelas, dan jenjang **SD/MI** memakai konsep **GURU KELAS**: guru ditandai "Guru
//   Kelas", memilih TEPAT SATU kelas, otomatis menjadi wali kelas kelas itu, sedangkan mata pelajaran
//   tetap dicentang manual. Jenjang lain (SMP/MTs/SMA/MA/SMK) TIDAK berubah pengaturannya.
//   Jenjang juga dapat dipilih pada halaman Pendaftaran sekolah dan pada Kelola Sekolah (Super Admin).
//   Uji: tests_eguru_jenjang.sh, check_eguru_jenjang_ui.sh, cek_live_jenjang_eguru.sh.
// 1.69.0 = REKAP NILAI & CETAK LEGER pada menu Tugas Tambahan "Wali Kelas" — permintaan pemilik:
//   *"Tambahkan fitur Rekap Nilai semua mata pelajaran dan Cetak Leger (tambahkan predikat dan
//   peringkat) pada tugas tambahan wali kelas. Jadi semua nilai mata pelajaran yang diisi oleh yang
//   mengajar di kelas itu terkirim ke Wali Kelas menjadi rekapan nilai."*
//   Tambahan tab KELIMA "Rekap Nilai": leger siswa × seluruh mata pelajaran (nilai akhir tiap mapel
//   dibaca dari nilai yang diisi guru pengampu, rumusnya sama dengan halaman Penilaian Siswa) +
//   Jumlah, Rata-rata, PREDIKAT, dan PERINGKAT; ditambah rekap per mata pelajaran (guru pengampu,
//   siswa ternilai, rata-rata, bobot ASAS). Lembar cetak baru `cetak_wali_leger.php` (A4 landscape).
//   Uji: tests_eguru_wali_nilai.sh, check_eguru_wali_nilai_ui.sh.
// 1.69.1 = PERBAIKAN: mengklik tab "Rekap Nilai" pada menu Wali Kelas menampilkan JADWAL PIKET
//   (laporan pemilik). Penyebabnya blok tab Jadwal Piket dibuka dengan `else` sehingga tab apa pun
//   selain struktur/program/absensi — termasuk `nilai` — ikut merendernya. Sekarang `elseif`.
// 1.69.2 = TIGA PERMINTAAN PEMILIK: (1) baris ringkasan di bawah kartu "Total Pengguna" pada halaman
//   depan DIHAPUS; (2) baris tab/aksi pada SELURUH menu Tugas Tambahan dibuat BERBEDA WARNA tiap chip
//   dengan tulisan tetap terbaca (`.chip-list.warna`, 6 rona, kontras ≥ 4,5:1); (3) lembar cetak
//   Jadwal Piket dibuat berbentuk KARTU PER HARI (3 kolom) seperti contoh pemilik — TANPA gambar kartun
//   dan TANPA kotak "CATATAN".
// 1.69.3 = PERBAIKAN: pada menu Wali Kelas — Absensi Kelas, tombol S / I / A tidak bisa dipilih
//   (laporan pemilik). Penyebabnya `initHadirGrid()` hanya terikat ke `#formHadir` (menu Daftar Hadir
//   Siswa), sedangkan form Absensi Kelas ber-id `#formAbsensiWaliKirim` — kelas penanda hijau tidak
//   pernah berpindah dari H. Sekarang penanda & tombol "Tandai Semua Hadir" berlaku di KEDUA formulir.
// 1.70.0 (skema v50) = TANDA TANGAN LANGSUNG DI LAYAR SENTUH pada Notulen Rapat — permintaan pemilik:
//   *"Pada notulen rapat buat tanda tangan langsung lewat HP seperti aplikasi TUSmart"*.
//   Nama-nama pada **Daftar Hadir Rapat** (juga Kepala Sekolah/Madrasah, Pimpinan Rapat, dan Notulis
//   pada blok tanda tangan lembar cetak) dapat menandatangani **langsung di layar sentuh**: coretan
//   jari/stylus pada <canvas> diekspor PNG (dipangkas ke area coretan), dikirim ke
//   `upload_notulen_ttd.php`, lalu diunggah ke media proxy — yang disimpan di tabel baru
//   **`notulen_ttd`** hanya URL CDN-nya. Tombol "Tanda Tangan" DICETAK SERVER dengan atribut `hidden`
//   dan hanya dimunculkan skrip pada perangkat berlayar sentuh (komputer biasa tetap mencetak kolom
//   tanda tangan kosong); tombol & dialog disembunyikan saat mencetak. Kunci pihak peserta memakai
//   ID GURU (`hadir:g<id>`) supaya tanda tangan tetap menempel pada orang yang sama walau daftar hadir
//   notulen disunting; tanda tangan pihak yang sudah tidak ada dibersihkan otomatis.
//   Uji: tests_eguru_ttd_barcode_piket.sh, check_eguru_ttd_ui.sh (+ .js), cek_live_ttd_notulen_eguru.sh.
// 1.70.1 = CETAK JADWAL PIKET hanya mencetak KARTU HARI YANG ADA SISWA PIKETNYA — permintaan pemilik:
//   *"Pada cetak jadwal piket jangan tampilkan kartu yang tidak dipilih siswa untuk piket. Tampilkan
//   saja kartu yang ada siswa yang piket."* Hari kosong tidak lagi dibuatkan kartu.
// 1.71.0 = BARCODE (KODE QR) pada KARTU PELAJAR yang ketika dipindai menampilkan DATA LEMBAGA —
//   permintaan pemilik. QR berisi alamat halaman publik baru **`lembaga.php`** (`?k=<id>-<token>`,
//   token = HMAC id sekolah sehingga tidak bisa ditebak) yang menampilkan identitas lembaga (nama,
//   NPSN/NSM, alamat per wilayah, kontak, kepala, tahun ajaran, jumlah guru/siswa/kelas) TANPA data
//   pribadi siswa dan tanpa login. Pembuat QR ditulis sendiri di `inc/qr.php` (mode BYTE, ECC M,
//   versi 1–10, Reed–Solomon GF(256), tanpa GD & tanpa pustaka luar) dan keluarannya SVG inline.
//   Uji: tests_eguru_ttd_barcode_piket.sh, check_eguru_kartu_ui.sh (bagian barcode).
// 1.71.1 (skema v51) = REKAP PELANGGARAN SISWA pada menu Tugas Tambahan "Kesiswaan" — permintaan
//   pemilik: *"Pada menu kesiswaan tambahkan fitur rekap pelanggaran dari seluruh guru mata pelajaran.
//   Jadi guru yang dapat tugas tambahan kesiswaan bisa melihat, memberikan tindak lanjut, dan
//   mencetak seluruh catatan pelanggaran dari setiap guru mata pelajaran."*
//   Tab KETIGA "Rekap Pelanggaran" pada menu Kesiswaan memuat SELURUH catatan pelanggaran sekolah
//   (bukan hanya milik guru itu) beserta saringan (kelas, mapel, guru pencatat, jenis, status,
//   tindak lanjut, rentang tanggal, kata kunci) dan kartu ringkasan. Guru pemegang menu Kesiswaan
//   (+ Super Admin dalam konteks) memberi **TINDAK LANJUT** per catatan (jenis: Dibina / Peringatan
//   Tertulis / Panggilan Orang Tua / Sanksi Sekolah / Selesai Ditangani + catatan bebas) yang
//   disimpan pada baris `kasus` itu sendiri (4 kolom baru, hanya MENAMBAH kolom) sehingga ikut
//   terlihat di menu Pelanggaran Siswa. Lembar cetak baru **`cetak_kesiswaan_pelanggaran.php`**
//   (A4 landscape, kolom Tindak Lanjut, tanda tangan kepala di kanan) mengikuti saringan aktif.
//   Kepala/operator/pengawas tetap HANYA LIHAT & CETAK (POST ditolak 403, pola Tugas Tambahan).
//   Uji: tests_eguru_kesiswaan_pelanggaran.sh, check_eguru_kesiswaan_pelanggaran_ui.sh,
//   cek_live_pelanggaran_kesiswaan.sh.
// 1.71.2 = PERBAIKAN TATA LETAK tabel rekap pelanggaran: lebar minimum tabel (1120 px) dan kolom
//   **Tindak Lanjut** (215 px) ditetapkan di berkas tema, karena dengan 10 kolom kolom terakhir
//   menyempit sampai ±194 px sehingga formulir tindak lanjut terasa sempit (terukur Playwright).
//   Seluruh isi sel yang sudah diukur tidak ada yang meluber; tabel tetap bergulir di dalam
//   wadahnya (.table-wrap) sehingga halaman tidak pernah melebar.
// 1.71.3 = DUA permintaan pemilik:
//   (1) KOLOM "MASA AKTIF" pada tabel Daftar Sekolah/Madrasah (Super Admin → Ringkasan Semua),
//       tepat di kolom yang sebelumnya kosong antara Sekolah/Madrasah dan Kelas: permanen /
//       aktif berjangka (sisa hari + tanggal berakhir) / uji coba / masa aktif habis. Sumber
//       tunggalnya tetap aktivasi_status(); baris JUMLAH memuat rekapnya.
//   (2) PERBAIKAN "aplikasi di domain sendiri tampil tanpa gaya": bila alamat yang dibuka masih
//       memuat jalur folder aplikasi pada DOMAIN SENDIRI (mis. https://asisten.guru/e-guru/),
//       seluruh tautan relatif menunjuk satu folder terlalu dalam. inc/init.php kini mengalihkan
//       jalur ber-awalan slug GANDA satu tingkat ke atas memakai Location relatif ('../…'),
//       sehingga benar di alamat platform (/e-guru/) maupun di domain sendiri (akar domain).
// 1.71.4 = POPUP PERINGATAN AKTIVASI (permintaan pemilik): *"Pada madrasah yang masa aktifnya sisa
//   7 hari tampilkan pop up peringatan agar segera melakukan aktifasi melalui akun kepala sekolah
//   atau operator. Popup ini muncul di akun semua pengguna pada lembaga tersebut sampai masa aktif
//   benar-benar habis."* → popup `<dialog id="popupPeringatanAktivasi">` dicetak pada SETIAP halaman
//   berpengguna selama sisa masa aktif/uji coba ≤ AKTIVASI_INGAT_HARI (7 hari) dan belum habis;
//   tombol "Lakukan Aktivasi Sekarang" HANYA untuk kepala & operator (guru/siswa diberi arahan),
//   Super Admin & pengawas tidak melihatnya, dan tidak dicetak pada halaman Status & Aktivasi
//   maupun lembar cetak. Sekaligus memperbaiki kalimat spanduk mode aktif yang berbunyi
//   "Masa masa aktif lembaga ini tersisa …" (frasa lengkap: "Masa aktif …").
// 1.71.5 = POPUP SELAMAT DATANG vs POPUP PERINGATAN (permintaan pemilik): *"Buat Popup selamat datang
//   tetap dimunculkan di lembaga yang masa aktifnya diatas 7 hari dan tidak dimunculkan ketika sisa
//   7 hari sampai habis."* → `sambutan_perlu_tampil()` kini mengembalikan false (dan langsung
//   membersihkan penandanya) bila `aktivasi_peringatan()` tidak null, jadi selama sisa ≤ 7 hari
//   yang tampil HANYA popup peringatan aktivasi; di atas 7 hari popup selamat datang berjalan
//   seperti biasa. Pengawas tidak terkena popup peringatan → popup selamat datang tetap baginya.
// 1.71.6 = DASHBOARD PENGAWAS + PENYEDERHANAAN MENU AKUN PENGAWAS (permintaan pemilik):
//   (1) *"Pada akun pengawas hapus menu yang saya tandai warna merah."* → menu daftar aplikasi
//       GLOBAL (Ujian Online, Smart Teacher, Aplikasi Sagu, Hasil Supervisi) DIHAPUS dari sidebar
//       pengawas (halamannya tetap boleh dibuka bila diakses langsung);
//   (2) *"Tambahkan menu Dashboard di atas menu daftar binaan dengan isi: Grafik atau chart"* →
//       halaman baru `dashboard_pengawas.php` (grafik guru & siswa per binaan, sebaran jenis
//       lembaga, baris nilai per binaan, tren catatan jurnal 6 bulan, tabel ringkasan + JUMLAH)
//       dengan menu TEPAT DI ATAS "Daftar Binaan";
//   (3) blok menu "Akun" pada sidebar pengawas tidak lagi tampil DUA KALI (bug pada tangkapan layar
//       pemilik) dan "Status & Aktivasi" (tautan buntu 403) tidak ditawarkan kepada pengawas.
// 1.71.7 = PERMINTAAN PEMILIK: *"Yang saya tandai warna merah sebaran jenis lembaga ganti dengan
//   sebaran jenjang"* → kartu donut "Sebaran Jenis Lembaga" pada Dashboard Pengawas DIGANTI menjadi
//   **"Sebaran Jenjang"** (SD/SMP/SMA/SMK/MI/MTs/MA/MAK) memakai kolom `sekolah.jenjang` melalui
//   jenjang_sah(); binaan yang jenjangnya belum diisi sekolahnya masuk potongan **"Belum diisi"**
//   (abu-abu) supaya jumlah seluruh potongan tetap = jumlah binaan aktif. Kartu kecil di bawah donut
//   menampilkan jumlah per jenjang yang ada.
// 1.71.8 = PERBAIKAN BUG (keluhan pemilik: *"Aplikasi mengalami kendala saat dibuka … jika pengawas
//   kelola madrasah yang masa aktif habis ada keterangan madrasah ini tidak bisa diakses karena masa
//   aktif penggunaan aplikasi telah berakhir"*): `aktivasi.php` tidak ada pada daftar putih pengawas,
//   sehingga pengalihan `gerbang_aktivasi()` untuk binaan yang masa aktifnya habis berakhir sebagai
//   halaman **403 teks mentah tanpa tema**. Sekarang:
//   (1) `aktivasi.php` boleh dibuka pengawas (200, bertema) dan isinya khusus pengawas: KETERANGAN
//       status masa aktif seluruh binaan + tabel `#tblStatusBinaan` + kalimat "Sekolah/madrasah ini
//       tidak bisa diakses karena masa aktif penggunaan aplikasi telah berakhir" — TANPA formulir
//       kode, biaya, rekening, atau kode aktivasi lembaga. POST tetap 403 (hanya-lihat);
//   (2) konteks binaan yang masa aktifnya habis **DILEPAS** (konteks dikosongkan + pesan) supaya
//       pengawas kembali ke Daftar Binaan, bukan terkunci seperti kepala/operator;
//   (3) Daftar Binaan menandai binaan "masa aktif habis", menolak masuk dengan keterangan itu, dan
//       lembar rekapan binaan tidak memuat/mencetak binaan yang masa aktifnya habis;
//   (4) penguncian untuk KEPALA/OPERATOR/GURU tidak berubah.
// 1.71.9 = PERMINTAAN PEMILIK: *"Urutkan: Paling atas adalah yang masa aktifnya paling lama."*
//   Tabel **Daftar Sekolah/Madrasah** pada Ringkasan Semua Sekolah kini diurutkan menurut MASA AKTIF
//   memakai helper **`aktivasi_urut_kunci()`** (`inc/helpers.php`): permanen / tanpa tanggal batas
//   paling atas (PHP_INT_MAX), lalu `aktif` & `uji_coba` menurut sisa DETIK terbesar, dan sekolah yang
//   masa aktif/uji cobanya SUDAH HABIS di paling bawah (-1) — sisa yang sama diurutkan menurut nama.
//   Urutan grafik "Sebaran Siswa per Sekolah/Madrasah" TIDAK diubah (tetap abjad) dan baris JUMLAH
//   tetap dihitung dari seluruh baris.
// 1.72.0 = PERMINTAAN PEMILIK: *"Pada akun pengawas tambahkan menu Perangkat Guru (Perangkat guru
//   ditampilkan per lembaga yang di dalamnya ada daftar guru + mata pelajaran dan daftar perangkatnya).
//   Pengawas bisa melihat, memberikan catatan setiap perangkat dan mendownload"* — dengan pilihan:
//   (1) **catatan pengawas TERPISAH** dari status tinjauan kepala sekolah (guru & kepala sekolah
//       dapat membacanya), (2) **pilih lembaga dulu**, lalu tampil daftar guru → mata pelajaran →
//       daftar perangkatnya.
//   Skema **v52**: tabel baru **`perangkat_catatan`** (kirim_id, pengawas_id, isi, dibuat, diubah;
//   UNIQUE (kirim_id, pengawas_id) + 2 indeks) — hanya CREATE TABLE/CREATE INDEX, tidak ada tabel
//   lama yang dibangun ulang, dan tabelnya KOSONG setelah migrasi.
//   Halaman baru **`perangkat_pengawas.php`** (pengawas saja; peran lain 403) berisi halaman PEMILIH
//   BINAAN (kartu per binaan + ringkasan guru/perangkat/catatan) lalu, setelah `?sekolah=ID`
//   memindahkan konteks (anti-IDOR `pengawas_punya()`), daftar perangkat **dikelompokkan per guru →
//   per mata pelajaran** (`<details open>`, bisa ditutup) dengan tombol **Unduh**, status tinjauan
//   kepala sekolah, dan formulir **Catatan Pengawas** per baris. Menu "Perangkat Guru" pada sidebar
//   pengawas dipindah ke blok LINTAS SEKOLAH (menu lama `perangkat_masuk.php` pada blok binaan
//   dihapus agar tidak ada dua menu bernama sama; halamannya tetap dapat dibuka langsung).
//   Catatan pengawas juga TAMPIL di akun guru (`perangkat_saya.php`) dan kepala sekolah
//   (`perangkat_masuk.php`), terpisah dari hasil tinjauan, lewat `perangkat_catatan_tampil()`.
// 1.72.1 = PERMINTAAN PEMILIK: *"Pada fitur cetak kartu pelajar tambahkan pilihan template warna.
//   Contoh desain yang saya upload. Untuk posisi tulisan, logo, barcode tetap. Yang saya upload
//   hanya untuk contoh tampilan desainnya saja"*. Yang berubah **HANYA warna/desain** — tata letak
//   kartu (kepala+logo, kotak foto 3x4, nama, baris NISN/NIS/Jenis Kelamin/Tanggal Lahir, barcode QR
//   di kanan-bawah, kaki "masa berlaku", ukuran 88 x 55 mm, 8 kartu per lembar A4) TIDAK berubah.
//   Lima template pada konstanta `KARTU_TEMPLATE` (bawah): **Hijau Emas (bawaan)**, Hijau Cerah,
//   Biru Tosca, Merah Maroon, Ungu Indigo. Pilihan dibuat dengan deretan tombol pada halaman cetak
//   (`cetak_kartu_siswa.php`, tautan biasa tanpa JavaScript), diingat di sesi
//   (`KARTU_TEMPLATE_SESI`), dan warna tiap template diatur blok CSS "TEMPLATE WARNA KARTU PELAJAR"
//   di halaman itu (kelas `kartu-tpl-<kode>` pada `<body>`; markup kartu tidak berubah).
// 1.72.2 = PERMINTAAN PEMILIK: *"Tulisan new berkedip ganti dengan tulisan 'Bonus'. Pada menu Smart
//   Teacher juga tambahkan fitur 'Bonus' berkedip"*. Lencana pada sidebar kini berbunyi **Bonus**
//   (masih merah & berkedip; teksnya konstanta `DAFTAR_APPS_BARU_TEKS`), dan fitur itu dipakai DUA
//   menu: **Ujian Online** + **Smart Teacher**. Skema **v53** menambah kolom `smart_teacher.baru_pada`
//   (hanya menambah kolom; kosong untuk semua aplikasi — tidak ada yang mendadak "bonus").
//   Kotak setelan pada formulir aplikasi menjadi **"Tandai sebagai bonus"**. Lencana tetap MERAH &
//   BERKEDIP (kelas `.new-tag` + keyframes `newKedip` di `assets/style.css`) dan hanya dipasang pada
//   MENU SIDEBAR (bukan kartu aplikasi/tabel pengelolaan).
// 1.72.3 = PERMINTAAN PEMILIK: *"Tambahkan fitur non aktifkan dan fitur hapus pada yang ditandai
//   warna merah. Sebelum menghapus super admin akan dimintakan sandi (sandinya: 181288). Tujuan sandi
//   ini agar ketika tidak sengaja di klik hapus lembaga tidak langsung terhapus"*. Kolom **Aksi**
//   pada tabel Daftar Sekolah/Madrasah (dasbor Ringkasan Semua) kini memuat **Kelola + Nonaktifkan +
//   Hapus**; tombol Hapus membuka formulir **sandi konfirmasi** (`SEKOLAH_HAPUS_SANDI`) yang
//   diperiksa di SERVER — sandi salah/kosong = lembaga TIDAK dihapus. Sandi yang sama juga diminta
//   pada "Zona berbahaya" di halaman Kelola Sekolah. Penghapusan & nonaktif kini memakai jalur
//   bersama `sekolah_hapus_permanen()`/`sekolah_set_aktif()` di `inc/helpers.php`.
// 1.73.2 = PERBAIKAN BUG (laporan pemilik): *"Pada setingan jenis lembaga, tulisan Madrasah Umum
//   ganti menjadi Sekolah umum"*. Pada halaman **Identitas & Pengaturan** (sekolah berjenis
//   madrasah) pilihan jenis lembaga tampil sebagai "Madrasah Umum (SD/SMP/SMA/SMK)", padahal
//   seharusnya "Sekolah Umum (SD/SMP/SMA/SMK)": label di kode sudah benar, tetapi ikut melewati
//   penyesuaian istilah `terjemahkan_html()` sehingga kata "Sekolah" diubah menjadi "Madrasah".
//   Perbaikan: label KEDUA pilihan (`pengaturan.php`) dibungkus **`tetap_kata()`** karena nama
//   jenis lembaga adalah istilah TETAP, bukan label yang mengikuti jenis sekolah. Halaman
//   lintas sekolah (Kelola Sekolah, Pendaftaran, `daftar.php`) sudah menyetel
//   `__vs_istilah_nonaktif` sehingga tidak terpengaruh. Uji: `tests_eguru_jenis_lembaga_label.sh`.
// 1.73.1 = PERBAIKAN BUG PENTING (laporan pemilik): *"Saat peserta meeting melakukan tanda tangan,
//   tanda tangan tidak bisa disimpan dengan pesan Gagal menghubungi server"*.
//   Penyebab: versi pertama fitur Daftar Hadir Meeting menulis definisi tabel `meeting_hadir`
//   dengan FOREIGN KEY ke tabel yang TIDAK ADA (`schools_placeholder` — salah tulis nama, seharusnya
//   `sekolah`). SQLite tidak memeriksa sasaran FK saat CREATE TABLE, jadi kesalahannya baru muncul
//   sebagai galat PHP saat INSERT (`no such table: main.schools_placeholder`), dan karena
//   `CREATE TABLE IF NOT EXISTS` tidak memperbaiki tabel yang sudah ada, basis data yang sempat
//   menjalankan versi itu terus gagal menyimpan tanda tangan (dialog hanya menampilkan pesan umum
//   "Gagal menghubungi server" karena balasannya halaman HTML galat, bukan JSON).
//   Perbaikan: **skema v56** membangun ULANG tabel `meeting_hadir` bila definisi tersimpannya masih
//   memuat `schools_placeholder` (isi disalin, id tetap) + indeks dipulihkan. Ditambah jaring
//   pengaman: `upload_meeting_ttd.php` SELALU menjawab JSON walau terjadi galat PHP
//   (exception handler + shutdown handler), dan `initTtdSentuh()` (`assets/app.js`) menyebut KODE
//   HTTP pada pesan kegagalan bila balasannya bukan JSON — supaya kejadian serupa tidak lagi
//   tampil sebagai pesan buta. Uji: `tests_eguru_meeting_repair.sh`.
// 1.73.0 = PERMINTAAN PEMILIK: *"Pada akun pengawas tambahkan menu aktifkan daftar hadir meeting
//   (pertemuan/zoom). Jadwal di atur dengan memasukkan tanggal pertemuan, judul pertemuan/meeting.
//   Pengawas bisa mengaktifkan daftar hadir binaan yang dia pilih atau seluruh binaannya. Ketika
//   diaktifkan maka akan muncul di seluruh akun binaannya untuk di tanda tangani secara online (tanda
//   tangan digital seperti di notulen rapat). Dokumen daftar hadir meeting/pertemuan ini akan menjadi
//   rekapan setiap pertemuan yang dilakukan oleh pengawas."*
//   → MENU BARU **"Daftar Hadir Meeting"** pada akun pengawas (`meeting.php`) dan pada akun lembaga
//   binaan (`meeting_hadir.php`). Skema **v55**: tabel `meeting` (judul + tanggal + tanda tangan
//   Pemimpin Meeting), `meeting_sekolah` (binaan yang diaktifkan), `meeting_hadir` (tanda tangan tiap
//   akun lembaga). Pilihan pemilik: penanda tangan = SEMUA akun lembaga (kepala, operator, guru);
//   pengawas menandatangani sebagai **Pemimpin Meeting** (satu tanda tangan untuk lembar seluruh
//   binaan); hasilnya **rekapan di layar + lembar cetak A4** (`cetak_meeting.php`, satu lembar per
//   binaan). Tanda tangan memakai mekanisme Notulen Rapat (`initTtdSentuh()` + `upload_meeting_ttd.php`
//   → media proxy), jadi tetap butuh perangkat berlayar sentuh.
// 1.72.4 = DUA PERMINTAAN PEMILIK:
//   (a) *"Yang saya beri tanda merah dirapikan agar fitur hapus, kelola dan nonaktifkan sejajar
//       rapi"* → ketiga tombol kolom Aksi pada tabel Daftar Sekolah/Madrasah kini berada pada SATU
//       baris (wadah flex `.sekolah-aksi-baris`); formulir sandi Hapus turun di bawah tombol Hapus.
//   (b) *"dibawah menu Smart Teacher tambahkan menu Studio Foto Pintar (tambahkan label Bonus
//       seperti Menu Smart Teacher) dan linknya akan di isi oleh super admin. Dan jika link belum di
//       isi ketika klik menu tersebut tampilkan keterangan aplikasi sementara dikembangkan"* → menu
//       baru **Studio Foto Pintar** (`studio_foto.php`) memakai modul bersama `inc/daftar_apps.php`
//       (daftar aplikasi GLOBAL diisi Super Admin) + lencana **Bonus** berkedip seperti Smart Teacher
//       (skema **v54**: tabel `studio_foto`). Selama tautannya belum diisi, halaman menu menampilkan
//       keterangan **"Aplikasi Sementara Dikembangkan"** (kunci `kosong_judul`/`kosong_teks` pada
//       konfigurasi menu itu; menu lain tidak berubah).
// 1.73.3 = PERMINTAAN PEMILIK: *"Ganti masa aktif sekolah yang baru mendaftar yang semula 30 hari
//   menjadi 15 hari"* → konstanta **`PENDAFTARAN_HARI_AKTIF`** (`inc/helpers.php`) menjadi **15**,
//   jadi pendaftar mandiri (`daftar.php`) langsung aktif **15 hari** (bukan 30) sebelum aplikasi
//   terkunci sampai kode aktivasi lembaga dimasukkan. Seluruh tulisan "Pendaftaran Gratis (Aktif N
//   Hari)" pada `daftar.php`/`pendaftaran.php` serta panduan Pendaftaran (`inc/petunjuk.php`) kini
//   memakai konstanta itu (dulu satu tempat di kartu sukses masih menulis angka 30 mati).
//   Pilihan masa uji coba manual Super Admin (30/60/90 hari) TIDAK berubah.
// (Versi ditulis ulang terakhir setelah SELURUH berkas aplikasi selesai disentuh.)
define('APP_VERSION', '1.73.3');
// Unggahan berkas (logo sekolah) lewat media proxy platform. Token dibaca server-side
// dari berkas .vibecoder-media-token dan TIDAK pernah dikirim ke browser.
// AKTIVASI sekolah/madrasah: cadangan kunci rahasia generator KODE AKTIVASI.
// Nilai UTAMA dibuat acak sekali dan disimpan di BASIS DATA (tabel `app_pengaturan`, kunci
// `aktivasi_secret`) — bukan berkas di `data/`, karena berkas di sana dapat diunduh lewat web.
// Konstanta ini hanya dipakai bila penyimpanan ke basis data gagal. JANGAN mengubah nilainya
// setelah kode dibagikan ke sekolah: kode aktivasi lama akan berubah dan tidak bisa dipakai lagi.
define('AKTIVASI_SECRET_CADANGAN', 'eguru-aktivasi-cadangan-2026');

// ---------------------------------------------------------------------------------------------
// MASA AKTIF & PERMOHONAN KODE AKTIVASI — permintaan pemilik:
// "Pada menu status & Aktifasi pada bagian Aktivasi Sekolah/Madrasah tambahkan 'Hubungi HD Admin
//  untuk melakukan aktifasi. Kirim permohonan kode aktifasi (pada saat klik permohonan otomatis akan
//  diarahkan ke WA 085240102180 dengan pesan WA: Assalamualaikum HD Admin Aplikasi (nama aplikasi)
//  Kami dari (Nama Sekolah) ingin melakukan aktifasi aplikasi (nama aplikasi).'
//  Pada menu status & Aktifasi ini juga tambahkan Nomer Rekening "Bank Mandiri 1500010135547.
//  An. Supianto" dan tambahkan keterangan "Pastikan anda transfer ke nomer rekening tersebut untuk
//  proses aktifasi dan verikasi. Diluar rekening tersebut kami tidak bertanggungjawab dan aplikasi
//  anda tidak akan aktif"
//  Pada saat pengguna aktifasi melakukan aktifasi (memasukkan kode aktifasi dari admin) Pastikan
//  aplikasi aktif dengan terbuka semua fitur dengan masa aktif 1 tahun (365 hari). Buat hitungan
//  mundur. Dan jika masa aktif habis bisa menghubungi kembali HD Admin untuk meminta kode aktifas
//  yang baru.
//  Saat masa aktif habis, buat aplikasi otomatis mengunci semua fitur tanpa menghilangkan data
//  sebelumnya dan otomatis kolom input kode aktifasi muncul. dan meminta pengguna untuk mengaktifkan
//  kembali dengan menghubungi HD aplikasi."
// ---------------------------------------------------------------------------------------------

// Masa aktif setelah kode aktivasi dimasukkan: 1 tahun = 365 hari (lihat aktivasi_aktifkan()).
const AKTIVASI_HARI_AKTIF = 365;

// AMBANG POPUP PERINGATAN AKTIVASI (v1.71.4 — permintaan pemilik): *"Pada madrasah yang masa
// aktifnya sisa 7 hari tampilkan pop up peringatan agar segera melakukan aktifasi melalui akun
// kepala sekolah atau operator. Popup ini muncul di akun semua pengguna pada lembaga tersebut sampai
// masa aktif benar-benar habis."* → bila sisa masa aktif/uji coba ≤ angka ini, popup peringatan
// dicetak di SETIAP halaman berpengguna milik lembaga itu (lihat aktivasi_peringatan()).
const AKTIVASI_INGAT_HARI = 7;

// Nomor WA HD Admin untuk PERMOHONAN kode aktivasi DAN BUKTI TRANSFER (ditulis lokal 0…; diubah ke
// format internasional oleh wa_internasional()). Sejak v1.53.0 dapat diubah Super Admin lewat menu
// "Biaya & WA Aktivasi" — nilai di sini menjadi BAWAANNYA; halaman membaca `aktivasi_wa()`.
const AKTIVASI_WA_PERMOHONAN = '085240102180';

// Nomor rekening tujuan aktivasi & keterangannya (ditampilkan apa adanya pada kartu permohonan).
const AKTIVASI_REKENING = 'Bank Mandiri 1500010135547. An. Supianto';
const AKTIVASI_REKENING_KET = 'Pastikan anda transfer ke nomer rekening tersebut untuk proses aktifasi dan verikasi. Diluar rekening tersebut kami tidak bertanggungjawab dan aplikasi anda tidak akan aktif';

// NOMINAL BIAYA (v1.52.0 — permintaan pemilik: *"Pada menu status dan aktifasi tampilkan nominal
// aktifasi"*; v1.53.0 dapat DIUBAH Super Admin lewat menu "Biaya & WA Aktivasi").
// NILAI BAWAAN dua tarif:
//   • AKTIVASI_BIAYA            = biaya AKTIVASI pertama (masa aktif 1 tahun / 365 hari);
//   • AKTIVASI_BIAYA_LANGGANAN  = biaya LANGGANAN untuk tahun berikutnya (perpanjangan).
// Halaman WAJIB membaca lewat `aktivasi_biaya_pertama()` / `aktivasi_biaya_langganan()`
// (inc/helpers.php) — bukan konstanta ini langsung — supaya perubahan Super Admin terpakai, dan
// jangan menulis angka mati di halaman.
const AKTIVASI_BIAYA = 1000000;
const AKTIVASI_BIAYA_LANGGANAN = 500000;
// Batas kewajaran nominal yang boleh diisi Super Admin (Rp 1 miliar) — pengaman salah ketik.
const AKTIVASI_BIAYA_MAKS = 1000000000;
// Batas panjang nomor WA yang disimpan (hanya angka).
const AKTIVASI_WA_MAKS = 15;

// ---------------------------------------------------------------- SARANA & PRASARANA (data barang)
// Pilihan TETAP untuk penginputan data barang pada menu Tugas Tambahan "Sarana & Prasarana"
// (permintaan pemilik). Dipakai halaman input, impor, ekspor, dan lembar cetak — satu sumber
// kebenaran, jangan disalin ke halaman lain.
//
// CATATAN: **Sumber Dana TIDAK ada di sini** — atas permintaan pemilik ("Sumber dana rubah menjadi
// inputan manual") kolom itu kini **isian bebas** (diketik pengguna), bukan pilihan tetap.
const SARPRAS_KONDISI     = ['Baik', 'Rusak Ringan', 'Rusak Berat'];
const SARPRAS_JENIS       = ['Tetap', 'Tidak Tetap', 'Bergerak'];

// Kode menu Tugas Tambahan yang memiliki halaman penginputan data barang (permintaan pemilik:
// "Pada menu sarana prasarana isi menu buat untuk penginputan data barang"). Menu lain tetap
// berbunyi "Masih dalam Pengembangan".
const SARPRAS_KODE_MENU = 'sarpras';

// ---------------------------------------------------------------- KESISWAAN (program kerja)
// Permintaan pemilik: "Pada Menu kesiswaan isi dengan 'Laporan Program Kerja Kesiswaan' dengan menu
// inputan: Jenis Kegiatan, Bentuk Kegiatan, Tujuan, Target, Tahun Ajaran, Waktu Pelaksanaan
// (Pilihan centang dari Bulan Juli ke Juni), Sumber Dana, Keterangan."
// Waktu Pelaksanaan = centang BULAN (boleh lebih dari satu), urut tahun ajaran: Juli s.d. Juni.
const KS_KODE_MENU = 'kesiswaan';
const KS_BULAN = ['Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
                  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni'];
// Panjang maksimal isian teks pendek program kerja (menjaga basis data tetap rapi).
const KS_TEKS_MAKS = 120;
// Batas isian Sumber Dana pada program kerja (isian manual, sama panjangnya dengan data barang).
// SENGAJA dibuat konstanta sendiri: modul Kesiswaan tidak boleh bergantung pada inc/sarpras.php.
const KS_SUMBER_MAKS = 60;
const KS_URAIAN_MAKS = 400;
// Batas catatan TINDAK LANJUT pelanggaran oleh guru Tugas Tambahan Kesiswaan (skema v51,
// permintaan pemilik: "guru yang dapat tugas tambahan kesiswaan bisa melihat, memberikan tindak
// lanjut, dan mencetak seluruh catatan pelanggaran dari setiap guru mata pelajaran").
const KS_TL_CATATAN_MAKS = 500;

// ---------------------------------------------------------------- PEMBINA OSIM/OSIS
// Permintaan pemilik: menu "Pembina OSIM/OSIS" berisi dua laporan:
//   (1) "Laporan Susunan Organisasi" — jabatan mulai Kepala Sekolah/Madrasah, Wakil Bidang Kesiswaan,
//       Pembina OSIM/OSIS, Ketua, Wakil Ketua, Sekretaris, Bendahara, Seksi-Seksi (nama seksi ditambah
//       manual) hingga Anggota OSIM/OSIS; **tiap seksi & tiap anggota menjadi baris tersendiri**;
//       kolomnya hanya **Jabatan + Nama**;
//   (2) "Laporan Program Kerja" — Nama Program, Waktu Pelaksanaan (**tanggal lengkap**), Target,
//       Tahun Ajaran, Terlaksana (Ya/Tidak), Keterangan; plus impor, ekspor, dan cetak.
const OSIM_KODE_MENU = 'osim';
// Jabatan tetap pada susunan organisasi (dipakai sebagai saran pengetikan + tombol "Susun Baku").
// Nama SEKSI tidak ada di sini karena ditambahkan manual oleh pengguna.
const OSIM_JABATAN = ['Kepala Sekolah/Madrasah', 'Wakil Bidang Kesiswaan', 'Pembina OSIM/OSIS',
                      'Ketua OSIM/OSIS', 'Wakil Ketua OSIM/OSIS', 'Sekretaris', 'Bendahara',
                      'Anggota OSIM/OSIS'];
// Pilihan "Terlaksana" pada program kerja.
const OSIM_TERLAKSANA = ['Ya', 'Tidak'];
// Panjang maksimal isian teks (jabatan/nama/seksi, nama program, target, keterangan).
const OSIM_TEKS_MAKS = 120;
const OSIM_URAIAN_MAKS = 400;

// ---------------------------------------------------------------- PEMBINA PRAMUKA
// Permintaan pemilik: *"Pada menu pembina pramuka isi menu buat seperti menu Pembina Osim hanya saja
// pembina osim ganti dengan pembina pramuka"*.
//
// Jadi menu "Pembina Pramuka" memakai ISI & FITUR YANG SAMA dengan Pembina OSIM/OSIS (dua laporan:
// Susunan Organisasi + Program Kerja, beserta impor, ekspor, bagan, dan cetaknya) — yang berbeda
// hanya SATU baris jabatan bakunya: "Pembina OSIM/OSIS" menjadi **"Pembina Pramuka"**. Jabatan lain
// (Ketua/Wakil Ketua/Anggota) sengaja dibiarkan sama sesuai permintaan (hanya "pembina" yang diganti);
// pengguna tetap bebas menulis jabatan/seksi apa pun karena isiannya bebas + ada saran pengetikan.
const PRAMUKA_KODE_MENU = 'pramuka';
const PRAMUKA_JABATAN = ['Kepala Sekolah/Madrasah', 'Wakil Bidang Kesiswaan', 'Pembina Pramuka',
                         'Ketua OSIM/OSIS', 'Wakil Ketua OSIM/OSIS', 'Sekretaris', 'Bendahara',
                         'Anggota OSIM/OSIS'];
// ---------------------------------------------------------------- PEMBINA PMR
// Permintaan pemilik: *"Tugas Tambahan Pembina PMR isi menu buat sama seperti Pembina OSIM/OSIS"*.
// Jadi menu "Pembina PMR" memakai ISI & FITUR YANG SAMA dengan Pembina OSIM/OSIS (dan Pembina
// Pramuka): Susunan Organisasi + bagan + Program Kerja + impor + ekspor + cetak, memakai SATU modul
// `inc/osim.php`. Yang berbeda hanya nama menu, SATU baris jabatan bakunya ("Pembina OSIM/OSIS"
// menjadi **"Pembina PMR"** — pola yang sama dengan Pembina Pramuka), judul bawaan bagan, serta
// penyimpanan judul/periode bagannya (kolom `sekolah.pmr_bagan_*`).
const PMR_KODE_MENU = 'pmr';
const PMR_JABATAN = ['Kepala Sekolah/Madrasah', 'Wakil Bidang Kesiswaan', 'Pembina PMR',
                     'Ketua OSIM/OSIS', 'Wakil Ketua OSIM/OSIS', 'Sekretaris', 'Bendahara',
                     'Anggota OSIM/OSIS'];

// Kode menu Tugas Tambahan yang memakai modul `inc/osim.php` (satu modul, TIGA menu).
// ⚠️ Jangan menyalin modulnya menjadi beberapa berkas — seluruh fungsi menerima `$kode` dan
// memisahkan data per sekolah + per menu (kolom `kode` pada `osim_pengurus`/`osim_program`).
const OSIM_KODE_LIST = ['osim', 'pramuka', 'pmr'];

// ---------------------------------------------------------------- KEPALA PERPUSTAKAAN (laporan e-PERPUS)
// Permintaan pemilik: "Pada tugas tambahan menu Ka. Perpustakaan, menunya diisi dengan: Laporan
// Anggota, Laporan Data Buku, Laporan Peminjaman, Laporan Pengembalian, Laporan Pengunjung.
// Semua laporan ini di impor dari hasil unduhan aplikasi e-perpus. Ditambahkan juga fitur cetak
// untuk setiap laporannya."
//
// Jadi menu Tugas Tambahan "Ka. Perpustakaan" TIDAK menginput data sendiri: pengguna mengunduh
// laporan dari aplikasi e-PERPUS (CSV/Excel) lalu mengimpornya ke sini, dan tiap laporan punya
// lembar cetak sendiri. Satu himpunan data per (sekolah, jenis) — impor ulang menggantikan.
const PERPUS_KODE_MENU = 'kepala_perpus';
// Urutan di sini = urutan tab & lembar cetak (jangan diubah tanpa alasan).
const PERPUS_JENIS = [
    'anggota'      => 'Laporan Anggota',
    'buku'         => 'Laporan Data Buku',
    'peminjaman'   => 'Laporan Peminjaman',
    'pengembalian' => 'Laporan Pengembalian',
    'pengunjung'   => 'Laporan Pengunjung',
];
// Batas impor (menjaga basis data & ukuran kiriman formulir tetap wajar — post_max_size 21 MB).
const PERPUS_MAKS_KOLOM = 30;     // jumlah kolom yang diterima dari satu berkas laporan
const PERPUS_MAKS_BARIS = 3000;   // jumlah baris yang diterima dari satu berkas laporan
const PERPUS_SEL_MAKS   = 200;    // panjang maksimal isi satu sel
const PERPUS_PER_HALAMAN = 50;    // baris per halaman pada tampilan layar (cetak mencetak semua)

// ---------------------------------------------------------------- KEPALA LAB (IPA & KOMPUTER)
// Permintaan pemilik: "Pada Menu Kepala Lab IPA dan Lab Komputer menu isi dengan:
// 1. Daftar Inventaris Barang (No, Nama Barang, Jumlah, Keadaan (Baik atau Rusak), Keterangan)
// 2. Laporan Program (No, Program Kerja, Alokasi Waktu, Status Realisasi (Terlaksana atau Belum),
// Keterangan). Pastikan ditambahkan fitur cetak dokumen."
//
// Kedua menu memakai struktur yang SAMA, jadi keduanya dilayani satu modul `inc/lab.php`; yang
// membedakan hanyalah kolom `kode` (baris data dipisah per menu). Kode menu TIDAK berubah walau
// Super Admin mengganti NAMA menunya ("Ka. Lab IPA" / "Ka. Lab Komputer" pada data pemilik),
// sedangkan `tata_usaha` pada menu aslinya bernama "Tata Usaha".
const LAB_KODE_MENU = ['kepala_lab', 'tata_usaha'];
// Pilihan TETAP untuk kolom Keadaan & Status Realisasi (permintaan pemilik).
const LAB_KEADAAN = ['Baik', 'Rusak'];
const LAB_STATUS  = ['Terlaksana', 'Belum'];
// Panjang maksimal isian teks (menjaga basis data & tabel tetap rapi).
const LAB_TEKS_MAKS = 160;
const LAB_KET_MAKS  = 300;

// ---------------------------------------------------------------- KURIKULUM (kirim dokumen ke guru)
// Permintaan pemilik: "Pada menu kurikulum isi menu dengan fitur untuk mengirim dokumen kepada guru.
// Dokumen bisa berupa pdf, gambar atau lainnya. Dokumen yang dikirim akan masuk di akun guru pada
// sekolah tersebut. Fiturnya Unggah Dokumen ke guru: Judul Dokumen, Catatan, Unggah file"
//
// Keputusan pemilik (jangan diubah tanpa bertanya):
//   1. satu kiriman ditujukan untuk **SEMUA guru di sekolah itu** (bukan per guru);
//   2. yang boleh mengirim: **Kepala Sekolah/Madrasah & Admin Operator**, serta **guru yang
//      ditugaskan** menu Kurikulum (pola Tugas Tambahan yang sudah berlaku);
//   3. guru melihatnya pada **kartu di dashboard** + halaman khusus **"Dokumen Saya"**.
//
// Jenis berkas yang diterima SAMA dengan yang didukung media proxy platform — berkas yang tidak ada
// di daftar ini ditolak sebelum dikirim (supaya tidak ada unggahan yang gagal tanpa penjelasan).
// Batas ukurannya juga mengikuti platform, TETAPI tetap dibatasi lagi oleh batas server PHP
// (lihat dok_batas_efektif() di inc/dokumen.php — batas itu yang ditampilkan ke pengguna).
const DOK_EKSTENSI_GAMBAR  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
const DOK_EKSTENSI_VIDEO   = ['mp4', 'webm', 'mov'];
const DOK_EKSTENSI_DOKUMEN = ['pdf', 'docx', 'xlsx', 'pptx', 'csv', 'txt', 'zip'];
const DOK_MAKS_GAMBAR  = 15 * 1024 * 1024;    // 15 MB (batas platform untuk gambar)
const DOK_MAKS_VIDEO   = 300 * 1024 * 1024;   // 300 MB (batas platform untuk video)
const DOK_MAKS_DOKUMEN = 50 * 1024 * 1024;    // 50 MB (batas platform untuk dokumen)
const DOK_JUDUL_MAKS   = 160;
const DOK_CATATAN_MAKS = 600;
// Kode menu Tugas Tambahan yang berisi fitur ini.
const KURIKULUM_KODE_MENU = 'kurikulum';

// ===================== FITUR "KIRIM PERANGKAT" (skema v48) =====================
// Permintaan pemilik: *"Menu Perangkat Guru yang ada di Super Admin dihapus saja dengan isinya. Menu
// ini sudah tidak dibutuhkan. Sekarang buatkan fitur Kirim Perangkat di akun guru (Seperti Kalender
// Pendidikan, Analisis Pekan Efektif, Capaian Pembelajaran, Alur Tujuan Pembelajaran, RPP/Modul Ajar.
// Nama-nama dokumen ini di isi oleh Super Admin, bisa ditambah atau dikurangi. File yang terkirim
// masuk di akun Kepala sekolah (…bisa meninjau dokumen tersebut dengan: Dokumen Disetujui, Dokumen
// Direvisi, Dokumen Ditolak sekaligus bisa mendownload), dan Pengawas (Pengawas hanya lihat dan
// download). Hasil tinjauan kepala sekolah terkirim ke akun guru"*
//
// Keputusan pemilik (jangan diubah tanpa bertanya):
//   1. menu & halaman "Perangkat Guru" lama (termasuk KODE DOWNLOAD) **DIHAPUS SELURUHNYA**;
//   2. daftar Nama Dokumen = **SATU daftar GLOBAL** untuk semua sekolah/madrasah, diisi Super Admin;
//   3. satu berkas **per Nama Dokumen × per mata pelajaran yang diampu** (unggah ulang menggantikan);
//   4. memakai **Tahun Ajaran** (tersimpan pada tiap kiriman, jadi perangkat tahun lalu tetap ada).
//
// Nama dokumen BAWAAN yang disemai sekali saat migrasi v48 (Super Admin boleh menambah/mengurangi
// lewat menu "Jenis Perangkat"). Nama persis seperti permintaan pemilik.
const PERANGKAT_JENIS_BAWAAN = [
    'Kalender Pendidikan',
    'Analisis Pekan Efektif',
    'Capaian Pembelajaran',
    'Alur Tujuan Pembelajaran',
    'RPP/Modul Ajar',
];
// Panjang maksimal nama dokumen (isi Super Admin), keterangan pengirim, dan catatan peninjau.
const PERANGKAT_NAMA_MAKS       = 80;
const PERANGKAT_KETERANGAN_MAKS = 600;
const PERANGKAT_CATATAN_MAKS    = 1000;

// CATATAN PENGAWAS (skema v52) — catatan pengawas pada tiap perangkat, **TERPISAH** dari status
// tinjauan kepala sekolah. Permintaan pemilik: *"Pada akun pengawas tambahkan menu Perangkat Guru …
// Pengawas bisa melihat, memberikan catatan setiap perangkat dan mendownload"*, dengan pilihan
// "Catatan pengawas terpisah dari status tinjauan kepala (guru & kepala sekolah bisa membacanya)".
// Satu pengawas = satu catatan per perangkat (boleh diperbarui); catatan ini TIDAK mengubah
// `perangkat_kirim.status`/`catatan` milik kepala sekolah.
const PERANGKAT_CATATAN_PENGAWAS_MAKS = 1000;

/**
 * Keempat keadaan tinjauan dokumen (urut sesuai tampilan).
 *
 * `menunggu` = BELUM DITINJAU dan sekaligus pilihan untuk MEMBATALKAN tinjauan (memilih keadaan ini
 * mengosongkan keterangan & peninjau) — pola yang sama dengan status tinjauan dokumen madrasah pada
 * aplikasi Supervisi Guru.
 */
const PERANGKAT_STATUS = [
    'menunggu'  => 'Belum Ditinjau',
    'disetujui' => 'Dokumen Disetujui',
    'revisi'    => 'Dokumen Direvisi',
    'ditolak'   => 'Dokumen Ditolak',
];
// Keadaan yang WAJIB disertai keterangan/alasan (itulah "alasannya" bagi guru).
const PERANGKAT_STATUS_ALASAN_WAJIB = ['revisi', 'ditolak'];

// ===================== ISI "TENTANG APLIKASI" PADA HALAMAN MASUK =====================
// Permintaan pemilik: *"Pada halaman depan di bagian tentang aplikasi tambahkan tempat foto, nama
// dan keterangan dengan posisi atau letak dibuat sama dengan aplikasi supervisi"* — dengan pilihan:
// **dua foto** (seperti aplikasi Supervisi), tulisan Tentang Aplikasi yang lama **diganti** oleh
// keterangan yang diisi pemilik, dan pengisiannya lewat **menu baru "Tentang Aplikasi"** pada akun
// Super Admin (halaman `tentang.php`).
// Disimpan di tabel `app_pengaturan` (tingkat APLIKASI, karena halaman masuk dipakai semua
// sekolah/madrasah). Kunci: `tentang_foto_1`, `tentang_nama_1`, `tentang_foto_2`, `tentang_nama_2`,
// `tentang_keterangan`, dan penanda sekali-jalan `tentang_seed`.
const TENTANG_FOTO_SLOT = 2;          // jumlah tempat foto 3x4
const TENTANG_NAMA_MAKS = 80;         // panjang maksimal nama di bawah foto
const TENTANG_KET_MAKS  = 3000;       // panjang maksimal keterangan/penjelasan
const TENTANG_FOTO_RASIO = '3x4';     // informasi bagi Super Admin (kotak foto memakai rasio 3:4)
const TENTANG_FOTO_MAKS_BYTE = 15 * 1024 * 1024;   // batas platform untuk gambar (15 MB)

// ===================== JENIS LEMBAGA & PILIHAN JENJANG (skema v49, v1.68.0) =====================
// Permintaan pemilik: *"pada yang ditandai warna biru ganti dengan Jenis Lembaga. dan pada yang saya
// tandai warna merah tambahkan fitur Pilihan Jenjang. Ketika Jenis lembaga yang dipilih sekolah umum
// maka fitur pilihan jenjang yang aktif SD, SMP, SMA, SMK. Ketika yang dipilih madrasah pilihan
// jenjangnya MI, MTs, MA, MAK. Untuk setingan MTs/SMP, SMA/MA/SMK pengaturan tetap seperti sekarang
// ini tidak ada perubahan. Tapi ketika yang dipilih jenjang SD atau MI maka setingan berbeda. Karena
// guru MI atau SD itu guru kelas yang memegang banyak mata pelajaran"*.
//
// Pilihan pemilik (jangan diubah tanpa bertanya):
//   1. penanda **"Guru Kelas"** pada Data Guru + guru itu **otomatis menjadi Wali Kelas**, sedangkan
//      mata pelajaran **tetap dicentang manual** (tidak ada pencentangan otomatis);
//   2. Pilihan Jenjang tampil di **Identitas & Pengaturan**, **halaman Pendaftaran**, dan
//      **Kelola Sekolah (Super Admin)**;
//   3. jenjang menjadi **acuan tingkat kelas** saat menambah kelas (SD/MI I–VI, SMP/MTs VII–IX,
//      SMA/MA/SMK X–XII).
//
// JENJANG yang berlaku untuk tiap JENIS LEMBAGA (urutan tampil pada pilihan).
const JENJANG_PILIHAN = [
    'umum'     => ['SD', 'SMP', 'SMA', 'SMK'],
    'madrasah' => ['MI', 'MTs', 'MA', 'MAK'],
];
// Keterangan panjang tiap jenjang (dipakai pada pilihan & pil status).
const JENJANG_LABEL = [
    'SD'  => 'SD — Sekolah Dasar',
    'SMP' => 'SMP — Sekolah Menengah Pertama',
    'SMA' => 'SMA — Sekolah Menengah Atas',
    'SMK' => 'SMK — Sekolah Menengah Kejuruan',
    'MI'  => 'MI — Madrasah Ibtidaiyah',
    'MTs' => 'MTs — Madrasah Tsanawiyah',
    'MA'  => 'MA — Madrasah Aliyah',
    'MAK' => 'MAK — Madrasah Aliyah Kejuruan',
];
// Tingkat kelas yang menjadi ACUAN (datalist) saat menambah kelas pada halaman Kelas.
const JENJANG_TINGKAT = [
    'SD'  => ['I', 'II', 'III', 'IV', 'V', 'VI'],
    'MI'  => ['I', 'II', 'III', 'IV', 'V', 'VI'],
    'SMP' => ['VII', 'VIII', 'IX'],
    'MTs' => ['VII', 'VIII', 'IX'],
    'SMA' => ['X', 'XI', 'XII'],
    'MA'  => ['X', 'XI', 'XII'],
    'SMK' => ['X', 'XI', 'XII'],
    'MAK' => ['X', 'XI', 'XII'],
];
// Jenjang yang gurunya adalah GURU KELAS: satu guru memegang BANYAK mata pelajaran pada SATU kelas
// (dan otomatis ditetapkan sebagai wali kelas kelas itu). Jenjang lain tetap seperti sebelumnya.
const JENJANG_GURU_KELAS = ['SD', 'MI'];

// ---------------------------------------------------------------- PENILAIAN SISWA (kolom Formatif)
// Permintaan pemilik (v1.36.0): "Pada lembar penilaian formatif guru bisa menginput nilai berupa
// angka, huruf atau tanda centang." Kolom **Formatif** boleh berisi angka (0-100), huruf (mis. A/B/C/D),
// atau tanda centang; nilai Sumatif Lingkup Materi & ASAS tetap ANGKA.
// Daftar saran yang muncul pada tiap sel isian Formatif (angka tetap boleh diketik langsung).
const NILAI_FORMATIF_PILIHAN = ['✓', 'A', 'B', 'C', 'D'];
// Panjang maksimal teks huruf yang diterima pada satu sel Formatif (selebihnya dipotong).
const NILAI_FORMATIF_HURUF_MAKS = 8;
// Ejaan tanda centang yang diterima dari pengguna → selalu disimpan sebagai '✓'.
const NILAI_FORMATIF_CENTANG = ['✓', '✔', '√', 'v', 'vh', 'cek', 'check', 'centang'];

// ---------------------------------------------------------------- JURNAL HARIAN GURU
// Permintaan pemilik (v1.37.0): *"Pada bagian Input Jurnal Harian Baru tambahkan kolom 'Uraian
// Kegiatan (Pilihan Mengajar bisa juga input manual jika kegiatan selain mengajar)' setelah kolom
// tanggal. Pada kolom kelas berubah menjadi 'Kelas/Tempat' selain pilih kelas guru bisa menginput
// manual tempatnya. Mata Pelajaran dan materi/topik pembelajaran bisa dikosongkan jika kegiatan guru
// selain mengajar"*.
//  • URAIAN KEGIATAN — pilihannya "Mengajar" (boleh diketik kegiatan lain secara manual).
//    Catatan lama & isian kosong dianggap "Mengajar" supaya data yang sudah ada tetap utuh.
//  • KELAS/TEMPAT — guru MEMILIH KELAS **atau** mengisi TEMPAT manual (tidak boleh keduanya).
//  • HANYA catatan ber-uraian "Mengajar" yang dihitung sebagai TATAP MUKA pada Laporan Rekap
//    Jumlah Pertemuan Guru (lihat jurnal_sql_mengajar()/pertemuan_where() di inc/helpers.php).
const JURNAL_URAIAN_BAWAAN = 'Mengajar';
const JURNAL_URAIAN_PILIHAN = ['Mengajar'];   // daftar saran pada isian (tetap bisa diketik bebas)
const JURNAL_URAIAN_MAKS = 60;                // panjang maksimal uraian kegiatan
const JURNAL_TEMPAT_MAKS = 80;                // panjang maksimal tempat kegiatan manual
const JURNAL_MAPEL_WAJIB_MENGAJAR = true;     // mapel & materi wajib HANYA untuk kegiatan mengajar

// Kode menu Tugas Tambahan "Wali Kelas" (empat laporan kelas — lihat inc/wali.php).
// Didefinisikan di sini karena dipakai juga oleh inc/helpers.php (aturan akses: guru yang
// DITETAPKAN sebagai wali kelas otomatis boleh membuka menunya, tanpa penugasan terpisah).
const WALI_KODE_MENU = 'wali_kelas';

// Kode baku menu Tugas Tambahan **"Ka. Tata Usaha"**. Dipakai hak akses Notulen Rapat
// (permintaan pemilik: menu Notulen Rapat dipindah ke akun guru yang bertugas tambahan sebagai
// Ka. Tata Usaha). `tugas_menu_ka_tu()` tetap mencocokkan NAMA sebagai cadangan bila Super Admin
// memakai kode lain.
const KA_TU_KODE_MENU = 'ka-tu';

// ---------------------------------------------------------------- GRUP WhatsApp e-GURU
// Permintaan pemilik: "Pada Akun Kepala Sekolah, Operator dan Guru pada halaman dasboard tambahkan
// Fitur Gabung Grup e-Guru (link: https://chat.whatsapp.com/CSfXdQ8NE07250AJtZ4tJL) tambahkan
// keterangan 'Dapatkan info menarik dan update aplikasi dengan bergabung bersama grup premium
// e-Guru'." → ubah tautan/keterangannya DI SINI (jangan di halaman) bila grupnya berganti.
// Judul kartu, tautan grup, dan keterangannya. Sejak v1.48.0 ketiganya dapat diubah Super Admin
// lewat menu "Gabung Grup e-GURU" (tersimpan di `app_pengaturan`); konstanta ini menjadi NILAI
// BAWAAN bila Super Admin belum pernah mengubahnya.
const GRUP_EGURU_JUDUL = 'Gabung Grup e-GURU';
const GRUP_EGURU_URL = 'https://chat.whatsapp.com/CSfXdQ8NE07250AJtZ4tJL';
const GRUP_EGURU_KET = 'Dapatkan info menarik dan update aplikasi dengan bergabung bersama grup premium e-Guru';
// Batas panjang isian pada halaman pengaturannya (menjaga basis data tetap rapi).
const GRUP_EGURU_JUDUL_MAKS = 60;
const GRUP_EGURU_URL_MAKS   = 300;
const GRUP_EGURU_KET_MAKS   = 300;

// LABEL "BONUS" BERKEDIP pada menu daftar aplikasi (permintaan pemilik).
// Riwayat: v1.59.0 memakai tulisan **NEW** untuk menu "Ujian Online". **v1.72.2** pemilik meminta
// *"Tulisan new berkedip ganti dengan tulisan 'Bonus'. Pada menu Smart Teacher juga tambahkan fitur
// 'Bonus' berkedip"* → teksnya kini **Bonus** (merah, berkedip) dan fiturnya dipakai menu
// **Ujian Online** + **Smart Teacher**.
// Super Admin menandainya lewat centang "Tandai sebagai bonus" pada formulir aplikasinya, lalu
// tulisan **Bonus** tampil DI ATAS nama menu pada sidebar — hilang sendiri setelah jumlah hari di
// bawah ini.
const DAFTAR_APPS_BARU_HARI = 30;
// Teks lencana (dipakai `daftar_apps_badge_new()` dan kotak setelan pada formulir aplikasi).
// JANGAN menulis "Bonus" langsung di halaman/modul — ubah di sini bila pemilik berganti teks lagi.
const DAFTAR_APPS_BARU_TEKS = 'Bonus';

define('MEDIA_ENDPOINT', 'http://127.0.0.1:4310/api/app-media/upload');
define('FOTO_MAKS_BYTE', 15 * 1024 * 1024); // 15 MB (batas platform untuk gambar)
define('FOTO_EKSTENSI', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);

// Foto BUKTI KEGIATAN pada notulen rapat: maksimal 2 foto per notulen
// (permintaan pemilik). Batas ini ditegakkan di server (upload_notulen_foto.php).
define('NOTULEN_FOTO_MAKS', 2);

// Ukuran foto bukti kegiatan SAAT DICETAK pada lembar "Foto Dokumentasi" notulen.
//   '4R' = 4 x 6 inci  ≈ 102 x 152 mm
//   '3R' = 3,5 x 5 inci ≈ 89 x 127 mm
// Cukup ubah SATU baris ini untuk berpindah ukuran; tata letak lembar menyesuaikan sendiri
// (lihat foto_cetak_ukuran() di inc/helpers.php).
define('NOTULEN_FOTO_CETAK', '4R');

// Pembuat aplikasi (tampil pada halaman login & footer).
define('APP_AUTHOR', 'Supianto');
define('APP_AUTHOR_JABATAN', 'Kepala MA. Sirojuth Tholibiin Mootilango');

// Berkas database SQLite (di dalam folder aplikasi).
define('DB_FILE', __DIR__ . '/../data/eguru.sqlite');

// Aplikasi selalu diakses di bawah subfolder-nya sendiri (https://.../e-guru/),
// jadi SEMUA tautan memakai path relatif (tanpa leading slash).
define('BASE', '');

date_default_timezone_set('Asia/Jakarta');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Simpan berkas sesi di dalam folder aplikasi (folder sesi sistem bisa tidak writable).
    $sessDir = __DIR__ . '/../data/sessions';
    if (!is_dir($sessDir)) {
        @mkdir($sessDir, 0775, true);
    }
    if (is_dir($sessDir) && is_writable($sessDir)) {
        session_save_path($sessDir);
    }

    // ================= KEAMANAN SESI (JANGAN DIHAPUS) =================
    // Diatur dari aplikasi karena nilai bawaan PHP di server ini TIDAK aman:
    //   session.cookie_httponly = Off, session.cookie_samesite = (kosong),
    //   session.use_strict_mode = Off.
    //  • use_strict_mode : id sesi yang TIDAK dikenal server ditolak → menutup SESSION FIXATION
    //                      (penyerang menyodorkan id sesi buatannya sendiri lalu menunggu korban
    //                      memakainya). `login.php` tetap memanggil session_regenerate_id(true).
    //  • use_only_cookies: id sesi tidak pernah diambil dari URL (anti session id di tautan).
    //  • httponly        : cookie sesi tidak dapat dibaca JavaScript → dampak XSS dibatasi
    //                      (penyerang tidak bisa mencuri cookie sesi).
    //  • samesite=Lax    : permintaan lintas situs tidak membawa cookie sesi → lapisan tambahan
    //                      di atas token CSRF (tombol/link dari situs lain tidak berfungsi).
    //  • secure          : cookie hanya dikirim lewat HTTPS. Dinilai dari permintaan yang sedang
    //                      berjalan (aplikasi berada di belakang proxy Caddy, jadi X-Forwarded-Proto
    //                      ikut diperiksa) supaya pengujian lokal lewat http://127.0.0.1 tetap jalan.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    $protokolHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $protokolHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// ================= SANDI KONFIRMASI HAPUS SEKOLAH/MADRASAH (v1.72.3) =================
// Permintaan pemilik: *"Tambahkan fitur non aktifkan dan fitur hapus pada yang ditandai warna merah.
// Sebelum menghapus super admin akan dimintakan sandi (sandinya: 181288). Tujuan sandi ini agar
// ketika tidak sengaja di klik hapus lembaga tidak langsung terhapus"*.
//
// Tombol **Hapus** pada daftar sekolah/madrasah (dasbor Ringkasan Semua + halaman Kelola Sekolah)
// TIDAK langsung menghapus: Super Admin harus mengetik sandi ini lebih dulu. Sandi diperiksa di
// SERVER (`sekolah_hapus_sandi_sah()` di `inc/helpers.php`), bukan hanya di peramban — menghapus
// lembaga berarti menghapus SELURUH datanya (guru, siswa, nilai, jurnal, akun) sehingga tidak boleh
// bisa dijalankan karena salah klik atau karena satu permintaan POST buatan.
// Ubah nilainya di sini bila pemilik mengganti sandi.
const SEKOLAH_HAPUS_SANDI = '181288';

// ================= BATAS PERCOBAAN MASUK (ANTI BRUTE FORCE) =================
// Permintaan pemilik: "Pastikan sistem keamanan aplikasi aman dari peretasan atau serangan hacker."
// Tanpa pembatas, kata sandi akun dapat dicoba berulang-ulang tanpa henti oleh program. Hitungannya
// diambil dari riwayat percobaan GAGAL pada tabel `log_aktivitas` (kolom ip/username/waktu) —
// TIDAK memakai tabel baru, sehingga tidak ada migrasi skema.
//   • LOGIN_MENIT_GAGAL   : rentang waktu hitungan (menit).
//   • LOGIN_MAKS_GAGAL_IP : jumlah kegagalan dari SATU alamat IP pada rentang itu.
//   • LOGIN_MAKS_GAGAL_USER : jumlah kegagalan untuk SATU nama pengguna dari satu alamat IP —
//     **juga dipakai untuk SATU NISN siswa** sejak v1.65.0 (masuk siswa memakai NISN + kata sandi
//     tanggal lahir), berlaku tanpa memandang alamat IP.
//   • LOGIN_MAKS_GAGAL_SISWA: jumlah percobaan masuk SISWA yang gagal dari satu alamat IP PUBLIK
//     (IP privat/loopback diabaikan karena itu alamat bersama proxy lokal).
// Keberhasilan masuk dari IP itu MENGOSONGKAN hitungan (lihat login_blokir_detik()).
// Lihat `login_blokir_detik_siswa()` di inc/helpers.php (hitungan per NISN + per IP).
const LOGIN_MENIT_GAGAL = 10;
const LOGIN_MAKS_GAGAL_IP = 30;
const LOGIN_MAKS_GAGAL_USER = 10;
const LOGIN_MAKS_GAGAL_SISWA = 12;

// ================= TEMPLATE WARNA KARTU PELAJAR (v1.72.1) =================
// Permintaan pemilik: *"Pada fitur cetak kartu pelajar tambahkan pilihan template warna. Contoh desain
// yang saya upload. Untuk posisi tulisan, logo, barcode tetap. Yang saya upload hanya untuk contoh
// tampilan desainnya saja"*.
//
// Keputusan (jangan diubah tanpa bertanya):
//   • yang berubah **hanya WARNA/desain** — TATA LETAK (kepala kartu + logo, kotak foto 3x4, nama,
//     baris NISN/NIS/Jenis Kelamin/Tanggal Lahir, barcode QR di kanan-bawah, kaki "masa berlaku",
//     ukuran 88 x 55 mm, 8 kartu per lembar A4) TIDAK berubah sama sekali;
//   • pilihan berupa **template** (bukan pemilih warna bebas) supaya hasil cetak selalu rapi;
//   • template dipilih pada halaman cetak itu sendiri (deretan tombol "chip") dan **diingat di sesi**,
//     jadi tidak perlu memilih ulang setiap kali mencetak.
//
// Kunci array = kode pada URL (`?tpl=…`). `warna` = dua warna untuk pratinjau tombol (gradasi).
// Kode `hijau-emas` adalah DESAIN BAWAAN (yang sudah ada) — jangan dihapus.
const KARTU_TEMPLATE = [
    'hijau-emas' => [
        'label' => 'Hijau Emas',
        'ket'   => 'Bawaan aplikasi — hijau tua dengan garis emas',
        'warna' => ['#0b4a33', '#c9a227'],
    ],
    'hijau-cerah' => [
        'label' => 'Hijau Cerah',
        'ket'   => 'Hijau daun cerah dengan aksen emas',
        'warna' => ['#15803d', '#eab308'],
    ],
    'tosca' => [
        'label' => 'Biru Tosca',
        'ket'   => 'Biru tosca muda, bersih dan modern',
        'warna' => ['#0891b2', '#22d3ee'],
    ],
    'maroon' => [
        'label' => 'Merah Maroon',
        'ket'   => 'Merah maroon tegas dengan latar putih',
        'warna' => ['#7f1d1d', '#dc2626'],
    ],
    'indigo' => [
        'label' => 'Ungu Indigo',
        'ket'   => 'Ungu indigo, senada dengan tema aplikasi',
        'warna' => ['#312e81', '#8b5cf6'],
    ],
];
// Kunci sesi untuk mengingat template terakhir yang dipilih.
const KARTU_TEMPLATE_SESI = 'kartu_template';
