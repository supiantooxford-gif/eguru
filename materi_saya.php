<?php
/**
 * HALAMAN SISWA "MATERI & TUGAS" (`materi_saya.php`) — v1.113.0 / skema v73.
 *
 * Permintaan pemilik: *"Hasilnya akan terkirim ke akun siswa dengan menampilkan nama guru pengirim,
 * mata pelajaran, file/dokumen (bisa di download dan dicetak)"*.
 *
 * Halaman ini hanya MELIHAT & MENCETAK (akun siswa tidak boleh mengubah apa pun — ditegakkan
 * `gerbang_siswa()`; berkas ini terdaftar pada `siswa_berkas_boleh()`).
 * Seluruh logika ada di modul `inc/materi_siswa.php`.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

materi_halaman_siswa();
