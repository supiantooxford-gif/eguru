<?php
/**
 * UJIAN ONLINE — daftar link aplikasi ujian online (nama aplikasi, link, keterangan).
 *
 * Permintaan pemilik: *"Dibawah menu pelanggaran siswa tambahkan menu 'Ujian Online'. Isi dari menu
 * ini buat seperti menu smart teacher yaitu memasukkan link aplikasi ujian online lewat akun super
 * admin"* → bentuknya SAMA PERSIS dengan menu Smart Teacher: daftar **GLOBAL** (satu untuk semua
 * sekolah/madrasah), terlihat SEMUA pengguna, tampil sebagai KARTU berisi nama + keterangan + tombol
 * "Buka Aplikasi", dan isinya diisi **Super Admin** lewat halaman ini.
 *
 * Seluruh perilaku (validasi tautan, pengurutan, sembunyikan, hapus, render kartu) ada pada
 * MODUL BERSAMA `inc/daftar_apps.php` — jangan menyalin logikanya ke sini.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/daftar_apps.php';

daftar_apps_halaman('ujian_online');
