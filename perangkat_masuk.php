<?php
/**
 * HALAMAN "PERANGKAT MASUK" (`perangkat_masuk.php`) — v1.67.0 / skema v48.
 *
 * Permintaan pemilik: *"File yang terkirim masuk di akun Kepala sekolah (Kepala Sekolah bisa meninjau
 * dokumen tersebut dengan: Dokumen Disetujui, Dokumen Direvisi, Dokumen Ditolak sekaligus bisa
 * mendownload), dan Pengawas (Pengawas hanya lihat dan download). Hasil tinjauan kepala sekolah
 * terkirim ke akun guru"*.
 *
 * Halaman ini dipakai DUA peran (kepala sekolah/madrasah + Super Admin dalam konteks untuk meninjau,
 * pengawas untuk melihat & mengunduh) dan seluruh logikanya ada di modul `inc/perangkat.php`.
 * **Jangan menyalin logikanya ke halaman lain.**
 */
declare(strict_types=1);
require_once __DIR__ . '/inc/init.php';

perangkat_masuk_halaman();
