<?php
/**
 * STUDIO FOTO PINTAR — daftar aplikasi Studio Foto Pintar (nama aplikasi, link, keterangan).
 *
 * Permintaan pemilik (v1.72.4): *"dibawah menu Smart Teacher tambahkan menu Studio Foto Pintar
 * (tambahkan label Bonus seperti Menu Smart Teacher) dan linknya akan di isi oleh super admin.
 * Dan jika link belum di isi ketika klik menu tersebut tampilkan keterangan aplikasi sementara
 * dikembangkan"*.
 *
 * Bentuknya SAMA dengan menu Smart Teacher: daftar GLOBAL (satu daftar untuk semua
 * sekolah/madrasah), diisi **Super Admin** saja, tampil sebagai kartu berisi nama + keterangan +
 * tombol "Buka Aplikasi", dan aplikasinya dapat ditandai **Bonus** (lencana merah berkedip pada
 * menu sidebar — lihat `label_baru` pada konfigurasinya).
 *
 * Seluruh perilaku (validasi tautan, pengurutan, sembunyikan, hapus, render kartu, serta keterangan
 * "Aplikasi Sementara Dikembangkan" ketika daftarnya belum diisi) ada pada MODUL BERSAMA
 * `inc/daftar_apps.php` — JANGAN menyalin logikanya ke berkas ini.
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/daftar_apps.php';

daftar_apps_halaman('studio_foto');
