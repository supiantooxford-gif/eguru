<?php
/**
 * DAFTAR HADIR MEETING/PERTEMUAN — akun PENGAWAS (`meeting.php`), e-GURU v1.73.0 / skema v55.
 *
 * Permintaan pemilik: *"Pada akun pengawas tambahkan menu aktifkan daftar hadir meeting
 * (pertemuan/zoom). Jadwal di atur dengan memasukkan tanggal pertemuan, judul pertemuan/meeting.
 * Pengawas bisa mengaktifkan daftar hadir binaan yang dia pilih atau seluruh binaannya."*
 *
 * Seluruh logika ada pada MODUL `inc/meeting.php` (`meeting_halaman_pengawas()`) — **jangan
 * menyalinnya ke sini**. Berkas ini juga terdaftar pada `pengawas_berkas_boleh()` dan
 * `pengawas_berkas_post()` (`inc/helpers.php`) supaya gerbang pengawas mengizinkan pengawas
 * MEMBUAT & mengubah meeting miliknya sendiri.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/meeting.php';

meeting_halaman_pengawas();
