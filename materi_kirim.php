<?php
/**
 * HALAMAN GURU "KIRIM MATERI/TUGAS KE SISWA" (`materi_kirim.php`) — v1.113.0 / skema v73.
 *
 * Permintaan pemilik: *"Pada akun guru tambahkan menu 'Kirim Materi/Tugas Ke Siswa'. Isi dari menu ini
 * guru bisa mengirim sekaligus ke siswa yang dia ajar atau memilih siswa tertentu. Modulnya: Judul
 * Materi/Tugas, Mata Pelajaran, Unggah (bisa pdf, word, gambar dan Maksimal 2MB) dan ada setingan
 * batas waktu tugas/materi bisa dibuka oleh siswa. Hasilnya akan terkirim ke akun siswa dengan
 * menampilkan nama guru pengirim, mata pelajaran, file/dokumen (bisa di download dan dicetak)"*.
 *
 * Halaman ini hanya berkas tipis: seluruh logika (validasi, unggah berkas ke media proxy, penerima,
 * pengaturan waktu, dan tampilan) ada di modul `inc/materi_siswa.php`.
 * **Jangan menyalin logikanya ke halaman lain.**
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

materi_halaman_guru();
